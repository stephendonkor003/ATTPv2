<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $member->name }} Activity Reports</title>
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
        .grid td { width: 25%; border: 0; padding: 5px; }
        .metric { border: 1px solid #e2e8f0; border-radius: 7px; padding: 10px; background: #f8fafc; }
        .metric-label { color: #64748b; font-size: 10px; text-transform: uppercase; font-weight: 700; }
        .metric-value { color: #0f172a; font-size: 15px; font-weight: 700; margin-top: 4px; }
        .bar { height: 10px; border-radius: 99px; background: #e2e8f0; overflow: hidden; margin-top: 5px; }
        .bar span { display: block; height: 10px; background: #2563eb; border-radius: 99px; }
        .pill { display: inline-block; border-radius: 99px; padding: 3px 7px; background: #e0f2fe; color: #075985; font-size: 10px; font-weight: 700; }
        .two-col td { width: 50%; vertical-align: top; border: 0; padding: 6px; }
    </style>
</head>
<body>
@php
    $currency = 'USD';
    $maxFunds = max($chartData['funds']['values']->all() ?: [1]);
@endphp

<div class="header">
    <div class="kicker">Activity Reporting Profile / {{ $dashboardFilter['label'] }}</div>
    <div class="title">{{ $member->name }}</div>
    <div>{{ $member->consortium?->name ?? 'Consortium not linked' }}{{ $member->country ? ' / ' . $member->country : '' }}</div>
    <div>Generated {{ now()->format('M d, Y H:i') }}</div>
</div>

<table class="grid" style="margin-top: 12px;">
    <tr>
        <td><div class="metric"><div class="metric-label">Reports</div><div class="metric-value">{{ number_format($reportStats['total']) }}</div></div></td>
        <td><div class="metric"><div class="metric-label">Approved</div><div class="metric-value">{{ number_format($reportStats['approved']) }}</div></div></td>
        <td><div class="metric"><div class="metric-label">Average Progress</div><div class="metric-value">{{ number_format($reportStats['average_progress'], 1) }}%</div></div></td>
        <td><div class="metric"><div class="metric-label">Funds Spent</div><div class="metric-value">{{ $currency }} {{ number_format($reportStats['funds_spent'], 2) }}</div></div></td>
    </tr>
</table>

<table class="two-col">
    <tr>
        <td>
            <div class="section">
                <div class="section-title">Report Status Graph</div>
                @forelse($statusCounts as $status => $count)
                    @php
                        $width = $reportStats['total'] > 0 ? min(100, ($count / $reportStats['total']) * 100) : 0;
                    @endphp
                    <div><strong>{{ ucfirst(str_replace('_', ' ', $status)) }}</strong> - {{ number_format($count) }}</div>
                    <div class="bar"><span style="width: {{ number_format($width, 2, '.', '') }}%;"></span></div>
                @empty
                    <div class="muted">No status data found.</div>
                @endforelse
            </div>
        </td>
        <td>
            <div class="section">
                <div class="section-title">Funds Spent by Month</div>
                @foreach($chartData['funds']['labels'] as $index => $label)
                    @php
                        $value = (float) ($chartData['funds']['values'][$index] ?? 0);
                        $width = $maxFunds > 0 ? min(100, ($value / $maxFunds) * 100) : 0;
                    @endphp
                    <div><strong>{{ $label }}</strong> - {{ $currency }} {{ number_format($value, 2) }}</div>
                    <div class="bar"><span style="width: {{ number_format($width, 2, '.', '') }}%; background:#0f766e;"></span></div>
                @endforeach
            </div>
        </td>
    </tr>
</table>

<table class="two-col">
    <tr>
        <td>
            <div class="section">
                <div class="section-title">Evidence Summary</div>
                <div>Total evidence files: <strong>{{ number_format($reportStats['evidence_count']) }}</strong></div>
                <div>Reports with evidence: <strong>{{ number_format($reportStats['with_evidence']) }}</strong></div>
                <div>Reports without evidence: <strong>{{ number_format($reportStats['without_evidence']) }}</strong></div>
            </div>
        </td>
        <td>
            <div class="section">
                <div class="section-title">Deadline</div>
                <div>Next monthly report due: <strong>{{ $monthlyReportDue->format('M d, Y') }}</strong></div>
                <div>
                    {{ $monthlyReportDaysLeft >= 0
                        ? $monthlyReportDaysLeft . ' days left'
                        : abs($monthlyReportDaysLeft) . ' days overdue' }}
                </div>
            </div>
        </td>
    </tr>
</table>

<div class="section">
    <div class="section-title">Activity Report Register</div>
    <table>
        <thead>
        <tr>
            <th>Report</th>
            <th>Period</th>
            <th>Progress</th>
            <th>Funds spent</th>
            <th>Evidence</th>
            <th>Status</th>
            <th>Submitted</th>
        </tr>
        </thead>
        <tbody>
        @forelse($reportRecords as $report)
            <tr>
                <td>{{ $report->title }}<br><span class="muted">{{ $report->workplan?->title ?? 'No workplan selected' }}</span></td>
                <td>{{ $report->reporting_period_start?->format('M d') ?? 'N/A' }} - {{ $report->reporting_period_end?->format('M d, Y') ?? 'N/A' }}</td>
                <td>{{ number_format((float) $report->progress_percent, 1) }}%</td>
                <td>{{ $currency }} {{ number_format((float) $report->funds_spent, 2) }}</td>
                <td>{{ number_format($report->evidence->count()) }}</td>
                <td><span class="pill">{{ ucfirst(str_replace('_', ' ', $report->status)) }}</span></td>
                <td>{{ $report->submitted_at?->format('d M Y') ?? $report->created_at?->format('d M Y') }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">No reports match the selected search.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
</body>
</html>
