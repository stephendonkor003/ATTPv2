@extends('layouts.app')

@section('title', 'Project Details')

@push('styles')
    <style>
        .project-show-workspace {
            color: #0f172a;
        }

        .project-show-hero {
            border-radius: 8px;
            padding: 20px;
            color: #ffffff;
            background: linear-gradient(135deg, #063f36 0%, #0f766e 56%, #522b39 100%);
            box-shadow: 0 18px 36px rgba(6, 63, 54, 0.16);
        }

        .project-show-hero h4,
        .project-show-hero p {
            color: #ffffff;
        }

        .project-show-kicker {
            color: #d9fff4;
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .project-show-chip {
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

        .project-show-stat-grid,
        .project-show-grid {
            display: grid;
            gap: 12px;
        }

        .project-show-stat-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .project-show-grid {
            grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr);
        }

        .project-show-stat,
        .project-show-panel,
        .project-show-table-card,
        .project-allocation-card {
            border: 1px solid #dbe3ea;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
        }

        .project-show-stat {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
        }

        .project-show-icon {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            color: #065f46;
            background: #d1fae5;
            font-size: 1.05rem;
        }

        .project-show-icon.blue {
            color: #075985;
            background: #e0f2fe;
        }

        .project-show-icon.amber {
            color: #92400e;
            background: #fef3c7;
        }

        .project-show-icon.wine {
            color: #522b39;
            background: #f8e8ef;
        }

        .project-show-label {
            color: #64748b;
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .project-show-value {
            color: #0f172a;
            font-size: 1.55rem;
            font-weight: 800;
            line-height: 1.1;
        }

        .project-show-panel,
        .project-allocation-card {
            padding: 16px;
        }

        .project-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            padding: 10px 0;
            border-bottom: 1px dashed #cbd5e1;
        }

        .project-detail-row:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .project-detail-row span {
            color: #64748b;
            font-size: 0.84rem;
            font-weight: 700;
        }

        .project-detail-row strong {
            color: #0f172a;
            text-align: right;
            max-width: 65%;
        }

        .project-budget-bar {
            height: 10px;
            border-radius: 999px;
            overflow: hidden;
            background: #e2e8f0;
        }

        .project-budget-bar span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #0f766e 0%, #d97706 100%);
        }

        .project-indicator-card {
            border: 1px solid #dbe3ea;
            border-radius: 8px;
            padding: 14px;
            height: 100%;
            background: #ffffff;
        }

        .project-activity-actions {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
            white-space: nowrap;
        }

        .project-activity-action {
            width: 30px;
            height: 30px;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #1d4ed8;
            background: #eff6ff;
            font-size: 0.9rem;
            line-height: 1;
        }

        .project-activity-action:hover {
            color: #ffffff;
            border-color: #1d4ed8;
            background: #1d4ed8;
        }

        .project-activity-action.warning {
            border-color: #fde68a;
            color: #92400e;
            background: #fffbeb;
        }

        .project-activity-action.warning:hover {
            color: #ffffff;
            border-color: #d97706;
            background: #d97706;
        }

        .project-allocation-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .project-year-field {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            background: #f8fafc;
        }

        .project-allocation-summary {
            border: 1px solid #dbe3ea;
            border-radius: 8px;
            padding: 12px 14px;
            background: #f8fafc;
        }

        .project-empty-state {
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 16px;
            color: #64748b;
            background: #f8fafc;
        }

        .project-show-table-card .table td {
            vertical-align: middle;
        }

        @media (max-width: 1199.98px) {
            .project-show-stat-grid,
            .project-show-grid,
            .project-allocation-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .project-show-stat-grid,
            .project-show-grid,
            .project-allocation-grid {
                grid-template-columns: 1fr;
            }

            .project-show-hero {
                padding: 16px;
            }

            .project-detail-row {
                flex-direction: column;
            }

            .project-detail-row strong {
                max-width: 100%;
                text-align: left;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $currency = $project->currency ?? $project->program?->currency ?? 'USD';
        $outcome = match ($project->expected_outcome_type) {
            'percentage' => ($project->expected_outcome_value !== null ? $project->expected_outcome_value . '%' : 'Not set'),
            'text' => $project->expected_outcome_value ?: 'Not set',
            default => 'Not set',
        };
        $allocationPercent = $projectStats['allocation_percent'] ?? 0;
        $activityPercent = $projectStats['activity_percent'] ?? 0;
        $subActivityPercent = $projectStats['sub_activity_percent'] ?? 0;
    @endphp

    <div class="nxl-container project-show-workspace">
        <div class="project-show-hero">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
                <div>
                    <div class="project-show-kicker mb-2">Project Details</div>
                    <h4 class="fw-bold mb-2">{{ $project->name }}</h4>
                    <p class="mb-0">
                        {{ $project->description ?: 'No description has been added for this project.' }}
                    </p>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <span class="project-show-chip"><i class="feather-hash"></i> {{ $project->project_id }}</span>
                        <span class="project-show-chip"><i class="feather-layers"></i> {{ $project->program->name ?? 'No program' }}</span>
                        <span class="project-show-chip"><i class="feather-git-branch"></i> {{ number_format($projectStats['activities']) }} activities</span>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-content-start justify-content-xl-end gap-2">
                    <a href="{{ route('budget.projects.index') }}" class="btn btn-light">
                        <i class="feather-arrow-left me-1"></i> Projects
                    </a>
                    @can('project.edit')
                        <a href="{{ route('budget.projects.edit', $project->id) }}" class="btn btn-success">
                            <i class="feather-edit me-1"></i> Edit Project
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
        @if ($errors->any())
            <div class="alert alert-danger mt-3">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="project-show-stat-grid mt-3">
            <div class="project-show-stat">
                <span class="project-show-icon"><i class="feather-git-branch"></i></span>
                <div>
                    <div class="project-show-label">Activities</div>
                    <div class="project-show-value">{{ number_format($projectStats['activities']) }}</div>
                    <small class="text-muted">{{ number_format($projectStats['sub_activities']) }} sub-activities</small>
                </div>
            </div>
            <div class="project-show-stat">
                <span class="project-show-icon blue"><i class="feather-target"></i></span>
                <div>
                    <div class="project-show-label">Indicators</div>
                    <div class="project-show-value">{{ number_format($projectStats['project_indicators']) }}</div>
                    <small class="text-muted">{{ number_format($projectStats['program_indicators']) }} program indicators</small>
                </div>
            </div>
            <div class="project-show-stat">
                <span class="project-show-icon amber"><i class="feather-dollar-sign"></i></span>
                <div>
                    <div class="project-show-label">Project Budget</div>
                    <div class="project-show-value" style="font-size: 1.25rem;">{{ number_format($projectStats['project_budget'], 2) }}</div>
                    <small class="text-muted">{{ $currency }}</small>
                </div>
            </div>
            <div class="project-show-stat">
                <span class="project-show-icon wine"><i class="feather-pie-chart"></i></span>
                <div>
                    <div class="project-show-label">Allocated</div>
                    <div class="project-show-value">{{ $allocationPercent }}%</div>
                    <small class="text-muted">{{ number_format($projectStats['allocation_total'], 2) }} {{ $currency }}</small>
                </div>
            </div>
        </div>

        <div class="project-show-grid mt-3">
            <div class="project-show-panel">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Project Profile</h6>
                        <p class="text-muted small mb-0">Program alignment, governance, timing, and expected outcome.</p>
                    </div>
                    <i class="feather-info text-success"></i>
                </div>

                <div class="project-detail-row">
                    <span>Project ID</span>
                    <strong>{{ $project->project_id }}</strong>
                </div>
                <div class="project-detail-row">
                    <span>Portfolio</span>
                    <strong>{{ $project->program->sector->name ?? 'Not assigned' }}</strong>
                </div>
                <div class="project-detail-row">
                    <span>Program</span>
                    <strong>{{ $project->program->name ?? 'Not assigned' }}</strong>
                </div>
                <div class="project-detail-row">
                    <span>Governance Node</span>
                    <strong>{{ $project->program->governanceNode->name ?? $project->governanceNode->name ?? 'Not assigned' }}</strong>
                </div>
                <div class="project-detail-row">
                    <span>Duration</span>
                    <strong>{{ $project->start_year ?? 'N/A' }} to {{ $project->end_year ?? 'N/A' }}</strong>
                </div>
                <div class="project-detail-row">
                    <span>Expected Outcome</span>
                    <strong>{{ Str::limit($outcome, 160) }}</strong>
                </div>
            </div>

            <div class="project-show-panel">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Allocation Utilization</h6>
                        <p class="text-muted small mb-0">Project budget, yearly allocation, and activity distribution.</p>
                    </div>
                    <span class="badge bg-success-subtle text-success">{{ $allocationPercent }}%</span>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between small fw-semibold mb-1">
                        <span>Allocated by year</span>
                        <span>{{ number_format($projectStats['allocation_total'], 2) }}</span>
                    </div>
                    <div class="project-budget-bar">
                        <span style="width: {{ $allocationPercent }}%;"></span>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between small fw-semibold mb-1">
                        <span>Assigned to activities</span>
                        <span>{{ number_format($projectStats['activity_allocation_total'], 2) }}</span>
                    </div>
                    <div class="project-budget-bar">
                        <span style="width: {{ $activityPercent }}%;"></span>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between small fw-semibold mb-1">
                        <span>Assigned to sub-activities</span>
                        <span>{{ number_format($projectStats['sub_activity_allocation_total'], 2) }}</span>
                    </div>
                    <div class="project-budget-bar">
                        <span style="width: {{ $subActivityPercent }}%;"></span>
                    </div>
                </div>

                <div class="project-detail-row">
                    <span>Project Budget</span>
                    <strong>{{ number_format($projectStats['project_budget'], 2) }} {{ $currency }}</strong>
                </div>
                <div class="project-detail-row">
                    <span>Yearly Allocation</span>
                    <strong>{{ number_format($projectStats['allocation_total'], 2) }} {{ $currency }}</strong>
                </div>
                <div class="project-detail-row">
                    <span>Activity Allocation</span>
                    <strong>{{ number_format($projectStats['activity_allocation_total'], 2) }} {{ $currency }}</strong>
                </div>
                <div class="project-detail-row">
                    <span>Sub-Activity Allocation</span>
                    <strong>{{ number_format($projectStats['sub_activity_allocation_total'], 2) }} {{ $currency }}</strong>
                </div>
                <div class="project-detail-row">
                    <span>Envelope Not Assigned to Activities</span>
                    <strong>{{ number_format($projectStats['remaining_to_activities'], 2) }} {{ $currency }}</strong>
                </div>
            </div>
        </div>

        @if ($project->program && $project->program->indicators && $project->program->indicators->count() > 0)
            <div class="project-show-panel mt-3">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Program Indicators</h6>
                        <p class="text-muted small mb-0">M&E indicators inherited from the linked program.</p>
                    </div>
                    <span class="badge bg-primary-subtle text-primary">{{ number_format($project->program->indicators->count()) }} indicators</span>
                </div>

                <div class="row g-3">
                    @foreach ($project->program->indicators as $indicator)
                        <div class="col-md-6">
                            <div class="project-indicator-card">
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <span class="fw-bold text-dark">{{ $indicator->name }}</span>
                                    @if ($indicator->level)
                                        <span class="badge bg-light text-primary border">{{ $indicator->level->name }}</span>
                                    @endif
                                </div>
                                <div class="small text-muted d-grid gap-1">
                                    @if ($indicator->baseline_year)
                                        <span>Baseline: {{ $indicator->baseline_year }} ({{ $indicator->baseline_type ?? 'year' }})</span>
                                    @endif
                                    @if ($indicator->frequency)
                                        <span>Reporting: {{ $indicator->frequency->name }}</span>
                                    @endif
                                    @if ($indicator->definitions)
                                        <span>Definition: {{ Str::limit($indicator->definitions, 120) }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($project->indicators && $project->indicators->count() > 0)
            <div class="project-show-panel mt-3">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Project Indicators</h6>
                        <p class="text-muted small mb-0">Indicators attached directly to this project.</p>
                    </div>
                    <span class="badge bg-success-subtle text-success">{{ number_format($project->indicators->count()) }} indicators</span>
                </div>

                <div class="row g-3">
                    @foreach ($project->indicators as $indicator)
                        <div class="col-md-6">
                            <div class="project-indicator-card">
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <span class="fw-bold text-dark">{{ $indicator->name }}</span>
                                    @if ($indicator->level)
                                        <span class="badge bg-light text-primary border">{{ $indicator->level->name }}</span>
                                    @endif
                                </div>
                                <div class="small text-muted d-grid gap-1">
                                    @if ($indicator->parentIndicator)
                                        <span>Parent: {{ $indicator->parentIndicator->name }}</span>
                                    @endif
                                    @if ($indicator->baseline_year)
                                        <span>Baseline: {{ $indicator->baseline_year }} ({{ $indicator->baseline_type ?? 'year' }})</span>
                                    @endif
                                    @if ($indicator->frequency)
                                        <span>Reporting: {{ $indicator->frequency->name }}</span>
                                    @endif
                                    @if ($indicator->definitions)
                                        <span>Definition: {{ Str::limit($indicator->definitions, 120) }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="card shadow-sm border-0 mt-3 project-show-table-card">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Activities Under This Project</h6>
                        <p class="text-muted small mb-0">Review activity allocations, sub-activity depth, and delivery structure.</p>
                    </div>
                    @can('activities.create')
                        <a href="{{ route('budget.activities.create', $project->id) }}" class="btn btn-success btn-sm">
                            <i class="feather-plus-circle me-1"></i> Add Activity
                        </a>
                    @endcan
                </div>

                <x-data-table id="projectActivitiesTable">
                    <thead class="table-light">
                        <tr>
                            <th width="50">#</th>
                            <th>Activity</th>
                            <th>Sub-Activities</th>
                            <th>Allocation</th>
                            <th>Sub-Activity Allocation</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($project->activities as $activity)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $activity->name }}</div>
                                    <small class="text-muted">{{ $activity->description ? Str::limit($activity->description, 15, '...') : 'No description.' }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-warning-subtle text-warning">{{ number_format($activity->sub_activities_count) }} sub</span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ number_format((float) $activity->allocation_total, 2) }}</div>
                                    <small class="text-muted">{{ $currency }}</small>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ number_format((float) $activity->sub_activity_allocation_total, 2) }}</div>
                                    <small class="text-muted">{{ $currency }}</small>
                                </td>
                                <td class="text-end">
                                    <div class="project-activity-actions">
                                        @can('activities.view')
                                            <a href="{{ route('budget.activities.show', $activity->id) }}"
                                                class="project-activity-action" title="View Activity">
                                                <i class="feather-eye"></i>
                                            </a>
                                        @endcan
                                        @can('activities.edit')
                                            <a href="{{ route('budget.activities.edit', $activity->id) }}"
                                                class="project-activity-action warning" title="Edit Activity">
                                                <i class="feather-edit"></i>
                                            </a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="project-empty-state text-center">No activities have been created for this project yet.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-data-table>
            </div>
        </div>

        <div class="project-allocation-card mt-3">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
                <div>
                    <h6 class="fw-bold mb-1">Yearly Budget Allocations</h6>
                    <p class="text-muted small mb-0">Control the project yearly budget and keep it above activity-level allocations.</p>
                </div>
                <span class="badge bg-success-subtle text-success">{{ $currency }}</span>
            </div>

            @php
                $allocationsByYear = $project->allocations->keyBy(fn ($allocation) => (int) $allocation->year);
                $activityTotalsByYear = $project->activities
                    ->flatMap(fn ($activity) => $activity->allocations)
                    ->groupBy(fn ($allocation) => (int) $allocation->year)
                    ->map(fn ($allocations) => (float) $allocations->sum('amount'));
            @endphp

            <form action="{{ route('budget.projects.allocations.update', $project->id) }}" method="POST">
                @csrf

                <div class="project-allocation-grid">
                    @foreach ($project->years() as $year)
                        @php
                            $year = (int) $year;
                            $allocation = $allocationsByYear->get($year);
                            $activityTotal = (float) ($activityTotalsByYear[$year] ?? 0);
                            $currentAmount = old('allocations.' . $year, optional($allocation)->amount ?? 0);
                        @endphp
                        <div class="project-year-field">
                            <label class="form-label fw-semibold">Year {{ $year }}</label>
                            <input type="number" name="allocations[{{ $year }}]"
                                class="form-control allocation-input" step="0.01" min="0"
                                value="{{ $currentAmount }}" data-child-total="{{ $activityTotal }}">
                            <small class="text-muted">
                                Activity total: {{ number_format($activityTotal, 2) }} {{ $currency }}
                            </small>
                        </div>
                    @endforeach
                </div>

                <input type="hidden" name="total_budget" value="{{ $project->total_budget }}">

                <div class="project-allocation-summary d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mt-3">
                    <div>
                        <span class="text-muted">Project allocation total:</span>
                        <strong id="allocationTotal">0.00</strong> {{ $currency }}
                        <span class="text-muted">/ {{ number_format((float) $project->total_budget, 2) }}</span>
                    </div>
                    @can('project.edit')
                        <button type="submit" class="btn btn-success">
                            <i class="feather-check-circle me-1"></i> Update Allocations
                        </button>
                    @endcan
                </div>
            </form>
        </div>
    </div>

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
