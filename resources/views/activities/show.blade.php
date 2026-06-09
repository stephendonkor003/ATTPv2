@extends('layouts.app')

@section('title', 'Activity Details')

@section('content')

    <style>
        .info-box {
            background: #f8f9ff;
            border-left: 4px solid #0d6efd;
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 25px;
        }

        .budget-positive {
            color: #198754;
            font-weight: bold;
        }

        .budget-negative {
            color: #dc3545;
            font-weight: bold;
        }

        .fade-in {
            animation: fadeIn 0.4s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>

    <main class="nxl-container fade-in">
        <div class="nxl-content">

            <!-- HEADER -->
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold">{{ $activity->name }}</h4>
                    <div class="text-muted small">
                        Activity under Project <strong>{{ $project->project_id }}</strong>
                    </div>
                </div>

                <a href="{{ route('budget.projects.show', $project->id) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left-circle me-1"></i> Back to Project
                </a>
            </div>

            <!-- ACTIVITY SUMMARY -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <h5 class="fw-bold text-primary mb-3">Activity Summary</h5>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <small class="text-muted fw-semibold">Activity Name</small>
                            <div class="fw-bold">{{ $activity->name }}</div>
                        </div>

                        <div class="col-md-4">
                            <small class="text-muted fw-semibold">Description</small>
                            <div class="fw-bold">{{ $activity->description ?? 'No description' }}</div>
                        </div>

                        <div class="col-md-4">
                            <small class="text-muted fw-semibold">Created By</small>
                            <div class="fw-bold">{{ optional($activity->creator)->name ?? 'System' }}</div>
                        </div>

                        <div class="col-md-4">
                            <small class="text-muted fw-semibold">Expected Outcome</small>
                            <div class="fw-bold">
                                @if ($activity->expected_outcome_type === 'percentage')
                                    {{ $activity->expected_outcome_value ?? 'N/A' }}%
                                @elseif ($activity->expected_outcome_type === 'text')
                                    {{ $activity->expected_outcome_value ?? 'N/A' }}
                                @else
                                    N/A
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PROJECT SUMMARY -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <h5 class="fw-bold text-primary mb-3">Project Information</h5>

                    <div class="row g-3">
                        <div class="col-md-3">
                            <small class="text-muted fw-semibold">Project</small>
                            <div class="fw-bold">{{ $project->name }}</div>
                        </div>

                        <div class="col-md-3">
                            <small class="text-muted fw-semibold">Program</small>
                            <div class="fw-bold">{{ $project->program->name ?? 'N/A' }}</div>
                        </div>

                        <div class="col-md-3">
                            <small class="text-muted fw-semibold">Sector</small>
                            <div class="fw-bold">{{ $project->sector->name ?? 'N/A' }}</div>
                        </div>

                        <div class="col-md-3">
                            <small class="text-muted fw-semibold">Project Budget</small>
                            <div class="fw-bold">{{ number_format($project->total_budget, 2) }} {{ $project->currency }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BUDGET COMPARISON -->
            <div class="info-box">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Total Project Budget:</strong><br>
                        {{ number_format($project->total_budget, 2) }} {{ $project->currency }}
                    </div>

                    <div class="col-md-3">
                        <strong>Activity Allocation Total:</strong><br>
                        <span class="text-primary fw-bold">
                            {{ number_format($totalAllocation, 2) }} {{ $project->currency }}
                        </span>
                    </div>

                    <div class="col-md-3">
                        <strong>Remaining Project Budget:</strong><br>
                        <span class="{{ $remainingBudget >= 0 ? 'budget-positive' : 'budget-negative' }}">
                            {{ number_format($remainingBudget, 2) }} {{ $project->currency }}
                        </span>
                    </div>

                    <div class="col-md-3">
                        <strong>% of Project Used:</strong><br>
                        <span class="fw-bold">
                            {{ number_format($percentageUsed, 1) }}%
                        </span>
                    </div>
                </div>
            </div>

            <!-- ALLOCATION TABLE -->
            <div class="card shadow-sm border-0">
                <div class="card-body">

                    <h5 class="fw-bold mb-3">Yearly Allocation Breakdown</h5>

                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Year</th>
                                <th>Amount ({{ $project->currency }})</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($activity->allocations as $allocation)
                                <tr>
                                    <td class="fw-semibold">{{ $allocation->year }}</td>
                                    <td>{{ number_format($allocation->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="text-end fw-bold mt-3">
                        Total: {{ number_format($totalAllocation, 2) }} {{ $project->currency }}
                    </div>

                    <div class="text-end mt-3">
                        @can('activities.edit')
                            <a href="{{ route('budget.activities.edit', $activity->id) }}" class="btn btn-primary">
                                <i class="bi bi-sliders"></i> Edit Allocations
                            </a>
                        @endcan
                    </div>
                </div>
            </div>

        </div>
    </main>

@endsection
