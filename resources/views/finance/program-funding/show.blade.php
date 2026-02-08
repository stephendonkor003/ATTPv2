@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        {{-- ================= HEADER ================= --}}
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">Program Funding Details</h4>
                <p class="text-muted mb-0">
                    Funding governance record and supporting documentation
                </p>
            </div>

            <div class="d-flex gap-2">
                @can('finance.program_funding.edit')
                    <a href="{{ route('finance.program-funding.edit', $programFunding) }}" class="btn btn-warning">
                        <i class="feather-edit me-1"></i> Edit
                    </a>
                @endcan

                <a href="{{ route('finance.program-funding.index') }}" class="btn btn-light">
                    <i class="feather-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        {{-- ================= SUMMARY CARDS ================= --}}
        <div class="row g-3 mb-4">

            <div class="col-md-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <small class="text-muted">Program</small>
                        <h6 class="fw-bold mb-0">
                            {{ $programFunding->program_name ?? (optional($programFunding->program)->name ?? '—') }}
                        </h6>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <small class="text-muted">Funder</small>
                        <h6 class="fw-bold mb-0">
                            {{ optional($programFunding->funder)->name ?? '—' }}
                        </h6>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <small class="text-muted">Governance Node</small>
                        <h6 class="fw-bold mb-0">
                            {{ optional($programFunding->governanceNode)->name ?? '-' }}
                        </h6>
                        <div class="small text-muted">
                            {{ optional(optional($programFunding->governanceNode)->level)->name ?? '' }}
                        </div>
                    </div>
                </div>
            </div>

            @php
                $statusClass =
                    [
                        'draft' => 'bg-warning',
                        'submitted' => 'bg-primary',
                        'approved' => 'bg-success',
                        'rejected' => 'bg-danger',
                    ][$programFunding->status] ?? 'bg-secondary';
            @endphp

            <div class="col-md-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <small class="text-muted">Status</small>
                        <div>
                            <span class="badge {{ $statusClass }}">
                                {{ ucfirst($programFunding->status) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        {{-- ================= FUNDING INFORMATION ================= --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Funding Information</h6>

                <div class="row g-3">
                    <div class="col-md-3">
                        <small class="text-muted">Funding Type</small>
                        <div class="fw-semibold">
                            {{ ucfirst($programFunding->funding_type) }}
                        </div>
                    </div>

                    <div class="col-md-3">
                        <small class="text-muted">Approved Amount</small>
                        <div class="fw-semibold">
                            {{ number_format($programFunding->approved_amount, 2) }}
                            {{ $programFunding->currency }}
                        </div>
                    </div>

                    <div class="col-md-3">
                        <small class="text-muted">Funding Period</small>
                        <div class="fw-semibold">
                            {{ $programFunding->start_year }} - {{ $programFunding->end_year }}
                        </div>
                    </div>

                    <div class="col-md-3">
                        <small class="text-muted">Submitted By</small>
                        <div class="fw-semibold">
                            {{ optional($programFunding->creator)->name ?? 'System' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if ($programFunding->status === 'rejected')
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-2 text-danger">Rejection Reason</h6>
                    <p class="mb-2">{{ $programFunding->rejection_reason ?? 'No reason provided.' }}</p>
                    <div class="small text-muted">
                        Rejected by: {{ optional($programFunding->rejector)->name ?? 'System' }}
                        @if ($programFunding->rejected_at)
                            on {{ $programFunding->rejected_at->format('d M Y, H:i') }}
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- ================= AU STRATEGIC ALIGNMENT ================= --}}
        @if ($programFunding->is_continental_initiative ||
             $programFunding->memberStates->count() ||
             $programFunding->regionalBlocks->count() ||
             $programFunding->aspirations->count() ||
             $programFunding->goals->count() ||
             $programFunding->flagshipProjects->count())
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">
                        <i class="feather-globe me-1"></i> AU Strategic Alignment
                    </h6>

                    <div class="row g-4">
                        {{-- Continental Initiative / Member States --}}
                        <div class="col-md-6">
                            <small class="text-muted d-block mb-2">Beneficiary Member States</small>
                            @if ($programFunding->is_continental_initiative)
                                <span class="badge bg-success fs-6">
                                    <i class="feather-globe me-1"></i> Continental Initiative
                                </span>
                                <div class="small text-muted mt-1">Applies to all 55 AU member states</div>
                            @elseif ($programFunding->memberStates->count())
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach ($programFunding->memberStates as $state)
                                        <span class="badge bg-light text-dark border">{{ $state->name }}</span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-muted">Not specified</span>
                            @endif
                        </div>

                        {{-- Regional Blocks --}}
                        <div class="col-md-6">
                            <small class="text-muted d-block mb-2">Regional Blocks (RECs)</small>
                            @if ($programFunding->regionalBlocks->count())
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach ($programFunding->regionalBlocks as $block)
                                        <span class="badge bg-info text-dark">
                                            {{ $block->abbreviation ?? $block->name }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-muted">Not specified</span>
                            @endif
                        </div>

                        {{-- Aspirations --}}
                        <div class="col-md-6">
                            <small class="text-muted d-block mb-2">Agenda 2063 Aspirations</small>
                            @if ($programFunding->aspirations->count())
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach ($programFunding->aspirations as $aspiration)
                                        <span class="badge bg-primary">
                                            Aspiration {{ $aspiration->number }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-muted">Not specified</span>
                            @endif
                        </div>

                        {{-- Goals --}}
                        <div class="col-md-6">
                            <small class="text-muted d-block mb-2">Agenda 2063 Goals</small>
                            @if ($programFunding->goals->count())
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach ($programFunding->goals as $goal)
                                        <span class="badge bg-secondary">
                                            Goal {{ $goal->number }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-muted">Not specified</span>
                            @endif
                        </div>

                        {{-- Flagship Projects --}}
                        @if ($programFunding->flagshipProjects->count())
                            <div class="col-md-12">
                                <small class="text-muted d-block mb-2">AU Flagship Projects</small>
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach ($programFunding->flagshipProjects as $project)
                                        <span class="badge bg-warning text-dark">
                                            #{{ $project->number }}: {{ $project->name }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- ================= SUPPORTING DOCUMENTS ================= --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                @can('finance.program_funding.view')
                    <h6 class="fw-bold mb-3">Supporting Documents</h6>

                    @if ($programFunding->documents->count())
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Document Type</th>
                                        <th>Description</th>
                                        <th>Submitted Date</th>
                                        <th>System Recieved Date</th>
                                        <th class="text-center">File</th>
                                    </tr>
                                </thead>
                                <tbody>

                                    @foreach ($programFunding->documents as $doc)
                                        <tr>
                                            <td>{{ $doc->document_type ?? '—' }}</td>
                                            <td>{{ $doc->description ?? '—' }}</td>
                                            <td>{{ $doc->created_at ?? '—' }}</td>
                                            <td>{{ $doc->created_at ?? '—' }}</td>
                                            <td class="text-center">
                                                <a href="{{ route('finance.program-funding.documents.download', [$programFunding, $doc]) }}" target="_blank"
                                                    class="btn btn-sm btn-outline-primary">
                                                    <i class="feather-download me-1"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach

                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">
                            No supporting documents uploaded.
                        </p>
                    @endif
                @endcan
            </div>
        </div>

        {{-- ================= GOVERNANCE ACTIONS ================= --}}
        <div class="card shadow-sm border-0">
            <div class="card-body d-flex justify-content-between align-items-center">

                <div class="text-muted small">
                    Funding records must be approved before budgets or commitments can be created.
                </div>

                <div class="d-flex gap-2">

                    @if ($programFunding->status === 'draft')
                        @can('finance.program_funding.submit')
                            <form method="POST" action="{{ route('finance.program-funding.submit', $programFunding) }}">
                                @csrf
                                <button class="btn btn-primary">
                                    <i class="feather-send me-1"></i> Submit for Approval
                                </button>
                            </form>
                        @endcan
                    @endif

                    @if ($programFunding->status === 'rejected')
                        @can('finance.program_funding.submit')
                            <form method="POST" action="{{ route('finance.program-funding.submit', $programFunding) }}">
                                @csrf
                                <button class="btn btn-primary">
                                    <i class="feather-rotate-ccw me-1"></i> Resubmit
                                </button>
                            </form>
                        @endcan
                    @endif

                    @if ($programFunding->status === 'submitted')
                        @can('finance.program_funding.approve')
                            <form method="POST" action="{{ route('finance.program-funding.approve', $programFunding) }}">
                                @csrf
                                <button class="btn btn-success">
                                    <i class="feather-check me-1"></i> Approve
                                </button>
                            </form>

                            <button class="btn btn-danger" type="button" data-bs-toggle="collapse"
                                data-bs-target="#rejectReasonForm" aria-expanded="false" aria-controls="rejectReasonForm">
                                <i class="feather-x me-1"></i> Reject
                            </button>
                        @endcan
                    @endif

                </div>
            </div>
        </div>

        @if ($programFunding->status === 'submitted')
            @can('finance.program_funding.approve')
                <div class="collapse mt-3" id="rejectReasonForm">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <h6 class="fw-bold text-danger mb-2">Rejection Reason</h6>
                            <form method="POST" action="{{ route('finance.program-funding.reject', $programFunding) }}">
                                @csrf
                                <div class="mb-3">
                                    <textarea name="rejection_reason" class="form-control" rows="3"
                                        placeholder="Provide a reason for rejection" required></textarea>
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-danger">
                                        <i class="feather-x me-1"></i> Confirm Rejection
                                    </button>
                                    <button class="btn btn-light" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#rejectReasonForm">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endcan
        @endif

    </div>
@endsection
