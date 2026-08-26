@extends('layouts.app')

@section('title', 'EOI Qualification Report')

@section('content')
    @php
        $procurement = $report['procurement'];
        $stats = $report['stats'];
        $applicants = $report['applicants'];
        $generatedAt = $report['generated_at'];
        $procurementTitle = $procurement->title ?: 'Untitled procurement';
        $procurementReference = $procurement->reference_no ?: 'No reference number';
    @endphp

    <main class="nxl-container eoi-report" aria-labelledby="eoiReportTitle">
        <header class="page-header eoi-page-header">
            <div class="page-header-left">
                <span class="eoi-page-kicker">Expression of Interest</span>
                <h4 class="fw-bold mb-1" id="eoiReportTitle">
                    <i class="feather-award me-2" aria-hidden="true"></i>
                    Qualification Report
                </h4>
                <p class="mb-0">
                    {{ $procurementTitle }}
                    <span class="eoi-header-separator" aria-hidden="true">/</span>
                    {{ $procurementReference }}
                </p>
            </div>

            <div class="eoi-header-actions" aria-label="Report actions">
                <a href="{{ route('reports.evaluations.index') }}" class="btn btn-light btn-sm">
                    <i class="feather-arrow-left me-1" aria-hidden="true"></i>
                    Back to Reports
                </a>
                <a href="{{ route('reports.evaluations.eoi.procurement.pdf', $procurement) }}" class="btn btn-success btn-sm">
                    <i class="feather-download me-1" aria-hidden="true"></i>
                    Download PDF
                </a>
            </div>
        </header>

        <section class="eoi-rule-card" aria-labelledby="qualificationRuleTitle">
            <div class="eoi-rule-icon" aria-hidden="true">
                <i class="feather-shield"></i>
            </div>
            <div class="eoi-rule-copy">
                <span class="eoi-eyebrow">Mandatory qualification gate</span>
                <h5 id="qualificationRuleTitle">One Not Qualified decision stops progression</h5>
                <p>
                    An applicant advances to <strong>Technical Evaluation</strong> only when every assigned panel task is
                    complete and no evaluator has recorded <strong>Not Qualified</strong> against any criterion.
                </p>
            </div>
            <div class="eoi-rule-path" aria-label="Qualification rules">
                <div class="eoi-rule-step eoi-rule-step--success">
                    <span>All Qualified</span>
                    <strong>Fully Qualified</strong>
                    <small>Advances when the panel is complete</small>
                </div>
                <div class="eoi-rule-step eoi-rule-step--warning">
                    <span>One or more Average Qualified</span>
                    <strong>Average Qualified</strong>
                    <small>Advances if there is no NQ</small>
                </div>
                <div class="eoi-rule-step eoi-rule-step--danger">
                    <span>At least one Not Qualified</span>
                    <strong>Not Qualified</strong>
                    <small>Does not advance</small>
                </div>
            </div>
        </section>

        <section class="eoi-kpi-grid" aria-label="EOI qualification overview">
            <article class="eoi-kpi eoi-kpi--total">
                <span class="eoi-kpi-icon" aria-hidden="true"><i class="feather-users"></i></span>
                <div>
                    <span>Total Applicants</span>
                    <strong>{{ number_format($stats['total_applicants']) }}</strong>
                    <small>In this EOI report</small>
                </div>
            </article>
            <article class="eoi-kpi eoi-kpi--advance">
                <span class="eoi-kpi-icon" aria-hidden="true"><i class="feather-arrow-up-right"></i></span>
                <div>
                    <span>Advance to Technical</span>
                    <strong>{{ number_format($stats['advance']) }}</strong>
                    <small>Panel-complete applicants</small>
                </div>
            </article>
            <article class="eoi-kpi eoi-kpi--qualified">
                <span class="eoi-kpi-icon" aria-hidden="true"><i class="feather-check-circle"></i></span>
                <div>
                    <span>Fully Qualified</span>
                    <strong>{{ number_format($stats['fully_qualified']) }}</strong>
                    <small>Qualified decisions only</small>
                </div>
            </article>
            <article class="eoi-kpi eoi-kpi--average">
                <span class="eoi-kpi-icon" aria-hidden="true"><i class="feather-minus-circle"></i></span>
                <div>
                    <span>Average Qualified</span>
                    <strong>{{ number_format($stats['average_qualified']) }}</strong>
                    <small>No disqualifying decision</small>
                </div>
            </article>
            <article class="eoi-kpi eoi-kpi--not-qualified">
                <span class="eoi-kpi-icon" aria-hidden="true"><i class="feather-x-circle"></i></span>
                <div>
                    <span>Not Qualified</span>
                    <strong>{{ number_format($stats['not_qualified']) }}</strong>
                    <small>At least one NQ recorded</small>
                </div>
            </article>
            <article class="eoi-kpi eoi-kpi--pending">
                <span class="eoi-kpi-icon" aria-hidden="true"><i class="feather-clock"></i></span>
                <div>
                    <span>Awaiting Panel</span>
                    <strong>{{ number_format($stats['pending']) }}</strong>
                    <small>{{ number_format($stats['panel_members']) }} panel member(s)</small>
                </div>
            </article>
        </section>

        <section class="eoi-applicant-panel" aria-labelledby="applicantSummaryTitle">
            <div class="eoi-panel-heading">
                <div>
                    <span class="eoi-eyebrow">Applicant-level determination</span>
                    <h5 id="applicantSummaryTitle">Qualification Summary</h5>
                    <p>Search the shortlist, filter outcomes, and expand an applicant to inspect every evaluator decision.</p>
                </div>
                <div class="eoi-generated-at">
                    <i class="feather-calendar" aria-hidden="true"></i>
                    <span>Generated</span>
                    <strong>{{ $generatedAt->format('d M Y, H:i') }}</strong>
                </div>
            </div>

            <div class="eoi-filter-bar" role="search" aria-label="Filter applicant qualification results">
                <div class="eoi-search-field">
                    <label for="eoiApplicantSearch">Search applicants</label>
                    <div class="eoi-input-wrap">
                        <i class="feather-search" aria-hidden="true"></i>
                        <input
                            type="search"
                            class="form-control"
                            id="eoiApplicantSearch"
                            placeholder="Name, submission code, or email"
                            autocomplete="off"
                        >
                    </div>
                </div>
                <div class="eoi-filter-field">
                    <label for="eoiOutcomeFilter">Outcome</label>
                    <select class="form-control" id="eoiOutcomeFilter">
                        <option value="all">All outcomes ({{ $stats['total_applicants'] }})</option>
                        <option value="fully_qualified">Fully Qualified ({{ $stats['fully_qualified'] }})</option>
                        <option value="average_qualified">Average Qualified ({{ $stats['average_qualified'] }})</option>
                        <option value="not_qualified">Not Qualified ({{ $stats['not_qualified'] }})</option>
                        <option value="pending">Awaiting Panel ({{ $stats['pending'] }})</option>
                    </select>
                </div>
                <div class="eoi-filter-field">
                    <label for="eoiPanelFilter">Panel status</label>
                    <select class="form-control" id="eoiPanelFilter">
                        <option value="all">All panel statuses</option>
                        <option value="complete">Panel complete</option>
                        <option value="incomplete">Panel incomplete</option>
                    </select>
                </div>
                <div class="eoi-filter-actions" aria-label="Applicant detail controls">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="eoiExpandAll">
                        <i class="feather-chevrons-down me-1" aria-hidden="true"></i>
                        Expand visible
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="eoiCollapseAll">
                        <i class="feather-chevrons-up me-1" aria-hidden="true"></i>
                        Collapse all
                    </button>
                    <button type="button" class="btn btn-light btn-sm" id="eoiClearFilters">
                        <i class="feather-rotate-ccw me-1" aria-hidden="true"></i>
                        Clear
                    </button>
                </div>
            </div>

            <div class="eoi-list-status" id="eoiResultCount" role="status" aria-live="polite">
                Showing {{ $stats['total_applicants'] }} of {{ $stats['total_applicants'] }} applicants
            </div>

            <div class="eoi-applicant-list" id="eoiApplicantList">
                @forelse ($applicants as $row)
                    @php
                        $applicant = $row['applicant'];
                        $outcome = $row['outcome'];
                        $applicantName = $applicant->display_name;
                        $submissionCode = $applicant->procurement_submission_code ?: 'No submission code';
                        $applicantEmail = $applicant->submitter?->email;
                        $panelState = $row['panel_complete'] ? 'complete' : 'incomplete';
                        $searchText = Illuminate\Support\Str::lower(implode(' ', array_filter([
                            $applicantName,
                            $submissionCode,
                            $applicantEmail,
                            $outcome['label'],
                            $row['next_stage'],
                        ])));
                        $outcomeIcon = match ($outcome['code']) {
                            'fully_qualified' => 'feather-check-circle',
                            'average_qualified' => 'feather-minus-circle',
                            'not_qualified' => 'feather-x-circle',
                            default => 'feather-clock',
                        };
                    @endphp

                    <details
                        class="eoi-applicant"
                        data-search="{{ $searchText }}"
                        data-outcome="{{ $outcome['code'] }}"
                        data-panel="{{ $panelState }}"
                    >
                        <summary>
                            <span class="eoi-applicant-identity">
                                <span class="eoi-applicant-avatar" aria-hidden="true">
                                    <i class="feather-user"></i>
                                </span>
                                <span>
                                    <strong>{{ $applicantName }}</strong>
                                    <small>
                                        {{ $submissionCode }}
                                        @if ($applicantEmail)
                                            <span aria-hidden="true">&middot;</span> {{ $applicantEmail }}
                                        @endif
                                    </small>
                                </span>
                            </span>

                            <span class="eoi-panel-progress">
                                <span class="eoi-panel-progress-label">
                                    <span>Panel completion</span>
                                    <strong>{{ $row['completed_tasks'] }}/{{ $row['expected_tasks'] }} tasks</strong>
                                </span>
                                <span
                                    class="eoi-progress-track"
                                    role="progressbar"
                                    aria-label="Panel completion for {{ $applicantName }}"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                    aria-valuenow="{{ $row['completion_percent'] }}"
                                >
                                    <span style="width: {{ $row['completion_percent'] }}%"></span>
                                </span>
                                <small>
                                    {{ $row['completed_evaluators'] }}/{{ $row['expected_evaluators'] }} evaluator(s)
                                    <span class="eoi-panel-label eoi-panel-label--{{ $panelState }}">
                                        {{ $row['panel_complete'] ? 'Complete' : 'In progress' }}
                                    </span>
                                </small>
                            </span>

                            <span class="eoi-decision-totals" aria-label="Decision counts">
                                <span class="eoi-count eoi-count--qualified" title="Qualified decisions">
                                    <b>Q</b> {{ $row['counts']['qualified'] }}
                                </span>
                                <span class="eoi-count eoi-count--average" title="Average Qualified decisions">
                                    <b>AQ</b> {{ $row['counts']['average_qualified'] }}
                                </span>
                                <span class="eoi-count eoi-count--not-qualified" title="Not Qualified decisions">
                                    <b>NQ</b> {{ $row['counts']['not_qualified'] }}
                                </span>
                            </span>

                            <span class="eoi-applicant-outcome">
                                <span class="eoi-outcome eoi-outcome--{{ $outcome['code'] }}">
                                    <i class="{{ $outcomeIcon }}" aria-hidden="true"></i>
                                    {{ $outcome['label'] }}
                                </span>
                                <small>
                                    Next stage: <strong>{{ $row['next_stage'] }}</strong>
                                </small>
                            </span>

                            <span class="eoi-summary-chevron" aria-hidden="true">
                                <i class="feather-chevron-down"></i>
                            </span>
                        </summary>

                        <div class="eoi-applicant-detail">
                            <div class="eoi-determination eoi-determination--{{ $outcome['code'] }}">
                                <span class="eoi-determination-icon" aria-hidden="true">
                                    <i class="{{ $outcomeIcon }}"></i>
                                </span>
                                <div>
                                    <span class="eoi-eyebrow">Panel determination</span>
                                    <h6>{{ $outcome['label'] }} &mdash; {{ $row['next_stage'] }}</h6>
                                    <p>{{ $outcome['description'] }}</p>
                                </div>
                                <div class="eoi-determination-meta">
                                    <span>{{ $row['total_decisions'] }} decision(s)</span>
                                    <span>{{ $row['evaluation_reports']->count() }} template(s)</span>
                                </div>
                            </div>

                            <div class="eoi-detail-stats" aria-label="Detailed applicant totals">
                                <div>
                                    <span>Panel tasks</span>
                                    <strong>{{ $row['completed_tasks'] }} / {{ $row['expected_tasks'] }}</strong>
                                </div>
                                <div>
                                    <span>Qualified</span>
                                    <strong class="text-success">{{ $row['counts']['qualified'] }}</strong>
                                </div>
                                <div>
                                    <span>Average Qualified</span>
                                    <strong class="eoi-text-warning">{{ $row['counts']['average_qualified'] }}</strong>
                                </div>
                                <div>
                                    <span>Not Qualified</span>
                                    <strong class="text-danger">{{ $row['counts']['not_qualified'] }}</strong>
                                </div>
                                <div>
                                    <span>Panel status</span>
                                    <strong>{{ $row['panel_complete'] ? 'Complete' : 'In progress' }}</strong>
                                </div>
                                <div>
                                    <span>Progression</span>
                                    <strong>{{ $row['can_advance'] ? 'Approved' : 'Not approved' }}</strong>
                                </div>
                            </div>

                            <div class="eoi-template-stack">
                                @forelse ($row['evaluation_reports'] as $templateReport)
                                    @php
                                        $evaluation = $templateReport['evaluation'];
                                        $members = $templateReport['members'];
                                        $criteriaRows = $templateReport['criteria'];
                                        $completedMembers = $members->where('task_complete', true)->count();
                                    @endphp

                                    <article class="eoi-template-card">
                                        <header class="eoi-template-header">
                                            <div>
                                                <span class="eoi-eyebrow">EOI evaluation template</span>
                                                <h6>
                                                    <i class="feather-clipboard" aria-hidden="true"></i>
                                                    {{ $evaluation->name }}
                                                </h6>
                                                @if ($evaluation->description)
                                                    <p>{{ $evaluation->description }}</p>
                                                @endif
                                            </div>
                                            <div class="eoi-template-meta">
                                                <span><strong>{{ $criteriaRows->count() }}</strong> criteria</span>
                                                <span><strong>{{ $members->count() }}</strong> evaluator(s)</span>
                                                <span class="{{ $completedMembers === $members->count() && $members->isNotEmpty() ? 'is-complete' : '' }}">
                                                    <strong>{{ $completedMembers }}/{{ $members->count() }}</strong> complete
                                                </span>
                                            </div>
                                        </header>

                                        <section class="eoi-member-section" aria-labelledby="memberHeading-{{ $applicant->id }}-{{ $evaluation->id }}">
                                            <div class="eoi-subsection-title">
                                                <div>
                                                    <span class="eoi-eyebrow">Assigned panel</span>
                                                    <h6 id="memberHeading-{{ $applicant->id }}-{{ $evaluation->id }}">Evaluator completion</h6>
                                                </div>
                                                <span>{{ $completedMembers }} of {{ $members->count() }} task(s) complete</span>
                                            </div>

                                            <div class="eoi-member-grid">
                                                @forelse ($members as $member)
                                                    @php
                                                        $memberStatus = $member['task_complete']
                                                            ? 'complete'
                                                            : ($member['submitted'] ? 'incomplete' : 'pending');
                                                        $memberStatusLabel = $member['task_complete']
                                                            ? 'Complete'
                                                            : ($member['submitted'] ? 'Missing decisions' : 'Awaiting submission');
                                                    @endphp
                                                    <article class="eoi-member eoi-member--{{ $memberStatus }}">
                                                        <div class="eoi-member-head">
                                                            <span class="eoi-member-icon" aria-hidden="true">
                                                                <i class="{{ $member['task_complete'] ? 'feather-user-check' : 'feather-user' }}"></i>
                                                            </span>
                                                            <div>
                                                                <strong>{{ $member['name'] }}</strong>
                                                                @if ($member['email'])
                                                                    <small>{{ $member['email'] }}</small>
                                                                @endif
                                                            </div>
                                                            <span class="eoi-member-status">{{ $memberStatusLabel }}</span>
                                                        </div>
                                                        <div class="eoi-member-foot">
                                                            <span>
                                                                <i class="feather-calendar" aria-hidden="true"></i>
                                                                {{ $member['submitted_at']?->format('d M Y, H:i') ?? 'Not submitted' }}
                                                            </span>
                                                            <span class="eoi-member-counts" aria-label="Evaluator decision counts">
                                                                <b class="is-q">Q {{ $member['counts']['qualified'] }}</b>
                                                                <b class="is-aq">AQ {{ $member['counts']['average_qualified'] }}</b>
                                                                <b class="is-nq">NQ {{ $member['counts']['not_qualified'] }}</b>
                                                            </span>
                                                        </div>
                                                        @unless ($member['assigned'])
                                                            <small class="eoi-import-note">
                                                                <i class="feather-info" aria-hidden="true"></i>
                                                                Imported submission; original assignment is unavailable.
                                                            </small>
                                                        @endunless
                                                    </article>
                                                @empty
                                                    <div class="eoi-inline-empty">
                                                        <i class="feather-user-x" aria-hidden="true"></i>
                                                        No evaluator records are available for this template.
                                                    </div>
                                                @endforelse
                                            </div>
                                        </section>

                                        <section class="eoi-matrix-section" aria-labelledby="matrixHeading-{{ $applicant->id }}-{{ $evaluation->id }}">
                                            <div class="eoi-subsection-title">
                                                <div>
                                                    <span class="eoi-eyebrow">Decision evidence</span>
                                                    <h6 id="matrixHeading-{{ $applicant->id }}-{{ $evaluation->id }}">Criterion-by-evaluator matrix</h6>
                                                </div>
                                                <div class="eoi-matrix-legend" aria-label="Decision legend">
                                                    <span class="eoi-decision eoi-decision--qualified">Q</span>
                                                    <span>Qualified</span>
                                                    <span class="eoi-decision eoi-decision--average">AQ</span>
                                                    <span>Average Qualified</span>
                                                    <span class="eoi-decision eoi-decision--not-qualified">NQ</span>
                                                    <span>Not Qualified</span>
                                                </div>
                                            </div>

                                            <div class="table-responsive eoi-matrix-wrap">
                                                <table class="table eoi-matrix-table">
                                                    <caption class="visually-hidden">
                                                        Criterion decisions and comments for {{ $evaluation->name }} and {{ $applicantName }}
                                                    </caption>
                                                    <thead>
                                                        <tr>
                                                            <th scope="col" class="eoi-criterion-column">Criterion</th>
                                                            <th scope="col" class="eoi-result-column">Panel result</th>
                                                            @foreach ($members as $member)
                                                                <th scope="col" class="eoi-evaluator-column">
                                                                    <span>{{ $member['name'] }}</span>
                                                                    <small>{{ $member['task_complete'] ? 'Complete' : ($member['submitted'] ? 'Incomplete' : 'Pending') }}</small>
                                                                </th>
                                                            @endforeach
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @forelse ($criteriaRows as $criterionRow)
                                                            @php
                                                                $criterion = $criterionRow['criterion'];
                                                                $criterionOutcome = $criterionRow['outcome'];
                                                            @endphp
                                                            <tr>
                                                                <th scope="row" class="eoi-criterion-cell">
                                                                    <span class="eoi-criterion-section">{{ $criterionRow['section']->name }}</span>
                                                                    <strong>{{ $loop->iteration }}. {{ $criterion->name }}</strong>
                                                                    @if ($criterion->description)
                                                                        <small>{{ $criterion->description }}</small>
                                                                    @endif
                                                                </th>
                                                                <td class="eoi-result-cell">
                                                                    <span class="eoi-outcome eoi-outcome--{{ $criterionOutcome['code'] }}">
                                                                        {{ $criterionOutcome['label'] }}
                                                                    </span>
                                                                    <span class="eoi-result-counts">
                                                                        Q {{ $criterionRow['counts']['qualified'] }}
                                                                        <span aria-hidden="true">&middot;</span>
                                                                        AQ {{ $criterionRow['counts']['average_qualified'] }}
                                                                        <span aria-hidden="true">&middot;</span>
                                                                        NQ {{ $criterionRow['counts']['not_qualified'] }}
                                                                    </span>
                                                                </td>
                                                                @foreach ($members as $member)
                                                                    @php
                                                                        $assessment = $criterionRow['assessments']->firstWhere('member_key', $member['key']);
                                                                        $decisionClass = $assessment
                                                                            ? match ($assessment['decision']) {
                                                                                2 => 'qualified',
                                                                                1 => 'average',
                                                                                default => 'not-qualified',
                                                                            }
                                                                            : 'pending';
                                                                        $decisionShort = $assessment
                                                                            ? match ($assessment['decision']) {
                                                                                2 => 'Q',
                                                                                1 => 'AQ',
                                                                                default => 'NQ',
                                                                            }
                                                                            : null;
                                                                    @endphp
                                                                    <td class="eoi-assessment-cell">
                                                                        @if ($assessment)
                                                                            <span class="eoi-decision eoi-decision--{{ $decisionClass }}">
                                                                                <b>{{ $decisionShort }}</b>
                                                                                {{ $assessment['label'] }}
                                                                            </span>
                                                                            @if ($assessment['comment'] !== '')
                                                                                <p>{{ $assessment['comment'] }}</p>
                                                                            @else
                                                                                <small class="eoi-no-comment">No evaluator comment</small>
                                                                            @endif
                                                                        @else
                                                                            <span class="eoi-decision eoi-decision--pending">
                                                                                <i class="feather-clock" aria-hidden="true"></i>
                                                                                Awaiting decision
                                                                            </span>
                                                                            <small class="eoi-no-comment">
                                                                                {{ $member['submitted'] ? 'Decision not recorded' : 'Evaluation not submitted' }}
                                                                            </small>
                                                                        @endif
                                                                    </td>
                                                                @endforeach
                                                            </tr>
                                                        @empty
                                                            <tr>
                                                                <td colspan="{{ 2 + $members->count() }}">
                                                                    <div class="eoi-inline-empty">
                                                                        <i class="feather-inbox" aria-hidden="true"></i>
                                                                        No criteria were found for this evaluation template.
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </section>
                                    </article>
                                @empty
                                    <div class="eoi-inline-empty eoi-inline-empty--large">
                                        <i class="feather-clipboard" aria-hidden="true"></i>
                                        <strong>No detailed evaluation records yet</strong>
                                        <span>Panel templates and evaluator decisions will appear here as they are assigned.</span>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </details>
                @empty
                    <div class="eoi-empty-state">
                        <span><i class="feather-users" aria-hidden="true"></i></span>
                        <h6>No applicants are available</h6>
                        <p>Applicant qualification results will appear after submissions enter the EOI workflow.</p>
                    </div>
                @endforelse
            </div>

            <div class="eoi-empty-state eoi-filter-empty" id="eoiNoResults" hidden>
                <span><i class="feather-search" aria-hidden="true"></i></span>
                <h6>No applicants match these filters</h6>
                <p>Try a different name, outcome, or panel status.</p>
                <button type="button" class="btn btn-outline-primary btn-sm" id="eoiNoResultsClear">Clear filters</button>
            </div>
        </section>

        <footer class="eoi-report-footer">
            <div>
                <i class="feather-info" aria-hidden="true"></i>
                <span>
                    <strong>Decision key:</strong> Q = Qualified, AQ = Average Qualified, NQ = Not Qualified.
                </span>
            </div>
            <div>
                <i class="feather-file-text" aria-hidden="true"></i>
                <span>{{ number_format($stats['submitted_evaluations']) }} submitted evaluator report(s)</span>
            </div>
        </footer>
    </main>
