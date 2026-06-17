@extends('layouts.app')

@section('title', 'Think Tank Dashboard')

@push('styles')
    <style>
        .tt-soft-shell {
            background: #f6f8fb;
            border-radius: 8px;
            padding: 1.2rem;
        }

        .tt-soft-hero {
            border-radius: 8px;
            background:
                linear-gradient(135deg, rgba(20, 184, 166, 0.94), rgba(79, 70, 229, 0.9)),
                linear-gradient(45deg, #14b8a6, #f59e0b);
            color: #fff;
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.18);
            overflow: hidden;
        }

        .tt-soft-hero h3,
        .tt-soft-hero p {
            color: #fff;
        }

        .tt-soft-kicker {
            color: #fef3c7;
            font-size: 0.72rem;
            font-weight: 900;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .tt-soft-action {
            border: 1px solid rgba(255, 255, 255, 0.28);
            background: rgba(255, 255, 255, 0.16);
            color: #fff;
            font-weight: 800;
            backdrop-filter: blur(8px);
        }

        .tt-soft-action:hover {
            background: #fff;
            color: #0f172a;
        }

        .tt-soft-filter {
            display: grid;
            grid-template-columns: minmax(220px, 1.3fr) minmax(170px, .75fr) minmax(130px, .55fr) auto;
            gap: 0.7rem;
            align-items: end;
        }

        .tt-soft-filter label {
            color: #475569;
            font-size: 0.72rem;
            font-weight: 850;
        }

        .tt-soft-stat-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.85rem;
        }

        .tt-soft-stat,
        .tt-soft-panel,
        .tt-soft-table {
            border: 0;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
        }

        .tt-soft-stat {
            position: relative;
            overflow: hidden;
            transition: transform .18s ease, box-shadow .18s ease;
        }

        .tt-soft-stat:hover,
        .tt-soft-panel:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 36px rgba(15, 23, 42, 0.12);
        }

        .tt-soft-stat .label {
            color: #64748b;
            font-size: 0.7rem;
            font-weight: 900;
            text-transform: uppercase;
        }

        .tt-soft-stat .value {
            color: #0f172a;
            font-size: 1.35rem;
            font-weight: 950;
        }

        .tt-soft-stat .meta {
            color: #64748b;
            font-size: 0.78rem;
            font-weight: 700;
        }

        .tt-soft-icon {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.14);
        }

        .tt-soft-icon.teal { background: linear-gradient(135deg, #14b8a6, #0f766e); }
        .tt-soft-icon.indigo { background: linear-gradient(135deg, #6366f1, #4338ca); }
        .tt-soft-icon.amber { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .tt-soft-icon.rose { background: linear-gradient(135deg, #fb7185, #be123c); }
        .tt-soft-icon.emerald { background: linear-gradient(135deg, #22c55e, #15803d); }
        .tt-soft-icon.cyan { background: linear-gradient(135deg, #06b6d4, #0369a1); }

        .tt-soft-chart-grid {
            display: grid;
            grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr);
            gap: 0.9rem;
        }

        .tt-soft-panel {
            padding: 1rem;
            min-height: 330px;
            transition: transform .18s ease, box-shadow .18s ease;
        }

        .tt-soft-panel h5 {
            color: #0f172a;
            font-weight: 950;
            margin-bottom: 0.2rem;
        }

        .tt-soft-panel .sub {
            color: #64748b;
            font-size: 0.8rem;
            font-weight: 700;
            margin-bottom: 0.8rem;
        }

        .tt-soft-mini-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.8rem;
        }

        .tt-soft-progress {
            height: 8px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
        }

        .tt-soft-progress span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #14b8a6, #6366f1);
        }

        .tt-soft-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.28rem;
            border-radius: 999px;
            padding: 0.26rem 0.5rem;
            font-size: 0.7rem;
            font-weight: 900;
        }

        .tt-soft-badge.good {
            background: #dcfce7;
            color: #166534;
        }

        .tt-soft-badge.warn {
            background: #fff7ed;
            color: #c2410c;
        }

        .tt-soft-badge.info {
            background: #e0f2fe;
            color: #075985;
        }

        .tt-soft-badge.muted {
            background: #f1f5f9;
            color: #475569;
        }

        .tt-soft-table table {
            margin-bottom: 0;
        }

        .tt-soft-table th {
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 900;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .tt-soft-table td {
            vertical-align: middle;
        }

        .tt-soft-name {
            color: #0f172a;
            font-weight: 950;
            text-decoration: none;
        }

        .tt-soft-name:hover {
            color: #0f766e;
        }

        .tt-soft-empty {
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 2rem;
            color: #64748b;
            text-align: center;
            background: #f8fafc;
        }

        @media (max-width: 1200px) {
            .tt-soft-stat-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .tt-soft-chart-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 760px) {
            .tt-soft-shell {
                padding: 0.8rem;
            }

            .tt-soft-filter,
            .tt-soft-stat-grid,
            .tt-soft-mini-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $currency = $portfolioRows->first()['currency'] ?? 'USD';
        $formatMoney = fn ($amount) => $currency . ' ' . number_format((float) $amount, 2);
    @endphp

    <div class="nxl-container">
        <div class="tt-soft-shell">
            <div class="card tt-soft-hero mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between gap-3 flex-wrap">
                        <div>
                            <div class="tt-soft-kicker mb-2">Think Tank Management</div>
                            <h3 class="fw-bold mb-2">Think Tank Operations Dashboard</h3>
                            <p class="mb-0">
                                Procurement, vendor, payment, proof document, and reporting visibility for qualified consortium think tanks.
                            </p>
                        </div>
                        <div class="d-flex gap-2 flex-wrap align-items-start">
                            @can('finance.commitments.create')
                                @if (Route::has('finance.purchase-requests.create'))
                                    <a href="{{ route('finance.purchase-requests.create') }}" class="btn tt-soft-action btn-sm">
                                        <i class="feather-file-plus me-1"></i> New PR
                                    </a>
                                @endif
                            @endcan
                            @can('finance.purchase_orders.create')
                                @if (Route::has('procurement.purchase-orders.create'))
                                    <a href="{{ route('procurement.purchase-orders.create') }}" class="btn tt-soft-action btn-sm">
                                        <i class="feather-shopping-bag me-1"></i> New PO
                                    </a>
                                @endif
                            @endcan
                            @can('finance.purchase_requests.view')
                                @if (Route::has('procurement.disbursements.create'))
                                    <a href="{{ route('procurement.disbursements.create') }}" class="btn tt-soft-action btn-sm">
                                        <i class="feather-credit-card me-1"></i> Pay
                                    </a>
                                @endif
                            @endcan
                        </div>
                    </div>
                </div>
            </div>

            <form method="GET" action="{{ route('think-tanks-admin.dashboard') }}" class="card tt-soft-table mb-4">
                <div class="card-body">
                    <div class="tt-soft-filter">
                        <div>
                            <label for="ttSearch">Search</label>
                            <input id="ttSearch" type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Think tank, consortium, country, vendor">
                        </div>
                        <div>
                            <label for="ttConsortium">Consortium</label>
                            <select id="ttConsortium" name="consortium_id" class="form-select">
                                <option value="">All consortia</option>
                                @foreach ($consortia as $consortium)
                                    <option value="{{ $consortium->id }}" @selected((string) request('consortium_id') === (string) $consortium->id)>
                                        {{ $consortium->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="ttStatus">Status</label>
                            <select id="ttStatus" name="status" class="form-select">
                                <option value="">All statuses</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="feather-search me-1"></i> Filter
                            </button>
                            <a href="{{ route('think-tanks-admin.dashboard') }}" class="btn btn-light border">
                                <i class="feather-x"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </form>

            <div class="tt-soft-stat-grid mb-4">
                <div class="card tt-soft-stat">
                    <div class="card-body d-flex justify-content-between gap-3">
                        <div>
                            <div class="label">Think Tanks</div>
                            <div class="value">{{ number_format($summary['think_tanks']) }}</div>
                            <div class="meta">{{ number_format($summary['active']) }} active profiles</div>
                        </div>
                        <span class="tt-soft-icon teal"><i class="feather-users"></i></span>
                    </div>
                </div>
                <div class="card tt-soft-stat">
                    <div class="card-body d-flex justify-content-between gap-3">
                        <div>
                            <div class="label">Purchase Requests</div>
                            <div class="value">{{ number_format($summary['purchase_requests']) }}</div>
                            <div class="meta">{{ number_format($summary['purchase_orders']) }} purchase orders</div>
                        </div>
                        <span class="tt-soft-icon indigo"><i class="feather-file-text"></i></span>
                    </div>
                </div>
                <div class="card tt-soft-stat">
                    <div class="card-body d-flex justify-content-between gap-3">
                        <div>
                            <div class="label">Paid Disbursements</div>
                            <div class="value">{{ number_format($summary['disbursements']) }}</div>
                            <div class="meta">{{ $formatMoney($summary['paid_amount']) }}</div>
                        </div>
                        <span class="tt-soft-icon emerald"><i class="feather-credit-card"></i></span>
                    </div>
                </div>
                <div class="card tt-soft-stat">
                    <div class="card-body d-flex justify-content-between gap-3">
                        <div>
                            <div class="label">Proof Documents</div>
                            <div class="value">{{ number_format($summary['documents']) }}</div>
                            <div class="meta">{{ number_format($summary['reports']) }} submitted reports</div>
                        </div>
                        <span class="tt-soft-icon amber"><i class="feather-paperclip"></i></span>
                    </div>
                </div>
            </div>

            <div class="tt-soft-mini-grid mb-4">
                <div class="card tt-soft-stat">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <span class="label">PO Portfolio</span>
                            <span class="tt-soft-badge info">{{ number_format($summary['payment_rate'], 1) }}%</span>
                        </div>
                        <div class="value">{{ $formatMoney($summary['po_amount']) }}</div>
                        <div class="tt-soft-progress mt-2"><span style="width: {{ number_format($summary['payment_rate'], 2, '.', '') }}%"></span></div>
                    </div>
                </div>
                <div class="card tt-soft-stat">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <span class="label">Open PO Balance</span>
                            <span class="tt-soft-badge warn">{{ $formatMoney($summary['open_amount']) }}</span>
                        </div>
                        <div class="value">{{ $formatMoney($summary['open_amount']) }}</div>
                        <div class="meta">Remaining amount across linked think tank POs</div>
                    </div>
                </div>
                <div class="card tt-soft-stat">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <span class="label">Receipt Confirmation</span>
                            <span class="tt-soft-badge good">{{ number_format($summary['receipt_rate'], 1) }}%</span>
                        </div>
                        <div class="value">{{ $formatMoney($summary['confirmed_amount']) }}</div>
                        <div class="tt-soft-progress mt-2"><span style="width: {{ number_format($summary['receipt_rate'], 2, '.', '') }}%"></span></div>
                    </div>
                </div>
            </div>

            <div class="tt-soft-chart-grid mb-4">
                <div class="tt-soft-panel">
                    <h5>Financial Mix</h5>
                    <div class="sub">PO value, paid amount, open balance, and confirmed receipts.</div>
                    <div id="ttAdminFinanceChart"></div>
                </div>
                <div class="tt-soft-panel">
                    <h5>Top Think Tanks</h5>
                    <div class="sub">Largest purchase order portfolios by linked think tank/vendor identity.</div>
                    <div id="ttAdminTopChart"></div>
                </div>
                <div class="tt-soft-panel">
                    <h5>Operational Flow</h5>
                    <div class="sub">Request, order, payment, proof, and reporting activity.</div>
                    <div id="ttAdminPipelineChart"></div>
                </div>
                <div class="tt-soft-panel">
                    <h5>Report Status</h5>
                    <div class="sub">Submitted, approved, and attention-needed report records.</div>
                    <div id="ttAdminReportChart"></div>
                </div>
            </div>

            <div class="card tt-soft-table">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
                        <div>
                            <h5 class="fw-bold mb-1">Think Tank Operations Register</h5>
                            <div class="text-muted small">PR, PO, disbursement, proof document, and report coverage by think tank.</div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            @if (Route::has('think-tanks-admin.directory'))
                                <a href="{{ route('think-tanks-admin.directory') }}" class="btn btn-light border btn-sm">
                                    <i class="feather-list me-1"></i> Profiles
                                </a>
                            @endif
                            @can('consortiums.view')
                                @if (Route::has('consortium-operations.index'))
                                    <a href="{{ route('consortium-operations.index') }}" class="btn btn-light border btn-sm">
                                        <i class="feather-grid me-1"></i> Consortium Dashboard
                                    </a>
                                @endif
                            @endcan
                        </div>
                    </div>

                    @if ($portfolioRows->isEmpty())
                        <div class="tt-soft-empty">No think tanks matched this view.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                <tr>
                                    <th>Think Tank</th>
                                    <th>Linkage</th>
                                    <th>PR</th>
                                    <th>PO</th>
                                    <th>Paid</th>
                                    <th>Documents</th>
                                    <th>Reports</th>
                                    <th>Progress</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($portfolioRows as $row)
                                    <tr>
                                        <td style="min-width: 240px;">
                                            <a class="tt-soft-name" href="{{ route('think-tanks-admin.show', $row['id']) }}">{{ $row['name'] }}</a>
                                            <div class="text-muted small">{{ $row['consortium'] }}{{ $row['consortium_code'] ? ' | ' . $row['consortium_code'] : '' }}</div>
                                            <div class="text-muted small">{{ $row['country'] }} | {{ ucfirst($row['status']) }}</div>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1 flex-wrap">
                                                <span class="tt-soft-badge {{ $row['vendor_linked'] ? 'good' : 'warn' }}">Vendor</span>
                                                <span class="tt-soft-badge {{ $row['portal_linked'] ? 'good' : 'warn' }}">Portal</span>
                                                <span class="tt-soft-badge {{ $row['dataset_linked'] ? 'info' : 'muted' }}">DB</span>
                                            </div>
                                        </td>
                                        <td>
                                            <strong>{{ number_format($row['purchase_requests']) }}</strong>
                                        </td>
                                        <td>
                                            <strong>{{ number_format($row['purchase_orders']) }}</strong>
                                            <div class="text-muted small">{{ $row['currency'] }} {{ number_format($row['po_amount'], 2) }}</div>
                                        </td>
                                        <td>
                                            <strong>{{ number_format($row['disbursements']) }}</strong>
                                            <div class="text-muted small">{{ $row['currency'] }} {{ number_format($row['paid_amount'], 2) }}</div>
                                        </td>
                                        <td>
                                            <strong>{{ number_format($row['documents']) }}</strong>
                                            <div class="text-muted small">{{ number_format($row['procurement_documents']) }} procurement | {{ number_format($row['report_documents']) }} report</div>
                                        </td>
                                        <td>
                                            <strong>{{ number_format($row['reports_total']) }}</strong>
                                            <div class="text-muted small">{{ number_format($row['reports_submitted']) }} pending | {{ number_format($row['reports_approved']) }} approved</div>
                                        </td>
                                        <td style="min-width: 160px;">
                                            <div class="d-flex justify-content-between small fw-bold mb-1">
                                                <span>Paid</span>
                                                <span>{{ number_format($row['payment_rate'], 1) }}%</span>
                                            </div>
                                            <div class="tt-soft-progress mb-2">
                                                <span style="width: {{ number_format($row['payment_rate'], 2, '.', '') }}%"></span>
                                            </div>
                                            <div class="d-flex justify-content-between small fw-bold mb-1">
                                                <span>Receipt</span>
                                                <span>{{ number_format($row['receipt_rate'], 1) }}%</span>
                                            </div>
                                            <div class="tt-soft-progress">
                                                <span style="width: {{ number_format($row['receipt_rate'], 2, '.', '') }}%"></span>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('think-tanks-admin.show', $row['id']) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="feather-eye"></i>
                                            </a>
                                        </td>
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
            const money = (value) => Number(value || 0).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            const base = {
                chart: { toolbar: { show: false }, fontFamily: 'Inter, Arial, sans-serif' },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 3 },
                grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
                legend: { position: 'bottom' }
            };

            new ApexCharts(document.querySelector('#ttAdminFinanceChart'), {
                ...base,
                series: chartData.finance.values,
                labels: chartData.finance.labels,
                chart: { ...base.chart, type: 'donut', height: 260 },
                colors: ['#14b8a6', '#22c55e', '#f59e0b', '#6366f1'],
                tooltip: { y: { formatter: (value) => money(value) } }
            }).render();

            new ApexCharts(document.querySelector('#ttAdminTopChart'), {
                ...base,
                series: [
                    { name: 'PO Value', data: chartData.topThinkTanks.po },
                    { name: 'Paid', data: chartData.topThinkTanks.paid },
                    { name: 'Open', data: chartData.topThinkTanks.open }
                ],
                chart: { ...base.chart, type: 'bar', height: 300 },
                colors: ['#6366f1', '#22c55e', '#f59e0b'],
                plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: '62%' } },
                xaxis: {
                    categories: chartData.topThinkTanks.labels,
                    labels: { formatter: (value) => Number(value || 0).toLocaleString() }
                },
                tooltip: { y: { formatter: (value) => money(value) } }
            }).render();

            new ApexCharts(document.querySelector('#ttAdminPipelineChart'), {
                ...base,
                series: [{ name: 'Records', data: chartData.pipeline.values }],
                chart: { ...base.chart, type: 'bar', height: 260 },
                colors: ['#06b6d4'],
                plotOptions: { bar: { borderRadius: 5, columnWidth: '46%' } },
                xaxis: { categories: chartData.pipeline.labels }
            }).render();

            new ApexCharts(document.querySelector('#ttAdminReportChart'), {
                ...base,
                series: chartData.reports.values,
                labels: chartData.reports.labels,
                chart: { ...base.chart, type: 'donut', height: 260 },
                colors: ['#06b6d4', '#22c55e', '#fb7185']
            }).render();
        });
    </script>
@endpush
