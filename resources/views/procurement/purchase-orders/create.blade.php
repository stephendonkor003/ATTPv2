@extends('layouts.app')

@push('styles')
    <style>
        .po-create {
            --po-ink: #111827;
            --po-muted: #667085;
            --po-border: #dbe3ef;
            --po-panel: #ffffff;
            --po-page: #f4f7fb;
            --po-soft: #f8fafc;
            --po-soft-accent: #ecfdf5;
            --po-accent: #0f766e;
            --po-accent-dark: #0b5d55;
            --po-blue: #1d4ed8;
            background: linear-gradient(180deg, #f8fafc 0, #f4f7fb 260px, #f6f8fb 100%);
            min-height: calc(100vh - 80px);
            padding-bottom: 30px;
        }

        .po-create .page-band {
            background: #ffffff;
            border: 1px solid #dde5ef;
            border-left: 4px solid var(--po-accent);
            border-radius: 10px;
            padding: 18px 20px;
            box-shadow: 0 14px 36px rgba(15, 23, 42, .06);
        }

        .po-create .workspace {
            display: grid;
            grid-template-columns: minmax(260px, 320px) minmax(0, 1fr);
            gap: 16px;
        }

        .po-create .panel {
            background: var(--po-panel);
            border: 1px solid #dfe7f2;
            border-radius: 10px;
            box-shadow: 0 16px 38px rgba(15, 23, 42, .06);
            overflow: hidden;
        }

        .po-create .panel-header {
            padding: 14px 16px;
            border-bottom: 1px solid #e5ebf4;
            background: #fbfcfe;
        }

        .po-create .panel-body {
            padding: 16px;
        }

        .po-create .request-list {
            display: grid;
            gap: 8px;
            max-height: 500px;
            overflow: auto;
            padding-right: 4px;
        }

        .po-create .request-option {
            width: 100%;
            text-align: left;
            border: 1px solid var(--po-border);
            border-radius: 8px;
            background: #fff;
            padding: 11px 12px;
            transition: border-color .15s ease, background .15s ease, box-shadow .15s ease, transform .15s ease;
        }

        .po-create .request-option.active,
        .po-create .request-option:hover {
            border-color: var(--po-accent);
            background: var(--po-soft-accent);
            box-shadow: inset 3px 0 0 var(--po-accent);
        }

        .po-create .request-title {
            font-weight: 700;
            color: var(--po-ink);
            line-height: 1.3;
        }

        .po-create .request-meta {
            color: var(--po-muted);
            font-size: .78rem;
            margin-top: 4px;
        }

        .po-create .metric-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
        }

        .po-create .metric {
            background: #ffffff;
            border: 1px solid #e3eaf4;
            border-left: 3px solid var(--po-accent);
            border-radius: 8px;
            padding: 12px;
            min-height: 78px;
        }

        .po-create .metric-label {
            color: var(--po-muted);
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .po-create .metric-value {
            color: var(--po-ink);
            font-weight: 800;
            margin-top: 6px;
            font-size: 1rem;
        }

        .po-create .commitment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
        }

        .po-create .commitment-option {
            border: 1px solid var(--po-border);
            background: #fff;
            border-radius: 8px;
            padding: 12px;
            text-align: left;
            transition: border-color .15s ease, background .15s ease, box-shadow .15s ease;
        }

        .po-create .commitment-option.active,
        .po-create .commitment-option:hover {
            border-color: var(--po-accent);
            background: var(--po-soft-accent);
            box-shadow: 0 0 0 3px rgba(15, 118, 110, .08);
        }

        .po-create .section-title {
            color: var(--po-ink);
            font-weight: 800;
            margin-bottom: 10px;
        }

        .po-create .line-table {
            max-height: 320px;
            overflow: auto;
            border: 1px solid #e3eaf4;
            border-radius: 8px;
            background: #ffffff;
        }

        .po-create .line-table table {
            margin-bottom: 0;
        }

        .po-create .line-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #f8fafc;
            color: #475467;
            font-size: .74rem;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .po-create .line-table tbody tr:hover {
            background: #fbfdff;
        }

        .po-create .form-section {
            background: #ffffff;
            border: 1px solid #e4eaf3;
            border-radius: 10px;
            padding: 16px;
            margin-top: 16px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .035);
        }

        .po-create details.form-section {
            background: #fbfcfe;
        }

        .po-create details.form-section > summary {
            cursor: pointer;
            color: var(--po-ink);
            font-weight: 800;
            list-style: none;
        }

        .po-create details.form-section > summary::-webkit-details-marker {
            display: none;
        }

        .po-create details.form-section > summary::after {
            content: "+";
            float: right;
            color: var(--po-muted);
            font-weight: 700;
        }

        .po-create details.form-section[open] > summary::after {
            content: "-";
        }

        .po-create .line-item-summary {
            line-height: 1.35;
        }

        .po-create .line-item-evidence-check {
            width: 20px;
            height: 20px;
            accent-color: var(--po-accent);
            cursor: pointer;
        }

        .po-create .line-item-date-input {
            min-width: 145px;
        }

        .po-create .evidence-status {
            min-width: 108px;
            border-radius: 999px;
        }

        .po-create .evidence-status.bg-success-subtle {
            background: #dcfce7 !important;
            color: #166534 !important;
        }

        .po-create .evidence-status.bg-info-subtle {
            background: #e0f2fe !important;
            color: #075985 !important;
        }

        .po-create .evidence-field-bank {
            display: none;
        }

        .po-create .evidence-document-row + .evidence-document-row {
            margin-top: 8px;
        }

        .po-create .existing-evidence-documents {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .po-create .evidence-modal-summary {
            background: #ffffff;
            border: 1px solid #e3eaf4;
            border-left: 3px solid var(--po-accent);
            border-radius: 10px;
            padding: 12px 14px;
        }

        .po-create .evidence-document-row {
            display: grid;
            grid-template-columns: minmax(180px, 1fr) minmax(220px, 1.25fr) auto;
            gap: 8px;
            align-items: end;
            background: #ffffff;
            border: 1px solid #e3eaf4;
            border-radius: 10px;
            padding: 10px;
        }

        .po-create .evidence-document-label {
            color: var(--po-muted);
            display: block;
            font-size: .72rem;
            font-weight: 700;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .po-create .evidence-edit-btn {
            border-color: #9db5d3;
            color: #1f3f68;
            background: #ffffff;
        }

        .po-create .evidence-edit-btn:hover {
            border-color: var(--po-accent);
            color: var(--po-accent-dark);
            background: var(--po-soft-accent);
        }

        .po-create .btn-primary,
        .po-create .btn-outline-primary:hover {
            background: var(--po-accent);
            border-color: var(--po-accent);
        }

        .po-create .btn-outline-primary {
            color: var(--po-accent);
            border-color: var(--po-accent);
        }

        .po-create .btn-primary:hover {
            background: var(--po-accent-dark);
            border-color: var(--po-accent-dark);
        }

        .po-create .empty-state {
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 28px;
            text-align: center;
            color: var(--po-muted);
            background: #f8fafc;
        }

        .po-create .deliverable-card {
            border: 1.5px solid var(--po-border);
            border-radius: 10px;
            padding: 14px 16px;
            background: #fff;
            cursor: pointer;
            transition: border-color .15s, background .15s, box-shadow .15s;
            user-select: none;
            height: 100%;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }
        .po-create .deliverable-card:hover {
            border-color: var(--po-accent);
            background: var(--po-soft-accent);
        }
        .po-create .deliverable-card.checked {
            border-color: var(--po-accent);
            background: var(--po-soft-accent);
            box-shadow: 0 0 0 3px rgba(15, 118, 110, .1);
        }
        .po-create .deliverable-card input[type="checkbox"] {
            accent-color: var(--po-accent);
            width: 17px;
            height: 17px;
            flex-shrink: 0;
            margin-top: 2px;
            cursor: pointer;
        }
        .po-create .dlv-freq-badge {
            display: inline-block;
            font-size: .68rem;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: .03em;
        }
        .po-create .dlv-freq-one_time  { background:#f1f5f9; color:#475569; }
        .po-create .dlv-freq-daily     { background:#dbeafe; color:#1d4ed8; }
        .po-create .dlv-freq-weekly    { background:#ede9fe; color:#6d28d9; }
        .po-create .dlv-freq-monthly   { background:#d1fae5; color:#065f46; }
        .po-create .dlv-freq-quarterly { background:#fef3c7; color:#92400e; }
        .po-create .dlv-freq-yearly    { background:#fee2e2; color:#991b1b; }

        #lineItemEvidenceModal {
            z-index: 1095 !important;
            background: transparent;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
        }

        #lineItemEvidenceModal.show {
            background: rgba(16, 24, 40, .42) !important;
        }

        #lineItemEvidenceModal .modal-dialog {
            max-width: min(860px, calc(100vw - 28px));
            position: relative;
            z-index: 2;
            filter: none !important;
            -webkit-filter: none !important;
        }

        #lineItemEvidenceModal .modal-content {
            background: #ffffff;
            border: 0;
            border-radius: 14px;
            box-shadow: 0 28px 80px rgba(15, 23, 42, .28);
            overflow: hidden;
            filter: none !important;
            -webkit-filter: none !important;
            opacity: 1 !important;
        }

        #lineItemEvidenceModal .modal-header,
        #lineItemEvidenceModal .modal-footer {
            background: #ffffff;
            border-color: #e5ebf4;
        }

        #lineItemEvidenceModal .modal-header {
            padding: 18px 20px;
        }

        #lineItemEvidenceModal .modal-body {
            background: #f7f9fc;
            padding: 18px 20px;
        }

        body.line-evidence-modal-open {
            overflow: hidden;
        }

        body.line-evidence-modal-open .nxl-container.po-create {
            filter: none !important;
            -webkit-filter: none !important;
        }

        body.line-evidence-modal-open .modal-backdrop {
            display: none !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }

        body.modal-open .modal-backdrop {
            z-index: 1085 !important;
        }

        @media (max-width: 991.98px) {
            .po-create .workspace {
                grid-template-columns: 1fr;
            }

            .po-create .metric-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            .po-create .metric-grid {
                grid-template-columns: 1fr;
            }

            .po-create .evidence-document-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $isEdit = isset($purchaseOrder) && $purchaseOrder;
        $oldPurchaseRequestId = old('purchase_request_id', $isEdit ? (string) $purchaseOrder->purchase_request_id : null);
        $oldCommitmentId = old('budget_commitment_id', $isEdit ? (string) $purchaseOrder->budget_commitment_id : null);
        $selectedProcurementId = old('procurement_id', $isEdit ? (string) $purchaseOrder->procurement_id : null);
        $selectedVendorId = old('vendor_id', $isEdit ? (string) $purchaseOrder->vendor_id : null);
        $selectedStatus = old('status', $isEdit ? $purchaseOrder->status : 'draft');
        $selectedIncoterm = old('incoterm', $isEdit ? $purchaseOrder->incoterm : null);
        $selectedDeliverableIds = old('deliverable_ids', $isEdit ? $purchaseOrder->deliverables->pluck('id')->map(fn ($id) => (string) $id)->all() : []);
        $submittedItemEvidence = old('item_evidence');
        $lineItemEvidenceInput = is_array($submittedItemEvidence) ? $submittedItemEvidence : ($itemEvidenceDefaults ?? []);

        if (is_array($submittedItemEvidence) && !empty($itemEvidenceDefaults)) {
            foreach ($itemEvidenceDefaults as $itemId => $defaults) {
                if (isset($lineItemEvidenceInput[$itemId])) {
                    $lineItemEvidenceInput[$itemId]['existing_documents'] = $defaults['existing_documents'] ?? [];
                }
            }
        }
    @endphp

    <div class="nxl-container po-create">
        <div class="page-band mb-4 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <h4 class="fw-bold mb-1">{{ $isEdit ? 'Edit Purchase Order' : 'Create Purchase Order' }}</h4>
                <p class="text-muted mb-0">
                    {{ $isEdit ? 'Update purchase order details, deliverables, dates, and supporting evidence.' : 'Select an approved purchase request, confirm the funding year, then issue a standards-ready purchase order.' }}
                </p>
            </div>
            <a href="{{ $isEdit ? route('procurement.purchase-orders.show', $purchaseOrder) : route('procurement.purchase-orders.index') }}" class="btn btn-outline-secondary">
                <i class="feather-arrow-left me-1"></i> Back
            </a>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        @if ($purchaseRequests->isEmpty())
            <div class="alert alert-warning">
                {{ $isEdit ? 'The source purchase request for this purchase order could not be loaded for editing.' : 'No approved purchase requests with remaining commitment balance are available for purchase order creation.' }}
            </div>
        @else
            <form method="POST" action="{{ $isEdit ? route('procurement.purchase-orders.update', $purchaseOrder) : route('procurement.purchase-orders.store') }}" id="purchaseOrderForm" enctype="multipart/form-data">
                @csrf
                @if ($isEdit)
                    @method('PUT')
                @endif
                <input type="hidden" name="purchase_request_id" id="purchaseRequestIdInput" value="{{ $oldPurchaseRequestId }}">
                <input type="hidden" name="budget_commitment_id" id="budgetCommitmentIdInput" value="{{ $oldCommitmentId }}">

                <div class="workspace">
                    <aside class="panel">
                        <div class="panel-header">
                            <label class="form-label fw-semibold mb-2" for="purchaseRequestSearch">Purchase Request</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="feather-search"></i></span>
                                <input type="search" id="purchaseRequestSearch" class="form-control" placeholder="Search reference, program, project">
                            </div>
                            <div class="small text-muted mt-2">
                                <span id="requestResultCount">{{ $purchaseRequests->count() }}</span> available requests
                            </div>
                        </div>
                        <div class="panel-body">
                            <div id="purchaseRequestList" class="request-list"></div>
                        </div>
                    </aside>

                    <main class="panel">
                        <div class="panel-header d-flex flex-column flex-md-row justify-content-between gap-2">
                            <div>
                                <div class="small text-muted">Selected purchase request</div>
                                <h5 class="fw-bold mb-0" id="selectedRequestTitle">Select a purchase request</h5>
                            </div>
                            <span class="badge bg-success align-self-start" id="selectedRequestStatus">Approved</span>
                        </div>
                        <div class="panel-body">
                            <div id="requestEmptyState" class="empty-state">
                                {{ $isEdit ? 'Loading the purchase request linked to this purchase order.' : 'Use the search list to select an approved purchase request.' }}
                            </div>

                            <div id="requestDetails" class="d-none">
                                <div class="metric-grid mb-4">
                                    <div class="metric">
                                        <div class="metric-label">Total Request</div>
                                        <div class="metric-value" id="requestTotalAmount">-</div>
                                    </div>
                                    <div class="metric">
                                        <div class="metric-label">Available Balance</div>
                                        <div class="metric-value" id="requestRemainingAmount">-</div>
                                    </div>
                                    <div class="metric">
                                        <div class="metric-label">Start Year</div>
                                        <div class="metric-value" id="requestStartYear">-</div>
                                    </div>
                                    <div class="metric">
                                        <div class="metric-label">Delivery Date</div>
                                        <div class="metric-value" id="requestDeliveryDate">-</div>
                                    </div>
                                </div>

                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <div class="text-muted small">Program</div>
                                        <div class="fw-semibold" id="requestProgram">-</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="text-muted small">Governance Node</div>
                                        <div class="fw-semibold" id="requestGovernance">-</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-muted small">Project</div>
                                        <div class="fw-semibold" id="requestProject">-</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-muted small">Activity</div>
                                        <div class="fw-semibold" id="requestActivity">-</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-muted small">Sub-Activity</div>
                                        <div class="fw-semibold" id="requestSubActivity">-</div>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <div class="section-title">Funding Year</div>
                                    <div id="commitmentOptions" class="commitment-grid"></div>
                                    <div class="form-text">The selected year controls the approved commitment used by this purchase order.</div>
                                </div>

                                <div>
                                    <div class="section-title">Requested Line Items</div>
                                    <div class="line-table table-responsive">
                                        <table class="table table-sm align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 110px;" class="text-center">Deliverable Check</th>
                                                    <th>Requested Item</th>
                                                    <th>Linked Deliverable</th>
                                                    <th style="width: 170px;">Date</th>
                                                    <th class="text-end">Amount</th>
                                                    <th style="width: 150px;" class="text-center">Evidence</th>
                                                </tr>
                                            </thead>
                                            <tbody id="requestItemsBody"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </main>
                </div>

                <div class="panel mt-4">
                    <div class="panel-header">
                        <h5 class="fw-bold mb-0">Purchase Order Details</h5>
                    </div>
                    <div class="panel-body">
                        <div class="row g-3">
                            <div class="col-lg-8">
                                <label class="form-label fw-semibold">PO Title</label>
                                <input type="text" name="po_title" id="poTitleInput" class="form-control"
                                    value="{{ old('po_title', $isEdit ? $purchaseOrder->po_title : null) }}" maxlength="255">
                            </div>
                            <div class="col-lg-4">
                                <label class="form-label fw-semibold">Status</label>
                                <select name="status" class="form-select">
                                    <option value="draft" @selected($selectedStatus === 'draft')>Draft</option>
                                    <option value="issued" @selected($selectedStatus === 'issued')>Issued</option>
                                    <option value="closed" @selected($selectedStatus === 'closed')>Closed</option>
                                    <option value="cancelled" @selected($selectedStatus === 'cancelled')>Cancelled</option>
                                </select>
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label fw-semibold">Procurement</label>
                                <select name="procurement_id" id="procurementSelect" class="form-select">
                                    <option value="">Optional procurement link</option>
                                    @foreach ($procurements as $procurement)
                                        <option value="{{ $procurement->id }}" @selected($selectedProcurementId === (string) $procurement->id)>
                                            {{ $procurement->title }} ({{ $procurement->reference_no ?? 'N/A' }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label fw-semibold">Vendor / Supplier</label>
                                <select name="vendor_id" id="vendorSelect" class="form-select">
                                    <option value="">Optional vendor</option>
                                    @foreach ($vendors as $vendor)
                                        <option value="{{ $vendor->id }}" @selected($selectedVendorId === (string) $vendor->id)>
                                            {{ $vendor->name }} - {{ $vendor->email }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">PO Amount <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0.01" name="amount" id="amountInput"
                                    class="form-control" value="{{ old('amount', $isEdit ? $purchaseOrder->amount : null) }}" required>
                                <div class="form-text" id="amountHelp">Select a funding year to set the maximum amount.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Currency</label>
                                <input type="text" name="currency" id="currencyInput" class="form-control"
                                    value="{{ old('currency', $isEdit ? $purchaseOrder->currency : 'USD') }}" maxlength="10">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Issue Date</label>
                                <input type="date" name="issued_at" class="form-control"
                                    value="{{ old('issued_at', $isEdit ? $purchaseOrder->issued_at?->format('Y-m-d') : now()->toDateString()) }}">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Expected Delivery</label>
                                <input type="date" name="expected_delivery_date" id="expectedDeliveryInput" class="form-control"
                                    value="{{ old('expected_delivery_date', $isEdit ? $purchaseOrder->expected_delivery_date?->format('Y-m-d') : null) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Valid Until</label>
                                <input type="date" name="valid_until" class="form-control" value="{{ old('valid_until', $isEdit ? $purchaseOrder->valid_until?->format('Y-m-d') : null) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Incoterm</label>
                                <select name="incoterm" class="form-select">
                                    <option value="">Not applicable</option>
                                    @foreach (['EXW', 'FCA', 'CPT', 'CIP', 'DAP', 'DPU', 'DDP', 'FAS', 'FOB', 'CFR', 'CIF'] as $term)
                                        <option value="{{ $term }}" @selected($selectedIncoterm === $term)>{{ $term }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Supplier Reference</label>
                                <input type="text" name="supplier_reference" class="form-control"
                                    value="{{ old('supplier_reference', $isEdit ? $purchaseOrder->supplier_reference : null) }}" maxlength="255">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Contract Reference</label>
                                <input type="text" name="contract_reference" class="form-control"
                                    value="{{ old('contract_reference', $isEdit ? $purchaseOrder->contract_reference : null) }}" maxlength="255">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">
                                    {{ $isEdit ? 'Replace Supporting Documentation' : 'Supporting Documentation' }}
                                    @unless ($isEdit)
                                        <span class="text-danger">*</span>
                                    @endunless
                                </label>
                                <input type="file" name="supporting_document"
                                    class="form-control @error('supporting_document') is-invalid @enderror"
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip" {{ ! $isEdit ? 'required' : '' }}>
                                <div class="form-text">
                                    @if ($isEdit && $purchaseOrder->supporting_document_path)
                                        Current file: {{ $purchaseOrder->supporting_document_name ?? basename($purchaseOrder->supporting_document_path) }}. Upload a new file only when replacing it.
                                    @else
                                        Attach the signed contract, award memo, or other approval evidence. PDF, Office, image, or ZIP files up to 20 MB are accepted.
                                    @endif
                                </div>
                                @error('supporting_document')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Deliverables Section --}}
                        <details class="form-section" id="deliverableSection">
                            <summary>Additional Procurement Deliverables</summary>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <div class="small text-muted mt-1">
                                        Optional: link extra procurement deliverables beyond those already attached to the requested line items.
                                    </div>
                                </div>
                                <span id="dlvSelectedBadge" class="badge bg-primary" style="display:none;font-size:.8rem;padding:6px 10px;">
                                    0 selected
                                </span>
                            </div>

                            @error('deliverable_ids')
                                <div class="alert alert-warning py-2 mb-3">{{ $message }}</div>
                            @enderror

                            {{-- State: no procurement chosen yet --}}
                            <div id="dlvStateNone" class="empty-state">
                                <i class="feather-link" style="font-size:1.4rem;display:block;margin-bottom:8px;opacity:.4"></i>
                                Select a procurement above to load its deliverables here.
                            </div>

                            {{-- State: procurement chosen but no deliverables exist --}}
                            <div id="dlvStateEmpty" class="empty-state d-none">
                                <i class="feather-inbox" style="font-size:1.4rem;display:block;margin-bottom:8px;opacity:.4"></i>
                                No deliverables found for this procurement.
                                <div class="mt-2">
                                    <a id="dlvCreateLink" href="{{ route('procurement.deliverables.create') }}"
                                       target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="feather-plus me-1"></i> Create Deliverables
                                    </a>
                                    <span class="text-muted small ms-2">(opens in new tab — refresh after saving)</span>
                                </div>
                            </div>

                            {{-- State: deliverable cards grid --}}
                            <div id="dlvPickerList" class="row g-3 d-none"></div>

                            <div id="dlvCreateHint" class="form-text mt-2 d-none">
                                Missing a deliverable?
                                <a id="dlvCreateLinkHint" href="{{ route('procurement.deliverables.create') }}"
                                   target="_blank">Create more deliverables</a>
                                for this procurement, then refresh the page.
                            </div>
                        </details>

                        <div class="form-section">
                            <div class="section-title">Parties and Addresses</div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Buyer Contact</label>
                                    <input type="text" name="buyer_contact_name" class="form-control"
                                        value="{{ old('buyer_contact_name', $isEdit ? $purchaseOrder->buyer_contact_name : $buyerDefaults['name']) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Buyer Email</label>
                                    <input type="email" name="buyer_contact_email" class="form-control"
                                        value="{{ old('buyer_contact_email', $isEdit ? $purchaseOrder->buyer_contact_email : $buyerDefaults['email']) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Buyer Phone</label>
                                    <input type="text" name="buyer_contact_phone" class="form-control"
                                        value="{{ old('buyer_contact_phone', $isEdit ? $purchaseOrder->buyer_contact_phone : null) }}">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Supplier Contact</label>
                                    <input type="text" name="vendor_contact_name" id="vendorContactNameInput" class="form-control"
                                        value="{{ old('vendor_contact_name', $isEdit ? $purchaseOrder->vendor_contact_name : null) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Supplier Email</label>
                                    <input type="email" name="vendor_contact_email" id="vendorContactEmailInput" class="form-control"
                                        value="{{ old('vendor_contact_email', $isEdit ? $purchaseOrder->vendor_contact_email : null) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Supplier Phone</label>
                                    <input type="text" name="vendor_contact_phone" id="vendorContactPhoneInput" class="form-control"
                                        value="{{ old('vendor_contact_phone', $isEdit ? $purchaseOrder->vendor_contact_phone : null) }}">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Bill To <span class="text-danger">*</span></label>
                                    <textarea name="billing_address" class="form-control" rows="3" required>{{ old('billing_address', $isEdit ? $purchaseOrder->billing_address : null) }}</textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Ship To <span class="text-danger">*</span></label>
                                    <textarea name="shipping_address" class="form-control" rows="3" required>{{ old('shipping_address', $isEdit ? $purchaseOrder->shipping_address : null) }}</textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Delivery Location</label>
                                    <textarea name="delivery_location" id="deliveryLocationInput" class="form-control" rows="2">{{ old('delivery_location', $isEdit ? $purchaseOrder->delivery_location : null) }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="section-title">Commercial Terms</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Delivery Terms <span class="text-danger">*</span></label>
                                    <input type="text" name="delivery_terms" class="form-control"
                                        value="{{ old('delivery_terms', $isEdit ? $purchaseOrder->delivery_terms : 'Delivery in accordance with the agreed purchase request schedule') }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Payment Terms <span class="text-danger">*</span></label>
                                    <input type="text" name="payment_terms" class="form-control"
                                        value="{{ old('payment_terms', $isEdit ? $purchaseOrder->payment_terms : 'Payment after acceptance of goods/services and valid invoice') }}" required>
                                </div>
                                <div class="col-12">
                                    <details class="border rounded p-3">
                                        <summary class="fw-semibold">Optional Terms and Instructions</summary>
                                        <div class="row g-3 mt-1">
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">Warranty / Support</label>
                                                <textarea name="warranty_terms" class="form-control" rows="3">{{ old('warranty_terms', $isEdit ? $purchaseOrder->warranty_terms : null) }}</textarea>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">Inspection and Acceptance</label>
                                                <textarea name="inspection_requirements" class="form-control" rows="3">{{ old('inspection_requirements', $isEdit ? $purchaseOrder->inspection_requirements : 'Goods and services are subject to inspection and written acceptance by the authorized receiving officer.') }}</textarea>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">Special Instructions</label>
                                                <textarea name="special_instructions" class="form-control" rows="4">{{ old('special_instructions', $isEdit ? $purchaseOrder->special_instructions : null) }}</textarea>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">Terms and Conditions</label>
                                                <textarea name="terms_conditions" class="form-control" rows="4">{{ old('terms_conditions', $isEdit ? $purchaseOrder->terms_conditions : 'Supplier must comply with applicable procurement rules, tax obligations, anti-fraud requirements, confidentiality obligations, and delivery documentation standards.') }}</textarea>
                                            </div>
                                        </div>
                                    </details>
                                </div>
                            </div>
                        </div>

                        <div id="poFormWarning" class="alert alert-warning d-none mt-4"></div>

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="{{ $isEdit ? route('procurement.purchase-orders.show', $purchaseOrder) : route('procurement.purchase-orders.index') }}" class="btn btn-light">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather-save me-1"></i> {{ $isEdit ? 'Save Changes' : 'Create Purchase Order' }}
                            </button>
                        </div>
                    </div>
                </div>

                <div id="lineItemEvidenceBank" class="evidence-field-bank"></div>

                <div class="modal fade" id="lineItemEvidenceModal" tabindex="-1" aria-labelledby="lineItemEvidenceTitle" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <div>
                                    <h5 class="modal-title mb-1" id="lineItemEvidenceTitle">Deliverable Confirmation</h5>
                                    <div class="small text-muted" id="lineItemEvidenceSubtitle"></div>
                                </div>
                                <button type="button" class="btn-close line-item-evidence-close" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="evidence-modal-summary mb-3">
                                    <div class="small text-muted">Deliverable</div>
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
        document.addEventListener('DOMContentLoaded', function () {
            const purchaseRequests = @json($purchaseRequests);
            const procurements = @json($procurementOptions);
            const vendors = @json($vendorOptions);
            const deliverablesByProcurement = @json($deliverablesByProcurement);
            const oldPurchaseRequestId = @json($oldPurchaseRequestId);
            const oldCommitmentId = @json($oldCommitmentId);
            const oldAmount = @json(old('amount', $isEdit ? $purchaseOrder->amount : null));
            const oldDeliverableId = @json(old('deliverable_id'));
            const oldItemEvidence = @json($lineItemEvidenceInput);

            const list = document.getElementById('purchaseRequestList');
            const search = document.getElementById('purchaseRequestSearch');
            const resultCount = document.getElementById('requestResultCount');
            const requestInput = document.getElementById('purchaseRequestIdInput');
            const commitmentInput = document.getElementById('budgetCommitmentIdInput');
            const emptyState = document.getElementById('requestEmptyState');
            const details = document.getElementById('requestDetails');
            const warning = document.getElementById('poFormWarning');
            const amountInput = document.getElementById('amountInput');
            const amountHelp = document.getElementById('amountHelp');
            const currencyInput = document.getElementById('currencyInput');
            const poTitleInput = document.getElementById('poTitleInput');
            const expectedDeliveryInput = document.getElementById('expectedDeliveryInput');
            const deliveryLocationInput = document.getElementById('deliveryLocationInput');
            const procurementSelect = document.getElementById('procurementSelect');
            const vendorSelect = document.getElementById('vendorSelect');
            const evidenceBank = document.getElementById('lineItemEvidenceBank');
            const evidenceModalEl = document.getElementById('lineItemEvidenceModal');
            const evidenceModalFields = document.getElementById('lineItemEvidenceModalFields');
            const evidenceModalTitle = document.getElementById('lineItemEvidenceTitle');
            const evidenceModalSubtitle = document.getElementById('lineItemEvidenceSubtitle');
            const evidenceModalDeliverable = document.getElementById('lineItemEvidenceDeliverable');
            const evidenceDoneBtn = document.getElementById('lineItemEvidenceDoneBtn');
            const evidenceCloseBtns = evidenceModalEl
                ? evidenceModalEl.querySelectorAll('.line-item-evidence-close')
                : [];
            let activeEvidenceFieldset = null;

            const money = (currency, value) => `${currency || ''} ${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`.trim();
            const text = (value) => value || '-';
            const escapeHtml = (value) => String(value || '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            }[char]));

            function filteredRequests() {
                const term = (search?.value || '').trim().toLowerCase();
                if (!term) return purchaseRequests;
                return purchaseRequests.filter((request) => (request.search_text || '').toLowerCase().includes(term));
            }

            function renderRequestList() {
                const rows = filteredRequests();
                resultCount.textContent = rows.length;
                list.innerHTML = '';

                if (rows.length === 0) {
                    list.innerHTML = '<div class="empty-state">No purchase request matches your search.</div>';
                    return;
                }

                rows.forEach((request) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'request-option';
                    button.dataset.id = request.id;
                    button.innerHTML = `
                        <div class="request-title">${request.reference_no}</div>
                        <div class="request-meta">${request.program}</div>
                        <div class="request-meta">${request.currency} ${Number(request.remaining_amount || 0).toLocaleString()} available</div>
                    `;
                    button.addEventListener('click', () => selectPurchaseRequest(request.id));
                    list.appendChild(button);
                });

                markActiveRequest();
            }

            function markActiveRequest() {
                const selectedId = requestInput.value;
                document.querySelectorAll('.request-option').forEach((button) => {
                    button.classList.toggle('active', button.dataset.id === selectedId);
                });
            }

            function selectPurchaseRequest(id) {
                const request = purchaseRequests.find((item) => item.id === id);
                if (!request) return;

                requestInput.value = request.id;
                markActiveRequest();
                emptyState.classList.add('d-none');
                details.classList.remove('d-none');

                document.getElementById('selectedRequestTitle').textContent = `${request.reference_no} - ${request.program}`;
                document.getElementById('selectedRequestStatus').textContent = request.status || 'Approved';
                document.getElementById('requestTotalAmount').textContent = money(request.currency, request.total_amount);
                document.getElementById('requestRemainingAmount').textContent = money(request.currency, request.remaining_amount);
                document.getElementById('requestStartYear').textContent = text(request.start_year);
                document.getElementById('requestDeliveryDate').textContent = text(request.delivery_date);
                document.getElementById('requestProgram').textContent = text(request.program);
                document.getElementById('requestGovernance').textContent = text(request.governance_node);
                document.getElementById('requestProject').textContent = text(request.project);
                document.getElementById('requestActivity').textContent = text(request.activity);
                document.getElementById('requestSubActivity').textContent = text(request.sub_activity);

                const nextAutoTitle = `Purchase Order for ${request.reference_no}`;
                if (!poTitleInput.value || poTitleInput.dataset.autoTitle === poTitleInput.value) {
                    poTitleInput.value = nextAutoTitle;
                    poTitleInput.dataset.autoTitle = nextAutoTitle;
                }
                if (!currencyInput.value) {
                    currencyInput.value = request.currency || 'USD';
                }
                if (!expectedDeliveryInput.value && request.delivery_date) {
                    expectedDeliveryInput.value = request.delivery_date;
                }
                if (!deliveryLocationInput.value) {
                    deliveryLocationInput.value = request.governance_node || '';
                }

                if (!request.commitments.some((commitment) => commitment.id === commitmentInput.value)) {
                    commitmentInput.value = '';
                }

                renderCommitments(request);
                renderItems(request);
            }

            function renderCommitments(request) {
                const wrap = document.getElementById('commitmentOptions');
                wrap.innerHTML = '';

                request.commitments.forEach((commitment, index) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'commitment-option';
                    button.dataset.id = commitment.id;
                    button.innerHTML = `
                        <div class="fw-bold">Year ${commitment.year || 'N/A'}</div>
                        <div class="small text-muted">${commitment.project || ''}</div>
                        <div class="mt-2">${money(commitment.currency, commitment.remaining_amount)} available</div>
                    `;
                    button.addEventListener('click', () => selectCommitment(request, commitment.id));
                    wrap.appendChild(button);

                    const shouldSelect = oldCommitmentId
                        ? commitment.id === oldCommitmentId
                        : index === 0;

                    if (shouldSelect && !commitmentInput.value) {
                        selectCommitment(request, commitment.id);
                    }
                });

                if (commitmentInput.value) {
                    selectCommitment(request, commitmentInput.value, true);
                }
            }

            function selectCommitment(request, commitmentId, preserveAmount = false) {
                const commitment = request.commitments.find((item) => item.id === commitmentId);
                if (!commitment) return;

                commitmentInput.value = commitment.id;
                document.querySelectorAll('.commitment-option').forEach((button) => {
                    button.classList.toggle('active', button.dataset.id === commitment.id);
                });

                amountInput.max = commitment.remaining_amount;
                amountHelp.textContent = `Maximum for selected year: ${money(commitment.currency, commitment.remaining_amount)}`;
                currencyInput.value = commitment.currency || request.currency || 'USD';

                if (!preserveAmount && !oldAmount) {
                    amountInput.value = commitment.remaining_amount;
                }
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

                row.querySelector('.evidence-document-input').addEventListener('change', () => {
                    updateEvidenceRowState(itemId);
                });
                row.querySelector('.evidence-document-remove').addEventListener('click', () => {
                    row.remove();
                    updateEvidenceRowState(itemId);
                });

                list.appendChild(row);
            }

            function ensureEvidenceFieldset(item) {
                if (!item?.id || !evidenceBank) return null;

                let fieldset = document.querySelector(`.line-item-evidence-fieldset[data-item-id="${item.id}"]`);
                if (fieldset) return fieldset;

                const previous = oldItemEvidence[item.id] || {};
                const isConfirmed = previous.is_met === '1' || previous.is_met === 1 || previous.is_met === true;
                const previousDocumentNames = Array.isArray(previous.document_names)
                    ? previous.document_names.filter((name) => String(name || '').trim() !== '')
                    : [];
                const previousExistingDocuments = Array.isArray(previous.existing_documents)
                    ? previous.existing_documents
                    : [];
                const existingDocumentHtml = previousExistingDocuments.length > 0
                    ? `
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Existing Documents</label>
                            <div class="existing-evidence-documents">
                                ${previousExistingDocuments.map((document) => `
                                    <span class="badge bg-light text-dark border existing-evidence-document">
                                        ${escapeHtml(document.display_name || document.name || 'Document')}
                                    </span>
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
                            name="item_evidence[${item.id}][is_met]"
                            value="1"${isConfirmed ? ' checked' : ''}>
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

                fieldset.querySelector('.modal-evidence-met').addEventListener('change', (event) => {
                    const rowCheck = document.querySelector(`.line-item-evidence-check[data-item-id="${item.id}"]`);
                    if (rowCheck) {
                        rowCheck.checked = event.target.checked;
                    }
                    updateEvidenceRowState(item.id);
                });
                fieldset.querySelector('.modal-evidence-notes').addEventListener('input', () => {
                    updateEvidenceRowState(item.id);
                });
                fieldset.querySelector('.evidence-document-add').addEventListener('click', () => {
                    addEvidenceDocumentRow(fieldset, item.id);
                });

                evidenceBank.appendChild(fieldset);
                if (previousDocumentNames.length > 0) {
                    previousDocumentNames.forEach((name) => addEvidenceDocumentRow(fieldset, item.id, name));
                } else {
                    addEvidenceDocumentRow(fieldset, item.id);
                }
                return fieldset;
            }

            function updateEvidenceRowState(itemId) {
                const fieldset = document.querySelector(`.line-item-evidence-fieldset[data-item-id="${itemId}"]`);
                if (!fieldset) return;

                const confirmed = fieldset.querySelector('.modal-evidence-met')?.checked || false;
                const notes = (fieldset.querySelector('.modal-evidence-notes')?.value || '').trim();
                const newFileCount = Array.from(fieldset.querySelectorAll('.evidence-document-input'))
                    .reduce((total, input) => total + (input.files ? input.files.length : 0), 0);
                const fileCount = newFileCount + fieldset.querySelectorAll('.existing-evidence-document').length;
                const deliverableDate = (document.querySelector(`.line-item-date-input[data-item-id="${itemId}"]`)?.value || '').trim();

                const rowCheck = document.querySelector(`.line-item-evidence-check[data-item-id="${itemId}"]`);
                if (rowCheck) {
                    rowCheck.checked = confirmed;
                }

                const status = document.querySelector(`.evidence-status[data-item-id="${itemId}"]`);
                if (status) {
                    if (confirmed) {
                        status.className = 'badge bg-success-subtle text-success evidence-status';
                        status.textContent = fileCount > 0 ? `Confirmed (${fileCount})` : 'Confirmed';
                    } else if (fileCount > 0 || notes !== '') {
                        status.className = 'badge bg-info-subtle text-info evidence-status';
                        status.textContent = fileCount > 0 ? `Docs (${fileCount})` : 'Notes added';
                    } else if (deliverableDate !== '') {
                        status.className = 'badge bg-info-subtle text-info evidence-status';
                        status.textContent = 'Date set';
                    } else {
                        status.className = 'badge bg-light text-muted border evidence-status';
                        status.textContent = 'Pending';
                    }
                }
            }

            function showEvidenceModal() {
                if (!evidenceModalEl) return;

                document.querySelectorAll('.modal-backdrop').forEach((backdrop) => backdrop.remove());
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

            function openEvidenceModal(item, request) {
                const fieldset = ensureEvidenceFieldset(item);
                if (!fieldset || !evidenceModalEl || !evidenceModalFields) return;

                returnActiveEvidenceFieldset();
                activeEvidenceFieldset = fieldset;
                evidenceModalFields.appendChild(fieldset);

                if (evidenceModalTitle) {
                    evidenceModalTitle.textContent = item.resource || item.category || 'Requested Line Item';
                }
                if (evidenceModalSubtitle) {
                    evidenceModalSubtitle.textContent = `${item.category || 'N/A'} | ${money(request.currency, item.amount)}`;
                }
                if (evidenceModalDeliverable) {
                    evidenceModalDeliverable.textContent = item.deliverable_title || 'No deliverable linked';
                }

                showEvidenceModal();
            }

            function renderItems(request) {
                const body = document.getElementById('requestItemsBody');
                returnActiveEvidenceFieldset();
                if (evidenceBank) {
                    evidenceBank.innerHTML = '';
                }
                body.innerHTML = '';

                if (!request.items || request.items.length === 0) {
                    body.innerHTML = '<tr><td colspan="6" class="text-muted text-center">No line items found.</td></tr>';
                    return;
                }

                request.items.forEach((item) => {
                    ensureEvidenceFieldset(item);
                    const previous = oldItemEvidence[item.id] || {};
                    const deliverableDate = previous.deliverable_date || '';
                    const row = document.createElement('tr');
                    row.dataset.itemId = item.id;
                    row.innerHTML = `
                        <td class="text-center">
                            <input type="checkbox"
                                class="line-item-evidence-check"
                                data-item-id="${item.id}"
                                aria-label="Confirm deliverable for ${escapeHtml(item.resource || item.category || 'line item')}">
                        </td>
                        <td>
                            <div class="line-item-summary fw-semibold">${escapeHtml(item.resource || 'N/A')}</div>
                            <small class="text-muted">${escapeHtml(item.category || 'N/A')}</small>
                            <div class="small text-muted">${escapeHtml(item.description || '')}</div>
                            <div class="small text-muted">${escapeHtml(item.budget_code || '')}</div>
                        </td>
                        <td>${escapeHtml(item.deliverable_title || 'N/A')}</td>
                        <td>
                            <input type="date"
                                name="item_evidence[${item.id}][deliverable_date]"
                                class="form-control form-control-sm line-item-date-input"
                                data-item-id="${item.id}"
                                value="${escapeHtml(deliverableDate)}"
                                aria-label="Deliverable date for ${escapeHtml(item.resource || item.category || 'line item')}">
                        </td>
                        <td class="text-end fw-semibold">${money(request.currency, item.amount)}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-primary evidence-edit-btn" data-item-id="${item.id}">
                                <i class="feather-upload-cloud me-1"></i> Evidence
                            </button>
                            <div class="mt-1">
                                <span class="badge bg-light text-muted border evidence-status" data-item-id="${item.id}">Pending</span>
                            </div>
                        </td>
                    `;
                    row.querySelector('.line-item-evidence-check').addEventListener('change', (event) => {
                        const fieldset = ensureEvidenceFieldset(item);
                        const modalCheck = fieldset?.querySelector('.modal-evidence-met');
                        if (modalCheck) {
                            modalCheck.checked = event.target.checked;
                        }
                        updateEvidenceRowState(item.id);
                    });
                    row.querySelector('.line-item-date-input').addEventListener('change', () => {
                        updateEvidenceRowState(item.id);
                    });
                    row.querySelector('.evidence-edit-btn').addEventListener('click', () => {
                        openEvidenceModal(item, request);
                    });
                    body.appendChild(row);
                    updateEvidenceRowState(item.id);
                });
            }

            function fillVendorContacts(vendorId, onlyBlank = true) {
                const vendor = vendors.find((item) => item.id === vendorId);
                if (!vendor) return;

                const fields = [
                    ['vendorContactNameInput', vendor.name],
                    ['vendorContactEmailInput', vendor.email],
                    ['vendorContactPhoneInput', vendor.phone],
                ];

                fields.forEach(([id, value]) => {
                    const field = document.getElementById(id);
                    if (field && (!onlyBlank || !field.value)) {
                        field.value = value || '';
                    }
                });
            }

            const dlvStateNone      = document.getElementById('dlvStateNone');
            const dlvStateEmpty     = document.getElementById('dlvStateEmpty');
            const dlvPickerList     = document.getElementById('dlvPickerList');
            const dlvCreateLink     = document.getElementById('dlvCreateLink');
            const dlvCreateLinkHint = document.getElementById('dlvCreateLinkHint');
            const dlvCreateHint     = document.getElementById('dlvCreateHint');
            const dlvSelectedBadge  = document.getElementById('dlvSelectedBadge');
            const oldDeliverableIds = @json($selectedDeliverableIds);

            const FREQ_LABELS = {
                one_time: 'One-time', daily: 'Daily', weekly: 'Weekly',
                monthly: 'Monthly', quarterly: 'Quarterly', yearly: 'Yearly',
            };

            function setDlvCreateHref(procurementId) {
                [dlvCreateLink, dlvCreateLinkHint].forEach(el => {
                    if (!el) return;
                    try {
                        const url = new URL(el.href, location.origin);
                        if (procurementId) url.searchParams.set('procurement_id', procurementId);
                        else url.searchParams.delete('procurement_id');
                        el.href = url.toString();
                    } catch (e) {}
                });
            }

            function updateSelectedBadge() {
                const n = dlvPickerList ? dlvPickerList.querySelectorAll('input[type="checkbox"]:checked').length : 0;
                if (dlvSelectedBadge) {
                    dlvSelectedBadge.style.display = n > 0 ? '' : 'none';
                    dlvSelectedBadge.textContent   = n + (n === 1 ? ' selected' : ' selected');
                }
            }

            function updateDeliverableSelect(procurementId) {
                const deliverables = (procurementId && deliverablesByProcurement[procurementId]) || [];
                setDlvCreateHref(procurementId);

                if (!procurementId) {
                    dlvStateNone  && dlvStateNone.classList.remove('d-none');
                    dlvStateEmpty && dlvStateEmpty.classList.add('d-none');
                    dlvPickerList && dlvPickerList.classList.add('d-none');
                    dlvCreateHint && dlvCreateHint.classList.add('d-none');
                    if (dlvPickerList) dlvPickerList.innerHTML = '';
                    if (dlvSelectedBadge) dlvSelectedBadge.style.display = 'none';
                    return;
                }

                dlvStateNone && dlvStateNone.classList.add('d-none');

                if (deliverables.length === 0) {
                    dlvStateEmpty && dlvStateEmpty.classList.remove('d-none');
                    dlvPickerList && dlvPickerList.classList.add('d-none');
                    dlvCreateHint && dlvCreateHint.classList.add('d-none');
                    if (dlvPickerList) dlvPickerList.innerHTML = '';
                    if (dlvSelectedBadge) dlvSelectedBadge.style.display = 'none';
                    return;
                }

                dlvStateEmpty && dlvStateEmpty.classList.add('d-none');
                dlvPickerList && dlvPickerList.classList.remove('d-none');
                dlvCreateHint && dlvCreateHint.classList.remove('d-none');
                dlvPickerList.innerHTML = '';

                deliverables.forEach((d) => {
                    const isChecked = oldDeliverableIds.includes(d.id);
                    const freq      = d.frequency || 'one_time';
                    const freqLabel = FREQ_LABELS[freq] || freq;
                    const typeLabel = d.type === 'milestone'
                        ? '<span class="badge bg-warning text-dark" style="font-size:.68rem">Milestone</span>'
                        : '<span class="badge bg-light text-dark border" style="font-size:.68rem">Deliverable</span>';
                    const timeline  = (d.start && d.end)
                        ? `<div class="text-muted mt-1" style="font-size:.76rem"><i class="feather-calendar" style="font-size:.7rem"></i> ${d.start} &rarr; ${d.end}</div>`
                        : '';
                    const amtHtml   = (d.amount > 0)
                        ? `<div class="text-muted" style="font-size:.76rem">${d.currency || ''} ${Number(d.amount).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</div>`
                        : '';
                    const descHtml  = d.description
                        ? `<div class="text-muted mt-2" style="font-size:.78rem;line-height:1.4;max-height:2.8em;overflow:hidden">${d.description}</div>`
                        : '';

                    const col  = document.createElement('div');
                    col.className = 'col-md-4 col-lg-3';

                    const card = document.createElement('label');
                    card.className = 'deliverable-card' + (isChecked ? ' checked' : '');
                    card.innerHTML = `
                        <input type="checkbox" name="deliverable_ids[]" value="${d.id}"${isChecked ? ' checked' : ''}>
                        <div style="flex:1;min-width:0">
                            <div class="fw-semibold" style="font-size:.88rem;line-height:1.3">${d.title}</div>
                            <div class="d-flex flex-wrap gap-1 mt-1 align-items-center">
                                ${typeLabel}
                                <span class="dlv-freq-badge dlv-freq-${freq}">${freqLabel}</span>
                            </div>
                            ${timeline}${amtHtml}${descHtml}
                        </div>
                    `;

                    card.querySelector('input').addEventListener('change', function () {
                        card.classList.toggle('checked', this.checked);
                        updateSelectedBadge();
                    });

                    col.appendChild(card);
                    dlvPickerList.appendChild(col);
                });

                updateSelectedBadge();
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
            }

            search?.addEventListener('input', renderRequestList);
            evidenceDoneBtn?.addEventListener('click', closeEvidenceModal);
            evidenceCloseBtns.forEach((button) => button.addEventListener('click', closeEvidenceModal));
            evidenceModalEl?.addEventListener('mousedown', (event) => {
                if (event.target === evidenceModalEl) {
                    closeEvidenceModal();
                }
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && evidenceModalEl?.classList.contains('show')) {
                    closeEvidenceModal();
                }
            });
            vendorSelect?.addEventListener('change', () => fillVendorContacts(vendorSelect.value, true));
            procurementSelect?.addEventListener('change', () => {
                const procurement = procurements.find((item) => item.id === procurementSelect.value);
                if (procurement?.awarded_vendor_id && !vendorSelect.value) {
                    vendorSelect.value = procurement.awarded_vendor_id;
                    fillVendorContacts(procurement.awarded_vendor_id, true);
                }
                updateDeliverableSelect(procurementSelect.value);
            });

            // Restore deliverable dropdown on validation failure
            if (procurementSelect?.value) {
                updateDeliverableSelect(procurementSelect.value);
            }

            document.getElementById('purchaseOrderForm')?.addEventListener('submit', function (event) {
                warning.classList.add('d-none');
                warning.textContent = '';

                if (!requestInput.value || !commitmentInput.value) {
                    event.preventDefault();
                    warning.textContent = 'Select a purchase request and funding year before creating the purchase order.';
                    warning.classList.remove('d-none');
                }
            });

            renderRequestList();

            const initialId = oldPurchaseRequestId || purchaseRequests[0]?.id;
            if (initialId) {
                selectPurchaseRequest(initialId);
            }

            if (vendorSelect?.value) {
                fillVendorContacts(vendorSelect.value, true);
            }
        });
    </script>
@endpush
