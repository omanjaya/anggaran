<?php

namespace App\Jobs;

use App\Models\DeviationAlert;
use App\Models\MonthlyPlan;
use App\Models\MonthlyRealization;
use App\Models\User;
use App\Notifications\DeviationAlertNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckDeviationAlerts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $year;
    protected int $month;

    public function __construct(?int $year = null, ?int $month = null)
    {
        $this->year = $year ?? (int) date('Y');
        $this->month = $month ?? (int) date('n');
    }

    public function handle(): void
    {
        $alertsCreated = 0;
        $currentDate = now();

        Log::info("Checking deviation alerts for {$this->month}/{$this->year}");

        // Get all monthly plans
        $monthlyPlans = MonthlyPlan::with(['budgetItem.subActivity'])
            ->where('year', $this->year)
            ->get();

        foreach ($monthlyPlans as $plan) {
            if ($plan->planned_amount <= 0) {
                continue;
            }

            $realization = MonthlyRealization::where([
                'budget_item_id' => $plan->budget_item_id,
                'month' => $plan->month,
                'year' => $plan->year,
            ])->where('status', 'APPROVED')->first();

            // Check for unrealized items in past months
            if ($plan->month < $this->month && !$realization) {
                $this->createOrUpdateAlert(
                    $plan,
                    null,
                    'NOT_REALIZED',
                    'HIGH',
                    "Item belanja '{$plan->budgetItem->name}' bulan " . $this->getMonthName($plan->month) . " belum direalisasi."
                );
                $alertsCreated++;
                continue;
            }

            if ($realization) {
                $deviationPercentage = (($realization->realized_amount - $plan->planned_amount) / $plan->planned_amount) * 100;

                // Under realization (< 70%)
                if ($deviationPercentage < -30) {
                    $severity = $deviationPercentage < -50 ? 'CRITICAL' : 'HIGH';
                    $this->createOrUpdateAlert(
                        $plan,
                        $realization,
                        'UNDER_REALIZATION',
                        $severity,
                        "Realisasi item '{$plan->budgetItem->name}' hanya " . round(100 + $deviationPercentage, 1) . "% dari rencana.",
                        $deviationPercentage
                    );
                    $alertsCreated++;
                }
                // Over realization (> 110%)
                elseif ($deviationPercentage > 10) {
                    $severity = $deviationPercentage > 30 ? 'CRITICAL' : 'HIGH';
                    $this->createOrUpdateAlert(
                        $plan,
                        $realization,
                        'OVER_REALIZATION',
                        $severity,
                        "Realisasi item '{$plan->budgetItem->name}' melebihi " . round($deviationPercentage, 1) . "% dari rencana.",
                        $deviationPercentage
                    );
                    $alertsCreated++;
                }
            }

            // Check deadline approaching (current month)
            if ($plan->month == $this->month && !$realization) {
                $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $this->month, $this->year);
                $daysLeft = $daysInMonth - $currentDate->day;

                if ($daysLeft <= 7 && $daysLeft > 0) {
                    $this->createOrUpdateAlert(
                        $plan,
                        null,
                        'DEADLINE_APPROACHING',
                        'MEDIUM',
                        "Item '{$plan->budgetItem->name}' harus direalisasi dalam {$daysLeft} hari lagi."
                    );
                    $alertsCreated++;
                }
            }
        }

        Log::info("Deviation alert check completed. {$alertsCreated} alerts created/updated.");

        // Send notifications for critical alerts
        $this->sendNotifications();
    }

    protected function createOrUpdateAlert(
        MonthlyPlan $plan,
        ?MonthlyRealization $realization,
        string $type,
        string $severity,
        string $message,
        float $deviation = 0
    ): void {
        $existingAlert = DeviationAlert::where([
            'budget_item_id' => $plan->budget_item_id,
            'month' => $plan->month,
            'year' => $plan->year,
            'alert_type' => $type,
        ])->whereIn('status', ['ACTIVE', 'ACKNOWLEDGED'])->first();

        if ($existingAlert) {
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

    protected function sendNotifications(): void
    {
        $criticalAlerts = DeviationAlert::with(['budgetItem.subActivity'])
            ->where('status', 'ACTIVE')
            ->whereIn('severity', ['CRITICAL', 'HIGH'])
            ->where('created_at', '>=', now()->subDay())
            ->get();

        if ($criticalAlerts->isEmpty()) {
            return;
        }

        // Get users who should receive alerts (Kadis, Monev)
        $recipients = User::whereIn('role', ['KADIS', 'MONEV', 'ADMIN'])->get();

        foreach ($recipients as $user) {
            try {
                $user->notify(new DeviationAlertNotification($criticalAlerts));
            } catch (\Exception $e) {
                Log::error("Failed to send deviation alert notification to user {$user->id}: {$e->getMessage()}");
            }
        }
    }

    protected function getMonthName(int $month): string
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
