@extends('layouts.vendor')

@section('title', 'Invoice Details')

@section('content')
    <div class="mb-4">
        <h3 class="mb-1">Invoice Details</h3>
        <p class="text-muted mb-0">Invoice {{ $invoice->reference_no ?? 'N/A' }}</p>
    </div>

    <div class="card vendor-card mb-4">
        <div class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3">
            <div>
                <h5 class="mb-1">{{ $invoice->procurement?->title ?? 'N/A' }}</h5>
                <div class="text-muted small">{{ $invoice->procurement?->reference_no ?? 'N/A' }}</div>
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
                    @if ($invoice->purchaseOrder)
                        <div class="fw-semibold">{{ $invoice->purchaseOrder->reference_no ?? 'PO' }}</div>
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
@endsection
