@extends('layouts.app')

@section('content')
    <div class="nxl-container">
        <div class="card mb-4 border-0" style="background: linear-gradient(120deg, #0f172a, #0ea5e9); color: #fff; border-radius: 16px;">
            <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <h4 class="fw-bold mb-1">Create Purchase Order</h4>
                    <p class="mb-0 text-white-50">Tie the purchase order to an approved budget commitment.</p>
                </div>
                <a href="{{ route('procurement.purchase-orders.index') }}" class="btn btn-light">
                    <i class="feather-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="card shadow-sm border-0">
            <div class="card-body">
                @if ($commitments->isEmpty())
                    <div class="alert alert-warning mb-0">
                        No approved commitments with remaining balance are available for purchase order creation.
                    </div>
                @else
                    <form method="POST" action="{{ route('procurement.purchase-orders.store') }}" class="row g-3">
                        @csrf

                        <div class="col-lg-12">
                            <label class="form-label fw-semibold">Approved Commitment <span class="text-danger">*</span></label>
                            <select name="budget_commitment_id" id="commitmentSelect" class="form-select" required>
                                <option value="">Select approved commitment</option>
                                @foreach ($commitments->groupBy('project_label') as $projectName => $projectCommitments)
                                    <optgroup label="Project: {{ $projectName }}">
                                        @foreach ($projectCommitments as $commitment)
                                            @php
                                                $remaining = (float) ($commitment->remaining_amount ?? $commitment->commitment_amount ?? 0);
                                                $activityLabel = $commitment->activity_label ?: 'Project-level commitment';
                                                $subActivityLabel = $commitment->sub_activity_label ?: 'No sub-activity';
                                                $label = 'Activity: ' . $activityLabel
                                                    . ' | Sub-Activity: ' . $subActivityLabel
                                                    . ' | ' . ($commitment->purchase_request_reference ?? 'Commitment')
                                                    . ' | ' . ($commitment->commitment_year ?? 'Year N/A')
                                                    . ' | Remaining ' . number_format($remaining, 2);
                                            @endphp
                                            <option value="{{ $commitment->id }}"
                                                data-amount="{{ $remaining }}"
                                                data-currency="{{ $commitment->commitment_currency ?? 'USD' }}"
                                                @selected((string) old('budget_commitment_id') === (string) $commitment->id)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <div class="form-text">A purchase order cannot be created without an approved commitment.</div>
                        </div>

                        <div class="col-lg-6">
                            <label class="form-label fw-semibold">Procurement</label>
                            <select name="procurement_id" class="form-select">
                                <option value="">Optional</option>
                                @foreach ($procurements as $procurement)
                                    <option value="{{ $procurement->id }}" @selected(old('procurement_id') === $procurement->id)>
                                        {{ $procurement->title }} ({{ $procurement->reference_no ?? 'N/A' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-6">
                            <label class="form-label fw-semibold">Vendor</label>
                            <select name="vendor_id" class="form-select">
                                <option value="">Optional</option>
                                @foreach ($vendors as $vendor)
                                    <option value="{{ $vendor->id }}" @selected(old('vendor_id') === $vendor->id)>
                                        {{ $vendor->name }} - {{ $vendor->email }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="amount" id="amountInput"
                                class="form-control" value="{{ old('amount') }}" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Currency</label>
                            <input type="text" name="currency" id="currencyInput" class="form-control"
                                value="{{ old('currency', 'USD') }}" maxlength="10">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Issued At</label>
                            <input type="date" name="issued_at" class="form-control" value="{{ old('issued_at', now()->toDateString()) }}">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <option value="draft" @selected(old('status', 'draft') === 'draft')>Draft</option>
                                <option value="issued" @selected(old('status') === 'issued')>Issued</option>
                            </select>
                        </div>

                        <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                            <a href="{{ route('procurement.purchase-orders.index') }}" class="btn btn-light">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather-save me-1"></i> Create Purchase Order
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const select = document.getElementById('commitmentSelect');
            const amount = document.getElementById('amountInput');
            const currency = document.getElementById('currencyInput');

            select?.addEventListener('change', function () {
                const option = select.options[select.selectedIndex];
                if (!option) return;
                if (!amount.value && option.dataset.amount) amount.value = option.dataset.amount;
                if (option.dataset.currency) currency.value = option.dataset.currency;
            });
        });
    </script>
@endsection
