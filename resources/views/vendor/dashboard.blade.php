@extends('layouts.vendor')

@section('title', 'Vendor Dashboard')

@section('content')
    @php
        use Illuminate\Support\Str;
    @endphp
    <div class="mb-4">
        <h3 class="mb-1">Welcome back</h3>
        <p class="text-muted mb-0">
            Category: {{ auth()->user()->vendor_category ?? 'Unassigned' }} ·
            Monitor your active procurements, updates, and clarifications.
        </p>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>There were errors with your request.</strong>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card vendor-card">
                <div class="card-body">
                    <div class="text-muted small">Total Applications</div>
                    <h4 class="mb-0">{{ $submissions->count() }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card vendor-card">
                <div class="card-body">
                    <div class="text-muted small">Applications Open</div>
                    <h4 class="mb-0">{{ $openCount }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card vendor-card">
                <div class="card-body">
                    <div class="text-muted small">Applications Closed</div>
                    <h4 class="mb-0">{{ $closedCount }}</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card vendor-card mb-4">
        <div class="card-body">
            <h5 class="mb-3">My Applications</h5>
            @if ($submissions->isEmpty())
                <p class="text-muted mb-0">You have not submitted any procurement applications yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Procurement Reference</th>
                                <th>Procurement</th>
                                <th>Status</th>
                                <th>Application Closes</th>
                                <th>Open</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($submissions as $submission)
                                <tr>
                                    <td>
                                        <span class="badge-soft">
                                            {{ $submission->procurement_reference ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td>{{ $submission->procurement?->title ?? 'N/A' }}</td>
                                    <td>
                                        <span class="status-pill">{{ ucfirst($submission->status ?? 'pending') }}</span>
                                    </td>
                                    <td>{{ $submission->application_end_date ?? 'N/A' }}</td>
                                    <td>
                                        @if ($submission->is_open)
                                            <span class="badge bg-success-subtle text-success">Open</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary">Closed</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($submission->is_open)
                                            <a href="{{ route('vendor.applications.edit', $submission) }}"
                                                class="btn btn-vendor btn-sm">
                                                Edit Application
                                            </a>
                                        @else
                                            <span class="text-muted small">Locked</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="card vendor-card mb-4">
        <div class="card-body">
            <h5 class="mb-3">Awarded Contracts</h5>
            @if ($awardedProcurements->isEmpty())
                <p class="text-muted mb-0">No awarded contracts yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Procurement</th>
                                <th>Reference</th>
                                <th>Awarded On</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($awardedProcurements as $awarded)
                                <tr>
                                    <td class="fw-semibold">{{ $awarded->title ?? 'N/A' }}</td>
                                    <td>{{ $awarded->reference_no ?? 'N/A' }}</td>
                                    <td>{{ $awarded->awarded_at?->format('M d, Y') ?? 'N/A' }}</td>
                                    <td>
                                        <a href="{{ route('vendor.invoices.index', ['procurement_id' => $awarded->id]) }}"
                                            class="btn btn-vendor btn-sm">
                                            Generate Invoice
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

    <div class="card vendor-card mb-4">
        <div class="card-body">
            <h5 class="mb-3">Latest Notifications</h5>
            @if ($notifications->isEmpty())
                <p class="text-muted mb-0">No notifications yet.</p>
            @else
                @foreach ($notifications as $notification)
                    @php
                        $data = $notification->data ?? [];
                        $type = $data['type'] ?? 'general';
                        $title = match ($type) {
                            'contract_awarded' => 'Contract Awarded',
                            'contract_terminated' => 'Contract Terminated',
                            default => 'Notification',
                        };
                        $message = match ($type) {
                            'contract_awarded' => 'Your contract has been awarded. You can now submit monthly invoices in your vendor portal.',
                            'contract_terminated' => 'Your contract has been terminated. Please review the termination details and contact the procurement team if needed.',
                            default => 'You have a new notification from the procurement team.',
                        };
                        $modalId = 'notificationModal' . $notification->id;
                    @endphp
                    <div class="border rounded p-3 mb-3">
                        <div class="fw-semibold">{{ $title }}</div>
                        <div class="text-muted small">
                            {{ $data['procurement_reference'] ?? '' }} · {{ $notification->created_at?->format('M d, Y') }}
                        </div>
                        <div class="text-muted small mt-2">{{ $message }}</div>
                        <div class="mt-3">
                            <button class="btn btn-vendor-outline btn-sm" data-bs-toggle="modal"
                                data-bs-target="#{{ $modalId }}">
                                View Details
                            </button>
                        </div>
                    </div>

                    <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-0 shadow">
                                <div class="modal-header">
                                    <h5 class="modal-title">{{ $title }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="mb-2">
                                        <strong>Procurement:</strong>
                                        {{ $data['procurement_title'] ?? 'N/A' }}
                                    </p>
                                    <p class="mb-2">
                                        <strong>Reference:</strong>
                                        {{ $data['procurement_reference'] ?? 'N/A' }}
                                    </p>
                                    <p class="mb-2">
                                        <strong>Date:</strong>
                                        {{ $notification->created_at?->format('M d, Y, H:i') ?? 'N/A' }}
                                    </p>
                                    @if (!empty($data['reason']))
                                        <div class="alert alert-danger mb-0">
                                            <strong>Reason:</strong> {{ $data['reason'] }}
                                        </div>
                                    @else
                                        <div class="alert alert-info mb-0">{{ $message }}</div>
                                    @endif
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
    <div class="card vendor-card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <h5 class="mb-1">Need Clarification?</h5>
                    <p class="text-muted mb-0">Send questions or request more details from the procurement team.</p>
                </div>
                <a class="btn btn-vendor" href="{{ route('vendor.clarifications') }}">Request Clarification</a>
            </div>
        </div>
    </div>
@endsection
