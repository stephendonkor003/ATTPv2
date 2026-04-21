@extends('layouts.app')
@section('title', 'Survey Reports')

@push('styles')
    <style>
        .survey-report-filter {
            border: 1px solid #dbe4ef;
            border-radius: 20px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
        }

        .survey-report-chart {
            border: 1px solid #dbe4ef;
            border-radius: 20px;
            background: #ffffff;
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.06);
            overflow: hidden;
            height: 100%;
        }

        .survey-report-chart__head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem 1.1rem 0.75rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .survey-report-chart__title {
            color: #0f172a;
            font-size: 0.98rem;
            font-weight: 700;
            margin-bottom: 0.2rem;
        }

        .survey-report-chart__text {
            color: #64748b;
            font-size: 0.84rem;
            margin-bottom: 0;
        }

        .survey-report-chart__body {
            padding: 1rem 1.1rem 1.2rem;
        }

        .survey-report-note {
            color: #64748b;
            font-size: 0.88rem;
        }

        .survey-report-heatmap-wrap {
            overflow-x: auto;
        }

        .survey-report-heatmap-wrap canvas {
            width: 100%;
            min-height: 320px;
            display: block;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
        }

        .survey-summary-list {
            margin: 0;
            padding-left: 1.1rem;
            color: #0f172a;
        }

        .survey-summary-list li + li {
            margin-top: 0.55rem;
        }

        .survey-meta-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.38rem 0.75rem;
            border-radius: 999px;
            background: #f8fafc;
            border: 1px solid #dbe4ef;
            color: #334155;
            font-size: 0.8rem;
            font-weight: 600;
            margin: 0 0.45rem 0.45rem 0;
        }

        .survey-report-empty {
            text-align: center;
            padding: 2.5rem 1.25rem;
            color: #64748b;
        }

        .survey-table-wrap {
            border: 1px solid #dbe4ef;
            border-radius: 20px;
            background: #ffffff;
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }

        .survey-chart-canvas {
            min-height: 300px;
        }

        .survey-response-register {
            display: grid;
            gap: 1rem;
        }

        .survey-response-card {
            border: 1px solid #dbe4ef;
            border-radius: 22px;
            background:
                linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            box-shadow: 0 16px 30px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }

        .survey-response-card__head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem 1.1rem 0.8rem;
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(135deg, rgba(15, 118, 110, 0.08), rgba(14, 165, 233, 0.05));
        }

        .survey-response-card__title {
            color: #0f172a;
            font-size: 1rem;
            font-weight: 700;
        }

        .survey-response-card__meta {
            color: #64748b;
            font-size: 0.84rem;
            margin-top: 0.2rem;
        }

        .survey-response-card__count {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.4rem 0.72rem;
            border-radius: 999px;
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 0.8rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .survey-response-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.55rem;
            padding: 0.9rem 1.1rem 0;
        }

        .survey-response-meta__chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.38rem 0.7rem;
            border-radius: 999px;
            border: 1px solid #dbe4ef;
            background: #f8fafc;
            color: #334155;
            font-size: 0.79rem;
            font-weight: 600;
        }

        .survey-response-answer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 0.85rem;
            padding: 1rem 1.1rem 1.15rem;
        }

        .survey-response-answer {
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            background: #ffffff;
            padding: 0.9rem;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
        }

        .survey-response-answer__section {
            color: #0f766e;
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 0.3rem;
        }

        .survey-response-answer__question {
            color: #0f172a;
            font-size: 0.94rem;
            font-weight: 700;
            line-height: 1.4;
            margin-bottom: 0.45rem;
        }

        .survey-response-answer__type {
            color: #64748b;
            font-size: 0.78rem;
            font-weight: 600;
            margin-bottom: 0.55rem;
        }

        .survey-response-answer__value {
            color: #334155;
            font-size: 0.88rem;
            line-height: 1.55;
            white-space: pre-wrap;
            word-break: break-word;
        }
    </style>
@endpush

