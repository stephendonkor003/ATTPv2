<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" data-theme="light" data-theme-preference="system">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="ATTP Think Tank workspace">

    <title>@yield('title', 'Think Tank Portal')</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('think-tank-portal/assets/images/attp-logo.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('think-tank-portal/assets/vendor/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('think-tank-portal/assets/vendor/feather.min.css') }}">

    @if (app()->getLocale() === 'ar')
        <link rel="stylesheet" href="{{ asset('think-tank-portal/assets/css/rtl.css') }}">
    @endif

    @php
        $initialPortalPreferences = auth()->user() && method_exists(auth()->user(), 'resolvedThinkTankPortalPreferences')
            ? auth()->user()->resolvedThinkTankPortalPreferences()
            : \App\Models\User::DEFAULT_THINK_TANK_PORTAL_PREFERENCES;
    @endphp
    <script>
        (() => {
            const preference = @json($initialPortalPreferences['theme_mode'] ?? 'system');
            const dark = preference === 'dark'
                || (preference === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.dataset.theme = dark ? 'dark' : 'light';
            document.documentElement.dataset.themePreference = preference;
        })();
    </script>


    @stack('styles')
    <link rel="stylesheet" href="{{ asset('think-tank-portal/assets/css/portal.css') }}?v={{ filemtime(public_path('think-tank-portal/assets/css/portal.css')) }}">
    <link rel="stylesheet" href="{{ asset('think-tank-portal/assets/css/modules.css') }}?v={{ filemtime(public_path('think-tank-portal/assets/css/modules.css')) }}">
</head>
<body class="tt-portal-body">
    <a class="tt-skip-link" href="#tt-main-content">Skip to main content</a>
    @yield('content')

    <script src="{{ asset('think-tank-portal/assets/vendor/bootstrap.min.js') }}"></script>
    <script src="{{ asset('think-tank-portal/assets/js/portal.js') }}?v={{ filemtime(public_path('think-tank-portal/assets/js/portal.js')) }}"></script>
    @stack('scripts')
    @stack('modals')
</body>
</html>
