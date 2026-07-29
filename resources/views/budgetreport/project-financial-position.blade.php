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

        .pfp-shell {
            --pfp-ink: #12231f;
            --pfp-muted: #65766f;
            --pfp-green: #08765f;
            --pfp-green-dark: #065745;
            --pfp-mint: #e9f6f1;
            --pfp-line: #dfe9e5;
            --pfp-amber: #b7791f;
            background:
                radial-gradient(circle at top right, rgba(8, 118, 95, .09), transparent 30rem),
                #f4f7f6;
        }

        .pfp-shell .nxl-container {
            padding: 24px clamp(16px, 2.4vw, 34px) 44px;
        }

        .pfp-hero {
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 22px;
            background:
                radial-gradient(circle at 88% 18%, rgba(70, 206, 167, .24), transparent 20rem),
                linear-gradient(135deg, #0c2e27 0%, #075f4d 62%, #0b8068 100%);
            padding: clamp(24px, 3vw, 38px);
            box-shadow: 0 24px 54px rgba(7, 73, 59, .18);
        }

        .pfp-hero h3 {
            font-size: clamp(1.65rem, 3vw, 2.45rem);
            letter-spacing: -.035em;
        }

        .pfp-hero .eyebrow {
            color: #9de5cf;
        }

        .pfp-hero-status {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
            gap: .65rem;
        }

        .pfp-status-pill,
        .pfp-dashboard-link {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            min-height: 38px;
            padding: .55rem .78rem;
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 999px;
            background: rgba(255, 255, 255, .11);
            color: #fff;
            font-size: .72rem;
            font-weight: 800;
            text-decoration: none;
            backdrop-filter: blur(8px);
        }

        .pfp-status-pill i {
            color: #8ef0d2;
        }

        .pfp-dashboard-link:hover {
            border-color: rgba(255, 255, 255, .38);
            background: rgba(255, 255, 255, .18);
            color: #fff;
        }

        .pfp-filter,
        .pfp-panel,
        .pfp-stat {
            border-color: var(--pfp-line);
            border-radius: 16px;
            box-shadow: 0 12px 34px rgba(35, 64, 55, .065);
        }

        .pfp-filter {
            margin: -18px 18px 0;
            padding: 20px;
        }

        .pfp-filter .form-control,
        .pfp-filter .form-select {
            min-height: 44px;
            border-color: #d8e5e0;
            border-radius: 10px;
            color: var(--pfp-ink);
        }

        .pfp-filter .form-control:focus,
        .pfp-filter .form-select:focus {
            border-color: #43a88e;
            box-shadow: 0 0 0 .2rem rgba(8, 118, 95, .1);
        }

        .pfp-filter-chip {
            border: 1px solid #d9ebe5;
            background: #f0f8f5;
            color: #24594c;
        }

        .pfp-advanced {
            width: 100%;
            border: 1px solid var(--pfp-line);
            border-radius: 12px;
            background: #fafcfb;
        }

        .pfp-advanced > summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .85rem 1rem;
            color: #31574d;
            font-size: .76rem;
            font-weight: 850;
            cursor: pointer;
            list-style: none;
        }

        .pfp-advanced > summary::-webkit-details-marker {
            display: none;
        }

        .pfp-advanced > summary::after {
            content: '+';
            color: var(--pfp-green);
            font-size: 1.15rem;
        }

        .pfp-advanced[open] > summary::after {
            content: '−';
        }

        .pfp-advanced-body {
            padding: 0 1rem 1rem;
            border-top: 1px solid var(--pfp-line);
        }

        .pfp-stat {
            min-height: 132px;
            padding: 17px;
            border-left: 0;
        }

        .pfp-stat::after {
            content: '';
            position: absolute;
            inset: auto -28px -42px auto;
            width: 92px;
            height: 92px;
            border-radius: 50%;
            background: rgba(8, 118, 95, .06);
        }

        .pfp-stat.gold::after { background: rgba(183, 121, 31, .08); }
        .pfp-stat.red::after { background: rgba(176, 67, 67, .08); }

        .pfp-stat .value {
            margin-top: 9px;
            color: var(--pfp-ink);
            font-size: clamp(1rem, 1.6vw, 1.25rem);
            letter-spacing: -.02em;
        }

        .pfp-stat-meta {
            margin-top: .45rem;
            color: var(--pfp-muted);
            font-size: .68rem;
            font-weight: 700;
        }

        .pfp-stat-icon {
            display: grid;
            place-items: center;
            width: 38px;
            height: 38px;
            border-radius: 11px;
            background: var(--pfp-mint);
            color: var(--pfp-green);
            font-size: 1rem;
        }

        .pfp-reconciliation {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 1rem;
            padding: .9rem 1rem;
            border: 1px solid #bce2d5;
            border-radius: 14px;
            background: #ecf9f4;
            color: #145747;
        }

        .pfp-reconciliation.is-filtered {
            border-color: #ead9b4;
            background: #fff9eb;
            color: #745216;
        }

        .pfp-reconciliation strong,
        .pfp-reconciliation span {
            display: block;
        }

        .pfp-reconciliation span {
            margin-top: .18rem;
            font-size: .69rem;
        }

        .pfp-panel-header {
            border-bottom-color: var(--pfp-line);
            background: #fff;
            color: var(--pfp-ink);
        }

        .pfp-panel-header h5,
        .pfp-panel-header .text-muted,
        .pfp-panel-header .small {
            color: inherit !important;
        }

        .pfp-panel-header .soft-note {
            color: var(--pfp-green) !important;
        }

        .pfp-control-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
            padding: 1rem;
        }

        .pfp-control-card {
            min-width: 0;
            padding: 1rem;
            border: 1px solid var(--pfp-line);
            border-radius: 13px;
            background: #fafcfb;
        }

        .pfp-control-label {
            color: var(--pfp-muted);
            font-size: .67rem;
            font-weight: 850;
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        .pfp-control-value {
            margin-top: .38rem;
            color: var(--pfp-ink);
            font-size: 1.15rem;
            font-weight: 900;
        }

        .pfp-coverage-track {
            overflow: hidden;
            height: 8px;
            margin-top: .75rem;
            border-radius: 99px;
            background: #e4ece9;
        }

        .pfp-coverage-track span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #0c7d64, #34b692);
        }

        .pfp-composition-list {
            display: grid;
            gap: .55rem;
            margin-top: .65rem;
        }

        .pfp-composition-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            font-size: .72rem;
        }

        .pfp-composition-row span:first-child {
            color: #536a62;
            font-weight: 750;
            text-transform: capitalize;
        }

        .pfp-exceptions {
            margin: 0 1rem 1rem;
            border: 1px solid #efd8ad;
            border-radius: 13px;
            background: #fffaf0;
        }

        .pfp-exceptions > summary {
            padding: .9rem 1rem;
            color: #805b1b;
            font-size: .74rem;
            font-weight: 850;
            cursor: pointer;
        }

        .pfp-exceptions .table {
            margin-bottom: 0;
            font-size: .74rem;
        }

        .pfp-table-wrap {
            max-height: 72vh;
        }

        .pfp-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #173f35 !important;
            border-color: #28594d;
        }

        .pfp-table tfoot th,
        .pfp-table tfoot td {
            position: sticky;
            bottom: 0;
            z-index: 2;
            border-color: #cfe1db;
            background: #eaf5f1;
            color: #153f34;
            font-weight: 900;
        }

        .pfp-report-footer {
            align-items: center;
            background: linear-gradient(135deg, #102a43 0%, #176b87 100%);
            border-top: 4px solid #f4b942;
            border-radius: 8px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, .12);
            color: #dbeafe;
            display: grid;
            gap: 1rem;
            grid-template-columns: 1fr 1.35fr 1fr;
            margin-top: 1.5rem;
            padding: 16px 20px;
        }

        .pfp-report-footer strong {
            color: #fff;
            display: block;
            font-weight: 900;
        }

        .pfp-report-footer .footer-kicker {
            color: #f8d77a;
            font-size: .68rem;
            font-weight: 900;
            letter-spacing: .07em;
            text-transform: uppercase;
        }

        .pfp-report-footer .footer-copy {
            color: #dbeafe;
            font-size: .75rem;
            margin-top: 3px;
        }

        .pfp-report-footer .footer-context {
            border-left: 1px solid rgba(255, 255, 255, .2);
            border-right: 1px solid rgba(255, 255, 255, .2);
            padding-inline: 1rem;
            text-align: center;
        }

        .pfp-report-footer .footer-time {
            text-align: right;
        }

        @media (max-width: 1199.98px) {
            .pfp-control-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 767.98px) {
            .pfp-shell .nxl-container {
                padding-inline: 12px;
            }

            .pfp-filter {
                margin-inline: 8px;
            }

            .pfp-control-grid {
                grid-template-columns: 1fr;
            }

            .pfp-reconciliation {
                align-items: flex-start;
                flex-direction: column;
            }

            .pfp-report-footer {
                grid-template-columns: 1fr;
            }

            .pfp-report-footer .footer-context {
                border-left: 0;
                border-right: 0;
                border-top: 1px solid rgba(255, 255, 255, .2);
                border-bottom: 1px solid rgba(255, 255, 255, .2);
                padding: .75rem 0;
                text-align: left;
            }

            .pfp-report-footer .footer-time {
                text-align: left;
            }
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
            .pfp-panel-header,
            .pfp-report-footer {
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
                <div class="eyebrow mb-2">Financial control centre</div>
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                    <div>
                        <h3 class="fw-bold text-white mb-2">Project Financial Position</h3>
                        <div class="lead-copy">
                            One reconciled view of approved funding, scheduled allocations, commitments,
                            purchase orders, and paid disbursements.
                        </div>
                    </div>
                    <div class="pfp-actions d-flex flex-column align-items-lg-end gap-2">
                        @if ($program && $position)
                            <div class="pfp-hero-status">
                                @if ($position['dashboard_aligned'] ?? false)
                                    <span class="pfp-status-pill">
                                        <i class="feather-check-circle"></i> Reconciled with Executive Dashboard
                                    </span>
                                @endif
                                <a class="pfp-dashboard-link"
                                    href="{{ route('finance.execution.dashboard', array_filter([
                                        'program_id' => $program->id,
                                        'project_id' => $filters['project_id'] ?? null,
                                    ])) }}">
                                    Open Executive Dashboard <i class="feather-arrow-up-right"></i>
                                </a>
                            </div>
                            <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                            <a id="pfpExportPdf"
                                href="{{ route('budget.reports.project-financial-position.export.pdf', $query ?? request()->query()) }}"
                                class="btn btn-warning fw-bold">
                                <i class="feather-download me-1"></i> Export PDF
                            </a>
                                <button type="button" class="btn btn-light" onclick="window.print()">
                                    <i class="feather-printer me-1"></i> Print
                                </button>
                            </div>
                        @else
                            <button type="button" class="btn btn-light" onclick="window.print()">
                                <i class="feather-printer me-1"></i> Print
                            </button>
                        @endif
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
                $hasAdvancedFilters = $selectedProjectId !== ''
                    || $selectedActivityId !== ''
                    || $selectedSubActivityId !== ''
                    || $filterFocus !== 'all'
                    || $filterDepth !== 'sub_activity'
                    || filled($filters['search'] ?? '');
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

                    <div class="col-12">
                        <details class="pfp-advanced" @if ($hasAdvancedFilters) open @endif>
                            <summary>
                                <span><i class="feather-sliders me-2"></i>Advanced structure and ledger filters</span>
                                <span class="text-muted">Optional</span>
                            </summary>
                            <div class="pfp-advanced-body">
                                <div class="row g-3 pt-3">
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
                            </div>
                        </details>
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
                    $controls = $position['controls'] ?? [];
                    $money = fn ($value) => $currency . ' ' . number_format((float) $value, 2);
                    $statCards = [
                        ['label' => 'Approved Funding', 'value' => $money($totals['approved_funding'] ?? 0), 'meta' => 'Approved funding-partner value', 'class' => 'green', 'icon' => 'feather-target'],
                        ['label' => 'Scheduled Allocation', 'value' => $money($totals['scheduled_allocation'] ?? 0), 'meta' => 'Executive Dashboard allocation', 'class' => (($totals['approved_funding_less_scheduled_allocation'] ?? 0) < 0 ? 'red' : ''), 'icon' => 'feather-layers'],
                        ['label' => 'Committed', 'value' => $money($totals['committed'] ?? 0), 'meta' => number_format($totals['commitment_rate'] ?? 0, 2).'% of approved funding', 'class' => 'gold', 'icon' => 'feather-lock'],
                        ['label' => 'Disbursed', 'value' => $money($totals['disbursed'] ?? 0), 'meta' => number_format($totals['disbursement_rate'] ?? 0, 2).'% of approved funding', 'class' => 'green', 'icon' => 'feather-send'],
                        ['label' => 'Purchase Orders', 'value' => $money($totals['purchase_orders'] ?? 0), 'meta' => number_format($position['counts']['purchase_orders'] ?? 0).' active records', 'class' => 'slate', 'icon' => 'feather-file-text'],
                        ['label' => 'Funding Utilization Gap', 'value' => $money($totals['funding_utilization_gap'] ?? 0), 'meta' => 'Approved funding less commitments', 'class' => (($totals['funding_utilization_gap'] ?? 0) < 0 ? 'red' : ''), 'icon' => 'feather-pie-chart'],
                        ['label' => 'Purchase Request Total', 'value' => $money($totals['unprocessed_purchase_requests'] ?? 0), 'meta' => 'Unprocessed purchase requests', 'class' => (($totals['unprocessed_purchase_requests'] ?? 0) > 0 ? 'gold' : 'green'), 'icon' => 'feather-clipboard'],
                        ['label' => 'Unpaid Commitments', 'value' => $money($totals['unpaid_commitments'] ?? 0), 'meta' => 'Purchase orders less disbursements', 'class' => (($totals['unpaid_commitments'] ?? 0) > 0 ? 'gold' : 'green'), 'icon' => 'feather-clock'],
                    ];
                @endphp

                <div class="pfp-panel mt-4">
                    <div class="pfp-panel-header">
                        <h5 class="fw-bold mb-1">Report Context</h5>
                        <div class="small text-muted">Applied scope for the web report and PDF export</div>
                    </div>
                    <div class="row g-0">
                        <div class="col-lg-4 border-end border-bottom p-3 pfp-mini-metric">
                            <div class="metric-label">Program</div>
                            <div class="fw-bold">{{ $program->program_id ? $program->program_id . ' - ' : '' }}{{ $program->name }}</div>
                        </div>
                        <div class="col-lg-3 border-end border-bottom p-3 pfp-mini-metric">
                            <div class="metric-label">Funding Partners</div>
                            <div class="fw-bold">{{ $funders->isEmpty() ? 'N/A' : $funders->pluck('name')->implode(', ') }}</div>
                        </div>
                        <div class="col-lg-3 border-end border-bottom p-3 pfp-mini-metric">
                            <div class="metric-label">Coverage</div>
                            <div class="fw-bold">{{ $filters['label'] ?? 'Life to date' }}</div>
                        </div>
                        <div class="col-lg-2 border-bottom p-3 pfp-mini-metric">
                            <div class="metric-label">Rows Included</div>
                            <div class="fw-bold text-capitalize">{{ str_replace('_', ' ', $filterDepth) }}</div>
                        </div>
                        <div class="col-lg-6 border-end border-bottom p-3 pfp-mini-metric">
                            <div class="metric-label">Structure Filter</div>
                            <div class="fw-bold">{{ $structureFilterLabel ?? 'All projects, activities, and sub-activities' }}</div>
                        </div>
                        <div class="col-lg-2 border-end border-bottom p-3 pfp-mini-metric">
                            <div class="metric-label">Financial Focus</div>
                            <div class="fw-bold text-capitalize">{{ str_replace('_', ' ', $filterFocus) }}</div>
                        </div>
                        <div class="col-lg-3 border-end border-bottom p-3 pfp-mini-metric">
                            <div class="metric-label">Search</div>
                            <div class="fw-bold">{{ filled($filters['search'] ?? '') ? $filters['search'] : 'None' }}</div>
                        </div>
                        <div class="col-lg-1 border-bottom p-3 pfp-mini-metric">
                            <div class="metric-label">Zero Lines</div>
                            <div class="fw-bold">{{ ($filters['include_zero'] ?? true) ? 'Shown' : 'Hidden' }}</div>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <h5 class="fw-bold mb-1">Financial Control Summary</h5>
                    <div class="small text-muted">Approved funding and current execution position for the selected scope</div>
                </div>

                <div class="row g-3 mt-1">
                    @foreach ($statCards as $card)
                        <div class="col-md-6 col-xl-3">
                            <div class="pfp-stat {{ $card['class'] }}">
                                <div class="d-flex justify-content-between gap-3">
                                    <div>
                                        <div class="label">{{ $card['label'] }}</div>
                                        <div class="value">{{ $card['value'] }}</div>
                                        <div class="pfp-stat-meta">{{ $card['meta'] }}</div>
                                    </div>
                                    <div class="pfp-stat-icon"><i class="{{ $card['icon'] }}"></i></div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="pfp-reconciliation {{ ($position['dashboard_aligned'] ?? false) ? '' : 'is-filtered' }}">
                    <div class="d-flex align-items-start gap-2">
                        <i class="{{ ($position['dashboard_aligned'] ?? false) ? 'feather-check-circle' : 'feather-filter' }} mt-1"></i>
                        <div>
                            <strong>
                                {{ ($position['dashboard_aligned'] ?? false)
                                    ? 'Execution Dashboard source active'
                                    : 'Filtered financial-position view' }}
                            </strong>
                            <span>
                                {{ ($position['dashboard_aligned'] ?? false)
                                    ? 'Scheduled allocation, commitment, disbursement, component totals, and utilization are loaded directly from the Execution Dashboard dataset.'
                                    : 'Custom funding, period, or structure filters are active, so totals are intentionally narrower than the programme-wide Executive Dashboard.' }}
                            </span>
                        </div>
                    </div>
                    <a class="btn btn-sm btn-outline-success"
                        href="{{ route('finance.execution.dashboard', ['program_id' => $program->id]) }}">
                        Open source dashboard <i class="feather-arrow-up-right ms-1"></i>
                    </a>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-xl-4">
                        <div class="pfp-panel h-100">
                            <div class="pfp-panel-header">
                                <h5 class="fw-bold mb-1">Executive Controls</h5>
                                <div class="text-muted small">Programme-envelope reconciliation</div>
                            </div>
                            <div class="p-3">
                                <div class="pfp-balance-line">
                                    <span>Approved Funding less Scheduled Allocation</span>
                                    <strong class="{{ ($totals['approved_funding_less_scheduled_allocation'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">{{ $money($totals['approved_funding_less_scheduled_allocation'] ?? 0) }}</strong>
                                </div>
                                <div class="pfp-balance-line">
                                    <span>Unpaid commitments plus unprocessed purchase requests</span>
                                    <strong class="{{ ($totals['commitment_pipeline_balance'] ?? 0) < 0 ? 'text-danger' : '' }}">{{ $money($totals['commitment_pipeline_balance'] ?? 0) }}</strong>
                                </div>
                                <div class="pfp-balance-line">
                                    <span>Commitment utilization of Approved Funding</span>
                                    <strong>{{ number_format($totals['commitment_rate'] ?? 0, 2) }}%</strong>
                                </div>
                                <div class="pfp-balance-line">
                                    <span>Disbursement utilization of Approved Funding</span>
                                    <strong>{{ number_format($totals['disbursement_rate'] ?? 0, 2) }}%</strong>
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

                <div class="pfp-panel mt-4">
                    <div class="pfp-panel-header">
                        <div>
                            <h5 class="fw-bold mb-1">Accounting Integrity</h5>
                            <div class="small text-muted">
                                Purchase-request, purchase-order, and disbursement control ratios
                            </div>
                        </div>
                    </div>
                    <div class="pfp-control-grid">
                        <div class="pfp-control-card">
                            <div class="pfp-control-label">Commitment Processing</div>
                            <div class="small text-muted mb-2">Unprocessed Purchase Requests Ratio</div>
                            <div class="pfp-control-value">{{ number_format($controls['commitment_processing_rate'] ?? 0, 1) }}%</div>
                            <div class="pfp-coverage-track" aria-hidden="true">
                                <span style="width: {{ min(100, max(0, (float) ($controls['commitment_processing_rate'] ?? 0))) }}%"></span>
                            </div>
                            <div class="small text-muted mt-2">
                                Unprocessed purchase requests ÷ committed
                            </div>
                        </div>
                        <div class="pfp-control-card">
                            <div class="pfp-control-label">Commitment Realization</div>
                            <div class="small text-muted mb-2">PO Coverage of Commitments</div>
                            <div class="pfp-control-value">{{ number_format($controls['commitment_realization_rate'] ?? 0, 1) }}%</div>
                            <div class="pfp-coverage-track" aria-hidden="true">
                                <span style="width: {{ min(100, max(0, (float) ($controls['commitment_realization_rate'] ?? 0))) }}%"></span>
                            </div>
                            <div class="small text-muted mt-2">
                                Purchase orders ÷ committed
                            </div>
                        </div>
                        <div class="pfp-control-card">
                            <div class="pfp-control-label">Disbursement Backlog</div>
                            <div class="small text-muted mb-2">Unpaid Commitments Ratio</div>
                            <div class="pfp-control-value">{{ number_format($controls['disbursement_backlog_rate'] ?? 0, 1) }}%</div>
                            <div class="pfp-coverage-track" aria-hidden="true">
                                <span style="width: {{ min(100, max(0, (float) ($controls['disbursement_backlog_rate'] ?? 0))) }}%"></span>
                            </div>
                            <div class="small text-muted mt-2">
                                Unpaid commitments ÷ purchase orders
                            </div>
                        </div>
                        <div class="pfp-control-card">
                            <div class="pfp-control-label">Disbursement Efficiency</div>
                            <div class="small text-muted mb-2">PO-to-Disbursement Conversion Rate</div>
                            <div class="pfp-control-value">{{ number_format($controls['disbursement_efficiency_rate'] ?? 0, 1) }}%</div>
                            <div class="pfp-coverage-track" aria-hidden="true">
                                <span style="width: {{ min(100, max(0, (float) ($controls['disbursement_efficiency_rate'] ?? 0))) }}%"></span>
                            </div>
                            <div class="small text-muted mt-2">
                                Disbursed ÷ purchase orders
                            </div>
                        </div>
                        <div class="pfp-control-card">
                            <div class="pfp-control-label">Funding Utilization Integrity Gap</div>
                            <div class="small text-muted mb-2">Idle Committed Funds Ratio</div>
                            <div class="pfp-control-value">{{ number_format($controls['funding_utilization_integrity_gap_rate'] ?? 0, 1) }}%</div>
                            <div class="pfp-coverage-track" aria-hidden="true">
                                <span style="width: {{ min(100, max(0, (float) ($controls['funding_utilization_integrity_gap_rate'] ?? 0))) }}%"></span>
                            </div>
                            <div class="small text-muted mt-2">
                                (Unpaid commitments + purchase requests) ÷ approved funding
                            </div>
                        </div>
                        <div class="pfp-control-card">
                            <div class="pfp-control-label">Procurement Pipeline Utilization</div>
                            <div class="small text-muted mb-2">Commitment Structural Gap</div>
                            <div class="pfp-control-value">{{ $money($controls['procurement_pipeline_utilization_gap'] ?? 0) }}</div>
                            <div class="small text-muted mt-2">
                                Committed − (purchase orders + purchase requests)
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-xl-7">
                        <div class="pfp-panel">
                            <div class="pfp-panel-header">
                                <h5 class="fw-bold mb-1">Scheduled Allocation vs Commitments vs Disbursements</h5>
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
                                <div class="small soft-note">How approved funding is currently positioned</div>
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
                            <div class="text-muted small">Scheduled allocation and execution by project, activity, and sub-activity in {{ $currency }}</div>
                        </div>
                        <span class="badge bg-primary-subtle text-primary align-self-start">{{ $currency }}</span>
                    </div>
                    <div class="table-responsive pfp-table-wrap">
                        <table class="table table-bordered pfp-table">
                            <thead>
                                <tr>
                                    <th>Program Structure</th>
                                    <th class="text-end">Scheduled</th>
                                    <th class="text-end">Committed</th>
                                    <th class="text-end">POs</th>
                                    <th class="text-end">Invoices</th>
                                    <th class="text-end">Disbursed</th>
                                    <th class="text-end">Scheduled Balance</th>
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
                            <tfoot>
                                <tr>
                                    <th>Scheduled total</th>
                                    <td class="text-end">{{ number_format($totals['scheduled_allocation'] ?? 0, 2) }}</td>
                                    <td class="text-end">{{ number_format($totals['committed'] ?? 0, 2) }}</td>
                                    <td class="text-end">{{ number_format($totals['purchase_orders'] ?? 0, 2) }}</td>
                                    <td class="text-end">{{ number_format($totals['invoiced'] ?? 0, 2) }}</td>
                                    <td class="text-end">{{ number_format($totals['disbursed'] ?? 0, 2) }}</td>
                                    <td class="text-end">{{ number_format(($totals['scheduled_allocation'] ?? 0) - ($totals['committed'] ?? 0), 2) }}</td>
                                    <td class="text-end">{{ number_format($totals['unpaid_commitments'] ?? 0, 2) }}</td>
                                    <td class="text-end">
                                        {{ number_format(($totals['scheduled_allocation'] ?? 0) > 0 ? (($totals['committed'] ?? 0) / $totals['scheduled_allocation']) * 100 : 0, 1) }}%
                                    </td>
                                    <td class="text-end">
                                        {{ number_format(($totals['scheduled_allocation'] ?? 0) > 0 ? (($totals['disbursed'] ?? 0) / $totals['scheduled_allocation']) * 100 : 0, 1) }}%
                                    </td>
                                    <td>—</td>
                                    <td>—</td>
                                    <td>—</td>
                                    <td>—</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <footer class="pfp-report-footer">
                    <div>
                        <div class="footer-kicker">Official financial control report</div>
                        <strong>ATTP · Project Financial Position</strong>
                        <div class="footer-copy">Reconciled financial execution reporting</div>
                    </div>
                    <div class="footer-context">
                        <div class="footer-kicker">Report scope</div>
                        <strong>{{ $program->program_id ?: $program->name }}</strong>
                        <div class="footer-copy">{{ $filters['label'] ?? 'Life to date' }} · {{ $currency }}</div>
                    </div>
                    <div class="footer-time">
                        <div class="footer-kicker">Generated in your local time</div>
                        <strong>
                            <time id="pfpGeneratedAt"
                                datetime="{{ $reportGeneratedAt->toIso8601String() }}"
                                data-instant="{{ $reportGeneratedAt->copy()->utc()->toIso8601String() }}">
                                {{ $reportGeneratedAt->format('d M Y, H:i:s T') }} ({{ $reportTimezone }})
                            </time>
                        </strong>
                        <div class="footer-copy" id="pfpGeneratedTimezone">{{ str_replace('_', ' ', $reportTimezone) }}</div>
                    </div>
                </footer>

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
                                        { label: 'Scheduled allocation', data: chartData.budget || [], backgroundColor: '#176b87' },
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
                                    labels: ['Disbursed', 'Unpaid Commitments', 'Unprocessed Purchase Requests', 'Funding Utilization Gap'],
                                    datasets: [{
                                        data: [
                                            Math.max(Number(totals.disbursed || 0), 0),
                                            Math.max(Number(totals.unpaid_commitments || 0), 0),
                                            Math.max(Number(totals.unprocessed_purchase_requests || 0), 0),
                                            Math.max(Number(totals.funding_utilization_gap || 0), 0),
                                        ],
                                        backgroundColor: ['#1d8f6f', '#f4b942', '#7c5ce7', '#176b87'],
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
            const exportLink = document.getElementById('pfpExportPdf');
            const generatedAt = document.getElementById('pfpGeneratedAt');
            const generatedTimezone = document.getElementById('pfpGeneratedTimezone');
            const browserTimezone = window.Intl
                ? (Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC')
                : 'UTC';

            if (generatedAt && window.Intl) {
                const instant = new Date(generatedAt.dataset.instant || generatedAt.dateTime);
                if (!Number.isNaN(instant.getTime())) {
                    const formatter = new Intl.DateTimeFormat(undefined, {
                        year: 'numeric',
                        month: 'short',
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit',
                        timeZoneName: 'short',
                        timeZone: browserTimezone,
                    });
                    generatedAt.textContent = `${formatter.format(instant)} (${browserTimezone})`;
                    generatedAt.dateTime = instant.toISOString();
                }
            }

            if (generatedTimezone) {
                generatedTimezone.textContent = browserTimezone.replace(/_/g, ' ');
            }

            const syncExportTimeContext = () => {
                if (!exportLink) {
                    return;
                }

                const exportUrl = new URL(exportLink.href, window.location.href);
                exportUrl.searchParams.set('report_timezone', browserTimezone);
                exportLink.href = exportUrl.toString();
            };

            syncExportTimeContext();
            exportLink?.addEventListener('click', syncExportTimeContext);

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
