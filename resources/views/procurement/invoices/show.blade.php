@extends('layouts.app')

@push('styles')
    <style>
        .invoice-show .hero-card {
            background: linear-gradient(120deg, #0f172a 0%, #1e293b 40%, #10b981 100%);
            color: #fff;
            border: none;
            border-radius: 16px;
        }

        .invoice-show .hero-card p {
            color: rgba(255, 255, 255, 0.78);
        }

        .invoice-show .detail-card {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 16px 30px rgba(15, 23, 42, 0.08);
        }

        .invoice-show .stat-tile {
            background: #f8fafc;
            border-radius: 14px;
            padding: 16px;
            height: 100%;
        }

        .invoice-show .stat-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
        }

        .invoice-show .stat-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: #0f172a;
        }
    </style>
@endpush

@section('content')
    <div class="nxl-container invoice-show">
        <div class="card hero-card mb-4">
            <div class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-start">
                <div>
                    <h4 class="fw-bold mb-1">Vendor Invoice</h4>
                    <p class="mb-0">{{ $invoice->reference_no ?? 'N/A' }}</p>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-3 mt-lg-0">
                    <a href="{{ route('procurement.invoices.index') }}" class="btn btn-outline-light">
                        <i class="feather-arrow-left me-1"></i> Back
                    </a>
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card detail-card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="stat-tile">
                            <div class="stat-label">Status</div>
                            <div class="stat-value text-capitalize">{{ $invoice->status ?? 'submitted' }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-tile">
                            <div class="stat-label">Invoice Month</div>
                            <div class="stat-value">{{ $invoice->invoice_month?->format('M Y') ?? 'N/A' }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-tile">
                            <div class="stat-label">Invoice Amount</div>
                            <div class="stat-value">
                                {{ $invoice->amount ? number_format($invoice->amount, 2) : 'N/A' }}
                                {{ $invoice->currency ?? '' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mt-1">
                    <div class="col-md-6">
                        <div class="text-muted small">Procurement</div>
                        <div class="fw-semibold">{{ $invoice->procurement?->title ?? 'N/A' }}</div>
                        <div class="small text-muted">{{ $invoice->procurement?->reference_no ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Vendor</div>
                        <div class="fw-semibold">{{ $invoice->vendor?->name ?? 'Vendor' }}</div>
                        <div class="small text-muted">{{ $invoice->vendor?->email ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Budget</div>
                        <div class="fw-semibold">
                            {{ $budget !== null ? number_format($budget, 2) : 'N/A' }}
                            {{ $currency ?? '' }}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Remaining</div>
                        <div class="fw-semibold">
                            {{ $remaining !== null ? number_format($remaining, 2) : 'N/A' }}
                            {{ $currency ?? '' }}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Sub-Activity</div>
                        <div class="fw-semibold">{{ $invoice->subActivity?->name ?? 'N/A' }}</div>
                    </div>
                </div>

                @if ($invoice->notes)
                    <div class="alert alert-light mt-4 mb-0">
                        <strong>Notes:</strong> {{ $invoice->notes }}
                    </div>
                @endif
            </div>
        </div>

        @if ($invoice->deliverables && $invoice->deliverables->isNotEmpty())
            <div class="card detail-card mb-4">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">Linked Deliverables</h6>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
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

        <div class="card detail-card">
            <div class="card-body">
                <h6 class="fw-semibold mb-3">Actions</h6>
                <div class="d-flex flex-wrap gap-2">
                    @if ($invoice->status === 'submitted')
                        <form method="POST" action="{{ route('procurement.invoices.approve', $invoice) }}">
                            @csrf
                            <button class="btn btn-success">
                                <i class="feather-check-circle me-1"></i> Approve
                            </button>
                        </form>
                        <form method="POST" action="{{ route('procurement.invoices.reject', $invoice) }}" class="d-flex gap-2">
                            @csrf
                            <input type="text" name="reason" class="form-control" placeholder="Rejection reason" required>
                            <button class="btn btn-outline-danger">
                                <i class="feather-x-circle me-1"></i> Reject
                            </button>
                        </form>
                    @endif

                    @if ($invoice->status === 'approved' && !$invoice->purchaseOrder)
                        <form method="POST" action="{{ route('procurement.invoices.purchase-order', $invoice) }}">
                            @csrf
                            <button class="btn btn-primary">
                                <i class="feather-clipboard me-1"></i> Generate Purchase Order
                            </button>
                        </form>
                    @endif

                    @if ($invoice->purchaseOrder)
                        <a href="{{ route('procurement.purchase-orders.show', $invoice->purchaseOrder) }}"
                            class="btn btn-outline-primary">
                            <i class="feather-eye me-1"></i> View Purchase Order
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
