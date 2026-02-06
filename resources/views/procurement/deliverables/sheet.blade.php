@extends('layouts.app')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold mb-1">Deliverables Process Sheet</h4>
                <p class="text-muted mb-0">
                    Wide-format sheet showing timelines, approvals, and status progression.
                </p>
            </div>
            <div>
                <a href="{{ route('procurement.deliverables.index') }}" class="btn btn-outline-secondary">
                    Back to Deliverables
                </a>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle" style="min-width: 1400px;">
                        <thead class="table-light">
                            <tr>
                                <th>Procurement Ref</th>
                                <th>Procurement</th>
                                <th>Vendor</th>
                                <th>Type</th>
                                <th>Title</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Duration (days)</th>
                                <th class="text-end">Amount</th>
                                <th>Status</th>
                                <th>Vendor Approval</th>
                                <th>Admin Approval</th>
                                <th>Completed At</th>
                                <th>Cancelled At</th>
                                <th>Notes</th>
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
                                    <td>{{ $deliverable->procurement?->reference_no ?? 'N/A' }}</td>
                                    <td>{{ $deliverable->procurement?->title ?? 'N/A' }}</td>
                                    <td>{{ $deliverable->vendor?->name ?? 'N/A' }}</td>
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
                                    <td>{{ $deliverable->completed_at?->format('Y-m-d') ?? '—' }}</td>
                                    <td>{{ $deliverable->cancelled_at?->format('Y-m-d') ?? '—' }}</td>
                                    <td>{{ $deliverable->notes ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="15" class="text-center text-muted">No deliverables recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
