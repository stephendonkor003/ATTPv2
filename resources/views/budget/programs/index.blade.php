@extends('layouts.app')

@section('title', 'Programs')

@push('styles')
    <style>
        .program-workspace {
            color: #0f172a;
        }

        .program-hero {
            border-radius: 8px;
            padding: 20px;
            color: #ffffff;
            background: linear-gradient(135deg, #063f36 0%, #0f766e 56%, #522b39 100%);
            box-shadow: 0 18px 36px rgba(6, 63, 54, 0.16);
        }

        .program-hero h4,
        .program-hero p {
            color: #ffffff;
        }

        .program-kicker {
            color: #d9fff4;
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .program-hero-chip {
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

        .program-stat-grid,
        .program-insight-grid {
            display: grid;
            gap: 12px;
        }

        .program-stat-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .program-insight-grid {
            grid-template-columns: minmax(0, 1.2fr) minmax(0, 0.9fr) minmax(0, 1fr);
        }

        .program-stat-card,
        .program-panel,
        .program-table-card {
            border: 1px solid #dbe3ea;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
        }

        .program-stat-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
        }

        .program-stat-icon {
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

        .program-stat-icon.blue {
            color: #075985;
            background: #e0f2fe;
        }

        .program-stat-icon.amber {
            color: #92400e;
            background: #fef3c7;
        }

        .program-stat-icon.wine {
            color: #522b39;
            background: #f8e8ef;
        }

        .program-stat-label {
            color: #64748b;
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .program-stat-value {
            color: #0f172a;
            font-size: 1.55rem;
            font-weight: 800;
            line-height: 1.1;
        }

        .program-stat-card small {
            color: #64748b;
        }

        .program-panel {
            padding: 16px;
        }

        .program-progress {
            height: 8px;
            border-radius: 999px;
            overflow: hidden;
            background: #e2e8f0;
        }

        .program-progress span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #0f766e 0%, #d97706 100%);
        }

        .program-count-list {
            display: grid;
            gap: 8px;
        }

        .program-count-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 12px;
            background: #f8fafc;
        }

        .program-count-item span {
            color: #64748b;
            font-size: 0.84rem;
            font-weight: 700;
        }

        .program-count-item strong {
            color: #0f172a;
            font-size: 1.1rem;
        }

        .program-health-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        .program-health-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 12px;
            background: #ffffff;
        }

        .program-health-card span {
            color: #64748b;
            font-size: 0.8rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .program-health-card strong {
            display: block;
            color: #0f172a;
            font-size: 1.25rem;
            margin-top: 4px;
        }

        .program-name-cell {
            min-width: 280px;
        }

        .program-description {
            display: block;
            max-width: 180px;
        }

        .program-actions {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
            white-space: nowrap;
        }

        .program-icon-action {
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
            transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        }

        .program-icon-action:hover {
            border-color: #1d4ed8;
            color: #ffffff;
            background: #1d4ed8;
        }

        .program-icon-action.warning {
            border-color: #fde68a;
            color: #92400e;
            background: #fffbeb;
        }

        .program-icon-action.warning:hover {
            border-color: #d97706;
            color: #ffffff;
            background: #d97706;
        }

        .program-icon-action.danger {
            border-color: #fecaca;
            color: #b91c1c;
            background: #fef2f2;
        }

        .program-icon-action.danger:hover {
            border-color: #b91c1c;
            color: #ffffff;
            background: #b91c1c;
        }

        .program-empty-state {
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 16px;
            color: #64748b;
            background: #f8fafc;
        }

        .program-table-card .table td {
            vertical-align: middle;
        }

        @media (max-width: 1199.98px) {
            .program-stat-grid,
            .program-insight-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .program-stat-grid,
            .program-insight-grid,
            .program-health-grid {
                grid-template-columns: 1fr;
            }

            .program-hero {
                padding: 16px;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $maxBudget = max(1, (float) $topPrograms->max(fn ($program) => (float) ($program->total_budget ?? 0)));
        $governanceCoverage = $programStats['total'] > 0 ? round(($programStats['governance_assigned'] / $programStats['total']) * 100) : 0;
        $utilization = $programStats['total_budget'] > 0 ? min(100, round(($programStats['project_budget'] / $programStats['total_budget']) * 100)) : 0;
    @endphp

    <div class="nxl-container program-workspace">
        <div class="program-hero">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
                <div>
                    <div class="program-kicker mb-2">ATTP Program Management</div>
                    <h4 class="fw-bold mb-2">Program Control Center</h4>
                    <p class="mb-0">
                        Manage approved programs, portfolio alignment, governance ownership, project budgets, activities, and delivery structure.
                    </p>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <span class="program-hero-chip"><i class="feather-layers"></i> {{ number_format($programStats['total']) }} programs</span>
                        <span class="program-hero-chip"><i class="feather-briefcase"></i> {{ number_format($programStats['portfolios']) }} portfolios</span>
                        <span class="program-hero-chip"><i class="feather-folder"></i> {{ number_format($programStats['projects']) }} projects</span>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-content-start justify-content-xl-end gap-2">
                    <a href="{{ route('budget.portfolios.index') }}" class="btn btn-light">
                        <i class="feather-briefcase me-1"></i> Portfolios
                    </a>
                    @can('program.create')
                        <a href="{{ route('budget.programs.create') }}" class="btn btn-success">
                            <i class="feather-plus-circle me-1"></i> New Program
                        </a>
                    @endcan
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="program-stat-grid mt-3">
            <div class="program-stat-card">
                <span class="program-stat-icon"><i class="feather-layers"></i></span>
                <div>
                    <div class="program-stat-label">Programs</div>
                    <div class="program-stat-value">{{ number_format($programStats['total']) }}</div>
                    <small>{{ number_format($programStats['portfolios']) }} linked portfolios</small>
                </div>
            </div>
            <div class="program-stat-card">
                <span class="program-stat-icon blue"><i class="feather-folder"></i></span>
                <div>
                    <div class="program-stat-label">Projects</div>
                    <div class="program-stat-value">{{ number_format($programStats['projects']) }}</div>
                    <small>{{ number_format($programStats['activities']) }} activities linked</small>
                </div>
            </div>
            <div class="program-stat-card">
                <span class="program-stat-icon amber"><i class="feather-git-branch"></i></span>
                <div>
                    <div class="program-stat-label">Sub-Activities</div>
                    <div class="program-stat-value">{{ number_format($programStats['sub_activities']) }}</div>
                    <small>Across all program activities</small>
                </div>
            </div>
            <div class="program-stat-card">
                <span class="program-stat-icon wine"><i class="feather-dollar-sign"></i></span>
                <div>
                    <div class="program-stat-label">Budget Envelope</div>
                    <div class="program-stat-value" style="font-size: 1.25rem;">{{ number_format((float) $programStats['total_budget'], 2) }}</div>
                    <small>{{ $utilization }}% assigned to projects</small>
                </div>
            </div>
        </div>

        <div class="program-insight-grid mt-3">
            <div class="program-panel">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Largest Programs</h6>
                        <p class="text-muted small mb-0">Ranked by approved program budget.</p>
                    </div>
                    <span class="badge bg-success-subtle text-success">Top {{ number_format($topPrograms->count()) }}</span>
                </div>

                <div class="d-grid gap-3">
                    @forelse ($topPrograms as $program)
                        @php
                            $budget = (float) ($program->total_budget ?? 0);
                            $width = max(3, min(100, ($budget / $maxBudget) * 100));
                        @endphp
                        <div>
                            <div class="d-flex justify-content-between align-items-center gap-3 mb-1">
                                <div class="fw-semibold text-dark text-truncate">{{ $program->name }}</div>
                                <div class="small fw-bold text-nowrap">{{ number_format($budget, 2) }}</div>
                            </div>
                            <div class="program-progress">
                                <span style="width: {{ $width }}%;"></span>
                            </div>
                        </div>
                    @empty
                        <div class="program-empty-state">No programs have been created yet.</div>
                    @endforelse
                </div>
            </div>

            <div class="program-panel">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Hierarchy Coverage</h6>
                        <p class="text-muted small mb-0">Delivery structure under all visible programs.</p>
                    </div>
                    <i class="feather-grid text-success"></i>
                </div>

                <div class="program-count-list">
                    <div class="program-count-item">
                        <span>Projects</span>
                        <strong>{{ number_format($programStats['projects']) }}</strong>
                    </div>
                    <div class="program-count-item">
                        <span>Activities</span>
                        <strong>{{ number_format($programStats['activities']) }}</strong>
                    </div>
                    <div class="program-count-item">
                        <span>Sub-Activities</span>
                        <strong>{{ number_format($programStats['sub_activities']) }}</strong>
                    </div>
                    <div class="program-count-item">
                        <span>Governance</span>
                        <strong>{{ $governanceCoverage }}%</strong>
                    </div>
                </div>
            </div>

            <div class="program-panel">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Budget Readiness</h6>
                        <p class="text-muted small mb-0">Program budget compared with project allocation.</p>
                    </div>
                    <i class="feather-pie-chart text-success"></i>
                </div>

                <div class="program-health-grid">
                    <div class="program-health-card">
                        <span>Program Budget</span>
                        <strong>{{ number_format((float) $programStats['total_budget'], 2) }}</strong>
                    </div>
                    <div class="program-health-card">
                        <span>Project Budget</span>
                        <strong>{{ number_format((float) $programStats['project_budget'], 2) }}</strong>
                    </div>
                    <div class="program-health-card">
                        <span>Utilization</span>
                        <strong>{{ $utilization }}%</strong>
                    </div>
                    <div class="program-health-card">
                        <span>Governance</span>
                        <strong>{{ $governanceCoverage }}%</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-4 shadow-sm border-0 program-table-card">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Program Register</h6>
                        <p class="text-muted small mb-0">Review portfolio alignment, governance, hierarchy depth, and budget utilization.</p>
                    </div>
                    <span class="badge bg-success-subtle text-success">{{ number_format($programs->count()) }} records</span>
                </div>

                <x-data-table id="programsTable">
                    <thead class="table-light">
                        <tr>
                            <th width="50">#</th>
                            <th>Program</th>
                            <th>Portfolio</th>
                            <th>Governance</th>
                            <th>TTL</th>
                            <th>Hierarchy</th>
                            <th>Budget</th>
                            <th>Updated</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($programs as $program)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <div class="program-name-cell">
                                        <div class="fw-semibold text-dark">{{ $program->name }}</div>
                                        <span class="badge bg-primary-subtle text-primary rounded-pill mb-1">
                                            {{ $program->program_id }}
                                        </span>
                                        <small class="text-muted program-description">
                                            {{ $program->description ? Str::limit($program->description, 15, '...') : 'No description.' }}
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info-subtle text-info">
                                        {{ $program->sector->name ?? '-' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semibold">
                                        {{ $program->governanceNode->name ?? 'Unassigned' }}
                                    </div>
                                    <small class="text-muted">
                                        {{ $program->governanceNode->level->name ?? 'No governance level' }}
                                    </small>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $program->ttl_name ?: ($program->ttlUser?->name ?? 'Unassigned') }}</div>
                                    <small class="text-muted">{{ $program->ttl_email ?: ($program->ttlUser?->email ?? 'No email') }}</small>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <span class="badge bg-primary-subtle text-primary">{{ number_format($program->projects_count) }} projects</span>
                                        <span class="badge bg-warning-subtle text-warning">{{ number_format($program->activities_count) }} activities</span>
                                        <span class="badge bg-light text-dark border">{{ number_format($program->sub_activities_count) }} sub</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ number_format((float) ($program->total_budget ?? 0), 2) }}</div>
                                    <small class="text-muted">{{ $program->budget_utilization_percent }}% project assigned</small>
                                </td>
                                <td>
                                    @if ($program->latest_structure_update_at)
                                        <div class="fw-semibold text-dark">{{ $program->latest_structure_update_at->format('d M Y') }}</div>
                                        <small class="text-muted">{{ $program->latest_structure_update_at->format('H:i') }}</small>
                                    @else
                                        <span class="text-muted">Not logged</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="program-actions">
                                        @can('program.view')
                                            <a href="{{ route('budget.programs.show', $program->id) }}"
                                                class="program-icon-action" title="View Program">
                                                <i class="feather-eye"></i>
                                            </a>
                                        @endcan
                                        @can('program.edit')
                                            <a href="{{ route('budget.programs.edit', $program->id) }}"
                                                class="program-icon-action warning" title="Edit Program">
                                                <i class="feather-edit"></i>
                                            </a>
                                        @endcan
                                        @can('program.delete')
                                            <form action="{{ route('budget.programs.destroy', $program->id) }}" method="POST"
                                                onsubmit="return confirm('Delete this program?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="program-icon-action danger" title="Delete Program">
                                                    <i class="feather-trash-2"></i>
                                                </button>
                                            </form>
                                        @endcan
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
