<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $notification->heading }}</title>
</head>
<body style="margin:0;background:#f1f5f9;color:#172033;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f5f9;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellspacing="0" cellpadding="0" style="max-width:640px;width:100%;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #dbe4ee;">
                    <tr>
                        <td style="background:#0b3b2e;padding:24px 30px;color:#ffffff;">
                            <div style="font-size:12px;letter-spacing:1.2px;text-transform:uppercase;color:#b9e4d2;font-weight:700;">African Think Tank Platform</div>
                            <div style="font-size:23px;font-weight:800;margin-top:6px;">{{ $notification->heading }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px;">
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.65;">Dear {{ $notification->recipient_name ?: 'Procurement colleague' }},</p>
                            <p style="margin:0 0 22px;font-size:15px;line-height:1.65;">{{ $notification->message }}</p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:22px;">
                                <tr>
                                    <td style="padding:16px 18px;">
                                        <div style="font-size:12px;color:#64748b;text-transform:uppercase;font-weight:700;">Procurement plan</div>
                                        <div style="font-size:17px;font-weight:800;margin-top:4px;">{{ $metadata['plan_title'] ?? $event->plan?->title }}</div>
                                        <div style="font-size:13px;color:#64748b;margin-top:5px;">{{ $metadata['plan_code'] ?? $event->plan?->plan_code }} &middot; FY {{ $metadata['fiscal_year'] ?? $event->plan?->fiscal_year }}</div>
                                        @if ($event->item_id)
                                            <div style="border-top:1px solid #e2e8f0;margin-top:13px;padding-top:13px;font-size:14px;">
                                                <strong>{{ $metadata['item_code'] ?? $event->item?->item_code }}</strong> &mdash; {{ $metadata['item_title'] ?? $event->item?->title }}
                                            </div>
                                        @endif
                                        <div style="border-top:1px solid #e2e8f0;margin-top:13px;padding-top:13px;">
                                            <div style="font-size:12px;color:#64748b;text-transform:uppercase;font-weight:700;">Recorded transition</div>
                                            <div style="font-size:14px;font-weight:800;margin-top:5px;color:#0f5f4b;">{{ $fromStatusLabel }} &rarr; {{ $toStatusLabel }}</div>
                                            <div style="font-size:12px;color:#64748b;margin-top:5px;">{{ $event->created_at?->format('d M Y, H:i T') }}@if($metadata['actor_name'] ?? $event->actor?->name) &middot; {{ $metadata['actor_name'] ?? $event->actor?->name }}@endif</div>
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            @if ($event->reason)
                                <div style="border-left:4px solid #dc8b18;background:#fff7e8;padding:13px 16px;margin-bottom:22px;border-radius:4px;">
                                    <div style="font-size:12px;color:#8a4b08;text-transform:uppercase;font-weight:800;margin-bottom:4px;">Action note</div>
                                    <div style="font-size:14px;line-height:1.55;">{{ $event->reason }}</div>
                                </div>
                            @endif

                            <a href="{{ $actionUrl }}" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;padding:12px 19px;border-radius:8px;font-size:14px;font-weight:700;">Open procurement workspace</a>
                            <p style="margin:18px 0 0;color:#64748b;font-size:12px;line-height:1.55;">A PDF status summary is attached for your records. This email reflects the workflow event recorded at the date and time shown above.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 30px;background:#f8fafc;border-top:1px solid #e2e8f0;color:#64748b;font-size:12px;line-height:1.5;">
                            This is an automated workflow notification from the ATTP Secretariat.<br>
                            African Think Tank Platform &middot; Procurement oversight
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
