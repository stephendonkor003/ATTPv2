@extends('layouts.app')

@push('styles')
    <style>
        .disb-show .hero-card {
            background: linear-gradient(120deg, #0f172a 0%, #1e293b 40%, #14b8a6 100%);
            color: #fff;
            border: none;
            border-radius: 16px;
        }

        .disb-show .detail-card {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 16px 30px rgba(15, 23, 42, 0.08);
        }
    </style>
@endpush

@section('content')
    <div class="nxl-container disb-show">
        <div class="card hero-card mb-4">
            <div class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-start">
                <div>
                    <h4 class="fw-bold mb-1">Disbursement Receipt</h4>
                    <p class="mb-0">{{ $disbursement->reference_no ?? 'N/A' }}</p>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-3 mt-lg-0">
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

        <div class="card detail-card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="text-muted small">Purchase Order</div>
                        <div class="fw-semibold">{{ $disbursement->purchaseOrder?->reference_no ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Paid At</div>
                        <div class="fw-semibold">{{ $disbursement->paid_at?->format('d M Y') ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Payment Method</div>
                        <div class="fw-semibold">{{ $disbursement->payment_method ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Vendor</div>
                        <div class="fw-semibold">{{ $disbursement->vendor?->name ?? 'Vendor' }}</div>
                        <div class="small text-muted">{{ $disbursement->vendor?->email ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Procurement</div>
                        <div class="fw-semibold">{{ $disbursement->procurement?->title ?? 'N/A' }}</div>
                        <div class="small text-muted">{{ $disbursement->procurement?->reference_no ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Amount</div>
                        <div class="fw-semibold">
                            {{ $disbursement->amount ? number_format($disbursement->amount, 2) : 'N/A' }}
                            {{ $disbursement->currency ?? '' }}
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="text-muted small">Sub-Activity</div>
                        <div class="fw-semibold">{{ $disbursement->subActivity?->name ?? 'N/A' }}</div>
                    </div>
                </div>

                @if ($disbursement->notes)
                    <div class="alert alert-light mt-4 mb-0">
                        <strong>Notes:</strong> {{ $disbursement->notes }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
