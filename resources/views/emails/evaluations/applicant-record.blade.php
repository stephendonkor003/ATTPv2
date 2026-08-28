<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $communication->subject }}</title>
</head>
<body style="margin:0;padding:0;background:#eef3f0;font-family:Arial,Helvetica,sans-serif;color:#172033;line-height:1.6;">
    @php
        $appName = \App\Support\PdfBranding::PLATFORM_NAME;
        $reference = trim((string) ($procurement?->reference_no ?? '')) ?: 'Not provided';
        $title = trim((string) ($procurement?->title ?? '')) ?: 'Procurement opportunity';
        $supportEmail = trim((string) config('mail.from.address'));
    @endphp

    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        Your completed EOI evaluation record is attached.
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#eef3f0;">
        <tr>
            <td align="center" style="padding:30px 14px;">
                <table role="presentation" width="680" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:680px;background:#ffffff;border:1px solid #dce6e0;border-radius:14px;overflow:hidden;box-shadow:0 16px 38px rgba(15,23,42,.10);">
                    <tr>
                        <td style="background:#006b3f;padding:0;">
                            <div style="height:6px;background:#f4b41a;"></div>
                            <div style="padding:27px 30px 29px;">
                                <div style="font-size:12px;font-weight:700;letter-spacing:.11em;text-transform:uppercase;color:#c9f3df;">{{ $appName }}</div>
                                <h1 style="margin:8px 0 0;font-size:25px;line-height:1.25;color:#ffffff;">Your EOI evaluation outcome</h1>
                                <p style="margin:8px 0 0;font-size:14px;color:#e3fff1;">A private copy of your completed evaluation record is attached.</p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px;">
                            <p style="margin:0 0 16px;">Dear <strong>{{ $recipient->recipient_name }}</strong>,</p>
                            <p style="margin:0 0 22px;">The EOI panel has completed its review of your application. Your individualized evaluation record is attached as a PDF.</p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;margin:0 0 22px;border:1px solid #dce6e0;">
                                <tr><td style="width:34%;padding:11px 16px;background:#f6faf8;border-bottom:1px solid #e4ebe7;font-weight:700;color:#4d6257;">Procurement</td><td style="padding:11px 16px;border-bottom:1px solid #e4ebe7;">{{ $title }}</td></tr>
                                <tr><td style="padding:11px 16px;background:#f6faf8;border-bottom:1px solid #e4ebe7;font-weight:700;color:#4d6257;">Reference</td><td style="padding:11px 16px;border-bottom:1px solid #e4ebe7;">{{ $reference }}</td></tr>
                                <tr><td style="padding:11px 16px;background:#f6faf8;border-bottom:1px solid #e4ebe7;font-weight:700;color:#4d6257;">Outcome</td><td style="padding:11px 16px;border-bottom:1px solid #e4ebe7;font-weight:700;color:#006b3f;">{{ $recipient->outcome_label }}</td></tr>
                                <tr><td style="padding:11px 16px;background:#f6faf8;font-weight:700;color:#4d6257;">Workflow decision</td><td style="padding:11px 16px;">{{ $recipient->workflow_decision }}</td></tr>
                            </table>

                            <div style="padding:14px 16px;border-radius:9px;background:#f2f7f4;color:#315744;font-size:13px;">
                                To protect the integrity and privacy of the panel, evaluator identities are masked throughout the attached record as <strong>XXX-XXXX-XXXX</strong>.
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="border-top:1px solid #dce6e0;background:#f7faf8;padding:18px 30px;color:#64748b;font-size:12px;line-height:1.55;">
                            This private notification was sent by {{ $appName }}. Please keep the attached record secure.
                            @if ($supportEmail !== '')<br>Support: {{ $supportEmail }}@endif
                        </td>
                    </tr>
                </table>
                <div style="margin-top:12px;color:#8a9a91;font-size:11px;">&copy; {{ date('Y') }} {{ $appName }}. All rights reserved.</div>
            </td>
        </tr>
    </table>
</body>
</html>
