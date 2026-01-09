<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReportTemplate;
use App\Models\SubActivity;
use App\Models\BudgetItem;
use App\Models\MonthlyPlan;
use App\Models\MonthlyRealization;
use App\Exports\CustomReportExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomReportController extends Controller
{
    public function templates(Request $request): JsonResponse
    {
        $templates = ReportTemplate::forUser(auth()->id())
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'config' => 'required|array',
            'config.columns' => 'required|array|min:1',
            'config.filters' => 'nullable|array',
            'config.grouping' => 'nullable|string',
            'config.sorting' => 'nullable|array',
            'is_public' => 'boolean',
            'is_default' => 'boolean',
        ]);

        $template = ReportTemplate::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'config' => $validated['config'],
            'is_public' => $validated['is_public'] ?? false,
            'is_default' => $validated['is_default'] ?? false,
        ]);

        // If set as default, unset other defaults for this user
        if ($template->is_default) {
            ReportTemplate::where('user_id', auth()->id())
                ->where('id', '!=', $template->id)
                ->update(['is_default' => false]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Template laporan berhasil dibuat.',
            'data' => $template,
        ], 201);
    }

    public function destroyTemplate(int $id): JsonResponse
    {
        $template = ReportTemplate::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template laporan berhasil dihapus.',
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_id' => 'nullable|exists:report_templates,id',
            'config' => 'required_without:template_id|array',
            'config.columns' => 'required_without:template_id|array',
            'config.filters' => 'nullable|array',
            'config.grouping' => 'nullable|string',
        ]);

        $config = $validated['config'] ?? null;

        if (isset($validated['template_id'])) {
            $template = ReportTemplate::find($validated['template_id']);
            $config = $template->config;
        }

        $data = $this->buildReportData($config);

        return response()->json([
            'success' => true,
            'data' => [
                'config' => $config,
                'report' => $data,
                'generated_at' => now()->toISOString(),
            ],
        ]);
    }

    public function exportPdf(Request $request)
    {
        $validated = $request->validate([
            'template_id' => 'nullable|exists:report_templates,id',
            'config' => 'required_without:template_id|array',
            'title' => 'nullable|string',
        ]);

        $config = $validated['config'] ?? null;
        $title = $validated['title'] ?? 'Laporan Custom';

        if (isset($validated['template_id'])) {
            $template = ReportTemplate::find($validated['template_id']);
            $config = $template->config;
            $title = $template->name;
        }

        $data = $this->buildReportData($config);

        $pdf = PDF::loadView('reports.custom', [
            'title' => $title,
            'config' => $config,
            'data' => $data,
            'generated_at' => now(),
            'generated_by' => auth()->user()->name,
        ]);

        $filename = 'laporan-custom-' . now()->format('Y-m-d-His') . '.pdf';

        return $pdf->download($filename);
    }

    public function exportExcel(Request $request)
    {
        $validated = $request->validate([
            'template_id' => 'nullable|exists:report_templates,id',
            'config' => 'required_without:template_id|array',
            'title' => 'nullable|string',
        ]);

        $config = $validated['config'] ?? null;
        $title = $validated['title'] ?? 'Laporan Custom';

        if (isset($validated['template_id'])) {
            $template = ReportTemplate::find($validated['template_id']);
            $config = $template->config;
            $title = $template->name;
        }

        $data = $this->buildReportData($config);

        $filename = 'laporan-custom-' . now()->format('Y-m-d-His') . '.xlsx';

        return Excel::download(new CustomReportExport($config, $data, $title), $filename);
    }

    private function buildReportData(array $config): array
    {
        $columns = $config['columns'] ?? [];
        $filters = $config['filters'] ?? [];
        $grouping = $config['grouping'] ?? null;

        // Build query based on config
        $query = BudgetItem::query()
            ->with(['subActivity.activity.program', 'monthlyPlans', 'realizations']);

        // Apply filters
        if (!empty($filters['sub_activity_id'])) {
            $query->where('sub_activity_id', $filters['sub_activity_id']);
        }

        if (!empty($filters['category'])) {
            $query->whereHas('subActivity', function ($q) use ($filters) {
                $q->where('category', $filters['category']);
            });
        }

        if (!empty($filters['year'])) {
            $query->whereHas('monthlyPlans', function ($q) use ($filters) {
                $q->where('year', $filters['year']);
            });
        }

        $items = $query->get();

        // Build report rows
        $rows = [];
        foreach ($items as $item) {
            $row = [];

            foreach ($columns as $column) {
                $row[$column] = $this->getColumnValue($item, $column, $filters);
            }

            $rows[] = $row;
        }

        // Apply grouping if specified
        if ($grouping) {
            $rows = collect($rows)->groupBy($grouping)->map(function ($group, $key) use ($columns) {
                return [
                    'group' => $key,
                    'items' => $group->values(),
                    'totals' => $this->calculateGroupTotals($group, $columns),
                ];
            })->values()->toArray();
        }

        // Calculate totals
        $totals = $this->calculateTotals($items, $columns, $filters);

        return [
            'rows' => $rows,
            'totals' => $totals,
            'row_count' => count($items),
        ];
    }

    private function getColumnValue(BudgetItem $item, string $column, array $filters): mixed
    {
        $year = $filters['year'] ?? date('Y');
        $month = $filters['month'] ?? null;

        return match($column) {
            'code' => $item->code,
            'name' => $item->name,
            'unit' => $item->unit,
            'volume' => $item->volume,
            'unit_price' => $item->unit_price,
            'total_budget' => $item->total_budget,
            'category' => $item->subActivity?->category,
            'sub_activity' => $item->subActivity?->name,
            'activity' => $item->subActivity?->activity?->name,
            'program' => $item->subActivity?->activity?->program?->name,
            'planned_amount' => $this->getPlannedAmount($item, $year, $month),
            'realized_amount' => $this->getRealizedAmount($item, $year, $month),
            'deviation' => $this->getDeviation($item, $year, $month),
            'deviation_percentage' => $this->getDeviationPercentage($item, $year, $month),
            'absorption_rate' => $this->getAbsorptionRate($item, $year, $month),
            default => null,
        };
    }

    private function getPlannedAmount(BudgetItem $item, int $year, ?int $month): float
    {
        $query = $item->monthlyPlans()->where('year', $year);
        if ($month) {
            $query->where('month', $month);
        }
        return $query->sum('planned_amount');
    }

    private function getRealizedAmount(BudgetItem $item, int $year, ?int $month): float
    {
        $query = $item->realizations()->where('year', $year)->where('status', 'APPROVED');
        if ($month) {
            $query->where('month', $month);
        }
        return $query->sum('realized_amount');
    }

    private function getDeviation(BudgetItem $item, int $year, ?int $month): float
    {
        $planned = $this->getPlannedAmount($item, $year, $month);
        $realized = $this->getRealizedAmount($item, $year, $month);
        return $realized - $planned;
    }

    private function getDeviationPercentage(BudgetItem $item, int $year, ?int $month): float
    {
        $planned = $this->getPlannedAmount($item, $year, $month);
        if ($planned <= 0) return 0;

        $realized = $this->getRealizedAmount($item, $year, $month);
        return round((($realized - $planned) / $planned) * 100, 2);
    }

    private function getAbsorptionRate(BudgetItem $item, int $year, ?int $month): float
    {
        $planned = $this->getPlannedAmount($item, $year, $month);
        if ($planned <= 0) return 0;

        $realized = $this->getRealizedAmount($item, $year, $month);
        return round(($realized / $planned) * 100, 2);
    }

    private function calculateGroupTotals($group, array $columns): array
    {
        $numericColumns = ['volume', 'unit_price', 'total_budget', 'planned_amount', 'realized_amount', 'deviation'];
        $totals = [];

        foreach ($columns as $column) {
            if (in_array($column, $numericColumns)) {
                $totals[$column] = $group->sum($column);
            }
        }

        return $totals;
    }

    private function calculateTotals($items, array $columns, array $filters): array
    {
        $year = $filters['year'] ?? date('Y');
        $month = $filters['month'] ?? null;

        return [
            'total_budget' => $items->sum('total_budget'),
            'planned_amount' => $items->sum(fn($item) => $this->getPlannedAmount($item, $year, $month)),
            'realized_amount' => $items->sum(fn($item) => $this->getRealizedAmount($item, $year, $month)),
        ];
    }
}
