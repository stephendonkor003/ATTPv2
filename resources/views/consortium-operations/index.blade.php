@extends('layouts.app')

@section('title', 'Consortium Operations')

@push('styles')
    <style>
        .ops-hero {
            background:
                linear-gradient(125deg, rgba(15, 23, 42, 0.94), rgba(13, 148, 136, 0.9)),
                linear-gradient(45deg, rgba(245, 158, 11, 0.18), rgba(37, 99, 235, 0.16));
            border-radius: 16px;
            color: #f8fafc;
            padding: 1.25rem;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.18);
        }

        .ops-hero .ops-kicker {
            color: #fde68a;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 0.74rem;
            letter-spacing: 0;
        }

        .ops-hero p {
            color: rgba(248, 250, 252, 0.88);
        }

        .ops-action-btn {
            border: 1px solid rgba(248, 250, 252, 0.42);
            color: #f8fafc;
            background: rgba(248, 250, 252, 0.12);
            font-weight: 700;
        }

        .ops-action-btn:hover {
            background: #f8fafc;
            color: #0f172a;
        }

        .ops-summary-tile,
        .consortium-card,
        .ops-analysis-shell,
        .ops-filter-panel {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
        }

        .ops-summary-tile {
            padding: 1rem;
            height: 100%;
        }

        .ops-summary-icon {
            width: 42px;
            height: 42px;
            border-radius: 11px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #0f172a;
            background: #d9f99d;
        }

        .ops-summary-icon.blue {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .ops-summary-icon.amber {
            background: #fef3c7;
            color: #92400e;
        }

        .ops-summary-icon.green {
            background: #dcfce7;
            color: #166534;
        }

        .ops-summary-label,
        .consortium-meta-label {
            color: #64748b;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0;
        }

        .ops-tabs {
            gap: 0.5rem;
            border-bottom: 0;
            padding: 0.35rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }

        .ops-tabs .nav-link {
            border: 0;
            border-radius: 9px;
            color: #475569;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
        }

        .ops-tabs .nav-link.active {
            background: #0f172a;
            color: #ffffff;
        }

        .ops-filter-panel {
            padding: 1rem;
        }

        .consortium-card {
            padding: 1.1rem;
            height: 100%;
            overflow: hidden;
            position: relative;
        }

        .consortium-card::before {
            content: "";
            position: absolute;
            inset: 0 0 auto 0;
            height: 5px;
            background: linear-gradient(90deg, #0f766e, #2563eb, #f59e0b);
        }

        .consortium-code {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.32rem 0.62rem;
            border-radius: 999px;
            background: #f1f5f9;
            color: #334155;
            font-size: 0.74rem;
            font-weight: 800;
        }

        .consortium-status {
            border-radius: 999px;
            padding: 0.32rem 0.62rem;
            font-size: 0.74rem;
            font-weight: 800;
            background: #dcfce7;
            color: #166534;
        }

        .consortium-status.paused {
            background: #fef3c7;
            color: #92400e;
        }

        .consortium-status.closed {
            background: #fee2e2;
            color: #991b1b;
        }

        .metric-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
        }

        .metric-box {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.8rem;
            background: #f8fafc;
            min-height: 92px;
        }

        .metric-value {
            color: #0f172a;
            font-size: 1.08rem;
            font-weight: 900;
            line-height: 1.2;
            overflow-wrap: anywhere;
        }

        .receipt-track {
            height: 9px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
        }

        .receipt-track > span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #22c55e, #0ea5e9);
        }

        .ops-analysis-shell {
            padding: 1rem;
        }

        .analysis-mode-btn {
            border: 1px solid #cbd5e1;
            color: #334155;
            background: #ffffff;
            border-radius: 9px;
            font-weight: 800;
            padding: 0.45rem 0.75rem;
        }

        .analysis-mode-btn.active {
            color: #ffffff;
            border-color: #0f172a;
            background: #0f172a;
        }

        .analysis-control-panel {
            border: 1px solid #dbeafe;
            border-radius: 14px;
            background: linear-gradient(180deg, #f8fafc, #ffffff);
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .analysis-control-grid {
            display: grid;
            grid-template-columns: minmax(180px, .8fr) minmax(150px, .6fr) minmax(260px, 1.4fr) minmax(155px, .7fr) minmax(155px, .7fr);
            gap: 0.8rem;
            align-items: end;
        }

        .analysis-field {
            display: grid;
            gap: 0.35rem;
        }

        .analysis-field label {
            color: #334155;
            font-size: 0.74rem;
            font-weight: 850;
            text-transform: uppercase;
        }

        .analysis-field select,
        .analysis-field input {
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            color: #0f172a;
            min-height: 42px;
        }

        .analysis-select {
            min-height: 42px;
        }

        .analysis-select[multiple] {
            min-height: 146px;
            padding: 0.45rem;
        }

        .analysis-select option {
            padding: 0.45rem 0.55rem;
        }

        .analysis-chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.85rem;
        }

        .analysis-chip {
            border-radius: 999px;
            background: #e0f2fe;
            color: #075985;
            border: 1px solid #bae6fd;
            padding: 0.35rem 0.6rem;
            font-size: 0.75rem;
            font-weight: 850;
        }

        .analysis-chip.warning {
            background: #fef3c7;
            color: #92400e;
            border-color: #fde68a;
        }

        .analysis-sheet {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            overflow: hidden;
            background: #ffffff;
            margin-top: 1rem;
        }

        .analysis-sheet-header {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: center;
            padding: 0.9rem 1rem;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .analysis-sheet table {
            margin-bottom: 0;
        }

        .analysis-sheet th {
            color: #475569;
            font-size: 0.74rem;
            text-transform: uppercase;
            background: #ffffff;
        }

        .chart-frame {
            min-height: 390px;
        }

        .empty-state {
            border: 1px dashed #cbd5e1;
            border-radius: 14px;
            padding: 2rem;
            color: #64748b;
            background: #f8fafc;
        }

        @media (max-width: 767.98px) {
            .metric-grid {
                grid-template-columns: 1fr;
            }

            .ops-hero {
                padding: 1rem;
            }

            .analysis-control-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 1200px) {
            .analysis-control-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .analysis-control-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <div class="nxl-container">
        @php
            $firstConsortium = $consortia->first();
            $receiptRate = ($summary['funds_disbursed'] ?? 0) > 0
                ? min(100, (($summary['funds_receipted'] ?? 0) / $summary['funds_disbursed']) * 100)
                : 0;
        @endphp

        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-users text-warning me-2"></i>
                    Consortium Operations
                </h4>
                <p class="mb-0">Funding receipts, think tank coverage, reporting movement, and consortium comparison.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                @can('think_tanks.funding.view')
                    <a href="{{ route('think-tanks-admin.funding') }}" class="btn btn-light btn-sm">
                        <i class="feather-credit-card me-1"></i> Funding Dashboard
                    </a>
                @endcan
                @can('consortiums.manage')
                    <button type="button" class="btn btn-warning btn-sm fw-bold text-dark" data-bs-toggle="modal" data-bs-target="#createConsortiumModal">
                        <i class="feather-plus me-1"></i> New Consortium
                    </button>
                @endcan
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <ul class="nav nav-tabs attp-management-tabs mb-4">
            <li class="nav-item"><a class="nav-link active" aria-current="page" href="{{ route('consortium-operations.index') }}">Consortium Operations</a></li>
            @can('consortiums.view')
                @if ($firstConsortium)
                    <li class="nav-item"><a class="nav-link" href="{{ route('consortium-operations.show', $firstConsortium) }}">Think Tank Portal Oversight</a></li>
                @elseif ($firstPortalMember && Route::has('think-tank.dashboard'))
                    <li class="nav-item"><a class="nav-link" href="{{ route('think-tank.dashboard', ['think_tank_member_id' => $firstPortalMember->id]) }}">Think Tank Portal Oversight</a></li>
                @else
                    <li class="nav-item"><span class="nav-link disabled">Think Tank Portal Oversight</span></li>
                @endif
            @else
                <li class="nav-item"><span class="nav-link disabled">Think Tank Portal Oversight</span></li>
            @endcan
        </ul>

        <div class="ops-hero mb-4">
            <div class="row g-3 align-items-center">
                <div class="col-lg-8">
                    <div class="ops-kicker mb-2">Secretariat visibility</div>
                    <h4 class="fw-bold text-white mb-2">Track what each consortium received, confirmed, and reported.</h4>
                    <p class="mb-0">
                        Each card shows the funds sent to think tanks, payment receipt confirmation, number of supported think tanks, and report outcomes.
                    </p>
                </div>
                <div class="col-lg-4">
                    <div class="d-flex gap-2 justify-content-lg-end flex-wrap">
                        @can('think_tanks.funding.history.view')
                            <a class="btn ops-action-btn btn-sm" href="{{ route('think-tanks-admin.funding.history') }}">
                                <i class="feather-clock me-1"></i> Transfer History
                            </a>
                        @endcan
                        @can('think_tanks.directory.view')
                            <a class="btn ops-action-btn btn-sm" href="{{ route('think-tanks-admin.directory') }}">
                                <i class="feather-list me-1"></i> Think Tank Directory
                            </a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6 col-xl-3">
                <div class="ops-summary-tile">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="ops-summary-label mb-1">Consortia</div>
                            <h3 class="fw-bold mb-0">{{ number_format($summary['consortia']) }}</h3>
                        </div>
                        <span class="ops-summary-icon blue"><i class="feather-grid"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="ops-summary-tile">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="ops-summary-label mb-1">Think Tanks</div>
                            <h3 class="fw-bold mb-0">{{ number_format($summary['think_tanks']) }}</h3>
                        </div>
                        <span class="ops-summary-icon"><i class="feather-users"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="ops-summary-tile">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="ops-summary-label mb-1">Funds Sent</div>
                            <h5 class="fw-bold mb-0">USD {{ number_format($summary['funds_disbursed'], 2) }}</h5>
                        </div>
                        <span class="ops-summary-icon amber"><i class="feather-dollar-sign"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="ops-summary-tile">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="ops-summary-label mb-1">Receipts Confirmed</div>
                            <h5 class="fw-bold mb-1">USD {{ number_format($summary['funds_receipted'], 2) }}</h5>
                            <div class="small text-muted">{{ number_format($receiptRate, 1) }}% of sent funds</div>
                        </div>
                        <span class="ops-summary-icon green"><i class="feather-check-circle"></i></span>
                    </div>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs ops-tabs mb-4" id="consortiumOpsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="cards-tab" data-bs-toggle="tab" data-bs-target="#cards-pane" type="button" role="tab" aria-controls="cards-pane" aria-selected="true">
                    <i class="feather-layers"></i> Consortium Cards
                </button>
            </li>
            @can('consortiums.analysis.view')
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="analysis-tab" data-bs-toggle="tab" data-bs-target="#analysis-pane" type="button" role="tab" aria-controls="analysis-pane" aria-selected="false">
                        <i class="feather-bar-chart-2"></i> Graphical Components
                    </button>
                </li>
            @endcan
        </ul>

        <div class="tab-content" id="consortiumOpsTabContent">
            <div class="tab-pane fade show active" id="cards-pane" role="tabpanel" aria-labelledby="cards-tab" tabindex="0">
                <div class="ops-filter-panel mb-4">
                    <form class="row g-2 align-items-center" method="GET">
                        <div class="col-lg-7">
                            <input class="form-control" name="q" value="{{ request('q') }}" placeholder="Search by consortium, code, country, or region">
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <select class="form-select" name="status">
                                <option value="">All statuses</option>
                                @foreach (['active', 'paused', 'closed'] as $status)
                                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 col-lg-2 d-grid">
                            <button class="btn btn-outline-primary fw-bold" type="submit">
                                <i class="feather-filter me-1"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>

                <div class="row g-3">
                    @forelse ($consortia as $consortium)
                        @php
                            $sent = (float) ($consortium->transferred_amount ?? 0);
                            $received = (float) ($consortium->receipted_amount ?? 0);
                            $cardReceiptRate = $sent > 0 ? min(100, ($received / $sent) * 100) : 0;
                            $pendingTransfers = max(0, (int) ($consortium->transfer_count ?? 0) - (int) ($consortium->confirmed_transfer_count ?? 0));
                            $statusClass = in_array($consortium->status, ['paused', 'closed'], true) ? $consortium->status : 'active';
                        @endphp
                        <div class="col-xl-4 col-lg-6">
                            <div class="consortium-card">
                                <div class="d-flex justify-content-between gap-2 align-items-start mb-3">
                                    <div>
                                        <span class="consortium-code mb-2">
                                            <i class="feather-hash"></i> {{ $consortium->code ?: 'NO CODE' }}
                                        </span>
                                        <h5 class="fw-bold mb-1">{{ $consortium->name }}</h5>
                                        <div class="text-muted small">
                                            {{ $consortium->funder?->name ?? 'Partner not linked' }}
                                        </div>
                                    </div>
                                    <span class="consortium-status {{ $statusClass }}">{{ ucfirst($consortium->status ?? 'active') }}</span>
                                </div>

                                <div class="metric-grid mb-3">
                                    <div class="metric-box">
                                        <div class="consortium-meta-label mb-1">Think Tanks</div>
                                        <div class="metric-value">{{ number_format($consortium->members_count) }}</div>
                                        <div class="small text-muted">Supported members</div>
                                    </div>
                                    <div class="metric-box">
                                        <div class="consortium-meta-label mb-1">Funds Sent</div>
                                        <div class="metric-value">USD {{ number_format($sent, 2) }}</div>
                                        <div class="small text-muted">{{ number_format($consortium->transfer_count ?? 0) }} transfer(s)</div>
                                    </div>
                                    <div class="metric-box">
                                        <div class="consortium-meta-label mb-1">Payment Receipt</div>
                                        <div class="metric-value">USD {{ number_format($received, 2) }}</div>
                                        <div class="small text-muted">{{ number_format($consortium->confirmed_transfer_count ?? 0) }} confirmed</div>
                                    </div>
                                    <div class="metric-box">
                                        <div class="consortium-meta-label mb-1">Reports</div>
                                        <div class="metric-value">{{ number_format($consortium->reports_total_count ?? 0) }}</div>
                                        <div class="small text-muted">
                                            {{ number_format($consortium->reports_approved_count ?? 0) }} approved,
                                            {{ number_format($consortium->reports_rejected_count ?? 0) }} rejected/revision
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="d-flex justify-content-between small fw-bold mb-1">
                                        <span>Receipt confirmation</span>
                                        <span>{{ number_format($cardReceiptRate, 1) }}%</span>
                                    </div>
                                    <div class="receipt-track" aria-label="Receipt confirmation progress">
                                        <span style="width: {{ number_format($cardReceiptRate, 2, '.', '') }}%"></span>
                                    </div>
                                    <div class="small text-muted mt-1">{{ number_format($pendingTransfers) }} transfer(s) awaiting receipt confirmation</div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center gap-2">
                                    <div class="small text-muted">
                                        {{ $consortium->country ?: 'Country not set' }}{{ $consortium->region ? ' / ' . $consortium->region : '' }}
                                    </div>
                                    <a class="btn btn-sm btn-dark fw-bold" href="{{ route('consortium-operations.show', $consortium) }}">
                                        Open <i class="feather-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12">
                            <div class="empty-state text-center">
                                <i class="feather-inbox d-block mb-2" style="font-size: 1.8rem;"></i>
                                No consortium workspaces match this view.
                            </div>
                        </div>
                    @endforelse
                </div>

                <div class="mt-4">
                    {{ $consortia->links() }}
                </div>
            </div>

            @can('consortiums.analysis.view')
                <div class="tab-pane fade" id="analysis-pane" role="tabpanel" aria-labelledby="analysis-tab" tabindex="0">
                    <div class="ops-analysis-shell mb-4">
                        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
                            <div>
                                <h5 class="fw-bold mb-1">Graphical Components and Analysis</h5>
                                <p class="text-muted mb-0">Compare disbursements, receipts, and report decisions across consortia or individual think tanks.</p>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <button type="button" class="analysis-mode-btn active" data-analysis-mode="consortia">
                                    <i class="feather-grid me-1"></i> Consortia vs Consortia
                                </button>
                                <button type="button" class="analysis-mode-btn" data-analysis-mode="thinkTanks">
                                    <i class="feather-users me-1"></i> Think Tanks vs Think Tanks
                                </button>
                            </div>
                        </div>

                        <div class="analysis-control-panel" aria-label="Comparison Selector">
                            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                                <div>
                                    <h6 class="fw-bold mb-1">Comparison Selector</h6>
                                    <div class="text-muted small">Choose the comparison type, then select one item or many items for the charts.</div>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-sm btn-light border fw-bold" id="analysisTopButton">
                                        <i class="feather-trending-up me-1"></i> Select Top
                                    </button>
                                    <button type="button" class="btn btn-sm btn-light border fw-bold" id="analysisClearButton">
                                        <i class="feather-x-circle me-1"></i> Clear
                                    </button>
                                    <button type="button" class="btn btn-sm btn-primary fw-bold" id="analysisApplyButton">
                                        <i class="feather-check-circle me-1"></i> Apply Comparison
                                    </button>
                                </div>
                            </div>

                            <div class="analysis-control-grid">
                                <div class="analysis-field">
                                    <label for="analysisScopeSelect">Comparison scope</label>
                                    <select class="form-select" id="analysisScopeSelect">
                                        <option value="consortia">Consortia vs Consortia</option>
                                        <option value="thinkTanks">Think Tanks vs Think Tanks</option>
                                    </select>
                                </div>
                                <div class="analysis-field">
                                    <label for="analysisSelectionMode">Selection style</label>
                                    <select class="form-select" id="analysisSelectionMode">
                                        <option value="multi">Multiple select</option>
                                        <option value="single">Single select</option>
                                    </select>
                                </div>
                                <div class="analysis-field">
                                    <label for="analysisEntitySelect">Records to compare</label>
                                    <select class="form-select analysis-select" id="analysisEntitySelect" multiple></select>
                                </div>
                                <div class="analysis-field">
                                    <label for="analysisSortSelect">Sort by</label>
                                    <select class="form-select" id="analysisSortSelect">
                                        <option value="transferred">Funds sent</option>
                                        <option value="receipted">Receipts confirmed</option>
                                        <option value="receiptRate">Receipt rate</option>
                                        <option value="submittedReports">Reports submitted</option>
                                        <option value="approvedReports">Reports approved</option>
                                        <option value="rejectedReports">Reports rejected</option>
                                        <option value="label">Name</option>
                                    </select>
                                </div>
                                <div class="analysis-field">
                                    <label for="analysisMetricSelect">Metric focus</label>
                                    <select class="form-select" id="analysisMetricSelect">
                                        <option value="both">Funds and reports</option>
                                        <option value="funds">Funds only</option>
                                        <option value="reports">Reports only</option>
                                    </select>
                                </div>
                            </div>

                            <div class="analysis-chip-row" id="analysisChipRow">
                                <span class="analysis-chip warning">No comparison selected yet</span>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-xl-6" data-analysis-chart-wrap="funds">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="d-flex justify-content-between gap-2 align-items-center mb-2">
                                        <h6 class="fw-bold mb-0">Disbursements vs Receipts</h6>
                                        <span class="badge bg-light text-dark border">USD</span>
                                    </div>
                                    <div id="fundsComparisonChart" class="chart-frame"></div>
                                </div>
                            </div>
                            <div class="col-xl-6" data-analysis-chart-wrap="reports">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="d-flex justify-content-between gap-2 align-items-center mb-2">
                                        <h6 class="fw-bold mb-0">Reports Submitted vs Approved and Rejected</h6>
                                        <span class="badge bg-light text-dark border">Reports</span>
                                    </div>
                                    <div id="reportsComparisonChart" class="chart-frame"></div>
                                </div>
                            </div>
                        </div>

                        <div class="analysis-sheet">
                            <div class="analysis-sheet-header">
                                <div>
                                    <h6 class="fw-bold mb-1">Selected Comparison Sheet</h6>
                                    <div class="text-muted small" id="analysisSheetSummary">The table updates after you apply a comparison.</div>
                                </div>
                                <span class="badge bg-light text-dark border" id="analysisSelectedCount">0 selected</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Context</th>
                                            <th>Funds Sent</th>
                                            <th>Receipts</th>
                                            <th>Receipt Rate</th>
                                            <th>Reports</th>
                                            <th>Approved</th>
                                            <th>Rejected / Revision</th>
                                        </tr>
                                    </thead>
                                    <tbody id="analysisSheetBody">
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">Apply a comparison to load the selected records.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endcan
        </div>
    </div>
@endsection

@can('consortiums.manage')
    @push('modals')
        <div class="modal fade" id="createConsortiumModal" tabindex="-1" aria-labelledby="createConsortiumModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content border-0">
                    <div class="modal-header text-white" style="background: linear-gradient(120deg, #0f172a, #0f766e);">
                        <div>
                            <h5 class="modal-title fw-bold text-white" id="createConsortiumModalLabel">Create Consortium</h5>
                            <div class="small" style="color: #fde68a; font-weight: 800;">Set up the workspace before adding think tank members and funding records.</div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="{{ route('consortium-operations.store') }}">
                        @csrf
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Consortium name</label>
                                    <input class="form-control" name="name" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Country</label>
                                    <input class="form-control" name="country">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Region</label>
                                    <input class="form-control" name="region">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Currency</label>
                                    <input class="form-control" name="currency" value="USD" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Start date</label>
                                    <input class="form-control" name="start_date" type="date">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">End date</label>
                                    <input class="form-control" name="end_date" type="date">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Code</label>
                                    <input class="form-control" name="code" placeholder="Auto generated if empty">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Mandate</label>
                                    <textarea class="form-control" name="mandate" rows="3"></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Notes</label>
                                    <textarea class="form-control" name="notes" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary fw-bold">
                                <i class="feather-save me-1"></i> Create Consortium
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endpush
@endcan

@can('consortiums.analysis.view')
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const comparisonData = @json($comparisonData);
                const fundsChartEl = document.querySelector('#fundsComparisonChart');
                const reportsChartEl = document.querySelector('#reportsComparisonChart');
                const modeButtons = document.querySelectorAll('[data-analysis-mode]');
                const scopeSelect = document.querySelector('#analysisScopeSelect');
                const selectionModeSelect = document.querySelector('#analysisSelectionMode');
                const entitySelect = document.querySelector('#analysisEntitySelect');
                const sortSelect = document.querySelector('#analysisSortSelect');
                const metricSelect = document.querySelector('#analysisMetricSelect');
                const applyButton = document.querySelector('#analysisApplyButton');
                const clearButton = document.querySelector('#analysisClearButton');
                const topButton = document.querySelector('#analysisTopButton');
                const chipRow = document.querySelector('#analysisChipRow');
                const sheetBody = document.querySelector('#analysisSheetBody');
                const sheetSummary = document.querySelector('#analysisSheetSummary');
                const selectedCount = document.querySelector('#analysisSelectedCount');
                const fundsWrap = document.querySelector('[data-analysis-chart-wrap="funds"]');
                const reportsWrap = document.querySelector('[data-analysis-chart-wrap="reports"]');
                let currentMode = 'consortia';
                let fundsChart = null;
                let reportsChart = null;

                const formatUsd = (value) => 'USD ' + Number(value || 0).toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });

                const formatNumber = (value) => Number(value || 0).toLocaleString();
                const formatRate = (value) => Number(value || 0).toLocaleString(undefined, {
                    minimumFractionDigits: 1,
                    maximumFractionDigits: 1
                }) + '%';

                const emptyMarkup = '<div class="empty-state text-center h-100 d-flex align-items-center justify-content-center">No comparison data available for this view.</div>';

                function rowsForMode(mode) {
                    return Array.isArray(comparisonData[mode]) ? [...comparisonData[mode]] : [];
                }

                function sortRows(rows) {
                    const sortKey = sortSelect?.value || 'transferred';

                    return [...rows].sort((a, b) => {
                        if (sortKey === 'label') {
                            return String(a.label || '').localeCompare(String(b.label || ''));
                        }

                        return Number(b[sortKey] || 0) - Number(a[sortKey] || 0);
                    });
                }

                function selectedIds() {
                    if (!entitySelect) {
                        return [];
                    }

                    return Array.from(entitySelect.selectedOptions).map((option) => option.value);
                }

                function selectedRows() {
                    const ids = selectedIds();
                    const rows = rowsForMode(currentMode).filter((row) => ids.includes(String(row.id)));

                    return sortRows(rows);
                }

                function setActiveMode(mode) {
                    modeButtons.forEach((button) => {
                        button.classList.toggle('active', button.dataset.analysisMode === mode);
                    });
                    if (scopeSelect && scopeSelect.value !== mode) {
                        scopeSelect.value = mode;
                    }
                }

                function updateSelectMode() {
                    if (!entitySelect || !selectionModeSelect) {
                        return;
                    }

                    const multi = selectionModeSelect.value !== 'single';
                    if (multi) {
                        entitySelect.setAttribute('multiple', 'multiple');
                        entitySelect.size = Math.min(9, Math.max(5, entitySelect.options.length || 5));
                    } else {
                        entitySelect.removeAttribute('multiple');
                        entitySelect.size = 1;
                        const selected = selectedIds();
                        Array.from(entitySelect.options).forEach((option, index) => {
                            option.selected = selected.length ? option.value === selected[0] : index === 0;
                        });
                    }
                }

                function populateEntitySelect(mode, preferredIds = null) {
                    if (!entitySelect) {
                        return;
                    }

                    const rows = sortRows(rowsForMode(mode));
                    const requestedIds = preferredIds || selectedIds();
                    const availableIds = rows.map((row) => String(row.id));
                    let idsToSelect = requestedIds.filter((id) => availableIds.includes(String(id)));

                    if (!idsToSelect.length) {
                        const defaultCount = selectionModeSelect?.value === 'single' ? 1 : Math.min(6, rows.length);
                        idsToSelect = rows.slice(0, defaultCount).map((row) => String(row.id));
                    }

                    entitySelect.innerHTML = '';
                    rows.forEach((row) => {
                        const option = document.createElement('option');
                        option.value = String(row.id);
                        option.textContent = row.label + (row.context ? ' | ' + row.context : '');
                        option.selected = idsToSelect.includes(String(row.id));
                        entitySelect.appendChild(option);
                    });

                    updateSelectMode();
                }

                function updateMetricVisibility() {
                    const metric = metricSelect?.value || 'both';
                    if (fundsWrap) {
                        fundsWrap.classList.toggle('d-none', metric === 'reports');
                    }
                    if (reportsWrap) {
                        reportsWrap.classList.toggle('d-none', metric === 'funds');
                    }
                }

                function renderChips(rows) {
                    if (!chipRow) {
                        return;
                    }

                    chipRow.innerHTML = '';
                    const modeLabel = currentMode === 'consortia' ? 'Consortia' : 'Think tanks';
                    const styleLabel = selectionModeSelect?.value === 'single' ? 'Single select' : 'Multiple select';
                    const chips = [
                        modeLabel,
                        styleLabel,
                        (metricSelect?.selectedOptions[0]?.textContent || 'Funds and reports'),
                        rows.length + ' selected'
                    ];

                    if (!rows.length) {
                        const chip = document.createElement('span');
                        chip.className = 'analysis-chip warning';
                        chip.textContent = 'No records selected';
                        chipRow.appendChild(chip);
                        return;
                    }

                    chips.forEach((text) => {
                        const chip = document.createElement('span');
                        chip.className = 'analysis-chip';
                        chip.textContent = text;
                        chipRow.appendChild(chip);
                    });
                }

                function renderSheet(rows) {
                    if (!sheetBody) {
                        return;
                    }

                    sheetBody.innerHTML = '';
                    if (selectedCount) {
                        selectedCount.textContent = rows.length + ' selected';
                    }
                    if (sheetSummary) {
                        sheetSummary.textContent = rows.length
                            ? 'Showing the records currently selected for comparison.'
                            : 'No records selected. Use the dropdown to choose one or more records.';
                    }

                    if (!rows.length) {
                        const tr = document.createElement('tr');
                        const td = document.createElement('td');
                        td.colSpan = 8;
                        td.className = 'text-center text-muted py-4';
                        td.textContent = 'No records selected for this comparison.';
                        tr.appendChild(td);
                        sheetBody.appendChild(tr);
                        return;
                    }

                    rows.forEach((row) => {
                        const tr = document.createElement('tr');
                        [
                            row.label || '-',
                            row.context || '-',
                            formatUsd(row.transferred),
                            formatUsd(row.receipted),
                            formatRate(row.receiptRate),
                            formatNumber(row.submittedReports),
                            formatNumber(row.approvedReports),
                            formatNumber(row.rejectedReports)
                        ].forEach((value) => {
                            const td = document.createElement('td');
                            td.textContent = value;
                            tr.appendChild(td);
                        });
                        sheetBody.appendChild(tr);
                    });
                }

                function destroyCharts() {
                    if (fundsChart) {
                        fundsChart.destroy();
                        fundsChart = null;
                    }
                    if (reportsChart) {
                        reportsChart.destroy();
                        reportsChart = null;
                    }
                }

                function renderCharts(mode = currentMode) {
                    const rows = selectedRows();
                    setActiveMode(mode);
                    updateMetricVisibility();
                    renderChips(rows);
                    renderSheet(rows);

                    if (!window.ApexCharts || !fundsChartEl || !reportsChartEl) {
                        return;
                    }

                    destroyCharts();
                    fundsChartEl.innerHTML = '';
                    reportsChartEl.innerHTML = '';

                    if (!rows.length) {
                        fundsChartEl.innerHTML = emptyMarkup;
                        reportsChartEl.innerHTML = emptyMarkup;
                        return;
                    }

                    const categories = rows.map((row) => row.label);
                    const chartHeight = Math.max(390, rows.length * 34);

                    if ((metricSelect?.value || 'both') !== 'reports') {
                        fundsChart = new ApexCharts(fundsChartEl, {
                            series: [
                                { name: 'Disbursed', data: rows.map((row) => row.transferred) },
                                { name: 'Receipted', data: rows.map((row) => row.receipted) }
                            ],
                            chart: {
                                type: 'bar',
                                height: chartHeight,
                                toolbar: { show: true }
                            },
                            colors: ['#2563eb', '#16a34a'],
                            plotOptions: {
                                bar: {
                                    horizontal: true,
                                    borderRadius: 5,
                                    barHeight: '62%'
                                }
                            },
                            dataLabels: { enabled: false },
                            xaxis: {
                                categories: categories,
                                labels: {
                                    formatter: (value) => formatUsd(value)
                                }
                            },
                            tooltip: {
                                y: {
                                    formatter: (value) => formatUsd(value)
                                }
                            },
                            legend: {
                                position: 'top',
                                horizontalAlign: 'left'
                            }
                        });

                        fundsChart.render();
                    }

                    if ((metricSelect?.value || 'both') !== 'funds') {
                        reportsChart = new ApexCharts(reportsChartEl, {
                            series: [
                                { name: 'Submitted', data: rows.map((row) => row.submittedReports) },
                                { name: 'Approved', data: rows.map((row) => row.approvedReports) },
                                { name: 'Rejected / Revision', data: rows.map((row) => row.rejectedReports) }
                            ],
                            chart: {
                                type: 'bar',
                                height: chartHeight,
                                toolbar: { show: true }
                            },
                            colors: ['#0ea5e9', '#22c55e', '#f59e0b'],
                            plotOptions: {
                                bar: {
                                    horizontal: true,
                                    borderRadius: 5,
                                    barHeight: '62%'
                                }
                            },
                            dataLabels: { enabled: false },
                            xaxis: {
                                categories: categories,
                                decimalsInFloat: 0
                            },
                            tooltip: {
                                y: {
                                    formatter: (value) => Number(value || 0).toLocaleString()
                                }
                            },
                            legend: {
                                position: 'top',
                                horizontalAlign: 'left'
                            }
                        });

                        reportsChart.render();
                    }
                }

                function switchMode(mode) {
                    currentMode = mode || 'consortia';
                    setActiveMode(currentMode);
                    populateEntitySelect(currentMode, []);
                    renderCharts(currentMode);
                }

                function selectTopRows() {
                    if (!entitySelect) {
                        return;
                    }

                    const count = selectionModeSelect?.value === 'single' ? 1 : 8;
                    const ids = sortRows(rowsForMode(currentMode)).slice(0, count).map((row) => String(row.id));
                    Array.from(entitySelect.options).forEach((option) => {
                        option.selected = ids.includes(option.value);
                    });
                    renderCharts(currentMode);
                }

                function clearRows() {
                    if (!entitySelect) {
                        return;
                    }

                    Array.from(entitySelect.options).forEach((option) => {
                        option.selected = false;
                    });
                    renderCharts(currentMode);
                }

                modeButtons.forEach((button) => {
                    button.addEventListener('click', function () {
                        switchMode(this.dataset.analysisMode || 'consortia');
                    });
                });

                scopeSelect?.addEventListener('change', function () {
                    switchMode(this.value || 'consortia');
                });

                selectionModeSelect?.addEventListener('change', function () {
                    const currentSelection = selectedIds();
                    updateSelectMode();
                    if (this.value === 'single' && currentSelection.length > 1) {
                        Array.from(entitySelect.options).forEach((option, index) => {
                            option.selected = option.value === currentSelection[0] || (!currentSelection[0] && index === 0);
                        });
                    }
                    renderCharts(currentMode);
                });

                sortSelect?.addEventListener('change', function () {
                    populateEntitySelect(currentMode, selectedIds());
                    renderCharts(currentMode);
                });

                metricSelect?.addEventListener('change', function () {
                    renderCharts(currentMode);
                });

                applyButton?.addEventListener('click', function () {
                    renderCharts(currentMode);
                });

                clearButton?.addEventListener('click', clearRows);
                topButton?.addEventListener('click', selectTopRows);

                const analysisTab = document.querySelector('#analysis-tab');
                if (analysisTab) {
                    analysisTab.addEventListener('shown.bs.tab', function () {
                        renderCharts(currentMode);
                    });
                }

                populateEntitySelect(currentMode, []);
                renderChips(selectedRows());
                renderSheet(selectedRows());
            });
        </script>
    @endpush
@endcan
