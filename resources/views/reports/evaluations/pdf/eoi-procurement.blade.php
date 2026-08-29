<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>EOI Qualification Report - {{ $report['procurement']->reference_no ?? 'Procurement' }}</title>
    <style>
        @page { size: A4 landscape; margin: 20px 24px 42px; }
        * { box-sizing: border-box; }
        body { color: #203047; font-family: DejaVu Sans, Arial, sans-serif; font-size: 7.4px; line-height: 1.35; margin: 0; }
        h1, h2, h3, h4, p { margin: 0; }
        table { border-collapse: collapse; table-layout: fixed; width: 100%; }

        .pdf-footer { border-top: 1px solid #2f9e62; bottom: -30px; color: #66758a; font-size: 6.2px; height: 20px; left: 0; padding-top: 5px; position: fixed; right: 0; }
        .pdf-footer td { border: 0; padding: 0; width: 33.333%; }
        .pdf-footer .center { text-align: center; }
        .pdf-footer .page { text-align: right; }

        .document-header { background: #0b2138; border-bottom: 4px solid #2f9e62; color: #fff; margin-bottom: 8px; padding: 11px 13px; }
        .document-header table, .document-header td { border: 0; }
        .brand-cell { vertical-align: middle; width: 185px; }
        .brand-cell img { display: block; max-height: 38px; max-width: 168px; }
        .brand-fallback { color: #fff; font-size: 11px; font-weight: 700; }
        .heading-cell { text-align: right; vertical-align: middle; }
        .document-tag { background: #197447; color: #fff; display: inline-block; font-size: 6px; font-weight: 700; letter-spacing: .09em; margin-bottom: 3px; padding: 2px 6px; text-transform: uppercase; }
        .heading-cell h1 { color: #fff; font-size: 15px; line-height: 1.12; }
        .heading-cell p { color: #cbd7e5; font-size: 7px; margin-top: 3px; }

        .procurement-banner { background: #f5f8fb; border: 1px solid #cad6e3; border-left: 4px solid #2f9e62; margin-bottom: 7px; padding: 7px 9px; }
        .procurement-banner h2 { color: #102840; font-size: 12px; line-height: 1.25; }
        .procurement-banner p { color: #617286; font-size: 6.8px; margin-top: 2px; }
        .meta-table { margin-bottom: 7px; }
        .meta-table td { border: 1px solid #d7e0e9; padding: 5px 7px; vertical-align: top; width: 25%; }
        .meta-table td:nth-child(even) { background: #f8fafc; }
        .label { color: #6a788a; display: block; font-size: 5.9px; font-weight: 700; letter-spacing: .06em; margin-bottom: 2px; text-transform: uppercase; }
        .value { color: #192b3e; display: block; font-size: 7.7px; font-weight: 700; }

        .logic-note { background: #eef7ff; border: 1px solid #acd2ef; color: #33465a; margin-bottom: 8px; padding: 6px 8px; }
        .logic-note strong { color: #153f68; }
        .logic-note-title { display: block; font-size: 6px; letter-spacing: .06em; margin-bottom: 2px; text-transform: uppercase; }
        .section-title { border-bottom: 2px solid #183551; color: #102840; font-size: 10.5px; margin: 10px 0 5px; padding-bottom: 3px; }
        .section-kicker { color: #6a788a; font-size: 6.5px; margin: -2px 0 5px; }

        .summary-table { border-collapse: separate; border-spacing: 4px 0; margin-bottom: 7px; }
        .summary-table td { border: 1px solid #d4e0e8; border-top: 3px solid #64748b; padding: 6px 7px; vertical-align: top; width: 20%; }
        .summary-table .summary-proceeding { background: #effbf4; border-color: #a7dfba; border-top-color: #1e9b56; }
        .summary-table .summary-not-proceeding { background: #f5f7fa; border-color: #cad4df; border-top-color: #64748b; }
        .summary-table .summary-qualified { background: #f0f8f2; border-color: #b8dec5; border-top-color: #278150; }
        .summary-table .summary-stopped { background: #fff5f5; border-color: #f2bbbb; border-top-color: #c54141; }
        .summary-table .summary-pending { background: #fffbed; border-color: #f4d580; border-top-color: #c98813; }
        .summary-count { color: #162d45; float: right; font-size: 18px; font-weight: 700; line-height: 1; margin-left: 5px; }
        .summary-table h3 { color: #16334e; font-size: 7.5px; line-height: 1.2; margin-bottom: 2px; }
        .summary-table p { color: #657589; font-size: 6.1px; }

        .ranking-rule { background: #fffbea; border: 1px solid #eadb98; color: #4f4a2b; margin-bottom: 5px; padding: 5px 7px; }
        .ranking-rule strong { color: #5d4c0d; }
        .register-table, .evidence-table, .task-table, .workflow-table, .communication-table { font-size: 6.55px; }
        .register-table thead, .evidence-table thead, .task-table thead, .workflow-table thead, .communication-table thead { display: table-header-group; }
        .register-table th, .register-table td, .evidence-table th, .evidence-table td, .task-table th, .task-table td, .workflow-table th, .workflow-table td, .communication-table th, .communication-table td { border: 1px solid #d5dfe8; overflow-wrap: break-word; padding: 3px 4px; vertical-align: top; word-wrap: break-word; }
        .register-table th, .evidence-table th, .task-table th, .workflow-table th, .communication-table th { background: #eaf0f5; color: #33485d; font-size: 5.8px; letter-spacing: .035em; line-height: 1.15; text-align: left; text-transform: uppercase; }
        .register-table tbody tr:nth-child(even) td, .evidence-table tbody tr:nth-child(even) td, .task-table tbody tr:nth-child(even) td, .workflow-table tbody tr:nth-child(even) td, .communication-table tbody tr:nth-child(even) td { background: #fafcfd; }
        .register-table tbody tr, .evidence-table tbody tr, .task-table tbody tr, .workflow-table tbody tr, .communication-table tbody tr { page-break-inside: avoid; }

        .number { text-align: center; }
        .strong { color: #182c42; font-weight: 700; }
        .subline { color: #697a8d; display: block; font-size: 5.9px; line-height: 1.22; margin-top: 1px; }
        .small-note { color: #6a788a; font-size: 6px; }
        .rank-medal { background: #edf7ef; border: 1px solid #b3d8bf; border-radius: 10px; color: #186b3c; display: inline-block; font-size: 7px; font-weight: 700; min-width: 26px; padding: 2px 4px; text-align: center; }
        .rank-medal--1 { background: #fff5cf; border-color: #e8c853; color: #8a6600; }
        .rank-medal--2 { background: #f0f2f5; border-color: #bac3cc; color: #58636f; }
        .rank-medal--3 { background: #fff0e9; border-color: #dda477; color: #8a4526; }
        .rank-medal--outside { background: #f1f5f9; border-color: #cbd5e1; color: #526274; }
        .badge { border: 1px solid transparent; display: inline-block; font-size: 5.8px; font-weight: 700; line-height: 1.15; padding: 2px 4px; text-transform: uppercase; }
        .badge-proceeding { background: #dcf7e5; border-color: #92d7a9; color: #146c3b; }
        .badge-not-proceeding { background: #edf1f5; border-color: #c1ccd7; color: #46576a; }
        .badge-qualified { background: #e2f7e8; border-color: #a2d9b1; color: #147342; }
        .badge-average { background: #fff3c9; border-color: #eed078; color: #89610b; }
        .badge-not-qualified { background: #ffe3e3; border-color: #eeb0b0; color: #9d2525; }
        .badge-pending { background: #e9eef3; border-color: #c7d1db; color: #526273; }
        .badge-task-complete { background: #e2f7e8; border-color: #a2d9b1; color: #147342; }
        .badge-task-pending { background: #fff3c9; border-color: #eed078; color: #89610b; }
        .badge-task-incomplete { background: #fff0dc; border-color: #edc189; color: #8b5b16; }
        .signal { white-space: nowrap; }
        .signal-q { color: #166534; font-weight: 700; }
        .signal-aq { color: #a16207; font-weight: 700; }
        .signal-nq { color: #a12727; font-weight: 700; }
        .workflow-proceeding { color: #146c3b; }
        .workflow-not-proceeding { color: #46576a; }
        .workflow-stopped { color: #9d2525; }
        .workflow-pending { color: #89610b; }

        .page-break-before { page-break-before: always; }
        .evidence-applicant { page-break-inside: avoid; margin: 8px 0 12px; }
        .evidence-applicant + .evidence-applicant { border-top: 2px solid #d7e1e9; padding-top: 9px; }
        .applicant-evidence-heading { background: #f4f7fa; border: 1px solid #cbd8e3; margin-bottom: 4px; padding: 5px 7px; }
        .applicant-evidence-heading td { border: 0; padding: 0; vertical-align: middle; }
        .applicant-evidence-heading .right { text-align: right; width: 36%; }
        .applicant-evidence-heading h3 { color: #152d45; font-size: 8.3px; }
        .applicant-evidence-heading p { color: #647488; font-size: 6px; margin-top: 1px; }
        .template-block { margin: 5px 0 8px; page-break-inside: avoid; }
        .template-heading { background: #173a58; color: #fff; padding: 4px 6px; }
        .template-heading h4 { color: #fff; font-size: 7.2px; }
        .template-heading p { color: #d4e2ed; font-size: 5.8px; margin-top: 1px; }
        .task-table, .evidence-table { margin-top: 3px; }
        .criterion-title { color: #253b52; font-weight: 700; }
        .criterion-section { color: #647488; display: block; font-size: 5.7px; font-weight: 700; text-transform: uppercase; }
        .comment { color: #45566a; white-space: pre-line; }
        .empty-panel { background: #f8fafc; border: 1px dashed #b8c6d3; color: #68798c; padding: 8px; text-align: center; }
        .workflow-summary { page-break-before: always; }
        .workflow-note { color: #647488; font-size: 6.3px; margin: -2px 0 5px; }
        .rule-list { color: #526477; font-size: 5.8px; line-height: 1.25; margin-top: 2px; }
        .candidate-list { margin-top: 5px; }
        .candidate-list h3 { color: #203a54; font-size: 7.5px; margin-bottom: 3px; }
        .status-plain { font-weight: 700; text-transform: capitalize; }
        .status-disqualified { color: #9d2525; }
        .status-qualified, .status-submitted { color: #146c3b; }
        .status-late, .status-under-review { color: #89610b; }
    </style>
</head>
<body data-layout-revision="detailed-v3">
    @php
        $platformName = $platformName ?? 'Africa Think Tank Platform';
        $platformUrl = $platformUrl ?? rtrim((string) config('app.url'), '/');
        $procurement = $report['procurement'];
        $applicants = collect($report['applicants'] ?? []);
        $rankedQualifiedApplicants = collect($report['qualified_ranking'] ?? [])->sortBy('qualification_rank')->values();
        $qualifiedShortlist = collect($report['qualified_shortlist'] ?? $rankedQualifiedApplicants->where('within_qualified_shortlist', true))->values();
        $qualifiedOutsideShortlist = collect($report['qualified_outside_shortlist'] ?? $rankedQualifiedApplicants->where('within_qualified_shortlist', false))->values();
        $stats = $report['stats'] ?? [];
        $generatedAt = $report['generated_at'] ?? now();
        $technicalProposalRounds = collect($technicalProposalRounds ?? []);
        $communications = collect($communications ?? []);
        $finalNotQualifiedApplicants = $applicants->filter(fn (array $row): bool => (bool) ($row['panel_complete'] ?? false) && data_get($row, 'outcome.code') === 'not_qualified')->values();
        $awaitingPanelApplicants = $applicants->filter(fn (array $row): bool => ! (bool) ($row['panel_complete'] ?? false))->values();
        $ordinal = function (int $rank): string {
            $lastTwo = $rank % 100;
            $suffix = in_array($lastTwo, [11, 12, 13], true) ? 'th' : match ($rank % 10) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' };
            return $rank.$suffix;
        };
        $outcomeBadge = fn (string $code): string => match ($code) { 'fully_qualified' => 'badge-qualified', 'average_qualified' => 'badge-average', 'not_qualified' => 'badge-not-qualified', default => 'badge-pending' };
        $workflowClass = fn (string $code): string => match ($code) { 'proceeding' => 'workflow-proceeding', 'not_proceeding' => 'workflow-not-proceeding', 'does_not_advance' => 'workflow-stopped', default => 'workflow-pending' };
        $taskBadge = function (array $member): array {
            if (($member['task_complete'] ?? false) === true) return ['Complete', 'badge-task-complete'];
            return ($member['submitted'] ?? false) === true ? ['Missing decisions', 'badge-task-incomplete'] : ['Awaiting submission', 'badge-task-pending'];
        };
        $formatRoundDate = fn ($date, ?string $timezone = null): string => $date ? $date->timezone($timezone ?: config('app.timezone'))->format('d M Y, H:i') : 'No deadline';
    @endphp

    <div class="pdf-footer"><table><tr><td>{{ $platformName }}</td><td class="center">{{ $platformUrl }}</td><td class="page">&nbsp;</td></tr></table></div>
    <header class="document-header"><table><tr>
        <td class="brand-cell">@if (! empty($logoDataUri))<img src="{{ $logoDataUri }}" alt="{{ $platformName }}">@else<span class="brand-fallback">{{ $platformName }}</span>@endif</td>
        <td class="heading-cell"><span class="document-tag">Official internal report &middot; Complete decision and evidence register</span><h1>Expression of Interest Qualification Report</h1><p>{{ $procurement->reference_no ?? 'No reference' }}</p></td>
    </tr></table></header>

    <section class="procurement-banner"><h2>{{ $procurement->title ?? 'Procurement' }}</h2><p>Consolidated active-panel qualification, current shortlist progression, evaluator evidence, and read-only post-qualification workflow snapshot.</p></section>
    <table class="meta-table"><tr>
        <td><span class="label">Procurement reference</span><span class="value">{{ $procurement->reference_no ?? 'N/A' }}</span></td>
        <td><span class="label">Evaluation method</span><span class="value">Expression of Interest</span></td>
        <td><span class="label">Active panel</span><span class="value">{{ number_format($stats['panel_members'] ?? 0) }} member(s)</span></td>
        <td><span class="label">Generated</span><span class="value">{{ $generatedAt->format('d M Y, H:i') }}</span></td>
    </tr></table>
    <div class="logic-note"><strong class="logic-note-title">Active-panel and shortlist decision rule</strong><strong>Only currently assigned panel tasks are counted.</strong> A panel outcome is final only when every active task is complete. Qualified outcomes remain distinct from the current shortlist: ranks 1&ndash;{{ \App\Services\EoiQualificationService::QUALIFIED_SHORTLIST_LIMIT }} proceed; rank {{ \App\Services\EoiQualificationService::QUALIFIED_SHORTLIST_LIMIT + 1 }} onward is shown as not proceeding. This report does not rewrite published proposal-round history.</div>

    <h2 class="section-title">Decision Summary</h2>
    <table class="summary-table" aria-label="EOI decision summary"><tr>
        <td class="summary-proceeding"><span class="summary-count">{{ number_format($qualifiedShortlist->count()) }}</span><h3>Proceeding</h3><p>Current top {{ \App\Services\EoiQualificationService::QUALIFIED_SHORTLIST_LIMIT }} qualified ranks.</p></td>
        <td class="summary-not-proceeding"><span class="summary-count">{{ number_format($qualifiedOutsideShortlist->count()) }}</span><h3>Not Proceeding</h3><p>Panel-qualified but outside the current top {{ \App\Services\EoiQualificationService::QUALIFIED_SHORTLIST_LIMIT }}.</p></td>
        <td class="summary-qualified"><span class="summary-count">{{ number_format($rankedQualifiedApplicants->count()) }}</span><h3>Panel Qualified</h3><p>Fully and Average Qualified final panel outcomes.</p></td>
        <td class="summary-stopped"><span class="summary-count">{{ number_format($finalNotQualifiedApplicants->count()) }}</span><h3>Not Qualified</h3><p>Final panel decisions that stop progression.</p></td>
        <td class="summary-pending"><span class="summary-count">{{ number_format($awaitingPanelApplicants->count()) }}</span><h3>Awaiting Panel</h3><p>Active panel tasks remain incomplete.</p></td>
    </tr></table>

    <h2 class="section-title">Qualified Applicant Ranking</h2>
    <p class="section-kicker">Every panel-qualified applicant is shown in rank order. A ranking position and the current shortlist decision are both visible.</p>
    <div class="ranking-rule"><strong>Ranking rule:</strong> Fully Qualified applicants come first; within the same outcome, the higher share and count of Qualified decisions comes first. Exact ties use applicant name and submission code.</div>
    <table class="register-table" aria-label="Qualified applicant ranking"><colgroup><col style="width: 6%;"><col style="width: 12%;"><col style="width: 18%;"><col style="width: 11%;"><col style="width: 12%;"><col style="width: 10%;"><col style="width: 12%;"><col style="width: 19%;"></colgroup>
        <thead><tr><th>Rank</th><th>Current shortlist</th><th>Applicant</th><th>Submission</th><th>Final EOI outcome</th><th>Decision signals</th><th>Active panel tasks</th><th>Current workflow decision</th></tr></thead>
        <tbody>
            @forelse ($rankedQualifiedApplicants as $rankedRow)
                @php
                    $rank = (int) ($rankedRow['qualification_rank'] ?? $loop->iteration);
                    $isProceeding = (bool) ($rankedRow['within_qualified_shortlist'] ?? false);
                    $progression = $rankedRow['progression'] ?? [];
                    $applicant = $rankedRow['applicant'];
                    $outcome = $rankedRow['outcome'];
                @endphp
                <tr data-qualified-rank="{{ $rank }}" data-qualified-progression="{{ $isProceeding ? 'proceeding' : 'not-proceeding' }}">
                    <td class="number"><span class="rank-medal rank-medal--{{ $rank <= 3 ? $rank : ($isProceeding ? 'shortlist' : 'outside') }}">&#9733; {{ $rank }}</span><span class="subline">{{ $ordinal($rank) }}</span></td>
                    <td><span class="badge {{ $isProceeding ? 'badge-proceeding' : 'badge-not-proceeding' }}">{{ $isProceeding ? 'Proceeding' : 'Not proceeding' }}</span><span class="subline">{{ $isProceeding ? 'Within current top '.\App\Services\EoiQualificationService::QUALIFIED_SHORTLIST_LIMIT : 'Outside current top '.\App\Services\EoiQualificationService::QUALIFIED_SHORTLIST_LIMIT }}</span></td>
                    <td><span class="strong">{{ $applicant->display_name }}</span>@if ($applicant->submitter?->email)<span class="subline">{{ $applicant->submitter->email }}</span>@endif</td>
                    <td><span class="strong">{{ $applicant->procurement_submission_code ?: 'N/A' }}</span></td>
                    <td><span class="badge {{ $outcomeBadge($outcome['code'] ?? 'pending') }}">{{ $outcome['label'] ?? 'Awaiting Panel' }}</span></td>
                    <td class="signal"><span class="signal-q">Q {{ $rankedRow['counts']['qualified'] ?? 0 }}</span> &middot; <span class="signal-aq">AQ {{ $rankedRow['counts']['average_qualified'] ?? 0 }}</span> &middot; <span class="signal-nq">NQ {{ $rankedRow['counts']['not_qualified'] ?? 0 }}</span></td>
                    <td><span class="strong">{{ $rankedRow['completed_tasks'] ?? 0 }}/{{ $rankedRow['expected_tasks'] ?? 0 }} tasks</span><span class="subline">{{ $rankedRow['completed_evaluators'] ?? 0 }}/{{ $rankedRow['expected_evaluators'] ?? 0 }} evaluator(s)</span></td>
                    <td class="{{ $workflowClass($progression['code'] ?? ($isProceeding ? 'proceeding' : 'not_proceeding')) }}"><span class="strong">{{ $progression['workflow'] ?? ($isProceeding ? 'Proceeding to Technical Evaluation' : 'Not proceeding') }}</span><span class="subline">{{ $progression['note'] ?? '' }}</span></td>
                </tr>
            @empty
                <tr><td colspan="8" class="number">No panel-complete qualified applicants are available to rank.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2 class="section-title">Applicant Outcome Register</h2>
    <p class="section-kicker">All applicants, including final Not Qualified and panel-incomplete records, use the same current workflow decision as the web report.</p>
    <table class="register-table" aria-label="Applicant outcome register"><colgroup><col style="width: 3%;"><col style="width: 15%;"><col style="width: 11%;"><col style="width: 12%;"><col style="width: 11%;"><col style="width: 12%;"><col style="width: 14%;"><col style="width: 22%;"></colgroup>
        <thead><tr><th>#</th><th>Applicant</th><th>Submission</th><th>Active panel completion</th><th>Decision signals</th><th>Final EOI outcome</th><th>Current shortlist decision</th><th>Final outcome workflow decision</th></tr></thead>
        <tbody>
            @forelse ($applicants as $index => $row)
                @php
                    $applicant = $row['applicant'];
                    $outcome = $row['outcome'];
                    $progression = $row['progression'] ?? [];
                    $progressionCode = $progression['code'] ?? 'awaiting_decision';
                    $rank = $row['qualification_rank'] ?? null;
                @endphp
                <tr data-summary-outcome="{{ $outcome['code'] ?? 'pending' }}" data-shortlist-progression="{{ $progressionCode }}">
                    <td class="number">{{ $index + 1 }}</td>
                    <td><span class="strong">{{ $applicant->display_name }}</span>@if ($applicant->submitter?->email)<span class="subline">{{ $applicant->submitter->email }}</span>@endif</td>
                    <td><span class="strong">{{ $applicant->procurement_submission_code ?: 'N/A' }}</span></td>
                    <td><span class="strong">{{ $row['completed_tasks'] ?? 0 }}/{{ $row['expected_tasks'] ?? 0 }} tasks</span><span class="subline">{{ $row['completed_evaluators'] ?? 0 }}/{{ $row['expected_evaluators'] ?? 0 }} evaluator(s) &middot; {{ $row['completion_percent'] ?? 0 }}%</span></td>
                    <td class="signal"><span class="signal-q">Q {{ $row['counts']['qualified'] ?? 0 }}</span> &middot; <span class="signal-aq">AQ {{ $row['counts']['average_qualified'] ?? 0 }}</span> &middot; <span class="signal-nq">NQ {{ $row['counts']['not_qualified'] ?? 0 }}</span></td>
                    <td><span class="badge {{ $outcomeBadge($outcome['code'] ?? 'pending') }}">{{ $outcome['label'] ?? 'Awaiting Panel' }}</span><span class="subline">{{ $outcome['description'] ?? '' }}</span></td>
                    <td>@if ($rank)<span class="rank-medal rank-medal--{{ $rank <= 3 ? $rank : (($row['within_qualified_shortlist'] ?? false) ? 'shortlist' : 'outside') }}">&#9733; {{ $rank }}</span>@endif <span class="badge {{ $progressionCode === 'proceeding' ? 'badge-proceeding' : ($progressionCode === 'not_proceeding' ? 'badge-not-proceeding' : 'badge-pending') }}">{{ $progression['label'] ?? 'Awaiting decision' }}</span></td>
                    <td class="{{ $workflowClass($progressionCode) }}"><span class="strong">{{ $progression['workflow'] ?? ($row['next_stage'] ?? 'No final routing') }}</span><span class="subline">{{ $progression['note'] ?? '' }}</span></td>
                </tr>
            @empty
                <tr><td colspan="8" class="number">No reportable EOI applicants were found for this procurement.</td></tr>
            @endforelse
        </tbody>
    </table>

    <section class="page-break-before" aria-label="Applicant evaluation evidence appendix">
        <h2 class="section-title">Applicant Evaluation Evidence Appendix</h2>
        <p class="section-kicker">The detailed evaluator evidence below matches the applicant records available in the web report. This is an internal report; evaluator names and comments are retained.</p>
        @forelse ($applicants as $row)
            @php
                $applicant = $row['applicant'];
                $outcome = $row['outcome'];
                $progression = $row['progression'] ?? [];
                $progressionCode = $progression['code'] ?? 'awaiting_decision';
            @endphp
            <section class="evidence-applicant" data-evidence-applicant="{{ $applicant->id }}">
                <table class="applicant-evidence-heading"><tr>
                    <td><h3>{{ $applicant->display_name }}</h3><p>{{ $applicant->procurement_submission_code ?: 'No submission code' }} &middot; {{ $row['completed_tasks'] ?? 0 }}/{{ $row['expected_tasks'] ?? 0 }} active panel tasks complete &middot; Q {{ $row['counts']['qualified'] ?? 0 }} / AQ {{ $row['counts']['average_qualified'] ?? 0 }} / NQ {{ $row['counts']['not_qualified'] ?? 0 }}</p></td>
                    <td class="right {{ $workflowClass($progressionCode) }}"><span class="badge {{ $outcomeBadge($outcome['code'] ?? 'pending') }}">{{ $outcome['label'] ?? 'Awaiting Panel' }}</span> <span class="badge {{ $progressionCode === 'proceeding' ? 'badge-proceeding' : ($progressionCode === 'not_proceeding' ? 'badge-not-proceeding' : 'badge-pending') }}">{{ $progression['label'] ?? 'Awaiting decision' }}</span><span class="subline">{{ $progression['workflow'] ?? ($row['next_stage'] ?? '') }}</span></td>
                </tr></table>

                @forelse (collect($row['evaluation_reports'] ?? []) as $evaluationReport)
                    @php
                        $evaluation = $evaluationReport['evaluation'];
                        $members = collect($evaluationReport['members'] ?? []);
                        $criteriaRows = collect($evaluationReport['criteria'] ?? []);
                    @endphp
                    <section class="template-block">
                        <div class="template-heading"><h4>{{ $evaluation->name }}</h4><p>{{ $evaluation->description ?: 'EOI evaluation template' }} &middot; {{ $criteriaRows->count() }} criteria &middot; {{ $members->count() }} assigned evaluator(s)</p></div>
                        <table class="task-table" aria-label="Evaluator completion for {{ $evaluation->name }}"><colgroup><col style="width: 25%;"><col style="width: 16%;"><col style="width: 17%;"><col style="width: 14%;"><col style="width: 14%;"><col style="width: 14%;"></colgroup><thead><tr><th>Evaluator</th><th>Task status</th><th>Submitted at</th><th>Q</th><th>AQ</th><th>NQ</th></tr></thead><tbody>
                            @forelse ($members as $member)
                                @php
                                    [$taskStatus, $taskBadgeClass] = $taskBadge($member);
                                @endphp
                                <tr><td><span class="strong">{{ $member['name'] ?? 'Unassigned evaluator' }}</span>@if ($member['email'] ?? null)<span class="subline">{{ $member['email'] }}</span>@endif @unless ($member['assigned'] ?? false)<span class="subline">Imported record; original assignment unavailable.</span>@endunless</td><td><span class="badge {{ $taskBadgeClass }}">{{ $taskStatus }}</span></td><td>{{ $member['submitted_at']?->format('d M Y, H:i') ?? 'Not submitted' }}</td><td class="signal-q">{{ $member['counts']['qualified'] ?? 0 }}</td><td class="signal-aq">{{ $member['counts']['average_qualified'] ?? 0 }}</td><td class="signal-nq">{{ $member['counts']['not_qualified'] ?? 0 }}</td></tr>
                            @empty
                                <tr><td colspan="6" class="number">No evaluator records are available for this template.</td></tr>
                            @endforelse
                        </tbody></table>
                        <table class="evidence-table" aria-label="Criterion decisions for {{ $evaluation->name }}"><colgroup><col style="width: 24%;"><col style="width: 15%;"><col style="width: 17%;"><col style="width: 14%;"><col style="width: 30%;"></colgroup><thead><tr><th>Criterion</th><th>Panel result</th><th>Evaluator</th><th>Decision</th><th>Evaluator comment</th></tr></thead><tbody>
                            @forelse ($criteriaRows as $criterionRow)
                                @php
                                    $criterion = $criterionRow['criterion'];
                                    $criterionOutcome = $criterionRow['outcome'];
                                    $criterionMembers = $members;
                                @endphp
                                @if ($criterionMembers->isEmpty())
                                    <tr><td><span class="criterion-section">{{ $criterionRow['section']->name }}</span><span class="criterion-title">{{ $criterion->name }}</span>@if ($criterion->description)<span class="subline">{{ $criterion->description }}</span>@endif</td><td><span class="badge {{ $outcomeBadge($criterionOutcome['code'] ?? 'pending') }}">{{ $criterionOutcome['label'] ?? 'Awaiting Panel' }}</span><span class="subline">Q {{ $criterionRow['counts']['qualified'] ?? 0 }} &middot; AQ {{ $criterionRow['counts']['average_qualified'] ?? 0 }} &middot; NQ {{ $criterionRow['counts']['not_qualified'] ?? 0 }}</span></td><td colspan="3" class="small-note">No evaluator record is available.</td></tr>
                                @else
                                    @foreach ($criterionMembers as $member)
                                        @php
                                            $assessment = collect($criterionRow['assessments'] ?? [])
                                                ->firstWhere('member_key', $member['key'] ?? null);
                                        @endphp
                                        <tr><td><span class="criterion-section">{{ $criterionRow['section']->name }}</span><span class="criterion-title">{{ $criterion->name }}</span>@if ($criterion->description)<span class="subline">{{ $criterion->description }}</span>@endif</td><td><span class="badge {{ $outcomeBadge($criterionOutcome['code'] ?? 'pending') }}">{{ $criterionOutcome['label'] ?? 'Awaiting Panel' }}</span><span class="subline">Q {{ $criterionRow['counts']['qualified'] ?? 0 }} &middot; AQ {{ $criterionRow['counts']['average_qualified'] ?? 0 }} &middot; NQ {{ $criterionRow['counts']['not_qualified'] ?? 0 }}</span></td><td><span class="strong">{{ $member['name'] ?? 'Unassigned evaluator' }}</span><span class="subline">{{ ($member['task_complete'] ?? false) ? 'Complete' : (($member['submitted'] ?? false) ? 'Missing decisions' : 'Awaiting submission') }}</span></td><td>@if ($assessment)<span class="badge {{ $assessment['decision'] === 2 ? 'badge-qualified' : ($assessment['decision'] === 1 ? 'badge-average' : 'badge-not-qualified') }}">{{ $assessment['label'] }}</span>@else<span class="badge badge-pending">{{ ($member['submitted'] ?? false) ? 'Decision not recorded' : 'Awaiting submission' }}</span>@endif</td><td class="comment">{{ $assessment['comment'] ?? 'No evaluator comment' }}</td></tr>
                                    @endforeach
                                @endif
                            @empty
                                <tr><td colspan="5" class="number">No criteria were found for this evaluation template.</td></tr>
                            @endforelse
                        </tbody></table>
                    </section>
                @empty
                    <div class="empty-panel">No detailed evaluation template records are available for this applicant.</div>
                @endforelse
            </section>
        @empty
            <div class="empty-panel">No applicants are available for an EOI evidence report.</div>
        @endforelse
    </section>

    <section class="workflow-summary" aria-label="Technical proposal workflow snapshot">
        <h2 class="section-title">Technical Proposal Workflow Snapshot</h2>
        <p class="workflow-note">Read-only snapshot of the technical-proposal rounds displayed on the web report. It records existing history; it does not change invitations, candidate records, rules, or uploaded documents.</p>
        <table class="workflow-table"><colgroup><col style="width: 6%;"><col style="width: 18%;"><col style="width: 9%;"><col style="width: 15%;"><col style="width: 20%;"><col style="width: 20%;"><col style="width: 6%;"><col style="width: 6%;"></colgroup><thead><tr><th>Round</th><th>Title</th><th>Status</th><th>Deadline</th><th>Submission policy</th><th>Rules and templates</th><th>Enrolled</th><th>Received</th></tr></thead><tbody>
            @forelse ($technicalProposalRounds as $round)
                <tr><td class="number"><span class="strong">{{ $round->round_number }}</span></td><td><span class="strong">{{ $round->title }}</span></td><td><span class="status-plain status-{{ $round->status }}">{{ \Illuminate\Support\Str::headline((string) $round->status) }}</span></td><td>{{ $formatRoundDate($round->deadline_at, $round->timezone) }}<span class="subline">{{ $round->timezone ?: config('app.timezone') }}</span></td><td><span class="strong">Late: {{ \Illuminate\Support\Str::headline(str_replace('_', ' ', (string) $round->late_policy)) }}</span><span class="subline">Portal {{ str_replace('_', ' ', (string) $round->portal_requirement) }} &middot; Email {{ str_replace('_', ' ', (string) $round->email_requirement) }} &middot; Physical {{ str_replace('_', ' ', (string) $round->physical_requirement) }}</span></td><td><span class="strong">{{ $round->rules_count ?? 0 }} rule(s) &middot; {{ $round->templates_count ?? 0 }} file(s)</span><span class="rule-list">Full rule, template, candidate, and compliance detail is included in the XLSX and CSV audit exports.</span></td><td class="number">{{ $round->candidates_count ?? 0 }}</td><td class="number">{{ $round->received_candidates_count ?? 0 }}</td></tr>
            @empty
                <tr><td colspan="8" class="number">No technical proposal round has been prepared for this procurement.</td></tr>
            @endforelse
        </tbody></table>
    </section>

    <section class="page-break-before" aria-label="Communication delivery snapshot">
        <h2 class="section-title">Communication Delivery Snapshot</h2>
        <p class="workflow-note">The latest communication records shown on the web report, retained here as a read-only delivery audit.</p>
        <table class="communication-table"><colgroup><col style="width: 13%;"><col style="width: 29%;"><col style="width: 13%;"><col style="width: 13%;"><col style="width: 8%;"><col style="width: 8%;"><col style="width: 8%;"><col style="width: 8%;"></colgroup><thead><tr><th>Type</th><th>Subject</th><th>Created</th><th>Sent</th><th>Recipients</th><th>Sent</th><th>Queued</th><th>Failed / skipped</th></tr></thead><tbody>
            @forelse ($communications as $communication)
                <tr><td><span class="strong">{{ \Illuminate\Support\Str::headline(str_replace('_', ' ', (string) $communication->type)) }}</span></td><td>{{ $communication->subject }}</td><td>{{ $communication->created_at?->format('d M Y, H:i') ?? 'N/A' }}</td><td>{{ $communication->sent_at?->format('d M Y, H:i') ?? 'Not sent' }}</td><td class="number">{{ $communication->recipients_count ?? 0 }}</td><td class="number">{{ $communication->sent_recipients_count ?? 0 }}</td><td class="number">{{ $communication->pending_recipients_count ?? 0 }}</td><td class="number">{{ ($communication->failed_recipients_count ?? 0).' / '.($communication->skipped_recipients_count ?? 0) }}</td></tr>
            @empty
                <tr><td colspan="8" class="number">No evaluation-record or proposal-invitation communication has been created for this procurement.</td></tr>
            @endforelse
        </tbody></table>
    </section>
    <p class="small-note" style="margin-top: 7px;">Decision key: Q = Qualified, AQ = Average Qualified, NQ = Not Qualified. Internal report generated {{ $generatedAt->format('d M Y, H:i') }}.</p>
</body>
</html>
