<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BudgetItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BudgetItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = BudgetItem::with(['subActivity.activity.program', 'details']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('code', 'ilike', "%{$search}%");
            });
        }

        if ($request->has('sub_activity_id')) {
            $query->where('sub_activity_id', $request->sub_activity_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Include monthly plans if requested
        $withMonthlyPlans = $request->boolean('with_monthly_plans');
        $year = $request->get('year', date('Y'));

        if ($withMonthlyPlans) {
            $query->with(['monthlyPlans' => function ($q) use ($year) {
                $q->where('year', $year);
            }]);
        }

        $budgetItems = $query->orderBy('code')
            ->paginate($request->get('per_page', 15));

        // Transform data to include monthly_plans indexed by month
        $items = collect($budgetItems->items())->map(function ($item) use ($withMonthlyPlans) {
            $itemArray = $item->toArray();

            if ($withMonthlyPlans && isset($itemArray['monthly_plans'])) {
                // Index monthly plans by month number for easy access
                $monthlyPlansIndexed = [];
                foreach ($itemArray['monthly_plans'] as $plan) {
                    $monthlyPlansIndexed[$plan['month']] = [
                        'id' => $plan['id'],
                        'planned_volume' => $plan['planned_volume'],
                        'planned_amount' => $plan['planned_amount'],
                    ];
                }
                $itemArray['monthly_plans'] = $monthlyPlansIndexed;
            }

            return $itemArray;
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $budgetItems->currentPage(),
                'last_page' => $budgetItems->lastPage(),
                'per_page' => $budgetItems->perPage(),
                'total' => $budgetItems->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sub_activity_id' => 'required|exists:sub_activities,id',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('budget_items')->where(function ($query) use ($request) {
                    return $query->where('sub_activity_id', $request->sub_activity_id);
                }),
            ],
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'volume' => 'required|numeric|min:0',
            'unit_price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['total_budget'] = $validated['volume'] * $validated['unit_price'];

        $budgetItem = BudgetItem::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Item anggaran berhasil dibuat',
            'data' => $budgetItem->load('subActivity.activity.program'),
        ], 201);
    }

    public function show(BudgetItem $budgetItem): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $budgetItem->load(['subActivity.activity.program', 'monthlyPlans', 'details']),
        ]);
    }

    public function update(Request $request, BudgetItem $budgetItem): JsonResponse
    {
        $validated = $request->validate([
            'sub_activity_id' => 'sometimes|exists:sub_activities,id',
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('budget_items')->where(function ($query) use ($request, $budgetItem) {
                    return $query->where('sub_activity_id', $request->sub_activity_id ?? $budgetItem->sub_activity_id);
                })->ignore($budgetItem->id),
            ],
            'name' => 'sometimes|string|max:255',
            'unit' => 'sometimes|string|max:50',
            'volume' => 'sometimes|numeric|min:0',
            'unit_price' => 'sometimes|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($validated['volume']) || isset($validated['unit_price'])) {
            $volume = $validated['volume'] ?? $budgetItem->volume;
            $unitPrice = $validated['unit_price'] ?? $budgetItem->unit_price;
            $validated['total_budget'] = $volume * $unitPrice;
        }

        $budgetItem->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Item anggaran berhasil diupdate',
            'data' => $budgetItem->fresh()->load('subActivity.activity.program'),
        ]);
    }

    public function destroy(BudgetItem $budgetItem): JsonResponse
    {
        if ($budgetItem->monthlyPlans()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus item anggaran yang memiliki perencanaan bulanan',
            ], 422);
        }

        $budgetItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item anggaran berhasil dihapus',
        ]);
    }
}
