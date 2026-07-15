@extends('layouts.partner')

@section('content')
<div class="nxl-container">
    <!-- Breadcrumb Navigation -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('partner.dashboard') }}">{{ __('partner.dashboard') }}</a></li>
            <li class="breadcrumb-item"><a href="{{ route('partner.programs.index') }}">{{ __('partner.funded_programs') }}</a></li>
            <li class="breadcrumb-item"><a href="{{ route('partner.programs.show', $funding->id) }}">{{ $project->program->name }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $project->name }}</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1">{{ $project->name }}</h4>
                <p class="text-muted mb-0">{{ __('partner.project_details_description') }}</p>
            </div>
            <a href="{{ route('partner.programs.show', $funding->id) }}" class="btn btn-light">
                <i class="feather-arrow-left me-1"></i> {{ __('partner.back_to_program') }}
            </a>
        </div>
    </div>

    <div class="row mt-3">
        <!-- Main Project Information -->
        <div class="col-lg-8">
            <!-- Project Overview Card -->
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="feather-info me-2"></i>{{ __('partner.project_overview') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small">{{ __('partner.project_name') }}</label>
                            <p class="fw-semibold mb-0">{{ $project->name }}</p>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted small">{{ __('partner.program') }}</label>
                            <p class="fw-semibold mb-0">
                                <a href="{{ route('partner.programs.show', $funding->id) }}" class="text-decoration-none">
                                    {{ $project->program->name }}
                                </a>
                            </p>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted small">{{ __('partner.governance_node') }}</label>
                            <p class="fw-semibold mb-0">
                                {{ $project->governanceNode->name ?? '-' }}
                                @if($project->governanceNode)
                                    <br><small class="text-muted">{{ $project->governanceNode->level->name ?? '' }}</small>
                                @endif
                            </p>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted small">{{ __('partner.budget') }}</label>
                            <p class="fw-semibold mb-0 text-success">
                                {{ $project->currency ?? $funding->currency ?? $funder->currency }} {{ number_format($project->total_budget ?? 0, 2) }}
                            </p>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted small">Task Team Leader</label>
                            <p class="fw-semibold mb-0">
                                {{ $project->program?->ttl_name ?: ($project->program?->ttlUser?->name ?? 'Unassigned') }}
                                @if($project->program?->ttl_email || $project->program?->ttlUser?->email)
                                    <br><small class="text-muted">{{ $project->program?->ttl_email ?: $project->program?->ttlUser?->email }}</small>
                                @endif
                            </p>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted small">Program Progress Share</label>
                            <p class="fw-semibold mb-0">
                                <span class="badge bg-success-subtle text-success">{{ $project->partner_progress_percent ?? 0 }}%</span>
                            </p>
                        </div>

                        @if($project->description)
                            <div class="col-md-12">
                                <label class="text-muted small">{{ __('partner.description') }}</label>
                                <p class="mb-0">{{ $project->description }}</p>
                            </div>
                        @endif

                        @if($project->start_date || $project->end_date)
                            <div class="col-md-6">
                                <label class="text-muted small">{{ __('partner.start_date') }}</label>
                                <p class="fw-semibold mb-0">{{ $project->start_date ? $project->start_date->format('M d, Y') : '—' }}</p>
                            </div>

                            <div class="col-md-6">
                                <label class="text-muted small">{{ __('partner.end_date') }}</label>
                                <p class="fw-semibold mb-0">{{ $project->end_date ? $project->end_date->format('M d, Y') : '—' }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Activities Under This Project -->
            @if($project->activities && $project->activities->count() > 0)
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="feather-activity me-2"></i>{{ __('partner.activities') }} ({{ $project->activities->count() }})</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('partner.activity_name') }}</th>
                                        <th>{{ __('partner.governance_node') }}</th>
                                        <th class="text-end">{{ __('partner.budget') }}</th>
                                        <th class="text-center">{{ __('partner.sub_activities') }}</th>
                                        <th>{{ __('partner.status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($project->activities as $activity)
                                        <tr>
                                            <td>
                                                <a href="{{ route('partner.activities.show', $activity->id) }}" class="text-decoration-none">
                                                    <strong class="text-primary">{{ $activity->name }}</strong>
                                                </a>
                                                @if($activity->description)
                                                    <br><small class="text-muted">{{ Str::limit($activity->description, 80) }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                {{ $activity->governanceNode->name ?? '-' }}
                                                @if($activity->governanceNode)
                                                    <br><small class="text-muted">{{ $activity->governanceNode->level->name ?? '' }}</small>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <strong>{{ $activity->currency ?? $project->currency ?? $funding->currency }} {{ number_format($activity->budget ?? 0, 2) }}</strong>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-info">{{ $activity->subActivities->count() }}</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $activity->status === 'completed' ? 'success' : ($activity->status === 'in_progress' ? 'warning' : 'secondary') }}">
                                                    {{ ucfirst($activity->status ?? 'pending') }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @else
                <div class="alert alert-info">
                    <i class="feather-info-circle me-2"></i>
                    {{ __('partner.no_activities_found') }}
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Project Summary Card -->
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="feather-clipboard me-2"></i>{{ __('partner.project_summary') }}</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small">{{ __('partner.total_activities') }}</label>
                        <p class="fw-bold mb-0 fs-4 text-primary">{{ $project->activities->count() }}</p>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small">{{ __('partner.total_sub_activities') }}</label>
                        <p class="fw-bold mb-0 fs-4 text-success">
                            {{ $project->activities->sum(fn($a) => $a->subActivities->count()) }}
                        </p>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small">{{ __('partner.project_status') }}</label>
                        <p class="fw-semibold mb-0">
                            <span class="badge bg-{{ $project->status === 'completed' ? 'success' : ($project->status === 'active' ? 'info' : 'secondary') }}">
                                {{ ucfirst($project->status ?? 'active') }}
                            </span>
                        </p>
                    </div>

                    @if($project->total_budget)
                        <div class="mb-0">
                            <label class="text-muted small">{{ __('partner.total_budget') }}</label>
                            <p class="fw-bold mb-0 fs-5 text-success">
                                {{ $project->currency ?? $funding->currency }} {{ number_format($project->total_budget, 2) }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Quick Actions Card -->
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="feather-zap me-2"></i>{{ __('partner.quick_actions') }}</h5>
                </div>
                <div class="card-body">
                    <a href="{{ route('partner.programs.show', $funding->id) }}" class="btn btn-outline-primary w-100 mb-2">
                        <i class="feather-arrow-left me-1"></i> {{ __('partner.back_to_program') }}
                    </a>

                    <a href="{{ route('partner.programs.index') }}" class="btn btn-outline-secondary w-100">
                        <i class="feather-list me-1"></i> {{ __('partner.all_programs') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
