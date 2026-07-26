<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Submitted Bi-Annual Site Visit Reports</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 96px 24px 58px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #ffffff;
            color: #243b36;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8.5px;
            line-height: 1.36;
        }

        h1,
        h2,
        p {
            margin: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .pdf-header {
            position: fixed;
            top: -96px;
            right: 0;
            left: 0;
            height: 76px;
            padding: 13px 24px 11px;
            border-bottom: 4px solid #d7a528;
            background: #075446;
            color: #ffffff;
            z-index: 10;
        }

        .pdf-header-table td {
            border: 0;
            padding: 0;
            vertical-align: middle;
        }

        .pdf-header-logo {
            width: 190px;
        }

        .pdf-header-logo img {
            display: block;
            max-width: 175px;
            max-height: 45px;
        }

        .pdf-brand-fallback {
            color: #ffffff;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: .04em;
        }

        .pdf-header-title {
            text-align: right;
        }

        .pdf-header-title h1 {
            color: #ffffff;
            font-size: 17px;
            font-weight: 800;
            line-height: 1.1;
        }

        .pdf-header-title p {
            margin-top: 5px;
            color: #d8eee8;
            font-size: 8.5px;
        }

        .pdf-footer {
            position: fixed;
            right: 0;
            bottom: -43px;
            left: 0;
            padding: 8px 24px 0;
            border-top: 2px solid #d7a528;
            color: #5f736e;
            font-size: 7.6px;
            z-index: 10;
        }

        .pdf-footer td {
            border: 0;
            padding: 0;
            vertical-align: top;
            width: 33.333%;
        }

        .pdf-footer-brand {
            color: #075446;
            font-weight: 800;
        }

        .pdf-footer-center {
            text-align: center;
        }

        .pdf-footer-page {
            color: #243b36;
            font-weight: 800;
            text-align: right;
        }

        .portfolio-watermark {
            position: fixed;
            top: 37%;
            left: 7%;
            width: 86%;
            color: #dfece8;
            font-size: 48px;
            font-weight: 800;
            letter-spacing: .04em;
            line-height: 1.05;
            opacity: .34;
            text-align: center;
            text-transform: uppercase;
            transform: rotate(-25deg);
            z-index: -1000;
        }

        .report-intro {
            margin-bottom: 10px;
            padding: 11px 13px;
            border: 1px solid #cdded9;
            border-left: 5px solid #08765f;
            background: #f6faf8;
        }

        .report-intro-table td {
            border: 0;
            padding: 0;
            vertical-align: top;
        }

        .report-intro-main {
            width: 70%;
        }

        .report-eyebrow {
            display: block;
            margin-bottom: 3px;
            color: #08765f;
            font-size: 7.4px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .report-intro h2 {
            color: #173c34;
            font-size: 14px;
            font-weight: 800;
        }

        .report-intro p {
            margin-top: 4px;
            color: #60736e;
        }

        .report-intro-meta {
            color: #4d625c;
            text-align: right;
        }

        .report-intro-meta strong {
            display: block;
            color: #173c34;
            font-size: 9px;
        }

        .stat-table {
            margin-bottom: 10px;
            table-layout: fixed;
        }

        .stat-table td {
            padding: 8px 9px;
            border: 1px solid #d7e4e0;
            background: #ffffff;
            vertical-align: middle;
        }

        .stat-table td:nth-child(even) {
            background: #f7faf9;
        }

        .stat-value {
            display: block;
            color: #075446;
            font-size: 13px;
            font-weight: 800;
            line-height: 1;
        }

        .stat-label {
            display: block;
            margin-top: 3px;
            color: #667a74;
            font-size: 7px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .filter-strip {
            margin-bottom: 10px;
            padding: 7px 9px;
            border: 1px solid #eadba9;
            background: #fffaf0;
            color: #6c5a23;
            font-size: 7.8px;
        }

        .filter-strip strong {
            color: #46370f;
        }

        .report-table {
            table-layout: fixed;
        }

        .report-table th {
            padding: 7px 6px;
            border: 1px solid #075446;
            background: #08765f;
            color: #ffffff;
            font-size: 6.8px;
            font-weight: 800;
            letter-spacing: .035em;
            text-align: left;
            text-transform: uppercase;
        }

        .report-table td {
            padding: 7px 6px;
            border: 1px solid #d7e2df;
            background: rgba(255, 255, 255, .94);
            vertical-align: top;
        }

        .report-table tbody tr:nth-child(even) td {
            background: rgba(247, 250, 249, .94);
        }

        .report-table tr {
            page-break-inside: avoid;
        }

        .record-title {
            color: #153c33;
            font-weight: 800;
            line-height: 1.3;
        }

        .record-meta {
            display: block;
            margin-top: 2px;
            color: #657872;
            font-size: 7.2px;
            line-height: 1.32;
        }

        .metric {
            color: #075446;
            font-size: 9px;
            font-weight: 800;
        }

        .status {
            display: inline-block;
            padding: 3px 5px;
            border: 1px solid #bfd2cc;
            border-radius: 8px;
            color: #455d57;
            font-size: 6.7px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .status-approved {
            border-color: #9bd0bc;
            background: #e6f5ef;
            color: #087052;
        }

        .status-submitted {
            border-color: #b9cbea;
            background: #edf3ff;
            color: #315eae;
        }

        .empty-state {
            padding: 24px;
            border: 1px dashed #b9ccc6;
            color: #667a74;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $pdfRows = collect($rows ?? ($paginator ?? []));
        $filterValues = is_array($filters ?? null) ? $filters : [];
        $portfolioOptions = collect($options['portfolios'] ?? []);
        $thinkTankOptions = collect($options['think_tanks'] ?? []);
        $selectedPortfolio = $portfolioOptions->first(
            static fn ($item) => (string) data_get($item, 'id') === (string) ($filterValues['portfolio_id'] ?? '')
        );
        $selectedThinkTank = $thinkTankOptions->first(
            static fn ($item) => (string) data_get($item, 'id') === (string) ($filterValues['think_tank_id'] ?? '')
        );
        $activeFilters = collect([
            'Search' => $filterValues['q'] ?? null,
            'Portfolio' => data_get($selectedPortfolio, 'name'),
            'Think Tank' => data_get($selectedThinkTank, 'name'),
            'Cycle year' => $filterValues['cycle_year'] ?? null,
            'Cycle half' => match ((string) ($filterValues['cycle_half'] ?? '')) {
                '1' => 'H1',
                '2' => 'H2',
                default => null,
            },
            'Status' => match ($filterValues['status'] ?? null) {
                'submitted' => 'Awaiting review',
                'approved' => 'Approved',
                default => null,
            },
            'Submitted from' => $filterValues['submitted_from'] ?? null,
            'Submitted to' => $filterValues['submitted_to'] ?? null,
        ])->filter(static fn ($value) => filled($value));
        $watermarkText = filled($portfolioName ?? null) ? $portfolioName : 'Multi-Portfolio';
        $generatedTimestamp = ($generatedAt ?? null) instanceof \DateTimeInterface
            ? $generatedAt->format('d M Y, H:i')
            : now()->format('d M Y, H:i');
    @endphp

    <div class="pdf-header">
        <table class="pdf-header-table">
            <tr>
                <td class="pdf-header-logo">
                    @if (filled($logoDataUri ?? null))
                        <img src="{{ $logoDataUri }}" alt="{{ config('app.name', 'ATTP') }}">
                    @else
                        <span class="pdf-brand-fallback">{{ config('app.name', 'ATTP') }}</span>
                    @endif
                </td>
                <td class="pdf-header-title">
                    <h1>Submitted Bi-Annual Site Visit Reports</h1>
                    <p>{{ $watermarkText }} · Monitoring &amp; Evaluation</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="pdf-footer">
        <table>
            <tr>
                <td class="pdf-footer-brand">{{ config('app.name', 'ATTP') }} · Bi-Annual Site Visits</td>
                <td class="pdf-footer-center">Official monitoring report · Generated {{ $generatedTimestamp }}</td>
                <td class="pdf-footer-page"></td>
            </tr>
        </table>
    </div>

    <div class="portfolio-watermark">{{ $watermarkText }}</div>

    <div class="report-intro">
        <table class="report-intro-table">
            <tr>
                <td class="report-intro-main">
                    <span class="report-eyebrow">Monitoring evidence register</span>
                    <h2>Submitted Site Visit Assessments</h2>
                    <p>Consolidated questionnaire outcomes for submitted and approved bi-annual monitoring visits.</p>
                </td>
                <td class="report-intro-meta">
                    <strong>{{ $watermarkText }}</strong>
                    {{ number_format($pdfRows->count()) }} {{ \Illuminate\Support\Str::plural('record', $pdfRows->count()) }} in export
                </td>
            </tr>
        </table>
    </div>

    <table class="stat-table">
        <tr>
            <td>
                <span class="stat-value">{{ number_format((int) ($stats['total'] ?? 0)) }}</span>
                <span class="stat-label">Submitted reports</span>
            </td>
            <td>
                <span class="stat-value">{{ number_format((int) ($stats['awaiting'] ?? 0)) }}</span>
                <span class="stat-label">Awaiting review</span>
            </td>
            <td>
                <span class="stat-value">{{ number_format((int) ($stats['approved'] ?? 0)) }}</span>
                <span class="stat-label">Approved</span>
            </td>
            <td>
                <span class="stat-value">
                    {{ isset($stats['average_score']) && $stats['average_score'] !== null
                        ? number_format((float) $stats['average_score'], 1).'%' : '—' }}
                </span>
                <span class="stat-label">Average weighted score</span>
            </td>
            <td>
                <span class="stat-value">
                    {{ isset($stats['average_completion']) && $stats['average_completion'] !== null
                        ? number_format((float) $stats['average_completion'], 1).'%' : '—' }}
                </span>
                <span class="stat-label">Average completion</span>
            </td>
        </tr>
    </table>

    @if ($activeFilters->isNotEmpty())
        <div class="filter-strip">
            <strong>Applied filters:</strong>
            {{ $activeFilters->map(static fn ($value, $label) => $label.': '.$value)->implode('  ·  ') }}
        </div>
    @endif

    @if ($pdfRows->isEmpty())
        <div class="empty-state">No submitted site visit reports match the selected filters.</div>
    @else
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 16%">Visit</th>
                    <th style="width: 17%">Think Tank / Portfolio</th>
                    <th style="width: 10%">Cycle / Dates</th>
                    <th style="width: 15%">Submitted</th>
                    <th style="width: 12%">Team lead</th>
                    <th style="width: 9%">Completion</th>
                    <th style="width: 9%">Weighted score</th>
                    <th style="width: 12%">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pdfRows as $row)
                    @php
                        $visit = data_get($row, 'visit');
                        $status = (string) data_get($row, 'status', $visit?->siteVisit?->status ?? 'submitted');
                        $submittedAt = data_get($row, 'submitted_at', $visit?->submitted_at);
                        $submittedAtLabel = $submittedAt instanceof \DateTimeInterface
                            ? $submittedAt->format('d M Y, H:i')
                            : ($submittedAt ?: '—');
                        $score = data_get($row, 'score_percentage');
                        $completion = data_get($row, 'completion_percentage', $visit?->completion_percentage);
                    @endphp
                    <tr>
                        <td>
                            <span class="record-title">{{ $visit?->title ?: 'Monitoring Site Visit' }}</span>
                            <span class="record-meta">{{ $visit?->reference_number ?: '—' }}</span>
                        </td>
                        <td>
                            <span class="record-title">
                                {{ data_get($row, 'think_tank_name', $visit?->thinkTank?->name ?? '—') }}
                            </span>
                            <span class="record-meta">{{ data_get($row, 'portfolio_name', 'Unassigned portfolio') }}</span>
                        </td>
                        <td>
                            <span class="record-title">
                                {{ $visit?->cycleLabel() ?? trim(($visit?->cycle_half ?? '').' '.($visit?->cycle_year ?? '')) }}
                            </span>
                            <span class="record-meta">
                                {{ optional($visit?->starts_on)->format('d M Y') ?: '—' }}
                                @if ($visit?->ends_on)
                                    – {{ $visit->ends_on->format('d M Y') }}
                                @endif
                            </span>
                        </td>
                        <td>
                            <span class="record-title">{{ $submittedAtLabel }}</span>
                            <span class="record-meta">
                                By {{ data_get($row, 'submitted_by_name', 'Monitoring team') }}
                            </span>
                        </td>
                        <td>
                            <span class="record-title">{{ data_get($row, 'lead_name', 'Not recorded') }}</span>
                            <span class="record-meta">Monitoring team</span>
                        </td>
                        <td>
                            <span class="metric">
                                {{ $completion === null ? '—' : number_format((float) $completion, 1).'%' }}
                            </span>
                        </td>
                        <td>
                            <span class="metric">{{ $score === null ? '—' : number_format((float) $score, 1).'%' }}</span>
                        </td>
                        <td>
                            <span class="status status-{{ $status }}">
                                {{ $status === 'submitted' ? 'Awaiting review' : str_replace('_', ' ', $status) }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
