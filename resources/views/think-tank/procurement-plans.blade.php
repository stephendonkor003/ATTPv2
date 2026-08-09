@php
    $currency = strtoupper($member->consortium?->currency ?: 'USD');
    $statusLabels = [
        'draft' => 'Draft',
        'submitted' => 'Under ATTP review',
        'revision_requested' => 'Action required',
        'rejected' => 'Rejected',
        'approved' => 'Approved',
    ];
    $hasFilters = filled($filters['keyword']) || filled($filters['fiscalYear']) || filled($filters['status']);
    $canManagePlans = auth()->user()?->can('think_tank.procurement_plans.manage');
    $portfolioProgress = $stats['items'] > 0
        ? min(100, (int) round(($stats['no_objection'] / $stats['items']) * 100))
        : 0;
@endphp

<x-think-tank.partials.shell :member="$member" title="Procurement Plans">
    <div class="ppl-page" @if ($canManagePlans) data-ppl-create-page-url="{{ route('think-tank.procurement-plans.create', $portalRouteParams) }}" @endif>
        <header class="ppl-page-head">
            <div class="ppl-page-copy">
                <div class="ppl-path" aria-label="Current location">
                    <span>Procurement</span><i class="feather-chevron-right" aria-hidden="true"></i><strong>Annual plans</strong>
                </div>
                <h1>Procurement plan library</h1>
                <p>Create, organize and track every financial-year procurement plan from preparation through approval.</p>
            </div>
            <div class="ppl-page-actions">
                @if (Route::has('think-tank.evaluations.index') && auth()->user()?->can('evaluations.evaluate'))
                    <a class="ppl-button is-secondary" href="{{ route('think-tank.evaluations.index', $portalRouteParams) }}">
                        <i class="feather-clipboard" aria-hidden="true"></i> Evaluation assignments
                    </a>
                @endif
                @if ($canManagePlans)
                    <a class="ppl-button is-primary" href="{{ route('think-tank.procurement-plans.create', $portalRouteParams) }}">
                        <i class="feather-plus" aria-hidden="true"></i> Create annual plan
                    </a>
                @endif
            </div>
        </header>

        <section class="ppl-summary" aria-label="Procurement portfolio summary">
            <article class="ppl-summary-card">
                <span class="ppl-summary-icon"><i class="feather-folder" aria-hidden="true"></i></span>
                <div><small>Annual plans</small><strong>{{ number_format($stats['plans']) }}</strong><span>Financial-year folders</span></div>
            </article>
            <article class="ppl-summary-card">
                <span class="ppl-summary-icon"><i class="feather-list" aria-hidden="true"></i></span>
                <div><small>Procurement items</small><strong>{{ number_format($stats['items']) }}</strong><span>Across all plans</span></div>
            </article>
            <article class="ppl-summary-card">
                <span class="ppl-summary-icon"><i class="feather-credit-card" aria-hidden="true"></i></span>
                <div><small>Total planned value</small><strong class="is-currency">{{ $currency }} {{ number_format($stats['budget'], 0) }}</strong><span>Current portfolio estimate</span></div>
            </article>
            <article class="ppl-summary-card {{ $stats['action_required'] > 0 ? 'is-attention' : '' }}">
                <span class="ppl-summary-icon"><i class="feather-alert-circle" aria-hidden="true"></i></span>
                <div><small>Action required</small><strong>{{ number_format($stats['action_required']) }}</strong><span>Returned or rejected items</span></div>
            </article>
        </section>

        <div class="ppl-content-grid">
            <section class="ppl-library" aria-labelledby="ppl-library-title">
                <div class="ppl-library-head">
                    <div>
                        <span class="ppl-section-label">Plan folders</span>
                        <h2 id="ppl-library-title">Financial-year plans</h2>
                        <p>Select a folder to manage items, TORs, supporting documents and submissions.</p>
                    </div>
                    <span class="ppl-count">{{ number_format($plans->total()) }} {{ Str::plural('plan', $plans->total()) }}</span>
                </div>

                <form class="ppl-filter" method="GET" action="{{ route('think-tank.procurement-plans', $portalRouteParams) }}">
                    @foreach ($portalRouteParams as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach

                    <label class="ppl-search">
                        <span class="visually-hidden">Search procurement plans</span>
                        <i class="feather-search" aria-hidden="true"></i>
                        <input type="search" name="q" value="{{ $filters['keyword'] }}" placeholder="Search by plan title or code">
                    </label>
                    <label class="ppl-select">
                        <span class="visually-hidden">Financial year</span>
                        <select name="fiscal_year">
                            <option value="">All financial years</option>
                            @foreach ($fiscalYears as $year)
                                <option value="{{ $year }}" @selected($filters['fiscalYear'] === $year)>FY {{ $year }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="ppl-select">
                        <span class="visually-hidden">Plan status</span>
                        <select name="status">
                            <option value="">All statuses</option>
                            @foreach ($statusLabels as $value => $label)
                                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="ppl-filter-button" type="submit"><i class="feather-filter" aria-hidden="true"></i><span>Apply</span></button>
                    @if ($hasFilters)
                        <a class="ppl-clear" href="{{ route('think-tank.procurement-plans', $portalRouteParams) }}" title="Clear filters" aria-label="Clear filters"><i class="feather-x"></i></a>
                    @endif
                </form>

                <div class="ppl-folder-grid" aria-label="Annual procurement plans">
                    @forelse ($plans as $plan)
                        @php
                            $progress = $plan->items_count
                                ? min(100, (int) round(($plan->approved_items_count / $plan->items_count) * 100))
                                : 0;
                            $fiscalYearLabel = str_starts_with((string) $plan->fiscal_year, 'FY')
                                ? $plan->fiscal_year
                                : 'FY '.$plan->fiscal_year;
                            $statusLabel = $statusLabels[$plan->status] ?? Str::headline($plan->status);
                        @endphp
                        <a class="ppl-folder" href="{{ route('think-tank.procurement-plans.show', array_merge($portalRouteParams, ['plan' => $plan])) }}">
                            <div class="ppl-folder-top">
                                <span class="ppl-folder-symbol"><i class="feather-folder" aria-hidden="true"></i></span>
                                <div class="ppl-folder-year"><small>Financial year</small><strong>{{ $fiscalYearLabel }}</strong></div>
                                <span class="ppl-status is-{{ $plan->status }}">{{ $statusLabel }}</span>
                            </div>

                            <div class="ppl-folder-body">
                                <span class="ppl-plan-code">{{ $plan->plan_code ?: 'Annual procurement plan' }}</span>
                                <h3>{{ $plan->title }}</h3>
                                <div class="ppl-folder-facts">
                                    <div><small>Items</small><strong>{{ number_format($plan->items_count) }}</strong></div>
                                    <div><small>Planned value</small><strong>{{ strtoupper($plan->currency ?: $currency) }} {{ number_format((float) $plan->estimated_budget, 0) }}</strong></div>
                                    <div><small>Version</small><strong>{{ $plan->version }}</strong></div>
                                </div>

                                <div class="ppl-progress-head"><span>Approval progress</span><strong>{{ $progress }}%</strong></div>
                                <div class="ppl-progress" role="progressbar" aria-label="Approval progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress }}">
                                    <span style="width: {{ $progress }}%"></span>
                                </div>

                                @if ($plan->action_items_count)
                                    <div class="ppl-folder-alert"><i class="feather-alert-triangle" aria-hidden="true"></i>{{ $plan->action_items_count }} {{ Str::plural('item', $plan->action_items_count) }} need attention</div>
                                @endif
                            </div>

                            <div class="ppl-folder-foot">
                                <span><i class="feather-clock" aria-hidden="true"></i> Updated {{ $plan->updated_at?->format('d M Y, H:i') }}</span>
                                <strong>Open folder <i class="feather-arrow-up-right" aria-hidden="true"></i></strong>
                            </div>
                        </a>
                    @empty
                        <div class="ppl-empty">
                            <span class="ppl-empty-icon"><i class="feather-folder" aria-hidden="true"></i></span>
                            <h3>{{ $hasFilters ? 'No plans match your filters' : 'Create your first procurement plan' }}</h3>
                            <p>{{ $hasFilters ? 'Adjust the financial year, status or search term and try again.' : 'Start a financial-year folder, then add procurement items and all required documents.' }}</p>
                            @if ($hasFilters)
                                <a class="ppl-button is-secondary" href="{{ route('think-tank.procurement-plans', $portalRouteParams) }}">Clear filters</a>
                            @elseif ($canManagePlans)
                                <a class="ppl-button is-primary" href="{{ route('think-tank.procurement-plans.create', $portalRouteParams) }}"><i class="feather-plus"></i>Create annual plan</a>
                            @endif
                        </div>
                    @endforelse
                </div>

                @if ($plans->hasPages())
                    <div class="ppl-pagination">{{ $plans->withQueryString()->links() }}</div>
                @endif
            </section>

            <aside class="ppl-insights" aria-label="Procurement planning guidance">
                <section class="ppl-health-card">
                    <div class="ppl-insight-head">
                        <span class="ppl-section-label">Portfolio health</span>
                        <h2>Clearance progress</h2>
                    </div>
                    <div class="ppl-health-body">
                        <div class="ppl-health-ring" style="--ppl-progress: {{ $portfolioProgress * 3.6 }}deg">
                            <div><strong>{{ $portfolioProgress }}%</strong><span>cleared</span></div>
                        </div>
                        <div class="ppl-health-copy">
                            <strong>{{ number_format($stats['no_objection']) }} cleared items</strong>
                            <p>Items with World Bank no-objection or already published.</p>
                        </div>
                    </div>
                    <div class="ppl-health-row"><span>Total portfolio items</span><strong>{{ number_format($stats['items']) }}</strong></div>
                    <div class="ppl-health-row"><span>Requiring action</span><strong>{{ number_format($stats['action_required']) }}</strong></div>
                </section>

                <section class="ppl-guide-card">
                    <div class="ppl-insight-head">
                        <span class="ppl-section-label">Planning workflow</span>
                        <h2>From plan to execution</h2>
                    </div>
                    <ol class="ppl-guide-list">
                        <li><span>1</span><div><strong>Prepare the plan</strong><p>Create the folder and add every intended procurement item.</p></div></li>
                        <li><span>2</span><div><strong>Complete documents</strong><p>Attach TORs and supporting documents before submission.</p></div></li>
                        <li><span>3</span><div><strong>Submit for review</strong><p>Follow ATTP decisions and respond to requested revisions.</p></div></li>
                        <li><span>4</span><div><strong>Proceed after clearance</strong><p>Begin execution after STEP and World Bank no-objection.</p></div></li>
                    </ol>
                </section>
            </aside>
        </div>
    </div>

</x-think-tank.partials.shell>
