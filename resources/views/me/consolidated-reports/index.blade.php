@extends('layouts.app')

@section('title', 'Think Tank M&E Reports')
@section('lean_admin_scripts', '1')

@push('styles')
    @include('me.consolidated-reports.partials.styles')
@endpush

@section('content')
@php
    $viewer = auth()->user();
    $canReviewReports = $viewer?->can('me.performance_reports.review') ?? false;
    $canOpenResults = $viewer && ($viewer->can('me.results.view') || $viewer->can('me.performance_reports.view') || $viewer->can('me.configuration.view') || $viewer->can('me.configuration.manage'));
    $advancedKeys = ['geographic_scope','country','rec','implementing_institution_type','implementing_institution','priority_theme','gender','age_group','stakeholder_category'];
    $advancedFilterCount = collect($filters)->only($advancedKeys)->filter(fn ($value) => filled($value))->count();
    $optionalFilterCount = collect($filters)->only([...$advancedKeys,'portfolio_id','project_component_id','think_tank_id'])->filter(fn ($value) => filled($value))->count();
    $exportFilters = collect([
        'reporting_year' => $filters['year'],
        'reporting_period_type' => $filters['period_type'],
        'reporting_period_label' => $filters['period_label'],
        'portfolio_id' => $filters['portfolio_id'],
        'project_component_id' => $filters['project_component_id'],
        'think_tank_id' => $filters['think_tank_id'],
        ...collect($filters)->only($advancedKeys)->all(),
    ])->filter(fn ($value) => filled($value))->all();
    $dashboardFilters = collect($exportFilters)
        ->except(['portfolio_id','project_component_id'])
        ->when(filled($filters['portfolio_id']), fn ($values) => $values->put('thematic_area_id', $filters['portfolio_id']))
        ->when(filled($filters['project_component_id']), fn ($values) => $values->put('component_id', $filters['project_component_id']))
        ->all();
    $yearOptions = collect($years)->push($filters['year'])->filter()->unique()->sortDesc()->values();
    $contextLabel = ($periodTypes[$filters['period_type']] ?? str($filters['period_type'])->headline()).' · '.($periodLabels[$filters['period_type']][$filters['period_label']] ?? $filters['period_label']).' '.$filters['year'];
    if ($selectedProject) {
        $contextLabel .= ' | '.collect([$selectedProject->project_id, $selectedProject->name])->filter()->join(' - ');
    }
@endphp

