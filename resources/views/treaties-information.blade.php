<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AU Treaties Information | ATTP Impact</title>
    <meta name="description" content="African Union treaties status - signatures, ratifications, and submissions by member state.">
    <link rel="icon" href="{{ asset('assets/images/au.png') }}" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="{{ asset('assets/style.css') }}">
    <style>
        :root {
            --au-green:       #006B3F;
            --au-green-dark:  #004d2e;
            --au-green-light: #009A44;
            --gold:           #fbbc05;
            --orange:         #e16435;
            --light:          #f0f5f1;
            --magenta:        #006B3F;
            --ink:            #102018;
            --muted:          #5a7065;
            --line:           #dbe8df;
            --surface-soft:   #f7faf8;
            --blue:           #2563eb;
            --teal:           #0f766e;
            --red:            #b91c1c;
        }

        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0; font-family: 'Inter', sans-serif;
            background: linear-gradient(180deg, #edf6f1 0%, #f8fbf9 36%, #eef5f1 100%);
            color: #1a2e22;
        }

        /* ── NAVBAR ── */
        .navbar {
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px 5%; background: var(--au-green);
            box-shadow: 0 2px 12px rgba(0,0,0,.2);
            position: sticky; top: 0; z-index: 100;
        }
        .nav-links a { margin-left: 20px; font-weight: 500; color: #fff; text-decoration: none; font-size: .9rem; transition: color .2s; }
        .nav-links a.active, .nav-links a:hover { color: var(--gold); }

        /* ── HERO ── */
        .treaties-hero {
            background: linear-gradient(135deg, rgba(0,45,29,.95), rgba(0,107,63,.78), rgba(15,118,110,.74)),
                        url('{{ asset('assets/images/au3.jpg') }}') center/cover no-repeat;
            padding: 78px 24px 104px;
            color: #fff;
        }
        .hero-inner { max-width: 1160px; margin: 0 auto; }
        .treaties-hero .breadcrumb {
            font-size: .82rem; color: rgba(255,255,255,.76); margin-bottom: 18px;
        }
        .treaties-hero .breadcrumb a { color: var(--gold); text-decoration: none; }
        .treaties-hero .breadcrumb a:hover { text-decoration: underline; }
        .hero-eyebrow {
            display: inline-flex; align-items: center; gap: 8px;
            margin: 0 0 12px; color: #dff6e9; font-size: .78rem; font-weight: 800;
            text-transform: uppercase; letter-spacing: .08em;
        }
        .hero-eyebrow::before {
            content: ""; width: 28px; height: 2px; border-radius: 999px; background: var(--gold);
        }
        .treaties-hero h1 {
            max-width: 780px; font-size: clamp(2.1rem, 4.6vw, 4.2rem);
            line-height: 1.03; margin: 0 0 18px; color: #fff; letter-spacing: 0;
        }
        .treaties-hero p { max-width: 720px; margin: 0; line-height: 1.75; opacity: .92; font-size: 1.03rem; }
        .hero-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 28px; }
        .hero-link {
            display: inline-flex; align-items: center; justify-content: center;
            min-height: 42px; padding: 10px 18px; border-radius: 8px;
            border: 1px solid rgba(255,255,255,.38); color: #fff; text-decoration: none;
            font-size: .9rem; font-weight: 800; transition: transform .2s, background .2s, border-color .2s;
        }
        .hero-link.primary { background: var(--gold); color: #122016; border-color: var(--gold); }
        .hero-link:hover { transform: translateY(-1px); background: rgba(255,255,255,.12); }
        .hero-link.primary:hover { background: #ffd65b; }

        /* ── STATS BAR ── */
        .stats-bar {
            max-width: 1160px; margin: -46px auto 0; padding: 0 24px;
            position: relative; z-index: 10;
        }
        .stats-grid {
            display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 14px;
        }
        .stat-card {
            position: relative; background: rgba(255,255,255,.96); border-radius: 12px; border: 1px solid #e0ebe5;
            box-shadow: 0 14px 34px rgba(16,32,24,.12);
            padding: 18px 18px; text-align: left; overflow: hidden;
            transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
        }
        .stat-card::before {
            content: ""; position: absolute; left: 0; top: 0; right: 0; height: 4px;
            background: var(--au-green);
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 18px 42px rgba(16,32,24,.16); border-color: #c9dbd0; }
        .stat-number { font-size: 2rem; font-weight: 800; color: var(--au-green); line-height: 1; margin-bottom: 7px; }
        .stat-label { font-size: .79rem; color: #5f7569; font-weight: 700; line-height: 1.35; }
        .stat-hint { margin-top: 8px; font-size: .72rem; color: #7c9185; }
        .stat-card.gold .stat-number { color: #b8860b; }
        .stat-card.gold::before { background: var(--gold); }
        .stat-card.blue .stat-number { color: #1e5fa3; }
        .stat-card.blue::before { background: var(--blue); }
        .stat-card.orange .stat-number { color: #c0502a; }
        .stat-card.orange::before { background: var(--orange); }
        .stat-card.teal .stat-number { color: var(--teal); }
        .stat-card.teal::before { background: var(--teal); }

        /* ── MAIN LAYOUT ── */
        .page-wrap { max-width: 1160px; margin: 48px auto 60px; padding: 0 24px; }
        .section-intro {
            display: flex; justify-content: space-between; align-items: flex-end; gap: 18px;
            margin: 0 0 16px;
        }
        .section-kicker {
            display: inline-block; margin-bottom: 7px; color: var(--teal); font-size: .76rem;
            font-weight: 800; text-transform: uppercase; letter-spacing: .08em;
        }
        .section-intro h2 { margin: 0; color: var(--ink); font-size: 1.45rem; letter-spacing: 0; }
        .section-intro p { margin: 7px 0 0; color: var(--muted); max-width: 690px; line-height: 1.6; font-size: .92rem; }
        .coverage-chip {
            flex-shrink: 0; border: 1px solid var(--line); background: #fff; color: var(--muted);
            border-radius: 999px; padding: 9px 14px; font-size: .82rem; font-weight: 800;
            box-shadow: 0 8px 18px rgba(16,32,24,.06);
        }
        .coverage-chip strong { color: var(--au-green-dark); }

        /* ── TOOLBAR ── */
        .toolbar {
            background: #fff; border-radius: 12px; border: 1px solid #e0ebe5;
            padding: 18px; display: grid; grid-template-columns: 1.2fr 1fr .9fr 1fr auto;
            gap: 12px; align-items: end; margin-bottom: 12px;
            box-shadow: 0 8px 24px rgba(16,32,24,.06);
        }
        .filter-control { min-width: 0; }
        .filter-control label {
            display: block; font-size: .76rem; font-weight: 800; color: #5a7065; margin-bottom: 6px;
        }
        .toolbar label { font-size: .82rem; font-weight: 600; color: #5a7065; white-space: nowrap; }
        .toolbar select, .toolbar input {
            width: 100%; border: 1.5px solid #d0dcd5; border-radius: 8px; padding: 10px 12px;
            font: inherit; font-size: .88rem; background: #f7faf8; outline: none;
            transition: border-color .2s, background .2s, box-shadow .2s; min-width: 0;
        }
        .toolbar select:focus, .toolbar input:focus {
            border-color: var(--au-green); background: #fff; box-shadow: 0 0 0 3px rgba(0,107,63,.1);
        }
        .toolbar-spacer { flex: 1; }
        .toolbar-actions { display: flex; gap: 8px; align-items: center; justify-content: flex-end; }
        .reset-btn {
            min-height: 40px; padding: 9px 14px; border-radius: 8px; border: 1.5px solid #c8d8ce;
            background: #fff; color: #4a6355; font-size: .85rem; font-weight: 600;
            cursor: pointer; white-space: nowrap; transition: all .2s; text-decoration: none;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .reset-btn:hover { background: var(--au-green); color: #fff; border-color: var(--au-green); }
        .reset-btn.secondary:hover { background: #102018; border-color: #102018; }
        .results-summary {
            display: flex; justify-content: space-between; align-items: center; gap: 14px;
            margin-bottom: 28px; color: var(--muted); font-size: .86rem;
        }
        .results-summary strong { color: var(--au-green-dark); }
        .filter-loading {
            display: none; align-items: center; gap: 14px;
            margin: -12px 0 28px; padding: 14px 16px;
            border: 1px solid #d9e8df; border-radius: 12px;
            background: linear-gradient(90deg, #ffffff, #f4faf6);
            box-shadow: 0 12px 26px rgba(16,32,24,.08);
        }
        .filter-loading.active { display: flex; }
        .loading-mark {
            width: 42px; height: 42px; border-radius: 50%;
            border: 4px solid #dfece5; border-top-color: var(--au-green);
            animation: spinTreatyLoader .8s linear infinite; flex-shrink: 0;
        }
        .loading-copy { min-width: 0; flex: 1; }
        .loading-copy strong { display: block; color: var(--ink); font-size: .92rem; margin-bottom: 3px; }
        .loading-copy span { color: var(--muted); font-size: .82rem; }
        .loading-bars { display: grid; gap: 5px; width: 170px; flex-shrink: 0; }
        .loading-bars i {
            display: block; height: 7px; border-radius: 999px;
            background: linear-gradient(90deg, #dcebe3, #ffffff, #dcebe3);
            background-size: 220% 100%; animation: shimmerTreatyLoader 1.1s ease-in-out infinite;
        }
        .loading-bars i:nth-child(2) { width: 78%; animation-delay: .12s; }
        .loading-bars i:nth-child(3) { width: 58%; animation-delay: .24s; }
        body.is-filtering .treaties-list,
        body.is-filtering .full-table-card,
        body.is-filtering .treaty-map-card {
            opacity: .56; filter: saturate(.82); transition: opacity .18s, filter .18s;
        }
        @keyframes spinTreatyLoader { to { transform: rotate(360deg); } }
        @keyframes shimmerTreatyLoader { 0% { background-position: 120% 0; } 100% { background-position: -120% 0; } }

        /* Africa Treaty Map */
        .treaty-map-card {
            background: #fff; border-radius: 14px; border: 1px solid #e0ebe5;
            box-shadow: 0 12px 30px rgba(16,32,24,.08); overflow: hidden; margin-bottom: 28px;
        }
        .treaty-map-head {
            display: flex; justify-content: space-between; align-items: flex-start; gap: 18px;
            padding: 20px 22px; border-bottom: 1px solid #e8f0eb; background: linear-gradient(90deg, #f7faf8, #eef7f2);
        }
        .treaty-map-head h2 { margin: 0 0 5px; font-size: 1.12rem; color: var(--au-green-dark); }
        .treaty-map-head p { margin: 0; color: #5a7065; font-size: .88rem; line-height: 1.5; }
        .map-status {
            border: 1px solid #d0dcd5; border-radius: 999px; background: #fff;
            padding: 6px 12px; color: #4a6355; font-size: .78rem; font-weight: 700;
            white-space: nowrap;
        }
        #treatyAfricaMap { width: 100%; min-height: 520px; background: #dbe7f6; }
        .treaty-map-meta {
            display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px;
            padding: 14px 16px; border-top: 1px solid #e8f0eb; background: #fff;
        }
        .map-count {
            border: 1px solid #e0ebe5; border-radius: 10px; padding: 10px 12px; background: #f7faf8;
            transition: transform .2s, border-color .2s, background .2s;
        }
        .map-count:hover { transform: translateY(-2px); border-color: #c9dbd0; background: #fff; }
        .map-count .k {
            display: flex; align-items: center; gap: 7px; font-size: .75rem; font-weight: 800;
            text-transform: uppercase; letter-spacing: .4px; color: #5a7065;
        }
        .map-count .v { margin-top: 4px; font-size: 1.45rem; line-height: 1; font-weight: 800; color: #102018; }
        .map-swatch { width: 12px; height: 12px; border-radius: 3px; border: 1px solid rgba(0,0,0,.15); }
        .swatch-signed { background: #2563eb; }
        .swatch-ratified { background: #16a34a; }
        .swatch-submitted { background: #f97316; }
        .swatch-none { background: #d1d5db; }
        .leaflet-popup-content { font-family: 'Inter', sans-serif; }
        .map-popup-title { font-weight: 800; color: var(--au-green-dark); margin-bottom: 6px; }
        .map-popup-row { font-size: .82rem; margin: 3px 0; color: #334155; }

        /* ── TREATY CARDS ── */
        .treaties-list { display: flex; flex-direction: column; gap: 20px; margin-bottom: 48px; }

        .treaty-card {
            background: #fff; border-radius: 14px; border: 1px solid #e0ebe5;
            box-shadow: 0 8px 22px rgba(16,32,24,.06); overflow: hidden;
            transition: transform .2s, box-shadow .2s, border-color .2s;
        }
        .treaty-card:hover { transform: translateY(-2px); box-shadow: 0 14px 34px rgba(16,32,24,.1); border-color: #c9dbd0; }
        .treaty-card.open { border-color: rgba(0,107,63,.28); }
        .treaty-header {
            display: flex; align-items: flex-start; gap: 20px;
            padding: 22px 24px; cursor: pointer; transition: background .15s;
        }
        .treaty-header:hover { background: #f7faf8; }

        .treaty-code {
            flex-shrink: 0; background: var(--au-green-dark); color: var(--gold);
            border-radius: 10px; padding: 8px 12px; font-size: .78rem; font-weight: 800;
            text-align: center; min-width: 72px; letter-spacing: .3px;
        }

        .treaty-meta { flex: 1; min-width: 0; }
        .treaty-meta h3 { margin: 0 0 4px; font-size: 1.05rem; color: #102018; line-height: 1.35; }
        .treaty-meta .short-title { font-size: .85rem; color: #6b8676; margin-bottom: 10px; }

        .treaty-badges { display: flex; gap: 8px; flex-wrap: wrap; }
        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; border-radius: 99px; font-size: .76rem; font-weight: 700;
        }
        .badge-signed   { background: #dbeafe; color: #1e5fa3; }
        .badge-ratified { background: #dcfce7; color: #166534; }
        .badge-submitted{ background: #fff7ed; color: #9a3412; }
        .badge-date     { background: #f1f5f9; color: #475569; }
        .badge-status   { background: #e8f5ee; color: var(--au-green); }
        .badge-pending  { background: #fff1f2; color: var(--red); }

        .treaty-progress {
            display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px;
            margin-top: 14px; max-width: 620px;
        }
        .progress-item { min-width: 0; }
        .progress-label {
            display: flex; justify-content: space-between; gap: 8px;
            color: #5a7065; font-size: .76rem; font-weight: 800; margin-bottom: 6px;
        }
        .progress-track { height: 8px; border-radius: 999px; background: #edf2ef; overflow: hidden; }
        .progress-fill { height: 100%; border-radius: inherit; background: var(--blue); width: 0; }
        .progress-fill.ratified { background: #16a34a; }

        .treaty-toggle {
            flex-shrink: 0; width: 32px; height: 32px; border-radius: 8px;
            border: 1.5px solid #d0dcd5; background: #f7faf8;
            display: flex; align-items: center; justify-content: center;
            color: #5a7065; font-size: 1rem; transition: all .2s; cursor: pointer;
        }
        .treaty-card.open .treaty-toggle { background: var(--au-green); color: #fff; border-color: var(--au-green); transform: rotate(45deg); }

        .treaty-description {
            border-top: 1px solid #e8f0eb;
            padding: 0 24px; max-height: 0; overflow: hidden;
            transition: max-height .35s ease, padding .2s;
        }
        .treaty-card.open .treaty-description {
            max-height: 2600px; padding: 20px 24px;
        }

        .treaty-desc-grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: 24px; margin-bottom: 20px; }
        .desc-section h4 { font-size: .85rem; font-weight: 700; color: var(--au-green); margin: 0 0 6px; text-transform: uppercase; letter-spacing: .4px; }
        .desc-section p  { font-size: .9rem; color: #3a5040; line-height: 1.65; margin: 0; }

        .treaty-read-more {
            display: inline-block; margin-top: 10px; font-size: .85rem; font-weight: 600;
            color: var(--au-green); text-decoration: none;
        }
        .treaty-read-more:hover { text-decoration: underline; }

        /* Country status mini-table */
        .country-table-wrap { overflow-x: auto; margin-top: 16px; }
        .country-table {
            width: 100%; border-collapse: collapse; font-size: .83rem;
        }
        .country-table th {
            background: var(--au-green-dark); color: #fff; padding: 9px 12px;
            text-align: left; font-weight: 600; white-space: nowrap;
        }
        .country-table td {
            padding: 8px 12px; border-bottom: 1px solid #e8f0eb; vertical-align: middle;
        }
        .country-table tr:last-child td { border-bottom: none; }
        .country-table tr:hover td { background: #f7faf8; }
        .country-table .flag { font-size: 1.1rem; }
        .status-dot {
            display: inline-block; width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
        }
        .dot-yes  { background: #22c55e; }
        .dot-no   { background: #e5e7eb; }
        .status-cell { display: flex; align-items: center; gap: 6px; }

        /* ── FULL TABLE SECTION ── */
        .section-heading {
            font-size: 1.25rem; font-weight: 700; color: var(--au-green-dark);
            margin: 0 0 18px; padding-bottom: 12px; border-bottom: 2px solid var(--gold);
        }

        .full-table-card {
            background: #fff; border-radius: 14px; border: 1px solid #e0ebe5;
            box-shadow: 0 12px 30px rgba(16,32,24,.08); overflow: hidden;
        }
        .full-table-head {
            display: flex; justify-content: space-between; align-items: flex-end; gap: 18px;
            padding: 18px 20px; border-bottom: 1px solid #e8f0eb;
            background: linear-gradient(90deg, #ffffff, #f7faf8);
        }
        .full-table-head h2 { margin: 0 0 5px; color: var(--au-green-dark); font-size: 1.18rem; }
        .full-table-head p { margin: 0; color: var(--muted); font-size: .86rem; line-height: 1.5; }
        .full-table-meta { color: var(--muted); font-size: .82rem; font-weight: 800; white-space: nowrap; }
        .full-table-scroll { max-height: 660px; overflow: auto; }
        .full-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        .full-table th {
            background: #f0f5f1; color: #2a3d30; padding: 11px 14px;
            text-align: left; font-weight: 700; border-bottom: 2px solid #d0dcd5;
            white-space: nowrap; position: sticky; top: 0; z-index: 2;
        }
        .full-table td { padding: 10px 14px; border-bottom: 1px solid #e8f0eb; vertical-align: middle; }
        .full-table tr:last-child td { border-bottom: none; }
        .full-table tr:hover td { background: #f7faf8; }
        .full-table .treaty-name-col { font-weight: 600; color: #102018; max-width: 260px; }
        .full-table .country-col { white-space: nowrap; }

        .status-pill {
            display: inline-block; padding: 3px 9px; border-radius: 99px;
            font-size: .74rem; font-weight: 700; white-space: nowrap;
        }
        .pill-yes { background: #dcfce7; color: #166534; }
        .pill-no  { background: #f1f5f9; color: #94a3b8; }
        .pill-signed { background: #dbeafe; color: #1e40af; }
        .pill-ratified { background: #dcfce7; color: #166534; }
        .pill-submitted { background: #fff7ed; color: #9a3412; }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center; padding: 60px 40px;
            background: #fff; border-radius: 14px; border: 1px solid #e0ebe5;
        }
        .empty-icon { font-size: 3rem; margin-bottom: 16px; }
        .empty-state h3 { color: var(--au-green); margin: 0 0 8px; }
        .empty-state p { color: #6b8676; margin: 0; }

        /* ── RESPONSIVE ── */
        @media (max-width: 900px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .section-intro { flex-direction: column; align-items: flex-start; }
            .toolbar { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .toolbar-actions { justify-content: flex-start; }
            .treaty-map-head { flex-direction: column; }
            .treaty-map-meta { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .treaty-desc-grid { grid-template-columns: 1fr; }
            .treaties-hero h1 { font-size: 1.9rem; }
            .treaty-progress { grid-template-columns: 1fr; }
            .full-table-head { flex-direction: column; align-items: flex-start; }
        }
        @media (max-width: 600px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .toolbar { grid-template-columns: 1fr; }
            .toolbar select, .toolbar input { min-width: 0; width: 100%; }
            .treaties-hero { padding: 58px 18px 92px; }
            .hero-actions { flex-direction: column; align-items: stretch; }
            .treaty-header { gap: 12px; padding: 18px; }
            .treaty-code { min-width: 58px; padding: 8px 9px; }
            .treaty-description { padding-left: 18px; padding-right: 18px; }
        }
    </style>
</head>
<body>

<!-- ── NAVBAR ── -->
<header class="navbar" role="banner">
    <a href="{{ route('landing.index') }}" class="logo" aria-label="ATTP Home">
        <img src="{{ asset('assets/images/au.png') }}" alt="ATTP" class="logo-sm">
    </a>
    <nav class="nav-links" aria-label="Main navigation">
        <a href="{{ route('landing.index') }}">Home</a>
        <div class="has-dropdown">
            <a href="#">Programs</a>
            <ul class="nav-dropdown">
                <li><a href="{{ route('events') }}">Events / Webinars</a></li>
                <li><a href="{{ route('careers.index') }}">Careers</a></li>
            </ul>
        </div>
        <div class="has-dropdown">
            <a href="#" class="active">Analytics</a>
            <ul class="nav-dropdown">
                <li><a href="{{ route('impact.map') }}">Impact Map</a></li>
                <li><a href="{{ route('world.indicators.performance') }}">World Indicators / Performance</a></li>
            </ul>
        </div>
        <a href="{{ route('news.index') }}">News &amp; Updates</a>
        <a href="#contact">Contact</a>
    </nav>
    <div class="nav-actions">
        <a href="{{ route('public.procurement.index') }}" class="btn btn-primary">Policy Programs &amp; Research</a>
        <a href="{{ route('login') }}" class="btn btn-login">Login</a>
        <x-language-selector style="treaties" />
    </div>
    <button class="hamburger-btn" id="hamburgerBtn" onclick="openMobileNav()" aria-label="Open menu" aria-expanded="false">
        <span></span><span></span><span></span>
    </button>
</header>

<!-- ── HERO ── -->
@php
    $treatyCollection = collect($treatiesData);
    $totalTreaties    = count($treatiesData);
    $totalSigned      = $treatyCollection->sum('signed_count');
    $totalRatified    = $treatyCollection->sum('ratified_count');
    $totalSubmitted   = $treatyCollection->sum('original_submitted_count');
    $totalCountries   = count($memberStates);
    $totalRows        = count($statusTableRows);
    $totalPending     = collect($statusTableRows)->filter(function ($row) {
        return !($row['is_signed'] ?? false)
            && !($row['is_ratified'] ?? false)
            && !($row['is_original_submitted'] ?? false);
    })->count();
    $signatureCoverage = $totalRows > 0 ? round(($totalSigned / $totalRows) * 100) : 0;
    $ratificationCoverage = $totalRows > 0 ? round(($totalRatified / $totalRows) * 100) : 0;
@endphp

<section class="treaties-hero">
    <div class="hero-inner">
        <div class="breadcrumb">
        <a href="{{ route('impact.map') }}">Impact Map</a>
        &nbsp;/&nbsp; AU Treaties Information
        </div>
        <div class="hero-eyebrow">Public Treaty Monitor</div>
        <h1>African Union Treaty Status Dashboard</h1>
        <p>Track African Union treaty signatures, ratifications, and instrument submissions across member states from one public matrix.</p>
        <div class="hero-actions">
            <a class="hero-link primary" href="#treatyExplorer">Explore treaties</a>
            <a class="hero-link" href="{{ route('impact.map') }}">Back to impact map</a>
        </div>
    </div>
</section>

<!-- ── STATS BAR ── -->
<div class="stats-bar">
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number">{{ $totalTreaties }}</div>
            <div class="stat-label">AU Treaties</div>
            <div class="stat-hint">Active treaty records</div>
        </div>
        <div class="stat-card gold">
            <div class="stat-number">{{ $totalSigned }}</div>
            <div class="stat-label">Signatures recorded</div>
            <div class="stat-hint">{{ $signatureCoverage }}% of matrix rows</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-number">{{ $totalRatified }}</div>
            <div class="stat-label">Ratifications</div>
            <div class="stat-hint">{{ $ratificationCoverage }}% of matrix rows</div>
        </div>
        <div class="stat-card teal">
            <div class="stat-number">{{ $totalSubmitted }}</div>
            <div class="stat-label">Instruments submitted</div>
            <div class="stat-hint">Original documents tracked</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-number">{{ $totalCountries }}</div>
            <div class="stat-label">Member states</div>
            <div class="stat-hint">{{ number_format($totalPending) }} pending rows</div>
        </div>
    </div>
</div>

<!-- ── PAGE CONTENT ── -->
<div class="page-wrap">

    @if(count($treatiesData) === 0)
        <div class="empty-state">
            <div class="empty-icon">📄</div>
            <h3>No treaty data available</h3>
            <p>Treaty records with member state statuses will appear here once they are entered into the system.</p>
        </div>
    @else

        <!-- ── FILTER TOOLBAR ── -->
        <section id="treatyExplorer" class="section-intro">
            <div>
                <span class="section-kicker">Treaty Explorer</span>
                <h2>Browse the treaty status matrix</h2>
                <p>Review treaty adoption details, member-state action, and country status patterns across the continent.</p>
            </div>
            <div class="coverage-chip"><strong id="visibleTreatyCount">{{ $totalTreaties }}</strong> visible treaties</div>
        </section>

        <div class="toolbar">
            <div class="filter-control">
                <label for="filterTreaty">Treaty</label>
                <select id="filterTreaty" onchange="applyFilters()">
                    <option value="">All treaties</option>
                    @foreach($treatiesData as $t)
                        <option value="{{ $t['id'] }}">{{ $t['short_title'] ?: $t['title'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-control">
                <label for="filterCountry">Member State</label>
                <select id="filterCountry" onchange="applyFilters()">
                    <option value="">All countries</option>
                    @foreach($memberStates as $ms)
                        <option value="{{ $ms['country_code'] }}">{{ $ms['country_name'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-control">
                <label for="filterStatus">Status</label>
                <select id="filterStatus" onchange="applyFilters()">
                    <option value="">Any status</option>
                    <option value="signed">Signed</option>
                    <option value="ratified">Ratified</option>
                    <option value="submitted">Instrument submitted</option>
                    <option value="none">No action recorded</option>
                </select>
            </div>

            <div class="filter-control">
                <label for="filterSearch">Search</label>
                <input id="filterSearch" type="search" placeholder="Treaty or country" oninput="applyFilters()">
            </div>

            <div class="toolbar-actions">
                <button class="reset-btn secondary" type="button" onclick="toggleVisibleTreaties(true)">Open</button>
                <button class="reset-btn" type="button" onclick="resetFilters()">Reset</button>
            </div>
        </div>
        <div class="results-summary">
            <div><strong id="visibleRowCount">{{ number_format($totalRows) }}</strong> visible status rows</div>
            <div><strong>{{ number_format($totalRows) }}</strong> total treaty/member-state rows</div>
        </div>
        <div id="filterLoading" class="filter-loading" role="status" aria-live="polite" aria-hidden="true">
            <div class="loading-mark" aria-hidden="true"></div>
            <div class="loading-copy">
                <strong>Rendering selection</strong>
                <span id="filterLoadingText">Preparing treaty cards, map, and matrix rows...</span>
            </div>
            <div class="loading-bars" aria-hidden="true"><i></i><i></i><i></i></div>
        </div>

        <!-- Africa Treaty Status Map -->
        <section class="treaty-map-card" aria-labelledby="treatyMapTitle">
            <div class="treaty-map-head">
                <div>
                    <h2 id="treatyMapTitle">African Continent Treaty Status Map</h2>
                    <p id="treatyMapSubtitle">Countries are colored by their strongest recorded status for the selected treaty view.</p>
                </div>
                <div class="map-status" id="treatyMapStatus">Preparing Africa map...</div>
            </div>
            <div id="treatyAfricaMap"></div>
            <div class="treaty-map-meta">
                <div class="map-count">
                    <div class="k"><span class="map-swatch swatch-signed"></span>Signed</div>
                    <div class="v" id="mapSignedCount">0</div>
                </div>
                <div class="map-count">
                    <div class="k"><span class="map-swatch swatch-ratified"></span>Ratified</div>
                    <div class="v" id="mapRatifiedCount">0</div>
                </div>
                <div class="map-count">
                    <div class="k"><span class="map-swatch swatch-submitted"></span>Instrument Submitted</div>
                    <div class="v" id="mapSubmittedCount">0</div>
                </div>
                <div class="map-count">
                    <div class="k"><span class="map-swatch swatch-none"></span>No Record</div>
                    <div class="v" id="mapNoRecordCount">0</div>
                </div>
            </div>
        </section>

        <!-- ── TREATY CARDS ── -->
        <div class="treaties-list" id="treatyCardList">
            @foreach($treatiesData as $treaty)
                @php
                    $treatyStateCount = max(1, count($treaty['statuses']));
                    $treatySignedPercent = min(100, round(($treaty['signed_count'] / $treatyStateCount) * 100));
                    $treatyRatifiedPercent = min(100, round(($treaty['ratified_count'] / $treatyStateCount) * 100));
                    $treatyPendingCount = collect($treaty['statuses'])->filter(function ($statusRow) {
                        return !($statusRow['is_signed'] ?? false)
                            && !($statusRow['is_ratified'] ?? false)
                            && !($statusRow['is_original_submitted'] ?? false);
                    })->count();
                    $treatySearchText = strtolower(trim(($treaty['title'] ?? '') . ' ' . ($treaty['short_title'] ?? '') . ' ' . ($treaty['reference_code'] ?? '')));
                @endphp
                <div class="treaty-card" data-treaty-id="{{ $treaty['id'] }}" data-search="{{ $treatySearchText }}">
                    <div class="treaty-header" onclick="toggleTreaty(this.closest('.treaty-card'))">
                        <div class="treaty-code">{{ $treaty['reference_code'] ?: ('T-' . str_pad($loop->iteration, 2, '0', STR_PAD_LEFT)) }}</div>
                        <div class="treaty-meta">
                            <h3>{{ $treaty['title'] }}</h3>
                            @if($treaty['short_title'] && $treaty['short_title'] !== $treaty['title'])
                                <div class="short-title">{{ $treaty['short_title'] }}</div>
                            @endif
                            <div class="treaty-badges">
                                @if($treaty['adoption_date'])
                                    <span class="badge badge-date">Adopted: {{ \Carbon\Carbon::parse($treaty['adoption_date'])->format('d M Y') }}</span>
                                @endif
                                @if($treaty['entry_into_force_date'])
                                    <span class="badge badge-date">In force: {{ \Carbon\Carbon::parse($treaty['entry_into_force_date'])->format('d M Y') }}</span>
                                @endif
                                <span class="badge badge-status">{{ ucfirst($treaty['status']) }}</span>
                                <span class="badge badge-signed">&#9998; {{ $treaty['signed_count'] }} Signed</span>
                                <span class="badge badge-ratified">&#10003; {{ $treaty['ratified_count'] }} Ratified</span>
                                @if($treaty['original_submitted_count'])
                                    <span class="badge badge-submitted">&#8679; {{ $treaty['original_submitted_count'] }} Submitted</span>
                                @endif
                                <span class="badge badge-pending">{{ $treatyPendingCount }} Pending</span>
                            </div>
                            <div class="treaty-progress" aria-hidden="true">
                                <div class="progress-item">
                                    <div class="progress-label"><span>Signature coverage</span><span>{{ $treatySignedPercent }}%</span></div>
                                    <div class="progress-track"><div class="progress-fill" style="width: {{ $treatySignedPercent }}%;"></div></div>
                                </div>
                                <div class="progress-item">
                                    <div class="progress-label"><span>Ratification coverage</span><span>{{ $treatyRatifiedPercent }}%</span></div>
                                    <div class="progress-track"><div class="progress-fill ratified" style="width: {{ $treatyRatifiedPercent }}%;"></div></div>
                                </div>
                            </div>
                        </div>
                        <div class="treaty-toggle">+</div>
                    </div>

                    <div class="treaty-description">
                        @if($treaty['description'] || $treaty['overview'])
                            <div class="treaty-desc-grid">
                                @if($treaty['description'])
                                    <div class="desc-section">
                                        <h4>Description</h4>
                                        <p>{{ \Illuminate\Support\Str::limit(strip_tags($treaty['description']), 400) }}</p>
                                    </div>
                                @endif
                                @if($treaty['overview'])
                                    <div class="desc-section">
                                        <h4>Overview</h4>
                                        <p>{{ \Illuminate\Support\Str::limit(strip_tags($treaty['overview']), 400) }}</p>
                                    </div>
                                @endif
                            </div>
                        @endif

                        @if($treaty['read_more_url'])
                            <a class="treaty-read-more" href="{{ $treaty['read_more_url'] }}" target="_blank" rel="noopener">
                                Read full treaty &#8599;
                            </a>
                        @endif

                        @if(count($treaty['statuses']) > 0)
                            <div class="country-table-wrap" style="margin-top:18px;">
                                <table class="country-table" data-treaty="{{ $treaty['id'] }}">
                                    <thead>
                                        <tr>
                                            <th>Member State</th>
                                            <th>Signed</th>
                                            <th>Signed Date</th>
                                            <th>Ratified</th>
                                            <th>Ratified Date</th>
                                            <th>Instrument Submitted</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($treaty['statuses'] as $s)
                                            <tr class="country-row"
                                                data-country="{{ $s['country_code'] }}"
                                                data-signed="{{ $s['is_signed'] ? '1' : '0' }}"
                                                data-ratified="{{ $s['is_ratified'] ? '1' : '0' }}"
                                                data-submitted="{{ $s['is_original_submitted'] ? '1' : '0' }}">
                                                <td class="country-col">{{ $s['country_name'] }}</td>
                                                <td>
                                                    <div class="status-cell">
                                                        <span class="status-dot {{ $s['is_signed'] ? 'dot-yes' : 'dot-no' }}"></span>
                                                        {{ $s['is_signed'] ? 'Yes' : 'No' }}
                                                    </div>
                                                </td>
                                                <td>{{ $s['signed_at'] ? \Carbon\Carbon::parse($s['signed_at'])->format('d M Y') : '-' }}</td>
                                                <td>
                                                    <div class="status-cell">
                                                        <span class="status-dot {{ $s['is_ratified'] ? 'dot-yes' : 'dot-no' }}"></span>
                                                        {{ $s['is_ratified'] ? 'Yes' : 'No' }}
                                                    </div>
                                                </td>
                                                <td>{{ $s['ratified_at'] ? \Carbon\Carbon::parse($s['ratified_at'])->format('d M Y') : '-' }}</td>
                                                <td>
                                                    <div class="status-cell">
                                                        <span class="status-dot {{ $s['is_original_submitted'] ? 'dot-yes' : 'dot-no' }}"></span>
                                                        {{ $s['is_original_submitted'] ? 'Yes' : '-' }}
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p style="color:#6b8676;font-size:.88rem;margin-top:12px;">No member state status records for this treaty.</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <!-- ── FULL STATUS TABLE ── -->
        @if(count($statusTableRows) > 0)
            <div class="full-table-card">
                <div class="full-table-head">
                    <div>
                        <h2>Complete Status Matrix</h2>
                        <p>All treaty and member-state combinations, including pending records.</p>
                    </div>
                    <div class="full-table-meta"><span id="matrixVisibleCount">{{ number_format($totalRows) }}</span> rows shown</div>
                </div>
                <div class="full-table-scroll">
                    <table class="full-table" id="fullStatusTable">
                        <thead>
                            <tr>
                                <th>Treaty</th>
                                <th>Ref. Code</th>
                                <th>Member State</th>
                                <th>Signed</th>
                                <th>Ratified</th>
                                <th>Instrument</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($statusTableRows as $row)
                                <tr class="full-table-row"
                                    data-treaty="{{ $row['treaty_id'] }}"
                                    data-country="{{ $row['country_code'] }}"
                                    data-signed="{{ $row['is_signed'] ? '1' : '0' }}"
                                    data-ratified="{{ $row['is_ratified'] ? '1' : '0' }}"
                                    data-submitted="{{ $row['is_original_submitted'] ? '1' : '0' }}"
                                    data-search="{{ strtolower(trim(($row['treaty_title'] ?? '') . ' ' . ($row['reference_code'] ?? '') . ' ' . ($row['country_name'] ?? ''))) }}">
                                    <td class="treaty-name-col">{{ $row['treaty_title'] }}</td>
                                    <td><span class="badge badge-date">{{ $row['reference_code'] ?: '-' }}</span></td>
                                    <td class="country-col">{{ $row['country_name'] }}</td>
                                    <td>
                                        <span class="status-pill {{ $row['is_signed'] ? 'pill-signed' : 'pill-no' }}">
                                            {{ $row['is_signed'] ? 'Signed' : 'No' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-pill {{ $row['is_ratified'] ? 'pill-ratified' : 'pill-no' }}">
                                            {{ $row['is_ratified'] ? 'Ratified' : 'No' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-pill {{ $row['is_original_submitted'] ? 'pill-submitted' : 'pill-no' }}">
                                            {{ $row['is_original_submitted'] ? 'Submitted' : 'No' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    @endif {{-- end treatiesData not empty --}}
</div>

<!-- ── GALLERY STRIP ── -->
@include('partials.gallery-strip')

<!-- ── FOOTER ── -->
<footer id="contact" class="footer">
    <div class="footer-content">
        <div class="footer-logo">
            <h3>ATTP<span> · Administration</span></h3>
            <p>African Think Tank Platform Administration - supporting African Union institutions through centralized governance, policy coordination, and strategic oversight.</p>
        </div>
        <div class="footer-links">
            <h4>Quick Links</h4>
            <a href="{{ route('landing.index') }}">Home</a>
            <a href="{{ route('impact.map') }}">Impact Map</a>
            <a href="{{ route('news.index') }}">News &amp; Updates</a>
            <a href="{{ route('careers.index') }}">Careers</a>
        </div>
        <div class="footer-contact">
            <h4>Contact</h4>
            <p>Email: attpinfo@africanunion.org</p>
            <p>&copy; 2026 African Think Tank Platform Administration (ATTP)</p>
        </div>
    </div>
    <div class="footer-bottom">
        <p>Supporting African Union policy coordination, governance reform, and evidence-based decision-making across the continent.</p>
    </div>
</footer>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/shpjs@6.2.0/dist/shp.min.js"></script>
<script>
    const treatyShapeFiles = @json($shapeFiles);
    const treatiesMapData = @json($treatiesData);
    const treatyStatusRows = @json($statusTableRows);
    const treatyMapEls = {
        status: document.getElementById('treatyMapStatus'),
        subtitle: document.getElementById('treatyMapSubtitle'),
        signed: document.getElementById('mapSignedCount'),
        ratified: document.getElementById('mapRatifiedCount'),
        submitted: document.getElementById('mapSubmittedCount'),
        none: document.getElementById('mapNoRecordCount')
    };
    const treatyMapColors = { signed: '#2563eb', ratified: '#16a34a', submitted: '#f97316', none: '#d1d5db', filtered: '#e5e7eb' };
    const treatyCountryAliases = {
        'cabo verde': 'CV', 'cape verde': 'CV', 'cote divoire': 'CI', 'cote d ivoire': 'CI',
        'cote dvoire': 'CI', 'ivory coast': 'CI', 'democratic republic of congo': 'CD',
        'democratic republic of the congo': 'CD', 'dem rep congo': 'CD', 'dr congo': 'CD',
        'drc': 'CD', 'congo': 'CG', 'republic of congo': 'CG', 'congo brazzaville': 'CG',
        'sao tome and principe': 'ST', 'sao tome principe': 'ST', 'swaziland': 'SZ',
        'eswatini': 'SZ', 'w sahara': 'EH', 'western sahara': 'EH',
        'sahrawi arab democratic republic': 'EH', 'tanzania': 'TZ',
        'united republic of tanzania': 'TZ', 'south sudan': 'SS', 's sudan': 'SS'
    };
    let treatyMap = null;
    let treatyLayerGroup = null;
    const treatyMapLayers = [];

    function setTreatyMapStatus(message) {
        if (treatyMapEls.status) treatyMapEls.status.textContent = message;
    }

    function normalizeMapCountryName(name) {
        const input = (name || '').toString();
        const normalized = typeof input.normalize === 'function' ? input.normalize('NFD') : input;
        return normalized.replace(/[\u0300-\u036f]/g, '').replace(/[\u2019']/g, '').replace(/[^a-zA-Z0-9 ]/g, ' ').replace(/\s+/g, ' ').trim().toLowerCase();
    }

    const treatyCodeByName = treatyStatusRows.reduce((lookup, row) => {
        const name = normalizeMapCountryName(row.country_name || '');
        const code = String(row.country_code || '').trim().toUpperCase();
        if (name && code) lookup[name] = code;
        return lookup;
    }, {});

    function resolveTreatyCountryCode(countryName) {
        const normalized = normalizeMapCountryName(countryName);
        if (!normalized) return null;
        return treatyCodeByName[normalized] || treatyCountryAliases[normalized] || null;
    }

    function getFeatureCountryName(feature, sourceUrl) {
        const properties = feature && feature.properties ? feature.properties : {};
        const propertyName = properties.NAME || properties.ADMIN || properties.NAME_EN || properties.COUNTRY || properties.name;
        if (propertyName) return propertyName;
        return decodeURIComponent((sourceUrl.split('/').pop() || '').replace(/\.shp$/i, '')) || 'Country';
    }

    function toFeatureCollection(raw) {
        if (!raw) return { type: 'FeatureCollection', features: [] };
        if (raw.type === 'FeatureCollection') return raw;
        if (raw.type === 'Feature') return { type: 'FeatureCollection', features: [raw] };
        if (Array.isArray(raw)) return { type: 'FeatureCollection', features: raw.flatMap(item => toFeatureCollection(item).features) };
        if (typeof raw === 'object') return { type: 'FeatureCollection', features: Object.keys(raw).flatMap(key => toFeatureCollection(raw[key]).features) };
        return { type: 'FeatureCollection', features: [] };
    }

    function buildMapStatusIndex() {
        const selectedTreaty = document.getElementById('filterTreaty')?.value || '';
        const index = {};
        treatyStatusRows.forEach(row => {
            if (selectedTreaty && String(row.treaty_id) !== selectedTreaty) return;
            const code = String(row.country_code || '').trim().toUpperCase();
            if (!code) return;
            const current = index[code] || { country_name: row.country_name || '', signed: false, ratified: false, submitted: false };
            current.country_name = current.country_name || row.country_name || '';
            current.signed = current.signed || !!row.is_signed;
            current.ratified = current.ratified || !!row.is_ratified;
            current.submitted = current.submitted || !!row.is_original_submitted;
            index[code] = current;
        });
        return index;
    }

    function getStrongestStatus(status) {
        if (!status) return 'none';
        if (status.submitted) return 'submitted';
        if (status.ratified) return 'ratified';
        if (status.signed) return 'signed';
        return 'none';
    }

    function statusHasNoAction(status) {
        return !status || (!status.signed && !status.ratified && !status.submitted);
    }

    function mapMatchesStatusFilter(status, statusFilter) {
        if (!statusFilter) return true;
        if (statusFilter === 'none') return statusHasNoAction(status);
        return !!status?.[statusFilter];
    }

    function rowMatchesStatus(row, statusFilter) {
        if (!statusFilter) return true;
        if (statusFilter === 'none') {
            return row.dataset.signed !== '1' && row.dataset.ratified !== '1' && row.dataset.submitted !== '1';
        }
        return row.dataset[statusFilter] === '1';
    }

    function getTreatyLayerStyle(countryName) {
        const countryFilter = document.getElementById('filterCountry')?.value || '';
        const statusFilter = document.getElementById('filterStatus')?.value || '';
        const index = buildMapStatusIndex();
        const code = resolveTreatyCountryCode(countryName);
        const status = code ? index[code] : null;
        const strongest = getStrongestStatus(status);
        const hiddenByCountry = countryFilter && code !== countryFilter;
        const hiddenByStatus = statusFilter && !mapMatchesStatusFilter(status, statusFilter);
        return {
            fillColor: (hiddenByCountry || hiddenByStatus) ? treatyMapColors.filtered : treatyMapColors[strongest],
            weight: (status && !hiddenByCountry && !hiddenByStatus) ? 1.8 : 0.8,
            opacity: 1,
            color: (status && !hiddenByCountry && !hiddenByStatus) ? '#1f2937' : '#ffffff',
            fillOpacity: (hiddenByCountry || hiddenByStatus) ? 0.42 : (status ? 0.88 : 0.55)
        };
    }

    function refreshTreatyMap() {
        if (!treatyMapLayers.length) return;
        const index = buildMapStatusIndex();
        const countryFilter = document.getElementById('filterCountry')?.value || '';
        const statusFilter = document.getElementById('filterStatus')?.value || '';
        const selectedTreaty = document.getElementById('filterTreaty')?.value || '';
        const counts = { signed: 0, ratified: 0, submitted: 0, none: 0 };
        Object.keys(index).forEach(code => {
            const status = index[code];
            if (countryFilter && code !== countryFilter) return;
            if (!mapMatchesStatusFilter(status, statusFilter)) return;
            const strongest = getStrongestStatus(status);
            if (strongest === 'none') counts.none += 1;
            if (status.signed) counts.signed += 1;
            if (status.ratified) counts.ratified += 1;
            if (status.submitted) counts.submitted += 1;
        });
        treatyMapEls.signed.textContent = counts.signed;
        treatyMapEls.ratified.textContent = counts.ratified;
        treatyMapEls.submitted.textContent = counts.submitted;
        treatyMapEls.none.textContent = counts.none;
        treatyMapLayers.forEach(layer => layer.setStyle(getTreatyLayerStyle(layer._countryName)));
        const treaty = selectedTreaty ? treatiesMapData.find(item => String(item.id) === selectedTreaty) : null;
        if (treatyMapEls.subtitle) {
            treatyMapEls.subtitle.textContent = treaty
                ? `${treaty.title}: countries are colored by signed, ratified, and instrument-submitted status.`
                : 'Combined view across all treaties. Countries are colored by their strongest recorded status.';
        }
    }

    async function initializeTreatyAfricaMap() {
        const mapEl = document.getElementById('treatyAfricaMap');
        if (!mapEl || !window.L) return;
        treatyMap = L.map('treatyAfricaMap', { center: [0, 20], zoom: 3, minZoom: 3, maxZoom: 8, scrollWheelZoom: true, zoomControl: true, attributionControl: false });
        treatyLayerGroup = L.featureGroup().addTo(treatyMap);
        if (!Array.isArray(treatyShapeFiles) || treatyShapeFiles.length === 0) {
            setTreatyMapStatus('No Africa shapefiles found in public/assets/Africa.');
            return;
        }
        setTreatyMapStatus(`Loading ${treatyShapeFiles.length} country shapes...`);
        const loaders = treatyShapeFiles.map(async shapeUrl => {
            const resolvedUrl = new URL(shapeUrl, window.location.href).toString();
            const rawShape = await shp(resolvedUrl);
            const featureCollection = toFeatureCollection(rawShape);
            if (!featureCollection.features.length) return;
            L.geoJSON(featureCollection, {
                style: feature => getTreatyLayerStyle(getFeatureCountryName(feature, resolvedUrl)),
                onEachFeature: (feature, layer) => {
                    const countryName = getFeatureCountryName(feature, resolvedUrl);
                    layer._countryName = countryName;
                    treatyMapLayers.push(layer);
                    layer.on({
                        mouseover: event => {
                            const currentLayer = event.target;
                            currentLayer.setStyle({ weight: 3, color: '#fbbc05', fillOpacity: 0.95 });
                            if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) currentLayer.bringToFront();
                            const index = buildMapStatusIndex();
                            const code = resolveTreatyCountryCode(countryName);
                            const status = code ? index[code] : null;
                            currentLayer.bindPopup(`
                                <div class="map-popup-title">${countryName}</div>
                                <div class="map-popup-row">Signed: ${status?.signed ? 'Yes' : 'No'}</div>
                                <div class="map-popup-row">Ratified: ${status?.ratified ? 'Yes' : 'No'}</div>
                                <div class="map-popup-row">Instrument Submitted: ${status?.submitted ? 'Yes' : 'No'}</div>
                            `).openPopup();
                        },
                        mouseout: event => {
                            event.target.setStyle(getTreatyLayerStyle(event.target._countryName));
                            event.target.closePopup();
                        }
                    });
                }
            }).addTo(treatyLayerGroup);
        });
        const results = await Promise.allSettled(loaders);
        const failed = results.filter(result => result.status === 'rejected').length;
        if (treatyLayerGroup.getLayers().length > 0) treatyMap.fitBounds(treatyLayerGroup.getBounds(), { padding: [28, 28], maxZoom: 4 });
        refreshTreatyMap();
        setTreatyMapStatus(failed ? `Map loaded with ${failed} shape file(s) skipped.` : 'Africa treaty map loaded.');
    }

    /* ── Treaty card accordion ── */
    function toggleTreaty(card) {
        const isOpen = card.classList.contains('open');
        // close all
        document.querySelectorAll('.treaty-card.open').forEach(c => c.classList.remove('open'));
        if (!isOpen) card.classList.add('open');
    }

    /* ── Filters ── */
    let filterDebounceTimer = null;
    let filterRenderToken = 0;

    function setFilterLoading(isLoading, message = 'Preparing treaty cards, map, and matrix rows...') {
        const loader = document.getElementById('filterLoading');
        const loaderText = document.getElementById('filterLoadingText');
        if (loaderText) loaderText.textContent = message;
        if (loader) {
            loader.classList.toggle('active', isLoading);
            loader.setAttribute('aria-hidden', isLoading ? 'false' : 'true');
        }
        document.body.classList.toggle('is-filtering', isLoading);
    }

    function applyFilters() {
        window.clearTimeout(filterDebounceTimer);
        const token = ++filterRenderToken;
        setFilterLoading(true, 'Rendering selection across treaty cards, map, and matrix rows...');

        filterDebounceTimer = window.setTimeout(() => {
            window.requestAnimationFrame(() => renderFilters(token));
        }, 120);
    }

    function renderFilters(token) {
        if (token !== filterRenderToken) return;

        const treatyVal  = document.getElementById('filterTreaty').value;
        const countryVal = document.getElementById('filterCountry').value;
        const statusVal  = document.getElementById('filterStatus').value;
        const searchVal  = (document.getElementById('filterSearch')?.value || '').trim().toLowerCase();
        let visibleCards = 0;
        let visibleRows = 0;

        document.querySelectorAll('.treaty-card').forEach(card => {
            const matchTreaty = !treatyVal || card.dataset.treatyId === treatyVal;
            const cardMatchesSearch = !searchVal || (card.dataset.search || '').includes(searchVal);
            let cardVisibleRows = 0;

            card.querySelectorAll('.country-row').forEach(row => {
                const matchCountry = !countryVal || row.dataset.country === countryVal;
                const matchStatus  = rowMatchesStatus(row, statusVal);
                const rowText = `${row.textContent || ''} ${row.dataset.country || ''}`.toLowerCase();
                const matchSearch = !searchVal || cardMatchesSearch || rowText.includes(searchVal);
                const showRow = matchCountry && matchStatus && matchSearch;
                row.style.display = showRow ? '' : 'none';
                if (showRow) cardVisibleRows += 1;
            });

            const hasRowFilters = !!countryVal || !!statusVal || !!searchVal;
            const showCard = matchTreaty && (!hasRowFilters ? cardMatchesSearch : cardVisibleRows > 0);
            card.style.display = showCard ? '' : 'none';
            if (showCard) visibleCards += 1;
        });

        document.querySelectorAll('.full-table-row').forEach(row => {
            const matchTreaty  = !treatyVal  || row.dataset.treaty   === treatyVal;
            const matchCountry = !countryVal || row.dataset.country  === countryVal;
            const matchStatus  = rowMatchesStatus(row, statusVal);
            const matchSearch  = !searchVal || (row.dataset.search || row.textContent || '').toLowerCase().includes(searchVal);
            const showRow = matchTreaty && matchCountry && matchStatus && matchSearch;
            row.style.display = showRow ? '' : 'none';
            if (showRow) visibleRows += 1;
        });

        const visibleTreatyCount = document.getElementById('visibleTreatyCount');
        const visibleRowCount = document.getElementById('visibleRowCount');
        const matrixVisibleCount = document.getElementById('matrixVisibleCount');
        if (visibleTreatyCount) visibleTreatyCount.textContent = visibleCards.toLocaleString();
        if (visibleRowCount) visibleRowCount.textContent = visibleRows.toLocaleString();
        if (matrixVisibleCount) matrixVisibleCount.textContent = visibleRows.toLocaleString();

        refreshTreatyMap();
        window.requestAnimationFrame(() => {
            if (token === filterRenderToken) {
                setFilterLoading(false);
            }
        });
    }

    function resetFilters() {
        document.getElementById('filterTreaty').value  = '';
        document.getElementById('filterCountry').value = '';
        document.getElementById('filterStatus').value  = '';
        const searchInput = document.getElementById('filterSearch');
        if (searchInput) searchInput.value = '';
        applyFilters();
    }

    function toggleVisibleTreaties(open) {
        document.querySelectorAll('.treaty-card').forEach(card => {
            if (card.style.display === 'none') return;
            card.classList.toggle('open', open);
        });
    }

    /* ── Lang switcher close on outside click ── */
    document.addEventListener('click', function(e) {
        document.querySelectorAll('.lang-switcher.open').forEach(function(el) {
            if (!el.contains(e.target)) el.classList.remove('open');
        });
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.lang-switcher.open').forEach(el => el.classList.remove('open'));
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        applyFilters();
        initializeTreatyAfricaMap();
    });
</script>
</body>
</html>
