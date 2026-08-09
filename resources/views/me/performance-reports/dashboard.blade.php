@extends('layouts.app')

@section('title', 'M&E Reporting Operations Dashboard')
@section('lean_admin_scripts', '1')

@push('styles')
    @include('me.performance-reports.partials.dashboard-styles')
@endpush

@section('content')
@php
    $viewer = auth()->user();
    $canOpenRegister = $viewer && ($viewer->can('me.data_entry.view') || $viewer->can('me.data_entry.manage') || $viewer->can('me.configuration.view') || $viewer->can('me.configuration.manage'));
    $canCreateReport = $viewer && ($viewer->can('me.data_entry.manage') || $viewer->can('me.configuration.manage'));
    $canReviewReports = $viewer && $viewer->can('me.performance_reports.review');
    $canOpenConsolidated = $viewer && ($viewer->can('me.performance_reports.view') || $viewer->can('me.performance_reports.review') || $viewer->can('me.configuration.view') || $viewer->can('me.configuration.manage'));
    $canOpenResults = $viewer && ($viewer->can('me.results.view') || $viewer->can('me.performance_reports.view') || $viewer->can('me.configuration.view') || $viewer->can('me.configuration.manage'));
    $canOpenNotifications = $viewer && $viewer->can('me.reporting_notifications.view');
    $advancedKeys = ['geographic_scope','country','rec','implementing_institution_type','implementing_institution','priority_theme','gender','age_group','stakeholder_category'];
    $activeFilterCount = collect($filters)
        ->except(['sort','per_page'])
        ->filter(fn ($value) => filled($value))
        ->count();
    $advancedFilterCount = collect($filters)->only($advancedKeys)->filter(fn ($value) => filled($value))->count();
    $preservedFilters = collect($filters)
        ->reject(fn ($value) => $value === null || $value === '')
        ->reject(fn ($value, $key) => ($key === 'sort' && $value === 'latest_period') || ($key === 'per_page' && (int) $value === 15))
        ->all();
    $dashboardUrl = fn (array $parameters = []) => route('budget.me.rebuild.reporting-dashboard', array_merge($preservedFilters, $parameters));
    $drilldownUrl = fn (string $key) => $dashboardUrl(['drilldown' => $key]).'#report-records';
    $recordStart = $records->total() > 0 ? $records->firstItem() : 0;
    $recordEnd = $records->total() > 0 ? $records->lastItem() : 0;
@endphp

