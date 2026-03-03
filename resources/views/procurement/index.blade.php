@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        {{-- ================= PAGE HEADER ================= --}}
        <div class="page-header d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="page-title mb-1">Procurements</h4>
                <p class="text-muted mb-0">
                    Manage procurements and their associated forms
                </p>
            </div>

            <a href="{{ route('procurements.create') }}" class="btn btn-primary btn-sm">
                <i class="feather-plus me-1"></i> New Procurement
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success mb-3">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger mb-3">{{ session('error') }}</div>
        @endif

        {{-- ================= EDUCATIVE INFO ================= --}}
        <div class="alert alert-info mb-3">
            <strong>Procurement Lifecycle:</strong>
            <span class="ms-2">Draft → Submitted → Approved → Published → Closed → Awarded</span>
            <div class="small mt-1 text-muted">
                Actions appear automatically based on the current status.
            </div>
        </div>

        {{-- ================= TABLE ================= --}}
        <div class="card shadow-sm">
            <div class="card-body">
                <x-data-table id="procurementsTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Reference</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th class="text-center">Status</th>
                            <th width="220" class="text-center">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($procurements as $p)
                            <tr>
                                <td class="ps-4 fw-semibold">
                                    {{ $p->reference_no ?? '—' }}
                                </td>

                                <td>
                                    <div class="fw-semibold">{{ $p->title }}</div>
                                    <small class="text-muted">
                                        Fiscal Year: {{ $p->fiscal_year ?? '—' }}
                                    </small>
                                </td>

                                <td>
                                    {{ $p->resource->name ?? '—' }}
                                </td>

                                <td class="text-center">
                                    @php
                                        $statusColors = [
                                            'draft' => 'secondary',
                                            'submitted' => 'warning',
                                            'rejected' => 'danger',
                                            'approved' => 'success',
                                            'published' => 'primary',
                                            'closed' => 'dark',
                                            'awarded' => 'success',
                                        ];
                                    @endphp
                                    <span class="badge bg-{{ $statusColors[$p->status] ?? 'secondary' }} {{ $p->status === 'submitted' ? 'text-dark' : '' }} px-3 py-1">
                                        {{ ucfirst($p->status) }}
                                    </span>
                                </td>

                                <td class="text-center">
                                    <div class="d-inline-flex gap-1 flex-wrap justify-content-center">
                                        <a href="{{ route('procurements.show', $p) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="feather-eye"></i>
                                        </a>

                                        @if ($p->status === 'draft')
                                            <form method="POST" action="{{ route('statusProcurement.submit', $p) }}" class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-warning" onclick="return confirm('Submit this procurement for approval?')">
                                                    Submit
                                                </button>
                                            </form>
                                        @endif

                                        @if ($p->status === 'rejected')
                                            <form method="POST" action="{{ route('statusProcurement.submit', $p) }}" class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-warning" onclick="return confirm('Resubmit this procurement for approval?')">
                                                    Resubmit
                                                </button>
                                            </form>
                                        @endif

                                        @if ($p->status === 'submitted')
                                            <form method="POST" action="{{ route('statusProcurement.approve', $p) }}" class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-success" onclick="return confirm('Approve this procurement?')">
                                                    Approve
                                                </button>
                                            </form>
                                            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $p->id }}">
                                                Reject
                                            </button>
                                        @endif

                                        @if ($p->status === 'approved')
                                            <form method="POST" action="{{ route('statusProcurement.publish', $p) }}" class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-primary" onclick="return confirm('Publish this procurement?')">
                                                    Publish
                                                </button>
                                            </form>
                                        @endif

                                        @if ($p->status === 'published')
                                            <form method="POST" action="{{ route('statusProcurement.close', $p) }}" class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-dark" onclick="return confirm('Close this procurement?')">
                                                    Close
                                                </button>
                                            </form>
                                        @endif

                                        @if ($p->status === 'closed')
                                            <form method="POST" action="{{ route('statusProcurement.award', $p) }}" class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-success" onclick="return confirm('Award this procurement? This is final.')">
                                                    Award
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-data-table>
            </div>
        </div>

    </div>

    {{-- ================= REJECTION MODALS ================= --}}
    @foreach ($procurements->where('status', 'submitted') as $p)
        <div class="modal fade" id="rejectModal{{ $p->id }}" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('statusProcurement.reject', $p) }}" class="w-100">
                    @csrf
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header">
                            <h5 class="fw-bold mb-0">
                                <i class="feather-x-circle text-danger me-2"></i>
                                Reject Procurement
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted mb-3">Rejecting: <strong>{{ $p->title }}</strong></p>
                            <label class="form-label fw-semibold">Reason for rejection <span class="text-danger">*</span></label>
                            <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="Enter the reason for rejection..."></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">
                                <i class="feather-x me-1"></i> Reject
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endsection
