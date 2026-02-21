<div style="font-family: Arial, sans-serif; background:#f4f6fb; padding:24px;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:10px; overflow:hidden;">
        <tr>
            <td style="background:#2563eb; color:#fff; padding:16px 20px; font-size:18px; font-weight:700;">
                Indicator Reminder
            </td>
        </tr>
        <tr>
            <td style="padding:20px;">
                <p style="margin:0 0 12px;">Hello {{ $user->name }},</p>
                <p style="margin:0 0 12px;">
                    You are listed as a responsible user for the indicator:
                </p>
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; margin-bottom:12px;">
                    <strong>{{ $indicator->name }}</strong><br>
                    Baseline: {{ $indicator->baseline_year ?? 'n/a' }} ({{ $indicator->baseline_type ?? 'year' }})<br>
                    Frequency: {{ $frequency ?? 'n/a' }}
                </div>
                <p style="margin:0 0 12px;">
                    Please review or provide data according to the frequency above. If manual input is required, log in and update the indicator values.
                </p>
                <p style="margin:0 0 12px;">Thank you.</p>
            </td>
        </tr>
        <tr>
            <td style="background:#0f172a; color:#cbd5e1; padding:12px 20px; font-size:12px;">
                This is an automated reminder sent every 4 hours until data is updated.
            </td>
        </tr>
    </table>
</div>
