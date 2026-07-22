<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="theme-color" content="#006B3F" />
    <title>Gallery | ATTP – African Think Tank Platform</title>
    <meta name="description" content="Browse photos from ATTP events, webinars, and activities across Africa." />
    <link rel="icon" href="{{ asset('assets/images/au3.jpg') }}" type="image/png" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('assets/style.css') }}" />
    {!! app()->getLocale() === 'ar' ? '<link rel="stylesheet" href="' . asset('assets/css/rtl.css') . '">' : '' !!}

    <style>
        /* ── Gallery page overrides ── */
        body { background: #0f1117; color: #eee; }

        /* Hero */
        .gp-hero {
            background: linear-gradient(160deg, #004d2e 0%, #006B3F 55%, #009A44 100%);
            padding: 7rem 2rem 3.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .gp-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url('{{ asset('assets/images/au3.jpg') }}') center/cover no-repeat;
            opacity: 0.08;
        }
        .gp-hero-inner { position: relative; z-index: 1; }
        .gp-hero h1 {
            font-size: 2.8rem;
            font-weight: 800;
            color: #fbbc05;
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }
        .gp-hero p {
            font-size: 1.05rem;
            color: rgba(255,255,255,0.85);
            max-width: 560px;
            margin: 0 auto 1.5rem;
        }
        .gp-stats {
            display: inline-flex;
            gap: 2rem;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 50px;
            padding: 0.6rem 2rem;
            font-size: 0.9rem;
            color: rgba(255,255,255,0.8);
        }
        .gp-stats strong { color: #fbbc05; }

        /* Controls */
        .gp-controls {
            background: #161b22;
            border-bottom: 1px solid #222;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            position: sticky;
            top: 70px;
            z-index: 50;
        }
        .gp-count { color: #888; font-size: 0.88rem; }
        .gp-count span { color: #fbbc05; font-weight: 600; }

        /* Grid */
        .gp-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 6px;
            padding: 6px;
            max-width: 1600px;
            margin: 0 auto;
        }

        .gp-item {
            aspect-ratio: 4/3;
            overflow: hidden;
            cursor: pointer;
            position: relative;
            background: #1a1f2b;
            border-radius: 4px;
        }
        .gp-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.4s ease;
        }
        .gp-item:hover img { transform: scale(1.07); }

        .gp-item-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.55) 0%, transparent 60%);
            opacity: 0;
            transition: opacity 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .gp-item:hover .gp-item-overlay { opacity: 1; }
        .gp-item-overlay svg { color: #fff; width: 36px; height: 36px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.5)); }

        /* Skeleton shimmer */
        .gp-skeleton {
            aspect-ratio: 4/3;
            background: linear-gradient(90deg, #1a1f2b 25%, #232a38 50%, #1a1f2b 75%);
            background-size: 400% 100%;
            animation: shimmer 1.4s ease infinite;
            border-radius: 4px;
        }
        @keyframes shimmer {
            0%   { background-position: 100% 0; }
            100% { background-position: -100% 0; }
        }

        /* Load More */
        .gp-footer {
            padding: 2.5rem 1rem;
            text-align: center;
        }
        .gp-load-more-btn {
            background: #006B3F;
            color: #fff;
            border: none;
            padding: 0.9rem 2.8rem;
            border-radius: 50px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s;
            font-family: inherit;
        }
        .gp-load-more-btn:hover { background: #004d2e; transform: translateY(-2px); }
        .gp-load-more-btn:disabled { background: #333; cursor: default; transform: none; }
        .gp-progress {
            color: #666;
            font-size: 0.85rem;
            margin-top: 0.8rem;
        }
        .gp-progress-bar-wrap {
            width: 200px;
            height: 3px;
            background: #222;
            border-radius: 3px;
            margin: 0.5rem auto 0;
            overflow: hidden;
        }
        .gp-progress-bar {
            height: 100%;
            background: #006B3F;
            border-radius: 3px;
            transition: width 0.4s;
        }

        /* Lightbox */
        .gp-lightbox {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(0,0,0,0.97);
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }
        .gp-lightbox.active {
            display: flex;
            animation: lbFadeIn 0.2s ease;
        }
        @keyframes lbFadeIn { from { opacity: 0; } to { opacity: 1; } }

        .gp-lb-top {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(to bottom, rgba(0,0,0,0.6), transparent);
            z-index: 2;
        }
        .gp-lb-counter {
            color: rgba(255,255,255,0.7);
            font-size: 0.85rem;
            background: rgba(0,0,0,0.4);
            padding: 4px 12px;
            border-radius: 20px;
        }
        .gp-lb-close {
            background: rgba(255,255,255,0.1);
            border: none;
            color: #fff;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            font-size: 1.4rem;
            line-height: 1;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .gp-lb-close:hover { background: rgba(255,255,255,0.25); }

        .gp-lb-body {
            display: flex;
            align-items: center;
            gap: 1rem;
            max-width: 94vw;
            position: relative;
            z-index: 1;
        }
        .gp-lb-img-wrap {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #gpLightboxImg {
            max-width: 82vw;
            max-height: 80vh;
            object-fit: contain;
            border-radius: 3px;
            display: block;
            box-shadow: 0 0 80px rgba(0,0,0,0.8);
            transition: opacity 0.15s;
        }
        #gpLightboxImg.fading { opacity: 0; }
        .gp-lb-loading {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .gp-lb-spinner {
            width: 32px;
            height: 32px;
            border: 3px solid rgba(255,255,255,0.15);
            border-top-color: #006B3F;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .gp-lb-nav {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
            color: #fff;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            font-size: 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
            flex-shrink: 0;
        }
        .gp-lb-nav:hover { background: rgba(255,255,255,0.2); }

        .gp-lb-thumbnails {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 1rem 1.5rem;
            background: linear-gradient(to top, rgba(0,0,0,0.7), transparent);
            display: flex;
            justify-content: center;
            gap: 6px;
            z-index: 2;
            overflow: hidden;
        }
        .gp-lb-thumb {
            width: 48px;
            height: 34px;
            border-radius: 3px;
            overflow: hidden;
            opacity: 0.45;
            cursor: pointer;
            transition: opacity 0.2s, transform 0.2s;
            flex-shrink: 0;
            border: 2px solid transparent;
        }
        .gp-lb-thumb.active { opacity: 1; border-color: #006B3F; transform: scale(1.1); }
        .gp-lb-thumb:hover { opacity: 0.8; }
        .gp-lb-thumb img { width: 100%; height: 100%; object-fit: cover; }

        /* Page footer */
        .gp-page-footer {
            background: #0a0e14;
            border-top: 1px solid #1e2530;
        }
        .gp-page-footer .footer { background: transparent; }

        @media (max-width: 640px) {
            .gp-hero h1 { font-size: 1.8rem; }
            .gp-stats { flex-direction: column; gap: 0.5rem; padding: 0.8rem 1.2rem; }
            .gp-grid { grid-template-columns: repeat(2, 1fr); gap: 4px; padding: 4px; }
            #gpLightboxImg { max-width: 96vw; max-height: 70vh; }
            .gp-lb-nav { width: 36px; height: 36px; font-size: 1.1rem; }
            .gp-lb-thumbnails { display: none; }
        }
    </style>
</head>
<body class="public-mobile-nav" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<!-- ====== MOBILE NAV ====== -->
<div class="mobile-nav-overlay" id="navOverlay" onclick="closeMobileNav()"></div>
<nav class="mobile-nav" id="mobileNav">
    <div class="mobile-nav-header">
        <img src="{{ asset('assets/images/au.png') }}" alt="ATTP">
        <button class="mobile-nav-close" onclick="closeMobileNav()" aria-label="Close menu">&times;</button>
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
    <button type="button" class="mobile-dropdown-toggle" onclick="toggleMobileDropdown(this)" aria-expanded="false">
        {{ __('navigation.discussion') }} <span class="mobile-dropdown-arrow">▾</span>
    </button>
    <div class="mobile-dropdown-items" aria-hidden="true">
        <a href="{{ route('discussion.thematic-areas') }}" onclick="closeMobileNav()">{{ __('navigation.thematic_areas') }}</a>
        <a href="{{ route('discussion.current') }}" onclick="closeMobileNav()">{{ __('navigation.current_discussions') }}</a>
        <a href="{{ route('discussion.join') }}" onclick="closeMobileNav()">{{ __('navigation.join_discussion') }}</a>
    </div>
    <a href="{{ route('news.index') }}" onclick="closeMobileNav()">{{ __('navigation.news_updates') }}</a>
    <a href="{{ route('gallery') }}" class="active" onclick="closeMobileNav()">{{ __('navigation.gallery') }}</a>
    <a href="{{ route('public.grievances.create') }}" onclick="closeMobileNav()">Log a Grievance</a>
    <a href="#contact" onclick="closeMobileNav()">{{ __('navigation.contact') }}</a>
    <div class="mobile-nav-actions">
        <a href="{{ route('public.procurement.index') }}" class="btn btn-primary">{{ __('landing.policy_programs') }}</a>
        <a href="{{ route('login') }}" class="btn btn-login">{{ __('navigation.login') }}</a>
        <x-language-selector style="landing" />
    </div>
</nav>

<!-- ====== NAVBAR ====== -->
<header class="navbar" role="banner">
    <a href="{{ route('landing.index') }}" class="logo" aria-label="ATTP Home">
        <img src="{{ asset('assets/images/au.png') }}" alt="African Think Tank Platform" class="logo-sm">
    </a>
    <nav class="nav-links" aria-label="Main navigation">
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
            <a href="#">{{ __('navigation.discussion') }}</a>
            <ul class="nav-dropdown">
                <li><a href="{{ route('discussion.thematic-areas') }}">{{ __('navigation.thematic_areas') }}</a></li>
                <li><a href="{{ route('discussion.current') }}">{{ __('navigation.current_discussions') }}</a></li>
                <li><a href="{{ route('discussion.join') }}">{{ __('navigation.join_discussion') }}</a></li>
            </ul>
        </div>
        <a href="{{ route('news.index') }}">{{ __('navigation.news_updates') }}</a>
        <a href="{{ route('gallery') }}" class="active">{{ __('navigation.gallery') }}</a>
        <a href="{{ route('public.grievances.create') }}">Grievance</a>
        <a href="#contact">{{ __('navigation.contact') }}</a>
    </nav>
    <div class="nav-actions">
        <a href="{{ route('public.procurement.index') }}" class="btn btn-primary">{{ __('landing.policy_programs') }}</a>
        <a href="{{ route('login') }}" class="btn btn-login">{{ __('navigation.login') }}</a>
        <x-language-selector style="landing" />
    </div>
    <button class="hamburger-btn" id="hamburgerBtn" onclick="openMobileNav()" aria-label="Open menu" aria-expanded="false">
        <span></span><span></span><span></span>
    </button>
</header>

<!-- ====== HERO ====== -->
<section class="gp-hero">
    <div class="gp-hero-inner">
        <h1>Our Gallery</h1>
        <p>Explore moments from ATTP events, webinars, and activities across Africa</p>
        <div class="gp-stats">
            <span><strong>{{ count($galleryImages) }}</strong> Photos</span>
            <span><strong>Pan-African</strong> Events</span>
            <span><strong>AU</strong> Initiatives</span>
        </div>
    </div>
</section>

<!-- ====== FEATURED VIDEO ====== -->
<div style="background:#0a0e14; padding:2.5rem 1rem 0;">
    <div style="max-width:960px; margin:0 auto;">
        <p style="color:#fbbc05; font-size:0.78rem; font-weight:700; letter-spacing:2px; text-transform:uppercase; margin-bottom:0.6rem;">&#9654; Featured Video</p>
        <div style="position:relative; border-radius:10px; overflow:hidden; aspect-ratio:16/9; background:#000; box-shadow:0 20px 60px rgba(0,0,0,0.7);">
            <video id="gpFeaturedVideo" controls playsinline preload="none" poster="{{ asset('assets/images/au3.jpg') }}"
                   style="width:100%;height:100%;object-fit:cover;display:block;">
                <source src="{{ asset('gallary/video.mp4') }}" type="video/mp4">
            </video>
            <!-- unmute toggle -->
            <button id="gpMuteBtn" onclick="toggleGpMute()"
                style="display:none;position:absolute;bottom:1rem;right:1rem;background:rgba(0,0,0,0.55);border:1px solid rgba(255,255,255,0.2);color:#fff;padding:0.4rem 0.9rem;border-radius:20px;font-size:0.78rem;font-weight:600;cursor:pointer;backdrop-filter:blur(4px);transition:background 0.2s;">
                🔇 Unmute
            </button>
        </div>
    </div>
</div>

<!-- ====== CONTROLS BAR ====== -->
<div class="gp-controls">
    <p class="gp-count">Showing <span id="gpShowing">0</span> of <span>{{ count($galleryImages) }}</span> photos</p>
</div>

<!-- ====== GRID ====== -->
<div class="gp-grid" id="gpGrid"></div>

<!-- ====== FOOTER / LOAD MORE ====== -->
<div class="gp-footer" id="gpFooter">
    <button class="gp-load-more-btn" id="gpLoadMoreBtn" onclick="loadMore()">Load More Photos</button>
    <p class="gp-progress" id="gpProgressText"></p>
    <div class="gp-progress-bar-wrap">
        <div class="gp-progress-bar" id="gpProgressBar" style="width:0%"></div>
    </div>
</div>

<!-- ====== LIGHTBOX ====== -->
<div class="gp-lightbox" id="gpLightbox" role="dialog" aria-modal="true" aria-label="Photo viewer">
    <!-- top bar -->
    <div class="gp-lb-top">
        <span class="gp-lb-counter" id="gpLbCounter">1 / {{ count($galleryImages) }}</span>
        <button class="gp-lb-close" onclick="closeLightbox()" aria-label="Close">&times;</button>
    </div>

    <!-- nav + image -->
    <div class="gp-lb-body">
        <button class="gp-lb-nav" onclick="prevLb()" aria-label="Previous">&#8249;</button>
        <div class="gp-lb-img-wrap">
            <img id="gpLightboxImg" src="" alt="Gallery photo">
            <div class="gp-lb-loading" id="gpLbLoading" style="display:none">
                <div class="gp-lb-spinner"></div>
            </div>
        </div>
        <button class="gp-lb-nav" onclick="nextLb()" aria-label="Next">&#8250;</button>
    </div>

    <!-- thumbnail strip -->
    <div class="gp-lb-thumbnails" id="gpLbThumbs"></div>
</div>

<!-- ====== PAGE FOOTER ====== -->
<div class="gp-page-footer">
    <footer id="contact" class="footer" role="contentinfo">
        <div class="footer-content">
            <div class="footer-logo">
                <h3>ATTP<span> Administration</span></h3>
                <p>{{ __('landing.footer_description') }}</p>
            </div>
            <div class="footer-links">
                <h4>{{ __('landing.footer_links_title') }}</h4>
                <a href="{{ route('landing.index') }}">{{ __('landing.footer_link_home') }}</a>
                <a href="{{ route('gallery') }}">Gallery</a>
                <a href="{{ route('events') }}">{{ __('landing.events_webinars') }}</a>
                <a href="{{ route('careers.index') }}">{{ __('navigation.careers') }}</a>
                <a href="#contact">{{ __('navigation.contact') }}</a>
            </div>
            <div class="footer-contact">
                <h4>{{ __('landing.footer_contact_title') }}</h4>
                <p>{{ __('landing.footer_email') }}</p>
                <p>{{ __('landing.footer_copyright', ['year' => date('Y')]) }}</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>Supporting African Union policy coordination, governance reform, and evidence-based decision-making across the continent.</p>
        </div>
    </footer>
</div>

<script>
(function () {
    'use strict';

    var IMAGES = @json($galleryImages);
    var BATCH  = 24;
    var loaded = 0;
    var lbIdx  = 0;

    var grid       = document.getElementById('gpGrid');
    var loadBtn    = document.getElementById('gpLoadMoreBtn');
    var progressTx = document.getElementById('gpProgressText');
    var progressBar= document.getElementById('gpProgressBar');
    var showing    = document.getElementById('gpShowing');
    var footer     = document.getElementById('gpFooter');

    /* ── render a batch ── */
    function renderBatch() {
        var end = Math.min(loaded + BATCH, IMAGES.length);
        var frag = document.createDocumentFragment();

        for (var i = loaded; i < end; i++) {
            (function (idx) {
                var item = document.createElement('div');
                item.className = 'gp-item';

                var img = document.createElement('img');
                img.alt = 'ATTP Gallery';
                img.loading = 'lazy';
                img.decoding = 'async';
                img.onerror = function () { this.parentElement.style.display = 'none'; };
                img.src = IMAGES[idx].thumb;

                var overlay = document.createElement('div');
                overlay.className = 'gp-item-overlay';
                overlay.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M11 8v6M8 11h6"/></svg>';

                item.appendChild(img);
                item.appendChild(overlay);
                item.addEventListener('click', function () { openLb(idx); });
                frag.appendChild(item);
            })(i);
        }

        grid.appendChild(frag);
        loaded = end;
        updateUI();
    }

    function updateUI() {
        showing.textContent = loaded;
        var pct = Math.round((loaded / IMAGES.length) * 100);
        progressBar.style.width = pct + '%';
        progressTx.textContent = loaded + ' of ' + IMAGES.length + ' photos loaded';

        if (loaded >= IMAGES.length) {
            loadBtn.disabled = true;
            loadBtn.textContent = 'All photos loaded';
        }
    }

    window.loadMore = function () {
        loadBtn.disabled = true;
        loadBtn.textContent = 'Loading…';
        setTimeout(function () {
            renderBatch();
            if (loaded < IMAGES.length) {
                loadBtn.disabled = false;
                loadBtn.textContent = 'Load More Photos';
            }
        }, 50);
    };

    /* ── lightbox ── */
    var lbEl   = document.getElementById('gpLightbox');
    var lbImg  = document.getElementById('gpLightboxImg');
    var lbLoad = document.getElementById('gpLbLoading');
    var lbCtr  = document.getElementById('gpLbCounter');
    var lbThumbs = document.getElementById('gpLbThumbs');
    var thumbsBuilt = false;

    function buildThumbs() {
        if (thumbsBuilt) return;
        var shown = Math.min(9, IMAGES.length);
        for (var i = 0; i < shown; i++) {
            (function(i) {
                var t = document.createElement('div');
                t.className = 'gp-lb-thumb';
                t.dataset.idx = i;
                var ti = document.createElement('img');
                ti.src = IMAGES[i].thumb;
                ti.alt = '';
                ti.loading = 'lazy';
                t.appendChild(ti);
                t.addEventListener('click', function() { goLb(i); });
                lbThumbs.appendChild(t);
            })(i);
        }
        thumbsBuilt = true;
    }

    function updateThumbs() {
        lbThumbs.querySelectorAll('.gp-lb-thumb').forEach(function(t) {
            t.classList.toggle('active', parseInt(t.dataset.idx) === lbIdx);
        });
    }

    function goLb(idx) {
        lbIdx = idx;
        lbImg.classList.add('fading');
        lbLoad.style.display = 'flex';
        var src = IMAGES[lbIdx].large;
        lbCtr.textContent = (lbIdx + 1) + ' / ' + IMAGES.length;
        var tmp = new Image();
        tmp.onload = function () {
            lbImg.src = src;
            lbImg.classList.remove('fading');
            lbLoad.style.display = 'none';
        };
        tmp.onerror = function () {
            lbImg.src = src;
            lbImg.classList.remove('fading');
            lbLoad.style.display = 'none';
        };
        tmp.src = src;
        updateThumbs();
    }

    function openLb(idx) {
        buildThumbs();
        lbEl.classList.add('active');
        document.body.style.overflow = 'hidden';
        goLb(idx);
    }

    window.closeLightbox = function () {
        lbEl.classList.remove('active');
        document.body.style.overflow = '';
    };

    window.prevLb = function () { goLb((lbIdx - 1 + IMAGES.length) % IMAGES.length); };
    window.nextLb = function () { goLb((lbIdx + 1) % IMAGES.length); };

    document.addEventListener('keydown', function (e) {
        if (!lbEl.classList.contains('active')) return;
        if (e.key === 'ArrowRight') window.nextLb();
        else if (e.key === 'ArrowLeft') window.prevLb();
        else if (e.key === 'Escape') window.closeLightbox();
    });

    lbEl.addEventListener('click', function (e) {
        if (e.target === lbEl) window.closeLightbox();
    });

    /* Touch swipe */
    var touchX = null;
    lbEl.addEventListener('touchstart', function(e){ touchX = e.touches[0].clientX; }, {passive:true});
    lbEl.addEventListener('touchend', function(e){
        if (touchX === null) return;
        var diff = touchX - e.changedTouches[0].clientX;
        if (Math.abs(diff) > 40) { diff > 0 ? window.nextLb() : window.prevLb(); }
        touchX = null;
    }, {passive:true});

    /* ── nav helpers ── */
    window.openMobileNav = function () {
        var nav=document.getElementById('mobileNav'), ov=document.getElementById('navOverlay'), btn=document.getElementById('hamburgerBtn');
        nav.classList.add('open'); ov.style.display='block';
        requestAnimationFrame(function(){ ov.classList.add('visible'); });
        btn.classList.add('open'); btn.setAttribute('aria-expanded','true');
        document.body.style.overflow='hidden';
    };
    window.closeMobileNav = function () {
        var nav=document.getElementById('mobileNav'), ov=document.getElementById('navOverlay'), btn=document.getElementById('hamburgerBtn');
        nav.classList.remove('open'); ov.classList.remove('visible');
        btn.classList.remove('open'); btn.setAttribute('aria-expanded','false');
        document.body.style.overflow='';
        setTimeout(function(){ ov.style.display='none'; }, 300);
    };
    window.toggleMobileDropdown = function (trigger) {
        var btn=trigger.closest?trigger.closest('.mobile-dropdown-toggle'):trigger;
        var items=btn?btn.nextElementSibling:null;
        if(!btn||!items) return;
        var open=items.classList.contains('open');
        document.querySelectorAll('.mobile-dropdown-items.open').forEach(function(el){ el.classList.remove('open'); el.setAttribute('aria-hidden','true'); });
        document.querySelectorAll('.mobile-dropdown-toggle.open').forEach(function(el){ el.classList.remove('open'); el.setAttribute('aria-expanded','false'); });
        if(!open){ items.classList.add('open'); items.setAttribute('aria-hidden','false'); btn.classList.add('open'); btn.setAttribute('aria-expanded','true'); }
    };
    document.addEventListener('click', function(e){
        document.querySelectorAll('.lang-switcher.open').forEach(function(el){ if(!el.contains(e.target)) el.classList.remove('open'); });
    });

    /* ── initial load ── */
    renderBatch();

    /* ── featured video mute toggle ── */
    window.toggleGpMute = function () {
        var v = document.getElementById('gpFeaturedVideo');
        var btn = document.getElementById('gpMuteBtn');
        if (!v || !btn) return;
        v.muted = !v.muted;
        btn.textContent = v.muted ? '🔇 Unmute' : '🔊 Mute';
    };
}());
</script>

<!--Start of Tawk.to Script-->
<script type="text/javascript">
    var Tawk_API=Tawk_API||{},Tawk_LoadStart=new Date();
    (function(){ var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
    s1.async=true; s1.src='https://embed.tawk.to/69204852eba156195f5dae48/1jaj1l0r8';
    s1.charset='UTF-8'; s1.setAttribute('crossorigin','*'); s0.parentNode.insertBefore(s1,s0); })();
</script>
<!--End of Tawk.to Script-->
</body>
</html>
