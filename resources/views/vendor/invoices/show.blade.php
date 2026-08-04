@extends('layouts.vendor')

@section('title', 'Invoice Details')

@section('content')
    @php($linkedPurchaseOrder = $invoice->purchaseOrder ?: $invoice->evidence?->purchaseOrder)
    <div class="mb-4">
        <h3 class="mb-1">Invoice Details</h3>
        <p class="text-muted mb-0">Invoice {{ $invoice->reference_no ?? 'N/A' }}</p>
    </div>

    <div class="card vendor-card mb-4">
        <div class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3">
            <div>
                <h5 class="mb-1">{{ $invoice->procurement?->title ?? $linkedPurchaseOrder?->po_title ?? 'Monthly deliverable invoice' }}</h5>
                <div class="text-muted small">{{ $invoice->procurement?->reference_no ?? $linkedPurchaseOrder?->reference_no ?? 'N/A' }}</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('vendor.invoices.index') }}" class="btn btn-vendor-outline btn-sm">
                    Back
                </a>
                <a href="{{ route('vendor.invoices.pdf', $invoice) }}" class="btn btn-light btn-sm">
                    View PDF
                </a>
                <a href="{{ route('vendor.invoices.download', $invoice) }}" class="btn btn-vendor btn-sm">
                    Download PDF
                </a>
            </div>
        </div>
    </div>

    <div class="card vendor-card">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="text-muted small">Invoice Month</div>
                    <div class="fw-semibold">{{ $invoice->invoice_month?->format('M Y') ?? 'N/A' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Amount</div>
                    <div class="fw-semibold">
                        {{ $invoice->amount ? number_format($invoice->amount, 2) : 'N/A' }}
                        {{ $invoice->currency ?? '' }}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Status</div>
                    <div class="status-pill text-capitalize">{{ $invoice->status ?? 'submitted' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Sub-Activity</div>
                    <div class="fw-semibold">{{ $invoice->subActivity?->name ?? 'N/A' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Purchase Order</div>
                    @if ($linkedPurchaseOrder)
                        <div class="fw-semibold">{{ $linkedPurchaseOrder->reference_no ?? 'PO' }}</div>
                    @else
                        <div class="text-muted">Pending</div>
                    @endif
                </div>
                @if ($invoice->notes)
                    <div class="col-12">
                        <div class="text-muted small">Notes</div>
                        <div class="fw-semibold">{{ $invoice->notes }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if ($invoice->deliverables && $invoice->deliverables->isNotEmpty())
        <div class="card vendor-card mt-4">
            <div class="card-body">
                <h5 class="mb-3">Linked Deliverables</h5>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Deliverable</th>
                                <th>Timeline</th>
                                <th class="text-end">Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice->deliverables as $deliverable)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $deliverable->title }}</div>
                                        <span class="badge bg-light text-dark">{{ ucfirst($deliverable->type) }}</span>
                                    </td>
                                    <td>
                                        {{ $deliverable->timeline_start?->format('M d, Y') ?? '—' }}
                                        -
                                        {{ $deliverable->timeline_end?->format('M d, Y') ?? '—' }}
                                    </td>
                                    <td class="text-end">
                                        {{ $deliverable->amount ? number_format($deliverable->amount, 2) : '—' }}
                                        {{ $deliverable->currency ?? '' }}
                                    </td>
                                    <td class="text-capitalize">{{ $deliverable->status }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if ($invoice->evidence)
        @php($invoiceDocuments = collect($invoice->evidence->documents ?? [])->filter(fn ($document) => is_array($document)))
        <div class="card vendor-card mt-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h5 class="mb-1">Invoice & Evidence Documents</h5>
                        <div class="small text-muted">
                            {{ $invoice->evidence->purchaseRequestItem?->milestone ?? 'Monthly deliverable' }}
                            @if ($invoice->evidence->deliverable_date) · {{ $invoice->evidence->deliverable_date->format('d M Y') }} @endif
                        </div>
                    </div>
                    <span class="status-pill">{{ $invoiceDocuments->count() }} file(s)</span>
                </div>
                @forelse ($invoiceDocuments as $documentIndex => $document)
                    <div class="d-flex align-items-center gap-3 py-2 border-bottom">
                        <span class="badge bg-light text-dark text-capitalize">{{ $document['document_type'] ?? 'evidence' }}</span>
                        <div class="min-w-0 flex-grow-1">
                            <div class="fw-semibold text-truncate">{{ $document['display_name'] ?? $document['name'] ?? 'Document' }}</div>
                            <div class="small text-muted">Added by {{ $document['uploaded_by_name'] ?? $document['source_label'] ?? 'ATTP' }}</div>
                        </div>
                        <a href="{{ route('vendor.purchase-orders.evidence.documents.download', [$linkedPurchaseOrder, $invoice->evidence, $documentIndex, 'download' => 1]) }}" class="btn btn-vendor-outline btn-sm">
                            <i class="feather-download me-1"></i> Download
                        </a>
                    </div>
                @empty
                    <div class="text-muted">No files are attached to this invoice.</div>
                @endforelse
            </div>
        </div>
    @endif
@endsection
