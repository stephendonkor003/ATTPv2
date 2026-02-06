@extends('layouts.vendor')

@section('title', 'My Invoices')

@section('content')
    <div class="mb-4">
        <h3 class="mb-1">My Invoices</h3>
        <p class="text-muted mb-0">
            Submit invoices for awarded procurements. Select approved deliverables to auto-calculate the invoice amount.
        </p>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>There were errors with your submission.</strong>
        </div>
    @endif

    <div class="card vendor-card mb-4">
        <div class="card-body">
            <h5 class="mb-3">Create Invoice</h5>
            @if ($awardedProcurements->isEmpty())
                <p class="text-muted mb-0">No awarded procurements available for invoicing yet.</p>
            @else
                <form method="POST" action="{{ route('vendor.invoices.store') }}" class="row g-3">
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Awarded Procurement</label>
                        <select name="procurement_id" id="invoiceProcurementSelect" class="form-control" required>
                            <option value="">Select procurement</option>
                            @foreach ($awardedProcurements as $procurement)
                                @php
                                    $budget = $budgetByProcurement[$procurement->id] ?? null;
                                    $currency = $currencyByProcurement[$procurement->id] ?? null;
                                    $remaining = $remainingByProcurement[$procurement->id] ?? null;
                                    $selectedProcurement = request('procurement_id');
                                @endphp
                                <option value="{{ $procurement->id }}"
                                    data-budget="{{ $budget ?? '' }}"
                                    data-remaining="{{ $remaining ?? '' }}"
                                    data-currency="{{ $currency ?? '' }}"
                                    @selected($selectedProcurement === $procurement->id)>
                                    {{ $procurement->reference_no ?? 'N/A' }} - {{ $procurement->title }}
                                </option>
                            @endforeach
                        </select>
                        <div id="invoiceBudgetMeta" class="small text-muted mt-1"></div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Invoice Month</label>
                        <input type="month" name="invoice_month" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Amount</label>
                        <input type="number" step="0.01" name="amount" id="invoiceAmountInput" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Deliverables (Optional)</label>
                        <div id="deliverablesPicker" class="border rounded p-3">
                            @if ($eligibleDeliverables->isEmpty())
                                <div class="text-muted small">No completed deliverables available for invoicing yet.</div>
                            @else
                                @foreach ($eligibleDeliverables as $deliverable)
                                    <div class="form-check deliverable-option mb-2"
                                        data-procurement="{{ $deliverable->procurement_id }}">
                                        <input class="form-check-input deliverable-check"
                                            type="checkbox"
                                            name="deliverable_ids[]"
                                            value="{{ $deliverable->id }}"
                                            data-amount="{{ $deliverable->amount ?? 0 }}">
                                        <label class="form-check-label">
                                            {{ $deliverable->title }}
                                            <span class="text-muted small">
                                                ({{ $deliverable->timeline_end?->format('M d, Y') ?? 'No due date' }} ·
                                                {{ $deliverable->amount ? number_format($deliverable->amount, 2) : '—' }}
                                                {{ $deliverable->currency ?? '' }})
                                            </span>
                                        </label>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                        @error('deliverable_ids')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                        <small class="text-muted">
                            Select completed deliverables to auto-calculate the invoice amount.
                        </small>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Notes (Optional)</label>
                        <textarea name="notes" rows="2" class="form-control" placeholder="Add invoice notes"></textarea>
                    </div>
                    <div class="col-12 text-end">
                        <button class="btn btn-vendor">Submit Invoice</button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <div class="card vendor-card">
        <div class="card-body">
            <h5 class="mb-3">Invoice History</h5>
            @if ($invoices->isEmpty())
                <p class="text-muted mb-0">You have not submitted any invoices yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Invoice Reference</th>
                                <th>Procurement</th>
                                <th>Month</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>PO</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoices as $invoice)
                                <tr>
                                    <td>
                                        <span class="badge-soft">{{ $invoice->reference_no ?? 'N/A' }}</span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $invoice->procurement?->title ?? 'N/A' }}</div>
                                        <small class="text-muted">{{ $invoice->procurement?->reference_no ?? 'N/A' }}</small>
                                    </td>
                                    <td>{{ $invoice->invoice_month?->format('M Y') ?? 'N/A' }}</td>
                                    <td>
                                        {{ $invoice->amount ? number_format($invoice->amount, 2) : 'N/A' }}
                                        {{ $invoice->currency ?? '' }}
                                    </td>
                                    <td>
                                        <span class="status-pill text-capitalize">{{ $invoice->status ?? 'submitted' }}</span>
                                    </td>
                                    <td>
                                        @if ($invoice->purchaseOrder)
                                            <span class="badge bg-success-subtle text-success">
                                                {{ $invoice->purchaseOrder->reference_no ?? 'PO' }}
                                            </span>
                                        @else
                                            <span class="text-muted small">Pending</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="{{ route('vendor.invoices.show', $invoice) }}"
                                                class="btn btn-vendor-outline btn-sm">View</a>
                                            <a href="{{ route('vendor.invoices.pdf', $invoice) }}"
                                                class="btn btn-light btn-sm">PDF</a>
                                            <a href="{{ route('vendor.invoices.download', $invoice) }}"
                                                class="btn btn-vendor btn-sm">Download</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection

    @push('scripts')
        <script>
            (function() {
                const select = document.getElementById('invoiceProcurementSelect');
                const meta = document.getElementById('invoiceBudgetMeta');
                const deliverableOptions = document.querySelectorAll('.deliverable-option');
                const deliverableChecks = document.querySelectorAll('.deliverable-check');
                const amountInput = document.getElementById('invoiceAmountInput');

                if (!select || !meta) {
                    return;
                }

                const updateMeta = () => {
                    const option = select.options[select.selectedIndex];
                    if (!option || !option.dataset) {
                        meta.textContent = '';
                        return;
                    }
                    const budget = option.dataset.budget;
                    const remaining = option.dataset.remaining;
                    const currency = option.dataset.currency || '';

                    if (!budget) {
                        meta.textContent = 'Budget information is not available for this procurement.';
                        return;
                    }

                    meta.textContent =
                        `Budget: ${Number(budget).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency} | ` +
                        `Remaining: ${Number(remaining).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}`;
                };

                const filterDeliverables = () => {
                    const procurementId = select.value;
                    deliverableChecks.forEach((checkbox) => {
                        checkbox.checked = false;
                    });

                    deliverableOptions.forEach((option) => {
                        if (!procurementId) {
                            option.classList.add('d-none');
                            return;
                        }
                        if (option.dataset.procurement === procurementId) {
                            option.classList.remove('d-none');
                        } else {
                            option.classList.add('d-none');
                        }
                    });

                    if (amountInput) {
                        amountInput.value = '';
                        amountInput.readOnly = false;
                    }

                    updateDeliverableTotal();
                };

                const updateDeliverableTotal = () => {
                    let total = 0;
                    let selectedCount = 0;
                    deliverableChecks.forEach((checkbox) => {
                        const option = checkbox.closest('.deliverable-option');
                        if (checkbox.checked && option && !option.classList.contains('d-none')) {
                            total += Number(checkbox.dataset.amount || 0);
                            selectedCount += 1;
                        }
                    });

                    if (amountInput) {
                        if (selectedCount > 0) {
                            amountInput.value = total.toFixed(2);
                            amountInput.readOnly = true;
                        } else {
                            amountInput.readOnly = false;
                        }
                    }
                };

                updateMeta();
                filterDeliverables();
                select.addEventListener('change', updateMeta);
                select.addEventListener('change', filterDeliverables);
                deliverableChecks.forEach((checkbox) => {
                    checkbox.addEventListener('change', updateDeliverableTotal);
                });
            })();
        </script>
    @endpush
