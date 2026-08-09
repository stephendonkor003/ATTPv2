<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $event === 'recalled' ? 'Procurement opportunity recalled' : 'Procurement opportunity republished' }}</title>
</head>
<body style="margin:0;background:#eef4f6;color:#18313a;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#eef4f6;padding:28px 12px;">
    <tr><td align="center">
        <table role="presentation" width="640" cellspacing="0" cellpadding="0" style="width:100%;max-width:640px;overflow:hidden;border:1px solid #d4e2e7;border-radius:16px;background:#ffffff;">
            <tr>
                <td style="background:#075C7A;padding:26px 30px;color:#ffffff;">
                    <div style="font-size:11px;font-weight:700;letter-spacing:1.3px;text-transform:uppercase;color:#ccebf4;">African Think Tank Platform</div>
                    <div style="margin-top:7px;font-size:23px;font-weight:800;line-height:1.25;">
                        {{ $event === 'recalled' ? 'Procurement opportunity recalled' : 'Procurement opportunity republished' }}
                    </div>
                    <div style="margin-top:7px;color:#e5f6fb;font-size:13px;">Vendor application notification</div>
                </td>
            </tr>
            <tr>
                <td style="padding:30px;">
                    <p style="margin:0 0 15px;font-size:15px;line-height:1.65;">Dear {{ $vendor->name ?: 'Vendor' }},</p>

                    @if($event === 'recalled')
                        <p style="margin:0 0 20px;font-size:15px;line-height:1.65;">
                            {{ $procurement->thinkTankMember?->name ?? 'The publishing Think Tank' }} has recalled the procurement opportunity below. It is temporarily unavailable while the publication is reviewed. Your application remains securely stored; you do not need to apply again.
                        </p>
                    @else
                        <p style="margin:0 0 20px;font-size:15px;line-height:1.65;">
                            {{ $procurement->thinkTankMember?->name ?? 'The publishing Think Tank' }} has republished the procurement opportunity below. @if($submission->isWithdrawn()) Your earlier application was withdrawn, so you may submit a new application if you still wish to participate. @else Your previous application is still available in the vendor portal. Please review it, respond to the recall note and resubmit, or withdraw it if you prefer to apply again. @endif
                        </p>
                    @endif

                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:20px;border:1px solid #dce7eb;border-radius:11px;background:#f7fafb;">
                        <tr><td style="padding:17px 18px;">
                            <div style="font-size:11px;font-weight:800;letter-spacing:.7px;text-transform:uppercase;color:#66808a;">Procurement opportunity</div>
                            <div style="margin-top:5px;font-size:17px;font-weight:800;color:#18313a;">{{ $procurement->title }}</div>
                            <div style="margin-top:6px;font-size:13px;color:#66808a;">{{ $procurement->reference_no ?: 'No reference' }} &middot; Publication version {{ $procurement->publication_version }}</div>
                            @if($event === 'republished' && $procurement->application_end_date)
                                <div style="margin-top:7px;color:#075C7A;font-size:13px;font-weight:700;">Applications close {{ $procurement->application_end_date->format('d M Y') }}</div>
                            @endif
                        </td></tr>
                    </table>

                    @if($reason)
                        <div style="margin-bottom:21px;border-left:4px solid #e5a22c;border-radius:5px;background:#fff8e9;padding:14px 16px;">
                            <div style="margin-bottom:5px;color:#8a5808;font-size:11px;font-weight:800;letter-spacing:.7px;text-transform:uppercase;">Recall note</div>
                            <div style="font-size:14px;line-height:1.6;color:#513b17;">{{ $reason }}</div>
                        </div>
                    @endif

                    <a href="{{ $portalUrl }}" style="display:inline-block;border-radius:8px;background:#075C7A;padding:12px 19px;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;">Open my vendor applications</a>
                    <p style="margin:18px 0 0;color:#66808a;font-size:12px;line-height:1.55;">For your security, sign in using your existing vendor portal account. Do not create another vendor account for this procurement.</p>
                </td>
            </tr>
            <tr>
                <td style="border-top:1px solid #dce7eb;background:#f7fafb;padding:19px 30px;color:#66808a;font-size:12px;line-height:1.55;">
                    This is an automated procurement notification from the African Think Tank Platform.<br>
                    ATTP &middot; Transparent procurement &middot; Vendor services
                </td>
            </tr>
        </table>
    </td></tr>
</table>
</body>
</html>