@endsection

@push('styles')
    <style>
        .eoi-report {
            --eoi-ink: #172033;
            --eoi-muted: #667085;
            --eoi-line: #dce3ec;
            --eoi-soft: #f6f8fb;
            --eoi-blue: #2563eb;
            --eoi-teal: #0f766e;
            --eoi-green: #15803d;
            --eoi-green-soft: #ecfdf3;
            --eoi-amber: #b45309;
            --eoi-amber-soft: #fffbeb;
            --eoi-red: #b42318;
            --eoi-red-soft: #fff1f0;
            --eoi-slate: #475467;
            color: var(--eoi-ink);
            padding-bottom: 28px;
        }

        .eoi-page-kicker {
            color: rgba(255, 255, 255, .76);
            display: block;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .13em;
            margin-bottom: 3px;
            text-transform: uppercase;
        }

        .eoi-page-header p {
            color: rgba(255, 255, 255, .84) !important;
            font-size: 13px;
        }

        .eoi-header-separator {
            margin: 0 7px;
            opacity: .55;
        }

        .eoi-header-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .eoi-eyebrow {
            color: var(--eoi-muted);
            display: block;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .09em;
            text-transform: uppercase;
        }

        .eoi-rule-card {
            align-items: center;
            background: linear-gradient(135deg, #172033 0%, #263650 100%);
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: 14px;
            box-shadow: 0 14px 28px rgba(15, 23, 42, .12);
            color: #fff;
            display: grid;
            gap: 18px;
            grid-template-columns: auto minmax(220px, .85fr) minmax(480px, 1.6fr);
            margin-bottom: 16px;
            overflow: hidden;
            padding: 20px;
            position: relative;
        }

        .eoi-rule-card::after {
            background: #14b8a6;
            border-radius: 999px;
            content: '';
            filter: blur(28px);
            height: 100px;
            opacity: .16;
            position: absolute;
            right: -25px;
            top: -40px;
            width: 180px;
        }

        .eoi-rule-icon {
            align-items: center;
            background: rgba(20, 184, 166, .14);
            border: 1px solid rgba(94, 234, 212, .22);
            border-radius: 12px;
            color: #5eead4;
            display: inline-flex;
            font-size: 25px;
            height: 54px;
            justify-content: center;
            width: 54px;
        }

        .eoi-rule-copy {
            min-width: 0;
        }

        .eoi-rule-copy .eoi-eyebrow {
            color: #5eead4;
        }

        .eoi-rule-copy h5 {
            color: #fff;
            font-size: 17px;
            font-weight: 800;
            margin: 5px 0 6px;
        }

        .eoi-rule-copy p {
            color: #cbd5e1;
            font-size: 12px;
            line-height: 1.55;
            margin: 0;
        }

        .eoi-rule-copy p strong {
            color: #fff;
        }

        .eoi-rule-path {
            display: grid;
            gap: 8px;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            position: relative;
            z-index: 1;
        }

        .eoi-rule-step {
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .1);
            border-radius: 9px;
            min-width: 0;
            padding: 11px 12px;
        }

        .eoi-rule-step span,
        .eoi-rule-step small {
            color: #aab8ca;
            display: block;
            font-size: 9px;
            line-height: 1.35;
        }

        .eoi-rule-step strong {
            display: block;
            font-size: 12px;
            margin: 4px 0;
        }

        .eoi-rule-step--success { border-top: 3px solid #4ade80; }
        .eoi-rule-step--warning { border-top: 3px solid #fbbf24; }
        .eoi-rule-step--danger { border-top: 3px solid #fb7185; }

        .eoi-kpi-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            margin-bottom: 16px;
        }

        .eoi-kpi {
            align-items: flex-start;
            background: #fff;
            border: 1px solid var(--eoi-line);
            border-radius: 11px;
            box-shadow: 0 8px 20px rgba(15, 23, 42, .04);
            display: flex;
            gap: 10px;
            min-height: 116px;
            padding: 15px 13px;
            position: relative;
        }

        .eoi-kpi::after {
            background: var(--kpi-color, var(--eoi-blue));
            border-radius: 0 0 8px 8px;
            bottom: 0;
            content: '';
            height: 3px;
            left: 14px;
            position: absolute;
            right: 14px;
        }

        .eoi-kpi-icon {
            align-items: center;
            background: var(--kpi-soft, #eff6ff);
            border-radius: 8px;
            color: var(--kpi-color, var(--eoi-blue));
            display: inline-flex;
            flex: 0 0 32px;
            height: 32px;
            justify-content: center;
        }

        .eoi-kpi > div {
            min-width: 0;
        }

        .eoi-kpi > div > span {
            color: var(--eoi-muted);
            display: block;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .04em;
            line-height: 1.3;
            text-transform: uppercase;
        }

        .eoi-kpi strong {
            color: var(--eoi-ink);
            display: block;
            font-size: 24px;
            line-height: 1;
            margin: 7px 0 5px;
        }

        .eoi-kpi small {
            color: #98a2b3;
            display: block;
            font-size: 9px;
            line-height: 1.3;
        }

        .eoi-kpi--total { --kpi-color: #2563eb; --kpi-soft: #eff6ff; }
        .eoi-kpi--advance { --kpi-color: #0f766e; --kpi-soft: #ecfdf5; }
        .eoi-kpi--qualified { --kpi-color: #15803d; --kpi-soft: #f0fdf4; }
        .eoi-kpi--average { --kpi-color: #b45309; --kpi-soft: #fffbeb; }
        .eoi-kpi--not-qualified { --kpi-color: #b42318; --kpi-soft: #fff1f0; }
        .eoi-kpi--pending { --kpi-color: #64748b; --kpi-soft: #f1f5f9; }

        .eoi-applicant-panel {
            background: #fff;
            border: 1px solid var(--eoi-line);
            border-radius: 14px;
            box-shadow: 0 14px 30px rgba(15, 23, 42, .05);
            overflow: hidden;
        }

        .eoi-panel-heading {
            align-items: flex-start;
            border-bottom: 1px solid var(--eoi-line);
            display: flex;
            gap: 18px;
            justify-content: space-between;
            padding: 18px 20px;
        }

        .eoi-panel-heading h5 {
            color: var(--eoi-ink);
            font-size: 18px;
            font-weight: 800;
            margin: 4px 0;
        }

        .eoi-panel-heading p {
            color: var(--eoi-muted);
            font-size: 12px;
            margin: 0;
        }

        .eoi-generated-at {
            align-items: center;
            background: var(--eoi-soft);
            border: 1px solid var(--eoi-line);
            border-radius: 8px;
            display: grid;
            flex: 0 0 auto;
            gap: 0 8px;
            grid-template-columns: auto 1fr;
            padding: 8px 11px;
        }

        .eoi-generated-at i {
            color: var(--eoi-teal);
            grid-row: 1 / 3;
        }

        .eoi-generated-at span {
            color: var(--eoi-muted);
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .eoi-generated-at strong {
            color: var(--eoi-ink);
            font-size: 11px;
        }

        .eoi-filter-bar {
            align-items: end;
            background: #f8fafc;
            border-bottom: 1px solid var(--eoi-line);
            display: grid;
            gap: 10px;
            grid-template-columns: minmax(220px, 1.3fr) minmax(170px, .75fr) minmax(170px, .75fr) auto;
            padding: 14px 20px;
        }

        .eoi-filter-bar label {
            color: var(--eoi-slate);
            display: block;
            font-size: 10px;
            font-weight: 800;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .eoi-filter-bar .form-control {
            border-color: #cbd5e1;
            border-radius: 7px;
            color: var(--eoi-ink);
            font-size: 12px;
            height: 38px;
        }

        .eoi-filter-bar .form-control:focus {
            border-color: var(--eoi-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .1);
        }

        .eoi-input-wrap {
            position: relative;
        }

        .eoi-input-wrap i {
            color: #98a2b3;
            left: 12px;
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
        }

        .eoi-input-wrap input {
            padding-left: 35px;
        }

        .eoi-filter-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .eoi-filter-actions .btn {
            min-height: 38px;
            white-space: nowrap;
        }

        .eoi-list-status {
            background: #fff;
            border-bottom: 1px solid var(--eoi-line);
            color: var(--eoi-muted);
            font-size: 10px;
            font-weight: 700;
            padding: 8px 20px;
            text-align: right;
        }

        .eoi-applicant-list {
            background: var(--eoi-soft);
            display: grid;
            gap: 10px;
            padding: 14px;
        }

        .eoi-applicant[hidden] {
            display: none !important;
        }

        .eoi-applicant {
            background: #fff;
            border: 1px solid var(--eoi-line);
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, .035);
            overflow: hidden;
        }

        .eoi-applicant[open] {
            border-color: #b8c7d9;
            box-shadow: 0 12px 28px rgba(15, 23, 42, .07);
        }

        .eoi-applicant > summary {
            align-items: center;
            cursor: pointer;
            display: grid;
            gap: 16px;
            grid-template-columns: minmax(230px, 1.25fr) minmax(180px, .8fr) auto minmax(175px, .75fr) auto;
            list-style: none;
            min-height: 88px;
            padding: 13px 15px;
            transition: background-color .2s ease;
        }

        .eoi-applicant > summary::-webkit-details-marker {
            display: none;
        }

        .eoi-applicant > summary:hover {
            background: #fbfcfe;
        }

        .eoi-applicant > summary:focus-visible {
            box-shadow: inset 0 0 0 3px rgba(37, 99, 235, .22);
            outline: none;
        }

        .eoi-applicant[open] > summary {
            background: #fbfcfe;
            border-bottom: 1px solid var(--eoi-line);
        }

        .eoi-applicant-identity {
            align-items: center;
            display: flex;
            gap: 11px;
            min-width: 0;
        }

        .eoi-applicant-avatar {
            align-items: center;
            background: #eef6ff;
            border: 1px solid #dbeafe;
            border-radius: 9px;
            color: var(--eoi-blue);
            display: inline-flex;
            flex: 0 0 38px;
            height: 38px;
            justify-content: center;
        }

        .eoi-applicant-identity > span:last-child {
            min-width: 0;
        }

        .eoi-applicant-identity strong {
            color: var(--eoi-ink);
            display: block;
            font-size: 13px;
            line-height: 1.35;
        }

        .eoi-applicant-identity small {
            color: var(--eoi-muted);
            display: block;
            font-size: 10px;
            line-height: 1.45;
            margin-top: 3px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .eoi-panel-progress-label {
            align-items: baseline;
            display: flex;
            gap: 8px;
            justify-content: space-between;
        }

        .eoi-panel-progress-label span,
        .eoi-panel-progress small {
            color: var(--eoi-muted);
            font-size: 9px;
        }

        .eoi-panel-progress-label strong {
            color: var(--eoi-ink);
            font-size: 10px;
        }

        .eoi-progress-track {
            background: #e8edf3;
            border-radius: 999px;
            display: block;
            height: 6px;
            margin: 6px 0 5px;
            overflow: hidden;
        }

        .eoi-progress-track > span {
            background: linear-gradient(90deg, var(--eoi-blue), #14b8a6);
            border-radius: inherit;
            display: block;
            height: 100%;
            min-width: 0;
        }

        .eoi-panel-progress small {
            align-items: center;
            display: flex;
            gap: 7px;
            justify-content: space-between;
        }

        .eoi-panel-label {
            border-radius: 999px;
            font-size: 8px;
            font-weight: 800;
            padding: 2px 6px;
            text-transform: uppercase;
        }

        .eoi-panel-label--complete {
            background: var(--eoi-green-soft);
            color: var(--eoi-green);
        }

        .eoi-panel-label--incomplete {
            background: #f1f5f9;
            color: #64748b;
        }

        .eoi-decision-totals {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            justify-content: center;
        }

        .eoi-count {
            border: 1px solid transparent;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 700;
            padding: 5px 7px;
            white-space: nowrap;
        }

        .eoi-count b {
            font-size: 8px;
            letter-spacing: .04em;
        }

        .eoi-count--qualified { background: var(--eoi-green-soft); border-color: #bbf7d0; color: var(--eoi-green); }
        .eoi-count--average { background: var(--eoi-amber-soft); border-color: #fde68a; color: var(--eoi-amber); }
        .eoi-count--not-qualified { background: var(--eoi-red-soft); border-color: #fecaca; color: var(--eoi-red); }

        .eoi-applicant-outcome {
            align-items: flex-end;
            display: flex;
            flex-direction: column;
            gap: 5px;
            text-align: right;
        }

        .eoi-applicant-outcome small {
            color: var(--eoi-muted);
            font-size: 9px;
        }

        .eoi-applicant-outcome small strong {
            color: var(--eoi-ink);
        }

        .eoi-outcome {
            align-items: center;
            border: 1px solid transparent;
            border-radius: 999px;
            display: inline-flex;
            font-size: 9px;
            font-weight: 800;
            gap: 5px;
            line-height: 1.2;
            padding: 5px 8px;
            white-space: nowrap;
        }

        .eoi-outcome--fully_qualified { background: var(--eoi-green-soft); border-color: #bbf7d0; color: var(--eoi-green); }
        .eoi-outcome--average_qualified { background: var(--eoi-amber-soft); border-color: #fde68a; color: var(--eoi-amber); }
        .eoi-outcome--not_qualified { background: var(--eoi-red-soft); border-color: #fecaca; color: var(--eoi-red); }
        .eoi-outcome--pending { background: #f1f5f9; border-color: #dbe3ec; color: #64748b; }

        .eoi-summary-chevron {
            align-items: center;
            background: var(--eoi-soft);
            border: 1px solid var(--eoi-line);
            border-radius: 7px;
            color: var(--eoi-slate);
            display: inline-flex;
            height: 29px;
            justify-content: center;
            transition: transform .2s ease;
            width: 29px;
        }

        .eoi-applicant[open] .eoi-summary-chevron {
            transform: rotate(180deg);
        }

        .eoi-applicant-detail {
            background: #fff;
            padding: 16px;
        }

        .eoi-determination {
            align-items: center;
            background: var(--determination-soft, #f8fafc);
            border: 1px solid var(--determination-line, var(--eoi-line));
            border-left: 4px solid var(--determination-color, #64748b);
            border-radius: 9px;
            display: grid;
            gap: 12px;
            grid-template-columns: auto 1fr auto;
            margin-bottom: 12px;
            padding: 13px 14px;
        }

        .eoi-determination--fully_qualified { --determination-color: var(--eoi-green); --determination-soft: var(--eoi-green-soft); --determination-line: #bbf7d0; }
        .eoi-determination--average_qualified { --determination-color: var(--eoi-amber); --determination-soft: var(--eoi-amber-soft); --determination-line: #fde68a; }
        .eoi-determination--not_qualified { --determination-color: var(--eoi-red); --determination-soft: var(--eoi-red-soft); --determination-line: #fecaca; }

        .eoi-determination-icon {
            align-items: center;
            background: #fff;
            border: 1px solid var(--determination-line, var(--eoi-line));
            border-radius: 8px;
            color: var(--determination-color, #64748b);
            display: inline-flex;
            height: 36px;
            justify-content: center;
            width: 36px;
        }

        .eoi-determination h6 {
            color: var(--eoi-ink);
            font-size: 13px;
            font-weight: 800;
            margin: 3px 0;
        }

        .eoi-determination p {
            color: var(--eoi-slate);
            font-size: 10px;
            line-height: 1.45;
            margin: 0;
        }

        .eoi-determination-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            justify-content: flex-end;
        }

        .eoi-determination-meta span {
            background: rgba(255, 255, 255, .75);
            border: 1px solid var(--determination-line, var(--eoi-line));
            border-radius: 999px;
            color: var(--eoi-slate);
            font-size: 9px;
            font-weight: 700;
            padding: 4px 7px;
        }

        .eoi-detail-stats {
            display: grid;
            gap: 8px;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            margin-bottom: 14px;
        }

        .eoi-detail-stats > div {
            background: var(--eoi-soft);
            border: 1px solid var(--eoi-line);
            border-radius: 8px;
            padding: 9px 10px;
        }

        .eoi-detail-stats span {
            color: var(--eoi-muted);
            display: block;
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .eoi-detail-stats strong {
            color: var(--eoi-ink);
            display: block;
            font-size: 12px;
            margin-top: 4px;
        }

        .eoi-text-warning { color: var(--eoi-amber) !important; }

        .eoi-template-stack {
            display: grid;
            gap: 14px;
        }

        .eoi-template-card {
            border: 1px solid var(--eoi-line);
            border-radius: 10px;
            overflow: hidden;
        }

        .eoi-template-header {
            align-items: flex-start;
            background: linear-gradient(110deg, #f8fafc, #f0f7f7);
            border-bottom: 1px solid var(--eoi-line);
            display: flex;
            gap: 15px;
            justify-content: space-between;
            padding: 14px 16px;
        }

        .eoi-template-header h6 {
            align-items: center;
            color: var(--eoi-ink);
            display: flex;
            font-size: 14px;
            font-weight: 800;
            gap: 7px;
            margin: 4px 0 0;
        }

        .eoi-template-header h6 i {
            color: var(--eoi-teal);
        }

        .eoi-template-header p {
            color: var(--eoi-muted);
            font-size: 10px;
            line-height: 1.45;
            margin: 5px 0 0;
            max-width: 700px;
        }

        .eoi-template-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            justify-content: flex-end;
        }

        .eoi-template-meta span {
            background: #fff;
            border: 1px solid var(--eoi-line);
            border-radius: 999px;
            color: var(--eoi-muted);
            font-size: 9px;
            padding: 5px 8px;
            white-space: nowrap;
        }

        .eoi-template-meta span strong {
            color: var(--eoi-ink);
        }

        .eoi-template-meta span.is-complete {
            background: var(--eoi-green-soft);
            border-color: #bbf7d0;
            color: var(--eoi-green);
        }

        .eoi-template-meta span.is-complete strong {
            color: var(--eoi-green);
        }

        .eoi-member-section,
        .eoi-matrix-section {
            padding: 14px 16px;
        }

        .eoi-member-section {
            border-bottom: 1px solid var(--eoi-line);
        }

        .eoi-subsection-title {
            align-items: flex-end;
            display: flex;
            gap: 15px;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .eoi-subsection-title h6 {
            color: var(--eoi-ink);
            font-size: 12px;
            font-weight: 800;
            margin: 3px 0 0;
        }

        .eoi-subsection-title > span {
            color: var(--eoi-muted);
            font-size: 9px;
            font-weight: 700;
        }

        .eoi-member-grid {
            display: grid;
            gap: 8px;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        }

        .eoi-member {
            background: #fbfcfe;
            border: 1px solid var(--eoi-line);
            border-left: 3px solid #94a3b8;
            border-radius: 8px;
            padding: 10px;
        }

        .eoi-member--complete { border-left-color: var(--eoi-green); }
        .eoi-member--incomplete { border-left-color: var(--eoi-amber); }
        .eoi-member--pending { border-left-color: #94a3b8; }

        .eoi-member-head {
            align-items: center;
            display: grid;
            gap: 8px;
            grid-template-columns: auto 1fr auto;
        }

        .eoi-member-icon {
            align-items: center;
            background: #eef2f6;
            border-radius: 7px;
            color: var(--eoi-slate);
            display: inline-flex;
            height: 31px;
            justify-content: center;
            width: 31px;
        }

        .eoi-member--complete .eoi-member-icon {
            background: var(--eoi-green-soft);
            color: var(--eoi-green);
        }

        .eoi-member-head > div {
            min-width: 0;
        }

        .eoi-member-head strong {
            color: var(--eoi-ink);
            display: block;
            font-size: 11px;
        }

        .eoi-member-head small {
            color: var(--eoi-muted);
            display: block;
            font-size: 8px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .eoi-member-status {
            background: #eef2f6;
            border-radius: 999px;
            color: #64748b;
            font-size: 8px;
            font-weight: 800;
            padding: 4px 7px;
            white-space: nowrap;
        }

        .eoi-member--complete .eoi-member-status { background: var(--eoi-green-soft); color: var(--eoi-green); }
        .eoi-member--incomplete .eoi-member-status { background: var(--eoi-amber-soft); color: var(--eoi-amber); }

        .eoi-member-foot {
            align-items: center;
            border-top: 1px solid #e8edf3;
            display: flex;
            gap: 8px;
            justify-content: space-between;
            margin-top: 8px;
            padding-top: 7px;
        }

        .eoi-member-foot > span:first-child {
            color: var(--eoi-muted);
            font-size: 8px;
        }

        .eoi-member-counts {
            display: flex;
            gap: 4px;
        }

        .eoi-member-counts b {
            border-radius: 4px;
            font-size: 8px;
            padding: 2px 4px;
        }

        .eoi-member-counts .is-q { background: var(--eoi-green-soft); color: var(--eoi-green); }
        .eoi-member-counts .is-aq { background: var(--eoi-amber-soft); color: var(--eoi-amber); }
        .eoi-member-counts .is-nq { background: var(--eoi-red-soft); color: var(--eoi-red); }

        .eoi-import-note {
            color: var(--eoi-muted);
            display: block;
            font-size: 8px;
            margin-top: 6px;
        }

        .eoi-matrix-legend {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 4px 6px;
        }

        .eoi-matrix-legend > span:not(.eoi-decision) {
            color: var(--eoi-muted);
            font-size: 8px;
            margin-right: 4px;
        }

        .eoi-matrix-wrap {
            border: 1px solid var(--eoi-line);
            border-radius: 8px;
            max-height: 620px;
        }

        .eoi-matrix-table {
            border-collapse: separate;
            border-spacing: 0;
            margin: 0;
            min-width: 850px;
        }

        .eoi-matrix-table thead th {
            background: #172033;
            border-color: #344054;
            color: #fff;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .03em;
            padding: 9px 10px;
            position: sticky;
            text-transform: uppercase;
            top: 0;
            vertical-align: bottom;
            z-index: 2;
        }

        .eoi-matrix-table thead th:first-child {
            left: 0;
            z-index: 3;
        }

        .eoi-matrix-table thead th small {
            color: #aab8ca;
            display: block;
            font-size: 7px;
            font-weight: 600;
            letter-spacing: 0;
            margin-top: 3px;
            text-transform: none;
        }

        .eoi-criterion-column { min-width: 230px; width: 26%; }
        .eoi-result-column { min-width: 145px; width: 15%; }
        .eoi-evaluator-column { min-width: 190px; }

        .eoi-matrix-table tbody th,
        .eoi-matrix-table tbody td {
            border-color: #e5eaf0;
            padding: 10px;
            vertical-align: top;
        }

        .eoi-matrix-table tbody tr:nth-child(even) > * {
            background: #fbfcfe;
        }

        .eoi-matrix-table tbody tr:hover > * {
            background: #f5f9ff;
        }

        .eoi-criterion-cell {
            background: #fff;
            left: 0;
            position: sticky;
            z-index: 1;
        }

        .eoi-criterion-section {
            color: var(--eoi-teal);
            display: block;
            font-size: 8px;
            font-weight: 800;
            letter-spacing: .04em;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .eoi-criterion-cell strong {
            color: var(--eoi-ink);
            display: block;
            font-size: 10px;
            line-height: 1.4;
        }

        .eoi-criterion-cell small {
            color: var(--eoi-muted);
            display: block;
            font-size: 8px;
            font-weight: 400;
            line-height: 1.45;
            margin-top: 4px;
        }

        .eoi-result-cell .eoi-outcome {
            white-space: normal;
        }

        .eoi-result-counts {
            color: var(--eoi-muted);
            display: block;
            font-size: 8px;
            line-height: 1.5;
            margin-top: 6px;
        }

        .eoi-decision {
            align-items: center;
            border: 1px solid transparent;
            border-radius: 5px;
            display: inline-flex;
            font-size: 8px;
            font-weight: 700;
            gap: 5px;
            line-height: 1.2;
            padding: 4px 6px;
        }

        .eoi-decision b {
            font-size: 8px;
        }

        .eoi-decision--qualified { background: var(--eoi-green-soft); border-color: #bbf7d0; color: var(--eoi-green); }
        .eoi-decision--average { background: var(--eoi-amber-soft); border-color: #fde68a; color: var(--eoi-amber); }
        .eoi-decision--not-qualified { background: var(--eoi-red-soft); border-color: #fecaca; color: var(--eoi-red); }
        .eoi-decision--pending { background: #f1f5f9; border-color: #dbe3ec; color: #64748b; }

        .eoi-assessment-cell p {
            color: var(--eoi-slate);
            font-size: 9px;
            line-height: 1.5;
            margin: 7px 0 0;
            white-space: pre-line;
        }

        .eoi-no-comment {
            color: #98a2b3;
            display: block;
            font-size: 8px;
            font-style: italic;
            margin-top: 7px;
        }

        .eoi-inline-empty,
        .eoi-empty-state {
            align-items: center;
            color: var(--eoi-muted);
            display: flex;
            font-size: 10px;
            gap: 7px;
            justify-content: center;
            padding: 18px;
            text-align: center;
        }

        .eoi-inline-empty--large {
            flex-direction: column;
            min-height: 130px;
        }

        .eoi-inline-empty--large i {
            color: #94a3b8;
            font-size: 22px;
        }

        .eoi-inline-empty--large strong {
            color: var(--eoi-ink);
            font-size: 12px;
        }

        .eoi-filter-empty[hidden] {
            display: none !important;
        }

        .eoi-empty-state {
            flex-direction: column;
            min-height: 220px;
        }

        .eoi-empty-state > span {
            align-items: center;
            background: #eef2f6;
            border-radius: 999px;
            color: #64748b;
            display: inline-flex;
            font-size: 23px;
            height: 52px;
            justify-content: center;
            width: 52px;
        }

        .eoi-empty-state h6 {
            color: var(--eoi-ink);
            font-size: 14px;
            font-weight: 800;
            margin: 3px 0 0;
        }

        .eoi-empty-state p {
            margin: 0;
        }

        .eoi-report-footer {
            align-items: center;
            color: var(--eoi-muted);
            display: flex;
            font-size: 10px;
            gap: 12px;
            justify-content: space-between;
            padding: 12px 4px 0;
        }

        .eoi-report-footer > div {
            align-items: center;
            display: flex;
            gap: 6px;
        }

        .eoi-report-footer i {
            color: var(--eoi-teal);
        }

        @media (max-width: 1399.98px) {
            .eoi-kpi-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .eoi-kpi {
                min-height: 100px;
            }

            .eoi-filter-bar {
                grid-template-columns: minmax(220px, 1.2fr) repeat(2, minmax(160px, .7fr));
            }

            .eoi-filter-actions {
                grid-column: 1 / -1;
            }

            .eoi-applicant > summary {
                grid-template-columns: minmax(220px, 1.2fr) minmax(175px, .8fr) auto minmax(165px, .75fr) auto;
                gap: 10px;
            }
        }

        @media (max-width: 1199.98px) {
            .eoi-rule-card {
                grid-template-columns: auto 1fr;
            }

            .eoi-rule-path {
                grid-column: 1 / -1;
            }

            .eoi-applicant > summary {
                grid-template-columns: minmax(220px, 1fr) minmax(170px, .75fr) auto;
            }

            .eoi-decision-totals {
                justify-content: flex-start;
            }

            .eoi-applicant-outcome {
                align-items: flex-start;
                text-align: left;
            }

            .eoi-summary-chevron {
                grid-column: 3;
                grid-row: 1 / 3;
                justify-self: end;
            }

            .eoi-detail-stats {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .eoi-header-actions,
            .eoi-header-actions .btn {
                width: 100%;
            }

            .eoi-header-actions .btn {
                flex: 1 1 0;
            }

            .eoi-rule-card {
                align-items: flex-start;
                grid-template-columns: auto 1fr;
                padding: 16px;
            }

            .eoi-rule-path {
                grid-template-columns: 1fr;
            }

            .eoi-kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .eoi-panel-heading,
            .eoi-template-header,
            .eoi-subsection-title,
            .eoi-report-footer {
                align-items: flex-start;
                flex-direction: column;
            }

            .eoi-generated-at {
                align-self: stretch;
            }

            .eoi-filter-bar {
                grid-template-columns: 1fr;
            }

            .eoi-filter-actions {
                grid-column: auto;
            }

            .eoi-filter-actions .btn {
                flex: 1 1 130px;
            }

            .eoi-list-status {
                text-align: left;
            }

            .eoi-applicant > summary {
                grid-template-columns: 1fr auto;
                padding: 14px;
            }

            .eoi-panel-progress,
            .eoi-decision-totals,
            .eoi-applicant-outcome {
                grid-column: 1 / -1;
            }

            .eoi-summary-chevron {
                grid-column: 2;
                grid-row: 1;
            }

            .eoi-determination {
                align-items: flex-start;
                grid-template-columns: auto 1fr;
            }

            .eoi-determination-meta {
                grid-column: 1 / -1;
                justify-content: flex-start;
            }

            .eoi-template-meta {
                justify-content: flex-start;
            }

            .eoi-matrix-legend {
                margin-top: 5px;
            }
        }

        @media (max-width: 479.98px) {
            .eoi-kpi-grid,
            .eoi-detail-stats {
                grid-template-columns: 1fr;
            }

            .eoi-kpi {
                min-height: 86px;
            }

            .eoi-applicant-list,
            .eoi-applicant-detail {
                padding: 10px;
            }

            .eoi-member-head {
                grid-template-columns: auto 1fr;
            }

            .eoi-member-status {
                grid-column: 1 / -1;
                justify-self: start;
            }

            .eoi-member-foot {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        @media print {
            .eoi-header-actions,
            .eoi-filter-bar,
            .eoi-list-status,
            .eoi-summary-chevron {
                display: none !important;
            }

            .eoi-report,
            .eoi-applicant-panel,
            .eoi-applicant,
            .eoi-template-card {
                box-shadow: none !important;
            }

            .eoi-applicant > .eoi-applicant-detail {
                display: block !important;
            }

            .eoi-applicant-list {
                padding: 0;
            }

            .eoi-matrix-wrap {
                max-height: none;
                overflow: visible !important;
            }

            .eoi-matrix-table thead th,
            .eoi-criterion-cell {
                position: static;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .eoi-applicant > summary,
            .eoi-summary-chevron {
                transition: none;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('eoiApplicantSearch');
            const outcomeFilter = document.getElementById('eoiOutcomeFilter');
            const panelFilter = document.getElementById('eoiPanelFilter');
            const resultCount = document.getElementById('eoiResultCount');
            const noResults = document.getElementById('eoiNoResults');
            const expandButton = document.getElementById('eoiExpandAll');
            const collapseButton = document.getElementById('eoiCollapseAll');
            const clearButton = document.getElementById('eoiClearFilters');
            const noResultsClearButton = document.getElementById('eoiNoResultsClear');
            const applicantList = document.getElementById('eoiApplicantList');

            if (!searchInput || !outcomeFilter || !panelFilter || !applicantList) {
                return;
            }

            const applicantCards = Array.from(applicantList.querySelectorAll('.eoi-applicant'));
            const totalApplicants = applicantCards.length;

            const normalize = (value) => String(value || '').trim().toLocaleLowerCase();

            const applyFilters = () => {
                const query = normalize(searchInput.value);
                const outcome = outcomeFilter.value;
                const panel = panelFilter.value;
                let visibleApplicants = 0;

                applicantCards.forEach((card) => {
                    const matchesSearch = !query || normalize(card.dataset.search).includes(query);
                    const matchesOutcome = outcome === 'all' || card.dataset.outcome === outcome;
                    const matchesPanel = panel === 'all' || card.dataset.panel === panel;
                    const isVisible = matchesSearch && matchesOutcome && matchesPanel;

                    card.hidden = !isVisible;
                    if (isVisible) {
                        visibleApplicants += 1;
                    } else {
                        card.open = false;
                    }
                });

                if (resultCount) {
                    resultCount.textContent = `Showing ${visibleApplicants} of ${totalApplicants} applicants`;
                }

                if (noResults) {
                    noResults.hidden = visibleApplicants !== 0 || totalApplicants === 0;
                }

                applicantList.hidden = visibleApplicants === 0 && totalApplicants > 0;
            };

            const clearFilters = () => {
                searchInput.value = '';
                outcomeFilter.value = 'all';
                panelFilter.value = 'all';
                applyFilters();
                searchInput.focus();
            };

            searchInput.addEventListener('input', applyFilters);
            outcomeFilter.addEventListener('change', applyFilters);
            panelFilter.addEventListener('change', applyFilters);

            expandButton?.addEventListener('click', function () {
                applicantCards.filter((card) => !card.hidden).forEach((card) => {
                    card.open = true;
                });
            });

            collapseButton?.addEventListener('click', function () {
                applicantCards.forEach((card) => {
                    card.open = false;
                });
            });

            clearButton?.addEventListener('click', clearFilters);
            noResultsClearButton?.addEventListener('click', clearFilters);

            applyFilters();
        });
    </script>
@endpush
