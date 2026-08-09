<x-think-tank.partials.shell :member="$member" title="Evaluation Assignments">
    <div class="tea-page">
        <header class="tea-head">
            <div>
                <div class="tte-path"><span>Procurement</span><i class="feather-chevron-right"></i><strong>Assignments</strong></div>
                <h1>Evaluation assignments</h1>
                <p>Build evaluation teams by assigning active users from {{ $member->name }} to technical and financial evaluation templates.</p>
            </div>
            <a class="ppl-button is-secondary" href="{{ route('think-tank.evaluations.index', $portalRouteParams) }}"><i class="feather-clipboard"></i>My evaluations</a>
        </header>

        <section class="tea-summary" aria-label="Evaluation assignment summary">
            <article><span><i class="feather-layers"></i></span><div><small>Templates</small><strong>{{ number_format($stats['templates']) }}</strong><em>Available for assignment</em></div></article>
            <article><span><i class="feather-user-check"></i></span><div><small>Assignments</small><strong>{{ number_format($stats['assignments']) }}</strong><em>Team placements created</em></div></article>
            <article><span><i class="feather-users"></i></span><div><small>Evaluators</small><strong>{{ number_format($stats['evaluators']) }}</strong><em>Unique team members</em></div></article>
            <article class="{{ $stats['unassigned'] > 0 ? 'is-attention' : '' }}"><span><i class="feather-alert-circle"></i></span><div><small>Unassigned</small><strong>{{ number_format($stats['unassigned']) }}</strong><em>Templates needing a team</em></div></article>
        </section>

        <section class="tea-panel">
            <div class="tea-panel-head">
                <div><span class="ppl-section-label">Team allocation</span><h2>Templates and assigned evaluators</h2><p>Select one or more active users. Only accounts belonging to this Think Tank are available.</p></div>
                <div class="tea-template-links">
                    <a href="{{ route('think-tank.evaluation-templates.technical', $portalRouteParams) }}"><i class="feather-award"></i>Technical templates</a>
                    <a href="{{ route('think-tank.evaluation-templates.financial', $portalRouteParams) }}"><i class="feather-dollar-sign"></i>Financial templates</a>
                </div>
            </div>

            <form class="tea-filter" method="GET" action="{{ route('think-tank.evaluation-assignments.index', $portalRouteParams) }}">
                @foreach ($portalRouteParams as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
                <label><i class="feather-search"></i><input type="search" name="q" value="{{ $filters['keyword'] }}" placeholder="Search template, procurement or reference"></label>
                <select name="phase" aria-label="Evaluation phase">
                    <option value="">All evaluation phases</option>
                    <option value="technical" @selected($filters['phase'] === 'technical')>Technical evaluations</option>
                    <option value="financial" @selected($filters['phase'] === 'financial')>Financial evaluations</option>
                </select>
                <button type="submit"><i class="feather-filter"></i>Apply filters</button>
                @if ($filters['keyword'] !== '' || $filters['phase'] !== '')
                    <a href="{{ route('think-tank.evaluation-assignments.index', $portalRouteParams) }}" aria-label="Clear filters"><i class="feather-x"></i></a>
                @endif
            </form>

            <div class="tea-list">
                @forelse ($evaluations as $evaluation)
                    @php
                        $item = $evaluation->procurement?->thinkTankPlanningItem;
                        $plan = $item?->plan;
                        $assignedIds = $evaluation->assignments->pluck('user_id')->map(fn ($id) => (string) $id);
                        $availableMembers = $teamMembers->reject(fn ($teamMember) => $assignedIds->contains((string) $teamMember->id));
                        $criteriaCount = $evaluation->sections->sum(fn ($section) => $section->criteria->count());
                    @endphp
                    <article class="tea-card">
                        <header class="tea-card-head">
                            <span class="tea-phase-icon is-{{ $evaluation->evaluation_phase }}"><i class="{{ $evaluation->evaluation_phase === 'financial' ? 'feather-dollar-sign' : 'feather-award' }}"></i></span>
                            <div>
                                <span class="tea-phase">{{ Str::headline($evaluation->evaluation_phase ?: 'evaluation') }}</span>
                                <h3>{{ $evaluation->name }}</h3>
                                <p>{{ $evaluation->procurement?->title ?: 'Procurement opportunity' }}</p>
                            </div>
                            <span class="tea-assignment-count"><strong>{{ $evaluation->assignments->count() }}</strong><small>assigned</small></span>
                        </header>

                        <div class="tea-card-meta">
                            <span><i class="feather-hash"></i>{{ $evaluation->procurement?->reference_no ?: ($item?->item_code ?: 'No reference') }}</span>
                            <span><i class="feather-list"></i>{{ number_format($criteriaCount) }} {{ Str::plural('criterion', $criteriaCount) }}</span>
                            @if ($plan)<a href="{{ route('think-tank.procurement-plans.show', array_merge($portalRouteParams, ['plan' => $plan])) }}"><i class="feather-folder"></i>{{ str_starts_with((string) $plan->fiscal_year, 'FY') ? $plan->fiscal_year : 'FY '.$plan->fiscal_year }}</a>@endif
                        </div>

                        <div class="tea-team">
                            <span class="tea-block-label">Current evaluation team</span>
                            <div class="tea-team-list">
                                @forelse ($evaluation->assignments as $assignment)
                                    <span class="tea-person {{ $assignment->evaluator?->is_disabled ? 'is-disabled' : '' }}">
                                        <i>{{ collect(preg_split('/\s+/', trim((string) $assignment->evaluator?->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') ?: 'TT' }}</i>
                                        <span><strong>{{ $assignment->evaluator?->name ?: 'Unavailable user' }}</strong><small>{{ $assignment->evaluator?->email ?: Str::headline($assignment->status) }}</small></span>
                                    </span>
                                @empty
                                    <span class="tea-no-team"><i class="feather-user-x"></i>No evaluator has been assigned.</span>
                                @endforelse
                            </div>
                        </div>

                        <div class="tea-assign-box">
                            <div><strong>Assign team members</strong><p>New assignments receive access to applications and scoring forms.</p></div>
                            @if ($plan && $item && $availableMembers->isNotEmpty())
                                <form method="POST" action="{{ route('think-tank.procurement-plans.evaluations.assign', array_merge($portalRouteParams, ['plan' => $plan, 'item' => $item, 'evaluation' => $evaluation])) }}">
                                    @csrf
                                    <select name="evaluator_ids[]" multiple required aria-label="Select evaluation team members">
                                        @foreach ($availableMembers as $teamMember)
                                            <option value="{{ $teamMember->id }}">{{ $teamMember->name }} — {{ $teamMember->email }} ({{ $teamMember->thinkTankAccessLabel() }})</option>
                                        @endforeach
                                    </select>
                                    <button type="submit"><i class="feather-user-plus"></i>Assign selected users</button>
                                </form>
                            @elseif ($availableMembers->isEmpty())
                                <div class="tea-all-assigned"><i class="feather-check-circle"></i>All eligible active users are already assigned.</div>
                            @else
                                <div class="tea-all-assigned"><i class="feather-alert-circle"></i>The procurement item link is unavailable.</div>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="tea-empty">
                        <span><i class="feather-users"></i></span><h3>No evaluation templates found</h3>
                        <p>Create a technical or financial evaluation template before assigning team members.</p>
                        <div><a class="ppl-button is-primary" href="{{ route('think-tank.evaluation-templates.technical', $portalRouteParams) }}">Create technical template</a><a class="ppl-button is-secondary" href="{{ route('think-tank.evaluation-templates.financial', $portalRouteParams) }}">Create financial template</a></div>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-think-tank.partials.shell>
