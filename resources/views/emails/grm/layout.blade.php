<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $emailTitle ?? 'ATTP Grievance Redress Mechanism' }}</title>
</head>
<body style="margin:0;padding:0;background:#eef4f1;color:#17231d;font-family:Arial,Helvetica,sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        {{ $emailPreheader ?? 'An update from the ATTP Grievance Redress Mechanism.' }}
    </div>

    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;background:#eef4f1;">
        <tr>
            <td align="center" style="padding:30px 12px;">
                <table width="680" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;max-width:680px;background:#ffffff;border:1px solid #d7e2dc;border-radius:14px;overflow:hidden;box-shadow:0 12px 28px rgba(16,84,59,.10);">
                    <tr>
                        <td style="height:6px;background:{{ $emailAccent ?? '#d4a017' }};font-size:0;line-height:0;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="background:#10543b;padding:24px 28px;color:#ffffff;">
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                                <tr>
                                    <td width="54" valign="top">
                                        <div style="width:42px;height:42px;line-height:42px;text-align:center;border-radius:10px;background:#ffffff;color:#10543b;font-weight:800;font-size:16px;">AU</div>
                                    </td>
                                    <td valign="middle">
                                        <div style="font-size:11px;line-height:1.4;font-weight:700;letter-spacing:1.3px;text-transform:uppercase;color:#cce8da;">African Think Tank Platform</div>
                                        <div style="margin-top:3px;font-size:13px;line-height:1.4;color:#ffffff;">Grievance Redress Mechanism</div>
                                    </td>
                                    <td align="right" valign="middle" style="font-size:12px;color:#d8ede3;white-space:nowrap;">
                                        {{ $grievance->case_number ?? 'ATTP GRM' }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px 30px 12px;">
                            <div style="font-size:11px;line-height:1.4;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;color:{{ $emailAccent ?? '#a16207' }};">
                                {{ $emailEyebrow ?? 'Case communication' }}
                            </div>
                            <h1 style="margin:7px 0 8px;color:#17231d;font-size:25px;line-height:1.25;font-weight:800;">
                                {{ $emailHeading ?? 'Grievance case update' }}
                            </h1>
                            @if (filled($emailSubheading ?? null))
                                <p style="margin:0;color:#607068;font-size:14px;line-height:1.6;">{{ $emailSubheading }}</p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:10px 30px 32px;color:#33443b;font-size:15px;line-height:1.65;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="border-top:1px solid #dce6e1;background:#f7faf8;padding:20px 30px;">
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                                <tr>
                                    <td valign="top" style="color:#55665d;font-size:12px;line-height:1.6;">
                                        <strong style="display:block;color:#293a31;font-size:12px;">Confidential case communication</strong>
                                        Use the case number in this email whenever you follow up with the GRM team.
                                    </td>
                                    <td align="right" valign="top" style="color:#6b7c73;font-size:11px;line-height:1.6;white-space:nowrap;">
                                        &copy; {{ now()->year }} ATTP Administration<br>
                                        African Think Tank Platform
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#522b39;color:#f8edf1;padding:12px 30px;text-align:center;font-size:10px;line-height:1.5;letter-spacing:.2px;">
                            Developed, maintained and supported by the ATTP Technical Team.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
