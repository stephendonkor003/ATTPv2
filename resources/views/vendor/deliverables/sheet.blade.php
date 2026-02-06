@extends('layouts.vendor')

@section('title', 'Deliverables Sheet')

@section('content')
    <div class="mb-4">
        <h3 class="mb-1">Deliverables Process Sheet</h3>
        <p class="text-muted mb-0">
            A wide-format view of timelines, approvals, and progress for all deliverables.
        </p>
    </div>

    <div class="d-flex justify-content-end mb-3">
        <a href="{{ route('vendor.deliverables.index') }}" class="btn btn-vendor-outline btn-sm">
            Back to Deliverables
        </a>
    </div>

    <div class="card vendor-card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle" style="min-width: 1200px;">
                    <thead>
                        <tr>
                            <th>Procurement</th>
                            <th>Type</th>
                            <th>Title</th>
                            <th>Start</th>
                            <th>End</th>
                            <th>Duration (days)</th>
                            <th class="text-end">Amount</th>
                            <th>Status</th>
                            <th>Your Approval</th>
                            <th>Admin Approval</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($deliverables as $deliverable)
                            @php
                                $start = $deliverable->timeline_start;
                                $end = $deliverable->timeline_end;
                                $duration = $start && $end ? $start->diffInDays($end) + 1 : null;
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $deliverable->procurement?->title ?? 'N/A' }}</div>
                                    <small class="text-muted">{{ $deliverable->procurement?->reference_no ?? 'N/A' }}</small>
                                </td>
                                <td>{{ ucfirst($deliverable->type) }}</td>
                                <td>{{ $deliverable->title }}</td>
                                <td>{{ $start?->format('Y-m-d') ?? '—' }}</td>
                                <td>{{ $end?->format('Y-m-d') ?? '—' }}</td>
                                <td>{{ $duration ?? '—' }}</td>
                                <td class="text-end">
                                    {{ $deliverable->amount ? number_format($deliverable->amount, 2) : '—' }}
                                    {{ $deliverable->currency ?? '' }}
                                </td>
                                <td>{{ ucwords(str_replace('_', ' ', $deliverable->status)) }}</td>
                                <td>{{ ucfirst($deliverable->vendor_approval_status) }}</td>
                                <td>{{ ucfirst($deliverable->admin_approval_status) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted">No deliverables recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
