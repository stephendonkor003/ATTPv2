@extends('layouts.app')

@section('title', 'Submitted Bi-Annual Site Visit Reports')
@section('lean_admin_scripts', '1')

@push('styles')
    @include('biannual-site-visits.partials.styles')
    <style>
        .basv-report-stats {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: .8rem;
            margin-bottom: 1rem;
        }

        .basv-report-filter-grid {
            display: grid;
            grid-template-columns: 1.4fr repeat(3, minmax(130px, .72fr));
            gap: .8rem;
        }

        .basv-report-filter-grid-secondary {
            display: grid;
            grid-template-columns: repeat(4, minmax(130px, 1fr)) auto;
            align-items: end;
            gap: .8rem;
            margin-top: .8rem;
        }

        .basv-report-filter-actions {
            display: flex;
            gap: .45rem;
        }

        .basv-report-filter-actions .basv-btn {
            white-space: nowrap;
        }

        .basv-report-score {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 58px;
            padding: .35rem .55rem;
            border: 1px solid #b8dfd4;
            border-radius: .55rem;
            background: #ecf8f4;
            color: #07634f;
            font-size: .74rem;
            font-weight: 850;
        }

        .basv-report-score.is-muted {
            border-color: #dce5e2;
            background: #f4f7f6;
            color: #697a76;
        }

        .basv-report-progress {
            min-width: 105px;
        }

        .basv-report-progress-label {
            display: flex;
            justify-content: space-between;
            gap: .5rem;
            margin-bottom: .3rem;
            color: #4f635e;
            font-size: .66rem;
            font-weight: 750;
        }

        .basv-report-filter-summary {
            display: flex;
            align-items: center;
            gap: .45rem;
            color: var(--basv-muted);
            font-size: .7rem;
        }

        .basv-report-table-actions {
            display: flex;
            justify-content: flex-end;
            gap: .35rem;
        }

        .basv-report-table-actions .basv-btn {
            min-height: 34px;
            padding: .4rem .65rem;
        }

        @media (max-width: 1199.98px) {
            .basv-report-stats {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .basv-report-filter-grid,
            .basv-report-filter-grid-secondary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .basv-report-filter-actions {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 767.98px) {
            .basv-report-stats,
            .basv-report-filter-grid,
            .basv-report-filter-grid-secondary {
                grid-template-columns: 1fr;
            }

            .basv-report-filter-actions {
                display: grid;
                grid-column: auto;
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $filterValues = is_array($filters ?? null) ? $filters : request()->only([
            'q',
            'portfolio_id',
            'think_tank_id',
            'cycle_year',
            'cycle_half',
            'status',
            'submitted_from',
            'submitted_to',
        ]);
        $reportRows = $paginator ?? collect($rows ?? []);
        $hasFilters = collect($filterValues)->filter(static fn ($value) => filled($value))->isNotEmpty();
    @endphp

    <main class="nxl-container">
        <div class="nxl-content basv-page">
            <div class="basv-hero">
                <div>
                    <span class="basv-eyebrow">
                        <i class="feather-file-text"></i> Monitoring &amp; Evaluation
                    </span>
                    <h1>Submitted Site Visit Reports</h1>
                    <p>Review completed bi-annual assessments across portfolios, compare questionnaire scores, and
                        export a presentation-ready monitoring register.</p>
                </div>
                <div class="basv-hero-actions">
                    <a href="{{ route('biannual-site-visits.index') }}" class="basv-btn basv-btn-light">
                        <i class="feather-arrow-left"></i> Visit Register
                    </a>
                    @canany(['biannual_site_visits.export', 'biannual_site_visits.approve'])
                        <a href="{{ $reportPdfUrl ?? route('biannual-site-visits.reports.submitted.pdf', request()->query()) }}"
                            class="basv-btn basv-btn-light" target="_blank">
                            <i class="feather-download"></i> Download PDF
                        </a>
                    @endcanany
                </div>
            </div>

            <div class="basv-report-stats">
                <div class="basv-stat">
                    <span class="basv-stat-icon"><i class="feather-file-text"></i></span>
                    <div>
                        <strong>{{ number_format((int) ($stats['total'] ?? 0)) }}</strong>
                        <span>Submitted reports</span>
                    </div>
                </div>
                <div class="basv-stat">
                    <span class="basv-stat-icon"><i class="feather-clock"></i></span>
                    <div>
                        <strong>{{ number_format((int) ($stats['awaiting'] ?? 0)) }}</strong>
                        <span>Awaiting review</span>
                    </div>
                </div>
                <div class="basv-stat">
                    <span class="basv-stat-icon"><i class="feather-check-circle"></i></span>
                    <div>
                        <strong>{{ number_format((int) ($stats['approved'] ?? 0)) }}</strong>
                        <span>Approved reports</span>
                    </div>
                </div>
                <div class="basv-stat">
                    <span class="basv-stat-icon"><i class="feather-award"></i></span>
                    <div>
                        <strong>
                            {{ isset($stats['average_score']) && $stats['average_score'] !== null
                                ? number_format((float) $stats['average_score'], 1).'%' : '—' }}
                        </strong>
                        <span>Average weighted score</span>
                    </div>
                </div>
                <div class="basv-stat">
                    <span class="basv-stat-icon"><i class="feather-trending-up"></i></span>
                    <div>
                        <strong>
                            {{ isset($stats['average_completion']) && $stats['average_completion'] !== null
                                ? number_format((float) $stats['average_completion'], 1).'%' : '—' }}
                        </strong>
                        <span>Average completion</span>
                    </div>
                </div>
            </div>

            <div class="basv-card">
                <div class="basv-card-head">
                    <div>
                        <h2><i class="feather-filter me-2"></i>Report filters</h2>
                        <div class="basv-help">Filters apply to both the register below and the PDF export.</div>
                    </div>
                    @if ($hasFilters)
                        <div class="basv-report-filter-summary">
                            <i class="feather-check-circle"></i> Filtered view
                        </div>
                    @endif
                </div>
                <div class="basv-card-body">
                    <form method="GET" action="{{ route('biannual-site-visits.reports.submitted') }}">
                        <div class="basv-report-filter-grid">
                            <div>
                                <label class="form-label" for="report-q">Search</label>
                                <input id="report-q" type="search" name="q" class="form-control"
                                    value="{{ $filterValues['q'] ?? '' }}"
                                    placeholder="Reference, visit title, Think Tank or team lead">
                            </div>
                            <div>
                                <label class="form-label" for="report-portfolio">Portfolio</label>
                                <select id="report-portfolio" name="portfolio_id" class="form-select">
                                    <option value="">All portfolios</option>
                                    @foreach (($options['portfolios'] ?? []) as $portfolio)
                                        @php
                                            $portfolioId = data_get($portfolio, 'id');
                                            $portfolioLabel = data_get($portfolio, 'name', 'Unnamed portfolio');
                                        @endphp
                                        <option value="{{ $portfolioId }}" @selected((string) ($filterValues['portfolio_id'] ?? '') === (string) $portfolioId)>
                                            {{ $portfolioLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="form-label" for="report-think-tank">Think Tank</label>
                                <select id="report-think-tank" name="think_tank_id" class="form-select">
                                    <option value="">All Think Tanks</option>
                                    @foreach (($options['think_tanks'] ?? []) as $thinkTank)
                                        @php
                                            $thinkTankId = data_get($thinkTank, 'id');
                                            $thinkTankLabel = data_get($thinkTank, 'name', 'Unnamed Think Tank');
                                        @endphp
                                        <option value="{{ $thinkTankId }}" @selected((string) ($filterValues['think_tank_id'] ?? '') === (string) $thinkTankId)>
                                            {{ $thinkTankLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="form-label" for="report-year">Cycle year</label>
                                <select id="report-year" name="cycle_year" class="form-select">
                                    <option value="">All years</option>
                                    @foreach (($options['years'] ?? []) as $year)
                                        <option value="{{ $year }}" @selected((string) ($filterValues['cycle_year'] ?? '') === (string) $year)>
                                            {{ $year }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="basv-report-filter-grid-secondary">
                            <div>
                                <label class="form-label" for="report-half">Cycle half</label>
                                <select id="report-half" name="cycle_half" class="form-select">
                                    <option value="">H1 and H2</option>
                                    <option value="1" @selected((string) ($filterValues['cycle_half'] ?? '') === '1')>H1 — First half</option>
                                    <option value="2" @selected((string) ($filterValues['cycle_half'] ?? '') === '2')>H2 — Second half</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label" for="report-status">Review status</label>
                                <select id="report-status" name="status" class="form-select">
                                    <option value="">Submitted and approved</option>
                                    <option value="submitted" @selected(($filterValues['status'] ?? '') === 'submitted')>Awaiting review</option>
                                    <option value="approved" @selected(($filterValues['status'] ?? '') === 'approved')>Approved</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label" for="report-from">Submitted from</label>
                                <input id="report-from" type="date" name="submitted_from" class="form-control"
                                    value="{{ $filterValues['submitted_from'] ?? '' }}">
                            </div>
                            <div>
                                <label class="form-label" for="report-to">Submitted to</label>
                                <input id="report-to" type="date" name="submitted_to" class="form-control"
                                    value="{{ $filterValues['submitted_to'] ?? '' }}">
                            </div>
                            <div class="basv-report-filter-actions">
                                <button type="submit" class="basv-btn basv-btn-primary">
                                    <i class="feather-search"></i> Apply
                                </button>
                                <a href="{{ route('biannual-site-visits.reports.submitted') }}"
                                    class="basv-btn basv-btn-ghost">
                                    <i class="feather-rotate-ccw"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="basv-card">
                <div class="basv-card-head">
                    <div>
                        <h2><i class="feather-list me-2"></i>Submitted assessment register</h2>
                        <div class="basv-help">
                            {{ number_format((int) (method_exists($reportRows, 'total') ? $reportRows->total() : count($reportRows))) }}
                            {{ \Illuminate\Support\Str::plural('report', method_exists($reportRows, 'total') ? $reportRows->total() : count($reportRows)) }}
                            in this view
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="basv-table" style="min-width: 1220px">
                        <thead>
                            <tr>
                                <th>Visit</th>
                                <th>Think Tank / Portfolio</th>
                                <th>Cycle</th>
                                <th>Submitted</th>
                                <th>Team lead</th>
                                <th>Completion</th>
                                <th>Weighted score</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($reportRows as $row)
                                @php
                                    $visit = data_get($row, 'visit');
                                    $status = (string) data_get($row, 'status', $visit?->siteVisit?->status ?? 'submitted');
                                    $score = data_get($row, 'score_percentage');
                                    $completion = (float) data_get(
                                        $row,
                                        'completion_percentage',
                                        $visit?->completion_percentage ?? 0
                                    );
                                    $submittedAt = data_get($row, 'submitted_at', $visit?->submitted_at);
                                    $submittedAtLabel = $submittedAt instanceof \DateTimeInterface
                                        ? $submittedAt->format('d M Y, H:i')
                                        : ($submittedAt ?: '—');
                                @endphp
                                <tr>
                                    <td>
                                        <a class="basv-record-title"
                                            href="{{ route('biannual-site-visits.show', $visit) }}">
                                            {{ $visit?->title ?: 'Monitoring Site Visit' }}
                                        </a>
                                        <span class="basv-record-meta">{{ $visit?->reference_number ?: '—' }}</span>
                                    </td>
                                    <td>
                                        <strong>{{ data_get($row, 'think_tank_name', $visit?->thinkTank?->name ?? '—') }}</strong>
                                        <span class="basv-record-meta">
                                            {{ data_get($row, 'portfolio_name', 'Unassigned portfolio') }}
                                        </span>
                                    </td>
                                    <td>
                                        <strong>{{ $visit?->cycleLabel() ?? trim(($visit?->cycle_half ?? '').' '.($visit?->cycle_year ?? '')) }}</strong>
                                        <span class="basv-record-meta">
                                            {{ optional($visit?->starts_on)->format('d M Y') ?: '—' }}
                                            @if ($visit?->ends_on)
                                                – {{ $visit->ends_on->format('d M Y') }}
                                            @endif
                                        </span>
                                    </td>
                                    <td>
                                        <strong>{{ $submittedAtLabel }}</strong>
                                        <span class="basv-record-meta">
                                            By {{ data_get($row, 'submitted_by_name', 'Monitoring team') }}
                                        </span>
                                    </td>
                                    <td>
                                        <strong>{{ data_get($row, 'lead_name', 'Not recorded') }}</strong>
                                        <span class="basv-record-meta">Monitoring team</span>
                                    </td>
                                    <td>
                                        <div class="basv-report-progress">
                                            <div class="basv-report-progress-label">
                                                <span>Answered</span><span>{{ number_format($completion, 1) }}%</span>
                                            </div>
                                            <div class="basv-progress">
                                                <span style="width: {{ min(100, max(0, $completion)) }}%"></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="basv-report-score {{ $score === null ? 'is-muted' : '' }}">
                                            {{ $score === null ? '—' : number_format((float) $score, 1).'%' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="basv-badge {{ $status }}">
                                            {{ $status === 'submitted' ? 'Awaiting review' : str_replace('_', ' ', $status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="basv-report-table-actions">
                                            <a href="{{ route('biannual-site-visits.show', $visit) }}"
                                                class="basv-btn basv-btn-ghost" title="Open submitted assessment">
                                                Open <i class="feather-arrow-right"></i>
                                            </a>
                                            @canany(['biannual_site_visits.export', 'biannual_site_visits.approve'])
                                                <a href="{{ route('biannual-site-visits.pdf', $visit) }}"
                                                    class="basv-btn basv-btn-ghost" target="_blank" title="Download assessment PDF">
                                                    <i class="feather-download"></i>
                                                </a>
                                            @endcanany
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9">
                                        <div class="basv-empty">
                                            <i class="feather-inbox"></i>
                                            <strong>No submitted reports match these filters</strong>
                                            <div class="mt-1">
                                                Adjust the filters or return to the visit register to track assessments in progress.
                                            </div>
                                            @if ($hasFilters)
                                                <a href="{{ route('biannual-site-visits.reports.submitted') }}"
                                                    class="basv-btn basv-btn-ghost mt-3">
                                                    Clear filters
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if (method_exists($reportRows, 'links') && $reportRows->hasPages())
                    <div class="p-3 border-top">{{ $reportRows->withQueryString()->links() }}</div>
                @endif
            </div>
        </div>
    </main>
@endsection
