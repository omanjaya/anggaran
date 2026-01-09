<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApprovalStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ApprovalHistory;
use App\Models\MonthlyPlan;
use App\Models\MonthlyRealization;
use App\Models\RealizationDocument;
use App\Models\User;
use App\Notifications\RealizationApproved;
use App\Notifications\RealizationRejected;
use App\Notifications\RealizationSubmitted;
use App\Notifications\RealizationVerified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RealizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MonthlyRealization::with([
            'monthlyPlan.budgetItem.subActivity.activity.program',
            'submittedBy',
            'verifiedBy',
            'approvedBy',
        ]);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('month')) {
            $query->whereHas('monthlyPlan', function ($q) use ($request) {
                $q->where('month', $request->month);
            });
        }

        if ($request->has('year')) {
            $query->whereHas('monthlyPlan', function ($q) use ($request) {
                $q->where('year', $request->year);
            });
        }

        $realizations = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $realizations->items(),
            'meta' => [
                'current_page' => $realizations->currentPage(),
                'last_page' => $realizations->lastPage(),
                'per_page' => $realizations->perPage(),
                'total' => $realizations->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'monthly_plan_id' => 'required|exists:monthly_plans,id',
            'realized_volume' => 'required|numeric|min:0',
            'realized_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        // Check if realization already exists
        if (MonthlyRealization::where('monthly_plan_id', $validated['monthly_plan_id'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Realisasi untuk perencanaan ini sudah ada',
            ], 422);
        }

        $plan = MonthlyPlan::findOrFail($validated['monthly_plan_id']);

        // Calculate deviations
        $deviationVolume = $validated['realized_volume'] - $plan->planned_volume;
        $deviationAmount = $validated['realized_amount'] - $plan->planned_amount;
        $deviationPercentage = $plan->planned_amount > 0
            ? (($validated['realized_amount'] - $plan->planned_amount) / $plan->planned_amount) * 100
            : 0;

        $realization = MonthlyRealization::create([
            'monthly_plan_id' => $validated['monthly_plan_id'],
            'realized_volume' => $validated['realized_volume'],
            'realized_amount' => $validated['realized_amount'],
            'deviation_volume' => $deviationVolume,
            'deviation_amount' => $deviationAmount,
            'deviation_percentage' => $deviationPercentage,
            'status' => ApprovalStatus::DRAFT,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Realisasi berhasil dibuat',
            'data' => $realization->load('monthlyPlan.budgetItem'),
        ], 201);
    }

    public function show(MonthlyRealization $realization): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $realization->load([
                'monthlyPlan.budgetItem.subActivity.activity.program',
                'submittedBy',
                'verifiedBy',
                'approvedBy',
                'documents',
                'approvalHistories.performedBy',
            ]),
        ]);
    }

    public function update(Request $request, MonthlyRealization $realization): JsonResponse
    {
        if ($realization->isLocked()) {
            return response()->json([
                'success' => false,
                'message' => 'Realisasi telah dikunci dan tidak dapat diubah',
            ], 422);
        }

        if (!in_array($realization->status, [ApprovalStatus::DRAFT, ApprovalStatus::REJECTED])) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya realisasi dengan status Draft atau Ditolak yang dapat diubah',
            ], 422);
        }

        $validated = $request->validate([
            'realized_volume' => 'sometimes|numeric|min:0',
            'realized_amount' => 'sometimes|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $plan = $realization->monthlyPlan;

        if (isset($validated['realized_volume']) || isset($validated['realized_amount'])) {
            $realizedVolume = $validated['realized_volume'] ?? $realization->realized_volume;
            $realizedAmount = $validated['realized_amount'] ?? $realization->realized_amount;

            $validated['deviation_volume'] = $realizedVolume - $plan->planned_volume;
            $validated['deviation_amount'] = $realizedAmount - $plan->planned_amount;
            $validated['deviation_percentage'] = $plan->planned_amount > 0
                ? (($realizedAmount - $plan->planned_amount) / $plan->planned_amount) * 100
                : 0;
        }

        $realization->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Realisasi berhasil diupdate',
            'data' => $realization->fresh()->load('monthlyPlan.budgetItem'),
        ]);
    }

    public function submit(MonthlyRealization $realization): JsonResponse
    {
        if ($realization->status !== ApprovalStatus::DRAFT && $realization->status !== ApprovalStatus::REJECTED) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya realisasi dengan status Draft atau Ditolak yang dapat diajukan',
            ], 422);
        }

        DB::transaction(function () use ($realization) {
            $oldStatus = $realization->status;

            $realization->update([
                'status' => ApprovalStatus::SUBMITTED,
                'submitted_by' => auth()->id(),
                'submitted_at' => now(),
                'rejection_reason' => null,
            ]);

            ApprovalHistory::create([
                'monthly_realization_id' => $realization->id,
                'from_status' => $oldStatus,
                'to_status' => ApprovalStatus::SUBMITTED,
                'action' => 'submit',
                'performed_by' => auth()->id(),
                'created_at' => now(),
            ]);
        });

        // Send notification to Bendahara users
        $realization->load('monthlyPlan.budgetItem');
        $bendaharaUsers = User::where('role', UserRole::BENDAHARA)
            ->where('is_active', true)
            ->get();
        Notification::send($bendaharaUsers, new RealizationSubmitted($realization));

        return response()->json([
            'success' => true,
            'message' => 'Realisasi berhasil diajukan untuk verifikasi',
            'data' => $realization->fresh(),
        ]);
    }

    public function verify(Request $request, MonthlyRealization $realization): JsonResponse
    {
        if ($realization->status !== ApprovalStatus::SUBMITTED) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya realisasi dengan status Diajukan yang dapat diverifikasi',
            ], 422);
        }

        $validated = $request->validate([
            'action' => 'required|in:verify,reject',
            'notes' => 'nullable|string',
            'rejection_reason' => 'required_if:action,reject|nullable|string',
        ]);

        DB::transaction(function () use ($realization, $validated) {
            $oldStatus = $realization->status;
            $newStatus = $validated['action'] === 'verify' ? ApprovalStatus::VERIFIED : ApprovalStatus::REJECTED;

            $updateData = [
                'status' => $newStatus,
                'verified_by' => auth()->id(),
                'verified_at' => now(),
            ];

            if ($validated['action'] === 'reject') {
                $updateData['rejection_reason'] = $validated['rejection_reason'];
            }

            $realization->update($updateData);

            ApprovalHistory::create([
                'monthly_realization_id' => $realization->id,
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
                'action' => $validated['action'],
                'notes' => $validated['notes'] ?? null,
                'performed_by' => auth()->id(),
                'created_at' => now(),
            ]);
        });

        // Send notifications
        $realization->load('monthlyPlan.budgetItem', 'submittedBy', 'verifiedBy');

        if ($validated['action'] === 'verify') {
            // Notify Kadis users for approval
            $kadisUsers = User::where('role', UserRole::KADIS)
                ->where('is_active', true)
                ->get();
            Notification::send($kadisUsers, new RealizationVerified($realization));
        } else {
            // Notify the submitter about rejection
            if ($realization->submittedBy) {
                $realization->submittedBy->notify(new RealizationRejected($realization, $validated['rejection_reason'] ?? ''));
            }
        }

        $message = $validated['action'] === 'verify'
            ? 'Realisasi berhasil diverifikasi'
            : 'Realisasi ditolak';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $realization->fresh(),
        ]);
    }

    public function approve(Request $request, MonthlyRealization $realization): JsonResponse
    {
        if ($realization->status !== ApprovalStatus::VERIFIED) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya realisasi dengan status Terverifikasi yang dapat disetujui',
            ], 422);
        }

        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'notes' => 'nullable|string',
            'rejection_reason' => 'required_if:action,reject|nullable|string',
        ]);

        DB::transaction(function () use ($realization, $validated) {
            $oldStatus = $realization->status;
            $newStatus = $validated['action'] === 'approve' ? ApprovalStatus::APPROVED : ApprovalStatus::REJECTED;

            $updateData = [
                'status' => $newStatus,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ];

            if ($validated['action'] === 'reject') {
                $updateData['rejection_reason'] = $validated['rejection_reason'];
            }

            // Auto-lock upon approval
            if ($validated['action'] === 'approve') {
                $updateData['locked_at'] = now();
                $updateData['locked_by'] = auth()->id();
            }

            $realization->update($updateData);

            ApprovalHistory::create([
                'monthly_realization_id' => $realization->id,
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
                'action' => $validated['action'],
                'notes' => $validated['notes'] ?? null,
                'performed_by' => auth()->id(),
                'created_at' => now(),
            ]);
        });

        // Send notifications
        $realization->load('monthlyPlan.budgetItem', 'submittedBy', 'approvedBy');

        if ($validated['action'] === 'approve') {
            // Notify the submitter about approval
            if ($realization->submittedBy) {
                $realization->submittedBy->notify(new RealizationApproved($realization));
            }
        } else {
            // Notify the submitter about rejection
            if ($realization->submittedBy) {
                $realization->submittedBy->notify(new RealizationRejected($realization, $validated['rejection_reason'] ?? ''));
            }
        }

        $message = $validated['action'] === 'approve'
            ? 'Realisasi berhasil disetujui dan dikunci'
            : 'Realisasi ditolak';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $realization->fresh(),
        ]);
    }

    public function lock(MonthlyRealization $realization): JsonResponse
    {
        if ($realization->status !== ApprovalStatus::APPROVED) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya realisasi yang sudah disetujui yang dapat dikunci',
            ], 422);
        }

        if ($realization->isLocked()) {
            return response()->json([
                'success' => false,
                'message' => 'Realisasi sudah dikunci',
            ], 422);
        }

        $realization->update([
            'locked_at' => now(),
            'locked_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Realisasi berhasil dikunci',
            'data' => $realization->fresh(),
        ]);
    }

    public function unlock(MonthlyRealization $realization): JsonResponse
    {
        if (!$realization->isLocked()) {
            return response()->json([
                'success' => false,
                'message' => 'Realisasi tidak dalam status terkunci',
            ], 422);
        }

        $realization->update([
            'locked_at' => null,
            'locked_by' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Realisasi berhasil dibuka kuncinya',
            'data' => $realization->fresh(),
        ]);
    }

    public function pendingVerification(Request $request): JsonResponse
    {
        $realizations = MonthlyRealization::with(['monthlyPlan.budgetItem.subActivity.activity.program', 'submittedBy'])
            ->where('status', ApprovalStatus::SUBMITTED)
            ->orderBy('submitted_at')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $realizations->items(),
            'meta' => [
                'current_page' => $realizations->currentPage(),
                'last_page' => $realizations->lastPage(),
                'per_page' => $realizations->perPage(),
                'total' => $realizations->total(),
            ],
        ]);
    }

    public function pendingApproval(Request $request): JsonResponse
    {
        $realizations = MonthlyRealization::with(['monthlyPlan.budgetItem.subActivity.activity.program', 'submittedBy', 'verifiedBy'])
            ->where('status', ApprovalStatus::VERIFIED)
            ->orderBy('verified_at')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $realizations->items(),
            'meta' => [
                'current_page' => $realizations->currentPage(),
                'last_page' => $realizations->lastPage(),
                'per_page' => $realizations->perPage(),
                'total' => $realizations->total(),
            ],
        ]);
    }

    public function uploadDocument(Request $request, MonthlyRealization $realization): JsonResponse
    {
        if (!in_array($realization->status, [ApprovalStatus::DRAFT, ApprovalStatus::REJECTED])) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen hanya dapat diupload untuk realisasi dengan status Draft atau Ditolak',
            ], 422);
        }

        $validated = $request->validate([
            'document' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:10240',
        ]);

        $file = $request->file('document');
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $extension;

        $path = $file->storeAs('realization-documents/' . $realization->id, $filename, 'public');

        $document = RealizationDocument::create([
            'monthly_realization_id' => $realization->id,
            'filename' => $filename,
            'original_filename' => $originalName,
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'uploaded_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dokumen berhasil diupload',
            'data' => $document->load('uploadedBy'),
        ], 201);
    }

    public function getDocuments(MonthlyRealization $realization): JsonResponse
    {
        $documents = $realization->documents()->with('uploadedBy')->get();

        return response()->json([
            'success' => true,
            'data' => $documents,
        ]);
    }

    public function downloadDocument(MonthlyRealization $realization, RealizationDocument $document): mixed
    {
        if ($document->monthly_realization_id !== $realization->id) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen tidak ditemukan',
            ], 404);
        }

        if (!Storage::disk('public')->exists($document->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan di server',
            ], 404);
        }

        return Storage::disk('public')->download($document->file_path, $document->original_filename);
    }

    public function deleteDocument(MonthlyRealization $realization, RealizationDocument $document): JsonResponse
    {
        if ($document->monthly_realization_id !== $realization->id) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen tidak ditemukan',
            ], 404);
        }

        if (!in_array($realization->status, [ApprovalStatus::DRAFT, ApprovalStatus::REJECTED])) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen hanya dapat dihapus untuk realisasi dengan status Draft atau Ditolak',
            ], 422);
        }

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return response()->json([
            'success' => true,
            'message' => 'Dokumen berhasil dihapus',
        ]);
    }

    public function batchVerify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:monthly_realizations,id',
            'action' => 'required|in:verify,reject',
            'notes' => 'nullable|string',
            'rejection_reason' => 'required_if:action,reject|nullable|string',
        ]);

        $realizations = MonthlyRealization::whereIn('id', $validated['ids'])
            ->where('status', ApprovalStatus::SUBMITTED)
            ->get();

        if ($realizations->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada realisasi yang dapat diverifikasi',
            ], 422);
        }

        $processed = 0;
        $skipped = 0;

        DB::transaction(function () use ($realizations, $validated, &$processed, &$skipped) {
            foreach ($realizations as $realization) {
                $oldStatus = $realization->status;
                $newStatus = $validated['action'] === 'verify' ? ApprovalStatus::VERIFIED : ApprovalStatus::REJECTED;

                $updateData = [
                    'status' => $newStatus,
                    'verified_by' => auth()->id(),
                    'verified_at' => now(),
                ];

                if ($validated['action'] === 'reject') {
                    $updateData['rejection_reason'] = $validated['rejection_reason'];
                }

                $realization->update($updateData);

                ApprovalHistory::create([
                    'monthly_realization_id' => $realization->id,
                    'from_status' => $oldStatus,
                    'to_status' => $newStatus,
                    'action' => $validated['action'],
                    'notes' => $validated['notes'] ?? null,
                    'performed_by' => auth()->id(),
                    'created_at' => now(),
                ]);

                $processed++;
            }
        });

        // Send notifications
        foreach ($realizations as $realization) {
            $realization->load('monthlyPlan.budgetItem', 'submittedBy', 'verifiedBy');

            if ($validated['action'] === 'verify') {
                $kadisUsers = User::where('role', UserRole::KADIS)
                    ->where('is_active', true)
                    ->get();
                Notification::send($kadisUsers, new RealizationVerified($realization));
            } else {
                if ($realization->submittedBy) {
                    $realization->submittedBy->notify(new RealizationRejected($realization, $validated['rejection_reason'] ?? ''));
                }
            }
        }

        $message = $validated['action'] === 'verify'
            ? "{$processed} realisasi berhasil diverifikasi"
            : "{$processed} realisasi ditolak";

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'processed' => $processed,
                'skipped' => $skipped,
            ],
        ]);
    }

    public function batchApprove(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:monthly_realizations,id',
            'action' => 'required|in:approve,reject',
            'notes' => 'nullable|string',
            'rejection_reason' => 'required_if:action,reject|nullable|string',
        ]);

        $realizations = MonthlyRealization::whereIn('id', $validated['ids'])
            ->where('status', ApprovalStatus::VERIFIED)
            ->get();

        if ($realizations->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada realisasi yang dapat disetujui',
            ], 422);
        }

        $processed = 0;
        $skipped = 0;

        DB::transaction(function () use ($realizations, $validated, &$processed, &$skipped) {
            foreach ($realizations as $realization) {
                $oldStatus = $realization->status;
                $newStatus = $validated['action'] === 'approve' ? ApprovalStatus::APPROVED : ApprovalStatus::REJECTED;

                $updateData = [
                    'status' => $newStatus,
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                ];

                if ($validated['action'] === 'reject') {
                    $updateData['rejection_reason'] = $validated['rejection_reason'];
                }

                // Auto-lock upon batch approval
                if ($validated['action'] === 'approve') {
                    $updateData['locked_at'] = now();
                    $updateData['locked_by'] = auth()->id();
                }

                $realization->update($updateData);

                ApprovalHistory::create([
                    'monthly_realization_id' => $realization->id,
                    'from_status' => $oldStatus,
                    'to_status' => $newStatus,
                    'action' => $validated['action'],
                    'notes' => $validated['notes'] ?? null,
                    'performed_by' => auth()->id(),
                    'created_at' => now(),
                ]);

                $processed++;
            }
        });

        // Send notifications
        foreach ($realizations as $realization) {
            $realization->load('monthlyPlan.budgetItem', 'submittedBy', 'approvedBy');

            if ($validated['action'] === 'approve') {
                if ($realization->submittedBy) {
                    $realization->submittedBy->notify(new RealizationApproved($realization));
                }
            } else {
                if ($realization->submittedBy) {
                    $realization->submittedBy->notify(new RealizationRejected($realization, $validated['rejection_reason'] ?? ''));
                }
            }
        }

        $message = $validated['action'] === 'approve'
            ? "{$processed} realisasi berhasil disetujui dan dikunci"
            : "{$processed} realisasi ditolak";

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'processed' => $processed,
                'skipped' => $skipped,
            ],
        ]);
    }
}
