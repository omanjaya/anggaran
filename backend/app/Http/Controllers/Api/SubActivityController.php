<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SubActivity::with(['activity.program']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('code', 'ilike', "%{$search}%");
            });
        }

        if ($request->has('activity_id')) {
            $query->where('activity_id', $request->activity_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $subActivities = $query->withCount('budgetItems')
            ->orderBy('code')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $subActivities->items(),
            'meta' => [
                'current_page' => $subActivities->currentPage(),
                'last_page' => $subActivities->lastPage(),
                'per_page' => $subActivities->perPage(),
                'total' => $subActivities->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'activity_id' => 'required|exists:activities,id',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('sub_activities')->where(function ($query) use ($request) {
                    return $query->where('activity_id', $request->activity_id);
                }),
            ],
            'name' => 'required|string|max:255',
            'total_budget' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $subActivity = SubActivity::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Sub kegiatan berhasil dibuat',
            'data' => $subActivity->load('activity.program'),
        ], 201);
    }

    public function show(SubActivity $subActivity): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $subActivity->load(['activity.program', 'budgetItems']),
        ]);
    }

    public function update(Request $request, SubActivity $subActivity): JsonResponse
    {
        $validated = $request->validate([
            'activity_id' => 'sometimes|exists:activities,id',
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('sub_activities')->where(function ($query) use ($request, $subActivity) {
                    return $query->where('activity_id', $request->activity_id ?? $subActivity->activity_id);
                })->ignore($subActivity->id),
            ],
            'name' => 'sometimes|string|max:255',
            'total_budget' => 'sometimes|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $subActivity->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Sub kegiatan berhasil diupdate',
            'data' => $subActivity->fresh()->load('activity.program'),
        ]);
    }

    public function destroy(SubActivity $subActivity): JsonResponse
    {
        if ($subActivity->budgetItems()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus sub kegiatan yang memiliki item anggaran',
            ], 422);
        }

        $subActivity->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sub kegiatan berhasil dihapus',
        ]);
    }
}
