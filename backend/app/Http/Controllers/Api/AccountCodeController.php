<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountCode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class AccountCodeController extends Controller
{
    /**
     * Display a listing of account codes.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AccountCode::query();

        // Filter by level
        if ($request->has('level')) {
            $query->where('level', $request->level);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by parent code
        if ($request->has('parent_code')) {
            $query->where('parent_code', $request->parent_code);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'code');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination or all
        if ($request->boolean('all')) {
            $data = $query->get();
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        }

        $perPage = $request->get('per_page', 15);
        $data = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
            ]
        ]);
    }

    /**
     * Store a newly created account code.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:account_codes,code'],
            'description' => ['required', 'string', 'max:500'],
            'level' => ['required', 'integer', 'min:1', 'max:5'],
            'parent_code' => ['nullable', 'string', 'exists:account_codes,code'],
            'is_active' => ['boolean'],
        ]);

        // Validate parent level
        if (!empty($validated['parent_code'])) {
            $parent = AccountCode::where('code', $validated['parent_code'])->first();
            if ($parent && $parent->level >= $validated['level']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent level must be less than child level'
                ], 422);
            }
        }

        $accountCode = AccountCode::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Account code created successfully',
            'data' => $accountCode
        ], 201);
    }

    /**
     * Display the specified account code.
     */
    public function show(string $code): JsonResponse
    {
        $accountCode = AccountCode::where('code', $code)
            ->with(['parent', 'children'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $accountCode
        ]);
    }

    /**
     * Update the specified account code.
     */
    public function update(Request $request, string $code): JsonResponse
    {
        $accountCode = AccountCode::where('code', $code)->firstOrFail();

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('account_codes')->ignore($accountCode->id)],
            'description' => ['sometimes', 'string', 'max:500'],
            'level' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'parent_code' => ['nullable', 'string', 'exists:account_codes,code'],
            'is_active' => ['boolean'],
        ]);

        // Prevent circular reference
        if (!empty($validated['parent_code']) && $validated['parent_code'] === $code) {
            return response()->json([
                'success' => false,
                'message' => 'Account code cannot be its own parent'
            ], 422);
        }

        $accountCode->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Account code updated successfully',
            'data' => $accountCode
        ]);
    }

    /**
     * Remove the specified account code.
     */
    public function destroy(string $code): JsonResponse
    {
        $accountCode = AccountCode::where('code', $code)->firstOrFail();

        // Check if has children
        if ($accountCode->children()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete account code with children. Delete children first.'
            ], 422);
        }

        // Check if used in budget items
        if ($accountCode->budgetItems()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete account code used in budget items. Deactivate instead.'
            ], 422);
        }

        $accountCode->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account code deleted successfully'
        ]);
    }

    /**
     * Get account codes in tree structure.
     */
    public function tree(): JsonResponse
    {
        $rootCodes = AccountCode::whereNull('parent_code')
            ->with('children.children.children.children.children')
            ->orderBy('code')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rootCodes
        ]);
    }

    /**
     * Get leaf nodes (level 5) for selection.
     */
    public function leafNodes(Request $request): JsonResponse
    {
        $query = AccountCode::active()->leafNodes();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $data = $query->orderBy('code')->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Import account codes from Excel.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        try {
            $file = $request->file('file');
            $data = [];
            $errors = [];
            $imported = 0;

            // Use PhpSpreadsheet or similar library
            // For now, return placeholder
            // TODO: Implement actual import logic

            return response()->json([
                'success' => true,
                'message' => "Successfully imported {$imported} account codes",
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get levels for dropdown.
     */
    public function levels(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => collect(AccountCode::LEVELS)->map(function ($name, $level) {
                return ['value' => $level, 'label' => $name];
            })->values()
        ]);
    }
}
