@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        <!-- ================= PAGE HEADER ================= -->
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold text-dark mb-1">Budget Overview — All Sectors</h4>
                <p class="text-muted mb-0">High-level financial summary across sectors and programs</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('budget.reports.commitments') }}" class="btn btn-primary">
                    <i class="feather-file-text me-1"></i> Commitment Report
                </a>
                <a href="{{ route('budget.reports.ifr') }}" class="btn btn-outline-primary">
                    <i class="feather-activity me-1"></i> IFR Report
                </a>
            </div>
        </div>

        <!-- ================= KPI CARDS ================= -->
        <div class="row mt-4 g-3">

            <!-- Total Sectors -->
            <div class="col-md-4">
                <div class="card shadow-sm text-center h-100">
                    <div class="card-body">
                        <h6 class="text-muted">Total Sectors</h6>
                        <h3 class="fw-bold">{{ $sectors->count() }}</h3>
                    </div>
                </div>
            </div>

            <!-- Total Programs -->
            <div class="col-md-4">
                <div class="card shadow-sm text-center h-100">
                    <div class="card-body">
                        <h6 class="text-muted">Total Programs</h6>
                        <h3 class="fw-bold">
                            {{ $sectors->sum(fn($s) => $s->programs->count()) }}
                        </h3>
                    </div>
                </div>
            </div>

            <!-- Total Budget -->
            <div class="col-md-4">
                <div class="card shadow-sm text-center h-100">
                    <div class="card-body">
                        <h6 class="text-muted">Total Budget (All Sectors)</h6>
                        <h3 class="fw-bold text-primary">
                            {{ number_format(
                                $sectors->sum(fn($s) => $s->programs->sum(fn($p) => $p->projects->sum(fn($pr) => $pr->totalAllocation()))),
                                2,
                            ) }}
                        </h3>
                    </div>
                </div>
            </div>

        </div>

        <!-- ================= SECTOR TABLE ================= -->
        <div class="card mt-4 shadow-sm">
            <div class="card-body">

                <h5 class="fw-bold mb-3">Sector Breakdown</h5>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Sector</th>
                                <th class="text-center">Programs</th>
                                <th class="text-end">Total Budget</th>
                                {{-- <th class="text-center">Action</th> --}}
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($sectors as $sector)
                                @php
                                    $sectorTotal = $sector->programs->sum(
                                        fn($p) => $p->projects->sum(fn($pr) => $pr->totalAllocation()),
                                    );
                                @endphp

                                <tr>
                                    <td class="fw-semibold">{{ $sector->name }}</td>

                                    <td class="text-center">
                                        <span class="badge bg-info">
                                            {{ $sector->programs->count() }}
                                        </span>
                                    </td>

                                    <td class="text-end">
                                        {{ number_format($sectorTotal, 2) }}
                                    </td>

                                    {{-- <td class="text-center">
                                        @if ($sector->programs->isNotEmpty())
                                            <a href="{{ route('budget.reports.program', $sector->programs->first()->id) }}"
                                                class="btn btn-sm btn-primary" title="View programs under this sector">
                                                <i class="feather-eye me-1"></i> View Programs
                                            </a>
                                        @else
                                            <span class="text-muted small">No programs</span>
                                        @endif
                                    </td> --}}
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        No sectors found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                    </table>
                </div>

            </div>
        </div>

        <!-- ================= CHARTS ================= -->
        <div class="card mt-4 shadow-sm">
            <div class="card-body">

                <h5 class="fw-bold mb-3">Sector-Level Analysis</h5>

                <div class="row g-4">

                    <!-- BAR CHART -->
                    <div class="col-md-6">
                        <div class="border rounded p-3">
                            <canvas id="sectorBarChart" height="180"></canvas>
                        </div>
                    </div>

                    <!-- PIE CHART -->
                    <div class="col-md-6">
                        <div class="border rounded p-3">
                            <canvas id="sectorPieChart" height="180"></canvas>
                        </div>
                    </div>

                </div>

            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        const sectorNames = @json($sectors->pluck('name'));

        const sectorTotals = @json($sectors->map(fn($s) => $s->programs->sum(fn($p) => $p->projects->sum(fn($pr) => $pr->totalAllocation()))));

        /* ================= BAR CHART ================= */
        new Chart(document.getElementById('sectorBarChart'), {
            type: 'bar',
            data: {
                labels: sectorNames,
                datasets: [{
                    label: 'Sector Total Budget',
                    data: sectorTotals,
                    backgroundColor: '#0d6efd'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        /* ================= PIE CHART ================= */
        new Chart(document.getElementById('sectorPieChart'), {
            type: 'pie',
            data: {
                labels: sectorNames,
                datasets: [{
                    data: sectorTotals,
                    backgroundColor: [
                        '#0d6efd', '#198754', '#dc3545',
                        '#ffc107', '#0dcaf0', '#6f42c1'
                    ]
                }]
            },
            options: {
                responsive: true
            }
        });
    </script>
@endsection
