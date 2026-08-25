<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>ATTP M&amp;E Consolidation - {{ $levelLabel ?? str($filters['level'] ?? 'indicator')->headline() }}</title>
    <style>
        @page { size: A4 landscape; margin: 11mm 10mm 13mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #17343e; font-family: DejaVu Sans, sans-serif; font-size: 7.2px; line-height: 1.35; }
        h1, h2, h3, p { margin: 0; }
        .header { padding: 13px 15px; border-radius: 8px; background: #075c7a; color: #fff; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { border: 0; padding: 0; vertical-align: top; }
        .header-table .right { width: 38%; text-align: right; }
        .eyebrow { color: #cbe4eb; font-size: 6.6px; font-weight: bold; letter-spacing: .8px; text-transform: uppercase; }
        h1 { margin-top: 3px; font-size: 16px; line-height: 1.15; }
        .subtitle { max-width: 600px; margin-top: 4px; color: #e2f0f4; font-size: 7.4px; }
        .scope-label { display: inline-block; max-width: 355px; padding: 6px 8px; border: 1px solid #70a6b7; border-radius: 6px; background: #176c87; color: #fff; font-size: 6.7px; text-align: left; }
        .scope-label strong { display: block; margin-bottom: 2px; color: #cbe4eb; font-size: 5.8px; text-transform: uppercase; }
        .policy { margin-top: 7px; padding: 7px 9px; border: 1px solid #bdd7de; border-radius: 6px; background: #f0f6f8; color: #3c6470; }
        .policy strong { color: #214954; }
        .section { margin-top: 8px; }
        .section-title { padding: 6px 8px; border: 1px solid #dce7ea; border-bottom: 0; border-radius: 6px 6px 0 0; background: #f3f7f8; }
        .section-title h2 { color: #17343e; font-size: 9px; }
        .section-title p { margin-top: 2px; color: #657980; font-size: 6.4px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 4.5px; border: 1px solid #dce7ea; vertical-align: top; }
        th { background: #f3f7f8; color: #526a73; font-size: 5.8px; font-weight: bold; letter-spacing: .25px; text-align: left; text-transform: uppercase; }
        td { background: #fff; }
        tr:nth-child(even) td { background: #fbfcfc; }
        .metrics { width: 100%; margin-top: 7px; border-spacing: 4px 0; border-collapse: separate; table-layout: fixed; }
        .metrics td { position: relative; height: 51px; padding: 7px; border: 1px solid #dce7ea; border-radius: 6px; background: #fff; }
        .metric-label { color: #657980; font-size: 5.6px; font-weight: bold; letter-spacing: .25px; text-transform: uppercase; }
        .metric-value { display: block; margin-top: 3px; color: #17343e; font-size: 13px; font-weight: bold; line-height: 1; }
        .metric-note { display: block; margin-top: 4px; color: #657980; font-size: 5.7px; }
        .quality { width: 100%; table-layout: fixed; }
        .quality td { padding: 6px; }
        .quality strong { display: block; margin-top: 3px; font-size: 10px; }
        .quality .clear strong { color: #187459; }
        .quality .issue { background: #fffbf3; }
        .quality .issue strong { color: #a56a17; }
        .code { display: inline-block; margin-bottom: 2px; padding: 2px 3px; border-radius: 3px; background: #eaf4f7; color: #075c7a; font-size: 5.7px; font-weight: bold; }
        .title { display: block; color: #17343e; font-weight: bold; }
        .meta { display: block; margin-top: 2px; color: #657980; font-size: 5.8px; }
        .value { color: #075c7a; font-weight: bold; }
        .status { display: inline-block; padding: 2px 4px; border-radius: 8px; color: #fff; font-size: 5.6px; font-weight: bold; }
        .method { padding: 7px 8px; border: 1px solid #dce7ea; background: #fbfcfc; }
        .method-table { width: 100%; table-layout: fixed; }
        .method-table td { width: 50%; padding: 7px; }
        .method-table strong { display: block; margin-bottom: 2px; color: #17343e; }
        .warning { margin: 5px 0; padding: 5px 6px; border: 1px solid #ead5b2; border-radius: 4px; background: #fffbf3; color: #73531f; }
        .detail { margin: 6px 0 8px; page-break-inside: avoid; }
        .detail-head { padding: 5px 6px; border: 1px solid #cfdfe3; border-bottom: 0; background: #edf5f7; color: #214954; font-weight: bold; }
        .source-table th, .source-table td { padding: 3px 3.5px; font-size: 5.7px; }
        .no-data { padding: 18px; border: 1px solid #dce7ea; color: #657980; text-align: center; }
        .page-break { page-break-before: always; }
        .avoid-break { page-break-inside: avoid; }
        .footer { position: fixed; right: 0; bottom: -9mm; left: 0; color: #71858c; font-size: 5.8px; text-align: center; }
        .footer .page-number:after { content: counter(page); }
    </style>
</head>
<body>
@php
    $engineSummary = $engineSummary ?? ($summary ?? data_get($data ?? [], 'summary', []));
    $quality = $quality ?? data_get($data ?? [], 'quality', []);
    $indicatorRows = collect($indicatorRows ?? ($indicator_rows ?? data_get($data ?? [], 'indicator_rows', [])));
    $projectRows = collect($projectRows ?? ($project_rows ?? data_get($data ?? [], 'project_rows', [])));
    $filters = $filters ?? [];
    $currentLevel = (string) ($filters['level'] ?? 'indicator');
    $isProjectLevel = $currentLevel === 'project';
    $scopeLabel = $scopeLabel ?? 'All authorized approved results';
    $generatedAt = $generatedAt ?? now();
    $qualityTotal = collect($quality)->sum(fn ($value) => (int) $value);
    $formatValue = static function (mixed $value, ?string $unit = null, int $decimals = 2): string {
        if ($value === null || $value === '') return 'Not available';
        if (is_bool($value)) return $value ? 'Yes' : 'No';
        if (is_numeric($value)) {
            $formatted = number_format((float) $value, $decimals);
            return $unit ? $formatted.' '.$unit : $formatted;
        }
        return (string) $value;
    };
    $formatDate = static function (mixed $value): string {
        if (! $value) return 'No approval recorded';
        try {
            return $value instanceof \DateTimeInterface
                ? $value->format('d M Y, H:i')
                : \Illuminate\Support\Carbon::parse($value)->format('d M Y, H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    };
@endphp

<div class="footer">ATTP M&amp;E Consolidations Engine &middot; Approved results only &middot; Page <span class="page-number"></span></div>

<header class="header">
    <table class="header-table">
        <tr>
            <td>
                <span class="eyebrow">Monitoring, Evaluation and Learning</span>
                <h1>M&amp;E Consolidations Engine</h1>
                <p class="subtitle">{{ $levelLabel ?? str($currentLevel)->headline() }} consolidation with approved targets, governed calculations and auditable source detail.</p>
            </td>
            <td class="right">
                <div class="scope-label"><strong>Report scope</strong>{{ $scopeLabel }}<br>Generated {{ $generatedAt->format('d M Y, H:i') }} by {{ $generatedBy?->name ?: 'Authorized user' }}</div>
            </td>
        </tr>
    </table>
</header>

<div class="policy">
    <strong>Official approved-only consolidation.</strong>
    Draft, submitted, returned, reviewed and merely verified records are excluded. Indicator calculations preserve the configured unit and aggregation rules. Project performance is an unweighted average after capping each rateable indicator's scorecard contribution at 100%; raw values from unlike units are never summed.
</div>

<table class="metrics">
    <tr>
        <td><span class="metric-label">{{ $isProjectLevel ? 'Projects / results areas' : 'Indicators in scope' }}</span><span class="metric-value">{{ number_format((int) ($isProjectLevel ? ($engineSummary['results_area_count'] ?? 0) : ($engineSummary['indicator_count'] ?? 0))) }}</span><span class="metric-note">{{ $isProjectLevel ? 'Comparable scorecards' : number_format((int) ($engineSummary['reported_indicator_count'] ?? 0)).' reported' }}</span></td>
        <td><span class="metric-label">Approved contributions</span><span class="metric-value">{{ number_format((int) ($engineSummary['approved_contribution_count'] ?? 0)) }}</span><span class="metric-note">Deduplicated official records</span></td>
        <td><span class="metric-label">Average indicator attainment</span><span class="metric-value">{{ ($engineSummary['average_achievement'] ?? null) === null ? '—' : number_format((float) $engineSummary['average_achievement'],1).'%' }}</span><span class="metric-note">Each indicator capped at 100%</span></td>
        <td><span class="metric-label">Reporting completeness</span><span class="metric-value">{{ number_format((float) ($engineSummary['reporting_completeness'] ?? 0),1) }}%</span><span class="metric-note">{{ number_format((int) ($engineSummary['organization_count'] ?? 0)) }} reporting organizations</span></td>
        <td><span class="metric-label">Evidence-link verification</span><span class="metric-value">{{ ($engineSummary['evidence_verification_rate'] ?? null) === null ? '—' : number_format((float) $engineSummary['evidence_verification_rate'],1).'%' }}</span><span class="metric-note">{{ number_format((int) ($engineSummary['verified_evidence_count'] ?? 0)) }} / {{ number_format((int) ($engineSummary['evidence_count'] ?? 0)) }} links verified</span></td>
        <td><span class="metric-label">Quality exceptions</span><span class="metric-value">{{ number_format($qualityTotal) }}</span><span class="metric-note">Disclosed below</span></td>
    </tr>
</table>

<section class="section avoid-break">
    <div class="section-title"><h2>Consolidation methodology</h2><p>How the engine produces traceable indicator results and comparable project scorecards.</p></div>
    <table class="method-table">
        <tr>
            <td><strong>Indicator-level calculation</strong>Each indicator uses its configured time aggregation and organization roll-up. Targets, actuals, qualitative values, units, contributors and evidence remain attributable.</td>
            <td><strong>Project-level calculation</strong>Project performance is the unweighted average after each rated indicator's contribution is capped at 100%. Completeness, contributions, evidence links and participant/beneficiary instances are separate supporting counts. No mixed-unit actual total is produced.</td>
        </tr>
    </table>
</section>

<section class="section avoid-break">
    <div class="section-title"><h2>Consolidation quality controls</h2><p>Exceptions are visible rather than silently coerced into a project or indicator result.</p></div>
    <table class="quality">
        <tr>
            @foreach([
                ['missing_targets','Missing targets'],
                ['not_reported','Not reported'],
                ['non_additive_or_qualitative','Non-additive / qualitative'],
                ['missing_required_evidence','Required evidence missing'],
                ['incomplete_reporting','Incomplete reporting'],
                ['weighted_values_without_weights','Missing valid weights'],
            ] as [$key,$label])
                @php
                    $qualityValue = (int) ($quality[$key] ?? 0);
                @endphp
                <td class="{{ $qualityValue > 0 ? 'issue' : 'clear' }}">{{ $label }}<strong>{{ number_format($qualityValue) }}</strong></td>
            @endforeach
        </tr>
    </table>
</section>

@if($isProjectLevel)
    <section class="section">
        <div class="section-title"><h2>Project-level consolidation register</h2><p>Project scorecards and the indicator evidence behind each score.</p></div>
        @if($projectRows->isEmpty())
            <div class="no-data">No project scorecards match this approved-results scope.</div>
        @else
            <table>
                <thead><tr><th style="width:21%">Project / results area</th><th>Indicator coverage</th><th>Average attainment</th><th>Scorecard status</th><th>Reporting completeness</th><th>Contributors</th><th>Evidence links</th><th>Participant / beneficiary instances</th><th>Last approval</th></tr></thead>
                <tbody>
                @foreach($projectRows as $row)
                    @php
                        $status = $row['status'] ?? ['label'=>'Not rated','color'=>'#64748b'];
                        $statusColor = preg_match('/^#[0-9a-f]{6}$/i', (string) ($status['color'] ?? '')) ? $status['color'] : '#64748b';
                    @endphp
                    <tr>
                        <td><span class="code">{{ $row['code'] }}</span><span class="title">{{ $row['name'] }}</span><span class="meta">{{ $row['portfolio'] ?: 'Portfolio not recorded' }}{{ $row['program'] ? ' · '.$row['program'] : '' }}</span></td>
                        <td><strong>{{ number_format((int) $row['reported_indicator_count']) }} / {{ number_format((int) $row['indicator_count']) }} reported</strong><span class="meta">{{ number_format((int) $row['rated_indicator_count']) }} rateable &middot; {{ number_format((int) $row['not_rated_count']) }} unrated</span></td>
                        <td><span class="value">{{ $row['average_achievement'] === null ? 'Not rateable' : number_format((float) $row['average_achievement'],1).'%' }}</span><span class="meta">Average capped indicator attainment</span></td>
                        <td><span class="status" style="background:{{ $statusColor }}">{{ $status['label'] }}</span><span class="meta">{{ number_format((int) $row['on_track_count']) }} on track &middot; {{ number_format((int) $row['attention_count']) }} attention</span></td>
                        <td>{{ number_format((float) $row['reporting_completeness'],1) }}%<span class="meta">{{ number_format((int) $row['approved_contribution_count']) }} approved contributions</span></td>
                        <td>{{ number_format((int) $row['organization_count']) }}<span class="meta">{{ collect($row['organizations'] ?? [])->take(4)->join(', ') ?: 'None' }}</span></td>
                        <td>{{ number_format((int) $row['verified_evidence_count']) }} / {{ number_format((int) $row['evidence_count']) }} verified</td>
                        <td>{{ number_format((int) $row['beneficiary_count']) }}<span class="meta">F {{ number_format((int) $row['female_beneficiaries']) }} &middot; M {{ number_format((int) $row['male_beneficiaries']) }}</span></td>
                        <td>{{ $formatDate($row['latest_approved_at'] ?? null) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>

    @foreach($projectRows as $row)
        <section class="detail">
            <div class="detail-head">{{ $row['code'] }} &middot; {{ $row['name'] }} — indicator breakdown</div>
            <div class="warning"><strong>No mixed-unit total:</strong> {{ $row['calculation_note'] }}</div>
            <table class="source-table">
                <thead><tr><th style="width:27%">Indicator</th><th>Unit / type</th><th>Target</th><th>Approved actual</th><th>Attainment</th><th>Performance</th><th>Reporting</th><th>Evidence links</th><th>Aggregation rules</th></tr></thead>
                <tbody>
                @foreach($row['indicator_rows'] as $indicatorRow)
                    @php
                        $indicator = $indicatorRow['indicator'];
                        $unit = $indicatorRow['unit_label'] ?? ($indicator?->unit?->symbol ?: $indicator?->unit?->name);
                        $status = $indicatorRow['classification'] ?? ['label'=>'Not rated','color'=>'#64748b'];
                        $statusColor = preg_match('/^#[0-9a-f]{6}$/i', (string) ($status['color'] ?? '')) ? $status['color'] : '#64748b';
                    @endphp
                    <tr>
                        <td><span class="code">{{ $indicator?->indicator_code }}</span><span class="title">{{ $indicator?->name }}</span></td>
                        <td>{{ $unit ?: 'No unit' }}<span class="meta">{{ str($indicator?->value_type ?: 'number')->headline() }}</span></td>
                        <td>{{ filled($indicatorRow['target_text'] ?? null) ? $indicatorRow['target_text'] : $formatValue($indicatorRow['target_value'] ?? null,$unit) }}</td>
                        <td>{{ $formatValue($indicatorRow['actual'] ?? null,$unit) }}</td>
                        <td>{{ ($indicatorRow['achievement_percent'] ?? null) === null ? 'Not rateable' : number_format((float) $indicatorRow['achievement_percent'],1).'%' }}</td>
                        <td><span class="status" style="background:{{ $statusColor }}">{{ $status['label'] }}</span></td>
                        <td>{{ number_format((int) ($indicatorRow['reported_organizations'] ?? 0)) }} / {{ number_format((int) ($indicatorRow['expected_organizations'] ?? 0)) }}<span class="meta">{{ number_format((float) ($indicatorRow['reporting_completeness'] ?? 0),1) }}%</span></td>
                        <td>{{ number_format((int) ($indicatorRow['verified_evidence_count'] ?? 0)) }} / {{ number_format((int) ($indicatorRow['evidence_count'] ?? 0)) }}</td>
                        <td>{{ $indicatorRow['organization_rollup_label'] ?? 'Not configured' }}<span class="meta">{{ $indicatorRow['time_aggregation_label'] ?? $indicator?->aggregationMethodLabel() }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endforeach
@else
    <section class="section">
        <div class="section-title"><h2>Indicator-level consolidation register</h2><p>Approved targets, consolidated actuals, configured methods, performance and reporting controls.</p></div>
        @if($indicatorRows->isEmpty())
            <div class="no-data">No indicators match this approved-results scope.</div>
        @else
            <table>
                <thead><tr><th style="width:22%">Indicator</th><th>Project / level</th><th>Aggregation rules</th><th>Baseline / target</th><th>Approved actual</th><th>Attainment / variance</th><th>Performance</th><th>Reporting</th><th>Evidence links / achievements</th><th>Last approval</th></tr></thead>
                <tbody>
                @foreach($indicatorRows as $row)
                    @php
                        $indicator = $row['indicator'];
                        $unit = $row['unit_label'] ?? ($indicator?->unit?->symbol ?: $indicator?->unit?->name);
                        $status = $row['classification'] ?? ['label'=>'Not rated','color'=>'#64748b'];
                        $statusColor = preg_match('/^#[0-9a-f]{6}$/i', (string) ($status['color'] ?? '')) ? $status['color'] : '#64748b';
                    @endphp
                    <tr>
                        <td><span class="code">{{ $indicator?->indicator_code ?: 'No code' }}</span><span class="title">{{ $indicator?->name ?: 'Indicator unavailable' }}</span><span class="meta">{{ str($indicator?->value_type ?: 'number')->headline() }}{{ $unit ? ' · '.$unit : '' }}</span></td>
                        <td>{{ $indicator?->projectComponent?->project_id ?: 'PDO' }}<span class="meta">{{ $indicator?->projectComponent?->name ?: 'Project Development Objective / cross-project result' }}</span><span class="meta">{{ $indicator?->resultsLevelLabel() }}</span></td>
                        <td>{{ $row['organization_rollup_label'] ?? 'Not configured' }}<span class="meta">Organizations</span>{{ $row['time_aggregation_label'] ?? $indicator?->aggregationMethodLabel() }}<span class="meta">Time periods</span></td>
                        <td>{{ $formatValue($row['baseline'] ?? null,$unit) }}<span class="meta">Baseline</span><span class="value">{{ filled($row['target_text'] ?? null) ? $row['target_text'] : $formatValue($row['target_value'] ?? null,$unit) }}</span><span class="meta">Approved target</span></td>
                        <td><span class="value">{{ $formatValue($row['actual'] ?? null,$unit) }}</span><span class="meta">{{ $indicator?->is_cumulative ? 'Cumulative' : 'Period-specific' }}</span></td>
                        <td>{{ ($row['achievement_percent'] ?? null) === null ? 'Not rateable' : number_format((float) $row['achievement_percent'],1).'%' }}<span class="meta">Variance {{ $formatValue($row['variance_value'] ?? $row['variance'] ?? null,$unit) }}</span><span class="meta">{{ data_get($row,'trend.label','No trend') }}</span></td>
                        <td><span class="status" style="background:{{ $statusColor }}">{{ $status['label'] }}</span></td>
                        <td>{{ number_format((int) ($row['reported_organizations'] ?? 0)) }} / {{ number_format((int) ($row['expected_organizations'] ?? 0)) }}<span class="meta">{{ number_format((float) ($row['reporting_completeness'] ?? 0),1) }}% complete</span></td>
                        <td>{{ number_format((int) ($row['verified_evidence_count'] ?? 0)) }} / {{ number_format((int) ($row['evidence_count'] ?? 0)) }} links verified<span class="meta">{{ number_format((int) ($row['achievement_count'] ?? 0)) }} achievements &middot; {{ number_format((int) ($row['beneficiary_count'] ?? 0)) }} participant/beneficiary instances</span></td>
                        <td>{{ $formatDate($row['latest_approved_at'] ?? null) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>

    @foreach($indicatorRows as $row)
        @php
            $indicator = $row['indicator'];
            $unit = $row['unit_label'] ?? ($indicator?->unit?->symbol ?: $indicator?->unit?->name);
        @endphp
        <section class="detail">
            <div class="detail-head">{{ $indicator?->indicator_code }} &middot; {{ $indicator?->name }} — approved source contributions</div>
            <div class="method">{{ $row['calculation_note'] ?? 'Calculated from approved indicator results only.' }} Organization roll-up: {{ $row['organization_rollup_label'] ?? 'Not configured' }}. Time aggregation: {{ $row['time_aggregation_label'] ?? $indicator?->aggregationMethodLabel() }}.</div>
            @if(collect($row['source_contributions'] ?? [])->isEmpty())
                <div class="no-data">No approved source contribution is available for this indicator in the selected scope.</div>
            @else
                <table class="source-table">
                    <thead><tr><th>Organization</th><th>Country</th><th>Period</th><th>Approved contribution</th><th>Weight numerator</th><th>Weight denominator</th><th>Data source</th><th>Achievements</th><th>Evidence links</th><th>Approved at</th></tr></thead>
                    <tbody>
                    @foreach($row['source_contributions'] as $source)
                        <tr>
                            <td><strong>{{ $source['organization'] }}</strong></td>
                            <td>{{ $source['country'] ?: 'Not recorded' }}</td>
                            <td>{{ $source['period'] ?: 'Not recorded' }}</td>
                            <td>{{ $formatValue($source['actual'] ?? null,$unit) }}</td>
                            <td>{{ ($source['rollup_numerator'] ?? null) === null ? 'Not required' : $formatValue($source['rollup_numerator']) }}</td>
                            <td>{{ ($source['rollup_denominator'] ?? null) === null ? 'Not required' : $formatValue($source['rollup_denominator']) }}</td>
                            <td>{{ $source['data_source'] ?: 'Not recorded' }}</td>
                            <td>{{ number_format((int) ($source['achievement_count'] ?? 0)) }}</td>
                            <td>{{ number_format((int) ($source['evidence_count'] ?? 0)) }}</td>
                            <td>{{ $formatDate($source['approved_at'] ?? null) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    @endforeach
@endif
</body>
</html>
