@extends('layouts.vendor')

@section('title', 'My Procurement Submissions')

@section('content')
    <div class="mb-4">
        <h3 class="mb-1">My Procurement Submissions</h3>
        <p class="text-muted mb-0">View all submitted applications and update those still open.</p>
    </div>

    <div class="card vendor-card">
        <div class="card-body">
            @if ($submissions->isEmpty())
                <p class="text-muted mb-0">No submissions yet.</p>
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
@endsection
