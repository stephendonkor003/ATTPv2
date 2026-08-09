@php
    $isTechnical = $phase === 'technical';
    $phaseLabel = $isTechnical ? 'Technical' : 'Financial';
    $indexRoute = $isTechnical ? 'think-tank.evaluation-templates.technical' : 'think-tank.evaluation-templates.financial';
    $storeRoute = $isTechnical ? 'think-tank.evaluation-templates.technical.store' : 'think-tank.evaluation-templates.financial.store';
    $defaultCriteria = $isTechnical
        ? [
            ['name' => 'Technical approach and methodology', 'description' => 'Assess the quality and suitability of the proposed approach.', 'max_score' => 60],
            ['name' => 'Relevant experience and personnel', 'description' => 'Assess relevant experience and the proposed team.', 'max_score' => 40],
        ]
        : [
            ['name' => 'Evaluated price', 'description' => 'Assess the evaluated financial offer.', 'max_score' => 70],
            ['name' => 'Cost realism and completeness', 'description' => 'Assess completeness, consistency and value for money.', 'max_score' => 30],
        ];
    $criteriaRows = old('criteria', $defaultCriteria);
@endphp

<x-think-tank.partials.shell :member="$member" :title="$phaseLabel.' Evaluation Templates'">
    <div class="tet-page">
        <header class="tet-head">
            <div>
                <div class="tte-path"><span>Procurement</span><i class="feather-chevron-right"></i><span>Templates</span><i class="feather-chevron-right"></i><strong>{{ $phaseLabel }}</strong></div>
                <h1>{{ $phaseLabel }} evaluation templates</h1>
                <p>Create reusable scoring criteria for a published procurement opportunity, then assign the evaluation team.</p>
            </div>
            <a class="ppl-button is-secondary" href="{{ route('think-tank.evaluation-assignments.index', $portalRouteParams) }}"><i class="feather-user-check"></i>Manage assignments</a>
        </header>

        <nav class="tet-phase-tabs" aria-label="Evaluation template type">
            <a class="{{ $isTechnical ? 'is-active' : '' }}" href="{{ route('think-tank.evaluation-templates.technical', $portalRouteParams) }}"><i class="feather-award"></i><span><strong>Technical evaluations</strong><small>Methodology, capacity and quality</small></span></a>
            <a class="{{ !$isTechnical ? 'is-active' : '' }}" href="{{ route('think-tank.evaluation-templates.financial', $portalRouteParams) }}"><i class="feather-dollar-sign"></i><span><strong>Financial evaluations</strong><small>Price, realism and value for money</small></span></a>
        </nav>

        <section class="tet-summary" aria-label="{{ $phaseLabel }} template summary">
            <article><small>Templates</small><strong>{{ number_format($stats['templates']) }}</strong><span>{{ $phaseLabel }} forms created</span></article>
            <article><small>Scoring criteria</small><strong>{{ number_format($stats['criteria']) }}</strong><span>Criteria across templates</span></article>
            <article><small>Team assignments</small><strong>{{ number_format($stats['assignments']) }}</strong><span>Evaluator placements</span></article>
            <article><small>Opportunities</small><strong>{{ number_format($stats['opportunities']) }}</strong><span>Procurements configured</span></article>
        </section>

        <div class="tet-layout">
            <section class="tet-library">
                <header class="tet-library-head">
                    <div><span class="ppl-section-label">Template library</span><h2>Existing {{ Str::lower($phaseLabel) }} templates</h2><p>Review the scoring structure and assignment readiness for each procurement.</p></div>
                    <span>{{ number_format($templates->count()) }} {{ Str::plural('template', $templates->count()) }}</span>
                </header>

                <form class="tet-search" method="GET" action="{{ route($indexRoute, $portalRouteParams) }}">
                    @foreach ($portalRouteParams as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
                    <label><i class="feather-search"></i><input name="q" type="search" value="{{ $keyword }}" placeholder="Search templates or procurement opportunities"></label>
                    <button type="submit">Search</button>
                    @if ($keyword !== '')<a href="{{ route($indexRoute, $portalRouteParams) }}" aria-label="Clear search"><i class="feather-x"></i></a>@endif
                </form>

                <div class="tet-list">
                    @forelse ($templates as $template)
                        @php
                            $item = $template->procurement?->thinkTankPlanningItem;
                            $plan = $item?->plan;
                            $criteria = $template->sections->flatMap->criteria;
                            $totalPoints = $criteria->sum('max_score');
                        @endphp
                        <article class="tet-card">
                            <header>
                                <span class="tet-card-icon"><i class="{{ $isTechnical ? 'feather-award' : 'feather-dollar-sign' }}"></i></span>
                                <div><span>{{ $item?->item_code ?: ($template->procurement?->reference_no ?: $phaseLabel.' evaluation') }}</span><h3>{{ $template->name }}</h3><p>{{ $template->procurement?->title ?: 'Procurement opportunity' }}</p></div>
                                <span class="tet-status">{{ Str::headline($template->status ?: 'active') }}</span>
                            </header>
                            @if ($template->description)<p class="tet-description">{{ $template->description }}</p>@endif
                            <div class="tet-criteria">
                                <div class="tet-criteria-head"><strong>Scoring criteria</strong><span>{{ number_format((float) $totalPoints, 0) }} total points</span></div>
                                @foreach ($criteria as $criterion)
                                    <div><span><i></i>{{ $criterion->name }}</span><strong>{{ number_format((float) $criterion->max_score, 0) }}</strong></div>
                                @endforeach
                            </div>
                            <footer>
                                <span><i class="feather-users"></i>{{ $template->assignments->count() }} assigned</span>
                                <span><i class="feather-list"></i>{{ $criteria->count() }} criteria</span>
                                @if ($plan)<a href="{{ route('think-tank.procurement-plans.show', array_merge($portalRouteParams, ['plan' => $plan])) }}"><i class="feather-folder"></i>Open plan</a>@endif
                                <a href="{{ route('think-tank.evaluation-assignments.index', array_merge($portalRouteParams, ['q' => $template->name])) }}"><i class="feather-user-plus"></i>Assign team</a>
                            </footer>
                        </article>
                    @empty
                        <div class="tet-empty"><span><i class="{{ $isTechnical ? 'feather-award' : 'feather-dollar-sign' }}"></i></span><h3>No {{ Str::lower($phaseLabel) }} templates yet</h3><p>Use the form to create the first scoring template for an eligible procurement opportunity.</p></div>
                    @endforelse
                </div>
            </section>

            <aside class="tet-builder" id="create-template">
                <header><span class="tet-builder-icon"><i class="feather-plus-square"></i></span><div><span class="ppl-section-label">New template</span><h2>Create {{ Str::lower($phaseLabel) }} evaluation</h2><p>Criteria must total exactly 100 points.</p></div></header>

                <form method="POST" action="{{ route($storeRoute, $portalRouteParams) }}" data-tet-criteria-form>
                    @csrf
                    @if ($errors->any())
                        <div class="tet-errors"><i class="feather-alert-circle"></i><div><strong>Check the template information</strong><span>{{ $errors->first() }}</span></div></div>
                    @endif

                    <label class="tet-field"><span>Procurement opportunity</span><select name="item_id" required><option value="">Select an eligible item</option>@foreach ($eligibleItems as $item)<option value="{{ $item->id }}" @selected(old('item_id') == $item->id)>{{ $item->item_code }} — {{ $item->title }} (FY {{ $item->plan?->fiscal_year }})</option>@endforeach</select><small>Only items with an execution opportunity are listed.</small></label>
                    <label class="tet-field"><span>Template name</span><input name="name" value="{{ old('name') }}" placeholder="{{ $phaseLabel }} evaluation template" required></label>
                    <label class="tet-field"><span>Instructions <em>Optional</em></span><textarea name="description" placeholder="Guidance for evaluation panel members">{{ old('description') }}</textarea></label>

                    <div class="tet-builder-section">
                        <div><span><strong>Scoring criteria</strong><small>Add criteria and allocate all 100 points.</small></span><span class="tet-total" data-tet-total>100 / 100</span></div>
                        <div class="tet-builder-rows" data-tet-rows>
                            @foreach ($criteriaRows as $index => $criterion)
                                <div class="tet-builder-row" data-tet-row>
                                    <input name="criteria[{{ $index }}][name]" value="{{ $criterion['name'] ?? '' }}" placeholder="Criterion name" required>
                                    <input name="criteria[{{ $index }}][description]" value="{{ $criterion['description'] ?? '' }}" placeholder="Scoring guidance">
                                    <label><input type="number" name="criteria[{{ $index }}][max_score]" value="{{ $criterion['max_score'] ?? '' }}" min=".01" max="100" step=".01" required data-tet-score><span>points</span></label>
                                    <button type="button" data-tet-remove aria-label="Remove criterion"><i class="feather-trash-2"></i></button>
                                </div>
                            @endforeach
                        </div>
                        <button class="tet-add-row" type="button" data-tet-add><i class="feather-plus"></i>Add criterion</button>
                    </div>

                    <button class="ppl-button is-primary tet-submit" type="submit" @disabled($eligibleItems->isEmpty())><i class="feather-save"></i>Create {{ Str::lower($phaseLabel) }} template</button>
                    @if ($eligibleItems->isEmpty())<p class="tet-no-items"><i class="feather-info"></i>Publish or configure a procurement opportunity before creating its evaluation template.</p>@endif
                </form>
            </aside>
        </div>
    </div>
</x-think-tank.partials.shell>
