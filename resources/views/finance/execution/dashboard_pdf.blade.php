<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Financial Execution Analytics</title>
    <style>
        @page { margin: 22px 22px 38px; }

        * { box-sizing: border-box; }

        body {
            color: #10212f;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 9.5px;
            line-height: 1.35;
            margin: 0;
        }

        h1, h2, h3, h4, h5, h6, p { margin: 0; }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        .page-break { page-break-before: always; }
        .keep-together { page-break-inside: avoid; }
        .muted { color: #667085; }
        .right { text-align: right; }
        .center { text-align: center; }
        .positive { color: #047857; font-weight: bold; }
        .negative { color: #b91c1c; font-weight: bold; }

        .header {
            background: #0f172a;
            border-bottom: 4px solid #f59e0b;
            color: #ffffff;
            padding: 18px 20px;
        }

        .header-eyebrow {
            color: #bfdbfe;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: .8px;
            text-transform: uppercase;
        }

        .header-title {
            font-size: 22px;
            font-weight: bold;
            margin: 4px 0 6px;
        }

        .header-copy { color: #cbd5e1; }

        .pdf-footer {
            border-top: 2px solid #f59e0b;
            bottom: -28px;
            color: #64748b;
            font-size: 8px;
            left: 0;
            padding-top: 6px;
            position: fixed;
            right: 0;
        }

        .pdf-footer td { border: 0; padding: 0; }
        .page-number::after { content: counter(page); }

        .filter-panel {
            background: #ffffff;
            border: 1px solid #d9e2ea;
            margin-top: 12px;
            padding: 11px 12px 12px;
        }

        .filter-title {
            color: #10212f;
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .filter-grid td {
            border: 0;
            padding: 0 5px;
            width: 33.33%;
        }

        .filter-grid td:first-child { padding-left: 0; }
        .filter-grid td:last-child { padding-right: 0; }

        .filter-label {
            color: #667085;
            display: block;
            font-size: 8px;
            font-weight: bold;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .filter-select {
            background: #ffffff;
            border: 1px solid #cfd8e3;
            color: #243746;
            min-height: 31px;
            padding: 8px 28px 7px 9px;
            position: relative;
        }

        .filter-chevron {
            color: #667085;
            font-size: 11px;
            position: absolute;
            right: 9px;
            top: 6px;
        }

        .hero {
            background: #0f766e;
            color: #ffffff;
            margin-top: 12px;
            padding: 15px 16px;
        }

        .hero-table td { border: 0; padding: 0; vertical-align: middle; }
        .hero-main { width: 42%; }
        .hero-metrics { width: 58%; }

        .hero-label {
            color: #ccfbf1;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: .7px;
            text-transform: uppercase;
        }

        .hero-value {
            font-size: 27px;
            font-weight: bold;
            margin: 3px 0;
        }

        .hero-sub { color: #d9fffa; font-size: 9px; }

        .hero-metric-grid td {
            border: 0;
            border-left: 1px solid rgba(255, 255, 255, .25);
            padding: 3px 8px;
            text-align: center;
            width: 25%;
        }

        .hero-metric-grid span {
            color: #ccfbf1;
            display: block;
            font-size: 7.5px;
            text-transform: uppercase;
        }

        .hero-metric-grid strong {
            display: block;
            font-size: 13px;
            margin-top: 3px;
        }

        .kpi-grid {
            border-collapse: separate;
            border-spacing: 5px;
            margin: 7px -5px 0;
            width: calc(100% + 10px);
        }

        .kpi-grid td {
            background: #ffffff;
            border: 1px solid #d9e2ea;
            border-top: 4px solid #0f766e;
            padding: 9px 8px;
            vertical-align: top;
            width: 16.66%;
        }

        .kpi-grid td.tone-gold { border-top-color: #b7791f; }
        .kpi-grid td.tone-green { border-top-color: #168a5b; }
        .kpi-grid td.tone-blue { border-top-color: #2563eb; }
        .kpi-grid td.tone-coral { border-top-color: #d65a31; }
        .kpi-grid td.tone-violet { border-top-color: #6d5bd0; }

        .kpi-label {
            color: #667085;
            font-size: 7.5px;
            font-weight: bold;
            min-height: 19px;
            text-transform: uppercase;
        }

        .kpi-value {
            color: #10212f;
            font-size: 14px;
            font-weight: bold;
            margin: 4px 0 2px;
        }

        .kpi-meta { color: #667085; font-size: 7.5px; }

        .chart-grid {
            border-collapse: separate;
            border-spacing: 5px;
            margin: 0 -5px;
            width: calc(100% + 10px);
        }

        .chart-grid > tbody > tr > td {
            border: 0;
            padding: 5px;
            vertical-align: top;
        }

        .chart-card {
            background: #ffffff;
            border: 1px solid #d9e2ea;
            padding: 10px 10px 7px;
            page-break-inside: avoid;
        }

        .chart-card--mix {
            background: #fbfdff;
            border-top: 3px solid #2563eb;
            padding-top: 8px;
        }

        .chart-title {
            color: #10212f;
            font-size: 11px;
            font-weight: bold;
        }

        .chart-note {
            color: #667085;
            font-size: 8px;
            margin: 2px 0 6px;
        }

        .chart-image {
            display: block;
            height: auto;
            width: 100%;
        }

        .chart-image--large {
            margin: 0 auto;
            max-width: 100%;
            width: 100%;
        }

        .section {
            margin-top: 12px;
            page-break-inside: avoid;
        }

        .section-head {
            margin-bottom: 7px;
        }

        .section-title {
            color: #10212f;
            font-size: 13px;
            font-weight: bold;
        }

        .section-note {
            color: #667085;
            font-size: 8.5px;
            margin-top: 2px;
        }

        .data-table th,
        .data-table td {
            border: 1px solid #e0e7ef;
            padding: 6px;
            vertical-align: middle;
        }

        .data-table th {
            background: #f5f8fa;
            color: #344054;
            font-size: 8px;
            text-align: left;
            text-transform: uppercase;
        }

        .data-table tbody tr:nth-child(even) td { background: #fbfcfd; }
        .data-table .total-row th,
        .data-table .total-row td { background: #eaf5f4; font-weight: bold; }

        .component-label strong { display: block; }
        .component-label span {
            color: #667085;
            display: block;
            font-size: 8px;
            margin-top: 2px;
        }

        .badge {
            display: inline-block;
            font-size: 8px;
            font-weight: bold;
            padding: 3px 6px;
        }

        .badge-good { background: #dcfce7; color: #166534; }
        .badge-warn { background: #fef3c7; color: #92400e; }
        .badge-bad { background: #fee2e2; color: #991b1b; }

        .progress-track {
            background: #e5e7eb;
            height: 6px;
            overflow: hidden;
            width: 100%;
        }

        .progress-track span {
            background: #0f766e;
            display: block;
            height: 6px;
        }

        .progress-track.warning span { background: #f59e0b; }
        .progress-track.danger span { background: #dc2626; }

        .reconciliation {
            background: #eff6ff;
            border-left: 4px solid #2563eb;
            color: #344054;
            margin-top: 8px;
            padding: 8px 10px;
        }

        .insight {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-left: 4px solid #2563eb;
            margin-bottom: 8px;
            padding: 9px 10px;
            page-break-inside: avoid;
        }

        .insight.warning { border-left-color: #f59e0b; }
        .insight.danger { border-left-color: #dc2626; }
        .insight.success { border-left-color: #16a34a; }
        .insight strong { color: #10212f; display: block; margin-bottom: 2px; }
    </style>
</head>
<body>
    @php
        $rows = collect($executionBreakdownRows ?? []);
        $componentRows = collect($componentBreakdownRows ?? []);
        $isSubComponentBreakdown = ($componentBreakdownLevel ?? 'component') === 'sub_component';
        $breakdownTitle = $isSubComponentBreakdown
            ? 'Sub-component Execution Performance Breakdown'
            : 'Component Breakdown';
        $breakdownNote = $isSubComponentBreakdown
            ? 'Execution performance for every sub-component within the selected component'
            : 'Total budget envelope followed by component-level execution using the same financial columns';
        $breakdownColumnLabel = $isSubComponentBreakdown ? 'Sub-component' : 'Component';
        $breakdownTotalLabel = $isSubComponentBreakdown
            ? 'Selected component and all sub-components'
            : 'All selected components';
        $globalBreakdownTitle = $isSubComponentBreakdown
            ? 'Selected Component - Global Execution Performance'
            : 'Execution Performance Breakdown';
        $globalBreakdownNote = $isSubComponentBreakdown
            ? 'Overall year-by-year execution performance for the selected component before the sub-component detail'
            : 'Year-by-year global commitments, planned commitments, disbursements, remaining balance, and rates';
        $totals = $executionBreakdownTotals ?? [
            'allocation' => $totalAllocation ?? 0,
            'commitment' => $totalCommitment ?? 0,
            'disbursement' => $totalDisbursements ?? 0,
            'remaining' => ($totalAllocation ?? 0) - ($totalCommitment ?? 0),
            'execution_rate' => $executionRate ?? 0,
            'disbursement_rate' => $disbursementRate ?? 0,
        ];
        $summary = $executionSummary ?? [];
        $filters = $executionFilters ?? [
            'sector' => 'All Sectors',
            'program' => 'All Programs',
            'project' => 'All Projects',
        ];
        $charts = $executionChartImages ?? [];
        $currencyCode = $currency ?? ($summary['currency'] ?? 'USD');
        $money = fn ($value) => $currencyCode . ' ' . number_format((float) $value, 2);
        $compactMoney = function ($value) use ($currencyCode) {
            $value = (float) $value;
            if (abs($value) >= 1000000) {
                return $currencyCode . ' ' . number_format($value / 1000000, 2) . 'M';
            }
            if (abs($value) >= 1000) {
                return $currencyCode . ' ' . number_format($value / 1000, 1) . 'K';
            }
            return $currencyCode . ' ' . number_format($value, 2);
        };
        $percent = fn ($value, $decimals = 1) => number_format(max(0, (float) $value), $decimals) . '%';
        $scopeLabel = match ($scopeType ?? 'global') {
            'sector' => 'Sector: ' . ($scope?->name ?? 'N/A'),
            'program' => 'Program: ' . ($scope?->name ?? 'N/A'),
            'project' => 'Project: ' . ($scope?->name ?? 'N/A'),
            default => 'All sectors, programs, and projects',
        };
        $generatedAt = now()->format('d M Y, H:i');
        $budgetEnvelope = (float) ($summary['budget_envelope'] ?? $totals['allocation'] ?? 0);
        $scheduledAllocation = (float) ($summary['scheduled_allocation'] ?? $rows->sum('allocation'));
        $unallocatedEnvelope = (float) ($summary['unallocated_envelope'] ?? ($budgetEnvelope - $scheduledAllocation));
        $kpiCards = [
            [
                'label' => 'Budget Envelope',
                'value' => $compactMoney($budgetEnvelope),
                'meta' => abs($unallocatedEnvelope) > 0.01
                    ? $money($scheduledAllocation) . ' scheduled'
                    : $money($budgetEnvelope),
                'tone' => 'teal',
            ],
            [
                'label' => 'Planned Commitments',
                'value' => $compactMoney($totals['commitment'] ?? 0),
                'meta' => $percent($totals['execution_rate'] ?? 0) . ' commitment rate',
                'tone' => 'gold',
            ],
            [
                'label' => 'Disbursed',
                'value' => $compactMoney($totals['disbursement'] ?? 0),
                'meta' => $percent($totals['disbursement_rate'] ?? 0) . ' paid',
                'tone' => 'green',
            ],
            [
                'label' => 'Remaining Global Commitments',
                'value' => $compactMoney($totals['remaining'] ?? 0),
                'meta' => $money($totals['remaining'] ?? 0),
                'tone' => 'blue',
            ],
            [
                'label' => 'Unpaid Commitments',
                'value' => $compactMoney($summary['unpaid_commitments'] ?? 0),
                'meta' => $money($summary['unpaid_commitments'] ?? 0),
                'tone' => 'coral',
            ],
            [
                'label' => 'Peak Commitment Year',
                'value' => $summary['peak_commitment_year'] ?? 'N/A',
                'meta' => $compactMoney($summary['peak_commitment'] ?? 0),
                'tone' => 'violet',
            ],
        ];
    @endphp

    <div class="pdf-footer">
        <table>
            <tr>
                <td>
                    <strong style="color: #0f172a;">Africa Think Tank Platform</strong>
                    &nbsp;·&nbsp; Financial Execution Analytics
                </td>
                <td class="right">
                    {{ $scopeLabel }} &nbsp;·&nbsp; Generated {{ $generatedAt }}
                    &nbsp;·&nbsp; Page <span class="page-number"></span>
                </td>
            </tr>
        </table>
    </div>

    <div class="header">
        <div class="header-eyebrow">Finance Execution</div>
        <div class="header-title">Financial Execution Analytics</div>
        <p class="header-copy">
            {{ $scopeLabel }}. Financial execution performance covering global commitments,
            planned commitments, paid disbursements, variance, momentum, and risk. Generated on {{ $generatedAt }}.
        </p>
    </div>

    <div class="filter-panel">
        <div class="filter-title">Execution Filters</div>
        <table class="filter-grid">
            <tr>
                <td>
                    <span class="filter-label">Sector</span>
                    <div class="filter-select">
                        {{ $filters['sector'] }}
                        <span class="filter-chevron">⌄</span>
                    </div>
                </td>
                <td>
                    <span class="filter-label">Program</span>
                    <div class="filter-select">
                        {{ $filters['program'] }}
                        <span class="filter-chevron">⌄</span>
                    </div>
                </td>
                <td>
                    <span class="filter-label">Project</span>
                    <div class="filter-select">
                        {{ $filters['project'] }}
                        <span class="filter-chevron">⌄</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="hero">
        <table class="hero-table">
            <tr>
                <td class="hero-main">
                    <div class="hero-label">Budget Envelope</div>
                    <div class="hero-value">{{ $compactMoney($budgetEnvelope) }}</div>
                    <div class="hero-sub">
                        {{ $money($budgetEnvelope) }} approved for the selected execution scope.
                        @if (abs($unallocatedEnvelope) > 0.01)
                            {{ $money(abs($unallocatedEnvelope)) }}
                            {{ $unallocatedEnvelope > 0 ? 'remains undistributed across component years.' : 'is allocated above the approved envelope.' }}
                        @endif
                    </div>
                </td>
                <td class="hero-metrics">
                    <table class="hero-metric-grid">
                        <tr>
                            <td>
                                <span>Commitment Rate</span>
                                <strong>{{ $percent($totals['execution_rate'] ?? 0) }}</strong>
                            </td>
                            <td>
                                <span>Disbursement Rate</span>
                                <strong>{{ $percent($totals['disbursement_rate'] ?? 0) }}</strong>
                            </td>
                            <td>
                                <span>Latest Year</span>
                                <strong>{{ $summary['latest_year'] ?? 'N/A' }}</strong>
                            </td>
                            <td>
                                <span>Years</span>
                                <strong>{{ number_format((int) ($summary['active_years'] ?? count($years))) }}</strong>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <table class="kpi-grid">
        <tr>
            @foreach ($kpiCards as $card)
                <td class="tone-{{ $card['tone'] }}">
                    <div class="kpi-label">{{ $card['label'] }}</div>
                    <div class="kpi-value">{{ $card['value'] }}</div>
                    <div class="kpi-meta">{{ $card['meta'] }}</div>
                </td>
            @endforeach
        </tr>
    </table>

    <div class="page-break"></div>

    <table class="chart-grid">
        <tr>
            <td style="width: 100%;">
                <div class="chart-card chart-card--mix">
                    <div class="chart-title">Execution Mix</div>
                    <div class="chart-note">Current share of disbursed, unpaid, and remaining global commitments</div>
                    @if (!empty($charts['execution_mix']))
                        <img class="chart-image chart-image--large" src="{{ $charts['execution_mix'] }}" alt="Execution mix pie chart">
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="page-break"></div>

    <table class="chart-grid">
        <tr>
            <td style="width: 50%;">
                <div class="chart-card">
                    <div class="chart-title">Rate Movement</div>
                    <div class="chart-note">Planned and disbursed against global commitments</div>
                    @if (!empty($charts['rate_movement']))
                        <img class="chart-image" src="{{ $charts['rate_movement'] }}" alt="Rate movement chart">
                    @endif
                </div>
            </td>
            <td style="width: 50%;">
                <div class="chart-card">
                    <div class="chart-title">Cumulative Momentum</div>
                    <div class="chart-note">Running global, planned, and payment flow</div>
                    @if (!empty($charts['cumulative_momentum']))
                        <img class="chart-image" src="{{ $charts['cumulative_momentum'] }}" alt="Cumulative momentum chart">
                    @endif
                </div>
            </td>
        </tr>
        <tr>
            <td style="width: 50%;">
                <div class="chart-card">
                    <div class="chart-title">Cumulative Financial Profile</div>
                    <div class="chart-note">Running totals by year</div>
                    @if (!empty($charts['financial_profile']))
                        <img class="chart-image" src="{{ $charts['financial_profile'] }}" alt="Cumulative financial profile chart">
                    @endif
                </div>
            </td>
            <td style="width: 50%;">
                <div class="chart-card">
                    <div class="chart-title">Variance Control</div>
                    <div class="chart-note">Running remaining global commitments after planned commitments</div>
                    @if (!empty($charts['variance_control']))
                        <img class="chart-image" src="{{ $charts['variance_control'] }}" alt="Variance control chart">
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="page-break"></div>

    <table class="chart-grid">
        <tr>
            <td style="width: 50%;">
                <div class="chart-card">
                    <div class="chart-title">Quality Radar</div>
                    <div class="chart-note">Execution balance and coverage</div>
                    @if (!empty($charts['quality_radar']))
                        <img class="chart-image" src="{{ $charts['quality_radar'] }}" alt="Quality radar chart">
                    @endif
                </div>
            </td>
            <td style="width: 50%;">
                <div class="chart-card">
                    <div class="chart-title">Exposure Concentration</div>
                    <div class="chart-note">Cumulative commitment scale and variance pressure</div>
                    @if (!empty($charts['exposure_concentration']))
                        <img class="chart-image" src="{{ $charts['exposure_concentration'] }}" alt="Exposure concentration chart">
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="section">
        <div class="section-head">
            <div class="section-title">{{ $globalBreakdownTitle }}</div>
            <div class="section-note">{{ $globalBreakdownNote }}</div>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="center">Year</th>
                    <th class="right">Global Commitments</th>
                    <th class="right">Planned Commitments</th>
                    <th class="right">Disbursed Amount</th>
                    <th class="right">Remaining</th>
                    <th class="center">Commitment Rate</th>
                    <th class="center">Disbursement Rate</th>
                    <th style="width: 130px;">Progress</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    @php
                        $executionClass = ($row['execution_rate'] ?? 0) < 50
                            ? 'badge-bad'
                            : (($row['execution_rate'] ?? 0) < 80 ? 'badge-warn' : 'badge-good');
                        $disbursementClass = ($row['disbursement_rate'] ?? 0) < 50
                            ? 'badge-bad'
                            : (($row['disbursement_rate'] ?? 0) < 80 ? 'badge-warn' : 'badge-good');
                        $progressClass = ($row['execution_rate'] ?? 0) < 50
                            ? 'danger'
                            : (($row['execution_rate'] ?? 0) < 80 ? 'warning' : '');
                    @endphp
                    <tr>
                        <td class="center"><strong>{{ $row['year'] }}</strong></td>
                        <td class="right">{{ number_format($row['allocation'], 2) }}</td>
                        <td class="right">{{ number_format($row['commitment'], 2) }}</td>
                        <td class="right">{{ number_format($row['disbursement'], 2) }}</td>
                        <td class="right">{{ number_format($row['remaining'], 2) }}</td>
                        <td class="center"><span class="badge {{ $executionClass }}">{{ $percent($row['execution_rate']) }}</span></td>
                        <td class="center"><span class="badge {{ $disbursementClass }}">{{ $percent($row['disbursement_rate']) }}</span></td>
                        <td>
                            <div class="progress-track {{ $progressClass }}">
                                <span style="width: {{ min(100, max(0, (float) $row['execution_rate'])) }}%;"></span>
                            </div>
                        </td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td class="center">TOTAL</td>
                    <td class="right">{{ number_format($totals['allocation'], 2) }}</td>
                    <td class="right">{{ number_format($totals['commitment'], 2) }}</td>
                    <td class="right">{{ number_format($totals['disbursement'], 2) }}</td>
                    <td class="right">{{ number_format($totals['remaining'], 2) }}</td>
                    <td class="center">{{ $percent($totals['execution_rate']) }}</td>
                    <td class="center">{{ $percent($totals['disbursement_rate']) }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>

        @if (abs($unallocatedEnvelope) > 0.01)
            <div class="reconciliation">
                <strong>Envelope reconciliation:</strong>
                the approved envelope is {{ $money($budgetEnvelope) }}, while
                {{ $money($scheduledAllocation) }} is distributed across component years.
                The {{ $money(abs($unallocatedEnvelope)) }} difference is included in the dashboard total
                and shown separately in the component breakdown.
            </div>
        @endif
    </div>

    <div class="section">
        <div class="section-head">
            <div class="section-title">{{ $breakdownTitle }}</div>
            <div class="section-note">{{ $breakdownNote }}</div>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ $breakdownColumnLabel }}</th>
                    <th class="right">Global Commitments</th>
                    <th class="right">Planned Commitments</th>
                    <th class="right">Disbursed Amount</th>
                    <th class="right">Remaining</th>
                    <th class="center">Commitment Rate</th>
                    <th class="center">Disbursement Rate</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totalExecutionClass = ($totals['execution_rate'] ?? 0) < 50
                        ? 'badge-bad'
                        : (($totals['execution_rate'] ?? 0) < 80 ? 'badge-warn' : 'badge-good');
                    $totalDisbursementClass = ($totals['disbursement_rate'] ?? 0) < 50
                        ? 'badge-bad'
                        : (($totals['disbursement_rate'] ?? 0) < 80 ? 'badge-warn' : 'badge-good');
                @endphp
                <tr class="total-row">
                    <td class="component-label">
                        <strong>Total</strong>
                        <span>{{ $breakdownTotalLabel }}</span>
                    </td>
                    <td class="right">{{ number_format($totals['allocation'], 2) }}</td>
                    <td class="right">{{ number_format($totals['commitment'], 2) }}</td>
                    <td class="right">{{ number_format($totals['disbursement'], 2) }}</td>
                    <td class="right">{{ number_format($totals['remaining'], 2) }}</td>
                    <td class="center"><span class="badge {{ $totalExecutionClass }}">{{ $percent($totals['execution_rate']) }}</span></td>
                    <td class="center"><span class="badge {{ $totalDisbursementClass }}">{{ $percent($totals['disbursement_rate']) }}</span></td>
                </tr>
                @foreach ($componentRows as $component)
                    @php
                        $executionClass = ($component['execution_rate'] ?? 0) < 50
                            ? 'badge-bad'
                            : (($component['execution_rate'] ?? 0) < 80 ? 'badge-warn' : 'badge-good');
                        $disbursementClass = ($component['disbursement_rate'] ?? 0) < 50
                            ? 'badge-bad'
                            : (($component['disbursement_rate'] ?? 0) < 80 ? 'badge-warn' : 'badge-good');
                    @endphp
                    <tr>
                        <td class="component-label">
                            <strong>{{ $component['label'] }}</strong>
                            @if (!empty($component['description']))
                                <span>{{ $component['description'] }}</span>
                            @endif
                        </td>
                        <td class="right">{{ number_format($component['allocation'], 2) }}</td>
                        <td class="right">{{ number_format($component['commitment'], 2) }}</td>
                        <td class="right">{{ number_format($component['disbursement'], 2) }}</td>
                        <td class="right">{{ number_format($component['remaining'], 2) }}</td>
                        <td class="center"><span class="badge {{ $executionClass }}">{{ $percent($component['execution_rate']) }}</span></td>
                        <td class="center"><span class="badge {{ $disbursementClass }}">{{ $percent($component['disbursement_rate']) }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-head">
            <div class="section-title">Execution Insights</div>
            <div class="section-note">Risk and progress signals from the current financial position</div>
        </div>
        @forelse ($aiInsights as $insight)
            <div class="insight {{ $insight['type'] ?? '' }}">
                <strong>{{ $insight['title'] ?? 'Insight' }}</strong>
                <div class="muted">{{ $insight['message'] ?? '' }}</div>
            </div>
        @empty
            <div class="insight success">
                <strong>No significant execution risks or anomalies detected.</strong>
                <div class="muted">The selected scope does not currently show major anomalies.</div>
            </div>
        @endforelse
    </div>
</body>
</html>
