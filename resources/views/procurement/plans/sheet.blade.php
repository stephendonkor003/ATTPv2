@extends('layouts.app')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="page-title mb-1">Program Plans</h4>
                <p class="text-muted mb-0">Each program plan is listed with its duration and procurement count. Open the sheet to see every procurement linked to a plan.</p>
            </div>
            <a href="{{ route('procurement.structure.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="feather-plus me-1"></i> New Program Plan
            </a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Plan Name</th>
                                <th>Duration</th>
                                <th>Timeline</th>
                                <th class="text-center">Procurements</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($programPlans as $plan)
                                <tr>
                                    <td class="fw-semibold">{{ $plan->name }}</td>
                                    <td>
                                        @if($plan->duration_days !== null)
                                            {{ $plan->duration_days }} days
                                        @else
                                            <span class="text-muted">TBD</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $plan->start_date?->format('M d, Y') ?? '—' }} — {{ $plan->end_date?->format('M d, Y') ?? '—' }}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info text-dark">{{ $plan->procurements_count }}</span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('procurement.plans.program-plans.sheet', $plan) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="feather-eye me-1"></i> View Sheet
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        No program plans available yet. Create one to start scheduling procurements.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
