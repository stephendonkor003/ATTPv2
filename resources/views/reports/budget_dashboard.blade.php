@extends('layouts.app')

@section('title', 'Budget Summary Dashboard')

@section('content')
    @php
        $money = fn ($value) => number_format((float) $value, 2);
        $topPrograms = $programRows->sortByDesc('total_allocated')->take(8)->values();
    @endphp

    <style>
        .budget-summary-page {
            color: #111827;
        }

        .bs-hero {
            background: linear-gradient(120deg, #0f172a 0%, #0f766e 48%, #2563eb 100%);
            border-radius: 8px;
            color: #fff;
            overflow: hidden;
            position: relative;
        }

        .bs-hero::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(255,255,255,.14), transparent 48%);
            pointer-events: none;
        }

        .bs-hero-body {
            padding: 28px;
            position: relative;
            z-index: 1;
        }

        .bs-pdf-btn {
            background: linear-gradient(135deg, #fbbf24, #f97316);
            border: 0;
            color: #111827;
            font-weight: 800;
            box-shadow: 0 12px 22px rgba(15, 23, 42, .22);
            transition: transform .16s ease, box-shadow .16s ease;
        }

        .bs-pdf-btn:hover,
        .bs-pdf-btn:focus {
            color: #111827;
            box-shadow: 0 16px 28px rgba(15, 23, 42, .3);
            transform: translateY(-2px);
        }

        .bs-card,
        .bs-panel,
        .bs-sector-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }

        .bs-card,
        .bs-sector-card {
            height: 100%;
            padding: 18px;
            transition: transform .16s ease, box-shadow .16s ease;
        }

        .bs-card:hover,
        .bs-sector-card:hover {
            box-shadow: 0 14px 30px rgba(15, 23, 42, .11);
            transform: translateY(-2px);
        }

        .bs-card-icon {
            align-items: center;
            background: #eff6ff;
            border-radius: 8px;
            color: #1d4ed8;
            display: inline-flex;
            font-size: 1.1rem;
            height: 40px;
            justify-content: center;
            width: 40px;
        }

        .bs-panel-header {
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
            padding: 18px 20px;
        }

        .bs-panel-body {
            padding: 20px;
        }

        .bs-chart-box {
            min-height: 300px;
            position: relative;
        }

        .bs-chart-box.sm {
            min-height: 250px;
        }

        .bs-chart-box canvas {
            height: 100% !important;
            width: 100% !important;
        }

        .bs-progress {
            background: #e5e7eb;
            border-radius: 999px;
            height: 8px;
            overflow: hidden;
        }

        .bs-progress span {
            background: linear-gradient(90deg, #0f766e, #2563eb);
            border-radius: 999px;
            display: block;
            height: 100%;
        }

        .bs-chip {
            align-items: center;
            background: #eff6ff;
            border: 1px solid #dbeafe;
            border-radius: 999px;
            color: #1e40af;
            display: inline-flex;
            font-size: .78rem;
            font-weight: 700;
            gap: 6px;
            padding: 5px 10px;
        }

        .bs-table-wrap {
            max-height: 560px;
            overflow: auto;
        }

        @media (max-width: 767.98px) {
            .bs-hero-body,
            .bs-panel-body {
                padding: 18px;
            }

            .bs-chart-box {
                min-height: 250px;
            }
        }
    </style>

    <main class="nxl-container budget-summary-page">
        <div class="bs-hero mb-4">
            <div class="bs-hero-body d-flex flex-column flex-xl-row justify-content-between gap-3">
                <div>
                    <div class="text-uppercase small fw-semibold text-white-50 mb-2">Budget Summary</div>
                    <h3 class="fw-bold mb-2 text-white">Budget Allocation Dashboard</h3>
                    <p class="mb-0 text-white-50">
                        Portfolio budget, allocation coverage, remaining balance, and program funding concentration.
                    </p>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-start">
                    <a href="{{ route('budget.summary.dashboard.export.pdf') }}" class="btn bs-pdf-btn">
                        <i class="feather-download me-1"></i> Download PDF
                    </a>
                    <a href="{{ route('budget.summary.executive') }}" class="btn btn-outline-light">
                        <i class="feather-bar-chart-2 me-1"></i> Executive Report
                    </a>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6 col-xl-3">
                <div class="bs-card">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="text-muted small">Total Budget</div>
                            <div class="h4 fw-bold mb-0">{{ $money($summary['total_budget']) }}</div>
                        </div>
                        <span class="bs-card-icon"><i class="feather-dollar-sign"></i></span>
                    </div>
                    <div class="small text-muted mt-3">{{ number_format($totalProjects) }} projects in scope</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="bs-card">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="text-muted small">Total Allocated</div>
                            <div class="h4 fw-bold mb-0">{{ $money($summary['total_allocated']) }}</div>
                        </div>
                        <span class="bs-card-icon"><i class="feather-check-circle"></i></span>
                    </div>
                    <div class="small text-muted mt-3">{{ number_format($summary['allocation_rate'], 1) }}% allocation coverage</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="bs-card">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="text-muted small">Remaining Balance</div>
                            <div class="h4 fw-bold mb-0 {{ $summary['remaining_budget'] < 0 ? 'text-danger' : 'text-success' }}">
                                {{ $money($summary['remaining_budget']) }}
                            </div>
                        </div>
                        <span class="bs-card-icon"><i class="feather-pie-chart"></i></span>
                    </div>
                    <div class="small text-muted mt-3">Budget minus project-level allocation</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="bs-card">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="text-muted small">Structure</div>
                            <div class="h4 fw-bold mb-0">{{ number_format($totalPrograms) }}</div>
                        </div>
                        <span class="bs-card-icon"><i class="feather-layers"></i></span>
                    </div>
                    <div class="small text-muted mt-3">
                        {{ number_format($totalActivities) }} activities, {{ number_format($totalSubActivities) }} sub-activities
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-4">
                <div class="bs-card">
                    <div class="text-muted small">Project-Level Allocation</div>
                    <div class="h5 fw-bold mb-2">{{ $money($summary['project_allocated']) }}</div>
                    <div class="bs-progress">
                        <span style="width: {{ $summary['total_allocated'] > 0 ? min(100, ($summary['project_allocated'] / $summary['total_allocated']) * 100) : 0 }}%;"></span>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="bs-card">
                    <div class="text-muted small">Activity-Level Allocation</div>
                    <div class="h5 fw-bold mb-2">{{ $money($summary['activity_allocated']) }}</div>
                    <div class="bs-progress">
                        <span style="width: {{ $summary['total_allocated'] > 0 ? min(100, ($summary['activity_allocated'] / $summary['total_allocated']) * 100) : 0 }}%;"></span>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="bs-card">
                    <div class="text-muted small">Sub-Activity Allocation</div>
                    <div class="h5 fw-bold mb-2">{{ $money($summary['sub_activity_allocated']) }}</div>
                    <div class="bs-progress">
                        <span style="width: {{ $summary['total_allocated'] > 0 ? min(100, ($summary['sub_activity_allocated'] / $summary['total_allocated']) * 100) : 0 }}%;"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-xl-8">
                <div class="bs-panel h-100">
                    <div class="bs-panel-header">
                        <h5 class="fw-bold mb-1">Top Program Allocation</h5>
                        <div class="text-muted small">Budget and allocation comparison by program</div>
                    </div>
                    <div class="bs-panel-body">
                        <div class="bs-chart-box">
                            <canvas id="programAllocationChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="bs-panel h-100">
                    <div class="bs-panel-header">
                        <h5 class="fw-bold mb-1">Allocation Split</h5>
                        <div class="text-muted small">Project envelope with downstream activity and sub-activity totals</div>
                    </div>
                    <div class="bs-panel-body">
                        <div class="bs-chart-box sm">
                            <canvas id="allocationSplitChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            @forelse ($sectorRows as $sector)
                @php
                    $share = $summary['total_allocated'] > 0 ? (($sector['total_allocated'] / $summary['total_allocated']) * 100) : 0;
                @endphp
                <div class="col-md-6 col-xl-3">
                    <div class="bs-sector-card">
                        <div class="d-flex justify-content-between gap-2 mb-2">
                            <div class="fw-bold">{{ $sector['sector'] }}</div>
                            <span class="bs-chip">{{ number_format($share, 1) }}%</span>
                        </div>
                        <div class="text-muted small mb-3">{{ number_format($sector['programs']) }} programs, {{ number_format($sector['projects']) }} projects</div>
                        <div class="h5 fw-bold mb-2">{{ $money($sector['total_allocated']) }}</div>
                        <div class="bs-progress"><span style="width: {{ min(100, $share) }}%;"></span></div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="alert alert-light border mb-0">No sector allocation data available.</div>
                </div>
            @endforelse
        </div>

        <div class="bs-panel mb-5">
            <div class="bs-panel-header d-flex flex-column flex-lg-row justify-content-between gap-2">
                <div>
                    <h5 class="fw-bold mb-1">Program Allocation Breakdown</h5>
                    <div class="text-muted small">Program budget, project-level allocation, remaining balance, and utilization.</div>
                </div>
                <span class="bs-chip">{{ number_format($programRows->count()) }} programs</span>
            </div>
            <div class="bs-panel-body">
                <div class="table-responsive bs-table-wrap">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Program</th>
                                <th>Sector</th>
                                <th class="text-center">Projects</th>
                                <th class="text-center">Activities</th>
                                <th class="text-end">Budget</th>
                                <th class="text-end">Allocated</th>
                                <th class="text-end">Remaining</th>
                                <th style="min-width: 170px;">Utilization</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($programRows->sortByDesc('total_allocated') as $row)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $row['name'] }}</div>
                                        <div class="small text-muted">{{ $row['sub_activities'] }} sub-activities</div>
                                    </td>
                                    <td>{{ $row['sector'] }}</td>
                                    <td class="text-center">{{ number_format($row['projects']) }}</td>
                                    <td class="text-center">{{ number_format($row['activities']) }}</td>
                                    <td class="text-end">{{ $money($row['total_budget']) }}</td>
                                    <td class="text-end fw-semibold">{{ $money($row['total_allocated']) }}</td>
                                    <td class="text-end {{ $row['remaining'] < 0 ? 'text-danger' : 'text-success' }}">{{ $money($row['remaining']) }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="bs-progress flex-grow-1">
                                                <span style="width: {{ min(100, max(0, $row['utilization'])) }}%;"></span>
                                            </div>
                                            <span class="small fw-semibold">{{ number_format($row['utilization'], 1) }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No budget programs found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.Chart) return;

            const charts = @json($chartData);
            const money = (value) => Number(value || 0).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            const axisMoney = (value) => {
                const number = Number(value || 0);
                if (number >= 1000000) return `${(number / 1000000).toFixed(1)}M`;
                if (number >= 1000) return `${(number / 1000).toFixed(1)}K`;
                return number.toLocaleString();
            };

            Chart.defaults.font.family = "'Inter', 'Segoe UI', sans-serif";
            Chart.defaults.color = '#475569';
            Chart.defaults.plugins.tooltip.backgroundColor = '#0f172a';
            Chart.defaults.plugins.tooltip.cornerRadius = 8;
            Chart.defaults.plugins.tooltip.padding = 12;

            const programCanvas = document.getElementById('programAllocationChart');
            if (programCanvas && Array.isArray(charts.programLabels) && charts.programLabels.length) {
                new Chart(programCanvas, {
                    type: 'bar',
                    data: {
                        labels: charts.programLabels,
                        datasets: [
                            {
                                label: 'Budget',
                                data: charts.programBudget,
                                backgroundColor: '#dbeafe',
                                borderRadius: 6,
                                maxBarThickness: 30
                            },
                            {
                                label: 'Allocated',
                                data: charts.programAllocated,
                                backgroundColor: '#0f766e',
                                borderRadius: 6,
                                maxBarThickness: 30
                            }
                        ]
                    },
                    options: {
                        indexAxis: 'y',
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                            tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${money(ctx.raw)}` } }
                        },
                        scales: {
                            x: { beginAtZero: true, ticks: { callback: axisMoney }, grid: { color: '#e5e7eb' } },
                            y: { grid: { display: false } }
                        }
                    }
                });
            }

            const splitCanvas = document.getElementById('allocationSplitChart');
            if (splitCanvas && Array.isArray(charts.allocationSplit) && charts.allocationSplit.some(value => Number(value || 0) > 0)) {
                new Chart(splitCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: charts.allocationSplitLabels,
                        datasets: [{
                            data: charts.allocationSplit,
                            backgroundColor: ['#2563eb', '#0f766e', '#f97316'],
                            borderColor: '#ffffff',
                            borderWidth: 3,
                            hoverOffset: 8
                        }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        cutout: '66%',
                        plugins: {
                            legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                            tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${money(ctx.raw)}` } }
                        }
                    }
                });
            }
        });
    </script>
@endsection
