@extends('layouts.app')

@section('title', 'Budget Dashboard')

@section('content')

    <style>
        .kpi-card {
            border-radius: 12px;
            transition: .3s;
        }

        .kpi-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.1);
        }

        .progress {
            height: 10px;
            border-radius: 5px;
        }

        .table-hover tbody tr:hover {
            background: #f6f8ff;
        }
    </style>

    <main class="nxl-container fade-in">
        <div class="nxl-content">

            <!-- HEADER -->
            <div class="page-header mb-4">
                <h4 class="fw-bold">📊 Budget Allocation Dashboard</h4>
                <p class="text-muted">High-level summary of programs, projects, activities & sub-activities.</p>
            </div>

            <!-- KPI CARDS -->
            <div class="row g-3 mb-4">

                <div class="col-md-3">
                    <div class="card kpi-card shadow-sm p-3">
                        <h6 class="text-muted">Total Programs</h6>
                        <h3 class="fw-bold">{{ $totalPrograms }}</h3>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card kpi-card shadow-sm p-3">
                        <h6 class="text-muted">Total Projects</h6>
                        <h3 class="fw-bold">{{ $totalProjects }}</h3>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card kpi-card shadow-sm p-3">
                        <h6 class="text-muted">Total Activities</h6>
                        <h3 class="fw-bold">{{ $totalActivities }}</h3>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card kpi-card shadow-sm p-3">
                        <h6 class="text-muted">Total Sub-Activities</h6>
                        <h3 class="fw-bold">{{ $totalSubActivities }}</h3>
                    </div>
                </div>

            </div>


            <!-- BUDGET SUMMARY -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">

                    <h5 class="fw-bold mb-3">💰 Budget & Allocation Summary</h5>

                    <div class="row">
                        <div class="col-md-4">
                            <p class="m-0 text-muted">Total Budget:</p>
                            <h4 class="fw-bold">{{ number_format($totalBudget, 2) }}</h4>
                        </div>

                        <div class="col-md-4">
                            <p class="m-0 text-muted">Total Projects:</p>
                            <h4 class="fw-bold text-primary">{{ number_format($totalProjects) }}</h4>
                        </div>
                    </div>

                </div>
            </div>


            <!-- PROGRAM SUMMARY TABLE -->
            <div class="card shadow-sm border-0 mb-5">
                <div class="card-body">

                    <h5 class="fw-bold mb-3">📘 Program Allocation Breakdown</h5>

                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Program</th>
                                <th>Total Projects</th>
                                <th>Total Activities</th>
                                <th>Allocated</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($programs as $index => $program)
                                @php
                                    $allocated = 0;
                                    foreach ($program->projects as $project) {
                                        foreach ($project->activities as $a) {
                                            $allocated += $a->allocations->sum('amount');
                                        }
                                    }
                                @endphp

                                <tr>
                                    <td class="fw-bold">{{ $index + 1 }}</td>
                                    <td>{{ $program->name }}</td>
                                    <td>{{ $program->projects->count() }}</td>
                                    <td>{{ $program->projects->sum(fn($p) => $p->activities->count()) }}</td>
                                    <td class="fw-bold">{{ number_format($allocated, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                </div>
            </div>


            <!-- CHARTS SECTION -->
            <div class="card shadow-sm border-0 mb-5">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">📈 Allocation Chart</h5>
                    <div id="allocationChart" style="height: 350px;"></div>
                </div>
            </div>

        </div>
    </main>



    <!-- APEXCHARTS -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <script>
        document.addEventListener("DOMContentLoaded", () => {

            let programNames = [
                @foreach ($programs as $program)
                    "{{ $program->name }}",
                @endforeach
            ];

            let programAllocations = [
                @foreach ($programs as $program)
                    {{ $program->projects->sum(fn($p) => $p->activities->sum(fn($a) => $a->allocations->sum('amount'))) }},
                @endforeach
            ];

            var options = {
                chart: {
                    type: 'bar',
                    height: 350
                },
                series: [{
                    name: 'Allocated',
                    data: programAllocations
                }],
                xaxis: {
                    categories: programNames
                },
                colors: ['#0d6efd']
            };

            var chart = new ApexCharts(document.querySelector("#allocationChart"), options);
            chart.render();
        });
    </script>

@endsection
