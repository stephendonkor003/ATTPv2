@extends('layouts.app')

@section('title', $methodDefinition['label'].' Evaluation Reports')

@section('content')
    @php
        $statusLabels = [
            'ready' => 'Report ready',
            'in_progress' => 'In progress',
            'awaiting' => 'Awaiting reports',
        ];
        $otherMethods = collect(\App\Models\Evaluation::configurationTypes());
    @endphp

    <main class="nxl-container evr-shell" aria-labelledby="methodReportTitle">
        <header class="evr-hero">
            <div class="evr-hero__copy">
                <span class="evr-eyebrow">{{ $methodDefinition['mode'] }}</span>
                <h1 id="methodReportTitle">{{ $methodDefinition['label'] }} Evaluation Reports</h1>
                <p>{{ $methodDefinition['description'] }} Select a procurement below to open its complete report and supporting evaluation evidence.</p>
                <div class="evr-hero__meta">
                    <span><i class="{{ $methodDefinition['icon'] }}" aria-hidden="true"></i>{{ number_format($summary['procurements']) }} procurements</span>
                    <span><i class="feather-file-text" aria-hidden="true"></i>{{ number_format($summary['reports']) }} reports filed</span>
                    <span><i class="feather-check-circle" aria-hidden="true"></i>{{ number_format($summary['ready']) }} report-ready</span>
                </div>
            </div>
            <div class="evr-hero__actions evr-no-print">
                <a href="{{ route('reports.evaluations.index') }}" class="evr-btn evr-btn--light">
                    <i class="feather-arrow-left" aria-hidden="true"></i> All methods
                </a>
            </div>
        </header>

        <section class="evr-kpi-grid" aria-label="{{ $methodDefinition['label'] }} report overview">
            @foreach ([
                ['feather-briefcase', 'Procurements', $summary['procurements'], null],
                ['feather-file-text', 'Completed reports', $summary['reports'], null],
                ['feather-users', 'Applicants evaluated', $summary['applicants'], null],
                ['feather-user-check', 'Evaluators', $summary['evaluators'], null],
                ['feather-check-circle', 'Reports ready', $summary['ready'], 'of '.$summary['procurements']],
            ] as [$icon, $label, $value, $detail])
                <article class="evr-kpi">
                    <span class="evr-kpi__icon"><i class="{{ $icon }}" aria-hidden="true"></i></span>
                    <div><span>{{ $label }}</span><strong>{{ number_format($value) }}</strong>@if ($detail)<small>{{ $detail }}</small>@endif</div>
                </article>
            @endforeach
        </section>

        <section class="evr-section evr-panel" aria-labelledby="procurementListTitle">
            <header class="evr-panel__head">
                <div>
                    <span class="evr-eyebrow">Procurement library</span>
                    <h2 id="procurementListTitle">Procurements using {{ $methodDefinition['label'] }}</h2>
                    <p>Search by title or reference and filter by report progress.</p>
                </div>
                <div class="evr-actions evr-no-print" aria-label="Switch evaluation method">
                    @foreach ($otherMethods as $otherType => $otherDefinition)
                        <a href="{{ route('reports.evaluations.method', $otherType) }}"
                           class="evr-btn {{ $otherType === $method ? 'evr-btn--primary' : 'evr-btn--outline' }}"
                           @if ($otherType === $method) aria-current="page" @endif>
                            {{ $otherDefinition['label'] }}
                        </a>
                    @endforeach
                </div>
            </header>

            @if ($procurementRows->isNotEmpty())
                <div class="evr-toolbar evr-no-print" role="search" aria-label="Filter procurements">
                    <label class="evr-field evr-field--search" for="methodProcurementSearch">
                        <span>Search procurements</span>
                        <div class="evr-input">
                            <i class="feather-search" aria-hidden="true"></i>
                            <input id="methodProcurementSearch" type="search" autocomplete="off" placeholder="Title, reference, template, or category">
                        </div>
                    </label>
                    <label class="evr-field" for="methodProcurementStatus">
                        <span>Report status</span>
                        <select id="methodProcurementStatus">
                            <option value="all">All statuses</option>
                            <option value="ready">Report ready</option>
                            <option value="in_progress">In progress</option>
                            <option value="awaiting">Awaiting reports</option>
                        </select>
                    </label>
                    <label class="evr-field" for="methodProcurementSort">
                        <span>Sort by</span>
                        <select id="methodProcurementSort">
                            <option value="recent">Most recent</option>
                            <option value="title">Title A–Z</option>
                            <option value="reports">Most reports</option>
                        </select>
                    </label>
                    <button id="methodProcurementReset" type="button" class="evr-btn evr-btn--outline">
                        <i class="feather-rotate-ccw" aria-hidden="true"></i> Reset
                    </button>
                </div>

                <div class="evr-results">
                    <span id="methodProcurementResultCount" aria-live="polite">Showing {{ $procurementRows->count() }} procurements</span>
                    <span>Open a procurement to review its method-specific report.</span>
                </div>

                <div id="methodProcurementList" class="evr-procurement-list">
                    @foreach ($procurementRows as $row)
                        @php
                            $procurement = $row['procurement'];
                            $title = $procurement->title ?: 'Untitled procurement';
                            $statusLabel = $statusLabels[$row['status']] ?? Str::headline($row['status']);
                            $result = $row['result_summary'];
                            $viewUrl = $method === \App\Models\Evaluation::TYPE_EOI
                                ? route('reports.evaluations.eoi.procurement', $procurement)
                                : route('reports.evaluations.method.procurement', [$method, $procurement]);
                            $search = Str::lower(collect([
                                $title,
                                $procurement->reference_no,
                                $procurement->status,
                                $row['procurement_method'],
                                $row['procurement_category'],
                            ])->merge($row['templates'])->filter()->implode(' '));
                        @endphp
                        <article class="evr-procurement-card"
                            data-method-procurement
                            data-search="{{ $search }}"
                            data-status="{{ $row['status'] }}"
                            data-title="{{ Str::lower($title) }}"
                            data-reports="{{ $row['report_count'] }}"
                            data-latest="{{ $row['latest_at']?->getTimestamp() ?? 0 }}">
                            <div class="evr-procurement-card__identity">
                                <div class="evr-reference-line">
                                    <span class="evr-reference"><i class="feather-hash" aria-hidden="true"></i>{{ $procurement->reference_no ?: 'No reference' }}</span>
                                    <span class="evr-status evr-status--{{ $row['status'] }}">{{ $statusLabel }}</span>
                                </div>
                                <h3><a href="{{ $viewUrl }}">{{ $title }}</a></h3>
                                <div class="evr-card-meta">
                                    <span><i class="feather-settings" aria-hidden="true"></i>{{ filled($row['procurement_method']) ? Str::headline($row['procurement_method']) : 'Method not specified' }}</span>
                                    <span><i class="feather-tag" aria-hidden="true"></i>{{ filled($row['procurement_category']) ? Str::headline($row['procurement_category']) : 'Category not specified' }}</span>
                                </div>
                                @if ($row['templates']->isNotEmpty())
                                    <div class="evr-tags" aria-label="Evaluation templates">
                                        @foreach ($row['templates']->take(3) as $template)<span>{{ $template }}</span>@endforeach
                                        @if ($row['templates']->count() > 3)<span>+{{ $row['templates']->count() - 3 }} more</span>@endif
                                    </div>
                                @endif
                            </div>

                            <div class="evr-procurement-card__metrics" aria-label="{{ $title }} reporting summary">
                                <div class="evr-mini-metric"><span>Reports</span><strong>{{ number_format($row['report_count']) }}</strong><small>{{ $row['completed_assignment_count'] }}/{{ $row['assignment_count'] }} tasks</small></div>
                                <div class="evr-mini-metric"><span>Applicants</span><strong>{{ number_format($row['applicant_count']) }}</strong><small>of {{ number_format($row['total_applicants']) }} received</small></div>
                                <div class="evr-mini-metric"><span>Evaluators</span><strong>{{ number_format($row['evaluator_count']) }}</strong><small>panel members</small></div>
                                <div class="evr-mini-metric"><span>{{ $result['label'] }}</span><strong>{{ $result['value'] !== null ? number_format($result['value'], $method === 'services' ? 1 : 0).$result['suffix'] : '—' }}</strong><small>{{ $result['detail'] }}</small></div>
                            </div>

                            <div class="evr-procurement-card__action evr-no-print">
                                <a href="{{ $viewUrl }}" class="evr-btn evr-btn--primary">
                                    <i class="feather-arrow-up-right" aria-hidden="true"></i>{{ $method === 'eoi' ? 'Qualification report' : 'Open report' }}
                                </a>
                                <small class="text-muted">{{ $row['latest_at']?->format('d M Y') ?? 'No submissions yet' }}</small>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div id="methodProcurementNoResults" class="evr-empty" hidden>
                    <span class="evr-empty__icon"><i class="feather-search" aria-hidden="true"></i></span>
                    <h3>No procurements match these filters</h3>
                    <p>Try a different search term or clear the report status filter.</p>
                </div>
            @else
                <div class="evr-empty">
                    <span class="evr-empty__icon"><i class="{{ $methodDefinition['icon'] }}" aria-hidden="true"></i></span>
                    <h3>No {{ $methodDefinition['label'] }} procurements yet</h3>
                    <p>Configured procurements will appear here as soon as this evaluation method is assigned.</p>
                </div>
            @endif
        </section>
    </main>
