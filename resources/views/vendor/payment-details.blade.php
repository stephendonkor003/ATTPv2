@extends('layouts.vendor')

@section('title', 'Payment Details')

@section('content')
    <div class="mb-4">
        <h3 class="mb-1">Payment Details</h3>
        <p class="text-muted mb-0">Provide your preferred payment information to appear on invoices.</p>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>There were errors with your submission.</strong>
        </div>
    @endif

    <div class="card vendor-card">
        <div class="card-body">
            <form method="POST" action="{{ route('vendor.payment-details.update') }}" class="row g-3">
                @csrf
                @method('PUT')

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Preferred Method</label>
                    <select name="payment_method_preference" class="form-control">
                        <option value="">Select method</option>
                        @foreach ($paymentMethods as $method)
                            <option value="{{ $method }}" @selected($user->payment_method_preference === $method)>
                                {{ $method }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Bank Name</label>
                    <input type="text" name="payment_bank_name" class="form-control"
                        value="{{ $user->payment_bank_name }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Account Name</label>
                    <input type="text" name="payment_account_name" class="form-control"
                        value="{{ $user->payment_account_name }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Account Number</label>
                    <input type="text" name="payment_account_number" class="form-control"
                        value="{{ $user->payment_account_number }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">SWIFT Code</label>
                    <input type="text" name="payment_swift_code" class="form-control"
                        value="{{ $user->payment_swift_code }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">IBAN</label>
                    <input type="text" name="payment_iban" class="form-control"
                        value="{{ $user->payment_iban }}">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Mobile Money Provider</label>
                    <input type="text" name="payment_mobile_provider" class="form-control"
                        value="{{ $user->payment_mobile_provider }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Mobile Money Number</label>
                    <input type="text" name="payment_mobile_number" class="form-control"
                        value="{{ $user->payment_mobile_number }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Tax ID</label>
                    <input type="text" name="payment_tax_id" class="form-control"
                        value="{{ $user->payment_tax_id }}">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Payment Address</label>
                    <input type="text" name="payment_address" class="form-control"
                        value="{{ $user->payment_address }}">
                </div>

                <div class="col-12 text-end">
                    <button class="btn btn-vendor">Save Payment Details</button>
                </div>
            </form>
        </div>
    </div>
@endsection
