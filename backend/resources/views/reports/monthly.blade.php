<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
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
            font-size: 9px;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #333;
            padding: 4px 6px;
            text-align: left;
        }
        th {
            background-color: #2563eb;
            color: white;
            font-size: 9px;
        }
        td {
            font-size: 8px;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .summary {
            margin-top: 20px;
            padding: 10px;
            background-color: #f3f4f6;
        }
        .summary table {
            width: auto;
        }
        .summary td {
            border: none;
            padding: 2px 10px;
        }
        .footer {
            margin-top: 30px;
            text-align: right;
            font-size: 9px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>DINAS KOMUNIKASI DAN INFORMATIKA PROVINSI BALI</h1>
        <h2>{{ $report['title'] }}</h2>
        <p>Dicetak: {{ $report['generated_at'] }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 3%">No</th>
                <th style="width: 6%">Kode</th>
                <th style="width: 15%">Program</th>
                <th style="width: 12%">Kegiatan</th>
                <th style="width: 12%">Sub Kegiatan</th>
                <th style="width: 12%">Item Anggaran</th>
                <th style="width: 5%">Satuan</th>
                <th style="width: 7%">Rencana (Rp)</th>
                <th style="width: 7%">Realisasi (Rp)</th>
                <th style="width: 7%">Deviasi (Rp)</th>
                <th style="width: 5%">%</th>
                <th style="width: 9%">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($report['data'] as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $item['code'] }}</td>
                <td>{{ $item['program'] }}</td>
                <td>{{ $item['activity'] }}</td>
                <td>{{ $item['sub_activity'] }}</td>
                <td>{{ $item['budget_item'] }}</td>
                <td class="text-center">{{ $item['unit'] }}</td>
                <td class="text-right">{{ number_format($item['planned_amount'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($item['realized_amount'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($item['deviation_amount'], 0, ',', '.') }}</td>
                <td class="text-center">{{ number_format($item['deviation_percentage'], 1) }}%</td>
                <td>{{ $item['status'] }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="12" class="text-center">Tidak ada data</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary">
        <strong>Ringkasan:</strong>
        <table>
            <tr>
                <td>Total Item:</td>
                <td>{{ $report['summary']['total_items'] }}</td>
            </tr>
            <tr>
                <td>Total Rencana:</td>
                <td>Rp {{ number_format($report['summary']['total_planned'], 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Total Realisasi:</td>
                <td>Rp {{ number_format($report['summary']['total_realized'], 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Persentase:</td>
                <td>{{ $report['summary']['percentage'] }}%</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>Denpasar, {{ now()->format('d F Y') }}</p>
        <p style="margin-top: 50px;">( _________________________ )</p>
        <p>Kepala Dinas</p>
    </div>
</body>
</html>
