@extends('layouts.vendor')

@section('title', 'Submit Report')

@section('content')
    <div class="vendor-page-head">
        <div>
            <div class="vendor-eyebrow">New Report</div>
            <h3 class="mb-1">Submit Report</h3>
            <p class="text-muted mb-0">Send structured progress or financial reports to the ATTP administration team.</p>
        </div>
        <a href="{{ route('vendor.reports.index') }}" class="btn btn-vendor-outline">
            <i class="feather-arrow-left me-1"></i> Back
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('vendor.reports.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card vendor-card">
                    <div class="card-body">
                        <h5 class="mb-3">Report Details</h5>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Title</label>
                            <input name="title" class="form-control" value="{{ old('title') }}" required>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Report Type</label>
                                <select name="report_type" class="form-select" required>
                                    @foreach (['progress' => 'Progress', 'completion' => 'Completion', 'financial' => 'Financial', 'deliverable' => 'Deliverable', 'incident' => 'Incident', 'other' => 'Other'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('report_type', 'progress') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Period Start</label>
                                <input type="date" name="reporting_period_start" class="form-control" value="{{ old('reporting_period_start') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Period End</label>
                                <input type="date" name="reporting_period_end" class="form-control" value="{{ old('reporting_period_end') }}">
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Related Procurement</label>
                                <select name="procurement_id" class="form-select">
                                    <option value="">General report</option>
                                    @foreach ($procurements as $procurement)
                                        <option value="{{ $procurement->id }}" @selected(old('procurement_id') === $procurement->id)>
                                            {{ $procurement->reference_no ?? 'N/A' }} - {{ $procurement->title }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Related Purchase Order</label>
                                <select name="purchase_order_id" class="form-select">
                                    <option value="">Not linked</option>
                                    @foreach ($purchaseOrders as $purchaseOrder)
                                        <option value="{{ $purchaseOrder->id }}" @selected(old('purchase_order_id') === $purchaseOrder->id)>
                                            {{ $purchaseOrder->reference_no ?? 'N/A' }} - {{ $purchaseOrder->po_title ?? 'Purchase Order' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label fw-semibold">Summary</label>
                            <textarea name="summary" class="form-control" rows="6" required>{{ old('summary') }}</textarea>
                        </div>
                        <div class="mt-3">
                            <label class="form-label fw-semibold">Challenges / Risks</label>
                            <textarea name="challenges" class="form-control" rows="4">{{ old('challenges') }}</textarea>
                        </div>
                        <div class="mt-3">
                            <label class="form-label fw-semibold">Next Steps</label>
                            <textarea name="next_steps" class="form-control" rows="4">{{ old('next_steps') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card vendor-card mb-4">
                    <div class="card-body">
                        <h5 class="mb-3">Evidence Files</h5>
                        <input type="file" name="documents[]" class="form-control" multiple>
                        <small class="text-muted d-block mt-2">Attach implementation evidence, financial support, deliverable proof, or supporting correspondence.</small>
                    </div>
                </div>
                <div class="card vendor-card">
                    <div class="card-body">
                        <h5 class="mb-2">Review Flow</h5>
                        <div class="vendor-flow-step active">1. Vendor submits report</div>
                        <div class="vendor-flow-step">2. Admin reviews evidence</div>
                        <div class="vendor-flow-step">3. Feedback is returned here</div>
                        <button class="btn btn-vendor w-100 mt-3" type="submit">Submit Report</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
