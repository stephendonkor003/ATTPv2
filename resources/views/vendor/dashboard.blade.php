@extends('layouts.vendor')

@section('title', 'Vendor Dashboard')

@section('content')
    @php
        use Illuminate\Support\Str;

        $formatMoney = fn ($amount, $currency = 'USD') => trim(($currency ?: 'USD') . ' ' . number_format((float) $amount, 2));
        $periodLabel = $dateFrom || $dateTo
            ? trim(($dateFrom?->format('M d, Y') ?? 'Any date') . ' to ' . ($dateTo?->format('M d, Y') ?? 'Today'))
            : 'All time';
    @endphp

    <div class="vendor-page-head">
        <div>
            <div class="vendor-eyebrow">Performance Dashboard</div>
            <h3 class="vendor-page-title">Welcome back, {{ auth()->user()->name ?? 'Vendor' }}</h3>
            <p class="text-muted mb-0">
                {{ auth()->user()->vendor_category ?? 'Vendor' }} workspace for purchase orders, deliverable evidence, reports, and payments.
            </p>
        </div>
        <form method="GET" action="{{ route('vendor.dashboard') }}" class="d-flex align-items-end flex-wrap gap-2">
            <div>
                <label class="form-label small fw-semibold mb-1">From</label>
                <input type="date" name="date_from" value="{{ $dateFrom?->toDateString() }}" class="form-control form-control-sm">
            </div>
            <div>
                <label class="form-label small fw-semibold mb-1">To</label>
                <input type="date" name="date_to" value="{{ $dateTo?->toDateString() }}" class="form-control form-control-sm">
            </div>
            <button class="btn btn-vendor btn-sm" type="submit">
                <i class="feather-filter me-1"></i> Apply
            </button>
            <a href="{{ route('vendor.dashboard') }}" class="btn btn-vendor-outline btn-sm">
                <i class="feather-refresh-cw me-1"></i> Reset
            </a>
        </form>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>There were errors with your request.</strong>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card vendor-card h-100">
                <div class="card-body vendor-metric">
                    <div>
                        <div class="vendor-metric-label">Applications</div>
                        <div class="vendor-metric-value">{{ $dashboardStats['applications'] }}</div>
                        <div class="text-muted small">{{ $dashboardStats['open_applications'] }} open in {{ $periodLabel }}</div>
                    </div>
                    <span class="vendor-metric-icon"><i class="feather-file-text"></i></span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card vendor-card h-100">
                <div class="card-body vendor-metric">
                    <div>
                        <div class="vendor-metric-label">Purchase Orders</div>
                        <div class="vendor-metric-value">{{ $dashboardStats['purchase_orders'] }}</div>
                        <div class="text-muted small">{{ $dashboardStats['pending_reviews'] }} active item(s)</div>
                    </div>
                    <span class="vendor-metric-icon"><i class="feather-file-text"></i></span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card vendor-card h-100">
                <div class="card-body vendor-metric">
                    <div>
                        <div class="vendor-metric-label">Reports</div>
                        <div class="vendor-metric-value">{{ $dashboardStats['reports'] }}</div>
                        <div class="text-muted small">{{ $dashboardStats['documents'] }} document(s) in knowledge</div>
                    </div>
                    <span class="vendor-metric-icon"><i class="feather-clipboard"></i></span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card vendor-card h-100">
                <div class="card-body vendor-metric">
                    <div>
                        <div class="vendor-metric-label">Payments Received</div>
                        <div class="vendor-metric-value fs-5">{{ $formatMoney($dashboardStats['payments_received']) }}</div>
                        <div class="text-muted small">{{ $formatMoney($dashboardStats['invoice_amount']) }} invoiced</div>
                    </div>
                    <span class="vendor-metric-icon"><i class="feather-dollar-sign"></i></span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-8">
            <div class="card vendor-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                        <div>
                            <div class="vendor-eyebrow">Cashflow</div>
                            <h5 class="mb-0">Invoices vs Payments</h5>
                        </div>
                        <span class="badge-soft">{{ $periodLabel }}</span>
                    </div>
                    <div class="vendor-chart-box">
                        <canvas id="vendorCashflowChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card vendor-card h-100">
                <div class="card-body">
                    <div class="vendor-eyebrow">Workload</div>
                    <h5 class="mb-3">Portal Activity Mix</h5>
                    <div class="vendor-chart-box">
                        <canvas id="vendorStatusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card vendor-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                        <div>
                            <div class="vendor-eyebrow">Activity Trend</div>
                            <h5 class="mb-0">Submissions, Purchase Orders, Reports, and Documents</h5>
                        </div>
                        <div class="text-muted small">{{ $dashboardStats['reports'] }} report(s), {{ $dashboardStats['documents'] }} document(s)</div>
                    </div>
                    <div class="vendor-chart-box">
                        <canvas id="vendorActivityChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="vendor-flow-step">
                <span class="vendor-metric-icon mb-3"><i class="feather-file-text"></i></span>
                <h6 class="fw-bold">Purchase Orders</h6>
                <p class="text-muted small mb-3">Review assigned purchase orders and upload deliverable evidence documents.</p>
                <a href="{{ route('vendor.purchase-orders.index') }}" class="btn btn-vendor btn-sm w-100">Open Purchase Orders</a>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="vendor-flow-step">
                <span class="vendor-metric-icon mb-3"><i class="feather-dollar-sign"></i></span>
                <h6 class="fw-bold">Payments</h6>
                <p class="text-muted small mb-3">Review disbursements, payment evidence, and payment status from ATTP finance.</p>
                <a href="{{ route('vendor.payments.index') }}" class="btn btn-vendor-outline btn-sm w-100">View Payments</a>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="vendor-flow-step">
                <span class="vendor-metric-icon mb-3"><i class="feather-clipboard"></i></span>
                <h6 class="fw-bold">Reports</h6>
                <p class="text-muted small mb-3">Submit progress, completion, financial, deliverable, or incident reports.</p>
                <a href="{{ route('vendor.reports.create') }}" class="btn btn-vendor-outline btn-sm w-100">Submit Report</a>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="vendor-flow-step">
                <span class="vendor-metric-icon mb-3"><i class="feather-folder"></i></span>
                <h6 class="fw-bold">Knowledge Library</h6>
                <p class="text-muted small mb-3">Find every document submitted through forms, requests, reports, and uploads.</p>
                <a href="{{ route('vendor.knowledge.index') }}" class="btn btn-vendor-outline btn-sm w-100">Open Library</a>
            </div>
        </div>
        @if ($thinkTankMember)
            <div class="col-xl-3 col-md-6">
                <div class="vendor-flow-step">
                    <span class="vendor-metric-icon mb-3"><i class="feather-calendar"></i></span>
                    <h6 class="fw-bold">Work Plan</h6>
                    <p class="text-muted small mb-3">Review consortium workplans, budgets, progress, and recent report activity.</p>
                    <a href="{{ route('vendor.work-plan.index') }}" class="btn btn-vendor-outline btn-sm w-100">Open Work Plan</a>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="vendor-flow-step">
                    <span class="vendor-metric-icon mb-3"><i class="feather-book-open"></i></span>
                    <h6 class="fw-bold">Research Report</h6>
                    <p class="text-muted small mb-3">Submit and track research outputs connected to the think tank profile.</p>
                    <a href="{{ route('vendor.research-report.index') }}" class="btn btn-vendor-outline btn-sm w-100">Open Research</a>
                </div>
            </div>
        @endif
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-7">
            <div class="card vendor-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div>
                            <div class="vendor-eyebrow">Applications</div>
                            <h5 class="mb-0">Recent Procurement Applications</h5>
                        </div>
                        <a href="{{ route('vendor.submissions') }}" class="btn btn-vendor-outline btn-sm">View All</a>
                    </div>

                    @if ($submissions->isEmpty())
                        <div class="vendor-empty">
                            <span class="vendor-empty-icon mx-auto mb-3"><i class="feather-file-text"></i></span>
                            <p class="text-muted mb-0">No procurement applications in this period.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Reference</th>
                                        <th>Procurement</th>
                                        <th>Status</th>
                                        <th>Closes</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($submissions->take(6) as $submission)
                                        <tr>
                                            <td><span class="badge-soft">{{ $submission->procurement_reference ?? 'N/A' }}</span></td>
                                            <td>{{ $submission->procurement?->title ?? 'N/A' }}</td>
                                            <td><span class="status-pill">{{ ucfirst($submission->status ?? 'pending') }}</span></td>
                                            <td>{{ $submission->application_end_date ?? 'N/A' }}</td>
                                            <td class="text-end">
                                                @if ($submission->is_open)
                                                    <a href="{{ route('vendor.applications.edit', $submission) }}" class="btn btn-vendor btn-sm">Edit</a>
                                                @else
                                                    <span class="text-muted small">Locked</span>
                                                @endif
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

        <div class="col-xl-5">
            <div class="card vendor-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div>
                            <div class="vendor-eyebrow">Timeline</div>
                            <h5 class="mb-0">Recent Activity</h5>
                        </div>
                        <span class="badge-soft">{{ $recentActivity->count() }} updates</span>
                    </div>

                    @forelse ($recentActivity as $activity)
                        <div class="vendor-activity-item d-flex align-items-start gap-3 mb-2">
                            <span class="vendor-metric-icon"><i class="{{ $activity['icon'] }}"></i></span>
                            <div class="min-w-0">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="badge-soft">{{ $activity['type'] }}</span>
                                    <span class="text-muted small">{{ $activity['date']?->format('M d, Y') }}</span>
                                </div>
                                <div class="fw-semibold text-truncate">{{ $activity['title'] }}</div>
                                <div class="text-muted small text-truncate">{{ $activity['detail'] }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="vendor-empty">
                            <span class="vendor-empty-icon mx-auto mb-3"><i class="feather-clock"></i></span>
                            <p class="text-muted mb-0">No activity in this period.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-6">
            <div class="card vendor-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div>
                            <div class="vendor-eyebrow">Assigned Orders</div>
                            <h5 class="mb-0">Latest Purchase Orders</h5>
                        </div>
                        <a href="{{ route('vendor.purchase-orders.index') }}" class="btn btn-vendor-outline btn-sm">View All</a>
                    </div>

                    @forelse ($purchaseOrders->take(5) as $purchaseOrder)
                        <div class="vendor-file-row d-flex justify-content-between align-items-center gap-3 mb-2">
                            <div class="min-w-0">
                                <div class="fw-semibold text-truncate">{{ $purchaseOrder->po_title ?: 'Purchase Order' }}</div>
                                <div class="text-muted small">
                                    {{ $purchaseOrder->reference_no }} | {{ Str::headline($purchaseOrder->status) }}
                                </div>
                            </div>
                            <a href="{{ route('vendor.purchase-orders.show', $purchaseOrder) }}"
                                class="btn btn-sm btn-vendor-outline">Open</a>
                        </div>
                    @empty
                        <div class="vendor-empty">
                            <span class="vendor-empty-icon mx-auto mb-3"><i class="feather-file-text"></i></span>
                            <p class="text-muted mb-0">No purchase orders in this period.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card vendor-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div>
                            <div class="vendor-eyebrow">Reporting</div>
                            <h5 class="mb-0">Latest Submitted Reports</h5>
                        </div>
                        <a href="{{ route('vendor.reports.index') }}" class="btn btn-vendor-outline btn-sm">View All</a>
                    </div>

                    @forelse ($reports->take(5) as $report)
                        <div class="vendor-file-row d-flex justify-content-between align-items-center gap-3 mb-2">
                            <div class="min-w-0">
                                <div class="fw-semibold text-truncate">{{ $report->title }}</div>
                                <div class="text-muted small">
                                    {{ $report->reference_no }} | {{ Str::headline($report->status) }}
                                </div>
                            </div>
                            <a href="{{ route('vendor.reports.show', $report) }}" class="btn btn-sm btn-vendor-outline">Open</a>
                        </div>
                    @empty
                        <div class="vendor-empty">
                            <span class="vendor-empty-icon mx-auto mb-3"><i class="feather-clipboard"></i></span>
                            <p class="text-muted mb-0">No reports submitted in this period.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Chart === 'undefined') {
                return;
            }

            const statusChart = @json($statusChart);
            const cashflowChart = @json($cashflowChart);
            const activityChart = @json($activityChart);
            const palette = {
                green: '#006B3F',
                blue: '#3454D1',
                teal: '#0B5F74',
                amber: '#F59E0B',
                red: '#EF4444',
                slate: '#64748B'
            };

            const baseOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            usePointStyle: true,
                            boxWidth: 8
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#E2E8F0'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            };

            const cashflowNode = document.getElementById('vendorCashflowChart');
            if (cashflowNode) {
                new Chart(cashflowNode, {
                    type: 'line',
                    data: {
                        labels: cashflowChart.labels,
                        datasets: [
                            {
                                label: 'Invoices',
                                data: cashflowChart.invoices,
                                borderColor: palette.blue,
                                backgroundColor: 'rgba(52, 84, 209, .12)',
                                fill: true,
                                tension: .35
                            },
                            {
                                label: 'Payments',
                                data: cashflowChart.payments,
                                borderColor: palette.green,
                                backgroundColor: 'rgba(0, 107, 63, .12)',
                                fill: true,
                                tension: .35
                            }
                        ]
                    },
                    options: baseOptions
                });
            }

            const statusNode = document.getElementById('vendorStatusChart');
            if (statusNode) {
                new Chart(statusNode, {
                    type: 'doughnut',
                    data: {
                        labels: statusChart.labels,
                        datasets: [{
                            data: statusChart.data,
                            backgroundColor: [palette.green, palette.blue, palette.teal, palette.amber, palette.red],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    usePointStyle: true,
                                    boxWidth: 8
                                }
                            }
                        }
                    }
                });
            }

            const activityNode = document.getElementById('vendorActivityChart');
            if (activityNode) {
                new Chart(activityNode, {
                    type: 'bar',
                    data: {
                        labels: activityChart.labels,
                        datasets: [
                            {
                                label: 'Applications',
                                data: activityChart.applications,
                                backgroundColor: palette.green
                            },
                            {
                                label: 'Purchase Orders',
                                data: activityChart.requests,
                                backgroundColor: palette.blue
                            },
                            {
                                label: 'Reports',
                                data: activityChart.reports,
                                backgroundColor: palette.amber
                            },
                            {
                                label: 'Documents',
                                data: activityChart.documents,
                                backgroundColor: palette.teal
                            }
                        ]
                    },
                    options: baseOptions
                });
            }
        });
    </script>
@endpush
