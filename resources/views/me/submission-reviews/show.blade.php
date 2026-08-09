@extends('layouts.app')

@section('title', 'Review M&E Submission')

@push('styles')
    @include('me.submission-reviews.partials.styles')
@endpush

@section('content')
@php
    $period = $submission->assignment?->collection?->reportingPeriod;
    $form = $submission->assignment?->collection?->form;
    $thinkTank = $submission->assignment?->thinkTank;
    $openFindings = $submission->dataQualityFindings->where('status', 'open');
    $closedFindings = $submission->dataQualityFindings->where('status', '!=', 'open');
    $workflowSteps = [
        \App\Models\MeDataSubmission::STATUS_SUBMITTED,
        \App\Models\MeDataSubmission::STATUS_UNDER_REVIEW,
        \App\Models\MeDataSubmission::STATUS_VERIFIED,
        \App\Models\MeDataSubmission::STATUS_APPROVED,
    ];
    $displayStep = $effectiveStatus === \App\Models\MeDataSubmission::STATUS_RESUBMITTED
        ? \App\Models\MeDataSubmission::STATUS_SUBMITTED
        : ($effectiveStatus === \App\Models\MeDataSubmission::STATUS_VALIDATED
            ? \App\Models\MeDataSubmission::STATUS_VERIFIED
            : $effectiveStatus);
    $currentStepIndex = array_search($displayStep, $workflowSteps, true);
