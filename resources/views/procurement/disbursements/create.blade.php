@extends('layouts.app')

@push('styles')
    <style>
        .disb-create .po-document {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.07);
        }

        .disb-create .po-document-header {
            border-bottom: 1px solid #e2e8f0;
            padding: 20px;
        }

        .disb-create .po-document-body {
            padding: 20px;
        }

        .disb-create .section-title {
            color: #64748b;
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .05em;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .disb-create .stat-tile {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            height: 100%;
            padding: 14px;
        }

        .disb-create .stat-label {
            color: #64748b;
            font-size: .72rem;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .disb-create .stat-value {
            color: #0f172a;
            font-size: 1.05rem;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .disb-create .line-table {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            max-height: 340px;
            overflow: auto;
        }

        .disb-create .line-table table {
            margin-bottom: 0;
        }

        .disb-create .line-table thead th {
            background: #f8fafc;
            color: #475569;
            font-size: .72rem;
            letter-spacing: .04em;
            position: sticky;
            text-transform: uppercase;
            top: 0;
            z-index: 2;
        }

        .disb-create .line-item-evidence-check {
            accent-color: #0f766e;
            cursor: pointer;
            height: 20px;
            width: 20px;
        }

        .disb-create .line-item-date-input {
            min-width: 145px;
        }

        .disb-create .evidence-status {
            border-radius: 999px;
            min-width: 104px;
        }

        .disb-create .evidence-field-bank {
            display: none;
        }

        .disb-create .evidence-document-row {
            align-items: end;
            background: #fff;
            border: 1px solid #e3eaf4;
            border-radius: 10px;
            display: grid;
            gap: 8px;
            grid-template-columns: minmax(180px, 1fr) minmax(220px, 1.25fr) auto;
            padding: 10px;
        }

        .disb-create .evidence-document-row + .evidence-document-row {
            margin-top: 8px;
        }

        .disb-create .evidence-document-label {
            color: #667085;
            display: block;
            font-size: .72rem;
            font-weight: 700;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        #lineItemEvidenceModal {
            z-index: 1095;
        }

        #lineItemEvidenceModal.show {
            background: rgba(16, 24, 40, .42);
            display: block;
        }

        #lineItemEvidenceModal .modal-dialog {
            max-width: min(860px, calc(100vw - 28px));
        }

        #lineItemEvidenceModal .modal-content {
            border: 0;
            border-radius: 14px;
            box-shadow: 0 28px 80px rgba(15, 23, 42, .28);
            overflow: hidden;
        }

        .evidence-modal-summary {
            background: #f8fafc;
            border: 1px solid #e3eaf4;
            border-left: 3px solid #0f766e;
            border-radius: 10px;
            padding: 12px 14px;
        }

        body.line-evidence-modal-open {
            overflow: hidden;
        }

        @media (max-width: 575.98px) {
            .disb-create .evidence-document-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <div class="nxl-container disb-create">
        <div class="page-header d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
            <div>
                <h4 class="fw-bold mb-1">Create Disbursement</h4>
                <p class="text-muted mb-0">Record payment against a purchase order and confirm linked deliverables.</p>
            </div>
            <a href="{{ route('procurement.disbursements.index') }}" class="btn btn-outline-secondary">
                <i class="feather-arrow-left me-1"></i> Back
            </a>
        </div>

        @if (session('error'))
            <div class="alert alert-danger mt-3">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger mt-3">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($purchaseOrders->isEmpty())
            <div class="alert alert-warning mt-3">
                No purchase orders with remaining balance are available for disbursement.
            </div>
        @else
            <form method="POST" action="{{ route('procurement.disbursements.store') }}" enctype="multipart/form-data" id="disbursementForm">
                @csrf

                <div class="po-document mt-4">
                    <div class="po-document-header d-flex flex-column flex-lg-row justify-content-between gap-3">
                        <div>
                            <div class="section-title">Disbursement Entry</div>
                            <h5 class="fw-bold mb-1" id="documentPoReference">Select a Purchase Order</h5>
                            <div class="text-muted" id="documentPoTitle">Choose a purchase order to load payment and deliverable details.</div>
                        </div>
                        <div class="text-lg-end">
                            <span class="badge bg-secondary px-3 py-2" id="documentPoStatus">Pending Selection</span>
                            <div class="text-muted small mt-2" id="documentPoVendor">Vendor: N/A</div>
                        </div>
                    </div>

                    <div class="po-document-body">
                        <div class="section-title">Purchase Order</div>
                        <div class="row g-3 align-items-end mb-4">
                            <div class="col-lg-8">
                                <label class="form-label fw-semibold">
                                    Purchase Order <span class="text-danger">*</span>
                                </label>
                                <select name="purchase_order_id" id="purchaseOrderSelect" class="form-select" required>
                                    <option value="">Select a Purchase Order</option>
                                    @foreach ($purchaseOrders as $order)
                                        <option value="{{ $order->id }}"
                                            @selected((string) old('purchase_order_id', $purchaseOrder?->id) === (string) $order->id)>
                                            {{ $order->reference_no ?? 'N/A' }}
                                            @if ($order->po_title)
                                                - {{ $order->po_title }}
                                            @elseif ($order->procurement?->title)
                                                - {{ $order->procurement->title }}
                                            @elseif ($order->thinkTankMember?->name)
                                                - {{ $order->thinkTankMember->name }}
                                            @else
                                                - Fund Transfer
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div id="poDetailsPanel" class="d-none">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <div class="stat-tile">
                                        <div class="stat-label">Total PO Amount</div>
                                        <div class="stat-value"><span id="po-amount">N/A</span> <span class="po-currency-tag"></span></div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-tile">
                                        <div class="stat-label">Already Paid</div>
                                        <div class="stat-value text-warning"><span id="po-paid">N/A</span> <span class="po-currency-tag"></span></div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-tile">
                                        <div class="stat-label">Remaining Balance</div>
                                        <div class="stat-value text-success"><span id="po-remaining">N/A</span> <span class="po-currency-tag"></span></div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-tile">
                                        <div class="stat-label">Expected Delivery</div>
                                        <div class="stat-value" id="po-expected-delivery">N/A</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-4 mt-1">
                                <div class="col-lg-6">
                                    <div class="section-title">Vendor and Procurement</div>
                                    <div class="mb-3">
                                        <div class="text-muted small">Vendor / Recipient</div>
                                        <div class="fw-semibold" id="po-vendor">N/A</div>
                                        <div class="small text-muted" id="po-vendor-email"></div>
                                        <div class="small text-muted" id="po-vendor-phone"></div>
                                        <div class="small text-muted" id="po-vendor-contact-name"></div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-muted small">Procurement / Title</div>
                                        <div class="fw-semibold" id="po-title">N/A</div>
                                    </div>
                                    <div>
                                        <div class="text-muted small">Governance Node</div>
                                        <div id="po-governance-node">N/A</div>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="section-title">Terms and References</div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="text-muted small">Payment Terms</div>
                                            <div id="po-payment-terms">N/A</div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="text-muted small">Delivery Terms</div>
                                            <div id="po-delivery-terms">N/A</div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="text-muted small">Supplier Reference</div>
                                            <div id="po-supplier-ref">N/A</div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="text-muted small">Contract Reference</div>
                                            <div id="po-contract-ref">N/A</div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="text-muted small">Sub-Activity</div>
                                            <div id="po-sub-activity">N/A</div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="text-muted small">Valid Until</div>
                                            <div id="po-valid-until">N/A</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <div class="section-title">Linked Deliverables</div>
                                <div id="po-deliverables" class="d-flex flex-wrap gap-2">N/A</div>
                            </div>

                            <div class="mt-4">
                                <div class="section-title">Line Items from Purchase Request</div>
                                <div class="line-table table-responsive">
                                    <table class="table table-sm align-middle">
                                        <thead>
                                            <tr>
                                                <th class="text-center" style="width: 112px;">Deliverable Check</th>
                                                <th>Requested Item</th>
                                                <th>Linked Deliverable</th>
                                                <th style="width: 170px;">Date</th>
                                                <th class="text-end">Amount</th>
                                                <th class="text-center" style="width: 150px;">Evidence</th>
                                            </tr>
                                        </thead>
                                        <tbody id="poLineItemsBody"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div id="disbursementPanel" class="d-none mt-4">
                            <div class="section-title">Disbursement Details</div>
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
                                        Maximum: <strong id="amount-max-hint">N/A</strong>
                                    </div>
                                    @error('amount')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">
                                        Deliverable <span class="text-danger" id="deliverableRequiredMark">*</span>
                                    </label>
                                    <select name="deliverable_id"
                                        id="deliverableSelect"
                                        class="form-select @error('deliverable_id') is-invalid @enderror">
                                        <option value="">Select deliverable</option>
                                    </select>
                                    <div class="small text-muted mt-1" id="deliverableHelp">
                                        Select the deliverable this payment is for.
                                    </div>
                                    @error('deliverable_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">
                                        Payment Method <span class="text-danger">*</span>
                                    </label>
                                    <select name="payment_method"
                                        class="form-select @error('payment_method') is-invalid @enderror"
                                        required>
                                        <option value="">Select method</option>
                                        @foreach ($paymentMethods as $method)
                                            <option value="{{ $method }}" @selected(old('payment_method') === $method)>
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
                                        Transfer Reference <span class="text-muted fw-normal">(Optional)</span>
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
                                        Notes <span class="text-muted fw-normal">(Optional)</span>
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
                </div>

                <div id="lineItemEvidenceBank" class="evidence-field-bank"></div>

                <div class="modal fade" id="lineItemEvidenceModal" tabindex="-1" aria-labelledby="lineItemEvidenceTitle" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <div>
                                    <h5 class="modal-title mb-1" id="lineItemEvidenceTitle">Deliverable Evidence</h5>
                                    <div class="small text-muted" id="lineItemEvidenceSubtitle"></div>
                                </div>
                                <button type="button" class="btn-close line-item-evidence-close" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="evidence-modal-summary mb-3">
                                    <div class="small text-muted">Linked Deliverable</div>
                                    <div class="fw-semibold" id="lineItemEvidenceDeliverable">N/A</div>
                                </div>
                                <div id="lineItemEvidenceModalFields"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light line-item-evidence-close">Close</button>
                                <button type="button" class="btn btn-primary" id="lineItemEvidenceDoneBtn">
                                    <i class="feather-check me-1"></i> Done
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
            const oldDeliverableId = @json(old('deliverable_id'));
            const oldItemEvidence = @json(old('item_evidence', []));

            const select = document.getElementById('purchaseOrderSelect');
            const poPanel = document.getElementById('poDetailsPanel');
            const disbPanel = document.getElementById('disbursementPanel');
            const amountInput = document.getElementById('amountInput');
            const deliverableSelect = document.getElementById('deliverableSelect');
            const currPrefix = document.getElementById('currency-prefix');
            const amountHint = document.getElementById('amount-max-hint');
            const deliverableHelp = document.getElementById('deliverableHelp');
            const deliverableRequiredMark = document.getElementById('deliverableRequiredMark');
            const evidenceBank = document.getElementById('lineItemEvidenceBank');
            const evidenceModalEl = document.getElementById('lineItemEvidenceModal');
            const evidenceModalFields = document.getElementById('lineItemEvidenceModalFields');
            const evidenceModalTitle = document.getElementById('lineItemEvidenceTitle');
            const evidenceModalSubtitle = document.getElementById('lineItemEvidenceSubtitle');
            const evidenceModalDeliverable = document.getElementById('lineItemEvidenceDeliverable');
            const evidenceDoneBtn = document.getElementById('lineItemEvidenceDoneBtn');
            let activeEvidenceFieldset = null;
            let currentPo = null;

            if (!select) return;

            const fmt = (value) =>
                Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            const escapeHtml = (value) => String(value || '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            }[char]));

            const setText = (id, value) => {
                const element = document.getElementById(id);
                if (element) element.textContent = value || 'N/A';
            };

            const statusBadges = {
                draft: '<span class="badge bg-secondary px-3 py-2">Draft</span>',
                issued: '<span class="badge bg-primary px-3 py-2">Issued</span>',
                partial_paid: '<span class="badge bg-warning text-dark px-3 py-2">Partial</span>',
                paid: '<span class="badge bg-success px-3 py-2">Paid</span>',
                cancelled: '<span class="badge bg-danger px-3 py-2">Cancelled</span>',
            };

            function evidenceDefaults(item) {
                const previous = item.evidence || {};
                const submitted = oldItemEvidence[item.id] || {};

                return {
                    ...previous,
                    ...submitted,
                    documents: previous.documents || [],
                };
            }

            function evidenceIsConfirmed(value) {
                return value === true || value === 1 || value === '1' || value === 'on';
            }

            function returnActiveEvidenceFieldset() {
                if (activeEvidenceFieldset && evidenceBank) {
                    evidenceBank.appendChild(activeEvidenceFieldset);
                    activeEvidenceFieldset = null;
                }
            }

            function addEvidenceDocumentRow(fieldset, itemId, documentName = '') {
                const list = fieldset.querySelector('.evidence-document-list');
                if (!list) return;

                const row = document.createElement('div');
                row.className = 'evidence-document-row';
                row.innerHTML = `
                    <div>
                        <label class="evidence-document-label">Document Name</label>
                        <input type="text"
                            name="item_evidence[${itemId}][document_names][]"
                            class="form-control evidence-document-name"
                            maxlength="255"
                            placeholder="Signed contract, delivery note, acceptance memo"
                            value="${escapeHtml(documentName)}">
                    </div>
                    <div>
                        <label class="evidence-document-label">Upload File</label>
                        <input type="file"
                            name="item_evidence[${itemId}][documents][]"
                            class="form-control evidence-document-input"
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip">
                    </div>
                    <button type="button" class="btn btn-outline-danger evidence-document-remove" aria-label="Remove document row">
                        <i class="feather-trash-2"></i>
                    </button>
                `;

                row.querySelector('.evidence-document-input')?.addEventListener('change', () => {
                    updateEvidenceRowState(itemId);
                });
                row.querySelector('.evidence-document-remove')?.addEventListener('click', () => {
                    row.remove();
                    updateEvidenceRowState(itemId);
                });

                list.appendChild(row);
            }

            function ensureEvidenceFieldset(item) {
                if (!item?.id || !evidenceBank) return null;

                let fieldset = document.querySelector(`.line-item-evidence-fieldset[data-item-id="${item.id}"]`);
                if (fieldset) return fieldset;

                const previous = evidenceDefaults(item);
                const existingDocuments = Array.isArray(previous.documents) ? previous.documents : [];
                const submittedDocumentNames = Array.isArray(previous.document_names)
                    ? previous.document_names.filter((name) => String(name || '').trim() !== '')
                    : [];
                const existingDocumentHtml = existingDocuments.length > 0
                    ? `
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Existing Documents</label>
                            <div class="d-flex flex-wrap gap-1">
                                ${existingDocuments.map((document) => `
                                    <a href="${escapeHtml(document.url || '#')}" class="badge bg-light text-dark border" title="${escapeHtml(document.name || 'Document')}">
                                        ${escapeHtml(document.display_name || document.name || 'Document')}
                                    </a>
                                `).join('')}
                            </div>
                        </div>
                    `
                    : '';

                fieldset = document.createElement('div');
                fieldset.className = 'line-item-evidence-fieldset';
                fieldset.dataset.itemId = item.id;
                fieldset.innerHTML = `
                    <div class="form-check form-switch mb-3">
                        <input type="checkbox"
                            class="form-check-input modal-evidence-met"
                            id="modalEvidenceMet-${item.id}"
                            ${evidenceIsConfirmed(previous.is_met) ? ' checked' : ''}>
                        <label class="form-check-label fw-semibold" for="modalEvidenceMet-${item.id}">
                            Confirm this deliverable for the line item
                        </label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea name="item_evidence[${item.id}][notes]"
                            class="form-control modal-evidence-notes"
                            rows="4"
                            maxlength="3000"
                            placeholder="Add acceptance notes, delivery comments, or review observations.">${escapeHtml(previous.notes || '')}</textarea>
                    </div>
                    ${existingDocumentHtml}
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-semibold mb-0">Relevant Documents</label>
                            <button type="button" class="btn btn-sm btn-outline-primary evidence-document-add">
                                <i class="feather-plus me-1"></i> Add Document
                            </button>
                        </div>
                        <div class="evidence-document-list"></div>
                        <div class="form-text">PDF, Office, image, or ZIP files up to 20 MB each.</div>
                    </div>
                `;

                fieldset.querySelector('.modal-evidence-met')?.addEventListener('change', (event) => {
                    const rowCheck = document.querySelector(`.line-item-evidence-check[data-item-id="${item.id}"]`);
                    if (rowCheck) rowCheck.checked = event.target.checked;
                    updateEvidenceRowState(item.id);
                });
                fieldset.querySelector('.modal-evidence-notes')?.addEventListener('input', () => {
                    updateEvidenceRowState(item.id);
                });
                fieldset.querySelector('.evidence-document-add')?.addEventListener('click', () => {
                    addEvidenceDocumentRow(fieldset, item.id);
                });

                evidenceBank.appendChild(fieldset);

                if (submittedDocumentNames.length > 0) {
                    submittedDocumentNames.forEach((name) => addEvidenceDocumentRow(fieldset, item.id, name));
                } else {
                    addEvidenceDocumentRow(fieldset, item.id);
                }

                return fieldset;
            }

            function updateEvidenceRowState(itemId) {
                const fieldset = document.querySelector(`.line-item-evidence-fieldset[data-item-id="${itemId}"]`);
                const rowCheck = document.querySelector(`.line-item-evidence-check[data-item-id="${itemId}"]`);
                const status = document.querySelector(`.evidence-status[data-item-id="${itemId}"]`);
                if (!fieldset || !status) return;

                const modalCheck = fieldset.querySelector('.modal-evidence-met');
                if (modalCheck && rowCheck) modalCheck.checked = rowCheck.checked;

                const confirmed = rowCheck?.checked || false;
                const date = (document.querySelector(`.line-item-date-input[data-item-id="${itemId}"]`)?.value || '').trim();
                const notes = (fieldset.querySelector('.modal-evidence-notes')?.value || '').trim();
                const existingCount = fieldset.querySelectorAll('a.badge').length;
                const newFileCount = Array.from(fieldset.querySelectorAll('.evidence-document-input'))
                    .reduce((total, input) => total + (input.files ? input.files.length : 0), 0);
                const fileCount = existingCount + newFileCount;

                if (confirmed) {
                    status.className = 'badge bg-success-subtle text-success evidence-status';
                    status.textContent = fileCount > 0 ? `Confirmed (${fileCount})` : 'Confirmed';
                } else if (fileCount > 0 || notes !== '') {
                    status.className = 'badge bg-info-subtle text-info evidence-status';
                    status.textContent = fileCount > 0 ? `Docs (${fileCount})` : 'Notes added';
                } else if (date !== '') {
                    status.className = 'badge bg-info-subtle text-info evidence-status';
                    status.textContent = 'Date set';
                } else {
                    status.className = 'badge bg-light text-muted border evidence-status';
                    status.textContent = 'Pending';
                }
            }

            function showEvidenceModal() {
                if (!evidenceModalEl) return;

                document.body.classList.add('line-evidence-modal-open');
                evidenceModalEl.classList.add('show');
                evidenceModalEl.style.display = 'block';
                evidenceModalEl.removeAttribute('aria-hidden');
                evidenceModalEl.setAttribute('aria-modal', 'true');
                evidenceModalEl.setAttribute('role', 'dialog');

                setTimeout(() => {
                    evidenceModalEl.querySelector('.modal-evidence-met, .evidence-document-add, .btn-close')?.focus();
                }, 0);
            }

            function closeEvidenceModal() {
                if (activeEvidenceFieldset) {
                    updateEvidenceRowState(activeEvidenceFieldset.dataset.itemId);
                }

                if (evidenceModalEl) {
                    evidenceModalEl.classList.remove('show');
                    evidenceModalEl.style.display = 'none';
                    evidenceModalEl.setAttribute('aria-hidden', 'true');
                    evidenceModalEl.removeAttribute('aria-modal');
                    evidenceModalEl.removeAttribute('role');
                }

                document.body.classList.remove('line-evidence-modal-open');
                returnActiveEvidenceFieldset();
            }

            function openEvidenceModal(item, po) {
                const fieldset = ensureEvidenceFieldset(item);
                if (!fieldset || !evidenceModalFields) return;

                returnActiveEvidenceFieldset();
                activeEvidenceFieldset = fieldset;
                evidenceModalFields.appendChild(fieldset);

                if (evidenceModalTitle) evidenceModalTitle.textContent = item.resource || item.category || 'Requested Line Item';
                if (evidenceModalSubtitle) evidenceModalSubtitle.textContent = `${item.category || 'N/A'} | ${po.currency || ''} ${fmt(item.amount)}`;
                if (evidenceModalDeliverable) evidenceModalDeliverable.textContent = item.deliverable_title || 'No deliverable linked';

                showEvidenceModal();
            }

            function renderLineItems(po) {
                const body = document.getElementById('poLineItemsBody');
                returnActiveEvidenceFieldset();
                if (evidenceBank) evidenceBank.innerHTML = '';
                if (!body) return;

                const items = Array.isArray(po.line_items) ? po.line_items : [];
                body.innerHTML = '';

                if (items.length === 0) {
                    body.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No line items found for this purchase order.</td></tr>';
                    return;
                }

                items.forEach((item) => {
                    const previous = evidenceDefaults(item);
                    const confirmed = evidenceIsConfirmed(previous.is_met);
                    const deliverableDate = previous.deliverable_date || '';
                    ensureEvidenceFieldset(item);

                    const row = document.createElement('tr');
                    row.dataset.itemId = item.id;
                    row.innerHTML = `
                        <td class="text-center">
                            <input type="checkbox"
                                name="item_evidence[${item.id}][is_met]"
                                value="1"
                                class="line-item-evidence-check"
                                data-item-id="${item.id}"
                                ${confirmed ? ' checked' : ''}
                                aria-label="Confirm deliverable for ${escapeHtml(item.resource || item.category || 'line item')}">
                        </td>
                        <td>
                            <div class="fw-semibold">${escapeHtml(item.resource || 'N/A')}</div>
                            <small class="text-muted">${escapeHtml(item.category || 'N/A')}</small>
                            <div class="small text-muted">${escapeHtml(item.budget_code || '')}</div>
                        </td>
                        <td>${escapeHtml(item.deliverable_title || 'N/A')}</td>
                        <td>
                            <input type="date"
                                name="item_evidence[${item.id}][deliverable_date]"
                                class="form-control form-control-sm line-item-date-input"
                                data-item-id="${item.id}"
                                value="${escapeHtml(deliverableDate)}">
                        </td>
                        <td class="text-end fw-semibold">${escapeHtml(po.currency || '')} ${fmt(item.amount)}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-primary evidence-edit-btn" data-item-id="${item.id}">
                                <i class="feather-upload-cloud me-1"></i> Evidence
                            </button>
                            <div class="mt-1">
                                <span class="badge bg-light text-muted border evidence-status" data-item-id="${item.id}">Pending</span>
                            </div>
                        </td>
                    `;

                    row.querySelector('.line-item-evidence-check')?.addEventListener('change', () => {
                        updateEvidenceRowState(item.id);
                    });
                    row.querySelector('.line-item-date-input')?.addEventListener('change', () => {
                        updateEvidenceRowState(item.id);
                    });
                    row.querySelector('.evidence-edit-btn')?.addEventListener('click', () => {
                        openEvidenceModal(item, po);
                    });

                    body.appendChild(row);
                    updateEvidenceRowState(item.id);
                });
            }

            function renderDeliverables(po) {
                const deliverables = Array.isArray(po.deliverables) ? po.deliverables : [];
                const list = document.getElementById('po-deliverables');

                if (list) {
                    if (deliverables.length === 0) {
                        list.innerHTML = '<span class="text-muted">No deliverables linked to this purchase order.</span>';
                    } else {
                        list.innerHTML = deliverables.map((deliverable) => {
                            const amount = Number(deliverable.amount || 0);
                            const amountText = amount > 0
                                ? ` | ${deliverable.currency || po.currency || ''} ${fmt(amount)}`
                                : '';
                            const refText = deliverable.procurement_ref ? ` | ${deliverable.procurement_ref}` : '';

                            return `
                                <span class="badge bg-light text-dark border px-3 py-2 text-start">
                                    ${escapeHtml(deliverable.title || 'Untitled deliverable')}${escapeHtml(refText)}${escapeHtml(amountText)}
                                </span>
                            `;
                        }).join('');
                    }
                }

                if (!deliverableSelect) return;
                deliverableSelect.innerHTML = '';

                if (deliverables.length === 0) {
                    deliverableSelect.required = false;
                    deliverableSelect.disabled = true;
                    deliverableSelect.innerHTML = '<option value="">No deliverables linked to this PO</option>';
                    if (deliverableHelp) deliverableHelp.textContent = 'This purchase order has no deliverables linked yet.';
                    if (deliverableRequiredMark) deliverableRequiredMark.classList.add('d-none');
                    return;
                }

                deliverableSelect.required = true;
                deliverableSelect.disabled = false;
                if (deliverableHelp) deliverableHelp.textContent = 'Select the deliverable this payment is for.';
                if (deliverableRequiredMark) deliverableRequiredMark.classList.remove('d-none');

                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'Select deliverable';
                deliverableSelect.appendChild(placeholder);

                deliverables.forEach((deliverable) => {
                    const option = document.createElement('option');
                    option.value = deliverable.id;
                    option.textContent = [
                        deliverable.title || 'Untitled deliverable',
                        deliverable.procurement_ref || null,
                        deliverable.amount > 0 ? `${deliverable.currency || po.currency || ''} ${fmt(deliverable.amount)}` : null,
                    ].filter(Boolean).join(' | ');
                    deliverableSelect.appendChild(option);
                });

                if (oldDeliverableId && deliverables.some((deliverable) => deliverable.id === oldDeliverableId)) {
                    deliverableSelect.value = oldDeliverableId;
                } else if (deliverables.length === 1) {
                    deliverableSelect.value = deliverables[0].id;
                }
            }

            function update() {
                const id = select.value;
                const po = poData[id];
                currentPo = po || null;

                if (!po) {
                    poPanel?.classList.add('d-none');
                    disbPanel?.classList.add('d-none');
                    if (deliverableSelect) {
                        deliverableSelect.innerHTML = '<option value="">Select deliverable</option>';
                        deliverableSelect.required = false;
                        deliverableSelect.disabled = true;
                    }
                    return;
                }

                setText('documentPoReference', po.reference_no);
                setText('documentPoTitle', po.po_title || po.procurement_title || null);
                setText('documentPoVendor', po.vendor_name ? `Vendor: ${po.vendor_name}` : 'Vendor: N/A');
                const documentStatus = document.getElementById('documentPoStatus');
                if (documentStatus) documentStatus.innerHTML = statusBadges[po.status] || escapeHtml(po.status || 'Selected');

                setText('po-title', po.po_title || po.procurement_title || null);
                setText('po-governance-node', po.governance_node);
                setText('po-vendor', po.vendor_name);
                setText('po-vendor-email', po.vendor_email);
                setText('po-vendor-phone', po.vendor_contact_phone);
                setText('po-vendor-contact-name', po.vendor_contact_name ? `Contact: ${po.vendor_contact_name}` : null);
                setText('po-sub-activity', po.sub_activity);
                setText('po-payment-terms', po.payment_terms);
                setText('po-delivery-terms', po.delivery_terms);
                setText('po-supplier-ref', po.supplier_reference);
                setText('po-contract-ref', po.contract_reference);
                setText('po-expected-delivery', po.expected_delivery);
                setText('po-valid-until', po.valid_until);

                renderDeliverables(po);
                renderLineItems(po);

                const totalAmount = Number(po.amount || 0);
                const paidAmount = Number(po.paid_amount ?? po.paid ?? 0);
                const balanceAmount = Number(po.balance_amount ?? po.remaining ?? Math.max(totalAmount - paidAmount, 0));

                setText('po-amount', fmt(totalAmount));
                setText('po-paid', fmt(paidAmount));
                setText('po-remaining', fmt(balanceAmount));
                document.querySelectorAll('.po-currency-tag').forEach((element) => {
                    element.textContent = po.currency || '';
                });

                const currency = po.currency || 'USD';
                if (currPrefix) currPrefix.textContent = currency;
                if (amountHint) amountHint.textContent = `${fmt(balanceAmount)} ${currency}`;
                if (amountInput) {
                    amountInput.max = balanceAmount;
                    if (!amountInput.dataset.userSet) {
                        amountInput.value = balanceAmount;
                    } else if (parseFloat(amountInput.value || 0) > balanceAmount) {
                        amountInput.value = balanceAmount;
                    }
                }

                poPanel?.classList.remove('d-none');
                disbPanel?.classList.remove('d-none');
            }

            amountInput?.addEventListener('input', () => {
                amountInput.dataset.userSet = '1';
            });
            if (amountInput?.value) {
                amountInput.dataset.userSet = '1';
            }

            select.addEventListener('change', () => {
                if (amountInput) delete amountInput.dataset.userSet;
                update();
            });

            evidenceDoneBtn?.addEventListener('click', closeEvidenceModal);
            document.querySelectorAll('.line-item-evidence-close').forEach((button) => {
                button.addEventListener('click', closeEvidenceModal);
            });
            evidenceModalEl?.addEventListener('mousedown', (event) => {
                if (event.target === evidenceModalEl) closeEvidenceModal();
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && evidenceModalEl?.classList.contains('show')) closeEvidenceModal();
            });

            if (select.value) update();
        })();
    </script>
@endpush
