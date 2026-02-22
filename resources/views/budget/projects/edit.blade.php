@extends('layouts.app')
@section('title', 'Edit Project')

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin/assets/css/select2-custom.css') }}">
@endpush

@section('content')
    <style>
        .project-chip {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.22rem 0.62rem;
            font-size: 0.72rem;
            font-weight: 600;
            border: 1px solid rgba(248, 250, 252, 0.38);
            background: rgba(248, 250, 252, 0.18);
            color: #f8fafc;
        }
        .section-card { border: 1px solid #e5e7eb; border-radius: 14px; box-shadow: 0 8px 24px rgba(15,23,42,0.04); }
    </style>

    <main class="nxl-container">
        <div class="nxl-content">

            <div class="page-header">
                <div class="page-header-left">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="project-chip">Budget - Projects</span>
                        <span class="project-chip">Edit</span>
                    </div>
                    <h5 class="m-b-10">Edit Project</h5>
                    <p class="mb-0">Update project details.</p>
                </div>
                <div class="page-header-right ms-auto">
                    <a href="{{ route('budget.projects.index') }}" class="btn btn-light text-primary border-0 shadow-sm">
                        <i class="bi bi-arrow-left-circle me-1"></i> Back to Projects
                    </a>
                </div>
            </div>

            <div class="card shadow-sm section-card">
                <div class="card-body">
                    <form action="{{ route('budget.projects.update', $project->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="program_id" value="{{ old('program_id', $project->program_id) }}">
                        <input type="hidden" name="start_year" value="{{ old('start_year', $project->start_year) }}">
                        <input type="hidden" name="end_year" value="{{ old('end_year', $project->end_year) }}">
                        <input type="hidden" name="expected_outcome_type"
                            value="{{ old('expected_outcome_type', $project->expected_outcome_type ?? 'text') }}">
                        @if (old('expected_outcome_type', $project->expected_outcome_type) === 'percentage')
                            <input type="hidden" name="expected_outcome_percentage"
                                value="{{ old('expected_outcome_percentage', $project->expected_outcome_value) }}">
                        @else
                            <input type="hidden" name="expected_outcome_text"
                                value="{{ old('expected_outcome_text', $project->expected_outcome_value) }}">
                        @endif
                        @foreach ($project->allocations as $allocation)
                            <input type="hidden" name="allocations[{{ $allocation->year }}]"
                                value="{{ old('allocations.' . $allocation->year, $allocation->amount) }}">
                        @endforeach

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Project Name</label>
                                <input type="text" name="name" class="form-control"
                                    value="{{ old('name', $project->name) }}" required>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Total Budget (GHS)</label>
                                <input type="number" step="0.01" name="total_budget" class="form-control"
                                    value="{{ old('total_budget', $project->total_budget) }}" required>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Duration (Years)</label>
                                <input type="number" name="duration_years" class="form-control" min="1"
                                    max="10" value="{{ old('duration_years', $project->total_years) }}" required>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea name="description" class="form-control" rows="3">{{ old('description', $project->description) }}</textarea>
                            </div>

                            <div class="col-12 mt-4">
                                <div class="alert alert-info mb-0">
                                    Indicators are managed from <strong>M&amp;E &rarr; Indicators</strong>.
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 d-flex justify-content-end gap-2">
                            <a href="{{ route('budget.projects.index') }}"
                                class="btn btn-light border">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save2 me-1"></i> Update Project
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </main>

@endsection

