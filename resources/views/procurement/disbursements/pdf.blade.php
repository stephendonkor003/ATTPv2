<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Payment Receipt {{ $disbursement->reference_no ?? '' }}</title>
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
                background: #14b8a6;
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
        </style>
    </head>
    <body>
        <div class="header">
            <table>
                <tr>
                    <td>
                        <div class="header-title">Payment Receipt</div>
                        <div class="header-subtitle">{{ config('app.name') }}</div>
                    </td>
                    <td style="text-align:right;">
                        <div class="badge">{{ $disbursement->status ?? 'completed' }}</div>
                        <div style="margin-top:8px; font-size:12px;">
                            Receipt: <strong>{{ $disbursement->reference_no ?? 'N/A' }}</strong>
                        </div>
                        <div style="font-size:10px; color:#cbd5f5;">
                            Paid At: {{ $disbursement->paid_at?->format('M d, Y') ?? now()->format('M d, Y') }}
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Purchase Order</div>
            <table>
                <tr>
                    <td class="label">Purchase Order Ref</td>
                    <td class="value">{{ $disbursement->purchaseOrder?->reference_no ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Procurement</td>
                    <td class="value">{{ $disbursement->procurement?->title ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Procurement Ref</td>
                    <td class="value">{{ $disbursement->procurement?->reference_no ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Sub-Activity</td>
                    <td class="value">{{ $disbursement->subActivity?->name ?? 'N/A' }}</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Vendor</div>
            <table>
                <tr>
                    <td class="label">Vendor Name</td>
                    <td class="value">{{ $disbursement->vendor?->name ?? 'Vendor' }}</td>
                </tr>
                <tr>
                    <td class="label">Vendor Email</td>
                    <td class="value">{{ $disbursement->vendor?->email ?? 'N/A' }}</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Payment Summary</div>
            <table>
                <tr>
                    <td class="label">Amount</td>
                    <td class="value">
                        {{ $disbursement->amount ? number_format((float) $disbursement->amount, 2) : 'N/A' }}
                        {{ $disbursement->currency ?? '' }}
                    </td>
                </tr>
                <tr>
                    <td class="label">Payment Method</td>
                    <td class="value">{{ $disbursement->payment_method ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Notes</td>
                    <td class="value">{{ $disbursement->notes ?? 'N/A' }}</td>
                </tr>
            </table>
            <div class="divider"></div>
            <div style="font-size:10px; color:#94a3b8;">
                This receipt confirms payment against the referenced purchase order.
            </div>
        </div>
    </body>
</html>
