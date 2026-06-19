<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Execution Dashboard</title>
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
            width: 16.66%;
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

        .bar.danger span {
            background: #dc2626;
        }

        .bar.warning span {
            background: #f59e0b;
        }

        .badge {
            border-radius: 999px;
            display: inline-block;
            font-size: 9px;
            font-weight: bold;
            padding: 3px 8px;
        }

        .badge-good {
            background: #dcfce7;
            color: #166534;
        }

        .badge-warn {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-bad {
            background: #fee2e2;
            color: #991b1b;
        }

        .insight {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-left: 4px solid #2563eb;
            margin-bottom: 8px;
            padding: 9px 10px;
        }

        .insight.warning {
            border-left-color: #f59e0b;
        }

        .insight.danger {
            border-left-color: #dc2626;
        }

        .insight.success {
            border-left-color: #16a34a;
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
        $rate = fn ($value) => number_format((float) $value, 1) . '%';
        $scopeLabel = match ($scopeType) {
            'sector' => 'Sector: ' . ($scope?->name ?? 'N/A'),
            'program' => 'Program: ' . ($scope?->name ?? 'N/A'),
            'project' => 'Project: ' . ($scope?->name ?? 'N/A'),
            default => 'All sectors, programs, and projects',
        };
        $generatedAt = now()->format('d M Y, H:i');
        $totalAlloc = collect($allocationByYear)->sum();
        $totalCommit = collect($commitmentByYear)->sum();
        $totalDisbursed = collect($disbursementByYear)->sum();
        $breakdownTotals = $executionBreakdownTotals ?? [
            'allocation' => $totalAlloc,
            'commitment' => $totalCommit,
            'disbursement' => $totalDisbursed,
            'remaining' => max($totalAlloc - $totalCommit, 0),
            'execution_rate' => 0,
            'disbursement_rate' => 0,
        ];
        $totalRemain = $breakdownTotals['remaining'];
        $totalPercent = min(100, max(0, (float) ($breakdownTotals['execution_rate'] ?? 0)));
        $totalDisbursementPercent = min(100, max(0, (float) ($breakdownTotals['disbursement_rate'] ?? 0)));
    @endphp

    <div class="header">
        <div class="eyebrow">Finance Execution</div>
        <div class="title">Execution Dashboard</div>
        <p class="muted">
            {{ $scopeLabel }}. Financial execution performance covering planned allocation,
            actual commitment, variance, momentum, and risk. Generated on {{ $generatedAt }}.
        </p>
    </div>

    <div class="section">
        <table class="summary-table">
            <tr>
                <td>
                    <div class="label">Total Allocation</div>
                    <div class="value">{{ $money($totalAllocation) }}</div>
                    <div class="muted">Planned budget envelope</div>
                </td>
                <td>
                    <div class="label">Total Commitment</div>
                    <div class="value">{{ $money($totalCommitment) }}</div>
                    <div class="muted">Actual committed amount</div>
                </td>
                <td>
                    <div class="label">Execution Rate</div>
                    <div class="value">{{ number_format($executionRate, 2) }}%</div>
                    <div class="muted">Commitment against allocation</div>
                </td>
                <td>
                    <div class="label">Total Disbursements</div>
                    <div class="value">{{ $money($totalDisbursements) }}</div>
                    <div class="muted">Paid disbursements</div>
                </td>
                <td>
                    <div class="label">Disbursement Rate</div>
                    <div class="value">{{ number_format($disbursementRate, 2) }}%</div>
                    <div class="muted">Paid against commitment</div>
                </td>
                <td>
                    <div class="label">Variance</div>
                    <div class="value {{ $variance < 0 ? 'negative' : 'positive' }}">{{ $money($variance) }}</div>
                    <div class="muted">Allocation minus commitment</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Execution Performance Breakdown</div>
        <table>
            <thead>
                <tr>
                    <th class="center">Year</th>
                    <th class="right">Allocated Amount</th>
                    <th class="right">Committed Amount</th>
                    <th class="right">Disbursed Amount</th>
                    <th class="right">Remaining</th>
                    <th class="center">Execution %</th>
                    <th class="center">Disbursement %</th>
                    <th style="width: 210px;">Progress</th>
                </tr>
            </thead>
            <tbody>
                @foreach (($executionBreakdownRows ?? collect()) as $row)
                    @php
                        $percent = min(100, max(0, (float) ($row['execution_rate'] ?? 0)));
                        $disbursementPercent = min(100, max(0, (float) ($row['disbursement_rate'] ?? 0)));
                        $barClass = $percent < 50 ? 'danger' : ($percent < 80 ? 'warning' : '');
                        $badgeClass = $percent < 50 ? 'badge-bad' : ($percent < 80 ? 'badge-warn' : 'badge-good');
                        $disbursementBadgeClass = $disbursementPercent < 50 ? 'badge-bad' : ($disbursementPercent < 80 ? 'badge-warn' : 'badge-good');
                    @endphp
                    <tr>
                        <td class="center"><strong>{{ $row['year'] }}</strong></td>
                        <td class="right">{{ $money($row['allocation']) }}</td>
                        <td class="right">{{ $money($row['commitment']) }}</td>
                        <td class="right">{{ $money($row['disbursement']) }}</td>
                        <td class="right positive">{{ $money($row['remaining']) }}</td>
                        <td class="center"><span class="badge {{ $badgeClass }}">{{ $rate($percent) }}</span></td>
                        <td class="center"><span class="badge {{ $disbursementBadgeClass }}">{{ $rate($disbursementPercent) }}</span></td>
                        <td>
                            <div class="bar {{ $barClass }}"><span style="width: {{ min(100, max(0, $percent)) }}%;"></span></div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th class="center">TOTAL</th>
                    <th class="right">{{ $money($breakdownTotals['allocation']) }}</th>
                    <th class="right">{{ $money($breakdownTotals['commitment']) }}</th>
                    <th class="right">{{ $money($breakdownTotals['disbursement']) }}</th>
                    <th class="right positive">{{ $money($totalRemain) }}</th>
                    <th class="center">{{ $rate($totalPercent) }}</th>
                    <th class="center">{{ $rate($totalDisbursementPercent) }}</th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="section">
        <table class="split">
            <tr>
                <td>
                    <div class="panel">
                        <table>
                            <thead>
                                <tr><th colspan="2">Execution Quality Metrics</th></tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Budget Utilization</td>
                                    <td class="right"><strong>{{ $rate($radarMetrics['budget_utilization'] ?? 0) }}</strong></td>
                                </tr>
                                <tr>
                                    <td>Timeliness</td>
                                    <td class="right"><strong>{{ $rate($radarMetrics['timeliness'] ?? 0) }}</strong></td>
                                </tr>
                                <tr>
                                    <td>Consistency</td>
                                    <td class="right"><strong>{{ $rate($radarMetrics['consistency'] ?? 0) }}</strong></td>
                                </tr>
                                <tr>
                                    <td>Coverage</td>
                                    <td class="right"><strong>{{ $rate($radarMetrics['coverage'] ?? 0) }}</strong></td>
                                </tr>
                                <tr>
                                    <td>Risk Control</td>
                                    <td class="right"><strong>{{ $rate($radarMetrics['risk_exposure'] ?? 0) }}</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </td>
                <td>
                    <div class="panel right-panel">
                        <table>
                            <thead>
                                <tr><th colspan="3">Yearly Risk Snapshot</th></tr>
                                <tr>
                                    <th>Year</th>
                                    <th class="right">Variance</th>
                                    <th class="center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($heatmap as $row)
                                    @php
                                        $rowVariance = ($row['allocation'] ?? 0) - ($row['commitment'] ?? 0);
                                        $status = $rowVariance < 0 ? 'Over commitment' : (($row['execution_rate'] ?? 0) < 50 ? 'Slow execution' : 'On track');
                                    @endphp
                                    <tr>
                                        <td>{{ $row['year'] }}</td>
                                        <td class="right {{ $rowVariance < 0 ? 'negative' : 'positive' }}">{{ $money($rowVariance) }}</td>
                                        <td class="center">{{ $status }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Execution Insights</div>
        @forelse ($aiInsights as $insight)
            <div class="insight {{ $insight['type'] ?? '' }}">
                <strong>{{ $insight['title'] ?? 'Insight' }}</strong>
                <div class="muted">{{ $insight['message'] ?? '' }}</div>
            </div>
        @empty
            <div class="insight success">
                <strong>No significant execution risks detected.</strong>
                <div class="muted">The selected scope does not currently show major anomalies.</div>
            </div>
        @endforelse
    </div>
</body>
</html>
