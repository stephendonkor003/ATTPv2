<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Procurement Evaluation Report</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #111;
        }

        h1,
        h2,
        h3,
        h4 {
            margin: 0 0 6px 0;
        }

        .cover {
            text-align: center;
            margin-bottom: 40px;
        }

        .cover h1 {
            font-size: 22px;
            margin-bottom: 10px;
        }

        .cover p {
            font-size: 13px;
        }

        .submission {
            page-break-after: always;
        }

        .header {
            border-bottom: 2px solid #000;
            margin-bottom: 12px;
            padding-bottom: 8px;
        }

        .meta {
            font-size: 11px;
            margin-bottom: 10px;
        }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
        }

        .yes {
            background: #16a34a;
            color: #fff;
        }

        .no {
            background: #dc2626;
            color: #fff;
        }

        .average {
            background: #f59e0b;
            color: #111;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 6px;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
            text-align: left;
        }

        .section {
            margin-bottom: 16px;
        }

        .notes {
            font-size: 11px;
            margin-top: 6px;
        }

        .overall {
            font-size: 14px;
            font-weight: bold;
            text-align: right;
            margin-top: 8px;
        }

        .evaluator-block {
            border: 1px solid #bbb;
            padding: 10px;
            margin-bottom: 14px;
        }

        .evaluator-header {
            font-weight: bold;
            margin-bottom: 6px;
        }

        .small {
            font-size: 10px;
            color: #444;
        }
    </style>
</head>

<body>

    {{-- ================= COVER PAGE ================= --}}
    <div class="cover">
        <h1>{{ $procurement->title }}</h1>
        <p>
            Procurement Evaluation Report<br>
            Generated on {{ now()->format('d M Y, H:i') }}
        </p>
    </div>

    {{-- ================= SUBMISSIONS ================= --}}
    @foreach ($submissions as $submission)
        @php
            $evaluation = $submission->evaluation;
            $isNumeric = $evaluation->usesNumericScoring();
            $sectionOutline = \App\Support\EvaluationSectionHierarchy::flattened($evaluation);
        @endphp

        <div class="submission">

            {{-- HEADER --}}
            <div class="header">
                <h2>Applicant: {{ optional($submission->applicant->submitter)->name ?? '—' }}</h2>

                <div class="meta">
                    <strong>Submission Code:</strong>
                    {{ $submission->applicant->procurement_submission_code }} <br>

                    <strong>Evaluation:</strong>
                    {{ $evaluation->name }} <br>

                    <strong>Type:</strong>
                    {{ $evaluation->typeLabel() }}
                </div>
            </div>

            {{-- ================= EVALUATORS ================= --}}
            @foreach ($submission->groupedEvaluators as $eval)
                <div class="evaluator-block">

                    <div class="evaluator-header">
                        Evaluator: {{ $eval->evaluator->name }}
                    </div>

                    <div class="small">
                        Submitted: {{ $eval->submitted_at->format('d M Y, H:i') }}
                    </div>

                    {{-- ================= SECTIONS ================= --}}
                    @foreach ($sectionOutline as $node)
                        @php
                            $section = $node['section'];
                            $sectionScore = $eval->sectionScores->firstWhere('evaluation_section_id', $section->id);
                            $sectionSubtotal = $isNumeric
                                ? \App\Support\EvaluationSectionHierarchy::numericSubtotal($eval, $section)
                                : null;
                            $sectionDistribution = $isNumeric
                                ? []
                                : \App\Support\EvaluationSectionHierarchy::decisionDistribution($eval, $section);
                        @endphp

                        <div class="section" style="margin-left: {{ min($node['depth'] * 12, 36) }}px;">
                            <h4>{{ $node['number'] }}. {{ $section->name }} <small>({{ $node['label'] }})</small></h4>

                            @if ($section->show_subtotal && $isNumeric)
                                <p><strong>Sub-total:</strong> {{ number_format($sectionSubtotal, 2) }} / {{ number_format($section->subtotalMaxScore(), 2) }}</p>
                            @elseif ($section->show_subtotal)
                                <p><strong>Category distribution:</strong>
                                    @foreach ($sectionDistribution as $decision => $count)
                                        {{ $decision }}: {{ $count }}@if (! $loop->last) &middot; @endif
                                    @endforeach
                                </p>
                            @endif

                            {{-- SERVICES --}}
                            @if ($section->criteria->isEmpty())
                                <p>Grouping section; criteria appear in child sections.</p>
                            @elseif ($isNumeric)
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Criteria</th>
                                            <th width="80">Score</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($section->criteria as $criteria)
                                            @php
                                                $cs = $eval->criteriaScores->firstWhere(
                                                    'evaluation_criteria_id',
                                                    $criteria->id,
                                                );
                                            @endphp
                                            <tr>
                                                <td>{{ $criteria->name }}</td>
                                                <td align="center">
                                                    {{ number_format($cs->score ?? 0, 2) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>

                                {{-- CATEGORICAL DECISIONS --}}
                            @else
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Criteria</th>
                                            <th width="80">Decision</th>
                                            <th>Comment</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($section->criteria as $criteria)
                                            @php
                                                $cs = $eval->criteriaScores->firstWhere(
                                                    'evaluation_criteria_id',
                                                    $criteria->id,
                                                );
                                                $decisionLabel = $evaluation->decisionLabel($cs?->decision);
                                                $decisionClass = match ($decisionLabel) {
                                                    'Yes', 'Qualified' => 'yes',
                                                    'Average Qualified' => 'average',
                                                    'No', 'Not Qualified' => 'no',
                                                    default => '',
                                                };
                                            @endphp
                                            <tr>
                                                <td>{{ $criteria->name }}</td>
                                                <td align="center">
                                                    @if ($decisionLabel)
                                                        <span class="badge {{ $decisionClass }}">{{ $decisionLabel }}</span>
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td>{{ $cs->comment ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif

                            @if ($section->criteria->isNotEmpty())
                                {{-- Notes are only collected for directly assessable sections. --}}
                                <div class="notes">
                                    <strong>Strengths:</strong><br>
                                    {{ $sectionScore->strengths ?? '—' }} <br><br>

                                    <strong>Weaknesses:</strong><br>
                                    {{ $sectionScore->weaknesses ?? '—' }}
                                </div>
                            @endif
                        </div>
                    @endforeach

                    {{-- OVERALL --}}
                    @if ($isNumeric)
                        <div class="overall">
                            Overall Score:
                            {{ number_format($eval->overall_score, 2) }}
                        </div>
                    @endif

                </div>
            @endforeach

        </div>
    @endforeach

</body>

</html>
