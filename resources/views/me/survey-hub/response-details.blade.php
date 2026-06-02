@extends('layouts.app')
@section('title', 'Survey Response Details')

@push('styles')
    <style>
        .survey-detail-filter {
            border: 1px solid #dbe4ef;
            border-radius: 20px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
        }

        .survey-detail-focus,
        .survey-detail-pattern {
            border: 1px solid #dbe4ef;
            border-radius: 20px;
            background: #ffffff;
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.06);
            height: 100%;
            overflow: hidden;
        }

        .survey-detail-focus__body,
        .survey-detail-pattern__body {
            padding: 1.15rem;
        }

        .survey-detail-eyebrow {
            color: #64748b;
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .survey-detail-title {
            color: #0f172a;
            font-size: 1.08rem;
            font-weight: 800;
            line-height: 1.45;
            margin-top: 0.35rem;
        }

        .survey-detail-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.38rem 0.72rem;
            border-radius: 999px;
            border: 1px solid #dbe4ef;
            background: #f8fafc;
            color: #334155;
            font-size: 0.8rem;
            font-weight: 700;
            margin: 0.55rem 0.45rem 0 0;
        }

        .survey-detail-progress {
            height: 0.58rem;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
            margin-top: 1rem;
        }

        .survey-detail-progress span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #0f766e, #0ea5e9);
        }

        .survey-pattern-row {
            padding: 0.8rem 0;
            border-bottom: 1px solid #edf2f7;
        }

        .survey-pattern-row:last-child {
            border-bottom: 0;
        }

        .survey-pattern-row__head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            color: #0f172a;
            font-size: 0.9rem;
            font-weight: 750;
        }

        .survey-pattern-row__label {
            min-width: 0;
            word-break: break-word;
        }

        .survey-pattern-row__count {
            color: #0f766e;
            white-space: nowrap;
        }

        .survey-pattern-bar {
            height: 0.48rem;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
            margin-top: 0.55rem;
        }

        .survey-pattern-bar span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #f97316, #0f766e);
        }

        .survey-response-feed {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1rem;
            padding: 1rem;
        }

        .survey-response-feed.is-all {
            grid-template-columns: minmax(0, 1fr);
        }

        .survey-answer-card {
            border: 1px solid #dbe4ef;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
            overflow: hidden;
            min-width: 0;
        }

        .survey-answer-card.is-missing {
            background: #f8fafc;
            border-style: dashed;
        }

        .survey-answer-card__head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.85rem;
            padding: 0.95rem 1rem;
            border-bottom: 1px solid #edf2f7;
            background: linear-gradient(135deg, rgba(15, 118, 110, 0.08), rgba(14, 165, 233, 0.05));
        }

        .survey-answer-person {
            display: flex;
            gap: 0.75rem;
            min-width: 0;
        }

        .survey-answer-avatar {
            width: 2.45rem;
            height: 2.45rem;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #0f766e;
            color: #ffffff;
            font-size: 0.82rem;
            font-weight: 800;
            flex: 0 0 auto;
        }

        .survey-answer-name {
            color: #0f172a;
            font-size: 0.96rem;
            font-weight: 800;
            line-height: 1.35;
            word-break: break-word;
        }

        .survey-answer-meta {
            color: #64748b;
            font-size: 0.78rem;
            line-height: 1.45;
            margin-top: 0.15rem;
            word-break: break-word;
        }

        .survey-answer-status {
            display: inline-flex;
            align-items: center;
            gap: 0.32rem;
            padding: 0.34rem 0.62rem;
            border-radius: 999px;
            border: 1px solid #bbf7d0;
            background: #f0fdf4;
            color: #15803d;
            font-size: 0.76rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .survey-answer-status.is-missing {
            border-color: #e2e8f0;
            background: #f1f5f9;
            color: #64748b;
        }

        .survey-answer-card__body {
            padding: 1rem;
        }

        .survey-answer-value {
            color: #1e293b;
            font-size: 0.92rem;
            line-height: 1.6;
            white-space: pre-wrap;
            word-break: break-word;
            min-height: 3.2rem;
        }

        .survey-answer-value.is-empty {
            color: #94a3b8;
            font-style: italic;
        }

        .survey-all-answer-list {
            display: grid;
            gap: 0.75rem;
        }

        .survey-all-answer-item {
            border: 1px solid #dbe4ef;
            border-radius: 12px;
            background: #f8fafc;
            padding: 0.85rem;
        }

        .survey-all-answer-item.is-empty {
            background: #ffffff;
            border-style: dashed;
        }

        .survey-all-answer-section {
            color: #0f766e;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .survey-all-answer-question {
            color: #0f172a;
            font-size: 0.92rem;
            font-weight: 800;
            line-height: 1.45;
            margin-top: 0.25rem;
            word-break: break-word;
        }

        .survey-all-answer-type {
            display: inline-flex;
            margin: 0.45rem 0 0.5rem;
            border: 1px solid #cbd5e1;
            border-radius: 999px;
            background: #ffffff;
            color: #475569;
            font-size: 0.72rem;
            font-weight: 800;
            padding: 0.24rem 0.52rem;
        }

        .survey-answer-card__foot {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            padding: 0 1rem 1rem;
        }

        .survey-answer-foot-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.32rem;
            padding: 0.34rem 0.62rem;
            border-radius: 999px;
            border: 1px solid #dbe4ef;
            background: #f8fafc;
            color: #475569;
            font-size: 0.76rem;
            font-weight: 700;
            max-width: 100%;
        }

        .survey-answer-foot-chip span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .survey-detail-empty {
            color: #64748b;
            padding: 2.5rem 1.25rem;
            text-align: center;
        }

        @media (max-width: 575.98px) {
            .survey-response-feed {
                grid-template-columns: 1fr;
                padding: 0.75rem;
            }

            .survey-answer-card__head {
                flex-direction: column;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $heroActions = [
            ['href' => route('budget.me.surveys.responses'), 'label' => 'Response Monitor', 'icon' => 'feather-arrow-left', 'class' => 'btn btn-light btn-sm'],
            ['href' => route('budget.me.surveys.responses.explore.export.excel', ['surveyLink' => $surveyLink, 'question_key' => $selectedQuestionKey]), 'label' => 'Export Excel', 'icon' => 'feather-file', 'class' => 'btn btn-light btn-sm'],
            ['href' => route('budget.me.surveys.responses.explore.export.pdf', ['surveyLink' => $surveyLink, 'question_key' => $selectedQuestionKey]), 'label' => 'Download PDF', 'icon' => 'feather-file-text', 'class' => 'btn btn-outline-light btn-sm'],
            ['href' => $surveyLink->public_url, 'label' => 'Public Link', 'icon' => 'feather-external-link', 'class' => 'btn btn-outline-light btn-sm'],
        ];
        $completionRate = (float) ($selectedQuestionStats['completion_rate'] ?? 0);
        $completionWidth = min(100, max(0, $completionRate));
        $breakdownRows = collect($answerBreakdown['rows'] ?? []);
        $coverageDenominator = max(0, (int) ($stats['answered'] ?? 0) + (int) ($stats['missing'] ?? 0));
    @endphp

    <div class="nxl-container">
        @include('me.survey-hub._header', [
            'active' => 'responses',
            'title' => 'Response Details',
            'subtitle' => ($surveyLink->indicator->name ?? 'Survey') . ' question-level response review.',
            'heroActions' => $heroActions,
        ])

        @include('me.survey-hub._alerts')

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="survey-stat p-3">
                    <div class="survey-stat__label">Submissions</div>
                    <div class="survey-stat__value mt-2">{{ $stats['responses'] ?? 0 }}</div>
                    <div class="survey-stat__meta mt-2">{{ $surveyLink->is_active ? 'Survey link active' : 'Survey link inactive' }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="survey-stat p-3">
                    <div class="survey-stat__label">Answered</div>
                    <div class="survey-stat__value mt-2">{{ $stats['answered'] ?? 0 }}</div>
                    <div class="survey-stat__meta mt-2">{{ $isAllQuestions ? 'Answered question fields.' : 'For the selected question.' }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="survey-stat p-3">
                    <div class="survey-stat__label">Missing</div>
                    <div class="survey-stat__value mt-2">{{ $stats['missing'] ?? 0 }}</div>
                    <div class="survey-stat__meta mt-2">{{ $isAllQuestions ? 'Unanswered question fields.' : 'No answer captured for this field.' }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="survey-stat p-3">
                    <div class="survey-stat__label">Latest</div>
                    <div class="survey-stat__value mt-2" style="font-size: 1.1rem;">
                        {{ !empty($stats['last_response']) ? \Illuminate\Support\Carbon::parse($stats['last_response'])->format('d M Y') : 'No data' }}
                    </div>
                    <div class="survey-stat__meta mt-2">
                        {{ !empty($stats['last_response']) ? \Illuminate\Support\Carbon::parse($stats['last_response'])->format('H:i') : 'Awaiting submissions' }}
                    </div>
                </div>
            </div>
        </div>

        <div class="survey-detail-filter p-3 mb-4">
            <form method="GET" action="{{ route('budget.me.surveys.responses.explore', $surveyLink) }}" class="row g-3 align-items-end">
                <div class="col-xl-7">
                    <label class="form-label fw-semibold">Questionnaire Question</label>
                    <select name="question_key" class="form-select" onchange="this.form.submit()">
                        @forelse ($questionOptions as $questionOption)
                            <option value="{{ $questionOption['key'] }}" @selected((string) $selectedQuestionKey === (string) $questionOption['key'])>
                                {{ $questionOption['label'] }} ({{ $questionOption['answered_count'] }} answered)
                            </option>
                        @empty
                            <option value="">No questionnaire questions available</option>
                        @endforelse
                    </select>
                </div>
                <div class="col-xl-3">
                    <label class="form-label fw-semibold">Survey</label>
                    <div class="form-control bg-light text-truncate">
                        {{ $methodology->name ?? $surveyLink->methodology->name ?? 'Questionnaire' }}
                    </div>
                </div>
                <div class="col-xl-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="feather-filter me-1"></i> Apply
                    </button>
                </div>
            </form>
        </div>

        @if ($questionOptions->isEmpty())
            <div class="survey-panel">
                <div class="survey-detail-empty">No questionnaire questions were found for this survey link.</div>
            </div>
        @else
            <div class="row g-4 mb-4">
                <div class="col-xl-7">
                    <div class="survey-detail-focus">
                        <div class="survey-detail-focus__body">
                            <div class="survey-detail-eyebrow">Selected Question</div>
                            <div class="survey-detail-title">{{ $selectedQuestion['label'] ?? 'Question' }}</div>
                            <div>
                                <span class="survey-detail-chip">
                                    <i class="feather-layers"></i>
                                    {{ trim((string) ($selectedQuestion['section_title'] ?? '')) ?: 'General section' }}
                                </span>
                                <span class="survey-detail-chip">
                                    <i class="feather-tag"></i>
                                    {{ \Illuminate\Support\Str::headline($selectedQuestion['type'] ?? 'Question') }}
                                </span>
                                <span class="survey-detail-chip">
                                    <i class="feather-check-circle"></i>
                                    {{ number_format($completionRate, 1) }}% complete
                                </span>
                            </div>
                            <div class="survey-detail-progress" aria-hidden="true">
                                <span style="width: {{ $completionWidth }}%;"></span>
                            </div>
                            <div class="survey-muted small mt-3">
                                {{ $selectedQuestionStats['headline'] ?? 'No responses yet.' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-5">
                    <div class="survey-detail-pattern">
                        <div class="survey-detail-pattern__body">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="survey-detail-eyebrow">Answer Pattern</div>
                                    <div class="survey-detail-title">{{ $answerBreakdown['title'] ?? 'Current Summary' }}</div>
                                    <div class="survey-muted small mt-1">{{ $answerBreakdown['subtitle'] ?? 'Selected question summary.' }}</div>
                                </div>
                                <span class="survey-detail-chip mt-0">
                                    <i class="feather-bar-chart-2"></i>
                                    {{ $stats['answered'] ?? 0 }}/{{ $isAllQuestions ? $coverageDenominator : ($stats['responses'] ?? 0) }}
                                </span>
                            </div>

                            <div class="mt-3">
                                @forelse ($breakdownRows as $row)
                                    <div class="survey-pattern-row">
                                        <div class="survey-pattern-row__head">
                                            <div class="survey-pattern-row__label" title="{{ $row['label'] }}">
                                                {{ \Illuminate\Support\Str::limit($row['label'], 95) }}
                                            </div>
                                            <div class="survey-pattern-row__count">
                                                {{ $row['count'] }} | {{ number_format((float) $row['percent'], 1) }}%
                                            </div>
                                        </div>
                                        <div class="survey-pattern-bar" aria-hidden="true">
                                            <span style="width: {{ min(100, max(0, (float) $row['percent'])) }}%;"></span>
                                        </div>
                                    </div>
                                @empty
                                    <div class="survey-detail-empty py-4">No answer pattern is available for this question yet.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="survey-panel">
                <div class="survey-panel__header">
                    <div>
                        <div class="survey-panel__title">{{ $isAllQuestions ? 'All Question Responses' : 'Question Responses' }}</div>
                        <p class="survey-panel__subtitle">
                            {{ $isAllQuestions
                                ? 'Each card shows one submission with every captured questionnaire question and answer.'
                                : 'Each card shows who submitted the response, the selected answer, and the survey context.' }}
                        </p>
                    </div>
                </div>

                <div class="survey-response-feed {{ $isAllQuestions ? 'is-all' : '' }}">
                    @forelse ($answerRows as $answerRow)
                        <article class="survey-answer-card {{ $answerRow['has_answer'] ? '' : 'is-missing' }}">
                            <div class="survey-answer-card__head">
                                <div class="survey-answer-person">
                                    <div class="survey-answer-avatar">{{ $answerRow['respondent_initials'] }}</div>
                                    <div class="min-w-0">
                                        <div class="survey-answer-name">{{ $answerRow['respondent_name'] }}</div>
                                        <div class="survey-answer-meta">
                                            {{ $answerRow['respondent_organization'] ?: 'No organization captured' }}
                                            @if ($answerRow['respondent_email'])
                                                <br>{{ $answerRow['respondent_email'] }}
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <span class="survey-answer-status {{ $answerRow['has_answer'] ? '' : 'is-missing' }}">
                                    <i class="feather-{{ $answerRow['has_answer'] ? 'check' : 'minus-circle' }}"></i>
                                    {{ $isAllQuestions
                                        ? (($answerRow['answers_count'] ?? 0) . '/' . ($answerRow['question_count'] ?? 0) . ' answered')
                                        : ($answerRow['has_answer'] ? 'Answered' : 'Missing') }}
                                </span>
                            </div>

                            <div class="survey-answer-card__body">
                                @if ($isAllQuestions)
                                    <div class="survey-detail-eyebrow mb-2">Questions And Answers</div>
                                    <div class="survey-all-answer-list">
                                        @forelse (($answerRow['answers'] ?? []) as $answerItem)
                                            <div class="survey-all-answer-item {{ !empty($answerItem['has_answer']) ? '' : 'is-empty' }}">
                                                <div class="survey-all-answer-section">{{ $answerItem['section_title'] ?? 'General section' }}</div>
                                                <div class="survey-all-answer-question">{{ $answerItem['question'] ?? 'Question' }}</div>
                                                <div class="survey-all-answer-type">{{ $answerItem['type'] ?? 'Question' }}</div>
                                                <div class="survey-answer-value {{ !empty($answerItem['has_answer']) ? '' : 'is-empty' }}">
                                                    {{ $answerItem['answer_value'] ?? 'No answer captured.' }}
                                                </div>
                                            </div>
                                        @empty
                                            <div class="survey-answer-value is-empty">No question answers were captured in this submission.</div>
                                        @endforelse
                                    </div>
                                @else
                                    <div class="survey-detail-eyebrow mb-2">Answer</div>
                                    <div class="survey-answer-value {{ $answerRow['has_answer'] ? '' : 'is-empty' }}">
                                        {{ $answerRow['answer_value'] }}
                                    </div>
                                @endif
                            </div>

                            <div class="survey-answer-card__foot">
                                <span class="survey-answer-foot-chip">
                                    <i class="feather-clock"></i>
                                    <span>{{ $answerRow['submitted_at'] }}</span>
                                </span>
                                @if ($answerRow['respondent_phone'])
                                    <span class="survey-answer-foot-chip">
                                        <i class="feather-phone"></i>
                                        <span>{{ $answerRow['respondent_phone'] }}</span>
                                    </span>
                                @endif
                                <span class="survey-answer-foot-chip">
                                    <i class="feather-target"></i>
                                    <span>{{ $answerRow['indicator_name'] }}</span>
                                </span>
                            </div>
                        </article>
                    @empty
                        <div class="survey-detail-empty">No responses were submitted for this survey link.</div>
                    @endforelse
                </div>
            </div>
        @endif
    </div>
@endsection
