@extends('layouts.app')

@section('title', 'M&E Consolidations Engine')
@section('lean_admin_scripts', '1')

@push('styles')
    @include('me.consolidation-engine.partials.styles')
@endpush

@section('content')
@php
    $engineSummary = $engineSummary ?? ($summary ?? data_get($data ?? [], 'summary', []));
    $quality = $quality ?? data_get($data ?? [], 'quality', []);
    $performanceDistribution = collect($performanceDistribution ?? ($performance_distribution ?? data_get($data ?? [], 'performance_distribution', [])));
    $indicatorRows = collect($indicatorRows ?? ($indicator_rows ?? data_get($data ?? [], 'indicator_rows', [])));
    $projectRows = collect($projectRows ?? ($project_rows ?? data_get($data ?? [], 'project_rows', [])));
    $filters = $filters ?? [];
    $currentLevel = array_key_exists((string) ($filters['level'] ?? 'indicator'), $levels ?? [])
        ? (string) $filters['level']
        : 'indicator';
    $isProjectLevel = $currentLevel === 'project';
    $levelOptions = $levels ?? ['indicator' => 'Indicator level', 'project' => 'Project level'];
    $filterTotal = (int) ($activeFilterCount ?? $filterCount ?? 0);
    $frameworkRecord = $frameworkContext ?? ($framework ?? null);
    $exportParameters = $exportQuery ?? collect($filters)->filter(fn ($value) => filled($value))->all();
    $canExport = (bool) ($canExport ?? false);
    $generatedAt = $generatedAt ?? now();
    $scopeLabel = $scopeLabel ?? 'All authorized approved results';
    $qualityTotal = collect($quality)->sum(fn ($value) => (int) $value);
    $thematicAreas = data_get($options ?? [], 'thematic_areas', \App\Models\MeIndicatorAchievement::PRIORITY_THEMES);
    $performanceStatuses = $performanceStatuses ?? [];
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
    $attainmentChartRows = $indicatorRows
        ->filter(fn (array $row) => $row['achievement_percent'] !== null)
        ->sortByDesc('achievement_percent')
        ->take(14)
        ->map(fn (array $row) => [
            'code' => $row['indicator']?->indicator_code ?: 'Uncoded',
            'achievement' => round((float) $row['achievement_percent'], 1),
            'color' => $row['classification']['color'] ?? '#64748b',
        ])->values();
    $projectChartRows = $projectRows->take(14)->map(fn (array $row) => [
        'code' => $row['code'],
        'achievement' => $row['average_achievement'],
        'completeness' => $row['reporting_completeness'],
    ])->values();
@endphp

