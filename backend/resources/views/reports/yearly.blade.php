<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
            margin: 15px;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
        }
        .header h1 {
            font-size: 14px;
            margin: 0;
        }
        .header h2 {
            font-size: 12px;
            margin: 5px 0;
            font-weight: normal;
        }
        .header p {
            font-size: 8px;
            color: #666;
        }
        .summary-box {
            background-color: #f3f4f6;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .summary-box h3 {
            margin: 0 0 10px 0;
            font-size: 11px;
        }
        .summary-grid {
            display: table;
            width: 100%;
        }
        .summary-item {
            display: table-cell;
            width: 16.66%;
            text-align: center;
            padding: 5px;
        }
        .summary-item .value {
            font-size: 12px;
            font-weight: bold;
            color: #2563eb;
        }
        .summary-item .label {
            font-size: 8px;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            page-break-inside: auto;
        }
        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        th, td {
            border: 1px solid #333;
            padding: 3px 4px;
            text-align: left;
        }
        th {
            background-color: #2563eb;
            color: white;
            font-size: 8px;
        }
        td {
            font-size: 7px;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .month-section {
            margin-top: 15px;
            page-break-before: auto;
        }
        .month-title {
            background-color: #1e40af;
            color: white;
            padding: 5px 10px;
            font-size: 10px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .month-empty {
            padding: 10px;
            text-align: center;
            color: #666;
            font-style: italic;
        }
        .footer {
            margin-top: 30px;
            text-align: right;
            font-size: 9px;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>DINAS KOMUNIKASI DAN INFORMATIKA PROVINSI BALI</h1>
        <h2>{{ $report['title'] }}</h2>
        <p>Dicetak: {{ $report['generated_at'] }}</p>
    </div>

    <div class="summary-box">
        <h3>Ringkasan Tahunan</h3>
        <table style="width: auto; border: none;">
            <tr>
                <td style="border: none; padding: 2px 15px 2px 0;">Total Anggaran:</td>
                <td style="border: none; padding: 2px 30px 2px 0; font-weight: bold;">Rp {{ number_format($report['summary']['total_budget'], 0, ',', '.') }}</td>
                <td style="border: none; padding: 2px 15px 2px 0;">Total Rencana:</td>
                <td style="border: none; padding: 2px 30px 2px 0; font-weight: bold;">Rp {{ number_format($report['summary']['total_planned'], 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td style="border: none; padding: 2px 15px 2px 0;">Total Realisasi:</td>
                <td style="border: none; padding: 2px 30px 2px 0; font-weight: bold;">Rp {{ number_format($report['summary']['total_realized'], 0, ',', '.') }}</td>
                <td style="border: none; padding: 2px 15px 2px 0;">Deviasi:</td>
                <td style="border: none; padding: 2px 30px 2px 0; font-weight: bold;">Rp {{ number_format($report['summary']['total_deviation'], 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td style="border: none; padding: 2px 15px 2px 0;">% vs Anggaran:</td>
                <td style="border: none; padding: 2px 30px 2px 0; font-weight: bold;">{{ $report['summary']['budget_percentage'] }}%</td>
                <td style="border: none; padding: 2px 15px 2px 0;">% vs Rencana:</td>
                <td style="border: none; padding: 2px 30px 2px 0; font-weight: bold;">{{ $report['summary']['plan_percentage'] }}%</td>
            </tr>
        </table>
    </div>

    @php
        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
            4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September',
            10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
    @endphp

    @foreach($report['data'] as $month => $items)
        <div class="month-section">
            <div class="month-title">{{ $monthNames[$month] }}</div>

            @if(count($items) > 0)
                <table>
                    <thead>
                        <tr>
                            <th style="width: 3%">No</th>
                            <th style="width: 7%">Kode</th>
                            <th style="width: 15%">Program</th>
                            <th style="width: 13%">Kegiatan</th>
                            <th style="width: 13%">Sub Kegiatan</th>
                            <th style="width: 13%">Item Anggaran</th>
                            <th style="width: 8%">Rencana (Rp)</th>
                            <th style="width: 8%">Realisasi (Rp)</th>
                            <th style="width: 8%">Deviasi (Rp)</th>
                            <th style="width: 5%">%</th>
                            <th style="width: 7%">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $index => $item)
                        <tr>
                            <td class="text-center">{{ $index + 1 }}</td>
                            <td>{{ $item['code'] }}</td>
                            <td>{{ $item['program'] }}</td>
                            <td>{{ $item['activity'] }}</td>
                            <td>{{ $item['sub_activity'] }}</td>
                            <td>{{ $item['budget_item'] }}</td>
                            <td class="text-right">{{ number_format($item['planned_amount'], 0, ',', '.') }}</td>
                            <td class="text-right">{{ number_format($item['realized_amount'], 0, ',', '.') }}</td>
                            <td class="text-right">{{ number_format($item['deviation_amount'], 0, ',', '.') }}</td>
                            <td class="text-center">{{ number_format($item['deviation_percentage'], 1) }}%</td>
                            <td>{{ $item['status'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="month-empty">Tidak ada data realisasi</div>
            @endif
        </div>
    @endforeach

    <div class="footer">
        <p>Denpasar, {{ now()->format('d F Y') }}</p>
        <p style="margin-top: 50px;">( _________________________ )</p>
        <p>Kepala Dinas</p>
    </div>
</body>
</html>
