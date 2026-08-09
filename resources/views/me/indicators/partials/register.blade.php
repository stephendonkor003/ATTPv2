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
    $unitFilterOptions = $indicators->pluck('unit')
        ->filter()
        ->unique(fn ($unit) => (string) $unit->id)
        ->sortBy('name')
        ->values();
    $frequencyFilterOptions = $indicators->pluck('frequency')
        ->filter()
        ->unique(fn ($frequency) => (string) $frequency->id)
        ->sortBy(fn ($frequency) => $frequency->indicatorCadenceLabel())
        ->values();
    $responsibleFilterOptions = $indicators->pluck('responsiblePerson')
        ->filter()
        ->unique(fn ($user) => (string) $user->id)
        ->sortBy('name')
        ->values();
@endphp

<section class="me-panel" aria-labelledby="indicator-register-title">
    <div class="me-panel-header flex-column flex-lg-row align-items-lg-center">
        <div>
            <h2 class="me-panel-title" id="indicator-register-title">Indicator register</h2>
            <p class="me-panel-subtitle">Search, filter and review every measurement profile in one controlled register.</p>
        </div>

        <div class="me-register-export-actions">
            <a href="{{ route('budget.me.indicators.report.excel', ['q' => $search, 'component_id' => $componentFilter]) }}" class="btn btn-light border" title="Export filtered indicators to Excel">
                <i class="feather-download"></i> Excel
            </a>
            <a href="{{ route('budget.me.indicators.report.pdf', ['q' => $search, 'component_id' => $componentFilter]) }}" class="btn btn-light border" title="Export filtered indicators to PDF">
                <i class="feather-file-text"></i> PDF
            </a>
        </div>
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
        <div class="me-register-toolbar" data-indicator-table-toolbar>
            <div class="me-register-filter me-register-filter-search">
                <label for="indicator-table-search">Search register</label>
                <div class="me-search-wrap">
                    <i class="feather-search" aria-hidden="true"></i>
                    <input
                        type="search"
                        id="indicator-table-search"
                        class="form-control"
                        value="{{ $search ?? request('q') }}"
                        placeholder="ID, indicator, evidence or person"
                        autocomplete="off"
                        data-indicator-table-search
                    >
                </div>
            </div>
            <div class="me-register-filter">
                <label for="indicator-component-filter">Component</label>
                <select id="indicator-component-filter" class="form-select" data-indicator-table-filter="componentId">
                    <option value="">All components</option>
                    @foreach ($componentOptions as $component)
                        <option value="{{ $component->id }}" @selected((string) $componentFilter === (string) $component->id)>
                            {{ $component->project_id ? $component->project_id.' — ' : '' }}{{ $component->name }}
                            ({{ number_format((int) ($componentCounts->get($component->id) ?? 0)) }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="me-register-filter">
                <label for="indicator-unit-filter">Unit</label>
                <select id="indicator-unit-filter" class="form-select" data-indicator-table-filter="unitId">
                    <option value="">All units</option>
                    @foreach ($unitFilterOptions as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="me-register-filter">
                <label for="indicator-frequency-filter">Frequency</label>
                <select id="indicator-frequency-filter" class="form-select" data-indicator-table-filter="frequencyId">
                    <option value="">All frequencies</option>
                    @foreach ($frequencyFilterOptions as $frequency)
                        <option value="{{ $frequency->id }}">{{ $frequency->indicatorCadenceLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="me-register-filter">
                <label for="indicator-responsible-filter">Responsible person</label>
                <select id="indicator-responsible-filter" class="form-select" data-indicator-table-filter="responsibleId">
                    <option value="">Everyone</option>
                    @foreach ($responsibleFilterOptions as $responsible)
                        <option value="{{ $responsible->id }}">{{ $responsible->name }}</option>
                    @endforeach
                    <option value="unassigned">Not assigned</option>
                </select>
            </div>
            <div class="me-register-filter me-register-page-size">
                <label for="indicator-page-size">Rows</label>
                <select id="indicator-page-size" class="form-select" data-indicator-table-length>
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="-1">All</option>
                </select>
            </div>
            <button type="button" class="btn btn-light border me-register-clear" data-indicator-table-clear>
                <i class="feather-rotate-ccw" aria-hidden="true"></i> Reset
            </button>
            <div class="me-register-match-count" role="status" aria-live="polite" data-indicator-table-count>
                {{ number_format($indicators->count()) }} indicators
            </div>
        </div>
        <div class="me-table-statusbar me-register-desktop">
            <span><i class="feather-info" aria-hidden="true"></i> Select a column heading to sort the register.</span>
            <span class="me-scroll-hint"><i class="feather-move" aria-hidden="true"></i> Scroll sideways on smaller screens</span>
        </div>
        <div class="me-register-desktop me-register-scroll" role="region" aria-label="Filterable indicator register" tabindex="0">
            <table id="indicator-register-table" class="table me-register-table align-middle" data-indicator-register-table>
                <caption class="visually-hidden">Results framework indicators and their required measurement information</caption>
                <colgroup>
                    <col class="me-col-indicator">
                    <col class="me-col-measurement">
                    <col class="me-col-reporting">
                    <col class="me-col-responsible">
                    <col class="me-col-actions">
                </colgroup>
                <thead>
                    <tr>
                        <th>Indicator</th>
                        <th>Measurement</th>
                        <th>Reporting &amp; evidence</th>
                        <th>Responsible person</th>
                        <th class="text-end me-actions-cell">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($indicators as $indicator)
                        @php
                            $setupTarget = $indicator->setupTarget;
                            $unitLabel = $indicator->unit?->name ?: 'Unit not set';
                            $dataCollectionMethod = (string) ($indicator->data_collection_method ?: $indicator->methodology ?: '');
                            $requirements = $indicator->disaggregationRequirements;
                            $disaggregations = $indicator->disaggregations->keyBy('level');
                            $disaggregationChain = $requirements->pluck('dimension.name')->filter()->join(' × ')
                                ?: $indicator->disaggregationChain();
                            $responsibleNames = collect([$indicator->responsiblePerson?->name])->filter();
                            if ($responsibleNames->isEmpty()) {
                                $responsibleNames = collect(json_decode((string) $indicator->responsible_party, true) ?: [])
                                    ->map(fn ($id) => $usersById->get((string) $id)?->name)
                                    ->filter()
                                    ->values();
                            }
                            $responsibleId = (string) ($indicator->responsible_user_id
                                ?: collect(json_decode((string) $indicator->responsible_party, true) ?: [])->first()
                                ?: 'unassigned');
                        @endphp
                        <tr
                            data-component-id="{{ $indicator->project_component_id }}"
                            data-unit-id="{{ $indicator->unit_id }}"
                            data-frequency-id="{{ $indicator->frequency_of_reporting_id }}"
                            data-responsible-id="{{ $responsibleId }}"
                        >
                            <td class="me-indicator-cell">
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
                            <td class="me-measurement-cell">
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
                            <td class="me-reporting-cell">
                                <div class="me-reporting-chips">
                                    <span class="me-chip"><i class="feather-calendar"></i>{{ $indicator->frequency?->indicatorCadenceLabel() ?: 'Frequency not set' }}</span>
                                    <span class="me-chip"><i class="feather-filter"></i>{{ $disaggregationChain !== '' ? $disaggregationChain : 'No disaggregation' }}</span>
                                </div>
                                <div class="me-muted text-break">
                                    <i class="feather-database me-1"></i>{{ $dataCollectionMethod !== '' ? $dataCollectionMethod : 'Collection method not set' }}
                                </div>
                                <div class="me-muted text-break mt-1">
                                    <i class="feather-check-square me-1"></i>
                                    @if ($indicator->meansOfVerificationFolder)
                                        <a href="{{ route('budget.me.rebuild.knowledge-repository', ['folder_id' => $indicator->meansOfVerificationFolder->id]) }}">
                                            {{ $indicator->meansOfVerificationFolder->name }}
                                        </a>
                                    @else
                                        Means of Verification not linked
                                    @endif
                                </div>
                            </td>
                            <td class="me-responsible-cell">
                                @if ($responsibleNames->isNotEmpty())
                                    <div class="me-person">
                                        <span class="me-person-icon" aria-hidden="true"><i class="feather-user"></i></span>
                                        <span>
                                            <strong>{{ $responsibleNames->join(', ') }}</strong>
                                            @if ($indicator->responsiblePerson?->email)
                                                <small>{{ $indicator->responsiblePerson->email }}</small>
                                            @endif
                                        </span>
                                    </div>
                                @else
                                    <span class="me-muted">Not assigned</span>
                                @endif
                            </td>
                            <td class="me-actions-cell">
                                @can('me.configuration.manage')
                                    <div class="me-row-actions">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary"
                                            data-disaggregation-open
                                            data-indicator-name="{{ $indicator->name }}"
                                            data-action="{{ route('budget.me.indicators.disaggregations.update', $indicator) }}"
                                             data-dimensions="{{ $requirements->pluck('dimension_id')->implode(',') }}"
                                             data-required-dimensions="{{ $requirements->where('is_required', true)->pluck('dimension_id')->implode(',') }}"
                                             data-numeric-dimensions="{{ $requirements->where('collect_numeric_value', true)->pluck('dimension_id')->implode(',') }}"
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
                    $unitLabel = $indicator->unit?->name ?: 'Unit not set';
                    $dataCollectionMethod = (string) ($indicator->data_collection_method ?: $indicator->methodology ?: '');
                    $requirements = $indicator->disaggregationRequirements;
                    $disaggregations = $indicator->disaggregations->keyBy('level');
                    $disaggregationChain = $requirements->pluck('dimension.name')->filter()->join(' × ')
                        ?: $indicator->disaggregationChain();
                    $responsibleNames = collect([$indicator->responsiblePerson?->name])->filter();
                            if ($responsibleNames->isEmpty()) {
                                $responsibleNames = collect(json_decode((string) $indicator->responsible_party, true) ?: [])
                                    ->map(fn ($id) => $usersById->get((string) $id)?->name)
                                    ->filter()
                                    ->values();
                            }
                            $responsibleId = (string) ($indicator->responsible_user_id
                                ?: collect(json_decode((string) $indicator->responsible_party, true) ?: [])->first()
                                ?: 'unassigned');
                            $mobileSearchText = collect([
                                $indicator->indicator_code,
                                $indicator->name,
                                $indicator->definitions,
                                $indicator->projectComponent?->project_id,
                                $indicator->projectComponent?->name,
                                $indicator->frequency?->indicatorCadenceLabel(),
                                $indicator->unit?->name,
                                $dataCollectionMethod,
                                $disaggregationChain,
                                $indicator->meansOfVerificationFolder?->name,
                                $responsibleNames->join(' '),
                            ])->filter()->join(' ');
                        @endphp
                <article
                    class="me-mobile-card"
                    data-indicator-mobile-card
                    data-component-id="{{ $indicator->project_component_id }}"
                    data-unit-id="{{ $indicator->unit_id }}"
                    data-frequency-id="{{ $indicator->frequency_of_reporting_id }}"
                    data-responsible-id="{{ $responsibleId }}"
                    data-search="{{ str($mobileSearchText)->lower() }}"
                >
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
                        @if ($indicator->meansOfVerificationFolder)
                            <a href="{{ route('budget.me.rebuild.knowledge-repository', ['folder_id' => $indicator->meansOfVerificationFolder->id]) }}">
                                {{ $indicator->meansOfVerificationFolder->name }}
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
                                 data-dimensions="{{ $requirements->pluck('dimension_id')->implode(',') }}"
                                 data-required-dimensions="{{ $requirements->where('is_required', true)->pluck('dimension_id')->implode(',') }}"
                                 data-numeric-dimensions="{{ $requirements->where('collect_numeric_value', true)->pluck('dimension_id')->implode(',') }}"
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

    @endif
</section>
