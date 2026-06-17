@extends('layouts.app')

@section('title', 'Vendor Report Details')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">Vendor Report</h4>
                <p class="text-muted mb-0">{{ $report->reference_no }} submitted by {{ $report->user->name ?? 'Vendor' }}.</p>
            </div>
            <a href="{{ route('vendors.requests.reports.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="feather-arrow-left me-1"></i> Back
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Please check the highlighted fields and try again.</strong>
            </div>
        @endif

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                            <div>
                                <div class="text-muted small">Report</div>
                                <h5 class="fw-bold mb-1">{{ $report->title }}</h5>
                                <div class="text-muted">{{ \Illuminate\Support\Str::headline($report->report_type) }}</div>
                            </div>
                            <span class="badge bg-primary-subtle text-primary px-3 py-2">
                                {{ \Illuminate\Support\Str::headline($report->status ?? 'submitted') }}
                            </span>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="text-muted small">Vendor</div>
                                <div class="fw-semibold">{{ $report->user->name ?? 'Vendor' }}</div>
                                <div class="text-muted small">{{ $report->user->email ?? 'N/A' }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">Reporting Period</div>
                                <div class="fw-semibold">
                                    {{ $report->reporting_period_start?->format('d M Y') ?? 'N/A' }}
                                    -
                                    {{ $report->reporting_period_end?->format('d M Y') ?? 'N/A' }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">Procurement</div>
                                <div class="fw-semibold">{{ $report->procurement->title ?? 'N/A' }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">Purchase Order</div>
                                <div class="fw-semibold">{{ $report->purchaseOrder->reference_no ?? 'N/A' }}</div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <div class="text-muted small">Summary</div>
                            <div class="border rounded p-3 bg-light">{{ $report->summary }}</div>
                        </div>

                        <div class="mt-3">
                            <div class="text-muted small">Challenges</div>
                            <div class="border rounded p-3 bg-light">{{ $report->challenges ?: 'No challenges reported.' }}</div>
                        </div>

                        <div class="mt-3">
                            <div class="text-muted small">Next Steps</div>
                            <div class="border rounded p-3 bg-light">{{ $report->next_steps ?: 'No next steps supplied.' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 fw-semibold">Admin Review</h6>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('vendors.requests.reports.respond', $report) }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Status</label>
                                <select name="status" class="form-control" required>
                                    @foreach (['submitted', 'reviewed', 'accepted', 'rejected'] as $status)
                                        <option value="{{ $status }}" {{ $report->status === $status ? 'selected' : '' }}>
                                            {{ \Illuminate\Support\Str::headline($status) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Feedback</label>
                                <textarea name="admin_feedback" rows="6" class="form-control">{{ old('admin_feedback', $report->admin_feedback) }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="feather-save me-1"></i> Save Review
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 fw-semibold">Attached Documents</h6>
                    </div>
                    <div class="card-body">
                        @forelse ($report->documents as $document)
                            <div class="border rounded p-3 mb-2">
                                <div class="fw-semibold">{{ $document->title }}</div>
                                <div class="text-muted small">{{ $document->file_name }}</div>
                                <a href="{{ route('vendors.requests.documents.download', $document) }}"
                                    class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="feather-download me-1"></i> Download
                                </a>
                            </div>
                        @empty
                            <p class="text-muted mb-0">No documents attached.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
