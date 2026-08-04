<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Document Uploads') | ATTP</title>
    <link rel="shortcut icon" type="image/jpeg" href="{{ asset('assets/images/attp-logo.jpeg') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/vendors.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/css/theme.min.css') }}">
    <style>
        :root {
            --aa-navy: #10233f;
            --aa-teal: #087f73;
            --aa-mint: #e9f8f4;
            --aa-bg: #f4f7fb;
            --aa-border: #dce4ee;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--aa-bg); color: #172033; font-family: Inter, system-ui, -apple-system, sans-serif; }
        .aa-shell { min-height: 100vh; display: flex; }
        .aa-sidebar { width: 260px; position: fixed; inset: 0 auto 0 0; padding: 26px 18px; background: linear-gradient(175deg, var(--aa-navy), #163a52 60%, var(--aa-teal)); color: #fff; z-index: 20; }
        .aa-brand { display: flex; align-items: center; gap: 12px; padding: 2px 8px 26px; border-bottom: 1px solid rgba(255,255,255,.16); }
        .aa-brand img { width: 46px; height: 46px; object-fit: contain; background: #fff; border-radius: 12px; padding: 4px; }
        .aa-brand-title { font-size: 1rem; font-weight: 800; line-height: 1.2; }
        .aa-brand-subtitle { color: rgba(255,255,255,.68); font-size: .75rem; }
        .aa-nav-label { margin: 26px 10px 9px; color: rgba(255,255,255,.58); text-transform: uppercase; letter-spacing: .1em; font-size: .68rem; font-weight: 800; }
        .aa-nav-link { display: flex; align-items: center; gap: 10px; padding: 12px 14px; color: rgba(255,255,255,.82); border-radius: 12px; text-decoration: none; font-weight: 650; }
        .aa-nav-link:hover, .aa-nav-link.active { color: #fff; background: rgba(255,255,255,.14); }
        .aa-user { position: absolute; left: 18px; right: 18px; bottom: 22px; padding: 14px; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.13); border-radius: 14px; }
        .aa-main { width: calc(100% - 260px); margin-left: 260px; min-height: 100vh; }
        .aa-topbar { min-height: 78px; display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 16px 30px; background: rgba(255,255,255,.94); border-bottom: 1px solid var(--aa-border); position: sticky; top: 0; z-index: 15; backdrop-filter: blur(8px); }
        .aa-topbar-kicker { color: var(--aa-teal); font-size: .7rem; font-weight: 800; text-transform: uppercase; letter-spacing: .1em; }
        .aa-content { max-width: 1480px; margin: 0 auto; padding: 30px; }
        .aa-page-title { font-size: clamp(1.55rem, 3vw, 2.25rem); color: var(--aa-navy); font-weight: 850; letter-spacing: -.035em; }
        .aa-card { background: #fff; border: 1px solid var(--aa-border); border-radius: 18px; box-shadow: 0 14px 34px rgba(16,35,63,.07); }
        .btn-aa { background: var(--aa-teal); border-color: var(--aa-teal); color: #fff; font-weight: 700; }
        .btn-aa:hover { background: #066b62; border-color: #066b62; color: #fff; }
        .btn-aa-soft { color: #086a62; background: var(--aa-mint); border-color: #b9e8de; font-weight: 700; }
        .aa-mobile-menu { display: none; }
        @media (max-width: 991.98px) {
            .aa-sidebar { display: none; }
            .aa-main { width: 100%; margin-left: 0; }
            .aa-topbar { padding: 14px 18px; }
            .aa-content { padding: 20px 16px 90px; }
            .aa-mobile-menu { display: flex; position: fixed; bottom: 14px; left: 14px; right: 14px; z-index: 30; padding: 9px; border-radius: 16px; background: var(--aa-navy); box-shadow: 0 16px 35px rgba(15,23,42,.3); justify-content: space-around; }
            .aa-mobile-menu a, .aa-mobile-menu button { color: #fff; background: transparent; border: 0; text-decoration: none; font-size: .8rem; font-weight: 700; padding: 8px 12px; }
        }
    </style>
    @stack('styles')
</head>
<body>
@php($assistantUser = auth()->user())
<div class="aa-shell">
    <aside class="aa-sidebar">
        <div class="aa-brand">
            <img src="{{ asset('assets/images/au.png') }}" alt="ATTP">
            <div>
                <div class="aa-brand-title">ATTP Documents</div>
                <div class="aa-brand-subtitle">Administrative Assistant</div>
            </div>
        </div>

        <div class="aa-nav-label">My workspace</div>
        <a href="{{ route('administrative-assistant.dashboard') }}" class="aa-nav-link {{ request()->routeIs('administrative-assistant.*') ? 'active' : '' }}">
            <i class="feather-upload-cloud"></i> Upload centre
        </a>

        <div class="aa-user">
            <div class="fw-bold text-truncate">{{ $assistantUser?->name }}</div>
            <div class="small text-white-50 text-truncate mb-2">{{ $assistantUser?->email }}</div>
            <a href="{{ route('logout') }}" class="small text-white text-decoration-none"
               onclick="event.preventDefault(); document.getElementById('aaLogoutForm').submit();">
                <i class="feather-log-out me-1"></i> Sign out
            </a>
        </div>
    </aside>

    <main class="aa-main">
        <header class="aa-topbar">
            <div>
                <div class="aa-topbar-kicker">Simple document workspace</div>
                <div class="fw-bold">Invoices & deliverable evidence</div>
            </div>
            <div class="text-end d-none d-sm-block">
                <div class="small text-muted">Today</div>
                <div class="fw-semibold">{{ now()->format('D, j M Y') }}</div>
            </div>
        </header>

        <div class="aa-content">
            @if (session('success'))
                <div class="alert alert-success border-0 shadow-sm"><i class="feather-check-circle me-2"></i>{{ session('success') }}</div>
            @endif
            @if (session('info'))
                <div class="alert alert-info border-0 shadow-sm"><i class="feather-info me-2"></i>{{ session('info') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger border-0 shadow-sm">
                    <div class="fw-bold mb-1">Please check the highlighted information.</div>
                    <div>{{ $errors->first() }}</div>
                </div>
            @endif

            @yield('content')
        </div>
    </main>
</div>

<div class="aa-mobile-menu">
    <a href="{{ route('administrative-assistant.dashboard') }}"><i class="feather-home me-1"></i> Home</a>
    <button type="button" onclick="document.getElementById('aaLogoutForm').submit();"><i class="feather-log-out me-1"></i> Sign out</button>
</div>
<form id="aaLogoutForm" method="POST" action="{{ route('logout') }}" class="d-none">@csrf</form>

<script src="{{ asset('admin/assets/vendors/js/vendors.min.js') }}"></script>
@stack('scripts')
</body>
</html>
