@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        <!-- PAGE HEADER -->
        <div class="page-header d-flex justify-content-between align-items-center">
            <h4 class="fw-bold text-dark">Program Report — {{ $program->name }}</h4>

            <a href="{{ route('budget.reports.index') }}" class="btn btn-outline-secondary">
                Back to Reports
            </a>
        </div>

        <!-- PROGRAM DETAILS -->
        <div class="card mt-3 shadow-sm">
            <div class="card-body">
                <h5 class="fw-bold">Program Information</h5>

                <table class="table table-borderless">
                    <tr>
                        <td><strong>Name:</strong></td>
                        <td>{{ $program->name }}</td>
                    </tr>
                    <tr>
                        <td><strong>Duration:</strong></td>
                        <td>{{ $program->start_year }} — {{ $program->end_year }}</td>
                    </tr>
                    <tr>
                        <td><strong>Currency:</strong></td>
                        <td>{{ $program->currency }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- KPI CARDS -->
        <div class="row mt-4">

            <div class="col-md-4">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h6 class="text-muted">Total Projects</h6>
                        <h3 class="fw-bold">{{ $program->projects->count() }}</h3>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h6 class="text-muted">Total Activities</h6>
                        <h3 class="fw-bold">
                            {{ $program->projects->sum(fn($p) => $p->activities->count()) }}
                        </h3>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h6 class="text-muted">Total Program Budget</h6>
                        <h3 class="fw-bold text-primary">
                            {{ number_format($program->projects->sum(fn($p) => $p->totalAllocation()), 2) }}
                        </h3>
                    </div>
                </div>
            </div>

        </div>

        <!-- PROJECT TABLE -->
        <div class="card mt-4 shadow-sm">
            <div class="card-body">
                <h5 class="fw-bold">Projects Under This Program</h5>

                <table class="table table-bordered align-middle mt-3">
                    <thead class="table-light">
                        <tr>
                            <th>Project</th>
                            <th>Total Allocation</th>
                            <th>Activities</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($program->projects as $project)
                            <tr>
                                <td>{{ $project->name }}</td>

                                <td>{{ number_format($project->totalAllocation(), 2) }}</td>

                                <td>{{ $project->activities->count() }}</td>

                                <td>
                                    <a href="{{ route('budget.reports.project', $project->id) }}"
                                        class="btn btn-sm btn-secondary">
                                        View Project
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                </table>
            </div>
        </div>

        <!-- PROJECT TREND LINE CHART -->
        <div class="card mt-4 shadow-sm">
            <div class="card-body">
                <h5 class="fw-bold">Project Budget Trends (Multi-Year Line Chart)</h5>

                <canvas id="projectTrendChart" height="160"></canvas>
            </div>
        </div>

    </div>
@endsection


@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        // YEARS
        const years = @json(range($program->start_year, $program->end_year));

        // DATASETS FOR EACH PROJECT
        let datasets = [];

        @foreach ($program->projects as $proj)
            datasets.push({
                label: "{{ $proj->name }}",
                data: [
                    @foreach (range($program->start_year, $program->end_year) as $yr)
                        {{ $proj->allocations->where('year', $yr)->sum('amount') }},
                    @endforeach
                ],
                borderColor: "{{ sprintf('#%06X', mt_rand(0, 0xffffff)) }}",
                borderWidth: 2,
                fill: false,
                tension: 0.35
            });
        @endforeach

        // LINE GRAPH
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
@endsection
