@php
    $currency = $purchaseOrderRecords->first()?->resolved_currency ?? $member->consortium?->currency ?? 'USD';
    $isAdminView = auth()->user()?->isSuperAdmin() || auth()->user()?->isAdmin();
    $receiptRate = min(100, max(0, (float) ($receiptSummary['rate'] ?? 0)));
    $poPaymentRate = (float) ($metrics['po_allocated'] ?? 0) > 0
        ? min(100, ((float) ($metrics['disbursed'] ?? 0) / (float) $metrics['po_allocated']) * 100)
        : 0;
    $resetParams = $isAdminView ? ['think_tank_member_id' => $member->id] : [];
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
    $areaAccess = array_merge([
        'me' => false,
        'reports' => false,
        'finance' => false,
        'procurement_plans' => false,
        'team' => false,
    ], (array) ($portalAreaAccess ?? []));
    $accessLabel = auth()->user()?->isSuperAdmin() || auth()->user()?->isAdmin()
        ? 'Administrator preview'
        : (auth()->user() && method_exists(auth()->user(), 'thinkTankAccessLabel')
            ? auth()->user()->thinkTankAccessLabel()
            : 'Portal user');
    $deadlineItems = collect($upcomingActivities ?? [])->take(4);
    $hasActiveDashboardFilter = filled($dashboardFilter['month'] ?? null)
        || filled($dashboardFilter['year'] ?? null)
        || filled($dashboardFilter['date_from'] ?? null)
        || filled($dashboardFilter['date_to'] ?? null);
@endphp

