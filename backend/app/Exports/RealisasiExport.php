<?php

namespace App\Exports;

use App\Models\BudgetItem;
use App\Models\MonthlyPlan;
use App\Models\MonthlyRealization;
use App\Models\Program;
use App\Models\Skpd;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RealisasiExport
{
    protected int $year;
    protected ?int $month;
    protected ?int $programId;
    protected ?int $skpdId;
    protected Spreadsheet $spreadsheet;

    protected array $monthNames = [
        1 => 'Jan', 2 => 'Peb', 3 => 'Mar', 4 => 'Apr',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];

    protected array $monthNamesFull = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];

    public function __construct(int $year, ?int $month = null, ?int $programId = null, ?int $skpdId = null)
    {
        $this->year = $year;
        $this->month = $month;
        $this->programId = $programId;
        $this->skpdId = $skpdId;
        $this->spreadsheet = new Spreadsheet();
    }

    public function generate(): Spreadsheet
    {
        // Remove default sheet
        $this->spreadsheet->removeSheetByIndex(0);

        // Create DPA sheet
        $this->createDpaSheet();

        // Create PLGK sheet
        $this->createPlgkSheet();

        // Create Bobot sheet
        $this->createBobotSheet();

        // Create ROK OP sheet
        $this->createRokOpSheet();

        // Create monthly sheets
        $months = $this->month ? [$this->month] : range(1, 12);
        foreach ($months as $month) {
            $this->createMonthlySheet($month);
        }

        // Set first sheet as active
        $this->spreadsheet->setActiveSheetIndex(0);

        return $this->spreadsheet;
    }

    public function download(string $filename): void
    {
        $this->generate();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($this->spreadsheet);
        $writer->save('php://output');
    }

    protected function createDpaSheet(): void
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('DPA');

        // Get data
        $program = $this->programId ? Program::find($this->programId) : Program::first();
        $skpd = $this->skpdId ? Skpd::find($this->skpdId) : Skpd::first();

        // Header
        $sheet->mergeCells('F2:R2');
        $sheet->setCellValue('F2', 'RENCANA KERJA DAN ANGGARAN');
        $sheet->setCellValue('S2', 'Formulir RKA - SKPD 2.2.1');

        $sheet->mergeCells('F3:R3');
        $sheet->setCellValue('F3', 'SATUAN KERJA PERANGKAT DAERAH');

        $sheet->mergeCells('F4:R4');
        $sheet->setCellValue('F4', 'PEMERINTAH PROVINSI BALI');

        $sheet->mergeCells('F5:R5');
        $sheet->setCellValue('F5', 'TAHUN ANGGARAN ' . $this->year);

        // Info rows
        $sheet->setCellValue('B6', ' URUSAN PEMERINTAH');
        $sheet->setCellValue('H6', ':');
        $sheet->setCellValue('I6', $program?->urusan ?? '-');

        $sheet->setCellValue('B7', ' ORGANISASI');
        $sheet->setCellValue('F7', ':');
        $sheet->setCellValue('G7', $skpd ? "{$skpd->code} - {$skpd->name}" : '-');

        $sheet->setCellValue('B8', ' Program');
        $sheet->setCellValue('F8', ':');
        $sheet->setCellValue('G8', $program ? "{$program->code} - {$program->name}" : '-');

        // Calculate totals
        $query = BudgetItem::query();
        if ($this->programId) {
            $query->whereHas('subActivity.activity', fn($q) => $q->where('program_id', $this->programId));
        }

        $totalBudget = $query->sum('total_budget');

        $sheet->setCellValue('B11', 'Jumlah Tahun n-1');
        $sheet->setCellValue('F11', ':');
        $sheet->setCellValue('G11', 'Rp' . number_format(0, 2, ',', '.'));

        $sheet->setCellValue('B12', 'Jumlah Tahun n');
        $sheet->setCellValue('F12', ':');
        $sheet->setCellValue('G12', 'Rp' . number_format($totalBudget, 2, ',', '.'));

        // Style header
        $sheet->getStyle('F2:F5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Column headers for DPA items
        $row = 15;
        $sheet->setCellValue('B' . $row, 'Indikator');
        $sheet->setCellValue('F' . $row, 'Tolok Ukur Kegiatan');
        $sheet->setCellValue('R' . $row, 'Target Kinerja');

        $row = 17;
        $headers = ['No', 'Kode Rekening', 'Uraian', 'Vol', 'Satuan', 'Harga Satuan', 'Jumlah'];
        $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
        foreach ($headers as $i => $header) {
            $sheet->setCellValue($cols[$i] . $row, $header);
        }

        // Style headers
        $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'D9E1F2']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Budget items data
        $budgetItems = BudgetItem::with('subActivity.activity.program')
            ->when($this->programId, fn($q) => $q->whereHas('subActivity.activity', fn($q2) => $q2->where('program_id', $this->programId)))
            ->get();

        $row++;
        $no = 1;
        foreach ($budgetItems as $item) {
            $sheet->setCellValue('A' . $row, $no++);
            $sheet->setCellValue('B' . $row, $item->code);
            $sheet->setCellValue('C' . $row, $item->name);
            $sheet->setCellValue('D' . $row, $item->volume);
            $sheet->setCellValue('E' . $row, $item->unit);
            $sheet->setCellValue('F' . $row, $item->unit_price);
            $sheet->setCellValue('G' . $row, $item->total_budget);

            $sheet->getStyle('F' . $row . ':G' . $row)->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    protected function createPlgkSheet(): void
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('PLGK');

        $sheet->setCellValue('B1', 'RENCANA FISIK DAN KEUANGAN');
        $sheet->mergeCells('B1:Z1');
        $sheet->getStyle('B1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Headers
        $row = 5;
        $sheet->setCellValue('B' . $row, 'Kode Rekening');
        $sheet->setCellValue('C' . $row, 'Uraian');
        $sheet->setCellValue('D' . $row, 'Total Anggaran');

        // Monthly headers
        $col = 'E';
        foreach ($this->monthNames as $monthName) {
            $sheet->setCellValue($col . $row, $monthName);
            $col++;
        }

        // Style headers
        $sheet->getStyle('B' . $row . ':P' . $row)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'D9E1F2']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Data
        $budgetItems = BudgetItem::with(['monthlyPlans' => fn($q) => $q->where('year', $this->year)])
            ->when($this->programId, fn($q) => $q->whereHas('subActivity.activity', fn($q2) => $q2->where('program_id', $this->programId)))
            ->get();

        $row = 6;
        foreach ($budgetItems as $item) {
            $sheet->setCellValue('B' . $row, $item->code);
            $sheet->setCellValue('C' . $row, $item->name);
            $sheet->setCellValue('D' . $row, $item->total_budget);
            $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

            $col = 'E';
            foreach (range(1, 12) as $month) {
                $plan = $item->monthlyPlans->where('month', $month)->first();
                $sheet->setCellValue($col . $row, $plan?->planned_amount ?? 0);
                $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                $col++;
            }
            $row++;
        }

        // Auto-size columns
        foreach (range('B', 'P') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    protected function createBobotSheet(): void
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('Bobot');

        $sheet->setCellValue('B1', 'BOBOT ANGGARAN');
        $sheet->mergeCells('B1:P1');
        $sheet->getStyle('B1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Headers
        $row = 3;
        $sheet->setCellValue('B' . $row, 'Kode Rekening');
        $sheet->setCellValue('C' . $row, 'Uraian');
        $sheet->setCellValue('D' . $row, 'Total Anggaran');
        $sheet->setCellValue('E' . $row, 'Bobot (%)');

        $sheet->getStyle('B' . $row . ':E' . $row)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'D9E1F2']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // Data
        $budgetItems = BudgetItem::when($this->programId, fn($q) => $q->whereHas('subActivity.activity', fn($q2) => $q2->where('program_id', $this->programId)))->get();
        $totalBudget = $budgetItems->sum('total_budget');

        $row = 4;
        foreach ($budgetItems as $item) {
            $sheet->setCellValue('B' . $row, $item->code);
            $sheet->setCellValue('C' . $row, $item->name);
            $sheet->setCellValue('D' . $row, $item->total_budget);
            $sheet->setCellValue('E' . $row, $totalBudget > 0 ? ($item->total_budget / $totalBudget) * 100 : 0);

            $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('0.00');
            $row++;
        }

        // Auto-size columns
        foreach (range('B', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    protected function createRokOpSheet(): void
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('ROK OP');

        $sheet->setCellValue('B1', 'RENCANA OPERASIONAL (ROK OP)');
        $sheet->mergeCells('B1:L1');
        $sheet->getStyle('B1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Headers
        $row = 3;
        $headers = ['No', 'Kegiatan', 'Tanggal Mulai', 'Tanggal Selesai', 'Nilai', 'Status', 'PIC', 'Progress (%)'];
        $cols = ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
        foreach ($headers as $i => $header) {
            $sheet->setCellValue($cols[$i] . $row, $header);
        }

        $sheet->getStyle('B' . $row . ':I' . $row)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'D9E1F2']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // Data - operational schedules would be added here
        // For now, just a placeholder
        $sheet->setCellValue('B4', 'Data ROK OP akan ditampilkan di sini');

        foreach (range('B', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    protected function createMonthlySheet(int $month): void
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle($this->monthNames[$month]);

        $skpd = $this->skpdId ? Skpd::find($this->skpdId) : Skpd::first();
        $program = $this->programId ? Program::with('activities')->find($this->programId) : Program::with('activities')->first();

        // Header
        $sheet->setCellValue('B1', 'LAPORAN REALISASI FISIK DAN KEUANGAN');
        $sheet->mergeCells('B1:T1');
        $sheet->getStyle('B1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->setCellValue('B2', 'APBD PROVINSI BALI TAHUN ' . $this->year);
        $sheet->mergeCells('B2:T2');
        $sheet->getStyle('B2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->setCellValue('B3', 'BULAN: ' . strtoupper($this->monthNamesFull[$month]));
        $sheet->mergeCells('B3:T3');
        $sheet->getStyle('B3')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Info rows
        $sheet->setCellValue('B5', 'SKPD');
        $sheet->setCellValue('D5', ':');
        $sheet->setCellValue('E5', $skpd?->name ?? '-');
        $sheet->setCellValue('M5', 'PROGRAM');
        $sheet->setCellValue('N5', ':');
        $sheet->setCellValue('O5', $program?->name ?? '-');

        $sheet->setCellValue('B6', 'SUB UNIT KERJA');
        $sheet->setCellValue('D6', ':');
        $sheet->setCellValue('M6', 'KEGIATAN');
        $sheet->setCellValue('N6', ':');
        $sheet->setCellValue('O6', $program?->activities->first()?->name ?? '-');

        // Column headers - Row 9
        $row = 9;

        // Main headers
        $sheet->setCellValue('B' . $row, 'KODE REKENING');
        $sheet->mergeCells('B' . $row . ':B' . ($row + 3));

        $sheet->setCellValue('C' . $row, 'URAIAN BELANJA');
        $sheet->mergeCells('C' . $row . ':E' . ($row + 3));

        $sheet->setCellValue('F' . $row, 'RENCANA KEGIATAN');
        $sheet->mergeCells('F' . $row . ':K' . $row);

        $sheet->setCellValue('L' . $row, 'PELAKSANAAN KEGIATAN');
        $sheet->mergeCells('L' . $row . ':S' . $row);

        $sheet->setCellValue('T' . $row, 'SALDO');
        $sheet->mergeCells('T' . $row . ':T' . ($row + 3));

        // Sub headers - Row 10
        $row++;
        $sheet->setCellValue('F' . $row, 'VOL');
        $sheet->setCellValue('G' . $row, 'SAT');
        $sheet->setCellValue('H' . $row, 'LOKASI');
        $sheet->setCellValue('I' . $row, 'JML PAKET');
        $sheet->setCellValue('K' . $row, 'ANGGARAN');

        $sheet->setCellValue('L' . $row, 'KONTRAK/SWAKELOLA');
        $sheet->setCellValue('M' . $row, 'SISA ANGGARAN');
        $sheet->setCellValue('N' . $row, 'PROGRES');
        $sheet->mergeCells('N' . $row . ':S' . $row);

        // Sub-sub headers - Row 11
        $row++;
        $sheet->setCellValue('N' . $row, 'FISIK');
        $sheet->mergeCells('N' . $row . ':P' . $row);
        $sheet->setCellValue('Q' . $row, 'KEUANGAN');
        $sheet->mergeCells('Q' . $row . ':S' . $row);

        // Sub-sub-sub headers - Row 12
        $row++;
        $sheet->setCellValue('N' . $row, 'RENC');
        $sheet->setCellValue('O' . $row, 'REAL');
        $sheet->setCellValue('P' . $row, 'DEV');
        $sheet->setCellValue('Q' . $row, 'RENC');
        $sheet->setCellValue('R' . $row, 'REAL Rp.');
        $sheet->setCellValue('S' . $row, '%');

        // Column letters - Row 13
        $row++;
        $colLetters = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p'];
        $cols = ['B', 'C', 'F', 'G', 'H', 'I', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T'];
        foreach ($colLetters as $i => $letter) {
            if (isset($cols[$i])) {
                $sheet->setCellValue($cols[$i] . $row, $letter);
            }
        }

        // Style headers
        $sheet->getStyle('B9:T13')->applyFromArray([
            'font' => ['bold' => true, 'size' => 9],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'D9E1F2']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        // Data rows
        $row = 14;

        $budgetItems = BudgetItem::with([
            'subActivity.activity.program',
            'monthlyPlans' => fn($q) => $q->where('year', $this->year)->where('month', $month)->with('realization'),
        ])
            ->when($this->programId, fn($q) => $q->whereHas('subActivity.activity', fn($q2) => $q2->where('program_id', $this->programId)))
            ->get();

        foreach ($budgetItems as $item) {
            $plan = $item->monthlyPlans->first();
            $realization = $plan?->realization;

            $budget = (float) $item->total_budget;
            $plannedAmount = (float) ($plan?->planned_amount ?? 0);
            $realizedAmount = (float) ($realization?->realized_amount ?? 0);

            // Calculate physical progress
            $plannedVolume = (float) ($plan?->planned_volume ?? 0);
            $realizedVolume = (float) ($realization?->realized_volume ?? 0);
            $plannedPhysical = $item->volume > 0 ? ($plannedVolume / $item->volume) * 100 : 0;
            $realizedPhysical = $item->volume > 0 ? ($realizedVolume / $item->volume) * 100 : 0;
            $physicalDeviation = $realizedPhysical - $plannedPhysical;
            $plannedFinancial = $budget > 0 ? ($plannedAmount / $budget) * 100 : 0;
            $realizedFinancialPct = $budget > 0 ? ($realizedAmount / $budget) * 100 : 0;
            $balance = $budget - $realizedAmount;

            $sheet->setCellValue('B' . $row, $item->code);
            $sheet->setCellValue('C' . $row, $item->name);
            $sheet->setCellValue('F' . $row, $item->volume);
            $sheet->setCellValue('G' . $row, $item->unit);
            $sheet->setCellValue('H' . $row, '-');
            $sheet->setCellValue('I' . $row, 1);
            $sheet->setCellValue('K' . $row, $budget);
            $sheet->setCellValue('L' . $row, $realizedAmount);
            $sheet->setCellValue('M' . $row, $balance);
            $sheet->setCellValue('N' . $row, $plannedPhysical);
            $sheet->setCellValue('O' . $row, $realizedPhysical);
            $sheet->setCellValue('P' . $row, $physicalDeviation);
            $sheet->setCellValue('Q' . $row, $plannedFinancial);
            $sheet->setCellValue('R' . $row, $realizedAmount);
            $sheet->setCellValue('S' . $row, $realizedFinancialPct);
            $sheet->setCellValue('T' . $row, $balance);

            // Number formatting
            foreach (['K', 'L', 'M', 'R', 'T'] as $col) {
                $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode('#,##0');
            }
            foreach (['N', 'O', 'P', 'Q', 'S'] as $col) {
                $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode('0.00');
            }

            // Borders
            $sheet->getStyle('B' . $row . ':T' . $row)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);

            $row++;
        }

        // Totals row
        $totalBudget = $budgetItems->sum('total_budget');
        $totalRealized = $budgetItems->sum(fn($item) => $item->monthlyPlans->first()?->realization?->realized_amount ?? 0);
        $totalBalance = $totalBudget - $totalRealized;

        $sheet->setCellValue('B' . $row, 'TOTAL');
        $sheet->mergeCells('B' . $row . ':J' . $row);
        $sheet->setCellValue('K' . $row, $totalBudget);
        $sheet->setCellValue('L' . $row, $totalRealized);
        $sheet->setCellValue('M' . $row, $totalBalance);
        $sheet->setCellValue('T' . $row, $totalBalance);

        $sheet->getStyle('B' . $row . ':T' . $row)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'E2EFDA']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        foreach (['K', 'L', 'M', 'T'] as $col) {
            $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode('#,##0');
        }

        // Auto-size columns
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(35);
        foreach (['D', 'E'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(5);
        }
        foreach (['F', 'G', 'H', 'I', 'J'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(8);
        }
        foreach (['K', 'L', 'M', 'R', 'T'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(15);
        }
        foreach (['N', 'O', 'P', 'Q', 'S'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(8);
        }
    }

    public function getSpreadsheet(): Spreadsheet
    {
        return $this->generate();
    }
}
