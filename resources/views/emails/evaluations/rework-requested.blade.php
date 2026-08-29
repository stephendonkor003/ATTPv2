<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Evaluation Rework Required</title>
</head>
<body style="margin:0;padding:0;background:#eef3f0;font-family:Arial,Helvetica,sans-serif;color:#172033;line-height:1.6;">
    @php
        $appName = trim((string) config('mail.from.name')) ?: (trim((string) config('app.name')) ?: 'ATTP');
        $evaluatorName = trim((string) $rework->evaluator?->name) ?: 'Evaluator';
        $evaluationName = trim((string) $rework->evaluation?->name) ?: 'Evaluation';
        $procurementTitle = trim((string) $rework->procurement?->title) ?: 'Procurement opportunity';
        $reference = trim((string) $rework->procurement?->reference_no) ?: 'Not provided';
        $submissionCode = trim((string) $rework->applicant?->procurement_submission_code) ?: 'Not provided';
        $requesterName = trim((string) $rework->requester?->name) ?: 'Evaluation administrator';
        $assignment = $rework->assignment;
        $technicalProposalRound = $assignment?->technicalProposalRound;
        $workflowLabel = $assignment?->isTechnicalProposalStage()
            ? 'Technical proposal evaluation'.($technicalProposalRound
                ? ' - Round '.number_format((int) $technicalProposalRound->round_number)
                    .(filled($technicalProposalRound->title) ? ': '.$technicalProposalRound->title : '')
                : '')
            : 'Application evaluation';
        $workspaceUrl = $rework->assignment && $rework->applicant
            ? route('my.eval.start', [$rework->assignment, $rework->applicant])
            : route('my.eval.index');
        $supportEmail = trim((string) config('mail.from.address'));
    @endphp

    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        Your submitted evaluation needs corrections before it can return to the final panel report.
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#eef3f0;">
        <tr>
            <td align="center" style="padding:30px 14px;">
                <table role="presentation" width="680" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:680px;background:#fff;border:1px solid #dce6e0;border-radius:14px;overflow:hidden;box-shadow:0 16px 38px rgba(15,23,42,.10);">
                    <tr>
                        <td style="background:#006b3f;padding:0;">
                            <div style="height:6px;background:#f4b41a;"></div>
                            <div style="padding:27px 30px 29px;">
                                <div style="font-size:12px;font-weight:700;letter-spacing:.11em;text-transform:uppercase;color:#c9f3df;">{{ $appName }}</div>
                                <h1 style="margin:8px 0 0;font-size:25px;line-height:1.25;color:#fff;">Evaluation Rework Required</h1>
                                <p style="margin:8px 0 0;font-size:14px;color:#e3fff1;">Your previous answers remain available for correction.</p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px;">
                            <p style="margin:0 0 14px;font-size:16px;">Hello {{ $evaluatorName }},</p>
                            <p style="margin:0 0 22px;">An administrator has returned one of your submitted evaluations for rework. It is no longer included in the live panel result until you correct and resubmit it.</p>

                            <div style="margin:0 0 22px;border:1px solid #dce6e0;border-radius:12px;overflow:hidden;">
                                <div style="padding:12px 16px;background:#f2f7f4;border-bottom:1px solid #dce6e0;font-size:13px;font-weight:700;color:#214f39;text-transform:uppercase;letter-spacing:.05em;">Evaluation details</div>
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;">
                                    @foreach ([
                                        'Evaluation' => $evaluationName,
                                        'Workflow stage' => $workflowLabel,
                                        'Procurement' => $procurementTitle,
                                        'Reference' => $reference,
                                        'Application' => $submissionCode,
                                        'Requested by' => $requesterName,
                                        'Revision' => 'Revision '.number_format((int) $rework->source_revision_number + 1),
                                    ] as $label => $value)
                                        <tr>
                                            <td style="width:34%;padding:11px 16px;border-bottom:1px solid #e8eeea;background:#fbfdfc;font-size:13px;font-weight:700;color:#4d6257;">{{ $label }}</td>
                                            <td style="padding:11px 16px;border-bottom:1px solid #e8eeea;font-size:14px;">{{ $value }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </div>

                            <div style="margin:0 0 24px;padding:17px 18px;border-left:4px solid #d97706;border-radius:9px;background:#fff8eb;color:#5c3a08;">
                                <div style="margin-bottom:5px;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#9a5a0c;">What must be corrected</div>
                                <div style="font-size:14px;white-space:pre-line;">{{ $rework->reason }}</div>
                            </div>

                            <a href="{{ $workspaceUrl }}" style="display:inline-block;border-radius:8px;background:#006b3f;padding:12px 19px;color:#fff;text-decoration:none;font-size:14px;font-weight:700;">Open and revise evaluation</a>
                            <p style="margin:12px 0 0;color:#64748b;font-size:11px;line-height:1.5;word-break:break-all;">If the button does not open, copy this address into your browser:<br><a href="{{ $workspaceUrl }}" style="color:#006b3f;">{{ $workspaceUrl }}</a></p>
                            <p style="margin:20px 0 0;color:#64748b;font-size:12px;line-height:1.55;">Review every requested correction and submit the evaluation again when it is complete. A new identity verification recording is required for the final resubmission.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="border-top:1px solid #dce6e0;background:#f7faf8;padding:18px 30px;color:#64748b;font-size:12px;line-height:1.55;">
                            This is an automated notification from {{ $appName }}. Please do not reply directly to this email.
                            @if ($supportEmail !== '')
                                <br>Support: {{ $supportEmail }}
                            @endif
                        </td>
                    </tr>
                </table>
                <div style="margin-top:12px;color:#8a9a91;font-size:11px;">&copy; {{ date('Y') }} {{ $appName }}. All rights reserved.</div>
            </td>
        </tr>
    </table>
</body>
</html>
