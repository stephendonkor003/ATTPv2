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
        .project-edit-tabs .nav-link {
            font-weight: 600;
            color: #475569;
        }
        .project-edit-tabs .nav-link.active {
            color: #2563eb;
        }
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
                    @if (session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

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
                        @php
                            $allocationsByYear = $project->allocations->keyBy(fn ($allocation) => (int) $allocation->year);
                            $activityTotalsByYear = $project->activities
                                ->flatMap(fn ($activity) => $activity->allocations)
                                ->groupBy(fn ($allocation) => (int) $allocation->year)
                                ->map(fn ($allocations) => (float) $allocations->sum('amount'));
                            $currency = $project->currency ?? $project->program?->currency ?? 'USD';
                        @endphp

                        <ul class="nav nav-tabs project-edit-tabs mb-4" id="projectEditTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="project-info-tab" data-bs-toggle="tab"
                                    data-bs-target="#project-info-pane" type="button" role="tab"
                                    aria-controls="project-info-pane" aria-selected="true">
                                    Project Info
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="project-description-tab" data-bs-toggle="tab"
                                    data-bs-target="#project-description-pane" type="button" role="tab"
                                    aria-controls="project-description-pane" aria-selected="false">
                                    Description
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="projectEditTabsContent">
                            <div class="tab-pane fade show active" id="project-info-pane" role="tabpanel"
                                aria-labelledby="project-info-tab" tabindex="0">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Project Name</label>
                                        <input type="text" name="name" class="form-control"
                                            value="{{ old('name', $project->name) }}" required>
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">Total Budget (USD)</label>
                                        <input type="number" step="0.01" name="total_budget" class="form-control"
                                            value="{{ old('total_budget', $project->total_budget) }}" required>
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">Duration (Years)</label>
                                        <input type="number" name="duration_years" class="form-control" min="1"
                                            max="10" value="{{ old('duration_years', $project->total_years) }}" required>
                                    </div>

                                    <div class="col-12 mt-4">
                                        <div class="alert alert-info mb-0">
                                            Indicators are managed from <strong>M&amp;E &rarr; Indicators</strong>.
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="border rounded p-3">
                                            <h6 class="fw-semibold mb-3">Yearly Allocations ({{ $currency }})</h6>
                                            <div class="row g-3">
                                                @foreach ($project->years() as $year)
                                                    @php
                                                        $year = (int) $year;
                                                        $allocation = $allocationsByYear->get($year);
                                                        $activityTotal = (float) ($activityTotalsByYear[$year] ?? 0);
                                                        $currentAmount = old('allocations.' . $year, optional($allocation)->amount ?? 0);
                                                    @endphp
                                                    <div class="col-md-3">
                                                        <label class="form-label fw-semibold">Year {{ $year }}</label>
                                                        <input type="number"
                                                            name="allocations[{{ $year }}]"
                                                            class="form-control allocation-input"
                                                            step="0.01"
                                                            min="0"
                                                            value="{{ $currentAmount }}"
                                                            data-child-total="{{ $activityTotal }}">
                                                        <small class="text-muted">
                                                            Activity total: {{ number_format($activityTotal, 2) }} {{ $currency }}
                                                        </small>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <div class="text-end mt-3">
                                                <span class="text-muted">Project allocation total:</span>
                                                <strong id="allocationTotal">0.00</strong> {{ $currency }}
                                                <span class="text-muted">/ {{ number_format((float) $project->total_budget, 2) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="project-description-pane" role="tabpanel"
                                aria-labelledby="project-description-tab" tabindex="0">
                                <div class="row g-4">
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Description</label>
                                        <textarea name="description" class="form-control" rows="8"
                                            placeholder="Optional project description">{{ old('description', $project->description) }}</textarea>
                                    </div>
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
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const inputs = Array.from(document.querySelectorAll('.allocation-input'));
            const total = document.getElementById('allocationTotal');

            function updateTotal() {
                const sum = inputs.reduce((carry, input) => carry + (parseFloat(input.value) || 0), 0);
                total.textContent = sum.toFixed(2);

                inputs.forEach(input => {
                    const childTotal = parseFloat(input.dataset.childTotal) || 0;
                    const amount = parseFloat(input.value) || 0;
                    input.classList.toggle('is-invalid', amount < childTotal);
                });
            }

            inputs.forEach(input => input.addEventListener('input', updateTotal));
            updateTotal();
        });
    </script>

@endsection
