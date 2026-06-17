@extends('layouts.app')

@section('title', $reportCopy['pageTitle'] ?? 'Think Tank Reports')

@push('styles')
    <style>
        .tt-report-shell {
            background: #f6f8fb;
            border-radius: 8px;
            padding: 1.2rem;
        }

        .tt-report-hero {
            border: 0;
            border-radius: 8px;
            background:
                linear-gradient(135deg, rgba(15, 118, 110, 0.94), rgba(99, 102, 241, 0.9)),
                linear-gradient(45deg, #0f766e, #f59e0b);
            color: #fff;
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.18);
        }

        .tt-report-hero h3,
        .tt-report-hero p {
            color: #fff;
        }

        .tt-report-kicker {
            color: #fef3c7;
            font-size: 0.72rem;
            font-weight: 900;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .tt-report-action {
            border: 1px solid rgba(255, 255, 255, 0.28);
            background: rgba(255, 255, 255, 0.16);
            color: #fff;
            font-weight: 800;
            backdrop-filter: blur(8px);
        }

        .tt-report-action:hover,
        .tt-report-action.active {
            background: #fff;
            color: #0f172a;
        }

        .tt-report-filter {
            display: grid;
            grid-template-columns: minmax(220px, 1.2fr) minmax(170px, .7fr) minmax(135px, .55fr) minmax(145px, .55fr) minmax(145px, .55fr) auto;
            gap: 0.7rem;
            align-items: end;
        }

        .tt-report-filter label {
            color: #475569;
            font-size: 0.72rem;
            font-weight: 850;
        }

        .tt-report-card,
        .tt-report-panel,
        .tt-report-table {
            border: 0;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
        }

        .tt-report-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.85rem;
        }

        .tt-report-card {
            transition: transform .18s ease, box-shadow .18s ease;
        }

        .tt-report-card:hover,
        .tt-report-panel:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 36px rgba(15, 23, 42, 0.12);
        }

        .tt-report-card .label {
            color: #64748b;
            font-size: 0.7rem;
            font-weight: 900;
            text-transform: uppercase;
        }

        .tt-report-card .value {
            color: #0f172a;
            font-size: 1.35rem;
            font-weight: 950;
        }

        .tt-report-card .meta {
            color: #64748b;
            font-size: 0.78rem;
            font-weight: 700;
        }

        .tt-report-icon {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.14);
        }

        .tt-report-icon.teal { background: linear-gradient(135deg, #14b8a6, #0f766e); }
        .tt-report-icon.indigo { background: linear-gradient(135deg, #6366f1, #4338ca); }
        .tt-report-icon.emerald { background: linear-gradient(135deg, #22c55e, #15803d); }
        .tt-report-icon.rose { background: linear-gradient(135deg, #fb7185, #be123c); }

        .tt-report-chart-grid {
            display: grid;
            grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr);
            gap: 0.9rem;
        }

        .tt-report-panel {
            min-height: 315px;
            padding: 1rem;
            transition: transform .18s ease, box-shadow .18s ease;
        }

        .tt-report-panel h5,
        .tt-report-table h5 {
            color: #0f172a;
            font-weight: 950;
        }

        .tt-report-panel .sub {
            color: #64748b;
            font-size: 0.8rem;
            font-weight: 700;
            margin-bottom: 0.8rem;
        }

        .tt-report-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.28rem;
            border-radius: 999px;
            padding: 0.26rem 0.5rem;
            font-size: 0.7rem;
            font-weight: 900;
        }

        .tt-report-badge.good { background: #dcfce7; color: #166534; }
        .tt-report-badge.warn { background: #fff7ed; color: #c2410c; }
        .tt-report-badge.info { background: #e0f2fe; color: #075985; }
        .tt-report-badge.muted { background: #f1f5f9; color: #475569; }

        .tt-report-progress {
            height: 8px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
        }

        .tt-report-progress span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #14b8a6, #6366f1);
        }

        .tt-report-table th {
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 900;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .tt-report-name {
            color: #0f172a;
            font-weight: 950;
        }

        .tt-report-empty {
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 2rem;
            color: #64748b;
            text-align: center;
            background: #f8fafc;
        }

        @media (max-width: 1200px) {
            .tt-report-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .tt-report-chart-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 760px) {
            .tt-report-shell {
                padding: 0.8rem;
            }

            .tt-report-filter,
            .tt-report-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $isConsortiumReports = ($reportMode ?? 'consortium') === 'consortium';
        $formatMoney = fn ($amount) => 'USD ' . number_format((float) $amount, 2);
        $statusClass = fn ($status) => match ($status) {
            'approved' => 'good',
            'rejected', 'revisions_requested' => 'warn',
            'submitted' => 'info',
            default => 'muted',
        };
    @endphp

    <div class="nxl-container">
        <div class="tt-report-shell">
            <div class="card tt-report-hero mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between gap-3 flex-wrap">
                        <div>
                            <div class="tt-report-kicker mb-2">Think Tank Module</div>
                            <h3 class="fw-bold mb-2">{{ $reportCopy['heroTitle'] }}</h3>
                            <p class="mb-0">{{ $reportCopy['heroText'] }}</p>
                            <div class="mt-3">
                                <span class="tt-report-badge info">
                                    <i class="feather-calendar"></i> {{ $dateRangeLabel }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <form method="GET" action="{{ route($reportCopy['filterRoute']) }}" class="card tt-report-table mb-4">
                <div class="card-body">
                    <div class="tt-report-filter">
                        <div>
                            <label for="ttReportSearch">Search</label>
                            <input id="ttReportSearch" type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ $reportCopy['searchPlaceholder'] }}">
                        </div>
                        @if ($isConsortiumReports)
                            <div>
                                <label for="ttReportConsortium">Consortium</label>
                                <select id="ttReportConsortium" name="consortium_id" class="form-select">
                                    <option value="">All consortia</option>
                                    @foreach ($consortia as $consortium)
                                        <option value="{{ $consortium->id }}" @selected((string) request('consortium_id') === (string) $consortium->id)>
                                            {{ $consortium->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div>
                            <label for="ttReportStatus">Status</label>
                            <select id="ttReportStatus" name="status" class="form-select">
                                <option value="">All statuses</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="ttReportStart">From</label>
                            <input id="ttReportStart" type="date" name="start_date" value="{{ request('start_date') }}" class="form-control">
                        </div>
                        <div>
                            <label for="ttReportEnd">To</label>
                            <input id="ttReportEnd" type="date" name="end_date" value="{{ request('end_date') }}" class="form-control">
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="feather-search me-1"></i> Filter
                            </button>
                            <a href="{{ route($reportCopy['filterRoute']) }}" class="btn btn-light border">
                                <i class="feather-x"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </form>

            <div class="tt-report-grid mb-4">
                <div class="card tt-report-card">
                    <div class="card-body d-flex justify-content-between gap-3">
                        <div>
                            <div class="label">{{ $reportCopy['entityPlural'] }}</div>
                            <div class="value">{{ number_format($summary['entities']) }}</div>
                            <div class="meta">{{ number_format($summary['reports']) }} total reports</div>
                        </div>
                        <span class="tt-report-icon teal"><i class="feather-grid"></i></span>
                    </div>
                </div>
                <div class="card tt-report-card">
                    <div class="card-body d-flex justify-content-between gap-3">
                        <div>
                            <div class="label">Approved</div>
                            <div class="value">{{ number_format($summary['approved']) }}</div>
                            <div class="meta">{{ number_format($summary['submitted']) }} awaiting review</div>
                        </div>
                        <span class="tt-report-icon emerald"><i class="feather-check-circle"></i></span>
                    </div>
                </div>
                <div class="card tt-report-card">
                    <div class="card-body d-flex justify-content-between gap-3">
                        <div>
                            <div class="label">Evidence</div>
                            <div class="value">{{ number_format($summary['evidence']) }}</div>
                            <div class="meta">{{ number_format($summary['attention']) }} attention-needed</div>
                        </div>
                        <span class="tt-report-icon indigo"><i class="feather-paperclip"></i></span>
                    </div>
                </div>
                <div class="card tt-report-card">
                    <div class="card-body d-flex justify-content-between gap-3">
                        <div>
                            <div class="label">Funds Spent</div>
                            <div class="value">{{ $formatMoney($summary['funds_spent']) }}</div>
                            <div class="meta">{{ number_format($summary['avg_progress'], 1) }}% average progress</div>
                        </div>
                        <span class="tt-report-icon rose"><i class="feather-trending-up"></i></span>
                    </div>
                </div>
            </div>

            <div class="tt-report-chart-grid mb-4">
                <div class="tt-report-panel">
                    <h5>Report Status</h5>
                    <div class="sub">Submitted, approved, and attention-needed report records.</div>
                    <div id="ttReportStatusChart"></div>
                </div>
                <div class="tt-report-panel">
                    <h5>Top {{ $reportCopy['entityPlural'] }}</h5>
                    <div class="sub">Report volume and evidence count by selected report grouping.</div>
                    <div id="ttReportTopChart"></div>
                </div>
            </div>

            <div class="card tt-report-table mb-4">
                <div class="card-body">
                    <div class="mb-3">
                        <h5 class="mb-1">{{ $reportCopy['tableTitle'] }}</h5>
                        <div class="text-muted small">{{ $reportCopy['tableDescription'] }}</div>
                    </div>

                    @if ($reportRows->isEmpty())
                        <div class="tt-report-empty">{{ $reportCopy['emptyText'] }}</div>
                    @else
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                <tr>
                                    <th>{{ $reportCopy['entityColumn'] }}</th>
                                    <th>Reports</th>
                                    <th>Status</th>
                                    <th>Evidence</th>
                                    <th>Progress</th>
                                    <th>Latest Report</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($reportRows as $row)
                                    <tr>
                                        <td style="min-width: 260px;">
                                            <div class="tt-report-name">{{ $row['primary_name'] }}</div>
                                            <div class="text-muted small">{{ $row['primary_meta'] }}</div>
                                            <div class="text-muted small">{{ $row['secondary_meta'] }}</div>
                                        </td>
                                        <td>
                                            <strong>{{ number_format($row['reports']) }}</strong>
                                            <div class="text-muted small">{{ $formatMoney($row['funds_spent']) }}</div>
                                        </td>
                                        <td>
                                            <span class="tt-report-badge good">{{ number_format($row['approved']) }} approved</span>
                                            <div class="text-muted small mt-1">{{ number_format($row['submitted']) }} submitted | {{ number_format($row['attention']) }} attention</div>
                                        </td>
                                        <td>
                                            <strong>{{ number_format($row['evidence']) }}</strong>
                                            <div class="text-muted small">proof documents</div>
                                        </td>
                                        <td style="min-width: 150px;">
                                            <div class="d-flex justify-content-between small fw-bold mb-1">
                                                <span>Progress</span>
                                                <span>{{ number_format($row['avg_progress'], 1) }}%</span>
                                            </div>
                                            <div class="tt-report-progress">
                                                <span style="width: {{ number_format(min(100, $row['avg_progress']), 2, '.', '') }}%"></span>
                                            </div>
                                            <div class="text-muted small mt-1">{{ number_format($row['approval_rate'], 1) }}% approval rate</div>
                                        </td>
                                        <td style="min-width: 220px;">
                                            <strong>{{ $row['latest_title'] ?: 'No report title' }}</strong>
                                            <div class="mt-1">
                                                <span class="tt-report-badge {{ $statusClass($row['latest_status']) }}">{{ ucfirst(str_replace('_', ' ', $row['latest_status'] ?: 'unknown')) }}</span>
                                            </div>
                                            <div class="text-muted small mt-1">{{ $row['latest_date'] ?: 'No report date' }}</div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card tt-report-table">
                <div class="card-body">
                    <div class="mb-3">
                        <h5 class="mb-1">Recent Report Submissions</h5>
                        <div class="text-muted small">Latest submitted report records in this filtered view.</div>
                    </div>

                    @if ($recentReports->isEmpty())
                        <div class="tt-report-empty">No recent reports found.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Report</th>
                                    <th>{{ $reportCopy['entityColumn'] }}</th>
                                    <th>Status</th>
                                    <th>Period</th>
                                    <th>Submitted</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($recentReports as $report)
                                    <tr>
                                        <td style="min-width: 240px;">
                                            <div class="tt-report-name">{{ $report->title ?: 'Untitled report' }}</div>
                                            <div class="text-muted small">{{ \Illuminate\Support\Str::limit((string) $report->summary, 90) }}</div>
                                        </td>
                                        <td>
                                            @if ($isConsortiumReports)
                                                {{ $report->consortium?->name ?: 'No consortium' }}
                                            @else
                                                {{ $report->member?->name ?: 'No think tank' }}
                                            @endif
                                        </td>
                                        <td>
                                            <span class="tt-report-badge {{ $statusClass($report->status) }}">{{ ucfirst(str_replace('_', ' ', $report->status ?: 'draft')) }}</span>
                                        </td>
                                        <td>
                                            {{ $report->reporting_period_start?->format('M d, Y') ?: 'N/A' }}
                                            <div class="text-muted small">{{ $report->reporting_period_end?->format('M d, Y') ?: 'Open ended' }}</div>
                                        </td>
                                        <td>{{ ($report->submitted_at ?: $report->created_at)?->format('M d, Y') ?: 'N/A' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
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
            const base = {
                chart: { toolbar: { show: false }, fontFamily: 'Inter, Arial, sans-serif' },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 3 },
                grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
                legend: { position: 'bottom' }
            };

            new ApexCharts(document.querySelector('#ttReportStatusChart'), {
                ...base,
                series: chartData.status.values,
                labels: chartData.status.labels,
                chart: { ...base.chart, type: 'donut', height: 260 },
                colors: ['#06b6d4', '#22c55e', '#fb7185']
            }).render();

            new ApexCharts(document.querySelector('#ttReportTopChart'), {
                ...base,
                series: [
                    { name: 'Reports', data: chartData.topEntities.reports },
                    { name: 'Evidence', data: chartData.topEntities.evidence }
                ],
                chart: { ...base.chart, type: 'bar', height: 285 },
                colors: ['#6366f1', '#14b8a6'],
                plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: '62%' } },
                xaxis: { categories: chartData.topEntities.labels }
            }).render();
        });
    </script>
@endpush
