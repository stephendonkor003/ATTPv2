<!doctype html>
<html>
<body style="margin:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#172033;line-height:1.6;">
    @php
        $currency = $purchaseOrder->resolved_currency ?: ($purchaseOrder->currency ?: 'USD');
        $amount = $currency . ' ' . number_format((float) $purchaseOrder->amount, 2);
        $lineItems = $purchaseOrder->sourcePurchaseRequest()?->items ?? collect();
    @endphp

    <div style="max-width:680px;margin:0 auto;padding:28px 16px;">
        <div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;box-shadow:0 14px 30px rgba(15,23,42,0.08);">
            <div style="background:#006B3F;color:#ffffff;padding:24px;">
                <div style="font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#d1fae5;font-weight:700;">
                    ATTP Purchase Order
                </div>
                <h2 style="margin:8px 0 0;font-size:22px;line-height:1.3;">
                    {{ $purchaseOrder->reference_no ?? 'Purchase Order' }}
                </h2>
                <p style="margin:8px 0 0;color:#dcfce7;">
                    A purchase order has been assigned to your vendor portal.
                </p>
            </div>

            <div style="padding:24px;">
                <p style="margin-top:0;">Hello {{ $vendor->name ?? 'Vendor' }},</p>

                <p>
                    ATTP has created a purchase order for you. Please log in to the vendor portal to review the PO
                    and upload deliverable evidence documents against the relevant line items.
                </p>

                <table style="width:100%;border-collapse:collapse;margin:20px 0;">
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;width:190px;">Purchase Order</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">{{ $purchaseOrder->po_title ?: 'Purchase Order' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Reference</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">{{ $purchaseOrder->reference_no ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Amount</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;font-weight:700;color:#006B3F;">{{ $amount }}</td>
                    </tr>
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Status</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">{{ ucwords(str_replace('_', ' ', $purchaseOrder->status ?? 'draft')) }}</td>
                    </tr>
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Expected Delivery</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">{{ $purchaseOrder->expected_delivery_date?->format('d M Y') ?? 'Not set' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:11px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Deliverables</td>
                        <td style="padding:11px;border:1px solid #e5e7eb;">{{ number_format($lineItems->count()) }}</td>
                    </tr>
                </table>

                @if ($purchaseOrder->delivery_terms || $purchaseOrder->payment_terms)
                    <div style="margin:20px 0;padding:14px 16px;border:1px solid #dbe4ef;border-radius:8px;background:#f8fafc;">
                        @if ($purchaseOrder->delivery_terms)
                            <div style="margin-bottom:8px;">
                                <strong>Delivery Terms:</strong> {{ $purchaseOrder->delivery_terms }}
                            </div>
                        @endif
                        @if ($purchaseOrder->payment_terms)
                            <div>
                                <strong>Payment Terms:</strong> {{ $purchaseOrder->payment_terms }}
                            </div>
                        @endif
                    </div>
                @endif

                <p style="margin:26px 0;">
                    <a href="{{ $portalUrl }}" style="display:inline-block;background:#006B3F;color:#ffffff;padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:700;">
                        Open Purchase Order
                    </a>
                </p>

                <p style="margin:22px 0 0;color:#64748b;font-size:13px;">
                    Upload evidence only for deliverables connected to this purchase order. ATTP will review those documents before payment processing.
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