@endphp
<div class="mel-review-shell">
    <header class="mel-page-header">
        <div>
            <span class="mel-eyebrow">Official submission review &middot; Version {{ $submission->current_version ?: 1 }}</span>
            <h1>{{ $thinkTank?->name ?: 'Think Tank submission' }}</h1>
            <p>{{ $form?->code ?: 'No form code' }} &middot; {{ $form?->title ?: 'M&E data submission' }} &middot; {{ $period?->label ?: 'Reporting period not assigned' }}</p>
        </div>
        <div class="mel-header-actions">
            <span class="mel-header-button">{{ $statusLabels[$effectiveStatus] ?? str($effectiveStatus)->headline() }}</span>
            <a class="mel-header-button" href="{{ route('budget.me.submission-reviews.index') }}">Back to review queue</a>
        </div>
    </header>

    <div class="mel-lifecycle" aria-label="Submission review lifecycle">
        @foreach($workflowSteps as $index => $step)
            @php
                $stepClass = is_int($currentStepIndex) && $index < $currentStepIndex ? 'done' : (is_int($currentStepIndex) && $index === $currentStepIndex ? 'current' : '');
            @endphp
            <div class="mel-life-step {{ $stepClass }}">{{ $statusLabels[$step] ?? str($step)->headline() }}</div>
        @endforeach
    </div>
    @if(in_array($effectiveStatus, [\App\Models\MeDataSubmission::STATUS_RETURNED, \App\Models\MeDataSubmission::STATUS_REJECTED], true))
        <div class="alert {{ $effectiveStatus === \App\Models\MeDataSubmission::STATUS_REJECTED ? 'alert-danger' : 'alert-warning' }} mel-alert">
            This submission is currently <strong>{{ strtolower($statusLabels[$effectiveStatus] ?? $effectiveStatus) }}</strong>. The recorded reason is retained in the review history below.
        </div>
    @endif
    @if(session('success'))<div class="alert alert-success mel-alert" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger mel-alert" role="alert">
            <strong>The requested review update was not completed.</strong>
            <ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="mel-review-grid">
        <main>
            <section class="mel-panel">
                <div class="mel-panel-header">
                    <div><h2>Submission context</h2><p>Identity, ownership and reporting window for this case.</p></div>
                    <span class="mel-badge">Submission record</span>
                </div>
                <div class="mel-panel-body">
                    <div class="mel-fact-grid">
                        <div class="mel-fact"><small>Think Tank</small><strong>{{ $thinkTank?->name ?: 'Not available' }}</strong></div>
                        <div class="mel-fact"><small>Country</small><strong>{{ $thinkTank?->country ?: 'Not specified' }}</strong></div>
                        <div class="mel-fact"><small>Reporting period</small><strong>{{ $period?->label ?: 'Not assigned' }}{{ $period?->reporting_year ? ' · '.$period->reporting_year : '' }}</strong></div>
                        <div class="mel-fact"><small>Submission deadline</small><strong>{{ $period?->submission_deadline?->format('d M Y, H:i') ?: $submission->assignment?->collection?->due_at?->format('d M Y, H:i') ?: 'Not set' }}</strong></div>
                        <div class="mel-fact"><small>Submitted by</small><strong>{{ $submission->submittedBy?->name ?: 'Not available' }}</strong></div>
                        <div class="mel-fact"><small>Submitted on</small><strong>{{ $submission->submitted_at?->format('d M Y, H:i') ?: 'Not yet submitted' }}</strong></div>
                        <div class="mel-fact"><small>Current version</small><strong>Version {{ $submission->current_version ?: 1 }} &middot; {{ $submission->versions->count() }} archived</strong></div>
                        <div class="mel-fact"><small>Payload summary</small><strong>{{ $submission->indicatorResults->count() }} results &middot; {{ $submission->answers->count() }} answers &middot; {{ $submission->evidence->count() }} evidence</strong></div>
                    </div>
                    @if(filled($submission->notes))
                        <div class="mt-3 p-3 rounded border bg-light"><small class="d-block text-muted fw-bold mb-1">SUBMITTER NOTES</small>{{ $submission->notes }}</div>
                    @endif
                </div>
            </section>

            @forelse($submission->indicatorResults as $result)
                @php
                    $indicator = $result->indicator;
                    $target = $indicator?->targets?->where('approval_status', 'approved')->firstWhere('reporting_year', $period?->reporting_year)
                        ?? $indicator?->targets?->where('approval_status', 'approved')->sortByDesc('project_year')->first();
                    $irs = $indicator?->approvedReferenceSheet;
                    $reportedValue = filled($result->actual_value) ? $result->actual_value : ($result->actual_text ?: 'Not reported');
                @endphp
                <section class="mel-panel mel-result-card">
                    <div class="mel-panel-header">
                        <div>
                            <h2>{{ $indicator?->indicator_code ?: 'Indicator' }} &middot; {{ $indicator?->name ?: 'Indicator details unavailable' }}</h2>
                            <p>{{ $indicator?->resultsLevelLabel() ?: 'Results level not specified' }}</p>
                        </div>
                        <span class="mel-badge">{{ str($result->review_status ?: 'submitted')->headline() }}</span>
                    </div>
                    <div class="mel-panel-body">
                        <div class="mel-fact-grid">
                            <div class="mel-fact"><small>Baseline</small><strong>{{ filled($indicator?->baseline_value) ? $indicator->baseline_value : 'Not set' }}</strong></div>
                            <div class="mel-fact"><small>Approved target</small><strong>{{ $target?->target_text ?? $target?->target_value ?? 'Not set' }}</strong></div>
                            <div class="mel-fact"><small>Reported result</small><strong>{{ $reportedValue }}</strong></div>
                            <div class="mel-fact"><small>Unit of measure</small><strong>{{ $indicator?->unit?->name ?: $irs?->unit_of_measurement ?: 'Not specified' }}</strong></div>
                        </div>
                        @if($irs)
                            <details class="mel-irs">
                                <summary>Open approved Indicator Reference Sheet guidance</summary>
                                <div class="mel-irs-body">
                                    <div class="mel-irs-item"><strong>Definition</strong>{{ $irs->definition ?: 'Not documented' }}</div>
                                    <div class="mel-irs-item"><strong>Inclusion criteria</strong>{{ $irs->inclusion_criteria ?: 'Not documented' }}</div>
                                    <div class="mel-irs-item"><strong>Calculation method</strong>{{ $irs->calculation_method ?: 'Not documented' }}</div>
                                    <div class="mel-irs-item"><strong>Means of Verification</strong>{{ $irs->means_of_verification ?: 'Not documented' }}</div>
                                </div>
                            </details>
                        @endif
                        @if(($previousApproved[$indicator?->id] ?? collect())->isNotEmpty())
                            <div class="mt-3">
                                <small class="d-block text-muted fw-bold mb-2">PREVIOUS APPROVED VALUES</small>
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($previousApproved[$indicator->id]->take(4) as $previous)
                                        <span class="mel-badge">{{ $previous->reportingPeriod?->label ?: 'Prior period' }}: {{ filled($previous->actual_value) ? $previous->actual_value : ($previous->actual_text ?: 'N/A') }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </section>
            @empty
                <section class="mel-panel mt-3"><div class="mel-panel-body mel-empty"><strong>No indicator results were generated</strong><span>Review the submitted answers and form configuration before making a decision.</span></div></section>
            @endforelse

            <section class="mel-panel mt-3">
                <div class="mel-panel-header">
                    <div><h2>Submitted answers</h2><p>Responses captured from the configured data-entry form.</p></div>
                    <span class="mel-badge">{{ $submission->answers->count() }} {{ str('response')->plural($submission->answers->count()) }}</span>
                </div>
                <div class="mel-panel-body">
                    @forelse($submission->answers as $answer)
                        @php
                            $answerValue = data_get($answer->value, 'value', $answer->value);
                            if (is_array($answerValue)) {
                                $answerValue = implode(', ', collect($answerValue)->flatten()->map(fn ($item) => is_bool($item) ? ($item ? 'Yes' : 'No') : (string) $item)->all());
                            } elseif (is_bool($answerValue)) {
                                $answerValue = $answerValue ? 'Yes' : 'No';
                            }
                        @endphp
                        <div class="mel-answer">
                            <span class="mel-answer-label">{{ $answer->field?->label ?: str($answer->field_key)->headline() }}</span>
                            <div class="mel-answer-value">{{ filled($answerValue) ? $answerValue : 'No response provided' }}</div>
                        </div>
                    @empty
                        <div class="mel-empty"><strong>No form answers are attached</strong><span>The submission contains no stored response values.</span></div>
                    @endforelse
                </div>
            </section>

            <section class="mel-panel mt-3">
                <div class="mel-panel-header">
                    <div><h2>Means of Verification</h2><p>Supporting documents supplied with this submission.</p></div>
                    <span class="mel-badge">{{ $submission->evidence->count() }} {{ str('file')->plural($submission->evidence->count()) }}</span>
                </div>
                <div class="mel-panel-body">
                    @forelse($submission->evidence as $evidence)
                        <div class="mel-evidence">
                            <div>
                                <span class="mel-cell-title">{{ $evidence->document_title ?: $evidence->original_name ?: 'Supporting evidence' }}</span>
                                <span class="mel-cell-meta">{{ str($evidence->evidence_type ?: 'document')->headline() }} &middot; {{ str($evidence->verification_status ?: 'pending')->headline() }}{{ $evidence->indicator ? ' · '.$evidence->indicator->indicator_code : '' }}</span>
                            </div>
                            <a class="mel-btn mel-btn-secondary" href="{{ route('budget.me.submission-reviews.evidence.download', [$submission, $evidence]) }}">Download</a>
                        </div>
                    @empty
                        <div class="mel-empty"><strong>No evidence was attached</strong><span>Confirm whether evidence is mandatory for the reported indicators before proceeding.</span></div>
                    @endforelse
                </div>
            </section>
        </main>

        <aside class="mel-sticky-column">
            <section class="mel-panel">
                <div class="mel-panel-header">
                    <div><h2>Review decision</h2><p>Only valid actions for this stage are available.</p></div>
                </div>
                <div class="mel-panel-body">
                    @if(!$canDecide)
                        <div class="alert alert-warning mb-0 small">You submitted this record, so an independent authorised reviewer must make the decision.</div>
                    @elseif(empty($availableActions))
                        <div class="alert alert-light border mb-0 small">No further review decision is available while this submission is <strong>{{ strtolower($statusLabels[$effectiveStatus] ?? $effectiveStatus) }}</strong>.</div>
                    @else
                        <p class="mel-decision-copy">Add a clear review note. A reason is mandatory when returning or rejecting a submission, and every decision is retained in the immutable history.</p>
                        @if($blockingDqaCount > 0 && array_key_exists('approve', $availableActions))
                            <div class="alert alert-danger py-2 small">Approval is blocked by {{ $blockingDqaCount }} open DQA {{ str('error')->plural($blockingDqaCount) }}. Resolve {{ str('it')->plural($blockingDqaCount) }} first.</div>
                        @endif
                        <form id="review-decision-form" method="POST" action="{{ route('budget.me.submission-reviews.decide', $submission) }}">
                            @csrf
                            <label class="form-label small fw-bold" for="review-comments">Reviewer note</label>
                            <textarea id="review-comments" class="form-control" name="comments" rows="5" maxlength="5000" placeholder="Record your assessment, required correction or decision rationale">{{ old('comments') }}</textarea>
                            <div class="mel-action-grid">
                                @foreach($availableActions as $key => $label)
                                    @php
                                        $isDestructive = in_array($key, ['return', 'reject'], true);
                                        $approvalBlocked = $key === 'approve' && $blockingDqaCount > 0;
                                    @endphp
                                    <button
                                        class="mel-btn {{ $isDestructive ? 'mel-btn-danger' : 'mel-btn-primary' }}"
                                        type="submit"
                                        name="action"
                                        value="{{ $key }}"
                                        data-requires-comments="{{ $isDestructive ? '1' : '0' }}"
                                        data-confirm="{{ $key === 'reject' ? 'Reject this submission? This decision will be recorded in the audit history.' : '' }}"
                                        @disabled($approvalBlocked)
                                    >{{ $label }}</button>
                                @endforeach
                            </div>
                        </form>
                    @endif
                </div>
            </section>

            <section class="mel-panel">
                <div class="mel-panel-header">
                    <div><h2>Data quality findings</h2><p>Resolve findings only after checking the supporting record.</p></div>
                    <span class="mel-badge {{ $blockingDqaCount ? 'danger' : ($openFindings->count() ? 'warning' : 'success') }}">{{ $openFindings->count() }} open</span>
                </div>
                <div class="mel-panel-body">
                    @forelse($openFindings as $finding)
                        <div class="mel-finding {{ strtolower($finding->severity) === 'error' ? 'error' : '' }}">
                            <div class="mel-finding-title">{{ strtoupper($finding->severity) }} &middot; {{ str($finding->rule_code)->headline() }}</div>
                            <p class="mel-finding-message">{{ $finding->message }}</p>
                            @if($canDecide)
                                <form method="POST" action="{{ route('budget.me.submission-reviews.dqa.resolve', [$submission, $finding]) }}">
                                    @csrf
                                    <label class="visually-hidden" for="resolution-{{ $finding->id }}">Resolution note</label>
                                    <textarea id="resolution-{{ $finding->id }}" name="resolution_notes" class="form-control" rows="2" maxlength="5000" placeholder="Reviewer justification or resolution note" required></textarea>
                                    <button class="mel-btn mel-btn-secondary w-100 mt-2" type="submit">Mark as resolved</button>
                                </form>
                            @else
                                <div class="mel-cell-meta">An independent reviewer must resolve this finding.</div>
                            @endif
                        </div>
                    @empty
                        <div class="alert alert-success mb-0 small">No open data-quality findings remain for this submission.</div>
                    @endforelse
                    @if($closedFindings->isNotEmpty())
                        <details class="mel-irs mt-3">
                            <summary>View {{ $closedFindings->count() }} closed {{ str('finding')->plural($closedFindings->count()) }}</summary>
                            <div class="px-3 pb-3">
                                @foreach($closedFindings as $finding)
                                    <div class="border-top pt-2 mt-2 small"><strong>{{ str($finding->rule_code)->headline() }} &middot; {{ str($finding->status)->headline() }}</strong><div class="text-muted">{{ $finding->resolution_notes ?: 'No resolution note recorded.' }}</div></div>
                                @endforeach
                            </div>
                        </details>
                    @endif
                </div>
            </section>

            <section class="mel-panel">
                <div class="mel-panel-header">
                    <div><h2>Review history</h2><p>Chronological, immutable decision log.</p></div>
                    <span class="mel-badge">{{ $submission->reviews->count() }} events</span>
                </div>
                <div class="mel-panel-body">
                    <div class="mel-timeline">
                        @forelse($submission->reviews as $review)
                            <div class="mel-event">
                                <div class="mel-event-title">{{ str($review->action)->headline() }} &middot; {{ str($review->from_status)->headline() }} to {{ str($review->to_status)->headline() }}</div>
                                <div class="mel-event-meta">{{ $review->reviewer?->name ?: 'System' }} &middot; {{ $review->reviewed_at?->format('d M Y, H:i') ?: 'Time unavailable' }} &middot; Version {{ $review->submission_version }}</div>
                                <p class="mel-event-comment">{{ $review->comments ?: 'No reviewer note was recorded.' }}</p>
                            </div>
                        @empty
                            <div class="mel-event"><div class="mel-event-title">No review decisions yet</div><p class="mel-event-comment">The first valid decision will appear here.</p></div>
                        @endforelse
                    </div>
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('review-decision-form');
    if (!form) return;
    form.addEventListener('submit', function (event) {
        const button = event.submitter;
        const comments = document.getElementById('review-comments');
        if (!button || !comments) return;
        comments.required = button.dataset.requiresComments === '1';
        if (!form.checkValidity()) {
            event.preventDefault();
            form.reportValidity();
            return;
        }
        if (button.dataset.confirm && !window.confirm(button.dataset.confirm)) {
            event.preventDefault();
        }
    });
});
</script>
@endpush
