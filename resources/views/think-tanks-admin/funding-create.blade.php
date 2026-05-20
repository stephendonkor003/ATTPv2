@extends('layouts.app')

@section('title', 'Record Transfer')

@push('styles')
    <style>
        .tt-transfer-hero {
            border: 0;
            border-radius: 10px;
            background: #0f172a;
            color: #ffffff;
            overflow: hidden;
        }

        .tt-transfer-hero .kicker {
            color: #facc15;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            font-size: 0.72rem;
        }

        .tt-transfer-hero .hero-copy {
            color: #e2e8f0;
            max-width: 760px;
            line-height: 1.65;
        }

        .tt-transfer-hero .hero-title {
            color: #facc15;
            font-weight: 900;
        }

        .tt-step {
            display: flex;
            gap: 0.85rem;
            align-items: flex-start;
            padding: 0.9rem 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .tt-step:last-child {
            border-bottom: 0;
        }

        .tt-step-num {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #0ea5e9;
            color: #ffffff;
            font-weight: 800;
            flex: 0 0 auto;
        }

        .tt-form-card,
        .tt-info-card {
            border: 0;
            border-radius: 8px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
        }

        .tt-budget-pill {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.85rem;
            background: #f8fafc;
        }

        .tt-budget-pill .label {
            color: #64748b;
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 700;
        }

        .tt-budget-pill .value {
            color: #0f172a;
            font-size: 1.05rem;
            font-weight: 800;
        }

        .tt-help-note {
            border-left: 4px solid #facc15;
            background: #fffbeb;
            color: #713f12;
            border-radius: 8px;
            padding: 0.95rem 1rem;
        }
    </style>
@endpush

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="feather-plus text-primary me-2"></i>Record Transfer</h4>
                <p class="text-muted mb-0">Create a bank transfer record against the Funding to Think Tanks sub-activity budget.</p>
            </div>
            <div class="d-flex gap-2">
                @can('think_tanks.funding.history.view')
                    <a href="{{ route('think-tanks-admin.funding.history') }}" class="btn btn-light btn-sm border">Transfer History</a>
                @endcan
                <a href="{{ route('think-tanks-admin.funding') }}" class="btn btn-light btn-sm border">Funding Dashboard</a>
            </div>
        </div>

        @if ($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif
        @if (session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif

        @php
            $currency = 'USD';
        @endphp

        <div class="card tt-transfer-hero mb-4">
            <div class="card-body p-4">
                <div class="kicker mb-2">Funding to Think Tanks</div>
                <h3 class="hero-title mb-2">Record a transfer after the Secretariat sends funds.</h3>
                <p class="hero-copy mb-0">
                    This page records an operational disbursement from the approved Funding to Think Tanks sub-activity.
                    The system creates the budget trail, stores the transfer details, and then waits for the think tank to confirm receipt from its own portal.
                </p>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="card tt-form-card">
                    <div class="card-header bg-white border-0 pb-0">
                        <h5 class="mb-1 fw-bold">Transfer Information</h5>
                        <p class="text-muted mb-0">Use the bank transfer details exactly as they appear on the payment instruction or transaction advice.</p>
                    </div>
                    <div class="card-body">
                        <form class="row g-3" method="POST" action="{{ route('think-tanks-admin.funding.store') }}">
                            @csrf
                            <div class="col-md-12">
                                <label class="form-label">Think Tank</label>
                                <select class="form-select" name="think_tank_member_id" required>
                                    <option value="">Select think tank and consortium</option>
                                    @foreach ($thinkTanks as $thinkTank)
                                        <option value="{{ $thinkTank->id }}" @selected(old('think_tank_member_id') === $thinkTank->id)>{{ $thinkTank->name }} - {{ $thinkTank->consortium?->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Amount</label>
                                <input class="form-control" type="number" step="0.01" min="0.01" max="{{ $summary['remaining'] }}" name="amount" value="{{ old('amount') }}" required>
                                <div class="form-text">Cannot exceed the remaining sub-activity balance.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Currency</label>
                                <input class="form-control" name="currency" maxlength="10" value="USD" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Transfer Date and Time</label>
                                <input class="form-control" type="datetime-local" name="paid_at" value="{{ old('paid_at', now()->format('Y-m-d\TH:i')) }}" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Transfer Method</label>
                                <input class="form-control" name="payment_method" value="{{ old('payment_method', 'Bank transfer') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Transfer Reference</label>
                                <input class="form-control" name="transfer_reference" value="{{ old('transfer_reference') }}" placeholder="Bank ref, payment voucher, or transaction ID">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" name="notes" rows="4" placeholder="Purpose, tranche, internal approval note, or any instruction attached to this transfer">{{ old('notes') }}</textarea>
                            </div>

                            <div class="col-12">
                                <div class="tt-help-note">
                                    After saving, the transfer appears in the think tank portal as pending receipt. The think tank must confirm once funds are visible in its bank account.
                                </div>
                            </div>

                            <div class="col-12 d-flex justify-content-end gap-2">
                                <a href="{{ route('think-tanks-admin.funding') }}" class="btn btn-light border">Cancel</a>
                                <button class="btn btn-primary"><i class="feather-send me-1"></i> Save Transfer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card tt-info-card mb-4">
                    <div class="card-header bg-white border-0 pb-0"><h5 class="mb-0 fw-bold">Budget Guardrails</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12"><div class="tt-budget-pill"><div class="label">Sub-Activity Budget</div><div class="value">{{ $currency }} {{ number_format($summary['budget'], 2) }}</div></div></div>
                            <div class="col-12"><div class="tt-budget-pill"><div class="label">Already Transferred</div><div class="value">{{ $currency }} {{ number_format($summary['transferred'], 2) }}</div></div></div>
                            <div class="col-12"><div class="tt-budget-pill"><div class="label">Available Remaining</div><div class="value">{{ $currency }} {{ number_format($summary['remaining'], 2) }}</div></div></div>
                        </div>
                    </div>
                </div>

                <div class="card tt-info-card">
                    <div class="card-header bg-white border-0 pb-0"><h5 class="mb-0 fw-bold">How It Works</h5></div>
                    <div class="card-body">
                        <div class="tt-step">
                            <span class="tt-step-num">1</span>
                            <div><strong>Select the think tank.</strong><div class="text-muted small">The transfer is attached to that think tank and its consortium.</div></div>
                        </div>
                        <div class="tt-step">
                            <span class="tt-step-num">2</span>
                            <div><strong>Enter the USD transfer details.</strong><div class="text-muted small">Amount, date, method, and reference become the official transfer record.</div></div>
                        </div>
                        <div class="tt-step">
                            <span class="tt-step-num">3</span>
                            <div><strong>The system creates the finance trail.</strong><div class="text-muted small">Budget commitment, purchase request, transfer order, and disbursement are linked.</div></div>
                        </div>
                        <div class="tt-step">
                            <span class="tt-step-num">4</span>
                            <div><strong>The think tank confirms receipt.</strong><div class="text-muted small">Because the system is not connected to the bank, confirmation happens from the portal.</div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