<div class="mel-consolidation-engine">
    <header class="ce-header">
        <div>
            <span class="ce-eyebrow">Monitoring, Evaluation and Learning</span>
            <h1>M&amp;E Consolidations Engine</h1>
            <p>Consolidate finally approved performance at indicator or project level, inspect every source contribution and export the same governed scope for management and audit use.</p>
        </div>
        <div class="ce-header-side">
            <div class="ce-scope">
                <span>Current approved-results scope</span>
                <strong>{{ $scopeLabel }} &middot; Generated {{ $generatedAt->format('d M Y, H:i') }}</strong>
            </div>
            <div class="ce-actions ce-no-print" aria-label="Download and print consolidation">
                @if($canExport && $frameworkRecord)
                    <a class="ce-btn ce-btn-header" href="{{ route('budget.me.consolidation-engine.excel', $exportParameters) }}"><i class="feather-file-text" aria-hidden="true"></i>Excel</a>
                    <a class="ce-btn ce-btn-header" href="{{ route('budget.me.consolidation-engine.csv', $exportParameters) }}"><i class="feather-list" aria-hidden="true"></i>CSV</a>
                    <a class="ce-btn ce-btn-header" href="{{ route('budget.me.consolidation-engine.pdf', $exportParameters) }}"><i class="feather-download" aria-hidden="true"></i>PDF</a>
                @endif
                <button class="ce-btn ce-btn-header" type="button" data-ce-print><i class="feather-printer" aria-hidden="true"></i>Print</button>
            </div>
        </div>
    </header>

    <aside class="ce-guardrail" aria-label="Official consolidation rule">
        <span class="ce-guardrail-mark">APR</span>
        <div>
            <strong>Official approved-only consolidation is active</strong>
            <p>Draft, submitted, returned, reviewed and merely verified records are excluded. Indicator results follow their configured aggregation rules. Project performance averages rated indicator attainment with each contribution capped at 100%; raw values with different units are never added together.</p>
        </div>
        <span class="ce-approved-pill">Final approvals only</span>
    </aside>

    @if(isset($errors) && $errors->any())
        <div class="ce-alert" role="alert"><strong>The requested consolidation scope could not be applied.</strong> {{ $errors->first() }}</div>
    @endif

    <nav class="ce-level-tabs ce-no-print" aria-label="Consolidation level">
        <a class="ce-level-tab {{ $currentLevel === 'indicator' ? 'active' : '' }}"
           href="{{ route('budget.me.consolidation-engine.index', array_merge($exportParameters, ['level' => 'indicator'])) }}"
           @if($currentLevel === 'indicator') aria-current="page" @endif>
            <span class="ce-level-icon"><i class="feather-target" aria-hidden="true"></i></span>
            <span class="ce-level-copy"><strong>Indicator-level consolidation</strong><small>Targets, actuals, configured formulas, contributors, evidence and source-level audit detail.</small></span>
        </a>
        <a class="ce-level-tab {{ $currentLevel === 'project' ? 'active' : '' }}"
           href="{{ route('budget.me.consolidation-engine.index', array_merge($exportParameters, ['level' => 'project'])) }}"
           @if($currentLevel === 'project') aria-current="page" @endif>
            <span class="ce-level-icon"><i class="feather-layers" aria-hidden="true"></i></span>
            <span class="ce-level-copy"><strong>Project-level consolidation</strong><small>Project scorecards, indicator mix, completeness, evidence and nested indicator breakdowns.</small></span>
        </a>
    </nav>

    @if(!$frameworkRecord)
        <section class="ce-panel" aria-labelledby="engine-setup-title">
            <div class="ce-empty">
                <span class="ce-empty-mark">RF</span>
                <strong id="engine-setup-title">The active Results Framework is not available</strong>
                <p>Install and activate the controlled ATTP Results Framework before this engine can resolve indicator formulas, approved targets and project relationships.</p>
                @canany(['me.configuration.view', 'me.configuration.manage'])
                    <a class="ce-btn ce-btn-primary ce-no-print" href="{{ route('budget.me.framework.index') }}">Open framework administration</a>
                @endcanany
            </div>
        </section>
    @else
        <details class="ce-panel ce-filter ce-no-print" @if($filterTotal > 0) open @endif>
            <summary class="ce-panel-head">
                <div><h2>Consolidation scope and filters</h2><p>Every chart, scorecard, table and download uses this exact approved-results scope.</p></div>
                <div class="ce-summary-right"><span class="ce-badge">{{ $filterTotal }} active {{ str('filter')->plural($filterTotal) }}</span><span class="ce-chevron" aria-hidden="true"><i class="feather-chevron-down"></i></span></div>
            </summary>
            <div class="ce-panel-body">
                <form method="GET" action="{{ route('budget.me.consolidation-engine.index') }}" class="ce-filter-grid" data-ce-scope-form>
                    <input type="hidden" name="level" value="{{ $currentLevel }}">

                    <div class="ce-field">
                        <label for="ce-project-year">Target project year</label>
                        <select id="ce-project-year" name="project_year" class="form-select">
                            @foreach(range(1,4) as $year)<option value="{{ $year }}" @selected((int) ($filters['project_year'] ?? 1) === $year)>Project Year {{ $year }}</option>@endforeach
                        </select>
                        <small>Selects the approved target benchmark.</small>
                    </div>
                    <div class="ce-field">
                        <label for="ce-reporting-year">Reporting year</label>
                        <select id="ce-reporting-year" name="reporting_year" class="form-select" data-ce-year>
                            <option value="">All reporting years</option>
                            @foreach($reportingYears ?? [] as $year)<option value="{{ $year }}" @selected((int) ($filters['reporting_year'] ?? 0) === (int) $year)>{{ $year }}</option>@endforeach
                        </select>
                    </div>
                    <div class="ce-field ce-field-wide">
                        <label for="ce-period">Reporting period</label>
                        <select id="ce-period" name="reporting_period_id" class="form-select" data-ce-period>
                            <option value="">All periods in scope</option>
                            @foreach($periods ?? [] as $item)
                                <option value="{{ $item->id }}" data-year="{{ $item->reporting_year }}" @selected((string) ($filters['reporting_period_id'] ?? '') === (string) $item->id)>
                                    {{ $item->label }}{{ $item->reporting_year ? ' · '.$item->reporting_year : '' }}
                                </option>
                            @endforeach
                        </select>
                        <small>A selected period takes precedence over reporting year.</small>
                    </div>
                    <div class="ce-field">
                        <label for="ce-portfolio">Portfolio</label>
                        <select id="ce-portfolio" name="portfolio_id" class="form-select" data-ce-portfolio>
                            <option value="">All authorized portfolios</option>
                            @foreach($portfolios ?? [] as $item)<option value="{{ $item->id }}" @selected((string) ($filters['portfolio_id'] ?? '') === (string) $item->id)>{{ $item->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="ce-field ce-field-wide">
                        <label for="ce-project">Project / results component</label>
                        <select id="ce-project" name="component_id" class="form-select" data-ce-project>
                            <option value="">PDO and all authorized projects</option>
                            @foreach($projects ?? [] as $item)
                                <option value="{{ $item->id }}"
                                        data-portfolio="{{ $item->program?->sector?->id }}"
                                        @selected((string) ($filters['component_id'] ?? '') === (string) $item->id)>
                                    {{ $item->project_id }} &middot; {{ $item->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ce-field">
                        <label for="ce-think-tank">Think Tank / contributor</label>
                        <select id="ce-think-tank" name="think_tank_id" class="form-select">
                            <option value="">All authorized contributors</option>
                            @foreach($thinkTanks ?? [] as $item)<option value="{{ $item->id }}" @selected((string) ($filters['think_tank_id'] ?? '') === (string) $item->id)>{{ $item->name }}</option>@endforeach
                        </select>
                    </div>

                    <details class="ce-advanced" @if(collect($filters)->only(['indicator_id','results_level','performance_status','country','thematic_area'])->filter()->isNotEmpty()) open @endif>
                        <summary>Indicator, performance, participant and beneficiary filters</summary>
                        <div class="ce-filter-grid">
                            <div class="ce-field ce-field-wide">
                                <label for="ce-indicator">Indicator</label>
                                <select id="ce-indicator" name="indicator_id" class="form-select" data-ce-indicator>
                                    <option value="">All indicators</option>
                                    @foreach($indicators ?? [] as $item)
                                        <option value="{{ $item->id }}"
                                                data-project="{{ $item->project_component_id }}"
                                                data-results-level="{{ $item->results_level }}"
                                                @selected((string) ($filters['indicator_id'] ?? '') === (string) $item->id)>
                                            {{ $item->indicator_code }} &middot; {{ $item->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="ce-field">
                                <label for="ce-results-level">Results level</label>
                                <select id="ce-results-level" name="results_level" class="form-select">
                                    <option value="">PDO and intermediate results</option>
                                    <option value="pdo" @selected(($filters['results_level'] ?? null) === 'pdo')>Project Development Objective</option>
                                    <option value="intermediate_results" @selected(($filters['results_level'] ?? null) === 'intermediate_results')>Intermediate Results</option>
                                </select>
                            </div>
                            <div class="ce-field">
                                <label for="ce-performance">Performance classification</label>
                                <select id="ce-performance" name="performance_status" class="form-select">
                                    <option value="">All performance statuses</option>
                                    @foreach($performanceStatuses as $value => $label)<option value="{{ $value }}" @selected(($filters['performance_status'] ?? null) === $value)>{{ $label }}</option>@endforeach
                                </select>
                            </div>
                            <div class="ce-field">
                                <label for="ce-country">Country</label>
                                <select id="ce-country" name="country" class="form-select">
                                    <option value="">All countries</option>
                                    @foreach($countries ?? [] as $country)<option value="{{ $country }}" @selected(($filters['country'] ?? null) === $country)>{{ $country }}</option>@endforeach
                                </select>
                            </div>
                            <div class="ce-field ce-field-wide">
                                <label for="ce-theme">ATTP priority thematic area</label>
                                <select id="ce-theme" name="thematic_area" class="form-select">
                                    <option value="">All thematic areas</option>
                                    @foreach($thematicAreas as $value => $label)<option value="{{ $value }}" @selected(($filters['thematic_area'] ?? null) === $value)>{{ $label }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                    </details>

                    <div class="ce-filter-actions">
                        <p class="ce-filter-tip"><strong>Calculation rule:</strong> target year chooses the benchmark; period or reporting year chooses approved actuals. Project scorecards average only rateable indicator attainment percentages, cap each indicator's scorecard contribution at 100% and never combine raw values from unlike units.</p>
                        <div class="ce-actions">
                            <a class="ce-btn ce-btn-secondary" href="{{ route('budget.me.consolidation-engine.index', ['level' => $currentLevel]) }}">Clear filters</a>
                            <button class="ce-btn ce-btn-primary" type="submit"><i class="feather-filter" aria-hidden="true"></i>Run consolidation</button>
                        </div>
                    </div>
                </form>
            </div>
        </details>

        <section class="ce-metrics" aria-label="Consolidation summary">
            <article class="ce-metric"><span class="ce-metric-label">{{ $isProjectLevel ? 'Projects / results areas' : 'Indicators in scope' }}</span><strong>{{ number_format((int) ($isProjectLevel ? ($engineSummary['results_area_count'] ?? 0) : ($engineSummary['indicator_count'] ?? 0))) }}</strong><small>{{ $isProjectLevel ? number_format((int) ($engineSummary['project_count'] ?? 0)).' project-linked areas plus PDO where present' : number_format((int) ($engineSummary['reported_indicator_count'] ?? 0)).' indicators contain approved data' }}</small></article>
            <article class="ce-metric" style="--metric:#1676b8"><span class="ce-metric-label">Approved contributions</span><strong>{{ number_format((int) ($engineSummary['approved_contribution_count'] ?? 0)) }}</strong><small>Deduplicated final source records used in this scope</small></article>
            <article class="ce-metric" style="--metric:#6b63a8"><span class="ce-metric-label">Average indicator attainment</span><strong>{{ ($engineSummary['average_achievement'] ?? null) === null ? '—' : number_format((float) $engineSummary['average_achievement'],1).'%' }}</strong><small>Unweighted average; each rated indicator is capped at 100%</small></article>
            <article class="ce-metric" style="--metric:#187459"><span class="ce-metric-label">Reporting completeness</span><strong>{{ number_format((float) ($engineSummary['reporting_completeness'] ?? 0),1) }}%</strong><small>{{ number_format((int) ($engineSummary['organization_count'] ?? 0)) }} approved reporting organizations represented</small></article>
            <article class="ce-metric" style="--metric:#0e7490"><span class="ce-metric-label">Evidence verification</span><strong>{{ ($engineSummary['evidence_verification_rate'] ?? null) === null ? '—' : number_format((float) $engineSummary['evidence_verification_rate'],1).'%' }}</strong><small>{{ number_format((int) ($engineSummary['verified_evidence_count'] ?? 0)) }} of {{ number_format((int) ($engineSummary['evidence_count'] ?? 0)) }} evidence links verified</small></article>
            <article class="ce-metric" style="--metric:{{ $qualityTotal > 0 ? '#a56a17' : '#187459' }}"><span class="ce-metric-label">Quality exceptions</span><strong>{{ number_format($qualityTotal) }}</strong><small>{{ $qualityTotal > 0 ? 'Signals disclosed below; results remain traceable' : 'No configured quality exception is present' }}</small></article>
        </section>

        <div class="ce-grid">
            <section class="ce-panel" aria-labelledby="ce-performance-chart-title">
                <div class="ce-panel-head"><div><h2 id="ce-performance-chart-title">Performance distribution</h2><p>Indicators grouped by the active framework's approved performance thresholds.</p></div><span class="ce-badge">{{ $indicatorRows->count() }} indicators</span></div>
                @if($performanceDistribution->isNotEmpty())
                    <div id="ce-performance-chart" class="ce-chart" role="img" aria-label="Donut chart of indicator performance classifications"></div>
                @else
                    <div class="ce-empty"><span class="ce-empty-mark">PD</span><strong>No performance classifications in scope</strong><p>Broaden the reporting scope or wait for official indicator results to receive final approval.</p></div>
                @endif
            </section>
            <section class="ce-panel" aria-labelledby="ce-level-chart-title">
                <div class="ce-panel-head">
                    <div><h2 id="ce-level-chart-title">{{ $isProjectLevel ? 'Project attainment and completeness' : 'Highest indicator target attainment' }}</h2><p>{{ $isProjectLevel ? 'A scorecard comparison; raw indicator values are not summed.' : 'Rateable indicators ordered by approved target attainment.' }}</p></div>
                    <span class="ce-badge">{{ $isProjectLevel ? $projectChartRows->count().' areas' : $attainmentChartRows->count().' rated' }}</span>
                </div>
                @if(($isProjectLevel && $projectChartRows->isNotEmpty()) || (!$isProjectLevel && $attainmentChartRows->isNotEmpty()))
                    <div id="ce-level-chart" class="ce-chart" role="img" aria-label="{{ $isProjectLevel ? 'Bar chart comparing project attainment and completeness' : 'Bar chart of indicator target attainment' }}"></div>
                @else
                    <div class="ce-empty"><span class="ce-empty-mark">TA</span><strong>No target attainment can be calculated</strong><p>Approved numeric or Yes/No results require a matching approved target before attainment can be rated.</p></div>
                @endif
            </section>
        </div>

        <section class="ce-panel" style="margin-top:1rem" aria-labelledby="ce-method-title">
            <div class="ce-panel-head"><div><h2 id="ce-method-title">Consolidation methodology</h2><p>The engine preserves indicator meaning while producing a comparable project management view.</p></div><span class="ce-badge">Governed calculation</span></div>
            <div class="ce-panel-body ce-method-grid">
                <article class="ce-method"><span class="ce-method-mark">IN</span><div><strong>Indicator-level result</strong><p>Each indicator uses its configured time aggregation and organization roll-up, retains its own unit and qualitative values, and exposes every included approved contribution.</p></div></article>
                <article class="ce-method"><span class="ce-method-mark">PR</span><div><strong>Project-level scorecard</strong><p>Project attainment is the unweighted average of rateable indicator percentages after capping each indicator's contribution at 100%. Counts, completeness and evidence links are summarized separately; raw actuals from unlike units are never added.</p></div></article>
            </div>
        </section>

        <section class="ce-panel" style="margin-top:1rem" aria-labelledby="ce-quality-title">
            <div class="ce-panel-head"><div><h2 id="ce-quality-title">Consolidation quality controls</h2><p>Visible exceptions explain why a result may be unrated, incomplete or unsuitable for one numeric total.</p></div><span class="ce-badge {{ $qualityTotal > 0 ? 'warning' : '' }}">{{ $qualityTotal > 0 ? $qualityTotal.' signals' : 'All checks clear' }}</span></div>
            <div class="ce-panel-body ce-quality-grid">
                @foreach([
                    ['missing_targets','Missing approved targets','Reported values that cannot be rated'],
                    ['not_reported','Indicators not reported','Framework indicators without approved data'],
                    ['non_additive_or_qualitative','Non-additive / qualitative','Values preserved without forced summation'],
                    ['missing_required_evidence','Required evidence missing','Reported indicators lacking required evidence'],
                    ['incomplete_reporting','Incomplete reporting','Indicators below full reporter coverage'],
                    ['weighted_values_without_weights','Missing valid weights','Weighted roll-ups missing numerator or denominator'],
                ] as [$key,$label,$description])
                    @php
                        $qualityValue = (int) ($quality[$key] ?? 0);
                    @endphp
                    <article class="ce-quality {{ $qualityValue > 0 ? 'has-issue' : 'is-clear' }}"><span>{{ $label }}</span><strong>{{ number_format($qualityValue) }}</strong><span>{{ $description }}</span></article>
                @endforeach
            </div>
        </section>

        @if(!$isProjectLevel)
            <section class="ce-panel ce-register" id="ce-register" aria-labelledby="ce-indicator-register-title">
                <div class="ce-panel-head">
                    <div><h2 id="ce-indicator-register-title">Indicator-level consolidation register</h2><p>Approved targets, results, formulas, coverage and auditable source contributions.</p></div>
                    @if($canExport)<span class="ce-badge">Excel includes contributor detail</span>@endif
                </div>
                <div class="ce-toolbar ce-no-print">
                    <div class="ce-toolbar-fields">
                        <label class="ce-search" for="ce-register-search"><i class="feather-search" aria-hidden="true"></i><input id="ce-register-search" class="form-control" type="search" placeholder="Search indicator, project, unit or contributor" data-ce-search></label>
                        <select class="form-select" aria-label="Filter register by performance" data-ce-status-filter>
                            <option value="">All performance statuses</option>
                            @foreach($performanceStatuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                        <button class="ce-btn ce-btn-secondary" type="button" data-ce-clear>Clear local search</button>
                    </div>
                    <span class="ce-toolbar-count" aria-live="polite"><span data-ce-visible-count>{{ $indicatorRows->count() }}</span> of {{ $indicatorRows->count() }} indicators shown</span>
                </div>
                @if($indicatorRows->isEmpty())
                    <div class="ce-empty"><span class="ce-empty-mark">0</span><strong>No indicators match this approved-results scope</strong><p>Clear one or more filters, select another reporting period or confirm that results have received final Secretariat approval.</p><a class="ce-btn ce-btn-secondary ce-no-print" href="{{ route('budget.me.consolidation-engine.index', ['level'=>'indicator']) }}">Clear consolidation filters</a></div>
                @else
                    <div class="ce-table-wrap" role="region" aria-label="Scrollable indicator consolidation register" tabindex="0">
                        <table class="ce-table">
                            <thead><tr><th>Indicator</th><th>Project / level</th><th>Configured aggregation</th><th>Baseline / target</th><th>Consolidated actual</th><th>Attainment / variance</th><th>Performance</th><th>Reporting coverage</th><th>Evidence / outputs</th><th>Last approval</th><th>Calculation detail</th></tr></thead>
                            <tbody>
                            @foreach($indicatorRows as $row)
                                @php
                                    $indicator = $row['indicator'];
                                    $classification = $row['classification'] ?? ['code'=>'not_rated','label'=>'Not rated','color'=>'#64748b'];
                                    $statusColor = preg_match('/^#[0-9a-f]{6}$/i', (string) ($classification['color'] ?? '')) ? $classification['color'] : '#64748b';
                                    $unit = $row['unit_label'] ?? ($indicator?->unit?->symbol ?: $indicator?->unit?->name);
                                    $targetDisplay = filled($row['target_text'] ?? null) ? $row['target_text'] : $formatValue($row['target_value'] ?? null, $unit);
                                    $detailId = 'ce-indicator-detail-'.$loop->index;
                                    $searchText = str(collect([
                                        $indicator?->indicator_code, $indicator?->name, $indicator?->projectComponent?->project_id,
                                        $indicator?->projectComponent?->name, $unit, $row['organization_rollup_label'] ?? null,
                                        collect($row['reporting_organizations'] ?? [])->join(' '),
                                    ])->filter()->join(' '))->lower();
                                    $achievementWidth = min(100,max(0,(float) ($row['achievement_percent'] ?? 0)));
                                    $coverageWidth = min(100,max(0,(float) ($row['reporting_completeness'] ?? 0)));
                                @endphp
                                <tr class="ce-main-row" data-ce-main-row data-search="{{ $searchText }}" data-status="{{ $classification['code'] }}" data-detail-id="{{ $detailId }}">
                                    <td><span class="ce-code">{{ $indicator?->indicator_code ?: 'No code' }}</span><span class="ce-title">{{ $indicator?->name ?: 'Indicator unavailable' }}</span><span class="ce-meta">{{ str($indicator?->value_type ?: 'number')->headline() }}{{ $unit ? ' · '.$unit : '' }}</span></td>
                                    <td><strong>{{ $indicator?->projectComponent?->project_id ?: 'PDO' }}</strong><span class="ce-meta">{{ $indicator?->projectComponent?->name ?: 'Project Development Objective / cross-project result' }}</span><span class="ce-meta">{{ $indicator?->resultsLevelLabel() }}</span></td>
                                    <td><strong>{{ $row['organization_rollup_label'] ?? 'Not configured' }}</strong><span class="ce-meta">Across organizations</span><span class="ce-meta">{{ $row['time_aggregation_label'] ?? $indicator?->aggregationMethodLabel() }}</span><span class="ce-meta">Across reporting periods</span></td>
                                    <td><strong>{{ $formatValue($row['baseline'] ?? null, $unit) }}</strong><span class="ce-meta">Baseline</span><span class="ce-value">{{ $targetDisplay }}</span><span class="ce-meta">Approved Project Year {{ (int) ($filters['project_year'] ?? 1) }} target</span></td>
                                    <td><span class="ce-value">{{ $formatValue($row['actual'] ?? null, $unit) }}</span><span class="ce-meta">{{ $indicator?->is_cumulative ? 'Cumulative actual' : 'Period actual' }}</span>@if(in_array($indicator?->value_type,['milestone','text'],true) || $indicator?->organization_rollup_method === 'non_additive')<span class="ce-badge warning" style="margin-top:.35rem">Not numerically additive</span>@endif</td>
                                    <td><strong>{{ ($row['achievement_percent'] ?? null) === null ? 'Not rateable' : number_format((float) $row['achievement_percent'],1).'%' }}</strong><div class="ce-progress"><span style="width:{{ $achievementWidth }}%;--progress:{{ $statusColor }}"></span></div><span class="ce-meta">Variance: {{ $formatValue($row['variance_value'] ?? $row['variance'] ?? null, $unit) }}</span><span class="ce-meta">Trend: {{ data_get($row,'trend.label','Not available') }}</span></td>
                                    <td><span class="ce-status" style="--status:{{ $statusColor }}">{{ $classification['label'] }}</span></td>
                                    <td><strong>{{ number_format((int) ($row['reported_organizations'] ?? 0)) }} / {{ number_format((int) ($row['expected_organizations'] ?? 0)) }} reporters</strong><div class="ce-progress"><span style="width:{{ $coverageWidth }}%"></span></div><span class="ce-meta">{{ number_format((float) ($row['reporting_completeness'] ?? 0),1) }}% complete</span></td>
                                    <td><strong>{{ number_format((int) ($row['verified_evidence_count'] ?? 0)) }} / {{ number_format((int) ($row['evidence_count'] ?? 0)) }} evidence links verified</strong><span class="ce-meta">{{ number_format((int) ($row['achievement_count'] ?? 0)) }} achievement records</span><span class="ce-meta">{{ number_format((int) ($row['beneficiary_count'] ?? 0)) }} participant/beneficiary instances</span></td>
                                    <td>{{ $formatDate($row['latest_approved_at'] ?? null) }}</td>
                                    <td><button class="ce-btn ce-btn-secondary ce-btn-small ce-no-print" type="button" data-ce-toggle data-target="{{ $detailId }}" aria-controls="{{ $detailId }}" aria-expanded="false"><i class="feather-eye" aria-hidden="true"></i><span>View sources</span></button><span class="ce-meta">{{ collect($row['source_contributions'] ?? [])->count() }} included contributions</span></td>
                                </tr>
                                <tr class="ce-detail-row" id="{{ $detailId }}" data-ce-detail hidden>
                                    <td colspan="11">
                                        <div class="ce-detail-shell">
                                            <div class="ce-detail-head"><div><strong>{{ $indicator?->indicator_code }} calculation and source audit</strong><p>{{ $row['calculation_note'] ?? 'Calculated from approved indicator results only.' }}</p></div><button class="ce-btn ce-btn-secondary ce-btn-small ce-no-print" type="button" data-ce-close-detail data-target="{{ $detailId }}">Close detail</button></div>
                                            <div class="ce-detail-facts">
                                                <div class="ce-detail-fact"><small>Time aggregation</small><strong>{{ $row['time_aggregation_label'] ?? $indicator?->aggregationMethodLabel() }}</strong></div>
                                                <div class="ce-detail-fact"><small>Organization roll-up</small><strong>{{ $row['organization_rollup_label'] ?? 'Not configured' }}</strong></div>
                                                <div class="ce-detail-fact"><small>Geographic footprint</small><strong>{{ collect($row['countries'] ?? [])->join(', ') ?: 'Not recorded' }}</strong></div>
                                                <div class="ce-detail-fact"><small>Participant / beneficiary instances</small><strong>F {{ number_format((int) ($row['female_beneficiaries'] ?? 0)) }} &middot; M {{ number_format((int) ($row['male_beneficiaries'] ?? 0)) }}</strong></div>
                                            </div>
                                            @if(collect($row['source_contributions'] ?? [])->isEmpty())
                                                <div class="ce-source-empty">No approved source contribution is available for this indicator in the selected scope.</div>
                                            @else
                                                <div class="ce-source-wrap"><table class="ce-source-table"><thead><tr><th>Organization</th><th>Country</th><th>Period</th><th>Approved contribution</th><th>Weight inputs</th><th>Data source</th><th>Achievements</th><th>Evidence links</th><th>Approved at</th></tr></thead><tbody>
                                                @foreach($row['source_contributions'] as $source)
                                                    <tr><td><strong>{{ $source['organization'] }}</strong></td><td>{{ $source['country'] ?: 'Not recorded' }}</td><td>{{ $source['period'] ?: 'Not recorded' }}</td><td>{{ $formatValue($source['actual'] ?? null, $unit) }}</td><td>@if(($source['rollup_numerator'] ?? null) !== null || ($source['rollup_denominator'] ?? null) !== null){{ $formatValue($source['rollup_numerator'] ?? null) }} / {{ $formatValue($source['rollup_denominator'] ?? null) }}@else<span class="ce-meta">Not required</span>@endif</td><td>{{ $source['data_source'] ?: 'Not recorded' }}</td><td>{{ number_format((int) ($source['achievement_count'] ?? 0)) }}</td><td>{{ number_format((int) ($source['evidence_count'] ?? 0)) }}</td><td>{{ $formatDate($source['approved_at'] ?? null) }}</td></tr>
                                                @endforeach
                                                </tbody></table></div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="ce-table-filter-empty" data-ce-filter-empty><td colspan="11"><div class="ce-empty"><span class="ce-empty-mark">0</span><strong>No indicator rows match the local search</strong><p>Clear the keyword or performance selector. Server-side consolidation filters remain unchanged.</p></div></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="ce-scroll-tip"><span>Open “View sources” to audit approved contributions and weighting inputs.</span><span>Scroll horizontally to inspect the complete register.</span></div>
                @endif
            </section>
        @else
            <section class="ce-panel ce-register" id="ce-register" aria-labelledby="ce-project-register-title">
                <div class="ce-panel-head"><div><h2 id="ce-project-register-title">Project-level consolidation register</h2><p>Comparable scorecards with nested indicator detail and no mixed-unit raw total.</p></div><span class="ce-badge warning">No cross-unit summation</span></div>
                <div class="ce-toolbar ce-no-print">
                    <div class="ce-toolbar-fields">
                        <label class="ce-search" for="ce-register-search"><i class="feather-search" aria-hidden="true"></i><input id="ce-register-search" class="form-control" type="search" placeholder="Search project, portfolio, program or contributor" data-ce-search></label>
                        <select class="form-select" aria-label="Filter projects by scorecard status" data-ce-status-filter>
                            <option value="">All project scorecard statuses</option>
                            @foreach($projectRows->pluck('status')->filter()->unique('code') as $status)<option value="{{ $status['code'] }}">{{ $status['label'] }}</option>@endforeach
                        </select>
                        <button class="ce-btn ce-btn-secondary" type="button" data-ce-clear>Clear local search</button>
                    </div>
                    <span class="ce-toolbar-count" aria-live="polite"><span data-ce-visible-count>{{ $projectRows->count() }}</span> of {{ $projectRows->count() }} projects / results areas shown</span>
                </div>
                @if($projectRows->isEmpty())
                    <div class="ce-empty"><span class="ce-empty-mark">0</span><strong>No project scorecards match this scope</strong><p>Clear one or more filters or confirm that active framework indicators are linked to an authorized project/results component.</p><a class="ce-btn ce-btn-secondary ce-no-print" href="{{ route('budget.me.consolidation-engine.index', ['level'=>'project']) }}">Clear consolidation filters</a></div>
                @else
                    <div class="ce-table-wrap" role="region" aria-label="Scrollable project consolidation register" tabindex="0">
                        <table class="ce-table project">
                            <thead><tr><th>Project / results area</th><th>Indicator coverage</th><th>Average attainment</th><th>Performance mix</th><th>Reporting completeness</th><th>Contributors</th><th>Evidence links</th><th>Participant / beneficiary instances</th><th>Last approval</th><th>Project detail</th></tr></thead>
                            <tbody>
                            @foreach($projectRows as $row)
                                @php
                                    $status = $row['status'] ?? ['code'=>'not_rated','label'=>'Not rated','color'=>'#64748b'];
                                    $statusColor = preg_match('/^#[0-9a-f]{6}$/i', (string) ($status['color'] ?? '')) ? $status['color'] : '#64748b';
                                    $detailId = 'ce-project-detail-'.$loop->index;
                                    $searchText = str(collect([$row['code'],$row['name'],$row['program'] ?? null,$row['portfolio'] ?? null,collect($row['organizations'] ?? [])->join(' ')])->filter()->join(' '))->lower();
                                    $achievementWidth = min(100,max(0,(float) ($row['average_achievement'] ?? 0)));
                                    $coverageWidth = min(100,max(0,(float) ($row['reporting_completeness'] ?? 0)));
                                @endphp
                                <tr class="ce-main-row" data-ce-main-row data-search="{{ $searchText }}" data-status="{{ $status['code'] }}" data-detail-id="{{ $detailId }}">
                                    <td><span class="ce-code">{{ $row['code'] }}</span><span class="ce-title">{{ $row['name'] }}</span><span class="ce-meta">{{ $row['portfolio'] ?: 'Portfolio not recorded' }}{{ $row['program'] ? ' · '.$row['program'] : '' }}</span></td>
                                    <td><strong>{{ number_format((int) $row['reported_indicator_count']) }} / {{ number_format((int) $row['indicator_count']) }} reported</strong><span class="ce-meta">{{ number_format((int) $row['rated_indicator_count']) }} rateable &middot; {{ number_format((int) $row['not_rated_count']) }} qualitative/unrated</span></td>
                                    <td><span class="ce-value">{{ $row['average_achievement'] === null ? 'Not rateable' : number_format((float) $row['average_achievement'],1).'%' }}</span><div class="ce-progress"><span style="width:{{ $achievementWidth }}%;--progress:{{ $statusColor }}"></span></div><span class="ce-meta">Average capped indicator attainment; not a raw-value total</span></td>
                                    <td><span class="ce-status" style="--status:{{ $statusColor }}">{{ $status['label'] }}</span><span class="ce-meta">{{ number_format((int) $row['on_track_count']) }} on track / achieved</span><span class="ce-meta">{{ number_format((int) $row['attention_count']) }} need attention</span></td>
                                    <td><strong>{{ number_format((float) $row['reporting_completeness'],1) }}%</strong><div class="ce-progress"><span style="width:{{ $coverageWidth }}%"></span></div><span class="ce-meta">{{ number_format((int) $row['approved_contribution_count']) }} approved contributions</span></td>
                                    <td><strong>{{ number_format((int) $row['organization_count']) }} organizations</strong><div>@foreach(collect($row['organizations'] ?? [])->take(3) as $organization)<span class="ce-chip">{{ $organization }}</span>@endforeach</div>@if(collect($row['organizations'] ?? [])->count() > 3)<span class="ce-meta">+{{ collect($row['organizations'])->count()-3 }} more</span>@endif</td>
                                    <td><strong>{{ number_format((int) $row['verified_evidence_count']) }} / {{ number_format((int) $row['evidence_count']) }} verified</strong><span class="ce-meta">{{ $row['evidence_verification_rate'] === null ? 'No evidence rate available' : number_format((float) $row['evidence_verification_rate'],1).'% verification' }}</span></td>
                                    <td><strong>{{ number_format((int) $row['beneficiary_count']) }}</strong><span class="ce-meta">F {{ number_format((int) $row['female_beneficiaries']) }} &middot; M {{ number_format((int) $row['male_beneficiaries']) }}</span><span class="ce-meta">Instances may recur across different indicators.</span></td>
                                    <td>{{ $formatDate($row['latest_approved_at'] ?? null) }}</td>
                                    <td><button class="ce-btn ce-btn-secondary ce-btn-small ce-no-print" type="button" data-ce-toggle data-target="{{ $detailId }}" aria-controls="{{ $detailId }}" aria-expanded="false"><i class="feather-layers" aria-hidden="true"></i><span>View indicators</span></button><span class="ce-meta">{{ number_format((int) $row['indicator_count']) }} nested rows</span></td>
                                </tr>
                                <tr class="ce-detail-row" id="{{ $detailId }}" data-ce-detail hidden>
                                    <td colspan="10">
                                        <div class="ce-detail-shell">
                                            <div class="ce-detail-head"><div><strong>{{ $row['code'] }} indicator breakdown</strong><p>{{ $row['calculation_note'] }}</p></div><button class="ce-btn ce-btn-secondary ce-btn-small ce-no-print" type="button" data-ce-close-detail data-target="{{ $detailId }}">Close detail</button></div>
                                            <div class="ce-method-warning"><strong>Interpretation:</strong> the project score is an unweighted average after each rated indicator's target-attainment contribution is capped at 100%. Indicator actuals retain their individual units below and are never added into one raw project value.</div>
                                            <div class="ce-source-wrap"><table class="ce-source-table"><thead><tr><th>Indicator</th><th>Unit / type</th><th>Target</th><th>Approved actual</th><th>Attainment</th><th>Performance</th><th>Reporting</th><th>Evidence links</th></tr></thead><tbody>
                                            @foreach($row['indicator_rows'] as $indicatorRow)
                                                @php
                                                    $nestedIndicator = $indicatorRow['indicator'];
                                                    $nestedUnit = $indicatorRow['unit_label'] ?? ($nestedIndicator?->unit?->symbol ?: $nestedIndicator?->unit?->name);
                                                    $nestedStatus = $indicatorRow['classification'] ?? ['label'=>'Not rated','color'=>'#64748b'];
                                                    $nestedColor = preg_match('/^#[0-9a-f]{6}$/i', (string) ($nestedStatus['color'] ?? '')) ? $nestedStatus['color'] : '#64748b';
                                                @endphp
                                                <tr><td><span class="ce-code">{{ $nestedIndicator?->indicator_code }}</span><strong class="ce-title">{{ $nestedIndicator?->name }}</strong></td><td>{{ $nestedUnit ?: 'No unit' }}<span class="ce-meta">{{ str($nestedIndicator?->value_type ?: 'number')->headline() }}</span></td><td>{{ filled($indicatorRow['target_text'] ?? null) ? $indicatorRow['target_text'] : $formatValue($indicatorRow['target_value'] ?? null,$nestedUnit) }}</td><td>{{ $formatValue($indicatorRow['actual'] ?? null,$nestedUnit) }}</td><td>{{ ($indicatorRow['achievement_percent'] ?? null) === null ? 'Not rateable' : number_format((float) $indicatorRow['achievement_percent'],1).'%' }}</td><td><span class="ce-status" style="--status:{{ $nestedColor }}">{{ $nestedStatus['label'] }}</span></td><td>{{ number_format((int) ($indicatorRow['reported_organizations'] ?? 0)) }} / {{ number_format((int) ($indicatorRow['expected_organizations'] ?? 0)) }}<span class="ce-meta">{{ number_format((float) ($indicatorRow['reporting_completeness'] ?? 0),1) }}%</span></td><td>{{ number_format((int) ($indicatorRow['verified_evidence_count'] ?? 0)) }} / {{ number_format((int) ($indicatorRow['evidence_count'] ?? 0)) }} verified</td></tr>
                                            @endforeach
                                            </tbody></table></div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="ce-table-filter-empty" data-ce-filter-empty><td colspan="10"><div class="ce-empty"><span class="ce-empty-mark">0</span><strong>No project rows match the local search</strong><p>Clear the keyword or project scorecard status. Server-side consolidation filters remain unchanged.</p></div></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="ce-scroll-tip"><span>Open “View indicators” to inspect every metric behind the scorecard.</span><span>Participant/beneficiary figures are reporting instances, not necessarily unique people.</span></div>
                @endif
            </section>
        @endif
    @endif
</div>
@endsection

@if($frameworkRecord)
@push('scripts')
<script src="{{ asset('admin/assets/vendors/js/apexcharts.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const currentLevel = @json($currentLevel);
    const distribution = {{ \Illuminate\Support\Js::from($performanceDistribution->values()->all()) }};
    const attainment = {{ \Illuminate\Support\Js::from($attainmentChartRows->all()) }};
    const projects = {{ \Illuminate\Support\Js::from($projectChartRows->all()) }};
    const search = document.querySelector('[data-ce-search]');
    const status = document.querySelector('[data-ce-status-filter]');
    const clear = document.querySelector('[data-ce-clear]');
    const rows = Array.from(document.querySelectorAll('[data-ce-main-row]'));
    const count = document.querySelector('[data-ce-visible-count]');
    const filterEmpty = document.querySelector('[data-ce-filter-empty]');

    const closeDetail = function (detailId) {
        const detail = document.getElementById(detailId);
        if (!detail) return;
        detail.hidden = true;
        const button = document.querySelector('[data-ce-toggle][data-target="' + detailId + '"]');
        if (button) {
            button.setAttribute('aria-expanded', 'false');
            const label = button.querySelector('span');
            if (label) label.textContent = currentLevel === 'project' ? 'View indicators' : 'View sources';
        }
    };
    const applyLocalFilter = function () {
        const term = (search?.value || '').trim().toLowerCase();
        const selectedStatus = status?.value || '';
        let visible = 0;
        rows.forEach(function (row) {
            const matches = (!term || (row.dataset.search || '').includes(term))
                && (!selectedStatus || row.dataset.status === selectedStatus);
            row.hidden = !matches;
            if (matches) {
                visible++;
            } else if (row.dataset.detailId) {
                closeDetail(row.dataset.detailId);
            }
        });
        if (count) count.textContent = String(visible);
        if (filterEmpty) filterEmpty.classList.toggle('is-visible', visible === 0);
    };
    search?.addEventListener('input', applyLocalFilter);
    status?.addEventListener('change', applyLocalFilter);
    clear?.addEventListener('click', function () {
        if (search) search.value = '';
        if (status) status.value = '';
        applyLocalFilter();
        search?.focus();
    });

    document.querySelectorAll('[data-ce-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            const detailId = button.dataset.target;
            const detail = document.getElementById(detailId);
            if (!detail) return;
            const opening = detail.hidden;
            detail.hidden = !opening;
            button.setAttribute('aria-expanded', opening ? 'true' : 'false');
            const label = button.querySelector('span');
            if (label) label.textContent = opening ? 'Hide detail' : (currentLevel === 'project' ? 'View indicators' : 'View sources');
            if (opening) detail.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        });
    });
    document.querySelectorAll('[data-ce-close-detail]').forEach(function (button) {
        button.addEventListener('click', function () { closeDetail(button.dataset.target); });
    });

    const focusRegister = function (term, selectedStatus) {
        if (search) search.value = term || '';
        if (status && selectedStatus && Array.from(status.options).some(function (option) { return option.value === selectedStatus; })) {
            status.value = selectedStatus;
        }
        applyLocalFilter();
        document.getElementById('ce-register')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };
    const baseChart = {
        chart: { fontFamily: 'Inter, Arial, sans-serif', foreColor: '#657980', toolbar: { show: false }, animations: { speed: 420 } },
        dataLabels: { style: { fontSize: '10px', fontWeight: 700 } },
        legend: { fontSize: '11px', fontWeight: 600 },
        grid: { borderColor: '#e5edef', strokeDashArray: 3 },
        tooltip: { theme: 'light' }
    };
    const renderChart = function (selector, options) {
        const element = document.querySelector(selector);
        if (element && window.ApexCharts) new ApexCharts(element, options).render();
    };
    if (distribution.length) {
        renderChart('#ce-performance-chart', {
            ...baseChart,
            chart: { ...baseChart.chart, type: 'donut', height: 315, events: { dataPointSelection: function (_event, _context, selection) { const item = distribution[selection.dataPointIndex]; if (item) focusRegister('', item.code); } } },
            series: distribution.map(function (item) { return Number(item.count) || 0; }),
            labels: distribution.map(function (item) { return item.label; }),
            colors: distribution.map(function (item) { return item.color; }),
            stroke: { colors: ['#fff'], width: 3 },
            plotOptions: { pie: { donut: { size: '67%', labels: { show: true, total: { show: true, label: 'Indicators', formatter: function () { return distribution.reduce(function (sum,item) { return sum + Number(item.count || 0); },0); } } } } } },
            legend: { position: 'bottom' },
            dataLabels: { enabled: false }
        });
    }
    if (currentLevel === 'project' && projects.length) {
        renderChart('#ce-level-chart', {
            ...baseChart,
            chart: { ...baseChart.chart, type: 'bar', height: Math.max(315, projects.length * 43), events: { dataPointSelection: function (_event,_context,selection) { const item=projects[selection.dataPointIndex]; if(item) focusRegister(item.code,''); } } },
            series: [
                { name: 'Average attainment', data: projects.map(function (item) { return item.achievement === null ? 0 : Number(item.achievement); }) },
                { name: 'Reporting completeness', data: projects.map(function (item) { return Number(item.completeness) || 0; }) }
            ],
            colors: ['#075c7a','#73a9b6'],
            plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '62%' } },
            xaxis: { categories: projects.map(function (item) { return item.code; }), min: 0, labels: { formatter: function (value) { return Math.round(value) + '%'; } } },
            annotations: { xaxis: [{ x: 100, borderColor: '#a56a17', strokeDashArray: 4, label: { text: '100%', style: { background: '#a56a17',color:'#fff',fontSize:'9px' } } }] }
        });
    } else if (attainment.length) {
        const attainmentMax = Math.max(100, ...attainment.map(function (item) { return Number(item.achievement) || 0; }));
        renderChart('#ce-level-chart', {
            ...baseChart,
            chart: { ...baseChart.chart, type: 'bar', height: Math.max(315, attainment.length * 34), events: { dataPointSelection: function (_event,_context,selection) { const item=attainment[selection.dataPointIndex]; if(item) focusRegister(item.code,''); } } },
            series: [{ name: 'Target attainment', data: attainment.map(function (item) { return Number(item.achievement) || 0; }) }],
            colors: attainment.map(function (item) { return item.color; }),
            plotOptions: { bar: { horizontal: true, distributed: true, borderRadius: 4, barHeight: '60%' } },
            xaxis: { categories: attainment.map(function (item) { return item.code; }), min: 0, max: Math.ceil(attainmentMax / 25) * 25, labels: { formatter: function (value) { return Math.round(value) + '%'; } } },
            dataLabels: { enabled: true, formatter: function (value) { return Number(value).toFixed(1) + '%'; }, style: { fontSize: '9px' } },
            legend: { show: false },
            annotations: { xaxis: [{ x: 100, borderColor: '#a56a17', strokeDashArray: 4 }] }
        });
    }

    const year = document.querySelector('[data-ce-year]');
    const period = document.querySelector('[data-ce-period]');
    const portfolio = document.querySelector('[data-ce-portfolio]');
    const project = document.querySelector('[data-ce-project]');
    const indicator = document.querySelector('[data-ce-indicator]');
    const filterOptions = function (select, attribute, selected, clearInvalid) {
        if (!select) return;
        let validSelection = !select.value;
        Array.from(select.options).forEach(function (option, index) {
            if (index === 0) return;
            const allowed = !selected || !option.dataset[attribute] || option.dataset[attribute] === selected;
            option.disabled = !allowed;
            option.hidden = !allowed;
            if (option.selected && allowed) validSelection = true;
        });
        if (clearInvalid && !validSelection) select.value = '';
    };
    const syncScopeOptions = function (clearInvalid) {
        filterOptions(period, 'year', year?.value || '', clearInvalid);
        filterOptions(project, 'portfolio', portfolio?.value || '', clearInvalid);
        filterOptions(indicator, 'project', project?.value || '', clearInvalid);
    };
    year?.addEventListener('change', function () { syncScopeOptions(true); });
    portfolio?.addEventListener('change', function () { syncScopeOptions(true); });
    project?.addEventListener('change', function () { syncScopeOptions(true); });
    syncScopeOptions(false);

    let printState = [];
    window.addEventListener('beforeprint', function () {
        printState = Array.from(document.querySelectorAll('[data-ce-detail]')).map(function (row) { return row.hidden; });
        document.querySelectorAll('[data-ce-detail]').forEach(function (row) { row.hidden = false; });
    });
    window.addEventListener('afterprint', function () {
        document.querySelectorAll('[data-ce-detail]').forEach(function (row,index) { row.hidden = printState[index] ?? true; });
    });
    document.querySelector('[data-ce-print]')?.addEventListener('click', function () { window.print(); });
});
</script>
@endpush
@endif
