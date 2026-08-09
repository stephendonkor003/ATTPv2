@extends('layouts.app')

@section('title', 'M&E Management Dashboard')
@section('lean_admin_scripts', '1')

@push('styles')
    @include('me.management-dashboard.partials.styles')
@endpush

@section('content')
@php
    $viewer = auth()->user();
    $canOpenReporting = $viewer && collect(['me.performance_reports.view','me.performance_reports.review','me.performance_reports.archive','me.data_entry.view','me.data_entry.manage','me.configuration.view','me.configuration.manage'])->contains(fn ($permission) => $viewer->can($permission));
    $canOpenResults = $viewer && collect(['me.results.view','me.performance_reports.view','me.configuration.view','me.configuration.manage'])->contains(fn ($permission) => $viewer->can($permission));
    $canOpenConsolidated = $viewer && collect(['me.performance_reports.view','me.performance_reports.review','me.configuration.view','me.configuration.manage'])->contains(fn ($permission) => $viewer->can($permission));
    $canOpenDqa = $viewer && collect(['me.configuration.view','me.configuration.manage','me.dqa.manage','me.submissions.review','me.data_entry.view','me.data_entry.manage'])->contains(fn ($permission) => $viewer->can($permission));
    $canOpenReviews = $viewer && collect(['me.submissions.review','me.data_entry.manage','me.configuration.manage'])->contains(fn ($permission) => $viewer->can($permission));
    $canOpenNotifications = $viewer && $viewer->can('me.reporting_notifications.view');
    $canOpenRepository = $viewer && collect(['me.configuration.view','me.configuration.manage'])->contains(fn ($permission) => $viewer->can($permission));
    $activeFilterCount = collect($filters)->filter(fn ($value) => filled($value))->count();
    $managementFilters = collect($filters)->filter(fn ($value) => filled($value))->all();
    $reportingFilters = collect([
        'reporting_year' => $filters['reporting_year'],
        'think_tank_id' => $filters['think_tank_id'],
        'thematic_area_id' => $filters['portfolio_id'],
    ])->filter(fn ($value) => filled($value))->all();
    $dqaFilters = collect([
        'reporting_year' => $filters['reporting_year'],
        'think_tank_id' => $filters['think_tank_id'],
        'portfolio_id' => $filters['portfolio_id'],
        'reporting_period_id' => $filters['reporting_period_id'],
    ])->filter(fn ($value) => filled($value))->all();
    $resultsFilters = collect([
        'reporting_year' => $filters['reporting_year'],
        'think_tank_id' => $filters['think_tank_id'],
    ])->filter(fn ($value) => filled($value))->all();
    $formatPercent = fn ($value) => number_format((float) $value, 1).'%';
    $statusTone = fn (string $status) => match ($status) {
        'open' => 'success',
        'deadline_passed' => 'danger',
        'closed' => 'neutral',
        default => 'info',
    };
@endphp

