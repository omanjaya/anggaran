<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService
    ) {}

    public function stats(Request $request): JsonResponse
    {
        $year = $request->get('year', date('Y'));

        return response()->json([
            'success' => true,
            'data' => $this->dashboardService->getStats((int) $year),
        ]);
    }

    public function monthlyTrend(Request $request): JsonResponse
    {
        $year = $request->get('year', date('Y'));

        return response()->json([
            'success' => true,
            'data' => $this->dashboardService->getMonthlyTrend((int) $year),
        ]);
    }

    public function programStats(Request $request): JsonResponse
    {
        $year = $request->get('year', date('Y'));

        return response()->json([
            'success' => true,
            'data' => $this->dashboardService->getProgramStats((int) $year),
        ]);
    }

    public function recentActivities(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);

        return response()->json([
            'success' => true,
            'data' => $this->dashboardService->getRecentActivities((int) $limit),
        ]);
    }
}
