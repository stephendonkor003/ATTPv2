<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Basic Meta -->
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>ATTP ? African Think Tank Platform Administration | African Union</title>

    <meta name="description"
        content="ATTP (African Think Tank Platform Administration) is a pan-African knowledge and policy coordination platform supporting African Union institutions, research bodies, and development partners." />

    <meta name="keywords"
        content="ATTP, African Think Tank Platform, African Union policy, Africa governance, policy research Africa, AU think tanks, development policy Africa, public policy Africa, African institutions, research platform Africa" />

    <meta name="author" content="ATTP Secretariat" />
    <meta name="robots" content="index, follow" />
    <meta name="language" content="en" />
    <meta name="theme-color" content="#0B4F6C" />

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website" />
    <meta property="og:title" content="ATTP ? African Think Tank Platform Administration" />
    <meta property="og:description"
        content="Strengthening Africa?s policy ecosystem through collaboration, research, and institutional coordination under the African Union." />
    <meta property="og:image" content="https://attp.africa/assets/images/au3.jpg" />
    <meta property="og:url" content="https://attp.africa/" />
    <meta property="og:site_name" content="ATTP" />

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="ATTP ? African Think Tank Platform Administration" />
    <meta name="twitter:description"
        content="A pan-African platform advancing policy research, governance, and institutional collaboration across Africa." />
    <meta name="twitter:image" content="https://attp.africa/assets/images/au3.jpg" />
    <meta name="twitter:site" content="@ATTP_Africa" />

    <!-- Favicon -->
    <link rel="icon" href="https://attp.africa/assets/images/au3.jpg" type="image/png" />

    <!-- Canonical URL -->
    <link rel="canonical" href="https://attp.africa/" />

    <!-- Fonts & Styles -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('assets/style.css') }}" />

    <!-- Schema.org Markup -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      "name": "African Think Tank Platform Administration",
      "alternateName": "ATTP",
      "url": "https://attp.africa",
      "logo": "https://attp.africa/assets/images/attp-logo.png",
      "description": "ATTP is a pan-African policy and research coordination platform working with African Union institutions, think tanks, and development partners to strengthen governance and evidence-based decision-making across Africa.",
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

        /* ===== Header Section ===== */
        .events-header {
            position: relative;
            background: url('/assets/images/au3.jpg') center/cover no-repeat;
            height: 380px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            text-align: center;
        }

        .events-header::before {
            content: "";
            position: absolute;
            inset: 0;
            background: rgba(82, 43, 57, 0.7);
        }

        .events-header .header-content {
            position: relative;
            z-index: 2;
            max-width: 800px;
            padding: 0 1rem;
        }

        .events-header h1 {
            font-size: 2.5rem;
            color: var(--gold);
        }

        .events-header p {
            margin-top: 1rem;
            font-size: 1.1rem;
            line-height: 1.6;
        }

        /* ===== Filter Section ===== */
        .filter-bar {
            background: #fff;
            box-shadow: 0 5px 15px rgba(0, 0, 0, .1);
            border-radius: 12px;
            max-width: 1100px;
            margin: -60px auto 2rem;
            padding: 1.5rem 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
        }

        .filter-bar input,
        .filter-bar select {
            padding: .8rem 1rem;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 1rem;
            width: 240px;
        }

        .filter-bar button {
            background: var(--magenta);
            color: #fff;
            border: none;
            padding: .9rem 1.6rem;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: .3s;
        }

        .filter-bar button:hover {
            background: var(--orange);
        }

        /* ===== Events Grid ===== */
        .events-container {
            max-width: 1200px;
            margin: 0 auto 5rem;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .event-card {
            background: #fff;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, .1);
            transition: transform .3s ease;
        }

        .event-card:hover {
            transform: translateY(-5px);
        }

        .event-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .event-card .card-body {
            padding: 1.2rem;
        }

        .event-card h4 {
            color: var(--wine);
            margin-bottom: .5rem;
        }

        .event-card p {
            color: #555;
            font-size: .95rem;
            margin-bottom: 1rem;
        }

        .event-date {
            color: var(--magenta);
            font-weight: 600;
            font-size: .9rem;
        }

        .btn-view {
            background: var(--magenta);
            color: #fff;
            padding: .6rem 1.2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: .3s;
        }

        .btn-view:hover {
            background: var(--orange);
        }

        /* ===== Modal ===== */
        .modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 100;
        }

        .modal.active {
            display: flex;
        }

        .modal-box {
            background: #fff;
            padding: 2rem;
            border-radius: 15px;
            max-width: 600px;
            text-align: center;
            position: relative;
        }

        .modal-box h3 {
            color: var(--magenta);
        }

        .close-btn {
            position: absolute;
            top: 12px;
            right: 15px;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--magenta);
        }

        /* ===== Footer ===== */
        /* .footer {
            background: var(--wine);
            color: #fff;
            padding: 3rem 1rem;
            text-align: center;
        }

        .footer a {
            color: var(--gold);
            text-decoration: none;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        @media(max-width:768px) {
            .filter-bar {
                flex-direction: column;
            }

            .filter-bar input,
            .filter-bar select,
            .filter-bar button {
                width: 100%;
            }
        } */
    </style>
