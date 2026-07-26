@extends('layouts.app')

@section('title', 'Bi-Annual Site Visits')
@section('lean_admin_scripts', '1')

@push('styles')
    @include('biannual-site-visits.partials.styles')
@endpush

@section('content')
    <main class="nxl-container">
        <div class="nxl-content basv-page">
            <div class="basv-hero">
                <div>
                    <span class="basv-eyebrow"><i class="feather-activity"></i> Monitoring &amp; Evaluation</span>
                    <h1>Bi-Annual Site Visits</h1>
                    <p>Plan H1 and H2 monitoring visits, coordinate flexible assessment teams, and complete the
                        configurable Think Tank questionnaire in one auditable workflow.</p>
                </div>
                <div class="basv-hero-actions">
                    @canany(['biannual_site_visits.view', 'biannual_site_visits.approve', 'biannual_site_visits.export'])
                        <a href="{{ route('biannual-site-visits.reports.submitted') }}" class="basv-btn basv-btn-light">
                            <i class="feather-file-text"></i> Submitted Reports
                        </a>
                    @endcanany
                    @can('biannual_site_visits.templates.manage')
                        <a href="{{ route('biannual-site-visits.templates.index') }}" class="basv-btn basv-btn-light">
                            <i class="feather-sliders"></i> Questionnaire Builder
                        </a>
                    @endcan
                    @can('biannual_site_visits.create')
                        <a href="{{ route('biannual-site-visits.create') }}" class="basv-btn basv-btn-light">
                            <i class="feather-plus"></i> Schedule Visit
                        </a>
                    @endcan
                </div>
            </div>

            @if (session('success'))
                <div class="basv-alert success"><i class="feather-check-circle me-1"></i>{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="basv-alert danger">
                    <strong>Please check the following:</strong>
                    <ul class="mb-0 mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="basv-stats">
                <div class="basv-stat">
                    <span class="basv-stat-icon"><i class="feather-calendar"></i></span>
                    <div><strong>{{ number_format($stats['total'] ?? 0) }}</strong><span>Total visits</span></div>
                </div>
                <div class="basv-stat">
                    <span class="basv-stat-icon"><i class="feather-edit-3"></i></span>
                    <div><strong>{{ number_format($stats['active'] ?? 0) }}</strong><span>In progress</span></div>
                </div>
                <div class="basv-stat">
                    <span class="basv-stat-icon"><i class="feather-clock"></i></span>
                    <div><strong>{{ number_format($stats['submitted'] ?? 0) }}</strong><span>Awaiting review</span></div>
                </div>
                <div class="basv-stat">
                    <span class="basv-stat-icon"><i class="feather-check-circle"></i></span>
                    <div><strong>{{ number_format($stats['approved'] ?? 0) }}</strong><span>Approved</span></div>
                </div>
            </div>

            <div class="basv-card">
                <div class="basv-card-head">
                    <h2><i class="feather-map-pin me-2"></i>Monitoring visit register</h2>
                    <form method="GET" class="d-flex gap-2">
                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All statuses</option>
                            @foreach (['draft' => 'Draft', 'returned' => 'Returned', 'submitted' => 'Submitted', 'approved' => 'Approved'] as $value => $label)
                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <select name="cycle_year" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All years</option>
                            @foreach ($years ?? [] as $year)
                                <option value="{{ $year }}" @selected((string) request('cycle_year') === (string) $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>

                <div class="table-responsive">
                    @if ($visits->isEmpty())
                        <div class="basv-empty">
                            <i class="feather-map"></i>
                            <strong>No bi-annual visits found</strong>
                            <div class="mt-1">Schedule the first H1 or H2 monitoring visit to begin.</div>
                        </div>
                    @else
                        <table class="basv-table">
                            <thead>
                                <tr>
                                    <th>Visit</th>
                                    <th>Think Tank</th>
                                    <th>Cycle</th>
                                    <th>Team</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($visits as $visit)
                                    @php
                                        $status = $visit->siteVisit?->status ?: 'draft';
                                        $progress = (float) ($visit->completion_percentage ?? 0);
                                    @endphp
                                    <tr>
                                        <td>
                                            <a class="basv-record-title"
                                                href="{{ route('biannual-site-visits.show', $visit) }}">
                                                {{ $visit->title ?: 'Monitoring Site Visit' }}
                                            </a>
                                            <span class="basv-record-meta">{{ $visit->reference_number }}</span>
                                        </td>
                                        <td>
                                            <strong>{{ $visit->thinkTank?->name ?? '—' }}</strong>
                                            <span class="basv-record-meta">{{ $visit->thinkTank?->country }}</span>
                                        </td>
                                        <td>
                                            <strong>{{ $visit->cycleLabel() }}</strong>
                                            <span class="basv-record-meta">
                                                {{ optional($visit->starts_on)->format('d M Y') }}
                                                @if ($visit->ends_on)
                                                    – {{ $visit->ends_on->format('d M Y') }}
                                                @endif
                                            </span>
                                        </td>
                                        <td>
                                            <strong>{{ $visit->siteVisit?->group?->members?->count() ?? 0 }} members</strong>
                                            <span class="basv-record-meta">
                                                Lead: {{ $visit->siteVisit?->group?->leader?->name ?? 'Not set' }}
                                            </span>
                                        </td>
                                        <td style="min-width: 130px">
                                            <div class="d-flex justify-content-between mb-1">
                                                <small>{{ round($progress) }}%</small>
                                            </div>
                                            <div class="basv-progress"><span style="width: {{ min(100, $progress) }}%"></span></div>
                                        </td>
                                        <td><span class="basv-badge {{ $status }}">{{ str_replace('_', ' ', $status) }}</span></td>
                                        <td class="text-end">
                                            <a href="{{ route('biannual-site-visits.show', $visit) }}"
                                                class="basv-btn basv-btn-ghost">
                                                Open <i class="feather-arrow-right"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if (method_exists($visits, 'links'))
                            <div class="p-3">{{ $visits->withQueryString()->links() }}</div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </main>
@endsection
