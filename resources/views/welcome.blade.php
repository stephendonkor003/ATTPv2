<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <!-- Basic Meta -->
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ __('landing.page_title') }}</title>

    <meta name="description"
        content="ATTP (African Think Tank Platform Administration) is a pan-African knowledge and policy coordination platform supporting African Union institutions, think tanks, and development partners." />

    <meta name="keywords"
        content="ATTP, African Think Tank Platform, African Union policy, Africa governance, policy research Africa, AU think tanks, development policy Africa, public policy Africa, African institutions, research platform Africa" />

    <meta name="author" content="African Think Tank Platform Administration (ATTP)" />
    <meta name="robots" content="index, follow" />
    <meta name="language" content="en" />
    <meta name="theme-color" content="#006B3F" />

    <!-- Favicon -->
    <link rel="icon" href="{{ asset('assets/images/au3.jpg') }}" type="image/png" />

    <!-- Open Graph -->
    <meta property="og:type" content="website" />
    <meta property="og:title" content="ATTP ? African Think Tank Platform Administration" />
    <meta property="og:description"
        content="Strengthening Africa?s policy ecosystem through collaboration, research, and institutional coordination in support of the African Union." />
    <meta property="og:image" content="https://africathinktankplatform.africa/assets/images/au3.jpg" />
    <meta property="og:url" content="https://africathinktankplatform.africa/" />
    <meta property="og:site_name" content="ATTP" />

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="ATTP ? African Think Tank Platform Administration" />
    <meta name="twitter:description"
        content="A pan-African platform advancing policy research, governance, and institutional collaboration across Africa." />
    <meta name="twitter:image" content="https://africathinktankplatform.africa/assets/images/au.png" />
    <meta name="twitter:site" content="@ATTP_Africa" />

    <!-- Canonical URL -->
    <link rel="canonical" href="https://africathinktankplatform.africa/" />

    <!-- Fonts & Styles -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('assets/style.css') }}" />

    <!-- RTL CSS for Arabic -->
    {!! app()->getLocale() === 'ar' ? '<link rel="stylesheet" href="' . asset('assets/css/rtl.css') . '">' : '' !!}

    <!-- Schema.org Markup -->
    <script type="application/ld+json">
    {
      "@@context": "https://schema.org",
      "@type": "Organization",
      "name": "African Think Tank Platform Administration",
      "alternateName": "ATTP",
      "url": "https://africathinktankplatform.africa",
      "logo": "https://africathinktankplatform.africa/assets/images/au3.jpg",
      "description": "ATTP is a pan-African policy and research coordination platform supporting African Union institutions, think tanks, and development partners to strengthen governance and evidence-based decision-making across Africa.",
      "foundingLocation": {
        "@type": "Place",
        "name": "Africa"
      },
      "sameAs": [
        "https://www.linkedin.com/company/attp-africa",
        "https://twitter.com/ATTP_Africa"
      ]
    }
    </script>
</head>




