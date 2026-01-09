<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DpaImportService;
use App\Services\DpaPdfParserService;
use App\Models\BudgetItem;
use App\Models\BudgetItemDetail;
use App\Models\MonthlyPlan;
use App\Models\ActivityIndicator;
use App\Models\SubActivity;
use App\Models\Activity;
use App\Models\Program;
use App\Models\AccountCode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DpaImportController extends Controller
{
    protected DpaImportService $importService;

    public function __construct(DpaImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * Preview DPA PDF content without importing
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:10240', // Max 10MB
        ]);

        try {
            $file = $request->file('file');
            $path = $file->store('temp/dpa', 'local');
            $fullPath = Storage::disk('local')->path($path);

            $parser = new DpaPdfParserService();
            $data = $parser->parse($fullPath);

            // Clean up temp file
            Storage::disk('local')->delete($path);

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Preview berhasil. Periksa data sebelum import.',
            ]);
        } catch (\Exception $e) {
            Log::error('DPA Preview Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal membaca PDF: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Import single DPA PDF
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:10240',
        ]);

        try {
            $file = $request->file('file');
            $path = $file->store('temp/dpa', 'local');
            $fullPath = Storage::disk('local')->path($path);

            $result = $this->importService->import($fullPath, auth()->id());

            // Clean up temp file
            Storage::disk('local')->delete($path);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Import DPA berhasil',
                    'data' => $result['data'],
                    'log' => $result['log'],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'],
                    'log' => $result['log'],
                ], 422);
            }
        } catch (\Exception $e) {
            Log::error('DPA Import Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal import DPA: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import multiple DPA PDFs
     */
    public function importBatch(Request $request): JsonResponse
    {
        $request->validate([
            'files' => 'required|array|min:1|max:20',
            'files.*' => 'file|mimes:pdf|max:10240',
        ]);

        $results = [];
        $successCount = 0;
        $failCount = 0;

        foreach ($request->file('files') as $file) {
            try {
                $path = $file->store('temp/dpa', 'local');
                $fullPath = Storage::disk('local')->path($path);

                $result = $this->importService->import($fullPath, auth()->id());

                // Clean up temp file
                Storage::disk('local')->delete($path);

                $results[] = [
                    'filename' => $file->getClientOriginalName(),
                    'success' => $result['success'],
                    'message' => $result['success'] ? 'Berhasil' : $result['error'],
                    'log' => $result['log'] ?? [],
                ];

                if ($result['success']) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            } catch (\Exception $e) {
                $results[] = [
                    'filename' => $file->getClientOriginalName(),
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
                $failCount++;
            }
        }

        return response()->json([
            'success' => $failCount === 0,
            'message' => "Import selesai: {$successCount} berhasil, {$failCount} gagal",
            'summary' => [
                'total' => count($request->file('files')),
                'success' => $successCount,
                'failed' => $failCount,
            ],
            'results' => $results,
        ]);
    }

    /**
     * Clear all DPA data (for testing purposes)
     */
    public function clearAll(): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Delete in correct order (respect foreign keys)
            $budgetItemDetails = BudgetItemDetail::count();
            BudgetItemDetail::query()->delete();

            $monthlyPlans = MonthlyPlan::count();
            MonthlyPlan::query()->delete();

            $activityIndicators = ActivityIndicator::count();
            ActivityIndicator::query()->delete();

            $budgetItems = BudgetItem::count();
            BudgetItem::query()->delete();

            $subActivities = SubActivity::count();
            SubActivity::query()->delete();

            $activities = Activity::count();
            Activity::query()->delete();

            $programs = Program::count();
            Program::query()->delete();

            $accountCodes = AccountCode::count();
            AccountCode::query()->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Semua data DPA berhasil dihapus',
                'deleted' => [
                    'budget_item_details' => $budgetItemDetails,
                    'monthly_plans' => $monthlyPlans,
                    'activity_indicators' => $activityIndicators,
                    'budget_items' => $budgetItems,
                    'sub_activities' => $subActivities,
                    'activities' => $activities,
                    'programs' => $programs,
                    'account_codes' => $accountCodes,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Clear DPA Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data: ' . $e->getMessage(),
            ], 500);
        }
    }
}
