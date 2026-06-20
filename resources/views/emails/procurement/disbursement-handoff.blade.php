<!doctype html>
<html>
<body style="margin:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#172033;line-height:1.6;">
    @php
        $purchaseOrder = $disbursement->purchaseOrder;
        $vendor = $purchaseOrder?->vendor ?: $disbursement->vendor;
        $currency = $disbursement->resolved_currency ?: ($disbursement->currency ?: 'USD');
        $amount = $currency . ' ' . number_format((float) $disbursement->amount, 2);
        $documents = collect($disbursement->signed_documents ?? []);
    @endphp

    <div style="max-width:680px;margin:0 auto;padding:28px 16px;">
        <div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;box-shadow:0 14px 30px rgba(15,23,42,0.08);">
            <div style="background:#0f766e;color:#ffffff;padding:24px;">
                <div style="font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#ccfbf1;font-weight:700;">
                    Procurement Processing
                </div>
                <h2 style="margin:8px 0 0;font-size:22px;line-height:1.3;">
                    {{ $disbursement->reference_no ?? 'Payment Receipt' }}
                </h2>
                <p style="margin:8px 0 0;color:#d1fae5;">
                    A completed payment is ready for goods receipt and SAP 52 entry.
                </p>
            </div>

            <div style="padding:24px;">
                <p style="margin-top:0;">Hello {{ $recipient->name ?? 'Procurement Officer' }},</p>

                <p>
                    Finance has recorded a payment/disbursement. Please review the signed documents,
                    generate the goods receipt, and enter the SAP 52 series reference in ATTP.
                </p>

                <table style="width:100%;border-collapse:collapse;margin:20px 0;">
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;width:190px;">Receipt</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">{{ $disbursement->reference_no ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Purchase Order</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">{{ $purchaseOrder?->reference_no ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Vendor</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">
                            {{ $vendor?->name ?? 'Vendor' }}
                            @if ($vendor?->email)
                                <div style="color:#64748b;font-size:13px;">{{ $vendor->email }}</div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Amount</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;font-weight:700;color:#006B3F;">{{ $amount }}</td>
                    </tr>
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Paid At</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">{{ $disbursement->paid_at?->format('d M Y') ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Signed Documents</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">{{ number_format($documents->count()) }}</td>
                    </tr>
                </table>

                <p style="margin:26px 0;">
                    <a href="{{ $reviewUrl }}" style="display:inline-block;background:#006B3F;color:#ffffff;padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:700;">
                        Open Receipt
                    </a>
                </p>

                <p style="margin:22px 0 0;color:#64748b;font-size:13px;">
                    Record the goods receipt and SAP 52 series reference from the disbursement receipt screen.
                </p>
            </div>

            <div style="background:#f8fafc;border-top:1px solid #e5e7eb;padding:16px 24px;color:#64748b;font-size:12px;">
                <strong style="color:#0f172a;">{{ config('app.name', 'ATTP') }}</strong><br>
                Developed, maintained and supported by the ATTP Technical Team.
            </div>
        </div>
    </div>
</body>
</html>
