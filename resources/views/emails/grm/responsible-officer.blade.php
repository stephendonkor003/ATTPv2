<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $grievance->case_number }}</title>
</head>
<body style="margin:0;background:#f3f7f5;font-family:Arial,sans-serif;color:#0f172a;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f3f7f5;padding:24px;">
        <tr>
            <td align="center">
                <table width="680" cellpadding="0" cellspacing="0" role="presentation" style="max-width:680px;background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #dbe5df;">
                    <tr>
                        <td style="background:#0f766e;color:#ffffff;padding:24px 28px;">
                            <h2 style="margin:0;font-size:23px;">New Grievance Assigned</h2>
                            <p style="margin:7px 0 0;color:#dff5ee;">Grievance Redress Mechanism case requiring program-level attention</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin-top:0;">Dear {{ $officer->name }},</p>
                            <p>
                                A new grievance has been registered in ATTP and assigned to you as the responsible GRM officer for the linked program.
                                Please review the case, acknowledge it, and record the next action in the GRM workspace.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:22px 0;border-collapse:collapse;">
                                <tr>
                                    <td style="padding:11px;border:1px solid #e2e8f0;background:#f8fafc;width:34%;"><strong>Case Number</strong></td>
                                    <td style="padding:11px;border:1px solid #e2e8f0;">{{ $grievance->case_number }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:11px;border:1px solid #e2e8f0;background:#f8fafc;"><strong>Subject</strong></td>
                                    <td style="padding:11px;border:1px solid #e2e8f0;">{{ $grievance->subject }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:11px;border:1px solid #e2e8f0;background:#f8fafc;"><strong>Program</strong></td>
                                    <td style="padding:11px;border:1px solid #e2e8f0;">{{ $grievance->program?->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:11px;border:1px solid #e2e8f0;background:#f8fafc;"><strong>Portfolio</strong></td>
                                    <td style="padding:11px;border:1px solid #e2e8f0;">{{ $grievance->program?->sector?->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:11px;border:1px solid #e2e8f0;background:#f8fafc;"><strong>Level</strong></td>
                                    <td style="padding:11px;border:1px solid #e2e8f0;">{{ $grievance->level?->name ?? 'Unclassified' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:11px;border:1px solid #e2e8f0;background:#f8fafc;"><strong>Submitted By</strong></td>
                                    <td style="padding:11px;border:1px solid #e2e8f0;">
                                        {{ $grievance->is_anonymous ? 'Anonymous' : ($grievance->submitter_name ?: $grievance->submitter?->name ?: 'Not provided') }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:11px;border:1px solid #e2e8f0;background:#f8fafc;"><strong>Response Due</strong></td>
                                    <td style="padding:11px;border:1px solid #e2e8f0;">{{ $grievance->due_response_at?->format('d M Y H:i') ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:11px;border:1px solid #e2e8f0;background:#f8fafc;"><strong>Supporting Documents</strong></td>
                                    <td style="padding:11px;border:1px solid #e2e8f0;">{{ number_format((int) ($grievance->attachments_count ?? 0)) }}</td>
                                </tr>
                            </table>

                            <div style="margin:22px 0;padding:16px;border-left:4px solid #0f766e;background:#f8fafc;">
                                <strong style="display:block;margin-bottom:8px;">Summary</strong>
                                <div style="white-space:pre-line;color:#334155;">{{ \Illuminate\Support\Str::limit($grievance->description, 650) }}</div>
                            </div>

                            <p style="margin:0 0 22px;color:#475569;">
                                The submitter has also received an acknowledgement email with the case number.
                            </p>

                            <a href="{{ $caseUrl }}" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 18px;border-radius:6px;">
                                Open GRM Case
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#522b39;color:#ffffff;padding:16px 28px;font-size:12px;">
                            Developed, maintained and supported by the ATTP Technical Team.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
