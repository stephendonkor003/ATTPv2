<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="ATTP Think Tank Portal">

    <title>@yield('title', 'ATTP Think Tank Portal')</title>

    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('admin/assets/images/favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/feather.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/css/theme.min.css') }}">

    @if (app()->getLocale() === 'ar')
        <link rel="stylesheet" href="{{ asset('assets/css/rtl.css') }}">
    @endif

    <style>
        :root {
            color-scheme: light;
            --tt-page: #f5f7f6;
            --tt-surface: #ffffff;
            --tt-surface-soft: #f8faf9;
            --tt-border: #e1e7e3;
            --tt-border-strong: #ccd7d1;
            --tt-ink: #18221d;
            --tt-muted: #637068;
            --tt-brand: #176b4b;
            --tt-brand-deep: #10543b;
            --tt-brand-soft: #e9f2ed;
            --tt-focus: #2563eb;
            --tt-shadow: 0 1px 2px rgba(20, 43, 30, .06);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            min-height: 100%;
            background: var(--tt-page);
            scroll-behavior: smooth;
        }

        body.tt-portal-body {
            min-height: 100vh;
            margin: 0;
            background: var(--tt-page);
            color: var(--tt-ink);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.5;
            text-rendering: optimizeLegibility;
            -webkit-font-smoothing: antialiased;
        }

        a {
            color: var(--tt-brand);
        }

        a:hover {
            color: var(--tt-brand-deep);
        }

        :where(a, button, input, select, textarea, summary):focus-visible {
            outline: 3px solid rgba(37, 99, 235, .34);
            outline-offset: 2px;
        }

        .tt-skip-link {
            position: fixed;
            inset-block-start: 10px;
            inset-inline-start: 10px;
            z-index: 10000;
            padding: .65rem .9rem;
            border-radius: 9px;
            background: var(--tt-ink);
            color: #fff;
            font-weight: 800;
            transform: translateY(-150%);
            transition: transform .16s ease;
        }

        .tt-skip-link:focus {
            color: #fff;
            transform: translateY(0);
        }

        .tt-layout {
            min-height: 100vh;
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition-duration: .01ms !important;
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
            }
        }
    </style>

    @stack('styles')
</head>
<body class="tt-portal-body">
    <a class="tt-skip-link" href="#tt-main-content">Skip to main content</a>

    <div class="tt-layout">
        @yield('content')
    </div>

    <script src="{{ asset('admin/assets/vendors/js/bootstrap.min.js') }}"></script>
    @stack('scripts')
    @stack('modals')
</body>
</html>
