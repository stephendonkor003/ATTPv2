<!doctype html>
<html>
<body style="margin:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#172033;line-height:1.6;">
    <div style="max-width:620px;margin:0 auto;padding:28px 16px;">
        <div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
            <div style="background:#0f172a;color:#ffffff;padding:22px 24px;">
                <h2 style="margin:0;font-size:20px;">ATTP Login Verification</h2>
                <p style="margin:6px 0 0;color:#cbd5e1;">Use this code to complete your sign in.</p>
            </div>

            <div style="padding:24px;">
                <p>Hello {{ $user->name ?? 'User' }},</p>
                <p>Your one-time verification code is:</p>

                <div style="margin:22px 0;padding:18px;text-align:center;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;">
                    <span style="font-size:34px;letter-spacing:8px;font-weight:700;color:#1d4ed8;">{{ $otpCode }}</span>
                </div>

                <p>This code expires shortly. If you did not try to sign in, please ignore this email or contact the ATTP administrator.</p>

                <p style="margin-top:28px;color:#64748b;font-size:13px;">
                    This is an automated security message from {{ config('app.name', 'ATTP') }}.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
