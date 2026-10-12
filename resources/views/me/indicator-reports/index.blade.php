@extends('layouts.app')

@section('title', 'M&E Indicator Reports')
@section('lean_admin_scripts', '1')

@push('styles')
    @include('me.indicator-reports.partials.styles')
@endpush

@section('content')
@php
    $filters = $filters ?? [];
    $modes = $modes ?? ['individual' => 'Individual indicator report', 'consolidated' => 'Consolidated indicator report'];
    $currentMode = array_key_exists((string) ($filters['mode'] ?? $mode ?? 'individual'), $modes)
        ? (string) ($filters['mode'] ?? $mode)
        : 'individual';
    $isIndividual = $currentMode === 'individual';
    $frameworkRecord = $frameworkContext ?? ($framework ?? null);
    $indicatorRows = collect($indicatorRows ?? []);
    $projectRows = collect($projectRows ?? []);
    $contributionRows = collect($contributionRows ?? []);
    $evidenceRows = collect($evidenceRows ?? []);
    $thinkTankSubmissionRows = collect($thinkTankSubmissionRows ?? []);
    $performanceDistribution = collect($performanceDistribution ?? []);
    $reportSummary = $reportSummary ?? [];
    $quality = $quality ?? [];
    $selectedRow = $selectedIndicatorRow ?? ($isIndividual ? $indicatorRows->first() : null);
    $selectedIndicatorRecord = data_get($selectedRow, 'indicator');
    $referenceSheet = $selectedIndicatorRecord?->approvedReferenceSheet;
    $calculationRule = $selectedIndicatorRecord?->calculationRules?->firstWhere('is_active', true);
    $hasReportData = (bool) ($hasReportData ?? $indicatorRows->isNotEmpty());
    $hasExportableReport = $hasReportData && (! $isIndividual || $selectedIndicatorRecord);
    $filterTotal = (int) ($activeFilterCount ?? 0);
    $exportParameters = $exportQuery ?? collect($filters)->filter(fn ($value) => filled($value))->all();
    $canExport = (bool) ($canExport ?? false);
    $generatedAt = $generatedAt ?? now();
    $scopeLabel = $scopeLabel ?? 'All authorized approved indicator results';
    $performanceStatuses = $performanceStatuses ?? [];
    $thematicAreas = $thematicAreas ?? \App\Models\MeIndicatorAchievement::PRIORITY_THEMES;
    $qualityLabels = [
        'missing_targets' => 'Reported indicators without an approved target',
        'not_reported' => 'Indicators without an approved result',
        'non_additive_or_qualitative' => 'Qualitative or non-additive indicators',
        'missing_required_evidence' => 'Reported indicators missing required evidence',
        'incomplete_reporting' => 'Indicators below full reporting coverage',
        'weighted_values_without_weights' => 'Weighted results missing valid weight inputs',
    ];
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
            if (is_object($value) && method_exists($value, 'format')) return $value->format('d M Y, H:i');
            $timestamp = strtotime((string) $value);
            return $timestamp ? date('d M Y, H:i', $timestamp) : (string) $value;
        } catch (\Throwable) {
            return (string) $value;
        }
    };
    $targetDisplay = static function (array $row) use ($formatValue): string {
        $targetText = trim((string) ($row['target_text'] ?? ''));
        return $targetText !== '' ? $targetText : $formatValue($row['target_value'] ?? null, $row['unit_label'] ?? null);
    };
    $attainmentRows = $indicatorRows
        ->filter(fn (array $row) => ($row['achievement_percent'] ?? null) !== null)
        ->sortByDesc('achievement_percent')
        ->take(14)
        ->map(fn (array $row) => [
            'code' => $row['indicator']?->indicator_code ?: 'Uncoded',
            'name' => $row['indicator']?->name ?: 'Indicator',
            'value' => round((float) $row['achievement_percent'], 1),
            'color' => data_get($row, 'classification.color', '#64748b'),
        ])->values();
    $sourceChartRows = $contributionRows
        ->filter(fn (array $row) => is_numeric($row['actual'] ?? null))
        ->map(fn (array $row) => [
            'label' => trim(($row['organization'] ?? 'Think Tank').(($row['period'] ?? null) ? ' · '.$row['period'] : '')),
            'value' => (float) $row['actual'],
        ])
        ->values();
@endphp

