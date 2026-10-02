@extends('layouts.app')

@section('title', 'Evaluation Submission Report')

@section('content')
@php
    $evaluation = $submission->evaluation;
    $isNumeric = $evaluation?->usesNumericScoring() ?? false;
    $sectionOutline = $evaluation
        ? \App\Support\EvaluationSectionHierarchy::flattened($evaluation)
        : collect();
    $applicantName = $submission->applicant?->display_name ?? 'Applicant';
    $submissionCode = $submission->applicant?->procurement_submission_code ?? 'Not recorded';
@endphp
<main class="nxl-container evr-shell" aria-labelledby="evaluationSubmissionReportTitle">
    <header class="evr-hero">
        <div class="evr-hero__copy">
            <span class="evr-eyebrow">Individual evaluation submission report</span>
            <h1 id="evaluationSubmissionReportTitle">{{ $applicantName }}</h1>
            <p>{{ $submission->evaluation?->name ?? 'Evaluation' }} &middot; {{ $submission->procurement?->title ?? 'Procurement not recorded' }}</p>
            <div class="evr-hero__meta">
                <span><i class="feather-hash" aria-hidden="true"></i>{{ $submissionCode }}</span>
                <span><i class="feather-user" aria-hidden="true"></i>{{ $submission->evaluator?->name ?? 'Evaluator not recorded' }}</span>
                <span><i class="feather-clock" aria-hidden="true"></i>{{ $submission->submitted_at?->format('d M Y, H:i') ?? 'Submission time not recorded' }}</span>
            </div>
        </div>
        <div class="evr-hero__actions evr-no-print" aria-label="Report actions">
            <a href="{{ route('reports.evaluations.index') }}" class="evr-btn evr-btn--ghost"><i class="feather-arrow-left" aria-hidden="true"></i> Reports</a>
            <a href="{{ route('reports.evaluations.submission.pdf', $submission) }}" class="evr-btn evr-btn--light"><i class="feather-download" aria-hidden="true"></i> Download PDF</a>
            <a href="{{ route('reports.evaluations.submission.anonymised-pdf', $submission) }}" class="evr-btn evr-btn--ghost"><i class="feather-shield" aria-hidden="true"></i> Anonymised PDF</a>
            <button type="button" class="evr-btn evr-btn--ghost" onclick="window.print()"><i class="feather-printer" aria-hidden="true"></i> Print</button>
        </div>
    </header>

    <section class="eval-report-section evr-evidence" aria-labelledby="submissionEvidenceTitle">
        <header class="eval-section-head">
            <span class="eval-section-icon"><i class="feather-clipboard" aria-hidden="true"></i></span>
            <div>
                <h2 id="submissionEvidenceTitle">Submitted section evidence</h2>
                <p>The hierarchy, scores or decisions, evaluator responses, and section feedback below reproduce the submitted evaluation record in its configured form structure.</p>
            </div>
        </header>
        <div class="eval-section-body evr-evidence__body">
            @forelse ($sectionOutline as $node)
                @php
                    $section = $node['section'];
                    $sectionScore = $submission->sectionScores->firstWhere('evaluation_section_id', $section->id);
                    $sectionTotal = $isNumeric
                        ? \App\Support\EvaluationSectionHierarchy::numericSubtotal($submission, $section)
                        : null;
                    $sectionMax = $isNumeric ? $section->subtotalMaxScore() : null;
                    $sectionDistribution = $isNumeric
                        ? []
                        : \App\Support\EvaluationSectionHierarchy::decisionDistribution($submission, $section);
                @endphp

                <article class="evr-evidence-node hierarchy-tone-{{ $node['root_index'] % 8 }}"
                    style="--evidence-depth: {{ min($node['depth'], 3) }};">
                    <header class="evr-evidence-node__head">
                        <div>
                            <span class="evr-evidence-node__level">{{ $node['label'] }} {{ $node['number'] }}</span>
                            <h3>{{ $section->name }}</h3>
                        </div>
                        @if ($section->show_subtotal && $isNumeric)
                            <span class="evr-evidence-node__subtotal">
                                Subtotal {{ number_format($sectionTotal, 2) }} / {{ number_format($sectionMax, 2) }}
                            </span>
                        @elseif ($section->show_subtotal)
                            <span class="evr-evidence-node__distribution">
                                @foreach ($sectionDistribution as $decision => $count)
                                    <span>{{ $decision }}: {{ $count }}</span>
                                @endforeach
                            </span>
                        @endif
                    </header>

                    @if ($section->criteria->isNotEmpty())
                        <div class="eval-table-scroll" tabindex="0" role="region" aria-label="{{ $section->name }} submitted criteria">
                            <table class="eval-table evr-evidence-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Criterion</th>
                                        @if ($isNumeric)
                                            <th scope="col" class="eval-number">Maximum</th>
                                            <th scope="col" class="eval-number">Score</th>
                                        @else
                                            <th scope="col">Decision</th>
                                        @endif
                                        <th scope="col">Evaluator response</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($section->criteria as $criteria)
                                        @php
                                            $criteriaScore = $submission->criteriaScores->firstWhere('evaluation_criteria_id', $criteria->id);
                                            $decisionLabel = $isNumeric ? null : $evaluation?->decisionLabel($criteriaScore?->decision);
                                        @endphp
                                        <tr>
                                            <td>{{ $criteria->name }}</td>
                                            @if ($isNumeric)
                                                <td class="eval-number">{{ number_format($criteria->max_score ?? 0, 2) }}</td>
                                                <td class="eval-number">{{ $criteriaScore?->score !== null ? number_format($criteriaScore->score, 2) : 'Not recorded' }}</td>
                                            @else
                                                <td>{{ $decisionLabel ?: 'Not recorded' }}</td>
                                            @endif
                                            <td class="eval-comment">{{ filled($criteriaScore?->comment) ? $criteriaScore->comment : 'Not recorded' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="evr-evidence-feedback">
                            <div>
                                <span>Strengths</span>
                                <p>{{ filled($sectionScore?->strengths) ? $sectionScore->strengths : 'Not recorded' }}</p>
                            </div>
                            <div>
                                <span>Weaknesses</span>
                                <p>{{ filled($sectionScore?->weaknesses) ? $sectionScore->weaknesses : 'Not recorded' }}</p>
                            </div>
                        </div>
                    @else
                        <p class="evr-evidence-node__empty">Grouping section; its questions are organised in the child sections shown below.</p>
                    @endif
                </article>
            @empty
                <div class="eval-empty"><strong>No evaluation sections recorded</strong>This submission does not have a configured section hierarchy.</div>
            @endforelse
        </div>
    </section>

    @include('reports.evaluations.partials.management-report', ['management' => $management])
</main>
@endsection

@push('styles')
    @include('reports.evaluations.partials.report-suite-styles')
    @include('evaluations.partials.hierarchy-theme')
    <style>
        .evr-evidence { margin-top: 24px; }
        .evr-evidence__body { display: grid; gap: 16px; }
        .evr-evidence-node {
            background: #fff;
            border: 1px solid color-mix(in srgb, var(--section-color), #fff 68%);
            border-left: 4px solid var(--section-color);
            border-radius: 12px;
            margin-left: calc(var(--evidence-depth) * 18px);
            overflow: hidden;
        }
        .evr-evidence-node__head {
            align-items: flex-start;
            background: var(--section-soft);
            display: flex;
            gap: 16px;
            justify-content: space-between;
            padding: 15px 17px;
        }
        .evr-evidence-node__head h3 { color: var(--section-deep); font-size: .95rem; margin: 3px 0 0; }
        .evr-evidence-node__level { color: var(--section-color); font-size: .67rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
        .evr-evidence-node__subtotal { color: var(--section-deep); font-size: .75rem; font-weight: 800; white-space: nowrap; }
        .evr-evidence-node__distribution { display: flex; flex-wrap: wrap; gap: 6px; justify-content: flex-end; }
        .evr-evidence-node__distribution span { background: #fff; border: 1px solid color-mix(in srgb, var(--section-color), #fff 70%); border-radius: 999px; color: var(--section-deep); font-size: .68rem; font-weight: 750; padding: 4px 8px; }
        .evr-evidence-table td:first-child { min-width: 220px; }
        .evr-evidence-table .eval-comment { min-width: 220px; white-space: pre-wrap; }
        .evr-evidence-feedback { border-top: 1px solid #e9eef3; display: grid; gap: 12px; grid-template-columns: repeat(2, minmax(0, 1fr)); padding: 14px 16px 16px; }
        .evr-evidence-feedback > div { background: #f8fafb; border: 1px solid #e4eaef; border-radius: 9px; padding: 11px 12px; }
        .evr-evidence-feedback span { color: #526d7d; display: block; font-size: .65rem; font-weight: 800; letter-spacing: .055em; text-transform: uppercase; }
        .evr-evidence-feedback p { color: #29465c; font-size: .75rem; line-height: 1.6; margin: 6px 0 0; white-space: pre-wrap; }
        .evr-evidence-node__empty { color: #617981; font-size: .75rem; margin: 0; padding: 14px 17px; }
        @media (max-width: 767px) {
            .evr-evidence-node { margin-left: 0; }
            .evr-evidence-node__head { flex-direction: column; }
            .evr-evidence-node__distribution { justify-content: flex-start; }
            .evr-evidence-feedback { grid-template-columns: 1fr; }
        }
        @media print {
            .evr-evidence-node { break-inside: auto; margin-left: 0; }
            .evr-evidence-node__head { break-after: avoid; }
            .evr-evidence-feedback { break-inside: avoid; }
        }
    </style>
@endpush
