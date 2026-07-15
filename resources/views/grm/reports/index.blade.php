@extends('layouts.app')

@section('title', 'Grievance Redress Mechanism Reports')

@section('content')
    <style>
        .grm-report-hero {
            border-radius: 8px;
            padding: 22px;
            color: #fff;
            background: linear-gradient(135deg, #064e3b 0%, #0f766e 58%, #522b39 100%);
            box-shadow: 0 18px 36px rgba(6, 78, 59, .18);
        }
        .grm-report-hero h4,
        .grm-report-hero p { color: #fff; }
        .grm-report-grid { display: grid; gap: 14px; }
        .grm-filter-card,
        .grm-kpi-card,
        .grm-panel,
        .grm-attention-row {
            border: 1px solid #dbe5df;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .06);
        }
        .grm-filter-card { padding: 16px; }
        .grm-kpi-card { padding: 16px; min-height: 132px; }
        .grm-kpi-label {
            color: #64748b;
            font-size: .75rem;
            font-weight: 800;
            text-transform: uppercase;
        }
        .grm-kpi-value {
            display: block;
            color: #0f172a;
            font-size: 1.9rem;
            font-weight: 900;
            line-height: 1;
            margin: 10px 0 8px;
        }
        .grm-kpi-foot { color: #475569; font-size: .82rem; }
        .grm-kpi-accent {
            width: 40px;
            height: 4px;
            border-radius: 999px;
            background: #0f766e;
        }
        .grm-panel-header {
            padding: 15px 17px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            border-radius: 8px 8px 0 0;
        }
        .grm-panel-body { padding: 17px; }
        .grm-chart { min-height: 285px; }
        .grm-sla-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }
        .grm-sla-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px;
            background: #f8fafc;
        }
        .grm-sla-card span { color: #64748b; display: block; font-size: .78rem; font-weight: 800; text-transform: uppercase; }
        .grm-sla-card strong { color: #0f172a; display: block; font-size: 1.45rem; margin-top: 5px; }
        .grm-progress-track { height: 8px; border-radius: 999px; background: #e2e8f0; overflow: hidden; margin-top: 10px; }
        .grm-progress-fill { height: 100%; border-radius: 999px; background: #0f766e; }
        .grm-list { display: grid; gap: 10px; }
        .grm-list-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 12px;
            align-items: center;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            background: #f8fafc;
        }
        .grm-list-row strong { color: #0f172a; }
        .grm-list-row small { color: #64748b; }
        .grm-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: .78rem;
            font-weight: 800;
            background: #ecfdf5;
            color: #047857;
        }
        .grm-pill.is-danger { background: #fef2f2; color: #b91c1c; }
        .grm-pill.is-warn { background: #fffbeb; color: #92400e; }
        .grm-attention-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 12px;
            align-items: center;
            padding: 13px;
            text-decoration: none;
            color: #0f172a;
        }
        .grm-attention-row:hover { border-color: #0f766e; color: #064e3b; background: #ecfdf5; }
        @media (max-width: 991px) {
            .grm-sla-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 575px) {
            .grm-sla-grid { grid-template-columns: 1fr; }
            .grm-attention-row,
            .grm-list-row { grid-template-columns: 1fr; }
        }
    </style>

    @php
        $totalCases = max(1, (int) $totals['total']);
        $safePercent = fn ($value) => min(100, max(0, (float) $value));
    @endphp

    <div class="container-fluid">
        <div class="grm-report-hero mb-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <span class="badge bg-light text-success mb-2">Grievance Redress Mechanism Intelligence</span>
                    <h4 class="mb-1">Reports and Case Performance</h4>
                    <p class="mb-0">Monitor response discipline, resolution progress, anonymous submissions, evidence coverage, and program risk.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('grm.logs.index') }}" class="btn btn-light text-success fw-bold">
                        <i class="feather-clipboard me-1"></i> Case Logs
                    </a>
                    <a href="{{ route('grm.submissions.create') }}" class="btn btn-outline-light fw-bold">
                        <i class="feather-plus me-1"></i> New Case
                    </a>
                </div>
            </div>
        </div>

        <form method="GET" class="grm-filter-card mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-xl-3 col-lg-4">
                    <label class="form-label fw-semibold">Program</label>
                    <select name="program_id" class="form-select">
                        <option value="">All visible programs</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->id }}" @selected($selectedProgram === $program->id)>{{ $program->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-lg-4">
                    <label class="form-label fw-semibold">Level</label>
                    <select name="level_id" class="form-select">
                        <option value="">All levels</option>
                        @foreach ($levels as $level)
                            <option value="{{ $level->id }}" @selected($selectedLevel === $level->id)>{{ $level->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-lg-4">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-lg-4">
                    <label class="form-label fw-semibold">From</label>
                    <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control">
                </div>
                <div class="col-xl-2 col-lg-4">
                    <label class="form-label fw-semibold">To</label>
                    <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control">
                </div>
                <div class="col-xl-1 col-lg-4 d-flex gap-2">
                    <button class="btn btn-success flex-fill" title="Apply filters">
                        <i class="feather-filter"></i>
                    </button>
                    <a href="{{ route('grm.reports.index') }}" class="btn btn-outline-secondary" title="Clear filters">
                        <i class="feather-x"></i>
                    </a>
                </div>
            </div>
        </form>

        <div class="row g-3 mb-3">
            <div class="col-xl-3 col-md-6">
                <div class="grm-kpi-card">
                    <div class="grm-kpi-accent"></div>
                    <span class="grm-kpi-label">Total Cases</span>
                    <strong class="grm-kpi-value">{{ number_format($totals['total']) }}</strong>
                    <div class="grm-kpi-foot">{{ number_format($totals['with_documents']) }} with supporting documents</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="grm-kpi-card">
                    <div class="grm-kpi-accent" style="background:#2563eb"></div>
                    <span class="grm-kpi-label">Response Rate</span>
                    <strong class="grm-kpi-value">{{ number_format($rates['response'], 1) }}%</strong>
                    <div class="grm-progress-track"><div class="grm-progress-fill" style="width: {{ $safePercent($rates['response']) }}%; background:#2563eb"></div></div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="grm-kpi-card">
                    <div class="grm-kpi-accent" style="background:#f59e0b"></div>
                    <span class="grm-kpi-label">Unattended</span>
                    <strong class="grm-kpi-value">{{ number_format($totals['unattended']) }}</strong>
                    <div class="grm-kpi-foot">{{ number_format($totals['overdue_response']) }} overdue for response</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="grm-kpi-card">
                    <div class="grm-kpi-accent" style="background:#522b39"></div>
                    <span class="grm-kpi-label">Resolution Rate</span>
                    <strong class="grm-kpi-value">{{ number_format($rates['resolution'], 1) }}%</strong>
                    <div class="grm-progress-track"><div class="grm-progress-fill" style="width: {{ $safePercent($rates['resolution']) }}%; background:#522b39"></div></div>
                </div>
            </div>
        </div>

        <div class="grm-panel mb-3">
            <div class="grm-panel-header">
                <h6 class="fw-bold mb-0">Service-Level and Intake Quality</h6>
            </div>
            <div class="grm-panel-body">
                <div class="grm-sla-grid">
                    <div class="grm-sla-card">
                        <span>Avg Response Time</span>
                        <strong>{{ number_format($averages['response_hours'], 1) }}h</strong>
                    </div>
                    <div class="grm-sla-card">
                        <span>Avg Resolution Time</span>
                        <strong>{{ number_format($averages['resolution_hours'], 1) }}h</strong>
                    </div>
                    <div class="grm-sla-card">
                        <span>Anonymous Cases</span>
                        <strong>{{ number_format($rates['anonymous'], 1) }}%</strong>
                    </div>
                    <div class="grm-sla-card">
                        <span>Evidence Coverage</span>
                        <strong>{{ number_format($rates['documents'], 1) }}%</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-8">
                <div class="grm-panel h-100">
                    <div class="grm-panel-header">
                        <h6 class="fw-bold mb-0">Six-Month Case Movement</h6>
                    </div>
                    <div class="grm-panel-body">
                        <div id="grmTrendChart" class="grm-chart"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="grm-panel h-100">
                    <div class="grm-panel-header">
                        <h6 class="fw-bold mb-0">Response Health</h6>
                    </div>
                    <div class="grm-panel-body">
                        <div id="grmResponseHealthChart" class="grm-chart"></div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="grm-panel h-100">
                    <div class="grm-panel-header">
                        <h6 class="fw-bold mb-0">Status Distribution</h6>
                    </div>
                    <div class="grm-panel-body">
                        <div id="grmStatusChart" class="grm-chart"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="grm-panel h-100">
                    <div class="grm-panel-header">
                        <h6 class="fw-bold mb-0">Cases by Level</h6>
                    </div>
                    <div class="grm-panel-body">
                        <div id="grmLevelChart" class="grm-chart"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="grm-panel h-100">
                    <div class="grm-panel-header">
                        <h6 class="fw-bold mb-0">Officer Workload</h6>
                    </div>
                    <div class="grm-panel-body grm-list">
                        @forelse ($assigneeSeries as $item)
                            <div class="grm-list-row">
                                <div>
                                    <strong>{{ $item['label'] }}</strong>
                                    <div><small>{{ number_format($item['open']) }} open or unattended</small></div>
                                </div>
                                <span class="grm-pill {{ $item['open'] > 0 ? 'is-warn' : '' }}">{{ number_format($item['count']) }}</span>
                            </div>
                        @empty
                            <div class="text-muted">No workload data yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-xl-7">
                <div class="grm-panel h-100">
                    <div class="grm-panel-header">
                        <h6 class="fw-bold mb-0">Program Case Load</h6>
                    </div>
                    <div class="grm-panel-body">
                        <div id="grmProgramLoadChart" class="grm-chart"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="grm-panel h-100">
                    <div class="grm-panel-header d-flex justify-content-between align-items-center gap-2">
                        <h6 class="fw-bold mb-0">Attention Queue</h6>
                        <span class="grm-pill is-danger">{{ number_format($totals['overdue_response'] + $totals['overdue_resolution']) }} at risk</span>
                    </div>
                    <div class="grm-panel-body grm-list">
                        @forelse ($attentionCases as $case)
                            <a href="{{ route('grm.logs.show', $case) }}" class="grm-attention-row">
                                <div>
                                    <strong>{{ $case->case_number }}</strong>
                                    <div><small>{{ $case->program?->name ?? 'No program' }} | {{ $case->status_label }}</small></div>
                                    <div><small>Response due: {{ $case->due_response_at?->format('d M Y H:i') ?? 'N/A' }}</small></div>
                                </div>
                                <span class="grm-pill {{ $case->due_response_at?->isPast() ? 'is-danger' : 'is-warn' }}">
                                    {{ $case->due_response_at?->isPast() ? 'Overdue' : 'Open' }}
                                </span>
                            </a>
                        @empty
                            <div class="text-muted">No cases need immediate attention for the current filter.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.ApexCharts) {
                return;
            }

            const chartData = @json($chartData);
            const baseOptions = {
                chart: { toolbar: { show: false }, fontFamily: 'Inter, Arial, sans-serif' },
                dataLabels: { enabled: false },
                grid: { borderColor: '#e2e8f0' },
                legend: { position: 'bottom' },
                noData: { text: 'No data for selected filters' }
            };

            new ApexCharts(document.querySelector('#grmTrendChart'), {
                ...baseOptions,
                chart: { ...baseOptions.chart, type: 'area', height: 285 },
                colors: ['#0f766e', '#2563eb'],
                stroke: { curve: 'smooth', width: 3 },
                fill: { opacity: .18 },
                series: [
                    { name: 'Submitted', data: chartData.trend.submitted },
                    { name: 'Resolved', data: chartData.trend.resolved }
                ],
                xaxis: { categories: chartData.trend.labels }
            }).render();

            new ApexCharts(document.querySelector('#grmResponseHealthChart'), {
                ...baseOptions,
                chart: { ...baseOptions.chart, type: 'donut', height: 285 },
                colors: ['#16a34a', '#f59e0b', '#dc2626'],
                labels: chartData.responseHealth.labels,
                series: chartData.responseHealth.values
            }).render();

            new ApexCharts(document.querySelector('#grmStatusChart'), {
                ...baseOptions,
                chart: { ...baseOptions.chart, type: 'bar', height: 285 },
                colors: ['#0f766e'],
                series: [{ name: 'Cases', data: chartData.status.values }],
                xaxis: { categories: chartData.status.labels },
                plotOptions: { bar: { borderRadius: 5, columnWidth: '48%' } }
            }).render();

            new ApexCharts(document.querySelector('#grmLevelChart'), {
                ...baseOptions,
                chart: { ...baseOptions.chart, type: 'polarArea', height: 285 },
                colors: chartData.level.colors && chartData.level.colors.length ? chartData.level.colors : ['#0f766e', '#2563eb', '#f59e0b', '#522b39'],
                labels: chartData.level.labels,
                series: chartData.level.values
            }).render();

            new ApexCharts(document.querySelector('#grmProgramLoadChart'), {
                ...baseOptions,
                chart: { ...baseOptions.chart, type: 'bar', height: 285, stacked: false },
                colors: ['#0f766e', '#dc2626'],
                series: [
                    { name: 'Total cases', data: chartData.programLoad.cases },
                    { name: 'Unattended', data: chartData.programLoad.unattended }
                ],
                xaxis: { categories: chartData.programLoad.labels },
                plotOptions: { bar: { horizontal: true, borderRadius: 5 } }
            }).render();
        });
    </script>
@endpush
