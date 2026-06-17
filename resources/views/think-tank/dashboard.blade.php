@php
    $currency = $purchaseOrderRecords->first()?->resolved_currency ?? $member->consortium?->currency ?? 'USD';
    $isAdminView = auth()->user()?->isSuperAdmin() || auth()->user()?->isAdmin();
    $financePercent = min(100, max(0, (float) ($metrics['utilization'] ?? 0)));
    $receiptRate = min(100, max(0, (float) ($receiptSummary['rate'] ?? 0)));
    $poPaymentRate = (float) ($metrics['po_allocated'] ?? 0) > 0
        ? min(100, ((float) ($metrics['disbursed'] ?? 0) / (float) $metrics['po_allocated']) * 100)
        : 0;
    $resetParams = $isAdminView ? ['think_tank_member_id' => $member->id] : [];
@endphp

@push('styles')
    <style>
        .think-tank-workspace > .card.shadow-sm.border-0.overflow-hidden.mb-4 {
            display: none;
        }

        .tt-report-shell {
            display: grid;
            gap: 18px;
        }

        .tt-search-panel,
        .tt-report-hero,
        .tt-kpi-card,
        .tt-report-panel,
        .tt-chart-box {
            border: 1px solid #e2e8f0;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
        }

        .tt-search-panel {
            padding: 16px;
        }

        .tt-search-title {
            font-size: 17px;
            font-weight: 900;
            color: #0f172a;
            margin: 0;
        }

        .tt-search-subtitle {
            color: #64748b;
            font-size: 13px;
            margin: 4px 0 0;
        }

        .tt-search-grid {
            display: grid;
            grid-template-columns: minmax(260px, 1.4fr) minmax(150px, .7fr) minmax(150px, .7fr) minmax(145px, .7fr) minmax(145px, .7fr) auto;
            gap: 12px;
            align-items: end;
            margin-top: 14px;
        }

        .tt-field {
            display: grid;
            gap: 6px;
        }

        .tt-field label {
            color: #334155;
            font-size: 12px;
            font-weight: 850;
        }

        .tt-field input,
        .tt-field select {
            min-height: 42px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            color: #0f172a;
            background: #ffffff;
            padding: 9px 10px;
        }

        .tt-search-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .tt-report-hero {
            overflow: hidden;
            background:
                linear-gradient(120deg, rgba(15, 23, 42, .96), rgba(15, 118, 110, .9)),
                linear-gradient(45deg, rgba(245, 158, 11, .22), rgba(37, 99, 235, .14));
            color: #f8fafc;
            padding: 24px;
        }

        .tt-hero-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(280px, .7fr);
            gap: 18px;
            align-items: center;
        }

        .tt-kicker {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border-radius: 999px;
            border: 1px solid rgba(248, 250, 252, .3);
            color: #fde68a;
            background: rgba(248, 250, 252, .12);
            font-weight: 900;
            font-size: 12px;
            padding: 7px 11px;
        }

        .tt-report-hero h1 {
            color: #ffffff;
            font-size: 30px;
            font-weight: 900;
            line-height: 1.14;
            margin: 12px 0 8px;
        }

        .tt-report-hero p,
        .tt-hero-meta {
            color: rgba(248, 250, 252, .86);
        }

        .tt-hero-facts {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .tt-hero-fact {
            border: 1px solid rgba(248, 250, 252, .26);
            border-radius: 10px;
            padding: 12px;
            background: rgba(15, 23, 42, .2);
        }

        .tt-hero-fact span {
            display: block;
            color: rgba(248, 250, 252, .7);
            font-size: 12px;
            font-weight: 800;
        }

        .tt-hero-fact strong {
            color: #ffffff;
            font-size: 15px;
        }

        .tt-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .tt-kpi-card {
            padding: 16px;
            min-height: 132px;
        }

        .tt-kpi-icon {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: #dbeafe;
            color: #1d4ed8;
            margin-bottom: 12px;
        }

        .tt-kpi-card.green .tt-kpi-icon { background: #dcfce7; color: #166534; }
        .tt-kpi-card.amber .tt-kpi-icon { background: #fef3c7; color: #92400e; }
        .tt-kpi-card.teal .tt-kpi-icon { background: #ccfbf1; color: #0f766e; }

        .tt-kpi-value {
            color: #0f172a;
            font-size: 22px;
            font-weight: 900;
            line-height: 1.15;
            overflow-wrap: anywhere;
        }

        .tt-kpi-label {
            color: #64748b;
            font-size: 13px;
            font-weight: 750;
            margin-top: 6px;
        }

        .tt-progress {
            height: 9px;
            border-radius: 999px;
            overflow: hidden;
            background: #e2e8f0;
        }

        .tt-progress > span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #0ea5e9, #22c55e);
        }

        .tt-section-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.25fr) minmax(340px, .75fr);
            gap: 18px;
        }

        .tt-report-panel {
            padding: 18px;
        }

        .tt-panel-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }

        .tt-panel-head h2 {
            color: #0f172a;
            font-size: 18px;
            font-weight: 900;
            margin: 0;
        }

        .tt-panel-head p {
            color: #64748b;
            font-size: 13px;
            margin: 3px 0 0;
        }

        .tt-chart-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .tt-chart-box {
            padding: 14px;
            min-height: 305px;
            box-shadow: none;
            background: #fbfdff;
        }

        .tt-chart-box h3 {
            color: #334155;
            font-size: 14px;
            font-weight: 900;
            margin: 0 0 10px;
        }

        .tt-table-wrap {
            overflow-x: auto;
        }

        .tt-report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .tt-report-table th {
            background: #f1f5f9;
            color: #475569;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0;
            padding: 10px;
            border-bottom: 1px solid #cbd5e1;
            white-space: nowrap;
        }

        .tt-report-table td {
            padding: 11px 10px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .tt-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 4px 8px;
            background: #e0f2fe;
            color: #075985;
            font-size: 12px;
            font-weight: 850;
        }

        .tt-badge.good { background: #dcfce7; color: #166534; }
        .tt-badge.warn { background: #fef3c7; color: #92400e; }
        .tt-badge.danger { background: #fee2e2; color: #991b1b; }

        .tt-funded-list,
        .tt-status-list {
            display: grid;
            gap: 10px;
        }

        .tt-funded-row,
        .tt-status-row {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #ffffff;
            padding: 12px;
        }

        .tt-funded-title {
            color: #0f172a;
            font-weight: 900;
            margin-bottom: 5px;
        }

        .tt-funded-money {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            color: #64748b;
            font-size: 12px;
            margin: 10px 0;
        }

        .tt-funded-money strong {
            display: block;
            color: #0f172a;
            font-size: 13px;
        }

        .tt-finance-links {
            display: grid;
            gap: 5px;
            min-width: 180px;
        }

        .tt-finance-line {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
        }

        .tt-finance-chip {
            display: inline-flex;
            justify-content: center;
            min-width: 30px;
            border-radius: 999px;
            padding: 3px 7px;
            background: #e0f2fe;
            color: #0369a1;
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .tt-finance-line a {
            color: #0f766e;
            font-weight: 850;
            text-decoration: none;
        }

        .tt-finance-line a:hover {
            color: #0f172a;
            text-decoration: underline;
        }

        .tt-status-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .tt-empty {
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
            padding: 18px;
            color: #64748b;
            background: #f8fafc;
            text-align: center;
        }

        @media (max-width: 1200px) {
            .tt-search-grid,
            .tt-hero-grid,
            .tt-section-grid {
                grid-template-columns: 1fr;
            }

            .tt-kpi-grid,
            .tt-chart-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .tt-kpi-grid,
            .tt-chart-grid,
            .tt-hero-facts,
            .tt-funded-money {
                grid-template-columns: 1fr;
            }

            .tt-report-hero h1 {
                font-size: 24px;
            }
        }
    </style>
@endpush

<x-think-tank.partials.shell :member="$member" title="Think Tank Dashboard">
    <div class="tt-report-shell">
        <section class="tt-search-panel">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <h2 class="tt-search-title">Think Tank Report Search</h2>
                    <p class="tt-search-subtitle">Select a think tank and run the search to generate the full operational report.</p>
                </div>
                <a class="btn btn-dark fw-bold" href="{{ route('think-tank.dashboard.download', $dashboardQueryParams) }}">
                    <i class="feather-download me-1"></i> Download Report
                </a>
            </div>

            <form method="GET" action="{{ route('think-tank.dashboard') }}">
                <div class="tt-search-grid">
                    <div class="tt-field">
                        <label for="think_tank_member_id">Think tank</label>
                        @if($isAdminView)
                            <select id="think_tank_member_id" name="think_tank_member_id" required>
                                @foreach($membersForSearch as $searchMember)
                                    <option value="{{ $searchMember->id }}" @selected((string) $member->id === (string) $searchMember->id)>
                                        {{ $searchMember->name }}{{ $searchMember->consortium ? ' - ' . $searchMember->consortium->name : '' }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <input value="{{ $member->name }}" readonly>
                        @endif
                    </div>

                    <div class="tt-field">
                        <label for="filter_month">Month</label>
                        <input id="filter_month" type="month" name="filter_month" value="{{ $dashboardFilter['month'] }}">
                    </div>
                    <div class="tt-field">
                        <label for="filter_year">Year</label>
                        <select id="filter_year" name="filter_year">
                            <option value="">All years</option>
                            @foreach($dashboardFilter['year_options'] as $year)
                                <option value="{{ $year }}" @selected((string) $dashboardFilter['year'] === (string) $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="tt-field">
                        <label for="date_from">From</label>
                        <input id="date_from" type="date" name="date_from" value="{{ $dashboardFilter['date_from'] }}">
                    </div>
                    <div class="tt-field">
                        <label for="date_to">To</label>
                        <input id="date_to" type="date" name="date_to" value="{{ $dashboardFilter['date_to'] }}">
                    </div>
                    <div class="tt-search-actions">
                        <button class="btn btn-primary fw-bold" type="submit">
                            <i class="feather-search me-1"></i> Run Search
                        </button>
                        <a class="btn btn-light border fw-bold" href="{{ route('think-tank.dashboard', $resetParams) }}">Reset</a>
                    </div>
                </div>
            </form>
        </section>

        <section class="tt-report-hero">
            <div class="tt-hero-grid">
                <div>
                    <span class="tt-kicker"><i class="feather-activity"></i> {{ $dashboardFilter['label'] }} report</span>
                    <h1>{{ $member->name }}</h1>
                    <p class="mb-2">
                        {{ $member->consortium?->name ?? 'Consortium not linked' }}
                        {{ $member->country ? ' / ' . $member->country : '' }}
                    </p>
                    <div class="tt-hero-meta">
                        Funding, receipt confirmation, reports, research, procurement, and activity utilisation are generated from live system records.
                    </div>
                </div>
                <div class="tt-hero-facts">
                    <div class="tt-hero-fact">
                        <span>Consortium</span>
                        <strong>{{ $member->consortium?->name ?? 'Not linked' }}</strong>
                    </div>
                    <div class="tt-hero-fact">
                        <span>Status</span>
                        <strong>{{ ucfirst($member->status ?? 'active') }}</strong>
                    </div>
                    <div class="tt-hero-fact">
                        <span>Role</span>
                        <strong>{{ ucfirst(str_replace('_', ' ', $member->role ?? 'member')) }}</strong>
                    </div>
                    <div class="tt-hero-fact">
                        <span>Generated</span>
                        <strong>{{ now()->format('M d, Y H:i') }}</strong>
                    </div>
                </div>
            </div>
        </section>

        <section class="tt-kpi-grid">
            <article class="tt-kpi-card">
                <span class="tt-kpi-icon"><i class="feather-credit-card"></i></span>
                <div class="tt-kpi-value">{{ $currency }} {{ number_format($metrics['po_allocated'], 2) }}</div>
                <div class="tt-kpi-label">PO allocated by ATTP</div>
            </article>
            <article class="tt-kpi-card green">
                <span class="tt-kpi-icon"><i class="feather-check-circle"></i></span>
                <div class="tt-kpi-value">{{ $currency }} {{ number_format($metrics['disbursed'], 2) }}</div>
                <div class="tt-kpi-label">Actually paid from POs</div>
            </article>
            <article class="tt-kpi-card amber">
                <span class="tt-kpi-icon"><i class="feather-trending-up"></i></span>
                <div class="tt-kpi-value">{{ $currency }} {{ number_format($metrics['po_unpaid'], 2) }}</div>
                <div class="tt-kpi-label">PO unpaid balance | {{ number_format($poPaymentRate, 1) }}% paid</div>
            </article>
            <article class="tt-kpi-card teal">
                <span class="tt-kpi-icon"><i class="feather-file-text"></i></span>
                <div class="tt-kpi-value">{{ $currency }} {{ number_format($receiptSummary['confirmed'], 2) }}</div>
                <div class="tt-kpi-label">Payment receipt confirmed</div>
            </article>
        </section>

        <section class="tt-section-grid">
            <div>
                <div class="tt-report-panel">
                    <div class="tt-panel-head">
                        <div>
                            <h2>Graphs and Analysis</h2>
                            <p>Financial, receipt, reporting, procurement, and research patterns for the selected think tank.</p>
                        </div>
                    </div>
                    <div class="tt-chart-grid">
                        <div class="tt-chart-box">
                            <h3>Financial Position</h3>
                            <div id="ttFinanceChart"></div>
                        </div>
                        <div class="tt-chart-box">
                            <h3>Paid vs Receipt</h3>
                            <div id="ttReceiptChart"></div>
                        </div>
                        <div class="tt-chart-box">
                            <h3>Reports Submitted Over 6 Months</h3>
                            <div id="ttReportsChart"></div>
                        </div>
                        <div class="tt-chart-box">
                            <h3>Delivery Mix</h3>
                            <div id="ttDeliveryChart"></div>
                        </div>
                    </div>
                </div>

                <div class="tt-report-panel">
                    <div class="tt-panel-head">
                        <div>
                            <h2>Linked Purchase Order Ledger</h2>
                            <p>Approved funding POs, their source PRs, actual payments, and unpaid balances.</p>
                        </div>
                        <a class="btn btn-sm btn-light border" href="{{ route('think-tank.purchase-orders', $portalRouteParams) }}">Open POs</a>
                    </div>
                    <div class="tt-table-wrap">
                        <table class="tt-report-table">
                            <thead>
                            <tr>
                                <th>Purchase Order</th>
                                <th>Purchase Request</th>
                                <th>PO Amount</th>
                                <th>Paid</th>
                                <th>Unpaid</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($purchaseOrderRecords as $purchaseOrder)
                                @php
                                    $purchaseRequest = $purchaseOrder->purchaseRequest ?: $purchaseOrder->budgetCommitment?->purchaseRequest;
                                    $paidAmount = (float) $purchaseOrder->disbursements->sum('amount');
                                    $unpaidAmount = max((float) $purchaseOrder->amount - $paidAmount, 0);
                                    $portalPoUrl = route('think-tank.purchase-orders.show', array_merge($portalRouteParams, ['purchaseOrder' => $purchaseOrder]));
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ $isAdminView ? route('procurement.purchase-orders.show', $purchaseOrder) : $portalPoUrl }}" class="fw-bold">
                                            {{ $purchaseOrder->reference_no ?: 'Purchase Order' }}
                                        </a>
                                        <div class="text-muted small">{{ $purchaseOrder->issued_at?->format('M d, Y') ?? 'No issued date' }}</div>
                                    </td>
                                    <td>
                                        @if($purchaseRequest)
                                            @if($isAdminView)
                                                <a href="{{ route('finance.purchase-requests.show', $purchaseRequest) }}" class="fw-bold">{{ $purchaseRequest->reference_no ?: 'Purchase Request' }}</a>
                                            @else
                                                <strong>{{ $purchaseRequest->reference_no ?: 'Purchase Request' }}</strong>
                                            @endif
                                            <div class="text-muted small">{{ \Illuminate\Support\Str::limit($purchaseRequest->description, 70) }}</div>
                                        @else
                                            <span class="text-muted small">No linked purchase request</span>
                                        @endif
                                    </td>
                                    <td>{{ $purchaseOrder->resolved_currency ?? $currency }} {{ number_format((float) $purchaseOrder->amount, 2) }}</td>
                                    <td>{{ $purchaseOrder->resolved_currency ?? $currency }} {{ number_format($paidAmount, 2) }}</td>
                                    <td>{{ $purchaseOrder->resolved_currency ?? $currency }} {{ number_format($unpaidAmount, 2) }}</td>
                                    <td><span class="tt-badge">{{ ucfirst(str_replace('_', ' ', $purchaseOrder->status ?? 'pending')) }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><div class="tt-empty">No linked funding purchase orders found for this selected period.</div></td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tt-report-panel">
                    <div class="tt-panel-head">
                        <div>
                            <h2>Paid Disbursement Register</h2>
                            <p>Paid disbursements linked to purchase orders, source purchase requests, and receipt confirmation.</p>
                        </div>
                    </div>
                    <div class="tt-table-wrap">
                        <table class="tt-report-table">
                            <thead>
                            <tr>
                                <th>Disbursement</th>
                                <th>PO / PR</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Receipt</th>
                                <th>Notes</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($transferRecords as $transfer)
                                @php
                                    $receiptStatus = $transfer->recipient_confirmation_status ?: 'pending';
                                    $purchaseOrder = $transfer->purchaseOrder;
                                    $purchaseRequest = $purchaseOrder?->purchaseRequest ?: $purchaseOrder?->budgetCommitment?->purchaseRequest;
                                @endphp
                                <tr>
                                    <td>
                                        @if($isAdminView)
                                            <a href="{{ route('procurement.disbursements.show', $transfer) }}" class="fw-bold">{{ $transfer->transfer_reference ?: $transfer->reference_no }}</a>
                                        @else
                                            <strong>{{ $transfer->transfer_reference ?: $transfer->reference_no }}</strong>
                                        @endif
                                        <div class="text-muted small">{{ $transfer->reference_no }}</div>
                                    </td>
                                    <td>
                                        <div class="tt-finance-links">
                                            <div class="tt-finance-line">
                                                <span class="tt-finance-chip">PO</span>
                                                @if($purchaseOrder)
                                                    @php $portalPoUrl = route('think-tank.purchase-orders.show', array_merge($portalRouteParams, ['purchaseOrder' => $purchaseOrder])); @endphp
                                                    <a href="{{ $isAdminView ? route('procurement.purchase-orders.show', $purchaseOrder) : $portalPoUrl }}">{{ $purchaseOrder->reference_no ?: 'Purchase Order' }}</a>
                                                @else
                                                    <span class="text-muted small">No PO</span>
                                                @endif
                                            </div>
                                            <div class="tt-finance-line">
                                                <span class="tt-finance-chip">PR</span>
                                                @if($purchaseRequest)
                                                    @if($isAdminView)
                                                        <a href="{{ route('finance.purchase-requests.show', $purchaseRequest) }}">{{ $purchaseRequest->reference_no ?: 'Purchase Request' }}</a>
                                                    @else
                                                        <span>{{ $purchaseRequest->reference_no ?: 'Purchase Request' }}</span>
                                                    @endif
                                                @else
                                                    <span class="text-muted small">No PR</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $transfer->paid_at?->format('M d, Y') ?? $transfer->created_at?->format('M d, Y') }}</td>
                                    <td>{{ $currency }} {{ number_format((float) $transfer->amount, 2) }}</td>
                                    <td>
                                        <span class="tt-badge {{ $receiptStatus === 'confirmed' ? 'good' : 'warn' }}">
                                            {{ ucfirst(str_replace('_', ' ', $receiptStatus)) }}
                                        </span>
                                    </td>
                                    <td>{{ \Illuminate\Support\Str::limit($transfer->notes ?: $transfer->recipient_confirmation_notes ?: 'No notes', 80) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><div class="tt-empty">No paid disbursements found for this selected period.</div></td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tt-report-panel">
                    <div class="tt-panel-head">
                        <div>
                            <h2>Recent Activity Reports</h2>
                            <p>Latest report submissions and Secretariat review status.</p>
                        </div>
                        <a class="btn btn-sm btn-light border" href="{{ route('think-tank.reports', $portalRouteParams) }}">Open Reports</a>
                    </div>
                    <div class="tt-table-wrap">
                        <table class="tt-report-table">
                            <thead><tr><th>Report</th><th>Period</th><th>Progress</th><th>Status</th></tr></thead>
                            <tbody>
                            @forelse($recentReports as $report)
                                <tr>
                                    <td>{{ $report->title }}</td>
                                    <td>{{ $report->reporting_period_start?->format('M d, Y') ?? '-' }} - {{ $report->reporting_period_end?->format('M d, Y') ?? '-' }}</td>
                                    <td>{{ number_format((float) $report->progress_percent, 1) }}%</td>
                                    <td><span class="tt-badge">{{ ucfirst(str_replace('_', ' ', $report->status)) }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><div class="tt-empty">No activity reports found for this selected period.</div></td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tt-report-panel">
                    <div class="tt-panel-head">
                        <div>
                            <h2>Procurement Opportunities</h2>
                            <p>Open and recent procurement records created by this think tank.</p>
                        </div>
                        <a class="btn btn-sm btn-primary" href="{{ route('think-tank.procurement', $portalRouteParams) }}">Open Procurement</a>
                    </div>
                    <div class="tt-table-wrap">
                        <table class="tt-report-table">
                            <thead><tr><th>Title</th><th>Status</th><th>Applications</th><th>Closing</th><th></th></tr></thead>
                            <tbody>
                            @forelse($recentProcurements as $procurement)
                                <tr>
                                    <td>{{ $procurement->title }}</td>
                                    <td><span class="tt-badge">{{ ucfirst($procurement->status) }}</span></td>
                                    <td>{{ number_format($procurement->submissions_count) }}</td>
                                    <td>{{ $procurement->application_end_date?->format('M d, Y') ?? 'N/A' }}</td>
                                    <td>
                                        <a class="btn btn-sm btn-light border" href="{{ route('think-tank.procurement.submissions', array_merge($portalRouteParams, ['procurement' => $procurement])) }}">
                                            Evaluate
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><div class="tt-empty">No procurement opportunities found for this selected period.</div></td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <aside>
                <div class="tt-report-panel">
                    <div class="tt-panel-head">
                        <div>
                            <h2>Receipt Confirmation</h2>
                            <p>Comparison of paid disbursements and bank receipt confirmations.</p>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between small fw-bold mb-2">
                        <span>Confirmed receipts</span>
                        <span>{{ number_format($receiptRate, 1) }}%</span>
                    </div>
                    <div class="tt-progress mb-3"><span style="width: {{ $receiptRate }}%"></span></div>
                    <div class="tt-status-list">
                        <div class="tt-status-row">
                            <span>Total paid from POs</span>
                            <strong>{{ $currency }} {{ number_format($receiptSummary['sent'], 2) }}</strong>
                        </div>
                        <div class="tt-status-row">
                            <span>Confirmed in bank</span>
                            <strong>{{ $currency }} {{ number_format($receiptSummary['confirmed'], 2) }}</strong>
                        </div>
                        <div class="tt-status-row">
                            <span>Awaiting confirmation</span>
                            <strong>{{ $currency }} {{ number_format($receiptSummary['pending'], 2) }}</strong>
                        </div>
                    </div>
                </div>

                <div class="tt-report-panel">
                    <div class="tt-panel-head">
                        <div>
                            <h2>Funded Activities</h2>
                            <p>Budget lines funded through Secretariat disbursements.</p>
                        </div>
                    </div>
                    <div class="tt-funded-list">
                        @forelse($fundedActivities as $activity)
                            <article class="tt-funded-row">
                                <div class="tt-funded-title">{{ $activity['budget_line'] }}</div>
                                <div class="text-muted small">{{ number_format($activity['utilization'], 1) }}% of disbursed funds reported as spent.</div>
                                <div class="tt-funded-money">
                                    <span>Allocated <strong>{{ $currency }} {{ number_format($activity['allocated'], 2) }}</strong></span>
                                    <span>Disbursed <strong>{{ $currency }} {{ number_format($activity['disbursed'], 2) }}</strong></span>
                                    <span>Spent <strong>{{ $currency }} {{ number_format($activity['spent'], 2) }}</strong></span>
                                </div>
                                <div class="tt-progress"><span style="width: {{ $activity['utilization'] }}%"></span></div>
                            </article>
                        @empty
                            <div class="tt-empty">No funded activities found for this selected period.</div>
                        @endforelse
                    </div>
                </div>

                <div class="tt-report-panel">
                    <div class="tt-panel-head">
                        <div>
                            <h2>Report Status</h2>
                            <p>Review state of submitted reports.</p>
                        </div>
                    </div>
                    <div class="tt-status-list">
                        @forelse($reportStatusCounts as $status => $count)
                            <div class="tt-status-row">
                                <span>{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                                <strong>{{ number_format($count) }}</strong>
                            </div>
                        @empty
                            <div class="tt-empty">No report status data yet.</div>
                        @endforelse
                    </div>
                </div>

                <div class="tt-report-panel">
                    <div class="tt-panel-head">
                        <div>
                            <h2>Recent Research</h2>
                            <p>Latest research outputs submitted to the Secretariat.</p>
                        </div>
                    </div>
                    <div class="tt-table-wrap">
                        <table class="tt-report-table">
                            <thead><tr><th>Title</th><th>Type</th><th>Status</th></tr></thead>
                            <tbody>
                            @forelse($recentResearch as $output)
                                <tr>
                                    <td>{{ $output->title }}</td>
                                    <td>{{ str_replace('_', ' ', ucfirst($output->output_type)) }}</td>
                                    <td><span class="tt-badge">{{ ucfirst($output->status) }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="3"><div class="tt-empty">No research outputs found for this selected period.</div></td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </aside>
        </section>
    </div>
</x-think-tank.partials.shell>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof ApexCharts === 'undefined') {
                return;
            }

            const chartData = @json($chartData);
            const reportCurrency = @json($currency);
            const money = (value) => reportCurrency + ' ' + Number(value || 0).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            const baseOptions = {
                chart: { toolbar: { show: false }, fontFamily: 'Inter, Arial, sans-serif' },
                dataLabels: { enabled: false },
                colors: ['#2563eb', '#16a34a', '#f59e0b', '#0f766e'],
                grid: { borderColor: '#e2e8f0' },
                legend: { position: 'bottom' }
            };

            new ApexCharts(document.querySelector('#ttFinanceChart'), {
                ...baseOptions,
                chart: { ...baseOptions.chart, type: 'bar', height: 250 },
                series: [{ name: 'Amount', data: chartData.finance.values }],
                xaxis: { categories: chartData.finance.labels },
                yaxis: { labels: { formatter: (value) => Number(value).toLocaleString() } },
                tooltip: { y: { formatter: money } },
                plotOptions: { bar: { borderRadius: 5, columnWidth: '48%' } }
            }).render();

            new ApexCharts(document.querySelector('#ttReceiptChart'), {
                ...baseOptions,
                chart: { ...baseOptions.chart, type: 'donut', height: 250 },
                colors: ['#22c55e', '#f59e0b'],
                series: chartData.receipts.values,
                labels: chartData.receipts.labels,
                tooltip: { y: { formatter: money } }
            }).render();

            new ApexCharts(document.querySelector('#ttReportsChart'), {
                ...baseOptions,
                chart: { ...baseOptions.chart, type: 'area', height: 250 },
                stroke: { curve: 'smooth', width: 3 },
                fill: { opacity: .18 },
                series: [{ name: 'Reports', data: chartData.reports.values }],
                xaxis: { categories: chartData.reports.labels }
            }).render();

            new ApexCharts(document.querySelector('#ttDeliveryChart'), {
                ...baseOptions,
                chart: { ...baseOptions.chart, type: 'bar', height: 250 },
                series: [{ name: 'Records', data: chartData.delivery.values }],
                xaxis: { categories: chartData.delivery.labels },
                plotOptions: { bar: { horizontal: true, borderRadius: 5 } }
            }).render();
        });
    </script>
@endpush
