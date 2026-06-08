<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $anonymised ? 'Anonymised ' : '' }}Site Visit Report</title>
    <style>
        @page {
            margin: 112px 34px 76px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: #ffffff;
            color: #1f2937;
            font-family: DejaVu Sans, sans-serif;
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

        .pdf-footer .footer-inner {
            border-top: 1px solid #cbd5e1;
            padding: 9px 34px 0;
        }

        .pdf-footer .page-number:after {
            content: counter(page) " / " counter(pages);
        }

        .footer-table {
            border-collapse: collapse;
            width: 100%;
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

        .header-table,
        .meta-table,
        .summary-table,
        .approval-table {
            border-collapse: collapse;
            width: 100%;
        }

        .header-logo {
            width: 158px;
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
            letter-spacing: .02em;
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
            background: {{ $anonymised ? '#f97316' : '#2563eb' }};
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

        .report-cover {
            background: #f8fafc;
            border: 1px solid #dbe3eb;
            border-left: 5px solid #16a34a;
            margin-bottom: 14px;
            padding: 14px 16px;
        }

        .report-cover h2 {
            color: #0f172a;
            font-size: 17px;
            line-height: 1.22;
            margin-bottom: 8px;
        }

        .muted {
            color: #64748b;
        }

        .small {
            font-size: 9.5px;
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
            font-size: 11px;
            font-weight: 700;
        }

        .summary-table {
            margin-bottom: 13px;
        }

        .summary-table td {
            border: 1px solid #dbe3eb;
            padding: 9px 10px;
            vertical-align: top;
            width: 25%;
        }

        .summary-table .soft {
            background: #f8fafc;
        }

        .section-title {
            border-bottom: 1px solid #cbd5e1;
            color: #0f172a;
            font-size: 13px;
            font-weight: 800;
            margin: 16px 0 9px;
            padding-bottom: 6px;
        }

        .notice {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
            margin-bottom: 12px;
            padding: 9px 11px;
        }

        .approval-box,
        .observation-box {
            border: 1px solid #dbe3eb;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }

        .approval-box {
            background: #f8fafc;
            padding: 11px;
        }

        .approval-table td {
            vertical-align: top;
        }

        .approval-status {
            text-align: right;
            width: 120px;
        }

        .status-pill,
        .severity-pill,
        .action-pill {
            border-radius: 999px;
            display: inline-block;
            font-size: 8.5px;
            font-weight: 800;
            letter-spacing: .04em;
            padding: 4px 8px;
            text-transform: uppercase;
        }

        .status-pill {
            background: #dcfce7;
            color: #166534;
        }

        .status-pill--submitted,
        .status-pill--draft {
            background: #fef3c7;
            color: #92400e;
        }

        .status-pill--rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .remarks {
            color: #334155;
            margin-top: 8px;
            white-space: pre-line;
        }

        .observation-box {
            padding: 0;
        }

        .observation-head {
            background: #f8fafc;
            border-bottom: 1px solid #dbe3eb;
            padding: 9px 10px;
        }

        .observation-head h3 {
            color: #111827;
            font-size: 11.5px;
            line-height: 1.25;
        }

        .observation-body {
            padding: 10px;
        }

        .badge-row {
            margin-top: 7px;
        }

        .severity-pill {
            border: 1px solid #2563eb;
            color: #1d4ed8;
        }

        .severity-pill--high {
            border-color: #dc2626;
            color: #991b1b;
        }

        .severity-pill--medium {
            border-color: #d97706;
            color: #92400e;
        }

        .action-pill {
            border: 1px solid #94a3b8;
            color: #334155;
            margin-left: 4px;
        }

        .action-pill--required {
            border-color: #ea580c;
            color: #9a3412;
        }

        .empty {
            border: 1px dashed #cbd5e1;
            color: #64748b;
            padding: 14px;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $approval = $siteVisit->approvals->first();
        $actionRequired = $siteVisit->observations->where('action_required', true)->count();
        $score = null;

        foreach ($siteVisit->approvals as $approvalRecord) {
            if (preg_match('/Total score:\s*([0-9.]+)/i', (string) $approvalRecord->remarks, $matches)) {
                $score = $matches[1];
                break;
            }
        }

        $maskReviewer = fn ($name = null) => $anonymised ? 'XXX-XXX-XX' : ($name ?: 'Reviewer');
        $maskEvaluator = fn ($name = null) => $anonymised ? 'XXX' : ($name ?: '-');
        $reviewerNames = $siteVisit->approvals
            ->map(fn ($approvalRecord) => $approvalRecord->reviewer?->name)
            ->filter()
            ->unique()
            ->values();
        $evaluatorNames = collect([
                $siteVisit->assignment?->user?->name,
                $siteVisit->group?->leader?->name,
            ])
            ->merge($siteVisit->group?->members?->map(fn ($member) => $member->user?->name) ?? collect())
            ->filter()
            ->unique()
            ->values();
        $redactText = function ($text) use ($anonymised, $reviewerNames, $evaluatorNames) {
            if (! $anonymised || blank($text)) {
                return $text;
            }

            $redacted = (string) $text;

            foreach ($reviewerNames as $name) {
                $redacted = str_replace($name, 'XXX-XXX-XX', $redacted);
            }

            foreach ($evaluatorNames as $name) {
                $redacted = str_replace($name, 'XXX', $redacted);
            }

            return $redacted;
        };

        $teamName = $siteVisit->assignment_type === 'individual'
            ? $maskEvaluator($siteVisit->assignment?->user?->name)
            : ($siteVisit->group?->group_name ?? '-');
        $leaderName = $maskEvaluator($siteVisit->group?->leader?->name ?? $siteVisit->assignment?->user?->name);
        $modeLabel = $anonymised ? 'Anonymised applicant' : 'Internal report';
    @endphp

    <div class="pdf-header">
        <table class="header-table">
            <tr>
                <td class="header-logo">
                    @if ($logoDataUri)
                        <img src="{{ $logoDataUri }}" alt="{{ $platformName }}">
                    @else
                        <span class="fallback-brand">{{ $platformName }}</span>
                    @endif
                </td>
                <td class="header-title">
                    <span class="mode-pill">{{ $modeLabel }}</span>
                    <h1>Site Visit Evaluation Report</h1>
                    <p>{{ $procurement->title }}</p>
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

    <div class="report-cover">
        <h2>{{ $siteVisit->submission?->display_name ?? 'Applicant' }}</h2>
        <p class="muted">
            Site visit report for {{ $procurement->title }}.
        </p>
    </div>

    <table class="summary-table">
        <tr>
            <td>
                <span class="label">Visit Date</span>
                <span class="value">{{ optional($siteVisit->visit_date)->format('d M Y') ?? '-' }}</span>
            </td>
            <td class="soft">
                <span class="label">Status</span>
                <span class="status-pill status-pill--{{ $siteVisit->status }}">
                    {{ ucfirst($siteVisit->status ?? '-') }}
                </span>
            </td>
            <td>
                <span class="label">Assignment</span>
                <span class="value">{{ ucfirst($siteVisit->assignment_type ?? '-') }}</span>
            </td>
            <td class="soft">
                <span class="label">Total Score</span>
                <span class="value">{{ $score ?? '-' }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="label">Team or Officer</span>
                <span class="value">{{ $teamName }}</span>
            </td>
            <td class="soft">
                <span class="label">Lead Evaluator</span>
                <span class="value">{{ $leaderName }}</span>
            </td>
            <td>
                <span class="label">Observations</span>
                <span class="value">{{ $siteVisit->observations->count() }}</span>
            </td>
            <td class="soft">
                <span class="label">Action Items</span>
                <span class="value">{{ $actionRequired }}</span>
            </td>
        </tr>
        @if ($siteVisit->group && $siteVisit->group->members->isNotEmpty())
            <tr>
                <td colspan="4">
                    <span class="label">Evaluation Team Members</span>
                    <span class="value">
                        @foreach ($siteVisit->group->members as $member)
                            {{ $maskEvaluator($member->user?->name) }}@if (!$loop->last), @endif
                        @endforeach
                    </span>
                </td>
            </tr>
        @endif
    </table>

    <h2 class="section-title">Approval Summary</h2>
    @forelse ($siteVisit->approvals as $approvalRecord)
        <div class="approval-box">
            <table class="approval-table">
                <tr>
                    <td>
                        <span class="label">Approval Officer</span>
                        <span class="value">{{ $maskReviewer($approvalRecord->reviewer?->name) }}</span>
                    </td>
                    <td class="approval-status">
                        <span class="status-pill status-pill--{{ $approvalRecord->status }}">
                            {{ ucfirst($approvalRecord->status ?? '-') }}
                        </span>
                    </td>
                </tr>
            </table>
            @if ($approvalRecord->remarks)
                <p class="remarks">{{ $redactText($approvalRecord->remarks) }}</p>
            @endif
        </div>
    @empty
        <div class="empty">No approval record has been captured.</div>
    @endforelse

    <h2 class="section-title">Evaluation Observations</h2>
    @forelse ($siteVisit->observations as $observation)
        <div class="observation-box">
            <div class="observation-head">
                <h3>{{ $observation->category }}</h3>
                <div class="badge-row">
                    <span class="severity-pill severity-pill--{{ $observation->severity }}">
                        {{ ucfirst($observation->severity ?? 'Low') }}
                    </span>
                    <span class="action-pill {{ $observation->action_required ? 'action-pill--required' : '' }}">
                        Action: {{ $observation->action_required ? 'Required' : 'No' }}
                    </span>
                </div>
            </div>
            <div class="observation-body">
                <p>{{ $redactText($observation->description) }}</p>
            </div>
        </div>
    @empty
        <div class="empty">No observations recorded.</div>
    @endforelse
</body>
</html>
