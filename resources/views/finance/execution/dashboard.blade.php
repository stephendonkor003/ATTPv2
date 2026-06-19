@extends('layouts.app')

@section('content')
    <style>
        .execution-pdf-btn {
            background: linear-gradient(135deg, #fbbf24, #f97316);
            border: 0;
            color: #111827;
            font-weight: 800;
            box-shadow: 0 12px 22px rgba(15, 23, 42, .16);
            transition: transform .16s ease, box-shadow .16s ease;
        }

        .execution-pdf-btn:hover,
        .execution-pdf-btn:focus {
            color: #111827;
            box-shadow: 0 16px 28px rgba(15, 23, 42, .24);
            transform: translateY(-2px);
        }
    </style>

    <div class="nxl-container">

        {{-- ================================
     * PAGE HEADER
     * ================================ --}}
        <div class="page-header mb-4 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <h4 class="fw-bold mb-1">Execution Dashboard</h4>
                <p class="text-muted mb-0">
                    Financial execution performance — planned vs actual, variance, momentum, and risk
                </p>
            </div>
            <a href="{{ route('finance.execution.dashboard.export.pdf', request()->query()) }}" class="btn execution-pdf-btn">
                <i class="feather-download me-1"></i> Download PDF
            </a>
        </div>

        {{-- ================================
     * FILTERS
     * ================================ --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('finance.execution.dashboard') }}" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Sector</label>
                        <select name="sector_id" class="form-select" onchange="this.form.submit()">
                            <option value="">All Sectors</option>
                            @foreach ($sectors as $sector)
                                <option value="{{ $sector->id }}" @selected(request('sector_id') == $sector->id)>
                                    {{ $sector->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Program</label>
                        <select name="program_id" class="form-select" onchange="this.form.submit()">
                            <option value="">All Programs</option>
                            @foreach ($programs as $program)
                                <option value="{{ $program->id }}" @selected(request('program_id') == $program->id)>
                                    {{ $program->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Project</label>
                        <select name="project_id" class="form-select" onchange="this.form.submit()">
                            <option value="">All Projects</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}" @selected(request('project_id') == $project->id)>
                                    {{ $project->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>
        </div>

        {{-- ================================
     * KPI SUMMARY
     * ================================ --}}
        <div class="row g-4 mb-4">
            <div class="col-md-4 col-xl-2">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <p class="text-muted mb-1">Total Allocation</p>
                        <h4 class="fw-bold">{{ number_format($totalAllocation, 2) }}</h4>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-xl-2">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <p class="text-muted mb-1">Total Commitment</p>
                        <h4 class="fw-bold">{{ number_format($totalCommitment, 2) }}</h4>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-xl-2">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <p class="text-muted mb-1">Execution Rate</p>
                        <h4 class="fw-bold">{{ $executionRate }}%</h4>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-xl-2">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <p class="text-muted mb-1">Total Disbursements</p>
                        <h4 class="fw-bold">{{ number_format($totalDisbursements, 2) }}</h4>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-xl-2">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <p class="text-muted mb-1">Disbursement Rate</p>
                        <h4 class="fw-bold">{{ $disbursementRate }}%</h4>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-xl-2">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <p class="text-muted mb-1">Variance</p>
                        <h4 class="fw-bold {{ $variance < 0 ? 'text-danger' : 'text-success' }}">
                            {{ number_format($variance, 2) }}
                        </h4>
                    </div>
                </div>
            </div>
        </div>

        {{-- =====================================================
     * FINANCIAL EXECUTION GRAPHS (2 × 2 GRID)
     * ===================================================== --}}
        <div class="row g-4 mb-4">

            {{-- 1. Planned vs Actual --}}
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <h6 class="fw-semibold mb-1">Planned vs Committed vs Disbursed</h6>
                        <p class="text-muted small mb-3">
                            Compares yearly allocations, commitments, and paid disbursements to show execution progress over time.
                        </p>
                        <canvas id="executionLineChart" height="140"></canvas>
                    </div>
                </div>
            </div>

            {{-- 2. Yearly Variance --}}
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <h6 class="fw-semibold mb-1">Yearly Budget Variance</h6>
                        <p class="text-muted small mb-3">
                            Shows under- or over-execution per year (Allocation − Commitment).
                        </p>
                        <canvas id="executionVarianceChart" height="140"></canvas>
                    </div>
                </div>
            </div>

            {{-- 3. Cumulative Execution --}}
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <h6 class="fw-semibold mb-1">Cumulative Execution Momentum</h6>
                        <p class="text-muted small mb-3">
                            Tracks cumulative allocation, commitment, and disbursement to reveal long-term execution momentum.
                        </p>
                        <canvas id="executionCumulativeChart" height="140"></canvas>
                    </div>
                </div>
            </div>

            {{-- 4. Risk Bubble --}}
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <h6 class="fw-semibold mb-1">Execution Risk Concentration</h6>
                        <p class="text-muted small mb-3">
                            Highlights years with high financial exposure using variance-based bubble sizing.
                        </p>
                        <canvas id="executionBubbleChart" height="140"></canvas>
                    </div>
                </div>
            </div>

        </div>

        {{-- =====================================================
 * EXECUTION PERFORMANCE TABLE
 * ===================================================== --}}
        <div class="card shadow-sm border-0 mb-5">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="fw-semibold mb-1">Execution Performance Breakdown</h5>
                        <p class="text-muted small mb-0">
                            Year-by-year allocation, commitment, disbursement, remaining balance, and execution rates
                        </p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle w-100" id="executionTable">
                        <thead class="table-light">
                            <tr class="text-center">
                                <th>Year</th>
                                <th class="text-end">Allocated Amount</th>
                                <th class="text-end">Committed Amount</th>
                                <th class="text-end">Disbursed Amount</th>
                                <th class="text-end">Remaining</th>
                                <th class="text-center">Execution %</th>
                                <th class="text-center">Disbursement %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($years as $year)
                                @php
                                    $allocated = $allocationByYear[$year] ?? 0;
                                    $committed = $commitmentByYear[$year] ?? 0;
                                    $disbursed = $disbursementByYear[$year] ?? 0;
                                    $remaining = max($allocated - $committed, 0);
                                    $percent = $allocated > 0 ? min(100, ($committed / $allocated) * 100) : 0;
                                    $disbursementPercent = $committed > 0 ? min(100, ($disbursed / $committed) * 100) : 0;
                                @endphp
                                <tr>
                                    <td class="fw-semibold text-center">{{ $year }}</td>

                                    <td class="text-end">
                                        {{ number_format($allocated, 2) }}
                                    </td>

                                    <td class="text-end">
                                        {{ number_format($committed, 2) }}
                                    </td>

                                    <td class="text-end">
                                        {{ number_format($disbursed, 2) }}
                                    </td>

                                    <td class="text-end fw-semibold text-success">
                                        {{ number_format($remaining, 2) }}
                                    </td>

                                    <td class="text-center">
                                        <span
                                            class="badge rounded-pill
                                    {{ $percent < 50 ? 'bg-danger' : ($percent < 80 ? 'bg-warning text-dark' : 'bg-success') }}">
                                            {{ number_format($percent, 1) }}%
                                        </span>
                                    </td>

                                    <td class="text-center">
                                        <span
                                            class="badge rounded-pill
                                    {{ $disbursementPercent < 50 ? 'bg-danger' : ($disbursementPercent < 80 ? 'bg-warning text-dark' : 'bg-success') }}">
                                            {{ number_format($disbursementPercent, 1) }}%
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>

                        <tfoot class="table-light fw-semibold">
                            @php
                                $totalAlloc = collect($allocationByYear)->sum();
                                $totalCommit = collect($commitmentByYear)->sum();
                                $totalDisbursed = collect($disbursementByYear)->sum();
                                $totalRemain = max($totalAlloc - $totalCommit, 0);
                                $totalPercent = $totalAlloc > 0 ? min(100, ($totalCommit / $totalAlloc) * 100) : 0;
                                $totalDisbursementPercent = $totalCommit > 0 ? min(100, ($totalDisbursed / $totalCommit) * 100) : 0;
                            @endphp
                            <tr>
                                <td class="text-center">TOTAL</td>
                                <td class="text-end">{{ number_format($totalAlloc, 2) }}</td>
                                <td class="text-end">{{ number_format($totalCommit, 2) }}</td>
                                <td class="text-end">{{ number_format($totalDisbursed, 2) }}</td>
                                <td class="text-end text-success">
                                    {{ number_format($totalRemain, 2) }}
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary">
                                        {{ number_format($totalPercent, 1) }}%
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary">
                                        {{ number_format($totalDisbursementPercent, 1) }}%
                                    </span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>


        {{-- ================================
     * AI EXECUTION INSIGHTS (UNCHANGED)
     * ================================ --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h5 class="fw-semibold mb-3">AI Execution Insights</h5>

                @forelse($aiInsights as $insight)
                    <div class="alert alert-{{ $insight['type'] }} mb-3">
                        <h6 class="fw-semibold mb-1">{{ $insight['title'] }}</h6>
                        <p class="mb-0">{{ $insight['message'] }}</p>
                    </div>
                @empty
                    <p class="text-muted mb-0">
                        No significant execution risks or anomalies detected.
                    </p>
                @endforelse
            </div>
        </div>

    </div>







    <!-- =========================================================
                                                     LOAD LIBRARIES (ONCE ONLY)
                                                ========================================================= -->
    <script src="https://cdn.jsdelivr.net/npm/chartjs-chart-financial@0.2.1/dist/chartjs-chart-financial.min.js"></script>


    <!-- =========================================================
                                         LOAD CHART.JS (ONCE ONLY)
                                    ========================================================= -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            /* =========================================================
             * GLOBAL STYLING (CLEAN & PROFESSIONAL)
             * ========================================================= */
            Chart.defaults.font.family = "'Inter','Segoe UI',sans-serif";
            Chart.defaults.font.size = 12;
            Chart.defaults.color = '#6b7280';

            const YEARS = @json($years);

            const ALLOCATIONS = @json(collect($years)->map(fn($y) => $allocationByYear[$y] ?? 0)->values());

            const COMMITMENTS = @json(collect($years)->map(fn($y) => $commitmentByYear[$y] ?? 0)->values());

            const DISBURSEMENTS = @json(collect($years)->map(fn($y) => $disbursementByYear[$y] ?? 0)->values());

            const VARIANCE = ALLOCATIONS.map((a, i) => a - COMMITMENTS[i]);

            /* =========================================================
             * 1. LINE CHART — Planned vs Actual (CORE GRAPH)
             * ========================================================= */
            const lineEl = document.getElementById('executionLineChart');
            if (lineEl) {
                new Chart(lineEl, {
                    type: 'line',
                    data: {
                        labels: YEARS,
                        datasets: [{
                                label: 'Allocation (Planned)',
                                data: ALLOCATIONS,
                                borderColor: '#2563eb',
                                backgroundColor: 'rgba(37,99,235,0.15)',
                                fill: true,
                                tension: 0.4,
                                borderWidth: 2
                            },
                            {
                                label: 'Commitment (Actual)',
                                data: COMMITMENTS,
                                borderColor: '#16a34a',
                                backgroundColor: 'rgba(22,163,74,0.15)',
                                fill: true,
                                tension: 0.4,
                                borderWidth: 2
                            },
                            {
                                label: 'Paid Disbursements',
                                data: DISBURSEMENTS,
                                borderColor: '#f97316',
                                backgroundColor: 'rgba(249,115,22,0.12)',
                                fill: false,
                                tension: 0.4,
                                borderWidth: 3,
                                pointRadius: 4,
                                pointHoverRadius: 6
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }

            /* =========================================================
             * 2. VARIANCE BAR CHART — Under / Over Execution
             * ========================================================= */
            const varianceEl = document.getElementById('executionVarianceChart');
            if (varianceEl) {
                new Chart(varianceEl, {
                    type: 'bar',
                    data: {
                        labels: YEARS,
                        datasets: [{
                            label: 'Variance (Allocation − Commitment)',
                            data: VARIANCE,
                            backgroundColor: VARIANCE.map(v =>
                                v < 0 ? '#dc2626' : '#22c55e'
                            ),
                            borderRadius: 6
                        }]
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
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Variance Amount'
                                }
                            }
                        }
                    }
                });
            }

            /* =========================================================
             * 3. CUMULATIVE AREA CHART — Execution Momentum
             * ========================================================= */
            const cumulativeEl = document.getElementById('executionCumulativeChart');
            if (cumulativeEl) {

                let cumAlloc = 0;
                let cumCommit = 0;
                let cumDisbursement = 0;

                const CUM_ALLOC = ALLOCATIONS.map(v => (cumAlloc += v));
                const CUM_COMMIT = COMMITMENTS.map(v => (cumCommit += v));
                const CUM_DISBURSEMENT = DISBURSEMENTS.map(v => (cumDisbursement += v));

                new Chart(cumulativeEl, {
                    type: 'line',
                    data: {
                        labels: YEARS,
                        datasets: [{
                                label: 'Cumulative Allocation',
                                data: CUM_ALLOC,
                                borderColor: '#1d4ed8',
                                backgroundColor: 'rgba(29,78,216,0.2)',
                                fill: true,
                                tension: 0.3
                            },
                            {
                                label: 'Cumulative Commitment',
                                data: CUM_COMMIT,
                                borderColor: '#15803d',
                                backgroundColor: 'rgba(21,128,61,0.2)',
                                fill: true,
                                tension: 0.3
                            },
                            {
                                label: 'Cumulative Paid Disbursements',
                                data: CUM_DISBURSEMENT,
                                borderColor: '#ea580c',
                                backgroundColor: 'rgba(234,88,12,0.14)',
                                fill: true,
                                tension: 0.3,
                                borderWidth: 3,
                                pointRadius: 4,
                                pointHoverRadius: 6
                            }
                        ]
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
            }

            /* =========================================================
             * 4. BUBBLE CHART — Financial Risk Concentration
             * ========================================================= */
            const bubbleEl = document.getElementById('executionBubbleChart');
            if (bubbleEl) {

                const bubbleData = YEARS.map((year, i) => ({
                    x: year,
                    y: COMMITMENTS[i],
                    r: Math.max(6, Math.abs(VARIANCE[i]) / 10000)
                }));

                new Chart(bubbleEl, {
                    type: 'bubble',
                    data: {
                        datasets: [{
                            label: 'Execution Risk by Year',
                            data: bubbleData,
                            backgroundColor: 'rgba(234,179,8,0.45)',
                            borderColor: '#eab308'
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        },
                        scales: {
                            x: {
                                title: {
                                    display: true,
                                    text: 'Year'
                                }
                            },
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Commitment Amount'
                                }
                            }
                        }
                    }
                });
            }

        });
    </script>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const table = document.getElementById('executionTable');

            if (table) {
                new DataTable(table, {
                    paging: true,
                    searching: true,
                    ordering: true,
                    responsive: true,
                    pageLength: 10,
                    lengthMenu: [5, 10, 25, 50],
                    order: [
                        [0, 'asc']
                    ],
                    language: {
                        search: "Search year:",
                        lengthMenu: "Show _MENU_ entries",
                        info: "Showing _START_ to _END_ of _TOTAL_ records"
                    }
                });
            }
        });
    </script>
@endsection
