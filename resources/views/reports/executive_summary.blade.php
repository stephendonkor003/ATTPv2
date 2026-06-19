@extends('layouts.app')

@section('title', 'Executive Budget Summary')

@section('content')
    @php
        $money = fn ($value) => number_format((float) $value, 2);
    @endphp

    <style>
        .exec-summary-page {
            color: #111827;
        }

        .es-hero {
            background: linear-gradient(120deg, #0f172a 0%, #0f766e 48%, #2563eb 100%);
            border-radius: 8px;
            color: #fff;
            overflow: hidden;
            position: relative;
        }

        .es-hero::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(255,255,255,.14), transparent 48%);
            pointer-events: none;
        }

        .es-hero-body {
            padding: 28px;
            position: relative;
            z-index: 1;
        }

        .es-pdf-btn {
            background: linear-gradient(135deg, #fbbf24, #f97316);
            border: 0;
            color: #111827;
            font-weight: 800;
            box-shadow: 0 12px 22px rgba(15, 23, 42, .22);
            transition: transform .16s ease, box-shadow .16s ease;
        }

        .es-pdf-btn:hover,
        .es-pdf-btn:focus {
            color: #111827;
            box-shadow: 0 16px 28px rgba(15, 23, 42, .3);
            transform: translateY(-2px);
        }

        .es-card,
        .es-panel,
        .es-insight {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }

        .es-card,
        .es-insight {
            height: 100%;
            padding: 18px;
            transition: transform .16s ease, box-shadow .16s ease;
        }

        .es-card:hover,
        .es-insight:hover {
            box-shadow: 0 14px 30px rgba(15, 23, 42, .11);
            transform: translateY(-2px);
        }

        .es-card-icon {
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

        .es-panel-header {
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
            padding: 18px 20px;
        }

        .es-panel-body {
            padding: 20px;
        }

        .es-chart-box {
            min-height: 305px;
            position: relative;
        }

        .es-chart-box.sm {
            min-height: 260px;
        }

        .es-chart-box canvas {
            height: 100% !important;
            width: 100% !important;
        }

        .es-rank {
            align-items: center;
            background: #eff6ff;
            border-radius: 999px;
            color: #1e40af;
            display: inline-flex;
            font-size: .78rem;
            font-weight: 800;
            height: 30px;
            justify-content: center;
            width: 30px;
        }

        .es-chip {
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

        .es-progress {
            background: #e5e7eb;
            border-radius: 999px;
            height: 8px;
            overflow: hidden;
        }

        .es-progress span {
            background: linear-gradient(90deg, #0f766e, #2563eb);
            border-radius: 999px;
            display: block;
            height: 100%;
        }

        .es-table-wrap {
            max-height: 560px;
            overflow: auto;
        }

        @media (max-width: 767.98px) {
            .es-hero-body,
            .es-panel-body {
                padding: 18px;
            }

            .es-chart-box {
                min-height: 250px;
            }
        }
    </style>

    <main class="nxl-container exec-summary-page">
        <div class="es-hero mb-4">
            <div class="es-hero-body d-flex flex-column flex-xl-row justify-content-between gap-3">
                <div>
                    <div class="text-uppercase small fw-semibold text-white-50 mb-2">Budget Summary</div>
                    <h3 class="fw-bold mb-2 text-white">Executive Summary Report</h3>
                    <p class="mb-0 text-white-50">
                        Ranked financial insights across projects, activities, sub-activities, and program portfolios.
                    </p>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-start">
                    <a href="{{ route('budget.summary.executive.export.pdf') }}" class="btn es-pdf-btn">
                        <i class="feather-download me-1"></i> Download PDF
                    </a>
                    <a href="{{ route('budget.summary.dashboard') }}" class="btn btn-outline-light">
                        <i class="feather-grid me-1"></i> Budget Dashboard
                    </a>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6 col-xl-3">
                <div class="es-card">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="text-muted small">Project Allocation</div>
                            <div class="h4 fw-bold mb-0">{{ $money($executiveStats['total_allocated']) }}</div>
                        </div>
                        <span class="es-card-icon"><i class="feather-award"></i></span>
                    </div>
                    <div class="small text-muted mt-3">{{ number_format($executiveStats['projects']) }} projects ranked</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="es-card">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="text-muted small">Project Budget</div>
                            <div class="h4 fw-bold mb-0">{{ $money($executiveStats['total_budget']) }}</div>
                        </div>
                        <span class="es-card-icon"><i class="feather-dollar-sign"></i></span>
                    </div>
                    <div class="small text-muted mt-3">Remaining: {{ $money($executiveStats['remaining']) }}</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="es-card">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="text-muted small">Activities Ranked</div>
                            <div class="h4 fw-bold mb-0">{{ number_format($executiveStats['activities']) }}</div>
                        </div>
                        <span class="es-card-icon"><i class="feather-list"></i></span>
                    </div>
                    <div class="small text-muted mt-3">{{ number_format($executiveStats['sub_activities']) }} sub-activities ranked</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="es-card">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="text-muted small">Avg. Project Allocation</div>
                            <div class="h4 fw-bold mb-0">{{ $money($executiveStats['average_project_allocation']) }}</div>
                        </div>
                        <span class="es-card-icon"><i class="feather-trending-up"></i></span>
                    </div>
                    <div class="small text-muted mt-3">{{ number_format($executiveStats['programs']) }} programs in portfolio</div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-4">
                <div class="es-insight">
                    <div class="text-muted small text-uppercase fw-bold mb-1">Top Project</div>
                    <div class="h5 fw-bold mb-0">{{ $executiveStats['top_project'] ?? 'N/A' }}</div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="es-insight">
                    <div class="text-muted small text-uppercase fw-bold mb-1">Top Activity</div>
                    <div class="h5 fw-bold mb-0">{{ $executiveStats['top_activity'] ?? 'N/A' }}</div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="es-insight">
                    <div class="text-muted small text-uppercase fw-bold mb-1">Top Sub-Activity</div>
                    <div class="h5 fw-bold mb-0">{{ $executiveStats['top_sub_activity'] ?? 'N/A' }}</div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-xl-8">
                <div class="es-panel h-100">
                    <div class="es-panel-header">
                        <h5 class="fw-bold mb-1">Top Funded Projects</h5>
                        <div class="text-muted small">Budget and allocation comparison for the highest-ranked projects.</div>
                    </div>
                    <div class="es-panel-body">
                        <div class="es-chart-box">
                            <canvas id="projectRankingChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="es-panel h-100">
                    <div class="es-panel-header">
                        <h5 class="fw-bold mb-1">Program Concentration</h5>
                        <div class="text-muted small">Top program allocation envelopes.</div>
                    </div>
                    <div class="es-panel-body">
                        <div class="es-chart-box sm">
                            <canvas id="programConcentrationChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-xl-6">
                <div class="es-panel h-100">
                    <div class="es-panel-header">
                        <h5 class="fw-bold mb-1">Top Activities</h5>
                        <div class="text-muted small">Most financially significant activities.</div>
                    </div>
                    <div class="es-panel-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Activity</th>
                                        <th>Project</th>
                                        <th class="text-end">Allocated</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($activityRankings->take(10) as $item)
                                        <tr>
                                            <td><span class="es-rank">{{ $loop->iteration }}</span></td>
                                            <td class="fw-semibold">{{ $item['activity']->name }}</td>
                                            <td>{{ $item['project']?->name ?? 'N/A' }}</td>
                                            <td class="text-end fw-semibold">{{ $money($item['allocated']) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted py-4">No activities found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="es-panel h-100">
                    <div class="es-panel-header">
                        <h5 class="fw-bold mb-1">Top Sub-Activities</h5>
                        <div class="text-muted small">Granular implementation items with the highest allocation.</div>
                    </div>
                    <div class="es-panel-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Sub-Activity</th>
                                        <th>Activity</th>
                                        <th class="text-end">Allocated</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($subActivityRankings->take(10) as $item)
                                        <tr>
                                            <td><span class="es-rank">{{ $loop->iteration }}</span></td>
                                            <td class="fw-semibold">{{ $item['sub']->name }}</td>
                                            <td>{{ $item['activity']?->name ?? 'N/A' }}</td>
                                            <td class="text-end fw-semibold">{{ $money($item['allocated']) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted py-4">No sub-activities found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="es-panel mb-4">
            <div class="es-panel-header d-flex flex-column flex-lg-row justify-content-between gap-2">
                <div>
                    <h5 class="fw-bold mb-1">Project Funding Leaderboard</h5>
                    <div class="text-muted small">Projects ranked by total allocation across project, activity, and sub-activity levels.</div>
                </div>
                <span class="es-chip">{{ number_format($projectRankings->count()) }} projects</span>
            </div>
            <div class="es-panel-body">
                <div class="table-responsive es-table-wrap">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Project</th>
                                <th>Program</th>
                                <th class="text-end">Budget</th>
                                <th class="text-end">Allocated</th>
                                <th class="text-end">Remaining</th>
                                <th style="min-width: 170px;">Utilization</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($projectRankings as $item)
                                <tr>
                                    <td><span class="es-rank">{{ $loop->iteration }}</span></td>
                                    <td>
                                        <div class="fw-semibold">{{ $item['project']->name }}</div>
                                        <div class="small text-muted">{{ $item['project']->project_id }}</div>
                                    </td>
                                    <td>{{ $item['project']->program?->name ?? 'N/A' }}</td>
                                    <td class="text-end">{{ $money($item['budget']) }}</td>
                                    <td class="text-end fw-semibold">{{ $money($item['allocated']) }}</td>
                                    <td class="text-end {{ $item['remaining'] < 0 ? 'text-danger' : 'text-success' }}">{{ $money($item['remaining']) }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="es-progress flex-grow-1">
                                                <span style="width: {{ min(100, max(0, $item['utilization'])) }}%;"></span>
                                            </div>
                                            <span class="small fw-semibold">{{ number_format($item['utilization'], 1) }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-4">No projects found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="es-panel mb-5">
            <div class="es-panel-header d-flex flex-column flex-lg-row justify-content-between gap-2">
                <div>
                    <h5 class="fw-bold mb-1">Program Portfolio Sheet</h5>
                    <div class="text-muted small">Program-level budget, allocation, remaining balance, and portfolio structure.</div>
                </div>
                <span class="es-chip">{{ number_format($programSheets->count()) }} programs</span>
            </div>
            <div class="es-panel-body">
                <div class="table-responsive es-table-wrap">
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
                            @forelse ($programSheets as $row)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $row['name'] }}</div>
                                        <div class="small text-muted">{{ $row['sub_activities'] }} sub-activities</div>
                                    </td>
                                    <td>{{ $row['sector'] }}</td>
                                    <td class="text-center">{{ number_format($row['projects']) }}</td>
                                    <td class="text-center">{{ number_format($row['activities']) }}</td>
                                    <td class="text-end">{{ $money($row['budget']) }}</td>
                                    <td class="text-end fw-semibold">{{ $money($row['allocated']) }}</td>
                                    <td class="text-end {{ $row['remaining'] < 0 ? 'text-danger' : 'text-success' }}">{{ $money($row['remaining']) }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="es-progress flex-grow-1">
                                                <span style="width: {{ min(100, max(0, $row['utilization'])) }}%;"></span>
                                            </div>
                                            <span class="small fw-semibold">{{ number_format($row['utilization'], 1) }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted py-4">No programs found.</td></tr>
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

            const projectCanvas = document.getElementById('projectRankingChart');
            if (projectCanvas && Array.isArray(charts.projectLabels) && charts.projectLabels.length) {
                new Chart(projectCanvas, {
                    type: 'bar',
                    data: {
                        labels: charts.projectLabels,
                        datasets: [
                            {
                                label: 'Budget',
                                data: charts.projectBudgets,
                                backgroundColor: '#dbeafe',
                                borderRadius: 6,
                                maxBarThickness: 28
                            },
                            {
                                label: 'Allocated',
                                data: charts.projectAllocated,
                                backgroundColor: '#0f766e',
                                borderRadius: 6,
                                maxBarThickness: 28
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

            const programCanvas = document.getElementById('programConcentrationChart');
            if (programCanvas && Array.isArray(charts.programAllocated) && charts.programAllocated.some(value => Number(value || 0) > 0)) {
                new Chart(programCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: charts.programLabels,
                        datasets: [{
                            data: charts.programAllocated,
                            backgroundColor: ['#0f766e', '#2563eb', '#7c3aed', '#f97316', '#db2777', '#0891b2', '#65a30d', '#334155', '#dc2626', '#4f46e5'],
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
