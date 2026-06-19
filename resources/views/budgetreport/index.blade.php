@extends('layouts.app')

@section('content')
    @php
        $money = fn ($value) => number_format((float) $value, 2);
        $totalBudget = max((float) ($portfolioStats['total_budget'] ?? 0), 1);
        $topSector = collect($sectorSummaries)->sortByDesc('total_budget')->first();
    @endphp

    <style>
        .budget-report-page {
            color: #111827;
        }

        .br-hero {
            background: linear-gradient(120deg, #0f172a 0%, #0f766e 46%, #2563eb 100%);
            border-radius: 8px;
            color: #f8fafc;
            overflow: hidden;
            position: relative;
        }

        .br-hero::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(255, 255, 255, .14), transparent 50%);
            pointer-events: none;
        }

        .br-hero-body {
            position: relative;
            z-index: 1;
            padding: 28px;
        }

        .br-panel,
        .br-metric,
        .br-sector-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }

        .br-metric,
        .br-sector-card {
            padding: 18px;
            height: 100%;
            transition: transform .16s ease, box-shadow .16s ease;
        }

        .br-metric:hover,
        .br-sector-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(15, 23, 42, .11);
        }

        .br-metric-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eef2ff;
            color: #1d4ed8;
            font-size: 1.1rem;
        }

        .br-panel-header {
            padding: 18px 20px;
            border-bottom: 1px solid #e5e7eb;
            background: #f8fafc;
        }

        .br-panel-body {
            padding: 20px;
        }

        .br-chart-box {
            position: relative;
            min-height: 320px;
        }

        .br-chart-box.sm {
            min-height: 275px;
        }

        .br-chart-box canvas {
            width: 100% !important;
            height: 100% !important;
        }

        .br-progress {
            height: 8px;
            background: #e5e7eb;
            border-radius: 999px;
            overflow: hidden;
        }

        .br-progress span {
            display: block;
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #0f766e, #2563eb);
        }

        .br-table-wrap {
            max-height: 520px;
            overflow: auto;
        }

        .br-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #dbeafe;
            background: #eff6ff;
            color: #1e40af;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: .78rem;
            font-weight: 600;
        }

        .br-tabs {
            border-bottom: 1px solid #dbe3ef;
        }

        .br-tabs .nav-link {
            border: 0;
            border-bottom: 3px solid transparent;
            color: #64748b;
            font-weight: 800;
            padding: 0.85rem 1rem;
        }

        .br-tabs .nav-link.active {
            color: #0f766e;
            background: transparent;
            border-bottom-color: #0f766e;
        }

        .br-project-selector {
            display: grid;
            grid-template-columns: minmax(260px, 1fr) auto;
            gap: 0.75rem;
            align-items: end;
        }

        .br-pdf-btn {
            background: linear-gradient(135deg, #fbbf24, #f97316);
            border: 0;
            color: #111827;
            font-weight: 800;
            box-shadow: 0 12px 22px rgba(15, 23, 42, .2);
            transition: transform .16s ease, box-shadow .16s ease;
        }

        .br-pdf-btn:hover,
        .br-pdf-btn:focus {
            color: #111827;
            box-shadow: 0 16px 28px rgba(15, 23, 42, .28);
            transform: translateY(-2px);
        }

        .br-insight {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #ffffff;
            padding: 18px;
            height: 100%;
            border-left: 4px solid #0f766e;
        }

        .br-insight-label {
            color: #64748b;
            font-size: .76rem;
            text-transform: uppercase;
            font-weight: 700;
        }

        .br-chart-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .br-chart-title span {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #e0f2fe;
            color: #0369a1;
        }

        @media (max-width: 767.98px) {
            .br-hero-body,
            .br-panel-body {
                padding: 18px;
            }

            .br-chart-box {
                min-height: 260px;
            }
        }
    </style>

    <div class="nxl-container budget-report-page">
        <div class="br-hero mb-4">
            <div class="br-hero-body d-flex flex-column flex-xl-row justify-content-between gap-3">
                <div>
                    <div class="text-uppercase small fw-semibold text-white-50 mb-2">Budget Reports</div>
                    <h3 class="fw-bold mb-2 text-white">Portfolio Budget Overview</h3>
                    <p class="mb-0 text-white-50">
                        Sector funding, program concentration, project ranking, and annual allocation movement.
                    </p>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-start">
                    <a href="{{ route('budget.reports.portfolio.export.pdf') }}" class="btn br-pdf-btn">
                        <i class="feather-download me-1"></i> Download PDF
                    </a>
                    <a href="{{ route('budget.reports.commitments') }}" class="btn btn-light">
                        <i class="feather-file-text me-1"></i> Commitment Report
                    </a>
                    <a href="{{ route('budget.reports.commitment-disbursement') }}" class="btn btn-outline-light">
                        <i class="feather-repeat me-1"></i> Commitment & Disbursement
                    </a>
                    <a href="{{ route('budget.reports.ifr') }}" class="btn btn-outline-light">
                        <i class="feather-activity me-1"></i> IFR Report
                    </a>
                    <a href="{{ route('budget.reports.project-financial-position') }}" class="btn btn-outline-light">
                        <i class="feather-trending-up me-1"></i> Financial Position
                    </a>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs br-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link {{ $activeReportTab === 'portfolio' ? 'active' : '' }}" href="{{ route('budget.reports.index') }}">
                    <i class="feather-pie-chart me-1"></i> Portfolio Overview
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $activeReportTab === 'project-progress' ? 'active' : '' }}" href="{{ route('budget.reports.index', ['tab' => 'project-progress', 'project_id' => $selectedProjectId]) }}">
                    <i class="feather-trending-up me-1"></i> Project Progress
                </a>
            </li>
        </ul>

        @if ($activeReportTab === 'portfolio')
        <div class="row g-3 mb-4">
            <div class="col-md-6 col-xl-3">
                <div class="br-metric">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="text-muted small">Total Budget</div>
                            <div class="h4 fw-bold mb-0">{{ $money($portfolioStats['total_budget']) }}</div>
                        </div>
                        <span class="br-metric-icon"><i class="feather-dollar-sign"></i></span>
                    </div>
                    <div class="small text-muted mt-3">Across {{ number_format($portfolioStats['funded_sectors']) }} funded sectors</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="br-metric">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="text-muted small">Programs</div>
                            <div class="h4 fw-bold mb-0">{{ number_format($portfolioStats['programs']) }}</div>
                        </div>
                        <span class="br-metric-icon"><i class="feather-layers"></i></span>
                    </div>
                    <div class="small text-muted mt-3">{{ number_format($portfolioStats['projects']) }} projects in the portfolio</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="br-metric">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="text-muted small">Activities</div>
                            <div class="h4 fw-bold mb-0">{{ number_format($portfolioStats['activities']) }}</div>
                        </div>
                        <span class="br-metric-icon"><i class="feather-list"></i></span>
                    </div>
                    <div class="small text-muted mt-3">Linked under funded projects</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="br-metric">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="text-muted small">Avg. Project Budget</div>
                            <div class="h4 fw-bold mb-0">{{ $money($portfolioStats['average_project_budget']) }}</div>
                        </div>
                        <span class="br-metric-icon"><i class="feather-bar-chart-2"></i></span>
                    </div>
                    <div class="small text-muted mt-3">Top sector: {{ $topSector['name'] ?? 'N/A' }}</div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-4">
                <div class="br-insight">
                    <div class="br-insight-label mb-1">Largest Sector</div>
                    <div class="h5 fw-bold mb-1">{{ $portfolioStats['largest_sector'] ?? 'N/A' }}</div>
                    <div class="text-muted small">{{ number_format($portfolioStats['largest_sector_share'], 1) }}% of total budget allocation</div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="br-insight" style="border-left-color:#2563eb;">
                    <div class="br-insight-label mb-1">Largest Program</div>
                    <div class="h5 fw-bold mb-1">{{ $portfolioStats['largest_program'] ?? 'N/A' }}</div>
                    <div class="text-muted small">{{ number_format($portfolioStats['largest_program_share'], 1) }}% of total budget allocation</div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="br-insight" style="border-left-color:#ea580c;">
                    <div class="br-insight-label mb-1">Portfolio Density</div>
                    <div class="h5 fw-bold mb-1">{{ number_format($portfolioStats['projects']) }} projects</div>
                    <div class="text-muted small">{{ number_format($portfolioStats['activities']) }} activities mapped across {{ number_format($portfolioStats['programs']) }} programs</div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            @foreach ($sectorSummaries as $sector)
                @php
                    $share = $portfolioStats['total_budget'] > 0 ? (($sector['total_budget'] / $portfolioStats['total_budget']) * 100) : 0;
                @endphp
                <div class="col-md-6 col-xl-3">
                    <div class="br-sector-card">
                        <div class="d-flex justify-content-between gap-2 mb-3">
                            <div>
                                <div class="fw-bold">{{ $sector['name'] }}</div>
                                <div class="text-muted small">{{ number_format($sector['programs']) }} programs, {{ number_format($sector['projects']) }} projects</div>
                            </div>
                            <span class="br-chip">{{ number_format($share, 1) }}%</span>
                        </div>
                        <div class="h5 fw-bold mb-2">{{ $money($sector['total_budget']) }}</div>
                        <div class="br-progress"><span style="width: {{ min(100, $share) }}%;"></span></div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="br-panel h-100">
                    <div class="br-panel-header">
                        <div class="br-chart-title mb-1">
                            <span><i class="feather-bar-chart-2"></i></span>
                            <h5 class="fw-bold mb-0">Sector Allocation Ranking</h5>
                        </div>
                        <div class="text-muted small">Total budget allocation by sector</div>
                    </div>
                    <div class="br-panel-body">
                        <div class="br-chart-box">
                            <canvas id="sectorAllocationChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="br-panel h-100">
                    <div class="br-panel-header">
                        <div class="br-chart-title mb-1">
                            <span><i class="feather-pie-chart"></i></span>
                            <h5 class="fw-bold mb-0">Budget Share</h5>
                        </div>
                        <div class="text-muted small">Sector percentage of total allocation</div>
                    </div>
                    <div class="br-panel-body">
                        <div class="br-chart-box">
                            <canvas id="sectorShareChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="br-panel h-100">
                    <div class="br-panel-header">
                        <div class="br-chart-title mb-1">
                            <span><i class="feather-trending-up"></i></span>
                            <h5 class="fw-bold mb-0">Annual Allocation Trend</h5>
                        </div>
                        <div class="text-muted small">Project allocation totals by year</div>
                    </div>
                    <div class="br-panel-body">
                        <div class="br-chart-box sm">
                            <canvas id="annualTrendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="br-panel h-100">
                    <div class="br-panel-header">
                        <div class="br-chart-title mb-1">
                            <span><i class="feather-grid"></i></span>
                            <h5 class="fw-bold mb-0">Portfolio Structure</h5>
                        </div>
                        <div class="text-muted small">Programs, projects, and activities by sector</div>
                    </div>
                    <div class="br-panel-body">
                        <div class="br-chart-box sm">
                            <canvas id="portfolioStructureChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-7">
                <div class="br-panel h-100">
                    <div class="br-panel-header">
                        <div class="br-chart-title mb-1">
                            <span><i class="feather-award"></i></span>
                            <h5 class="fw-bold mb-0">Top Funded Programs</h5>
                        </div>
                        <div class="text-muted small">Largest program envelopes by project allocation</div>
                    </div>
                    <div class="br-panel-body">
                        <div class="br-chart-box">
                            <canvas id="topProgramsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="br-panel h-100">
                    <div class="br-panel-header">
                        <div class="br-chart-title mb-1">
                            <span><i class="feather-disc"></i></span>
                            <h5 class="fw-bold mb-0">Project Footprint</h5>
                        </div>
                        <div class="text-muted small">Activities compared with project budget size</div>
                    </div>
                    <div class="br-panel-body">
                        <div class="br-chart-box">
                            <canvas id="projectFootprintChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-7">
                <div class="br-panel h-100">
                    <div class="br-panel-header">
                        <div class="br-chart-title mb-1">
                            <span><i class="feather-briefcase"></i></span>
                            <h5 class="fw-bold mb-0">Top Funded Projects</h5>
                        </div>
                        <div class="text-muted small">Largest individual project allocations</div>
                    </div>
                    <div class="br-panel-body">
                        <div class="br-chart-box">
                            <canvas id="topProjectsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="br-panel h-100">
                    <div class="br-panel-header">
                        <div class="br-chart-title mb-1">
                            <span><i class="feather-target"></i></span>
                            <h5 class="fw-bold mb-0">Project Budget Bands</h5>
                        </div>
                        <div class="text-muted small">Project count grouped by budget size</div>
                    </div>
                    <div class="br-panel-body">
                        <div class="br-chart-box">
                            <canvas id="projectBandsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="br-panel h-100">
                    <div class="br-panel-header">
                        <div class="br-chart-title mb-1">
                            <span><i class="feather-maximize-2"></i></span>
                            <h5 class="fw-bold mb-0">Average Project Size</h5>
                        </div>
                        <div class="text-muted small">Average project allocation by sector</div>
                    </div>
                    <div class="br-panel-body">
                        <div class="br-chart-box sm">
                            <canvas id="averageProjectChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="br-panel h-100">
                    <div class="br-panel-header">
                        <div class="br-chart-title mb-1">
                            <span><i class="feather-compass"></i></span>
                            <h5 class="fw-bold mb-0">Sector Operating Shape</h5>
                        </div>
                        <div class="text-muted small">Relative spread of programs, projects, and activities</div>
                    </div>
                    <div class="br-panel-body">
                        <div class="br-chart-box sm">
                            <canvas id="sectorShapeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="br-panel mt-4">
            <div class="br-panel-header d-flex flex-column flex-lg-row justify-content-between gap-2">
                <div>
                    <h5 class="fw-bold mb-1">Sector Breakdown</h5>
                    <div class="text-muted small">Financial summary and operating structure by sector</div>
                </div>
                <span class="br-chip">{{ number_format($sectorSummaries->count()) }} sectors</span>
            </div>
            <div class="br-panel-body">
                <div class="table-responsive br-table-wrap">
                    <table class="table align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Sector</th>
                                <th class="text-center">Programs</th>
                                <th class="text-center">Projects</th>
                                <th class="text-center">Activities</th>
                                <th class="text-end">Total Budget</th>
                                <th style="min-width: 190px;">Share</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($sectorSummaries as $sector)
                                @php
                                    $share = $portfolioStats['total_budget'] > 0 ? (($sector['total_budget'] / $portfolioStats['total_budget']) * 100) : 0;
                                    $sectorModel = $sectors->firstWhere('id', $sector['id']);
                                    $firstProgram = $sectorModel?->programs?->first();
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $sector['name'] }}</div>
                                        <div class="small text-muted">Average project budget: {{ $money($sector['average_project_budget']) }}</div>
                                    </td>
                                    <td class="text-center">{{ number_format($sector['programs']) }}</td>
                                    <td class="text-center">{{ number_format($sector['projects']) }}</td>
                                    <td class="text-center">{{ number_format($sector['activities']) }}</td>
                                    <td class="text-end fw-semibold">{{ $money($sector['total_budget']) }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="br-progress flex-grow-1"><span style="width: {{ min(100, $share) }}%;"></span></div>
                                            <span class="small fw-semibold">{{ number_format($share, 1) }}%</span>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        @if ($firstProgram)
                                            <a href="{{ route('budget.reports.program', $firstProgram) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="feather-eye me-1"></i> Open
                                            </a>
                                        @else
                                            <span class="text-muted small">No program</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No budget sectors found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @else
            <div class="br-panel mb-4">
                <div class="br-panel-header">
                    <div class="br-chart-title mb-1">
                        <span><i class="feather-search"></i></span>
                        <h5 class="fw-bold mb-0">Project Selection</h5>
                    </div>
                    <div class="text-muted small">Review progress for a single project.</div>
                </div>
                <div class="br-panel-body">
                    <form method="GET" action="{{ route('budget.reports.index') }}" class="br-project-selector">
                        <input type="hidden" name="tab" value="project-progress">
                        <div>
                            <label for="projectProgressSelect" class="form-label fw-semibold">Project</label>
                            <select id="projectProgressSelect" name="project_id" class="form-select">
                                @foreach ($projectOptions as $projectOption)
                                    <option value="{{ $projectOption['id'] }}" @selected((string) $selectedProjectId === (string) $projectOption['id'])>
                                        {{ $projectOption['name'] }} - {{ $projectOption['program'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather-filter me-1"></i> View Progress
                        </button>
                    </form>
                </div>
            </div>

            @if (! $projectProgress)
                <div class="br-panel">
                    <div class="br-panel-body text-center text-muted py-5">
                        No project is available for progress review.
                    </div>
                </div>
            @else
                @php
                    $projectCurrency = $projectProgress['project']['currency'] ?? 'USD';
                    $projectMoney = fn ($value) => $projectCurrency . ' ' . number_format((float) $value, 2);
                    $projectSummary = $projectProgress['summary'];
                @endphp

                <div class="br-panel mb-4">
                    <div class="br-panel-header d-flex flex-column flex-lg-row justify-content-between gap-2">
                        <div>
                            <h5 class="fw-bold mb-1">{{ $projectProgress['project']['name'] }}</h5>
                            <div class="text-muted small">
                                {{ $projectProgress['project']['code'] ?: 'No project code' }}
                                @if ($projectProgress['project']['program'])
                                    | {{ $projectProgress['project']['program'] }}
                                @endif
                            </div>
                        </div>
                        <span class="br-chip">
                            {{ $projectProgress['project']['start_year'] ?: 'N/A' }} - {{ $projectProgress['project']['end_year'] ?: 'N/A' }}
                        </span>
                    </div>
                    <div class="br-panel-body">
                        <div class="row g-3">
                            <div class="col-md-6 col-xl-3">
                                <div class="br-metric">
                                    <div class="text-muted small">Project Allocation</div>
                                    <div class="h4 fw-bold mb-0">{{ $projectMoney($projectSummary['project_budget']) }}</div>
                                    <div class="small text-muted mt-3">{{ number_format($projectSummary['activity_count']) }} activities</div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-3">
                                <div class="br-metric">
                                    <div class="text-muted small">Activity Allocation</div>
                                    <div class="h4 fw-bold mb-0">{{ $projectMoney($projectSummary['activity_budget']) }}</div>
                                    <div class="small text-muted mt-3">{{ number_format($projectSummary['activity_progress'], 1) }}% of project allocation</div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-3">
                                <div class="br-metric">
                                    <div class="text-muted small">Sub-Activity Allocation</div>
                                    <div class="h4 fw-bold mb-0">{{ $projectMoney($projectSummary['sub_activity_budget']) }}</div>
                                    <div class="small text-muted mt-3">{{ number_format($projectSummary['sub_activity_count']) }} sub-activities</div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-3">
                                <div class="br-metric">
                                    <div class="text-muted small">Remaining to Activities</div>
                                    <div class="h4 fw-bold mb-0">{{ $projectMoney($projectSummary['remaining_to_activities']) }}</div>
                                    <div class="small text-muted mt-3">{{ number_format($projectSummary['sub_activity_progress'], 1) }}% sub-activity coverage</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-xl-8">
                        <div class="br-panel h-100">
                            <div class="br-panel-header">
                                <div class="br-chart-title mb-1">
                                    <span><i class="feather-trending-up"></i></span>
                                    <h5 class="fw-bold mb-0">Project Allocation Progress</h5>
                                </div>
                                <div class="text-muted small">Project, activity, and sub-activity allocations by year.</div>
                            </div>
                            <div class="br-panel-body">
                                <div class="br-chart-box">
                                    <canvas id="selectedProjectProgressChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4">
                        <div class="br-panel h-100">
                            <div class="br-panel-header">
                                <div class="br-chart-title mb-1">
                                    <span><i class="feather-target"></i></span>
                                    <h5 class="fw-bold mb-0">Progress Ratios</h5>
                                </div>
                                <div class="text-muted small">Budget cascade from project to activities.</div>
                            </div>
                            <div class="br-panel-body">
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between small fw-semibold mb-2">
                                        <span>Activity progress</span>
                                        <span>{{ number_format($projectSummary['activity_progress'], 1) }}%</span>
                                    </div>
                                    <div class="br-progress"><span style="width: {{ min(100, $projectSummary['activity_progress']) }}%;"></span></div>
                                </div>
                                <div>
                                    <div class="d-flex justify-content-between small fw-semibold mb-2">
                                        <span>Sub-activity progress</span>
                                        <span>{{ number_format($projectSummary['sub_activity_progress'], 1) }}%</span>
                                    </div>
                                    <div class="br-progress"><span style="width: {{ min(100, $projectSummary['sub_activity_progress']) }}%;"></span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="br-panel">
                    <div class="br-panel-header d-flex flex-column flex-lg-row justify-content-between gap-2">
                        <div>
                            <h5 class="fw-bold mb-1">Activity Progress Breakdown</h5>
                            <div class="text-muted small">Activity allocation coverage and sub-activity mapping for the selected project.</div>
                        </div>
                        <span class="br-chip">{{ number_format($projectSummary['activity_count']) }} activities</span>
                    </div>
                    <div class="br-panel-body">
                        <div class="table-responsive br-table-wrap">
                            <table class="table align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Activity</th>
                                        <th class="text-center">Sub-Activities</th>
                                        <th class="text-end">Activity Allocation</th>
                                        <th class="text-end">Sub-Activity Allocation</th>
                                        <th style="min-width: 190px;">Progress</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($projectProgress['activity_rows'] as $activityRow)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $activityRow['name'] }}</div>
                                                <div class="small text-muted">{{ number_format($activityRow['activity_share'], 1) }}% of project allocation</div>
                                            </td>
                                            <td class="text-center">{{ number_format($activityRow['sub_activities']) }}</td>
                                            <td class="text-end fw-semibold">{{ $projectMoney($activityRow['activity_budget']) }}</td>
                                            <td class="text-end">{{ $projectMoney($activityRow['sub_activity_budget']) }}</td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="br-progress flex-grow-1"><span style="width: {{ min(100, $activityRow['sub_activity_progress']) }}%;"></span></div>
                                                    <span class="small fw-semibold">{{ number_format($activityRow['sub_activity_progress'], 1) }}%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">No activities found for this project.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.Chart) return;

            const charts = @json($chartData);
            const projectProgressChart = @json($projectProgress['chart'] ?? null);
            const palette = ['#0f766e', '#2563eb', '#7c3aed', '#ea580c', '#db2777', '#0891b2', '#65a30d', '#334155', '#dc2626', '#4f46e5'];
            const money = (value) => Number(value || 0).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            Chart.defaults.font.family = "'Inter', 'Segoe UI', sans-serif";
            Chart.defaults.color = '#475569';
            Chart.defaults.plugins.tooltip.backgroundColor = '#0f172a';
            Chart.defaults.plugins.tooltip.padding = 12;
            Chart.defaults.plugins.tooltip.cornerRadius = 8;

            const noData = (values) => !Array.isArray(values) || values.every((value) => Number(value || 0) === 0);
            const normalize = (values) => {
                const max = Math.max(...(values || []).map((value) => Number(value || 0)), 0);
                return (values || []).map((value) => max > 0 ? Math.round((Number(value || 0) / max) * 100) : 0);
            };
            const axisMoney = (value) => {
                const number = Number(value || 0);
                if (number >= 1000000) return `${(number / 1000000).toFixed(1)}M`;
                if (number >= 1000) return `${(number / 1000).toFixed(1)}K`;
                return number.toLocaleString();
            };

            const sectorCanvas = document.getElementById('sectorAllocationChart');
            if (sectorCanvas && !noData(charts.sectorTotals)) {
                const gradient = sectorCanvas.getContext('2d').createLinearGradient(0, 0, 0, 320);
                gradient.addColorStop(0, '#2563eb');
                gradient.addColorStop(1, '#0f766e');

                new Chart(sectorCanvas, {
                    type: 'bar',
                    data: {
                        labels: charts.sectorLabels,
                        datasets: [{
                            label: 'Budget',
                            data: charts.sectorTotals,
                            backgroundColor: gradient,
                            borderRadius: 6,
                            maxBarThickness: 44
                        }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: (ctx) => `Budget: ${money(ctx.raw)}` } }
                        },
                        scales: {
                            x: { grid: { display: false } },
                            y: {
                                beginAtZero: true,
                                ticks: { callback: axisMoney },
                                grid: { color: '#e5e7eb' }
                            }
                        }
                    }
                });
            }

            const shareCanvas = document.getElementById('sectorShareChart');
            if (shareCanvas && !noData(charts.sectorTotals)) {
                new Chart(shareCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: charts.sectorLabels,
                        datasets: [{
                            data: charts.sectorTotals,
                            backgroundColor: palette,
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

            const trendCanvas = document.getElementById('annualTrendChart');
            if (trendCanvas && !noData(charts.yearTotals)) {
                const trendGradient = trendCanvas.getContext('2d').createLinearGradient(0, 0, 0, 260);
                trendGradient.addColorStop(0, 'rgba(15, 118, 110, .24)');
                trendGradient.addColorStop(1, 'rgba(15, 118, 110, 0)');

                new Chart(trendCanvas, {
                    type: 'line',
                    data: {
                        labels: charts.yearLabels,
                        datasets: [{
                            label: 'Annual Allocation',
                            data: charts.yearTotals,
                            borderColor: '#0f766e',
                            backgroundColor: trendGradient,
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#0f766e',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            tension: .38,
                            fill: true
                        }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: (ctx) => `Allocation: ${money(ctx.raw)}` } }
                        },
                        scales: {
                            x: { grid: { display: false } },
                            y: { beginAtZero: true, ticks: { callback: axisMoney }, grid: { color: '#e5e7eb' } }
                        }
                    }
                });
            }

            const structureCanvas = document.getElementById('portfolioStructureChart');
            if (structureCanvas) {
                new Chart(structureCanvas, {
                    type: 'bar',
                    data: {
                        labels: charts.sectorLabels,
                        datasets: [
                            { label: 'Programs', data: charts.sectorPrograms, backgroundColor: '#2563eb', borderRadius: 5 },
                            { label: 'Projects', data: charts.sectorProjects, backgroundColor: '#0f766e', borderRadius: 5 },
                            { label: 'Activities', data: charts.sectorActivities, backgroundColor: '#ea580c', borderRadius: 5 }
                        ]
                    },
                    options: {
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } },
                        scales: {
                            x: { stacked: false, grid: { display: false } },
                            y: { beginAtZero: true, grid: { color: '#e5e7eb' } }
                        }
                    }
                });
            }

            const topProgramsCanvas = document.getElementById('topProgramsChart');
            if (topProgramsCanvas && !noData(charts.topProgramTotals)) {
                new Chart(topProgramsCanvas, {
                    type: 'bar',
                    data: {
                        labels: charts.topProgramLabels,
                        datasets: [{
                            label: 'Budget',
                            data: charts.topProgramTotals,
                            backgroundColor: palette,
                            borderRadius: 6,
                            maxBarThickness: 30
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: (ctx) => `Budget: ${money(ctx.raw)}` } }
                        },
                        scales: {
                            x: { beginAtZero: true, ticks: { callback: axisMoney }, grid: { color: '#e5e7eb' } },
                            y: { grid: { display: false } }
                        }
                    }
                });
            }

            const footprintCanvas = document.getElementById('projectFootprintChart');
            if (footprintCanvas && Array.isArray(charts.projectScatter) && charts.projectScatter.length) {
                new Chart(footprintCanvas, {
                    type: 'bubble',
                    data: {
                        datasets: [{
                            label: 'Projects',
                            data: charts.projectScatter,
                            backgroundColor: 'rgba(37, 99, 235, .24)',
                            borderColor: '#2563eb',
                            borderWidth: 1.5
                        }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        parsing: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    title: (items) => items[0]?.raw?.label || 'Project',
                                    label: (ctx) => [
                                        `Sector: ${ctx.raw.sector || 'N/A'}`,
                                        `Activities: ${ctx.raw.x}`,
                                        `Budget: ${money(ctx.raw.y)}`
                                    ]
                                }
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                title: { display: true, text: 'Activities' },
                                grid: { color: '#e5e7eb' }
                            },
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'Budget' },
                                ticks: { callback: axisMoney },
                                grid: { color: '#e5e7eb' }
                            }
                        }
                    }
                });
            }

            const topProjectsCanvas = document.getElementById('topProjectsChart');
            if (topProjectsCanvas && !noData(charts.topProjectTotals)) {
                new Chart(topProjectsCanvas, {
                    type: 'bar',
                    data: {
                        labels: charts.topProjectLabels,
                        datasets: [{
                            label: 'Project Budget',
                            data: charts.topProjectTotals,
                            backgroundColor: charts.topProjectTotals.map((_, index) => palette[index % palette.length]),
                            borderRadius: 6,
                            maxBarThickness: 28
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: (ctx) => `Budget: ${money(ctx.raw)}` } }
                        },
                        scales: {
                            x: { beginAtZero: true, ticks: { callback: axisMoney }, grid: { color: '#e5e7eb' } },
                            y: { grid: { display: false } }
                        }
                    }
                });
            }

            const projectBandsCanvas = document.getElementById('projectBandsChart');
            if (projectBandsCanvas && !noData(charts.projectBandCounts)) {
                new Chart(projectBandsCanvas, {
                    type: 'polarArea',
                    data: {
                        labels: charts.projectBandLabels,
                        datasets: [{
                            data: charts.projectBandCounts,
                            backgroundColor: ['rgba(15, 118, 110, .72)', 'rgba(37, 99, 235, .72)', 'rgba(234, 88, 12, .72)'],
                            borderColor: '#ffffff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                            tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${ctx.raw} projects` } }
                        },
                        scales: {
                            r: { grid: { color: '#e5e7eb' }, ticks: { precision: 0 } }
                        }
                    }
                });
            }

            const averageProjectCanvas = document.getElementById('averageProjectChart');
            if (averageProjectCanvas && !noData(charts.sectorAverageProjects)) {
                const avgGradient = averageProjectCanvas.getContext('2d').createLinearGradient(0, 0, 0, 260);
                avgGradient.addColorStop(0, 'rgba(124, 58, 237, .28)');
                avgGradient.addColorStop(1, 'rgba(124, 58, 237, 0)');

                new Chart(averageProjectCanvas, {
                    type: 'line',
                    data: {
                        labels: charts.sectorLabels,
                        datasets: [{
                            label: 'Average Project Budget',
                            data: charts.sectorAverageProjects,
                            borderColor: '#7c3aed',
                            backgroundColor: avgGradient,
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#7c3aed',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            tension: .35,
                            fill: true
                        }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: (ctx) => `Average: ${money(ctx.raw)}` } }
                        },
                        scales: {
                            x: { grid: { display: false } },
                            y: { beginAtZero: true, ticks: { callback: axisMoney }, grid: { color: '#e5e7eb' } }
                        }
                    }
                });
            }

            const shapeCanvas = document.getElementById('sectorShapeChart');
            if (shapeCanvas && charts.sectorLabels?.length) {
                new Chart(shapeCanvas, {
                    type: 'radar',
                    data: {
                        labels: charts.sectorLabels,
                        datasets: [
                            {
                                label: 'Programs',
                                data: normalize(charts.sectorPrograms),
                                borderColor: '#2563eb',
                                backgroundColor: 'rgba(37, 99, 235, .12)',
                                pointBackgroundColor: '#2563eb'
                            },
                            {
                                label: 'Projects',
                                data: normalize(charts.sectorProjects),
                                borderColor: '#0f766e',
                                backgroundColor: 'rgba(15, 118, 110, .12)',
                                pointBackgroundColor: '#0f766e'
                            },
                            {
                                label: 'Activities',
                                data: normalize(charts.sectorActivities),
                                borderColor: '#ea580c',
                                backgroundColor: 'rgba(234, 88, 12, .12)',
                                pointBackgroundColor: '#ea580c'
                            }
                        ]
                    },
                    options: {
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                            tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${ctx.raw}% relative strength` } }
                        },
                        scales: {
                            r: {
                                beginAtZero: true,
                                max: 100,
                                ticks: { display: false },
                                grid: { color: '#e5e7eb' },
                                angleLines: { color: '#e5e7eb' }
                            }
                        }
                    }
                });
            }

            const selectedProjectCanvas = document.getElementById('selectedProjectProgressChart');
            if (selectedProjectCanvas && projectProgressChart && projectProgressChart.labels?.length) {
                new Chart(selectedProjectCanvas, {
                    type: 'bar',
                    data: {
                        labels: projectProgressChart.labels,
                        datasets: [
                            { label: 'Project Allocation', data: projectProgressChart.project, backgroundColor: '#0f766e', borderRadius: 5 },
                            { label: 'Activity Allocation', data: projectProgressChart.activities, backgroundColor: '#2563eb', borderRadius: 5 },
                            { label: 'Sub-Activity Allocation', data: projectProgressChart.subActivities, backgroundColor: '#ea580c', borderRadius: 5 }
                        ]
                    },
                    options: {
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                            tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${money(ctx.raw)}` } }
                        },
                        scales: {
                            x: { grid: { display: false } },
                            y: { beginAtZero: true, ticks: { callback: axisMoney }, grid: { color: '#e5e7eb' } }
                        }
                    }
                });
            }
        });
    </script>
@endsection
