@extends('layouts.app')

@push('styles')
    <style>
        .disb-create .po-document {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.09);
            overflow: hidden;
        }

        .disb-create .page-header {
            background: linear-gradient(120deg, #0f172a 0%, #1e293b 42%, #0f766e 100%);
            border-radius: 16px;
            color: #fff;
            padding: 20px;
        }

        .disb-create .page-header h1,
        .disb-create .page-header h2,
        .disb-create .page-header h3,
        .disb-create .page-header h4,
        .disb-create .page-header h5,
        .disb-create .page-header h6,
        .disb-create .page-header p {
            color: #fff !important;
        }

        .disb-create .page-header .text-muted {
            color: rgba(255, 255, 255, .78) !important;
        }

        .disb-create .page-header .btn-outline-secondary {
            border-color: rgba(255, 255, 255, .65);
            color: #fff;
        }

        .disb-create .page-header .btn-outline-secondary:hover {
            background: rgba(255, 255, 255, .16);
            border-color: #fff;
            color: #fff;
        }

        .disb-create .po-document-header {
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
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
            border-radius: 10px;
            height: 100%;
            padding: 14px;
            transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
        }

        .disb-create .stat-tile:hover {
            border-color: #c8d7e8;
            box-shadow: 0 12px 26px rgba(15, 23, 42, .08);
            transform: translateY(-1px);
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
            border-radius: 10px;
            max-height: 340px;
            overflow: auto;
        }

        .disb-create .payment-panel {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
        }

        .disb-create .payment-panel .form-control,
        .disb-create .payment-panel .form-select,
        .disb-create .payment-panel .input-group-text {
            border-color: #d9e2ef;
        }

        .disb-create .payment-note {
            background: #ecfeff;
            border: 1px solid #bae6fd;
            border-left: 3px solid #0f766e;
            border-radius: 10px;
            color: #164e63;
            padding: 10px 12px;
        }

        .disb-create .payment-lines {
            display: grid;
            gap: 12px;
        }

        .disb-create .payment-line-card {
            background: #fff;
            border: 1px solid #dbe6f2;
            border-radius: 12px;
            padding: 14px;
        }

        .disb-create .payment-line-card:hover {
            border-color: #b8cadf;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .08);
        }

        .disb-create .payment-line-number {
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

        .disb-create .payment-line-summary {
            background: #eefaf7;
            border: 1px solid #c9efe5;
            border-radius: 10px;
            color: #115e59;
            padding: 10px 12px;
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
                                                <th class="text-center" style="width: 130px;">Payment</th>
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
                                @error('payments')
                                    <div class="text-danger small mt-2">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div id="disbursementPanel" class="d-none mt-4">
                            <div class="payment-panel">
                                <div class="d-flex flex-column flex-lg-row justify-content-between gap-2 mb-3">
                                    <div>
                                        <div class="section-title mb-1">Disbursement Details</div>
                                        <div class="text-muted small">Add every PO item line being paid. Each row gets its own receipt reference and payment details.</div>
                                    </div>
                                    <div class="payment-note small">
                                        Payment is posted per PO item line. Use add/remove to build a batch without losing the line-level audit trail.
                                    </div>
                                </div>

                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                                    <div class="payment-line-summary small">
                                        Batch total: <strong><span id="paymentBatchCurrency">USD</span> <span id="paymentBatchTotal">0.00</span></strong>
                                        <span class="mx-2">|</span>
                                        PO balance after this batch: <strong><span id="paymentBatchRemaining">0.00</span></strong>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary" id="addPaymentLineBtn">
                                        <i class="feather-plus me-1"></i> Add Payment Item Line
                                    </button>
                                </div>

                                <div id="paymentLines" class="payment-lines"></div>
                                <div id="paymentLinesEmpty" class="alert alert-light border mb-0">
                                    Select <strong>Add Payment</strong> from an item line, or use the add button to start a payment row.
                                </div>

                                <div class="d-flex flex-wrap justify-content-end gap-2 mt-3">
                                    <a href="{{ route('procurement.disbursements.index') }}" class="btn btn-light">
                                        Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="feather-check-circle me-1"></i> Record Disbursements
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
            const oldPayments = @json(array_values(old('payments', [])));
            const oldItemEvidence = @json(old('item_evidence', []));
            const paymentMethods = @json($paymentMethods);
            const statusOptions = @json($statusOptions);
            const paidStatuses = @json(\App\Models\ProcurementPurchaseOrder::PAID_DISBURSEMENT_STATUSES);

            const select = document.getElementById('purchaseOrderSelect');
            const poPanel = document.getElementById('poDetailsPanel');
            const disbPanel = document.getElementById('disbursementPanel');
            const paymentLines = document.getElementById('paymentLines');
            const paymentLinesEmpty = document.getElementById('paymentLinesEmpty');
            const addPaymentLineBtn = document.getElementById('addPaymentLineBtn');
            const batchCurrency = document.getElementById('paymentBatchCurrency');
            const batchTotal = document.getElementById('paymentBatchTotal');
            const batchRemaining = document.getElementById('paymentBatchRemaining');
            const evidenceBank = document.getElementById('lineItemEvidenceBank');
            const evidenceModalEl = document.getElementById('lineItemEvidenceModal');
            const evidenceModalFields = document.getElementById('lineItemEvidenceModalFields');
            const evidenceModalTitle = document.getElementById('lineItemEvidenceTitle');
            const evidenceModalSubtitle = document.getElementById('lineItemEvidenceSubtitle');
            const evidenceModalDeliverable = document.getElementById('lineItemEvidenceDeliverable');
            const evidenceDoneBtn = document.getElementById('lineItemEvidenceDoneBtn');
            let activeEvidenceFieldset = null;
            let currentPo = null;
            let oldPaymentsApplied = false;

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

            const isPaidStatus = (value) => paidStatuses.includes(String(value || '').toLowerCase());

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

            function lineRemainingForPayment(item, po) {
                const lineRemaining = Number(item.remaining_amount ?? Math.max(Number(item.amount || 0) - Number(item.paid_amount || 0), 0));
                const poRemaining = Number(po.balance_amount ?? po.remaining ?? lineRemaining);

                return Math.max(Math.min(lineRemaining, poRemaining), 0);
            }

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

            function itemOptions(po, selected = '') {
                const items = Array.isArray(po?.line_items) ? po.line_items : [];
                return [''].concat(items).map((item) => {
                    if (item === '') {
                        return '<option value="">Select paid item line</option>';
                    }
                    const balance = lineRemainingForPayment(item, po);
                    const label = `${item.resource || item.category || 'Line item'} | Balance ${po.currency || ''} ${fmt(balance)}`;
                    return `<option value="${escapeHtml(item.id)}" ${String(selected) === String(item.id) ? ' selected' : ''}>${escapeHtml(label)}</option>`;
                }).join('');
            }

            function selectedPaymentTotalForItem(itemId, excludingCard = null) {
                return Array.from(paymentLines?.querySelectorAll('.payment-line-card') || [])
                    .filter((card) => card !== excludingCard
                        && card.querySelector('.payment-item-select')?.value === String(itemId)
                        && isPaidStatus(card.querySelector('.payment-status-select')?.value))
                    .reduce((total, card) => total + Number(card.querySelector('.payment-amount-input')?.value || 0), 0);
            }

            function updatePaymentCardLimit(card) {
                if (!currentPo || !card) return;

                const itemId = card.querySelector('.payment-item-select')?.value || '';
                const amountInput = card.querySelector('.payment-amount-input');
                const status = card.querySelector('.payment-status-select')?.value || 'completed';
                const hint = card.querySelector('.payment-line-limit');
                const item = (currentPo.line_items || []).find((candidate) => String(candidate.id) === String(itemId));
                const currency = currentPo.currency || 'USD';

                if (!item || !amountInput || !hint) {
                    if (amountInput) amountInput.removeAttribute('max');
                    if (hint) hint.textContent = `Select a line item (${currency})`;
                    return;
                }

                const lineBalance = lineRemainingForPayment(item, currentPo);
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
                const cards = Array.from(paymentLines?.querySelectorAll('.payment-line-card') || []);
                let total = 0;
                const currency = currentPo?.currency || 'USD';

                cards.forEach((card, index) => {
                    const number = card.querySelector('.payment-line-number');
                    if (number) number.textContent = index + 1;
                    if (isPaidStatus(card.querySelector('.payment-status-select')?.value)) {
                        total += Number(card.querySelector('.payment-amount-input')?.value || 0);
                    }
                });

                if (paymentLinesEmpty) paymentLinesEmpty.classList.toggle('d-none', cards.length > 0);
                if (batchCurrency) batchCurrency.textContent = currency;
                if (batchTotal) batchTotal.textContent = fmt(total);

                const poBalance = Number(currentPo?.balance_amount ?? currentPo?.remaining ?? 0);
                if (batchRemaining) batchRemaining.textContent = `${currency} ${fmt(Math.max(poBalance - total, 0))}`;
            }

            function addPaymentRow(defaults = {}) {
                if (!paymentLines || !currentPo) return;

                const index = `${Date.now()}-${Math.floor(Math.random() * 10000)}`;
                const selectedItemId = defaults.purchase_request_item_id || defaults.itemId || '';
                const selectedItem = (currentPo.line_items || []).find((item) => String(item.id) === String(selectedItemId));
                const suggestedAmount = defaults.amount || (selectedItem ? lineRemainingForPayment(selectedItem, currentPo).toFixed(2) : '');
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
                    <div class="row g-3">
                        <div class="col-lg-5">
                            <label class="form-label fw-semibold">Paid PO Line Item <span class="text-danger">*</span></label>
                            <select name="payments[${index}][purchase_request_item_id]" class="form-select payment-item-select" required>
                                ${itemOptions(currentPo, selectedItemId)}
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
                                <span class="input-group-text">${escapeHtml(currentPo.currency || 'USD')}</span>
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
                    </div>
                `;

                card.querySelector('.payment-line-remove')?.addEventListener('click', () => {
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

                paymentLines.appendChild(card);
                updatePaymentCardLimit(card);
                updatePaymentRows();
            }

            function resetPaymentLines() {
                if (paymentLines) paymentLines.innerHTML = '';
                updatePaymentRows();
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
                    resetPaymentLines();
                    return;
                }

                items.forEach((item) => {
                    const previous = evidenceDefaults(item);
                    const confirmed = evidenceIsConfirmed(previous.is_met);
                    const deliverableDate = previous.deliverable_date || '';
                    const lineAmount = Number(item.amount || 0);
                    const linePaid = Number(item.paid_amount || 0);
                    const lineRemaining = lineRemainingForPayment(item, po);
                    const isPayable = lineRemaining > 0;
                    ensureEvidenceFieldset(item);

                    const row = document.createElement('tr');
                    row.dataset.itemId = item.id;
                    row.innerHTML = `
                        <td class="text-center">
                            <button type="button" class="btn btn-sm ${isPayable ? 'btn-outline-success' : 'btn-light'} payment-add-from-line"
                                data-item-id="${escapeHtml(item.id)}" ${isPayable ? '' : ' disabled'}>
                                <i class="feather-plus me-1"></i> ${isPayable ? 'Add Payment' : 'Paid'}
                            </button>
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
                        <td class="text-end">
                            <div class="fw-semibold">${escapeHtml(po.currency || '')} ${fmt(lineAmount)}</div>
                            <div class="small text-muted">Paid ${fmt(linePaid)}</div>
                            <div class="small ${lineRemaining > 0 ? 'text-success' : 'text-muted'}">Balance ${fmt(lineRemaining)}</div>
                        </td>
                        <td class="text-center">
                            <input type="checkbox"
                                name="item_evidence[${item.id}][is_met]"
                                value="1"
                                class="line-item-evidence-check d-none"
                                data-item-id="${item.id}"
                                ${confirmed ? ' checked' : ''}>
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
                    row.querySelector('.payment-add-from-line')?.addEventListener('click', () => {
                        addPaymentRow({ itemId: item.id });
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

                resetPaymentLines();
                if (!oldPaymentsApplied && oldPayments.length > 0) {
                    oldPayments.forEach((payment) => addPaymentRow(payment));
                    oldPaymentsApplied = true;
                }
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
            }

            function update() {
                const id = select.value;
                const po = poData[id];
                currentPo = po || null;

                if (!po) {
                    poPanel?.classList.add('d-none');
                    disbPanel?.classList.add('d-none');
                    resetPaymentLines();
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

                const totalAmount = Number(po.amount || 0);
                const paidAmount = Number(po.paid_amount ?? po.paid ?? 0);
                const balanceAmount = Number(po.balance_amount ?? po.remaining ?? Math.max(totalAmount - paidAmount, 0));
                const currency = po.currency || 'USD';

                setText('po-amount', fmt(totalAmount));
                setText('po-paid', fmt(paidAmount));
                setText('po-remaining', fmt(balanceAmount));
                document.querySelectorAll('.po-currency-tag').forEach((element) => {
                    element.textContent = po.currency || '';
                });

                if (batchCurrency) batchCurrency.textContent = currency;

                renderDeliverables(po);
                renderLineItems(po);

                poPanel?.classList.remove('d-none');
                disbPanel?.classList.remove('d-none');
            }

            addPaymentLineBtn?.addEventListener('click', () => addPaymentRow());

            select.addEventListener('change', () => {
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
