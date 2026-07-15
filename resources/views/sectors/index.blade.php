@extends('layouts.app')

@section('title', 'Portfolios')

@push('styles')
    <style>
        .portfolio-workspace {
            color: #0f172a;
        }

        .portfolio-hero {
            border-radius: 8px;
            padding: 20px;
            color: #ffffff;
            background: linear-gradient(135deg, #063f36 0%, #0f766e 56%, #522b39 100%);
            box-shadow: 0 18px 36px rgba(6, 63, 54, 0.16);
        }

        .portfolio-hero h4,
        .portfolio-hero p {
            color: #ffffff;
        }

        .portfolio-kicker {
            color: #d9fff4;
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .portfolio-hero-chip {
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

        .portfolio-stat-grid,
        .portfolio-insight-grid {
            display: grid;
            gap: 12px;
        }

        .portfolio-stat-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .portfolio-insight-grid {
            grid-template-columns: minmax(0, 1.2fr) minmax(0, 0.9fr) minmax(0, 1fr);
        }

        .portfolio-stat-card,
        .portfolio-panel,
        .portfolio-table-card {
            border: 1px solid #dbe3ea;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
        }

        .portfolio-stat-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
        }

        .portfolio-stat-icon {
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

        .portfolio-stat-icon.blue {
            color: #075985;
            background: #e0f2fe;
        }

        .portfolio-stat-icon.amber {
            color: #92400e;
            background: #fef3c7;
        }

        .portfolio-stat-icon.wine {
            color: #522b39;
            background: #f8e8ef;
        }

        .portfolio-stat-label {
            color: #64748b;
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .portfolio-stat-value {
            color: #0f172a;
            font-size: 1.55rem;
            font-weight: 800;
            line-height: 1.1;
        }

        .portfolio-stat-card small {
            color: #64748b;
        }

        .portfolio-panel {
            padding: 16px;
        }

        .portfolio-progress {
            height: 8px;
            border-radius: 999px;
            overflow: hidden;
            background: #e2e8f0;
        }

        .portfolio-progress span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #0f766e 0%, #d97706 100%);
        }

        .portfolio-count-list {
            display: grid;
            gap: 8px;
        }

        .portfolio-count-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 12px;
            background: #f8fafc;
        }

        .portfolio-count-item span {
            color: #64748b;
            font-size: 0.84rem;
            font-weight: 700;
        }

        .portfolio-count-item strong {
            color: #0f172a;
            font-size: 1.1rem;
        }

        .portfolio-health-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        .portfolio-health-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 12px;
            background: #ffffff;
        }

        .portfolio-health-card span {
            color: #64748b;
            font-size: 0.8rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .portfolio-health-card strong {
            display: block;
            color: #0f172a;
            font-size: 1.25rem;
            margin-top: 4px;
        }

        .portfolio-name-cell {
            min-width: 280px;
        }

        .portfolio-description {
            display: block;
            max-width: 280px;
        }

        .portfolio-actions {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
            white-space: nowrap;
        }

        .portfolio-icon-action {
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

        .portfolio-icon-action:hover {
            border-color: #1d4ed8;
            color: #ffffff;
            background: #1d4ed8;
        }

        .portfolio-icon-action.danger {
            border-color: #fecaca;
            color: #b91c1c;
            background: #fef2f2;
        }

        .portfolio-icon-action.danger:hover {
            border-color: #b91c1c;
            color: #ffffff;
            background: #b91c1c;
        }

        .portfolio-empty-state {
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 16px;
            color: #64748b;
            background: #f8fafc;
        }

        .portfolio-table-card .table td {
            vertical-align: middle;
        }

        @media (max-width: 1199.98px) {
            .portfolio-stat-grid,
            .portfolio-insight-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .portfolio-stat-grid,
            .portfolio-insight-grid,
            .portfolio-health-grid {
                grid-template-columns: 1fr;
            }

            .portfolio-hero {
                padding: 16px;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $maxBudget = max(1, (float) $topPortfolios->max(fn ($portfolio) => (float) $portfolio->total_budget_value));
        $ttlCoverage = $portfolioStats['total'] > 0 ? round(($portfolioStats['ttl_assigned'] / $portfolioStats['total']) * 100) : 0;
        $governanceCoverage = $portfolioStats['total'] > 0 ? round(($portfolioStats['governance_assigned'] / $portfolioStats['total']) * 100) : 0;
    @endphp

    <div class="nxl-container portfolio-workspace">
        <div class="portfolio-hero">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
                <div>
                    <div class="portfolio-kicker mb-2">ATTP Portfolio Management</div>
                    <h4 class="fw-bold mb-2">Portfolio Control Center</h4>
                    <p class="mb-0">
                        Manage portfolio ownership, governance alignment, TTL coverage, and the budget hierarchy across programs, projects, activities, and sub-activities.
                    </p>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <span class="portfolio-hero-chip"><i class="feather-briefcase"></i> {{ number_format($portfolioStats['total']) }} portfolios</span>
                        <span class="portfolio-hero-chip"><i class="feather-layers"></i> {{ number_format($portfolioStats['programs']) }} programs</span>
                        <span class="portfolio-hero-chip"><i class="feather-check-circle"></i> {{ number_format($portfolioStats['active']) }} active</span>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-content-start justify-content-xl-end gap-2">
                    <a href="{{ route('budget.programs.index') }}" class="btn btn-light">
                        <i class="feather-list me-1"></i> Programs
                    </a>
                    @can('sector.create')
                        <a href="{{ route('budget.portfolios.create') }}" class="btn btn-success">
                            <i class="feather-plus-circle me-1"></i> New Portfolio
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

        <div class="portfolio-stat-grid mt-3">
            <div class="portfolio-stat-card">
                <span class="portfolio-stat-icon"><i class="feather-briefcase"></i></span>
                <div>
                    <div class="portfolio-stat-label">Total Portfolios</div>
                    <div class="portfolio-stat-value">{{ number_format($portfolioStats['total']) }}</div>
                    <small>{{ number_format($portfolioStats['active']) }} active, {{ number_format($portfolioStats['ended']) }} ended</small>
                </div>
            </div>
            <div class="portfolio-stat-card">
                <span class="portfolio-stat-icon blue"><i class="feather-layers"></i></span>
                <div>
                    <div class="portfolio-stat-label">Programs</div>
                    <div class="portfolio-stat-value">{{ number_format($portfolioStats['programs']) }}</div>
                    <small>{{ number_format($portfolioStats['projects']) }} projects linked</small>
                </div>
            </div>
            <div class="portfolio-stat-card">
                <span class="portfolio-stat-icon amber"><i class="feather-git-branch"></i></span>
                <div>
                    <div class="portfolio-stat-label">Activities</div>
                    <div class="portfolio-stat-value">{{ number_format($portfolioStats['activities']) }}</div>
                    <small>{{ number_format($portfolioStats['sub_activities']) }} sub-activities</small>
                </div>
            </div>
            <div class="portfolio-stat-card">
                <span class="portfolio-stat-icon wine"><i class="feather-dollar-sign"></i></span>
                <div>
                    <div class="portfolio-stat-label">Budget Envelope</div>
                    <div class="portfolio-stat-value" style="font-size: 1.25rem;">{{ $portfolioStats['currency'] }} {{ number_format((float) $portfolioStats['budget'], 2) }}</div>
                    <small>Program budget total</small>
                </div>
            </div>
        </div>

        <div class="portfolio-insight-grid mt-3">
            <div class="portfolio-panel">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Largest Portfolios</h6>
                        <p class="text-muted small mb-0">Ranked by program budget envelope.</p>
                    </div>
                    <span class="badge bg-success-subtle text-success">Top {{ number_format($topPortfolios->count()) }}</span>
                </div>

                <div class="d-grid gap-3">
                    @forelse ($topPortfolios as $portfolio)
                        @php
                            $budget = (float) $portfolio->total_budget_value;
                            $width = max(3, min(100, ($budget / $maxBudget) * 100));
                        @endphp
                        <div>
                            <div class="d-flex justify-content-between align-items-center gap-3 mb-1">
                                <div class="fw-semibold text-dark text-truncate">{{ $portfolio->name }}</div>
                                <div class="small fw-bold text-nowrap">{{ $portfolio->currency ?: 'USD' }} {{ number_format($budget, 2) }}</div>
                            </div>
                            <div class="portfolio-progress">
                                <span style="width: {{ $width }}%;"></span>
                            </div>
                        </div>
                    @empty
                        <div class="portfolio-empty-state">No portfolios have been created yet.</div>
                    @endforelse
                </div>
            </div>

            <div class="portfolio-panel">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Hierarchy Coverage</h6>
                        <p class="text-muted small mb-0">Structure under all visible portfolios.</p>
                    </div>
                    <i class="feather-grid text-success"></i>
                </div>

                <div class="portfolio-count-list">
                    <div class="portfolio-count-item">
                        <span>Programs</span>
                        <strong>{{ number_format($portfolioStats['programs']) }}</strong>
                    </div>
                    <div class="portfolio-count-item">
                        <span>Projects</span>
                        <strong>{{ number_format($portfolioStats['projects']) }}</strong>
                    </div>
                    <div class="portfolio-count-item">
                        <span>Activities</span>
                        <strong>{{ number_format($portfolioStats['activities']) }}</strong>
                    </div>
                    <div class="portfolio-count-item">
                        <span>Sub-Activities</span>
                        <strong>{{ number_format($portfolioStats['sub_activities']) }}</strong>
                    </div>
                </div>
            </div>

            <div class="portfolio-panel">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Ownership Readiness</h6>
                        <p class="text-muted small mb-0">Governance and TTL assignment coverage.</p>
                    </div>
                    <i class="feather-user-check text-success"></i>
                </div>

                <div class="portfolio-health-grid">
                    <div class="portfolio-health-card">
                        <span>TTL Coverage</span>
                        <strong>{{ $ttlCoverage }}%</strong>
                        <small class="text-muted">{{ number_format($portfolioStats['ttl_assigned']) }} assigned</small>
                    </div>
                    <div class="portfolio-health-card">
                        <span>Governance</span>
                        <strong>{{ $governanceCoverage }}%</strong>
                        <small class="text-muted">{{ number_format($portfolioStats['governance_assigned']) }} assigned</small>
                    </div>
                    <div class="portfolio-health-card">
                        <span>Active</span>
                        <strong>{{ number_format($portfolioStats['active']) }}</strong>
                        <small class="text-muted">current portfolios</small>
                    </div>
                    <div class="portfolio-health-card">
                        <span>Ended</span>
                        <strong>{{ number_format($portfolioStats['ended']) }}</strong>
                        <small class="text-muted">closed portfolios</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-4 shadow-sm border-0 portfolio-table-card">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Portfolio Register</h6>
                        <p class="text-muted small mb-0">Review ownership, governance scope, and hierarchy depth for each portfolio.</p>
                    </div>
                    <span class="badge bg-success-subtle text-success">{{ number_format($sectors->count()) }} records</span>
                </div>

                <x-data-table id="sectorsTable">
                    <thead class="table-light">
                        <tr>
                            <th width="50">#</th>
                            <th>Portfolio</th>
                            <th>Ownership</th>
                            <th>Hierarchy</th>
                            <th>Budget</th>
                            <th>Governance</th>
                            <th>Updated</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sectors as $sector)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <div class="portfolio-name-cell">
                                        <div class="fw-semibold text-dark">{{ $sector->name }}</div>
                                        <span class="badge {{ ($sector->status ?? 'active') === 'ended' ? 'bg-secondary' : 'bg-success' }} rounded-pill mb-1">
                                            {{ ucfirst($sector->status ?? 'active') }}
                                        </span>
                                        <small class="text-muted portfolio-description">
                                            {{ $sector->description ? Str::limit($sector->description, 15, '...') : 'No description added.' }}
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $sector->portfolio_manager_name ?: optional($sector->portfolioManager)->name ?: 'No portfolio leader' }}</div>
                                    <small class="text-muted d-block">
                                        {{ $sector->portfolio_manager_role ?: optional(optional($sector->portfolioManager)->role)->name ?: 'Leader role not set' }}
                                    </small>
                                    <small class="text-muted">{{ $sector->ttl_name ?: 'No TTL assigned' }}</small>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <span class="badge bg-primary-subtle text-primary">{{ number_format($sector->programs_count) }} programs</span>
                                        <span class="badge bg-info-subtle text-info">{{ number_format($sector->projects_count) }} projects</span>
                                        <span class="badge bg-warning-subtle text-warning">{{ number_format($sector->activities_count) }} activities</span>
                                        <span class="badge bg-light text-dark border">{{ number_format($sector->sub_activities_count) }} sub</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $sector->currency ?: 'USD' }} {{ number_format((float) $sector->total_budget_value, 2) }}</div>
                                    <small class="text-muted">Program total budget</small>
                                </td>
                                <td>
                                    <div class="fw-semibold">
                                        {{ $sector->governanceNode->name ?? 'Unassigned' }}
                                    </div>
                                    <small class="text-muted">
                                        {{ $sector->governanceNode->level->name ?? 'No governance level' }}
                                    </small>
                                </td>
                                <td>
                                    @if ($sector->latest_structure_update_at)
                                        <div class="fw-semibold text-dark">{{ $sector->latest_structure_update_at->format('d M Y') }}</div>
                                        <small class="text-muted">{{ $sector->latest_structure_update_at->format('H:i') }}</small>
                                    @else
                                        <span class="text-muted">Not logged</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="portfolio-actions">
                                        <a href="{{ route('budget.portfolios.show', $sector) }}" class="portfolio-icon-action" title="View Portfolio">
                                            <i class="feather-eye"></i>
                                        </a>
                                        @can('sector.edit')
                                            <a href="{{ route('budget.portfolios.edit', $sector) }}" class="portfolio-icon-action" title="Edit Portfolio">
                                                <i class="feather-edit-2"></i>
                                            </a>
                                        @endcan
                                        @can('sector.delete')
                                            <form action="{{ route('budget.portfolios.destroy', $sector) }}" method="POST"
                                                onsubmit="return confirm('Delete this portfolio? Programs linked to it may be affected.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="portfolio-icon-action danger" title="Delete Portfolio">
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
