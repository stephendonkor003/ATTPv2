@extends('layouts.app')

@push('styles')
    <style>
        .po-create {
            --po-ink: #172033;
            --po-muted: #64748b;
            --po-border: #dbe3ef;
            --po-panel: #ffffff;
            --po-soft: #f6f8fb;
            --po-accent: #0f766e;
            --po-blue: #2563eb;
        }

        .po-create .page-band {
            background: #fff;
            border: 1px solid var(--po-border);
            border-radius: 8px;
            padding: 18px 20px;
        }

        .po-create .workspace {
            display: grid;
            grid-template-columns: minmax(280px, 360px) minmax(0, 1fr);
            gap: 18px;
        }

        .po-create .panel {
            background: var(--po-panel);
            border: 1px solid var(--po-border);
            border-radius: 8px;
        }

        .po-create .panel-header {
            padding: 14px 16px;
            border-bottom: 1px solid var(--po-border);
        }

        .po-create .panel-body {
            padding: 16px;
        }

        .po-create .request-list {
            display: grid;
            gap: 8px;
            max-height: 580px;
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
            transition: border-color .15s ease, background .15s ease;
        }

        .po-create .request-option.active,
        .po-create .request-option:hover {
            border-color: var(--po-accent);
            background: #f0fdfa;
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
            background: var(--po-soft);
            border: 1px solid #e8eef7;
            border-radius: 8px;
            padding: 12px;
            min-height: 82px;
        }

        .po-create .metric-label {
            color: var(--po-muted);
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .04em;
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
        }

        .po-create .commitment-option.active,
        .po-create .commitment-option:hover {
            border-color: var(--po-blue);
            background: #eff6ff;
        }

        .po-create .section-title {
            color: var(--po-ink);
            font-weight: 800;
            margin-bottom: 10px;
        }

        .po-create .line-table {
            max-height: 280px;
            overflow: auto;
        }

        .po-create .form-section {
            border-top: 1px solid var(--po-border);
            padding-top: 18px;
            margin-top: 18px;
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
            border-color: #93c5fd;
            background: #f8fbff;
        }
        .po-create .deliverable-card.checked {
            border-color: var(--po-blue);
            background: #eff6ff;
            box-shadow: 0 0 0 3px rgba(37,99,235,.1);
        }
        .po-create .deliverable-card input[type="checkbox"] {
            accent-color: var(--po-blue);
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
        }
    </style>
@endpush

