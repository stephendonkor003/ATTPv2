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
            pointer-events: none;
            z-index: 1;
        }

        .module-card:hover::before {
            opacity: 1;
        }

        .module-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 36px rgba(15, 23, 42, 0.12);
            position: relative;
            z-index: 1;
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
            position: relative;
            z-index: 2;
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
                {{-- Governance --}}
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card module-card h-100"
                        style="background: linear-gradient(150deg, #0ea5e91a 0%, #ffffff 68%); border-color: #0ea5e92e;">
                        <div class="card-body d-flex flex-column gap-3">
                            <div class="d-flex align-items-start gap-3">
                                <span class="module-icon"
                                    style="background: linear-gradient(145deg, #0ea5e92e 0%, #0ea5e94d 100%); color: #0f172a;">
                                    <i class="feather-shield"></i>
                                </span>
                                <div>
                                    <h6 class="mb-1">Governance</h6>
                                    <p class="subtext mb-0">Configure governance structure and funding partners.</p>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="quick-link" href="{{ route('finance.governance.index') }}">
                                    <i class="feather-arrow-up-right text-primary"></i>Setup
                                </a>
                                <a class="quick-link" href="{{ route('finance.funders.index') }}">
                                    <i class="feather-arrow-up-right text-primary"></i>Funding Partners
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Budget Structure --}}
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card module-card h-100"
                        style="background: linear-gradient(150deg, #22c55e1a 0%, #ffffff 68%); border-color: #22c55e2e;">
                        <div class="card-body d-flex flex-column gap-3">
                            <div class="d-flex align-items-start gap-3">
                                <span class="module-icon"
                                    style="background: linear-gradient(145deg, #22c55e2e 0%, #22c55e4d 100%); color: #0f172a;">
                                    <i class="feather-grid"></i>
                                </span>
                                <div>
                                    <h6 class="mb-1">Budget Structure</h6>
                                    <p class="subtext mb-0">Manage sectors, programs, and projects.</p>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="quick-link" href="{{ route('budget.programs.index') }}">
                                    <i class="feather-arrow-up-right text-primary"></i>Programs
                                </a>
                                <a class="quick-link" href="{{ route('budget.projects.index') }}">
                                    <i class="feather-arrow-up-right text-primary"></i>Projects
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Budget Execution --}}
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card module-card h-100"
                        style="background: linear-gradient(150deg, #f59e0b1a 0%, #ffffff 68%); border-color: #f59e0b2e;">
                        <div class="card-body d-flex flex-column gap-3">
                            <div class="d-flex align-items-start gap-3">
                                <span class="module-icon"
                                    style="background: linear-gradient(145deg, #f59e0b2e 0%, #f59e0b4d 100%); color: #0f172a;">
                                    <i class="feather-activity"></i>
                                </span>
                                <div>
                                    <h6 class="mb-1">Budget Execution</h6>
                                    <p class="subtext mb-0">Commitments, purchase requests, and resources.</p>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="quick-link" href="{{ route('finance.commitments.index') }}">
                                    <i class="feather-arrow-up-right text-primary"></i>Commitments
                                </a>
                                <a class="quick-link" href="{{ route('finance.purchase-requests.index') }}">
                                    <i class="feather-arrow-up-right text-primary"></i>Purchase Requests
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Reports & Oversight --}}
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card module-card h-100"
                        style="background: linear-gradient(150deg, #6366f11a 0%, #ffffff 68%); border-color: #6366f12e;">
                        <div class="card-body d-flex flex-column gap-3">
                            <div class="d-flex align-items-start gap-3">
                                <span class="module-icon"
                                    style="background: linear-gradient(145deg, #6366f12e 0%, #6366f14d 100%); color: #0f172a;">
                                    <i class="feather-bar-chart-2"></i>
                                </span>
                                <div>
                                    <h6 class="mb-1">Reports & Oversight</h6>
                                    <p class="subtext mb-0">Dashboards, summaries, and executive reporting.</p>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="quick-link" href="{{ route('budget.reports.index') }}">
                                    <i class="feather-arrow-up-right text-primary"></i>Reports
                                </a>
                                <a class="quick-link" href="{{ route('budget.summary.dashboard') }}">
                                    <i class="feather-arrow-up-right text-primary"></i>Summary
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Monitoring & Evaluation --}}
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card module-card h-100"
                        style="background: linear-gradient(150deg, #0ea5e91a 0%, #ffffff 68%); border-color: #0ea5e92e;">
                        <div class="card-body d-flex flex-column gap-3">
                            <div class="d-flex align-items-start gap-3">
                                <span class="module-icon"
                                    style="background: linear-gradient(145deg, #0ea5e92e 0%, #0ea5e94d 100%); color: #0f172a;">
                                    <i class="feather-target"></i>
                                </span>
                                <div>
                                    <h6 class="mb-1">Monitoring & Evaluation</h6>
                                    <p class="subtext mb-0">Indicators, frequencies, and survey links.</p>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="quick-link" href="{{ route('budget.me.indicators.index') }}">
                                    <i class="feather-arrow-up-right text-primary"></i>Indicators
                                </a>
                                <a class="quick-link" href="{{ route('budget.me-configuration.frequencies.index') }}">
                                    <i class="feather-arrow-up-right text-primary"></i>Frequencies
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Human Resource --}}
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card module-card h-100"
                        style="background: linear-gradient(150deg, #10b9811a 0%, #ffffff 68%); border-color: #10b9812e;">
                        <div class="card-body d-flex flex-column gap-3">
                            <div class="d-flex align-items-start gap-3">
                                <span class="module-icon"
                                    style="background: linear-gradient(145deg, #10b9812e 0%, #10b9814d 100%); color: #0f172a;">
                                    <i class="feather-users"></i>
                                </span>
                                <div>
                                    <h6 class="mb-1">Human Resource</h6>
                                    <p class="subtext mb-0">Positions, recruitment, and HR analytics.</p>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="quick-link" href="{{ route('hr.positions.index') }}">
                                    <i class="feather-arrow-up-right text-primary"></i>Positions
                                </a>
                                <a class="quick-link" href="{{ route('hr.vacancies.index') }}">
                                    <i class="feather-arrow-up-right text-primary"></i>Recruitment
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Vendors --}}
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card module-card h-100"
                        style="background: linear-gradient(150deg, #0f172a1a 0%, #ffffff 68%); border-color: #0f172a2e;">
                        <div class="card-body d-flex flex-column gap-3">
                            <div class="d-flex align-items-start gap-3">
                                <span class="module-icon"
                                    style="background: linear-gradient(145deg, #0f172a2e 0%, #0f172a4d 100%); color: #0f172a;">
                                    <i class="feather-briefcase"></i>
                                </span>
                                <div>
                                    <h6 class="mb-1">Vendors Management</h6>
                                    <p class="subtext mb-0">Vendor directory, categories, and negotiations.</p>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="quick-link" href="{{ route('vendors.index') }}">
                                    <i class="feather-arrow-up-right text-primary"></i>Vendors
                                </a>
                                <a class="quick-link" href="{{ route('vendors.categories.index') }}">
                                    <i class="feather-arrow-up-right text-primary"></i>Categories
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Prescreening --}}
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card module-card h-100"
                        style="background: linear-gradient(150deg, #ec48991a 0%, #ffffff 68%); border-color: #ec48992e;">
                        <div class="card-body d-flex flex-column gap-3">
                            <div class="d-flex align-items-start gap-3">
                                <span class="module-icon"
                                    style="background: linear-gradient(145deg, #ec48992e 0%, #ec48994d 100%); color: #0f172a;">
                                    <i class="feather-check-square"></i>
                                </span>
                                <div>
                                    <h6 class="mb-1">Prescreening Engine</h6>
                                    <p class="subtext mb-0">Templates, assignments, and submissions oversight.</p>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="quick-link" href="{{ route('prescreening.templates.index') }}">
                                    <i class="feather-arrow-up-right text-primary"></i>Templates
                                </a>
                                <a class="quick-link" href="{{ route('prescreening.submissions.index') }}">
                                    <i class="feather-arrow-up-right text-primary"></i>Submissions
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Data Sources --}}
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card module-card h-100"
                        style="background: linear-gradient(150deg, #0ea5e91a 0%, #ffffff 68%); border-color: #0ea5e92e;">
                        <div class="card-body d-flex flex-column gap-3">
                            <div class="d-flex align-items-start gap-3">
                                <span class="module-icon"
                                    style="background: linear-gradient(145deg, #0ea5e92e 0%, #0ea5e94d 100%); color: #0f172a;">
                                    <i class="feather-database"></i>
                                </span>
                                <div>
                                    <h6 class="mb-1">Data Source & Cleaning</h6>
                                    <p class="subtext mb-0">Bridge templates, sync status, and raw data review.</p>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="quick-link" href="{{ route('budget.me.data-sources.index') }}">
                                    <i class="feather-arrow-up-right text-primary"></i>Data Sources
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Site Visits --}}
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card module-card h-100"
                        style="background: linear-gradient(150deg, #22d3ee1a 0%, #ffffff 68%); border-color: #22d3ee2e;">
                        <div class="card-body d-flex flex-column gap-3">
                            <div class="d-flex align-items-start gap-3">
                                <span class="module-icon"
                                    style="background: linear-gradient(145deg, #22d3ee2e 0%, #22d3ee4d 100%); color: #0f172a;">
                                    <i class="feather-map-pin"></i>
                                </span>
                                <div>
                                    <h6 class="mb-1">Site Visits</h6>
                                    <p class="subtext mb-0">Plan, approve, and report on site engagements.</p>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="quick-link" href="{{ route('site-visits.index') }}">
                                    <i class="feather-arrow-up-right text-primary"></i>All Visits
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Audit --}}
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card module-card h-100"
                        style="background: linear-gradient(150deg, #f973161a 0%, #ffffff 68%); border-color: #f973162e;">
                        <div class="card-body d-flex flex-column gap-3">
                            <div class="d-flex align-items-start gap-3">
                                <span class="module-icon"
                                    style="background: linear-gradient(145deg, #f973162e 0%, #f973164d 100%); color: #0f172a;">
                                    <i class="feather-activity"></i>
                                </span>
                                <div>
                                    <h6 class="mb-1">Audit</h6>
                                    <p class="subtext mb-0">System audit trails and security visibility.</p>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="quick-link" href="{{ route('system.audit.index') }}">
                                    <i class="feather-arrow-up-right text-primary"></i>Audit Log
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- User Management --}}
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card module-card h-100"
                        style="background: linear-gradient(150deg, #10b9811a 0%, #ffffff 68%); border-color: #10b9812e;">
                        <div class="card-body d-flex flex-column gap-3">
                            <div class="d-flex align-items-start gap-3">
                                <span class="module-icon"
                                    style="background: linear-gradient(145deg, #10b9812e 0%, #10b9814d 100%); color: #0f172a;">
                                    <i class="feather-user-check"></i>
                                </span>
                                <div>
                                    <h6 class="mb-1">User Management</h6>
                                    <p class="subtext mb-0">Manage users, roles, and permissions.</p>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="quick-link" href="{{ route('system.users.index') }}">
                                    <i class="feather-arrow-up-right text-primary"></i>Users
                                </a>
                                <a class="quick-link" href="{{ route('system.roles.index') }}">
                                    <i class="feather-arrow-up-right text-primary"></i>Roles
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
@endsection
