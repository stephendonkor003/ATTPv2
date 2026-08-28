<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" data-theme="light">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'ATTP Vendor Portal')</title>

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
            background: #f6f8fb;
            color: #172033;
        }

        .vendor-shell {
            min-height: 100vh;
            display: flex;
        }

        .vendor-sidebar {
            width: 280px;
            background:
                linear-gradient(180deg, rgba(14, 30, 23, .98) 0%, rgba(0, 107, 63, .97) 58%, rgba(11, 95, 116, .97) 100%),
                #0f172a;
            color: #f8fafc;
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 1030;
            padding: 20px 16px;
            overflow-y: auto;
        }

        .vendor-brand {
            border-radius: 8px;
            background: rgba(255, 255, 255, .12);
            border: 1px solid rgba(255, 255, 255, .14);
            padding: 16px;
            margin-bottom: 18px;
        }

        .vendor-brand img {
            width: 42px;
            height: 42px;
            object-fit: contain;
            background: #fff;
            border-radius: 8px;
            padding: 5px;
        }

        .vendor-nav-label {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: rgba(248, 250, 252, .66);
            margin: 18px 10px 8px;
        }

        .vendor-nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 8px;
            color: rgba(248, 250, 252, .9);
            text-decoration: none;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .vendor-nav-link:hover,
        .vendor-nav-link.active {
            color: #102018;
            background: #f8fafc;
        }

        .vendor-main {
            flex: 1;
            margin-left: 280px;
            min-width: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .vendor-topbar {
            min-height: 78px;
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

        .vendor-topbar-kicker,
        .vendor-eyebrow {
            color: #64748b;
            font-size: .73rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .vendor-user-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            border-radius: 999px;
            padding: 7px 12px 7px 7px;
            min-width: 0;
        }

        .vendor-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #0b5f74;
            color: #fff;
            font-weight: 800;
        }

        .vendor-content {
            padding: 28px;
            flex: 1 0 auto;
        }

        .vendor-content .nxl-container {
            max-width: 100%;
            margin: 0;
            padding: 0;
            position: static !important;
            top: auto !important;
        }

        .vendor-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .05);
        }

        .btn-vendor {
            background: #006B3F;
            border-color: #006B3F;
            color: #fff;
            font-weight: 700;
        }

        .btn-vendor:hover,
        .btn-vendor:focus {
            background: #004d2e;
            border-color: #004d2e;
            color: #fff;
        }

        .btn-vendor-outline {
            background: #fff;
            border: 1px solid #006B3F;
            color: #006B3F;
            font-weight: 700;
        }

        .btn-vendor-outline:hover,
        .btn-vendor-outline:focus {
            background: #006B3F;
            color: #fff;
        }

        .badge-soft {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 4px 10px;
            background: #e8f5ee;
            color: #006B3F;
            font-size: .78rem;
            font-weight: 800;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 4px 10px;
            background: #eef2ff;
            color: #1d4ed8;
            font-size: .78rem;
            font-weight: 800;
        }

        .vendor-page-head {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 22px;
        }

        .vendor-page-title {
            font-size: 1.55rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .vendor-metric {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            min-height: 116px;
        }

        .vendor-metric-icon,
        .vendor-empty-icon,
        .vendor-doc-icon {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eef7f2;
            color: #006B3F;
            flex: 0 0 auto;
        }

        .vendor-empty-icon {
            margin: 0 auto;
        }

        .vendor-metric-label {
            color: #64748b;
            font-size: .78rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .vendor-metric-value {
            font-size: 1.65rem;
            font-weight: 850;
            color: #0f172a;
            line-height: 1.1;
        }

        .vendor-chart-box {
            position: relative;
            min-height: 285px;
        }

        .vendor-chart-box canvas {
            width: 100% !important;
            height: 285px !important;
        }

        .vendor-empty {
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 28px;
            text-align: center;
            background: #f8fafc;
        }

        .vendor-line-item,
        .vendor-file-row,
        .vendor-doc-card,
        .vendor-flow-step,
        .vendor-activity-item {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
        }

        .vendor-line-item {
            padding: 14px;
            margin-bottom: 12px;
        }

        .vendor-flow-step {
            padding: 14px;
            height: 100%;
        }

        .vendor-flow-step + .vendor-flow-step {
            margin-top: 10px;
        }

        .vendor-file-row,
        .vendor-activity-item {
            padding: 12px;
        }

        .vendor-file-row {
            display: flex;
            align-items: center;
            gap: 12px;
            color: inherit;
            text-decoration: none;
        }

        .vendor-file-row:hover {
            border-color: #006B3F;
            color: inherit;
        }

        .vendor-file-row small {
            display: block;
            color: #64748b;
        }

        .vendor-doc-card {
            padding: 16px;
            height: 100%;
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }

        .vendor-doc-card .btn {
            margin-left: auto;
        }

        .vendor-readable {
            white-space: pre-wrap;
            line-height: 1.7;
        }

        .vendor-footer {
            border-top: 1px solid #e2e8f0;
            background: #fff;
            padding: 18px 28px;
            color: #64748b;
            flex-shrink: 0;
        }

        @media (max-width: 991.98px) {
            .vendor-shell {
                display: block;
            }

            .vendor-sidebar {
                position: static;
                width: 100%;
                min-height: auto;
            }

            .vendor-main {
                margin-left: 0;
            }

            .vendor-topbar {
                position: static;
                flex-direction: column;
                align-items: stretch;
            }

            .vendor-page-head {
                align-items: stretch;
                flex-direction: column;
            }

            .vendor-content {
                padding: 18px;
            }

            .vendor-user-pill {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    @include('layouts.partials.impersonation-banner')
    @php
        $vendorUser = auth()->user();
        $vendorName = $vendorUser?->name ?? 'Vendor';
        $vendorEmail = $vendorUser?->email ?? '';
        $vendorInitials = collect(explode(' ', $vendorName))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
        $vendorThinkTankMember = $vendorUser
            ? ($vendorUser->vendorThinkTankMembership()->with('consortium')->first() ?: $vendorUser->thinkTankMembership()->with('consortium')->first())
            : null;
    @endphp

    <div class="vendor-shell">
        <aside class="vendor-sidebar">
            <div class="vendor-brand">
                <div class="d-flex align-items-center gap-3">
                    <img src="{{ asset('assets/images/au.png') }}" alt="ATTP">
                    <div>
                        <div class="fw-bold">ATTP Vendor Portal</div>
                        <div class="small text-white-50">{{ $vendorName }}</div>
                    </div>
                </div>
            </div>

            <div class="vendor-nav-label">Workspace</div>
            <a href="{{ route('vendor.dashboard') }}" class="vendor-nav-link {{ request()->routeIs('vendor.dashboard') ? 'active' : '' }}">
                <i class="feather-home"></i> Dashboard
            </a>
            <a href="{{ route('vendor.procurements.index') }}" class="vendor-nav-link {{ request()->routeIs('vendor.procurements.*') ? 'active' : '' }}">
                <i class="feather-briefcase"></i> Open Procurements
            </a>
            <a href="{{ route('vendor.submissions') }}" class="vendor-nav-link {{ request()->routeIs('vendor.submissions', 'vendor.applications.*') ? 'active' : '' }}">
                <i class="feather-file-text"></i> My Submissions
            </a>
            <a href="{{ route('vendor.eoi-communications.index') }}" class="vendor-nav-link {{ request()->routeIs('vendor.eoi-communications.*') ? 'active' : '' }}">
                <i class="feather-mail"></i> Evaluation Notices
            </a>
            @if ($vendorUser?->can('evaluations.evaluate'))
                <a href="{{ route('my.eval.index') }}" class="vendor-nav-link {{ request()->routeIs('my.eval.*', 'eval.assign.start', 'eval.assign.view') ? 'active' : '' }}">
                    <i class="feather-check-square"></i> My Evaluations
                </a>
            @endif
            <a href="{{ route('vendor.purchase-orders.index') }}" class="vendor-nav-link {{ request()->routeIs('vendor.purchase-orders.*') ? 'active' : '' }}">
                <i class="feather-file-text"></i> Purchase Orders
            </a>
            <a href="{{ route('vendor.reports.index') }}" class="vendor-nav-link {{ request()->routeIs('vendor.reports.*') ? 'active' : '' }}">
                <i class="feather-clipboard"></i> Reports
            </a>
            <a href="{{ route('vendor.knowledge.index') }}" class="vendor-nav-link {{ request()->routeIs('vendor.knowledge.*') ? 'active' : '' }}">
                <i class="feather-folder"></i> Knowledge Management
            </a>
            @if ($vendorThinkTankMember)
                <a href="{{ route('vendor.work-plan.index') }}" class="vendor-nav-link {{ request()->routeIs('vendor.work-plan.*') ? 'active' : '' }}">
                    <i class="feather-calendar"></i> Work Plan
                </a>
                <a href="{{ route('vendor.research-report.index') }}" class="vendor-nav-link {{ request()->routeIs('vendor.research-report.*') ? 'active' : '' }}">
                    <i class="feather-book-open"></i> Research Report
                </a>
            @endif
            <a href="{{ route('vendor.clarifications') }}" class="vendor-nav-link {{ request()->routeIs('vendor.clarifications') ? 'active' : '' }}">
                <i class="feather-message-square"></i> Clarifications
            </a>
            <a href="{{ route('grm.submissions.create') }}" class="vendor-nav-link {{ request()->routeIs('grm.submissions.*') ? 'active' : '' }}">
                <i class="feather-alert-octagon"></i> Grievance Redress Mechanism
            </a>

            <div class="vendor-nav-label">Finance</div>
            <a href="{{ route('vendor.payment-details') }}" class="vendor-nav-link {{ request()->routeIs('vendor.payment-details') ? 'active' : '' }}">
                <i class="feather-credit-card"></i> Payment Details
            </a>
            <a href="{{ route('vendor.payments.index') }}" class="vendor-nav-link {{ request()->routeIs('vendor.payments.*') ? 'active' : '' }}">
                <i class="feather-dollar-sign"></i> Payments
            </a>
            <a href="{{ route('vendor.invoices.index') }}" class="vendor-nav-link {{ request()->routeIs('vendor.invoices.*') ? 'active' : '' }}">
                <i class="feather-file"></i> Invoices
            </a>
            <a href="{{ route('vendor.deliverables.index') }}" class="vendor-nav-link {{ request()->routeIs('vendor.deliverables.*') ? 'active' : '' }}">
                <i class="feather-check-square"></i> Deliverables
            </a>

            <div class="vendor-nav-label">Account</div>
            <a href="{{ route('logout') }}" class="vendor-nav-link"
                onclick="event.preventDefault(); document.getElementById('vendor-logout-form').submit();">
                <i class="feather-log-out"></i> Logout
            </a>
            <form id="vendor-logout-form" method="POST" action="{{ route('logout') }}" class="d-none">
                @csrf
            </form>
        </aside>

        <main class="vendor-main">
            <header class="vendor-topbar">
                <div>
                    <div class="vendor-topbar-kicker">Vendor Workspace</div>
                    <div class="fw-bold text-dark">{{ $vendorName }}</div>
                    <div class="small text-muted">{{ now()->format('l, M d, Y') }}</div>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <a href="{{ route('vendor.purchase-orders.index') }}" class="btn btn-vendor btn-sm">
                        <i class="feather-file-text me-1"></i> Purchase Orders
                    </a>
                    <a href="{{ route('vendor.procurements.index') }}" class="btn btn-vendor-outline btn-sm">
                        <i class="feather-search me-1"></i> Find Procurements
                    </a>
                    <div class="vendor-user-pill">
                        <span class="vendor-avatar">{{ $vendorInitials ?: 'V' }}</span>
                        <div class="min-w-0">
                            <div class="small fw-bold text-truncate">{{ $vendorEmail }}</div>
                            <div class="small text-muted text-truncate">{{ $vendorUser?->vendor_category ?? 'Vendor' }}</div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="vendor-content">
                @yield('content')
            </div>

            <footer class="vendor-footer">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="small">ATTP Vendor Portal</div>
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
