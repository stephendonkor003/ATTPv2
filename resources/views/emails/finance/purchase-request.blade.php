<!doctype html>
<html>
<body style="margin:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#172033;line-height:1.6;">
    <div style="max-width:640px;margin:0 auto;padding:28px 16px;">
        <div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
            <div style="background:#0f172a;color:#ffffff;padding:22px 24px;">
                <h2 style="margin:0;font-size:20px;">Purchase Request {{ $purchaseRequest->reference_no }}</h2>
                <p style="margin:6px 0 0;color:#cbd5e1;">A purchase request PDF is attached for your review.</p>
            </div>

            <div style="padding:24px;">
                <p>Hello {{ $recipientName }},</p>

                <p>Please find attached the purchase request document for the following request.</p>

                <table style="width:100%;border-collapse:collapse;margin:18px 0;">
                    <tr>
                        <td style="padding:10px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;width:160px;">Reference</td>
                        <td style="padding:10px;border:1px solid #e5e7eb;">{{ $purchaseRequest->reference_no }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Program</td>
                        <td style="padding:10px;border:1px solid #e5e7eb;">
                            {{ $purchaseRequest->programFunding?->program?->name ?? $purchaseRequest->programFunding?->program_name ?? 'N/A' }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:10px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Sub-Activity</td>
                        <td style="padding:10px;border:1px solid #e5e7eb;">{{ $purchaseRequest->subActivity?->name ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Total Amount</td>
                        <td style="padding:10px;border:1px solid #e5e7eb;">
                            {{ $purchaseRequest->currency ?? 'USD' }} {{ number_format((float) $purchaseRequest->total_amount, 2) }}
                        </td>
                    </tr>
                </table>

                <p style="margin-top:24px;color:#64748b;font-size:13px;">
                    This is an automated message from {{ config('app.name', 'ATTP') }}.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
