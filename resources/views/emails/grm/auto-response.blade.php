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
                <table width="640" cellpadding="0" cellspacing="0" role="presentation" style="max-width:640px;background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #dbe5df;">
                    <tr>
                        <td style="background:#0f766e;color:#ffffff;padding:22px 26px;">
                            <h2 style="margin:0;font-size:22px;">ATTP Grievance Management</h2>
                            <p style="margin:6px 0 0;color:#dff5ee;">Case received and registered</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:26px;">
                            <p style="margin-top:0;">Dear {{ $grievance->submitter_name ?: 'Stakeholder' }},</p>
                            <p>
                                Your grievance has been received and registered under case number
                                <strong>{{ $grievance->case_number }}</strong>.
                            </p>

                            @if ($rule?->auto_response_body)
                                <p style="white-space:pre-line;">{{ $rule->auto_response_body }}</p>
                            @else
                                <p>The responsible GRM team will review the case and respond through the appropriate channel.</p>
                            @endif

                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:20px 0;border-collapse:collapse;">
                                <tr>
                                    <td style="padding:10px;border:1px solid #e2e8f0;background:#f8fafc;"><strong>Program</strong></td>
                                    <td style="padding:10px;border:1px solid #e2e8f0;">{{ $grievance->program?->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px;border:1px solid #e2e8f0;background:#f8fafc;"><strong>Level</strong></td>
                                    <td style="padding:10px;border:1px solid #e2e8f0;">{{ $grievance->level?->name ?? 'Unclassified' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px;border:1px solid #e2e8f0;background:#f8fafc;"><strong>Response Due</strong></td>
                                    <td style="padding:10px;border:1px solid #e2e8f0;">{{ $grievance->due_response_at?->format('d M Y H:i') ?? 'N/A' }}</td>
                                </tr>
                            </table>

                            <p style="margin-bottom:0;color:#475569;">Please keep this case number for tracking and follow up.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#522b39;color:#ffffff;padding:16px 26px;font-size:12px;">
                            Developed, maintained and supported by the ATTP Technical Team.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
