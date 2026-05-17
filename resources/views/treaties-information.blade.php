<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AU Treaties Information | ATTP Impact</title>
    <meta name="description" content="African Union treaties status — signatures, ratifications, and submissions by member state.">
    <link rel="icon" href="{{ asset('assets/images/au.png') }}" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
        }

        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--light); color: #1a2e22; }

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
            background: linear-gradient(135deg, rgba(0,77,46,.96), rgba(0,107,63,.9)),
                        url('{{ asset('assets/images/au3.jpg') }}') center/cover no-repeat;
            padding: 64px 24px 80px;
            text-align: center; color: #fff;
        }
        .treaties-hero .breadcrumb {
            font-size: .82rem; color: rgba(255,255,255,.65); margin-bottom: 14px;
        }
        .treaties-hero .breadcrumb a { color: var(--gold); text-decoration: none; }
        .treaties-hero .breadcrumb a:hover { text-decoration: underline; }
        .treaties-hero h1 { font-size: 2.6rem; margin: 0 0 14px; color: var(--gold); }
        .treaties-hero p { max-width: 680px; margin: 0 auto; line-height: 1.75; opacity: .9; font-size: 1rem; }

        /* ── STATS BAR ── */
        .stats-bar {
            max-width: 1160px; margin: -40px auto 0; padding: 0 24px;
            position: relative; z-index: 10;
        }
        .stats-grid {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px;
        }
        .stat-card {
            background: #fff; border-radius: 14px; border: 1px solid #e0ebe5;
            box-shadow: 0 8px 24px rgba(0,0,0,.1);
            padding: 20px 22px; text-align: center;
        }
        .stat-number { font-size: 2.2rem; font-weight: 800; color: var(--au-green); line-height: 1; margin-bottom: 6px; }
        .stat-label { font-size: .82rem; color: #6b8676; font-weight: 500; }
        .stat-card.gold .stat-number { color: #b8860b; }
        .stat-card.blue .stat-number { color: #1e5fa3; }
        .stat-card.orange .stat-number { color: #c0502a; }

        /* ── MAIN LAYOUT ── */
        .page-wrap { max-width: 1160px; margin: 48px auto 60px; padding: 0 24px; }

        /* ── TOOLBAR ── */
        .toolbar {
            background: #fff; border-radius: 12px; border: 1px solid #e0ebe5;
            padding: 16px 20px; display: flex; gap: 12px; flex-wrap: wrap;
            align-items: center; margin-bottom: 28px;
            box-shadow: 0 2px 8px rgba(0,0,0,.05);
        }
        .toolbar label { font-size: .82rem; font-weight: 600; color: #5a7065; white-space: nowrap; }
        .toolbar select, .toolbar input {
            border: 1.5px solid #d0dcd5; border-radius: 8px; padding: 9px 12px;
            font: inherit; font-size: .88rem; background: #f7faf8; outline: none;
            transition: border-color .2s; min-width: 180px;
        }
        .toolbar select:focus, .toolbar input:focus {
            border-color: var(--au-green); background: #fff;
        }
        .toolbar-spacer { flex: 1; }
        .reset-btn {
            padding: 9px 18px; border-radius: 8px; border: 1.5px solid #c8d8ce;
            background: #fff; color: #4a6355; font-size: .85rem; font-weight: 600;
            cursor: pointer; white-space: nowrap; transition: all .2s; text-decoration: none;
            display: inline-block;
        }
        .reset-btn:hover { background: var(--au-green); color: #fff; border-color: var(--au-green); }

        /* ── TREATY CARDS ── */
        .treaties-list { display: flex; flex-direction: column; gap: 20px; margin-bottom: 48px; }

        .treaty-card {
            background: #fff; border-radius: 14px; border: 1px solid #e0ebe5;
            box-shadow: 0 2px 10px rgba(0,0,0,.05); overflow: hidden;
        }
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
            max-height: 2000px; padding: 20px 24px;
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
            background: var(--au-green-dark); color: #fff; padding: 8px 12px;
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
            box-shadow: 0 2px 10px rgba(0,0,0,.05); overflow: hidden;
        }
        .full-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        .full-table th {
            background: #f0f5f1; color: #2a3d30; padding: 11px 14px;
            text-align: left; font-weight: 700; border-bottom: 2px solid #d0dcd5;
            white-space: nowrap; position: sticky; top: 0;
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
            .treaty-desc-grid { grid-template-columns: 1fr; }
            .treaties-hero h1 { font-size: 1.9rem; }
        }
        @media (max-width: 600px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .toolbar { flex-direction: column; align-items: stretch; }
            .toolbar select, .toolbar input { min-width: 0; width: 100%; }
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
<section class="treaties-hero">
    <div class="breadcrumb">
        <a href="{{ route('impact.map') }}">Impact Map</a>
        &nbsp;/&nbsp; AU Treaties Information
    </div>
    <h1>AU Treaties Information</h1>
    <p>Comprehensive status of African Union treaties — tracking signatures, ratifications, and instrument submissions by member state.</p>
</section>

<!-- ── STATS BAR ── -->
@php
    $totalTreaties    = count($treatiesData);
    $totalSigned      = collect($treatiesData)->sum('signed_count');
    $totalRatified    = collect($treatiesData)->sum('ratified_count');
    $totalSubmitted   = collect($treatiesData)->sum('original_submitted_count');
    $totalCountries   = count($memberStates);
@endphp

<div class="stats-bar">
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number">{{ $totalTreaties }}</div>
            <div class="stat-label">AU Treaties</div>
        </div>
        <div class="stat-card gold">
            <div class="stat-number">{{ $totalSigned }}</div>
            <div class="stat-label">Signatures recorded</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-number">{{ $totalRatified }}</div>
            <div class="stat-label">Ratifications</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-number">{{ $totalCountries }}</div>
            <div class="stat-label">Member states</div>
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
        <div class="toolbar">
            <label for="filterTreaty">Treaty</label>
            <select id="filterTreaty" onchange="applyFilters()">
                <option value="">All treaties</option>
                @foreach($treatiesData as $t)
                    <option value="{{ $t['id'] }}">{{ $t['short_title'] ?: $t['title'] }}</option>
                @endforeach
            </select>

            <label for="filterCountry">Member State</label>
            <select id="filterCountry" onchange="applyFilters()">
                <option value="">All countries</option>
                @foreach($memberStates as $ms)
                    <option value="{{ $ms['country_code'] }}">{{ $ms['country_name'] }}</option>
                @endforeach
            </select>

            <label for="filterStatus">Status</label>
            <select id="filterStatus" onchange="applyFilters()">
                <option value="">Any status</option>
                <option value="signed">Signed</option>
                <option value="ratified">Ratified</option>
                <option value="submitted">Instrument submitted</option>
            </select>

            <div class="toolbar-spacer"></div>
            <button class="reset-btn" onclick="resetFilters()">Reset filters</button>
        </div>

        <!-- ── TREATY CARDS ── -->
        <div class="treaties-list" id="treatyCardList">
            @foreach($treatiesData as $treaty)
                <div class="treaty-card" data-treaty-id="{{ $treaty['id'] }}">
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
                                                <td>{{ $s['signed_at'] ? \Carbon\Carbon::parse($s['signed_at'])->format('d M Y') : '—' }}</td>
                                                <td>
                                                    <div class="status-cell">
                                                        <span class="status-dot {{ $s['is_ratified'] ? 'dot-yes' : 'dot-no' }}"></span>
                                                        {{ $s['is_ratified'] ? 'Yes' : 'No' }}
                                                    </div>
                                                </td>
                                                <td>{{ $s['ratified_at'] ? \Carbon\Carbon::parse($s['ratified_at'])->format('d M Y') : '—' }}</td>
                                                <td>
                                                    <div class="status-cell">
                                                        <span class="status-dot {{ $s['is_original_submitted'] ? 'dot-yes' : 'dot-no' }}"></span>
                                                        {{ $s['is_original_submitted'] ? 'Yes' : '—' }}
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
            <h2 class="section-heading">Complete Status Matrix</h2>
            <div class="full-table-card">
                <div style="overflow-x:auto;">
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
                                    data-submitted="{{ $row['is_original_submitted'] ? '1' : '0' }}">
                                    <td class="treaty-name-col">{{ $row['treaty_title'] }}</td>
                                    <td><span class="badge badge-date">{{ $row['reference_code'] ?: '—' }}</span></td>
                                    <td class="country-col">{{ $row['country_name'] }}</td>
                                    <td>
                                        <span class="status-pill {{ $row['is_signed'] ? 'pill-yes' : 'pill-no' }}">
                                            {{ $row['is_signed'] ? 'Signed' : 'No' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-pill {{ $row['is_ratified'] ? 'pill-yes' : 'pill-no' }}">
                                            {{ $row['is_ratified'] ? 'Ratified' : 'No' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-pill {{ $row['is_original_submitted'] ? 'pill-yes' : 'pill-no' }}">
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

<!-- ── FOOTER ── -->
<footer id="contact" class="footer">
    <div class="footer-content">
        <div class="footer-logo">
            <h3>ATTP<span> · Administration</span></h3>
            <p>African Think Tank Platform Administration — supporting African Union institutions through centralized governance, policy coordination, and strategic oversight.</p>
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

<script>
    /* ── Treaty card accordion ── */
    function toggleTreaty(card) {
        const isOpen = card.classList.contains('open');
        // close all
        document.querySelectorAll('.treaty-card.open').forEach(c => c.classList.remove('open'));
        if (!isOpen) card.classList.add('open');
    }

    /* ── Filters ── */
    function applyFilters() {
        const treatyVal  = document.getElementById('filterTreaty').value;
        const countryVal = document.getElementById('filterCountry').value;
        const statusVal  = document.getElementById('filterStatus').value;

        // Treaty cards
        document.querySelectorAll('.treaty-card').forEach(card => {
            const matchTreaty = !treatyVal || card.dataset.treatyId === treatyVal;
            card.style.display = matchTreaty ? '' : 'none';

            // rows inside
            card.querySelectorAll('.country-row').forEach(row => {
                const matchCountry = !countryVal || row.dataset.country === countryVal;
                const matchStatus  = !statusVal  || row.dataset[statusVal] === '1';
                row.style.display = (matchCountry && matchStatus) ? '' : 'none';
            });
        });

        // Full status table
        document.querySelectorAll('.full-table-row').forEach(row => {
            const matchTreaty  = !treatyVal  || row.dataset.treaty   === treatyVal;
            const matchCountry = !countryVal || row.dataset.country  === countryVal;
            const matchStatus  = !statusVal  || row.dataset[statusVal] === '1';
            row.style.display = (matchTreaty && matchCountry && matchStatus) ? '' : 'none';
        });
    }

    function resetFilters() {
        document.getElementById('filterTreaty').value  = '';
        document.getElementById('filterCountry').value = '';
        document.getElementById('filterStatus').value  = '';
        applyFilters();
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
</script>
</body>
</html>
