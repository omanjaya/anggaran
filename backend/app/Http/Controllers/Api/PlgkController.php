<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubActivity;
use App\Services\PlgkGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PlgkController extends Controller
{
    protected PlgkGeneratorService $plgkService;

    public function __construct(PlgkGeneratorService $plgkService)
    {
        $this->plgkService = $plgkService;
    }

    /**
     * Get PLGK for a sub-activity.
     */
    public function show(Request $request, SubActivity $subActivity): JsonResponse
    {
        $year = $request->get('year', now()->year);

        $data = $subActivity->load([
            'budgetItems.monthlyPlans' => function ($query) use ($year) {
                $query->where('year', $year)->orderBy('month');
            },
        ]);

        // Format data for display
        $formattedData = [
            'sub_activity' => [
                'id' => $subActivity->id,
                'category' => $subActivity->category,
                'name' => $subActivity->name,
                'budget_current_year' => $subActivity->budget_current_year,
            ],
            'year' => $year,
            'budget_items' => $subActivity->budgetItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'account_code' => $item->account_code,
                    'description' => $item->description,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'total_volume' => $item->total_volume,
                    'total_amount' => $item->total_amount,
                    'monthly_plans' => $item->monthlyPlans->map(function ($plan) {
                        return [
                            'id' => $plan->id,
                            'month' => $plan->month,
                            'planned_volume' => $plan->planned_volume,
                            'planned_amount' => $plan->planned_amount,
                        ];
                    }),
                ];
            }),
            'summary' => [
                'total_budget' => $subActivity->budgetItems->sum('total_amount'),
                'total_planned' => $subActivity->budgetItems->sum(function ($item) {
                    return $item->monthlyPlans->sum('planned_amount');
                }),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $formattedData
        ]);
    }

    /**
     * Preview PLGK generation.
     */
    public function preview(Request $request, SubActivity $subActivity): JsonResponse
    {
        $validated = $request->validate([
            'method' => ['required', 'in:equal,custom,copy_previous'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'custom_allocations' => ['nullable', 'array'],
        ]);

        $preview = $this->plgkService->preview(
            $subActivity,
            $validated['method'],
            $validated['year'],
            $validated['custom_allocations'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => $preview
        ]);
    }

    /**
     * Generate PLGK for a sub-activity.
     */
    public function generate(Request $request, SubActivity $subActivity): JsonResponse
    {
        $validated = $request->validate([
            'method' => ['required', 'in:equal,custom,copy_previous'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'custom_allocations' => ['nullable', 'array'],
        ]);

        try {
            $plans = $this->plgkService->generate(
                $subActivity,
                $validated['method'],
                $validated['year'],
                $validated['custom_allocations'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => "Successfully generated {$plans->count()} monthly plans",
                'data' => [
                    'generated_count' => $plans->count(),
                    'year' => $validated['year'],
                    'method' => $validated['method'],
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PLGK: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate PLGK data.
     */
    public function validate(Request $request, SubActivity $subActivity): JsonResponse
    {
        $year = $request->get('year', now()->year);

        $validation = $this->plgkService->validate($subActivity, $year);

        return response()->json([
            'success' => true,
            'data' => $validation
        ]);
    }

    /**
     * Get allocation methods.
     */
    public function methods(): JsonResponse
    {
        $methods = [
            [
                'value' => PlgkGeneratorService::METHOD_EQUAL,
                'label' => 'Distribusi Merata',
                'description' => 'Membagi anggaran merata ke 12 bulan'
            ],
            [
                'value' => PlgkGeneratorService::METHOD_CUSTOM,
                'label' => 'Alokasi Kustom',
                'description' => 'Menentukan alokasi manual per bulan'
            ],
            [
                'value' => PlgkGeneratorService::METHOD_COPY_PREVIOUS,
                'label' => 'Salin Tahun Sebelumnya',
                'description' => 'Menggunakan pola alokasi tahun lalu'
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $methods
        ]);
    }

    /**
     * Get available years.
     */
    public function years(): JsonResponse
    {
        $currentYear = now()->year;
        $years = range($currentYear - 2, $currentYear + 2);

        return response()->json([
            'success' => true,
            'data' => $years
        ]);
    }
}