<body>
    <!-- ====== MOBILE NAV OVERLAY ====== -->
    <div class="mobile-nav-overlay" id="navOverlay" onclick="closeMobileNav()"></div>

    <!-- ====== MOBILE NAV DRAWER ====== -->
    <nav class="mobile-nav" id="mobileNav">
        <div class="mobile-nav-header">
            <img src="{{ asset('assets/images/au.png') }}" alt="ATTP">
            <button class="mobile-nav-close" onclick="closeMobileNav()" aria-label="Close menu">&times;</button>
        </div>
        <a href="{{ route('landing.index') }}" onclick="closeMobileNav()">{{ __('navigation.home') }}</a>

        <button type="button" class="mobile-dropdown-toggle" onclick="toggleMobileDropdown(this)" aria-expanded="false">
            Programs <span class="mobile-dropdown-arrow">▾</span>
        </button>
        <div class="mobile-dropdown-items" aria-hidden="true">
            <a href="{{ route('events') }}" onclick="closeMobileNav()">{{ __('landing.events_webinars') }}</a>
            <a href="{{ route('careers.index') }}" onclick="closeMobileNav()">{{ __('navigation.careers') }}</a>
        </div>

        <button type="button" class="mobile-dropdown-toggle" onclick="toggleMobileDropdown(this)" aria-expanded="false">
            Analytics <span class="mobile-dropdown-arrow">▾</span>
        </button>
        <div class="mobile-dropdown-items" aria-hidden="true">
            <a href="{{ route('impact.map') }}" onclick="closeMobileNav()">{{ __('navigation.impact_map') }}</a>
            <a href="{{ route('world.indicators.performance') }}" onclick="closeMobileNav()">{{ __('navigation.world_indicators_performance') }}</a>
        </div>

        <a href="{{ route('news.index') }}" onclick="closeMobileNav()">News &amp; Updates</a>
        <a href="{{ route('gallery') }}" onclick="closeMobileNav()">Gallery</a>
        <a href="#contact" onclick="closeMobileNav()">{{ __('navigation.contact') }}</a>
        <div class="mobile-nav-actions">
            <a href="{{ route('public.procurement.index') }}" class="btn btn-primary">{{ __('landing.policy_programs') }}</a>
            <a href="{{ route('login') }}" class="btn btn-login">{{ __('navigation.login') }}</a>
        </div>
    </nav>

    <!-- ====== NAVBAR ====== -->
    <header class="navbar" role="banner">
        <a href="{{ route('landing.index') }}" class="logo" aria-label="ATTP Home">
            <img src="{{ asset('assets/images/au.png') }}" alt="African Think Tank Platform" class="logo-sm">
        </a>

        <nav class="nav-links" aria-label="Main navigation">
            <a href="{{ route('landing.index') }}" class="active">{{ __('navigation.home') }}</a>

            <div class="has-dropdown">
                <a href="#">Programs</a>
                <ul class="nav-dropdown">
                    <li><a href="{{ route('events') }}">{{ __('landing.events_webinars') }}</a></li>
                    <li><a href="{{ route('careers.index') }}">{{ __('navigation.careers') }}</a></li>
                </ul>
            </div>

            <div class="has-dropdown">
                <a href="#">Analytics</a>
                <ul class="nav-dropdown">
                    <li><a href="{{ route('impact.map') }}">{{ __('navigation.impact_map') }}</a></li>
                    <li><a href="{{ route('world.indicators.performance') }}">{{ __('navigation.world_indicators_performance') }}</a></li>
                </ul>
            </div>

            <a href="{{ route('news.index') }}">News &amp; Updates</a>
            <a href="{{ route('gallery') }}">Gallery</a>
            <a href="#contact">{{ __('navigation.contact') }}</a>
        </nav>

        <div class="nav-actions">
            <a href="{{ route('public.procurement.index') }}" class="btn btn-primary">
                {{ __('landing.policy_programs') }}
            </a>
            <a href="{{ route('login') }}" class="btn btn-login">{{ __('navigation.login') }}</a>
            <x-language-selector style="landing" />
        </div>

        <button class="hamburger-btn" id="hamburgerBtn" onclick="openMobileNav()" aria-label="Open menu" aria-expanded="false">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </header>

    <!-- ====== HERO SECTION ====== -->
    <section class="hero">
        <div class="overlay"></div>

        <div class="slider">
            {{-- Video slide — dominant, plays first --}}
            <div class="slide video-slide active">
                <video id="heroSlideVideo" muted playsinline preload="metadata" loop>
                    <source src="{{ asset('gallary/video.mp4') }}" type="video/mp4">
                </video>
            </div>
            <div class="slide" style="background-image: url('{{ asset('assets/images/au1.jpg') }}');"></div>
            <div class="slide" style="background-image: url('{{ asset('assets/images/au2.webp') }}');"></div>
            <div class="slide" style="background-image: url('{{ asset('assets/images/au3.jpg') }}');"></div>
        </div>

        {{-- Live-video badge shown only when video slide is active --}}
        <div class="hero-video-badge">
            <span></span> LIVE FOOTAGE
        </div>

        <div class="hero-content">
            <h1 id="typewriter" class="typing-text"></h1>
            <p id="hero-subtitle" class="hero-subtitle-text"></p>

            <a href="{{ route('public.procurement.index') }}" class="cta-btn">
                {{ __('landing.hero_cta') }}
            </a>
        </div>
    </section>

    <div class="section-stripe"></div>

    <!-- ====== SYSTEM PROCESS FLOW ====== -->
    <section id="process" class="process-section">
        <h2>{{ __('landing.process_title') }}</h2>
        <p>
            {{ __('landing.process_subtitle') }}
        </p>

        <div class="process-flow">

            <div class="flow-card">
                <span>1</span>
                <h3>{{ __('landing.step1_title') }}</h3>
                <p>
                    {{ __('landing.step1_desc') }}
                </p>
            </div>

            <div class="flow-card">
                <span>2</span>
                <h3>{{ __('landing.step2_title') }}</h3>
                <p>
                    {{ __('landing.step2_desc') }}
                </p>
            </div>

            <div class="flow-card">
                <span>3</span>
                <h3>{{ __('landing.step3_title') }}</h3>
                <p>
                    {{ __('landing.step3_desc') }}
                </p>
            </div>

            <div class="flow-card">
                <span>4</span>
                <h3>{{ __('landing.step4_title') }}</h3>
                <p>
                    {{ __('landing.step4_desc') }}
                </p>
            </div>

            <div class="flow-card">
                <span>5</span>
                <h3>{{ __('landing.step5_title') }}</h3>
                <p>
                    {{ __('landing.step5_desc') }}
                </p>
            </div>

            <div class="flow-card">
                <span>6</span>
                <h3>{{ __('landing.step6_title') }}</h3>
                <p>
                    {{ __('landing.step6_desc') }}
                </p>
            </div>

            <div class="flow-card">
                <span>7</span>
                <h3>{{ __('landing.step7_title') }}</h3>
                <p>
                    {{ __('landing.step7_desc') }}
                </p>
            </div>

            <div class="flow-card">
                <span>8</span>
                <h3>{{ __('landing.step8_title') }}</h3>
                <p>
                    {{ __('landing.step8_desc') }}
                </p>
            </div>

        </div>
    </section>
    <section id="annoucements" class="process-section">
        <h2>{{ __('landing.grants_title') }}</h2>
        <p>
            {{ __('landing.grants_subtitle') }}
        </p>

        <div class="annoucment">

            <div class="flow-card">
                <span>1</span>
                <h3>English (EN)</h3>
                <p>
                    {{ __('landing.download_en') }}
                </p>
                <a href="{{ asset('assets/award/ATTP_Award_Announcement_EN.pdf') }}" class="btn-view"
                    target="_blank" rel="noopener noreferrer">
                    {{ __('landing.download_version', ['language' => 'English']) }}
                </a>
            </div>

            <div class="flow-card">
                <span>2</span>
                <h3>French (FR)</h3>
                <p>
                    {{ __('landing.download_fr') }}
                </p>
                <a href="{{ asset('assets/award/ATTP_Award_Announcement_FR.pdf') }}" class="btn-view"
                    target="_blank" rel="noopener noreferrer">
                    {{ __('landing.download_version', ['language' => 'French']) }}
                </a>
            </div>

            <div class="flow-card">
                <span>3</span>
                <h3>Arabic (AR)</h3>
                <p>
                    {{ __('landing.download_ar') }}
                </p>
                <a href="{{ asset('assets/award/ATTP_Award_Announcement_AR.pdf') }}" class="btn-view"
                    target="_blank" rel="noopener noreferrer">
                    {{ __('landing.download_version', ['language' => 'Arabic']) }}
                </a>
            </div>

            <div class="flow-card">
                <span>4</span>
                <h3>Portuguese (PT)</h3>
                <p>
                    {{ __('landing.download_pt') }}
                </p>
                <a href="{{ asset('assets/award/ATTP_Award_Announcement_PT.pdf') }}" class="btn-view"
                    target="_blank" rel="noopener noreferrer">
                    {{ __('landing.download_version', ['language' => 'Portuguese']) }}
                </a>
            </div>

            <div class="flow-card">
                <span>5</span>
                <h3>Spanish (ES)</h3>
                <p>
                    {{ __('landing.download_es') }}
                </p>
                <a href="{{ asset('assets/award/ATTP_Award_Announcement_ES.pdf') }}" class="btn-view"
                    target="_blank" rel="noopener noreferrer">
                    {{ __('landing.download_version', ['language' => 'Spanish']) }}
                </a>
            </div>

            <div class="flow-card">
                <span>6</span>
                <h3>Swahili (SW)</h3>
                <p>
                    {{ __('landing.download_sw') }}
                </p>
                <a href="{{ asset('assets/award/ATTP_Award_Announcement_SW.pdf') }}" class="btn-view"
                    target="_blank" rel="noopener noreferrer">
                    {{ __('landing.download_version', ['language' => 'Swahili']) }}
                </a>
            </div>

        </div>


    </section>





    <!-- ====== CUSTOMIZATION SECTION ====== -->
    <section id="customization" class="customization-section">
        <div class="content">
            <h2>{{ __('landing.governance_title') }}</h2>
            <p>
                {{ __('landing.governance_subtitle') }}
            </p>

            <ul>
                <li>{{ __('landing.governance_item1') }}</li>
                <li>{{ __('landing.governance_item2') }}</li>
                <li>{{ __('landing.governance_item3') }}</li>
                <li>{{ __('landing.governance_item4') }}</li>
                <li>{{ __('landing.governance_item5') }}</li>
            </ul>
        </div>
    </section>



    <!-- ====== GALLERY SECTION ====== -->
    @if(!empty($galleryImages))
    <section id="gallery" class="gallery-section">
        <div class="gallery-section-header">
            <h2>Our Gallery</h2>
            <p>Moments from our events, webinars, and activities across Africa</p>
            <a href="{{ route('gallery') }}" class="btn-gallery-all">View All Photos</a>
        </div>
        <div class="gallery-scroller-wrapper">
            <div class="gallery-scroller">
                @foreach($galleryImages as $i => $img)
                <div class="gallery-scroller-item" onclick="openMainLightbox({{ $i }})">
                    <img src="{{ asset('gallary/' . rawurlencode($img)) }}" alt="ATTP Gallery" loading="lazy">
                </div>
                @endforeach
                @foreach($galleryImages as $i => $img)
                <div class="gallery-scroller-item" onclick="openMainLightbox({{ $i }})">
                    <img src="{{ asset('gallary/' . rawurlencode($img)) }}" alt="ATTP Gallery" loading="lazy">
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <div class="gallery-lightbox" id="mainGalleryLightbox">
        <div class="gallery-lightbox-overlay" onclick="closeMainLightbox()"></div>
        <div class="gallery-lightbox-inner">
            <button class="gallery-lightbox-btn" onclick="prevMainImage()">&#8249;</button>
            <div class="gallery-lightbox-img-wrap">
                <button class="gallery-lightbox-close" onclick="closeMainLightbox()">&times;</button>
                <img id="mainLightboxImg" src="" alt="Gallery Image">
                <div class="gallery-lightbox-counter" id="mainLightboxCounter"></div>
            </div>
            <button class="gallery-lightbox-btn" onclick="nextMainImage()">&#8250;</button>
        </div>
    </div>
    @endif

    <!-- ====== VIDEO FEATURE SECTION ====== -->
    <section class="video-feature-section">
        <div class="video-feature-inner">
            <div class="video-feature-header">
                <h2>Watch Our Story</h2>
                <p>See ATTP's impact and activities across Africa in action</p>
            </div>
            <div class="video-feature-player" id="vfPlayer">
                <video id="vfVideo" preload="none" controls poster="{{ asset('assets/images/au3.jpg') }}">
                    <source src="{{ asset('gallary/video.mp4') }}" type="video/mp4">
                </video>
                <div class="vf-overlay-btn" id="vfOverlay" onclick="startVfVideo()">
                    <div class="vf-play-icon">
                        <svg viewBox="0 0 24 24"><polygon points="5,3 19,12 5,21"/></svg>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ====== FOOTER ====== -->
    <footer id="contact" class="footer" role="contentinfo">
        <div class="footer-content">

            <div class="footer-logo">
                <h3>ATTP<span> Administration</span></h3>
                <p>{{ __('landing.footer_description') }}</p>
            </div>

            <div class="footer-links">
                <h4>{{ __('landing.footer_links_title') }}</h4>
                <a href="{{ route('landing.index') }}">{{ __('landing.footer_link_home') }}</a>
                <a href="#process">{{ __('landing.footer_link_process') }}</a>
                <a href="#gallery">Gallery</a>
                <a href="#customization">{{ __('landing.footer_link_oversight') }}</a>
                <a href="#contact">{{ __('navigation.contact') }}</a>
                <a href="{{ route('careers.index') }}">{{ __('navigation.careers') }}</a>
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



    <script src="{{ asset('assets/script.js') }}"></script>

    <script>
    function startVfVideo() {
        var vid = document.getElementById('vfVideo');
        var overlay = document.getElementById('vfOverlay');
        if (!vid) return;
        overlay.style.display = 'none';
        vid.load();
        vid.play().catch(function () { overlay.style.display = 'flex'; });
        vid.addEventListener('ended', function () { overlay.style.display = 'flex'; });
    }
    </script>

    @if(!empty($galleryImages))
    <script>
    (function () {
        var mainImgs = @json($galleryImages);
        var mainIdx = 0;

        window.openMainLightbox = function (i) {
            mainIdx = i;
            updateMain();
            document.getElementById('mainGalleryLightbox').classList.add('active');
            document.body.style.overflow = 'hidden';
        };
        window.closeMainLightbox = function () {
            document.getElementById('mainGalleryLightbox').classList.remove('active');
            document.body.style.overflow = '';
        };
        window.prevMainImage = function () {
            mainIdx = (mainIdx - 1 + mainImgs.length) % mainImgs.length;
            updateMain();
        };
        window.nextMainImage = function () {
            mainIdx = (mainIdx + 1) % mainImgs.length;
            updateMain();
        };
        function updateMain() {
            document.getElementById('mainLightboxImg').src =
                '{{ rtrim(asset("gallary"), "/") }}/' + encodeURIComponent(mainImgs[mainIdx]);
            document.getElementById('mainLightboxCounter').textContent =
                (mainIdx + 1) + ' / ' + mainImgs.length;
        }
        document.addEventListener('keydown', function (e) {
            if (!document.getElementById('mainGalleryLightbox').classList.contains('active')) return;
            if (e.key === 'ArrowRight') window.nextMainImage();
            else if (e.key === 'ArrowLeft') window.prevMainImage();
            else if (e.key === 'Escape') window.closeMainLightbox();
        });
    }());
    </script>
    @endif

    <script>
        function openMobileNav() {
            const nav = document.getElementById('mobileNav');
            const overlay = document.getElementById('navOverlay');
            const btn = document.getElementById('hamburgerBtn');
            nav.classList.add('open');
            overlay.style.display = 'block';
            requestAnimationFrame(() => overlay.classList.add('visible'));
            btn.classList.add('open');
            btn.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
        }

        function closeMobileNav() {
            const nav = document.getElementById('mobileNav');
            const overlay = document.getElementById('navOverlay');
            const btn = document.getElementById('hamburgerBtn');
            nav.classList.remove('open');
            overlay.classList.remove('visible');
            btn.classList.remove('open');
            btn.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
            setTimeout(() => { overlay.style.display = 'none'; }, 300);
        }

        function toggleMobileDropdown(trigger) {
            const btn = trigger.closest('.mobile-dropdown-toggle');
            const items = btn ? btn.nextElementSibling : null;

            if (!btn || !items || !items.classList.contains('mobile-dropdown-items')) {
                return;
            }

            const isOpen = items.classList.contains('open');

            document.querySelectorAll('.mobile-dropdown-items.open').forEach(el => {
                el.classList.remove('open');
                el.setAttribute('aria-hidden', 'true');
            });

            document.querySelectorAll('.mobile-dropdown-toggle.open').forEach(el => {
                el.classList.remove('open');
                el.setAttribute('aria-expanded', 'false');
            });

            if (!isOpen) {
                items.classList.add('open');
                items.setAttribute('aria-hidden', 'false');
                btn.classList.add('open');
                btn.setAttribute('aria-expanded', 'true');
            }
        }

        // Close lang-switcher on outside click
        document.addEventListener('click', function(e) {
            document.querySelectorAll('.lang-switcher.open').forEach(function(el) {
                if (!el.contains(e.target)) el.classList.remove('open');
            });
        });

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeMobileNav();
                document.querySelectorAll('.lang-switcher.open').forEach(el => el.classList.remove('open'));
            }
        });
    </script>
    <!--Start of Tawk.to Script-->
    <!--Start of Tawk.to Script-->
    <!--Start of Tawk.to Script-->
    <script type="text/javascript">
        var Tawk_API = Tawk_API || {},
            Tawk_LoadStart = new Date();
        (function() {
            var s1 = document.createElement("script"),
                s0 = document.getElementsByTagName("script")[0];
            s1.async = true;
            s1.src = 'https://embed.tawk.to/69204852eba156195f5dae48/1jaj1l0r8';
            s1.charset = 'UTF-8';
            s1.setAttribute('crossorigin', '*');
            s0.parentNode.insertBefore(s1, s0);
        })();
    </script>
    <!--End of Tawk.to Script-->
    <!--End of Tawk.to Script-->
    <!--End of Tawk.to Script-->
</body>

</html>
