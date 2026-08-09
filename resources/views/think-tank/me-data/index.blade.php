@php
    $stateRank = ['open' => 0, 'upcoming' => 1, 'submitted' => 2, 'closed' => 3];
    $indicatorRows = collect();

    foreach ($assignmentGroups as $state => $cards) {
        foreach ($cards as $card) {
            $assignment = $card['assignment'] ?? null;
            $linkedForm = $assignment?->collection?->form;
            $fallbackField = collect($linkedForm?->fields ?? [])->first(
                fn ($field): bool => filled($field->indicator_id ?? null)
            );
            $indicatorId = $card['indicator_id'] ?? $fallbackField?->indicator_id;
            $indicatorName = trim((string) ($card['indicator_name'] ?? $fallbackField?->label ?? $card['form_title']));
            $indicatorCode = trim((string) ($card['indicator_code'] ?? $card['form_code']));
            $indicatorUnit = trim((string) ($card['indicator_unit'] ?? $fallbackField?->unit_label ?? ''));
            $isLinkedIndicator = filled($indicatorId);

            $indicatorRows->push(array_merge($card, [
                'indicator_id' => $indicatorId,
                'indicator_name' => $indicatorName !== '' ? $indicatorName : 'Assigned indicator',
                'indicator_code' => $indicatorCode,
                'indicator_unit' => $indicatorUnit,
                'is_linked_indicator' => $isLinkedIndicator,
                'state_rank' => $stateRank[$state] ?? 9,
            ]));
        }
    }

    $indicatorRows = $indicatorRows
        ->sortBy([
            ['state_rank', 'asc'],
            ['indicator_name', 'asc'],
        ])
        ->values();
    $linkedIndicatorCount = $indicatorRows
        ->where('is_linked_indicator', true)
        ->pluck('indicator_id')
        ->filter()
        ->unique()
        ->count();
    $actionRequiredCount = $indicatorRows->where('state', 'open')->count();
@endphp


