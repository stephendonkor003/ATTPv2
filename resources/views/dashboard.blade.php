@extends('layouts.app')
@section('title', 'Dashboard')

@push('styles')
    <style>
        .dash-hero {
            background-image:
                linear-gradient(135deg, rgba(15, 23, 42, 0.92) 0%, rgba(14, 165, 233, 0.78) 60%, rgba(16, 185, 129, 0.62) 100%),
                url('https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?auto=format&fit=crop&w=1600&q=80');
            background-size: cover;
            background-position: center;
            color: #f8fafc;
            border-radius: 18px;
            box-shadow: 0 16px 36px rgba(15, 23, 42, 0.25);
            overflow: hidden;
        }

        .dash-hero h4 {
            color: #f8fafc;
        }

        .dash-hero p {
            color: rgba(248, 250, 252, 0.9);
        }

        .dash-hero .chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 6px 12px;
            background: rgba(255, 255, 255, 0.18);
            color: #e2f3ff;
            font-weight: 600;
            font-size: 0.82rem;
        }

        .module-card {
            position: relative;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .module-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 20% 20%, rgba(255, 255, 255, 0.35), transparent 40%),
                radial-gradient(circle at 85% 15%, rgba(255, 255, 255, 0.22), transparent 35%);
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .module-card:hover::before {
            opacity: 1;
        }

        .module-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 36px rgba(15, 23, 42, 0.12);
        }

        .module-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #0f172a;
            font-size: 1.2rem;
            background: #e2e8f0;
        }

        .quick-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            font-weight: 600;
            color: #0f172a;
            text-decoration: none;
            transition: background 0.15s ease, border-color 0.15s ease;
        }

        .quick-link:hover {
            background: #e0f2fe;
            border-color: #bae6fd;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            background: #0ea5e9;
            color: #f8fafc;
            font-weight: 600;
            font-size: 0.86rem;
        }

        .subtext {
            color: #6b7280;
            font-size: 0.9rem;
        }
    </style>
@endpush

