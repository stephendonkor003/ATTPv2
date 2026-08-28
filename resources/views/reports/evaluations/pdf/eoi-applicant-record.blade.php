<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>EOI Evaluation Record</title>
    <style>
        @page { margin: 28mm 14mm 18mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #1d2939; font-family: DejaVu Sans, sans-serif; font-size: 9px; line-height: 1.45; }
        .header { position: fixed; top: -21mm; left: 0; right: 0; height: 17mm; border-bottom: 2px solid #087443; }
        .header table { width: 100%; border-collapse: collapse; }
        .brand { color: #087443; font-size: 12px; font-weight: 700; }
        .brand-sub { color: #667085; font-size: 7.5px; }
        .logo { width: 38px; max-height: 34px; }
        .header-meta { color: #475467; font-size: 7.5px; text-align: right; }
        .footer { position: fixed; bottom: -12mm; left: 0; right: 0; padding-top: 4px; border-top: 1px solid #d0d5dd; color: #667085; font-size: 7px; }
        .footer .page:after { content: counter(page); }
        h1 { margin: 0 0 4px; color: #087443; font-size: 21px; line-height: 1.15; }
        h2 { margin: 17px 0 7px; padding-bottom: 4px; border-bottom: 1px solid #d7e5dd; color: #175c3b; font-size: 12px; page-break-after: avoid; }
        h3 { margin: 10px 0 6px; color: #344054; font-size: 10px; page-break-after: avoid; }
        p { margin: 0 0 6px; }
        .eyebrow { color: #b77a00; font-size: 7.5px; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; }
        .intro { margin-bottom: 12px; color: #667085; font-size: 8.5px; }
        .identity, .metrics, .outcome-card { width: 100%; border-collapse: collapse; }
        .identity { margin-top: 11px; border: 1px solid #d0d5dd; }
        .identity th, .identity td { padding: 6px 8px; border-bottom: 1px solid #eaecf0; vertical-align: top; }
        .identity th { width: 19%; background: #f8faf9; color: #475467; text-align: left; }
        .identity tr:last-child th, .identity tr:last-child td { border-bottom: 0; }
        .outcome-card { margin-top: 10px; border: 1px solid #badbc9; background: #f2faf6; }
        .outcome-card td { padding: 10px; vertical-align: top; }
        .outcome-label { color: #087443; font-size: 16px; font-weight: 700; }
        .outcome-route { color: #344054; font-size: 10px; font-weight: 700; }
        .outcome-note { margin-top: 3px; color: #475467; }
        .metrics { margin-top: 8px; table-layout: fixed; }
        .metrics td { padding: 7px; border: 1px solid #d0d5dd; text-align: center; }
        .metric-value { display: block; color: #101828; font-size: 13px; font-weight: 700; }
        .metric-label { display: block; margin-top: 2px; color: #667085; font-size: 7px; text-transform: uppercase; }
        .privacy { margin-top: 9px; padding: 7px 9px; border-left: 3px solid #e0a115; background: #fff9eb; color: #704f00; }
        table.data { width: 100%; margin: 5px 0 10px; border-collapse: collapse; table-layout: fixed; }
        table.data th { padding: 5px 6px; border: 1px solid #cfd9d3; background: #edf5f0; color: #285942; font-size: 7px; text-align: left; text-transform: uppercase; }
        table.data td { padding: 5px 6px; border: 1px solid #dfe5e1; vertical-align: top; overflow-wrap: anywhere; }
        .status-good { color: #087443; font-weight: 700; }
        .status-warn { color: #9a6700; font-weight: 700; }
        .status-bad { color: #b42318; font-weight: 700; }
        .assessment { margin-bottom: 4px; padding-bottom: 4px; border-bottom: 1px dotted #d0d5dd; }
        .assessment:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: 0; }
        .masked { color: #475467; font-family: DejaVu Sans Mono, monospace; font-size: 7px; }
        .comment { color: #475467; font-style: italic; }
        .evaluation { page-break-inside: auto; }
        .avoid-break { page-break-inside: avoid; }
        .empty { padding: 8px; border: 1px dashed #d0d5dd; color: #667085; }
    </style>
</head>
<body>
    @php
        $reference = trim((string) ($procurement->reference_no ?? '')) ?: 'Not provided';
        $title = trim((string) ($procurement->title ?? '')) ?: 'Procurement opportunity';
        $outcome = $row['outcome'];
        $submissionCode = trim((string) ($applicant->procurement_submission_code ?? '')) ?: 'Not provided';
    @endphp

    <div class="header">
        <table>
            <tr>
                <td style="width:48px;">
                    @if ($logoDataUri)<img class="logo" src="{{ $logoDataUri }}" alt="">@endif
                </td>
                <td>
                    <div class="brand">{{ $platformName }}</div>
                    <div class="brand-sub">Procurement Evaluation Record</div>
                </td>
                <td class="header-meta">Generated {{ $generatedAt->format('d M Y, H:i') }}<br>{{ $reference }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <table style="width:100%;border-collapse:collapse;">
            <tr>
                <td>Private applicant record &middot; Evaluator identities protected</td>
                <td style="text-align:right;">Page <span class="page"></span></td>
            </tr>
        </table>
    </div>

    <div class="eyebrow">Expression of Interest</div>
    <h1>Applicant Evaluation Record</h1>
    <p class="intro">This individualized record uses the same active-panel outcome and workflow logic shown in the official web report.</p>

    <table class="identity">
        <tr><th>Applicant</th><td><strong>{{ $applicant->display_name }}</strong></td><th>Submission code</th><td>{{ $submissionCode }}</td></tr>
        <tr><th>Procurement</th><td>{{ $title }}</td><th>Reference</th><td>{{ $reference }}</td></tr>
    </table>

    <table class="outcome-card">
        <tr>
            <td style="width:38%;">
                <div class="eyebrow">Final panel outcome</div>
                <div class="outcome-label">{{ $outcome['label'] }}</div>
            </td>
            <td>
                <div class="eyebrow">Final workflow decision</div>
                <div class="outcome-route">{{ $row['next_stage'] }}</div>
                <div class="outcome-note">{{ $outcome['description'] }}</div>
            </td>
        </tr>
    </table>

    <table class="metrics">
        <tr>
            <td><span class="metric-value">{{ $row['completed_tasks'] }}/{{ $row['expected_tasks'] }}</span><span class="metric-label">Panel tasks completed</span></td>
            <td><span class="metric-value">{{ $row['counts']['qualified'] ?? 0 }}</span><span class="metric-label">Qualified decisions</span></td>
            <td><span class="metric-value">{{ $row['counts']['average_qualified'] ?? 0 }}</span><span class="metric-label">Average qualified</span></td>
            <td><span class="metric-value">{{ $row['counts']['not_qualified'] ?? 0 }}</span><span class="metric-label">Not qualified</span></td>
        </tr>
    </table>

    <div class="privacy"><strong>Privacy notice:</strong> all evaluator names and contact details are represented by {{ $evaluatorMask }}. Only currently assigned panel tasks are included.</div>

    <h2>Evaluation evidence</h2>
    @forelse ($evaluations as $evaluation)
        <section class="evaluation">
            <h3>{{ $evaluation['name'] }}</h3>
            @if ($evaluation['description'] !== '')<p class="intro">{{ $evaluation['description'] }}</p>@endif

            <div class="avoid-break">
                <table class="data">
                    <colgroup><col style="width:40%"><col style="width:20%"><col style="width:40%"></colgroup>
                    <thead><tr><th>Panel member</th><th>Status</th><th>Decision totals</th></tr></thead>
                    <tbody>
                        @foreach ($evaluation['members'] as $member)
                            <tr>
                                <td>Evaluator {{ str_pad((string) $member['number'], 2, '0', STR_PAD_LEFT) }} &middot; <span class="masked">{{ $member['name'] }}</span></td>
                                <td class="{{ $member['task_complete'] ? 'status-good' : 'status-warn' }}">{{ $member['task_complete'] ? 'Complete' : 'Incomplete' }}</td>
                                <td>Q {{ $member['counts']['qualified'] ?? 0 }} &middot; AQ {{ $member['counts']['average_qualified'] ?? 0 }} &middot; NQ {{ $member['counts']['not_qualified'] ?? 0 }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <table class="data">
                <colgroup><col style="width:24%"><col style="width:18%"><col style="width:42%"><col style="width:16%"></colgroup>
                <thead><tr><th>Criterion</th><th>Section</th><th>Panel record</th><th>Criterion outcome</th></tr></thead>
                <tbody>
                    @forelse ($evaluation['criteria'] as $criterion)
                        <tr>
                            <td>
                                <strong>{{ $criterion['name'] }}</strong>
                                @if ($criterion['description'] !== '')<br><span class="comment">{{ $criterion['description'] }}</span>@endif
                            </td>
                            <td>{{ $criterion['section'] }}</td>
                            <td>
                                @forelse ($criterion['assessments'] as $assessment)
                                    <div class="assessment">
                                        <span class="masked">Evaluator {{ str_pad((string) $assessment['number'], 2, '0', STR_PAD_LEFT) }} &middot; {{ $assessment['evaluator'] }}</span><br>
                                        <strong>{{ $assessment['label'] }}</strong>
                                        @if ($assessment['comment'] !== '')<br><span class="comment">{{ $assessment['comment'] }}</span>@endif
                                    </div>
                                @empty
                                    <span class="status-warn">No completed decision</span>
                                @endforelse
                            </td>
                            <td><strong>{{ data_get($criterion, 'outcome.label', 'Awaiting Panel') }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">No criteria were configured for this evaluation.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    @empty
        <div class="empty">No active-panel evaluation evidence is available.</div>
    @endforelse
</body>
</html>
