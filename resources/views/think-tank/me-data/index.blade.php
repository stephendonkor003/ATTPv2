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

@push('styles')
    <style>
        .tt-indicator-workspace {
            --me-ink: #1b2821;
            --me-muted: #66736b;
            --me-line: #dfe7e2;
            --me-green: #176b4b;
            --me-green-soft: #edf6f1;
        }

        .tt-indicator-workspace .me-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1.25rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--me-line);
        }

        .tt-indicator-workspace .me-eyebrow {
            margin-bottom: .3rem;
            color: var(--me-green);
            font-size: .69rem;
            font-weight: 800;
            letter-spacing: .065em;
            text-transform: uppercase;
        }

        .tt-indicator-workspace .me-heading h1 {
            margin: 0;
            color: var(--me-ink);
            font-size: clamp(1.25rem, 2vw, 1.65rem);
            font-weight: 760;
        }

        .tt-indicator-workspace .me-heading p {
            max-width: 690px;
            margin: .38rem 0 0;
            color: var(--me-muted);
            font-size: .84rem;
            line-height: 1.55;
        }

        .tt-indicator-workspace .me-assigned-count {
            min-width: 86px;
            padding: .58rem .75rem;
            border: 1px solid var(--me-line);
            border-radius: 8px;
            background: #fff;
            color: var(--me-muted);
            font-size: .7rem;
            text-align: center;
        }

        .tt-indicator-workspace .me-assigned-count strong {
            display: block;
            color: var(--me-ink);
            font-size: 1.05rem;
        }

        .tt-indicator-workspace .me-overview-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 0;
            margin: 1rem 0;
            overflow: hidden;
            border: 1px solid var(--me-line);
            border-radius: 9px;
            background: #fff;
        }

        .tt-indicator-workspace .me-overview-item {
            min-width: 150px;
            display: flex;
            align-items: center;
            gap: .65rem;
            padding: .75rem .9rem;
            border-inline-end: 1px solid var(--me-line);
            color: var(--me-muted);
            font-size: .74rem;
        }

        .tt-indicator-workspace .me-overview-item:last-child {
            border-inline-end: 0;
        }

        .tt-indicator-workspace .me-overview-item i {
            color: var(--me-green);
        }

        .tt-indicator-workspace .me-overview-item strong {
            display: block;
            color: var(--me-ink);
            font-size: .87rem;
        }

        .tt-indicator-workspace .me-tools {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin: 1rem 0 .75rem;
        }

        .tt-indicator-workspace .me-filters {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
        }

        .tt-indicator-workspace .me-filter {
            min-height: 34px;
            border: 1px solid var(--me-line);
            border-radius: 7px;
            background: #fff;
            color: #536158;
            padding: .38rem .65rem;
            font-size: .72rem;
            font-weight: 700;
        }

        .tt-indicator-workspace .me-filter[aria-pressed="true"] {
            border-color: #b9d6c7;
            background: var(--me-green-soft);
            color: #11553a;
        }

        .tt-indicator-workspace .me-search {
            width: min(100%, 290px);
            position: relative;
        }

        .tt-indicator-workspace .me-search i {
            position: absolute;
            inset-block-start: 50%;
            inset-inline-start: .75rem;
            color: #849188;
            transform: translateY(-50%);
        }

        .tt-indicator-workspace .me-search input {
            width: 100%;
            min-height: 38px;
            border: 1px solid var(--me-line);
            border-radius: 8px;
            background: #fff;
            padding: .5rem .75rem .5rem 2.15rem;
            color: var(--me-ink);
            font-size: .78rem;
        }

        [dir="rtl"] .tt-indicator-workspace .me-search input {
            padding: .5rem 2.15rem .5rem .75rem;
        }

        .tt-indicator-workspace .me-register-head {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: .55rem;
        }

        .tt-indicator-workspace .me-register-head h2 {
            margin: 0;
            color: var(--me-ink);
            font-size: .96rem;
            font-weight: 760;
        }

        .tt-indicator-workspace .me-results-status {
            color: var(--me-muted);
            font-size: .72rem;
        }

        .tt-indicator-workspace .me-indicator-list {
            display: grid;
            gap: .55rem;
        }

        .tt-indicator-workspace .me-indicator-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(220px, .62fr) auto;
            align-items: center;
            gap: 1rem;
            padding: .9rem 1rem;
            border: 1px solid var(--me-line);
            border-inline-start: 3px solid #98a69e;
            border-radius: 8px;
            background: rgba(255, 255, 255, .94);
            color: inherit;
            text-decoration: none;
            transition: border-color .14s ease, background-color .14s ease;
        }

        .tt-indicator-workspace .me-indicator-row:hover,
        .tt-indicator-workspace .me-indicator-row:focus-visible {
            border-color: #9fc7b3;
            background: #fbfdfc;
            color: inherit;
        }

        .tt-indicator-workspace .me-indicator-row[data-state="open"] { border-inline-start-color: #168454; }
        .tt-indicator-workspace .me-indicator-row[data-state="upcoming"] { border-inline-start-color: #4777b8; }
        .tt-indicator-workspace .me-indicator-row[data-state="submitted"] { border-inline-start-color: #7063a8; }

        .tt-indicator-workspace .me-indicator-code {
            display: inline-flex;
            margin-bottom: .25rem;
            color: var(--me-green);
            font-size: .65rem;
            font-weight: 800;
            letter-spacing: .055em;
            text-transform: uppercase;
        }

        .tt-indicator-workspace .me-indicator-name {
            margin: 0;
            color: var(--me-ink);
            font-size: .91rem;
            font-weight: 760;
            line-height: 1.35;
        }

        .tt-indicator-workspace .me-template-name {
            margin: .28rem 0 0;
            color: var(--me-muted);
            font-size: .72rem;
        }

        .tt-indicator-workspace .me-row-meta {
            display: grid;
            gap: .32rem;
            color: #58675e;
            font-size: .71rem;
        }

        .tt-indicator-workspace .me-row-meta span {
            display: flex;
            align-items: center;
            gap: .4rem;
        }

        .tt-indicator-workspace .me-row-meta i {
            width: 14px;
            color: #718078;
        }

        .tt-indicator-workspace .me-row-action {
            min-width: 115px;
            display: grid;
            justify-items: end;
            gap: .42rem;
        }

        .tt-indicator-workspace .me-state {
            padding: .24rem .48rem;
            border-radius: 999px;
            background: #f1f4f2;
            color: #59675f;
            font-size: .65rem;
            font-weight: 750;
        }

        .tt-indicator-workspace .me-open-form {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            color: var(--me-green);
            font-size: .73rem;
            font-weight: 800;
        }

        .tt-indicator-workspace .me-empty {
            padding: 2.5rem 1rem;
            border: 1px dashed #c8d4cd;
            border-radius: 9px;
            background: rgba(255, 255, 255, .72);
            color: var(--me-muted);
            font-size: .8rem;
            text-align: center;
        }

        @media (max-width: 860px) {
            .tt-indicator-workspace .me-tools {
                align-items: stretch;
                flex-direction: column;
            }

            .tt-indicator-workspace .me-search {
                width: 100%;
            }

            .tt-indicator-workspace .me-indicator-row {
                grid-template-columns: minmax(0, 1fr) auto;
            }

            .tt-indicator-workspace .me-row-meta {
                grid-column: 1 / -1;
                grid-row: 2;
            }
        }

        @media (max-width: 600px) {
            .tt-indicator-workspace .me-heading {
                flex-direction: column;
            }

            .tt-indicator-workspace .me-assigned-count {
                min-width: 0;
                display: flex;
                align-items: baseline;
                gap: .35rem;
            }

            .tt-indicator-workspace .me-overview-item {
                min-width: 50%;
                flex: 1;
            }

            .tt-indicator-workspace .me-indicator-row {
                grid-template-columns: 1fr;
            }

            .tt-indicator-workspace .me-row-meta {
                grid-column: auto;
                grid-row: auto;
            }

            .tt-indicator-workspace .me-row-action {
                min-width: 0;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }
        }
    </style>
@endpush

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
