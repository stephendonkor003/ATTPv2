@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        {{-- ===================== PAGE HEADER ===================== --}}
        <div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h4 class="fw-bold mb-1">Planned Commitments</h4>
                <p class="text-muted mb-0">
                    Planned committed resources across projects, activities, and sub-activities
                </p>
            </div>

            @can('finance.commitments.create')
                <a href="{{ route('finance.commitments.create') }}" class="btn btn-primary">
                    <i class="feather-plus-circle me-1"></i>
                    New Planned Commitment
                </a>
            @endcan
        </div>

        {{-- ===================== FLASH MESSAGES ===================== --}}
        @if (session('success'))
            <div class="alert alert-success mt-3">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger mt-3">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- ===================== COMMITMENTS TABLE ===================== --}}
        <div class="card shadow-sm mt-4">
            <div class="card-body">

                <x-data-table
                    id="commitmentsTable"
                >
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Program</th>
                            <th>Allocation</th>
                            <th>Resource</th>
                            <th>Milestone Date</th>
                            <th class="text-end">Amount</th>
                            <th>Year</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($commitments as $c)
                            @php
                                $label = null;
                                if ($c->allocation_level === 'project') {
                                    $label = \App\Models\Project::find($c->allocation_id)?->name;
                                }
                                if ($c->allocation_level === 'activity') {
                                    $label = \App\Models\Activity::find($c->allocation_id)?->name;
                                }
                                if ($c->allocation_level === 'sub_activity') {
                                    $label = \App\Models\SubActivity::find($c->allocation_id)?->name;
                                }
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>

                                {{-- Program --}}
                                <td>
                                    {{ $c->programFunding->program->name ?? $c->programFunding->program_name ?? '—' }}
                                </td>

                                {{-- Allocation --}}
                                <td>
                                    <span class="badge mb-1 {{ $c->allocation_level === 'project' ? 'bg-primary' : ($c->allocation_level === 'activity' ? 'bg-warning text-dark' : 'bg-success') }}">
                                        {{ ucfirst(str_replace('_', ' ', $c->allocation_level)) }}
                                    </span>
                                    <div class="small text-muted mt-1">
                                        {{ $label ?? 'Allocation not found' }}
                                    </div>
                                </td>

                                {{-- Resource --}}
                                <td>
                                    @if ($c->purchaseRequest)
                                        <div class="fw-semibold">
                                            @can('finance.purchase_requests.view')
                                                <a href="{{ route('finance.purchase-requests.show', $c->purchaseRequest) }}">
                                                    {{ $c->purchaseRequest->reference_no }}
                                                </a>
                                            @else
                                                {{ $c->purchaseRequest->reference_no }}
                                            @endcan
                                        </div>
                                        <small class="text-muted">Purchase Request</small>
                                    @else
                                        <div class="fw-semibold">{{ $c->resource->name ?? '—' }}</div>
                                        <small class="text-muted">
                                            <span class="badge bg-info-subtle text-info">
                                                {{ $c->resourceCategory->name ?? '—' }}
                                            </span>
                                        </small>
                                    @endif
                                </td>

                                {{-- Milestone Date (earliest) --}}
                                @php
                                    $milestoneDate = $c->purchaseRequest?->items
                                        ? $c->purchaseRequest->items
                                            ->filter(fn($i) => !empty($i->milestone_date))
                                            ->sortBy('milestone_date')
                                            ->first()?->milestone_date
                                        : null;
                                @endphp
                                <td>
                                    {{ $milestoneDate?->format('Y-m-d') ?? '—' }}
                                </td>

                                {{-- Amount --}}
                                <td class="text-end fw-bold">
                                    <span class="text-muted me-1">
                                        {{ $c->programFunding->program->currency ?? '' }}
                                    </span>
                                    {{ number_format($c->commitment_amount, 2) }}
                                </td>


                                {{-- Year --}}
                                <td>
                                    <span class="badge bg-light text-dark">
                                        {{ $c->commitment_year }}
                                    </span>
                                </td>

                                {{-- Status --}}
                                <td>
                                    <span class="badge {{ $c->status === 'approved' ? 'bg-success' : ($c->status === 'submitted' ? 'bg-warning text-dark' : ($c->status === 'cancelled' ? 'bg-danger' : 'bg-secondary')) }}">
                                        {{ ucfirst($c->status) }}
                                    </span>
                                </td>

                                {{-- Action --}}
                                <td class="text-end">
                                    <a href="{{ route('finance.commitments.show', $c->id) }}"
                                        class="btn btn-sm btn-outline-primary"
                                        title="View Commitment">
                                        <i class="feather-eye"></i>
                                    </a>
                                    @can('finance.commitments.edit')
                                        @if ($c->status === 'draft')
                                            <a href="{{ route('finance.commitments.edit', $c->id) }}"
                                                class="btn btn-sm btn-outline-secondary"
                                                title="Edit Commitment">
                                                <i class="feather-edit-2"></i>
                                            </a>
                                        @endif
                                    @endcan
                                    @can('finance.commitments.delete')
                                        @if ($c->status === 'draft')
                                            <form action="{{ route('finance.commitments.destroy', $c->id) }}"
                                                method="POST" class="d-inline"
                                                onsubmit="return confirm('Delete this draft commitment?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="btn btn-sm btn-outline-danger"
                                                    title="Delete Commitment">
                                                    <i class="feather-trash-2"></i>
                                                </button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>

                            </tr>
                        @endforeach
                    </tbody>
                </x-data-table>

            </div>
        </div>

    </div>
@endsection
