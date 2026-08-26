@extends(auth()->user()?->user_type === 'vendor' ? 'layouts.vendor' : 'layouts.app')

@section('title', 'My Evaluations | ATTP')

@section('content')
    @php
        $workload = [
            ['label' => 'Assignments', 'value' => $stats['assignments'] ?? $assignments->count(), 'help' => 'Evaluation forms assigned to you', 'icon' => 'feather-clipboard', 'tone' => 'blue'],
            ['label' => 'Application tasks', 'value' => $stats['tasks'] ?? 0, 'help' => 'Applications in your worklist', 'icon' => 'feather-file-text', 'tone' => 'violet'],
            ['label' => 'To complete', 'value' => $stats['pending'] ?? 0, 'help' => 'Not started or saved as draft', 'icon' => 'feather-clock', 'tone' => 'amber'],
            ['label' => 'Completed', 'value' => $stats['completed'] ?? 0, 'help' => 'Final evaluations submitted', 'icon' => 'feather-check-circle', 'tone' => 'green'],
        ];
    @endphp

    <div class="nxl-container my-evaluations-page">
        <header class="evaluation-page-hero">
            <div>
                <span class="evaluation-page-eyebrow">Evaluator workspace</span>
                <h1>My Evaluations</h1>
                <p>Open an assigned application to start scoring, continue a saved draft, or review completed work.</p>
            </div>
            <span class="assignment-total">
                <i class="feather-user-check" aria-hidden="true"></i>
                {{ number_format($stats['assignments'] ?? $assignments->count()) }}
                {{ \Illuminate\Support\Str::plural('assignment', $stats['assignments'] ?? $assignments->count()) }}
            </span>
        </header>

        @if (session('success'))
            <div class="alert alert-success d-flex align-items-start gap-2 mb-4" role="status">
                <i class="feather-check-circle mt-1" aria-hidden="true"></i><span>{{ session('success') }}</span>
            </div>
        @endif

        @if (session('warning'))
            <div class="alert alert-warning d-flex align-items-start gap-2 mb-4" role="status">
                <i class="feather-alert-triangle mt-1" aria-hidden="true"></i><span>{{ session('warning') }}</span>
            </div>
        @endif

        <section class="workload-summary" aria-label="Evaluation workload summary">
            @foreach ($workload as $item)
                <article class="workload-card workload-card--{{ $item['tone'] }}">
                    <span class="workload-icon"><i class="{{ $item['icon'] }}" aria-hidden="true"></i></span>
                    <div>
                        <span>{{ $item['label'] }}</span>
                        <strong>{{ number_format($item['value']) }}</strong>
                        <small>{{ $item['help'] }}</small>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="method-guide" aria-labelledby="method-guide-title">
            <span class="method-guide-icon"><i class="feather-info" aria-hidden="true"></i></span>
            <div class="method-guide-copy">
                <strong id="method-guide-title">The evaluation method controls how you respond</strong>
                <p>Each application is assessed independently. Save unfinished work as a draft; final submissions are read-only.</p>
            </div>
            <div class="method-guide-options">
                <span><b class="method-dot method-dot--services"></b><strong>Services</strong> Numeric scores</span>
                <span><b class="method-dot method-dot--goods"></b><strong>Goods</strong> Yes or No</span>
                <span><b class="method-dot method-dot--eoi"></b><strong>EOI</strong> Qualification category</span>
            </div>
        </section>

        <section class="evaluation-worklist" aria-labelledby="evaluation-worklist-title">
            <header class="worklist-heading">
                <div>
                    <span class="worklist-kicker">Assigned work</span>
                    <h2 id="evaluation-worklist-title">Procurements requiring your evaluation</h2>
                    <p>Select an application below to open its evaluation form.</p>
                </div>
            </header>

            <div class="assignment-list">
                @forelse ($assignments as $assignment)
                    @php
                        $evaluation = $assignment->evaluation;
                        $procurement = $assignment->procurement;
                        $method = $evaluation?->type ?: \App\Models\Evaluation::TYPE_SERVICES;
                        $method = in_array($method, \App\Models\Evaluation::MANAGED_TYPES, true)
                            ? $method
                            : \App\Models\Evaluation::TYPE_SERVICES;
                        $methodPresentation = match ($method) {
                            \App\Models\Evaluation::TYPE_GOODS => [
                                'icon' => 'feather-package',
                                'mode' => 'Yes / No compliance',
                                'guidance' => 'Record a Yes or No decision for every criterion and explain the evidence in your evaluator comment.',
                            ],
                            \App\Models\Evaluation::TYPE_EOI => [
                                'icon' => 'feather-user-check',
                                'mode' => 'Qualification categories',
                                'guidance' => 'Classify every criterion as Qualified, Average Qualified, or Not Qualified.',
                            ],
                            default => [
                                'icon' => 'feather-bar-chart-2',
                                'mode' => 'Numeric scoring',
                                'guidance' => 'Score every criterion numerically without exceeding its defined maximum.',
                            ],
                        };
                        $methodLabel = $evaluation?->typeLabel() ?? 'Services';
                        $assignmentApplications = $applicationsByAssignmentId
                            ->get((string) $assignment->getKey(), collect());
                        $completedForAssignment = $assignmentApplications->filter(function ($application) use ($evaluationSubmissions, $assignment): bool {
                            $key = implode(':', [$assignment->evaluation_id, $assignment->procurement_id, $application->id]);

                            return filled($evaluationSubmissions->get($key)?->submitted_at);
                        })->count();
                        $applicationTotal = $assignmentApplications->count();
                        $completion = $applicationTotal > 0
                            ? min(100, (int) round(($completedForAssignment / $applicationTotal) * 100))
                            : 0;
                    @endphp

                    <article class="assignment-card method-{{ $method }}">
                        <header class="assignment-heading">
                            <span class="assignment-method-icon">
                                <i class="{{ $methodPresentation['icon'] }}" aria-hidden="true"></i>
                            </span>

                            <div class="assignment-title">
                                <div class="assignment-labels">
                                    <span class="method-label">{{ $methodLabel }}</span>
                                    <span>{{ $assignment->form_submission_id ? 'Specific application' : 'All applications' }}</span>
                                </div>
                                <h3>{{ $procurement?->title ?? 'Procurement evaluation' }}</h3>
                                <p>{{ $evaluation?->name ?? 'Evaluation form' }}</p>
                            </div>

                            <div class="assignment-completion" aria-label="{{ $completedForAssignment }} of {{ $applicationTotal }} applications completed">
                                <strong>{{ $completedForAssignment }}/{{ $applicationTotal }}</strong>
                                <span>completed</span>
                                <div class="progress" role="progressbar" aria-valuenow="{{ $completion }}"
                                    aria-valuemin="0" aria-valuemax="100" aria-label="Assignment completion">
                                    <div class="progress-bar" style="width: {{ $completion }}%"></div>
                                </div>
                            </div>
                        </header>

                        <div class="assignment-context">
                            <span><i class="feather-hash" aria-hidden="true"></i>{{ $procurement?->reference_no ?: 'Reference not provided' }}</span>
                            <span><i class="feather-calendar" aria-hidden="true"></i>Assigned {{ ($assignment->assigned_at ?: $assignment->created_at)?->format('d M Y') ?? 'recently' }}</span>
                        </div>

                        <div class="assignment-guidance">
                            <i class="feather-compass" aria-hidden="true"></i>
                            <div><strong>{{ $methodPresentation['mode'] }}</strong><span>{{ $methodPresentation['guidance'] }}</span></div>
                        </div>

                        <div class="application-list">
                            <div class="application-list-heading" aria-hidden="true">
                                <span>Application</span><span>Applicant</span><span>Submitted</span><span>Status</span><span>Action</span>
                            </div>

                            @forelse ($assignmentApplications as $application)
                                @php
                                    $submissionKey = implode(':', [$assignment->evaluation_id, $assignment->procurement_id, $application->id]);
                                    $evaluationSubmission = $evaluationSubmissions->get($submissionKey);
                                    $isCompleted = filled($evaluationSubmission?->submitted_at);
                                    $isDraft = $evaluationSubmission && ! $isCompleted;
                                    $taskStatus = $isCompleted ? 'Completed' : ($isDraft ? 'Draft saved' : 'Not started');
                                    $taskTone = $isCompleted ? 'complete' : ($isDraft ? 'draft' : 'new');
                                    $applicationDate = $application->submitted_at ?: $application->created_at;
                                @endphp

                                <article class="application-row">
                                    <div class="application-cell" data-label="Application">
                                        <strong>{{ $application->procurement_submission_code ?: 'Submission' }}</strong>
                                        <small>{{ $application->form?->name ?: 'Application form' }}</small>
                                    </div>
                                    <div class="application-cell" data-label="Applicant">
                                        <strong>{{ $application->submitter?->name ?: 'Applicant' }}</strong>
                                        <small>{{ $application->submitter?->email ?: 'Applicant account' }}</small>
                                    </div>
                                    <div class="application-cell" data-label="Submitted">
                                        <span>{{ $applicationDate?->format('d M Y') ?? 'Date unavailable' }}</span>
                                    </div>
                                    <div class="application-cell" data-label="Status">
                                        <span class="task-status task-status--{{ $taskTone }}">
                                            <i class="{{ $isCompleted ? 'feather-check-circle' : ($isDraft ? 'feather-save' : 'feather-circle') }}" aria-hidden="true"></i>
                                            {{ $taskStatus }}
                                        </span>
                                    </div>
                                    <div class="application-cell application-action" data-label="Action">
                                        @if ($isCompleted)
                                            <a class="task-action task-action--view" href="{{ route('my.eval.view', [$assignment, $application]) }}">
                                                <i class="feather-eye" aria-hidden="true"></i>View evaluation
                                            </a>
                                        @elseif ($isDraft)
                                            <a class="task-action task-action--continue" href="{{ route('my.eval.start', [$assignment, $application]) }}">
                                                <i class="feather-edit-3" aria-hidden="true"></i>Continue
                                            </a>
                                        @else
                                            <a class="task-action" href="{{ route('my.eval.start', [$assignment, $application]) }}">
                                                <i class="feather-play" aria-hidden="true"></i>Start evaluation
                                            </a>
                                        @endif
                                    </div>
                                </article>
                            @empty
                                <div class="no-applications">
                                    <span><i class="feather-inbox" aria-hidden="true"></i></span>
                                    <div>
                                        <strong>No applications are ready for evaluation</strong>
                                        <p>This assignment will become actionable when an eligible application is submitted.</p>
                                    </div>
                                </div>
                            @endforelse
                        </div>
                    </article>
                @empty
                    <div class="empty-worklist">
                        <span><i class="feather-clipboard" aria-hidden="true"></i></span>
                        <h3>No evaluation assignments yet</h3>
                        <p>When an evaluation is assigned to your account, its procurement and applications will appear here.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
