@extends('layouts.app')

@section('content')
    <style>
        .pfp-shell {
            background: linear-gradient(180deg, #eef5f8 0%, #f7f9fc 38%, #f5f7fb 100%);
            min-height: calc(100vh - 110px);
            padding-bottom: 34px;
        }

        .pfp-hero {
            background:
                linear-gradient(135deg, rgba(16, 42, 67, .98) 0%, rgba(23, 107, 135, .96) 58%, rgba(244, 185, 66, .92) 100%);
            color: #fff;
            border-radius: 0 0 18px 18px;
            padding: 30px;
            box-shadow: 0 18px 42px rgba(16, 42, 67, .18);
        }

        .pfp-hero .eyebrow {
            color: #ffe08a;
            font-size: .78rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .pfp-hero h3,
        .pfp-hero .lead-copy,
        .pfp-hero .text-white-50 {
            color: #fff !important;
        }

        .pfp-hero .lead-copy {
            max-width: 860px;
            font-weight: 700;
            line-height: 1.55;
        }

        .pfp-filter,
        .pfp-panel,
        .pfp-stat {
            background: #fff;
            border: 1px solid #e7edf5;
            border-radius: 8px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, .07);
        }

        .pfp-filter {
            margin-top: -22px;
            padding: 20px;
            position: relative;
            z-index: 2;
        }

        .pfp-filter-title {
            color: #102a43;
            font-weight: 900;
        }

        .pfp-filter .form-label {
            color: #334155;
            font-size: .78rem;
            font-weight: 800;
            letter-spacing: .03em;
            text-transform: uppercase;
        }

        .pfp-filter .form-control,
        .pfp-filter .form-select {
            border-color: #d8e2ef;
            min-height: 42px;
        }

        .pfp-filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border-radius: 999px;
            background: #eaf3f8;
            color: #102a43;
            font-size: .78rem;
            font-weight: 800;
            padding: 7px 11px;
        }

        .pfp-period-field {
            display: none;
        }

        .pfp-stat {
            padding: 16px;
            min-height: 116px;
            border-left: 4px solid #176b87;
            position: relative;
            overflow: hidden;
        }

        .pfp-stat.gold { border-color: #f4b942; }
        .pfp-stat.green { border-color: #1d8f6f; }
        .pfp-stat.red { border-color: #bf4e30; }
        .pfp-stat.slate { border-color: #475569; }

        .pfp-stat .label {
            color: #64748b;
            font-size: .77rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .pfp-stat .value {
            color: #102a43;
            font-size: 1.08rem;
            font-weight: 900;
            margin-top: 8px;
            word-break: break-word;
        }

        .pfp-panel {
            overflow: hidden;
        }

        .pfp-panel-header {
            background: linear-gradient(135deg, #102a43 0%, #176b87 100%);
            border-bottom: 1px solid rgba(255, 255, 255, .12);
            color: #fff;
            padding: 18px 20px;
        }

        .pfp-panel-header h5,
        .pfp-panel-header .text-muted,
        .pfp-panel-header .small {
            color: #fff !important;
        }

        .pfp-panel-header .soft-note {
            color: #f8d77a !important;
            font-weight: 800;
        }

        .pfp-mini-metric {
            background: #fff;
            min-height: 104px;
        }

        .pfp-mini-metric .metric-label {
            color: #64748b;
            font-size: .72rem;
            font-weight: 900;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .pfp-balance-line {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px dashed #dbe4ef;
        }

        .pfp-balance-line:last-child {
            border-bottom: 0;
        }

        .pfp-balance-line strong {
            color: #102a43;
        }

        .pfp-table {
            font-size: .84rem;
            margin-bottom: 0;
        }

        .pfp-table th {
            background: #102a43 !important;
            color: #fff !important;
            border-color: #183b5b;
            font-size: .72rem;
            letter-spacing: .04em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .pfp-table td {
            vertical-align: middle;
            white-space: nowrap;
        }

        .pfp-table td:first-child {
            min-width: 360px;
            white-space: normal;
        }

        .pfp-row-project td {
            background: #e8f3f8;
            color: #102a43;
            font-weight: 800;
        }

        .pfp-row-activity td {
            background: #fff6dc;
            color: #4f3b00;
            font-weight: 700;
        }

        .pfp-row-sub td {
            background: #fff;
        }

        .pfp-chart-box {
            min-height: 300px;
            padding: 16px;
        }

        .pfp-empty {
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 32px;
            text-align: center;
            color: #64748b;
            background: #fff;
        }

        .pfp-section-title {
            color: #102a43;
            font-size: .78rem;
            font-weight: 900;
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        @media print {
            @page {
                size: A4 landscape;
                margin: 8mm;
            }

            .nxl-navigation,
            .header,
            .pfp-filter,
            .pfp-actions {
                display: none !important;
            }

            .content-wrapper {
                margin-left: 0 !important;
            }

            .pfp-shell {
                background: #fff;
            }

            .pfp-hero,
            .pfp-panel-header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .pfp-stat {
                min-height: auto;
                padding: 8px;
            }

            .pfp-table {
                width: 100% !important;
                table-layout: fixed;
                font-size: 6.5px;
            }

            .pfp-table th,
            .pfp-table td {
                padding: 3px !important;
                white-space: normal !important;
                word-break: break-word;
            }
        }
    </style>

    <div class="pfp-shell">
        <div class="nxl-container">
            <div class="pfp-hero">
                <div class="eyebrow mb-2">Reports & Analytics Engine</div>
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                    <div>
                        <h3 class="fw-bold text-white mb-2">Project Financial Position</h3>
                        <div class="lead-copy">
                            Full program position showing approved funding, allocation, commitment, purchase order, invoice, payment, and balance.
                        </div>
                    </div>
                    <div class="pfp-actions d-flex flex-wrap align-items-start gap-2">
                        @if ($program && $position)
                            <a href="{{ route('budget.reports.project-financial-position.export.pdf', $query ?? request()->query()) }}"
                                class="btn btn-warning fw-bold">
                                <i class="feather-download me-1"></i> Export Landscape PDF
                            </a>
                        @endif
                        <button type="button" class="btn btn-light" onclick="window.print()">
                            <i class="feather-printer me-1"></i> Browser Print
                        </button>
                    </div>
                </div>
            </div>

            @php
                $filterMode = $filters['mode'] ?? 'life_to_date';
                $selectedFundingId = (string) ($filters['funding_id'] ?? '');
                $selectedProjectId = (string) ($filters['project_id'] ?? '');
                $selectedActivityId = (string) ($filters['activity_id'] ?? '');
                $selectedSubActivityId = (string) ($filters['sub_activity_id'] ?? '');
                $filterFocus = $filters['focus'] ?? 'all';
                $filterDepth = $filters['depth'] ?? 'sub_activity';
            @endphp
            <form method="GET" action="{{ route('budget.reports.project-financial-position') }}" class="pfp-filter">
                <div class="d-flex flex-column flex-xl-row justify-content-between gap-2 mb-3">
                    <div>
                        <div class="pfp-filter-title">Report Filters</div>
                        <div class="text-muted small">Narrow the balance sheet by funding source, period, structure level, and financial condition.</div>
                    </div>
                    @if ($program)
                        <div class="d-flex flex-wrap gap-2">
                            <span class="pfp-filter-chip"><i class="feather-calendar"></i>{{ $filters['label'] ?? 'Life to date' }}</span>
                            <span class="pfp-filter-chip"><i class="feather-layers"></i>{{ ucfirst(str_replace('_', ' ', $filterDepth)) }}</span>
                            <span class="pfp-filter-chip"><i class="feather-folder"></i>{{ $structureFilterLabel ?? 'All projects, activities, and sub-activities' }}</span>
                            <span class="pfp-filter-chip"><i class="feather-filter"></i>{{ ucfirst(str_replace('_', ' ', $filterFocus)) }}</span>
                        </div>
                    @endif
                </div>

                <div class="row g-3 align-items-end">
                    <div class="col-lg-5">
                        <label class="form-label fw-semibold">Program</label>
                        <select name="program_id" id="pfpProgramFilter" class="form-select" required>
                            @foreach ($programs as $programOption)
                                <option value="{{ $programOption->id }}" @selected((string) $selectedProgramId === (string) $programOption->id)>
                                    {{ $programOption->program_id ? $programOption->program_id . ' - ' : '' }}{{ $programOption->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-3">
                        <label class="form-label fw-semibold">Funding Source</label>
                        <select name="funding_id" class="form-select">
                            <option value="">All approved funding</option>
                            @foreach ($fundingOptions as $fundingOption)
                                <option value="{{ $fundingOption->id }}" @selected($selectedFundingId === (string) $fundingOption->id)>
                                    {{ $fundingOption->funder?->name ?: 'Funding Source' }} - {{ $fundingOption->currency ?? 'USD' }} {{ number_format((float) $fundingOption->approved_amount, 2) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-2">
                        <label class="form-label fw-semibold">Period</label>
                        <select name="filter_mode" id="pfpFilterMode" class="form-select">
                            <option value="life_to_date" @selected($filterMode === 'life_to_date')>Life to Date</option>
                            <option value="multi_year" @selected($filterMode === 'multi_year')>Multi Year</option>
                            <option value="yearly" @selected($filterMode === 'yearly')>Yearly</option>
                            <option value="quarterly" @selected($filterMode === 'quarterly')>Quarterly</option>
                            <option value="semiannual" @selected($filterMode === 'semiannual')>6 Months</option>
                            <option value="range" @selected($filterMode === 'range')>Date Range</option>
                        </select>
                    </div>

                    <div class="col-lg-2">
                        <button class="btn btn-primary w-100" type="submit">
                            <i class="feather-search me-1"></i> Run Report
                        </button>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Project</label>
                        <select name="project_id" id="pfpProjectFilter" class="form-select">
                            <option value="">All projects</option>
                            @foreach (($structureOptions['projects'] ?? collect()) as $projectOption)
                                <option value="{{ $projectOption->id }}" @selected($selectedProjectId === (string) $projectOption->id)>
                                    {{ $projectOption->project_id ? $projectOption->project_id . ' - ' : '' }}{{ $projectOption->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Activity</label>
                        <select name="activity_id" id="pfpActivityFilter" class="form-select">
                            <option value="">All activities</option>
                            @foreach (($structureOptions['activities'] ?? collect()) as $activityOption)
                                <option value="{{ $activityOption['id'] }}"
                                    data-project-id="{{ $activityOption['project_id'] }}"
                                    @selected($selectedActivityId === (string) $activityOption['id'])>
                                    {{ $activityOption['project_name'] }} / {{ $activityOption['name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Sub-Activity</label>
                        <select name="sub_activity_id" id="pfpSubActivityFilter" class="form-select">
                            <option value="">All sub-activities</option>
                            @foreach (($structureOptions['subActivities'] ?? collect()) as $subActivityOption)
                                <option value="{{ $subActivityOption['id'] }}"
                                    data-project-id="{{ $subActivityOption['project_id'] }}"
                                    data-activity-id="{{ $subActivityOption['activity_id'] }}"
                                    @selected($selectedSubActivityId === (string) $subActivityOption['id'])>
                                    {{ $subActivityOption['activity_name'] }} / {{ $subActivityOption['name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2 pfp-period-field pfp-period-multi-year">
                        <label class="form-label fw-semibold">Start Year</label>
                        <input type="number" name="start_year" class="form-control" value="{{ request('start_year', $filters['start_year'] ?? now()->year) }}">
                    </div>
                    <div class="col-md-2 pfp-period-field pfp-period-multi-year">
                        <label class="form-label fw-semibold">End Year</label>
                        <input type="number" name="end_year" class="form-control" value="{{ request('end_year', $filters['end_year'] ?? now()->year) }}">
                    </div>
                    <div class="col-md-2 pfp-period-field pfp-period-yearly pfp-period-quarterly pfp-period-semiannual">
                        <label class="form-label fw-semibold">Year</label>
                        <input type="number" name="year" class="form-control" value="{{ request('year', $filters['start_year'] ?? now()->year) }}">
                    </div>
                    <div class="col-md-2 pfp-period-field pfp-period-quarterly">
                        <label class="form-label fw-semibold">Quarter</label>
                        <select name="quarter" class="form-select">
                            @for ($quarter = 1; $quarter <= 4; $quarter++)
                                <option value="{{ $quarter }}" @selected((int) request('quarter', 1) === $quarter)>Q{{ $quarter }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-md-2 pfp-period-field pfp-period-semiannual">
                        <label class="form-label fw-semibold">Half Year</label>
                        <select name="half" class="form-select">
                            <option value="1" @selected((int) request('half', 1) === 1)>H1</option>
                            <option value="2" @selected((int) request('half', 1) === 2)>H2</option>
                        </select>
                    </div>
                    <div class="col-md-3 pfp-period-field pfp-period-range">
                        <label class="form-label fw-semibold">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
                    </div>
                    <div class="col-md-3 pfp-period-field pfp-period-range">
                        <label class="form-label fw-semibold">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Search Structure / Reference</label>
                        <input type="search" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Project, activity, PR, PO, invoice, payment reference">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Financial Focus</label>
                        <select name="focus" class="form-select">
                            <option value="all" @selected($filterFocus === 'all')>All Lines</option>
                            <option value="unpaid" @selected($filterFocus === 'unpaid')>Unpaid Commitments</option>
                            <option value="over_committed" @selected($filterFocus === 'over_committed')>Over Committed</option>
                            <option value="with_disbursement" @selected($filterFocus === 'with_disbursement')>With Disbursements</option>
                            <option value="with_invoice" @selected($filterFocus === 'with_invoice')>With Invoices</option>
                            <option value="no_activity" @selected($filterFocus === 'no_activity')>No Financial Activity</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Detail Level</label>
                        <select name="depth" class="form-select">
                            <option value="project" @selected($filterDepth === 'project')>Project only</option>
                            <option value="activity" @selected($filterDepth === 'activity')>Project + Activity</option>
                            <option value="sub_activity" @selected($filterDepth === 'sub_activity')>Full Details</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Zero Lines</label>
                        <label class="form-control d-flex align-items-center gap-2">
                            <input type="checkbox" class="form-check-input mt-0" name="include_zero" value="1" @checked($filters['include_zero'] ?? true)>
                            <span class="fw-semibold">Show</span>
                        </label>
                    </div>

                    <div class="col-12 d-flex flex-wrap justify-content-end gap-2">
                        <a href="{{ route('budget.reports.project-financial-position', ['program_id' => $selectedProgramId]) }}" class="btn btn-outline-secondary">
                            <i class="feather-rotate-ccw me-1"></i> Reset Filters
                        </a>
                        <button class="btn btn-primary" type="submit">
                            <i class="feather-sliders me-1"></i> Apply Filters
                        </button>
                    </div>
                </div>
            </form>

            @if (! $program || ! $position)
                <div class="pfp-empty mt-4">
                    Select a program to generate the full financial position report.
                </div>
            @else
                @php
                    $currency = $position['currency'] ?? 'USD';
                    $totals = $position['totals'];
                    $money = fn ($value) => $currency . ' ' . number_format((float) $value, 2);
                    $statCards = [
                        ['label' => 'Approved Funding', 'value' => $money($totals['approved_funding'] ?? 0), 'class' => 'green', 'icon' => 'feather-award'],
                        ['label' => 'Program Budget', 'value' => $money($totals['budget'] ?? 0), 'class' => '', 'icon' => 'feather-briefcase'],
                        ['label' => 'Committed', 'value' => $money($totals['committed'] ?? 0), 'class' => 'gold', 'icon' => 'feather-lock'],
                        ['label' => 'Purchase Orders', 'value' => $money($totals['purchase_orders'] ?? 0), 'class' => 'slate', 'icon' => 'feather-file-text'],
                        ['label' => 'Invoices', 'value' => $money($totals['invoiced'] ?? 0), 'class' => 'slate', 'icon' => 'feather-file'],
                        ['label' => 'Disbursed', 'value' => $money($totals['disbursed'] ?? 0), 'class' => 'green', 'icon' => 'feather-send'],
                        ['label' => 'Funding Balance', 'value' => $money($totals['funding_balance'] ?? 0), 'class' => (($totals['funding_balance'] ?? 0) < 0 ? 'red' : 'green'), 'icon' => 'feather-pocket'],
                        ['label' => 'Uncommitted Budget', 'value' => $money($totals['uncommitted_budget'] ?? 0), 'class' => (($totals['uncommitted_budget'] ?? 0) < 0 ? 'red' : ''), 'icon' => 'feather-minus-circle'],
                    ];
                @endphp

                <div class="row g-3 mt-2">
                    @foreach ($statCards as $card)
                        <div class="col-md-6 col-xl-3">
                            <div class="pfp-stat {{ $card['class'] }}">
                                <div class="d-flex justify-content-between gap-3">
                                    <div>
                                        <div class="label">{{ $card['label'] }}</div>
                                        <div class="value">{{ $card['value'] }}</div>
                                    </div>
                                    <div class="fs-3 text-muted"><i class="{{ $card['icon'] }}"></i></div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-xl-4">
                        <div class="pfp-panel h-100">
                            <div class="pfp-panel-header">
                                <h5 class="fw-bold mb-1">Balance Position</h5>
                                <div class="text-muted small">Program-level control totals</div>
                            </div>
                            <div class="p-3">
                                <div class="pfp-balance-line">
                                    <span>Approved funding less program budget</span>
                                    <strong class="{{ ($totals['allocation_balance'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">{{ $money($totals['allocation_balance'] ?? 0) }}</strong>
                                </div>
                                <div class="pfp-balance-line">
                                    <span>Program budget less approved commitments</span>
                                    <strong class="{{ ($totals['uncommitted_budget'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">{{ $money($totals['uncommitted_budget'] ?? 0) }}</strong>
                                </div>
                                <div class="pfp-balance-line">
                                    <span>Approved commitments less disbursements</span>
                                    <strong class="{{ ($totals['unpaid_commitments'] ?? 0) < 0 ? 'text-danger' : '' }}">{{ $money($totals['unpaid_commitments'] ?? 0) }}</strong>
                                </div>
                                <div class="pfp-balance-line">
                                    <span>Invoices less disbursements</span>
                                    <strong class="{{ ($totals['invoice_balance'] ?? 0) < 0 ? 'text-danger' : '' }}">{{ $money($totals['invoice_balance'] ?? 0) }}</strong>
                                </div>
                                <div class="pfp-balance-line">
                                    <span>Commitment utilization</span>
                                    <strong>{{ number_format($totals['commitment_rate'] ?? 0, 1) }}%</strong>
                                </div>
                                <div class="pfp-balance-line">
                                    <span>Disbursement utilization</span>
                                    <strong>{{ number_format($totals['disbursement_rate'] ?? 0, 1) }}%</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-8">
                        <div class="pfp-panel h-100">
                            <div class="pfp-panel-header">
                                <h5 class="fw-bold mb-1">{{ $program->name }}</h5>
                                <div class="text-muted small">
                                    Funding Partners:
                                    {{ $funders->isEmpty() ? 'N/A' : $funders->pluck('name')->implode(', ') }}
                                </div>
                            </div>
                            <div class="row g-0">
                                <div class="col-md-4 border-end p-3 pfp-mini-metric">
                                    <div class="metric-label">Projects</div>
                                    <div class="h4 fw-bold mb-0">{{ number_format($position['counts']['projects'] ?? 0) }}</div>
                                </div>
                                <div class="col-md-4 border-end p-3 pfp-mini-metric">
                                    <div class="metric-label">Activities</div>
                                    <div class="h4 fw-bold mb-0">{{ number_format($position['counts']['activities'] ?? 0) }}</div>
                                </div>
                                <div class="col-md-4 p-3 pfp-mini-metric">
                                    <div class="metric-label">Sub-Activities</div>
                                    <div class="h4 fw-bold mb-0">{{ number_format($position['counts']['sub_activities'] ?? 0) }}</div>
                                </div>
                                <div class="col-md-3 border-top border-end p-3 pfp-mini-metric">
                                    <div class="metric-label">Commitments</div>
                                    <div class="h5 fw-bold mb-0">{{ number_format($position['counts']['commitments'] ?? 0) }}</div>
                                </div>
                                <div class="col-md-3 border-top border-end p-3 pfp-mini-metric">
                                    <div class="metric-label">POs</div>
                                    <div class="h5 fw-bold mb-0">{{ number_format($position['counts']['purchase_orders'] ?? 0) }}</div>
                                </div>
                                <div class="col-md-3 border-top border-end p-3 pfp-mini-metric">
                                    <div class="metric-label">Invoices</div>
                                    <div class="h5 fw-bold mb-0">{{ number_format($position['counts']['invoices'] ?? 0) }}</div>
                                </div>
                                <div class="col-md-3 border-top p-3 pfp-mini-metric">
                                    <div class="metric-label">Payments</div>
                                    <div class="h5 fw-bold mb-0">{{ number_format($position['counts']['disbursements'] ?? 0) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-xl-7">
                        <div class="pfp-panel">
                            <div class="pfp-panel-header">
                                <h5 class="fw-bold mb-1">Budget vs Commitments vs Disbursements</h5>
                                <div class="small soft-note">Project comparison for the selected filters</div>
                            </div>
                            <div class="pfp-chart-box">
                                <canvas id="pfpProjectBarChart" height="125"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-5">
                        <div class="pfp-panel">
                            <div class="pfp-panel-header">
                                <h5 class="fw-bold mb-1">Program Control Split</h5>
                                <div class="small soft-note">Committed, disbursed, and remaining funding</div>
                            </div>
                            <div class="pfp-chart-box">
                                <canvas id="pfpProgramDoughnutChart" height="125"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pfp-panel mt-4">
                    <div class="pfp-panel-header d-flex flex-column flex-lg-row justify-content-between gap-2">
                        <div>
                            <h5 class="fw-bold mb-1">Full Program Balance Sheet</h5>
                            <div class="text-muted small">Project, activity, and sub-activity financial position in {{ $currency }}</div>
                        </div>
                        <span class="badge bg-primary-subtle text-primary align-self-start">{{ $currency }}</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered pfp-table">
                            <thead>
                                <tr>
                                    <th>Program Structure</th>
                                    <th class="text-end">Budget</th>
                                    <th class="text-end">Committed</th>
                                    <th class="text-end">POs</th>
                                    <th class="text-end">Invoices</th>
                                    <th class="text-end">Disbursed</th>
                                    <th class="text-end">Budget Balance</th>
                                    <th class="text-end">Unpaid Commitment</th>
                                    <th class="text-end">Commitment %</th>
                                    <th class="text-end">Disbursement %</th>
                                    <th>PR Ref.</th>
                                    <th>PO Ref.</th>
                                    <th>Invoice Ref.</th>
                                    <th>Payment Ref.</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($position['rows'] as $projectRow)
                                    @include('budgetreport.financial-position-row', ['row' => $projectRow, 'depth' => 0])
                                    @foreach ($projectRow['children'] as $activityRow)
                                        @include('budgetreport.financial-position-row', ['row' => $activityRow, 'depth' => 1])
                                        @foreach ($activityRow['children'] as $subRow)
                                            @include('budgetreport.financial-position-row', ['row' => $subRow, 'depth' => 2])
                                        @endforeach
                                    @endforeach
                                @empty
                                    <tr>
                                        <td colspan="14" class="text-center text-muted py-4">No matching financial lines were found for the selected filters.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <script type="application/json" id="pfpChartData">@json($position['chart'])</script>
                <script type="application/json" id="pfpTotalsData">@json($totals)</script>
                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        if (!window.Chart) {
                            return;
                        }

                        const chartData = JSON.parse(document.getElementById('pfpChartData')?.textContent || '{}');
                        const totals = JSON.parse(document.getElementById('pfpTotalsData')?.textContent || '{}');
                        const labels = chartData.labels || [];
                        const moneyTick = (value) => new Intl.NumberFormat('en-US', { notation: 'compact' }).format(value || 0);

                        const barNode = document.getElementById('pfpProjectBarChart');
                        if (barNode) {
                            new Chart(barNode, {
                                type: 'bar',
                                data: {
                                    labels,
                                    datasets: [
                                        { label: 'Budget', data: chartData.budget || [], backgroundColor: '#176b87' },
                                        { label: 'Committed', data: chartData.committed || [], backgroundColor: '#f4b942' },
                                        { label: 'Disbursed', data: chartData.disbursed || [], backgroundColor: '#1d8f6f' },
                                    ],
                                },
                                options: {
                                    responsive: true,
                                    plugins: { legend: { position: 'bottom' } },
                                    scales: { y: { ticks: { callback: moneyTick } } },
                                },
                            });
                        }

                        const doughnutNode = document.getElementById('pfpProgramDoughnutChart');
                        if (doughnutNode) {
                            new Chart(doughnutNode, {
                                type: 'doughnut',
                                data: {
                                    labels: ['Disbursed', 'Committed Not Paid', 'Funding Balance'],
                                    datasets: [{
                                        data: [
                                            Math.max(Number(totals.disbursed || 0), 0),
                                            Math.max(Number(totals.unpaid_commitments || 0), 0),
                                            Math.max(Number(totals.funding_balance || 0), 0),
                                        ],
                                        backgroundColor: ['#1d8f6f', '#f4b942', '#176b87'],
                                        borderWidth: 0,
                                    }],
                                },
                                options: {
                                    responsive: true,
                                    plugins: { legend: { position: 'bottom' } },
                                    cutout: '68%',
                                },
                            });
                        }
                    });
                </script>
            @endif
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const programSelect = document.getElementById('pfpProgramFilter');
            const projectSelect = document.getElementById('pfpProjectFilter');
            const activitySelect = document.getElementById('pfpActivityFilter');
            const subActivitySelect = document.getElementById('pfpSubActivityFilter');
            const modeSelect = document.getElementById('pfpFilterMode');
            const fields = document.querySelectorAll('.pfp-period-field');

            const syncPeriodFields = () => {
                const mode = modeSelect?.value || 'life_to_date';
                fields.forEach((field) => {
                    field.style.display = field.classList.contains(`pfp-period-${mode}`) ? '' : 'none';
                });
            };

            const setOptionVisibility = (option, isVisible) => {
                option.hidden = !isVisible;
                option.disabled = !isVisible;
            };

            const syncStructureFields = () => {
                const projectId = projectSelect?.value || '';

                activitySelect?.querySelectorAll('option[data-project-id]').forEach((option) => {
                    const isVisible = !projectId || option.dataset.projectId === projectId;
                    setOptionVisibility(option, isVisible);

                    if (!isVisible && option.selected) {
                        activitySelect.value = '';
                    }
                });

                const activityId = activitySelect?.value || '';

                subActivitySelect?.querySelectorAll('option[data-project-id]').forEach((option) => {
                    const matchesProject = !projectId || option.dataset.projectId === projectId;
                    const matchesActivity = !activityId || option.dataset.activityId === activityId;
                    const isVisible = matchesProject && matchesActivity;
                    setOptionVisibility(option, isVisible);

                    if (!isVisible && option.selected) {
                        subActivitySelect.value = '';
                    }
                });
            };

            programSelect?.addEventListener('change', () => {
                if (projectSelect) {
                    projectSelect.value = '';
                }

                if (activitySelect) {
                    activitySelect.value = '';
                }

                if (subActivitySelect) {
                    subActivitySelect.value = '';
                }
            });
            projectSelect?.addEventListener('change', syncStructureFields);
            activitySelect?.addEventListener('change', syncStructureFields);
            modeSelect?.addEventListener('change', syncPeriodFields);
            syncStructureFields();
            syncPeriodFields();
        });
    </script>
@endsection
