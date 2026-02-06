@extends('layouts.app')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold mb-1">Vendor Deliverables</h4>
                <p class="text-muted mb-0">
                    Track deliverables and milestones across awarded procurements with approvals and timelines.
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('procurement.deliverables.sheet') }}" class="btn btn-outline-primary">
                    View Process Sheet
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="row g-3 mb-4">
            <div class="col-md-2">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Total</div>
                        <div class="fs-4 fw-bold">{{ $counts['total'] ?? 0 }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Pending</div>
                        <div class="fs-4 fw-bold">{{ $counts['pending'] ?? 0 }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Completed</div>
                        <div class="fs-4 fw-bold">{{ $counts['completed'] ?? 0 }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Cancelled</div>
                        <div class="fs-4 fw-bold">{{ $counts['cancelled'] ?? 0 }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Awaiting Admin</div>
                        <div class="fs-4 fw-bold">{{ $counts['awaiting_admin'] ?? 0 }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Awaiting Vendor</div>
                        <div class="fs-4 fw-bold">{{ $counts['awaiting_vendor'] ?? 0 }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('procurement.deliverables.index') }}" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Procurement</label>
                        <select name="procurement_id" class="form-control">
                            <option value="">All Procurements</option>
                            @foreach ($procurements as $procurement)
                                <option value="{{ $procurement->id }}"
                                    @selected(($filters['procurement_id'] ?? '') === $procurement->id)>
                                    {{ $procurement->reference_no ?? 'N/A' }} - {{ $procurement->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Vendor</label>
                        <select name="vendor_id" class="form-control">
                            <option value="">All Vendors</option>
                            @foreach ($vendors as $vendor)
                                <option value="{{ $vendor->id }}"
                                    @selected(($filters['vendor_id'] ?? '') === $vendor->id)>
                                    {{ $vendor->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-control">
                            <option value="">All Statuses</option>
                            @foreach (['pending', 'in_progress', 'completed', 'cancelled'] as $statusOption)
                                <option value="{{ $statusOption }}"
                                    @selected(($filters['status'] ?? '') === $statusOption)>
                                    {{ ucwords(str_replace('_', ' ', $statusOption)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Type</label>
                        <select name="type" class="form-control">
                            <option value="">All Types</option>
                            @foreach (['deliverable', 'milestone'] as $typeOption)
                                <option value="{{ $typeOption }}"
                                    @selected(($filters['type'] ?? '') === $typeOption)>
                                    {{ ucfirst($typeOption) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button class="btn btn-primary w-100">Filter</button>
                        <a href="{{ route('procurement.deliverables.index') }}" class="btn btn-outline-secondary w-100">
                            Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <x-data-table id="deliverablesTable">
                    <thead class="table-light">
                        <tr>
                            <th>Procurement</th>
                            <th>Vendor</th>
                            <th>Deliverable</th>
                            <th>Timeline</th>
                            <th class="text-end">Amount</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Approvals</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($deliverables as $deliverable)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $deliverable->procurement?->title ?? 'N/A' }}</div>
                                    <small class="text-muted">{{ $deliverable->procurement?->reference_no ?? 'N/A' }}</small>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $deliverable->vendor?->name ?? 'N/A' }}</div>
                                    <small class="text-muted">{{ $deliverable->vendor?->email ?? '' }}</small>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $deliverable->title }}</div>
                                    <span class="badge bg-light text-dark">{{ ucfirst($deliverable->type) }}</span>
                                </td>
                                <td>
                                    <div class="small">
                                        {{ $deliverable->timeline_start?->format('M d, Y') ?? '—' }}
                                        -
                                        {{ $deliverable->timeline_end?->format('M d, Y') ?? '—' }}
                                    </div>
                                    <div class="text-muted small">
                                        Seq: {{ $deliverable->sequence }}
                                    </div>
                                </td>
                                <td class="text-end">
                                    {{ $deliverable->amount ? number_format($deliverable->amount, 2) : '—' }}
                                    {{ $deliverable->currency ?? '' }}
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $deliverable->status === 'completed' ? 'success' : ($deliverable->status === 'cancelled' ? 'danger' : 'warning') }}">
                                        {{ ucwords(str_replace('_', ' ', $deliverable->status)) }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="small">
                                        Vendor:
                                        <span class="badge bg-{{ $deliverable->vendor_approval_status === 'approved' ? 'success' : ($deliverable->vendor_approval_status === 'rejected' ? 'danger' : 'secondary') }}">
                                            {{ ucfirst($deliverable->vendor_approval_status) }}
                                        </span>
                                    </div>
                                    <div class="small mt-1">
                                        Admin:
                                        <span class="badge bg-{{ $deliverable->admin_approval_status === 'approved' ? 'success' : ($deliverable->admin_approval_status === 'rejected' ? 'danger' : 'secondary') }}">
                                            {{ ucfirst($deliverable->admin_approval_status) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex flex-column gap-2">
                                        <form method="POST" action="{{ route('procurement.deliverables.status', $deliverable) }}">
                                            @csrf
                                            <div class="input-group input-group-sm">
                                                <select name="status" class="form-select">
                                                    @foreach (['pending', 'in_progress', 'completed', 'cancelled'] as $statusOption)
                                                        <option value="{{ $statusOption }}"
                                                            @selected($deliverable->status === $statusOption)>
                                                            {{ ucwords(str_replace('_', ' ', $statusOption)) }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <button class="btn btn-outline-primary">Update</button>
                                            </div>
                                        </form>

                                        @if ($deliverable->admin_approval_status === 'pending')
                                            <form method="POST" action="{{ route('procurement.deliverables.approve', $deliverable) }}">
                                                @csrf
                                                <button class="btn btn-sm btn-success w-100">Approve</button>
                                            </form>

                                            <form method="POST" action="{{ route('procurement.deliverables.reject', $deliverable) }}">
                                                @csrf
                                                <div class="input-group input-group-sm">
                                                    <input type="text" name="reason" class="form-control"
                                                        placeholder="Rejection reason" required>
                                                    <button class="btn btn-outline-danger">Reject</button>
                                                </div>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-data-table>

                <div class="mt-3">
                    {{ $deliverables->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
