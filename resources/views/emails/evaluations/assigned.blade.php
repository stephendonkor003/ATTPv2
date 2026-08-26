<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Evaluation Assignment</title>
</head>
<body style="margin:0;padding:0;background:#eef3f0;font-family:Arial,Helvetica,sans-serif;color:#172033;line-height:1.6;">
    @php
        $appName = trim((string) config('app.name', 'ATTP')) ?: 'ATTP';
        $evaluatorName = trim((string) data_get($evaluator ?? null, 'name')) ?: 'Evaluator';
        $evaluationName = trim((string) data_get($evaluation ?? null, 'name')) ?: 'Evaluation';
        $procurementTitle = trim((string) data_get($procurement ?? null, 'title')) ?: 'Procurement opportunity';
        $referenceNumber = trim((string) data_get($procurement ?? null, 'reference_no')) ?: 'Not provided';
        $submissionCode = trim((string) data_get($submission ?? null, 'procurement_submission_code'));
        $assignmentScope = $submissionCode !== '' ? 'Specific submission' : 'Entire procurement';
        $workspaceUrl = route('my.eval.index');
        $supportEmail = trim((string) config('mail.from.address'));
    @endphp

    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        You have a new evaluation assignment for {{ $procurementTitle }}.
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#eef3f0;">
        <tr>
            <td align="center" style="padding:30px 14px;">
                <table role="presentation" width="680" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:680px;background:#ffffff;border:1px solid #dce6e0;border-radius:14px;overflow:hidden;box-shadow:0 16px 38px rgba(15,23,42,.10);">
                    <tr>
                        <td style="background:#006b3f;padding:0;">
                            <div style="height:6px;background:#f4b41a;"></div>
                            <div style="padding:27px 30px 29px;">
                                <div style="font-size:12px;font-weight:700;letter-spacing:.11em;text-transform:uppercase;color:#c9f3df;">
                                    {{ $appName }}
                                </div>
                                <h1 style="margin:8px 0 0;font-size:25px;line-height:1.25;color:#ffffff;">
                                    Evaluation Assignment
                                </h1>
                                <p style="margin:8px 0 0;font-size:14px;color:#e3fff1;">
                                    A procurement evaluation is ready in your workspace.
                                </p>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:30px;">
                            <p style="margin:0 0 14px;font-size:16px;">Hello {{ $evaluatorName }},</p>

                            <p style="margin:0 0 22px;">
                                You have been assigned to evaluate the procurement shown below. Please sign in to review
                                the evaluation criteria and the submissions included in your assignment.
                            </p>

                            <div style="margin:0 0 22px;border:1px solid #dce6e0;border-radius:12px;overflow:hidden;">
                                <div style="padding:12px 16px;background:#f2f7f4;border-bottom:1px solid #dce6e0;font-size:13px;font-weight:700;color:#214f39;text-transform:uppercase;letter-spacing:.05em;">
                                    Assignment details
                                </div>
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;">
                                    <tr>
                                        <td style="width:34%;padding:11px 16px;border-bottom:1px solid #e8eeea;background:#fbfdfc;font-size:13px;font-weight:700;color:#4d6257;">Evaluation</td>
                                        <td style="padding:11px 16px;border-bottom:1px solid #e8eeea;font-size:14px;">{{ $evaluationName }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:11px 16px;border-bottom:1px solid #e8eeea;background:#fbfdfc;font-size:13px;font-weight:700;color:#4d6257;">Procurement</td>
                                        <td style="padding:11px 16px;border-bottom:1px solid #e8eeea;font-size:14px;">{{ $procurementTitle }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:11px 16px;border-bottom:1px solid #e8eeea;background:#fbfdfc;font-size:13px;font-weight:700;color:#4d6257;">Reference</td>
                                        <td style="padding:11px 16px;border-bottom:1px solid #e8eeea;font-size:14px;">{{ $referenceNumber }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:11px 16px;{{ $submissionCode !== '' ? 'border-bottom:1px solid #e8eeea;' : '' }}background:#fbfdfc;font-size:13px;font-weight:700;color:#4d6257;">Assignment scope</td>
                                        <td style="padding:11px 16px;{{ $submissionCode !== '' ? 'border-bottom:1px solid #e8eeea;' : '' }}font-size:14px;">{{ $assignmentScope }}</td>
                                    </tr>
                                    @if ($submissionCode !== '')
                                        <tr>
                                            <td style="padding:11px 16px;background:#fbfdfc;font-size:13px;font-weight:700;color:#4d6257;">Submission code</td>
                                            <td style="padding:11px 16px;font-size:14px;">{{ $submissionCode }}</td>
                                        </tr>
                                    @endif
                                </table>
                            </div>

                            <p style="margin:0 0 24px;color:#415449;">
                                Complete the evaluation carefully and save your work as you progress. Submit it only after
                                you have reviewed every applicable criterion.
                            </p>

                            <a href="{{ $workspaceUrl }}" style="display:inline-block;border-radius:8px;background:#006b3f;padding:12px 19px;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;">
                                Open My Evaluations
                            </a>

                            <p style="margin:20px 0 0;color:#64748b;font-size:12px;line-height:1.55;">
                                If this assignment appears incorrect, contact your system administrator before beginning the evaluation.
                            </p>
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
                <div style="margin-top:12px;color:#8a9a91;font-size:11px;">
                    &copy; {{ date('Y') }} {{ $appName }}. All rights reserved.
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
