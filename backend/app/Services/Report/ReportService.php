<?php

namespace App\Services\Report;

use App\Enums\ApprovalStatus;
use App\Enums\BudgetCategory;
use App\Models\MonthlyPlan;
use App\Models\MonthlyRealization;
use App\Models\Program;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class ReportService
{
    public function generateMonthlyReport(int $month, int $year): array
    {
        $data = $this->getMonthlyReportData($month, $year);

        return [
            'title' => "Laporan Realisasi Anggaran Bulan {$this->getMonthName($month)} {$year}",
            'period' => "{$this->getMonthName($month)} {$year}",
            'generated_at' => now()->format('d/m/Y H:i'),
            'data' => $data,
            'summary' => $this->calculateSummary($data),
        ];
    }

    public function generateQuarterlyReport(int $quarter, int $year): array
    {
        $months = $this->getQuarterMonths($quarter);
        $data = [];

        foreach ($months as $month) {
            $data[$month] = $this->getMonthlyReportData($month, $year);
        }

        return [
            'title' => "Laporan Realisasi Anggaran Triwulan {$quarter} Tahun {$year}",
            'period' => "Triwulan {$quarter} ({$this->getMonthName($months[0])} - {$this->getMonthName($months[2])}) {$year}",
            'generated_at' => now()->format('d/m/Y H:i'),
            'data' => $data,
            'summary' => $this->calculateQuarterlySummary($data),
        ];
    }

    public function generateYearlyReport(int $year): array
    {
        $data = [];

        for ($month = 1; $month <= 12; $month++) {
            $data[$month] = $this->getMonthlyReportData($month, $year);
        }

        return [
            'title' => "Laporan Realisasi Anggaran Tahunan {$year}",
            'period' => "Tahun {$year}",
            'generated_at' => now()->format('d/m/Y H:i'),
            'data' => $data,
            'summary' => $this->calculateYearlySummary($data, $year),
        ];
    }

    public function generateCategoryReport(string $category, int $year): array
    {
        $budgetCategory = BudgetCategory::from($category);

        $programs = Program::where('fiscal_year', $year)
            ->where('category', $budgetCategory)
            ->where('is_active', true)
            ->with(['activities.subActivities.budgetItems.monthlyPlans' => function ($q) use ($year) {
                $q->where('year', $year)->with('realization');
            }])
            ->get();

        $data = $programs->map(function ($program) {
            $items = [];
            $totalBudget = 0;
            $totalRealized = 0;

            foreach ($program->activities as $activity) {
                foreach ($activity->subActivities as $subActivity) {
                    foreach ($subActivity->budgetItems as $budgetItem) {
                        $planned = $budgetItem->monthlyPlans->sum('planned_amount');
                        $realized = $budgetItem->monthlyPlans
                            ->filter(fn($p) => $p->realization && $p->realization->status === ApprovalStatus::APPROVED)
                            ->sum(fn($p) => $p->realization->realized_amount);

                        $totalBudget += $budgetItem->total_budget;
                        $totalRealized += $realized;

                        $items[] = [
                            'code' => $budgetItem->code,
                            'name' => $budgetItem->name,
                            'activity' => $activity->name,
                            'sub_activity' => $subActivity->name,
                            'budget' => (float) $budgetItem->total_budget,
                            'planned' => (float) $planned,
                            'realized' => (float) $realized,
                            'percentage' => $budgetItem->total_budget > 0
                                ? round(($realized / $budgetItem->total_budget) * 100, 2)
                                : 0,
                        ];
                    }
                }
            }

            return [
                'program' => [
                    'code' => $program->code,
                    'name' => $program->name,
                    'budget' => (float) $program->total_budget,
                    'realized' => (float) $totalRealized,
                    'percentage' => $program->total_budget > 0
                        ? round(($totalRealized / $program->total_budget) * 100, 2)
                        : 0,
                ],
                'items' => $items,
            ];
        })->toArray();

        return [
            'title' => "Laporan Realisasi {$budgetCategory->label()} Tahun {$year}",
            'category' => $budgetCategory->label(),
            'period' => "Tahun {$year}",
            'generated_at' => now()->format('d/m/Y H:i'),
            'data' => $data,
        ];
    }

    public function exportToPdf(array $reportData, string $template = 'monthly'): \Illuminate\Http\Response
    {
        $pdf = Pdf::loadView("reports.{$template}", ['report' => $reportData]);
        $pdf->setPaper('a4', 'landscape');

        return $pdf->download("laporan_{$template}_" . now()->format('Ymd_His') . '.pdf');
    }

    private function getMonthlyReportData(int $month, int $year): array
    {
        return MonthlyPlan::where('month', $month)
            ->where('year', $year)
            ->with([
                'budgetItem.subActivity.activity.program',
                'realization',
            ])
            ->get()
            ->map(function ($plan) {
                $realization = $plan->realization;
                $isApproved = $realization && $realization->status === ApprovalStatus::APPROVED;

                return [
                    'program' => $plan->budgetItem->subActivity->activity->program->name ?? '-',
                    'activity' => $plan->budgetItem->subActivity->activity->name ?? '-',
                    'sub_activity' => $plan->budgetItem->subActivity->name ?? '-',
                    'budget_item' => $plan->budgetItem->name ?? '-',
                    'code' => $plan->budgetItem->code ?? '-',
                    'unit' => $plan->budgetItem->unit ?? '-',
                    'planned_volume' => (float) $plan->planned_volume,
                    'planned_amount' => (float) $plan->planned_amount,
                    'realized_volume' => $isApproved ? (float) $realization->realized_volume : 0,
                    'realized_amount' => $isApproved ? (float) $realization->realized_amount : 0,
                    'deviation_amount' => $isApproved ? (float) $realization->deviation_amount : 0,
                    'deviation_percentage' => $isApproved ? (float) $realization->deviation_percentage : 0,
                    'status' => $realization ? $realization->status->label() : 'Belum ada realisasi',
                ];
            })
            ->toArray();
    }

    private function calculateSummary(array $data): array
    {
        $totalPlanned = array_sum(array_column($data, 'planned_amount'));
        $totalRealized = array_sum(array_column($data, 'realized_amount'));

        return [
            'total_items' => count($data),
            'total_planned' => $totalPlanned,
            'total_realized' => $totalRealized,
            'total_deviation' => $totalRealized - $totalPlanned,
            'percentage' => $totalPlanned > 0
                ? round(($totalRealized / $totalPlanned) * 100, 2)
                : 0,
        ];
    }

    private function calculateQuarterlySummary(array $data): array
    {
        $totalPlanned = 0;
        $totalRealized = 0;
        $totalItems = 0;

        foreach ($data as $monthData) {
            $totalPlanned += array_sum(array_column($monthData, 'planned_amount'));
            $totalRealized += array_sum(array_column($monthData, 'realized_amount'));
            $totalItems += count($monthData);
        }

        return [
            'total_items' => $totalItems,
            'total_planned' => $totalPlanned,
            'total_realized' => $totalRealized,
            'total_deviation' => $totalRealized - $totalPlanned,
            'percentage' => $totalPlanned > 0
                ? round(($totalRealized / $totalPlanned) * 100, 2)
                : 0,
        ];
    }

    private function calculateYearlySummary(array $data, int $year): array
    {
        $totalBudget = Program::where('fiscal_year', $year)
            ->where('is_active', true)
            ->sum('total_budget');

        $totalPlanned = 0;
        $totalRealized = 0;

        foreach ($data as $monthData) {
            $totalPlanned += array_sum(array_column($monthData, 'planned_amount'));
            $totalRealized += array_sum(array_column($monthData, 'realized_amount'));
        }

        return [
            'total_budget' => (float) $totalBudget,
            'total_planned' => $totalPlanned,
            'total_realized' => $totalRealized,
            'total_deviation' => $totalRealized - $totalPlanned,
            'budget_percentage' => $totalBudget > 0
                ? round(($totalRealized / $totalBudget) * 100, 2)
                : 0,
            'plan_percentage' => $totalPlanned > 0
                ? round(($totalRealized / $totalPlanned) * 100, 2)
                : 0,
        ];
    }

    private function getQuarterMonths(int $quarter): array
    {
        return match ($quarter) {
            1 => [1, 2, 3],
            2 => [4, 5, 6],
            3 => [7, 8, 9],
            4 => [10, 11, 12],
            default => [1, 2, 3],
        };
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
