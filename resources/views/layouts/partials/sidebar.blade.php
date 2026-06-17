@php
    $sidebarUser = auth()->user();
    $isMemberStateSidebarUser = $sidebarUser && $sidebarUser->user_type === 'member_state';
    $sidebarMemberState = $isMemberStateSidebarUser ? $sidebarUser->memberState : null;
    $sidebarMemberStateFlag = $sidebarMemberState?->flag_url ?: asset('assets/images/au.png');
    $financialGovernanceSidebarPermissions = [
        'finance.governance_structure.view',
        'finance.governance_structure.manage',
        'finance.funders.view',
        'finance.funders.manage',
        'finance.program_funding.view',
        'finance.program_funding.manage',
        'finance.departments.view',
        'finance.departments.manage',
    ];
    $canSeeFinancialGovernanceSidebar = $sidebarUser
        && collect($financialGovernanceSidebarPermissions)->contains(
            fn($permission) => $sidebarUser->can($permission)
        );
    $procurementSettingsSidebarPermissions = [
        'procurement.settings.manage',
        'procurement.settings.geographics',
        'procurement.settings.methods',
        'procurement.settings.stages',
        'procurement.settings.statuses',
        'procurement.settings.step_stages',
        'procurement.settings.step_approvals',
        'procurement.view_all',
    ];
    $canSeeProcurementSettingsSidebar = $sidebarUser
        && collect($procurementSettingsSidebarPermissions)->contains(
            fn($permission) => $sidebarUser->can($permission)
        );
    $procurementSidebarPermissions = [
        'procurement.create',
        'procurement.view',
        'procurement.manage',
        'procurement.plan.view',
        'procurement.plan.create',
        'procurement.view_all',
        'procurement.manage_all',
        'procurement.audit',
        'forms.manage',
        'vendor.manage',
        'prescreening.evaluate',
        'prescreening.manage',
        'prescreening.view_all',
    ];
    $canSeeProcurementSidebar = $canSeeProcurementSettingsSidebar
        || ($sidebarUser
            && collect($procurementSidebarPermissions)->contains(
                fn($permission) => $sidebarUser->can($permission)
            ));
    $newsCommunicationSidebarPermissions = [
        'communications.view',
        'communications.respond',
        'news.manage',
        'news.approve',
        'questions.view',
        'questions.respond',
    ];
    $canSeeNewsCommunicationSidebar = $sidebarUser
        && collect($newsCommunicationSidebarPermissions)->contains(
            fn($permission) => $sidebarUser->hasPermission($permission)
        );
    $isAdminSidebarUser = (bool) ($sidebarUser?->isSuperAdmin() || $sidebarUser?->isAdmin());
    $thinkTankFinancePermissions = [
        'think_tanks.funding.view',
        'consortiums.view',
        'finance.purchase_requests.view',
        'finance.commitments.create',
        'finance.purchase_orders.create',
    ];
    $canSeeThinkTankFinance = $sidebarUser
        && collect($thinkTankFinancePermissions)->contains(
            fn($permission) => $sidebarUser->can($permission)
        );
@endphp

<style>
    .ms-sidebar-hero {
        background: linear-gradient(130deg, #0f172a 0%, #0f766e 48%, #0ea5e9 100%);
        color: #f8fafc;
        border-radius: 14px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.24);
    }

    .ms-sidebar-hero::before {
        content: "";
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at 15% 15%, rgba(255, 255, 255, 0.22), transparent 38%);
        pointer-events: none;
    }

    .ms-sidebar-hero .hero-body {
        position: relative;
        z-index: 1;
    }

    .ms-flag-wave-wrap {
        width: 68px;
        height: 44px;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.32);
        box-shadow: 0 8px 16px rgba(2, 6, 23, 0.26);
        background: #0f172a;
    }

    .ms-flag-wave {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transform-origin: left center;
        animation: memberFlagWave 4.8s ease-in-out infinite;
    }

    @keyframes memberFlagWave {

        0%,
        100% {
            transform: perspective(800px) rotateY(0deg) skewY(0deg) scaleX(1);
        }

        25% {
            transform: perspective(800px) rotateY(-12deg) skewY(1.6deg) scaleX(1.02);
        }

        50% {
            transform: perspective(800px) rotateY(7deg) skewY(-1.2deg) scaleX(0.99);
        }

        75% {
            transform: perspective(800px) rotateY(-8deg) skewY(0.8deg) scaleX(1.01);
        }
    }

    .ms-hero-kicker {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: rgba(248, 250, 252, 0.78);
    }
</style>

