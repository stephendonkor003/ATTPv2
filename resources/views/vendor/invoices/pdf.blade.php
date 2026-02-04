<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Invoice {{ $invoice->reference_no ?? '' }}</title>
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
                background: #1d4ed8;
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

            td,
            th {
                padding: 8px 6px;
                vertical-align: top;
            }

            .divider {
                height: 1px;
                background: #e2e8f0;
                margin: 12px 0;
            }

            .small-muted {
                font-size: 10px;
                color: #94a3b8;
            }

            .table {
                width: 100%;
                border-collapse: collapse;
            }

            .table thead th {
                text-align: left;
                background: #f8fafc;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                color: #64748b;
            }

            .table tbody td {
                border-bottom: 1px solid #e2e8f0;
            }

            .total-row td {
                border-top: 2px solid #0f172a;
                font-weight: 700;
            }

            .summary-table td {
                padding: 6px 0;
            }

            .summary-label {
                text-align: right;
                color: #64748b;
            }

            .summary-value {
                text-align: right;
                font-weight: 700;
            }
        </style>
    </head>
    <body>
        @php
            $currency = $invoice->currency ?? '';
            $invoiceDate = $invoice->created_at?->format('d M Y') ?? now()->format('d M Y');
            $invoiceMonth = $invoice->invoice_month?->format('M Y') ?? now()->format('M Y');
            $dueDate = $invoice->invoice_month
                ? \Illuminate\Support\Carbon::parse($invoice->invoice_month)->endOfMonth()->format('d M Y')
                : now()->endOfMonth()->format('d M Y');
            $amount = (float) ($invoice->amount ?? 0);
        @endphp

        <div class="header">
            <table>
                <tr>
                    <td>
                        <div class="header-title">Invoice</div>
                        <div class="header-subtitle">Issued by {{ $invoice->vendor?->name ?? 'Vendor' }}</div>
                    </td>
                    <td style="text-align:right;">
                        <div class="badge">{{ $invoice->status ?? 'submitted' }}</div>
                        <div style="margin-top:8px; font-size:12px;">
                            Reference: <strong>{{ $invoice->reference_no ?? 'N/A' }}</strong>
                        </div>
                        <div class="small-muted">
                            Invoice Date: {{ $invoiceDate }}<br>
                            Invoice Month: {{ $invoiceMonth }}<br>
                            Due Date: {{ $dueDate }}
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Parties</div>
            <table>
                <tr>
                    <td style="width: 50%;">
                        <div class="small-muted">Invoice From</div>
                        <div class="value">{{ $invoice->vendor?->name ?? 'Vendor' }}</div>
                        <div class="small-muted">{{ $invoice->vendor?->email ?? 'N/A' }}</div>
                    </td>
                    <td style="width: 50%; text-align:right;">
                        <div class="small-muted">Invoice To</div>
                        <div class="value">{{ config('app.name') }}</div>
                        <div class="small-muted">Procurement Office</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Invoice Items</div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Reference</th>
                        <th>Period</th>
                        <th style="text-align:right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            {{ $invoice->procurement?->title ?? 'Procurement Service' }}<br>
                            <span class="small-muted">Sub-Activity: {{ $invoice->subActivity?->name ?? 'N/A' }}</span>
                        </td>
                        <td>{{ $invoice->procurement?->reference_no ?? 'N/A' }}</td>
                        <td>{{ $invoiceMonth }}</td>
                        <td style="text-align:right;">
                            {{ number_format($amount, 2) }} {{ $currency }}
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="divider"></div>
            <table class="summary-table">
                <tr>
                    <td class="summary-label">Subtotal</td>
                    <td class="summary-value">{{ number_format($amount, 2) }} {{ $currency }}</td>
                </tr>
                <tr>
                    <td class="summary-label">Tax</td>
                    <td class="summary-value">{{ number_format(0, 2) }} {{ $currency }}</td>
                </tr>
                <tr class="total-row">
                    <td class="summary-label">Total Due</td>
                    <td class="summary-value">{{ number_format($amount, 2) }} {{ $currency }}</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Notes & Terms</div>
            <div class="value">{{ $invoice->notes ?? 'Payment due within 30 days of invoice date.' }}</div>
            <div class="divider"></div>
            <div class="small-muted">
                This invoice is generated through the vendor portal and will be reviewed by the procurement team.
            </div>
        </div>

        <div class="section">
            <div class="section-title">Payment Instructions</div>
            <table>
                <tr>
                    <td class="label">Preferred Method</td>
                    <td class="value">{{ $invoice->vendor?->payment_method_preference ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Bank Name</td>
                    <td class="value">{{ $invoice->vendor?->payment_bank_name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Account Name</td>
                    <td class="value">{{ $invoice->vendor?->payment_account_name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Account Number</td>
                    <td class="value">{{ $invoice->vendor?->payment_account_number ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">SWIFT Code</td>
                    <td class="value">{{ $invoice->vendor?->payment_swift_code ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">IBAN</td>
                    <td class="value">{{ $invoice->vendor?->payment_iban ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Mobile Money</td>
                    <td class="value">
                        {{ $invoice->vendor?->payment_mobile_provider ?? 'N/A' }}
                        {{ $invoice->vendor?->payment_mobile_number ? ' · ' . $invoice->vendor?->payment_mobile_number : '' }}
                    </td>
                </tr>
                <tr>
                    <td class="label">Tax ID</td>
                    <td class="value">{{ $invoice->vendor?->payment_tax_id ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Payment Address</td>
                    <td class="value">{{ $invoice->vendor?->payment_address ?? 'N/A' }}</td>
                </tr>
            </table>
        </div>
    </body>
</html>
