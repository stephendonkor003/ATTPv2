@extends('layouts.app')

@section('content')
    @php
        $rows = collect($executionBreakdownRows ?? []);
        $componentRows = collect($componentBreakdownRows ?? []);
        $isSubComponentBreakdown = ($componentBreakdownLevel ?? 'component') === 'sub_component';
        $breakdownTitle = $isSubComponentBreakdown
            ? 'Sub-component Execution Performance Breakdown'
            : 'Component Breakdown';
        $breakdownNote = $isSubComponentBreakdown
            ? 'Execution performance for every sub-component within the selected component'
            : 'Total budget envelope followed by component-level execution using the same financial columns';
        $breakdownColumnLabel = $isSubComponentBreakdown ? 'Sub-component' : 'Component';
        $breakdownTotalLabel = $isSubComponentBreakdown
            ? 'Selected component and all sub-components'
            : 'All selected components';
        $globalBreakdownTitle = $isSubComponentBreakdown
            ? 'Selected Component - Global Execution Performance'
            : 'Execution Performance Breakdown';
        $globalBreakdownNote = $isSubComponentBreakdown
            ? 'Overall year-by-year execution performance for the selected component before the sub-component detail'
            : 'Year-by-year global commitments, planned commitments, disbursements, remaining balance, and rates';
        $totals = $executionBreakdownTotals ?? [
            'allocation' => $totalAllocation ?? 0,
            'commitment' => $totalCommitment ?? 0,
            'disbursement' => $totalDisbursements ?? 0,
            'remaining' => ($totalAllocation ?? 0) - ($totalCommitment ?? 0),
            'execution_rate' => $executionRate ?? 0,
            'disbursement_rate' => $disbursementRate ?? 0,
        ];
        $summary = $executionSummary ?? [];
        $currencyCode = $currency ?? ($summary['currency'] ?? 'USD');
        $money = fn ($value) => $currencyCode . ' ' . number_format((float) $value, 2);
        $compactMoney = function ($value) use ($currencyCode) {
            $value = (float) $value;
            if (abs($value) >= 1000000) {
                return $currencyCode . ' ' . number_format($value / 1000000, 2) . 'M';
            }
            if (abs($value) >= 1000) {
                return $currencyCode . ' ' . number_format($value / 1000, 1) . 'K';
            }
            return $currencyCode . ' ' . number_format($value, 2);
        };
        $percent = fn ($value, $decimals = 1) => number_format(max(0, (float) $value), $decimals) . '%';
        $scopeLabel = match ($scopeType ?? 'global') {
            'sector' => 'Sector: ' . ($scope?->name ?? 'N/A'),
            'program' => 'Program: ' . ($scope?->name ?? 'N/A'),
            'project' => 'Project: ' . ($scope?->name ?? 'N/A'),
            default => 'All sectors, programs, and projects',
        };
        $budgetEnvelope = (float) ($summary['budget_envelope'] ?? $totals['allocation'] ?? 0);
        $scheduledAllocation = (float) ($summary['scheduled_allocation'] ?? collect($rows)->sum('allocation'));
        $unallocatedEnvelope = (float) ($summary['unallocated_envelope'] ?? ($budgetEnvelope - $scheduledAllocation));
        $kpiCards = [
            [
                'label' => 'Budget Envelope',
                'value' => $compactMoney($budgetEnvelope),
                'meta' => abs($unallocatedEnvelope) > 0.01
                    ? $money($scheduledAllocation) . ' scheduled'
                    : $money($budgetEnvelope),
                'icon' => 'feather-target',
                'tone' => 'teal',
            ],
            [
                'label' => 'Planned Commitments',
                'value' => $compactMoney($totals['commitment'] ?? 0),
                'meta' => $percent($totals['execution_rate'] ?? 0) . ' commitment rate',
                'icon' => 'feather-lock',
                'tone' => 'gold',
            ],
            [
                'label' => 'Disbursed',
                'value' => $compactMoney($totals['disbursement'] ?? 0),
                'meta' => $percent($totals['disbursement_rate'] ?? 0) . ' paid',
                'icon' => 'feather-send',
                'tone' => 'green',
            ],
            [
                'label' => 'Remaining Global Commitments',
                'value' => $compactMoney($totals['remaining'] ?? 0),
                'meta' => $money($totals['remaining'] ?? 0),
                'icon' => 'feather-pie-chart',
                'tone' => 'blue',
            ],
            [
                'label' => 'Unpaid Commitments',
                'value' => $compactMoney($summary['unpaid_commitments'] ?? 0),
                'meta' => $money($summary['unpaid_commitments'] ?? 0),
                'icon' => 'feather-clock',
                'tone' => 'coral',
            ],
            [
                'label' => 'Peak Commitment Year',
                'value' => $summary['peak_commitment_year'] ?? 'N/A',
                'meta' => $compactMoney($summary['peak_commitment'] ?? 0),
                'icon' => 'feather-trending-up',
                'tone' => 'violet',
            ],
        ];
    @endphp

    <style>
        .execution-shell {
            --ink: #10212f;
            --muted: #667085;
            --line: #d9e2ea;
            --panel: #ffffff;
            --wash: #f4f7f9;
            --teal: #0f766e;
            --green: #168a5b;
            --gold: #b7791f;
            --blue: #2563eb;
            --coral: #d65a31;
            --violet: #6d5bd0;
            background: var(--wash);
            margin: -1.5rem;
            padding: 1.5rem;
            min-height: calc(100vh - 70px);
            color: var(--ink);
        }

        .execution-topbar {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 1rem;
            align-items: start;
            margin-bottom: 1rem;
        }

        .execution-title {
            font-size: 1.35rem;
            font-weight: 800;
            margin: 0;
            letter-spacing: 0;
        }

        .execution-scope {
            color: var(--muted);
            font-size: .92rem;
            margin-top: .25rem;
        }

        .execution-pdf-btn {
            background: var(--ink);
            border: 0;
            color: #fff;
            font-weight: 800;
            border-radius: 8px;
            padding: .72rem 1rem;
        }

        .execution-pdf-btn:hover,
        .execution-pdf-btn:focus {
            color: #fff;
            background: #0b1721;
        }

        .execution-pdf-btn.is-loading {
            cursor: wait;
            opacity: .82;
            pointer-events: none;
        }

        .execution-download-modal[hidden] {
            display: none !important;
        }

        .execution-download-modal {
            align-items: center;
            display: flex;
            inset: 0;
            justify-content: center;
            padding: 1rem;
            position: fixed;
            z-index: 1095;
        }

        .execution-download-backdrop {
            backdrop-filter: blur(5px);
            background: rgba(8, 20, 31, .72);
            inset: 0;
            position: absolute;
        }

        .execution-download-dialog {
            background: #fff;
            border: 1px solid rgba(217, 226, 234, .9);
            border-radius: 16px;
            box-shadow: 0 28px 80px rgba(5, 16, 25, .32);
            max-width: 510px;
            overflow: hidden;
            position: relative;
            width: 100%;
        }

        .execution-download-accent {
            background: linear-gradient(90deg, var(--teal), var(--gold), var(--coral));
            height: 5px;
        }

        .execution-download-content {
            padding: 1.5rem;
        }

        .execution-download-heading {
            align-items: center;
            display: flex;
            gap: 1rem;
        }

        .execution-download-spinner {
            align-items: center;
            background: #e7f6f3;
            border-radius: 14px;
            display: inline-flex;
            flex: 0 0 auto;
            height: 54px;
            justify-content: center;
            position: relative;
            width: 54px;
        }

        .execution-download-spinner::before {
            animation: executionPdfSpin .9s linear infinite;
            border: 4px solid rgba(15, 118, 110, .2);
            border-radius: 50%;
            border-top-color: var(--teal);
            content: "";
            height: 30px;
            width: 30px;
        }

        .execution-download-modal.is-ready .execution-download-spinner::before {
            animation: none;
            border: 0;
            content: "✓";
            color: var(--green);
            font-size: 1.75rem;
            font-weight: 900;
            height: auto;
            width: auto;
        }

        .execution-download-modal.is-error .execution-download-spinner {
            background: #fee2e2;
        }

        .execution-download-modal.is-error .execution-download-spinner::before {
            animation: none;
            border: 0;
            color: #b91c1c;
            content: "!";
            font-size: 1.75rem;
            font-weight: 900;
            height: auto;
            width: auto;
        }

        .execution-download-title {
            color: var(--ink);
            font-size: 1.12rem;
            font-weight: 900;
            margin: 0;
        }

        .execution-download-subtitle {
            color: var(--muted);
            font-size: .88rem;
            margin: .25rem 0 0;
        }

        .execution-download-status {
            align-items: flex-start;
            background: #f6f9fb;
            border: 1px solid var(--line);
            border-radius: 10px;
            display: flex;
            gap: .7rem;
            margin-top: 1.2rem;
            min-height: 66px;
            padding: .85rem;
        }

        .execution-download-pulse {
            animation: executionPdfPulse 1.25s ease-in-out infinite;
            background: var(--teal);
            border-radius: 50%;
            flex: 0 0 auto;
            height: 9px;
            margin-top: .3rem;
            width: 9px;
        }

        .execution-download-status strong {
            color: var(--ink);
            display: block;
            font-size: .84rem;
        }

        .execution-download-status span {
            color: var(--muted);
            display: block;
            font-size: .82rem;
            margin-top: .12rem;
        }

        .execution-download-progress {
            background: #e5edf2;
            border-radius: 999px;
            height: 8px;
            margin-top: 1rem;
            overflow: hidden;
            position: relative;
        }

        .execution-download-progress span {
            animation: executionPdfProgress 1.65s ease-in-out infinite;
            background: linear-gradient(90deg, var(--teal), var(--blue), var(--teal));
            border-radius: inherit;
            height: 100%;
            left: -45%;
            position: absolute;
            width: 45%;
        }

        .execution-download-modal.is-ready .execution-download-progress span {
            animation: none;
            background: var(--green);
            left: 0;
            width: 100%;
        }

        .execution-download-meta {
            color: var(--muted);
            display: flex;
            font-size: .78rem;
            justify-content: space-between;
            margin-top: .55rem;
        }

        .execution-download-actions {
            display: none;
            gap: .65rem;
            justify-content: flex-end;
            margin-top: 1rem;
        }

        .execution-download-modal.is-error .execution-download-actions {
            display: flex;
        }

        @keyframes executionPdfSpin {
            to { transform: rotate(360deg); }
        }

        @keyframes executionPdfPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(15, 118, 110, .3); opacity: .65; }
            50% { box-shadow: 0 0 0 7px rgba(15, 118, 110, 0); opacity: 1; }
        }

        @keyframes executionPdfProgress {
            0% { left: -45%; }
            100% { left: 105%; }
        }

        @media (prefers-reduced-motion: reduce) {
            .execution-download-spinner::before,
            .execution-download-pulse,
            .execution-download-progress span {
                animation-duration: 2.8s;
            }
        }

        .execution-filter-panel,
        .execution-hero,
        .execution-kpi,
        .execution-panel,
        .execution-table-panel,
        .execution-insight-panel {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: 0 10px 24px rgba(16, 33, 47, .06);
        }

        .execution-filter-panel {
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .execution-filter-panel .form-label {
            color: var(--muted);
            font-size: .78rem;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: .04em;
        }

        .execution-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(260px, .8fr);
            gap: 1rem;
            padding: 1.25rem;
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .execution-hero-figure {
            font-size: clamp(2rem, 5vw, 4rem);
            font-weight: 900;
            line-height: 1;
            letter-spacing: 0;
            margin: .15rem 0 .5rem;
        }

        .execution-hero-label {
            color: var(--muted);
            font-size: .82rem;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: .06em;
        }

        .execution-hero-sub {
            color: var(--muted);
            max-width: 680px;
            margin: 0;
        }

        .execution-hero-metrics {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .75rem;
        }

        .execution-mini {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: .85rem;
            min-width: 0;
        }

        .execution-mini span {
            display: block;
            color: var(--muted);
            font-size: .75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .execution-mini strong {
            display: block;
            font-size: 1.15rem;
            margin-top: .2rem;
        }

        .execution-kpi-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: .85rem;
            margin-bottom: 1rem;
        }

        .execution-kpi {
            padding: 1rem;
            min-width: 0;
            position: relative;
            overflow: hidden;
        }

        .execution-kpi::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 4px;
            background: var(--tone, var(--teal));
        }

        .execution-kpi .icon {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            color: var(--tone, var(--teal));
            background: color-mix(in srgb, var(--tone, var(--teal)) 12%, white);
            margin-bottom: .75rem;
        }

        .execution-kpi .label {
            color: var(--muted);
            font-size: .76rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .04em;
            white-space: normal;
        }

        .execution-kpi .value {
            font-size: 1.35rem;
            font-weight: 900;
            margin-top: .25rem;
            overflow-wrap: anywhere;
        }

        .execution-kpi .meta {
            color: var(--muted);
            font-size: .85rem;
            margin-top: .15rem;
        }

        .tone-teal { --tone: var(--teal); }
        .tone-green { --tone: var(--green); }
        .tone-gold { --tone: var(--gold); }
        .tone-blue { --tone: var(--blue); }
        .tone-coral { --tone: var(--coral); }
        .tone-violet { --tone: var(--violet); }

        .execution-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .execution-panel {
            padding: 1rem;
            min-height: 310px;
        }

        .execution-panel.span-12 { grid-column: span 12; }
        .execution-panel.span-8 { grid-column: span 8; }
        .execution-panel.span-6 { grid-column: span 6; }
        .execution-panel.span-4 { grid-column: span 4; }

        .execution-panel--primary {
            min-height: 390px;
        }

        .execution-panel--primary .execution-chart {
            min-height: 315px;
        }

        .execution-panel--mix {
            min-height: 440px;
            position: relative;
            overflow: hidden;
            border-color: rgba(37, 99, 235, .22);
            background:
                radial-gradient(circle at 92% 0%, rgba(37, 99, 235, .11), transparent 32%),
                linear-gradient(145deg, #ffffff 0%, #f8fbff 100%);
            box-shadow: 0 16px 36px rgba(37, 99, 235, .1);
        }

        .execution-panel--mix::before {
            content: "";
            position: absolute;
            inset: 0 0 auto;
            height: 4px;
            background: linear-gradient(90deg, #168a5b 0 33.33%, #d65a31 33.33% 66.66%, #2563eb 66.66%);
        }

        .execution-panel--mix .execution-chart {
            min-height: 365px;
            padding: .8rem .9rem .4rem;
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: 8px;
            background: rgba(255, 255, 255, .82);
        }

        .execution-mix-badge {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .4rem .68rem;
            border: 1px solid rgba(37, 99, 235, .18);
            border-radius: 999px;
            color: #1d4ed8;
            background: rgba(239, 246, 255, .92);
            font-size: .74rem;
            font-weight: 850;
            letter-spacing: .02em;
            white-space: nowrap;
        }

        .execution-panel-head {
            display: flex;
            justify-content: space-between;
            gap: .75rem;
            align-items: flex-start;
            margin-bottom: .75rem;
        }

        .execution-panel-title {
            font-weight: 900;
            margin: 0;
            font-size: .98rem;
        }

        .execution-panel-note {
            color: var(--muted);
            margin: .18rem 0 0;
            font-size: .84rem;
        }

        .execution-chart {
            position: relative;
            min-height: 235px;
        }

        .execution-table-panel {
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .execution-table-panel table {
            margin-bottom: 0;
        }

        .execution-table-panel thead th {
            white-space: nowrap;
            color: var(--muted);
            font-size: .76rem;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .execution-table-panel td,
        .execution-table-panel th {
            vertical-align: middle;
        }

        .execution-component-label {
            min-width: 180px;
        }

        .execution-component-label strong {
            display: block;
        }

        .execution-component-label span {
            color: var(--muted);
            display: block;
            font-size: .82rem;
            margin-top: .1rem;
        }

        .execution-total-row td {
            background: #eef6ff;
        }

        .execution-rate-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 68px;
            padding: .34rem .55rem;
            border-radius: 999px;
            font-weight: 900;
            font-size: .78rem;
        }

        .rate-low { color: #991b1b; background: #fee2e2; }
        .rate-mid { color: #92400e; background: #fef3c7; }
        .rate-good { color: #14532d; background: #dcfce7; }

        .execution-insight-panel {
            padding: 1rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 1400px) {
            .execution-kpi-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 992px) {
            .execution-topbar,
            .execution-hero {
                grid-template-columns: 1fr;
            }

            .execution-panel.span-8,
            .execution-panel.span-6,
            .execution-panel.span-4 {
                grid-column: span 12;
            }
        }

        @media (max-width: 768px) {
            .execution-shell {
                margin: -1rem;
                padding: 1rem;
            }

            .execution-kpi-grid,
            .execution-hero-metrics {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="execution-shell">
        <div class="execution-topbar">
            <div>
                <h4 class="execution-title">Execution Dashboard</h4>
                <div class="execution-scope">{{ $scopeLabel }}</div>
            </div>
            <a
                href="{{ route('finance.execution.dashboard.export.pdf', request()->query()) }}"
                class="btn execution-pdf-btn"
                id="executionPdfDownload"
                data-download-url="{{ route('finance.execution.dashboard.export.pdf', request()->query()) }}"
                data-status-url="{{ route('finance.execution.dashboard.export.status') }}"
                data-snapshot-hash="{{ $executionChartData['snapshot_hash'] ?? '' }}"
            >
                <i class="feather-download me-1"></i> Download PDF
            </a>
        </div>

        <div class="execution-filter-panel">
            <form method="GET" action="{{ route('finance.execution.dashboard') }}" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Sector</label>
                    <select name="sector_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All Sectors</option>
                        @foreach ($sectors as $sector)
                            <option value="{{ $sector->id }}" @selected(request('sector_id') == $sector->id)>
                                {{ $sector->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Program</label>
                    <select name="program_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All Programs</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->id }}" @selected(request('program_id') == $program->id)>
                                {{ $program->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Project</label>
                    <select name="project_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All Projects</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected(request('project_id') == $project->id)>
                                {{ $project->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>

        <section class="execution-hero">
            <div>
                <div class="execution-hero-label">Budget Envelope</div>
                <div class="execution-hero-figure">{{ $compactMoney($budgetEnvelope) }}</div>
                <p class="execution-hero-sub">
                    {{ $money($budgetEnvelope) }} approved for the selected execution scope.
                    @if (abs($unallocatedEnvelope) > 0.01)
                        {{ $money(abs($unallocatedEnvelope)) }}
                        {{ $unallocatedEnvelope > 0 ? 'remains undistributed across component years.' : 'is allocated above the approved envelope.' }}
                    @endif
                </p>
            </div>
            <div class="execution-hero-metrics">
                <div class="execution-mini">
                    <span>Commitment Rate</span>
                    <strong>{{ $percent($totals['execution_rate'] ?? 0, 1) }}</strong>
                </div>
                <div class="execution-mini">
                    <span>Disbursement Rate</span>
                    <strong>{{ $percent($totals['disbursement_rate'] ?? 0, 1) }}</strong>
                </div>
                <div class="execution-mini">
                    <span>Latest Year</span>
                    <strong>{{ $summary['latest_year'] ?? 'N/A' }}</strong>
                </div>
                <div class="execution-mini">
                    <span>Years</span>
                    <strong>{{ number_format($summary['active_years'] ?? count($years)) }}</strong>
                </div>
            </div>
        </section>

        <section class="execution-kpi-grid">
            @foreach ($kpiCards as $card)
                <div class="execution-kpi tone-{{ $card['tone'] }}">
                    <span class="icon"><i class="{{ $card['icon'] }}"></i></span>
                    <div class="label">{{ $card['label'] }}</div>
                    <div class="value">{{ $card['value'] }}</div>
                    <div class="meta">{{ $card['meta'] }}</div>
                </div>
            @endforeach
        </section>

        <section class="execution-grid">
            <div class="execution-panel span-12 execution-panel--primary">
                <div class="execution-panel-head">
                    <div>
                        <h6 class="execution-panel-title">Global, Planned, and Disbursed</h6>
                        <p class="execution-panel-note">Cumulative execution trend</p>
                    </div>
                </div>
                <div class="execution-chart"><canvas id="executionLineChart"></canvas></div>
            </div>

            <div class="execution-panel span-12 execution-panel--mix">
                <div class="execution-panel-head">
                    <div>
                        <h6 class="execution-panel-title">Execution Mix</h6>
                        <p class="execution-panel-note">How disbursed, unpaid, and remaining global commitments move cumulatively by year</p>
                    </div>
                    <span class="execution-mix-badge">
                        <i class="feather-trending-up"></i>
                        Cumulative line view
                    </span>
                </div>
                <div class="execution-chart"><canvas id="executionMixChart"></canvas></div>
            </div>

            <div class="execution-panel span-6">
                <div class="execution-panel-head">
                    <div>
                        <h6 class="execution-panel-title">Rate Movement</h6>
                        <p class="execution-panel-note">Planned and disbursed against global commitments</p>
                    </div>
                </div>
                <div class="execution-chart"><canvas id="executionRateChart"></canvas></div>
            </div>

            <div class="execution-panel span-6">
                <div class="execution-panel-head">
                    <div>
                        <h6 class="execution-panel-title">Cumulative Momentum</h6>
                        <p class="execution-panel-note">Running global, planned, and payment flow</p>
                    </div>
                </div>
                <div class="execution-chart"><canvas id="executionCumulativeChart"></canvas></div>
            </div>

            <div class="execution-panel span-6">
                <div class="execution-panel-head">
                    <div>
                        <h6 class="execution-panel-title">Cumulative Financial Profile</h6>
                        <p class="execution-panel-note">Running totals by year</p>
                    </div>
                </div>
                <div class="execution-chart"><canvas id="executionAnnualProfileChart"></canvas></div>
            </div>

            <div class="execution-panel span-6">
                <div class="execution-panel-head">
                    <div>
                        <h6 class="execution-panel-title">Variance Control</h6>
                        <p class="execution-panel-note">Running remaining global commitments after planned commitments</p>
                    </div>
                </div>
                <div class="execution-chart"><canvas id="executionVarianceChart"></canvas></div>
            </div>

            <div class="execution-panel span-6">
                <div class="execution-panel-head">
                    <div>
                        <h6 class="execution-panel-title">Quality Radar</h6>
                        <p class="execution-panel-note">Execution balance and coverage</p>
                    </div>
                </div>
                <div class="execution-chart"><canvas id="executionRadarChart"></canvas></div>
            </div>

            <div class="execution-panel span-6">
                <div class="execution-panel-head">
                    <div>
                        <h6 class="execution-panel-title">Exposure Concentration</h6>
                        <p class="execution-panel-note">Cumulative commitment scale and variance pressure</p>
                    </div>
                </div>
                <div class="execution-chart"><canvas id="executionBubbleChart"></canvas></div>
            </div>
        </section>

        <section class="execution-table-panel">
            <div class="execution-panel-head">
                <div>
                    <h5 class="execution-panel-title">{{ $globalBreakdownTitle }}</h5>
                    <p class="execution-panel-note">{{ $globalBreakdownNote }}</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle w-100" id="executionTable">
                    <thead>
                        <tr class="text-center">
                            <th>Year</th>
                            <th class="text-end">Global Commitments</th>
                            <th class="text-end">Planned Commitments</th>
                            <th class="text-end">Disbursed Amount</th>
                            <th class="text-end">Remaining</th>
                            <th class="text-center">Commitment Rate</th>
                            <th class="text-center">Disbursement Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $executionClass = ($row['execution_rate'] ?? 0) < 50 ? 'rate-low' : (($row['execution_rate'] ?? 0) < 80 ? 'rate-mid' : 'rate-good');
                                $disbursementClass = ($row['disbursement_rate'] ?? 0) < 50 ? 'rate-low' : (($row['disbursement_rate'] ?? 0) < 80 ? 'rate-mid' : 'rate-good');
                            @endphp
                            <tr>
                                <td class="fw-semibold text-center">{{ $row['year'] }}</td>
                                <td class="text-end">{{ number_format($row['allocation'], 2) }}</td>
                                <td class="text-end">{{ number_format($row['commitment'], 2) }}</td>
                                <td class="text-end">{{ number_format($row['disbursement'], 2) }}</td>
                                <td class="text-end fw-semibold">{{ number_format($row['remaining'], 2) }}</td>
                                <td class="text-center">
                                    <span class="execution-rate-pill {{ $executionClass }}">{{ $percent($row['execution_rate']) }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="execution-rate-pill {{ $disbursementClass }}">{{ $percent($row['disbursement_rate']) }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="text-center fw-bold">TOTAL</td>
                            <td class="text-end fw-bold">{{ number_format($totals['allocation'], 2) }}</td>
                            <td class="text-end fw-bold">{{ number_format($totals['commitment'], 2) }}</td>
                            <td class="text-end fw-bold">{{ number_format($totals['disbursement'], 2) }}</td>
                            <td class="text-end fw-bold">{{ number_format($totals['remaining'], 2) }}</td>
                            <td class="text-center"><span class="execution-rate-pill rate-good">{{ $percent($totals['execution_rate']) }}</span></td>
                            <td class="text-center"><span class="execution-rate-pill rate-good">{{ $percent($totals['disbursement_rate']) }}</span></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @if (abs($unallocatedEnvelope) > 0.01)
                <div class="alert alert-info border-0 mt-3 mb-0">
                    <strong>Envelope reconciliation:</strong>
                    the approved envelope is {{ $money($budgetEnvelope) }}, while
                    {{ $money($scheduledAllocation) }} is currently distributed across component years.
                    The {{ $money(abs($unallocatedEnvelope)) }} difference is included in the dashboard total
                    and shown separately in the component breakdown.
                </div>
            @endif
        </section>

        <section class="execution-table-panel">
            <div class="execution-panel-head">
                <div>
                    <h5 class="execution-panel-title">{{ $breakdownTitle }}</h5>
                    <p class="execution-panel-note">{{ $breakdownNote }}</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle w-100">
                    <thead>
                        <tr class="text-center">
                            <th>{{ $breakdownColumnLabel }}</th>
                            <th class="text-end">Global Commitments</th>
                            <th class="text-end">Planned Commitments</th>
                            <th class="text-end">Disbursed Amount</th>
                            <th class="text-end">Remaining</th>
                            <th class="text-center">Commitment Rate</th>
                            <th class="text-center">Disbursement Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $totalExecutionClass = ($totals['execution_rate'] ?? 0) < 50 ? 'rate-low' : (($totals['execution_rate'] ?? 0) < 80 ? 'rate-mid' : 'rate-good');
                            $totalDisbursementClass = ($totals['disbursement_rate'] ?? 0) < 50 ? 'rate-low' : (($totals['disbursement_rate'] ?? 0) < 80 ? 'rate-mid' : 'rate-good');
                        @endphp
                        <tr class="execution-total-row">
                            <td class="execution-component-label">
                                <strong>Total</strong>
                                <span>{{ $breakdownTotalLabel }}</span>
                            </td>
                            <td class="text-end fw-bold">{{ number_format($totals['allocation'], 2) }}</td>
                            <td class="text-end fw-bold">{{ number_format($totals['commitment'], 2) }}</td>
                            <td class="text-end fw-bold">{{ number_format($totals['disbursement'], 2) }}</td>
                            <td class="text-end fw-bold">{{ number_format($totals['remaining'], 2) }}</td>
                            <td class="text-center"><span class="execution-rate-pill {{ $totalExecutionClass }}">{{ $percent($totals['execution_rate']) }}</span></td>
                            <td class="text-center"><span class="execution-rate-pill {{ $totalDisbursementClass }}">{{ $percent($totals['disbursement_rate']) }}</span></td>
                        </tr>
                        @foreach ($componentRows as $component)
                            @php
                                $executionClass = ($component['execution_rate'] ?? 0) < 50 ? 'rate-low' : (($component['execution_rate'] ?? 0) < 80 ? 'rate-mid' : 'rate-good');
                                $disbursementClass = ($component['disbursement_rate'] ?? 0) < 50 ? 'rate-low' : (($component['disbursement_rate'] ?? 0) < 80 ? 'rate-mid' : 'rate-good');
                            @endphp
                            <tr>
                                <td class="execution-component-label">
                                    <strong>{{ $component['label'] }}</strong>
                                    @if (!empty($component['description']))
                                        <span>{{ $component['description'] }}</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ number_format($component['allocation'], 2) }}</td>
                                <td class="text-end">{{ number_format($component['commitment'], 2) }}</td>
                                <td class="text-end">{{ number_format($component['disbursement'], 2) }}</td>
                                <td class="text-end fw-semibold">{{ number_format($component['remaining'], 2) }}</td>
                                <td class="text-center">
                                    <span class="execution-rate-pill {{ $executionClass }}">{{ $percent($component['execution_rate']) }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="execution-rate-pill {{ $disbursementClass }}">{{ $percent($component['disbursement_rate']) }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="execution-insight-panel">
            <div class="execution-panel-head">
                <div>
                    <h5 class="execution-panel-title">Execution Insights</h5>
                    <p class="execution-panel-note">Risk and progress signals from the current financial position</p>
                </div>
            </div>
            @forelse($aiInsights as $insight)
                <div class="alert alert-{{ $insight['type'] }} mb-3">
                    <h6 class="fw-semibold mb-1">{{ $insight['title'] }}</h6>
                    <p class="mb-0">{{ $insight['message'] }}</p>
                </div>
            @empty
                <p class="text-muted mb-0">No significant execution risks or anomalies detected.</p>
            @endforelse
        </section>
    </div>

    <div
        class="execution-download-modal"
        id="executionPdfModal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="executionPdfModalTitle"
        aria-describedby="executionPdfStatusText"
        aria-hidden="true"
        hidden
    >
        <div class="execution-download-backdrop"></div>
        <div class="execution-download-dialog">
            <div class="execution-download-accent"></div>
            <div class="execution-download-content">
                <div class="execution-download-heading">
                    <div class="execution-download-spinner" aria-hidden="true"></div>
                    <div>
                        <h5 class="execution-download-title" id="executionPdfModalTitle">Preparing complete dashboard PDF</h5>
                        <p class="execution-download-subtitle">
                            Please keep this window open while the report is assembled.
                        </p>
                    </div>
                </div>

                <div class="execution-download-status" aria-live="polite" aria-atomic="true">
                    <span class="execution-download-pulse" aria-hidden="true"></span>
                    <div>
                        <strong id="executionPdfStatusLabel">Reading dashboard</strong>
                        <span id="executionPdfStatusText">Reading the selected filters and financial scope…</span>
                    </div>
                </div>

                <div
                    class="execution-download-progress"
                    id="executionPdfProgress"
                    role="progressbar"
                    aria-label="Generating execution dashboard PDF"
                    aria-valuetext="Generating report"
                >
                    <span></span>
                </div>

                <div class="execution-download-meta">
                    <span>Full dashboard · all graphs · all tables</span>
                    <span><strong id="executionPdfElapsed">0</strong>s elapsed</span>
                </div>

                <div class="execution-download-actions">
                    <button type="button" class="btn btn-light" id="executionPdfClose">Close</button>
                    <button type="button" class="btn btn-dark" id="executionPdfRetry">
                        <i class="feather-refresh-cw me-1"></i> Try again
                    </button>
                </div>
            </div>
        </div>
    </div>
    <iframe
        id="executionPdfDownloadFrame"
        name="executionPdfDownloadFrame"
        title="Execution Dashboard PDF download"
        hidden
    ></iframe>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const pdfButton = document.getElementById('executionPdfDownload');
            const pdfModal = document.getElementById('executionPdfModal');
            const pdfModalTitle = document.getElementById('executionPdfModalTitle');
            const pdfStatusLabel = document.getElementById('executionPdfStatusLabel');
            const pdfStatusText = document.getElementById('executionPdfStatusText');
            const pdfElapsed = document.getElementById('executionPdfElapsed');
            const pdfRetry = document.getElementById('executionPdfRetry');
            const pdfClose = document.getElementById('executionPdfClose');
            const pdfProgress = document.getElementById('executionPdfProgress');
            const pdfDownloadFrame = document.getElementById('executionPdfDownloadFrame');
            const pdfButtonDefaultHtml = pdfButton ? pdfButton.innerHTML : '';
            const pdfReadingSteps = [
                ['Reading selected filters', 'Reading the selected sector, programme, project, and execution years…'],
                ['Reading budget cards', 'Reading the budget envelope, commitments, disbursements, and remaining balance…'],
                ['Reading dashboard graphs', 'Reading all eight graph datasets and cumulative financial movements…'],
                ['Drawing report graphs', 'Drawing print-ready trend, mix, rate, variance, radar, and exposure graphs…'],
                ['Reading breakdown tables', 'Reading every component and year-by-year execution record…'],
                ['Reading execution insights', 'Reading risk signals, performance findings, and reconciliation notes…'],
                ['Finishing the document', 'Applying the report header, page layout, footer, and page numbers…'],
            ];
            let pdfIsDownloading = false;
            let pdfStepTimer = null;
            let pdfElapsedTimer = null;
            let pdfStatusTimer = null;
            let pdfDeadlineTimer = null;
            let pdfStartedAt = 0;
            let pdfStatusFailures = 0;

            const stopPdfTimers = () => {
                window.clearInterval(pdfStepTimer);
                window.clearInterval(pdfElapsedTimer);
                window.clearTimeout(pdfStatusTimer);
                window.clearTimeout(pdfDeadlineTimer);
                pdfStepTimer = null;
                pdfElapsedTimer = null;
                pdfStatusTimer = null;
                pdfDeadlineTimer = null;
            };

            const setPdfButtonLoading = loading => {
                if (!pdfButton) {
                    return;
                }

                pdfButton.classList.toggle('is-loading', loading);
                pdfButton.setAttribute('aria-disabled', loading ? 'true' : 'false');
                pdfButton.innerHTML = loading
                    ? '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Preparing PDF…'
                    : pdfButtonDefaultHtml;
            };

            const showPdfModal = () => {
                if (!pdfModal) {
                    return;
                }

                pdfModal.hidden = false;
                pdfModal.setAttribute('aria-hidden', 'false');
                pdfModal.classList.remove('is-error', 'is-ready');
                document.body.style.overflow = 'hidden';
                pdfModalTitle.textContent = 'Preparing complete dashboard PDF';
                pdfStatusLabel.textContent = pdfReadingSteps[0][0];
                pdfStatusText.textContent = pdfReadingSteps[0][1];
                pdfProgress.setAttribute('aria-valuetext', 'Generating report');
                pdfElapsed.textContent = '0';

                let stepIndex = 0;
                pdfStartedAt = Date.now();
                stopPdfTimers();
                pdfStepTimer = window.setInterval(() => {
                    stepIndex = (stepIndex + 1) % pdfReadingSteps.length;
                    pdfStatusLabel.textContent = pdfReadingSteps[stepIndex][0];
                    pdfStatusText.textContent = pdfReadingSteps[stepIndex][1];
                }, 2300);
                pdfElapsedTimer = window.setInterval(() => {
                    pdfElapsed.textContent = String(Math.floor((Date.now() - pdfStartedAt) / 1000));
                }, 1000);
            };

            const hidePdfModal = () => {
                if (!pdfModal) {
                    return;
                }

                stopPdfTimers();
                pdfModal.hidden = true;
                pdfModal.setAttribute('aria-hidden', 'true');
                pdfModal.classList.remove('is-error', 'is-ready');
                document.body.style.overflow = '';
            };

            const createDownloadToken = () => {
                if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                    return window.crypto.randomUUID().replace(/-/g, '');
                }

                if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
                    const bytes = new Uint8Array(24);
                    window.crypto.getRandomValues(bytes);
                    return Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
                }

                return `${Date.now().toString(36)}${Math.random().toString(36).slice(2)}${Math.random().toString(36).slice(2)}`;
            };

            const showPdfError = error => {
                stopPdfTimers();
                pdfIsDownloading = false;
                setPdfButtonLoading(false);
                pdfModal.classList.add('is-error');
                pdfModalTitle.textContent = 'The PDF could not be downloaded';
                pdfStatusLabel.textContent = 'Report generation needs attention';
                pdfStatusText.textContent = typeof error === 'string'
                    ? error
                    : (error instanceof Error
                        ? error.message
                        : 'The report could not be generated. Please try again.');
                pdfProgress.setAttribute('aria-valuetext', 'Download failed');
            };

            const completePdfDownload = status => {
                stopPdfTimers();
                pdfModal.classList.add('is-ready');
                pdfModalTitle.textContent = 'Report ready';
                pdfStatusLabel.textContent = 'Download handed off successfully';
                pdfStatusText.textContent = status?.message
                    || 'The complete Execution Dashboard PDF has been sent to your browser or download manager.';
                pdfProgress.setAttribute('aria-valuetext', 'Download started');

                window.setTimeout(() => {
                    pdfIsDownloading = false;
                    setPdfButtonLoading(false);
                    hidePdfModal();
                }, 1400);
            };

            const pollPdfStatus = async (statusUrl, downloadToken) => {
                if (!pdfIsDownloading) {
                    return;
                }

                try {
                    const url = new URL(statusUrl, window.location.href);
                    url.searchParams.set('download_token', downloadToken);
                    url.searchParams.set('_', String(Date.now()));
                    const response = await fetch(url.toString(), {
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        cache: 'no-store',
                    });

                    if (response.status === 401 || response.status === 403) {
                        showPdfError('Your session or report permission has expired. Refresh the page, sign in, and try again.');
                        return;
                    }
                    if (!response.ok) {
                        throw new Error(`Status service returned HTTP ${response.status}.`);
                    }

                    const status = await response.json();
                    pdfStatusFailures = 0;
                    if (status.status === 'ready') {
                        completePdfDownload(status);
                        return;
                    }
                    if (status.status === 'failed') {
                        showPdfError(status.message || 'The server could not generate the PDF. Please try again.');
                        return;
                    }
                } catch (error) {
                    pdfStatusFailures += 1;
                    if (pdfStatusFailures >= 4) {
                        pdfStatusLabel.textContent = 'Reconnecting to report status';
                        pdfStatusText.textContent = 'The download request is still active. Reconnecting without cancelling it…';
                    }
                }

                pdfStatusTimer = window.setTimeout(
                    () => pollPdfStatus(statusUrl, downloadToken),
                    pdfStatusFailures >= 4 ? 1800 : 700
                );
            };

            const startPdfDownload = () => {
                if (!pdfButton || !pdfModal || !pdfDownloadFrame || pdfIsDownloading) {
                    return;
                }

                const statusUrl = pdfButton.dataset.statusUrl;
                if (!statusUrl) {
                    showPdfError('The report status service is unavailable. Refresh the page and try again.');
                    return;
                }

                pdfIsDownloading = true;
                pdfStatusFailures = 0;
                setPdfButtonLoading(true);
                showPdfModal();

                const downloadToken = createDownloadToken();
                const downloadUrl = new URL(
                    pdfButton.dataset.downloadUrl || pdfButton.href,
                    window.location.href
                );
                downloadUrl.searchParams.set('download_token', downloadToken);
                if (pdfButton.dataset.snapshotHash) {
                    downloadUrl.searchParams.set('dashboard_snapshot', pdfButton.dataset.snapshotHash);
                }

                pdfDeadlineTimer = window.setTimeout(() => {
                    showPdfError(
                        'The report is taking longer than three minutes. The request was not cancelled; check your browser or download manager, then try again if no file appears.'
                    );
                }, 180000);

                pdfDownloadFrame.src = downloadUrl.toString();
                pdfStatusTimer = window.setTimeout(
                    () => pollPdfStatus(statusUrl, downloadToken),
                    500
                );
            };

            if (pdfButton && pdfModal) {
                pdfButton.addEventListener('click', event => {
                    event.preventDefault();
                    startPdfDownload();
                });
                pdfRetry.addEventListener('click', startPdfDownload);
                pdfClose.addEventListener('click', () => {
                    pdfIsDownloading = false;
                    setPdfButtonLoading(false);
                    hidePdfModal();
                });
            }

            if (!window.Chart) {
                return;
            }

            const rows = @json($rows->values());
            const totals = @json($totals);
            const radarMetrics = @json($radarMetrics ?? []);
            const chartData = @json($executionChartData ?? []);
            const currency = @json($currencyCode);
            const labels = chartData.labels || rows.map(row => String(row.year));
            const allocations = chartData.allocations || rows.map(row => Number(row.allocation || 0));
            const commitments = chartData.commitments || rows.map(row => Number(row.commitment || 0));
            const disbursements = chartData.disbursements || rows.map(row => Number(row.disbursement || 0));
            const runningTotal = values => {
                let total = 0;
                return values.map(value => {
                    total += Number(value || 0);
                    return Number(total.toFixed(2));
                });
            };
            const cumulativeAllocation = chartData.cumulative_allocation || runningTotal(allocations);
            const cumulativeCommitment = chartData.cumulative_commitment || runningTotal(commitments);
            const cumulativeDisbursement = chartData.cumulative_disbursement || runningTotal(disbursements);
            const cumulativeRemaining = chartData.cumulative_remaining || cumulativeAllocation.map((value, index) => (
                value - Number(cumulativeCommitment[index] || 0)
            ));
            const cumulativeExecutionRates = chartData.cumulative_execution_rates || cumulativeAllocation.map((value, index) => (
                value > 0 ? (Number(cumulativeCommitment[index] || 0) / value) * 100 : 0
            ));
            const cumulativeDisbursementRates = chartData.cumulative_disbursement_rates || cumulativeAllocation.map((value, index) => (
                value > 0 ? (Number(cumulativeDisbursement[index] || 0) / value) * 100 : 0
            ));
            const cumulativeUnpaidCommitments = chartData.cumulative_unpaid_commitments
                || cumulativeCommitment.map((value, index) => (
                    Math.max(Number(value || 0) - Number(cumulativeDisbursement[index] || 0), 0)
                ));
            const cumulativeGlobalRemaining = chartData.cumulative_global_remaining
                || cumulativeCommitment.map(value => (
                    Math.max(Number(totals.allocation || 0) - Number(value || 0), 0)
                ));
            const mixTrend = Array.isArray(chartData.mix_trend) && chartData.mix_trend.length
                ? chartData.mix_trend
                : [
                    { label: 'Disbursed', values: cumulativeDisbursement, color: '#168a5b' },
                    { label: 'Unpaid Commitments', values: cumulativeUnpaidCommitments, color: '#d65a31' },
                    { label: 'Remaining Global Commitments', values: cumulativeGlobalRemaining, color: '#2563eb' },
            ];
            const qualityLabels = chartData.quality_labels
                || ['Commitment Rate', 'Timeliness', 'Consistency', 'Coverage', 'Risk Control'];
            const qualityValues = chartData.quality_values || [
                Number(radarMetrics.budget_utilization || 0),
                Number(radarMetrics.timeliness || 0),
                Number(radarMetrics.consistency || 0),
                Number(radarMetrics.coverage || 0),
                Number(radarMetrics.risk_exposure || 0)
            ];

            const money = value => `${currency} ${new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(Number(value || 0))}`;
            const compactMoney = value => `${currency} ${new Intl.NumberFormat('en-US', {
                notation: 'compact',
                maximumFractionDigits: 2
            }).format(Number(value || 0))}`;

            Chart.defaults.font.family = "'Inter','Segoe UI',sans-serif";
            Chart.defaults.font.size = 12;
            Chart.defaults.color = '#667085';

            const commonPlugins = {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 8
                    }
                },
                tooltip: {
                    callbacks: {
                        label: context => {
                            const label = context.dataset.label || context.label || '';
                            const value = context.parsed.y ?? context.parsed.x ?? context.parsed ?? 0;
                            return `${label}: ${context.dataset.percent ? Number(value).toFixed(1) + '%' : money(value)}`;
                        }
                    }
                }
            };

            const makeChart = (id, config) => {
                const element = document.getElementById(id);
                if (!element) {
                    return null;
                }
                return new Chart(element, config);
            };

            makeChart('executionLineChart', {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Cumulative Global Commitments',
                            data: cumulativeAllocation,
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37,99,235,.12)',
                            fill: true,
                            tension: .35,
                            borderWidth: 2
                        },
                        {
                            label: 'Cumulative Planned Commitments',
                            data: cumulativeCommitment,
                            borderColor: '#b7791f',
                            backgroundColor: 'rgba(183,121,31,.12)',
                            fill: true,
                            tension: .35,
                            borderWidth: 2
                        },
                        {
                            label: 'Cumulative Recorded Disbursements',
                            data: cumulativeDisbursement,
                            borderColor: '#168a5b',
                            backgroundColor: 'rgba(22,138,91,.1)',
                            fill: false,
                            tension: .35,
                            borderWidth: 3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: commonPlugins,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: value => compactMoney(value) }
                        }
                    }
                }
            });

            makeChart('executionMixChart', {
                type: 'line',
                data: {
                    labels,
                    datasets: mixTrend.map((series, index) => {
                        const color = series.color || ['#168a5b', '#d65a31', '#2563eb'][index] || '#2563eb';
                        const fills = [
                            'rgba(22,138,91,.11)',
                            'rgba(214,90,49,.09)',
                            'rgba(37,99,235,.08)'
                        ];

                        return {
                            label: series.label,
                            data: (series.values || []).map(value => Number(value || 0)),
                            borderColor: color,
                            backgroundColor: fills[index] || 'rgba(37,99,235,.08)',
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: color,
                            pointBorderWidth: 2.5,
                            pointRadius: 4,
                            pointHoverRadius: 7,
                            pointHoverBackgroundColor: color,
                            pointHoverBorderColor: '#ffffff',
                            pointHoverBorderWidth: 3,
                            borderWidth: 3,
                            fill: 'origin',
                            tension: .4,
                            cubicInterpolationMode: 'monotone',
                            order: 3 - index,
                        };
                    })
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    layout: {
                        padding: {
                            top: 4,
                            right: 8,
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            align: 'end',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'line',
                                boxWidth: 26,
                                boxHeight: 8,
                                padding: 18,
                                font: {
                                    weight: 700,
                                },
                                sort: (first, second) => first.datasetIndex - second.datasetIndex,
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, .94)',
                            titleColor: '#ffffff',
                            bodyColor: '#e2e8f0',
                            padding: 12,
                            cornerRadius: 8,
                            displayColors: true,
                            callbacks: {
                                title: items => items.length ? `Execution mix · ${items[0].label}` : '',
                                label: context => {
                                    const value = Number(context.parsed.y || 0);
                                    const envelope = Math.max(Number(totals.allocation || 0), 1);
                                    const share = (value / envelope) * 100;
                                    return ` ${context.dataset.label}: ${money(value)} (${share.toFixed(1)}%)`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false,
                            },
                            border: {
                                display: false,
                            },
                            ticks: {
                                color: '#475569',
                                font: {
                                    weight: 700,
                                },
                                padding: 8,
                            }
                        },
                        y: {
                            beginAtZero: true,
                            suggestedMax: Number(totals.allocation || 0),
                            grid: {
                                color: 'rgba(148, 163, 184, .2)',
                                drawTicks: false,
                            },
                            border: {
                                display: false,
                            },
                            ticks: {
                                callback: value => compactMoney(value),
                                padding: 10,
                                maxTicksLimit: 6,
                            }
                        }
                    }
                }
            });

            makeChart('executionRateChart', {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Commitment Rate',
                            data: cumulativeExecutionRates,
                            percent: true,
                            borderColor: '#0f766e',
                            backgroundColor: 'rgba(15,118,110,.13)',
                            fill: true,
                            tension: .35,
                            borderWidth: 2
                        },
                        {
                            label: 'Disbursement Rate',
                            data: cumulativeDisbursementRates,
                            percent: true,
                            borderColor: '#6d5bd0',
                            backgroundColor: 'rgba(109,91,208,.13)',
                            fill: true,
                            tension: .35,
                            borderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: commonPlugins,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: value => `${value}%` }
                        }
                    }
                }
            });

            makeChart('executionCumulativeChart', {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Cumulative Global Commitments',
                            data: cumulativeAllocation,
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37,99,235,.12)',
                            fill: true,
                            tension: .3
                        },
                        {
                            label: 'Cumulative Planned Commitments',
                            data: cumulativeCommitment,
                            borderColor: '#b7791f',
                            backgroundColor: 'rgba(183,121,31,.12)',
                            fill: true,
                            tension: .3
                        },
                        {
                            label: 'Cumulative Recorded Disbursements',
                            data: cumulativeDisbursement,
                            borderColor: '#168a5b',
                            backgroundColor: 'rgba(22,138,91,.12)',
                            fill: true,
                            tension: .3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: commonPlugins,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: value => compactMoney(value) }
                        }
                    }
                }
            });

            makeChart('executionAnnualProfileChart', {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        { label: 'Cumulative Global Commitments', data: cumulativeAllocation, backgroundColor: '#2563eb' },
                        { label: 'Cumulative Planned Commitments', data: cumulativeCommitment, backgroundColor: '#b7791f' },
                        { label: 'Cumulative Recorded Disbursements', data: cumulativeDisbursement, backgroundColor: '#168a5b' }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: commonPlugins,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: value => compactMoney(value) }
                        }
                    }
                }
            });

            makeChart('executionVarianceChart', {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Remaining Global Commitments',
                        data: cumulativeRemaining,
                        backgroundColor: cumulativeRemaining.map(value => value > 0 ? '#0f766e' : '#d65a31'),
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: commonPlugins,
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: { callback: value => compactMoney(value) }
                        }
                    }
                }
            });

            makeChart('executionRadarChart', {
                type: 'radar',
                data: {
                    labels: qualityLabels,
                    datasets: [{
                        label: 'Score',
                        data: qualityValues,
                        backgroundColor: 'rgba(109,91,208,.18)',
                        borderColor: '#6d5bd0',
                        pointBackgroundColor: '#6d5bd0'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: commonPlugins,
                    scales: {
                        r: {
                            min: 0,
                            max: 100,
                            ticks: { callback: value => `${value}%` }
                        }
                    }
                }
            });

            makeChart('executionBubbleChart', {
                type: 'bubble',
                data: {
                    datasets: [{
                        label: 'Year Exposure',
                        data: labels.map((year, index) => ({
                            x: Number(cumulativeExecutionRates[index] || 0),
                            y: Number(cumulativeCommitment[index] || 0),
                            r: Math.max(5, Math.min(22, Math.sqrt(Math.max(Math.abs(Number(cumulativeRemaining[index] || 0)), 1)) / 900)),
                            year
                        })),
                        backgroundColor: 'rgba(214,90,49,.38)',
                        borderColor: '#d65a31'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        ...commonPlugins,
                        tooltip: {
                            callbacks: {
                                label: context => `${context.raw.year}: ${context.raw.x.toFixed(1)}%, ${money(context.raw.y)} cumulative commitment`
                            }
                        }
                    },
                    scales: {
                        x: {
                            min: 0,
                            title: { display: true, text: 'Commitment Rate' },
                            ticks: { callback: value => `${value}%` }
                        },
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Commitment' },
                            ticks: { callback: value => compactMoney(value) }
                        }
                    }
                }
            });

            const table = document.getElementById('executionTable');
            if (table && window.DataTable) {
                new DataTable(table, {
                    paging: true,
                    searching: true,
                    ordering: true,
                    responsive: true,
                    pageLength: 10,
                    lengthMenu: [5, 10, 25, 50],
                    order: [[0, 'asc']],
                    language: {
                        search: 'Search year:',
                        lengthMenu: 'Show _MENU_ entries',
                        info: 'Showing _START_ to _END_ of _TOTAL_ records'
                    }
                });
            }
        });
    </script>
@endsection