@endsection

@push('styles')
    <style>
        .my-evaluations-page { --eval-ink:#172033; --eval-muted:#667085; --eval-border:#e4e9f1; color:var(--eval-ink); }
        .evaluation-page-hero { display:flex; align-items:center; justify-content:space-between; gap:1.5rem; margin-bottom:1.5rem; padding:1.55rem 1.7rem; color:#fff; border-radius:18px; background:radial-gradient(circle at 88% 15%,rgba(255,255,255,.16),transparent 30%),linear-gradient(130deg,#17296b,#3157d5 65%,#4d74e8); box-shadow:0 18px 40px rgba(35,68,178,.15); }
        .evaluation-page-eyebrow,.worklist-kicker { display:block; margin-bottom:.3rem; font-size:.72rem; font-weight:750; letter-spacing:.1em; text-transform:uppercase; }
        .evaluation-page-eyebrow { color:rgba(255,255,255,.7); }
        .evaluation-page-hero h1 { margin:0; color:#fff; font-size:clamp(1.6rem,2.5vw,2.25rem); font-weight:760; }
        .evaluation-page-hero p { max-width:680px; margin:.45rem 0 0; color:rgba(255,255,255,.78); font-size:.95rem; }
        .assignment-total { display:inline-flex; flex:0 0 auto; align-items:center; gap:.55rem; padding:.7rem .9rem; color:#fff; border:1px solid rgba(255,255,255,.2); border-radius:12px; background:rgba(255,255,255,.11); font-weight:700; }

        .workload-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:1rem; margin-bottom:1.25rem; }
        .workload-card { display:flex; min-width:0; align-items:center; gap:.85rem; padding:1rem; border:1px solid var(--eval-border); border-radius:14px; background:#fff; box-shadow:0 6px 18px rgba(20,34,66,.045); }
        .workload-icon { display:grid; width:43px; height:43px; flex:0 0 43px; place-items:center; color:var(--metric-color); border-radius:12px; background:var(--metric-soft); font-size:1.05rem; }
        .workload-card--blue { --metric-color:#3157d5; --metric-soft:#eef2ff; }
        .workload-card--violet { --metric-color:#6941c6; --metric-soft:#f4f0ff; }
        .workload-card--amber { --metric-color:#b54708; --metric-soft:#fff4e5; }
        .workload-card--green { --metric-color:#067647; --metric-soft:#eafaf1; }
        .workload-card span,.workload-card strong,.workload-card small { display:block; }
        .workload-card>div>span { color:var(--eval-muted); font-size:.78rem; font-weight:650; }
        .workload-card strong { margin:.05rem 0; color:var(--eval-ink); font-size:1.35rem; line-height:1.2; }
        .workload-card small { overflow:hidden; color:#8a94a6; font-size:.7rem; text-overflow:ellipsis; white-space:nowrap; }

        .method-guide { display:grid; grid-template-columns:auto minmax(220px,1fr) auto; align-items:center; gap:.9rem; margin-bottom:1.7rem; padding:.95rem 1rem; border:1px solid #cfdcf9; border-radius:14px; background:#f5f8ff; }
        .method-guide-icon { display:grid; width:38px; height:38px; place-items:center; color:#3157d5; border-radius:10px; background:#e5ecff; }
        .method-guide-copy strong { display:block; color:#253b80; font-size:.86rem; }
        .method-guide-copy p { margin:.15rem 0 0; color:#62709a; font-size:.78rem; }
        .method-guide-options { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:.45rem; }
        .method-guide-options>span { display:inline-flex; align-items:center; gap:.35rem; padding:.38rem .5rem; color:#526074; border:1px solid #e1e7f1; border-radius:8px; background:#fff; font-size:.7rem; }
        .method-dot { width:7px; height:7px; border-radius:50%; }
        .method-dot--services { background:#3157d5; } .method-dot--goods { background:#dc6803; } .method-dot--eoi { background:#7f56d9; }

        .worklist-heading { display:flex; align-items:end; justify-content:space-between; gap:1rem; margin-bottom:.85rem; }
        .worklist-kicker { color:#3157d5; }
        .worklist-heading h2 { margin:0; color:var(--eval-ink); font-size:1.18rem; font-weight:750; }
        .worklist-heading p { margin:.25rem 0 0; color:var(--eval-muted); font-size:.82rem; }
        .assignment-list { display:grid; gap:1rem; }
        .assignment-card { --method-color:#3157d5; --method-soft:#eef2ff; overflow:hidden; border:1px solid var(--eval-border); border-radius:16px; background:#fff; box-shadow:0 7px 22px rgba(20,34,66,.055); }
        .assignment-card.method-goods { --method-color:#b54708; --method-soft:#fff4e5; }
        .assignment-card.method-eoi { --method-color:#6941c6; --method-soft:#f4f0ff; }
        .assignment-heading { display:grid; grid-template-columns:auto minmax(0,1fr) auto; align-items:center; gap:.9rem; padding:1rem 1.1rem .85rem; }
        .assignment-method-icon { display:grid; width:46px; height:46px; place-items:center; color:var(--method-color); border-radius:13px; background:var(--method-soft); font-size:1.1rem; }
        .assignment-labels { display:flex; flex-wrap:wrap; gap:.35rem; margin-bottom:.3rem; }
        .assignment-labels span { padding:.22rem .42rem; color:#667085; border-radius:6px; background:#f2f4f7; font-size:.66rem; font-weight:700; text-transform:uppercase; }
        .assignment-labels .method-label { color:var(--method-color); background:var(--method-soft); }
        .assignment-title h3 { margin:0; color:var(--eval-ink); font-size:1rem; font-weight:740; }
        .assignment-title p { margin:.16rem 0 0; color:var(--eval-muted); font-size:.77rem; }
        .assignment-completion { min-width:100px; text-align:right; }
        .assignment-completion strong,.assignment-completion span { display:block; }
        .assignment-completion strong { color:var(--eval-ink); font-size:1rem; }
        .assignment-completion span { color:var(--eval-muted); font-size:.68rem; }
        .assignment-completion .progress { width:90px; height:5px; margin:.35rem 0 0 auto; background:#edf0f5; }
        .assignment-completion .progress-bar { background:var(--method-color); }
        .assignment-context { display:flex; flex-wrap:wrap; gap:.65rem 1.2rem; padding:0 1.1rem .85rem; color:var(--eval-muted); font-size:.72rem; }
        .assignment-context span { display:inline-flex; align-items:center; gap:.35rem; }
        .assignment-context i { color:var(--method-color); }
        .assignment-guidance { display:flex; align-items:flex-start; gap:.65rem; margin:0 1.1rem .9rem; padding:.7rem .75rem; color:var(--method-color); border:1px solid color-mix(in srgb,var(--method-color) 16%,white); border-radius:10px; background:var(--method-soft); }
        .assignment-guidance>i { margin-top:.12rem; }
        .assignment-guidance strong,.assignment-guidance span { display:block; }
        .assignment-guidance strong { font-size:.74rem; }
        .assignment-guidance span { margin-top:.1rem; color:#657087; font-size:.71rem; line-height:1.45; }

        .application-list { border-top:1px solid #edf0f4; }
        .application-list-heading,.application-row { display:grid; grid-template-columns:minmax(160px,1.15fr) minmax(170px,1.25fr) 110px 125px 145px; align-items:center; gap:.75rem; }
        .application-list-heading { padding:.55rem 1.1rem; color:#7a8495; background:#fafbfc; font-size:.65rem; font-weight:750; letter-spacing:.04em; text-transform:uppercase; }
        .application-row { padding:.85rem 1.1rem; border-top:1px solid #edf0f4; }
        .application-row:first-of-type { border-top:0; }
        .application-row:hover { background:#fbfcff; }
        .application-cell { min-width:0; }
        .application-cell strong,.application-cell small { display:block; }
        .application-cell strong { overflow:hidden; color:#344054; font-size:.76rem; text-overflow:ellipsis; white-space:nowrap; }
        .application-cell small { overflow:hidden; margin-top:.12rem; color:#8a94a6; font-size:.68rem; text-overflow:ellipsis; white-space:nowrap; }
        .application-cell>span:not(.task-status) { color:#596579; font-size:.73rem; }
        .task-status { display:inline-flex; align-items:center; gap:.3rem; padding:.32rem .45rem; border-radius:7px; font-size:.68rem; font-weight:700; white-space:nowrap; }
        .task-status--new { color:#475467; background:#f2f4f7; }
        .task-status--draft { color:#b54708; background:#fff4e5; }
        .task-status--complete { color:#067647; background:#eafaf1; }
        .task-action { display:inline-flex; min-height:36px; align-items:center; justify-content:center; gap:.35rem; padding:.43rem .65rem; color:#fff; border:1px solid #3157d5; border-radius:8px; background:#3157d5; font-size:.7rem; font-weight:720; text-decoration:none; white-space:nowrap; }
        .task-action:hover { color:#fff; background:#2648b8; }
        .task-action--continue { color:#3157d5; background:#fff; }
        .task-action--continue:hover { color:#2648b8; background:#eef2ff; }
        .task-action--view { color:#067647; border-color:#8bd7b2; background:#fff; }
        .task-action--view:hover { color:#05603a; background:#eafaf1; }

        .no-applications,.empty-worklist { display:flex; align-items:center; justify-content:center; gap:.75rem; min-height:105px; padding:1.25rem; color:var(--eval-muted); text-align:left; }
        .no-applications>span,.empty-worklist>span { display:grid; width:42px; height:42px; flex:0 0 42px; place-items:center; color:#7a8aa3; border-radius:12px; background:#f1f4f8; }
        .no-applications strong { display:block; color:#475467; font-size:.8rem; }
        .no-applications p,.empty-worklist p { margin:.15rem 0 0; font-size:.73rem; }
        .empty-worklist { min-height:250px; flex-direction:column; border:1px dashed #cdd5df; border-radius:16px; background:#fff; text-align:center; }
        .empty-worklist>span { width:56px; height:56px; flex-basis:56px; font-size:1.25rem; }
        .empty-worklist h3 { margin:0; color:#344054; font-size:1rem; }
        .empty-worklist p { max-width:460px; }
        @supports not (border-color:color-mix(in srgb,red 10%,white)) { .assignment-guidance { border-color:#e0e6ef; } }

        @media (max-width:1199.98px) {
            .workload-summary { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .method-guide { grid-template-columns:auto 1fr; }
            .method-guide-options { grid-column:2; justify-content:flex-start; }
            .application-list-heading,.application-row { grid-template-columns:minmax(150px,1.1fr) minmax(150px,1fr) 100px 115px 125px; }
        }
        @media (max-width:767.98px) {
            .evaluation-page-hero { align-items:flex-start; flex-direction:column; padding:1.2rem; }
            .assignment-total { width:100%; justify-content:center; }
            .workload-summary { grid-template-columns:1fr 1fr; gap:.7rem; }
            .workload-card { align-items:flex-start; padding:.8rem; }
            .workload-icon { width:36px; height:36px; flex-basis:36px; }
            .workload-card small { white-space:normal; }
            .method-guide { grid-template-columns:auto 1fr; }
            .method-guide-options { grid-column:1/-1; justify-content:flex-start; }
            .assignment-heading { grid-template-columns:auto minmax(0,1fr); }
            .assignment-completion { grid-column:1/-1; width:100%; text-align:left; }
            .assignment-completion .progress { width:100%; margin-left:0; }
            .assignment-context { flex-direction:column; gap:.35rem; }
            .application-list-heading { display:none; }
            .application-row { display:grid; grid-template-columns:1fr 1fr; gap:.8rem; padding:1rem; }
            .application-cell::before { display:block; margin-bottom:.25rem; color:#98a2b3; content:attr(data-label); font-size:.63rem; font-weight:750; letter-spacing:.04em; text-transform:uppercase; }
            .application-action { grid-column:1/-1; }
            .task-action { width:100%; min-height:42px; }
        }
        @media (max-width:479.98px) {
            .workload-summary { grid-template-columns:1fr; }
            .application-row { grid-template-columns:1fr; }
            .application-action { grid-column:auto; }
            .assignment-heading { align-items:flex-start; }
            .assignment-method-icon { width:40px; height:40px; }
        }
    </style>
@endpush
