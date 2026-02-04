<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Purchase Order {{ $purchaseOrder->reference_no ?? '' }}</title>
        <style>
            @page {
                margin: 28px 32px;
            }

            * {
                box-sizing: border-box;
            }

            body {
                font-family: DejaVu Sans, sans-serif;
                color: #0f172a;
                font-size: 12px;
                line-height: 1.5;
            }

            .header {
                background: #0f172a;
                color: #ffffff;
                border-radius: 12px;
                padding: 18px 20px;
            }

            .header-title {
                font-size: 20px;
                font-weight: 700;
                margin-bottom: 4px;
            }

            .header-subtitle {
                font-size: 11px;
                color: #cbd5f5;
            }

            .badge {
                display: inline-block;
                background: #0ea5e9;
                color: #ffffff;
                font-size: 9px;
                font-weight: 600;
                letter-spacing: 0.12em;
                text-transform: uppercase;
                padding: 4px 10px;
                border-radius: 999px;
            }

            .section {
                margin-top: 18px;
                padding: 14px 16px;
                border: 1px solid #e2e8f0;
                border-radius: 10px;
            }

            .section-title {
                font-size: 10px;
                font-weight: 700;
                letter-spacing: 0.14em;
                text-transform: uppercase;
                color: #64748b;
                margin-bottom: 10px;
            }

            .label {
                color: #64748b;
                width: 35%;
            }

            .value {
                font-weight: 600;
                color: #0f172a;
            }

            table {
                width: 100%;
                border-collapse: collapse;
            }

            td {
                padding: 4px 0;
                vertical-align: top;
            }

            .divider {
                height: 1px;
                background: #e2e8f0;
                margin: 10px 0;
            }

            .signature-table td {
                width: 50%;
                padding-top: 28px;
            }

            .signature-line {
                border-top: 1px solid #94a3b8;
                margin-top: 34px;
            }

            .small-muted {
                font-size: 10px;
                color: #94a3b8;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <table>
                <tr>
                    <td>
                        <div class="header-title">Purchase Order</div>
                        <div class="header-subtitle">{{ config('app.name') }}</div>
                    </td>
                    <td style="text-align:right;">
                        <div class="badge">{{ $purchaseOrder->status ?? 'draft' }}</div>
                        <div style="margin-top:8px; font-size:12px;">
                            Reference: <strong>{{ $purchaseOrder->reference_no ?? 'N/A' }}</strong>
                        </div>
                        <div class="small-muted">
                            Issued:
                            {{ $purchaseOrder->issued_at?->format('d M Y, H:i') ?? now()->format('d M Y') }}
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Procurement Details</div>
            <table>
                <tr>
                    <td class="label">Procurement Title</td>
                    <td class="value">{{ $purchaseOrder->procurement?->title ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Procurement Reference</td>
                    <td class="value">{{ $purchaseOrder->procurement?->reference_no ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Sub-Activity</td>
                    <td class="value">{{ $purchaseOrder->subActivity?->name ?? 'N/A' }}</td>
                </tr>
                @if ($purchaseOrder->invoice)
                    <tr>
                        <td class="label">Invoice Reference</td>
                        <td class="value">{{ $purchaseOrder->invoice->reference_no ?? 'N/A' }}</td>
                    </tr>
                @endif
                @if ($purchaseOrder->negotiation)
                    <tr>
                        <td class="label">Negotiation ID</td>
                        <td class="value">{{ $purchaseOrder->negotiation->id }}</td>
                    </tr>
                @endif
            </table>
        </div>

        <div class="section">
            <div class="section-title">Vendor Details</div>
            <table>
                <tr>
                    <td class="label">Vendor Name</td>
                    <td class="value">{{ $purchaseOrder->vendor?->name ?? 'Vendor' }}</td>
                </tr>
                <tr>
                    <td class="label">Vendor Email</td>
                    <td class="value">{{ $purchaseOrder->vendor?->email ?? 'N/A' }}</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Financial Summary</div>
            <table>
                <tr>
                    <td class="label">Approved Amount</td>
                    <td class="value">
                        {{ $purchaseOrder->amount ? number_format((float) $purchaseOrder->amount, 2) : 'N/A' }}
                    </td>
                </tr>
                <tr>
                    <td class="label">Currency</td>
                    <td class="value">{{ $purchaseOrder->currency ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Created By</td>
                    <td class="value">{{ $purchaseOrder->created_by ?? 'N/A' }}</td>
                </tr>
            </table>
            <div class="divider"></div>
            <div class="small-muted">
                This purchase order is generated from the approved vendor invoice and is valid once signed.
            </div>
        </div>

        <table class="signature-table">
            <tr>
                <td>
                    <div class="signature-line"></div>
                    <div class="small-muted">Authorized Officer</div>
                </td>
                <td style="text-align:right;">
                    <div class="signature-line"></div>
                    <div class="small-muted">Vendor Representative</div>
                </td>
            </tr>
        </table>
    </body>
</html>
