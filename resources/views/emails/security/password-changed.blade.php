<!doctype html>
<html>
<body style="margin:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#172033;line-height:1.6;">
    <div style="max-width:620px;margin:0 auto;padding:28px 16px;">
        <div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
            <div style="background:#0f172a;color:#ffffff;padding:22px 24px;">
                <h2 style="margin:0;font-size:20px;">Password Changed</h2>
                <p style="margin:6px 0 0;color:#cbd5e1;">Your ATTP account password was updated successfully.</p>
            </div>

            <div style="padding:24px;">
                <p>Hello {{ $user->name ?? 'User' }},</p>
                <p>This confirms that the password for your account was changed.</p>
                <p>If you made this change, no further action is needed.</p>
                <p>If you did not make this change, contact the ATTP administrator immediately.</p>

                <p style="margin-top:28px;color:#64748b;font-size:13px;">
                    This is an automated security message from {{ config('app.name', 'ATTP') }}.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
