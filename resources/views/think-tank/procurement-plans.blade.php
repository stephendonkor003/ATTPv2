@php
    $defaultCurrency = $member->consortium?->currency ?? 'USD';
    $planRows = $plans instanceof \Illuminate\Contracts\Pagination\Paginator
        ? collect($plans->items())
        : collect($plans);
@endphp

@push('styles')
    <style>
        .tt-plans-page {
            display: grid;
            gap: 1rem;
        }

        .tt-plans-intro,
        .tt-plan-stat,
        .tt-plan-form,
        .tt-plan-list {
            border: 1px solid var(--tt-border, #dfe8e3);
            border-radius: 10px;
            background: var(--tt-surface, #fff);
            box-shadow: none;
        }

        .tt-plans-intro {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0 0 1rem;
            border-width: 0 0 1px;
            border-radius: 0;
            background: transparent;
        }

        .tt-plans-eyebrow {
            color: var(--tt-brand, #176b4b);
            font-size: .72rem;
            font-weight: 850;
            letter-spacing: .055em;
            text-transform: uppercase;
        }

        .tt-plans-intro h1,
        .tt-plan-form h2,
        .tt-plan-list h2 {
            color: var(--tt-ink, #17241d);
            font-weight: 850;
        }

        .tt-plans-intro h1 {
            margin: .2rem 0 .3rem;
            font-size: clamp(1.45rem, 2vw, 1.9rem);
        }

        .tt-plans-intro p,
        .tt-panel-copy {
            margin: 0;
            color: var(--tt-muted, #607066);
            font-size: .86rem;
        }

        .tt-plan-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .8rem;
        }

        .tt-plan-stat {
            padding: 1rem;
        }

        .tt-plan-stat strong {
            display: block;
            color: var(--tt-ink, #17241d);
            font-size: 1.2rem;
            font-weight: 850;
        }

        .tt-plan-stat span {
            color: var(--tt-muted, #607066);
            font-size: .75rem;
        }

        .tt-plans-grid {
            display: grid;
            grid-template-columns: minmax(300px, .42fr) minmax(0, 1fr);
            gap: 1rem;
            align-items: start;
        }

        .tt-plan-form,
        .tt-plan-list {
            padding: 1.1rem;
        }

        .tt-plan-form h2,
        .tt-plan-list h2 {
            margin: 0 0 .2rem;
            font-size: 1rem;
        }

        .tt-plan-fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .75rem;
            margin-top: 1rem;
        }

        .tt-field {
            display: grid;
            gap: .32rem;
        }

        .tt-field.is-wide {
            grid-column: 1 / -1;
        }

        .tt-field label {
            color: #42544a;
            font-size: .72rem;
            font-weight: 800;
        }

        .tt-field input,
        .tt-field select,
        .tt-field textarea {
            width: 100%;
            min-height: 42px;
            border: 1px solid var(--tt-border-strong, #cbd9d1);
            border-radius: 10px;
            background: #fff;
            color: var(--tt-ink, #17241d);
            font: inherit;
            font-size: .8rem;
            padding: .62rem .7rem;
        }

        .tt-field textarea {
            min-height: 92px;
            resize: vertical;
        }

        .tt-form-help {
            margin: .75rem 0 0;
            padding: .7rem;
            border-radius: 10px;
            background: var(--tt-brand-soft, #e7f2ec);
            color: #385647;
            font-size: .72rem;
            line-height: 1.5;
        }

        .tt-plan-filters {
            display: grid;
            grid-template-columns: minmax(160px, 1fr) repeat(2, minmax(120px, .45fr)) auto;
            gap: .55rem;
            margin: 1rem 0;
            padding: .7rem;
            border-radius: 12px;
            background: #f7faf8;
        }

        .tt-plan-filters input,
        .tt-plan-filters select {
            min-height: 38px;
            border: 1px solid #d5e1da;
            border-radius: 9px;
            background: #fff;
            font-size: .76rem;
            padding: .48rem .6rem;
        }

        .tt-plan-card-list {
            display: grid;
            gap: .65rem;
        }

        .tt-plan-card {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 1rem;
            padding: .9rem;
            border: 1px solid #e3ebe6;
            border-radius: 12px;
            background: #fff;
        }

        .tt-plan-code {
            color: var(--tt-brand, #176b4b);
            font-size: .67rem;
            font-weight: 850;
            letter-spacing: .035em;
            text-transform: uppercase;
        }

        .tt-plan-title {
            margin: .16rem 0 .25rem;
            color: var(--tt-ink, #17241d);
            font-size: .9rem;
            font-weight: 850;
        }

        .tt-plan-meta {
            display: flex;
            flex-wrap: wrap;
            gap: .4rem .8rem;
            color: var(--tt-muted, #607066);
            font-size: .71rem;
        }

        .tt-plan-side {
            min-width: 130px;
            text-align: right;
        }

        .tt-plan-budget {
            display: block;
            color: var(--tt-ink, #17241d);
            font-size: .82rem;
            font-weight: 850;
        }

        .tt-plan-status {
            display: inline-flex;
            margin-top: .35rem;
            padding: .26rem .52rem;
            border-radius: 999px;
            background: #edf4f0;
            color: #416050;
            font-size: .66rem;
            font-weight: 800;
            text-transform: capitalize;
        }

        .tt-plans-empty {
            padding: 2rem 1rem;
            border: 1px dashed #cad9d1;
            border-radius: 12px;
            color: var(--tt-muted, #607066);
            text-align: center;
        }

        @media (max-width: 991.98px) {
            .tt-plan-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .tt-plans-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 767.98px) {
            .tt-plans-intro,
            .tt-plan-card {
                align-items: flex-start;
                grid-template-columns: 1fr;
            }

            .tt-plan-fields,
            .tt-plan-filters {
                grid-template-columns: 1fr;
            }

            .tt-plan-side {
                text-align: left;
            }
        }

        @media (max-width: 575.98px) {
            .tt-plans-intro {
                align-items: flex-start;
                flex-direction: column;
            }

            .tt-plan-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

<x-think-tank.partials.shell :member="$member" title="Procurement Plans">
    <div class="tt-plans-page">
        <header class="tt-plans-intro">
            <div>
                <div class="tt-plans-eyebrow">Procurement workspace</div>
                <h1>Procurement plans</h1>
                <p>Create and submit planned procurements for review. Opportunities, evaluations, and vendor selection are not managed here.</p>
            </div>
        </header>

        <section class="tt-plan-stats" aria-label="Procurement plan summary">
            <article class="tt-plan-stat">
                <strong>{{ number_format((int) data_get($planStats, 'total', $planRows->count())) }}</strong>
                <span>Total plans</span>
            </article>
            <article class="tt-plan-stat">
                <strong>{{ number_format((int) data_get($planStats, 'submitted', $planRows->where('status', 'submitted')->count())) }}</strong>
                <span>Submitted</span>
            </article>
            <article class="tt-plan-stat">
                <strong>{{ number_format((int) data_get($planStats, 'approved', $planRows->where('status', 'approved')->count())) }}</strong>
                <span>Approved</span>
            </article>
            <article class="tt-plan-stat">
                <strong>{{ $defaultCurrency }} {{ number_format((float) data_get($planStats, 'estimated_budget', data_get($planStats, 'budget', data_get($planStats, 'total_budget', $planRows->sum('estimated_budget')))), 2) }}</strong>
                <span>Planned value</span>
            </article>
        </section>

        <section class="tt-plans-grid">
            <div class="tt-plan-form">
                <h2>Submit a new plan</h2>
                <p class="tt-panel-copy">Add one clear procurement requirement at a time.</p>

                <form method="POST" action="{{ route('think-tank.procurement-plans.store', $portalRouteParams) }}">
                    @csrf
                    <div class="tt-plan-fields">
                        <div class="tt-field is-wide">
                            <label for="plan-title">Plan title <span class="text-danger">*</span></label>
                            <input id="plan-title" name="title" value="{{ old('title') }}" maxlength="255" placeholder="Research data collection services" required>
                        </div>
                        <div class="tt-field">
                            <label for="plan-fiscal-year">Fiscal year</label>
                            <input id="plan-fiscal-year" name="fiscal_year" value="{{ old('fiscal_year', now()->format('Y')) }}" maxlength="20" placeholder="{{ now()->format('Y') }}">
                        </div>
                        <div class="tt-field">
                            <label for="plan-date">Planned publication date</label>
                            <input id="plan-date" type="date" name="planned_publish_date" value="{{ old('planned_publish_date') }}">
                        </div>
                        <div class="tt-field">
                            <label for="plan-budget">Estimated budget <span class="text-danger">*</span></label>
                            <input id="plan-budget" type="number" name="estimated_budget" value="{{ old('estimated_budget') }}" min="0" step="0.01" placeholder="0.00" required>
                        </div>
                        <div class="tt-field">
                            <label for="plan-currency">Currency</label>
                            <input id="plan-currency" name="currency" value="{{ old('currency', $defaultCurrency) }}" maxlength="10">
                        </div>
                        <div class="tt-field is-wide">
                            <label for="plan-description">Description</label>
                            <textarea id="plan-description" name="description" placeholder="Describe what will be procured and why it is needed.">{{ old('description') }}</textarea>
                        </div>
                    </div>
                    <p class="tt-form-help">The Secretariat will review the submitted plan. Creating a plan does not publish a procurement opportunity.</p>
                    <button class="btn btn-success w-100 mt-3" type="submit">
                        <i class="feather-send me-1" aria-hidden="true"></i> Submit procurement plan
                    </button>
                </form>
            </div>

            <div class="tt-plan-list">
                <h2>Submitted plans</h2>
                <p class="tt-panel-copy">Plans belonging to {{ $member->name }} only.</p>

                <form class="tt-plan-filters" method="GET" action="{{ route('think-tank.procurement-plans', $portalRouteParams) }}">
                    <input name="q" value="{{ data_get($filters, 'keyword') }}" aria-label="Search plans" placeholder="Search title or code">
                    <select name="fiscal_year" aria-label="Filter by fiscal year">
                        <option value="">All years</option>
                        @foreach ($fiscalYears as $year)
                            <option value="{{ $year }}" @selected((string) data_get($filters, 'fiscalYear') === (string) $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                    <select name="status" aria-label="Filter by status">
                        <option value="">All statuses</option>
                        @foreach (['submitted', 'approved', 'revisions_requested', 'rejected'] as $status)
                            <option value="{{ $status }}" @selected(data_get($filters, 'status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-outline-success btn-sm" type="submit">Filter</button>
                </form>

                <div class="tt-plan-card-list">
                    @forelse ($plans as $plan)
                        <article class="tt-plan-card">
                            <div>
                                <div class="tt-plan-code">{{ $plan->plan_code ?: 'Plan' }}</div>
                                <h3 class="tt-plan-title">{{ $plan->title }}</h3>
                                <div class="tt-plan-meta">
                                    <span><i class="feather-calendar" aria-hidden="true"></i> {{ $plan->fiscal_year ?: 'Year not set' }}</span>
                                    <span><i class="feather-clock" aria-hidden="true"></i> {{ $plan->planned_publish_date?->format('d M Y') ?? 'Publication date not set' }}</span>
                                    <span>Submitted {{ $plan->created_at?->format('d M Y') }}</span>
                                </div>
                                @if ($plan->description)
                                    <p class="tt-panel-copy mt-2">{{ \Illuminate\Support\Str::limit($plan->description, 150) }}</p>
                                @endif
                            </div>
                            <div class="tt-plan-side">
                                <span class="tt-plan-budget">{{ $plan->currency ?: $defaultCurrency }} {{ number_format((float) $plan->estimated_budget, 2) }}</span>
                                <span class="tt-plan-status">{{ str_replace('_', ' ', $plan->status ?: 'submitted') }}</span>
                            </div>
                        </article>
                    @empty
                        <div class="tt-plans-empty">No procurement plan matches the selected filters.</div>
                    @endforelse
                </div>

                @if ($plans instanceof \Illuminate\Contracts\Pagination\Paginator && $plans->hasPages())
                    <div class="mt-3">{{ $plans->links() }}</div>
                @endif
            </div>
        </section>
    </div>
</x-think-tank.partials.shell>
