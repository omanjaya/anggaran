<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuditLogController extends Controller
{
    /**
     * Display a listing of audit logs.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with('user:id,name,email');

        // Filter by action
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by model type
        if ($request->has('auditable_type')) {
            $query->where('auditable_type', 'like', "%{$request->auditable_type}%");
        }

        // Filter by date range
        if ($request->has('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        // Search in values
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereJsonContains('old_values', $search)
                  ->orWhereJsonContains('new_values', $search)
                  ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 20);
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
     * Display the specified audit log.
     */
    public function show(AuditLog $auditLog): JsonResponse
    {
        $auditLog->load('user:id,name,email');

        return response()->json([
            'success' => true,
            'data' => $auditLog
        ]);
    }

    /**
     * Get audit logs for a specific model.
     */
    public function forModel(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'string'],
            'id' => ['required', 'integer'],
        ]);

        $modelClass = $this->resolveModelClass($request->type);
        
        if (!$modelClass) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid model type'
            ], 400);
        }

        $logs = AuditLog::with('user:id,name,email')
            ->where('auditable_type', $modelClass)
            ->where('auditable_id', $request->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }

    /**
     * Get available actions for filter.
     */
    public function actions(): JsonResponse
    {
        $actions = [
            ['value' => AuditLog::ACTION_CREATE, 'label' => 'Create'],
            ['value' => AuditLog::ACTION_UPDATE, 'label' => 'Update'],
            ['value' => AuditLog::ACTION_DELETE, 'label' => 'Delete'],
            ['value' => AuditLog::ACTION_APPROVE, 'label' => 'Approve'],
            ['value' => AuditLog::ACTION_REJECT, 'label' => 'Reject'],
            ['value' => AuditLog::ACTION_VERIFY, 'label' => 'Verify'],
            ['value' => AuditLog::ACTION_SUBMIT, 'label' => 'Submit'],
            ['value' => AuditLog::ACTION_LOGIN, 'label' => 'Login'],
            ['value' => AuditLog::ACTION_LOGOUT, 'label' => 'Logout'],
            ['value' => AuditLog::ACTION_IMPORT, 'label' => 'Import'],
            ['value' => AuditLog::ACTION_EXPORT, 'label' => 'Export'],
        ];

        return response()->json([
            'success' => true,
            'data' => $actions
        ]);
    }

    /**
     * Get statistics for dashboard.
     */
    public function stats(Request $request): JsonResponse
    {
        $from = $request->get('from', now()->subDays(30));
        $to = $request->get('to', now());

        $stats = [
            'total' => AuditLog::whereBetween('created_at', [$from, $to])->count(),
            'by_action' => AuditLog::whereBetween('created_at', [$from, $to])
                ->selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->get()
                ->pluck('count', 'action'),
            'by_user' => AuditLog::whereBetween('created_at', [$from, $to])
                ->with('user:id,name')
                ->selectRaw('user_id, COUNT(*) as count')
                ->groupBy('user_id')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
            'recent' => AuditLog::with('user:id,name')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Resolve model class from type string.
     */
    private function resolveModelClass(string $type): ?string
    {
        $mapping = [
            'program' => \App\Models\Program::class,
            'activity' => \App\Models\Activity::class,
            'sub_activity' => \App\Models\SubActivity::class,
            'budget_item' => \App\Models\BudgetItem::class,
            'monthly_plan' => \App\Models\MonthlyPlan::class,
            'monthly_realization' => \App\Models\MonthlyRealization::class,
            'user' => \App\Models\User::class,
            'account_code' => \App\Models\AccountCode::class,
        ];

        return $mapping[strtolower($type)] ?? null;
    }
}
