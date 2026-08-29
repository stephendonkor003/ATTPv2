@php
    $submittedCount = $evaluationRows->where('status', 'submitted')->count();
    $reworkCount = $evaluationRows->where('status', 'rework')->count();
@endphp

<section class="pev-panel pev-review-register" aria-labelledby="evaluationReviewTitle">
    <header class="pev-panel__head">
        <div>
            <span class="pev-eyebrow">Individual panel records</span>
            <h2 id="evaluationReviewTitle">Review submitted evaluations</h2>
            <p>Open a particular evaluator record or return it with clear correction instructions. Reworked records remain outside live rankings until resubmitted.</p>
        </div>
        <div class="pev-review-summary" aria-label="Evaluation record status">
            <span><strong>{{ number_format($submittedCount) }}</strong> submitted</span>
            <span class="pev-review-summary--warning"><strong>{{ number_format($reworkCount) }}</strong> in rework</span>
        </div>
    </header>

    <div class="pev-review-grid">
        @forelse ($evaluationRows as $row)
            @php
                $submission = $row['submission'];
                $evaluation = $row['evaluation'];
                $applicant = $row['applicant'];
                $evaluator = $row['evaluator'];
                $openRework = $row['open_rework'];
                $completedReworkCount = $row['completed_rework_count'];
                $latestCompletedRework = $row['latest_completed_rework'];
                $methodLabel = $evaluation?->typeLabel() ?? 'Evaluation';
            @endphp

            <article class="pev-review-card pev-review-card--{{ $row['status'] }}">
                <header class="pev-review-card__head">
                    <span class="pev-method-pill pev-method-pill--{{ $evaluation?->type }}">
                        <i class="{{ $evaluation?->isServices() ? 'feather-bar-chart-2' : ($evaluation?->isGoods() ? 'feather-package' : 'feather-user-check') }}" aria-hidden="true"></i>
                        {{ $methodLabel }}
                    </span>
                    <span class="pev-review-state pev-review-state--{{ $row['status'] }}">
                        <i class="{{ $row['status'] === 'submitted' ? 'feather-check-circle' : ($row['status'] === 'rework' ? 'feather-refresh-cw' : 'feather-edit-3') }}" aria-hidden="true"></i>
                        {{ $row['status_label'] }}
                    </span>
                </header>

                <div class="pev-review-workflow">
                    <span><i class="{{ $row['workflow_round_label'] ? 'feather-award' : 'feather-file-text' }}" aria-hidden="true"></i></span>
                    <div>
                        <small>Workflow stage</small>
                        <strong>{{ $row['workflow_label'] }}</strong>
                        @if ($row['workflow_round_label'])
                            <p>{{ $row['workflow_round_label'] }}</p>
                        @endif
                    </div>
                </div>

                <div class="pev-review-card__identity">
                    <span>{{ $applicant?->procurement_submission_code ?: 'Application record' }}</span>
                    <h3>{{ $applicant?->display_name ?: 'Applicant unavailable' }}</h3>
                    <p>{{ $evaluation?->name ?: 'Evaluation form unavailable' }}</p>
                </div>

                <div class="pev-review-facts">
                    <div>
                        <span>Evaluator</span>
                        <strong>{{ $evaluator?->name ?: 'Evaluator unavailable' }}</strong>
                        <small>{{ $evaluator?->email ?: 'Email unavailable' }}</small>
                    </div>
                    <div>
                        <span>Recorded result</span>
                        <strong>{{ $row['result'] }}</strong>
                        <small>Revision {{ number_format(max(1, (int) $submission->revision_number)) }}</small>
                    </div>
                </div>

                @if ($openRework)
                    <details class="pev-rework-note" open>
                        <summary>
                            <span><i class="feather-message-square" aria-hidden="true"></i>Correction instructions</span>
                            <span class="pev-details-toggle">Full instructions <i class="feather-chevron-down" aria-hidden="true"></i></span>
                        </summary>
                        <div class="pev-rework-note__body">
                            <p>{{ $openRework->reason }}</p>
                            <small>
                                Requested by {{ $openRework->requester?->name ?: 'an administrator' }}
                                {{ $openRework->requested_at ? 'on '.$openRework->requested_at->format('d M Y, H:i') : '' }}
                                @if ($openRework->notified_at)
                                    &middot; Email delivered
                                @elseif ($openRework->notification_error)
                                    &middot; Email delivery needs attention
                                @else
                                    &middot; Email delivery not recorded
                                @endif
                            </small>
                        </div>
                    </details>
                @endif

                @if ($completedReworkCount > 0)
                    <details class="pev-rework-history">
                        <summary>
                            <span>
                                <i class="feather-rotate-ccw" aria-hidden="true"></i>
                                <strong>{{ number_format($completedReworkCount) }} completed {{ Str::plural('rework cycle', $completedReworkCount) }}</strong>
                            </span>
                            <span class="pev-details-toggle">Latest details <i class="feather-chevron-down" aria-hidden="true"></i></span>
                        </summary>

                        @if ($latestCompletedRework)
                            <div class="pev-rework-history__body">
                                <div class="pev-rework-history__grid">
                                    <div>
                                        <span>Latest cycle</span>
                                        <strong>Cycle {{ number_format((int) $latestCompletedRework->cycle) }}</strong>
                                    </div>
                                    <div>
                                        <span>Revision change</span>
                                        <strong>
                                            @if ($latestCompletedRework->source_revision_number !== null && $latestCompletedRework->completed_revision_number !== null)
                                                Revision {{ number_format((int) $latestCompletedRework->source_revision_number) }}
                                                <i class="feather-arrow-right" aria-hidden="true"></i>
                                                Revision {{ number_format((int) $latestCompletedRework->completed_revision_number) }}
                                            @else
                                                Revision details unavailable
                                            @endif
                                        </strong>
                                    </div>
                                </div>
                                <p>
                                    Requested by {{ $latestCompletedRework->requester?->name ?: 'an administrator' }}
                                    {{ $latestCompletedRework->requested_at ? 'on '.$latestCompletedRework->requested_at->format('d M Y, H:i') : '(date unavailable)' }}.
                                    Completed {{ $latestCompletedRework->completed_at ? 'on '.$latestCompletedRework->completed_at->format('d M Y, H:i') : '(date unavailable)' }}.
                                </p>
                                <span class="pev-rework-delivery pev-rework-delivery--{{ $latestCompletedRework->notified_at ? 'success' : ($latestCompletedRework->notification_error ? 'warning' : 'muted') }}">
                                    <i class="{{ $latestCompletedRework->notified_at ? 'feather-mail' : 'feather-alert-circle' }}" aria-hidden="true"></i>
                                    @if ($latestCompletedRework->notified_at)
                                        Notification delivered {{ $latestCompletedRework->notified_at->format('d M Y, H:i') }}
                                    @elseif ($latestCompletedRework->notification_error)
                                        Notification delivery needed attention
                                    @else
                                        Notification delivery was not recorded
                                    @endif
                                </span>
                            </div>
                        @endif
                    </details>
                @endif

                <footer class="pev-review-card__foot">
                    <small>
                        <i class="feather-clock" aria-hidden="true"></i>
                        {{ $row['activity_at'] ? $row['activity_at']->format('d M Y, H:i') : 'No activity date' }}
                    </small>
                    <div class="pev-review-actions">
                        @if ($row['view_url'])
                            <a href="{{ $row['view_url'] }}" class="pev-btn pev-btn--outline">
                                <i class="feather-eye" aria-hidden="true"></i> View
                            </a>
                        @endif

                        @if ($row['can_request_rework'])
                            <button type="button" class="pev-btn pev-btn--rework"
                                data-rework-open
                                data-rework-action="{{ $row['rework_url'] }}"
                                data-submission-id="{{ $submission->getKey() }}"
                                data-rework-requires-proposal-round-override="{{ $row['requires_proposal_round_override'] ? '1' : '0' }}"
                                data-evaluation="{{ ($evaluation?->name ?: $methodLabel).' | '.$row['workflow_context'] }}"
                                data-evaluator="{{ $evaluator?->name ?: 'Evaluator' }}"
                                data-applicant="{{ $applicant?->procurement_submission_code ?: 'Application' }}">
                                <i class="feather-rotate-ccw" aria-hidden="true"></i>
                                {{ $row['requires_proposal_round_override'] ? 'Override lock & rework' : 'Send for rework' }}
                            </button>
                        @elseif ($row['blocking_reason'] && auth()->user()?->can('evaluations.manage'))
                            <span class="pev-review-locked" title="{{ $row['blocking_reason'] }}">
                                <i class="feather-lock" aria-hidden="true"></i> Rework locked
                            </span>
                        @elseif ($row['status'] === 'rework')
                            <span class="pev-review-awaiting"><i class="feather-clock" aria-hidden="true"></i> Awaiting evaluator</span>
                        @endif
                    </div>
                </footer>

                @if ($row['requires_proposal_round_override'] && $row['can_override_proposal_round_lock'])
                    <p class="pev-review-blocking-copy pev-review-blocking-copy--override">
                        <i class="feather-shield" aria-hidden="true"></i>
                        A technical-proposal round exists. Your administrator override will be recorded in the audit snapshot.
                    </p>
                @elseif ($row['blocking_reason'] && auth()->user()?->can('evaluations.manage'))
                    <p class="pev-review-blocking-copy">{{ $row['blocking_reason'] }}</p>
                @endif
            </article>
        @empty
            <div class="pev-empty">
                <span class="pev-empty__icon"><i class="feather-clipboard" aria-hidden="true"></i></span>
                <h3>No evaluator records yet</h3>
                <p>Individual records appear here after panel members submit their evaluations.</p>
            </div>
        @endforelse
    </div>
</section>
