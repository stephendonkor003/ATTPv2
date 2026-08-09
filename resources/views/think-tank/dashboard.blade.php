@php
    $currency = $purchaseOrderRecords->first()?->resolved_currency ?? $member->consortium?->currency ?? 'USD';
    $isAdminView = auth()->user()?->isSuperAdmin() || auth()->user()?->isAdmin();
    $preferences = auth()->user() && method_exists(auth()->user(), 'resolvedThinkTankPortalPreferences')
        ? auth()->user()->resolvedThinkTankPortalPreferences()
        : \App\Models\User::DEFAULT_THINK_TANK_PORTAL_PREFERENCES;
    $visibleWidgets = collect($preferences['dashboard_widgets']);
    $areaAccess = array_merge([
        'me' => false,
        'reports' => false,
        'finance' => false,
        'procurement_plans' => false,
        'team' => false,
    ], (array) ($portalAreaAccess ?? []));
    $meUpdates = $mePerformanceUpdates ?? [];
    $meSummary = array_merge([
        'total' => 0,
        'open' => 0,
        'upcoming' => 0,
        'submitted' => 0,
        'closed' => 0,
        'action_required' => 0,
    ], (array) data_get($meUpdates, 'summary', []));
    $mePriority = collect(data_get($meUpdates, 'priority', []));
    $canViewMeUpdates = (bool) data_get($meUpdates, 'can_view', false);
    $canSubmitMeUpdates = (bool) data_get($meUpdates, 'can_submit', false);
    $meUpdatesUrl = data_get($meUpdates, 'index_url') ?: route('think-tank.me-data.index', $portalRouteParams);
    $deadlineItems = collect($upcomingActivities ?? [])->take(5);
    $receiptRate = min(100, max(0, (float) ($receiptSummary['rate'] ?? 0)));
    $poPaymentRate = (float) ($metrics['po_allocated'] ?? 0) > 0
        ? min(100, ((float) ($metrics['disbursed'] ?? 0) / (float) $metrics['po_allocated']) * 100)
        : 0;
    $resetParams = $isAdminView ? ['think_tank_member_id' => $member->id] : [];
    $accessLabel = $isAdminView
        ? 'Administrator preview'
        : (auth()->user() && method_exists(auth()->user(), 'thinkTankAccessLabel')
            ? auth()->user()->thinkTankAccessLabel()
            : 'Portal user');
    $hasActiveDashboardFilter = filled($dashboardFilter['month'] ?? null)
        || filled($dashboardFilter['year'] ?? null)
        || filled($dashboardFilter['date_from'] ?? null)
        || filled($dashboardFilter['date_to'] ?? null);
    $showPerformance = $visibleWidgets->contains('performance') && $canViewMeUpdates;
    $showDeadlines = $visibleWidgets->contains('deadlines') && $deadlineItems->isNotEmpty();
    $showFinance = $visibleWidgets->contains('finance') && $areaAccess['finance'];
    $showFilters = $visibleWidgets->contains('filters') && $areaAccess['finance'];
    $firstName = trim(explode(' ', trim((string) auth()->user()?->name))[0] ?? 'there');
    $greeting = match (true) {
        now()->hour < 12 => 'Good morning',
        now()->hour < 17 => 'Good afternoon',
        default => 'Good evening',
    };
@endphp


