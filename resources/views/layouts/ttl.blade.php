<!doctype html>
<html lang="{{ app()->getLocale() }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ATTP TTL Workspace')</title>

    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('admin/assets/images/favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/vendors.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/css/theme.min.css') }}">

    @stack('styles')

    <style>
        :root {
            --ttl-green: #006b3f;
            --ttl-green-dark: #064e3b;
            --ttl-wine: #522b39;
            --ttl-ink: #142033;
            --ttl-muted: #667085;
            --ttl-border: #dbe6df;
            --ttl-soft: #f3f8f5;
            --ttl-sidebar: 250px;
        }

        body {
            margin: 0;
            color: var(--ttl-ink);
            background: linear-gradient(180deg, #edf8f1 0%, #f7f9fc 42%, #f5f7fb 100%);
            font-size: 14px;
        }

        .ttl-shell {
            min-height: 100vh;
            display: flex;
        }

        .ttl-sidebar {
            width: var(--ttl-sidebar);
            position: fixed;
            inset: 0 auto 0 0;
            padding: 16px 12px;
            color: #fff;
            background: linear-gradient(180deg, #064e3b 0%, #006b3f 58%, #522b39 100%);
            box-shadow: 14px 0 34px rgba(15, 23, 42, .14);
            z-index: 1030;
            overflow-y: auto;
        }

        .ttl-brand,
        .ttl-sidebar-footer {
            border: 1px solid rgba(255, 255, 255, .16);
            border-radius: 8px;
            background: rgba(255, 255, 255, .1);
            padding: 12px;
        }

        .ttl-brand img {
            width: 38px;
            height: 38px;
            object-fit: contain;
            border-radius: 8px;
            background: #fff;
            padding: 4px;
        }

        .ttl-brand-title {
            font-weight: 900;
            line-height: 1.1;
        }

        .ttl-brand-subtitle {
            color: rgba(255, 255, 255, .72);
            font-size: .76rem;
        }

        .ttl-search {
            position: relative;
            margin: 14px 0;
        }

        .ttl-search i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, .72);
        }

        .ttl-search input {
            width: 100%;
            min-height: 42px;
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 8px;
            padding: 8px 12px 8px 38px;
            color: #fff;
            background: rgba(255, 255, 255, .12);
            outline: 0;
        }

        .ttl-search input::placeholder {
            color: rgba(255, 255, 255, .68);
        }

        .ttl-nav-label {
            margin: 18px 8px 8px;
            color: rgba(255, 255, 255, .62);
            font-size: .68rem;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .ttl-nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 42px;
            border-radius: 8px;
            padding: 9px 10px;
            color: rgba(255, 255, 255, .82);
            text-decoration: none;
            font-weight: 800;
        }

        .ttl-nav-link:hover,
        .ttl-nav-link.active {
            color: #fff;
            background: rgba(255, 255, 255, .14);
        }

        .ttl-nav-link.is-filtered-out,
        .ttl-nav-label.is-filtered-out,
        .ttl-nav-empty {
            display: none;
        }

        .ttl-nav-empty.is-visible {
            display: block;
            border: 1px dashed rgba(255, 255, 255, .24);
            border-radius: 8px;
            padding: 10px;
            color: rgba(255, 255, 255, .72);
            font-weight: 700;
        }

        .ttl-sidebar-footer {
            margin-top: 18px;
            color: rgba(255, 255, 255, .72);
            font-size: .78rem;
        }

        .ttl-sidebar-footer strong {
            display: block;
            color: #fff;
            margin-bottom: 3px;
        }

        .ttl-main {
            width: calc(100% - var(--ttl-sidebar));
            margin-left: var(--ttl-sidebar);
            min-height: 100vh;
            padding: 18px;
        }

        .ttl-topbar,
        .ttl-page-header,
        .ttl-footer {
            border: 1px solid var(--ttl-border);
            border-radius: 8px;
            background: rgba(255, 255, 255, .9);
            box-shadow: 0 10px 24px rgba(15, 23, 42, .04);
        }

        .ttl-topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
        }

        .ttl-topbar-kicker {
            color: var(--ttl-green);
            font-size: .74rem;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .ttl-topbar-title {
            color: var(--ttl-ink);
            font-size: 1.08rem;
            font-weight: 900;
        }

        .ttl-topbar-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #cfe5da;
            border-radius: 999px;
            padding: 6px 10px;
            color: var(--ttl-green-dark);
            background: #eef9f3;
            font-weight: 800;
            font-size: .78rem;
        }

        .ttl-user-chip {
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid #e5e7eb;
            border-radius: 999px;
            padding: 7px 10px;
            background: #fff;
        }

        .ttl-avatar {
            width: 34px;
            height: 34px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            color: #fff;
            background: var(--ttl-wine);
            font-weight: 900;
        }

        .ttl-page-header {
            margin-top: 14px;
            padding: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .ttl-page-title {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 900;
        }

        .ttl-page-subtitle {
            color: var(--ttl-muted);
            margin-top: 3px;
        }

        .ttl-content {
            padding: 14px 0;
        }

        .ttl-footer {
            padding: 14px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            color: var(--ttl-muted);
            font-size: .86rem;
        }

        @media (max-width: 991.98px) {
            .ttl-sidebar {
                position: static;
                width: 100%;
                min-height: auto;
            }

            .ttl-shell {
                display: block;
            }

            .ttl-main {
                width: 100%;
                margin-left: 0;
            }

            .ttl-topbar {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    @php
        $ttlUser = auth()->user();
        $ttlName = $ttlUser?->name ?? 'TTL';
        $ttlEmail = $ttlUser?->email ?? '';
        $ttlInitials = collect(preg_split('/\s+/', trim($ttlName)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn ($namePart) => strtoupper(substr($namePart, 0, 1)))
            ->implode('') ?: 'TL';
        $ttlPageTitle = trim($__env->yieldContent('ttl_page_title', $__env->yieldContent('title', 'TTL Workspace'))) ?: 'TTL Workspace';
        $ttlPageSubtitle = trim($__env->yieldContent('ttl_page_subtitle', 'Review assigned ATTP programs, projects, activities, budgets and progress.')) ?: 'Review assigned ATTP programs, projects, activities, budgets and progress.';
    @endphp

    <div class="ttl-shell">
        <aside class="ttl-sidebar">
            <div class="ttl-brand">
                <div class="d-flex align-items-center gap-3">
                    <img src="{{ asset('assets/images/au.png') }}" alt="ATTP">
                    <div>
                        <div class="ttl-brand-title">ATTP TTL Workspace</div>
                        <div class="ttl-brand-subtitle">Task Team Leader portal</div>
                    </div>
                </div>
            </div>

            <div class="ttl-search">
                <i class="feather-search"></i>
                <input type="search" placeholder="Search menus" data-ttl-menu-search>
            </div>

            <div class="ttl-nav-empty" data-ttl-menu-empty>No menu found.</div>

            <div class="ttl-nav-label" data-ttl-nav-label="workspace">Workspace</div>
            <a href="{{ route('ttl.dashboard') }}"
                class="ttl-nav-link {{ request()->routeIs('ttl.dashboard') ? 'active' : '' }}"
                data-ttl-menu-item
                data-ttl-nav-section="workspace"
                data-ttl-menu-label="dashboard overview programs projects progress">
                <i class="feather-home"></i> Dashboard
            </a>
            <a href="{{ route('ttl.dashboard') }}#ttl-programs"
                class="ttl-nav-link"
                data-ttl-menu-item
                data-ttl-nav-section="workspace"
                data-ttl-menu-label="assigned programs">
                <i class="feather-folder"></i> Assigned Programs
            </a>
            <a href="{{ route('ttl.dashboard') }}#ttl-projects"
                class="ttl-nav-link"
                data-ttl-menu-item
                data-ttl-nav-section="workspace"
                data-ttl-menu-label="projects activities sub activities">
                <i class="feather-layers"></i> Projects
            </a>
            <a href="{{ route('grm.submissions.create') }}"
                class="ttl-nav-link {{ request()->routeIs('grm.submissions.*') ? 'active' : '' }}"
                data-ttl-menu-item
                data-ttl-nav-section="workspace"
                data-ttl-menu-label="grievance redress mechanism complaints concerns cases">
                <i class="feather-alert-octagon"></i> Grievance Redress Mechanism
            </a>

            <div class="ttl-nav-label" data-ttl-nav-label="account">Account</div>
            <a href="{{ route('logout') }}" class="ttl-nav-link"
                data-ttl-menu-item
                data-ttl-nav-section="account"
                data-ttl-menu-label="logout sign out"
                onclick="event.preventDefault(); document.getElementById('ttl-logout-form').submit();">
                <i class="feather-log-out"></i> Logout
            </a>
            <form id="ttl-logout-form" method="POST" action="{{ route('logout') }}" class="d-none">
                @csrf
            </form>

            <div class="ttl-sidebar-footer">
                <strong>Read-only TTL access</strong>
                <div>Program oversight for assigned ATTP delivery records.</div>
            </div>
        </aside>

        <main class="ttl-main">
            <header class="ttl-topbar">
                <div>
                    <div class="ttl-topbar-kicker">Integrated Program Oversight</div>
                    <div class="ttl-topbar-title">Task Team Leader Control Center</div>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <span class="ttl-topbar-chip">
                            <i class="feather-clock"></i>
                            <span data-ttl-current-time>{{ now()->format('D, M j H:i:s') }}</span>
                        </span>
                        <span class="ttl-topbar-chip">
                            <i class="feather-map-pin"></i>
                            <span data-ttl-current-location>{{ config('app.timezone', 'Africa/Nairobi') }}</span>
                        </span>
                    </div>
                </div>
                <div class="ttl-user-chip">
                    <div class="ttl-avatar">{{ $ttlInitials }}</div>
                    <div>
                        <div class="fw-bold text-dark">{{ $ttlName }}</div>
                        <div class="small text-muted">{{ $ttlEmail }}</div>
                    </div>
                </div>
            </header>

            <section class="ttl-page-header">
                <div>
                    <h1 class="ttl-page-title">{{ $ttlPageTitle }}</h1>
                    <div class="ttl-page-subtitle">{{ $ttlPageSubtitle }}</div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('ttl.dashboard') }}" class="btn btn-success btn-sm">
                        <i class="feather-home me-1"></i> Dashboard
                    </a>
                    <a href="{{ route('ttl.dashboard') }}" class="btn btn-light btn-sm"
                        onclick="if (window.history.length > 1) { event.preventDefault(); window.history.back(); }">
                        <i class="feather-arrow-left me-1"></i> Back
                    </a>
                </div>
            </section>

            <div class="ttl-content">
                @yield('content')
            </div>

            <footer class="ttl-footer">
                <div><strong>ATTP TTL Workspace</strong></div>
                <div><i class="feather-tool me-1"></i> Developed, maintained and supported by the ATTP Technical Team.</div>
            </footer>
        </main>
    </div>

    <script src="{{ asset('admin/assets/vendors/js/vendors.min.js') }}"></script>
    <script src="{{ asset('admin/assets/js/common-init.min.js') }}"></script>
    <script>
        (function () {
            const searchInput = document.querySelector('[data-ttl-menu-search]');
            const menuItems = Array.from(document.querySelectorAll('[data-ttl-menu-item]'));
            const labels = Array.from(document.querySelectorAll('[data-ttl-nav-label]'));
            const emptyState = document.querySelector('[data-ttl-menu-empty]');

            if (searchInput && menuItems.length) {
                const normalize = (value) => (value || '').toString().toLowerCase().trim();
                const filterMenus = () => {
                    const query = normalize(searchInput.value);
                    let visibleCount = 0;

                    menuItems.forEach((item) => {
                        const label = normalize(item.dataset.ttlMenuLabel + ' ' + item.textContent);
                        const isMatch = query === '' || label.includes(query);
                        item.classList.toggle('is-filtered-out', !isMatch);
                        if (isMatch) visibleCount += 1;
                    });

                    labels.forEach((label) => {
                        const section = label.dataset.ttlNavLabel;
                        const hasVisibleItem = menuItems.some((item) => item.dataset.ttlNavSection === section && !item.classList.contains('is-filtered-out'));
                        label.classList.toggle('is-filtered-out', query !== '' && !hasVisibleItem);
                    });

                    emptyState?.classList.toggle('is-visible', visibleCount === 0);
                };

                searchInput.addEventListener('input', filterMenus);
                searchInput.addEventListener('search', filterMenus);
            }

            const timeTarget = document.querySelector('[data-ttl-current-time]');
            const locationTarget = document.querySelector('[data-ttl-current-location]');

            if (locationTarget && window.Intl) {
                const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
                if (timeZone) locationTarget.textContent = timeZone.replace(/_/g, ' ');
            }

            if (timeTarget) {
                const updateClock = () => {
                    timeTarget.textContent = new Date().toLocaleString([], {
                        weekday: 'short',
                        month: 'short',
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit'
                    });
                };
                updateClock();
                window.setInterval(updateClock, 1000);
            }
        })();
    </script>
    @stack('scripts')
</body>
</html>
