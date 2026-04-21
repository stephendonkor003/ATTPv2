<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Survey Report</title>
    <style>
        @page {
            margin: 110px 28px 72px 28px;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #0f172a;
            background: #ffffff;
        }

        .header {
            position: fixed;
            top: -92px;
            left: 0;
            right: 0;
            height: 78px;
            padding: 14px 22px;
            background: #0f172a;
            color: #ffffff;
            border-bottom: 4px solid #f59e0b;
        }

        .header-table,
        .footer-table,
        .metric-table,
        .chart-grid,
        .response-head {
            width: 100%;
            border-collapse: collapse;
        }

        .header-kicker {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #cbd5e1;
            margin-bottom: 4px;
        }

        .header-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .header-meta {
            font-size: 10px;
            color: #e2e8f0;
        }

        .header-prepared {
            text-align: right;
            vertical-align: top;
            font-size: 10px;
            color: #e2e8f0;
            white-space: nowrap;
        }

        .footer {
            position: fixed;
            bottom: -56px;
            left: 0;
            right: 0;
            height: 42px;
            padding: 10px 22px;
            background: #0f172a;
            color: #e2e8f0;
            border-top: 3px solid #0f766e;
            font-size: 9px;
        }

        .footer-center {
            text-align: center;
        }

        .footer-right {
            text-align: right;
        }

        .page-number:before {
            content: "Page " counter(page) " of " counter(pages);
        }

        .report-body {
            padding-top: 2px;
        }

        .hero {
            border: 1px solid #dbe4ef;
            border-left: 6px solid #0f766e;
            border-radius: 14px;
            background: #f8fbfc;
            padding: 18px 18px 14px;
            margin-bottom: 16px;
        }

        .hero-kicker {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #0f766e;
            margin-bottom: 6px;
            font-weight: 700;
        }

        .hero h1 {
            margin: 0 0 5px;
            font-size: 24px;
            line-height: 1.1;
            color: #0f172a;
        }

        .hero p {
            margin: 0;
            color: #475569;
            font-size: 11px;
        }

        .chips {
            margin: 12px 0 4px;
        }

        .chip {
            display: inline-block;
            margin: 0 6px 6px 0;
            padding: 5px 9px;
            border-radius: 999px;
            border: 1px solid #dbe4ef;
            background: #ffffff;
            color: #334155;
            font-size: 9px;
        }

        .metric-table {
            margin-top: 10px;
        }

        .metric-table td {
            width: 25%;
            padding: 10px 12px;
            border: 1px solid #dbe4ef;
            background: #ffffff;
            vertical-align: top;
        }

        .metric-label {
            display: block;
            color: #64748b;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .metric-value {
            display: block;
            color: #0f172a;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .metric-note {
            color: #64748b;
            font-size: 9px;
        }

        .section {
            margin-bottom: 16px;
        }

        .section-keep {
            page-break-inside: avoid;
        }

        .section-title {
            padding: 8px 12px;
            border-left: 4px solid #0f766e;
            background: #f8fafc;
            color: #0f172a;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .section-note {
            color: #64748b;
            font-size: 10px;
            margin: 0 0 8px;
        }

        .summary-list {
            margin: 0;
            padding-left: 18px;
        }

        .summary-list li {
            margin-bottom: 5px;
        }

        .chart-grid td {
            width: 50%;
            padding: 7px;
            vertical-align: top;
        }

        .chart-card {
            border: 1px solid #dbe4ef;
            border-radius: 12px;
            background: #ffffff;
            padding: 10px;
            min-height: 260px;
        }

        .chart-card h3 {
            margin: 0 0 6px;
            font-size: 11px;
            color: #0f172a;
        }

        .chart-card p {
            margin: 0 0 8px;
            font-size: 9px;
            color: #64748b;
        }

        .chart-card img {
            width: 100%;
            max-height: 220px;
            object-fit: contain;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-table th,
        .report-table td {
            border: 1px solid #dbe4ef;
            padding: 8px 9px;
            vertical-align: top;
        }

        .report-table th {
            background: #f8fafc;
            color: #475569;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .muted {
            color: #64748b;
        }

        .response-card {
            border: 1px solid #dbe4ef;
            border-radius: 12px;
            background: #ffffff;
            padding: 10px;
            margin-bottom: 10px;
        }

        .response-card-title {
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 3px;
        }

        .response-card-subtitle {
            font-size: 10px;
            color: #64748b;
        }

        .response-card-metric {
            text-align: right;
            vertical-align: top;
        }

        .response-count {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 999px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1d4ed8;
            font-size: 9px;
            font-weight: 700;
        }

        .response-meta {
            margin: 8px 0 10px;
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            color: #475569;
            font-size: 9px;
            line-height: 1.55;
        }

        .response-answer-table th:nth-child(1) {
            width: 18%;
        }

        .response-answer-table th:nth-child(2) {
            width: 28%;
        }

        .response-answer-table th:nth-child(3) {
            width: 12%;
        }

        .response-answer-table tr {
            page-break-inside: avoid;
        }

        .response-answer-value {
            white-space: pre-wrap;
            word-break: break-word;
        }

        .empty-state {
            border: 1px dashed #cbd5e1;
            padding: 14px;
            color: #64748b;
            background: #f8fafc;
        }
    </style>
</head>

<body>
    @php
        $appName = config('app.name', 'ATTP');
        $generatedAt = now()->format('d M Y H:i');
        $responseRegister = collect($report['response_register'] ?? []);
    @endphp

    <div class="header">
        <table class="header-table">
            <tr>
                <td>
                    <div class="header-kicker">Monitoring &amp; Evaluation</div>
                    <div class="header-title">{{ $appName }} Survey Report</div>
                    <div class="header-meta">
                        Filtered reporting pack for questionnaire responses, charts, field analytics, and detailed submissions.
                    </div>
                </td>
                <td class="header-prepared">
                    Prepared {{ $generatedAt }}
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <table class="footer-table">
            <tr>
                <td>{{ $appName }} Survey Reporting Pack</td>
                <td class="footer-center">Confidential</td>
                <td class="footer-right"><span class="page-number"></span></td>
            </tr>
        </table>
    </div>

    <main class="report-body">
        <div class="hero">
            <div class="hero-kicker">Questionnaire Reporting Pack</div>
            <h1>Survey Report</h1>
            <p>
                Consolidated survey reporting for the selected questionnaire filter, including summary analytics,
                charts, question-field performance, and detailed response records.
            </p>

            <div class="chips">
                @if ($report['resolved_methodology'])
                    <span class="chip">Questionnaire: {{ $report['resolved_methodology']->name }}</span>
                @endif
                @if ($report['resolved_survey_link'])
                    <span class="chip">Survey Link: {{ $report['resolved_survey_link']->public_token }}</span>
                @endif
                @if ($report['selected_question'])
                    <span class="chip">Question Focus: {{ $report['selected_question']['label'] }}</span>
                @endif
                @if ($filters['date_from'] || $filters['date_to'])
                    <span class="chip">Date Range: {{ $filters['date_from'] ?: 'Start' }} to {{ $filters['date_to'] ?: 'Now' }}</span>
                @endif
            </div>

            <table class="metric-table" cellspacing="0" cellpadding="0">
                <tr>
                    <td>
                        <span class="metric-label">Responses</span>
                        <span class="metric-value">{{ $report['stats']['responses'] ?? 0 }}</span>
                        <span class="metric-note">Filtered submissions in this report.</span>
                    </td>
                    <td>
                        <span class="metric-label">Questionnaires</span>
                        <span class="metric-value">{{ $report['stats']['questionnaires'] ?? 0 }}</span>
                        <span class="metric-note">Survey forms represented in the result set.</span>
                    </td>
                    <td>
                        <span class="metric-label">Indicators</span>
                        <span class="metric-value">{{ $report['stats']['indicators'] ?? 0 }}</span>
                        <span class="metric-note">Indicators contributing to this analysis.</span>
                    </td>
                    <td>
                        <span class="metric-label">Average / Day</span>
                        <span class="metric-value">{{ $report['stats']['average_per_day'] ?? 0 }}</span>
                        <span class="metric-note">
                            Last response:
                            {{ !empty($report['stats']['last_response']) ? \Illuminate\Support\Carbon::parse($report['stats']['last_response'])->format('d M Y H:i') : 'No responses yet' }}
                        </span>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section section-keep">
            <div class="section-title">Executive Summary</div>
            <ul class="summary-list">
                @foreach ($report['summary'] as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        </div>

        @if (!empty($chartImages['trend']) || !empty($chartImages['pie']) || !empty($chartImages['bar']) || !empty($chartImages['heatmap']))
            <div class="section section-keep">
                <div class="section-title">Charts &amp; Visual Analysis</div>
                <table class="chart-grid" cellspacing="0" cellpadding="0">
                    <tr>
                        <td>
                            <div class="chart-card">
                                <h3>Response Trend &amp; Cumulative Growth</h3>
                                <p>Tracks new responses and cumulative reporting volume across the filtered period.</p>
                                @if (!empty($chartImages['trend']))
                                    <img src="{{ $chartImages['trend'] }}" alt="Response trend chart">
                                @else
                                    <div class="muted">Trend chart not included in this export.</div>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="chart-card">
                                <h3>Pie Breakdown</h3>
                                <p>Distribution view for the selected question field or current survey context.</p>
                                @if (!empty($chartImages['pie']))
                                    <img src="{{ $chartImages['pie'] }}" alt="Pie chart">
                                @else
                                    <div class="muted">Pie chart not included in this export.</div>
                                @endif
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="chart-card">
                                <h3>Bar Analysis</h3>
                                <p>Counts, scores, or grouped answer activity in the selected reporting context.</p>
                                @if (!empty($chartImages['bar']))
                                    <img src="{{ $chartImages['bar'] }}" alt="Bar chart">
                                @else
                                    <div class="muted">Bar chart not included in this export.</div>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="chart-card">
                                <h3>{{ $report['heatmap']['title'] }}</h3>
                                <p>{{ $report['heatmap']['description'] }}</p>
                                @if (!empty($chartImages['heatmap']))
                                    <img src="{{ $chartImages['heatmap'] }}" alt="Heatmap chart">
                                @else
                                    <div class="muted">Heatmap chart not included in this export.</div>
                                @endif
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        @endif

        <div class="section section-keep">
            <div class="section-title">Question Field Performance</div>
            <table class="report-table">
                <thead>
                    <tr>
                        <th style="width: 30%;">Question</th>
                        <th style="width: 14%;">Type</th>
                        <th style="width: 10%;">Answered</th>
                        <th style="width: 12%;">Completion</th>
                        <th>Summary</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (collect($report['question_stats'])->take(12) as $questionStat)
                        <tr>
                            <td>
                                <strong>{{ $questionStat['label'] }}</strong>
                                <div class="muted">{{ $questionStat['section_title'] ?: 'General section' }}</div>
                            </td>
                            <td>{{ \Illuminate\Support\Str::headline($questionStat['type']) }}</td>
                            <td>{{ $questionStat['answered_count'] }}</td>
                            <td>{{ $questionStat['completion_rate'] }}%</td>
                            <td>{{ $questionStat['headline'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Detailed Response Register</div>
            <div class="section-note">
                Every filtered response is listed below with respondent context and answer details in report order.
            </div>

            @forelse ($responseRegister as $responseRow)
                <div class="response-card">
                    <table class="response-head">
                        <tr>
                            <td>
                                <div class="response-card-title">
                                    Response {{ $responseRow['response_number'] }} | Submitted {{ $responseRow['submitted_at'] }}
                                </div>
                                <div class="response-card-subtitle">
                                    Questionnaire: {{ $responseRow['methodology_name'] }} | Indicator: {{ $responseRow['indicator_name'] }}
                                    @if ($responseRow['survey_token'])
                                        | Survey Link: {{ $responseRow['survey_token'] }}
                                    @endif
                                </div>
                            </td>
                            <td class="response-card-metric">
                                <span class="response-count">{{ $responseRow['answers_count'] }}/{{ $responseRow['question_count'] }} answered</span>
                            </td>
                        </tr>
                    </table>

                    <div class="response-meta">
                        Respondent: {{ $responseRow['respondent_name'] }}
                        @if ($responseRow['respondent_email'])
                            | Email: {{ $responseRow['respondent_email'] }}
                        @endif
                        @if ($responseRow['respondent_phone'])
                            | Phone: {{ $responseRow['respondent_phone'] }}
                        @endif
                        @if ($responseRow['respondent_organization'])
                            | Organization: {{ $responseRow['respondent_organization'] }}
                        @endif
                    </div>

                    <table class="report-table response-answer-table">
                        <thead>
                            <tr>
                                <th>Section</th>
                                <th>Question</th>
                                <th>Type</th>
                                <th>Answer</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($responseRow['answers'] as $answer)
                                <tr>
                                    <td>{{ $answer['section_title'] }}</td>
                                    <td>{{ $answer['question'] }}</td>
                                    <td>{{ $answer['type'] }}</td>
                                    <td class="response-answer-value">{{ $answer['value'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="muted">No answer details were captured for this response.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @empty
                <div class="empty-state">No filtered responses were available for this PDF export.</div>
            @endforelse
        </div>
    </main>
</body>

</html>
