@extends('layouts.app')

@section('title', 'Website Activity Performed')

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/flagicon.min.css') }}">
    <style>
        .wva-activity-shell {
            color: #0f172a;
        }

        .wva-activity-hero {
            background:
                radial-gradient(circle at 10% 20%, rgba(14, 165, 233, .22), transparent 30%),
                radial-gradient(circle at 88% 18%, rgba(34, 197, 94, .2), transparent 34%),
                linear-gradient(135deg, #0f172a 0%, #164e63 55%, #14532d 100%);
            border-radius: 10px;
            color: #f8fafc;
            padding: 24px;
            box-shadow: 0 18px 34px rgba(15, 23, 42, .22);
        }

        .wva-activity-hero h4,
        .wva-activity-hero p {
            color: #f8fafc;
        }

        .wva-filter,
        .wva-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, .07);
        }

        .wva-filter {
            padding: 16px;
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

        .wva-table th {
            color: #64748b;
            font-size: .74rem;
            letter-spacing: .04em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .wva-url {
            max-width: 560px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .wva-activity-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: .78rem;
            font-weight: 800;
            background: #e0f2fe;
            color: #0369a1;
        }

        .wva-activity-pill.exit {
            background: #fee2e2;
            color: #991b1b;
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

        .wva-empty {
            color: #64748b;
            padding: 28px;
            text-align: center;
        }

        @media (max-width: 767.98px) {
            .wva-url {
                max-width: 260px;
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
        $flagCode = function ($iso2) {
            $code = strtolower((string) $iso2);

            return preg_match('/^[a-z]{2}$/', $code) ? $code : 'xx';
        };
    @endphp

    <div class="nxl-container wva-activity-shell">
        <div class="wva-activity-hero mb-4 d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
            <div>
                <div class="text-uppercase small fw-bold text-white-50 mb-2">Activity Performed</div>
                <h4 class="fw-bold mb-2">Visitor URL activity log</h4>
                <p class="mb-0 text-white-50">
                    Review page views, exit events, country, URL, and session time captured from public website visits.
                </p>
            </div>
            <a href="{{ route('website-visit-analysis.index') }}" class="btn btn-light">
                <i class="feather-map me-1"></i> Visit Analysis
            </a>
        </div>

        <form class="wva-filter mb-4" method="GET" action="{{ route('website-visit-analysis.activity') }}">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Continent</label>
                    <select class="form-select" name="continent">
                        <option value="">All continents</option>
                        @foreach ($continents as $continent)
                            <option value="{{ $continent }}" @selected($filters['continent'] === $continent)>{{ $continent }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Country</label>
                    <select class="form-select" name="country">
                        <option value="">All countries</option>
                        @foreach ($countries as $country)
                            <option value="{{ $country->iso2_code }}" @selected($filters['country'] === $country->iso2_code)>
                                {{ $country->name }} ({{ $country->iso2_code }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Activity</label>
                    <select class="form-select" name="activity_type">
                        <option value="">All activity</option>
                        @foreach ($activityTypes as $type)
                            <option value="{{ $type }}" @selected($filters['activity_type'] === $type)>{{ str_replace('_', ' ', ucfirst($type)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">URL, IP, visitor, or country</label>
                    <input class="form-control" name="q" value="{{ $filters['q'] }}" placeholder="Search URL, IP, visitor, country, or title">
                </div>
                <div class="col-md-1 d-flex gap-2">
                    <button class="btn btn-primary w-100" type="submit" title="Filter">
                        <i class="feather-filter"></i>
                    </button>
                </div>
            </div>
            @if ($filters['continent'] || $filters['country'] || $filters['activity_type'] || $filters['q'])
                <div class="mt-3">
                    <a href="{{ route('website-visit-analysis.activity') }}" class="btn btn-sm btn-outline-secondary">Clear filters</a>
                </div>
            @endif
        </form>

        <div class="wva-card">
            <div class="wva-card-header">
                <div>
                    <h5>Performed Activities</h5>
                    <div class="text-muted small">{{ number_format($activities->total()) }} matching records</div>
                </div>
                <span class="badge bg-light text-dark border">Latest first</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 wva-table">
                    <thead>
                        <tr>
                            <th>Activity</th>
                            <th>URL</th>
                            <th>Country</th>
                            <th>Visitor / IP</th>
                            <th class="text-end">Session Time</th>
                            <th class="text-end">Occurred</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($activities as $activity)
                            <tr>
                                <td>
                                    <span class="wva-activity-pill {{ $activity->activity_type === 'exit' ? 'exit' : '' }}">
                                        <i class="feather-{{ $activity->activity_type === 'exit' ? 'log-out' : 'eye' }}"></i>
                                        {{ str_replace('_', ' ', ucfirst($activity->activity_type)) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold wva-url" title="{{ $activity->title ?: $activity->path }}">
                                        {{ $activity->title ?: ($activity->path ?: 'Untitled page') }}
                                    </div>
                                    @if ($activity->url)
                                        <a class="text-muted small wva-url d-block" href="{{ $activity->url }}" target="_blank" rel="noopener">{{ $activity->url }}</a>
                                    @else
                                        <div class="text-muted small">URL not captured</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="wva-country-cell">
                                        <span class="fi fi-{{ $flagCode($activity->visit?->country_iso2) }} wva-flag mt-1"></span>
                                        <div class="wva-country-text">
                                            <div class="fw-bold">{{ $activity->visit?->country_name ?: 'Unknown' }}</div>
                                            <div class="text-muted small">{{ $activity->visit?->continent ?: 'Continent not set' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-muted small">Visitor</div>
                                    <code class="small wva-visitor-code">{{ $activity->visit?->visitor_uuid ? \Illuminate\Support\Str::limit((string) $activity->visit->visitor_uuid, 24) : 'Not captured' }}</code>
                                    <div class="text-muted small mt-1">IP</div>
                                    <code class="small wva-ip-code">{{ $activity->visit?->ip_address ?: 'Not captured' }}</code>
                                </td>
                                <td class="text-end">{{ $durationLabel($activity->duration_seconds) }}</td>
                                <td class="text-end">
                                    <div class="fw-bold">{{ optional($activity->occurred_at)->format('d M Y') }}</div>
                                    <div class="text-muted small">{{ optional($activity->occurred_at)->format('H:i') }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="wva-empty">No performed activity has been recorded for these filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($activities->hasPages())
                <div class="card-footer bg-white">
                    {{ $activities->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
