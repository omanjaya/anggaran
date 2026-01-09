<?php

namespace App\Services\Dashboard;

use App\Enums\ApprovalStatus;
use App\Enums\BudgetCategory;
use App\Models\MonthlyPlan;
use App\Models\MonthlyRealization;
use App\Models\Program;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getStats(int $year = null): array
    {
        $year = $year ?? date('Y');

        $totalBudget = Program::where('fiscal_year', $year)
            ->where('is_active', true)
            ->sum('total_budget');

        $totalRealization = MonthlyRealization::whereHas('monthlyPlan', function ($q) use ($year) {
            $q->where('year', $year);
        })
            ->where('status', ApprovalStatus::APPROVED)
            ->sum('realized_amount');

        $realizationPercentage = $totalBudget > 0
            ? ($totalRealization / $totalBudget) * 100
            : 0;

        $pendingApprovals = MonthlyRealization::whereIn('status', [
            ApprovalStatus::SUBMITTED,
            ApprovalStatus::VERIFIED,
        ])->count();

        $categories = $this->getCategoryStats($year);

        return [
            'total_budget' => (float) $totalBudget,
            'total_realization' => (float) $totalRealization,
            'realization_percentage' => round($realizationPercentage, 2),
            'pending_approvals' => $pendingApprovals,
            'categories' => $categories,
            'fiscal_year' => $year,
        ];
    }

    public function getCategoryStats(int $year): array
    {
        $categories = [];

        foreach (BudgetCategory::cases() as $category) {
            $budget = Program::where('fiscal_year', $year)
                ->where('category', $category)
                ->where('is_active', true)
                ->sum('total_budget');

            $realization = MonthlyRealization::whereHas('monthlyPlan.budgetItem.subActivity.activity.program', function ($q) use ($year, $category) {
                $q->where('fiscal_year', $year)
                    ->where('category', $category);
            })
                ->where('status', ApprovalStatus::APPROVED)
                ->sum('realized_amount');

            $percentage = $budget > 0 ? ($realization / $budget) * 100 : 0;

            $categories[] = [
                'code' => $category->value,
                'name' => $category->label(),
                'budget' => (float) $budget,
                'realization' => (float) $realization,
                'percentage' => round($percentage, 2),
            ];
        }

        return $categories;
    }

    public function getMonthlyTrend(int $year): array
    {
        $months = [];

        for ($month = 1; $month <= 12; $month++) {
            $planned = MonthlyPlan::where('year', $year)
                ->where('month', $month)
                ->sum('planned_amount');

            $realized = MonthlyRealization::whereHas('monthlyPlan', function ($q) use ($year, $month) {
                $q->where('year', $year)->where('month', $month);
            })
                ->where('status', ApprovalStatus::APPROVED)
                ->sum('realized_amount');

            $months[] = [
                'month' => $month,
                'month_name' => $this->getMonthName($month),
                'planned' => (float) $planned,
                'realized' => (float) $realized,
                'deviation' => (float) ($realized - $planned),
            ];
        }

        return $months;
    }

    public function getProgramStats(int $year): array
    {
        return Program::where('fiscal_year', $year)
            ->where('is_active', true)
            ->with(['activities.subActivities.budgetItems.monthlyPlans' => function ($q) use ($year) {
                $q->where('year', $year);
            }])
            ->get()
            ->map(function ($program) use ($year) {
                $totalPlanned = 0;
                foreach ($program->activities as $activity) {
                    foreach ($activity->subActivities as $subActivity) {
                        foreach ($subActivity->budgetItems as $budgetItem) {
                            $totalPlanned += $budgetItem->monthlyPlans->sum('planned_amount');
                        }
                    }
                }

                $realized = MonthlyRealization::whereHas('monthlyPlan.budgetItem.subActivity.activity', function ($q) use ($program) {
                    $q->where('program_id', $program->id);
                })
                    ->whereHas('monthlyPlan', function ($q) use ($year) {
                        $q->where('year', $year);
                    })
                    ->where('status', ApprovalStatus::APPROVED)
                    ->sum('realized_amount');

                return [
                    'id' => $program->id,
                    'code' => $program->code,
                    'name' => $program->name,
                    'category' => $program->category->label(),
                    'budget' => (float) $program->total_budget,
                    'planned' => (float) $totalPlanned,
                    'realized' => (float) $realized,
                    'percentage' => $program->total_budget > 0
                        ? round(($realized / $program->total_budget) * 100, 2)
                        : 0,
                ];
            })
            ->toArray();
    }

    public function getRecentActivities(int $limit = 10): array
    {
        return MonthlyRealization::with([
            'monthlyPlan.budgetItem.subActivity.activity.program',
            'submittedBy',
            'verifiedBy',
            'approvedBy',
        ])
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($realization) {
                return [
                    'id' => $realization->id,
                    'program' => $realization->monthlyPlan->budgetItem->subActivity->activity->program->name ?? '-',
                    'budget_item' => $realization->monthlyPlan->budgetItem->name ?? '-',
                    'month' => $realization->monthlyPlan->month,
                    'year' => $realization->monthlyPlan->year,
                    'amount' => (float) $realization->realized_amount,
                    'status' => $realization->status->value,
                    'status_label' => $realization->status->label(),
                    'updated_at' => $realization->updated_at->toISOString(),
                ];
            })
            ->toArray();
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
