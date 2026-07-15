@extends('layouts.ttl')

@section('title', $project->name)
@section('ttl_page_title', $project->name)
@section('ttl_page_subtitle', 'Project oversight view for activities, sub-activities, governance and budget share.')

@push('styles')
    <style>
        .ttl-project-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(0, .9fr);
            gap: 12px;
        }

        .ttl-card {
            border: 1px solid #dbe6df;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .05);
        }

        .ttl-panel,
        .ttl-table-card {
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

        .ttl-table-card .table td {
            vertical-align: middle;
        }

        @media (max-width: 991.98px) {
            .ttl-project-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <div class="ttl-project-grid">
        <section class="ttl-card ttl-panel">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <h5 class="fw-bold mb-1">Project Profile</h5>
                    <div class="text-muted small">{{ $project->description ?: 'No project description has been added.' }}</div>
                </div>
                <span class="badge bg-success-subtle text-success">{{ $project->budget_share_percent }}%</span>
            </div>

            <div class="ttl-row">
                <span>Project ID</span>
                <strong>{{ $project->project_id ?? 'N/A' }}</strong>
            </div>
            <div class="ttl-row">
                <span>Program</span>
                <strong>
                    <a href="{{ route('ttl.programs.show', $project->program) }}" class="text-decoration-none">
                        {{ $project->program?->name ?? 'N/A' }}
                    </a>
                </strong>
            </div>
            <div class="ttl-row">
                <span>Portfolio</span>
                <strong>{{ $project->program?->sector?->name ?? 'Not assigned' }}</strong>
            </div>
            <div class="ttl-row">
                <span>Governance Node</span>
                <strong>
                    {{ $project->governanceNode?->name ?? 'Not assigned' }}
                    @if ($project->governanceNode?->level?->name)
                        <br><small class="text-muted">{{ $project->governanceNode->level->name }}</small>
                    @endif
                </strong>
            </div>
            <div class="ttl-row">
                <span>Duration</span>
                <strong>{{ $project->start_year ?? 'N/A' }} to {{ $project->end_year ?? 'N/A' }}</strong>
            </div>
        </section>

        <section class="ttl-card ttl-panel">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <h5 class="fw-bold mb-1">Budget Share</h5>
                    <div class="text-muted small">Project budget against the parent program envelope.</div>
                </div>
                <i class="feather-pie-chart text-success"></i>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between small fw-bold mb-1">
                    <span>Budget share</span>
                    <span>{{ $project->budget_share_percent }}%</span>
                </div>
                <div class="ttl-progress">
                    <span style="width: {{ $project->budget_share_percent }}%;"></span>
                </div>
            </div>

            <div class="ttl-row">
                <span>Project Budget</span>
                <strong>{{ $project->currency ?? $project->program?->currency ?? 'USD' }} {{ number_format((float) ($project->total_budget ?? 0), 2) }}</strong>
            </div>
            <div class="ttl-row">
                <span>Program Budget</span>
                <strong>{{ $project->program?->currency ?? 'USD' }} {{ number_format((float) ($project->program?->total_budget ?? 0), 2) }}</strong>
            </div>
            <div class="ttl-row">
                <span>Activities</span>
                <strong>{{ number_format($project->activities_count) }}</strong>
            </div>
            <div class="ttl-row">
                <span>Sub-Activities</span>
                <strong>{{ number_format($project->sub_activities_count) }}</strong>
            </div>
        </section>
    </div>

    <section class="ttl-card ttl-table-card mt-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h5 class="fw-bold mb-1">Activities</h5>
                <div class="text-muted small">Delivery activities and sub-activities under this project.</div>
            </div>
            <a href="{{ route('ttl.programs.show', $project->program) }}" class="btn btn-sm btn-outline-success">
                <i class="feather-arrow-left me-1"></i> Program
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Activity</th>
                        <th>Governance</th>
                        <th>Sub-Activities</th>
                        <th class="text-end">Budget</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($project->activities as $activity)
                        <tr>
                            <td>
                                <div class="fw-bold text-dark">{{ $activity->name }}</div>
                                @if ($activity->description)
                                    <small class="text-muted">{{ Str::limit($activity->description, 90) }}</small>
                                @endif
                            </td>
                            <td>
                                <div>{{ $activity->governanceNode?->name ?? 'N/A' }}</div>
                                <small class="text-muted">{{ $activity->governanceNode?->level?->name ?? '' }}</small>
                            </td>
                            <td>
                                @if ($activity->subActivities->count() > 0)
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach ($activity->subActivities as $subActivity)
                                            <span class="badge bg-light text-dark border">{{ Str::limit($subActivity->name, 28) }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-muted">None</span>
                                @endif
                            </td>
                            <td class="text-end">{{ $activity->currency ?? $project->currency ?? 'USD' }} {{ number_format((float) ($activity->budget ?? 0), 2) }}</td>
                            <td><span class="badge bg-info-subtle text-info">{{ ucfirst($activity->status ?? 'active') }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No activities have been linked yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
