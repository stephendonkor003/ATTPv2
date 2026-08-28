@extends('layouts.app')

@section('title', 'Panel Evaluations')

@section('content')
    @php
        $statusLabels = [
            'ready' => 'Panel complete',
            'in_progress' => 'In progress',
            'awaiting' => 'Awaiting reports',
            'setup_required' => 'Setup required',
        ];
    @endphp

    <main class="nxl-container pev-shell" aria-labelledby="panelEvaluationTitle">
        <header class="pev-hero">
            <div class="pev-hero__copy">
                <span class="pev-eyebrow">Procurement evaluation workspace</span>
                <h1 id="panelEvaluationTitle">Panel Evaluations</h1>
                <p>Open a procurement to follow its evaluation journey, understand what is happening now, and continue into the correct Services, Goods, or EOI workspace.</p>
                <div class="pev-hero__meta" aria-label="Panel evaluation overview">
                    <span><i class="feather-briefcase" aria-hidden="true"></i>{{ number_format($summary['procurements']) }} procurements</span>
                    <span><i class="feather-inbox" aria-hidden="true"></i>{{ number_format($summary['applications']) }} applications</span>
                    <span><i class="feather-file-text" aria-hidden="true"></i>{{ number_format($summary['reports']) }} active reports</span>
                </div>
            </div>
            <div class="pev-hero__signal" aria-hidden="true">
                <span class="pev-hero__signal-ring"><i class="feather-activity"></i></span>
                <div><strong>{{ number_format($summary['ready']) }}</strong><span>panel-ready</span></div>
            </div>
        </header>

        <section class="pev-kpi-grid" aria-label="Workspace summary">
            @foreach ([
                ['feather-briefcase', 'Procurements', $summary['procurements'], 'in your accessible portfolio'],
                ['feather-users', 'Applications', $summary['applications'], 'linked to these procurements'],
                ['feather-file-text', 'Submitted reports', $summary['reports'], 'from currently assigned evaluators'],
                ['feather-loader', 'Active panels', $summary['in_progress'], 'awaiting or in progress'],
                ['feather-check-circle', 'Panel complete', $summary['ready'], 'all active assignments complete'],
            ] as [$icon, $label, $value, $detail])
                <article class="pev-kpi">
                    <span class="pev-kpi__icon"><i class="{{ $icon }}" aria-hidden="true"></i></span>
                    <div><span>{{ $label }}</span><strong>{{ number_format($value) }}</strong><small>{{ $detail }}</small></div>
                </article>
            @endforeach
        </section>

        <section class="pev-panel" aria-labelledby="procurementLibraryTitle">
            <header class="pev-panel__head">
                <div>
                    <span class="pev-eyebrow">Procurement library</span>
                    <h2 id="procurementLibraryTitle">Choose a procurement</h2>
                    <p>Each card shows only current panel activity. Removed evaluator records are not included.</p>
                </div>
                <span class="pev-count" id="panelResultCount" aria-live="polite">{{ $procurementCards->count() }} procurements</span>
            </header>

            @if ($procurementCards->isNotEmpty())
                <div class="pev-toolbar" role="search" aria-label="Filter panel procurements">
                    <label class="pev-field pev-field--search" for="panelProcurementSearch">
                        <span>Search</span>
                        <div class="pev-input">
                            <i class="feather-search" aria-hidden="true"></i>
                            <input id="panelProcurementSearch" type="search" autocomplete="off" placeholder="Title, reference, category, or template">
                        </div>
                    </label>
                    <label class="pev-field" for="panelMethodFilter">
                        <span>Evaluation type</span>
                        <select id="panelMethodFilter">
                            <option value="all">All types</option>
                            <option value="eoi">EOI</option>
                            <option value="services">Services</option>
                            <option value="goods">Goods</option>
                        </select>
                    </label>
                    <label class="pev-field" for="panelStatusFilter">
                        <span>Journey status</span>
                        <select id="panelStatusFilter">
                            <option value="all">All statuses</option>
                            <option value="ready">Panel complete</option>
                            <option value="in_progress">In progress</option>
                            <option value="awaiting">Awaiting reports</option>
                            <option value="setup_required">Setup required</option>
                        </select>
                    </label>
                    <label class="pev-field" for="panelSort">
                        <span>Sort</span>
                        <select id="panelSort">
                            <option value="recent">Recent activity</option>
                            <option value="title">Title A–Z</option>
                            <option value="progress">Most progress</option>
                        </select>
                    </label>
                    <button id="panelFilterReset" class="pev-btn pev-btn--outline" type="button">
                        <i class="feather-rotate-ccw" aria-hidden="true"></i> Reset
                    </button>
                </div>

                <div id="panelProcurementGrid" class="pev-procurement-grid">
                    @foreach ($procurementCards as $card)
                        @include('evaluations.panel.partials.procurement-card', [
                            'card' => $card,
                            'statusLabel' => $statusLabels[$card['status']] ?? Str::headline($card['status']),
                        ])
                    @endforeach
                </div>

                <div id="panelNoResults" class="pev-empty" hidden>
                    <span class="pev-empty__icon"><i class="feather-search" aria-hidden="true"></i></span>
                    <h3>No procurements match these filters</h3>
                    <p>Change a filter or reset the procurement library.</p>
                </div>
            @else
                <div class="pev-empty">
                    <span class="pev-empty__icon"><i class="feather-briefcase" aria-hidden="true"></i></span>
                    <h3>No panel procurements yet</h3>
                    <p>No procurement is currently available within your accessible portfolio.</p>
                </div>
            @endif
        </section>
    </main>
