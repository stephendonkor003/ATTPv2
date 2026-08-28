<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>EOI Qualification Report - {{ $report['procurement']->reference_no ?? 'Procurement' }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 82px 26px 50px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            color: #1f2937;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8.4px;
            line-height: 1.38;
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
            height: 66px;
            left: 0;
            padding: 11px 26px 9px;
            position: fixed;
            right: 0;
            top: -82px;
        }

        .pdf-footer {
            border-top: 2px solid #16a34a;
            bottom: -39px;
            color: #475569;
            font-size: 7.6px;
            height: 29px;
            left: 0;
            padding: 7px 26px 0;
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
            margin-bottom: 9px;
            padding: 10px 12px;
        }

        .report-intro h2 {
            color: #0b2138;
            font-size: 15px;
            line-height: 1.2;
            margin-bottom: 3px;
        }

        .report-intro p {
            color: #64748b;
        }

        .meta-table {
            margin-bottom: 9px;
        }

        .meta-table td {
            border: 1px solid #dbe3eb;
            padding: 6px 8px;
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
            margin-bottom: 10px;
            padding: 8px 10px;
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
            margin-bottom: 11px;
        }

        .kpi-table td {
            border: 1px solid #dbe3eb;
            padding: 6px 5px;
            text-align: center;
            vertical-align: top;
            width: 12.5%;
        }

        .kpi-table td:nth-child(even) {
            background: #f8fafc;
        }

        .kpi-number {
            color: #0b2138;
            display: block;
            font-size: 15px;
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

        .qualified-banner {
            background: #14532d;
            border-left: 5px solid #4ade80;
            color: #ffffff;
            margin: 11px 0 0;
            padding: 8px 10px;
            page-break-after: avoid;
        }

        .qualified-banner table {
            border-collapse: collapse;
            width: 100%;
        }

        .qualified-banner td {
            border: 0;
            padding: 0;
            vertical-align: middle;
        }

        .qualified-banner h3 {
            color: #ffffff;
            font-size: 11.5px;
            line-height: 1.15;
            margin-bottom: 2px;
        }

        .qualified-banner p {
            color: #dcfce7;
            font-size: 7.4px;
        }

        .qualified-total {
            color: #ffffff;
            font-size: 19px;
            font-weight: 700;
            text-align: right;
            width: 105px;
        }

        .qualified-total span {
            display: block;
            font-size: 6.4px;
            letter-spacing: .05em;
            margin-top: 2px;
            text-transform: uppercase;
        }

        .qualified-table {
            border-collapse: collapse;
            margin-bottom: 11px;
            table-layout: fixed;
            width: 100%;
        }

        .qualified-table th,
        .qualified-table td {
            border: 1px solid #bbd8c4;
            padding: 4px 5px;
            vertical-align: top;
        }

        .qualified-table th {
            background: #dcfce7;
            color: #14532d;
            font-size: 6.8px;
            letter-spacing: .03em;
            text-align: left;
            text-transform: uppercase;
        }

        .qualified-table tbody tr:nth-child(even) td {
            background: #f0fdf4;
        }

        .qualified-empty {
            background: #f0fdf4;
            border: 1px solid #86efac;
            border-top: 0;
            color: #166534;
            margin-bottom: 11px;
            padding: 10px 12px;
            text-align: center;
        }

        .qualified-empty strong {
            display: block;
            font-size: 9px;
            margin-bottom: 2px;
        }

        .not-qualified-banner {
            background: #7f1d1d;
            border-left-color: #f87171;
        }

        .not-qualified-banner p {
            color: #fee2e2;
        }

        .not-qualified-table th {
            background: #fee2e2;
            color: #7f1d1d;
        }

        .not-qualified-table th,
        .not-qualified-table td {
            border-color: #fecaca;
        }

        .not-qualified-table tbody tr:nth-child(even) td {
            background: #fff7f7;
        }

        .not-qualified-empty {
            background: #fff7f7;
            border-color: #fca5a5;
            color: #991b1b;
        }

        .pending-banner {
            background: #78350f;
            border-left-color: #fbbf24;
        }

        .pending-banner p {
            color: #fef3c7;
        }

        .pending-table th {
            background: #fef3c7;
            color: #78350f;
        }

        .pending-table th,
        .pending-table td {
            border-color: #fde68a;
        }

        .pending-table tbody tr:nth-child(even) td {
            background: #fffbeb;
        }

        .pending-empty {
            background: #fffbeb;
            border-color: #fcd34d;
            color: #92400e;
        }

        .section-heading {
            border-bottom: 2px solid #0b2138;
            color: #0b2138;
            font-size: 11px;
            margin: 10px 0 6px;
            padding-bottom: 4px;
        }

        th,
        td {
            overflow-wrap: break-word;
            word-wrap: break-word;
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
                && ($row['panel_complete'] ?? false) === true)
            ->values();
        $finalNotQualifiedApplicants = $applicants
            ->filter(fn (array $row): bool => ($row['panel_complete'] ?? false) === true
                && data_get($row, 'outcome.code') === 'not_qualified')
            ->values();
        $awaitingPanelApplicants = $applicants
            ->filter(fn (array $row): bool => ($row['panel_complete'] ?? false) !== true)
            ->values();
        $finalDecisionCount = $qualifiedApplicants->count() + $finalNotQualifiedApplicants->count();
        $stats = $report['stats'] ?? [];
        $generatedAt = $report['generated_at'] ?? now();
        // DomPDF keeps the complete document tree in memory. Keep the official
        // outcome register exhaustive, while bounding the evidence appendix on
        // exceptionally large procurements so the download remains reliable.
        $detailApplicantLimit = 25;
        $detailApplicants = $applicants
            ->sortBy(function (array $row): int {
                return match ($row['outcome']['code'] ?? 'pending') {
                    'not_qualified' => 0,
                    'pending' => 1,
                    'average_qualified' => 2,
                    'fully_qualified' => 3,
                    default => 4,
                };
            })
            ->take($detailApplicantLimit)
            ->values();
        $appendixIsTruncated = $detailApplicants->count() < $applicants->count();
        $outcomeClass = function (array $outcome): string {
            return match ($outcome['tone'] ?? 'pending') {
                'success' => 'badge-success',
                'warning' => 'badge-warning',
                'danger' => 'badge-danger',
                default => 'badge-pending',
            };
        };
        $routeClass = function (array $applicantRow): string {
            if (! ($applicantRow['panel_complete'] ?? false)) {
                return 'route-pending';
            }

            if ($applicantRow['can_advance'] ?? false) {
                return 'route-advance';
            }

            return ($applicantRow['outcome']['code'] ?? null) === 'not_qualified'
                ? 'route-stop'
                : 'route-pending';
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
        <strong>One &quot;Not Qualified&quot; decision is an automatic disqualifier.</strong>
        An applicant advances to <strong>Technical Evaluation</strong> only when every assigned panel task is complete,
        at least one valid criterion decision exists, and no evaluator has recorded &quot;Not Qualified&quot;. Applicants with only
        &quot;Qualified&quot; decisions are Fully Qualified; applicants with at least one &quot;Average Qualified&quot; decision and no
        &quot;Not Qualified&quot; decision are Average Qualified. Incomplete panels remain pending.
    </div>

    <table class="kpi-table">
        <tr>
            <td><span class="kpi-number">{{ $stats['total_applicants'] ?? 0 }}</span><span class="kpi-label">Applicants</span></td>
            <td><span class="kpi-number">{{ $qualifiedApplicants->count() }}</span><span class="kpi-label">Qualified / advance</span></td>
            <td><span class="kpi-number">{{ $qualifiedApplicants->where('outcome.code', 'fully_qualified')->count() }}</span><span class="kpi-label">Fully qualified</span></td>
            <td><span class="kpi-number">{{ $qualifiedApplicants->where('outcome.code', 'average_qualified')->count() }}</span><span class="kpi-label">Average qualified</span></td>
            <td><span class="kpi-number">{{ $finalNotQualifiedApplicants->count() }}</span><span class="kpi-label">Final not qualified</span></td>
            <td><span class="kpi-number">{{ $awaitingPanelApplicants->count() }}</span><span class="kpi-label">Awaiting panel</span></td>
            <td><span class="kpi-number">{{ $finalDecisionCount }}</span><span class="kpi-label">Final decisions</span></td>
            <td><span class="kpi-number">{{ $stats['panel_members'] ?? 0 }}</span><span class="kpi-label">Panel members</span></td>
        </tr>
    </table>

    <div class="qualified-banner" data-summary-outcome="qualified">
        <table>
            <tr>
                <td>
                    <h3>Qualified Applicants &mdash; Advancing to Technical Evaluation</h3>
                    <p>Official advancement shortlist: completed panel, valid EOI decisions, and no Not Qualified decision.</p>
                </td>
                <td class="qualified-total">
                    {{ $qualifiedApplicants->count() }}
                    <span>Approved to advance</span>
                </td>
            </tr>
        </table>
    </div>

    @if ($qualifiedApplicants->isNotEmpty())
        <table class="qualified-table">
            <thead>
                <tr>
                    <th style="width: 4%;">#</th>
                    <th style="width: 13%;">Submission</th>
                    <th style="width: 25%;">Applicant</th>
                    <th style="width: 14%;">Qualification outcome</th>
                    <th style="width: 12%;">EOI counts</th>
                    <th style="width: 17%;">Panel completion</th>
                    <th style="width: 15%;">Next stage</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($qualifiedApplicants as $index => $applicantRow)
                    @php
                        $applicant = $applicantRow['applicant'];
                    @endphp
                    <tr data-summary-outcome="qualified">
                        <td class="number-cell">{{ $index + 1 }}</td>
                        <td><span class="strong">{{ $applicant->procurement_submission_code ?? 'N/A' }}</span></td>
                        <td>
                            <span class="strong">{{ $applicant->display_name }}</span>
                            @if ($applicant->submitter?->email)
                                <br><span class="muted small">{{ $applicant->submitter->email }}</span>
                            @endif
                        </td>
                        <td><span class="badge {{ $outcomeClass($applicantRow['outcome']) }}">{{ $applicantRow['outcome']['label'] }}</span></td>
                        <td>
                            <span class="strong">Q {{ $applicantRow['counts']['qualified'] }}</span>
                            &nbsp;&middot;&nbsp; AQ {{ $applicantRow['counts']['average_qualified'] }}
                        </td>
                        <td>
                            <span class="strong">{{ $applicantRow['completed_tasks'] }}/{{ $applicantRow['expected_tasks'] }} tasks</span>
                            <br><span class="muted small">{{ $applicantRow['completed_evaluators'] }}/{{ $applicantRow['expected_evaluators'] }} evaluator(s) &middot; {{ $applicantRow['completion_percent'] }}%</span>
                        </td>
                        <td class="route-advance">
                            {{ $applicantRow['next_stage'] }}
                            <br><span class="small">Approved to advance</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="qualified-empty">
            <strong>No applicants are currently approved to advance.</strong>
            Fully Qualified and Average Qualified applicants will appear here after all assigned panel tasks are complete.
        </div>
    @endif

    <div class="qualified-banner not-qualified-banner" data-summary-outcome="not-qualified">
        <table>
            <tr>
                <td>
                    <h3>Not Qualified Applicants &mdash; Do Not Advance</h3>
                    <p>Final non-advancement list: every assigned panel task is complete and at least one Not Qualified decision was recorded.</p>
                </td>
                <td class="qualified-total">
                    {{ $finalNotQualifiedApplicants->count() }}
                    <span>Final not qualified</span>
                </td>
            </tr>
        </table>
    </div>

    @if ($finalNotQualifiedApplicants->isNotEmpty())
        <table class="qualified-table not-qualified-table">
            <thead>
                <tr>
                    <th style="width: 4%;">#</th>
                    <th style="width: 14%;">Submission</th>
                    <th style="width: 27%;">Applicant</th>
                    <th style="width: 15%;">Final outcome</th>
                    <th style="width: 12%;">NQ decisions</th>
                    <th style="width: 16%;">Panel completion</th>
                    <th style="width: 12%;">Routing</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($finalNotQualifiedApplicants as $index => $applicantRow)
                    @php
                        $applicant = $applicantRow['applicant'];
                    @endphp
                    <tr data-summary-outcome="not-qualified">
                        <td class="number-cell">{{ $index + 1 }}</td>
                        <td><span class="strong">{{ $applicant->procurement_submission_code ?? 'N/A' }}</span></td>
                        <td>
                            <span class="strong">{{ $applicant->display_name }}</span>
                            @if ($applicant->submitter?->email)
                                <br><span class="muted small">{{ $applicant->submitter->email }}</span>
                            @endif
                        </td>
                        <td><span class="badge badge-danger">Not Qualified</span></td>
                        <td class="number-cell route-stop">{{ $applicantRow['counts']['not_qualified'] }}</td>
                        <td>
                            <span class="strong">{{ $applicantRow['completed_tasks'] }}/{{ $applicantRow['expected_tasks'] }} tasks</span>
                            <br><span class="muted small">{{ $applicantRow['completion_percent'] }}% complete</span>
                        </td>
                        <td class="route-stop">Does not advance</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="qualified-empty not-qualified-empty">
            <strong>No final Not Qualified decisions.</strong>
            Incomplete applicants remain in Awaiting Panel until every assigned task is complete.
        </div>
    @endif

    <div class="qualified-banner pending-banner" data-summary-outcome="pending">
        <table>
            <tr>
                <td>
                    <h3>Awaiting Panel Completion</h3>
                    <p>These applicants do not have a final workflow decision until all remaining assigned panel tasks are complete.</p>
                </td>
                <td class="qualified-total">
                    {{ $awaitingPanelApplicants->count() }}
                    <span>Pending completion</span>
                </td>
            </tr>
        </table>
    </div>

    @if ($awaitingPanelApplicants->isNotEmpty())
        <table class="qualified-table pending-table">
            <thead>
                <tr>
                    <th style="width: 4%;">#</th>
                    <th style="width: 15%;">Submission</th>
                    <th style="width: 28%;">Applicant</th>
                    <th style="width: 17%;">Current signal</th>
                    <th style="width: 18%;">Panel completion</th>
                    <th style="width: 18%;">Routing</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($awaitingPanelApplicants as $index => $applicantRow)
                    @php
                        $applicant = $applicantRow['applicant'];
                        $pendingHasVeto = data_get($applicantRow, 'outcome.code') === 'not_qualified';
                    @endphp
                    <tr data-summary-outcome="pending">
                        <td class="number-cell">{{ $index + 1 }}</td>
                        <td><span class="strong">{{ $applicant->procurement_submission_code ?? 'N/A' }}</span></td>
                        <td><span class="strong">{{ $applicant->display_name }}</span></td>
                        <td>
                            <span class="badge badge-pending">
                                {{ $pendingHasVeto ? 'NQ recorded / incomplete' : 'Awaiting Panel' }}
                            </span>
                        </td>
                        <td>
                            <span class="strong">{{ $applicantRow['completed_tasks'] }}/{{ $applicantRow['expected_tasks'] }} tasks</span>
                            <br><span class="muted small">{{ $applicantRow['completion_percent'] }}% complete</span>
                        </td>
                        <td class="route-pending">Held until panel completion</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="qualified-empty pending-empty">
            <strong>No applicants are awaiting panel completion.</strong>
            Every reportable applicant currently has a final panel outcome.
        </div>
    @endif

    <h3 class="section-heading">Applicant Outcome Register</h3>
    <table class="register-table">
        <thead>
            <tr>
                <th style="width: 3%;">#</th>
                <th style="width: 13%;">Submission</th>
                <th style="width: 22%;">Applicant</th>
                <th style="width: 13%;">Panel completion</th>
                <th style="width: 14%;">Category decisions</th>
                <th style="width: 13%;">Final outcome</th>
                <th style="width: 22%;">Workflow decision</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($applicants as $index => $applicantRow)
                @php
                    $applicant = $applicantRow['applicant'];
                @endphp
                <tr>
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
                        <br><span class="muted small">{{ $applicantRow['completed_evaluators'] }}/{{ $applicantRow['expected_evaluators'] }} evaluator(s) &middot; {{ $applicantRow['completion_percent'] }}%</span>
                    </td>
                    <td>
                        <span class="strong">Q {{ $applicantRow['counts']['qualified'] }}</span>
                        &nbsp;&middot;&nbsp; AQ {{ $applicantRow['counts']['average_qualified'] }}
                        &nbsp;&middot;&nbsp; <span class="{{ $applicantRow['counts']['not_qualified'] > 0 ? 'route-stop' : '' }}">NQ {{ $applicantRow['counts']['not_qualified'] }}</span>
                    </td>
                    <td>
                        <span class="badge {{ $outcomeClass($applicantRow['outcome']) }}">{{ $applicantRow['outcome']['label'] }}</span>
                    </td>
                    <td class="{{ $routeClass($applicantRow) }}">
                        {{ $applicantRow['next_stage'] }}
                        @if ($applicantRow['can_advance'])
                            <br><span class="small">Eligible to advance</span>
                        @elseif (($applicantRow['outcome']['code'] ?? null) === 'not_qualified')
                            <br><span class="small">Blocked by the automatic disqualification rule</span>
                        @else
                            <br><span class="small">No final routing until the panel completes</span>
                        @endif
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
            The applicant outcome register above includes all {{ $applicants->count() }} applicants.
            To keep this PDF reliable, the evidence appendix below shows the {{ $detailApplicants->count() }} highest-priority
            records (Not Qualified and pending records first). The secure online report contains every evaluator decision and comment.
        </div>
    @endif

    @foreach ($detailApplicants as $index => $applicantRow)
        @php
            $applicant = $applicantRow['applicant'];
            $outcomeTone = $applicantRow['outcome']['tone'] ?? 'pending';
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
                            <span class="badge {{ $outcomeClass($applicantRow['outcome']) }}">{{ $applicantRow['outcome']['label'] }}</span>
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
                        <span class="label">Next stage</span>
                        <span class="value {{ $routeClass($applicantRow) }}">{{ $applicantRow['next_stage'] }}</span>
                    </td>
                </tr>
            </table>

            <div class="outcome-note {{ in_array($outcomeTone, ['success', 'warning', 'danger'], true) ? $outcomeTone : '' }}">
                <strong>Consolidated determination:</strong> {{ $applicantRow['outcome']['description'] }}
                @if (! $applicantRow['panel_complete'])
                    <strong> Panel completion: {{ $applicantRow['completion_percent'] }}%.</strong>
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
                        &nbsp;&middot;&nbsp; {{ $members->count() }} panel task(s)
                    </h3>

                    <h4 class="subheading">Panel member completion and category counts</h4>
                    <table class="panel-table">
                        <thead>
                            <tr>
                                <th style="width: 24%;">Panel member</th>
                                <th style="width: 11%;">Assignment</th>
                                <th style="width: 15%;">Completion</th>
                                <th style="width: 17%;">Submitted at</th>
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
                                    <td>{{ $member['assigned'] ? 'Assigned' : 'Submission record' }}</td>
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
                                    <td colspan="7" class="number-cell muted">No panel member records are available for this evaluation.</td>
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
