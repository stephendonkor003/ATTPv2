@extends('layouts.app')

@section('title', 'ATTP Results Dashboard')
@section('lean_admin_scripts', '1')

@push('styles')
    @include('me.results-dashboard.partials.styles')
@endpush

@section('content')
@php
    $viewer = auth()->user();
    $canExport = $viewer && ($viewer->can('me.reports.export') || $viewer->can('me.performance_reports.view') || $viewer->can('me.configuration.manage'));
    $canOpenWorkflow = $viewer && ($viewer->can('me.performance_reports.view') || $viewer->can('me.performance_reports.review') || $viewer->can('me.performance_reports.archive') || $viewer->can('me.data_entry.view') || $viewer->can('me.data_entry.manage') || $viewer->can('me.configuration.view') || $viewer->can('me.configuration.manage'));
    $canOpenReviews = $viewer && ($viewer->can('me.submissions.review') || $viewer->can('me.data_entry.manage') || $viewer->can('me.configuration.manage'));
    $formatValue = static function (mixed $value, int $decimals = 2): string {
        if ($value === null || $value === '') return '—';
        if (is_bool($value)) return $value ? 'Yes' : 'No';
        if (is_numeric($value)) return number_format((float) $value, $decimals);
        return (string) $value;
    };
    $reportedRate = $summary['indicator_count'] > 0
        ? round(($summary['reported_indicator_count'] / $summary['indicator_count']) * 100, 1)
        : 0;
    $genderTotal = (int) $analytics['gender']['female'] + (int) $analytics['gender']['male'];
    $hasComponentPerformance = collect($analytics['components'])->contains(
        fn (array $component): bool => $component['average_achievement'] !== null || $component['reported_count'] > 0
    );
@endphp

