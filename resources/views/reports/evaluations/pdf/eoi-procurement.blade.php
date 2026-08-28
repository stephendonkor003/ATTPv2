<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>EOI Qualification Report - {{ $report['procurement']->reference_no ?? 'Procurement' }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 76px 24px 46px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            color: #1f2937;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8px;
            line-height: 1.34;
            margin: 0;
        }

        h1,
        h2,
        h3,
        h4,
        p {
            margin: 0;
        }

        .pdf-header {
            background: #0b2138;
            border-bottom: 4px solid #16a34a;
            color: #ffffff;
            height: 60px;
            left: 0;
            padding: 9px 24px 8px;
            position: fixed;
            right: 0;
            top: -76px;
        }

        .pdf-footer {
            border-top: 2px solid #16a34a;
            bottom: -35px;
            color: #475569;
            font-size: 7.6px;
            height: 29px;
            left: 0;
            padding: 7px 24px 0;
            position: fixed;
            right: 0;
        }

        .header-table,
        .footer-table,
        .meta-table,
        .kpi-table,
        .register-table,
        .detail-summary,
        .panel-table,
        .criteria-table,
        .evidence-table {
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }

        .header-table td,
        .footer-table td {
            border: 0;
            padding: 0;
            vertical-align: middle;
        }

        .header-logo {
            width: 175px;
        }

        .header-logo img {
            display: block;
            max-height: 37px;
            max-width: 165px;
        }

        .fallback-brand {
            color: #ffffff;
            font-size: 13px;
            font-weight: 700;
        }

        .header-copy {
            text-align: right;
        }

        .header-copy h1 {
            color: #ffffff;
            font-size: 16px;
            line-height: 1.15;
        }

        .header-copy p {
            color: #cbd5e1;
            font-size: 8.2px;
            margin-top: 4px;
        }

        .document-label {
            background: #15803d;
            color: #ffffff;
            display: inline-block;
            font-size: 6.8px;
            font-weight: 700;
            letter-spacing: .08em;
            margin-bottom: 4px;
            padding: 2px 7px;
            text-transform: uppercase;
        }

        .footer-table td {
            width: 33.333%;
        }

        .footer-center {
            text-align: center;
        }

        .footer-right {
            font-weight: 700;
            text-align: right;
        }

        .page-number:after {
            content: counter(page) " of " counter(pages);
        }

        .report-intro {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-left: 5px solid #16a34a;
            margin-bottom: 7px;
            padding: 8px 10px;
        }

        .report-intro h2 {
            color: #0b2138;
            font-size: 13px;
            line-height: 1.2;
            margin-bottom: 3px;
        }

        .report-intro p {
            color: #64748b;
        }

        .meta-table {
            margin-bottom: 7px;
        }

        .meta-table td {
            border: 1px solid #dbe3eb;
            padding: 5px 7px;
            vertical-align: top;
            width: 25%;
        }

        .meta-table td:nth-child(even) {
            background: #f8fafc;
        }

        .label {
            color: #64748b;
            display: block;
            font-size: 6.7px;
            font-weight: 700;
            letter-spacing: .05em;
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        .value {
            color: #111827;
            font-size: 8.7px;
            font-weight: 700;
        }

        .rule-box {
            background: #eff6ff;
            border: 1px solid #93c5fd;
            margin-bottom: 8px;
            padding: 6px 8px;
        }

        .rule-box strong {
            color: #1e3a5f;
        }

        .rule-box .rule-title {
            color: #1e3a5f;
            display: block;
            font-size: 8px;
            letter-spacing: .04em;
            margin-bottom: 3px;
            text-transform: uppercase;
        }

        .kpi-table {
            margin-bottom: 8px;
        }

        .kpi-table td {
            border: 1px solid #dbe3eb;
            padding: 5px 4px;
            text-align: center;
            vertical-align: top;
            width: 20%;
        }

        .kpi-table td:nth-child(even) {
            background: #f8fafc;
        }

        .kpi-number {
            color: #0b2138;
            display: block;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.05;
            margin-bottom: 3px;
        }

        .kpi-label {
            color: #64748b;
            font-size: 6.7px;
            font-weight: 700;
            letter-spacing: .03em;
            text-transform: uppercase;
        }

        .decision-summary-title {
            color: #0b2138;
            font-size: 10.5px;
            margin: 9px 0 5px;
        }

        .decision-card-table {
            border-collapse: separate;
            border-spacing: 5px 0;
            margin: 0 0 8px;
            table-layout: fixed;
            width: 100%;
        }

        .decision-card-table td {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-top: 3px solid #64748b;
            padding: 7px 8px;
            vertical-align: top;
            width: 33.333%;
        }

        .decision-card-table td.decision-card-qualified {
            background: #f0fdf4;
            border-color: #86efac;
            border-top-color: #16a34a;
        }

        .decision-card-table td.decision-card-stopped {
            background: #fff7f7;
            border-color: #fca5a5;
            border-top-color: #dc2626;
        }

        .decision-card-table td.decision-card-pending {
            background: #fffbeb;
            border-color: #fcd34d;
            border-top-color: #d97706;
        }

        .decision-card-count {
            color: #0b2138;
            float: right;
            font-size: 18px;
            font-weight: 700;
            line-height: 1;
            margin-left: 8px;
        }

        .decision-card-table h3 {
            color: #0b2138;
            font-size: 8.5px;
            line-height: 1.25;
            margin-bottom: 3px;
        }

        .decision-card-table p {
            color: #64748b;
            font-size: 7px;
        }

        .section-heading {
            border-bottom: 2px solid #0b2138;
            color: #0b2138;
            font-size: 11px;
            margin: 8px 0 5px;
            padding-bottom: 4px;
        }

        th,
        td {
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        .register-table {
            margin-bottom: 8px;
        }

        .register-table th,
        .register-table td,
        .panel-table th,
        .panel-table td,
        .criteria-table th,
        .criteria-table td,
        .evidence-table th,
        .evidence-table td {
            border: 1px solid #dbe3eb;
            padding: 4px 5px;
            vertical-align: top;
        }

        .register-table th,
        .panel-table th,
        .criteria-table th,
        .evidence-table th {
            background: #e9eff5;
            color: #334155;
            font-size: 6.8px;
            letter-spacing: .03em;
            text-align: left;
            text-transform: uppercase;
        }

        .register-table th {
            line-height: 1.18;
            vertical-align: middle;
        }

        .register-table td {
            line-height: 1.3;
            vertical-align: middle;
        }

        .register-table th:nth-child(6),
        .register-table td:nth-child(6) {
            border-left: 2px solid #94a3b8;
        }

        .register-table th:nth-child(7),
        .register-table td:nth-child(7) {
            border-left: 2px solid #cbd5e1;
        }

        .register-subline {
            color: #64748b;
            display: block;
            font-size: 6.8px;
            line-height: 1.25;
            margin-top: 3px;
        }

        .decision-counts {
            white-space: nowrap;
        }

        .decision-counts span {
            display: inline-block;
            font-size: 6.8px;
            font-weight: 700;
            margin-right: 3px;
            padding: 1px 3px;
        }

        .decision-count-q {
            background: #dcfce7;
            color: #166534;
        }

        .decision-count-aq {
            background: #fef3c7;
            color: #92400e;
        }

        .decision-count-nq {
            background: #fee2e2;
            color: #991b1b;
        }

        .register-table tbody tr:nth-child(even) td,
        .panel-table tbody tr:nth-child(even) td,
        .criteria-table tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        .number-cell {
            text-align: center;
        }

        .muted {
            color: #64748b;
        }

        .small {
            font-size: 7.1px;
        }

        .strong {
            color: #111827;
            font-weight: 700;
        }

        .nowrap {
            white-space: nowrap;
        }

        .badge {
            border: 1px solid transparent;
            display: inline-block;
            font-size: 6.7px;
            font-weight: 700;
            letter-spacing: .025em;
            padding: 2px 5px;
            text-transform: uppercase;
        }

        .badge-success {
            background: #dcfce7;
            border-color: #86efac;
            color: #166534;
        }

        .badge-warning {
            background: #fef3c7;
            border-color: #fcd34d;
            color: #92400e;
        }

        .badge-danger {
            background: #fee2e2;
            border-color: #fca5a5;
            color: #991b1b;
        }

        .badge-pending {
            background: #e2e8f0;
            border-color: #cbd5e1;
            color: #475569;
        }

        .badge-blue {
            background: #dbeafe;
            border-color: #93c5fd;
            color: #1d4ed8;
        }

        .route-advance {
            color: #166534;
            font-weight: 700;
        }

        .route-stop {
            color: #991b1b;
            font-weight: 700;
        }

        .route-pending {
            color: #475569;
            font-weight: 700;
        }

        .applicant-detail {
            page-break-before: always;
        }

        .applicant-banner {
            background: #0b2138;
            border-left: 5px solid #16a34a;
            color: #ffffff;
            margin-bottom: 7px;
            padding: 8px 10px;
        }

        .applicant-banner table {
            border-collapse: collapse;
            width: 100%;
        }

        .applicant-banner td {
            border: 0;
            padding: 0;
            vertical-align: middle;
        }

        .applicant-banner h2 {
            color: #ffffff;
            font-size: 13px;
            margin-bottom: 2px;
        }

        .applicant-banner p {
            color: #cbd5e1;
            font-size: 7.5px;
        }

        .banner-outcome {
            text-align: right;
            width: 180px;
        }

        .detail-summary {
            margin-bottom: 6px;
        }

        .detail-summary td {
            border: 1px solid #dbe3eb;
            padding: 5px 6px;
            vertical-align: top;
            width: 20%;
        }

        .detail-summary td:nth-child(even) {
            background: #f8fafc;
        }

        .outcome-note {
            background: #f8fafc;
            border: 1px solid #dbe3eb;
            border-left: 4px solid #64748b;
            color: #334155;
            margin-bottom: 8px;
            padding: 6px 8px;
        }

        .outcome-note.success {
            background: #f0fdf4;
            border-color: #86efac;
            border-left-color: #16a34a;
        }

        .outcome-note.warning {
            background: #fffbeb;
            border-color: #fcd34d;
            border-left-color: #d97706;
        }

        .outcome-note.danger {
            background: #fef2f2;
            border-color: #fca5a5;
            border-left-color: #dc2626;
        }

        .evaluation-block {
            margin-top: 9px;
        }

        .evaluation-heading {
            background: #e9eff5;
            border: 1px solid #cbd5e1;
            color: #0b2138;
            font-size: 10px;
            margin-bottom: 5px;
            padding: 5px 7px;
            page-break-after: avoid;
        }

        .subheading {
            color: #334155;
            font-size: 8px;
            margin: 6px 0 3px;
            page-break-after: avoid;
            text-transform: uppercase;
        }

        .panel-table,
        .criteria-table,
        .evidence-table {
            margin-bottom: 6px;
        }

        .evidence-title {
            background: #7f1d1d;
            color: #ffffff;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: .03em;
            margin-top: 7px;
            padding: 4px 6px;
            page-break-after: avoid;
            text-transform: uppercase;
        }

        .evidence-table th {
            background: #fee2e2;
            color: #7f1d1d;
        }

        .comment {
            white-space: pre-wrap;
        }

        .positive-evidence {
            background: #f0fdf4;
            border: 1px solid #86efac;
            color: #166534;
            margin: 6px 0;
            padding: 5px 7px;
        }

        .empty-state {
            background: #f8fafc;
            border: 1px dashed #94a3b8;
            color: #64748b;
            padding: 8px;
            text-align: center;
        }

        thead {
            display: table-header-group;
        }

        tr {
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    @php
        $platformName = $platformName ?? 'Africa Think Tank Platform';
        $platformUrl = $platformUrl ?? rtrim((string) config('app.url'), '/');
        $procurement = $report['procurement'];
        $applicants = collect($report['applicants'] ?? []);
        $qualifiedApplicants = $applicants
            ->filter(fn (array $row): bool => ($row['can_advance'] ?? false) === true
                && ($row['panel_complete'] ?? false) === true
                && in_array(data_get($row, 'outcome.code'), ['fully_qualified', 'average_qualified'], true))
            ->values();
        $finalNotQualifiedApplicants = $applicants
            ->filter(fn (array $row): bool => ($row['panel_complete'] ?? false) === true
                && data_get($row, 'outcome.code') === 'not_qualified')
            ->values();
        $panelInProgressApplicants = $applicants
            ->filter(fn (array $row): bool => ($row['panel_complete'] ?? false) !== true)
            ->values();
        $stats = $report['stats'] ?? [];
        $generatedAt = $report['generated_at'] ?? now();
        // The decision register remains exhaustive. Bound only the evidence
        // appendix so large procurements still generate reliably in DomPDF.
        $detailApplicantLimit = 25;
        $detailApplicants = $applicants->take($detailApplicantLimit)->values();
        $appendixIsTruncated = $detailApplicants->count() < $applicants->count();
        $decisionView = function (array $applicantRow): array {
            $panelComplete = (bool) ($applicantRow['panel_complete'] ?? false);
            $outcomeCode = data_get($applicantRow, 'outcome.code', 'pending');

            if (! $panelComplete) {
                return [
                    'group' => 'pending',
                    'label' => 'Awaiting Panel',
                    'tone' => 'pending',
                    'workflow' => 'Awaiting EOI panel',
                    'workflow_note' => 'No final workflow decision until every assigned task is complete.',
                    'signal' => $outcomeCode === 'not_qualified'
                        ? 'Current signal: NQ recorded; panel incomplete.'
                        : 'Current signal: panel evaluation in progress.',
                ];
            }

            if (($applicantRow['can_advance'] ?? false) === true
                && in_array($outcomeCode, ['fully_qualified', 'average_qualified'], true)) {
                return [
                    'group' => 'qualified',
                    'label' => data_get($applicantRow, 'outcome.label', 'Qualified'),
                    'tone' => data_get($applicantRow, 'outcome.tone', 'success'),
                    'workflow' => 'Technical Evaluation',
                    'workflow_note' => 'Approved to advance.',
                    'signal' => null,
                ];
            }

            if ($outcomeCode === 'not_qualified') {
                return [
                    'group' => 'not-qualified',
                    'label' => 'Not Qualified',
                    'tone' => 'danger',
                    'workflow' => 'Does not advance',
                    'workflow_note' => 'Final panel decision; progression is stopped.',
                    'signal' => null,
                ];
            }

            return [
                'group' => 'pending',
                'label' => 'Decision Pending',
                'tone' => 'pending',
                'workflow' => 'Awaiting EOI decision',
                'workflow_note' => 'No final workflow decision has been recorded.',
                'signal' => null,
            ];
        };
        $outcomeClass = function (array $outcome): string {
            return match ($outcome['tone'] ?? 'pending') {
                'success' => 'badge-success',
                'warning' => 'badge-warning',
                'danger' => 'badge-danger',
                default => 'badge-pending',
            };
        };
        $routeClass = function (array $applicantRow) use ($decisionView): string {
            return match ($decisionView($applicantRow)['group']) {
                'qualified' => 'route-advance',
                'not-qualified' => 'route-stop',
                default => 'route-pending',
            };
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
                <td class="header-copy">
                    <span class="document-label">Official decision record</span>
                    <h1>Expression of Interest Qualification Report</h1>
                    <p>{{ $procurement->reference_no ?? 'No reference' }} &nbsp;|&nbsp; {{ $procurement->title ?? 'Procurement' }}</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="pdf-footer">
        <table class="footer-table">
            <tr>
                <td>{{ $platformName }}</td>
                <td class="footer-center">{{ $platformUrl }}</td>
                <td class="footer-right">Page <span class="page-number"></span></td>
            </tr>
        </table>
    </div>

    <div class="report-intro">
        <h2>{{ $procurement->title ?? 'Procurement' }}</h2>
        <p>Consolidated EOI panel qualification and Technical Evaluation routing record.</p>
    </div>

    <table class="meta-table">
        <tr>
            <td>
                <span class="label">Procurement reference</span>
                <span class="value">{{ $procurement->reference_no ?? 'N/A' }}</span>
            </td>
            <td>
                <span class="label">Evaluation method</span>
                <span class="value">Expression of Interest</span>
            </td>
            <td>
                <span class="label">Report generated</span>
                <span class="value">{{ $generatedAt->format('d M Y, H:i') }}</span>
            </td>
            <td>
                <span class="label">Report status</span>
                <span class="value">Consolidated panel record</span>
            </td>
        </tr>
    </table>

    <div class="rule-box">
        <strong class="rule-title">Mandatory EOI qualification rule</strong>
        <strong>Only the currently assigned panel is counted.</strong> Every assigned task must be complete before the workflow
        decision is final. A completed panel with any &quot;Not Qualified&quot; decision does not advance; otherwise a valid Fully
        Qualified or Average Qualified outcome advances to <strong>Technical Evaluation</strong>. Incomplete panels remain
        Awaiting Panel, including where an early NQ signal has been recorded.
    </div>

    <table class="kpi-table">
        <tr>
            <td><span class="kpi-number">{{ $stats['total_applicants'] ?? 0 }}</span><span class="kpi-label">Applicants</span></td>
            <td><span class="kpi-number">{{ $qualifiedApplicants->count() }}</span><span class="kpi-label">Qualified / advance</span></td>
            <td><span class="kpi-number">{{ $finalNotQualifiedApplicants->count() }}</span><span class="kpi-label">Final not qualified</span></td>
            <td><span class="kpi-number">{{ $panelInProgressApplicants->count() }}</span><span class="kpi-label">Awaiting panel</span></td>
            <td><span class="kpi-number">{{ $stats['panel_members'] ?? 0 }}</span><span class="kpi-label">Panel members</span></td>
        </tr>
    </table>

    <h3 class="decision-summary-title">Panel Decision Summary</h3>
    <table class="decision-card-table" aria-label="Panel decision summary">
        <tr>
            <td class="decision-card-qualified" data-summary-outcome="qualified">
                <span class="decision-card-count">{{ $qualifiedApplicants->count() }}</span>
                <h3>Qualified Applicants &mdash; Advancing to Technical Evaluation</h3>
                <p>Panel complete. These applicants are approved to advance.</p>
            </td>
            <td class="decision-card-stopped" data-summary-outcome="not-qualified">
                <span class="decision-card-count">{{ $finalNotQualifiedApplicants->count() }}</span>
                <h3>Not Qualified Applicants &mdash; Do Not Advance</h3>
                <p>Panel complete. These final decisions stop progression.</p>
            </td>
            <td class="decision-card-pending" data-summary-outcome="pending">
                <span class="decision-card-count">{{ $panelInProgressApplicants->count() }}</span>
                <h3>Awaiting Panel Completion</h3>
                <p>No final outcome or workflow routing until every assigned task is complete.</p>
            </td>
        </tr>
    </table>

    <h3 class="section-heading">Applicant Outcome Register</h3>
    <table class="register-table">
        <colgroup>
            <col style="width: 3%;">
            <col style="width: 12%;">
            <col style="width: 22%;">
            <col style="width: 15%;">
            <col style="width: 13%;">
            <col style="width: 15%;">
            <col style="width: 20%;">
        </colgroup>
        <thead>
            <tr>
                <th>#</th>
                <th>Submission</th>
                <th>Applicant</th>
                <th>Panel completion</th>
                <th>Decision counts</th>
                <th>Final outcome</th>
                <th>Workflow decision</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($applicants as $index => $applicantRow)
                @php
                    $applicant = $applicantRow['applicant'];
                    $finalDecision = $decisionView($applicantRow);
                @endphp
                <tr data-summary-outcome="{{ $finalDecision['group'] }}">
                    <td class="number-cell">{{ $index + 1 }}</td>
                    <td>
                        <span class="strong">{{ $applicant->procurement_submission_code ?? 'N/A' }}</span>
                    </td>
                    <td>
                        <span class="strong">{{ $applicant->display_name }}</span>
                        @if ($applicant->submitter?->email)
                            <br><span class="muted small">{{ $applicant->submitter->email }}</span>
                        @endif
                    </td>
                    <td>
                        <span class="strong">{{ $applicantRow['completed_tasks'] }}/{{ $applicantRow['expected_tasks'] }} tasks</span>
                        <span class="register-subline">
                            {{ $applicantRow['completed_evaluators'] }}/{{ $applicantRow['expected_evaluators'] }} evaluator(s)
                            &middot; {{ $applicantRow['completion_percent'] }}%
                            &middot; {{ $applicantRow['panel_complete'] ? 'Complete' : 'In progress' }}
                        </span>
                    </td>
                    <td>
                        <span class="decision-counts">
                            <span class="decision-count-q">Q {{ $applicantRow['counts']['qualified'] }}</span>
                            <span class="decision-count-aq">AQ {{ $applicantRow['counts']['average_qualified'] }}</span>
                            <span class="decision-count-nq">NQ {{ $applicantRow['counts']['not_qualified'] }}</span>
                        </span>
                    </td>
                    <td>
                        <span class="badge {{ $outcomeClass(['tone' => $finalDecision['tone']]) }}">{{ $finalDecision['label'] }}</span>
                        @if ($finalDecision['signal'])
                            <span class="register-subline">{{ $finalDecision['signal'] }}</span>
                        @endif
                    </td>
                    <td class="{{ $routeClass($applicantRow) }}">
                        <span class="strong {{ $routeClass($applicantRow) }}">{{ $finalDecision['workflow'] }}</span>
                        <span class="register-subline">{{ $finalDecision['workflow_note'] }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="number-cell muted">No reportable EOI applicants were found for this procurement.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if ($appendixIsTruncated)
        <div class="empty-state">
            The register above includes all {{ $applicants->count() }} applicants. The evidence appendix contains the first
            {{ $detailApplicants->count() }} records; the web report remains the complete interactive evidence record.
        </div>
    @endif

    @foreach ($detailApplicants as $index => $applicantRow)
        @php
            $applicant = $applicantRow['applicant'];
            $finalDecision = $decisionView($applicantRow);
            $outcomeTone = $finalDecision['tone'];
        @endphp

        <div class="applicant-detail">
            <div class="applicant-banner">
                <table>
                    <tr>
                        <td>
                            <h2>{{ $index + 1 }}. {{ $applicant->display_name }}</h2>
                            <p>
                                Submission {{ $applicant->procurement_submission_code ?? 'N/A' }}
                                @if ($applicant->submitter?->email)
                                    &nbsp;|&nbsp; {{ $applicant->submitter->email }}
                                @endif
                            </p>
                        </td>
                        <td class="banner-outcome">
                            <span class="badge {{ $outcomeClass(['tone' => $finalDecision['tone']]) }}">{{ $finalDecision['label'] }}</span>
                        </td>
                    </tr>
                </table>
            </div>

            <table class="detail-summary">
                <tr>
                    <td>
                        <span class="label">Panel tasks</span>
                        <span class="value">{{ $applicantRow['completed_tasks'] }} / {{ $applicantRow['expected_tasks'] }} complete</span>
                    </td>
                    <td>
                        <span class="label">Evaluators</span>
                        <span class="value">{{ $applicantRow['completed_evaluators'] }} / {{ $applicantRow['expected_evaluators'] }} complete</span>
                    </td>
                    <td>
                        <span class="label">Valid decisions</span>
                        <span class="value">{{ $applicantRow['total_decisions'] }}</span>
                    </td>
                    <td>
                        <span class="label">Category distribution</span>
                        <span class="value">Q {{ $applicantRow['counts']['qualified'] }} &middot; AQ {{ $applicantRow['counts']['average_qualified'] }} &middot; NQ {{ $applicantRow['counts']['not_qualified'] }}</span>
                    </td>
                    <td>
                        <span class="label">Workflow decision</span>
                        <span class="value {{ $routeClass($applicantRow) }}">{{ $finalDecision['workflow'] }}</span>
                    </td>
                </tr>
            </table>

            <div class="outcome-note {{ in_array($outcomeTone, ['success', 'warning', 'danger'], true) ? $outcomeTone : '' }}">
                @if ($applicantRow['panel_complete'])
                    <strong>Final panel determination:</strong> {{ $applicantRow['outcome']['description'] }}
                    <strong> Workflow: {{ $finalDecision['workflow'] }}.</strong>
                @else
                    <strong>Current panel signal:</strong> {{ $applicantRow['outcome']['description'] }}
                    <strong> Final outcome and workflow remain pending at {{ $applicantRow['completion_percent'] }}% completion.</strong>
                @endif
            </div>

            @forelse ($applicantRow['evaluation_reports'] as $evaluationIndex => $evaluationReport)
                @php
                    $evaluation = $evaluationReport['evaluation'];
                    $members = collect($evaluationReport['members']);
                    $criteriaRows = collect($evaluationReport['criteria']);
                    $expectedMembers = $members->where('assigned', true);
                    $expectedMemberCount = ($expectedMembers->isNotEmpty() ? $expectedMembers : $members)->count();
                    $notQualifiedEvidence = $criteriaRows->flatMap(function (array $criterionRow) {
                        return collect($criterionRow['assessments'])
                            ->where('decision', 0)
                            ->map(function (array $assessment) use ($criterionRow): array {
                                return [
                                    'section' => $criterionRow['section']->name,
                                    'criterion' => $criterionRow['criterion']->name,
                                    'evaluator' => $assessment['evaluator_name'],
                                    'comment' => $assessment['comment'],
                                ];
                            });
                    })->values();
                @endphp

                <div class="evaluation-block">
                    <h3 class="evaluation-heading">
                        Evaluation {{ $evaluationIndex + 1 }}: {{ $evaluation->name }}
                        &nbsp;&middot;&nbsp; {{ $members->count() }} active panel task(s)
                    </h3>

                    <h4 class="subheading">Active panel completion and decision counts</h4>
                    <table class="panel-table">
                        <thead>
                            <tr>
                                <th style="width: 29%;">Panel member</th>
                                <th style="width: 18%;">Completion</th>
                                <th style="width: 20%;">Submitted at</th>
                                <th style="width: 11%;">Qualified</th>
                                <th style="width: 11%;">Average</th>
                                <th style="width: 11%;">Not Qualified</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($members as $member)
                                <tr>
                                    <td>
                                        <span class="strong">{{ $member['name'] }}</span>
                                        @if ($member['email'])
                                            <br><span class="muted small">{{ $member['email'] }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($member['task_complete'])
                                            <span class="badge badge-success">Complete</span>
                                        @elseif ($member['submitted'])
                                            <span class="badge badge-warning">Submitted / incomplete</span>
                                        @else
                                            <span class="badge badge-pending">Awaiting submission</span>
                                        @endif
                                    </td>
                                    <td>{{ $member['submitted_at']?->format('d M Y, H:i') ?? 'Not submitted' }}</td>
                                    <td class="number-cell">{{ $member['counts']['qualified'] }}</td>
                                    <td class="number-cell">{{ $member['counts']['average_qualified'] }}</td>
                                    <td class="number-cell {{ $member['counts']['not_qualified'] > 0 ? 'route-stop' : '' }}">{{ $member['counts']['not_qualified'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="number-cell muted">No active panel member records are available for this evaluation.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <h4 class="subheading">Criterion-level consolidated panel outcome</h4>
                    <table class="criteria-table">
                        <thead>
                            <tr>
                                <th style="width: 18%;">Section</th>
                                <th style="width: 30%;">Criterion</th>
                                <th style="width: 10%;">Received</th>
                                <th style="width: 8%;">Qualified</th>
                                <th style="width: 9%;">Average</th>
                                <th style="width: 10%;">Not Qualified</th>
                                <th style="width: 15%;">Consolidated outcome</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($criteriaRows as $criterionRow)
                                <tr>
                                    <td>{{ $criterionRow['section']->name }}</td>
                                    <td><span class="strong">{{ $criterionRow['criterion']->name }}</span></td>
                                    <td class="number-cell">{{ collect($criterionRow['assessments'])->count() }} / {{ $expectedMemberCount }}</td>
                                    <td class="number-cell">{{ $criterionRow['counts']['qualified'] }}</td>
                                    <td class="number-cell">{{ $criterionRow['counts']['average_qualified'] }}</td>
                                    <td class="number-cell {{ $criterionRow['counts']['not_qualified'] > 0 ? 'route-stop' : '' }}">{{ $criterionRow['counts']['not_qualified'] }}</td>
                                    <td><span class="badge {{ $outcomeClass($criterionRow['outcome']) }}">{{ $criterionRow['outcome']['label'] }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="number-cell muted">No criteria are configured or reportable for this evaluation.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    @if ($notQualifiedEvidence->isNotEmpty())
                        <div class="evidence-title">Automatic disqualification evidence &mdash; {{ $notQualifiedEvidence->count() }} Not Qualified decision(s)</div>
                        <table class="evidence-table">
                            <thead>
                                <tr>
                                    <th style="width: 16%;">Evaluator</th>
                                    <th style="width: 19%;">Section</th>
                                    <th style="width: 28%;">Criterion</th>
                                    <th style="width: 10%;">Decision</th>
                                    <th style="width: 27%;">Evaluator comment / evidence</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($notQualifiedEvidence as $evidence)
                                    <tr>
                                        <td><span class="strong">{{ $evidence['evaluator'] }}</span></td>
                                        <td>{{ $evidence['section'] }}</td>
                                        <td>{{ $evidence['criterion'] }}</td>
                                        <td><span class="badge badge-danger">Not Qualified</span></td>
                                        <td class="comment">{{ filled($evidence['comment']) ? $evidence['comment'] : 'No supporting comment was entered.' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="positive-evidence">
                            <strong>No Not Qualified evidence recorded:</strong>
                            no submitted panel decision in this evaluation triggers the automatic disqualification rule.
                        </div>
                    @endif
                </div>
            @empty
                <div class="empty-state">No evaluation detail is available for this applicant.</div>
            @endforelse
        </div>
    @endforeach
</body>
</html>
