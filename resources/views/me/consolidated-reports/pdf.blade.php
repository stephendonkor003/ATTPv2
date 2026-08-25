<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>ATTP Consolidated M&amp;E Performance Report</title>
    <style>
        @page { margin: 19mm 12mm 17mm; }
        body { margin: 0; color: #17343e; font-family: DejaVu Sans, sans-serif; font-size: 8px; line-height: 1.42; }
        .header { width: 100%; margin-bottom: 10px; border-collapse: collapse; }
        .header td { padding: 0; border: 0; vertical-align: top; }
        .brand { width: 62px; height: 46px; border-radius: 7px; background: #075c7a; color: #fff; font-size: 17px; font-weight: bold; line-height: 46px; text-align: center; }
        .heading { padding-left: 10px !important; }
        .eyebrow { color: #4f7783; font-size: 7px; font-weight: bold; letter-spacing: .8px; text-transform: uppercase; }
        h1 { margin: 2px 0 3px; color: #075c7a; font-size: 17px; line-height: 1.2; }
        .subtitle { color: #60777f; font-size: 8px; }
        .document-meta { width: 210px; color: #60777f; font-size: 7px; line-height: 1.55; text-align: right; }
        .rule { height: 3px; margin: 0 0 9px; background: #075c7a; }
        .scope { margin-bottom: 9px; padding: 7px 9px; border: 1px solid #c9dde2; background: #f1f7f8; color: #3c606b; }
        .scope strong { color: #075c7a; }
        .summary { width: 100%; margin: 0 0 10px; border-collapse: separate; border-spacing: 5px 0; }
        .summary td { width: 20%; padding: 7px 8px; border: 1px solid #d9e6e9; border-radius: 6px; background: #fbfcfc; }
        .summary span { display: block; color: #6a7f86; font-size: 6.8px; font-weight: bold; text-transform: uppercase; }
        .summary strong { display: block; margin-top: 3px; color: #17343e; font-size: 13px; }
        h2 { margin: 11px 0 5px; color: #17343e; font-size: 10px; }
        .section-note { margin: -3px 0 6px; color: #687d84; font-size: 7px; }
        table.data { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.data th { padding: 5px; border: 1px solid #bdd1d7; background: #075c7a; color: #fff; font-size: 6.7px; text-align: left; text-transform: uppercase; }
        table.data td { padding: 5px; border: 1px solid #d8e3e6; vertical-align: top; overflow-wrap: break-word; }
        table.data tbody tr:nth-child(even) td { background: #f8fafb; }
        .code { color: #075c7a; font-size: 7px; font-weight: bold; }
        .muted { color: #6d8188; font-size: 6.7px; }
        .num { text-align: right; }
        .value { color: #075c7a; font-size: 9px; font-weight: bold; }
        .qualitative { margin-bottom: 3px; padding: 3px 4px; border-left: 2px solid #6b63a8; background: #f7f5fb; }
        .qualitative strong { display: block; color: #514b7f; font-size: 6.5px; }
        .warning { color: #966016; font-weight: bold; }
        .page-break { page-break-before: always; }
        .footer { position: fixed; right: 0; bottom: -11mm; left: 0; padding-top: 4px; border-top: 1px solid #d6e2e5; color: #6d8188; font-size: 6.5px; }
        .footer .right { float: right; }
    </style>
</head>
<body>
@php
    $organizationCount = $reports->pluck('think_tank_member_id')->filter()->unique()->count();
    $achievementCount = (int) $consolidated->sum('achievement_count');
    $beneficiaryCount = (int) $consolidated->sum('beneficiary_count');
    $duplicateCount = (int) $consolidated->sum('duplicate_result_count');
    $activeFilters = collect([
        'Portfolio' => $selectedPortfolio?->name,
        'Project / component' => $selectedProject
            ? collect([$selectedProject->project_id, $selectedProject->name])->filter()->join(' · ')
            : null,
        'Think tank' => $selectedThinkTank?->name,
        'Geographic scope' => $filters['geographic_scope'] ?? null,
        'Country' => $filters['country'] ?? null,
        'REC' => $filters['rec'] ?? null,
        'Implementing institution type' => $filters['implementing_institution_type'] ?? null,
        'Implementing institution' => $filters['implementing_institution'] ?? null,
        'ATTP priority thematic area' => $filters['priority_theme'] ?? null,
        'Gender' => $filters['gender'] ?? null,
        'Age group' => $filters['age_group'] ?? null,
        'Stakeholder category' => $filters['stakeholder_category'] ?? null,
    ])->filter(fn ($value) => filled($value));
@endphp

<div class="footer"><span>ATTP Monitoring, Evaluation and Learning · Official approved consolidation</span><span class="right">Generated {{ now()->format('d M Y, H:i') }}</span></div>

<table class="header"><tr><td style="width:62px"><div class="brand">ATTP</div></td><td class="heading"><div class="eyebrow">Africa Think Tank Platform</div><h1>Consolidated M&amp;E Performance Report</h1><div class="subtitle">Finally approved organization results consolidated with indicator-authorized roll-up controls</div></td><td class="document-meta"><strong>{{ strtoupper(str_replace('_',' ',$filters['period_type'])) }}</strong><br>{{ $filters['period_label'] }} · {{ $filters['year'] }}<br>Prepared by {{ $generatedBy?->name ?: 'ATTP Secretariat' }}</td></tr></table>
<div class="rule"></div>

<div class="scope"><strong>Report scope:</strong> {{ $activeFilters->isEmpty() ? 'All authorized portfolios and organizations; no beneficiary filters applied.' : $activeFilters->map(fn ($value,$label) => $label.': '.str($value)->headline())->join(' | ') }}<br><span class="muted">Draft, submitted, reviewed and verified reports are excluded. Archived reports retain their final approved contribution.</span></div>

<table class="summary"><tr><td><span>Approved reports</span><strong>{{ number_format($reports->count()) }}</strong></td><td><span>Organizations</span><strong>{{ number_format($organizationCount) }}</strong></td><td><span>Indicators</span><strong>{{ number_format($consolidated->count()) }}</strong></td><td><span>Achievements</span><strong>{{ number_format($achievementCount) }}</strong></td><td><span>Beneficiaries</span><strong>{{ number_format($beneficiaryCount) }}</strong></td></tr></table>

<h2>Approved consolidated indicator performance</h2>
<p class="section-note">Qualitative milestones remain attributable to each reporting organization. Numeric results use the roll-up method configured on the indicator.</p>
<table class="data">
    <thead><tr><th style="width:17%">Indicator</th><th style="width:12%">Roll-up</th><th style="width:18%">Consolidated result</th><th style="width:8%">Target</th><th style="width:12%">Organizations</th><th style="width:9%">Outputs</th><th style="width:9%">Beneficiaries</th><th style="width:15%">Disaggregation</th></tr></thead>
    <tbody>
    @forelse($consolidated as $row)
        @php
            $indicator = $row['indicator'];
            $unit = $indicator?->unit?->symbol ?: $indicator?->unit?->name;
            $isQualitative = $indicator?->value_type === 'milestone' || $row['qualitative_values']->isNotEmpty();
        @endphp
        <tr>
            <td><span class="code">{{ $indicator?->indicator_code ?: 'Uncoded' }}</span><br><strong>{{ $indicator?->name ?: 'Indicator unavailable' }}</strong><br><span class="muted">{{ str($indicator?->value_type ?: 'number')->headline() }}@if($indicator?->projectComponent)<br>Project: {{ $indicator->projectComponent->project_id }} · {{ $indicator->projectComponent->name }}@endif</span></td>
            <td>{{ $row['rollup_label'] }}@if($row['duplicate_result_count']>0)<br><span class="warning">{{ $row['duplicate_result_count'] }} overlap(s) suppressed</span>@endif</td>
            <td>@if($isQualitative)@forelse($row['qualitative_values'] as $qualitative)<div class="qualitative"><strong>{{ $qualitative['organization'] }}</strong>{{ $qualitative['value'] }}</div>@empty<span class="muted">No qualitative result recorded</span>@endforelse @else<span class="value">{{ $row['value']!==null ? number_format($row['value'],2) : 'Not numerically additive' }}</span>@if($unit)<br><span class="muted">{{ $unit }}</span>@endif @endif</td>
            <td class="num">{{ $row['target']!==null ? number_format($row['target'],2) : '—' }}</td>
            <td><strong>{{ $row['organization_count'] }}</strong><br><span class="muted">{{ $row['organizations']->join(', ') ?: 'Not recorded' }}</span></td>
            <td>{{ number_format($row['achievement_count']) }} achievements<br><span class="muted">{{ $row['reported_value_count'] }} authoritative values</span></td>
            <td class="num"><strong>{{ number_format($row['beneficiary_count']) }}</strong><br><span class="muted">F {{ number_format($row['gender']->get('female',0)) }} · M {{ number_format($row['gender']->get('male',0)) }}</span></td>
            <td><span class="muted"><strong>Countries:</strong> {{ $row['countries']->keys()->join(', ') ?: 'Not recorded' }}<br><strong>RECs:</strong> {{ $row['recs']->keys()->map(fn($value)=>strtoupper($value))->join(', ') ?: 'Not recorded' }}<br><strong>Themes:</strong> {{ $row['themes']->keys()->map(fn($value)=>str($value)->headline())->join(', ') ?: 'Not recorded' }}<br><strong>Stakeholders:</strong> {{ $row['stakeholders']->keys()->map(fn($value)=>str($value)->headline())->join(', ') ?: 'Not recorded' }}</span></td>
        </tr>
    @empty
        <tr><td colspan="8" style="padding:18px;text-align:center">No finally approved data is available for this reporting scope.</td></tr>
    @endforelse
    </tbody>
</table>

<div class="page-break"></div>
<h2>Approved source report register</h2>
<p class="section-note">These are the source reports included in the official consolidation above.</p>
<table class="data">
    <thead><tr><th style="width:27%">Organization</th><th style="width:29%">Report</th><th style="width:11%">Status</th><th style="width:11%">Indicators</th><th style="width:11%">Approved</th><th style="width:11%">Approved by</th></tr></thead>
    <tbody>
    @forelse($reports as $report)
        <tr><td><strong>{{ $report->thinkTank?->name ?: 'Organization unavailable' }}</strong><br><span class="muted">{{ $report->thinkTank?->country ?: 'Country not recorded' }}</span></td><td>{{ $report->form?->code ?: 'No code' }} · {{ $report->form?->title ?: 'Form unavailable' }}@if($report->projectComponent)<br><span class="muted">Project: {{ $report->projectComponent->project_id }} · {{ $report->projectComponent->name }}</span>@endif</td><td>{{ $report->lifecycleLabel() }}</td><td class="num">{{ $report->indicatorResults->count() }}</td><td>{{ $report->approved_at?->format('d M Y, H:i') ?: 'Date unavailable' }}</td><td>{{ $report->approvedBy?->name ?: 'Not recorded' }}</td></tr>
    @empty
        <tr><td colspan="6" style="padding:18px;text-align:center">No approved source reports are available.</td></tr>
    @endforelse
    </tbody>
</table>

@if($duplicateCount > 0)<div class="scope" style="margin-top:9px"><strong>Audit attention:</strong> {{ $duplicateCount }} overlapping approved source {{ str('result')->plural($duplicateCount) }} were suppressed. The most recently approved result per organization, indicator and period was retained.</div>@endif
</body>
</html>
