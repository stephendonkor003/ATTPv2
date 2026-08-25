<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>ATTP M&amp;E Indicator Report</title>
    <style>
        @page { margin: 18mm 11mm 15mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #243b49; font: 9px/1.42 DejaVu Sans, sans-serif; }
        h1, h2, h3, p { margin-top: 0; }
        h1 { margin-bottom: 4px; color: #fff; font-size: 20px; }
        h2 { margin: 16px 0 7px; padding-bottom: 4px; border-bottom: 1px solid #cbdde3; color: #153b59; font-size: 12px; }
        h3 { margin-bottom: 5px; color: #176b87; font-size: 10px; }
        .header { margin-bottom: 9px; padding: 14px 16px; border-radius: 7px; color: #fff; background: #153b59; }
        .header .eyebrow { margin-bottom: 5px; color: #a9e0eb; font-size: 7px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        .header .sub { margin: 0; color: #d9eef2; font-size: 8px; }
        .header-table, .metric-table, .profile-table, .quality-table { width: 100%; border-collapse: separate; border-spacing: 5px; }
        .header-table td { padding: 0; vertical-align: bottom; }
        .scope { color: #d9eef2; text-align: right; }
        .guardrail { margin-bottom: 9px; padding: 7px 9px; border: 1px solid #b9dfcf; border-radius: 5px; color: #285646; background: #eef8f3; }
        .guardrail strong { color: #16815f; }
        .metric-table { table-layout: fixed; border-spacing: 5px; margin: 0 -5px; }
        .metric { min-height: 51px; padding: 8px; border: 1px solid #d7e5e9; border-radius: 5px; background: #f8fbfc; vertical-align: top; }
        .metric span { display: block; color: #607582; font-size: 6.5px; font-weight: bold; letter-spacing: .45px; text-transform: uppercase; }
        .metric strong { display: block; margin: 3px 0; color: #153b59; font-size: 15px; }
        .metric small { color: #71858f; font-size: 6.5px; }
        .profile-table { border-spacing: 5px; margin: 0 -5px; }
        .profile-table td { width: 25%; padding: 7px; border: 1px solid #dce7eb; border-radius: 4px; vertical-align: top; }
        .profile-table small { display: block; margin-bottom: 2px; color: #71858f; font-size: 6.5px; font-weight: bold; text-transform: uppercase; }
        .profile-table strong { color: #243b49; font-size: 8px; }
        .narrative { margin-bottom: 6px; padding: 8px 9px; border-left: 3px solid #2d8ea3; background: #f3f8fa; }
        .narrative strong { display: block; margin-bottom: 2px; color: #153b59; }
        table.data { width: 100%; margin-bottom: 8px; border-collapse: collapse; page-break-inside: auto; }
        table.data thead { display: table-header-group; }
        table.data tr { page-break-inside: avoid; page-break-after: auto; }
        table.data th { padding: 5px 4px; border: 1px solid #cbdde3; color: #fff; background: #176b87; font-size: 6.2px; letter-spacing: .18px; text-align: left; text-transform: uppercase; }
        table.data td { padding: 5px 4px; border: 1px solid #dce7eb; background: #fff; font-size: 6.7px; vertical-align: top; }
        table.data tbody tr:nth-child(even) td { background: #f8fbfc; }
        .code { display: inline-block; margin-bottom: 2px; padding: 2px 3px; border-radius: 3px; color: #176b87; background: #e7f3f6; font-weight: bold; }
        .status { display: inline-block; padding: 2px 4px; border-radius: 8px; color: #fff; background: #64748b; font-size: 6px; font-weight: bold; white-space: nowrap; }
        .muted { color: #71858f; }
        .quality-table { border-spacing: 4px; margin: 0 -4px; table-layout: fixed; }
        .quality-table td { padding: 6px; border: 1px solid #ead8b9; border-radius: 4px; background: #fffaf0; vertical-align: top; }
        .quality-table strong { display: block; color: #9a5c0e; font-size: 12px; }
        .quality-table span { color: #6f6250; font-size: 6.5px; }
        .method { margin-top: 11px; padding: 8px 10px; border: 1px solid #d7e5e9; border-radius: 5px; background: #f8fbfc; }
        .method p { margin: 0 0 4px; }
        .method p:last-child { margin-bottom: 0; }
        .empty { padding: 15px; border: 1px dashed #c4d7dd; color: #71858f; background: #f8fbfc; text-align: center; }
        .page-break { page-break-before: always; }
        .footer { position: fixed; right: 0; bottom: -10mm; left: 0; color: #71858f; font-size: 6.5px; text-align: center; }
        .page-number:after { content: counter(page); }
    </style>
</head>
<body>
@php
    $indicatorRows = collect($indicatorRows ?? []);
    $projectRows = collect($projectRows ?? []);
    $contributionRows = collect($contributionRows ?? []);
    $evidenceRows = collect($evidenceRows ?? []);
    $summary = $reportSummary ?? [];
    $quality = $quality ?? [];
    $mode = $mode ?? data_get($filters ?? [], 'mode', 'individual');
    $selectedRow = $selectedIndicatorRow ?? ($mode === 'individual' ? $indicatorRows->first() : null);
    $selectedIndicator = data_get($selectedRow, 'indicator');
    $referenceSheet = $selectedIndicator?->approvedReferenceSheet;
    $formatValue = static function (mixed $value, ?string $unit = null): string {
        if ($value === null || $value === '') return 'Not available';
        if (is_bool($value)) return $value ? 'Yes' : 'No';
        if (is_numeric($value)) {
            $formatted = number_format((float) $value, 2);
            return $unit ? $formatted.' '.$unit : $formatted;
        }
        return (string) $value;
    };
    $formatPercent = static fn (mixed $value): string => is_numeric($value)
        ? number_format((float) $value, 1).'%' : 'Not rateable';
    $formatDate = static function (mixed $value): string {
        if (! $value) return 'Not available';
        try { return \Illuminate\Support\Carbon::parse($value)->format('d M Y, H:i'); }
        catch (\Throwable) { return (string) $value; }
    };
    $safeColor = static fn (mixed $color): string => is_string($color) && preg_match('/^#[0-9a-f]{6}$/i', $color)
        ? $color : '#64748b';
@endphp

<div class="footer">ATTP M&amp;E Indicator Report &middot; Approved results only &middot; Page <span class="page-number"></span></div>

<div class="header">
    <table class="header-table">
        <tr>
            <td style="width:66%">
                <div class="eyebrow">Monitoring &amp; Evaluation &middot; Official Indicator Reporting</div>
                <h1>{{ $modeLabel ?? ($mode === 'individual' ? 'Individual indicator report' : 'Consolidated indicator report') }}</h1>
                <p class="sub">Target, actual, performance, source provenance, evidence and reporting quality in one governed report.</p>
            </td>
            <td class="scope">
                <strong>{{ $scopeLabel ?? 'All authorized approved indicator results' }}</strong><br>
                Generated {{ ($generatedAt ?? now())->format('d M Y, H:i T') }}<br>
                By {{ $generatedBy?->name ?? $generatedBy?->full_name ?? 'Authorized platform user' }}
            </td>
        </tr>
    </table>
</div>

<div class="guardrail">
    <strong>Official approved-results output · Approved results only.</strong>
    Draft, submitted, returned, rejected and otherwise unapproved result records are excluded. Approved targets and the approved Indicator Reference Sheet govern this report.
</div>

<table class="metric-table">
    <tr>
        <td class="metric"><span>Indicators</span><strong>{{ number_format((int) data_get($summary, 'indicator_count', 0)) }}</strong><small>{{ number_format((int) data_get($summary, 'reported_indicator_count', 0)) }} with approved results</small></td>
        <td class="metric"><span>Approved contributions</span><strong>{{ number_format((int) data_get($summary, 'approved_contribution_count', 0)) }}</strong><small>Deduplicated result records</small></td>
        <td class="metric"><span>Reporting organizations</span><strong>{{ number_format((int) data_get($summary, 'organization_count', 0)) }}</strong><small>Attributable contributors</small></td>
        <td class="metric"><span>Average attainment</span><strong>{{ $formatPercent(data_get($summary, 'average_achievement')) }}</strong><small>Rateable indicators capped at 100%</small></td>
        <td class="metric"><span>Reporting completeness</span><strong>{{ $formatPercent(data_get($summary, 'reporting_completeness', 0)) }}</strong><small>Reported / expected slots</small></td>
        <td class="metric"><span>Evidence verification</span><strong>{{ $formatPercent(data_get($summary, 'evidence_verification_rate')) }}</strong><small>{{ number_format((int) data_get($summary, 'evidence_count', 0)) }} evidence-link instances</small></td>
    </tr>
</table>

@if($mode === 'individual' && $selectedRow)
    @php($project = $selectedIndicator?->projectComponent)
    <h2>Indicator identity and governed profile</h2>
    <table class="profile-table">
        <tr>
            <td><small>Indicator</small><strong>{{ $selectedIndicator?->indicator_code }} &middot; {{ $selectedIndicator?->name }}</strong></td>
            <td><small>Results area</small><strong>{{ $project?->project_id ?: 'PDO' }} &middot; {{ $project?->name ?: 'Cross-project result' }}</strong></td>
            <td><small>Portfolio / programme</small><strong>{{ $project?->program?->sector?->name ?: 'Not assigned' }} / {{ $project?->program?->name ?: 'Not assigned' }}</strong></td>
            <td><small>Results level</small><strong>{{ $selectedIndicator?->resultsLevelLabel() }}</strong></td>
        </tr>
        <tr>
            <td><small>Baseline</small><strong>{{ $formatValue(data_get($selectedRow, 'baseline'), data_get($selectedRow, 'unit_label')) }}</strong></td>
            <td><small>Approved target</small><strong>{{ $formatValue(data_get($selectedRow, 'target_value'), data_get($selectedRow, 'unit_label')) }}{{ data_get($selectedRow, 'target_text') ? ' · '.data_get($selectedRow, 'target_text') : '' }}</strong></td>
            <td><small>Approved actual</small><strong>{{ $formatValue(data_get($selectedRow, 'actual'), data_get($selectedRow, 'unit_label')) }}</strong></td>
            <td><small>Attainment / variance</small><strong>{{ $formatPercent(data_get($selectedRow, 'achievement_percent')) }} / {{ $formatValue(data_get($selectedRow, 'variance_value', data_get($selectedRow, 'variance'))) }}</strong></td>
        </tr>
        <tr>
            <td><small>Performance</small><strong>{{ data_get($selectedRow, 'classification.label', 'Not rated') }}</strong></td>
            <td><small>Trend</small><strong>{{ data_get($selectedRow, 'trend.label', 'Not available') }}</strong></td>
            <td><small>Coverage</small><strong>{{ data_get($selectedRow, 'reported_organizations', 0) }} / {{ data_get($selectedRow, 'expected_organizations', 0) }} organizations ({{ $formatPercent(data_get($selectedRow, 'reporting_completeness', 0)) }})</strong></td>
            <td><small>Latest approval</small><strong>{{ $formatDate(data_get($selectedRow, 'latest_approved_at')) }}</strong></td>
        </tr>
    </table>

    <div class="narrative"><strong>Definition</strong>{{ $referenceSheet?->definition ?: $selectedIndicator?->definitions ?: 'No approved definition has been recorded.' }}</div>
    @if($referenceSheet?->rationale)<div class="narrative"><strong>Rationale</strong>{{ $referenceSheet->rationale }}</div>@endif
    <table class="profile-table">
        <tr>
            <td><small>Value type / unit</small><strong>{{ str($selectedIndicator?->value_type ?: 'not configured')->headline() }} / {{ data_get($selectedRow, 'unit_label') ?: 'Not configured' }}</strong></td>
            <td><small>Time aggregation</small><strong>{{ data_get($selectedRow, 'time_aggregation_label', 'Not configured') }}</strong></td>
            <td><small>Organization roll-up</small><strong>{{ data_get($selectedRow, 'organization_rollup_label', 'Not configured') }}</strong></td>
            <td><small>Cumulative / evidence</small><strong>{{ $selectedIndicator?->is_cumulative ? 'Cumulative' : 'Period-specific' }} / {{ $selectedIndicator?->requires_evidence ? 'Evidence required' : 'Evidence optional' }}</strong></td>
        </tr>
        <tr>
            <td><small>Reporting frequency</small><strong>{{ $selectedIndicator?->frequency?->name ?: $referenceSheet?->reporting_frequency ?: 'Not configured' }}</strong></td>
            <td><small>Reporting source</small><strong>{{ str($selectedIndicator?->reporting_source ?: 'not configured')->headline() }}</strong></td>
            <td><small>Responsible party</small><strong>{{ $selectedIndicator?->responsiblePerson?->name ?: $selectedIndicator?->responsible_party ?: 'Not assigned' }}</strong></td>
            <td><small>IRS version</small><strong>{{ $referenceSheet?->version ? 'Approved version '.$referenceSheet->version : 'No approved IRS' }}</strong></td>
        </tr>
    </table>
    <div class="narrative"><strong>Calculation and data collection</strong>{{ $referenceSheet?->calculation_method ?: $selectedIndicator?->methodology ?: data_get($selectedRow, 'calculation_note', 'Not configured') }}</div>
    <div class="narrative"><strong>Data sources and means of verification</strong>{{ $referenceSheet?->data_sources ?: $selectedIndicator?->primary_source ?: 'Data source not configured' }} &middot; {{ $referenceSheet?->means_of_verification ?: $selectedIndicator?->meansOfVerification?->title ?: 'Means of verification not configured' }}</div>
@elseif($mode === 'individual')
    <div class="empty">Choose an indicator to build the individual Indicator Report.</div>
@endif

@if($mode === 'consolidated' && $projectRows->isNotEmpty())
    <h2>Results-area consolidation</h2>
    <table class="data">
        <thead><tr><th>Results area</th><th>Indicators</th><th>Reported</th><th>Average attainment</th><th>Completeness</th><th>Performance</th><th>Evidence</th><th>Latest approval</th></tr></thead>
        <tbody>
        @foreach($projectRows as $row)
            <tr>
                <td><span class="code">{{ $row['code'] }}</span><br>{{ $row['name'] }}</td>
                <td>{{ $row['indicator_count'] }} ({{ $row['rated_indicator_count'] }} rateable)</td>
                <td>{{ $row['reported_indicator_count'] }} / {{ $row['indicator_count'] }}</td>
                <td>{{ $formatPercent($row['average_achievement']) }}</td>
                <td>{{ $formatPercent($row['reporting_completeness']) }}</td>
                <td>{{ data_get($row, 'status.label') }}</td>
                <td>{{ $row['verified_evidence_count'] }} / {{ $row['evidence_count'] }} verified links</td>
                <td>{{ $formatDate($row['latest_approved_at']) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<h2>{{ $mode === 'individual' ? 'Indicator performance record' : 'Consolidated indicator performance register' }}</h2>
@if($indicatorRows->isEmpty())
    <div class="empty">No approved indicator data matches this report scope.</div>
@else
    <table class="data">
        <thead><tr><th style="width:23%">Indicator / results area</th><th>Baseline</th><th>Target</th><th>Period actual</th><th>Consolidated actual</th><th>Variance</th><th>Attainment</th><th>Performance / trend</th><th>Coverage</th><th>Evidence / approval</th></tr></thead>
        <tbody>
        @foreach($indicatorRows as $row)
            @php($indicator = $row['indicator'])
            @php($project = $indicator->projectComponent)
            <tr>
                <td><span class="code">{{ $indicator->indicator_code }}</span><br><strong>{{ $indicator->name }}</strong><br><span class="muted">{{ $project?->project_id ?: 'PDO' }} &middot; {{ $project?->name ?: 'Cross-project result' }}</span></td>
                <td>{{ $formatValue($row['baseline'], $row['unit_label']) }}</td>
                <td>{{ $formatValue($row['target_value'], $row['unit_label']) }}{{ $row['target_text'] ? ' · '.$row['target_text'] : '' }}</td>
                <td>{{ $formatValue($row['period_actual'], $row['unit_label']) }}</td>
                <td>{{ $formatValue($row['actual'], $row['unit_label']) }}</td>
                <td>{{ $formatValue($row['variance_value'] ?? $row['variance']) }}</td>
                <td>{{ $formatPercent($row['achievement_percent']) }}</td>
                <td><span class="status" style="background:{{ $safeColor(data_get($row, 'classification.color')) }}">{{ data_get($row, 'classification.label') }}</span><br><span class="muted">{{ data_get($row, 'trend.label') }}</span></td>
                <td>{{ $row['reported_organizations'] }} / {{ $row['expected_organizations'] }}<br>{{ $formatPercent($row['reporting_completeness']) }}</td>
                <td>{{ $row['verified_evidence_count'] }} / {{ $row['evidence_count'] }} verified<br><span class="muted">{{ $formatDate($row['latest_approved_at']) }}</span></td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<h2>Approved source contributions</h2>
@if($contributionRows->isEmpty())
    <div class="empty">No approved source contribution was recorded for this report scope.</div>
@else
    <table class="data">
        <thead><tr><th>Indicator</th><th>Organization / country</th><th>Period</th><th>Approved actual</th><th>Weight inputs</th><th>Data source</th><th>Achievements</th><th>Evidence</th><th>Approved at / result ID</th></tr></thead>
        <tbody>
        @foreach($contributionRows as $row)
            <tr>
                <td><span class="code">{{ $row['indicator_code'] }}</span><br>{{ $row['indicator_name'] }}</td>
                <td>{{ $row['organization'] }}<br><span class="muted">{{ $row['country'] ?: 'Country not recorded' }}</span></td>
                <td>{{ $row['period'] ?: 'Not recorded' }}</td>
                <td>{{ $formatValue($row['actual'], $row['unit']) }}</td>
                <td>{{ $formatValue($row['rollup_numerator']) }} / {{ $formatValue($row['rollup_denominator']) }}</td>
                <td>{{ $row['data_source'] ?: 'Not recorded' }}</td>
                <td>{{ number_format($row['achievement_count']) }}</td>
                <td>{{ $row['verified_evidence_count'] }} / {{ $row['evidence_count'] }} verified</td>
                <td>{{ $formatDate($row['approved_at']) }}<br><span class="muted">{{ $row['source_result_id'] }}</span></td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<h2>Evidence-link register</h2>
@if($evidenceRows->isEmpty())
    <div class="empty">No evidence links were recorded for approved results in this scope.</div>
@else
    <table class="data">
        <thead><tr><th>Indicator</th><th>Organization</th><th>Period</th><th>Evidence title</th><th>Intake source</th><th>Validation status</th><th>Verified</th><th>Evidence / result reference</th></tr></thead>
        <tbody>
        @foreach($evidenceRows as $row)
            <tr>
                <td><span class="code">{{ $row['indicator_code'] }}</span><br>{{ $row['indicator_name'] }}</td>
                <td>{{ $row['organization'] }}</td>
                <td>{{ $row['period'] ?: 'Not recorded' }}</td>
                <td>{{ $row['title'] ?: 'Untitled evidence link' }}</td>
                <td>{{ $row['evidence_source'] ?: $row['source'] }}</td>
                <td>{{ str($row['status'] ?: 'pending')->headline() }}</td>
                <td>{{ $row['verified'] ? 'Yes' : 'No' }}</td>
                <td>{{ $row['evidence_key'] }}<br><span class="muted">{{ $row['source_result_id'] }}</span></td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<h2>Data-quality exceptions</h2>
<table class="quality-table"><tr>
    <td><strong>{{ (int) data_get($quality, 'missing_targets', 0) }}</strong><span>Approved results missing a target</span></td>
    <td><strong>{{ (int) data_get($quality, 'not_reported', 0) }}</strong><span>Indicators not reported</span></td>
    <td><strong>{{ (int) data_get($quality, 'missing_required_evidence', 0) }}</strong><span>Reported indicators missing required evidence</span></td>
    <td><strong>{{ (int) data_get($quality, 'incomplete_reporting', 0) }}</strong><span>Indicators below full reporting completeness</span></td>
    <td><strong>{{ (int) data_get($quality, 'weighted_values_without_weights', 0) }}</strong><span>Weighted values missing numerator/denominator</span></td>
    <td><strong>{{ (int) data_get($quality, 'non_additive_or_qualitative', 0) }}</strong><span>Qualitative or non-additive indicators</span></td>
</tr></table>

<div class="method">
    <h3>Calculation and interpretation controls</h3>
    <p><strong>Inclusion:</strong> only finally approved, deduplicated indicator results and approved targets are included. Evidence validation is shown as a quality measure; it does not silently remove an otherwise approved result.</p>
    <p><strong>Aggregation:</strong> one value is resolved per organization across time, then the indicator-configured organization roll-up is applied. Qualitative and non-additive results remain attributable. Unlike indicator units are never added together.</p>
    <p><strong>Scorecards:</strong> average attainment is unweighted across rateable indicators, with each indicator capped at 100%. Country and thematic filters qualify whole approved result records; they do not apportion numeric actuals.</p>
    <p><strong>Counts:</strong> participant and beneficiary totals are reporting instances, not necessarily unique people. Evidence totals are evidence-link instances, not globally unique physical files.</p>
</div>
</body>
</html>
