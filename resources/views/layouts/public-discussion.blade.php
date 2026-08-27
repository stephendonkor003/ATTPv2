<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('description')">
    <meta name="referrer" content="no-referrer">
    <meta name="theme-color" content="#006B3F">
    <title>@yield('title')</title>
    <link rel="icon" href="{{ asset('assets/images/au3.jpg') }}" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/style.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/discussion-forum.css') }}?v={{ filemtime(public_path('assets/css/discussion-forum.css')) }}">
    @if(app()->getLocale() === 'ar')
        <link rel="stylesheet" href="{{ asset('assets/css/rtl.css') }}">
    @endif
</head>
<body class="public-mobile-nav discussion-forum-page" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    @include('layouts.partials.impersonation-banner')
    <a class="forum-skip-link" href="#discussion-content">Skip to forum content</a>

    <div class="mobile-nav-overlay" id="navOverlay" onclick="closeMobileNav()"></div>

    <nav class="mobile-nav" id="mobileNav" aria-label="{{ __('navigation.main_navigation') }}">
        <div class="mobile-nav-header">
            <img src="{{ asset('assets/images/au.png') }}" alt="ATTP">
            <button class="mobile-nav-close" onclick="closeMobileNav()" aria-label="{{ __('navigation.close_menu') }}">&times;</button>
        </div>
        <a href="{{ route('landing.index') }}" onclick="closeMobileNav()">{{ __('navigation.home') }}</a>

        <button type="button" class="mobile-dropdown-toggle" onclick="toggleMobileDropdown(this)" aria-expanded="false">
            {{ __('navigation.programs') }} <span class="mobile-dropdown-arrow">▾</span>
        </button>
        <div class="mobile-dropdown-items" aria-hidden="true">
            <a href="{{ route('events') }}" onclick="closeMobileNav()">{{ __('landing.events_webinars') }}</a>
            <a href="{{ route('careers.index') }}" onclick="closeMobileNav()">{{ __('navigation.careers') }}</a>
        </div>

        <button type="button" class="mobile-dropdown-toggle" onclick="toggleMobileDropdown(this)" aria-expanded="false">
            {{ __('navigation.analytics') }} <span class="mobile-dropdown-arrow">▾</span>
        </button>
        <div class="mobile-dropdown-items" aria-hidden="true">
            <a href="{{ route('impact.map') }}" onclick="closeMobileNav()">{{ __('navigation.impact_map') }}</a>
            <a href="{{ route('world.indicators.performance') }}" onclick="closeMobileNav()">{{ __('navigation.world_indicators_performance') }}</a>
        </div>

        <button type="button" class="mobile-dropdown-toggle open" onclick="toggleMobileDropdown(this)" aria-expanded="true">
            {{ __('navigation.discussion') }} <span class="mobile-dropdown-arrow">▾</span>
        </button>
        <div class="mobile-dropdown-items open" aria-hidden="false">
            <a href="{{ route('discussion.thematic-areas') }}" class="{{ request()->routeIs('discussion.thematic-areas') ? 'active' : '' }}" onclick="closeMobileNav()">{{ __('navigation.thematic_areas') }}</a>
            <a href="{{ route('discussion.current') }}" class="{{ request()->routeIs('discussion.current') ? 'active' : '' }}" onclick="closeMobileNav()">{{ __('navigation.current_discussions') }}</a>
            <a href="{{ route('discussion.join') }}" class="{{ request()->routeIs('discussion.join') ? 'active' : '' }}" onclick="closeMobileNav()">{{ __('navigation.join_discussion') }}</a>
        </div>

        <a href="{{ route('news.index') }}" onclick="closeMobileNav()">{{ __('navigation.news_updates') }}</a>
        <a href="{{ route('gallery') }}" onclick="closeMobileNav()">{{ __('navigation.gallery') }}</a>
        <a href="{{ route('public.grievances.create') }}" onclick="closeMobileNav()">Log a Grievance</a>
        <a href="{{ route('landing.index') }}#contact" onclick="closeMobileNav()">{{ __('navigation.contact') }}</a>

        <div class="mobile-nav-actions">
            <a href="{{ route('public.procurement.index') }}" class="btn btn-primary">{{ __('landing.policy_programs') }}</a>
            <a href="{{ route('login') }}" class="btn btn-login">{{ __('navigation.login') }}</a>
            <x-language-selector style="discussion-mobile" />
        </div>
    </nav>

    <header class="navbar" role="banner">
        <a href="{{ route('landing.index') }}" class="logo" aria-label="ATTP Home">
            <img src="{{ asset('assets/images/au.png') }}" alt="African Think Tank Platform" class="logo-sm">
        </a>

        <nav class="nav-links" aria-label="{{ __('navigation.main_navigation') }}">
            <a href="{{ route('landing.index') }}">{{ __('navigation.home') }}</a>
            <div class="has-dropdown">
                <a href="#">{{ __('navigation.programs') }}</a>
                <ul class="nav-dropdown">
                    <li><a href="{{ route('events') }}">{{ __('landing.events_webinars') }}</a></li>
                    <li><a href="{{ route('careers.index') }}">{{ __('navigation.careers') }}</a></li>
                </ul>
            </div>
            <div class="has-dropdown">
                <a href="#">{{ __('navigation.analytics') }}</a>
                <ul class="nav-dropdown">
                    <li><a href="{{ route('impact.map') }}">{{ __('navigation.impact_map') }}</a></li>
                    <li><a href="{{ route('world.indicators.performance') }}">{{ __('navigation.world_indicators_performance') }}</a></li>
                </ul>
            </div>
            <div class="has-dropdown">
                <a href="#" class="active">{{ __('navigation.discussion') }}</a>
                <ul class="nav-dropdown">
                    <li><a href="{{ route('discussion.thematic-areas') }}" class="{{ request()->routeIs('discussion.thematic-areas') ? 'active' : '' }}">{{ __('navigation.thematic_areas') }}</a></li>
                    <li><a href="{{ route('discussion.current') }}" class="{{ request()->routeIs('discussion.current') ? 'active' : '' }}">{{ __('navigation.current_discussions') }}</a></li>
                    <li><a href="{{ route('discussion.join') }}" class="{{ request()->routeIs('discussion.join') ? 'active' : '' }}">{{ __('navigation.join_discussion') }}</a></li>
                </ul>
            </div>
            <a href="{{ route('news.index') }}">{{ __('navigation.news_updates') }}</a>
            <a href="{{ route('gallery') }}">{{ __('navigation.gallery') }}</a>
            <a href="{{ route('public.grievances.create') }}">Grievance</a>
            <a href="{{ route('landing.index') }}#contact">{{ __('navigation.contact') }}</a>
        </nav>

        <div class="nav-actions">
            <a href="{{ route('public.procurement.index') }}" class="btn btn-primary">{{ __('landing.policy_programs') }}</a>
            <a href="{{ route('login') }}" class="btn btn-login">{{ __('navigation.login') }}</a>
            <x-language-selector style="discussion" />
        </div>

        <button class="hamburger-btn" id="hamburgerBtn" onclick="openMobileNav()" aria-label="{{ __('navigation.open_menu') }}" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
    </header>

    <main class="discussion-main" id="discussion-app">
        <section class="discussion-hero">
            <div class="discussion-hero__pattern" aria-hidden="true"></div>
            <div class="discussion-hero__inner">
                <div class="discussion-hero__copy">
                    <span class="discussion-eyebrow"><span class="forum-live-dot" aria-hidden="true"></span>{{ __('discussion.eyebrow') }}</span>
                    <h1>@yield('heading')</h1>
                    <p>@yield('description')</p>
                    <div class="discussion-hero__actions">
                        <a class="forum-button forum-button--gold" href="{{ route('discussion.current') }}">Explore active discussions <span aria-hidden="true">→</span></a>
                        <a class="forum-button forum-button--hero-outline" href="{{ route('discussion.join') }}">Participate in a discussion</a>
                    </div>
                </div>
                <aside class="discussion-hero__card" aria-label="Forum participation">
                    <div class="discussion-hero__card-icon" aria-hidden="true">✦</div>
                    <span class="discussion-hero__card-label">ATTP Community Forum</span>
                    <strong>Ideas become stronger through informed dialogue.</strong>
                    <p>Read openly. Register simply. Contribute respectfully.</p>
                    <div class="discussion-hero__member" v-cloak v-if="participant">
                        <span class="forum-avatar forum-avatar--tiny" aria-hidden="true" v-text="initials(participant.display_name)"></span>
                        <span>Signed in as <strong v-text="participant.display_name"></strong></span>
                    </div>
                </aside>
            </div>
        </section>

        <section class="discussion-content" id="discussion-content">
            @include('discussion._forum-sections')
        </section>

        <noscript>
            <section class="forum-noscript">
                <h2>JavaScript is required for the live discussion forum</h2>
                <p>Please enable JavaScript to load thematic areas, read discussions, or create a participant account.</p>
            </section>
        </noscript>
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
                <a href="{{ route('discussion.thematic-areas') }}">{{ __('navigation.thematic_areas') }}</a>
                <a href="{{ route('discussion.current') }}">{{ __('navigation.current_discussions') }}</a>
                <a href="{{ route('discussion.join') }}">{{ __('navigation.join_discussion') }}</a>
                <a href="{{ route('public.grievances.create') }}">Log a Grievance</a>
            </div>
            <div class="footer-contact">
                <h4>{{ __('landing.footer_contact_title') }}</h4>
                <p>{{ __('landing.footer_email') }}</p>
                <p>{{ __('landing.footer_copyright', ['year' => date('Y')]) }}</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>{{ __('landing.supporting_statement') }}</p>
        </div>
    </footer>

    @php
        $discussionConfig = [
            'apiBase' => url('/api/discussions'),
            'initialView' => trim($__env->yieldContent('forum_view', 'themes')),
            'locale' => app()->getLocale(),
            'pollInterval' => 30000,
            'urls' => [
                'themes' => route('discussion.thematic-areas'),
                'current' => route('discussion.current'),
                'join' => route('discussion.join'),
            ],
        ];
    @endphp
    <script>window.ATTP_DISCUSSION_CONFIG = @json($discussionConfig);</script>
    <script src="{{ asset('admin/assets/vendors/js/jquery.min.js') }}"></script>
    <script src="{{ asset('admin/assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/vue.global.prod.js') }}"></script>
    <script src="{{ asset('assets/js/discussion-forum.js') }}?v={{ filemtime(public_path('assets/js/discussion-forum.js')) }}"></script>
    <script>
        function openMobileNav() {
            const nav = document.getElementById('mobileNav');
            const overlay = document.getElementById('navOverlay');
            const button = document.getElementById('hamburgerBtn');
            nav.classList.add('open');
            overlay.style.display = 'block';
            requestAnimationFrame(() => overlay.classList.add('visible'));
            button.classList.add('open');
            button.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
        }

        function closeMobileNav() {
            const nav = document.getElementById('mobileNav');
            const overlay = document.getElementById('navOverlay');
            const button = document.getElementById('hamburgerBtn');
            nav.classList.remove('open');
            overlay.classList.remove('visible');
            button.classList.remove('open');
            button.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
            setTimeout(() => { overlay.style.display = 'none'; }, 300);
        }

        function toggleMobileDropdown(trigger) {
            const items = trigger.nextElementSibling;
            if (!items || !items.classList.contains('mobile-dropdown-items')) return;
            const isOpen = items.classList.contains('open');

            document.querySelectorAll('.mobile-dropdown-items.open').forEach((item) => {
                item.classList.remove('open');
                item.setAttribute('aria-hidden', 'true');
            });
            document.querySelectorAll('.mobile-dropdown-toggle.open').forEach((button) => {
                button.classList.remove('open');
                button.setAttribute('aria-expanded', 'false');
            });

            if (!isOpen) {
                items.classList.add('open');
                items.setAttribute('aria-hidden', 'false');
                trigger.classList.add('open');
                trigger.setAttribute('aria-expanded', 'true');
            }
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeMobileNav();
        });
    </script>
</body>
</html>
