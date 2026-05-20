@extends('layouts.app')

@push('styles')
    <style>
        .po-show .hero-card {
            background: linear-gradient(120deg, #1e293b 0%, #0f172a 40%, #0ea5e9 100%);
            color: #fff;
            border: none;
            border-radius: 16px;
        }

        .po-show .hero-card p {
            color: rgba(255, 255, 255, 0.78);
        }

        .po-show .detail-card {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 16px 30px rgba(15, 23, 42, 0.08);
        }

        .po-show .stat-tile {
            background: #f8fafc;
            border-radius: 14px;
            padding: 16px;
            height: 100%;
        }

        .po-show .stat-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
        }

        .po-show .stat-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: #0f172a;
        }
    </style>
@endpush

@section('content')
    <div class="nxl-container po-show">
        <div class="card hero-card mb-4">
            <div class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-start">
                <div>
                    <h4 class="fw-bold mb-1">Purchase Order</h4>
                    <p class="mb-0">{{ $purchaseOrder->reference_no ?? 'N/A' }}</p>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-3 mt-lg-0">
                    <a href="{{ route('procurement.purchase-orders.pdf', $purchaseOrder) }}" class="btn btn-light">
                        <i class="feather-eye me-1"></i> View PDF
                    </a>
                    <a href="{{ route('procurement.purchase-orders.download', $purchaseOrder) }}" class="btn btn-primary">
                        <i class="feather-download me-1"></i> Download PDF
                    </a>
                    <a href="{{ route('procurement.purchase-orders.index') }}" class="btn btn-outline-light">
                        <i class="feather-arrow-left me-1"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <div class="card detail-card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="stat-tile">
                            <div class="stat-label">Status</div>
                            <div class="stat-value text-capitalize">{{ str_replace('_', ' ', $purchaseOrder->status ?? 'draft') }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-tile">
                            <div class="stat-label">Issued At</div>
                            <div class="stat-value">
                                {{ $purchaseOrder->issued_at?->format('d M Y, H:i') ?? 'N/A' }}
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-tile">
                            <div class="stat-label">Approved Amount</div>
                            <div class="stat-value">
                                {{ $purchaseOrder->amount ? number_format($purchaseOrder->amount, 2) : 'N/A' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mt-1">
                    <div class="col-md-6">
                        <div class="text-muted small">Procurement</div>
                        <div class="fw-semibold">{{ $purchaseOrder->procurement?->title ?? 'N/A' }}</div>
                        <div class="small text-muted">{{ $purchaseOrder->procurement?->reference_no ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Vendor</div>
                        <div class="fw-semibold">{{ $purchaseOrder->vendor?->name ?? 'Vendor' }}</div>
                        <div class="small text-muted">{{ $purchaseOrder->vendor?->email ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Currency</div>
                        <div class="fw-semibold">{{ $purchaseOrder->currency ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-8">
                        <div class="text-muted small">Sub-Activity</div>
                        <div class="fw-semibold">{{ $purchaseOrder->subActivity?->name ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Paid Amount</div>
                        <div class="fw-semibold">
                            {{ number_format($purchaseOrder->paidAmount(), 2) }} {{ $purchaseOrder->currency ?? '' }}
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="text-muted small">Remaining Balance</div>
                        <div class="fw-semibold">
                            {{ number_format($purchaseOrder->remainingAmount(), 2) }} {{ $purchaseOrder->currency ?? '' }}
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
                @elseif ($purchaseOrder->negotiation)
                    <div class="alert alert-info mt-4 mb-0">
                        Linked Negotiation: {{ $purchaseOrder->negotiation->id }}
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
                        <h6 class="fw-semibold mb-3">Disbursement History</h6>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Receipt</th>
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
                                            <td>
                                                {{ $disbursement->amount ? number_format($disbursement->amount, 2) : 'N/A' }}
                                                {{ $disbursement->currency ?? '' }}
                                            </td>
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
