<!doctype html>
<html>
<body style="margin:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#172033;line-height:1.6;">
    @php
        $vendor = $purchaseRequest->user;
        $currency = $purchaseRequest->currency ?: 'USD';
    @endphp

    <div style="max-width:680px;margin:0 auto;padding:28px 16px;">
        <div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;box-shadow:0 14px 30px rgba(15,23,42,0.08);">
            <div style="background:#7c2d12;color:#ffffff;padding:24px;">
                <div style="font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#fed7aa;font-weight:700;">
                    Purchase Request Returned
                </div>
                <h2 style="margin:8px 0 0;font-size:22px;line-height:1.3;">
                    {{ $purchaseRequest->reference_no }}
                </h2>
                <p style="margin:8px 0 0;color:#ffedd5;">
                    The ATTP admin team needs you to update and resubmit this request.
                </p>
            </div>

            <div style="padding:24px;">
                <p style="margin-top:0;">Hello {{ $vendor?->name ?? 'Vendor' }},</p>

                <p>
                    Your purchase request has been returned for correction. Please review the admin note below,
                    update the request, and resubmit it from the vendor portal.
                </p>

                <table style="width:100%;border-collapse:collapse;margin:20px 0;">
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;width:190px;">Title</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">{{ $purchaseRequest->title }}</td>
                    </tr>
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Amount</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;font-weight:700;color:#006B3F;">
                            {{ $currency }} {{ number_format((float) $purchaseRequest->requested_amount, 2) }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Priority</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">{{ ucfirst($purchaseRequest->priority ?? 'normal') }}</td>
                    </tr>
                </table>

                <div style="margin:20px 0;padding:16px;border:1px solid #fed7aa;border-radius:8px;background:#fff7ed;">
                    <div style="font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:#9a3412;font-weight:700;margin-bottom:6px;">
                        Admin Note
                    </div>
                    <div>{{ $purchaseRequest->admin_response ?: 'Please update this request and resubmit it.' }}</div>
                </div>

                <p style="margin:26px 0;">
                    <a href="{{ $editUrl }}" style="display:inline-block;background:#006B3F;color:#ffffff;padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:700;">
                        Edit and Resubmit Request
                    </a>
                </p>

                <p style="margin:22px 0 0;color:#64748b;font-size:13px;">
                    Once resubmitted, admins will receive a fresh notification for review.
                </p>
            </div>

            <div style="background:#f8fafc;border-top:1px solid #e5e7eb;padding:16px 24px;color:#64748b;font-size:12px;">
                <strong style="color:#0f172a;">{{ config('app.name', 'ATTP') }}</strong><br>
                Automated vendor request update. Please do not reply directly to this email.
            </div>
        </div>
    </div>
</body>
</html>
