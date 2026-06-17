@extends('layouts.vendor')

@section('title', 'Reports')

@section('content')
    <div class="vendor-page-head">
        <div>
            <div class="vendor-eyebrow">Reporting</div>
            <h3 class="mb-1">Reports</h3>
            <p class="text-muted mb-0">Submit progress, completion, financial, and deliverable reports for admin review.</p>
        </div>
        <a href="{{ route('vendor.reports.create') }}" class="btn btn-vendor">
            <i class="feather-plus-circle me-1"></i> New Report
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-3 mb-4">
        @foreach ([
            'Total Reports' => $stats['total'] ?? 0,
            'Submitted' => $stats['submitted'] ?? 0,
            'Reviewed' => $stats['reviewed'] ?? 0,
            'Action Required' => $stats['action_required'] ?? 0,
        ] as $label => $value)
            <div class="col-md-3">
                <div class="card vendor-card h-100">
                    <div class="card-body vendor-metric">
                        <div>
                            <div class="vendor-metric-label">{{ $label }}</div>
                            <div class="vendor-metric-value">{{ number_format($value) }}</div>
                        </div>
                        <span class="vendor-metric-icon"><i class="feather-bar-chart-2"></i></span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card vendor-card">
        <div class="card-body">
            @if ($reports->isEmpty())
                <div class="vendor-empty">
                    <div class="vendor-empty-icon"><i class="feather-bar-chart-2"></i></div>
                    <h5>No reports submitted</h5>
                    <p class="text-muted mb-3">Use reports to document implementation progress and finance updates.</p>
                    <a href="{{ route('vendor.reports.create') }}" class="btn btn-vendor btn-sm">Submit Report</a>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Report</th>
                                <th>Related Record</th>
                                <th>Period</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($reports as $report)
                                <tr>
                                    <td><span class="badge-soft">{{ $report->reference_no }}</span></td>
                                    <td>
                                        <div class="fw-semibold">{{ $report->title }}</div>
                                        <small class="text-muted">{{ ucfirst($report->report_type) }}</small>
                                    </td>
                                    <td>{{ $report->procurement?->title ?? $report->purchaseOrder?->reference_no ?? 'General report' }}</td>
                                    <td>
                                        {{ $report->reporting_period_start?->format('M d, Y') ?? 'N/A' }}
                                        -
                                        {{ $report->reporting_period_end?->format('M d, Y') ?? 'N/A' }}
                                    </td>
                                    <td><span class="status-pill">{{ ucwords(str_replace('_', ' ', $report->status)) }}</span></td>
                                    <td class="text-end">
                                        <a href="{{ route('vendor.reports.show', $report) }}" class="btn btn-vendor-outline btn-sm">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