@endsection

@push('styles')
    @include('reports.evaluations.partials.report-suite-styles')
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const list = document.getElementById('methodProcurementList');
            const search = document.getElementById('methodProcurementSearch');
            const status = document.getElementById('methodProcurementStatus');
            const sort = document.getElementById('methodProcurementSort');
            const reset = document.getElementById('methodProcurementReset');
            const resultCount = document.getElementById('methodProcurementResultCount');
            const empty = document.getElementById('methodProcurementNoResults');

            if (!list || !search || !status || !sort) return;

            const cards = Array.from(list.querySelectorAll('[data-method-procurement]'));
            const normalize = (value) => String(value || '').trim().toLocaleLowerCase();

            const sortCards = () => {
                cards.sort((left, right) => {
                    if (sort.value === 'title') return (left.dataset.title || '').localeCompare(right.dataset.title || '');
                    if (sort.value === 'reports') return Number(right.dataset.reports || 0) - Number(left.dataset.reports || 0);
                    return Number(right.dataset.latest || 0) - Number(left.dataset.latest || 0)
                        || (left.dataset.title || '').localeCompare(right.dataset.title || '');
                });
                cards.forEach((card) => list.appendChild(card));
            };

            const filterCards = () => {
                const query = normalize(search.value);
                let visible = 0;
                cards.forEach((card) => {
                    const matches = (!query || normalize(card.dataset.search).includes(query))
                        && (status.value === 'all' || card.dataset.status === status.value);
                    card.hidden = !matches;
                    visible += matches ? 1 : 0;
                });
                if (resultCount) resultCount.textContent = `Showing ${visible} ${visible === 1 ? 'procurement' : 'procurements'}`;
                if (empty) empty.hidden = visible !== 0;
            };

            search.addEventListener('input', filterCards);
            status.addEventListener('change', filterCards);
            sort.addEventListener('change', () => { sortCards(); filterCards(); });
            reset?.addEventListener('click', () => {
                search.value = '';
                status.value = 'all';
                sort.value = 'recent';
                sortCards();
                filterCards();
                search.focus();
            });
            sortCards();
            filterCards();
        });
    </script>
@endpush
