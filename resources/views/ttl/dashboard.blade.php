@extends('layouts.ttl')

@section('title', 'TTL Dashboard')
@section('ttl_page_title', 'TTL Dashboard')
@section('ttl_page_subtitle', 'Assigned ATTP programs, project structures, funding context and delivery progress.')

@push('styles')
    <style>
        .ttl-stat-grid,
        .ttl-program-grid {
            display: grid;
            gap: 12px;
        }

        .ttl-stat-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .ttl-program-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .ttl-card {
            border: 1px solid #dbe6df;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .05);
        }

        .ttl-stat-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
        }

        .ttl-stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            color: #006b3f;
            background: #dff5ee;
            font-size: 1.05rem;
        }

        .ttl-stat-icon.wine {
            color: #522b39;
            background: #f8e8ef;
        }

        .ttl-stat-label {
            color: #667085;
            font-size: .75rem;
            font-weight: 900;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .ttl-stat-value {
            color: #142033;
            font-size: 1.45rem;
            font-weight: 900;
            line-height: 1.1;
        }

        .ttl-progress {
            height: 9px;
            overflow: hidden;
            border-radius: 999px;
            background: #e2e8f0;
        }

        .ttl-progress span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #006b3f 0%, #d97706 100%);
        }

        .ttl-program-card {
            padding: 16px;
        }

        .ttl-program-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin: 10px 0;
        }

        .ttl-mini-pill {
            border: 1px solid #dbe6df;
            border-radius: 999px;
            padding: 5px 8px;
            color: #064e3b;
            background: #f3f8f5;
            font-size: .76rem;
            font-weight: 800;
        }

        .ttl-table-card {
            padding: 16px;
        }

        .ttl-table-card .table td {
            vertical-align: middle;
        }

        @media (max-width: 1199.98px) {
            .ttl-stat-grid,
            .ttl-program-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .ttl-stat-grid,
            .ttl-program-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <div class="ttl-stat-grid">
        <div class="ttl-card ttl-stat-card">
            <div class="ttl-stat-icon"><i class="feather-folder"></i></div>
            <div>
                <div class="ttl-stat-label">Programs</div>
                <div class="ttl-stat-value">{{ number_format($stats['programs']) }}</div>
                <small class="text-muted">Assigned to you</small>
            </div>
        </div>
        <div class="ttl-card ttl-stat-card">
            <div class="ttl-stat-icon"><i class="feather-layers"></i></div>
            <div>
                <div class="ttl-stat-label">Projects</div>
                <div class="ttl-stat-value">{{ number_format($stats['projects']) }}</div>
                <small class="text-muted">{{ number_format($stats['activities']) }} activities</small>
            </div>
        </div>
        <div class="ttl-card ttl-stat-card">
            <div class="ttl-stat-icon"><i class="feather-git-branch"></i></div>
            <div>
                <div class="ttl-stat-label">Sub-Activities</div>
                <div class="ttl-stat-value">{{ number_format($stats['sub_activities']) }}</div>
                <small class="text-muted">Delivery packages</small>
            </div>
        </div>
        <div class="ttl-card ttl-stat-card">
            <div class="ttl-stat-icon wine"><i class="feather-trending-up"></i></div>
            <div>
                <div class="ttl-stat-label">Progress</div>
                <div class="ttl-stat-value">{{ $stats['progress'] }}%</div>
                <small class="text-muted">Project budget against program envelope</small>
            </div>
        </div>
    </div>

    <section id="ttl-programs" class="mt-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <div>
                <h5 class="fw-bold mb-1">Assigned Programs</h5>
                <div class="text-muted small">Read-only TTL view of program scope, funding and progress.</div>
            </div>
            <span class="badge bg-success-subtle text-success">{{ number_format($programs->count()) }} records</span>
        </div>

        <div class="ttl-program-grid">
            @foreach ($programs as $program)
                <article class="ttl-card ttl-program-card">
                    <div class="d-flex justify-content-between gap-3">
                        <div class="min-w-0">
                            <h6 class="fw-bold mb-1 text-truncate">{{ $program->name }}</h6>
                            <div class="text-muted small">{{ $program->sector?->name ?? 'No portfolio' }}</div>
                        </div>
                        <span class="badge bg-success-subtle text-success">{{ $program->progress_percent }}%</span>
                    </div>

                    <div class="ttl-program-meta">
                        <span class="ttl-mini-pill"><i class="feather-briefcase me-1"></i>{{ $program->governanceNode?->name ?? 'No node' }}</span>
                        <span class="ttl-mini-pill"><i class="feather-dollar-sign me-1"></i>{{ $program->currency ?? 'USD' }} {{ number_format((float) ($program->total_budget ?? 0), 2) }}</span>
                        <span class="ttl-mini-pill"><i class="feather-users me-1"></i>{{ number_format($program->fundings->count()) }} funders</span>
                    </div>

                    <div class="ttl-progress mb-3">
                        <span style="width: {{ $program->progress_percent }}%;"></span>
                    </div>

                    <div class="row g-2 small text-muted mb-3">
                        <div class="col-4"><strong class="d-block text-dark">{{ number_format($program->projects->count()) }}</strong>Projects</div>
                        <div class="col-4"><strong class="d-block text-dark">{{ number_format($program->activities_count) }}</strong>Activities</div>
                        <div class="col-4"><strong class="d-block text-dark">{{ number_format($program->sub_activities_count) }}</strong>Sub</div>
                    </div>

                    <a href="{{ route('ttl.programs.show', $program) }}" class="btn btn-sm btn-success">
                        <i class="feather-eye me-1"></i> Open Program
                    </a>
                </article>
            @endforeach
        </div>
    </section>

    <section id="ttl-projects" class="ttl-card ttl-table-card mt-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h5 class="fw-bold mb-1">Project Register</h5>
                <div class="text-muted small">Projects linked to your assigned programs.</div>
            </div>
            <span class="badge bg-primary-subtle text-primary">{{ number_format($projects->count()) }} projects</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Project</th>
                        <th>Program</th>
                        <th>Governance</th>
                        <th>Hierarchy</th>
                        <th class="text-end">Budget</th>
                        <th class="text-center">Progress</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($projects as $project)
                        <tr>
                            <td>
                                <div class="fw-bold text-dark">{{ $project->name }}</div>
                                <small class="text-muted">{{ $project->project_id ?? 'No project ID' }}</small>
                            </td>
                            <td>{{ $project->program?->name ?? 'N/A' }}</td>
                            <td>
                                <div>{{ $project->governanceNode?->name ?? 'N/A' }}</div>
                                <small class="text-muted">{{ $project->governanceNode?->level?->name ?? '' }}</small>
                            </td>
                            <td>
                                <span class="badge bg-warning-subtle text-warning">{{ number_format($project->activities_count) }} activities</span>
                                <span class="badge bg-light text-dark border">{{ number_format($project->sub_activities_count) }} sub</span>
                            </td>
                            <td class="text-end">
                                <strong>{{ $project->currency ?? $project->program?->currency ?? 'USD' }} {{ number_format((float) ($project->total_budget ?? 0), 2) }}</strong>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-success-subtle text-success">{{ $project->budget_share_percent }}%</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('ttl.projects.show', $project) }}" class="btn btn-sm btn-outline-success">
                                    <i class="feather-arrow-right"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="text-center text-muted py-4">No projects have been linked yet.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
