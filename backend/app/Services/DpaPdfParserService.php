<?php

namespace App\Services;

use Smalot\PdfParser\Parser;
use Illuminate\Support\Str;

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
            if (preg_match('/Alokasi Tahun\s*-1\s*:\s*Rp([\d.,]+)/i', $line, $matches)) {
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

        // Capaian Kegiatan
        if (preg_match('/Capaian Kegiatan\s+(.+?)\s+(\d+[\d.,]*\s*\w+)/i', $textBlock, $matches)) {
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

    protected function parseBudgetItems(): array
    {
        $items = [];
        $textBlock = implode("\n", $this->lines);
        $seenCodes = [];

        // Pattern 1: ALL account code levels (5, 5.1, 5.1.02, 5.1.02.01, etc.)
        // Matches lines like: "5.1.02.01 Belanja Barang Rp3.111.200,00"
        $allCodesPattern = '/^(5(?:\.\d+)*)\s+([A-Za-z].+?)\s+Rp([\d.,]+)/m';

        if (preg_match_all($allCodesPattern, $textBlock, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $code = trim($match[1]);
                $description = trim($match[2]);
                $amount = $this->parseAmount($match[3]);

                // Skip if we've already seen this exact code+amount combo
                $key = $code . '_' . $amount;
                if (isset($seenCodes[$key])) {
                    continue;
                }
                $seenCodes[$key] = true;

                // Determine level based on code structure
                $parts = explode('.', $code);
                $level = count($parts);
                $isDetailCode = $level >= 5 && preg_match('/\.\d{5}$/', $code);

                $items[] = [
                    'code' => $code,
                    'description' => $description,
                    'amount' => $amount,
                    'level' => $level,
                    'is_detail_code' => $isDetailCode,
                    'details' => [],
                ];
            }
        }

        // Pattern 2: Detail codes that are merged with description (no space)
        // Matches: "5.1.02.01.001.00024Belanja ATK Rp594.100,00"
        $mergedPattern = '/(5\.\d+\.\d+\.\d+\.\d+\.\d+)([A-Za-z].+?)\s+Rp([\d.,]+)/';

        if (preg_match_all($mergedPattern, $textBlock, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $code = trim($match[1]);
                $description = trim($match[2]);
                $amount = $this->parseAmount($match[3]);

                $key = $code . '_' . $amount;
                if (isset($seenCodes[$key])) {
                    continue;
                }
                $seenCodes[$key] = true;

                $parts = explode('.', $code);

                $items[] = [
                    'code' => $code,
                    'description' => $description,
                    'amount' => $amount,
                    'level' => count($parts),
                    'is_detail_code' => true,
                    'details' => [],
                ];
            }
        }

        // Pattern 3: Spesifikasi items (detail line items with volume, unit, price)
        // Handles both formats: with space "Rp4.200,00 0%" and without "Rp141.600,000%"
        $specPattern = '/Spesifikasi:\s*([^\n]+)\n\s*(\d+[\d.,]*)\s+(\w+)\s+\w+\s*Rp([\d.,]+),?\d*\s*\d+%\s*Rp([\d.,]+)/';

        if (preg_match_all($specPattern, $textBlock, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $items[] = [
                    'code' => null,
                    'description' => trim($match[1]),
                    'volume' => $this->parseNumber($match[2]),
                    'unit' => trim($match[3]),
                    'unit_price' => $this->parseAmount($match[4]),
                    'amount' => $this->parseAmount($match[5]),
                    'is_detail' => true,
                ];
            }
        }

        // Sort items by code to maintain hierarchy
        usort($items, function($a, $b) {
            if (empty($a['code'])) return 1;
            if (empty($b['code'])) return -1;
            return strcmp($a['code'], $b['code']);
        });

        return $this->consolidateBudgetItems($items);
    }

    protected function consolidateBudgetItems(array $items): array
    {
        // First pass: separate codes and details
        $codes = [];
        $details = [];

        foreach ($items as $item) {
            if (!empty($item['code'])) {
                $item['details'] = [];
                $codes[$item['code']] = $item;
            } else {
                $details[] = $item;
            }
        }

        // Sort codes by code string to maintain hierarchy
        ksort($codes);

        // Second pass: try to associate details with their parent codes
        // Details are associated with the last detail-level code (ending in .00xxx) based on position in PDF
        $this->associateDetailsWithParents($codes, $details);

        return array_values($codes);
    }

    protected function associateDetailsWithParents(array &$codes, array $details): void
    {
        // Get detail-level codes (ending in .00xxx) sorted
        $detailCodes = [];
        foreach ($codes as $code => $item) {
            if (preg_match('/\.\d{5}$/', $code)) {
                $detailCodes[] = $code;
            }
        }

        if (empty($detailCodes)) {
            // If no detail codes, assign all to the last code
            $lastCode = array_key_last($codes);
            if ($lastCode) {
                $codes[$lastCode]['details'] = $details;
            }
            return;
        }

        // Group details by matching keywords with account code descriptions
        foreach ($details as $detail) {
            $detailDesc = strtolower($detail['description']);
            $assigned = false;

            foreach ($detailCodes as $code) {
                $codeDesc = strtolower($codes[$code]['description']);

                // Match by keywords
                if (
                    (str_contains($detailDesc, 'stapl') && str_contains($codeDesc, 'tulis')) ||
                    (str_contains($detailDesc, 'penjepit') && str_contains($codeDesc, 'tulis')) ||
                    (str_contains($detailDesc, 'pulpen') && str_contains($codeDesc, 'tulis')) ||
                    (str_contains($detailDesc, 'kertas') && str_contains($codeDesc, 'kertas')) ||
                    (str_contains($detailDesc, 'foto') && str_contains($codeDesc, 'cetak')) ||
                    (str_contains($detailDesc, 'copy') && str_contains($codeDesc, 'cetak')) ||
                    (str_contains($detailDesc, 'tinta') && str_contains($codeDesc, 'komputer')) ||
                    (str_contains($detailDesc, 'ssl') && str_contains($codeDesc, 'software')) ||
                    (str_contains($detailDesc, 'langganan') && str_contains($codeDesc, 'software')) ||
                    (str_contains($detailDesc, 'lisensi') && str_contains($codeDesc, 'software'))
                ) {
                    $codes[$code]['details'][] = $detail;
                    $assigned = true;
                    break;
                }
            }

            // If not matched, assign to last detail code
            if (!$assigned && !empty($detailCodes)) {
                $lastDetailCode = end($detailCodes);
                $codes[$lastDetailCode]['details'][] = $detail;
            }
        }
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