<nav class="nxl-navigation" style="background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 45%, #e0f2fe 100%);">
    <div class="navbar-wrapper" style="background: transparent;">
        {{-- <div class="m-header">
            <a href="#" class="b-brand">
                <!-- ========   change your logo hear   ============ -->
                <img src="{{ asset('assets/img/logo.svg') }}" alt="" class="logo logo-lg">
                <img src="{{ asset('assets/img/logo.svg') }}" alt="" class="logo logo-sm">
            </a>
        </div> --}}

        <div class="navbar-content">
            <div class="px-3 pt-3">
                @if ($isMemberStateSidebarUser)
                    <div class="ms-sidebar-hero">
                        <div class="hero-body card-body py-3 d-flex align-items-center gap-3">
                            <div class="ms-flag-wave-wrap flex-shrink-0">
                                <img src="{{ $sidebarMemberStateFlag }}"
                                    alt="{{ $sidebarMemberState?->name ?? 'Member State' }} flag" class="ms-flag-wave">
                            </div>
                            <div class="flex-grow-1">
                                <div class="ms-hero-kicker">Member State Command Desk</div>
                                <div class="fw-bold">{{ $sidebarMemberState?->name ?? 'Member State' }}</div>
                                <div class="small text-white-50">Agenda 2063 Progress Workspace</div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="card border-0 shadow-sm position-relative overflow-hidden"
                        style="background: linear-gradient(135deg, #0f172a 0%, #0ea5e9 50%, #10b981 100%); color:#f8fafc; border-radius:14px;">
                        <div
                            style="position:absolute; inset:0; background: radial-gradient(circle at 20% 20%, rgba(255,255,255,0.15), transparent 45%), radial-gradient(circle at 80% 0%, rgba(255,255,255,0.18), transparent 40%);">
                        </div>
                        <div class="card-body py-3 d-flex align-items-center gap-3 position-relative">
                            <span class="module-icon" style="background: rgba(255,255,255,0.18); color:#f8fafc;">
                                <i class="feather-cpu"></i>
                            </span>
                            <div class="flex-grow-1">
                                <div class="fw-bold">ATTP Control Center</div>
                                <div class="small text-white-50">Operations & Oversight</div>
                            </div>
                        </div>
                    </div>
                @endif
                <div class="mt-2">
                    <div class="d-flex align-items-center px-3 py-2 rounded-3 shadow-sm"
                        style="background: linear-gradient(120deg, #0ea5e9 0%, #6366f1 100%); color:#f8fafc;">
                        <i class="feather-star me-2"></i>
                        <span id="au-aspiration-ticker" class="small" style="line-height:1.3;">Loading
                            aspiration...</span>
                    </div>
                </div>
            </div>
            <ul class="nxl-navbar">

                @if (auth()->check() && auth()->user()->user_type === 'member_state')
                    <li class="nxl-item nxl-caption">
                        <label>Member State Portal</label>
                    </li>
                    <li class="nxl-item">
                        <a href="{{ route('member-state.dashboard') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-home"></i></span>
                            <span class="nxl-mtext">Dashboard</span>
                        </a>
                    </li>
                    @can('member_state.treaties.view')
                        <li class="nxl-item">
                            <a href="{{ route('member-state.treaties.index') }}" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-file-text"></i></span>
                                <span class="nxl-mtext">Treaties & Agreements</span>
                            </a>
                        </li>
                    @endcan
                    <li class="nxl-item">
                        <a href="{{ route('member-state.communications.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-send"></i></span>
                            <span class="nxl-mtext">Communications</span>
                        </a>
                    </li>
                    <li class="nxl-item">
                        <a href="{{ route('member-state.national-data.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-database"></i></span>
                            <span class="nxl-mtext">National Data</span>
                        </a>
                    </li>
                    <li class="nxl-item">
                        <a href="{{ route('member-state.comparisons.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-bar-chart-2"></i></span>
                            <span class="nxl-mtext">Comparisons</span>
                        </a>
                    </li>
                    <li class="nxl-item">
                        <a href="{{ route('member-state.questions.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-help-circle"></i></span>
                            <span class="nxl-mtext">Ask the AU</span>
                        </a>
                    </li>
                    <li class="nxl-item">
                        <a href="{{ route('member-state.commodities.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-package"></i></span>
                            <span class="nxl-mtext">Commodities</span>
                        </a>
                    </li>
                @endif

                {{-- ================= DASHBOARD ================= --}}
                @can('dashboard.access')
                    <li class="nxl-item nxl-caption">
                        <label>{{ __('admin.dashboard') }}</label>
                    </li>
                    <li class="nxl-item">
                        <a href="{{ route('dashboard') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-home"></i></span>
                            <span class="nxl-mtext">{{ __('admin.overview') }}</span>
                        </a>
                    </li>
                @endcan

                @if ($isAdminSidebarUser)
                    <li class="nxl-item nxl-caption">
                        <label>Knowledge Management</label>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-folder"></i></span>
                            <span class="nxl-mtext">Knowledge Management</span>
                            <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item">
                                <a href="{{ route('data-warehouse.create') }}" class="nxl-link">
                                    <i class="feather-upload-cloud me-2"></i> Add Knowledge Record
                                </a>
                            </li>
                            <li class="nxl-item">
                                <a href="{{ route('data-warehouse.categories') }}" class="nxl-link">
                                    <i class="feather-tag me-2"></i> Knowledge Categories
                                </a>
                            </li>
                            <li class="nxl-item">
                                <a href="{{ route('data-warehouse.index') }}" class="nxl-link">
                                    <i class="feather-folder me-2"></i> File Library
                                </a>
                            </li>
                        </ul>
                    </li>
                @endif

                {{-- ================= NEWS & COMMUNICATIONS ================= --}}
                @if ($canSeeNewsCommunicationSidebar)
                    <li class="nxl-item nxl-caption">
                        <label>News & Communications</label>
                    </li>

                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-message-square"></i></span>
                            <span class="nxl-mtext">News & Communications</span>
                            <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>

                        <ul class="nxl-submenu">
                            @canany(['news.manage', 'news.approve'])
                                <li class="nxl-item">
                                    <a href="{{ route('system.news.index') }}" class="nxl-link">
                                        <i class="feather-send me-2"></i> News Posting
                                    </a>
                                </li>
                            @endcanany

                            @can('communications.view')
                                <li class="nxl-item">
                                    <a href="{{ route('system.communications.index') }}" class="nxl-link">
                                        <i class="feather-message-circle me-2"></i> Member State Communications
                                    </a>
                                </li>
                            @endcan

                            @can('questions.view')
                                <li class="nxl-item">
                                    <a href="{{ route('system.questions.index') }}" class="nxl-link">
                                        <i class="feather-help-circle me-2"></i> Respond to Questions
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endif


                {{-- ================= THINK TANK MODULE ================= --}}
                @if ($canSeeThinkTankFinance)
                    <li class="nxl-item nxl-caption">
                        <label>Think Tank Module</label>
                    </li>

                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-credit-card"></i></span>
                            <span class="nxl-mtext">Think Tank Module</span>
                            <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>

                        <ul class="nxl-submenu">
                            @if (Route::has('think-tanks-admin.consortium-analysis'))
                                <li class="nxl-item">
                                    <a href="{{ route('think-tanks-admin.consortium-analysis') }}" class="nxl-link">
                                        <i class="feather-grid me-2"></i> Consortium Analysis
                                    </a>
                                </li>
                            @endif

                            @if (Route::has('think-tanks-admin.think-tank-analysis'))
                                <li class="nxl-item">
                                    <a href="{{ route('think-tanks-admin.think-tank-analysis') }}" class="nxl-link">
                                        <i class="feather-users me-2"></i> Think Tank Analysis
                                    </a>
                                </li>
                            @endif

                            @if (Route::has('think-tanks-admin.consortium-reports'))
                                <li class="nxl-item">
                                    <a href="{{ route('think-tanks-admin.consortium-reports') }}" class="nxl-link">
                                        <i class="feather-file-text me-2"></i> Consortium Reports
                                    </a>
                                </li>
                            @endif

                            @if (Route::has('think-tanks-admin.think-tank-reports'))
                                <li class="nxl-item">
                                    <a href="{{ route('think-tanks-admin.think-tank-reports') }}" class="nxl-link">
                                        <i class="feather-layers me-2"></i> Think Tank Reports
                                    </a>
                                </li>
                            @endif

                        </ul>
                    </li>
                @endif


                {{-- ================= FINANCIAL GOVERNANCE ================= --}}
                @if ($canSeeFinancialGovernanceSidebar)
                    <li class="nxl-item nxl-caption">
                        <label>{{ __('admin.financial_governance') }}</label>
                    </li>

                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-shield"></i></span>
                            <span class="nxl-mtext">{{ __('admin.governance_setup') }}</span>
                            <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>

                        <ul class="nxl-submenu">
                            @canany(['finance.governance_structure.view', 'finance.governance_structure.manage'])
                                <li class="nxl-item">
                                    <a href="{{ route('finance.governance.index') }}" class="nxl-link">
                                        <i class="feather-git-branch me-2"></i> {{ __('admin.governance_structure') }}
                                    </a>
                                </li>
                            @endcanany
                            @canany(['finance.funders.view', 'finance.funders.manage'])
                                <li class="nxl-item">
                                    <a href="{{ route('finance.funders.index') }}" class="nxl-link">
                                        <i class="feather-globe me-2"></i> {{ __('admin.funding_partners') }}
                                    </a>
                                </li>
                            @endcanany

                            @canany(['finance.program_funding.view', 'finance.program_funding.manage'])
                                <li class="nxl-item">
                                    <a href="{{ route('finance.program-funding.index') }}" class="nxl-link">
                                        <i class="feather-credit-card me-2"></i> {{ __('admin.program_financing') }}
                                    </a>
                                </li>
                            @endcanany



                        </ul>
                    </li>
                @endif


                {{-- ================= BUDGET PLANNING ================= --}}
                @canany(['sector.view', 'program.view', 'project.view', 'activities.view', 'subactivities.view'])
                    <li class="nxl-item nxl-caption">
                        <label>{{ __('admin.budget_planning') }}</label>
                    </li>

                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-folder"></i></span>
                            <span class="nxl-mtext">{{ __('admin.budget_structure') }}</span>
                            <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>

                        <ul class="nxl-submenu">
                            @can('sector.view')
                                <li class="nxl-item">
                                    <a href="{{ route('budget.sectors.index') }}" class="nxl-link">
                                        <i class="feather-layers me-2"></i> {{ __('admin.sectors') }}
                                    </a>
                                </li>
                            @endcan

                            @can('program.view')
                                <li class="nxl-item">
                                    <a href="{{ route('budget.programs.index') }}" class="nxl-link">
                                        <i class="feather-grid me-2"></i> {{ __('admin.programs') }}
                                    </a>
                                </li>
                            @endcan

                            @can('project.view')
                                <li class="nxl-item">
                                    <a href="{{ route('budget.projects.index') }}" class="nxl-link">
                                        <i class="feather-briefcase me-2"></i> {{ __('admin.projects') }}
                                    </a>
                                </li>
                            @endcan

                            @can('activities.view')
                                <li class="nxl-item">
                                    <a href="{{ route('budget.activities.index') }}" class="nxl-link">
                                        <i class="feather-list me-2"></i> {{ __('admin.activities') }}
                                    </a>
                                </li>
                            @endcan

                            @can('subactivities.view')
                                <li class="nxl-item">
                                    <a href="{{ route('budget.subactivities.index') }}" class="nxl-link">
                                        <i class="feather-check-square me-2"></i> {{ __('admin.sub_activities') }}
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcanany


                {{-- ================= BUDGET EXECUTION ================= --}}
                @canany(['finance.commitments.view', 'finance.awp.view', 'finance.purchase_requests.view', 'finance.resources.view',
                    'finance.executions.view'])
                    <li class="nxl-item nxl-caption">
                        <label>{{ __('admin.budget_execution') }}</label>
                    </li>

                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-activity"></i></span>
                            <span class="nxl-mtext">{{ __('admin.execution_commitments') }}</span>
                            <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>

                        <ul class="nxl-submenu">
                            @can('finance.commitments.view')
                                <li class="nxl-item">
                                    <a href="{{ route('finance.commitments.index') }}" class="nxl-link">
                                        <i class="feather-edit me-2"></i> Planned Commitments
                                    </a>
                                </li>
                            @endcan

                            @can('finance.purchase_requests.view')
                                <li class="nxl-item">
                                    <a href="{{ route('finance.purchase-requests.index') }}" class="nxl-link">
                                        <i class="feather-file-text me-2"></i> {{ __('admin.purchase_requests') }}
                                    </a>
                                </li>
                                <li class="nxl-item">
                                    <a href="{{ route('procurement.invoices.index') }}" class="nxl-link">
                                        <i class="feather-file-text me-2"></i> Vendor Invoices
                                    </a>
                                </li>
                                <li class="nxl-item">
                                    <a href="{{ route('procurement.purchase-orders.index') }}" class="nxl-link">
                                        <i class="feather-clipboard me-2"></i> Purchase Orders
                                    </a>
                                </li>
                                <li class="nxl-item">
                                    <a href="{{ route('procurement.disbursements.index') }}" class="nxl-link">
                                        <i class="feather-dollar-sign me-2"></i> Planned Disbursements
                                    </a>
                                </li>
                            @endcan

                            @can('finance.resources.view')
                                <li class="nxl-item">
                                    <a href="{{ route('finance.resources.categories.index') }}" class="nxl-link">
                                        <i class="feather-folder me-2"></i> {{ __('admin.resource_categories') }}
                                    </a>
                                </li>

                                <li class="nxl-item">
                                    <a href="{{ route('finance.resources.items.index') }}" class="nxl-link">
                                        <i class="feather-box me-2"></i> {{ __('admin.resource_items') }}
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcanany

                {{-- ================= WORK PLANS REGISTRY ================= --}}
                @can('finance.awp.create')
                    <li class="nxl-item nxl-caption">
                        <label>Work Plans Registry</label>
                    </li>

                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-clipboard"></i></span>
                            <span class="nxl-mtext">Work Plans Registry</span>
                            <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>

                        <ul class="nxl-submenu">
                            <li class="nxl-item">
                                <a href="{{ route('finance.awp.create') }}" class="nxl-link">
                                    <i class="feather-folder-plus me-2"></i> Create Work Plan
                                </a>
                            </li>
                        </ul>
                    </li>
                @endcan


                {{-- ================= REPORTING ================= --}}
                @canany(['budget.reports.view', 'budget.project_financial_position.view', 'budget.summary.view', 'finance.executions.view', 'hr.analytics.view',
                    'prescreening.reports.view_all', 'evaluations.view_all'])
                    <li class="nxl-item nxl-caption">
                        <label>{{ __('admin.reports_analytics') }}</label>
                    </li>

                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon">
                                <i class="feather-bar-chart"></i>
                            </span>
                            <span class="nxl-mtext">{{ __('admin.reports_oversight') }}</span>
                            <span class="nxl-arrow">
                                <i class="feather-chevron-right"></i>
                            </span>
                        </a>

                        <ul class="nxl-submenu">

                            {{-- Budget Reports --}}
                            @canany(['budget.reports.view', 'budget.project_financial_position.view'])
                                <li class="nxl-item">
                                    <a href="{{ route('budget.reports.index') }}" class="nxl-link">
                                        <i class="feather-file-text me-2"></i>
                                        {{ __('admin.budget_reports') }}
                                    </a>
                                </li>
                                <li class="nxl-item">
                                    <a href="{{ route('budget.reports.commitments') }}" class="nxl-link">
                                        <i class="feather-bar-chart-2 me-2"></i>
                                        {{ __('admin.commitment_report') }}
                                    </a>
                                </li>
                                <li class="nxl-item">
                                    <a href="{{ route('budget.reports.ifr') }}" class="nxl-link">
                                        <i class="feather-activity me-2"></i>
                                        {{ __('admin.ifr_report') }}
                                    </a>
                                </li>
                                <li class="nxl-item">
                                    <a href="{{ route('budget.reports.project-financial-position') }}" class="nxl-link">
                                        <i class="feather-briefcase me-2"></i>
                                        {{ __('admin.project_financial_position') }}
                                    </a>
                                </li>
                            @endcanany

                            {{-- Execution Dashboard --}}
                            @can('finance.executions.view')
                                <li class="nxl-item">
                                    <a href="{{ route('finance.execution.dashboard') }}" class="nxl-link">
                                        <i class="feather-trending-up me-2"></i>
                                        {{ __('admin.execution_dashboard') }}
                                    </a>

                                </li>
                            @endcan

                            {{-- Summary Dashboard --}}
                            @can('budget.summary.view')
                                <li class="nxl-item">
                                    <a href="{{ route('budget.summary.dashboard') }}" class="nxl-link">
                                        <i class="feather-pie-chart me-2"></i>
                                        {{ __('admin.program_allocation') }}
                                    </a>
                                </li>
                            @endcan

                            {{-- Executive Reports --}}
                            @can('budget.summary.view')
                                <li class="nxl-item">
                                    <a href="{{ route('budget.summary.executive') }}" class="nxl-link">
                                        <i class="feather-clipboard me-2"></i>
                                        {{ __('admin.allocations_reports') }}
                                    </a>
                                </li>
                            @endcan

                            {{-- Prescreening Reports --}}
                            @can('prescreening.reports.view_all')
                                <li class="nxl-item">
                                    <a href="{{ route('reports.prescreening.index') }}" class="nxl-link">
                                        <i class="feather-file-text me-2"></i>
                                        {{ __('admin.prescreening_reports') }}
                                    </a>
                                </li>
                            @endcan

                            @can('evaluations.view_all')
                                <li class="nxl-item">
                                    <a href="{{ route('reports.evaluations.index') }}" class="nxl-link">
                                        <i class="feather-file-text me-2"></i>
                                        {{ __('admin.evaluation_reports') }}
                                    </a>
                                </li>
                            @endcan


                            {{-- HR ANALYTICS --}}
                            @can('hr.analytics.view')
                                <li class="nxl-item">
                                    <a href="{{ route('hr.analytics') }}" class="nxl-link">
                                        <i class="feather-bar-chart-2 me-2"></i>
                                        HR Analytics
                                    </a>
                                </li>
                            @endcan

                        </ul>
                    </li>
                @endcanany


                {{-- ======================================================
                    | MONITORING & EVALUATION
                    ====================================================== --}}
                @canany(['me.configuration.view', 'me.configuration.manage', 'world.indicators.manage'])
                    <li class="nxl-item nxl-caption">
                        <label>{{ __('Monitoring & Evaluation') }}</label>
                    </li>

                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-target"></i></span>
                            <span class="nxl-mtext">M&E Configuration</span>
                            <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>

                        <ul class="nxl-submenu">
                            @canany(['me.configuration.view', 'me.configuration.manage'])
                                <li class="nxl-item">
                                    <a href="{{ route('budget.me.indicators.index') }}" class="nxl-link">
                                        <i class="feather-target me-2"></i> Indicators
                                    </a>
                                </li>

                                <li class="nxl-item">
                                    <a href="{{ route('budget.me.data-sources.index') }}" class="nxl-link">
                                        <i class="feather-database me-2"></i> Data Source Controller
                                    </a>
                                </li>

                                <li class="nxl-item nxl-hasmenu">
                                    <a href="javascript:void(0);" class="nxl-link">
                                        <i class="feather-clipboard me-2"></i> Survey
                                        <span class="nxl-arrow ms-auto"><i class="feather-chevron-right"></i></span>
                                    </a>

                                    <ul class="nxl-submenu">
                                        @canany(['me.configuration.view', 'me.configuration.manage'])
                                            <li class="nxl-item">
                                                <a href="{{ route('budget.me.surveys.index') }}" class="nxl-link">
                                                    <i class="feather-home me-2"></i> Overview
                                                </a>
                                            </li>
                                        @endcanany

                                        @canany(['me.configuration.view', 'me.configuration.manage'])
                                            <li class="nxl-item">
                                                <a href="{{ route('budget.me.surveys.responses') }}" class="nxl-link">
                                                    <i class="feather-inbox me-2"></i> Responses
                                                </a>
                                            </li>
                                        @endcanany

                                        @canany(['me.configuration.view', 'me.configuration.manage'])
                                            <li class="nxl-item">
                                                <a href="{{ route('budget.me.surveys.reports') }}" class="nxl-link">
                                                    <i class="feather-bar-chart-2 me-2"></i> Reports
                                                </a>
                                            </li>
                                        @endcanany

                                        @can('me.configuration.manage')
                                            <li class="nxl-item">
                                                <a href="{{ route('budget.me.surveys.questionnaires.create') }}" class="nxl-link">
                                                    <i class="feather-plus-square me-2"></i> Add Questionnaires
                                                </a>
                                            </li>
                                        @endcan

                                        @canany(['me.configuration.view', 'me.configuration.manage'])
                                            <li class="nxl-item">
                                                <a href="{{ route('budget.me.surveys.questionnaires') }}" class="nxl-link">
                                                    <i class="feather-book-open me-2"></i> Questionnaire Library
                                                </a>
                                            </li>
                                        @endcanany

                                        @canany(['me.configuration.view', 'me.configuration.manage'])
                                            <li class="nxl-item">
                                                <a href="{{ route('budget.me.surveys.qr') }}" class="nxl-link">
                                                    <i class="feather-grid me-2"></i> Generate QR Code
                                                </a>
                                            </li>
                                        @endcanany
                                    </ul>
                                </li>

                                <li class="nxl-item">
                                    <a href="{{ route('budget.me-configuration.indicator-levels.index') }}" class="nxl-link">
                                        <i class="feather-layers me-2"></i> Indicator Levels
                                    </a>
                                </li>

                                <li class="nxl-item">
                                    <a href="{{ route('budget.me-configuration.frequencies.index') }}" class="nxl-link">
                                        <i class="feather-clock me-2"></i> Reporting Frequencies
                                    </a>
                                </li>

                                <li class="nxl-item">
                                    <a href="{{ route('budget.me-configuration.units.index') }}" class="nxl-link">
                                        <i class="feather-sliders me-2"></i> Indicator Units
                                    </a>
                                </li>
                                <li class="nxl-item">
                                    <a href="{{ route('budget.me-configuration.definitions.index') }}" class="nxl-link">
                                        <i class="feather-file-text me-2"></i> Definitions / Formulas
                                    </a>
                                </li>
                                <li class="nxl-item">
                                    <a href="{{ route('budget.me-configuration.methodologies.index') }}" class="nxl-link">
                                        <i class="feather-book-open me-2"></i> Methodologies
                                    </a>
                                </li>
                            @endcanany

                        </ul>
                    </li>
                @endcanany


                {{-- ======================================================
                    | HUMAN CAPITAL MANAGEMENT
                    ====================================================== --}}
                @canany(['hr.access', 'hrm.positions.view', 'hrm.vacancies.view'])
                    <li class="nxl-item nxl-caption">
                        <label>{{ __('admin.human_capital') }}</label>
                    </li>

                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon">
                                <i class="feather-users"></i>
                            </span>
                            <span class="nxl-mtext">Human Resources</span>
                            <span class="nxl-arrow">
                                <i class="feather-chevron-right"></i>
                            </span>
                        </a>

                        <ul class="nxl-submenu">

                            {{-- POSITIONS --}}
                            @can('hrm.positions.view')
                                <li class="nxl-item">
                                    <a href="{{ route('hr.positions.index') }}" class="nxl-link">
                                        <i class="feather-briefcase me-2"></i>
                                        Positions
                                    </a>
                                </li>
                            @endcan

                            {{-- RECRUITMENT / VACANCIES --}}
                            @can('hrm.vacancies.view')
                                <li class="nxl-item">
                                    <a href="{{ route('hr.vacancies.index') }}" class="nxl-link">
                                        <i class="feather-user-plus me-2"></i>
                                        Recruitment
                                    </a>
                                </li>
                            @endcan



                            <hr>

                            {{-- PUBLIC CAREERS (NO PERMISSION) --}}
                            <li class="nxl-item">
                                <a href="{{ route('careers.index') }}" target="_blank" class="nxl-link">
                                    <i class="feather-globe me-2"></i>
                                    Public Careers
                                </a>
                            </li>

                        </ul>
                    </li>
                @endcanany

                {{-- ======================================================
                | PROCUREMENT MANAGEMENT
                ====================================================== --}}
                @if ($canSeeProcurementSidebar)
                    <li class="nxl-item nxl-caption">
                        <label>{{ __('admin.procurement') }}</label>
                    </li>
                    {{-- ================= PROCUREMENT SETTINGS ================= --}}
                    @if ($canSeeProcurementSettingsSidebar)
                        <li class="nxl-item nxl-hasmenu">
                            <a href="javascript:void(0);" class="nxl-link">
                                <span class="nxl-micon">
                                    <i class="feather-settings"></i>
                                </span>
                                <span class="nxl-mtext">Procurement Settings</span>
                                <span class="nxl-arrow">
                                    <i class="feather-chevron-right"></i>
                                </span>
                            </a>

                            <ul class="nxl-submenu">
                                @canany(['procurement.settings.manage', 'procurement.settings.geographics', 'procurement.view_all'])
                                    <li class="nxl-item">
                                        <a href="{{ route('procurement.settings.geographics.index') }}" class="nxl-link">
                                            <i class="feather-map-pin me-2"></i>
                                            Geographics
                                        </a>
                                    </li>
                                @endcanany

                                @canany(['procurement.settings.manage', 'procurement.settings.methods', 'procurement.view_all'])
                                    <li class="nxl-item">
                                        <a href="{{ route('procurement.settings.method-planned.index') }}" class="nxl-link">
                                            <i class="feather-calendar me-2"></i>
                                            Methods Planned
                                        </a>
                                    </li>
                                @endcanany

                                @canany(['procurement.settings.manage', 'procurement.settings.stages', 'procurement.view_all'])
                                    <li class="nxl-item">
                                        <a href="{{ route('procurement.settings.stages.index') }}" class="nxl-link">
                                            <i class="feather-layers me-2"></i>
                                            Stages
                                        </a>
                                    </li>
                                @endcanany

                                @canany(['procurement.settings.manage', 'procurement.settings.statuses', 'procurement.view_all'])
                                    <li class="nxl-item">
                                        <a href="{{ route('procurement.settings.statuses.index') }}" class="nxl-link">
                                            <i class="feather-flag me-2"></i>
                                            Statuses
                                        </a>
                                    </li>
                                @endcanany

                                @canany(['procurement.settings.manage', 'procurement.settings.step_stages', 'procurement.view_all'])
                                    <li class="nxl-item">
                                        <a href="{{ route('procurement.settings.step-stages.index') }}" class="nxl-link">
                                            <i class="feather-git-branch me-2"></i>
                                            Step Stages
                                        </a>
                                    </li>
                                @endcanany

                                @canany(['procurement.settings.manage', 'procurement.settings.step_approvals', 'procurement.view_all'])
                                    <li class="nxl-item">
                                        <a href="{{ route('procurement.settings.step-approvals.index') }}" class="nxl-link">
                                            <i class="feather-check-circle me-2"></i>
                                            Step Approvals
                                        </a>
                                    </li>
                                @endcanany
                            </ul>
                        </li>
                    @endif
                    {{-- ================= PROCUREMENT STRUCTURE ================= --}}
                    @canany(['procurement.plan.view', 'procurement.plan.create', 'procurement.view_all'])
                        <li class="nxl-item nxl-hasmenu">
                            <a href="javascript:void(0);" class="nxl-link">
                                <span class="nxl-micon">
                                    <i class="feather-layers"></i>
                                </span>
                                <span class="nxl-mtext">Procurement Structure</span>
                                <span class="nxl-arrow">
                                    <i class="feather-chevron-right"></i>
                                </span>
                            </a>

                            <ul class="nxl-submenu">
                                @canany(['procurement.plan.view', 'procurement.view_all'])
                                    <li class="nxl-item">
                                        <a href="{{ route('procurement.structure.index') }}" class="nxl-link">
                                            <i class="feather-check-square me-2"></i>
                                            @can('procurement.view_all')
                                                All Procurement Plans
                                            @else
                                                My Procurement Plan
                                            @endcan
                                        </a>
                                    </li>
                                @endcanany

                                @canany(['procurement.plan.create', 'procurement.view_all'])
                                    <li class="nxl-item">
                                        <a href="{{ route('procurement.plans.create') }}" class="nxl-link">
                                            <i class="feather-file-text me-2"></i>
                                            Create Plan Item
                                        </a>
                                    </li>
                                @endcanany

                                @canany(['procurement.plan.view', 'procurement.view_all'])
                                    <li class="nxl-item">
                                        <a href="{{ route('procurement.plans.sheet') }}" class="nxl-link">
                                            <i class="feather-share-2 me-2"></i>
                                            @can('procurement.view_all')
                                                All Procurement Sheets
                                            @else
                                                My Procurement Sheet
                                            @endcan
                                        </a>
                                    </li>
                                @endcanany
                            </ul>
                        </li>
                    @endcanany
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon">
                                <i class="feather-briefcase"></i>
                            </span>
                            <span class="nxl-mtext">Procurement</span>
                            <span class="nxl-arrow">
                                <i class="feather-chevron-right"></i>
                            </span>
                        </a>

                        <ul class="nxl-submenu">

                            {{-- ================= CORE PROCUREMENT ================= --}}
                            {{-- @can('procurement.view') --}}
                            <li class="nxl-item">
                                <a href="{{ route('procurements.index') }}" class="nxl-link">
                                    <i class="feather-list me-2"></i>
                                    Procurement Registry
                                </a>
                            </li>
                            {{-- @endcan --}}

                            @can('forms.manage')
                                <li class="nxl-item">
                                    <a href="{{ route('procurement.contract-negotiations.index') }}" class="nxl-link">
                                        <i class="feather-file-text me-2"></i>
                                        Contract Negotiations
                                    </a>
                                </li>
                            @endcan

                            @can('vendor.manage')
                                <li class="nxl-item">
                                    <a href="{{ route('vendors.index') }}" class="nxl-link">
                                        <i class="feather-users me-2"></i>
                                        Procurement Vendors
                                    </a>
                                </li>
                            @endcan

                            {{-- ================= SUBMISSIONS ================= --}}
                            {{-- @can('procurement.view') --}}
                            <li class="nxl-item">
                                <a href="{{ route('procurement.submissions.index') }}" class="nxl-link">
                                    <i class="feather-inbox me-2"></i>
                                    Applicants Submissions
                                </a>
                            </li>
                            {{-- @endcan --}}

                            {{-- ================= FORMS & SETUP ================= --}}
                            @can('forms.manage')
                                <li class="nxl-item">
                                    <a href="{{ route('forms.index') }}" class="nxl-link">
                                        <i class="feather-file-text me-2"></i>
                                        Forms Builder
                                    </a>
                                </li>
                            @endcan



                            <li class="nxl-item">
                                <a href="{{ route('public.procurement.index') }}" target="_blank" class="nxl-link">
                                    <i class="feather-globe me-2"></i>
                                    Public Procurements
                                </a>
                            </li>
                        </ul>
                    </li>



                @endif


                {{-- ================= VENDOR MANAGEMENT ================= --}}
                @canany(['vendor.manage', 'vendor.requests.manage'])
                    <li class="nxl-item nxl-caption">
                        <label>Vendors</label>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon">
                                <i class="feather-briefcase"></i>
                            </span>
                            <span class="nxl-mtext">Vendor Management</span>
                            <span class="nxl-arrow">
                                <i class="feather-chevron-right"></i>
                            </span>
                        </a>
                        <ul class="nxl-submenu">
                            @can('vendor.manage')
                                <li class="nxl-item">
                                    <a href="{{ route('vendors.index') }}" class="nxl-link">
                                        <i class="feather-users me-2"></i>
                                        Vendor Directory
                                    </a>
                                </li>
                                <li class="nxl-item">
                                    <a href="{{ route('vendors.create') }}" class="nxl-link">
                                        <i class="feather-user-plus me-2"></i>
                                        Create Vendor
                                    </a>
                                </li>
                                <li class="nxl-item">
                                    <a href="{{ route('vendors.categories.index') }}" class="nxl-link">
                                        <i class="feather-tag me-2"></i>
                                        Vendor Categories
                                    </a>
                                </li>
                            @endcan
                            @can('vendor.requests.manage')
                                <li class="nxl-item">
                                    <a href="{{ route('vendors.requests.messages.index') }}" class="nxl-link">
                                        <i class="feather-message-square me-2"></i>
                                        Clarification Messages
                                    </a>
                                </li>
                                <li class="nxl-item">
                                    <a href="{{ route('vendors.requests.information.index') }}" class="nxl-link">
                                        <i class="feather-inbox me-2"></i>
                                        Information Requests
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcanany


                {{-- ================= PRESCREENING ================= --}}
                @canany(['prescreening.access', 'prescreening.evaluate', 'prescreening.manage',
                    'prescreening.view_all'])
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon">
                                <i class="feather-check-square"></i>
                            </span>
                            <span class="nxl-mtext">Prescreening</span>
                            <span class="nxl-arrow">
                                <i class="feather-chevron-right"></i>
                            </span>
                        </a>

                        <ul class="nxl-submenu">

                            {{-- TEMPLATE CONFIGURATION --}}
                            @can('prescreening.manage')
                                <li class="nxl-item">
                                    <a href="{{ route('prescreening.templates.index') }}" class="nxl-link">
                                        <i class="feather-layout me-2"></i>
                                        Prescreening Templates
                                    </a>
                                </li>
                            @endcan

                            {{-- TEMPLATE → PROCUREMENT --}}
                            @can('prescreening.manage')
                                <li class="nxl-item">
                                    <a href="{{ route('procurements.index') }}" class="nxl-link">
                                        <i class="feather-link me-2"></i>
                                        Assign Template to Procurement
                                    </a>
                                </li>
                            @endcan

                            {{-- USER ASSIGNMENT --}}
                            @can('prescreening.manage')
                                <li class="nxl-item">
                                    <a href="{{ route('prescreening.assignments.index') }}" class="nxl-link">
                                        <i class="feather-users me-2"></i>
                                        Prescreening Assignments
                                    </a>
                                </li>
                            @endcan

                            {{-- EVALUATOR VIEW --}}
                            @canany(['prescreening.evaluate', 'prescreening.view_all'])
                                <li class="nxl-item">
                                    <a href="{{ route('prescreening.submissions.index') }}" class="nxl-link">
                                        <i class="feather-inbox me-2"></i>
                                        Prescreening Submissions
                                    </a>
                                </li>
                            @endcanany

                            @can('prescreening.evaluate')
                                <li class="nxl-item">
                                    <a href="{{ route('prescreening.assignments.my') }}" class="nxl-link">
                                        <i class="feather-user-check me-2"></i>
                                        My Assignments
                                    </a>
                                </li>
                            @endcan

                        </ul>
                    </li>
                @endcanany




                @canany(['evaluations.manage', 'evaluations.evaluate'])
                    <li class="nxl-item nxl-caption">
                        <label>{{ __('admin.evaluation') }}</label>
                    </li>

                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon">
                                <i class="feather-check-square"></i>
                            </span>
                            <span class="nxl-mtext">Evaluations</span>
                            <span class="nxl-arrow">
                                <i class="feather-chevron-right"></i>
                            </span>
                        </a>

                        <ul class="nxl-submenu">

                            {{-- ================= CONFIGURATION ================= --}}
                            @can('evaluations.manage')
                                <li class="nxl-item">
                                    <a href="{{ route('evals.cfg.index') }}" class="nxl-link">
                                        <i class="feather-settings me-2"></i>
                                        Evaluation Configuration
                                    </a>
                                </li>
                            @endcan

                            {{-- ================= ASSIGNMENTS ================= --}}
                            @can('evaluations.manage')
                                <li class="nxl-item">
                                    <a href="{{ route('eval.assign.hub') }}" class="nxl-link">
                                        <i class="feather-user-plus me-2"></i>
                                        Assign Evaluators
                                    </a>
                                </li>
                            @endcan

                            {{-- ================= MY EVALUATIONS ================= --}}
                            @can('evaluations.evaluate')
                                <li class="nxl-item">
                                    <a href="{{ route('my.eval.index') }}" class="nxl-link">
                                        <i class="feather-edit me-2"></i>
                                        My Evaluations
                                    </a>
                                </li>
                            @endcan

                            {{-- ================= PANEL EVALUATIONS ================= --}}
                            @can('evaluations.view_all')
                                <li class="nxl-item">
                                    <a href="{{ route('eval.panel.index') }}"
                                        class="nxl-link {{ request()->routeIs('eval.panel.*') ? 'active' : '' }}">
                                        <i class="feather-layers me-2"></i>
                                        Panel Evaluations
                                    </a>
                                </li>
                            @endcan

                        </ul>
                    </li>
                @endcanany


                {{-- ================= SITE VISITS ================= --}}
                {{-- ================= SITE VISITS ================= --}}
                @canany(['site_visits.view', 'site_visits.create', 'site_visits.approve'])
                    <li class="nxl-item nxl-caption">
                        <label>{{ __('admin.site_visits') }}</label>
                    </li>

                    @can('site_visits.view')
                        <li class="nxl-item">
                            <a href="{{ route('site-visits.index') }}" class="nxl-link">
                                <i class="feather-map-pin me-2"></i>
                                Site Visits
                            </a>
                        </li>
                    @endcan

                    @can('site_visits.create')
                        <li class="nxl-item">
                            <a href="{{ route('site-visits.create') }}" class="nxl-link">
                                <i class="feather-plus-square me-2"></i>
                                Create Site Visit
                            </a>
                        </li>
                    @endcan

                    @can('site_visits.approve')
                        <li class="nxl-item">
                            <a href="{{ route('site-visits.index', ['filter' => 'pending']) }}" class="nxl-link">
                                <i class="feather-check-circle me-2"></i>
                                Pending Approvals
                            </a>
                        </li>

                        <li class="nxl-item">
                            <a href="{{ route('site-visits.reports.index') }}" class="nxl-link">
                                <i class="feather-bar-chart-2 me-2"></i>
                                Site Visit Reports
                            </a>
                        </li>
                    @endcan
                @endcanany



















                {{-- ================= AU MASTER DATA ================= --}}
                @canany(['settings.au_master_data.view', 'settings.au_master_data.create',
                    'settings.au_master_data.edit', 'treaties.view', 'treaties.create', 'treaties.edit'])
                    <li class="nxl-item nxl-caption">
                        <label>AU Master Data</label>
                    </li>

                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-globe"></i></span>
                            <span class="nxl-mtext">AU Configuration</span>
                            <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>

                        <ul class="nxl-submenu">
                            @canany(['settings.au_master_data.view', 'treaties.view'])
                                @can('settings.au_master_data.view')
                                    <li class="nxl-item">
                                        <a href="{{ route('settings.au.member-states.index') }}" class="nxl-link">
                                            <i class="feather-flag me-2"></i> Member States
                                        </a>
                                    </li>

                                    <li class="nxl-item">
                                        <a href="{{ route('settings.au.regional-blocks.index') }}" class="nxl-link">
                                            <i class="feather-map me-2"></i> Regional Blocks (RECs)
                                        </a>
                                    </li>

                                    <li class="nxl-item">
                                        <a href="{{ route('settings.au.aspirations.index') }}" class="nxl-link">
                                            <i class="feather-star me-2"></i> Aspirations
                                        </a>
                                    </li>

                                    <li class="nxl-item">
                                        <a href="{{ route('settings.au.goals.index') }}" class="nxl-link">
                                            <i class="feather-target me-2"></i> Goals
                                        </a>
                                    </li>

                                    <li class="nxl-item">
                                        <a href="{{ route('settings.au.flagship-projects.index') }}" class="nxl-link">
                                            <i class="feather-award me-2"></i> Flagship Projects
                                        </a>
                                    </li>
                                @endcan
                                @can('treaties.view')
                                    <li class="nxl-item">
                                        <a href="{{ route('settings.au.treaties.index') }}" class="nxl-link">
                                            <i class="feather-file-text me-2"></i> Treaties & Agreements
                                        </a>
                                    </li>
                                @endcan
                            @endcanany
                        </ul>
                    </li>
                @endcanany


                {{-- ================= COMMUNICATIONS ================= --}}
                @canany(['communications.view', 'communications.respond', 'news.manage', 'news.approve', 'questions.view', 'questions.respond',
                    'national_data.review', 'national_data.approve'])
                    <li class="nxl-item nxl-caption">
                        <label>Communications</label>
                    </li>

                    @can('communications.view')
                        <li class="nxl-item">
                            <a href="{{ route('system.communications.index') }}" class="nxl-link">
                                <i class="feather-message-circle me-2"></i> Member State Communications
                            </a>
                        </li>
                    @endcan
                    @canany(['news.manage', 'news.approve'])
                        <li class="nxl-item">
                            <a href="{{ route('system.news.index') }}" class="nxl-link">
                                <i class="feather-send me-2"></i> News Posting
                            </a>
                        </li>
                    @endcanany
                    @can('questions.view')
                        <li class="nxl-item">
                            <a href="{{ route('system.questions.index') }}" class="nxl-link">
                                <i class="feather-help-circle me-2"></i> Respond to Questions
                            </a>
                        </li>
                    @endcan
                    @can('national_data.review')
                        <li class="nxl-item">
                            <a href="{{ route('system.national-data-reviews.index') }}" class="nxl-link">
                                <i class="feather-check-square me-2"></i> National Data Reviews
                            </a>
                        </li>
                    @endcan
                @endcanany

                {{-- ================= WORLD INDICATORS ================= --}}
                @can('world.indicators.manage')
                    <li class="nxl-item nxl-caption">
                        <label>World Indicators</label>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-globe"></i></span>
                            <span class="nxl-mtext">World Indicators</span>
                            <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item">
                                <a href="{{ route('budget.me.world-indicators.settings.edit') }}#api-controls"
                                    class="nxl-link">
                                    <i class="feather-sliders me-2"></i> API Controls
                                </a>
                            </li>
                            <li class="nxl-item">
                                <a href="{{ route('budget.me.world-indicators.settings.edit') }}#imf-data"
                                    class="nxl-link">
                                    <i class="feather-trending-up me-2"></i> IMF Data
                                </a>
                            </li>
                            <li class="nxl-item">
                                <a href="{{ route('budget.me.world-indicators.settings.edit') }}#world-bank-data"
                                    class="nxl-link">
                                    <i class="feather-bar-chart-2 me-2"></i> World Bank Data
                                </a>
                            </li>
                        </ul>
                    </li>
                @endcan

                {{-- ================= SYSTEM MANAGEMENT ================= --}}
                @canany(['users.manage', 'roles.manage', 'permissions.manage', 'system.audit.view'])
                    <li class="nxl-item nxl-caption">
                        <label>{{ __('admin.users_security') }}</label>
                    </li>

                    @can('roles.manage')
                        <li class="nxl-item">
                            <a href="{{ route('system.roles.index') }}" class="nxl-link">
                                <i class="feather-shield me-2"></i> Roles Management
                            </a>
                        </li>
                    @endcan

                    @can('permissions.manage')
                        <li class="nxl-item">
                            <a href="{{ route('system.permissions.index') }}" class="nxl-link">
                                <i class="feather-lock me-2"></i> Permissions
                            </a>
                        </li>
                    @endcan

                    @can('users.manage')
                        <li class="nxl-item">
                            <a href="{{ route('system.users.index') }}" class="nxl-link">
                                <i class="feather-users me-2"></i> Users
                            </a>
                        </li>
                    @endcan

                    @can('system.audit.view')
                        <li class="nxl-item">
                            <a href="{{ route('system.audit.index') }}" class="nxl-link">
                                <i class="feather-activity me-2"></i> System Audit
                            </a>
                        </li>
                    @endcan

                    @can('users.manage')
                        <li class="nxl-item">
                            <a href="{{ route('system.attp-ai-guide.settings') }}" class="nxl-link">
                                <i class="feather-bot me-2"></i> ATTP AI Guide
                            </a>
                        </li>
                    @endcan
                @endcanany


            </ul>





            {{-- Footer card --}}
            <div class="card text-center mt-4">
                <div class="card-body">
                    <i class="feather-clipboard fs-4 text-dark"></i>
                    <h6 class="mt-4 text-dark fw-bolder">ATTP</h6>
                    <p class="fs-11 my-3 text-dark">
                        Manage bidding projects, evaluation committees, and procurement reporting for Africa’s
                        development initiatives.
                    </p>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-danger text-white w-100">
                            <i class="feather-log-out me-1"></i> {{ __('common.logout') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</nav>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const aspirationEl = document.getElementById('au-aspiration-ticker');
        const aspirations = [
            'A prosperous Africa based on inclusive growth and sustainable development.',
            'An integrated continent, politically united, based on Pan-African ideals.',
            'An Africa of good governance, democracy, respect for human rights, justice and rule of law.',
            'A peaceful and secure Africa.',
            'An Africa with a strong cultural identity, common heritage, values and ethics.',
            'An Africa whose development is people-driven, especially by women and youth.',
            'Africa as a strong, united, resilient and influential global player and partner.'
        ];
        let idx = 0;
        const rotate = () => {
            if (aspirationEl) aspirationEl.textContent = aspirations[idx];
            idx = (idx + 1) % aspirations.length;
        };
        rotate();
        setInterval(rotate, 5000);
    });
</script>