<div class="mel-management">
    <header class="md-header">
        <div>
            <span class="md-eyebrow">Monitoring, Evaluation and Learning</span>
            <h1>Management dashboard</h1>
            <p>A decision-ready view of official performance, reporting coverage, approval bottlenecks and data-quality risk across your authorized ATTP portfolio.</p>
        </div>
        <div class="md-header-side">
            <span class="md-generated">Updated {{ $generatedAt->format('d M Y, H:i') }}</span>
            <div class="md-actions">
                @if($canOpenResults)<a class="md-btn md-btn-header" href="{{ route('budget.me.results-dashboard.index', $resultsFilters) }}">Official results</a>@endif
                @if($canOpenReporting)<a class="md-btn md-btn-header" href="{{ route('budget.me.rebuild.reporting-dashboard', $reportingFilters) }}">Reporting operations</a>@endif
                @if($canOpenConsolidated)<a class="md-btn md-btn-header" href="{{ route('budget.me.consolidated-reports.index', $managementFilters) }}">Consolidated reports</a>@endif
                <button class="md-btn md-btn-header" id="management-print" type="button">Print snapshot</button>
            </div>
        </div>
    </header>

    <aside class="md-guardrail" aria-label="Management dashboard data rule">
        <span class="md-guardrail-mark">GOV</span>
        <div>
            <strong>Official performance is approval-controlled</strong>
            <p>Achievement, rating and evidence metrics use approved or archived reports only. Draft and in-review records remain visible in workflow health, but cannot influence official performance decisions. {{ $isPortfolioScoped ? 'Your assigned portfolio boundary is applied to every metric and drill-down.' : 'Your authorized organization scope is applied to every metric and drill-down.' }}</p>
        </div>
    </aside>

    <details class="md-panel md-filter" @if($activeFilterCount > 0) open @endif>
        <summary class="md-panel-head">
            <div><h2>Management reporting scope</h2><p>Use one scope across performance, submissions, data quality, periods and organization coverage.</p></div>
            <div class="md-summary-right"><span class="md-badge">{{ $activeFilterCount }} active {{ str('filter')->plural($activeFilterCount) }}</span><span class="md-chevron" aria-hidden="true">⌄</span></div>
        </summary>
        <div class="md-panel-body">
            <form class="md-filter-grid" method="GET" action="{{ route('budget.me.rebuild.management-dashboard') }}">
                <div class="md-field">
                    <label for="management-year">Reporting year</label>
                    <select class="form-select" id="management-year" name="reporting_year">
                        <option value="">All years</option>
                        @foreach($filterOptions['years'] as $year)<option value="{{ $year }}" @selected((string)$filters['reporting_year'] === (string)$year)>{{ $year }}</option>@endforeach
                    </select>
                    <small>Controls both report years and configured reporting periods.</small>
                </div>
                <div class="md-field">
                    <label for="management-portfolio">Thematic area / Portfolio</label>
                    <select class="form-select" id="management-portfolio" name="portfolio_id">
                        <option value="">All authorized portfolios</option>
                        @foreach($filterOptions['portfolios'] as $portfolio)<option value="{{ $portfolio->id }}" @selected((string)$filters['portfolio_id'] === (string)$portfolio->id)>{{ $portfolio->name }}</option>@endforeach
                    </select>
                    <small>Portfolio managers see only their assigned portfolio options.</small>
                </div>
                <div class="md-field">
                    <label for="management-period">Reporting period</label>
                    <select class="form-select" id="management-period" name="reporting_period_id">
                        <option value="">All reporting periods</option>
                        @foreach($filterOptions['periods'] as $period)<option value="{{ $period->id }}" data-year="{{ $period->reporting_year }}" data-portfolio="{{ $period->portfolio_id }}" @selected((string)$filters['reporting_period_id'] === (string)$period->id)>{{ $period->reporting_year ? $period->reporting_year.' · ' : '' }}{{ $period->label }}</option>@endforeach
                    </select>
                    <small>Use a period for a precise reporting-window snapshot.</small>
                </div>
                <div class="md-field">
                    <label for="management-organization">Reporting organization</label>
                    <select class="form-select" id="management-organization" name="think_tank_id">
                        <option value="">All reporting organizations</option>
                        @foreach($filterOptions['thinkTanks'] as $thinkTank)<option value="{{ $thinkTank->id }}" @selected((string)$filters['think_tank_id'] === (string)$thinkTank->id)>{{ $thinkTank->name }}{{ $thinkTank->country ? ' · '.$thinkTank->country : '' }}</option>@endforeach
                    </select>
                    <small>Recalculates expected, submitted and approved coverage.</small>
                </div>
                <div class="md-filter-actions">
                    <p class="md-filter-tip"><strong>Reading the dashboard:</strong> workflow cards show operational records; official performance cards deliberately exclude unapproved results. Empty values mean the selected scope has no eligible records, not that performance is zero.</p>
                    <div class="md-actions">
                        <a class="md-btn md-btn-secondary" href="{{ route('budget.me.rebuild.management-dashboard') }}">Clear scope</a>
                        <button class="md-btn md-btn-primary" type="submit">Apply scope</button>
                    </div>
                </div>
            </form>
        </div>
    </details>

    <section class="md-metrics" aria-label="Management performance indicators">
        <a class="md-metric" style="--metric:#187459" href="{{ $canOpenResults ? route('budget.me.results-dashboard.index', $resultsFilters) : '#official-performance' }}">
            <span class="md-metric-label">Official reports</span><strong>{{ number_format($metrics['official_reports']) }}</strong>
            <small>{{ $formatPercent($metrics['official_rate']) }} of {{ number_format($reportCount) }} workflow reports are approved or archived.</small>
        </a>
        <a class="md-metric" style="--metric:#075c7a" href="#official-performance">
            <span class="md-metric-label">Average achievement</span><strong>{{ $metrics['average_achievement'] === null ? '—' : $formatPercent($metrics['average_achievement']) }}</strong>
            <small>Approved indicator results with a calculated target-achievement value.</small>
        </a>
        <a class="md-metric" style="--metric:#1676b8" href="#organization-coverage">
            <span class="md-metric-label">Reporting coverage</span><strong>{{ $formatPercent($metrics['reporting_coverage']) }}</strong>
            <small>{{ number_format($metrics['submitted_assignments']) }} of {{ number_format($metrics['total_assignments']) }} expected assignments submitted.</small>
        </a>
        <a class="md-metric" style="--metric:#a56a17" href="{{ $canOpenReporting ? route('budget.me.rebuild.reporting-dashboard', array_merge($reportingFilters, ['drilldown' => 'review_queue'])).'#report-records' : '#management-actions' }}">
            <span class="md-metric-label">Awaiting decision</span><strong>{{ number_format($metrics['awaiting_decision']) }}</strong>
            <small>Submitted or verified reports requiring a management workflow decision.</small>
        </a>
        <a class="md-metric" style="--metric:#ae3f3d" href="{{ $canOpenDqa ? route('budget.me.rebuild.data-quality', array_merge($dqaFilters, ['severity' => 'error', 'finding_status' => 'open'])) : '#management-actions' }}">
            <span class="md-metric-label">Blocking DQA errors</span><strong>{{ number_format($metrics['open_errors']) }}</strong>
            <small>{{ number_format($metrics['open_warnings']) }} additional open {{ str('warning')->plural($metrics['open_warnings']) }} in scope.</small>
        </a>
        <a class="md-metric" style="--metric:#6b63a8" href="{{ $canOpenResults ? route('budget.me.results-dashboard.index', $resultsFilters) : '#official-performance' }}">
            <span class="md-metric-label">Evidence coverage</span><strong>{{ $formatPercent($metrics['evidence_coverage']) }}</strong>
            <small>{{ number_format($metrics['evidenced_official_reports']) }} official {{ str('report')->plural($metrics['evidenced_official_reports']) }} contain supporting evidence.</small>
        </a>
    </section>

    <div class="md-grid" id="official-performance">
        <section class="md-panel" aria-labelledby="portfolio-chart-title">
            <div class="md-panel-head">
                <div><h2 id="portfolio-chart-title">Portfolio readiness</h2><p>Reporting coverage, official approval and evidence coverage by active portfolio.</p></div>
                <span class="md-badge">Target = 100%</span>
            </div>
            @if($portfolioRows->isNotEmpty())
                <div id="management-portfolio-chart" class="md-chart md-chart-tall" role="img" aria-label="Portfolio readiness comparison chart"></div>
            @else
                <div class="md-empty"><span class="md-empty-mark">PF</span><strong>No portfolio activity in this scope</strong><p>Readiness appears after assignments or performance reports are recorded.</p></div>
            @endif
        </section>

        <section class="md-panel" id="management-actions" aria-labelledby="actions-title">
            <div class="md-panel-head"><div><h2 id="actions-title">Management action queue</h2><p>Priority work derived directly from the selected scope.</p></div></div>
            <div class="md-action-list">
                @php
                    $actionItems = collect([
                        ['mark'=>'RVW','title'=>'Reports awaiting decision','count'=>$metrics['awaiting_decision'],'description'=>'Verify submitted reports and issue final approval for verified records.','tone'=>'warning','url'=>$canOpenReporting ? route('budget.me.rebuild.reporting-dashboard', array_merge($reportingFilters,['drilldown'=>'review_queue'])).'#report-records' : null],
                        ['mark'=>'DQA','title'=>'Blocking data-quality errors','count'=>$metrics['open_errors'],'description'=>'Resolve or return submissions before they proceed to final approval.','tone'=>'danger','url'=>$canOpenDqa ? route('budget.me.rebuild.data-quality', array_merge($dqaFilters,['severity'=>'error','finding_status'=>'open'])) : null],
                        ['mark'=>'AGE','title'=>'Findings open over seven days','count'=>$findingAging['overdue'],'description'=>'Escalate aged findings whose corrective action remains incomplete.','tone'=>'danger','url'=>$canOpenDqa ? route('budget.me.rebuild.data-quality', array_merge($dqaFilters,['finding_status'=>'open','sort'=>'aging'])) : null],
                        ['mark'=>'RISK','title'=>'Official reports needing attention','count'=>$metrics['attention_reports'],'description'=>'Review approved at-risk and off-track performance and agree adaptive action.','tone'=>'warning','url'=>$canOpenResults ? route('budget.me.results-dashboard.index', $resultsFilters) : null],
                    ]);
                @endphp
                @foreach($actionItems as $action)
                    <article class="md-action-item">
                        <span class="md-action-mark {{ $action['tone'] }}">{{ $action['mark'] }}</span>
                        <div><strong>{{ $action['title'] }}</strong><p>{{ $action['description'] }}</p></div>
                        <div class="md-action-end"><strong>{{ number_format($action['count']) }}</strong>@if($action['url'])<a href="{{ $action['url'] }}">Open queue</a>@endif</div>
                    </article>
                @endforeach
            </div>
        </section>
    </div>

    <div class="md-grid md-grid-balanced">
        <section class="md-panel" aria-labelledby="lifecycle-title">
            <div class="md-panel-head"><div><h2 id="lifecycle-title">Report lifecycle</h2><p>All workflow reports, including drafts and reports under review.</p></div><span class="md-badge">{{ number_format($reportCount) }} reports</span></div>
            @if($reportCount > 0)
                <div id="management-lifecycle-chart" class="md-chart" role="img" aria-label="Performance report lifecycle chart"></div>
            @else
                <div class="md-empty"><span class="md-empty-mark">RPT</span><strong>No performance reports in this scope</strong><p>Lifecycle distribution appears after a report is created.</p></div>
            @endif
        </section>
        <section class="md-panel" aria-labelledby="performance-title">
            <div class="md-panel-head"><div><h2 id="performance-title">Official performance rating</h2><p>Approved and archived reports only.</p></div><span class="md-badge success">Approval controlled</span></div>
            @if($metrics['official_reports'] > 0)
                <div id="management-performance-chart" class="md-chart" role="img" aria-label="Official performance rating chart"></div>
            @else
                <div class="md-empty"><span class="md-empty-mark">APR</span><strong>No approved performance ratings</strong><p>Ratings appear only after reports receive final approval.</p></div>
            @endif
        </section>
        <section class="md-panel" aria-labelledby="quality-title">
            <div class="md-panel-head"><div><h2 id="quality-title">Data-quality disposition</h2><p>Current open errors, open warnings and resolved findings.</p></div><span class="md-badge {{ $metrics['open_errors'] > 0 ? 'danger' : 'success' }}">{{ number_format($submissionCount) }} submissions</span></div>
            @if($dataQuality->sum('count') > 0)
                <div id="management-quality-chart" class="md-chart" role="img" aria-label="Data quality finding disposition chart"></div>
                <div class="md-aging">
                    <span><strong>{{ number_format($findingAging['new']) }}</strong>0–2 days</span>
                    <span><strong>{{ number_format($findingAging['attention']) }}</strong>3–7 days</span>
                    <span class="danger"><strong>{{ number_format($findingAging['overdue']) }}</strong>Over 7 days</span>
                </div>
            @else
                <div class="md-empty"><span class="md-empty-mark">DQA</span><strong>No data-quality findings</strong><p>Findings appear after submitted records are evaluated by the DQA engine.</p></div>
            @endif
        </section>
    </div>

    <section class="md-panel md-table-panel" id="organization-coverage" aria-labelledby="organization-title">
        <div class="md-panel-head">
            <div><h2 id="organization-title">Reporting organization coverage</h2><p>Expected assignments, submissions, approvals, overdue work and blocking data-quality errors.</p></div>
            <span class="md-badge">{{ number_format($organizationRows->count()) }} organizations</span>
        </div>
        <div class="md-table-toolbar">
            <div class="md-search"><span aria-hidden="true">⌕</span><input class="form-control" id="organization-search" type="search" placeholder="Search organization or country" aria-label="Search organization coverage"></div>
            <span id="organization-row-count">{{ number_format($organizationRows->count()) }} rows shown</span>
        </div>
        @if($organizationRows->isNotEmpty())
            <div class="md-table-wrap">
                <table class="md-table" id="organization-table">
                    <thead><tr><th>Reporting organization</th><th>Expected</th><th>Submitted</th><th>Coverage</th><th>Approved</th><th>Approval rate</th><th>Overdue</th><th>Blocking errors</th><th>Last submission</th><th>Action</th></tr></thead>
                    <tbody>
                    @foreach($organizationRows as $row)
                        <tr data-search="{{ str($row['name'].' '.$row['country'])->lower() }}">
                            <td><strong class="md-table-title">{{ $row['name'] }}</strong><small>{{ $row['country'] ?: 'Country not specified' }}</small></td>
                            <td>{{ number_format($row['expected']) }}</td>
                            <td>{{ number_format($row['submitted']) }}</td>
                            <td><strong>{{ $formatPercent($row['coverage']) }}</strong><span class="md-progress"><span style="width:{{ min(100,$row['coverage']) }}%;--bar:#1676b8"></span></span></td>
                            <td>{{ number_format($row['approved']) }}</td>
                            <td><strong>{{ $formatPercent($row['approval_rate']) }}</strong><span class="md-progress"><span style="width:{{ min(100,$row['approval_rate']) }}%;--bar:#187459"></span></span></td>
                            <td><span class="md-status {{ $row['overdue'] > 0 ? 'danger' : 'neutral' }}">{{ number_format($row['overdue']) }}</span></td>
                            <td><span class="md-status {{ $row['open_errors'] > 0 ? 'danger' : 'success' }}">{{ number_format($row['open_errors']) }}</span></td>
                            <td>{{ $row['last_submission_at']?->format('d M Y, H:i') ?? 'No submission' }}</td>
                            <td>@if($canOpenReporting && $row['id'])<a class="md-btn md-btn-small md-btn-secondary" href="{{ route('budget.me.rebuild.reporting-dashboard', array_merge($reportingFilters,['think_tank_id'=>$row['id']])) }}">Inspect</a>@else<span class="md-muted">View only</span>@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="md-scroll-tip"><span>Scroll horizontally for all management fields.</span><span>Coverage = submitted ÷ expected assignments.</span></div>
        @else
            <div class="md-empty"><span class="md-empty-mark">ORG</span><strong>No assigned reporting organizations</strong><p>Create and assign a reporting collection to populate organization coverage.</p></div>
        @endif
    </section>

    <div class="md-bottom-grid">
        <section class="md-panel" aria-labelledby="period-title">
            <div class="md-panel-head"><div><h2 id="period-title">Reporting window health</h2><p>Upcoming, open, passed and closed reporting periods with submission coverage.</p></div></div>
            @if($periodHealth->isNotEmpty())
                <div class="md-period-list">
                    @foreach($periodHealth as $period)
                        <article class="md-period">
                            <div class="md-period-top"><div><strong>{{ $period['label'] }}</strong><small>{{ $period['portfolio'] }} · {{ $period['reporting_year'] ?: 'Year not set' }}</small></div><span class="md-status {{ $statusTone($period['status']) }}">{{ $period['status_label'] }}</span></div>
                            <div class="md-period-meta"><span>Deadline <strong>{{ $period['deadline']?->format('d M Y, H:i') ?? 'Not configured' }}</strong></span><span>Coverage <strong>{{ $period['submitted'] }}/{{ $period['expected'] }} · {{ $formatPercent($period['coverage']) }}</strong></span></div>
                            <span class="md-progress md-progress-wide"><span style="width:{{ min(100,$period['coverage']) }}%;--bar:{{ $period['status']==='deadline_passed' ? '#ae3f3d' : '#075c7a' }}"></span></span>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="md-empty"><span class="md-empty-mark">CAL</span><strong>No reporting periods in this scope</strong><p>Configure reporting windows to monitor deadlines and expected coverage.</p></div>
            @endif
        </section>

        <section class="md-panel" aria-labelledby="decision-title">
            <div class="md-panel-head"><div><h2 id="decision-title">Recent official decisions</h2><p>The latest reports promoted into the official results record.</p></div></div>
            @if($recentDecisions->isNotEmpty())
                <div class="md-decision-list">
                    @foreach($recentDecisions as $report)
                        <article class="md-decision">
                            <span class="md-decision-mark">{{ $report->status === 'archived' ? 'ARC' : 'APR' }}</span>
                            <div><strong>{{ $report->form?->title ?: 'Performance report' }}</strong><p>{{ $report->thinkTank?->name ?: 'Secretariat / Internal' }} · {{ $report->periodLabel() }}</p><small>{{ $report->portfolio?->name ?: 'Portfolio not assigned' }} · {{ $report->approved_at?->format('d M Y, H:i') ?? 'Approval date unavailable' }}</small></div>
                            @if($canOpenReporting)<a class="md-btn md-btn-small md-btn-secondary" href="{{ route('budget.me.performance-reports.edit', $report) }}">Open</a>@endif
                        </article>
                    @endforeach
                </div>
            @else
                <div class="md-empty"><span class="md-empty-mark">APR</span><strong>No official decisions yet</strong><p>Approved reports will appear here after the Secretariat completes final review.</p></div>
            @endif
        </section>

        <section class="md-panel" aria-labelledby="workspace-title">
            <div class="md-panel-head"><div><h2 id="workspace-title">M&E workspaces</h2><p>Move from management signal to the responsible operational workspace.</p></div></div>
            <nav class="md-workspaces" aria-label="Related M&E workspaces">
                @if($canOpenReviews)<a href="{{ route('budget.me.submission-reviews.index', $dqaFilters) }}"><span>Review</span><strong>Submission review queue</strong><small>Verify submissions and record decisions.</small></a>@endif
                @if($canOpenDqa)<a href="{{ route('budget.me.rebuild.data-quality', $dqaFilters) }}"><span>Quality</span><strong>Data quality workflow</strong><small>Investigate, resolve and audit findings.</small></a>@endif
                @if($canOpenNotifications)<a href="{{ route('budget.me.reporting-notifications.index') }}"><span>Notify</span><strong>Reporting notifications</strong><small>Manage reminders and workflow alerts.</small></a>@endif
                @if($canOpenRepository)<a href="{{ route('budget.me.rebuild.knowledge-repository') }}"><span>Evidence</span><strong>Knowledge repository</strong><small>Inspect controlled evidence and document versions.</small></a>@endif
                @if($canOpenConsolidated)<a href="{{ route('budget.me.consolidated-reports.index', $managementFilters) }}"><span>Report</span><strong>Consolidated reporting</strong><small>Generate management and partner-ready reports.</small></a>@endif
            </nav>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('admin/assets/vendors/js/apexcharts.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const lifecycle = {{ \Illuminate\Support\Js::from($lifecycle) }};
    const performance = {{ \Illuminate\Support\Js::from($performance) }};
    const quality = {{ \Illuminate\Support\Js::from($dataQuality) }};
    const portfolios = {{ \Illuminate\Support\Js::from($portfolioRows) }};
    const reportingBase = {{ \Illuminate\Support\Js::from(route('budget.me.rebuild.reporting-dashboard')) }};
    const dqaBase = {{ \Illuminate\Support\Js::from(route('budget.me.rebuild.data-quality')) }};
    const reportingFilters = {{ \Illuminate\Support\Js::from($reportingFilters) }};
    const dqaFilters = {{ \Illuminate\Support\Js::from($dqaFilters) }};
    const canReporting = {{ \Illuminate\Support\Js::from($canOpenReporting) }};
    const canDqa = {{ \Illuminate\Support\Js::from($canOpenDqa) }};
    const openUrl = function (base, parameters, anchor) {
        const url = new URL(base, window.location.origin);
        Object.entries(parameters).forEach(([key,value]) => { if (value !== null && value !== '') url.searchParams.set(key,value); });
        if (anchor) url.hash = anchor;
        window.location.href = url.toString();
    };
    const render = function (selector, options) {
        const element = document.querySelector(selector);
        if (element && window.ApexCharts) new ApexCharts(element, options).render();
    };
    const base = {
        chart: { toolbar: { show: false }, fontFamily: 'Inter, Arial, sans-serif', animations: { enabled: false } },
        grid: { borderColor: '#e5edef', strokeDashArray: 3 },
        tooltip: { theme: 'light' },
        noData: { text: 'No records in this scope' },
        dataLabels: { style: { fontSize: '11px' } },
    };
    render('#management-portfolio-chart', {
        ...base,
        chart: { ...base.chart, type: 'bar', height: Math.max(340, portfolios.length * 48), events: { dataPointSelection: (_,__,config) => { if (canReporting && portfolios[config.dataPointIndex]) openUrl(reportingBase, {...reportingFilters,thematic_area_id:portfolios[config.dataPointIndex].id}); } } },
        series: [
            { name: 'Reporting coverage', data: portfolios.map(item => item.coverage) },
            { name: 'Official approval', data: portfolios.map(item => item.official_rate) },
            { name: 'Evidence coverage', data: portfolios.map(item => item.evidence_coverage) },
        ],
        colors: ['#1676b8','#187459','#6b63a8'],
        plotOptions: { bar: { horizontal: true, borderRadius: 3, barHeight: '68%' } },
        xaxis: { categories: portfolios.map(item => item.name), min: 0, max: 100, labels: { formatter: value => Math.round(value) + '%' } },
        yaxis: { labels: { maxWidth: 210, style: { fontSize: '11px' } } },
        legend: { position: 'top', horizontalAlign: 'left', fontSize: '11px' },
        dataLabels: { enabled: false },
    });
    render('#management-lifecycle-chart', {
        ...base,
        chart: { ...base.chart, type: 'bar', height: 295, events: { dataPointSelection: (_,__,config) => { const item=lifecycle[config.dataPointIndex]; if (canReporting && item) openUrl(reportingBase,{...reportingFilters,status:item.key},'report-records'); } } },
        series: [{ name: 'Reports', data: lifecycle.map(item => item.count) }],
        colors: lifecycle.map(item => item.color),
        plotOptions: { bar: { distributed: true, borderRadius: 4, columnWidth: '52%' } },
        xaxis: { categories: lifecycle.map(item => item.label) },
        yaxis: { min: 0, forceNiceScale: true, labels: { formatter: value => Math.round(value) } },
        dataLabels: { enabled: true, formatter: value => Math.round(value) },
        legend: { show: false },
    });
    render('#management-performance-chart', {
        ...base,
        chart: { ...base.chart, type: 'donut', height: 295 },
        series: performance.map(item => item.count),
        labels: performance.map(item => item.label),
        colors: performance.map(item => item.color),
        stroke: { colors: ['#fff'], width: 3 },
        plotOptions: { pie: { donut: { size: '64%', labels: { show: true, total: { show: true, label: 'Official', formatter: () => performance.reduce((sum,item)=>sum+item.count,0) } } } } },
        dataLabels: { enabled: false },
        legend: { position: 'bottom', fontSize: '11px' },
    });
    render('#management-quality-chart', {
        ...base,
        chart: { ...base.chart, type: 'donut', height: 255, events: { dataPointSelection: (_,__,config) => { const item=quality[config.dataPointIndex]; if (!canDqa || !item) return; const extra=item.key==='resolved'?{finding_status:'resolved'}:{finding_status:'open',severity:item.key==='errors'?'error':'warning'}; openUrl(dqaBase,{...dqaFilters,...extra}); } } },
        series: quality.map(item => item.count),
        labels: quality.map(item => item.label),
        colors: quality.map(item => item.color),
        stroke: { colors: ['#fff'], width: 3 },
        plotOptions: { pie: { donut: { size: '62%', labels: { show: true, total: { show: true, label: 'Findings', formatter: () => quality.reduce((sum,item)=>sum+item.count,0) } } } } },
        dataLabels: { enabled: false },
        legend: { position: 'bottom', fontSize: '11px' },
    });

    const search = document.getElementById('organization-search');
    const rows = Array.from(document.querySelectorAll('#organization-table tbody tr'));
    const count = document.getElementById('organization-row-count');
    search?.addEventListener('input', function () {
        const term = this.value.trim().toLowerCase();
        let visible = 0;
        rows.forEach(row => { const show = !term || (row.dataset.search || '').includes(term); row.hidden = !show; if (show) visible++; });
        if (count) count.textContent = visible.toLocaleString() + ' rows shown';
    });
    document.getElementById('management-print')?.addEventListener('click', () => window.print());

    const year = document.getElementById('management-year');
    const portfolio = document.getElementById('management-portfolio');
    const period = document.getElementById('management-period');
    const filterPeriods = function () {
        if (!period) return;
        const selected = period.value;
        Array.from(period.options).forEach((option,index) => {
            if (index === 0) return;
            const yearMatches = !year?.value || option.dataset.year === year.value;
            const portfolioMatches = !portfolio?.value || option.dataset.portfolio === portfolio.value;
            option.hidden = !(yearMatches && portfolioMatches);
        });
        if (period.selectedOptions[0]?.hidden) period.value = '';
        if (selected && period.value === '') period.dispatchEvent(new Event('change'));
    };
    year?.addEventListener('change', filterPeriods);
    portfolio?.addEventListener('change', filterPeriods);
    filterPeriods();
});
</script>
@endpush
