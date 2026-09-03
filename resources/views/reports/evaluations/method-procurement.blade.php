@extends('layouts.app')

@section('title', $methodDefinition['label'].' Report - '.($procurement->reference_no ?: $procurement->title))

@section('content')
@php
    $isServices = $method === \App\Models\Evaluation::TYPE_SERVICES;
    $metricValue = $resultSummary['value'] !== null
        ? number_format((float) $resultSummary['value'], $isServices ? 1 : 0).$resultSummary['suffix']
        : '—';
    $highestScore = $summary['highest_score'] !== null
        ? number_format((float) $summary['highest_score'], 2).'%'
        : '—';
    $summaryCards = [
        ['feather-users', 'Total applicants', number_format((int) $summary['applicants'])],
        ['feather-user-check', 'Evaluators', number_format((int) $summary['evaluators'])],
        ['feather-layers', 'Templates used', number_format((int) ($summary['templates'] ?? 0))],
        ['feather-award', 'Highest score', $highestScore],
    ];
@endphp

<main class="nxl-container evr-shell" aria-labelledby="procurementReportTitle">
    <header class="evr-hero">
        <div class="evr-hero__copy">
            <span class="evr-eyebrow">{{ $methodDefinition['label'] }} · {{ $methodDefinition['mode'] }}</span>
            <h1 id="procurementReportTitle">{{ $procurement->title ?: 'Untitled procurement' }}</h1>
            <p>Method report with a four-section management view: summary cards, evaluator-by-applicant scoring insight, detailed submission evidence, and applicant intelligence ranking.</p>
            <div class="evr-hero__meta">
                <span><i class="feather-hash" aria-hidden="true"></i>{{ $procurement->reference_no ?: 'No reference number' }}</span>
                <span><i class="feather-activity" aria-hidden="true"></i>{{ Str::headline($procurement->status ?: 'Status not specified') }}</span>
                @if ($summary['latest_at'])<span><i class="feather-clock" aria-hidden="true"></i>Updated {{ $summary['latest_at']->format('d M Y, H:i') }}</span>@endif
            </div>
        </div>
        <div class="evr-hero__actions evr-no-print" aria-label="Report export actions">
            <a href="{{ route('reports.evaluations.method', $method) }}" class="evr-btn evr-btn--ghost">
                <i class="feather-arrow-left" aria-hidden="true"></i> Procurement list
            </a>
            <a href="{{ route('reports.evaluations.method.procurement.excel', [$method, $procurement]) }}" class="evr-btn evr-btn--light">
                <i class="feather-grid" aria-hidden="true"></i> Excel
            </a>
            <a href="{{ route('reports.evaluations.method.procurement.csv', [$method, $procurement]) }}" class="evr-btn evr-btn--ghost">
                <i class="feather-file-text" aria-hidden="true"></i> CSV
            </a>
            <a href="{{ route('reports.evaluations.method.procurement.pdf', [$method, $procurement]) }}" class="evr-btn evr-btn--ghost">
                <i class="feather-download" aria-hidden="true"></i> PDF
            </a>
            <button type="button" class="evr-btn evr-btn--ghost" onclick="window.print()">
                <i class="feather-printer" aria-hidden="true"></i> Print
            </button>
        </div>
    </header>

    <section class="evr-section evr-panel" aria-labelledby="executive-summary">
        <header class="evr-panel__head">
            <div>
                <span class="evr-eyebrow">Section 1</span>
                <h2 id="executive-summary">Executive summary</h2>
                <p>Key outcome metrics for one-screen management visibility.</p>
            </div>
        </header>
        <div class="evr-panel__body">
            <div class="evr-kpi-grid evr-kpi-grid--summary">
                @foreach ($summaryCards as [$icon, $label, $value])
                    <article class="evr-kpi">
                        <span class="evr-kpi__icon"><i class="{{ $icon }}" aria-hidden="true"></i></span>
                        <div><span>{{ $label }}</span><strong>{{ $value }}</strong></div>
                    </article>
                @endforeach
            </div>

            <div class="evr-steps">
                <div class="evr-step">
                    <span class="evr-step__number">1</span>
                    <div>
                        <strong>Templates used</strong>
                        <p>{{ $summary['templates'] }} template{{ $summary['templates'] === 1 ? '' : 's' }} contributed to this report.</p>
                    </div>
                </div>
                <div class="evr-step">
                    <span class="evr-step__number">2</span>
                    <div>
                        <strong>{{ $resultSummary['label'] }}</strong>
                        <p>{{ $metricValue }}</p>
                    </div>
                </div>
                <div class="evr-step">
                    <span class="evr-step__number">3</span>
                    <div>
                        <strong>Highest evaluated score</strong>
                        <p>{{ $highestScore }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="evr-section evr-panel" aria-labelledby="section-two-title">
        <header class="evr-panel__head">
            <div>
                <span class="evr-eyebrow">Section 2</span>
        <h2 id="section-two-title">Evaluator graph component</h2>
                <p>One bar chart per applicant. X-axis shows evaluator names and Y-axis shows percentage scores.</p>
            </div>
        </header>
        <div class="evr-panel__body">
            @if ($evaluatorApplicantCharts->isNotEmpty())
                @forelse ($evaluatorApplicantCharts as $chartGroup)
                    <article class="evr-graph-section">
                        <header class="evr-graph-section__head">
                            <div>
                                <strong>{{ $chartGroup['evaluation']?->name ?? 'Evaluation' }}</strong>
                                <small>{{ $chartGroup['phase'] }}</small>
                            </div>
                            <span class="evr-evaluation-card__badge">{{ number_format((int) $chartGroup['applicant_count']) }} applicant{{ $chartGroup['applicant_count'] === 1 ? '' : 's' }}</span>
                        </header>

                        <div class="evr-graph-grid">
                            @forelse ($chartGroup['applicant_charts'] as $applicantChart)
                                @php
                                    $scores = $applicantChart['scores'];
                                    $scoreCount = $scores->count();
                                    $averageDisplay = $applicantChart['average_percentage'] !== null
                                        ? number_format((float) $applicantChart['average_percentage'], 2)
                                        : 'N/A';
                                @endphp
                                <article class="evr-chart-card">
                                    <header class="evr-chart-card__head">
                                        <div>
                                            <h4>{{ $applicantChart['submission_code'] }}</h4>
                                            <small>{{ $applicantChart['submission_name'] }}</small>
                                        </div>
                                        <span>{{ $scoreCount }} evaluator{{ $scoreCount === 1 ? '' : 's' }} · avg {{ $averageDisplay }}%</span>
                                    </header>
                                    @if ($scores->isNotEmpty())
                                        <div class="evr-chart-wrap">
                                            <canvas class="evr-evaluator-applicant-chart" data-chart='{{ e(json_encode($scores->map(fn ($score) => [
                                                'evaluator' => $score['evaluator'],
                                                'percentage' => (float) $score['percentage'],
                                            ])->values()->toArray())) }}'></canvas>
                                        </div>
                                    @else
                                        <div class="evr-chart-empty">No evaluator percentage yet for this applicant.</div>
                                    @endif
                                </article>
                            @empty
                                <p class="evr-empty-line">No applicant evaluations yet for this template.</p>
                            @endforelse
                        </div>
                    </article>
                @empty
                    <p class="evr-empty-line">No evaluator score series found for this method.</p>
                @endforelse
            @else
                <div class="evr-empty">
                    <span class="evr-empty__icon"><i class="feather-bar-chart-2"></i></span>
                <h3>No evaluator graph data available</h3>
                <p>Submit evaluator percentages for any completed submission to render section charts.</p>
            </div>
            @endif
        </div>
    </section>

    <section class="evr-section evr-panel" aria-labelledby="section-three-title">
        <header class="evr-panel__head">
            <div>
                <span class="evr-eyebrow">Section 3</span>
                <h2 id="section-three-title">Detailed evaluator submissions</h2>
                <p>Every evaluator submission, with section and criterion-level details.</p>
            </div>
        </header>
        <div class="evr-panel__body">
            @if ($submissionRows->isNotEmpty())
                <div class="evr-submission-stack">
                    @foreach ($submissionRows as $submissionRow)
                        <article class="evr-submission-card">
                            <header class="evr-submission-card__head">
                                <div>
                                    <strong>{{ $submissionRow['code'] }}</strong>
                                    <span>{{ $submissionRow['applicant'] }}</span>
                                </div>
                                <ul>
                                    <li><strong>{{ $submissionRow['evaluator'] }}</strong><small>{{ $submissionRow['evaluator_email'] ?: 'Email not available' }}</small></li>
                                    <li><strong>{{ $submissionRow['evaluation'] }}</strong><small>{{ $submissionRow['phase'] }}</small></li>
                                    <li><strong>{{ $submissionRow['result'] }}</strong><small>{{ $submissionRow['submitted_at']?->format('d M Y, H:i') ?: 'N/A' }}</small></li>
                                </ul>
                            </header>

                            <div class="evr-table-wrap">
                                <table class="evr-table">
                                    <thead>
                                        <tr>
                                            <th>Section</th>
                                            <th>Criterion</th>
                                            <th>Score</th>
                                            <th>Decision</th>
                                            <th>Comment</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($submissionRow['criterion_rows'] as $criterionRow)
                                            <tr>
                                                <td>{{ $criterionRow['section'] }}</td>
                                                <td>{{ $criterionRow['criterion'] }}</td>
                                                <td>{{ $criterionRow['score_display'] }}</td>
                                                <td>{{ $criterionRow['decision'] ?: 'N/A' }}</td>
                                                <td>{{ $criterionRow['comment'] }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5" class="text-center py-3">No criteria details were submitted.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="evr-empty">
                    <span class="evr-empty__icon"><i class="feather-file-minus"></i></span>
                    <h3>No detailed submissions</h3>
                    <p>No evaluator submissions are available yet.</p>
                </div>
            @endif
        </div>
    </section>

    <section class="evr-section evr-panel" aria-labelledby="section-four-title">
        <header class="evr-panel__head">
            <div>
                <span class="evr-eyebrow">Section 4</span>
                <h2 id="section-four-title">Applicant intelligence and ranking</h2>
                <p>Applicant score comparison with podium markers and medal icons for top performers.</p>
            </div>
        </header>
        <div class="evr-panel__body">
            @forelse ($intelligenceSummary as $group)
                @php
                    $groupRankings = collect($group['rankings'] ?? []);
                    $topRows = $groupRankings
                        ->filter(fn (array $row): bool => filled($row['rank']) && $row['rank'] <= 3)
                        ->values();
                @endphp
                    <article class="evr-evaluation-card">
                    <header class="evr-evaluation-card__head">
                        <div>
                            <span class="evr-eyebrow">{{ $group['phase'] ?? 'Applicant comparison' }}</span>
                            <h3>{{ $group['evaluation']?->name ?? 'Combined view' }}</h3>
                        <p>{{ number_format((int) ($group['ranked_applicants'] ?? 0)) }} ranked · {{ number_format((int) ($group['incomplete_applicants'] ?? 0)) }} awaiting complete panel</p>
                        <p>{{ $group['phase'] }} ranking set compares all applicants by outcome metric.</p>
                    </div>
                    <span class="evr-evaluation-card__badge">{{ number_format((int) $groupRankings->count()) }} applicant{{ $groupRankings->count() === 1 ? '' : 's' }}</span>
                </header>

                    <div class="evr-podium">
                        @forelse ($topRows as $top)
                            <article class="evr-podium-card evr-podium-card--{{ $top['medal'] ?? '' }}">
                                <span class="evr-medal">
                                    @if (($top['medal'] ?? null) === 'gold') 🥇
                                    @elseif (($top['medal'] ?? null) === 'silver') 🥈
                                    @elseif (($top['medal'] ?? null) === 'bronze') 🥉
                                    @else <i class="feather-hash" aria-hidden="true"></i> @endif
                                </span>
                                <small>Rank {{ (int) ($top['rank'] ?? 0) }}</small>
                                <h3>{{ $top['submission']?->display_name ?: 'Applicant not available' }}</h3>
                                <p>{{ $top['submission']?->procurement_submission_code ?: 'N/A' }}</p>
                                <strong class="evr-podium-score">{{ $top['metric'] !== null ? number_format((float) $top['metric'], 2).'%' : 'N/A' }}</strong>
                            </article>
                        @empty
                            <p class="evr-empty-line">No ranked applicants yet.</p>
                        @endforelse
                    </div>

                    <div class="evr-table-wrap">
                        <table class="evr-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Submission</th>
                                    <th>Applicant</th>
                                    <th>Metric</th>
                                    <th>Highest</th>
                                    <th>Lowest</th>
                                    <th>Spread</th>
                                    <th>Panel status</th>
                                    <th>Evaluators</th>
                                    <th>Medal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($groupRankings as $row)
                                    <tr>
                                <td>
                                    @if (in_array($row['medal'] ?? null, ['gold', 'silver', 'bronze']))
                                        <span class="evr-ranking-badge evr-ranking-badge--{{ $row['medal'] }}">
                                            @if (($row['medal'] ?? null) === 'gold') 🥇 GOLD
                                            @elseif (($row['medal'] ?? null) === 'silver') 🥈 SILVER
                                            @elseif (($row['medal'] ?? null) === 'bronze') 🥉 BRONZE
                                            @endif
                                        </span>
                                    @else
                                        N/A
                                    @endif
                                </td>
                                        <td><strong>{{ $row['submission']?->procurement_submission_code ?: 'N/A' }}</strong></td>
                                        <td>{{ $row['submission']?->display_name ?: 'Applicant not available' }}</td>
                                        <td>{{ $row['metric'] !== null ? number_format((float) $row['metric'], 2).'%' : 'N/A' }}</td>
                                        <td>{{ $row['highest'] !== null ? number_format((float) $row['highest'], 2) : 'N/A' }}</td>
                                        <td>{{ $row['lowest'] !== null ? number_format((float) $row['lowest'], 2) : 'N/A' }}</td>
                                        <td>{{ $row['spread'] !== null ? number_format((float) $row['spread'], 2) : 'N/A' }}</td>
                                        <td><span class="evr-outcome evr-outcome--{{ $row['outcome_tone'] ?? 'neutral' }}">{{ $row['outcome'] ?? $row['panel_status'] ?? 'N/A' }}</span></td>
                                        <td>{{ number_format((int) ($row['evaluators'] ?? 0)) }}</td>
                                        <td><small>{{ strtoupper((string) ($row['medal'] ?? '')) }}</small></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="10" class="text-center py-3">No ranked applicants for this evaluation set.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>
            @empty
                <div class="evr-empty">
                    <span class="evr-empty__icon"><i class="feather-users"></i></span>
                    <h3>No ranking data</h3>
                    <p>Applicant intelligence needs at least one completed numeric submission.</p>
                </div>
            @endforelse
        </div>
    </section>
</main>
@endsection

@push('styles')
    @include('reports.evaluations.partials.report-suite-styles')
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Chart === 'undefined') {
                return;
            }

            document.querySelectorAll('.evr-evaluator-applicant-chart').forEach((canvas) => {
                const rawRows = canvas.dataset.chart || '[]';
                let rows = [];

                try {
                    rows = JSON.parse(rawRows);
                } catch {
                    rows = [];
                }

                if (!Array.isArray(rows) || rows.length === 0) {
                    return;
                }

                const labels = rows.map((row) => row.evaluator || 'Unassigned');
                const percentages = rows.map((row) => Number(row.percentage ?? 0));
                const chart = new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            data: percentages,
                            backgroundColor: 'rgba(15, 118, 110, 0.25)',
                            borderColor: 'rgba(15, 118, 110, 0.95)',
                            borderWidth: 1,
                            borderRadius: 8,
                        }],
                    },
                    options: {
                        indexAxis: 'x',
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                ticks: {
                                    autoSkip: false,
                                    maxRotation: 35,
                                    color: '#4d6275',
                                    font: { size: 10 },
                                },
                                grid: { display: false },
                            },
                            y: {
                                beginAtZero: true,
                                max: 100,
                                ticks: {
                                    callback: (value) => `${value}%`,
                                    color: '#4d6275',
                                    font: { size: 11 },
                                },
                                grid: { color: '#ebf1f5' },
                            },
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: (context) => `${Number(context.parsed.y).toFixed(1)}%`,
                                },
                            },
                        },
                    },
                });

                if (chart) {
                    chart.resize();
                }
            });
        });
    </script>
@endpush


