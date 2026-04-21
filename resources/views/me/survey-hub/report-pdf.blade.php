<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Survey Report</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            font-size: 12px;
            line-height: 1.45;
            margin: 0;
        }

        .page {
            padding: 20px 22px;
        }

        .hero {
            background: linear-gradient(135deg, #0f172a 0%, #0f766e 100%);
            color: #ffffff;
            padding: 18px 20px;
            border-radius: 14px;
            margin-bottom: 16px;
        }

        .hero h1 {
            margin: 0 0 6px;
            font-size: 22px;
        }

        .hero p {
            margin: 0;
            color: rgba(255, 255, 255, 0.9);
        }

        .chips {
            margin: 12px 0 0;
        }

        .chip {
            display: inline-block;
            margin: 0 6px 6px 0;
            padding: 5px 10px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.14);
            font-size: 10px;
        }

        .stats {
            width: 100%;
            margin-bottom: 16px;
        }

        .stats td {
            width: 25%;
            padding: 10px 12px;
            border: 1px solid #dbe4ef;
            border-radius: 10px;
            background: #f8fafc;
            vertical-align: top;
        }

        .stat-label {
            display: block;
            color: #64748b;
            font-size: 10px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .stat-value {
            display: block;
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
        }

        .section {
            margin-bottom: 18px;
        }

        .section-title {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .summary {
            padding-left: 18px;
            margin: 0;
        }

        .summary li + li {
            margin-top: 5px;
        }

        .chart-grid {
            width: 100%;
        }

        .chart-grid td {
            width: 50%;
            padding: 8px;
            vertical-align: top;
        }

        .chart-card {
            border: 1px solid #dbe4ef;
            border-radius: 12px;
            padding: 10px;
            background: #ffffff;
            min-height: 260px;
        }

        .chart-card h3 {
            margin: 0 0 8px;
            font-size: 12px;
        }

        .chart-card img {
            width: 100%;
            max-height: 220px;
            object-fit: contain;
        }

        table.report-table {
            width: 100%;
            border-collapse: collapse;
        }

        table.report-table th,
        table.report-table td {
            border: 1px solid #dbe4ef;
            padding: 8px 9px;
            vertical-align: top;
        }

        table.report-table th {
            background: #f8fafc;
            font-size: 10px;
            text-transform: uppercase;
            color: #475569;
        }

        .muted {
            color: #64748b;
        }

        .response-card {
            border: 1px solid #dbe4ef;
            border-radius: 12px;
            padding: 10px;
            margin-bottom: 10px;
            background: #ffffff;
            page-break-inside: avoid;
        }

        .response-card-head {
            margin-bottom: 8px;
        }

        .response-card-title {
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .response-meta {
            font-size: 10px;
            color: #475569;
            margin-bottom: 8px;
        }

        .response-answer-table th:nth-child(1) {
            width: 17%;
        }

        .response-answer-table th:nth-child(2) {
            width: 29%;
        }

        .response-answer-table th:nth-child(3) {
            width: 12%;
        }

        .response-answer-value {
            white-space: pre-wrap;
            word-break: break-word;
        }
    </style>
</head>

<body>
    <div class="page">
        <div class="hero">
            <h1>Survey Report</h1>
            <p>Filtered reporting for questionnaire responses, field analytics, summaries, and downloadable charts.</p>
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
        </div>

        <table class="stats" cellspacing="10" cellpadding="0">
            <tr>
                <td>
                    <span class="stat-label">Responses</span>
                    <span class="stat-value">{{ $report['stats']['responses'] ?? 0 }}</span>
                </td>
                <td>
                    <span class="stat-label">Questionnaires</span>
                    <span class="stat-value">{{ $report['stats']['questionnaires'] ?? 0 }}</span>
                </td>
                <td>
                    <span class="stat-label">Indicators</span>
                    <span class="stat-value">{{ $report['stats']['indicators'] ?? 0 }}</span>
                </td>
                <td>
                    <span class="stat-label">Average / Day</span>
                    <span class="stat-value">{{ $report['stats']['average_per_day'] ?? 0 }}</span>
                </td>
            </tr>
        </table>

        <div class="section">
            <div class="section-title">Summary</div>
            <ul class="summary">
                @foreach ($report['summary'] as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        </div>

        @if (!empty($chartImages['trend']) || !empty($chartImages['pie']) || !empty($chartImages['bar']) || !empty($chartImages['heatmap']))
            <div class="section">
                <div class="section-title">Charts</div>
                <table class="chart-grid" cellspacing="0" cellpadding="0">
                    <tr>
                        <td>
                            <div class="chart-card">
                                <h3>Response Trend & Cumulative Growth</h3>
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

        <div class="section">
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
            <div class="section-title">All Filtered Responses</div>
            @foreach ($report['response_register'] as $responseRow)
                <div class="response-card">
                    <div class="response-card-head">
                        <div class="response-card-title">
                            Response {{ $responseRow['response_number'] }} | Submitted {{ $responseRow['submitted_at'] }}
                        </div>
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
                            | Questionnaire: {{ $responseRow['methodology_name'] }}
                            | Indicator: {{ $responseRow['indicator_name'] }}
                            @if ($responseRow['survey_token'])
                                | Survey Link: {{ $responseRow['survey_token'] }}
                            @endif
                            | Answered: {{ $responseRow['answers_count'] }}/{{ $responseRow['question_count'] }}
                        </div>
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
            @endforeach
        </div>
    </div>
</body>

</html>
