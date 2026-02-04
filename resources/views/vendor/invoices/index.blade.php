@extends('layouts.vendor')

@section('title', 'My Invoices')

@section('content')
    <div class="mb-4">
        <h3 class="mb-1">My Invoices</h3>
        <p class="text-muted mb-0">
            Submit monthly invoices for awarded procurements. Invoices cannot exceed the remaining sub-activity budget.
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
                        <input type="number" step="0.01" name="amount" class="form-control" required>
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

            updateMeta();
            select.addEventListener('change', updateMeta);
        })();
    </script>
@endpush
