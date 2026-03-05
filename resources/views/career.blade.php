<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Careers at ATTP – Join Africa's Procurement Transformation</title>


    <meta name="description"
        content="Explore open career opportunities at ATTP and be part of Africa’s digital procurement transformation." />
    <meta name="keywords" content="ATTP careers, vacancies, procurement jobs, Africa, technology, digital procurement" />
    <meta name="author" content="ATTP Team" />

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="/assets/style.css" />

    <!-- RTL CSS for Arabic -->
    @if(app()->getLocale() === 'ar')
        <link rel="stylesheet" href="{{ asset('assets/css/rtl.css') }}">
    @endif

    <style>
        :root {
            --gold: #fbbc05;
            --orange: #e16435;
            --magenta: #a70d53;
            --wine: #522b39;
            --light: #f7f4f2;
        }

        body {
            font-family: "Inter", sans-serif;
            background: var(--light);
            color: #333;
            margin: 0;
            padding: 0;
        }

        /* ===== HERO ===== */
        .career-hero {
            position: relative;
            height: 380px;
            background: url('{{ asset('assets/three.webp') }}') center/cover no-repeat;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #fff;
        }

        .career-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background: rgba(82, 43, 57, 0.75);
        }

        .career-hero .content {
            position: relative;
            z-index: 2;
            max-width: 800px;
            padding: 1rem;
        }

        .career-hero h1 {
            color: var(--gold);
            font-size: 2.6rem;
        }

        .career-hero p {
            margin-top: 1rem;
            font-size: 1.1rem;
            line-height: 1.6;
        }

        /* ===== CULTURE ===== */
        .culture {
            max-width: 1100px;
            margin: 4rem auto;
            padding: 0 2rem;
        }

        .culture h2 {
            color: var(--wine);
        }

        .culture ul {
            margin-top: 1.5rem;
            line-height: 1.8;
        }

        /* ===== FILTER ===== */
        .filter-bar {
            background: #fff;
            box-shadow: 0 5px 15px rgba(0, 0, 0, .1);
            border-radius: 12px;
            max-width: 900px;
            margin: 0 auto 3rem;
            padding: 1.5rem;
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .filter-bar input {
            padding: .8rem 1rem;
            border-radius: 8px;
            border: 1px solid #ccc;
            width: 260px;
        }

        .filter-bar button {
            background: var(--magenta);
            color: #fff;
            border: none;
            padding: .8rem 1.6rem;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
        }

        /* ===== VACANCIES GRID ===== */
        .vacancies {
            max-width: 1200px;
            margin: 0 auto 5rem;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .vacancy-card {
            background: #fff;
            border-radius: 15px;
            padding: 1.8rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, .1);
        }

        .vacancy-card h4 {
            color: var(--wine);
        }

        .vacancy-meta {
            font-size: .9rem;
            color: var(--magenta);
            margin-bottom: .8rem;
        }

        .apply-btn {
            display: inline-block;
            margin-top: 1rem;
            background: var(--magenta);
            color: #fff;
            padding: .6rem 1.4rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
        }

        .apply-btn:hover {
            background: var(--orange);
        }

        @media(max-width:768px) {
            .filter-bar {
                flex-direction: column;
            }

            .filter-bar input,
            .filter-bar button {
                width: 100%;
            }
        }

        /* ===== APPLY MODAL ===== */
        .modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 1rem;
        }

        .modal.active {
            display: flex;
        }

        /* Wider modal with scrollable content */
        .modal-box {
            background: #fff;
            padding: 2rem;
            border-radius: 15px;
            width: 100%;
            max-width: 1000px;
            /* Wider width */
            max-height: 90vh;
            /* Limit height to viewport */
            overflow-y: auto;
            /* Scrollbar if content exceeds height */
            text-align: left;
            position: relative;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        /* Modal header */
        .modal-box h3 {
            color: var(--magenta);
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .modal-box p {
            margin-bottom: 1rem;
            color: #333;
        }

        /* Close button */
        .close-btn {
            position: absolute;
            top: 12px;
            right: 15px;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--magenta);
            transition: color 0.3s;
        }

        .close-btn:hover {
            color: var(--orange);
        }

        /* Form fields */
        .modal-box .form-control {
            width: 100%;
            padding: 0.8rem 1rem;
            margin-bottom: 1rem;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 1rem;
        }

        .modal-box textarea.form-control {
            resize: vertical;
        }

        /* File inputs */
        .modal-box input[type="file"] {
            border: 1px solid #ccc;
            padding: 0.6rem;
            border-radius: 8px;
            width: 100%;
            font-size: 0.95rem;
            margin-bottom: 1rem;
        }

        /* Labels for file inputs */
        .modal-box label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.3rem;
            color: #333;
        }

        /* Submit button */
        .modal-box .apply-btn {
            background: var(--magenta);
            color: #fff;
            border: none;
            padding: 0.8rem 1.6rem;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .modal-box .apply-btn:hover {
            background: var(--orange);
        }

        @media(max-width:768px) {
            .modal-box {
                padding: 1.5rem;
                max-width: 95%;
                max-height: 95vh;
            }
        }

        /* ===== SUCCESS MODAL ===== */
        #successModal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.4s ease;
        }

        #successModal.active {
            opacity: 1;
            pointer-events: auto;
        }

        #successModal .modal-box {
            max-width: 500px;
            text-align: center;
            padding: 2rem;
            border-radius: 15px;
            background: #fff;
            transform: scale(0.8);
            transition: transform 0.4s ease;
        }

        #successModal.active .modal-box {
            transform: scale(1);
            /* zoom effect on open */
        }

        #successModal h3 {
            color: var(--magenta);
            font-size: 1.6rem;
            margin-bottom: 1rem;
        }

        #successModal p {
            color: #333;
            margin-bottom: 1.5rem;
        }

        #successModal .apply-btn {
            width: 50%;
            margin: 0 auto;
        }
    </style>


