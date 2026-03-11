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

        .dash-hero h4,
        .dash-hero p {
            color: #f8fafc;
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

        .access-state {
            border: 1px solid #dbeafe;
            border-radius: 18px;
            background:
                radial-gradient(circle at 8% 12%, rgba(14, 165, 233, 0.18), transparent 30%),
                radial-gradient(circle at 90% 20%, rgba(16, 185, 129, 0.17), transparent 32%),
                #f8fbff;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08);
        }

        .digital-clock {
            font-family: "JetBrains Mono", "Courier New", monospace;
            font-size: 2.1rem;
            letter-spacing: 0.08em;
            font-weight: 700;
            color: #0f172a;
        }

        .pulse-dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: #22c55e;
            box-shadow: 0 0 0 rgba(34, 197, 94, 0.5);
            animation: pulse 1.4s ease-out infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.5);
            }
            100% {
                box-shadow: 0 0 0 14px rgba(34, 197, 94, 0);
            }
        }
    </style>
@endpush

@section('content')
    @php
        $appName = config('app.name', env('APP_NAME', 'System'));
        $hasDashboardAccess = $hasDashboardAccess ?? (auth()->user()?->can('dashboard.access') ?? false);

        $quickLinks = [
            ['label' => 'New Project', 'route' => 'budget.projects.create', 'icon' => 'plus-circle', 'accent' => 'text-primary', 'permissions' => ['project.create']],
            ['label' => 'Indicators Hub', 'route' => 'budget.me.indicators.index', 'icon' => 'target', 'accent' => 'text-danger', 'permissions' => ['me.configuration.view']],
            ['label' => 'Indicator Levels', 'route' => 'budget.me-configuration.indicator-levels.index', 'icon' => 'layers', 'accent' => 'text-success', 'permissions' => ['me.configuration.view']],
            ['label' => 'Frequencies', 'route' => 'budget.me-configuration.frequencies.index', 'icon' => 'clock', 'accent' => 'text-primary', 'permissions' => ['me.configuration.view']],
            ['label' => 'Indicator Units', 'route' => 'budget.me-configuration.units.index', 'icon' => 'sliders', 'accent' => 'text-warning', 'permissions' => ['me.configuration.view']],
            ['label' => 'Data Sources', 'route' => 'budget.me.data-sources.index', 'icon' => 'database', 'accent' => 'text-success', 'permissions' => ['me.configuration.view']],
            ['label' => 'M&E Report (Excel)', 'route' => 'budget.me.indicators.report.excel', 'icon' => 'download', 'accent' => 'text-info', 'permissions' => ['me.configuration.view']],
            ['label' => 'M&E Report (PDF)', 'route' => 'budget.me.indicators.report.pdf', 'icon' => 'file-text', 'accent' => 'text-danger', 'permissions' => ['me.configuration.view']],
            ['label' => 'Vendors', 'route' => 'vendors.index', 'icon' => 'briefcase', 'accent' => 'text-warning', 'permissions' => ['vendor.manage']],
            ['label' => 'Users', 'route' => 'system.users.index', 'icon' => 'users', 'accent' => 'text-info', 'permissions' => ['users.manage']],
        ];

        $modules = [
            [
                'title' => 'Governance',
                'desc' => 'Configure governance structure and funding partners.',
                'icon' => 'shield',
                'card_style' => 'background: linear-gradient(150deg, #0ea5e91a 0%, #ffffff 68%); border-color: #0ea5e92e;',
                'icon_style' => 'background: linear-gradient(145deg, #0ea5e92e 0%, #0ea5e94d 100%); color: #0f172a;',
                'links' => [
                    ['label' => 'Setup', 'route' => 'finance.governance.index', 'permissions' => ['finance.access', 'finance.governance_structure.view']],
                    ['label' => 'Partners', 'route' => 'finance.funders.index', 'permissions' => ['finance.access', 'finance.funders.view']],
                ],
            ],
            [
                'title' => 'Budget Structure',
                'desc' => 'Manage sectors, programs, and projects.',
                'icon' => 'grid',
                'card_style' => 'background: linear-gradient(150deg, #22c55e1a 0%, #ffffff 68%); border-color: #22c55e2e;',
                'icon_style' => 'background: linear-gradient(145deg, #22c55e2e 0%, #22c55e4d 100%); color: #0f172a;',
                'links' => [
                    ['label' => 'Programs', 'route' => 'budget.programs.index', 'permissions' => ['program.view']],
                    ['label' => 'Projects', 'route' => 'budget.projects.index', 'permissions' => ['project.view']],
                ],
            ],
            [
                'title' => 'Budget Execution',
                'desc' => 'Commitments, purchase requests, and resources.',
                'icon' => 'activity',
                'card_style' => 'background: linear-gradient(150deg, #f59e0b1a 0%, #ffffff 68%); border-color: #f59e0b2e;',
                'icon_style' => 'background: linear-gradient(145deg, #f59e0b2e 0%, #f59e0b4d 100%); color: #0f172a;',
                'links' => [
                    ['label' => 'Commitments', 'route' => 'finance.commitments.index', 'permissions' => ['finance.access', 'finance.commitments.view']],
                    ['label' => 'Purchase Requests', 'route' => 'finance.purchase-requests.index', 'permissions' => ['finance.access', 'finance.purchase_requests.view']],
                ],
            ],
            [
                'title' => 'Reports & Oversight',
                'desc' => 'Dashboards, summaries, and executive reporting.',
                'icon' => 'bar-chart-2',
                'card_style' => 'background: linear-gradient(150deg, #6366f11a 0%, #ffffff 68%); border-color: #6366f12e;',
                'icon_style' => 'background: linear-gradient(145deg, #6366f12e 0%, #6366f14d 100%); color: #0f172a;',
                'links' => [
                    ['label' => 'Reports', 'route' => 'budget.reports.index', 'permissions' => ['budget.reports.view']],
                    ['label' => 'Summary', 'route' => 'budget.summary.dashboard', 'permissions' => ['budget.summary.view']],
                ],
            ],
            [
                'title' => 'Monitoring & Evaluation',
                'desc' => 'Indicators, frequencies, and survey links.',
                'icon' => 'target',
                'card_style' => 'background: linear-gradient(150deg, #0ea5e91a 0%, #ffffff 68%); border-color: #0ea5e92e;',
                'icon_style' => 'background: linear-gradient(145deg, #0ea5e92e 0%, #0ea5e94d 100%); color: #0f172a;',
                'links' => [
                    ['label' => 'Indicators', 'route' => 'budget.me.indicators.index', 'permissions' => ['me.configuration.view']],
                    ['label' => 'Frequencies', 'route' => 'budget.me-configuration.frequencies.index', 'permissions' => ['me.configuration.view']],
                ],
            ],
            [
                'title' => 'Human Resource',
                'desc' => 'Positions, recruitment, and HR analytics.',
                'icon' => 'users',
                'card_style' => 'background: linear-gradient(150deg, #10b9811a 0%, #ffffff 68%); border-color: #10b9812e;',
                'icon_style' => 'background: linear-gradient(145deg, #10b9812e 0%, #10b9814d 100%); color: #0f172a;',
                'links' => [
                    ['label' => 'Positions', 'route' => 'hr.positions.index', 'permissions' => ['hrm.positions.view']],
                    ['label' => 'Recruitment', 'route' => 'hr.vacancies.index', 'permissions' => ['hrm.vacancies.view']],
                ],
            ],
            [
                'title' => 'Vendors Management',
                'desc' => 'Vendor directory, categories, and negotiations.',
                'icon' => 'briefcase',
                'card_style' => 'background: linear-gradient(150deg, #0f172a1a 0%, #ffffff 68%); border-color: #0f172a2e;',
                'icon_style' => 'background: linear-gradient(145deg, #0f172a2e 0%, #0f172a4d 100%); color: #0f172a;',
                'links' => [
                    ['label' => 'Vendors', 'route' => 'vendors.index', 'permissions' => ['vendor.manage']],
                    ['label' => 'Categories', 'route' => 'vendors.categories.index', 'permissions' => ['vendor.manage']],
                ],
            ],
            [
                'title' => 'Prescreening Engine',
                'desc' => 'Templates, assignments, and submissions oversight.',
                'icon' => 'check-square',
                'card_style' => 'background: linear-gradient(150deg, #ec48991a 0%, #ffffff 68%); border-color: #ec48992e;',
                'icon_style' => 'background: linear-gradient(145deg, #ec48992e 0%, #ec48994d 100%); color: #0f172a;',
                'links' => [
                    ['label' => 'Templates', 'route' => 'prescreening.templates.index', 'permissions' => ['prescreening.manage']],
                    ['label' => 'Submissions', 'route' => 'prescreening.submissions.index', 'permissions' => ['prescreening.evaluate']],
                ],
            ],
            [
                'title' => 'Data Source & Cleaning',
                'desc' => 'Bridge templates, sync status, and raw data review.',
                'icon' => 'database',
                'card_style' => 'background: linear-gradient(150deg, #0ea5e91a 0%, #ffffff 68%); border-color: #0ea5e92e;',
                'icon_style' => 'background: linear-gradient(145deg, #0ea5e92e 0%, #0ea5e94d 100%); color: #0f172a;',
                'links' => [
                    ['label' => 'Data Sources', 'route' => 'budget.me.data-sources.index', 'permissions' => ['me.configuration.view']],
                ],
            ],
            [
                'title' => 'Site Visits',
                'desc' => 'Plan, approve, and report on site engagements.',
                'icon' => 'map-pin',
                'card_style' => 'background: linear-gradient(150deg, #22d3ee1a 0%, #ffffff 68%); border-color: #22d3ee2e;',
                'icon_style' => 'background: linear-gradient(145deg, #22d3ee2e 0%, #22d3ee4d 100%); color: #0f172a;',
                'links' => [
                    ['label' => 'All Visits', 'route' => 'site-visits.index', 'permissions' => ['site_visits.view']],
                ],
            ],
            [
                'title' => 'Audit',
                'desc' => 'System audit trails and security visibility.',
                'icon' => 'activity',
                'card_style' => 'background: linear-gradient(150deg, #f973161a 0%, #ffffff 68%); border-color: #f973162e;',
                'icon_style' => 'background: linear-gradient(145deg, #f973162e 0%, #f973164d 100%); color: #0f172a;',
                'links' => [
                    ['label' => 'Audit Log', 'route' => 'system.audit.index', 'permissions' => ['system.audit.view']],
                ],
            ],
            [
                'title' => 'User Management',
                'desc' => 'Manage users, roles, and permissions.',
                'icon' => 'user-check',
                'card_style' => 'background: linear-gradient(150deg, #10b9811a 0%, #ffffff 68%); border-color: #10b9812e;',
                'icon_style' => 'background: linear-gradient(145deg, #10b9812e 0%, #10b9814d 100%); color: #0f172a;',
                'links' => [
                    ['label' => 'Users', 'route' => 'system.users.index', 'permissions' => ['users.manage']],
                    ['label' => 'Roles', 'route' => 'system.roles.index', 'permissions' => ['roles.manage']],
                ],
            ],
        ];

        $canUse = static function (array $permissions): bool {
            if (empty($permissions)) {
                return true;
            }

            $user = auth()->user();
            if (!$user) {
                return false;
            }

            foreach ($permissions as $permission) {
                if (!$user->can($permission)) {
                    return false;
                }
            }

            return true;
        };

        $visibleQuickLinks = collect($quickLinks)
            ->filter(
                fn ($link) => $hasDashboardAccess
                    && \Illuminate\Support\Facades\Route::has($link['route'])
                    && $canUse($link['permissions'])
            )
            ->values();

        $visibleModules = collect($modules)
            ->map(function ($module) use ($canUse, $hasDashboardAccess) {
                $module['links'] = collect($module['links'])
                    ->filter(
                        fn ($link) => $hasDashboardAccess
                            && \Illuminate\Support\Facades\Route::has($link['route'])
                            && $canUse($link['permissions'])
                    )
                    ->values()
                    ->all();

                return $module;
            })
            ->filter(fn ($module) => !empty($module['links']))
            ->values();

        $showNoAccessState = !$hasDashboardAccess || ($visibleQuickLinks->isEmpty() && $visibleModules->isEmpty());
    @endphp

    <main class="nxl-container">
        <div class="nxl-content">
            <div class="card dash-hero mb-4">
                <div class="card-body p-4 d-flex flex-column flex-lg-row align-items-start align-items-lg-center gap-3">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="chip"><i class="feather-cpu"></i> {{ $appName }}</span>
                            <span class="chip"><i class="feather-activity"></i> Digital Intelligence</span>
                        </div>
                        <h4 class="mb-2">{{ $appName }} Command Center</h4>
                        <p class="mb-0" style="color: rgba(248, 250, 252, 0.88);">
                            Unified operational surface for governance, finance, procurement, M&E, and oversight.
                        </p>
                    </div>
                    <div class="text-end">
                        <div class="pill mb-2">
                            <i class="feather-user"></i>
                            <span>Welcome, {{ Auth::user()->name }}</span>
                        </div>
                        <div class="subtext text-white-50">
                            Role: {{ ucfirst(str_replace('_', ' ', Auth::user()->user_type ?? 'user')) }}
                        </div>
                    </div>
                </div>
            </div>

            @if ($showNoAccessState)
                <div class="card access-state">
                    <div class="card-body p-4 p-lg-5">
                        <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-4">
                            <div>
                                <h5 class="mb-2">{{ $appName }} Digital Intelligence Layer</h5>
                                <p class="text-muted mb-3">
                                    Your account is authenticated, but it does not currently have permission to open dashboard modules.
                                    Request dashboard and module access from your system administrator to continue.
                                </p>
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <span class="pulse-dot"></span>
                                    <span class="fw-semibold text-success">Live system pulse</span>
                                </div>
                                <div class="small text-muted">
                                    @if (!$hasDashboardAccess)
                                        Missing permission: <code>dashboard.access</code>.
                                    @else
                                        Dashboard access exists, but no module-level permissions are assigned yet.
                                    @endif
                                </div>
                            </div>
                            <div class="text-lg-end">
                                <div class="digital-clock" id="noAccessClock">--:--:--</div>
                                <div class="text-muted small" id="noAccessDate">Loading date...</div>
                                <div class="badge bg-info-subtle text-info mt-2" id="noAccessUptime">Session pulse 00:00:00</div>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                @if ($visibleQuickLinks->isNotEmpty())
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-primary-subtle text-primary fw-semibold">Quick Links</span>
                                <span class="text-muted small">Permission-aware shortcuts</span>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($visibleQuickLinks as $link)
                                    <a class="quick-link" href="{{ route($link['route']) }}">
                                        <i class="feather-{{ $link['icon'] }} {{ $link['accent'] }}"></i>{{ $link['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                <div class="row g-3">
                    @foreach ($visibleModules as $module)
                        <div class="col-12 col-md-6 col-xl-4">
                            <div class="card module-card h-100" style="{{ $module['card_style'] }}">
                                <div class="card-body d-flex flex-column gap-3">
                                    <div class="d-flex align-items-start gap-3">
                                        <span class="module-icon" style="{{ $module['icon_style'] }}">
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
            @endif
        </div>
    </main>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const clockEl = document.getElementById('noAccessClock');
            const dateEl = document.getElementById('noAccessDate');
            const uptimeEl = document.getElementById('noAccessUptime');

            if (!clockEl || !dateEl || !uptimeEl) {
                return;
            }

            const start = Date.now();

            const formatClock = new Intl.DateTimeFormat([], {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            });

            const formatDate = new Intl.DateTimeFormat([], {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            });

            const pad = (value) => String(value).padStart(2, '0');

            const tick = () => {
                const now = new Date();
                clockEl.textContent = formatClock.format(now);
                dateEl.textContent = formatDate.format(now);

                const elapsed = Math.floor((Date.now() - start) / 1000);
                const hours = Math.floor(elapsed / 3600);
                const minutes = Math.floor((elapsed % 3600) / 60);
                const seconds = elapsed % 60;
                uptimeEl.textContent = `Session pulse ${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
            };

            tick();
            setInterval(tick, 1000);
        });
    </script>
@endpush
