<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MonthlyPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MonthlyPlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MonthlyPlan::with(['budgetItem.subActivity.activity.program', 'createdBy']);

        if ($request->has('budget_item_id')) {
            $query->where('budget_item_id', $request->budget_item_id);
        }

        if ($request->has('month')) {
            $query->where('month', $request->month);
        }

        if ($request->has('year')) {
            $query->where('year', $request->year);
        }

        $plans = $query->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $plans->items(),
            'meta' => [
                'current_page' => $plans->currentPage(),
                'last_page' => $plans->lastPage(),
                'per_page' => $plans->perPage(),
                'total' => $plans->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'budget_item_id' => 'required|exists:budget_items,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2100',
            'planned_volume' => 'required|numeric|min:0',
            'planned_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        // Check for duplicate
        $exists = MonthlyPlan::where('budget_item_id', $validated['budget_item_id'])
            ->where('month', $validated['month'])
            ->where('year', $validated['year'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Perencanaan untuk bulan dan tahun tersebut sudah ada',
            ], 422);
        }

        $validated['created_by'] = auth()->id();

        $plan = MonthlyPlan::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Perencanaan bulanan berhasil dibuat',
            'data' => $plan->load(['budgetItem.subActivity.activity.program', 'createdBy']),
        ], 201);
    }

    public function show(MonthlyPlan $monthlyPlan): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $monthlyPlan->load(['budgetItem.subActivity.activity.program', 'createdBy', 'realization']),
        ]);
    }

    public function update(Request $request, MonthlyPlan $monthlyPlan): JsonResponse
    {
        // Check if realization exists
        if ($monthlyPlan->realization()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat mengubah perencanaan yang sudah memiliki realisasi',
            ], 422);
        }

        $validated = $request->validate([
            'planned_volume' => 'sometimes|numeric|min:0',
            'planned_amount' => 'sometimes|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $monthlyPlan->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Perencanaan bulanan berhasil diupdate',
            'data' => $monthlyPlan->fresh()->load(['budgetItem.subActivity.activity.program', 'createdBy']),
        ]);
    }

    public function destroy(MonthlyPlan $monthlyPlan): JsonResponse
    {
        if ($monthlyPlan->realization()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus perencanaan yang sudah memiliki realisasi',
            ], 422);
        }

        $monthlyPlan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Perencanaan bulanan berhasil dihapus',
        ]);
    }

    public function byBudgetItem(Request $request, int $budgetItemId): JsonResponse
    {
        $year = $request->get('year', date('Y'));

        $plans = MonthlyPlan::where('budget_item_id', $budgetItemId)
            ->where('year', $year)
            ->with('realization')
            ->orderBy('month')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }
}
