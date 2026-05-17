@extends('layouts.app')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold mb-1">Create Disbursement</h4>
                <p class="text-muted mb-0">Record a payment against a purchase order.</p>
            </div>
            <a href="{{ route('procurement.disbursements.index') }}" class="btn btn-light btn-sm">
                <i class="feather-arrow-left me-1"></i> Back
            </a>
        </div>

        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card shadow-sm">
            <div class="card-body">
                @if (!$purchaseOrder && $purchaseOrders->isEmpty())
                    <div class="alert alert-warning">
                        No purchase orders with remaining balance are available for disbursement.
                    </div>
                @else
                <form method="POST" action="{{ route('procurement.disbursements.store') }}" class="row g-3">
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Purchase Order</label>
                        <select name="purchase_order_id" id="purchaseOrderSelect" class="form-control" required>
                            @if ($purchaseOrder)
                                <option value="{{ $purchaseOrder->id }}" selected>
                                    {{ $purchaseOrder->reference_no ?? 'N/A' }} - {{ $purchaseOrder->procurement?->title ?? ($purchaseOrder->thinkTankMember?->name ?? 'Fund Transfer') }}
                                </option>
                            @else
                                <option value="">Select Purchase Order</option>
                                @foreach ($purchaseOrders as $order)
                                    <option value="{{ $order->id }}"
                                        data-remaining="{{ $order->remainingAmount() }}"
                                        data-currency="{{ $order->currency ?? '' }}"
                                        data-vendor="{{ $order->vendor?->name ?? '' }}">
                                        {{ $order->reference_no ?? 'N/A' }} - {{ $order->procurement?->title ?? ($order->thinkTankMember?->name ?? 'Fund Transfer') }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                        <div id="purchaseOrderMeta" class="small text-muted mt-1">
                            @if ($purchaseOrder)
                                Remaining Balance: {{ number_format($purchaseOrder->remainingAmount(), 2) }}
                                {{ $purchaseOrder->currency ?? '' }}
                            @endif
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Amount</label>
                        <input type="number" step="0.01" name="amount" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Payment Method</label>
                        <select name="payment_method" class="form-control" required>
                            <option value="">Select method</option>
                            @foreach ($paymentMethods as $method)
                                <option value="{{ $method }}">{{ $method }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Paid At</label>
                        <input type="date" name="paid_at" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Notes (Optional)</label>
                        <textarea name="notes" rows="3" class="form-control"></textarea>
                    </div>
                    <div class="col-12 text-end">
                        <button class="btn btn-primary">
                            <i class="feather-check-circle me-1"></i> Record Disbursement
                        </button>
                    </div>
                </form>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function() {
            const select = document.getElementById('purchaseOrderSelect');
            const meta = document.getElementById('purchaseOrderMeta');

            if (!select || !meta) {
                return;
            }

            const updateMeta = () => {
                const option = select.options[select.selectedIndex];
                if (!option || !option.dataset) {
                    meta.textContent = '';
                    return;
                }
                const remaining = option.dataset.remaining;
                const currency = option.dataset.currency || '';
                const vendor = option.dataset.vendor || '';

                if (!remaining) {
                    meta.textContent = '';
                    return;
                }

                meta.textContent = `Remaining Balance: ${Number(remaining).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}` +
                    (vendor ? ` · Vendor: ${vendor}` : '');
            };

            updateMeta();
            select.addEventListener('change', updateMeta);
        })();
    </script>
@endpush