@section('content')
    @php
        $oldPurchaseRequestId = old('purchase_request_id');
        $oldCommitmentId = old('budget_commitment_id');
    @endphp

    <div class="nxl-container po-create">
        <div class="page-band mb-4 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <h4 class="fw-bold mb-1">Create Purchase Order</h4>
                <p class="text-muted mb-0">Select an approved purchase request, confirm the funding year, then issue a standards-ready purchase order.</p>
            </div>
            <a href="{{ route('procurement.purchase-orders.index') }}" class="btn btn-outline-secondary">
                <i class="feather-arrow-left me-1"></i> Back
            </a>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        @if ($purchaseRequests->isEmpty())
            <div class="alert alert-warning">
                No approved purchase requests with remaining commitment balance are available for purchase order creation.
            </div>
        @else
            <form method="POST" action="{{ route('procurement.purchase-orders.store') }}" id="purchaseOrderForm" enctype="multipart/form-data">
                @csrf
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
                                Use the search list to select an approved purchase request.
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
                                                    <th>Category</th>
                                                    <th>Item</th>
                                                    <th>Description</th>
                                                    <th class="text-end">Amount</th>
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
                                    value="{{ old('po_title') }}" maxlength="255">
                            </div>
                            <div class="col-lg-4">
                                <label class="form-label fw-semibold">Status</label>
                                <select name="status" class="form-select">
                                    <option value="draft" @selected(old('status', 'draft') === 'draft')>Draft</option>
                                    <option value="issued" @selected(old('status') === 'issued')>Issued</option>
                                </select>
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label fw-semibold">Procurement</label>
                                <select name="procurement_id" id="procurementSelect" class="form-select">
                                    <option value="">Optional procurement link</option>
                                    @foreach ($procurements as $procurement)
                                        <option value="{{ $procurement->id }}" @selected(old('procurement_id') === (string) $procurement->id)>
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
                                        <option value="{{ $vendor->id }}" @selected(old('vendor_id') === $vendor->id)>
                                            {{ $vendor->name }} - {{ $vendor->email }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">PO Amount <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0.01" name="amount" id="amountInput"
                                    class="form-control" value="{{ old('amount') }}" required>
                                <div class="form-text" id="amountHelp">Select a funding year to set the maximum amount.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Currency</label>
                                <input type="text" name="currency" id="currencyInput" class="form-control"
                                    value="{{ old('currency', 'USD') }}" maxlength="10">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Issue Date</label>
                                <input type="date" name="issued_at" class="form-control"
                                    value="{{ old('issued_at', now()->toDateString()) }}">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Expected Delivery</label>
                                <input type="date" name="expected_delivery_date" id="expectedDeliveryInput" class="form-control"
                                    value="{{ old('expected_delivery_date') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Valid Until</label>
                                <input type="date" name="valid_until" class="form-control" value="{{ old('valid_until') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Incoterm</label>
                                <select name="incoterm" class="form-select">
                                    <option value="">Not applicable</option>
                                    @foreach (['EXW', 'FCA', 'CPT', 'CIP', 'DAP', 'DPU', 'DDP', 'FAS', 'FOB', 'CFR', 'CIF'] as $term)
                                        <option value="{{ $term }}" @selected(old('incoterm') === $term)>{{ $term }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Supplier Reference</label>
                                <input type="text" name="supplier_reference" class="form-control"
                                    value="{{ old('supplier_reference') }}" maxlength="255">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Contract Reference</label>
                                <input type="text" name="contract_reference" class="form-control"
                                    value="{{ old('contract_reference') }}" maxlength="255">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">
                                    Supporting Documentation <span class="text-danger">*</span>
                                </label>
                                <input type="file" name="supporting_document"
                                    class="form-control @error('supporting_document') is-invalid @enderror"
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip" required>
                                <div class="form-text">Attach the signed contract, award memo, or other approval evidence. PDF, Office, image, or ZIP files up to 20 MB are accepted.</div>
                                @error('supporting_document')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- ── Deliverables Section ─────────────────────── --}}
                        <div class="form-section" id="deliverableSection">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <div class="section-title mb-0">Deliverables</div>
                                    <div class="small text-muted mt-1">
                                        Link the specific deliverables this purchase order covers.
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
                        </div>

                        <div class="form-section">
                            <div class="section-title">Parties and Addresses</div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Buyer Contact</label>
                                    <input type="text" name="buyer_contact_name" class="form-control"
                                        value="{{ old('buyer_contact_name', $buyerDefaults['name']) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Buyer Email</label>
                                    <input type="email" name="buyer_contact_email" class="form-control"
                                        value="{{ old('buyer_contact_email', $buyerDefaults['email']) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Buyer Phone</label>
                                    <input type="text" name="buyer_contact_phone" class="form-control"
                                        value="{{ old('buyer_contact_phone') }}">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Supplier Contact</label>
                                    <input type="text" name="vendor_contact_name" id="vendorContactNameInput" class="form-control"
                                        value="{{ old('vendor_contact_name') }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Supplier Email</label>
                                    <input type="email" name="vendor_contact_email" id="vendorContactEmailInput" class="form-control"
                                        value="{{ old('vendor_contact_email') }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Supplier Phone</label>
                                    <input type="text" name="vendor_contact_phone" id="vendorContactPhoneInput" class="form-control"
                                        value="{{ old('vendor_contact_phone') }}">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Bill To <span class="text-danger">*</span></label>
                                    <textarea name="billing_address" class="form-control" rows="3" required>{{ old('billing_address') }}</textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Ship To <span class="text-danger">*</span></label>
                                    <textarea name="shipping_address" class="form-control" rows="3" required>{{ old('shipping_address') }}</textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Delivery Location</label>
                                    <textarea name="delivery_location" id="deliveryLocationInput" class="form-control" rows="2">{{ old('delivery_location') }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="section-title">Commercial Terms</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Delivery Terms <span class="text-danger">*</span></label>
                                    <input type="text" name="delivery_terms" class="form-control"
                                        value="{{ old('delivery_terms', 'Delivery in accordance with the agreed purchase request schedule') }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Payment Terms <span class="text-danger">*</span></label>
                                    <input type="text" name="payment_terms" class="form-control"
                                        value="{{ old('payment_terms', 'Payment after acceptance of goods/services and valid invoice') }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Warranty / Support</label>
                                    <textarea name="warranty_terms" class="form-control" rows="3">{{ old('warranty_terms') }}</textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Inspection and Acceptance</label>
                                    <textarea name="inspection_requirements" class="form-control" rows="3">{{ old('inspection_requirements', 'Goods and services are subject to inspection and written acceptance by the authorized receiving officer.') }}</textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Special Instructions</label>
                                    <textarea name="special_instructions" class="form-control" rows="4">{{ old('special_instructions') }}</textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Terms and Conditions</label>
                                    <textarea name="terms_conditions" class="form-control" rows="4">{{ old('terms_conditions', 'Supplier must comply with applicable procurement rules, tax obligations, anti-fraud requirements, confidentiality obligations, and delivery documentation standards.') }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div id="poFormWarning" class="alert alert-warning d-none mt-4"></div>

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="{{ route('procurement.purchase-orders.index') }}" class="btn btn-light">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather-save me-1"></i> Create Purchase Order
                            </button>
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
            const oldAmount = @json(old('amount'));
            const oldDeliverableId = @json(old('deliverable_id'));

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

            const money = (currency, value) => `${currency || ''} ${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`.trim();
            const text = (value) => value || '-';

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

            function renderItems(request) {
                const body = document.getElementById('requestItemsBody');
                body.innerHTML = '';

                if (!request.items || request.items.length === 0) {
                    body.innerHTML = '<tr><td colspan="4" class="text-muted text-center">No line items found.</td></tr>';
                    return;
                }

                request.items.forEach((item) => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${item.category || 'N/A'}</td>
                        <td>${item.resource || 'N/A'}</td>
                        <td>
                            <div>${item.description || 'N/A'}</div>
                            <small class="text-muted">${item.budget_code || ''}</small>
                        </td>
                        <td class="text-end fw-semibold">${money(request.currency, item.amount)}</td>
                    `;
                    body.appendChild(row);
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
            const oldDeliverableIds = @json(old('deliverable_ids', []));

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

            search?.addEventListener('input', renderRequestList);
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
