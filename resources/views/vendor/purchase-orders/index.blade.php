@extends('layouts.vendor')

@section('title', 'Purchase Orders')

@section('content')
    @php
        use Illuminate\Support\Str;

        $formatMoney = fn ($amount, $currency = 'USD') => trim(($currency ?: 'USD') . ' ' . number_format((float) $amount, 2));
        $statusTabs = [
            null => 'All',
            'issued' => 'Issued',
            'draft' => 'Draft',
            'closed' => 'Closed',
            'cancelled' => 'Cancelled',
        ];
    @endphp

    <div class="vendor-page-head">
        <div>
            <div class="vendor-eyebrow">Legal Purchase Orders</div>
            <h3 class="mb-1">Purchase Orders</h3>
            <p class="text-muted mb-0">Review ATTP purchase orders assigned to you and upload deliverable evidence.</p>
        </div>
        <a href="{{ route('vendor.payments.index') }}" class="btn btn-vendor-outline">
            <i class="feather-dollar-sign me-1"></i> Payments
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if (session('info'))
        <div class="alert alert-info">{{ session('info') }}</div>
    @endif

    <div class="row g-3 mb-4">
        @foreach ([
            'Total POs' => $stats['total'] ?? 0,
            'Issued' => $stats['issued'] ?? 0,
            'Draft' => $stats['draft'] ?? 0,
            'Closed' => $stats['closed'] ?? 0,
        ] as $label => $value)
            <div class="col-xl-3 col-md-6">
                <div class="card vendor-card h-100">
                    <div class="card-body vendor-metric">
                        <div>
                            <div class="vendor-metric-label">{{ $label }}</div>
                            <div class="vendor-metric-value">{{ number_format($value) }}</div>
                        </div>
                        <span class="vendor-metric-icon"><i class="feather-file-text"></i></span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card vendor-card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($statusTabs as $tabStatus => $label)
                        <a href="{{ route('vendor.purchase-orders.index', array_filter(['status' => $tabStatus])) }}"
                            class="btn btn-sm {{ (string) $status === (string) $tabStatus ? 'btn-vendor' : 'btn-vendor-outline' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
                <span class="badge-soft">{{ number_format($purchaseOrders->total()) }} record(s)</span>
            </div>

            @if ($purchaseOrders->isEmpty())
                <div class="vendor-empty">
                    <div class="vendor-empty-icon"><i class="feather-file-text"></i></div>
                    <h5>No purchase orders yet</h5>
                    <p class="text-muted mb-0">You will see purchase orders here after ATTP creates and assigns one to your vendor account.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Purchase Order</th>
                                <th>Procurement</th>
                                <th>Amount</th>
                                <th>Issued</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($purchaseOrders as $purchaseOrder)
                                @php
                                    $sourceRequest = $purchaseOrder->sourcePurchaseRequest();
                                    $lineItems = $sourceRequest?->items ?? collect();
                                    $evidenceCount = $purchaseOrder->lineItemEvidence->count();
                                @endphp
                                <tr>
                                    <td><span class="badge-soft">{{ $purchaseOrder->reference_no ?? 'N/A' }}</span></td>
                                    <td>
                                        <div class="fw-semibold">{{ $purchaseOrder->po_title ?: 'Purchase Order' }}</div>
                                        <small class="text-muted">{{ number_format($lineItems->count()) }} deliverable(s), {{ number_format($evidenceCount) }} evidence record(s)</small>
                                    </td>
                                    <td>
                                        <div>{{ $purchaseOrder->procurement?->title ?? 'ATTP purchase order' }}</div>
                                        <small class="text-muted">{{ $purchaseOrder->supplier_reference ?: $purchaseOrder->contract_reference ?: 'N/A' }}</small>
                                    </td>
                                    <td>{{ $formatMoney($purchaseOrder->amount, $purchaseOrder->resolved_currency) }}</td>
                                    <td>{{ $purchaseOrder->issued_at?->format('M d, Y') ?? 'N/A' }}</td>
                                    <td><span class="status-pill">{{ Str::headline($purchaseOrder->status ?? 'draft') }}</span></td>
                                    <td class="text-end">
                                        <a href="{{ route('vendor.purchase-orders.show', $purchaseOrder) }}" class="btn btn-vendor-outline btn-sm">
                                            Open
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $purchaseOrders->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