@section('content')
    @php
        $modules = [
            [
                'title' => 'Governance',
                'desc' => 'Configure governance structure and funding partners.',
                'icon' => 'shield',
                'color' => '#0ea5e9',
                'links' => [
                    ['label' => 'Setup', 'route' => 'finance.governance.index'],
                    ['label' => 'Funding Partners', 'route' => 'finance.funders.index'],
                ],
            ],
            [
                'title' => 'Budget Structure',
                'desc' => 'Manage sectors, programs, projects, activities, and sub-activities.',
                'icon' => 'grid',
                'color' => '#22c55e',
                'links' => [
                    ['label' => 'Programs', 'route' => 'budget.programs.index'],
                    ['label' => 'Projects', 'route' => 'budget.projects.index'],
                ],
            ],
            [
                'title' => 'Budget Execution',
                'desc' => 'Commitments, purchase requests, and resource tracking.',
                'icon' => 'activity',
                'color' => '#f59e0b',
                'links' => [
                    ['label' => 'Commitments', 'route' => 'finance.commitments.index'],
                    ['label' => 'Purchase Requests', 'route' => 'finance.purchase-requests.index'],
                ],
            ],
            [
                'title' => 'Reports & Oversight',
                'desc' => 'Dashboards, summaries, and executive reporting.',
                'icon' => 'bar-chart-2',
                'color' => '#6366f1',
                'links' => [
                    ['label' => 'Reports', 'route' => 'budget.reports.index'],
                    ['label' => 'Summary', 'route' => 'budget.summary.dashboard'],
                ],
            ],
            [
                'title' => 'Monitoring & Evaluation',
                'desc' => 'Central hub for indicators, frequencies, units, and definitions.',
                'icon' => 'target',
                'color' => '#0ea5e9',
                'links' => [
                    ['label' => 'Indicators', 'route' => 'budget.me.indicators.index'],
                    ['label' => 'Frequencies', 'route' => 'budget.me-configuration.frequencies.index'],
                ],
            ],
            [
                'title' => 'Human Resource',
                'desc' => 'Positions, recruitment, and HR analytics.',
                'icon' => 'users',
                'color' => '#10b981',
                'links' => [
                    ['label' => 'Positions', 'route' => 'hr.positions.index'],
                    ['label' => 'Recruitment', 'route' => 'hr.vacancies.index'],
                ],
            ],
            [
                'title' => 'Vendors Management',
                'desc' => 'Vendor directory, categories, and negotiations.',
                'icon' => 'briefcase',
                'color' => '#0f172a',
                'links' => [
                    ['label' => 'Vendors', 'route' => 'vendors.index'],
                    ['label' => 'Categories', 'route' => 'vendors.categories.index'],
                ],
            ],
            [
                'title' => 'Prescreening Engine',
                'desc' => 'Templates, assignments, and submissions oversight.',
                'icon' => 'check-square',
                'color' => '#ec4899',
                'links' => [
                    ['label' => 'Templates', 'route' => 'prescreening.templates.index'],
                    ['label' => 'Submissions', 'route' => 'prescreening.submissions.index'],
                ],
            ],
            [
                'title' => 'Data Source & Cleaning',
                'desc' => 'Bridge templates, sync status, and raw data review.',
                'icon' => 'database',
                'color' => '#0ea5e9',
                'links' => [
                    ['label' => 'Data Sources', 'route' => 'budget.me.data-sources.index'],
                ],
            ],
            [
                'title' => 'Site Visits',
                'desc' => 'Plan, approve, and report on site engagements.',
                'icon' => 'map-pin',
                'color' => '#22d3ee',
                'links' => [
                    ['label' => 'All Visits', 'route' => 'site-visits.index'],
                ],
            ],
            [
                'title' => 'Audit',
                'desc' => 'System audit trails and security visibility.',
                'icon' => 'activity',
                'color' => '#f97316',
                'links' => [
                    ['label' => 'Audit Log', 'route' => 'system.audit.index'],
                ],
            ],
            [
                'title' => 'User Management',
                'desc' => 'Manage users, roles, and permissions.',
                'icon' => 'user-check',
                'color' => '#10b981',
                'links' => [
                    ['label' => 'Users', 'route' => 'system.users.index'],
                    ['label' => 'Roles', 'route' => 'system.roles.index'],
                ],
            ],
        ];
    @endphp

    <main class="nxl-container">
        <div class="nxl-content">

            <div class="card dash-hero mb-4">
                <div class="card-body p-4 d-flex flex-column flex-lg-row align-items-start align-items-lg-center gap-3">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="chip"><i class="feather-cpu"></i> ATTP Portal</span>
                            <span class="chip"><i class="feather-activity"></i> Operational Hub</span>
                        </div>
                        <h4 class="mb-2">Enterprise Operations & M&E Command Center</h4>
                        <p class="mb-0" style="color: rgba(248, 250, 252, 0.88);">
                            Navigate core modules, launch quick actions, and keep oversight on procurement,
                            budgeting, M&E, data sources, and governance—now in one consolidated view.
                        </p>
                    </div>
                    <div class="text-end">
                        <div class="pill mb-2">
                            <i class="feather-user"></i>
                            <span>Welcome, {{ Auth::user()->name }}</span>
                        </div>
                        <div class="subtext text-white-50">Role: {{ ucfirst(str_replace('_', ' ', Auth::user()->user_type ?? 'user')) }}</div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-primary-subtle text-primary fw-semibold">Quick Links</span>
                        <span class="text-muted small">Jump to common actions</span>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="quick-link" href="{{ route('budget.projects.create') }}"><i class="feather-plus-circle text-primary"></i>New Project</a>
                        <a class="quick-link" href="{{ route('budget.me.indicators.index') }}"><i class="feather-target text-danger"></i>Indicators Hub</a>
                        <a class="quick-link" href="{{ route('budget.me-configuration.indicator-levels.index') }}"><i class="feather-layers text-success"></i>Indicator Levels</a>
                        <a class="quick-link" href="{{ route('budget.me-configuration.frequencies.index') }}"><i class="feather-clock text-primary"></i>Frequencies</a>
                        <a class="quick-link" href="{{ route('budget.me-configuration.units.index') }}"><i class="feather-sliders text-warning"></i>Indicator Units</a>
                        <a class="quick-link" href="{{ route('budget.me.data-sources.index') }}"><i class="feather-database text-success"></i>Data Sources</a>
                        <a class="quick-link" href="{{ route('budget.me.indicators.report.excel') }}"><i class="feather-download text-info"></i>M&E Report (Excel)</a>
                        <a class="quick-link" href="{{ route('budget.me.indicators.report.pdf') }}"><i class="feather-file-text text-danger"></i>M&E Report (PDF)</a>
                        <a class="quick-link" href="{{ route('vendors.index') }}"><i class="feather-briefcase text-warning"></i>Vendors</a>
                        <a class="quick-link" href="{{ route('system.users.index') }}"><i class="feather-users text-info"></i>Users</a>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                @foreach ($modules as $module)
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="card module-card h-100"
                            style="background: linear-gradient(150deg, {{ $module['color'] }}1a 0%, #ffffff 68%); border-color: {{ $module['color'] }}2e;">
                            <div class="card-body d-flex flex-column gap-3">
                                <div class="d-flex align-items-start gap-3">
                                    <span class="module-icon"
                                        style="background: linear-gradient(145deg, {{ $module['color'] }}2e 0%, {{ $module['color'] }}4d 100%); color: #0f172a;">
                                        <i class="feather-{{ $module['icon'] }}"></i>
                                    </span>
                                    <div>
                                        <h6 class="mb-1">{{ $module['title'] }}</h6>
                                        <p class="subtext mb-0">{{ $module['desc'] }}</p>
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($module['links'] as $link)
                                        <a class="quick-link" href="{{ route($link['route']) }}">
                                            <i class="feather-arrow-up-right text-primary"></i>{{ $link['label'] }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

        </div>
    </main>
@endsection
