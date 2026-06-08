<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ ($anonymised ?? false) ? 'Anonymised ' : '' }}Prescreening Submission Report</title>
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
        .values-table {
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

        .summary-table,
        .score-table {
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

        .status-pill,
        .result-pill {
            border-radius: 999px;
            display: inline-block;
            font-size: 8.5px;
            font-weight: 800;
            padding: 4px 8px;
            text-transform: uppercase;
        }

        .status-pill {
            background: #eef2f6;
            color: #334155;
        }

        .status-pill--prescreen_passed,
        .result-pill--passed {
            background: #dcfce7;
            color: #166534;
        }

        .status-pill--prescreen_failed,
        .result-pill--failed {
            background: #fee2e2;
            color: #991b1b;
        }

        .section-title {
            border-bottom: 1px solid #cbd5e1;
            color: #0f172a;
            font-size: 13px;
            font-weight: 800;
            margin: 16px 0 9px;
            padding-bottom: 6px;
        }

        .criteria-table,
        .values-table {
            margin-bottom: 12px;
        }

        .criteria-table th,
        .criteria-table td,
        .values-table th,
        .values-table td {
            border: 1px solid #dbe3eb;
            padding: 6px 7px;
            vertical-align: top;
        }

        .criteria-table th,
        .values-table th {
            background: #f8fafc;
            color: #475569;
            font-size: 8.5px;
            text-align: left;
            text-transform: uppercase;
        }

        .comments {
            white-space: pre-line;
        }
    </style>
</head>
<body>
    @php
        $anonymised = $anonymised ?? false;
        $platformName = $platformName ?? 'Africa Think Tank Platform';
        $platformUrl = $platformUrl ?? rtrim(config('app.url') ?: url('/'), '/');
        $criteria = isset($criteria) ? collect($criteria) : collect();
        if ($criteria->isEmpty()) {
            $sectionCriteria = collect($sections ?? [])->flatMap(fn ($section) => $section->criteria)->values();
            $criteria = $sectionCriteria->isNotEmpty()
                ? $sectionCriteria
                : collect($template?->criteria ?? [])->values();
        }
        $applicantName = $submission->display_name ?? $submission->submitter?->name ?? 'Applicant';
        $submissionCode = $submission->procurement_submission_code ?? 'N/A';
        $evaluatorName = $submission->prescreeningResult?->evaluator?->name;
        $displayEvaluatorName = $anonymised ? 'XXX' : ($evaluatorName ?? 'N/A');
        $modeLabel = $anonymised ? 'Anonymised Applicant' : 'Internal Report';
        $status = $submission->status ?? 'pending';
        $redact = function ($text) use ($anonymised, $evaluatorName) {
            if (! $anonymised || blank($text)) {
                return $text;
            }

            return $evaluatorName ? str_replace($evaluatorName, 'XXX', (string) $text) : $text;
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
                    <h1>Prescreening Submission Report</h1>
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
        <p class="muted">{{ $submissionCode }} | {{ $template->name ?? 'Prescreening' }}</p>
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
                <span class="label">Final Status</span>
                <span class="status-pill status-pill--{{ $status }}">
                    {{ \Illuminate\Support\Str::headline($status) }}
                </span>
            </td>
            <td class="soft">
                <span class="label">Evaluated</span>
                <span class="value">{{ optional($submission->prescreeningResult?->evaluated_at)->format('d M Y, H:i') ?? 'N/A' }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="label">Applicant</span>
                <span class="value">{{ $applicantName }}</span>
            </td>
            <td class="soft">
                <span class="label">Applicant Email</span>
                <span class="value">{{ $submission->submitter?->email ?? 'N/A' }}</span>
            </td>
            <td>
                <span class="label">Evaluator</span>
                <span class="value">{{ $displayEvaluatorName }}</span>
            </td>
            <td class="soft">
                <span class="label">Submitted</span>
                <span class="value">{{ optional($submission->submitted_at)->format('d M Y, H:i') ?? 'N/A' }}</span>
            </td>
        </tr>
    </table>

    <table class="score-table">
        <tr>
            <td>
                <span class="label">Total Criteria</span>
                <span class="value">{{ $submission->prescreeningResult?->total_criteria ?? $criteria->count() }}</span>
            </td>
            <td class="soft">
                <span class="label">Passed</span>
                <span class="value">{{ $submission->prescreeningResult?->passed_criteria ?? 'N/A' }}</span>
            </td>
            <td>
                <span class="label">Failed</span>
                <span class="value">{{ $submission->prescreeningResult?->failed_criteria ?? 'N/A' }}</span>
            </td>
        </tr>
    </table>

    <h2 class="section-title">Criteria Evaluation</h2>
    <table class="criteria-table">
        <thead>
            <tr>
                <th>Criterion</th>
                <th>Result</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($criteria as $criterion)
                @php $evaluation = $evaluations[$criterion->id] ?? null; @endphp
                <tr>
                    <td>{{ $criterion->name }}</td>
                    <td>
                        @if ($evaluation)
                            <span class="result-pill {{ $evaluation->is_passed ? 'result-pill--passed' : 'result-pill--failed' }}">
                                {{ $evaluation->is_passed ? 'Passed' : 'Failed' }}
                            </span>
                        @else
                            N/A
                        @endif
                    </td>
                    <td class="comments">{{ $redact($evaluation->remarks ?? 'N/A') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">No criteria available.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2 class="section-title">Applicant Submission Values</h2>
    <table class="values-table">
        <thead>
            <tr>
                <th>Field</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($submission->values as $value)
                <tr>
                    <td>{{ \Illuminate\Support\Str::headline($value->field_key) }}</td>
                    <td>{{ $value->value }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">No submission values found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