@push('styles')
    <style>
        .think-tank-workspace > .page-header,
        .think-tank-workspace > .card.shadow-sm.border-0.overflow-hidden.mb-4 {
            display: none !important;
        }

        .tt-dashboard {
            display: grid;
            gap: 16px;
            color: #0f172a;
        }

        .tt-dashboard-intro {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 2px;
        }

        .tt-period-chip {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #475569;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 9px;
        }

        .tt-dashboard-intro h1 {
            color: #0f172a;
            font-size: clamp(1.55rem, 2.3vw, 2rem);
            font-weight: 850;
            line-height: 1.2;
            margin: 0;
        }

        .tt-dashboard-intro p {
            color: #64748b;
            font-size: 14px;
            margin: 6px 0 0;
            max-width: 720px;
        }

        .tt-intro-actions {
            display: flex;
            flex: 0 0 auto;
            flex-wrap: wrap;
            gap: 8px;
        }

        .tt-primary-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.55fr) minmax(300px, .7fr);
            gap: 16px;
            align-items: stretch;
        }

        .tt-primary-grid.is-single {
            grid-template-columns: 1fr;
        }

        .tt-me-focus,
        .tt-deadlines,
        .tt-kpi-card,
        .tt-disclosure {
            border: 1px solid #dbe4ef;
            border-radius: 10px;
            background: #ffffff;
        }

        .tt-me-focus {
            position: relative;
            overflow: hidden;
            padding: 22px;
        }

        .tt-me-focus::before {
            position: absolute;
            inset: 0 auto 0 0;
            width: 4px;
            background: #0f766e;
            content: '';
        }

        .tt-me-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
        }

        .tt-section-eyebrow {
            color: #0f766e;
            font-size: 11px;
            font-weight: 850;
            letter-spacing: .055em;
            text-transform: uppercase;
        }

        .tt-me-head h2,
        .tt-deadline-head h2,
        .tt-glance-head h2 {
            color: #0f172a;
            font-size: 19px;
            font-weight: 850;
            margin: 5px 0;
        }

        .tt-me-head p,
        .tt-deadline-head p,
        .tt-glance-head p {
            color: #64748b;
            font-size: 13px;
            margin: 0;
        }

        .tt-me-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 9px;
            margin-top: 18px;
        }

        .tt-me-stat {
            border-radius: 10px;
            background: #f8fafc;
            padding: 10px 12px;
        }

        .tt-me-stat strong {
            display: block;
            color: #0f172a;
            font-size: 18px;
            font-weight: 850;
        }

        .tt-me-stat span {
            color: #64748b;
            font-size: 12px;
            font-weight: 650;
        }

        .tt-update-list {
            display: grid;
            gap: 9px;
            margin-top: 14px;
        }

        .tt-update-card {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 16px;
            align-items: center;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 13px;
        }

        .tt-update-card.is-overdue {
            border-color: #fecaca;
            background: #fffafa;
        }

        .tt-update-title {
            color: #0f172a;
            font-size: 14px;
            font-weight: 800;
            margin: 0 0 4px;
        }

        .tt-update-code {
            color: #0f766e;
            font-size: 10px;
            font-weight: 850;
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        .tt-update-meta {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px 12px;
            color: #64748b;
            font-size: 12px;
        }

        .tt-update-meta span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .tt-update-progress {
            height: 5px;
            margin-top: 9px;
            overflow: hidden;
            border-radius: 999px;
            background: #e2e8f0;
        }

        .tt-update-progress span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: #0f766e;
        }

        .tt-deadlines {
            padding: 18px;
        }

        .tt-deadline-head {
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .tt-deadline-list {
            display: grid;
        }

        .tt-deadline-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            align-items: start;
            color: inherit;
            padding: 12px 0;
            text-decoration: none;
            border-bottom: 1px solid #edf2f7;
        }

        .tt-deadline-item:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .tt-deadline-item:hover .tt-deadline-title,
        .tt-deadline-item:focus .tt-deadline-title {
            color: #0f766e;
        }

        .tt-deadline-title {
            display: block;
            color: #1e293b;
            font-size: 13px;
            font-weight: 800;
            line-height: 1.35;
        }

        .tt-deadline-meta {
            display: block;
            color: #64748b;
            font-size: 11px;
            line-height: 1.45;
            margin-top: 3px;
        }

        .tt-deadline-value {
            color: #475569;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .tt-glance-head {
            margin: 2px 2px -5px;
        }

        .tt-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
        }

        .tt-kpi-card {
            min-width: 0;
            padding: 14px;
        }

        .tt-kpi-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            float: left;
            width: 34px;
            height: 34px;
            border-radius: 9px;
            color: #1d4ed8;
            background: #dbeafe;
            margin: 0 10px 0 0;
        }

        .tt-kpi-card.green .tt-kpi-icon { color: #166534; background: #dcfce7; }
        .tt-kpi-card.amber .tt-kpi-icon { color: #92400e; background: #fef3c7; }
        .tt-kpi-card.teal .tt-kpi-icon { color: #0f766e; background: #ccfbf1; }

        .tt-kpi-value {
            color: #0f172a;
            font-size: 17px;
            font-weight: 850;
            line-height: 1.2;
            overflow-wrap: anywhere;
        }

        .tt-kpi-label {
            color: #64748b;
            font-size: 11px;
            font-weight: 650;
            margin-top: 3px;
        }

        .tt-disclosure {
            overflow: hidden;
        }

        .tt-disclosure > summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            min-height: 58px;
            padding: 13px 16px;
            color: #0f172a;
            cursor: pointer;
            font-size: 14px;
            font-weight: 800;
            list-style: none;
        }

        .tt-disclosure > summary::-webkit-details-marker {
            display: none;
        }

        .tt-disclosure > summary::after {
            width: 9px;
            height: 9px;
            border-right: 2px solid #64748b;
            border-bottom: 2px solid #64748b;
            content: '';
            transform: rotate(45deg);
            transition: transform .2s ease;
        }

        .tt-disclosure[open] > summary::after {
            transform: rotate(225deg);
        }

        .tt-disclosure > summary:hover,
        .tt-disclosure > summary:focus-visible {
            background: #f8fafc;
        }

        .tt-summary-copy {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .tt-summary-copy > i {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 9px;
            color: #0f766e;
            background: #ecfdf5;
        }

        .tt-summary-copy small {
            display: block;
            color: #64748b;
            font-size: 11px;
            font-weight: 550;
            margin-top: 2px;
        }

        .tt-disclosure-content {
            border-top: 1px solid #e2e8f0;
            padding: 16px;
        }

        .tt-filter-grid {
            display: grid;
            grid-template-columns: minmax(220px, 1.25fr) repeat(4, minmax(135px, .7fr));
            gap: 11px;
            align-items: end;
        }

        .tt-field {
            display: grid;
            gap: 6px;
        }

        .tt-field label {
            color: #334155;
            font-size: 12px;
            font-weight: 750;
        }

        .tt-field input,
        .tt-field select {
            width: 100%;
            min-height: 40px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            color: #0f172a;
            background: #ffffff;
            padding: 8px 10px;
        }

        .tt-filter-actions {
            display: flex;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }

        .tt-empty-note {
            border-radius: 9px;
            color: #64748b;
            background: #f8fafc;
            font-size: 13px;
            margin-top: 14px;
            padding: 14px;
        }

        @media (max-width: 1199.98px) {
            .tt-primary-grid,
            .tt-filter-grid {
                grid-template-columns: 1fr;
            }

            .tt-kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .tt-dashboard-intro,
            .tt-me-head {
                align-items: flex-start;
                flex-direction: column;
            }

            .tt-intro-actions,
            .tt-intro-actions .btn,
            .tt-me-head .btn,
            .tt-update-card .btn {
                width: 100%;
            }

            .tt-me-summary,
            .tt-kpi-grid,
            .tt-update-card {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

<x-think-tank.partials.shell :member="$member" title="Think Tank Dashboard">
    <div class="tt-dashboard">
        <header class="tt-dashboard-intro" aria-labelledby="workspace-heading">
            <div>
                <span class="tt-period-chip">
                    <i class="feather-calendar" aria-hidden="true"></i>
                    {{ $dashboardFilter['label'] }}
                </span>
                <h1 id="workspace-heading">Your workspace</h1>
                <p>
                    Welcome back to {{ $member->name }}. You are signed in as {{ $accessLabel }}; use the work areas assigned to your role.
                </p>
            </div>
            <div class="tt-intro-actions">
                @if ($canViewMeUpdates)
                    <a class="btn btn-primary" href="{{ $meUpdatesUrl }}">
                        <i class="feather-edit-3 me-1" aria-hidden="true"></i> Performance updates
                    </a>
                @endif
                @if ($areaAccess['procurement_plans'] && ! $canViewMeUpdates && ! $areaAccess['finance'])
                    <a class="btn btn-primary" href="{{ route('think-tank.procurement-plans', $portalRouteParams) }}">
                        Open procurement plans
                    </a>
                @endif
                @can('think_tank.dashboard.download')
                    <a class="btn btn-light border" href="{{ route('think-tank.dashboard.download', $dashboardQueryParams) }}">
                        <i class="feather-download me-1" aria-hidden="true"></i> Download report
                    </a>
                @endcan
            </div>
        </header>

        @if ($canViewMeUpdates || $deadlineItems->isNotEmpty())
        <section class="tt-primary-grid {{ $canViewMeUpdates && $deadlineItems->isNotEmpty() ? '' : 'is-single' }}" aria-label="Priority work">
            @if ($canViewMeUpdates)
            <article class="tt-me-focus" aria-labelledby="performance-updates-heading">
                <div class="tt-me-head">
                    <div>
                        <div class="tt-section-eyebrow">Indicator performance updates</div>
                        <h2 id="performance-updates-heading">Periodic performance updates</h2>
                        <p>
                            Submit indicator results for the reporting periods assigned to your think tank. Save work as a draft and submit it when ready.
                        </p>
                    </div>
                    @if ($canViewMeUpdates)
                        <a class="btn btn-outline-primary btn-sm" href="{{ $meUpdatesUrl }}">
                            View all <i class="feather-arrow-right ms-1" aria-hidden="true"></i>
                        </a>
                    @endif
                </div>

                @if ($canViewMeUpdates)
                    <div class="tt-me-summary" aria-label="Performance update summary">
                        <div class="tt-me-stat">
                            <strong>{{ number_format($meSummary['action_required']) }}</strong>
                            <span>Need action</span>
                        </div>
                        <div class="tt-me-stat">
                            <strong>{{ number_format($meSummary['open']) }}</strong>
                            <span>Open now</span>
                        </div>
                        <div class="tt-me-stat">
                            <strong>{{ number_format($meSummary['submitted']) }}</strong>
                            <span>Submitted</span>
                        </div>
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
                                <article class="tt-update-card {{ $isOverdue ? 'is-overdue' : '' }}">
                                    <div>
                                        <span class="tt-update-code">{{ data_get($card, 'form_code', 'Performance update') }}</span>
                                        <h3 class="tt-update-title">{{ data_get($card, 'form_title', 'Indicator performance update') }}</h3>
                                        <div class="tt-update-meta">
                                            <span><i class="feather-calendar" aria-hidden="true"></i> {{ data_get($card, 'period_label', 'Reporting period') }}</span>
                                            @if ($dueAt)
                                                <span>
                                                    <i class="feather-flag" aria-hidden="true"></i>
                                                    {{ $isOverdue ? 'Overdue' : 'Due' }} {{ $dueAt->format('d M Y') }}
                                                </span>
                                            @endif
                                            <span>{{ $progressPercent }}% complete</span>
                                        </div>
                                        <div class="tt-update-progress" role="progressbar" aria-label="Form completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progressPercent }}">
                                            <span style="width: {{ $progressPercent }}%"></span>
                                        </div>
                                    </div>
                                    @if ($cardUrl)
                                        <a class="btn btn-sm {{ data_get($card, 'can_edit', false) && $canSubmitMeUpdates ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ $cardUrl }}">
                                            {{ data_get($card, 'action_label', 'View update') }}
                                        </a>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="tt-empty-note">
                            @if ((int) $meSummary['total'] === 0)
                                No periodic indicator update is assigned right now. New reporting periods will appear here when the M&amp;E team publishes them.
                            @else
                                No update needs immediate attention. Use <strong>View all</strong> to review upcoming and submitted periods.
                            @endif
                        </div>
                    @endif
                @endif
            </article>
            @endif

            @if ($deadlineItems->isNotEmpty())
            <aside class="tt-deadlines" aria-labelledby="deadlines-heading">
                <div class="tt-deadline-head">
                    <div class="tt-section-eyebrow">What needs attention</div>
                    <h2 id="deadlines-heading">Upcoming deadlines</h2>
                    <p>Your nearest reporting and operational tasks.</p>
                </div>
                <div class="tt-deadline-list">
                    @forelse ($deadlineItems as $item)
                        <a class="tt-deadline-item" href="{{ $item['route'] }}">
                            <span>
                                <span class="tt-deadline-title">{{ $item['title'] }}</span>
                                <span class="tt-deadline-meta">{{ $item['meta'] }}</span>
                            </span>
                            <span class="tt-deadline-value">{{ $item['value'] }}</span>
                        </a>
                    @empty
                        <div class="tt-empty-note mb-0">There are no upcoming deadlines.</div>
                    @endforelse
                </div>
            </aside>
            @endif
        </section>
        @endif

        @if ($areaAccess['finance'])
        <div class="tt-glance-head">
            <h2>Finance at a glance</h2>
            <p>A short summary for {{ $dashboardFilter['label'] }}. Use the downloadable report for the full operational detail.</p>
        </div>

        <section class="tt-kpi-grid" aria-label="Finance summary">
            <article class="tt-kpi-card">
                <span class="tt-kpi-icon"><i class="feather-credit-card" aria-hidden="true"></i></span>
                <div class="tt-kpi-value">{{ $currency }} {{ number_format((float) ($metrics['po_allocated'] ?? 0), 2) }}</div>
                <div class="tt-kpi-label">Funding committed</div>
            </article>
            <article class="tt-kpi-card green">
                <span class="tt-kpi-icon"><i class="feather-check-circle" aria-hidden="true"></i></span>
                <div class="tt-kpi-value">{{ $currency }} {{ number_format((float) ($metrics['disbursed'] ?? 0), 2) }}</div>
                <div class="tt-kpi-label">Payments recorded</div>
            </article>
            <article class="tt-kpi-card amber">
                <span class="tt-kpi-icon"><i class="feather-clock" aria-hidden="true"></i></span>
                <div class="tt-kpi-value">{{ $currency }} {{ number_format((float) ($metrics['po_unpaid'] ?? 0), 2) }}</div>
                <div class="tt-kpi-label">Remaining balance &middot; {{ number_format($poPaymentRate, 1) }}% paid</div>
            </article>
            <article class="tt-kpi-card teal">
                <span class="tt-kpi-icon"><i class="feather-file-text" aria-hidden="true"></i></span>
                <div class="tt-kpi-value">{{ $currency }} {{ number_format((float) ($receiptSummary['confirmed'] ?? 0), 2) }}</div>
                <div class="tt-kpi-label">Receipts confirmed &middot; {{ number_format($receiptRate, 1) }}%</div>
            </article>
        </section>

        <details class="tt-disclosure" @if ($hasActiveDashboardFilter) open @endif>
            <summary>
                <span class="tt-summary-copy">
                    <i class="feather-filter" aria-hidden="true"></i>
                    <span>
                        Filter report period
                        <small>Current selection: {{ $dashboardFilter['label'] }}</small>
                    </span>
                </span>
            </summary>
            <div class="tt-disclosure-content">
                <form method="GET" action="{{ route('think-tank.dashboard') }}">
                    <div class="tt-filter-grid">
                        <div class="tt-field">
                            <label for="think_tank_member_id">Think tank</label>
                            @if ($isAdminView)
                                <select id="think_tank_member_id" name="think_tank_member_id" required>
                                    @foreach ($membersForSearch as $searchMember)
                                        <option value="{{ $searchMember->id }}" @selected((string) $member->id === (string) $searchMember->id)>
                                            {{ $searchMember->name }}{{ $searchMember->consortium ? ' - '.$searchMember->consortium->name : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                <input id="think_tank_member_id" value="{{ $member->name }}" readonly>
                            @endif
                        </div>
                        <div class="tt-field">
                            <label for="filter_month">Month</label>
                            <input id="filter_month" type="month" name="filter_month" value="{{ $dashboardFilter['month'] }}">
                        </div>
                        <div class="tt-field">
                            <label for="filter_year">Year</label>
                            <select id="filter_year" name="filter_year">
                                <option value="">All years</option>
                                @foreach ($dashboardFilter['year_options'] as $year)
                                    <option value="{{ $year }}" @selected((string) $dashboardFilter['year'] === (string) $year)>{{ $year }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="tt-field">
                            <label for="date_from">From</label>
                            <input id="date_from" type="date" name="date_from" value="{{ $dashboardFilter['date_from'] }}">
                        </div>
                        <div class="tt-field">
                            <label for="date_to">To</label>
                            <input id="date_to" type="date" name="date_to" value="{{ $dashboardFilter['date_to'] }}">
                        </div>
                    </div>
                    <div class="tt-filter-actions">
                        <a class="btn btn-light border" href="{{ route('think-tank.dashboard', $resetParams) }}">Clear</a>
                        <button class="btn btn-primary" type="submit">
                            <i class="feather-check me-1" aria-hidden="true"></i> Apply filters
                        </button>
                    </div>
                </form>
            </div>
        </details>
        @endif
    </div>
</x-think-tank.partials.shell>