<div class="mel-results">
    <header class="mr-header">
        <div>
            <span class="mr-eyebrow">Monitoring, Evaluation and Learning</span>
            <h1>Results and performance dashboard</h1>
            <p>Explore the approved ATTP Results Framework, compare targets with actual performance, monitor reporting coverage and identify indicators requiring management attention.</p>
        </div>
        <div class="mr-header-side">
            <div class="mr-scope">
                <span>Current reporting scope</span>
                <strong>{{ $scopeLabel }}</strong>
            </div>
            <div class="mr-header-actions">
                @if($canOpenWorkflow)<a class="mr-btn mr-btn-header" href="{{ route('budget.me.rebuild.reporting-dashboard') }}">Reporting workflow</a>@endif
                @if($canOpenReviews)<a class="mr-btn mr-btn-header" href="{{ route('budget.me.submission-reviews.index') }}">Review queue</a>@endif
            </div>
        </div>
    </header>

    @if(!$framework)
        <section class="mr-panel mr-setup">
            <div class="mr-empty">
                <span class="mr-empty-mark">RF</span>
                <strong>The ATTP Results Framework is not installed</strong>
                <p>Install and activate the official framework before this dashboard can calculate targets, performance classifications and reporting completeness.</p>
            </div>
        </section>
    @else
        <aside class="mr-guardrail" aria-label="Official data rule">
            <span class="mr-guardrail-mark">OK</span>
            <div>
                <strong>Official-data guardrail is active</strong>
                <p>Only records with final Secretariat approval are included. Draft, submitted, under-review, returned and rejected records are excluded from every metric, chart and export on this page.</p>
            </div>
        </aside>

        <section class="mr-metrics" aria-label="Results summary">
            <article class="mr-metric">
                <span class="mr-metric-label">Framework indicators</span>
                <strong>{{ number_format($summary['indicator_count']) }}</strong>
                <small>{{ number_format($summary['pdo_count']) }} at PDO level in this report view</small>
            </article>
            <article class="mr-metric" style="--metric:#0e7490">
                <span class="mr-metric-label">Indicators reported</span>
                <strong>{{ number_format($reportedRate, 1) }}%</strong>
                <small>{{ $summary['reported_indicator_count'] }} of {{ $summary['indicator_count'] }} have approved data</small>
            </article>
            <article class="mr-metric" style="--metric:#6b63a8">
                <span class="mr-metric-label">Average achievement</span>
                <strong>{{ $summary['average_achievement'] === null ? '—' : number_format($summary['average_achievement'], 1).'%' }}</strong>
                <small>Calculated only for reportable numeric and Yes/No results</small>
            </article>
            <article class="mr-metric" style="--metric:#187459">
                <span class="mr-metric-label">On track or achieved</span>
                <strong>{{ number_format($summary['on_track_count']) }}</strong>
                <small>{{ $summary['on_track_rate'] === null ? 'No rated indicators yet' : number_format($summary['on_track_rate'], 1).'% of rated indicators' }}</small>
            </article>
            <article class="mr-metric" style="--metric:#a56a17">
                <span class="mr-metric-label">Reporting completeness</span>
                <strong>{{ number_format($summary['average_completeness'], 1) }}%</strong>
                <small>{{ number_format($summary['attention_count']) }} performance {{ str('exception')->plural($summary['attention_count']) }}</small>
            </article>
            <article class="mr-metric" style="--metric:#3f7d86">
                <span class="mr-metric-label">Evidence verified</span>
                <strong>{{ $summary['evidence_verification_rate'] === null ? '—' : number_format($summary['evidence_verification_rate'], 1).'%' }}</strong>
                <small>{{ $summary['verified_evidence_count'] }} of {{ $summary['evidence_count'] }} evidence files verified</small>
            </article>
        </section>

        <details class="mr-panel" @if($activeFilterCount > 0) open @endif>
            <summary class="mr-panel-head">
                <div>
                    <h2>Report scope and filters</h2>
                    <p>{{ $reportTitle }} · Target year {{ $filters['project_year'] }} · {{ $scopeLabel }}</p>
                </div>
                <div class="mr-summary-right">
                    <span class="mr-badge">{{ $activeFilterCount }} active {{ str('filter')->plural($activeFilterCount) }}</span>
                    <span class="mr-chevron" aria-hidden="true">⌄</span>
                </div>
            </summary>
            <div class="mr-panel-body">
                <form method="GET" action="{{ route('budget.me.results-dashboard.index') }}" class="mr-filter-grid">
                    <div class="mr-field mr-field-wide">
                        <label for="results-report-type">Report view</label>
                        <select id="results-report-type" name="report_type" class="form-select">
                            @foreach($reportTypes as $key => $label)<option value="{{ $key }}" @selected($filters['report_type'] === $key)>{{ $label }}</option>@endforeach
                        </select>
                        <small>Changes the indicator set and ordering used by the dashboard and exports.</small>
                    </div>
                    <div class="mr-field">
                        <label for="results-project-year">Target project year</label>
                        <select id="results-project-year" name="project_year" class="form-select">
                            @foreach(range(1, 4) as $year)<option value="{{ $year }}" @selected($filters['project_year'] === $year)>Project Year {{ $year }}</option>@endforeach
                        </select>
                        <small>Selects the approved target revision, not the reporting period.</small>
                    </div>
                    <div class="mr-field">
                        <label for="results-reporting-year">Reporting year</label>
                        <select id="results-reporting-year" name="reporting_year" class="form-select">
                            <option value="">All reporting years</option>
                            @foreach($reportingYears as $year)<option value="{{ $year }}" @selected($filters['reporting_year'] === (int) $year)>{{ $year }}</option>@endforeach
                        </select>
                    </div>
                    <div class="mr-field">
                        <label for="results-period">Reporting period</label>
                        <select id="results-period" name="reporting_period_id" class="form-select">
                            <option value="">All periods in scope</option>
                            @foreach($periods as $item)
                                <option value="{{ $item->id }}" data-year="{{ $item->reporting_year }}" @selected($filters['reporting_period_id'] === $item->id)>
                                    {{ $item->label }}{{ $item->reporting_year ? ' · '.$item->reporting_year : '' }}
                                </option>
                            @endforeach
                        </select>
                        <small>A selected reporting period takes precedence over reporting year.</small>
                    </div>
                    <div class="mr-field">
                        <label for="results-component">Results component</label>
                        <select id="results-component" name="component_id" class="form-select">
                            <option value="">PDO and all components</option>
                            @foreach($components as $item)<option value="{{ $item->id }}" @selected($filters['component_id'] === $item->id)>{{ $item->project_id }} · {{ $item->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="mr-field mr-field-wide">
                        <label for="results-indicator">Indicator</label>
                        <select id="results-indicator" name="indicator_id" class="form-select">
                            <option value="">All indicators</option>
                            @foreach($indicators as $item)<option value="{{ $item->id }}" @selected($filters['indicator_id'] === $item->id)>{{ $item->indicator_code }} · {{ $item->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="mr-field">
                        <label for="results-tank">Think Tank</label>
                        <select id="results-tank" name="think_tank_id" class="form-select">
                            <option value="">All Think Tanks</option>
                            @foreach($thinkTanks as $item)<option value="{{ $item->id }}" @selected($filters['think_tank_id'] === $item->id)>{{ $item->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="mr-field">
                        <label for="results-country">Country</label>
                        <select id="results-country" name="country" class="form-select">
                            <option value="">All countries</option>
                            @foreach($countries as $country)<option value="{{ $country }}" @selected($filters['country'] === $country)>{{ $country }}</option>@endforeach
                        </select>
                    </div>
                    <div class="mr-field">
                        <label for="results-theme">Thematic area</label>
                        <select id="results-theme" name="thematic_area" class="form-select">
                            <option value="">All thematic areas</option>
                            @foreach(\App\Models\MeIndicatorAchievement::PRIORITY_THEMES as $key => $label)<option value="{{ $key }}" @selected($filters['thematic_area'] === $key)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="mr-filter-actions">
                        <p class="mr-filter-tip"><strong>How to read this:</strong> target year chooses the benchmark. Reporting year or period chooses the approved actuals measured against it. Country and Think Tank filters also recalculate the expected reporting population.</p>
                        <div class="mr-actions">
                            <a class="mr-btn mr-btn-secondary" href="{{ route('budget.me.results-dashboard.index') }}">Clear filters</a>
                            <button class="mr-btn mr-btn-primary" type="submit">Apply report scope</button>
                        </div>
                    </div>
                </form>
            </div>
        </details>

        <div class="mr-grid">
            <section class="mr-panel" aria-labelledby="component-chart-title">
                <div class="mr-panel-head">
                    <div><h2 id="component-chart-title">Performance by results area</h2><p>Average target achievement and reporting completeness for each component.</p></div>
                    <span class="mr-badge">Target = 100%</span>
                </div>
                @if($hasComponentPerformance)
                    <div id="component-performance-chart" class="mr-chart" role="img" aria-label="Bar chart comparing achievement and completeness by results area"></div>
                @else
                    <div class="mr-empty"><span class="mr-empty-mark">CP</span><strong>No approved component performance yet</strong><p>Component bars will appear after results receive final Secretariat approval.</p></div>
                @endif
            </section>
            <section class="mr-panel" aria-labelledby="distribution-chart-title">
                <div class="mr-panel-head">
                    <div><h2 id="distribution-chart-title">Performance distribution</h2><p>Indicators grouped by the framework's approved thresholds.</p></div>
                </div>
                <div id="performance-distribution-chart" class="mr-chart" role="img" aria-label="Donut chart of indicator performance classifications"></div>
                <div class="mr-legend">
                    @foreach($analytics['performance'] as $item)
                        <span class="mr-legend-item"><i class="mr-legend-dot" style="--legend:{{ $item['color'] }}"></i>{{ $item['label'] }} ({{ $item['count'] }})</span>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="mr-grid">
            <section class="mr-panel" aria-labelledby="attainment-chart-title">
                <div class="mr-panel-head">
                    <div><h2 id="attainment-chart-title">Indicator target attainment</h2><p>Approved actual as a percentage of the selected target; values may exceed 100%.</p></div>
                    <span class="mr-badge">{{ count($analytics['attainment']) }} rated</span>
                </div>
                @if(count($analytics['attainment']) > 0)
                    <div id="indicator-attainment-chart" class="mr-chart mr-chart-tall" role="img" aria-label="Horizontal bar chart of indicator target attainment"></div>
                @else
                    <div class="mr-empty"><span class="mr-empty-mark">TA</span><strong>No target attainment can be calculated</strong><p>The selected scope has no approved numeric or Yes/No result paired with an approved target.</p></div>
                @endif
            </section>
            <section class="mr-panel" aria-labelledby="quality-chart-title">
                <div class="mr-panel-head">
                    <div><h2 id="quality-chart-title">Reporting quality and inclusion</h2><p>Coverage, evidence verification and beneficiary gender data.</p></div>
                </div>
                @if($summary['approved_result_count'] > 0)
                    <div class="mr-mini-charts">
                        <div class="mr-mini-chart">
                            <h3>Data readiness</h3>
                            <p>Completeness and verified evidence</p>
                            <div id="reporting-quality-chart" class="mr-mini-chart-canvas" role="img" aria-label="Radial chart of reporting completeness and evidence verification"></div>
                        </div>
                        <div class="mr-mini-chart">
                            <h3>Beneficiary gender</h3>
                            <p>{{ number_format($genderTotal) }} people disaggregated</p>
                            @if($genderTotal > 0)
                                <div id="gender-chart" class="mr-mini-chart-canvas" role="img" aria-label="Donut chart of female and male beneficiary data"></div>
                            @else
                                <div class="mr-empty"><span class="mr-empty-mark">GD</span><strong>No gender breakdown</strong><p>Approved results do not yet contain beneficiary gender counts.</p></div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="mr-empty"><span class="mr-empty-mark">DQ</span><strong>No approved data-quality signals yet</strong><p>Completeness and verification charts will activate when official results enter this scope.</p></div>
                @endif
            </section>
        </div>

        <div class="mr-insight-grid">
            <section class="mr-panel" aria-labelledby="attention-title">
                <div class="mr-panel-head"><div><h2 id="attention-title">Management attention</h2><p>Reported indicators with a performance or completeness exception.</p></div><span class="mr-badge">Top {{ count($analytics['attention']) }}</span></div>
                <div class="mr-panel-body">
                    @if(count($analytics['attention']) > 0)
                        <div class="mr-attention-list">
                            @foreach($analytics['attention'] as $item)
                                <article class="mr-attention-item">
                                    <div>
                                        <strong><span class="mr-attention-code">{{ $item['code'] }}</span>{{ $item['name'] }}</strong>
                                        <p>{{ $item['classification']['label'] }} · Reporting completeness {{ number_format($item['completeness'], 1) }}%</p>
                                    </div>
                                    <div class="mr-attention-value">
                                        <strong>{{ $item['achievement'] === null ? '—' : number_format($item['achievement'], 1).'%' }}</strong>
                                        <span class="mr-cell-note">achievement</span>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="mr-empty"><span class="mr-empty-mark">OK</span><strong>{{ $summary['approved_result_count'] > 0 ? 'No current exceptions in this scope' : 'No approved results to assess yet' }}</strong><p>{{ $summary['approved_result_count'] > 0 ? 'All reported indicators meet the configured performance and completeness checks.' : 'Exceptions will be highlighted here after official results are approved.' }}</p></div>
                    @endif
                </div>
            </section>
            <section class="mr-panel" aria-labelledby="signals-title">
                <div class="mr-panel-head"><div><h2 id="signals-title">Portfolio signals</h2><p>Supporting counts behind the visual summary.</p></div></div>
                <div class="mr-panel-body mr-signal-list">
                    <div class="mr-signal"><span>Approved result records</span><strong>{{ number_format($summary['approved_result_count']) }}</strong></div>
                    <div class="mr-signal"><span>Indicators without approved data</span><strong>{{ number_format($summary['not_reported_count']) }}</strong></div>
                    <div class="mr-signal"><span>Verified evidence files</span><strong>{{ number_format($summary['verified_evidence_count']) }} / {{ number_format($summary['evidence_count']) }}</strong></div>
                    <div class="mr-signal"><span>Positive / negative trends</span><strong>{{ $analytics['trends']['up'] }} / {{ $analytics['trends']['down'] }}</strong></div>
                    <div class="mr-signal"><span>Female / male beneficiaries</span><strong>{{ number_format($analytics['gender']['female']) }} / {{ number_format($analytics['gender']['male']) }}</strong></div>
                    <div class="mr-signal"><span>Framework and target context</span><strong>{{ $framework->code }} · {{ $framework->version }} · Y{{ $filters['project_year'] }}</strong></div>
                </div>
            </section>
        </div>

        <section class="mr-panel" style="margin-top:1rem" aria-labelledby="register-title">
            <div class="mr-panel-head">
                <div><h2 id="register-title">{{ $reportTitle }}</h2><p>Detailed approved-results register for {{ $scopeLabel }}.</p></div>
                @if($canExport)
                    <div class="mr-actions" aria-label="Download this filtered report">
                        <a class="mr-btn mr-btn-secondary mr-btn-small" href="{{ route('budget.me.results-dashboard.excel', $exportQuery) }}">Excel</a>
                        <a class="mr-btn mr-btn-secondary mr-btn-small" href="{{ route('budget.me.results-dashboard.csv', $exportQuery) }}">CSV</a>
                        <a class="mr-btn mr-btn-primary mr-btn-small" href="{{ route('budget.me.results-dashboard.pdf', $exportQuery) }}">PDF report</a>
                    </div>
                @endif
            </div>
            <div class="mr-table-toolbar">
                <div class="mr-table-controls">
                    <label class="mr-search" for="results-table-search">
                        <span class="mr-search-mark" aria-hidden="true">⌕</span>
                        <input id="results-table-search" class="form-control" type="search" placeholder="Search code, indicator, component or source">
                    </label>
                    <select id="results-status-filter" class="form-select mr-status-filter" aria-label="Filter register by performance status">
                        <option value="">All performance statuses</option>
                        @foreach($analytics['performance'] as $item)<option value="{{ $item['code'] }}">{{ $item['label'] }} ({{ $item['count'] }})</option>@endforeach
                    </select>
                    <button id="results-table-clear" class="mr-btn mr-btn-secondary" type="button">Clear search</button>
                </div>
                <span id="results-row-count" class="mr-row-count" aria-live="polite">Showing {{ $rows->count() }} {{ str('indicator')->plural($rows->count()) }}</span>
            </div>
            <div class="mr-table-wrap">
                <table class="mr-table">
                    <thead>
                        <tr>
                            <th>Indicator</th>
                            <th>Level / source</th>
                            <th>Baseline</th>
                            <th>Target</th>
                            <th>Scope actual</th>
                            <th>Cumulative actual</th>
                            <th>Trend</th>
                            <th>Achievement</th>
                            <th>Performance</th>
                            <th>Records / evidence</th>
                            <th>Reporting completeness</th>
                        </tr>
                    </thead>
                    <tbody id="results-table-body">
                        @forelse($rows as $row)
                            @php
                                $achievementWidth = $row['achievement_percent'] === null ? 0 : min(100, max(0, (float) $row['achievement_percent']));
                                $searchText = str($row['indicator']->indicator_code.' '.$row['indicator']->name.' '.($row['indicator']->projectComponent?->name ?? 'Project Development Objective').' '.$row['indicator']->reporting_source)->lower();
                            @endphp
                            <tr data-result-row data-status="{{ $row['classification']['code'] }}" data-search="{{ $searchText }}">
                                <td>
                                    <div class="mr-indicator">
                                        <span class="mr-code">{{ $row['indicator']->indicator_code }}</span>
                                        <div>
                                            <strong>{{ $row['indicator']->name }}</strong>
                                            <small title="{{ $row['calculation_note'] }}">{{ $row['indicator']->is_cumulative ? 'Cumulative' : 'Period-specific' }} · {{ str($row['indicator']->value_type)->headline() }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    {{ $row['indicator']->resultsLevelLabel() }}
                                    <span class="mr-cell-note">{{ $row['indicator']->projectComponent?->name ?: 'Project Development Objective' }}</span>
                                    <span class="mr-cell-note">Source: {{ str($row['indicator']->reporting_source)->replace('_', ' ')->headline() }}</span>
                                </td>
                                <td>{{ $formatValue($row['baseline']) }}</td>
                                <td>
                                    {{ $row['target_text'] ?: $formatValue($row['target_value']) }}
                                    <span class="mr-cell-note">Project Year {{ $filters['project_year'] }}</span>
                                </td>
                                <td>
                                    {{ $formatValue($row['period_actual']) }}
                                    @if($row['indicator']->unit?->symbol)<span class="mr-cell-note">{{ $row['indicator']->unit->symbol }}</span>@endif
                                </td>
                                <td>{{ $formatValue($row['cumulative_actual']) }}</td>
                                <td>
                                    <span class="mr-trend {{ $row['trend']['direction'] }}">
                                        @if($row['trend']['direction'] === 'up')&uarr;@elseif($row['trend']['direction'] === 'down')&darr;@endif
                                        {{ $row['trend']['label'] }}
                                    </span>
                                </td>
                                <td>
                                    <strong>{{ $row['achievement_percent'] === null ? '—' : number_format($row['achievement_percent'], 1).'%' }}</strong>
                                    <div class="mr-progress"><span style="width:{{ $achievementWidth }}%;--progress:{{ $row['classification']['color'] }}"></span></div>
                                    <span class="mr-cell-note">Variance {{ $formatValue($row['variance']) }}</span>
                                </td>
                                <td><span class="mr-status" style="background:{{ $row['classification']['color'] }}">{{ $row['classification']['label'] }}</span></td>
                                <td>
                                    <strong>{{ number_format($row['result_count']) }} approved</strong>
                                    <span class="mr-cell-note">{{ $row['verified_evidence_count'] }}/{{ $row['evidence_count'] }} evidence verified</span>
                                    <span class="mr-cell-note">F {{ number_format($row['female_beneficiaries']) }} · M {{ number_format($row['male_beneficiaries']) }}</span>
                                </td>
                                <td>
                                    <strong>{{ number_format($row['reporting_completeness'], 1) }}%</strong>
                                    <div class="mr-progress"><span style="width:{{ min(100, $row['reporting_completeness']) }}%"></span></div>
                                    <span class="mr-cell-note">{{ $row['reported_organizations'] }}/{{ $row['expected_organizations'] }} expected reporters</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="11"><div class="mr-empty"><span class="mr-empty-mark">0</span><strong>No indicators match this report scope</strong><p>Clear one or more filters or choose a broader report view.</p></div></td></tr>
                        @endforelse
                        <tr id="results-table-filter-empty" class="mr-table-filter-empty"><td colspan="11"><div class="mr-empty"><span class="mr-empty-mark">0</span><strong>No register rows match your search</strong><p>Try another keyword or performance status.</p></div></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="mr-scroll-tip"><span>Scroll horizontally to view the complete register.</span><span>All figures follow the filters applied above.</span></div>
        </section>
    @endif
</div>
@endsection

@if($framework)
@push('scripts')
<script src="{{ asset('admin/assets/vendors/js/apexcharts.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const performance = {{ \Illuminate\Support\Js::from($analytics['performance']) }};
    const components = {{ \Illuminate\Support\Js::from($analytics['components']) }};
    const attainment = {{ \Illuminate\Support\Js::from($analytics['attainment']) }};
    const quality = {{ \Illuminate\Support\Js::from($analytics['quality']) }};
    const gender = {{ \Illuminate\Support\Js::from($analytics['gender']) }};
    const baseChart = {
        chart: { fontFamily: 'Inter, Arial, sans-serif', foreColor: '#657980', toolbar: { show: false }, animations: { speed: 420 } },
        dataLabels: { style: { fontSize: '10px', fontWeight: 700 } },
        legend: { fontSize: '11px', fontWeight: 600 },
        grid: { borderColor: '#e5edef', strokeDashArray: 3 },
        tooltip: { theme: 'light' }
    };
    const renderChart = function (selector, options) {
        const element = document.querySelector(selector);
        if (!element || !window.ApexCharts) return;
        new ApexCharts(element, options).render();
    };

    renderChart('#performance-distribution-chart', {
        ...baseChart,
        chart: { ...baseChart.chart, type: 'donut', height: 300 },
        series: performance.map(item => item.count),
        labels: performance.map(item => item.label),
        colors: performance.map(item => item.color),
        stroke: { colors: ['#fff'], width: 3 },
        plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: 'Indicators', color: '#657980', formatter: () => performance.reduce((sum, item) => sum + item.count, 0) } } } } },
        legend: { show: false },
        noData: { text: 'No indicators in this scope' }
    });

    renderChart('#component-performance-chart', {
        ...baseChart,
        chart: { ...baseChart.chart, type: 'bar', height: 315 },
        series: [
            { name: 'Target achievement', data: components.map(item => item.average_achievement === null ? 0 : item.average_achievement) },
            { name: 'Reporting completeness', data: components.map(item => item.average_completeness) }
        ],
        colors: ['#075c7a', '#73a9b6'],
        plotOptions: { bar: { borderRadius: 4, columnWidth: '52%' } },
        xaxis: { categories: components.map(item => item.short_label), labels: { style: { fontSize: '10px' } } },
        yaxis: { min: 0, labels: { formatter: value => Math.round(value) + '%' } },
        annotations: { yaxis: [{ y: 100, borderColor: '#a56a17', strokeDashArray: 4, label: { text: 'Target', style: { background: '#a56a17', color: '#fff', fontSize: '9px' } } }] },
        tooltip: { ...baseChart.tooltip, y: { formatter: value => Number(value).toFixed(1) + '%' } }
    });

    const attainmentMax = Math.max(100, ...attainment.map(item => Number(item.achievement) || 0));
    renderChart('#indicator-attainment-chart', {
        ...baseChart,
        chart: { ...baseChart.chart, type: 'bar', height: Math.max(370, attainment.length * 38) },
        series: [{ name: 'Achievement', data: attainment.map(item => item.achievement) }],
        colors: attainment.map(item => item.color),
        plotOptions: { bar: { horizontal: true, distributed: true, borderRadius: 4, barHeight: '62%', dataLabels: { position: 'top' } } },
        dataLabels: { enabled: true, offsetX: 5, formatter: value => Number(value).toFixed(1) + '%', style: { colors: ['#425b64'], fontSize: '9px' } },
        xaxis: { categories: attainment.map(item => item.code), min: 0, max: Math.ceil(attainmentMax / 25) * 25, labels: { formatter: value => Math.round(value) + '%' } },
        yaxis: { labels: { maxWidth: 85, style: { fontSize: '10px', fontWeight: 700 } } },
        annotations: { xaxis: [{ x: 100, borderColor: '#a56a17', strokeDashArray: 4, label: { text: '100% target', style: { background: '#a56a17', color: '#fff', fontSize: '9px' } } }] },
        legend: { show: false },
        tooltip: { y: { formatter: value => Number(value).toFixed(1) + '% achieved' } }
    });

    renderChart('#reporting-quality-chart', {
        ...baseChart,
        chart: { ...baseChart.chart, type: 'radialBar', height: 235 },
        series: [Number(quality.reporting_completeness) || 0, Number(quality.evidence_verification) || 0],
        labels: ['Completeness', 'Evidence verified'],
        colors: ['#075c7a', '#187459'],
        plotOptions: { radialBar: { hollow: { size: '34%' }, track: { background: '#e7eef0' }, dataLabels: { name: { fontSize: '10px' }, value: { fontSize: '15px', formatter: value => Number(value).toFixed(0) + '%' }, total: { show: true, label: 'Readiness', formatter: () => Number(quality.reporting_completeness || 0).toFixed(0) + '%' } } } },
        legend: { show: false }
    });

    renderChart('#gender-chart', {
        ...baseChart,
        chart: { ...baseChart.chart, type: 'donut', height: 235 },
        series: [Number(gender.female) || 0, Number(gender.male) || 0],
        labels: ['Female', 'Male'],
        colors: ['#6b63a8', '#3f8aa0'],
        stroke: { colors: ['#fbfcfc'], width: 3 },
        plotOptions: { pie: { donut: { size: '62%', labels: { show: true, total: { show: true, label: 'Total', formatter: () => (Number(gender.female) + Number(gender.male)).toLocaleString() } } } } },
        legend: { position: 'bottom', fontSize: '10px' },
        dataLabels: { enabled: false }
    });

    const search = document.getElementById('results-table-search');
    const status = document.getElementById('results-status-filter');
    const clear = document.getElementById('results-table-clear');
    const rows = Array.from(document.querySelectorAll('[data-result-row]'));
    const count = document.getElementById('results-row-count');
    const filterEmpty = document.getElementById('results-table-filter-empty');
    const filterRows = function () {
        const term = (search?.value || '').trim().toLowerCase();
        const selectedStatus = status?.value || '';
        let visible = 0;
        rows.forEach(function (row) {
            const matchesSearch = !term || (row.dataset.search || '').includes(term);
            const matchesStatus = !selectedStatus || row.dataset.status === selectedStatus;
            const show = matchesSearch && matchesStatus;
            row.hidden = !show;
            if (show) visible++;
        });
        if (count) count.textContent = 'Showing ' + visible + ' ' + (visible === 1 ? 'indicator' : 'indicators');
        if (filterEmpty) filterEmpty.style.display = rows.length > 0 && visible === 0 ? 'table-row' : 'none';
    };
    search?.addEventListener('input', filterRows);
    status?.addEventListener('change', filterRows);
    clear?.addEventListener('click', function () { if (search) search.value = ''; if (status) status.value = ''; filterRows(); search?.focus(); });

    const reportingYear = document.getElementById('results-reporting-year');
    const reportingPeriod = document.getElementById('results-period');
    const constrainPeriods = function () {
        if (!reportingYear || !reportingPeriod) return;
        const year = reportingYear.value;
        Array.from(reportingPeriod.options).forEach(function (option, index) {
            if (index === 0) return;
            option.hidden = Boolean(year && option.dataset.year && option.dataset.year !== year);
            option.disabled = option.hidden;
        });
        const selected = reportingPeriod.selectedOptions[0];
        if (selected && selected.disabled) reportingPeriod.value = '';
    };
    reportingYear?.addEventListener('change', constrainPeriods);
    constrainPeriods();
});
</script>
@endpush
@endif