@section('content')
    @php
        $heroActions = [
            ['href' => route('budget.me.surveys.qr'), 'label' => 'QR Codes', 'icon' => 'feather-grid', 'class' => 'btn btn-light btn-sm'],
            ['href' => route('budget.me.surveys.responses'), 'label' => 'Responses', 'icon' => 'feather-inbox', 'class' => 'btn btn-outline-light btn-sm'],
        ];
    @endphp

    <div class="nxl-container">
        @include('me.survey-hub._header', [
            'active' => 'reports',
            'title' => 'Survey Reports',
            'subtitle' => 'Filter survey responses, review cumulative summaries, and generate chart-based reporting from questionnaire fields and aggregated submissions.',
            'heroActions' => $heroActions,
        ])

        @include('me.survey-hub._alerts')

        <div class="survey-report-filter p-3 mb-4">
            <form method="GET" action="{{ route('budget.me.surveys.reports') }}" class="row g-3 align-items-end">
                <div class="col-lg-3">
                    <label class="form-label fw-semibold">Questionnaire</label>
                    <select name="methodology_id" class="form-select">
                        <option value="">All questionnaires</option>
                        @foreach ($filters['methodologies'] as $methodology)
                            <option value="{{ $methodology->id }}" @selected((string) $filters['selected_methodology_id'] === (string) $methodology->id)>
                                {{ $methodology->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3">
                    <label class="form-label fw-semibold">Survey Link</label>
                    <select name="survey_link_id" class="form-select">
                        <option value="">All survey links</option>
                        @foreach ($filters['survey_links'] as $surveyLink)
                            <option value="{{ $surveyLink->id }}" @selected((string) $filters['selected_survey_link_id'] === (string) $surveyLink->id)>
                                {{ $surveyLink->public_token }} | {{ $surveyLink->indicator->name ?? 'Indicator' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label fw-semibold">Indicator</label>
                    <select name="indicator_id" class="form-select">
                        <option value="">All indicators</option>
                        @foreach ($filters['indicators'] as $indicator)
                            <option value="{{ $indicator->id }}" @selected((string) $filters['selected_indicator_id'] === (string) $indicator->id)>
                                {{ $indicator->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-4">
                    <label class="form-label fw-semibold">Question Field Focus</label>
                    <select name="question_key" class="form-select">
                        <option value="">Auto select best field</option>
                        @foreach ($filters['question_options'] as $questionOption)
                            <option value="{{ $questionOption['key'] }}" @selected((string) $filters['selected_question_key'] === (string) $questionOption['key'])>
                                {{ $questionOption['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label fw-semibold">From</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="form-control">
                </div>
                <div class="col-lg-2">
                    <label class="form-label fw-semibold">To</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="form-control">
                </div>
                <div class="col-lg-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="feather-filter me-1"></i> Run Report
                    </button>
                    <a href="{{ route('budget.me.surveys.reports') }}" class="btn btn-light border">Reset</a>
                    @if (($report['stats']['responses'] ?? 0) > 0)
                        <button type="button" class="btn btn-outline-secondary" id="exportSurveyReportPdfBtn">
                            <i class="feather-file-text me-1"></i> PDF
                        </button>
                    @endif
                </div>
            </form>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="survey-stat p-3">
                    <div class="survey-stat__label">Responses</div>
                    <div class="survey-stat__value mt-2">{{ $report['stats']['responses'] ?? 0 }}</div>
                    <div class="survey-stat__meta mt-2">Filtered submissions included in this report.</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="survey-stat p-3">
                    <div class="survey-stat__label">Questionnaires</div>
                    <div class="survey-stat__value mt-2">{{ $report['stats']['questionnaires'] ?? 0 }}</div>
                    <div class="survey-stat__meta mt-2">Survey forms represented in the filtered data.</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="survey-stat p-3">
                    <div class="survey-stat__label">Indicators</div>
                    <div class="survey-stat__value mt-2">{{ $report['stats']['indicators'] ?? 0 }}</div>
                    <div class="survey-stat__meta mt-2">Indicators contributing responses to the report.</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="survey-stat p-3">
                    <div class="survey-stat__label">Average / Day</div>
                    <div class="survey-stat__value mt-2">{{ $report['stats']['average_per_day'] ?? 0 }}</div>
                    <div class="survey-stat__meta mt-2">
                        Last response:
                        {{ !empty($report['stats']['last_response']) ? \Illuminate\Support\Carbon::parse($report['stats']['last_response'])->format('d M Y H:i') : 'No responses yet' }}
                    </div>
                </div>
            </div>
        </div>

        @if (($report['stats']['responses'] ?? 0) === 0)
            <div class="survey-panel">
                <div class="survey-report-empty">
                    No report data matched the current survey filters. Adjust the questionnaire, date range, or survey link and run the report again.
                </div>
            </div>
        @else
            <div class="row g-4 mb-4">
                <div class="col-xl-5">
                    <div class="survey-panel h-100">
                        <div class="survey-panel__header">
                            <div>
                                <div class="survey-panel__title">Reporting Summary</div>
                                <p class="survey-panel__subtitle">Cumulative narrative based on the filtered survey responses and question field performance.</p>
                            </div>
                        </div>
                        <div class="p-3 p-lg-4">
                            <div class="mb-3">
                                @if ($report['resolved_methodology'])
                                    <span class="survey-meta-chip"><i class="feather-book-open"></i> {{ $report['resolved_methodology']->name }}</span>
                                @endif
                                @if ($report['resolved_survey_link'])
                                    <span class="survey-meta-chip"><i class="feather-link"></i> {{ $report['resolved_survey_link']->public_token }}</span>
                                @endif
                                @if ($report['selected_question'])
                                    <span class="survey-meta-chip"><i class="feather-help-circle"></i> {{ $report['selected_question']['label'] }}</span>
                                @endif
                                @if ($filters['date_from'] || $filters['date_to'])
                                    <span class="survey-meta-chip"><i class="feather-calendar"></i> {{ $filters['date_from'] ?: 'Start' }} to {{ $filters['date_to'] ?: 'Now' }}</span>
                                @endif
                            </div>

                            <ul class="survey-summary-list">
                                @foreach ($report['summary'] as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-xl-7">
                    <div class="survey-table-wrap h-100">
                        <div class="survey-panel__header">
                            <div>
                                <div class="survey-panel__title">Question Field Performance</div>
                                <p class="survey-panel__subtitle">Completion and answer highlights for the most active questionnaire fields in the current report.</p>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table survey-table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-3">Question</th>
                                        <th>Type</th>
                                        <th>Answered</th>
                                        <th>Completion</th>
                                        <th class="pe-3">Summary</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach (collect($report['question_stats'])->take(10) as $questionStat)
                                        <tr>
                                            <td class="ps-3">
                                                <div class="fw-semibold text-dark">{{ $questionStat['label'] }}</div>
                                                <div class="survey-muted small">{{ $questionStat['section_title'] ?: 'General section' }}</div>
                                            </td>
                                            <td>{{ \Illuminate\Support\Str::headline($questionStat['type']) }}</td>
                                            <td>{{ $questionStat['answered_count'] }}</td>
                                            <td>{{ $questionStat['completion_rate'] }}%</td>
                                            <td class="pe-3">{{ $questionStat['headline'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-xl-6">
                    <div class="survey-report-chart">
                        <div class="survey-report-chart__head">
                            <div>
                                <div class="survey-report-chart__title">Response Trend & Cumulative Growth</div>
                                <p class="survey-report-chart__text">Tracks new responses and cumulative reporting volume across the filtered period.</p>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-download-chart="trend">
                                <i class="feather-download me-1"></i> PNG
                            </button>
                        </div>
                        <div class="survey-report-chart__body">
                            <canvas id="surveyTrendChart" class="survey-chart-canvas"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6">
                    <div class="survey-report-chart">
                        <div class="survey-report-chart__head">
                            <div>
                                <div class="survey-report-chart__title">Pie Breakdown: {{ $report['focus_charts']['title'] }}</div>
                                <p class="survey-report-chart__text">Distribution view for the selected question field or the current survey context.</p>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-download-chart="pie">
                                <i class="feather-download me-1"></i> PNG
                            </button>
                        </div>
                        <div class="survey-report-chart__body">
                            <canvas id="surveyPieChart" class="survey-chart-canvas"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6">
                    <div class="survey-report-chart">
                        <div class="survey-report-chart__head">
                            <div>
                                <div class="survey-report-chart__title">Bar Analysis: {{ $report['focus_charts']['title'] }}</div>
                                <p class="survey-report-chart__text">Counts, scores, or field activity by question value, answer bucket, or row grouping.</p>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-download-chart="bar">
                                <i class="feather-download me-1"></i> PNG
                            </button>
                        </div>
                        <div class="survey-report-chart__body">
                            <canvas id="surveyBarChart" class="survey-chart-canvas"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6">
                    <div class="survey-report-chart">
                        <div class="survey-report-chart__head">
                            <div>
                                <div class="survey-report-chart__title">{{ $report['heatmap']['title'] }}</div>
                                <p class="survey-report-chart__text">{{ $report['heatmap']['description'] }}</p>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-download-canvas="surveyHeatmapCanvas">
                                <i class="feather-download me-1"></i> PNG
                            </button>
                        </div>
                        <div class="survey-report-chart__body">
                            <div class="survey-report-heatmap-wrap">
                                <canvas id="surveyHeatmapCanvas"></canvas>
                            </div>
                            <div class="survey-report-note mt-3">
                                Darker cells indicate higher concentration of answers in that question field or matrix combination.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="survey-panel mb-4">
                <div class="survey-panel__header">
                    <div>
                        <div class="survey-panel__title">All Filtered Responses</div>
                        <p class="survey-panel__subtitle">A full response register showing every submission and the answer details captured in the current report filter.</p>
                    </div>
                </div>
                <div class="p-3 p-lg-4">
                    <div class="survey-response-register">
                        @foreach ($report['response_register'] as $responseRow)
                            <article class="survey-response-card">
                                <div class="survey-response-card__head">
                                    <div>
                                        <div class="survey-response-card__title">Response {{ $responseRow['response_number'] }}</div>
                                        <div class="survey-response-card__meta">
                                            Submitted {{ $responseRow['submitted_at'] }}
                                        </div>
                                    </div>
                                    <div class="survey-response-card__count">
                                        {{ $responseRow['answers_count'] }}/{{ $responseRow['question_count'] }} answered
                                    </div>
                                </div>

                                <div class="survey-response-meta">
                                    <span class="survey-response-meta__chip"><i class="feather-user"></i> {{ $responseRow['respondent_name'] }}</span>
                                    @if ($responseRow['respondent_email'])
                                        <span class="survey-response-meta__chip"><i class="feather-mail"></i> {{ $responseRow['respondent_email'] }}</span>
                                    @endif
                                    @if ($responseRow['respondent_phone'])
                                        <span class="survey-response-meta__chip"><i class="feather-phone"></i> {{ $responseRow['respondent_phone'] }}</span>
                                    @endif
                                    @if ($responseRow['respondent_organization'])
                                        <span class="survey-response-meta__chip"><i class="feather-briefcase"></i> {{ $responseRow['respondent_organization'] }}</span>
                                    @endif
                                    <span class="survey-response-meta__chip"><i class="feather-book-open"></i> {{ $responseRow['methodology_name'] }}</span>
                                    <span class="survey-response-meta__chip"><i class="feather-target"></i> {{ $responseRow['indicator_name'] }}</span>
                                    @if ($responseRow['survey_token'])
                                        <span class="survey-response-meta__chip"><i class="feather-link"></i> {{ $responseRow['survey_token'] }}</span>
                                    @endif
                                </div>

                                <div class="survey-response-answer-grid">
                                    @forelse ($responseRow['answers'] as $answer)
                                        <div class="survey-response-answer">
                                            <div class="survey-response-answer__section">{{ $answer['section_title'] }}</div>
                                            <div class="survey-response-answer__question">{{ $answer['question'] }}</div>
                                            <div class="survey-response-answer__type">{{ $answer['type'] }}</div>
                                            <div class="survey-response-answer__value">{{ $answer['value'] }}</div>
                                        </div>
                                    @empty
                                        <div class="survey-report-note">No answer details were captured for this response.</div>
                                    @endforelse
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('budget.me.surveys.reports.export.pdf') }}" id="surveyReportPdfForm" class="d-none">
                @csrf
                <input type="hidden" name="methodology_id" value="{{ $filters['selected_methodology_id'] }}">
                <input type="hidden" name="survey_link_id" value="{{ $filters['selected_survey_link_id'] }}">
                <input type="hidden" name="indicator_id" value="{{ $filters['selected_indicator_id'] }}">
                <input type="hidden" name="question_key" value="{{ $filters['selected_question_key'] }}">
                <input type="hidden" name="date_from" value="{{ $filters['date_from'] }}">
                <input type="hidden" name="date_to" value="{{ $filters['date_to'] }}">
                <input type="hidden" name="chart_trend" id="chart_trend">
                <input type="hidden" name="chart_pie" id="chart_pie">
                <input type="hidden" name="chart_bar" id="chart_bar">
                <input type="hidden" name="chart_heatmap" id="chart_heatmap">
            </form>
        @endif
    </div>
@endsection

@push('scripts')
    @if (($report['stats']['responses'] ?? 0) > 0)
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const chartPalette = [
                    '#0f766e',
                    '#0ea5e9',
                    '#f97316',
                    '#4f46e5',
                    '#10b981',
                    '#ef4444',
                    '#14b8a6',
                    '#f59e0b',
                    '#334155',
                    '#8b5cf6',
                ];

                const trendPayload = @json($report['timeline']);
                const piePayload = @json($report['focus_charts']['pie']);
                const barPayload = @json($report['focus_charts']['bar']);
                const heatmapPayload = @json($report['heatmap']);
                const chartInstances = {};

                function colors(count) {
                    return Array.from({ length: count }, (_, index) => chartPalette[index % chartPalette.length]);
                }

                function safeLabels(payload) {
                    return Array.isArray(payload?.labels) && payload.labels.length ? payload.labels : ['No data'];
                }

                function safeData(payload) {
                    return Array.isArray(payload?.data) && payload.data.length ? payload.data : [0];
                }

                chartInstances.trend = new Chart(document.getElementById('surveyTrendChart'), {
                    type: 'line',
                    data: {
                        labels: safeLabels(trendPayload),
                        datasets: [{
                            label: 'Responses',
                            data: Array.isArray(trendPayload?.daily) && trendPayload.daily.length ? trendPayload.daily : [0],
                            borderColor: '#0f766e',
                            backgroundColor: 'rgba(15, 118, 110, 0.14)',
                            tension: 0.32,
                            fill: true,
                            yAxisID: 'y',
                        }, {
                            label: 'Cumulative',
                            data: Array.isArray(trendPayload?.cumulative) && trendPayload.cumulative.length ? trendPayload.cumulative : [0],
                            borderColor: '#f97316',
                            backgroundColor: 'rgba(249, 115, 22, 0.08)',
                            tension: 0.22,
                            fill: false,
                            yAxisID: 'y1',
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: { legend: { position: 'bottom' } },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'Responses' },
                            },
                            y1: {
                                beginAtZero: true,
                                position: 'right',
                                grid: { drawOnChartArea: false },
                                title: { display: true, text: 'Cumulative' },
                            },
                        },
                    },
                });

                chartInstances.pie = new Chart(document.getElementById('surveyPieChart'), {
                    type: 'pie',
                    data: {
                        labels: safeLabels(piePayload),
                        datasets: [{
                            data: safeData(piePayload),
                            backgroundColor: colors(safeLabels(piePayload).length),
                            borderColor: '#ffffff',
                            borderWidth: 2,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom' } },
                    },
                });

                chartInstances.bar = new Chart(document.getElementById('surveyBarChart'), {
                    type: 'bar',
                    data: {
                        labels: safeLabels(barPayload),
                        datasets: [{
                            label: 'Responses',
                            data: safeData(barPayload),
                            backgroundColor: colors(safeLabels(barPayload).length),
                            borderRadius: 10,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true },
                        },
                    },
                });

                function drawHeatmap(canvas, payload) {
                    const rows = Array.isArray(payload?.rows) ? payload.rows : [];
                    const columns = Array.isArray(payload?.columns) ? payload.columns : [];
                    const values = Array.isArray(payload?.values) ? payload.values : [];
                    const maxValue = Number(payload?.max || 0);

                    const ctx = canvas.getContext('2d');
                    const rowHeight = 40;
                    const columnWidth = 72;
                    const leftPad = 210;
                    const topPad = 70;
                    const rightPad = 24;
                    const bottomPad = 24;

                    const width = leftPad + (Math.max(columns.length, 1) * columnWidth) + rightPad;
                    const height = topPad + (Math.max(rows.length, 1) * rowHeight) + bottomPad;

                    canvas.width = width;
                    canvas.height = height;

                    ctx.clearRect(0, 0, width, height);
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, width, height);

                    ctx.font = '600 12px Segoe UI, sans-serif';
                    ctx.fillStyle = '#334155';
                    ctx.textBaseline = 'middle';

                    columns.forEach((label, columnIndex) => {
                        const x = leftPad + (columnIndex * columnWidth) + (columnWidth / 2);
                        ctx.save();
                        ctx.translate(x, topPad - 18);
                        ctx.rotate(-0.45);
                        ctx.textAlign = 'right';
                        ctx.fillText(label, 0, 0);
                        ctx.restore();
                    });

                    rows.forEach((label, rowIndex) => {
                        const y = topPad + (rowIndex * rowHeight) + (rowHeight / 2);
                        ctx.textAlign = 'left';
                        ctx.fillStyle = '#0f172a';
                        ctx.fillText(label, 18, y);

                        (values[rowIndex] || []).forEach((value, columnIndex) => {
                            const x = leftPad + (columnIndex * columnWidth);
                            const cellY = topPad + (rowIndex * rowHeight);
                            const intensity = maxValue > 0 ? (Number(value || 0) / maxValue) : 0;
                            const alpha = 0.12 + (intensity * 0.88);

                            ctx.fillStyle = `rgba(15, 118, 110, ${alpha})`;
                            ctx.fillRect(x + 4, cellY + 4, columnWidth - 8, rowHeight - 8);

                            ctx.fillStyle = intensity > 0.55 ? '#ffffff' : '#0f172a';
                            ctx.textAlign = 'center';
                            ctx.fillText(String(value || 0), x + (columnWidth / 2), cellY + (rowHeight / 2));
                        });
                    });

                    ctx.strokeStyle = '#dbe4ef';
                    ctx.lineWidth = 1;
                    for (let rowIndex = 0; rowIndex <= rows.length; rowIndex++) {
                        const y = topPad + (rowIndex * rowHeight);
                        ctx.beginPath();
                        ctx.moveTo(leftPad, y);
                        ctx.lineTo(width - rightPad, y);
                        ctx.stroke();
                    }
                    for (let columnIndex = 0; columnIndex <= columns.length; columnIndex++) {
                        const x = leftPad + (columnIndex * columnWidth);
                        ctx.beginPath();
                        ctx.moveTo(x, topPad);
                        ctx.lineTo(x, height - bottomPad);
                        ctx.stroke();
                    }
                }

                const heatmapCanvas = document.getElementById('surveyHeatmapCanvas');
                if (heatmapCanvas) {
                    drawHeatmap(heatmapCanvas, heatmapPayload);
                }

                function downloadDataUrl(dataUrl, filename) {
                    if (!dataUrl) {
                        return;
                    }

                    const anchor = document.createElement('a');
                    anchor.href = dataUrl;
                    anchor.download = filename;
                    document.body.appendChild(anchor);
                    anchor.click();
                    anchor.remove();
                }

                document.querySelectorAll('[data-download-chart]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const chartKey = button.getAttribute('data-download-chart');
                        const chart = chartInstances[chartKey];
                        const dataUrl = chart?.toBase64Image?.() || '';
                        downloadDataUrl(dataUrl, `survey-report-${chartKey}.png`);
                    });
                });

                document.querySelectorAll('[data-download-canvas]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const canvas = document.getElementById(button.getAttribute('data-download-canvas'));
                        if (!canvas) {
                            return;
                        }

                        downloadDataUrl(canvas.toDataURL('image/png'), 'survey-report-heatmap.png');
                    });
                });

                document.getElementById('exportSurveyReportPdfBtn')?.addEventListener('click', () => {
                    const form = document.getElementById('surveyReportPdfForm');
                    if (!form) {
                        return;
                    }

                    document.getElementById('chart_trend').value = chartInstances.trend?.toBase64Image?.() || '';
                    document.getElementById('chart_pie').value = chartInstances.pie?.toBase64Image?.() || '';
                    document.getElementById('chart_bar').value = chartInstances.bar?.toBase64Image?.() || '';
                    document.getElementById('chart_heatmap').value = heatmapCanvas?.toDataURL('image/png') || '';
                    form.submit();
                });
            });
        </script>
    @endif
@endpush
