<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Laporan SIPERA' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
        }
        .container {
            padding: 20px;
        }

        /* Header Styles */
        .header {
            text-align: center;
            border-bottom: 3px double #333;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .header h2 {
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 3px;
        }
        .header p {
            font-size: 9pt;
            color: #666;
        }

        /* Report Info */
        .report-info {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f5f5f5;
            border-radius: 5px;
        }
        .report-info table {
            width: 100%;
            font-size: 9pt;
        }
        .report-info td {
            padding: 2px 5px;
        }
        .report-info td:first-child {
            width: 120px;
            font-weight: bold;
        }

        /* Data Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .data-table th,
        .data-table td {
            border: 1px solid #333;
            padding: 5px 8px;
            text-align: left;
            vertical-align: top;
        }
        .data-table th {
            background-color: #2c5aa0;
            color: white;
            font-weight: bold;
            text-align: center;
            font-size: 9pt;
        }
        .data-table td {
            font-size: 9pt;
        }
        .data-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .data-table tbody tr:hover {
            background-color: #e9f0f7;
        }

        /* Number formatting */
        .number {
            text-align: right;
            font-family: 'DejaVu Sans Mono', monospace;
        }
        .center {
            text-align: center;
        }

        /* Summary section */
        .summary {
            margin-top: 20px;
            padding: 15px;
            background-color: #e8f4f8;
            border-radius: 5px;
        }
        .summary h3 {
            font-size: 11pt;
            margin-bottom: 10px;
            color: #2c5aa0;
        }
        .summary-table {
            width: 50%;
            margin-left: auto;
        }
        .summary-table td {
            padding: 3px 10px;
        }
        .summary-table td:last-child {
            text-align: right;
            font-weight: bold;
        }

        /* Footer */
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
        }
        .signature-area {
            float: right;
            width: 250px;
            text-align: center;
        }
        .signature-area p {
            margin-bottom: 60px;
        }
        .signature-line {
            border-bottom: 1px solid #333;
            margin-bottom: 5px;
        }
        .generated-date {
            font-size: 8pt;
            color: #666;
            margin-top: 20px;
            clear: both;
        }

        /* Status badges */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: bold;
        }
        .badge-success {
            background-color: #28a745;
            color: white;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #333;
        }
        .badge-danger {
            background-color: #dc3545;
            color: white;
        }
        .badge-info {
            background-color: #17a2b8;
            color: white;
        }

        /* Progress bar */
        .progress-bar {
            width: 100%;
            height: 12px;
            background-color: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background-color: #28a745;
        }
        .progress-text {
            font-size: 8pt;
            text-align: center;
            margin-top: 2px;
        }

        /* Page break */
        .page-break {
            page-break-after: always;
        }

        /* Chart placeholder */
        .chart-placeholder {
            width: 100%;
            height: 200px;
            border: 1px dashed #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>PEMERINTAH PROVINSI BALI</h1>
            <h2>{{ $title ?? 'LAPORAN ANGGARAN' }}</h2>
            <p>{{ $subtitle ?? 'Sistem Informasi Perencanaan dan Realisasi Anggaran (SIPERA)' }}</p>
        </div>

        <!-- Report Info -->
        @if(isset($reportInfo))
        <div class="report-info">
            <table>
                <tr>
                    <td>Periode</td>
                    <td>: {{ $reportInfo['period'] ?? '-' }}</td>
                    <td>Tahun Anggaran</td>
                    <td>: {{ $reportInfo['year'] ?? date('Y') }}</td>
                </tr>
                <tr>
                    <td>SKPD</td>
                    <td>: {{ $reportInfo['skpd'] ?? 'Semua SKPD' }}</td>
                    <td>Tanggal Cetak</td>
                    <td>: {{ $reportInfo['printDate'] ?? now()->format('d/m/Y H:i') }}</td>
                </tr>
            </table>
        </div>
        @endif

        <!-- Main Data Table -->
        @if(isset($data) && count($data) > 0)
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 40px;">No</th>
                    @foreach($columns ?? [] as $column)
                    <th style="{{ $column['style'] ?? '' }}">{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($data as $index => $row)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    @foreach($columns ?? [] as $column)
                    <td class="{{ $column['class'] ?? '' }}">
                        @if($column['type'] ?? 'text' === 'currency')
                            Rp {{ number_format($row[$column['key']] ?? 0, 0, ',', '.') }}
                        @elseif($column['type'] ?? 'text' === 'percentage')
                            {{ number_format($row[$column['key']] ?? 0, 2) }}%
                        @elseif($column['type'] ?? 'text' === 'status')
                            <span class="badge badge-{{ $row[$column['key'] . '_color'] ?? 'info' }}">
                                {{ $row[$column['key']] ?? '-' }}
                            </span>
                        @elseif($column['type'] ?? 'text' === 'progress')
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: {{ min($row[$column['key']] ?? 0, 100) }}%"></div>
                            </div>
                            <div class="progress-text">{{ number_format($row[$column['key']] ?? 0, 1) }}%</div>
                        @else
                            {{ $row[$column['key']] ?? '-' }}
                        @endif
                    </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
            @if(isset($totals))
            <tfoot>
                <tr style="background-color: #2c5aa0; color: white; font-weight: bold;">
                    <td colspan="{{ isset($columns) ? 2 : 1 }}" class="center">TOTAL</td>
                    @foreach($totals as $total)
                    <td class="{{ $total['class'] ?? 'number' }}">
                        @if($total['type'] ?? 'text' === 'currency')
                            Rp {{ number_format($total['value'] ?? 0, 0, ',', '.') }}
                        @elseif($total['type'] ?? 'text' === 'percentage')
                            {{ number_format($total['value'] ?? 0, 2) }}%
                        @else
                            {{ $total['value'] ?? '-' }}
                        @endif
                    </td>
                    @endforeach
                </tr>
            </tfoot>
            @endif
        </table>
        @else
        <p style="text-align: center; color: #666; padding: 20px;">Tidak ada data untuk ditampilkan.</p>
        @endif

        <!-- Summary Section -->
        @if(isset($summary))
        <div class="summary">
            <h3>Ringkasan</h3>
            <table class="summary-table">
                @foreach($summary as $item)
                <tr>
                    <td>{{ $item['label'] }}</td>
                    <td>
                        @if($item['type'] ?? 'text' === 'currency')
                            Rp {{ number_format($item['value'] ?? 0, 0, ',', '.') }}
                        @elseif($item['type'] ?? 'text' === 'percentage')
                            {{ number_format($item['value'] ?? 0, 2) }}%
                        @else
                            {{ $item['value'] ?? '-' }}
                        @endif
                    </td>
                </tr>
                @endforeach
            </table>
        </div>
        @endif

        <!-- Notes -->
        @if(isset($notes))
        <div style="margin-top: 20px;">
            <strong>Catatan:</strong>
            <ul style="margin-left: 20px; margin-top: 5px; font-size: 9pt;">
                @foreach($notes as $note)
                <li>{{ $note }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <!-- Footer with Signature -->
        <div class="footer">
            <div class="signature-area">
                <p>{{ $signatureLocation ?? 'Denpasar' }}, {{ now()->format('d F Y') }}</p>
                <p>{{ $signatureTitle ?? 'Mengetahui,' }}<br>{{ $signaturePosition ?? 'Kepala SKPD' }}</p>
                <div class="signature-line"></div>
                <p>{{ $signatureName ?? '.................................' }}<br>
                   <small>NIP. {{ $signatureNip ?? '.................................' }}</small>
                </p>
            </div>
            <div style="clear: both;"></div>
            <p class="generated-date">
                Dokumen ini digenerate otomatis oleh SIPERA pada {{ now()->format('d/m/Y H:i:s') }}
            </p>
        </div>
    </div>
</body>
</html>
