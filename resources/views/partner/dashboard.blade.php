@extends('layouts.partner')

@push('styles')
    <style>
        .partner-dashboard-hero {
            position: relative;
            overflow: hidden;
            display: flex;
            justify-content: space-between;
            gap: 18px;
            border-radius: 8px;
            padding: 20px;
            color: #fff;
            background: linear-gradient(135deg, #043b32 0%, #0f766e 54%, #123c69 100%);
            box-shadow: 0 14px 34px rgba(17, 32, 51, .14);
        }

        .partner-dashboard-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, rgba(255, 255, 255, .10) 1px, transparent 1px),
                linear-gradient(180deg, rgba(255, 255, 255, .08) 1px, transparent 1px);
            background-size: 42px 42px;
            opacity: .18;
        }

        .partner-hero-content,
        .partner-hero-metrics {
            position: relative;
            z-index: 1;
        }

        .partner-hero-content {
            max-width: 680px;
        }

        .partner-hero-kicker {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #ffe9bd;
            font-size: .68rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: 8px;
        }

        .partner-hero-title {
            color: #fff;
            font-size: 1.28rem;
            font-weight: 900;
            line-height: 1.22;
            margin-bottom: 6px;
        }

        .partner-hero-copy {
            color: rgba(255, 255, 255, .78);
            max-width: 620px;
            margin-bottom: 0;
            font-size: .84rem;
        }

        .partner-hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 14px;
        }

        .partner-hero-actions .btn {
            border-radius: 8px;
            font-weight: 800;
        }

        .partner-hero-metrics {
            display: grid;
            grid-template-columns: repeat(2, minmax(118px, 1fr));
            gap: 8px;
            min-width: 270px;
        }

        .partner-hero-metric {
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 8px;
            padding: 10px;
            background: rgba(255, 255, 255, .10);
            backdrop-filter: blur(10px);
        }

        .partner-hero-metric span {
            display: block;
            color: rgba(255, 255, 255, .72);
            font-size: .62rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 4px;
        }

        .partner-hero-metric strong {
            display: block;
            font-size: .9rem;
            line-height: 1.2;
        }

        .partner-stat-card {
            min-height: 128px;
            border-radius: 8px;
            padding: 14px;
            background: #fff;
            box-shadow: 0 8px 22px rgba(17, 32, 51, .06);
        }

        .partner-stat-card.is-green {
            border-left: 4px solid #0f766e !important;
        }

        .partner-stat-card.is-gold {
            border-left: 4px solid #f5b84b !important;
        }

        .partner-stat-card.is-blue {
            border-left: 4px solid #2563eb !important;
        }

        .partner-stat-card.is-slate {
            border-left: 4px solid #475569 !important;
        }

        .partner-stat-icon {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            font-size: 1.05rem;
        }

        .partner-stat-icon.is-green {
            color: #0f766e;
            background: #dff5ee;
        }

        .partner-stat-icon.is-gold {
            color: #a15c00;
            background: #fff2cc;
        }

        .partner-stat-icon.is-blue {
            color: #2563eb;
            background: #dbeafe;
        }

        .partner-stat-icon.is-slate {
            color: #334155;
            background: #e2e8f0;
        }

        .partner-stat-value {
            color: #172033;
            font-size: 1.08rem;
            font-weight: 900;
            line-height: 1.2;
            margin-bottom: 4px;
        }

        .partner-stat-label {
            color: #667085;
            font-size: .76rem;
            font-weight: 800;
            margin-bottom: 9px;
        }

        .partner-stat-hint {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #0f766e;
            background: #eefbf7;
            border-radius: 999px;
            padding: 4px 8px;
            font-size: .66rem;
            font-weight: 800;
        }

        .partner-analytics-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(260px, .65fr);
            gap: 14px;
        }

        .partner-panel {
            border-radius: 8px;
            border: 1px solid #dbe6ef;
            background: #fff;
            box-shadow: 0 8px 22px rgba(17, 32, 51, .06);
        }

        .partner-panel-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 16px;
            border-bottom: 1px solid #e8eef5;
        }

        .partner-panel-body {
            padding: 16px;
        }

        .partner-chart-row + .partner-chart-row {
            margin-top: 12px;
        }

        .partner-chart-meta {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            color: #172033;
            font-weight: 800;
            margin-bottom: 6px;
            font-size: .82rem;
        }

        .partner-chart-meta small {
            color: #667085;
            font-weight: 800;
            white-space: nowrap;
            font-size: .72rem;
        }

        .partner-chart-track {
            height: 9px;
            border-radius: 999px;
            overflow: hidden;
            background: #e8eef5;
        }

        .partner-chart-fill {
            height: 100%;
            width: var(--bar-width);
            border-radius: inherit;
            background: linear-gradient(90deg, #0f766e 0%, #f5b84b 100%);
        }

        .partner-ring-wrap {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 14px;
        }

        .partner-ring {
            width: 92px;
            height: 92px;
            flex: 0 0 92px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: conic-gradient(#0f766e var(--ring-value), #e8eef5 0);
        }

        .partner-ring-inner {
            width: 64px;
            height: 64px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            color: #172033;
            background: #fff;
            font-size: .96rem;
            font-weight: 900;
        }

        .partner-insight-list {
            display: grid;
            gap: 10px;
        }

        .partner-insight-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 9px 10px;
            border: 1px solid #e8eef5;
            border-radius: 8px;
            background: #f8fafc;
            font-size: .82rem;
        }

        .partner-action-card {
            display: block;
            height: 100%;
            border-radius: 8px;
            padding: 14px;
            color: #172033;
            text-decoration: none;
            background: #fff;
            box-shadow: 0 8px 22px rgba(17, 32, 51, .06);
        }

        .partner-action-icon {
            width: 36px;
            height: 36px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            margin-bottom: 10px;
            font-size: 1rem;
        }

        .partner-action-icon.tone-primary {
            color: #2563eb;
            background: #dbeafe;
        }

        .partner-action-icon.tone-success {
            color: #0f766e;
            background: #dff5ee;
        }

        .partner-action-icon.tone-warning {
            color: #a15c00;
            background: #fff2cc;
        }

        .partner-action-icon.tone-info {
            color: #0369a1;
            background: #e0f2fe;
        }

        .partner-soft-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 6px 10px;
            color: #0f766e;
            background: #dff5ee;
            font-size: .68rem;
            font-weight: 900;
        }

        .partner-table-card .table thead th {
            color: #667085;
            background: #f8fafc;
            border-bottom: 1px solid #e8eef5;
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .partner-table-card .table tbody tr {
            transition: background-color .16s ease;
        }

        .partner-table-card .table tbody tr:hover {
            background: #f6fbfa;
        }

        .partner-empty-state {
            padding: 28px;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            color: #667085;
            text-align: center;
            background: #f8fafc;
            font-size: .84rem;
        }

        @media (max-width: 1199.98px) {
            .partner-dashboard-hero,
            .partner-analytics-grid {
                grid-template-columns: 1fr;
            }

            .partner-dashboard-hero {
                display: grid;
            }

            .partner-hero-metrics {
                min-width: 0;
            }
        }

        @media (max-width: 575.98px) {
            .partner-dashboard-hero,
            .partner-panel-body,
            .partner-panel-header {
                padding: 16px;
            }

            .partner-hero-metrics {
                grid-template-columns: 1fr;
            }

            .partner-ring-wrap {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
@endpush

@section('content')
@php
    $totalFunding = (float) ($stats['total_funding'] ?? 0);
    $fundsRemaining = (float) ($stats['funds_remaining'] ?? 0);
    $fundsUtilized = max($totalFunding - $fundsRemaining, 0);
    $utilizationPercent = $totalFunding > 0 ? min(100, max(0, round(($fundsUtilized / $totalFunding) * 100))) : 0;
    $balancePercent = $totalFunding > 0 ? min(100, max(0, round(($fundsRemaining / $totalFunding) * 100))) : 0;
    $fundingMax = max((float) ($fundings->max('approved_amount') ?? 0), 1);
    $fundingChartRows = $fundings->take(6);
    $projectActivityMax = max((int) ($fundedProjects->max('activities_count') ?? 0), 1);
    $portfolioCards = [
        [
            'label' => __('partner.total_programs'),
            'value' => number_format($stats['total_programs'] ?? 0),
            'icon' => 'feather-folder',
            'tone' => 'green',
            'hint' => 'Funded program portfolio',
        ],
        [
            'label' => __('partner.total_funding'),
            'value' => $funder->currency . ' ' . number_format($totalFunding, 2),
            'icon' => 'feather-dollar-sign',
            'tone' => 'gold',
            'hint' => $utilizationPercent . '% currently utilized',
        ],
        [
            'label' => 'Funded Projects',
            'value' => number_format($stats['total_projects'] ?? 0),
            'icon' => 'feather-activity',
            'tone' => 'blue',
            'hint' => 'Project delivery visibility',
        ],
        [
            'label' => 'Think Tanks',
            'value' => number_format($stats['think_tanks'] ?? 0),
            'icon' => 'feather-users',
            'tone' => 'slate',
            'hint' => 'Research delivery network',
        ],
    ];
@endphp

<div class="nxl-container">
    <section class="partner-dashboard-hero">
        <div class="partner-hero-content">
            <div class="partner-hero-kicker">
                <i class="feather-shield"></i> Read-only portfolio intelligence
            </div>
            <h4 class="partner-hero-title">{{ __('partner.welcome') }}, {{ $funder->name }}.</h4>
            <p class="partner-hero-copy">{{ __('partner.dashboard_description') }} Track funded programs, delivery progress, budgets, reports and think tank work from one partner workspace.</p>
            <div class="partner-hero-actions">
                <a href="{{ route('partner.reports.index') }}" class="btn btn-light btn-sm">
                    <i class="feather-pie-chart me-1"></i> View Reports
                </a>
                <a href="{{ route('partner.programs.index') }}" class="btn btn-outline-light btn-sm">
                    <i class="feather-folder me-1"></i> Funded Programs
                </a>
            </div>
        </div>
        <div class="partner-hero-metrics">
            <div class="partner-hero-metric">
                <span>Available balance</span>
                <strong>{{ $funder->currency }} {{ number_format($fundsRemaining, 2) }}</strong>
            </div>
            <div class="partner-hero-metric">
                <span>Utilization</span>
                <strong>{{ $utilizationPercent }}%</strong>
            </div>
            <div class="partner-hero-metric">
                <span>Projects</span>
                <strong>{{ number_format($stats['total_projects'] ?? 0) }}</strong>
            </div>
            <div class="partner-hero-metric">
                <span>Programs</span>
                <strong>{{ number_format($stats['total_programs'] ?? 0) }}</strong>
            </div>
        </div>
    </section>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show mt-3">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-3 mt-3">
        @foreach($portfolioCards as $card)
            <div class="col-md-6 col-xl-3">
                <div class="partner-stat-card partner-card-hover is-{{ $card['tone'] }}">
                    <div class="d-flex justify-content-between gap-3">
                        <div class="min-w-0">
                            <div class="partner-stat-value">{{ $card['value'] }}</div>
                            <div class="partner-stat-label">{{ $card['label'] }}</div>
                            <span class="partner-stat-hint">
                                <i class="feather-trending-up"></i> {{ $card['hint'] }}
                            </span>
                        </div>
                        <div class="partner-stat-icon is-{{ $card['tone'] }}">
                            <i class="{{ $card['icon'] }}"></i>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="partner-analytics-grid mt-4">
        <div class="partner-panel partner-card-hover">
            <div class="partner-panel-header">
                <div>
                    <h5 class="mb-1 fw-bold">Funding Distribution</h5>
                    <div class="small text-muted">Approved funding by program for {{ $funder->name }}.</div>
                </div>
                <span class="partner-soft-badge">Top {{ $fundingChartRows->count() }}</span>
            </div>
            <div class="partner-panel-body">
                @forelse($fundingChartRows as $funding)
                    @php
                        $amount = (float) ($funding->approved_amount ?? 0);
                        $barWidth = max(4, min(100, round(($amount / $fundingMax) * 100)));
                    @endphp
                    <div class="partner-chart-row">
                        <div class="partner-chart-meta">
                            <span>{{ $funding->program_name ?? ($funding->program?->name ?? 'Program') }}</span>
                            <small>{{ $funding->currency ?? $funder->currency }} {{ number_format($amount, 2) }}</small>
                        </div>
                        <div class="partner-chart-track">
                            <div class="partner-chart-fill" style="--bar-width: {{ $barWidth }}%;"></div>
                        </div>
                    </div>
                @empty
                    <div class="partner-empty-state">No funded programs are linked yet.</div>
                @endforelse
            </div>
        </div>

        <div class="partner-panel partner-card-hover">
            <div class="partner-panel-header">
                <div>
                    <h5 class="mb-1 fw-bold">Portfolio Health</h5>
                    <div class="small text-muted">Budget use and delivery shape at a glance.</div>
                </div>
            </div>
            <div class="partner-panel-body">
                <div class="partner-ring-wrap">
                    <div class="partner-ring" style="--ring-value: {{ $utilizationPercent }}%;">
                        <div class="partner-ring-inner">{{ $utilizationPercent }}%</div>
                    </div>
                    <div>
                        <div class="fw-bold text-dark mb-1">Funding utilized</div>
                        <div class="small text-muted">Remaining balance is {{ $balancePercent }}% of approved partner funding.</div>
                    </div>
                </div>
                <div class="partner-insight-list">
                    <div class="partner-insight-item">
                        <span class="text-muted">Funds used</span>
                        <strong>{{ $funder->currency }} {{ number_format($fundsUtilized, 2) }}</strong>
                    </div>
                    <div class="partner-insight-item">
                        <span class="text-muted">Activities visible</span>
                        <strong>{{ number_format($fundedProjects->sum('activities_count')) }}</strong>
                    </div>
                    <div class="partner-insight-item">
                        <span class="text-muted">Report areas</span>
                        <strong>Finance, delivery, think tank</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="partner-panel partner-table-card mt-4">
        <div class="partner-panel-header">
            <div>
                <h5 class="mb-1 fw-bold">Funded Project Dashboard</h5>
                <div class="small text-muted">Read-only access to projects financed by {{ $funder->name }}.</div>
            </div>
            <a href="{{ route('partner.programs.index') }}" class="btn btn-sm btn-outline-success">
                <i class="feather-folder me-1"></i> Funded Programs
            </a>
        </div>
        <div class="partner-panel-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Program</th>
                            <th>Governance Node</th>
                            <th class="text-end">Activities</th>
                            <th class="text-center">Access</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($fundedProjects->take(8) as $project)
                            @php
                                $activityWidth = max(6, min(100, round(((int) $project->activities_count / $projectActivityMax) * 100)));
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $project->name }}</td>
                                <td>{{ $project->program?->name ?? 'N/A' }}</td>
                                <td>
                                    <div>{{ $project->governanceNode?->name ?? 'N/A' }}</div>
                                    @if($project->governanceNode?->level)
                                        <small class="text-muted">{{ $project->governanceNode->level->name }}</small>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="fw-bold">{{ number_format($project->activities_count) }}</div>
                                    <div class="partner-chart-track mt-1 ms-auto" style="max-width: 120px;">
                                        <div class="partner-chart-fill" style="--bar-width: {{ $activityWidth }}%;"></div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('partner.projects.show', $project) }}" class="btn btn-sm btn-outline-success">
                                        <i class="feather-eye me-1"></i> View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="partner-empty-state">No funded projects are linked yet.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-4">
        <div class="col-md-6 col-xl-3">
            <a href="{{ route('partner.reports.index') }}" class="partner-action-card partner-card-hover">
                <div class="partner-action-icon tone-primary"><i class="feather-pie-chart"></i></div>
                <h6 class="fw-bold mb-1">Partner Reports</h6>
                <p class="small text-muted mb-0">Funding, delivery, and think tank performance.</p>
            </a>
        </div>
        <div class="col-md-6 col-xl-3">
            <a href="{{ route('partner.reports.financial-position') }}" class="partner-action-card partner-card-hover">
                <div class="partner-action-icon tone-success"><i class="feather-dollar-sign"></i></div>
                <h6 class="fw-bold mb-1">Financial Position</h6>
                <p class="small text-muted mb-0">Program hierarchy budget and balance report.</p>
            </a>
        </div>
        <div class="col-md-6 col-xl-3">
            <a href="{{ route('partner.workplan.index') }}" class="partner-action-card partner-card-hover">
                <div class="partner-action-icon tone-warning"><i class="feather-check-square"></i></div>
                <h6 class="fw-bold mb-1">Work Plan</h6>
                <p class="small text-muted mb-0">Read-only work plan and no-objection status.</p>
            </a>
        </div>
        <div class="col-md-6 col-xl-3">
            <a href="{{ route('partner.think-tanks.deep-search') }}" class="partner-action-card partner-card-hover">
                <div class="partner-action-icon tone-info"><i class="feather-search"></i></div>
                <h6 class="fw-bold mb-1">Think Tank Search</h6>
                <p class="small text-muted mb-0">Delivery records for funded think tanks.</p>
            </a>
        </div>
    </div>

    <div class="mt-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <div>
                <h5 class="fw-bold mb-1">Funding & Think Tank Report</h5>
                <div class="small text-muted">Consolidated reporting from the admin portal.</div>
            </div>
            <a href="{{ route('partner.reports.index') }}" class="btn btn-sm btn-outline-success">
                <i class="feather-pie-chart me-1"></i> Full Report
            </a>
        </div>
        @include('partner.partials.funding-report', ['reportingOverview' => $reportingOverview, 'funder' => $funder])
    </div>

    <div class="partner-panel partner-table-card mt-4">
        <div class="partner-panel-header">
            <div>
                <h5 class="mb-1 fw-bold">{{ __('partner.recent_programs') }}</h5>
                <div class="small text-muted">Latest approved funding records connected to your partner account.</div>
            </div>
            @if($fundings->count() > 5)
                <a href="{{ route('partner.programs.index') }}" class="btn btn-sm btn-success">
                    {{ __('partner.view_all') }}
                </a>
            @endif
        </div>
        <div class="partner-panel-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('partner.program_name') }}</th>
                            <th>{{ __('partner.governance_node') }}</th>
                            <th class="text-end">{{ __('partner.approved_amount') }}</th>
                            <th>{{ __('partner.period') }}</th>
                            <th class="text-center">{{ __('partner.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($fundings->take(5) as $funding)
                            <tr>
                                <td><strong>{{ $funding->program_name ?? ($funding->program?->name ?? 'N/A') }}</strong></td>
                                <td>
                                    <div>{{ $funding->governanceNode->name ?? '-' }}</div>
                                    @if($funding->governanceNode)
                                        <small class="text-muted">{{ $funding->governanceNode->level->name ?? '' }}</small>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <strong>{{ $funding->currency ?? $funder->currency }} {{ number_format($funding->approved_amount, 2) }}</strong>
                                </td>
                                <td>{{ $funding->start_year }} - {{ $funding->end_year }}</td>
                                <td class="text-center">
                                    <a href="{{ route('partner.programs.show', $funding->id) }}"
                                       class="btn btn-sm btn-outline-success">
                                        <i class="feather-eye"></i> {{ __('partner.view') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="partner-empty-state">{{ __('partner.no_programs') }}</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
