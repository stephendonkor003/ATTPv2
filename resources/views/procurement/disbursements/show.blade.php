@extends('layouts.app')

@push('styles')
    <style>
        .disb-show {
            padding-bottom: 2rem;
        }

        .disb-show .hero-card {
            background: linear-gradient(120deg, #0f172a 0%, #1e293b 40%, #14b8a6 100%);
            border: none;
            border-radius: 16px;
            color: #fff;
            overflow: hidden;
        }

        .disb-show .hero-card h1,
        .disb-show .hero-card h2,
        .disb-show .hero-card h3,
        .disb-show .hero-card h4,
        .disb-show .hero-card h5,
        .disb-show .hero-card h6,
        .disb-show .hero-card p,
        .disb-show .hero-card .text-muted {
            color: #fff !important;
        }

        .disb-show .receipt-pill {
            background: rgba(255, 255, 255, .14);
            border: 1px solid rgba(255, 255, 255, .24);
            border-radius: 999px;
            display: inline-flex;
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: 0;
            margin-bottom: 8px;
            padding: 4px 10px;
            text-transform: uppercase;
        }

        .disb-show .hero-amount {
            font-size: 1.75rem;
            font-weight: 800;
            line-height: 1.15;
        }

        .disb-show .hero-note {
            color: rgba(255, 255, 255, .76);
            font-size: .86rem;
        }

        .disb-show .content-card {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow: 0 16px 30px rgba(15, 23, 42, .08);
        }

        .disb-show .content-card .card-body {
            padding: 20px;
        }

        .disb-show .summary-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .disb-show .summary-tile {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            min-height: 96px;
            padding: 14px;
        }

        .disb-show .tile-label,
        .disb-show .section-label {
            color: #64748b;
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .disb-show .tile-value {
            color: #0f172a;
            font-size: 1.08rem;
            font-weight: 800;
            line-height: 1.25;
            margin-top: 6px;
            overflow-wrap: anywhere;
        }

        .disb-show .tile-note {
            color: #64748b;
            display: block;
            font-size: .78rem;
            margin-top: 4px;
        }

        .disb-show .reference-list {
            border-collapse: separate;
            border-spacing: 0 8px;
            margin-bottom: 0;
        }

        .disb-show .reference-list th,
        .disb-show .reference-list td {
            background: #fff;
            border: 1px solid #e8eef6;
            padding: 10px 12px;
            vertical-align: middle;
        }

        .disb-show .reference-list th {
            border-right: 0;
            border-radius: 10px 0 0 10px;
            color: #64748b;
            font-size: .76rem;
            text-transform: uppercase;
            width: 178px;
        }

        .disb-show .reference-list td {
            border-left: 0;
            border-radius: 0 10px 10px 0;
            color: #1f2937;
        }

        .disb-show .paid-lines-card .table {
            min-width: 980px;
        }

        .disb-show .paid-lines-card th {
            color: #475569;
            font-size: .74rem;
            letter-spacing: 0;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .disb-show .paid-lines-card td {
            vertical-align: middle;
        }

        .disb-show .line-deliverable {
            max-width: 360px;
            white-space: normal;
        }

        @media (max-width: 1199.98px) {
            .disb-show .summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .disb-show .summary-grid {
                grid-template-columns: 1fr;
            }

            .disb-show .reference-list th,
            .disb-show .reference-list td {
                display: block;
                width: 100%;
            }

            .disb-show .reference-list th {
                border-radius: 10px 10px 0 0;
                border-right: 1px solid #e8eef6;
                padding-bottom: 4px;
            }

            .disb-show .reference-list td {
                border-left: 1px solid #e8eef6;
                border-radius: 0 0 10px 10px;
                border-top: 0;
                padding-top: 4px;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $purchaseOrder = $disbursement->purchaseOrder;
        $sourcePurchaseRequest = $purchaseOrder?->purchaseRequest ?: $purchaseOrder?->budgetCommitment?->purchaseRequest;
        $lineItems = $sourcePurchaseRequest?->items ?? collect();
        $evidenceByItem = $purchaseOrder?->lineItemEvidence?->keyBy(fn ($evidence) => (string) $evidence->purchase_request_item_id) ?? collect();
        $currency = $disbursement->currency ?? $purchaseOrder?->currency ?? $sourcePurchaseRequest?->currency ?? '';
        $money = fn ($value) => trim(($currency ? $currency . ' ' : '') . number_format((float) $value, 2));
        $statusValue = strtolower((string) ($disbursement->status ?? 'completed'));
        $paidDisbursementStatuses = ['completed', 'paid', 'fully_paid'];
        $paidDisbursements = $purchaseOrder?->disbursements?->filter(fn ($receipt) => $receipt->deliverable_id
            && (
                in_array(strtolower((string) $receipt->status), $paidDisbursementStatuses, true)
                || strtolower((string) $receipt->recipient_confirmation_status) === 'confirmed'
            )
        ) ?? collect();
        $paidDisbursementsByDeliverable = $paidDisbursements->groupBy(fn ($receipt) => (string) $receipt->deliverable_id);
        $paidLineItems = $lineItems->filter(function ($item) use ($evidenceByItem, $paidDisbursementsByDeliverable) {
            $itemEvidence = $evidenceByItem->get((string) $item->id);
            $hasPaidReceipt = $item->deliverable_id
                && $paidDisbursementsByDeliverable->has((string) $item->deliverable_id);

            return (bool) $itemEvidence?->is_met || $hasPaidReceipt;
        })->values();
        $currentReceiptDeliverableId = (string) ($disbursement->deliverable_id ?? '');
        $currentReceiptLineItems = $lineItems->filter(fn ($item) => $currentReceiptDeliverableId !== ''
            && (string) ($item->deliverable_id ?? '') === $currentReceiptDeliverableId
        )->values();
        $paidLineItemsTotal = $paidLineItems->sum(fn ($item) => (float) ($item->amount ?? 0));
        $poAmount = (float) ($purchaseOrder?->amount ?? 0);
        $poPaidAmount = $purchaseOrder ? (float) $purchaseOrder->paidAmount() : 0;
        $poBalanceAmount = $purchaseOrder ? (float) $purchaseOrder->remainingAmount() : 0;
        $requestAmount = (float) ($sourcePurchaseRequest?->total_amount ?? 0);
        $paymentStatusClass = match ($statusValue) {
            'completed', 'paid', 'fully_paid' => 'bg-success',
            'pending' => 'bg-warning text-dark',
            'cancelled', 'void', 'reversed' => 'bg-danger',
            default => 'bg-secondary',
        };
        $poStatusClass = match ($purchaseOrder?->status) {
            'paid', 'closed' => 'bg-success',
            'issued' => 'bg-primary',
            'partial_paid' => 'bg-warning text-dark',
            'cancelled' => 'bg-danger',
            default => 'bg-secondary',
        };
        $prStatusClass = match ($sourcePurchaseRequest?->status) {
            'approved' => 'bg-success',
            'submitted' => 'bg-warning text-dark',
            'rejected', 'cancelled' => 'bg-danger',
            default => 'bg-secondary',
        };
        $programName = $sourcePurchaseRequest?->programFunding?->program?->name
            ?? $sourcePurchaseRequest?->programFunding?->program_name
            ?? 'N/A';
        $vendorName = $purchaseOrder?->vendor?->name ?? $disbursement->vendor?->name ?? 'Vendor';
        $vendorEmail = $purchaseOrder?->vendor?->email ?? $disbursement->vendor?->email ?? 'N/A';
    @endphp

    <div class="nxl-container disb-show">
        <div class="card hero-card mb-4">
            <div class="card-body d-flex flex-column flex-xl-row justify-content-between align-items-start gap-3">
                <div class="min-w-0">
                    <span class="receipt-pill">Disbursement Receipt</span>
                    <h4 class="fw-bold mb-1">{{ $disbursement->reference_no ?? 'N/A' }}</h4>
                    <p class="mb-0">
                        {{ $purchaseOrder?->reference_no ?? 'No PO reference' }}
                        @if ($sourcePurchaseRequest?->reference_no)
                            | {{ $sourcePurchaseRequest->reference_no }}
                        @endif
                    </p>
                </div>
                <div class="text-xl-end">
                    <div class="hero-amount">{{ $money($disbursement->amount) }}</div>
                    <div class="hero-note mb-3">
                        Paid {{ $disbursement->paid_at?->format('d M Y') ?? 'N/A' }}
                        via {{ $disbursement->payment_method ?? 'N/A' }}
                    </div>
                    <div class="d-flex flex-wrap justify-content-xl-end gap-2">
                        @if ($canEditDisbursements)
                            <a href="{{ route('procurement.disbursements.edit', $disbursement) }}" class="btn btn-warning">
                                <i class="feather-edit-2 me-1"></i> Edit
                            </a>
                        @endif
                        <a href="{{ route('procurement.disbursements.pdf', $disbursement) }}" class="btn btn-light">
                            <i class="feather-eye me-1"></i> View PDF
                        </a>
                        <a href="{{ route('procurement.disbursements.download', $disbursement) }}" class="btn btn-primary">
                            <i class="feather-download me-1"></i> Download PDF
                        </a>
                        <a href="{{ route('procurement.disbursements.index') }}" class="btn btn-outline-light">
                            <i class="feather-arrow-left me-1"></i> Back
                        </a>
                    </div>
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="summary-grid mb-4">
            <div class="summary-tile">
                <div class="tile-label">Receipt Amount</div>
                <div class="tile-value">{{ $money($disbursement->amount) }}</div>
                <span class="tile-note">{{ $disbursement->transfer_reference ?: 'No transfer reference' }}</span>
            </div>
            <div class="summary-tile">
                <div class="tile-label">PO Total</div>
                <div class="tile-value">{{ $money($poAmount) }}</div>
                <span class="tile-note">{{ $purchaseOrder?->reference_no ?? 'N/A' }}</span>
            </div>
            <div class="summary-tile">
                <div class="tile-label">PO Paid</div>
                <div class="tile-value">{{ $money($poPaidAmount) }}</div>
                <span class="tile-note">Balance {{ $money($poBalanceAmount) }}</span>
            </div>
            <div class="summary-tile">
                <div class="tile-label">Paid Line Items</div>
                <div class="tile-value">{{ $paidLineItems->count() }}</div>
                <span class="tile-note">{{ $money($paidLineItemsTotal) }} confirmed/paid</span>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-4">
                <div class="card content-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div>
                                <div class="section-label mb-1">Payment Summary</div>
                                <h5 class="fw-bold mb-0">{{ $disbursement->reference_no ?? 'N/A' }}</h5>
                            </div>
                            <span class="badge {{ $paymentStatusClass }} text-capitalize">
                                {{ str_replace('_', ' ', $disbursement->status ?? 'completed') }}
                            </span>
                        </div>

                        <table class="table table-sm reference-list">
                            <tr>
                                <th>Amount</th>
                                <td class="fw-semibold">{{ $money($disbursement->amount) }}</td>
                            </tr>
                            <tr>
                                <th>Paid At</th>
                                <td>{{ $disbursement->paid_at?->format('d M Y') ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Method</th>
                                <td>{{ $disbursement->payment_method ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Transfer Ref</th>
                                <td>{{ $disbursement->transfer_reference ?: 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Vendor</th>
                                <td>
                                    <div class="fw-semibold">{{ $vendorName }}</div>
                                    <div class="small text-muted">{{ $vendorEmail }}</div>
                                </td>
                            </tr>
                            <tr>
                                <th>Deliverable</th>
                                <td>{{ $disbursement->deliverable?->title ?? 'N/A' }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card content-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div>
                                <div class="section-label mb-1">Reference From PO</div>
                                <h5 class="fw-bold mb-0">{{ $purchaseOrder?->reference_no ?? 'N/A' }}</h5>
                            </div>
                            <span class="badge {{ $poStatusClass }} text-capitalize">
                                {{ str_replace('_', ' ', $purchaseOrder?->status ?? 'N/A') }}
                            </span>
                        </div>

                        <table class="table table-sm reference-list">
                            <tr>
                                <th>PO Title</th>
                                <td>{{ $purchaseOrder?->po_title ?: ($purchaseOrder?->procurement?->title ?? 'N/A') }}</td>
                            </tr>
                            <tr>
                                <th>PO Amount</th>
                                <td class="fw-semibold">{{ $money($poAmount) }}</td>
                            </tr>
                            <tr>
                                <th>Total Paid</th>
                                <td>{{ $money($poPaidAmount) }}</td>
                            </tr>
                            <tr>
                                <th>Balance</th>
                                <td>{{ $money($poBalanceAmount) }}</td>
                            </tr>
                            <tr>
                                <th>Expected Delivery</th>
                                <td>{{ $purchaseOrder?->expected_delivery_date?->format('d M Y') ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Procurement Ref</th>
                                <td>{{ $purchaseOrder?->procurement?->reference_no ?? $purchaseOrder?->contract_reference ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Open PO</th>
                                <td>
                                    @if ($purchaseOrder)
                                        <a href="{{ route('procurement.purchase-orders.show', $purchaseOrder) }}" class="btn btn-sm btn-outline-primary">
                                            View Purchase Order
                                        </a>
                                    @else
                                        N/A
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card content-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div>
                                <div class="section-label mb-1">Reference From PR</div>
                                <h5 class="fw-bold mb-0">{{ $sourcePurchaseRequest?->reference_no ?? 'N/A' }}</h5>
                            </div>
                            <span class="badge {{ $prStatusClass }} text-capitalize">
                                {{ str_replace('_', ' ', $sourcePurchaseRequest?->status ?? 'N/A') }}
                            </span>
                        </div>

                        <table class="table table-sm reference-list">
                            <tr>
                                <th>Program</th>
                                <td>{{ $programName }}</td>
                            </tr>
                            <tr>
                                <th>Sub-Activity</th>
                                <td>{{ $sourcePurchaseRequest?->subActivity?->name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Governance Node</th>
                                <td>{{ $sourcePurchaseRequest?->governanceNode?->name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Request Amount</th>
                                <td class="fw-semibold">{{ $money($requestAmount) }}</td>
                            </tr>
                            <tr>
                                <th>Commitment Date</th>
                                <td>{{ $sourcePurchaseRequest?->commitment_date?->format('d M Y') ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Delivery Date</th>
                                <td>{{ $sourcePurchaseRequest?->delivery_date?->format('d M Y') ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Open PR</th>
                                <td>
                                    @if ($sourcePurchaseRequest)
                                        <a href="{{ route('finance.purchase-requests.show', $sourcePurchaseRequest) }}" class="btn btn-sm btn-outline-primary">
                                            View Purchase Request
                                        </a>
                                    @else
                                        N/A
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card content-card paid-lines-card mt-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-2 mb-3">
                    <div>
                        <div class="section-label mb-1">Paid PO Item Lines</div>
                        <h5 class="fw-bold mb-1">Line Items Paid or Confirmed Against This PO</h5>
                        <div class="text-muted small">
                            Items tagged "This receipt" are linked to the deliverable paid by {{ $disbursement->reference_no ?? 'this receipt' }}.
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-light text-dark border px-3 py-2">
                            {{ $paidLineItems->count() }} paid {{ $paidLineItems->count() === 1 ? 'item' : 'items' }}
                        </span>
                        <span class="badge bg-success px-3 py-2">{{ $money($paidLineItemsTotal) }}</span>
                    </div>
                </div>

                @if ($paidLineItems->isEmpty())
                    <div class="alert alert-light border mb-0">
                        No paid PO item lines were found for this purchase order yet.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 52px;">#</th>
                                    <th>Category</th>
                                    <th>Resource Item</th>
                                    <th>Deliverable</th>
                                    <th style="width: 190px;">Payment Receipt</th>
                                    <th style="width: 160px;">Evidence</th>
                                    <th style="width: 150px;">Deliverable Date</th>
                                    <th class="text-end" style="width: 150px;">Line Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($paidLineItems as $item)
                                    @php
                                        $itemEvidence = $evidenceByItem->get((string) $item->id);
                                        $itemDocuments = collect($itemEvidence?->documents ?? []);
                                        $itemReceipts = $item->deliverable_id
                                            ? $paidDisbursementsByDeliverable->get((string) $item->deliverable_id, collect())
                                            : collect();
                                        $isCurrentReceiptLine = $currentReceiptLineItems->contains(fn ($currentItem) => (string) $currentItem->id === (string) $item->id);
                                    @endphp
                                    <tr>
                                        <td class="text-muted">{{ $loop->iteration }}</td>
                                        <td>{{ $item->resourceCategory?->name ?? 'N/A' }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $item->resource?->name ?? 'N/A' }}</div>
                                            @if ($item->budget_code)
                                                <div class="small text-muted">{{ $item->budget_code }}</div>
                                            @endif
                                        </td>
                                        <td class="line-deliverable">
                                            <div class="fw-semibold">{{ $item->milestone ?: ($item->deliverable?->title ?? 'N/A') }}</div>
                                            @if ($item->deliverable?->procurement)
                                                <div class="small text-muted">
                                                    {{ $item->deliverable->procurement->reference_no ?? $item->deliverable->procurement->title }}
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($itemReceipts->isNotEmpty())
                                                <div class="d-flex flex-wrap gap-1">
                                                    @foreach ($itemReceipts as $receipt)
                                                        <span class="badge {{ (string) $receipt->id === (string) $disbursement->id ? 'bg-success' : 'bg-light text-dark border' }}">
                                                            {{ $receipt->reference_no ?? 'Receipt' }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="badge bg-info-subtle text-info">Evidence confirmed</span>
                                            @endif
                                            @if ($isCurrentReceiptLine)
                                                <div class="mt-1">
                                                    <span class="badge bg-primary">This receipt</span>
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($itemEvidence)
                                                <span class="badge {{ $itemEvidence->is_met ? 'bg-success' : 'bg-info' }}">
                                                    {{ $itemEvidence->is_met ? 'Confirmed' : 'Recorded' }}
                                                </span>
                                                @if ($itemDocuments->isNotEmpty())
                                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                                        @foreach ($itemDocuments as $documentIndex => $document)
                                                            <a href="{{ route('procurement.purchase-orders.line-item-evidence.document', [$purchaseOrder, $itemEvidence, $documentIndex]) }}?download=1"
                                                                class="badge bg-light text-dark border"
                                                                title="{{ $document['name'] ?? 'Document' }}">
                                                                {{ $document['display_name'] ?? $document['name'] ?? 'Document' }}
                                                            </a>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            @else
                                                <span class="badge bg-light text-muted border">Paid</span>
                                            @endif
                                        </td>
                                        <td>{{ $itemEvidence?->deliverable_date?->format('d M Y') ?? $item->milestone_date?->format('d M Y') ?? 'N/A' }}</td>
                                        <td class="text-end fw-semibold">{{ $money($item->amount) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="7" class="text-end">Paid Line Total</th>
                                    <th class="text-end">{{ $money($paidLineItemsTotal) }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        @if ($disbursement->notes)
            <div class="card content-card mt-4">
                <div class="card-body">
                    <div class="section-label mb-2">Receipt Notes</div>
                    <div>{{ $disbursement->notes }}</div>
                </div>
            </div>
        @endif
    </div>
@endsection
