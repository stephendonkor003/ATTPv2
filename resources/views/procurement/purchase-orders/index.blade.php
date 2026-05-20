@extends('layouts.app')

@push('styles')
    <style>
        .po-page .hero-card {
            background: linear-gradient(120deg, #0f172a 0%, #1e293b 45%, #0ea5e9 100%);
            color: #fff;
            border: none;
            border-radius: 16px;
            overflow: hidden;
        }

        .po-page .hero-card p {
            color: rgba(255, 255, 255, 0.75);
        }

        .po-page .stat-card {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.06);
        }

        .po-page .stat-title {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
        }

        .po-page .stat-value {
            font-size: 1.4rem;
            font-weight: 700;
            color: #0f172a;
        }

        .po-page .table-card {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 16px 28px rgba(15, 23, 42, 0.08);
        }

        .po-page .table thead th {
            background: #f8fafc;
        }
    </style>
@endpush

@section('content')
    <div class="nxl-container po-page">
        <div class="card hero-card mb-4">
            <div class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-start">
                <div>
                    <h4 class="fw-bold mb-1">Purchase Orders</h4>
                    <p class="mb-0">Track purchase orders tied to approved budget commitments.</p>
                </div>
                <div class="mt-3 mt-lg-0">
                    @can('finance.purchase_orders.create')
                        <a href="{{ route('procurement.purchase-orders.create') }}" class="btn btn-light">
                            <i class="feather-plus-circle me-1"></i> Create Purchase Order
                        </a>
                    @else
                        <span class="badge bg-light text-dark px-3 py-2">
                            Budget Execution
                        </span>
                    @endcan
                </div>
            </div>
        </div>

        @php
            $pageAmountTotal = $purchaseOrders->sum('amount');
            $totalOrders = $purchaseOrders->total();
            $latestOrder = $purchaseOrders->first();
        @endphp

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card p-3 h-100">
                    <div class="stat-title">Total Orders</div>
                    <div class="stat-value">{{ $totalOrders }}</div>
                    <div class="text-muted small">Across all pages</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card p-3 h-100">
                    <div class="stat-title">Page Amount</div>
                    <div class="stat-value">
                        {{ number_format((float) $pageAmountTotal, 2) }}
                    </div>
                    <div class="text-muted small">Sum of current page</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card p-3 h-100">
                    <div class="stat-title">Latest PO</div>
                    <div class="stat-value">{{ $latestOrder?->reference_no ?? 'N/A' }}</div>
                    <div class="text-muted small">
                        {{ $latestOrder?->created_at?->format('d M Y') ?? 'N/A' }}
                    </div>
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card table-card">
            <div class="card-body">
                <x-data-table id="purchaseOrdersTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">PO Reference</th>
                            <th>Procurement</th>
                            <th>Commitment</th>
                            <th>Vendor</th>
                            <th class="text-center">Amount</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Issued</th>
                            <th class="text-center" width="120">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($purchaseOrders as $purchaseOrder)
                            <tr>
                                <td class="ps-4 fw-semibold">
                                    {{ $purchaseOrder->reference_no ?? 'N/A' }}
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $purchaseOrder->procurement?->title ?? 'N/A' }}</div>
                                    <small class="text-muted">{{ $purchaseOrder->procurement?->reference_no ?? 'N/A' }}</small>
                                </td>
                                <td>
                                    <div class="fw-semibold">
                                        {{ $purchaseOrder->budgetCommitment?->description ?? 'Approved Commitment' }}
                                    </div>
                                    <small class="text-muted">
                                        {{ $purchaseOrder->budgetCommitment?->commitment_year ?? 'N/A' }}
                                        - {{ $purchaseOrder->budgetCommitment?->commitment_amount ? number_format($purchaseOrder->budgetCommitment->commitment_amount, 2) : 'N/A' }}
                                    </small>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $purchaseOrder->vendor?->name ?? 'Vendor' }}</div>
                                    <small class="text-muted">{{ $purchaseOrder->vendor?->email ?? 'N/A' }}</small>
                                </td>
                                <td class="text-center">
                                    {{ $purchaseOrder->amount ? number_format($purchaseOrder->amount, 2) : 'N/A' }}
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary text-capitalize">
                                        {{ str_replace('_', ' ', $purchaseOrder->status ?? 'draft') }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    {{ $purchaseOrder->issued_at?->format('d M Y') ?? 'N/A' }}
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('procurement.purchase-orders.show', $purchaseOrder) }}"
                                        class="btn btn-sm btn-outline-primary">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-data-table>

                <div class="mt-3">
                    {{ $purchaseOrders->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection

