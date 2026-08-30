@extends('layouts.app')

@section('title', 'Evaluation Submission Report')

@section('content')
    @php
        $evaluation = $submission->evaluation;
        $isNumeric = $evaluation?->usesNumericScoring() ?? false;
        $applicantName = $submission->applicant?->display_name ?? 'Applicant';
        $submissionCode = $submission->applicant?->procurement_submission_code ?? 'N/A';
        $score = $submission->overall_score !== null ? (float) $submission->overall_score : null;
        $scorePercent = ($isNumeric && $score !== null && $overallMax)
            ? min(100, round(($score / max($overallMax, 1)) * 100, 1))
            : null;
        $criteriaTotal = $submission->evaluation?->sections?->sum(fn ($section) => $section->criteria->count()) ?? 0;
        $sectionOutline = $evaluation
            ? \App\Support\EvaluationSectionHierarchy::flattened($evaluation)
            : collect();
        $decisionSummary = collect($evaluation?->decisionOptions() ?? [])
            ->map(function (string $label, int $decision) use ($submission) {
                $count = $submission->criteriaScores->where('decision', $decision)->count();

                return $count.' '.$label;
            })
            ->implode(' / ');
    @endphp

    <main class="nxl-container evaluation-submission-report">
        <div class="page-header">
            <div class="page-header-left">
                <h4 class="fw-bold mb-1">
                    <i class="feather-clipboard me-2"></i>
                    Evaluation Submission Report
                </h4>
                <p class="mb-0">{{ $applicantName }} | {{ $submissionCode }}</p>
            </div>
            <div class="report-actions">
                <a href="{{ route('reports.evaluations.index') }}" class="btn btn-light btn-sm">
                    <i class="feather-arrow-left me-1"></i> Reports
                </a>
                <a href="{{ route('reports.evaluations.submission.pdf', $submission) }}" class="btn btn-success btn-sm">
                    <i class="feather-download me-1"></i> Download PDF
                </a>
                <a href="{{ route('reports.evaluations.submission.anonymised-pdf', $submission) }}" class="btn btn-dark btn-sm">
                    <i class="feather-shield me-1"></i> Anonymised PDF
                </a>
            </div>
        </div>

        <section class="report-overview">
            <div class="overview-main">
                <span class="report-eyebrow">Applicant</span>
                <h5>{{ $applicantName }}</h5>
                <div class="overview-meta">
                    <span>{{ $submissionCode }}</span>
                    <span>{{ $submission->procurement?->reference_no ?? 'No reference' }}</span>
                    <span>{{ $evaluation?->typeLabel() ?? 'Evaluation' }}</span>
                </div>
            </div>
            <div class="overview-score">
                <span>{{ $isNumeric ? 'Overall Score' : 'Decision Summary' }}</span>
                @if ($isNumeric)
                    <strong>
                        {{ $score !== null ? number_format($score, 2) : '-' }}
                        @if ($overallMax)
                            <small>/ {{ number_format($overallMax, 2) }}</small>
                        @endif
                    </strong>
                    @if ($scorePercent !== null)
                        <div class="score-track">
                            <div style="width: {{ $scorePercent }}%"></div>
                        </div>
                    @endif
                @else
                    <strong>{{ $decisionSummary ?: 'No decisions recorded' }}</strong>
                @endif
            </div>
        </section>

        <div class="report-kpi-grid">
            <div class="report-kpi report-kpi--procurement">
                <span>Procurement</span>
                <strong>{{ $submission->procurement?->title ?? 'N/A' }}</strong>
            </div>
            <div class="report-kpi report-kpi--evaluation">
                <span>Evaluation</span>
                <strong>{{ $submission->evaluation?->name ?? 'N/A' }}</strong>
            </div>
            <div class="report-kpi report-kpi--evaluator">
                <span>Evaluator</span>
                <strong>{{ $submission->evaluator?->name ?? 'N/A' }}</strong>
            </div>
            <div class="report-kpi report-kpi--submitted">
                <span>Submitted</span>
                <strong>{{ optional($submission->submitted_at)->format('d M Y, H:i') ?? 'N/A' }}</strong>
            </div>
        </div>

        <div class="report-summary-strip">
            <div>
                <span>Sections</span>
                <strong>{{ $submission->evaluation?->sections?->count() ?? 0 }}</strong>
            </div>
            <div>
                <span>Criteria</span>
                <strong>{{ $criteriaTotal }}</strong>
            </div>
            <div>
                <span>Submission Code</span>
                <strong>{{ $submissionCode }}</strong>
            </div>
            <div>
                <span>Applicant Email</span>
                <strong>{{ $submission->applicant?->submitter?->email ?? 'N/A' }}</strong>
            </div>
        </div>

        <section class="section-stack">
            @forelse ($sectionOutline as $node)
                @php
                    $section = $node['section'];
                    $sectionScore = $submission->sectionScores->firstWhere('evaluation_section_id', $section->id);
                    $sectionTotal = $isNumeric
                        ? \App\Support\EvaluationSectionHierarchy::numericSubtotal($submission, $section)
                        : null;
                    $sectionMax = $isNumeric ? $section->subtotalMaxScore() : null;
                    $sectionPercent = ($isNumeric && $sectionMax > 0) ? min(100, round(($sectionTotal / $sectionMax) * 100, 1)) : null;
                    $sectionDistribution = $isNumeric
                        ? []
                        : \App\Support\EvaluationSectionHierarchy::decisionDistribution($submission, $section);
                @endphp

                <article class="evaluation-section-card" style="margin-left: {{ min($node['depth'] * 18, 54) }}px">
                    <div class="section-card-head">
                        <div>
                            <span class="report-eyebrow">{{ $node['label'] }} {{ $node['number'] }}</span>
                            <h5>{{ $node['number'] }}. {{ $section->name }}</h5>
                        </div>
                        @if ($section->show_subtotal && $isNumeric)
                            <div class="section-score">
                                <small>Sub-total</small>
                                <span>{{ number_format($sectionTotal, 2) }}</span>
                                <small>/ {{ number_format($sectionMax, 2) }}</small>
                            </div>
                        @elseif ($section->show_subtotal)
                            <div class="d-flex flex-wrap gap-1 justify-content-end">
                                @foreach ($sectionDistribution as $decision => $count)
                                    <span class="decision-pill">{{ $decision }}: {{ $count }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if ($section->show_subtotal && $isNumeric && $sectionPercent !== null)
                        <div class="section-progress">
                            <div style="width: {{ $sectionPercent }}%"></div>
                        </div>
                    @endif

                    <div class="criteria-table-wrap">
                        @if ($section->criteria->isEmpty())
                            <div class="p-3 text-muted">Grouping section; criteria are organised in its child sections.</div>
                        @elseif ($isNumeric)
                            <table class="table criteria-table">
                                <thead>
                                    <tr>
                                        <th>Criteria</th>
                                        <th class="text-end">Max</th>
                                        <th class="text-end">Score</th>
                                        <th>Evaluator response</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($section->criteria as $criteria)
                                        @php
                                            $criteriaScore = $submission->criteriaScores->firstWhere('evaluation_criteria_id', $criteria->id);
                                        @endphp
                                        <tr>
                                            <td>{{ $criteria->name }}</td>
                                            <td class="text-end">{{ number_format($criteria->max_score ?? 0, 2) }}</td>
                                            <td class="text-end fw-semibold">{{ number_format($criteriaScore->score ?? 0, 2) }}</td>
                                            <td>{{ $criteriaScore->comment ?? 'N/A' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <table class="table criteria-table">
                                <thead>
                                    <tr>
                                        <th>Criteria</th>
                                        <th>Decision</th>
                                        <th>Comment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($section->criteria as $criteria)
                                        @php
                                            $criteriaScore = $submission->criteriaScores->firstWhere('evaluation_criteria_id', $criteria->id);
                                            $decisionLabel = $evaluation?->decisionLabel($criteriaScore?->decision);
                                            $decisionClass = match ($decisionLabel) {
                                                'Yes', 'Qualified' => 'decision-pill--yes',
                                                'Average Qualified' => 'decision-pill--average',
                                                'No', 'Not Qualified' => 'decision-pill--no',
                                                default => '',
                                            };
                                        @endphp
                                        <tr>
                                            <td>{{ $criteria->name }}</td>
                                            <td>
                                                @if ($decisionLabel)
                                                    <span class="decision-pill {{ $decisionClass }}">{{ $decisionLabel }}</span>
                                                @else
                                                    <span class="decision-pill">N/A</span>
                                                @endif
                                            </td>
                                            <td>{{ $criteriaScore->comment ?? 'N/A' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>

                    @if ($section->criteria->isNotEmpty())
                        <div class="section-comments">
                            <div>
                                <span>Strengths</span>
                                <p>{{ $sectionScore->strengths ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <span>Weaknesses</span>
                                <p>{{ $sectionScore->weaknesses ?? 'N/A' }}</p>
                            </div>
                        </div>
                    @endif
                </article>
            @empty
                <div class="empty-report">
                    <strong>No evaluation sections were found for this submission.</strong>
                </div>
            @endforelse
        </section>

        @if ($submission->comments)
            <section class="final-comments">
                <span class="report-eyebrow">Evaluator Comments</span>
                <p>{{ $submission->comments }}</p>
            </section>
        @endif

        <div class="bottom-actions">
            <a href="{{ route('reports.evaluations.submission.pdf', $submission) }}" class="btn btn-success">
                <i class="feather-download me-1"></i> Download PDF
            </a>
            <a href="{{ route('reports.evaluations.submission.anonymised-pdf', $submission) }}" class="btn btn-dark">
                <i class="feather-shield me-1"></i> Anonymised PDF
            </a>
        </div>
    </main>
@endsection

@push('styles')
    <style>
        .evaluation-submission-report {
            --ink: #172033;
            --muted: #667085;
            --line: #d9e2ec;
            --soft: #f6f8fb;
            --green: #0f766e;
            --blue: #2563eb;
            --orange: #b45309;
            --red: #b42318;
        }

        .report-actions,
        .bottom-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .report-overview {
            align-items: stretch;
            background: #fff;
            border: 1px solid var(--line);
            border-left: 4px solid var(--green);
            border-radius: 8px;
            box-shadow: 0 14px 28px rgba(15, 23, 42, .05);
            display: grid;
            gap: 18px;
            grid-template-columns: 1fr 280px;
            margin-bottom: 16px;
            padding: 18px;
        }

        .report-eyebrow,
        .report-kpi span,
        .report-summary-strip span,
        .overview-score span,
        .section-comments span {
            color: var(--muted);
            display: block;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .overview-main h5,
        .evaluation-section-card h5 {
            color: var(--ink);
            font-size: 18px;
            font-weight: 800;
            line-height: 1.3;
            margin: 6px 0 0;
        }

        .overview-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }

        .overview-meta span {
            background: #eef2f6;
            border-radius: 999px;
            color: #344054;
            font-size: 12px;
            font-weight: 700;
            padding: 5px 9px;
        }

        .overview-score {
            background: var(--soft);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 15px;
        }

        .overview-score strong {
            color: var(--ink);
            display: block;
            font-size: 28px;
            line-height: 1.1;
            margin-top: 8px;
        }

        .overview-score small {
            color: var(--muted);
            font-size: 15px;
        }

        .score-track,
        .section-progress {
            background: #e5e7eb;
            border-radius: 999px;
            height: 8px;
            margin-top: 13px;
            overflow: hidden;
        }

        .score-track div,
        .section-progress div {
            background: linear-gradient(90deg, var(--green), var(--blue));
            height: 100%;
        }

        .report-kpi-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            margin-bottom: 14px;
        }

        .report-kpi {
            background: #fff;
            border: 1px solid var(--line);
            border-left: 4px solid var(--ink);
            border-radius: 8px;
            box-shadow: 0 10px 22px rgba(15, 23, 42, .04);
            padding: 14px 15px;
        }

        .report-kpi strong {
            color: var(--ink);
            display: block;
            font-size: 14px;
            line-height: 1.35;
            margin-top: 7px;
        }

        .report-kpi--procurement { border-left-color: var(--green); }
        .report-kpi--evaluation { border-left-color: var(--blue); }
        .report-kpi--evaluator { border-left-color: var(--orange); }
        .report-kpi--submitted { border-left-color: var(--red); }

        .report-summary-strip {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 8px;
            display: grid;
            gap: 0;
            grid-template-columns: repeat(4, 1fr);
            margin-bottom: 16px;
            overflow: hidden;
        }

        .report-summary-strip div {
            border-right: 1px solid var(--line);
            padding: 13px 15px;
        }

        .report-summary-strip div:last-child {
            border-right: 0;
        }

        .report-summary-strip strong {
            color: var(--ink);
            display: block;
            font-size: 14px;
            line-height: 1.35;
            margin-top: 6px;
            word-break: break-word;
        }

        .section-stack {
            display: grid;
            gap: 16px;
        }

        .evaluation-section-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: 0 14px 28px rgba(15, 23, 42, .04);
            overflow: hidden;
        }

        .section-card-head {
            align-items: center;
            background: #f8fafc;
            border-bottom: 1px solid var(--line);
            display: flex;
            gap: 14px;
            justify-content: space-between;
            padding: 16px 18px;
        }

        .section-score {
            color: var(--ink);
            font-weight: 800;
            text-align: right;
        }

        .section-score span {
            font-size: 20px;
        }

        .section-score small {
            color: var(--muted);
            font-size: 12px;
        }

        .section-progress {
            border-radius: 0;
            height: 5px;
            margin: 0;
        }

        .criteria-table-wrap {
            overflow-x: auto;
            padding: 16px 18px 4px;
        }

        .criteria-table {
            margin-bottom: 0;
        }

        .criteria-table th {
            color: var(--muted);
            font-size: 11px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .criteria-table td {
            color: #344054;
            vertical-align: top;
        }

        .decision-pill {
            background: #eef2f6;
            border-radius: 999px;
            color: #344054;
            display: inline-block;
            font-size: 11px;
            font-weight: 800;
            padding: 4px 8px;
            text-transform: uppercase;
        }

        .decision-pill--yes {
            background: #dcfce7;
            color: #166534;
        }

        .decision-pill--no {
            background: #fee2e2;
            color: #991b1b;
        }

        .decision-pill--average {
            background: #fef3c7;
            color: #92400e;
        }

        .section-comments {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            padding: 16px 18px 18px;
        }

        .section-comments div,
        .final-comments {
            background: var(--soft);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 13px 14px;
        }

        .section-comments p,
        .final-comments p {
            color: #344054;
            line-height: 1.55;
            margin: 7px 0 0;
            white-space: pre-line;
        }

        .final-comments {
            background: #fff;
            margin-top: 16px;
        }

        .bottom-actions {
            justify-content: flex-end;
            margin-top: 18px;
        }

        .empty-report {
            background: #fff;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            color: var(--muted);
            padding: 24px;
            text-align: center;
        }

        @media (max-width: 992px) {
            .report-overview,
            .report-summary-strip {
                grid-template-columns: 1fr;
            }

            .report-summary-strip div {
                border-bottom: 1px solid var(--line);
                border-right: 0;
            }

            .report-summary-strip div:last-child {
                border-bottom: 0;
            }
        }

        @media (max-width: 720px) {
            .section-card-head,
            .section-comments {
                display: block;
            }

            .section-comments div + div {
                margin-top: 12px;
            }

            .report-actions,
            .bottom-actions {
                width: 100%;
            }

            .report-actions .btn,
            .bottom-actions .btn {
                flex: 1 1 100%;
            }
        }
    </style>
@endpush
