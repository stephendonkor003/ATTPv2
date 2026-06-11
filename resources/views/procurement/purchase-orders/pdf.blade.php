<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Purchase Order {{ $purchaseOrder->reference_no ?? '' }}</title>
        <style>
            @page {
                margin: 24px 28px 34px;
            }

            * {
                box-sizing: border-box;
            }

            body {
                font-family: DejaVu Sans, sans-serif;
                color: #111827;
                font-size: 10.5px;
                line-height: 1.45;
            }

            table {
                width: 100%;
                border-collapse: collapse;
            }

            th,
            td {
                vertical-align: top;
            }

            .header {
                border-bottom: 4px solid #0f766e;
                padding-bottom: 12px;
                margin-bottom: 14px;
            }

            .brand {
                font-size: 12px;
                font-weight: 700;
                color: #0f766e;
                text-transform: uppercase;
                letter-spacing: 0.12em;
            }

            .title {
                font-size: 22px;
                font-weight: 700;
                color: #0f172a;
                margin-top: 5px;
            }

            .meta-box {
                border: 1px solid #cbd5e1;
                background: #f8fafc;
                padding: 9px 10px;
            }

            .badge {
                display: inline-block;
                background: #0f172a;
                color: #fff;
                font-size: 9px;
                padding: 3px 8px;
                text-transform: uppercase;
                letter-spacing: 0.08em;
            }

            .section {
                margin-top: 12px;
                border: 1px solid #dbe4ee;
            }

            .section-title {
                background: #f1f5f9;
                color: #0f172a;
                font-size: 9px;
                font-weight: 700;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                padding: 7px 9px;
                border-bottom: 1px solid #dbe4ee;
            }

            .section-body {
                padding: 9px;
            }

            .label {
                color: #64748b;
                width: 32%;
                padding: 3px 0;
            }

            .value {
                font-weight: 600;
                color: #111827;
                padding: 3px 0;
            }

            .line-table th {
                background: #f8fafc;
                border: 1px solid #dbe4ee;
                padding: 6px;
                font-size: 9px;
                text-align: left;
            }

            .line-table td {
                border: 1px solid #dbe4ee;
                padding: 6px;
            }

            .right {
                text-align: right;
            }

            .muted {
                color: #64748b;
            }

            .preline {
                white-space: pre-line;
            }

            .signature td {
                width: 50%;
                padding-top: 34px;
            }

            .signature-line {
                border-top: 1px solid #64748b;
                padding-top: 5px;
                color: #64748b;
                font-size: 9px;
            }

            .footer {
                position: fixed;
                bottom: -22px;
                left: 0;
                right: 0;
                color: #64748b;
                font-size: 8.5px;
                border-top: 1px solid #dbe4ee;
                padding-top: 6px;
            }
        </style>
    </head>
    <body>
        @php
            $sourcePurchaseRequest = $purchaseOrder->purchaseRequest ?: $purchaseOrder->budgetCommitment?->purchaseRequest;
            $lineItems = $sourcePurchaseRequest?->items ?? collect();
            $currency = $purchaseOrder->currency ?? $sourcePurchaseRequest?->currency ?? '';
            $poAmount = (float) ($purchaseOrder->amount ?? 0);
            $commitmentAmount = (float) ($purchaseOrder->budgetCommitment?->commitment_amount ?? 0);
            $vendorContactName = $purchaseOrder->vendor_contact_name ?: ($purchaseOrder->vendor?->name ?? 'N/A');
            $vendorContactEmail = $purchaseOrder->vendor_contact_email ?: ($purchaseOrder->vendor?->email ?? 'N/A');
            $programName = $sourcePurchaseRequest?->programFunding?->program?->name
                ?? $sourcePurchaseRequest?->programFunding?->program_name
                ?? 'N/A';
        @endphp

        <div class="header">
            <table>
                <tr>
                    <td style="width: 60%;">
                        <div class="brand">{{ config('app.name') }}</div>
                        <div class="title">Purchase Order</div>
                        <div class="muted">{{ $purchaseOrder->po_title ?: 'Official procurement purchase order' }}</div>
                    </td>
                    <td style="width: 40%;">
                        <div class="meta-box">
                            <table>
                                <tr>
                                    <td class="label">PO Number</td>
                                    <td class="value right">{{ $purchaseOrder->reference_no ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td class="label">Status</td>
                                    <td class="value right"><span class="badge">{{ $purchaseOrder->status ?? 'draft' }}</span></td>
                                </tr>
                                <tr>
                                    <td class="label">Issue Date</td>
                                    <td class="value right">{{ $purchaseOrder->issued_at?->format('d M Y') ?? now()->format('d M Y') }}</td>
                                </tr>
                                <tr>
                                    <td class="label">Valid Until</td>
                                    <td class="value right">{{ $purchaseOrder->valid_until?->format('d M Y') ?? 'N/A' }}</td>
                                </tr>
                            </table>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <table>
            <tr>
                <td style="width: 50%; padding-right: 6px;">
                    <div class="section" style="margin-top:0;">
                        <div class="section-title">Buyer / Bill To</div>
                        <div class="section-body">
                            <strong>{{ config('app.name') }}</strong><br>
                            <span class="preline">{{ $purchaseOrder->billing_address ?? 'N/A' }}</span><br>
                            <br>
                            <span class="muted">Contact:</span>
                            {{ $purchaseOrder->buyer_contact_name ?? 'N/A' }}<br>
                            <span class="muted">Email:</span>
                            {{ $purchaseOrder->buyer_contact_email ?? 'N/A' }}<br>
                            <span class="muted">Phone:</span>
                            {{ $purchaseOrder->buyer_contact_phone ?? 'N/A' }}
                        </div>
                    </div>
                </td>
                <td style="width: 50%; padding-left: 6px;">
                    <div class="section" style="margin-top:0;">
                        <div class="section-title">Supplier / Vendor</div>
                        <div class="section-body">
                            <strong>{{ $purchaseOrder->vendor?->name ?? 'N/A' }}</strong><br>
                            <span class="preline">{{ $purchaseOrder->vendor?->payment_address ?? 'N/A' }}</span><br>
                            <br>
                            <span class="muted">Contact:</span>
                            {{ $vendorContactName }}<br>
                            <span class="muted">Email:</span>
                            {{ $vendorContactEmail }}<br>
                            <span class="muted">Phone:</span>
                            {{ $purchaseOrder->vendor_contact_phone ?? 'N/A' }}<br>
                            <span class="muted">Tax ID:</span>
                            {{ $purchaseOrder->vendor?->payment_tax_id ?? 'N/A' }}
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <div class="section">
            <div class="section-title">Source and Procurement References</div>
            <div class="section-body">
                <table>
                    <tr>
                        <td class="label">Purchase Request</td>
                        <td class="value">{{ $sourcePurchaseRequest?->reference_no ?? 'N/A' }}</td>
                        <td class="label">Program</td>
                        <td class="value">{{ $programName }}</td>
                    </tr>
                    <tr>
                        <td class="label">Governance Node</td>
                        <td class="value">{{ $sourcePurchaseRequest?->governanceNode?->name ?? 'N/A' }}</td>
                        <td class="label">Commitment Year</td>
                        <td class="value">{{ $purchaseOrder->budgetCommitment?->commitment_year ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Procurement</td>
                        <td class="value">{{ $purchaseOrder->procurement?->title ?? 'N/A' }}</td>
                        <td class="label">Contract Ref</td>
                        <td class="value">{{ $purchaseOrder->contract_reference ?? $purchaseOrder->procurement?->reference_no ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Supplier Ref</td>
                        <td class="value">{{ $purchaseOrder->supplier_reference ?? 'N/A' }}</td>
                        <td class="label">Expected Delivery</td>
                        <td class="value">{{ $purchaseOrder->expected_delivery_date?->format('d M Y') ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Supporting Doc</td>
                        <td class="value" colspan="3">{{ $purchaseOrder->supporting_document_name ?? 'N/A' }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Line Items</div>
            <div class="section-body">
                <table class="line-table">
                    <thead>
                        <tr>
                            <th style="width: 28px;">#</th>
                            <th style="width: 16%;">Category</th>
                            <th style="width: 16%;">Resource</th>
                            <th style="width: 18%;">Deliverable</th>
                            <th>Description</th>
                            <th style="width: 14%;">Milestone Date</th>
                            <th class="right" style="width: 16%;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lineItems as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $item->resourceCategory?->name ?? 'N/A' }}</td>
                                <td>{{ $item->resource?->name ?? 'N/A' }}</td>
                                <td>{{ $item->deliverable?->title ?? 'N/A' }}</td>
                                <td>
                                    {{ $item->milestone ?? $item->object_type ?? 'N/A' }}
                                    @if ($item->budget_code || $item->work_plan_payment_basis)
                                        <br><span class="muted">{{ $item->budget_code ?? $item->work_plan_payment_basis }}</span>
                                    @endif
                                </td>
                                <td>{{ $item->milestone_date?->format('d M Y') ?? 'N/A' }}</td>
                                <td class="right">{{ $currency }} {{ number_format((float) $item->amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">No purchase request line items were found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="6" class="right">Purchase Order Amount</th>
                            <th class="right">{{ $currency }} {{ number_format($poAmount, 2) }}</th>
                        </tr>
                        <tr>
                            <th colspan="6" class="right">Selected Commitment Amount</th>
                            <th class="right">{{ $currency }} {{ number_format($commitmentAmount, 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <table>
            <tr>
                <td style="width: 50%; padding-right: 6px;">
                    <div class="section">
                        <div class="section-title">Delivery Terms</div>
                        <div class="section-body">
                            <table>
                                <tr>
                                    <td class="label">Ship To</td>
                                    <td class="value preline">{{ $purchaseOrder->shipping_address ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td class="label">Location</td>
                                    <td class="value preline">{{ $purchaseOrder->delivery_location ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td class="label">Incoterm</td>
                                    <td class="value">{{ $purchaseOrder->incoterm ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td class="label">Terms</td>
                                    <td class="value">{{ $purchaseOrder->delivery_terms ?? 'N/A' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </td>
                <td style="width: 50%; padding-left: 6px;">
                    <div class="section">
                        <div class="section-title">Payment and Quality</div>
                        <div class="section-body">
                            <table>
                                <tr>
                                    <td class="label">Payment</td>
                                    <td class="value">{{ $purchaseOrder->payment_terms ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td class="label">Inspection</td>
                                    <td class="value">{{ $purchaseOrder->inspection_requirements ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td class="label">Warranty</td>
                                    <td class="value">{{ $purchaseOrder->warranty_terms ?? 'N/A' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <div class="section">
            <div class="section-title">Terms, Conditions, and Instructions</div>
            <div class="section-body">
                <strong>Special Instructions</strong><br>
                <span class="preline">{{ $purchaseOrder->special_instructions ?? 'N/A' }}</span>
                <br><br>
                <strong>Terms and Conditions</strong><br>
                <span class="preline">{{ $purchaseOrder->terms_conditions ?? 'N/A' }}</span>
            </div>
        </div>

        <table class="signature">
            <tr>
                <td style="padding-right: 24px;">
                    <div class="signature-line">Authorized Buyer Signature / Date</div>
                </td>
                <td style="padding-left: 24px;">
                    <div class="signature-line">Supplier Acceptance Signature / Date</div>
                </td>
            </tr>
        </table>

        <div class="footer">
            {{ config('app.name') }} | Purchase Order {{ $purchaseOrder->reference_no ?? 'N/A' }} | Generated {{ now()->format('d M Y H:i') }}
        </div>
    </body>
</html>
