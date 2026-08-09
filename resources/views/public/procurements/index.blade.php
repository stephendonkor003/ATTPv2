<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Open Procurements | ATTP Africa</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Explore open procurement opportunities and apply digitally on ATTP Africa.">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f7f4f2;
            margin: 0;
        }

        /* ===== HEADER ===== */
        .events-header {
            background: url('/assets/three.webp') center/cover no-repeat;
            height: 360px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }

        .events-header::before {
            content: "";
            position: absolute;
            inset: 0;
            background: rgba(82, 43, 57, 0.75);
        }

        .header-content {
            position: relative;
            text-align: center;
            max-width: 800px;
            padding: 0 1rem;
        }

        .header-content h1 {
            color: #fbbc05;
            font-size: 2.5rem;
            margin-bottom: .5rem;
        }

        .header-content p {
            font-size: 1.05rem;
            line-height: 1.6;
        }

        /* ===== FILTER BAR ===== */
        .filter-bar {
            background: #fff;
            margin: -50px auto 2.5rem;
            max-width: 1100px;
            padding: 1.5rem 2rem;
            border-radius: 12px;
            display: flex;
            gap: 1rem;
            justify-content: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, .1);
        }

        .filter-bar input {
            padding: .8rem 1rem;
            width: 360px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 1rem;
        }

        .filter-bar button {
            background: #a70d53;
            color: #fff;
            border: none;
            padding: .8rem 1.6rem;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: .3s;
        }

        .filter-bar button:hover {
            background: #e16435;
        }

        /* ===== GRID ===== */
        .events-container {
            max-width: 1200px;
            margin: 0 auto 4rem;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
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
            transform: translateY(-6px);
        }

        .event-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .event-card-media {
            position: relative;
            height: 200px;
            overflow: hidden;
            background: #dce9ed;
        }

        .event-card-media > img {
            height: 100%;
        }

        .event-card-owner {
            position: absolute;
            left: 14px;
            right: 14px;
            bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-radius: 9px;
            background: rgba(7, 63, 82, .9);
            padding: 8px 10px;
            color: #fff;
            backdrop-filter: blur(8px);
        }

        .event-card-owner img,
        .event-card-owner span:first-child {
            display: grid;
            width: 30px;
            height: 30px;
            flex: 0 0 30px;
            place-items: center;
            border-radius: 7px;
            background: #fff;
            object-fit: contain;
            color: #075c7a;
            font-weight: 800;
        }

        .event-card-owner strong {
            overflow: hidden;
            font-size: .78rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .card-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin: .8rem 0 0;
        }

        .card-meta span {
            border-radius: 999px;
            background: #eaf3f5;
            padding: 5px 8px;
            color: #075c7a;
            font-size: .74rem;
            font-weight: 700;
        }

        .card-body {
            padding: 1.2rem 1.3rem;
        }

        .card-body h4 {
            color: #522b39;
            margin-bottom: .5rem;
            font-size: 1.1rem;
        }

        .card-body p {
            color: #555;
            font-size: .95rem;
            line-height: 1.5;
        }

        .btn-view {
            display: inline-block;
            margin-top: 1rem;
            background: #a70d53;
            color: #fff;
            padding: .6rem 1.2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: .3s;
        }

        .btn-view:hover {
            background: #e16435;
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
</head>

<body>
    <header class="navbar">
        <div class="logo">

            <img src="{{ asset('assets/images/au.png') }}" alt="" class="logo logo-sm">

        </div>
        <nav class="nav-links">
            <a href="{{ route('landing.index') }}">Home</a>
            <a href="#annoucements">Annoucements</a>
            <a href="{{ route('events') }}">Events / Webinars</a>
            {{-- <a href="#customization">Customization</a> --}}
            <a href="{{ route('public.grievances.create') }}">Grievance</a>
            <a href="#contact">Contact</a>
            <a href="{{ route('careers.index') }}">Career</a>
        </nav>

        <div class="nav-actions">
            <a href="{{ route('login') }}" class="btn btn-login">Login</a>
            <a href="{{ route('public.procurement.index') }}" class="btn btn-primary">Call for Proposals</a>

        </div>
    </header>



    <section class="events-header">
        <div class="header-content">
            <h1>Open Procurement Opportunities</h1>
            <p>
                Browse available procurement calls and submit your application
                digitally through the ATTP platform.
            </p>
        </div>
    </section>
    <br>
    <br>
    <br>

    <div class="filter-bar">
        <input type="text" id="searchInput" placeholder="Search procurement by keyword...">
        <button onclick="filterProcurements()">Initiate Procurement Search</button>
    </div>


    <section class="events-container" id="procurementContainer">

        @forelse($procurements as $procurement)
            <div class="event-card">
                <div class="event-card-media">
                    <img src="{{ $procurement->cover_image_url ?: asset('assets/three.webp') }}" alt="{{ $procurement->title }} cover image">
                    @if($procurement->thinkTankMember)
                        <div class="event-card-owner">
                            @if($procurement->thinkTankMember->logo_url)
                                <img src="{{ $procurement->thinkTankMember->logo_url }}" alt="{{ $procurement->thinkTankMember->name }} logo">
                            @else
                                <span>{{ Str::upper(Str::substr($procurement->thinkTankMember->name, 0, 1)) }}</span>
                            @endif
                            <strong>{{ $procurement->thinkTankMember->name }}</strong>
                        </div>
                    @endif
                </div>

                <div class="card-body">
                    <h4>{{ $procurement->title }}</h4>

                    <p>
                        {{ \Illuminate\Support\Str::limit(strip_tags($procurement->description), 130) }}
                    </p>

                    <div class="card-meta">
                        <span>{{ $procurement->reference_no ?: 'Open call' }}</span>
                        @if($procurement->application_end_date)<span>Closes {{ $procurement->application_end_date->format('d M Y') }}</span>@endif
                        @if($procurement->activeForm)<span>{{ $procurement->activeForm->fields->count() }} form questions</span>@endif
                    </div>

                    <a href="{{ route('public.procurement.show', $procurement->slug) }}" class="btn-view">
                        View Details & Apply
                    </a>
                </div>
            </div>

        @empty
            <p style="grid-column:1/-1;text-align:center;font-weight:600;">
                No open procurements at the moment.
            </p>
        @endforelse

    </section>


    <script>
        function filterProcurements() {
            const search = document
                .getElementById('searchInput')
                .value
                .toLowerCase();

            document.querySelectorAll('.event-card').forEach(card => {
                card.style.display = card.innerText.toLowerCase().includes(search) ?
                    'block' :
                    'none';
            });
        }
    </script>

    @include('partials.gallery-strip')

    <footer id="contact" class="footer">
        <div class="footer-content">

            <div class="footer-logo">
                <h3>ATTP<span> Administration</span></h3>
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


    <script src="assets/script.js"></script>

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


</body>

</html>
