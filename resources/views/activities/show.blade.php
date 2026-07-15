@extends('layouts.app')

@section('title', 'Activity Details')

@push('styles')
    <style>
        .activity-detail {
            color: #0f172a;
        }

        .activity-detail-hero {
            border-radius: 8px;
            padding: 20px;
            color: #ffffff;
            background: linear-gradient(135deg, #063f36 0%, #0f766e 58%, #522b39 100%);
            box-shadow: 0 16px 32px rgba(6, 63, 54, 0.14);
        }

        .activity-detail-hero h4,
        .activity-detail-hero p {
            color: #ffffff;
        }

        .activity-kicker {
            color: #d9fff4;
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .activity-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 999px;
            padding: 0.35rem 0.7rem;
            color: #effff9;
            background: rgba(255, 255, 255, 0.1);
            font-size: 0.82rem;
            font-weight: 700;
        }

        .activity-stat-grid,
        .activity-detail-grid {
            display: grid;
            gap: 12px;
        }

        .activity-stat-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .activity-detail-grid {
            grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr);
        }

        .activity-stat-card,
        .activity-panel,
        .activity-table-card {
            border: 1px solid #dbe3ea;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
        }

        .activity-stat-card {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px;
            min-height: 78px;
        }

        .activity-stat-icon {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            color: #065f46;
            background: #d1fae5;
        }

        .activity-stat-icon.blue {
            color: #075985;
            background: #e0f2fe;
        }

        .activity-stat-icon.amber {
            color: #92400e;
            background: #fef3c7;
        }

        .activity-stat-icon.wine {
            color: #522b39;
            background: #f8e8ef;
        }

        .activity-stat-label {
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .activity-stat-value {
            color: #0f172a;
            font-size: 1.18rem;
            font-weight: 800;
            line-height: 1.15;
        }

        .activity-panel {
            padding: 16px;
        }

        .activity-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            padding: 10px 0;
            border-bottom: 1px dashed #cbd5e1;
        }

        .activity-detail-row:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .activity-detail-row span {
            color: #64748b;
            font-size: 0.84rem;
            font-weight: 700;
        }

        .activity-detail-row strong {
            max-width: 65%;
            color: #0f172a;
            text-align: right;
        }

        .activity-progress-bar {
            height: 10px;
            border-radius: 999px;
            overflow: hidden;
            background: #e2e8f0;
        }

        .activity-progress-bar span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #0f766e 0%, #d97706 100%);
        }

        .activity-path-card {
            border: 1px solid #7dd3fc;
            border-radius: 8px;
            padding: 12px;
            background: #f0f9ff;
        }

        .activity-path-card + .activity-path-card {
            margin-top: 8px;
        }

        .activity-path-label {
            color: #075985;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .activity-path-title {
            color: #0f172a;
            font-weight: 800;
        }

        .activity-table-card .table td {
            vertical-align: middle;
        }

        .activity-allocation-total {
            border-radius: 8px;
            padding: 12px;
            color: #522b39;
            background: #fbf1f5;
            font-weight: 800;
        }

        .activity-sub-card {
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 12px;
            height: 100%;
            background: #fffbeb;
        }

        .activity-sub-title {
            color: #78350f;
            font-weight: 800;
        }

        .activity-sub-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
        }

        .activity-sub-meta span {
            border-radius: 999px;
            padding: 0.18rem 0.48rem;
            color: #92400e;
            background: #fef3c7;
            font-size: 0.74rem;
            font-weight: 700;
        }

        .activity-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .activity-empty-state {
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 16px;
            color: #64748b;
            background: #f8fafc;
        }

        @media (max-width: 1199.98px) {
            .activity-stat-grid,
            .activity-detail-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .activity-stat-grid,
            .activity-detail-grid {
                grid-template-columns: 1fr;
            }

            .activity-detail-row {
                flex-direction: column;
            }

            .activity-detail-row strong {
                max-width: 100%;
                text-align: left;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $currency = $project->currency ?? $project->program?->currency ?? 'USD';
        $outcome = match ($activity->expected_outcome_type) {
            'percentage' => ($activity->expected_outcome_value !== null ? $activity->expected_outcome_value . '%' : 'Not set'),
            'text' => $activity->expected_outcome_value ?: 'Not set',
            default => 'Not set',
        };
    @endphp

    <div class="nxl-container activity-detail">
        <div class="activity-detail-hero">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
                <div>
                    <div class="activity-kicker mb-2">Activity Details</div>
                    <h4 class="fw-bold mb-2">{{ $activity->name }}</h4>
                    <p class="mb-0">
                        {{ $activity->description ?: 'No description has been added for this activity.' }}
                    </p>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <span class="activity-chip"><i class="feather-layers"></i> {{ $project->program?->name ?? 'No program' }}</span>
                        <span class="activity-chip"><i class="feather-folder"></i> {{ $project->project_id }}</span>
                        <span class="activity-chip"><i class="feather-list"></i> {{ number_format($activityStats['sub_activity_count']) }} sub-activities</span>
                    </div>
                </div>

                <div class="activity-actions align-content-start justify-content-xl-end">
                    <a href="{{ route('budget.activities.index') }}" class="btn btn-light">
                        <i class="feather-arrow-left me-1"></i> Activities
                    </a>
                    <a href="{{ route('budget.projects.show', $project->id) }}" class="btn btn-light">
                        <i class="feather-folder me-1"></i> Project
                    </a>
                    @can('activities.edit')
                        <a href="{{ route('budget.activities.edit', $activity->id) }}" class="btn btn-success">
                            <i class="feather-edit me-1"></i> Edit Allocations
                        </a>
                    @endcan
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show mt-3">
                <i class="feather-check-circle me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show mt-3">
                <i class="feather-alert-triangle me-2"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="activity-stat-grid mt-3">
            <div class="activity-stat-card">
                <span class="activity-stat-icon"><i class="feather-dollar-sign"></i></span>
                <div>
                    <div class="activity-stat-label">Activity Allocation</div>
                    <div class="activity-stat-value">{{ number_format($activityStats['activity_allocation_total'], 2) }}</div>
                    <small class="text-muted">{{ $currency }}</small>
                </div>
            </div>
            <div class="activity-stat-card">
                <span class="activity-stat-icon blue"><i class="feather-folder"></i></span>
                <div>
                    <div class="activity-stat-label">Project Budget</div>
                    <div class="activity-stat-value">{{ number_format($activityStats['project_budget'], 2) }}</div>
                    <small class="text-muted">{{ $activityStats['activity_project_percent'] }}% used by this activity</small>
                </div>
            </div>
            <div class="activity-stat-card">
                <span class="activity-stat-icon amber"><i class="feather-list"></i></span>
                <div>
                    <div class="activity-stat-label">Sub-Activities</div>
                    <div class="activity-stat-value">{{ number_format($activityStats['sub_activity_count']) }}</div>
                    <small class="text-muted">{{ number_format($activityStats['sub_activity_allocation_total'], 2) }} allocated</small>
                </div>
            </div>
            <div class="activity-stat-card">
                <span class="activity-stat-icon wine"><i class="feather-pie-chart"></i></span>
                <div>
                    <div class="activity-stat-label">Available In Activity</div>
                    <div class="activity-stat-value">{{ number_format($activityStats['remaining_activity_allocation'], 2) }}</div>
                    <small class="text-muted">{{ $currency }}</small>
                </div>
            </div>
        </div>

        <div class="activity-detail-grid mt-3">
            <div class="activity-panel">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Activity Profile</h6>
                        <p class="text-muted small mb-0">Outcome, hierarchy, and creation context.</p>
                    </div>
                    <i class="feather-info text-success"></i>
                </div>

                <div class="activity-detail-row">
                    <span>Activity Name</span>
                    <strong>{{ $activity->name }}</strong>
                </div>
                <div class="activity-detail-row">
                    <span>Expected Outcome</span>
                    <strong>{{ Str::limit($outcome, 160) }}</strong>
                </div>
                <div class="activity-detail-row">
                    <span>Program</span>
                    <strong>{{ $project->program?->name ?? 'Not assigned' }}</strong>
                </div>
                <div class="activity-detail-row">
                    <span>Portfolio</span>
                    <strong>{{ $project->program?->sector?->name ?? 'Not assigned' }}</strong>
                </div>
                <div class="activity-detail-row">
                    <span>Project</span>
                    <strong>{{ $project->project_id }} - {{ $project->name }}</strong>
                </div>
                <div class="activity-detail-row">
                    <span>Created</span>
                    <strong>{{ $activity->created_at ? $activity->created_at->format('d M Y') : 'N/A' }}</strong>
                </div>
            </div>

            <div class="activity-panel">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Budget Utilization</h6>
                        <p class="text-muted small mb-0">Activity allocation and sub-activity usage.</p>
                    </div>
                    <span class="badge bg-success-subtle text-success">{{ $activityStats['sub_activity_usage_percent'] }}%</span>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between small fw-semibold mb-1">
                        <span>Sub-activity usage</span>
                        <span>{{ number_format($activityStats['sub_activity_allocation_total'], 2) }}</span>
                    </div>
                    <div class="activity-progress-bar">
                        <span style="width: {{ $activityStats['sub_activity_usage_percent'] }}%;"></span>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between small fw-semibold mb-1">
                        <span>Share of project budget</span>
                        <span>{{ $activityStats['activity_project_percent'] }}%</span>
                    </div>
                    <div class="activity-progress-bar">
                        <span style="width: {{ $activityStats['activity_project_percent'] }}%;"></span>
                    </div>
                </div>

                <div class="activity-detail-row">
                    <span>Activity Allocation</span>
                    <strong>{{ number_format($activityStats['activity_allocation_total'], 2) }} {{ $currency }}</strong>
                </div>
                <div class="activity-detail-row">
                    <span>Sub-Activity Allocation</span>
                    <strong>{{ number_format($activityStats['sub_activity_allocation_total'], 2) }} {{ $currency }}</strong>
                </div>
                <div class="activity-detail-row">
                    <span>Available In Activity</span>
                    <strong>{{ number_format($activityStats['remaining_activity_allocation'], 2) }} {{ $currency }}</strong>
                </div>
            </div>
        </div>

        <div class="activity-detail-grid mt-3">
            <div class="activity-panel">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Hierarchy Path</h6>
                        <p class="text-muted small mb-0">Where this activity sits in the budget structure.</p>
                    </div>
                    <i class="feather-git-branch text-success"></i>
                </div>

                <div class="activity-path-card">
                    <div class="activity-path-label">Program</div>
                    <div class="activity-path-title">{{ $project->program?->name ?? 'Not assigned' }}</div>
                    <div class="text-muted small">{{ $project->program?->sector?->name ?? 'No portfolio assigned' }}</div>
                </div>
                <div class="activity-path-card">
                    <div class="activity-path-label">Project</div>
                    <div class="activity-path-title">{{ $project->project_id }} - {{ $project->name }}</div>
                    <div class="text-muted small">{{ $project->start_year ?? 'N/A' }} to {{ $project->end_year ?? 'N/A' }}</div>
                </div>
                <div class="activity-path-card">
                    <div class="activity-path-label">Activity</div>
                    <div class="activity-path-title">{{ $activity->name }}</div>
                    <div class="text-muted small">{{ number_format($activityStats['activity_allocation_total'], 2) }} {{ $currency }}</div>
                </div>
            </div>

            <div class="activity-panel">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Project Budget Context</h6>
                        <p class="text-muted small mb-0">Parent project budget and allocation totals.</p>
                    </div>
                    <i class="feather-dollar-sign text-success"></i>
                </div>

                <div class="activity-detail-row">
                    <span>Project Budget</span>
                    <strong>{{ number_format($activityStats['project_budget'], 2) }} {{ $currency }}</strong>
                </div>
                <div class="activity-detail-row">
                    <span>Project Yearly Allocation</span>
                    <strong>{{ number_format($activityStats['project_allocation_total'], 2) }} {{ $currency }}</strong>
                </div>
                <div class="activity-detail-row">
                    <span>All Activity Allocations</span>
                    <strong>{{ number_format($activityStats['project_activity_total'], 2) }} {{ $currency }}</strong>
                </div>
                <div class="activity-detail-row">
                    <span>This Activity</span>
                    <strong>{{ number_format($activityStats['activity_allocation_total'], 2) }} {{ $currency }}</strong>
                </div>
            </div>
        </div>

        <div class="card mt-3 shadow-sm border-0 activity-table-card">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Yearly Allocation Breakdown</h6>
                        <p class="text-muted small mb-0">Year-by-year allocation for this activity.</p>
                    </div>
                    <div class="activity-allocation-total">
                        Total: {{ number_format($activityStats['activity_allocation_total'], 2) }} {{ $currency }}
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Year</th>
                                <th>Amount</th>
                                <th>Currency</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($activity->allocations as $allocation)
                                <tr>
                                    <td class="fw-semibold">{{ $allocation->year }}</td>
                                    <td>{{ number_format((float) $allocation->amount, 2) }}</td>
                                    <td>{{ $currency }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">
                                        <div class="activity-empty-state text-center">No yearly allocations have been entered for this activity.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="activity-panel mt-3">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
                <div>
                    <h6 class="fw-bold mb-1">Sub-Activities</h6>
                    <p class="text-muted small mb-0">Child delivery items and their allocation totals.</p>
                </div>
                @can('subactivities.create')
                    <a href="{{ route('budget.subactivities.create', $activity->id) }}" class="btn btn-success btn-sm">
                        <i class="feather-plus-circle me-1"></i> Add Sub-Activity
                    </a>
                @endcan
            </div>

            <div class="row g-3">
                @forelse ($activity->subActivities as $subActivity)
                    @php
                        $subTotal = (float) $subActivity->allocations->sum('amount');
                    @endphp
                    <div class="col-md-6">
                        <div class="activity-sub-card">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <div class="activity-sub-title">{{ $subActivity->name }}</div>
                                    <div class="text-muted small">
                                        {{ $subActivity->description ? Str::limit($subActivity->description, 100) : 'No description has been added.' }}
                                    </div>
                                </div>
                                @can('subactivities.edit')
                                    <a href="{{ route('budget.subactivities.edit', $subActivity->id) }}" class="btn btn-sm btn-light border">
                                        <i class="feather-edit"></i>
                                    </a>
                                @endcan
                            </div>
                            <div class="activity-sub-meta">
                                <span>{{ number_format($subTotal, 2) }} {{ $currency }}</span>
                                <span>{{ number_format($subActivity->allocations->count()) }} years</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="activity-empty-state text-center">No sub-activities have been created under this activity yet.</div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
