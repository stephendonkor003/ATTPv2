@props(['member', 'title' => 'Think Tank Portal', 'showPortalTabs' => true])

@extends('layouts.think-tank')

@section('title', $title.' | ATTP')

@php
    $portalUser = auth()->user();
    $isAdminPreview = (bool) ($portalUser?->isSuperAdmin() || $portalUser?->isAdmin());
    $portalRouteParams = $isAdminPreview ? ['think_tank_member_id' => $member->id] : [];
    $canAccessPortalArea = static function (string $area) use ($portalUser): bool {
        if (! $portalUser) {
            return false;
        }

        if (method_exists($portalUser, 'canAccessThinkTankArea')) {
            return $portalUser->canAccessThinkTankArea($area);
        }

        return match ($area) {
            'dashboard' => $portalUser->can('think_tank.portal.access'),
            'me' => $portalUser->canAny(['think_tank.me.view', 'think_tank.me.submit']),
            'finance' => $portalUser->canAny([
                'think_tank.finance.view',
                'think_tank.finance.manage',
                'think_tank.procurement.view',
            ]),
            'procurement_plans' => $portalUser->canAny([
                'think_tank.procurement_plans.view',
                'think_tank.procurement_plans.manage',
                'think_tank.procurement.view',
                'think_tank.procurement.manage',
            ]),
            'reports' => $portalUser->can('think_tank.reports.submit'),
            'team' => $portalUser->can('think_tank.team.manage'),
            default => false,
        };
    };
    $portalNav = collect([
        [
            'area' => 'dashboard',
            'label' => 'Dashboard',
            'icon' => 'feather-home',
            'route' => 'think-tank.dashboard',
            'patterns' => ['think-tank.dashboard'],
        ],
        [
            'area' => 'me',
            'label' => 'M&E Data',
            'icon' => 'feather-activity',
            'route' => 'think-tank.me-data.index',
            'patterns' => ['think-tank.me-data.*'],
        ],
        [
            'area' => 'finance',
            'label' => 'Finance',
            'icon' => 'feather-credit-card',
            'route' => 'think-tank.finance',
            'patterns' => ['think-tank.finance', 'think-tank.purchase-orders*'],
        ],
        [
            'area' => 'procurement_plans',
            'label' => 'Procurement Plans',
            'icon' => 'feather-clipboard',
            'route' => 'think-tank.procurement-plans',
            'patterns' => ['think-tank.procurement-plans*'],
        ],
        [
            'area' => 'reports',
            'label' => 'Report Uploads',
            'icon' => 'feather-upload-cloud',
            'route' => 'think-tank.report-uploads',
            'patterns' => ['think-tank.report-uploads*', 'think-tank.upload-report-finding'],
        ],
    ])->filter(
        fn (array $item): bool => $canAccessPortalArea($item['area'])
            && Illuminate\Support\Facades\Route::has($item['route'])
    )->map(function (array $item) use ($portalRouteParams): array {
        $item['url'] = route($item['route'], $portalRouteParams);
        $item['active'] = request()->routeIs(...$item['patterns']);

        return $item;
    })->values();
    $accountName = trim((string) ($portalUser?->name ?: 'Portal user'));
    $accountInitials = collect(preg_split('/\s+/', $accountName) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('') ?: 'TT';
    $roleName = $isAdminPreview
        ? ($portalUser?->role?->name ?: 'Administrator')
        : ($portalUser && method_exists($portalUser, 'thinkTankAccessLabel')
            ? $portalUser->thinkTankAccessLabel()
            : ($portalUser?->role?->name ?: 'Think Tank User'));
    $memberName = trim((string) ($member->name ?: 'Think Tank'));
    $memberInitials = collect(preg_split('/\s+/', $memberName) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('') ?: 'TT';
    $memberLogoUrl = trim((string) ($member->logo_url ?? ''));
    $canManageBranding = ! $isAdminPreview
        && $canAccessPortalArea('team')
        && Illuminate\Support\Facades\Route::has('think-tank.branding.logo.update');
@endphp

@push('styles')
    <style>
        .tt-portal-shell {
            min-height: 100vh;
            color: var(--tt-ink);
        }

        .tt-shell-header {
            position: sticky;
            inset-block-start: 0;
            z-index: 1020;
            border-bottom: 1px solid var(--tt-border);
            background: #fff;
            box-shadow: 0 1px 2px rgba(20, 43, 30, .035);
        }

        .tt-shell-inner,
        .tt-shell-main,
        .tt-shell-footer-inner {
            width: min(100% - 40px, 1280px);
            margin-inline: auto;
        }

        .tt-shell-topbar {
            min-height: 66px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .tt-shell-brand {
            min-width: 0;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--tt-ink);
            text-decoration: none;
        }

        .tt-shell-brand:hover {
            color: var(--tt-ink);
        }

        .tt-shell-brand-symbol {
            width: 36px;
            height: 36px;
            display: inline-grid;
            place-items: center;
            flex: 0 0 36px;
            border: 1px solid #cfe0d6;
            border-radius: 9px;
            background: #f2f7f4;
            color: var(--tt-brand-deep);
            font-size: 1rem;
        }

        .tt-shell-brand-copy {
            min-width: 0;
            display: grid;
            gap: 1px;
        }

        .tt-shell-brand-copy strong {
            color: var(--tt-ink);
            max-width: min(31vw, 380px);
            overflow: hidden;
            font-size: .92rem;
            font-weight: 760;
            line-height: 1.2;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .tt-shell-brand-copy small {
            overflow: hidden;
            color: var(--tt-muted);
            font-size: .72rem;
            font-weight: 600;
            line-height: 1.3;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .tt-shell-tools {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
        }

        .tt-co-branding {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-inline-end: 6px;
            padding-inline-end: 14px;
            border-inline-end: 1px solid var(--tt-border);
        }

        .tt-au-brand,
        .tt-member-brand {
            height: 40px;
            display: inline-grid;
            place-items: center;
            overflow: hidden;
            border-radius: 7px;
        }

        .tt-au-brand {
            width: 74px;
            background: #07543c;
            padding: 5px 8px;
        }

        .tt-au-brand img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .tt-member-brand {
            width: 48px;
            border: 1px solid var(--tt-border);
            background: #fff;
            color: var(--tt-brand-deep);
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .035em;
            padding: 4px;
        }

        .tt-member-brand img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .tt-shell-tools .lang-btn {
            min-height: 40px;
            padding: 5px 9px 5px 6px;
            border-color: var(--tt-border-strong);
            background: var(--tt-surface-soft);
            box-shadow: none;
            color: var(--tt-ink);
        }

        .tt-shell-tools .lang-btn:hover,
        .tt-shell-tools .lang-switcher.open .lang-btn {
            border-color: #a9beb2;
            background: var(--tt-brand-soft);
            box-shadow: none;
        }

        .tt-shell-tools .lang-globe {
            background: #deece4;
            color: var(--tt-brand-deep);
        }

        .tt-shell-tools .lang-current-code {
            color: var(--tt-brand-deep);
        }

        .tt-admin-return,
        .tt-mobile-nav-toggle {
            min-height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            border: 1px solid var(--tt-border-strong);
            border-radius: 10px;
            background: #fff;
            color: #405047;
            padding: 8px 11px;
            font-size: .8rem;
            font-weight: 750;
            text-decoration: none;
        }

        .tt-admin-return:hover,
        .tt-mobile-nav-toggle:hover {
            border-color: #a9beb2;
            background: var(--tt-surface-soft);
            color: var(--tt-ink);
        }

        .tt-mobile-nav-toggle {
            display: none;
        }

        .tt-account-menu {
            position: relative;
        }

        .tt-account-menu > summary {
            min-height: 42px;
            display: flex;
            align-items: center;
            gap: 9px;
            border: 1px solid transparent;
            border-radius: 11px;
            cursor: pointer;
            list-style: none;
            padding: 4px 7px;
            user-select: none;
        }

        .tt-account-menu > summary::-webkit-details-marker {
            display: none;
        }

        .tt-account-menu[open] > summary,
        .tt-account-menu > summary:hover {
            border-color: var(--tt-border);
            background: var(--tt-surface-soft);
        }

        .tt-account-avatar {
            width: 32px;
            height: 32px;
            display: inline-grid;
            place-items: center;
            flex: 0 0 32px;
            border-radius: 50%;
            background: var(--tt-brand-soft);
            color: var(--tt-brand-deep);
            font-size: .72rem;
            font-weight: 900;
            letter-spacing: .03em;
        }

        .tt-account-summary-copy {
            max-width: 150px;
            display: grid;
            line-height: 1.2;
        }

        .tt-account-summary-copy strong,
        .tt-account-summary-copy small {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .tt-account-summary-copy strong {
            color: var(--tt-ink);
            font-size: .79rem;
            font-weight: 800;
        }

        .tt-account-summary-copy small {
            color: var(--tt-muted);
            font-size: .68rem;
        }

        .tt-account-panel {
            position: absolute;
            inset-block-start: calc(100% + 9px);
            inset-inline-end: 0;
            z-index: 1050;
            width: min(290px, calc(100vw - 28px));
            border: 1px solid var(--tt-border);
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 18px 44px rgba(22, 53, 38, .14);
            padding: 14px;
        }

        .tt-account-panel strong,
        .tt-account-panel span {
            display: block;
            overflow-wrap: anywhere;
        }

        .tt-account-panel strong {
            color: var(--tt-ink);
            font-size: .9rem;
        }

        .tt-account-panel span {
            color: var(--tt-muted);
            font-size: .76rem;
        }

        .tt-account-role {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid var(--tt-border);
        }

        .tt-account-utility-link {
            min-height: 38px;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
            border-radius: 8px;
            background: var(--tt-surface-soft);
            color: #405047;
            padding: 8px 10px;
            font-size: .77rem;
            font-weight: 750;
            text-decoration: none;
        }

        .tt-account-utility-link:hover {
            background: var(--tt-brand-soft);
            color: var(--tt-brand-deep);
        }

        .tt-branding-control {
            margin-top: 8px;
            border: 1px solid var(--tt-border);
            border-radius: 9px;
            background: #fff;
        }

        .tt-branding-control > summary {
            min-height: 38px;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            list-style: none;
            padding: 8px 10px;
            color: #405047;
            font-size: .77rem;
            font-weight: 750;
        }

        .tt-branding-control > summary::-webkit-details-marker {
            display: none;
        }

        .tt-branding-control > summary i:last-child {
            margin-inline-start: auto;
        }

        .tt-branding-control[open] > summary {
            border-bottom: 1px solid var(--tt-border);
            background: var(--tt-surface-soft);
        }

        .tt-branding-form {
            display: grid;
            gap: 9px;
            padding: 10px;
        }

        .tt-branding-form label {
            color: var(--tt-muted);
            font-size: .7rem;
            font-weight: 700;
        }

        .tt-branding-form input[type="file"] {
            width: 100%;
            border: 1px solid var(--tt-border-strong);
            border-radius: 7px;
            background: #fff;
            color: var(--tt-muted);
            padding: 6px;
            font-size: .7rem;
        }

        .tt-branding-form-actions {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .tt-branding-save,
        .tt-branding-remove {
            min-height: 34px;
            border-radius: 7px;
            padding: 6px 9px;
            font-size: .72rem;
            font-weight: 750;
        }

        .tt-branding-save {
            border: 1px solid var(--tt-brand);
            background: var(--tt-brand);
            color: #fff;
        }

        .tt-branding-remove {
            border: 1px solid #e4c8c8;
            background: #fff;
            color: #8a3030;
        }

        .tt-logout-button {
            width: 100%;
            min-height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 12px;
            border: 1px solid #e4c8c8;
            border-radius: 9px;
            background: #fffafa;
            color: #8a3030;
            font-size: .8rem;
            font-weight: 800;
        }

        .tt-logout-button:hover {
            background: #fcecec;
        }

        .tt-shell-nav {
            display: flex;
            align-items: center;
            gap: 4px;
            border-top: 1px solid #edf2ef;
            padding: 8px 0 10px;
        }

        .tt-shell-nav-link {
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid transparent;
            border-radius: 9px;
            color: #53645a;
            padding: 9px 13px;
            font-size: .84rem;
            font-weight: 750;
            line-height: 1.2;
            text-decoration: none;
            white-space: nowrap;
        }

        .tt-shell-nav-link i {
            color: #718077;
            font-size: .95rem;
        }

        .tt-shell-nav-link:hover {
            border-color: var(--tt-border);
            background: var(--tt-surface-soft);
            color: var(--tt-ink);
        }

        .tt-shell-nav-link.is-active {
            border-color: #cfe1d7;
            background: var(--tt-brand-soft);
            color: var(--tt-brand-deep);
        }

        .tt-shell-nav-link.is-active i {
            color: var(--tt-brand);
        }

        .tt-shell-main {
            min-height: calc(100vh - 190px);
            position: relative;
            isolation: isolate;
            padding-block: 26px 38px;
        }

        .tt-shell-watermark {
            width: min(62vw, 700px);
            height: min(62vw, 700px);
            position: fixed;
            inset-block-start: 54%;
            inset-inline-start: 50%;
            z-index: 0;
            display: grid;
            place-items: center;
            pointer-events: none;
            transform: translate(-50%, -50%);
            user-select: none;
            opacity: .028;
            filter: grayscale(1);
        }

        [dir="rtl"] .tt-shell-watermark {
            transform: translate(50%, -50%);
        }

        .tt-shell-watermark img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .tt-shell-watermark span {
            color: #0f583d;
            font-size: clamp(10rem, 29vw, 21rem);
            font-weight: 800;
            letter-spacing: -.08em;
            line-height: 1;
        }

        .tt-shell-main > :not(.tt-shell-watermark) {
            position: relative;
            z-index: 1;
        }

        .tt-shell-alerts {
            display: grid;
            gap: 10px;
            margin-bottom: 16px;
        }

        .tt-shell-alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            border: 1px solid;
            border-radius: 11px;
            padding: 11px 13px;
            font-size: .84rem;
            font-weight: 650;
        }

        .tt-shell-alert i {
            margin-top: 2px;
        }

        .tt-shell-alert.is-success,
        .tt-shell-alert.is-status {
            border-color: #bcdcc9;
            background: #edf8f1;
            color: #205f3e;
        }

        .tt-shell-alert.is-error {
            border-color: #efc6c6;
            background: #fff4f4;
            color: #873333;
        }

        .tt-shell-alert.is-warning {
            border-color: #ecd6a4;
            background: #fff9e9;
            color: #795b19;
        }

        .think-tank-workspace .nxl-container {
            width: 100%;
            min-height: 0;
            margin: 0;
            padding: 0;
            position: static;
        }

        .think-tank-workspace .top,
        .think-tank-workspace .tt-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .think-tank-workspace .sub {
            margin: 5px 0 0;
            color: var(--tt-muted);
        }

        .think-tank-workspace .grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .think-tank-workspace .grid.two {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .think-tank-workspace .section {
            margin-top: 18px;
        }

        .think-tank-workspace .card {
            border: 1px solid var(--tt-border);
            border-radius: 12px;
            background: var(--tt-surface);
            box-shadow: var(--tt-shadow);
        }

        .think-tank-workspace .metric {
            margin-top: 7px;
            color: var(--tt-ink);
            font-size: 1.45rem;
            font-weight: 850;
        }

        .think-tank-workspace .label {
            color: var(--tt-muted);
            font-size: .78rem;
        }

        .think-tank-workspace table {
            width: 100%;
            border-collapse: collapse;
            font-size: .84rem;
        }

        .think-tank-workspace th,
        .think-tank-workspace td {
            padding: 11px 10px;
            border-bottom: 1px solid var(--tt-border);
            text-align: start;
            vertical-align: top;
        }

        .think-tank-workspace th {
            background: var(--tt-surface-soft);
            color: #4d5e54;
            font-size: .7rem;
            font-weight: 850;
            letter-spacing: .035em;
            text-transform: uppercase;
        }

        .think-tank-workspace .badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            background: #e9f2ee;
            color: #315b48;
            padding: 4px 8px;
            font-size: .7rem;
            font-weight: 800;
        }

        .think-tank-workspace .badge.good {
            background: #e2f4e8;
            color: #22613d;
        }

        .think-tank-workspace form.stack {
            display: grid;
            gap: 10px;
        }

        .think-tank-workspace input,
        .think-tank-workspace select,
        .think-tank-workspace textarea {
            width: 100%;
            border: 1px solid var(--tt-border-strong);
            border-radius: 8px;
            background: #fff;
            color: var(--tt-ink);
            padding: 9px 10px;
            font: inherit;
        }

        .think-tank-workspace textarea {
            min-height: 92px;
            resize: vertical;
        }

        .think-tank-workspace input:focus,
        .think-tank-workspace select:focus,
        .think-tank-workspace textarea:focus {
            border-color: #72a58b;
            box-shadow: 0 0 0 3px rgba(23, 107, 75, .11);
            outline: none;
        }

        .think-tank-workspace .btn-primary,
        .think-tank-workspace .btn:not([class*="btn-"]):not(.light):not(.secondary) {
            border-color: var(--tt-brand);
            background: var(--tt-brand);
            color: #fff;
        }

        .think-tank-workspace .btn-primary:hover,
        .think-tank-workspace .btn:not([class*="btn-"]):not(.light):not(.secondary):hover {
            border-color: var(--tt-brand-deep);
            background: var(--tt-brand-deep);
            color: #fff;
        }

        .think-tank-workspace .btn.light,
        .think-tank-workspace .btn.secondary {
            border: 1px solid var(--tt-border-strong);
            background: #fff;
            color: var(--tt-ink);
        }

        .tt-shell-footer {
            border-top: 1px solid var(--tt-border);
            background: #fff;
        }

        .tt-shell-footer-inner {
            min-height: 58px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            color: var(--tt-muted);
            font-size: .73rem;
        }

        .tt-shell-footer strong {
            color: #4a5b51;
        }

        @media (max-width: 960px) {
            .tt-shell-topbar {
                min-height: 66px;
            }

            .tt-admin-return span,
            .tt-account-summary-copy,
            .tt-shell-tools .lang-current-name {
                display: none;
            }

            .tt-co-branding {
                gap: 6px;
                margin-inline-end: 2px;
                padding-inline-end: 8px;
            }

            .tt-au-brand {
                width: 66px;
            }

            .tt-member-brand {
                width: 40px;
            }

            .tt-mobile-nav-toggle {
                display: inline-flex;
            }

            .tt-shell-nav {
                display: none;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 7px;
                padding: 10px 0 14px;
            }

            .tt-shell-nav.is-open {
                display: grid;
            }

            .tt-shell-nav-link {
                width: 100%;
                white-space: normal;
            }

            .think-tank-workspace .grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .tt-shell-inner,
            .tt-shell-main,
            .tt-shell-footer-inner {
                width: min(100% - 24px, 1280px);
            }

            .tt-shell-brand,
            .tt-admin-return {
                display: none;
            }

            .tt-shell-topbar {
                gap: 7px;
            }

            .tt-au-brand,
            .tt-member-brand {
                height: 36px;
            }

            .tt-au-brand {
                width: 61px;
                padding-inline: 6px;
            }

            .tt-member-brand {
                width: 36px;
            }

            .tt-shell-tools {
                width: 100%;
                justify-content: flex-end;
                gap: 3px;
            }

            .tt-shell-tools .lang-current,
            .tt-shell-tools .lang-caret,
            .tt-account-menu > summary > .feather-chevron-down {
                display: none;
            }

            .tt-shell-tools .lang-btn {
                width: 38px;
                min-width: 38px;
                justify-content: center;
                padding: 4px;
            }

            .tt-account-menu > summary {
                padding-inline: 4px;
            }

            .tt-shell-nav {
                grid-template-columns: 1fr;
            }

            .think-tank-workspace .grid,
            .think-tank-workspace .grid.two {
                grid-template-columns: 1fr;
            }

            .think-tank-workspace .top,
            .think-tank-workspace .tt-top,
            .tt-shell-footer-inner {
                align-items: flex-start;
                flex-direction: column;
            }

            .tt-shell-footer-inner {
                justify-content: center;
                padding-block: 16px;
            }
        }
    </style>
@endpush

@section('content')
    <div class="tt-portal-shell think-tank-workspace" data-tt-portal-shell>
        <header class="tt-shell-header">
            <div class="tt-shell-inner">
                <div class="tt-shell-topbar">
                    <a class="tt-shell-brand" href="{{ route('think-tank.dashboard', $portalRouteParams) }}" aria-label="ATTP Think Tank Portal dashboard">
                        <span class="tt-shell-brand-symbol" aria-hidden="true"><i class="feather-grid"></i></span>
                        <span class="tt-shell-brand-copy">
                            <strong>{{ $memberName }}</strong>
                            <small>Think Tank workspace</small>
                        </span>
                    </a>

                    <div class="tt-shell-tools">
                        <div class="tt-co-branding" aria-label="African Union and {{ $memberName }}">
                            <span class="tt-au-brand" title="African Union" data-tt-au-logo>
                                <img src="{{ asset('assets/images/au.png') }}" alt="African Union">
                            </span>
                            <span class="tt-member-brand" title="{{ $memberName }}" data-tt-member-logo>
                                @if ($memberLogoUrl !== '')
                                    <img src="{{ $memberLogoUrl }}" alt="{{ $memberName }} logo">
                                @else
                                    <span aria-hidden="true">{{ $memberInitials }}</span>
                                    <span class="visually-hidden">{{ $memberName }} logo not uploaded</span>
                                @endif
                            </span>
                        </div>

                        <x-language-selector style="portal" />

                        @if ($isAdminPreview && Illuminate\Support\Facades\Route::has('dashboard'))
                            <a class="tt-admin-return" href="{{ route('dashboard') }}">
                                <i class="feather-arrow-left" aria-hidden="true"></i>
                                <span>Back to administration</span>
                            </a>
                        @endif

                        <details class="tt-account-menu" data-tt-account-menu>
                            <summary aria-label="Open account menu">
                                <span class="tt-account-avatar" aria-hidden="true">{{ $accountInitials }}</span>
                                <span class="tt-account-summary-copy">
                                    <strong>{{ $accountName }}</strong>
                                    <small>{{ $roleName }}</small>
                                </span>
                                <i class="feather-chevron-down" aria-hidden="true"></i>
                            </summary>
                            <div class="tt-account-panel">
                                <strong>{{ $accountName }}</strong>
                                <span>{{ $portalUser?->email }}</span>
                                <span class="tt-account-role">{{ $roleName }}</span>
                                @if ($canAccessPortalArea('team') && \Illuminate\Support\Facades\Route::has('think-tank.team-access'))
                                    <a class="tt-account-utility-link" href="{{ route('think-tank.team-access', $portalRouteParams) }}">
                                        <i class="feather-users" aria-hidden="true"></i>
                                        Manage team access
                                    </a>
                                @endif
                                @if ($canManageBranding)
                                    <details class="tt-branding-control">
                                        <summary>
                                            <i class="feather-image" aria-hidden="true"></i>
                                            Organization logo
                                            <i class="feather-chevron-down" aria-hidden="true"></i>
                                        </summary>
                                        <form class="tt-branding-form"
                                              method="POST"
                                              action="{{ route('think-tank.branding.logo.update') }}"
                                              enctype="multipart/form-data"
                                              data-tt-branding-form>
                                            @csrf
                                            @method('PUT')
                                            <label for="tt-organization-logo">PNG, JPEG or WebP, up to 5 MB</label>
                                            <input id="tt-organization-logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp">
                                            <div class="tt-branding-form-actions">
                                                <button class="tt-branding-save" type="submit">Save logo</button>
                                                @if ($memberLogoUrl !== '')
                                                    <button class="tt-branding-remove" type="submit" name="remove_logo" value="1">Remove</button>
                                                @endif
                                            </div>
                                        </form>
                                    </details>
                                @endif
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="tt-logout-button" type="submit">
                                        <i class="feather-log-out" aria-hidden="true"></i>
                                        Log out
                                    </button>
                                </form>
                            </div>
                        </details>

                        <button class="tt-mobile-nav-toggle" type="button" aria-expanded="false" aria-controls="tt-portal-primary-nav" data-tt-nav-toggle>
                            <i class="feather-menu" aria-hidden="true"></i>
                            <span class="visually-hidden">Open portal navigation</span>
                        </button>
                    </div>
                </div>

                {{-- This global navigation remains visible on every portal page. The legacy
                     showPortalTabs prop is retained only for backwards-compatible components. --}}
                <nav class="tt-shell-nav" id="tt-portal-primary-nav" aria-label="Think tank portal navigation" data-think-tank-area-navigation data-tt-primary-nav>
                    @foreach ($portalNav as $navItem)
                        <a class="tt-shell-nav-link {{ $navItem['active'] ? 'is-active' : '' }}"
                           href="{{ $navItem['url'] }}"
                           @if ($navItem['active']) aria-current="page" @endif>
                            <i class="{{ $navItem['icon'] }}" aria-hidden="true"></i>
                            <span>{{ $navItem['label'] }}</span>
                        </a>
                    @endforeach
                </nav>
            </div>
        </header>

        <main class="tt-shell-main" id="tt-main-content" tabindex="-1">
            <div class="tt-shell-watermark" aria-hidden="true" data-tt-member-watermark>
                @if ($memberLogoUrl !== '')
                    <img src="{{ $memberLogoUrl }}" alt="" role="presentation">
                @else
                    <span>{{ $memberInitials }}</span>
                @endif
            </div>

            @if (session('success') || session('status') || session('error') || session('warning') || (isset($errors) && $errors->any()))
                <div class="tt-shell-alerts" aria-label="Page notifications">
                    @if (session('success'))
                        <div class="tt-shell-alert is-success" role="status">
                            <i class="feather-check-circle" aria-hidden="true"></i>
                            <span>{{ session('success') }}</span>
                        </div>
                    @endif
                    @if (session('status'))
                        <div class="tt-shell-alert is-status" role="status">
                            <i class="feather-info" aria-hidden="true"></i>
                            <span>{{ session('status') }}</span>
                        </div>
                    @endif
                    @if (session('warning'))
                        <div class="tt-shell-alert is-warning" role="alert">
                            <i class="feather-alert-triangle" aria-hidden="true"></i>
                            <span>{{ session('warning') }}</span>
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="tt-shell-alert is-error" role="alert">
                            <i class="feather-alert-circle" aria-hidden="true"></i>
                            <span>{{ session('error') }}</span>
                        </div>
                    @endif
                    @if (isset($errors) && $errors->any())
                        <div class="tt-shell-alert is-error" role="alert">
                            <i class="feather-alert-circle" aria-hidden="true"></i>
                            <span>{{ $errors->first() }}</span>
                        </div>
                    @endif
                </div>
            @endif

            {{ $slot }}
        </main>

        <footer class="tt-shell-footer">
            <div class="tt-shell-footer-inner">
                <span><strong>African Think Tank Platform</strong></span>
                <span>{{ $memberName }}</span>
            </div>
        </footer>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const startPortalShell = () => {
                const shell = document.querySelector('[data-tt-portal-shell]');
                const toggle = shell?.querySelector('[data-tt-nav-toggle]');
                const nav = shell?.querySelector('[data-tt-primary-nav]');
                const account = shell?.querySelector('[data-tt-account-menu]');

                if (!shell || !toggle || !nav) return;

                const setNavigationOpen = (open, returnFocus = false) => {
                    nav.classList.toggle('is-open', open);
                    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                    const label = toggle.querySelector('.visually-hidden');
                    if (label) label.textContent = open ? 'Close portal navigation' : 'Open portal navigation';
                    if (returnFocus) toggle.focus();
                };

                toggle.addEventListener('click', () => {
                    setNavigationOpen(toggle.getAttribute('aria-expanded') !== 'true');
                });

                nav.addEventListener('click', (event) => {
                    if (event.target.closest('a')) setNavigationOpen(false);
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key !== 'Escape') return;
                    if (toggle.getAttribute('aria-expanded') === 'true') setNavigationOpen(false, true);
                    if (account?.open) {
                        account.open = false;
                        account.querySelector('summary')?.focus();
                    }
                });

                document.addEventListener('click', (event) => {
                    if (account?.open && !account.contains(event.target)) account.open = false;
                });

                const desktopQuery = window.matchMedia('(min-width: 961px)');
                const resetForDesktop = (query) => {
                    if (query.matches) setNavigationOpen(false);
                };

                if (desktopQuery.addEventListener) desktopQuery.addEventListener('change', resetForDesktop);
                else desktopQuery.addListener(resetForDesktop);
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', startPortalShell, { once: true });
            } else {
                startPortalShell();
            }
        })();
    </script>
@endpush
