<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('description', 'African Think Tank Platform')">
    <meta name="theme-color" content="#006B3F">
    <title>@yield('title', 'African Think Tank Platform')</title>
    <link rel="icon" href="{{ asset('assets/images/au3.jpg') }}" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('admin/assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/feather.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/style.css') }}">
    @if (app()->getLocale() === 'ar')
        <link rel="stylesheet" href="{{ asset('assets/css/rtl.css') }}">
    @endif
    <style>
        body.attp-public-page { margin: 0; background: #f5f8f6; font-family: Inter, sans-serif; color: #0f172a; }
        .attp-public-page .navbar { position: relative; z-index: 1030; }
        .attp-public-main { min-height: calc(100vh - 280px); }
        .public-grievance-link { color: #7a3b51 !important; font-weight: 800 !important; }
        .public-grievance-link:hover { color: #522b39 !important; }
        @media (max-width: 991px) { .attp-public-page .navbar { position: relative; } }
    </style>
    @stack('styles')
</head>
<body class="public-mobile-nav attp-public-page" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <div class="mobile-nav-overlay" id="navOverlay" onclick="closeMobileNav()"></div>

    <nav class="mobile-nav" id="mobileNav" aria-label="{{ __('navigation.main_navigation') }}">
        <div class="mobile-nav-header">
            <img src="{{ asset('assets/images/au.png') }}" alt="ATTP">
            <button class="mobile-nav-close" type="button" onclick="closeMobileNav()" aria-label="{{ __('navigation.close_menu') }}">&times;</button>
        </div>
        <a href="{{ route('landing.index') }}" onclick="closeMobileNav()">{{ __('navigation.home') }}</a>
        <a href="{{ route('events') }}" onclick="closeMobileNav()">{{ __('landing.events_webinars') }}</a>
        <a href="{{ route('news.index') }}" onclick="closeMobileNav()">{{ __('navigation.news_updates') }}</a>
        <a href="{{ route('gallery') }}" onclick="closeMobileNav()">{{ __('navigation.gallery') }}</a>
        <a href="{{ route('public.grievances.create') }}" class="public-grievance-link" onclick="closeMobileNav()">Log a Grievance</a>
        <a href="{{ route('landing.index') }}#contact" onclick="closeMobileNav()">{{ __('navigation.contact') }}</a>
        <div class="mobile-nav-actions">
            <a href="{{ route('public.procurement.index') }}" class="btn btn-primary">{{ __('landing.policy_programs') }}</a>
            <a href="{{ route('login') }}" class="btn btn-login">{{ __('navigation.login') }}</a>
            <x-language-selector style="landing-mobile" />
        </div>
    </nav>

    <header class="navbar" role="banner">
        <a href="{{ route('landing.index') }}" class="logo" aria-label="ATTP Home">
            <img src="{{ asset('assets/images/au.png') }}" alt="African Think Tank Platform" class="logo-sm">
        </a>
        <nav class="nav-links" aria-label="{{ __('navigation.main_navigation') }}">
            <a href="{{ route('landing.index') }}">{{ __('navigation.home') }}</a>
            <a href="{{ route('events') }}">{{ __('landing.events_webinars') }}</a>
            <a href="{{ route('news.index') }}">{{ __('navigation.news_updates') }}</a>
            <a href="{{ route('gallery') }}">{{ __('navigation.gallery') }}</a>
            <a href="{{ route('public.grievances.create') }}"
                class="public-grievance-link {{ request()->routeIs('public.grievances.*') ? 'active' : '' }}">Log a Grievance</a>
            <a href="{{ route('landing.index') }}#contact">{{ __('navigation.contact') }}</a>
        </nav>
        <div class="nav-actions">
            <a href="{{ route('login') }}" class="btn btn-login">{{ __('navigation.login') }}</a>
            <x-language-selector style="landing" />
        </div>
        <button class="hamburger-btn" id="hamburgerBtn" type="button" onclick="openMobileNav()" aria-label="{{ __('navigation.open_menu') }}" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
    </header>

    <main class="attp-public-main" id="main-content">
        @yield('content')
    </main>

    <footer id="contact" class="footer" role="contentinfo">
        <div class="footer-content">
            <div class="footer-logo">
                <h3>ATTP<span> Administration</span></h3>
                <p>{{ __('landing.footer_description') }}</p>
            </div>
            <div class="footer-links">
                <h4>{{ __('landing.footer_links_title') }}</h4>
                <a href="{{ route('landing.index') }}">{{ __('navigation.home') }}</a>
                <a href="{{ route('public.grievances.create') }}">Log a Grievance</a>
                <a href="{{ route('gallery') }}">{{ __('navigation.gallery') }}</a>
                <a href="{{ route('careers.index') }}">{{ __('navigation.careers') }}</a>
            </div>
            <div class="footer-contact">
                <h4>{{ __('landing.footer_contact_title') }}</h4>
                <p>{{ __('landing.footer_email') }}</p>
                <p>{{ __('landing.footer_copyright', ['year' => date('Y')]) }}</p>
            </div>
        </div>
        <div class="footer-bottom"><p>{{ __('landing.supporting_statement') }}</p></div>
    </footer>

    <script src="{{ asset('admin/assets/vendors/js/bootstrap.min.js') }}"></script>
    <script>
        function openMobileNav() {
            const nav = document.getElementById('mobileNav');
            const overlay = document.getElementById('navOverlay');
            const button = document.getElementById('hamburgerBtn');
            nav?.classList.add('open');
            if (overlay) {
                overlay.style.display = 'block';
                requestAnimationFrame(() => overlay.classList.add('visible'));
            }
            button?.classList.add('open');
            button?.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
        }

        function closeMobileNav() {
            const nav = document.getElementById('mobileNav');
            const overlay = document.getElementById('navOverlay');
            const button = document.getElementById('hamburgerBtn');
            nav?.classList.remove('open');
            overlay?.classList.remove('visible');
            button?.classList.remove('open');
            button?.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
            window.setTimeout(() => { if (overlay) overlay.style.display = 'none'; }, 300);
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeMobileNav();
        });
    </script>
    @stack('scripts')
    @stack('modals')
</body>
</html>
