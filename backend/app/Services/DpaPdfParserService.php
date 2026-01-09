<?php

namespace App\Services;

use Smalot\PdfParser\Parser;

class DpaPdfParserService
{
    protected Parser $parser;
    protected string $text;
    protected array $lines;

    public function __construct()
    {
        $this->parser = new Parser();
    }

    public function parse(string $filePath): array
    {
        $pdf = $this->parser->parseFile($filePath);
        $this->text = $pdf->getText();
        $this->lines = explode("\n", $this->text);

        return [
            'header' => $this->parseHeader(),
            'indicators' => $this->parseIndicators(),
            'sub_activity' => $this->parseSubActivity(),
            'budget_items' => $this->parseBudgetItems(),
            'monthly_plan' => $this->parseMonthlyPlan(),
        ];
    }

    protected function parseHeader(): array
    {
        $header = [
            'nomor_dpa' => null,
            'tahun_anggaran' => null,
            'urusan_pemerintahan' => ['code' => null, 'name' => null],
            'bidang_urusan' => ['code' => null, 'name' => null],
            'program' => ['code' => null, 'name' => null],
            'kegiatan' => ['code' => null, 'name' => null],
            'organisasi' => ['code' => null, 'name' => null],
            'unit' => ['code' => null, 'name' => null],
            'alokasi_tahun_minus_1' => 0,
            'alokasi_tahun' => 0,
            'alokasi_tahun_plus_1' => 0,
        ];

        foreach ($this->lines as $line) {
            $line = trim($line);

            // Nomor DPA
            if (preg_match('/Nomor DPA\s*:\s*(.+)/i', $line, $matches)) {
                $header['nomor_dpa'] = trim($matches[1]);
            }

            // Tahun Anggaran
            if (preg_match('/TAHUN ANGGARAN\s*(\d{4})/i', $line, $matches)) {
                $header['tahun_anggaran'] = (int)$matches[1];
            }

            // Urusan Pemerintahan
            if (preg_match('/Urusan Pemerintahan\s*:\s*(\d+)\s*-\s*(.+)/i', $line, $matches)) {
                $header['urusan_pemerintahan'] = [
                    'code' => trim($matches[1]),
                    'name' => trim($matches[2]),
                ];
            }

            // Bidang Urusan
            if (preg_match('/Bidang Urusan\s*:\s*([\d.]+)\s*-\s*(.+)/i', $line, $matches)) {
                $header['bidang_urusan'] = [
                    'code' => trim($matches[1]),
                    'name' => trim($matches[2]),
                ];
            }

            // Program
            if (preg_match('/^Program\s*:\s*([\d.]+)\s*-\s*(.+)/i', $line, $matches)) {
                $header['program'] = [
                    'code' => trim($matches[1]),
                    'name' => trim($matches[2]),
                ];
            }

            // Kegiatan
            if (preg_match('/^Kegiatan\s*:\s*([\d.]+)\s*-\s*(.+)/i', $line, $matches)) {
                $header['kegiatan'] = [
                    'code' => trim($matches[1]),
                    'name' => trim($matches[2]),
                ];
            }

            // Organisasi
            if (preg_match('/^Organisasi\s*:\s*([\d.]+)\s*-\s*(.+)/i', $line, $matches)) {
                $header['organisasi'] = [
                    'code' => trim($matches[1]),
                    'name' => trim($matches[2]),
                ];
            }

            // Unit
            if (preg_match('/^Unit\s*:\s*([\d.]+)\s*-\s*(.+)/i', $line, $matches)) {
                $header['unit'] = [
                    'code' => trim($matches[1]),
                    'name' => trim($matches[2]),
                ];
            }

            // Alokasi Tahun -1
            if (preg_match('/Alokasi Tahun\s*-\s*1\s*:\s*Rp([\d.,]+)/i', $line, $matches)) {
                $header['alokasi_tahun_minus_1'] = $this->parseAmount($matches[1]);
            }

            // Alokasi Tahun (current)
            if (preg_match('/Alokasi Tahun\s*:\s*Rp([\d.,]+)/i', $line, $matches)) {
                $header['alokasi_tahun'] = $this->parseAmount($matches[1]);
            }

            // Alokasi Tahun +1
            if (preg_match('/Alokasi Tahun\s*\+\s*1\s*:\s*Rp([\d.,]+)/i', $line, $matches)) {
                $header['alokasi_tahun_plus_1'] = $this->parseAmount($matches[1]);
            }
        }

        return $header;
    }

