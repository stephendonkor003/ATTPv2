<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Budget Summary Dashboard</title>
    <style>
        @page { margin: 22px; }

        body {
            color: #111827;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10.5px;
            line-height: 1.4;
        }

        h1, h2, h3, p {
            margin: 0;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th, td {
            border: 1px solid #e5e7eb;
            padding: 6px 7px;
            vertical-align: top;
        }

        th {
            background: #f8fafc;
            color: #0f172a;
            font-weight: bold;
            text-align: left;
        }

        .header {
            background: #0f172a;
            border-bottom: 4px solid #f59e0b;
            color: #fff;
            padding: 18px 20px;
        }

        .eyebrow {
            color: #bfdbfe;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: .8px;
            text-transform: uppercase;
        }

        .title {
            font-size: 22px;
            font-weight: bold;
            margin: 4px 0 6px;
        }

        .muted {
            color: #64748b;
        }

        .header .muted {
            color: #cbd5e1;
        }

        .section {
            margin-top: 14px;
        }

        .section-title {
            color: #0f172a;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 7px;
        }

        .summary-table td {
            background: #fff;
            border: 1px solid #dbeafe;
            width: 25%;
        }

        .label {
            color: #64748b;
            font-size: 9px;
            text-transform: uppercase;
        }

        .value {
            color: #0f172a;
            font-size: 16px;
            font-weight: bold;
            margin-top: 3px;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .positive {
            color: #047857;
            font-weight: bold;
        }

        .negative {
            color: #b91c1c;
            font-weight: bold;
        }

        .bar {
            background: #e5e7eb;
            border-radius: 999px;
            height: 7px;
            overflow: hidden;
            width: 100%;
        }

        .bar span {
            background: #0f766e;
            display: block;
            height: 7px;
        }

        .split td {
            border: 0;
            padding: 0;
            width: 50%;
        }

        .panel {
            border: 1px solid #e5e7eb;
            margin-right: 8px;
        }

        .panel.right-panel {
            margin-left: 8px;
            margin-right: 0;
        }
    </style>
</head>
<body>
    @php
        $money = fn ($value) => number_format((float) $value, 2);
        $generatedAt = now()->format('d M Y, H:i');
    @endphp

    <div class="header">
        <div class="eyebrow">Budget Summary</div>
        <div class="title">Budget Allocation Dashboard</div>
        <p class="muted">
            Portfolio budget, allocation coverage, remaining balance, and program funding concentration.
            Generated on {{ $generatedAt }}.
        </p>
    </div>

    <div class="section">
        <table class="summary-table">
            <tr>
                <td>
                    <div class="label">Total Budget</div>
                    <div class="value">{{ $money($summary['total_budget']) }}</div>
                    <div class="muted">{{ number_format($totalProjects) }} projects</div>
                </td>
                <td>
                    <div class="label">Total Allocated</div>
                    <div class="value">{{ $money($summary['total_allocated']) }}</div>
                    <div class="muted">{{ number_format($summary['allocation_rate'], 1) }}% coverage</div>
                </td>
                <td>
                    <div class="label">Remaining Balance</div>
                    <div class="value {{ $summary['remaining_budget'] < 0 ? 'negative' : 'positive' }}">{{ $money($summary['remaining_budget']) }}</div>
                    <div class="muted">Budget minus project allocation</div>
                </td>
                <td>
                    <div class="label">Programs</div>
                    <div class="value">{{ number_format($totalPrograms) }}</div>
                    <div class="muted">{{ number_format($totalActivities) }} activities</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <table class="summary-table">
            <tr>
                <td>
                    <div class="label">Project-Level Allocation</div>
                    <div class="value">{{ $money($summary['project_allocated']) }}</div>
                </td>
                <td>
                    <div class="label">Activity-Level Allocation</div>
                    <div class="value">{{ $money($summary['activity_allocated']) }}</div>
                </td>
                <td>
                    <div class="label">Sub-Activity Allocation</div>
                    <div class="value">{{ $money($summary['sub_activity_allocated']) }}</div>
                </td>
                <td>
                    <div class="label">Top Program</div>
                    <div class="value" style="font-size: 12px;">{{ $summary['top_program'] ?? 'N/A' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <table class="split">
            <tr>
                <td>
                    <div class="panel">
                        <table>
                            <thead>
                                <tr><th colspan="5">Sector Allocation Summary</th></tr>
                                <tr>
                                    <th>Sector</th>
                                    <th class="center">Programs</th>
                                    <th class="center">Projects</th>
                                    <th class="right">Allocated</th>
                                    <th class="right">Remaining</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($sectorRows as $sector)
                                    <tr>
                                        <td><strong>{{ $sector['sector'] }}</strong></td>
                                        <td class="center">{{ number_format($sector['programs']) }}</td>
                                        <td class="center">{{ number_format($sector['projects']) }}</td>
                                        <td class="right">{{ $money($sector['total_allocated']) }}</td>
                                        <td class="right {{ $sector['remaining'] < 0 ? 'negative' : 'positive' }}">{{ $money($sector['remaining']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="center muted">No sector allocation data available.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </td>
                <td>
                    <div class="panel right-panel">
                        <table>
                            <thead>
                                <tr><th colspan="4">Top Programs</th></tr>
                                <tr>
                                    <th>Program</th>
                                    <th>Sector</th>
                                    <th class="right">Budget</th>
                                    <th class="right">Allocated</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($programRows->sortByDesc('total_allocated')->take(8) as $row)
                                    <tr>
                                        <td><strong>{{ \Illuminate\Support\Str::limit($row['name'], 42) }}</strong></td>
                                        <td>{{ $row['sector'] }}</td>
                                        <td class="right">{{ $money($row['total_budget']) }}</td>
                                        <td class="right">{{ $money($row['total_allocated']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="center muted">No programs found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Program Allocation Breakdown</div>
        <table>
            <thead>
                <tr>
                    <th>Program</th>
                    <th>Sector</th>
                    <th class="center">Projects</th>
                    <th class="center">Activities</th>
                    <th class="right">Budget</th>
                    <th class="right">Allocated</th>
                    <th class="right">Remaining</th>
                    <th style="width: 150px;">Utilization</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($programRows->sortByDesc('total_allocated') as $row)
                    <tr>
                        <td>
                            <strong>{{ $row['name'] }}</strong>
                            <div class="muted">{{ number_format($row['sub_activities']) }} sub-activities</div>
                        </td>
                        <td>{{ $row['sector'] }}</td>
                        <td class="center">{{ number_format($row['projects']) }}</td>
                        <td class="center">{{ number_format($row['activities']) }}</td>
                        <td class="right">{{ $money($row['total_budget']) }}</td>
                        <td class="right">{{ $money($row['total_allocated']) }}</td>
                        <td class="right {{ $row['remaining'] < 0 ? 'negative' : 'positive' }}">{{ $money($row['remaining']) }}</td>
                        <td>
                            <div class="bar"><span style="width: {{ min(100, max(0, $row['utilization'])) }}%;"></span></div>
                            <div class="muted">{{ number_format($row['utilization'], 1) }}%</div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="center muted">No budget programs found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
