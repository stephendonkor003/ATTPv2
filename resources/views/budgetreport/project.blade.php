@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        <!-- PAGE HEADER -->
        <div class="page-header d-flex justify-content-between align-items-center">
            <h4 class="fw-bold text-dark">Project Report — {{ $project->name }}</h4>

            <a href="{{ route('budget.reports.program', $project->program) }}" class="btn btn-outline-secondary">
                Back to Program
            </a>
        </div>

        <!-- PROJECT INFORMATION -->
        <div class="card mt-3 shadow-sm">
            <div class="card-body">
                <h5 class="fw-bold">Project Information</h5>

                <table class="table table-borderless">
                    <tr>
                        <td><strong>Project Name:</strong></td>
                        <td>{{ $project->name }}</td>
                    </tr>
                    <tr>
                        <td><strong>Program:</strong></td>
                        <td>{{ $project->program->name }}</td>
                    </tr>
                    <tr>
                        <td><strong>Duration:</strong></td>
                        <td>{{ $project->program->start_year }} – {{ $project->program->end_year }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- KPI CARDS -->
        <div class="row mt-4">

            <div class="col-md-4">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h6 class="text-muted">Total Activities</h6>
                        <h3 class="fw-bold">{{ $project->activities->count() }}</h3>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h6 class="text-muted">Total Sub-Activities</h6>
                        <h3 class="fw-bold">
                            {{ $project->activities->sum(fn($a) => $a->subActivities->count()) }}
                        </h3>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h6 class="text-muted">Total Project Budget</h6>
                        <h3 class="fw-bold text-primary">
                            {{ number_format($project->totalAllocation(), 2) }}
                        </h3>
                    </div>
                </div>
            </div>

        </div>

        <!-- ACTIVITIES TABLE -->
        <div class="card mt-4 shadow-sm">
            <div class="card-body">

                <h5 class="fw-bold">Activities Under This Project</h5>

                <table class="table table-bordered align-middle mt-3">
                    <thead class="table-light">
                        <tr>
                            <th>Activity</th>
                            <th>Total Allocation</th>
                            <th>Sub-Activities</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($project->activities as $activity)
                            <tr>
                                <td>{{ $activity->name }}</td>

                                <td>{{ number_format($activity->totalAllocation(), 2) }}</td>

                                <td>{{ $activity->subActivities->count() }}</td>

                                <td>
                                    <a href="{{ route('budget.reports.activity', $activity->id) }}"
                                        class="btn btn-sm btn-secondary">
                                        View Activity
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                </table>

            </div>
        </div>

        <!-- ACTIVITY TREND CHART -->
        <div class="card mt-4 shadow-sm">
            <div class="card-body">

                <h5 class="fw-bold">Activity Budget Trends (Line Chart)</h5>

                <canvas id="activityTrendChart" height="160"></canvas>

            </div>
        </div>

    </div>
@endsection



@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        const years = @json(range($project->program->start_year, $project->program->end_year));

        let datasets = [];

        @foreach ($project->activities as $act)
            datasets.push({
                label: "{{ $act->name }}",
                data: [
                    @foreach (range($project->program->start_year, $project->program->end_year) as $yr)
                        {{ $act->allocations->where('year', $yr)->sum('amount') }},
                    @endforeach
                ],
                borderColor: "{{ sprintf('#%06X', mt_rand(0, 0xffffff)) }}",
                borderWidth: 2,
                fill: false,
                tension: 0.35
            });
        @endforeach

        new Chart(document.getElementById('activityTrendChart'), {
            type: "line",
            data: {
                labels: years,
                datasets: datasets
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: "bottom"
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
@endsection
