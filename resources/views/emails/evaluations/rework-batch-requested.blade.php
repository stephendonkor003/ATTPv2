<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Evaluation Rework Required</title></head>
<body style="margin:0;padding:0;background:#eef3f0;font-family:Arial,Helvetica,sans-serif;color:#172033;line-height:1.6;">
    @php
        $firstRework = $reworks->first();
        $appName = trim((string) config('mail.from.name')) ?: (trim((string) config('app.name')) ?: 'ATTP');
        $evaluatorName = trim((string) $firstRework->evaluator?->name) ?: 'Evaluator';
        $procurementTitle = trim((string) $firstRework->procurement?->title) ?: 'Procurement opportunity';
        $reference = trim((string) $firstRework->procurement?->reference_no) ?: 'Not provided';
        $requesterName = trim((string) $firstRework->requester?->name) ?: 'Evaluation administrator';
        $workspaceUrl = route('my.eval.index');
        $supportEmail = trim((string) config('mail.from.address'));
        $requestCount = $reworks->count();
        $sharedReason = $reworks->pluck('reason')->unique()->count() === 1;
    @endphp
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">{{ $requestCount }} {{ str('evaluation')->plural($requestCount) }} returned for correction. Your previous answers remain available.</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#eef3f0;">
        <tr><td align="center" style="padding:30px 14px;">
            <table role="presentation" width="720" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:720px;background:#fff;border:1px solid #dce6e0;border-radius:14px;overflow:hidden;">
                <tr><td style="background:#006b3f;padding:0;">
                    <div style="height:6px;background:#f4b41a;"></div>
                    <div style="padding:27px 30px 29px;">
                        <div style="font-size:12px;font-weight:700;letter-spacing:.11em;text-transform:uppercase;color:#c9f3df;">{{ $appName }}</div>
                        <h1 style="margin:8px 0 0;font-size:25px;line-height:1.25;color:#fff;">Evaluation Rework Required</h1>
                        <p style="margin:8px 0 0;font-size:14px;color:#e3fff1;">{{ $requestCount }} {{ str('evaluation')->plural($requestCount) }} to review and resubmit</p>
                    </div>
                </td></tr>
                <tr><td style="padding:30px;">
                    <p style="margin:0 0 14px;font-size:16px;">Hello {{ $evaluatorName }},</p>
                    <p style="margin:0 0 22px;">{{ $requesterName }} has returned the evaluations listed below for correction. Their previous answers remain available, and they are excluded from live panel results until you correct and resubmit them.</p>
                    <div style="margin:0 0 22px;padding:15px 17px;background:#f2f7f4;border:1px solid #dce6e0;border-radius:9px;">
                        <strong style="display:block;font-size:14px;color:#214f39;">{{ $procurementTitle }}</strong>
                        <div style="font-size:13px;color:#4d6257;">Reference: {{ $reference }}</div>
                        <div style="font-size:13px;color:#4d6257;">Requested by: {{ $requesterName }}</div>
                    </div>
                    @if($sharedReason)
                        <div style="margin:0 0 24px;padding:17px 18px;border-left:4px solid #d97706;border-radius:9px;background:#fff8eb;color:#5c3a08;">
                            <div style="margin-bottom:5px;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#9a5a0c;">What must be corrected</div>
                            <div style="font-size:14px;white-space:pre-line;">{{ $firstRework->reason }}</div>
                        </div>
                    @endif
                    <h2 style="margin:0 0 10px;font-size:17px;color:#214f39;">Evaluations requiring your attention</h2>
                    <table width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;margin:0 0 24px;table-layout:fixed;">
                        <thead><tr>
                            <th scope="col" style="width:40%;padding:11px 12px;background:#f2f7f4;text-align:left;font-size:12px;color:#4d6257;border:1px solid #dce6e0;">Applicant</th>
                            <th scope="col" style="padding:11px 12px;background:#f2f7f4;text-align:left;font-size:12px;color:#4d6257;border:1px solid #dce6e0;">Evaluation and stage</th>
                        </tr></thead>
                        <tbody>
                            @foreach($reworks as $rework)
                                @php
                                    $assignment = $rework->assignment;
                                    $round = $assignment?->technicalProposalRound;
                                    $stage = $assignment?->isTechnicalProposalStage() ? 'Technical proposal evaluation' : 'Application evaluation';
                                    $phase = trim((string) $rework->evaluation?->evaluation_phase);
                                    $applicantName = trim((string) $rework->applicant?->display_name) ?: 'Applicant';
                                    $applicationCode = trim((string) $rework->applicant?->procurement_submission_code);
                                @endphp
                                <tr>
                                    <td style="padding:13px 12px;vertical-align:top;border:1px solid #dce6e0;font-size:13px;overflow-wrap:anywhere;">
                                        <strong>{{ $applicantName }}</strong>
                                        @if($applicationCode !== '')<div style="margin-top:3px;font-size:11px;color:#64748b;">{{ $applicationCode }}</div>@endif
                                    </td>
                                    <td style="padding:13px 12px;vertical-align:top;border:1px solid #dce6e0;font-size:13px;overflow-wrap:anywhere;">
                                        <strong>{{ $rework->evaluation?->name ?: 'Evaluation' }}</strong>
                                        <div style="margin-top:3px;font-size:12px;color:#4d6257;">{{ $stage }}@if($phase !== '') · {{ str($phase)->headline() }}@endif</div>
                                        @if($assignment?->isTechnicalProposalStage() && $round)<div style="font-size:12px;color:#64748b;">Round {{ number_format((int) $round->round_number) }}@if(filled($round->title)): {{ $round->title }}@endif</div>@endif
                                        <div style="font-size:12px;color:#64748b;">Next submission: revision {{ number_format((int) $rework->source_revision_number + 1) }}</div>
                                        @unless($sharedReason)<div style="margin-top:8px;white-space:pre-line;color:#5c3a08;">{{ $rework->reason }}</div>@endunless
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <a href="{{ $workspaceUrl }}" style="display:inline-block;border-radius:8px;background:#006b3f;padding:12px 19px;color:#fff;text-decoration:none;font-size:14px;font-weight:700;">Open My Evaluations</a>
                    <p style="margin:12px 0 0;color:#64748b;font-size:11px;line-height:1.5;word-break:break-all;">If the button does not open, copy this address into your browser:<br><a href="{{ $workspaceUrl }}" style="color:#006b3f;">{{ $workspaceUrl }}</a></p>
                    <p style="margin:20px 0 0;color:#64748b;font-size:12px;line-height:1.55;">Open each returned evaluation, review the requested corrections, and submit it again when complete. A new identity verification recording is required for each final resubmission.</p>
                </td></tr>
                <tr><td style="border-top:1px solid #dce6e0;background:#f7faf8;padding:18px 30px;color:#64748b;font-size:12px;line-height:1.55;">This is an automated notification from {{ $appName }}. Please do not reply directly to this email.@if($supportEmail !== '')<br>Support: {{ $supportEmail }}@endif</td></tr>
            </table>
            <div style="margin-top:12px;color:#8a9a91;font-size:11px;">&copy; {{ date('Y') }} {{ $appName }}. All rights reserved.</div>
        </td></tr>
    </table>
</body>
</html>
