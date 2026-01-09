<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ringkasan Anggaran</title>
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
            padding: 20px;
        }

        .header {
            text-align: center;
            border-bottom: 3px double #333;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .header h1 {
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .header h2 {
            font-size: 13pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .header p {
            font-size: 10pt;
            color: #666;
        }

        .stats-grid {
            display: table;
            width: 100%;
            margin-bottom: 25px;
        }
        .stat-row {
            display: table-row;
        }
        .stat-box {
            display: table-cell;
            width: 25%;
            padding: 10px;
            text-align: center;
            vertical-align: top;
        }
        .stat-box-inner {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
        }
        .stat-value {
            font-size: 18pt;
            font-weight: bold;
            color: #2c5aa0;
        }
        .stat-label {
            font-size: 9pt;
            color: #666;
            margin-top: 5px;
        }
        .stat-box.green .stat-value { color: #28a745; }
        .stat-box.red .stat-value { color: #dc3545; }
        .stat-box.yellow .stat-value { color: #ffc107; }
        .stat-box.blue .stat-value { color: #2c5aa0; }

        .section {
            margin-bottom: 25px;
        }
        .section-title {
            font-size: 12pt;
            font-weight: bold;
            color: #2c5aa0;
            border-bottom: 2px solid #2c5aa0;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }

        .progress-section {
            margin-bottom: 20px;
        }
        .progress-item {
            margin-bottom: 15px;
        }
        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        .progress-name {
            font-weight: bold;
        }
        .progress-value {
            color: #666;
        }
        .progress-bar-container {
            width: 100%;
            height: 20px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%;
            border-radius: 4px;
        }
        .progress-bar-fill.low { background-color: #dc3545; }
        .progress-bar-fill.medium { background-color: #ffc107; }
        .progress-bar-fill.high { background-color: #28a745; }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .table th,
        .table td {
            border: 1px solid #dee2e6;
            padding: 8px;
            text-align: left;
        }
        .table th {
            background-color: #2c5aa0;
            color: white;
            font-weight: bold;
        }
        .table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .table .number {
            text-align: right;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 8pt;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }

        .alert-box {
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .alert-critical {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .alert-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>SIPERA - PEMERINTAH PROVINSI BALI</h1>
        <h2>RINGKASAN ANGGARAN TAHUN {{ $year ?? date('Y') }}</h2>
        <p>Periode: {{ $period ?? 'Januari - Desember' }} | Dicetak: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <!-- Stats Overview -->
    <div class="stats-grid">
        <div class="stat-row">
            <div class="stat-box blue">
                <div class="stat-box-inner">
                    <div class="stat-value">Rp {{ number_format($totalBudget ?? 0, 0, ',', '.') }}</div>
                    <div class="stat-label">Total Anggaran</div>
                </div>
            </div>
            <div class="stat-box green">
                <div class="stat-box-inner">
                    <div class="stat-value">Rp {{ number_format($totalRealization ?? 0, 0, ',', '.') }}</div>
                    <div class="stat-label">Total Realisasi</div>
                </div>
            </div>
            <div class="stat-box yellow">
                <div class="stat-box-inner">
                    <div class="stat-value">{{ number_format($realizationPercentage ?? 0, 1) }}%</div>
                    <div class="stat-label">Persentase Realisasi</div>
                </div>
            </div>
            <div class="stat-box red">
                <div class="stat-box-inner">
                    <div class="stat-value">Rp {{ number_format($remainingBudget ?? 0, 0, ',', '.') }}</div>
                    <div class="stat-label">Sisa Anggaran</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Realization by Program -->
    @if(isset($programData) && count($programData) > 0)
    <div class="section">
        <div class="section-title">Realisasi per Program</div>
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 40px;">No</th>
                    <th>Nama Program</th>
                    <th style="width: 150px;" class="number">Anggaran</th>
                    <th style="width: 150px;" class="number">Realisasi</th>
                    <th style="width: 80px;" class="number">%</th>
                </tr>
            </thead>
            <tbody>
                @foreach($programData as $index => $program)
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td>{{ $program['name'] }}</td>
                    <td class="number">Rp {{ number_format($program['budget'] ?? 0, 0, ',', '.') }}</td>
                    <td class="number">Rp {{ number_format($program['realization'] ?? 0, 0, ',', '.') }}</td>
                    <td class="number">{{ number_format($program['percentage'] ?? 0, 1) }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Monthly Progress -->
    @if(isset($monthlyData) && count($monthlyData) > 0)
    <div class="section">
        <div class="section-title">Progres Bulanan</div>
        <div class="progress-section">
            @foreach($monthlyData as $month)
            <div class="progress-item">
                <div class="progress-label">
                    <span class="progress-name">{{ $month['name'] }}</span>
                    <span class="progress-value">
                        Rp {{ number_format($month['realization'] ?? 0, 0, ',', '.') }} /
                        Rp {{ number_format($month['plan'] ?? 0, 0, ',', '.') }}
                        ({{ number_format($month['percentage'] ?? 0, 1) }}%)
                    </span>
                </div>
                <div class="progress-bar-container">
                    @php
                        $pct = $month['percentage'] ?? 0;
                        $class = $pct < 50 ? 'low' : ($pct < 80 ? 'medium' : 'high');
                    @endphp
                    <div class="progress-bar-fill {{ $class }}" style="width: {{ min($pct, 100) }}%"></div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Alerts -->
    @if(isset($alerts) && count($alerts) > 0)
    <div class="section">
        <div class="section-title">Peringatan Aktif</div>
        @foreach($alerts as $alert)
        <div class="alert-box {{ $alert['severity'] === 'CRITICAL' ? 'alert-critical' : 'alert-warning' }}">
            <strong>[{{ $alert['severity'] }}]</strong> {{ $alert['message'] }}
        </div>
        @endforeach
    </div>
    @endif

    <div class="footer">
        <p>Dokumen ini digenerate otomatis oleh SIPERA (Sistem Informasi Perencanaan dan Realisasi Anggaran)</p>
        <p>{{ now()->format('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>
