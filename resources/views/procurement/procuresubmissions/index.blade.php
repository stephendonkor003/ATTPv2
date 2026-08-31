@extends('layouts.app')

@push('styles')
    <style>
        .submission-hub {
            --hub-ink: #172033;
            --hub-muted: #687386;
            --hub-line: #e7eaf0;
            --hub-soft: #f5f7fb;
            --hub-primary: #3454d1;
            --hub-success: #17a668;
            --hub-warning: #dc8b12;
        }

        .submission-hub-hero {
            position: relative;
            overflow: hidden;
            padding: 1.5rem;
            color: #fff;
            border-radius: 18px;
            background: linear-gradient(125deg, #172c66 0%, #3454d1 58%, #4775e8 100%);
            box-shadow: 0 18px 45px rgba(31, 57, 134, .2);
        }

        .submission-hub-hero::after {
            position: absolute;
            top: -95px;
            right: -70px;
            width: 260px;
            height: 260px;
            content: '';
            border: 45px solid rgba(255, 255, 255, .08);
            border-radius: 50%;
        }

        .submission-hub-hero > * { position: relative; z-index: 1; }
        .submission-hub-hero p { max-width: 720px; color: rgba(255, 255, 255, .78); }

        .submission-hub-eyebrow {
            display: inline-flex;
            gap: .45rem;
            align-items: center;
            margin-bottom: .6rem;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .78);
        }

        .submission-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
        }

        .submission-summary-card {
            display: flex;
            gap: .85rem;
            align-items: center;
            min-width: 0;
            padding: 1rem 1.1rem;
            border: 1px solid var(--hub-line);
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(23, 32, 51, .055);
        }

        .submission-summary-icon {
            display: inline-flex;
            flex: 0 0 42px;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            font-size: 1.05rem;
            color: var(--hub-primary);
            border-radius: 12px;
            background: rgba(52, 84, 209, .1);
        }

        .submission-summary-icon.is-success { color: var(--hub-success); background: rgba(23, 166, 104, .11); }
        .submission-summary-icon.is-warning { color: var(--hub-warning); background: rgba(220, 139, 18, .12); }

        .submission-summary-label {
            margin-bottom: .15rem;
            overflow: hidden;
            font-size: .74rem;
            font-weight: 700;
            letter-spacing: .045em;
            text-overflow: ellipsis;
            text-transform: uppercase;
            white-space: nowrap;
            color: var(--hub-muted);
        }

        .submission-summary-value { font-size: 1.35rem; font-weight: 800; line-height: 1.15; color: var(--hub-ink); }

        .procurement-directory-toolbar {
            padding: 1rem;
            border: 1px solid var(--hub-line);
            border-radius: 14px;
            background: #fff;
        }

        .procurement-search-wrap { position: relative; }
        .procurement-search-wrap i {
            position: absolute;
            top: 50%;
            left: .9rem;
            color: var(--hub-muted);
            transform: translateY(-50%);
            pointer-events: none;
        }
        .procurement-search-wrap .form-control { padding-left: 2.65rem; }

        .procurement-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
        }

        .procurement-card-link {
            display: flex;
            min-width: 0;
            color: inherit;
            border: 1px solid var(--hub-line);
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 8px 26px rgba(23, 32, 51, .055);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }

        .procurement-card-link:hover {
            color: inherit;
            border-color: rgba(52, 84, 209, .42);
            box-shadow: 0 16px 38px rgba(31, 57, 134, .13);
            transform: translateY(-3px);
        }

        .procurement-card-link:focus-visible { outline: 3px solid rgba(52, 84, 209, .3); outline-offset: 3px; }
        .procurement-card-link.is-selected {
            border-color: var(--hub-primary);
            box-shadow: 0 0 0 3px rgba(52, 84, 209, .11), 0 16px 38px rgba(31, 57, 134, .13);
        }

        .procurement-card-body {
            display: flex;
            flex: 1;
            flex-direction: column;
            min-width: 0;
            padding: 1.15rem;
        }

        .procurement-card-reference {
            display: inline-flex;
            align-items: center;
            max-width: 100%;
            padding: .33rem .55rem;
            overflow: hidden;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .035em;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #2f4dbb;
            border-radius: 8px;
            background: rgba(52, 84, 209, .09);
        }

        .procurement-card-title {
            display: -webkit-box;
            min-height: 3.1rem;
            margin: .9rem 0 .35rem;
            overflow: hidden;
            font-size: 1rem;
            font-weight: 750;
            line-height: 1.5;
            color: var(--hub-ink);
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }

        .procurement-card-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(70px, 1fr));
            gap: .55rem;
            margin: 1rem 0;
        }

        .procurement-mini-stat { min-width: 0; padding: .65rem .45rem; text-align: center; border-radius: 10px; background: var(--hub-soft); }
        .procurement-mini-stat strong { display: block; font-size: 1.05rem; line-height: 1.1; color: var(--hub-ink); }
        .procurement-mini-stat span {
            display: block;
            margin-top: .25rem;
            overflow: hidden;
            font-size: .67rem;
            font-weight: 650;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: var(--hub-muted);
        }

        .procurement-progress-label { display: flex; justify-content: space-between; margin-bottom: .4rem; font-size: .72rem; color: var(--hub-muted); }
        .procurement-progress { height: 7px; overflow: hidden; border-radius: 999px; background: #e9edf5; }
        .procurement-progress > span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #3454d1, #5d7be7);
        }

        .procurement-card-footer {
            display: flex;
            gap: .75rem;
            align-items: center;
            justify-content: space-between;
            margin-top: auto;
            padding-top: 1rem;
            font-size: .73rem;
            color: var(--hub-muted);
        }

        .procurement-open-label { display: inline-flex; gap: .35rem; align-items: center; font-weight: 700; color: var(--hub-primary); }

        .submission-directory-card {
            overflow: hidden;
            border: 1px solid var(--hub-line);
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 10px 30px rgba(23, 32, 51, .07);
        }

        .submission-directory-header { padding: 1.25rem; color: #fff; background: linear-gradient(120deg, #18254a, #2f4dbb); }
        .submission-directory-meta { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
        .submission-directory-meta span {
            display: inline-flex;
            align-items: center;
            padding: .32rem .55rem;
            font-size: .72rem;
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 999px;
            background: rgba(255, 255, 255, .09);
        }

        .submission-directory-table { padding: 1.1rem; }
        .workflow-status-strip { display: flex; flex-wrap: wrap; gap: .45rem; }
        .workflow-status-chip {
            display: inline-flex;
            gap: .35rem;
            align-items: center;
            padding: .4rem .6rem;
            font-size: .72rem;
            color: var(--hub-muted);
            border: 1px solid var(--hub-line);
            border-radius: 999px;
            background: #fff;
        }
        .workflow-status-chip strong { color: var(--hub-ink); }

        html.app-skin-dark .submission-hub { --hub-ink: #f4f6fb; --hub-muted: #aeb7c7; --hub-line: #26324a; --hub-soft: #172239; }
        html.app-skin-dark .submission-summary-card,
        html.app-skin-dark .procurement-directory-toolbar,
        html.app-skin-dark .procurement-card-link,
        html.app-skin-dark .submission-directory-card,
        html.app-skin-dark .workflow-status-chip { background: #0f172a; }
        html.app-skin-dark .procurement-progress { background: #26324a; }

        @media (max-width: 991.98px) {
            .submission-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 767.98px) {
            .submission-hub-hero { padding: 1.15rem; border-radius: 14px; }
            .submission-summary-grid, .procurement-card-grid { grid-template-columns: 1fr; }
            .procurement-directory-toolbar .form-select,
            .procurement-directory-toolbar .btn,
            .submission-directory-header .btn { width: 100%; }
            .submission-directory-table { padding: .75rem; }
        }
    </style>
@endpush

@section('content')
    @php
        $procurementStatusColors = [
            'draft' => 'secondary',
            'submitted' => 'warning',
            'approved' => 'success',
            'published' => 'primary',
            'closed' => 'dark',
            'awarded' => 'success',
            'recalled' => 'danger',
        ];
        $submissionStatusColors = [
            'draft' => 'secondary',
            'submitted' => 'primary',
            'under_review' => 'info',
            'revision_requested' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            'withdrawn' => 'dark',
            'prescreen_failed' => 'danger',
            'prescreen_passed' => 'success',
            'eoi_evaluation' => 'warning',
            'eoi_not_qualified' => 'danger',
            'technical_evaluation' => 'success',
            'evaluated' => 'info',
            'site_visit_completed' => 'success',
        ];
    @endphp

    <div class="nxl-container submission-hub">
        <section class="submission-hub-hero mb-4" aria-labelledby="submission-hub-title">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div>
                    <div class="submission-hub-eyebrow"><i class="feather-layers" aria-hidden="true"></i> Submission workspace</div>
                    <h3 id="submission-hub-title" class="fw-bold text-white mb-2">Procurement Submissions</h3>
                    <p class="mb-0">Choose a procurement to review its applicants, submission records and 3PAP Sanctions Screening progress.</p>
                </div>

                @if (!$selectedProcurement)
                    @can('forms.manage')
                        <div>
                            @if ($screeningConfigured)
                                <form method="POST" action="{{ route('procurement.submissions.screen-all') }}"
                                    onsubmit="return confirm('Queue background 3PAP sanctions screening for accessible applicants that need a check?');">
                                    @csrf
                                    <button type="submit" class="btn btn-light">
                                        <i class="feather-shield me-1" aria-hidden="true"></i> Queue 3PAP checks for applicants
                                    </button>
                                </form>
                            @else
                                <button type="button" class="btn btn-outline-light" disabled>
                                    <i class="feather-slash me-1" aria-hidden="true"></i> 3PAP screening not configured
                                </button>
                            @endif
                        </div>
                    @endcan
                @endif
            </div>
        </section>

        <div class="alert alert-light border small mb-4" role="note">
            <i class="feather-info me-1" aria-hidden="true"></i>
            3PAP results support human review and do not automatically determine applicant eligibility.
        </div>

        <section class="submission-summary-grid mb-4" aria-label="Submission overview">
            <article class="submission-summary-card">
                <span class="submission-summary-icon"><i class="feather-briefcase" aria-hidden="true"></i></span>
                <div class="min-w-0"><div class="submission-summary-label">Procurements</div><div class="submission-summary-value">{{ number_format($overview['procurements']) }}</div></div>
            </article>
            <article class="submission-summary-card">
                <span class="submission-summary-icon"><i class="feather-file-text" aria-hidden="true"></i></span>
                <div class="min-w-0"><div class="submission-summary-label">Total submissions</div><div class="submission-summary-value">{{ number_format($overview['submissions']) }}</div></div>
            </article>
            <article class="submission-summary-card">
                <span class="submission-summary-icon is-success"><i class="feather-check-circle" aria-hidden="true"></i></span>
                <div class="min-w-0"><div class="submission-summary-label">Screened successfully</div><div class="submission-summary-value">{{ number_format($overview['screened']) }}</div></div>
            </article>
            <article class="submission-summary-card">
                <span class="submission-summary-icon is-warning"><i class="feather-alert-circle" aria-hidden="true"></i></span>
                <div class="min-w-0"><div class="submission-summary-label">Pending or failed</div><div class="submission-summary-value">{{ number_format($overview['needs_attention']) }}</div></div>
            </article>
        </section>

        <section aria-labelledby="procurement-directory-title">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-2 mb-3">
                <div>
                    <h5 id="procurement-directory-title" class="fw-bold mb-1">Browse by procurement</h5>
                    <p class="text-muted mb-0">Each card opens the complete applicant list for that procurement.</p>
                </div>
                <div class="small text-muted" aria-live="polite">
                    <span id="visibleProcurementCount">{{ $procurementGroups->count() }}</span> of {{ $procurementGroups->count() }} procurements shown
                </div>
            </div>

            @if ($procurementGroups->isNotEmpty())
                <div class="procurement-directory-toolbar mb-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-lg-5">
                            <label for="procurementCardSearch" class="form-label small fw-semibold">Search procurements</label>
                            <div class="procurement-search-wrap">
                                <i class="feather-search" aria-hidden="true"></i>
                                <input type="search" id="procurementCardSearch" class="form-control" placeholder="Search by title or reference" autocomplete="off">
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-2">
                            <label for="procurementStatusFilter" class="form-label small fw-semibold">Status</label>
                            <select id="procurementStatusFilter" class="form-select">
                                <option value="">All statuses</option>
                                @foreach ($procurementGroups->pluck('status')->filter()->unique()->sort() as $status)
                                    <option value="{{ strtolower($status) }}">{{ \Illuminate\Support\Str::headline($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6 col-lg-2">
                            <label for="procurementAttentionFilter" class="form-label small fw-semibold">3PAP Screening</label>
                            <select id="procurementAttentionFilter" class="form-select">
                                <option value="">All records</option>
                                <option value="needs-attention">Needs attention</option>
                                <option value="complete">Fully checked</option>
                            </select>
                        </div>
                        <div class="col-sm-8 col-lg-2">
                            <label for="procurementSort" class="form-label small fw-semibold">Sort by</label>
                            <select id="procurementSort" class="form-select">
                                <option value="latest">Latest submission</option>
                                <option value="submissions">Most submissions</option>
                                <option value="title">Title A–Z</option>
                            </select>
                        </div>
                        <div class="col-sm-4 col-lg-1">
                            <button type="button" id="clearProcurementFilters" class="btn btn-light w-100" title="Clear filters">
                                <i class="feather-rotate-ccw" aria-hidden="true"></i><span class="visually-hidden">Clear filters</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div id="procurementCardGrid" class="procurement-card-grid">
                    @foreach ($procurementGroups as $procurement)
                        @php
                            $total = (int) $procurement->submissions_count;
                            $screened = (int) $procurement->screening_success_count;
                            $failed = (int) $procurement->screening_failed_count;
                            $active = (int) ($procurement->screening_active_count ?? 0);
                            $waiting = (int) ($procurement->screening_waiting_count ?? 0);
                            $unchecked = max(0, $total - (int) $procurement->screening_records_count);
                            $needsAttention = max(0, $total - $screened);
                            $progress = $total > 0 ? min(100, (int) round(($screened / $total) * 100)) : 0;
                            $latestTimestamp = $procurement->latest_submission_at
                                ? \Illuminate\Support\Carbon::parse($procurement->latest_submission_at)->timestamp
                                : 0;
                            $isSelected = $selectedProcurement && (string) $selectedProcurement->id === (string) $procurement->id;
                        @endphp
                        <a href="{{ route('procurement.submissions.index', ['procurement_id' => $procurement->id]) }}#submission-directory"
                            class="procurement-card-link text-decoration-none {{ $isSelected ? 'is-selected' : '' }}"
                            data-procurement-card
                            data-search="{{ \Illuminate\Support\Str::lower(trim($procurement->title.' '.($procurement->reference_no ?? ''))) }}"
                            data-title="{{ \Illuminate\Support\Str::lower($procurement->title) }}"
                            data-status="{{ strtolower($procurement->status ?? 'unknown') }}"
                            data-attention="{{ $needsAttention > 0 ? 'needs-attention' : 'complete' }}"
                            data-submissions="{{ $total }}"
                            data-latest="{{ $latestTimestamp }}"
                            aria-label="Open {{ $total }} submissions for {{ $procurement->title }}"
                            @if ($isSelected) aria-current="page" @endif>
                            <article class="procurement-card-body">
                                <div class="d-flex gap-2 justify-content-between align-items-start">
                                    <span class="procurement-card-reference">{{ $procurement->reference_no ?: 'No reference' }}</span>
                                    <span class="badge bg-{{ $procurementStatusColors[$procurement->status] ?? 'secondary' }}-subtle text-{{ $procurementStatusColors[$procurement->status] ?? 'secondary' }}">
                                        {{ \Illuminate\Support\Str::headline($procurement->status ?: 'unknown') }}
                                    </span>
                                </div>

                                <h6 class="procurement-card-title">{{ $procurement->title }}</h6>
                                <div class="small text-muted">Fiscal year {{ $procurement->fiscal_year ?: 'not specified' }}</div>

                                <div class="procurement-card-stats">
                                    <div class="procurement-mini-stat"><strong>{{ number_format($total) }}</strong><span>Applications</span></div>
                                    <div class="procurement-mini-stat"><strong>{{ number_format($unchecked) }}</strong><span>Not checked</span></div>
                                    <div class="procurement-mini-stat"><strong>{{ number_format($active) }}</strong><span>In progress</span></div>
                                    @if ($waiting > 0)<div class="procurement-mini-stat"><strong>{{ number_format($waiting) }}</strong><span>Waiting setup</span></div>@endif
                                    <div class="procurement-mini-stat"><strong>{{ number_format($failed) }}</strong><span>Check failed</span></div>
                                </div>

                                <div class="procurement-progress-label"><span>Successful 3PAP screening</span><span>{{ $screened }}/{{ $total }}</span></div>
                                <div class="procurement-progress" role="progressbar" aria-label="Successful 3PAP sanctions screening progress for {{ $procurement->title }}"
                                    aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100">
                                    <span style="width: {{ $progress }}%"></span>
                                </div>

                                <div class="procurement-card-footer">
                                    <span><i class="feather-clock me-1" aria-hidden="true"></i>
                                        {{ $procurement->latest_submission_at
                                            ? \Illuminate\Support\Carbon::parse($procurement->latest_submission_at)->diffForHumans()
                                            : 'No submission date' }}
                                    </span>
                                    <span class="procurement-open-label">
                                        {{ $isSelected ? 'Viewing list' : 'Open submissions' }} <i class="feather-arrow-right" aria-hidden="true"></i>
                                    </span>
                                </div>
                            </article>
                        </a>
                    @endforeach
                </div>

                <div id="procurementFilterEmpty" class="alert alert-light border text-center mt-3 mb-0" hidden>
                    <i class="feather-search d-block fs-3 text-muted mb-2" aria-hidden="true"></i>
                    <strong>No procurements match these filters.</strong>
                    <div class="small text-muted mt-1">Clear the filters or try a broader search.</div>
                </div>
            @else
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-5 text-center">
                        <i class="feather-inbox fs-1 text-muted" aria-hidden="true"></i>
                        <h6 class="fw-bold mt-3 mb-1">No procurement submissions available</h6>
                        <p class="text-muted mb-0">No submissions are currently available within your assigned portfolio.</p>
                    </div>
                </div>
            @endif
        </section>

        @if ($selectedProcurement)
            @php
                $selectedTotal = (int) $selectedProcurement->submissions_count;
                $selectedScreened = (int) $selectedProcurement->screening_success_count;
                $selectedFailed = (int) $selectedProcurement->screening_failed_count;
                $selectedActive = (int) ($selectedProcurement->screening_active_count ?? 0);
                $selectedWaiting = (int) ($selectedProcurement->screening_waiting_count ?? 0);
                $selectedUnchecked = max(0, $selectedTotal - (int) $selectedProcurement->screening_records_count);
            @endphp
            <section id="submission-directory" class="submission-directory-card mt-4" aria-labelledby="selected-procurement-title">
                <header class="submission-directory-header">
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                        <div>
                            <a href="{{ route('procurement.submissions.index') }}" class="d-inline-flex align-items-center text-white-50 small mb-2">
                                <i class="feather-arrow-left me-1" aria-hidden="true"></i> All procurements
                            </a>
                            <h4 id="selected-procurement-title" class="fw-bold text-white mb-2">{{ $selectedProcurement->title }}</h4>
                            <div class="submission-directory-meta">
                                <span>{{ $selectedProcurement->reference_no ?: 'No reference' }}</span>
                                <span>{{ number_format($selectedTotal) }} {{ \Illuminate\Support\Str::plural('submission', $selectedTotal) }}</span>
                                <span>{{ number_format($selectedScreened) }} screened</span>
                                <span>{{ number_format($selectedUnchecked) }} not checked</span>
                                @if ($selectedActive > 0)<span>{{ number_format($selectedActive) }} in progress</span>@endif
                                @if ($selectedWaiting > 0)<span>{{ number_format($selectedWaiting) }} waiting for setup</span>@endif
                                @if ($selectedFailed > 0)<span>{{ number_format($selectedFailed) }} failed checks</span>@endif
                            </div>
                        </div>

                        @can('forms.manage')
                            <div>
                                @if ($screeningConfigured && $selectedTotal > 0)
                                    <form method="POST" action="{{ route('procurement.submissions.screen-all') }}"
                                        onsubmit="return confirm('Queue background 3PAP sanctions screening for applicants in this procurement that need a check?');">
                                        @csrf
                                        <input type="hidden" name="procurement_id" value="{{ $selectedProcurement->id }}">
                                        <button type="submit" class="btn btn-light">
                                            <i class="feather-shield me-1" aria-hidden="true"></i> Queue 3PAP checks for this procurement
                                        </button>
                                    </form>
                                @elseif (!$screeningConfigured)
                                    <button type="button" class="btn btn-outline-light" disabled>
                                        <i class="feather-slash me-1" aria-hidden="true"></i> 3PAP screening not configured
                                    </button>
                                @endif
                            </div>
                        @endcan
                    </div>
                </header>

                <div class="submission-directory-table">
                    @if ($statusDistribution->isNotEmpty())
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                            <div>
                                <h6 class="fw-bold mb-1">Applicant submissions</h6>
                                <p class="text-muted small mb-0">Search, sort and open an applicant record below.</p>
                            </div>
                            <div class="workflow-status-strip" aria-label="Submission status totals">
                                @foreach ($statusDistribution as $status => $count)
                                    <span class="workflow-status-chip">{{ \Illuminate\Support\Str::headline($status) }} <strong>{{ number_format($count) }}</strong></span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <x-data-table
                        id="submissionsTable{{ str_replace('-', '', (string) $selectedProcurement->id) }}"
                        :config="[
                            'pageLength' => 25,
                            'order' => [[6, 'desc']],
                            'columnDefs' => [
                                ['targets' => [7], 'orderable' => false, 'searchable' => false],
                            ],
                        ]">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Submission Code</th>
                                <th>Applicant</th>
                                <th>Official Email</th>
                                <th>Form</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">3PAP Sanctions Screening</th>
                                <th>Submitted At</th>
                                <th class="text-center" width="180">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($submissions as $submission)
                                @php
                                    $officialName = $submission->values->firstWhere('field_key', 'official_name')?->value;
                                    $officialEmail = $submission->values->firstWhere('field_key', 'official_email')?->value;
                                    $screening = $submission->screening;
                                    $screeningAutomationState = data_get($screening?->response_payload, 'automation.state');
                                    $screeningIsWaitingForSetup = in_array($screeningAutomationState, [
                                        'automatic_disabled',
                                        'waiting_for_configuration',
                                    ], true);
                                    $activeScreeningStatuses = ['queued', 'processing', 'retrying'];
                                    $activeScreeningLabels = [
                                        'queued' => 'Queued',
                                        'processing' => 'Screening',
                                        'retrying' => 'Retry scheduled',
                                    ];
                                    $activeScreeningColors = [
                                        'queued' => 'primary',
                                        'processing' => 'warning',
                                        'retrying' => 'info',
                                    ];
                                    $riskColors = [
                                        'clear' => 'success',
                                        'low' => 'info',
                                        'medium' => 'warning',
                                        'high' => 'danger',
                                        'critical' => 'dark',
                                    ];
                                @endphp
                                <tr>
                                    <td class="ps-3"><span class="fw-semibold text-primary">{{ $submission->procurement_submission_code }}</span></td>
                                    <td><div class="fw-semibold">{{ $officialName ?: $submission->submitter?->name ?: 'Applicant name unavailable' }}</div></td>
                                    <td>
                                        @if ($officialEmail)
                                            <a href="mailto:{{ $officialEmail }}">{{ $officialEmail }}</a>
                                        @else
                                            <span class="text-muted">Not provided</span>
                                        @endif
                                    </td>
                                    <td>{{ $submission->form?->name ?: 'Form unavailable' }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-{{ $submissionStatusColors[$submission->status] ?? 'secondary' }}-subtle text-{{ $submissionStatusColors[$submission->status] ?? 'secondary' }} px-3 py-1">
                                            {{ \Illuminate\Support\Str::headline($submission->status ?: 'unknown') }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        @if ($screening)
                                            @if (in_array($screening->request_status, $activeScreeningStatuses, true))
                                                @php
                                                    $activeColor = $activeScreeningColors[$screening->request_status] ?? 'primary';
                                                @endphp
                                                <span class="badge bg-{{ $activeColor }}-subtle text-{{ $activeColor }} px-3 py-1">
                                                    {{ $activeScreeningLabels[$screening->request_status] ?? 'In progress' }}
                                                </span>
                                                @if ($screening->request_status === 'retrying' && $screening->next_retry_at)
                                                    <div class="small text-muted mt-1">Retry {{ $screening->next_retry_at->format('d M Y, H:i') }}</div>
                                                @elseif ($screening->queued_at)
                                                    <div class="small text-muted mt-1">Queued {{ $screening->queued_at->format('d M Y, H:i') }}</div>
                                                @endif
                                            @elseif ($screening->request_status === 'waiting')
                                                <span class="badge bg-warning-subtle text-warning px-3 py-1">Waiting for setup</span>
                                                <div class="small text-muted mt-1">{{ \Illuminate\Support\Str::limit($screening->error_message, 40) }}</div>
                                            @elseif ($screening->request_status === 'error')
                                                <span class="badge bg-{{ $screeningIsWaitingForSetup ? 'warning' : 'danger' }}-subtle text-{{ $screeningIsWaitingForSetup ? 'warning' : 'danger' }} px-3 py-1">
                                                    {{ $screeningIsWaitingForSetup ? 'Waiting for setup' : 'Check failed' }}
                                                </span>
                                                <div class="small text-muted mt-1">{{ \Illuminate\Support\Str::limit($screening->error_message, 40) }}</div>
                                            @elseif ($screening->request_status === 'success')
                                                <span class="badge bg-{{ $riskColors[$screening->risk_level] ?? 'secondary' }} px-3 py-1">
                                                    {{ strtoupper($screening->risk_level ?? 'unknown') }}
                                                </span>
                                                <div class="small text-muted mt-1">
                                                    {{ number_format((int) $screening->total_matches) }}
                                                    {{ \Illuminate\Support\Str::plural('match', (int) $screening->total_matches) }}
                                                </div>
                                                @if ($screening->review_decision)
                                                    <div class="small mt-1">
                                                        <span class="badge bg-{{ $screening->review_decision === 'fit' ? 'success' : 'danger' }}-subtle text-{{ $screening->review_decision === 'fit' ? 'success' : 'danger' }}">
                                                            {{ $screening->review_decision === 'fit' ? 'Fit' : 'Not fit' }}
                                                        </span>
                                                    </div>
                                                @endif
                                                <div class="small text-muted">{{ $screening->last_checked_at?->format('d M Y, H:i') ?: 'Not recorded' }}</div>
                                            @else
                                                <span class="badge bg-warning-subtle text-warning px-3 py-1">Result unavailable</span>
                                            @endif
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary px-3 py-1">Not checked</span>
                                        @endif
                                    </td>
                                    <td data-order="{{ $submission->submitted_at?->timestamp ?? 0 }}">
                                        {{ $submission->submitted_at?->format('d M Y, H:i') ?: 'Not recorded' }}
                                    </td>
                                    <td class="text-center">
                                        <div class="d-inline-flex flex-wrap justify-content-center gap-1">
                                            <a href="{{ route('procurement.submissions.show', $submission) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="feather-eye me-1" aria-hidden="true"></i> View
                                            </a>
                                            @can('forms.manage')
                                                <a href="{{ route('procurement.submissions.screening.report', $submission) }}"
                                                    class="btn btn-sm btn-outline-dark">
                                                    <i class="feather-shield me-1" aria-hidden="true"></i> Report
                                                </a>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-data-table>
                </div>
            </section>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const grid = document.getElementById('procurementCardGrid');
            if (!grid) return;

            const cards = Array.from(grid.querySelectorAll('[data-procurement-card]'));
            const search = document.getElementById('procurementCardSearch');
            const status = document.getElementById('procurementStatusFilter');
            const attention = document.getElementById('procurementAttentionFilter');
            const sort = document.getElementById('procurementSort');
            const clear = document.getElementById('clearProcurementFilters');
            const count = document.getElementById('visibleProcurementCount');
            const empty = document.getElementById('procurementFilterEmpty');

            const applyFilters = function () {
                const query = search.value.trim().toLowerCase();
                const visible = cards.filter(function (card) {
                    const matches = (!query || card.dataset.search.includes(query))
                        && (!status.value || card.dataset.status === status.value)
                        && (!attention.value || card.dataset.attention === attention.value);
                    card.hidden = !matches;
                    return matches;
                });

                visible.sort(function (left, right) {
                    if (sort.value === 'submissions') return Number(right.dataset.submissions) - Number(left.dataset.submissions);
                    if (sort.value === 'title') return left.dataset.title.localeCompare(right.dataset.title);
                    return Number(right.dataset.latest) - Number(left.dataset.latest);
                }).forEach(function (card) { grid.appendChild(card); });

                count.textContent = String(visible.length);
                empty.hidden = visible.length !== 0;
            };

            [search, status, attention, sort].forEach(function (control) {
                control.addEventListener(control === search ? 'input' : 'change', applyFilters);
            });

            clear.addEventListener('click', function () {
                search.value = '';
                status.value = '';
                attention.value = '';
                sort.value = 'latest';
                applyFilters();
                search.focus();
            });

            applyFilters();
        });
    </script>
@endpush
