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
    $discussionSidebarPermissions = [
        'discussions.view',
        'discussions.create',
        'discussions.manage',
        'discussions.thematic_areas.manage',
        'discussions.participants.manage',
        'discussions.moderate',
    ];
    $canSeeDiscussionSidebar = $sidebarUser
        && collect($discussionSidebarPermissions)->contains(
            fn($permission) => $sidebarUser->hasPermission($permission)
        );
    $isAdminSidebarUser = (bool) ($sidebarUser?->isSuperAdmin() || $sidebarUser?->isAdmin());
    $thinkTankFinancePermissions = [
        'think_tanks.funding.view',
        'consortiums.view',
        'think_tank.procurement.review',
        'think_tank.procurement.reports',
        'think_tank.procurement.step',
        'procurement.view_all',
        'procurement.manage_all',
    ];
    $canSeeThinkTankFinance = $sidebarUser
        && collect($thinkTankFinancePermissions)->contains(
            fn($permission) => $sidebarUser->can($permission)
        );
    $canManageThinkTankUsers = (bool) ($sidebarUser?->can('users.manage'));
    $canSeeThinkTankModule = $canSeeThinkTankFinance || $canManageThinkTankUsers;
    $vendorSidebarAlerts = $sidebarUser && $sidebarUser->can('vendor.requests.manage') && Route::has('vendors.requests.alerts.read')
        ? \App\Support\VendorAdminAlerts::forUser($sidebarUser)
        : [];
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

    .vendor-sidebar-alerts {
        padding: 12px 16px 0;
        position: relative;
    }

    .vendor-alert-strip {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 8px;
        position: relative;
        z-index: 20;
    }

    .vendor-alert-tile {
        position: relative;
    }

    .vendor-alert-button {
        width: 100%;
        height: 38px;
        border: 1px solid rgba(100, 116, 139, 0.24);
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.9);
        color: #0f172a;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        position: relative;
        box-shadow: 0 8px 16px rgba(15, 23, 42, 0.08);
    }

    .vendor-alert-button:hover,
    .vendor-alert-button:focus {
        border-color: rgba(14, 165, 233, 0.62);
        color: #0284c7;
        outline: none;
    }

    .vendor-alert-badge {
        min-width: 17px;
        height: 17px;
        padding: 0 5px;
        border-radius: 999px;
        background: #dc2626;
        color: #fff;
        font-size: 0.64rem;
        font-weight: 800;
        line-height: 17px;
        position: absolute;
        top: -6px;
        right: -4px;
    }

    .vendor-alert-badge.is-zero {
        display: none;
    }

    .vendor-alert-popover {
        position: fixed;
        left: var(--vendor-alert-left, 290px);
        top: var(--vendor-alert-top, 120px);
        width: min(360px, calc(100vw - 32px));
        max-height: 360px;
        overflow-y: auto;
        border: 1px solid #dbe4ef;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 22px 46px rgba(15, 23, 42, 0.24);
        display: none;
        z-index: 3000;
    }

    .vendor-alert-popover.is-visible {
        display: block;
    }

    .vendor-alert-popover-header {
        padding: 12px 14px;
        border-bottom: 1px solid #e2e8f0;
        font-weight: 800;
        color: #0f172a;
        background: #f8fafc;
    }

    .vendor-alert-item {
        display: block;
        padding: 11px 14px;
        color: #334155;
        border-bottom: 1px solid #eef2f7;
        text-decoration: none;
    }

    .vendor-alert-item:hover {
        background: #f0f9ff;
        color: #0f172a;
    }

    .vendor-alert-item.is-unread {
        border-left: 3px solid #0ea5e9;
        background: #f8fafc;
    }

    .vendor-alert-item-title {
        color: #0f172a;
        font-size: 0.82rem;
        font-weight: 800;
        line-height: 1.25;
    }

    .vendor-alert-item-meta {
        color: #64748b;
        font-size: 0.72rem;
        line-height: 1.4;
        margin-top: 4px;
    }

    .vendor-alert-empty {
        padding: 14px;
        color: #64748b;
        font-size: 0.78rem;
        font-weight: 600;
    }

    .admin-sidebar-search-item {
        margin-top: 12px;
        padding: 0 16px 12px;
    }

    .admin-sidebar-search {
        position: relative;
    }

    .admin-sidebar-search-icon {
        position: absolute;
        left: 13px;
        top: 50%;
        transform: translateY(-50%);
        color: #64748b;
        pointer-events: none;
    }

    .admin-sidebar-search input {
        width: 100%;
        height: 40px;
        border: 1px solid rgba(100, 116, 139, 0.24);
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.88);
        color: #1e293b;
        font-size: 0.82rem;
        font-weight: 600;
        padding: 0 40px 0 38px;
        box-shadow: 0 10px 20px rgba(15, 23, 42, 0.08);
    }

    .admin-sidebar-search input:focus {
        border-color: rgba(14, 165, 233, 0.72);
        box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.16);
        outline: none;
    }

    .admin-sidebar-search-clear {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        width: 26px;
        height: 26px;
        border: 0;
        border-radius: 6px;
        background: rgba(15, 23, 42, 0.08);
        color: #334155;
        display: none;
        align-items: center;
        justify-content: center;
    }

    .admin-sidebar-search-clear.is-visible {
        display: flex;
    }

    .admin-sidebar-search-meta {
        min-height: 16px;
        margin-top: 6px;
        color: #64748b;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .admin-sidebar-empty {
        margin: 0 16px 12px;
        padding: 10px 12px;
        border: 1px dashed rgba(100, 116, 139, 0.35);
        border-radius: 8px;
        color: #64748b;
        font-size: 0.78rem;
        font-weight: 600;
        background: rgba(255, 255, 255, 0.62);
    }

    .attp-admin-sidebar,
    .attp-admin-sidebar .navbar-wrapper,
    .attp-admin-sidebar .navbar-content {
        background: linear-gradient(180deg, #064f6d 0%, #087493 48%, #075a78 100%) !important;
    }

    .attp-admin-sidebar .nxl-navbar .nxl-caption label,
    .attp-admin-sidebar .nxl-navbar .nxl-link,
    .attp-admin-sidebar .nxl-navbar .nxl-link .nxl-micon,
    .attp-admin-sidebar .nxl-navbar .nxl-link .nxl-mtext,
    .attp-admin-sidebar .nxl-navbar .nxl-link .nxl-arrow,
    .attp-admin-sidebar .nxl-navbar .nxl-link i {
        color: #ffffff !important;
    }

    .attp-admin-sidebar .nxl-navbar .nxl-caption label {
        opacity: 0.82;
    }

    .attp-admin-sidebar .nxl-navbar .nxl-link:hover,
    .attp-admin-sidebar .nxl-navbar .nxl-link:focus,
    .attp-admin-sidebar .nxl-navbar .nxl-item.active>.nxl-link,
    .attp-admin-sidebar .nxl-navbar .nxl-hasmenu.sidebar-search-open>.nxl-link,
    .attp-admin-sidebar .nxl-navbar .sidebar-search-match>.nxl-link {
        background: rgba(255, 255, 255, 0.14) !important;
        color: #ffffff !important;
    }

    .attp-admin-sidebar .nxl-submenu {
        background: rgba(3, 47, 66, 0.26);
    }

    .attp-admin-sidebar .admin-sidebar-search-meta,
    .attp-admin-sidebar .admin-sidebar-empty {
        color: rgba(255, 255, 255, 0.82);
    }

    .attp-admin-sidebar .admin-sidebar-empty {
        border-color: rgba(255, 255, 255, 0.35);
        background: rgba(255, 255, 255, 0.1);
    }

    .attp-admin-sidebar .sidebar-footer-card {
        background: #522B39 !important;
        border: 1px solid rgba(255, 255, 255, 0.18);
        color: #ffffff;
        box-shadow: 0 18px 34px rgba(82, 43, 57, 0.28);
    }

    .attp-admin-sidebar .sidebar-footer-card,
    .attp-admin-sidebar .sidebar-footer-card i,
    .attp-admin-sidebar .sidebar-footer-card h6,
    .attp-admin-sidebar .sidebar-footer-card p {
        color: #ffffff !important;
    }

    .attp-admin-sidebar .attp-sidebar-brand {
        overflow: hidden;
        padding: .35rem .45rem;
        border: 1px solid rgba(255, 255, 255, .22);
        border-radius: 14px;
        background: #086f91;
        box-shadow: 0 12px 24px rgba(3, 47, 66, .28);
    }

    .attp-admin-sidebar .attp-sidebar-brand-logo {
        display: block;
        width: 100%;
        height: auto;
        border: 0;
        border-radius: 9px;
        background: #086f91;
        object-fit: contain;
    }

    .nxl-navbar.sidebar-search-active .sidebar-search-hidden {
        display: none !important;
    }

    .nxl-navbar.sidebar-search-active .nxl-hasmenu.sidebar-search-open>.nxl-submenu {
        display: block !important;
        height: auto !important;
        max-height: none !important;
        opacity: 1 !important;
        visibility: visible !important;
    }

    .nxl-navbar.sidebar-search-active .nxl-hasmenu.sidebar-search-open>.nxl-link,
    .nxl-navbar.sidebar-search-active .sidebar-search-match>.nxl-link {
        background: rgba(14, 165, 233, 0.12);
        color: #0f172a;
    }

    .nxl-navbar.sidebar-search-active .nxl-hasmenu.sidebar-search-open>.nxl-link .nxl-micon,
    .nxl-navbar.sidebar-search-active .sidebar-search-match>.nxl-link .nxl-micon {
        color: #0284c7;
    }
</style>

<nav class="nxl-navigation {{ $isMemberStateSidebarUser ? 'member-state-sidebar' : 'attp-admin-sidebar' }}">
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
                    <div class="attp-sidebar-brand">
                        <img src="{{ asset('assets/images/attp-logo.jpeg') }}"
                            alt="Africa Think Tank Platform"
                            class="attp-sidebar-brand-logo">
                    </div>
                @endif
                <div class="mt-2">
                    <div class="d-flex align-items-center px-3 py-2 rounded-3 shadow-sm"
                        style="background: linear-gradient(120deg, #0a7899 0%, #07526f 100%); color:#f8fafc;">
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

                @if (!empty($vendorSidebarAlerts))
                    <li class="nxl-item vendor-sidebar-alerts" data-sidebar-search-fixed="true">
                        <div class="vendor-alert-strip" data-vendor-alert-strip>
                            @foreach ($vendorSidebarAlerts as $alertBucket)
                                <div class="vendor-alert-tile">
                                    <button type="button" class="vendor-alert-button" title="{{ $alertBucket['label'] }}"
                                        aria-label="{{ $alertBucket['label'] }}">
                                        <i class="{{ $alertBucket['icon'] }}"></i>
                                        <span class="vendor-alert-badge {{ (int) $alertBucket['unread_count'] === 0 ? 'is-zero' : '' }}"
                                            data-vendor-alert-badge="{{ $alertBucket['type'] }}">
                                            {{ number_format((int) $alertBucket['unread_count']) }}
                                        </span>
                                    </button>
                                    <div class="vendor-alert-popover">
                                        <div class="vendor-alert-popover-header">
                                            {{ $alertBucket['label'] }}
                                        </div>
                                        @forelse ($alertBucket['items'] as $alertItem)
                                            <a href="{{ $alertItem['url'] }}"
                                                class="vendor-alert-item {{ $alertItem['is_read'] ? '' : 'is-unread' }}"
                                                data-vendor-alert-item
                                                data-vendor-alert-type="{{ $alertItem['type'] }}"
                                                data-vendor-alert-read="{{ $alertItem['is_read'] ? '1' : '0' }}"
                                                data-vendor-alert-mark-url="{{ $alertItem['mark_url'] }}">
                                                <div class="vendor-alert-item-title">{{ $alertItem['vendor'] }}</div>
                                                <div class="vendor-alert-item-meta">
                                                    {{ $alertItem['title'] }}
                                                    @if ($alertItem['amount'])
                                                        <span class="fw-semibold"> | {{ $alertItem['amount'] }}</span>
                                                    @endif
                                                </div>
                                                <div class="vendor-alert-item-meta">{{ $alertItem['date'] }}</div>
                                            </a>
                                        @empty
                                            <div class="vendor-alert-empty">No records yet.</div>
                                        @endforelse
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </li>
                @endif

                <li class="nxl-item admin-sidebar-search-item" data-sidebar-search-fixed="true">
                    <div class="admin-sidebar-search">
                        <i class="feather-search admin-sidebar-search-icon"></i>
                        <input type="search" id="admin-sidebar-menu-search" autocomplete="off" placeholder="Search menus"
                            aria-label="Search sidebar menus">
                        <button type="button" class="admin-sidebar-search-clear" id="admin-sidebar-menu-clear"
                            aria-label="Clear menu search">
                            <i class="feather-x"></i>
                        </button>
                    </div>
                    <div class="admin-sidebar-search-meta" id="admin-sidebar-menu-count"></div>
                </li>
                <li class="nxl-item admin-sidebar-empty d-none" id="admin-sidebar-menu-empty"
                    data-sidebar-search-fixed="true">
                    No matching menus found
                </li>

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

                @if ($isAdminSidebarUser)
                    <li class="nxl-item nxl-caption">
                        <label>Website Visit Analysis</label>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-activity"></i></span>
                            <span class="nxl-mtext">Website Visit Analysis</span>
                            <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item">
                                <a href="{{ route('website-visit-analysis.index') }}" class="nxl-link">
                                    <i class="feather-map-pin me-2"></i> Visit Analysis
                                </a>
                            </li>
                            <li class="nxl-item">
                                <a href="{{ route('website-visit-analysis.activity') }}" class="nxl-link">
                                    <i class="feather-list me-2"></i> Activity Performed
                                </a>
                            </li>
                        </ul>
                    </li>
                @endif

                @if ($sidebarUser)
                    <li class="nxl-item nxl-caption">
                        <label>Grievance Redress Mechanism</label>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-alert-octagon"></i></span>
                            <span class="nxl-mtext">Grievance Redress Mechanism</span>
                            <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item">
                                <a href="{{ route('grm.submissions.create') }}" class="nxl-link">
                                    <i class="feather-edit-3 me-2"></i> Log a Grievance
                                </a>
                            </li>
                            @can('grm.configure')
                                <li class="nxl-item">
                                    <a href="{{ route('grm.levels.index') }}" class="nxl-link">
                                        <i class="feather-sliders me-2"></i> Grievance Configuration
                                    </a>
                                </li>
                            @endcan
                            @can('grm.escalations')
                                <li class="nxl-item">
                                    <a href="{{ route('grm.escalations.index') }}" class="nxl-link">
                                        <i class="feather-clock me-2"></i> Escalation Configuration
                                    </a>
                                </li>
                            @endcan
                            @can('grm.view')
                                <li class="nxl-item">
                                    <a href="{{ route('grm.logs.index') }}" class="nxl-link">
                                        <i class="feather-clipboard me-2"></i> Grievance Logs
                                    </a>
                                </li>
                            @endcan
                            @can('grm.reports')
                                <li class="nxl-item">
                                    <a href="{{ route('grm.reports.index') }}" class="nxl-link">
                                        <i class="feather-bar-chart-2 me-2"></i> Metrics and Reports
                                    </a>
                                </li>
                            @endcan
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

                {{-- ================= DISCUSSION CONTROLS ================= --}}
                @if ($canSeeDiscussionSidebar)
                    <li class="nxl-item nxl-caption">
                        <label>Discussion Controls</label>
                    </li>

                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-message-square"></i></span>
                            <span class="nxl-mtext">Discussion Controls</span>
                            <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>

                        <ul class="nxl-submenu">
                            @canany(['discussions.view', 'discussions.create', 'discussions.manage', 'discussions.thematic_areas.manage', 'discussions.participants.manage', 'discussions.moderate'])
                                <li class="nxl-item">
                                    <a href="{{ route('system.discussions.dashboard') }}" class="nxl-link">
                                        <i class="feather-grid me-2"></i> Discussion Dashboard
                                    </a>
                                </li>
                            @endcanany
                            @can('discussions.create')
                                <li class="nxl-item">
                                    <a href="{{ route('system.discussions.topics.create') }}" class="nxl-link">
                                        <i class="feather-plus-circle me-2"></i> Create Discussion
                                    </a>
                                </li>
                            @endcan
                            @canany(['discussions.view', 'discussions.manage'])
                                <li class="nxl-item">
                                    <a href="{{ route('system.discussions.topics.index') }}" class="nxl-link">
                                        <i class="feather-list me-2"></i> Manage Discussions
                                    </a>
                                </li>
                            @endcanany
                            @can('discussions.thematic_areas.manage')
                                <li class="nxl-item">
                                    <a href="{{ route('system.discussions.themes.index') }}" class="nxl-link">
                                        <i class="feather-layers me-2"></i> Thematic Areas
                                    </a>
                                </li>
                            @endcan
                            @can('discussions.participants.manage')
                                <li class="nxl-item">
                                    <a href="{{ route('system.discussions.participants.index') }}" class="nxl-link">
                                        <i class="feather-user-x me-2"></i> Block Participating Users
                                    </a>
                                </li>
                            @endcan
                            @can('discussions.moderate')
                                <li class="nxl-item">
                                    <a href="{{ route('system.discussions.moderation.live') }}" class="nxl-link">
                                        <i class="feather-radio me-2"></i> Live Discussion Monitor
                                    </a>
                                </li>
                                <li class="nxl-item">
                                    <a href="{{ route('system.discussions.moderation.index') }}" class="nxl-link">
                                        <i class="feather-shield me-2"></i> Moderation History
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endif


                {{-- ================= THINK TANK MODULE ================= --}}
                @if ($canSeeThinkTankModule)
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
                            @if ($canSeeThinkTankFinance && Route::has('think-tanks-admin.consortium-analysis'))
                                <li class="nxl-item">
                                    <a href="{{ route('think-tanks-admin.consortium-analysis') }}" class="nxl-link">
                                        <i class="feather-grid me-2"></i> Consortium Analysis
                                    </a>
                                </li>
                            @endif

                            @if ($canSeeThinkTankFinance && Route::has('think-tanks-admin.think-tank-analysis'))
                                <li class="nxl-item">
                                    <a href="{{ route('think-tanks-admin.think-tank-analysis') }}" class="nxl-link">
                                        <i class="feather-users me-2"></i> Think Tank Analysis
                                    </a>
                                </li>
                            @endif

                            @if ($canSeeThinkTankFinance && Route::has('think-tanks-admin.consortium-reports'))
                                <li class="nxl-item">
                                    <a href="{{ route('think-tanks-admin.consortium-reports') }}" class="nxl-link">
                                        <i class="feather-file-text me-2"></i> Consortium Reports
                                    </a>
                                </li>
                            @endif

                            @if ($canSeeThinkTankFinance && Route::has('think-tanks-admin.think-tank-reports'))
                                <li class="nxl-item">
                                    <a href="{{ route('think-tanks-admin.think-tank-reports') }}" class="nxl-link">
                                        <i class="feather-layers me-2"></i> Think Tank Reports
                                    </a>
                                </li>
                            @endif

                            @can('users.manage')
                                @if (Route::has('system.think-tank-users.index'))
                                    <li class="nxl-item">
                                        <a href="{{ route('system.think-tank-users.index') }}" class="nxl-link">
                                            <i class="feather-user-check me-2"></i> Think Tank Users
                                        </a>
                                    </li>
                                @endif
                            @endcan

                            @canany(['think_tank.procurement.review', 'procurement.view_all', 'procurement.manage_all'])
                                @if (Route::has('think-tank-procurement.index'))
                                    <li class="nxl-item">
                                        <a href="{{ route('think-tank-procurement.index') }}" class="nxl-link">
                                            <i class="feather-clipboard me-2"></i> Procurement Submissions
                                        </a>
                                    </li>
                                @endif
                            @endcanany

                            @canany(['think_tank.procurement.reports', 'think_tank.procurement.step', 'procurement.view_all', 'procurement.manage_all'])
                                @if (Route::has('think-tank-procurement.reports'))
                                    <li class="nxl-item">
                                        <a href="{{ route('think-tank-procurement.reports') }}" class="nxl-link">
                                            <i class="feather-bar-chart-2 me-2"></i> Procurement Reports
                                        </a>
                                    </li>
                                @endif
                            @endcanany

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
                                    <a href="{{ route('budget.portfolios.index') }}" class="nxl-link">
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
                                    <a href="{{ route('budget.reports.commitment-disbursement') }}" class="nxl-link">
                                        <i class="feather-repeat me-2"></i>
                                        {{ __('admin.commitment_disbursement_report') }}
                                    </a>
                                </li>
                                <li class="nxl-item">
                                    <a href="{{ route('budget.reports.ifr') }}" class="nxl-link">
                                        <i class="feather-activity me-2"></i>
                                        {{ __('admin.ifr_report') }}
                                    </a>
                                </li>
                            @endcanany

                            {{-- Project Financial Position (includes execution analytics) --}}
                            @canany(['budget.reports.view', 'budget.project_financial_position.view', 'finance.executions.view'])
                                <li class="nxl-item">
                                    <a href="{{ route('budget.reports.project-financial-position') }}" class="nxl-link">
                                        <i class="feather-briefcase me-2"></i>
                                        {{ __('admin.project_financial_position') }}
                                    </a>
                                </li>
                            @endcanany

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
                @canany(['me.configuration.view', 'me.configuration.manage', 'me.data_entry.view', 'me.data_entry.manage', 'me.reporting_notifications.view', 'me.results.view',
                    'me.performance_reports.view', 'me.performance_reports.review', 'me.performance_reports.archive', 'me.dqa.manage', 'me.submissions.review', 'world.indicators.manage',
                    'biannual_site_visits.view', 'biannual_site_visits.create',
                    'biannual_site_visits.respond', 'biannual_site_visits.submit',
                    'biannual_site_visits.approve', 'biannual_site_visits.export'])
                    <li class="nxl-item nxl-caption">
                        <label>{{ __('Monitoring & Evaluation') }}</label>
                    </li>

                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-target"></i></span>
                            <span class="nxl-mtext">Monitoring & Evaluation</span>
                            <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>

                        <ul class="nxl-submenu">
                            @canany(['me.configuration.view', 'me.configuration.manage', 'world.indicators.manage'])
                                <li class="nxl-item">
                                    <a href="{{ route('budget.me.framework.index') }}" class="nxl-link">
                                        <i class="feather-layers me-2"></i> Framework, IRS &amp; Targets
                                    </a>
                                </li>
                                <li class="nxl-item">
                                    <a href="{{ route('budget.me.indicators.index') }}" class="nxl-link">
                                        <i class="feather-target me-2"></i> Results Framework and Indicator Management
                                    </a>
                                </li>
                            @endcanany
                            @canany(['me.data_entry.view', 'me.data_entry.manage', 'me.configuration.view', 'me.configuration.manage'])
                                <li class="nxl-item">
                                    <a href="{{ route('budget.me.rebuild.data-entry') }}" class="nxl-link">
                                        <i class="feather-edit-3 me-2"></i> Data Entry and Performance Tracking
                                    </a>
                                </li>
                                <li class="nxl-item">
                                    <a href="{{ route('budget.me.submission-reviews.index') }}" class="nxl-link">
                                        <i class="feather-check-square me-2"></i> Think Tank Submission Review
                                    </a>
                                </li>
                            @endcanany
                            @canany(['biannual_site_visits.view', 'biannual_site_visits.create',
                                'biannual_site_visits.respond', 'biannual_site_visits.submit',
                                'biannual_site_visits.approve', 'biannual_site_visits.export'])
                                <li class="nxl-item">
                                    <a href="{{ route('biannual-site-visits.index') }}"
                                        class="nxl-link {{ request()->routeIs('biannual-site-visits.*') && !request()->routeIs('biannual-site-visits.templates.*') && !request()->routeIs('biannual-site-visits.reports.*') ? 'active' : '' }}">
                                        <i class="feather-map-pin me-2"></i> Bi-Annual Site Visits
                                    </a>
                                </li>
                            @endcanany
                            @can('me.reporting_notifications.view')
                                <li class="nxl-item">
                                    <a href="{{ route('budget.me.reporting-notifications.index') }}" class="nxl-link">
                                        <i class="feather-bell me-2"></i> Reporting Notifications
                                        @php($meUnreadNotifications = auth()->user()?->unreadNotifications()->where('type', \App\Notifications\MeReportingNotification::class)->count() ?? 0)
                                        @if($meUnreadNotifications)<span class="badge bg-danger ms-1">{{ $meUnreadNotifications }}</span>@endif
                                    </a>
                                </li>
                            @endcan
                            @canany(['me.configuration.view', 'me.configuration.manage', 'me.dqa.manage', 'me.submissions.review', 'me.data_entry.view', 'me.data_entry.manage'])
                                <li class="nxl-item">
                                    <a href="{{ route('budget.me.rebuild.data-quality') }}" class="nxl-link">
                                        <i class="feather-check-circle me-2"></i> Data Quality and Approval Workflow
                                    </a>
                                </li>
                            @endcanany
                            @canany(['me.results.view', 'me.performance_reports.view', 'me.configuration.view', 'me.configuration.manage'])
                                <li class="nxl-item">
                                    <a href="{{ route('budget.me.results-dashboard.index') }}" class="nxl-link">
                                        <i class="feather-trending-up me-2"></i> Official Results Framework Dashboard
                                    </a>
                                </li>
                            @endcanany
                            @canany(['me.performance_reports.view', 'me.performance_reports.review', 'me.performance_reports.archive', 'me.data_entry.view', 'me.data_entry.manage', 'me.configuration.view', 'me.configuration.manage'])
                                <li class="nxl-item">
                                    <a href="{{ route('budget.me.rebuild.reporting-dashboard') }}" class="nxl-link">
                                        <i class="feather-bar-chart-2 me-2"></i> Reporting and Dashboard
                                    </a>
                                </li>
                            @endcanany
                            @canany(['me.performance_reports.view', 'me.performance_reports.review', 'me.configuration.view', 'me.configuration.manage'])
                                <li class="nxl-item">
                                    <a href="{{ route('budget.me.consolidated-reports.index') }}" class="nxl-link">
                                        <i class="feather-pie-chart me-2"></i> Think Tank &amp; Consolidated Reports
                                    </a>
                                </li>
                            @endcanany
                            @canany(['me.results.view', 'me.performance_reports.view', 'me.performance_reports.review', 'me.performance_reports.archive', 'me.data_entry.view', 'me.data_entry.manage', 'me.dqa.manage', 'me.submissions.review', 'me.configuration.view', 'me.configuration.manage'])
                                <li class="nxl-item">
                                    <a href="{{ route('budget.me.rebuild.management-dashboard') }}" class="nxl-link">
                                        <i class="feather-monitor me-2"></i> Management Dashboard
                                    </a>
                                </li>
                            @endcanany
                            @canany(['me.configuration.view', 'me.configuration.manage', 'world.indicators.manage', 'me.performance_reports.view', 'me.performance_reports.review', 'me.data_entry.view', 'me.data_entry.manage'])
                                <li class="nxl-item">
                                    <a href="{{ route('budget.me.rebuild.knowledge-repository') }}" class="nxl-link">
                                        <i class="feather-folder me-2"></i> Knowledge and Evidence Repository (MEAL plans, TOCs and pertinent documents)
                                    </a>
                                </li>
                            @endcanany
                            @canany(['me.configuration.view', 'me.configuration.manage', 'world.indicators.manage'])
                                <li class="nxl-item">
                                    <a href="{{ route('budget.me.matrices.index') }}" class="nxl-link">
                                        <i class="feather-grid me-2"></i> M&amp;E Matrix Manager
                                    </a>
                                </li>
                                <li class="nxl-item">
                                    <a href="{{ route('budget.me.focal-units.index') }}" class="nxl-link">
                                        <i class="feather-user-check me-2"></i> M&amp;E Focal Unit Register
                                    </a>
                                </li>
                                <li class="nxl-item">
                                    <a href="{{ route('budget.me.rebuild.data-governance') }}" class="nxl-link">
                                        <i class="feather-shield me-2"></i> Data Governance Framework
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
                @canany(['site_visits.view', 'site_visits.create', 'site_visits.approve',
                    'biannual_site_visits.view', 'biannual_site_visits.approve', 'biannual_site_visits.export',
                    'biannual_site_visits.templates.manage'])
                    <li class="nxl-item nxl-caption">
                        <label>{{ __('admin.site_visits') }}</label>
                    </li>

                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-map-pin"></i></span>
                            <span class="nxl-mtext">Site Visits</span>
                            <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            @canany(['site_visits.view', 'site_visits.create', 'site_visits.approve'])
                                <li class="nxl-item">
                                    <a href="{{ route('site-visits.index') }}"
                                        class="nxl-link {{ request()->routeIs('site-visits.*') ? 'active' : '' }}">
                                        <i class="feather-briefcase me-2"></i>
                                        Procurement Site Visits
                                    </a>
                                </li>
                            @endcanany

                            @canany(['biannual_site_visits.view', 'biannual_site_visits.approve',
                                'biannual_site_visits.export'])
                                <li class="nxl-item">
                                    <a href="{{ route('biannual-site-visits.reports.submitted') }}"
                                        class="nxl-link {{ request()->routeIs('biannual-site-visits.reports.*') ? 'active' : '' }}">
                                        <i class="feather-file-text me-2"></i>
                                        Submitted Visit Reports
                                    </a>
                                </li>
                            @endcanany

                            @can('biannual_site_visits.templates.manage')
                                <li class="nxl-item">
                                    <a href="{{ route('biannual-site-visits.templates.index') }}"
                                        class="nxl-link {{ request()->routeIs('biannual-site-visits.templates.*') ? 'active' : '' }}">
                                        <i class="feather-sliders me-2"></i>
                                        Questionnaire Builder
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
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
            <div class="card text-center mt-4 sidebar-footer-card">
                <div class="card-body">
                    <i class="feather-clipboard fs-4 text-dark"></i>
                    <h6 class="mt-4 text-dark fw-bolder">System Workspace</h6>
                    <p class="fs-11 my-3 text-dark">
                        Coordinate procurement, finance, think tank operations, reporting, and approvals across the platform.
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

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        document.querySelectorAll('.vendor-alert-tile').forEach((tile) => {
            const button = tile.querySelector('.vendor-alert-button');
            const popover = tile.querySelector('.vendor-alert-popover');
            let hideTimer = null;

            if (!button || !popover) {
                return;
            }

            const positionPopover = () => {
                const rect = button.getBoundingClientRect();
                const gap = 12;
                popover.classList.add('is-visible');
                popover.style.setProperty('--vendor-alert-left', `${rect.right + gap}px`);

                const top = Math.min(rect.top, window.innerHeight - popover.offsetHeight - 16);
                popover.style.setProperty('--vendor-alert-top', `${Math.max(16, top)}px`);
            };

            const showPopover = () => {
                window.clearTimeout(hideTimer);
                positionPopover();
            };

            const hidePopover = () => {
                hideTimer = window.setTimeout(() => {
                    popover.classList.remove('is-visible');
                }, 120);
            };

            button.addEventListener('mouseenter', showPopover);
            button.addEventListener('focus', showPopover);
            button.addEventListener('mouseleave', hidePopover);
            button.addEventListener('blur', hidePopover);
            popover.addEventListener('mouseenter', showPopover);
            popover.addEventListener('mouseleave', hidePopover);
            window.addEventListener('resize', () => {
                if (popover.classList.contains('is-visible')) {
                    positionPopover();
                }
            });
        });

        document.querySelectorAll('[data-vendor-alert-item]').forEach((alertLink) => {
            alertLink.addEventListener('click', (event) => {
                if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                    return;
                }

                const markUrl = alertLink.dataset.vendorAlertMarkUrl;
                if (!markUrl || markUrl === '#') {
                    return;
                }

                event.preventDefault();
                const destination = alertLink.href;
                const alertType = alertLink.dataset.vendorAlertType;
                const wasUnread = alertLink.dataset.vendorAlertRead !== '1';

                fetch(markUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                }).finally(() => {
                    if (wasUnread) {
                        alertLink.dataset.vendorAlertRead = '1';
                        alertLink.classList.remove('is-unread');
                        const badge = Array.from(document.querySelectorAll('[data-vendor-alert-badge]'))
                            .find((candidate) => candidate.dataset.vendorAlertBadge === alertType);

                        if (badge) {
                            const current = Number((badge.textContent || '0').replace(/[^\d]/g, '')) || 0;
                            const next = Math.max(current - 1, 0);
                            badge.textContent = String(next);
                            badge.classList.toggle('is-zero', next === 0);
                        }
                    }

                    window.location.href = destination;
                });
            });
        });

        const navList = document.querySelector('.nxl-navbar');
        const searchInput = document.getElementById('admin-sidebar-menu-search');
        const clearButton = document.getElementById('admin-sidebar-menu-clear');
        const emptyState = document.getElementById('admin-sidebar-menu-empty');
        const countEl = document.getElementById('admin-sidebar-menu-count');

        if (!navList || !searchInput) {
            return;
        }

        const searchItem = searchInput.closest('[data-sidebar-search-fixed]');
        const originalTopLevelNodes = Array.from(navList.children);
        const topOrder = new Map(originalTopLevelNodes.map((node, index) => [node, index]));
        const managedTopItems = originalTopLevelNodes.filter((node) => (
            node.classList.contains('nxl-item') && !node.dataset.sidebarSearchFixed
        ));
        let activeMatches = [];
        let pendingSearchFrame = null;

        const normalizeSearchText = (value) => (value || '')
            .toString()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase();

        const visibleText = (node) => (node?.textContent || '').replace(/\s+/g, ' ').trim();

        const directTopItemFor = (node) => {
            let current = node;
            while (current && current.parentElement !== navList) {
                current = current.parentElement;
            }
            return current;
        };

        const directLinkFor = (node) => Array.from(node.children || [])
            .find((child) => child.matches?.('a.nxl-link'));

        const sectionLabelFor = (topItem) => {
            let cursor = topItem.previousElementSibling;
            while (cursor) {
                if (cursor.classList.contains('nxl-caption')) {
                    return visibleText(cursor);
                }
                cursor = cursor.previousElementSibling;
            }
            return '';
        };

        const menuRecords = Array.from(navList.querySelectorAll('a.nxl-link[href]'))
            .filter((link) => {
                const href = (link.getAttribute('href') || '').trim().toLowerCase();
                return href && href !== '#' && !href.startsWith('javascript') && !link.closest('[data-sidebar-search-fixed]');
            })
            .map((link) => {
                const topItem = directTopItemFor(link);
                const item = link.closest('.nxl-item');
                const topLink = topItem ? directLinkFor(topItem) : null;
                const parentLabel = topLink && topLink !== link ? visibleText(topLink) : '';
                const ownLabel = visibleText(link);
                const sectionLabel = topItem ? sectionLabelFor(topItem) : '';
                const searchable = normalizeSearchText([sectionLabel, parentLabel, ownLabel].filter(Boolean).join(' '));

                return {
                    link,
                    item,
                    topItem,
                    ownLabel,
                    text: searchable,
                    order: topOrder.get(topItem) ?? 9999,
                };
            })
            .filter((record) => record.topItem && record.item && record.text);

        const scoreRecord = (record, query, tokens) => {
            if (!tokens.every((token) => record.text.includes(token))) {
                return null;
            }

            const ownText = normalizeSearchText(record.ownLabel);
            let score = record.order;

            if (ownText === query) {
                score -= 1200;
            } else if (ownText.startsWith(query)) {
                score -= 900;
            } else if (record.text.startsWith(query)) {
                score -= 600;
            }

            score += Math.max(record.text.indexOf(tokens[0]), 0);
            return score;
        };

        const resetSidebarSearch = () => {
            navList.classList.remove('sidebar-search-active');
            originalTopLevelNodes.forEach((node) => navList.appendChild(node));
            navList.querySelectorAll('.sidebar-search-hidden, .sidebar-search-match, .sidebar-search-open').forEach((node) => {
                node.classList.remove('sidebar-search-hidden', 'sidebar-search-match', 'sidebar-search-open');
            });
            emptyState?.classList.add('d-none');
            clearButton?.classList.remove('is-visible');
            if (countEl) {
                countEl.textContent = '';
            }
            activeMatches = [];
        };

        const markVisibleChildPath = (topItem, item, visibleItems) => {
            let current = item;
            while (current && current !== topItem) {
                if (current.classList.contains('nxl-item')) {
                    visibleItems.add(current);
                }
                current = current.parentElement?.closest('.nxl-item');
            }
        };

        const applySidebarSearch = () => {
            const query = normalizeSearchText(searchInput.value);
            const tokens = query.split(' ').filter(Boolean);

            if (!query || tokens.length === 0) {
                resetSidebarSearch();
                return;
            }

            navList.classList.add('sidebar-search-active');
            clearButton?.classList.add('is-visible');

            navList.querySelectorAll('.sidebar-search-hidden, .sidebar-search-match, .sidebar-search-open').forEach((node) => {
                node.classList.remove('sidebar-search-hidden', 'sidebar-search-match', 'sidebar-search-open');
            });
            managedTopItems.forEach((node) => node.classList.add('sidebar-search-hidden'));

            const scoredMatches = menuRecords
                .map((record) => ({ record, score: scoreRecord(record, query, tokens) }))
                .filter((entry) => entry.score !== null)
                .sort((a, b) => a.score - b.score || a.record.order - b.record.order);

            activeMatches = scoredMatches.map((entry) => entry.record);

            const topMatches = new Map();
            scoredMatches.forEach(({ record, score }) => {
                const existing = topMatches.get(record.topItem);
                if (!existing) {
                    topMatches.set(record.topItem, {
                        topItem: record.topItem,
                        score,
                        order: record.order,
                        records: [record],
                    });
                    return;
                }

                existing.score = Math.min(existing.score, score);
                existing.records.push(record);
            });

            const orderedTopMatches = Array.from(topMatches.values())
                .sort((a, b) => a.score - b.score || a.order - b.order);
            const insertionPoint = emptyState || searchItem?.nextSibling || null;

            orderedTopMatches.forEach(({ topItem, records }) => {
                const visibleChildItems = new Set();
                const matchedItems = new Set(records.map((record) => record.item));

                records.forEach((record) => markVisibleChildPath(topItem, record.item, visibleChildItems));

                topItem.classList.remove('sidebar-search-hidden');
                topItem.classList.add('sidebar-search-match');
                if (topItem.classList.contains('nxl-hasmenu')) {
                    topItem.classList.add('sidebar-search-open');
                }

                Array.from(topItem.querySelectorAll('.nxl-submenu .nxl-item')).forEach((childItem) => {
                    const isVisible = visibleChildItems.has(childItem) || matchedItems.has(childItem);
                    childItem.classList.toggle('sidebar-search-hidden', !isVisible);
                    childItem.classList.toggle('sidebar-search-match', matchedItems.has(childItem));
                    if (isVisible && childItem.classList.contains('nxl-hasmenu')) {
                        childItem.classList.add('sidebar-search-open');
                    }
                });

                navList.insertBefore(topItem, insertionPoint);
            });

            if (emptyState) {
                emptyState.classList.toggle('d-none', scoredMatches.length > 0);
            }
            if (countEl) {
                countEl.textContent = scoredMatches.length === 1
                    ? '1 menu match'
                    : `${scoredMatches.length} menu matches`;
            }
        };

        const scheduleSidebarSearch = () => {
            if (pendingSearchFrame) {
                cancelAnimationFrame(pendingSearchFrame);
            }
            pendingSearchFrame = requestAnimationFrame(applySidebarSearch);
        };

        searchInput.addEventListener('input', scheduleSidebarSearch);
        searchInput.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                searchInput.value = '';
                resetSidebarSearch();
            }
            if (event.key === 'Enter' && activeMatches[0]) {
                event.preventDefault();
                activeMatches[0].link.click();
            }
        });

        clearButton?.addEventListener('click', () => {
            searchInput.value = '';
            resetSidebarSearch();
            searchInput.focus();
        });
    });
</script>
