<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="theme-color" content="#006B3F" />

    <title>Events & Webinars | ATTP – African Think Tank Platform</title>

    <meta name="description" content="Engage with African Union institutions and policy leaders through ATTP-hosted webinars and strategic events on governance, development, and policy innovation." />
    <meta name="author" content="ATTP Secretariat" />
    <meta name="robots" content="index, follow" />

    <link rel="icon" href="{{ asset('assets/images/au3.jpg') }}" type="image/png" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('assets/style.css') }}" />

    <style>
        :root {
            --au-green:      #006B3F;
            --au-green-dark: #004d2e;
            --au-green-light:#009A44;
            --gold:          #fbbc05;
            --orange:        #e16435;
            --light:         #f0f4f0;
        }

        body {
            font-family: "Inter", sans-serif;
            background: var(--light);
            color: #333;
            margin: 0;
        }

        /* ===== PAGE HERO ===== */
        .events-hero {
            position: relative;
            background: url('{{ asset('assets/images/au3.jpg') }}') center/cover no-repeat;
            height: 380px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            text-align: center;
        }

        .events-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(rgba(0, 77, 46, 0.88), rgba(0, 0, 0, 0.65));
        }

        .events-hero .hero-inner {
            position: relative;
            z-index: 2;
            max-width: 800px;
            padding: 0 1.5rem;
        }

        .events-hero h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gold);
            margin-bottom: 0.8rem;
            text-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }

        .events-hero p {
            font-size: 1.05rem;
            line-height: 1.7;
            color: rgba(255,255,255,0.90);
        }

        /* ===== FILTER BAR ===== */
        .filter-bar {
            background: #fff;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.10);
            border-radius: 14px;
            max-width: 800px;
            margin: -50px auto 3rem;
            padding: 1.4rem 1.8rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 10;
        }

        .filter-bar input {
            flex: 1;
            min-width: 220px;
            padding: 0.8rem 1rem;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            transition: border-color 0.25s;
        }

        .filter-bar input:focus {
            outline: none;
            border-color: var(--au-green);
            box-shadow: 0 0 0 3px rgba(0,107,63,0.10);
        }

        .filter-bar button {
            background: var(--au-green);
            color: #fff;
            border: none;
            padding: 0.85rem 1.8rem;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: background 0.25s;
            white-space: nowrap;
        }

        .filter-bar button:hover {
            background: var(--au-green-dark);
        }

        /* ===== EVENTS GRID ===== */
        .events-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto 5rem;
            padding: 0 2rem;
        }

        .event-card {
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.09);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-top: 4px solid var(--au-green);
            display: flex;
            flex-direction: column;
        }

        .event-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 10px 28px rgba(0, 107, 63, 0.16);
        }

        .event-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .event-card .card-body {
            padding: 1.3rem 1.4rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .event-card h4 {
            color: var(--au-green);
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.4;
            margin: 0;
        }

        .event-date {
            color: var(--au-green-light);
            font-weight: 600;
            font-size: 0.85rem;
        }

        .event-card p {
            color: #555;
            font-size: 0.9rem;
            line-height: 1.6;
            margin: 0;
        }

        .event-card .card-links {
            margin-top: auto;
            padding-top: 0.8rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .btn-view {
            display: inline-block;
            background: var(--au-green);
            color: #fff;
            padding: 0.5rem 1.1rem;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            transition: background 0.25s;
            width: fit-content;
        }

        .btn-view:hover {
            background: var(--au-green-light);
            color: #fff;
        }

        /* ===== MODAL ===== */
        .modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.70);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .modal.active {
            display: flex;
        }

        .modal-box {
            background: #fff;
            padding: 2.5rem 2rem;
            border-radius: 16px;
            max-width: 560px;
            width: 90%;
            text-align: center;
            position: relative;
            border-top: 4px solid var(--au-green);
        }

        .modal-box h3 {
            color: var(--au-green);
            margin-bottom: 0.8rem;
        }

        .close-btn {
            position: absolute;
            top: 10px;
            right: 14px;
            background: none;
            border: none;
            font-size: 1.6rem;
            cursor: pointer;
            color: var(--au-green);
            line-height: 1;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .events-container { grid-template-columns: repeat(2, 1fr); }
            .events-hero h1 { font-size: 2rem; }
        }

        @media (max-width: 600px) {
            .events-container {
                grid-template-columns: 1fr;
                padding: 0 1rem;
            }

            .filter-bar {
                margin: -40px 1rem 2rem;
                padding: 1rem;
            }

            .events-hero h1 { font-size: 1.7rem; }
            .events-hero { height: 300px; }
        }
    </style>
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

        <button class="mobile-dropdown-toggle" onclick="toggleMobileDropdown(this)">
            Programs <span class="mobile-dropdown-arrow">▾</span>
        </button>
        <div class="mobile-dropdown-items">
            <a href="{{ route('events') }}" class="active" onclick="closeMobileNav()">{{ __('landing.events_webinars') }}</a>
            <a href="{{ route('careers.index') }}" onclick="closeMobileNav()">{{ __('navigation.careers') }}</a>
        </div>

        <button class="mobile-dropdown-toggle" onclick="toggleMobileDropdown(this)">
            Analytics <span class="mobile-dropdown-arrow">▾</span>
        </button>
        <div class="mobile-dropdown-items">
            <a href="{{ route('impact.map') }}" onclick="closeMobileNav()">{{ __('navigation.impact_map') }}</a>
            <a href="{{ route('world.indicators.performance') }}" onclick="closeMobileNav()">{{ __('navigation.world_indicators_performance') }}</a>
        </div>

        <a href="{{ route('news.index') }}" onclick="closeMobileNav()">News &amp; Updates</a>
        <a href="#contact" onclick="closeMobileNav()">{{ __('navigation.contact') }}</a>
        <div class="mobile-nav-actions">
            <a href="{{ route('public.procurement.index') }}" class="btn btn-primary">{{ __('landing.policy_programs') }}</a>
            <a href="{{ route('login') }}" class="btn btn-login">{{ __('navigation.login') }}</a>
            <x-language-selector style="events" />
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
                <a href="#" class="active">Programs</a>
                <ul class="nav-dropdown">
                    <li><a href="{{ route('events') }}" class="active">{{ __('landing.events_webinars') }}</a></li>
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
            <span></span><span></span><span></span>
        </button>
    </header>

    <!-- ====== HERO ====== -->
    <section class="events-hero">
        <div class="hero-inner">
            <h1>Webinars &amp; Strategic Events</h1>
            <p>
                Engage with African Union institutions, policy leaders, and think tanks through
                ATTP-hosted webinars and strategic events focused on governance, development,
                policy innovation, and evidence-based decision-making across Africa.
            </p>
        </div>
    </section>

    <!-- ====== FILTER BAR ====== -->
    <div class="filter-bar">
        <input type="text" placeholder="Search events by keyword..." id="searchInput" />
        <button onclick="filterEvents()">Search Events</button>
    </div>

    <!-- ====== EVENTS GRID ====== -->
    <section class="events-container" id="eventsContainer">

        <div class="event-card">
            <img src="{{ asset('assets/images/au1.jpg') }}" alt="ATTP Launch Webinar">
            <div class="card-body">
                <p class="event-date">July 24, 2025 &mdash; Status: Completed</p>
                <h4>Launch of ATTP Call for Proposals</h4>
                <p>
                    This webinar introduced the ATTP Call for Proposals and provided an overview of the eligibility
                    criteria, submission guidelines, and key objectives of the ATTP initiative.
                </p>
                <div class="card-links">
                    <a href="https://drive.google.com/file/u/0/d/1cPV1APFR0zB5rSvNL9PvISpNXHY9LcDr/view?usp=sharing&pli=1"
                        target="_blank" rel="noopener noreferrer" class="btn-view">View Recording</a>
                </div>
            </div>
        </div>

        <div class="event-card">
            <img src="{{ asset('assets/images/au2.webp') }}" alt="Follow-up Webinar August 5">
            <div class="card-body">
                <p class="event-date">August 5, 2025 &mdash; 2:00 pm EAT | Completed</p>
                <h4>Follow-up Webinar: Consortium Application Guidance</h4>
                <p>
                    This webinar provided an overview of the ATTP Consortium Application Form and guidance on navigating
                    the ATTP website, clarifying eligibility requirements and consortium formation.
                </p>
                <div class="card-links">
                    <a href="https://drive.google.com/file/d/1gSqmT-U2guRVa7FNSfdvLp7RHS45L0Za/view" target="_blank"
                        rel="noopener noreferrer" class="btn-view">View Recording</a>
                </div>
            </div>
        </div>

        <div class="event-card">
            <img src="{{ asset('assets/images/au3.jpg') }}" alt="Follow-up Webinar August 26">
            <div class="card-body">
                <p class="event-date">August 26, 2025 &mdash; 2:00 pm EAT | Completed</p>
                <h4>Follow-up Webinar: Budget, Templates &amp; Commitment Letter</h4>
                <p>
                    This session provided additional guidance on proposal development, focusing on the budget and
                    timeline template, CV Template, Past Research and Experience Template, and the Commitment Letter.
                </p>
                <div class="card-links">
                    <a href="https://drive.google.com/file/d/1EzbZ7jbsf6I3FTM1urC9RG_Onld-EGjF/view" target="_blank"
                        rel="noopener noreferrer" class="btn-view">View Recording</a>
                </div>
            </div>
        </div>

        <div class="event-card">
            <img src="{{ asset('assets/images/au4.jpg') }}" alt="Follow-up Webinar September 8">
            <div class="card-body">
                <p class="event-date">September 8, 2025 &mdash; 2:00 pm EAT | Completed</p>
                <h4>Follow-up Webinar: Applicant Q&amp;A Session</h4>
                <p>
                    This webinar was conducted to address key applicant questions and provide additional clarification
                    to support submission readiness.
                </p>
                <div class="card-links">
                    <a href="https://drive.google.com/file/d/1LQzkyAG6ITBIRZqzLK7jEiyxD45MpsVT/view" target="_blank"
                        rel="noopener noreferrer" class="btn-view">View Recording</a>
                </div>
            </div>
        </div>

        <div class="event-card">
            <img src="{{ asset('assets/images/au5.jpg') }}" alt="Final Follow-up Webinar">
            <div class="card-body">
                <p class="event-date">September 23, 2025 &mdash; 2:00 pm EAT | Completed</p>
                <h4>Final Follow-up Webinar: Submission Deadline Preparation</h4>
                <p>
                    This webinar focused on addressing final questions and preparing applicants
                    for the submission deadline on September 24, 2025.
                </p>
            </div>
        </div>

        <div class="event-card">
            <img src="{{ asset('assets/images/au6.jpg') }}" alt="How to Apply">
            <div class="card-body">
                <p class="event-date">Step-by-Step Application Guide</p>
                <h4>Watch Our Guide on How to Apply</h4>
                <p>Video walkthroughs available in English and French to help you complete your application.</p>
                <div class="card-links">
                    <a href="https://drive.google.com/file/d/1oFGoh93O1MnoB9bdBhQHaWn4mZlHu5ra/view"
                        target="_blank" rel="noopener noreferrer" class="btn-view">
                        How to Apply &mdash; English
                    </a>
                    <a href="https://drive.google.com/file/d/19bb8Gx5SICNeZKpFAP2XUPDZH9lre-9I/view"
                        target="_blank" rel="noopener noreferrer" class="btn-view">
                        How to Apply &mdash; French
                    </a>
                </div>
            </div>
        </div>

        <div class="event-card">
            <img src="{{ asset('assets/images/au7.jpg') }}" alt="Clarification Questions">
            <div class="card-body">
                <p class="event-date">Ongoing Resource</p>
                <h4>Response to Clarification Questions</h4>
                <p>
                    For all clarification questions submitted, please refer to the updated
                    Frequently Asked Questions (FAQ) page.
                </p>
                <div class="card-links">
                    <a href="https://africathinktankplatform.africa/faq" target="_blank" rel="noopener noreferrer"
                        class="btn-view">
                        Visit ATTP FAQ Page
                    </a>
                </div>
            </div>
        </div>

    </section>

    <!-- ====== FOOTER ====== -->
    <footer id="contact" class="footer" role="contentinfo">
        <div class="footer-content">
            <div class="footer-logo">
                <h3>ATTP<span> Administration</span></h3>
                <p>
                    African Think Tank Platform Administration &mdash; supporting African Union
                    institutions through centralized governance, policy coordination,
                    and strategic oversight of programs and funded initiatives.
                </p>
            </div>

            <div class="footer-links">
                <h4>Quick Links</h4>
                <a href="{{ route('landing.index') }}">Home</a>
                <a href="{{ route('events') }}">Events &amp; Webinars</a>
                <a href="{{ route('careers.index') }}">Careers</a>
                <a href="{{ route('public.procurement.index') }}">Procurement</a>
                <a href="#contact">Contact</a>
            </div>

            <div class="footer-contact">
                <h4>Contact</h4>
                <p>Email: attpinfo@africanunion.org</p>
                <p>&copy; {{ date('Y') }} African Think Tank Platform Administration (ATTP)</p>
            </div>
        </div>

        <div class="footer-bottom">
            <p>Supporting African Union policy coordination, governance reform, and evidence-based decision-making across the continent.</p>
        </div>
    </footer>

    <script>
        function filterEvents() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            document.querySelectorAll('.event-card').forEach(card => {
                card.style.display = card.innerText.toLowerCase().includes(search) ? 'flex' : 'none';
            });
        }

        document.getElementById('searchInput').addEventListener('keyup', function(e) {
            if (e.key === 'Enter') filterEvents();
        });

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

        function toggleMobileDropdown(btn) {
            const items = btn.nextElementSibling;
            const isOpen = items.classList.contains('open');
            document.querySelectorAll('.mobile-dropdown-items.open').forEach(el => el.classList.remove('open'));
            document.querySelectorAll('.mobile-dropdown-toggle.open').forEach(el => el.classList.remove('open'));
            if (!isOpen) { items.classList.add('open'); btn.classList.add('open'); }
        }

        document.addEventListener('click', function(e) {
            document.querySelectorAll('.lang-switcher.open').forEach(function(el) {
                if (!el.contains(e.target)) el.classList.remove('open');
            });
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeMobileNav();
                document.querySelectorAll('.lang-switcher.open').forEach(el => el.classList.remove('open'));
            }
        });
    </script>

    <script type="text/javascript">
        var Tawk_API = Tawk_API || {}, Tawk_LoadStart = new Date();
        (function() {
            var s1 = document.createElement("script"), s0 = document.getElementsByTagName("script")[0];
            s1.async = true;
            s1.src = 'https://embed.tawk.to/69204852eba156195f5dae48/1jaj1l0r8';
            s1.charset = 'UTF-8';
            s1.setAttribute('crossorigin', '*');
            s0.parentNode.insertBefore(s1, s0);
        })();
    </script>

</body>
</html>
