@extends('layouts.app')

@section('title', 'Evaluator Assignments | ATTP')

@section('content')
    @php
        $totalAssignments = $procurements->sum(fn ($procurement) => $procurement->evaluationAssignments->count());
        $unassignedProcurements = $procurements
            ->filter(fn ($procurement) => $procurement->evaluationAssignments->isEmpty())
            ->count();
        $openProcurementId = (string) session('open_procurement_id', '');
        $procurementStatusTones = [
            'draft' => 'neutral',
            'published' => 'success',
            'closed' => 'danger',
            'recalled' => 'warning',
            'awarded' => 'violet',
        ];
        $assignmentStatusTones = [
            'assigned' => 'neutral',
            'submitted' => 'success',
            'rework' => 'warning',
        ];
    @endphp

    <div class="nxl-container evaluator-assignment-page">
        <header class="assignment-page-hero">
            <div class="assignment-hero-copy">
                <span class="assignment-hero-eyebrow">Evaluation administration</span>
                <h1>Evaluator Assignments</h1>
                <p>Select a procurement, choose its evaluation form and assign the right evaluator. Existing team placements are listed with both the evaluator's name and email address.</p>
            </div>
            <span class="assignment-hero-total">
                <i class="feather-user-check" aria-hidden="true"></i>
                <strong>{{ number_format($totalAssignments) }}</strong>
                {{ \Illuminate\Support\Str::plural('assignment', $totalAssignments) }}
            </span>
        </header>

        @if (session('success'))
            <div class="assignment-notice assignment-notice--success" role="status">
                <i class="feather-check-circle" aria-hidden="true"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="assignment-notice assignment-notice--danger" role="alert">
                <i class="feather-alert-circle" aria-hidden="true"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="assignment-notice assignment-notice--danger" role="alert">
                <i class="feather-alert-triangle" aria-hidden="true"></i>
                <div>
                    <strong>The assignment could not be saved.</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <section class="assignment-summary" aria-label="Assignment overview">
            <article class="assignment-summary-card assignment-summary-card--blue">
                <span class="assignment-summary-icon"><i class="feather-briefcase" aria-hidden="true"></i></span>
                <div><span>Procurements</span><strong>{{ number_format($procurements->count()) }}</strong><small>Available to manage</small></div>
            </article>
            <article class="assignment-summary-card assignment-summary-card--violet">
                <span class="assignment-summary-icon"><i class="feather-users" aria-hidden="true"></i></span>
                <div><span>Assignments</span><strong>{{ number_format($totalAssignments) }}</strong><small>Evaluator placements</small></div>
            </article>
            <article class="assignment-summary-card assignment-summary-card--green">
                <span class="assignment-summary-icon"><i class="feather-user-check" aria-hidden="true"></i></span>
                <div><span>Eligible evaluators</span><strong>{{ number_format($evaluators->count()) }}</strong><small>Available accounts</small></div>
            </article>
            <article class="assignment-summary-card assignment-summary-card--amber">
                <span class="assignment-summary-icon"><i class="feather-user-x" aria-hidden="true"></i></span>
                <div><span>Unassigned</span><strong>{{ number_format($unassignedProcurements) }}</strong><small>Procurements without a team</small></div>
            </article>
        </section>

        @if ($procurements->isNotEmpty())
            <section class="assignment-directory" aria-labelledby="assignment-directory-title">
                <header class="assignment-directory-heading">
                    <div>
                        <span class="assignment-section-kicker">Team allocation</span>
                        <h2 id="assignment-directory-title">Procurements and evaluation teams</h2>
                        <p>Search for a procurement, then open it to create or review assignments.</p>
                    </div>
                    <span id="assignmentResultCount" class="assignment-result-count" role="status" aria-live="polite"></span>
                </header>

                <div class="assignment-filter-bar">
                    <label class="assignment-search" for="assignmentProcurementSearch">
                        <i class="feather-search" aria-hidden="true"></i>
                        <input type="search" id="assignmentProcurementSearch" class="form-control"
                            placeholder="Search by procurement title or reference" autocomplete="off">
                    </label>
                    <div class="assignment-filter-field">
                        <label for="assignmentCoverageFilter">Team status</label>
                        <select id="assignmentCoverageFilter" class="form-select">
                            <option value="all">All procurements</option>
                            <option value="assigned">Has evaluator assignments</option>
                            <option value="unassigned">No evaluator assigned</option>
                        </select>
                    </div>
                    <button type="button" id="clearAssignmentFilters" class="assignment-clear-button">
                        <i class="feather-rotate-ccw" aria-hidden="true"></i>
                        Clear
                    </button>
                </div>

                <div class="accordion assignment-procurement-list" id="procurementAccordion">
                    @foreach ($procurements as $procurement)
                        @php
                            $procurementPortfolioId = $procurementPortfolioIds[(string) $procurement->id] ?? null;
                            $selectableEvaluations = $procurementPortfolioId
                                ? ($evaluationsByPortfolioId[(string) $procurementPortfolioId] ?? collect())
                                : $evaluations;
                            $contextCollection = collect($assignmentContexts ?? []);
                            $hasAssignmentContext = $contextCollection->has((string) $procurement->id);
                            $assignmentContext = $contextCollection->get((string) $procurement->id, []);
                            $applicationSubmissionIds = collect(data_get($assignmentContext, 'application_submission_ids', []))
                                ->map(fn ($id) => (string) $id)
                                ->unique()
                                ->values();
                            $applicationSubmissions = $hasAssignmentContext
                                ? $procurement->submissions
                                    ->filter(fn ($submission) => $applicationSubmissionIds->contains((string) $submission->id))
                                    ->values()
                                : $procurement->submissions->values();
                            $technicalRound = data_get($assignmentContext, 'technical_round');
                            $technicalCandidates = collect(data_get($assignmentContext, 'technical_candidates', []))
                                ->filter(function ($item) {
                                    $candidate = data_get($item, 'candidate');
                                    $applicant = data_get($item, 'applicant') ?: $candidate?->applicant;

                                    return $candidate
                                        && $applicant
                                        && $candidate->status === \App\Models\EoiTechnicalProposalCandidate::STATUS_QUALIFIED;
                                })
                                ->values();
                            $qualifiedProposalCount = $technicalCandidates->count();
                            $hasTechnicalShortlist = $technicalRound && $qualifiedProposalCount > 0;
                            $technicalStatusCounts = collect(data_get($assignmentContext, 'status_counts', []));
                            $technicalExcludedCount = (int) $technicalStatusCounts
                                ->except([\App\Models\EoiTechnicalProposalCandidate::STATUS_QUALIFIED])
                                ->sum();
                            $assignmentCount = $procurement->evaluationAssignments->count();
                            $submissionCount = $procurement->submissions->count();
                            $isOldForm = (string) old('procurement_id', '') === (string) $procurement->id;
                            $shouldOpen = ($isOldForm && $errors->any()) || $openProcurementId === (string) $procurement->id;
                            $selectedEvaluationId = $isOldForm ? (string) old('evaluation_id', '') : '';
                            $selectedEvaluatorId = $isOldForm ? (string) old('user_id', '') : '';
                            $defaultAssignmentType = $applicationSubmissions->isNotEmpty()
                                ? 'procurement'
                                : ($hasTechnicalShortlist ? 'technical_proposal_procurement' : 'procurement');
                            $selectedAssignmentType = $isOldForm
                                ? old('assignment_type', $defaultAssignmentType)
                                : $defaultAssignmentType;
                            if ((str_starts_with((string) $selectedAssignmentType, 'technical_proposal_') && ! $hasTechnicalShortlist)
                                || (in_array($selectedAssignmentType, ['procurement', 'submission'], true) && $applicationSubmissions->isEmpty())) {
                                $selectedAssignmentType = $defaultAssignmentType;
                            }
                            $selectedSubmissionId = $isOldForm ? (string) old('submission_id', '') : '';
                            $procurementStatus = strtolower((string) ($procurement->status ?: 'unknown'));
                            $procurementStatusTone = $procurementStatusTones[$procurementStatus] ?? 'neutral';
                            $searchText = \Illuminate\Support\Str::lower(trim(
                                $procurement->title.' '.($procurement->reference_no ?? '').' '.$procurementStatus
                            ));
                        @endphp

                        <article class="accordion-item assignment-procurement-card"
                            data-assignment-procurement
                            data-search="{{ $searchText }}"
                            data-coverage="{{ $assignmentCount > 0 ? 'assigned' : 'unassigned' }}">
                            <h3 class="accordion-header" id="heading{{ $procurement->id }}">
                                <button class="accordion-button procurement-accordion-button {{ $shouldOpen ? '' : 'collapsed' }}"
                                    type="button" data-bs-toggle="collapse"
                                    data-bs-target="#collapse{{ $procurement->id }}"
                                    aria-expanded="{{ $shouldOpen ? 'true' : 'false' }}"
                                    aria-controls="collapse{{ $procurement->id }}">
                                    <span class="procurement-heading-icon"><i class="feather-briefcase" aria-hidden="true"></i></span>
                                    <span class="procurement-heading-copy">
                                        <span class="procurement-heading-labels">
                                            <span class="procurement-reference"><i class="feather-hash" aria-hidden="true"></i>{{ $procurement->reference_no ?: 'No reference' }}</span>
                                            <span class="procurement-state procurement-state--{{ $procurementStatusTone }}">{{ \Illuminate\Support\Str::headline($procurementStatus) }}</span>
                                        </span>
                                        <strong>{{ $procurement->title }}</strong>
                                        <small>{{ $selectableEvaluations->count() }} active {{ \Illuminate\Support\Str::plural('evaluation form', $selectableEvaluations->count()) }} available</small>
                                    </span>
                                    <span class="procurement-heading-metrics" aria-hidden="true">
                                        <span><strong>{{ number_format($assignmentCount) }}</strong><small>{{ \Illuminate\Support\Str::plural('assignment', $assignmentCount) }}</small></span>
                                        <span><strong>{{ number_format($submissionCount) }}</strong><small>{{ \Illuminate\Support\Str::plural('application', $submissionCount) }}</small></span>
                                        @if ($technicalRound)
                                            <span class="procurement-heading-metric--shortlist"><strong>{{ number_format($qualifiedProposalCount) }}</strong><small>Proposal shortlist</small></span>
                                        @endif
                                    </span>
                                </button>
                            </h3>

                            <div id="collapse{{ $procurement->id }}"
                                class="accordion-collapse collapse {{ $shouldOpen ? 'show' : '' }}"
                                aria-labelledby="heading{{ $procurement->id }}"
                                data-bs-parent="#procurementAccordion">
                                <div class="accordion-body assignment-procurement-body">
                                    <section class="assignment-builder" aria-labelledby="assignment-form-title-{{ $procurement->id }}">
                                        <header class="assignment-block-heading">
                                            <span><i class="feather-user-plus" aria-hidden="true"></i></span>
                                            <div>
                                                <h4 id="assignment-form-title-{{ $procurement->id }}">Create an evaluator assignment</h4>
                                                <p>Pair a reusable evaluation form with either the original applications or the qualified technical-proposal shortlist.</p>
                                            </div>
                                        </header>

                                        @if ($technicalRound)
                                            <div class="assignment-shortlist-note {{ $hasTechnicalShortlist ? 'assignment-shortlist-note--ready' : 'assignment-shortlist-note--waiting' }}">
                                                <span class="assignment-shortlist-icon" aria-hidden="true">
                                                    <i class="{{ $hasTechnicalShortlist ? 'feather-award' : 'feather-clock' }}"></i>
                                                </span>
                                                <div class="assignment-shortlist-copy">
                                                    <span class="assignment-shortlist-kicker">Second-round proposal review</span>
                                                    <strong>
                                                        {{ $technicalRound->title ?: 'Technical Proposal Round '.$technicalRound->round_number }}
                                                    </strong>
                                                    <p>
                                                        @if ($hasTechnicalShortlist)
                                                            Only applicants marked Qualified after proposal receipt and compliance review can be assigned here. Disqualified and unresolved applicants are excluded.
                                                        @else
                                                            No applicant has completed the proposal-compliance gate as Qualified yet. Technical-proposal assignment will become available when the shortlist is ready.
                                                        @endif
                                                    </p>
                                                </div>
                                                <div class="assignment-shortlist-counts">
                                                    <span class="assignment-shortlist-badge {{ $hasTechnicalShortlist ? 'is-ready' : 'is-waiting' }}">
                                                        <strong>{{ number_format($qualifiedProposalCount) }}</strong>
                                                        qualified
                                                    </span>
                                                    @if ($technicalExcludedCount > 0)
                                                        <span class="assignment-shortlist-excluded">{{ number_format($technicalExcludedCount) }} not eligible</span>
                                                    @endif
                                                    <small>Round {{ $technicalRound->round_number }}</small>
                                                </div>
                                            </div>
                                        @endif

                                        <form method="POST" action="{{ route('eval.assign.store') }}" class="assignment-form-grid"
                                            data-assignment-form>
                                            @csrf
                                            <input type="hidden" name="procurement_id" value="{{ $procurement->id }}">
                                            @if ($technicalRound)
                                                <input type="hidden" name="technical_proposal_round_id" value="{{ $technicalRound->id }}">
                                            @endif

                                            <div class="assignment-field assignment-field--procurement">
                                                <label for="procurement-title-{{ $procurement->id }}">Procurement title</label>
                                                <div class="assignment-readonly-field">
                                                    <i class="feather-briefcase" aria-hidden="true"></i>
                                                    <input type="text" id="procurement-title-{{ $procurement->id }}"
                                                        value="{{ $procurement->title }}" readonly aria-readonly="true"
                                                        title="{{ $procurement->title }}">
                                                </div>
                                                <small class="assignment-field-message">{{ $procurement->reference_no ?: 'No procurement reference' }}</small>
                                            </div>

                                            <div class="assignment-field assignment-field--evaluation">
                                                <label for="evaluation-{{ $procurement->id }}">Evaluation form <span aria-hidden="true">*</span></label>
                                                <select name="evaluation_id" id="evaluation-{{ $procurement->id }}" class="form-select" required
                                                    @disabled($selectableEvaluations->isEmpty())>
                                                    <option value="">Choose an evaluation form</option>
                                                    @foreach ($selectableEvaluations as $evaluation)
                                                        <option value="{{ $evaluation->id }}" data-evaluation-type="{{ $evaluation->type }}"
                                                            @selected($selectedEvaluationId === (string) $evaluation->id)>
                                                            {{ $evaluation->name }} — {{ $evaluation->typeLabel() }}
                                                            @if ($evaluation->portfolio)
                                                                · {{ $evaluation->portfolio->name }}
                                                            @endif
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @if ($selectableEvaluations->isEmpty())
                                                    <small class="assignment-field-message assignment-field-message--danger">No active portfolio evaluation form is available.</small>
                                                @endif
                                            </div>

                                            <div class="assignment-field assignment-field--evaluator">
                                                <label for="evaluator-{{ $procurement->id }}">Evaluator <span aria-hidden="true">*</span></label>
                                                <select name="user_id" id="evaluator-{{ $procurement->id }}" class="form-select" required
                                                    @disabled($evaluators->isEmpty())>
                                                    <option value="">Choose an evaluator</option>
                                                    @foreach ($evaluators as $user)
                                                        <option value="{{ $user->id }}" @selected($selectedEvaluatorId === (string) $user->id)>
                                                            {{ $user->name }} — {{ $user->email ?: 'No email recorded' }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <small class="assignment-field-message">Vendor and think tank accounts are excluded.</small>
                                            </div>

                                            <div class="assignment-field assignment-field--scope">
                                                <label for="assignment-type-{{ $procurement->id }}">Assignment scope <span aria-hidden="true">*</span></label>
                                                <select name="assignment_type" id="assignment-type-{{ $procurement->id }}"
                                                    class="form-select assignment-type" required data-procurement="{{ $procurement->id }}">
                                                    <option value="procurement" @selected($selectedAssignmentType === 'procurement')
                                                        @disabled($applicationSubmissions->isEmpty())>
                                                        All original applications ({{ $applicationSubmissions->count() }})
                                                    </option>
                                                    <option value="submission" @selected($selectedAssignmentType === 'submission')
                                                        @disabled($applicationSubmissions->isEmpty())>
                                                        One original application
                                                    </option>
                                                    @if ($hasTechnicalShortlist)
                                                        <option value="technical_proposal_procurement"
                                                            @selected($selectedAssignmentType === 'technical_proposal_procurement')>
                                                            All qualified proposals ({{ $qualifiedProposalCount }})
                                                        </option>
                                                        <option value="technical_proposal_submission"
                                                            @selected($selectedAssignmentType === 'technical_proposal_submission')>
                                                            One qualified proposal
                                                        </option>
                                                    @endif
                                                </select>
                                                <small class="assignment-field-message" data-assignment-scope-help>
                                                    Choose the original application stage or a proposal shortlist when one is ready.
                                                </small>
                                            </div>

                                            <div class="assignment-field assignment-field--submission submission-select {{ in_array($selectedAssignmentType, ['submission', 'technical_proposal_submission'], true) ? '' : 'd-none' }}"
                                                id="submissionSelect{{ $procurement->id }}">
                                                <label for="submission-{{ $procurement->id }}" data-submission-label>Application <span aria-hidden="true">*</span></label>
                                                <select name="submission_id" id="submission-{{ $procurement->id }}" class="form-select"
                                                    @if (in_array($selectedAssignmentType, ['submission', 'technical_proposal_submission'], true)) required @endif>
                                                    <option value="" data-assignment-placeholder>Choose an application</option>
                                                    <optgroup label="Original applications" data-assignment-target-group="application">
                                                        @foreach ($applicationSubmissions as $submission)
                                                            <option value="{{ $submission->id }}" data-assignment-target="application"
                                                                @selected($selectedAssignmentType === 'submission' && $selectedSubmissionId === (string) $submission->id)>
                                                                {{ $submission->procurement_submission_code ?: 'Application '.$loop->iteration }}
                                                                — {{ $submission->display_name }}
                                                            </option>
                                                        @endforeach
                                                    </optgroup>
                                                    @if ($hasTechnicalShortlist)
                                                        <optgroup label="Qualified technical proposals — Round {{ $technicalRound->round_number }}"
                                                            data-assignment-target-group="technical_proposal">
                                                            @foreach ($technicalCandidates as $technicalItem)
                                                                @php
                                                                    $technicalCandidate = data_get($technicalItem, 'candidate');
                                                                    $technicalApplicant = data_get($technicalItem, 'applicant') ?: $technicalCandidate?->applicant;
                                                                    $latestProposal = data_get($technicalItem, 'latest_submission') ?: $technicalCandidate?->latestSubmission;
                                                                @endphp
                                                                <option value="{{ $technicalApplicant->id }}" data-assignment-target="technical_proposal"
                                                                    data-candidate-id="{{ $technicalCandidate->id }}"
                                                                    @selected($selectedAssignmentType === 'technical_proposal_submission' && $selectedSubmissionId === (string) $technicalApplicant->id)>
                                                                    {{ $technicalApplicant->display_name }}
                                                                    — {{ $technicalApplicant->procurement_submission_code ?: 'Application '.$loop->iteration }}
                                                                    @if ($latestProposal)
                                                                        · Proposal revision {{ $latestProposal->revision_number }}
                                                                    @endif
                                                                </option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endif
                                                </select>
                                                <small class="assignment-field-message" data-submission-help>
                                                    Select one eligible original application.
                                                </small>
                                            </div>

                                            <div class="assignment-form-action">
                                                <button type="submit" class="assignment-primary-button"
                                                    @disabled($selectableEvaluations->isEmpty() || $evaluators->isEmpty() || ($applicationSubmissions->isEmpty() && ! $hasTechnicalShortlist))>
                                                    <i class="feather-user-plus" aria-hidden="true"></i>
                                                    Assign evaluator
                                                </button>
                                            </div>
                                        </form>
                                    </section>

                                    <section class="assignment-registry" aria-labelledby="assignment-list-title-{{ $procurement->id }}">
                                        <header class="assignment-registry-heading">
                                            <div>
                                                <span class="assignment-section-kicker">Current team</span>
                                                <h4 id="assignment-list-title-{{ $procurement->id }}">Assigned evaluators</h4>
                                                <p>Review evaluator identity, evaluation form, scope and progress.</p>
                                            </div>
                                            <span class="assignment-count-badge">{{ number_format($assignmentCount) }} {{ \Illuminate\Support\Str::plural('evaluator', $assignmentCount) }}</span>
                                        </header>

                                        @if ($procurement->evaluationAssignments->isNotEmpty())
                                            <div class="assignment-table-wrap">
                                                <table class="table assignment-table align-middle mb-0">
                                                    <colgroup>
                                                        <col class="assignment-col-evaluator">
                                                        <col class="assignment-col-email">
                                                        <col class="assignment-col-evaluation">
                                                        <col class="assignment-col-scope">
                                                        <col class="assignment-col-status">
                                                        <col class="assignment-col-actions">
                                                    </colgroup>
                                                    <thead>
                                                        <tr>
                                                            <th scope="col">Evaluator</th>
                                                            <th scope="col">Email</th>
                                                            <th scope="col">Evaluation</th>
                                                            <th scope="col">Scope</th>
                                                            <th scope="col">Status</th>
                                                            <th scope="col">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($procurement->evaluationAssignments as $assignment)
                                                            @php
                                                                $evaluatorName = $assignment->evaluator?->name ?: 'Unavailable evaluator';
                                                                $evaluatorEmail = $assignment->evaluator?->email;
                                                                $initials = collect(preg_split('/\s+/', trim($evaluatorName)))
                                                                    ->filter()
                                                                    ->take(2)
                                                                    ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
                                                                    ->implode('') ?: 'EV';
                                                                 $assignmentStatus = strtolower((string) ($assignment->status ?: 'assigned'));
                                                                 $assignmentStatusTone = $assignmentStatusTones[$assignmentStatus] ?? 'neutral';
                                                                 $assignmentWorkflowStage = strtolower((string) ($assignment->workflow_stage ?: 'application'));
                                                                 $assignmentTechnicalRound = $assignment->technicalProposalRound;
                                                                 $isTechnicalProposalAssignment = $assignmentTechnicalRound
                                                                     || str_contains($assignmentWorkflowStage, 'technical_proposal');
                                                             @endphp
                                                            <tr>
                                                                <td data-label="Evaluator">
                                                                    <div class="assignment-person">
                                                                        <span class="assignment-avatar" aria-hidden="true">{{ $initials }}</span>
                                                                        <span>
                                                                            <strong>{{ $evaluatorName }}</strong>
                                                                            <small>Assigned {{ ($assignment->assigned_at ?: $assignment->created_at)?->format('d M Y') ?? 'recently' }}</small>
                                                                        </span>
                                                                    </div>
                                                                </td>
                                                                <td data-label="Email" class="assignment-email-cell">
                                                                    @if ($evaluatorEmail)
                                                                        <a href="mailto:{{ $evaluatorEmail }}"><i class="feather-mail" aria-hidden="true"></i>{{ $evaluatorEmail }}</a>
                                                                    @else
                                                                        <span><i class="feather-mail" aria-hidden="true"></i>No email recorded</span>
                                                                    @endif
                                                                </td>
                                                                <td data-label="Evaluation">
                                                                    <div class="assignment-evaluation-name">
                                                                        <strong>{{ $assignment->evaluation?->name ?: 'Unavailable evaluation form' }}</strong>
                                                                        @if ($assignment->evaluation)
                                                                            <span class="assignment-method-badge">{{ $assignment->evaluation->typeLabel() }}</span>
                                                                        @endif
                                                                    </div>
                                                                </td>
                                                                 <td data-label="Scope">
                                                                     <div class="assignment-scope">
                                                                         @if ($isTechnicalProposalAssignment)
                                                                             <span class="assignment-stage-badge assignment-stage-badge--technical">
                                                                                 <i class="feather-award" aria-hidden="true"></i>Technical proposal
                                                                             </span>
                                                                             @if ($assignment->form_submission_id)
                                                                                 <strong>Specific qualified proposal</strong>
                                                                                 <small>{{ $assignment->submission?->procurement_submission_code ?: 'Applicant unavailable' }}</small>
                                                                             @else
                                                                                 <strong>Qualified proposal shortlist</strong>
                                                                                 <small>All eligible second-round applicants</small>
                                                                             @endif
                                                                             @if ($assignmentTechnicalRound)
                                                                                 <small class="assignment-round-label">
                                                                                     Round {{ $assignmentTechnicalRound->round_number }}
                                                                                     @if ($assignmentTechnicalRound->title)
                                                                                         · {{ $assignmentTechnicalRound->title }}
                                                                                     @endif
                                                                                 </small>
                                                                             @endif
                                                                         @else
                                                                             <span class="assignment-stage-badge assignment-stage-badge--application">
                                                                                 <i class="feather-file-text" aria-hidden="true"></i>Application evaluation
                                                                             </span>
                                                                             @if ($assignment->form_submission_id)
                                                                                 <strong>Specific application</strong>
                                                                                 <small>{{ $assignment->submission?->procurement_submission_code ?: 'Application unavailable' }}</small>
                                                                             @else
                                                                                 <strong>Entire procurement</strong>
                                                                                 <small>All eligible original applications</small>
                                                                             @endif
                                                                         @endif
                                                                     </div>
                                                                 </td>
                                                                <td data-label="Status">
                                                                    <span class="assignment-state assignment-state--{{ $assignmentStatusTone }}">
                                                                        <i class="{{ $assignmentStatus === 'submitted' ? 'feather-check-circle' : ($assignmentStatus === 'rework' ? 'feather-refresh-cw' : 'feather-clock') }}" aria-hidden="true"></i>
                                                                        {{ \Illuminate\Support\Str::headline($assignmentStatus) }}
                                                                    </span>
                                                                </td>
                                                                <td data-label="Actions">
                                                                    <div class="assignment-actions">
                                                                        <a href="{{ route('eval.assign.applicants', $assignment->id) }}" class="assignment-action assignment-action--primary">
                                                                            <i class="feather-file-text" aria-hidden="true"></i>Applicants
                                                                        </a>

                                                                        @can('evaluations.view_all')
                                                                            @if ($assignmentStatus === 'submitted')
                                                                                <a href="{{ route('my.eval.compare', $assignment->id) }}" class="assignment-action assignment-action--success">
                                                                                    <i class="feather-columns" aria-hidden="true"></i>Compare
                                                                                </a>
                                                                            @endif
                                                                        @endcan

                                                                        @if ($assignmentStatus !== 'submitted')
                                                                            <form method="POST" action="{{ route('eval.assign.destroy', $assignment) }}"
                                                                                onsubmit="return confirm('Remove this evaluator assignment?');">
                                                                                @csrf
                                                                                @method('DELETE')
                                                                                <button type="submit" class="assignment-action assignment-action--danger">
                                                                                    <i class="feather-trash-2" aria-hidden="true"></i>Remove
                                                                                </button>
                                                                            </form>
                                                                        @endif
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @else
                                            <div class="assignment-empty-team">
                                                <span><i class="feather-user-plus" aria-hidden="true"></i></span>
                                                <div>
                                                    <strong>No evaluator has been assigned</strong>
                                                    <p>Use the form above to add the first evaluator to this procurement.</p>
                                                </div>
                                            </div>
                                        @endif
                                    </section>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div id="assignmentFilterEmpty" class="assignment-filter-empty" hidden>
                    <span><i class="feather-search" aria-hidden="true"></i></span>
                    <h3>No procurements match your filters</h3>
                    <p>Try another title or reference, or clear the team-status filter.</p>
                </div>
            </section>
        @else
            <section class="assignment-page-empty">
                <span><i class="feather-briefcase" aria-hidden="true"></i></span>
                <h2>No procurements are available</h2>
                <p>Procurements within your management scope will appear here when they become available.</p>
            </section>
        @endif
    </div>
@endsection

@push('styles')
    <style>
        .evaluator-assignment-page {
            --assignment-ink: #172033;
            --assignment-muted: #667085;
            --assignment-border: #e3e8f0;
            --assignment-soft: #f6f8fb;
            --assignment-primary: #3157d5;
            color: var(--assignment-ink);
        }

        .assignment-page-hero {
            position: relative;
            display: flex;
            overflow: hidden;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            margin-bottom: 1.25rem;
            padding: 1.55rem 1.7rem;
            color: #fff;
            border-radius: 18px;
            background: radial-gradient(circle at 88% 12%, rgba(255, 255, 255, .18), transparent 28%), linear-gradient(130deg, #17296b, #3157d5 64%, #4d74e8);
            box-shadow: 0 18px 40px rgba(35, 68, 178, .16);
        }

        .assignment-page-hero::after {
            position: absolute;
            top: -105px;
            right: -80px;
            width: 260px;
            height: 260px;
            content: '';
            border: 42px solid rgba(255, 255, 255, .07);
            border-radius: 50%;
        }

        .assignment-page-hero > * { position: relative; z-index: 1; }
        .assignment-hero-copy { min-width: 0; }
        .assignment-hero-eyebrow,
        .assignment-section-kicker { display: block; margin-bottom: .28rem; font-size: .7rem; font-weight: 760; letter-spacing: .1em; text-transform: uppercase; }
        .assignment-hero-eyebrow { color: rgba(255, 255, 255, .72); }
        .assignment-hero-copy h1 { margin: 0; color: #fff; font-size: clamp(1.55rem, 2.5vw, 2.2rem); font-weight: 780; }
        .assignment-hero-copy p { max-width: 760px; margin: .48rem 0 0; color: rgba(255, 255, 255, .78); font-size: .9rem; line-height: 1.55; }
        .assignment-hero-total { display: inline-flex; flex: 0 0 auto; align-items: center; gap: .45rem; padding: .72rem .9rem; color: #fff; border: 1px solid rgba(255, 255, 255, .22); border-radius: 12px; background: rgba(255, 255, 255, .11); font-size: .76rem; font-weight: 650; white-space: nowrap; }
        .assignment-hero-total i { font-size: 1rem; }
        .assignment-hero-total strong { font-size: 1.05rem; }

        .assignment-notice { display: flex; align-items: flex-start; gap: .7rem; margin-bottom: 1rem; padding: .82rem .95rem; border: 1px solid; border-radius: 12px; font-size: .82rem; }
        .assignment-notice > i { margin-top: .12rem; font-size: 1rem; }
        .assignment-notice--success { color: #05603a; border-color: #a6e4c2; background: #edfdf4; }
        .assignment-notice--danger { color: #9c2a20; border-color: #f1b8b2; background: #fff4f2; }
        .assignment-notice strong { display: block; }
        .assignment-notice ul { margin: .25rem 0 0; padding-left: 1.1rem; }

        .assignment-summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .9rem; margin-bottom: 1.35rem; }
        .assignment-summary-card { --summary-color: #3157d5; --summary-soft: #eef2ff; display: flex; min-width: 0; align-items: center; gap: .78rem; padding: .95rem 1rem; border: 1px solid var(--assignment-border); border-radius: 14px; background: #fff; box-shadow: 0 7px 20px rgba(20, 34, 66, .045); }
        .assignment-summary-card--violet { --summary-color: #6941c6; --summary-soft: #f4f0ff; }
        .assignment-summary-card--green { --summary-color: #067647; --summary-soft: #eafaf1; }
        .assignment-summary-card--amber { --summary-color: #b54708; --summary-soft: #fff4e5; }
        .assignment-summary-icon { display: grid; width: 42px; height: 42px; flex: 0 0 42px; place-items: center; color: var(--summary-color); border-radius: 12px; background: var(--summary-soft); font-size: 1.02rem; }
        .assignment-summary-card > div { min-width: 0; }
        .assignment-summary-card span,
        .assignment-summary-card strong,
        .assignment-summary-card small { display: block; }
        .assignment-summary-card > div > span { color: var(--assignment-muted); font-size: .7rem; font-weight: 700; letter-spacing: .035em; text-transform: uppercase; }
        .assignment-summary-card strong { margin: .04rem 0; color: var(--assignment-ink); font-size: 1.25rem; line-height: 1.2; }
        .assignment-summary-card small { overflow: hidden; color: #8a94a6; font-size: .67rem; text-overflow: ellipsis; white-space: nowrap; }

        .assignment-directory { padding: 1.1rem; border: 1px solid var(--assignment-border); border-radius: 16px; background: #f8fafc; }
        .assignment-directory-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; margin-bottom: .9rem; }
        .assignment-section-kicker { color: var(--assignment-primary); }
        .assignment-directory-heading h2 { margin: 0; color: var(--assignment-ink); font-size: 1.14rem; font-weight: 760; }
        .assignment-directory-heading p { margin: .22rem 0 0; color: var(--assignment-muted); font-size: .78rem; }
        .assignment-result-count { color: var(--assignment-muted); font-size: .74rem; font-weight: 650; white-space: nowrap; }

        .assignment-filter-bar { display: grid; grid-template-columns: minmax(260px, 1fr) minmax(210px, .34fr) auto; align-items: end; gap: .7rem; margin-bottom: .9rem; padding: .8rem; border: 1px solid var(--assignment-border); border-radius: 12px; background: #fff; }
        .assignment-search { position: relative; margin: 0; }
        .assignment-search > i { position: absolute; top: 50%; left: .85rem; z-index: 1; color: #8490a3; transform: translateY(-50%); pointer-events: none; }
        .assignment-search .form-control { min-height: 42px; padding-left: 2.5rem; border-color: #dbe1ea; border-radius: 9px; font-size: .78rem; }
        .assignment-filter-field label { display: block; margin-bottom: .28rem; color: #475467; font-size: .69rem; font-weight: 700; }
        .assignment-filter-field .form-select { min-height: 42px; border-color: #dbe1ea; border-radius: 9px; font-size: .76rem; }
        .assignment-clear-button { display: inline-flex; min-height: 42px; align-items: center; justify-content: center; gap: .38rem; padding: .6rem .78rem; color: #526074; border: 1px solid #dbe1ea; border-radius: 9px; background: #fff; font-size: .74rem; font-weight: 700; }
        .assignment-clear-button:hover { color: var(--assignment-primary); border-color: #b9c7ed; background: #f5f8ff; }

        .assignment-procurement-list { display: grid; gap: .75rem; }
        .assignment-procurement-card { overflow: hidden; border: 1px solid var(--assignment-border) !important; border-radius: 14px !important; background: #fff; box-shadow: 0 5px 17px rgba(20, 34, 66, .04); }
        .assignment-procurement-card[hidden] { display: none !important; }
        .procurement-accordion-button { display: flex; align-items: center; gap: .8rem; min-width: 0; padding: .9rem 1rem; color: var(--assignment-ink); background: #fff; box-shadow: none !important; }
        .procurement-accordion-button:not(.collapsed) { color: var(--assignment-ink); background: #f5f8ff; border-bottom: 1px solid #e5eaf2; }
        .procurement-accordion-button:focus-visible { z-index: 2; outline: 3px solid rgba(49, 87, 213, .2); outline-offset: -3px; }
        .procurement-heading-icon { display: grid; width: 42px; height: 42px; flex: 0 0 42px; place-items: center; color: var(--assignment-primary); border-radius: 11px; background: #eef2ff; }
        .procurement-heading-copy { min-width: 0; flex: 1 1 auto; }
        .procurement-heading-labels { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; margin-bottom: .26rem; }
        .procurement-reference,
        .procurement-state { display: inline-flex; align-items: center; gap: .23rem; padding: .2rem .4rem; border-radius: 6px; font-size: .61rem; font-weight: 750; line-height: 1.2; text-transform: uppercase; }
        .procurement-reference { color: #3157d5; background: #eef2ff; }
        .procurement-state--neutral { color: #475467; background: #f2f4f7; }
        .procurement-state--success { color: #067647; background: #eafaf1; }
        .procurement-state--danger { color: #b42318; background: #fff0ee; }
        .procurement-state--warning { color: #b54708; background: #fff4e5; }
        .procurement-state--violet { color: #6941c6; background: #f4f0ff; }
        .procurement-heading-copy > strong { display: block; overflow: hidden; color: var(--assignment-ink); font-size: .88rem; font-weight: 750; text-overflow: ellipsis; white-space: nowrap; }
        .procurement-heading-copy > small { display: block; margin-top: .13rem; color: var(--assignment-muted); font-size: .67rem; font-weight: 500; }
        .procurement-heading-metrics { display: flex; flex: 0 0 auto; align-items: stretch; gap: .4rem; margin-left: auto; }
        .procurement-heading-metrics > span { min-width: 82px; padding: .42rem .55rem; text-align: center; border: 1px solid #e2e7ef; border-radius: 9px; background: #fff; }
        .procurement-heading-metrics strong,
        .procurement-heading-metrics small { display: block; }
         .procurement-heading-metrics strong { color: var(--assignment-ink); font-size: .84rem; line-height: 1.15; }
         .procurement-heading-metrics small { margin-top: .1rem; color: var(--assignment-muted); font-size: .59rem; font-weight: 600; text-transform: uppercase; }
         .procurement-heading-metrics > .procurement-heading-metric--shortlist { border-color: #b7e2ca; background: #f0fbf5; }
         .procurement-heading-metric--shortlist strong { color: #067647; }

         .assignment-procurement-body { padding: 1rem; background: #fff; }
         .assignment-builder { padding: .95rem; border: 1px solid #dbe4fa; border-radius: 12px; background: #f7f9ff; }
        .assignment-block-heading { display: flex; align-items: flex-start; gap: .65rem; margin-bottom: .8rem; }
        .assignment-block-heading > span { display: grid; width: 36px; height: 36px; flex: 0 0 36px; place-items: center; color: var(--assignment-primary); border-radius: 9px; background: #e7edff; }
        .assignment-block-heading h4,
        .assignment-registry-heading h4 { margin: 0; color: var(--assignment-ink); font-size: .9rem; font-weight: 760; }
         .assignment-block-heading p,
         .assignment-registry-heading p { margin: .16rem 0 0; color: var(--assignment-muted); font-size: .7rem; line-height: 1.45; }
         .assignment-shortlist-note { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: .7rem; margin: -.05rem 0 .85rem; padding: .72rem .78rem; border: 1px solid; border-radius: 10px; }
         .assignment-shortlist-note--ready { border-color: #b7e2ca; background: #f0fbf5; }
         .assignment-shortlist-note--waiting { border-color: #ecd49f; background: #fff9eb; }
         .assignment-shortlist-icon { display: grid; width: 36px; height: 36px; place-items: center; border-radius: 9px; font-size: .92rem; }
         .assignment-shortlist-note--ready .assignment-shortlist-icon { color: #067647; background: #dff6e9; }
         .assignment-shortlist-note--waiting .assignment-shortlist-icon { color: #b54708; background: #ffefc7; }
         .assignment-shortlist-copy { min-width: 0; }
         .assignment-shortlist-kicker { display: block; margin-bottom: .12rem; color: #667085; font-size: .57rem; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; }
         .assignment-shortlist-copy strong { display: block; overflow: hidden; color: #253149; font-size: .74rem; font-weight: 760; text-overflow: ellipsis; white-space: nowrap; }
         .assignment-shortlist-copy p { max-width: 820px; margin: .18rem 0 0; color: #667085; font-size: .64rem; line-height: 1.4; }
         .assignment-shortlist-counts { display: flex; min-width: 104px; align-items: flex-end; flex-direction: column; gap: .18rem; text-align: right; }
         .assignment-shortlist-badge { display: inline-flex; align-items: baseline; gap: .27rem; padding: .27rem .45rem; border-radius: 7px; font-size: .58rem; font-weight: 760; text-transform: uppercase; }
         .assignment-shortlist-badge strong { font-size: .72rem; }
         .assignment-shortlist-badge.is-ready { color: #067647; background: #dff6e9; }
         .assignment-shortlist-badge.is-waiting { color: #b54708; background: #ffefc7; }
         .assignment-shortlist-excluded,
         .assignment-shortlist-counts small { color: #7b8799; font-size: .57rem; font-weight: 650; }
         .assignment-form-grid { display: grid; grid-template-columns: minmax(0, 1.12fr) minmax(0, 1.2fr) minmax(0, 1.05fr) minmax(0, 1fr) auto; align-items: start; gap: .7rem; }
         .assignment-field { min-width: 0; }
         .assignment-field label { display: block; margin-bottom: .3rem; color: #344054; font-size: .69rem; font-weight: 730; }
         .assignment-field label span { color: #d92d20; }
         .assignment-field .form-select,
         .assignment-readonly-field { min-height: 43px; color: #344054; border: 1px solid #d8e0ed; border-radius: 9px; background-color: #fff; font-size: .73rem; }
         .assignment-field .form-select { border-color: #d8e0ed; }
         .assignment-field .form-select:focus { border-color: #8fa7ed; box-shadow: 0 0 0 .2rem rgba(49, 87, 213, .1); }
         .assignment-readonly-field { display: flex; overflow: hidden; align-items: center; gap: .48rem; padding: 0 .72rem; background: #f7f9fc; }
         .assignment-readonly-field i { flex: 0 0 auto; color: #667085; }
         .assignment-readonly-field input { min-width: 0; width: 100%; padding: 0; color: #475467; border: 0; outline: 0; background: transparent; font: inherit; font-weight: 650; text-overflow: ellipsis; }
         .assignment-field-message { display: block; margin-top: .27rem; color: #7b8799; font-size: .62rem; line-height: 1.35; }
         .assignment-field-message--danger { color: #b42318; }
         .assignment-field--submission { grid-column: 1 / -1; grid-row: 2; }
         .assignment-form-action { grid-column: 5; grid-row: 1; align-self: start; padding-top: 1.18rem; }
        .assignment-primary-button { display: inline-flex; min-height: 43px; align-items: center; justify-content: center; gap: .38rem; padding: .63rem .82rem; color: #fff; border: 1px solid var(--assignment-primary); border-radius: 9px; background: var(--assignment-primary); font-size: .72rem; font-weight: 750; white-space: nowrap; box-shadow: 0 6px 14px rgba(49, 87, 213, .16); }
        .assignment-primary-button:hover { border-color: #294ab9; background: #294ab9; }
        .assignment-primary-button:disabled { cursor: not-allowed; opacity: .55; box-shadow: none; }

        .assignment-registry { margin-top: 1rem; overflow: hidden; border: 1px solid var(--assignment-border); border-radius: 12px; }
        .assignment-registry-heading { display: flex; align-items: center; justify-content: space-between; gap: .8rem; padding: .82rem .9rem; border-bottom: 1px solid var(--assignment-border); background: #fff; }
        .assignment-count-badge { flex: 0 0 auto; padding: .35rem .5rem; color: #3157d5; border-radius: 7px; background: #eef2ff; font-size: .66rem; font-weight: 750; }
        .assignment-table-wrap { width: 100%; overflow: hidden; }
        .assignment-table { width: 100%; table-layout: fixed; }
        .assignment-col-evaluator { width: 16%; }
         .assignment-col-email { width: 17%; }
         .assignment-col-evaluation { width: 18%; }
         .assignment-col-scope { width: 20%; }
         .assignment-col-status { width: 11%; }
         .assignment-col-actions { width: 18%; }
        .assignment-table thead th { padding: .58rem .65rem; color: #667085; border: 0; border-bottom: 1px solid var(--assignment-border); background: #f8fafc; font-size: .61rem; font-weight: 780; letter-spacing: .045em; text-transform: uppercase; }
        .assignment-table tbody td { padding: .75rem .65rem; color: #475467; border-color: #edf0f4; font-size: .7rem; vertical-align: middle; overflow-wrap: anywhere; }
        .assignment-table tbody tr:last-child td { border-bottom: 0; }
        .assignment-table tbody tr:hover { background: #fbfcff; }
        .assignment-person { display: flex; min-width: 0; align-items: center; gap: .5rem; }
        .assignment-avatar { display: grid; width: 33px; height: 33px; flex: 0 0 33px; place-items: center; color: #3157d5; border-radius: 9px; background: #eef2ff; font-size: .65rem; font-weight: 800; }
        .assignment-person > span:last-child { min-width: 0; }
        .assignment-person strong,
        .assignment-person small,
        .assignment-evaluation-name strong,
        .assignment-scope strong,
        .assignment-scope small { display: block; }
        .assignment-person strong,
        .assignment-evaluation-name strong,
        .assignment-scope strong { color: #344054; font-size: .7rem; font-weight: 720; line-height: 1.35; }
         .assignment-person small,
         .assignment-scope small { margin-top: .15rem; color: #8a94a6; font-size: .61rem; line-height: 1.35; }
         .assignment-stage-badge { display: inline-flex; align-items: center; gap: .24rem; margin-bottom: .3rem; padding: .2rem .34rem; border-radius: 5px; font-size: .55rem; font-weight: 780; letter-spacing: .025em; text-transform: uppercase; }
         .assignment-stage-badge--application { color: #3157d5; background: #eef2ff; }
         .assignment-stage-badge--technical { color: #067647; background: #eafaf1; }
         .assignment-scope .assignment-round-label { color: #5269b8; font-weight: 680; }
        .assignment-email-cell a,
        .assignment-email-cell > span { display: inline-flex; min-width: 0; align-items: flex-start; gap: .3rem; color: #3157d5; font-size: .67rem; line-height: 1.4; overflow-wrap: anywhere; word-break: break-word; }
        .assignment-email-cell > span { color: #8a94a6; }
        .assignment-email-cell i { flex: 0 0 auto; margin-top: .12rem; }
        .assignment-method-badge { display: inline-flex; margin-top: .3rem; padding: .2rem .35rem; color: #5269b8; border-radius: 5px; background: #eef2ff; font-size: .58rem; font-weight: 720; }
        .assignment-state { display: inline-flex; align-items: center; gap: .25rem; padding: .28rem .4rem; border-radius: 6px; font-size: .61rem; font-weight: 720; white-space: nowrap; }
        .assignment-state--neutral { color: #475467; background: #f2f4f7; }
        .assignment-state--success { color: #067647; background: #eafaf1; }
        .assignment-state--warning { color: #b54708; background: #fff4e5; }
        .assignment-actions { display: flex; flex-wrap: wrap; gap: .3rem; }
        .assignment-actions form { margin: 0; }
        .assignment-action { display: inline-flex; min-height: 30px; align-items: center; justify-content: center; gap: .27rem; padding: .36rem .45rem; border: 1px solid; border-radius: 7px; background: #fff; font-size: .61rem; font-weight: 720; line-height: 1; text-decoration: none; white-space: nowrap; }
        .assignment-action--primary { color: #3157d5; border-color: #b9c8f0; }
        .assignment-action--primary:hover { color: #2849b6; background: #eef2ff; }
        .assignment-action--success { color: #067647; border-color: #9ed9ba; }
        .assignment-action--success:hover { color: #05603a; background: #eafaf1; }
        .assignment-action--danger { color: #b42318; border-color: #efbbb6; }
        .assignment-action--danger:hover { color: #912018; background: #fff0ee; }
        .assignment-action:focus-visible,
        .assignment-primary-button:focus-visible,
        .assignment-clear-button:focus-visible { outline: 3px solid rgba(49, 87, 213, .22); outline-offset: 2px; }

        .assignment-empty-team { display: flex; min-height: 105px; align-items: center; justify-content: center; gap: .7rem; padding: 1rem; text-align: left; background: #fbfcfd; }
        .assignment-empty-team > span,
        .assignment-filter-empty > span,
        .assignment-page-empty > span { display: grid; width: 42px; height: 42px; flex: 0 0 42px; place-items: center; color: #6f7d93; border-radius: 12px; background: #eef1f5; }
        .assignment-empty-team strong { display: block; color: #475467; font-size: .76rem; }
        .assignment-empty-team p { margin: .15rem 0 0; color: var(--assignment-muted); font-size: .68rem; }
        .assignment-filter-empty,
        .assignment-page-empty { padding: 2rem 1rem; color: var(--assignment-muted); text-align: center; }
        .assignment-filter-empty > span,
        .assignment-page-empty > span { margin: 0 auto .7rem; }
        .assignment-filter-empty h3,
        .assignment-page-empty h2 { margin: 0; color: #344054; font-size: .95rem; font-weight: 750; }
        .assignment-filter-empty p,
        .assignment-page-empty p { margin: .3rem 0 0; font-size: .74rem; }
        .assignment-page-empty { border: 1px solid var(--assignment-border); border-radius: 16px; background: #fff; }

         @media (max-width: 1199.98px) {
             .assignment-form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
             .assignment-field--submission,
             .assignment-form-action { grid-column: 1 / -1; grid-row: auto; }
             .assignment-form-action { padding-top: 0; }
             .assignment-primary-button { width: 100%; }
         }

        @media (max-width: 991.98px) {
            .assignment-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .procurement-heading-metrics > span { min-width: 70px; }
            .assignment-table colgroup,
            .assignment-table thead { display: none; }
            .assignment-table,
            .assignment-table tbody { display: block; width: 100%; }
            .assignment-table tbody { padding: .7rem; background: #f8fafc; }
            .assignment-table tbody tr { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .6rem; margin-bottom: .7rem; padding: .8rem; border: 1px solid var(--assignment-border); border-radius: 11px; background: #fff; }
            .assignment-table tbody tr:last-child { margin-bottom: 0; }
            .assignment-table tbody td { display: block; min-width: 0; padding: 0; border: 0; }
            .assignment-table tbody td::before { display: block; margin-bottom: .28rem; color: #8a94a6; content: attr(data-label); font-size: .57rem; font-weight: 780; letter-spacing: .045em; text-transform: uppercase; }
            .assignment-table tbody td:first-child,
            .assignment-table tbody td:last-child { grid-column: 1 / -1; }
        }

        @media (max-width: 767.98px) {
            .assignment-page-hero { align-items: flex-start; flex-direction: column; padding: 1.25rem; }
            .assignment-hero-total { align-self: flex-start; }
            .assignment-filter-bar { grid-template-columns: 1fr; }
             .assignment-clear-button { width: 100%; }
             .procurement-heading-metrics { display: none; }
             .assignment-directory { padding: .8rem; }
             .assignment-procurement-body { padding: .75rem; }
             .assignment-shortlist-note { grid-template-columns: auto minmax(0, 1fr); align-items: start; }
             .assignment-shortlist-counts { grid-column: 2; align-items: flex-start; flex-direction: row; flex-wrap: wrap; text-align: left; }
         }

        @media (max-width: 575.98px) {
            .assignment-summary { grid-template-columns: 1fr; }
            .assignment-directory-heading,
            .assignment-registry-heading { align-items: flex-start; flex-direction: column; }
            .assignment-result-count { white-space: normal; }
            .procurement-accordion-button { align-items: flex-start; padding: .8rem; }
            .procurement-heading-icon { width: 36px; height: 36px; flex-basis: 36px; }
             .procurement-heading-copy > strong { white-space: normal; }
             .assignment-form-grid { grid-template-columns: 1fr; }
             .assignment-field--submission,
             .assignment-form-action { grid-column: auto; }
             .assignment-shortlist-note { grid-template-columns: 1fr; }
             .assignment-shortlist-icon,
             .assignment-shortlist-counts { grid-column: auto; }
             .assignment-shortlist-copy strong { white-space: normal; }
             .assignment-table tbody tr { grid-template-columns: 1fr; }
            .assignment-table tbody td:first-child,
            .assignment-table tbody td:last-child { grid-column: auto; }
            .assignment-actions,
            .assignment-actions form,
            .assignment-action { width: 100%; }
        }
    </style>
@endpush

@push('scripts')
    <script>
         document.addEventListener('DOMContentLoaded', function () {
             document.querySelectorAll('.assignment-type').forEach(function (select) {
                 const procurementId = select.dataset.procurement;
                 const submissionWrap = document.getElementById(`submissionSelect${procurementId}`);
                 const submissionSelect = submissionWrap?.querySelector('select[name="submission_id"]');
                 const submissionLabel = submissionWrap?.querySelector('[data-submission-label]');
                 const submissionHelp = submissionWrap?.querySelector('[data-submission-help]');
                 const scopeHelp = select.closest('.assignment-field')?.querySelector('[data-assignment-scope-help]');

                 const toggleSubmission = function () {
                     const target = select.value === 'submission'
                         ? 'application'
                         : (select.value === 'technical_proposal_submission' ? 'technical_proposal' : null);
                     const isSpecific = target !== null;

                     submissionWrap?.classList.toggle('d-none', !isSpecific);

                     if (submissionSelect) {
                         const selectedTarget = submissionSelect.options[submissionSelect.selectedIndex]
                             ?.dataset.assignmentTarget || null;

                         submissionSelect.required = isSpecific;

                         submissionSelect.querySelectorAll('[data-assignment-target]').forEach(function (option) {
                             const matchesTarget = option.dataset.assignmentTarget === target;
                             option.hidden = !matchesTarget;
                             option.disabled = !matchesTarget;
                         });

                         submissionSelect.querySelectorAll('[data-assignment-target-group]').forEach(function (group) {
                             const matchesTarget = group.dataset.assignmentTargetGroup === target;
                             group.hidden = !matchesTarget;
                             group.disabled = !matchesTarget;
                         });

                         const placeholder = submissionSelect.querySelector('[data-assignment-placeholder]');
                         if (placeholder) {
                             placeholder.textContent = target === 'technical_proposal'
                                 ? 'Choose a qualified proposal applicant'
                                 : 'Choose an original application';
                         }

                         if (!isSpecific || (selectedTarget && selectedTarget !== target)) {
                             submissionSelect.value = '';
                         }
                     }

                     if (submissionLabel) {
                         submissionLabel.innerHTML = target === 'technical_proposal'
                             ? 'Qualified proposal applicant <span aria-hidden="true">*</span>'
                             : 'Application <span aria-hidden="true">*</span>';
                     }

                     if (submissionHelp) {
                         submissionHelp.textContent = target === 'technical_proposal'
                             ? 'Only the post-compliance Qualified shortlist is available.'
                             : 'Select one eligible original application.';
                     }

                     if (scopeHelp) {
                         const messages = {
                             procurement: 'The evaluator will receive every eligible original application.',
                             submission: 'The evaluator will receive one selected original application.',
                             technical_proposal_procurement: 'The evaluator will receive the entire Qualified second-round proposal shortlist.',
                             technical_proposal_submission: 'The evaluator will receive one applicant from the Qualified proposal shortlist.',
                         };
                         scopeHelp.textContent = messages[select.value]
                             || 'Choose an available assignment scope.';
                     }
                 };

                select.addEventListener('change', toggleSubmission);
                toggleSubmission();
            });

            const searchInput = document.getElementById('assignmentProcurementSearch');
            const coverageFilter = document.getElementById('assignmentCoverageFilter');
            const clearButton = document.getElementById('clearAssignmentFilters');
            const emptyState = document.getElementById('assignmentFilterEmpty');
            const resultCount = document.getElementById('assignmentResultCount');
            const cards = Array.from(document.querySelectorAll('[data-assignment-procurement]'));

            if (!searchInput || !coverageFilter || cards.length === 0) {
                return;
            }

            const applyFilters = function () {
                const search = searchInput.value.trim().toLocaleLowerCase();
                const coverage = coverageFilter.value;
                let visibleCount = 0;

                cards.forEach(function (card) {
                    const matchesSearch = !search || (card.dataset.search || '').includes(search);
                    const matchesCoverage = coverage === 'all' || card.dataset.coverage === coverage;
                    const isVisible = matchesSearch && matchesCoverage;

                    card.hidden = !isVisible;
                    if (isVisible) {
                        visibleCount += 1;
                    }
                });

                if (emptyState) {
                    emptyState.hidden = visibleCount !== 0;
                }

                if (resultCount) {
                    resultCount.textContent = `${visibleCount} of ${cards.length} procurements shown`;
                }
            };

            searchInput.addEventListener('input', applyFilters);
            coverageFilter.addEventListener('change', applyFilters);
            clearButton?.addEventListener('click', function () {
                searchInput.value = '';
                coverageFilter.value = 'all';
                applyFilters();
                searchInput.focus();
            });

            applyFilters();
        });
    </script>
@endpush
