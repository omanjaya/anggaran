<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Skpd;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkpdController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Skpd::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('short_name', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $skpd = $query->orderBy('name')->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $skpd,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:skpd,code',
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'head_name' => 'nullable|string|max:255',
            'head_title' => 'nullable|string|max:255',
            'nip_head' => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ]);

        $skpd = Skpd::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'SKPD berhasil dibuat.',
            'data' => $skpd,
        ], 201);
    }

    public function show(Skpd $skpd): JsonResponse
    {
        $skpd->load(['users', 'programs.activities.subActivities']);

        // Calculate statistics
        $stats = [
            'total_users' => $skpd->users->count(),
            'total_programs' => $skpd->programs->count(),
            'total_budget' => $skpd->programs->sum(function ($program) {
                return $program->activities->sum(function ($activity) {
                    return $activity->subActivities->sum('budget');
                });
            }),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'skpd' => $skpd,
                'stats' => $stats,
            ],
        ]);
    }

    public function update(Request $request, Skpd $skpd): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:skpd,code,' . $skpd->id,
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'head_name' => 'nullable|string|max:255',
            'head_title' => 'nullable|string|max:255',
            'nip_head' => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ]);

        $skpd->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'SKPD berhasil diperbarui.',
            'data' => $skpd,
        ]);
    }

    public function destroy(Skpd $skpd): JsonResponse
    {
        // Check if SKPD has related data
        if ($skpd->programs()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'SKPD tidak dapat dihapus karena masih memiliki program terkait.',
            ], 422);
        }

        if ($skpd->users()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'SKPD tidak dapat dihapus karena masih memiliki user terkait.',
            ], 422);
        }

        $skpd->delete();

        return response()->json([
            'success' => true,
            'message' => 'SKPD berhasil dihapus.',
        ]);
    }
}
