<?php

namespace App\Http\Controllers\Api;

use App\Enums\BudgetCategory;
use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProgramController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Program::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('code', 'ilike', "%{$search}%");
            });
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('fiscal_year')) {
            $query->where('fiscal_year', $request->fiscal_year);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $programs = $query->withCount('activities')
            ->orderBy('code')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $programs->items(),
            'meta' => [
                'current_page' => $programs->currentPage(),
                'last_page' => $programs->lastPage(),
                'per_page' => $programs->perPage(),
                'total' => $programs->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:programs,code',
            'name' => 'required|string|max:255',
            'category' => ['required', Rule::enum(BudgetCategory::class)],
            'fiscal_year' => 'required|integer|min:2020|max:2100',
            'total_budget' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $program = Program::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Program berhasil dibuat',
            'data' => $program,
        ], 201);
    }

    public function show(Program $program): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $program->load('activities'),
        ]);
    }

    public function update(Request $request, Program $program): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('programs')->ignore($program->id)],
            'name' => 'sometimes|string|max:255',
            'category' => ['sometimes', Rule::enum(BudgetCategory::class)],
            'fiscal_year' => 'sometimes|integer|min:2020|max:2100',
            'total_budget' => 'sometimes|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $program->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Program berhasil diupdate',
            'data' => $program->fresh(),
        ]);
    }

    public function destroy(Program $program): JsonResponse
    {
        if ($program->activities()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus program yang memiliki kegiatan',
            ], 422);
        }

        $program->delete();

        return response()->json([
            'success' => true,
            'message' => 'Program berhasil dihapus',
        ]);
    }

    public function categories(): JsonResponse
    {
        $categories = collect(BudgetCategory::cases())->map(fn($cat) => [
            'value' => $cat->value,
            'label' => $cat->label(),
            'budget' => $cat->budget(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }
}
