@extends('layouts.app')
@section('title', 'Project Details')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">

            <!-- Header -->
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">{{ $project->name }}</h4>
                    <p class="text-muted mb-0">Detailed view and yearly allocations for this project.</p>
                </div>
                <a href="{{ route('budget.projects.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left-circle me-1"></i> Back to Projects
                </a>
            </div>

            <!-- Details Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <p class="fw-semibold text-muted mb-1">Project ID</p>
                            <p>{{ $project->project_id }}</p>
                        </div>
                        <div class="col-md-4">
                            <p class="fw-semibold text-muted mb-1">Total Budget (GHS)</p>
                            <p>{{ number_format($project->total_budget, 2) }}</p>
                        </div>
                        <div class="col-md-4">
                            <p class="fw-semibold text-muted mb-1">Duration (Years)</p>
                            <p>{{ $project->total_years ?? $project->duration_years }}</p>
                        </div>
                        <div class="col-md-12">
                            <p class="fw-semibold text-muted mb-1">Description</p>
                            <p>{{ $project->description ?? '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Program Indicators -->
            @if ($project->program && $project->program->indicators && $project->program->indicators->count() > 0)
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="bi bi-bullseye me-2"></i> Program Indicators
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            @foreach ($project->program->indicators as $indicator)
                                <div class="col-md-6">
                                    <div class="p-3 border border-light rounded bg-light-shade">
                                        <p class="fw-semibold mb-1">{{ $indicator->name }}</p>
                                        <small class="text-muted">Created:
                                            {{ $indicator->created_at ? $indicator->created_at->format('d M, Y') : 'N/A' }}</small>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <!-- Project Indicators -->
            @if ($project->indicators && $project->indicators->count() > 0)
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="bi bi-target me-2"></i> Project Indicators
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            @foreach ($project->indicators as $indicator)
                                <div class="col-md-6">
                                    <div class="indicator-card p-3" style="--stripe:#2563eb;">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <span class="indicator-chip">{{ $indicator->name }}</span>
                                        </div>
                                        <small class="text-muted">Created:
                                            {{ $indicator->created_at ? $indicator->created_at->format('d M, Y') : 'N/A' }}</small>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <!-- Allocations -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Yearly Budget Allocations</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('budget.projects.allocations.update', $project->id) }}" method="POST">
                        @csrf

                        <div class="row g-3 align-items-center">
                            @foreach ($project->allocations->sortBy('year') as $alloc)
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Year {{ $alloc->year }}</label>
                                    <input type="number" name="allocations[{{ $alloc->year }}]"
                                        class="form-control" step="0.01" min="0" value="{{ $alloc->amount }}">
                                </div>
                            @endforeach
                        </div>

                        <input type="hidden" name="total_budget" value="{{ $project->total_budget }}">

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


