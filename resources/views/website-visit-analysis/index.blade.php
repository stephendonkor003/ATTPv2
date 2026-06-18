@extends('layouts.app')

@section('title', 'Website Visit Analysis')

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/flagicon.min.css') }}">
    <style>
        .wva-shell {
            color: #0f172a;
        }

        .wva-hero {
            background:
                radial-gradient(circle at 12% 18%, rgba(34, 197, 94, .22), transparent 30%),
                radial-gradient(circle at 88% 12%, rgba(14, 165, 233, .24), transparent 32%),
                linear-gradient(135deg, #0f172a 0%, #155e75 48%, #166534 100%);
            border-radius: 10px;
            color: #f8fafc;
            padding: 24px;
            box-shadow: 0 18px 34px rgba(15, 23, 42, .22);
        }

        .wva-hero h4,
        .wva-hero p {
            color: #f8fafc;
        }

        .wva-eyebrow {
            color: #bbf7d0;
            font-size: .76rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .wva-filter,
        .wva-card,
        .wva-stat {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, .07);
        }

        .wva-filter {
            overflow: hidden;
        }

        .wva-filter-header {
            align-items: center;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            gap: 12px;
            justify-content: space-between;
            padding: 14px 18px;
        }

        .wva-filter-header h5 {
            font-size: 1rem;
            font-weight: 800;
            margin: 0;
        }

        .wva-filter-body {
            padding: 18px;
        }

        .wva-filter-actions {
            align-items: flex-end;
            display: flex;
            gap: 10px;
            height: 100%;
        }

        .wva-filter-actions .btn {
            min-height: 38px;
        }

        .wva-stat {
            padding: 16px;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .wva-stat::after {
            content: "";
            position: absolute;
            right: -24px;
            top: -28px;
            width: 86px;
            height: 86px;
            border-radius: 999px;
            background: var(--stat-glow, rgba(14, 165, 233, .16));
        }

        .wva-stat-icon {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--stat-bg, #e0f2fe);
            color: var(--stat-color, #0369a1);
            font-size: 1.1rem;
        }

        .wva-stat .value {
            font-size: 1.55rem;
            font-weight: 800;
            line-height: 1.15;
        }

        .wva-card {
            overflow: hidden;
        }

        .wva-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 18px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .wva-card-header h5 {
            margin: 0;
            font-size: 1rem;
            font-weight: 800;
        }

        .wva-map {
            width: 100%;
            height: clamp(470px, 62vh, 680px);
            min-height: 470px;
            background: #dbeafe;
            position: relative;
        }

        .wva-map.leaflet-container {
            font-family: inherit;
        }

        .wva-map .leaflet-control-attribution {
            font-size: .68rem;
        }

        .wva-map-marker {
            align-items: center;
            border: 2px solid #ffffff;
            border-radius: 999px;
            color: #ffffff;
            display: inline-flex;
            font-size: .68rem;
            font-weight: 800;
            height: 22px;
            justify-content: center;
            line-height: 1;
            min-width: 26px;
            padding: 0 7px;
            box-shadow: 0 8px 18px rgba(15, 23, 42, .28);
            text-shadow: 0 1px 2px rgba(15, 23, 42, .35);
            white-space: nowrap;
        }

        .wva-map-marker.has-data {
            background: #16a34a;
        }

        .wva-map-marker.no-data {
            background: #dc2626;
            height: 20px;
            min-width: 24px;
        }

        .wva-map-marker.is-highlighted {
            background: #16a34a;
            font-size: .76rem;
            height: 26px;
            min-width: 34px;
            box-shadow:
                0 0 0 6px rgba(22, 163, 74, .2),
                0 12px 24px rgba(22, 101, 52, .32);
        }

        .wva-map-marker.is-dimmed {
            opacity: .38;
        }

        .wva-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 12px 18px;
            border-top: 1px solid #e2e8f0;
            background: #ffffff;
        }

        .wva-legend span {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #475569;
            font-size: .84rem;
            font-weight: 700;
        }

        .wva-dot {
            width: 11px;
            height: 11px;
            border-radius: 999px;
            display: inline-block;
            border: 2px solid #ffffff;
            box-shadow: 0 0 0 1px rgba(15, 23, 42, .12);
        }

        .wva-flag {
            width: 26px;
            height: 18px;
            border-radius: 2px;
            box-shadow: 0 0 0 1px rgba(15, 23, 42, .14);
            flex: 0 0 auto;
        }

        .wva-country-cell {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            min-width: 0;
        }

        .wva-country-text {
            min-width: 0;
        }

        .wva-ip-code,
        .wva-visitor-code {
            color: #334155;
            background: #f1f5f9;
            border-radius: 4px;
            padding: 2px 5px;
        }

        .wva-popup-country {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .wva-popup-country .fi {
            width: 24px;
            height: 16px;
            border-radius: 2px;
            box-shadow: 0 0 0 1px rgba(15, 23, 42, .14);
        }

        .wva-popup-stat {
            color: #475569;
            line-height: 1.55;
        }

        .wva-filter .select2-container {
            width: 100% !important;
        }

        .wva-filter .select2-container--default .select2-selection--single {
            border: 1px solid #d1d5db;
            border-radius: .375rem;
            min-height: 38px;
        }

        .wva-filter .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 36px;
        }

        .wva-filter .select2-container--default .select2-selection--single .select2-selection__arrow {
            min-height: 36px;
        }

        .wva-select-option {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }

        .wva-select-option .fi {
            width: 22px;
            height: 15px;
            border-radius: 2px;
            box-shadow: 0 0 0 1px rgba(15, 23, 42, .14);
            flex: 0 0 auto;
        }

        .wva-select-option small {
            color: #64748b;
            display: block;
            font-size: .72rem;
            line-height: 1.15;
        }

        .wva-table-wrap {
            max-height: 420px;
            overflow: auto;
        }

        .wva-table th {
            color: #64748b;
            font-size: .74rem;
            letter-spacing: .04em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .wva-table td {
            vertical-align: middle;
        }

        .wva-url {
            max-width: 430px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .wva-empty {
            color: #64748b;
            padding: 24px;
            text-align: center;
        }

        @media (max-width: 767.98px) {
            .wva-filter-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .wva-filter-actions {
                flex-direction: column;
            }

            .wva-filter-actions .btn {
                width: 100%;
            }

            .wva-map {
                height: 420px;
                min-height: 340px;
            }

            .wva-url {
                max-width: 240px;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $durationLabel = function ($seconds) {
            $seconds = max(0, (int) $seconds);
            if ($seconds >= 3600) {
                return intdiv($seconds, 3600) . 'h ' . intdiv($seconds % 3600, 60) . 'm';
            }
            if ($seconds >= 60) {
                return intdiv($seconds, 60) . 'm ' . ($seconds % 60) . 's';
            }
            return $seconds . 's';
        };

        $selectedCountry = $countries->firstWhere('iso2_code', $filters['country']);
        $flagCode = function ($iso2) {
            $code = strtolower((string) $iso2);

            return preg_match('/^[a-z]{2}$/', $code) ? $code : 'xx';
        };
        $countryOptions = $countries->map(function ($country) use ($flagCode) {
            $iso2 = strtoupper((string) $country->iso2_code);

            return [
                'id' => $iso2,
                'text' => $country->name . ' (' . $iso2 . ')' . ($country->continent ? ' - ' . $country->continent : ''),
                'name' => (string) $country->name,
                'code' => $iso2,
                'continent' => (string) ($country->continent ?? ''),
                'flag' => $flagCode($country->iso2_code),
            ];
        })->values();
    @endphp

    <div class="nxl-container wva-shell">
        <div class="wva-hero mb-4 d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
            <div>
                <div class="wva-eyebrow mb-2">Website Visit Analysis</div>
                <h4 class="fw-bold mb-2">Public website traffic dashboard</h4>
                <p class="mb-0 text-white-50">
                    Track visits by URL, country, continent, active sessions, and the minutes or hours visitors spend on the website.
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('website-visit-analysis.activity') }}" class="btn btn-light">
                    <i class="feather-list me-1"></i> Activity Performed
                </a>
                <a href="{{ route('landing.index') }}" class="btn btn-outline-light" target="_blank" rel="noopener">
                    <i class="feather-external-link me-1"></i> Open Website
                </a>
            </div>
        </div>

        <form class="wva-filter mb-4" method="GET" action="{{ route('website-visit-analysis.index') }}">
            <div class="wva-filter-header">
                <div>
                    <h5>Dashboard Filters</h5>
                    <div class="text-muted small">Filter traffic by continent and country.</div>
                </div>
                <span class="badge bg-light text-success border">Live map preview</span>
            </div>
            <div class="wva-filter-body">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label fw-semibold">Continent</label>
                        <select class="form-select" name="continent" id="wvaContinentSelect">
                            <option value="">All continents</option>
                            @foreach ($continents as $continent)
                                <option value="{{ $continent }}" @selected($filters['continent'] === $continent)>{{ $continent }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-5 col-md-6">
                        <label class="form-label fw-semibold">Country</label>
                        <select class="form-select wva-country-select" name="country" id="wvaCountrySelect">
                            <option value="">All countries</option>
                            @foreach ($countries as $country)
                                <option value="{{ $country->iso2_code }}" data-continent="{{ $country->continent }}" data-name="{{ $country->name }}" data-flag="{{ $flagCode($country->iso2_code) }}" @selected($filters['country'] === $country->iso2_code)>
                                    {{ $country->name }} ({{ $country->iso2_code }}){{ $country->continent ? ' - ' . $country->continent : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label fw-semibold d-none d-lg-block">&nbsp;</label>
                        <div class="wva-filter-actions">
                            <button class="btn btn-primary flex-fill" type="submit">
                                <i class="feather-filter me-1"></i> Apply Filter
                            </button>
                            <a class="btn btn-outline-secondary" href="{{ route('website-visit-analysis.index') }}">Clear</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        @if ($filters['continent'] || $selectedCountry)
            <div class="alert alert-info border-0 shadow-sm">
                Showing visits
                @if ($filters['continent'])
                    for <strong>{{ $filters['continent'] }}</strong>
                @endif
                @if ($selectedCountry)
                    {{ $filters['continent'] ? 'and' : 'for' }}
                    <strong>
                        <span class="fi fi-{{ $flagCode($selectedCountry->iso2_code) }} wva-flag me-1"></span>
                        {{ $selectedCountry->name }}
                    </strong>
                @endif
            </div>
        @endif

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="wva-stat" style="--stat-bg:#dcfce7;--stat-color:#166534;--stat-glow:rgba(34,197,94,.16);">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <div class="text-muted small fw-semibold">Total Visits</div>
                            <div class="value">{{ number_format($summary['total_visits']) }}</div>
                        </div>
                        <span class="wva-stat-icon"><i class="feather-globe"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="wva-stat" style="--stat-bg:#e0f2fe;--stat-color:#0369a1;--stat-glow:rgba(14,165,233,.18);">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <div class="text-muted small fw-semibold">Unique Visitors</div>
                            <div class="value">{{ number_format($summary['unique_visitors']) }}</div>
                        </div>
                        <span class="wva-stat-icon"><i class="feather-users"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="wva-stat" style="--stat-bg:#fef3c7;--stat-color:#92400e;--stat-glow:rgba(245,158,11,.16);">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <div class="text-muted small fw-semibold">Page Views</div>
                            <div class="value">{{ number_format($summary['page_views']) }}</div>
                        </div>
                        <span class="wva-stat-icon"><i class="feather-eye"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="wva-stat" style="--stat-bg:#fee2e2;--stat-color:#991b1b;--stat-glow:rgba(239,68,68,.13);">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <div class="text-muted small fw-semibold">Avg Time</div>
                            <div class="value">{{ $durationLabel($summary['average_duration_seconds']) }}</div>
                        </div>
                        <span class="wva-stat-icon"><i class="feather-clock"></i></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-8">
                <div class="wva-card h-100">
                    <div class="wva-card-header">
                        <div>
                            <h5>World Visit Map</h5>
                            <div class="text-muted small">Marker numbers show visit counts. Selected continent or country is highlighted in green.</div>
                        </div>
                        <span class="badge bg-light text-dark border">{{ number_format($summary['active_visitors']) }} active now</span>
                    </div>
                    <div id="visitWorldMap" class="wva-map"></div>
                    <div class="wva-legend">
                        <span><i class="wva-dot" style="background:#16a34a;"></i>{{ number_format($mapCounts['with_data']) }} countries with data</span>
                        <span><i class="wva-dot" style="background:#dc2626;"></i>{{ number_format($mapCounts['without_data']) }} countries without data</span>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="wva-card h-100">
                    <div class="wva-card-header">
                        <h5>Countries</h5>
                        <span class="text-muted small">Visit count and time</span>
                    </div>
                    <div class="wva-table-wrap">
                        <table class="table table-hover align-middle mb-0 wva-table">
                            <thead>
                                <tr>
                                    <th>Country</th>
                                    <th class="text-end">Visitors</th>
                                    <th class="text-end">Visits</th>
                                    <th class="text-end">Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($countryRows as $row)
                                    <tr>
                                        <td>
                                            <div class="wva-country-cell">
                                                <span class="fi fi-{{ $flagCode($row->country_iso2) }} wva-flag mt-1"></span>
                                                <div class="wva-country-text">
                                                    <div class="fw-bold">{{ $row->country_name ?: $row->country_iso2 }}</div>
                                                    <div class="text-muted small">
                                                        {{ $row->continent ?: 'Continent not set' }} - {{ number_format($row->unique_ips) }} unique IP{{ $row->unique_ips === 1 ? '' : 's' }}
                                                    </div>
                                                    <div class="text-muted small">
                                                        Sample IP:
                                                        <code class="wva-ip-code">{{ $row->sample_ip_address ?: 'Not captured' }}</code>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end fw-bold">{{ number_format($row->visitors) }}</td>
                                        <td class="text-end fw-bold">{{ number_format($row->visits) }}</td>
                                        <td class="text-end">{{ $durationLabel($row->total_duration_seconds) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="wva-empty">No country visit data has been recorded yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-7">
                <div class="wva-card h-100">
                    <div class="wva-card-header">
                        <h5>Visits and Page Views</h5>
                        <span class="text-muted small">Last 14 days</span>
                    </div>
                    <div class="p-3">
                        <div id="visitsTrendChart" style="min-height: 310px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="wva-card h-100">
                    <div class="wva-card-header">
                        <h5>Continents</h5>
                        <span class="text-muted small">Traffic distribution</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 wva-table">
                            <thead>
                                <tr>
                                    <th>Continent</th>
                                    <th class="text-end">Visits</th>
                                    <th class="text-end">Page Views</th>
                                    <th class="text-end">Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($continentRows as $row)
                                    <tr>
                                        <td class="fw-bold">{{ $row->continent }}</td>
                                        <td class="text-end">{{ number_format($row->visits) }}</td>
                                        <td class="text-end">{{ number_format($row->page_views) }}</td>
                                        <td class="text-end">{{ $durationLabel($row->total_duration_seconds) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="wva-empty">No continent data is available yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-7">
                <div class="wva-card h-100">
                    <div class="wva-card-header">
                        <h5>Top URLs</h5>
                        <span class="text-muted small">Most viewed pages</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 wva-table">
                            <thead>
                                <tr>
                                    <th>URL</th>
                                    <th class="text-end">Views</th>
                                    <th class="text-end">Visits</th>
                                    <th class="text-end">Avg Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($topPages as $page)
                                    <tr>
                                        <td>
                                            <div class="fw-bold wva-url" title="{{ $page->title ?: $page->path }}">{{ $page->title ?: $page->path }}</div>
                                            <a class="text-muted small wva-url d-block" href="{{ $page->url }}" target="_blank" rel="noopener">{{ $page->path }}</a>
                                        </td>
                                        <td class="text-end fw-bold">{{ number_format($page->views) }}</td>
                                        <td class="text-end">{{ number_format($page->visits) }}</td>
                                        <td class="text-end">{{ $durationLabel($page->average_duration_seconds) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="wva-empty">No URL activity has been captured yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="wva-card h-100">
                    <div class="wva-card-header">
                        <h5>Recent Activity</h5>
                        <a href="{{ route('website-visit-analysis.activity') }}" class="btn btn-sm btn-outline-primary">View all</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 wva-table">
                            <thead>
                                <tr>
                                    <th>Activity</th>
                                    <th>Country / IP</th>
                                    <th class="text-end">Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentActivities as $activity)
                                    <tr>
                                        <td>
                                            <div class="fw-bold">{{ str_replace('_', ' ', ucfirst($activity->activity_type)) }}</div>
                                            <div class="text-muted small wva-url">{{ $activity->path ?: $activity->url }}</div>
                                        </td>
                                        <td>
                                            <div class="wva-country-cell">
                                                <span class="fi fi-{{ $flagCode($activity->visit?->country_iso2) }} wva-flag mt-1"></span>
                                                <div class="wva-country-text">
                                                    <div class="fw-bold">{{ $activity->visit?->country_name ?: 'Unknown' }}</div>
                                                    <div class="text-muted small">
                                                        Visitor:
                                                        <code class="wva-visitor-code">{{ $activity->visit?->visitor_uuid ? \Illuminate\Support\Str::limit((string) $activity->visit->visitor_uuid, 18) : 'Not captured' }}</code>
                                                    </div>
                                                    <div class="text-muted small">
                                                        IP:
                                                        <code class="wva-ip-code">{{ $activity->visit?->ip_address ?: 'Not captured' }}</code>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end">{{ optional($activity->occurred_at)->format('d M, H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="wva-empty">No visitor activity yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('admin/assets/vendors/js/select2.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const markers = @json($mapMarkers);
            const trend = @json($dailyTrend);
            const countries = @json($countryOptions);
            const filters = @json($filters);
            const continentSelect = document.getElementById('wvaContinentSelect');
            const countrySelect = document.getElementById('wvaCountrySelect');
            let applyMapHighlight = function () {};

            function escapeHtml(value) {
                return String(value || '').replace(/[&<>"']/g, function (char) {
                    return {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;'
                    }[char];
                });
            }

            function currentContinent() {
                return continentSelect ? continentSelect.value : (filters.continent || '');
            }

            function currentCountry() {
                return countrySelect ? String(countrySelect.value || '').toUpperCase() : String(filters.country || '').toUpperCase();
            }

            function countryMatchesContinent(country, continent) {
                return !continent || country.continent === continent;
            }

            function makeCountryOption(country, selectedValue) {
                const option = new Option(country.text, country.id, false, String(country.id || '').toUpperCase() === selectedValue);
                option.dataset.continent = country.continent || '';
                option.dataset.flag = country.flag || 'xx';
                option.dataset.name = country.name || country.text;
                option.dataset.code = country.code || country.id;

                return option;
            }

            function renderCountryOptions(preferredValue) {
                if (!countrySelect) {
                    return '';
                }

                const continent = currentContinent();
                const availableCountries = countries.filter(function (country) {
                    return countryMatchesContinent(country, continent);
                });
                const preferred = String(preferredValue || '').toUpperCase();
                const selectedValue = availableCountries.some(function (country) {
                    return String(country.id || '').toUpperCase() === preferred;
                }) ? preferred : '';

                countrySelect.innerHTML = '';

                const allOption = new Option('All countries', '', false, selectedValue === '');
                allOption.dataset.continent = '';
                allOption.dataset.flag = 'xx';
                allOption.dataset.name = 'All countries';
                allOption.dataset.code = '';
                countrySelect.appendChild(allOption);

                availableCountries.forEach(function (country) {
                    countrySelect.appendChild(makeCountryOption(country, selectedValue));
                });

                countrySelect.value = selectedValue;

                return selectedValue;
            }

            function flagOptionTemplate(option, includeContinent) {
                if (!option.element) {
                    return option.text;
                }

                const flag = option.element.dataset.flag || 'xx';
                const name = option.element.dataset.name || option.text || 'All countries';
                const code = option.element.dataset.code || option.id || '';
                const continent = option.element.dataset.continent || '';
                const row = document.createElement('span');
                row.className = 'wva-select-option';

                const flagEl = document.createElement('span');
                flagEl.className = 'fi fi-' + flag;
                row.appendChild(flagEl);

                const label = document.createElement('span');
                const title = document.createElement('span');
                title.textContent = code ? name + ' (' + code + ')' : name;
                label.appendChild(title);

                if (includeContinent && continent) {
                    const meta = document.createElement('small');
                    meta.textContent = continent;
                    label.appendChild(meta);
                }

                row.appendChild(label);

                return window.jQuery ? window.jQuery(row) : row;
            }

            function initializeCountrySelect() {
                if (!countrySelect || !window.jQuery || !window.jQuery.fn || typeof window.jQuery.fn.select2 !== 'function') {
                    return;
                }

                const select = window.jQuery(countrySelect);
                if (select.hasClass('select2-hidden-accessible')) {
                    select.select2('destroy');
                }

                select.select2({
                    width: '100%',
                    templateResult: function (option) {
                        return flagOptionTemplate(option, true);
                    },
                    templateSelection: function (option) {
                        return flagOptionTemplate(option, false);
                    }
                });
            }

            function refreshCountrySelect() {
                if (!countrySelect || !window.jQuery || !window.jQuery.fn || typeof window.jQuery.fn.select2 !== 'function') {
                    return;
                }

                window.jQuery(countrySelect).trigger('change.select2');
            }

            function compactNumber(value) {
                const number = Number(value || 0);

                if (number >= 1000000) {
                    return (number / 1000000).toFixed(number >= 10000000 ? 0 : 1).replace(/\.0$/, '') + 'M';
                }

                if (number >= 1000) {
                    return (number / 1000).toFixed(number >= 10000 ? 0 : 1).replace(/\.0$/, '') + 'K';
                }

                return String(number);
            }

            renderCountryOptions(filters.country || '');
            initializeCountrySelect();

            if (window.L && document.getElementById('visitWorldMap')) {
                const worldBounds = L.latLngBounds([[-62, -180], [82, 180]]);
                const map = L.map('visitWorldMap', {
                    center: [18, 0],
                    zoom: 1,
                    minZoom: 0,
                    maxZoom: 9,
                    zoomSnap: .25,
                    zoomDelta: .5,
                    scrollWheelZoom: false,
                    maxBounds: [[-85, -190], [85, 190]],
                    maxBoundsViscosity: .75
                });

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors',
                    noWrap: true
                }).addTo(map);

                const markerInstances = [];
                const highlightLayer = L.layerGroup().addTo(map);

                function markerIcon(marker, highlighted, dimmed) {
                    const hasData = Boolean(marker.hasData);
                    const label = compactNumber(marker.visits);
                    const width = Math.max(highlighted ? 34 : 26, (label.length * (highlighted ? 8 : 7)) + (highlighted ? 22 : 18));
                    const height = highlighted ? 26 : (hasData ? 22 : 20);
                    const classes = [
                        'wva-map-marker',
                        hasData ? 'has-data' : 'no-data',
                        highlighted ? 'is-highlighted' : '',
                        dimmed ? 'is-dimmed' : ''
                    ].filter(Boolean).join(' ');

                    return L.divIcon({
                        className: '',
                        html: '<span class="' + classes + '">' + label + '</span>',
                        iconSize: [width, height],
                        iconAnchor: [width / 2, height / 2],
                        popupAnchor: [0, -Math.ceil(height / 2)]
                    });
                }

                function markerPopup(marker) {
                    const hasData = Boolean(marker.hasData);
                    const countryCode = /^[a-z]{2}$/i.test(marker.code || '') ? String(marker.code).toLowerCase() : 'xx';

                    return '<div class="wva-popup-country">' +
                        '<span class="fi fi-' + countryCode + '"></span>' +
                        '<span>' + escapeHtml(marker.name) + '</span>' +
                        '</div>' +
                        (hasData
                            ? '<div class="wva-popup-stat">' +
                                'Continent: ' + escapeHtml(marker.continent || 'Not set') + '<br>' +
                                'Visits: ' + Number(marker.visits || 0).toLocaleString() + '<br>' +
                                'Visitors: ' + Number(marker.visitors || 0).toLocaleString() + '<br>' +
                                'Unique IPs: ' + Number(marker.uniqueIps || 0).toLocaleString() + '<br>' +
                                'Page views: ' + Number(marker.pageViews || 0).toLocaleString() + '<br>' +
                                'Sample IP: ' + escapeHtml(marker.sampleIpAddress || 'Not captured') + '<br>' +
                                'Time: ' + escapeHtml(marker.durationLabel) +
                            '</div>'
                            : '<div class="wva-popup-stat">Continent: ' + escapeHtml(marker.continent || 'Not set') + '<br>No recorded visits</div>');
                }

                function markerIsHighlighted(marker) {
                    const country = currentCountry();
                    const continent = currentContinent();

                    if (country) {
                        return String(marker.code || '').toUpperCase() === country;
                    }

                    return Boolean(continent && marker.continent === continent);
                }

                markers.forEach(function (marker) {
                    if (!Array.isArray(marker.latLng) || marker.latLng.length !== 2) {
                        return;
                    }

                    const leafletMarker = L.marker(marker.latLng, {
                        icon: markerIcon(marker, false, false),
                        title: marker.name
                    }).addTo(map).bindPopup(markerPopup(marker));

                    markerInstances.push({
                        data: marker,
                        leafletMarker: leafletMarker
                    });
                });

                applyMapHighlight = function () {
                    const country = currentCountry();
                    const continent = currentContinent();
                    const hasSelection = Boolean(country || continent);
                    const highlighted = [];

                    highlightLayer.clearLayers();

                    markerInstances.forEach(function (item) {
                        const isHighlighted = markerIsHighlighted(item.data);
                        item.leafletMarker.setIcon(markerIcon(item.data, isHighlighted, hasSelection && !isHighlighted));

                        if (isHighlighted) {
                            highlighted.push(item);
                            L.circleMarker(item.data.latLng, {
                                radius: country ? 22 : 14,
                                color: '#16a34a',
                                fillColor: '#16a34a',
                                fillOpacity: .16,
                                opacity: .95,
                                weight: 3,
                                interactive: false
                            }).addTo(highlightLayer);
                        }
                    });

                    if (highlighted.length) {
                        const bounds = L.latLngBounds(highlighted.map(function (item) {
                            return item.data.latLng;
                        }));
                        const fitOptions = {
                            padding: [50, 50],
                            animate: false
                        };

                        if (country) {
                            fitOptions.maxZoom = 5;
                        } else if (continent) {
                            fitOptions.maxZoom = 3;
                        }

                        map.fitBounds(bounds, fitOptions);

                        if (country && highlighted[0]) {
                            highlighted[0].leafletMarker.openPopup();
                        }

                        return;
                    }

                    map.fitBounds(worldBounds, {
                        padding: [12, 12],
                        animate: false
                    });
                };

                applyMapHighlight();

                setTimeout(function () {
                    map.invalidateSize();
                    applyMapHighlight();
                }, 150);

                window.addEventListener('resize', function () {
                    map.invalidateSize();
                });
            }

            if (continentSelect) {
                continentSelect.addEventListener('change', function () {
                    renderCountryOptions(countrySelect ? countrySelect.value : '');
                    refreshCountrySelect();
                    applyMapHighlight();
                });
            }

            if (countrySelect) {
                countrySelect.addEventListener('change', function () {
                    applyMapHighlight();
                });
            }

            if (window.ApexCharts && document.querySelector('#visitsTrendChart')) {
                new ApexCharts(document.querySelector('#visitsTrendChart'), {
                    chart: {
                        type: 'area',
                        height: 310,
                        toolbar: { show: false }
                    },
                    series: [
                        { name: 'Visits', data: trend.visits },
                        { name: 'Page Views', data: trend.page_views }
                    ],
                    colors: ['#16a34a', '#0ea5e9'],
                    stroke: { curve: 'smooth', width: 3 },
                    fill: {
                        type: 'gradient',
                        gradient: { opacityFrom: .32, opacityTo: .06 }
                    },
                    dataLabels: { enabled: false },
                    xaxis: {
                        categories: trend.labels,
                        labels: { style: { colors: '#64748b' } }
                    },
                    yaxis: {
                        labels: {
                            style: { colors: '#64748b' },
                            formatter: function (value) { return Math.round(value); }
                        }
                    },
                    grid: {
                        borderColor: '#e2e8f0',
                        strokeDashArray: 4
                    },
                    legend: {
                        position: 'top',
                        horizontalAlign: 'left'
                    }
                }).render();
            }
        });
    </script>
@endpush
