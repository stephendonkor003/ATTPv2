@extends('layouts.vendor')

@section('title', $pageTitle)

@section('content')
    @php
        $storeRoute = route('vendor.purchase-requests.store');
        $indexRoute = route('vendor.purchase-requests.index');
        $hasProcurementSources = $procurements->isNotEmpty();
    @endphp

    <div class="vendor-page-head">
        <div>
            <div class="vendor-eyebrow">New Intake</div>
            <h3 class="mb-1">{{ $pageTitle }}</h3>
            <p class="text-muted mb-0">Provide the details admins need to review and process your request.</p>
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

    <form method="POST" action="{{ $storeRoute }}" enctype="multipart/form-data">
        @csrf
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card vendor-card mb-4">
                    <div class="card-body">
                        <h5 class="mb-3">Request Details</h5>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Title</label>
                            <input name="title" class="form-control" value="{{ old('title') }}" required>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Related Procurement</label>
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
                                        <option value="{{ $procurement->id }}" @selected(old('sub_activity_id') === $procurement->id)>
                                            {{ $procurementLabel ?: 'Assigned Procurement' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Currency</label>
                                <input name="currency" class="form-control" value="{{ old('currency', 'USD') }}" maxlength="10" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Date</label>
                                <input type="date" name="needed_by" class="form-control" value="{{ old('needed_by') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Priority</label>
                                <select name="priority" class="form-select" required>
                                    @foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('priority', 'normal') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="4"
                                placeholder="Add any details the admin should know.">{{ old('description') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="card vendor-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                            <div>
                                <h5 class="mb-1">Line Items</h5>
                                <p class="text-muted mb-0 small">Amounts are calculated from quantity and unit price.</p>
                            </div>
                            <button type="button" class="btn btn-vendor-outline btn-sm" id="addLineItem">
                                <i class="feather-plus me-1"></i> Add Item
                            </button>
                        </div>

                        <div id="lineItems">
                            @for ($i = 0; $i < 2; $i++)
                                <div class="vendor-line-item" data-line-item-row>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Item</label>
                                            <input name="items[{{ $i }}][item_name]" class="form-control"
                                                value="{{ old("items.$i.item_name") }}" @required($i === 0)>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Qty</label>
                                            <input type="number" step="0.01" min="0.01" name="items[{{ $i }}][quantity]"
                                                class="form-control" value="{{ old("items.$i.quantity", $i === 0 ? 1 : null) }}"
                                                data-line-quantity @required($i === 0)>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Unit Price</label>
                                            <input type="number" step="0.01" min="0.01" name="items[{{ $i }}][unit_price]"
                                                class="form-control" value="{{ old("items.$i.unit_price") }}"
                                                data-line-unit-price @required($i === 0)>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Delivery Date</label>
                                            <input type="date" name="items[{{ $i }}][delivery_date]" class="form-control"
                                                value="{{ old("items.$i.delivery_date") }}">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Amount</label>
                                            <input type="text" class="form-control" value="0.00" data-line-amount readonly>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Description</label>
                                            <input name="items[{{ $i }}][description]" class="form-control"
                                                value="{{ old("items.$i.description") }}">
                                        </div>
                                    </div>
                                </div>
                            @endfor
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            <div class="border rounded px-3 py-2 bg-light text-end">
                                <div class="text-muted small fw-semibold">Calculated Total</div>
                                <div class="fs-5 fw-bold" id="lineItemsTotal">0.00</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card vendor-card mb-4">
                    <div class="card-body">
                        <h5 class="mb-3">Supporting Documents</h5>
                        <input type="file" name="documents[]" class="form-control" multiple>
                        <small class="text-muted d-block mt-2">Upload quotes, specifications, delivery notes, correspondence, or any document admins need.</small>
                    </div>
                </div>

                <div class="card vendor-card">
                    <div class="card-body">
                        <h5 class="mb-2">Submission Flow</h5>
                        <div class="vendor-flow-step active">1. Vendor submits request</div>
                        <div class="vendor-flow-step">2. Admin reviews and responds</div>
                        <div class="vendor-flow-step">3. Finance processes internally</div>
                        <button class="btn btn-vendor w-100 mt-3" type="submit" @disabled(! $hasProcurementSources)>
                            Submit Request
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const container = document.getElementById('lineItems');
            const addButton = document.getElementById('addLineItem');
            let nextIndex = 2;

            addButton?.addEventListener('click', function () {
                const block = document.createElement('div');
                block.className = 'vendor-line-item';
                block.setAttribute('data-line-item-row', 'true');
                block.innerHTML = `
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Item</label>
                            <input name="items[${nextIndex}][item_name]" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Qty</label>
                            <input type="number" step="0.01" min="0.01" name="items[${nextIndex}][quantity]" class="form-control" value="1" data-line-quantity>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Unit Price</label>
                            <input type="number" step="0.01" min="0.01" name="items[${nextIndex}][unit_price]" class="form-control" data-line-unit-price>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Delivery Date</label>
                            <input type="date" name="items[${nextIndex}][delivery_date]" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Amount</label>
                            <input type="text" class="form-control" value="0.00" data-line-amount readonly>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <input name="items[${nextIndex}][description]" class="form-control">
                        </div>
                    </div>`;
                container.appendChild(block);
                bindLineItem(block);
                nextIndex += 1;
            });

            const totalEl = document.getElementById('lineItemsTotal');

            function money(value) {
                return Number(value || 0).toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
            }

            function calculateTotals() {
                let total = 0;

                container.querySelectorAll('[data-line-item-row]').forEach((row) => {
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
            }

            container.querySelectorAll('[data-line-item-row]').forEach(bindLineItem);
            calculateTotals();
        });
    </script>
@endpush
