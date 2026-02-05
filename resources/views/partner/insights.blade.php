@extends('layouts.partner')

@section('content')
<div class="nxl-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1">{{ __('partner.program_insights') }}</h4>
                <p class="text-muted mb-0">{{ __('partner.insights_description') }}</p>
            </div>
            <a href="{{ route('partner.dashboard') }}" class="btn btn-light">
                <i class="feather-arrow-left me-1"></i> {{ __('partner.back_to_dashboard') }}
            </a>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card shadow-sm mt-3">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="feather-filter me-2"></i>{{ __('partner.filters') }}</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('partner.insights') }}" id="filterForm">
                @if(request('funding'))
                    <input type="hidden" name="funding" value="{{ request('funding') }}">
                @endif
                <div class="row g-3">
                    <!-- Search -->
                    <div class="col-md-4">
                        <label for="search" class="form-label">{{ __('partner.search_programs') }}</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="feather-search"></i></span>
                            <input
                                type="text"
                                class="form-control"
                                id="search"
                                name="search"
                                value="{{ request('search') }}"
                                placeholder="{{ __('partner.search_placeholder') }}"
                            >
                        </div>
                    </div>

                    <!-- Year Filter -->
                    <div class="col-md-3">
                        <label for="year" class="form-label">{{ __('partner.filter_by_year') }}</label>
                        <select class="form-select" id="year" name="year">
                            <option value="">{{ __('partner.all_years') }}</option>
                            @foreach($years as $year)
                                <option value="{{ $year }}" {{ request('year') == $year ? 'selected' : '' }}>
                                    {{ $year }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Sector Filter -->
                    <div class="col-md-3">
                        <label for="sector" class="form-label">{{ __('partner.filter_by_sector') }}</label>
                        <select class="form-select" id="sector" name="sector">
                            <option value="">{{ __('partner.all_sectors') }}</option>
                            @foreach($sectors as $sector)
                                <option value="{{ $sector->id }}" {{ request('sector') == $sector->id ? 'selected' : '' }}>
                                    {{ $sector->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Governance Node Filter -->
                    <div class="col-md-3">
                        <label for="governance_node" class="form-label">{{ __('partner.filter_by_governance') }}</label>
                        <select class="form-select" id="governance_node" name="governance_node">
                            <option value="">{{ __('partner.all_nodes') }}</option>
                            @foreach($governanceNodes as $node)
                                <option value="{{ $node->id }}" {{ request('governance_node') == $node->id ? 'selected' : '' }}>
                                    {{ $node->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Action Buttons -->
                    <div class="col-md-12 mt-2">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="feather-check me-1"></i> {{ __('partner.apply_filters') }}
                        </button>
                        <a href="{{ request('funding') ? route('partner.insights', ['funding' => request('funding')]) : route('partner.insights') }}" class="btn btn-outline-secondary">
                            <i class="feather-x me-1"></i> {{ __('partner.clear_filters') }}
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if($selectedFunding)
        <div class="alert alert-success mt-3">
            <i class="feather-filter me-2"></i>
            {{ __('partner.filtered_for_program', ['program' => $selectedFunding->program_name ?? ($selectedFunding->program?->name ?? '—')]) }}
            <a href="{{ route('partner.programs.show', $selectedFunding->id) }}" class="ms-2">
                {{ __('partner.view_details') }}
            </a>
        </div>
    @endif

    <!-- Results Summary -->
    @if($fundings->count() > 0)
        <div class="alert alert-info mt-3">
            <i class="feather-info me-2"></i>
            {{ __('partner.showing_results', ['count' => $fundings->count()]) }}
        </div>
    @endif

    <!-- Programs Results -->
    <div class="row mt-3">
        @forelse($fundings as $funding)
            <div class="col-12 mb-3">
                <div class="card shadow-sm">
                    <div class="card-header" style="background: linear-gradient(135deg, #007144 0%, #00a86b 100%);">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                @if($funding->program && $funding->program->sector)
                                    <div class="mb-1">
                                        <span class="badge bg-light text-dark">
                                            <i class="feather-briefcase me-1"></i>
                                            {{ $funding->program->sector->name }}
                                        </span>
                                    </div>
                                @endif
                                <h5 class="mb-1 text-white">
                                    <i class="feather-package me-2"></i>
                                    {{ $funding->program_name ?? $funding->program->name }}
                                </h5>
                                <p class="mb-0 text-white-50 small">
                                    <i class="feather-map-pin me-1"></i>
                                    {{ $funding->governanceNode->name ?? '—' }}
                                    @if($funding->governanceNode)
                                        <span class="mx-2">|</span>
                                        <i class="feather-calendar me-1"></i>
                                        {{ $funding->start_year }} - {{ $funding->end_year }}
                                    @endif
                                    <span class="mx-2">|</span>
                                    <i class="feather-dollar-sign me-1"></i>
                                    {{ $funding->currency ?? $funder->currency }} {{ number_format($funding->approved_amount ?? 0, 2) }}
                                </p>
                            </div>
                            <a href="{{ route('partner.programs.show', $funding->id) }}" class="btn btn-light btn-sm">
                                <i class="feather-eye me-1"></i> {{ __('partner.view_details') }}
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        @if(!$funding->program_id)
                            <div class="alert alert-info mb-0">
                                <i class="feather-info-circle me-2"></i>
                                This funding is not linked to a specific program structure yet. Projects and activities are not available for drill-down.
                            </div>
                        @elseif(isset($funding->projects) && $funding->projects->count() > 0)
                            <!-- Projects Accordion -->
                            <div class="accordion" id="programAccordion{{ $funding->id }}">
                                @foreach($funding->projects as $projectIndex => $project)
                                    <div class="accordion-item border mb-2">
                                        <h2 class="accordion-header">
                                            <button
                                                class="accordion-button collapsed"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#project{{ $project->id }}"
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
                                            id="project{{ $project->id }}"
                                            class="accordion-collapse collapse"
                                            data-bs-parent="#programAccordion{{ $funding->id }}"
                                        >
                                            <div class="accordion-body bg-light">
                                                <div class="d-flex justify-content-end mb-2">
                                                    <a href="{{ route('partner.projects.show', $project->id) }}" class="btn btn-sm btn-primary">
                                                        <i class="feather-eye me-1"></i> {{ __('partner.view_details') }}
                                                    </a>
                                                </div>

                                                @if($project->activities && $project->activities->count() > 0)
                                                    <!-- Activities Table -->
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-hover bg-white mb-0">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th>{{ __('partner.activity_name') }}</th>
                                                                    <th>{{ __('partner.governance_node') }}</th>
                                                                    <th class="text-end">{{ __('partner.budget') }}</th>
                                                                    <th class="text-center">{{ __('partner.sub_activities') }}</th>
                                                                    <th class="text-center">{{ __('partner.action') }}</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach($project->activities as $activity)
                                                                    <tr>
                                                                        <td>
                                                                            <i class="feather-activity me-2 text-info"></i>
                                                                            <strong>{{ $activity->name }}</strong>
                                                                        </td>
                                                                        <td>{{ $activity->governanceNode->name ?? '—' }}</td>
                                                                        <td class="text-end">
                                                                            {{ $activity->currency ?? $project->currency ?? $funding->currency }}
                                                                            {{ number_format($activity->budget ?? 0, 2) }}
                                                                        </td>
                                                                        <td class="text-center">
                                                                            <span class="badge bg-info">
                                                                                {{ $activity->subActivities->count() }}
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
                        @else
                            <p class="text-muted text-center mb-0">
                                <i class="feather-info-circle me-2"></i>
                                No projects found for this program
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-warning">
                    <i class="feather-alert-circle me-2"></i>
                    {{ __('partner.no_programs') }}
                </div>
            </div>
        @endforelse
    </div>
</div>

@push('scripts')
<script>
    // Re-initialize Feather icons after accordion actions
    $(document).on('shown.bs.collapse hidden.bs.collapse', function () {
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    });
</script>
@endpush
@endsection
