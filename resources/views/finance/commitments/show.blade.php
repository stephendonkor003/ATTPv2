@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        {{-- ===================== PAGE HEADER ===================== --}}
        <div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h4 class="fw-bold mb-1">
                    Budget Commitment Details
                </h4>
                <p class="text-muted mb-0">
                    Commitment ID: #{{ $commitment->id }}
                </p>
            </div>

            <a href="{{ route('finance.commitments.index') }}" class="btn btn-light">
                <i class="feather-arrow-left me-1"></i> Back to Commitments
            </a>
        </div>

        {{-- ===================== MAIN CARD ===================== --}}
        <div class="card shadow-sm border-0 mt-4">
            <div class="card-body">

                {{-- ===================== STATUS & SUMMARY ===================== --}}
                <div class="row g-3 mb-4">

                    <div class="col-md-3">
                        <strong>Status</strong><br>
                        <span
                            class="badge
                        {{ $commitment->status === 'approved'
                            ? 'bg-success'
                            : ($commitment->status === 'submitted'
                                ? 'bg-warning text-dark'
                                : ($commitment->status === 'cancelled'
                                    ? 'bg-danger'
                                    : 'bg-secondary')) }}">
                            {{ ucfirst($commitment->status) }}
                        </span>
                    </div>

                    <div class="col-md-3">
                        <strong>Commitment Year</strong><br>
                        <span class="badge bg-light text-dark">
                            {{ $commitment->commitment_year }}
                        </span>
                    </div>

                    <div class="col-md-3">
                        <strong>Amount</strong><br>
                        <span class="fw-bold text-primary">
                            {{ $commitment->programFunding->program->currency ?? '' }}
                            {{ number_format($commitment->commitment_amount, 2) }}
                        </span>
                    </div>


                    <div class="col-md-3">
                        <strong>Allocation Level</strong><br>
                        <span
                            class="badge
                        {{ $commitment->allocation_level === 'project'
                            ? 'bg-primary'
                            : ($commitment->allocation_level === 'activity'
                                ? 'bg-warning text-dark'
                                : 'bg-success') }}">
                            {{ ucfirst(str_replace('_', ' ', $commitment->allocation_level)) }}
                        </span>
                    </div>

                </div>

                <hr>

                {{-- ===================== PROGRAM & FUNDING ===================== --}}
                <h6 class="fw-bold text-primary mb-3">Program & Funding Context</h6>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <strong>Program</strong><br>
                        {{ $commitment->programFunding->program->name ?? '—' }}
                    </div>

                    <div class="col-md-6">
                        <strong>Program Funding (Program)</strong><br>
                        {{ $commitment->programFunding->program->name ?? '—' }}
                    </div>


                </div>

                <hr>

                {{-- ===================== ALLOCATION DETAILS ===================== --}}
                <h6 class="fw-bold text-warning mb-3">Allocation Details</h6>

                <div class="row g-3 mb-4">
                    <div class="col-md-12">
                        <strong>Allocated Item</strong><br>

                        @php
                            $allocationLabel = null;

                            if ($commitment->allocation_level === 'project') {
                                $allocationLabel = \App\Models\Project::find($commitment->allocation_id)?->name;
                            }

                            if ($commitment->allocation_level === 'activity') {
                                $allocationLabel = \App\Models\Activity::find($commitment->allocation_id)?->name;
                            }

                            if ($commitment->allocation_level === 'sub_activity') {
                                $allocationLabel = \App\Models\SubActivity::find($commitment->allocation_id)?->name;
                            }
                        @endphp

                        <span class="fw-semibold">
                            {{ $allocationLabel ?? 'Allocation not found' }}
                        </span>
                    </div>
                </div>

                <hr>

                {{-- ===================== RESOURCE / PURCHASE REQUEST DETAILS ===================== --}}
                @if ($commitment->purchaseRequest)
                    <h6 class="fw-bold text-success mb-3">Purchase Request</h6>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <strong>Reference</strong><br>
                            @can('finance.purchase_requests.view')
                                <a href="{{ route('finance.purchase-requests.show', $commitment->purchaseRequest) }}">
                                    {{ $commitment->purchaseRequest->reference_no }}
                                </a>
                            @else
                                {{ $commitment->purchaseRequest->reference_no }}
                            @endcan
                        </div>

                        <div class="col-md-6">
                            <strong>Total Amount</strong><br>
                            <span class="fw-bold text-primary">
                                {{ $commitment->purchaseRequest->currency ?? $commitment->programFunding->program->currency ?? '' }}
                                {{ number_format((float) $commitment->purchaseRequest->total_amount, 2) }}
                            </span>
                        </div>
                    </div>

                    @if (!empty($commitment->description))
                        <div class="mb-3">
                            <strong>Description</strong><br>
                            <span class="text-muted">{{ $commitment->description }}</span>
                        </div>
                    @endif

                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Category</th>
                                    <th>Resource Item</th>
                                    <th>Milestone / Description</th>
                                    <th>Milestone Date</th>
                                    <th class="text-end" style="width: 160px;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($commitment->purchaseRequest->items as $item)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $item->resourceCategory->name ?? '—' }}</td>
                                        <td>{{ $item->resource->name ?? '—' }}</td>
                                        <td>{{ $item->milestone ?? '—' }}</td>
                                        <td>{{ $item->milestone_date?->format('Y-m-d') ?? '—' }}</td>
                                        <td class="text-end fw-semibold">
                                            {{ $commitment->purchaseRequest->currency ?? $commitment->programFunding->program->currency ?? '' }}
                                            {{ number_format((float) $item->amount, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <h6 class="fw-bold text-success mb-3">Resource Commitment</h6>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <strong>Resource Category</strong><br>
                            {{ $commitment->resourceCategory->name ?? '—' }}
                        </div>

                        <div class="col-md-6">
                            <strong>Resource Item</strong><br>
                            {{ $commitment->resource->name ?? '—' }}
                        </div>
                    </div>
                @endif

                <hr>

                {{-- ===================== AUDIT INFORMATION ===================== --}}
                <h6 class="fw-bold text-secondary mb-3">Audit Information</h6>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <strong>Created By</strong><br>
                        {{ $commitment->creator->name ?? '—' }}
                        @if (!empty($commitment->creator->email))
                            <br><small class="text-muted">{{ $commitment->creator->email }}</small>
                        @endif
                    </div>


                    <div class="col-md-4">
                        <strong>Created At</strong><br>
                        {{ $commitment->created_at }}
                    </div>

                    <div class="col-md-4">
                        <strong>Approved By</strong><br>
                        {{ $commitment->approver->name ?? '—' }}
                        @if (!empty($commitment->approver?->email))
                            <br><small class="text-muted">{{ $commitment->approver->email }}</small>
                        @endif
                    </div>


                    <div class="col-md-4">
                        <strong>Approved At</strong><br>
                        {{ $commitment->approved_at ?? '—' }}
                    </div>
                </div>

                <hr>

                {{-- ===================== ACTIONS ===================== --}}
                <div class="d-flex flex-wrap gap-2">

                    @if ($commitment->status === 'draft')
                        @can('finance.commitments.submit')
                            <form method="POST" action="{{ route('finance.commitments.submit', $commitment) }}">
                                @csrf
                                <button class="btn btn-warning">
                                    <i class="feather-send me-1"></i>
                                    Submit for Approval
                                </button>
                            </form>
                        @endcan
                    @endif
                    @can('finance.commitments.approve')
                        @if ($commitment->status === 'submitted')
                            <form method="POST" action="{{ route('finance.commitments.approve', $commitment) }}">
                                @csrf
                                <button class="btn btn-success">
                                    <i class="feather-check-circle me-1"></i>
                                    Approve Commitment
                                </button>
                            </form>
                        @endif

                        @if (in_array($commitment->status, ['draft', 'submitted']))
                            <form method="POST" action="{{ route('finance.commitments.cancel', $commitment) }}">
                                @csrf
                                <button class="btn btn-danger">
                                    <i class="feather-x-circle me-1"></i>
                                    Cancel Commitment
                                </button>
                            </form>
                        @endif
                    @endcan
                </div>

            </div>
        </div>

    </div>
@endsection
