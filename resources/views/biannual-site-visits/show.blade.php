@extends('layouts.app')

@section('title', ($visit->reference_number ?: 'Bi-Annual Site Visit'))
@section('lean_admin_scripts', '1')

@push('styles')
    @include('biannual-site-visits.partials.styles')
    <style>
        .basv-workspace {
            display: grid;
            grid-template-columns: minmax(190px, 245px) minmax(0, 1fr);
            gap: 1rem;
            align-items: start;
        }

        .basv-action-bar {
            position: sticky;
            z-index: 12;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 1rem;
            padding: .8rem 1rem;
            border: 1px solid var(--basv-border);
            border-radius: .85rem .85rem 0 0;
            background: rgba(255, 255, 255, .96);
            box-shadow: 0 -10px 30px rgba(15, 42, 39, .09);
            backdrop-filter: blur(8px);
        }

        .basv-score-panel {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .6rem;
        }

        .basv-score-box {
            padding: .8rem;
            border: 1px solid var(--basv-border);
            border-radius: .7rem;
            background: #fbfdfc;
            text-align: center;
        }

        .basv-score-box strong {
            display: block;
            color: var(--basv-green-dark);
            font-size: 1.15rem;
        }

        .basv-score-box span {
            color: var(--basv-muted);
            font-size: .65rem;
            font-weight: 750;
        }

        @media (max-width: 991.98px) {
            .basv-workspace {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $siteVisit = $visit->siteVisit;
        $status = $siteVisit?->status ?: 'draft';
        $team = $siteVisit?->group;
        $visibleQuestionKeySet = array_fill_keys($visibleQuestionKeys ?? [], true);
        $questionKey = static fn (array $question, int $sectionIndex, int $topicIndex, int $questionIndex): string =>
            (string) ($question['key']
                ?? $question['stable_key']
                ?? 'Q-'.$sectionIndex.'-'.$topicIndex.'-'.$questionIndex);
        $visibleCount = static function (array $questions, int $sectionIndex, int $topicIndex) use (
            $visibleQuestionKeySet,
            $questionKey
        ): int {
            return collect($questions)->filter(
                static fn (array $question, int $questionIndex): bool => isset(
                    $visibleQuestionKeySet[$questionKey($question, $sectionIndex, $topicIndex, $questionIndex)]
                )
            )->count();
        };
    @endphp

    <main class="nxl-container">
        <div class="nxl-content basv-page">
            <div class="basv-hero">
                <div>
                    <span class="basv-eyebrow">
                        <i class="feather-map-pin"></i> {{ $visit->reference_number }}
                        <span>·</span> {{ $visit->cycleLabel() }}
                    </span>
                    <h1>{{ $visit->title }}</h1>
                    <p>{{ $visit->thinkTank?->name }}{{ $visit->location ? ' · '.$visit->location : '' }}.
                        Questionnaire version {{ $visit->template_version }} is locked to this visit.</p>
                </div>
                <div class="basv-hero-actions">
                    @if ($canManageSchedule)
                        <a href="{{ route('biannual-site-visits.edit', $visit) }}" class="basv-btn basv-btn-light">
                            <i class="feather-edit-3"></i> Edit schedule
                        </a>
                    @elseif (! $visit->is_active && $visit->hasMutableWorkflowStatus() && auth()->user()?->can('biannual_site_visits.create'))
                        <form method="POST" action="{{ route('biannual-site-visits.reactivate', $visit) }}"
                            onsubmit="return confirm('Reactivate this scheduled site visit with all previous responses?')">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="basv-btn basv-btn-light">
                                <i class="feather-refresh-cw"></i> Reactivate
                            </button>
                        </form>
                    @endif
                    <a href="{{ route('biannual-site-visits.index') }}" class="basv-btn basv-btn-light">
                        <i class="feather-arrow-left"></i> Register
                    </a>
                    @canany(['biannual_site_visits.export', 'biannual_site_visits.approve'])
                        <a href="{{ route('biannual-site-visits.pdf', $visit) }}" class="basv-btn basv-btn-light">
                            <i class="feather-download"></i> PDF
                        </a>
                    @endcanany
                </div>
            </div>

            @if (session('success'))
                <div class="basv-alert success"><i class="feather-check-circle me-1"></i>{{ session('success') }}</div>
            @endif
            @if (! $visit->is_active)
                <div class="basv-alert danger">
                    <i class="feather-slash me-1"></i>
                    <strong>This scheduled visit is inactive and read-only.</strong>
                    @if ($visit->deactivation_reason)
                        Reason: {{ $visit->deactivation_reason }}
                    @endif
                    Its questionnaire responses and audit history remain available.
                </div>
            @endif
            @if ($errors->any())
                <div class="basv-alert danger">
                    <strong>Please resolve the following before continuing:</strong>
                    <ul class="mb-0 mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="basv-stats">
                <div class="basv-stat">
                    <span class="basv-stat-icon"><i class="feather-check-square"></i></span>
                    <div><strong>{{ $completion['answered'] ?? 0 }}/{{ $completion['total'] ?? 0 }}</strong><span>Questions answered</span></div>
                </div>
                <div class="basv-stat">
                    <span class="basv-stat-icon"><i class="feather-pie-chart"></i></span>
                    <div><strong>{{ round($completion['percentage'] ?? 0) }}%</strong><span>Questionnaire complete</span></div>
                </div>
                <div class="basv-stat">
                    <span class="basv-stat-icon"><i class="feather-users"></i></span>
                    <div><strong>{{ $team?->members?->count() ?? 0 }}</strong><span>Monitoring team</span></div>
                </div>
                <div class="basv-stat">
                    <span class="basv-stat-icon"><i class="feather-activity"></i></span>
                    <div>
                        <strong style="font-size:.88rem">{{ $visit->is_active ? ucfirst(str_replace('_', ' ', $status)) : 'Inactive' }}</strong>
                        <span>{{ $visit->is_active ? 'Workflow status' : 'Lifecycle status' }}</span>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-lg-8">
                    <div class="basv-card h-100 mb-0">
                        <div class="basv-card-head"><h2><i class="feather-info me-2"></i>Visit brief</h2></div>
                        <div class="basv-card-body">
                            <div class="row g-3">
                                <div class="col-sm-4">
                                    <div class="basv-help">Think Tank</div>
                                    <strong>{{ $visit->thinkTank?->name ?? '—' }}</strong>
                                </div>
                                <div class="col-sm-4">
                                    <div class="basv-help">Visit dates</div>
                                    <strong>{{ optional($visit->starts_on)->format('d M Y') }} – {{ optional($visit->ends_on)->format('d M Y') }}</strong>
                                </div>
                                <div class="col-sm-4">
                                    <div class="basv-help">Questionnaire</div>
                                    <strong>{{ $visit->template?->name ?? ($snapshot['name'] ?? 'Monitoring tool') }}</strong>
                                </div>
                                @if ($visit->objectives)
                                    <div class="col-12">
                                        <div class="basv-help">Objectives and preparation notes</div>
                                        <div>{{ $visit->objectives }}</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="basv-card h-100 mb-0">
                        <div class="basv-card-head"><h2><i class="feather-bar-chart-2 me-2"></i>Assessment score</h2></div>
                        <div class="basv-card-body">
                            <div class="basv-score-panel">
                                <div class="basv-score-box">
                                    <strong>{{ isset($scores['overall']) ? number_format($scores['overall'], 1).'%' : '—' }}</strong>
                                    <span>Weighted overall</span>
                                </div>
                                <div class="basv-score-box">
                                    <strong>{{ $scores['rated'] ?? 0 }}</strong>
                                    <span>Rated</span>
                                </div>
                                <div class="basv-score-box">
                                    <strong>{{ $scores['not_applicable'] ?? 0 }}</strong>
                                    <span>N/A</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="basv-card">
                <div class="basv-card-head">
                    <h2><i class="feather-users me-2"></i>Assigned monitoring team</h2>
                    <span class="basv-badge">{{ $team?->group_name }}</span>
                </div>
                <div class="basv-card-body">
                    <div class="row g-2">
                        @foreach ($team?->members ?? [] as $member)
                            @php
                                $specialism = data_get($visit->settings, 'team_specialisms.'.$member->user_id);
                            @endphp
                            <div class="col-md-6 col-xl">
                                <div class="p-3 rounded-3 border h-100">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="basv-stat-icon" style="width:36px;height:36px">
                                            <i class="feather-user"></i>
                                        </span>
                                        <div>
                                            <strong class="d-block">{{ $member->user?->name }}</strong>
                                            <span class="basv-record-meta">
                                                {{ (string) $member->user_id === (string) $team?->leader_id ? 'Team Lead' : ($specialism ?: 'Team member') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('biannual-site-visits.answers.update', $visit) }}" id="questionnaire-form">
                @csrf
                @method('PUT')

                <div class="basv-workspace">
                    <aside class="basv-card">
                        <div class="basv-card-head"><h3>Questionnaire sections</h3></div>
                        <div class="basv-card-body p-2">
                            <nav class="basv-section-nav">
                                @foreach ($snapshot['sections'] ?? [] as $sectionIndex => $section)
                                    @php
                                        $sectionVisibleCount = collect($section['topics'] ?? [])->map(
                                            fn (array $topic, int $topicIndex): int => $visibleCount(
                                                $topic['questions'] ?? [],
                                                $sectionIndex,
                                                $topicIndex
                                            )
                                        )->sum();
                                    @endphp
                                    @continue($sectionVisibleCount === 0)
                                    <a class="basv-section-link" href="#section-{{ $sectionIndex + 1 }}">
                                        <span>{{ $sectionIndex + 1 }}. {{ $section['title'] ?? $section['name'] ?? 'Section' }}</span>
                                        <small>{{ $sectionVisibleCount }}</small>
                                    </a>
                                @endforeach
                            </nav>
                        </div>
                    </aside>

                    <div>
                        @foreach ($snapshot['sections'] ?? [] as $sectionIndex => $section)
                            @php
                                $sectionVisibleCount = collect($section['topics'] ?? [])->map(
                                    fn (array $topic, int $topicIndex): int => $visibleCount(
                                        $topic['questions'] ?? [],
                                        $sectionIndex,
                                        $topicIndex
                                    )
                                )->sum();
                            @endphp
                            @continue($sectionVisibleCount === 0)
                            <section class="basv-card" id="section-{{ $sectionIndex + 1 }}" style="scroll-margin-top:120px">
                                <div class="basv-card-head">
                                    <div>
                                        <span class="basv-eyebrow" style="color:var(--basv-green)">Part {{ $sectionIndex + 1 }}</span>
                                        <h2>{{ $section['title'] ?? $section['name'] ?? 'Section' }}</h2>
                                    </div>
                                    <span class="basv-badge">
                                        {{ $sectionVisibleCount }} questions
                                    </span>
                                </div>
                                <div class="basv-card-body">
                                    @if (!empty($section['description']))
                                        <p class="text-muted small">{{ $section['description'] }}</p>
                                    @endif

                                    @foreach ($section['topics'] ?? [] as $topicIndex => $topic)
                                        @php
                                            $topicVisibleCount = $visibleCount(
                                                $topic['questions'] ?? [],
                                                $sectionIndex,
                                                $topicIndex
                                            );
                                        @endphp
                                        @continue($topicVisibleCount === 0)
                                        <div class="basv-topic" id="topic-{{ $sectionIndex + 1 }}-{{ $topicIndex + 1 }}">
                                            <div class="basv-topic-head mb-3">
                                                <h3>{{ $sectionIndex + 1 }}.{{ $topicIndex + 1 }} {{ $topic['title'] ?? $topic['name'] ?? 'Topic' }}</h3>
                                                @if (!empty($topic['description']))
                                                    <p>{{ $topic['description'] }}</p>
                                                @endif
                                                @if (!empty($topic['guidance']))
                                                    <p><i class="feather-info me-1"></i>{{ $topic['guidance'] }}</p>
                                                @endif
                                            </div>

                                            @foreach ($topic['questions'] ?? [] as $questionIndex => $question)
                                                @php
                                                    $key = $questionKey($question, $sectionIndex, $topicIndex, $questionIndex);
                                                    $type = $question['response_type'] ?? 'scored_assessment';
                                                    $answer = $answerMap[$key] ?? [];
                                                    $field = 'answers['.$key.']';
                                                    $value = old('answers.'.$key.'.value', $answer['value'] ?? null);
                                                    $oldScoreSentinel = '__basv_no_old_rating__';
                                                    $oldScore = old('answers.'.$key.'.score', $oldScoreSentinel);
                                                    $hasOldScore = $oldScore !== $oldScoreSentinel;
                                                    $score = $hasOldScore ? $oldScore : ($answer['score'] ?? null);
                                                    $answerIsNa = (bool) ($answer['is_not_applicable'] ?? false);
                                                    $evidenceItems = is_array($value)
                                                        ? (array_is_list($value) ? $value : [$value])
                                                        : [$value];
                                                    $evidenceReference = collect($evidenceItems)->map(
                                                        static function (mixed $item): ?string {
                                                            if (is_scalar($item)) {
                                                                return trim((string) $item) ?: null;
                                                            }
                                                            if (! is_array($item)) {
                                                                return null;
                                                            }

                                                            return $item['reference']
                                                                ?? $item['original_name']
                                                                ?? $item['name']
                                                                ?? $item['url']
                                                                ?? $item['path']
                                                                ?? $item['stored_path']
                                                                ?? null;
                                                        }
                                                    )->filter()->implode(PHP_EOL);
                                                    $questionRatings = collect($question['options'] ?? [])->map(
                                                        static function (mixed $option, int $index): array {
                                                            $option = is_array($option)
                                                                ? $option
                                                                : ['value' => $index, 'label' => $option, 'score' => $index];
                                                            $ratingValue = $option['value'] ?? $option['score'] ?? $index;
                                                            $ratingScore = $option['score'] ?? $ratingValue;
                                                            $ratingLabel = $option['label'] ?? $ratingValue;
                                                            $naAliases = ['na', 'n/a', 'not applicable', 'not_applicable'];

                                                            return [
                                                                'value' => $ratingValue,
                                                                'score' => $ratingScore,
                                                                'label' => $ratingLabel,
                                                                'description' => $option['description']
                                                                    ?? $option['help_text']
                                                                    ?? null,
                                                                'is_na' => (bool) (
                                                                    $option['is_not_applicable']
                                                                        ?? $option['is_na']
                                                                        ?? (
                                                                            in_array(strtolower(trim((string) $ratingValue)), $naAliases, true)
                                                                            || in_array(strtolower(trim((string) $ratingLabel)), $naAliases, true)
                                                                        )
                                                                ),
                                                            ];
                                                        }
                                                    )->values();
                                                    if ($questionRatings->isEmpty()) {
                                                        $questionRatings = collect([
                                                            ['value' => 0, 'score' => 0, 'label' => 'Not applicable', 'description' => null, 'is_na' => true],
                                                            ['value' => 1, 'score' => 1, 'label' => 'Weak', 'description' => null, 'is_na' => false],
                                                            ['value' => 2, 'score' => 2, 'label' => 'Average', 'description' => null, 'is_na' => false],
                                                            ['value' => 3, 'score' => 3, 'label' => 'Strong', 'description' => null, 'is_na' => false],
                                                        ]);
                                                    }
                                                    $selectedRating = $questionRatings->first(
                                                        static fn (array $rating): bool => $hasOldScore
                                                            ? (string) $score === (string) $rating['value']
                                                            : (
                                                                $answerIsNa
                                                                    ? (bool) $rating['is_na']
                                                                    : (
                                                                        ! (bool) $rating['is_na']
                                                                        && (string) $score === (string) $rating['score']
                                                                    )
                                                            )
                                                    );
                                                    $selectedRatingIsNa = $hasOldScore
                                                        ? (bool) data_get($selectedRating, 'is_na', false)
                                                        : $answerIsNa;
                                                @endphp
                                                @continue(!isset($visibleQuestionKeySet[$key]))
                                                <article class="basv-question" data-question-key="{{ $key }}">
                                                    <div class="basv-question-head">
                                                        <div>
                                                            <span class="basv-question-key">{{ $key }}</span>
                                                            <div class="basv-question-title">
                                                                {{ $question['prompt'] ?? $question['question'] ?? 'Question' }}
                                                                @if ($question['required'] ?? false)
                                                                    <span class="text-danger" title="Required">*</span>
                                                                @endif
                                                            </div>
                                                            @if (!empty($question['help_text']))
                                                                <div class="basv-help">{{ $question['help_text'] }}</div>
                                                            @endif
                                                        </div>
                                                        @if ($type !== 'information')
                                                            <span class="basv-badge">{{ str_replace('_', ' ', $type) }}</span>
                                                        @endif
                                                    </div>

                                                    @if ($type !== 'information' && $canEdit)
                                                        <input type="hidden" name="{{ $field }}[_present]" value="1">
                                                    @endif

                                                    @if ($type === 'information')
                                                        <div class="basv-alert mb-0">{{ $question['help_text'] ?? $question['prompt'] ?? '' }}</div>
                                                    @elseif ($type === 'scored_assessment')
                                                        <div class="mb-3">
                                                            <label class="form-label">Rating</label>
                                                            <div class="basv-rating">
                                                                @foreach ($questionRatings as $rating)
                                                                    @php
                                                                        $ratingIsNa = ($question['allows_na'] ?? true)
                                                                            && (bool) ($rating['is_na'] ?? false);
                                                                        $ratingSelected = $selectedRating !== null
                                                                            && (string) $rating['value'] === (string) data_get($selectedRating, 'value')
                                                                            && (bool) $rating['is_na'] === (bool) data_get($selectedRating, 'is_na');
                                                                    @endphp
                                                                    <label>
                                                                        <input type="radio" name="{{ $field }}[score]"
                                                                            value="{{ $rating['value'] }}"
                                                                            data-not-applicable="{{ $ratingIsNa ? '1' : '0' }}"
                                                                            @checked($ratingSelected)
                                                                            @disabled(!$canEdit)>
                                                                        <span>
                                                                            <span class="basv-rating-title"><strong class="me-1">{{ $rating['score'] }}</strong> {{ $rating['label'] }}</span>
                                                                            @if (filled($rating['description'] ?? null))
                                                                                <small class="basv-rating-description">{{ $rating['description'] }}</small>
                                                                            @endif
                                                                        </span>
                                                                    </label>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                        <div class="row g-2">
                                                            <div class="col-md-6">
                                                                <label class="form-label">Strength observed</label>
                                                                <textarea class="form-control" name="{{ $field }}[strength]" @disabled(!$canEdit)
                                                                    placeholder="Evidence of what is working well">{{ old('answers.'.$key.'.strength', $answer['strength'] ?? '') }}</textarea>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Weakness or gap</label>
                                                                <textarea class="form-control" name="{{ $field }}[weakness]" @disabled(!$canEdit)
                                                                    placeholder="Gap, non-compliance, or improvement needed">{{ old('answers.'.$key.'.weakness', $answer['weakness'] ?? '') }}</textarea>
                                                            </div>
                                                            <div class="col-12">
                                                                <label class="form-label">Evidence and assessor notes</label>
                                                                <textarea class="form-control" name="{{ $field }}[evidence_notes]" @disabled(!$canEdit)
                                                                    placeholder="Document references, interviews, observations, and supporting notes">{{ old('answers.'.$key.'.evidence_notes', $answer['evidence_notes'] ?? '') }}</textarea>
                                                            </div>
                                                            <div class="col-12 na-reason" @style([
                                                                'display:none' => !($question['allows_na'] ?? true) || !$selectedRatingIsNa
                                                            ])>
                                                                <label class="form-label">Why is this not applicable?</label>
                                                                <input class="form-control" name="{{ $field }}[not_applicable_reason]"
                                                                    value="{{ old('answers.'.$key.'.not_applicable_reason', $answer['not_applicable_reason'] ?? '') }}"
                                                                    @disabled(!$canEdit)>
                                                            </div>
                                                        </div>
                                                    @elseif ($type === 'evidence')
                                                        <textarea class="form-control" name="{{ $field }}[value]" @disabled(!$canEdit)
                                                            placeholder="Document name, link, path, or evidence reference">{{ $evidenceReference }}</textarea>
                                                    @elseif (in_array($type, ['long_text', 'narrative'], true))
                                                        <textarea class="form-control" name="{{ $field }}[value]" @disabled(!$canEdit)>{{ is_scalar($value) ? $value : '' }}</textarea>
                                                    @elseif ($type === 'short_text')
                                                        <input class="form-control" name="{{ $field }}[value]" value="{{ is_scalar($value) ? $value : '' }}" @disabled(!$canEdit)>
                                                    @elseif (in_array($type, ['number', 'percentage'], true))
                                                        <input class="form-control" type="number" name="{{ $field }}[value]"
                                                            value="{{ is_scalar($value) ? $value : '' }}" @disabled(!$canEdit)
                                                            @if($type === 'percentage') min="0" max="100" step="0.01" @else step="any" @endif>
                                                    @elseif ($type === 'date')
                                                        <input class="form-control" type="date" name="{{ $field }}[value]"
                                                            value="{{ is_scalar($value) ? $value : '' }}" @disabled(!$canEdit)>
                                                    @elseif (in_array($type, ['single_choice', 'yes_no_na'], true))
                                                        @php
                                                            $options = $type === 'yes_no_na'
                                                                ? [['value' => 'yes', 'label' => 'Yes'], ['value' => 'no', 'label' => 'No'], ['value' => 'na', 'label' => 'Not applicable']]
                                                                : ($question['options'] ?? []);
                                                        @endphp
                                                        <select class="form-select" name="{{ $field }}[value]" @disabled(!$canEdit)>
                                                            <option value="">Select an answer</option>
                                                            @foreach ($options as $option)
                                                                @php
                                                                    $optionValue = is_array($option) ? ($option['value'] ?? $option['label'] ?? '') : $option;
                                                                    $optionLabel = is_array($option) ? ($option['label'] ?? $optionValue) : $option;
                                                                @endphp
                                                                <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                                                            @endforeach
                                                        </select>
                                                    @elseif ($type === 'multiple_choice')
                                                        @foreach ($question['options'] ?? [] as $option)
                                                            @php
                                                                $optionValue = is_array($option) ? ($option['value'] ?? $option['label'] ?? '') : $option;
                                                                $optionLabel = is_array($option) ? ($option['label'] ?? $optionValue) : $option;
                                                            @endphp
                                                            <label class="d-flex align-items-center gap-2 mb-2">
                                                                <input type="checkbox" class="form-check-input m-0"
                                                                    name="{{ $field }}[value][]" value="{{ $optionValue }}"
                                                                    @checked(in_array($optionValue, (array) $value, true)) @disabled(!$canEdit)>
                                                                <span>{{ $optionLabel }}</span>
                                                            </label>
                                                        @endforeach
                                                    @else
                                                        <textarea class="form-control" name="{{ $field }}[value]" @disabled(!$canEdit)>{{ is_scalar($value) ? $value : '' }}</textarea>
                                                    @endif
                                                </article>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach

                        @if ($canEdit)
                            <div class="basv-action-bar">
                                <div>
                                    <strong class="d-block">Collaborative draft</strong>
                                    <small class="text-muted">Any assigned team member may save findings. Submission is reserved for the lead.</small>
                                </div>
                                <button class="basv-btn basv-btn-primary" type="submit">
                                    <i class="feather-save"></i> Save draft
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </form>

            @if ($canSubmit)
                <div class="basv-card">
                    <div class="basv-card-head"><h2><i class="feather-send me-2"></i>Lead submission</h2></div>
                    <div class="basv-card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <strong>Submit the consolidated assessment for review</strong>
                            <div class="basv-help">All required visible questions must be complete. The submitted version becomes read-only.</div>
                        </div>
                        <form method="POST" action="{{ route('biannual-site-visits.submit', $visit) }}"
                            onsubmit="return confirm('Submit this questionnaire for formal review?')">
                            @csrf
                            <button class="basv-btn basv-btn-primary" type="submit">
                                <i class="feather-send"></i> Submit for review
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            @if ($canReview)
                <div class="basv-card">
                    <div class="basv-card-head"><h2><i class="feather-shield me-2"></i>Review decision</h2></div>
                    <div class="basv-card-body">
                        <form method="POST" action="{{ route('biannual-site-visits.review', $visit) }}">
                            @csrf
                            <div class="basv-form-grid">
                                <div>
                                    <label class="form-label">Decision</label>
                                    <select class="form-select" name="status" required>
                                        <option value="">Select a decision</option>
                                        <option value="approved">Approve and finalize</option>
                                        <option value="returned">Return for correction</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Reviewer remarks</label>
                                    <textarea class="form-control" name="remarks" placeholder="Required when returning the assessment"></textarea>
                                </div>
                            </div>
                            <div class="text-end mt-3">
                                <button class="basv-btn basv-btn-primary" type="submit">
                                    <i class="feather-check-circle"></i> Record decision
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </main>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('.basv-question').forEach(question => {
            const radios = question.querySelectorAll('input[type="radio"][name$="[score]"]');
            const reason = question.querySelector('.na-reason');
            if (!reason) return;

            radios.forEach(radio => radio.addEventListener('change', () => {
                reason.style.display = radio.checked && radio.dataset.notApplicable === '1' ? '' : 'none';
            }));
        });
    </script>
@endpush
