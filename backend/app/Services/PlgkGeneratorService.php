<?php

namespace App\Services;

use App\Models\BudgetItem;
use App\Models\MonthlyPlan;
use App\Models\SubActivity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlgkGeneratorService
{
    /**
     * Allocation methods
     */
    public const METHOD_EQUAL = 'equal';
    public const METHOD_CUSTOM = 'custom';
    public const METHOD_COPY_PREVIOUS = 'copy_previous';

    /**
     * Generate PLGK (monthly plans) from DPA (budget items)
     *
     * @param SubActivity $subActivity
     * @param string $method Allocation method
     * @param int $year Target year
     * @param array|null $customAllocations Custom allocations per month
     * @return Collection Generated monthly plans
     */
    public function generate(
        SubActivity $subActivity,
        string $method = self::METHOD_EQUAL,
        int $year = null,
        ?array $customAllocations = null
    ): Collection {
        $year = $year ?? now()->year;
        $budgetItems = $subActivity->budgetItems;
        $generatedPlans = collect();

        DB::beginTransaction();

        try {
            foreach ($budgetItems as $budgetItem) {
                $plans = match ($method) {
                    self::METHOD_EQUAL => $this->generateEqual($budgetItem, $year),
                    self::METHOD_CUSTOM => $this->generateCustom($budgetItem, $year, $customAllocations[$budgetItem->id] ?? null),
                    self::METHOD_COPY_PREVIOUS => $this->generateFromPreviousYear($budgetItem, $year),
                    default => throw new \InvalidArgumentException("Invalid allocation method: {$method}")
                };

                $generatedPlans = $generatedPlans->merge($plans);
            }

            DB::commit();

            return $generatedPlans;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate equal distribution across 12 months
     */
    protected function generateEqual(BudgetItem $budgetItem, int $year): Collection
    {
        // Delete existing plans for this year
        MonthlyPlan::where('budget_item_id', $budgetItem->id)
            ->where('year', $year)
            ->delete();

        $monthlyVolume = round($budgetItem->total_volume / 12, 2);
        $monthlyAmount = round($budgetItem->total_amount / 12, 2);

        // Handle remainder for last month
        $volumeRemainder = $budgetItem->total_volume - ($monthlyVolume * 11);
        $amountRemainder = $budgetItem->total_amount - ($monthlyAmount * 11);

        $plans = collect();

        for ($month = 1; $month <= 12; $month++) {
            $isLastMonth = $month === 12;

            $plan = MonthlyPlan::create([
                'budget_item_id' => $budgetItem->id,
                'month' => $month,
                'year' => $year,
                'planned_volume' => $isLastMonth ? $volumeRemainder : $monthlyVolume,
                'planned_amount' => $isLastMonth ? $amountRemainder : $monthlyAmount,
            ]);

            $plans->push($plan);
        }

        return $plans;
    }

    /**
     * Generate with custom allocation per month
     */
    protected function generateCustom(BudgetItem $budgetItem, int $year, ?array $allocations): Collection
    {
        if (!$allocations) {
            return $this->generateEqual($budgetItem, $year);
        }

        // Validate allocations sum
        $totalVolume = array_sum(array_column($allocations, 'volume'));
        $totalAmount = array_sum(array_column($allocations, 'amount'));

        // Allow small tolerance for rounding
        $volumeDiff = abs($totalVolume - $budgetItem->total_volume);
        $amountDiff = abs($totalAmount - $budgetItem->total_amount);

        if ($volumeDiff > 0.01 || $amountDiff > 1) {
            throw new \InvalidArgumentException(
                "Custom allocations sum ({$totalVolume}, {$totalAmount}) doesn't match budget item " .
                "({$budgetItem->total_volume}, {$budgetItem->total_amount})"
            );
        }

        // Delete existing plans
        MonthlyPlan::where('budget_item_id', $budgetItem->id)
            ->where('year', $year)
            ->delete();

        $plans = collect();

        foreach ($allocations as $allocation) {
            $plan = MonthlyPlan::create([
                'budget_item_id' => $budgetItem->id,
                'month' => $allocation['month'],
                'year' => $year,
                'planned_volume' => $allocation['volume'],
                'planned_amount' => $allocation['amount'],
            ]);

            $plans->push($plan);
        }

        return $plans;
    }

    /**
     * Copy allocation pattern from previous year
     */
    protected function generateFromPreviousYear(BudgetItem $budgetItem, int $year): Collection
    {
        $previousPlans = MonthlyPlan::where('budget_item_id', $budgetItem->id)
            ->where('year', $year - 1)
            ->get();

        if ($previousPlans->isEmpty()) {
            // No previous year data, use equal distribution
            return $this->generateEqual($budgetItem, $year);
        }

        // Calculate percentage distribution from previous year
        $previousTotal = $previousPlans->sum('planned_amount');
        
        if ($previousTotal == 0) {
            return $this->generateEqual($budgetItem, $year);
        }

        // Delete existing plans
        MonthlyPlan::where('budget_item_id', $budgetItem->id)
            ->where('year', $year)
            ->delete();

        $plans = collect();
        $allocatedAmount = 0;
        $allocatedVolume = 0;

        foreach ($previousPlans as $index => $prevPlan) {
            $isLast = $index === count($previousPlans) - 1;
            $percentage = $prevPlan->planned_amount / $previousTotal;

            if ($isLast) {
                // Handle remainder
                $amount = $budgetItem->total_amount - $allocatedAmount;
                $volume = $budgetItem->total_volume - $allocatedVolume;
            } else {
                $amount = round($budgetItem->total_amount * $percentage, 2);
                $volume = round($budgetItem->total_volume * $percentage, 2);
                $allocatedAmount += $amount;
                $allocatedVolume += $volume;
            }

            $plan = MonthlyPlan::create([
                'budget_item_id' => $budgetItem->id,
                'month' => $prevPlan->month,
                'year' => $year,
                'planned_volume' => $volume,
                'planned_amount' => $amount,
            ]);

            $plans->push($plan);
        }

        return $plans;
    }

    /**
     * Preview PLGK generation without saving
     */
    public function preview(
        SubActivity $subActivity,
        string $method = self::METHOD_EQUAL,
        int $year = null,
        ?array $customAllocations = null
    ): array {
        $year = $year ?? now()->year;
        $budgetItems = $subActivity->budgetItems;
        $preview = [];

        foreach ($budgetItems as $budgetItem) {
            $monthlyData = [];

            if ($method === self::METHOD_EQUAL) {
                $monthlyVolume = round($budgetItem->total_volume / 12, 2);
                $monthlyAmount = round($budgetItem->total_amount / 12, 2);
                $volumeRemainder = $budgetItem->total_volume - ($monthlyVolume * 11);
                $amountRemainder = $budgetItem->total_amount - ($monthlyAmount * 11);

                for ($month = 1; $month <= 12; $month++) {
                    $isLast = $month === 12;
                    $monthlyData[] = [
                        'month' => $month,
                        'month_name' => $this->getMonthName($month),
                        'planned_volume' => $isLast ? $volumeRemainder : $monthlyVolume,
                        'planned_amount' => $isLast ? $amountRemainder : $monthlyAmount,
                    ];
                }
            } elseif ($method === self::METHOD_CUSTOM && isset($customAllocations[$budgetItem->id])) {
                $monthlyData = $customAllocations[$budgetItem->id];
            }

            $preview[] = [
                'budget_item' => [
                    'id' => $budgetItem->id,
                    'account_code' => $budgetItem->account_code,
                    'description' => $budgetItem->description,
                    'unit' => $budgetItem->unit,
                    'total_volume' => $budgetItem->total_volume,
                    'unit_price' => $budgetItem->unit_price,
                    'total_amount' => $budgetItem->total_amount,
                ],
                'monthly_plans' => $monthlyData,
            ];
        }

        return [
            'sub_activity' => [
                'id' => $subActivity->id,
                'category' => $subActivity->category,
                'name' => $subActivity->name,
                'budget_current_year' => $subActivity->budget_current_year,
            ],
            'year' => $year,
            'method' => $method,
            'items' => $preview,
            'summary' => [
                'total_items' => count($preview),
                'total_budget' => $budgetItems->sum('total_amount'),
            ],
        ];
    }

    /**
     * Get month name in Indonesian
     */
    protected function getMonthName(int $month): string
    {
        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        return $months[$month] ?? '';
    }

    /**
     * Validate PLGK data
     */
    public function validate(SubActivity $subActivity, int $year): array
    {
        $budgetItems = $subActivity->budgetItems;
        $issues = [];

        foreach ($budgetItems as $budgetItem) {
            $plans = MonthlyPlan::where('budget_item_id', $budgetItem->id)
                ->where('year', $year)
                ->get();

            $plannedTotal = $plans->sum('planned_amount');
            $plannedVolume = $plans->sum('planned_volume');

            // Check if all 12 months exist
            if ($plans->count() !== 12) {
                $issues[] = [
                    'budget_item_id' => $budgetItem->id,
                    'type' => 'missing_months',
                    'message' => "Only {$plans->count()} months planned, expected 12",
                ];
            }

            // Check if totals match
            if (abs($plannedTotal - $budgetItem->total_amount) > 1) {
                $issues[] = [
                    'budget_item_id' => $budgetItem->id,
                    'type' => 'amount_mismatch',
                    'message' => "Planned total ({$plannedTotal}) doesn't match budget ({$budgetItem->total_amount})",
                ];
            }

            if (abs($plannedVolume - $budgetItem->total_volume) > 0.01) {
                $issues[] = [
                    'budget_item_id' => $budgetItem->id,
                    'type' => 'volume_mismatch',
                    'message' => "Planned volume ({$plannedVolume}) doesn't match budget ({$budgetItem->total_volume})",
                ];
            }
        }

        return [
            'is_valid' => empty($issues),
            'issues' => $issues,
        ];
    }
}
