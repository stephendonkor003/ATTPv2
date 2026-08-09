<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 12px 12px 42px; }
        body { margin: 0; color: #17343e; font-family: DejaVu Sans,Arial,sans-serif; font-size: 7px; line-height: 1.3; }
        .header { padding: 10px 12px; border-bottom: 4px solid #73a9b6; background: #075c7a; color: #fff; }
        .header-table,.meta-table,.summary-table,.results-table,.footer-table { width: 100%; border-collapse: collapse; }
        .title { margin: 0 0 3px; color: #fff; font-size: 17px; font-weight: 800; }
        .subtitle { color: #cde9ef; font-size: 8px; font-weight: 700; }
        .header-side { color: #d9edf1; font-size: 7px; text-align: right; }
        .section { margin-top: 8px; }
        .section-title { padding: 5px 7px; background: #075c7a; color: #fff; font-size: 7px; font-weight: 800; letter-spacing: .35px; text-transform: uppercase; }
        .section-note { padding: 4px 7px; border: 1px solid #d7e3e6; border-top: 0; background: #f7fafb; color: #647980; font-size: 6px; }
        .meta-table td,.summary-table td { padding: 5px 6px; border: 1px solid #d7e3e6; vertical-align: top; }
        .meta-table td { width: 25%; }
        .meta-label,.summary-label { display: block; color: #647980; font-size: 5.8px; font-weight: 800; text-transform: uppercase; }
        .meta-value { display: block; margin-top: 2px; color: #17343e; font-size: 7px; font-weight: 800; }
        .summary-table td { width: 16.666%; }
        .summary-value { display: block; margin-top: 2px; color: #075c7a; font-size: 10px; font-weight: 900; }
        .summary-meta { display: block; margin-top: 1px; color: #647980; font-size: 5.4px; }
        .guard { margin-top: 6px; padding: 5px 7px; border: 1px solid #b8decf; background: #edf8f3; color: #176348; }
        .objective { margin-top: 6px; padding: 6px 7px; border-left: 3px solid #075c7a; background: #eef5f7; }
        .results-table { table-layout: fixed; page-break-inside: auto; }
        .results-table thead { display: table-header-group; }
        .results-table tr { page-break-inside: avoid; }
        .results-table th { padding: 4px 3px; border: 1px solid #075c7a; background: #075c7a; color: #fff; font-size: 5.4px; font-weight: 800; text-align: left; text-transform: uppercase; vertical-align: middle; }
        .results-table td { padding: 4px 3px; border: 1px solid #d7e3e6; color: #294750; font-size: 5.7px; overflow-wrap: anywhere; vertical-align: top; }
        .results-table tbody tr:nth-child(even) td { background: #f8fafb; }
        .indicator { width: 21%; }
        .context { width: 12%; }
        .num { text-align: right; white-space: nowrap; }
        .code { color: #075c7a; font-weight: 900; }
        .muted { display: block; margin-top: 1px; color: #6b7e85; font-size: 5.1px; }
        .status { font-weight: 800; }
        .empty { padding: 18px !important; color: #647980 !important; text-align: center; }
        .footer { position: fixed; right: 0; bottom: -32px; left: 0; padding: 6px 10px 5px; border-top: 3px solid #73a9b6; background: #05465d; color: #d7ebef; font-size: 5.8px; }
        .footer-table td { width: 33.333%; border: 0; padding: 0; vertical-align: middle; }
        .footer-brand { color: #fff; font-weight: 900; }
        .footer-context { color: #cde9ef; text-align: center; }
        .footer-page { color: #fff; font-weight: 800; text-align: right; }
        .page-number:after { content: "Page " counter(page) " of " counter(pages); }
    </style>
</head>
<body>
@php
    $formatValue = static function (mixed $value, int $decimals = 2): string {
        if ($value === null || $value === '') return '—';
        if (is_bool($value)) return $value ? 'Yes' : 'No';
        if (is_numeric($value)) return number_format((float) $value, $decimals);
        return (string) $value;
    };
    $reportedRate = $summary['indicator_count'] > 0
        ? ($summary['reported_indicator_count'] / $summary['indicator_count']) * 100
        : 0;
@endphp

<div class="header">
    <table class="header-table">
        <tr>
            <td>
                <div class="title">{{ $reportTitle }}</div>
                <div class="subtitle">ATTP Results Framework · Official approved-results report</div>
            </td>
            <td class="header-side">
                Generated {{ now()->format('d M Y, H:i') }}<br>
                By <strong>{{ $generatedBy?->name ?: 'ATTP Secretariat' }}</strong>
            </td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Report context</div>
    <div class="section-note">The report scope below is identical to the scope applied on the dashboard.</div>
    <table class="meta-table">
        <tr>
            <td><span class="meta-label">Framework</span><span class="meta-value">{{ $framework?->code }} · {{ $framework?->version }}</span></td>
            <td><span class="meta-label">Reporting scope</span><span class="meta-value">{{ $scopeLabel }}</span></td>
            <td><span class="meta-label">Target benchmark</span><span class="meta-value">Project Year {{ $projectYear }}</span></td>
            <td><span class="meta-label">Geographic scope</span><span class="meta-value">{{ $filters['country'] ?: 'All countries' }}</span></td>
        </tr>
    </table>
    <div class="objective"><strong>Project Development Objective:</strong> {{ $framework?->project_development_objective }}</div>
    <div class="guard"><strong>Official-data guardrail:</strong> only final Secretariat-approved records are included. Draft, submitted, under-review, returned and rejected data are excluded.</div>
</div>

<div class="section">
    <div class="section-title">Executive performance summary</div>
    <table class="summary-table">
        <tr>
            <td><span class="summary-label">Indicators</span><span class="summary-value">{{ number_format($summary['indicator_count']) }}</span><span class="summary-meta">{{ $summary['pdo_count'] }} PDO level</span></td>
            <td><span class="summary-label">Reported</span><span class="summary-value">{{ number_format($reportedRate, 1) }}%</span><span class="summary-meta">{{ $summary['reported_indicator_count'] }} with approved data</span></td>
            <td><span class="summary-label">Avg. achievement</span><span class="summary-value">{{ $summary['average_achievement'] === null ? '—' : number_format($summary['average_achievement'], 1).'%' }}</span><span class="summary-meta">Rated indicators only</span></td>
            <td><span class="summary-label">On track</span><span class="summary-value">{{ number_format($summary['on_track_count']) }}</span><span class="summary-meta">Achieved or on track</span></td>
            <td><span class="summary-label">Completeness</span><span class="summary-value">{{ number_format($summary['average_completeness'], 1) }}%</span><span class="summary-meta">Expected reporters</span></td>
            <td><span class="summary-label">Evidence verified</span><span class="summary-value">{{ $summary['evidence_verification_rate'] === null ? '—' : number_format($summary['evidence_verification_rate'], 1).'%' }}</span><span class="summary-meta">{{ $summary['verified_evidence_count'] }}/{{ $summary['evidence_count'] }} files</span></td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Detailed approved-results register</div>
    <div class="section-note">Period actual, cumulative actual, trend, target achievement and reporting coverage are shown separately.</div>
    <table class="results-table">
        <thead>
            <tr>
                <th class="indicator">Indicator</th>
                <th class="context">Level / component</th>
                <th class="num">Baseline</th>
                <th class="num">Target</th>
                <th class="num">Scope actual</th>
                <th class="num">Cumulative</th>
                <th>Trend</th>
                <th class="num">Achievement</th>
                <th>Performance</th>
                <th class="num">Records / evidence</th>
                <th class="num">Completeness</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td><span class="code">{{ $row['indicator']->indicator_code }}</span><br>{{ $row['indicator']->name }}<span class="muted">{{ str($row['indicator']->reporting_source)->replace('_', ' ')->headline() }}</span></td>
                    <td>{{ $row['indicator']->resultsLevelLabel() }}<span class="muted">{{ $row['indicator']->projectComponent?->name ?: 'Project Development Objective' }}</span></td>
                    <td class="num">{{ $formatValue($row['baseline']) }}</td>
                    <td class="num">{{ $row['target_text'] ?: $formatValue($row['target_value']) }}</td>
                    <td class="num">{{ $formatValue($row['period_actual']) }}</td>
                    <td class="num">{{ $formatValue($row['cumulative_actual']) }}</td>
                    <td>{{ $row['trend']['label'] }}</td>
                    <td class="num">{{ $row['achievement_percent'] === null ? '—' : number_format($row['achievement_percent'], 1).'%' }}<span class="muted">Var. {{ $formatValue($row['variance']) }}</span></td>
                    <td class="status" style="color:{{ $row['classification']['color'] }}">{{ $row['classification']['label'] }}</td>
                    <td class="num">{{ $row['result_count'] }} approved<span class="muted">{{ $row['verified_evidence_count'] }}/{{ $row['evidence_count'] }} evidence</span></td>
                    <td class="num">{{ number_format($row['reporting_completeness'], 1) }}%<span class="muted">{{ $row['reported_organizations'] }}/{{ $row['expected_organizations'] }} reporters</span></td>
                </tr>
            @empty
                <tr><td colspan="11" class="empty">No framework indicators match the selected report scope.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="footer">
    <table class="footer-table">
        <tr>
            <td class="footer-brand">Africa Think Tank Platform</td>
            <td class="footer-context">{{ $reportTitle }} · {{ $scopeLabel }}</td>
            <td class="footer-page"><span class="page-number"></span></td>
        </tr>
    </table>
</div>
</body>
</html>
