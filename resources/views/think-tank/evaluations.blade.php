<x-think-tank.partials.shell :member="$member" title="My Evaluations">
    <div class="tte-page">
        <header class="tte-head">
            <div>
                <div class="tte-path"><span>Procurement</span><i class="feather-chevron-right"></i><strong>Evaluation assignments</strong></div>
                <h1>Evaluation workspace</h1>
                <p>Review your assigned applications, save work in progress and submit completed technical or financial evaluations.</p>
            </div>
            <a class="ppl-button is-secondary" href="{{ route('think-tank.procurement-plans', $portalRouteParams) }}"><i class="feather-folder"></i>Annual plans</a>
        </header>

        <section class="tte-summary" aria-label="Evaluation workload summary">
            <article><span><i class="feather-clipboard"></i></span><div><small>Assignments</small><strong>{{ number_format($stats['assignments']) }}</strong><em>Evaluation templates assigned</em></div></article>
            <article><span><i class="feather-file-text"></i></span><div><small>Application tasks</small><strong>{{ number_format($stats['tasks']) }}</strong><em>Applications requiring evaluation</em></div></article>
            <article><span><i class="feather-clock"></i></span><div><small>Remaining</small><strong>{{ number_format($stats['pending']) }}</strong><em>Draft or not yet started</em></div></article>
            <article><span><i class="feather-check-circle"></i></span><div><small>Completed</small><strong>{{ number_format($stats['completed']) }}</strong><em>Final evaluations submitted</em></div></article>
        </section>

        <section class="tte-guidance">
            <span><i class="feather-info" aria-hidden="true"></i></span>
            <div><strong>Evaluation guidance</strong><p>Evaluate each application independently. Draft scores can be saved, but a submitted evaluation is locked unless it is formally returned for rework.</p></div>
            <div class="tte-guidance-tags"><span>Services: numeric scoring</span><span>Goods: Yes / No assessment</span></div>
        </section>

        <section class="tte-worklist" aria-labelledby="tte-worklist-title">
            <header class="tte-worklist-head">
                <div><span class="ppl-section-label">Assigned work</span><h2 id="tte-worklist-title">Evaluation assignments</h2><p>Open an application below to start, continue or review your submitted evaluation.</p></div>
                <span class="tte-count">{{ number_format($assignments->count()) }} {{ Str::plural('assignment', $assignments->count()) }}</span>
            </header>

            <div class="tte-assignment-list">
                @forelse ($assignments as $assignment)
                    @php
                        $assignmentSubmissions = $assignment->form_submission_id
                            ? $submissions->where('id', $assignment->form_submission_id)
                            : $submissions->where('procurement_id', $assignment->procurement_id);
                        $evaluationType = Str::lower((string) ($assignment->evaluation?->type ?: 'services'));
                        $phase = $assignment->evaluation?->evaluation_phase
                            ?: $assignment->evaluation?->type
                            ?: 'Evaluation';
                        $plan = $assignment->procurement?->thinkTankPlanningItem?->plan;
                        $completeForAssignment = $assignmentSubmissions->filter(function ($application) use ($evaluationSubmissions, $assignment): bool {
                            $key = implode(':', [$assignment->evaluation_id, $assignment->procurement_id, $application->id]);
                            return filled($evaluationSubmissions->get($key)?->submitted_at);
                        })->count();
                    @endphp
                    <article class="tte-assignment">
                        <header class="tte-assignment-head">
                            <span class="tte-assignment-icon"><i class="{{ $evaluationType === 'goods' ? 'feather-package' : 'feather-award' }}" aria-hidden="true"></i></span>
                            <div class="tte-assignment-title">
                                <div class="tte-assignment-labels">
                                    <span>{{ Str::headline($phase) }}</span>
                                    <span>{{ $assignment->form_submission_id ? 'Specific application' : 'All applications' }}</span>
                                </div>
                                <h3>{{ $assignment->procurement?->title ?: 'Procurement evaluation' }}</h3>
                                <p>{{ $assignment->evaluation?->name ?: 'Evaluation template' }}</p>
                            </div>
                            <div class="tte-assignment-progress"><strong>{{ $completeForAssignment }}/{{ $assignmentSubmissions->count() }}</strong><span>completed</span></div>
                        </header>

                        <div class="tte-assignment-meta">
                            <span><i class="feather-hash"></i>{{ $assignment->procurement?->reference_no ?: 'No reference' }}</span>
                            <span><i class="feather-calendar"></i>Assigned {{ $assignment->assigned_at?->format('d M Y') ?: $assignment->created_at?->format('d M Y') }}</span>
                            @if ($plan)
                                <a href="{{ route('think-tank.procurement-plans.show', array_merge($portalRouteParams, ['plan' => $plan])) }}"><i class="feather-folder"></i>{{ str_starts_with((string) $plan->fiscal_year, 'FY') ? $plan->fiscal_year : 'FY '.$plan->fiscal_year }}</a>
                            @endif
                        </div>

                        <div class="tte-applications">
                            <div class="tte-applications-head">
                                <span>Application</span><span>Applicant</span><span>Submitted</span><span>Status</span><span>Action</span>
                            </div>
                            @forelse ($assignmentSubmissions as $application)
                                @php
                                    $submissionKey = implode(':', [$assignment->evaluation_id, $assignment->procurement_id, $application->id]);
                                    $evaluationSubmission = $evaluationSubmissions->get($submissionKey);
                                    $isCompleted = filled($evaluationSubmission?->submitted_at);
                                    $isDraft = $evaluationSubmission && ! $isCompleted;
                                @endphp
                                <div class="tte-application-row">
                                    <div data-label="Application"><strong>{{ $application->procurement_submission_code }}</strong><small>{{ $application->form?->name ?: 'Application form' }}</small></div>
                                    <div data-label="Applicant"><strong>{{ $application->submitter?->name ?: $application->display_name }}</strong><small>{{ $application->submitter?->email ?: 'Vendor account' }}</small></div>
                                    <div data-label="Submitted"><span>{{ ($application->submitted_at ?: $application->created_at)?->format('d M Y') }}</span></div>
                                    <div data-label="Status"><span class="tte-task-status {{ $isCompleted ? 'is-complete' : ($isDraft ? 'is-draft' : 'is-new') }}">{{ $isCompleted ? 'Completed' : ($isDraft ? 'Draft saved' : 'Not started') }}</span></div>
                                    <div data-label="Action">
                                        @if ($isCompleted)
                                            <a class="tte-task-action is-view" href="{{ route('eval.assign.view', [$assignment, $application]) }}"><i class="feather-eye"></i>View</a>
                                        @elseif ($isDraft)
                                            <a class="tte-task-action" href="{{ route('eval.assign.start', [$assignment, $application]) }}"><i class="feather-edit-3"></i>Continue</a>
                                        @else
                                            <a class="tte-task-action" href="{{ route('eval.assign.start', [$assignment, $application]) }}"><i class="feather-play"></i>Start</a>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="tte-no-applications"><i class="feather-inbox"></i><div><strong>No applications are ready</strong><span>This assignment will become actionable when eligible applications are received.</span></div></div>
                            @endforelse
                        </div>
                    </article>
                @empty
                    <div class="tte-empty">
                        <span><i class="feather-clipboard"></i></span>
                        <h3>No evaluation assignments yet</h3>
                        <p>Assignments created for your account will appear here with their applications and evaluation status.</p>
                        <a class="ppl-button is-secondary" href="{{ route('think-tank.procurement-plans', $portalRouteParams) }}">Return to annual plans</a>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-think-tank.partials.shell>
