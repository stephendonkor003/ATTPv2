@extends(auth()->user()?->user_type === 'vendor' ? 'layouts.vendor' : 'layouts.app')

@section('content')
    @php
        $evaluation = $assignment->evaluation;
        $isNumeric = $evaluation->usesNumericScoring();
        $isCategorical = $evaluation->usesCategoricalDecisions();
        $decisionOptions = $evaluation->decisionOptions();
        $sectionOutline = \App\Support\EvaluationSectionHierarchy::flattened($evaluation);
        $sectionGroups = $sectionOutline->groupBy('root_index');
        $rootNodes = $sectionOutline->where('depth', 0)->values();
        $totalQuestions = $sectionOutline->sum(
            fn (array $node) => $node['section']->criteria->count(),
        );
        $overallMaximum = $isNumeric
            ? $sectionOutline->sum(fn (array $node) => (float) $node['section']->criteria->sum('max_score'))
            : 0;
        $overallAnswered = $submission->criteriaScores->filter(
            fn ($score) => $isNumeric
                ? $score->score !== null
                : $evaluation->decisionLabel($score->decision) !== null,
        )->count();
        $overallCompletion = $totalQuestions > 0
            ? min(100, round(($overallAnswered / $totalQuestions) * 100))
            : 0;
        $overallScorePercentage = $isNumeric && $overallMaximum > 0
            ? min(100, round((((float) $submission->overall_score) / $overallMaximum) * 100, 1))
            : 0;
        $overallDecisionDistribution = $isCategorical
            ? collect($decisionOptions)->mapWithKeys(function (string $label, int $decision) use ($submission): array {
                return [
                    $label => $submission->criteriaScores->filter(
                        fn ($score) => $score->decision !== null && (int) $score->decision === $decision,
                    )->count(),
                ];
            })
            : collect();
    @endphp

    <div class="nxl-container evaluator-workspace evaluation-readonly">
        <header class="evaluation-hero mb-4">
            <div class="hero-copy">
                <span class="hero-eyebrow">Submitted evaluation</span>
                <h1>{{ $assignment->procurement->title }}</h1>
                <p>
                    {{ $evaluation->name }} · {{ $evaluation->typeLabel() }}
                    <span aria-hidden="true">•</span>
                    {{ $totalQuestions }} {{ \Illuminate\Support\Str::plural('question', $totalQuestions) }}
                </p>
            </div>
            <div class="hero-actions">
                @can('evaluations.view_all')
                    <a href="{{ route('reports.evaluations.submission.pdf', $submission) }}" class="btn btn-light btn-sm">
                        <i class="feather-download me-1" aria-hidden="true"></i>Download PDF
                    </a>
                @endcan
                <a href="{{ route('my.eval.index') }}" class="btn btn-outline-light btn-sm">
                    <i class="feather-arrow-left me-1" aria-hidden="true"></i>My Evaluations
                </a>
            </div>
        </header>

        <div class="submitted-banner mb-4" role="status">
            <span><i class="feather-check-circle" aria-hidden="true"></i></span>
            <div>
                <strong>Evaluation submitted</strong>
                <p>Finalised {{ optional($submission->submitted_at)->format('d M Y, H:i') }}. This record is read-only.</p>
            </div>
            <b>{{ $overallCompletion }}% complete</b>
        </div>

        <div class="row g-4 align-items-start">
            <main class="col-xl-9 col-lg-8">
                @include('evaluations.partials.technical-proposal-dossier', ['proposalTarget' => $proposalTarget ?? null])

                <details class="applicant-panel mb-4">
                    <summary>
                        <span class="summary-icon"><i class="feather-file-text" aria-hidden="true"></i></span>
                        <span>
                            <strong>{{ !empty($proposalTarget) ? 'Original EOI application background' : 'Applicant submitted information' }}</strong>
                            <small>{{ $applicant->procurement_submission_code }} · {{ optional($applicant->submitter)->name ?? 'Applicant' }}</small>
                        </span>
                        <i class="feather-chevron-down summary-chevron" aria-hidden="true"></i>
                    </summary>
                    <div class="applicant-panel-body">
                        <div class="applicant-facts">
                            <div><span>Submission code</span><strong>{{ $applicant->procurement_submission_code }}</strong></div>
                            <div><span>Applicant</span><strong>{{ optional($applicant->submitter)->name ?? '—' }}</strong></div>
                            <div><span>Submitted</span><strong>{{ $applicant->created_at->format('d M Y, H:i') }}</strong></div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm applicant-table align-middle mb-0">
                                <tbody>
                                    @foreach ($applicant->values as $value)
                                        @php
                                            $rawValue = $value->value;
                                            $decodedValue = is_string($rawValue) ? json_decode($rawValue, true) : null;
                                        @endphp
                                        <tr>
                                            <th>{{ ucwords(str_replace('_', ' ', $value->field_key)) }}</th>
                                            <td>
                                                @if (is_string($rawValue) && Str::contains($rawValue, 'procurement_submissions'))
                                                    <a href="{{ route('procurement.submissions.values.download', ['submission' => $applicant->id, 'value' => $value->id]) }}"
                                                        target="_blank" class="btn btn-sm btn-outline-primary me-1">View</a>
                                                    <a href="{{ route('procurement.submissions.values.download', ['submission' => $applicant->id, 'value' => $value->id, 'download' => 1]) }}"
                                                        download class="btn btn-sm btn-outline-secondary">Download</a>
                                                @elseif (is_array($decodedValue))
                                                    @foreach ($decodedValue as $item)
                                                        <span class="value-chip">{{ is_scalar($item) ? $item : json_encode($item) }}</span>
                                                    @endforeach
                                                @else
                                                    {{ filled($rawValue) ? $rawValue : '—' }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </details>

                <nav class="root-jump-nav mb-4" aria-label="Main evaluation sections">
                    <span>Jump to</span>
                    @foreach ($rootNodes as $rootNode)
                        <a href="#evaluation-section-{{ $rootNode['section']->id }}"
                            class="root-jump-link hierarchy-tone-{{ $rootNode['root_index'] % 8 }}">
                            <b>{{ $rootNode['number'] }}</b>{{ $rootNode['section']->name }}
                        </a>
                    @endforeach
                </nav>

                @forelse ($sectionGroups as $rootIndex => $nodes)
                    <section class="assessment-branch hierarchy-tone-{{ $rootIndex % 8 }}">
                        @foreach ($nodes as $node)
                            @php
                                $section = $node['section'];
                                $depth = (int) $node['depth'];
                                $directQuestionCount = $section->criteria->count();
                                $branchCriteria = $section->subtreeCriteria();
                                $branchCriteriaIds = $branchCriteria->pluck('id');
                                $branchQuestionCount = $branchCriteria->count();
                                $branchScores = $submission->criteriaScores->whereIn(
                                    'evaluation_criteria_id',
                                    $branchCriteriaIds,
                                );
                                $branchAnswered = $branchScores->filter(
                                    fn ($score) => $isNumeric
                                        ? $score->score !== null
                                        : $evaluation->decisionLabel($score->decision) !== null,
                                )->count();
                                $branchCompletion = $branchQuestionCount > 0
                                    ? min(100, round(($branchAnswered / $branchQuestionCount) * 100))
                                    : 0;
                                $sectionScore = $submission->sectionScores->firstWhere(
                                    'evaluation_section_id',
                                    $section->id,
                                );
                                $branchMaximum = $isNumeric ? $section->subtotalMaxScore() : 0;
                                $branchSubtotal = $isNumeric
                                    ? \App\Support\EvaluationSectionHierarchy::numericSubtotal($submission, $section)
                                    : 0;
                                $branchScorePercentage = $isNumeric && $branchMaximum > 0
                                    ? min(100, round(($branchSubtotal / $branchMaximum) * 100, 1))
                                    : 0;
                                $decisionDistribution = $isCategorical
                                    ? \App\Support\EvaluationSectionHierarchy::decisionDistribution($submission, $section)
                                    : [];
                                $contentId = 'evaluation-section-content-' . $section->id;
                            @endphp

                            <article id="evaluation-section-{{ $section->id }}"
                                class="assessment-node hierarchy-tone-{{ $node['root_index'] % 8 }} depth-{{ $depth }} {{ $depth === 0 ? 'is-root' : '' }}">
                                <header class="assessment-node-header">
                                    <button type="button" class="node-toggle" data-node-toggle
                                        aria-expanded="true" aria-controls="{{ $contentId }}"
                                        title="Collapse {{ $section->name }}">
                                        <i class="feather-chevron-down" aria-hidden="true"></i>
                                        <span class="visually-hidden">Collapse {{ $section->name }}</span>
                                    </button>

                                    <span class="outline-marker">{{ $node['number'] }}</span>
                                    <div class="node-heading">
                                        <span class="tier-label">{{ $node['label'] }}</span>
                                        <h2>{{ $section->name }}</h2>
                                        @if (filled($section->description))
                                            <p>{{ $section->description }}</p>
                                        @endif
                                    </div>

                                    <div class="node-status">
                                        <div class="question-count" title="Questions in this section and all nested sections">
                                            <i class="feather-list" aria-hidden="true"></i>
                                            <strong>{{ $branchQuestionCount }}</strong>
                                            {{ \Illuminate\Support\Str::plural('question', $branchQuestionCount) }}
                                            @if ($directQuestionCount !== $branchQuestionCount)
                                                <small>{{ $directQuestionCount }} here</small>
                                            @endif
                                        </div>

                                        <div class="completion-summary">
                                            <span><strong>{{ $branchAnswered }}</strong>/{{ $branchQuestionCount }} answered</span>
                                            <b>{{ $branchCompletion }}%</b>
                                            <div class="mini-progress" aria-hidden="true"><span style="width: {{ $branchCompletion }}%"></span></div>
                                        </div>

                                        @if ($section->show_subtotal && $isNumeric)
                                            <div class="subtotal-summary numeric-summary">
                                                <span>Score</span>
                                                <strong>{{ number_format($branchSubtotal, 2) }} / {{ number_format($branchMaximum, 2) }}</strong>
                                                <small>{{ number_format($branchScorePercentage, 1) }}% of available points</small>
                                            </div>
                                        @elseif ($section->show_subtotal && $isCategorical)
                                            <div class="subtotal-summary category-summary">
                                                @foreach ($decisionOptions as $decisionValue => $decisionLabel)
                                                    <span class="category-counter">
                                                        {{ $decisionLabel }}
                                                        <strong>{{ $decisionDistribution[$decisionLabel] ?? 0 }}</strong>
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </header>

                                <div class="assessment-node-body" id="{{ $contentId }}">
                                    @if ($section->criteria->isNotEmpty())
                                        <div class="table-responsive">
                                            <table class="table criteria-table align-middle mb-0">
                                                <thead>
                                                    <tr>
                                                        <th class="question-index-column">#</th>
                                                        <th>Evaluation question</th>
                                                        @if ($isNumeric)
                                                            <th class="text-center score-column">Maximum</th>
                                                            <th class="text-center score-column">Score</th>
                                                            <th>Evaluator response</th>
                                                        @else
                                                            <th class="decision-column">{{ $evaluation->isEoi() ? 'Qualification' : 'Decision' }}</th>
                                                            <th>Evidence comment</th>
                                                        @endif
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($section->criteria as $criterion)
                                                        @php
                                                            $score = $submission->criteriaScores->firstWhere(
                                                                'evaluation_criteria_id',
                                                                $criterion->id,
                                                            );
                                                            $decisionLabel = $evaluation->decisionLabel($score?->decision);
                                                            $decisionClass = match ($decisionLabel) {
                                                                'Yes', 'Qualified' => 'decision-positive',
                                                                'Average Qualified' => 'decision-average',
                                                                'No', 'Not Qualified' => 'decision-negative',
                                                                default => 'decision-empty',
                                                            };
                                                        @endphp
                                                        <tr>
                                                            <td class="question-index-column"><span class="question-number">{{ $loop->iteration }}</span></td>
                                                            <td>
                                                                <strong>{{ $criterion->name }}</strong>
                                                                @if (filled($criterion->description))
                                                                    <p>{{ $criterion->description }}</p>
                                                                @endif
                                                            </td>
                                                            @if ($isNumeric)
                                                                <td class="text-center score-maximum">{{ number_format($criterion->max_score, 2) }}</td>
                                                                <td class="text-center">
                                                                    <span class="score-result">{{ number_format($score?->score ?? 0, 2) }}</span>
                                                                </td>
                                                                <td class="evidence-copy">{{ $score?->comment ?: '—' }}</td>
                                                            @else
                                                                <td>
                                                                    <span class="decision-badge {{ $decisionClass }}">{{ $decisionLabel ?? 'Not answered' }}</span>
                                                                </td>
                                                                <td class="evidence-copy">{{ $score?->comment ?: '—' }}</td>
                                                            @endif
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="section-notes readonly-notes">
                                            <div>
                                                <span>Section strengths</span>
                                                <p>{{ $sectionScore?->strengths ?: '—' }}</p>
                                            </div>
                                            <div>
                                                <span>Section weaknesses</span>
                                                <p>{{ $sectionScore?->weaknesses ?: '—' }}</p>
                                            </div>
                                        </div>
                                    @else
                                        <div class="structural-node-note">
                                            <i class="feather-corner-down-right" aria-hidden="true"></i>
                                            <div>
                                                <strong>Grouping section</strong>
                                                <span>This heading organises {{ $branchQuestionCount }} {{ \Illuminate\Support\Str::plural('question', $branchQuestionCount) }} in its child sections. It has no separate response.</span>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </section>
                @empty
                    <div class="empty-evaluation mb-4">
                        <i class="feather-inbox" aria-hidden="true"></i>
                        <strong>No evaluation questions were configured.</strong>
                    </div>
                @endforelse

                <section class="overall-result mb-4">
                    <div class="overall-result-heading">
                        <span class="overall-icon"><i class="feather-activity" aria-hidden="true"></i></span>
                        <div><span>Whole evaluation</span><strong>Final overall summary</strong></div>
                    </div>
                    <div class="overall-completion">
                        <span><b>{{ $overallAnswered }}</b> / {{ $totalQuestions }} answered</span>
                        <strong>{{ $overallCompletion }}% complete</strong>
                    </div>
                    @if ($isNumeric)
                        <div class="overall-numeric">
                            <span>Overall score</span>
                            <strong>{{ number_format($submission->overall_score ?? 0, 2) }} / {{ number_format($overallMaximum, 2) }}</strong>
                            <small>{{ number_format($overallScorePercentage, 1) }}% of available points</small>
                        </div>
                    @else
                        <div class="overall-categories">
                            @foreach ($overallDecisionDistribution as $decisionLabel => $count)
                                <span class="overall-category">{{ $decisionLabel }} <strong>{{ $count }}</strong></span>
                            @endforeach
                            @if ($evaluation->isEoi())
                                <small>EOI categories are shown as counts only; no numeric rank is calculated.</small>
                            @endif
                        </div>
                    @endif
                </section>
            </main>

            <aside class="col-xl-3 col-lg-4">
                <div class="evaluator-sidebar">
                    <section class="status-card mb-3">
                        <span class="status-card-icon"><i class="feather-check" aria-hidden="true"></i></span>
                        <div><span>Status</span><strong>Finalised</strong><small>{{ optional($submission->submitted_at)->format('d M Y, H:i') }}</small></div>
                    </section>

                    <section class="identity-card mb-3">
                        <header><i class="feather-video" aria-hidden="true"></i> Identity proof</header>
                        <div class="identity-body">
                            @if ($submission->video_path)
                                <video controls preload="metadata">
                                    <source src="{{ route('my.eval.video', [$assignment->id, $applicant->id]) }}">
                                </video>
                                <p>Secure evaluator verification recording</p>
                            @else
                                <div class="identity-empty"><i class="feather-video-off" aria-hidden="true"></i>No identity video recorded.</div>
                            @endif
                        </div>
                    </section>

                    <section class="structure-key">
                        <strong>Form structure</strong>
                        @foreach (\App\Models\EvaluationSection::LEVEL_LABELS as $level => $label)
                            <span><b>{{ $level }}</b>{{ $label }}</span>
                        @endforeach
                    </section>
                </div>
            </aside>
        </div>
    </div>

    @include('evaluations.partials.hierarchy-theme')

    <style>
        .evaluator-workspace { --workspace-ink: #172033; --workspace-muted: #667085; --workspace-border: #e2e7ef; color: var(--workspace-ink); }
        .evaluation-hero { display: flex; align-items: center; justify-content: space-between; gap: 2rem; padding: 1.5rem 1.65rem; color: #fff; border-radius: 18px; background: radial-gradient(circle at 88% 12%, rgba(255,255,255,.16), transparent 28%), linear-gradient(128deg, #17296b 0%, #3157d5 62%, #4d74e8 100%); box-shadow: 0 18px 42px rgba(35,68,178,.16); }
        .hero-eyebrow { display: block; margin-bottom: .35rem; color: rgba(255,255,255,.72); font-size: .68rem; font-weight: 750; letter-spacing: .1em; text-transform: uppercase; }
        .evaluation-hero h1 { margin: 0; color: #fff; font-size: clamp(1.35rem, 2vw, 2rem); font-weight: 750; letter-spacing: -.025em; }
        .evaluation-hero p { display: flex; flex-wrap: wrap; align-items: center; gap: .45rem; margin: .45rem 0 0; color: rgba(255,255,255,.78); font-size: .86rem; }
        .hero-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .5rem; }

        .submitted-banner { display: flex; align-items: center; gap: .75rem; padding: .8rem 1rem; border: 1px solid #b7e4cc; border-radius: 13px; background: #effaf4; }
        .submitted-banner > span { display: grid; width: 35px; height: 35px; place-items: center; color: #087647; border-radius: 9px; background: #d8f3e5; }
        .submitted-banner > div { flex: 1; }
        .submitted-banner strong { color: #176b43; font-size: .76rem; }
        .submitted-banner p { margin: .12rem 0 0; color: #638070; font-size: .66rem; }
        .submitted-banner > b { color: #087647; font-size: .76rem; }

        .applicant-panel { overflow: hidden; border: 1px solid var(--workspace-border); border-radius: 14px; background: #fff; box-shadow: 0 5px 16px rgba(20,34,66,.04); }
        .applicant-panel summary { display: flex; align-items: center; gap: .7rem; padding: .85rem 1rem; cursor: pointer; list-style: none; }
        .applicant-panel summary::-webkit-details-marker { display: none; }
        .summary-icon, .overall-icon { display: grid; flex: 0 0 38px; width: 38px; height: 38px; place-items: center; border-radius: 10px; }
        .summary-icon { color: #3157d5; background: #eef2ff; }
        .applicant-panel summary > span:nth-child(2) { display: flex; flex: 1; flex-direction: column; }
        .applicant-panel summary strong { font-size: .8rem; }
        .applicant-panel summary small { margin-top: .1rem; color: var(--workspace-muted); font-size: .68rem; }
        .summary-chevron { color: #98a2b3; transition: transform .2s ease; }
        .applicant-panel[open] .summary-chevron { transform: rotate(180deg); }
        .applicant-panel-body { padding: 1rem; border-top: 1px solid var(--workspace-border); }
        .applicant-facts { display: grid; grid-template-columns: repeat(3, 1fr); gap: .7rem; margin-bottom: 1rem; }
        .applicant-facts > div { padding: .65rem .75rem; border-radius: 9px; background: #f8fafc; }
        .applicant-facts span, .applicant-facts strong { display: block; }
        .applicant-facts span { color: var(--workspace-muted); font-size: .62rem; text-transform: uppercase; }
        .applicant-facts strong { margin-top: .15rem; font-size: .74rem; }
        .applicant-table { font-size: .73rem; }
        .applicant-table th { width: 30%; color: #475467; background: #f8fafc; font-weight: 650; }
        .applicant-table th, .applicant-table td { padding: .55rem .65rem; border-color: #edf0f4; }
        .value-chip { display: inline-flex; margin: .1rem .2rem .1rem 0; padding: .2rem .45rem; border-radius: 999px; background: #f2f4f7; font-size: .65rem; }

        .root-jump-nav { display: flex; align-items: center; flex-wrap: wrap; gap: .45rem; padding: .55rem .65rem; border: 1px solid var(--workspace-border); border-radius: 11px; background: #fff; }
        .root-jump-nav > span { margin-right: .15rem; color: var(--workspace-muted); font-size: .64rem; font-weight: 700; text-transform: uppercase; }
        .root-jump-link { display: inline-flex; align-items: center; gap: .35rem; max-width: 240px; padding: .3rem .5rem; overflow: hidden; color: var(--section-deep); border: 1px solid color-mix(in srgb, var(--section-color) 20%, white); border-radius: 8px; background: var(--section-soft); font-size: .66rem; font-weight: 650; text-decoration: none; text-overflow: ellipsis; white-space: nowrap; }
        .root-jump-link:hover { color: #fff; background: var(--section-color); }
        .root-jump-link b { display: grid; min-width: 19px; height: 19px; place-items: center; color: #fff; border-radius: 5px; background: var(--section-color); font-size: .58rem; }

        .assessment-branch { position: relative; margin-bottom: 1.25rem; }
        .assessment-node { position: relative; margin-bottom: .75rem; border: 1px solid var(--workspace-border); border-left: 4px solid var(--section-color); border-radius: 13px; background: #fff; box-shadow: 0 4px 14px rgba(20,34,66,.045); scroll-margin-top: 1rem; }
        .assessment-node.depth-1 { margin-left: 1.25rem; }
        .assessment-node.depth-2 { margin-left: 2.5rem; }
        .assessment-node.depth-3 { margin-left: 3.75rem; }
        .assessment-node:not(.is-root)::before { position: absolute; top: -13px; left: -17px; width: 13px; height: 33px; border-bottom: 1px solid color-mix(in srgb, var(--section-color) 35%, white); border-left: 1px solid color-mix(in srgb, var(--section-color) 35%, white); border-radius: 0 0 0 8px; content: ''; }
        .assessment-node.is-root { border-left-width: 5px; box-shadow: 0 7px 22px color-mix(in srgb, var(--section-color) 10%, transparent); }
        .assessment-node-header { display: flex; align-items: flex-start; gap: .65rem; padding: .8rem .9rem; background: linear-gradient(90deg, var(--section-soft), #fff 44%); border-radius: 12px 12px 0 0; }
        .assessment-node.is-root .assessment-node-header { padding-block: .95rem; background: linear-gradient(98deg, var(--section-soft), #fff 56%); }
        .node-toggle { display: grid; flex: 0 0 27px; width: 27px; height: 27px; margin-top: .18rem; padding: 0; place-items: center; color: var(--section-deep); border: 0; border-radius: 7px; background: rgba(255,255,255,.75); }
        .node-toggle[aria-expanded="false"] i { transform: rotate(-90deg); }
        .outline-marker { display: grid; flex: 0 0 auto; min-width: 36px; height: 36px; padding: 0 .3rem; place-items: center; color: #fff; border-radius: 9px; background: var(--section-color); font-size: .65rem; font-weight: 750; }
        .node-heading { min-width: 140px; flex: 1; padding-top: .05rem; }
        .tier-label { display: block; color: var(--section-deep); font-size: .57rem; font-weight: 750; letter-spacing: .075em; text-transform: uppercase; }
        .node-heading h2 { margin: .12rem 0 0; color: var(--workspace-ink); font-size: .86rem; font-weight: 720; }
        .assessment-node.is-root .node-heading h2 { font-size: .96rem; }
        .node-heading p { margin: .18rem 0 0; color: var(--workspace-muted); font-size: .66rem; line-height: 1.4; }
        .node-status { display: flex; max-width: 52%; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: .4rem; }
        .question-count, .completion-summary, .subtotal-summary { border-radius: 8px; font-size: .62rem; }
        .question-count { display: inline-flex; align-items: center; gap: .3rem; padding: .34rem .48rem; color: #475467; border: 1px solid #e5e9ef; background: #fff; }
        .question-count i { color: var(--section-color); }
        .question-count small { padding-left: .32rem; color: #98a2b3; border-left: 1px solid #e5e7eb; }
        .completion-summary { display: grid; grid-template-columns: 1fr auto; min-width: 126px; gap: .15rem .4rem; padding: .34rem .48rem; color: #475467; border: 1px solid #e5e9ef; background: #fff; }
        .completion-summary b { color: var(--section-deep); }
        .mini-progress { grid-column: 1 / -1; height: 3px; overflow: hidden; border-radius: 99px; background: #e9edf2; }
        .mini-progress span { display: block; height: 100%; border-radius: inherit; background: var(--section-color); }
        .subtotal-summary { padding: .38rem .52rem; color: var(--section-deep); border: 1px solid color-mix(in srgb, var(--section-color) 25%, white); background: var(--section-soft); }
        .numeric-summary span, .numeric-summary strong, .numeric-summary small { display: block; }
        .numeric-summary > span { font-size: .55rem; font-weight: 700; text-transform: uppercase; }
        .numeric-summary strong { margin: .05rem 0; font-size: .72rem; }
        .numeric-summary small { opacity: .78; font-size: .56rem; }
        .category-summary { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .28rem; }
        .category-counter { display: inline-flex; align-items: center; gap: .25rem; padding: .22rem .35rem; border-radius: 6px; background: rgba(255,255,255,.8); white-space: nowrap; }
        .category-counter strong { display: grid; min-width: 18px; height: 18px; place-items: center; color: #fff; border-radius: 5px; background: var(--section-color); font-size: .58rem; }
        .assessment-node-body { padding: .85rem .9rem 1rem; border-top: 1px solid #edf0f4; }
        .assessment-node-body.is-collapsed { display: none; }

        .criteria-table { font-size: .7rem; }
        .criteria-table thead th { padding: .48rem .55rem; color: #667085; border-bottom: 1px solid #e4e8ee; background: #f8fafc; font-size: .6rem; font-weight: 750; letter-spacing: .035em; text-transform: uppercase; }
        .criteria-table td { padding: .62rem .55rem; border-color: #edf0f4; }
        .criteria-table td strong { color: #273246; font-size: .72rem; }
        .criteria-table td p { margin: .15rem 0 0; color: var(--workspace-muted); font-size: .64rem; line-height: 1.4; }
        .question-index-column { width: 38px; text-align: center; }
        .question-number { display: grid; width: 25px; height: 25px; margin: auto; place-items: center; color: var(--section-deep); border-radius: 7px; background: var(--section-soft); font-size: .6rem; font-weight: 750; }
        .score-column { width: 130px; }
        .decision-column { width: 190px; }
        .score-maximum { color: var(--section-deep); font-weight: 750; }
        .score-result { display: inline-flex; min-width: 55px; justify-content: center; padding: .28rem .45rem; color: var(--section-deep); border-radius: 7px; background: var(--section-soft); font-weight: 750; }
        .decision-badge { display: inline-flex; padding: .27rem .45rem; border-radius: 999px; font-size: .62rem; font-weight: 700; white-space: nowrap; }
        .decision-positive { color: #067647; background: #e8f8ef; }
        .decision-average { color: #9a5b05; background: #fff5d6; }
        .decision-negative { color: #b42318; background: #fff0ee; }
        .decision-empty { color: #667085; background: #f2f4f7; }
        .evidence-copy { color: #475467; font-size: .66rem; line-height: 1.45; }
        .section-notes { display: grid; grid-template-columns: repeat(2, 1fr); gap: .75rem; margin-top: .85rem; padding: .75rem; border-radius: 10px; background: #f8fafc; }
        .readonly-notes > div { padding: .65rem .7rem; border: 1px solid #e5e9ef; border-radius: 8px; background: #fff; }
        .readonly-notes span { display: block; color: #667085; font-size: .58rem; font-weight: 700; letter-spacing: .045em; text-transform: uppercase; }
        .readonly-notes p { margin: .25rem 0 0; color: #344054; font-size: .68rem; line-height: 1.45; white-space: pre-line; }
        .structural-node-note { display: flex; align-items: center; gap: .65rem; min-height: 46px; padding: .55rem .65rem; color: var(--section-deep); border: 1px dashed color-mix(in srgb, var(--section-color) 35%, white); border-radius: 9px; background: var(--section-soft); }
        .structural-node-note strong, .structural-node-note span { display: block; }
        .structural-node-note strong { font-size: .68rem; }
        .structural-node-note span { margin-top: .08rem; color: var(--workspace-muted); font-size: .62rem; }

        .overall-result { display: flex; align-items: center; flex-wrap: wrap; gap: .9rem 1.2rem; padding: 1rem 1.1rem; color: #fff; border-radius: 15px; background: linear-gradient(128deg, #172033, #263652); box-shadow: 0 12px 28px rgba(20,30,50,.13); }
        .overall-result-heading { display: flex; min-width: 175px; flex: 1; align-items: center; gap: .6rem; }
        .overall-icon { color: #fff; background: rgba(255,255,255,.12); }
        .overall-result-heading span, .overall-result-heading strong { display: block; }
        .overall-result-heading span { color: rgba(255,255,255,.62); font-size: .58rem; text-transform: uppercase; }
        .overall-result-heading strong { margin-top: .1rem; color: #fff; font-size: .78rem; }
        .overall-completion { display: flex; flex-direction: column; padding: .45rem .65rem; border-left: 1px solid rgba(255,255,255,.13); }
        .overall-completion span { color: rgba(255,255,255,.68); font-size: .62rem; }
        .overall-completion strong { margin-top: .08rem; color: #fff; font-size: .72rem; }
        .overall-numeric { min-width: 175px; text-align: right; }
        .overall-numeric > span, .overall-numeric strong, .overall-numeric small { display: block; }
        .overall-numeric > span { color: rgba(255,255,255,.62); font-size: .58rem; text-transform: uppercase; }
        .overall-numeric strong { color: #fff; font-size: 1rem; }
        .overall-numeric small { color: rgba(255,255,255,.66); font-size: .58rem; }
        .overall-categories { display: flex; max-width: 440px; flex-wrap: wrap; justify-content: flex-end; gap: .35rem; }
        .overall-category { display: inline-flex; align-items: center; gap: .35rem; padding: .35rem .48rem; border-radius: 8px; background: rgba(255,255,255,.1); font-size: .62rem; }
        .overall-category strong { display: grid; min-width: 20px; height: 20px; place-items: center; border-radius: 5px; background: rgba(255,255,255,.16); }
        .overall-categories small { width: 100%; color: rgba(255,255,255,.58); font-size: .57rem; text-align: right; }
        .empty-evaluation { display: grid; min-height: 150px; place-items: center; padding: 2rem; color: #98a2b3; border: 1px dashed #cdd5df; border-radius: 13px; background: #fff; }

        .evaluator-sidebar { position: sticky; top: 1rem; }
        .status-card, .identity-card, .structure-key { overflow: hidden; border: 1px solid var(--workspace-border); border-radius: 14px; background: #fff; box-shadow: 0 5px 16px rgba(20,34,66,.045); }
        .status-card { display: flex; align-items: center; gap: .65rem; padding: .8rem; }
        .status-card-icon { display: grid; flex: 0 0 36px; width: 36px; height: 36px; place-items: center; color: #087647; border-radius: 10px; background: #e8f8ef; }
        .status-card div > span, .status-card strong, .status-card small { display: block; }
        .status-card div > span { color: #667085; font-size: .57rem; text-transform: uppercase; }
        .status-card strong { color: #087647; font-size: .74rem; }
        .status-card small { margin-top: .08rem; color: #98a2b3; font-size: .58rem; }
        .identity-card > header { display: flex; align-items: center; gap: .45rem; padding: .7rem .8rem; color: #344054; border-bottom: 1px solid #edf0f4; background: #f8fafc; font-size: .7rem; font-weight: 720; }
        .identity-body { padding: .8rem; }
        .identity-body video { display: block; width: 100%; max-height: 230px; border-radius: 10px; background: #111827; }
        .identity-body p { margin: .5rem 0 0; color: #667085; font-size: .58rem; text-align: center; }
        .identity-empty { display: flex; align-items: center; justify-content: center; gap: .4rem; min-height: 100px; color: #8a5560; border-radius: 9px; background: #fff5f5; font-size: .65rem; }
        .structure-key { display: grid; gap: .35rem; padding: .75rem .8rem; }
        .structure-key > strong { margin-bottom: .1rem; color: #344054; font-size: .68rem; }
        .structure-key > span { display: flex; align-items: center; gap: .45rem; color: var(--workspace-muted); font-size: .62rem; }
        .structure-key b { display: grid; width: 20px; height: 20px; place-items: center; color: #3157d5; border-radius: 6px; background: #eef2ff; font-size: .58rem; }

        @supports not (background: color-mix(in srgb, red 10%, white)) {
            .root-jump-link, .subtotal-summary, .structural-node-note { border-color: #d8dee8; }
        }

        @media (max-width: 1199.98px) {
            .node-status { max-width: 58%; }
            .category-summary { width: 100%; }
        }
        @media (max-width: 991.98px) {
            .evaluation-hero { align-items: flex-start; flex-direction: column; }
            .evaluator-sidebar { position: static; }
        }
        @media (max-width: 767.98px) {
            .evaluation-hero { padding: 1.15rem; }
            .hero-actions { justify-content: flex-start; }
            .submitted-banner { align-items: flex-start; }
            .submitted-banner > b { margin-left: auto; }
            .applicant-facts, .section-notes { grid-template-columns: 1fr; }
            .assessment-node.depth-1, .assessment-node.depth-2, .assessment-node.depth-3 { margin-left: .65rem; }
            .assessment-node:not(.is-root)::before { display: none; }
            .assessment-node-header { flex-wrap: wrap; }
            .node-heading { min-width: calc(100% - 110px); }
            .node-status { width: 100%; max-width: none; justify-content: flex-start; padding-left: 2rem; }
            .category-summary { justify-content: flex-start; }
            .overall-result { align-items: flex-start; flex-direction: column; }
            .overall-completion { border-left: 0; padding-left: 0; }
            .overall-numeric, .overall-categories { max-width: none; text-align: left; }
            .overall-categories { justify-content: flex-start; }
            .overall-categories small { text-align: left; }
        }
    </style>

    <script>
        (() => {
            'use strict';
            const byId = id => document.getElementById(id);

            document.querySelectorAll('[data-node-toggle]').forEach(button => {
                button.addEventListener('click', () => {
                    const content = byId(button.getAttribute('aria-controls'));
                    const expanded = button.getAttribute('aria-expanded') === 'true';
                    button.setAttribute('aria-expanded', String(!expanded));
                    button.title = `${expanded ? 'Expand' : 'Collapse'} ${button.closest('.assessment-node')?.querySelector('h2')?.textContent?.trim() || 'section'}`;
                    content?.classList.toggle('is-collapsed', expanded);
                });
            });
        })();
    </script>
@endsection
