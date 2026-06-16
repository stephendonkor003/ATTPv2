<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Executive Summary Report</title>
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

        .rank {
            background: #eff6ff;
            color: #1e40af;
            display: inline-block;
            font-size: 9px;
            font-weight: bold;
            padding: 2px 6px;
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
        <div class="title">Executive Summary Report</div>
        <p class="muted">
            Ranked financial insights across projects, activities, sub-activities, and program portfolios.
            Generated on {{ $generatedAt }}.
        </p>
    </div>

    <div class="section">
        <table class="summary-table">
            <tr>
                <td>
                    <div class="label">Ranked Allocation</div>
                    <div class="value">{{ $money($executiveStats['total_allocated']) }}</div>
                    <div class="muted">{{ number_format($executiveStats['projects']) }} projects ranked</div>
                </td>
                <td>
                    <div class="label">Project Budget</div>
                    <div class="value">{{ $money($executiveStats['total_budget']) }}</div>
                    <div class="muted">Remaining: {{ $money($executiveStats['remaining']) }}</div>
                </td>
                <td>
                    <div class="label">Activities Ranked</div>
                    <div class="value">{{ number_format($executiveStats['activities']) }}</div>
                    <div class="muted">{{ number_format($executiveStats['sub_activities']) }} sub-activities</div>
                </td>
                <td>
                    <div class="label">Avg. Project Allocation</div>
                    <div class="value">{{ $money($executiveStats['average_project_allocation']) }}</div>
                    <div class="muted">{{ number_format($executiveStats['programs']) }} programs</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <table class="summary-table">
            <tr>
                <td>
                    <div class="label">Top Project</div>
                    <div class="value" style="font-size: 12px;">{{ $executiveStats['top_project'] ?? 'N/A' }}</div>
                </td>
                <td>
                    <div class="label">Top Activity</div>
                    <div class="value" style="font-size: 12px;">{{ $executiveStats['top_activity'] ?? 'N/A' }}</div>
                </td>
                <td>
                    <div class="label">Top Sub-Activity</div>
                    <div class="value" style="font-size: 12px;">{{ $executiveStats['top_sub_activity'] ?? 'N/A' }}</div>
                </td>
                <td>
                    <div class="label">Top Program</div>
                    <div class="value" style="font-size: 12px;">{{ $programSheets->first()['name'] ?? 'N/A' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Project Funding Leaderboard</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 42px;">Rank</th>
                    <th>Project</th>
                    <th>Program</th>
                    <th class="right">Budget</th>
                    <th class="right">Allocated</th>
                    <th class="right">Remaining</th>
                    <th style="width: 145px;">Utilization</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($projectRankings->take(20) as $item)
                    <tr>
                        <td><span class="rank">{{ $loop->iteration }}</span></td>
                        <td>
                            <strong>{{ $item['project']->name }}</strong>
                            <div class="muted">{{ $item['project']->project_id }}</div>
                        </td>
                        <td>{{ $item['project']->program?->name ?? 'N/A' }}</td>
                        <td class="right">{{ $money($item['budget']) }}</td>
                        <td class="right">{{ $money($item['allocated']) }}</td>
                        <td class="right {{ $item['remaining'] < 0 ? 'negative' : 'positive' }}">{{ $money($item['remaining']) }}</td>
                        <td>
                            <div class="bar"><span style="width: {{ min(100, max(0, $item['utilization'])) }}%;"></span></div>
                            <div class="muted">{{ number_format($item['utilization'], 1) }}%</div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="center muted">No projects found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <table class="split">
            <tr>
                <td>
                    <div class="panel">
                        <table>
                            <thead>
                                <tr><th colspan="4">Top Activities</th></tr>
                                <tr>
                                    <th style="width: 42px;">Rank</th>
                                    <th>Activity</th>
                                    <th>Project</th>
                                    <th class="right">Allocated</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($activityRankings->take(10) as $item)
                                    <tr>
                                        <td><span class="rank">{{ $loop->iteration }}</span></td>
                                        <td><strong>{{ $item['activity']->name }}</strong></td>
                                        <td>{{ $item['project']?->name ?? 'N/A' }}</td>
                                        <td class="right">{{ $money($item['allocated']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="center muted">No activities found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </td>
                <td>
                    <div class="panel right-panel">
                        <table>
                            <thead>
                                <tr><th colspan="4">Top Sub-Activities</th></tr>
                                <tr>
                                    <th style="width: 42px;">Rank</th>
                                    <th>Sub-Activity</th>
                                    <th>Activity</th>
                                    <th class="right">Allocated</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($subActivityRankings->take(10) as $item)
                                    <tr>
                                        <td><span class="rank">{{ $loop->iteration }}</span></td>
                                        <td><strong>{{ $item['sub']->name }}</strong></td>
                                        <td>{{ $item['activity']?->name ?? 'N/A' }}</td>
                                        <td class="right">{{ $money($item['allocated']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="center muted">No sub-activities found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Program Portfolio Sheet</div>
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
                    <th style="width: 145px;">Utilization</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($programSheets as $row)
                    <tr>
                        <td>
                            <strong>{{ $row['name'] }}</strong>
                            <div class="muted">{{ number_format($row['sub_activities']) }} sub-activities</div>
                        </td>
                        <td>{{ $row['sector'] }}</td>
                        <td class="center">{{ number_format($row['projects']) }}</td>
                        <td class="center">{{ number_format($row['activities']) }}</td>
                        <td class="right">{{ $money($row['budget']) }}</td>
                        <td class="right">{{ $money($row['allocated']) }}</td>
                        <td class="right {{ $row['remaining'] < 0 ? 'negative' : 'positive' }}">{{ $money($row['remaining']) }}</td>
                        <td>
                            <div class="bar"><span style="width: {{ min(100, max(0, $row['utilization'])) }}%;"></span></div>
                            <div class="muted">{{ number_format($row['utilization'], 1) }}%</div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="center muted">No programs found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
