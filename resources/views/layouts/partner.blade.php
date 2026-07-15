<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" data-theme="light">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'ATTP Partner Portal')</title>

    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('admin/assets/images/favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/vendors.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/dataTables.bs5.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/select2-theme.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/css/theme.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/css/datatable-custom.css') }}">

    @stack('styles')

    <style>
        :root {
            --partner-sidebar-width: 260px;
            --partner-ink: #172033;
            --partner-muted: #667085;
            --partner-green: #0f766e;
            --partner-green-dark: #064e3b;
            --partner-mint: #dff5ee;
            --partner-gold: #f5b84b;
            --partner-blue: #2563eb;
            --partner-border: #dbe6ef;
            --partner-surface: #ffffff;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html,
        body {
            max-width: 100%;
            overflow-x: hidden;
        }

        body {
            background:
                linear-gradient(180deg, rgba(223, 245, 238, .72) 0%, rgba(245, 247, 251, .88) 38%, #f5f7fb 100%);
            color: var(--partner-ink);
            font-size: 14px;
        }

        .partner-shell {
            min-height: 100vh;
            display: flex;
        }

        .partner-sidebar {
            width: var(--partner-sidebar-width);
            background:
                linear-gradient(180deg, rgba(4, 59, 50, .98) 0%, rgba(7, 95, 85, .98) 62%, rgba(18, 60, 105, .98) 100%);
            color: #f8fafc;
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 1030;
            padding: 14px 12px;
            overflow-y: auto;
            box-shadow: 14px 0 34px rgba(15, 23, 42, .16);
        }

        .partner-brand {
            border-radius: 8px;
            background: linear-gradient(135deg, rgba(255, 255, 255, .18), rgba(255, 255, 255, .08));
            border: 1px solid rgba(255, 255, 255, .18);
            padding: 12px;
            margin-bottom: 12px;
            box-shadow: 0 12px 24px rgba(2, 44, 34, .22);
        }

        .partner-brand img {
            width: 36px;
            height: 36px;
            object-fit: contain;
            background: #fff;
            border-radius: 8px;
            padding: 4px;
        }

        .partner-brand-title {
            font-size: .88rem;
            font-weight: 800;
            line-height: 1.2;
        }

        .partner-brand-meta {
            color: rgba(248, 250, 252, .72);
            font-size: .78rem;
            margin-top: 3px;
        }

        .partner-readonly-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 10px;
            border-radius: 999px;
            padding: 5px 8px;
            background: rgba(245, 184, 75, .18);
            color: #ffe9bd;
            font-size: .66rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .partner-sidebar-tools {
            display: grid;
            gap: 10px;
            margin-bottom: 12px;
        }

        .partner-notification-card {
            display: block;
            border: 1px solid rgba(255, 255, 255, .16);
            border-radius: 8px;
            padding: 10px;
            color: #fff;
            text-decoration: none;
            background:
                linear-gradient(135deg, rgba(255, 255, 255, .16), rgba(255, 255, 255, .07)),
                rgba(255, 255, 255, .08);
            box-shadow: 0 12px 24px rgba(2, 44, 34, .18);
            transition: transform .18s ease, background-color .18s ease, border-color .18s ease, box-shadow .18s ease;
        }

        .partner-notification-card:hover {
            color: #fff;
            transform: translateY(-3px);
            border-color: rgba(255, 233, 189, .52);
            background:
                linear-gradient(135deg, rgba(255, 233, 189, .20), rgba(255, 255, 255, .08)),
                rgba(255, 255, 255, .12);
            box-shadow: 0 16px 30px rgba(2, 44, 34, .24);
        }

        .partner-notification-head {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 8px;
        }

        .partner-notification-icon {
            position: relative;
            width: 36px;
            height: 36px;
            display: grid;
            place-items: center;
            flex: 0 0 36px;
            border-radius: 8px;
            color: #043b32;
            background: #ffe9bd;
            box-shadow: 0 10px 18px rgba(255, 233, 189, .16);
            transition: transform .18s ease;
        }

        .partner-notification-card:hover .partner-notification-icon {
            transform: rotate(-6deg) scale(1.04);
        }

        .partner-notification-pulse {
            position: absolute;
            top: -4px;
            right: -4px;
            width: 13px;
            height: 13px;
            border: 2px solid #043b32;
            border-radius: 50%;
            background: #22c55e;
        }

        .partner-notification-copy {
            min-width: 0;
            flex: 1;
        }

        .partner-notification-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 7px;
            color: #fff;
            font-size: .8rem;
            font-weight: 900;
            line-height: 1.15;
        }

        .partner-notification-subtitle {
            color: rgba(255, 255, 255, .68);
            font-size: .66rem;
            font-weight: 800;
            margin-top: 3px;
        }

        .partner-notification-badge {
            min-width: 24px;
            height: 24px;
            display: inline-grid;
            place-items: center;
            border-radius: 999px;
            color: #043b32;
            background: #ffe9bd;
            font-size: .72rem;
            font-weight: 900;
            padding: 0 7px;
            box-shadow: inset 0 -1px 0 rgba(4, 59, 50, .12);
        }

        .partner-notification-badge.is-zero {
            color: rgba(255, 255, 255, .78);
            background: rgba(255, 255, 255, .14);
        }

        .partner-notification-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 7px;
        }

        .partner-notification-stat {
            border-radius: 8px;
            padding: 7px;
            background: rgba(255, 255, 255, .11);
            border: 1px solid rgba(255, 255, 255, .08);
        }

        .partner-notification-stat span {
            display: block;
            color: rgba(255, 255, 255, .70);
            font-size: .62rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .partner-notification-stat strong {
            display: block;
            margin-top: 3px;
            font-size: .86rem;
            line-height: 1.15;
        }

        .partner-recent-request {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid rgba(255, 255, 255, .12);
            color: rgba(255, 255, 255, .74);
            font-size: .7rem;
        }

        .partner-search-wrap {
            position: relative;
        }

        .partner-search-wrap i {
            position: absolute;
            top: 50%;
            left: 12px;
            color: rgba(255, 255, 255, .72);
            transform: translateY(-50%);
        }

        .partner-menu-search {
            width: 100%;
            min-height: 38px;
            border: 1px solid rgba(255, 255, 255, .16);
            border-radius: 8px;
            color: #fff;
            background: rgba(255, 255, 255, .10);
            padding: 8px 10px 8px 36px;
            outline: none;
            font-weight: 700;
            font-size: .82rem;
        }

        .partner-menu-search::placeholder {
            color: rgba(255, 255, 255, .64);
        }

        .partner-menu-search:focus {
            border-color: rgba(255, 233, 189, .75);
            box-shadow: 0 0 0 3px rgba(255, 233, 189, .14);
        }

        .partner-nav-empty {
            display: none;
            border: 1px dashed rgba(255, 255, 255, .20);
            border-radius: 8px;
            padding: 12px;
            color: rgba(255, 255, 255, .70);
            font-size: .82rem;
            font-weight: 700;
            text-align: center;
        }

        .partner-nav-empty.is-visible {
            display: block;
        }

        .partner-nav-label {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: rgba(248, 250, 252, .65);
            margin: 14px 8px 6px;
        }

        .partner-nav-link {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 8px 10px;
            border-radius: 8px;
            border: 1px solid transparent;
            color: rgba(248, 250, 252, .88);
            text-decoration: none;
            font-weight: 700;
            margin-bottom: 3px;
            font-size: .85rem;
            transition: transform .18s ease, background-color .18s ease, border-color .18s ease, color .18s ease;
        }

        .partner-nav-link:hover {
            color: #fff;
            background: rgba(255, 255, 255, .12);
            border-color: rgba(255, 255, 255, .12);
            transform: translateX(3px);
        }

        .partner-nav-link.active {
            color: #0f172a;
            background: #f8fafc;
            border-color: rgba(255, 255, 255, .55);
            box-shadow: 0 12px 22px rgba(2, 44, 34, .18);
        }

        .partner-nav-count {
            min-width: 22px;
            height: 22px;
            display: inline-grid;
            place-items: center;
            border-radius: 999px;
            margin-left: auto;
            padding: 0 6px;
            color: #043b32;
            background: #ffe9bd;
            font-size: .68rem;
            font-weight: 900;
        }

        .partner-nav-count.is-zero {
            color: rgba(255, 255, 255, .72);
            background: rgba(255, 255, 255, .14);
        }

        .partner-nav-link.active .partner-nav-count {
            color: #fff;
            background: var(--partner-green);
        }

        .partner-nav-link.is-filtered-out,
        .partner-nav-label.is-filtered-out {
            display: none;
        }

        .partner-sidebar-footer {
            border: 1px solid rgba(255, 255, 255, .14);
            border-radius: 8px;
            margin-top: 14px;
            padding: 10px;
            color: rgba(255, 255, 255, .74);
            background: rgba(3, 59, 50, .38);
            font-size: .76rem;
        }

        .partner-sidebar-footer strong {
            display: block;
            color: #fff;
            font-size: .8rem;
            margin-bottom: 4px;
        }

        .partner-main {
            flex: 1;
            width: calc(100% - var(--partner-sidebar-width));
            margin-left: var(--partner-sidebar-width);
            min-width: 0;
            background: transparent;
        }

        .partner-topbar {
            position: sticky;
            top: 0;
            z-index: 1020;
            min-height: 92px;
            overflow: visible;
            background:
                linear-gradient(135deg, rgba(4, 59, 50, .98) 0%, rgba(15, 118, 110, .98) 54%, rgba(18, 60, 105, .98) 100%);
            border-bottom: 1px solid rgba(255, 255, 255, .16);
            padding: 13px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            backdrop-filter: blur(14px);
            box-shadow: 0 18px 36px rgba(17, 32, 51, .18);
        }

        .partner-topbar::before {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            background:
                linear-gradient(90deg, rgba(255, 255, 255, .09) 1px, transparent 1px),
                linear-gradient(180deg, rgba(255, 255, 255, .07) 1px, transparent 1px);
            background-size: 48px 48px;
            opacity: .16;
        }

        .partner-topbar::after {
            content: "";
            position: absolute;
            left: 22px;
            right: 22px;
            bottom: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255, 233, 189, .64), transparent);
        }

        .partner-topbar-copy {
            position: relative;
            z-index: 1;
            min-width: 0;
            max-width: 820px;
        }

        .partner-topbar-kicker {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #ffe9bd;
            font-size: .7rem;
            font-weight: 900;
            letter-spacing: .06em;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .partner-topbar-title {
            color: #fff;
            font-size: 1.15rem;
            font-weight: 900;
            line-height: 1.18;
            letter-spacing: .01em;
        }

        .partner-topbar-subtitle {
            color: rgba(255, 255, 255, .78);
            font-size: .8rem;
            margin-top: 4px;
            max-width: 780px;
        }

        .partner-topbar-meta {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 10px;
        }

        .partner-topbar-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 30px;
            border: 1px solid rgba(255, 255, 255, .16);
            border-radius: 999px;
            padding: 5px 10px;
            color: #fff;
            background: rgba(255, 255, 255, .12);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, .12);
            font-size: .72rem;
            font-weight: 900;
            white-space: nowrap;
        }

        .partner-topbar-chip i {
            color: #ffe9bd;
        }

        .partner-topbar-chip.is-accent {
            color: #043b32;
            border-color: rgba(255, 233, 189, .54);
            background: #ffe9bd;
        }

        .partner-topbar-chip.is-accent i {
            color: #7c4a03;
        }

        .partner-topbar-actions {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }

        .partner-language-form {
            margin: 0;
        }

        .partner-language-select-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
        }

        .partner-language-select-wrap i {
            position: absolute;
            left: 10px;
            color: #ffe9bd;
            pointer-events: none;
            z-index: 1;
        }

        .partner-language-select {
            min-height: 38px;
            max-width: 170px;
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 8px;
            color: #fff;
            background: rgba(255, 255, 255, .12);
            padding: 8px 32px 8px 34px;
            font-size: .78rem;
            font-weight: 900;
            outline: none;
            appearance: none;
            cursor: pointer;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, .10);
            transition: border-color .18s ease, background-color .18s ease, box-shadow .18s ease;
        }

        .partner-language-select:hover,
        .partner-language-select:focus {
            border-color: rgba(255, 233, 189, .62);
            background: rgba(255, 255, 255, .17);
            box-shadow: 0 0 0 3px rgba(255, 233, 189, .12);
        }

        .partner-language-select option {
            color: #172033;
            background: #fff;
        }

        .partner-language-select-wrap::after {
            content: "";
            position: absolute;
            right: 12px;
            width: 7px;
            height: 7px;
            border-right: 2px solid #ffe9bd;
            border-bottom: 2px solid #ffe9bd;
            pointer-events: none;
            transform: rotate(45deg) translateY(-2px);
        }

        .partner-user-chip {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
            padding: 6px 8px;
            border: 1px solid var(--partner-border);
            border-radius: 8px;
            background: #f8fafc;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, .78);
        }

        .partner-topbar .partner-user-chip {
            border-color: rgba(255, 255, 255, .16);
            color: #fff;
            background: rgba(255, 255, 255, .12);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, .10);
        }

        .partner-topbar .partner-user-chip .text-dark {
            color: #fff !important;
        }

        .partner-topbar .partner-user-chip .text-muted {
            color: rgba(255, 255, 255, .68) !important;
        }

        .partner-user-avatar {
            width: 32px;
            height: 32px;
            flex: 0 0 32px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            color: #fff;
            background: linear-gradient(135deg, #ffe9bd, #f5b84b);
            color: #043b32;
            font-size: .74rem;
            font-weight: 900;
        }

        .partner-user-chip-text {
            min-width: 0;
            max-width: 230px;
        }

        .partner-user-chip-text .small {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .partner-page-header {
            padding: 14px 22px 0;
        }

        .partner-page-header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border: 1px solid rgba(15, 118, 110, .18);
            border-radius: 8px;
            padding: 12px 14px;
            background: linear-gradient(135deg, #ffffff 0%, #eefbf7 100%);
            box-shadow: 0 10px 24px rgba(17, 32, 51, .06);
        }

        .partner-page-title-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .partner-page-icon {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            flex: 0 0 38px;
            border-radius: 8px;
            color: #fff;
            background: linear-gradient(135deg, var(--partner-green), #123c69);
            box-shadow: 0 10px 18px rgba(15, 118, 110, .18);
        }

        .partner-page-title {
            color: var(--partner-ink);
            font-size: .98rem;
            font-weight: 900;
            line-height: 1.2;
            margin: 0;
        }

        .partner-page-subtitle {
            color: var(--partner-muted);
            font-size: .76rem;
            margin-top: 3px;
        }

        .partner-page-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 7px;
        }

        .partner-signed-in-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border-radius: 999px;
            padding: 7px 10px;
            color: var(--partner-green-dark);
            background: #dff5ee;
            font-size: .74rem;
            font-weight: 900;
            white-space: nowrap;
        }

        .partner-page-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            min-height: 34px;
            border: 1px solid rgba(15, 118, 110, .30);
            border-radius: 8px;
            padding: 7px 10px;
            color: var(--partner-green-dark);
            background: #fff;
            font-size: .76rem;
            font-weight: 900;
            text-decoration: none;
            transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease;
        }

        .partner-page-btn:hover {
            color: #fff;
            background: var(--partner-green);
            box-shadow: 0 12px 24px rgba(15, 118, 110, .18);
            transform: translateY(-1px);
        }

        .partner-page-btn.is-dashboard {
            color: #fff;
            border-color: var(--partner-green);
            background: var(--partner-green);
        }

        .partner-page-btn.is-dashboard:hover {
            background: var(--partner-green-dark);
        }

        .partner-content {
            padding: 22px;
        }

        .partner-footer {
            border-top: 1px solid rgba(219, 230, 239, .9);
            background: #f5f8fb;
            padding: 16px 22px;
            color: #64748b;
        }

        .partner-footer-inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            border: 1px solid rgba(255, 255, 255, .16);
            border-radius: 8px;
            padding: 12px 14px;
            background: linear-gradient(135deg, #043b32 0%, #0f766e 62%, #123c69 100%);
            box-shadow: 0 12px 26px rgba(17, 32, 51, .10);
        }

        .partner-footer-brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .partner-footer-mark {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            flex: 0 0 34px;
            border-radius: 8px;
            color: #043b32;
            background: #ffe9bd;
            font-size: .88rem;
        }

        .partner-footer-title {
            color: #fff;
            font-weight: 900;
            line-height: 1.2;
            font-size: .86rem;
        }

        .partner-footer-copy {
            color: rgba(255, 255, 255, .76);
            font-size: .74rem;
            margin-top: 3px;
        }

        .partner-footer-support {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            padding: 7px 10px;
            color: #ffe9bd;
            background: rgba(255, 255, 255, .12);
            font-size: .72rem;
            font-weight: 900;
            text-align: right;
            border: 1px solid rgba(255, 255, 255, .14);
        }

        .partner-card-hover {
            border-radius: 8px;
            border: 1px solid var(--partner-border) !important;
            transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
        }

        .partner-card-hover:hover {
            transform: translateY(-3px);
            border-color: rgba(15, 118, 110, .34) !important;
            box-shadow: 0 18px 38px rgba(17, 32, 51, .12) !important;
        }

        .content .nxl-container,
        .partner-content .nxl-container {
            max-width: 100%;
            margin: 0;
            padding: 0;
            position: static !important;
            top: auto !important;
        }

        .partner-content > .nxl-container > .page-header {
            display: none !important;
        }

        .partner-content .card,
        .partner-content .partner-panel,
        .partner-content .partner-stat-card,
        .partner-content .partner-action-card {
            border-radius: 8px !important;
        }

        @media (max-width: 991.98px) {
            .partner-sidebar {
                position: static;
                width: 100%;
                min-height: auto;
            }

            .partner-shell {
                display: block;
            }

            .partner-main {
                width: 100%;
                margin-left: 0;
            }

            .partner-content {
                padding: 18px;
            }

            .partner-topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .partner-topbar-actions,
            .partner-user-chip {
                width: 100%;
            }

            .partner-user-chip-text {
                max-width: none;
            }

            .partner-page-header {
                padding: 14px 18px 0;
            }

            .partner-page-header-inner {
                align-items: flex-start;
                flex-direction: column;
            }

            .partner-page-actions {
                justify-content: flex-start;
                width: 100%;
            }

            .partner-footer-inner {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    @php
        $partnerUser = auth()->user();
        $partnerName = $partnerUser?->name ?? 'Partner';
        $partnerEmail = $partnerUser?->email ?? '';
        $partnerFunder = $partnerUser?->partnerFunder();
        $partnerOrgName = $partnerFunder?->name ?? 'Funding Partner';
        $partnerInitials = collect(preg_split('/\s+/', trim($partnerName)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn ($namePart) => strtoupper(substr($namePart, 0, 1)))
            ->implode('') ?: 'FP';
        $partnerLocales = [
            'en' => ['short' => 'EN', 'name' => 'English'],
            'fr' => ['short' => 'FR', 'name' => 'French'],
            'ar' => ['short' => 'AR', 'name' => 'Arabic'],
            'pt' => ['short' => 'PT', 'name' => 'Portuguese'],
            'es' => ['short' => 'ES', 'name' => 'Spanish'],
            'sw' => ['short' => 'SW', 'name' => 'Kiswahili'],
        ];
        $partnerCurrentLocale = app()->getLocale();
        $partnerUnreadNotificationCount = $partnerUser?->unreadNotifications()->count() ?? 0;
        $partnerOpenRequestCount = $partnerFunder
            ? \App\Models\PartnerInformationRequest::where('funder_id', $partnerFunder->id)
                ->whereIn('status', ['pending', 'in_progress'])
                ->count()
            : 0;
        $partnerCompletedRequestCount = $partnerFunder
            ? \App\Models\PartnerInformationRequest::where('funder_id', $partnerFunder->id)
                ->where('status', 'completed')
                ->count()
            : 0;
        $partnerRecentRequest = $partnerFunder
            ? \App\Models\PartnerInformationRequest::where('funder_id', $partnerFunder->id)
                ->latest()
                ->first()
            : null;
        $partnerSidebarNotificationCount = $partnerUnreadNotificationCount + $partnerOpenRequestCount;
        $partnerRouteTitle = match (true) {
            request()->routeIs('partner.dashboard') => 'Partner Dashboard',
            request()->routeIs('partner.programs.index') => 'Funded Programs',
            request()->routeIs('partner.programs.show') => 'Program Details',
            request()->routeIs('partner.programs.report') => 'Program Report',
            request()->routeIs('partner.projects.show') => 'Project Details',
            request()->routeIs('partner.activities.show') => 'Activity Details',
            request()->routeIs('partner.insights') => 'Program Insights',
            request()->routeIs('partner.reports.financial-position') => 'Financial Position',
            request()->routeIs('partner.reports.*') => 'Partner Reports',
            request()->routeIs('partner.think-tanks.*') => 'Think Tank Deep Search',
            request()->routeIs('partner.workplan.*') => 'Work Plan Review',
            request()->routeIs('partner.requests.create') => 'New Information Request',
            request()->routeIs('partner.requests.show') => 'Request Details',
            request()->routeIs('partner.requests.*') => 'Information Requests',
            request()->routeIs('partner.profile.*') => 'Profile Settings',
            default => 'Partner Workspace',
        };
        $partnerRouteSubtitle = match (true) {
            request()->routeIs('partner.dashboard') => 'Signed in to review funded programs, reports and delivery progress.',
            request()->routeIs('partner.programs.*', 'partner.projects.*', 'partner.activities.*') => 'Signed in with read-only access to partner-funded delivery records.',
            request()->routeIs('partner.reports.*', 'partner.insights') => 'Signed in to review partner reports, analytics and financial position.',
            request()->routeIs('partner.think-tanks.*') => 'Signed in to review think tank performance and supporting evidence.',
            request()->routeIs('partner.workplan.*') => 'Signed in to review approved work plan records.',
            request()->routeIs('partner.requests.*') => 'Signed in to track information requests and ATTP responses.',
            request()->routeIs('partner.profile.*') => 'Signed in to manage your partner portal profile.',
            default => 'Signed in to the ATTP funding partner workspace.',
        };
        $partnerPageTitle = trim($__env->yieldContent('partner_page_title', $__env->yieldContent('title', $partnerRouteTitle))) ?: $partnerRouteTitle;
        $partnerPageSubtitle = trim($__env->yieldContent('partner_page_subtitle', $partnerRouteSubtitle)) ?: $partnerRouteSubtitle;
    @endphp

    <div class="partner-shell">
        <aside class="partner-sidebar">
            <div class="partner-brand">
                <div class="d-flex align-items-center gap-3">
                    <img src="{{ asset('assets/images/au.png') }}" alt="ATTP">
                    <div>
                        <div class="partner-brand-title">ATTP Partner Portal</div>
                        <div class="partner-brand-meta">{{ $partnerOrgName }}</div>
                    </div>
                </div>
                <div class="partner-readonly-pill">
                    <i class="feather-lock"></i> Read-only workspace
                </div>
            </div>

            <div class="partner-sidebar-tools">
                @can('partner.requests.view')
                    <a href="{{ route('partner.requests.index') }}" class="partner-notification-card">
                        <div class="partner-notification-head">
                            <div class="partner-notification-icon">
                                <i class="feather-bell"></i>
                                @if($partnerSidebarNotificationCount > 0)
                                    <span class="partner-notification-pulse"></span>
                                @endif
                            </div>
                            <div class="partner-notification-copy">
                                <div class="partner-notification-title">
                                    <span>Partner alerts</span>
                                    <span class="partner-notification-badge {{ $partnerSidebarNotificationCount === 0 ? 'is-zero' : '' }}">
                                        {{ number_format($partnerSidebarNotificationCount) }}
                                    </span>
                                </div>
                                <div class="partner-notification-subtitle">Requests, responses and updates</div>
                            </div>
                        </div>
                        <div class="partner-notification-grid">
                            <div class="partner-notification-stat">
                                <span>Open requests</span>
                                <strong>{{ number_format($partnerOpenRequestCount) }}</strong>
                            </div>
                            <div class="partner-notification-stat">
                                <span>Completed</span>
                                <strong>{{ number_format($partnerCompletedRequestCount) }}</strong>
                            </div>
                        </div>
                        @if($partnerRecentRequest)
                            <div class="partner-recent-request">
                                Latest: {{ \Illuminate\Support\Str::limit($partnerRecentRequest->subject, 42) }}
                            </div>
                        @endif
                    </a>
                @endcan

                <div class="partner-search-wrap">
                    <i class="feather-search"></i>
                    <input type="search"
                        class="partner-menu-search"
                        placeholder="Search menus"
                        aria-label="Search partner menus"
                        data-partner-menu-search>
                </div>
            </div>

            <div class="partner-nav-empty" data-partner-menu-empty>No menu found.</div>

            <div class="partner-nav-label" data-partner-nav-label="workspace">Workspace</div>
            <a href="{{ route('partner.dashboard') }}"
                class="partner-nav-link {{ request()->routeIs('partner.dashboard') ? 'active' : '' }}"
                data-partner-menu-item
                data-partner-nav-section="workspace"
                data-partner-menu-label="dashboard overview home">
                <i class="feather-home"></i> Dashboard
            </a>
            <a href="{{ route('partner.programs.index') }}"
                class="partner-nav-link {{ request()->routeIs('partner.programs.*', 'partner.projects.*', 'partner.activities.*') ? 'active' : '' }}"
                data-partner-menu-item
                data-partner-nav-section="workspace"
                data-partner-menu-label="funded programs projects activities">
                <i class="feather-folder"></i> Funded Programs
            </a>
            <a href="{{ route('partner.insights') }}"
                class="partner-nav-link {{ request()->routeIs('partner.insights') ? 'active' : '' }}"
                data-partner-menu-item
                data-partner-nav-section="workspace"
                data-partner-menu-label="insights analytics charts">
                <i class="feather-bar-chart-2"></i> Insights
            </a>
            <a href="{{ route('partner.reports.index') }}"
                class="partner-nav-link {{ request()->routeIs('partner.reports.*') ? 'active' : '' }}"
                data-partner-menu-item
                data-partner-nav-section="workspace"
                data-partner-menu-label="reports finance financial position performance">
                <i class="feather-pie-chart"></i> Reports
            </a>
            <a href="{{ route('partner.think-tanks.deep-search') }}"
                class="partner-nav-link {{ request()->routeIs('partner.think-tanks.*') ? 'active' : '' }}"
                data-partner-menu-item
                data-partner-nav-section="workspace"
                data-partner-menu-label="think tank deep search research">
                <i class="feather-search"></i> Think Tank Deep Search
            </a>
            <a href="{{ route('partner.workplan.index') }}"
                class="partner-nav-link {{ request()->routeIs('partner.workplan.*') ? 'active' : '' }}"
                data-partner-menu-item
                data-partner-nav-section="workspace"
                data-partner-menu-label="work plan no objection review">
                <i class="feather-check-square"></i> Work Plan
            </a>
            @can('partner.requests.view')
                <a href="{{ route('partner.requests.index') }}"
                    class="partner-nav-link {{ request()->routeIs('partner.requests.*') ? 'active' : '' }}"
                    data-partner-menu-item
                    data-partner-nav-section="workspace"
                    data-partner-menu-label="information requests notifications messages">
                    <i class="feather-message-square"></i> Information Requests
                    <span class="partner-nav-count {{ $partnerOpenRequestCount === 0 ? 'is-zero' : '' }}">
                        {{ number_format($partnerOpenRequestCount) }}
                    </span>
                </a>
            @endcan
            <a href="{{ route('grm.submissions.create') }}"
                class="partner-nav-link {{ request()->routeIs('grm.submissions.*') ? 'active' : '' }}"
                data-partner-menu-item
                data-partner-nav-section="workspace"
                data-partner-menu-label="grievance redress mechanism complaints concerns cases">
                <i class="feather-alert-octagon"></i> Grievance Redress Mechanism
            </a>

            <div class="partner-nav-label" data-partner-nav-label="account">Account</div>
            @can('partner.profile.edit')
                <a href="{{ route('partner.profile.edit') }}"
                    class="partner-nav-link {{ request()->routeIs('partner.profile.*') ? 'active' : '' }}"
                    data-partner-menu-item
                    data-partner-nav-section="account"
                    data-partner-menu-label="profile account settings">
                    <i class="feather-user"></i> Profile
                </a>
            @endcan
            <a href="{{ route('logout') }}" class="partner-nav-link"
                data-partner-menu-item
                data-partner-nav-section="account"
                data-partner-menu-label="logout sign out"
                onclick="event.preventDefault(); document.getElementById('partner-logout-form').submit();">
                <i class="feather-log-out"></i> Logout
            </a>
            <form id="partner-logout-form" method="POST" action="{{ route('logout') }}" class="d-none">
                @csrf
            </form>

            <div class="partner-sidebar-footer">
                <strong>ATTP Partner Access</strong>
                <div>Secure read-only visibility into funded programs, reports and delivery records.</div>
            </div>
        </aside>

        <main class="partner-main">
            <header class="partner-topbar">
                <div class="partner-topbar-copy">
                    <div class="partner-topbar-kicker">
                        <i class="feather-shield"></i> Integrated Funding Partner Dashboard
                    </div>
                    <div class="partner-topbar-title">{{ $partnerOrgName }}</div>
                    <div class="partner-topbar-subtitle">
                        An integrated dashboard providing deep insight for portfolio funding partners across funding, delivery, reports, documents and evidence.
                    </div>
                    <div class="partner-topbar-meta">
                        <span class="partner-topbar-chip">
                            <i class="feather-clock"></i>
                            <span data-partner-current-time>{{ now()->format('D, M j H:i:s') }}</span>
                        </span>
                        <span class="partner-topbar-chip">
                            <i class="feather-map-pin"></i>
                            <span data-partner-current-location>{{ config('app.timezone', 'Africa/Accra') }}</span>
                        </span>
                        <span class="partner-topbar-chip is-accent">
                            <i class="feather-trending-up"></i> Portfolio insight
                        </span>
                    </div>
                </div>
                <div class="partner-topbar-actions">
                    @can('partner.requests.create')
                        <a href="{{ route('partner.requests.create') }}" class="btn btn-primary btn-sm">
                            <i class="feather-plus-circle me-1"></i> New Request
                        </a>
                    @endcan
                    <form method="POST"
                        action="{{ route('language.switch', $partnerCurrentLocale) }}"
                        class="partner-language-form"
                        data-partner-language-form
                        data-action-template="{{ route('language.switch', '__LOCALE__') }}">
                        @csrf
                        <label class="partner-language-select-wrap">
                            <i class="feather-globe"></i>
                            <span class="visually-hidden">Select language</span>
                            <select class="partner-language-select" data-partner-language-select aria-label="Select language">
                                @foreach($partnerLocales as $localeCode => $locale)
                                    <option value="{{ $localeCode }}" @selected($partnerCurrentLocale === $localeCode)>
                                        {{ $locale['short'] }} - {{ $locale['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    </form>
                    <div class="partner-user-chip">
                        <div class="partner-user-avatar">{{ $partnerInitials }}</div>
                        <div class="partner-user-chip-text">
                            <div class="fw-bold text-dark">{{ $partnerName }}</div>
                            <div class="small text-muted">{{ $partnerEmail }}</div>
                        </div>
                    </div>
                </div>
            </header>

            <section class="partner-page-header">
                <div class="partner-page-header-inner">
                    <div class="partner-page-title-wrap">
                        <div class="partner-page-icon">
                            <i class="feather-compass"></i>
                        </div>
                        <div class="min-w-0">
                            <h1 class="partner-page-title">{{ $partnerPageTitle }}</h1>
                            <div class="partner-page-subtitle">{{ $partnerPageSubtitle }}</div>
                        </div>
                    </div>
                    <div class="partner-page-actions">
                        <span class="partner-signed-in-pill">
                            <i class="feather-check-circle"></i> Signed in as {{ \Illuminate\Support\Str::limit($partnerName, 24) }}
                        </span>
                        <a href="{{ route('partner.dashboard') }}"
                            class="partner-page-btn"
                            onclick="if (window.history.length > 1) { event.preventDefault(); window.history.back(); }">
                            <i class="feather-arrow-left"></i> Back
                        </a>
                        <a href="{{ route('partner.dashboard') }}" class="partner-page-btn is-dashboard">
                            <i class="feather-home"></i> Dashboard
                        </a>
                    </div>
                </div>
            </section>

            <div class="partner-content">
                @yield('content')
            </div>

            <footer class="partner-footer">
                <div class="partner-footer-inner">
                    <div class="partner-footer-brand">
                        <div class="partner-footer-mark">
                            <i class="feather-shield"></i>
                        </div>
                        <div>
                            <div class="partner-footer-title">ATTP Partner Portal</div>
                            <div class="partner-footer-copy">Funding partner workspace for dashboards, reports and portfolio review.</div>
                        </div>
                    </div>
                    <div class="partner-footer-support">
                        <i class="feather-tool"></i>
                        Developed, maintained and supported by the ATTP Technical Team.
                    </div>
                </div>
            </footer>
        </main>
    </div>

    <script src="{{ asset('admin/assets/vendors/js/vendors.min.js') }}"></script>
    <script src="{{ asset('admin/assets/vendors/js/dataTables.min.js') }}"></script>
    <script src="{{ asset('admin/assets/vendors/js/dataTables.bs5.min.js') }}"></script>
    <script src="{{ asset('admin/assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('admin/assets/vendors/js/select2-active.min.js') }}"></script>
    <script src="{{ asset('admin/assets/js/common-init.min.js') }}"></script>
    <script src="{{ asset('admin/assets/js/datatable-config.js') }}"></script>

    <script>
        (function () {
            const searchInput = document.querySelector('[data-partner-menu-search]');
            const menuItems = Array.from(document.querySelectorAll('[data-partner-menu-item]'));
            const labels = Array.from(document.querySelectorAll('[data-partner-nav-label]'));
            const emptyState = document.querySelector('[data-partner-menu-empty]');

            if (!searchInput || menuItems.length === 0) {
                return;
            }

            const normalize = (value) => (value || '').toString().toLowerCase().trim();

            const filterMenus = () => {
                const query = normalize(searchInput.value);
                let visibleCount = 0;

                menuItems.forEach((item) => {
                    const label = normalize(item.dataset.partnerMenuLabel + ' ' + item.textContent);
                    const isMatch = query === '' || label.includes(query);

                    item.classList.toggle('is-filtered-out', !isMatch);

                    if (isMatch) {
                        visibleCount += 1;
                    }
                });

                labels.forEach((label) => {
                    const section = label.dataset.partnerNavLabel;
                    const hasVisibleItem = menuItems.some((item) => {
                        return item.dataset.partnerNavSection === section
                            && !item.classList.contains('is-filtered-out');
                    });

                    label.classList.toggle('is-filtered-out', query !== '' && !hasVisibleItem);
                });

                if (emptyState) {
                    emptyState.classList.toggle('is-visible', visibleCount === 0);
                }
            };

            searchInput.addEventListener('input', filterMenus);
            searchInput.addEventListener('search', filterMenus);
        })();

        (function () {
            const timeTarget = document.querySelector('[data-partner-current-time]');
            const locationTarget = document.querySelector('[data-partner-current-location]');
            const languageForm = document.querySelector('[data-partner-language-form]');
            const languageSelect = document.querySelector('[data-partner-language-select]');

            if (locationTarget && window.Intl) {
                const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

                if (timeZone) {
                    locationTarget.textContent = timeZone.replace(/_/g, ' ');
                }
            }

            if (!timeTarget) {
                return;
            }

            const updateClock = () => {
                timeTarget.textContent = new Date().toLocaleString([], {
                    weekday: 'short',
                    month: 'short',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
            };

            updateClock();
            window.setInterval(updateClock, 1000);

            if (languageForm && languageSelect) {
                languageSelect.addEventListener('change', function () {
                    const template = languageForm.dataset.actionTemplate;
                    const locale = this.value;

                    if (!template || !locale) {
                        return;
                    }

                    languageForm.action = template.replace('__LOCALE__', encodeURIComponent(locale));
                    languageForm.submit();
                });
            }
        })();
    </script>

    @stack('scripts')
    @stack('modals')
</body>

</html>
