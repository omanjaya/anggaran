<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Activity;
use App\Models\SubActivity;
use App\Models\BudgetItem;
use App\Models\MonthlyPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ImportController extends Controller
{
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
            'type' => 'required|in:dpa,plgk',
        ]);

        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getPathname());

        $preview = [];

        if ($request->type === 'dpa') {
            $preview = $this->previewDpa($spreadsheet);
        } else {
            $preview = $this->previewPlgk($spreadsheet);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'sheets' => $spreadsheet->getSheetNames(),
                'preview' => $preview,
            ],
        ]);
    }

    public function importDpa(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
            'program_id' => 'nullable|exists:programs,id',
            'activity_id' => 'nullable|exists:activities,id',
            'sub_activity_id' => 'nullable|exists:sub_activities,id',
            'category' => 'nullable|string',
            'year' => 'required|integer',
        ]);

        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getPathname());

        DB::beginTransaction();
        try {
            $result = $this->processDpaImport($spreadsheet, $request->all());
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Berhasil import DPA. {$result['items_created']} item belanja dibuat.",
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal import DPA: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function importPlgk(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
            'sub_activity_id' => 'required|exists:sub_activities,id',
            'year' => 'required|integer',
        ]);

        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getPathname());

        DB::beginTransaction();
        try {
            $result = $this->processPlgkImport($spreadsheet, $request->all());
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Berhasil import PLGK. {$result['plans_created']} rencana bulanan dibuat.",
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal import PLGK: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function downloadTemplate(Request $request)
    {
        $type = $request->get('type', 'dpa');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        if ($type === 'dpa') {
            $this->generateDpaTemplate($sheet);
        } else {
            $this->generatePlgkTemplate($sheet);
        }

        $filename = "template-{$type}-" . now()->format('Y-m-d') . '.xlsx';
        $tempPath = storage_path('app/temp/' . $filename);

        if (!is_dir(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($tempPath);

        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    }

    private function previewDpa(Spreadsheet $spreadsheet): array
    {
        $preview = [];

        // Try to read from DPA or PLGK sheet
        $sheetNames = ['DPA', 'PLGK', 'Sheet1'];
        $sheet = null;

        foreach ($sheetNames as $name) {
            if ($spreadsheet->sheetNameExists($name)) {
                $sheet = $spreadsheet->getSheetByName($name);
                break;
            }
        }

        if (!$sheet) {
            $sheet = $spreadsheet->getActiveSheet();
        }

        // Read header row to identify columns
        $headerRow = 5; // Based on Pak Kadis format
        $dataStartRow = 7;

        // Try to find data rows
        $maxRow = min($sheet->getHighestRow(), 50); // Preview first 50 rows

        for ($row = $dataStartRow; $row <= $maxRow; $row++) {
            $code = $sheet->getCell("B{$row}")->getValue();
            $name = $sheet->getCell("C{$row}")->getValue();
            $volume = $sheet->getCell("G{$row}")->getValue();
            $unit = $sheet->getCell("H{$row}")->getValue();
            $unitPrice = $sheet->getCell("I{$row}")->getValue();
            $total = $sheet->getCell("J{$row}")->getValue();

            // Skip empty rows or header rows
            if (empty($name) || !is_numeric($volume)) {
                continue;
            }

            $preview[] = [
                'code' => $code,
                'name' => $name,
                'volume' => (float) $volume,
                'unit' => $unit,
                'unit_price' => (float) $unitPrice,
                'total' => (float) $total,
            ];
        }

        return $preview;
    }

    private function previewPlgk(Spreadsheet $spreadsheet): array
    {
        $preview = [];
        $months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        foreach ($months as $index => $monthName) {
            if ($spreadsheet->sheetNameExists($monthName)) {
                $sheet = $spreadsheet->getSheetByName($monthName);
                $rowCount = 0;

                for ($row = 7; $row <= min($sheet->getHighestRow(), 20); $row++) {
                    $name = $sheet->getCell("C{$row}")->getValue();
                    if (!empty($name) && !str_starts_with($name, 'Sub Kegiatan')) {
                        $rowCount++;
                    }
                }

                $preview[] = [
                    'month' => $index + 1,
                    'month_name' => $monthName,
                    'items_found' => $rowCount,
                ];
            }
        }

        return $preview;
    }

    private function processDpaImport(Spreadsheet $spreadsheet, array $params): array
    {
        $subActivityId = $params['sub_activity_id'];
        $year = $params['year'];

        // Get the DPA or PLGK sheet
        $sheet = null;
        foreach (['DPA', 'PLGK', 'Sheet1'] as $name) {
            if ($spreadsheet->sheetNameExists($name)) {
                $sheet = $spreadsheet->getSheetByName($name);
                break;
            }
        }

        if (!$sheet) {
            $sheet = $spreadsheet->getActiveSheet();
        }

        $itemsCreated = 0;
        $errors = [];

        // Read budget items from the sheet
        // Based on Pak Kadis format: B=Kode, C=Uraian, G=Volume, H=Satuan, I=Harga, J=Total
        for ($row = 7; $row <= $sheet->getHighestRow(); $row++) {
            $code = trim((string) $sheet->getCell("B{$row}")->getValue());
            $name = trim((string) $sheet->getCell("C{$row}")->getValue());
            $volume = $sheet->getCell("G{$row}")->getValue();
            $unit = trim((string) $sheet->getCell("H{$row}")->getValue());
            $unitPrice = $sheet->getCell("I{$row}")->getValue();
            $total = $sheet->getCell("J{$row}")->getValue();

            // Skip empty or header rows
            if (empty($name) || !is_numeric($volume) || str_starts_with($name, 'Sub Kegiatan')) {
                continue;
            }

            // Skip if code looks like a header (account code format: 5.x.xx.xx.xx)
            if (!empty($code) && !preg_match('/^5\.\d/', $code)) {
                continue;
            }

            try {
                BudgetItem::updateOrCreate(
                    [
                        'sub_activity_id' => $subActivityId,
                        'code' => $code ?: 'AUTO-' . str_pad($itemsCreated + 1, 4, '0', STR_PAD_LEFT),
                    ],
                    [
                        'name' => $name,
                        'volume' => (float) $volume,
                        'unit' => $unit ?: 'paket',
                        'unit_price' => (float) $unitPrice,
                        'total_budget' => (float) $total ?: ((float) $volume * (float) $unitPrice),
                        'is_active' => true,
                    ]
                );
                $itemsCreated++;
            } catch (\Exception $e) {
                $errors[] = "Row {$row}: {$e->getMessage()}";
            }
        }

        return [
            'items_created' => $itemsCreated,
            'errors' => $errors,
        ];
    }

    private function processPlgkImport(Spreadsheet $spreadsheet, array $params): array
    {
        $subActivityId = $params['sub_activity_id'];
        $year = $params['year'];

        $months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        $plansCreated = 0;
        $errors = [];

        // Get existing budget items for this sub-activity
        $budgetItems = BudgetItem::where('sub_activity_id', $subActivityId)
            ->get()
            ->keyBy('name');

        foreach ($months as $monthIndex => $monthName) {
            $month = $monthIndex + 1;

            if (!$spreadsheet->sheetNameExists($monthName)) {
                continue;
            }

            $sheet = $spreadsheet->getSheetByName($monthName);

            // Based on format: K=Rencana Fisik, L=Rencana Keuangan (columns may vary)
            for ($row = 7; $row <= $sheet->getHighestRow(); $row++) {
                $itemName = trim((string) $sheet->getCell("C{$row}")->getValue());
                $plannedVolume = $sheet->getCell("K{$row}")->getValue(); // Rencana Fisik
                $plannedAmount = $sheet->getCell("L{$row}")->getValue(); // Rencana Keuangan

                if (empty($itemName) || str_starts_with($itemName, 'Sub Kegiatan')) {
                    continue;
                }

                // Try to match with existing budget item
                $budgetItem = $budgetItems->get($itemName);

                if (!$budgetItem) {
                    // Try fuzzy match
                    $budgetItem = $budgetItems->first(function ($item) use ($itemName) {
                        return str_contains(strtolower($item->name), strtolower(substr($itemName, 0, 30)));
                    });
                }

                if (!$budgetItem) {
                    $errors[] = "Bulan {$monthName}, Row {$row}: Item '{$itemName}' tidak ditemukan.";
                    continue;
                }

                try {
                    MonthlyPlan::updateOrCreate(
                        [
                            'budget_item_id' => $budgetItem->id,
                            'month' => $month,
                            'year' => $year,
                        ],
                        [
                            'planned_volume' => (float) $plannedVolume ?: 0,
                            'planned_amount' => (float) $plannedAmount ?: 0,
                        ]
                    );
                    $plansCreated++;
                } catch (\Exception $e) {
                    $errors[] = "Bulan {$monthName}, Row {$row}: {$e->getMessage()}";
                }
            }
        }

        return [
            'plans_created' => $plansCreated,
            'errors' => $errors,
        ];
    }

    private function generateDpaTemplate($sheet): void
    {
        $sheet->setTitle('DPA');

        // Headers
        $headers = ['A1' => 'No', 'B1' => 'Kode Rekening', 'C1' => 'Uraian/Nama Item',
            'D1' => 'Volume', 'E1' => 'Satuan', 'F1' => 'Harga Satuan', 'G1' => 'Total'];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Sample data
        $sheet->setCellValue('A2', '1');
        $sheet->setCellValue('B2', '5.1.02.01.01.0025');
        $sheet->setCellValue('C2', 'Kertas HVS A4 80gsm');
        $sheet->setCellValue('D2', '10');
        $sheet->setCellValue('E2', 'rim');
        $sheet->setCellValue('F2', '50000');
        $sheet->setCellValue('G2', '=D2*F2');
    }

    private function generatePlgkTemplate($sheet): void
    {
        $sheet->setTitle('PLGK');

        $headers = ['A1' => 'No', 'B1' => 'Kode Rekening', 'C1' => 'Uraian',
            'D1' => 'Jan', 'E1' => 'Feb', 'F1' => 'Mar', 'G1' => 'Apr',
            'H1' => 'Mei', 'I1' => 'Jun', 'J1' => 'Jul', 'K1' => 'Agt',
            'L1' => 'Sep', 'M1' => 'Okt', 'N1' => 'Nov', 'O1' => 'Des', 'P1' => 'Total'];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }
    }
}