<x-think-tank.partials.shell :member="$member" title="Think Tank Dashboard">
    <div class="tt-dashboard">
        <section class="tt-dashboard-hero" aria-labelledby="workspace-heading">
            <div class="tt-hero-copy">
                <span class="tt-hero-eyebrow"><i class="feather-calendar"></i>{{ $dashboardFilter['label'] }}</span>
                <h1 id="workspace-heading">{{ $greeting }}, {{ $firstName }}. Your workspace is ready.</h1>
                <p>Welcome back to {{ $member->name }}. Focus on the work assigned to you, track what needs attention, and move submissions forward from one clear dashboard.</p>
                <div class="tt-hero-actions">
                    @if ($areaAccess['procurement_plans'])
                        <a class="tt-hero-button is-primary" href="{{ route('think-tank.procurement-plans', $portalRouteParams) }}"><i class="feather-briefcase"></i> Open procurement</a>
                    @elseif ($canViewMeUpdates)
                        <a class="tt-hero-button is-primary" href="{{ $meUpdatesUrl }}"><i class="feather-activity"></i> Open M&amp;E workspace</a>
                    @elseif ($areaAccess['reports'])
                        <a class="tt-hero-button is-primary" href="{{ route('think-tank.report-uploads', $portalRouteParams) }}"><i class="feather-upload-cloud"></i> Submit report</a>
                    @endif
                    @can('think_tank.dashboard.download')
                        <a class="tt-hero-button" href="{{ route('think-tank.dashboard.download', $dashboardQueryParams) }}"><i class="feather-download"></i> Download report</a>
                    @endcan
                    <button class="tt-hero-button" type="button" data-tt-settings-open><i class="feather-sliders"></i> Customize dashboard</button>
                </div>
            </div>
            <div class="tt-hero-panel" aria-label="Workspace snapshot">
                <div class="tt-hero-stat"><strong>{{ number_format((int) ($metrics['procurement_plans'] ?? 0)) }}</strong><span>Procurement plans</span></div>
                <div class="tt-hero-stat"><strong>{{ number_format((int) $meSummary['action_required']) }}</strong><span>M&amp;E updates need action</span></div>
                <div class="tt-hero-stat"><strong>{{ number_format((int) ($metrics['reports'] ?? 0)) }}</strong><span>Reports submitted</span></div>
                <div class="tt-hero-stat"><strong>{{ number_format($deadlineItems->count()) }}</strong><span>Upcoming priorities</span></div>
            </div>
        </section>

        <div class="tt-dashboard-heading">
            <div><span class="tt-dashboard-kicker">Core workspaces</span><h2>Everything you need, in one place</h2><p>Your role controls which secure workspaces are available below.</p></div>
            <span class="tt-dashboard-period"><i class="feather-shield"></i>{{ $accessLabel }}</span>
        </div>

        @php
            $workspaceCount = collect([$areaAccess['procurement_plans'], $areaAccess['me'], $areaAccess['reports'], $areaAccess['finance']])->filter()->count();
        @endphp
        <section class="tt-workspace-grid {{ $workspaceCount === 4 ? 'has-four' : '' }}" aria-label="Think Tank workspaces">
            @if ($areaAccess['procurement_plans'])
                <a class="tt-workspace-card is-procurement" href="{{ route('think-tank.procurement-plans', $portalRouteParams) }}">
                    <span class="tt-workspace-card-top"><span class="tt-workspace-icon"><i class="feather-briefcase"></i></span><span class="tt-workspace-count">{{ number_format((int) ($metrics['procurement_plans'] ?? 0)) }} {{ Illuminate\Support\Str::plural('plan', (int) ($metrics['procurement_plans'] ?? 0)) }}</span></span>
                    <h3>Procurement</h3><p>Prepare annual plans, respond to review decisions, manage STEP clearance and launch approved opportunities.</p>
                    <span class="tt-workspace-open">Enter workspace <i class="feather-arrow-right"></i></span>
                </a>
            @endif
            @if ($areaAccess['me'])
                <a class="tt-workspace-card is-me" href="{{ $meUpdatesUrl }}">
                    <span class="tt-workspace-card-top"><span class="tt-workspace-icon"><i class="feather-activity"></i></span><span class="tt-workspace-count">{{ number_format((int) $meSummary['action_required']) }} need action</span></span>
                    <h3>Monitoring &amp; Evaluation</h3><p>Complete indicator updates, manage reporting periods and keep performance evidence current.</p>
                    <span class="tt-workspace-open">Enter workspace <i class="feather-arrow-right"></i></span>
                </a>
            @endif
            @if ($areaAccess['reports'])
                <a class="tt-workspace-card is-reporting" href="{{ route('think-tank.report-uploads', $portalRouteParams) }}">
                    <span class="tt-workspace-card-top"><span class="tt-workspace-icon"><i class="feather-bar-chart-2"></i></span><span class="tt-workspace-count">{{ number_format((int) ($metrics['reports'] ?? 0)) }} {{ Illuminate\Support\Str::plural('report', (int) ($metrics['reports'] ?? 0)) }}</span></span>
                    <h3>Reporting &amp; Dashboard</h3><p>Submit activity reports and supporting evidence, then review the latest delivery picture.</p>
                    <span class="tt-workspace-open">Enter workspace <i class="feather-arrow-right"></i></span>
                </a>
            @endif
            @if ($areaAccess['finance'])
                <a class="tt-workspace-card is-finance" href="{{ route('think-tank.finance', $portalRouteParams) }}">
                    <span class="tt-workspace-card-top"><span class="tt-workspace-icon"><i class="feather-credit-card"></i></span><span class="tt-workspace-count">{{ number_format($poPaymentRate, 0) }}% paid</span></span>
                    <h3>Finance workspace</h3><p>Review committed funding, purchase orders, recorded payments and outstanding balances.</p>
                    <span class="tt-workspace-open">Enter workspace <i class="feather-arrow-right"></i></span>
                </a>
            @endif
        </section>

        @if ($showPerformance || $showDeadlines)
            <section class="tt-priority-layout {{ $showPerformance && $showDeadlines ? '' : 'is-single' }}" aria-label="Priority work">
                @if ($showPerformance)
                    <article class="tt-panel">
                        <div class="tt-panel-head">
                            <div><span class="tt-dashboard-kicker">Indicator performance updates</span><h2>Periodic performance updates</h2><p>Continue your current reporting-period assignments and submit them when ready.</p></div>
                            <a class="tt-panel-action" href="{{ $meUpdatesUrl }}">View all <i class="feather-arrow-right"></i></a>
                        </div>
                        <div class="tt-performance-summary">
                            <div class="tt-performance-stat"><strong>{{ number_format($meSummary['action_required']) }}</strong><span>Need action</span></div>
                            <div class="tt-performance-stat"><strong>{{ number_format($meSummary['open']) }}</strong><span>Open now</span></div>
                            <div class="tt-performance-stat"><strong>{{ number_format($meSummary['submitted']) }}</strong><span>Submitted</span></div>
                        </div>
                        @if ($mePriority->isNotEmpty())
                            <div class="tt-update-list">
                                @foreach ($mePriority as $card)
                                    @php
                                        $progress = (array) data_get($card, 'progress', []);
                                        $progressPercent = min(100, max(0, (int) ($progress['percent'] ?? 0)));
                                        $isOverdue = (bool) data_get($card, 'is_overdue', false);
                                        $dueAt = data_get($card, 'due_at');
                                        $cardUrl = data_get($card, 'url');
                                    @endphp
                                    <article class="tt-update-row {{ $isOverdue ? 'is-overdue' : '' }}">
                                        <div>
                                            <span class="tt-update-code">{{ data_get($card, 'form_code', 'Performance update') }}</span>
                                            <h3 class="tt-update-title">{{ data_get($card, 'form_title', 'Indicator performance update') }}</h3>
                                            <div class="tt-update-meta"><span><i class="feather-calendar"></i>{{ data_get($card, 'period_label', 'Reporting period') }}</span>@if ($dueAt)<span><i class="feather-flag"></i>{{ $isOverdue ? 'Overdue' : 'Due' }} {{ $dueAt->format('d M Y') }}</span>@endif<span>{{ $progressPercent }}% complete</span></div>
                                            <div class="tt-update-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progressPercent }}"><span style="width: {{ $progressPercent }}%"></span></div>
                                        </div>
                                        @if ($cardUrl)<a class="btn btn-sm {{ data_get($card, 'can_edit', false) && $canSubmitMeUpdates ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ $cardUrl }}">{{ data_get($card, 'action_label', 'View update') }}</a>@endif
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <div class="tt-empty-state"><i class="feather-check-circle"></i>{{ (int) $meSummary['total'] === 0 ? 'No periodic indicator update is assigned right now. New reporting periods will appear here when published.' : 'Nothing needs immediate attention. Open the M&E workspace to review upcoming and submitted periods.' }}</div>
                        @endif
                    </article>
                @endif

                @if ($showDeadlines)
                    <aside class="tt-panel">
                        <div class="tt-panel-head"><div><span class="tt-dashboard-kicker">Your agenda</span><h2>Upcoming deadlines</h2><p>The nearest tasks across your workspaces.</p></div></div>
                        <div class="tt-deadline-list">
                            @foreach ($deadlineItems as $item)
                                <a class="tt-deadline-item" href="{{ $item['route'] }}">
                                    <span class="tt-deadline-icon"><i class="feather-calendar"></i></span>
                                    <span><span class="tt-deadline-title">{{ $item['title'] }}</span><span class="tt-deadline-meta">{{ $item['meta'] }}</span><span class="tt-deadline-value">{{ $item['value'] }}</span></span>
                                </a>
                            @endforeach
                        </div>
                    </aside>
                @endif
            </section>
        @endif

        @if ($showFinance)
            <div class="tt-dashboard-heading">
                <div><span class="tt-dashboard-kicker">Financial position</span><h2>Finance at a glance</h2><p>A clean summary for {{ $dashboardFilter['label'] }}. Open Finance for transaction-level detail.</p></div>
                <a class="tt-panel-action" href="{{ route('think-tank.finance', $portalRouteParams) }}">Open finance <i class="feather-arrow-right"></i></a>
            </div>
            <section class="tt-finance-grid" aria-label="Finance summary">
                <article class="tt-finance-card"><div class="tt-finance-card-top"><span class="tt-finance-icon"><i class="feather-briefcase"></i></span><small>Committed</small></div><div class="tt-finance-value">{{ $currency }} {{ number_format((float) ($metrics['po_allocated'] ?? 0), 2) }}</div><div class="tt-finance-caption">Funding committed through purchase orders</div></article>
                <article class="tt-finance-card"><div class="tt-finance-card-top"><span class="tt-finance-icon"><i class="feather-check-circle"></i></span><small>{{ number_format($poPaymentRate, 1) }}% paid</small></div><div class="tt-finance-value">{{ $currency }} {{ number_format((float) ($metrics['disbursed'] ?? 0), 2) }}</div><div class="tt-finance-caption">Payments recorded to date</div></article>
                <article class="tt-finance-card"><div class="tt-finance-card-top"><span class="tt-finance-icon"><i class="feather-clock"></i></span><small>Outstanding</small></div><div class="tt-finance-value">{{ $currency }} {{ number_format((float) ($metrics['po_unpaid'] ?? 0), 2) }}</div><div class="tt-finance-caption">Remaining purchase-order balance</div></article>
                <article class="tt-finance-card"><div class="tt-finance-card-top"><span class="tt-finance-icon"><i class="feather-file-text"></i></span><small>{{ number_format($receiptRate, 1) }}% confirmed</small></div><div class="tt-finance-value">{{ $currency }} {{ number_format((float) ($receiptSummary['confirmed'] ?? 0), 2) }}</div><div class="tt-finance-caption">Receipts confirmed in the portal</div></article>
            </section>
        @endif

        @if ($showFilters)
            <details class="tt-panel tt-filter-panel" @if ($hasActiveDashboardFilter) open @endif>
                <summary><span class="tt-filter-summary"><span class="tt-filter-icon"><i class="feather-filter"></i></span><span><strong>Filter report period</strong><small>Current selection: {{ $dashboardFilter['label'] }}</small></span></span><i class="feather-chevron-down tt-filter-chevron"></i></summary>
                <div class="tt-filter-content">
                    <form method="GET" action="{{ route('think-tank.dashboard') }}">
                        <div class="tt-filter-grid">
                            <div class="tt-field"><label for="think_tank_member_id">Think tank</label>@if ($isAdminView)<select id="think_tank_member_id" name="think_tank_member_id" required>@foreach ($membersForSearch as $searchMember)<option value="{{ $searchMember->id }}" @selected((string) $member->id === (string) $searchMember->id)>{{ $searchMember->name }}{{ $searchMember->consortium ? ' - '.$searchMember->consortium->name : '' }}</option>@endforeach</select>@else<input id="think_tank_member_id" value="{{ $member->name }}" readonly>@endif</div>
                            <div class="tt-field"><label for="filter_month">Month</label><input id="filter_month" type="month" name="filter_month" value="{{ $dashboardFilter['month'] }}"></div>
                            <div class="tt-field"><label for="filter_year">Year</label><select id="filter_year" name="filter_year"><option value="">All years</option>@foreach ($dashboardFilter['year_options'] as $year)<option value="{{ $year }}" @selected((string) $dashboardFilter['year'] === (string) $year)>{{ $year }}</option>@endforeach</select></div>
                            <div class="tt-field"><label for="date_from">From</label><input id="date_from" type="date" name="date_from" value="{{ $dashboardFilter['date_from'] }}"></div>
                            <div class="tt-field"><label for="date_to">To</label><input id="date_to" type="date" name="date_to" value="{{ $dashboardFilter['date_to'] }}"></div>
                        </div>
                        <div class="tt-filter-actions"><a class="btn btn-light border" href="{{ route('think-tank.dashboard', $resetParams) }}">Clear</a><button class="btn btn-primary" type="submit"><i class="feather-check me-1"></i>Apply filters</button></div>
                    </form>
                </div>
            </details>
        @endif
    </div>
</x-think-tank.partials.shell>
