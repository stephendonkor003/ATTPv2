@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        {{-- ================= PAGE HEADER ================= --}}
        <div class="page-header d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="page-title mb-1">Procurement Plans</h4>
                <p class="text-muted mb-0">
                    Manage full structured procurement plans for projects
                </p>
            </div>

            <a href="{{ route('procurement.plans.create') }}" class="btn btn-primary btn-sm">
                <i class="feather-plus me-1"></i> New Plan
            </a>
        </div>

        {{-- ================= STATS CARDS ================= --}}
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="avatar-sm bg-primary bg-opacity-10 rounded">
                                    <i class="feather-file-text text-primary fs-4 d-flex align-items-center justify-content-center h-100"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="mb-0">{{ $plans->count() }}</h5>
                                <p class="text-muted mb-0 small">Total Plans</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="avatar-sm bg-success bg-opacity-10 rounded">
                                    <i class="feather-check-circle text-success fs-4 d-flex align-items-center justify-content-center h-100"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="mb-0">{{ $plans->where('is_launched', true)->count() }}</h5>
                                <p class="text-muted mb-0 small">Launched</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="avatar-sm bg-warning bg-opacity-10 rounded">
                                    <i class="feather-clock text-warning fs-4 d-flex align-items-center justify-content-center h-100"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="mb-0">{{ $plans->where('is_launched', false)->count() }}</h5>
                                <p class="text-muted mb-0 small">Not Launched</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="avatar-sm bg-info bg-opacity-10 rounded">
                                    <i class="feather-dollar-sign text-info fs-4 d-flex align-items-center justify-content-center h-100"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="mb-0">${{ number_format($plans->sum('estimated_budget'), 0) }}</h5>
                                <p class="text-muted mb-0 small">Total Budget</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ================= TABLE ================= --}}
        <div class="card shadow-sm">
            <div class="card-body">
                <x-data-table id="procurementPlansTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Code</th>
                            <th>Title</th>
                            <th>Activity</th>
                            <th>Method</th>
                            <th class="text-center">Stage</th>
                            <th class="text-center">Launched</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th width="140" class="text-center">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($plans as $plan)
                            <tr>
                                <td class="ps-4">
                                    <span class="fw-semibold text-primary">{{ $plan->procurement_code }}</span>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ Str::limit($plan->title, 30) }}</div>
                                    @if($plan->subActivity)
                                        <small class="text-muted">{{ $plan->subActivity->name }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($plan->activity)
                                        <span class="badge bg-light text-dark">{{ Str::limit($plan->activity->name, 20) }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($plan->methodPlanned)
                                        <span class="badge bg-info">{{ Str::limit($plan->methodPlanned->method_name, 15) }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($plan->stage)
                                        <span class="badge bg-secondary">{{ $plan->stage->stage_name }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($plan->is_launched)
                                        <span class="badge bg-success px-3 py-1">Yes</span>
                                    @else
                                        <span class="badge bg-danger px-3 py-1">No</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $plan->estimated_start_date?->format('M d, Y') ?? '—' }}
                                </td>
                                <td>
                                    {{ $plan->estimated_end_date?->format('M d, Y') ?? '—' }}
                                </td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('procurement.plans.show', $plan) }}"
                                            class="btn btn-sm btn-outline-primary" title="View">
                                            <i class="feather-eye"></i>
                                        </a>
                                        <a href="{{ route('procurement.plans.edit', $plan) }}"
                                            class="btn btn-sm btn-outline-warning" title="Edit">
                                            <i class="feather-edit"></i>
                                        </a>
                                        <form action="{{ route('procurement.plans.destroy', $plan) }}"
                                            method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"
                                                onclick="return confirm('Are you sure you want to delete this plan?')">
                                                <i class="feather-trash-2"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-data-table>
            </div>
        </div>

    </div>
@endsection
