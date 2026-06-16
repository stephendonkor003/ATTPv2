@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        <!-- PAGE HEADER -->
        <div class="page-header d-flex justify-content-between align-items-center">
            <h4 class="fw-bold text-dark">Activity Report — {{ $activity->name }}</h4>

            <a href="{{ route('budget.reports.project', $activity->project) }}" class="btn btn-outline-secondary">
                Back to Project
            </a>
        </div>

        <!-- ACTIVITY INFORMATION -->
        <div class="card mt-3 shadow-sm">
            <div class="card-body">

                <h5 class="fw-bold">Activity Information</h5>

                <table class="table table-borderless">
                    <tr>
                        <td><strong>Name:</strong></td>
                        <td>{{ $activity->name }}</td>
                    </tr>

                    <tr>
                        <td><strong>Project:</strong></td>
                        <td>{{ $activity->project->name }}</td>
                    </tr>

                    <tr>
                        <td><strong>Program:</strong></td>
                        <td>{{ $activity->project->program->name }}</td>
                    </tr>

                    <tr>
                        <td><strong>Total Allocation:</strong></td>
                        <td>{{ number_format($activity->totalAllocation(), 2) }}</td>
                    </tr>
                </table>

            </div>
        </div>

        <!-- KPI CARDS -->
        <div class="row mt-4">

            <div class="col-md-6">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h6 class="text-muted">Total Sub-Activities</h6>
                        <h3 class="fw-bold">{{ $activity->subActivities->count() }}</h3>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h6 class="text-muted">Total Activity Budget</h6>
                        <h3 class="fw-bold text-primary">{{ number_format($activity->totalAllocation(), 2) }}</h3>
                    </div>
                </div>
            </div>

        </div>

        <!-- SUB-ACTIVITY TABLE -->
        <div class="card mt-4 shadow-sm">
            <div class="card-body">

                <h5 class="fw-bold">Sub-Activities</h5>

                <table class="table table-bordered align-middle mt-3">
                    <thead class="table-light">
                        <tr>
                            <th>Sub-Activity</th>
                            <th>Total Allocation</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($activity->subActivities as $sub)
                            <tr>
                                <td>{{ $sub->name }}</td>
                                <td>{{ number_format($sub->totalAllocation(), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>

                </table>

            </div>
        </div>

        <!-- CHARTS SECTION -->
        <div class="card mt-4 shadow-sm">
            <div class="card-body">

                <h5 class="fw-bold">Visual Analytics</h5>

                <div class="row mt-4">

                    <div class="col-md-6">
                        <canvas id="subActivityBarChart" height="170"></canvas>
                    </div>

                    <div class="col-md-6">
                        <canvas id="subActivityPieChart" height="170"></canvas>
                    </div>

                </div>

            </div>
        </div>

    </div>
@endsection



@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        const subNames = @json($activity->subActivities->pluck('name'));
        const subTotals = @json($activity->subActivities->map(fn($s) => $s->totalAllocation()));

        // BAR CHART
        new Chart(document.getElementById('subActivityBarChart'), {
            type: 'bar',
            data: {
                labels: subNames,
                datasets: [{
                    label: "Sub-Activity Allocation",
                    data: subTotals,
                    backgroundColor: '#0d6efd',
                    borderColor: '#0a58ca',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // PIE CHART
        new Chart(document.getElementById('subActivityPieChart'), {
            type: 'pie',
            data: {
                labels: subNames,
                datasets: [{
                    data: subTotals,
                    backgroundColor: [
                        '#0d6efd', '#198754', '#dc3545',
                        '#ffc107', '#0dcaf0', '#6f42c1'
                    ]
                }]
            }
        });
    </script>
@endsection
