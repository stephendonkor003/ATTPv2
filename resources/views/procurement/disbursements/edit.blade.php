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

        @media (max-width: 991.98px) {
            .disb-edit .stat-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            .disb-edit .stat-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $currency = $purchaseOrder->resolved_currency ?? $disbursement->resolved_currency ?? '';
        $money = fn ($value) => trim($currency . ' ' . number_format((float) $value, 2));
        $selectedPaymentMethod = old('payment_method', $disbursement->payment_method);
        $selectedStatus = old('status', $disbursement->status ?? 'completed');
        $selectedLineItemId = old('purchase_request_item_id', $selectedLineItem?->id);
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
                    <h4 class="fw-bold mb-1">Edit Disbursement</h4>
                    <p class="mb-0">{{ $disbursement->reference_no ?? 'N/A' }}</p>
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
                        <div class="stat-label">Paid Excluding This</div>
                        <div class="stat-value">{{ $money($paidExcludingCurrent) }}</div>
                    </div>
                    <div class="stat-tile">
                        <div class="stat-label">Line Editable Limit</div>
                        <div class="stat-value">{{ $money($maxPayingAmount) }}</div>
                    </div>
                    <div class="stat-tile">
                        <div class="stat-label">Current Amount</div>
                        <div class="stat-value">{{ $money($disbursement->amount) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('procurement.disbursements.update', $disbursement) }}">
            @csrf
            @method('PUT')

            <div class="card panel-card">
                <div class="card-body">
                    <div class="section-title mb-3">Editable Payment Details</div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Paid PO Line Item <span class="text-danger">*</span></label>
                            @if ($lineItems->isEmpty())
                                <input type="text" class="form-control is-invalid" value="No purchase request line items found" disabled>
                                <div class="invalid-feedback d-block">This disbursement cannot be edited until the PO has source line items.</div>
                            @else
                                <select name="purchase_request_item_id" class="form-select @error('purchase_request_item_id') is-invalid @enderror" required>
                                    <option value="">Select paid line item</option>
                                    @foreach ($lineItems as $lineItem)
                                        @php
                                            $lineSummary = $lineItemPaymentSummaries->get((string) $lineItem->id, [
                                                'paid_amount' => 0,
                                                'remaining_amount' => (float) ($lineItem->amount ?? 0),
                                            ]);
                                        @endphp
                                        <option value="{{ $lineItem->id }}" @selected((string) $selectedLineItemId === (string) $lineItem->id)>
                                            {{ $lineItem->resource?->name ?? $lineItem->resourceCategory?->name ?? 'Line item' }}
                                            @if ($lineItem->milestone)
                                                | {{ $lineItem->milestone }}
                                            @endif
                                            | Balance {{ $currency ?: 'USD' }} {{ number_format((float) $lineSummary['remaining_amount'], 2) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('purchase_request_item_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            @endif
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">{{ $currency ?: 'USD' }}</span>
                                <input type="number"
                                    step="0.01"
                                    min="0.01"
                                    name="amount"
                                    value="{{ old('amount', number_format((float) $disbursement->amount, 2, '.', '')) }}"
                                    class="form-control @error('amount') is-invalid @enderror"
                                    required>
                            </div>
                            <div class="form-text">For paying statuses, maximum is {{ $money($maxPayingAmount) }}.</div>
                            @error('amount')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                                @if ($selectedStatus && ! array_key_exists($selectedStatus, $statusOptions))
                                    <option value="{{ $selectedStatus }}" selected>{{ ucfirst(str_replace('_', ' ', $selectedStatus)) }}</option>
                                @endif
                                @foreach ($statusOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Cancelled, void, and reversed do not count against the PO balance.</div>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Payment Method <span class="text-danger">*</span></label>
                            <select name="payment_method" class="form-select @error('payment_method') is-invalid @enderror" required>
                                <option value="">Select method</option>
                                @if ($selectedPaymentMethod && ! in_array($selectedPaymentMethod, $paymentMethods, true))
                                    <option value="{{ $selectedPaymentMethod }}" selected>{{ $selectedPaymentMethod }}</option>
                                @endif
                                @foreach ($paymentMethods as $method)
                                    <option value="{{ $method }}" @selected($selectedPaymentMethod === $method)>{{ $method }}</option>
                                @endforeach
                            </select>
                            @error('payment_method')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Paid At <span class="text-danger">*</span></label>
                            <input type="date"
                                name="paid_at"
                                value="{{ old('paid_at', $disbursement->paid_at?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                                class="form-control @error('paid_at') is-invalid @enderror"
                                required>
                            @error('paid_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Transfer Reference</label>
                            <input type="text"
                                name="transfer_reference"
                                value="{{ old('transfer_reference', $disbursement->transfer_reference) }}"
                                class="form-control @error('transfer_reference') is-invalid @enderror"
                                maxlength="255">
                            @error('transfer_reference')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea name="notes"
                                class="form-control @error('notes') is-invalid @enderror"
                                rows="4"
                                maxlength="2000">{{ old('notes', $disbursement->notes) }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 d-flex flex-wrap justify-content-end gap-2">
                            <a href="{{ route('procurement.disbursements.show', $disbursement) }}" class="btn btn-light">
                                Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather-save me-1"></i> Save Changes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
