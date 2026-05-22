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
            --au-green:      #006B3F;
            --au-green-dark: #004d2e;
            --au-green-light:#009A44;
            --gold:          #fbbc05;
            --orange:        #e16435;
            /* mapped to AU green so all existing var() refs update automatically */
            --magenta:       #006B3F;
            --wine:          #004d2e;
            --light:         #f0f4f0;
            --dark:          #1a1a1a;
            --success:       #10b981;
            --info:          #3b82f6;
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
            background: linear-gradient(135deg, var(--au-green-dark) 0%, var(--au-green) 60%, var(--au-green-light) 100%);
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
        .africa-map-canvas {
            width: 100%;
            height: clamp(360px, 62vh, 620px);
            min-height: 360px;
            border-radius: 12px;
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
        }

        .treaty-controls {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 0.75rem;
            align-items: end;
            margin-bottom: 1rem;
        }

        .treaty-controls .meta {
            color: #6b7280;
            font-size: 0.88rem;
        }

        .treaty-visual-tools {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem 0.75rem;
            align-items: center;
            margin-top: 0.55rem;
        }

        .treaty-visual-tools .hint {
            font-size: 0.78rem;
            color: #4b5563;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 999px;
            padding: 0.3rem 0.62rem;
        }

        .treaty-visual-tools .focus-btn {
            border-color: #0f766e;
            color: #0f766e;
            font-weight: 600;
        }

        .treaty-visual-tools .focus-btn:hover {
            background: #0f766e;
            color: #fff;
        }

        .map-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem 1rem;
            margin-top: 0.75rem;
            font-size: 0.82rem;
            color: #374151;
        }

        .map-legend-item {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .map-legend-swatch {
            width: 12px;
            height: 12px;
            border-radius: 3px;
            border: 1px solid rgba(0, 0, 0, 0.15);
            flex-shrink: 0;
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

        .popup-actions {
            margin-top: 0.9rem;
            display: flex;
            justify-content: flex-end;
        }

        .popup-learn-btn {
            border: 1px solid #34d399;
            background: rgba(16, 185, 129, 0.2);
            color: #d1fae5;
            border-radius: 999px;
            padding: 0.35rem 0.8rem;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .popup-learn-btn:hover {
            background: #10b981;
            border-color: #10b981;
            color: #042f2e;
        }

        /* Treaty Details Modal */
        .treaty-detail-overlay {
            padding: 1.25rem;
            z-index: 10010;
        }

        .treaty-detail-modal {
            width: min(980px, 96vw);
            max-height: min(90vh, 920px);
            background: #ffffff;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 26px 75px rgba(0, 0, 0, 0.35);
            display: flex;
            flex-direction: column;
        }

        .treaty-detail-header {
            background: linear-gradient(135deg, #064e3b 0%, #0f766e 70%, #34d399 100%);
            padding: 1.05rem 1.45rem 0.95rem;
            color: #ecfdf5;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }

        .treaty-detail-title {
            margin: 0;
            font-size: 1.35rem;
            line-height: 1.35;
            font-weight: 700;
            color: #ffffff;
            padding-right: 2rem;
        }

        .treaty-detail-subtitle {
            margin: 0.25rem 0 0;
            font-size: 0.9rem;
            color: rgba(236, 253, 245, 0.95);
        }

        .treaty-detail-close {
            color: #e5e7eb;
            right: 1.1rem;
            top: 0.75rem;
            z-index: 2;
        }

        .treaty-detail-close:hover {
            color: #ffffff;
        }

        .treaty-detail-body {
            padding: 1.2rem 1.45rem 1.35rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 0.95rem;
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        }

        .treaty-detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 0.75rem;
        }

        .treaty-detail-card {
            border: 1px solid #d1fae5;
            border-radius: 12px;
            padding: 0.75rem 0.8rem;
            background: #ffffff;
        }

        .treaty-detail-card .label {
            color: #065f46;
            text-transform: uppercase;
            font-weight: 700;
            font-size: 0.72rem;
            letter-spacing: 0.04em;
        }

        .treaty-detail-card .value {
            margin-top: 0.25rem;
            color: #111827;
            font-size: 0.94rem;
            font-weight: 600;
            word-break: break-word;
        }

        .treaty-detail-section {
            border: 1px solid #d1d5db;
            border-radius: 12px;
            background: #ffffff;
            padding: 0.75rem 0.9rem;
        }

        .treaty-detail-section h4 {
            margin: 0 0 0.4rem;
            font-size: 0.88rem;
            color: #111827;
        }

        .treaty-detail-section p {
            margin: 0;
            color: #374151;
            font-size: 0.9rem;
            line-height: 1.5;
            white-space: pre-wrap;
        }

        .treaty-status-pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.25rem 0.6rem;
            font-size: 0.75rem;
            font-weight: 700;
            border: 1px solid transparent;
        }

        .treaty-status-pill.signed {
            background: #fef9c3;
            border-color: #facc15;
            color: #92400e;
        }

        .treaty-status-pill.ratified {
            background: #dcfce7;
            border-color: #16a34a;
            color: #166534;
        }

        .treaty-status-pill.original {
            background: #ccfbf1;
            border-color: #0f766e;
            color: #134e4a;
        }

        .treaty-status-pill.none {
            background: #f3f4f6;
            border-color: #d1d5db;
            color: #4b5563;
        }

        .treaty-detail-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            overflow: hidden;
            background: #ffffff;
        }

        .treaty-detail-table th,
        .treaty-detail-table td {
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            padding: 0.58rem 0.65rem;
            font-size: 0.86rem;
            vertical-align: top;
        }

        .treaty-detail-table th {
            color: #065f46;
            background: #ecfdf5;
            font-weight: 700;
        }

        .treaty-detail-link {
            color: #0f766e;
            font-weight: 700;
            text-decoration: none;
        }

        .treaty-detail-link:hover {
            text-decoration: underline;
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

            .treaty-controls {
                grid-template-columns: 1fr;
            }

            .africa-map-canvas {
                height: 430px;
                min-height: 430px;
            }

            .treaty-detail-title {
                font-size: 1.1rem;
            }

            .treaty-detail-body {
                padding: 0.95rem;
            }

            .treaty-detail-table th,
            .treaty-detail-table td {
                font-size: 0.8rem;
                padding: 0.45rem 0.5rem;
            }
        }

        @media (max-width: 480px) {
            .africa-map-canvas {
                height: 360px;
                min-height: 360px;
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

        <button class="mobile-dropdown-toggle" onclick="toggleMobileDropdown(this)">
            Programs <span class="mobile-dropdown-arrow">▾</span>
        </button>
        <div class="mobile-dropdown-items">
            <a href="{{ route('events') }}" onclick="closeMobileNav()">{{ __('landing.events_webinars') }}</a>
            <a href="{{ route('careers.index') }}" onclick="closeMobileNav()">{{ __('navigation.careers') }}</a>
        </div>

        <button class="mobile-dropdown-toggle" onclick="toggleMobileDropdown(this)">
            Analytics <span class="mobile-dropdown-arrow">▾</span>
        </button>
        <div class="mobile-dropdown-items">
            <a href="{{ route('impact.map') }}" class="active" onclick="closeMobileNav()">{{ __('navigation.impact_map') }}</a>
            <a href="{{ route('world.indicators.performance') }}" onclick="closeMobileNav()">{{ __('navigation.world_indicators_performance') }}</a>
        </div>

        <a href="{{ route('news.index') }}" onclick="closeMobileNav()">News &amp; Updates</a>
        <a href="#contact" onclick="closeMobileNav()">{{ __('navigation.contact') }}</a>
        <div class="mobile-nav-actions">
            <a href="{{ route('public.procurement.index') }}" class="btn btn-primary">{{ __('landing.policy_programs') }}</a>
            <a href="{{ route('login') }}" class="btn btn-login">{{ __('navigation.login') }}</a>
            <x-language-selector style="impact" />
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
                <a href="#">Programs</a>
                <ul class="nav-dropdown">
                    <li><a href="{{ route('events') }}">{{ __('landing.events_webinars') }}</a></li>
                    <li><a href="{{ route('careers.index') }}">{{ __('navigation.careers') }}</a></li>
                </ul>
            </div>

            <div class="has-dropdown">
                <a href="#" class="active">Analytics</a>
                <ul class="nav-dropdown">
                    <li><a href="{{ route('impact.map') }}" class="active">{{ __('navigation.impact_map') }}</a></li>
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
            <div class="icon">&#x1F4B0;</div>
            <div class="value">USD {{ number_format($summary['total_funding'] / 1000000, 1) }}M</div>
            <div class="label">Total Funding</div>
        </div>
        <div class="summary-card">
            <div class="icon">&#x1F4CA;</div>
            <div class="value">{{ $summary['total_programs'] }}</div>
            <div class="label">Active Programs</div>
        </div>
        <div class="summary-card">
            <div class="icon">&#x1F91D;</div>
            <div class="value">{{ $summary['total_partners'] }}</div>
            <div class="label">Funding Partners</div>
        </div>
        <div class="summary-card">
            <div class="icon">&#x1F30D;</div>
            <div class="value">{{ $summary['total_countries'] }}</div>
            <div class="label">Countries Reached</div>
        </div>
        <div class="summary-card">
            <div class="icon">&#x1F3DB;</div>
            <div class="value">{{ $summary['total_regions'] }}</div>
            <div class="label">Regional Blocks</div>
        </div>
        <div class="summary-card">
            <div class="icon">&#x1F3AF;</div>
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
                            <span class="scope-badge continental">&#x1F30D;</span>
                            Continental Initiatives (All 55 States)
                        </label>
                    </div>
                    <div class="filter-checkbox targeted-filter">
                        <input type="checkbox" id="filter-targeted" value="targeted" class="filter-scope" checked>
                        <label for="filter-targeted">
                            <span class="scope-badge targeted">&#x1F3AF;</span>
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
                    <span>&#x1F4C4;</span> Download PDF Report
                </a>
                <a href="{{ route('impact.download.excel') }}" class="download-btn excel" id="download-excel">
                    <span>&#x1F4CA;</span> Download Excel/CSV
                </a>
            </div>
        </aside>

        <!-- Content Area -->
        <div class="content-area">
            <div class="tabs">
                <div class="tab-buttons">
                    <button class="tab-button active" data-tab="map">Interactive Map</button>
                    <button class="tab-button" data-tab="treaties">Treaties</button>
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
                        Learn more about how the ATTP - Africa Think Tank Platform is transforming the African
                        continent.
                        @if ($summary['continental_programs'] > 0)
                            <span class="continental-badge">{{ $summary['continental_programs'] }} Continental
                                Initiative(s)</span>
                        @endif
                    </p>
                    <div id="africa-map" class="africa-map-canvas"></div>
                    <p id="africa-map-status" style="margin: 0.75rem 0 0; color: #666; font-size: 0.9rem;">
                        Loading Africa shapefiles...
                    </p>
                    <div class="map-legend">
                        <span class="map-legend-item"><span class="map-legend-swatch"
                                style="background:#2e7d32;"></span>West Africa</span>
                        <span class="map-legend-item"><span class="map-legend-swatch"
                                style="background:#1565c0;"></span>East Africa</span>
                        <span class="map-legend-item"><span class="map-legend-swatch"
                                style="background:#ef6c00;"></span>Central Africa</span>
                        <span class="map-legend-item"><span class="map-legend-swatch"
                                style="background:#b7791f;"></span>North Africa</span>
                        <span class="map-legend-item"><span class="map-legend-swatch"
                                style="background:#0f766e;"></span>Southern Africa</span>
                        <span class="map-legend-item"><span class="map-legend-swatch"
                                style="background:#7d2d3d;"></span>Islands</span>
                    </div>

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

                <!-- Treaties Tab -->
                <div class="tab-content" id="treaties-tab">
                    <h2 style="color: var(--wine); margin-bottom: 0.75rem;">African Union Treaties</h2>
                    <p style="margin-bottom: 0.9rem; color: #4b5563; line-height: 1.7;">
                        The African Union treaty framework guides continental cooperation, legal harmonization,
                        ratification milestones, and formal submission of treaty instruments by member states.
                    </p>
                    <p style="margin-bottom: 1.4rem; color: #6b7280; line-height: 1.7;">
                        Open the dedicated treaties information page to explore treaty-by-treaty status filters,
                        member-state status indicators on the Africa map, and the full status dataset in a searchable table.
                    </p>
                    <a href="{{ route('impact.treaties.information') }}" class="filter-reset"
                        style="display:inline-flex; width:auto; padding:0.72rem 1.25rem; text-decoration:none;">
                        View Treaties Information
                    </a>
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

    <!-- Treaty Details Modal -->
    <div class="modal-overlay treaty-detail-overlay" id="treaty-detail-modal" aria-hidden="true">
        <div class="treaty-detail-modal" role="dialog" aria-modal="true" aria-labelledby="treaty-detail-title">
            <button type="button" class="modal-close treaty-detail-close" id="treaty-detail-close-btn"
                aria-label="Close treaty details">&times;</button>
            <div class="treaty-detail-header">
                <h2 class="treaty-detail-title" id="treaty-detail-title">Treaty Details</h2>
                <p class="treaty-detail-subtitle" id="treaty-detail-subtitle">
                    Detailed treaty information for this member state.
                </p>
            </div>
            <div class="treaty-detail-body" id="treaty-detail-body"></div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal-overlay" id="success-modal">
        <div class="success-modal">
            <button class="modal-close" onclick="closeModal()">&times;</button>
            <div class="modal-icon">&#x2705;</div>
            <h2 class="modal-title">Request Submitted!</h2>
            <p class="modal-message">
                Thank you for reaching out. We've received your request and will respond within 2-3 business days.
            </p>
            <button class="modal-button" onclick="closeModal()">Got it, Thanks!</button>
        </div>
    </div>

    <!-- Footer -->
    <footer id="contact" class="footer" role="contentinfo">
        <div class="footer-content">
            <div class="footer-logo">
                <h3>ATTP<span> Administration</span></h3>
                <p>{{ __('landing.footer_description') }}</p>
            </div>

            <div class="footer-links">
                <h4>{{ __('landing.footer_links_title') }}</h4>
                <a href="{{ route('landing.index') }}">{{ __('landing.footer_link_home') }}</a>
                <a href="{{ route('impact.map') }}">{{ __('navigation.impact_map') }}</a>
                <a href="{{ route('world.indicators.performance') }}">{{ __('navigation.world_indicators_performance') }}</a>
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

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/shpjs@6.2.0/dist/shp.min.js"></script>
    <script src="https://unpkg.com/topojson-client@3"></script>

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
        const shapeFiles = @json($shapeFiles);
        const treatiesData = @json($treatiesData ?? []);
        const fallbackAfricaMapUrl = 'https://cdn.jsdelivr.net/npm/world-atlas@2/countries-110m.json';

        // Initialize maps (using local Africa shapefiles)
        const map = L.map('africa-map', {
            center: [0, 20],
            zoom: 3,
            minZoom: 3,
            maxZoom: 8,
            scrollWheelZoom: true,
            zoomControl: true,
            attributionControl: false
        });
        const africaLayerGroup = L.featureGroup().addTo(map);
        const mapStatusEl = document.getElementById('africa-map-status');
        const treatiesMapEl = document.getElementById('treaties-map');
        const treatiesMap = treatiesMapEl ? L.map('treaties-map', {
            center: [0, 20],
            zoom: 3,
            minZoom: 3,
            maxZoom: 8,
            scrollWheelZoom: true,
            zoomControl: true,
            attributionControl: false
        }) : null;
        const treatiesLayerGroup = treatiesMap ? L.featureGroup().addTo(treatiesMap) : L.featureGroup();
        const treatiesMapStatusEl = document.getElementById('treaties-map-status');
        const treatySelectorEl = document.getElementById('treaty-selector');
        const treatySelectorMetaEl = document.getElementById('treaty-selector-meta');
        const treatyFocusBtn = document.getElementById('treaty-focus-btn');
        const treatyCountryLayersByCode = {};
        const treatyShapeCache = [];
        let treatiesLayersInitialized = false;
        const treatyDetailModalEl = document.getElementById('treaty-detail-modal');
        const treatyDetailCloseBtn = document.getElementById('treaty-detail-close-btn');
        const treatyDetailTitleEl = document.getElementById('treaty-detail-title');
        const treatyDetailSubtitleEl = document.getElementById('treaty-detail-subtitle');
        const treatyDetailBodyEl = document.getElementById('treaty-detail-body');

        function toTreatyId(value) {
            return value === null || value === undefined ? '' : String(value);
        }

        let activeTreatyId = toTreatyId(treatySelectorEl ? treatySelectorEl.value : '');

        function isTreatiesTabActive() {
            const treatiesTabEl = document.getElementById('treaties-tab');
            return !!(treatiesTabEl && treatiesTabEl.classList.contains('active'));
        }

        function setMapStatus(message) {
            if (mapStatusEl) {
                mapStatusEl.textContent = message;
            }
        }

        function setTreatiesMapStatus(message) {
            if (treatiesMapStatusEl) {
                treatiesMapStatusEl.textContent = message;
            }
        }

        // Tab switching
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', function() {
                const tab = this.dataset.tab;

                document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

                this.classList.add('active');
                document.getElementById(tab + '-tab').classList.add('active');

                if (tab === 'map') {
                    setTimeout(() => map.invalidateSize(), 120);
                }

                if (tab === 'treaties') {
                    if (treatiesMap) {
                        initializeTreatiesLayersIfNeeded();
                        setTimeout(() => {
                            treatiesMap.invalidateSize();
                            focusTreatyMapOnActiveCountries(false);
                        }, 120);
                    }
                }

                // Initialize charts when trends tab is shown
                if (tab === 'trends') {
                    initializeCharts();
                }
            });
        });

        function normalizeCountryName(name) {
            const input = (name || '').toString();
            const normalized = typeof input.normalize === 'function' ? input.normalize('NFD') : input;
            return normalized
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[\u2019']/g, '')
                .replace(/[^a-zA-Z0-9 ]/g, ' ')
                .replace(/\s+/g, ' ')
                .trim()
                .toLowerCase();
        }

        const aliasToCode = {
            'algeria': 'DZ',
            'angola': 'AO',
            'benin': 'BJ',
            'botswana': 'BW',
            'burkina faso': 'BF',
            'burundi': 'BI',
            'cabo verde': 'CV',
            'cape verde': 'CV',
            'cameroon': 'CM',
            'central african rep': 'CF',
            'central african republic': 'CF',
            'chad': 'TD',
            'comoros': 'KM',
            'congo': 'CG',
            'congo republic': 'CG',
            'congo brazzaville': 'CG',
            'c te divoire': 'CI',
            'ivory coast': 'CI',
            'cote divoire': 'CI',
            'cote d ivoire': 'CI',
            'cote dvoire': 'CI',
            'dem rep congo': 'CD',
            'democratic republic of congo': 'CD',
            'democratic republic of the congo': 'CD',
            'dr congo': 'CD',
            'drc': 'CD',
            'djibouti': 'DJ',
            'egypt': 'EG',
            'eq guinea': 'GQ',
            'equatorial guinea': 'GQ',
            'eritrea': 'ER',
            'swaziland': 'SZ',
            'eswatini': 'SZ',
            'ethiopia': 'ET',
            'gabon': 'GA',
            'gambia': 'GM',
            'the gambia': 'GM',
            'ghana': 'GH',
            'guinea': 'GN',
            'guinea bissau': 'GW',
            'kenya': 'KE',
            'lesotho': 'LS',
            'liberia': 'LR',
            'libya': 'LY',
            'madagascar': 'MG',
            'malawi': 'MW',
            'mali': 'ML',
            'mauritania': 'MR',
            'mauritius': 'MU',
            'morocco': 'MA',
            'mozambique': 'MZ',
            'namibia': 'NA',
            'niger': 'NE',
            'nigeria': 'NG',
            'rwanda': 'RW',
            'w sahara': 'EH',
            'western sahara': 'EH',
            'sahrawi arab democratic republic': 'EH',
            'sao tome and principe': 'ST',
            'sao tome principe': 'ST',
            'senegal': 'SN',
            'seychelles': 'SC',
            'sierra leone': 'SL',
            'somalia': 'SO',
            'south africa': 'ZA',
            's sudan': 'SS',
            'south sudan': 'SS',
            'sudan': 'SD',
            'tanzania': 'TZ',
            'united republic of tanzania': 'TZ',
            'togo': 'TG',
            'tunisia': 'TN',
            'uganda': 'UG',
            'zambia': 'ZM',
            'zimbabwe': 'ZW'
        };

        const auCountryNameSet = new Set(Object.keys(aliasToCode));

        const regionBaseColors = {
            west: '#2e7d32',
            east: '#1565c0',
            central: '#ef6c00',
            north: '#b7791f',
            south: '#0f766e',
            islands: '#7d2d3d',
            unknown: '#9ca3af'
        };

        const regionDisplayNames = {
            west: 'West Africa',
            east: 'East Africa',
            central: 'Central Africa',
            north: 'North Africa',
            south: 'Southern Africa',
            islands: 'Islands'
        };

        const regionalBlockToRegionKey = {
            ECOWAS: 'west',
            ECCAS: 'central',
            EAC: 'east',
            IGAD: 'east',
            SADC: 'south',
            UMA: 'north',
            COMESA: 'east',
            CENSAD: 'north'
        };

        const regionCountryGroups = {
            west: [
                'Benin', 'Burkina Faso', 'Cote d\'Ivoire', 'Ivory Coast', 'Gambia', 'Ghana', 'Guinea',
                'Guinea-Bissau', 'Liberia', 'Mali', 'Mauritania', 'Niger', 'Nigeria', 'Senegal',
                'Sierra Leone', 'Togo'
            ],
            east: [
                'Burundi', 'Djibouti', 'Eritrea', 'Ethiopia', 'Kenya', 'Rwanda', 'Somalia', 'South Sudan',
                'Sudan', 'Tanzania', 'Uganda'
            ],
            central: [
                'Cameroon', 'Central African Republic', 'Chad', 'Congo', 'Republic of Congo',
                'Democratic Republic of the Congo', 'Equatorial Guinea', 'Gabon'
            ],
            north: [
                'Algeria', 'Egypt', 'Libya', 'Morocco', 'Tunisia', 'Western Sahara',
                'Sahrawi Arab Democratic Republic'
            ],
            south: [
                'Angola', 'Botswana', 'Eswatini', 'Swaziland', 'Lesotho', 'Malawi', 'Mozambique', 'Namibia',
                'South Africa', 'Zambia', 'Zimbabwe'
            ]
        };

        const islandCountries = new Set([
            'cabo verde',
            'cape verde',
            'comoros',
            'madagascar',
            'mauritius',
            'seychelles',
            'sao tome and principe',
            'sao tome principe',
            'mayotte',
            'reunion',
            'french southern territories'
        ]);

        const countryRegionLookup = {};

        Object.entries(regionCountryGroups).forEach(([regionKey, countries]) => {
            countries.forEach((country) => {
                countryRegionLookup[normalizeCountryName(country)] = regionKey;
            });
        });

        islandCountries.forEach((country) => {
            countryRegionLookup[country] = 'islands';
        });

        const codeMappingByName = Object.entries(countryGeoData).reduce((lookup, entry) => {
            const code = entry[0];
            const payload = entry[1];
            if (payload && payload.name) {
                lookup[normalizeCountryName(payload.name)] = code;
            }
            return lookup;
        }, {});

        const treatyCodeMappingByName = (Array.isArray(treatiesData) ? treatiesData : []).reduce((lookup, treaty) => {
            (Array.isArray(treaty.statuses) ? treaty.statuses : []).forEach((entry) => {
                const normalizedName = normalizeCountryName(entry.country_name || '');
                const rawCode = (entry.country_code || '').toString().trim().toUpperCase();
                if (normalizedName && rawCode) {
                    lookup[normalizedName] = rawCode;
                }
            });
            return lookup;
        }, {});

        function resolveCountryCode(countryName) {
            const normalized = normalizeCountryName(countryName);
            if (!normalized) {
                return null;
            }
            if (aliasToCode[normalized]) {
                return aliasToCode[normalized];
            }
            if (codeMappingByName[normalized]) {
                return codeMappingByName[normalized];
            }
            return treatyCodeMappingByName[normalized] || null;
        }

        function resolveFundingCountryCode(countryName) {
            const normalized = normalizeCountryName(countryName);
            if (!normalized) {
                return null;
            }

            if (codeMappingByName[normalized]) {
                return codeMappingByName[normalized];
            }

            const alternateCode = treatyCodeMappingByName[normalized] || aliasToCode[normalized] || null;
            return alternateCode && countryGeoData[alternateCode] ? alternateCode : null;
        }

        function normalizeCountryCode(countryName) {
            const code = resolveCountryCode(countryName);
            if (!code) {
                return null;
            }

            const normalizedCode = String(code).trim().toUpperCase();
            return /^[A-Z]{2}$/.test(normalizedCode) ? normalizedCode : null;
        }

        const defaultTreatyStatus = {
            is_signed: false,
            is_ratified: false,
            is_original_submitted: false,
            signed_at: null,
            ratified_at: null,
            original_submitted_at: null
        };

        function buildTreatyStatusIndex(treatyRows) {
            const index = {};
            (Array.isArray(treatyRows) ? treatyRows : []).forEach((entry) => {
                const rawCode = (entry.country_code || '').toString().toUpperCase();
                let code = rawCode;
                if (!code || code.length !== 2) {
                    const resolvedByName = resolveCountryCode(entry.country_name || '');
                    code = resolvedByName ? resolvedByName.toUpperCase() : code;
                }
                if (!code || !/^[A-Z]{2}$/.test(code)) {
                    return;
                }
                index[code] = {
                    is_signed: !!entry.is_signed,
                    is_ratified: !!entry.is_ratified,
                    is_original_submitted: !!entry.is_original_submitted,
                    signed_at: entry.signed_at || null,
                    ratified_at: entry.ratified_at || null,
                    original_submitted_at: entry.original_submitted_at || null
                };
            });
            return index;
        }

        const combinedTreatyStatusIndex = (function() {
            const merged = {};
            (Array.isArray(treatiesData) ? treatiesData : []).forEach((treaty) => {
                const treatyIndex = buildTreatyStatusIndex(treaty.statuses || []);
                Object.keys(treatyIndex).forEach((countryCode) => {
                    const current = merged[countryCode] || {
                        is_signed: false,
                        is_ratified: false,
                        is_original_submitted: false,
                        signed_at: null,
                        ratified_at: null,
                        original_submitted_at: null
                    };
                    const incoming = treatyIndex[countryCode];
                    merged[countryCode] = {
                        is_signed: current.is_signed || incoming.is_signed,
                        is_ratified: current.is_ratified || incoming.is_ratified,
                        is_original_submitted: current.is_original_submitted || incoming
                            .is_original_submitted,
                        signed_at: current.signed_at || incoming.signed_at,
                        ratified_at: current.ratified_at || incoming.ratified_at,
                        original_submitted_at: current.original_submitted_at || incoming
                            .original_submitted_at
                    };
                });
            });
            return merged;
        })();

        const treatyStatusIndexesById = (Array.isArray(treatiesData) ? treatiesData : []).reduce((lookup, treaty) => {
            lookup[toTreatyId(treaty.id)] = buildTreatyStatusIndex(treaty.statuses || []);
            return lookup;
        }, {});

        function getSelectedTreaty() {
            if (!activeTreatyId) {
                return null;
            }
            return (Array.isArray(treatiesData) ? treatiesData : []).find((treaty) => toTreatyId(treaty.id) ===
                activeTreatyId) || null;
        }

        function getTreatyStatusForCountry(countryName) {
            const code = resolveCountryCode(countryName);
            if (!code) {
                return defaultTreatyStatus;
            }

            const normalizedCode = code.toUpperCase();
            if (activeTreatyId && treatyStatusIndexesById[activeTreatyId]) {
                return treatyStatusIndexesById[activeTreatyId][normalizedCode] || defaultTreatyStatus;
            }

            return combinedTreatyStatusIndex[normalizedCode] || defaultTreatyStatus;
        }

        function getTreatyFillColor(countryName) {
            const status = getTreatyStatusForCountry(countryName);
            if (status.is_original_submitted) {
                return '#0f766e';
            }
            if (status.is_ratified) {
                return '#2e7d32';
            }
            if (status.is_signed) {
                return '#facc15';
            }
            return '#d1d5db';
        }

        function refreshTreatySelectorMeta() {
            if (!treatySelectorMetaEl) {
                return;
            }

            const selectedTreaty = getSelectedTreaty();
            if (!selectedTreaty) {
                const signedCount = Object.values(combinedTreatyStatusIndex).filter((row) => row.is_signed).length;
                const ratifiedCount = Object.values(combinedTreatyStatusIndex).filter((row) => row.is_ratified).length;
                const originalSubmittedCount = Object.values(combinedTreatyStatusIndex).filter((row) => row
                    .is_original_submitted).length;
                treatySelectorMetaEl.textContent =
                    `Combined view: ${signedCount} signed, ${ratifiedCount} ratified, ${originalSubmittedCount} original submissions.`;
                return;
            }

            treatySelectorMetaEl.textContent =
                `${selectedTreaty.title}: ${selectedTreaty.signed_count} signed, ${selectedTreaty.ratified_count} ratified, ${selectedTreaty.original_submitted_count || 0} original submissions.`;
        }

        function normalizeRegionalAbbreviation(abbreviation) {
            return (abbreviation || '').toString().toUpperCase().replace(/[^A-Z]/g, '');
        }

        function resolveRegionKeyFromName(countryName) {
            const normalized = normalizeCountryName(countryName);
            if (!normalized) {
                return null;
            }

            return countryRegionLookup[normalized] || null;
        }

        function resolveRegionKeyFromData(countryData) {
            if (!countryData || !Array.isArray(countryData.regions)) {
                return null;
            }

            for (const abbreviation of countryData.regions) {
                const normalizedAbbreviation = normalizeRegionalAbbreviation(abbreviation);
                if (regionalBlockToRegionKey[normalizedAbbreviation]) {
                    return regionalBlockToRegionKey[normalizedAbbreviation];
                }
            }

            return null;
        }

        function getCountryData(countryName) {
            const code = resolveFundingCountryCode(countryName);
            if (code && countryGeoData[code]) {
                return countryGeoData[code];
            }
            return null;
        }

        function resolveRegionKey(countryName) {
            const fromName = resolveRegionKeyFromName(countryName);
            if (fromName) {
                return fromName;
            }

            const fromData = resolveRegionKeyFromData(getCountryData(countryName));
            if (fromData) {
                return fromData;
            }

            return 'unknown';
        }

        function getRegionDisplayName(countryName) {
            const regionKey = resolveRegionKey(countryName);
            return regionDisplayNames[regionKey] || 'Unclassified';
        }

        function getCountryShadeSeed(countryName) {
            const normalized = normalizeCountryName(countryName);
            let hash = 0;

            for (let i = 0; i < normalized.length; i += 1) {
                hash = (hash * 31 + normalized.charCodeAt(i)) % 2147483647;
            }

            return hash;
        }

        function hexToRgb(hexColor) {
            const cleanHex = (hexColor || '').replace('#', '');
            const hex = cleanHex.length === 3 ? cleanHex.split('').map((ch) => ch + ch).join('') : cleanHex;

            if (hex.length !== 6) {
                return {
                    r: 156,
                    g: 163,
                    b: 175
                };
            }

            return {
                r: parseInt(hex.substring(0, 2), 16),
                g: parseInt(hex.substring(2, 4), 16),
                b: parseInt(hex.substring(4, 6), 16)
            };
        }

        function rgbToHex(r, g, b) {
            const toHex = (value) => Math.max(0, Math.min(255, Math.round(value))).toString(16).padStart(2, '0');
            return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
        }

        function blendWithWhite(baseHex, ratioToWhite) {
            const ratio = Math.max(0, Math.min(1, ratioToWhite));
            const base = hexToRgb(baseHex);
            const mixed = {
                r: base.r + (255 - base.r) * ratio,
                g: base.g + (255 - base.g) * ratio,
                b: base.b + (255 - base.b) * ratio
            };
            return rgbToHex(mixed.r, mixed.g, mixed.b);
        }

        const regionDirectFundingMax = Object.values(countryGeoData).reduce((maxByRegion, payload) => {
            if (!payload || !payload.name) {
                return maxByRegion;
            }

            const regionKey = resolveRegionKeyFromName(payload.name) || resolveRegionKeyFromData(payload) ||
                'unknown';
            const directFunding = Number(payload.direct_funding || 0);
            maxByRegion[regionKey] = Math.max(maxByRegion[regionKey] || 0, directFunding);
            return maxByRegion;
        }, {});

        function getCountryFillColor(countryName) {
            const regionKey = resolveRegionKey(countryName);
            const baseColor = regionBaseColors[regionKey] || regionBaseColors.unknown;

            if (regionKey === 'islands') {
                const islandVariation = 0.14 + (getCountryShadeSeed(countryName) % 5) * 0.04;
                return blendWithWhite(baseColor, islandVariation);
            }

            const countryData = getCountryData(countryName);
            const maxDirectInRegion = Number(regionDirectFundingMax[regionKey] || 0);
            let fadeRatio = 0.55;

            if (countryData && Number(countryData.direct_funding || 0) > 0 && maxDirectInRegion > 0) {
                const intensity = Math.min(1, Number(countryData.direct_funding) / maxDirectInRegion);
                fadeRatio = 0.2 + (1 - intensity) * 0.42;
            } else if (countryData && Number(countryData.total_funding || 0) > 0) {
                fadeRatio = 0.5;
            } else {
                fadeRatio = 0.62;
            }

            const variation = ((getCountryShadeSeed(countryName) % 5) - 2) * 0.015;
            return blendWithWhite(baseColor, Math.max(0.12, Math.min(0.72, fadeRatio + variation)));
        }

        function getCountryNameFromFeature(feature, sourceUrl) {
            const properties = feature && feature.properties ? feature.properties : {};
            const propertyName = properties.NAME || properties.ADMIN || properties.NAME_EN || properties.COUNTRY || properties.name;

            if (propertyName) {
                return propertyName;
            }

            const fileName = decodeURIComponent((sourceUrl.split('/').pop() || '').replace(/\.shp$/i, ''));
            return fileName || 'Country';
        }

        function toFeatureCollection(raw) {
            if (!raw) {
                return {
                    type: 'FeatureCollection',
                    features: []
                };
            }

            if (raw.type === 'FeatureCollection') {
                return raw;
            }

            if (raw.type === 'Feature') {
                return {
                    type: 'FeatureCollection',
                    features: [raw]
                };
            }

            if (Array.isArray(raw)) {
                const combined = [];
                raw.forEach(function(item) {
                    const collection = toFeatureCollection(item);
                    combined.push(...collection.features);
                });

                return {
                    type: 'FeatureCollection',
                    features: combined
                };
            }

            if (typeof raw === 'object') {
                const combined = [];
                Object.keys(raw).forEach(function(key) {
                    const collection = toFeatureCollection(raw[key]);
                    combined.push(...collection.features);
                });

                return {
                    type: 'FeatureCollection',
                    features: combined
                };
            }

            return {
                type: 'FeatureCollection',
                features: []
            };
        }

        function getAfricaFallbackFeatureCollection(raw) {
            if (!raw) {
                return {
                    type: 'FeatureCollection',
                    features: []
                };
            }

            let collection = raw;
            if (
                raw.type === 'Topology'
                && window.topojson
                && raw.objects
                && raw.objects.countries
            ) {
                collection = window.topojson.feature(raw, raw.objects.countries);
            }

            const featureCollection = toFeatureCollection(collection);
            const features = featureCollection.features.filter(function(feature) {
                const countryName = getCountryNameFromFeature(feature, fallbackAfricaMapUrl);
                return auCountryNameSet.has(normalizeCountryName(countryName));
            });

            return {
                type: 'FeatureCollection',
                features: features
            };
        }

        function getDefaultStyle(countryName) {
            return {
                fillColor: getCountryFillColor(countryName),
                weight: 1.5,
                opacity: 1,
                color: '#ffffff',
                fillOpacity: 0.9
            };
        }

        function getTreatyDefaultStyle(countryName) {
            const status = getTreatyStatusForCountry(countryName);
            const hasTreatyAction = status.is_signed || status.is_ratified || status.is_original_submitted;
            return {
                fillColor: getTreatyFillColor(countryName),
                weight: hasTreatyAction ? 2.2 : 0.85,
                opacity: hasTreatyAction ? 1 : 0.72,
                color: hasTreatyAction ? '#1f2937' : '#ffffff',
                fillOpacity: hasTreatyAction ? 0.95 : 0.52
            };
        }

        function treatyStatusLabel(status) {
            if (status.is_original_submitted) {
                return 'Original Submitted to AU Legal';
            }
            if (status.is_ratified) {
                return 'Ratified';
            }
            if (status.is_signed) {
                return 'Signed';
            }
            return 'No recorded action';
        }

        function treatyStatusClass(status) {
            if (status.is_original_submitted) {
                return 'original';
            }
            if (status.is_ratified) {
                return 'ratified';
            }
            if (status.is_signed) {
                return 'signed';
            }
            return 'none';
        }

        function escapeHtml(value) {
            const raw = value === null || value === undefined ? '' : String(value);
            return raw
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function formatDisplayDate(dateValue) {
            if (!dateValue) {
                return 'Not recorded';
            }

            const parsed = new Date(`${dateValue}T00:00:00`);
            if (Number.isNaN(parsed.getTime())) {
                return escapeHtml(dateValue);
            }

            return parsed.toLocaleDateString(undefined, {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
        }

        function getTreatyById(treatyId) {
            const normalizedTreatyId = toTreatyId(treatyId);
            if (!normalizedTreatyId) {
                return null;
            }

            return (Array.isArray(treatiesData) ? treatiesData : []).find((treaty) => toTreatyId(treaty.id) ===
                normalizedTreatyId) || null;
        }

        function getTreatyStatusForCountryByTreaty(countryName, treatyId) {
            const normalizedCode = normalizeCountryCode(countryName);
            const normalizedTreatyId = toTreatyId(treatyId);
            if (!normalizedCode || !normalizedTreatyId) {
                return defaultTreatyStatus;
            }

            const statusIndex = treatyStatusIndexesById[normalizedTreatyId] || {};
            return statusIndex[normalizedCode] || defaultTreatyStatus;
        }

        function getCountryTreatyRows(countryName) {
            const normalizedCode = normalizeCountryCode(countryName);
            if (!normalizedCode) {
                return [];
            }

            return (Array.isArray(treatiesData) ? treatiesData : [])
                .map((treaty) => {
                    const statusIndex = treatyStatusIndexesById[toTreatyId(treaty.id)] || {};
                    const status = {
                        ...defaultTreatyStatus,
                        ...(statusIndex[normalizedCode] || {})
                    };
                    const hasAction = !!(status.is_signed || status.is_ratified || status.is_original_submitted);

                    return {
                        treaty,
                        status,
                        hasAction
                    };
                })
                .filter((row) => row.hasAction)
                .sort((a, b) => {
                    const aDate = a.status.ratified_at || a.status.signed_at || a.status.original_submitted_at || '';
                    const bDate = b.status.ratified_at || b.status.signed_at || b.status.original_submitted_at || '';
                    return String(bDate).localeCompare(String(aDate));
                });
        }

        function renderTreatyDetailSection(title, value) {
            const safeText = (value && String(value).trim() !== '') ? escapeHtml(value) : 'Not provided.';
            return `
                <section class="treaty-detail-section">
                    <h4>${escapeHtml(title)}</h4>
                    <p>${safeText}</p>
                </section>
            `;
        }

        function buildTreatyDetailModalContent(countryName, treaty, status) {
            const countrySafe = escapeHtml(countryName);
            const treatyName = escapeHtml(treaty.short_title || treaty.title || 'Treaty');
            const statusLabel = escapeHtml(treatyStatusLabel(status));
            const statusClass = treatyStatusClass(status);
            const referenceCode = treaty.reference_code ? escapeHtml(treaty.reference_code) : 'Not provided';
            const readMoreUrl = treaty.read_more_url ? escapeHtml(treaty.read_more_url) : '';

            const readMoreSection = readMoreUrl ? `
                <section class="treaty-detail-section">
                    <h4>Read More</h4>
                    <p>
                        <a href="${readMoreUrl}" target="_blank" rel="noopener noreferrer" class="treaty-detail-link">
                            Open official treaty reference in a new tab
                        </a>
                    </p>
                </section>
            ` : '';

            return `
                <div class="treaty-detail-grid">
                    <div class="treaty-detail-card">
                        <div class="label">Member State</div>
                        <div class="value">${countrySafe}</div>
                    </div>
                    <div class="treaty-detail-card">
                        <div class="label">Treaty</div>
                        <div class="value">${treatyName}</div>
                    </div>
                    <div class="treaty-detail-card">
                        <div class="label">Status</div>
                        <div class="value"><span class="treaty-status-pill ${statusClass}">${statusLabel}</span></div>
                    </div>
                    <div class="treaty-detail-card">
                        <div class="label">Signed On</div>
                        <div class="value">${formatDisplayDate(status.signed_at)}</div>
                    </div>
                    <div class="treaty-detail-card">
                        <div class="label">Ratified On</div>
                        <div class="value">${formatDisplayDate(status.ratified_at)}</div>
                    </div>
                    <div class="treaty-detail-card">
                        <div class="label">Original Submitted</div>
                        <div class="value">${formatDisplayDate(status.original_submitted_at)}</div>
                    </div>
                    <div class="treaty-detail-card">
                        <div class="label">Reference Code</div>
                        <div class="value">${referenceCode}</div>
                    </div>
                    <div class="treaty-detail-card">
                        <div class="label">Adoption Date</div>
                        <div class="value">${formatDisplayDate(treaty.adoption_date)}</div>
                    </div>
                    <div class="treaty-detail-card">
                        <div class="label">Entry Into Force</div>
                        <div class="value">${formatDisplayDate(treaty.entry_into_force_date)}</div>
                    </div>
                    <div class="treaty-detail-card">
                        <div class="label">Signed Count</div>
                        <div class="value">${Number(treaty.signed_count || 0)}</div>
                    </div>
                    <div class="treaty-detail-card">
                        <div class="label">Ratified Count</div>
                        <div class="value">${Number(treaty.ratified_count || 0)}</div>
                    </div>
                    <div class="treaty-detail-card">
                        <div class="label">Original Submissions</div>
                        <div class="value">${Number(treaty.original_submitted_count || 0)}</div>
                    </div>
                </div>
                ${renderTreatyDetailSection('Description', treaty.description)}
                ${renderTreatyDetailSection('Overview', treaty.overview)}
                ${renderTreatyDetailSection('Key Provisions', treaty.key_provisions)}
                ${renderTreatyDetailSection('Implementation Framework', treaty.implementation_framework)}
                ${renderTreatyDetailSection('Monitoring & Reporting', treaty.monitoring_and_reporting)}
                ${readMoreSection}
            `;
        }

        function buildCountryTreatyPortfolioContent(countryName) {
            const countrySafe = escapeHtml(countryName);
            const rows = getCountryTreatyRows(countryName);
            const signedCount = rows.filter((row) => row.status.is_signed).length;
            const ratifiedCount = rows.filter((row) => row.status.is_ratified).length;
            const originalCount = rows.filter((row) => row.status.is_original_submitted).length;

            const tableRows = rows.length ? rows.map((row) => {
                const treatyLabel = escapeHtml(row.treaty.short_title || row.treaty.title || 'Treaty');
                const statusLabel = escapeHtml(treatyStatusLabel(row.status));
                const statusClass = treatyStatusClass(row.status);
                return `
                    <tr>
                        <td>${treatyLabel}</td>
                        <td><span class="treaty-status-pill ${statusClass}">${statusLabel}</span></td>
                        <td>${formatDisplayDate(row.status.signed_at)}</td>
                        <td>${formatDisplayDate(row.status.ratified_at)}</td>
                        <td>${formatDisplayDate(row.status.original_submitted_at)}</td>
                    </tr>
                `;
            }).join('') : `
                <tr>
                    <td colspan="5">No treaty action has been recorded for ${countrySafe} yet.</td>
                </tr>
            `;

            return `
                <div class="treaty-detail-grid">
                    <div class="treaty-detail-card">
                        <div class="label">Member State</div>
                        <div class="value">${countrySafe}</div>
                    </div>
                    <div class="treaty-detail-card">
                        <div class="label">Treaties With Activity</div>
                        <div class="value">${rows.length}</div>
                    </div>
                    <div class="treaty-detail-card">
                        <div class="label">Signed</div>
                        <div class="value">${signedCount}</div>
                    </div>
                    <div class="treaty-detail-card">
                        <div class="label">Ratified</div>
                        <div class="value">${ratifiedCount}</div>
                    </div>
                    <div class="treaty-detail-card">
                        <div class="label">Original Submitted</div>
                        <div class="value">${originalCount}</div>
                    </div>
                </div>
                <table class="treaty-detail-table">
                    <thead>
                        <tr>
                            <th>Treaty</th>
                            <th>Status</th>
                            <th>Signed On</th>
                            <th>Ratified On</th>
                            <th>Original Submitted</th>
                        </tr>
                    </thead>
                    <tbody>${tableRows}</tbody>
                </table>
            `;
        }

        function openTreatyDetailModal(countryName, treatyId = '') {
            if (!treatyDetailModalEl || !treatyDetailBodyEl || !treatyDetailTitleEl || !treatyDetailSubtitleEl) {
                return;
            }

            const treaty = treatyId ? getTreatyById(treatyId) : getSelectedTreaty();

            if (treaty) {
                const treatyTitle = treaty.short_title || treaty.title || 'Treaty';
                const treatyStatus = getTreatyStatusForCountryByTreaty(countryName, treaty.id);

                treatyDetailTitleEl.textContent = treatyTitle;
                treatyDetailSubtitleEl.textContent = `${countryName} treaty timeline, status, and implementation details.`;
                treatyDetailBodyEl.innerHTML = buildTreatyDetailModalContent(countryName, treaty, treatyStatus);
            } else {
                treatyDetailTitleEl.textContent = `${countryName} Treaty Portfolio`;
                treatyDetailSubtitleEl.textContent = 'Combined view across all treaties with recorded activity.';
                treatyDetailBodyEl.innerHTML = buildCountryTreatyPortfolioContent(countryName);
            }

            treatyDetailModalEl.classList.add('show');
            treatyDetailModalEl.setAttribute('aria-hidden', 'false');
        }

        function openTreatyDetailModalFromButton(buttonEl, event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }

            if (!buttonEl) {
                return;
            }

            const countryName = buttonEl.dataset.countryName || 'Country';
            const treatyId = buttonEl.dataset.treatyId || '';
            openTreatyDetailModal(countryName, treatyId);
        }

        function closeTreatyDetailModal() {
            if (!treatyDetailModalEl) {
                return;
            }

            treatyDetailModalEl.classList.remove('show');
            treatyDetailModalEl.setAttribute('aria-hidden', 'true');
        }

        function highlightTreatyFeature(e) {
            const layer = e.target;
            const countryName = layer._countryName || 'Country';
            const treatyStatus = getTreatyStatusForCountry(countryName);
            const selectedTreaty = getSelectedTreaty();
            const treatyTitle = selectedTreaty ? selectedTreaty.title : 'Combined Treaties View';
            const safeCountryName = escapeHtml(countryName);
            const safeTreatyId = selectedTreaty ? escapeHtml(toTreatyId(selectedTreaty.id)) : '';

            layer.setStyle({
                weight: 3,
                color: '#111827',
                fillOpacity: 0.95
            });

            if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) {
                layer.bringToFront();
            }

            const popupContent = `
                <div class="popup-content">
                    <div class="popup-country">${countryName}</div>
                    <div class="popup-stat">
                        <span>Treaty Scope:</span>
                        <span>${treatyTitle}</span>
                    </div>
                    <div class="popup-stat">
                        <span>Status:</span>
                        <span>${treatyStatusLabel(treatyStatus)}</span>
                    </div>
                    <div class="popup-stat">
                        <span>Signed On:</span>
                        <span>${treatyStatus.signed_at || '—'}</span>
                    </div>
                    <div class="popup-stat">
                        <span>Ratified On:</span>
                        <span>${treatyStatus.ratified_at || '—'}</span>
                    </div>
                    <div class="popup-stat">
                        <span>Original Submitted:</span>
                        <span>${treatyStatus.original_submitted_at || '—'}</span>
                    </div>
                    <div class="popup-actions">
                        <button
                            type="button"
                            class="popup-learn-btn js-treaty-learn-more"
                            data-country-name="${safeCountryName}"
                            data-treaty-id="${safeTreatyId}"
                            onclick="openTreatyDetailModalFromButton(this, event)"
                        >
                            Learn More
                        </button>
                    </div>
                </div>
            `;
            layer.bindPopup(popupContent).openPopup();
        }

        function resetTreatyHighlight(e) {
            const layer = e.target;
            layer.setStyle(getTreatyDefaultStyle(layer._countryName));
        }

        function getActiveTreatyCountryCodes() {
            const selectedTreaty = getSelectedTreaty();
            const sourceIndex = selectedTreaty ? (treatyStatusIndexesById[toTreatyId(selectedTreaty.id)] || {}) :
                combinedTreatyStatusIndex;

            return Object.keys(sourceIndex).filter((countryCode) => {
                const row = sourceIndex[countryCode] || defaultTreatyStatus;
                return !!(row.is_signed || row.is_ratified || row.is_original_submitted);
            });
        }

        function focusTreatyMapOnActiveCountries(animateFocus = true) {
            if (!treatiesMap) {
                return;
            }

            const activeCodes = getActiveTreatyCountryCodes();
            const focusGroup = L.featureGroup();

            activeCodes.forEach((code) => {
                (treatyCountryLayersByCode[code] || []).forEach((layer) => {
                    focusGroup.addLayer(layer);
                });
            });

            if (focusGroup.getLayers().length > 0) {
                treatiesMap.fitBounds(focusGroup.getBounds(), {
                    padding: [36, 36],
                    maxZoom: 5,
                    animate: animateFocus
                });
                return;
            }

            if (treatiesLayerGroup.getLayers().length > 0) {
                treatiesMap.fitBounds(treatiesLayerGroup.getBounds(), {
                    padding: [28, 28],
                    maxZoom: 4,
                    animate: animateFocus
                });
            }
        }

        function refreshTreatyMapStyles(autoFocus = false) {
            if (!treatiesMap) {
                refreshTreatySelectorMeta();
                return;
            }

            treatiesLayerGroup.eachLayer(function(layer) {
                if (typeof layer.eachLayer === 'function') {
                    layer.eachLayer(function(childLayer) {
                        if (childLayer && childLayer._countryName && typeof childLayer.setStyle ===
                            'function') {
                            childLayer.setStyle(getTreatyDefaultStyle(childLayer._countryName));
                        }
                    });
                }
            });
            refreshTreatySelectorMeta();
            if (autoFocus) {
                focusTreatyMapOnActiveCountries(false);
            }
        }

        function highlightFeature(e) {
            const layer = e.target;
            const countryName = layer._countryName || 'Country';
            const data = getCountryData(countryName);
            const regionName = getRegionDisplayName(countryName);

            layer.setStyle({
                weight: 3,
                color: '#fbbc05',
                fillColor: '#fbbc05',
                fillOpacity: 0.9
            });

            if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) {
                layer.bringToFront();
            }

            if (data && data.total_funding > 0) {
                const popupContent = `
                    <div class="popup-content">
                        <div class="popup-country">${countryName}</div>
                        <div class="popup-stat">
                            <span>Regional Block:</span>
                            <span>${regionName}</span>
                        </div>
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
                        <div class="popup-stat">
                            <span>Regional Block:</span>
                            <span>${regionName}</span>
                        </div>
                        <p style="color: #999;">No direct program funding yet.</p>
                    </div>
                `).openPopup();
            }
        }

        function resetHighlight(e) {
            const layer = e.target;
            layer.setStyle(getDefaultStyle(layer._countryName));
            layer.closePopup();
        }

        function addShapeToMap(rawShape, shapeUrl) {
            const featureCollection = toFeatureCollection(rawShape);

            if (!featureCollection.features.length) {
                return;
            }

            L.geoJSON(featureCollection, {
                style: function(feature) {
                    const countryName = getCountryNameFromFeature(feature, shapeUrl);
                    return getDefaultStyle(countryName);
                },
                onEachFeature: function(feature, layer) {
                    const countryName = getCountryNameFromFeature(feature, shapeUrl);
                    layer._countryName = countryName;
                    layer.on({
                        mouseover: highlightFeature,
                        mouseout: resetHighlight
                    });
                }
            }).addTo(africaLayerGroup);
        }

        function addShapeToTreatiesMap(rawShape, shapeUrl) {
            if (!treatiesMap) {
                return;
            }

            const featureCollection = toFeatureCollection(rawShape);

            if (!featureCollection.features.length) {
                return;
            }

            L.geoJSON(featureCollection, {
                style: function(feature) {
                    const countryName = getCountryNameFromFeature(feature, shapeUrl);
                    return getTreatyDefaultStyle(countryName);
                },
                onEachFeature: function(feature, layer) {
                    const countryName = getCountryNameFromFeature(feature, shapeUrl);
                    layer._countryName = countryName;
                    const countryCode = resolveCountryCode(countryName);
                    if (countryCode) {
                        const normalizedCode = countryCode.toUpperCase();
                        if (!treatyCountryLayersByCode[normalizedCode]) {
                            treatyCountryLayersByCode[normalizedCode] = [];
                        }
                        treatyCountryLayersByCode[normalizedCode].push(layer);
                    }
                    layer.on({
                        mouseover: highlightTreatyFeature,
                        mouseout: resetTreatyHighlight
                    });
                }
            }).addTo(treatiesLayerGroup);
        }

        function initializeTreatiesLayersIfNeeded() {
            if (!treatiesMap) {
                return;
            }

            if (treatiesLayersInitialized) {
                return;
            }

            if (!treatyShapeCache.length) {
                setTreatiesMapStatus('Treaties map is waiting for Africa map data...');
                return;
            }

            setTreatiesMapStatus('Initializing treaty layer...');

            treatyShapeCache.forEach(function(shapeRecord) {
                addShapeToTreatiesMap(shapeRecord.featureCollection, shapeRecord.shapeUrl);
            });

            treatiesLayersInitialized = true;
            refreshTreatyMapStyles(true);

            if (treatiesLayerGroup.getLayers().length > 0) {
                treatiesMap.fitBounds(treatiesLayerGroup.getBounds(), {
                    padding: [30, 30],
                    maxZoom: 4
                });
            }

            setTreatiesMapStatus('Treaties map loaded.');
        }

        function applyAfricaFeatureCollection(featureCollection, sourceUrl, statusMessage) {
            if (!featureCollection.features.length) {
                throw new Error('Fallback Africa map source returned no AU country shapes.');
            }

            if (treatiesMap) {
                treatyShapeCache.push({
                    shapeUrl: sourceUrl,
                    featureCollection: featureCollection
                });
            }

            addShapeToMap(featureCollection, sourceUrl);

            if (africaLayerGroup.getLayers().length > 0) {
                map.fitBounds(africaLayerGroup.getBounds(), {
                    padding: [30, 30],
                    maxZoom: 4
                });
            }

            setMapStatus(statusMessage);

            if (treatiesMap) {
                if (isTreatiesTabActive()) {
                    initializeTreatiesLayersIfNeeded();
                } else {
                    setTreatiesMapStatus('Treaties layer is ready. Open the Treaties tab to initialize.');
                }
            }
        }

        function loadAfricaFallbackMap(reason) {
            setMapStatus('Loading Africa map fallback...');
            if (treatiesMap) {
                setTreatiesMapStatus('Loading Africa map fallback...');
            }

            return fetch(fallbackAfricaMapUrl)
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error(`Fallback map request failed with HTTP ${response.status}.`);
                    }

                    return response.json();
                })
                .then(function(raw) {
                    const featureCollection = getAfricaFallbackFeatureCollection(raw);
                    const statusMessage = reason
                        ? `Africa map loaded from fallback source. ${reason}`
                        : 'Africa map loaded from fallback source.';
                    applyAfricaFeatureCollection(featureCollection, fallbackAfricaMapUrl, statusMessage);
                })
                .catch(function(error) {
                    console.error('Failed to load Africa map fallback:', error);
                    setMapStatus('Africa map could not be loaded. Please add shapefiles to public/assets/Africa or check network access.');
                    if (treatiesMap) {
                        setTreatiesMapStatus('Treaties map could not be loaded because Africa shapes are unavailable.');
                    }
                });
        }

        function loadAfricaShapefiles() {
            if (!shapeFiles.length) {
                loadAfricaFallbackMap('No local shapefiles were found in public/assets/Africa.');
                return;
            }

            function resolveShapeFileUrl(shapeUrl) {
                const rawUrl = (shapeUrl || '').toString().trim();
                if (!rawUrl) {
                    return '';
                }

                try {
                    const resolved = new URL(rawUrl, window.location.href);
                    const isHttpUrl = resolved.protocol === 'http:' || resolved.protocol === 'https:';
                    const looksLikeAfricaShape = /\/assets\/Africa\/[^/]+\.shp$/i.test(resolved.pathname);

                    if (looksLikeAfricaShape && resolved.host !== window.location.host) {
                        return new URL(
                            `${resolved.pathname}${resolved.search}${resolved.hash}`,
                            window.location.origin
                        ).toString();
                    }

                    if (isHttpUrl && resolved.protocol !== window.location.protocol) {
                        resolved.protocol = window.location.protocol;
                    }

                    return resolved.toString();
                } catch (error) {
                    if (rawUrl.startsWith('/')) {
                        return `${window.location.origin}${rawUrl}`;
                    }

                    return rawUrl;
                }
            }

            setMapStatus(`Loading ${shapeFiles.length} shapefiles...`);
            if (treatiesMap) {
                setTreatiesMapStatus(`Preparing ${shapeFiles.length} treaty map source files...`);
            }
            let loaded = 0;

            const loaders = shapeFiles.map(function(shapeUrl) {
                const resolvedShapeUrl = resolveShapeFileUrl(shapeUrl);

                return shp(resolvedShapeUrl)
                    .then(function(rawShape) {
                        const featureCollection = toFeatureCollection(rawShape);
                        if (featureCollection.features.length) {
                            if (treatiesMap) {
                                treatyShapeCache.push({
                                    shapeUrl: resolvedShapeUrl,
                                    featureCollection: featureCollection
                                });
                            }
                            addShapeToMap(featureCollection, resolvedShapeUrl);
                        }
                        loaded += 1;
                        setMapStatus(`Loaded ${loaded}/${shapeFiles.length} shapefiles...`);
                        if (treatiesMap) {
                            setTreatiesMapStatus(`Prepared ${loaded}/${shapeFiles.length} treaty map source files...`);
                        }
                    })
                    .catch(function(error) {
                        throw {
                            shapeUrl: shapeUrl,
                            resolvedShapeUrl: resolvedShapeUrl,
                            error: error
                        };
                    });
            });

            Promise.allSettled(loaders).then(function(results) {
                const failed = results.filter(function(result) {
                    return result.status === 'rejected';
                });

                failed.forEach(function(result) {
                    if (result.reason && result.reason.shapeUrl) {
                        console.warn('Failed to load shapefile:', result.reason.resolvedShapeUrl || result.reason.shapeUrl, result.reason
                            .error);
                    } else {
                        console.warn('Failed to load shapefile:', result.reason);
                    }
                });

                if (africaLayerGroup.getLayers().length > 0) {
                    map.fitBounds(africaLayerGroup.getBounds(), {
                        padding: [30, 30],
                        maxZoom: 4
                    });
                } else {
                    loadAfricaFallbackMap('Local shapefiles were unavailable or unreadable.');
                    return;
                }

                if (failed.length > 0) {
                    setMapStatus(`Map loaded with ${failed.length} shapefile(s) skipped due to read errors.`);
                    if (treatiesMap) {
                        if (isTreatiesTabActive()) {
                            initializeTreatiesLayersIfNeeded();
                        } else {
                            setTreatiesMapStatus(
                                `Treaties layer is ready. ${failed.length} shapefile(s) were skipped due to read errors.`);
                        }
                    }
                } else {
                    setMapStatus('Africa map loaded.');
                    if (treatiesMap) {
                        if (isTreatiesTabActive()) {
                            initializeTreatiesLayersIfNeeded();
                        } else {
                            setTreatiesMapStatus('Treaties layer is ready. Open the Treaties tab to initialize.');
                        }
                    }
                }
            });
        }

        loadAfricaShapefiles();

        if (treatySelectorEl) {
            treatySelectorEl.addEventListener('change', function() {
                activeTreatyId = toTreatyId(this.value || '');
                if (treatiesLayersInitialized) {
                    refreshTreatyMapStyles(true);
                } else {
                    refreshTreatySelectorMeta();
                }
            });
        }

        if (treatyFocusBtn) {
            treatyFocusBtn.addEventListener('click', function() {
                focusTreatyMapOnActiveCountries(true);
            });
        }

        if (treatiesMap) {
            treatiesMap.on('popupopen', function(popupEvent) {
                const popupElement = popupEvent && popupEvent.popup && typeof popupEvent.popup.getElement === 'function' ?
                    popupEvent.popup.getElement() :
                    null;
                const learnMoreBtn = popupElement ? popupElement.querySelector('.js-treaty-learn-more') : null;

                if (learnMoreBtn && !learnMoreBtn.dataset.boundLearnMore) {
                    learnMoreBtn.dataset.boundLearnMore = '1';
                    learnMoreBtn.addEventListener('click', function(clickEvent) {
                        openTreatyDetailModalFromButton(learnMoreBtn, clickEvent);
                    });
                }
            });
        }

        document.addEventListener('click', function(event) {
            const learnMoreBtn = event.target.closest('.js-treaty-learn-more');
            if (!learnMoreBtn) {
                return;
            }

            const countryName = learnMoreBtn.dataset.countryName || 'Country';
            const treatyId = learnMoreBtn.dataset.treatyId || '';
            openTreatyDetailModal(countryName, treatyId);
        });

        if (treatyDetailCloseBtn) {
            treatyDetailCloseBtn.addEventListener('click', closeTreatyDetailModal);
        }

        if (treatyDetailModalEl) {
            treatyDetailModalEl.addEventListener('click', function(event) {
                if (event.target === treatyDetailModalEl) {
                    closeTreatyDetailModal();
                }
            });
        }

        refreshTreatySelectorMeta();

        let resizeTimer = null;
        window.addEventListener('resize', function() {
            if (resizeTimer) {
                clearTimeout(resizeTimer);
            }
            resizeTimer = setTimeout(function() {
                map.invalidateSize();
                if (treatiesMap) {
                    treatiesMap.invalidateSize();
                    focusTreatyMapOnActiveCountries(false);
                }
            }, 180);
        });

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
            const successModal = document.getElementById('success-modal');
            if (successModal) {
                successModal.classList.remove('show');
            }
        }

        // Close modal with ESC key
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeModal();
                closeTreatyDetailModal();
            }
        });

        // Initialize on load
        document.addEventListener('DOMContentLoaded', () => {
            applyFilters();
            refreshTreatySelectorMeta();
            setTimeout(() => {
                map.invalidateSize();
                if (treatiesMap) {
                    treatiesMap.invalidateSize();
                }
            }, 150);

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

</body>

</html>
