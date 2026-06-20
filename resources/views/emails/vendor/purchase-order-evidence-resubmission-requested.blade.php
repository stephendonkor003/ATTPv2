<!doctype html>
<html>
<body style="margin:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#172033;line-height:1.6;">
    @php
        $item = $evidence->purchaseRequestItem;
        $deliverable = $item?->milestone ?: ($item?->deliverable?->title ?? $item?->resource?->name ?? 'Deliverable evidence');
    @endphp

    <div style="max-width:680px;margin:0 auto;padding:28px 16px;">
        <div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;box-shadow:0 14px 30px rgba(15,23,42,0.08);">
            <div style="background:#92400e;color:#ffffff;padding:24px;">
                <div style="font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#fde68a;font-weight:700;">
                    Evidence Resubmission
                </div>
                <h2 style="margin:8px 0 0;font-size:22px;line-height:1.3;">
                    {{ $purchaseOrder->reference_no ?? 'Purchase Order' }}
                </h2>
                <p style="margin:8px 0 0;color:#fffbeb;">
                    ATTP has requested corrected evidence for one deliverable.
                </p>
            </div>

            <div style="padding:24px;">
                <p style="margin-top:0;">Hello {{ $vendor->name ?? 'Vendor' }},</p>

                <p>
                    One evidence section on your purchase order has been reopened for resubmission.
                    Please review the note below and upload the corrected document(s) from the vendor portal.
                </p>

                <table style="width:100%;border-collapse:collapse;margin:20px 0;">
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;width:190px;">Purchase Order</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">{{ $purchaseOrder->po_title ?: $purchaseOrder->reference_no }}</td>
                    </tr>
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Deliverable</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">{{ $deliverable }}</td>
                    </tr>
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Requested</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">{{ $evidence->vendor_resubmission_requested_at?->format('d M Y H:i') ?? 'N/A' }}</td>
                    </tr>
                </table>

                @if ($evidence->vendor_resubmission_note)
                    <div style="margin:20px 0;padding:14px 16px;border:1px solid #fbbf24;border-radius:8px;background:#fffbeb;">
                        <div style="font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:#92400e;font-weight:700;margin-bottom:6px;">
                            Admin Note
                        </div>
                        <div>{{ $evidence->vendor_resubmission_note }}</div>
                    </div>
                @endif

                <p style="margin:26px 0;">
                    <a href="{{ $portalUrl }}" style="display:inline-block;background:#006B3F;color:#ffffff;padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:700;">
                        Open Purchase Order
                    </a>
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
