@extends('layouts.app')
@section('title', 'Activity Details')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">

            <!-- Header -->
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">{{ $activity->name }}</h4>
                    <p class="text-muted mb-0">Detailed information and budget allocations for this activity.</p>
                </div>
                <a href="{{ route('budget.activities.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left-circle me-1"></i> Back to Activities
                </a>
            </div>

            <!-- Details -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <p class="fw-semibold text-muted mb-1">Activity ID</p>
                            <p>{{ $activity->activity_id }}</p>
                        </div>
                        <div class="col-md-4">
                            <p class="fw-semibold text-muted mb-1">Total Budget (USD)</p>
                            <p>{{ number_format($activity->total_budget, 2) }}</p>
                        </div>
                        <div class="col-md-4">
                            <p class="fw-semibold text-muted mb-1">Created</p>
                            <p>{{ $activity->created_at->format('d M, Y') }}</p>
                        </div>
                        <div class="col-md-12">
                            <p class="fw-semibold text-muted mb-1">Description</p>
                            <p>{{ $activity->description ?? '—' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Allocations -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Yearly Allocations</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('budget.allocations.update', ['type' => 'Activity', 'id' => $activity->id]) }}"
                        method="POST">
                        @csrf
                        <div class="row g-3 align-items-center">
                            @foreach ($activity->budgetAllocations as $alloc)
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Year {{ $alloc->year_number }}</label>
                                    <input type="number" name="allocations[{{ $alloc->year_number }}]" class="form-control"
                                        step="0.01" min="0" value="{{ $alloc->amount }}">
                                </div>
                            @endforeach
                        </div>

                        <input type="hidden" name="total_budget" value="{{ $activity->total_budget }}">

                        <div class="mt-4 d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check2-circle me-1"></i> Update Allocations
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </main>
@endsection
