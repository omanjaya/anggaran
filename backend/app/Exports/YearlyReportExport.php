<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class YearlyReportExport implements WithMultipleSheets
{
    public function __construct(
        private array $report
    ) {}

    public function sheets(): array
    {
        $sheets = [];

        // Summary sheet
        $sheets[] = new YearlySummarySheet($this->report);

        // Monthly sheets
        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
            4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September',
            10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        foreach ($this->report['data'] as $month => $data) {
            if (!empty($data)) {
                $sheets[] = new MonthlySheet($data, $monthNames[$month]);
            }
        }

        return $sheets;
    }
}

class YearlySummarySheet implements FromArray, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private array $report) {}

    public function array(): array
    {
        $summary = $this->report['summary'];

        return [
            ['Total Anggaran', number_format($summary['total_budget'], 0, ',', '.')],
            ['Total Rencana', number_format($summary['total_planned'], 0, ',', '.')],
            ['Total Realisasi', number_format($summary['total_realized'], 0, ',', '.')],
            ['Deviasi', number_format($summary['total_deviation'], 0, ',', '.')],
            ['Persentase vs Anggaran', $summary['budget_percentage'] . '%'],
            ['Persentase vs Rencana', $summary['plan_percentage'] . '%'],
        ];
    }

    public function headings(): array
    {
        return ['Keterangan', 'Nilai'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function title(): string
    {
        return 'Ringkasan';
    }
}

class MonthlySheet implements FromArray, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        private array $data,
        private string $monthName
    ) {}

    public function array(): array
    {
        return collect($this->data)->map(function ($item, $index) {
            return [
                $index + 1,
                $item['code'],
                $item['budget_item'],
                number_format($item['planned_amount'], 0, ',', '.'),
                number_format($item['realized_amount'], 0, ',', '.'),
                number_format($item['deviation_amount'], 0, ',', '.'),
                $item['status'],
            ];
        })->toArray();
    }

    public function headings(): array
    {
        return ['No', 'Kode', 'Item Anggaran', 'Rencana (Rp)', 'Realisasi (Rp)', 'Deviasi (Rp)', 'Status'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function title(): string
    {
        return $this->monthName;
    }
}
