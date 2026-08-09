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

        return method_exists($portalUser, 'canAccessThinkTankArea')
            ? $portalUser->canAccessThinkTankArea($area)
            : false;
    };
    $makeNavItem = static function (
        ?string $area,
        string $label,
        string $caption,
        string $icon,
        string $routeName,
        array $patterns,
        array $extraParams = [],
        ?string $fragment = null,
        bool $includePortalParams = true
    ) use ($canAccessPortalArea, $portalRouteParams): ?array {
        if (($area !== null && ! $canAccessPortalArea($area)) || ! Route::has($routeName)) {
            return null;
        }

        $parameters = array_merge($includePortalParams ? $portalRouteParams : [], $extraParams);
        $url = route($routeName, $parameters).($fragment ? '#'.$fragment : '');

        return [
            'area' => $area,
            'label' => $label,
            'caption' => $caption,
            'icon' => $icon,
            'route' => $routeName,
            'patterns' => $patterns,
            'url' => $url,
            'active' => $patterns !== []
                && request()->routeIs(...$patterns)
                && collect($extraParams)->every(fn ($value, $key): bool => (string) request()->query($key) === (string) $value),
            'type' => 'link',
        ];
    };

    $dashboardItems = collect([
        $makeNavItem('dashboard', 'Overview', 'Priorities and progress', 'feather-home', 'think-tank.dashboard', ['think-tank.dashboard']),
        $portalUser?->isThinkTankUser() && Route::has('think-tank.grievances.create')
            ? $makeNavItem(null, 'Grievance support', 'Confidential assistance', 'feather-shield', 'think-tank.grievances.create', ['think-tank.grievances.*'], [], null, false)
            : null,
        [
            'label' => 'Personalize workspace',
            'caption' => 'Theme and layout',
            'icon' => 'feather-sliders',
            'active' => false,
            'type' => 'settings',
            'url' => null,
        ],
    ])->filter()->values();

    $evaluationTemplateItems = collect([
        $portalUser?->can('think_tank.procurement_plans.manage')
            ? $makeNavItem('procurement_plans', 'Technical evaluations', 'Technical scoring templates', 'feather-award', 'think-tank.evaluation-templates.technical', ['think-tank.evaluation-templates.technical'])
            : null,
        $portalUser?->can('think_tank.procurement_plans.manage')
            ? $makeNavItem('procurement_plans', 'Financial evaluations', 'Financial scoring templates', 'feather-dollar-sign', 'think-tank.evaluation-templates.financial', ['think-tank.evaluation-templates.financial'])
            : null,
    ])->filter()->values();
    $evaluationTemplateMenu = $evaluationTemplateItems->isNotEmpty()
        ? [
            'label' => 'Templates',
            'caption' => 'Evaluation forms and criteria',
            'icon' => 'feather-layers',
            'active' => $evaluationTemplateItems->contains(fn (array $item): bool => (bool) ($item['active'] ?? false)),
            'type' => 'nested',
            'children' => $evaluationTemplateItems,
            'url' => null,
        ]
        : null;

    $procurementItems = collect([
        $makeNavItem('procurement_plans', 'Annual plans', 'Financial-year folders', 'feather-folder', 'think-tank.procurement-plans', ['think-tank.procurement-plans', 'think-tank.procurement-plans.show']),
        $portalUser?->can('think_tank.procurement_plans.manage')
            ? $makeNavItem('procurement_plans', 'Create a plan', 'Start a new annual folder', 'feather-folder-plus', 'think-tank.procurement-plans.create', ['think-tank.procurement-plans.create'])
            : null,
        $portalUser?->can('think_tank.procurement_plans.manage')
            ? $makeNavItem('procurement_plans', 'Assignments', 'Assign evaluation team members', 'feather-user-check', 'think-tank.evaluation-assignments.index', ['think-tank.evaluation-assignments.*'])
            : null,
        $evaluationTemplateMenu,
        Route::has('think-tank.evaluations.index') && $portalUser?->can('evaluations.evaluate')
            ? $makeNavItem('dashboard', 'My evaluations', 'Applications assigned to me', 'feather-clipboard', 'think-tank.evaluations.index', ['think-tank.evaluations.*'])
            : null,
    ])->filter()->values();

    $meItems = collect([
        $makeNavItem('me', 'M&E dashboard', 'Targets, results and deadlines', 'feather-bar-chart-2', 'think-tank.me-dashboard', ['think-tank.me-dashboard']),
        $makeNavItem('me', 'Indicator data', 'Assigned reporting forms', 'feather-activity', 'think-tank.me-data.index', ['think-tank.me-data.*']),
        $makeNavItem('me', 'Performance reports', 'Report lifecycle', 'feather-trending-up', 'think-tank.performance-reports.index', ['think-tank.performance-reports.*']),
        $makeNavItem('me', 'Notifications', 'Deadlines and decisions', 'feather-bell', 'think-tank.reporting-notifications.index', ['think-tank.reporting-notifications.*']),
    ])->filter()->values();

    $reportingItems = collect([
        $makeNavItem('reports', 'Submit activity report', 'Progress and evidence', 'feather-upload-cloud', 'think-tank.report-uploads', ['think-tank.report-uploads*']),
        $makeNavItem('reports', 'Reports and findings', 'Combined submission workspace', 'feather-file-text', 'think-tank.upload-report-finding', ['think-tank.upload-report-finding']),
        $makeNavItem('finance', 'Finance and payments', 'Transfers and receipts', 'feather-credit-card', 'think-tank.finance', ['think-tank.finance', 'think-tank.purchase-orders*']),
    ])->filter()->values();

    $auditItems = collect([
        $makeNavItem('dashboard', 'All activity', 'Complete organization history', 'feather-list', 'think-tank.audit-trails', ['think-tank.audit-trails'], ['scope' => 'all']),
        $canAccessPortalArea('procurement_plans')
            ? $makeNavItem('dashboard', 'Procurement history', 'Plans, items and decisions', 'feather-briefcase', 'think-tank.audit-trails', ['think-tank.audit-trails'], ['scope' => 'procurement'])
            : null,
        $canAccessPortalArea('me')
            ? $makeNavItem('dashboard', 'M&E history', 'Reports and lifecycle events', 'feather-bar-chart-2', 'think-tank.audit-trails', ['think-tank.audit-trails'], ['scope' => 'me'])
            : null,
    ])->filter()->values();

    $userItems = collect([
        $makeNavItem('team', 'User directory', 'Staff accounts and roles', 'feather-users', 'think-tank.team-access', ['think-tank.team-access*']),
        $makeNavItem('team', 'Add a user', 'Create a staff account', 'feather-user-plus', 'think-tank.team-access', [], [], 'add-user'),
        $makeNavItem('team', 'Roles and access', 'Review staff permissions', 'feather-key', 'think-tank.team-access', [], [], 'team-directory'),
    ])->filter()->values();

    $portalGroups = collect([
        ['key' => 'dashboard', 'label' => 'Dashboard', 'caption' => 'Workspace overview', 'icon' => 'feather-grid', 'items' => $dashboardItems],
        ['key' => 'procurement', 'label' => 'Procurement', 'caption' => 'Plan and execute', 'icon' => 'feather-briefcase', 'items' => $procurementItems],
        ['key' => 'me', 'label' => 'Monitoring & Evaluation', 'caption' => 'Indicators and results', 'icon' => 'feather-activity', 'items' => $meItems],
        ['key' => 'reporting', 'label' => 'Reporting', 'caption' => 'Reports and finance', 'icon' => 'feather-file-text', 'items' => $reportingItems],
        ['key' => 'audit', 'label' => 'Audit Trails', 'caption' => 'Accountable activity', 'icon' => 'feather-clock', 'items' => $auditItems],
        ['key' => 'users', 'label' => 'Users', 'caption' => 'People and permissions', 'icon' => 'feather-users', 'items' => $userItems],
    ])->filter(fn (array $group): bool => $group['items']->isNotEmpty())
        ->map(function (array $group): array {
            $group['active'] = $group['items']->contains(fn (array $item): bool => (bool) ($item['active'] ?? false));

            return $group;
        })->values();

    $portalNav = $portalGroups
        ->flatMap(fn (array $group) => $group['items']->flatMap(
            fn (array $item) => ($item['type'] ?? 'link') === 'nested' ? $item['children'] : [$item]
        ))
        ->filter(fn (array $item): bool => ($item['type'] ?? 'link') === 'link' && filled($item['url'] ?? null))
        ->values();

    $activeNavItem = $portalNav->firstWhere('active', true);
    $currentPageTitle = $activeNavItem['label'] ?? $title;
    $accountName = trim((string) ($portalUser?->name ?: 'Portal user'));
    $initials = static fn (string $name): string => collect(preg_split('/\s+/', trim($name)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('') ?: 'TT';
    $accountInitials = $initials($accountName);
    $memberName = trim((string) ($member->name ?: 'Think Tank'));
    $memberInitials = $initials($memberName);
    $memberLogoUrl = trim((string) ($member->logo_url ?? ''));
    $roleName = $isAdminPreview
        ? ($portalUser?->role?->name ?: 'Administrator preview')
        : ($portalUser && method_exists($portalUser, 'thinkTankAccessLabel')
            ? $portalUser->thinkTankAccessLabel()
            : 'Think Tank User');
    $branding = method_exists($member, 'resolvedPortalBranding')
        ? $member->resolvedPortalBranding()
        : \App\Models\ConsortiumThinkTank::DEFAULT_PORTAL_BRANDING;
    $preferences = $portalUser && method_exists($portalUser, 'resolvedThinkTankPortalPreferences')
        ? $portalUser->resolvedThinkTankPortalPreferences()
        : \App\Models\User::DEFAULT_THINK_TANK_PORTAL_PREFERENCES;
    $portalColour = $preferences['accent_color'] ?: $branding['primary_color'];
    $visibleWidgets = collect($preferences['dashboard_widgets']);
    $canManageBranding = ! $isAdminPreview
        && $portalUser?->resolvedThinkTankAccessLevel() === \App\Models\User::THINK_TANK_ACCESS_ADMIN
        && Illuminate\Support\Facades\Route::has('think-tank.branding.logo.update');
    $canManageTeam = $canAccessPortalArea('team')
        && Illuminate\Support\Facades\Route::has('think-tank.team-access');
@endphp


@section('content')
    <div class="tt-portal-shell think-tank-workspace"
         data-tt-portal-shell
         data-sidebar-mode="{{ $preferences['sidebar_mode'] }}"
         style="--tt-personal-accent: {{ $portalColour }}; --tt-org-brand: {{ $branding['primary_color'] }}; --tt-org-accent: {{ $branding['accent_color'] }};"
         data-visible-widgets='@json($visibleWidgets->values())'>
        <aside class="tt-sidebar" aria-label="Think Tank workspace sidebar" data-tt-institutional-background>
            <a class="tt-sidebar-brand" href="{{ route('think-tank.dashboard', $portalRouteParams) }}">
                <span class="tt-platform-logo" data-tt-attp-logo>
                    <img src="{{ asset('think-tank-portal/assets/images/attp-logo.svg') }}" alt="Africa Think Tank Platform">
                </span>
            </a>

            <div class="tt-sidebar-member" aria-label="Current Think Tank">
                <span class="tt-member-logo" data-tt-member-logo>
                    @if ($memberLogoUrl !== '')
                        <img src="{{ $memberLogoUrl }}" alt="{{ $memberName }} logo" data-tt-logo-source>
                    @else
                        <span aria-hidden="true">{{ $memberInitials }}</span>
                    @endif
                </span>
                <span class="tt-sidebar-member-copy">
                    <small>Current workspace</small>
                    <strong>{{ $memberName }}</strong>
                </span>
            </div>

            <div class="tt-sidebar-scroll">
                <span class="tt-sidebar-label">Navigation</span>
                <nav class="tt-sidebar-nav" data-think-tank-area-navigation aria-label="Think tank portal navigation">
                    @foreach ($portalGroups as $group)
                        <details class="tt-nav-group {{ $group['active'] ? 'is-active' : '' }}"
                                 data-tt-nav-group
                                 @if ($group['active']) open @endif>
                            <summary title="{{ $group['label'] }}">
                                <span class="tt-nav-group-icon"><i class="{{ $group['icon'] }}" aria-hidden="true"></i></span>
                                <span class="tt-nav-group-copy">
                                    <strong>{{ $group['label'] }}</strong>
                                    <small>{{ $group['caption'] }}</small>
                                </span>
                                <i class="feather-chevron-down tt-nav-chevron" aria-hidden="true"></i>
                            </summary>
                            <div class="tt-nav-submenu">
                                @foreach ($group['items'] as $navItem)
                                    @if (($navItem['type'] ?? 'link') === 'settings')
                                        <button class="tt-nav-submenu-link" type="button" data-tt-settings-open title="{{ $navItem['label'] }}">
                                            <i class="{{ $navItem['icon'] }}" aria-hidden="true"></i>
                                            <span><strong>{{ $navItem['label'] }}</strong><small>{{ $navItem['caption'] }}</small></span>
                                        </button>
                                    @elseif (($navItem['type'] ?? 'link') === 'nested')
                                        <div class="tt-nav-submenu-section {{ $navItem['active'] ? 'is-active' : '' }}">
                                            <div class="tt-nav-submenu-link is-section" title="{{ $navItem['label'] }}">
                                                <i class="{{ $navItem['icon'] }}" aria-hidden="true"></i>
                                                <span><strong>{{ $navItem['label'] }}</strong><small>{{ $navItem['caption'] }}</small></span>
                                            </div>
                                            <div class="tt-nav-tertiary" aria-label="{{ $navItem['label'] }}">
                                                @foreach ($navItem['children'] as $childItem)
                                                    <a class="tt-nav-tertiary-link {{ $childItem['active'] ? 'is-active' : '' }}"
                                                       href="{{ $childItem['url'] }}"
                                                       title="{{ $childItem['label'] }}"
                                                       @if ($childItem['active']) aria-current="page" @endif>
                                                        <i class="{{ $childItem['icon'] }}" aria-hidden="true"></i>
                                                        <span>{{ $childItem['label'] }}</span>
                                                    </a>
                                                @endforeach
                                            </div>
                                        </div>
                                    @else
                                        <a class="tt-nav-submenu-link {{ $navItem['active'] ? 'is-active' : '' }}"
                                           href="{{ $navItem['url'] }}"
                                           title="{{ $navItem['label'] }}"
                                           @if ($navItem['active']) aria-current="page" @endif>
                                            <i class="{{ $navItem['icon'] }}" aria-hidden="true"></i>
                                            <span><strong>{{ $navItem['label'] }}</strong><small>{{ $navItem['caption'] }}</small></span>
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                </nav>

                @if ($isAdminPreview && Route::has('dashboard'))
                    <div class="tt-sidebar-secondary">
                        <span class="tt-sidebar-label">Administration</span>
                        <a class="tt-sidebar-link" href="{{ route('dashboard') }}" title="Back to administration">
                            <i class="feather-corner-up-left" aria-hidden="true"></i>
                            <span class="tt-sidebar-link-copy"><strong>Administration</strong><small>Return to ATTP</small></span>
                        </a>
                    </div>
                @endif
            </div>

            <div class="tt-sidebar-foot">
                <span class="tt-program-mark">
                    <span data-tt-au-logo><img src="{{ asset('think-tank-portal/assets/images/african-union.svg') }}" alt="African Union"></span>
                    <span class="tt-program-mark-copy"><strong>African Think Tank Platform</strong><span>Secure partner workspace</span></span>
                </span>
            </div>
        </aside>

        <button class="tt-mobile-overlay" type="button" aria-label="Close navigation" data-tt-mobile-overlay></button>

        <div class="tt-shell-page">
            <header class="tt-shell-header" data-tt-institutional-background>
                <div class="tt-header-start">
                    <button class="tt-sidebar-toggle" type="button" aria-label="Toggle navigation" data-tt-sidebar-toggle>
                        <i class="feather-menu" aria-hidden="true"></i>
                    </button>
                    <div class="tt-header-search" role="search" data-tt-search>
                        <label class="tt-header-search-box" for="tt-workspace-search">
                            <i class="feather-search" aria-hidden="true"></i>
                            <input id="tt-workspace-search"
                                   type="search"
                                   placeholder="Search workspace..."
                                   autocomplete="off"
                                   aria-controls="tt-workspace-search-results"
                                   aria-expanded="false"
                                   data-tt-search-input>
                            <kbd aria-hidden="true">/</kbd>
                        </label>
                        <div class="tt-search-results" id="tt-workspace-search-results" hidden data-tt-search-results>
                            @foreach ($portalNav as $navItem)
                                <a class="tt-search-result"
                                   href="{{ $navItem['url'] }}"
                                   data-tt-search-item
                                   data-search-text="{{ Str::lower($navItem['label'].' '.$navItem['caption']) }}">
                                    <i class="{{ $navItem['icon'] }}" aria-hidden="true"></i>
                                    <span class="tt-search-result-copy"><strong>{{ $navItem['label'] }}</strong><small>{{ $navItem['caption'] }}</small></span>
                                </a>
                            @endforeach
                            <span class="tt-search-empty" hidden data-tt-search-empty>No workspace found.</span>
                        </div>
                    </div>
                    <div class="tt-page-heading">
                        <small class="tt-header-context">{{ $memberName }}</small>
                        <strong>{{ $currentPageTitle }}</strong>
                    </div>
                </div>

                <div class="tt-header-tools">
                    <span class="tt-header-date"><i class="feather-calendar" aria-hidden="true"></i>{{ now()->format('D, d M') }}</span>
                    <x-language-selector style="think-tank" />
                    <button class="tt-header-customize" type="button" data-tt-settings-open>
                        <i class="feather-sliders" aria-hidden="true"></i><span>Personalize</span>
                    </button>
                    <button class="tt-header-button" type="button" aria-label="Toggle light and dark mode" title="Toggle light and dark mode" data-tt-theme-toggle>
                        <i class="feather-moon" aria-hidden="true" data-tt-theme-icon></i>
                    </button>
                    <span class="tt-header-divider" aria-hidden="true"></span>
                    <details class="tt-account-menu" data-tt-account-menu>
                        <summary aria-label="Open account menu">
                            <span class="tt-account-avatar" aria-hidden="true">{{ $accountInitials }}</span>
                            <span class="tt-account-summary"><strong>{{ $accountName }}</strong><small>{{ $roleName }}</small></span>
                            <i class="feather-chevron-down" aria-hidden="true"></i>
                        </summary>
                        <div class="tt-account-panel">
                            <strong class="tt-account-panel-name">{{ $accountName }}</strong>
                            <span class="tt-account-panel-email">{{ $portalUser?->email }}</span>
                            <span class="tt-role-chip">{{ $roleName }}</span>
                            @if ($canManageTeam)
                                <a class="tt-account-link" href="{{ route('think-tank.team-access', $portalRouteParams) }}"><i class="feather-users"></i> Manage team access</a>
                            @endif
                            <button class="tt-account-link" type="button" data-tt-settings-open><i class="feather-sliders"></i> Workspace preferences</button>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="tt-logout-button" type="submit"><i class="feather-log-out"></i> Log out</button>
                            </form>
                        </div>
                    </details>
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
                        @if (session('success'))<div class="tt-shell-alert is-success" role="status"><i class="feather-check-circle"></i><span>{{ session('success') }}</span></div>@endif
                        @if (session('status'))<div class="tt-shell-alert is-status" role="status"><i class="feather-info"></i><span>{{ session('status') }}</span></div>@endif
                        @if (session('warning'))<div class="tt-shell-alert is-warning" role="alert"><i class="feather-alert-triangle"></i><span>{{ session('warning') }}</span></div>@endif
                        @if (session('error'))<div class="tt-shell-alert is-error" role="alert"><i class="feather-alert-circle"></i><span>{{ session('error') }}</span></div>@endif
                        @if (isset($errors) && $errors->any())<div class="tt-shell-alert is-error" role="alert"><i class="feather-alert-circle"></i><span>{{ $errors->first() }}</span></div>@endif
                    </div>
                @endif

                {{ $slot }}
            </main>

            <footer class="tt-shell-footer">
                <span><strong>{{ $memberName }}</strong> &middot; Think Tank workspace</span>
                <span>&copy; {{ now()->year }} African Think Tank Platform &middot; Powered by the African Union</span>
            </footer>
        </div>

        <button class="tt-drawer-backdrop" type="button" aria-label="Close customization panel" data-tt-settings-close></button>
        <aside class="tt-settings-drawer" aria-label="Workspace customization" aria-hidden="true" data-tt-settings-drawer>
            <div class="tt-settings-head">
                <div><h2>Customize workspace</h2><p>Make this portal feel right for the way you work.</p></div>
                <button class="tt-settings-close" type="button" aria-label="Close customization panel" data-tt-settings-close><i class="feather-x"></i></button>
            </div>

            <form method="POST" action="{{ route('think-tank.preferences.update', $portalRouteParams) }}" data-tt-preferences-form>
                @csrf
                @method('PUT')
                <div class="tt-settings-body">
                    <section class="tt-settings-section">
                        <h3>Appearance</h3>
                        <p>Choose how your personal portal looks on this account.</p>
                        <div class="tt-option-grid">
                            @foreach ([['system', 'feather-monitor', 'System'], ['light', 'feather-sun', 'Light'], ['dark', 'feather-moon', 'Dark']] as [$mode, $icon, $label])
                                <label class="tt-option-card">
                                    <input type="radio" name="theme_mode" value="{{ $mode }}" @checked($preferences['theme_mode'] === $mode) data-tt-theme-option>
                                    <span><i class="{{ $icon }}"></i>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </section>

                    <section class="tt-settings-section">
                        <h3>Accent colour</h3>
                        <p>Choose a personal identity accent. The portal's green interface remains consistent for every user.</p>
                        <div class="tt-colour-row">
                            @foreach (['#455a64', '#386c78', '#70566d', '#a65f46', '#a5763f', '#4a4a46'] as $swatch)
                                <button class="tt-colour-swatch {{ strtolower($portalColour) === $swatch ? 'is-active' : '' }}" type="button" style="--swatch: {{ $swatch }}" data-tt-colour="{{ $swatch }}" aria-label="Use {{ $swatch }}"></button>
                            @endforeach
                            <input class="tt-colour-input" type="color" name="accent_color" value="{{ $portalColour }}" aria-label="Custom accent colour" data-tt-colour-input>
                            @if ($memberLogoUrl !== '')
                                <button class="tt-logo-colour-button" type="button" data-tt-pick-logo-colour><i class="feather-aperture"></i> Use logo colour</button>
                            @endif
                        </div>
                    </section>

                    <section class="tt-settings-section">
                        <h3>Navigation size</h3>
                        <p>Keep labels visible or use a compact icon sidebar.</p>
                        <div class="tt-option-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                            @foreach ([['expanded', 'feather-sidebar', 'Comfortable'], ['compact', 'feather-columns', 'Compact']] as [$mode, $icon, $label])
                                <label class="tt-option-card">
                                    <input type="radio" name="sidebar_mode" value="{{ $mode }}" @checked($preferences['sidebar_mode'] === $mode) data-tt-sidebar-option>
                                    <span><i class="{{ $icon }}"></i>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </section>

                    <section class="tt-settings-section">
                        <h3>Dashboard sections</h3>
                        <p>Choose the supporting sections shown below your main work modules.</p>
                        <div class="tt-widget-options">
                            @foreach ([
                                'performance' => ['feather-trending-up', 'Performance update panel'],
                                'deadlines' => ['feather-calendar', 'Upcoming deadlines'],
                                'finance' => ['feather-credit-card', 'Finance at a glance'],
                                'filters' => ['feather-filter', 'Report period filters'],
                            ] as $widget => [$icon, $label])
                                <label class="tt-check-row"><input type="checkbox" name="dashboard_widgets[]" value="{{ $widget }}" @checked($visibleWidgets->contains($widget))><i class="{{ $icon }}"></i><span>{{ $label }}</span></label>
                            @endforeach
                        </div>
                    </section>

                    @if ($canManageBranding)
                        <section class="tt-settings-section">
                            <h3>Organization branding</h3>
                            <p>This logo and colour identity is shared with everyone in {{ $memberName }}.</p>
                            <div class="tt-brand-logo-preview">
                                <span class="tt-member-logo">
                                    @if ($memberLogoUrl !== '')<img src="{{ $memberLogoUrl }}" alt="{{ $memberName }} logo">@else<span>{{ $memberInitials }}</span>@endif
                                </span>
                                <div class="tt-file-field"><strong style="font-size:.73rem">Brand controls are below</strong><small>Only Think Tank administrators can change shared branding.</small></div>
                            </div>
                        </section>
                    @endif
                </div>
                <div class="tt-settings-actions"><button class="tt-settings-save" type="submit"><i class="feather-check me-1"></i> Save my preferences</button></div>
            </form>

            @if ($canManageBranding)
                <form class="tt-brand-form p-3 border-top" method="POST" action="{{ route('think-tank.branding.logo.update') }}" enctype="multipart/form-data" data-tt-branding-form>
                    @csrf
                    @method('PUT')
                    <div class="tt-brand-logo-preview">
                        <span class="tt-member-logo">
                            @if ($memberLogoUrl !== '')<img src="{{ $memberLogoUrl }}" alt="{{ $memberName }} logo" data-tt-brand-preview>@else<span data-tt-brand-preview>{{ $memberInitials }}</span>@endif
                        </span>
                        <div class="tt-file-field">
                            <label for="tt-organization-logo">Upload organization logo</label>
                            <input id="tt-organization-logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" data-tt-logo-input>
                            <small>PNG, JPEG or WebP, maximum 5 MB.</small>
                        </div>
                    </div>
                    <div class="tt-brand-colours">
                        <label class="tt-colour-field"><input type="color" name="primary_color" value="{{ $branding['primary_color'] }}"><span>Primary<small>Navigation identity</small></span></label>
                        <label class="tt-colour-field"><input type="color" name="accent_color" value="{{ $branding['accent_color'] }}"><span>Highlight<small>Badges & details</small></span></label>
                    </div>
                    <div class="tt-brand-actions">
                        <button class="tt-brand-save" type="submit"><i class="feather-save me-1"></i> Update organization brand</button>
                        @if ($memberLogoUrl !== '')<button class="tt-brand-remove" type="submit" name="remove_logo" value="1" formnovalidate><i class="feather-trash-2"></i></button>@endif
                    </div>
                </form>
            @endif
        </aside>
    </div>
@endsection
