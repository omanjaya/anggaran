<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OperationalSchedule;
use App\Models\BudgetItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OperationalScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = OperationalSchedule::with(['budgetItem.subActivity', 'pic']);

        if ($request->has('month')) {
            $query->where('month', $request->month);
        }

        if ($request->has('year')) {
            $query->where('year', $request->year);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('sub_activity_id')) {
            $query->whereHas('budgetItem', function ($q) use ($request) {
                $q->where('sub_activity_id', $request->sub_activity_id);
            });
        }

        if ($request->has('pic_user_id')) {
            $query->where('pic_user_id', $request->pic_user_id);
        }

        $schedules = $query->orderBy('year')
            ->orderBy('month')
            ->orderBy('priority', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $schedules,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'budget_item_id' => 'required|exists:budget_items,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'description' => 'nullable|string',
            'pic_user_id' => 'nullable|exists:users,id',
            'status' => 'nullable|in:PLANNED,IN_PROGRESS,COMPLETED,CANCELLED',
            'planned_volume' => 'nullable|numeric|min:0',
            'planned_amount' => 'nullable|numeric|min:0',
            'priority' => 'nullable|integer|min:1|max:3',
            'notes' => 'nullable|string',
        ]);

        // Check for duplicate
        $exists = OperationalSchedule::where('budget_item_id', $validated['budget_item_id'])
            ->where('month', $validated['month'])
            ->where('year', $validated['year'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal operasional untuk item ini pada bulan yang sama sudah ada.',
            ], 422);
        }

        $schedule = OperationalSchedule::create($validated);
        $schedule->load(['budgetItem.subActivity', 'pic']);

        return response()->json([
            'success' => true,
            'message' => 'Jadwal operasional berhasil dibuat.',
            'data' => $schedule,
        ], 201);
    }

    public function show(OperationalSchedule $operationalSchedule): JsonResponse
    {
        $operationalSchedule->load(['budgetItem.subActivity', 'pic']);

        return response()->json([
            'success' => true,
            'data' => $operationalSchedule,
        ]);
    }

    public function update(Request $request, OperationalSchedule $operationalSchedule): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'description' => 'nullable|string',
            'pic_user_id' => 'nullable|exists:users,id',
            'status' => 'nullable|in:PLANNED,IN_PROGRESS,COMPLETED,CANCELLED',
            'planned_volume' => 'nullable|numeric|min:0',
            'planned_amount' => 'nullable|numeric|min:0',
            'priority' => 'nullable|integer|min:1|max:3',
            'notes' => 'nullable|string',
        ]);

        $operationalSchedule->update($validated);
        $operationalSchedule->load(['budgetItem.subActivity', 'pic']);

        return response()->json([
            'success' => true,
            'message' => 'Jadwal operasional berhasil diperbarui.',
            'data' => $operationalSchedule,
        ]);
    }

    public function destroy(OperationalSchedule $operationalSchedule): JsonResponse
    {
        $operationalSchedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Jadwal operasional berhasil dihapus.',
        ]);
    }

    public function generateFromPlgk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sub_activity_id' => 'required|exists:sub_activities,id',
            'year' => 'required|integer',
        ]);

        $budgetItems = BudgetItem::where('sub_activity_id', $validated['sub_activity_id'])
            ->with('monthlyPlans')
            ->get();

        $created = 0;

        DB::beginTransaction();
        try {
            foreach ($budgetItems as $item) {
                $monthlyPlans = $item->monthlyPlans->where('year', $validated['year']);

                foreach ($monthlyPlans as $plan) {
                    if ($plan->planned_volume > 0 || $plan->planned_amount > 0) {
                        OperationalSchedule::updateOrCreate(
                            [
                                'budget_item_id' => $item->id,
                                'month' => $plan->month,
                                'year' => $plan->year,
                            ],
                            [
                                'planned_volume' => $plan->planned_volume,
                                'planned_amount' => $plan->planned_amount,
                                'status' => 'PLANNED',
                            ]
                        );
                        $created++;
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Berhasil membuat {$created} jadwal operasional dari PLGK.",
                'data' => ['count' => $created],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat jadwal operasional: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function calendar(Request $request): JsonResponse
    {
        $year = $request->get('year', date('Y'));
        $subActivityId = $request->get('sub_activity_id');

        $query = OperationalSchedule::with(['budgetItem.subActivity', 'pic'])
            ->where('year', $year);

        if ($subActivityId) {
            $query->whereHas('budgetItem', function ($q) use ($subActivityId) {
                $q->where('sub_activity_id', $subActivityId);
            });
        }

        $schedules = $query->get();

        // Group by month
        $calendar = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthSchedules = $schedules->filter(fn($s) => $s->month === $month);
            $calendar[$month] = [
                'month' => $month,
                'month_name' => $this->getMonthName($month),
                'total_items' => $monthSchedules->count(),
                'total_planned' => $monthSchedules->sum('planned_amount'),
                'by_status' => [
                    'planned' => $monthSchedules->where('status', 'PLANNED')->count(),
                    'in_progress' => $monthSchedules->where('status', 'IN_PROGRESS')->count(),
                    'completed' => $monthSchedules->where('status', 'COMPLETED')->count(),
                    'cancelled' => $monthSchedules->where('status', 'CANCELLED')->count(),
                ],
                'items' => $monthSchedules->values(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'year' => $year,
                'calendar' => array_values($calendar),
            ],
        ]);
    }

    public function gantt(Request $request): JsonResponse
    {
        $year = $request->get('year', date('Y'));
        $subActivityId = $request->get('sub_activity_id');

        $query = OperationalSchedule::with(['budgetItem.subActivity', 'pic'])
            ->where('year', $year)
            ->orderBy('month');

        if ($subActivityId) {
            $query->whereHas('budgetItem', function ($q) use ($subActivityId) {
                $q->where('sub_activity_id', $subActivityId);
            });
        }

        $schedules = $query->get();

        // Transform for Gantt chart format
        $ganttData = $schedules->map(function ($schedule) {
            return [
                'id' => $schedule->id,
                'name' => $schedule->budgetItem->name,
                'start' => $schedule->start_date ?? "{$schedule->year}-" . str_pad($schedule->month, 2, '0', STR_PAD_LEFT) . "-01",
                'end' => $schedule->end_date ?? "{$schedule->year}-" . str_pad($schedule->month, 2, '0', STR_PAD_LEFT) . "-" . cal_days_in_month(CAL_GREGORIAN, $schedule->month, $schedule->year),
                'progress' => $this->calculateProgress($schedule),
                'status' => $schedule->status,
                'pic' => $schedule->pic?->name,
                'category' => $schedule->budgetItem->subActivity->category ?? null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $ganttData,
        ]);
    }

    public function updateStatus(Request $request, OperationalSchedule $operationalSchedule): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:PLANNED,IN_PROGRESS,COMPLETED,CANCELLED',
            'notes' => 'nullable|string',
        ]);

        $operationalSchedule->update($validated);
        $operationalSchedule->load(['budgetItem.subActivity', 'pic']);

        return response()->json([
            'success' => true,
            'message' => 'Status jadwal berhasil diperbarui.',
            'data' => $operationalSchedule,
        ]);
    }

    public function assignPic(Request $request, OperationalSchedule $operationalSchedule): JsonResponse
    {
        $validated = $request->validate([
            'pic_user_id' => 'required|exists:users,id',
        ]);

        $operationalSchedule->update(['pic_user_id' => $validated['pic_user_id']]);
        $operationalSchedule->load(['budgetItem.subActivity', 'pic']);

        return response()->json([
            'success' => true,
            'message' => 'PIC berhasil ditugaskan.',
            'data' => $operationalSchedule,
        ]);
    }

    private function getMonthName(int $month): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
            4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September',
            10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $months[$month] ?? '';
    }

    private function calculateProgress(OperationalSchedule $schedule): int
    {
        return match($schedule->status) {
            'PLANNED' => 0,
            'IN_PROGRESS' => 50,
            'COMPLETED' => 100,
            'CANCELLED' => 0,
            default => 0,
        };
    }
}
