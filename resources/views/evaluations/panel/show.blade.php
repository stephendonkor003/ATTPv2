@extends('layouts.app')

@section('title', 'Evaluation Journey - '.($procurement->reference_no ?: $procurement->title))

@section('content')
    @php
        $title = $procurement->title ?: 'Untitled procurement';
        $currentStep = $journeySteps->firstWhere('state', 'current');
    @endphp

    <main class="nxl-container pev-shell" aria-labelledby="panelJourneyTitle">
        <nav class="pev-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('eval.panel.index') }}"><i class="feather-arrow-left" aria-hidden="true"></i> Panel evaluations</a>
            <span aria-hidden="true">/</span>
            <span>{{ $procurement->reference_no ?: 'Procurement journey' }}</span>
        </nav>

        <header class="pev-hero pev-hero--journey">
            <div class="pev-hero__copy">
                <div class="pev-reference-line">
                    <span class="pev-reference pev-reference--light"><i class="feather-hash" aria-hidden="true"></i>{{ $procurement->reference_no ?: 'No reference' }}</span>
                    <span class="pev-status pev-status--{{ $card['status'] }}">{{ Str::headline(str_replace('_', ' ', $card['status'])) }}</span>
                </div>
                <h1 id="panelJourneyTitle">{{ $title }}</h1>
                <p>Follow the procurement from publication through panel evaluation, applicant communication, proposal intake, and the final decision.</p>
                <div class="pev-hero__meta">
                    <span><i class="feather-activity" aria-hidden="true"></i>{{ Str::headline((string) ($procurement->status ?: 'Status not set')) }}</span>
                    <span><i class="feather-layers" aria-hidden="true"></i>{{ $card['methods']->count() }} evaluation type(s)</span>
                    @if ($card['latest_at'])
                        <span><i class="feather-clock" aria-hidden="true"></i>Updated {{ $card['latest_at']->format('d M Y, H:i') }}</span>
                    @endif
                </div>
            </div>
            <div class="pev-now-card">
                <span class="pev-now-card__orb"><i class="{{ $currentStep['icon'] ?? 'feather-check-circle' }}" aria-hidden="true"></i></span>
                <small>{{ $currentStep ? 'Happening now' : 'Journey status' }}</small>
                <strong>{{ $currentStep['label'] ?? 'Evaluation journey complete' }}</strong>
                <span>{{ $currentStep['meta'] ?? 'All recorded stages are complete' }}</span>
            </div>
        </header>

        @if (session('success'))
            <div class="pev-alert pev-alert--success" role="status">
                <i class="feather-check-circle" aria-hidden="true"></i><span>{{ session('success') }}</span>
            </div>
        @endif

        @if (session('warning'))
            <div class="pev-alert pev-alert--warning" role="alert">
                <i class="feather-alert-triangle" aria-hidden="true"></i><span>{{ session('warning') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="pev-alert pev-alert--danger" role="alert">
                <i class="feather-alert-circle" aria-hidden="true"></i>
                <div><strong>The evaluation could not be returned for rework.</strong><span>{{ $errors->first() }}</span></div>
            </div>
        @endif

        <section class="pev-kpi-grid" aria-label="Procurement panel overview">
            @foreach ([
                ['feather-inbox', 'Applications', $card['application_count'], 'received'],
                ['feather-users', 'Active panel', $card['evaluator_count'], $card['assignment_count'].' assignments'],
                ['feather-file-text', 'Active reports', $card['report_count'], $card['evaluated_applicant_count'].' applicants evaluated'],
                ['feather-trending-up', 'Panel progress', $card['completion_percent'].'%', 'current assignments only'],
            ] as [$icon, $label, $value, $detail])
                <article class="pev-kpi">
                    <span class="pev-kpi__icon"><i class="{{ $icon }}" aria-hidden="true"></i></span>
                    <div><span>{{ $label }}</span><strong>{{ is_numeric($value) ? number_format($value) : $value }}</strong><small>{{ $detail }}</small></div>
                </article>
            @endforeach
        </section>

        <section class="pev-panel pev-journey-panel" aria-labelledby="journeyFlowTitle">
            <header class="pev-panel__head">
                <div>
                    <span class="pev-eyebrow">Live procurement journey</span>
                    <h2 id="journeyFlowTitle">What is happening?</h2>
                    <p>The animated circle identifies the next active step. Completed and upcoming stages remain visible for context.</p>
                </div>
                <span class="pev-trust-note"><i class="feather-shield" aria-hidden="true"></i> Current assignments only</span>
            </header>
            <div class="pev-panel__body">
                @include('evaluations.panel.partials.journey', ['steps' => $journeySteps])
            </div>
        </section>

        <section class="pev-section" aria-labelledby="evaluationMethodsTitle">
            <header class="pev-section__head">
                <div>
                    <span class="pev-eyebrow">Evaluation workspaces</span>
                    <h2 id="evaluationMethodsTitle">Open the right evaluation type</h2>
                    <p>Each method keeps its own rules, panel progress, evidence, and report presentation.</p>
                </div>
            </header>

            <div class="pev-method-grid">
                @forelse ($card['methods'] as $methodCard)
                    @include('evaluations.panel.partials.method-card', compact('methodCard', 'procurement'))
                @empty
                    <div class="pev-empty">
                        <span class="pev-empty__icon"><i class="feather-settings" aria-hidden="true"></i></span>
                        <h3>No evaluation method configured</h3>
                        <p>This procurement is visible in the workspace, but a Services, Goods, or EOI evaluation must be configured before panel activity can begin.</p>
                    </div>
                @endforelse
            </div>
        </section>

        @include('evaluations.panel.partials.rework-register', compact('evaluationRows', 'procurement'))

        @if ($eoiStats)
            <section class="pev-panel" aria-labelledby="eoiHandoffTitle">
                <header class="pev-panel__head">
                    <div>
                        <span class="pev-eyebrow">EOI hand-off</span>
                        <h2 id="eoiHandoffTitle">Qualification to technical proposal</h2>
                        <p>The shortlist and proposal intake below follow the final, active EOI panel decision.</p>
                    </div>
                    @if (auth()->user()?->can('evaluations.view_all'))
                        <a href="{{ route('reports.evaluations.eoi.procurement', $procurement) }}" class="pev-btn pev-btn--outline">
                            Open qualification report <i class="feather-arrow-up-right" aria-hidden="true"></i>
                        </a>
                    @endif
                </header>
                <div class="pev-handoff-grid">
                    <article><span class="pev-handoff-icon pev-handoff-icon--success"><i class="feather-user-check"></i></span><div><small>Qualified to advance</small><strong>{{ number_format($eoiStats['advance']) }}</strong><span>Fully or average qualified after panel completion</span></div></article>
                    <article><span class="pev-handoff-icon pev-handoff-icon--warning"><i class="feather-clock"></i></span><div><small>Awaiting panel</small><strong>{{ number_format($eoiStats['panel_incomplete']) }}</strong><span>Final routing remains held</span></div></article>
                    <article><span class="pev-handoff-icon pev-handoff-icon--violet"><i class="feather-mail"></i></span><div><small>Invitation delivery</small><strong>{{ number_format($communicationSummary['notified_qualified']) }}</strong><span>{{ number_format($communicationSummary['offline_candidates']) }} offline applicant(s) enrolled for admin capture</span></div></article>
                    <article><span class="pev-handoff-icon pev-handoff-icon--blue"><i class="feather-upload-cloud"></i></span><div><small>Proposal responses</small><strong>{{ number_format($communicationSummary['proposal_respondents']) }}</strong><span>{{ number_format($communicationSummary['proposal_documents']) }} submitted documents</span></div></article>
                </div>
            </section>
        @endif
    </main>

    @if (auth()->user()?->can('evaluations.manage') && $evaluationRows->contains('can_request_rework', true))
        <div id="evaluationReworkModal" class="pev-rework-modal" role="dialog" aria-modal="true"
            aria-labelledby="evaluationReworkModalTitle" aria-describedby="evaluationReworkModalDescription" hidden>
            <button type="button" class="pev-rework-modal__backdrop" data-rework-close tabindex="-1" aria-label="Close rework dialog"></button>
            <section class="pev-rework-dialog" role="document">
                <header>
                    <div>
                        <span class="pev-eyebrow">Evaluation quality control</span>
                        <h2 id="evaluationReworkModalTitle">Send evaluation for rework</h2>
                        <p id="evaluationReworkModalDescription">The current submitted revision will be archived and removed from live rankings until the evaluator resubmits.</p>
                    </div>
                    <button type="button" class="pev-rework-dialog__close" data-rework-close aria-label="Close dialog">
                        <i class="feather-x" aria-hidden="true"></i>
                    </button>
                </header>

                <form method="POST" action="" id="evaluationReworkForm">
                    @csrf
                    <input type="hidden" name="rework_submission_id" id="reworkSubmissionId" value="{{ old('rework_submission_id') }}">

                    @if ($errors->any() && old('rework_submission_id'))
                        <div class="pev-alert pev-alert--danger pev-rework-form-error" id="reworkFormError" role="alert" tabindex="-1">
                            <i class="feather-alert-circle" aria-hidden="true"></i>
                            <div>
                                <strong>The evaluation could not be returned for rework.</strong>
                                <span>{{ $errors->first() }}</span>
                            </div>
                        </div>
                    @endif

                    <div class="pev-rework-target">
                        <span><i class="feather-file-text" aria-hidden="true"></i></span>
                        <div>
                            <small id="reworkEvaluationName">Evaluation</small>
                            <strong id="reworkApplicantName">Application</strong>
                            <p>Assigned to <b id="reworkEvaluatorName">Evaluator</b></p>
                        </div>
                    </div>

                    <label class="pev-rework-field" for="reworkReason">
                        <span>Correction instructions <em>Required</em></span>
                        <textarea name="reason" id="reworkReason" rows="7" minlength="10" maxlength="5000" required
                            @if ($errors->any() && old('rework_submission_id')) aria-describedby="reworkFormError" @endif
                            placeholder="Explain exactly what must be reviewed or corrected before this evaluation is submitted again.">{{ old('reason') }}</textarea>
                        <small><span id="reworkReasonCount">0</span>/5,000 characters &middot; These instructions are shown in the evaluator workspace and email.</small>
                    </label>

                    <div class="pev-rework-impact">
                        <i class="feather-info" aria-hidden="true"></i>
                        <p><strong>What happens next?</strong> Existing answers remain prefilled, the original revision is retained for audit, and this record stops contributing to rankings until it is resubmitted.</p>
                    </div>

                    <div class="pev-rework-override" id="reworkProposalRoundOverride" hidden>
                        <div class="pev-rework-override__warning">
                            <i class="feather-alert-triangle" aria-hidden="true"></i>
                            <div>
                                <strong>Administrator override required</strong>
                                <p>A technical-proposal round has already started. Reopening this EOI evaluation makes the applicant's panel result incomplete until the evaluator resubmits. Existing invitations and uploaded proposals are preserved, while further proposal activity is paused until the refreshed EOI outcome is available.</p>
                            </div>
                        </div>
                        <label class="pev-rework-override__confirm" for="reworkProposalRoundOverrideConfirm">
                            <input type="checkbox" name="override_proposal_round_lock" value="1"
                                id="reworkProposalRoundOverrideConfirm" disabled
                                @checked(old('override_proposal_round_lock'))>
                            <span>I understand the downstream impact and confirm this audited administrator override.</span>
                        </label>
                    </div>

                    <footer>
                        <button type="button" class="pev-btn pev-btn--outline" data-rework-close>Cancel</button>
                        <button type="submit" class="pev-btn pev-btn--rework" data-rework-submit>
                            <i class="feather-send" aria-hidden="true"></i> Return to evaluator
                        </button>
                    </footer>
                </form>
            </section>
        </div>
    @endif
@endsection

@push('styles')
    @include('evaluations.panel.partials.styles')
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('evaluationReworkModal');
            if (!modal) return;

            document.body.appendChild(modal);

            const form = modal.querySelector('#evaluationReworkForm');
            const reason = modal.querySelector('#reworkReason');
            const count = modal.querySelector('#reworkReasonCount');
            const submissionId = modal.querySelector('#reworkSubmissionId');
            const evaluationName = modal.querySelector('#reworkEvaluationName');
            const applicantName = modal.querySelector('#reworkApplicantName');
            const evaluatorName = modal.querySelector('#reworkEvaluatorName');
            const submitButton = modal.querySelector('[data-rework-submit]');
            const errorRegion = modal.querySelector('#reworkFormError');
            const overrideRegion = modal.querySelector('#reworkProposalRoundOverride');
            const overrideConfirmation = modal.querySelector('#reworkProposalRoundOverrideConfirm');
            const modalTitle = modal.querySelector('#evaluationReworkModalTitle');
            const modalDescription = modal.querySelector('#evaluationReworkModalDescription');
            const page = document.querySelector('main.pev-shell');
            let trigger = null;

            const updateCount = () => { count.textContent = String(reason.value.length); };
            const openModal = (button, preserveReason = false) => {
                trigger = button;
                const requiresProposalRoundOverride = button.dataset.reworkRequiresProposalRoundOverride === '1';
                if (!preserveReason) {
                    reason.value = '';
                    reason.removeAttribute('aria-describedby');
                    if (errorRegion) {
                        errorRegion.hidden = true;
                        errorRegion.textContent = '';
                    }
                    overrideConfirmation.checked = false;
                } else if (errorRegion) {
                    errorRegion.hidden = false;
                    reason.setAttribute('aria-describedby', errorRegion.id);
                }
                overrideRegion.hidden = !requiresProposalRoundOverride;
                overrideConfirmation.disabled = !requiresProposalRoundOverride;
                overrideConfirmation.required = requiresProposalRoundOverride;
                modalTitle.textContent = requiresProposalRoundOverride
                    ? 'Override lock and send for rework'
                    : 'Send evaluation for rework';
                modalDescription.textContent = requiresProposalRoundOverride
                    ? 'This exceptional action is limited to System Administrators and will be preserved in the rework audit snapshot.'
                    : 'The current submitted revision will be archived and removed from live rankings until the evaluator resubmits.';
                form.action = button.dataset.reworkAction;
                submissionId.value = button.dataset.submissionId;
                evaluationName.textContent = button.dataset.evaluation;
                applicantName.textContent = button.dataset.applicant;
                evaluatorName.textContent = button.dataset.evaluator;
                modal.hidden = false;
                modal.classList.add('is-open');
                document.body.classList.add('pev-modal-open');
                page?.setAttribute('inert', '');
                page?.setAttribute('aria-hidden', 'true');
                updateCount();
                window.setTimeout(() => (preserveReason && errorRegion ? errorRegion : reason).focus(), 40);
            };
            const closeModal = () => {
                modal.classList.remove('is-open');
                modal.hidden = true;
                document.body.classList.remove('pev-modal-open');
                page?.removeAttribute('inert');
                page?.removeAttribute('aria-hidden');
                trigger?.focus();
            };

            const trapFocus = event => {
                const focusable = [...modal.querySelectorAll(
                    '.pev-rework-dialog button:not([disabled]), .pev-rework-dialog textarea:not([disabled]), .pev-rework-dialog input:not([disabled]), .pev-rework-dialog a[href], .pev-rework-dialog [tabindex]:not([tabindex="-1"])'
                )].filter(element => element.getClientRects().length > 0);
                if (focusable.length === 0) return;

                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            };

            document.querySelectorAll('[data-rework-open]').forEach(button => {
                button.addEventListener('click', () => openModal(button));
            });
            modal.querySelectorAll('[data-rework-close]').forEach(button => {
                button.addEventListener('click', closeModal);
            });
            reason.addEventListener('input', updateCount);
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape' && !modal.hidden) closeModal();
                if (event.key === 'Tab' && !modal.hidden) trapFocus(event);
            });
            form.addEventListener('submit', () => {
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="feather-loader" aria-hidden="true"></i> Sending for rework...';
            });

            const reopenId = @json((string) old('rework_submission_id', ''));
            if (reopenId !== '') {
                const reopenButton = document.querySelector(`[data-rework-open][data-submission-id="${CSS.escape(reopenId)}"]`);
                if (reopenButton) openModal(reopenButton, true);
            }
        });
    </script>
@endpush
