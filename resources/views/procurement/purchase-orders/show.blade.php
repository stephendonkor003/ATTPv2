@extends('layouts.app')

@push('styles')
    <style>
        .po-show .po-document {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.07);
        }

        .po-show .po-document-header {
            border-bottom: 1px solid #e2e8f0;
            padding: 20px;
        }

        .po-show .po-document-body {
            padding: 20px;
        }

        .po-show .stat-tile {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px;
            height: 100%;
        }

        .po-show .stat-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
        }

        .po-show .stat-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: #0f172a;
            overflow-wrap: anywhere;
        }

        .po-show .section-title {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .po-show .white-space-pre-line {
            white-space: pre-line;
        }
    </style>
@endpush

@section('content')
    @php
        $sourcePurchaseRequest = $purchaseOrder->purchaseRequest ?: $purchaseOrder->budgetCommitment?->purchaseRequest;
        $lineItems = $sourcePurchaseRequest?->items ?? collect();
        $evidenceByItem = $purchaseOrder->lineItemEvidence->keyBy('purchase_request_item_id');
        $currency = $purchaseOrder->resolved_currency;
        $paidDisbursementStatuses = ['completed', 'paid', 'fully_paid'];
        $paidDisbursements = $purchaseOrder->disbursements
            ->filter(fn ($disbursement) => $disbursement->paid_at
                && in_array(strtolower((string) $disbursement->status), $paidDisbursementStatuses, true));
        $paidAmountsByItem = $paidDisbursements
            ->filter(fn ($disbursement) => filled($disbursement->purchase_request_item_id))
            ->groupBy(fn ($disbursement) => (string) $disbursement->purchase_request_item_id)
            ->map(fn ($receipts) => round($receipts->sum(fn ($receipt) => (float) $receipt->amount), 2));
        $legacyPaidDeliverableIds = $paidDisbursements
            ->filter(fn ($disbursement) => blank($disbursement->purchase_request_item_id) && $disbursement->deliverable_id)
            ->pluck('deliverable_id')
            ->map(fn ($deliverableId) => (string) $deliverableId)
            ->values()
            ->all();
        $linePaidAmountForItem = function ($item) use ($paidAmountsByItem, $legacyPaidDeliverableIds): float {
            $lineAmount = (float) ($item->amount ?? 0);
            $paidAmount = (float) $paidAmountsByItem->get((string) $item->id, 0);

            if ($paidAmount <= 0 && $item->deliverable_id && in_array((string) $item->deliverable_id, $legacyPaidDeliverableIds, true)) {
                $paidAmount = $lineAmount;
            }

            return round(min($lineAmount, $paidAmount), 2);
        };
        $lineItemTotalAmount = round($lineItems->sum(fn ($item) => (float) ($item->amount ?? 0)), 2);
        $lineItemPaidAmount = round($lineItems->sum($linePaidAmountForItem), 2);
        $lineItemPendingAmount = round(max($lineItemTotalAmount - $lineItemPaidAmount, 0), 2);
        $summaryPoAmount = $lineItems->isNotEmpty() ? $lineItemTotalAmount : (float) ($purchaseOrder->amount ?? 0);
        $summaryPaidAmount = $lineItems->isNotEmpty() ? $lineItemPaidAmount : $purchaseOrder->paidAmount();
        $summaryBalanceAmount = $lineItems->isNotEmpty() ? $lineItemPendingAmount : $purchaseOrder->remainingAmount();
        $vendorContactName = $purchaseOrder->vendor_contact_name ?: ($purchaseOrder->vendor?->name ?? 'N/A');
        $vendorContactEmail = $purchaseOrder->vendor_contact_email ?: ($purchaseOrder->vendor?->email ?? 'N/A');
        $statusClass = match ($purchaseOrder->status) {
            'issued' => 'bg-primary',
            'closed' => 'bg-success',
            'cancelled' => 'bg-danger',
            default => 'bg-secondary',
        };
    @endphp

    <div class="nxl-container po-show">
        <div class="page-header d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
            <div>
                <h4 class="fw-bold mb-1">{{ $purchaseOrder->po_title ?: 'Purchase Order' }}</h4>
                <p class="text-muted mb-0">{{ $purchaseOrder->reference_no ?? 'N/A' }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @can('finance.purchase_orders.create')
                    <a href="{{ route('procurement.purchase-orders.edit', $purchaseOrder) }}" class="btn btn-outline-secondary">
                        <i class="feather-edit-2 me-1"></i> Edit
                    </a>
                @endcan
                <a href="{{ route('procurement.purchase-orders.pdf', $purchaseOrder) }}" class="btn btn-outline-primary">
                    <i class="feather-eye me-1"></i> View PDF
                </a>
                <a href="{{ route('procurement.purchase-orders.download', $purchaseOrder) }}" class="btn btn-primary">
                    <i class="feather-download me-1"></i> Download PDF
                </a>
                @if ($purchaseOrder->supporting_document_path)
                    <a href="{{ route('procurement.purchase-orders.supporting-document', $purchaseOrder) }}?download=1" class="btn btn-outline-success">
                        <i class="feather-paperclip me-1"></i> Supporting Document
                    </a>
                @endif
                @can('finance.purchase_orders.delete')
                    <form method="POST" action="{{ url('procurement/purchase-orders/' . $purchaseOrder->getKey() . '/delete') }}"
                        onsubmit="return confirm('Delete this purchase order? Payment records will be kept but detached from this order.');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="feather-trash-2 me-1"></i> Delete
                        </button>
                    </form>
                @endcan
                <a href="{{ route('procurement.purchase-orders.index') }}" class="btn btn-outline-secondary">
                    <i class="feather-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success mt-3">{{ session('success') }}</div>
        @endif

        <div class="po-document mt-4">
            <div class="po-document-header d-flex flex-column flex-lg-row justify-content-between gap-3">
                <div>
                    <div class="section-title">Official Purchase Order</div>
                    <h5 class="fw-bold mb-1">{{ $purchaseOrder->reference_no ?? 'N/A' }}</h5>
                    <div class="text-muted">{{ $purchaseOrder->po_title ?: 'Procurement purchase order' }}</div>
                </div>
                <div class="text-lg-end">
                    <span class="badge {{ $statusClass }} text-capitalize px-3 py-2">
                        {{ str_replace('_', ' ', $purchaseOrder->status ?? 'draft') }}
                    </span>
                    <div class="text-muted small mt-2">
                        Issued: {{ $purchaseOrder->issued_at?->format('d M Y') ?? 'N/A' }}
                    </div>
                </div>
            </div>

            <div class="po-document-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="stat-tile">
                            <div class="stat-label">PO Amount</div>
                            <div class="stat-value">{{ $currency }} {{ number_format($summaryPoAmount, 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-tile">
                            <div class="stat-label">Paid / Confirmed</div>
                            <div class="stat-value">{{ $currency }} {{ number_format($summaryPaidAmount, 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-tile">
                            <div class="stat-label">Pending Balance</div>
                            <div class="stat-value">{{ $currency }} {{ number_format($summaryBalanceAmount, 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-tile">
                            <div class="stat-label">Expected Delivery</div>
                            <div class="stat-value">{{ $purchaseOrder->expected_delivery_date?->format('d M Y') ?? 'N/A' }}</div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mt-2">
                    <div class="col-lg-6">
                        <div class="section-title">Source Request and Funding</div>
                        <table class="table table-sm">
                            <tr>
                                <th style="width: 180px;">Purchase Request</th>
                                <td>{{ $sourcePurchaseRequest?->reference_no ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Program</th>
                                <td>{{ $sourcePurchaseRequest?->programFunding?->program?->name ?? $sourcePurchaseRequest?->programFunding?->program_name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Governance Node</th>
                                <td>{{ $sourcePurchaseRequest?->governanceNode?->name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Commitment Year</th>
                                <td>{{ $purchaseOrder->budgetCommitment?->commitment_year ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Commitment Amount</th>
                                <td>{{ $currency }} {{ number_format((float) ($purchaseOrder->budgetCommitment?->commitment_amount ?? 0), 2) }}</td>
                            </tr>
                        </table>
                    </div>

                    <div class="col-lg-6">
                        <div class="section-title">Procurement and Supplier</div>
                        <table class="table table-sm">
                            <tr>
                                <th style="width: 180px;">Procurement</th>
                                <td>{{ $purchaseOrder->procurement?->title ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Procurement Ref</th>
                                <td>{{ $purchaseOrder->procurement?->reference_no ?? $purchaseOrder->contract_reference ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Vendor</th>
                                <td>{{ $purchaseOrder->vendor?->name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Vendor Contact</th>
                                <td>
                                    {{ $vendorContactName }}
                                    <div class="small text-muted">{{ $vendorContactEmail }}</div>
                                </td>
                            </tr>
                            <tr>
                                <th>Supplier Ref</th>
                                <td>{{ $purchaseOrder->supplier_reference ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Supporting Document</th>
                                <td>
                                    @if ($purchaseOrder->supporting_document_path)
                                        <a href="{{ route('procurement.purchase-orders.supporting-document', $purchaseOrder) }}?download=1">
                                            {{ $purchaseOrder->supporting_document_name ?? basename($purchaseOrder->supporting_document_path) }}
                                        </a>
                                        @if ($purchaseOrder->supporting_document_size)
                                            <div class="small text-muted">{{ number_format($purchaseOrder->supporting_document_size / 1024, 1) }} KB</div>
                                        @endif
                                    @else
                                        N/A
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                @if ($purchaseOrder->deliverables->isNotEmpty())
                <div class="mt-4">
                    <div class="section-title">Deliverables &amp; Milestones</div>
                    <div class="row g-3">
                        @foreach ($purchaseOrder->deliverables->sortBy('sequence') as $dlv)
                            @php
                                $isRemoved = $dlv->trashed();
                                $dlvStatusClass = match($dlv->status) {
                                    'completed'  => 'success',
                                    'in_progress'=> 'primary',
                                    'cancelled'  => 'danger',
                                    default      => 'secondary',
                                };
                            @endphp
                            <div class="col-md-4">
                                <div class="stat-tile h-100{{ $isRemoved ? ' opacity-50' : '' }}"
                                     style="{{ $isRemoved ? 'border:1px solid #f5c6cb;background:#fff5f5;' : '' }}">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <span class="badge bg-light text-dark border">
                                            {{ $dlv->type === 'milestone' ? 'Milestone' : 'Deliverable' }}
                                            #{{ $dlv->sequence }}
                                        </span>
                                        @if ($isRemoved)
                                            <span class="badge bg-danger">Removed</span>
                                        @else
                                            <span class="badge bg-{{ $dlvStatusClass }}">
                                                {{ ucwords(str_replace('_', ' ', $dlv->status)) }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="fw-semibold mt-2">{{ $dlv->title }}</div>
                                    @if ($dlv->description)
                                        <div class="small text-muted mt-1">{{ $dlv->description }}</div>
                                    @endif
                                    @if ($dlv->timeline_start || $dlv->timeline_end)
                                        <div class="small text-muted mt-2">
                                            <i class="feather-calendar me-1"></i>
                                            {{ $dlv->timeline_start?->format('d M Y') ?? '?' }}
                                            →
                                            {{ $dlv->timeline_end?->format('d M Y') ?? '?' }}
                                        </div>
                                    @endif
                                    @if ($dlv->amount)
                                        <div class="small text-muted mt-1">
                                            {{ $currency }} {{ number_format((float) $dlv->amount, 2) }}
                                        </div>
                                    @endif
                                    @if ($isRemoved)
                                        <div class="small text-danger mt-2 pt-2 border-top">
                                            <i class="feather-user-x me-1"></i>
                                            Removed by <strong>{{ $dlv->deletedBy?->name ?? 'Unknown' }}</strong>
                                            on {{ $dlv->deleted_at?->format('d M Y, g:i A') }}
                                        </div>
                                    @else
                                        <div class="small mt-2">
                                            Vendor:
                                            <span class="badge bg-{{ $dlv->vendor_approval_status === 'approved' ? 'success' : ($dlv->vendor_approval_status === 'rejected' ? 'danger' : 'secondary') }}">
                                                {{ ucfirst($dlv->vendor_approval_status) }}
                                            </span>
                                            &nbsp;Admin:
                                            <span class="badge bg-{{ $dlv->admin_approval_status === 'approved' ? 'success' : ($dlv->admin_approval_status === 'rejected' ? 'danger' : 'secondary') }}">
                                                {{ ucfirst($dlv->admin_approval_status) }}
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif

                <div class="row g-4 mt-1">
                    <div class="col-lg-6">
                        <div class="section-title">Delivery and Payment Terms</div>
                        <table class="table table-sm">
                            <tr>
                                <th style="width: 180px;">Payment Terms</th>
                                <td>{{ $purchaseOrder->payment_terms ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Delivery Terms</th>
                                <td>{{ $purchaseOrder->delivery_terms ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Incoterm</th>
                                <td>{{ $purchaseOrder->incoterm ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Valid Until</th>
                                <td>{{ $purchaseOrder->valid_until?->format('d M Y') ?? 'N/A' }}</td>
                            </tr>
                        </table>
                    </div>

                    <div class="col-lg-6">
                        <div class="section-title">Addresses</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="text-muted small">Bill To</div>
                                <div class="fw-semibold white-space-pre-line">{{ $purchaseOrder->billing_address ?? 'N/A' }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">Ship To</div>
                                <div class="fw-semibold white-space-pre-line">{{ $purchaseOrder->shipping_address ?? 'N/A' }}</div>
                            </div>
                            <div class="col-12">
                                <div class="text-muted small">Delivery Location</div>
                                <div class="fw-semibold white-space-pre-line">{{ $purchaseOrder->delivery_location ?? 'N/A' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                @if ($lineItems->isNotEmpty())
                    <div class="mt-4">
                        <div class="section-title">Line Items from Purchase Request</div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Category</th>
                                        <th>Resource</th>
                                        <th>Deliverable</th>
                                        <th>Date</th>
                                        <th>Evidence</th>
                                        <th>Notes</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($lineItems as $item)
                                        @php
                                            $itemEvidence = $evidenceByItem->get($item->id);
                                            $itemDocuments = collect($itemEvidence?->documents ?? []);
                                            $itemPaidAmount = $linePaidAmountForItem($item);
                                            $itemHasPaidDisbursement = $itemPaidAmount > 0;
                                            $itemFullyPaid = $itemPaidAmount >= (float) ($item->amount ?? 0) && (float) ($item->amount ?? 0) > 0;
                                        @endphp
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $item->resourceCategory?->name ?? 'N/A' }}</td>
                                            <td>{{ $item->resource?->name ?? 'N/A' }}</td>
                                            <td>{{ $item->milestone ?: ($item->deliverable?->title ?? 'N/A') }}</td>
                                            <td>{{ $itemEvidence?->deliverable_date?->format('M d, Y') ?? 'N/A' }}</td>
                                            <td>
                                                @if ($itemEvidence)
                                                    <span class="badge {{ $itemEvidence->is_met || $itemHasPaidDisbursement ? 'bg-success' : 'bg-info' }}">
                                                        @if ($itemEvidence->is_met && $itemFullyPaid)
                                                            Paid / Confirmed
                                                        @elseif ($itemEvidence->is_met && $itemHasPaidDisbursement)
                                                            Part Paid / Confirmed
                                                        @elseif ($itemEvidence->is_met)
                                                            Confirmed
                                                        @elseif ($itemFullyPaid)
                                                            Paid
                                                        @elseif ($itemHasPaidDisbursement)
                                                            Part Paid
                                                        @else
                                                            Recorded
                                                        @endif
                                                    </span>
                                                    @if ($itemEvidence->notes)
                                                        <div class="small text-muted mt-1">{{ $itemEvidence->notes }}</div>
                                                    @endif
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
                                                @elseif ($itemFullyPaid)
                                                    <span class="badge bg-success">Paid</span>
                                                @elseif ($itemHasPaidDisbursement)
                                                    <span class="badge bg-success">Part Paid</span>
                                                @else
                                                    <span class="badge bg-light text-muted border">Pending</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div>{{ $item->observations ?? $item->object_type ?? 'N/A' }}</div>
                                                <div class="small text-muted">{{ $item->budget_code ?? $item->work_plan_payment_basis ?? '' }}</div>
                                            </td>
                                            <td class="text-end fw-semibold">
                                                {{ $currency }} {{ number_format((float) $item->amount, 2) }}
                                                @if ($itemHasPaidDisbursement)
                                                    <div class="small text-muted">Paid {{ number_format($itemPaidAmount, 2) }}</div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="row g-4 mt-1">
                    <div class="col-lg-6">
                        <div class="section-title">Quality and Warranty</div>
                        <div class="mb-3">
                            <div class="text-muted small">Inspection Requirements</div>
                            <div>{{ $purchaseOrder->inspection_requirements ?? 'N/A' }}</div>
                        </div>
                        <div>
                            <div class="text-muted small">Warranty Terms</div>
                            <div>{{ $purchaseOrder->warranty_terms ?? 'N/A' }}</div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="section-title">Conditions</div>
                        <div class="mb-3">
                            <div class="text-muted small">Special Instructions</div>
                            <div>{{ $purchaseOrder->special_instructions ?? 'N/A' }}</div>
                        </div>
                        <div>
                            <div class="text-muted small">Terms and Conditions</div>
                            <div>{{ $purchaseOrder->terms_conditions ?? 'N/A' }}</div>
                        </div>
                    </div>
                </div>

                @if ($purchaseOrder->invoice)
                    <div class="alert alert-info mt-4 mb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <strong>Linked Invoice:</strong> {{ $purchaseOrder->invoice->reference_no ?? 'N/A' }}
                        </div>
                        <a href="{{ route('procurement.invoices.show', $purchaseOrder->invoice) }}" class="btn btn-sm btn-light">
                            View Invoice
                        </a>
                    </div>
                @endif

                <div class="mt-4">
                    @if ($purchaseOrder->po_type === 'think_tank_transfer' && $purchaseOrder->status === 'pending')
                        <span class="badge bg-warning-subtle text-warning">Payment Sent - Pending Think Tank Receipt</span>
                    @elseif ($purchaseOrder->remainingAmount() > 0)
                        <a href="{{ route('procurement.disbursements.create', ['purchase_order_id' => $purchaseOrder->id]) }}"
                            class="btn btn-success">
                            <i class="feather-dollar-sign me-1"></i> Record Disbursement
                        </a>
                    @else
                        <span class="badge bg-success-subtle text-success">Fully Paid</span>
                    @endif
                </div>

                @if ($purchaseOrder->disbursements->isNotEmpty())
                    <div class="mt-4">
                        <div class="section-title">Disbursement History</div>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Receipt</th>
                                        <th>Deliverable</th>
                                        <th>Amount</th>
                                        <th>Paid At</th>
                                        <th>Method</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($purchaseOrder->disbursements as $disbursement)
                                        <tr>
                                            <td>{{ $disbursement->reference_no ?? 'N/A' }}</td>
                                            <td>{{ $disbursement->deliverable?->title ?? 'N/A' }}</td>
                                            <td>{{ $disbursement->amount ? $disbursement->resolved_currency . ' ' . number_format($disbursement->amount, 2) : 'N/A' }}</td>
                                            <td>{{ $disbursement->paid_at?->format('d M Y') ?? 'N/A' }}</td>
                                            <td>{{ $disbursement->payment_method ?? 'N/A' }}</td>
                                            <td>
                                                <a href="{{ route('procurement.disbursements.show', $disbursement) }}"
                                                    class="btn btn-sm btn-outline-primary">
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
