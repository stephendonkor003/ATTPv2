@extends('layouts.app')
@section('title', 'Think Tank Procurement Oversight')
@include('think-tank-procurement-admin._styles')

@section('content')
@php
    $hasFilters = collect($filters)->filter(fn ($value) => filled($value))->isNotEmpty();
    $displayYears = filled($filters['fiscal_year'])
        ? collect([(string) $filters['fiscal_year']])
        : $fiscalYears;
    $yearLink = static fn ($year = null) => route('think-tank-procurement.index', array_filter([
        'fiscal_year' => $year,
        'think_tank_member_id' => $filters['think_tank_member_id'],
        'status' => $filters['status'],
        'q' => $filters['q'],
    ], fn ($value) => filled($value)));
    $reviewQueueLink = route('think-tank-procurement.index', array_filter([
        'fiscal_year' => $filters['fiscal_year'],
        'think_tank_member_id' => $filters['think_tank_member_id'],
        'status' => 'submitted',
        'q' => $filters['q'],
    ], fn ($value) => filled($value)));
@endphp

<div class="nxl-container">
    <div class="atp">
        <section class="atp-hero atp-index-hero">
            <div class="atp-index-hero-copy">
                <div class="atp-kicker">ATTP administration &middot; Procurement officer workspace</div>
                <h1>Think Tank procurement oversight</h1>
                <p>Review annual procurement plans, supporting documents, item decisions, World Bank clearance and the complete audit history from one organized workspace.</p>
                <div class="atp-index-hero-meta">
                    <span><i class="feather-shield"></i> Permission controlled</span>
                    <span><i class="feather-clock"></i> Full audit history</span>
                    <span><i class="feather-layers"></i> {{ number_format($stats['plans']) }} plans in view</span>
                </div>
            </div>
            <aside class="atp-index-queue" aria-label="Current procurement review queue">
                <div class="atp-index-queue-head"><span><i class="feather-inbox"></i></span><small>Current review queue</small></div>
                <div class="atp-index-queue-value"><strong>{{ number_format($stats['submitted']) }}</strong><span>annual {{ Str::plural('plan', $stats['submitted']) }} awaiting review</span></div>
                <div class="atp-index-queue-actions">
                    <a href="{{ $reviewQueueLink }}">Open review queue <i class="feather-arrow-right"></i></a>
                    <a href="{{ route('think-tank-procurement.reports') }}">View reports <i class="feather-bar-chart-2"></i></a>
                </div>
            </aside>
            <div class="atp-actions atp-index-hero-actions">
                <a class="atp-btn light" href="#procurement-library"><i class="feather-folder"></i> Browse records</a>
                <a class="atp-btn gold" href="{{ route('think-tank-procurement.reports') }}"><i class="feather-bar-chart-2"></i> Procurement reports</a>
            </div>
        </section>

        <section class="atp-metrics atp-index-metrics" aria-label="Procurement portfolio summary">
            <article class="atp-metric atp-index-metric">
                <span class="atp-index-metric-icon green"><i class="feather-briefcase"></i></span>
                <div><small>Organizations</small><strong>{{ number_format($stats['folders']) }}</strong><p>Think Tanks represented</p></div>
            </article>
            <article class="atp-metric atp-index-metric">
                <span class="atp-index-metric-icon blue"><i class="feather-file-text"></i></span>
                <div><small>Annual plans</small><strong>{{ number_format($stats['plans']) }}</strong><p>{{ number_format($stats['draft_plans']) }} draft &middot; {{ number_format($stats['approved_plans']) }} approved</p></div>
            </article>
            <article class="atp-metric atp-index-metric {{ $stats['submitted'] ? 'attention' : '' }}">
                <span class="atp-index-metric-icon amber"><i class="feather-inbox"></i></span>
                <div><small>Review queue</small><strong>{{ number_format($stats['submitted']) }}</strong><p>{{ number_format($stats['plan_action_required']) }} returned for action</p></div>
            </article>
            <article class="atp-metric atp-index-metric">
                <span class="atp-index-metric-icon violet"><i class="feather-list"></i></span>
                <div><small>Procurement items</small><strong>{{ number_format($stats['items']) }}</strong><p>{{ number_format($stats['action_required']) }} require correction</p></div>
            </article>
            <article class="atp-metric atp-index-metric">
                <span class="atp-index-metric-icon teal"><i class="feather-check-circle"></i></span>
                <div><small>STEP-ready</small><strong>{{ number_format($stats['step_ready']) }}</strong><p>{{ number_format($stats['no_objection']) }} cleared or published</p></div>
            </article>
        </section>

        <section class="atp-filter-shell" aria-labelledby="folder-filter-title">
            <div class="atp-filter-title">
                <div>
                    <span class="atp-section-kicker">Folder filters</span>
                    <h2 id="folder-filter-title">Find a procurement folder</h2>
                    <p>Narrow the portfolio by organization, financial year or current plan status.</p>
                </div>
                @if($hasFilters)
                    <a class="atp-clear-link" href="{{ route('think-tank-procurement.index') }}"><i class="feather-x"></i> Clear all filters</a>
                @endif
            </div>

            <nav class="atp-year-tabs" aria-label="Filter by financial year">
                <a class="{{ blank($filters['fiscal_year']) ? 'active' : '' }}" href="{{ $yearLink() }}">
                    All years <span>{{ number_format($yearCounts->sum()) }}</span>
                </a>
                @foreach($fiscalYears as $year)
                    <a class="{{ (string) $filters['fiscal_year'] === (string) $year ? 'active' : '' }}" href="{{ $yearLink($year) }}">
                        FY {{ $year }} <span>{{ number_format((int) $yearCounts->get((string) $year, 0)) }}</span>
                    </a>
                @endforeach
            </nav>

            <form class="atp-filter" method="GET" action="{{ route('think-tank-procurement.index') }}">
                <div>
                    <label for="folder-search">Search</label>
                    <div class="atp-search-control"><i class="feather-search"></i><input id="folder-search" name="q" value="{{ $filters['q'] }}" placeholder="Think Tank, plan title or code"></div>
                </div>
                <div>
                    <label for="folder-member">Think Tank</label>
                    <select id="folder-member" name="think_tank_member_id">
                        <option value="">All Think Tanks</option>
                        @foreach($members as $member)
                            <option value="{{ $member->id }}" @selected($filters['think_tank_member_id'] === $member->id)>{{ $member->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="folder-year">Financial year</label>
                    <select id="folder-year" name="fiscal_year">
                        <option value="">All years</option>
                        @foreach($fiscalYears as $year)
                            <option value="{{ $year }}" @selected((string) $filters['fiscal_year'] === (string) $year)>FY {{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="folder-status">Plan status</label>
                    <select id="folder-status" name="status">
                        <option value="">All statuses</option>
                        @foreach(['draft', 'submitted', 'revision_requested', 'rejected', 'approved'] as $status)
                            <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ Str::headline($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="atp-btn primary" type="submit"><i class="feather-filter"></i> Apply filters</button>
            </form>

            @if($hasFilters)
                <div class="atp-active-filter-bar">
                    <span><i class="feather-sliders"></i> Active view</span>
                    @if($filters['q'])<strong>Search: “{{ $filters['q'] }}”</strong>@endif
                    @if($filters['think_tank_member_id'])<strong>{{ $members->firstWhere('id', $filters['think_tank_member_id'])?->name ?: 'Selected Think Tank' }}</strong>@endif
                    @if($filters['fiscal_year'])<strong>FY {{ $filters['fiscal_year'] }}</strong>@endif
                    @if($filters['status'])<strong>{{ Str::headline($filters['status']) }}</strong>@endif
                </div>
            @endif
        </section>

        <div id="procurement-library" class="atp-library-head atp-index-library-head">
            <div>
                <span class="atp-section-kicker">Procurement records</span>
                <h2>{{ $folders->count() }} Think Tank {{ Str::plural('record', $folders->count()) }}</h2>
                <p>Open a financial year or annual plan to review items, documents, decisions and audit activity.</p>
            </div>
            <div class="atp-library-total"><span class="atp-live-dot"></span><strong>{{ number_format($stats['items']) }}</strong><span>items in this view</span></div>
        </div>

        <section class="atp-folder-grid atp-index-folder-grid" aria-label="Think Tank procurement records">
            @forelse($folders as $folderPlans)
                @php
                    $folderPlans = $folderPlans->sortByDesc('fiscal_year')->values();
                    $firstPlan = $folderPlans->first();
                    $member = $firstPlan->member;
                    $folderItems = (int) $folderPlans->sum('items_count');
                    $folderApproved = (int) $folderPlans->sum('approved_items_count');
                    $folderAction = (int) $folderPlans->sum('action_items_count');
                    $folderNoObjection = (int) $folderPlans->sum('no_objection_items_count');
                    $folderValue = (float) $folderPlans->sum('estimated_budget');
                    $folderCurrencies = $folderPlans->pluck('currency')->filter()->unique()->values();
                    $folderCurrency = $folderCurrencies->count() === 1 ? $folderCurrencies->first() : 'Mixed currencies';
                    $folderPendingPlans = $folderPlans->where('status', 'submitted')->count();
                    $folderReturnedPlans = $folderPlans->whereIn('status', ['revision_requested', 'rejected'])->count();
                    $folderProgress = $folderItems ? min(100, (int) round(($folderApproved / $folderItems) * 100)) : 0;
                    $latestUpdatedPlan = $folderPlans->sortByDesc('updated_at')->first();
                    $initials = collect(preg_split('/\s+/', trim((string) $member?->name)) ?: [])
                        ->filter()->take(2)->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->implode('') ?: 'TT';
                @endphp

                <article class="atp-folder-card atp-index-folder {{ $folderPendingPlans ? 'awaiting-review' : '' }} {{ $folderReturnedPlans ? 'requires-action' : '' }}">
                    <span class="atp-folder-tab" aria-hidden="true"></span>
                    <div class="atp-folder-cover">
                        <div class="atp-folder-toolbar">
                            <span><i class="feather-archive"></i> Organization record</span>
                            @if($folderPendingPlans)
                                <strong class="review"><i class="feather-clock"></i> {{ $folderPendingPlans }} awaiting review</strong>
                            @elseif($folderReturnedPlans)
                                <strong class="action"><i class="feather-alert-circle"></i> {{ $folderReturnedPlans }} need action</strong>
                            @else
                                <strong><i class="feather-check"></i> Up to date</strong>
                            @endif
                        </div>
                        <div class="atp-folder-identity">
                            <span class="atp-folder-avatar">{{ $initials }}</span>
                            <div class="atp-folder-identity-copy">
                                <h3>{{ $member?->name ?: 'Unnamed Think Tank' }}</h3>
                                <p><span><i class="feather-map-pin"></i> {{ $member?->country ?: 'Country not set' }}</span><span><i class="feather-users"></i> {{ $firstPlan->consortium?->name ?: 'Consortium not set' }}</span></p>
                            </div>
                        </div>

                        <div class="atp-folder-overview">
                            <div class="atp-folder-primary-count">
                                <strong>{{ number_format($folderItems) }}</strong>
                                <span>procurement {{ Str::plural('item', $folderItems) }}<small>across {{ $folderPlans->count() }} annual {{ Str::plural('plan', $folderPlans->count()) }}</small></span>
                            </div>
                            <a class="atp-folder-open" href="{{ route('think-tank-procurement.show', $firstPlan) }}" aria-label="Open latest plan for {{ $member?->name }}"><span>Open latest</span><i class="feather-arrow-up-right"></i></a>
                        </div>

                        <div class="atp-folder-progress">
                            <div><span>Item approval progress</span><strong>{{ $folderApproved }} of {{ $folderItems }}</strong></div>
                            <div class="atp-folder-progress-track"><span style="width: {{ $folderProgress }}%"></span></div>
                        </div>

                        <div class="atp-folder-year-label"><span>Financial year coverage</span><small>Select a year to open its plan</small></div>
                        <div class="atp-folder-years" aria-label="Procurement items by financial year">
                            @foreach($displayYears as $year)
                                @php
                                    $yearPlans = $folderPlans->where('fiscal_year', (string) $year);
                                    $yearItemCount = (int) $yearPlans->sum('items_count');
                                    $yearPlan = $yearPlans->first();
                                    $yearTag = $yearPlan ? 'a' : 'span';
                                @endphp
                                <{{ $yearTag }} class="atp-folder-year {{ $yearPlan ? 'has-plan' : 'is-empty' }}" @if($yearPlan) href="{{ route('think-tank-procurement.show', $yearPlan) }}" @endif>
                                    <strong>FY {{ $year }}</strong>
                                    <span><b>{{ number_format($yearItemCount) }}</b> procurement {{ Str::plural('item', $yearItemCount) }}</span>
                                    @if($yearPlan)<i class="feather-arrow-right"></i>@endif
                                </{{ $yearTag }}>
                            @endforeach
                        </div>

                        <div class="atp-folder-stats">
                            <span><i class="feather-file-text"></i><strong>{{ $folderPlans->count() }}</strong> annual {{ Str::plural('plan', $folderPlans->count()) }}</span>
                            <span><i class="feather-check-circle"></i><strong>{{ $folderApproved }}</strong> approved</span>
                            <span class="{{ $folderAction ? 'needs-action' : '' }}"><i class="feather-alert-triangle"></i><strong>{{ $folderAction }}</strong> need action</span>
                            <span><i class="feather-globe"></i><strong>{{ $folderNoObjection }}</strong> cleared</span>
                        </div>
                    </div>

                    <div class="atp-folder-plans">
                        <div class="atp-folder-plan-heading"><span>Annual plan records</span><small>{{ $folderPlans->count() }} total</small></div>
                        @foreach($folderPlans as $plan)
                            <a class="atp-folder-plan" href="{{ route('think-tank-procurement.show', $plan) }}">
                                <span class="atp-folder-plan-icon"><i class="feather-file-text"></i></span>
                                <span class="atp-folder-plan-copy">
                                    <strong>FY {{ $plan->fiscal_year }} &middot; {{ $plan->title }}</strong>
                                    <small>{{ $plan->plan_code }} &middot; v{{ $plan->version }} &middot; {{ $plan->items_count }} {{ Str::plural('item', $plan->items_count) }}</small>
                                </span>
                                <span class="atp-folder-plan-side">
                                    <span class="atp-status {{ $plan->status }}">{{ $plan->status === 'submitted' ? 'Under review' : Str::headline($plan->status) }}</span>
                                    <i class="feather-chevron-right"></i>
                                </span>
                            </a>
                        @endforeach
                    </div>

                    <footer class="atp-folder-footer">
                        <div><span>Recorded value</span><strong>{{ $folderCurrency }} {{ number_format($folderValue, 2) }}</strong></div>
                        <small>Updated {{ $latestUpdatedPlan?->updated_at?->diffForHumans() ?: 'recently' }}</small>
                    </footer>
                </article>
            @empty
                <div class="atp-panel atp-empty atp-folder-empty">
                    <span class="atp-empty-icon"><i class="feather-folder-minus"></i></span>
                    <h3>No procurement records found</h3>
                    <p>Try another financial year, status, Think Tank or search term.</p>
                    @if($hasFilters)<a class="atp-btn" href="{{ route('think-tank-procurement.index') }}">Show all folders</a>@endif
                </div>
            @endforelse
        </section>

        <p class="text-muted fs-11 mb-0">* Values are displayed as recorded. Mixed currencies are not converted in the folder totals.</p>
    </div>
</div>
@endsection