</head>

<body>

    <!-- ===== NAVBAR ===== -->

    <header class="navbar">
        <div class="logo">
            <img src="{{ asset('assets/images/ATTP.white.bg.africa.png') }}" class="logo logo-sm" alt="ATTP">
        </div>

        ```
        <nav class="nav-links">
            <a href="/">{{ __('navigation.home') }}</a>
            <a href="#culture">{{ __('navigation.our_culture') }}</a>
            <a href="#vacancies">{{ __('navigation.vacancies') }}</a>
            <a href="{{ route('events') }}">{{ __('navigation.events') }}</a>
            <a href="{{ route('impact.map') }}">{{ __('navigation.impact_map') }}</a>
            <a href="{{ route('world.indicators.performance') }}">{{ __('navigation.world_indicators_performance') }}</a>
            <a href="{{ route('careers') }}" class="active">{{ __('navigation.careers') }}</a>
            <a href="{{ route('applicants.faq') }}">{{ __('navigation.faqs') }}</a>
        </nav>

        <div class="nav-actions">
            <x-language-selector style="landing" />
            <a href="{{ route('login') }}" class="btn btn-login">{{ __('navigation.login') }}</a>
            <a href="{{ route('applicants.create') }}" class="btn btn-primary">{{ __('navigation.call_for_proposals') }}</a>
        </div>
        ```

    </header>

    <!-- ===== HERO ===== -->

    <section class="career-hero">
        <div class="content">
            <h1>{{ __('career.hero_title') }}</h1>
            <p>
                {{ __('career.hero_description') }}
            </p>
        </div>
    </section>

    <!-- ===== CULTURE ===== -->



    <!-- ===== FILTER ===== -->
    <br>
    <div class="filter-bar" id="vacancies">
        <input type="text" id="searchInput" placeholder="{{ __('career.search_placeholder') }}">
        <button onclick="filterVacancies()">{{ __('career.search_button') }}</button>
    </div>

    <!-- ===== VACANCIES ===== -->

    <section class="vacancies" id="vacanciesContainer">


        @forelse($vacancies as $vacancy)
            <div class="vacancy-card">
                <h4>{{ $vacancy->title }}</h4>
                <div class="vacancy-meta">
                    {{ __('career.location_label') }}: {{ $vacancy->location ?? 'Remote / Africa' }}
                </div>
                <p>{{ Str::limit($vacancy->description, 150) }}</p>

                <button class="apply-btn" data-id="{{ $vacancy->id }}" data-title="{{ $vacancy->title }}"
                    data-description="{{ $vacancy->description }}"
                    data-location="{{ $vacancy->location ?? 'Remote / Africa' }}" onclick="openApplyModal(this)">
                    {{ __('career.apply_now') }}
                </button>
            </div>
        @empty
            <p style="grid-column:1/-1; text-align:center;">{{ __('career.no_vacancies') }}</p>
        @endforelse


        <!-- ===== APPLY MODAL ===== -->
        <div class="modal" id="applyModal">
            <div class="modal-box" style="max-width:700px;">
                <button class="close-btn" onclick="closeApplyModal()">&times;</button>

                <h3 id="modalTitle">{{ __('career.modal_title') }}</h3>
                <p id="modalLocation" style="color:#a70d53; font-weight:600;"></p>
                <p id="modalDescription" style="margin-bottom:1.5rem;"></p>

                <form method="POST" action="{{ route('vacancies.apply.store') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="vacancy_id" id="vacancy_id">

                    <input type="text" name="full_name" placeholder="{{ __('career.full_name') }}" required class="form-control mb-3">
                    <input type="email" name="email" placeholder="{{ __('career.email') }}" required class="form-control mb-3">
                    <input type="text" name="phone" placeholder="{{ __('career.phone') }}" required class="form-control mb-3">
                    <input type="text" name="nationality" placeholder="{{ __('career.nationality') }}"
                        class="form-control mb-3">

                    <label>{{ __('career.upload_cv') }}</label>
                    <input type="file" name="resume" accept=".pdf,.doc,.docx" required class="form-control mb-3">

                    <label>{{ __('career.upload_cover_letter') }}</label>
                    <input type="file" name="cover_letter" accept=".pdf,.doc,.docx" required
                        class="form-control mb-3">

                    <button type="submit" class="apply-btn" style="width:100%;">{{ __('career.submit_application') }}</button>
                </form>
            </div>
        </div>

        @if (session('success'))
            <div class="modal" id="successModal">
                <div class="modal-box">
                    <button class="close-btn" onclick="closeSuccessModal()">&times;</button>
                    <h3>{{ __('career.success_title') }}</h3>
                    <p>{{ __('career.success_message') }}</p>
                    <button class="apply-btn" onclick="closeSuccessModal()">{{ __('career.close') }}</button>
                </div>
            </div>
        @endif



        <!-- ===== MODAL JS ===== -->
        <script>
            function openApplyModal(button) {
                document.getElementById('applyModal').classList.add('active');

                document.getElementById('modalTitle').innerText = button.dataset.title;
                document.getElementById('modalLocation').innerText = button.dataset.location;
                document.getElementById('modalDescription').innerText = button.dataset.description;
                document.getElementById('vacancy_id').value = button.dataset.id;
            }

            function closeApplyModal() {
                document.getElementById('applyModal').classList.remove('active');
            }
        </script>


    </section>

    <!-- ===== SCRIPT ===== -->

    <script>
        function filterVacancies() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            document.querySelectorAll('.vacancy-card').forEach(card => {
                card.style.display = card.innerText.toLowerCase().includes(search) ? 'block' : 'none';
            });
        }
    </script>

    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const successModal = document.getElementById('successModal');
            if (successModal) {
                successModal.classList.add('active');

                // Optional: auto-close after 3 seconds
                setTimeout(() => {
                    successModal.classList.remove('active');
                }, 3000);
            }
        });

        function closeSuccessModal() {
            const successModal = document.getElementById('successModal');
            if (successModal) {
                successModal.classList.remove('active');
            }
        }
    </script>




    <!-- ====== FOOTER ====== -->
    <footer id="contact" class="footer">
        <div class="footer-content">
            <div class="footer-logo">
                <h3>ATTP.<span>Africa</span></h3>
                <p>{{ __('landing.footer_tagline') }}
                </p>

            </div>

            <div class="footer-links">
                <h4>{{ __('landing.quick_links') }}</h4>
                <a href="#">{{ __('navigation.home') }}</a>
                <a href="#process">{{ __('navigation.process') }}</a>
                <a href="#customization">{{ __('navigation.customization') }}</a>
                <a href="#">{{ __('navigation.contact') }}</a>
            </div>

            <div class="footer-contact">
                <h4>{{ __('navigation.contact') }}</h4>
                <p>{{ __('landing.contact_email') }}: {{ __('landing.contact_info') }}</p>
                <p>© 2025 ATTP. {{ __('common.all_rights_reserved') }}.</p>
            </div>

        </div>
        <p style="margin-top: 10px; font-weight: 600; text-align: center;">
            {{ __('common.powered_by') }}
        </p>

    </footer>

</body>

</html>
