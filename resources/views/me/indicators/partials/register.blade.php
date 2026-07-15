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
                    placeholder="Search ID, name or source"
                >
            </div>
            <button type="submit" class="btn btn-outline-success">Search</button>
            @if (filled($search ?? request('q')))
                <a href="{{ route('budget.me.indicators.index') }}" class="btn btn-light border">Clear</a>
            @endif
        </form>
    </div>

    @if ($indicators->isEmpty())
        <div class="me-empty-state">
            <span class="me-empty-icon"><i class="feather-target" aria-hidden="true"></i></span>
            <h3 class="h6 fw-bold mb-2">
                {{ filled($search ?? request('q')) ? 'No matching indicators' : 'No indicators have been added' }}
            </h3>
            <p class="me-muted small mb-3">
                {{ filled($search ?? request('q'))
                    ? 'Try a different ID, name, definition or data source.'
                    : 'Create the first indicator to begin your results framework.' }}
            </p>
            @can('me.configuration.manage')
                @if (!filled($search ?? request('q')))
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
                        <th style="width: 31%">Indicator</th>
                        <th style="width: 18%">Measurement</th>
                        <th style="width: 22%">Reporting</th>
                        <th style="width: 17%">Responsible person</th>
                        <th class="text-end" style="width: 12%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($indicators as $indicator)
                        @php
                            $setupTarget = $indicator->setupTarget;
                            $unitLabel = $indicator->unit?->symbol ?: ($indicator->unit?->name ?: 'Unit not set');
                            $source = (string) ($indicator->primary_source ?? '');
                            if (preg_match('/^(file_location|link|external_system_connector):(.*)$/s', $source, $sourceParts)) {
                                $source = trim($sourceParts[2]);
                            }
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
                                    <span class="me-chip"><i class="feather-calendar"></i>{{ $indicator->frequency?->name ?: 'Frequency not set' }}</span>
                                </div>
                                <div class="me-muted text-break">
                                    <i class="feather-database me-1"></i>{{ $source !== '' ? $source : 'Data source not set' }}
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
                    $source = (string) ($indicator->primary_source ?? '');
                    if (preg_match('/^(file_location|link|external_system_connector):(.*)$/s', $source, $sourceParts)) {
                        $source = trim($sourceParts[2]);
                    }
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
                            <strong>{{ $indicator->frequency?->name ?: 'Not set' }}</strong>
                        </div>
                        <div class="me-mobile-fact">
                            <small>Responsible</small>
                            <strong>{{ $responsibleNames->isNotEmpty() ? $responsibleNames->join(', ') : 'Not assigned' }}</strong>
                        </div>
                    </div>

                    <div class="me-muted text-break mb-3"><i class="feather-database me-1"></i>{{ $source !== '' ? $source : 'Data source not set' }}</div>

                    @can('me.configuration.manage')
                        <div class="me-row-actions justify-content-start">
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
