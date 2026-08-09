<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $reportTitle }}</title>
    <style>
        @page { size: A4 landscape; margin: 12px 12px 42px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #162233; font-family: DejaVu Sans, Arial, sans-serif; font-size: 7px; line-height: 1.3; }
        .header { padding: 12px 14px; border-bottom: 4px solid #f4b942; background: #102a43; color: #fff; }
        .header-table,.meta-table,.summary-table,.portfolio-table,.ledger-table,.footer-table { width: 100%; border-collapse: collapse; }
        .title { margin-bottom: 3px; font-size: 17px; font-weight: 900; }
        .subtitle { color: #f8d77a; font-size: 9px; font-weight: 800; }
        .header-side { color: #d7e6f1; font-size: 7px; line-height: 1.55; text-align: right; }
        .header-side strong { color: #fff; }
        .section { margin-top: 8px; }
        .section-title { padding: 5px 7px; background: #176b87; color: #fff; font-size: 8px; font-weight: 900; letter-spacing: .4px; text-transform: uppercase; }
        .section-note { padding: 4px 7px; border: 1px solid #d7e0ea; border-top: 0; background: #f8fafc; color: #64748b; font-size: 6.2px; }
        .meta-table td { padding: 6px; border: 1px solid #d7e0ea; vertical-align: top; }
        .meta-label,.summary-label { display: block; color: #64748b; font-size: 6px; font-weight: 900; letter-spacing: .25px; text-transform: uppercase; }
        .meta-value { display: block; margin-top: 2px; color: #102a43; font-size: 7px; font-weight: 800; }
        .summary-table td { width: 25%; padding: 7px; border: 1px solid #d7e0ea; vertical-align: top; }
        .summary-value { margin-top: 3px; color: #102a43; font-size: 11px; font-weight: 900; }
        .summary-meta { margin-top: 2px; color: #64748b; font-size: 5.8px; }
        .currency-note { margin-top: 6px; padding: 6px 7px; border: 1px solid #b8e2d2; background: #edf9f4; color: #176348; }
        .currency-note strong { margin-right: 5px; }
        .portfolio-table thead { display: table-header-group; }
        .portfolio-table tr,.ledger-table tr { page-break-inside: avoid; }
        .portfolio-table th { padding: 4px 5px; border: 1px solid #c5d8e4; background: #e8f3f8; color: #102a43; font-size: 6px; text-align: left; text-transform: uppercase; }
        .portfolio-table td { padding: 5px; border: 1px solid #d7e0ea; font-size: 6px; vertical-align: top; }
        .portfolio-name { color: #102a43; font-weight: 900; }
        .muted { color: #64748b; }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .ledger-table { table-layout: fixed; page-break-inside: auto; }
        .ledger-table thead { display: table-header-group; }
        .ledger-table th { padding: 4px 3px; border: 1px solid #102a43; background: #102a43; color: #fff; font-size: 5.5px; font-weight: 900; text-align: left; text-transform: uppercase; vertical-align: middle; }
        .ledger-table td { padding: 4px 3px; border: 1px solid #d7e0ea; font-size: 5.6px; vertical-align: top; word-break: break-word; overflow-wrap: anywhere; }
        .ledger-table tbody tr:nth-child(even) td { background: #f8fafc; }
        .ledger-table .item { width: 24%; }
        .ledger-table .owner { width: 14%; }
        .ledger-table .method { width: 12%; }
        .ledger-table .schedule { width: 10%; }
        .ledger-table .amount { width: 11%; }
        .ledger-table .documents { width: 8%; }
        .ledger-table .status { width: 11%; }
        .item-code { display: block; color: #176b87; font-size: 5.3px; font-weight: 900; }
        .item-title { display: block; margin-top: 2px; color: #102a43; font-weight: 800; }
        .item-source { display: block; margin-top: 2px; color: #64748b; font-size: 5px; }
        .status-pill { display: inline-block; padding: 2px 4px; border-radius: 8px; background: #edf3f0; color: #39594d; font-size: 4.8px; font-weight: 900; text-transform: uppercase; }
        .status-pill.submitted { background: #e7f0ff; color: #2b5d97; }
        .status-pill.revision_requested,.status-pill.rejected { background: #fff0e7; color: #974b17; }
        .status-pill.approved,.status-pill.no_objection_obtained,.status-pill.published { background: #e4f7ec; color: #21613b; }
        .footer { position: fixed; right: 0; bottom: -32px; left: 0; padding: 6px 10px 5px; border-top: 3px solid #f4b942; background: #102a43; color: #dbeafe; font-size: 6px; }
        .footer-table td { width: 33.333%; padding: 0; border: 0; vertical-align: middle; }
        .footer-brand { color: #fff; font-weight: 900; }
        .footer-context { text-align: center; }
        .footer-context strong { color: #f8d77a; }
        .footer-page { color: #fff; font-weight: 800; text-align: right; }
        .footer-small { display: block; margin-top: 1px; color: #a9c5d7; font-size: 5px; }
        .page-number:after { content: "Page " counter(page) " of " counter(pages); }
    </style>
</head>
<body>
@php
    $statusLabels = [
        'draft' => 'Draft', 'submitted' => 'Under review', 'revision_requested' => 'Action required',
        'rejected' => 'Rejected', 'approved' => 'Approved', 'no_objection_obtained' => 'No-objection obtained',
        'published' => 'Published',
    ];
    $currencySummary = $summary['budget_by_currency']->map(
        fn ($row) => $row['currency'].' '.number_format((float) $row['amount'], 2)
    )->implode('  |  ');
    $filterStatus = filled($filters['status']) ? ($statusLabels[$filters['status']] ?? Illuminate\Support\Str::headline($filters['status'])) : 'All statuses';
@endphp

<div class="header">
    <table class="header-table">
        <tr>
            <td>
                <div class="title">{{ $reportTitle }}</div>
                <div class="subtitle">{{ $scopeLabel }}</div>
            </td>
            <td class="header-side">
                Generated: <strong>{{ $reportGeneratedAt->format('d M Y, H:i:s') }}</strong><br>
                ATTP Procurement Oversight &middot; Official system report
            </td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Report Context</div>
    <div class="section-note">Applied reporting scope and filters used to generate this document</div>
    <table class="meta-table">
        <tr>
            <td style="width:30%"><span class="meta-label">Reporting scope</span><span class="meta-value">{{ $scopeLabel }}</span></td>
            <td style="width:18%"><span class="meta-label">Financial year</span><span class="meta-value">{{ $filters['fiscal_year'] ?: 'All years' }}</span></td>
            <td style="width:18%"><span class="meta-label">Item status</span><span class="meta-value">{{ $filterStatus }}</span></td>
            <td style="width:20%"><span class="meta-label">Search filter</span><span class="meta-value">{{ $filters['q'] ?: 'None' }}</span></td>
            <td style="width:14%"><span class="meta-label">Plans included</span><span class="meta-value">{{ number_format($summary['plans']) }}</span></td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Procurement Control Summary</div>
    <div class="section-note">Portfolio totals, review position and World Bank clearance progress</div>
    <table class="summary-table">
        <tr>
            <td><span class="summary-label">Think Tanks</span><div class="summary-value">{{ number_format($summary['think_tanks']) }}</div><div class="summary-meta">Organizations represented</div></td>
            <td><span class="summary-label">Procurement Items</span><div class="summary-value">{{ number_format($summary['items']) }}</div><div class="summary-meta">Items within this scope</div></td>
            <td><span class="summary-label">Approved / STEP Ready</span><div class="summary-value">{{ number_format($summary['approved']) }}</div><div class="summary-meta">Approved items in approved plans</div></td>
            <td><span class="summary-label">Action Required</span><div class="summary-value">{{ number_format($summary['action_required']) }}</div><div class="summary-meta">Returned or rejected items</div></td>
        </tr>
        <tr>
            <td><span class="summary-label">Exported to STEP</span><div class="summary-value">{{ number_format($summary['step_exported']) }}</div><div class="summary-meta">Export activity recorded</div></td>
            <td><span class="summary-label">No-objection / Published</span><div class="summary-value">{{ number_format($summary['no_objection']) }}</div><div class="summary-meta">World Bank-cleared items</div></td>
            <td><span class="summary-label">Published Opportunities</span><div class="summary-value">{{ number_format($summary['published']) }}</div><div class="summary-meta">Open or published execution records</div></td>
            <td><span class="summary-label">Documents</span><div class="summary-value">{{ number_format($summary['tor_documents'] + $summary['supporting_documents']) }}</div><div class="summary-meta">{{ $summary['tor_documents'] }} TOR &middot; {{ $summary['supporting_documents'] }} supporting</div></td>
        </tr>
    </table>
    <div class="currency-note"><strong>Recorded procurement value:</strong> {{ $currencySummary ?: 'No monetary value recorded' }}. Currency amounts are reported separately and are not converted.</div>
</div>

<div class="section">
    <div class="section-title">Portfolio Distribution</div>
    <div class="section-note">Procurement position by Think Tank within the selected report scope</div>
    <table class="portfolio-table">
        <thead><tr><th>Think Tank</th><th>Financial years</th><th class="center">Plans</th><th class="center">Items</th><th>Recorded value</th><th class="center">Approved</th><th class="center">Action</th><th class="center">Cleared</th><th class="center">Published</th></tr></thead>
        <tbody>
        @foreach($byThinkTank as $row)
            <tr>
                <td><span class="portfolio-name">{{ $row['name'] }}</span><br><span class="muted">{{ $row['country'] ?: 'Country not set' }} @if($row['consortium'])&middot; {{ $row['consortium'] }}@endif</span></td>
                <td>{{ $row['years']->map(fn ($year) => 'FY '.$year)->implode(', ') ?: 'N/A' }}</td>
                <td class="center">{{ $row['plans'] }}</td><td class="center">{{ $row['items'] }}</td>
                <td>{{ $row['budget_by_currency']->implode(' | ') }}</td>
                <td class="center">{{ $row['approved'] }}</td><td class="center">{{ $row['action_required'] }}</td><td class="center">{{ $row['no_objection'] }}</td><td class="center">{{ $row['published'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<div class="section">
    <div class="section-title">Detailed Procurement Item Register</div>
    <div class="section-note">Line-item plan, method, schedule, document, STEP and clearance details</div>
    <table class="ledger-table">
        <thead>
            <tr><th style="width:3%">#</th><th class="owner">Think Tank / Plan</th><th class="item">Procurement item</th><th class="method">Category / Method</th><th class="schedule">Schedule</th><th class="amount num">Estimated amount</th><th class="documents center">Documents</th><th class="status">Status / STEP</th></tr>
        </thead>
        <tbody>
        @foreach($items as $item)
            <tr>
                <td class="center">{{ $loop->iteration }}</td>
                <td><strong>{{ $item->plan?->member?->name }}</strong><br><span class="muted">FY {{ $item->plan?->fiscal_year }} &middot; {{ $item->plan?->plan_code }}</span></td>
                <td><span class="item-code">{{ $item->item_code }}</span><span class="item-title">{{ $item->title }}</span>@if($item->source_reference)<span class="item-source">Source: {{ $item->source_reference }}</span>@endif</td>
                <td>{{ Illuminate\Support\Str::headline($item->procurement_category) ?: 'N/A' }}<br><span class="muted">{{ $item->procurement_method ?: 'Method not set' }}</span></td>
                <td>{{ $item->planned_quarter ?: 'N/A' }}<br><span class="muted">{{ $item->planned_start_date?->format('d M Y') ?: 'Start TBC' }}</span></td>
                <td class="num"><strong>{{ $item->currency }} {{ number_format((float) $item->estimated_amount, 2) }}</strong></td>
                <td class="center">{{ $item->documents->where('document_type', 'tor')->count() }} TOR<br>{{ $item->documents->where('document_type', 'supporting')->count() }} support</td>
                <td><span class="status-pill {{ $item->status }}">{{ $item->workflowActivityStatus() }}</span>@if($item->step_reference)<br><span class="muted">STEP {{ $item->step_reference }}</span>@endif @if($item->no_objection_date)<br><span class="muted">No-objection {{ $item->no_objection_date->format('d M Y') }}</span>@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<div class="footer">
    <table class="footer-table"><tr>
        <td><span class="footer-brand">ATTP &middot; Procurement Oversight</span><span class="footer-small">African Think Tank Platform</span></td>
        <td class="footer-context"><strong>{{ $scopeLabel }}</strong><span class="footer-small">{{ $filters['fiscal_year'] ? 'FY '.$filters['fiscal_year'] : 'All financial years' }}</span></td>
        <td class="footer-page"><span class="page-number"></span><span class="footer-small">Generated {{ $reportGeneratedAt->format('d M Y H:i') }}</span></td>
    </tr></table>
</div>
</body>
</html>