@endsection

@push('styles')
    @include('evaluations.panel.partials.styles')
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const grid = document.getElementById('panelProcurementGrid');
            const search = document.getElementById('panelProcurementSearch');
            const method = document.getElementById('panelMethodFilter');
            const status = document.getElementById('panelStatusFilter');
            const sort = document.getElementById('panelSort');
            const reset = document.getElementById('panelFilterReset');
            const count = document.getElementById('panelResultCount');
            const empty = document.getElementById('panelNoResults');

            if (!grid || !search || !method || !status || !sort) return;

            const cards = Array.from(grid.querySelectorAll('[data-panel-procurement]'));
            const normalize = (value) => String(value || '').trim().toLocaleLowerCase();

            const arrange = () => {
                cards.sort((left, right) => {
                    if (sort.value === 'title') {
                        return (left.dataset.title || '').localeCompare(right.dataset.title || '');
                    }

                    if (sort.value === 'progress') {
                        return Number(right.dataset.progress || 0) - Number(left.dataset.progress || 0)
                            || (left.dataset.title || '').localeCompare(right.dataset.title || '');
                    }

                    return Number(right.dataset.latest || 0) - Number(left.dataset.latest || 0)
                        || (left.dataset.title || '').localeCompare(right.dataset.title || '');
                });

                cards.forEach((card) => grid.appendChild(card));
            };

            const filter = () => {
                const query = normalize(search.value);
                let visible = 0;

                cards.forEach((card) => {
                    const types = normalize(card.dataset.methods).split(' ').filter(Boolean);
                    const matches = (!query || normalize(card.dataset.search).includes(query))
                        && (method.value === 'all' || types.includes(method.value))
                        && (status.value === 'all' || card.dataset.status === status.value);

                    card.hidden = !matches;
                    visible += matches ? 1 : 0;
                });

                if (count) count.textContent = `${visible} ${visible === 1 ? 'procurement' : 'procurements'}`;
                if (empty) empty.hidden = visible !== 0;
            };

            search.addEventListener('input', filter);
            method.addEventListener('change', filter);
            status.addEventListener('change', filter);
            sort.addEventListener('change', () => { arrange(); filter(); });
            reset?.addEventListener('click', () => {
                search.value = '';
                method.value = 'all';
                status.value = 'all';
                sort.value = 'recent';
                arrange();
                filter();
                search.focus();
            });

            arrange();
            filter();
        });
    </script>
@endpush
