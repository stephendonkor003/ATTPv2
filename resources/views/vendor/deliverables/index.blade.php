@extends('layouts.vendor')

@section('title', 'Vendor Deliverables')

@section('content')
    <div class="mb-4">
        <h3 class="mb-1">Vendor Deliverables</h3>
        <p class="text-muted mb-0">
            Upload and track agreed deliverables and milestones for awarded procurements.
        </p>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if (session('import_errors'))
        <div class="alert alert-warning">
            <div class="fw-semibold mb-1">Import issues</div>
            <ul class="mb-0">
                @foreach (session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card vendor-card h-100">
                <div class="card-body">
                    <div class="text-muted small">Total</div>
                    <div class="fs-4 fw-bold">{{ $counts['total'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card vendor-card h-100">
                <div class="card-body">
                    <div class="text-muted small">Pending</div>
                    <div class="fs-4 fw-bold">{{ $counts['pending'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card vendor-card h-100">
                <div class="card-body">
                    <div class="text-muted small">Completed</div>
                    <div class="fs-4 fw-bold">{{ $counts['completed'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card vendor-card h-100">
                <div class="card-body">
                    <div class="text-muted small">Cancelled</div>
                    <div class="fs-4 fw-bold">{{ $counts['cancelled'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card vendor-card h-100">
                <div class="card-body">
                    <div class="text-muted small">Awaiting Admin</div>
                    <div class="fs-4 fw-bold">{{ $counts['awaiting_admin'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card vendor-card h-100">
                <div class="card-body">
                    <div class="text-muted small">Awaiting You</div>
                    <div class="fs-4 fw-bold">{{ $counts['awaiting_vendor'] ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card vendor-card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                <div>
                    <h5 class="mb-1">Upload Deliverables</h5>
                    <p class="text-muted mb-0">
                        Use the template to submit deliverables and milestones for an awarded procurement.
                    </p>
                </div>
                <a href="{{ route('vendor.deliverables.template') }}" class="btn btn-vendor-outline btn-sm">
                    Download Template
                </a>
            </div>
            @if ($awardedProcurements->isEmpty())
                <p class="text-muted mb-0">No awarded procurements available for deliverables upload yet.</p>
            @else
                <form method="POST" action="{{ route('vendor.deliverables.import') }}" enctype="multipart/form-data"
                    class="row g-3">
                    @csrf
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Awarded Procurement</label>
                        <select name="procurement_id" class="form-control" required>
                            <option value="">Select procurement</option>
                            @foreach ($awardedProcurements as $procurement)
                                <option value="{{ $procurement->id }}">
                                    {{ $procurement->reference_no ?? 'N/A' }} - {{ $procurement->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Upload File</label>
                        <input type="file" name="file" accept=".xlsx,.xls,.csv" class="form-control" required>
                        <small class="text-muted">Supported: .xlsx, .xls, .csv</small>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-vendor w-100">Upload</button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Deliverables Overview</h5>
        <a href="{{ route('vendor.deliverables.sheet') }}" class="btn btn-light btn-sm">
            View Process Sheet
        </a>
    </div>

    <div class="card vendor-card">
        <div class="card-body">
            @if ($deliverables->isEmpty())
                <p class="text-muted mb-0">No deliverables uploaded yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Procurement</th>
                                <th>Deliverable</th>
                                <th>Timeline</th>
                                <th class="text-end">Amount</th>
                                <th>Status</th>
                                <th>Approvals</th>
                                <th>Actions</th>
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
                                    <td>
                                        <span class="status-pill text-capitalize">
                                            {{ str_replace('_', ' ', $deliverable->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="small">
                                            You:
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
                                    <td>
                                        <div class="d-flex flex-column gap-2">
                                            @if ($deliverable->vendor_approval_status !== 'approved')
                                                <form method="POST" action="{{ route('vendor.deliverables.approve', $deliverable) }}">
                                                    @csrf
                                                    <button class="btn btn-vendor-outline btn-sm w-100">
                                                        Approve
                                                    </button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('vendor.deliverables.status', $deliverable) }}">
                                                @csrf
                                                <div class="input-group input-group-sm">
                                                    <select name="status" class="form-select"
                                                        {{ $deliverable->isAgreed() ? '' : 'disabled' }}>
                                                        @foreach (['pending', 'in_progress', 'completed', 'cancelled'] as $statusOption)
                                                            <option value="{{ $statusOption }}"
                                                                @selected($deliverable->status === $statusOption)>
                                                                {{ ucwords(str_replace('_', ' ', $statusOption)) }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <button class="btn btn-outline-primary" {{ $deliverable->isAgreed() ? '' : 'disabled' }}>
                                                        Update
                                                    </button>
                                                </div>
                                            </form>
                                            @if (!$deliverable->isAgreed())
                                                <small class="text-muted">Awaiting both approvals.</small>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    {{ $deliverables->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
