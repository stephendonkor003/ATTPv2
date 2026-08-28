@extends('layouts.app')

@section('title', $methodDefinition['label'].' Report - '.($procurement->reference_no ?: $procurement->title))

@section('content')
    @php
        $isServices = $method === \App\Models\Evaluation::TYPE_SERVICES;
        $isGoods = $method === \App\Models\Evaluation::TYPE_GOODS;
        $metricValue = $resultSummary['value'] !== null
            ? number_format($resultSummary['value'], $isServices ? 1 : 0).$resultSummary['suffix']
            : '—';
    @endphp

    <main class="nxl-container evr-shell" aria-labelledby="procurementReportTitle">
        <header class="evr-hero">
            <div class="evr-hero__copy">
                <span class="evr-eyebrow">{{ $methodDefinition['label'] }} · {{ $methodDefinition['mode'] }}</span>
                <h1 id="procurementReportTitle">{{ $procurement->title ?: 'Untitled procurement' }}</h1>
                <p>Method-specific panel report with applicant outcomes, evaluator activity, criterion analysis, and the full submitted evaluation trail.</p>
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

        <section class="evr-kpi-grid" aria-label="Report summary">
            @foreach ([
                ['feather-users', 'Applicants evaluated', $summary['applicants'], null],
                ['feather-file-text', 'Panel reports', $summary['total'], null],
                ['feather-user-check', 'Evaluators', $summary['evaluators'], null],
                ['feather-layers', 'Templates used', $summary['templates'], null],
                [$methodDefinition['icon'], $resultSummary['label'], $metricValue, $resultSummary['detail']],
            ] as [$icon, $label, $value, $detail])
                <article class="evr-kpi">
                    <span class="evr-kpi__icon"><i class="{{ $icon }}" aria-hidden="true"></i></span>
                    <div><span>{{ $label }}</span><strong>{{ is_numeric($value) ? number_format($value) : $value }}</strong>@if ($detail)<small>{{ $detail }}</small>@endif</div>
                </article>
            @endforeach
        </section>

        @if ($isServices && $summary['configuration_warnings'] > 0)
            <div class="evr-alert evr-alert--warning" role="alert">
                <i class="feather-alert-triangle" aria-hidden="true"></i>
                <div><strong>Scoring configuration needs attention</strong><span>{{ number_format($summary['configuration_warnings']) }} submitted report(s) were excluded from percentage calculations because their evaluation template has no positive maximum score.</span></div>
            </div>
        @endif

        @if ($isServices)
            <section class="evr-section evr-panel" aria-labelledby="rankingTitle">
                <header class="evr-panel__head">
                    <div>
                        <span class="evr-eyebrow">Panel scoring</span>
                        <h2 id="rankingTitle">Applicant rankings by evaluation</h2>
                        <p>Each evaluation is ranked separately. Scores are normalised against that template's configured maximum, and medals appear only after every assigned panel member has submitted.</p>
                    </div>
                    <span class="evr-evaluation-card__badge"><i class="feather-award" aria-hidden="true"></i> Complete panels only</span>
                </header>

                <div class="evr-panel__body">
                    <div class="evr-evaluation-stack">
                        @forelse ($serviceRankingGroups as $rankingGroup)
                            @php
                                $groupRankings = $rankingGroup['rankings'];
                                $topApplicants = $groupRankings
                                    ->filter(fn (array $row): bool => $row['rank'] !== null && $row['rank'] <= 3)
                                    ->values();
                            @endphp
                            <article class="evr-evaluation-card">
                                <header class="evr-evaluation-card__head">
                                    <div>
                                        <span class="evr-eyebrow">{{ $rankingGroup['phase'] }}</span>
                                        <h3>{{ $rankingGroup['evaluation']->name }}</h3>
                                        <p>{{ number_format($rankingGroup['ranked_applicants']) }} ranked · {{ number_format($rankingGroup['incomplete_applicants']) }} awaiting panel completion</p>
                                    </div>
                                    <span class="evr-evaluation-card__badge">Separate ranking</span>
                                </header>

                                @if ($topApplicants->isNotEmpty())
                                    <div class="evr-podium" aria-label="Top ranked applicants for {{ $rankingGroup['evaluation']->name }}">
                                        @foreach ($topApplicants as $row)
                                            @php
                                                $podiumClass = match ((int) $row['rank']) { 1 => 'first', 2 => 'second', default => 'third' };
                                            @endphp
                                            <article class="evr-podium-card evr-podium-card--{{ $podiumClass }}">
                                                <span class="evr-medal"><i class="feather-award" aria-hidden="true"></i></span>
                                                <small>Rank {{ $row['rank'] }}</small>
                                                <h3>{{ $row['submission']?->display_name ?: 'Applicant not available' }}</h3>
                                                <p>{{ $row['submission']?->procurement_submission_code ?: 'No submission code' }}</p>
                                                <strong class="evr-podium-score">{{ number_format($row['metric'], 1) }}%</strong>
                                            </article>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="evr-table-wrap">
                                    <table class="evr-table">
                                        <thead><tr><th>Rank</th><th>Submission</th><th>Applicant</th><th>Panel average</th><th>Score range</th><th>Panel status</th><th>Evaluators</th></tr></thead>
                                        <tbody>
                                            @forelse ($groupRankings as $row)
                                                <tr>
                                                    <td><span class="evr-rank {{ $row['rank'] && $row['rank'] <= 3 ? 'evr-rank--'.$row['rank'] : '' }}">{{ $row['rank'] ? '#'.$row['rank'] : '—' }}</span></td>
                                                    <td><strong>{{ $row['submission']?->procurement_submission_code ?: 'N/A' }}</strong></td>
                                                    <td>{{ $row['submission']?->display_name ?: 'Applicant not available' }}</td>
                                                    <td><span class="evr-score">{{ $row['metric'] !== null ? number_format($row['metric'], 1).'%' : '—' }}</span></td>
                                                    <td>{{ $row['lowest'] !== null ? number_format($row['lowest'], 1).'–'.number_format($row['highest'], 1).'%' : '—' }}@if ($row['spread'] !== null)<small>{{ number_format($row['spread'], 1) }} point spread</small>@endif</td>
                                                    <td><span class="evr-outcome evr-outcome--{{ $row['outcome_tone'] }}">{{ $row['panel_status'] }}</span><small>{{ $row['expected_tasks'] !== null ? $row['completed_tasks'].'/'.$row['expected_tasks'].' assigned tasks' : $row['completed_tasks'].' recorded report(s)' }}</small></td>
                                                    <td>{{ number_format($row['evaluators']) }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="7" class="text-center py-4">No scored submissions are available for this evaluation.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </article>
                        @empty
                            <div class="evr-empty"><span class="evr-empty__icon"><i class="feather-bar-chart-2" aria-hidden="true"></i></span><h3>No scored Services evaluations yet</h3><p>Rankings appear after assigned panel members submit valid scores.</p></div>
                        @endforelse
                    </div>
                </div>
            </section>
        @else
            <section class="evr-section evr-panel" aria-labelledby="outcomesTitle">
                <header class="evr-panel__head">
                    <div>
                        <span class="evr-eyebrow">Compliance evidence</span>
                        <h2 id="outcomesTitle">Applicant compliance summary</h2>
                        <p>Goods evaluations are categorical. Yes and No decisions are shown as submitted evidence, not converted into numeric ranks or treated as a final award decision.</p>
                    </div>
                    <span class="evr-evaluation-card__badge"><i class="feather-check-square" aria-hidden="true"></i> Decision counts</span>
                </header>
                <div class="evr-table-wrap">
                    <table class="evr-table">
                        <thead><tr><th>Submission</th><th>Applicant</th><th>Submitted evidence</th><th>Yes decisions</th><th>No decisions</th><th>Panel status</th><th>Evaluators</th><th>Reports</th></tr></thead>
                        <tbody>
                            @forelse ($applicantSummaries as $row)
                                <tr>
                                    <td><strong>{{ $row['submission']?->procurement_submission_code ?: 'N/A' }}</strong></td>
                                    <td>{{ $row['submission']?->display_name ?: 'Applicant not available' }}</td>
                                    <td><span class="evr-outcome evr-outcome--{{ $row['outcome_tone'] }}">{{ $row['outcome'] }}</span></td>
                                    <td>{{ number_format($row['counts']['yes'] ?? 0) }}</td>
                                    <td>{{ number_format($row['counts']['no'] ?? 0) }}</td>
                                    <td><span class="evr-outcome evr-outcome--{{ $row['panel_complete'] ? 'positive' : 'attention' }}">{{ $row['panel_status'] }}</span><small>{{ $row['expected_tasks'] !== null ? $row['completed_tasks'].'/'.$row['expected_tasks'].' assigned tasks' : $row['completed_tasks'].' recorded report(s)' }}</small></td>
                                    <td>{{ number_format($row['evaluators']) }}</td>
                                    <td>{{ number_format($row['evaluations']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center py-4">No Goods decisions have been submitted yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <div class="evr-detail-grid">
            <section class="evr-panel" aria-labelledby="criteriaAnalysisTitle">
                <header class="evr-panel__head">
                    <div><span class="evr-eyebrow">Evaluation data</span><h2 id="criteriaAnalysisTitle">Criteria analysis</h2><p>Results remain separated by evaluation template.</p></div>
                </header>
                <div class="evr-panel__body">
                    <div class="evr-evaluation-stack">
                        @forelse ($evaluationStats as $stat)
                            <article class="evr-evaluation-card">
                                <header class="evr-evaluation-card__head">
                                    <div><h3>{{ $stat['evaluation']->name }}</h3><p>{{ number_format($stat['total']) }} submitted evaluation(s)</p></div>
                                    <span class="evr-evaluation-card__badge">{{ $stat['evaluation']->typeLabel() }}</span>
                                </header>
                                <div class="evr-table-wrap">
                                    <table class="evr-table">
                                        <thead>
                                            <tr>
                                                <th>Criterion</th>
                                                @if ($isServices)<th>Maximum</th><th>Average score</th><th>Samples</th>
                                                @else<th>Yes</th><th>No</th><th>Pass rate</th><th>Samples</th>@endif
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($stat['criteria_stats'] as $criterion)
                                                <tr>
                                                    <td><strong>{{ $criterion['name'] }}</strong></td>
                                                    @if ($isServices)
                                                        <td>{{ number_format((float) $criterion['max'], 2) }}</td>
                                                        <td><span class="evr-score">{{ number_format((float) $criterion['avg'], 2) }}</span></td>
                                                        <td>{{ number_format($criterion['total']) }}</td>
                                                    @else
                                                        <td>{{ number_format($criterion['yes']) }}</td>
                                                        <td>{{ number_format($criterion['no']) }}</td>
                                                        <td>{{ number_format($criterion['rate'], 1) }}%</td>
                                                        <td>{{ number_format($criterion['total']) }}</td>
                                                    @endif
                                                </tr>
                                            @empty
                                                <tr><td colspan="{{ $isServices ? 4 : 5 }}" class="text-center py-3">No criterion results are available.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </article>
                        @empty
                            <div class="evr-empty"><span class="evr-empty__icon"><i class="feather-bar-chart-2"></i></span><h3>No evaluation data yet</h3><p>Criterion analysis appears after evaluators submit.</p></div>
                        @endforelse
                    </div>
                </div>
            </section>

            <aside class="evr-panel" aria-labelledby="evaluatorActivityTitle">
                <header class="evr-panel__head"><div><span class="evr-eyebrow">Panel activity</span><h2 id="evaluatorActivityTitle">Evaluators</h2></div></header>
                <div class="evr-panel__body">
                    <div class="evr-evaluator-list">
                        @forelse ($evaluatorBreakdown as $evaluator)
                            <article class="evr-evaluator">
                                <span class="evr-avatar">{{ Str::upper(Str::substr($evaluator['name'], 0, 1)) }}</span>
                                <div><strong>{{ $evaluator['name'] }}</strong><small>{{ $evaluator['email'] ?: 'Email not available' }}</small></div>
                                <div class="evr-evaluator__count">{{ number_format($evaluator['total']) }}<small>reports</small></div>
                            </article>
                        @empty
                            <div class="evr-empty"><span class="evr-empty__icon"><i class="feather-users"></i></span><h3>No panel activity</h3></div>
                        @endforelse
                    </div>
                </div>
            </aside>
        </div>

        <section class="evr-section evr-panel" aria-labelledby="evaluationAuditTitle">
            <header class="evr-panel__head">
                <div><span class="evr-eyebrow">Audit trail</span><h2 id="evaluationAuditTitle">Submitted evaluations</h2><p>Open an individual report for section scores, criterion comments, and evidence.</p></div>
                <span class="evr-evaluation-card__badge">{{ number_format($submissionRows->count()) }} reports</span>
            </header>
            <div class="evr-table-wrap">
                <table class="evr-table">
                    <thead><tr><th>Submission</th><th>Applicant</th><th>Evaluation</th><th>Phase</th><th>Evaluator</th><th>Result</th><th>Submitted</th><th class="evr-no-print">Action</th></tr></thead>
                    <tbody>
                        @forelse ($submissionRows as $row)
                            <tr>
                                <td><strong>{{ $row['code'] }}</strong></td>
                                <td>{{ $row['applicant'] }}</td>
                                <td><strong>{{ $row['evaluation'] }}</strong></td>
                                <td>{{ $row['phase'] }}</td>
                                <td>{{ $row['evaluator'] }}<small>{{ $row['evaluator_email'] }}</small></td>
                                <td><span class="evr-outcome {{ $isServices ? 'evr-outcome--positive' : '' }}">{{ $row['result'] }}</span></td>
                                <td>{{ $row['submitted_at']?->format('d M Y, H:i') ?: 'N/A' }}</td>
                                <td class="evr-no-print"><a href="{{ route('reports.evaluations.submission', $row['submission']) }}" class="evr-btn evr-btn--outline"><i class="feather-eye"></i> View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center py-4">No completed evaluations have been submitted.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>
@endsection

@push('styles')
    @include('reports.evaluations.partials.report-suite-styles')
@endpush
