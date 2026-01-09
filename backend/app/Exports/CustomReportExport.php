<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CustomReportExport implements FromArray, WithTitle, WithHeadings, WithStyles, ShouldAutoSize
{
    protected array $config;
    protected array $data;
    protected string $title;

    public function __construct(array $config, array $data, string $title)
    {
        $this->config = $config;
        $this->data = $data;
        $this->title = $title;
    }

    public function array(): array
    {
        $rows = [];

        if (isset($this->data['rows'])) {
            foreach ($this->data['rows'] as $row) {
                if (isset($row['items'])) {
                    // Grouped data
                    $rows[] = ['Group: ' . $row['group']];
                    foreach ($row['items'] as $item) {
                        $rows[] = $this->formatRow($item);
                    }
                    $rows[] = $this->formatTotals($row['totals'] ?? [], 'Subtotal');
                    $rows[] = []; // Empty row
                } else {
                    $rows[] = $this->formatRow($row);
                }
            }
        }

        // Add totals row
        if (isset($this->data['totals'])) {
            $rows[] = [];
            $rows[] = $this->formatTotals($this->data['totals'], 'TOTAL');
        }

        return $rows;
    }

    public function headings(): array
    {
        return array_map(function ($column) {
            return $this->getColumnLabel($column);
        }, $this->config['columns'] ?? []);
    }

    public function title(): string
    {
        return substr($this->title, 0, 31); // Excel sheet name max 31 chars
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0'],
                ],
            ],
        ];
    }

    protected function formatRow(array $row): array
    {
        $columns = $this->config['columns'] ?? [];
        $formatted = [];

        foreach ($columns as $column) {
            $value = $row[$column] ?? '';
            $formatted[] = $this->formatValue($column, $value);
        }

        return $formatted;
    }

    protected function formatTotals(array $totals, string $label): array
    {
        $columns = $this->config['columns'] ?? [];
        $formatted = [];
        $first = true;

        foreach ($columns as $column) {
            if ($first) {
                $formatted[] = $label;
                $first = false;
            } else {
                $value = $totals[$column] ?? '';
                $formatted[] = $this->formatValue($column, $value);
            }
        }

        return $formatted;
    }

    protected function formatValue(string $column, $value): mixed
    {
        $numericColumns = ['volume', 'unit_price', 'total_budget', 'planned_amount', 'realized_amount', 'deviation'];

        if (in_array($column, $numericColumns) && is_numeric($value)) {
            return (float) $value;
        }

        if (in_array($column, ['deviation_percentage', 'absorption_rate']) && is_numeric($value)) {
            return round((float) $value, 2) . '%';
        }

        return $value;
    }

    protected function getColumnLabel(string $column): string
    {
        $labels = [
            'code' => 'Kode',
            'name' => 'Nama Item',
            'unit' => 'Satuan',
            'volume' => 'Volume',
            'unit_price' => 'Harga Satuan',
            'total_budget' => 'Total Anggaran',
            'category' => 'Kategori',
            'sub_activity' => 'Sub Kegiatan',
            'activity' => 'Kegiatan',
            'program' => 'Program',
            'planned_amount' => 'Rencana',
            'realized_amount' => 'Realisasi',
            'deviation' => 'Deviasi',
            'deviation_percentage' => '% Deviasi',
            'absorption_rate' => 'Penyerapan',
        ];

        return $labels[$column] ?? $column;
    }
}
