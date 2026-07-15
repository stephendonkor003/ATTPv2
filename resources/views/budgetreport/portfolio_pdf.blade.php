<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Portfolio Budget Overview</title>
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
            background: #ffffff;
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

        .insight td {
            background: #f8fafc;
            border-left: 4px solid #0f766e;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
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

        .split .panel {
            border: 1px solid #e5e7eb;
            margin-right: 8px;
        }

        .split .panel:last-child {
            margin-left: 8px;
            margin-right: 0;
        }

        .rank {
            background: #eff6ff;
            color: #1e40af;
            display: inline-block;
            font-size: 9px;
            font-weight: bold;
            padding: 2px 6px;
        }
    </style>
</head>
<body>
    @php
        $portfolioCurrency = $portfolioCurrency ?? ($portfolioStats['currency'] ?? 'USD');
        $money = fn ($value, $currency = null) => trim(($currency ?: $portfolioCurrency ?: 'USD') . ' ' . number_format((float) $value, 2));
        $totalBudget = max((float) ($portfolioStats['total_budget'] ?? 0), 1);
        $generatedAt = now()->format('d M Y, H:i');
        $yearLabels = collect($chartData['yearLabels'] ?? []);
        $yearTotals = collect($chartData['yearTotals'] ?? []);
    @endphp

    <div class="header">
        <div class="eyebrow">Budget Reports</div>
        <div class="title">Portfolio Budget Overview</div>
        <p class="muted">
            Sector funding, program concentration, project ranking, and annual allocation movement.
            Reporting currency: {{ $portfolioCurrency }}. Generated on {{ $generatedAt }}.
        </p>
    </div>

    <div class="section">
        <table class="summary-table">
            <tr>
                <td>
                    <div class="label">Total Budget</div>
                    <div class="value">{{ $money($portfolioStats['total_budget'] ?? 0) }}</div>
                    <div class="muted">{{ number_format($portfolioStats['funded_sectors'] ?? 0) }} funded sectors</div>
                </td>
                <td>
                    <div class="label">Programs</div>
                    <div class="value">{{ number_format($portfolioStats['programs'] ?? 0) }}</div>
                    <div class="muted">{{ number_format($portfolioStats['projects'] ?? 0) }} projects</div>
                </td>
                <td>
                    <div class="label">Activities</div>
                    <div class="value">{{ number_format($portfolioStats['activities'] ?? 0) }}</div>
                    <div class="muted">Mapped under funded projects</div>
                </td>
                <td>
                    <div class="label">Avg. Project Budget</div>
                    <div class="value">{{ $money($portfolioStats['average_project_budget'] ?? 0) }}</div>
                    <div class="muted">Across all portfolio projects</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <table class="insight">
            <tr>
                <td>
                    <strong>Largest Sector:</strong> {{ $portfolioStats['largest_sector'] ?? 'N/A' }}
                    ({{ number_format($portfolioStats['largest_sector_share'] ?? 0, 1) }}% of total allocation)
                </td>
                <td style="border-left-color:#2563eb;">
                    <strong>Largest Program:</strong> {{ $portfolioStats['largest_program'] ?? 'N/A' }}
                    ({{ number_format($portfolioStats['largest_program_share'] ?? 0, 1) }}% of total allocation)
                </td>
                <td style="border-left-color:#f59e0b;">
                    <strong>Portfolio Density:</strong>
                    {{ number_format($portfolioStats['projects'] ?? 0) }} projects and
                    {{ number_format($portfolioStats['activities'] ?? 0) }} activities
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Sector Breakdown</div>
        <table>
            <thead>
                <tr>
                    <th>Sector</th>
                    <th class="center">Programs</th>
                    <th class="center">Projects</th>
                    <th class="center">Activities</th>
                    <th class="right">Total Budget</th>
                    <th style="width: 180px;">Share</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sectorSummaries as $sector)
                    @php
                        $share = ($portfolioStats['total_budget'] ?? 0) > 0 ? (($sector['total_budget'] / $portfolioStats['total_budget']) * 100) : 0;
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $sector['name'] }}</strong>
                            <div class="muted">Average project budget: {{ $money($sector['average_project_budget'], $sector['currency'] ?? $portfolioCurrency) }}</div>
                        </td>
                        <td class="center">{{ number_format($sector['programs']) }}</td>
                        <td class="center">{{ number_format($sector['projects']) }}</td>
                        <td class="center">{{ number_format($sector['activities']) }}</td>
                        <td class="right"><strong>{{ $money($sector['total_budget'], $sector['currency'] ?? $portfolioCurrency) }}</strong></td>
                        <td>
                            <div class="bar"><span style="width: {{ min(100, $share) }}%;"></span></div>
                            <div class="muted">{{ number_format($share, 1) }}%</div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="center muted">No budget sectors found.</td>
                    </tr>
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
                                <tr>
                                    <th colspan="3">Top Funded Programs</th>
                                </tr>
                                <tr>
                                    <th style="width: 42px;">Rank</th>
                                    <th>Program</th>
                                    <th class="right">Budget</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($programSummaries->take(10) as $program)
                                    <tr>
                                        <td><span class="rank">{{ $loop->iteration }}</span></td>
                                        <td>
                                            <strong>{{ \Illuminate\Support\Str::limit($program['name'], 54) }}</strong>
                                            <div class="muted">{{ $program['sector'] }} | {{ number_format($program['projects']) }} projects</div>
                                        </td>
                                        <td class="right">{{ $money($program['total_budget'], $program['currency'] ?? $portfolioCurrency) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="center muted">No programs found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </td>
                <td>
                    <div class="panel">
                        <table>
                            <thead>
                                <tr>
                                    <th colspan="3">Top Funded Projects</th>
                                </tr>
                                <tr>
                                    <th style="width: 42px;">Rank</th>
                                    <th>Project</th>
                                    <th class="right">Budget</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($projectSummaries->take(10) as $project)
                                    <tr>
                                        <td><span class="rank">{{ $loop->iteration }}</span></td>
                                        <td>
                                            <strong>{{ \Illuminate\Support\Str::limit($project['name'], 54) }}</strong>
                                            <div class="muted">{{ $project['program'] }} | {{ number_format($project['activities']) }} activities</div>
                                        </td>
                                        <td class="right">{{ $money($project['total_budget'], $project['currency'] ?? $portfolioCurrency) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="center muted">No projects found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Annual Allocation Movement</div>
        <table>
            <thead>
                <tr>
                    <th>Year</th>
                    <th class="right">Allocation</th>
                    <th style="width: 260px;">Relative Size</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($yearLabels as $index => $year)
                    @php
                        $amount = (float) ($yearTotals[$index] ?? 0);
                        $share = $totalBudget > 0 ? (($amount / $totalBudget) * 100) : 0;
                    @endphp
                    <tr>
                        <td><strong>{{ $year }}</strong></td>
                        <td class="right">{{ $money($amount) }}</td>
                        <td>
                            <div class="bar"><span style="width: {{ min(100, $share) }}%;"></span></div>
                            <div class="muted">{{ number_format($share, 1) }}% of portfolio</div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="center muted">No annual allocation data found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