<div class="mel-indicator-reports">
    <header class="ir-header">
        <div>
            <span class="ir-eyebrow">Monitoring, Evaluation and Learning</span>
            <h1>M&amp;E Indicator Reports</h1>
            <p>Build a governed dossier for one indicator or review a consolidated report across the filtered Results Framework, with approved source contributions, evidence and export-ready audit detail.</p>
        </div>
        <div class="ir-header-side">
            <div class="ir-scope">
                <span>Current approved-results scope</span>
                <strong>{{ $scopeLabel }} &middot; Generated {{ $generatedAt->format('d M Y, H:i') }}</strong>
            </div>
            <div class="ir-actions ir-no-print" aria-label="Download and print this indicator report">
                @if($canExport && $hasExportableReport)
                    <a class="ir-btn ir-btn-header" href="{{ route('budget.me.indicator-reports.excel', $exportParameters) }}"><i class="feather-file-text" aria-hidden="true"></i>Excel</a>
                    <a class="ir-btn ir-btn-header" href="{{ route('budget.me.indicator-reports.csv', $exportParameters) }}"><i class="feather-list" aria-hidden="true"></i>CSV</a>
                    <a class="ir-btn ir-btn-header" href="{{ route('budget.me.indicator-reports.pdf', $exportParameters) }}"><i class="feather-download" aria-hidden="true"></i>PDF</a>
                @else
                    <span class="ir-btn ir-btn-header" aria-disabled="true" title="Exports become available when the selected scope contains reportable indicator data."><i class="feather-file-text" aria-hidden="true"></i>Excel</span>
                    <span class="ir-btn ir-btn-header" aria-disabled="true" title="Exports become available when the selected scope contains reportable indicator data."><i class="feather-list" aria-hidden="true"></i>CSV</span>
                    <span class="ir-btn ir-btn-header" aria-disabled="true" title="Exports become available when the selected scope contains reportable indicator data."><i class="feather-download" aria-hidden="true"></i>PDF</span>
                @endif
                <button class="ir-btn ir-btn-header" type="button" data-ir-print @disabled(! $hasExportableReport)><i class="feather-printer" aria-hidden="true"></i>Print</button>
            </div>
        </div>
    </header>

    <aside class="ir-guardrail" aria-label="Official indicator reporting rule">
        <span class="ir-guardrail-mark">APR</span>
        <div>
            <strong>Approved-only indicator reporting is active</strong>
            <p>Only finally approved or archived source results influence this report. Draft, submitted, returned, reviewed and merely verified records are excluded. Each indicator follows its configured time and organization roll-up rules; unlike units are never added together.</p>
        </div>
        <span class="ir-approved-pill">Final approvals only</span>
    </aside>

    @if(isset($errors) && $errors->any())
        <div class="ir-alert" role="alert"><strong>The requested report scope could not be applied.</strong> {{ $errors->first() }}</div>
    @endif

    <nav class="ir-mode-tabs ir-no-print" aria-label="Indicator report mode">
        <a class="ir-mode-tab {{ $isIndividual ? 'active' : '' }}"
           href="{{ route('budget.me.indicator-reports.index', array_merge($exportParameters, ['mode' => 'individual'])) }}"
           @if($isIndividual) aria-current="page" @endif>
            <span class="ir-mode-icon"><i class="feather-file" aria-hidden="true"></i></span>
            <span class="ir-mode-copy"><strong>Individual indicator report</strong><small>Select one indicator to open its governed profile, performance, source contributions and evidence dossier.</small></span>
            <i class="feather-check-circle ir-mode-check" aria-hidden="true"></i>
        </a>
        <a class="ir-mode-tab {{ ! $isIndividual ? 'active' : '' }}"
           href="{{ route('budget.me.indicator-reports.index', array_merge($exportParameters, ['mode' => 'consolidated'])) }}"
           @if(! $isIndividual) aria-current="page" @endif>
            <span class="ir-mode-icon"><i class="feather-layers" aria-hidden="true"></i></span>
            <span class="ir-mode-copy"><strong>Consolidated indicator report</strong><small>Review every filtered indicator with results-area summaries, approved contributor detail and evidence disclosures.</small></span>
            <i class="feather-check-circle ir-mode-check" aria-hidden="true"></i>
        </a>
    </nav>

    @if(!$frameworkRecord)
        <section class="ir-panel" aria-labelledby="ir-framework-empty-title">
            <div class="ir-empty">
                <span class="ir-empty-mark">RF</span>
                <strong id="ir-framework-empty-title">The active Results Framework is not available</strong>
                <p>Install and activate the controlled ATTP Results Framework before indicator dossiers, approved targets and governed calculations can be reported.</p>
                @canany(['me.configuration.view', 'me.configuration.manage'])
                    <a class="ir-btn ir-btn-primary ir-no-print" href="{{ route('budget.me.framework.index') }}">Open framework administration</a>
                @endcanany
            </div>
        </section>
    @else
        <details class="ir-panel ir-filter ir-no-print" @if($filterTotal > 0 || $isIndividual) open @endif>
            <summary class="ir-panel-head">
                <div><h2>Indicator report scope and filters</h2><p>Every KPI, visual, table and download uses this exact approved-results scope.</p></div>
                <div class="ir-summary-right"><span class="ir-badge">{{ $filterTotal }} active {{ str('filter')->plural($filterTotal) }}</span><span class="ir-chevron" aria-hidden="true"><i class="feather-chevron-down"></i></span></div>
            </summary>
            <div class="ir-panel-body">
                <form method="GET" action="{{ route('budget.me.indicator-reports.index') }}" class="ir-filter-grid" data-ir-filter-form>
                    <input type="hidden" name="mode" value="{{ $currentMode }}">

                    <div class="ir-field">
                        <label for="ir-project-year">Target project year</label>
                        <select id="ir-project-year" name="project_year" class="form-select">
                            @foreach(range(1,4) as $year)<option value="{{ $year }}" @selected((int) ($filters['project_year'] ?? 1) === $year)>Project Year {{ $year }}</option>@endforeach
                        </select>
                        <small>Selects the approved target benchmark.</small>
                    </div>
                    <div class="ir-field">
                        <label for="ir-reporting-year">Reporting year</label>
                        <select id="ir-reporting-year" name="reporting_year" class="form-select" data-ir-year>
                            <option value="">All reporting years</option>
                            @foreach($reportingYears ?? [] as $year)<option value="{{ $year }}" @selected((int) ($filters['reporting_year'] ?? 0) === (int) $year)>{{ $year }}</option>@endforeach
                        </select>
                    </div>
                    <div class="ir-field ir-field-wide">
                        <label for="ir-period">Reporting period</label>
                        <select id="ir-period" name="reporting_period_id" class="form-select" data-ir-period>
                            <option value="">All periods in scope</option>
                            @foreach($periods ?? [] as $item)
                                <option value="{{ $item->id }}" data-year="{{ $item->reporting_year }}" data-portfolio="{{ $item->portfolio_id }}" @selected((string) ($filters['reporting_period_id'] ?? '') === (string) $item->id)>
                                    {{ $item->label }}{{ $item->reporting_year ? ' · '.$item->reporting_year : '' }}
                                </option>
                            @endforeach
                        </select>
                        <small>A selected reporting period takes precedence over reporting year.</small>
                    </div>
                    <div class="ir-field">
                        <label for="ir-portfolio">Portfolio</label>
                        <select id="ir-portfolio" name="portfolio_id" class="form-select" data-ir-portfolio>
                            <option value="">All authorized portfolios</option>
                            @foreach($portfolios ?? [] as $item)<option value="{{ $item->id }}" @selected((string) ($filters['portfolio_id'] ?? '') === (string) $item->id)>{{ $item->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="ir-field ir-field-wide">
                        <label for="ir-project">Project / results component</label>
                        <select id="ir-project" name="component_id" class="form-select" data-ir-project>
                            <option value="">PDO and all authorized projects</option>
                            @foreach($projects ?? [] as $item)
                                <option value="{{ $item->id }}" data-portfolio="{{ $item->program?->sector?->id }}" @selected((string) ($filters['component_id'] ?? '') === (string) $item->id)>{{ $item->project_id }} &middot; {{ $item->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ir-field ir-field-wide">
                        <label for="ir-indicator">Indicator @if($isIndividual)<span class="required">*</span>@endif</label>
                        <select id="ir-indicator" name="indicator_id" class="form-select" data-ir-indicator @if($isIndividual) required @endif>
                            <option value="">{{ $isIndividual ? 'Choose an indicator for its dossier' : 'All indicators' }}</option>
                            @foreach($indicators ?? [] as $item)
                                <option value="{{ $item->id }}" data-project="{{ $item->project_component_id }}" data-results-level="{{ $item->results_level }}" @selected((string) ($filters['indicator_id'] ?? '') === (string) $item->id)>{{ $item->indicator_code }} &middot; {{ $item->name }}</option>
                            @endforeach
                        </select>
                        <small>{{ $isIndividual ? 'An indicator selection is required before the individual dossier or its downloads can be generated.' : 'Leave blank to include every indicator matching the other filters.' }}</small>
                    </div>
                    <div class="ir-field">
                        <label for="ir-think-tank">Think Tank / contributor</label>
                        <select id="ir-think-tank" name="think_tank_id" class="form-select">
                            <option value="">All authorized contributors</option>
                            @foreach($thinkTanks ?? [] as $item)<option value="{{ $item->id }}" @selected((string) ($filters['think_tank_id'] ?? '') === (string) $item->id)>{{ $item->name }}{{ $item->country ? ' · '.$item->country : '' }}</option>@endforeach
                        </select>
                    </div>
                    <div class="ir-field">
                        <label for="ir-results-level">Results level</label>
                        <select id="ir-results-level" name="results_level" class="form-select" data-ir-results-level>
                            <option value="">PDO and intermediate results</option>
                            <option value="pdo" @selected(($filters['results_level'] ?? null) === 'pdo')>Project Development Objective</option>
                            <option value="intermediate_results" @selected(($filters['results_level'] ?? null) === 'intermediate_results')>Intermediate Results</option>
                        </select>
                    </div>
                    <div class="ir-field">
                        <label for="ir-performance">Performance classification</label>
                        <select id="ir-performance" name="performance_status" class="form-select" @disabled($isIndividual)>
                            <option value="">All performance statuses</option>
                            @foreach($performanceStatuses as $value => $label)<option value="{{ $value }}" @selected(($filters['performance_status'] ?? null) === $value)>{{ $label }}</option>@endforeach
                        </select>
                        <small>{{ $isIndividual ? 'Classification does not hide a specifically selected dossier.' : 'Filters the consolidated register after governed calculation.' }}</small>
                    </div>
                    <div class="ir-field">
                        <label for="ir-country">Country</label>
                        <select id="ir-country" name="country" class="form-select">
                            <option value="">All countries</option>
                            @foreach($countries ?? [] as $country)<option value="{{ $country }}" @selected(($filters['country'] ?? null) === $country)>{{ $country }}</option>@endforeach
                        </select>
                    </div>
                    <div class="ir-field ir-field-wide">
                        <label for="ir-theme">ATTP priority thematic area</label>
                        <select id="ir-theme" name="thematic_area" class="form-select">
                            <option value="">All thematic areas</option>
                            @foreach($thematicAreas as $value => $label)<option value="{{ $value }}" @selected(($filters['thematic_area'] ?? null) === $value)>{{ $label }}</option>@endforeach
                        </select>
                    </div>

                    <div class="ir-filter-actions">
                        <p class="ir-filter-tip"><strong>Reading this report:</strong> the target year selects the approved benchmark. The reporting year or period selects approved actuals. Contributor, country and thematic filters recalculate both source contributions and expected reporting coverage.</p>
                        <div class="ir-actions">
                            <a class="ir-btn ir-btn-secondary" href="{{ route('budget.me.indicator-reports.index', ['mode' => $currentMode]) }}">Clear filters</a>
                            <button class="ir-btn ir-btn-primary" type="submit">Generate report</button>
                        </div>
                    </div>
                </form>
            </div>
        </details>

        @if($isIndividual && !$selectedIndicatorRecord)
            <section class="ir-panel" aria-labelledby="ir-select-indicator-title">
                <div class="ir-empty">
                    <span class="ir-empty-mark">IND</span>
                    <strong id="ir-select-indicator-title">Choose an indicator to build its report</strong>
                    <p>Select one indicator above to open its approved profile, target and actual performance, calculation methodology, source contributions and evidence trail.</p>
                </div>
            </section>
        @elseif(!$hasReportData)
            <section class="ir-panel" aria-labelledby="ir-no-report-data-title">
                <div class="ir-empty">
                    <span class="ir-empty-mark">0</span>
                    <strong id="ir-no-report-data-title">No approved indicator data matches this report scope</strong>
                    <p>Clear one or more filters, select another period or confirm that the indicator is active in the current Results Framework.</p>
                    <a class="ir-btn ir-btn-secondary ir-no-print" href="{{ route('budget.me.indicator-reports.index', ['mode' => $currentMode]) }}">Clear report filters</a>
                </div>
            </section>
        @else
            <section class="ir-metrics" aria-label="Indicator report summary">
                <article class="ir-metric"><span class="ir-metric-label">{{ $isIndividual ? 'Indicator in dossier' : 'Indicators in report' }}</span><strong>{{ number_format((int) ($reportSummary['indicator_count'] ?? 0)) }}</strong><small>{{ number_format((int) ($reportSummary['reported_indicator_count'] ?? 0)) }} contain approved results</small></article>
                <article class="ir-metric" style="--metric:#176b87"><span class="ir-metric-label">Approved contributions</span><strong>{{ number_format((int) ($reportSummary['approved_contribution_count'] ?? 0)) }}</strong><small>{{ number_format((int) ($reportSummary['organization_count'] ?? 0)) }} reporting {{ str('organization')->plural((int) ($reportSummary['organization_count'] ?? 0)) }}</small></article>
                <article class="ir-metric" style="--metric:#6b5ca5"><span class="ir-metric-label">Average scorecard attainment</span><strong>{{ ($reportSummary['average_achievement'] ?? null) === null ? '—' : number_format((float) $reportSummary['average_achievement'],1).'%' }}</strong><small>Unweighted rated indicators, each capped at 100%</small></article>
                <article class="ir-metric" style="--metric:#16815f"><span class="ir-metric-label">Reporting completeness</span><strong>{{ number_format((float) ($reportSummary['reporting_completeness'] ?? 0),1) }}%</strong><small>Approved reporters against the expected population</small></article>
                <article class="ir-metric" style="--metric:#b26b14"><span class="ir-metric-label">Evidence verification</span><strong>{{ ($reportSummary['evidence_verification_rate'] ?? null) === null ? '—' : number_format((float) $reportSummary['evidence_verification_rate'],1).'%' }}</strong><small>{{ number_format((int) ($reportSummary['verified_evidence_count'] ?? 0)) }} of {{ number_format((int) ($reportSummary['evidence_count'] ?? 0)) }} evidence links verified</small></article>
                <article class="ir-metric" style="--metric:#2d8ea3"><span class="ir-metric-label">Participant / beneficiary instances</span><strong>{{ number_format((int) ($reportSummary['beneficiary_count'] ?? 0)) }}</strong><small>Reporting instances; not necessarily unique people</small></article>
            </section>

            @if($isIndividual && $selectedIndicatorRecord)
                @php
                    $selectedStatus = data_get($selectedRow, 'classification', []);
                    $selectedStatusColor = $selectedStatus['color'] ?? '#64748b';
                    $selectedAttainment = $selectedRow['achievement_percent'] ?? null;
                    $selectedUnit = $selectedRow['unit_label'] ?? ($selectedIndicatorRecord->unit?->symbol ?: $selectedIndicatorRecord->unit?->name);
                    $selectedProjectRecord = $selectedIndicatorRecord->projectComponent;
                    $reportingSourceLabel = match($selectedIndicatorRecord->reporting_source) {
                        'secretariat' => 'Secretariat',
                        'think_tank' => 'Think Tank',
                        'both' => 'Secretariat and Think Tank',
                        'system_calculated' => 'System calculated',
                        default => str($selectedIndicatorRecord->reporting_source ?: 'Not configured')->headline(),
                    };
                @endphp
                <section class="ir-panel" aria-labelledby="ir-profile-title">
                    <div class="ir-panel-head">
                        <div><h2 id="ir-profile-title">Indicator profile and governance</h2><p>Controlled definition, measurement, ownership and approved performance for the selected indicator.</p></div>
                        <span class="ir-badge" style="color:{{ $selectedStatusColor }};background:{{ $selectedStatusColor }}18">{{ $selectedStatus['label'] ?? 'Not rated' }}</span>
                    </div>
                    <div class="ir-dossier">
                        <div class="ir-profile-main">
                            <div class="ir-profile-heading">
                                <div>
                                    <span class="ir-code">{{ $selectedIndicatorRecord->indicator_code ?: 'Uncoded indicator' }}</span>
                                    <h2>{{ $selectedIndicatorRecord->name }}</h2>
                                    <p>{{ $selectedIndicatorRecord->resultsLevelLabel() }} &middot; {{ $selectedProjectRecord?->project_id ?: 'PDO' }} &middot; {{ $selectedProjectRecord?->name ?: 'Project Development Objective / Cross-project results' }}</p>
                                </div>
                                <span class="ir-badge neutral">{{ str($selectedIndicatorRecord->value_type ?: 'number')->headline() }}</span>
                            </div>
                            <div class="ir-facts">
                                <div class="ir-fact"><small>Baseline</small><strong>{{ $formatValue($selectedRow['baseline'] ?? null, $selectedUnit) }}{{ $selectedIndicatorRecord->baseline_year ? ' ('.$selectedIndicatorRecord->baseline_year.')' : '' }}</strong></div>
                                <div class="ir-fact"><small>Approved target</small><strong>{{ $targetDisplay($selectedRow) }} &middot; Project Year {{ (int) ($filters['project_year'] ?? 1) }}</strong></div>
                                <div class="ir-fact"><small>Approved period actual</small><strong>{{ $formatValue($selectedRow['period_actual'] ?? null, $selectedUnit) }}</strong></div>
                                <div class="ir-fact"><small>{{ $selectedIndicatorRecord->is_cumulative ? 'Cumulative actual' : 'Report actual' }}</small><strong>{{ $formatValue($selectedRow['actual'] ?? null, $selectedUnit) }}</strong></div>
                                <div class="ir-fact"><small>Reporting frequency</small><strong>{{ $selectedIndicatorRecord->frequency?->indicatorCadenceLabel() ?: 'Not configured' }}</strong></div>
                                <div class="ir-fact"><small>Reporting responsibility</small><strong>{{ $reportingSourceLabel }}</strong></div>
                                <div class="ir-fact"><small>Responsible party</small><strong>{{ $selectedIndicatorRecord->responsiblePerson?->name ?: $selectedIndicatorRecord->responsible_party ?: 'Not assigned' }}</strong></div>
                                <div class="ir-fact"><small>Evidence requirement</small><strong>{{ $selectedIndicatorRecord->requires_evidence ? 'Required' : 'Not mandatory' }} &middot; {{ $selectedIndicatorRecord->meansOfVerificationFolder?->name ?: $selectedIndicatorRecord->meansOfVerification?->title ?: 'MOV not linked' }}</strong></div>
                            </div>
                            <div class="ir-definition-grid">
                                <div class="ir-definition"><h3>Definition and rationale</h3><p>{{ $referenceSheet?->definition ?: $selectedIndicatorRecord->definitions ?: 'No controlled definition has been recorded.' }}@if($referenceSheet?->rationale)\n\n{{ $referenceSheet->rationale }}@endif</p></div>
                                <div class="ir-definition"><h3>Disaggregation and collection</h3><p><strong>Required disaggregation:</strong> {{ $selectedIndicatorRecord->disaggregationChain() ?: 'Standard reporting scope' }}\n<strong>Collection method:</strong> {{ $referenceSheet?->data_collection_method ?: $selectedIndicatorRecord->data_collection_method ?: 'Not configured' }}\n<strong>Primary source:</strong> {{ $selectedIndicatorRecord->primary_source ?: $referenceSheet?->data_sources ?: 'Not configured' }}</p></div>
                            </div>
                        </div>
                        <aside class="ir-status-panel">
                            <div class="ir-status-ring" style="--status:{{ $selectedStatusColor }}"><strong>{{ $selectedAttainment === null ? 'N/R' : number_format((float) $selectedAttainment,1).'%' }}</strong></div>
                            <h3>{{ $selectedStatus['label'] ?? 'Not rated' }}</h3>
                            <p>{{ $selectedAttainment === null ? 'An approved numeric or Yes/No actual and target are required for target attainment.' : 'Approved actual against the selected target benchmark.' }}</p>
                            <span class="ir-meta">Trend: {{ data_get($selectedRow, 'trend.label', 'Not available') }} &middot; Variance: {{ $formatValue($selectedRow['variance_value'] ?? $selectedRow['variance'] ?? null, $selectedUnit) }}</span>
                        </aside>
                    </div>
                </section>

                <section class="ir-panel" aria-labelledby="ir-methodology-title">
                    <div class="ir-panel-head"><div><h2 id="ir-methodology-title">Indicator methodology and calculation</h2><p>The approved definition and governed calculation logic behind this dossier.</p></div><span class="ir-badge neutral">Traceable calculation</span></div>
                    <div class="ir-panel-body">
                        <div class="ir-method-list">
                            <div class="ir-method"><strong>Time aggregation</strong><p>{{ $selectedRow['time_aggregation_label'] ?? $selectedIndicatorRecord->aggregationMethodLabel() }}. {{ $selectedIndicatorRecord->is_cumulative ? 'Approved periods are accumulated according to the configured rule.' : 'The selected reporting scope is treated as period-specific.' }}</p></div>
                            <div class="ir-method"><strong>Organization roll-up</strong><p>{{ $selectedRow['organization_rollup_label'] ?? 'Not configured' }}. Qualitative and non-additive values remain attributable to their source.</p></div>
                            <div class="ir-method"><strong>Calculation rule</strong><p>{{ $referenceSheet?->calculation_method ?: ($selectedRow['calculation_note'] ?? 'Calculated from finally approved qualifying indicator results only.') }}@if($calculationRule) Rule {{ $calculationRule->calculation_key }}, version {{ $calculationRule->version }}.@endif</p></div>
                            <div class="ir-method"><strong>Inclusion and verification</strong><p>{{ $referenceSheet?->inclusion_criteria ?: 'Finally approved records matching the active report scope are included.' }} {{ $referenceSheet?->verification_responsibility ? 'Verification: '.$referenceSheet->verification_responsibility.'.' : '' }}</p></div>
                        </div>
                    </div>
                </section>

                <nav class="ir-analysis-tabs ir-no-print" role="tablist" aria-label="Indicator report data views">
                    <button type="button" class="ir-analysis-tab active" role="tab" id="ir-data-tab" aria-controls="ir-data-panel" aria-selected="true" data-ir-view-tab="table"><i class="feather-table" aria-hidden="true"></i> Data table</button>
                    <button type="button" class="ir-analysis-tab" role="tab" id="ir-submission-tab" aria-controls="ir-submission-ledger-panel" aria-selected="false" data-ir-view-tab="submissions"><i class="feather-inbox" aria-hidden="true"></i> Think Tank submissions</button>
                    <button type="button" class="ir-analysis-tab" role="tab" id="ir-chart-tab" aria-controls="ir-chart-panel" aria-selected="false" data-ir-view-tab="charts"><i class="feather-bar-chart-2" aria-hidden="true"></i> Graphical analysis</button>
                </nav>

                <section class="ir-panel" id="ir-data-panel" role="tabpanel" aria-labelledby="ir-data-tab" data-ir-view-panel="table">
                    <div class="ir-panel-head"><div><h2 id="ir-contributions-title">Approved source contributions</h2><p>Every organization-level value used to produce the selected indicator result.</p></div><span class="ir-badge">{{ $contributionRows->count() }} {{ str('contribution')->plural($contributionRows->count()) }}</span></div>
                    @if($contributionRows->isEmpty())
                        <div class="ir-empty"><span class="ir-empty-mark">SRC</span><strong>No approved source contribution is available</strong><p>The indicator profile remains reportable, but no finally approved value matches this period and contributor scope.</p></div>
                    @else
                        <div class="ir-table-wrap" role="region" aria-label="Approved source contributions" tabindex="0">
                            <table class="ir-source-table">
                                <thead><tr><th>Organization</th><th>Country / period</th><th>Approved contribution</th><th>Weight inputs</th><th>Data source</th><th>Achievements</th><th>Evidence links</th><th>Approved at</th></tr></thead>
                                <tbody>
                                @foreach($contributionRows as $source)
                                    <tr>
                                        <td><strong>{{ $source['organization'] ?? 'Secretariat / Internal' }}</strong><span class="ir-meta">Result {{ $source['result_id'] ?? 'not identified' }}</span></td>
                                        <td>{{ $source['country'] ?: 'Country not recorded' }}<span class="ir-meta">{{ $source['period'] ?: 'Period not recorded' }}</span></td>
                                        <td><span class="ir-value">{{ $formatValue($source['actual'] ?? null, $source['unit'] ?? null) }}</span></td>
                                        <td>{{ ($source['rollup_numerator'] ?? null) === null && ($source['rollup_denominator'] ?? null) === null ? 'Not required' : $formatValue($source['rollup_numerator'] ?? null).' / '.$formatValue($source['rollup_denominator'] ?? null) }}</td>
                                        <td>{{ $source['data_source'] ?: 'Not recorded' }}</td>
                                        <td><strong>{{ number_format((int) ($source['achievement_count'] ?? 0)) }}</strong> records</td>
                                        <td><strong>{{ number_format((int) ($source['verified_evidence_count'] ?? 0)) }}/{{ number_format((int) ($source['evidence_count'] ?? 0)) }}</strong> verified</td>
                                        <td>{{ $formatDate($source['approved_at'] ?? null) }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>

                <section class="ir-panel" id="ir-chart-panel" role="tabpanel" aria-labelledby="ir-chart-tab" data-ir-view-panel="charts" hidden>
                    <div class="ir-panel-head"><div><h2>Approved Think Tank contributions</h2><p>Numeric, finally approved source values for this indicator and report scope.</p></div><span class="ir-badge neutral">{{ $sourceChartRows->count() }} chartable values</span></div>
                    @if($sourceChartRows->isNotEmpty())
                        <div id="ir-source-chart" class="ir-chart ir-chart-tall" role="img" aria-label="Bar chart of approved Think Tank indicator contributions"></div>
                    @else
                        <div class="ir-empty"><span class="ir-empty-mark">CH</span><strong>No numeric approved contributions can be charted</strong><p>Open the data table for qualitative values or select a report scope with approved numeric Think Tank contributions.</p></div>
                    @endif
                </section>

                <section class="ir-panel" aria-labelledby="ir-evidence-title" data-ir-view-panel="table">
                    <div class="ir-panel-head"><div><h2 id="ir-evidence-title">Evidence register and verification</h2><p>Evidence links supporting the selected indicator's approved source results.</p></div><span class="ir-badge {{ $evidenceRows->where('verified', false)->isNotEmpty() ? 'warning' : '' }}">{{ number_format($evidenceRows->where('verified', true)->count()) }}/{{ number_format($evidenceRows->count()) }} verified</span></div>
                    <div class="ir-panel-body">
                        @if($evidenceRows->isEmpty())
                            <div class="ir-empty" style="min-height:150px"><span class="ir-empty-mark">EV</span><strong>No evidence links were recorded for this result</strong><p>Required-evidence exceptions are disclosed in the quality controls below.</p></div>
                        @else
                            <div class="ir-link-list">
                                @foreach($evidenceRows as $evidence)
                                    <div class="ir-evidence-item">
                                        <div><strong>{{ $evidence['title'] ?: 'Untitled evidence link' }}</strong><small>{{ $evidence['source'] ?: 'Evidence source not recorded' }} &middot; {{ $evidence['organization'] ?: 'Organization not recorded' }} &middot; {{ $evidence['period'] ?: 'Period not recorded' }}</small></div>
                                        <span class="ir-badge {{ ($evidence['verified'] ?? false) ? '' : 'warning' }}">{{ ($evidence['verified'] ?? false) ? 'Verified' : str($evidence['status'] ?: 'Pending')->headline() }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </section>
            @else
                <nav class="ir-analysis-tabs ir-no-print" role="tablist" aria-label="Indicator report data views">
                    <button type="button" class="ir-analysis-tab active" role="tab" id="ir-data-tab" aria-controls="ir-register-panel" aria-selected="true" data-ir-view-tab="table"><i class="feather-table" aria-hidden="true"></i> Data table</button>
                    <button type="button" class="ir-analysis-tab" role="tab" id="ir-submission-tab" aria-controls="ir-submission-ledger-panel" aria-selected="false" data-ir-view-tab="submissions"><i class="feather-inbox" aria-hidden="true"></i> Think Tank submissions</button>
                    <button type="button" class="ir-analysis-tab" role="tab" id="ir-chart-tab" aria-controls="ir-chart-panel" aria-selected="false" data-ir-view-tab="charts"><i class="feather-bar-chart-2" aria-hidden="true"></i> Graphical analysis</button>
                </nav>

                <div class="ir-grid" id="ir-chart-panel" role="tabpanel" aria-labelledby="ir-chart-tab" data-ir-view-panel="charts" hidden>
                    <section class="ir-panel" aria-labelledby="ir-distribution-title">
                        <div class="ir-panel-head"><div><h2 id="ir-distribution-title">Indicator performance profile</h2><p>Filtered indicators grouped by the active framework's approved thresholds.</p></div><span class="ir-badge">{{ $indicatorRows->count() }} indicators</span></div>
                        @if($performanceDistribution->sum('count') > 0)<div id="ir-performance-chart" class="ir-chart" role="img" aria-label="Donut chart of indicator performance classifications"></div>@else<div class="ir-empty"><span class="ir-empty-mark">PD</span><strong>No performance classifications are available</strong><p>No rateable or qualitative indicator rows match this scope.</p></div>@endif
                    </section>
                    <section class="ir-panel" aria-labelledby="ir-attainment-title">
                        <div class="ir-panel-head"><div><h2 id="ir-attainment-title">Target attainment by indicator</h2><p>Rateable indicators ordered by approved target attainment; displayed values may exceed 100%.</p></div><span class="ir-badge">{{ $attainmentRows->count() }} rated</span></div>
                        @if($attainmentRows->isNotEmpty())<div id="ir-attainment-chart" class="ir-chart ir-chart-tall" role="img" aria-label="Horizontal bar chart of indicator target attainment"></div>@else<div class="ir-empty"><span class="ir-empty-mark">TA</span><strong>No rateable indicators are available in this scope</strong><p>An approved numeric or Yes/No actual and matching target are required for attainment.</p></div>@endif
                    </section>
                </div>

                <section class="ir-panel" style="margin-top:1rem" aria-labelledby="ir-results-areas-title" data-ir-view-panel="charts" hidden>
                    <div class="ir-panel-head"><div><h2 id="ir-results-areas-title">Results-area summary</h2><p>Comparable scorecards by PDO or project component. Raw indicator values with unlike units are never summed.</p></div><span class="ir-badge neutral">{{ $projectRows->count() }} results areas</span></div>
                    @if($projectRows->isEmpty())
                        <div class="ir-empty"><span class="ir-empty-mark">RA</span><strong>No results-area summary is available</strong><p>The filtered indicators are not linked to an authorized project or PDO scope.</p></div>
                    @else
                        <div class="ir-project-grid">
                            @foreach($projectRows as $projectRow)
                                @php
                                    $projectStatus = $projectRow['status'] ?? ['label' => 'Not rated', 'color' => '#64748b'];
                                @endphp
                                <article class="ir-project-card">
                                    <div class="ir-project-card-head"><div><span class="ir-code">{{ $projectRow['code'] }}</span><h3>{{ $projectRow['name'] }}</h3><p>{{ $projectRow['portfolio'] ?: 'Portfolio not recorded' }}{{ $projectRow['program'] ? ' · '.$projectRow['program'] : '' }}</p></div><span class="ir-badge" style="color:{{ $projectStatus['color'] }};background:{{ $projectStatus['color'] }}18">{{ $projectStatus['label'] }}</span></div>
                                    <div class="ir-project-stats"><div class="ir-project-stat"><span>Indicator coverage</span><strong>{{ $projectRow['reported_indicator_count'] }}/{{ $projectRow['indicator_count'] }}</strong></div><div class="ir-project-stat"><span>Average attainment</span><strong>{{ ($projectRow['average_achievement'] ?? null) === null ? '—' : number_format((float) $projectRow['average_achievement'],1).'%' }}</strong></div><div class="ir-project-stat"><span>Completeness</span><strong>{{ number_format((float) ($projectRow['reporting_completeness'] ?? 0),1) }}%</strong></div></div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="ir-panel" id="ir-register-panel" role="tabpanel" aria-labelledby="ir-data-tab" data-ir-view-panel="table">
                    <div class="ir-panel-head"><div><h2 id="ir-register-title">Consolidated indicator register</h2><p>Approved profiles, results, governed calculations and auditable source contributions for the filtered framework.</p></div><span class="ir-badge">{{ $indicatorRows->count() }} indicators</span></div>
                    <div class="ir-toolbar ir-no-print">
                        <div class="ir-toolbar-fields"><input class="form-control" type="search" placeholder="Search code, indicator, project or source" data-ir-search><select class="form-select" aria-label="Filter register by performance classification" data-ir-status><option value="">All performance statuses</option>@foreach($performanceDistribution as $item)<option value="{{ $item['code'] }}">{{ $item['label'] }} ({{ $item['count'] }})</option>@endforeach</select><button class="ir-btn ir-btn-secondary" type="button" data-ir-clear>Clear search</button></div>
                        <span class="ir-row-count" data-ir-count>Showing {{ $indicatorRows->count() }} {{ str('indicator')->plural($indicatorRows->count()) }}</span>
                    </div>
                    <div class="ir-table-wrap" role="region" aria-label="Scrollable consolidated indicator register" tabindex="0">
                        <table class="ir-table">
                            <thead><tr><th>Indicator</th><th>Results area</th><th>Baseline / target</th><th>Approved actual</th><th>Attainment / variance</th><th>Performance</th><th>Reporting</th><th>Evidence / achievements</th><th>Last approval</th><th>Audit detail</th></tr></thead>
                            <tbody>
                            @foreach($indicatorRows as $row)
                                @php
                                    $indicator = $row['indicator'];
                                    $unit = $row['unit_label'] ?? ($indicator?->unit?->symbol ?: $indicator?->unit?->name);
                                    $status = $row['classification'] ?? ['code'=>'not_rated','label'=>'Not rated','color'=>'#64748b'];
                                    $achievementWidth = ($row['achievement_percent'] ?? null) === null ? 0 : min(100, max(0, (float) $row['achievement_percent']));
                                    $rowKey = 'indicator-'.$loop->index;
                                    $searchText = str(($indicator?->indicator_code).' '.($indicator?->name).' '.($indicator?->projectComponent?->project_id).' '.($indicator?->projectComponent?->name).' '.collect($row['reporting_organizations'] ?? [])->join(' '))->lower();
                                    $sources = collect($row['source_contributions'] ?? []);
                                @endphp
                                <tr data-ir-row="{{ $rowKey }}" data-search="{{ $searchText }}" data-status="{{ $status['code'] }}">
                                    <td><span class="ir-code">{{ $indicator?->indicator_code ?: 'Uncoded' }}</span><span class="ir-title">{{ $indicator?->name ?: 'Indicator unavailable' }}</span><span class="ir-meta">{{ str($indicator?->value_type ?: 'number')->headline() }} &middot; {{ $indicator?->is_cumulative ? 'Cumulative' : 'Period-specific' }}</span></td>
                                    <td><strong>{{ $indicator?->resultsLevelLabel() }}</strong><span class="ir-meta">{{ $indicator?->projectComponent?->project_id ?: 'PDO' }} &middot; {{ $indicator?->projectComponent?->name ?: 'Project Development Objective / Cross-project results' }}</span></td>
                                    <td><strong>{{ $formatValue($row['baseline'] ?? null, $unit) }}</strong><span class="ir-meta">Baseline</span><span class="ir-value" style="margin-top:.3rem">{{ $targetDisplay($row) }}</span><span class="ir-meta">Project Year {{ (int) ($filters['project_year'] ?? 1) }} target</span></td>
                                    <td><span class="ir-value">{{ $formatValue($row['actual'] ?? null, $unit) }}</span><span class="ir-meta">{{ $row['organization_rollup_label'] ?? 'Governed indicator roll-up' }}</span></td>
                                    <td><strong>{{ ($row['achievement_percent'] ?? null) === null ? 'Not rateable' : number_format((float) $row['achievement_percent'],1).'%' }}</strong><div class="ir-progress"><span style="width:{{ $achievementWidth }}%;--progress:{{ $status['color'] }}"></span></div><span class="ir-meta">Variance {{ $formatValue($row['variance_value'] ?? $row['variance'] ?? null, $unit) }} &middot; {{ data_get($row,'trend.label','No trend') }}</span></td>
                                    <td><span class="ir-badge" style="color:{{ $status['color'] }};background:{{ $status['color'] }}18">{{ $status['label'] }}</span></td>
                                    <td><strong>{{ number_format((float) ($row['reporting_completeness'] ?? 0),1) }}%</strong><span class="ir-meta">{{ (int) ($row['reported_organizations'] ?? 0) }}/{{ (int) ($row['expected_organizations'] ?? 0) }} expected reporters</span></td>
                                    <td><strong>{{ number_format((int) ($row['verified_evidence_count'] ?? 0)) }}/{{ number_format((int) ($row['evidence_count'] ?? 0)) }} evidence verified</strong><span class="ir-meta">{{ number_format((int) ($row['achievement_count'] ?? 0)) }} achievement records &middot; {{ number_format((int) ($row['beneficiary_count'] ?? 0)) }} participant/beneficiary instances</span></td>
                                    <td>{{ $formatDate($row['latest_approved_at'] ?? null) }}</td>
                                    <td><a href="#{{ $rowKey }}-detail" class="ir-btn ir-btn-secondary ir-btn-small">View sources</a></td>
                                </tr>
                                <tr class="ir-detail-row" data-ir-detail="{{ $rowKey }}">
                                    <td colspan="10">
                                        <details class="ir-row-detail" id="{{ $rowKey }}-detail">
                                            <summary><i class="feather-chevron-down" aria-hidden="true"></i>Open profile, calculation and source-contribution detail</summary>
                                            <div class="ir-detail-body">
                                                <div class="ir-detail-facts">
                                                    <div class="ir-detail-fact"><small>Definition</small><strong>{{ $indicator?->approvedReferenceSheet?->definition ?: $indicator?->definitions ?: 'Not recorded' }}</strong></div>
                                                    <div class="ir-detail-fact"><small>Collection / source</small><strong>{{ $indicator?->data_collection_method ?: 'Not configured' }} &middot; {{ str($indicator?->reporting_source ?: 'not configured')->headline() }}</strong></div>
                                                    <div class="ir-detail-fact"><small>Aggregation</small><strong>{{ $row['time_aggregation_label'] ?? 'Not configured' }} &middot; {{ $row['organization_rollup_label'] ?? 'Not configured' }}</strong></div>
                                                    <div class="ir-detail-fact"><small>Calculation note</small><strong>{{ $row['calculation_note'] ?? 'Approved indicator results only.' }}</strong></div>
                                                </div>
                                                @if($sources->isEmpty())
                                                    <div class="ir-empty" style="min-height:120px"><strong>No approved source contribution is available for this indicator</strong><p>The indicator remains visible to disclose reporting gaps and missing approved data.</p></div>
                                                @else
                                                    <div class="ir-table-wrap"><table class="ir-source-table"><thead><tr><th>Organization</th><th>Country / period</th><th>Approved contribution</th><th>Weight inputs</th><th>Data source</th><th>Achievements</th><th>Evidence links</th><th>Approved at</th></tr></thead><tbody>
                                                    @foreach($sources as $source)
                                                        <tr><td><strong>{{ $source['organization'] ?? 'Secretariat / Internal' }}</strong></td><td>{{ $source['country'] ?: 'Not recorded' }}<span class="ir-meta">{{ $source['period'] ?: 'Period not recorded' }}</span></td><td><span class="ir-value">{{ $formatValue($source['actual'] ?? null, $unit) }}</span></td><td>{{ ($source['rollup_numerator'] ?? null) === null && ($source['rollup_denominator'] ?? null) === null ? 'Not required' : $formatValue($source['rollup_numerator'] ?? null).' / '.$formatValue($source['rollup_denominator'] ?? null) }}</td><td>{{ $source['data_source'] ?: 'Not recorded' }}</td><td>{{ number_format((int) ($source['achievement_count'] ?? 0)) }}</td><td>{{ number_format((int) ($source['verified_evidence_count'] ?? 0)) }}/{{ number_format((int) ($source['evidence_count'] ?? 0)) }} verified</td><td>{{ $formatDate($source['approved_at'] ?? null) }}</td></tr>
                                                    @endforeach
                                                    </tbody></table></div>
                                                @endif
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="ir-table-filter-empty" data-ir-filter-empty><td colspan="10"><div class="ir-empty"><span class="ir-empty-mark">0</span><strong>No indicator rows match your search</strong><p>Clear the keyword or performance selector. Server-side report filters remain unchanged.</p></div></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="ir-scroll-tip"><span>Open each row to inspect the controlled definition, calculation note and approved source values.</span><span>Scroll horizontally to view the complete report register.</span></div>
                </section>
            @endif

            <section class="ir-panel" style="margin-top:1rem" aria-labelledby="ir-quality-title" data-ir-view-panel="table">
                <div class="ir-panel-head"><div><h2 id="ir-quality-title">Evidence and data-quality disclosures</h2><p>Visible exceptions explain missing, non-additive, incomplete or not-yet-rateable report content.</p></div><span class="ir-badge {{ $qualityTotal > 0 ? 'warning' : '' }}">{{ $qualityTotal > 0 ? $qualityTotal.' signals' : 'All configured checks clear' }}</span></div>
                <div class="ir-panel-body">
                    <div class="ir-quality-grid">
                        @foreach($qualityLabels as $key => $label)<div class="ir-quality"><span>{{ $label }}</span><strong>{{ number_format((int) ($quality[$key] ?? 0)) }}</strong></div>@endforeach
                    </div>
                </div>
            </section>

            <section class="ir-panel" style="margin-top:1rem" aria-labelledby="ir-report-rules-title" data-ir-view-panel="table">
                <div class="ir-panel-head"><div><h2 id="ir-report-rules-title">Report methodology and audit trail</h2><p>Rules shared by the on-screen report and every Excel, CSV, PDF and print output.</p></div><span class="ir-badge neutral">Governed report</span></div>
                <div class="ir-panel-body">
                    <div class="ir-method-list">
                        <div class="ir-method"><strong>Approved sources only</strong><p>Draft, submitted, returned, reviewed and merely verified results are excluded. Archived approved contributions remain official.</p></div>
                        <div class="ir-method"><strong>Indicator-defined calculation</strong><p>Each indicator retains its unit, time aggregation and organization roll-up. Qualitative and non-additive values remain attributable.</p></div>
                        <div class="ir-method"><strong>Comparable scorecards</strong><p>Average scorecard attainment is unweighted and caps each rateable indicator at 100%. Raw unlike-unit actuals are never summed.</p></div>
                        <div class="ir-method"><strong>Transparent counts</strong><p>Evidence figures are evidence links. Participant and beneficiary figures are reporting instances and are not necessarily unique people.</p></div>
                    </div>
                    <span class="ir-meta" style="margin-top:.8rem">Framework {{ $frameworkRecord->code ?? 'not coded' }} &middot; Version {{ $frameworkRecord->version ?? 'not recorded' }} &middot; Latest approval {{ $formatDate($reportSummary['latest_approval_at'] ?? null) }} &middot; Report generated {{ $generatedAt->format('d M Y, H:i') }}</span>
                </div>
            </section>
        @endif

        <section class="ir-panel ir-submission-ledger" id="ir-submission-ledger-panel" role="tabpanel" aria-labelledby="{{ $hasReportData ? 'ir-submission-tab ir-submission-ledger-title' : 'ir-submission-ledger-title' }}" data-ir-view-panel="submissions" @if($hasReportData) hidden @endif>
            <div class="ir-panel-head">
                <div><h2 id="ir-submission-ledger-title">Think Tank submission ledger</h2><p>Submitted form responses, workflow status, review history and evidence for the selected reporting scope. These records are shown for traceability and do not affect official totals until approved.</p></div>
                <span class="ir-badge neutral">{{ number_format($thinkTankSubmissionRows->count()) }} submission {{ str('record')->plural($thinkTankSubmissionRows->count()) }}</span>
            </div>
            @if($thinkTankSubmissionRows->isEmpty())
                <div class="ir-empty"><span class="ir-empty-mark">SUB</span><strong>No submitted Think Tank forms match this scope</strong><p>Choose an indicator and reporting period, or check the collection window and submission workflow status.</p></div>
            @else
                <div class="ir-toolbar ir-submission-toolbar ir-no-print">
                    <div class="ir-toolbar-fields">
                        <input class="form-control" type="search" placeholder="Search Think Tank, indicator, period or form" aria-label="Search Think Tank submissions" data-ir-submission-search>
                        <select class="form-select" aria-label="Filter Think Tank submissions by workflow status" data-ir-submission-status>
                            <option value="">All workflow statuses</option>
                            @foreach($thinkTankSubmissionRows->pluck('status')->filter()->unique()->sort() as $submissionStatus)
                                <option value="{{ $submissionStatus }}">{{ str($submissionStatus)->replace('_', ' ')->headline() }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="ir-btn ir-btn-secondary ir-btn-small" data-ir-submission-clear>Clear</button>
                    </div>
                    <span class="ir-row-count" role="status" aria-live="polite" data-ir-submission-count>Showing {{ $thinkTankSubmissionRows->count() }} submission {{ str('record')->plural($thinkTankSubmissionRows->count()) }}</span>
                </div>
                <div class="ir-table-wrap" role="region" aria-label="Think Tank submitted data and workflow status" tabindex="0">
                    <table class="ir-source-table ir-submission-table">
                        <thead><tr><th>Indicator</th><th>Think Tank / country</th><th>Reporting period</th><th>Submitted by / date</th><th>Reported value</th><th>Workflow status</th><th>Response detail</th></tr></thead>
                        <tbody>
                        @foreach($thinkTankSubmissionRows as $submissionRow)
                            @php
                                $submissionStatus = (string) ($submissionRow['status'] ?: 'submitted');
                                $submissionStatusColor = match ($submissionStatus) {
                                    'approved' => '#16815f',
                                    'verified', 'under_review' => '#9a6818',
                                    'returned', 'rejected' => '#b83d3a',
                                    'submitted', 'resubmitted' => '#176b87',
                                    default => '#64748b',
                                };
                                $submissionSearch = str(implode(' ', [
                                    $submissionRow['indicator_code'] ?? '',
                                    $submissionRow['indicator_name'] ?? '',
                                    $submissionRow['think_tank'] ?? '',
                                    $submissionRow['country'] ?? '',
                                    $submissionRow['period'] ?? '',
                                    $submissionRow['form_code'] ?? '',
                                    $submissionRow['form_title'] ?? '',
                                ]))->lower();
                            @endphp
                            <tr data-ir-submission-row data-search="{{ $submissionSearch }}" data-status="{{ $submissionStatus }}">
                                <td><span class="ir-code">{{ $submissionRow['indicator_code'] ?: 'Uncoded' }}</span><span class="ir-title">{{ $submissionRow['indicator_name'] ?: 'Indicator unavailable' }}</span></td>
                                <td><strong>{{ $submissionRow['think_tank'] }}</strong><span class="ir-meta">{{ $submissionRow['country'] ?: 'Country not recorded' }}</span></td>
                                <td><strong>{{ $submissionRow['period'] ?: 'Period not recorded' }}</strong><span class="ir-meta">{{ $submissionRow['reporting_year'] ?: 'Year not recorded' }}</span></td>
                                <td><strong>{{ $submissionRow['submitted_by'] ?: 'Submitter not recorded' }}</strong><span class="ir-meta">{{ $submissionRow['submitted_at'] ? $formatDate($submissionRow['submitted_at']) : 'Submission date not recorded' }}</span></td>
                                <td><span class="ir-value">{{ $submissionRow['actual'] === null ? 'Form response available' : $formatValue($submissionRow['actual']) }}</span><span class="ir-meta">{{ count($submissionRow['answers']) }} response {{ str('field')->plural(count($submissionRow['answers'])) }}</span></td>
                                <td><span class="ir-badge" style="color:{{ $submissionStatusColor }};background:{{ $submissionStatusColor }}18">{{ str($submissionStatus)->replace('_', ' ')->headline() }}</span><span class="ir-meta">Revision {{ $submissionRow['revision'] }}</span></td>
                                <td>
                                    <details class="ir-submission-details">
                                        <summary class="ir-btn ir-btn-secondary ir-btn-small">View submission</summary>
                                        <div class="ir-submission-detail-body">
                                            <div class="ir-detail-facts">
                                                <div class="ir-detail-fact"><small>Form and version</small><strong>{{ $submissionRow['form_title'] ?: 'Form not recorded' }}{{ $submissionRow['form_code'] ? ' · '.$submissionRow['form_code'] : '' }}{{ $submissionRow['form_version'] ? ' · v'.$submissionRow['form_version'] : '' }}</strong></div>
                                                <div class="ir-detail-fact"><small>Reporting period</small><strong>{{ $submissionRow['period'] ?: 'Period not recorded' }}{{ $submissionRow['reporting_year'] ? ' · '.$submissionRow['reporting_year'] : '' }}{{ $submissionRow['period_start'] || $submissionRow['period_end'] ? ' · '.($submissionRow['period_start'] ?: '?').' to '.($submissionRow['period_end'] ?: '?') : '' }}</strong></div>
                                                <div class="ir-detail-fact"><small>Submission ID / version</small><strong>{{ $submissionRow['submission_id'] }} · {{ $submissionRow['revision'] }}</strong></div>
                                                <div class="ir-detail-fact"><small>Assigned</small><strong>{{ $submissionRow['assigned_at'] ? $formatDate($submissionRow['assigned_at']) : 'Not recorded' }}</strong></div>
                                                <div class="ir-detail-fact"><small>Collection opens</small><strong>{{ $submissionRow['opens_at'] ? $formatDate($submissionRow['opens_at']) : 'Not recorded' }}</strong></div>
                                                <div class="ir-detail-fact"><small>Submission deadline</small><strong>{{ $submissionRow['due_at'] ? $formatDate($submissionRow['due_at']) : 'Not recorded' }}</strong></div>
                                                <div class="ir-detail-fact"><small>Collection closes</small><strong>{{ $submissionRow['closes_at'] ? $formatDate($submissionRow['closes_at']) : 'Not recorded' }}</strong></div>
                                                <div class="ir-detail-fact"><small>Collection status</small><strong>{{ $submissionRow['collection_status'] ? str($submissionRow['collection_status'])->headline() : 'Not recorded' }}</strong></div>
                                                <div class="ir-detail-fact"><small>Indicator result review</small><strong>{{ $submissionRow['result_review_status'] ? str($submissionRow['result_review_status'])->replace('_', ' ')->headline() : 'No indicator result generated' }}{{ $submissionRow['approved_at'] ? ' · '.$formatDate($submissionRow['approved_at']) : '' }}</strong></div>
                                            </div>
                                            @if(filled($submissionRow['collection_instructions']))<div class="ir-detail-fact ir-submission-instructions"><small>Collection instructions</small><strong>{{ $submissionRow['collection_instructions'] }}</strong></div>@endif

                                            <h3 class="ir-submission-section-title">Submitted form responses</h3>
                                            @if(collect($submissionRow['answers'])->isEmpty())
                                                <p class="ir-meta">No answer fields are available for this submission.</p>
                                            @else
                                                <div class="ir-table-wrap"><table class="ir-source-table ir-answer-table"><thead><tr><th>Section</th><th>Question</th><th>Type</th><th>Submitted response</th></tr></thead><tbody>
                                                    @foreach($submissionRow['answers'] as $answer)
                                                        <tr><td>{{ $answer['section'] }}</td><td><strong>{{ $answer['label'] }}</strong></td><td>{{ str($answer['type'])->replace('_', ' ')->headline() }}</td><td class="ir-submission-answer">{{ $answer['value'] }}</td></tr>
                                                    @endforeach
                                                </tbody></table></div>
                                            @endif

                                            @if(filled($submissionRow['submission_notes']) || filled($submissionRow['review_notes']))
                                                <div class="ir-detail-facts ir-submission-notes">
                                                    @if(filled($submissionRow['submission_notes']))<div class="ir-detail-fact"><small>Think Tank submission note</small><strong>{{ $submissionRow['submission_notes'] }}</strong></div>@endif
                                                    @if(filled($submissionRow['review_notes']))<div class="ir-detail-fact"><small>Latest review note</small><strong>{{ $submissionRow['review_notes'] }}</strong></div>@endif
                                                </div>
                                            @endif

                                            <h3 class="ir-submission-section-title">Review history</h3>
                                            @if(collect($submissionRow['reviews'])->isEmpty())
                                                <p class="ir-meta">No review actions have been recorded.</p>
                                            @else
                                                <div class="ir-table-wrap"><table class="ir-source-table ir-answer-table"><thead><tr><th>Action</th><th>Status change</th><th>Reviewer</th><th>Date</th><th>Comments</th></tr></thead><tbody>
                                                    @foreach($submissionRow['reviews'] as $review)
                                                        <tr><td>{{ str($review['action'])->replace('_', ' ')->headline() }}</td><td>{{ $review['from_status'] ? str($review['from_status'])->replace('_', ' ')->headline().' → ' : '' }}{{ str($review['to_status'])->replace('_', ' ')->headline() }}</td><td>{{ $review['reviewer'] ?: 'Reviewer not recorded' }}</td><td>{{ $review['reviewed_at'] ? $formatDate($review['reviewed_at']) : 'Not recorded' }}</td><td>{{ $review['comments'] ?: 'No comments' }}</td></tr>
                                                    @endforeach
                                                </tbody></table></div>
                                            @endif

                                            <h3 class="ir-submission-section-title">Evidence links</h3>
                                            @if(collect($submissionRow['evidence'])->isEmpty())
                                                <p class="ir-meta">No evidence links were attached to this indicator submission.</p>
                                            @else
                                                <div class="ir-table-wrap"><table class="ir-source-table ir-answer-table"><thead><tr><th>Document</th><th>Verification status</th><th>File size</th><th>Added</th></tr></thead><tbody>
                                                    @foreach($submissionRow['evidence'] as $evidence)
                                                        <tr><td>{{ $evidence['title'] }}</td><td>{{ str($evidence['status'])->replace('_', ' ')->headline() }}</td><td>{{ $evidence['file_size'] ? number_format($evidence['file_size'] / 1024, 1).' KB' : 'Not recorded' }}</td><td>{{ $evidence['created_at'] ? $formatDate($evidence['created_at']) : 'Not recorded' }}</td></tr>
                                                    @endforeach
                                                </tbody></table></div>
                                            @endif
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @endforeach
                        <tr data-ir-submission-empty hidden><td colspan="7"><div class="ir-empty" style="min-height:130px"><strong>No Think Tank submissions match this search</strong><p>Clear the search or workflow-status filter to show the full report scope.</p></div></td></tr>
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif
</div>
@endsection

@if($frameworkRecord)
@push('scripts')
<script src="{{ asset('admin/assets/vendors/js/apexcharts.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelector('[data-ir-print]')?.addEventListener('click', function () { window.print(); });

    const portfolio = document.querySelector('[data-ir-portfolio]');
    const project = document.querySelector('[data-ir-project]');
    const indicator = document.querySelector('[data-ir-indicator]');
    const year = document.querySelector('[data-ir-year]');
    const period = document.querySelector('[data-ir-period]');
    const resultsLevel = document.querySelector('[data-ir-results-level]');
    const projectOptions = Array.from(project?.options || []);
    const indicatorOptions = Array.from(indicator?.options || []);
    const periodOptions = Array.from(period?.options || []);

    const filterSelect = function (select, options, predicate) {
        if (!select) return;
        options.forEach(function (option, index) { option.hidden = index > 0 && !predicate(option); });
        if (select.selectedOptions[0]?.hidden) select.value = '';
    };
    const refreshScopeOptions = function () {
        const portfolioId = portfolio?.value || '';
        const projectId = project?.value || '';
        const reportingYear = year?.value || '';
        const level = resultsLevel?.value || '';
        filterSelect(project, projectOptions, option => !portfolioId || !option.dataset.portfolio || option.dataset.portfolio === portfolioId);
        filterSelect(indicator, indicatorOptions, option => (!projectId || option.dataset.project === projectId) && (!level || option.dataset.resultsLevel === level));
        filterSelect(period, periodOptions, option => (!reportingYear || option.dataset.year === reportingYear) && (!portfolioId || !option.dataset.portfolio || option.dataset.portfolio === portfolioId));
    };
    portfolio?.addEventListener('change', function () { if (project) project.value = ''; if (indicator) indicator.value = ''; if (period) period.value = ''; refreshScopeOptions(); });
    project?.addEventListener('change', function () { if (indicator) indicator.value = ''; refreshScopeOptions(); });
    year?.addEventListener('change', function () { if (period) period.value = ''; refreshScopeOptions(); });
    resultsLevel?.addEventListener('change', function () { if (indicator) indicator.value = ''; refreshScopeOptions(); });
    refreshScopeOptions();

    const rows = Array.from(document.querySelectorAll('[data-ir-row]'));
    const search = document.querySelector('[data-ir-search]');
    const status = document.querySelector('[data-ir-status]');
    const clear = document.querySelector('[data-ir-clear]');
    const counter = document.querySelector('[data-ir-count]');
    const filterEmpty = document.querySelector('[data-ir-filter-empty]');
    const applyLocalFilters = function () {
        const term = (search?.value || '').trim().toLowerCase();
        const selectedStatus = status?.value || '';
        let visible = 0;
        rows.forEach(function (row) {
            const show = (!term || (row.dataset.search || '').includes(term)) && (!selectedStatus || row.dataset.status === selectedStatus);
            row.hidden = !show;
            const detail = document.querySelector('[data-ir-detail="' + row.dataset.irRow + '"]');
            if (detail) detail.hidden = !show;
            if (show) visible++;
        });
        if (counter) counter.textContent = 'Showing ' + visible + ' ' + (visible === 1 ? 'indicator' : 'indicators');
        if (filterEmpty) filterEmpty.style.display = rows.length > 0 && visible === 0 ? 'table-row' : 'none';
    };
    search?.addEventListener('input', applyLocalFilters);
    status?.addEventListener('change', applyLocalFilters);
    clear?.addEventListener('click', function () { if (search) search.value = ''; if (status) status.value = ''; applyLocalFilters(); search?.focus(); });

    const distribution = @json($performanceDistribution->values()->all());
    const attainment = @json($attainmentRows->values()->all());
    const sourceContributions = @json($sourceChartRows->values()->all());
    const base = { chart:{fontFamily:'Inter, Arial, sans-serif',foreColor:'#607582',toolbar:{show:false},animations:{speed:420}},grid:{borderColor:'#e2eaed',strokeDashArray:3},dataLabels:{style:{fontSize:'10px',fontWeight:700}},tooltip:{theme:'light'},legend:{fontSize:'11px',fontWeight:600} };
    const render = function (selector, options) {
        const target = document.querySelector(selector);
        if (!target || target.dataset.chartRendered === 'true') return;
        if (!window.ApexCharts) {
            target.dataset.chartRendered = 'true';
            target.textContent = 'Charts are unavailable right now. Use the data table to review this report.';
            target.setAttribute('role', 'status');
            return;
        }
        target.dataset.chartRendered = 'true';
        new window.ApexCharts(target, options).render();
    };
    const renderCharts = function () {
        render('#ir-performance-chart',{...base,chart:{...base.chart,type:'donut',height:300},series:distribution.map(item=>Number(item.count)||0),labels:distribution.map(item=>item.label),colors:distribution.map(item=>item.color),stroke:{colors:['#fff'],width:3},plotOptions:{pie:{donut:{size:'68%',labels:{show:true,total:{show:true,label:'Indicators',formatter:()=>distribution.reduce((sum,item)=>sum+(Number(item.count)||0),0)}}}}},legend:{position:'bottom'},dataLabels:{enabled:false},noData:{text:'No performance classifications are available'}});
        const attainmentMax = Math.max(100,...attainment.map(item=>Number(item.value)||0));
        render('#ir-attainment-chart',{...base,chart:{...base.chart,type:'bar',height:Math.max(330,attainment.length*32)},series:[{name:'Target attainment',data:attainment.map(item=>item.value)}],colors:attainment.map(item=>item.color),plotOptions:{bar:{horizontal:true,distributed:true,borderRadius:4,barHeight:'58%'}},xaxis:{categories:attainment.map(item=>item.code),min:0,max:Math.ceil(attainmentMax/25)*25,labels:{formatter:value=>Number(value).toFixed(0)+'%'}},dataLabels:{enabled:true,formatter:value=>Number(value).toFixed(1)+'%'},legend:{show:false},tooltip:{y:{formatter:value=>Number(value).toFixed(1)+'%'}}});
        render('#ir-source-chart',{...base,chart:{...base.chart,type:'bar',height:Math.max(300,sourceContributions.length*38)},series:[{name:'Approved contribution',data:sourceContributions.map(item=>Number(item.value)||0)}],colors:['#176b87'],plotOptions:{bar:{horizontal:true,borderRadius:4,barHeight:'58%'}},xaxis:{categories:sourceContributions.map(item=>item.label)},dataLabels:{enabled:true,formatter:value=>Number(value).toLocaleString()},legend:{show:false},tooltip:{y:{formatter:value=>Number(value).toLocaleString()}}});
    };

    const viewTabs = Array.from(document.querySelectorAll('[data-ir-view-tab]'));
    const viewPanels = Array.from(document.querySelectorAll('[data-ir-view-panel]'));
    const activateView = function (view) {
        viewTabs.forEach(function (tab) {
            const active = tab.dataset.irViewTab === view;
            tab.classList.toggle('active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.tabIndex = active ? 0 : -1;
        });
        viewPanels.forEach(function (panel) {
            panel.hidden = panel.dataset.irViewPanel !== view;
        });
        if (view === 'charts') window.requestAnimationFrame(renderCharts);
        if (view === 'submissions') window.requestAnimationFrame(() => {
            document.querySelector('[data-ir-view-panel="submissions"]')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    };
    viewTabs.forEach(function (tab, index) {
        tab.addEventListener('click', function () { activateView(tab.dataset.irViewTab); });
        tab.addEventListener('keydown', function (event) {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const nextIndex = event.key === 'Home' ? 0
                : event.key === 'End' ? viewTabs.length - 1
                    : (index + (event.key === 'ArrowRight' ? 1 : viewTabs.length - 1)) % viewTabs.length;
            viewTabs[nextIndex]?.focus();
            activateView(viewTabs[nextIndex]?.dataset.irViewTab || 'table');
        });
    });
    if (viewTabs.length > 0) {
        const initialView = viewTabs.find(tab => tab.getAttribute('aria-selected') === 'true')?.dataset.irViewTab || 'table';
        activateView(initialView);
    }

    const submissionRows = Array.from(document.querySelectorAll('[data-ir-submission-row]'));
    const submissionSearch = document.querySelector('[data-ir-submission-search]');
    const submissionStatus = document.querySelector('[data-ir-submission-status]');
    const submissionClear = document.querySelector('[data-ir-submission-clear]');
    const submissionCount = document.querySelector('[data-ir-submission-count]');
    const submissionEmpty = document.querySelector('[data-ir-submission-empty]');
    const filterSubmissions = function () {
        const term = (submissionSearch?.value || '').trim().toLowerCase();
        const selectedStatus = submissionStatus?.value || '';
        let visible = 0;
        submissionRows.forEach(function (row) {
            const matches = (!term || (row.dataset.search || '').includes(term))
                && (!selectedStatus || row.dataset.status === selectedStatus);
            row.hidden = !matches;
            if (matches) visible += 1;
        });
        if (submissionCount) submissionCount.textContent = 'Showing ' + visible + ' submission ' + (visible === 1 ? 'record' : 'records');
        if (submissionEmpty) submissionEmpty.hidden = visible !== 0;
    };
    submissionSearch?.addEventListener('input', filterSubmissions);
    submissionStatus?.addEventListener('change', filterSubmissions);
    submissionClear?.addEventListener('click', function () {
        if (submissionSearch) submissionSearch.value = '';
        if (submissionStatus) submissionStatus.value = '';
        filterSubmissions();
        submissionSearch?.focus();
    });
});
</script>
@endpush
@endif
