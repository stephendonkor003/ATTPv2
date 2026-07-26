@php
    $usersById = $users->keyBy(fn ($user) => (string) $user->id);
    $formatMetric = static function ($value): string {
        if ($value === null || $value === '') {
            return 'Not set';
        }

        if (!is_numeric($value)) {
            return (string) $value;
        }

        return rtrim(rtrim(number_format((float) $value, 4, '.', ','), '0'), '.');
    };
@endphp

<section class="me-panel" aria-labelledby="indicator-register-title">
    <div class="me-panel-header flex-column flex-lg-row align-items-lg-center">
        <div>
            <h2 class="me-panel-title" id="indicator-register-title">Indicator register</h2>
            <p class="me-panel-subtitle">Review the complete measurement definition without opening multiple tabs.</p>
        </div>

        <form method="GET" action="{{ route('budget.me.indicators.index') }}" class="me-filter-bar" role="search">
            <label class="visually-hidden" for="indicator-search">Search indicators</label>
            <div class="me-search-wrap">
                <i class="feather-search" aria-hidden="true"></i>
                <input
                    type="search"
                    id="indicator-search"
                    name="q"
                    class="form-control"
                    value="{{ $search ?? request('q') }}"
                    placeholder="Search ID, name, component or evidence"
                >
            </div>
            <label class="visually-hidden" for="indicator-component-filter">Project component</label>
            <select id="indicator-component-filter" name="component_id" class="form-select" style="min-width: 220px">
                <option value="">All project components</option>
                @foreach ($componentOptions as $component)
                    <option value="{{ $component->id }}" @selected((string) $componentFilter === (string) $component->id)>
                        {{ $component->project_id ? $component->project_id.' — ' : '' }}{{ $component->name }}
                        ({{ number_format((int) ($componentCounts->get($component->id) ?? 0)) }})
                    </option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-outline-success">Search</button>
            @if (filled($search ?? request('q')) || filled($componentFilter))
                <a href="{{ route('budget.me.indicators.index') }}" class="btn btn-light border">Clear</a>
            @endif
            <a href="{{ route('budget.me.indicators.report.excel', ['q' => $search, 'component_id' => $componentFilter]) }}" class="btn btn-light border" title="Export filtered indicators to Excel">
                <i class="feather-download"></i> Excel
            </a>
            <a href="{{ route('budget.me.indicators.report.pdf', ['q' => $search, 'component_id' => $componentFilter]) }}" class="btn btn-light border" title="Export filtered indicators to PDF">
                <i class="feather-file-text"></i> PDF
            </a>
        </form>
    </div>

    @if ($indicators->isEmpty())
        <div class="me-empty-state">
            <span class="me-empty-icon"><i class="feather-target" aria-hidden="true"></i></span>
            <h3 class="h6 fw-bold mb-2">
                {{ filled($search ?? request('q')) || filled($componentFilter) ? 'No matching indicators' : 'No indicators have been added' }}
            </h3>
            <p class="me-muted small mb-3">
                {{ filled($search ?? request('q')) || filled($componentFilter)
                    ? 'Try a different search term or project component.'
                    : 'Create the first indicator to begin your results framework.' }}
            </p>
            @can('me.configuration.manage')
                @if (!filled($search ?? request('q')) && !filled($componentFilter))
                    <a href="{{ route('budget.me.indicators.index', ['create' => 1]) }}#indicator-form" class="me-primary-action">
                        <i class="feather-plus" aria-hidden="true"></i> Add first indicator
                    </a>
                @endif
            @endcan
        </div>
    @else
        <div class="table-responsive me-register-desktop">
            <table class="table me-register-table align-middle">
                <caption class="visually-hidden">Results framework indicators and their required measurement information</caption>
                <thead>
                    <tr>
                        <th style="width: 30%">Indicator</th>
                        <th style="width: 18%">Measurement</th>
                        <th style="width: 24%">Reporting &amp; evidence</th>
                        <th style="width: 15%">Responsible person</th>
                        <th class="text-end" style="width: 13%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($indicators as $indicator)
                        @php
                            $setupTarget = $indicator->setupTarget;
                            $unitLabel = $indicator->unit?->symbol ?: ($indicator->unit?->name ?: 'Unit not set');
                            $dataCollectionMethod = (string) ($indicator->data_collection_method ?: $indicator->methodology ?: '');
                            $disaggregations = $indicator->disaggregations->keyBy('level');
                            $disaggregationChain = $indicator->disaggregationChain();
                            $responsibleNames = collect([$indicator->responsiblePerson?->name])->filter();
                            if ($responsibleNames->isEmpty()) {
                                $responsibleNames = collect(json_decode((string) $indicator->responsible_party, true) ?: [])
                                    ->map(fn ($id) => $usersById->get((string) $id)?->name)
                                    ->filter()
                                    ->values();
                            }
                        @endphp
                        <tr>
                            <td>
                                <span class="me-code">{{ $indicator->indicator_code ?: $indicator->id }}</span>
                                <div class="me-indicator-name">{{ $indicator->name }}</div>
                                <div class="me-definition" title="{{ $indicator->definitions }}">
                                    {{ $indicator->definitions ?: 'Definition not provided' }}
                                </div>
                                <div class="d-flex flex-wrap gap-1 mt-2">
                                    <span class="me-chip"><i class="feather-layers"></i>{{ $indicator->resultsLevelLabel() }}</span>
                                    <span class="me-chip">
                                        <i class="feather-briefcase"></i>
                                        {{ $indicator->projectComponent?->project_id ? $indicator->projectComponent->project_id.' — ' : '' }}{{ $indicator->projectComponent?->name ?: 'Component not set' }}
                                    </span>
                                </div>
                                @if ($indicator->indicatorable)
                                    <div class="me-muted mt-2"><i class="feather-link me-1"></i>{{ $indicator->indicatorable->name }}</div>
                                @endif
                            </td>
                            <td>
                                <div class="me-metric-line">
                                    <span class="me-muted">Baseline</span>
                                    <span class="me-metric-value">{{ $formatMetric($indicator->baseline_value) }}</span>
                                </div>
                                <div class="me-metric-line">
                                    <span class="me-muted">Target</span>
                                    <span class="me-metric-value">{{ $formatMetric($setupTarget?->target_value) }}</span>
                                </div>
                                <span class="me-chip"><i class="feather-hash"></i>{{ $unitLabel }}</span>
                            </td>
                            <td>
                                <div class="mb-2">
                                    <span class="me-chip"><i class="feather-calendar"></i>{{ $indicator->frequency?->indicatorCadenceLabel() ?: 'Frequency not set' }}</span>
                                    <span class="me-chip"><i class="feather-filter"></i>{{ $disaggregationChain !== '' ? $disaggregationChain : 'No disaggregation' }}</span>
                                </div>
                                <div class="me-muted text-break">
                                    <i class="feather-database me-1"></i>{{ $dataCollectionMethod !== '' ? $dataCollectionMethod : 'Collection method not set' }}
                                </div>
                                <div class="me-muted text-break mt-1">
                                    <i class="feather-check-square me-1"></i>
                                    @if ($indicator->meansOfVerification)
                                        <a href="{{ route('budget.me.rebuild.knowledge-repository', ['q' => $indicator->meansOfVerification->title]) }}">
                                            {{ $indicator->meansOfVerification->title }}
                                        </a>
                                    @else
                                        Means of Verification not linked
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if ($responsibleNames->isNotEmpty())
                                    <div class="fw-semibold text-dark">{{ $responsibleNames->join(', ') }}</div>
                                @else
                                    <span class="me-muted">Not assigned</span>
                                @endif
                            </td>
                            <td>
                                @can('me.configuration.manage')
                                    <div class="me-row-actions">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary"
                                            data-disaggregation-open
                                            data-indicator-name="{{ $indicator->name }}"
                                            data-action="{{ route('budget.me.indicators.disaggregations.update', $indicator) }}"
                                            data-primary="{{ $disaggregations->get('primary')?->dimension }}"
                                            data-secondary="{{ $disaggregations->get('secondary')?->dimension }}"
                                            data-tertiary="{{ $disaggregations->get('tertiary')?->dimension }}"
                                            aria-label="Configure disaggregation for {{ $indicator->name }}"
                                        >
                                            <i class="feather-filter" aria-hidden="true"></i> Disaggregation
                                        </button>
                                        <a
                                            href="{{ route('budget.me.indicators.index', ['edit' => $indicator->id]) }}#indicator-form"
                                            class="btn btn-sm btn-light border"
                                            aria-label="Edit {{ $indicator->name }}"
                                        >
                                            <i class="feather-edit-2" aria-hidden="true"></i> Edit
                                        </a>
                                        <form method="POST" action="{{ route('budget.me.indicators.destroy', $indicator) }}" data-delete-indicator-form>
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Delete {{ $indicator->name }}">
                                                <i class="feather-trash-2" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <span class="me-muted">View only</span>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="me-mobile-register">
            @foreach ($indicators as $indicator)
                @php
                    $setupTarget = $indicator->setupTarget;
                    $unitLabel = $indicator->unit?->symbol ?: ($indicator->unit?->name ?: 'Unit not set');
                    $dataCollectionMethod = (string) ($indicator->data_collection_method ?: $indicator->methodology ?: '');
                    $disaggregations = $indicator->disaggregations->keyBy('level');
                    $disaggregationChain = $indicator->disaggregationChain();
                    $responsibleNames = collect([$indicator->responsiblePerson?->name])->filter();
                    if ($responsibleNames->isEmpty()) {
                        $responsibleNames = collect(json_decode((string) $indicator->responsible_party, true) ?: [])
                            ->map(fn ($id) => $usersById->get((string) $id)?->name)
                            ->filter()
                            ->values();
                    }
                @endphp
                <article class="me-mobile-card">
                    <span class="me-code">{{ $indicator->indicator_code ?: $indicator->id }}</span>
                    <h3 class="me-indicator-name mb-1">{{ $indicator->name }}</h3>
                    <p class="me-definition mb-0">{{ $indicator->definitions ?: 'Definition not provided' }}</p>
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        <span class="me-chip"><i class="feather-layers"></i>{{ $indicator->resultsLevelLabel() }}</span>
                        <span class="me-chip"><i class="feather-briefcase"></i>{{ $indicator->projectComponent?->name ?: 'Component not set' }}</span>
                    </div>

                    <div class="me-mobile-facts">
                        <div class="me-mobile-fact">
                            <small>Baseline</small>
                            <strong>{{ $formatMetric($indicator->baseline_value) }} {{ $unitLabel }}</strong>
                        </div>
                        <div class="me-mobile-fact">
                            <small>Target</small>
                            <strong>{{ $formatMetric($setupTarget?->target_value) }} {{ $unitLabel }}</strong>
                        </div>
                        <div class="me-mobile-fact">
                            <small>Frequency</small>
                            <strong>{{ $indicator->frequency?->indicatorCadenceLabel() ?: 'Not set' }}</strong>
                        </div>
                        <div class="me-mobile-fact">
                            <small>Responsible</small>
                            <strong>{{ $responsibleNames->isNotEmpty() ? $responsibleNames->join(', ') : 'Not assigned' }}</strong>
                        </div>
                    </div>

                    <div class="me-muted text-break"><i class="feather-filter me-1"></i>{{ $disaggregationChain !== '' ? $disaggregationChain : 'No disaggregation' }}</div>
                    <div class="me-muted text-break"><i class="feather-database me-1"></i>{{ $dataCollectionMethod !== '' ? $dataCollectionMethod : 'Collection method not set' }}</div>
                    <div class="me-muted text-break mb-3">
                        <i class="feather-check-square me-1"></i>
                        @if ($indicator->meansOfVerification)
                            <a href="{{ route('budget.me.rebuild.knowledge-repository', ['q' => $indicator->meansOfVerification->title]) }}">
                                {{ $indicator->meansOfVerification->title }}
                            </a>
                        @else
                            Means of Verification not linked
                        @endif
                    </div>

                    @can('me.configuration.manage')
                        <div class="me-row-actions justify-content-start">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary"
                                data-disaggregation-open
                                data-indicator-name="{{ $indicator->name }}"
                                data-action="{{ route('budget.me.indicators.disaggregations.update', $indicator) }}"
                                data-primary="{{ $disaggregations->get('primary')?->dimension }}"
                                data-secondary="{{ $disaggregations->get('secondary')?->dimension }}"
                                data-tertiary="{{ $disaggregations->get('tertiary')?->dimension }}"
                            >
                                <i class="feather-filter me-1"></i> Disaggregation
                            </button>
                            <a href="{{ route('budget.me.indicators.index', ['edit' => $indicator->id]) }}#indicator-form" class="btn btn-sm btn-light border">
                                <i class="feather-edit-2 me-1"></i> Edit
                            </a>
                            <form method="POST" action="{{ route('budget.me.indicators.destroy', $indicator) }}" data-delete-indicator-form>
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="feather-trash-2 me-1"></i> Delete</button>
                            </form>
                        </div>
                    @endcan
                </article>
            @endforeach
        </div>

        @if ($indicators->hasPages())
            <div class="px-3 py-3 border-top">
                {{ $indicators->links() }}
            </div>
        @endif
    @endif
</section>
