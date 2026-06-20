@extends('layouts.vendor')

@section('title', $pageTitle)

@push('styles')
    <style>
        .purchase-request-create .form-panel {
            border: 1px solid #dbe4ef;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }

        .purchase-request-create .form-panel + .form-panel {
            margin-top: 18px;
        }

        .purchase-request-create .panel-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 20px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .purchase-request-create .panel-title-wrap {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            min-width: 0;
        }

        .purchase-request-create .panel-icon {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 38px;
            background: #e0f2fe;
            color: #0369a1;
            font-size: 1rem;
        }

        .purchase-request-create .panel-title {
            color: #0f172a;
            font-weight: 800;
            font-size: 1rem;
            line-height: 1.25;
            margin: 0;
        }

        .purchase-request-create .panel-subtitle {
            color: #64748b;
            font-size: 0.82rem;
            margin: 3px 0 0;
        }

        .purchase-request-create .panel-body {
            padding: 20px;
        }

        .purchase-request-create .form-label {
            color: #334155;
            font-size: 0.78rem;
            font-weight: 800;
            margin-bottom: 7px;
        }

        .purchase-request-create .form-control,
        .purchase-request-create .form-select {
            border-color: #cbd5e1;
            border-radius: 8px;
            min-height: 42px;
            color: #0f172a;
        }

        .purchase-request-create textarea.form-control {
            min-height: 112px;
        }

        .purchase-request-create .line-items-wrap,
        .purchase-request-create .document-rows {
            display: grid;
            gap: 12px;
        }

        .purchase-request-create .line-item-row,
        .purchase-request-create .document-row {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            padding: 14px;
        }

        .purchase-request-create .line-item-row:first-child .remove-line-item {
            visibility: hidden;
            pointer-events: none;
        }

        .purchase-request-create .amount-field {
            background: #eef2ff;
            color: #0f172a;
            font-weight: 800;
        }

        .purchase-request-create .total-strip {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-top: 16px;
            padding: 14px 16px;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            background: #f0f9ff;
        }

        .purchase-request-create .total-label {
            color: #0369a1;
            font-size: 0.74rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .purchase-request-create .total-value {
            color: #0f172a;
            font-size: 1.35rem;
            font-weight: 900;
            line-height: 1;
        }

        .purchase-request-create .icon-button {
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }

        .purchase-request-create .document-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            align-items: end;
        }

        .purchase-request-create .submit-panel {
            position: sticky;
            top: 16px;
        }

        .purchase-request-create .submission-list {
            display: grid;
            gap: 10px;
            margin-top: 14px;
        }

        .purchase-request-create .submission-step {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            background: #fff;
        }

        .purchase-request-create .submission-step span {
            width: 24px;
            height: 24px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 24px;
            background: #dcfce7;
            color: #166534;
            font-size: 0.72rem;
            font-weight: 900;
        }

        @media (max-width: 767.98px) {
            .purchase-request-create .panel-head,
            .purchase-request-create .total-strip {
                align-items: stretch;
                flex-direction: column;
            }

            .purchase-request-create .document-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $purchaseRequest = $purchaseRequest ?? null;
        $isEditing = isset($purchaseRequest);
        $storeRoute = route('vendor.purchase-requests.store');
        $formAction = $formAction ?? $storeRoute;
        $formMethod = $formMethod ?? null;
        $submitButtonText = $submitButtonText ?? 'Submit Request';
        $indexRoute = route('vendor.purchase-requests.index');
        $hasProcurementSources = $procurements->isNotEmpty();
        $selectedSubActivityId = old('sub_activity_id', $purchaseRequest->sub_activity_id ?? null);
        $lineItemRows = collect(old('items', $isEditing
            ? $purchaseRequest->items->map(fn ($item) => [
                'item_name' => $item->item_name,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'delivery_date' => $item->delivery_date?->format('Y-m-d'),
            ])->all()
            : [[]]));

        if ($lineItemRows->isEmpty()) {
            $lineItemRows = collect([[]]);
        }
    @endphp

    <div class="purchase-request-create">
        <div class="vendor-page-head">
            <div>
                <div class="vendor-eyebrow">New Intake</div>
                <h3 class="mb-1">{{ $pageTitle }}</h3>
                <p class="text-muted mb-0">
                    {{ $isEditing ? 'Update the returned request and resubmit it for admin review.' : 'Create a purchase request for admin review.' }}
                </p>
            </div>
            <a href="{{ $indexRoute }}" class="btn btn-vendor-outline">
                <i class="feather-arrow-left me-1"></i> Back
            </a>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        @unless ($hasProcurementSources)
            <div class="alert alert-warning">
                No procurement funding sources have been assigned to your vendor account yet. Please contact the administrator before submitting a request.
            </div>
        @endunless

        <form method="POST" action="{{ $formAction }}" enctype="multipart/form-data">
            @csrf
            @if ($formMethod)
                @method($formMethod)
            @endif
            <div class="row g-4">
                <div class="col-xl-8">
                    @if ($isEditing && $purchaseRequest->admin_response)
                        <div class="alert alert-warning">
                            <strong>Admin correction note:</strong>
                            <div class="mt-1">{{ $purchaseRequest->admin_response }}</div>
                        </div>
                    @endif

                    <section class="form-panel">
                        <div class="panel-head">
                            <div class="panel-title-wrap">
                                <span class="panel-icon"><i class="feather-file-text"></i></span>
                                <div>
                                    <h5 class="panel-title">Request Details</h5>
                                    <p class="panel-subtitle">Vendor request information</p>
                                </div>
                            </div>
                        </div>
                        <div class="panel-body">
                            <div class="mb-3">
                                <label class="form-label">Title</label>
                                <input name="title" class="form-control" value="{{ old('title', $purchaseRequest->title ?? '') }}"
                                    placeholder="Enter request title" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Related Procurement</label>
                                <select name="sub_activity_id" class="form-select" required @disabled(! $hasProcurementSources)>
                                    <option value="">-- Select Related Procurement --</option>
                                    @foreach ($procurements as $procurement)
                                        @php
                                            $activity = $procurement->activity;
                                            $project = $activity?->project;
                                            $program = $project?->program;
                                            $procurementLabel = collect([
                                                $procurement->name,
                                                $project?->name,
                                                $program?->name,
                                            ])->filter()->join(' / ');
                                        @endphp
                                        <option value="{{ $procurement->id }}" @selected((string) $selectedSubActivityId === (string) $procurement->id)>
                                            {{ $procurementLabel ?: 'Assigned Procurement' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Currency</label>
                                    <input name="currency" class="form-control" value="{{ old('currency', $purchaseRequest->currency ?? 'USD') }}"
                                        maxlength="10" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Date</label>
                                    <input type="date" name="needed_by" class="form-control"
                                        value="{{ old('needed_by', $purchaseRequest?->needed_by?->format('Y-m-d') ?? '') }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Priority</label>
                                    <select name="priority" class="form-select" required>
                                        @foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('priority', $purchaseRequest->priority ?? 'normal') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="mt-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control"
                                    placeholder="Add any details the admin should know.">{{ old('description', $purchaseRequest->description ?? '') }}</textarea>
                            </div>
                        </div>
                    </section>

                    <section class="form-panel">
                        <div class="panel-head">
                            <div class="panel-title-wrap">
                                <span class="panel-icon"><i class="feather-list"></i></span>
                                <div>
                                    <h5 class="panel-title">Line Items</h5>
                                    <p class="panel-subtitle">Cost breakdown</p>
                                </div>
                            </div>
                            <button type="button" class="btn btn-vendor-outline btn-sm" id="addLineItem">
                                <i class="feather-plus me-1"></i> Add Item
                            </button>
                        </div>

                        <div class="panel-body">
                            <div class="line-items-wrap" id="lineItems">
                                @foreach ($lineItemRows as $i => $itemRow)
                                    <div class="line-item-row" data-line-item-row>
                                        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                                            <div class="fw-bold text-dark">Item {{ $i + 1 }}</div>
                                            <button type="button" class="btn btn-outline-danger icon-button remove-line-item"
                                                title="Remove item" data-remove-line-item>
                                                <i class="feather-trash-2"></i>
                                            </button>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label">Item</label>
                                                <input name="items[{{ $i }}][item_name]" class="form-control"
                                                    value="{{ $itemRow['item_name'] ?? '' }}" @required($i === 0)>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Qty</label>
                                                <input type="number" step="0.01" min="0.01" name="items[{{ $i }}][quantity]"
                                                    class="form-control" value="{{ $itemRow['quantity'] ?? ($i === 0 ? 1 : null) }}"
                                                    data-line-quantity @required($i === 0)>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Unit Price</label>
                                                <input type="number" step="0.01" min="0.01" name="items[{{ $i }}][unit_price]"
                                                    class="form-control" value="{{ $itemRow['unit_price'] ?? '' }}"
                                                    data-line-unit-price @required($i === 0)>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Delivery Date</label>
                                                <input type="date" name="items[{{ $i }}][delivery_date]" class="form-control"
                                                    value="{{ $itemRow['delivery_date'] ?? '' }}">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Amount</label>
                                                <input type="text" class="form-control amount-field" value="0.00"
                                                    data-line-amount readonly>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Description</label>
                                                <input name="items[{{ $i }}][description]" class="form-control"
                                                    value="{{ $itemRow['description'] ?? '' }}"
                                                    placeholder="Optional item note">
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="total-strip">
                                <div>
                                    <div class="total-label">Calculated Total</div>
                                    <div class="text-muted small">Line item total</div>
                                </div>
                                <div class="total-value" id="lineItemsTotal">0.00</div>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="col-xl-4">
                    <aside class="form-panel mb-4">
                        <div class="panel-head">
                            <div class="panel-title-wrap">
                                <span class="panel-icon"><i class="feather-paperclip"></i></span>
                                <div>
                                    <h5 class="panel-title">Documents</h5>
                                    <p class="panel-subtitle">Supporting files</p>
                                </div>
                            </div>
                        </div>
                        <div class="panel-body">
                            @if ($isEditing && $purchaseRequest->documents->isNotEmpty())
                                <div class="mb-3">
                                    <div class="form-label">Existing Documents</div>
                                    <div class="d-grid gap-2">
                                        @foreach ($purchaseRequest->documents as $document)
                                            <label class="d-flex align-items-start gap-2 border rounded p-2 bg-light">
                                                <input type="checkbox" name="remove_documents[]" value="{{ $document->id }}" class="mt-1">
                                                <span>
                                                    <span class="fw-semibold d-block">{{ $document->title }}</span>
                                                    <span class="text-muted small">{{ $document->file_name }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <div class="text-muted small mt-1">Check any old file that should be removed when you resubmit.</div>
                                </div>
                            @endif

                            <div class="document-rows" id="documentRows">
                                <div class="document-row" data-document-row>
                                    <div>
                                        <label class="form-label">Document</label>
                                        <input type="file" name="documents[]" class="form-control">
                                    </div>
                                    <button type="button" class="btn btn-outline-danger icon-button remove-document"
                                        title="Remove document" data-remove-document disabled>
                                        <i class="feather-trash-2"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="button" class="btn btn-vendor-outline btn-sm w-100 mt-3" id="addDocument">
                                <i class="feather-plus me-1"></i> Add Another Document
                            </button>
                        </div>
                    </aside>

                    <aside class="form-panel submit-panel">
                        <div class="panel-head">
                            <div class="panel-title-wrap">
                                <span class="panel-icon"><i class="feather-send"></i></span>
                                <div>
                                    <h5 class="panel-title">{{ $isEditing ? 'Resubmit Request' : 'Submit Request' }}</h5>
                                    <p class="panel-subtitle">Final submission</p>
                                </div>
                            </div>
                        </div>
                        <div class="panel-body">
                            <div class="submission-list">
                                <div class="submission-step">
                                    <span>1</span>
                                    <div>
                                        <div class="fw-bold">Vendor submits request</div>
                                        <div class="text-muted small">Your request enters the admin queue.</div>
                                    </div>
                                </div>
                                <div class="submission-step">
                                    <span>2</span>
                                    <div>
                                        <div class="fw-bold">Admin reviews</div>
                                        <div class="text-muted small">You can track the status after submission.</div>
                                    </div>
                                </div>
                                <div class="submission-step">
                                    <span>3</span>
                                    <div>
                                        <div class="fw-bold">Finance processes</div>
                                        <div class="text-muted small">Approved requests move to finance handling.</div>
                                    </div>
                                </div>
                            </div>

                            <button class="btn btn-vendor w-100 mt-4" type="submit" @disabled(! $hasProcurementSources)>
                                <i class="feather-check-circle me-1"></i> {{ $submitButtonText }}
                            </button>
                        </div>
                    </aside>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const lineContainer = document.getElementById('lineItems');
            const addLineButton = document.getElementById('addLineItem');
            const totalEl = document.getElementById('lineItemsTotal');
            const documentRows = document.getElementById('documentRows');
            const addDocumentButton = document.getElementById('addDocument');
            let nextLineIndex = lineContainer?.querySelectorAll('[data-line-item-row]').length || 0;

            function money(value) {
                return Number(value || 0).toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
            }

            function refreshLineLabels() {
                lineContainer.querySelectorAll('[data-line-item-row]').forEach((row, index) => {
                    row.querySelector('.fw-bold.text-dark').textContent = `Item ${index + 1}`;
                    const removeButton = row.querySelector('[data-remove-line-item]');
                    if (removeButton) {
                        removeButton.disabled = index === 0 && lineContainer.querySelectorAll('[data-line-item-row]').length === 1;
                    }
                });
            }

            function calculateTotals() {
                let total = 0;

                lineContainer.querySelectorAll('[data-line-item-row]').forEach((row) => {
                    const quantity = Number(row.querySelector('[data-line-quantity]')?.value || 0);
                    const unitPrice = Number(row.querySelector('[data-line-unit-price]')?.value || 0);
                    const amount = Math.max(quantity, 0) * Math.max(unitPrice, 0);
                    const amountEl = row.querySelector('[data-line-amount]');

                    if (amountEl) {
                        amountEl.value = money(amount);
                    }

                    total += amount;
                });

                if (totalEl) {
                    totalEl.textContent = money(total);
                }
            }

            function bindLineItem(row) {
                row.querySelectorAll('[data-line-quantity], [data-line-unit-price]').forEach((input) => {
                    input.addEventListener('input', calculateTotals);
                });

                row.querySelector('[data-remove-line-item]')?.addEventListener('click', () => {
                    if (lineContainer.querySelectorAll('[data-line-item-row]').length <= 1) {
                        return;
                    }

                    row.remove();
                    refreshLineLabels();
                    calculateTotals();
                });
            }

            addLineButton?.addEventListener('click', function () {
                const block = document.createElement('div');
                block.className = 'line-item-row';
                block.setAttribute('data-line-item-row', 'true');
                block.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                        <div class="fw-bold text-dark">Item</div>
                        <button type="button" class="btn btn-outline-danger icon-button remove-line-item" title="Remove item" data-remove-line-item>
                            <i class="feather-trash-2"></i>
                        </button>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Item</label>
                            <input name="items[${nextLineIndex}][item_name]" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Qty</label>
                            <input type="number" step="0.01" min="0.01" name="items[${nextLineIndex}][quantity]" class="form-control" value="1" data-line-quantity>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Unit Price</label>
                            <input type="number" step="0.01" min="0.01" name="items[${nextLineIndex}][unit_price]" class="form-control" data-line-unit-price>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Delivery Date</label>
                            <input type="date" name="items[${nextLineIndex}][delivery_date]" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Amount</label>
                            <input type="text" class="form-control amount-field" value="0.00" data-line-amount readonly>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <input name="items[${nextLineIndex}][description]" class="form-control" placeholder="Optional item note">
                        </div>
                    </div>`;
                lineContainer.appendChild(block);
                bindLineItem(block);
                nextLineIndex += 1;
                refreshLineLabels();
                calculateTotals();
            });

            function refreshDocumentButtons() {
                const rows = documentRows.querySelectorAll('[data-document-row]');
                rows.forEach((row) => {
                    const button = row.querySelector('[data-remove-document]');
                    if (button) {
                        button.disabled = rows.length === 1;
                    }
                });
            }

            function bindDocumentRow(row) {
                row.querySelector('[data-remove-document]')?.addEventListener('click', () => {
                    if (documentRows.querySelectorAll('[data-document-row]').length <= 1) {
                        return;
                    }

                    row.remove();
                    refreshDocumentButtons();
                });
            }

            addDocumentButton?.addEventListener('click', () => {
                const row = document.createElement('div');
                row.className = 'document-row';
                row.setAttribute('data-document-row', 'true');
                row.innerHTML = `
                    <div>
                        <label class="form-label">Document</label>
                        <input type="file" name="documents[]" class="form-control">
                    </div>
                    <button type="button" class="btn btn-outline-danger icon-button remove-document" title="Remove document" data-remove-document>
                        <i class="feather-trash-2"></i>
                    </button>`;
                documentRows.appendChild(row);
                bindDocumentRow(row);
                refreshDocumentButtons();
            });

            lineContainer.querySelectorAll('[data-line-item-row]').forEach(bindLineItem);
            documentRows.querySelectorAll('[data-document-row]').forEach(bindDocumentRow);
            refreshLineLabels();
            refreshDocumentButtons();
            calculateTotals();
        });
    </script>
@endpush
