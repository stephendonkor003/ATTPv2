<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Impact Map - ATTP Africa</title>

    <meta name="description"
        content="Explore ATTP's impact across Africa with our interactive map showing funding partners and projects by country and region." />
    <meta name="keywords"
        content="ATTP impact, Africa projects, funding map, regional development, think tank projects" />

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('assets/style.css') }}" />

    <!-- Leaflet CSS for mapping -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <!-- Chart.js for comparisons -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- ApexCharts for advanced charts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <!-- Bootstrap 5 CSS for DataTables -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

    @if (app()->getLocale() === 'ar')
        <link rel="stylesheet" href="{{ asset('assets/css/rtl.css') }}">
    @endif

    <style>
        :root {
            --gold: #fbbc05;
            --orange: #e16435;
            --magenta: #a70d53;
            --wine: #522b39;
            --light: #f7f4f2;
            --dark: #1a1a1a;
            --success: #10b981;
            --info: #3b82f6;
        }

        body {
            font-family: "Inter", sans-serif;
            background: var(--light);
            color: #333;
            margin: 0;
            padding: 0;
        }

        /* Hero Section */
        .impact-hero {
            position: relative;
            height: 300px;
            background: linear-gradient(135deg, var(--wine) 0%, var(--magenta) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #fff;
            overflow: hidden;
        }

        .impact-hero::before {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(251, 188, 5, 0.1) 0%, transparent 70%);
            animation: pulse 15s ease-in-out infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }
        }

        .impact-hero .content {
            position: relative;
            z-index: 2;
            max-width: 900px;
            padding: 1rem;
        }

        .impact-hero h1 {
            color: var(--gold);
            font-size: 2.4rem;
            margin-bottom: 0.5rem;
        }

        .impact-hero p {
            font-size: 1rem;
            line-height: 1.6;
            opacity: 0.95;
        }

        /* Summary Cards */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin: -50px auto 2rem;
            max-width: 1400px;
            padding: 0 2rem;
            position: relative;
            z-index: 10;
        }

        .summary-card {
            background: #fff;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
            transition: all 0.3s;
        }

        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(167, 13, 83, 0.2);
        }

        .summary-card .icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .summary-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--wine);
        }

        .summary-card .label {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.3rem;
        }

        /* Main Container */
        .impact-main {
            max-width: 1600px;
            margin: 2rem auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 2rem;
        }

        /* Filter Sidebar */
        .filter-sidebar {
            background: #fff;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .filter-sidebar h3 {
            color: var(--wine);
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--gold);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .filter-section {
            margin-bottom: 1.5rem;
        }

        .filter-section h4 {
            color: var(--magenta);
            font-size: 0.9rem;
            margin-bottom: 0.8rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .filter-count {
            background: var(--gold);
            color: var(--wine);
            padding: 0.2rem 0.6rem;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .filter-options {
            max-height: 200px;
            overflow-y: auto;
        }

        .filter-checkbox {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            padding: 0.4rem 0.5rem;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-checkbox:hover {
            background: rgba(251, 188, 5, 0.15);
        }

        .filter-checkbox input[type="checkbox"] {
            margin-right: 0.6rem;
            cursor: pointer;
            accent-color: var(--magenta);
        }

        .filter-checkbox label {
            font-size: 0.85rem;
            cursor: pointer;
            flex: 1;
        }

        .filter-actions {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 0.8rem;
        }

        .filter-action-btn {
            background: none;
            border: 1px solid var(--magenta);
            color: var(--magenta);
            padding: 0.3rem 0.6rem;
            border-radius: 5px;
            font-size: 0.7rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-action-btn:hover {
            background: var(--magenta);
            color: #fff;
        }

        .filter-reset {
            background: var(--magenta);
            color: #fff;
            border: none;
            padding: 0.6rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
            margin-top: 1rem;
        }

        .filter-reset:hover {
            background: var(--wine);
        }

        /* Download Buttons */
        .download-section {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 2px solid #e5e7eb;
        }

        .download-section h4 {
            color: var(--wine);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .download-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 0.5rem;
            text-decoration: none;
            justify-content: center;
        }

        .download-btn.pdf {
            background: #fff;
            border-color: #dc2626;
            color: #dc2626;
        }

        .download-btn.pdf:hover {
            background: #dc2626;
            color: #fff;
        }

        .download-btn.excel {
            background: #fff;
            border-color: #059669;
            color: #059669;
        }

        .download-btn.excel:hover {
            background: #059669;
            color: #fff;
        }

        /* Content Area */
        .content-area {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        /* Tabs */
        .tabs {
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .tab-buttons {
            display: flex;
            border-bottom: 2px solid #e5e7eb;
            overflow-x: auto;
        }

        .tab-button {
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            color: #666;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .tab-button.active {
            color: var(--magenta);
            border-bottom-color: var(--magenta);
            background: rgba(167, 13, 83, 0.05);
        }

        .tab-button:hover:not(.active) {
            background: rgba(0, 0, 0, 0.02);
        }

        .tab-content {
            display: none;
            padding: 2rem;
        }

        .tab-content.active {
            display: block;
        }

        /* Map Container */
        #africa-map {
            width: 100%;
            height: 550px;
            border-radius: 12px;
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
        }

        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .chart-card {
            background: #fff;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
        }

        .chart-card h4 {
            color: var(--wine);
            margin-bottom: 1rem;
            font-size: 1rem;
        }

        /* Data Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .data-table th {
            background: var(--wine);
            color: #fff;
            padding: 0.8rem 1rem;
            text-align: left;
            font-size: 0.85rem;
        }

        .data-table td {
            padding: 0.8rem 1rem;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.9rem;
        }

        .data-table tr:hover {
            background: rgba(167, 13, 83, 0.05);
        }

        .data-table .amount {
            font-weight: 600;
            color: var(--success);
        }

        /* DataTables Custom Styling */
        #countriesTable thead th {
            background: var(--wine) !important;
            color: #fff !important;
            border-bottom: none !important;
            font-size: 0.85rem;
            padding: 1rem;
        }

        #countriesTable tbody td {
            vertical-align: middle;
            font-size: 0.9rem;
        }

        #countriesTable tbody td.amount {
            font-weight: 600;
            color: var(--success);
        }

        #countriesTable tbody tr:hover {
            background: rgba(167, 13, 83, 0.08) !important;
        }

        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 0.4rem 0.8rem;
        }

        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: var(--magenta);
            outline: none;
            box-shadow: 0 0 0 2px rgba(167, 13, 83, 0.1);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--magenta) !important;
            border-color: var(--magenta) !important;
            color: #fff !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--wine) !important;
            border-color: var(--wine) !important;
            color: #fff !important;
        }

        .dataTables_wrapper .dataTables_info {
            color: #666;
            font-size: 0.85rem;
        }

        /* Partner Cards */
        .partner-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .partner-card {
            background: linear-gradient(135deg, var(--wine) 0%, var(--magenta) 100%);
            color: #fff;
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s;
        }

        .partner-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(167, 13, 83, 0.3);
        }

        .partner-card h4 {
            color: var(--gold);
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .partner-stat {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .partner-stat span:last-child {
            font-weight: 600;
        }

        /* Region Cards */
        .region-card {
            background: #fff;
            border-radius: 12px;
            padding: 1.5rem;
            border-left: 4px solid var(--magenta);
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s;
        }

        .region-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 20px rgba(167, 13, 83, 0.15);
        }

        .region-card h4 {
            color: var(--wine);
            margin-bottom: 0.3rem;
        }

        .region-card .abbr {
            color: var(--magenta);
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }

        /* Aspiration Cards */
        .aspiration-card {
            background: linear-gradient(135deg, #7c3aed 0%, #a78bfa 100%);
            color: #fff;
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s;
        }

        .aspiration-card:hover {
            transform: scale(1.02);
        }

        .aspiration-number {
            background: rgba(255, 255, 255, 0.2);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        /* Goals Pills */
        .goal-pill {
            display: inline-block;
            background: var(--gold);
            color: var(--wine);
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            margin: 0.2rem;
        }

        /* Country Tags */
        .country-tag {
            display: inline-block;
            background: #e5e7eb;
            color: #374151;
            padding: 0.3rem 0.6rem;
            border-radius: 5px;
            font-size: 0.75rem;
            margin: 0.15rem;
        }

        /* Continental Badge */
        .continental-badge {
            background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
            color: #fff;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* Scope Filter Badges */
        .scope-badge {
            display: inline-block;
            margin-right: 0.3rem;
            font-size: 1rem;
        }

        .continental-filter label {
            color: var(--success);
            font-weight: 500;
        }

        .targeted-filter label {
            color: var(--magenta);
            font-weight: 500;
        }

        .filter-checkbox.continental-filter:hover {
            background: rgba(16, 185, 129, 0.1);
        }

        .filter-checkbox.targeted-filter:hover {
            background: rgba(167, 13, 83, 0.1);
        }

        /* Request Form */
        .request-form-section {
            max-width: 800px;
            margin: 0 auto;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--wine);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 0.8rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--magenta);
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .submit-btn {
            background: linear-gradient(135deg, var(--magenta) 0%, var(--wine) 100%);
            color: #fff;
            border: none;
            padding: 1rem 2rem;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(167, 13, 83, 0.3);
        }

        /* Leaflet Popup */
        .leaflet-popup-content-wrapper {
            background: rgba(26, 26, 26, 0.95);
            color: #fff;
            border-radius: 12px;
            padding: 0;
        }

        .popup-content {
            padding: 1.2rem;
        }

        .popup-country {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--gold);
            margin-bottom: 0.8rem;
            border-bottom: 2px solid var(--magenta);
            padding-bottom: 0.5rem;
        }

        .popup-stat {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.4rem;
            font-size: 0.9rem;
        }

        /* Success Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.show {
            display: flex;
        }

        .success-modal {
            background: #fff;
            border-radius: 20px;
            padding: 3rem 2.5rem;
            max-width: 500px;
            width: 90%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.4s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #999;
            cursor: pointer;
        }

        .modal-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .modal-title {
            color: var(--wine);
            font-size: 1.8rem;
            margin-bottom: 1rem;
        }

        .modal-message {
            color: #555;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .modal-button {
            background: linear-gradient(135deg, var(--magenta) 0%, var(--wine) 100%);
            color: #fff;
            border: none;
            padding: 0.9rem 2.5rem;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
        }

        /* Loading Spinner */
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 3rem;
        }

        .loading-spinner.show {
            display: block;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #e5e7eb;
            border-top-color: var(--magenta);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .impact-main {
                grid-template-columns: 1fr;
            }

            .filter-sidebar {
                position: static;
            }

            .charts-grid {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
                margin: -30px 1rem 1rem;
            }

            .impact-hero h1 {
                font-size: 1.8rem;
            }

            .tab-buttons {
                flex-wrap: nowrap;
                overflow-x: auto;
            }

            .tab-button {
                padding: 0.8rem 1rem;
                font-size: 0.85rem;
            }
        }
    </style>
</head>

<body>

    <!-- Navbar -->
    <header class="navbar">
        <div class="logo">
            <img src="{{ asset('assets/images/au.png') }}" class="logo logo-sm" alt="ATTP">
        </div>

        <nav class="nav-links">
            <a href="{{ route('landing.index') }}">{{ __('navigation.home') }}</a>
            <a href="{{ route('events') }}">{{ __('navigation.events') }}</a>
            <a href="{{ route('impact.map') }}" class="active">{{ __('navigation.impact_map') }}</a>
            <a href="{{ route('careers.index') }}">{{ __('navigation.careers') }}</a>
            <a href="{{ route('applicants.faq') }}">{{ __('navigation.faqs') }}</a>
        </nav>

        <div class="nav-actions">
            <x-language-selector style="landing" />
            <a href="{{ route('login') }}" class="btn btn-login">{{ __('navigation.login') }}</a>
            <a href="{{ route('public.procurement.index') }}" class="btn btn-primary">
                {{ __('landing.policy_programs') }}
            </a>
        </div>
    </header>

    <!-- Hero -->
    <section class="impact-hero">
        <div class="content">
            <h1>ATTP Impact Analytics Dashboard</h1>
            <p>
                Comprehensive insights into funding partners, regional impact, and project distribution across Africa.
                Powered by real-time program funding data aligned with AU Agenda 2063.
            </p>
        </div>
    </section>

    <!-- Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="icon">💰</div>
            <div class="value">USD {{ number_format($summary['total_funding'] / 1000000, 1) }}M</div>
            <div class="label">Total Funding</div>
        </div>
        <div class="summary-card">
            <div class="icon">📊</div>
            <div class="value">{{ $summary['total_programs'] }}</div>
            <div class="label">Active Programs</div>
        </div>
        <div class="summary-card">
            <div class="icon">🤝</div>
            <div class="value">{{ $summary['total_partners'] }}</div>
            <div class="label">Funding Partners</div>
        </div>
        <div class="summary-card">
            <div class="icon">🌍</div>
            <div class="value">{{ $summary['total_countries'] }}</div>
            <div class="label">Countries Reached</div>
        </div>
        <div class="summary-card">
            <div class="icon">🏛️</div>
            <div class="value">{{ $summary['total_regions'] }}</div>
            <div class="label">Regional Blocks</div>
        </div>
        <div class="summary-card">
            <div class="icon">🎯</div>
            <div class="value">{{ $summary['total_aspirations'] }}</div>
            <div class="label">Agenda 2063 Aspirations</div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="impact-main">

        <!-- Filter Sidebar -->
        <aside class="filter-sidebar">
            <h3>
                Filters
                <span class="filter-count" id="active-filters">All</span>
            </h3>

            <!-- Program Scope Filter -->
            <div class="filter-section">
                <h4>
                    Program Scope
                    <span class="filter-count">2</span>
                </h4>
                <div class="filter-options">
                    <div class="filter-checkbox continental-filter">
                        <input type="checkbox" id="filter-continental" value="continental" class="filter-scope" checked>
                        <label for="filter-continental">
                            <span class="scope-badge continental">🌍</span>
                            Continental Initiatives (All 55 States)
                        </label>
                    </div>
                    <div class="filter-checkbox targeted-filter">
                        <input type="checkbox" id="filter-targeted" value="targeted" class="filter-scope" checked>
                        <label for="filter-targeted">
                            <span class="scope-badge targeted">🎯</span>
                            Targeted Programs (Specific Countries)
                        </label>
                    </div>
                </div>
                <div class="scope-summary"
                    style="margin-top: 0.5rem; padding: 0.5rem; background: rgba(167, 13, 83, 0.05); border-radius: 5px; font-size: 0.8rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.3rem;">
                        <span>Continental:</span>
                        <strong>{{ $summary['continental_programs'] }} programs</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Targeted:</span>
                        <strong>{{ $summary['targeted_programs'] }} programs</strong>
                    </div>
                </div>
            </div>

            <!-- Funding Partners Filter -->
            <div class="filter-section">
                <h4>
                    Funding Partners
                    <span class="filter-count">{{ count($filterOptions['funders']) }}</span>
                </h4>
                <div class="filter-actions">
                    <button class="filter-action-btn" onclick="selectAll('funder')">All</button>
                    <button class="filter-action-btn" onclick="deselectAll('funder')">None</button>
                </div>
                <div class="filter-options">
                    @foreach ($filterOptions['funders'] as $funder)
                        <div class="filter-checkbox">
                            <input type="checkbox" id="funder-{{ $funder->id }}" value="{{ $funder->id }}"
                                class="filter-funder" checked>
                            <label for="funder-{{ $funder->id }}">{{ $funder->name }}</label>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Regional Blocks Filter -->
            <div class="filter-section">
                <h4>
                    Regional Blocks
                    <span class="filter-count">{{ count($filterOptions['regions']) }}</span>
                </h4>
                <div class="filter-actions">
                    <button class="filter-action-btn" onclick="selectAll('region')">All</button>
                    <button class="filter-action-btn" onclick="deselectAll('region')">None</button>
                </div>
                <div class="filter-options">
                    @foreach ($filterOptions['regions'] as $region)
                        <div class="filter-checkbox">
                            <input type="checkbox" id="region-{{ $region->id }}" value="{{ $region->id }}"
                                class="filter-region" checked>
                            <label for="region-{{ $region->id }}">{{ $region->abbreviation }} -
                                {{ $region->name }}</label>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Aspirations Filter -->
            <div class="filter-section">
                <h4>
                    Agenda 2063 Aspirations
                    <span class="filter-count">{{ count($filterOptions['aspirations']) }}</span>
                </h4>
                <div class="filter-actions">
                    <button class="filter-action-btn" onclick="selectAll('aspiration')">All</button>
                    <button class="filter-action-btn" onclick="deselectAll('aspiration')">None</button>
                </div>
                <div class="filter-options">
                    @foreach ($filterOptions['aspirations'] as $aspiration)
                        <div class="filter-checkbox">
                            <input type="checkbox" id="aspiration-{{ $aspiration->id }}"
                                value="{{ $aspiration->id }}" class="filter-aspiration" checked>
                            <label for="aspiration-{{ $aspiration->id }}">Asp. {{ $aspiration->number }}:
                                {{ Str::limit($aspiration->title, 30) }}</label>
                        </div>
                    @endforeach
                </div>
            </div>

            <button class="filter-reset" onclick="resetFilters()">Reset All Filters</button>

            <!-- Download Section -->
            <div class="download-section">
                <h4>Download Reports</h4>
                <a href="{{ route('impact.download.pdf') }}" class="download-btn pdf" id="download-pdf">
                    <span>📄</span> Download PDF Report
                </a>
                <a href="{{ route('impact.download.excel') }}" class="download-btn excel" id="download-excel">
                    <span>📊</span> Download Excel/CSV
                </a>
            </div>
        </aside>

        <!-- Content Area -->
        <div class="content-area">
            <div class="tabs">
                <div class="tab-buttons">
                    <button class="tab-button active" data-tab="map">Interactive Map</button>
                    <button class="tab-button" data-tab="partners">Funding Partners</button>
                    <button class="tab-button" data-tab="regions">Regional Analysis</button>
                    <button class="tab-button" data-tab="agenda">Agenda 2063</button>
                    <button class="tab-button" data-tab="trends">Trends & Charts</button>
                    <button class="tab-button" data-tab="request">Request Information</button>
                </div>

                <!-- Map Tab -->
                <div class="tab-content active" id="map-tab">
                    <h2 style="color: var(--wine); margin-bottom: 1rem;">Africa Impact Map</h2>
                    <p style="margin-bottom: 1.5rem; color: #666;">
                        Hover over countries to view funding details. Countries with programs are highlighted.
                        @if ($summary['continental_programs'] > 0)
                            <span class="continental-badge">{{ $summary['continental_programs'] }} Continental
                                Initiative(s)</span>
                        @endif
                    </p>
                    <div id="africa-map"></div>

                    <!-- Top Countries Table with DataTable -->
                    @if (count($fundingByCountry) > 0)
                        <h3 style="color: var(--wine); margin: 2rem 0 1rem;">Top Beneficiary Countries</h3>
                        <div class="table-responsive" style="background: #fff; border-radius: 12px; padding: 1rem;">
                            <table id="countriesTable" class="table table-striped table-hover" style="width: 100%;">
                                <thead style="background: var(--wine); color: #fff;">
                                    <tr>
                                        <th>Country</th>
                                        <th>Direct Funding (USD)</th>
                                        <th>Continental Share (USD)</th>
                                        <th>Programs</th>
                                        <th>Regions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($fundingByCountry as $country)
                                        <tr>
                                            <td>
                                                <strong>{{ $country['name'] }}</strong>
                                                <span
                                                    style="color: #999; font-size: 0.8rem;">({{ $country['code'] }})</span>
                                            </td>
                                            <td class="amount" data-order="{{ $country['direct_funding'] }}">
                                                {{ number_format($country['direct_funding'], 0) }}
                                            </td>
                                            <td style="color: #666;"
                                                data-order="{{ $country['continental_funding'] }}">
                                                {{ number_format($country['continental_funding'], 0) }}
                                            </td>
                                            <td>{{ $country['total_programs'] }}</td>
                                            <td>
                                                @foreach ($country['regions'] as $region)
                                                    <span class="country-tag">{{ $region }}</span>
                                                @endforeach
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <!-- Partners Tab -->
                <div class="tab-content" id="partners-tab">
                    <h2 style="color: var(--wine); margin-bottom: 1.5rem;">Funding Partners Overview</h2>

                    @if (count($fundingByPartner) > 0)
                        <div class="partner-grid">
                            @foreach ($fundingByPartner as $partner)
                                <div class="partner-card">
                                    <h4>{{ $partner['name'] }}</h4>
                                    <div class="partner-stat">
                                        <span>Total Funding:</span>
                                        <span>USD {{ number_format($partner['total_funding'], 0) }}</span>
                                    </div>
                                    <div class="partner-stat">
                                        <span>Programs:</span>
                                        <span>{{ $partner['program_count'] }}</span>
                                    </div>
                                    <div class="partner-stat">
                                        <span>Countries:</span>
                                        <span>{{ $partner['country_count'] }}{{ $partner['has_continental'] ? ' (Continental)' : '' }}</span>
                                    </div>
                                    <div class="partner-stat">
                                        <span>Regions:</span>
                                        <span>{{ implode(', ', $partner['regions']) ?: 'N/A' }}</span>
                                    </div>
                                    <div
                                        style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.2);">
                                        <span style="font-size: 0.85rem; opacity: 0.9;">Aspirations Addressed:</span>
                                        <div style="margin-top: 0.5rem;">
                                            @foreach ($partner['aspirations'] as $asp)
                                                <span class="goal-pill">Asp. {{ $asp }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p style="text-align: center; color: #999; padding: 3rem;">No funding partner data available.
                        </p>
                    @endif
                </div>

                <!-- Regions Tab -->
                <div class="tab-content" id="regions-tab">
                    <h2 style="color: var(--wine); margin-bottom: 1.5rem;">Regional Economic Communities</h2>

                    @if (count($fundingByRegion) > 0)
                        <div class="partner-grid">
                            @foreach ($fundingByRegion as $region)
                                <div class="region-card">
                                    <h4>{{ $region['name'] }}</h4>
                                    <div class="abbr">{{ $region['abbreviation'] }}</div>
                                    <div class="partner-stat">
                                        <span>Total Funding:</span>
                                        <span class="amount">USD
                                            {{ number_format($region['total_funding'], 0) }}</span>
                                    </div>
                                    <div class="partner-stat">
                                        <span>Programs:</span>
                                        <span>{{ $region['program_count'] }}</span>
                                    </div>
                                    <div class="partner-stat">
                                        <span>Partners:</span>
                                        <span>{{ $region['partner_count'] }}</span>
                                    </div>
                                    <div class="partner-stat">
                                        <span>Member States:</span>
                                        <span>{{ $region['country_count'] }}</span>
                                    </div>
                                    <div style="margin-top: 1rem;">
                                        @foreach (array_slice($region['countries'], 0, 5) as $country)
                                            <span class="country-tag">{{ $country }}</span>
                                        @endforeach
                                        @if (count($region['countries']) > 5)
                                            <span class="country-tag">+{{ count($region['countries']) - 5 }}
                                                more</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p style="text-align: center; color: #999; padding: 3rem;">No regional data available.</p>
                    @endif
                </div>

                <!-- Agenda 2063 Tab -->
                <div class="tab-content" id="agenda-tab">
                    <h2 style="color: var(--wine); margin-bottom: 1.5rem;">Agenda 2063 Alignment</h2>

                    @if (count($fundingByAspiration) > 0)
                        <div class="partner-grid">
                            @foreach ($fundingByAspiration as $aspiration)
                                <div class="aspiration-card">
                                    <div class="aspiration-number">{{ $aspiration['number'] }}</div>
                                    <h4 style="color: #fff; font-size: 1rem; margin-bottom: 0.5rem;">
                                        {{ $aspiration['title'] }}</h4>
                                    <div class="partner-stat" style="color: rgba(255,255,255,0.9);">
                                        <span>Total Funding:</span>
                                        <span style="color: var(--gold);">USD
                                            {{ number_format($aspiration['total_funding'], 0) }}</span>
                                    </div>
                                    <div class="partner-stat" style="color: rgba(255,255,255,0.9);">
                                        <span>Programs:</span>
                                        <span>{{ $aspiration['program_count'] }}</span>
                                    </div>
                                    <div class="partner-stat" style="color: rgba(255,255,255,0.9);">
                                        <span>Goals Addressed:</span>
                                        <span>{{ $aspiration['goal_count'] }}</span>
                                    </div>
                                    <div style="margin-top: 1rem;">
                                        @foreach ($aspiration['goals'] as $goal)
                                            <span class="goal-pill">Goal {{ $goal }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if (count($fundingByGoal) > 0)
                            <h3 style="color: var(--wine); margin: 2rem 0 1rem;">Goals Breakdown</h3>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Goal</th>
                                        <th>Aspiration</th>
                                        <th>Title</th>
                                        <th>Funding</th>
                                        <th>Programs</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach (array_slice($fundingByGoal, 0, 15) as $goal)
                                        <tr>
                                            <td><span class="goal-pill">Goal {{ $goal['number'] }}</span></td>
                                            <td>Asp. {{ $goal['aspiration_number'] }}</td>
                                            <td>{{ Str::limit($goal['title'], 50) }}</td>
                                            <td class="amount">USD {{ number_format($goal['total_funding'], 0) }}</td>
                                            <td>{{ $goal['program_count'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    @else
                        <p style="text-align: center; color: #999; padding: 3rem;">No Agenda 2063 alignment data
                            available.</p>
                    @endif
                </div>

                <!-- Trends Tab -->
                <div class="tab-content" id="trends-tab">
                    <h2 style="color: var(--wine); margin-bottom: 1.5rem;">Funding Trends & Analytics</h2>

                    <div class="charts-grid">
                        <!-- Funding by Type -->
                        <div class="chart-card">
                            <h4>Funding by Type</h4>
                            <div id="funding-type-chart"></div>
                        </div>

                        <!-- Year-over-Year Trend -->
                        <div class="chart-card">
                            <h4>Year-over-Year Trend</h4>
                            <div id="trend-chart"></div>
                        </div>

                        <!-- Partner Distribution -->
                        <div class="chart-card">
                            <h4>Partner Distribution</h4>
                            <div id="partner-chart"></div>
                        </div>

                        <!-- Regional Distribution -->
                        <div class="chart-card">
                            <h4>Regional Distribution</h4>
                            <div id="region-chart"></div>
                        </div>
                    </div>

                    <!-- Program Type Stats -->
                    <div
                        style="margin-top: 2rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <div class="summary-card">
                            <div class="value">{{ $summary['continental_programs'] }}</div>
                            <div class="label">Continental Initiatives</div>
                        </div>
                        <div class="summary-card">
                            <div class="value">{{ $summary['targeted_programs'] }}</div>
                            <div class="label">Targeted Programs</div>
                        </div>
                        <div class="summary-card">
                            <div class="value">USD {{ number_format($summary['average_funding'] / 1000000, 2) }}M
                            </div>
                            <div class="label">Avg. Funding per Program</div>
                        </div>
                    </div>
                </div>

                <!-- Request Information Tab -->
                <div class="tab-content" id="request-tab">
                    <div class="request-form-section">
                        <h2 style="color: var(--wine); margin-bottom: 1.5rem;">Request for Information</h2>
                        <p style="margin-bottom: 2rem; color: #666;">
                            Whether you're a researcher, academic, citizen, or organization, we're here to provide you
                            with the information you need about ATTP's impact across Africa.
                        </p>

                        <form id="request-form" onsubmit="submitRequest(event)">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="requester_type">I am a *</label>
                                    <select id="requester_type" name="requester_type" required>
                                        <option value="">Select your role</option>
                                        <option value="researcher">Researcher</option>
                                        <option value="academic">Academic</option>
                                        <option value="citizen">Citizen of Member State</option>
                                        <option value="organization">Organization</option>
                                        <option value="government">Government Official</option>
                                        <option value="media">Media/Press</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="full_name">Full Name *</label>
                                    <input type="text" id="full_name" name="full_name" required
                                        placeholder="Enter your full name">
                                </div>

                                <div class="form-group">
                                    <label for="email">Email Address *</label>
                                    <input type="email" id="email" name="email" required
                                        placeholder="your.email@example.com">
                                </div>

                                <div class="form-group">
                                    <label for="country">Country *</label>
                                    <input type="text" id="country" name="country" required
                                        placeholder="Your country">
                                </div>

                                <div class="form-group full-width">
                                    <label for="organization">Organization/Institution (Optional)</label>
                                    <input type="text" id="organization" name="organization"
                                        placeholder="Enter organization name">
                                </div>

                                <div class="form-group full-width">
                                    <label for="request_type">Type of Information Requested *</label>
                                    <select id="request_type" name="request_type" required>
                                        <option value="">Select information type</option>
                                        <option value="funding_data">Funding Data & Statistics</option>
                                        <option value="project_details">Project Details</option>
                                        <option value="regional_impact">Regional Impact Reports</option>
                                        <option value="partnership">Partnership Opportunities</option>
                                        <option value="research_collaboration">Research Collaboration</option>
                                        <option value="general_inquiry">General Inquiry</option>
                                    </select>
                                </div>

                                <div class="form-group full-width">
                                    <label for="message">Detailed Request/Message *</label>
                                    <textarea id="message" name="message" rows="5" required
                                        placeholder="Please provide details about your information request..."></textarea>
                                </div>

                                <div class="form-group full-width" style="text-align: center;">
                                    <button type="submit" class="submit-btn">Submit Request</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal-overlay" id="success-modal">
        <div class="success-modal">
            <button class="modal-close" onclick="closeModal()">&times;</button>
            <div class="modal-icon">✅</div>
            <h2 class="modal-title">Request Submitted!</h2>
            <p class="modal-message">
                Thank you for reaching out. We've received your request and will respond within 2-3 business days.
            </p>
            <button class="modal-button" onclick="closeModal()">Got it, Thanks!</button>
        </div>
    </div>

    <!-- Footer -->
    <footer id="contact" class="footer" style="margin-top: 4rem;">
        <div class="footer-content">
            <div class="footer-logo">
                <h3>ATTP<span> Administration</span></h3>
                <p>{{ __('landing.footer_description') }}</p>
            </div>

            <div class="footer-links">
                <h4>{{ __('landing.footer_links_title') }}</h4>
                <a href="{{ route('landing.index') }}">{{ __('landing.footer_link_home') }}</a>
                <a href="{{ route('impact.map') }}">{{ __('navigation.impact_map') }}</a>
                <a href="{{ route('careers.index') }}">{{ __('navigation.careers') }}</a>
                <a href="#contact">{{ __('navigation.contact') }}</a>
            </div>

            <div class="footer-contact">
                <h4>{{ __('landing.footer_contact_title') }}</h4>
                <p>{{ __('landing.footer_email') }}</p>
                <p>{{ __('landing.footer_copyright', ['year' => date('Y')]) }}</p>
            </div>
        </div>
    </footer>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- jQuery & DataTables JS -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

    <script>
        // Data from backend
        const countryGeoData = @json($countryGeoData);
        const fundingByPartner = @json($fundingByPartner);
        const fundingByRegion = @json($fundingByRegion);
        const trendData = @json($trendData);
        const summary = @json($summary);

        // Tab switching
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', function() {
                const tab = this.dataset.tab;

                document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

                this.classList.add('active');
                document.getElementById(tab + '-tab').classList.add('active');

                // Initialize charts when trends tab is shown
                if (tab === 'trends') {
                    initializeCharts();
                }
            });
        });

        // Initialize the map
        const map = L.map('africa-map', {
            center: [0, 20],
            zoom: 3,
            minZoom: 3,
            maxZoom: 8,
            scrollWheelZoom: true,
            zoomControl: true,
            attributionControl: false
        });

        let geojsonLayer;

        // Fetch and display Africa map
        fetch('https://raw.githubusercontent.com/johan/world.geo.json/master/countries.geo.json')
            .then(response => response.json())
            .then(data => {
                const africanCountries = [
                    'Algeria', 'Angola', 'Benin', 'Botswana', 'Burkina Faso', 'Burundi',
                    'Cameroon', 'Cape Verde', 'Cabo Verde', 'Central African Republic', 'Chad', 'Comoros',
                    'Democratic Republic of the Congo', 'Republic of the Congo', 'Djibouti',
                    'Egypt', 'Equatorial Guinea', 'Eritrea', 'Ethiopia', 'Gabon', 'Gambia',
                    'Ghana', 'Guinea', 'Guinea-Bissau', 'Ivory Coast', "Côte d'Ivoire", 'Kenya', 'Lesotho',
                    'Liberia', 'Libya', 'Madagascar', 'Malawi', 'Mali', 'Mauritania',
                    'Mauritius', 'Morocco', 'Mozambique', 'Namibia', 'Niger', 'Nigeria',
                    'Rwanda', 'Sao Tome and Principe', 'Senegal', 'Seychelles', 'Sierra Leone',
                    'Somalia', 'South Africa', 'South Sudan', 'Sudan', 'Eswatini', 'Tanzania',
                    'Togo', 'Tunisia', 'Uganda', 'Zambia', 'Zimbabwe'
                ];

                const africaGeoJSON = {
                    type: "FeatureCollection",
                    features: data.features.filter(feature => {
                        const countryName = feature.properties.name || feature.properties.NAME;
                        return africanCountries.some(african =>
                            countryName && countryName.toLowerCase().includes(african.toLowerCase())
                        );
                    })
                };

                addGeoJSONToMap(africaGeoJSON);
            });

        function addGeoJSONToMap(africaGeoJSON) {
            // Country code mapping for matching
            const codeMapping = {
                'Algeria': 'DZ',
                'Angola': 'AO',
                'Benin': 'BJ',
                'Botswana': 'BW',
                'Burkina Faso': 'BF',
                'Burundi': 'BI',
                'Cameroon': 'CM',
                'Cape Verde': 'CV',
                'Cabo Verde': 'CV',
                'Central African Republic': 'CF',
                'Chad': 'TD',
                'Comoros': 'KM',
                'Democratic Republic of the Congo': 'CD',
                'Republic of the Congo': 'CG',
                'Djibouti': 'DJ',
                'Egypt': 'EG',
                'Equatorial Guinea': 'GQ',
                'Eritrea': 'ER',
                'Ethiopia': 'ET',
                'Gabon': 'GA',
                'Gambia': 'GM',
                'Ghana': 'GH',
                'Guinea': 'GN',
                'Guinea-Bissau': 'GW',
                'Ivory Coast': 'CI',
                "Côte d'Ivoire": 'CI',
                'Kenya': 'KE',
                'Lesotho': 'LS',
                'Liberia': 'LR',
                'Libya': 'LY',
                'Madagascar': 'MG',
                'Malawi': 'MW',
                'Mali': 'ML',
                'Mauritania': 'MR',
                'Mauritius': 'MU',
                'Morocco': 'MA',
                'Mozambique': 'MZ',
                'Namibia': 'NA',
                'Niger': 'NE',
                'Nigeria': 'NG',
                'Rwanda': 'RW',
                'Sao Tome and Principe': 'ST',
                'Senegal': 'SN',
                'Seychelles': 'SC',
                'Sierra Leone': 'SL',
                'Somalia': 'SO',
                'South Africa': 'ZA',
                'South Sudan': 'SS',
                'Sudan': 'SD',
                'Eswatini': 'SZ',
                'Tanzania': 'TZ',
                'Togo': 'TG',
                'Tunisia': 'TN',
                'Uganda': 'UG',
                'Zambia': 'ZM',
                'Zimbabwe': 'ZW'
            };

            function getCountryData(countryName) {
                const code = codeMapping[countryName];
                if (code && countryGeoData[code]) {
                    return countryGeoData[code];
                }
                return null;
            }

            function style(feature) {
                const countryName = feature.properties.name || feature.properties.NAME;
                const data = getCountryData(countryName);
                const hasFunding = data && data.total_funding > 0;

                return {
                    fillColor: hasFunding ? '#a70d53' : '#d1d5db',
                    weight: 2,
                    opacity: 1,
                    color: '#ffffff',
                    fillOpacity: hasFunding ? 0.85 : 0.5
                };
            }

            function highlightFeature(e) {
                const layer = e.target;
                const countryName = layer.feature.properties.name || layer.feature.properties.NAME;
                const data = getCountryData(countryName);

                layer.setStyle({
                    weight: 3,
                    color: '#fbbc05',
                    fillColor: '#fbbc05',
                    fillOpacity: 0.9
                });

                layer.bringToFront();

                if (data && data.total_funding > 0) {
                    const popupContent = `
                        <div class="popup-content">
                            <div class="popup-country">${countryName}</div>
                            <div class="popup-stat">
                                <span>Direct Funding:</span>
                                <span style="color: #10b981; font-weight: 600;">$${(data.direct_funding / 1000000).toFixed(2)}M</span>
                            </div>
                            <div class="popup-stat">
                                <span>Continental Share:</span>
                                <span>$${(data.continental_funding / 1000000).toFixed(2)}M</span>
                            </div>
                            <div class="popup-stat">
                                <span>Total Programs:</span>
                                <span>${data.total_programs}</span>
                            </div>
                            <div class="popup-stat">
                                <span>Partners:</span>
                                <span>${data.partners.length}</span>
                            </div>
                        </div>
                    `;
                    layer.bindPopup(popupContent).openPopup();
                } else {
                    layer.bindPopup(`
                        <div class="popup-content">
                            <div class="popup-country">${countryName}</div>
                            <p style="color: #999;">No direct program funding yet.</p>
                        </div>
                    `).openPopup();
                }
            }

            function resetHighlight(e) {
                geojsonLayer.resetStyle(e.target);
                e.target.closePopup();
            }

            geojsonLayer = L.geoJSON(africaGeoJSON, {
                style: style,
                onEachFeature: (feature, layer) => {
                    layer.on({
                        mouseover: highlightFeature,
                        mouseout: resetHighlight
                    });
                }
            }).addTo(map);

            map.fitBounds(geojsonLayer.getBounds(), {
                padding: [30, 30],
                maxZoom: 4
            });
        }

        // Filter functions
        function selectAll(type) {
            document.querySelectorAll(`.filter-${type}`).forEach(cb => cb.checked = true);
            applyFilters();
        }

        function deselectAll(type) {
            document.querySelectorAll(`.filter-${type}`).forEach(cb => cb.checked = false);
            applyFilters();
        }

        function resetFilters() {
            document.querySelectorAll('.filter-funder, .filter-region, .filter-aspiration, .filter-scope').forEach(cb => cb
                .checked = true);
            applyFilters();
        }

        function applyFilters() {
            const funders = Array.from(document.querySelectorAll('.filter-funder:checked')).map(cb => cb.value);
            const regions = Array.from(document.querySelectorAll('.filter-region:checked')).map(cb => cb.value);
            const aspirations = Array.from(document.querySelectorAll('.filter-aspiration:checked')).map(cb => cb.value);
            const scopes = Array.from(document.querySelectorAll('.filter-scope:checked')).map(cb => cb.value);

            // Update filter count badge
            const totalFilters = funders.length + regions.length + aspirations.length + scopes.length;
            const totalAvailable = document.querySelectorAll(
                '.filter-funder, .filter-region, .filter-aspiration, .filter-scope').length;
            document.getElementById('active-filters').textContent = totalFilters === totalAvailable ? 'All' :
                `${totalFilters}`;

            // Update download links with filters
            updateDownloadLinks(funders, regions, aspirations, scopes);
        }

        function updateDownloadLinks(funders, regions, aspirations, scopes) {
            const baseUrl = '{{ url('/impact-map/download') }}';
            const params = new URLSearchParams();

            if (funders.length > 0) params.append('funders', funders.join(','));
            if (regions.length > 0) params.append('regions', regions.join(','));
            if (scopes.length > 0 && scopes.length < 2) {
                // Only add scope filter if not both are selected
                params.append('scope', scopes.join(','));
            }

            const queryString = params.toString();

            document.getElementById('download-pdf').href = baseUrl + '/pdf' + (queryString ? '?' + queryString : '');
            document.getElementById('download-excel').href = baseUrl + '/excel' + (queryString ? '?' + queryString : '');
        }

        // Attach filter listeners
        document.querySelectorAll('.filter-funder, .filter-region, .filter-aspiration, .filter-scope').forEach(cb => {
            cb.addEventListener('change', applyFilters);
        });

        // Initialize Charts
        function initializeCharts() {
            // Funding by Type Chart
            if (summary.by_funding_type && Object.keys(summary.by_funding_type).length > 0) {
                const typeData = Object.entries(summary.by_funding_type).map(([type, amount]) => ({
                    x: type.charAt(0).toUpperCase() + type.slice(1),
                    y: amount
                }));

                new ApexCharts(document.getElementById('funding-type-chart'), {
                    chart: {
                        type: 'donut',
                        height: 300
                    },
                    series: typeData.map(d => d.y),
                    labels: typeData.map(d => d.x),
                    colors: ['#522b39', '#a70d53', '#fbbc05', '#10b981'],
                    legend: {
                        position: 'bottom'
                    }
                }).render();
            }

            // Year-over-Year Trend
            if (trendData.length > 0) {
                new ApexCharts(document.getElementById('trend-chart'), {
                    chart: {
                        type: 'area',
                        height: 300
                    },
                    series: [{
                        name: 'Funding (USD)',
                        data: trendData.map(t => ({
                            x: t.year.toString(),
                            y: t.funding
                        }))
                    }],
                    colors: ['#a70d53'],
                    xaxis: {
                        type: 'category'
                    },
                    yaxis: {
                        labels: {
                            formatter: val => '$' + (val / 1000000).toFixed(1) + 'M'
                        }
                    }
                }).render();
            }

            // Partner Distribution
            if (fundingByPartner.length > 0) {
                const partnerData = fundingByPartner.slice(0, 8);
                new ApexCharts(document.getElementById('partner-chart'), {
                    chart: {
                        type: 'bar',
                        height: 300
                    },
                    series: [{
                        name: 'Funding',
                        data: partnerData.map(p => p.total_funding)
                    }],
                    xaxis: {
                        categories: partnerData.map(p => p.name.length > 15 ? p.name.substring(0, 15) + '...' : p
                            .name)
                    },
                    colors: ['#522b39'],
                    yaxis: {
                        labels: {
                            formatter: val => '$' + (val / 1000000).toFixed(1) + 'M'
                        }
                    }
                }).render();
            }

            // Regional Distribution
            if (fundingByRegion.length > 0) {
                new ApexCharts(document.getElementById('region-chart'), {
                    chart: {
                        type: 'pie',
                        height: 300
                    },
                    series: fundingByRegion.map(r => r.total_funding),
                    labels: fundingByRegion.map(r => r.abbreviation),
                    colors: ['#522b39', '#a70d53', '#e16435', '#fbbc05', '#10b981', '#3b82f6', '#8b5cf6',
                        '#f97316'
                    ],
                    legend: {
                        position: 'bottom'
                    }
                }).render();
            }
        }

        // Request form submission
        function submitRequest(e) {
            e.preventDefault();

            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);

            fetch('{{ route('impact.request') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        e.target.reset();
                        document.getElementById('success-modal').classList.add('show');
                    } else {
                        alert('Error submitting request. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error submitting request. Please try again.');
                });
        }

        function closeModal() {
            document.getElementById('success-modal').classList.remove('show');
        }

        // Close modal with ESC key
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeModal();
        });

        // Initialize on load
        document.addEventListener('DOMContentLoaded', () => {
            applyFilters();

            // Initialize Countries DataTable
            if ($('#countriesTable').length) {
                $('#countriesTable').DataTable({
                    responsive: true,
                    pageLength: 10,
                    lengthMenu: [
                        [10, 25, 50, -1],
                        [10, 25, 50, "All"]
                    ],
                    order: [
                        [1, 'desc']
                    ], // Sort by Direct Funding descending
                    language: {
                        search: "Search Countries:",
                        lengthMenu: "Show _MENU_ countries",
                        info: "Showing _START_ to _END_ of _TOTAL_ countries",
                        paginate: {
                            first: "First",
                            last: "Last",
                            next: "Next",
                            previous: "Previous"
                        }
                    },
                    columnDefs: [{
                            targets: [1, 2],
                            className: 'text-end'
                        },
                        {
                            targets: [3],
                            className: 'text-center'
                        }
                    ],
                    dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
                });
            }

            console.log('Impact Map loaded with real data from Program Funding');
        });
    </script>

</body>

</html>
