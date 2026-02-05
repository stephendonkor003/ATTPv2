@extends('layouts.partner')

@section('content')
<div class="nxl-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1">{{ $funding->program_name ?? ($funding->program?->name ?? 'Program Details') }}</h4>
                <p class="text-muted mb-0">{{ __('partner.program_details_description') }}</p>
            </div>
            <a href="{{ route('partner.programs.index') }}" class="btn btn-light">
                <i class="feather-arrow-left me-1"></i> {{ __('partner.back') }}
            </a>
        </div>
    </div>

    @if(!$programLinked)
        <div class="alert alert-info mt-3">
            <i class="feather-info-circle me-2"></i>
            {{ __('partner.program_not_linked') }}
        </div>
    @endif

    <div class="row mt-3">
        <!-- Main Program Information -->
        <div class="col-lg-8">
            <!-- Program Overview Card -->
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="feather-info me-2"></i>{{ __('partner.program_overview') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small">{{ __('partner.program_name') }}</label>
                            <p class="fw-semibold mb-0">{{ $funding->program_name ?? ($funding->program?->name ?? '—') }}</p>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted small">{{ __('partner.governance_node') }}</label>
                            <p class="fw-semibold mb-0">
                                {{ $funding->governanceNode->name ?? '-' }}
                                @if($funding->governanceNode)
                                    <br><small class="text-muted">{{ $funding->governanceNode->level->name ?? '' }}</small>
                                @endif
                            </p>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted small">{{ __('partner.funding_period') }}</label>
                            <p class="fw-semibold mb-0">{{ $funding->start_year }} - {{ $funding->end_year }}</p>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted small">{{ __('partner.approved_amount') }}</label>
                            <p class="fw-semibold mb-0 text-success">
                                {{ $funding->currency ?? $funder->currency }} {{ number_format($funding->approved_amount ?? 0, 2) }}
                            </p>
                        </div>

                        @if($funding->program && $funding->program->description)
                            <div class="col-md-12">
                                <label class="text-muted small">{{ __('partner.description') }}</label>
                                <p class="mb-0">{{ $funding->program->description }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Projects Under This Program -->
            @if(isset($projects) && $projects->count() > 0)
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="feather-layers me-2"></i>{{ __('partner.projects') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('partner.project_name') }}</th>
                                        <th>{{ __('partner.governance_node') }}</th>
                                        <th class="text-end">{{ __('partner.budget') }}</th>
                                        <th class="text-center">{{ __('partner.activities') }}</th>
                                        <th>{{ __('partner.status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($projects as $project)
                                        <tr>
                                            <td>
                                                <a href="{{ route('partner.projects.show', $project->id) }}" class="text-decoration-none">
                                                    <strong class="text-primary">{{ $project->name }}</strong>
                                                </a>
                                                @if($project->description)
                                                    <br><small class="text-muted">{{ Str::limit($project->description, 80) }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                {{ $project->governanceNode->name ?? '-' }}
                                                @if($project->governanceNode)
                                                    <br><small class="text-muted">{{ $project->governanceNode->level->name ?? '' }}</small>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <strong>{{ $project->currency ?? $funding->currency ?? $funder->currency }} {{ number_format($project->total_budget ?? 0, 2) }}</strong>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-info">{{ $project->activities->count() }}</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">{{ ucfirst($project->status ?? 'active') }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @elseif($programLinked)
                <div class="alert alert-info">
                    <i class="feather-info-circle me-2"></i>
                    {{ __('partner.no_projects_found') }}
                </div>
            @endif

            <!-- Program Structure: Projects, Activities, Sub-Activities -->
            @if(isset($projects) && $projects->count() > 0)
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="feather-list me-2"></i>{{ __('partner.program_structure') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="accordion" id="programStructure{{ $funding->id }}">
                            @foreach($projects as $project)
                                <div class="accordion-item border mb-2">
                                    <h2 class="accordion-header">
                                        <button
                                            class="accordion-button collapsed"
                                            type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#structureProject{{ $project->id }}"
                                        >
                                            <div class="w-100">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <i class="feather-box me-2 text-primary"></i>
                                                        <strong>{{ $project->name }}</strong>
                                                    </div>
                                                    <div class="me-3">
                                                        <span class="badge bg-secondary me-2">
                                                            {{ $project->activities->count() }} {{ __('partner.activities') }}
                                                        </span>
                                                        <span class="badge bg-info me-2">
                                                            {{ $project->activities->sum(fn($activity) => $activity->subActivities->count()) }}
                                                            {{ __('partner.sub_activities') }}
                                                        </span>
                                                        <span class="text-success fw-semibold">
                                                            {{ $project->currency ?? $funding->currency }} {{ number_format($project->total_budget ?? 0, 2) }}
                                                        </span>
                                                    </div>
                                                </div>
                                                <small class="text-muted d-block mt-1 ms-4">
                                                    {{ $project->governanceNode->name ?? '—' }}
                                                </small>
                                            </div>
                                        </button>
                                    </h2>
                                    <div
                                        id="structureProject{{ $project->id }}"
                                        class="accordion-collapse collapse"
                                        data-bs-parent="#programStructure{{ $funding->id }}"
                                    >
                                        <div class="accordion-body bg-light">
                                            <div class="d-flex justify-content-end mb-2">
                                                <a href="{{ route('partner.projects.show', $project->id) }}" class="btn btn-sm btn-outline-primary">
                                                    <i class="feather-eye me-1"></i> {{ __('partner.view_details') }}
                                                </a>
                                            </div>

                                            @if($project->activities && $project->activities->count() > 0)
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-hover bg-white mb-0">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th>{{ __('partner.activity_name') }}</th>
                                                                <th class="text-end">{{ __('partner.budget') }}</th>
                                                                <th>{{ __('partner.sub_activities') }}</th>
                                                                <th>{{ __('partner.status') }}</th>
                                                                <th class="text-center">{{ __('partner.action') }}</th>
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
                                                                    <td class="text-end">
                                                                        <strong>{{ $activity->currency ?? $project->currency ?? $funding->currency }} {{ number_format($activity->budget ?? 0, 2) }}</strong>
                                                                    </td>
                                                                    <td>
                                                                        @if($activity->subActivities->count() > 0)
                                                                            <div class="d-flex flex-wrap gap-1">
                                                                                @foreach($activity->subActivities as $subActivity)
                                                                                    <span class="badge bg-light text-dark border">{{ $subActivity->name }}</span>
                                                                                @endforeach
                                                                            </div>
                                                                        @else
                                                                            <span class="text-muted">—</span>
                                                                        @endif
                                                                    </td>
                                                                    <td>
                                                                        <span class="badge bg-{{ $activity->status === 'completed' ? 'success' : ($activity->status === 'in_progress' ? 'warning text-dark' : 'secondary') }}">
                                                                            {{ ucfirst($activity->status ?? 'pending') }}
                                                                        </span>
                                                                    </td>
                                                                    <td class="text-center">
                                                                        <a href="{{ route('partner.activities.show', $activity->id) }}" class="btn btn-sm btn-outline-primary">
                                                                            <i class="feather-arrow-right"></i>
                                                                        </a>
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @else
                                                <p class="text-muted text-center mb-0">{{ __('partner.no_activities_found') }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <!-- Budget Commitments -->
            @if($funding->commitments && $funding->commitments->count() > 0)
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="feather-dollar-sign me-2"></i>{{ __('partner.budget_commitments') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('partner.resource') }}</th>
                                        <th>{{ __('partner.category') }}</th>
                                        <th class="text-end">{{ __('partner.amount') }}</th>
                                        <th>{{ __('partner.fiscal_year') }}</th>
                                        <th>{{ __('partner.status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($funding->commitments as $commitment)
                                        <tr>
                                            <td>
                                                @if($commitment->resource)
                                                    <strong>{{ $commitment->resource->name ?? '—' }}</strong>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($commitment->resourceCategory)
                                                    <span class="badge bg-secondary">{{ $commitment->resourceCategory->name ?? '—' }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <strong>{{ $funding->currency ?? $funder->currency }} {{ number_format($commitment->commitment_amount ?? 0, 2) }}</strong>
                                            </td>
                                            <td>{{ $commitment->commitment_year ?? '—' }}</td>
                                            <td>
                                                <span class="badge {{ $commitment->status === 'approved' ? 'bg-success' : 'bg-warning text-dark' }}">
                                                    {{ ucfirst($commitment->status ?? 'pending') }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Program Documents -->
            @if($funding->documents && $funding->documents->count() > 0)
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="feather-file-text me-2"></i>{{ __('partner.documents') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            @foreach($funding->documents as $document)
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="feather-file me-2 text-primary"></i>
                                        <strong>{{ $document->file_name }}</strong>
                                        @if($document->document_type)
                                            <br><small class="text-muted ms-4">
                                                <span class="badge bg-secondary">{{ ucfirst($document->document_type) }}</span>
                                            </small>
                                        @endif
                                        <br><small class="text-muted ms-4">
                                            <i class="feather-calendar me-1"></i>{{ $document->created_at->format('M d, Y') }}
                                        </small>
                                    </div>
                                    <a href="{{ route('partner.documents.download', $document->id) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="feather-download"></i>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Funding Summary Card -->
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="feather-briefcase me-2"></i>{{ __('partner.funding_summary') }}</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small">{{ __('partner.your_organization') }}</label>
                        <p class="fw-bold mb-0">{{ $funder->name }}</p>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small">{{ __('partner.funding_type') }}</label>
                        <p class="fw-semibold mb-0">
                            <span class="badge bg-info">{{ ucfirst($funding->funding_type ?? 'grant') }}</span>
                        </p>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small">{{ __('partner.status') }}</label>
                        <p class="fw-semibold mb-0">
                            <span class="badge {{ $funding->status === 'approved' ? 'bg-success' : 'bg-warning text-dark' }}">
                                {{ ucfirst($funding->status ?? 'pending') }}
                            </span>
                        </p>
                    </div>

                    <div class="mb-0">
                        <label class="text-muted small">{{ __('partner.approved_on') }}</label>
                        <p class="fw-semibold mb-0">{{ $funding->created_at->format('M d, Y') }}</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Card -->
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="feather-zap me-2"></i>{{ __('partner.quick_actions') }}</h5>
                </div>
                <div class="card-body">
                    @can('partner.programs.view')
                        <a href="{{ route('partner.programs.report', $funding->id) }}" class="btn btn-outline-primary w-100 mb-2">
                            <i class="feather-file-text me-1"></i> {{ __('partner.program_report') }}
                        </a>

                        <a href="{{ route('partner.insights', ['funding' => $funding->id]) }}" class="btn btn-outline-secondary w-100 mb-2">
                            <i class="feather-bar-chart-2 me-1"></i> {{ __('partner.program_insights') }}
                        </a>
                    @endcan

                    @can('partner.requests.create')
                        <a href="{{ route('partner.requests.create') }}?program={{ $funding->id }}" class="btn btn-primary w-100 mb-2">
                            <i class="feather-message-circle me-1"></i> {{ __('partner.request_information') }}
                        </a>
                    @endcan

                    <a href="{{ route('partner.programs.index') }}" class="btn btn-outline-primary w-100">
                        <i class="feather-list me-1"></i> {{ __('partner.all_programs') }}
                    </a>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="feather-help-circle me-2"></i>{{ __('partner.need_help') }}</h5>
                </div>
                <div class="card-body">
                    <p class="small mb-2">{{ __('partner.need_help_description') }}</p>
                    @can('partner.requests.create')
                        <a href="{{ route('partner.requests.create') }}" class="btn btn-sm btn-success w-100">
                            <i class="feather-plus-circle me-1"></i> {{ __('partner.create_request') }}
                        </a>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