    protected function parseIndicators(): array
    {
        $indicators = [
            'capaian_kegiatan' => ['tolak_ukur' => null, 'target' => null],
            'masukan' => ['tolak_ukur' => null, 'target' => null],
            'keluaran' => ['tolak_ukur' => null, 'target' => null],
            'hasil' => ['tolak_ukur' => null, 'target' => null],
        ];

        $textBlock = implode(' ', $this->lines);

        // Capaian Kegiatan - improved pattern
        if (preg_match('/Capaian Kegiatan\s*([A-Za-z][^0-9]+?)\s+(\d+[\d.,]*\s*\w+)/i', $textBlock, $matches)) {
            $indicators['capaian_kegiatan'] = [
                'tolak_ukur' => trim($matches[1]),
                'target' => trim($matches[2]),
            ];
        }

        // Masukan
        if (preg_match('/Masukan\s+Dana Yang Dibutuhkan\s+Rp([\d.,]+)/i', $textBlock, $matches)) {
            $indicators['masukan'] = [
                'tolak_ukur' => 'Dana Yang Dibutuhkan',
                'target' => $this->parseAmount($matches[1]),
            ];
        }

        // Keluaran - improved pattern
        if (preg_match('/Keluaran\s+(.+?)\s+(\d+\s*\w+)\s*Hasil/is', $textBlock, $matches)) {
            $indicators['keluaran'] = [
                'tolak_ukur' => trim(preg_replace('/\s+/', ' ', $matches[1])),
                'target' => trim($matches[2]),
            ];
        }

        // Hasil - improved pattern
        if (preg_match('/Hasil\s+(.+?)\s+(\d+\s*\w+(?:\s*\w+)?)\s*Sub Kegiatan/is', $textBlock, $matches)) {
            $indicators['hasil'] = [
                'tolak_ukur' => trim(preg_replace('/\s+/', ' ', $matches[1])),
                'target' => trim($matches[2]),
            ];
        }

        return $indicators;
    }

    protected function parseSubActivity(): array
    {
        $subActivity = [
            'code' => null,
            'name' => null,
            'sumber_pendanaan' => null,
            'lokasi' => null,
            'keluaran' => null,
            'waktu_pelaksanaan' => null,
        ];

        $textBlock = implode("\n", $this->lines);

        // Sub Kegiatan
        if (preg_match('/Sub Kegiatan\s*:\s*([\d.]+)\s*-\s*(.+?)(?=\n|Sumber)/is', $textBlock, $matches)) {
            $subActivity['code'] = trim($matches[1]);
            $subActivity['name'] = trim(preg_replace('/\s+/', ' ', $matches[2]));
        }

        // Sumber Pendanaan
        if (preg_match('/Sumber Pendanaan\s*:\s*(.+?)(?=\n|Lokasi)/is', $textBlock, $matches)) {
            $subActivity['sumber_pendanaan'] = trim($matches[1]);
        }

        // Lokasi
        if (preg_match('/Lokasi\s*:\s*(.+?)(?=\n|Keluaran)/is', $textBlock, $matches)) {
            $subActivity['lokasi'] = trim($matches[1]);
        }

        // Keluaran Sub Kegiatan
        if (preg_match('/Keluaran Sub Kegiatan\s*:\s*(.+?)(?=\n|Waktu)/is', $textBlock, $matches)) {
            $subActivity['keluaran'] = trim(preg_replace('/\s+/', ' ', $matches[1]));
        }

        // Waktu Pelaksanaan
        if (preg_match('/Waktu Pelaksanaan\s*:\s*(.+?)(?=\n|Keterangan)/is', $textBlock, $matches)) {
            $subActivity['waktu_pelaksanaan'] = trim($matches[1]);
        }

        return $subActivity;
    }

