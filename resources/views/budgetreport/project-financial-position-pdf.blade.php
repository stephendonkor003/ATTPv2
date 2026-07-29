<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: A4 landscape;
            margin: 12px 12px 42px 12px;
        }

        body {
            color: #162233;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8px;
            line-height: 1.25;
            margin: 0;
        }

        .header {
            background: #102a43;
            color: #fff;
            padding: 10px 12px;
            border-bottom: 4px solid #f4b942;
        }

        .header-table,
        .meta-table,
        .summary-table,
        .balance-table,
        .ledger-table,
        .footer-table {
            border-collapse: collapse;
            width: 100%;
        }

        .title {
            font-size: 17px;
            font-weight: 800;
            margin: 0 0 4px;
        }

        .subtitle {
            color: #f8d77a;
            font-size: 9px;
            font-weight: 700;
        }

        .header-side {
            font-size: 8px;
            text-align: right;
        }

        .section {
            margin-top: 8px;
        }

        .section-title {
            background: #176b87;
            color: #fff;
            font-size: 8px;
            font-weight: 800;
            letter-spacing: .4px;
            padding: 5px 7px;
            text-transform: uppercase;
        }

        .section-note {
            background: #f8fafc;
            border: 1px solid #d7e0ea;
            border-top: 0;
            color: #64748b;
            font-size: 6.5px;
            padding: 4px 7px;
        }

        .meta-table td {
            border: 1px solid #d7e0ea;
            padding: 5px 6px;
            vertical-align: top;
        }

        .meta-label {
            color: #64748b;
            display: block;
            font-size: 6.5px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .meta-value {
            color: #102a43;
            font-weight: 800;
        }

        .summary-table td {
            border: 1px solid #d7e0ea;
            padding: 6px;
            vertical-align: top;
            width: 25%;
        }

        .summary-label {
            color: #64748b;
            font-size: 6.5px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .summary-value {
            color: #102a43;
            font-size: 10px;
            font-weight: 900;
            margin-top: 3px;
        }

        .summary-meta {
            color: #64748b;
            font-size: 6px;
            margin-top: 2px;
        }

        .source-note {
            background: #edf9f4;
            border: 1px solid #b8e2d2;
            color: #176348;
            margin-top: 6px;
            padding: 6px 7px;
        }

        .source-note.filtered {
            background: #fff8e5;
            border-color: #efd894;
            color: #735600;
        }

        .source-note strong {
            display: block;
            font-size: 7px;
            margin-bottom: 2px;
        }

        .balance-table td {
            border: 1px solid #d7e0ea;
            padding: 5px 6px;
        }

        .balance-table td:nth-child(2),
        .balance-table td:nth-child(4) {
            font-weight: 900;
            text-align: right;
        }

        .control-name {
            color: #102a43;
            display: block;
            font-weight: 800;
        }

        .control-detail {
            color: #64748b;
            display: block;
            font-size: 6px;
            margin-top: 1px;
        }

        .overview-table,
        .analysis-table {
            border-collapse: collapse;
            width: 100%;
        }

        .overview-table td {
            border: 1px solid #d7e0ea;
            padding: 5px 6px;
            text-align: center;
        }

        .overview-table .overview-label {
            color: #64748b;
            display: block;
            font-size: 6px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .overview-table .overview-value {
            color: #102a43;
            display: block;
            font-size: 9px;
            font-weight: 900;
            margin-top: 2px;
        }

        .analysis-layout {
            border-collapse: collapse;
            width: 100%;
        }

        .analysis-layout > tbody > tr > td {
            padding: 0;
            vertical-align: top;
        }

        .analysis-layout > tbody > tr > td:first-child {
            padding-right: 4px;
        }

        .analysis-layout > tbody > tr > td:last-child {
            padding-left: 4px;
        }

        .analysis-table th {
            background: #e8f3f8;
            border: 1px solid #c5d8e4;
            color: #102a43;
            font-size: 6px;
            padding: 4px;
            text-align: left;
        }

        .analysis-table td {
            border: 1px solid #d7e0ea;
            font-size: 6px;
            padding: 4px;
        }

        .analysis-table .num {
            text-align: right;
            white-space: nowrap;
        }

        .ledger-table {
            table-layout: fixed;
            page-break-inside: auto;
        }

        .ledger-table thead {
            display: table-header-group;
        }

        .ledger-table tfoot {
            display: table-row-group;
        }

        .ledger-table tr {
            page-break-inside: avoid;
        }

        .ledger-table th {
            background: #102a43;
            border: 1px solid #102a43;
            color: #fff;
            font-size: 5.7px;
            font-weight: 800;
            padding: 4px 2px;
            text-align: center;
            text-transform: uppercase;
            vertical-align: middle;
        }

        .ledger-table td {
            border: 1px solid #d7e0ea;
            font-size: 5.8px;
            padding: 3px 2px;
            vertical-align: top;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .ledger-table tfoot th,
        .ledger-table tfoot td {
            background: #102a43;
            border: 1px solid #102a43;
            color: #fff;
            font-size: 5.8px;
            font-weight: 800;
            padding: 4px 2px;
        }

        .ledger-table .structure {
            width: 22%;
        }

        .ledger-table .structure span {
            color: #64748b;
            display: block;
            font-size: 5.3px;
            font-weight: 700;
            text-transform: capitalize;
        }

        .ledger-table .num {
            text-align: right;
            white-space: nowrap;
        }

        .row-project td {
            background: #e8f3f8;
            color: #102a43;
            font-weight: 800;
        }

        .row-activity td {
            background: #fff6dc;
            color: #4f3b00;
            font-weight: 700;
        }

        .row-sub td {
            background: #fff;
        }

        .positive {
            color: #176348;
        }

        .negative {
            color: #9f1d1d;
        }

        .page-break {
            page-break-before: always;
        }

        .footer {
            background: #102a43;
            border-top: 3px solid #f4b942;
            bottom: -32px;
            color: #dbeafe;
            font-size: 6.2px;
            left: 0;
            padding: 6px 10px 5px;
            position: fixed;
            right: 0;
        }

        .footer-table td {
            border: 0;
            padding: 0;
            vertical-align: middle;
            width: 33.333%;
        }

        .footer-brand {
            color: #fff;
            font-weight: 900;
        }

        .footer-brand span,
        .footer-context span,
        .footer-page span {
            color: #a9c5d7;
            display: block;
            font-size: 5.4px;
            margin-top: 1px;
        }

        .footer-context {
            text-align: center;
        }

        .footer-context strong {
            color: #f8d77a;
        }

        .footer-page {
            color: #fff;
            font-weight: 800;
            text-align: right;
        }

        .page-number:after {
            content: "Page " counter(page) " of " counter(pages);
        }
    </style>
</head>
<body>
@php
    $currency = $position['currency'] ?? 'USD';
    $totals = $position['totals'];
    $controls = $position['controls'] ?? [];
    $counts = $position['counts'] ?? [];
    $chart = $position['chart'] ?? [];
    $money = fn ($value) => $currency . ' ' . number_format((float) $value, 2);
    $scheduledAllocation = (float) ($totals['scheduled_allocation'] ?? 0);
    $scheduledCommitmentRate = $scheduledAllocation > 0
        ? ((float) ($totals['committed'] ?? 0) / $scheduledAllocation) * 100
        : 0;
    $scheduledDisbursementRate = $scheduledAllocation > 0
        ? ((float) ($totals['disbursed'] ?? 0) / $scheduledAllocation) * 100
        : 0;
    $generatedTimestamp = $reportGeneratedAt->format('d M Y, H:i:s T') . ' (' . $reportTimezone . ')';
@endphp

<div class="header">
    <table class="header-table">
        <tr>
            <td>
                <div class="title">Project Financial Position</div>
                <div class="subtitle">{{ $program->name }}</div>
            </td>
            <td class="header-side">
                Currency: <strong>{{ $currency }}</strong><br>
                Generated in your local time: <strong>{{ $generatedTimestamp }}</strong>
            </td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Report Context</div>
    <div class="section-note">Applied scope for the web report and PDF export</div>
    <table class="meta-table">
        <tr>
            <td style="width: 38%;">
                <span class="meta-label">Program</span>
                <span class="meta-value">{{ $program->program_id ? $program->program_id . ' - ' : '' }}{{ $program->name }}</span>
            </td>
            <td style="width: 28%;">
                <span class="meta-label">Funding Partners</span>
                <span class="meta-value">{{ $funders->isEmpty() ? 'N/A' : $funders->pluck('name')->implode(', ') }}</span>
            </td>
            <td style="width: 17%;">
                <span class="meta-label">Coverage</span>
                <span class="meta-value">{{ $filters['label'] ?? 'Life to date' }}</span>
            </td>
            <td style="width: 17%;">
                <span class="meta-label">Rows Included</span>
                <span class="meta-value">{{ ucfirst(str_replace('_', ' ', $filters['depth'] ?? 'sub_activity')) }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="4">
                <span class="meta-label">Structure Filter</span>
                <span class="meta-value">{{ $structureFilterLabel ?? 'All projects, activities, and sub-activities' }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="meta-label">Financial Focus</span>
                <span class="meta-value">{{ ucfirst(str_replace('_', ' ', $filters['focus'] ?? 'all')) }}</span>
            </td>
            <td colspan="2">
                <span class="meta-label">Search</span>
                <span class="meta-value">{{ filled($filters['search'] ?? '') ? $filters['search'] : 'None' }}</span>
            </td>
            <td>
                <span class="meta-label">Zero Lines</span>
                <span class="meta-value">{{ ($filters['include_zero'] ?? true) ? 'Shown' : 'Hidden' }}</span>
            </td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Financial Control Summary</div>
    <div class="section-note">Approved funding and current execution position for the selected scope</div>
    <table class="summary-table">
        <tr>
            <td><div class="summary-label">Approved Funding</div><div class="summary-value">{{ $money($totals['approved_funding'] ?? 0) }}</div><div class="summary-meta">Approved funding-partner value</div></td>
            <td><div class="summary-label">Scheduled Allocation</div><div class="summary-value">{{ $money($totals['scheduled_allocation'] ?? 0) }}</div><div class="summary-meta">Executive Dashboard allocation</div></td>
            <td><div class="summary-label">Committed</div><div class="summary-value">{{ $money($totals['committed'] ?? 0) }}</div><div class="summary-meta">{{ number_format($totals['commitment_rate'] ?? 0, 2) }}% of approved funding</div></td>
            <td><div class="summary-label">Disbursed</div><div class="summary-value">{{ $money($totals['disbursed'] ?? 0) }}</div><div class="summary-meta">{{ number_format($totals['disbursement_rate'] ?? 0, 2) }}% of approved funding</div></td>
        </tr>
        <tr>
            <td><div class="summary-label">Purchase Orders</div><div class="summary-value">{{ $money($totals['purchase_orders'] ?? 0) }}</div><div class="summary-meta">{{ number_format($counts['purchase_orders'] ?? 0) }} active records</div></td>
            <td><div class="summary-label">Funding Utilization Gap</div><div class="summary-value">{{ $money($totals['funding_utilization_gap'] ?? 0) }}</div><div class="summary-meta">Approved funding less commitments</div></td>
            <td><div class="summary-label">Purchase Request Total</div><div class="summary-value">{{ $money($totals['unprocessed_purchase_requests'] ?? 0) }}</div><div class="summary-meta">Unprocessed purchase requests</div></td>
            <td><div class="summary-label">Unpaid Commitments</div><div class="summary-value">{{ $money($totals['unpaid_commitments'] ?? 0) }}</div><div class="summary-meta">Purchase orders less disbursements</div></td>
        </tr>
    </table>

    <div class="source-note {{ ($position['dashboard_aligned'] ?? false) ? '' : 'filtered' }}">
        <strong>{{ ($position['dashboard_aligned'] ?? false) ? 'Execution Dashboard source active' : 'Filtered financial-position view' }}</strong>
        {{ ($position['dashboard_aligned'] ?? false)
            ? 'Scheduled allocation, commitment, disbursement, component totals, and utilization are loaded directly from the Execution Dashboard dataset.'
            : 'Custom funding, period, or structure filters are active, so totals are intentionally narrower than the programme-wide Executive Dashboard.' }}
    </div>
</div>

<div class="section">
    <div class="section-title">Executive Controls</div>
    <div class="section-note">Programme-envelope reconciliation</div>
    <table class="balance-table">
        <tr>
            <td>Approved Funding less Scheduled Allocation</td>
            <td class="{{ ($totals['approved_funding_less_scheduled_allocation'] ?? 0) < 0 ? 'negative' : 'positive' }}">{{ $money($totals['approved_funding_less_scheduled_allocation'] ?? 0) }}</td>
            <td>Unpaid commitments plus unprocessed purchase requests</td>
            <td>{{ $money($totals['commitment_pipeline_balance'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Commitment utilization of Approved Funding</td>
            <td>{{ number_format($totals['commitment_rate'] ?? 0, 2) }}%</td>
            <td>Disbursement utilization of Approved Funding</td>
            <td>{{ number_format($totals['disbursement_rate'] ?? 0, 2) }}%</td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">{{ $program->name }}</div>
    <div class="section-note">
        Funding Partners: {{ $funders->isEmpty() ? 'N/A' : $funders->pluck('name')->implode(', ') }}
    </div>
    <table class="overview-table">
        <tr>
            <td><span class="overview-label">Projects</span><span class="overview-value">{{ number_format($counts['projects'] ?? 0) }}</span></td>
            <td><span class="overview-label">Activities</span><span class="overview-value">{{ number_format($counts['activities'] ?? 0) }}</span></td>
            <td><span class="overview-label">Sub-Activities</span><span class="overview-value">{{ number_format($counts['sub_activities'] ?? 0) }}</span></td>
            <td><span class="overview-label">Commitments</span><span class="overview-value">{{ number_format($counts['commitments'] ?? 0) }}</span></td>
            <td><span class="overview-label">POs</span><span class="overview-value">{{ number_format($counts['purchase_orders'] ?? 0) }}</span></td>
            <td><span class="overview-label">Invoices</span><span class="overview-value">{{ number_format($counts['invoices'] ?? 0) }}</span></td>
            <td><span class="overview-label">Payments</span><span class="overview-value">{{ number_format($counts['disbursements'] ?? 0) }}</span></td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Accounting Integrity</div>
    <div class="section-note">Purchase-request, purchase-order, and disbursement control ratios</div>
    <table class="balance-table">
        <tr>
            <td>
                <span class="control-name">Commitment Processing</span>
                <span class="control-detail">Unprocessed Purchase Requests Ratio</span>
                <span class="control-detail">Unprocessed purchase requests ÷ committed</span>
            </td>
            <td>{{ number_format($controls['commitment_processing_rate'] ?? 0, 1) }}%</td>
            <td>
                <span class="control-name">Commitment Realization</span>
                <span class="control-detail">PO Coverage of Commitments</span>
                <span class="control-detail">Purchase orders ÷ committed</span>
            </td>
            <td>{{ number_format($controls['commitment_realization_rate'] ?? 0, 1) }}%</td>
        </tr>
        <tr>
            <td>
                <span class="control-name">Disbursement Backlog</span>
                <span class="control-detail">Unpaid Commitments Ratio</span>
                <span class="control-detail">Unpaid commitments ÷ purchase orders</span>
            </td>
            <td>{{ number_format($controls['disbursement_backlog_rate'] ?? 0, 1) }}%</td>
            <td>
                <span class="control-name">Disbursement Efficiency</span>
                <span class="control-detail">PO-to-Disbursement Conversion Rate</span>
                <span class="control-detail">Disbursed ÷ purchase orders</span>
            </td>
            <td>{{ number_format($controls['disbursement_efficiency_rate'] ?? 0, 1) }}%</td>
        </tr>
    </table>
</div>

<div class="section">
    <table class="analysis-layout">
        <tr>
            <td style="width: 58%;">
                <div class="section-title">Scheduled Allocation vs Commitments vs Disbursements</div>
                <div class="section-note">Project comparison for the selected filters</div>
                <table class="analysis-table">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th class="num">Scheduled Allocation</th>
                            <th class="num">Committed</th>
                            <th class="num">Disbursed</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($chart['labels'] ?? []) as $index => $label)
                            <tr>
                                <td>{{ $label }}</td>
                                <td class="num">{{ $money($chart['budget'][$index] ?? 0) }}</td>
                                <td class="num">{{ $money($chart['committed'][$index] ?? 0) }}</td>
                                <td class="num">{{ $money($chart['disbursed'][$index] ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4">No matching projects for the selected filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
            <td style="width: 42%;">
                <div class="section-title">Program Control Split</div>
                <div class="section-note">How approved funding is currently positioned</div>
                <table class="analysis-table">
                    <tbody>
                        <tr><td>Disbursed</td><td class="num">{{ $money($totals['disbursed'] ?? 0) }}</td></tr>
                        <tr><td>Unpaid Commitments</td><td class="num">{{ $money($totals['unpaid_commitments'] ?? 0) }}</td></tr>
                        <tr><td>Unprocessed Purchase Requests</td><td class="num">{{ $money($totals['unprocessed_purchase_requests'] ?? 0) }}</td></tr>
                        <tr><td>Funding Utilization Gap</td><td class="num">{{ $money($totals['funding_utilization_gap'] ?? 0) }}</td></tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>
</div>

<div class="section page-break">
    <div class="section-title">Full Program Balance Sheet</div>
    <div class="section-note">Scheduled allocation and execution by project, activity, and sub-activity in {{ $currency }}</div>
    <table class="ledger-table">
        <thead>
            <tr>
                <th style="width: 22%;">Program Structure</th>
                <th style="width: 7%;">Scheduled</th>
                <th style="width: 7%;">Committed</th>
                <th style="width: 6.2%;">POs</th>
                <th style="width: 6.2%;">Invoices</th>
                <th style="width: 6.2%;">Disbursed</th>
                <th style="width: 6.2%;">Scheduled Balance</th>
                <th style="width: 6.2%;">Unpaid Commitment</th>
                <th style="width: 4.4%;">Commitment %</th>
                <th style="width: 4.4%;">Disbursement %</th>
                <th style="width: 4.8%;">PR Ref.</th>
                <th style="width: 4.8%;">PO Ref.</th>
                <th style="width: 4.8%;">Invoice Ref.</th>
                <th style="width: 4.8%;">Payment Ref.</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($position['rows'] as $projectRow)
                @include('budgetreport.financial-position-pdf-row', ['row' => $projectRow, 'depth' => 0])
                @foreach ($projectRow['children'] as $activityRow)
                    @include('budgetreport.financial-position-pdf-row', ['row' => $activityRow, 'depth' => 1])
                    @foreach ($activityRow['children'] as $subRow)
                        @include('budgetreport.financial-position-pdf-row', ['row' => $subRow, 'depth' => 2])
                    @endforeach
                @endforeach
            @empty
                <tr>
                    <td colspan="14" style="padding: 12px; text-align: center;">No matching financial lines were found for the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <th>Scheduled total</th>
                <td class="num">{{ number_format($totals['scheduled_allocation'] ?? 0, 2) }}</td>
                <td class="num">{{ number_format($totals['committed'] ?? 0, 2) }}</td>
                <td class="num">{{ number_format($totals['purchase_orders'] ?? 0, 2) }}</td>
                <td class="num">{{ number_format($totals['invoiced'] ?? 0, 2) }}</td>
                <td class="num">{{ number_format($totals['disbursed'] ?? 0, 2) }}</td>
                <td class="num">{{ number_format(($totals['scheduled_allocation'] ?? 0) - ($totals['committed'] ?? 0), 2) }}</td>
                <td class="num">{{ number_format($totals['unpaid_commitments'] ?? 0, 2) }}</td>
                <td class="num">{{ number_format($scheduledCommitmentRate, 1) }}%</td>
                <td class="num">{{ number_format($scheduledDisbursementRate, 1) }}%</td>
                <td>—</td>
                <td>—</td>
                <td>—</td>
                <td>—</td>
            </tr>
        </tfoot>
    </table>
</div>

<div class="footer">
    <table class="footer-table">
        <tr>
            <td class="footer-brand">
                ATTP · Project Financial Position
                <span>Official financial control report</span>
            </td>
            <td class="footer-context">
                <strong>{{ $program->program_id ?: $program->name }}</strong>
                <span>{{ $filters['label'] ?? 'Life to date' }} · {{ $currency }} · Generated {{ $generatedTimestamp }}</span>
            </td>
            <td class="footer-page">
                <span class="page-number"></span>
                <span>Reconciled financial execution reporting</span>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
