<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" data-theme="light">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'ATTP Partner Portal')</title>

    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('admin/assets/images/favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/vendors.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/dataTables.bs5.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/select2-theme.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/css/theme.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/css/datatable-custom.css') }}">

    @stack('styles')

    <style>
        body {
            background: #f5f7fb;
        }

        .partner-shell {
            min-height: 100vh;
            display: flex;
        }

        .partner-sidebar {
            width: 280px;
            background: linear-gradient(180deg, #0f172a 0%, #0f766e 100%);
            color: #f8fafc;
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 1030;
            padding: 20px 16px;
            overflow-y: auto;
        }

        .partner-brand {
            border-radius: 14px;
            background: rgba(255, 255, 255, .1);
            padding: 16px;
            margin-bottom: 18px;
        }

        .partner-brand img {
            width: 42px;
            height: 42px;
            object-fit: contain;
            background: #fff;
            border-radius: 10px;
            padding: 5px;
        }

        .partner-nav-label {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: rgba(248, 250, 252, .65);
            margin: 18px 10px 8px;
        }

        .partner-nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            color: rgba(248, 250, 252, .88);
            text-decoration: none;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .partner-nav-link:hover,
        .partner-nav-link.active {
            color: #0f172a;
            background: #f8fafc;
        }

        .partner-main {
            flex: 1;
            margin-left: 280px;
            min-width: 0;
        }

        .partner-topbar {
            min-height: 72px;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 14px 26px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 1020;
        }

        .partner-content {
            padding: 28px;
        }

        .partner-footer {
            border-top: 1px solid #e2e8f0;
            background: #fff;
            padding: 18px 28px;
            color: #64748b;
        }

        .content .nxl-container,
        .partner-content .nxl-container {
            max-width: 100%;
            margin: 0;
            padding: 0;
            position: static !important;
            top: auto !important;
        }

        @media (max-width: 991.98px) {
            .partner-sidebar {
                position: static;
                width: 100%;
                min-height: auto;
            }

            .partner-shell {
                display: block;
            }

            .partner-main {
                margin-left: 0;
            }

            .partner-content {
                padding: 18px;
            }
        }
    </style>
</head>

<body>
    @php
        $partnerUser = auth()->user();
        $partnerName = $partnerUser?->name ?? 'Partner';
        $partnerEmail = $partnerUser?->email ?? '';
    @endphp

    <div class="partner-shell">
        <aside class="partner-sidebar">
            <div class="partner-brand">
                <div class="d-flex align-items-center gap-3">
                    <img src="{{ asset('assets/images/au.png') }}" alt="ATTP">
                    <div>
                        <div class="fw-bold">ATTP Partner Portal</div>
                        <div class="small text-white-50">{{ $partnerName }}</div>
                    </div>
                </div>
            </div>

            <div class="partner-nav-label">Workspace</div>
            <a href="{{ route('partner.dashboard') }}" class="partner-nav-link {{ request()->routeIs('partner.dashboard') ? 'active' : '' }}">
                <i class="feather-home"></i> Dashboard
            </a>
            <a href="{{ route('partner.programs.index') }}" class="partner-nav-link {{ request()->routeIs('partner.programs.*', 'partner.projects.*', 'partner.activities.*') ? 'active' : '' }}">
                <i class="feather-folder"></i> Funded Programs
            </a>
            <a href="{{ route('partner.insights') }}" class="partner-nav-link {{ request()->routeIs('partner.insights') ? 'active' : '' }}">
                <i class="feather-bar-chart-2"></i> Insights
            </a>
            <a href="{{ route('partner.reports.index') }}" class="partner-nav-link {{ request()->routeIs('partner.reports.*') ? 'active' : '' }}">
                <i class="feather-pie-chart"></i> Reports
            </a>
            <a href="{{ route('partner.think-tanks.deep-search') }}" class="partner-nav-link {{ request()->routeIs('partner.think-tanks.*') ? 'active' : '' }}">
                <i class="feather-search"></i> Think Tank Deep Search
            </a>
            <a href="{{ route('partner.workplan.index') }}" class="partner-nav-link {{ request()->routeIs('partner.workplan.*') ? 'active' : '' }}">
                <i class="feather-check-square"></i> Work Plan
            </a>
            <a href="{{ route('partner.requests.index') }}" class="partner-nav-link {{ request()->routeIs('partner.requests.*') ? 'active' : '' }}">
                <i class="feather-message-square"></i> Information Requests
            </a>

            <div class="partner-nav-label">Account</div>
            <a href="{{ route('partner.profile.edit') }}" class="partner-nav-link {{ request()->routeIs('partner.profile.*') ? 'active' : '' }}">
                <i class="feather-user"></i> Profile
            </a>
            <a href="{{ route('logout') }}" class="partner-nav-link"
                onclick="event.preventDefault(); document.getElementById('partner-logout-form').submit();">
                <i class="feather-log-out"></i> Logout
            </a>
            <form id="partner-logout-form" method="POST" action="{{ route('logout') }}" class="d-none">
                @csrf
            </form>
        </aside>

        <main class="partner-main">
            <header class="partner-topbar">
                <div>
                    <div class="fw-bold text-dark">Funding Partner Workspace</div>
                    <div class="small text-muted">{{ $partnerEmail }}</div>
                </div>
                <a href="{{ route('partner.requests.create') }}" class="btn btn-primary btn-sm">
                    <i class="feather-plus-circle me-1"></i> New Request
                </a>
            </header>

            <div class="partner-content">
                @yield('content')
            </div>

            <footer class="partner-footer">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="small">ATTP Partner Portal</div>
                    <div class="small fw-semibold">Developed, maintained and supported by the ATTP Technical Team.</div>
                </div>
            </footer>
        </main>
    </div>

    <script src="{{ asset('admin/assets/vendors/js/vendors.min.js') }}"></script>
    <script src="{{ asset('admin/assets/vendors/js/dataTables.min.js') }}"></script>
    <script src="{{ asset('admin/assets/vendors/js/dataTables.bs5.min.js') }}"></script>
    <script src="{{ asset('admin/assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('admin/assets/vendors/js/select2-active.min.js') }}"></script>
    <script src="{{ asset('admin/assets/js/common-init.min.js') }}"></script>
    <script src="{{ asset('admin/assets/js/datatable-config.js') }}"></script>

    @stack('scripts')
    @stack('modals')
</body>

</html>
