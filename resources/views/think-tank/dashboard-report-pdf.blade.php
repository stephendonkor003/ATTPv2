<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $member->name }} Dashboard Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 11px; line-height: 1.45; }
        .header { background: #0f172a; color: #fff; padding: 18px 20px; border-radius: 10px; }
        .kicker { color: #fde68a; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .title { font-size: 22px; font-weight: 700; margin: 5px 0; }
        .muted { color: #64748b; }
        .section { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-top: 12px; }
        .section-title { font-size: 11px; text-transform: uppercase; color: #475569; font-weight: 700; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f5f9; color: #475569; font-size: 10px; text-transform: uppercase; text-align: left; padding: 6px; border-bottom: 1px solid #cbd5e1; }
        td { padding: 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .grid { width: 100%; }
        .grid td { width: 25%; border: 0; padding: 5px; }
        .metric { border: 1px solid #e2e8f0; border-radius: 7px; padding: 10px; background: #f8fafc; }
        .metric-label { color: #64748b; font-size: 10px; text-transform: uppercase; font-weight: 700; }
        .metric-value { color: #0f172a; font-size: 15px; font-weight: 700; margin-top: 4px; }
        .bar { height: 10px; border-radius: 99px; background: #e2e8f0; overflow: hidden; margin-top: 5px; }
        .bar span { display: block; height: 10px; background: #0f766e; border-radius: 99px; }
        .status-pill { display: inline-block; border-radius: 99px; padding: 3px 7px; background: #e0f2fe; color: #075985; font-size: 10px; font-weight: 700; }
        .two-col td { width: 50%; vertical-align: top; border: 0; padding: 6px; }
    </style>
</head>
<body>
@php
    $currency = $purchaseOrderRecords->first()?->resolved_currency ?? $member->consortium?->currency ?? 'USD';
    $receiptRate = min(100, max(0, (float) ($receiptSummary['rate'] ?? 0)));
    $poPaymentRate = (float) ($metrics['po_allocated'] ?? 0) > 0
        ? min(100, ((float) ($metrics['disbursed'] ?? 0) / (float) $metrics['po_allocated']) * 100)
        : 0;
    $areaAccess = array_merge([
        'me' => false,
        'reports' => false,
        'finance' => false,
        'procurement_plans' => false,
    ], (array) ($portalAreaAccess ?? []));
    $meSummary = array_merge([
        'total' => 0,
        'open' => 0,
        'submitted' => 0,
        'action_required' => 0,
    ], (array) data_get($mePerformanceUpdates ?? [], 'summary', []));
    $financeValues = collect($chartData['finance']['values'] ?? [])->map(fn ($value): float => (float) $value);
    $financeMaximum = max(1, (float) ($financeValues->max() ?? 0));
@endphp

<div class="header">
    <div class="kicker">Think Tank Dashboard Report / {{ $dashboardFilter['label'] }}</div>
    <div class="title">{{ $member->name }}</div>
    <div>{{ $member->consortium?->name ?? 'Consortium not linked' }}{{ $member->country ? ' / ' . $member->country : '' }}</div>
    <div>Generated {{ now()->format('M d, Y H:i') }}</div>
</div>

@if ($areaAccess['finance'])
<table class="grid" style="margin-top: 12px;">
    <tr>
        <td><div class="metric"><div class="metric-label">Funding Committed</div><div class="metric-value">{{ $currency }} {{ number_format($metrics['po_allocated'], 2) }}</div></div></td>
        <td><div class="metric"><div class="metric-label">Payments Recorded</div><div class="metric-value">{{ $currency }} {{ number_format($metrics['disbursed'], 2) }}</div></div></td>
        <td><div class="metric"><div class="metric-label">Remaining Balance</div><div class="metric-value">{{ $currency }} {{ number_format($metrics['po_unpaid'], 2) }}</div><div class="muted">{{ number_format($poPaymentRate, 1) }}% paid</div></div></td>
        <td><div class="metric"><div class="metric-label">Receipt Confirmed</div><div class="metric-value">{{ $currency }} {{ number_format($receiptSummary['confirmed'], 2) }}</div></div></td>
    </tr>
</table>

<table class="two-col">
    <tr>
        <td>
            <div class="section">
                <div class="section-title">Financial Graph</div>
                @foreach($chartData['finance']['labels'] as $index => $label)
                    @php
                        $value = (float) ($chartData['finance']['values'][$index] ?? 0);
                        $width = min(100, ($value / $financeMaximum) * 100);
                    @endphp
                    <div><strong>{{ $label }}</strong> - {{ $currency }} {{ number_format($value, 2) }}</div>
                    <div class="bar"><span style="width: {{ number_format($width, 2, '.', '') }}%;"></span></div>
                @endforeach
            </div>
        </td>
        <td>
            <div class="section">
                <div class="section-title">Receipt Confirmation Graph</div>
                <div><strong>Confirmed</strong> - {{ $currency }} {{ number_format($receiptSummary['confirmed'], 2) }}</div>
                <div class="bar"><span style="width: {{ number_format($receiptRate, 2, '.', '') }}%; background:#16a34a;"></span></div>
                <div style="margin-top: 8px;"><strong>Awaiting confirmation</strong> - {{ $currency }} {{ number_format($receiptSummary['pending'], 2) }}</div>
                <div class="bar"><span style="width: {{ number_format(100 - $receiptRate, 2, '.', '') }}%; background:#f59e0b;"></span></div>
            </div>
        </td>
    </tr>
</table>
@endif

@if ($areaAccess['me'])
    <div class="section">
        <div class="section-title">M&amp;E Data Assignments</div>
        <div>Total assigned: <strong>{{ number_format((int) $meSummary['total']) }}</strong></div>
        <div>Need action: <strong>{{ number_format((int) $meSummary['action_required']) }}</strong></div>
        <div>Open now: <strong>{{ number_format((int) $meSummary['open']) }}</strong></div>
        <div>Submitted: <strong>{{ number_format((int) $meSummary['submitted']) }}</strong></div>
    </div>
@endif

@if ($areaAccess['reports'])
    <div class="section">
        <div class="section-title">Report Upload Status</div>
        @forelse($reportStatusCounts as $status => $count)
            <div>{{ ucfirst(str_replace('_', ' ', $status)) }}: <strong>{{ number_format($count) }}</strong></div>
        @empty
            <div class="muted">No reports found.</div>
        @endforelse
    </div>
@endif

@if ($areaAccess['procurement_plans'])
    <div class="section">
        <div class="section-title">Procurement Plans</div>
        <div>Plans submitted by this think tank: <strong>{{ number_format((int) $metrics['procurement_plans']) }}</strong></div>
    </div>
@endif

@if ($areaAccess['finance'])
<div class="section">
    <div class="section-title">Funding Transfer Ledger</div>
    <table>
        <thead><tr><th>Reference</th><th>Source Reference</th><th>Committed</th><th>Paid</th><th>Remaining</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($purchaseOrderRecords as $purchaseOrder)
            @php
                $purchaseRequest = $purchaseOrder->purchaseRequest ?: $purchaseOrder->budgetCommitment?->purchaseRequest;
                $paidAmount = (float) $purchaseOrder->disbursements->sum('amount');
                $unpaidAmount = max((float) $purchaseOrder->amount - $paidAmount, 0);
            @endphp
            <tr>
                <td>{{ $purchaseOrder->reference_no ?: 'Funding transfer' }}<br><span class="muted">{{ $purchaseOrder->issued_at?->format('M d, Y') ?? '-' }}</span></td>
                <td>{{ $purchaseRequest?->reference_no ?? '-' }}</td>
                <td>{{ $purchaseOrder->resolved_currency ?? $currency }} {{ number_format((float) $purchaseOrder->amount, 2) }}</td>
                <td>{{ $purchaseOrder->resolved_currency ?? $currency }} {{ number_format($paidAmount, 2) }}</td>
                <td>{{ $purchaseOrder->resolved_currency ?? $currency }} {{ number_format($unpaidAmount, 2) }}</td>
                <td><span class="status-pill">{{ ucfirst(str_replace('_', ' ', $purchaseOrder->status ?? 'pending')) }}</span></td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No funding transfers found for this selected period.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="section">
    <div class="section-title">Transfer and Receipt Register</div>
    <table>
        <thead><tr><th>Reference</th><th>PO</th><th>PR</th><th>Date</th><th>Amount</th><th>Method</th><th>Receipt</th></tr></thead>
        <tbody>
        @forelse($transferRecords as $transfer)
            @php
                $purchaseOrder = $transfer->purchaseOrder;
                $purchaseRequest = $purchaseOrder?->purchaseRequest ?: $purchaseOrder?->budgetCommitment?->purchaseRequest;
            @endphp
            <tr>
                <td>{{ $transfer->transfer_reference ?: $transfer->reference_no }}</td>
                <td>{{ $purchaseOrder?->reference_no ?? '-' }}</td>
                <td>{{ $purchaseRequest?->reference_no ?? '-' }}</td>
                <td>{{ $transfer->paid_at?->format('M d, Y') ?? $transfer->created_at?->format('M d, Y') }}</td>
                <td>{{ $currency }} {{ number_format((float) $transfer->amount, 2) }}</td>
                <td>{{ ucfirst(str_replace('_', ' ', $transfer->payment_method ?? 'transfer')) }}</td>
                <td><span class="status-pill">{{ ucfirst(str_replace('_', ' ', $transfer->recipient_confirmation_status ?: 'pending')) }}</span></td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">No paid transfer records found for this selected period.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endif

@if ($areaAccess['reports'])
<div class="section">
    <div class="section-title">Recent Activity Reports</div>
    <table>
        <thead><tr><th>Report</th><th>Period</th><th>Progress</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($recentReports as $report)
            <tr>
                <td>{{ $report->title }}</td>
                <td>{{ $report->reporting_period_start?->format('M d, Y') ?? '-' }} - {{ $report->reporting_period_end?->format('M d, Y') ?? '-' }}</td>
                <td>{{ number_format((float) $report->progress_percent, 1) }}%</td>
                <td><span class="status-pill">{{ ucfirst(str_replace('_', ' ', $report->status)) }}</span></td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">No reports found for this selected period.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endif
</body>
</html>
