@extends('layouts.app')

@section('title', 'Data Quality and Approval Workflow')
@section('lean_admin_scripts', '1')

@push('styles')
    @include('me.data-quality.partials.styles')
@endpush

@section('content')
@php
    $baseTabQuery = request()->except(['tab', 'findings_page', 'pipeline_page']);
    $findingTabQuery = array_merge($baseTabQuery, ['tab' => 'findings', 'finding_status' => request('finding_status', 'open')]);
    $pipelineTabQuery = array_merge(request()->except(['tab', 'findings_page', 'pipeline_page', 'finding_status']), ['tab' => 'pipeline']);
    $rulesTabQuery = array_filter(['tab' => 'rules', 'portfolio_id' => $filters['portfolio_id'], 'think_tank_id' => $filters['think_tank_id'], 'reporting_period_id' => $filters['reporting_period_id'], 'reporting_year' => $filters['reporting_year']]);
    $ruleCounts = $ruleSummary->groupBy('rule_code')->map(fn ($rows) => (int) $rows->sum('total'));
    $maxRuleTotal = max(1, (int) $ruleCounts->max());
    $currentFindingTotal = $findings->total();
    $currentPipelineTotal = $pipeline->total();
@endphp
<div class="dq-workspace">
    <header class="dq-header">
        <div>
            <span class="dq-eyebrow">Monitoring, Evaluation and Learning</span>
            <h1>Data quality and approval workspace</h1>
            <p>Run repeatable quality checks, investigate exceptions, document resolutions and move eligible Think Tank submissions through independent verification and final approval.</p>
        </div>
        <div class="dq-header-actions">
            @if($canOpenReviewQueue)<a class="dq-header-link" href="{{ route('budget.me.submission-reviews.index') }}">Open review queue</a>@endif
            @if($canViewNotifications)<a class="dq-header-link" href="{{ route('budget.me.reporting-notifications.index') }}">Reporting notifications</a>@endif
        </div>
    </header>

    @if(session('success'))<div class="alert alert-success dq-alert" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger dq-alert" role="alert">
            <strong>The requested data-quality operation was not completed.</strong>
            <ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="dq-metrics" aria-label="Data-quality and approval summary">
        <article class="dq-metric" style="--metric:#ad3e3b"><span class="dq-metric-label">Blocking errors</span><strong>{{ number_format($metrics['open_errors']) }}</strong><small>Must be resolved before final approval</small></article>
        <article class="dq-metric" style="--metric:#a26816"><span class="dq-metric-label">Open warnings</span><strong>{{ number_format($metrics['open_warnings']) }}</strong><small>Require documented reviewer attention</small></article>
        <article class="dq-metric"><span class="dq-metric-label">Affected submissions</span><strong>{{ number_format($metrics['affected_submissions']) }}</strong><small>Submissions with at least one open finding</small></article>
        <article class="dq-metric" style="--metric:#26728a"><span class="dq-metric-label">Ready for review</span><strong>{{ number_format($metrics['ready_for_review']) }}</strong><small>Submitted without blocking DQA errors</small></article>
        <article class="dq-metric" style="--metric:#7863a7"><span class="dq-metric-label">Awaiting approval</span><strong>{{ number_format($metrics['awaiting_approval']) }}</strong><small>Verified records awaiting final decision</small></article>
        <article class="dq-metric" style="--metric:#177258"><span class="dq-metric-label">DQA coverage</span><strong>{{ number_format($metrics['coverage'], 1) }}%</strong><small>{{ number_format($metrics['evaluated']) }} of {{ number_format($metrics['submitted']) }} submitted records checked</small></article>
    </section>

    <section class="dq-flow" aria-label="Controlled approval workflow">
        <div class="dq-flow-step" data-step="1"><strong>Automated DQA</strong>Rules screen values, dates, evidence and disaggregation.</div>
        <div class="dq-flow-step" data-step="2"><strong>Reviewer investigation</strong>Errors and warnings are checked against source evidence.</div>
        <div class="dq-flow-step" data-step="3"><strong>Independent verification</strong>An authorised reviewer verifies the corrected submission.</div>
        <div class="dq-flow-step" data-step="4"><strong>Final approval</strong>Only verified records without blocking errors can be approved.</div>
    </section>

    <details class="dq-panel" @if($activeFilterCount > 0) open @endif>
        <summary class="dq-panel-head dq-filter-summary">
            <div><h2>Scope and filter the workspace</h2><p>Metrics follow the selected organisational scope; registers also apply status and rule filters.</p></div>
            <span class="dq-badge">{{ $activeFilterCount }} active {{ str('filter')->plural($activeFilterCount) }}</span>
        </summary>
        <div class="dq-panel-body">
            <form class="dq-filter-grid" method="GET" action="{{ route('budget.me.rebuild.data-quality') }}">
                <input type="hidden" name="tab" value="{{ $filters['tab'] }}">
                <div class="dq-field dq-filter-wide"><label for="dq-search">Search findings and submissions</label><input id="dq-search" class="form-control" name="q" value="{{ $filters['q'] }}" placeholder="Think Tank, form, indicator, rule, field or message"></div>
                <div class="dq-field"><label for="dq-portfolio">Portfolio</label><select id="dq-portfolio" class="form-select" name="portfolio_id"><option value="">All portfolios</option>@foreach($portfolios as $portfolio)<option value="{{ $portfolio->id }}" @selected($filters['portfolio_id'] === (string) $portfolio->id)>{{ $portfolio->name }}</option>@endforeach</select></div>
                <div class="dq-field"><label for="dq-think-tank">Think Tank</label><select id="dq-think-tank" class="form-select" name="think_tank_id"><option value="">All Think Tanks</option>@foreach($thinkTanks as $tank)<option value="{{ $tank->id }}" @selected($filters['think_tank_id'] === (string) $tank->id)>{{ $tank->name }}</option>@endforeach</select></div>
                <div class="dq-field"><label for="dq-year">Reporting year</label><input id="dq-year" class="form-control" type="number" min="2000" max="2200" name="reporting_year" value="{{ $filters['reporting_year'] }}" placeholder="For example, 2026"></div>
                <div class="dq-field"><label for="dq-period">Reporting period</label><select id="dq-period" class="form-select" name="reporting_period_id"><option value="">All reporting periods</option>@foreach($periods as $period)<option value="{{ $period->id }}" @selected($filters['reporting_period_id'] === (string) $period->id)>{{ $period->label }}{{ $period->reporting_year ? ' · '.$period->reporting_year : '' }}</option>@endforeach</select></div>
                <div class="dq-field"><label for="dq-severity">Severity</label><select id="dq-severity" class="form-select" name="severity"><option value="">Errors and warnings</option><option value="error" @selected($filters['severity'] === 'error')>Blocking errors</option><option value="warning" @selected($filters['severity'] === 'warning')>Warnings</option></select></div>
                <div class="dq-field"><label for="dq-finding-status">Finding status</label><select id="dq-finding-status" class="form-select" name="finding_status"><option value="">All finding statuses</option>@foreach($findingStatuses as $key=>$label)<option value="{{ $key }}" @selected($filters['finding_status'] === $key)>{{ $label }}</option>@endforeach</select></div>
                <div class="dq-field"><label for="dq-workflow-status">Submission stage</label><select id="dq-workflow-status" class="form-select" name="workflow_status"><option value="">All workflow stages</option>@foreach($workflowLabels as $key=>$label)<option value="{{ $key }}" @selected($filters['workflow_status'] === $key)>{{ $label }}</option>@endforeach</select></div>
                <div class="dq-field"><label for="dq-rule">Quality rule</label><select id="dq-rule" class="form-select" name="rule"><option value="">All quality rules</option>@foreach($ruleCatalogue as $key=>$rule)<option value="{{ $key }}" @selected($filters['rule'] === $key)>{{ $rule['label'] }}</option>@endforeach</select></div>
                <div class="dq-field"><label for="dq-sort">Sort order</label><select id="dq-sort" class="form-select" name="sort"><option value="severity" @selected($filters['sort'] === 'severity')>Blocking and oldest first</option><option value="newest" @selected($filters['sort'] === 'newest')>Newest first</option><option value="oldest" @selected($filters['sort'] === 'oldest')>Oldest first</option><option value="aging" @selected($filters['sort'] === 'aging')>Oldest open first</option><option value="dqa" @selected($filters['sort'] === 'dqa')>Highest DQA count first</option></select></div>
                <div class="dq-field"><label for="dq-page-size">Rows per page</label><select id="dq-page-size" class="form-select" name="per_page">@foreach([10,20,50,100] as $size)<option value="{{ $size }}" @selected($filters['per_page'] === $size)>{{ $size }} rows</option>@endforeach</select></div>
                <div class="dq-filter-actions"><a class="dq-btn dq-btn-secondary" href="{{ route('budget.me.rebuild.data-quality', ['tab'=>$filters['tab']]) }}">Clear filters</a><button class="dq-btn dq-btn-primary" type="submit">Apply filters</button></div>
            </form>
        </div>
    </details>

    <nav class="dq-tabs" aria-label="Data-quality workspace sections">
        <a class="dq-tab {{ $filters['tab'] === 'findings' ? 'active' : '' }}" href="{{ route('budget.me.rebuild.data-quality', $findingTabQuery) }}"><span class="dq-tab-count">{{ number_format($metrics['open_errors'] + $metrics['open_warnings']) }}</span><span><strong>Finding register</strong><small>Investigate and resolve exceptions</small></span></a>
        <a class="dq-tab {{ $filters['tab'] === 'pipeline' ? 'active' : '' }}" href="{{ route('budget.me.rebuild.data-quality', $pipelineTabQuery) }}"><span class="dq-tab-count">{{ number_format($metrics['submitted']) }}</span><span><strong>Approval pipeline</strong><small>Evaluate and progress submissions</small></span></a>
        <a class="dq-tab {{ $filters['tab'] === 'rules' ? 'active' : '' }}" href="{{ route('budget.me.rebuild.data-quality', $rulesTabQuery) }}"><span class="dq-tab-count">{{ count($ruleCatalogue) }}</span><span><strong>Rule catalogue</strong><small>Understand checks and corrections</small></span></a>
    </nav>

    @if($filters['tab'] === 'findings')
        <div class="dq-insights">
            <section class="dq-panel">
                <div class="dq-panel-head"><div><h3>Open-finding age</h3><p>Older exceptions should be prioritised.</p></div></div>
                <div class="dq-panel-body dq-aging">
                    <div class="dq-age"><small>0-2 days</small><strong>{{ number_format($aging['new']) }}</strong></div>
                    <div class="dq-age"><small>3-7 days</small><strong>{{ number_format($aging['attention']) }}</strong></div>
                    <div class="dq-age"><small>Over 7 days</small><strong class="text-danger">{{ number_format($aging['overdue']) }}</strong></div>
                </div>
            </section>
            <section class="dq-panel">
                <div class="dq-panel-head"><div><h3>Current exception concentration</h3><p>Open findings grouped by automated rule.</p></div><span class="dq-badge">{{ $lastEvaluationAt ? 'Last run '.\Illuminate\Support\Carbon::parse($lastEvaluationAt)->diffForHumans() : 'No run recorded' }}</span></div>
                <div class="dq-panel-body dq-rule-bars">
                    @forelse($ruleSummary->take(5) as $row)
                        @php $ruleInfo=$ruleCatalogue[$row->rule_code]??['label'=>str($row->rule_code)->headline()]; @endphp
                        <div class="dq-rule-bar-row"><span title="{{ $ruleInfo['label'] }}">{{ str($ruleInfo['label'])->limit(28) }}</span><div class="dq-bar"><span class="{{ $row->severity }}" style="width:{{ max(5,round(((int)$row->total/$maxRuleTotal)*100)) }}%"></span></div><strong>{{ $row->total }}</strong></div>
                    @empty<div class="text-muted small">No open findings exist in this scope.</div>@endforelse
                </div>
            </section>
        </div>

        <section class="dq-panel">
            <div class="dq-table-toolbar"><div><strong>Finding register</strong> &middot; @if($currentFindingTotal)Showing {{ number_format($findings->firstItem()) }}-{{ number_format($findings->lastItem()) }} of {{ number_format($currentFindingTotal) }}@else No matching findings @endif</div><span>Scroll horizontally for full context and resolution controls</span></div>
            <div class="dq-table-scroll" tabindex="0" role="region" aria-label="Scrollable data-quality findings register">
                <table class="dq-table">
                    <thead><tr><th style="width:285px">Finding</th><th style="width:255px">Think Tank / form</th><th style="width:225px">Indicator / field</th><th style="width:145px">Workflow</th><th style="width:145px">Age / ownership</th><th style="width:300px">Resolution / action</th></tr></thead>
                    <tbody>
                    @forelse($findings as $finding)
                        @php $submission=$finding->submission; $status=$submission?->effectiveStatus(); $rule=$ruleCatalogue[$finding->rule_code]??['label'=>str($finding->rule_code)->headline(),'guidance'=>'Review the submitted value and supporting evidence.']; $age=$finding->created_at?->diffInDays(now())??0; @endphp
                        <tr>
                            <td><div class="d-flex gap-1 flex-wrap"><span class="dq-badge {{ $finding->severity }}">{{ $finding->severity === 'error' ? 'Blocking error' : 'Warning' }}</span><span class="dq-badge {{ $finding->status }}">{{ $findingStatuses[$finding->status] ?? str($finding->status)->headline() }}</span></div><span class="dq-cell-title mt-2" title="{{ $rule['label'] }}">{{ $rule['label'] }}</span><div class="dq-message">{{ $finding->message }}</div><span class="dq-cell-meta">Rule: {{ $finding->rule_code }}</span></td>
                            <td><span class="dq-cell-title">{{ $submission?->assignment?->thinkTank?->name ?: 'Unknown Think Tank' }}</span><span class="dq-cell-meta">{{ $submission?->assignment?->collection?->form?->code ?: 'No form code' }} &middot; {{ $submission?->assignment?->collection?->form?->title ?: 'Form unavailable' }}<br>{{ $submission?->assignment?->collection?->form?->portfolio?->name ?: 'No portfolio' }}</span></td>
                            <td><span class="dq-cell-title">{{ $finding->indicatorResult?->indicator?->indicator_code ?: 'Submission-level check' }}</span><span class="dq-cell-meta">{{ $finding->indicatorResult?->indicator?->name ?: $rule['guidance'] }}@if($finding->field_key)<br>Field: {{ str($finding->field_key)->headline() }}@endif</span></td>
                            <td><span class="dq-badge {{ str($status)->replace('_','-') }}">{{ $workflowLabels[$status] ?? str($status)->headline() }}</span><span class="dq-cell-meta">Version {{ $submission?->current_version ?: 1 }}<br>{{ $submission?->assignment?->collection?->reportingPeriod?->label ?: 'No reporting period' }}</span></td>
                            <td><span class="dq-cell-title">{{ number_format($age) }} {{ str('day')->plural($age) }}</span><span class="dq-cell-meta">Raised {{ $finding->created_at?->format('d M Y, H:i') ?: 'Unknown' }}@if($finding->resolved_at)<br>Closed {{ $finding->resolved_at->format('d M Y, H:i') }} by {{ $finding->resolvedBy?->name ?: 'System' }}@endif</span></td>
                            <td>
                                @if($finding->status === 'open' && $canManageDqa)
                                    <details class="dq-resolution"><summary>Document resolution</summary><form method="POST" action="{{ route('budget.me.submission-reviews.dqa.resolve', [$submission,$finding]) }}">@csrf<textarea class="form-control" name="resolution_notes" maxlength="5000" placeholder="Explain the source checked, correction made or reviewer justification" required></textarea><button class="dq-btn dq-btn-primary w-100 mt-2" type="submit">Resolve finding</button></form></details>
                                @elseif($finding->status !== 'open')
                                    <div class="dq-message">{{ $finding->resolution_notes ?: 'No closure note recorded.' }}</div>
                                @else
                                    <span class="dq-cell-meta">You have view-only access to DQA resolution.</span>
                                @endif
                                @if($submission && $canOpenReviewQueue)<a class="dq-btn dq-btn-secondary mt-2" href="{{ route('budget.me.submission-reviews.show',$submission) }}">Open full review</a>@endif
                            </td>
                        </tr>
                    @empty<tr><td colspan="6" class="dq-empty"><strong>No findings match this view</strong><span>Adjust the filters, inspect another status, or run DQA checks from the approval pipeline.</span></td></tr>@endforelse
                    </tbody>
                </table>
            </div>
            @if($findings->hasPages())<div class="dq-pagination">{{ $findings->links() }}</div>@endif
        </section>
    @elseif($filters['tab'] === 'pipeline')
        <section class="dq-panel">
            <div class="dq-note m-3"><strong>Operational control:</strong> rerunning DQA supersedes the previous open rule results, creates a fresh current set and writes an immutable <em>DQA evaluated</em> event to the submission history. Approved, returned, rejected and draft records cannot be rerun from this workspace.</div>
            <form id="dq-bulk-form" method="POST" action="{{ route('budget.me.rebuild.data-quality.evaluate-selected') }}">@csrf</form>
            <div class="dq-table-toolbar"><div><strong>Approval pipeline</strong> &middot; @if($currentPipelineTotal)Showing {{ number_format($pipeline->firstItem()) }}-{{ number_format($pipeline->lastItem()) }} of {{ number_format($currentPipelineTotal) }}@else No matching submissions @endif</div><div class="dq-toolbar-actions">@if($canManageDqa)<label class="d-flex align-items-center gap-2"><input id="dq-select-all" type="checkbox"> Select eligible rows</label><span id="dq-selected-count" class="dq-badge superseded">0 selected</span><button id="dq-bulk-run" class="dq-btn dq-btn-primary" type="submit" form="dq-bulk-form" disabled>Run DQA checks</button>@endif</div></div>
            <div class="dq-table-scroll" tabindex="0" role="region" aria-label="Scrollable approval pipeline table">
                <table class="dq-table">
                    <thead><tr>@if($canManageDqa)<th style="width:40px">Select</th>@endif<th style="width:270px">Submission</th><th style="width:190px">Period / portfolio</th><th style="width:180px">Payload</th><th style="width:175px">DQA status</th><th style="width:150px">Workflow</th><th style="width:230px">Action</th></tr></thead>
                    <tbody>
                    @forelse($pipeline as $submission)
                        @php $status=$submission->effectiveStatus(); $eligible=in_array($status,$eligibleStatuses,true); $lastDqa=$submission->last_dqa_at?\Illuminate\Support\Carbon::parse($submission->last_dqa_at):null; @endphp
                        <tr>
                            @if($canManageDqa)<td>@if($eligible)<input class="dq-row-check" type="checkbox" name="submission_ids[]" value="{{ $submission->id }}" form="dq-bulk-form" aria-label="Select {{ $submission->assignment?->thinkTank?->name }}">@else<span class="text-muted">&mdash;</span>@endif</td>@endif
                            <td><span class="dq-cell-title">{{ $submission->assignment?->thinkTank?->name ?: 'Unknown Think Tank' }}</span><span class="dq-cell-meta">{{ $submission->assignment?->collection?->form?->code ?: 'No form code' }} &middot; Version {{ $submission->current_version ?: 1 }}<br>Submitted {{ $submission->submitted_at?->format('d M Y, H:i') ?: 'Not submitted' }} by {{ $submission->submittedBy?->name ?: 'Unknown' }}</span></td>
                            <td><span class="dq-cell-title">{{ $submission->assignment?->collection?->reportingPeriod?->label ?: 'No period' }}</span><span class="dq-cell-meta">{{ $submission->assignment?->collection?->form?->portfolio?->name ?: 'No portfolio' }}<br>{{ $submission->assignment?->collection?->reportingPeriod?->reporting_year ?: 'Year not set' }}</span></td>
                            <td><div class="dq-counts"><span class="dq-count">{{ $submission->indicator_results_count }} results</span><span class="dq-count">{{ $submission->evidence_count }} evidence</span></div><span class="dq-cell-meta">{{ $submission->assignment?->collection?->form?->title ?: 'Data-entry form' }}</span></td>
                            <td>@if($submission->blocking_dqa_count)<span class="dq-badge error">{{ $submission->blocking_dqa_count }} blocking</span>@elseif($submission->warning_dqa_count)<span class="dq-badge warning">{{ $submission->warning_dqa_count }} warnings</span>@else<span class="dq-badge resolved">No open finding</span>@endif<span class="dq-cell-meta">{{ $submission->open_dqa_count }} total open<br>{{ $lastDqa ? 'Last checked '.$lastDqa->diffForHumans() : 'No audited DQA run' }}</span></td>
                            <td><span class="dq-badge {{ str($status)->replace('_','-') }}">{{ $workflowLabels[$status] ?? str($status)->headline() }}</span>@if(in_array($status,[\App\Models\MeDataSubmission::STATUS_VERIFIED,\App\Models\MeDataSubmission::STATUS_VALIDATED],true) && !$submission->blocking_dqa_count)<span class="dq-cell-meta text-success">Ready for approval</span>@elseif($submission->blocking_dqa_count)<span class="dq-cell-meta text-danger">Approval blocked</span>@endif</td>
                            <td><div class="d-flex flex-wrap gap-1">@if($canOpenReviewQueue)<a class="dq-btn dq-btn-secondary" href="{{ route('budget.me.submission-reviews.show',$submission) }}">Open review</a>@endif @if($canManageDqa && $eligible)<form method="POST" action="{{ route('budget.me.rebuild.data-quality.evaluate',$submission) }}">@csrf<button class="dq-btn dq-btn-primary" type="submit">Run DQA</button></form>@endif</div>@unless($eligible)<span class="dq-cell-meta">DQA rerun is locked at this workflow stage.</span>@endunless</td>
                        </tr>
                    @empty<tr><td colspan="{{ $canManageDqa ? 7 : 6 }}" class="dq-empty"><strong>No submissions match this pipeline view</strong><span>Clear finding filters or select another workflow stage.</span></td></tr>@endforelse
                    </tbody>
                </table>
            </div>
            @if($pipeline->hasPages())<div class="dq-pagination">{{ $pipeline->links() }}</div>@endif
        </section>
    @else
        <div class="dq-note mb-3"><strong>How decisions work:</strong> blocking errors prevent final approval. Warnings do not automatically block approval, but the reviewer must investigate them and retain a clear resolution or justification. Rerunning DQA never deletes history; the earlier current set is marked superseded.</div>
        <section class="dq-rule-grid">
            @foreach($ruleCatalogue as $code=>$rule)
                @php $currentCount=(int)($ruleCounts[$code]??0); @endphp
                <article class="dq-rule-card"><div class="dq-rule-card-head"><div><h3>{{ $rule['label'] }}</h3><span class="dq-rule-code">{{ $code }}</span></div><span class="dq-badge {{ $rule['severity'] }}">{{ $rule['severity']==='error'?'Blocking error':'Warning' }}</span></div><p>{{ $rule['guidance'] }}</p><footer>{{ number_format($currentCount) }} currently open &middot; <a href="{{ route('budget.me.rebuild.data-quality', array_merge($rulesTabQuery,['tab'=>'findings','rule'=>$code,'finding_status'=>'open'])) }}">View affected records</a></footer></article>
            @endforeach
        </section>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.getElementById('dq-select-all');
    const checks = Array.from(document.querySelectorAll('.dq-row-check'));
    const counter = document.getElementById('dq-selected-count');
    const runButton = document.getElementById('dq-bulk-run');
    if (!selectAll || !counter || !runButton) return;
    function refresh() {
        const selected = checks.filter(function (item) { return item.checked; }).length;
        counter.textContent = selected + ' selected';
        runButton.disabled = selected === 0;
        selectAll.checked = checks.length > 0 && selected === checks.length;
        selectAll.indeterminate = selected > 0 && selected < checks.length;
    }
    selectAll.addEventListener('change', function () { checks.forEach(function (item) { item.checked = selectAll.checked; }); refresh(); });
    checks.forEach(function (item) { item.addEventListener('change', refresh); });
    refresh();
});
</script>
@endpush
