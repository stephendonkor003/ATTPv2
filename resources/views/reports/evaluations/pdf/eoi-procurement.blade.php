<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>EOI Qualification Report - {{ $report['procurement']->reference_no ?? 'Procurement' }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 22px 24px 42px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            color: #243247;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8px;
            line-height: 1.32;
            margin: 0;
        }

        h1,
        h2,
        h3,
        p {
            margin: 0;
        }

        table {
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }

        .document-header {
            background: #0b2138;
            border-bottom: 4px solid #16a34a;
            color: #ffffff;
            margin-bottom: 8px;
            padding: 11px 13px 10px;
        }

        .document-header table,
        .document-header td {
            border: 0;
        }

        .brand-cell {
            vertical-align: middle;
            width: 175px;
        }

        .brand-cell img {
            display: block;
            max-height: 36px;
            max-width: 160px;
        }

        .brand-fallback {
            color: #ffffff;
            font-size: 12px;
            font-weight: 700;
        }

        .heading-cell {
            text-align: right;
            vertical-align: middle;
        }

        .document-tag {
            background: #15803d;
            color: #ffffff;
            display: inline-block;
            font-size: 6.5px;
            font-weight: 700;
            letter-spacing: .08em;
            margin-bottom: 4px;
            padding: 2px 7px;
            text-transform: uppercase;
        }

        .heading-cell h1 {
            color: #ffffff;
            font-size: 15px;
            line-height: 1.15;
        }

        .heading-cell p {
            color: #cbd5e1;
            font-size: 7.5px;
            margin-top: 4px;
        }

        .procurement-title {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-left: 4px solid #16a34a;
            margin-bottom: 7px;
            padding: 7px 9px;
        }

        .procurement-title h2 {
            color: #0b2138;
            font-size: 12.5px;
            line-height: 1.22;
        }

        .procurement-title p {
            color: #64748b;
            font-size: 7.2px;
            margin-top: 2px;
        }

        .meta-table {
            margin-bottom: 7px;
        }

        .meta-table td {
            border: 1px solid #d7e0ea;
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
            font-size: 6.3px;
            font-weight: 700;
            letter-spacing: .05em;
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        .value {
            color: #172033;
            display: block;
            font-size: 8px;
            font-weight: 700;
        }

        .logic-note {
            background: #eff6ff;
            border: 1px solid #93c5fd;
            color: #334155;
            margin-bottom: 8px;
            padding: 6px 8px;
        }

        .logic-note strong {
            color: #1e3a5f;
        }

        .logic-note-title {
            display: block;
            font-size: 6.8px;
            letter-spacing: .05em;
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        .summary-heading {
            color: #0b2138;
            font-size: 10px;
            margin: 0 0 4px;
        }

        .decision-summary {
            border-collapse: separate;
            border-spacing: 5px 0;
            margin-bottom: 8px;
        }

        .decision-summary td {
            border: 1px solid #cbd5e1;
            border-top: 3px solid #64748b;
            padding: 6px 7px;
            vertical-align: top;
            width: 33.333%;
        }

        .decision-summary .is-qualified {
            background: #f0fdf4;
            border-color: #86efac;
            border-top-color: #16a34a;
        }

        .decision-summary .is-stopped {
            background: #fff7f7;
            border-color: #fca5a5;
            border-top-color: #dc2626;
        }

        .decision-summary .is-pending {
            background: #fffbeb;
            border-color: #fcd34d;
            border-top-color: #d97706;
        }

        .summary-count {
            color: #0b2138;
            float: right;
            font-size: 18px;
            font-weight: 700;
            line-height: 1;
            margin-left: 7px;
        }

        .decision-summary h3 {
            color: #0b2138;
            font-size: 8.2px;
            line-height: 1.22;
            margin-bottom: 2px;
        }

        .decision-summary p {
            color: #64748b;
            font-size: 6.7px;
        }

        .register-heading {
            border-bottom: 2px solid #0b2138;
            color: #0b2138;
            font-size: 10.5px;
            margin-bottom: 5px;
            padding-bottom: 3px;
        }

        .register-table {
            font-size: 7.2px;
        }

        .register-table thead {
            display: table-header-group;
        }

        .register-table th,
        .register-table td {
            border: 1px solid #d7e0ea;
            overflow-wrap: break-word;
            padding: 4px 5px;
            vertical-align: middle;
            word-wrap: break-word;
        }

        .register-table th {
            background: #e9eff5;
            color: #334155;
            font-size: 6.3px;
            letter-spacing: .035em;
            line-height: 1.15;
            text-align: left;
            text-transform: uppercase;
        }

        .register-table tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        .register-table tbody tr {
            page-break-inside: avoid;
        }

        .register-table th:nth-child(6),
        .register-table td:nth-child(6) {
            border-left: 2px solid #94a3b8;
        }

        .register-table th:nth-child(7),
        .register-table td:nth-child(7) {
            border-left: 2px solid #cbd5e1;
        }

        .number-cell {
            text-align: center;
        }

        .strong {
            color: #172033;
            font-weight: 700;
        }

        .subline {
            color: #64748b;
            display: block;
            font-size: 6.3px;
            line-height: 1.22;
            margin-top: 2px;
        }

        .decision-counts {
            white-space: nowrap;
        }

        .decision-count {
            display: inline-block;
            font-size: 6.3px;
            font-weight: 700;
            margin: 0 2px 1px 0;
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

        .badge {
            border: 1px solid transparent;
            display: inline-block;
            font-size: 6.2px;
            font-weight: 700;
            letter-spacing: .02em;
            line-height: 1.2;
            padding: 2px 4px;
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

        .route-qualified {
            color: #166534;
        }

        .route-stopped {
            color: #991b1b;
        }

        .route-pending {
            color: #475569;
        }

        .report-note {
            color: #64748b;
            font-size: 6.5px;
            margin-top: 6px;
        }

        .pdf-footer {
            border-top: 1px solid #16a34a;
            bottom: -31px;
            color: #64748b;
            font-size: 6.5px;
            height: 22px;
            left: 0;
            padding-top: 6px;
            position: fixed;
            right: 0;
        }

        .pdf-footer td {
            border: 0;
            padding: 0;
            width: 33.333%;
        }

        .footer-center {
            text-align: center;
        }

        .footer-page-slot {
            text-align: right;
        }
    </style>
</head>
<body data-layout-revision="compact-v2">
    @php
        $platformName = $platformName ?? 'Africa Think Tank Platform';
        $platformUrl = $platformUrl ?? rtrim((string) config('app.url'), '/');
        $procurement = $report['procurement'];
        $applicants = collect($report['applicants'] ?? []);
        $stats = $report['stats'] ?? [];
        $generatedAt = $report['generated_at'] ?? now();

        $decisionView = function (array $applicantRow): array {
            $panelComplete = (bool) ($applicantRow['panel_complete'] ?? false);
            $outcomeCode = data_get($applicantRow, 'outcome.code', 'pending');

            if (! $panelComplete) {
                return [
                    'group' => 'pending',
                    'label' => 'Awaiting Panel',
                    'tone' => 'pending',
                    'workflow' => 'No final routing',
                    'workflow_note' => 'Held until every active panel task is complete.',
                    'signal' => $outcomeCode === 'not_qualified'
                        ? 'NQ recorded; panel incomplete.'
                        : 'Panel evaluation in progress.',
                ];
            }

            if (($applicantRow['can_advance'] ?? false) === true
                && in_array($outcomeCode, ['fully_qualified', 'average_qualified'], true)) {
                return [
                    'group' => 'qualified',
                    'label' => data_get($applicantRow, 'outcome.label', 'Qualified'),
                    'tone' => data_get($applicantRow, 'outcome.tone', 'success'),
                    'workflow' => 'Technical Evaluation',
                    'workflow_note' => 'Final panel decision; approved to advance.',
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
                'workflow' => 'No final routing',
                'workflow_note' => 'A valid final panel decision is still required.',
                'signal' => null,
            ];
        };

        $qualifiedApplicants = $applicants
            ->filter(fn (array $row): bool => $decisionView($row)['group'] === 'qualified')
            ->values();
        $finalNotQualifiedApplicants = $applicants
            ->filter(fn (array $row): bool => $decisionView($row)['group'] === 'not-qualified')
            ->values();
        $awaitingPanelApplicants = $applicants
            ->filter(fn (array $row): bool => $decisionView($row)['group'] === 'pending')
            ->values();

        $badgeClass = function (string $tone): string {
            return match ($tone) {
                'success' => 'badge-success',
                'warning' => 'badge-warning',
                'danger' => 'badge-danger',
                default => 'badge-pending',
            };
        };

        $routeClass = function (string $group): string {
            return match ($group) {
                'qualified' => 'route-qualified',
                'not-qualified' => 'route-stopped',
                default => 'route-pending',
            };
        };
    @endphp

    <div class="pdf-footer">
        <table>
            <tr>
                <td>{{ $platformName }}</td>
                <td class="footer-center">{{ $platformUrl }}</td>
                <td class="footer-page-slot">&nbsp;</td>
            </tr>
        </table>
    </div>

    <header class="document-header">
        <table>
            <tr>
                <td class="brand-cell">
                    @if (! empty($logoDataUri))
                        <img src="{{ $logoDataUri }}" alt="{{ $platformName }}">
                    @else
                        <span class="brand-fallback">{{ $platformName }}</span>
                    @endif
                </td>
                <td class="heading-cell">
                    <span class="document-tag">Official decision record &middot; Compact summary</span>
                    <h1>Expression of Interest Qualification Report</h1>
                    <p>{{ $procurement->reference_no ?? 'No reference' }}</p>
                </td>
            </tr>
        </table>
    </header>

    <section class="procurement-title">
        <h2>{{ $procurement->title ?? 'Procurement' }}</h2>
        <p>Consolidated active-panel qualification and Technical Evaluation routing summary.</p>
    </section>

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
                <span class="label">Active panel</span>
                <span class="value">{{ number_format($stats['panel_members'] ?? 0) }} member(s)</span>
            </td>
            <td>
                <span class="label">Generated</span>
                <span class="value">{{ $generatedAt->format('d M Y, H:i') }}</span>
            </td>
        </tr>
    </table>

    <div class="logic-note">
        <strong class="logic-note-title">Active-panel decision rule</strong>
        <strong>Only the currently assigned panel is counted.</strong> A workflow outcome becomes final only after every active panel
        task is complete. Fully Qualified and Average Qualified applicants advance; a completed panel with any Not Qualified
        decision does not advance. An incomplete panel remains Awaiting Panel, including when an early NQ signal exists.
    </div>

    <h2 class="summary-heading">Panel Decision Summary</h2>
    <table class="decision-summary" aria-label="Panel decision summary">
        <tr>
            <td class="is-qualified" data-summary-outcome="qualified">
                <span class="summary-count">{{ number_format($qualifiedApplicants->count()) }}</span>
                <h3>Qualified Applicants &mdash; Advancing to Technical Evaluation</h3>
                <p>Final panel-complete outcomes approved to advance.</p>
            </td>
            <td class="is-stopped" data-summary-outcome="not-qualified">
                <span class="summary-count">{{ number_format($finalNotQualifiedApplicants->count()) }}</span>
                <h3>Not Qualified Applicants &mdash; Do Not Advance</h3>
                <p>Final not qualified decisions from completed panels.</p>
            </td>
            <td class="is-pending" data-summary-outcome="pending">
                <span class="summary-count">{{ number_format($awaitingPanelApplicants->count()) }}</span>
                <h3>Awaiting Panel Completion</h3>
                <p>No final outcome or workflow routing yet.</p>
            </td>
        </tr>
    </table>

    <h2 class="register-heading">Applicant Outcome Register</h2>
    <table class="register-table">
        <colgroup>
            <col style="width: 3%;">
            <col style="width: 13%;">
            <col style="width: 22%;">
            <col style="width: 15%;">
            <col style="width: 13%;">
            <col style="width: 15%;">
            <col style="width: 19%;">
        </colgroup>
        <thead>
            <tr>
                <th>#</th>
                <th>Submission</th>
                <th>Applicant</th>
                <th>Active panel completion</th>
                <th>Decision signals</th>
                <th>Final outcome</th>
                <th>Workflow decision</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($applicants as $index => $applicantRow)
                @php
                    $applicant = $applicantRow['applicant'];
                    $decision = $decisionView($applicantRow);
                @endphp
                <tr data-summary-outcome="{{ $decision['group'] }}">
                    <td class="number-cell">{{ $index + 1 }}</td>
                    <td><span class="strong">{{ $applicant->procurement_submission_code ?? 'N/A' }}</span></td>
                    <td>
                        <span class="strong">{{ $applicant->display_name }}</span>
                        @if ($applicant->submitter?->email)
                            <span class="subline">{{ $applicant->submitter->email }}</span>
                        @endif
                    </td>
                    <td>
                        <span class="strong">{{ $applicantRow['completed_tasks'] }}/{{ $applicantRow['expected_tasks'] }} tasks</span>
                        <span class="subline">
                            {{ $applicantRow['completed_evaluators'] }}/{{ $applicantRow['expected_evaluators'] }} evaluator(s)
                            &middot; {{ $applicantRow['completion_percent'] }}%
                        </span>
                    </td>
                    <td>
                        <span class="decision-counts">
                            <span class="decision-count decision-count-q">Q {{ $applicantRow['counts']['qualified'] }}</span>
                            <span class="decision-count decision-count-aq">AQ {{ $applicantRow['counts']['average_qualified'] }}</span>
                            <span class="decision-count decision-count-nq">NQ {{ $applicantRow['counts']['not_qualified'] }}</span>
                        </span>
                    </td>
                    <td>
                        <span class="badge {{ $badgeClass($decision['tone']) }}">{{ $decision['label'] }}</span>
                        @if ($decision['signal'])
                            <span class="subline">{{ $decision['signal'] }}</span>
                        @endif
                    </td>
                    <td class="{{ $routeClass($decision['group']) }}">
                        <span class="strong {{ $routeClass($decision['group']) }}">{{ $decision['workflow'] }}</span>
                        <span class="subline">{{ $decision['workflow_note'] }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="number-cell">No reportable EOI applicants were found for this procurement.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="report-note">
        Decision key: Q = Qualified, AQ = Average Qualified, NQ = Not Qualified. This consolidated PDF intentionally mirrors
        the collapsed web summary; detailed evaluator evidence remains available in the secured online report.
    </p>
</body>
</html>