<div class="mel-reporting">
    <header class="rp-header">
        <div>
            <span class="rp-eyebrow">Monitoring, Evaluation and Learning</span>
            <h1>Reporting operations dashboard</h1>
            <p>Manage the complete performance-report lifecycle, monitor reporting deadlines, identify review bottlenecks and inspect submission quality across your authorized portfolio.</p>
        </div>
        <div class="rp-header-side">
            <span class="rp-generated">Updated {{ $generatedAt->format('d M Y, H:i') }}</span>
            <div class="rp-actions">
                @if($canOpenRegister)<a class="rp-btn rp-btn-header" href="{{ route('budget.me.rebuild.data-entry', ['tab' => 'reports']) }}">Report register</a>@endif
                @if($canCreateReport)<a class="rp-btn rp-btn-header" href="{{ route('budget.me.performance-reports.create') }}">Create report</a>@endif
                <a class="rp-btn rp-btn-header" href="{{ route('budget.me.rebuild.reporting-dashboard.csv', $preservedFilters) }}">Download CSV</a>
                <button class="rp-btn rp-btn-header" id="reporting-print" type="button">Print dashboard</button>
            </div>
        </div>
    </header>

    <aside class="rp-scope-note" aria-label="Dashboard scope information">
        <span class="rp-scope-mark">SCP</span>
        <div>
            <strong>Permission and portfolio scope is enforced</strong>
            <p>Every metric, chart, attention item, drill-down and CSV row uses the same authorized data scope and active filters. Select a metric or chart segment to inspect the supporting reports.</p>
        </div>
    </aside>

    <details class="rp-panel rp-filter" @if($activeFilterCount > 0) open @endif>
        <summary class="rp-panel-head">
            <div><h2>Search and report scope</h2><p>Narrow the complete workspace by reporting context, owner, lifecycle or beneficiary dimension.</p></div>
            <div class="rp-summary-right"><span class="rp-badge">{{ $activeFilterCount }} active {{ str('filter')->plural($activeFilterCount) }}</span><span class="rp-chevron" aria-hidden="true">⌄</span></div>
        </summary>
        <div class="rp-panel-body">
            <form method="GET" action="{{ route('budget.me.rebuild.reporting-dashboard') }}" class="rp-filter-grid">
                <div class="rp-field rp-field-wide">
                    <label for="dashboard-search">Search reports</label>
                    <input id="dashboard-search" class="form-control" name="q" value="{{ $filters['q'] }}" placeholder="Form title, code, owner, country, component or portfolio">
                    <small>Search is case-insensitive and updates every dashboard calculation.</small>
                </div>
                <div class="rp-field">
                    <label for="dashboard-year">Reporting year</label>
                    <select class="form-select" id="dashboard-year" name="reporting_year"><option value="">All years</option>@foreach($filterOptions['years'] as $year)<option value="{{ $year }}" @selected((string)$filters['reporting_year']===(string)$year)>{{ $year }}</option>@endforeach</select>
                </div>
                <div class="rp-field">
                    <label for="dashboard-period-type">Reporting frequency</label>
                    <select class="form-select" id="dashboard-period-type" name="reporting_period_type"><option value="">All frequencies</option>@foreach($filterOptions['period_types'] as $value=>$label)<option value="{{ $value }}" @selected($filters['reporting_period_type']===$value)>{{ $label }}</option>@endforeach</select>
                </div>
                <div class="rp-field">
                    <label for="dashboard-period">Reporting period</label>
                    <select class="form-select" id="dashboard-period" name="reporting_period_label"><option value="">All periods</option></select>
                </div>
                <div class="rp-field">
                    <label for="dashboard-status">Workflow stage</label>
                    <select class="form-select" id="dashboard-status" name="status"><option value="">All stages</option>@foreach($filterOptions['statuses'] as $value=>$label)<option value="{{ $value }}" @selected($filters['status']===$value)>{{ $label }}</option>@endforeach</select>
                </div>
                <div class="rp-field">
                    <label for="dashboard-rating">Performance rating</label>
                    <select class="form-select" id="dashboard-rating" name="performance_rating"><option value="">All ratings</option>@foreach($filterOptions['performance_ratings'] as $value=>$label)<option value="{{ $value }}" @selected($filters['performance_rating']===$value)>{{ $label }}</option>@endforeach</select>
                </div>
                <div class="rp-field">
                    <label for="dashboard-component">Project component</label>
                    <select class="form-select" id="dashboard-component" name="component_id"><option value="">All components</option>@foreach($filterOptions['components'] as $component)<option value="{{ $component->id }}" @selected((string)$filters['component_id']===(string)$component->id)>{{ $component->project_id ? $component->project_id.' · ' : '' }}{{ $component->name }}</option>@endforeach</select>
                </div>
                <div class="rp-field">
                    <label for="dashboard-results-level">Results level</label>
                    <select class="form-select" id="dashboard-results-level" name="results_level"><option value="">All results levels</option>@foreach($filterOptions['results_levels'] as $value=>$label)<option value="{{ $value }}" @selected($filters['results_level']===$value)>{{ $label }}</option>@endforeach</select>
                </div>
                <div class="rp-field">
                    <label for="dashboard-owner">Report owner</label>
                    <select class="form-select" id="dashboard-owner" name="think_tank_id"><option value="">All report owners</option><option value="internal" @selected($filters['think_tank_id']==='internal')>Secretariat / Internal</option>@foreach($filterOptions['think_tanks'] as $thinkTank)<option value="{{ $thinkTank->id }}" @selected((string)$filters['think_tank_id']===(string)$thinkTank->id)>{{ $thinkTank->name }} · {{ str($thinkTank->role ?: 'think tank')->headline() }}</option>@endforeach</select>
                </div>
                <div class="rp-field">
                    <label for="dashboard-indicator">Indicator</label>
                    <select class="form-select" id="dashboard-indicator" name="indicator_id"><option value="">All indicators</option>@foreach($filterOptions['indicators'] as $indicator)<option value="{{ $indicator->id }}" @selected((string)$filters['indicator_id']===(string)$indicator->id)>{{ $indicator->indicator_code }} · {{ $indicator->name }}</option>@endforeach</select>
                </div>
                <div class="rp-field">
                    <label for="dashboard-theme">Thematic area / Portfolio</label>
                    <select class="form-select" id="dashboard-theme" name="thematic_area_id"><option value="">All thematic areas</option>@foreach($filterOptions['thematic_areas'] as $thematicArea)<option value="{{ $thematicArea->id }}" @selected((string)$filters['thematic_area_id']===(string)$thematicArea->id)>{{ $thematicArea->name }}</option>@endforeach</select>
                </div>

                <details class="rp-advanced" @if($advancedFilterCount > 0) open @endif>
                    <summary>Beneficiary and achievement disaggregation filters · {{ $advancedFilterCount }} active</summary>
                    <div class="rp-filter-grid">
                        <div class="rp-field"><label for="dashboard-geography">Geographic scope</label><select id="dashboard-geography" class="form-select" name="geographic_scope"><option value="">All scopes</option>@foreach($filterOptions['geographic_scopes'] as $value=>$label)<option value="{{ $value }}" @selected($filters['geographic_scope']===$value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="rp-field"><label for="dashboard-country">Country</label><select id="dashboard-country" class="form-select" name="country"><option value="">All countries</option>@foreach($filterOptions['countries'] as $value=>$label)<option value="{{ $value }}" @selected($filters['country']===$value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="rp-field"><label for="dashboard-rec">Regional Economic Community</label><select id="dashboard-rec" class="form-select" name="rec"><option value="">All RECs</option>@foreach($filterOptions['recs'] as $value=>$label)<option value="{{ $value }}" @selected($filters['rec']===$value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="rp-field"><label for="dashboard-institution-type">Institution type</label><select id="dashboard-institution-type" class="form-select" name="implementing_institution_type"><option value="">All types</option>@foreach($filterOptions['institution_types'] as $value=>$label)<option value="{{ $value }}" @selected($filters['implementing_institution_type']===$value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="rp-field"><label for="dashboard-institution">Implementing institution</label><select id="dashboard-institution" class="form-select" name="implementing_institution"><option value="">All institutions</option>@foreach($filterOptions['institutions'] as $value=>$label)<option value="{{ $value }}" @selected($filters['implementing_institution']===$value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="rp-field"><label for="dashboard-priority">ATTP priority thematic area</label><select id="dashboard-priority" class="form-select" name="priority_theme"><option value="">All priority areas</option>@foreach($filterOptions['priority_themes'] as $value=>$label)<option value="{{ $value }}" @selected($filters['priority_theme']===$value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="rp-field"><label for="dashboard-gender">Gender</label><select id="dashboard-gender" class="form-select" name="gender"><option value="">Female and male</option>@foreach($filterOptions['genders'] as $value=>$label)<option value="{{ $value }}" @selected($filters['gender']===$value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="rp-field"><label for="dashboard-age">Age group</label><select id="dashboard-age" class="form-select" name="age_group"><option value="">All age groups</option>@foreach($filterOptions['age_groups'] as $value=>$label)<option value="{{ $value }}" @selected($filters['age_group']===$value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="rp-field"><label for="dashboard-stakeholder">Stakeholder category</label><select id="dashboard-stakeholder" class="form-select" name="stakeholder_category"><option value="">All stakeholders</option>@foreach($filterOptions['stakeholder_categories'] as $value=>$label)<option value="{{ $value }}" @selected($filters['stakeholder_category']===$value)>{{ $label }}</option>@endforeach</select></div>
                    </div>
                </details>

                <div class="rp-field">
                    <label for="dashboard-sort">Register order</label>
                    <select id="dashboard-sort" class="form-select" name="sort"><option value="latest_period" @selected($filters['sort']==='latest_period')>Latest reporting period</option><option value="oldest_period" @selected($filters['sort']==='oldest_period')>Oldest reporting period</option><option value="recently_updated" @selected($filters['sort']==='recently_updated')>Recently updated</option><option value="workflow_stage" @selected($filters['sort']==='workflow_stage')>Workflow priority</option></select>
                </div>
                <div class="rp-field">
                    <label for="dashboard-page-size">Rows per page</label>
                    <select id="dashboard-page-size" class="form-select" name="per_page">@foreach([15,25,50,100] as $size)<option value="{{ $size }}" @selected($filters['per_page']===$size)>{{ $size }} rows</option>@endforeach</select>
                </div>
                <div class="rp-filter-actions">
                    <p class="rp-filter-tip"><strong>Tip:</strong> lifecycle filters describe where reports are now. Timeliness compares the submission timestamp with the linked collection deadline. Returned reports remain drafts until their author resubmits them.</p>
                    <div class="rp-actions"><a class="rp-btn rp-btn-secondary" href="{{ route('budget.me.rebuild.reporting-dashboard') }}">Clear filters</a><button class="rp-btn rp-btn-primary" type="submit">Apply report scope</button></div>
                </div>
            </form>
        </div>
    </details>

    <section class="rp-metrics" aria-label="Reporting workflow summary">
        <a class="rp-metric" href="{{ $dashboardUrl() }}#report-records"><span class="rp-metric-label">Total reports</span><strong>{{ number_format($totalReports) }}</strong><small>Matching the current authorized scope</small></a>
        <a class="rp-metric" style="--metric:#1676b8" href="{{ $drilldownUrl('review_queue') }}"><span class="rp-metric-label">Awaiting review</span><strong>{{ number_format($awaitingReview) }}</strong><small>{{ $awaitingVerification }} verification · {{ $awaitingApproval }} final approval</small></a>
        <a class="rp-metric" style="--metric:#ae3f3d" href="{{ $drilldownUrl('timeliness_overdue') }}"><span class="rp-metric-label">Overdue reports</span><strong>{{ number_format($overdueReports) }}</strong><small>Draft or returned after the deadline</small></a>
        <a class="rp-metric" style="--metric:#187459" href="{{ $drilldownUrl('timeliness_on_time') }}"><span class="rp-metric-label">On-time submission</span><strong>{{ number_format($onTimeRate,1) }}%</strong><small>Submitted reports with a configured deadline</small></a>
        <a class="rp-metric" style="--metric:#6b63a8" href="{{ $drilldownUrl('submission_ready') }}"><span class="rp-metric-label">Submission readiness</span><strong>{{ number_format($submissionReadiness,1) }}%</strong><small>{{ $submissionReadyCount }} reports have all seven sections</small></a>
        <a class="rp-metric" style="--metric:#a56a17" href="{{ $drilldownUrl('approved_decisions') }}"><span class="rp-metric-label">Avg. final approval</span><strong>{{ $averageApprovalLabel }}</strong><small>{{ $approvalDecisionCount }} completed {{ str('decision')->plural($approvalDecisionCount) }}</small></a>
    </section>

    <div class="rp-grid">
        <section class="rp-panel" aria-labelledby="workflow-chart-title">
            <div class="rp-panel-head"><div><h2 id="workflow-chart-title">Workflow distribution</h2><p>Mutually exclusive lifecycle stage for every matching report.</p></div><span class="rp-badge">{{ $totalReports }} reports</span></div>
            @if($totalReports > 0)<div id="report-workflow-chart" class="rp-chart" role="img" aria-label="Donut chart showing reports by lifecycle stage"></div>@else<div class="rp-empty"><span class="rp-empty-mark">WF</span><strong>No reports in this scope</strong><p>Create a report or broaden the filters to populate lifecycle analytics.</p></div>@endif
        </section>
        <section class="rp-panel" aria-labelledby="timeliness-chart-title">
            <div class="rp-panel-head"><div><h2 id="timeliness-chart-title">Submission timeliness</h2><p>Deadline performance based on each linked data collection.</p></div><span class="rp-badge">{{ number_format($onTimeRate,1) }}% on time</span></div>
            @if($totalReports > 0)<div id="report-timeliness-chart" class="rp-chart" role="img" aria-label="Horizontal bar chart of reporting timeliness"></div>@else<div class="rp-empty"><span class="rp-empty-mark">DL</span><strong>No deadline performance yet</strong><p>Timeliness becomes available when reporting assignments and reports exist.</p></div>@endif
        </section>
    </div>

    <div class="rp-grid">
        <section class="rp-panel" aria-labelledby="period-chart-title">
            <div class="rp-panel-head"><div><h2 id="period-chart-title">Reports by reporting period</h2><p>Reporting volume across year, frequency and period.</p></div></div>
            @if($reportsByPeriod->isNotEmpty())<div id="report-period-chart" class="rp-chart rp-chart-tall" role="img" aria-label="Column chart of reports by reporting period"></div>@else<div class="rp-empty"><span class="rp-empty-mark">RP</span><strong>No reporting-period data</strong><p>No reports match the selected reporting context.</p></div>@endif
        </section>
        <section class="rp-panel" aria-labelledby="owner-chart-title">
            <div class="rp-panel-head"><div><h2 id="owner-chart-title">Reports by think tank or partner</h2><p>Highest reporting volume by organization.</p></div></div>
            @if($reportsByThinkTank->isNotEmpty())<div id="report-owner-chart" class="rp-chart rp-chart-tall" role="img" aria-label="Horizontal bar chart of reports by think tank or partner"></div>@else<div class="rp-empty"><span class="rp-empty-mark">TT</span><strong>No organization-level data</strong><p>Organization comparisons will appear when reports match this scope.</p></div>@endif
        </section>
    </div>

    <div class="rp-grid">
        <section class="rp-panel" aria-labelledby="component-chart-title">
            <div class="rp-panel-head"><div><h2 id="component-chart-title">Reports by project component</h2><p>Reporting coverage across ATTP delivery components.</p></div></div>
            @if($reportsByComponent->isNotEmpty())<div id="report-component-chart" class="rp-chart" role="img" aria-label="Horizontal bar chart of reports by project component"></div>@else<div class="rp-empty"><span class="rp-empty-mark">PC</span><strong>No component report data</strong><p>Component coverage will appear when reports match this scope.</p></div>@endif
        </section>
        <section class="rp-panel" aria-labelledby="quality-chart-title">
            <div class="rp-panel-head"><div><h2 id="quality-chart-title">Indicator completeness and report quality</h2><p>Indicator completeness, seven-section readiness, evidence coverage and performance ratings.</p></div></div>
            @if($totalReports > 0)
                <div class="rp-mini-grid">
                    <div class="rp-mini-card"><h3>Reporting readiness</h3><p>Indicator completeness · sections · evidence</p><div id="report-readiness-chart" class="rp-mini-chart" role="img" aria-label="Radial chart of reporting readiness"></div></div>
                    <div class="rp-mini-card"><h3>Performance ratings</h3><p>Author-assessed overall report performance</p><div id="report-rating-chart" class="rp-mini-chart" role="img" aria-label="Donut chart of report performance ratings"></div></div>
                </div>
            @else<div class="rp-empty"><span class="rp-empty-mark">DQ</span><strong>No quality signals yet</strong><p>Quality analytics appear once reports are created.</p></div>@endif
        </section>
    </div>

    <div class="rp-insight-grid">
        <section class="rp-panel" aria-labelledby="attention-title">
            <div class="rp-panel-head"><div><h2 id="attention-title">Management attention queue</h2><p>Overdue submissions and reports awaiting Secretariat decisions.</p></div><span class="rp-badge {{ $attentionReports->isNotEmpty() ? 'warning' : '' }}">{{ $attentionReports->count() }} shown</span></div>
            <div class="rp-panel-body">
                @if($attentionReports->isNotEmpty())
                    <div class="rp-attention-list">
                        @foreach($attentionReports as $item)
                            @php $isOverdue = $item['timeliness'] === 'overdue'; @endphp
                            <article class="rp-attention">
                                <span class="rp-attention-mark" style="--mark:{{ $isOverdue ? '#ae3f3d' : '#1676b8' }};--mark-soft:{{ $isOverdue ? '#fff0ef' : '#eaf5fc' }}">{{ $isOverdue ? '!' : 'RV' }}</span>
                                <div><strong>{{ $item['title'] }}</strong><p>{{ $item['reason'] }}</p><div class="rp-attention-meta">{{ $item['owner'] }} · {{ $item['period'] }}@if($item['due_at']) · Due {{ $item['due_at']->format('d M Y, H:i') }}@endif</div></div>
                                <a class="rp-btn rp-btn-secondary rp-btn-small" href="{{ route('budget.me.performance-reports.edit', $item['id']) }}">{{ $canReviewReports && in_array($item['stage'], ['submitted','verified'], true) ? 'Review' : 'Open' }}</a>
                            </article>
                        @endforeach
                    </div>
                @else<div class="rp-empty"><span class="rp-empty-mark">OK</span><strong>No immediate reporting exceptions</strong><p>There are no overdue drafts or pending review decisions in the selected scope.</p></div>@endif
            </div>
        </section>
        <section class="rp-panel" aria-labelledby="signals-title">
            <div class="rp-panel-head"><div><h2 id="signals-title">Operational signals</h2><p>Supporting measures behind the management view.</p></div></div>
            <div class="rp-panel-body rp-signal-list">
                <div class="rp-signal"><span>Submitted for verification</span><strong>{{ number_format($awaitingVerification) }}</strong></div>
                <div class="rp-signal"><span>Verified, awaiting approval</span><strong>{{ number_format($awaitingApproval) }}</strong></div>
                <div class="rp-signal"><span>Avg. first review / verification</span><strong>{{ $averageReviewLabel }} · {{ $reviewDecisionCount }} decisions</strong></div>
                <div class="rp-signal"><span>Avg. review &amp; approval (final)</span><strong>{{ $averageApprovalLabel }}</strong></div>
                <div class="rp-signal"><span>Indicator completeness</span><strong>{{ number_format($reportedIndicators) }}/{{ number_format($indicatorTotal) }} · {{ number_format($indicatorCompleteness,1) }}%</strong></div>
                <div class="rp-signal"><span>Evidence coverage</span><strong>{{ $evidenceReportCount }}/{{ $totalReports }} · {{ number_format($evidenceCoverage,1) }}%</strong></div>
                <div class="rp-signal"><span>Reports missing required sections</span><strong>{{ max(0,$totalReports-$submissionReadyCount) }}</strong></div>
            </div>
        </section>
    </div>

    <section class="rp-panel rp-records" id="report-records" aria-labelledby="report-records-title">
        <div class="rp-panel-head">
            <div><h2 id="report-records-title">{{ $drilldownLabel }}</h2><p>Permission-scoped drill-down with lifecycle, timeliness, readiness and evidence details.</p></div>
            <div class="rp-actions">
                @if($drilldown)<a class="rp-btn rp-btn-secondary rp-btn-small" href="{{ $dashboardUrl() }}#report-records">Clear drill-down</a>@endif
                @if($canOpenConsolidated)<a class="rp-btn rp-btn-secondary rp-btn-small" href="{{ route('budget.me.consolidated-reports.index') }}">Consolidated reports</a>@endif
                @if($canOpenResults)<a class="rp-btn rp-btn-secondary rp-btn-small" href="{{ route('budget.me.results-dashboard.index') }}">Approved results</a>@endif
                @if($canOpenNotifications)<a class="rp-btn rp-btn-secondary rp-btn-small" href="{{ route('budget.me.reporting-notifications.index') }}">Notifications</a>@endif
                <a class="rp-btn rp-btn-primary rp-btn-small" href="{{ route('budget.me.rebuild.reporting-dashboard.csv', $preservedFilters) }}">Export filtered CSV</a>
            </div>
        </div>
        <div class="rp-record-summary"><strong>Showing {{ number_format($recordStart) }}–{{ number_format($recordEnd) }} of {{ number_format($records->total()) }} {{ str('report')->plural($records->total()) }}</strong><span>Page {{ $records->currentPage() }} of {{ max(1,$records->lastPage()) }}</span></div>
        @if($records->isEmpty())
            <div class="rp-empty"><span class="rp-empty-mark">0</span><strong>No reports match this selection</strong><p>Clear the drill-down or broaden one or more report filters.</p></div>
        @else
            <div class="rp-table-wrap">
                <table class="rp-table">
                    <thead><tr><th>Report</th><th>Owner</th><th>Component / Portfolio</th><th>Workflow</th><th>Timeliness</th><th>Reporting readiness</th><th>Evidence / Rating</th><th>Action</th></tr></thead>
                    <tbody>
                    @foreach($records as $report)
                        @php
                            $stage = $stageConfiguration[$report->dashboard_stage] ?? $stageConfiguration['draft'];
                            $time = $timelinessConfiguration[$report->dashboard_timeliness] ?? $timelinessConfiguration['no_deadline'];
                            $resultTotal = (int)$report->indicator_results_count;
                            $resultComplete = (int)$report->reported_indicator_results_count;
                            $resultPercent = $resultTotal > 0 ? round(($resultComplete/$resultTotal)*100,1) : 0;
                            $sectionPercent = round(((int)$report->dashboard_completed_sections/7)*100,1);
                            $reviewAction = $canReviewReports && in_array($report->dashboard_stage,['submitted','verified'],true);
                        @endphp
                        <tr>
                            <td><span class="rp-record-title">{{ $report->form?->title ?: 'Form unavailable' }}</span><span class="rp-record-meta">{{ $report->form?->code ?: 'No form code' }} · {{ $report->periodLabel() }}</span><span class="rp-record-meta">Updated {{ $report->updated_at?->format('d M Y, H:i') }}</span></td>
                            <td><span class="rp-record-title">{{ $report->thinkTank?->name ?: 'Secretariat / Internal' }}</span><span class="rp-record-meta">{{ $report->thinkTank ? str($report->thinkTank->role ?: 'think tank')->headline() : 'Internal report' }}</span></td>
                            <td><span class="rp-record-title">{{ $report->projectComponent?->name ?: 'Component unavailable' }}</span><span class="rp-record-meta">{{ $report->projectComponent?->project_id ?: 'No component code' }} · {{ $report->portfolio?->name ?: 'No portfolio' }}</span></td>
                            <td><span class="rp-status" style="--pill:{{ $stage['color'] }};--soft:{{ $stage['soft_color'] }}">{{ $stage['label'] }}</span><span class="rp-record-meta">{{ $report->dashboard_submission_ready ? 'All required sections complete' : $report->dashboard_missing_sections.' required sections incomplete' }}</span></td>
                            <td><span class="rp-status" style="--pill:{{ $time['color'] }};--soft:#fff">{{ $time['label'] }}</span><span class="rp-record-meta">{{ $report->assignment?->collection?->due_at?->format('d M Y, H:i') ?: 'Deadline not configured' }}</span></td>
                            <td><strong>{{ $resultComplete }}/{{ $resultTotal }} indicator results</strong><div class="rp-progress"><span style="width:{{ min(100,$resultPercent) }}%"></span></div><span class="rp-record-meta">{{ $report->dashboard_completed_sections }}/7 sections · {{ number_format($sectionPercent,1) }}%</span></td>
                            <td><strong>{{ $report->documents_count }} supporting {{ str('file')->plural($report->documents_count) }}</strong><span class="rp-record-meta">{{ \App\Models\MePerformanceReport::PERFORMANCE_RATINGS[$report->performance_rating] ?? 'Not rated' }}</span></td>
                            <td><a class="rp-btn {{ $reviewAction ? 'rp-btn-primary' : 'rp-btn-secondary' }} rp-btn-small" href="{{ route('budget.me.performance-reports.edit',$report) }}">{{ $reviewAction ? 'Review report' : ($report->isEditable() && $canCreateReport ? 'Continue report' : 'View report') }}</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="rp-scroll-tip"><span>Scroll horizontally to view all operational fields.</span><span>Milestone and numeric indicator results use their correct completion rules.</span></div>
            <div class="rp-pagination">{{ $records->links() }}</div>
        @endif
    </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('admin/assets/vendors/js/apexcharts.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const distribution = {{ \Illuminate\Support\Js::from($distribution->values()->all()) }};
    const timeliness = {{ \Illuminate\Support\Js::from($timeliness->values()->all()) }};
    const periods = {{ \Illuminate\Support\Js::from($reportsByPeriod->values()->all()) }};
    const owners = {{ \Illuminate\Support\Js::from($reportsByThinkTank->values()->all()) }};
    const components = {{ \Illuminate\Support\Js::from($reportsByComponent->values()->all()) }};
    const ratings = {{ \Illuminate\Support\Js::from($ratingDistribution->values()->all()) }};
    const readiness = {{ \Illuminate\Support\Js::from([$indicatorCompleteness,$submissionReadiness,$evidenceCoverage]) }};
    const dashboardRoute = {{ \Illuminate\Support\Js::from(route('budget.me.rebuild.reporting-dashboard')) }};
    const base = { chart:{fontFamily:'Inter, Arial, sans-serif',foreColor:'#657980',toolbar:{show:false},animations:{speed:420}}, grid:{borderColor:'#e5edef',strokeDashArray:3}, dataLabels:{style:{fontSize:'10px',fontWeight:700}}, tooltip:{theme:'light'}, legend:{fontSize:'10px',fontWeight:600} };
    const render = function (selector, options) { const target=document.querySelector(selector); if(target && window.ApexCharts) new ApexCharts(target,options).render(); };
    const inspectReports = function (parameters) {
        const destination = new URL(dashboardRoute, window.location.origin);
        const current = new URL(window.location.href);
        current.searchParams.forEach((value,key) => { if (!['records_page','drilldown'].includes(key)) destination.searchParams.set(key,value); });
        Object.entries(parameters).forEach(([key,value]) => {
            if (value === null || value === '') destination.searchParams.delete(key);
            else destination.searchParams.set(key,String(value));
        });
        destination.hash = 'report-records';
        window.location.assign(destination.toString());
    };
    const pointSelection = function (items, resolver, useSeriesIndex = false) {
        return { dataPointSelection: function (_event,_chart,selection) {
            const position = useSeriesIndex ? selection.seriesIndex : selection.dataPointIndex;
            const item = items[position];
            if (item) inspectReports(resolver(item));
        }};
    };

    render('#report-workflow-chart',{...base,chart:{...base.chart,type:'donut',height:305,events:pointSelection(distribution,item=>({drilldown:'stage_'+item.key}))},series:distribution.map(item=>item.count),labels:distribution.map(item=>item.label),colors:distribution.map(item=>item.color),stroke:{colors:['#fff'],width:3},plotOptions:{pie:{donut:{size:'67%',labels:{show:true,total:{show:true,label:'Reports',formatter:()=>distribution.reduce((sum,item)=>sum+item.count,0)}}}}},legend:{position:'bottom',fontSize:'11px'},dataLabels:{enabled:false},noData:{text:'No workflow data'}});
    render('#report-timeliness-chart',{...base,chart:{...base.chart,type:'bar',height:305,events:pointSelection(timeliness,item=>({drilldown:'timeliness_'+item.key}))},series:[{name:'Reports',data:timeliness.map(item=>item.count)}],colors:timeliness.map(item=>item.color),plotOptions:{bar:{horizontal:true,distributed:true,borderRadius:4,barHeight:'58%'}},xaxis:{categories:timeliness.map(item=>item.label),min:0,forceNiceScale:true,labels:{formatter:value=>Math.round(value)}},legend:{show:false},dataLabels:{enabled:true,formatter:value=>Math.round(value)}});
    render('#report-period-chart',{...base,chart:{...base.chart,type:'bar',height:350,events:pointSelection(periods,item=>{const [reporting_year,reporting_period_type,reporting_period_label]=item.key.split('|');return {reporting_year,reporting_period_type,reporting_period_label};})},series:[{name:'Reports',data:periods.map(item=>item.count)}],colors:['#075c7a'],plotOptions:{bar:{borderRadius:4,columnWidth:'50%'}},xaxis:{categories:periods.map(item=>item.label),labels:{rotate:-35,trim:true,style:{fontSize:'11px'}}},yaxis:{min:0,forceNiceScale:true,labels:{formatter:value=>Math.round(value)}},dataLabels:{enabled:true,formatter:value=>Math.round(value)},legend:{show:false}});
    render('#report-owner-chart',{...base,chart:{...base.chart,type:'bar',height:Math.max(350,owners.length*38),events:pointSelection(owners,item=>({think_tank_id:item.key}))},series:[{name:'Reports',data:owners.map(item=>item.count)}],colors:['#3f8aa0'],plotOptions:{bar:{horizontal:true,borderRadius:4,barHeight:'60%'}},xaxis:{categories:owners.map(item=>item.label),min:0,forceNiceScale:true,labels:{formatter:value=>Math.round(value)}},yaxis:{labels:{maxWidth:190,style:{fontSize:'11px'}}},dataLabels:{enabled:true,formatter:value=>Math.round(value)},legend:{show:false}});
    render('#report-component-chart',{...base,chart:{...base.chart,type:'bar',height:305,events:pointSelection(components,item=>({component_id:item.key}))},series:[{name:'Reports',data:components.map(item=>item.count)}],colors:['#6b63a8'],plotOptions:{bar:{horizontal:true,borderRadius:4,barHeight:'58%'}},xaxis:{categories:components.map(item=>item.subtitle),min:0,forceNiceScale:true,labels:{formatter:value=>Math.round(value)}},yaxis:{labels:{maxWidth:150,style:{fontSize:'11px'}}},dataLabels:{enabled:true,formatter:value=>Math.round(value)},legend:{show:false}});
    render('#report-readiness-chart',{...base,chart:{...base.chart,type:'radialBar',height:245,events:pointSelection([{drilldown:'indicator_complete'},{drilldown:'submission_ready'},{drilldown:'evidence_present'}],item=>({drilldown:item.drilldown}),true)},series:readiness,labels:['Indicators','Seven sections','Evidence'],colors:['#075c7a','#6b63a8','#187459'],plotOptions:{radialBar:{hollow:{size:'28%'},track:{background:'#e7eef0'},dataLabels:{name:{fontSize:'11px'},value:{fontSize:'14px',formatter:value=>Number(value).toFixed(0)+'%'},total:{show:true,label:'Ready',formatter:()=>Number(readiness[1]||0).toFixed(0)+'%'}}}},legend:{show:false}});
    render('#report-rating-chart',{...base,chart:{...base.chart,type:'donut',height:245,events:pointSelection(ratings,item=>({performance_rating:item.key}))},series:ratings.map(item=>item.count),labels:ratings.map(item=>item.label),colors:ratings.map(item=>item.color),stroke:{colors:['#fbfcfc'],width:3},plotOptions:{pie:{donut:{size:'61%',labels:{show:true,total:{show:true,label:'Reports',formatter:()=>ratings.reduce((sum,item)=>sum+item.count,0)}}}}},legend:{position:'bottom',fontSize:'11px'},dataLabels:{enabled:false}});

    const type=document.getElementById('dashboard-period-type');
    const period=document.getElementById('dashboard-period');
    const periodLabels={{ \Illuminate\Support\Js::from($filterOptions['period_labels']) }};
    let currentPeriod={{ \Illuminate\Support\Js::from($filters['reporting_period_label']) }};
    const refreshPeriods=function(){ if(!period)return; period.innerHTML=''; period.add(new Option('All periods','')); const groups=type?.value?{[type.value]:periodLabels[type.value]||{}}:periodLabels; Object.entries(groups).forEach(([frequency,options])=>Object.entries(options).forEach(([value,label])=>{const option=new Option(label,value,false,value===currentPeriod);option.dataset.frequency=frequency;period.add(option);})); };
    type?.addEventListener('change',function(){currentPeriod=null;refreshPeriods();});
    refreshPeriods();
    document.getElementById('reporting-print')?.addEventListener('click',()=>window.print());
});
</script>
@endpush
