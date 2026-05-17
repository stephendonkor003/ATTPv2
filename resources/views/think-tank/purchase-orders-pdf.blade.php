<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Purchase Order {{ $purchaseOrder->reference_no }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 12px; line-height: 1.5; }
        .header { background: #0f172a; color: #fff; padding: 18px 20px; border-radius: 10px; }
        .title { font-size: 22px; font-weight: 700; margin-bottom: 5px; }
        .muted { color: #64748b; }
        .section { border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; margin-top: 16px; }
        .section-title { font-size: 10px; text-transform: uppercase; letter-spacing: .12em; color: #64748b; font-weight: 700; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 5px 0; vertical-align: top; }
        .label { color: #64748b; width: 36%; }
        .value { font-weight: 700; }
        .signature td { width: 50%; padding-top: 42px; }
        .line { border-top: 1px solid #94a3b8; padding-top: 6px; color: #64748b; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">ATTP Purchase Order</div>
        <div>Reference: <strong>{{ $purchaseOrder->reference_no }}</strong></div>
        <div>Submitted to ATTP Secretariat for fund disbursement</div>
    </div>

    <div class="section">
        <div class="section-title">Think Tank Details</div>
        <table>
            <tr><td class="label">Think Tank</td><td class="value">{{ $member->name }}</td></tr>
            <tr><td class="label">Consortium</td><td class="value">{{ $member->consortium?->name ?? '-' }}</td></tr>
            <tr><td class="label">AU SAP Vendor Number</td><td class="value">{{ $member->au_sap_vendor_number ?? '-' }}</td></tr>
            <tr><td class="label">Payment Vendor Record</td><td class="value">{{ $purchaseOrder->vendor?->name ?? '-' }}</td></tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Financial Summary</div>
        <table>
            <tr><td class="label">Amount Requested</td><td class="value">{{ $purchaseOrder->currency }} {{ number_format($purchaseOrder->amount, 2) }}</td></tr>
            <tr><td class="label">Disbursed</td><td class="value">{{ $purchaseOrder->currency }} {{ number_format($purchaseOrder->paidAmount(), 2) }}</td></tr>
            <tr><td class="label">Remaining</td><td class="value">{{ $purchaseOrder->currency }} {{ number_format($purchaseOrder->remainingAmount(), 2) }}</td></tr>
            <tr><td class="label">Status</td><td class="value">{{ ucfirst(str_replace('_', ' ', $purchaseOrder->status)) }}</td></tr>
            <tr><td class="label">Issued Date</td><td class="value">{{ $purchaseOrder->issued_at?->format('d M Y') ?? now()->format('d M Y') }}</td></tr>
        </table>
    </div>

    <table class="signature">
        <tr>
            <td><div class="line">Think Tank Authorized Representative</div></td>
            <td style="text-align:right;"><div class="line">ATTP Secretariat</div></td>
        </tr>
    </table>
</body>
</html>
