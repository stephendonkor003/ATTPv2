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
    @endphp

    <div class="nxl-container evaluator-workspace">
        <header class="evaluation-hero mb-4">
            <div class="hero-copy">
                <a href="{{ route('my.eval.index') }}" class="hero-back">
                    <i class="feather-arrow-left" aria-hidden="true"></i> My Evaluations
                </a>
                <span class="hero-eyebrow">Evaluator workspace</span>
                <h1>{{ $assignment->procurement->title }}</h1>
                <p>
                    {{ $evaluation->name }} · {{ $evaluation->typeLabel() }}
                    <span aria-hidden="true">•</span>
                    {{ $totalQuestions }} {{ \Illuminate\Support\Str::plural('question', $totalQuestions) }}
                </p>
            </div>
            <div class="hero-guidance">
                <i class="feather-shield" aria-hidden="true"></i>
                <div>
                    <strong>Evidence-led assessment</strong>
                    <span>
                        {{ $evaluation->isEoi()
                            ? 'Classify each question using the documented EOI evidence.'
                            : ($evaluation->isGoods()
                                ? 'Record a Yes or No decision and explain the evidence.'
                                : 'Enter an objective score within each configured maximum.') }}
                    </span>
                </div>
            </div>
        </header>

        <div class="row g-4 align-items-start">
            <main class="col-12">
                @if ($errors->any())
                    <div class="alert alert-danger mb-4" role="alert">
                        <strong>Please review the evaluation details below.</strong>
                        <ul class="mb-0 mt-2 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="access-gate mb-4" role="status">
                    <span class="gate-icon"><i class="feather-save" aria-hidden="true"></i></span>
                    <div>
                        <strong>Your evaluation is ready</strong>
                        <p>Enter scores now and save a draft at any time. Identity verification is only required for final submission.</p>
                    </div>
                </div>

                <details class="applicant-panel mb-4">
                    <summary>
                        <span class="summary-icon"><i class="feather-file-text" aria-hidden="true"></i></span>
                        <span>
                            <strong>Applicant submitted information</strong>
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

                <form method="POST" action="{{ route('my.eval.submit', [$assignment->id, $applicant->id]) }}"
                    enctype="multipart/form-data" id="finalForm">
                    @csrf
                    <input type="hidden" name="form_submission_id" value="{{ $applicant->id }}">

                    <div id="evaluationForm">
                        <section class="form-progress-card mb-4" aria-live="polite">
                            <div>
                                <span class="progress-kicker">Evaluation progress</span>
                                <strong><span data-overall-answered>0</span> of {{ $totalQuestions }} questions answered</strong>
                            </div>
                            <div class="progress-copy"><span data-overall-completion>0</span>% complete</div>
                            <div class="progress form-progress-track" role="progressbar" aria-label="Overall completion"
                                aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                                <div class="progress-bar" data-overall-progress style="width: 0%"></div>
                            </div>
                        </section>

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
                                        $branchQuestionCount = $section->subtreeCriteria()->count();
                                        $branchMaximum = $isNumeric ? $section->subtotalMaxScore() : 0;
                                        $subtreeSectionIds = $section->subtreeIds()->values();
                                        $contentId = 'evaluation-section-content-' . $section->id;
                                        $savedSectionScore = $submission?->sectionScores->firstWhere(
                                            'evaluation_section_id',
                                            $section->id,
                                        );
                                    @endphp

                                    <article id="evaluation-section-{{ $section->id }}"
                                        class="assessment-node hierarchy-tone-{{ $node['root_index'] % 8 }} depth-{{ $depth }} {{ $depth === 0 ? 'is-root' : '' }}"
                                        data-evaluation-section data-subtree-sections='@json($subtreeSectionIds)'
                                        data-question-total="{{ $branchQuestionCount }}">
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
                                                    <span><strong data-section-answered>0</strong>/{{ $branchQuestionCount }} answered</span>
                                                    <b><span data-section-completion>0</span>%</b>
                                                    <div class="mini-progress" aria-hidden="true">
                                                        <span data-section-progress style="width: 0%"></span>
                                                    </div>
                                                </div>

                                                @if ($section->show_subtotal && $isNumeric)
                                                    <div class="subtotal-summary numeric-summary" aria-label="Live branch subtotal">
                                                        <span>Score</span>
                                                        <strong><b data-section-score>0.00</b> / {{ number_format($branchMaximum, 2) }}</strong>
                                                        <small><span data-section-percent>0</span>% of available points</small>
                                                    </div>
                                                @elseif ($section->show_subtotal && $isCategorical)
                                                    <div class="subtotal-summary category-summary"
                                                        aria-label="Live branch category summary">
                                                        @foreach ($decisionOptions as $decisionValue => $decisionLabel)
                                                            <span class="category-counter">
                                                                {{ $decisionLabel }}
                                                                <strong data-decision-value="{{ $decisionValue }}">0</strong>
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </header>

                                        <div class="assessment-node-body" id="{{ $contentId }}">
                                            @if ($section->criteria->isNotEmpty())
                                                @if ($isNumeric)
                                                    <div class="table-responsive numeric-criteria-wrap">
                                                        <table class="table criteria-table align-middle mb-0">
                                                            <thead>
                                                                <tr>
                                                                    <th class="question-index-column">#</th>
                                                                    <th>Evaluation question</th>
                                                                    <th class="text-center score-column">Maximum</th>
                                                                    <th class="score-column">Score</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach ($section->criteria as $criterion)
                                                                    @php
                                                                        $saved = $submission?->criteriaScores->firstWhere(
                                                                            'evaluation_criteria_id',
                                                                            $criterion->id,
                                                                        );
                                                                    @endphp
                                                                    <tr class="criterion-row" data-criterion-row>
                                                                        <td class="question-index-column">
                                                                            <span class="question-number">{{ $loop->iteration }}</span>
                                                                        </td>
                                                                        <td>
                                                                            <strong>{{ $criterion->name }}</strong>
                                                                            @if (filled($criterion->description))
                                                                                <p>{{ $criterion->description }}</p>
                                                                            @endif
                                                                        </td>
                                                                        <td class="text-center score-maximum">{{ number_format($criterion->max_score, 2) }}</td>
                                                                        <td>
                                                                            <div class="score-entry">
                                                                                <input type="number" name="criteria[{{ $criterion->id }}]"
                                                                                    class="form-control score-input" min="0"
                                                                                    max="{{ $criterion->max_score }}" step="0.01"
                                                                                    data-max="{{ $criterion->max_score }}"
                                                                                    data-section-id="{{ $section->id }}"
                                                                                    value="{{ old("criteria.{$criterion->id}", $saved?->score) }}" required
                                                                                    aria-label="Score for {{ $criterion->name }}">
                                                                                <span>/ {{ number_format($criterion->max_score, 2) }}</span>
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                @else
                                                    <div class="categorical-question-list" role="list"
                                                        aria-label="{{ $evaluation->isEoi() ? 'Qualification questions' : 'Compliance questions' }}">
                                                        @foreach ($section->criteria as $criterion)
                                                            @php
                                                                $saved = $submission?->criteriaScores->firstWhere(
                                                                    'evaluation_criteria_id',
                                                                    $criterion->id,
                                                                );
                                                                $savedDecision = old(
                                                                    "criteria.{$criterion->id}.decision",
                                                                    $saved?->decision,
                                                                );
                                                            @endphp
                                                            <article class="categorical-question-card" data-criterion-row role="listitem">
                                                                <header class="categorical-question-header">
                                                                    <span class="question-number">{{ $loop->iteration }}</span>
                                                                    <div class="categorical-question-copy">
                                                                        <span class="question-eyebrow">Evaluation question {{ $loop->iteration }}</span>
                                                                        <h3>{{ $criterion->name }}</h3>
                                                                        @if (filled($criterion->description))
                                                                            <p>{{ $criterion->description }}</p>
                                                                        @endif
                                                                    </div>
                                                                </header>

                                                                <div class="categorical-answer-stack">
                                                                    <fieldset class="decision-fieldset categorical-response-panel" data-decision-group
                                                                        data-section-id="{{ $section->id }}">
                                                                        <legend class="visually-hidden">
                                                                            {{ $evaluation->isEoi() ? 'Qualification' : 'Compliance decision' }} for {{ $criterion->name }}
                                                                        </legend>
                                                                        <div class="response-field-heading">
                                                                            <strong>{{ $evaluation->isEoi() ? 'Qualification' : 'Compliance decision' }}</strong>
                                                                            <span>Select one option</span>
                                                                        </div>
                                                                        <input type="hidden" name="criteria[{{ $criterion->id }}][decision]" value="">
                                                                        <div class="decision-options {{ $evaluation->isEoi() ? 'is-eoi' : 'is-goods' }}">
                                                                            @foreach ($decisionOptions as $decisionValue => $decisionLabel)
                                                                                @php($decisionId = "decision-{$criterion->id}-{$decisionValue}")
                                                                                <label class="decision-option" for="{{ $decisionId }}">
                                                                                    <input id="{{ $decisionId }}" type="radio"
                                                                                        name="criteria[{{ $criterion->id }}][decision]"
                                                                                        value="{{ $decisionValue }}"
                                                                                        class="decision-input"
                                                                                        data-section-id="{{ $section->id }}"
                                                                                        @checked((string) $savedDecision === (string) $decisionValue)
                                                                                        required>
                                                                                    @if ($evaluation->isEoi())
                                                                                        <span class="decision-number">{{ $loop->iteration }}</span>
                                                                                    @else
                                                                                        <i class="{{ (int) $decisionValue === 1 ? 'feather-check' : 'feather-x' }}" aria-hidden="true"></i>
                                                                                    @endif
                                                                                    <span>{{ $decisionLabel }}</span>
                                                                                </label>
                                                                            @endforeach
                                                                        </div>
                                                                    </fieldset>

                                                                    <div class="evidence-response categorical-response-panel">
                                                                        <label for="comment-{{ $criterion->id }}" class="evidence-label">
                                                                            <strong>Evidence comment</strong>
                                                                            <span>Required for final submission</span>
                                                                        </label>
                                                                        <textarea id="comment-{{ $criterion->id }}" name="criteria[{{ $criterion->id }}][comment]"
                                                                            class="form-control evidence-comment" rows="4" maxlength="5000"
                                                                            placeholder="Briefly cite the evidence for this decision" required>{{ old("criteria.{$criterion->id}.comment", $saved?->comment) }}</textarea>
                                                                    </div>
                                                                </div>
                                                            </article>
                                                        @endforeach
                                                    </div>
                                                @endif

                                                <div class="section-notes">
                                                    <div>
                                                        <label for="strengths-{{ $section->id }}">Section strengths</label>
                                                        <textarea id="strengths-{{ $section->id }}" name="sections[{{ $section->id }}][strengths]"
                                                            class="form-control" rows="3" maxlength="5000" placeholder="Summarise the strongest evidence" required>{{ old("sections.{$section->id}.strengths", $savedSectionScore?->strengths) }}</textarea>
                                                    </div>
                                                    <div>
                                                        <label for="weaknesses-{{ $section->id }}">Section weaknesses</label>
                                                        <textarea id="weaknesses-{{ $section->id }}" name="sections[{{ $section->id }}][weaknesses]"
                                                            class="form-control" rows="3" maxlength="5000" placeholder="Summarise gaps or concerns" required>{{ old("sections.{$section->id}.weaknesses", $savedSectionScore?->weaknesses) }}</textarea>
                                                    </div>
                                                </div>

                                                @if ($isNumeric)
                                                    <input type="hidden" name="sections[{{ $section->id }}][score]"
                                                        class="section-score-input" value="0">
                                                @endif
                                            @else
                                                <div class="structural-node-note">
                                                    <i class="feather-corner-down-right" aria-hidden="true"></i>
                                                    <div>
                                                        <strong>Grouping section</strong>
                                                        <span>This heading organises {{ $branchQuestionCount }} {{ \Illuminate\Support\Str::plural('question', $branchQuestionCount) }} in its child sections. No separate response is required here.</span>
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
                                <strong>No evaluation questions are configured.</strong>
                            </div>
                        @endforelse

                        <section class="overall-result mb-4" aria-live="polite">
                            <div class="overall-result-heading">
                                <span class="overall-icon"><i class="feather-activity" aria-hidden="true"></i></span>
                                <div>
                                    <span>Whole evaluation</span>
                                    <strong>Live overall summary</strong>
                                </div>
                            </div>

                            <div class="overall-completion">
                                <span><b data-overall-answered>0</b> / {{ $totalQuestions }} answered</span>
                                <strong><span data-overall-completion>0</span>% complete</strong>
                            </div>

                            @if ($isNumeric)
                                <div class="overall-numeric">
                                    <span>Overall score</span>
                                    <strong><b id="overallScore">0.00</b> / {{ number_format($overallMaximum, 2) }}</strong>
                                    <small><span data-overall-score-percent>0</span>% of available points</small>
                                </div>
                            @else
                                <div class="overall-categories">
                                    @foreach ($decisionOptions as $decisionValue => $decisionLabel)
                                        <span class="overall-category">
                                            {{ $decisionLabel }}
                                            <strong data-overall-decision-value="{{ $decisionValue }}">0</strong>
                                        </span>
                                    @endforeach
                                    @if ($evaluation->isEoi())
                                        <small>EOI categories are summarised as counts only; no numeric rank is calculated.</small>
                                    @endif
                                </div>
                            @endif
                        </section>

                        <div class="submission-actions">
                            <div>
                                <strong>Ready to finalise?</strong>
                                <span id="draftStatus" role="status" aria-live="polite">Draft changes are saved as you work.</span>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <button class="btn btn-outline-primary" type="button" id="saveDraft">
                                    <i class="feather-save me-1" aria-hidden="true"></i> Save draft
                                </button>
                                <button class="btn btn-success" type="submit" @disabled($totalQuestions === 0)>
                                    <i class="feather-check-circle me-1" aria-hidden="true"></i>
                                    Submit final evaluation
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </main>

        </div>
    </div>

    <button type="button" id="evaluatorToolsToggle" class="evaluator-tools-toggle"
        aria-controls="evaluatorToolsDrawer" aria-expanded="false"
        aria-haspopup="dialog" aria-label="Open evaluator tools and identity verification">
        <i class="feather-sliders" aria-hidden="true"></i>
        <span>Tools &amp; verification</span>
        <span class="tools-toggle-status" aria-hidden="true"></span>
    </button>

    <div id="evaluatorToolsBackdrop" class="evaluator-tools-backdrop" aria-hidden="true"></div>

    <aside id="evaluatorToolsDrawer" class="evaluator-tools-drawer" role="dialog" aria-modal="true"
        aria-hidden="true" aria-labelledby="evaluatorToolsTitle" tabindex="-1" inert>
        <header class="evaluator-tools-header">
            <div>
                <span>Evaluator tools</span>
                <h2 id="evaluatorToolsTitle">Monitor &amp; verification</h2>
            </div>
            <button type="button" id="evaluatorToolsClose" class="evaluator-tools-close"
                aria-label="Close evaluator tools">
                <i class="feather-x" aria-hidden="true"></i>
            </button>
        </header>

        <div class="evaluator-tools-body">
            <section class="monitor-card mb-3">
                <header>
                    <i class="feather-activity" aria-hidden="true"></i>
                    <h3>Evaluation monitor</h3>
                </header>
                <div class="monitor-body">
                    <div><span>Date</span><strong id="currentDate">—</strong></div>
                    <div><span>Time</span><strong id="currentTime">—</strong></div>
                    <div><span>Status</span><strong id="evalStatus">Draft</strong></div>
                </div>
            </section>

            <section class="camera-card mb-3">
                <header>
                    <span id="cameraStatus" class="camera-status idle" aria-hidden="true"></span>
                    <h3>Identity verification</h3>
                </header>
                <div class="camera-body">
                    <div class="camera-preview">
                        <video id="preview" autoplay muted playsinline></video>
                        <span>Camera preview</span>
                    </div>
                    <button id="startCamera" type="button" class="btn btn-primary w-100 mb-2">
                        <i class="feather-video me-1" aria-hidden="true"></i>Start camera
                    </button>
                    <button id="stopCamera" type="button" class="btn btn-outline-danger w-100 d-none">
                        Stop and use recording
                    </button>
                    <div class="verification-divider"><span>or</span></div>
                    <label for="finalVideo" class="verification-upload-label">Upload a verification video</label>
                    <input type="file" name="video" id="finalVideo" form="finalForm"
                        class="form-control form-control-sm" accept="video/webm,video/mp4,video/*"
                        aria-describedby="verificationHelp verificationError">
                    <p id="verificationHelp">Record up to 15 seconds or upload WebM/MP4. This is required only for final submission.</p>
                    <p id="verificationError" class="verification-error" role="alert" hidden></p>
                </div>
            </section>

            <section class="structure-key">
                <h3>Form structure</h3>
                <ol>
                    @foreach (\App\Models\EvaluationSection::LEVEL_LABELS as $level => $label)
                        <li><b>{{ $level }}</b><span>{{ $label }}</span></li>
                    @endforeach
                </ol>
            </section>
        </div>
    </aside>

    @include('evaluations.partials.hierarchy-theme')

    <style>
        .evaluator-workspace {
            --workspace-ink: #172033;
            --workspace-muted: #667085;
            --workspace-border: #e2e7ef;
            color: var(--workspace-ink);
        }
        .evaluator-workspace main { min-width: 0; }

        .evaluation-hero { display: flex; align-items: center; justify-content: space-between; gap: 2rem; padding: 1.5rem 1.65rem; color: #fff; border-radius: 18px; background: radial-gradient(circle at 88% 12%, rgba(255,255,255,.16), transparent 28%), linear-gradient(128deg, #17296b 0%, #3157d5 62%, #4d74e8 100%); box-shadow: 0 18px 42px rgba(35,68,178,.16); }
        .hero-back { display: inline-flex; align-items: center; gap: .35rem; margin-bottom: .65rem; color: rgba(255,255,255,.84); font-size: .78rem; font-weight: 700; text-decoration: none; }
        .hero-back:hover { color: #fff; }
        .hero-eyebrow { display: block; margin-bottom: .35rem; color: rgba(255,255,255,.72); font-size: .68rem; font-weight: 750; letter-spacing: .1em; text-transform: uppercase; }
        .evaluation-hero h1 { margin: 0; color: #fff; font-size: clamp(1.35rem, 2vw, 2rem); font-weight: 750; letter-spacing: -.025em; }
        .evaluation-hero p { display: flex; flex-wrap: wrap; align-items: center; gap: .45rem; margin: .45rem 0 0; color: rgba(255,255,255,.78); font-size: .86rem; }
        .hero-guidance { display: flex; max-width: 390px; align-items: flex-start; gap: .7rem; padding: .8rem .9rem; border: 1px solid rgba(255,255,255,.18); border-radius: 12px; background: rgba(255,255,255,.1); backdrop-filter: blur(7px); }
        .hero-guidance > i { margin-top: .12rem; font-size: 1.05rem; }
        .hero-guidance strong, .hero-guidance span { display: block; }
        .hero-guidance strong { color: #fff; font-size: .76rem; }
        .hero-guidance span { margin-top: .15rem; color: rgba(255,255,255,.74); font-size: .7rem; line-height: 1.45; }

        .access-gate { display: flex; align-items: center; gap: .9rem; padding: 1rem 1.1rem; border: 1px solid #f1d698; border-radius: 14px; background: #fffbeb; }
        .gate-icon, .summary-icon, .overall-icon { display: grid; flex: 0 0 38px; width: 38px; height: 38px; place-items: center; border-radius: 10px; }
        .gate-icon { color: #9a5b05; background: #fef0c7; }
        .access-gate strong { color: #754408; font-size: .82rem; }
        .access-gate p { margin: .2rem 0 0; color: #8b6a2e; font-size: .72rem; }

        .applicant-panel { overflow: hidden; border: 1px solid var(--workspace-border); border-radius: 14px; background: #fff; box-shadow: 0 5px 16px rgba(20,34,66,.04); }
        .applicant-panel summary { display: flex; align-items: center; gap: .7rem; padding: .85rem 1rem; cursor: pointer; list-style: none; }
        .applicant-panel summary::-webkit-details-marker { display: none; }
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

        .form-progress-card { display: grid; grid-template-columns: 1fr auto; gap: .65rem 1rem; padding: .85rem 1rem; border: 1px solid #dce4ff; border-radius: 13px; background: #f8faff; }
        .progress-kicker { display: block; color: #3157d5; font-size: .6rem; font-weight: 750; letter-spacing: .08em; text-transform: uppercase; }
        .form-progress-card strong { display: block; margin-top: .12rem; font-size: .78rem; }
        .progress-copy { align-self: center; color: #3157d5; font-size: .76rem; font-weight: 700; }
        .form-progress-track { grid-column: 1 / -1; height: 6px; background: #e6ebf8; }
        .form-progress-track .progress-bar { background: linear-gradient(90deg, #3157d5, #0f8a72); transition: width .25s ease; }

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
        .node-toggle { display: grid; flex: 0 0 27px; width: 27px; height: 27px; margin-top: .18rem; padding: 0; place-items: center; color: var(--section-deep); border: 0; border-radius: 7px; background: rgba(255,255,255,.75); transition: transform .18s ease; }
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
        .mini-progress span { display: block; height: 100%; border-radius: inherit; background: var(--section-color); transition: width .2s ease; }
        .subtotal-summary { padding: .38rem .52rem; color: var(--section-deep); border: 1px solid color-mix(in srgb, var(--section-color) 25%, white); background: var(--section-soft); }
        .numeric-summary span, .numeric-summary strong, .numeric-summary small { display: block; }
        .numeric-summary > span { font-size: .55rem; font-weight: 700; text-transform: uppercase; }
        .numeric-summary strong { margin: .05rem 0; font-size: .72rem; }
        .numeric-summary small { opacity: .78; font-size: .56rem; }
        .category-summary { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .28rem; }
        .category-counter { display: inline-flex; align-items: center; gap: .25rem; padding: .22rem .35rem; border-radius: 6px; background: rgba(255,255,255,.8); white-space: nowrap; }
        .category-counter strong { display: grid; min-width: 18px; height: 18px; place-items: center; color: #fff; border-radius: 5px; background: var(--section-color); font-size: .58rem; }
        .assessment-node-body { min-width: 0; padding: .85rem .9rem 1rem; border-top: 1px solid #edf0f4; }
        .assessment-node-body.is-collapsed { display: none; }

        .criteria-table { font-size: .82rem; }
        .criteria-table thead th { padding: .55rem .6rem; color: #667085; border-bottom: 1px solid #e4e8ee; background: #f8fafc; font-size: .7rem; font-weight: 750; letter-spacing: .035em; text-transform: uppercase; }
        .criteria-table td { padding: .62rem .55rem; border-color: #edf0f4; }
        .criteria-table td strong { color: #273246; font-size: .84rem; }
        .criteria-table td p { margin: .18rem 0 0; color: var(--workspace-muted); font-size: .76rem; line-height: 1.45; }
        .criteria-table .form-control, .criteria-table .form-select { min-height: 42px; border-color: #d8dee8; border-radius: 8px; font-size: .84rem; }
        .criteria-table .form-control:focus, .criteria-table .form-select:focus, .section-notes .form-control:focus { border-color: var(--section-color); box-shadow: 0 0 0 3px color-mix(in srgb, var(--section-color) 10%, transparent); }
        .question-index-column { width: 38px; text-align: center; }
        .question-number { display: grid; width: 25px; height: 25px; margin: auto; place-items: center; color: var(--section-deep); border-radius: 7px; background: var(--section-soft); font-size: .6rem; font-weight: 750; }
        .score-column { width: 130px; }
        .score-maximum { color: var(--section-deep); font-weight: 750; }
        .score-entry { display: flex; align-items: center; gap: .35rem; }
        .score-entry span { color: #98a2b3; white-space: nowrap; }
        .criterion-row.is-answered { background: color-mix(in srgb, var(--section-soft) 42%, white); }
        .categorical-question-list { display: grid; width: 100%; max-width: 100%; min-width: 0; gap: .9rem; }
        .categorical-question-card { width: 100%; max-width: 100%; min-width: 0; overflow: hidden; border: 1px solid #e1e6ee; border-radius: 13px; background: #fff; box-shadow: 0 4px 12px rgba(20,34,66,.035); }
        .categorical-question-card.is-answered { border-color: color-mix(in srgb, var(--section-color) 38%, white); box-shadow: 0 0 0 3px color-mix(in srgb, var(--section-color) 7%, transparent); }
        .categorical-question-header { display: flex; min-width: 0; align-items: flex-start; gap: .75rem; padding: .85rem .9rem; border-bottom: 1px solid #edf0f4; background: linear-gradient(100deg, var(--section-soft), #fff 62%); }
        .categorical-question-header .question-number { flex: 0 0 30px; width: 30px; height: 30px; margin: 0; }
        .categorical-question-copy { flex: 1; min-width: 0; }
        .question-eyebrow { display: block; margin-bottom: .15rem; color: var(--section-deep); font-size: .57rem; font-weight: 750; letter-spacing: .07em; text-transform: uppercase; }
        .categorical-question-copy h3 { margin: 0; color: #273246; font-size: .86rem; font-weight: 730; line-height: 1.4; overflow-wrap: anywhere; }
        .categorical-question-copy p { max-width: 100%; margin: .28rem 0 0; color: var(--workspace-muted); font-size: .74rem; line-height: 1.55; white-space: normal; overflow-wrap: anywhere; }
        .categorical-answer-stack { display: grid; width: 100%; min-width: 0; grid-template-columns: minmax(0, 1fr); gap: .75rem; padding: .85rem .9rem .95rem; }
        .categorical-response-panel { min-width: 0; margin: 0; padding: .75rem; border: 1px solid #e4e8ee; border-radius: 10px; background: #f8fafc; }
        .response-field-heading, .evidence-label { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .25rem .75rem; margin-bottom: .45rem; }
        .response-field-heading strong, .evidence-label strong { color: #344054; font-size: .76rem; font-weight: 720; }
        .response-field-heading span, .evidence-label span { color: #98a2b3; font-size: .67rem; font-weight: 500; }
        .decision-options { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: .55rem; margin: 0; }
        .decision-options.is-eoi { grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); }
        .decision-option { position: relative; display: flex; min-width: 0; min-height: 50px; align-items: center; gap: .55rem; margin: 0; padding: .65rem .7rem; color: #344054; border: 1px solid #d8dee8; border-radius: 10px; background: #fff; cursor: pointer; font-size: .78rem; font-weight: 700; transition: border-color .16s ease, background .16s ease, box-shadow .16s ease; }
        .decision-option:hover { border-color: var(--section-color); background: var(--section-soft); }
        .decision-option:focus-within { border-color: var(--section-color); box-shadow: 0 0 0 3px color-mix(in srgb, var(--section-color) 12%, transparent); }
        .decision-option:has(input:checked) { color: var(--section-deep); border-color: var(--section-color); background: var(--section-soft); box-shadow: 0 0 0 3px color-mix(in srgb, var(--section-color) 10%, transparent); }
        .decision-option input { flex: 0 0 17px; width: 17px; height: 17px; margin: 0; accent-color: var(--section-color); }
        .decision-option i { font-size: 1rem; }
        .decision-option > span:last-child { min-width: 0; overflow-wrap: anywhere; }
        .decision-number { display: grid; flex: 0 0 25px; width: 25px; height: 25px; place-items: center; color: #fff; border-radius: 7px; background: var(--section-color); font-size: .7rem; }
        .categorical-response-panel .form-control { border-color: #d8dee8; border-radius: 8px; }
        .categorical-response-panel .form-control:focus { border-color: var(--section-color); box-shadow: 0 0 0 3px color-mix(in srgb, var(--section-color) 10%, transparent); }
        .evidence-comment { display: block; width: 100%; max-width: 100%; min-height: 104px; resize: vertical; font-size: .8rem !important; }
        .section-notes { display: grid; grid-template-columns: repeat(2, 1fr); gap: .75rem; margin-top: .85rem; padding: .75rem; border-radius: 10px; background: #f8fafc; }
        .section-notes label { display: block; margin-bottom: .3rem; color: #475467; font-size: .78rem; font-weight: 700; }
        .section-notes .form-control { border-color: #d8dee8; border-radius: 8px; font-size: .82rem; }
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
        .submission-actions { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .9rem 1rem; border: 1px solid #cfe8da; border-radius: 13px; background: #f1fbf5; }
        .submission-actions strong, .submission-actions span { display: block; }
        .submission-actions strong { color: #176b43; font-size: .76rem; }
        .submission-actions span { margin-top: .1rem; color: #638070; font-size: .76rem; }
        #draftStatus[data-state="saving"] { color: #3157d5; }
        #draftStatus[data-state="saved"] { color: #067647; }
        #draftStatus[data-state="error"] { color: #b42318; }
        .empty-evaluation { display: grid; min-height: 150px; place-items: center; padding: 2rem; color: #98a2b3; border: 1px dashed #cdd5df; border-radius: 13px; background: #fff; }

        .evaluator-tools-toggle { position: fixed; right: 1.25rem; bottom: 1.25rem; z-index: 1075; display: inline-flex; align-items: center; gap: .55rem; min-height: 46px; padding: .7rem .9rem; color: #fff; border: 0; border-radius: 999px; background: #203fae; box-shadow: 0 12px 30px rgba(28,53,146,.28); font-size: .75rem; font-weight: 720; transition: transform .18s ease, background .18s ease, box-shadow .18s ease; }
        .evaluator-tools-toggle:hover { background: #172f8d; box-shadow: 0 14px 34px rgba(28,53,146,.34); transform: translateY(-2px); }
        .evaluator-tools-toggle:focus-visible, .evaluator-tools-close:focus-visible { outline: 3px solid rgba(49,87,213,.28); outline-offset: 3px; }
        .tools-toggle-status { width: 8px; height: 8px; border: 2px solid rgba(255,255,255,.62); border-radius: 50%; background: #fdb022; }
        .evaluator-tools-toggle.is-verified .tools-toggle-status { border-color: rgba(255,255,255,.78); background: #32d583; }
        .evaluator-tools-backdrop { position: fixed; inset: 0; z-index: 1080; visibility: hidden; background: rgba(16,24,40,.42); opacity: 0; pointer-events: none; transition: opacity .36s ease, visibility 0s linear .36s; }
        .evaluator-tools-backdrop.is-open { visibility: visible; opacity: 1; pointer-events: auto; transition-delay: 0s; }
        .evaluator-tools-drawer { --workspace-muted: #667085; --workspace-border: #e2e7ef; position: fixed; top: 0; right: 0; bottom: 0; z-index: 1085; display: flex; width: min(410px, calc(100vw - 1rem)); height: 100vh; height: 100dvh; flex-direction: column; overflow: hidden; visibility: hidden; border-left: 1px solid #e1e6ee; background: #f7f9fc; box-shadow: -20px 0 54px rgba(16,24,40,.2); pointer-events: none; transform: translate3d(100%, 0, 0); transition: transform .38s cubic-bezier(.22,.8,.2,1), visibility 0s linear .38s; }
        .evaluator-tools-drawer.is-open { visibility: visible; pointer-events: auto; transform: translate3d(0, 0, 0); transition-delay: 0s; }
        .evaluator-tools-header { display: flex; flex: 0 0 auto; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 1.1rem; color: #fff; background: linear-gradient(128deg, #17296b, #3157d5); }
        .evaluator-tools-header span { display: block; margin-bottom: .12rem; color: rgba(255,255,255,.68); font-size: .6rem; font-weight: 750; letter-spacing: .09em; text-transform: uppercase; }
        .evaluator-tools-header h2 { margin: 0; color: #fff; font-size: 1rem; font-weight: 740; }
        .evaluator-tools-close { display: grid; flex: 0 0 44px; width: 44px; height: 44px; padding: 0; place-items: center; color: #fff; border: 1px solid rgba(255,255,255,.25); border-radius: 10px; background: rgba(255,255,255,.12); font-size: 1.1rem; }
        .evaluator-tools-close:hover { background: rgba(255,255,255,.22); }
        .evaluator-tools-body { flex: 1 1 auto; min-height: 0; padding: 1rem 1rem max(1rem, env(safe-area-inset-bottom)); overflow-x: hidden; overflow-y: auto; overscroll-behavior: contain; }
        .evaluator-tools-body .btn, .evaluator-tools-body .form-control { min-height: 44px; }
        body.evaluator-drawer-open { overflow: hidden; }
        .monitor-card, .camera-card, .structure-key { overflow: hidden; border: 1px solid var(--workspace-border); border-radius: 14px; background: #fff; box-shadow: 0 5px 16px rgba(20,34,66,.045); }
        .monitor-card > header, .camera-card > header { display: flex; align-items: center; gap: .45rem; padding: .7rem .8rem; color: #344054; border-bottom: 1px solid #edf0f4; background: #f8fafc; font-size: .8rem; font-weight: 720; }
        .monitor-card h3, .camera-card h3, .structure-key h3 { margin: 0; color: inherit; font: inherit; }
        .monitor-body { padding: .7rem .8rem; }
        .monitor-body > div { display: flex; align-items: center; justify-content: space-between; padding: .35rem 0; color: var(--workspace-muted); font-size: .76rem; }
        .monitor-body strong { color: #344054; }
        .monitor-body .status-locked { color: #b54708; }
        .monitor-body .status-recording { color: #b42318; }
        .monitor-body .status-ready { color: #067647; }
        .camera-status { width: 9px; height: 9px; border-radius: 50%; background: #98a2b3; }
        .camera-status.recording { background: #d92d20; box-shadow: 0 0 0 5px rgba(217,45,32,.12); }
        .camera-status.ready { background: #12b76a; box-shadow: 0 0 0 5px rgba(18,183,106,.12); }
        .camera-body { padding: .8rem; }
        .camera-preview { position: relative; min-height: 150px; margin-bottom: .7rem; overflow: hidden; border-radius: 10px; aspect-ratio: 16 / 9; background: #111827; }
        .camera-preview video { display: block; width: 100%; height: 100%; min-height: 150px; object-fit: cover; }
        .camera-preview > span { position: absolute; right: .5rem; bottom: .45rem; padding: .2rem .38rem; color: rgba(255,255,255,.8); border-radius: 5px; background: rgba(0,0,0,.38); font-size: .55rem; }
        .camera-body p { margin: .55rem 0 0; color: var(--workspace-muted); font-size: .72rem; line-height: 1.45; text-align: center; }
        .verification-divider { display: flex; align-items: center; gap: .55rem; margin: .65rem 0; color: #98a2b3; font-size: .65rem; text-transform: uppercase; }
        .verification-divider::before, .verification-divider::after { height: 1px; flex: 1; background: #e4e7ec; content: ''; }
        .verification-upload-label { display: block; margin-bottom: .35rem; color: #475467; font-size: .72rem; font-weight: 700; }
        .camera-body .verification-error { margin-top: .65rem; padding: .55rem .65rem; color: #b42318; border: 1px solid #fecdca; border-radius: 8px; background: #fff1f0; text-align: left; }
        .structure-key { display: grid; gap: .35rem; padding: .75rem .8rem; }
        .structure-key > h3 { margin-bottom: .1rem; color: #344054; font-size: .68rem; font-weight: 720; }
        .structure-key ol { display: grid; gap: .35rem; margin: 0; padding: 0; list-style: none; }
        .structure-key li { display: flex; align-items: center; gap: .45rem; color: var(--workspace-muted); font-size: .62rem; }
        .structure-key b { display: grid; width: 20px; height: 20px; place-items: center; color: #3157d5; border-radius: 6px; background: #eef2ff; font-size: .58rem; }

        @supports not (background: color-mix(in srgb, red 10%, white)) {
            .root-jump-link, .subtotal-summary, .structural-node-note { border-color: #d8dee8; }
            .criterion-row.is-answered, .categorical-question-card.is-answered { background: #fafbfc; }
        }

        @media (max-width: 1199.98px) {
            .node-status { max-width: 58%; }
            .category-summary { width: 100%; }
        }

        @media (max-width: 991.98px) {
            .evaluation-hero { align-items: flex-start; flex-direction: column; }
            .hero-guidance { max-width: none; }
        }

        @media (max-width: 767.98px) {
            .evaluation-hero { padding: 1.15rem; }
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
            .submission-actions { align-items: stretch; flex-direction: column; }
            .decision-options, .decision-options.is-eoi { grid-template-columns: 1fr; }
            .evaluator-tools-toggle { right: .75rem; bottom: .75rem; }
            .evaluator-tools-toggle span:not(.tools-toggle-status) { display: none; }
            .evaluator-tools-drawer { width: 100vw; max-width: 100vw; border-left: 0; }
            .evaluator-tools-body { padding: .8rem; }
        }

        @media (prefers-reduced-motion: reduce) {
            .evaluator-tools-toggle, .evaluator-tools-backdrop, .evaluator-tools-drawer { transition: none; }
        }
    </style>

    <script>
        (() => {
            'use strict';

            const IS_NUMERIC = @json($isNumeric);
            const TOTAL_QUESTIONS = @json($totalQuestions);
            const OVERALL_MAXIMUM = @json($overallMaximum);
            const SAVE_URL = @json(route('my.eval.save', [$assignment->id, $applicant->id]));
            const byId = id => document.getElementById(id);
            const clampPercent = value => Math.max(0, Math.min(100, Number.isFinite(value) ? value : 0));
            const currentDate = byId('currentDate');
            const currentTime = byId('currentTime');
            const startCamera = byId('startCamera');
            const stopCamera = byId('stopCamera');
            const preview = byId('preview');
            const finalVideo = byId('finalVideo');
            const evalStatus = byId('evalStatus');
            const cameraStatus = byId('cameraStatus');
            const finalForm = byId('finalForm');
            const saveDraftButton = byId('saveDraft');
            const draftStatus = byId('draftStatus');
            const toolsToggle = byId('evaluatorToolsToggle');
            const toolsBackdrop = byId('evaluatorToolsBackdrop');
            const toolsDrawer = byId('evaluatorToolsDrawer');
            const toolsClose = byId('evaluatorToolsClose');
            const verificationError = byId('verificationError');
            const evaluatorWorkspace = document.querySelector('.evaluator-workspace');

            let drawerReturnFocus = null;

            function drawerFocusables() {
                if (!toolsDrawer) return [];

                return [...toolsDrawer.querySelectorAll(
                    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
                )].filter(element => element.getClientRects().length > 0);
            }

            function openEvaluatorTools(focusTarget = null) {
                if (!toolsDrawer || !toolsToggle || !toolsBackdrop) return;

                if (toolsDrawer.getAttribute('aria-hidden') !== 'false') {
                    drawerReturnFocus = document.activeElement instanceof HTMLElement
                        ? document.activeElement
                        : toolsToggle;
                }
                toolsDrawer.removeAttribute('inert');
                toolsDrawer.classList.add('is-open');
                toolsBackdrop.classList.add('is-open');
                toolsDrawer.setAttribute('aria-hidden', 'false');
                toolsToggle.setAttribute('aria-expanded', 'true');
                evaluatorWorkspace?.setAttribute('inert', '');
                document.body.classList.add('evaluator-drawer-open');

                window.requestAnimationFrame(() => (focusTarget || toolsClose || toolsDrawer).focus());
            }

            function closeEvaluatorTools(restoreFocus = true) {
                if (!toolsDrawer || !toolsToggle || !toolsBackdrop) return;

                const focusToRestore = drawerReturnFocus instanceof HTMLElement
                    && !toolsDrawer.contains(drawerReturnFocus)
                    ? drawerReturnFocus
                    : toolsToggle;
                evaluatorWorkspace?.removeAttribute('inert');
                if (restoreFocus) focusToRestore.focus();
                toolsDrawer.classList.remove('is-open');
                toolsBackdrop.classList.remove('is-open');
                toolsDrawer.setAttribute('inert', '');
                toolsDrawer.setAttribute('aria-hidden', 'true');
                toolsToggle.setAttribute('aria-expanded', 'false');
                document.body.classList.remove('evaluator-drawer-open');

                drawerReturnFocus = null;
            }

            toolsToggle?.addEventListener('click', () => openEvaluatorTools());
            toolsClose?.addEventListener('click', () => closeEvaluatorTools());
            toolsBackdrop?.addEventListener('click', () => closeEvaluatorTools());

            document.addEventListener('keydown', event => {
                if (toolsDrawer?.getAttribute('aria-hidden') !== 'false') return;

                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeEvaluatorTools();
                    return;
                }

                if (event.key !== 'Tab') return;
                const focusable = drawerFocusables();
                if (!focusable.length) {
                    event.preventDefault();
                    toolsDrawer.focus();
                    return;
                }

                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            });

            function updateClock() {
                const now = new Date();
                if (currentDate) currentDate.textContent = now.toLocaleDateString(undefined, {
                    day: '2-digit', month: 'short', year: 'numeric'
                });
                if (currentTime) currentTime.textContent = now.toLocaleTimeString();
            }

            updateClock();
            window.setInterval(updateClock, 1000);

            let recorder = null;
            let chunks = [];
            let stream = null;

            startCamera?.addEventListener('click', async () => {
                try {
                    stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
                    preview.srcObject = stream;
                    recorder = new MediaRecorder(stream);
                    chunks = [];
                    recorder.ondataavailable = event => {
                        if (event.data?.size) chunks.push(event.data);
                    };
                    recorder.start();

                    startCamera.classList.add('d-none');
                    stopCamera.classList.remove('d-none');
                    cameraStatus.className = 'camera-status recording';
                    evalStatus.textContent = 'Recording';
                    evalStatus.className = 'status-recording';

                    window.setTimeout(() => {
                        if (recorder?.state === 'recording') stopCamera.click();
                    }, 15000);
                } catch (error) {
                    window.alert('Camera recording is unavailable. You can continue scoring and upload a verification video before final submission.');
                    console.error(error);
                }
            });

            stopCamera?.addEventListener('click', () => {
                if (!recorder || recorder.state !== 'recording') return;

                recorder.onstop = () => {
                    const blobType = recorder.mimeType || 'video/webm';
                    const blob = new Blob(chunks, { type: blobType });
                    const extension = blobType.includes('mp4') ? 'mp4' : 'webm';
                    const file = new File([blob], `identity.${extension}`, { type: blobType });
                    const transfer = new DataTransfer();
                    transfer.items.add(file);
                    finalVideo.files = transfer.files;

                    stopCamera.classList.add('d-none');
                    startCamera.classList.remove('d-none');
                    startCamera.disabled = true;
                    startCamera.innerHTML = '<i class="feather-check me-1" aria-hidden="true"></i>Identity captured';
                    cameraStatus.className = 'camera-status ready';
                    evalStatus.textContent = 'Verified';
                    evalStatus.className = 'status-ready';
                    toolsToggle?.classList.add('is-verified');
                    recalculate();
                };

                recorder.stop();
                stream?.getTracks().forEach(track => track.stop());
                preview.srcObject = null;
            });

            function subtreeIds(section) {
                try {
                    return JSON.parse(section.dataset.subtreeSections || '[]').map(String);
                } catch (error) {
                    console.error('Invalid evaluation section hierarchy.', error);
                    return [];
                }
            }

            function setCompletion(scope, answered, total) {
                const completion = total > 0 ? clampPercent((answered / total) * 100) : 0;
                scope.querySelectorAll('[data-section-answered]').forEach(node => node.textContent = answered);
                scope.querySelectorAll('[data-section-completion]').forEach(node => node.textContent = completion.toFixed(0));
                scope.querySelectorAll('[data-section-progress]').forEach(node => node.style.width = `${completion}%`);
                return completion;
            }

            function setOverallCompletion(answered) {
                const completion = TOTAL_QUESTIONS > 0 ? clampPercent((answered / TOTAL_QUESTIONS) * 100) : 0;
                document.querySelectorAll('[data-overall-answered]').forEach(node => node.textContent = answered);
                document.querySelectorAll('[data-overall-completion]').forEach(node => node.textContent = completion.toFixed(0));
                document.querySelectorAll('[data-overall-progress]').forEach(node => node.style.width = `${completion}%`);
                document.querySelectorAll('.form-progress-track').forEach(node => node.setAttribute('aria-valuenow', completion.toFixed(0)));
            }

            function markRows() {
                document.querySelectorAll('[data-criterion-row]').forEach(row => {
                    const scoreInput = row.querySelector('.score-input');
                    const answered = IS_NUMERIC
                        ? Boolean(scoreInput && scoreInput.value !== '')
                        : Boolean(row.querySelector('.decision-input:checked'));
                    row.classList.toggle('is-answered', answered);
                });
            }

            function recalculateNumeric() {
                const inputs = [...document.querySelectorAll('.score-input')];
                let overallScore = 0;
                let overallAnswered = 0;

                inputs.forEach(input => {
                    const maximum = Number.parseFloat(input.dataset.max || '0') || 0;
                    const hasValue = input.value !== '';
                    let value = Number.parseFloat(input.value);

                    if (!Number.isFinite(value)) value = 0;
                    value = Math.max(0, Math.min(maximum, value));
                    if (hasValue && Number.parseFloat(input.value) !== value) input.value = value;
                    if (hasValue) overallAnswered++;
                    overallScore += hasValue ? value : 0;
                });

                document.querySelectorAll('[data-evaluation-section]').forEach(section => {
                    const ids = subtreeIds(section);
                    const relevant = inputs.filter(input => ids.includes(String(input.dataset.sectionId)));
                    const answered = relevant.filter(input => input.value !== '').length;
                    const score = relevant.reduce((sum, input) => sum + (Number.parseFloat(input.value) || 0), 0);
                    const maximum = relevant.reduce((sum, input) => sum + (Number.parseFloat(input.dataset.max) || 0), 0);
                    const percentage = maximum > 0 ? clampPercent((score / maximum) * 100) : 0;

                    setCompletion(section, answered, Number(section.dataset.questionTotal || 0));
                    section.querySelectorAll('[data-section-score]').forEach(node => node.textContent = score.toFixed(2));
                    section.querySelectorAll('[data-section-percent]').forEach(node => node.textContent = percentage.toFixed(1));

                    const hiddenScore = section.querySelector('.section-score-input');
                    if (hiddenScore) hiddenScore.value = score.toFixed(2);
                });

                setOverallCompletion(overallAnswered);
                const overallScoreNode = byId('overallScore');
                if (overallScoreNode) overallScoreNode.textContent = overallScore.toFixed(2);
                document.querySelectorAll('[data-overall-score-percent]').forEach(node => {
                    const percentage = OVERALL_MAXIMUM > 0 ? clampPercent((overallScore / OVERALL_MAXIMUM) * 100) : 0;
                    node.textContent = percentage.toFixed(1);
                });
            }

            function recalculateCategorical() {
                const inputs = [...document.querySelectorAll('.decision-input:checked')];
                const overallAnswered = inputs.length;

                document.querySelectorAll('[data-evaluation-section]').forEach(section => {
                    const ids = subtreeIds(section);
                    const relevant = inputs.filter(input => ids.includes(String(input.dataset.sectionId)));
                    const answered = relevant.length;

                    setCompletion(section, answered, Number(section.dataset.questionTotal || 0));
                    section.querySelectorAll('[data-decision-value]').forEach(counter => {
                        counter.textContent = relevant.filter(input =>
                            input.value === counter.dataset.decisionValue
                        ).length;
                    });
                });

                document.querySelectorAll('[data-overall-decision-value]').forEach(counter => {
                    counter.textContent = inputs.filter(input =>
                        input.value === counter.dataset.overallDecisionValue
                    ).length;
                });
                setOverallCompletion(overallAnswered);
            }

            function recalculate() {
                if (IS_NUMERIC) recalculateNumeric();
                else recalculateCategorical();
                markRows();
            }

            let saveTimer = null;
            let saveInProgress = false;
            let saveQueued = false;
            let hasUnsavedChanges = false;
            let finalSubmitting = false;
            let changeVersion = 0;

            function setDraftStatus(message, state = 'idle') {
                if (draftStatus) {
                    draftStatus.textContent = message;
                    draftStatus.dataset.state = state;
                }
                if (evalStatus && state !== 'error') {
                    evalStatus.textContent = state === 'saving' ? 'Saving' : (state === 'saved' ? 'Draft saved' : 'Draft');
                    evalStatus.className = state === 'saved' ? 'status-ready' : '';
                }
            }

            function scheduleDraftSave() {
                hasUnsavedChanges = true;
                changeVersion++;
                setDraftStatus('Unsaved changes — saving shortly…');
                window.clearTimeout(saveTimer);
                saveTimer = window.setTimeout(() => saveDraft(false), 1200);
            }

            async function saveDraft(manual = false) {
                if (!finalForm || (!hasUnsavedChanges && !manual)) return;

                if (saveInProgress) {
                    saveQueued = true;
                    return;
                }

                saveInProgress = true;
                saveQueued = false;
                window.clearTimeout(saveTimer);
                saveDraftButton?.setAttribute('disabled', 'disabled');
                setDraftStatus('Saving draft…', 'saving');
                const savingVersion = changeVersion;

                const payload = new FormData(finalForm);
                payload.delete('video');

                try {
                    const response = await fetch(SAVE_URL, {
                        method: 'POST',
                        body: payload,
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const result = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        const firstError = result.errors
                            ? Object.values(result.errors).flat()[0]
                            : null;
                        throw new Error(firstError || result.message || 'The draft could not be saved.');
                    }

                    hasUnsavedChanges = changeVersion !== savingVersion;
                    if (hasUnsavedChanges) saveQueued = true;
                    const savedTime = result.saved_at ? new Date(result.saved_at) : new Date();
                    setDraftStatus(`Draft saved at ${savedTime.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}.`, 'saved');
                } catch (error) {
                    hasUnsavedChanges = true;
                    setDraftStatus(error.message || 'Draft save failed. Try Save draft again.', 'error');
                    if (manual) window.alert(error.message || 'The draft could not be saved.');
                } finally {
                    saveInProgress = false;
                    saveDraftButton?.removeAttribute('disabled');
                    if (saveQueued) {
                        saveTimer = window.setTimeout(() => saveDraft(false), 250);
                    }
                }
            }

            document.addEventListener('input', event => {
                if (event.target.matches('.score-input, .decision-input')) recalculate();
                if (event.target.matches('.score-input, .decision-input, .evidence-comment, .section-notes textarea')) {
                    scheduleDraftSave();
                }
            });
            document.addEventListener('change', event => {
                if (event.target.matches('.score-input, .decision-input')) recalculate();
                if (event.target.matches('.score-input, .decision-input, .evidence-comment, .section-notes textarea')) {
                    scheduleDraftSave();
                }
            });

            saveDraftButton?.addEventListener('click', () => saveDraft(true));
            finalVideo?.addEventListener('change', () => {
                if (finalVideo.files.length) {
                    if (verificationError) verificationError.hidden = true;
                    cameraStatus.className = 'camera-status ready';
                    evalStatus.textContent = 'Verified';
                    evalStatus.className = 'status-ready';
                    toolsToggle?.classList.add('is-verified');
                }
            });

            document.querySelectorAll('[data-node-toggle]').forEach(button => {
                button.addEventListener('click', () => {
                    const content = byId(button.getAttribute('aria-controls'));
                    const expanded = button.getAttribute('aria-expanded') === 'true';
                    button.setAttribute('aria-expanded', String(!expanded));
                    button.title = `${expanded ? 'Expand' : 'Collapse'} ${button.closest('.assessment-node')?.querySelector('h2')?.textContent?.trim() || 'section'}`;
                    content?.classList.toggle('is-collapsed', expanded);
                });
            });

            finalForm?.addEventListener('submit', event => {
                recalculate();
                if (!finalVideo?.files.length) {
                    event.preventDefault();
                    if (verificationError) {
                        verificationError.textContent = 'Record or upload an identity verification video before final submission. Your draft remains saved.';
                        verificationError.hidden = false;
                    }
                    openEvaluatorTools(finalVideo);
                    return;
                }

                finalSubmitting = true;
                hasUnsavedChanges = false;
            });

            window.addEventListener('beforeunload', event => {
                if (!hasUnsavedChanges || finalSubmitting) return;
                event.preventDefault();
                event.returnValue = '';
            });

            recalculate();
        })();
    </script>
@endsection
