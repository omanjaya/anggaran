<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Activity::with('program');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('code', 'ilike', "%{$search}%");
            });
        }

        if ($request->has('program_id')) {
            $query->where('program_id', $request->program_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $activities = $query->withCount('subActivities')
            ->orderBy('code')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $activities->items(),
            'meta' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'program_id' => 'required|exists:programs,id',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('activities')->where(function ($query) use ($request) {
                    return $query->where('program_id', $request->program_id);
                }),
            ],
            'name' => 'required|string|max:255',
            'total_budget' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $activity = Activity::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Kegiatan berhasil dibuat',
            'data' => $activity->load('program'),
        ], 201);
    }

    public function show(Activity $activity): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $activity->load(['program', 'subActivities']),
        ]);
    }

    public function update(Request $request, Activity $activity): JsonResponse
    {
        $validated = $request->validate([
            'program_id' => 'sometimes|exists:programs,id',
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('activities')->where(function ($query) use ($request, $activity) {
                    return $query->where('program_id', $request->program_id ?? $activity->program_id);
                })->ignore($activity->id),
            ],
            'name' => 'sometimes|string|max:255',
            'total_budget' => 'sometimes|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $activity->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Kegiatan berhasil diupdate',
            'data' => $activity->fresh()->load('program'),
        ]);
    }

    public function destroy(Activity $activity): JsonResponse
    {
        if ($activity->subActivities()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus kegiatan yang memiliki sub kegiatan',
            ], 422);
        }

        $activity->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kegiatan berhasil dihapus',
        ]);
    }
}
