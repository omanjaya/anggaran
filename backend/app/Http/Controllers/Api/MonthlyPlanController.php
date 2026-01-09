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

    /**
     * Batch create/update monthly plans
     */
    public function batch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plans' => 'required|array|min:1',
            'plans.*.budget_item_id' => 'required|exists:budget_items,id',
            'plans.*.month' => 'required|integer|min:1|max:12',
            'plans.*.year' => 'required|integer|min:2020|max:2100',
            'plans.*.planned_volume' => 'required|numeric|min:0',
            'plans.*.planned_amount' => 'required|numeric|min:0',
            'plans.*.id' => 'nullable|integer|exists:monthly_plans,id',
        ]);

        $savedCount = 0;
        $errors = [];
        $savedPlans = [];

        foreach ($validated['plans'] as $planData) {
            try {
                // Check if we're updating or creating
                $existingPlan = MonthlyPlan::where('budget_item_id', $planData['budget_item_id'])
                    ->where('month', $planData['month'])
                    ->where('year', $planData['year'])
                    ->first();

                if ($existingPlan) {
                    // Check if has realization
                    if ($existingPlan->realization()->exists()) {
                        $errors[] = "Bulan {$planData['month']} sudah memiliki realisasi";
                        continue;
                    }

                    $existingPlan->update([
                        'planned_volume' => $planData['planned_volume'],
                        'planned_amount' => $planData['planned_amount'],
                    ]);

                    $savedPlans[] = [
                        'id' => $existingPlan->id,
                        'budget_item_id' => $existingPlan->budget_item_id,
                        'month' => $existingPlan->month,
                    ];
                } else {
                    $newPlan = MonthlyPlan::create([
                        'budget_item_id' => $planData['budget_item_id'],
                        'month' => $planData['month'],
                        'year' => $planData['year'],
                        'planned_volume' => $planData['planned_volume'],
                        'planned_amount' => $planData['planned_amount'],
                        'created_by' => auth()->id(),
                    ]);

                    $savedPlans[] = [
                        'id' => $newPlan->id,
                        'budget_item_id' => $newPlan->budget_item_id,
                        'month' => $newPlan->month,
                    ];
                }

                $savedCount++;
            } catch (\Exception $e) {
                $errors[] = "Error pada item {$planData['budget_item_id']}: " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => count($errors) === 0,
            'message' => "{$savedCount} rencana berhasil disimpan",
            'saved_count' => $savedCount,
            'errors' => $errors,
            'data' => $savedPlans,
        ]);
    }
}
