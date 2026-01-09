<?php

namespace App\Http\Controllers\Api;

use App\Exports\MonthlyReportExport;
use App\Exports\RealisasiExport;
use App\Exports\YearlyReportExport;
use App\Http\Controllers\Controller;
use App\Models\BudgetItem;
use App\Models\Program;
use App\Models\Skpd;
use App\Services\Report\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    protected array $monthNames = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];

    public function __construct(
        private ReportService $reportService
    ) {}

    public function monthly(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2100',
        ]);

        $report = $this->reportService->generateMonthlyReport(
            (int) $request->month,
            (int) $request->year
        );

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    public function quarterly(Request $request): JsonResponse
    {
        $request->validate([
            'quarter' => 'required|integer|min:1|max:4',
            'year' => 'required|integer|min:2020|max:2100',
        ]);

        $report = $this->reportService->generateQuarterlyReport(
            (int) $request->quarter,
            (int) $request->year
        );

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    public function yearly(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
        ]);

        $report = $this->reportService->generateYearlyReport((int) $request->year);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    public function byCategory(Request $request): JsonResponse
    {
        $request->validate([
            'category' => 'required|string',
            'year' => 'required|integer|min:2020|max:2100',
        ]);

        $report = $this->reportService->generateCategoryReport(
            $request->category,
            (int) $request->year
        );

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    public function exportMonthlyPdf(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2100',
        ]);

        $report = $this->reportService->generateMonthlyReport(
            (int) $request->month,
            (int) $request->year
        );

        $pdf = Pdf::loadView('reports.monthly', ['report' => $report]);
        $pdf->setPaper('a4', 'landscape');

        $filename = "laporan_bulanan_{$request->month}_{$request->year}.pdf";

        return $pdf->download($filename);
    }

    public function exportMonthlyExcel(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2100',
        ]);

        $report = $this->reportService->generateMonthlyReport(
            (int) $request->month,
            (int) $request->year
        );

        $filename = "laporan_bulanan_{$request->month}_{$request->year}.xlsx";

        return Excel::download(new MonthlyReportExport($report), $filename);
    }

    public function exportYearlyPdf(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
        ]);

        $report = $this->reportService->generateYearlyReport((int) $request->year);

        $pdf = Pdf::loadView('reports.yearly', ['report' => $report]);
        $pdf->setPaper('a4', 'landscape');

        $filename = "laporan_tahunan_{$request->year}.pdf";

        return $pdf->download($filename);
    }

    public function exportYearlyExcel(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
        ]);

        $report = $this->reportService->generateYearlyReport((int) $request->year);

        $filename = "laporan_tahunan_{$request->year}.xlsx";

        return Excel::download(new YearlyReportExport($report), $filename);
    }

    /**
     * Export Realisasi PDF - Format Pak Kadis
     */
    public function exportRealisasiPdf(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
            'program_id' => 'nullable|integer|exists:programs,id',
            'skpd_id' => 'nullable|integer|exists:skpd,id',
        ]);

        $year = (int) $request->year;
        $month = $request->month ? (int) $request->month : null;
        $programId = $request->program_id ? (int) $request->program_id : null;
        $skpdId = $request->skpd_id ? (int) $request->skpd_id : null;

        // Get related data
        $skpd = $skpdId ? Skpd::find($skpdId) : Skpd::first();
        $program = $programId ? Program::with('activities')->find($programId) : Program::with('activities')->first();
        $activity = $program?->activities->first();

        // Get months to process
        $months = $month ? [$month] : range(1, 12);

        // Build data for each month
        $data = [];
        foreach ($months as $m) {
            $budgetItems = BudgetItem::with([
                'subActivity.activity.program',
                'monthlyPlans' => fn($q) => $q->where('year', $year)->where('month', $m)->with('realization'),
            ])
                ->when($programId, fn($q) => $q->whereHas('subActivity.activity', fn($q2) => $q2->where('program_id', $programId)))
                ->get();

            $data[$m] = $budgetItems->map(function ($item) {
                $plan = $item->monthlyPlans->first();
                $realization = $plan?->realization;

                $budget = (float) $item->total_budget;
                $planned = (float) ($plan?->planned_amount ?? 0);
                $realized = (float) ($realization?->realized_amount ?? 0);

                // Calculate physical progress
                $plannedVolume = (float) ($plan?->planned_volume ?? 0);
                $realizedVolume = (float) ($realization?->realized_volume ?? 0);
                $physicalPlan = $item->volume > 0 ? ($plannedVolume / $item->volume) * 100 : 0;
                $physicalReal = $item->volume > 0 ? ($realizedVolume / $item->volume) * 100 : 0;

                return [
                    'code' => $item->code,
                    'name' => $item->name,
                    'volume' => $item->volume,
                    'unit' => $item->unit,
                    'location' => '-',
                    'budget' => $budget,
                    'realized' => $realized,
                    'physical_plan' => round($physicalPlan, 2),
                    'physical_real' => round($physicalReal, 2),
                    'financial_plan' => $budget > 0 ? round(($planned / $budget) * 100, 2) : 0,
                ];
            });
        }

        $pdf = Pdf::loadView('reports.realisasi', [
            'year' => $year,
            'months' => $months,
            'monthNames' => $this->monthNames,
            'data' => $data,
            'skpd' => $skpd,
            'program' => $program,
            'activity' => $activity,
        ]);

        $pdf->setPaper('a4', 'landscape');

        $monthStr = $month ? "_{$month}" : '';
        $filename = "laporan_realisasi_{$year}{$monthStr}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Export Realisasi Excel - Format Pak Kadis (Multiple Sheets)
     */
    public function exportRealisasiExcel(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
            'program_id' => 'nullable|integer|exists:programs,id',
            'skpd_id' => 'nullable|integer|exists:skpd,id',
        ]);

        $year = (int) $request->year;
        $month = $request->month ? (int) $request->month : null;
        $programId = $request->program_id ? (int) $request->program_id : null;
        $skpdId = $request->skpd_id ? (int) $request->skpd_id : null;

        $export = new RealisasiExport($year, $month, $programId, $skpdId);
        $spreadsheet = $export->getSpreadsheet();

        $monthStr = $month ? "_{$month}" : '';
        $filename = "laporan_realisasi_{$year}{$monthStr}.xlsx";

        // Stream the file
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Get realisasi report data
     */
    public function realisasi(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
            'program_id' => 'nullable|integer|exists:programs,id',
        ]);

        $year = (int) $request->year;
        $month = $request->month ? (int) $request->month : null;
        $programId = $request->program_id ? (int) $request->program_id : null;

        $months = $month ? [$month] : range(1, 12);
        $data = [];

        foreach ($months as $m) {
            $budgetItems = BudgetItem::with([
                'subActivity.activity.program',
                'monthlyPlans' => fn($q) => $q->where('year', $year)->where('month', $m)->with('realization'),
            ])
                ->when($programId, fn($q) => $q->whereHas('subActivity.activity', fn($q2) => $q2->where('program_id', $programId)))
                ->get();

            $data[$m] = [
                'month' => $m,
                'month_name' => $this->monthNames[$m],
                'items' => $budgetItems->map(function ($item) {
                    $plan = $item->monthlyPlans->first();
                    $realization = $plan?->realization;

                    $budget = (float) $item->total_budget;
                    $planned = (float) ($plan?->planned_amount ?? 0);
                    $realized = (float) ($realization?->realized_amount ?? 0);

                    // Calculate physical progress as percentage of planned vs realized volume
                    $plannedVolume = (float) ($plan?->planned_volume ?? 0);
                    $realizedVolume = (float) ($realization?->realized_volume ?? 0);
                    $physicalTarget = $item->volume > 0 ? ($plannedVolume / $item->volume) * 100 : 0;
                    $physicalProgress = $item->volume > 0 ? ($realizedVolume / $item->volume) * 100 : 0;

                    return [
                        'id' => $item->id,
                        'code' => $item->code,
                        'name' => $item->name,
                        'volume' => $item->volume,
                        'unit' => $item->unit,
                        'budget' => $budget,
                        'planned' => $planned,
                        'realized' => $realized,
                        'physical_target' => round($physicalTarget, 2),
                        'physical_progress' => round($physicalProgress, 2),
                        'balance' => $budget - $realized,
                    ];
                }),
                'totals' => [
                    'budget' => $budgetItems->sum('total_budget'),
                    'planned' => $budgetItems->sum(fn($i) => $i->monthlyPlans->first()?->planned_amount ?? 0),
                    'realized' => $budgetItems->sum(fn($i) => $i->monthlyPlans->first()?->realization?->realized_amount ?? 0),
                ],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'year' => $year,
                'months' => $data,
            ],
        ]);
    }
}