<div class="mel-consolidated">
    <header class="cr-header">
        <div>
            <span class="cr-eyebrow">Monitoring, Evaluation and Learning</span>
            <h1>Think Tank M&amp;E Reports</h1>
            <p>Select a Think Tank to view its M&amp;E reports, inspect individual submissions and consolidate only finally approved results using the authorized indicator-level roll-up method.</p>
        </div>
        <div class="cr-header-side">
            <span class="cr-generated">{{ $contextLabel }} · Updated {{ $generatedAt->format('d M Y, H:i') }}</span>
            <div class="cr-actions">
                <a class="cr-btn cr-btn-header" href="{{ route('budget.me.rebuild.reporting-dashboard', $dashboardFilters) }}">Reporting dashboard</a>
                @if($canOpenResults)<a class="cr-btn cr-btn-header" href="{{ route('budget.me.results-dashboard.index') }}">Approved results</a>@endif
                <a class="cr-btn cr-btn-header" href="{{ route('budget.me.consolidated-reports.excel', $exportFilters) }}">Download Excel</a>
                <a class="cr-btn cr-btn-header" href="{{ route('budget.me.consolidated-reports.pdf', $exportFilters) }}">Download PDF</a>
            </div>
        </div>
    </header>

    <aside class="cr-note" aria-label="Consolidation policy">
        <span class="cr-note-mark">APR</span>
        <div><strong>Official consolidation uses final approvals only</strong><p>Draft, submitted, reviewed and verified reports remain visible in the organization register, but they never enter the consolidated indicator totals. Archived reports retain their approved contribution. All results and export files use the same portfolio, project and disaggregation scope.</p></div>
    </aside>

    @if($errors->any())<div class="cr-alert" role="alert"><strong>The report scope could not be applied.</strong> {{ $errors->first() }}</div>@endif

    <details class="cr-panel cr-filter" open>
        <summary class="cr-panel-head">
            <div><h2>Find a Think Tank report</h2><p>Choose a Think Tank, then narrow its reports by period, portfolio, project or beneficiary dimension.</p></div>
            <div class="cr-summary-right"><span class="cr-badge">{{ $optionalFilterCount }} optional {{ str('filter')->plural($optionalFilterCount) }}</span><span class="cr-chevron" aria-hidden="true">⌄</span></div>
        </summary>
        <div class="cr-panel-body">
            <form method="GET" action="{{ route('budget.me.consolidated-reports.index') }}" class="cr-filter-grid cr-primary-filters" id="consolidated-filter">
                <div class="cr-field"><label for="consolidated-owner">Think Tank</label><select id="consolidated-owner" name="think_tank_id" class="form-select"><option value="">All Think Tanks</option>@foreach($thinkTanks as $thinkTank)<option value="{{ $thinkTank->id }}" @selected((string)$filters['think_tank_id']===(string)$thinkTank->id)>{{ $thinkTank->name }}</option>@endforeach</select></div>
                <div class="cr-field"><label for="consolidated-year">Reporting year</label><select id="consolidated-year" name="reporting_year" class="form-select">@foreach($yearOptions as $year)<option value="{{ $year }}" @selected((int)$filters['year']===(int)$year)>{{ $year }}</option>@endforeach</select></div>
                <div class="cr-field"><label for="consolidated-period-type">Reporting frequency</label><select name="reporting_period_type" id="consolidated-period-type" class="form-select">@foreach($periodTypes as $value=>$label)<option value="{{ $value }}" @selected($filters['period_type']===$value)>{{ $label }}</option>@endforeach</select></div>
                <div class="cr-field"><label for="consolidated-period-label">Reporting period</label><select name="reporting_period_label" id="consolidated-period-label" class="form-select"></select></div>
                <div class="cr-field"><label for="consolidated-portfolio">Portfolio</label><select id="consolidated-portfolio" name="portfolio_id" class="form-select" data-consolidated-portfolio><option value="">All authorized portfolios</option>@foreach($portfolios as $portfolio)<option value="{{ $portfolio->id }}" @selected((string)$filters['portfolio_id']===(string)$portfolio->id)>{{ $portfolio->name }}</option>@endforeach</select></div>
                <div class="cr-field">
                    <label for="consolidated-project">Project</label>
                    <select id="consolidated-project" name="project_component_id" class="form-select" data-consolidated-project aria-describedby="consolidated-project-help">
                        <option value="">All report-bearing projects</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" data-portfolio-id="{{ $project->program?->sector_id }}" @selected((string)$filters['project_component_id']===(string)$project->id)>{{ $project->project_id }} &middot; {{ $project->name }}</option>
                        @endforeach
                    </select>
                    <small id="consolidated-project-help" aria-live="polite">Choose a portfolio to narrow this list to its projects.</small>
                </div>

                <details class="cr-advanced" @if($advancedFilterCount > 0) open @endif>
                    <summary>Beneficiary and achievement disaggregation filters · {{ $advancedFilterCount }} active</summary>
                    <div class="cr-filter-grid">
                        <div class="cr-field"><label for="consolidated-geography">Geographic scope</label><select id="consolidated-geography" name="geographic_scope" class="form-select"><option value="">All scopes</option>@foreach($disaggregationOptions['geographic_scopes'] as $value=>$label)<option value="{{ $value }}" @selected($filters['geographic_scope']===$value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="cr-field"><label for="consolidated-country">Country</label><select id="consolidated-country" name="country" class="form-select"><option value="">All countries</option>@foreach($disaggregationOptions['countries'] as $value=>$label)<option value="{{ $value }}" @selected($filters['country']===$value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="cr-field"><label for="consolidated-rec">Regional Economic Community</label><select id="consolidated-rec" name="rec" class="form-select"><option value="">All RECs</option>@foreach($disaggregationOptions['recs'] as $value=>$label)<option value="{{ $value }}" @selected($filters['rec']===$value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="cr-field"><label for="consolidated-institution-type">Institution type</label><select id="consolidated-institution-type" name="implementing_institution_type" class="form-select"><option value="">All types</option>@foreach($disaggregationOptions['institution_types'] as $value=>$label)<option value="{{ $value }}" @selected($filters['implementing_institution_type']===$value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="cr-field"><label for="consolidated-institution">Implementing institution</label><select id="consolidated-institution" name="implementing_institution" class="form-select"><option value="">All institutions</option>@foreach($disaggregationOptions['institutions'] as $value=>$label)<option value="{{ $value }}" @selected($filters['implementing_institution']===$value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="cr-field"><label for="consolidated-theme">ATTP priority thematic area</label><select id="consolidated-theme" name="priority_theme" class="form-select"><option value="">All priority areas</option>@foreach($disaggregationOptions['priority_themes'] as $value=>$label)<option value="{{ $value }}" @selected($filters['priority_theme']===$value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="cr-field"><label for="consolidated-gender">Gender</label><select id="consolidated-gender" name="gender" class="form-select"><option value="">Female and male</option>@foreach($disaggregationOptions['genders'] as $value=>$label)<option value="{{ $value }}" @selected($filters['gender']===$value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="cr-field"><label for="consolidated-age">Age group</label><select id="consolidated-age" name="age_group" class="form-select"><option value="">All age groups</option>@foreach($disaggregationOptions['age_groups'] as $value=>$label)<option value="{{ $value }}" @selected($filters['age_group']===$value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="cr-field"><label for="consolidated-stakeholder">Stakeholder category</label><select id="consolidated-stakeholder" name="stakeholder_category" class="form-select"><option value="">All stakeholders</option>@foreach($disaggregationOptions['stakeholder_categories'] as $value=>$label)<option value="{{ $value }}" @selected($filters['stakeholder_category']===$value)>{{ $label }}</option>@endforeach</select></div>
                    </div>
                </details>

                <div class="cr-filter-actions">
                    <p class="cr-filter-tip"><strong>How this works:</strong> a beneficiary filter now includes only indicator results whose achievement breakdown matches every selected dimension. It no longer pulls unrelated indicators from the same report into official totals.</p>
                    <div class="cr-actions"><a class="cr-btn cr-btn-secondary" href="{{ route('budget.me.consolidated-reports.index') }}">Clear filters</a><button class="cr-btn cr-btn-primary" type="submit">View reports</button></div>
                </div>
            </form>
        </div>
    </details>

    <section class="cr-metrics" aria-label="Consolidated reporting summary">
        <article class="cr-metric"><span class="cr-metric-label">Organizations in scope</span><strong>{{ number_format($organizationCount) }}</strong><small>Active organizations plus any period contributors</small></article>
        <article class="cr-metric" style="--metric:#1676b8"><span class="cr-metric-label">Reporting coverage</span><strong>{{ number_format($coverageRate,1) }}%</strong><small>{{ $submittedOrganizationCount }} of {{ $organizationCount }} organizations submitted</small></article>
        <article class="cr-metric" style="--metric:#187459"><span class="cr-metric-label">Final approval coverage</span><strong>{{ number_format($approvalRate,1) }}%</strong><small>{{ $approvedOrganizationCount }} organizations have approved inputs</small></article>
        <article class="cr-metric" style="--metric:#6b63a8"><span class="cr-metric-label">Consolidated indicators</span><strong>{{ number_format($consolidated->count()) }}</strong><small>Indicators with matching, finally approved results</small></article>
        <article class="cr-metric" style="--metric:#a56a17"><span class="cr-metric-label">Achievements</span><strong>{{ number_format($totalAchievements) }}</strong><small>Matching approved achievement records</small></article>
        <article class="cr-metric" style="--metric:#0e7490"><span class="cr-metric-label">Beneficiaries</span><strong>{{ number_format($totalBeneficiaries) }}</strong><small>From the filtered disaggregation breakdown</small></article>
    </section>

    <div class="cr-grid">
        <section class="cr-panel" aria-labelledby="submission-stage-title">
            <div class="cr-panel-head"><div><h2 id="submission-stage-title">Submission lifecycle</h2><p>Current workflow stage of every matching organization report.</p></div><span class="cr-badge">{{ $reports->count() }} reports</span></div>
            @if($reports->isNotEmpty())<div id="consolidated-stage-chart" class="cr-chart" role="img" aria-label="Donut chart of submission lifecycle stages"></div>@else<div class="cr-empty"><span class="cr-empty-mark">WF</span><strong>{{ $selectedProject ? 'No submissions in this project and reporting scope' : 'No submissions in this reporting scope' }}</strong><p>Broaden the filters or select a reporting period with organization activity.</p></div>@endif
        </section>
        <section class="cr-panel" aria-labelledby="organization-volume-title">
            <div class="cr-panel-head"><div><h2 id="organization-volume-title">Reports by organization</h2><p>Submission volume for each organization in the current scope.</p></div><span class="cr-badge">{{ $submittedOrganizationCount }} reporting</span></div>
            @if($organizationRows->isNotEmpty())<div id="consolidated-organization-chart" class="cr-chart" role="img" aria-label="Horizontal bar chart of reports by organization"></div>@else<div class="cr-empty"><span class="cr-empty-mark">TT</span><strong>No organizations available</strong><p>No reporting organizations are visible within your authorized scope.</p></div>@endif
        </section>
    </div>

    <div class="cr-grid">
        <section class="cr-panel" aria-labelledby="indicator-contribution-title">
            <div class="cr-panel-head"><div><h2 id="indicator-contribution-title">Indicator reporting coverage</h2><p>Number of unique organizations contributing approved values to each indicator.</p></div><span class="cr-badge">Approved only</span></div>
            @if($consolidated->isNotEmpty())<div id="consolidated-indicator-chart" class="cr-chart" role="img" aria-label="Bar chart of organizations contributing to indicators"></div>@else<div class="cr-empty"><span class="cr-empty-mark">IN</span><strong>No approved indicator values</strong><p>Verified reports require final approval before their results become official.</p></div>@endif
        </section>
        <section class="cr-panel" aria-labelledby="beneficiary-profile-title">
            <div class="cr-panel-head"><div><h2 id="beneficiary-profile-title">Beneficiary profile</h2><p>Gender and age totals from the matching approved breakdown records.</p></div><span class="cr-badge">{{ number_format($totalBeneficiaries) }} recorded</span></div>
            @if($totalBeneficiaries > 0)<div class="cr-mini-grid"><div class="cr-mini-card"><h3>Gender distribution</h3><p>Female, male and not disaggregated</p><div id="consolidated-gender-chart" class="cr-mini-chart"></div></div><div class="cr-mini-card"><h3>Age distribution</h3><p>Youth, adults and not disaggregated</p><div id="consolidated-age-chart" class="cr-mini-chart"></div></div></div>@else<div class="cr-empty"><span class="cr-empty-mark">BG</span><strong>No beneficiary breakdown in this scope</strong><p>Approved indicator values may exist without beneficiary-level records.</p></div>@endif
        </section>
    </div>

    <section class="cr-panel" style="margin-top:1rem" aria-labelledby="quality-signals-title">
        <div class="cr-panel-head"><div><h2 id="quality-signals-title">Consolidation quality controls</h2><p>Signals that explain the reliability and auditability of this official output.</p></div>@if($duplicateResultCount > 0)<span class="cr-badge warning">Review overlap warning</span>@else<span class="cr-badge">No overlapping sources</span>@endif</div>
        <div class="cr-panel-body cr-quality-grid">
            <div class="cr-quality"><span>Approved or archived source reports</span><strong>{{ number_format($approvedReports->count()) }}</strong></div>
            <div class="cr-quality"><span>Pending or draft source reports excluded</span><strong>{{ number_format(max(0,$reports->count()-$approvedReports->count())) }}</strong></div>
            <div class="cr-quality"><span>Supporting documents on approved reports</span><strong>{{ number_format($evidenceDocumentCount) }}</strong></div>
            <div class="cr-quality"><span>Overlapping approved values suppressed</span><strong>{{ number_format($duplicateResultCount) }}</strong></div>
        </div>
    </section>

    <section class="cr-panel" id="organization-register" style="margin-top:1rem" aria-labelledby="organization-register-title">
        <div class="cr-panel-head"><div><h2 id="organization-register-title">Organization submission register</h2><p>Every organization is visible, including organizations without a submission in this reporting context.</p></div><span class="cr-badge">Lifecycle workspace</span></div>
        <div class="cr-toolbar">
            <div class="cr-toolbar-fields"><input id="organization-search" class="form-control" placeholder="Search organization, country or report"><select id="organization-stage" class="form-select"><option value="">All workflow stages</option>@foreach($stageConfiguration as $key=>$stage)<option value="{{ $key }}">{{ $stage['label'] }}</option>@endforeach<option value="missing">No submission</option></select></div>
            <span class="cr-toolbar-count"><span id="organization-visible-count">{{ $organizationRows->count() }}</span> of {{ $organizationRows->count() }} organizations shown</span>
        </div>
        @if($organizationRows->isEmpty())
            <div class="cr-empty"><span class="cr-empty-mark">0</span><strong>No organizations match this scope</strong><p>Reset the organization filter or confirm that the account has access to the selected portfolio.</p></div>
        @else
            <div class="cr-table-wrap"><table class="cr-table"><thead><tr><th>Think tank / partner</th><th>Country</th><th>Submission summary</th><th>Current lifecycle</th><th>Evidence</th><th>Last activity</th><th>Action</th></tr></thead><tbody>
            @foreach($organizationRows as $item)
                @php
                    $statuses = $item['reports']->map(fn($report) => match($report->status){'submitted'=>'submitted','reviewed','verified'=>'verified','approved'=>'approved','archived'=>'archived',default=>'draft'})->unique()->values();
                    $searchText = str($item['think_tank']->name.' '.$item['think_tank']->country.' '.$item['reports']->pluck('form.title')->join(' '))->lower();
                @endphp
                <tr data-org-row data-search="{{ $searchText }}" data-status="{{ $statuses->isEmpty() ? 'missing' : $statuses->join(' ') }}">
                    <td><span class="cr-title">{{ $item['think_tank']->name }}</span><span class="cr-meta">{{ str($item['think_tank']->role ?: 'think tank')->headline() }}@if($item['think_tank']->status !== 'active') · Inactive organization retained as a period contributor @endif</span></td>
                    <td>{{ $item['think_tank']->country ?: 'Not recorded' }}</td>
                    <td><strong>{{ $item['report_count'] }} {{ str('report')->plural($item['report_count']) }}</strong><span class="cr-meta">{{ $item['indicator_count'] }} linked indicator {{ str('result')->plural($item['indicator_count']) }} · {{ $item['approved_count'] }} approved input(s)</span>@foreach($item['reports'] as $organizationReport)<span class="cr-meta">{{ $organizationReport->form?->code ?: 'No form code' }} · {{ $organizationReport->form?->title ?: 'Form unavailable' }}</span>@if($organizationReport->projectComponent)<span class="cr-meta">Project: {{ $organizationReport->projectComponent->project_id }} · {{ $organizationReport->projectComponent->name }}</span>@endif @endforeach</td>
                    <td>
                        @forelse($item['reports'] as $organizationReport)
                            @php
                                $stageKey = match($organizationReport->status) {
                                    'submitted' => 'submitted',
                                    'reviewed', 'verified' => 'verified',
                                    'approved' => 'approved',
                                    'archived' => 'archived',
                                    default => 'draft',
                                };
                                $stage = $stageConfiguration[$stageKey];
                            @endphp
                            <span class="cr-status" style="--pill:{{ $stage['color'] }};--soft:{{ $stage['soft_color'] }}">{{ $stage['label'] }}</span>
                        @empty
                            <span class="cr-status" style="--pill:#ae3f3d;--soft:#fff0ef">No submission</span>
                        @endforelse
                    </td>
                    <td><strong>{{ $item['document_count'] }} supporting {{ str('file')->plural($item['document_count']) }}</strong><span class="cr-meta">Evidence from every period submission</span></td>
                    <td>{{ $item['latest_update']?->format('d M Y, H:i') ?: 'No activity recorded' }}</td>
                    <td>@forelse($item['reports'] as $organizationReport)<a href="{{ route('budget.me.performance-reports.edit',$organizationReport) }}" class="cr-btn {{ $canReviewReports && in_array($organizationReport->status,['submitted','verified','reviewed'],true) ? 'cr-btn-primary' : 'cr-btn-secondary' }} cr-btn-small">{{ $canReviewReports && in_array($organizationReport->status,['submitted','verified','reviewed'],true) ? 'Review' : 'View' }} {{ $organizationReport->form?->code ?: 'report' }}</a>@empty<span class="cr-meta">Awaiting submission</span>@endforelse</td>
                </tr>
            @endforeach
            <tr id="organization-no-match" hidden><td colspan="7"><div class="cr-empty"><strong>No organizations match the local register search.</strong><p>Clear the search term or workflow-stage selector.</p></div></td></tr>
            </tbody></table></div>
            <div class="cr-scroll-tip"><span>Use the search and workflow controls to inspect organizations without changing official totals.</span><span>Scroll horizontally to view all submission controls.</span></div>
        @endif
    </section>

    <section class="cr-panel" id="consolidated-indicators" style="margin-top:1rem" aria-labelledby="consolidated-indicators-title">
        <div class="cr-panel-head"><div><h2 id="consolidated-indicators-title">Approved consolidated indicator performance</h2><p>Numeric and qualitative results are preserved. Draft, submitted, reviewed and merely verified reports are deliberately excluded.</p></div><span class="cr-badge">{{ $consolidated->count() }} indicators</span></div>
        <div class="cr-toolbar"><div class="cr-toolbar-fields"><input id="indicator-search" class="form-control" placeholder="Search indicator code, title or organization"></div><span class="cr-toolbar-count"><span id="indicator-visible-count">{{ $consolidated->count() }}</span> of {{ $consolidated->count() }} indicators shown</span></div>
        @if($consolidated->isEmpty())
            <div class="cr-empty"><span class="cr-empty-mark">0</span><strong>No finally approved think-tank data is available</strong><p>Final approval is required before a result appears here or in the Excel and PDF exports.</p></div>
        @else
            <div class="cr-table-wrap"><table class="cr-table wide"><thead><tr><th>Indicator</th><th>Authorized roll-up</th><th>Consolidated result</th><th>Target</th><th>Reporting organizations</th><th>Outputs</th><th>Beneficiaries</th><th>Disaggregation snapshot</th></tr></thead><tbody>
            @foreach($consolidated as $row)
                @php
                    $indicator = $row['indicator'];
                    $unit = $indicator?->unit?->symbol ?: $indicator?->unit?->name;
                    $isQualitative = $indicator?->value_type === 'milestone' || $row['qualitative_values']->isNotEmpty();
                    $indicatorSearch = str(($indicator?->indicator_code).' '.($indicator?->name).' '.$row['organizations']->join(' '))->lower();
                @endphp
                <tr data-indicator-row data-search="{{ $indicatorSearch }}">
                    <td><span class="cr-meta" style="color:#075c7a;font-weight:800">{{ $indicator?->indicator_code ?: 'No indicator code' }}</span><span class="cr-title">{{ $indicator?->name ?: 'Indicator unavailable' }}</span><span class="cr-meta">{{ str($indicator?->value_type ?: 'number')->headline() }} · {{ str($indicator?->results_level ?: 'results framework')->headline() }}</span>@if($indicator?->projectComponent)<span class="cr-meta">Project: {{ $indicator->projectComponent->project_id }} · {{ $indicator->projectComponent->name }}</span>@endif</td>
                    <td><strong>{{ $row['rollup_label'] }}</strong><span class="cr-meta">Configured on the indicator; not chosen by this report.</span>@if($row['duplicate_result_count'] > 0)<div class="cr-warning">{{ $row['duplicate_result_count'] }} overlapping approved {{ str('value')->plural($row['duplicate_result_count']) }} suppressed</div>@endif</td>
                    <td>@if($isQualitative)<div class="cr-qualitative">@forelse($row['qualitative_values'] as $qualitative)<div><strong>{{ $qualitative['organization'] }}</strong>{{ $qualitative['value'] }}</div>@empty<span class="cr-meta">No qualitative result recorded</span>@endforelse</div>@else<span class="cr-result">{{ $row['value'] !== null ? number_format($row['value'],2) : 'Not numerically additive' }}</span>@if($unit)<span class="cr-meta">{{ $unit }}</span>@endif @endif</td>
                    <td>@if($row['target'] !== null)<strong>{{ number_format($row['target'],2) }}</strong>@if($unit)<span class="cr-meta">{{ $unit }}</span>@endif @else<span class="cr-meta">No single common target</span>@endif</td>
                    <td><strong>{{ $row['organization_count'] }} organizations</strong><span class="cr-meta">{{ $row['reported_value_count'] }} authoritative {{ str('value')->plural($row['reported_value_count']) }}</span><div>@foreach($row['organizations'] as $organization)<span class="cr-chip">{{ $organization }}</span>@endforeach</div></td>
                    <td><strong>{{ number_format($row['achievement_count']) }} achievements</strong><span class="cr-meta">Matching approved achievement records</span></td>
                    <td><strong>{{ number_format($row['beneficiary_count']) }}</strong><span class="cr-meta">F {{ number_format($row['gender']->get('female',0)) }} · M {{ number_format($row['gender']->get('male',0)) }} · Youth {{ number_format($row['age_groups']->get('youth_below_35',0)) }} · Adults {{ number_format($row['age_groups']->get('adult_35_plus',0)) }}</span></td>
                    <td><span class="cr-meta"><strong>Countries:</strong> {{ $row['countries']->keys()->take(4)->join(', ') ?: 'Not recorded' }}</span><span class="cr-meta"><strong>RECs:</strong> {{ $row['recs']->keys()->take(4)->map(fn($value)=>strtoupper($value))->join(', ') ?: 'Not recorded' }}</span><span class="cr-meta"><strong>Stakeholders:</strong> {{ $row['stakeholders']->keys()->take(4)->map(fn($value)=>str($value)->headline())->join(', ') ?: 'Not recorded' }}</span><span class="cr-meta"><strong>Themes:</strong> {{ $row['themes']->keys()->take(4)->map(fn($value)=>str($value)->headline())->join(', ') ?: 'Not recorded' }}</span></td>
                </tr>
            @endforeach
            <tr id="indicator-no-match" hidden><td colspan="8"><div class="cr-empty"><strong>No indicators match the local search.</strong><p>Clear the indicator search to restore every approved row.</p></div></td></tr>
            </tbody></table></div>
            <div class="cr-scroll-tip"><span>Qualitative milestone statuses remain attributable to their source organization.</span><span>Excel and PDF exports use this exact approved consolidation scope.</span></div>
        @endif
    </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('admin/assets/vendors/js/apexcharts.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const periodType = document.getElementById('consolidated-period-type');
    const periodLabel = document.getElementById('consolidated-period-label');
    const periodLabels = {{ \Illuminate\Support\Js::from($periodLabels) }};
    let currentPeriod = {{ \Illuminate\Support\Js::from($filters['period_label']) }};
    const refreshPeriods = function () {
        if (!periodLabel || !periodType) return;
        periodLabel.innerHTML = '';
        Object.entries(periodLabels[periodType.value] || {}).forEach(([value,label]) => periodLabel.add(new Option(label,value,false,value === currentPeriod)));
    };
    periodType?.addEventListener('change', function () { currentPeriod = null; refreshPeriods(); });
    refreshPeriods();

    const portfolio = document.querySelector('[data-consolidated-portfolio]');
    const project = document.querySelector('[data-consolidated-project]');
    const projectHelp = document.getElementById('consolidated-project-help');
    const projectPlaceholder = project?.querySelector('option[value=""]');
    const projectOptions = Array.from(project?.querySelectorAll('option[data-portfolio-id]') || []);
    const refreshProjects = function (clearInvalid = false) {
        if (!portfolio || !project || !projectPlaceholder) return;
        const portfolioId = portfolio.value;
        let available = 0;
        let selectedIsAvailable = project.value === '';

        projectOptions.forEach(option => {
            const matches = !portfolioId || option.dataset.portfolioId === portfolioId;
            option.hidden = !matches;
            option.disabled = !matches;
            if (matches) available++;
            if (matches && option.value === project.value) selectedIsAvailable = true;
        });

        if (clearInvalid && !selectedIsAvailable) project.value = '';
        project.disabled = Boolean(portfolioId && available === 0);
        projectPlaceholder.textContent = !portfolioId
            ? 'All report-bearing projects'
            : (available > 0 ? 'All projects in selected portfolio' : 'No report-bearing projects available');
        if (projectHelp) {
            projectHelp.textContent = !portfolioId
                ? 'Choose a portfolio to narrow this list to its projects.'
                : (available > 0
                    ? 'Only projects belonging to the selected portfolio are shown.'
                    : 'No report-bearing projects are available for this portfolio.');
        }
    };
    portfolio?.addEventListener('change', () => refreshProjects(true));
    refreshProjects();

    const organizationRows = Array.from(document.querySelectorAll('[data-org-row]'));
    const organizationSearch = document.getElementById('organization-search');
    const organizationStage = document.getElementById('organization-stage');
    const applyOrganizationSearch = function () {
        const term = (organizationSearch?.value || '').trim().toLowerCase();
        const stage = organizationStage?.value || '';
        let visible = 0;
        organizationRows.forEach(row => {
            const matches = (!term || row.dataset.search.includes(term)) && (!stage || row.dataset.status.split(' ').includes(stage));
            row.hidden = !matches;
            if (matches) visible++;
        });
        const counter = document.getElementById('organization-visible-count');
        if (counter) counter.textContent = visible;
        const empty = document.getElementById('organization-no-match');
        if (empty) empty.hidden = visible !== 0;
    };
    organizationSearch?.addEventListener('input', applyOrganizationSearch);
    organizationStage?.addEventListener('change', applyOrganizationSearch);

    const indicatorRows = Array.from(document.querySelectorAll('[data-indicator-row]'));
    const indicatorSearch = document.getElementById('indicator-search');
    const applyIndicatorSearch = function () {
        const term = (indicatorSearch?.value || '').trim().toLowerCase();
        let visible = 0;
        indicatorRows.forEach(row => { const matches = !term || row.dataset.search.includes(term); row.hidden = !matches; if (matches) visible++; });
        const counter = document.getElementById('indicator-visible-count');
        if (counter) counter.textContent = visible;
        const empty = document.getElementById('indicator-no-match');
        if (empty) empty.hidden = visible !== 0;
    };
    indicatorSearch?.addEventListener('input', applyIndicatorSearch);

    const stages = {{ \Illuminate\Support\Js::from($stageDistribution->values()->all()) }};
    const organizations = {{ \Illuminate\Support\Js::from($organizationRows->map(fn($item)=>['name'=>$item['think_tank']->name,'count'=>$item['report_count']])->values()->all()) }};
    const indicators = {{ \Illuminate\Support\Js::from($consolidated->map(fn($row)=>['code'=>$row['indicator']?->indicator_code ?: 'Uncoded','count'=>$row['organization_count']])->take(12)->values()->all()) }};
    const gender = {{ \Illuminate\Support\Js::from($genderTotals->values()->all()) }};
    const age = {{ \Illuminate\Support\Js::from($ageTotals->values()->all()) }};
    const base = { chart:{fontFamily:'Inter, Arial, sans-serif',foreColor:'#657980',toolbar:{show:false},animations:{speed:420}},grid:{borderColor:'#e5edef',strokeDashArray:3},dataLabels:{style:{fontSize:'11px',fontWeight:700}},tooltip:{theme:'light'},legend:{fontSize:'11px',fontWeight:600} };
    const render = function (selector, options) { const target=document.querySelector(selector); if(target && window.ApexCharts) new ApexCharts(target,options).render(); };
    render('#consolidated-stage-chart',{...base,chart:{...base.chart,type:'donut',height:310,events:{dataPointSelection:(_e,_c,selection)=>{const item=stages[selection.dataPointIndex];if(item&&organizationStage){organizationStage.value=item.key;applyOrganizationSearch();document.getElementById('organization-register')?.scrollIntoView({behavior:'smooth'});}}},series:stages.map(item=>item.count),labels:stages.map(item=>item.label),colors:stages.map(item=>item.color),stroke:{colors:['#fff'],width:3},plotOptions:{pie:{donut:{size:'67%',labels:{show:true,total:{show:true,label:'Reports',formatter:()=>stages.reduce((sum,item)=>sum+item.count,0)}}}}},legend:{position:'bottom'},dataLabels:{enabled:false}});
    render('#consolidated-organization-chart',{...base,chart:{...base.chart,type:'bar',height:Math.max(310,organizations.length*32),events:{dataPointSelection:(_e,_c,selection)=>{const item=organizations[selection.dataPointIndex];if(item&&organizationSearch){organizationSearch.value=item.name;applyOrganizationSearch();document.getElementById('organization-register')?.scrollIntoView({behavior:'smooth'});}}},series:[{name:'Reports',data:organizations.map(item=>item.count)}],colors:['#3f8aa0'],plotOptions:{bar:{horizontal:true,borderRadius:4,barHeight:'58%'}},xaxis:{categories:organizations.map(item=>item.name),min:0,forceNiceScale:true,labels:{formatter:value=>Math.round(value)}},dataLabels:{enabled:true,formatter:value=>Math.round(value)},legend:{show:false}});
    render('#consolidated-indicator-chart',{...base,chart:{...base.chart,type:'bar',height:310,events:{dataPointSelection:(_e,_c,selection)=>{const item=indicators[selection.dataPointIndex];if(item&&indicatorSearch){indicatorSearch.value=item.code;applyIndicatorSearch();document.getElementById('consolidated-indicators')?.scrollIntoView({behavior:'smooth'});}}},series:[{name:'Organizations',data:indicators.map(item=>item.count)}],colors:['#6b63a8'],plotOptions:{bar:{borderRadius:4,columnWidth:'52%'}},xaxis:{categories:indicators.map(item=>item.code),labels:{rotate:-35,style:{fontSize:'11px'}}},yaxis:{min:0,forceNiceScale:true,labels:{formatter:value=>Math.round(value)}},dataLabels:{enabled:true,formatter:value=>Math.round(value)},legend:{show:false}});
    const beneficiaryDonut = (selector,series,labels,colors) => render(selector,{...base,chart:{...base.chart,type:'donut',height:245},series,labels,colors,stroke:{colors:['#fbfcfc'],width:3},plotOptions:{pie:{donut:{size:'62%',labels:{show:true,total:{show:true,label:'People',formatter:()=>series.reduce((sum,value)=>sum+value,0).toLocaleString()}}}}},legend:{position:'bottom'},dataLabels:{enabled:false}});
    beneficiaryDonut('#consolidated-gender-chart',gender,['Female','Male','Not disaggregated'],['#6b63a8','#075c7a','#94a3b8']);
    beneficiaryDonut('#consolidated-age-chart',age,['Youth below 35','Adults 35+','Not disaggregated'],['#0e7490','#187459','#94a3b8']);
});
</script>
@endpush
