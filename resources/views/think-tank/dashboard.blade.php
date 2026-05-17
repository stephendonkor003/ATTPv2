@php
    $currency = 'USD';
    $portalRouteParams = (auth()->user()?->isSuperAdmin() || auth()->user()?->isAdmin())
        ? ['think_tank_member_id' => $member->id]
        : [];
    $financePercent = min(100, max(0, (float) ($metrics['utilization'] ?? 0)));
@endphp

@push('styles')
    <style>
        .tt-dashboard-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(280px, .55fr);
            gap: 18px;
            margin-bottom: 18px;
        }

        .tt-briefing,
        .tt-deadline-card,
        .tt-panel,
        .tt-stat-card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
        }

        .tt-briefing {
            position: relative;
            overflow: hidden;
            min-height: 245px;
            background:
                linear-gradient(120deg, rgba(15, 23, 42, .96), rgba(14, 116, 144, .88)),
                url("{{ asset('admin/assets/images/gallery/1.png') }}");
            background-size: cover;
            background-position: center;
            color: #fff;
            padding: 26px;
        }

        .tt-briefing h1 {
            max-width: 760px;
            font-size: 30px;
            line-height: 1.15;
            margin: 10px 0;
            color: #fff;
        }

        .tt-briefing p {
            max-width: 780px;
            color: rgba(255, 255, 255, .86);
            margin: 0;
        }

        .tt-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(255, 255, 255, .28);
            background: rgba(255, 255, 255, .13);
            border-radius: 999px;
            padding: 7px 11px;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
        }

        .tt-hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 22px;
        }

        .tt-hero-actions .btn {
            border-radius: 7px;
            font-weight: 700;
        }

        .tt-deadline-card {
            padding: 22px;
            display: grid;
            align-content: space-between;
            gap: 16px;
            background: linear-gradient(180deg, #f8fafc, #ffffff);
        }

        .tt-deadline-number {
            font-size: 54px;
            line-height: 1;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: 0;
        }

        .tt-deadline-label {
            color: #64748b;
            font-weight: 700;
        }

        .tt-progress {
            height: 9px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
        }

        .tt-progress > span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #0891b2, #22c55e);
        }

        .tt-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .tt-filter-card {
            border: 1px solid #dbeafe;
            border-radius: 10px;
            background: #f8fbff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
            padding: 16px;
            margin-bottom: 18px;
        }

        .tt-filter-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 14px;
        }

        .tt-filter-head h2 {
            font-size: 16px;
            font-weight: 900;
            color: #0f172a;
            margin: 0;
        }

        .tt-filter-head p {
            color: #64748b;
            font-size: 13px;
            margin: 4px 0 0;
        }

        .tt-filter-active {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border-radius: 999px;
            background: #dbeafe;
            color: #1e40af;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 850;
            white-space: nowrap;
        }

        .tt-filter-grid {
            display: grid;
            grid-template-columns: 1fr .85fr 1fr 1fr auto;
            gap: 12px;
            align-items: end;
        }

        .tt-filter-field {
            display: grid;
            gap: 6px;
        }

        .tt-filter-field label {
            color: #334155;
            font-size: 12px;
            font-weight: 850;
        }

        .tt-filter-field input,
        .tt-filter-field select {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            background: #fff;
            color: #0f172a;
            padding: 10px 11px;
            min-height: 42px;
        }

        .tt-filter-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .tt-stat-card {
            padding: 18px;
            min-height: 120px;
        }

        .tt-stat-card .icon {
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            margin-bottom: 12px;
            color: #0f172a;
            background: #e0f2fe;
        }

        .tt-stat-card .value {
            font-size: 23px;
            font-weight: 900;
            color: #0f172a;
            line-height: 1.2;
        }

        .tt-stat-card .label {
            color: #64748b;
            font-size: 13px;
            font-weight: 700;
            margin-top: 5px;
        }

        .tt-layout-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(320px, .65fr);
            gap: 18px;
        }

        .tt-panel {
            padding: 20px;
            margin-bottom: 18px;
        }

        .tt-panel-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }

        .tt-panel h2 {
            font-size: 18px;
            font-weight: 900;
            margin: 0;
            color: #0f172a;
        }

        .tt-panel .hint {
            color: #64748b;
            margin: 4px 0 0;
            font-size: 13px;
        }

        .tt-chart-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .tt-chart-box {
            min-height: 275px;
            border: 1px solid #eef2f7;
            border-radius: 8px;
            padding: 14px;
            background: #fbfdff;
        }

        .tt-chart-box h3 {
            font-size: 14px;
            font-weight: 800;
            color: #334155;
            margin: 0 0 10px;
        }

        .tt-notification-list,
        .tt-funded-list {
            display: grid;
            gap: 12px;
        }

        .tt-note,
        .tt-funded-row {
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            padding: 14px;
            background: #fff;
        }

        .tt-note {
            display: grid;
            grid-template-columns: 38px minmax(0, 1fr);
            gap: 12px;
        }

        .tt-note-icon {
            width: 38px;
            height: 38px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #dbeafe;
            color: #1d4ed8;
        }

        .tt-note.urgent .tt-note-icon { background: #fee2e2; color: #b91c1c; }
        .tt-note.complete .tt-note-icon { background: #dcfce7; color: #15803d; }
        .tt-note.review .tt-note-icon { background: #fef3c7; color: #b45309; }
        .tt-note.procurement .tt-note-icon { background: #ccfbf1; color: #0f766e; }

        .tt-note-title,
        .tt-funded-title {
            font-weight: 850;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .tt-note-meta,
        .tt-funded-meta {
            color: #64748b;
            font-size: 13px;
            line-height: 1.45;
        }

        .tt-note-value {
            display: inline-flex;
            align-items: center;
            margin-top: 9px;
            padding: 4px 8px;
            border-radius: 999px;
            background: #f1f5f9;
            color: #0f172a;
            font-weight: 800;
            font-size: 12px;
        }

        .tt-funded-row {
            display: grid;
            gap: 10px;
        }

        .tt-funded-money {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            font-size: 12px;
            color: #64748b;
        }

        .tt-funded-money strong {
            display: block;
            color: #0f172a;
            font-size: 13px;
            margin-top: 2px;
        }

        .tt-table-wrap {
            overflow-x: auto;
        }

        .tt-dashboard-table th {
            background: #f8fafc;
            color: #475569;
        }

        @media (max-width: 1200px) {
            .tt-dashboard-hero,
            .tt-layout-grid {
                grid-template-columns: 1fr;
            }

            .tt-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .tt-filter-actions {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 900px) {
            .tt-stats-grid,
            .tt-chart-grid,
            .tt-funded-money,
            .tt-filter-grid {
                grid-template-columns: 1fr;
            }

            .tt-briefing h1 {
                font-size: 24px;
            }
        }
    </style>
@endpush

<x-think-tank.partials.shell :member="$member" title="Dashboard">
    <section class="tt-dashboard-hero">
        <div class="tt-briefing">
            <span class="tt-kicker"><i class="feather-activity"></i> Runtime Think Tank Dashboard</span>
            <h1>{{ $member->name }} operational overview</h1>
            <p>
                Track the funds disbursed by the ATTP Secretariat, monthly reporting obligations,
                procurement opportunities, research submissions, and activities visible to oversight teams.
            </p>
            <div class="tt-hero-actions">
                <a class="btn btn-light" href="{{ route('think-tank.reports', $portalRouteParams) }}">
                    <i class="feather-file-text me-1"></i> Submit Report
                </a>
                <a class="btn btn-outline-light" href="{{ route('think-tank.procurement', $portalRouteParams) }}">
                    <i class="feather-briefcase me-1"></i> Manage Procurement
                </a>
                <a class="btn btn-outline-light" href="{{ route('think-tank.research', $portalRouteParams) }}">
                    <i class="feather-book-open me-1"></i> Publish Research
                </a>
            </div>
        </div>

        <aside class="tt-deadline-card">
            <div>
                <div class="tt-deadline-label">Upcoming monthly report</div>
                <div class="tt-deadline-number">
                    @if($reportSubmittedThisPeriod)
                        OK
                    @elseif($monthlyReportDaysLeft >= 0)
                        {{ $monthlyReportDaysLeft }}
                    @else
                        {{ abs($monthlyReportDaysLeft) }}
                    @endif
                </div>
                <p class="text-muted mb-0">
                    @if($reportSubmittedThisPeriod)
                        Report received for {{ now()->format('F Y') }}.
                    @elseif($monthlyReportDaysLeft >= 0)
                        Days left to submit by {{ $monthlyReportDue->format('M d, Y') }}.
                    @else
                        Days overdue since {{ $monthlyReportDue->format('M d, Y') }}.
                    @endif
                </p>
            </div>
            <div>
                <div class="d-flex justify-content-between small fw-bold mb-2">
                    <span>Fund utilisation</span>
                    <span>{{ number_format($financePercent, 1) }}%</span>
                </div>
                <div class="tt-progress"><span style="width: {{ $financePercent }}%"></span></div>
            </div>
        </aside>
    </section>

    <section class="tt-filter-card">
        <div class="tt-filter-head">
            <div>
                <h2>Dashboard period filter</h2>
                <p>Filter funds allocated, disbursed amounts, activities, reports, research, and procurement by month, year, or custom dates.</p>
            </div>
            <span class="tt-filter-active"><i class="feather-calendar"></i> {{ $dashboardFilter['label'] }}</span>
        </div>
        <form method="GET" action="{{ route('think-tank.dashboard', $portalRouteParams) }}">
            @if(isset($portalRouteParams['think_tank_member_id']))
                <input type="hidden" name="think_tank_member_id" value="{{ $portalRouteParams['think_tank_member_id'] }}">
            @endif
            <div class="tt-filter-grid">
                <div class="tt-filter-field">
                    <label for="filter_month">Month</label>
                    <input id="filter_month" type="month" name="filter_month" value="{{ $dashboardFilter['month'] }}">
                </div>
                <div class="tt-filter-field">
                    <label for="filter_year">Year</label>
                    <select id="filter_year" name="filter_year">
                        <option value="">All years</option>
                        @foreach($dashboardFilter['year_options'] as $year)
                            <option value="{{ $year }}" @selected((string) $dashboardFilter['year'] === (string) $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="tt-filter-field">
                    <label for="date_from">Custom from</label>
                    <input id="date_from" type="date" name="date_from" value="{{ $dashboardFilter['date_from'] }}">
                </div>
                <div class="tt-filter-field">
                    <label for="date_to">Custom to</label>
                    <input id="date_to" type="date" name="date_to" value="{{ $dashboardFilter['date_to'] }}">
                </div>
                <div class="tt-filter-actions">
                    <button class="btn btn-primary" type="submit"><i class="feather-filter me-1"></i> Apply</button>
                    <a class="btn btn-light border" href="{{ route('think-tank.dashboard', $portalRouteParams) }}">Reset</a>
                </div>
            </div>
        </form>
    </section>

    <section class="tt-stats-grid">
        <div class="tt-stat-card">
            <span class="icon"><i class="feather-credit-card"></i></span>
            <div class="value">{{ $currency }} {{ number_format($metrics['disbursed'], 2) }}</div>
            <div class="label">Amount disbursed by ATTP · {{ $dashboardFilter['label'] }}</div>
        </div>
        <div class="tt-stat-card">
            <span class="icon" style="background:#dcfce7;"><i class="feather-trending-up"></i></span>
            <div class="value">{{ $currency }} {{ number_format($metrics['spent'], 2) }}</div>
            <div class="label">Reported activity spend · {{ $dashboardFilter['label'] }}</div>
        </div>
        <div class="tt-stat-card">
            <span class="icon" style="background:#e0f2fe;"><i class="feather-archive"></i></span>
            <div class="value">{{ $currency }} {{ number_format($metrics['balance'], 2) }}</div>
            <div class="label">Funds in custody remaining to spend Â· {{ $dashboardFilter['label'] }}</div>
        </div>
        <div class="tt-stat-card">
            <span class="icon" style="background:#fef3c7;"><i class="feather-file-text"></i></span>
            <div class="value">{{ number_format($metrics['reports']) }}</div>
            <div class="label">Reports to Secretariat</div>
        </div>
        <div class="tt-stat-card">
            <span class="icon" style="background:#ccfbf1;"><i class="feather-book-open"></i></span>
            <div class="value">{{ number_format($metrics['research']) }}</div>
            <div class="label">Research outputs submitted</div>
        </div>
    </section>

    <section class="tt-layout-grid">
        <div>
            <div class="tt-panel">
                <div class="tt-panel-head">
                    <div>
                        <h2>Performance graphs</h2>
                        <p class="hint">Finance, reporting, procurement, and research at a glance.</p>
                    </div>
                </div>
                <div class="tt-chart-grid">
                    <div class="tt-chart-box">
                        <h3>Finance position</h3>
                        <div id="ttFinanceChart"></div>
                    </div>
                    <div class="tt-chart-box">
                        <h3>Reports submitted over 6 months</h3>
                        <div id="ttReportsChart"></div>
                    </div>
                    <div class="tt-chart-box">
                        <h3>Procurement pipeline</h3>
                        <div id="ttProcurementChart"></div>
                    </div>
                    <div class="tt-chart-box">
                        <h3>Research output mix</h3>
                        <div id="ttResearchChart"></div>
                    </div>
                </div>
            </div>

            <div class="tt-panel">
                <div class="tt-panel-head">
                    <div>
                        <h2>Funded activities</h2>
                        <p class="hint">Activities and budget lines funded through Secretariat disbursements.</p>
                    </div>
                    <a class="btn btn-sm btn-light border" href="{{ route('think-tank.reports', $portalRouteParams) }}">Update progress</a>
                </div>
                <div class="tt-funded-list">
                    @forelse($fundedActivities as $activity)
                        <article class="tt-funded-row">
                            <div>
                                <div class="tt-funded-title">{{ $activity['budget_line'] }}</div>
                                <div class="tt-funded-meta">
                                    Current status: <strong class="text-capitalize">{{ $activity['status'] }}</strong>.
                                    {{ number_format($activity['utilization'], 1) }}% of disbursed funds reported as spent.
                                </div>
                            </div>
                            <div class="tt-funded-money">
                                <span>Allocated <strong>{{ $currency }} {{ number_format($activity['allocated'], 2) }}</strong></span>
                                <span>Disbursed <strong>{{ $currency }} {{ number_format($activity['disbursed'], 2) }}</strong></span>
                                <span>Spent <strong>{{ $currency }} {{ number_format($activity['spent'], 2) }}</strong></span>
                            </div>
                            <div class="tt-progress"><span style="width: {{ $activity['utilization'] }}%"></span></div>
                        </article>
                    @empty
                        <div class="alert alert-light border mb-0">
                            No fund allocation has been recorded for this think tank yet.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="tt-panel">
                <div class="tt-panel-head">
                    <div>
                        <h2>Procurement opportunities</h2>
                        <p class="hint">Open evaluations and selections managed by this think tank.</p>
                    </div>
                    <a class="btn btn-sm btn-primary" href="{{ route('think-tank.procurement', $portalRouteParams) }}">Create opportunity</a>
                </div>
                <div class="tt-table-wrap">
                    <table class="tt-dashboard-table">
                        <thead><tr><th>Title</th><th>Status</th><th>Applications</th><th>Closing</th><th></th></tr></thead>
                        <tbody>
                        @forelse($recentProcurements as $procurement)
                            <tr>
                                <td>{{ $procurement->title }}</td>
                                <td><span class="badge">{{ ucfirst($procurement->status) }}</span></td>
                                <td>{{ number_format($procurement->submissions_count) }}</td>
                                <td>{{ $procurement->application_end_date?->format('M d, Y') ?? 'N/A' }}</td>
                                <td>
                                    <a class="btn btn-sm btn-light border" href="{{ route('think-tank.procurement.submissions', array_merge($portalRouteParams, ['procurement' => $procurement])) }}">
                                        Evaluate
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5">No procurement opportunities yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <aside>
            <div class="tt-panel">
                <div class="tt-panel-head">
                    <div>
                        <h2>Notifications</h2>
                        <p class="hint">Tasks that need attention from the think tank team.</p>
                    </div>
                </div>
                <div class="tt-notification-list">
                    @foreach($upcomingActivities as $activity)
                        <a class="tt-note {{ $activity['type'] }}" href="{{ $activity['route'] }}">
                            <span class="tt-note-icon">
                                @if($activity['type'] === 'urgent')
                                    <i class="feather-alert-triangle"></i>
                                @elseif($activity['type'] === 'complete')
                                    <i class="feather-check-circle"></i>
                                @elseif($activity['type'] === 'procurement')
                                    <i class="feather-briefcase"></i>
                                @else
                                    <i class="feather-bell"></i>
                                @endif
                            </span>
                            <span>
                                <span class="tt-note-title d-block">{{ $activity['title'] }}</span>
                                <span class="tt-note-meta d-block">{{ $activity['meta'] }}</span>
                                <span class="tt-note-value">{{ $activity['value'] }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="tt-panel">
                <div class="tt-panel-head">
                    <div>
                        <h2>Recent research</h2>
                        <p class="hint">Submissions visible to Secretariat oversight.</p>
                    </div>
                </div>
                <div class="tt-table-wrap">
                    <table class="tt-dashboard-table">
                        <thead><tr><th>Title</th><th>Type</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse($recentResearch as $output)
                            <tr>
                                <td>{{ $output->title }}</td>
                                <td>{{ str_replace('_', ' ', ucfirst($output->output_type)) }}</td>
                                <td><span class="badge">{{ ucfirst($output->status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="3">No research submitted yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tt-panel">
                <div class="tt-panel-head">
                    <div>
                        <h2>Recent reports</h2>
                        <p class="hint">Latest Secretariat-facing activity updates.</p>
                    </div>
                </div>
                <div class="tt-table-wrap">
                    <table class="tt-dashboard-table">
                        <thead><tr><th>Report</th><th>Progress</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse($recentReports as $report)
                            <tr>
                                <td>{{ $report->title }}</td>
                                <td>{{ number_format((float) $report->progress_percent, 1) }}%</td>
                                <td><span class="badge">{{ ucfirst($report->status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="3">No reports submitted yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </aside>
    </section>
</x-think-tank.partials.shell>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof ApexCharts === 'undefined') {
                return;
            }

            const chartData = @json($chartData);
            const baseOptions = {
                chart: { toolbar: { show: false }, fontFamily: 'Inter, Arial, sans-serif' },
                dataLabels: { enabled: false },
                colors: ['#0ea5e9', '#22c55e', '#f59e0b', '#6366f1'],
                grid: { borderColor: '#e2e8f0' },
                legend: { position: 'bottom' }
            };

            new ApexCharts(document.querySelector('#ttFinanceChart'), {
                ...baseOptions,
                chart: { ...baseOptions.chart, type: 'bar', height: 230 },
                series: [{ name: 'Amount', data: chartData.finance.values }],
                xaxis: { categories: chartData.finance.labels },
                yaxis: { labels: { formatter: (value) => Number(value).toLocaleString() } },
                plotOptions: { bar: { borderRadius: 5, columnWidth: '48%' } }
            }).render();

            new ApexCharts(document.querySelector('#ttReportsChart'), {
                ...baseOptions,
                chart: { ...baseOptions.chart, type: 'area', height: 230 },
                stroke: { curve: 'smooth', width: 3 },
                fill: { opacity: .18 },
                series: [{ name: 'Reports', data: chartData.reports.values }],
                xaxis: { categories: chartData.reports.labels }
            }).render();

            new ApexCharts(document.querySelector('#ttProcurementChart'), {
                ...baseOptions,
                chart: { ...baseOptions.chart, type: 'donut', height: 230 },
                series: chartData.procurements.values,
                labels: chartData.procurements.labels
            }).render();

            new ApexCharts(document.querySelector('#ttResearchChart'), {
                ...baseOptions,
                chart: { ...baseOptions.chart, type: 'bar', height: 230 },
                series: [{ name: 'Outputs', data: chartData.research.values.length ? chartData.research.values : [0] }],
                xaxis: { categories: chartData.research.labels.length ? chartData.research.labels : ['No output yet'] },
                plotOptions: { bar: { horizontal: true, borderRadius: 5 } }
            }).render();
        });
    </script>
@endpush