    /**
     * Parse budget items using line-by-line approach to maintain correct hierarchy
     */
    protected function parseBudgetItems(): array
    {
        $items = [];
        $currentDetailCode = null;
        $currentDetailIndex = null;
        $seenCodes = [];

        foreach ($this->lines as $lineNum => $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Pattern 1: Account code with description and amount
            // Matches: "5.1.02.01.001.00024Belanja ATK Rp594.100,00" or "5.1.02 Belanja Rp1.000,00"
            if (preg_match('/^(5(?:\.\d+)*)[\s\t]*([A-Za-z].+?)\s+Rp([\d.,]+)/', $line, $matches)) {
                $code = trim($matches[1]);
                $description = trim($matches[2]);
                $amount = $this->parseAmount($matches[3]);

                // Skip duplicates
                $key = $code . '_' . $amount;
                if (isset($seenCodes[$key])) continue;
                $seenCodes[$key] = true;

                // Determine level
                $parts = explode('.', $code);
                $level = count($parts);
                $isDetailCode = $level === 6 && preg_match('/\.\d{5}$/', $code);

                $item = [
                    'code' => $code,
                    'description' => $description,
                    'amount' => $amount,
                    'level' => $level,
                    'is_detail_code' => $isDetailCode,
                    'group_name' => null,
                    'sumber_dana' => null,
                    'details' => [],
                ];

                $items[] = $item;

                // If this is a detail code (level 6), track it for subsequent spesifikasi
                if ($isDetailCode) {
                    $currentDetailCode = $code;
                    $currentDetailIndex = count($items) - 1;
                }
            }

            // Pattern 2: Group header [ # ] Name
            if ($currentDetailIndex !== null && preg_match('/\[\s*#\s*\]\s*(.+)/', $line, $matches)) {
                $items[$currentDetailIndex]['group_name'] = trim($matches[1]);
            }

            // Pattern 3: Sumber Dana line
            if ($currentDetailIndex !== null && preg_match('/Sumber Dana:\s*(.+)/i', $line, $matches)) {
                $items[$currentDetailIndex]['sumber_dana'] = trim($matches[1]);
            }

            // Pattern 4: Spesifikasi line - look for it and the next line with details
            if ($currentDetailIndex !== null && preg_match('/Spesifikasi:\s*(.+)/', $line, $matches)) {
                $specName = trim($matches[1]);

                // Look at next line(s) for volume, unit, price, amount
                $nextLine = isset($this->lines[$lineNum + 1]) ? trim($this->lines[$lineNum + 1]) : '';

                // Try to match: "20 Kotak Kotak Rp4.200,00 0% Rp84.000,00"
                // Or: "3 Unit UnitRp274.900,000% Rp824.700,00" (no space)
                if (preg_match('/^(\d+[\d.,]*)\s*(\w+)\s+\w+\s*Rp([\d.,]+),?\d*\s*\d*%?\s*Rp([\d.,]+)/', $nextLine, $detailMatches)) {
                    $items[$currentDetailIndex]['details'][] = [
                        'description' => $specName,
                        'volume' => $this->parseNumber($detailMatches[1]),
                        'unit' => trim($detailMatches[2]),
                        'unit_price' => $this->parseAmount($detailMatches[3]),
                        'amount' => $this->parseAmount($detailMatches[4]),
                    ];
                }
            }
        }

        // Sort by code to maintain hierarchy
        usort($items, function($a, $b) {
            return strcmp($a['code'], $b['code']);
        });

        return $items;
    }

    protected function parseMonthlyPlan(): array
    {
        $months = [
            'Januari' => 1, 'Februari' => 2, 'Maret' => 3, 'April' => 4,
            'Mei' => 5, 'Juni' => 6, 'Juli' => 7, 'Agustus' => 8,
            'September' => 9, 'Oktober' => 10, 'November' => 11, 'Desember' => 12,
        ];

        $plan = [];
        $textBlock = implode("\n", $this->lines);

        foreach ($months as $name => $number) {
            if (preg_match('/' . $name . '\s+Rp([\d.,]+)/i', $textBlock, $matches)) {
                $plan[$number] = $this->parseAmount($matches[1]);
            } else {
                $plan[$number] = 0;
            }
        }

        return $plan;
    }

    protected function parseAmount(string $value): float
    {
        // Remove thousands separator (.) and convert decimal separator (,) to (.)
        $cleaned = str_replace('.', '', $value);
        $cleaned = str_replace(',', '.', $cleaned);
        return (float) $cleaned;
    }

    protected function parseNumber(string $value): float
    {
        $cleaned = str_replace('.', '', $value);
        $cleaned = str_replace(',', '.', $cleaned);
        return (float) $cleaned;
    }
}
