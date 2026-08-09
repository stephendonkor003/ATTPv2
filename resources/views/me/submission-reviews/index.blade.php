@extends('layouts.app')

@section('title', 'M&E Submission Reviews')

@push('styles')
    @include('me.submission-reviews.partials.styles')
@endpush

@section('content')
@php
    $allCount = collect($summary)->sum();
    $stageQuery = request()->except(['page', 'status']);
@endphp
<div class="mel-review-shell">
    <header class="mel-page-header">
        <div>
            <span class="mel-eyebrow">Monitoring, Evaluation and Learning</span>
            <h1>Submission review queue</h1>
            <p>Screen Think Tank submissions, inspect reported results and evidence, resolve data-quality findings, and record controlled review decisions from one workbench.</p>
        </div>
        <div class="mel-header-actions">
            <a class="mel-header-button" href="{{ route('budget.me.rebuild.data-entry', ['tab' => 'submissions']) }}">Data entry register</a>
            @if(Route::has('budget.me.results-dashboard.index'))
                <a class="mel-header-button" href="{{ route('budget.me.results-dashboard.index') }}">M&amp;E dashboard</a>
            @endif
        </div>
    </header>

    @if(session('success'))
        <div class="alert alert-success mel-alert" role="status">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger mel-alert" role="alert">
            <strong>The review queue could not be loaded with those inputs.</strong>
            <ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="mel-metrics" aria-label="Review queue summary">
        <article class="mel-metric" style="--metric-color:#ba7b18">
            <span class="mel-metric-label">Awaiting review</span>
            <strong class="mel-metric-value">{{ number_format($reviewMetrics['awaiting']) }}</strong>
            <span class="mel-metric-help">Submitted and resubmitted cases</span>
        </article>
        <article class="mel-metric">
            <span class="mel-metric-label">In review</span>
            <strong class="mel-metric-value">{{ number_format($reviewMetrics['in_review']) }}</strong>
            <span class="mel-metric-help">Cases actively being assessed</span>
        </article>
        <article class="mel-metric" style="--metric-color:#b33b39">
            <span class="mel-metric-label">Blocking DQA</span>
            <strong class="mel-metric-value">{{ number_format($reviewMetrics['blocking_dqa']) }}</strong>
            <span class="mel-metric-help">Submissions requiring resolution</span>
        </article>
        <article class="mel-metric" style="--metric-color:#18765a">
            <span class="mel-metric-label">Approved</span>
            <strong class="mel-metric-value">{{ number_format($reviewMetrics['approved']) }}</strong>
            <span class="mel-metric-help">Finalised submissions in scope</span>
        </article>
    </section>

    <nav class="mel-stage-scroller" aria-label="Filter by workflow stage">
        <div class="mel-stages">
            <a class="mel-stage {{ $statusFilter === '' ? 'active' : '' }}" href="{{ route('budget.me.submission-reviews.index', $stageQuery) }}">
                <span class="mel-stage-count">{{ number_format($allCount) }}</span>
                <span class="mel-stage-name">All stages</span>
            </a>
            @foreach($statuses as $item)
                <a class="mel-stage {{ $statusFilter === $item ? 'active' : '' }}" href="{{ route('budget.me.submission-reviews.index', array_merge($stageQuery, ['status' => $item])) }}">
                    <span class="mel-stage-count">{{ number_format($summary[$item] ?? 0) }}</span>
                    <span class="mel-stage-name">{{ $statusLabels[$item] ?? str($item)->headline() }}</span>
                </a>
            @endforeach
        </div>
    </nav>

    <details class="mel-panel" @if($activeFilterCount > 0) open @endif>
        <summary class="mel-panel-header mel-filter-summary">
            <div>
                <h2>Find and organise submissions</h2>
                <p>Use one or more filters. Your selected workflow stage remains active.</p>
            </div>
            <span class="mel-badge">{{ $activeFilterCount }} active {{ str('filter')->plural($activeFilterCount) }}</span>
        </summary>
        <div class="mel-panel-body">
            <form method="GET" class="mel-filter-grid" action="{{ route('budget.me.submission-reviews.index') }}">
                @if($statusFilter !== '')<input type="hidden" name="status" value="{{ $statusFilter }}">@endif
                <div class="mel-field mel-filter-wide">
                    <label for="review-search">Search submissions</label>
                    <input id="review-search" class="form-control" name="q" value="{{ $search }}" placeholder="Think Tank, form, indicator, submitter, status or notes">
                </div>
                <div class="mel-field">
                    <label for="review-portfolio">Portfolio</label>
                    <select id="review-portfolio" class="form-select" name="portfolio_id">
                        <option value="">All portfolios</option>
                        @foreach($portfolios as $portfolio)<option value="{{ $portfolio->id }}" @selected($portfolioId === (string) $portfolio->id)>{{ $portfolio->name }}</option>@endforeach
                    </select>
                </div>
                <div class="mel-field">
                    <label for="review-think-tank">Think Tank</label>
                    <select id="review-think-tank" class="form-select" name="think_tank_id">
                        <option value="">All Think Tanks</option>
                        @foreach($thinkTanks as $tank)<option value="{{ $tank->id }}" @selected((string) request('think_tank_id') === (string) $tank->id)>{{ $tank->name }}</option>@endforeach
                    </select>
                </div>
                <div class="mel-field">
                    <label for="review-year">Reporting year</label>
                    <input id="review-year" class="form-control" type="number" min="2000" max="2200" name="reporting_year" value="{{ request('reporting_year') }}" placeholder="For example, 2026">
                </div>
                <div class="mel-field">
                    <label for="review-period">Reporting period</label>
                    <select id="review-period" class="form-select" name="reporting_period_id">
                        <option value="">All reporting periods</option>
                        @foreach($periods as $period)<option value="{{ $period->id }}" @selected((string) request('reporting_period_id') === (string) $period->id)>{{ $period->label }} ({{ $period->reporting_year }})</option>@endforeach
                    </select>
                </div>
                <div class="mel-field">
                    <label for="review-component">Project component</label>
                    <select id="review-component" class="form-select" name="component_id">
                        <option value="">All components</option>
                        @foreach($components as $component)<option value="{{ $component->id }}" @selected((string) request('component_id') === (string) $component->id)>{{ $component->project_id }} - {{ $component->name }}</option>@endforeach
                    </select>
                </div>
                <div class="mel-field">
                    <label for="review-indicator">Indicator</label>
                    <select id="review-indicator" class="form-select" name="indicator_id">
                        <option value="">All indicators</option>
                        @foreach($indicators as $indicator)<option value="{{ $indicator->id }}" @selected((string) request('indicator_id') === (string) $indicator->id)>{{ $indicator->indicator_code }} - {{ $indicator->name }}</option>@endforeach
                    </select>
                </div>
                <div class="mel-field">
                    <label for="review-country">Country</label>
                    <select id="review-country" class="form-select" name="country">
                        <option value="">All countries</option>
                        @foreach($countries as $country)<option value="{{ $country }}" @selected(request('country') === $country)>{{ $country }}</option>@endforeach
                    </select>
                </div>
                <div class="mel-field">
                    <label for="review-reviewer">Last reviewer</label>
                    <select id="review-reviewer" class="form-select" name="reviewer_id">
                        <option value="">All reviewers</option>
                        @foreach($reviewers as $reviewer)<option value="{{ $reviewer->id }}" @selected((string) request('reviewer_id') === (string) $reviewer->id)>{{ $reviewer->name }}</option>@endforeach
                    </select>
                </div>
                <div class="mel-field">
                    <label for="review-dqa">Data quality</label>
                    <select id="review-dqa" class="form-select" name="dqa">
                        <option value="">Any DQA condition</option>
                        <option value="blocking" @selected($dqaFilter === 'blocking')>Blocking errors</option>
                        <option value="open" @selected($dqaFilter === 'open')>Any open finding</option>
                        <option value="clear" @selected($dqaFilter === 'clear')>No open findings</option>
                    </select>
                </div>
                <div class="mel-field">
                    <label for="review-evidence">Evidence</label>
                    <select id="review-evidence" class="form-select" name="evidence">
                        <option value="">With or without evidence</option>
                        <option value="with" @selected($evidenceFilter === 'with')>Has evidence</option>
                        <option value="without" @selected($evidenceFilter === 'without')>Missing evidence</option>
                    </select>
                </div>
                <div class="mel-field">
                    <label for="review-sort">Sort order</label>
                    <select id="review-sort" class="form-select" name="sort">
                        <option value="newest" @selected($sort === 'newest')>Newest submitted first</option>
                        <option value="oldest" @selected($sort === 'oldest')>Oldest submitted first</option>
                        <option value="dqa" @selected($sort === 'dqa')>Blocking DQA first</option>
                        <option value="recently_reviewed" @selected($sort === 'recently_reviewed')>Recently reviewed first</option>
                    </select>
                </div>
                <div class="mel-field">
                    <label for="review-page-size">Rows per page</label>
                    <select id="review-page-size" class="form-select" name="per_page">
                        @foreach([10,20,50,100] as $size)<option value="{{ $size }}" @selected($perPage === $size)>{{ $size }} rows</option>@endforeach
                    </select>
                </div>
                <div class="mel-filter-actions">
                    <a class="mel-btn mel-btn-secondary" href="{{ route('budget.me.submission-reviews.index', $statusFilter ? ['status' => $statusFilter] : []) }}">Clear filters</a>
                    <button class="mel-btn mel-btn-primary" type="submit">Apply filters</button>
                </div>
            </form>
        </div>
    </details>

    <section class="mel-panel mt-3" aria-labelledby="submission-register-title">
        <div class="mel-table-toolbar">
            <div><strong id="submission-register-title">Submission register</strong> &middot; @if($submissions->total()) Showing {{ number_format($submissions->firstItem()) }}-{{ number_format($submissions->lastItem()) }} of {{ number_format($submissions->total()) }} @else No matching records @endif</div>
            <span class="d-none d-md-inline">Scroll horizontally for all columns</span>
        </div>
        <div class="mel-table-scroll mel-desktop-table" tabindex="0" role="region" aria-label="Scrollable submission review table">
            <table class="mel-data-table">
                <thead>
                    <tr>
                        <th style="width:280px">Submission</th>
                        <th style="width:210px">Portfolio / component</th>
                        <th style="width:145px">Reporting period</th>
                        <th style="width:215px">Payload</th>
                        <th style="width:130px">Data quality</th>
                        <th style="width:130px">Stage</th>
                        <th style="width:190px">Review activity</th>
                        <th style="width:125px">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($submissions as $submission)
                        @php
                            $submissionStatus = $submission->effectiveStatus();
                            $form = $submission->assignment?->collection?->form;
                            $period = $submission->assignment?->collection?->reportingPeriod;
                        @endphp
                        <tr>
                            <td>
                                <span class="mel-cell-title" title="{{ $submission->assignment?->thinkTank?->name }}">{{ $submission->assignment?->thinkTank?->name ?: 'Unknown Think Tank' }}</span>
                                <span class="mel-cell-meta">{{ $form?->code ?: 'No form code' }} &middot; Version {{ $submission->current_version ?: 1 }}<br>{{ $submission->submittedBy?->name ?: 'Submitter unavailable' }}</span>
                            </td>
                            <td>
                                <span class="mel-cell-title" title="{{ $form?->portfolio?->name }}">{{ $form?->portfolio?->name ?: 'No portfolio' }}</span>
                                <span class="mel-cell-meta">{{ $form?->projectComponent?->project_id }}{{ $form?->projectComponent?->project_id ? ' - ' : '' }}{{ $form?->projectComponent?->name ?: 'No component' }}</span>
                            </td>
                            <td>
                                <span class="mel-cell-title">{{ $period?->label ?: 'Not assigned' }}</span>
                                <span class="mel-cell-meta">{{ $period?->reporting_year ?: 'Year not set' }}{{ $period?->submission_deadline ? ' · Due '.$period->submission_deadline->format('d M Y') : '' }}</span>
                            </td>
                            <td>
                                <div class="mel-counts">
                                    <span class="mel-count">{{ $submission->indicator_results_count }} results</span>
                                    <span class="mel-count">{{ $submission->answers_count }} answers</span>
                                    <span class="mel-count">{{ $submission->evidence_count }} evidence</span>
                                </div>
                                <span class="mel-cell-meta">{{ $form?->title ?: 'Data entry form' }}</span>
                            </td>
                            <td>
                                @if($submission->blocking_dqa_count)
                                    <span class="mel-badge danger">{{ $submission->blocking_dqa_count }} blocking</span>
                                    <span class="mel-cell-meta">{{ $submission->open_dqa_count }} total open</span>
                                @elseif($submission->open_dqa_count)
                                    <span class="mel-badge warning">{{ $submission->open_dqa_count }} open</span>
                                    <span class="mel-cell-meta">No blocking error</span>
                                @else
                                    <span class="mel-badge success">DQA clear</span>
                                    <span class="mel-cell-meta">No open finding</span>
                                @endif
                            </td>
                            <td><span class="mel-badge mel-status-{{ str($submissionStatus)->replace('_','-') }}">{{ $statusLabels[$submissionStatus] ?? str($submissionStatus)->headline() }}</span></td>
                            <td>
                                <span class="mel-cell-title">{{ $submission->reviewedBy?->name ?: 'Not reviewed' }}</span>
                                <span class="mel-cell-meta">
                                    @if($submission->reviewed_at)
                                        Updated {{ $submission->reviewed_at->format('d M Y, H:i') }}
                                    @elseif($submission->submitted_at)
                                        Submitted {{ $submission->submitted_at->format('d M Y, H:i') }}
                                    @else
                                        Not yet submitted
                                    @endif
                                </span>
                            </td>
                            <td><a class="mel-btn mel-btn-secondary" href="{{ route('budget.me.submission-reviews.show', $submission) }}">Open review</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="mel-empty"><strong>No submissions match this view</strong><span>Clear one or more filters, select another workflow stage, or return when new Think Tank submissions arrive.</span></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mel-mobile-list">
            @forelse($submissions as $submission)
                @php $submissionStatus=$submission->effectiveStatus(); $form=$submission->assignment?->collection?->form; $period=$submission->assignment?->collection?->reportingPeriod; @endphp
                <article class="mel-mobile-card">
                    <div class="mel-mobile-card-top">
                        <div><span class="mel-cell-title">{{ $submission->assignment?->thinkTank?->name ?: 'Unknown Think Tank' }}</span><span class="mel-cell-meta">{{ $form?->code ?: 'No form code' }} &middot; Version {{ $submission->current_version ?: 1 }}</span></div>
                        <span class="mel-badge mel-status-{{ str($submissionStatus)->replace('_','-') }}">{{ $statusLabels[$submissionStatus] ?? str($submissionStatus)->headline() }}</span>
                    </div>
                    <div class="mel-mobile-facts">
                        <div><small>Period</small><strong>{{ $period?->label ?: 'Not assigned' }}</strong></div>
                        <div><small>Component</small><strong>{{ $form?->projectComponent?->name ?: 'Not assigned' }}</strong></div>
                        <div><small>Content</small><strong>{{ $submission->indicator_results_count }} results &middot; {{ $submission->evidence_count }} evidence</strong></div>
                        <div><small>DQA</small><strong>{{ $submission->blocking_dqa_count }} blocking &middot; {{ $submission->open_dqa_count }} open</strong></div>
                    </div>
                    <a class="mel-btn mel-btn-primary w-100" href="{{ route('budget.me.submission-reviews.show', $submission) }}">Open review</a>
                </article>
            @empty
                <div class="mel-empty"><strong>No submissions match this view</strong><span>Adjust the filters or select another workflow stage.</span></div>
            @endforelse
        </div>

        @if($submissions->hasPages())<div class="mel-pagination">{{ $submissions->links() }}</div>@endif
    </section>
</div>
@endsection
