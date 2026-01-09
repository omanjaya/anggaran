<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviationAlert;
use App\Models\BudgetItem;
use App\Models\MonthlyPlan;
use App\Models\MonthlyRealization;
use App\Notifications\DeviationAlertNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeviationAlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DeviationAlert::with(['budgetItem.subActivity', 'realization', 'acknowledgedBy', 'resolvedBy']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->has('alert_type')) {
            $query->where('alert_type', $request->alert_type);
        }

        if ($request->has('month') && $request->has('year')) {
            $query->where('month', $request->month)->where('year', $request->year);
        }

        if ($request->has('sub_activity_id')) {
            $query->whereHas('budgetItem', function ($q) use ($request) {
                $q->where('sub_activity_id', $request->sub_activity_id);
            });
        }

        $alerts = $query->orderBy('severity', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $alerts,
        ]);
    }

    public function dashboard(): JsonResponse
    {
        $activeAlerts = DeviationAlert::active()->count();
        $criticalAlerts = DeviationAlert::active()->critical()->count();
        $highAlerts = DeviationAlert::active()->high()->count();

        $byType = DeviationAlert::active()
            ->select('alert_type', DB::raw('count(*) as count'))
            ->groupBy('alert_type')
            ->get()
            ->pluck('count', 'alert_type');

        $bySeverity = DeviationAlert::active()
            ->select('severity', DB::raw('count(*) as count'))
            ->groupBy('severity')
            ->get()
            ->pluck('count', 'severity');

        $recentAlerts = DeviationAlert::active()
            ->with(['budgetItem.subActivity'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_active' => $activeAlerts,
                'critical' => $criticalAlerts,
                'high' => $highAlerts,
                'by_type' => $byType,
                'by_severity' => $bySeverity,
                'recent_alerts' => $recentAlerts,
            ],
        ]);
    }

    public function check(Request $request): JsonResponse
    {
        $year = $request->get('year', date('Y'));
        $month = $request->get('month', date('n'));

        $alertsCreated = 0;
        $currentDate = now();

        // Get all monthly plans with their realizations
        $monthlyPlans = MonthlyPlan::with(['budgetItem.subActivity', 'realization'])
            ->where('year', $year)
            ->get();

        foreach ($monthlyPlans as $plan) {
            // Skip if no planned amount
            if ($plan->planned_amount <= 0) {
                continue;
            }

            // Get realization for this plan (via relationship)
            $realization = $plan->realization;

            // Only check approved realizations for deviation
            $approvedRealization = $realization && $realization->status?->value === 'APPROVED' ? $realization : null;

            // Check if month has passed and no realization at all
            if ($plan->month < $month && !$realization) {
                $this->createAlert($plan, null, 'NOT_REALIZED', 'HIGH',
                    "Item belanja '{$plan->budgetItem->name}' bulan " . $this->getMonthName($plan->month) . " belum direalisasi.");
                $alertsCreated++;
                continue;
            }

            // Check deviation for any realization (not just approved, for early warning)
            if ($realization) {
                $deviationPercentage = $plan->planned_amount > 0
                    ? (($realization->realized_amount - $plan->planned_amount) / $plan->planned_amount) * 100
                    : 0;

                // Under realization (< 70%)
                if ($deviationPercentage < -30) {
                    $severity = $deviationPercentage < -50 ? 'CRITICAL' : 'HIGH';
                    $this->createAlert($plan, $realization, 'UNDER_REALIZATION', $severity,
                        "Realisasi item '{$plan->budgetItem->name}' hanya " . round(100 + $deviationPercentage, 1) . "% dari rencana.",
                        $deviationPercentage);
                    $alertsCreated++;
                }
                // Over realization (> 110%)
                elseif ($deviationPercentage > 10) {
                    $severity = $deviationPercentage > 30 ? 'CRITICAL' : 'HIGH';
                    $this->createAlert($plan, $realization, 'OVER_REALIZATION', $severity,
                        "Realisasi item '{$plan->budgetItem->name}' melebihi " . round($deviationPercentage, 1) . "% dari rencana.",
                        $deviationPercentage);
                    $alertsCreated++;
                }
            }

            // Check deadline approaching (current month)
            if ($plan->month == $month && !$realization) {
                $daysLeft = cal_days_in_month(CAL_GREGORIAN, $month, $year) - $currentDate->day;
                if ($daysLeft <= 7 && $daysLeft > 0) {
                    $this->createAlert($plan, null, 'DEADLINE_APPROACHING', 'MEDIUM',
                        "Item '{$plan->budgetItem->name}' harus direalisasi dalam {$daysLeft} hari lagi.");
                    $alertsCreated++;
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Pemeriksaan deviasi selesai. {$alertsCreated} alert baru dibuat.",
            'data' => ['alerts_created' => $alertsCreated],
        ]);
    }

    public function acknowledge(Request $request, int $id): JsonResponse
    {
        $alert = DeviationAlert::findOrFail($id);

        if ($alert->status !== 'ACTIVE') {
            return response()->json([
                'success' => false,
                'message' => 'Alert sudah diproses sebelumnya.',
            ], 422);
        }

        $alert->update([
            'status' => 'ACKNOWLEDGED',
            'acknowledged_by' => auth()->id(),
            'acknowledged_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Alert berhasil di-acknowledge.',
            'data' => $alert->load(['acknowledgedBy']),
        ]);
    }

    public function resolve(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'resolution_notes' => 'required|string|max:1000',
        ]);

        $alert = DeviationAlert::findOrFail($id);

        if ($alert->status === 'RESOLVED') {
            return response()->json([
                'success' => false,
                'message' => 'Alert sudah diselesaikan sebelumnya.',
            ], 422);
        }

        $alert->update([
            'status' => 'RESOLVED',
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
            'resolution_notes' => $validated['resolution_notes'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Alert berhasil diselesaikan.',
            'data' => $alert->load(['resolvedBy']),
        ]);
    }

    public function dismiss(int $id): JsonResponse
    {
        $alert = DeviationAlert::findOrFail($id);

        if ($alert->status === 'RESOLVED' || $alert->status === 'DISMISSED') {
            return response()->json([
                'success' => false,
                'message' => 'Alert sudah diproses sebelumnya.',
            ], 422);
        }

        $alert->update([
            'status' => 'DISMISSED',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Alert berhasil diabaikan.',
            'data' => $alert,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $alert = DeviationAlert::findOrFail($id);
        $alert->delete();

        return response()->json([
            'success' => true,
            'message' => 'Alert berhasil dihapus.',
        ]);
    }

    private function createAlert(MonthlyPlan $plan, ?MonthlyRealization $realization, string $type, string $severity, string $message, float $deviation = 0): void
    {
        // Check if similar alert already exists and is active
        $existingAlert = DeviationAlert::where([
            'budget_item_id' => $plan->budget_item_id,
            'month' => $plan->month,
            'year' => $plan->year,
            'alert_type' => $type,
        ])->whereIn('status', ['ACTIVE', 'ACKNOWLEDGED'])->first();

        if ($existingAlert) {
            // Update existing alert
            $existingAlert->update([
                'severity' => $severity,
                'message' => $message,
                'deviation_percentage' => $deviation,
                'planned_amount' => $plan->planned_amount,
                'realized_amount' => $realization?->realized_amount ?? 0,
            ]);
            return;
        }

        DeviationAlert::create([
            'budget_item_id' => $plan->budget_item_id,
            'monthly_realization_id' => $realization?->id,
            'month' => $plan->month,
            'year' => $plan->year,
            'alert_type' => $type,
            'severity' => $severity,
            'planned_amount' => $plan->planned_amount,
            'realized_amount' => $realization?->realized_amount ?? 0,
            'deviation_percentage' => $deviation,
            'message' => $message,
            'status' => 'ACTIVE',
        ]);
    }

    private function getMonthName(int $month): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
            4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September',
            10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $months[$month] ?? '';
    }
}
