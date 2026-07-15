@extends('layouts.app')

@section('title', 'Program Details')

@push('styles')
    <style>
        .program-show-workspace {
            color: #0f172a;
        }

        .program-show-hero {
            border-radius: 8px;
            padding: 20px;
            color: #ffffff;
            background: linear-gradient(135deg, #063f36 0%, #0f766e 56%, #522b39 100%);
            box-shadow: 0 18px 36px rgba(6, 63, 54, 0.16);
        }

        .program-show-hero h4,
        .program-show-hero p {
            color: #ffffff;
        }

        .program-show-kicker {
            color: #d9fff4;
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .program-show-chip {
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

        .program-show-stat-grid,
        .program-show-grid {
            display: grid;
            gap: 12px;
        }

        .program-show-stat-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .program-show-grid {
            grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.9fr);
        }

        .program-show-stat,
        .program-show-panel,
        .program-show-table-card {
            border: 1px solid #dbe3ea;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
        }

        .program-show-stat {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
        }

        .program-show-icon {
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

        .program-show-icon.blue {
            color: #075985;
            background: #e0f2fe;
        }

        .program-show-icon.amber {
            color: #92400e;
            background: #fef3c7;
        }

        .program-show-icon.wine {
            color: #522b39;
            background: #f8e8ef;
        }

        .program-show-label {
            color: #64748b;
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .program-show-value {
            color: #0f172a;
            font-size: 1.55rem;
            font-weight: 800;
            line-height: 1.1;
        }

        .program-show-panel {
            padding: 16px;
        }

        .program-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            padding: 10px 0;
            border-bottom: 1px dashed #cbd5e1;
        }

        .program-detail-row:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .program-detail-row span {
            color: #64748b;
            font-size: 0.84rem;
            font-weight: 700;
        }

        .program-detail-row strong {
            color: #0f172a;
            text-align: right;
            max-width: 65%;
        }

        .program-budget-bar {
            height: 10px;
            border-radius: 999px;
            overflow: hidden;
            background: #e2e8f0;
        }

        .program-budget-bar span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #0f766e 0%, #d97706 100%);
        }

        .program-indicator-card {
            border: 1px solid #dbe3ea;
            border-radius: 8px;
            padding: 14px;
            height: 100%;
            background: #ffffff;
        }

        .program-project-actions {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
            white-space: nowrap;
        }

        .program-project-action {
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

        .program-project-action:hover {
            color: #ffffff;
            border-color: #1d4ed8;
            background: #1d4ed8;
        }

        .program-project-action.warning {
            border-color: #fde68a;
            color: #92400e;
            background: #fffbeb;
        }

        .program-project-action.warning:hover {
            color: #ffffff;
            border-color: #d97706;
            background: #d97706;
        }

        .program-empty-state {
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 16px;
            color: #64748b;
            background: #f8fafc;
        }

        .program-show-table-card .table td {
            vertical-align: middle;
        }

        @media (max-width: 1199.98px) {
            .program-show-stat-grid,
            .program-show-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .program-show-stat-grid,
            .program-show-grid {
                grid-template-columns: 1fr;
            }

            .program-show-hero {
                padding: 16px;
            }

            .program-detail-row {
                flex-direction: column;
            }

            .program-detail-row strong {
                max-width: 100%;
                text-align: left;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $outcome = match ($program->expected_outcome_type) {
            'percentage' => ($program->expected_outcome_value !== null ? $program->expected_outcome_value . '%' : 'Not set'),
            'text' => $program->expected_outcome_value ?: 'Not set',
            default => 'Not set',
        };
    @endphp

    <div class="nxl-container program-show-workspace">
        <div class="program-show-hero">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
                <div>
                    <div class="program-show-kicker mb-2">Program Details</div>
                    <h4 class="fw-bold mb-2">{{ $program->name }}</h4>
                    <p class="mb-0">
                        {{ $program->description ?: 'No description has been added for this program.' }}
                    </p>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <span class="program-show-chip"><i class="feather-hash"></i> {{ $program->program_id }}</span>
                        <span class="program-show-chip"><i class="feather-briefcase"></i> {{ $program->sector->name ?? 'No portfolio' }}</span>
                        <span class="program-show-chip"><i class="feather-user-check"></i> {{ $program->ttl_name ?: ($program->ttlUser?->name ?? 'No TTL') }}</span>
                        <span class="program-show-chip"><i class="feather-folder"></i> {{ number_format($programStats['projects']) }} projects</span>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-content-start justify-content-xl-end gap-2">
                    <a href="{{ route('budget.programs.index') }}" class="btn btn-light">
                        <i class="feather-arrow-left me-1"></i> Programs
                    </a>
                    @can('program.edit')
                        <a href="{{ route('budget.programs.edit', $program) }}" class="btn btn-success">
                            <i class="feather-edit me-1"></i> Edit Program
                        </a>
                    @endcan
                </div>
            </div>
        </div>

        <div class="program-show-stat-grid mt-3">
            <div class="program-show-stat">
                <span class="program-show-icon"><i class="feather-folder"></i></span>
                <div>
                    <div class="program-show-label">Projects</div>
                    <div class="program-show-value">{{ number_format($programStats['projects']) }}</div>
                    <small class="text-muted">{{ number_format($programStats['activities']) }} activities</small>
                </div>
            </div>
            <div class="program-show-stat">
                <span class="program-show-icon blue"><i class="feather-git-branch"></i></span>
                <div>
                    <div class="program-show-label">Sub-Activities</div>
                    <div class="program-show-value">{{ number_format($programStats['sub_activities']) }}</div>
                    <small class="text-muted">Delivery work packages</small>
                </div>
            </div>
            <div class="program-show-stat">
                <span class="program-show-icon amber"><i class="feather-target"></i></span>
                <div>
                    <div class="program-show-label">Indicators</div>
                    <div class="program-show-value">{{ number_format($programStats['indicators']) }}</div>
                    <small class="text-muted">M&E indicators attached</small>
                </div>
            </div>
            <div class="program-show-stat">
                <span class="program-show-icon wine"><i class="feather-dollar-sign"></i></span>
                <div>
                    <div class="program-show-label">Budget</div>
                    <div class="program-show-value" style="font-size: 1.25rem;">{{ number_format($programStats['program_budget'], 2) }}</div>
                    <small class="text-muted">{{ $programStats['budget_utilization_percent'] }}% assigned to projects</small>
                </div>
            </div>
        </div>

        <div class="program-show-grid mt-3">
            <div class="program-show-panel">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Program Profile</h6>
                        <p class="text-muted small mb-0">Ownership, governance, timing, and expected outcome.</p>
                    </div>
                    <i class="feather-info text-success"></i>
                </div>

                <div class="program-detail-row">
                    <span>Program ID</span>
                    <strong>{{ $program->program_id }}</strong>
                </div>
                <div class="program-detail-row">
                    <span>Portfolio</span>
                    <strong>{{ $program->sector->name ?? 'Not assigned' }}</strong>
                </div>
                <div class="program-detail-row">
                    <span>Governance Node</span>
                    <strong>{{ $program->governanceNode->name ?? 'Not assigned' }}</strong>
                </div>
                <div class="program-detail-row">
                    <span>Task Team Leader</span>
                    <strong>
                        {{ $program->ttl_name ?: ($program->ttlUser?->name ?? 'Not assigned') }}
                        @if ($program->ttl_email || $program->ttlUser?->email)
                            <br><small class="text-muted">{{ $program->ttl_email ?: $program->ttlUser?->email }}</small>
                        @endif
                    </strong>
                </div>
                <div class="program-detail-row">
                    <span>Duration</span>
                    <strong>{{ $program->start_year ?? 'N/A' }} to {{ $program->end_year ?? 'N/A' }}</strong>
                </div>
                <div class="program-detail-row">
                    <span>Expected Outcome</span>
                    <strong>{{ Str::limit($outcome, 160) }}</strong>
                </div>
                <div class="program-detail-row">
                    <span>Created</span>
                    <strong>{{ $program->created_at ? $program->created_at->format('d M Y') : 'N/A' }}</strong>
                </div>
            </div>

            <div class="program-show-panel">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Budget Utilization</h6>
                        <p class="text-muted small mb-0">Program envelope against project budgets.</p>
                    </div>
                    <span class="badge bg-success-subtle text-success">{{ $programStats['budget_utilization_percent'] }}%</span>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between small fw-semibold mb-1">
                        <span>Assigned to projects</span>
                        <span>{{ number_format($programStats['project_budget'], 2) }}</span>
                    </div>
                    <div class="program-budget-bar">
                        <span style="width: {{ $programStats['budget_utilization_percent'] }}%;"></span>
                    </div>
                </div>

                <div class="program-detail-row">
                    <span>Program Budget</span>
                    <strong>{{ number_format($programStats['program_budget'], 2) }}</strong>
                </div>
                <div class="program-detail-row">
                    <span>Project Budget</span>
                    <strong>{{ number_format($programStats['project_budget'], 2) }}</strong>
                </div>
                <div class="program-detail-row">
                    <span>Remaining</span>
                    <strong>{{ number_format($programStats['remaining_budget'], 2) }}</strong>
                </div>
                <div class="program-detail-row">
                    <span>Currency</span>
                    <strong>{{ $program->currency ?: 'N/A' }}</strong>
                </div>
            </div>
        </div>

        @if ($program->indicators && $program->indicators->count() > 0)
            <div class="program-show-panel mt-3">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Indicators</h6>
                        <p class="text-muted small mb-0">M&E indicators linked to this program.</p>
                    </div>
                    <span class="badge bg-primary-subtle text-primary">{{ number_format($program->indicators->count()) }} indicators</span>
                </div>

                <div class="row g-3">
                    @foreach ($program->indicators as $indicator)
                        @php
                            $responsible = [];
                            if ($indicator->responsible_party) {
                                $responsible = json_decode($indicator->responsible_party, true) ?? [];
                                if (!is_array($responsible)) {
                                    $responsible = [$indicator->responsible_party];
                                }
                            }

                            $sourceType = 'manual';
                            $sourceDetail = '';
                            if ($indicator->primary_source && str_contains($indicator->primary_source, ':')) {
                                [$sourceType, $sourceDetail] = explode(':', $indicator->primary_source, 2);
                            } elseif ($indicator->primary_source) {
                                $sourceDetail = $indicator->primary_source;
                            }
                        @endphp
                        <div class="col-md-6">
                            <div class="program-indicator-card">
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
                                    @if ($indicator->baseline_value !== null)
                                        <span>
                                            Baseline Value:
                                            {{ rtrim(rtrim(number_format($indicator->baseline_value, 2), '0'), '.') }}
                                            @if ($indicator->unit)
                                                {{ $indicator->unit->symbol ?? $indicator->unit->name }}
                                            @endif
                                        </span>
                                    @endif
                                    @if ($indicator->frequency)
                                        <span>Reporting: {{ $indicator->frequency->name }}</span>
                                    @endif
                                    @if (!empty($responsible))
                                        <span>
                                            Responsible:
                                            @foreach ($responsible as $id)
                                                <span class="badge bg-primary-subtle text-primary border">{{ $id }}</span>
                                            @endforeach
                                        </span>
                                    @endif
                                    @if ($sourceDetail)
                                        <span>Source: {{ ucfirst($sourceType) }} - {{ $sourceDetail }}</span>
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

        <div class="card shadow-sm border-0 mt-3 program-show-table-card">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Projects Under This Program</h6>
                        <p class="text-muted small mb-0">Review project budgets, duration, activities, and sub-activity depth.</p>
                    </div>
                    @can('project.create')
                        <a href="{{ route('budget.projects.create', ['program_id' => $program->id]) }}" class="btn btn-success btn-sm">
                            <i class="feather-plus-circle me-1"></i> Add Project
                        </a>
                    @endcan
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="50">#</th>
                                <th>Project</th>
                                <th>Hierarchy</th>
                                <th>Budget</th>
                                <th>Duration</th>
                                <th>Created</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($program->projects as $project)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ $project->name }}</div>
                                        <span class="badge bg-primary-subtle text-primary rounded-pill">{{ $project->project_id }}</span>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <span class="badge bg-warning-subtle text-warning">{{ number_format($project->activities_count) }} activities</span>
                                            <span class="badge bg-light text-dark border">{{ number_format($project->sub_activities_count) }} sub</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ number_format((float) ($project->total_budget ?? 0), 2) }}</div>
                                        <small class="text-muted">{{ $project->currency ?: $program->currency }}</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            {{ $project->duration_years_display ?? 'N/A' }} year{{ ($project->duration_years_display ?? 0) == 1 ? '' : 's' }}
                                        </span>
                                    </td>
                                    <td>{{ $project->created_at ? $project->created_at->format('d M Y') : 'N/A' }}</td>
                                    <td class="text-end">
                                        <div class="program-project-actions">
                                            @can('project.view')
                                                <a href="{{ route('budget.projects.show', $project->id) }}"
                                                    class="program-project-action" title="View Project">
                                                    <i class="feather-eye"></i>
                                                </a>
                                            @endcan
                                            @can('project.edit')
                                                <a href="{{ route('budget.projects.edit', $project->id) }}"
                                                    class="program-project-action warning" title="Edit Project">
                                                    <i class="feather-edit"></i>
                                                </a>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">
                                        <div class="program-empty-state text-center">
                                            No projects found for this program.
                                        </div>
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
