<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Application received</title></head>
<body style="margin:0;background:#eef4f6;color:#18313a;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#eef4f6;padding:28px 12px;"><tr><td align="center">
    <table role="presentation" width="640" cellspacing="0" cellpadding="0" style="width:100%;max-width:640px;overflow:hidden;border:1px solid #d4e2e7;border-radius:16px;background:#fff;">
        <tr><td style="background:#075C7A;padding:26px 30px;color:#fff;"><div style="color:#ccebf4;font-size:11px;font-weight:700;letter-spacing:1.3px;text-transform:uppercase;">African Think Tank Platform</div><div style="margin-top:7px;font-size:23px;font-weight:800;">Application received</div><div style="margin-top:7px;color:#e5f6fb;font-size:13px;">Your vendor portal record is ready</div></td></tr>
        <tr><td style="padding:30px;">
            <p style="margin:0 0 15px;font-size:15px;line-height:1.65;">Dear {{ $vendor->name ?: 'Vendor' }},</p>
            <p style="margin:0 0 20px;font-size:15px;line-height:1.65;">Your application has been received successfully. Keep using this vendor portal account to review, update, resubmit or withdraw the application whenever the opportunity permits.</p>
            <div style="margin-bottom:20px;border:1px solid #dce7eb;border-radius:11px;background:#f7fafb;padding:17px 18px;"><div style="color:#66808a;font-size:11px;font-weight:800;text-transform:uppercase;">Procurement opportunity</div><div style="margin-top:5px;font-size:17px;font-weight:800;">{{ $procurement->title }}</div><div style="margin-top:6px;color:#66808a;font-size:13px;">{{ $procurement->reference_no ?: 'No reference' }} &middot; Application {{ $submission->procurement_submission_code }}</div></div>
            @if($temporaryPassword)
                <div style="margin-bottom:20px;border-left:4px solid #2b84a0;border-radius:5px;background:#eef8fb;padding:14px 16px;"><div style="margin-bottom:5px;color:#075C7A;font-size:11px;font-weight:800;text-transform:uppercase;">Your vendor portal access</div><div style="font-size:14px;line-height:1.7;"><strong>Email:</strong> {{ $vendor->email }}<br><strong>Temporary password:</strong> {{ $temporaryPassword }}</div><div style="margin-top:7px;color:#58717a;font-size:12px;">You will be asked to change this password after signing in.</div></div>
            @endif
            <a href="{{ $portalUrl }}" style="display:inline-block;border-radius:8px;background:#075C7A;padding:12px 19px;color:#fff;text-decoration:none;font-size:14px;font-weight:700;">Sign in to vendor portal</a>
        </td></tr>
        <tr><td style="border-top:1px solid #dce7eb;background:#f7fafb;padding:19px 30px;color:#66808a;font-size:12px;line-height:1.55;">This is an automated notification from the African Think Tank Platform.<br>ATTP &middot; Procurement services &middot; Vendor portal</td></tr>
    </table>
</td></tr></table>
</body>
</html>
