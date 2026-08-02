@extends('layouts.app')

@section('title', 'Report Visualizations and Performance Dashboard')
@section('lean_admin_scripts', '1')

@push('styles')
    <style>
        .report-dashboard {
            --dash-green: #0b5c45;
            --dash-green-dark: #073f30;
            --dash-ink: #183b31;
            --dash-muted: #687a73;
            --dash-line: #dce8e3;
            max-width: 1480px;
            margin: 0 auto;
        }

        .report-dashboard .dashboard-hero {
            overflow: hidden;
            border-radius: 1rem;
            color: #fff;
            background:
                radial-gradient(circle at 85% 15%, rgba(255, 255, 255, .15), transparent 25%),
                linear-gradient(120deg, var(--dash-green-dark), #0d7456);
            box-shadow: 0 16px 38px rgba(7, 63, 48, .17);
        }

        .report-dashboard .hero-main {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.35rem 1.5rem;
        }

        .report-dashboard .hero-eyebrow,
        .report-dashboard .panel-eyebrow {
            font-size: .64rem;
            font-weight: 850;
            letter-spacing: .07em;
            text-transform: uppercase;
        }

        .report-dashboard .hero-eyebrow {
            color: rgba(255, 255, 255, .65);
        }

        .report-dashboard .hero-main h1 {
            margin: .35rem 0 .25rem;
            color: #fff;
            font-size: 1.45rem;
            font-weight: 850;
        }

        .report-dashboard .hero-main p {
            max-width: 760px;
            margin: 0;
            color: rgba(255, 255, 255, .75);
            font-size: .78rem;
        }

        .report-dashboard .hero-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: .5rem;
        }

        .report-dashboard .hero-actions .btn {
            font-size: .72rem;
            font-weight: 800;
        }

        .report-dashboard .hero-foot {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            padding: .7rem 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, .14);
            background: rgba(0, 0, 0, .08);
            color: rgba(255, 255, 255, .68);
            font-size: .67rem;
        }

        .report-dashboard .filter-panel,
        .report-dashboard .dashboard-panel,
        .report-dashboard .metric-card {
            border: 1px solid var(--dash-line);
            border-radius: .95rem;
            background: #fff;
            box-shadow: 0 8px 24px rgba(22, 61, 49, .045);
        }

        .report-dashboard .filter-panel {
            margin-top: 1rem;
            padding: 1rem;
        }

        .report-dashboard .filter-heading,
        .report-dashboard .panel-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: .8rem;
        }

        .report-dashboard .filter-heading h2,
        .report-dashboard .panel-heading h2 {
            margin: 0;
            color: var(--dash-ink);
            font-size: .92rem;
            font-weight: 850;
        }

        .report-dashboard .filter-heading p,
        .report-dashboard .panel-heading p {
            margin: .18rem 0 0;
            color: var(--dash-muted);
            font-size: .7rem;
        }

        .report-dashboard .filter-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .65rem;
            margin-top: .8rem;
        }

        .report-dashboard .filter-grid label {
            display: block;
            margin-bottom: .25rem;
            color: var(--dash-ink);
            font-size: .65rem;
            font-weight: 800;
        }

        .report-dashboard .filter-grid .form-select {
            min-height: 38px;
            border-color: #ceddd7;
            border-radius: .6rem;
            font-size: .72rem;
        }

        .report-dashboard .filter-actions {
            display: flex;
            justify-content: flex-end;
            gap: .5rem;
            margin-top: .75rem;
        }

        .report-dashboard .metric-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: .7rem;
            margin-top: 1rem;
        }

        .report-dashboard .metric-card {
            position: relative;
            display: flex;
            align-items: center;
            gap: .75rem;
            min-width: 0;
            padding: .85rem;
            color: inherit;
            text-decoration: none;
            transition: transform .15s ease, box-shadow .15s ease;
        }

        .report-dashboard a.metric-card:hover,
        .report-dashboard a.metric-card:focus-visible {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(22, 61, 49, .09);
        }

        .report-dashboard .metric-icon {
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            width: 2.35rem;
            height: 2.35rem;
            border-radius: .7rem;
            color: var(--metric-color, var(--dash-green));
            background: var(--metric-soft, #edf8f3);
        }

        .report-dashboard .metric-copy {
            min-width: 0;
        }

        .report-dashboard .metric-copy small,
        .report-dashboard .metric-copy strong,
        .report-dashboard .metric-copy span {
            display: block;
        }

        .report-dashboard .metric-copy small {
            overflow: hidden;
            color: var(--dash-muted);
            font-size: .61rem;
            font-weight: 800;
            text-overflow: ellipsis;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .report-dashboard .metric-copy strong {
            margin-top: .12rem;
            color: var(--dash-ink);
            font-size: 1.22rem;
            line-height: 1.1;
        }

        .report-dashboard .metric-copy span {
            margin-top: .14rem;
            color: var(--dash-muted);
            font-size: .61rem;
        }

        .report-dashboard .visual-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .8rem;
            margin-top: .8rem;
        }

        .report-dashboard .dashboard-panel {
            min-width: 0;
            padding: 1rem;
        }

        .report-dashboard .workflow-layout {
            display: grid;
            grid-template-columns: 180px minmax(0, 1fr);
            gap: 1rem;
            align-items: center;
            margin-top: 1rem;
        }

        .report-dashboard .workflow-donut {
            position: relative;
            width: 170px;
            height: 170px;
            border-radius: 50%;
            background: var(--workflow-gradient);
        }

        .report-dashboard .workflow-donut::after {
            position: absolute;
            inset: 25px;
            display: block;
            border: 1px solid #edf2ef;
            border-radius: 50%;
            background: #fff;
            content: "";
        }

        .report-dashboard .donut-total {
            position: absolute;
            inset: 0;
            z-index: 1;
            display: grid;
            place-content: center;
            text-align: center;
        }

        .report-dashboard .donut-total strong,
        .report-dashboard .donut-total span {
            display: block;
        }

        .report-dashboard .donut-total strong {
            color: var(--dash-ink);
            font-size: 1.55rem;
            line-height: 1;
        }

        .report-dashboard .donut-total span {
            margin-top: .25rem;
            color: var(--dash-muted);
            font-size: .58rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .report-dashboard .stage-list,
        .report-dashboard .bar-list {
            display: grid;
            gap: .48rem;
        }

        .report-dashboard .stage-row,
        .report-dashboard .bar-row {
            display: grid;
            gap: .3rem;
            min-width: 0;
            padding: .45rem .5rem;
            border-radius: .55rem;
            color: inherit;
            text-decoration: none;
        }

        .report-dashboard .stage-row:hover,
        .report-dashboard .bar-row:hover {
            background: #f7faf8;
        }

        .report-dashboard .stage-line,
        .report-dashboard .bar-line {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .6rem;
            min-width: 0;
            color: var(--dash-ink);
            font-size: .7rem;
        }

        .report-dashboard .stage-label {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            min-width: 0;
            font-weight: 750;
        }

        .report-dashboard .stage-dot {
            flex: 0 0 auto;
            width: .55rem;
            height: .55rem;
            border-radius: 50%;
            background: var(--stage-color);
        }

        .report-dashboard .stage-line strong,
        .report-dashboard .bar-line strong {
            flex: 0 0 auto;
            font-size: .72rem;
        }

        .report-dashboard .bar-track {
            height: 6px;
            overflow: hidden;
            border-radius: 999px;
            background: #edf2ef;
        }

        .report-dashboard .bar-track span {
            display: block;
            height: 100%;
            min-width: 3px;
            border-radius: inherit;
            background: var(--bar-color, var(--dash-green));
        }

        .report-dashboard .bar-meta {
            display: flex;
            justify-content: space-between;
            gap: .5rem;
            color: var(--dash-muted);
            font-size: .58rem;
        }

        .report-dashboard .completion-visual {
            display: grid;
            grid-template-columns: 110px minmax(0, 1fr);
            gap: 1rem;
            align-items: center;
            margin-top: 1rem;
        }

        .report-dashboard .completion-ring {
            display: grid;
            place-items: center;
            width: 105px;
            height: 105px;
            border-radius: 50%;
            background: conic-gradient(#15935d var(--completion), #e8efeb 0);
        }

        .report-dashboard .completion-ring > div {
            display: grid;
            place-items: center;
            width: 76px;
            height: 76px;
            border-radius: 50%;
            color: var(--dash-ink);
            background: #fff;
            font-size: 1.15rem;
            font-weight: 850;
        }

        .report-dashboard .completion-actions {
            display: grid;
            gap: .5rem;
        }

        .report-dashboard .completion-actions a {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .6rem;
            padding: .6rem;
            border: 1px solid var(--dash-line);
            border-radius: .6rem;
            color: var(--dash-ink);
            background: #fbfdfc;
            font-size: .68rem;
            text-decoration: none;
        }

        .report-dashboard .completion-actions strong {
            font-size: .74rem;
        }

        .report-dashboard .definition-note {
            margin-top: .75rem;
            padding: .65rem .75rem;
            border-left: 3px solid #d8941d;
            border-radius: .45rem;
            color: #6f5827;
            background: #fff9ec;
            font-size: .64rem;
        }

        .report-dashboard .records-panel {
            margin-top: .8rem;
        }

        .report-dashboard .records-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .7rem;
            margin-top: .8rem;
            padding: .55rem .65rem;
            border-radius: .6rem;
            color: var(--dash-muted);
            background: #f7faf8;
            font-size: .66rem;
        }

        .report-dashboard .records-summary strong {
            color: var(--dash-ink);
        }

        .report-dashboard .dashboard-table {
            margin-top: .65rem;
            font-size: .7rem;
        }

        .report-dashboard .dashboard-table thead th {
            padding: .65rem;
            border-bottom-width: 1px;
            color: #61736c;
            background: #f7faf8;
            font-size: .58rem;
            font-weight: 850;
            letter-spacing: .045em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .report-dashboard .dashboard-table tbody td {
            padding: .7rem .65rem;
            border-color: #edf2ef;
            vertical-align: middle;
        }

        .report-dashboard .record-title,
        .report-dashboard .record-meta {
            display: block;
        }

        .report-dashboard .record-title {
            max-width: 260px;
            overflow: hidden;
            color: var(--dash-ink);
            font-weight: 800;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .report-dashboard .record-meta {
            margin-top: .12rem;
            color: var(--dash-muted);
            font-size: .61rem;
        }

        .report-dashboard .status-pill {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            padding: .3rem .48rem;
            border: 1px solid var(--pill-color);
            border-radius: 999px;
            color: var(--pill-color);
            background: var(--pill-soft);
            font-size: .59rem;
            font-weight: 850;
            white-space: nowrap;
        }

        .report-dashboard .empty-state {
            padding: 2rem 1rem;
            color: var(--dash-muted);
            text-align: center;
        }

        @media (max-width: 1199.98px) {
            .report-dashboard .metric-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 991.98px) {
            .report-dashboard .filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .report-dashboard .visual-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 767.98px) {
            .report-dashboard .hero-main,
            .report-dashboard .filter-heading,
            .report-dashboard .panel-heading {
                align-items: stretch;
                flex-direction: column;
            }

            .report-dashboard .hero-actions {
                justify-content: flex-start;
            }

            .report-dashboard .metric-grid,
            .report-dashboard .filter-grid {
                grid-template-columns: 1fr 1fr;
            }

            .report-dashboard .workflow-layout {
                grid-template-columns: 1fr;
                justify-items: center;
            }

            .report-dashboard .stage-list {
                width: 100%;
            }
        }

        @media (max-width: 479.98px) {
            .report-dashboard .metric-grid,
            .report-dashboard .filter-grid,
            .report-dashboard .completion-visual {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $activeFilters = collect($filters)
            ->filter(fn ($value) => filled($value))
            ->all();
        $dashboardUrl = fn (array $parameters = []) => route(
            'budget.me.rebuild.reporting-dashboard',
            array_merge($activeFilters, $parameters)
        );
        $drilldownUrl = fn (string $key) => $dashboardUrl(['drilldown' => $key]).'#report-records';

        $cursor = 0.0;
        $gradientSegments = [];
        foreach ($distribution as $stage) {
            if ($stage['percentage'] <= 0) {
                continue;
            }
            $end = min(100, $cursor + $stage['percentage']);
            $gradientSegments[] = $stage['color'].' '.$cursor.'% '.$end.'%';
            $cursor = $end;
        }
        if ($cursor < 100 && $gradientSegments !== []) {
            $gradientSegments[] = '#e8efeb '.$cursor.'% 100%';
        }
        $workflowGradient = $gradientSegments === []
            ? '#e8efeb'
            : 'conic-gradient('.implode(', ', $gradientSegments).')';
    @endphp

    <div class="nxl-container">
        <main class="report-dashboard">
            <header class="dashboard-hero">
                <div class="hero-main">
                    <div>
                        <span class="hero-eyebrow">Monitoring &amp; Evaluation · Reporting intelligence</span>
                        <h1>Report Visualizations and Performance Dashboard</h1>
                        <p>Live workflow distribution, timeliness, review efficiency and indicator-reporting completeness across the authorized M&amp;E portfolio.</p>
                    </div>
                    <div class="hero-actions">
                        <a href="{{ route('budget.me.rebuild.data-entry', ['tab' => 'reports']) }}" class="btn btn-light">
                            <i class="feather-list me-1" aria-hidden="true"></i>Report register
                        </a>
                        @canany(['me.data_entry.manage', 'me.configuration.manage'])
                            <a href="{{ route('budget.me.performance-reports.create') }}" class="btn btn-outline-light">
                                <i class="feather-plus me-1" aria-hidden="true"></i>Create report
                            </a>
                        @endcanany
                    </div>
                </div>
                <div class="hero-foot">
                    <span><i class="feather-clock me-1" aria-hidden="true"></i>Generated {{ $generatedAt->format('d M Y, H:i') }}</span>
                    <span><i class="feather-shield me-1" aria-hidden="true"></i>Permission and portfolio scope applied</span>
                    <span><i class="feather-mouse-pointer me-1" aria-hidden="true"></i>Select any metric to inspect its report records</span>
                </div>
            </header>

            <section class="filter-panel" aria-labelledby="dashboard-filter-title">
                <div class="filter-heading">
                    <div>
                        <h2 id="dashboard-filter-title"><i class="feather-filter me-1" aria-hidden="true"></i>Dashboard filters</h2>
                        <p>All cards, charts, percentages and drill-down records use the same active filter set.</p>
                    </div>
                    @if ($activeFilters !== [])
                        <span class="badge bg-success-subtle text-success border border-success-subtle">{{ count($activeFilters) }} active</span>
                    @endif
                </div>
                <form method="GET" action="{{ route('budget.me.rebuild.reporting-dashboard') }}">
                    <div class="filter-grid">
                        <div>
                            <label for="dashboard-year">Reporting year</label>
                            <select class="form-select" id="dashboard-year" name="reporting_year">
                                <option value="">All years</option>
                                @foreach ($filterOptions['years'] as $year)
                                    <option value="{{ $year }}" @selected((string) $filters['reporting_year'] === (string) $year)>{{ $year }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="dashboard-period-type">Reporting frequency</label>
                            <select class="form-select" id="dashboard-period-type" name="reporting_period_type">
                                <option value="">All frequencies</option>
                                @foreach ($filterOptions['period_types'] as $value => $label)
                                    <option value="{{ $value }}" @selected($filters['reporting_period_type'] === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="dashboard-period">Reporting period</label>
                            <select class="form-select" id="dashboard-period" name="reporting_period_label"><option value="">All periods</option></select>
                        </div>
                        <div>
                            <label for="dashboard-component">Project component</label>
                            <select class="form-select" id="dashboard-component" name="component_id">
                                <option value="">All components</option>
                                @foreach ($filterOptions['components'] as $component)
                                    <option value="{{ $component->id }}" @selected((string) $filters['component_id'] === (string) $component->id)>
                                        {{ $component->project_id ? $component->project_id.' · ' : '' }}{{ $component->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="dashboard-results-level">Results level</label>
                            <select class="form-select" id="dashboard-results-level" name="results_level">
                                <option value="">All results levels</option>
                                @foreach ($filterOptions['results_levels'] as $value => $label)
                                    <option value="{{ $value }}" @selected($filters['results_level'] === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="dashboard-owner">Think tank / implementing partner</label>
                            <select class="form-select" id="dashboard-owner" name="think_tank_id">
                                <option value="">All report owners</option>
                                <option value="internal" @selected($filters['think_tank_id'] === 'internal')>Secretariat / Internal</option>
                                @foreach ($filterOptions['think_tanks'] as $thinkTank)
                                    <option value="{{ $thinkTank->id }}" @selected((string) $filters['think_tank_id'] === (string) $thinkTank->id)>
                                        {{ $thinkTank->name }} · {{ \Illuminate\Support\Str::headline($thinkTank->role ?: 'think tank') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="dashboard-indicator">Indicator</label>
                            <select class="form-select" id="dashboard-indicator" name="indicator_id">
                                <option value="">All indicators</option>
                                @foreach ($filterOptions['indicators'] as $indicator)
                                    <option value="{{ $indicator->id }}" @selected((string) $filters['indicator_id'] === (string) $indicator->id)>
                                        {{ $indicator->indicator_code }} · {{ $indicator->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="dashboard-theme">Thematic area / Portfolio</label>
                            <select class="form-select" id="dashboard-theme" name="thematic_area_id">
                                <option value="">All thematic areas</option>
                                @foreach ($filterOptions['thematic_areas'] as $thematicArea)
                                    <option value="{{ $thematicArea->id }}" @selected((string) $filters['thematic_area_id'] === (string) $thematicArea->id)>{{ $thematicArea->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="dashboard-status">Report status</label>
                            <select class="form-select" id="dashboard-status" name="status">
                                <option value="">All workflow stages</option>
                                @foreach ($filterOptions['statuses'] as $value => $label)
                                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="filter-actions">
                        <a href="{{ route('budget.me.rebuild.reporting-dashboard') }}" class="btn btn-light border">
                            <i class="feather-rotate-ccw me-1" aria-hidden="true"></i>Reset
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="feather-sliders me-1" aria-hidden="true"></i>Apply filters
                        </button>
                    </div>
                </form>
            </section>

            <section class="metric-grid" aria-label="Reporting performance indicators">
                <a href="{{ $dashboardUrl() }}#report-records" class="metric-card" style="--metric-color:#0b5c45;--metric-soft:#edf8f3">
                    <span class="metric-icon"><i class="feather-file-text" aria-hidden="true"></i></span>
                    <span class="metric-copy"><small>Total reports</small><strong>{{ number_format($totalReports) }}</strong><span>Matching current filters</span></span>
                </a>
                <a href="{{ $drilldownUrl('stage_submitted') }}" class="metric-card" style="--metric-color:#1676b8;--metric-soft:#eaf5fc">
                    <span class="metric-icon"><i class="feather-user-check" aria-hidden="true"></i></span>
                    <span class="metric-copy"><small>Awaiting review</small><strong>{{ number_format($awaitingReview) }}</strong><span>Secretariat action required</span></span>
                </a>
                <a href="{{ $drilldownUrl('timeliness_overdue') }}" class="metric-card" style="--metric-color:#c43d38;--metric-soft:#fff0ef">
                    <span class="metric-icon"><i class="feather-alert-octagon" aria-hidden="true"></i></span>
                    <span class="metric-copy"><small>Overdue reports</small><strong>{{ number_format($overdueReports) }}</strong><span>Past linked collection deadline</span></span>
                </a>
                <a href="{{ $drilldownUrl('timeliness_on_time') }}" class="metric-card" style="--metric-color:#15935d;--metric-soft:#eaf8f0">
                    <span class="metric-icon"><i class="feather-calendar" aria-hidden="true"></i></span>
                    <span class="metric-copy"><small>On-time submission</small><strong>{{ number_format($onTimeRate, 1) }}%</strong><span>Of submitted reports with deadlines</span></span>
                </a>
                <a href="{{ $drilldownUrl('reviewed_decisions') }}" class="metric-card" style="--metric-color:#7b5fb5;--metric-soft:#f3effb">
                    <span class="metric-icon"><i class="feather-clock" aria-hidden="true"></i></span>
                    <span class="metric-copy"><small>Avg. review &amp; approval</small><strong>{{ $averageReviewLabel }}</strong><span>{{ number_format($reviewDecisionCount) }} filtered {{ \Illuminate\Support\Str::plural('decision', $reviewDecisionCount) }}</span></span>
                </a>
            </section>

            <section class="visual-grid" aria-label="Workflow and timeliness visualizations">
                <article class="dashboard-panel">
                    <div class="panel-heading">
                        <div><span class="panel-eyebrow text-success">Workflow distribution</span><h2>Reports by lifecycle stage</h2><p>Current, mutually exclusive stage for every matching report.</p></div>
                        <span class="badge bg-light text-dark border">{{ number_format($totalReports) }} total</span>
                    </div>
                    <div class="workflow-layout">
                        <div class="workflow-donut" style="--workflow-gradient:{{ $workflowGradient }}">
                            <div class="donut-total"><strong>{{ number_format($totalReports) }}</strong><span>reports</span></div>
                        </div>
                        <div class="stage-list">
                            @foreach ($distribution as $stage)
                                <a href="{{ $drilldownUrl('stage_'.$stage['key']) }}" class="stage-row">
                                    <div class="stage-line">
                                        <span class="stage-label"><span class="stage-dot" style="--stage-color:{{ $stage['color'] }}"></span>{{ $stage['label'] }}</span>
                                        <strong>{{ number_format($stage['count']) }} · {{ number_format($stage['percentage'], 1) }}%</strong>
                                    </div>
                                    <div class="bar-track"><span style="width:{{ $stage['percentage'] }}%;--bar-color:{{ $stage['color'] }}"></span></div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                    <div class="definition-note"><strong>Returned</strong> means a report currently reopened as a draft following a recorded Secretariat return-for-correction decision. Approved corresponds to the reviewed-and-approved lifecycle stage.</div>
                </article>

                <article class="dashboard-panel">
                    <div class="panel-heading">
                        <div><span class="panel-eyebrow text-success">Submission timeliness</span><h2>Deadline performance</h2><p>Calculated from the report’s linked collection deadline and latest submission.</p></div>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">{{ number_format($onTimeRate, 1) }}% on time</span>
                    </div>
                    <div class="bar-list mt-3">
                        @foreach ($timeliness as $item)
                            <a href="{{ $drilldownUrl('timeliness_'.$item['key']) }}" class="bar-row">
                                <div class="bar-line"><span>{{ $item['label'] }}</span><strong>{{ number_format($item['count']) }}</strong></div>
                                <div class="bar-track"><span style="width:{{ $item['percentage'] }}%;--bar-color:{{ $item['color'] }}"></span></div>
                                <div class="bar-meta"><span>{{ number_format($item['percentage'], 1) }}% of filtered reports</span><span>View records <i class="feather-arrow-right" aria-hidden="true"></i></span></div>
                            </a>
                        @endforeach
                    </div>
                </article>

                <article class="dashboard-panel">
                    <div class="panel-heading">
                        <div><span class="panel-eyebrow text-success">Organization coverage</span><h2>Reports by think tank or partner</h2><p>Top reporting organizations under the active filters.</p></div>
                    </div>
                    <div class="bar-list mt-3">
                        @forelse ($reportsByThinkTank as $group)
                            <a href="{{ route('budget.me.rebuild.reporting-dashboard', array_merge($activeFilters, ['think_tank_id' => $group['key']])).'#report-records' }}" class="bar-row">
                                <div class="bar-line"><span>{{ $group['label'] }}</span><strong>{{ number_format($group['count']) }}</strong></div>
                                <div class="bar-track"><span style="width:{{ $group['percentage'] }}%;--bar-color:#1676b8"></span></div>
                                <div class="bar-meta"><span>{{ $group['subtitle'] }}</span><span>{{ number_format($group['percentage'], 1) }}%</span></div>
                            </a>
                        @empty
                            <div class="empty-state">No organization-level report data matches the filters.</div>
                        @endforelse
                    </div>
                </article>

                <article class="dashboard-panel">
                    <div class="panel-heading">
                        <div><span class="panel-eyebrow text-success">Component delivery</span><h2>Reports by project component</h2><p>Top components by reporting volume.</p></div>
                    </div>
                    <div class="bar-list mt-3">
                        @forelse ($reportsByComponent as $group)
                            <a href="{{ route('budget.me.rebuild.reporting-dashboard', array_merge($activeFilters, ['component_id' => $group['key']])).'#report-records' }}" class="bar-row">
                                <div class="bar-line"><span>{{ $group['label'] }}</span><strong>{{ number_format($group['count']) }}</strong></div>
                                <div class="bar-track"><span style="width:{{ $group['percentage'] }}%;--bar-color:#7b5fb5"></span></div>
                                <div class="bar-meta"><span>{{ $group['subtitle'] }}</span><span>{{ number_format($group['percentage'], 1) }}%</span></div>
                            </a>
                        @empty
                            <div class="empty-state">No component report data matches the filters.</div>
                        @endforelse
                    </div>
                </article>

                <article class="dashboard-panel">
                    <div class="panel-heading">
                        <div><span class="panel-eyebrow text-success">Reporting cadence</span><h2>Reports by reporting period</h2><p>Distribution across year and quarter.</p></div>
                    </div>
                    <div class="bar-list mt-3">
                        @forelse ($reportsByPeriod as $group)
                            @php [$groupYear, $groupPeriodType, $groupPeriodLabel] = array_pad(explode('|', $group['key'], 3), 3, null); @endphp
                            <a href="{{ route('budget.me.rebuild.reporting-dashboard', array_merge($activeFilters, ['reporting_year' => $groupYear, 'reporting_period_type' => $groupPeriodType, 'reporting_period_label' => $groupPeriodLabel])).'#report-records' }}" class="bar-row">
                                <div class="bar-line"><span>{{ $group['label'] }}</span><strong>{{ number_format($group['count']) }}</strong></div>
                                <div class="bar-track"><span style="width:{{ $group['percentage'] }}%;--bar-color:#0b7b78"></span></div>
                                <div class="bar-meta"><span>{{ $group['subtitle'] }}</span><span>{{ number_format($group['percentage'], 1) }}%</span></div>
                            </a>
                        @empty
                            <div class="empty-state">No reporting-period data matches the filters.</div>
                        @endforelse
                    </div>
                </article>

                <article class="dashboard-panel">
                    <div class="panel-heading">
                        <div><span class="panel-eyebrow text-success">Indicator completeness</span><h2>Required indicator results reported</h2><p>Complete means both a period result and its linked indicator result record are saved.</p></div>
                    </div>
                    <div class="completion-visual">
                        <div class="completion-ring" style="--completion:{{ $indicatorCompleteness }}%"><div>{{ number_format($indicatorCompleteness, 1) }}%</div></div>
                        <div class="completion-actions">
                            <a href="{{ $drilldownUrl('indicator_complete') }}"><span>Complete indicator reporting</span><strong>{{ number_format($reportedIndicators) }}</strong></a>
                            <a href="{{ $drilldownUrl('indicator_incomplete') }}"><span>Missing indicator results</span><strong>{{ number_format(max(0, $indicatorTotal - $reportedIndicators)) }}</strong></a>
                            <div class="text-muted small">{{ number_format($reportedIndicators) }} of {{ number_format($indicatorTotal) }} due indicator results reported.</div>
                        </div>
                    </div>
                </article>
            </section>

            <section class="dashboard-panel records-panel" id="report-records" aria-labelledby="report-records-title">
                <div class="panel-heading">
                    <div>
                        <span class="panel-eyebrow text-success">Permission-scoped drill-down</span>
                        <h2 id="report-records-title">{{ $drilldownLabel }}</h2>
                        <p>Open a report to view its standardized sections, evidence, status actions and lifecycle history.</p>
                    </div>
                    @if ($drilldown)
                        <a href="{{ $dashboardUrl() }}#report-records" class="btn btn-sm btn-light border"><i class="feather-x me-1" aria-hidden="true"></i>Clear drill-down</a>
                    @endif
                </div>
                <div class="records-summary">
                    <strong>{{ number_format($records->total()) }} {{ \Illuminate\Support\Str::plural('report', $records->total()) }}</strong>
                    <span>Page {{ $records->currentPage() }} of {{ max(1, $records->lastPage()) }}</span>
                </div>

                @if ($records->isEmpty())
                    <div class="empty-state">
                        <i class="feather-inbox d-block fs-3 mb-2" aria-hidden="true"></i>
                        No report records match this filter and drill-down selection.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table dashboard-table align-middle">
                            <caption class="visually-hidden">Report records matching the active dashboard filters</caption>
                            <thead>
                                <tr>
                                    <th>Report</th>
                                    <th>Owner</th>
                                    <th>Component / Theme</th>
                                    <th>Stage</th>
                                    <th>Timeliness</th>
                                    <th>Indicator completeness</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($records as $report)
                                    @php
                                        $stage = $stageConfiguration[$report->dashboard_stage] ?? $stageConfiguration['draft'];
                                        $time = $timelinessConfiguration[$report->dashboard_timeliness] ?? $timelinessConfiguration['no_deadline'];
                                        $resultTotal = (int) $report->indicator_results_count;
                                        $resultComplete = (int) $report->reported_indicator_results_count;
                                        $resultPercent = $resultTotal > 0 ? round(($resultComplete / $resultTotal) * 100, 1) : 0;
                                    @endphp
                                    <tr>
                                        <td>
                                            <span class="record-title" title="{{ $report->form?->title }}">{{ $report->form?->title ?: 'Form unavailable' }}</span>
                                            <span class="record-meta">{{ $report->form?->code }} · {{ $report->periodLabel() }}</span>
                                        </td>
                                        <td>
                                            <span class="record-title">{{ $report->thinkTank?->name ?: 'Secretariat / Internal' }}</span>
                                            <span class="record-meta">{{ $report->thinkTank ? \Illuminate\Support\Str::headline($report->thinkTank->role ?: 'think tank') : 'Internal report' }}</span>
                                        </td>
                                        <td>
                                            <span class="record-title">{{ $report->projectComponent?->name ?: 'Unavailable' }}</span>
                                            <span class="record-meta">{{ $report->portfolio?->name ?: 'No thematic area' }}</span>
                                        </td>
                                        <td>
                                            <span class="status-pill" style="--pill-color:{{ $stage['color'] }};--pill-soft:{{ $stage['soft_color'] }}">
                                                <i class="{{ $stage['icon'] }}" aria-hidden="true"></i>{{ $stage['label'] }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-pill" style="--pill-color:{{ $time['color'] }};--pill-soft:#fff">
                                                {{ $time['label'] }}
                                            </span>
                                            <span class="record-meta">{{ $report->assignment?->collection?->due_at?->format('d M Y, H:i') ?: 'Deadline not set' }}</span>
                                        </td>
                                        <td>
                                            <span class="record-title">{{ $resultComplete }}/{{ $resultTotal }} results · {{ number_format($resultPercent, 1) }}%</span>
                                            <span class="record-meta">{{ $report->documents_count }} supporting {{ \Illuminate\Support\Str::plural('document', $report->documents_count) }}</span>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('budget.me.performance-reports.edit', $report) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="feather-eye me-1" aria-hidden="true"></i>Open report
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $records->links() }}</div>
                @endif
            </section>
        </main>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const type = document.getElementById('dashboard-period-type');
            const period = document.getElementById('dashboard-period');
            const labels = @json($filterOptions['period_labels']);
            const current = @json($filters['reporting_period_label']);
            const refresh = () => {
                if (!period) return;
                period.innerHTML = '<option value="">All periods</option>';
                const groups = type?.value ? {[type.value]: labels[type.value] || {}} : labels;
                Object.entries(groups).forEach(([frequency, options]) => {
                    Object.entries(options).forEach(([value, label]) => {
                        const option = new Option(label, value, false, value === current);
                        option.dataset.frequency = frequency;
                        period.add(option);
                    });
                });
            };
            type?.addEventListener('change', refresh);
            refresh();
        });
    </script>
@endsection
