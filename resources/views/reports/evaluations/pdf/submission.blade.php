<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ ($anonymised ?? false) ? 'Anonymised ' : '' }}Evaluation Submission Report</title>
    <style>
        @page {
            margin: 112px 34px 76px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            color: #1f2937;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10.5px;
            line-height: 1.48;
        }

        h1,
        h2,
        h3,
        p {
            margin: 0;
        }

        .pdf-header {
            background: #0f172a;
            border-bottom: 4px solid #16a34a;
            color: #ffffff;
            height: 88px;
            left: 0;
            padding: 18px 34px 14px;
            position: fixed;
            right: 0;
            top: -112px;
        }

        .pdf-footer {
            background: #f8fafc;
            border-top: 3px solid #16a34a;
            bottom: -60px;
            color: #334155;
            font-size: 9px;
            left: 0;
            position: fixed;
            right: 0;
        }

        .footer-inner {
            border-top: 1px solid #cbd5e1;
            padding: 9px 34px 0;
        }

        .page-number:after {
            content: counter(page) " / " counter(pages);
        }

        .header-table,
        .footer-table,
        .summary-table,
        .score-table,
        .criteria-table,
        .comment-table {
            border-collapse: collapse;
            width: 100%;
        }

        .header-logo {
            width: 160px;
        }

        .header-logo img {
            display: block;
            max-height: 42px;
            max-width: 150px;
        }

        .fallback-brand {
            color: #ffffff;
            font-size: 14px;
            font-weight: 800;
        }

        .header-title {
            text-align: right;
        }

        .header-title h1 {
            color: #ffffff;
            font-size: 19px;
            font-weight: 800;
            line-height: 1.15;
        }

        .header-title p {
            color: #cbd5e1;
            font-size: 10px;
            margin-top: 5px;
        }

        .mode-pill {
            background: {{ ($anonymised ?? false) ? '#f97316' : '#2563eb' }};
            border-radius: 999px;
            color: #ffffff;
            display: inline-block;
            font-size: 8.5px;
            font-weight: 800;
            letter-spacing: .05em;
            margin-bottom: 7px;
            padding: 4px 9px;
            text-transform: uppercase;
        }

        .footer-table td {
            vertical-align: top;
            width: 33.333%;
        }

        .footer-brand {
            color: #0f172a;
            font-weight: 800;
        }

        .footer-url {
            color: #475569;
            text-align: center;
        }

        .footer-page {
            color: #0f172a;
            font-weight: 800;
            text-align: right;
        }

        .cover {
            background: #f8fafc;
            border: 1px solid #dbe3eb;
            border-left: 5px solid #16a34a;
            margin-bottom: 13px;
            padding: 14px 16px;
        }

        .cover h2 {
            color: #0f172a;
            font-size: 17px;
            line-height: 1.22;
            margin-bottom: 8px;
        }

        .muted {
            color: #64748b;
        }

        .label {
            color: #64748b;
            display: block;
            font-size: 8.2px;
            font-weight: 800;
            letter-spacing: .04em;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .value {
            color: #111827;
            font-size: 10.8px;
            font-weight: 700;
        }

        .summary-table {
            margin-bottom: 12px;
        }

        .summary-table td,
        .score-table td {
            border: 1px solid #dbe3eb;
            padding: 8px 9px;
            vertical-align: top;
        }

        .summary-table td {
            width: 25%;
        }

        .soft {
            background: #f8fafc;
        }

        .score-table {
            margin-bottom: 14px;
        }

        .score-number {
            color: #0f172a;
            font-size: 20px;
            font-weight: 800;
        }

        .badge {
            border-radius: 999px;
            display: inline-block;
            font-size: 8.5px;
            font-weight: 800;
            letter-spacing: .04em;
            padding: 4px 8px;
            text-transform: uppercase;
        }

        .badge-blue {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .badge-green {
            background: #dcfce7;
            color: #166534;
        }

        .badge-red {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-amber {
            background: #fef3c7;
            color: #92400e;
        }

        .section {
            border: 1px solid #dbe3eb;
            margin-bottom: 12px;
            page-break-inside: avoid;
        }

        .section-head {
            background: #f8fafc;
            border-bottom: 1px solid #dbe3eb;
            padding: 9px 10px;
        }

        .section-head h3 {
            color: #111827;
            font-size: 12.5px;
            line-height: 1.25;
        }

        .section-body {
            padding: 10px;
        }

        .criteria-table {
            margin-bottom: 10px;
        }

        .criteria-table th,
        .criteria-table td,
        .comment-table th,
        .comment-table td {
            border: 1px solid #dbe3eb;
            padding: 6px 7px;
            vertical-align: top;
        }

        .criteria-table th,
        .comment-table th {
            background: #f8fafc;
            color: #475569;
            font-size: 8.5px;
            text-align: left;
            text-transform: uppercase;
        }

        .text-right {
            text-align: right;
        }

        .comments {
            white-space: pre-line;
        }

        .final-comments {
            background: #f8fafc;
            border: 1px solid #dbe3eb;
            margin-top: 12px;
            padding: 10px;
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    @php
        $anonymised = $anonymised ?? false;
        $platformName = $platformName ?? 'Africa Think Tank Platform';
        $platformUrl = $platformUrl ?? rtrim(config('app.url') ?: url('/'), '/');
        $evaluation = $submission->evaluation;
        $isNumeric = $evaluation?->usesNumericScoring() ?? false;
        $sectionOutline = $evaluation
            ? \App\Support\EvaluationSectionHierarchy::flattened($evaluation)
            : collect();
        $rawApplicantName = $submission->applicant?->display_name ?? 'Applicant';
        $rawApplicantEmail = $submission->applicant?->submitter?->email;
        $rawSubmissionCode = $submission->applicant?->procurement_submission_code ?? 'N/A';
        $applicantName = $anonymised ? 'Applicant XXX' : $rawApplicantName;
        $applicantEmail = $anonymised ? 'Redacted' : ($rawApplicantEmail ?? 'N/A');
        $submissionCode = $anonymised ? 'ANONYMISED' : $rawSubmissionCode;
        $evaluatorName = $submission->evaluator?->name ?? null;
        $displayEvaluatorName = $evaluatorName ?? 'N/A';
        $score = $submission->overall_score !== null ? (float) $submission->overall_score : null;
        $decisionSummary = collect($evaluation?->decisionOptions() ?? [])
            ->map(function (string $label, int $decision) use ($submission) {
                $count = $submission->criteriaScores->where('decision', $decision)->count();

                return $count.' '.$label;
            })
            ->implode(' / ');
        $modeLabel = $anonymised ? 'Anonymised Applicant' : 'Internal Report';
        $applicantIdentifiers = collect([
            $rawApplicantName !== 'Applicant' ? $rawApplicantName : null,
            $rawApplicantEmail,
            $rawSubmissionCode !== 'N/A' ? $rawSubmissionCode : null,
        ])->filter()->unique()->values();
        $redact = function ($text) use ($anonymised, $applicantIdentifiers) {
            if (! $anonymised || blank($text)) {
                return $text;
            }

            $redacted = (string) $text;

            foreach ($applicantIdentifiers as $identifier) {
                $redacted = str_ireplace((string) $identifier, 'XXX', $redacted);
            }

            return $redacted;
        };
    @endphp

    <div class="pdf-header">
        <table class="header-table">
            <tr>
                <td class="header-logo">
                    @if (! empty($logoDataUri))
                        <img src="{{ $logoDataUri }}" alt="{{ $platformName }}">
                    @else
                        <span class="fallback-brand">{{ $platformName }}</span>
                    @endif
                </td>
                <td class="header-title">
                    <span class="mode-pill">{{ $modeLabel }}</span>
                    <h1>Evaluation Submission Report</h1>
                    <p>{{ $submissionCode }} | {{ $submission->procurement?->title ?? 'N/A' }}</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="pdf-footer">
        <div class="footer-inner">
            <table class="footer-table">
                <tr>
                    <td class="footer-brand">{{ $platformName }}</td>
                    <td class="footer-url">{{ $platformUrl }}</td>
                    <td class="footer-page">Page <span class="page-number"></span></td>
                </tr>
            </table>
        </div>
    </div>

    <div class="cover">
        <h2>{{ $applicantName }}</h2>
        <p class="muted">{{ $submissionCode }} | {{ $submission->evaluation?->name ?? 'Evaluation' }}</p>
    </div>

    <table class="summary-table">
        <tr>
            <td>
                <span class="label">Procurement</span>
                <span class="value">{{ $submission->procurement?->title ?? 'N/A' }}</span>
            </td>
            <td class="soft">
                <span class="label">Reference</span>
                <span class="value">{{ $submission->procurement?->reference_no ?? 'N/A' }}</span>
            </td>
            <td>
                <span class="label">Evaluation Type</span>
                <span class="badge badge-blue">{{ $evaluation?->typeLabel() ?? 'N/A' }}</span>
            </td>
            <td class="soft">
                <span class="label">Submitted</span>
                <span class="value">{{ optional($submission->submitted_at)->format('d M Y, H:i') ?? 'N/A' }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="label">Applicant</span>
                <span class="value">{{ $applicantName }}</span>
            </td>
            <td class="soft">
                <span class="label">Applicant Email</span>
                <span class="value">{{ $applicantEmail }}</span>
            </td>
            <td>
                <span class="label">Evaluator</span>
                <span class="value">{{ $displayEvaluatorName }}</span>
            </td>
            <td class="soft">
                <span class="label">Submission Code</span>
                <span class="value">{{ $submissionCode }}</span>
            </td>
        </tr>
    </table>

    <table class="score-table">
        <tr>
            <td>
                <span class="label">{{ $isNumeric ? 'Overall Score' : 'Decision Summary' }}</span>
                @if ($isNumeric)
                    <span class="score-number">
                        {{ $score !== null ? number_format($score, 2) : '-' }}
                        @if ($overallMax)
                            / {{ number_format($overallMax, 2) }}
                        @endif
                    </span>
                @else
                    <span class="score-number">{{ $decisionSummary ?: 'No decisions recorded' }}</span>
                @endif
            </td>
            <td class="soft">
                <span class="label">Sections</span>
                <span class="value">{{ $submission->evaluation?->sections?->count() ?? 0 }}</span>
            </td>
            <td>
                <span class="label">Criteria</span>
                <span class="value">{{ $submission->evaluation?->sections?->sum(fn ($section) => $section->criteria->count()) ?? 0 }}</span>
            </td>
        </tr>
    </table>

    @foreach ($sectionOutline as $node)
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

        <div class="section" style="margin-left: {{ min($node['depth'] * 12, 36) }}px;">
            <div class="section-head">
                <h3>{{ $node['label'] }} {{ $node['number'] }}: {{ $section->name }}</h3>
            </div>
            <div class="section-body">
                @if ($section->criteria->isEmpty())
                    <p class="muted">Grouping section; criteria are organised in its child sections.</p>
                    @if ($section->show_subtotal && $isNumeric)
                        <p><strong>Sub-total (including child sections):</strong>
                            {{ number_format($sectionTotal, 2) }} / {{ number_format($sectionMax, 2) }}</p>
                    @elseif ($section->show_subtotal)
                        <p><strong>Category distribution:</strong>
                            @foreach ($sectionDistribution as $decision => $count)
                                {{ $decision }}: {{ $count }}@if (! $loop->last) &middot; @endif
                            @endforeach
                        </p>
                    @endif
                @elseif ($isNumeric)
                    <table class="criteria-table">
                        <thead>
                            <tr>
                                <th>Criteria</th>
                                <th class="text-right">Max</th>
                                <th class="text-right">Score</th>
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
                                    <td class="text-right">{{ number_format($criteria->max_score ?? 0, 2) }}</td>
                                    <td class="text-right">{{ number_format($criteriaScore->score ?? 0, 2) }}</td>
                                    <td class="comments">{{ $redact($criteriaScore->comment ?? 'N/A') }}</td>
                                </tr>
                            @endforeach
                            @if ($section->show_subtotal)
                                <tr>
                                    <th>Sub-total (including child sections)</th>
                                    <th class="text-right">{{ number_format($sectionMax, 2) }}</th>
                                    <th class="text-right">{{ number_format($sectionTotal, 2) }}</th>
                                    <th></th>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                @else
                    <table class="criteria-table">
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
                                        'Yes', 'Qualified' => 'badge-green',
                                        'Average Qualified' => 'badge-amber',
                                        'No', 'Not Qualified' => 'badge-red',
                                        default => 'badge-blue',
                                    };
                                @endphp
                                <tr>
                                    <td>{{ $criteria->name }}</td>
                                    <td>
                                        @if ($decisionLabel)
                                            <span class="badge {{ $decisionClass }}">{{ $decisionLabel }}</span>
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td class="comments">{{ $redact($criteriaScore->comment ?? 'N/A') }}</td>
                                </tr>
                            @endforeach
                            @if ($section->show_subtotal)
                                <tr>
                                    <th>Category distribution (including child sections)</th>
                                    <th colspan="2">
                                        @foreach ($sectionDistribution as $decision => $count)
                                            {{ $decision }}: {{ $count }}@if (! $loop->last) &middot; @endif
                                        @endforeach
                                    </th>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                @endif

                @if ($section->criteria->isNotEmpty())
                    <table class="comment-table">
                        <tr>
                            <th>Strengths</th>
                            <td class="comments">{{ $redact($sectionScore->strengths ?? 'N/A') }}</td>
                        </tr>
                        <tr>
                            <th>Weaknesses</th>
                            <td class="comments">{{ $redact($sectionScore->weaknesses ?? 'N/A') }}</td>
                        </tr>
                    </table>
                @endif
            </div>
        </div>
    @endforeach

    @if ($submission->comments)
        <div class="final-comments">
            <span class="label">Evaluator Comments</span>
            <p class="comments">{{ $redact($submission->comments) }}</p>
        </div>
    @endif
</body>
</html>
