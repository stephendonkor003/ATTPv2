@extends('layouts.vendor')

@section('title', $pageTitle)

@section('content')
    @php
        $createRoute = route('vendor.purchase-requests.create');
    @endphp

    <div class="vendor-page-head">
        <div>
            <div class="vendor-eyebrow">Procurement Intake</div>
            <h3 class="mb-1">{{ $pageTitle }}</h3>
            <p class="text-muted mb-0">
                Submit purchase needs for admin review and downstream finance processing.
            </p>
        </div>
        <a href="{{ $createRoute }}" class="btn btn-vendor">
            <i class="feather-plus-circle me-1"></i> New Request
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-3 mb-4">
        @foreach ([
            'Total' => $stats['total'] ?? 0,
            'Submitted' => $stats['submitted'] ?? 0,
            'In Review' => $stats['in_review'] ?? 0,
            'Processed' => $stats['approved'] ?? 0,
        ] as $label => $value)
            <div class="col-md-3">
                <div class="card vendor-card h-100">
                    <div class="card-body vendor-metric">
                        <div>
                            <div class="vendor-metric-label">{{ $label }}</div>
                            <div class="vendor-metric-value">{{ number_format($value) }}</div>
                        </div>
                        <span class="vendor-metric-icon"><i class="feather-activity"></i></span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card vendor-card">
        <div class="card-body">
            @if ($requests->isEmpty())
                <div class="vendor-empty">
                    <div class="vendor-empty-icon"><i class="feather-file-plus"></i></div>
                    <h5>No requests yet</h5>
                    <p class="text-muted mb-3">Create your first request and it will appear in the admin review queue.</p>
                    <a href="{{ $createRoute }}" class="btn btn-vendor btn-sm">Create Request</a>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Title</th>
                                <th>Related Record</th>
                                <th class="text-end">Amount</th>
                                <th>Needed By</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($requests as $requestRecord)
                                <tr>
                                    <td><span class="badge-soft">{{ $requestRecord->reference_no }}</span></td>
                                    <td>
                                        <div class="fw-semibold">{{ $requestRecord->title }}</div>
                                        <small class="text-muted">{{ ucfirst($requestRecord->priority) }} priority</small>
                                    </td>
                                    <td>
                                        <div>{{ $requestRecord->procurement?->title ?? $requestRecord->purchaseOrder?->reference_no ?? 'General request' }}</div>
                                    </td>
                                    <td class="text-end">
                                        {{ $requestRecord->currency }} {{ number_format((float) $requestRecord->requested_amount, 2) }}
                                    </td>
                                    <td>{{ $requestRecord->needed_by?->format('M d, Y') ?? 'Not set' }}</td>
                                    <td><span class="status-pill">{{ ucwords(str_replace('_', ' ', $requestRecord->status)) }}</span></td>
                                    <td class="text-end">
                                        <a href="{{ route('vendor.purchase-requests.show', $requestRecord) }}" class="btn btn-vendor-outline btn-sm">
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