</head>


<body>
    <!-- ====== NAVBAR ====== -->
    <header class="navbar">
        <div class="logo">
            {{-- ATTP<span>.africa</span> --}}
            <img src="{{ asset('assets/images/au.png ') }}" alt="ATTP" class="logo logo-sm">

        </div>
        <nav class="nav-links">
            <a href="{{ route('landing.index') }}">Home</a>
            <a href="#annoucements">Annoucements</a>
            <a href="{{ route('events') }}">Events / Webinars</a>
            {{-- <a href="#customization">Customization</a> --}}
            <a href="#contact">Contact</a>
            <a href="{{ route('careers.index') }}">Career</a>
        </nav>

        <div class="nav-actions">
            <a href="{{ route('login') }}" class="btn btn-login">Login</a>
            <a href="{{ route('public.procurement.index') }}" class="btn btn-primary">
                Policy Programs & Research
            </a>

        </div>
    </header>

    <!-- ====== APPLICATION DESCRIPTION ====== -->
    <section class="application-header">
        <div class="overlay"></div>
        <div class="header-content">
            <br />
            <h1>Webinars & Strategic Events</h1>
            <p>
                Engage with African Union institutions, policy leaders, and think tanks through
                ATTP-hosted webinars and strategic events focused on governance, development,
                policy innovation, and evidence-based decision-making across Africa.
            </p>
        </div>
    </section>

    <!-- ====== HEADER ====== -->
    <br>
    <br>
    <br>
    <br>
    <br>
    <!-- ====== FILTER BAR ====== -->


    <!-- ====== WEBINARS FILTER BAR ====== -->
    <div class="filter-bar">
        <input type="text" placeholder="Search by keyword..." id="searchInput" />
        <button onclick="filterEvents()">Search</button>
    </div>

    <!-- ====== WEBINARS GRID ====== -->
    <section class="events-container" id="eventsContainer">

        <div class="event-card">
            <img src="{{ asset('assets/images/au1.jpg') }}" alt="ATTP Launch Webinar">
            <div class="card-body">
                <h4>July 24, 2025: Launch of ATTP Call for Proposals</h4>
                <p class="event-date">Status: Completed</p>
                <p>
                    This webinar introduced the ATTP Call for Proposals and provided an overview of the eligibility
                    criteria, submission guidelines, and key objectives of the ATTP initiative.
                </p>
                <p>
                    <strong>Recording:</strong>
                    <a href="https://drive.google.com/file/u/0/d/1cPV1APFR0zB5rSvNL9PvISpNXHY9LcDr/view?usp=sharing&pli=1"
                        target="_blank" rel="noopener noreferrer" class="btn-view">View Recording</a>
                </p>
            </div>
        </div>

        <div class="event-card">
            <img src="{{ asset('assets/images/au2.webp') }}" alt="Follow-up Webinar August 5">
            <div class="card-body">
                <h4>August 5, 2025: Follow-up Webinar</h4>
                <p class="event-date">Time: 2:00 pm EAT | Status: Completed</p>
                <p>
                    This webinar provided an overview of the ATTP Consortium Application Form and guidance on navigating
                    the ATTP website, clarifying eligibility requirements and consortium formation.
                </p>
                <p>
                    <strong>Recording:</strong>
                    <a href="https://drive.google.com/file/d/1gSqmT-U2guRVa7FNSfdvLp7RHS45L0Za/view" target="_blank"
                        rel="noopener noreferrer" class="btn-view">View Recording</a>
                </p>
            </div>
        </div>

        <div class="event-card">
            <img src="{{ asset('assets/images/au3.jpg') }}" alt="Follow-up Webinar August 26">
            <div class="card-body">
                <h4>August 26, 2025: Follow-up Webinar</h4>
                <p class="event-date">Time: 2:00 pm EAT | Status: Completed</p>
                <p>
                    This session provided additional guidance on proposal development, focusing on the budget and
                    timeline template (including eligible expenses), CV Template, Past Research and Experience Template,
                    and the Commitment Letter.
                </p>
                <p>
                    <strong>Recording:</strong>
                    <a href="https://drive.google.com/file/d/1EzbZ7jbsf6I3FTM1urC9RG_Onld-EGjF/view" target="_blank"
                        rel="noopener noreferrer" class="btn-view">View Recording</a>
                </p>
            </div>
        </div>

        <div class="event-card">
            <img src="{{ asset('assets/images/au4.jpg') }}" alt="Follow-up Webinar September 8">
            <div class="card-body">
                <h4>September 8, 2025: Follow-up Webinar</h4>
                <p class="event-date">Time: 2:00 pm EAT | Status: Completed</p>
                <p>
                    This webinar was conducted to address key applicant questions and provide additional clarification
                    to support submission readiness.
                </p>
                <p>
                    <strong>Recording:</strong>
                    <a href="https://drive.google.com/file/d/1LQzkyAG6ITBIRZqzLK7jEiyxD45MpsVT/view" target="_blank"
                        rel="noopener noreferrer" class="btn-view">View Recording</a>
                </p>
            </div>
        </div>

        <div class="event-card">
            <img src="{{ asset('assets/images/au5.jpg') }}" alt="Final Follow-up Webinar">
            <div class="card-body">
                <h4>September 23, 2025: Final Follow-up Webinar</h4>
                <p class="event-date">Time: 2:00 pm EAT | Status: Completed</p>
                <p>
                    This webinar focused on addressing final questions and preparing applicants
                    for the submission deadline on September 24, 2025.
                </p>
            </div>
        </div>

        <div class="event-card">
            <img src="{{ asset('assets/images/au6.jpg') }}" alt="How to Apply">
            <div class="card-body">
                <h4>Watch Our Step-By-Step Guide on How to Apply</h4>
                <ul style="list-style: disc; padding-left: 20px;">
                    <li>
                        <a href="https://drive.google.com/file/d/1oFGoh93O1MnoB9bdBhQHaWn4mZlHu5ra/view"
                            target="_blank" rel="noopener noreferrer" class="btn-view">
                            How to Apply Video ? English
                        </a>
                    </li>
                    <br><br>
                    <li>
                        <a href="https://drive.google.com/file/d/19bb8Gx5SICNeZKpFAP2XUPDZH9lre-9I/view"
                            target="_blank" rel="noopener noreferrer" class="btn-view">
                            How to Apply Video ? French
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="event-card">
            <img src="{{ asset('assets/images/au7.jpg') }}" alt="Clarification Questions">
            <div class="card-body">
                <h4>Response to Clarification Questions</h4>
                <p>
                    For all clarification questions submitted, please refer to the updated
                    Frequently Asked Questions (FAQ) page.
                </p>
                <p>
                    <a href="https://africathinktankplatform.africa/faq" target="_blank" rel="noopener noreferrer"
                        class="btn-view">
                        Visit ATTP FAQ Page
                    </a>
                </p>
            </div>
        </div>

    </section>


    <!-- ====== STYLING FOR 3-COLUMN GRID ====== -->
    <style>
        .events-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto 5rem;
            padding: 0 2rem;
        }

        @media (max-width: 992px) {
            .events-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .events-container {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script>
        function filterEvents() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const cards = document.querySelectorAll('.event-card');
            cards.forEach(card => {
                const text = card.innerText.toLowerCase();
                card.style.display = text.includes(search) ? 'block' : 'none';
            });
        }
    </script>






    <!-- ====== FOOTER ====== -->
    <!-- ====== FOOTER ====== -->
    <footer id="contact" class="footer">
        <div class="footer-content">

            <div class="footer-logo">
                <h3>ATTP<span> ? Administration</span></h3>
                <p>
                    African Think Tank Platform Administration ? supporting African Union
                    institutions through centralized governance, policy coordination,
                    and strategic oversight of programs and funded initiatives.
                </p>
            </div>

            <div class="footer-links">
                <h4>Quick Links</h4>
                <a href="#">Home</a>
                <a href="#process">Institutional Process Flow</a>
                <a href="#customization">Centralized Oversight</a>
                <a href="#contact">Contact</a>
            </div>

            <div class="footer-contact">
                <h4>Contact</h4>
                <p>Email: attpinfo@africanunion.org</p>
                <p>? 2026 African Think Tank Platform Administration (ATTP)</p>
            </div>

        </div>

        <p style="margin-top: 10px; font-weight: 600; text-align: center;">
            Supporting African Union policy coordination, governance reform,
            and evidence-based decision-making across the continent.
        </p>

    </footer>



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
