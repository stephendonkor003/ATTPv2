@extends('layouts.ttl')

@section('title', $program->name)
@section('ttl_page_title', $program->name)
@section('ttl_page_subtitle', 'Program oversight view for portfolio, funding, projects and delivery structure.')

@push('styles')
    <style>
        .ttl-detail-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(0, .85fr);
            gap: 12px;
        }

        .ttl-card {
            border: 1px solid #dbe6df;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .05);
        }

        .ttl-panel {
            padding: 16px;
        }

        .ttl-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px dashed #dbe6df;
        }

        .ttl-row:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .ttl-row span {
            color: #667085;
            font-weight: 800;
        }

        .ttl-row strong {
            text-align: right;
        }

        .ttl-progress {
            height: 10px;
            border-radius: 999px;
            overflow: hidden;
            background: #e2e8f0;
        }

        .ttl-progress span {
            display: block;
            height: 100%;
            background: linear-gradient(90deg, #006b3f 0%, #d97706 100%);
        }

        .ttl-table-card {
            padding: 16px;
        }

        .ttl-table-card .table td {
            vertical-align: middle;
        }

        @media (max-width: 991.98px) {
            .ttl-detail-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <div class="ttl-detail-grid">
        <section class="ttl-card ttl-panel">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <h5 class="fw-bold mb-1">Program Profile</h5>
                    <div class="text-muted small">{{ $program->description ?: 'No description has been added.' }}</div>
                </div>
                <span class="badge bg-success-subtle text-success">{{ $program->progress_percent }}%</span>
            </div>

            <div class="ttl-row">
                <span>Program ID</span>
                <strong>{{ $program->program_id ?? 'N/A' }}</strong>
            </div>
            <div class="ttl-row">
                <span>Portfolio</span>
                <strong>{{ $program->sector?->name ?? 'Not assigned' }}</strong>
            </div>
            <div class="ttl-row">
                <span>Governance Node</span>
                <strong>
                    {{ $program->governanceNode?->name ?? 'Not assigned' }}
                    @if ($program->governanceNode?->level?->name)
                        <br><small class="text-muted">{{ $program->governanceNode->level->name }}</small>
                    @endif
                </strong>
            </div>
            <div class="ttl-row">
                <span>Duration</span>
                <strong>{{ $program->start_year ?? 'N/A' }} to {{ $program->end_year ?? 'N/A' }}</strong>
            </div>
            <div class="ttl-row">
                <span>TTL</span>
                <strong>
                    {{ $program->ttl_name ?: auth()->user()->name }}
                    @if ($program->ttl_email)
                        <br><small class="text-muted">{{ $program->ttl_email }}</small>
                    @endif
                </strong>
            </div>
        </section>

        <section class="ttl-card ttl-panel">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <h5 class="fw-bold mb-1">Budget Progress</h5>
                    <div class="text-muted small">Project budgets against the approved program envelope.</div>
                </div>
                <i class="feather-pie-chart text-success"></i>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between small fw-bold mb-1">
                    <span>Assigned to projects</span>
                    <span>{{ $program->progress_percent }}%</span>
                </div>
                <div class="ttl-progress">
                    <span style="width: {{ $program->progress_percent }}%;"></span>
                </div>
            </div>

            <div class="ttl-row">
                <span>Program Budget</span>
                <strong>{{ $program->currency ?? 'USD' }} {{ number_format((float) ($program->total_budget ?? 0), 2) }}</strong>
            </div>
            <div class="ttl-row">
                <span>Project Budget</span>
                <strong>{{ $program->currency ?? 'USD' }} {{ number_format((float) $program->project_budget_value, 2) }}</strong>
            </div>
            <div class="ttl-row">
                <span>Projects</span>
                <strong>{{ number_format($program->projects->count()) }}</strong>
            </div>
            <div class="ttl-row">
                <span>Activities</span>
                <strong>{{ number_format($program->activities_count) }}</strong>
            </div>
            <div class="ttl-row">
                <span>Sub-Activities</span>
                <strong>{{ number_format($program->sub_activities_count) }}</strong>
            </div>
        </section>
    </div>

    <section class="ttl-card ttl-table-card mt-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h5 class="fw-bold mb-1">Funding Context</h5>
                <div class="text-muted small">Approved funding records linked to this program.</div>
            </div>
            <span class="badge bg-success-subtle text-success">{{ number_format($program->fundings->count()) }} records</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Funder</th>
                        <th>Funding Type</th>
                        <th>Period</th>
                        <th class="text-end">Approved Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($program->fundings as $funding)
                        <tr>
                            <td class="fw-bold">{{ $funding->funder?->name ?? 'N/A' }}</td>
                            <td>{{ ucfirst($funding->funding_type ?? 'grant') }}</td>
                            <td>{{ $funding->start_year ?? 'N/A' }} - {{ $funding->end_year ?? 'N/A' }}</td>
                            <td class="text-end">{{ $funding->currency ?? $program->currency ?? 'USD' }} {{ number_format((float) ($funding->approved_amount ?? 0), 2) }}</td>
                            <td><span class="badge bg-success-subtle text-success">{{ ucfirst($funding->status ?? 'approved') }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No funding records are linked yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="ttl-card ttl-table-card mt-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h5 class="fw-bold mb-1">Projects</h5>
                <div class="text-muted small">Project and activity structure under this program.</div>
            </div>
            <a href="{{ route('ttl.dashboard') }}#ttl-projects" class="btn btn-sm btn-outline-success">
                <i class="feather-list me-1"></i> All Projects
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Project</th>
                        <th>Governance</th>
                        <th>Hierarchy</th>
                        <th class="text-end">Budget</th>
                        <th class="text-center">Progress</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($program->projects as $project)
                        <tr>
                            <td>
                                <div class="fw-bold text-dark">{{ $project->name }}</div>
                                <small class="text-muted">{{ $project->project_id ?? 'No project ID' }}</small>
                            </td>
                            <td>
                                <div>{{ $project->governanceNode?->name ?? 'N/A' }}</div>
                                <small class="text-muted">{{ $project->governanceNode?->level?->name ?? '' }}</small>
                            </td>
                            <td>
                                <span class="badge bg-warning-subtle text-warning">{{ number_format($project->activities_count) }} activities</span>
                                <span class="badge bg-light text-dark border">{{ number_format($project->sub_activities_count) }} sub</span>
                            </td>
                            <td class="text-end">{{ $project->currency ?? $program->currency ?? 'USD' }} {{ number_format((float) ($project->total_budget ?? 0), 2) }}</td>
                            <td class="text-center"><span class="badge bg-success-subtle text-success">{{ $project->budget_share_percent }}%</span></td>
                            <td class="text-end">
                                <a href="{{ route('ttl.projects.show', $project) }}" class="btn btn-sm btn-outline-success">
                                    <i class="feather-arrow-right"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No projects have been linked yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
