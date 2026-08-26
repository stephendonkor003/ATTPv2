<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Evaluation Submitted</title>
</head>
<body style="margin:0;padding:0;background:#eef3f0;font-family:Arial,Helvetica,sans-serif;color:#172033;line-height:1.6;">
    @php
        $appName = trim((string) config('app.name', 'ATTP')) ?: 'ATTP';
        $evaluation = data_get($submission ?? null, 'evaluation');
        $procurement = data_get($submission ?? null, 'procurement');
        $applicant = data_get($submission ?? null, 'applicant');
        $evaluator = data_get($submission ?? null, 'evaluator');

        $submissionCode = trim((string) data_get($applicant, 'procurement_submission_code')) ?: 'Submission';
        $procurementTitle = trim((string) data_get($procurement, 'title')) ?: 'Procurement opportunity';
        $referenceNumber = trim((string) data_get($procurement, 'reference_no')) ?: 'Not provided';
        $evaluationName = trim((string) data_get($evaluation, 'name')) ?: 'Evaluation';
        $evaluatorName = trim((string) data_get($evaluator, 'name')) ?: 'Evaluator';
        $applicantName = trim((string) data_get($applicant, 'submitter.name')) ?: $submissionCode;
        $evaluationType = trim((string) data_get($evaluation, 'type'));
        $evaluationTypeLabel = $evaluation && method_exists($evaluation, 'typeLabel')
            ? $evaluation->typeLabel()
            : ($evaluationType !== '' ? \Illuminate\Support\Str::headline($evaluationType) : 'Evaluation');
        $isNumeric = $evaluation
            && method_exists($evaluation, 'usesNumericScoring')
            && $evaluation->usesNumericScoring();

        $overallScore = data_get($submission ?? null, 'overall_score');
        $scoreLabel = is_numeric($overallScore) ? number_format((float) $overallScore, 2) : 'Not recorded';
        if ($isNumeric && is_numeric($overallMax ?? null)) {
            $scoreLabel .= ' / ' . number_format((float) $overallMax, 2);
        }

        $submittedAt = data_get($submission ?? null, 'submitted_at');
        $submittedAtLabel = $submittedAt instanceof \DateTimeInterface
            ? $submittedAt->format('d M Y, H:i')
            : (trim((string) $submittedAt) ?: 'Recently submitted');
        $workspaceUrl = route('my.eval.index');
        $supportEmail = trim((string) config('mail.from.address'));
    @endphp

    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        {{ $submissionCode }} has been evaluated and the completed report is attached.
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
                                    Evaluation submitted
                                </h1>
                                <p style="margin:8px 0 0;font-size:14px;color:#e3fff1;">
                                    A completed procurement evaluation is ready for review.
                                </p>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:30px;">
                            <p style="margin:0 0 16px;">
                                The evaluation for <strong>{{ $submissionCode }}</strong> has been submitted successfully.
                                A detailed PDF report is attached to this email.
                            </p>

                            <div style="margin:0 0 22px;border:1px solid #dce6e0;border-radius:12px;overflow:hidden;">
                                <div style="padding:12px 16px;background:#f2f7f4;border-bottom:1px solid #dce6e0;font-size:13px;font-weight:700;color:#214f39;text-transform:uppercase;letter-spacing:.05em;">
                                    Completion details
                                </div>
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;">
                                    <tr>
                                        <td style="width:34%;padding:11px 16px;border-bottom:1px solid #e8eeea;background:#fbfdfc;font-size:13px;font-weight:700;color:#4d6257;">Procurement</td>
                                        <td style="padding:11px 16px;border-bottom:1px solid #e8eeea;font-size:14px;">{{ $procurementTitle }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:11px 16px;border-bottom:1px solid #e8eeea;background:#fbfdfc;font-size:13px;font-weight:700;color:#4d6257;">Reference</td>
                                        <td style="padding:11px 16px;border-bottom:1px solid #e8eeea;font-size:14px;">{{ $referenceNumber }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:11px 16px;border-bottom:1px solid #e8eeea;background:#fbfdfc;font-size:13px;font-weight:700;color:#4d6257;">Submission</td>
                                        <td style="padding:11px 16px;border-bottom:1px solid #e8eeea;font-size:14px;">{{ $submissionCode }} &middot; {{ $applicantName }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:11px 16px;border-bottom:1px solid #e8eeea;background:#fbfdfc;font-size:13px;font-weight:700;color:#4d6257;">Evaluation</td>
                                        <td style="padding:11px 16px;border-bottom:1px solid #e8eeea;font-size:14px;">{{ $evaluationName }} &middot; {{ $evaluationTypeLabel }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:11px 16px;border-bottom:1px solid #e8eeea;background:#fbfdfc;font-size:13px;font-weight:700;color:#4d6257;">Evaluator</td>
                                        <td style="padding:11px 16px;border-bottom:1px solid #e8eeea;font-size:14px;">{{ $evaluatorName }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:11px 16px;{{ $isNumeric ? 'border-bottom:1px solid #e8eeea;' : '' }}background:#fbfdfc;font-size:13px;font-weight:700;color:#4d6257;">Submitted</td>
                                        <td style="padding:11px 16px;{{ $isNumeric ? 'border-bottom:1px solid #e8eeea;' : '' }}font-size:14px;">{{ $submittedAtLabel }}</td>
                                    </tr>
                                    @if ($isNumeric)
                                        <tr>
                                            <td style="padding:11px 16px;background:#fbfdfc;font-size:13px;font-weight:700;color:#4d6257;">Overall score</td>
                                            <td style="padding:11px 16px;font-size:16px;font-weight:700;color:#006b3f;">{{ $scoreLabel }}</td>
                                        </tr>
                                    @endif
                                </table>
                            </div>

                            @unless ($isNumeric)
                                <p style="margin:0 0 22px;padding:12px 14px;border-radius:9px;background:#f2f7f4;color:#315744;font-size:13px;">
                                    This {{ $evaluationTypeLabel }} evaluation records categorical decisions. See the attached report for the decisions and evaluator comments.
                                </p>
                            @endunless

                            <a href="{{ $workspaceUrl }}" style="display:inline-block;border-radius:8px;background:#006b3f;padding:12px 19px;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;">
                                Open My Evaluations
                            </a>
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
