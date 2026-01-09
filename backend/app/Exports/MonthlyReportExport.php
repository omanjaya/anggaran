<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MonthlyReportExport implements FromArray, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        private array $report
    ) {}

    public function array(): array
    {
        return collect($this->report['data'])->map(function ($item, $index) {
            return [
                $index + 1,
                $item['code'],
                $item['program'],
                $item['activity'],
                $item['sub_activity'],
                $item['budget_item'],
                $item['unit'],
                number_format($item['planned_volume'], 2, ',', '.'),
                number_format($item['planned_amount'], 0, ',', '.'),
                number_format($item['realized_volume'], 2, ',', '.'),
                number_format($item['realized_amount'], 0, ',', '.'),
                number_format($item['deviation_amount'], 0, ',', '.'),
                number_format($item['deviation_percentage'], 2, ',', '.') . '%',
                $item['status'],
            ];
        })->toArray();
    }

    public function headings(): array
    {
        return [
            'No',
            'Kode',
            'Program',
            'Kegiatan',
            'Sub Kegiatan',
            'Item Anggaran',
            'Satuan',
            'Volume Rencana',
            'Jumlah Rencana (Rp)',
            'Volume Realisasi',
            'Jumlah Realisasi (Rp)',
            'Deviasi (Rp)',
            'Deviasi (%)',
            'Status',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return $this->report['period'];
    }
}