<x-think-tank.partials.shell :member="$member" title="Assigned M&E Indicators">
    <div class="tt-indicator-workspace" data-me-indicator-workspace>
        <header class="me-heading">
            <div>
                <div class="me-eyebrow">M&amp;E data reporting</div>
                <h1>Assigned indicators</h1>
                <p>Select an indicator to open its linked reporting template. Save work as a draft and submit it when the reporting information is complete.</p>
            </div>
            <div class="me-assigned-count" aria-label="{{ $linkedIndicatorCount }} linked indicators">
                <strong>{{ $linkedIndicatorCount }}</strong>
                linked {{ \Illuminate\Support\Str::plural('indicator', $linkedIndicatorCount) }}
            </div>
        </header>

        <section class="me-overview-bar" aria-label="Indicator reporting overview">
            <div class="me-overview-item">
                <i class="feather-list" aria-hidden="true"></i>
                <span><strong>{{ $indicatorRows->count() }}</strong> Assigned records</span>
            </div>
            <div class="me-overview-item">
                <i class="feather-edit-3" aria-hidden="true"></i>
                <span><strong>{{ $actionRequiredCount }}</strong> Require action</span>
            </div>
            <div class="me-overview-item">
                <i class="feather-check-circle" aria-hidden="true"></i>
                <span><strong>{{ $summary['submitted'] }}</strong> Submitted</span>
            </div>
        </section>

        @if ($indicatorRows->isNotEmpty())
            <div class="me-tools">
                <div class="me-filters" role="group" aria-label="Filter assigned indicators">
                    <button class="me-filter" type="button" data-me-filter="all" aria-pressed="true">All</button>
                    <button class="me-filter" type="button" data-me-filter="open" aria-pressed="false">Requires action</button>
                    <button class="me-filter" type="button" data-me-filter="upcoming" aria-pressed="false">Upcoming</button>
                    <button class="me-filter" type="button" data-me-filter="submitted" aria-pressed="false">Submitted</button>
                    <button class="me-filter" type="button" data-me-filter="closed" aria-pressed="false">Closed</button>
                </div>
                <label class="me-search">
                    <span class="visually-hidden">Search assigned indicators</span>
                    <i class="feather-search" aria-hidden="true"></i>
                    <input type="search" placeholder="Search indicators or forms" data-me-search autocomplete="off">
                </label>
            </div>

            <section aria-labelledby="indicator-register-heading">
                <div class="me-register-head">
                    <h2 id="indicator-register-heading">Indicator register</h2>
                    <span class="me-results-status" data-me-results-status aria-live="polite"></span>
                </div>
                <div class="me-indicator-list" data-me-indicator-list>
                    @foreach ($indicatorRows as $row)
                        @php
                            $stateLabel = match ($row['state']) {
                                'open' => $row['submission_status'] === 'returned' ? 'Correction needed' : 'Requires action',
                                'upcoming' => 'Upcoming',
                                'submitted' => $row['submission_status_label'],
                                default => 'Closed',
                            };
                            $searchText = \Illuminate\Support\Str::lower(implode(' ', array_filter([
                                $row['indicator_code'],
                                $row['indicator_name'],
                                $row['form_title'],
                                $row['period_label'],
                                $row['indicator_unit'],
                            ])));
                        @endphp
                        <a class="me-indicator-row"
                           href="{{ $row['url'] }}"
                           data-state="{{ $row['state'] }}"
                           data-search="{{ $searchText }}"
                           aria-label="Open {{ $row['indicator_name'] }} reporting form">
                            <div>
                                <span class="me-indicator-code">
                                    {{ $row['is_linked_indicator'] ? $row['indicator_code'] : 'Reporting template' }}
                                </span>
                                <h3 class="me-indicator-name">{{ $row['indicator_name'] }}</h3>
                                <p class="me-template-name">Form: {{ $row['form_title'] }}</p>
                            </div>
                            <div class="me-row-meta">
                                <span><i class="feather-calendar" aria-hidden="true"></i>{{ $row['period_label'] }}</span>
                                @if ($row['indicator_unit'] !== '')
                                    <span><i class="feather-hash" aria-hidden="true"></i>Unit: {{ $row['indicator_unit'] }}</span>
                                @endif
                                @if ($row['due_at'])
                                    <span><i class="feather-clock" aria-hidden="true"></i>Due {{ $row['due_at']->format('d M Y') }}</span>
                                @endif
                            </div>
                            <div class="me-row-action">
                                <span class="me-state">{{ $stateLabel }}</span>
                                <span class="me-open-form">
                                    {{ $row['state'] === 'open' ? ($row['submission_status'] ? 'Continue form' : 'Open form') : 'View form' }}
                                    <i class="feather-arrow-right" aria-hidden="true"></i>
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
                <div class="me-empty" data-me-no-results hidden>
                    No assigned indicator matches this filter.
                </div>
            </section>
        @else
            <section class="me-empty">
                <i class="feather-inbox d-block fs-3 mb-2" aria-hidden="true"></i>
                <strong class="d-block text-dark mb-1">No indicator has been assigned</strong>
                Assigned indicators and their reporting templates will appear here when the M&amp;E team publishes them.
            </section>
        @endif
    </div>

    <script>
        (() => {
            const startIndicatorWorkspace = () => {
                const workspace = document.querySelector('[data-me-indicator-workspace]');
                if (!workspace) return;

                const rows = Array.from(workspace.querySelectorAll('.me-indicator-row'));
                const filters = Array.from(workspace.querySelectorAll('[data-me-filter]'));
                const search = workspace.querySelector('[data-me-search]');
                const status = workspace.querySelector('[data-me-results-status]');
                const empty = workspace.querySelector('[data-me-no-results]');
                let activeState = 'all';

                const applyFilters = () => {
                    const query = (search?.value || '').trim().toLocaleLowerCase();
                    let visible = 0;

                    rows.forEach((row) => {
                        const matchesState = activeState === 'all' || row.dataset.state === activeState;
                        const matchesSearch = query === '' || (row.dataset.search || '').includes(query);
                        row.hidden = !(matchesState && matchesSearch);
                        if (!row.hidden) visible += 1;
                    });

                    if (status) status.textContent = visible + ' ' + (visible === 1 ? 'record' : 'records') + ' shown';
                    if (empty) empty.hidden = visible !== 0;
                };

                filters.forEach((filter) => {
                    filter.addEventListener('click', () => {
                        activeState = filter.dataset.meFilter || 'all';
                        filters.forEach((item) => item.setAttribute('aria-pressed', item === filter ? 'true' : 'false'));
                        applyFilters();
                    });
                });

                search?.addEventListener('input', applyFilters);
                applyFilters();
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', startIndicatorWorkspace, { once: true });
            } else {
                startIndicatorWorkspace();
            }
        })();
    </script>
</x-think-tank.partials.shell>
