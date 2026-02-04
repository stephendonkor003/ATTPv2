@extends('layouts.vendor')

@section('title', 'Payment Details')

@section('content')
    <div class="mb-4">
        <h3 class="mb-1">Payment Details</h3>
        <p class="text-muted mb-0">Receipt {{ $disbursement->reference_no ?? 'N/A' }}</p>
    </div>

    <div class="card vendor-card mb-4">
        <div class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3">
            <div>
                <h5 class="mb-1">{{ $disbursement->procurement?->title ?? 'N/A' }}</h5>
                <div class="text-muted small">{{ $disbursement->procurement?->reference_no ?? 'N/A' }}</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('vendor.payments.index') }}" class="btn btn-vendor-outline btn-sm">Back</a>
                <a href="{{ route('vendor.payments.pdf', $disbursement) }}" class="btn btn-light btn-sm">View PDF</a>
                <a href="{{ route('vendor.payments.download', $disbursement) }}" class="btn btn-vendor btn-sm">Download PDF</a>
            </div>
        </div>
    </div>

    <div class="card vendor-card">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="text-muted small">Purchase Order</div>
                    <div class="fw-semibold">{{ $disbursement->purchaseOrder?->reference_no ?? 'N/A' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Paid At</div>
                    <div class="fw-semibold">{{ $disbursement->paid_at?->format('M d, Y') ?? 'N/A' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Payment Method</div>
                    <div class="fw-semibold">{{ $disbursement->payment_method ?? 'N/A' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Amount</div>
                    <div class="fw-semibold">
                        {{ $disbursement->amount ? number_format($disbursement->amount, 2) : 'N/A' }}
                        {{ $disbursement->currency ?? '' }}
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Sub-Activity</div>
                    <div class="fw-semibold">{{ $disbursement->subActivity?->name ?? 'N/A' }}</div>
                </div>
                @if ($disbursement->notes)
                    <div class="col-12">
                        <div class="text-muted small">Notes</div>
                        <div class="fw-semibold">{{ $disbursement->notes }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
