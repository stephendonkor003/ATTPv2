@extends('layouts.partner')

@section('content')
<div class="nxl-container">
    <!-- Breadcrumb Navigation -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('partner.dashboard') }}">{{ __('partner.dashboard') }}</a></li>
            <li class="breadcrumb-item"><a href="{{ route('partner.programs.index') }}">{{ __('partner.funded_programs') }}</a></li>
            <li class="breadcrumb-item"><a href="{{ route('partner.programs.show', $funding->id) }}">{{ $funding->program_name ?? ($funding->program?->name ?? __('partner.program')) }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ __('partner.program_report') }}</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1">{{ __('partner.program_report') }} — {{ $funding->program_name ?? ($program?->name ?? __('partner.program')) }}</h4>
                <p class="text-muted mb-0">{{ __('partner.program_report_description') }}</p>
            </div>
            <a href="{{ route('partner.programs.show', $funding->id) }}" class="btn btn-light">
                <i class="feather-arrow-left me-1"></i> {{ __('partner.back_to_program') }}
            </a>
        </div>
    </div>

    @if(!$programLinked || !$program)
        <div class="alert alert-info mt-3">
            <i class="feather-info-circle me-2"></i>
            {{ __('partner.program_not_linked') }}
        </div>
    @else
        @php
            $totalProjects = $program->projects->count();
            $totalActivities = $program->projects->sum(fn($project) => $project->activities->count());
            $totalSubActivities = $program->projects->sum(fn($project) => $project->activities->sum(fn($activity) => $activity->subActivities->count()));
            $totalAllocation = $program->projects->sum(fn($project) => $project->allocations->sum('amount'));
            $chartYears = ($program->start_year && $program->end_year) ? range($program->start_year, $program->end_year) : [];
        @endphp

        <!-- Program Summary -->
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="feather-info me-2"></i>{{ __('partner.program_overview') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="text-muted small">{{ __('partner.program_name') }}</label>
                        <p class="fw-semibold mb-0">{{ $program->name }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">{{ __('partner.funding_period') }}</label>
                        <p class="fw-semibold mb-0">{{ $program->start_year }} - {{ $program->end_year }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">{{ __('partner.approved_amount') }}</label>
                        <p class="fw-semibold mb-0 text-success">{{ $funding->currency ?? $funder->currency }} {{ number_format($funding->approved_amount ?? 0, 2) }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">{{ __('partner.status') }}</label>
                        <p class="fw-semibold mb-0">
                            <span class="badge {{ $funding->status === 'approved' ? 'bg-success' : 'bg-warning text-dark' }}">
                                {{ ucfirst($funding->status ?? 'pending') }}
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row mt-4 g-3">
            <div class="col-md-3">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h6 class="text-muted">{{ __('partner.projects') }}</h6>
                        <h3 class="fw-bold">{{ $totalProjects }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h6 class="text-muted">{{ __('partner.activities') }}</h6>
                        <h3 class="fw-bold">{{ $totalActivities }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h6 class="text-muted">{{ __('partner.sub_activities') }}</h6>
                        <h3 class="fw-bold">{{ $totalSubActivities }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h6 class="text-muted">{{ __('partner.total_budget') }}</h6>
                        <h3 class="fw-bold text-primary">{{ $funding->currency ?? $funder->currency }} {{ number_format($totalAllocation, 2) }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Projects Table -->
        <div class="card shadow-sm mt-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="feather-layers me-2"></i>{{ __('partner.projects') }}</h5>
            </div>
            <div class="card-body">
                @if($program->projects->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mt-3">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('partner.project_name') }}</th>
                                    <th class="text-end">{{ __('partner.total_budget') }}</th>
                                    <th class="text-center">{{ __('partner.activities') }}</th>
                                    <th class="text-center">{{ __('partner.sub_activities') }}</th>
                                    <th class="text-center">{{ __('partner.action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($program->projects as $project)
                                    <tr>
                                        <td>{{ $project->name }}</td>
                                        <td class="text-end">{{ $funding->currency ?? $funder->currency }} {{ number_format($project->allocations->sum('amount'), 2) }}</td>
                                        <td class="text-center">{{ $project->activities->count() }}</td>
                                        <td class="text-center">
                                            {{ $project->activities->sum(fn($activity) => $activity->subActivities->count()) }}
                                        </td>
                                        <td class="text-center">
                                            <a href="{{ route('partner.projects.show', $project->id) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="feather-eye me-1"></i> {{ __('partner.view_details') }}
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted text-center mb-0">{{ __('partner.no_projects_found') }}</p>
                @endif
            </div>
        </div>

        <!-- Project Trend Chart -->
        <div class="card shadow-sm mt-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="feather-trending-up me-2"></i>{{ __('partner.project_budget_trends') }}</h5>
            </div>
            <div class="card-body">
                @if(count($chartYears) > 0)
                    <canvas id="projectTrendChart" height="160"></canvas>
                @else
                    <p class="text-muted mb-0">{{ __('partner.no_chart_data') }}</p>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
@if($programLinked && $program && count($chartYears) > 0)
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const years = @json($chartYears);
        const datasets = [];

        @foreach ($program->projects as $proj)
            datasets.push({
                label: "{{ $proj->name }}",
                data: [
                    @foreach ($chartYears as $yr)
                        {{ $proj->allocations->where('year', $yr)->sum('amount') }},
                    @endforeach
                ],
                borderColor: "{{ sprintf('#%06X', mt_rand(0, 0xffffff)) }}",
                borderWidth: 2,
                fill: false,
                tension: 0.35
            });
        @endforeach

        new Chart(document.getElementById('projectTrendChart'), {
            type: "line",
            data: {
                labels: years,
                datasets: datasets
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
@endif
@endpush
