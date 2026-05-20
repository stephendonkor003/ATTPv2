<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: A4 landscape;
            margin: 12px 12px 18px 12px;
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
        .ledger-table {
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

        .balance-table td {
            border: 1px solid #d7e0ea;
            padding: 5px 6px;
        }

        .balance-table td:nth-child(2),
        .balance-table td:nth-child(4) {
            font-weight: 900;
            text-align: right;
        }

        .ledger-table {
            table-layout: fixed;
            page-break-inside: auto;
        }

        .ledger-table thead {
            display: table-header-group;
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

        .footer {
            bottom: -10px;
            color: #64748b;
            font-size: 6px;
            left: 0;
            position: fixed;
            right: 0;
            text-align: center;
        }
    </style>
</head>
<body>
@php
    $currency = $position['currency'] ?? 'USD';
    $totals = $position['totals'];
    $money = fn ($value) => $currency . ' ' . number_format((float) $value, 2);
    $filterSummary = collect([
        'Period' => $filters['label'] ?? 'Life to date',
        'Structure' => $structureFilterLabel ?? 'All projects, activities, and sub-activities',
        'Focus' => ucfirst(str_replace('_', ' ', $filters['focus'] ?? 'all')),
        'Detail' => ucfirst(str_replace('_', ' ', $filters['depth'] ?? 'sub_activity')),
        'Search' => $filters['search'] ?: 'None',
    ]);
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
                Generated: {{ now()->format('d M Y, H:i') }}<br>
                Report Type: Full Financial Landscape
            </td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Report Context</div>
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
    </table>
</div>

<div class="section">
    <div class="section-title">Financial Control Summary</div>
    <table class="summary-table">
        <tr>
            <td><div class="summary-label">Approved Funding</div><div class="summary-value">{{ $money($totals['approved_funding'] ?? 0) }}</div></td>
            <td><div class="summary-label">Program Budget</div><div class="summary-value">{{ $money($totals['budget'] ?? 0) }}</div></td>
            <td><div class="summary-label">Committed</div><div class="summary-value">{{ $money($totals['committed'] ?? 0) }}</div></td>
            <td><div class="summary-label">Disbursed</div><div class="summary-value">{{ $money($totals['disbursed'] ?? 0) }}</div></td>
        </tr>
        <tr>
            <td><div class="summary-label">Purchase Orders</div><div class="summary-value">{{ $money($totals['purchase_orders'] ?? 0) }}</div></td>
            <td><div class="summary-label">Invoices</div><div class="summary-value">{{ $money($totals['invoiced'] ?? 0) }}</div></td>
            <td><div class="summary-label">Funding Balance</div><div class="summary-value">{{ $money($totals['funding_balance'] ?? 0) }}</div></td>
            <td><div class="summary-label">Unpaid Commitments</div><div class="summary-value">{{ $money($totals['unpaid_commitments'] ?? 0) }}</div></td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Balance Sheet Checks</div>
    <table class="balance-table">
        <tr>
            <td>Approved funding less program budget</td>
            <td class="{{ ($totals['allocation_balance'] ?? 0) < 0 ? 'negative' : 'positive' }}">{{ $money($totals['allocation_balance'] ?? 0) }}</td>
            <td>Program budget less approved commitments</td>
            <td class="{{ ($totals['uncommitted_budget'] ?? 0) < 0 ? 'negative' : 'positive' }}">{{ $money($totals['uncommitted_budget'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Approved commitments less disbursements</td>
            <td class="{{ ($totals['unpaid_commitments'] ?? 0) < 0 ? 'negative' : '' }}">{{ $money($totals['unpaid_commitments'] ?? 0) }}</td>
            <td>Invoices less disbursements</td>
            <td class="{{ ($totals['invoice_balance'] ?? 0) < 0 ? 'negative' : '' }}">{{ $money($totals['invoice_balance'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Commitment utilization</td>
            <td>{{ number_format($totals['commitment_rate'] ?? 0, 1) }}%</td>
            <td>Disbursement utilization</td>
            <td>{{ number_format($totals['disbursement_rate'] ?? 0, 1) }}%</td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Full Program Financial Position Ledger</div>
    <table class="ledger-table">
        <thead>
            <tr>
                <th style="width: 22%;">Structure</th>
                <th style="width: 7%;">Budget</th>
                <th style="width: 7%;">Commit.</th>
                <th style="width: 6.2%;">PO</th>
                <th style="width: 6.2%;">Invoice</th>
                <th style="width: 6.2%;">Paid</th>
                <th style="width: 6.2%;">Budg. Bal.</th>
                <th style="width: 6.2%;">Unpaid</th>
                <th style="width: 4.4%;">Com.%</th>
                <th style="width: 4.4%;">Pay%</th>
                <th style="width: 4.8%;">PR</th>
                <th style="width: 4.8%;">PO Ref</th>
                <th style="width: 4.8%;">Inv.</th>
                <th style="width: 4.8%;">Pay Ref</th>
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
    </table>
</div>

<div class="footer">
    Project Financial Position | {{ $program->name }} | {{ $filters['label'] ?? 'Life to date' }} | {{ $currency }}
</div>
</body>
</html>
