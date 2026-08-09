<x-think-tank.partials.shell :member="$member" title="Create Procurement Plan">
    <div class="ppc-page">
        <a class="ppc-back" href="{{ route('think-tank.procurement-plans', $portalRouteParams) }}">
            <i class="feather-arrow-left" aria-hidden="true"></i> Back to annual plans
        </a>

        <header class="ppc-head">
            <div>
                <span class="ppl-section-label">Procurement planning</span>
                <h1>Create an annual procurement plan</h1>
                <p>Open a dedicated financial-year folder. After creation, you can add procurement items, TORs and every supporting document.</p>
            </div>
            <div class="ppc-stage" aria-label="Plan creation progress">
                <span class="is-current"><strong>1</strong><small>Plan details</small></span>
                <i aria-hidden="true"></i>
                <span><strong>2</strong><small>Add items</small></span>
                <i aria-hidden="true"></i>
                <span><strong>3</strong><small>Submit</small></span>
            </div>
        </header>

        <div class="ppc-layout">
            <section class="ppc-form-card" aria-labelledby="ppc-form-title">
                <header class="ppc-card-head">
                    <span class="ppc-card-icon"><i class="feather-folder-plus" aria-hidden="true"></i></span>
                    <div><h2 id="ppc-form-title">Plan information</h2><p>Fields marked required must be completed before the folder is created.</p></div>
                </header>

                <form class="ppc-form" method="POST" action="{{ route('think-tank.procurement-plans.store', $portalRouteParams) }}">
                    @csrf

                    @if ($errors->any())
                        <div class="ppc-errors" role="alert">
                            <i class="feather-alert-circle" aria-hidden="true"></i>
                            <div>
                                <strong>The plan could not be created</strong>
                                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                            </div>
                        </div>
                    @endif

                    <div class="ppc-field is-wide">
                        <label for="plan-title">Plan title <span>Required</span></label>
                        <input id="plan-title" name="title" value="{{ old('title') }}" placeholder="Annual Procurement Plan FY 2026/27" required autofocus>
                        <small>Use a clear title that identifies the organization and financial year.</small>
                    </div>

                    <div class="ppc-field">
                        <label for="plan-year">Financial year <span>Required</span></label>
                        <input id="plan-year" name="fiscal_year" value="{{ old('fiscal_year', now()->format('Y').'/'.now()->addYear()->format('y')) }}" placeholder="2026/27" required>
                        <small>Recommended format: 2026/27</small>
                    </div>

                    <div class="ppc-field">
                        <label for="plan-currency">Planning currency <span>Required</span></label>
                        <input id="plan-currency" name="currency" value="{{ old('currency', $currency) }}" maxlength="10" placeholder="USD" required>
                        <small>Use the three-letter currency code.</small>
                    </div>

                    <div class="ppc-field is-wide">
                        <label for="plan-description">Planning note <em>Optional</em></label>
                        <textarea id="plan-description" name="description" placeholder="Describe the plan scope, priorities, assumptions or important context">{{ old('description') }}</textarea>
                        <small>This note remains attached to the financial-year plan throughout review.</small>
                    </div>

                    <div class="ppc-form-actions">
                        <a class="ppl-button is-secondary" href="{{ route('think-tank.procurement-plans', $portalRouteParams) }}">Cancel</a>
                        <button class="ppl-button is-primary" type="submit"><i class="feather-folder-plus" aria-hidden="true"></i>Create plan and continue</button>
                    </div>
                </form>
            </section>

            <aside class="ppc-side">
                <section class="ppc-help-card">
                    <span class="ppc-side-icon"><i class="feather-check-circle" aria-hidden="true"></i></span>
                    <h2>Before you begin</h2>
                    <p>Prepare the information needed to build a complete annual plan.</p>
                    <ul>
                        <li><i class="feather-check"></i>Confirmed financial year</li>
                        <li><i class="feather-check"></i>Expected procurement activities</li>
                        <li><i class="feather-check"></i>Estimated costs and timelines</li>
                        <li><i class="feather-check"></i>TORs and supporting documents</li>
                    </ul>
                </section>

                <section class="ppc-existing-card">
                    <header><span class="ppl-section-label">Existing folders</span><h2>Recent annual plans</h2></header>
                    <div class="ppc-existing-list">
                        @forelse ($existingPlans as $plan)
                            <a href="{{ route('think-tank.procurement-plans.show', array_merge($portalRouteParams, ['plan' => $plan])) }}">
                                <span><i class="feather-folder" aria-hidden="true"></i></span>
                                <div><strong>{{ str_starts_with((string) $plan->fiscal_year, 'FY') ? $plan->fiscal_year : 'FY '.$plan->fiscal_year }}</strong><small>{{ number_format($plan->items_count) }} {{ Str::plural('item', $plan->items_count) }} · {{ Str::headline($plan->status) }}</small></div>
                                <i class="feather-chevron-right" aria-hidden="true"></i>
                            </a>
                        @empty
                            <div class="ppc-existing-empty"><i class="feather-folder"></i><span>No annual plan has been created yet.</span></div>
                        @endforelse
                    </div>
                </section>

                <div class="ppc-security-note"><i class="feather-shield" aria-hidden="true"></i><span><strong>Organization workspace</strong>This plan will only be visible to your Think Tank and authorized ATTP reviewers.</span></div>
            </aside>
        </div>
    </div>
</x-think-tank.partials.shell>
