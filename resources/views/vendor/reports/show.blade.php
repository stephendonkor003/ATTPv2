@extends('layouts.vendor')

@section('title', $report->reference_no)

@section('content')
    <div class="vendor-page-head">
        <div>
            <div class="vendor-eyebrow">Report Detail</div>
            <h3 class="mb-1">{{ $report->title }}</h3>
            <p class="text-muted mb-0">{{ $report->reference_no }}</p>
        </div>
        <a href="{{ route('vendor.reports.index') }}" class="btn btn-vendor-outline">
            <i class="feather-arrow-left me-1"></i> Back
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card vendor-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <span class="badge-soft">{{ ucfirst($report->report_type) }}</span>
                            <span class="status-pill ms-2">{{ ucwords(str_replace('_', ' ', $report->status)) }}</span>
                        </div>
                        <div class="text-end small text-muted">
                            Submitted {{ $report->created_at?->format('M d, Y H:i') }}
                        </div>
                    </div>

                    <hr>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-muted small">Related Procurement</div>
                            <div class="fw-semibold">{{ $report->procurement?->title ?? 'Not linked' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Related Purchase Order</div>
                            <div class="fw-semibold">{{ $report->purchaseOrder?->reference_no ?? 'Not linked' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Period Start</div>
                            <div class="fw-semibold">{{ $report->reporting_period_start?->format('M d, Y') ?? 'N/A' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Period End</div>
                            <div class="fw-semibold">{{ $report->reporting_period_end?->format('M d, Y') ?? 'N/A' }}</div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="text-muted small">Summary</div>
                        <div class="vendor-readable">{{ $report->summary }}</div>
                    </div>
                    @if ($report->challenges)
                        <div class="mt-4">
                            <div class="text-muted small">Challenges / Risks</div>
                            <div class="vendor-readable">{{ $report->challenges }}</div>
                        </div>
                    @endif
                    @if ($report->next_steps)
                        <div class="mt-4">
                            <div class="text-muted small">Next Steps</div>
                            <div class="vendor-readable">{{ $report->next_steps }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card vendor-card mb-4">
                <div class="card-body">
                    <h5 class="mb-3">Admin Feedback</h5>
                    @if ($report->admin_feedback)
                        <div class="vendor-readable">{{ $report->admin_feedback }}</div>
                        <div class="text-muted small mt-2">
                            Reviewed {{ $report->reviewed_at?->format('M d, Y H:i') ?? 'recently' }}
                        </div>
                    @else
                        <p class="text-muted mb-0">No feedback yet.</p>
                    @endif
                </div>
            </div>

            <div class="card vendor-card">
                <div class="card-body">
                    <h5 class="mb-3">Evidence Files</h5>
                    @forelse ($report->documents as $document)
                        <a href="{{ route('vendor.reports.documents.download', [$report, $document]) }}" class="vendor-file-row">
                            <span><i class="feather-paperclip"></i></span>
                            <span>
                                <strong>{{ $document->title }}</strong>
                                <small>{{ $document->file_name }}</small>
                            </span>
                        </a>
                    @empty
                        <p class="text-muted mb-0">No files attached.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
