<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <title>Careers at ATTP – Join Africa’s Procurement Transformation</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- SEO --}}
    <meta name="description"
        content="Explore career opportunities at ATTP and join Africa’s digital procurement transformation.">

    {{-- Google Font --}}
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

    {{-- Base Styles --}}
    <link rel="stylesheet" href="{{ asset('assets/style.css') }}">

    <style>
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: var(--light);
            color: var(--text-dark);
        }

        /* =====================================================
           HERO
        ===================================================== */
        .career-hero {
            min-height: 380px;
            background: url('{{ asset('assets/images/au3.jpg') }}') center/cover no-repeat;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem;
        }

        .career-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(rgba(0, 77, 46, .86),
                    rgba(0, 107, 63, .72));
        }

        .career-hero-content {
            position: relative;
            z-index: 2;
            max-width: 800px;
            color: #fff;
        }

        .career-hero h1 {
            font-size: 2.7rem;
            color: var(--gold);
            margin-bottom: .7rem;
        }

        /* =====================================================
           FILTER BAR
        ===================================================== */
        .filter-bar {
            max-width: 900px;
            margin: -45px auto 3rem;
            background: #fff;
            padding: 1.6rem;
            border-radius: 8px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, .12);
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .filter-bar input {
            flex: 1;
            max-width: 360px;
            padding: .9rem 1.1rem;
            border-radius: 8px;
            border: 1px solid #ccc;
        }

        .filter-bar button {
            padding: .9rem 2rem;
            border-radius: 8px;
            border: none;
            background: var(--au-green);
            color: #fff;
            font-weight: 600;
            cursor: pointer;
        }

        .filter-bar button:hover {
            background: var(--au-green-hover);
        }

        /* =====================================================
           VACANCY CARDS
        ===================================================== */
        .vacancies {
            max-width: 1200px;
            margin: 0 auto 5rem;
            padding: 0 6%;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem;
        }

        .vacancy-card {
            position: relative;
            height: 360px;
            border-radius: 8px;
            overflow: hidden;
            background: url('{{ asset('assets/images/au3.jpg') }}') center/cover no-repeat;
            box-shadow: 0 18px 40px rgba(0, 0, 0, .18);
        }

        .vacancy-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(to top,
                    rgba(0, 0, 0, .9),
                    rgba(0, 0, 0, .35));
        }

        .vacancy-content {
            position: absolute;
            inset: 0;
            z-index: 2;
            padding: 1.7rem;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            color: #fff;
        }

        .vacancy-content h4 {
            color: var(--gold);
            margin-bottom: .4rem;
        }

        .employment-badge {
            display: inline-block;
            background: rgba(255, 255, 255, .15);
            border: 1px solid rgba(255, 255, 255, .3);
            padding: .25rem .75rem;
            border-radius: 20px;
            font-size: .75rem;
            font-weight: 600;
            margin-bottom: .6rem;
            width: fit-content;
        }

        .vacancy-summary {
            font-size: .9rem;
            line-height: 1.5;
            max-height: 0;
            opacity: 0;
            overflow: hidden;
            transition: all .4s ease;
        }

        .vacancy-actions {
            margin-top: 1rem;
            opacity: 0;
            transform: translateY(10px);
            transition: all .4s ease;
        }

        .vacancy-card:hover .vacancy-summary {
            max-height: 120px;
            opacity: 1;
        }

        .vacancy-card:hover .vacancy-actions {
            opacity: 1;
            transform: translateY(0);
        }

        .vacancy-content > button,
        .vacancy-actions button,
        .apply-btn {
            background: var(--au-green);
            border: none;
            color: #fff;
            padding: .6rem 1.4rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        .vacancy-content > button {
            margin-top: 1rem;
            width: fit-content;
        }

        .vacancy-content > button:hover,
        .vacancy-actions button:hover,
        .apply-btn:hover {
            background: var(--au-green-hover);
        }

        /* =====================================================
           MODAL (LARGE)
        ===================================================== */
        .modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            padding: 1.5rem;
        }

        .modal.active {
            display: flex;
        }

        .modal-box {
            background: #fff;
            width: 100%;
            max-width: 1100px;
            border-radius: 8px;
            padding: 2.5rem;
            display: grid;
            grid-template-columns: 1.3fr 1fr;
            gap: 2.5rem;
            max-height: 92vh;
            overflow-y: auto;
            position: relative;
        }

        .close-btn {
            position: absolute;
            top: 14px;
            right: 18px;
            font-size: 1.8rem;
            border: none;
            background: none;
            cursor: pointer;
            color: var(--au-green);
        }

        .modal-box input,
        .modal-box input[type="file"] {
            width: 100%;
            padding: .85rem;
            border-radius: 8px;
            border: 1px solid #ccc;
            margin-bottom: 1rem;
        }

        @media(max-width: 900px) {
            .modal-box {
                grid-template-columns: 1fr;
            }

            .career-hero h1 {
                font-size: 2.2rem;
            }
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
            <a href="{{ route('landing.index') }}">{{ __('navigation.home') }}</a>

            <div class="has-dropdown">
                <a href="#" class="active">Programs</a>
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
            <a href="#contact">{{ __('navigation.contact') }}</a>
        </nav>

        <div class="nav-actions">
            <a href="{{ route('public.procurement.index') }}" class="btn btn-primary">
                {{ __('landing.policy_programs') }}
            </a>
            <a href="{{ route('login') }}" class="btn btn-login">{{ __('navigation.login') }}</a>
            <x-language-selector style="careers" />
        </div>

        <button class="hamburger-btn" id="hamburgerBtn" onclick="openMobileNav()" aria-label="Open menu" aria-expanded="false">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </header>

    {{-- HERO --}}
    <section class="career-hero">
        <div class="career-hero-content">
            <h1>Build Your Career With ATTP</h1>
            <p>
                Join a mission-driven team strengthening African policy research,
                evidence-based decision-making, and continental collaboration.
            </p>
        </div>
    </section>
    {{-- FILTER --}}
    <div class="filter-bar" id="vacancies">
        <input type="text" id="searchInput" placeholder="Search available vacant positions...">
        <button onclick="filterVacancies()">Search</button>
    </div>

    {{-- VACANCIES --}}
    <section class="vacancies" id="vacanciesContainer">

        @forelse($vacancies as $vacancy)
            <div class="vacancy-card">
                <div class="vacancy-content">

                    @if ($vacancy->position)
                        <h4>{{ $vacancy->position->title }}</h4>

                        <span class="employment-badge">
                            {{ ucfirst($vacancy->position->employment_type) }}
                        </span>

                        <div class="vacancy-summary">
                            {{ \Illuminate\Support\Str::limit(strip_tags($vacancy->position->description), 120) }}
                        </div>
                    @endif

                    @if ($vacancy->position)
                        <button data-id="{{ $vacancy->id }}" data-title="{{ $vacancy->position->title }}"
                            data-employment="{{ ucfirst($vacancy->position->employment_type) }}"
                            data-description="{{ strip_tags($vacancy->position->description) }}"
                            onclick="openApplyModal(this)">
                            View Details
                        </button>
                    @endif


                    {{-- <h4>{{ $vacancy->title }}</h4>

                    <span class="employment-badge">
                        {{ ucfirst($vacancy->employment_type) }}
                    </span>

                    <div class="vacancy-summary">
                        {{ \Illuminate\Support\Str::limit(strip_tags($vacancy->description), 120) }}
                    </div>

                    <div class="vacancy-actions">
                        <button data-id="{{ $vacancy->id }}" data-title="{{ $vacancy->title }}"
                            data-employment="{{ ucfirst($vacancy->employment_type) }}"
                            data-description="{{ strip_tags($vacancy->description) }}" onclick="openApplyModal(this)">
                            View Details
                        </button>
                    </div> --}}
                </div>
            </div>
        @empty
            <p style="grid-column:1/-1;text-align:center;">
                No vacancies available at the moment.
            </p>
        @endforelse


    </section>

    {{-- MODAL --}}
    <div class="modal" id="applyModal">
        <div class="modal-box">
            <button class="close-btn" onclick="closeApplyModal()">×</button>

            {{-- LEFT: JOB DETAILS --}}
            <div>
                <h2 id="modalTitle"></h2>

                <p style="margin-top:.5rem;">
                    <strong>Employment Type:</strong>
                    <span id="modalEmployment"></span>
                </p>

                <hr>
                <br>

                <h4>Job Description</h4>
                <!-- ✅ CHANGED -->
                <div id="modalDescription" style="line-height:1.7;"></div>
            </div>

            {{-- RIGHT: APPLICATION --}}
            <div>
                <h4>Apply for this position</h4>

                <form method="POST" action="{{ route('careers.apply.store') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="vacancy_id" id="vacancy_id">

                    <input type="text" name="full_name" placeholder="Full Name" required>
                    <input type="email" name="email" placeholder="Email Address" required>
                    <input type="text" name="phone" placeholder="Phone Number" required>

                    <label>Upload CV</label>
                    <input type="file" name="resume" required>

                    <label>Upload Cover Letter (optional)</label>
                    <input type="file" name="cover_letter">

                    <button type="submit" class="apply-btn" style="width:100%;">
                        Submit Application
                    </button>
                </form>
            </div>
        </div>
    </div>




    <!-- ====== GALLERY STRIP ====== -->
    @include('partials.gallery-strip')

    <!-- ====== FOOTER ====== -->
    <footer id="contact" class="footer" role="contentinfo">
        <div class="footer-content">

            <div class="footer-logo">
                <h3>ATTP<span> · Administration</span></h3>
                <p>
                    African Think Tank Platform Administration — supporting African Union
                    institutions through centralized governance, policy coordination,
                    and strategic oversight of programs and funded initiatives.
                </p>
            </div>

            <div class="footer-links">
                <h4>{{ __('landing.footer_links_title') }}</h4>
                <a href="{{ route('landing.index') }}">{{ __('landing.footer_link_home') }}</a>
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


    <script src="{{ asset('assets/script.js') }}"></script>
    <!--Start of Tawk.to Script-->
    <!--Start of Tawk.to Script-->
    <script type="text/javascript">
        var Tawk_API = Tawk_API || {},
            Tawk_LoadStart = new Date();
        (function() {
            var s1 = document.createElement("script"),
                s0 = document.getElementsByTagName("script")[0];
            s1.async = true;
            s1.src = 'https://embed.tawk.to/6968b44f895de4198b902486/1jf0g0m8k';
            s1.charset = 'UTF-8';
            s1.setAttribute('crossorigin', '*');
            s0.parentNode.insertBefore(s1, s0);
        })();
    </script>
    <!--End of Tawk.to Script-->
    <!--End of Tawk.to Script-->
    {{-- JS --}}
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

        function openApplyModal(btn) {
            document.getElementById('applyModal').classList.add('active');

            document.getElementById('modalTitle').innerText = btn.dataset.title;
            document.getElementById('modalEmployment').innerText = btn.dataset.employment;

            // ✅ Allow HTML content from DB
            document.getElementById('modalDescription').innerHTML = btn.dataset.description;

            document.getElementById('vacancy_id').value = btn.dataset.id;
        }

        function closeApplyModal() {
            document.getElementById('applyModal').classList.remove('active');
        }

        function filterVacancies() {
            const q = document.getElementById('searchInput').value.toLowerCase();
            document.querySelectorAll('.vacancy-card').forEach(card => {
                card.style.display = card.innerText.toLowerCase().includes(q) ? 'block' : 'none';
            });
        }

        document.addEventListener('click', function(e) {
            document.querySelectorAll('.lang-switcher.open').forEach(function(el) {
                if (!el.contains(e.target)) el.classList.remove('open');
            });
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeMobileNav();
                closeApplyModal();
                document.querySelectorAll('.lang-switcher.open').forEach(el => el.classList.remove('open'));
            }
        });
    </script>


</body>

</html>
