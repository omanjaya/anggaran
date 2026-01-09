<?php

namespace App\Services;

use App\Models\Program;
use App\Models\Activity;
use App\Models\SubActivity;
use App\Models\BudgetItem;
use App\Models\MonthlyPlan;
use App\Models\Skpd;
use App\Models\AccountCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DpaImportService
{
    protected DpaPdfParserService $parser;
    protected array $importLog = [];
    protected int $userId;

    public function __construct(DpaPdfParserService $parser)
    {
        $this->parser = $parser;
    }

    public function import(string $filePath, int $userId): array
    {
        $this->userId = $userId;
        $this->importLog = [];

        try {
            $data = $this->parser->parse($filePath);

            DB::beginTransaction();

            // 1. Create or find SKPD
            $skpd = $this->importSkpd($data['header']);

            // 2. Create or find Program
            $program = $this->importProgram($data['header'], $skpd);

            // 3. Create or find Activity
            $activity = $this->importActivity($data['header'], $program);

            // 4. Create or find Sub Activity
            $subActivity = $this->importSubActivity($data, $activity);

            // 5. Import Budget Items
            $budgetItems = $this->importBudgetItems($data['budget_items'], $subActivity);

            // 6. Import Monthly Plans
            $this->importMonthlyPlans($data['monthly_plan'], $budgetItems, $data['header']['tahun_anggaran']);

            // 7. Import Account Codes
            $this->importAccountCodes($data['budget_items']);

            // Update totals
            $this->updateTotals($subActivity);

            DB::commit();

            $this->importLog[] = [
                'type' => 'success',
                'message' => 'Import berhasil',
                'details' => [
                    'skpd' => $skpd->name,
                    'program' => $program->name,
                    'activity' => $activity->name,
                    'sub_activity' => $subActivity->name,
                    'budget_items_count' => count($budgetItems),
                    'total_budget' => $subActivity->total_budget,
                ],
            ];

            return [
                'success' => true,
                'data' => $data,
                'log' => $this->importLog,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('DPA Import Error: ' . $e->getMessage(), [
                'file' => $filePath,
                'trace' => $e->getTraceAsString(),
            ]);

            $this->importLog[] = [
                'type' => 'error',
                'message' => 'Import gagal: ' . $e->getMessage(),
            ];

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'log' => $this->importLog,
            ];
        }
    }

    protected function importSkpd(array $header): Skpd
    {
        $skpd = Skpd::firstOrCreate(
            ['code' => $header['organisasi']['code']],
            [
                'name' => $header['organisasi']['name'],
                'short_name' => $this->extractShortName($header['organisasi']['name']),
                'is_active' => true,
            ]
        );

        $this->importLog[] = [
            'type' => 'info',
            'message' => $skpd->wasRecentlyCreated ? 'SKPD baru dibuat' : 'SKPD sudah ada',
            'details' => $skpd->name,
        ];

        return $skpd;
    }

    protected function importProgram(array $header, Skpd $skpd): Program
    {
        // Map based on program/activity name to valid BudgetCategory enum
        $category = $this->mapToCategory($header['program']['name'] ?? '');

        $program = Program::firstOrCreate(
            ['code' => $header['program']['code']],
            [
                'skpd_id' => $skpd->id,
                'name' => $header['program']['name'],
                'category' => $category,
                'fiscal_year' => $header['tahun_anggaran'] ?? date('Y'),
                'total_budget' => 0,
                'is_active' => true,
            ]
        );

        $this->importLog[] = [
            'type' => 'info',
            'message' => $program->wasRecentlyCreated ? 'Program baru dibuat' : 'Program sudah ada',
            'details' => $program->code . ' - ' . $program->name,
        ];

        return $program;
    }

    protected function importActivity(array $header, Program $program): Activity
    {
        $activity = Activity::firstOrCreate(
            [
                'program_id' => $program->id,
                'code' => $header['kegiatan']['code'],
            ],
            [
                'name' => $header['kegiatan']['name'],
                'total_budget' => 0,
                'is_active' => true,
            ]
        );

        $this->importLog[] = [
            'type' => 'info',
            'message' => $activity->wasRecentlyCreated ? 'Kegiatan baru dibuat' : 'Kegiatan sudah ada',
            'details' => $activity->code . ' - ' . $activity->name,
        ];

        return $activity;
    }

    protected function importSubActivity(array $data, Activity $activity): SubActivity
    {
        $subActivityData = $data['sub_activity'];
        $header = $data['header'];

        $subActivity = SubActivity::firstOrCreate(
            [
                'activity_id' => $activity->id,
                'code' => $subActivityData['code'],
            ],
            [
                'name' => $subActivityData['name'],
                'total_budget' => $header['alokasi_tahun'] ?? 0,
                'is_active' => true,
            ]
        );

        // Update total budget if exists
        if (!$subActivity->wasRecentlyCreated) {
            $subActivity->update([
                'total_budget' => $header['alokasi_tahun'] ?? $subActivity->total_budget,
            ]);
        }

        $this->importLog[] = [
            'type' => 'info',
            'message' => $subActivity->wasRecentlyCreated ? 'Sub Kegiatan baru dibuat' : 'Sub Kegiatan sudah ada',
            'details' => $subActivity->code . ' - ' . $subActivity->name,
        ];

        return $subActivity;
    }

    protected function importBudgetItems(array $items, SubActivity $subActivity): array
    {
        $budgetItems = [];

        foreach ($items as $item) {
            if (empty($item['code'])) continue;

            // Get details from the item
            $details = $item['details'] ?? [];
            $totalVolume = 0;
            $avgUnitPrice = 0;
            $unit = 'Paket';

            if (!empty($details)) {
                foreach ($details as $detail) {
                    $totalVolume += $detail['volume'] ?? 0;
                }
                // Use first detail's unit and calculate average price
                $firstDetail = $details[0] ?? [];
                $unit = $firstDetail['unit'] ?? 'Paket';
                $avgUnitPrice = $totalVolume > 0 ? ($item['amount'] / $totalVolume) : $item['amount'];
            } else {
                $totalVolume = 1;
                $avgUnitPrice = $item['amount'];
            }

            $budgetItem = BudgetItem::updateOrCreate(
                [
                    'sub_activity_id' => $subActivity->id,
                    'code' => $item['code'],
                ],
                [
                    'name' => $item['description'],
                    'unit' => $unit,
                    'volume' => $totalVolume,
                    'unit_price' => $avgUnitPrice,
                    'total_budget' => $item['amount'],
                    'is_active' => true,
                ]
            );

            $budgetItems[] = $budgetItem;

            $this->importLog[] = [
                'type' => 'info',
                'message' => 'Item belanja diimport',
                'details' => $item['code'] . ' - Rp' . number_format($item['amount'], 0, ',', '.'),
            ];
        }

        return $budgetItems;
    }

    protected function importMonthlyPlans(array $monthlyPlan, array $budgetItems, ?int $year): void
    {
        $year = $year ?? date('Y');
        $totalBudget = array_sum($monthlyPlan);

        if ($totalBudget <= 0 || empty($budgetItems)) return;

        // Distribute monthly plan proportionally to budget items
        foreach ($budgetItems as $budgetItem) {
            $ratio = $totalBudget > 0 ? ($budgetItem->total_budget / $totalBudget) : 0;

            foreach ($monthlyPlan as $month => $amount) {
                if ($amount <= 0) continue;

                $plannedAmount = $amount * $ratio;
                $plannedVolume = $budgetItem->unit_price > 0
                    ? $plannedAmount / $budgetItem->unit_price
                    : 0;

                MonthlyPlan::updateOrCreate(
                    [
                        'budget_item_id' => $budgetItem->id,
                        'month' => $month,
                        'year' => $year,
                    ],
                    [
                        'planned_volume' => round($plannedVolume, 2),
                        'planned_amount' => round($plannedAmount, 2),
                        'created_by' => $this->userId,
                    ]
                );
            }
        }

        $this->importLog[] = [
            'type' => 'info',
            'message' => 'Rencana bulanan diimport',
            'details' => 'Total: Rp' . number_format($totalBudget, 0, ',', '.'),
        ];
    }

    protected function importAccountCodes(array $items): void
    {
        foreach ($items as $item) {
            if (empty($item['code'])) continue;

            $code = $item['code'];
            $parts = explode('.', $code);
            $parentCode = null;

            // Create hierarchical account codes
            for ($i = 1; $i <= count($parts); $i++) {
                $currentCode = implode('.', array_slice($parts, 0, $i));
                $description = $i === count($parts) ? $item['description'] : 'Level ' . $i;

                AccountCode::firstOrCreate(
                    ['code' => $currentCode],
                    [
                        'description' => $description,
                        'level' => $i,
                        'parent_code' => $parentCode,
                        'is_active' => true,
                    ]
                );

                $parentCode = $currentCode;
            }
        }
    }

    protected function updateTotals(SubActivity $subActivity): void
    {
        // Update sub activity total
        $subActivityTotal = BudgetItem::where('sub_activity_id', $subActivity->id)
            ->where('is_active', true)
            ->sum('total_budget');
        $subActivity->update(['total_budget' => $subActivityTotal]);

        // Update activity total
        $activity = $subActivity->activity;
        $activityTotal = SubActivity::where('activity_id', $activity->id)
            ->where('is_active', true)
            ->sum('total_budget');
        $activity->update(['total_budget' => $activityTotal]);

        // Update program total
        $program = $activity->program;
        $programTotal = Activity::where('program_id', $program->id)
            ->where('is_active', true)
            ->sum('total_budget');
        $program->update(['total_budget' => $programTotal]);
    }

    protected function extractShortName(string $name): string
    {
        // Extract short name from full name
        // E.g., "Dinas Komunikasi, Informatika dan Statistik" -> "Diskominfos"
        $words = preg_split('/[\s,]+/', $name);
        $short = '';

        foreach ($words as $word) {
            if (in_array(strtolower($word), ['dan', 'dan/atau', 'atau', 'serta'])) continue;
            if (strlen($word) > 2) {
                $short .= strtoupper(substr($word, 0, 3));
            }
        }

        return substr($short, 0, 20);
    }

    protected function mapToCategory(string $name): string
    {
        $name = strtolower($name);

        // Map keywords to BudgetCategory enum values
        if (str_contains($name, 'analisis') || str_contains($name, 'kebutuhan')) {
            return 'ANALISIS';
        }
        if (str_contains($name, 'tata kelola') || str_contains($name, 'kebijakan')) {
            return 'TATA_KELOLA';
        }
        if (str_contains($name, 'operasional')) {
            return 'OPERASIONALISASI';
        }
        if (str_contains($name, 'layanan') || str_contains($name, 'penyediaan')) {
            return 'LAYANAN';
        }
        if (str_contains($name, 'elektronik') || str_contains($name, 'pelaksanaan')) {
            return 'ELEK_NON_ELEK';
        }

        // Default category
        return 'OPERASIONALISASI';
    }
}
