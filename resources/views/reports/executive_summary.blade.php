@extends('layouts.app')

@section('title', 'Executive Reports')

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

        .rank-badge {
            font-size: 12px;
            padding: 5px 10px;
            border-radius: 50px;
        }

        .leader-card {
            border-left: 4px solid #0d6efd;
            transition: .3s;
        }

        .leader-card:hover {
            background: #f8faff;
            transform: translateY(-2px);
        }
    </style>

    <style>
        .excel-program-row {
            background: #d9ead3 !important;
            font-size: 15px;
        }

        .excel-project-row {
            background: #cfe2f3 !important;
            font-weight: bold;
        }

        .excel-activity-row {
            background: #f9f9f9 !important;
            font-weight: 600;
        }

        .excel-sub-row {
            background: #ffffff !important;
        }

        .excel-table td,
        .excel-table th {
            padding: 6px 10px !important;
        }
    </style>

    <main class="nxl-container fade-in">
        <div class="nxl-content">

            <!-- HEADER -->
            <div class="page-header mb-4">
                <h4 class="fw-bold">📊 Executive Summary Report</h4>
                <p class="text-muted">High-level financial insights to support strategic decisions.</p>
            </div>


            <!-- KPI CARDS -->
            <div class="row g-3 mb-4">

                <div class="col-md-4">
                    <div class="card shadow-sm kpi-card p-3">
                        <h6 class="text-muted">Total Programs</h6>
                        <h3 class="fw-bold">{{ $programs->count() }}</h3>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow-sm kpi-card p-3">
                        <h6 class="text-muted">Total Projects Ranked</h6>
                        <h3 class="fw-bold">{{ $projectRankings->count() }}</h3>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow-sm kpi-card p-3">
                        <h6 class="text-muted">Total Activities Ranked</h6>
                        <h3 class="fw-bold">{{ $activityRankings->count() }}</h3>
                    </div>
                </div>

            </div>



            <!-- FUNDING LEADERBOARD -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">

                    <h5 class="fw-bold">🏆 Top Funded Projects</h5>
                    <p class="text-muted small">Projects ranked based on total allocation received.</p>

                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Project</th>
                                <th>Program</th>
                                <th>Total Allocation</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($projectRankings as $index => $item)
                                <tr>
                                    <td>
                                        <span class="rank-badge bg-primary text-white fw-bold">
                                            {{ $index + 1 }}
                                        </span>
                                    </td>
                                    <td>
                                        <strong>{{ $item['project']->name }}</strong><br>
                                        <small class="text-muted">{{ $item['project']->project_id }}</small>
                                    </td>
                                    <td>{{ $item['project']->program->name ?? 'N/A' }}</td>
                                    <td class="fw-bold">
                                        {{ number_format($item['allocated'], 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                </div>
            </div>




            <!-- TOP ACTIVITIES -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <h5 class="fw-bold">🔥 Top Activities by Funding</h5>
                    <p class="text-muted small">Most financially significant activities across all projects.</p>

                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Activity</th>
                                <th>Project</th>
                                <th>Total Allocation</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($activityRankings as $index => $item)
                                <tr>
                                    <td>
                                        <span class="rank-badge bg-success text-white fw-bold">
                                            {{ $index + 1 }}
                                        </span>
                                    </td>
                                    <td>{{ $item['activity']->name }}</td>
                                    <td>{{ $item['project']->name ?? 'N/A' }}</td>
                                    <td class="fw-bold">
                                        {{ number_format($item['allocated'], 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                </div>
            </div>



            <!-- TOP SUB-ACTIVITIES -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">

                    <h5 class="fw-bold">📍 Top Sub-Activities</h5>
                    <p class="text-muted small">Sub-activities ranked by their financial weight.</p>

                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Sub-Activity</th>
                                <th>Activity</th>
                                <th>Project</th>
                                <th>Allocated</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($subActivityRankings as $index => $item)
                                <tr>
                                    <td>
                                        <span class="rank-badge bg-info text-white fw-bold">
                                            {{ $index + 1 }}
                                        </span>
                                    </td>
                                    <td>{{ $item['sub']->name }}</td>
                                    <td>{{ $item['activity']->name }}</td>
                                    <td>{{ $item['project']->name }}</td>
                                    <td class="fw-bold">{{ number_format($item['allocated'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                </div>
            </div>



            <!-- CHARTS -->
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">📈 Project Allocation Distribution</h5>
                    <div id="projectAllocChart" style="height: 350px;"></div>
                </div>
            </div>

        </div>


        <!-- PROJECT & ACTIVITY LINE GRAPHS -->
        <div class="card shadow-sm border-0 mt-5 mb-4">
            <div class="card-body">
                <h4 class="fw-bold mb-3">📉 Allocation Line Trends</h4>
                <p class="text-muted">
                    Activity trends within each project, plus sub-activity trends within any selected activity.
                    Use the dropdowns to drill into sub-activities.
                </p>

                @foreach ($projectRankings as $index => $item)
                    @php
                        $project = $item['project'];
                        $projectChartId = 'projectLineChart_' . $project->id;
                        $activitySelectId = 'activitySelect_' . $project->id;
                        $subChartId = 'subLineChart_' . $project->id;
                        $years = range($project->start_year, $project->end_year);
                    @endphp

                    <div class="card mb-4 leader-card p-3">
                        <h5 class="fw-bold mb-1">
                            📌 {{ $project->name }}
                        </h5>
                        <small class="text-muted">Project Code: {{ $project->project_id }}</small>

                        <!-- Activity-level line chart -->
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div id="{{ $projectChartId }}" style="height: 320px; width: 100%;"></div>
                            <div class="ms-3" style="min-width:150px;">
                                <button class="btn btn-outline-primary btn-sm w-100 mb-2"
                                    onclick="downloadChartPNG('{{ $projectChartId }}')">
                                    <i class="bi bi-file-earmark-image"></i> PNG
                                </button>
                                <button class="btn btn-outline-secondary btn-sm w-100"
                                    onclick="downloadChartSVG('{{ $projectChartId }}')">
                                    <i class="bi bi-filetype-svg"></i> SVG
                                </button>
                            </div>
                        </div>

                        <!-- Sub-activity line chart (filter by activity) -->
                        <div class="mt-4">
                            <div class="d-flex align-items-center mb-2">
                                <label class="fw-semibold me-2">Sub-activity trends for activity:</label>
                                <select id="{{ $activitySelectId }}" class="form-select form-select-sm" style="max-width: 320px;">
                                    @foreach ($project->activities as $activity)
                                        <option value="{{ $activity->id }}">{{ $activity->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div id="{{ $subChartId }}" style="height: 260px; width: 100%;"></div>
                                <div class="ms-3" style="min-width:150px;">
                                    <button class="btn btn-outline-primary btn-sm w-100 mb-2"
                                        onclick="downloadChartPNG('{{ $subChartId }}')">
                                        <i class="bi bi-file-earmark-image"></i> PNG
                                    </button>
                                    <button class="btn btn-outline-secondary btn-sm w-100"
                                        onclick="downloadChartSVG('{{ $subChartId }}')">
                                        <i class="bi bi-filetype-svg"></i> SVG
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Interpretation Summary -->
                        <div class="alert alert-info mt-3">
                            <strong>Interpretation:</strong>
                            Activity chart: compares all activities within this project across years. <br>
                            Sub-activity chart: pick an activity to see its sub-activities over time.
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <!-- ============================================
                                         EXCEL-STYLE HIERARCHICAL BUDGET TABLE
                                    =============================================== -->
        <!-- ============================================
                                 PROGRAM-BY-PROGRAM EXCEL-STYLE BUDGET SHEETS
                            =============================================== -->

        @foreach ($programs as $program)
            <div class="card shadow-sm mt-5 mb-4">
                <div class="card-body">

                    @php
                        $maxYears = 5;
                        $programCurrency = $program->currency ?? ($program->projects->first()->currency ?? '—');

                        $programTotalsBudget = 0;
                        $programTotalsCff = 0;
                        $programTotalsOverall = 0;
                        $programYearTotals = [];
                        for ($i = 2; $i <= $maxYears; $i++) {
                            $programYearTotals[$i] = 0;
                        }
                        // Pre-compute program totals before rendering table rows
                        foreach ($program->projects as $projectTotalsHelper) {
                            $startYearHelper = $projectTotalsHelper->start_year;
                            $budgetHelper = $projectTotalsHelper->total_budget;
                            $cffHelper = $projectTotalsHelper->activities->sum(
                                fn($a) => $a->allocations->where('year', $startYearHelper)->sum('amount'),
                            );
                            $totalHelper = $projectTotalsHelper->activities->sum(fn($a) => $a->allocations->sum('amount'));

                            $programTotalsBudget += $budgetHelper;
                            $programTotalsCff += $cffHelper;
                            $programTotalsOverall += $totalHelper;

                            for ($i = 2; $i <= $maxYears; $i++) {
                                $yr = $startYearHelper + ($i - 1);
                                $v = $projectTotalsHelper->activities->sum(
                                    fn($a) => $a->allocations->where('year', $yr)->sum('amount'),
                                );
                                $programYearTotals[$i] += $v;
                            }
                        }
                    @endphp

                    <h4 class="fw-bold mb-2">📘 Budget Sheet — {{ $program->name }}</h4>
                    <p class="text-muted small mb-3">
                        Program Code: <strong>{{ $program->program_id }}</strong>
                        <span class="ms-3">Currency: <strong>{{ $programCurrency }}</strong></span>
                    </p>

                    <!-- SCROLL CONTAINER WITH STICKY HEADER -->
                    <div class="table-responsive" style="max-height: 650px; overflow-y: auto;">
                        <table class="table table-bordered excel-table align-middle">

                            <!-- =================== HEADER =================== -->
                            <thead class="table-warning fw-bold text-center sticky-header">
                                <tr>
                                    <th style="width: 120px;">ID</th>
                                    <th>Project / Activity / Sub-Activity</th>
                                    <th class="text-end">Budget</th>
                                    <th class="text-end">CFF (Yr 1)</th>

                                    @php $maxYears = 5; @endphp
                                    @for ($i = 2; $i <= $maxYears; $i++)
                                        <th class="text-end">Year {{ $i }}</th>
                                    @endfor

                                    <th class="text-end">Total</th>
                                    <th style="width: 50px;">⇅</th>
                                </tr>
                            </thead>


                            <!-- =================== BODY =================== -->
                            <tbody>

                                <!-- PROGRAM ROW -->
                                <tr class="excel-program-row expand-toggle" data-target="program-{{ $program->id }}">
                                    <td class="fw-bold text-center">{{ $program->program_id }}</td>
                                    <td class="fw-bold">📘 {{ $program->name }}</td>
                                    <td colspan="{{ 2 + ($maxYears - 1) + 1 }}"></td>
                                    <td class="toggle-icon text-center">+</td>
                                </tr>

                                <!-- PROGRAM TOTAL SUMMARY -->
                                <tr class="table-info fw-bold">
                                    <td class="text-center">{{ $program->program_id }} TOTAL</td>
                                    <td>Program Total ({{ $programCurrency }})</td>
                                    <td class="text-end">{{ number_format($programTotalsBudget, 2) }}</td>
                                    <td class="text-end">{{ number_format($programTotalsCff, 2) }}</td>

                                    @for ($i = 2; $i <= $maxYears; $i++)
                                        <td class="text-end">{{ number_format($programYearTotals[$i] ?? 0, 2) }}</td>
                                    @endfor

                                    <td class="text-end">{{ number_format($programTotalsOverall, 2) }}</td>
                                    <td class="text-center">—</td>
                                </tr>

                                <!-- PROJECTS LOOP -->
                                @foreach ($program->projects as $project)
                                    @php
                                        $start = $project->start_year;
                                        $budget = $project->total_budget;
                                        $cff = $project->activities->sum(
                                            fn($a) => $a->allocations->where('year', $start)->sum('amount'),
                                        );
                                        $total = $project->activities->sum(fn($a) => $a->allocations->sum('amount'));
                                    @endphp

                                    <tr class="excel-project-row child-row program-{{ $program->id }} expand-toggle"
                                        data-target="project-{{ $project->id }}" style="display: none;">
                                        <td class="fw-bold text-center">{{ $project->project_id }}</td>
                                        <td class="fw-bold">📂 {{ $project->name }}</td>
                                    <td class="fw-bold text-end">{{ number_format($budget, 2) }}</td>
                                        <td class="fw-bold text-end">{{ number_format($cff, 2) }}</td>

                                        @for ($i = 2; $i <= $maxYears; $i++)
                                            @php
                                                $yr = $start + ($i - 1);
                                                $v = $project->activities->sum(
                                                    fn($a) => $a->allocations->where('year', $yr)->sum('amount'),
                                                );
                                                $programYearTotals[$i] += $v;
                                            @endphp
                                            <td class="text-end">{{ number_format($v, 2) }}</td>
                                        @endfor

                                        <td class="fw-bold text-end">{{ number_format($total, 2) }}</td>
                                        <td class="toggle-icon text-center">+</td>
                                    </tr>

                                <!-- ===== ACTIVITIES LOOP ===== -->
                                @php $activityIndex = 1; @endphp
                                @foreach ($project->activities as $activity)
                                    @php
                                        $aBudget = $activity->totalAllocation();
                                        $aCFF = $activity->allocations->where('year', $start)->sum('amount');
                                        $activityDisplayId = $project->project_id . '-' . str_pad($activityIndex, 2, '0', STR_PAD_LEFT);
                                    @endphp

                                    <tr class="excel-activity-row child-row project-{{ $project->id }} expand-toggle"
                                        data-target="activity-{{ $activity->id }}" style="display: none;">
                                        <td class="text-center fw-bold">{{ $activityDisplayId }}</td>
                                        <td class="fw-semibold ps-4">🎯 {{ $activity->name }}</td>
                                        <td class="text-end fw-bold">{{ number_format($aBudget, 2) }}</td>
                                        <td class="text-end">{{ number_format($aCFF, 2) }}</td>

                                            @for ($i = 2; $i <= $maxYears; $i++)
                                                @php $yr = $start + ($i - 1); @endphp
                                                <td class="text-end">
                                                    {{ number_format($activity->allocations->where('year', $yr)->sum('amount'), 2) }}
                                                </td>
                                            @endfor

                                        <td class="fw-bold text-end">{{ number_format($aBudget, 2) }}</td>
                                        <td class="toggle-icon text-center">+</td>
                                    </tr>


                                    <!-- ===== SUB-ACTIVITIES LOOP ===== -->
                                    @php $subIndex = 1; @endphp
                                    @foreach ($activity->subActivities as $sub)
                                        @php
                                            $sTotal = $sub->allocations->sum('amount');
                                            $sCFF = $sub->allocations->where('year', $start)->sum('amount');
                                            $subDisplayId = $activityDisplayId . '-' . str_pad($subIndex, 2, '0', STR_PAD_LEFT);
                                        @endphp

                                        <tr class="excel-sub-row child-row activity-{{ $activity->id }}"
                                            style="display: none;">
                                            <td class="text-center">{{ $subDisplayId }}</td>
                                            <td class="ps-5">• {{ $sub->name }}</td>
                                            <td class="text-end fw-bold">{{ number_format($sTotal, 2) }}</td>
                                            <td class="text-end">{{ number_format($sCFF, 2) }}</td>

                                                @for ($i = 2; $i <= $maxYears; $i++)
                                                    @php $yr = $start + ($i - 1); @endphp
                                                    <td class="text-end">
                                                        {{ number_format($sub->allocations->where('year', $yr)->sum('amount'), 2) }}
                                                    </td>
                                                @endfor

                                            <td class="fw-bold text-end">{{ number_format($sTotal, 2) }}</td>
                                            <td></td>
                                        </tr>
                                        @php $subIndex++; @endphp
                                    @endforeach
                                    @php $activityIndex++; @endphp
                                @endforeach
                            @endforeach

                        </tbody>

                        </table>
                    </div>

                </div>
            </div>
        @endforeach
        <style>
            .sticky-header {
                position: sticky;
                top: 0;
                z-index: 20;
            }

            /* Zebra striping */
            .excel-table tbody tr:nth-child(odd) {
                background: #fafafa !important;
            }

            .excel-program-row {
                background: #d9ead3 !important;
                font-size: 15px;
            }

            .excel-project-row {
                background: #cfe2f3 !important;
                font-weight: bold;
            }

            .excel-activity-row {
                background: #f6f6f6 !important;
                font-weight: 600;
            }

            .excel-sub-row {
                background: #ffffff !important;
            }

            .expand-toggle {
                cursor: pointer;
            }

            .toggle-icon {
                font-size: 18px;
                font-weight: bold;
                cursor: pointer;
                user-select: none;
            }
        </style>


        <style>
            .excel-program-row {
                background: #d9ead3 !important;
                font-size: 15px;
            }

            .excel-project-row {
                background: #cfe2f3 !important;
                font-weight: bold;
            }

            .excel-activity-row {
                background: #f9f9f9 !important;
                font-weight: 600;
            }

            .excel-sub-row {
                background: #ffffff !important;
            }

            .excel-table td,
            .excel-table th {
                padding: 6px 10px !important;
                font-size: 13px;
            }
        </style>




    </main>

    <!-- CHART LIBRARY -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            // Expand / collapse rows
            document.querySelectorAll(".expand-toggle").forEach(row => {
                row.addEventListener("click", function() {
                    const target = this.getAttribute("data-target");
                    const icon = this.querySelector(".toggle-icon");
                    if (!target || !icon) return;
                    const children = document.querySelectorAll("." + target);
                    const isOpen = icon.textContent === "-";
                    children.forEach(child => child.style.display = isOpen ? "none" : "table-row");
                    icon.textContent = isOpen ? "+" : "-";
                });
            });

            @php
                $projectChartData = $projectRankings->map(function ($item) {
                    $p = $item['project'];
                    $years = range($p->start_year, $p->end_year);

                    return [
                        'id' => $p->id,
                        'chartId' => 'projectLineChart_' . $p->id,
                        'subChartId' => 'subLineChart_' . $p->id,
                        'activitySelectId' => 'activitySelect_' . $p->id,
                        'years' => $years,
                        'activities' => $p->activities->map(function ($a) use ($years) {
                            $activitySeries = array_map(function ($y) use ($a) {
                                return (float) $a->allocations->where('year', $y)->sum('amount');
                            }, $years);

                            $subSeries = $a->subActivities->map(function ($s) use ($years) {
                                $series = array_map(function ($y) use ($s) {
                                    return (float) $s->allocations->where('year', $y)->sum('amount');
                                }, $years);

                                return [
                                    'id' => $s->id,
                                    'name' => $s->name,
                                    'series' => $series,
                                ];
                            })->values()->all();

                            return [
                                'id' => $a->id,
                                'name' => $a->name,
                                'series' => $activitySeries,
                                'subs' => $subSeries,
                            ];
                        })->values()->all(),
                    ];
                })->values()->toJson();
            @endphp
            const projectChartData = {!! $projectChartData !!};

            // Chart registry + exporters
            const lineCharts = {};
            window.registerLineChart = (id, chart) => lineCharts[id] = chart;
            window.downloadChartPNG = (id) => {
                const c = lineCharts[id];
                if (!c) return;
                c.dataURI().then(({ imgURI }) => {
                    const link = document.createElement("a");
                    link.href = imgURI;
                    link.download = id + ".png";
                    link.click();
                });
            };
            window.downloadChartSVG = (id) => {
                const c = lineCharts[id];
                if (!c) return;
                c.dataURI().then(({ svgURI }) => {
                    const link = document.createElement("a");
                    link.href = svgURI;
                    link.download = id + ".svg";
                    link.click();
                });
            };

            // Build charts
            projectChartData.forEach(p => {
                const years = p.years;

                // Activity chart
                const activitySeries = p.activities.map(a => ({
                    name: a.name,
                    data: a.series
                }));

                const activityChart = new ApexCharts(
                    document.querySelector(`#${p.chartId}`),
                    {
                        chart: { type: 'line', height: 320, toolbar: { show: false } },
                        stroke: { width: 3, curve: 'smooth' },
                        series: activitySeries,
                        xaxis: { categories: years, title: { text: 'Year' } },
                        yaxis: { title: { text: 'Allocated Amount' }, labels: { formatter: v => v.toLocaleString() } },
                        colors: ['#0d6efd', '#6610f2', '#198754', '#dc3545', '#fd7e14', '#20c997', '#6f42c1', '#ffc107'],
                        markers: { size: 4 },
                        legend: { position: 'top' },
                        noData: { text: 'No activity data' }
                    }
                );
                activityChart.render();
                registerLineChart(p.chartId, activityChart);

                // Sub-activity chart, default first activity
                const select = document.querySelector(`#${p.activitySelectId}`);
                const firstActivityId = select?.value || (p.activities[0]?.id ?? null);
                const subSeriesMap = {};
                p.activities.forEach(a => {
                    subSeriesMap[a.id] = a.subs.map(sub => ({
                        name: sub.name,
                        data: sub.series
                    }));
                });

                const subChart = new ApexCharts(
                    document.querySelector(`#${p.subChartId}`),
                    {
                        chart: { type: 'line', height: 260, toolbar: { show: false } },
                        stroke: { width: 3, curve: 'smooth' },
                        series: subSeriesMap[firstActivityId] || [],
                        xaxis: { categories: years, title: { text: 'Year' } },
                        yaxis: { title: { text: 'Allocated Amount' }, labels: { formatter: v => v.toLocaleString() } },
                        colors: ['#0d6efd', '#6610f2', '#198754', '#dc3545', '#fd7e14', '#20c997', '#6f42c1', '#ffc107'],
                        markers: { size: 4 },
                        legend: { position: 'top' },
                        noData: { text: 'No sub-activity data for this activity' }
                    }
                );
                subChart.render();
                registerLineChart(p.subChartId, subChart);

                if (select) {
                    select.addEventListener('change', (e) => {
                        const actId = e.target.value;
                        subChart.updateSeries(subSeriesMap[actId] || []);
                    });
                }
            });

            // Project allocation bar chart (overall)
            const allocContainer = document.querySelector("#projectAllocChart");
            if (allocContainer) {
                const projectNames = @json($projectRankings->pluck('project.name'));
                const projectValues = @json($projectRankings->pluck('allocated'));
                new ApexCharts(allocContainer, {
                    chart: { type: 'bar', height: 350 },
                    series: [{ name: 'Allocated', data: projectValues }],
                    xaxis: { categories: projectNames },
                    colors: ['#0d6efd']
                }).render();
            }
        });
    </script>

@endsection
