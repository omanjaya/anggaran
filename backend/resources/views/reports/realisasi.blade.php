<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Realisasi Fisik dan Keuangan</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 8pt;
            line-height: 1.3;
            color: #333;
        }
        .page {
            page-break-after: always;
            padding: 5mm;
        }
        .page:last-child {
            page-break-after: avoid;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 10px;
        }
        .header h1 {
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .header h2 {
            font-size: 10pt;
            font-weight: bold;
            margin-bottom: 3px;
        }
        .header h3 {
            font-size: 9pt;
            font-weight: bold;
        }

        /* Info Section */
        .info-section {
            margin-bottom: 10px;
        }
        .info-row {
            display: flex;
            margin-bottom: 2px;
        }
        .info-label {
            width: 120px;
            font-weight: bold;
        }
        .info-separator {
            width: 15px;
            text-align: center;
        }
        .info-value {
            flex: 1;
        }
        .info-grid {
            display: table;
            width: 100%;
        }
        .info-grid-row {
            display: table-row;
        }
        .info-grid-cell {
            display: table-cell;
            padding: 2px 5px;
        }
        .info-grid-cell.left {
            width: 50%;
        }
        .info-grid-cell.right {
            width: 50%;
        }

        /* Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 7pt;
        }
        .data-table th,
        .data-table td {
            border: 1px solid #333;
            padding: 3px 4px;
            text-align: center;
            vertical-align: middle;
        }
        .data-table th {
            background-color: #d9e1f2;
            font-weight: bold;
            font-size: 7pt;
        }
        .data-table td {
            font-size: 7pt;
        }
        .data-table td.left {
            text-align: left;
        }
        .data-table td.right {
            text-align: right;
        }
        .data-table td.code {
            font-family: 'DejaVu Sans Mono', monospace;
            font-size: 6pt;
        }
        .data-table .total-row {
            background-color: #e2efda;
            font-weight: bold;
        }
        .data-table .total-row td {
            font-weight: bold;
        }

        /* Number formatting */
        .number {
            text-align: right !important;
            font-family: 'DejaVu Sans Mono', monospace;
        }
        .percentage {
            text-align: center;
        }
        .negative {
            color: #c00;
        }
        .positive {
            color: #080;
        }

        /* Footer */
        .footer {
            margin-top: 15px;
            font-size: 7pt;
        }
        .signature-section {
            float: right;
            width: 200px;
            text-align: center;
            margin-top: 10px;
        }
        .signature-section p {
            margin-bottom: 50px;
        }
        .signature-line {
            border-bottom: 1px solid #333;
            margin-bottom: 3px;
        }
        .generated-info {
            font-size: 6pt;
            color: #666;
            clear: both;
            padding-top: 60px;
        }

        /* Summary box */
        .summary-box {
            background-color: #f5f5f5;
            border: 1px solid #ddd;
            padding: 8px;
            margin-bottom: 10px;
            font-size: 8pt;
        }
        .summary-grid {
            display: table;
            width: 100%;
        }
        .summary-item {
            display: table-cell;
            width: 25%;
            text-align: center;
            padding: 5px;
        }
        .summary-item .label {
            font-size: 7pt;
            color: #666;
        }
        .summary-item .value {
            font-size: 10pt;
            font-weight: bold;
            color: #2c5aa0;
        }
    </style>
</head>
<body>
    @foreach($months as $month)
    <div class="page">
        <!-- Header -->
        <div class="header">
            <h1>LAPORAN REALISASI FISIK DAN KEUANGAN</h1>
            <h2>APBD PROVINSI BALI TAHUN {{ $year }}</h2>
            <h3>BULAN: {{ strtoupper($monthNames[$month]) }}</h3>
        </div>

        <!-- Info Section -->
        <div class="info-section">
            <div class="info-grid">
                <div class="info-grid-row">
                    <div class="info-grid-cell left">
                        <strong>SKPD</strong> : {{ $skpd?->name ?? '-' }}
                    </div>
                    <div class="info-grid-cell right">
                        <strong>PROGRAM</strong> : {{ $program?->name ?? '-' }}
                    </div>
                </div>
                <div class="info-grid-row">
                    <div class="info-grid-cell left">
                        <strong>SUB UNIT KERJA</strong> : {{ $skpd?->short_name ?? '-' }}
                    </div>
                    <div class="info-grid-cell right">
                        <strong>KEGIATAN</strong> : {{ $activity?->name ?? '-' }}
                    </div>
                </div>
                <div class="info-grid-row">
                    <div class="info-grid-cell left">
                        <strong>SUB BAGIAN</strong> : -
                    </div>
                    <div class="info-grid-cell right">
                        <strong>PPTK</strong> : -
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Box -->
        @php
            $monthData = $data[$month] ?? collect();
            $totalBudget = $monthData->sum('budget');
            $totalRealized = $monthData->sum('realized');
            $totalBalance = $totalBudget - $totalRealized;
            $realizationPct = $totalBudget > 0 ? ($totalRealized / $totalBudget) * 100 : 0;
        @endphp
        <div class="summary-box">
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="label">Total Anggaran</div>
                    <div class="value">Rp {{ number_format($totalBudget, 0, ',', '.') }}</div>
                </div>
                <div class="summary-item">
                    <div class="label">Total Realisasi</div>
                    <div class="value" style="color: #28a745;">Rp {{ number_format($totalRealized, 0, ',', '.') }}</div>
                </div>
                <div class="summary-item">
                    <div class="label">Sisa Anggaran</div>
                    <div class="value" style="color: #dc3545;">Rp {{ number_format($totalBalance, 0, ',', '.') }}</div>
                </div>
                <div class="summary-item">
                    <div class="label">Persentase Realisasi</div>
                    <div class="value">{{ number_format($realizationPct, 1) }}%</div>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <table class="data-table">
            <thead>
                <tr>
                    <th rowspan="4" style="width: 80px;">KODE REKENING</th>
                    <th rowspan="4" style="width: 180px;">URAIAN BELANJA</th>
                    <th colspan="5">RENCANA KEGIATAN</th>
                    <th colspan="8">PELAKSANAAN KEGIATAN</th>
                    <th rowspan="4" style="width: 70px;">SALDO</th>
                </tr>
                <tr>
                    <th rowspan="3">VOL</th>
                    <th rowspan="3">SAT</th>
                    <th rowspan="3">LOKASI</th>
                    <th rowspan="3">JML<br>PAKET</th>
                    <th rowspan="3">ANGGARAN</th>
                    <th rowspan="3">KONTRAK/<br>SWAKELOLA</th>
                    <th rowspan="3">SISA<br>ANGGARAN</th>
                    <th colspan="6">PROGRES</th>
                </tr>
                <tr>
                    <th colspan="3">FISIK</th>
                    <th colspan="3">KEUANGAN</th>
                </tr>
                <tr>
                    <th>RENC</th>
                    <th>REAL</th>
                    <th>DEV</th>
                    <th>RENC</th>
                    <th>REAL Rp.</th>
                    <th>%</th>
                </tr>
                <tr>
                    <th>a</th>
                    <th>b</th>
                    <th>c</th>
                    <th>d</th>
                    <th>e</th>
                    <th>f</th>
                    <th>g</th>
                    <th>h</th>
                    <th>i</th>
                    <th>j</th>
                    <th>k</th>
                    <th>l</th>
                    <th>m</th>
                    <th>n</th>
                    <th>o</th>
                    <th>p</th>
                </tr>
            </thead>
            <tbody>
                @forelse($monthData as $item)
                @php
                    $physicalDev = ($item['physical_real'] ?? 0) - ($item['physical_plan'] ?? 0);
                    $financialPct = $item['budget'] > 0 ? (($item['realized'] ?? 0) / $item['budget']) * 100 : 0;
                    $balance = $item['budget'] - ($item['realized'] ?? 0);
                @endphp
                <tr>
                    <td class="code">{{ $item['code'] }}</td>
                    <td class="left">{{ $item['name'] }}</td>
                    <td>{{ $item['volume'] ?? '-' }}</td>
                    <td>{{ $item['unit'] ?? '-' }}</td>
                    <td>{{ $item['location'] ?? '-' }}</td>
                    <td>1</td>
                    <td class="number">{{ number_format($item['budget'], 0, ',', '.') }}</td>
                    <td class="number">{{ number_format($item['realized'] ?? 0, 0, ',', '.') }}</td>
                    <td class="number">{{ number_format($balance, 0, ',', '.') }}</td>
                    <td class="percentage">{{ number_format($item['physical_plan'] ?? 0, 1) }}</td>
                    <td class="percentage">{{ number_format($item['physical_real'] ?? 0, 1) }}</td>
                    <td class="percentage {{ $physicalDev < 0 ? 'negative' : 'positive' }}">{{ number_format($physicalDev, 1) }}</td>
                    <td class="percentage">{{ number_format($item['financial_plan'] ?? 0, 1) }}</td>
                    <td class="number">{{ number_format($item['realized'] ?? 0, 0, ',', '.') }}</td>
                    <td class="percentage">{{ number_format($financialPct, 1) }}</td>
                    <td class="number">{{ number_format($balance, 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="16" style="text-align: center; padding: 20px;">Tidak ada data untuk bulan ini</td>
                </tr>
                @endforelse
            </tbody>
            @if($monthData->count() > 0)
            <tfoot>
                <tr class="total-row">
                    <td colspan="6" style="text-align: center;">TOTAL</td>
                    <td class="number">{{ number_format($totalBudget, 0, ',', '.') }}</td>
                    <td class="number">{{ number_format($totalRealized, 0, ',', '.') }}</td>
                    <td class="number">{{ number_format($totalBalance, 0, ',', '.') }}</td>
                    <td colspan="3"></td>
                    <td></td>
                    <td class="number">{{ number_format($totalRealized, 0, ',', '.') }}</td>
                    <td class="percentage">{{ number_format($realizationPct, 1) }}</td>
                    <td class="number">{{ number_format($totalBalance, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
            @endif
        </table>

        <!-- Footer -->
        <div class="footer">
            <div class="signature-section">
                <p>{{ $signatureLocation ?? 'Denpasar' }}, {{ now()->translatedFormat('d F Y') }}</p>
                <p>{{ $signatureTitle ?? 'Mengetahui' }},<br>{{ $signaturePosition ?? 'Kepala SKPD' }}</p>
                <div class="signature-line"></div>
                <p>{{ $signatureName ?? '.................................' }}<br>
                   <small>NIP. {{ $signatureNip ?? '.................................' }}</small>
                </p>
            </div>
            <div class="generated-info">
                Dokumen ini digenerate oleh SIPERA pada {{ now()->format('d/m/Y H:i:s') }}
            </div>
        </div>
    </div>
    @endforeach
</body>
</html>
