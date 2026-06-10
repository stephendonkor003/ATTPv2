@extends('layouts.app')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold mb-1">Create Disbursement</h4>
                <p class="text-muted mb-0">Select a purchase order — all details will be pulled directly from it.</p>
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

        @if ($purchaseOrders->isEmpty())
            <div class="alert alert-warning">
                No purchase orders with remaining balance are available for disbursement.
            </div>
        @else
            <form method="POST" action="{{ route('procurement.disbursements.store') }}">
                @csrf

                {{-- Step 1: Purchase Order Selection --}}
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h6 class="fw-bold mb-0">
                            <span class="badge bg-primary me-2">1</span>
                            Select Purchase Order
                        </h6>
                        <p class="text-muted small mb-0 mt-1">A disbursement must be linked to a purchase order.</p>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">
                                    Purchase Order <span class="text-danger">*</span>
                                </label>
                                <select name="purchase_order_id" id="purchaseOrderSelect" class="form-control" required>
                                    <option value="">— Select a Purchase Order —</option>
                                    @foreach ($purchaseOrders as $order)
                                        <option value="{{ $order->id }}"
                                            {{ ($purchaseOrder?->id === $order->id || old('purchase_order_id') === $order->id) ? 'selected' : '' }}>
                                            {{ $order->reference_no ?? 'N/A' }}
                                            @if ($order->po_title)
                                                — {{ $order->po_title }}
                                            @elseif ($order->procurement?->title)
                                                — {{ $order->procurement->title }}
                                            @elseif ($order->thinkTankMember?->name)
                                                — {{ $order->thinkTankMember->name }}
                                            @else
                                                — Fund Transfer
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Step 2: PO Details (populated by JS) --}}
                <div id="poDetailsPanel" class="card shadow-sm mb-4 d-none">
                    <div class="card-header d-flex align-items-center gap-2">
                        <h6 class="fw-bold mb-0">
                            <span class="badge bg-secondary me-2">2</span>
                            Purchase Order Details
                        </h6>
                        <span class="badge bg-light text-muted" id="po-status-badge"></span>
                    </div>
                    <div class="card-body">
                        {{-- Identifiers row --}}
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <div class="small text-muted mb-1">Reference</div>
                                <div class="fw-bold" id="po-reference">—</div>
                            </div>
                            <div class="col-md-5">
                                <div class="small text-muted mb-1">Title / Procurement</div>
                                <div class="fw-semibold" id="po-title">—</div>
                            </div>
                            <div class="col-md-2">
                                <div class="small text-muted mb-1">Type</div>
                                <div id="po-type">—</div>
                            </div>
                            <div class="col-md-2">
                                <div class="small text-muted mb-1">Governance Node</div>
                                <div id="po-governance-node">—</div>
                            </div>
                        </div>

                        <hr class="my-3">

                        {{-- Vendor + Terms row --}}
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <div class="small text-muted mb-1">Vendor / Recipient</div>
                                <div class="fw-semibold" id="po-vendor">—</div>
                                <div class="small text-muted" id="po-vendor-email"></div>
                                <div class="small text-muted" id="po-vendor-phone"></div>
                                <div class="small text-muted" id="po-vendor-contact-name"></div>
                            </div>
                            <div class="col-md-4">
                                <div class="small text-muted mb-1">Sub-Activity</div>
                                <div id="po-sub-activity">—</div>
                                <div class="small text-muted mt-2 mb-1">Incoterm</div>
                                <div id="po-incoterm">—</div>
                            </div>
                            <div class="col-md-4">
                                <div class="small text-muted mb-1">Payment Terms</div>
                                <div id="po-payment-terms">—</div>
                                <div class="small text-muted mt-2 mb-1">Delivery Terms</div>
                                <div id="po-delivery-terms">—</div>
                            </div>
                        </div>

                        {{-- References + Dates row --}}
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <div class="small text-muted mb-1">Supplier Reference</div>
                                <div id="po-supplier-ref">—</div>
                            </div>
                            <div class="col-md-3">
                                <div class="small text-muted mb-1">Contract Reference</div>
                                <div id="po-contract-ref">—</div>
                            </div>
                            <div class="col-md-3">
                                <div class="small text-muted mb-1">Expected Delivery</div>
                                <div id="po-expected-delivery">—</div>
                            </div>
                            <div class="col-md-3">
                                <div class="small text-muted mb-1">Valid Until</div>
                                <div id="po-valid-until">—</div>
                            </div>
                        </div>

                        <hr class="my-3">

                        {{-- Financial summary --}}
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="border rounded p-3 text-center">
                                    <div class="small text-muted mb-1">Total PO Amount</div>
                                    <div class="h5 fw-bold mb-0">
                                        <span id="po-amount">—</span>
                                        <span class="small text-muted po-currency-tag"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3 text-center">
                                    <div class="small text-muted mb-1">Already Paid</div>
                                    <div class="h5 fw-bold mb-0 text-warning">
                                        <span id="po-paid">—</span>
                                        <span class="small po-currency-tag"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="bg-success text-white rounded p-3 text-center">
                                    <div class="small opacity-75 mb-1">Remaining Balance</div>
                                    <div class="h5 fw-bold mb-0">
                                        <span id="po-remaining">—</span>
                                        <span class="small po-currency-tag"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Step 3: Disbursement fields (populated/shown by JS) --}}
                <div id="disbursementPanel" class="card shadow-sm mb-4 d-none">
                    <div class="card-header">
                        <h6 class="fw-bold mb-0">
                            <span class="badge bg-secondary me-2">3</span>
                            Disbursement Details
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">
                                    Amount <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text" id="currency-prefix">USD</span>
                                    <input type="number" step="0.01" min="0.01"
                                           name="amount" id="amountInput"
                                           class="form-control @error('amount') is-invalid @enderror"
                                           value="{{ old('amount') }}" required>
                                </div>
                                <div class="small text-muted mt-1">
                                    Maximum: <strong id="amount-max-hint">—</strong>
                                </div>
                                @error('amount')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">
                                    Payment Method <span class="text-danger">*</span>
                                </label>
                                <select name="payment_method"
                                        class="form-control @error('payment_method') is-invalid @enderror"
                                        required>
                                    <option value="">Select method</option>
                                    @foreach ($paymentMethods as $method)
                                        <option value="{{ $method }}"
                                            {{ old('payment_method') === $method ? 'selected' : '' }}>
                                            {{ $method }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('payment_method')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">
                                    Paid At <span class="text-danger">*</span>
                                </label>
                                <input type="date" name="paid_at"
                                       class="form-control @error('paid_at') is-invalid @enderror"
                                       value="{{ old('paid_at', date('Y-m-d')) }}" required>
                                @error('paid_at')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    Transfer Reference
                                    <span class="text-muted fw-normal">(Optional)</span>
                                </label>
                                <input type="text" name="transfer_reference"
                                       class="form-control @error('transfer_reference') is-invalid @enderror"
                                       placeholder="e.g. TRF-2026-001234"
                                       value="{{ old('transfer_reference') }}" maxlength="255">
                                @error('transfer_reference')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">
                                    Notes
                                    <span class="text-muted fw-normal">(Optional)</span>
                                </label>
                                <textarea name="notes" rows="3"
                                          class="form-control @error('notes') is-invalid @enderror"
                                          maxlength="2000">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="feather-check-circle me-1"></i> Record Disbursement
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        @endif
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const poData = @json($purchaseOrdersData);

    const select       = document.getElementById('purchaseOrderSelect');
    const poPanel      = document.getElementById('poDetailsPanel');
    const disbPanel    = document.getElementById('disbursementPanel');
    const amountInput  = document.getElementById('amountInput');
    const currPrefix   = document.getElementById('currency-prefix');
    const amountHint   = document.getElementById('amount-max-hint');

    if (!select) return;

    const fmt = (val) =>
        Number(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const setText = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.textContent = val || '—';
    };

    const statusBadges = {
        draft:        '<span class="badge bg-secondary">Draft</span>',
        issued:       '<span class="badge bg-primary">Issued</span>',
        partial_paid: '<span class="badge bg-warning text-dark">Partial</span>',
        paid:         '<span class="badge bg-success">Paid</span>',
        cancelled:    '<span class="badge bg-danger">Cancelled</span>',
    };

    function update() {
        const id = select.value;
        const po = poData[id];

        if (!po) {
            poPanel.classList.add('d-none');
            disbPanel.classList.add('d-none');
            return;
        }

        // Identifiers
        setText('po-reference', po.reference_no);
        setText('po-title', po.po_title || po.procurement_title || null);
        setText('po-type', po.po_type);
        setText('po-governance-node', po.governance_node);
        const statusEl = document.getElementById('po-status-badge');
        if (statusEl) statusEl.innerHTML = statusBadges[po.status] || po.status || '';

        // Vendor
        setText('po-vendor', po.vendor_name);
        setText('po-vendor-email', po.vendor_email);
        setText('po-vendor-phone', po.vendor_contact_phone);
        setText('po-vendor-contact-name', po.vendor_contact_name ? 'Contact: ' + po.vendor_contact_name : null);

        // Terms
        setText('po-sub-activity', po.sub_activity);
        setText('po-incoterm', po.incoterm);
        setText('po-payment-terms', po.payment_terms);
        setText('po-delivery-terms', po.delivery_terms);

        // References & dates
        setText('po-supplier-ref', po.supplier_reference);
        setText('po-contract-ref', po.contract_reference);
        setText('po-expected-delivery', po.expected_delivery);
        setText('po-valid-until', po.valid_until);

        // Financial
        setText('po-amount', fmt(po.amount));
        setText('po-paid', fmt(po.paid));
        setText('po-remaining', fmt(po.remaining));

        document.querySelectorAll('.po-currency-tag').forEach(el => {
            el.textContent = po.currency || '';
        });

        // Amount input
        const currency = po.currency || 'USD';
        if (currPrefix) currPrefix.textContent = currency;
        if (amountHint) amountHint.textContent = fmt(po.remaining) + ' ' + currency;

        amountInput.max = po.remaining;

        // Pre-fill amount with remaining balance only when not carrying an old/user value
        if (!amountInput.dataset.userSet) {
            amountInput.value = po.remaining;
        } else if (parseFloat(amountInput.value) > po.remaining) {
            amountInput.value = po.remaining;
        }

        // Show panels
        poPanel.classList.remove('d-none');
        disbPanel.classList.remove('d-none');
    }

    // Track manual edits so switching PO doesn't wipe the user's typed amount
    amountInput.addEventListener('input', () => {
        amountInput.dataset.userSet = '1';
    });

    // Preserve old() value across validation failures
    if (amountInput.value) {
        amountInput.dataset.userSet = '1';
    }

    select.addEventListener('change', () => {
        delete amountInput.dataset.userSet;
        update();
    });

    // Trigger immediately if a PO is already selected (pre-select from query param or old())
    if (select.value) update();
})();
</script>
@endpush
