@extends('layouts.app')

@push('styles')
    <style>
        .disb-edit .hero-card {
            background: linear-gradient(120deg, #0f172a 0%, #1e293b 42%, #0f766e 100%);
            border: none;
            border-radius: 16px;
            color: #fff;
        }

        .disb-edit .hero-card h1,
        .disb-edit .hero-card h2,
        .disb-edit .hero-card h3,
        .disb-edit .hero-card h4,
        .disb-edit .hero-card h5,
        .disb-edit .hero-card h6,
        .disb-edit .hero-card p,
        .disb-edit .hero-card .text-muted {
            color: #fff !important;
        }

        .disb-edit .panel-card {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow: 0 16px 30px rgba(15, 23, 42, .08);
        }

        .disb-edit .stat-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .disb-edit .stat-tile {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            height: 100%;
            padding: 13px;
            transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
        }

        .disb-edit .stat-tile:hover {
            border-color: #c8d7e8;
            box-shadow: 0 12px 26px rgba(15, 23, 42, .08);
            transform: translateY(-1px);
        }

        .disb-edit .stat-label {
            color: #64748b;
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .disb-edit .stat-value {
            color: #0f172a;
            font-weight: 800;
            margin-top: 4px;
            overflow-wrap: anywhere;
        }

        .disb-edit .section-title {
            color: #64748b;
            font-size: .74rem;
            font-weight: 800;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .disb-edit .payment-toolbar {
            background: #eefaf7;
            border: 1px solid #c9efe5;
            border-radius: 12px;
            color: #115e59;
            padding: 12px 14px;
        }

        .disb-edit .payment-lines {
            display: grid;
            gap: 12px;
        }

        .disb-edit .payment-line-card {
            background: #fff;
            border: 1px solid #dbe6f2;
            border-radius: 12px;
            padding: 14px;
            transition: border-color .18s ease, box-shadow .18s ease;
        }

        .disb-edit .payment-line-card:hover {
            border-color: #b8cadf;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .08);
        }

        .disb-edit .payment-line-number {
            align-items: center;
            background: #0f766e;
            border-radius: 999px;
            color: #fff;
            display: inline-flex;
            font-size: .75rem;
            font-weight: 800;
            height: 28px;
            justify-content: center;
            width: 28px;
        }

        .disb-edit .signed-document-row {
            align-items: end;
            background: #fff;
            border: 1px solid #e3eaf4;
            border-radius: 10px;
            display: grid;
            gap: 8px;
            grid-template-columns: minmax(180px, 1fr) minmax(220px, 1.25fr) auto;
            padding: 10px;
        }

        .disb-edit .signed-document-row + .signed-document-row {
            margin-top: 8px;
        }

        .disb-edit .signed-document-label {
            color: #667085;
            display: block;
            font-size: .72rem;
            font-weight: 700;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .disb-edit .line-balance-table {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            max-height: 420px;
            overflow: auto;
        }

        .disb-edit .line-balance-table table {
            margin-bottom: 0;
        }

        .disb-edit .line-balance-table thead th {
            background: #f8fafc;
            color: #475569;
            font-size: .72rem;
            letter-spacing: 0;
            position: sticky;
            text-transform: uppercase;
            top: 0;
            z-index: 2;
        }

        @media (max-width: 991.98px) {
            .disb-edit .stat-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            .disb-edit .stat-grid {
                grid-template-columns: 1fr;
            }

            .disb-edit .signed-document-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $currency = $purchaseOrder->resolved_currency ?? $disbursement->resolved_currency ?? 'USD';
        $money = fn ($value) => trim($currency . ' ' . number_format((float) $value, 2));
        $existingPaymentRowsById = collect($paymentRows ?? [])->keyBy(fn ($row) => (string) ($row['id'] ?? ''));
        $submittedPaymentRows = collect(old('payments', $paymentRows ?? []))
            ->map(function ($row) use ($existingPaymentRowsById) {
                $id = (string) ($row['id'] ?? '');
                if ($id !== '' && ! isset($row['signed_documents'])) {
                    $row['signed_documents'] = $existingPaymentRowsById->get($id)['signed_documents'] ?? [];
                }

                return $row;
            })
            ->values()
            ->all();
        $submittedDeletePaymentIds = array_values(old('delete_payment_ids', []));
        $paidStatuses = \App\Models\ProcurementPurchaseOrder::PAID_DISBURSEMENT_STATUSES;
        $statusBadgeClasses = [
            'completed' => 'bg-success',
            'paid' => 'bg-success',
            'fully_paid' => 'bg-success',
            'pending' => 'bg-warning text-dark',
            'cancelled' => 'bg-danger',
            'void' => 'bg-secondary',
            'reversed' => 'bg-secondary',
        ];
    @endphp

    <div class="nxl-container disb-edit">
        <div class="card hero-card mb-4">
            <div class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3">
                <div>
                    <h4 class="fw-bold mb-1">Edit Disbursement Payments</h4>
                    <p class="mb-0">
                        {{ $purchaseOrder->reference_no ?? 'N/A' }} | Seeded from {{ $disbursement->reference_no ?? 'this receipt' }}
                    </p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge {{ $statusBadgeClasses[$disbursement->status ?? 'completed'] ?? 'bg-secondary' }} px-3 py-2 text-capitalize">
                        {{ str_replace('_', ' ', $disbursement->status ?? 'completed') }}
                    </span>
                    <a href="{{ route('procurement.disbursements.show', $disbursement) }}" class="btn btn-outline-light btn-sm">
                        <i class="feather-arrow-left me-1"></i> Back to Receipt
                    </a>
                </div>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card panel-card mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                    <div>
                        <div class="section-title mb-1">Purchase Order Context</div>
                        <h5 class="fw-bold mb-1">{{ $purchaseOrder->reference_no ?? 'N/A' }}</h5>
                        <div class="text-muted">{{ $purchaseOrder->po_title ?? $purchaseOrder->procurement?->title ?? 'Purchase order payment' }}</div>
                    </div>
                    <div class="text-md-end">
                        <div class="text-muted small">Vendor / Recipient</div>
                        <div class="fw-semibold">{{ $purchaseOrder->vendor?->name ?? $disbursement->vendor?->name ?? 'Vendor' }}</div>
                        <div class="small text-muted">{{ $purchaseOrder->vendor?->email ?? $disbursement->vendor?->email ?? 'N/A' }}</div>
                    </div>
                </div>

                <div class="stat-grid">
                    <div class="stat-tile">
                        <div class="stat-label">PO Amount</div>
                        <div class="stat-value">{{ $money($purchaseOrder->amount) }}</div>
                    </div>
                    <div class="stat-tile">
                        <div class="stat-label">Paid Outside This Edit</div>
                        <div class="stat-value">{{ $money($paidExcludingEditable) }}</div>
                    </div>
                    <div class="stat-tile">
                        <div class="stat-label">Editable PO Balance</div>
                        <div class="stat-value">{{ $money($editablePoBalance) }}</div>
                    </div>
                    <div class="stat-tile">
                        <div class="stat-label">Payment Rows</div>
                        <div class="stat-value">{{ $editableDisbursements->count() }}</div>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('procurement.disbursements.update', $disbursement) }}" enctype="multipart/form-data" id="disbursementEditForm">
            @csrf
            @method('PUT')

            <div class="row g-4">
                <div class="col-xl-4">
                    <div class="card panel-card h-100">
                        <div class="card-body">
                            <div class="section-title mb-3">Line Item Balances</div>
                            <div class="line-balance-table table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th class="text-end">Amount</th>
                                            <th class="text-end">Available</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($lineItemsData as $item)
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold">{{ $item['resource'] ?? 'Line item' }}</div>
                                                    <small class="text-muted">{{ $item['deliverable_title'] ?? $item['category'] ?? 'N/A' }}</small>
                                                </td>
                                                <td class="text-end">{{ $currency }} {{ number_format((float) $item['amount'], 2) }}</td>
                                                <td class="text-end text-success">{{ $currency }} {{ number_format((float) $item['base_remaining_amount'], 2) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="text-center text-muted">No item lines found for this PO.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-8">
                    <div class="card panel-card">
                        <div class="card-body">
                            <div class="d-flex flex-column flex-lg-row justify-content-between gap-2 mb-3">
                                <div>
                                    <div class="section-title mb-1">Editable Payment Details</div>
                                    <div class="text-muted small">Add, remove, or update each paid PO item line. Every row keeps a unique receipt reference.</div>
                                </div>
                                <button type="button" class="btn btn-outline-primary" id="addPaymentLineBtn">
                                    <i class="feather-plus me-1"></i> Add Payment Item Line
                                </button>
                            </div>

                            <div class="payment-toolbar d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                                <div>
                                    Paid total in this edit:
                                    <strong>{{ $currency }} <span id="paymentBatchTotal">0.00</span></strong>
                                </div>
                                <div>
                                    Remaining after edit:
                                    <strong>{{ $currency }} <span id="paymentBatchRemaining">0.00</span></strong>
                                </div>
                            </div>

                            <div id="paymentLines" class="payment-lines"></div>
                            <div id="paymentLinesEmpty" class="alert alert-light border mb-0">
                                No payment rows are currently selected. Add a payment item line to continue.
                            </div>
                            <div id="deletedPaymentRows"></div>

                            <div class="d-flex flex-wrap justify-content-end gap-2 mt-3">
                                <a href="{{ route('procurement.disbursements.show', $disbursement) }}" class="btn btn-light">
                                    Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="feather-save me-1"></i> Save Payment Lines
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const lineItems = @json($lineItemsData);
            const initialPayments = @json($submittedPaymentRows);
            const initialDeletePaymentIds = @json($submittedDeletePaymentIds);
            const paymentMethods = @json($paymentMethods);
            const statusOptions = @json($statusOptions);
            const paidStatuses = @json($paidStatuses);
            const currency = @json($currency);
            const editablePoBalance = Number(@json($editablePoBalance));
            const paymentLines = document.getElementById('paymentLines');
            const paymentLinesEmpty = document.getElementById('paymentLinesEmpty');
            const deletedPaymentRows = document.getElementById('deletedPaymentRows');
            const addPaymentLineBtn = document.getElementById('addPaymentLineBtn');
            const batchTotal = document.getElementById('paymentBatchTotal');
            const batchRemaining = document.getElementById('paymentBatchRemaining');

            if (!paymentLines) return;

            const fmt = (value) =>
                Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            const escapeHtml = (value) => String(value || '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            }[char]));

            const isPaidStatus = (value) => paidStatuses.includes(String(value || '').toLowerCase());

            function methodOptions(selected = '') {
                const methods = paymentMethods.includes(selected) || selected === ''
                    ? paymentMethods
                    : [selected].concat(paymentMethods);

                return [''].concat(methods).map((method) => {
                    const label = method || 'Select method';
                    return `<option value="${escapeHtml(method)}" ${selected === method ? ' selected' : ''}>${escapeHtml(label)}</option>`;
                }).join('');
            }

            function statusOptionsHtml(selected = 'completed') {
                return Object.entries(statusOptions).map(([value, label]) =>
                    `<option value="${escapeHtml(value)}" ${selected === value ? ' selected' : ''}>${escapeHtml(label)}</option>`
                ).join('');
            }

            function itemOptions(selected = '') {
                return [''].concat(lineItems).map((item) => {
                    if (item === '') {
                        return '<option value="">Select paid item line</option>';
                    }
                    const label = `${item.resource || item.category || 'Line item'} | Available ${currency} ${fmt(item.base_remaining_amount)}`;
                    return `<option value="${escapeHtml(item.id)}" ${String(selected) === String(item.id) ? ' selected' : ''}>${escapeHtml(label)}</option>`;
                }).join('');
            }

            function selectedPaymentTotalForItem(itemId, excludingCard = null) {
                return Array.from(paymentLines.querySelectorAll('.payment-line-card'))
                    .filter((card) => card !== excludingCard
                        && card.querySelector('.payment-item-select')?.value === String(itemId)
                        && isPaidStatus(card.querySelector('.payment-status-select')?.value))
                    .reduce((total, card) => total + Number(card.querySelector('.payment-amount-input')?.value || 0), 0);
            }

            function updatePaymentCardLimit(card) {
                if (!card) return;

                const itemId = card.querySelector('.payment-item-select')?.value || '';
                const amountInput = card.querySelector('.payment-amount-input');
                const status = card.querySelector('.payment-status-select')?.value || 'completed';
                const hint = card.querySelector('.payment-line-limit');
                const item = lineItems.find((candidate) => String(candidate.id) === String(itemId));

                if (!item || !amountInput || !hint) {
                    if (amountInput) amountInput.removeAttribute('max');
                    if (hint) hint.textContent = `Select a line item (${currency})`;
                    return;
                }

                const lineBalance = Number(item.base_remaining_amount || 0);
                const usedElsewhere = selectedPaymentTotalForItem(item.id, card);
                const max = isPaidStatus(status)
                    ? Math.max(lineBalance - usedElsewhere, 0)
                    : Number(item.amount || lineBalance || 0);

                amountInput.max = max.toFixed(2);
                hint.textContent = `Available for this row: ${currency} ${fmt(max)}`;

                if (Number(amountInput.value || 0) > max) {
                    amountInput.value = max > 0 ? max.toFixed(2) : '';
                }
            }

            function updatePaymentRows() {
                const cards = Array.from(paymentLines.querySelectorAll('.payment-line-card'));
                let total = 0;

                cards.forEach((card, index) => {
                    const number = card.querySelector('.payment-line-number');
                    if (number) number.textContent = index + 1;
                    if (isPaidStatus(card.querySelector('.payment-status-select')?.value)) {
                        total += Number(card.querySelector('.payment-amount-input')?.value || 0);
                    }
                });

                paymentLinesEmpty?.classList.toggle('d-none', cards.length > 0);
                if (batchTotal) batchTotal.textContent = fmt(total);
                if (batchRemaining) batchRemaining.textContent = fmt(Math.max(editablePoBalance - total, 0));
            }

            function addSignedDocumentRow(card, index, documentName = '') {
                const list = card.querySelector('.signed-document-list');
                if (!list) return;

                const row = document.createElement('div');
                row.className = 'signed-document-row';
                row.innerHTML = `
                    <div>
                        <label class="signed-document-label">Document Name</label>
                        <input type="text"
                            name="payments[${index}][signed_document_names][]"
                            class="form-control"
                            maxlength="255"
                            value="${escapeHtml(documentName)}"
                            placeholder="Signed approval, cheque copy, bank advice">
                    </div>
                    <div>
                        <label class="signed-document-label">Signed File</label>
                        <input type="file"
                            name="payments[${index}][signed_documents][]"
                            class="form-control"
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip">
                    </div>
                    <button type="button" class="btn btn-outline-danger signed-document-remove" aria-label="Remove signed document row">
                        <i class="feather-trash-2"></i>
                    </button>
                `;

                row.querySelector('.signed-document-remove')?.addEventListener('click', () => row.remove());
                list.appendChild(row);
            }

            function existingSignedDocumentsHtml(documents = []) {
                if (!Array.isArray(documents) || documents.length === 0) {
                    return '<div class="small text-muted">No signed payment documents have been uploaded yet.</div>';
                }

                return `
                    <div class="d-flex flex-wrap gap-1">
                        ${documents.map((document) => `
                            <a href="${escapeHtml(document.url || '#')}" class="badge bg-light text-dark border" title="${escapeHtml(document.name || 'Document')}">
                                ${escapeHtml(document.display_name || document.name || 'Document')}
                            </a>
                        `).join('')}
                    </div>
                `;
            }

            function addDeletedPaymentId(id) {
                if (!id || !deletedPaymentRows) return;
                if (Array.from(deletedPaymentRows.querySelectorAll('input')).some((input) => input.value === String(id))) return;

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete_payment_ids[]';
                input.value = id;
                deletedPaymentRows.appendChild(input);
            }

            function addPaymentRow(defaults = {}) {
                const index = `${Date.now()}-${Math.floor(Math.random() * 10000)}`;
                const selectedItemId = defaults.purchase_request_item_id || '';
                const selectedItem = lineItems.find((item) => String(item.id) === String(selectedItemId));
                const suggestedAmount = defaults.amount || (selectedItem ? Number(selectedItem.base_remaining_amount || selectedItem.amount || 0).toFixed(2) : '');
                const paidAt = defaults.paid_at || new Date().toISOString().slice(0, 10);

                const card = document.createElement('div');
                card.className = 'payment-line-card';
                card.innerHTML = `
                    <div class="d-flex flex-column flex-lg-row justify-content-between gap-2 mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="payment-line-number">1</span>
                            <div>
                                <div class="fw-semibold">Payment Item Line</div>
                                <div class="small text-muted payment-line-limit">Select a line item</div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger payment-line-remove">
                            <i class="feather-trash-2 me-1"></i> Remove
                        </button>
                    </div>
                    ${defaults.id ? `<input type="hidden" name="payments[${index}][id]" value="${escapeHtml(defaults.id)}">` : ''}
                    <div class="row g-3">
                        <div class="col-lg-5">
                            <label class="form-label fw-semibold">Paid PO Line Item <span class="text-danger">*</span></label>
                            <select name="payments[${index}][purchase_request_item_id]" class="form-select payment-item-select" required>
                                ${itemOptions(selectedItemId)}
                            </select>
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label fw-semibold">Receipt Reference</label>
                            <input type="text" name="payments[${index}][reference_no]" class="form-control" maxlength="100"
                                value="${escapeHtml(defaults.reference_no || '')}" placeholder="Auto if blank">
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">${escapeHtml(currency)}</span>
                                <input type="number" step="0.01" min="0.01" name="payments[${index}][amount]"
                                    class="form-control payment-amount-input" value="${escapeHtml(suggestedAmount)}" required>
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label fw-semibold">Payment Method <span class="text-danger">*</span></label>
                            <select name="payments[${index}][payment_method]" class="form-select" required>
                                ${methodOptions(defaults.payment_method || '')}
                            </select>
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label fw-semibold">Paid At <span class="text-danger">*</span></label>
                            <input type="date" name="payments[${index}][paid_at]" class="form-control" value="${escapeHtml(paidAt)}" required>
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="payments[${index}][status]" class="form-select payment-status-select" required>
                                ${statusOptionsHtml(defaults.status || 'completed')}
                            </select>
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label fw-semibold">Transfer Reference</label>
                            <input type="text" name="payments[${index}][transfer_reference]" class="form-control" maxlength="255"
                                value="${escapeHtml(defaults.transfer_reference || '')}" placeholder="Bank or cheque reference">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea name="payments[${index}][notes]" rows="2" class="form-control" maxlength="2000">${escapeHtml(defaults.notes || '')}</textarea>
                        </div>
                        <div class="col-12">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-2">
                                <div>
                                    <label class="form-label fw-semibold mb-0">Signed Payment Documents</label>
                                    <div class="small text-muted">Existing files remain attached. Add new signed documents below when needed.</div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary signed-document-add">
                                    <i class="feather-plus me-1"></i> Add Document
                                </button>
                            </div>
                            <div class="mb-2">
                                ${existingSignedDocumentsHtml(defaults.signed_documents || [])}
                            </div>
                            <div class="signed-document-list"></div>
                        </div>
                    </div>
                `;

                card.querySelector('.payment-line-remove')?.addEventListener('click', () => {
                    addDeletedPaymentId(defaults.id);
                    card.remove();
                    Array.from(paymentLines.querySelectorAll('.payment-line-card')).forEach(updatePaymentCardLimit);
                    updatePaymentRows();
                });
                card.querySelector('.payment-item-select')?.addEventListener('change', () => {
                    updatePaymentCardLimit(card);
                    updatePaymentRows();
                });
                card.querySelector('.payment-status-select')?.addEventListener('change', () => {
                    Array.from(paymentLines.querySelectorAll('.payment-line-card')).forEach(updatePaymentCardLimit);
                    updatePaymentRows();
                });
                card.querySelector('.payment-amount-input')?.addEventListener('input', () => {
                    Array.from(paymentLines.querySelectorAll('.payment-line-card')).forEach(updatePaymentCardLimit);
                    updatePaymentRows();
                });
                card.querySelector('.signed-document-add')?.addEventListener('click', () => {
                    addSignedDocumentRow(card, index);
                });

                paymentLines.appendChild(card);
                const submittedNames = Array.isArray(defaults.signed_document_names)
                    ? defaults.signed_document_names.filter((name) => String(name || '').trim() !== '')
                    : [];
                submittedNames.forEach((name) => addSignedDocumentRow(card, index, name));
                updatePaymentCardLimit(card);
                updatePaymentRows();
            }

            initialDeletePaymentIds.forEach((id) => addDeletedPaymentId(id));
            initialPayments.forEach((row) => addPaymentRow(row));
            if (initialPayments.length === 0) addPaymentRow();
            addPaymentLineBtn?.addEventListener('click', () => addPaymentRow());
        })();
    </script>
@endpush
