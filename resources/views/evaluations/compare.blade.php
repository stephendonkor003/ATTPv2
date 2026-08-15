@extends('layouts.app')

@section('content')
    @php
        $decisionColors = [
            2 => 'success',
            1 => $evaluation->isEoi() ? 'warning' : 'success',
            0 => 'danger',
        ];
    @endphp

    <div class="nxl-container evaluation-compare">

        {{-- ================= PAGE HEADER ================= --}}
        <div class="page-header mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div>
                    <h4 class="fw-bold">Panel Evaluation Comparison</h4>
                    <p class="text-muted mb-0">
                        @if ($isNumeric)
                            Independent evaluator scores have been aggregated and ranked objectively.
                        @else
                            Evaluator decisions are summarized by category without assigning an overall outcome or applicant rank.
                        @endif
                    </p>
                </div>

                <span class="badge bg-{{ $evaluation->typeColor() }} px-3 py-2">
                    {{ $evaluation->typeLabel() }}
                </span>
            </div>
        </div>

        @if ($comparisons->isEmpty())
            <div class="alert alert-info d-flex align-items-center gap-2" role="status">
                <i class="feather-info" aria-hidden="true"></i>
                No submitted panel evaluations are available for comparison yet.
            </div>
        @else
            {{-- ================= EXECUTIVE SUMMARY ================= --}}
            @if ($isNumeric)
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card soft-card text-center p-3">
                            <small class="text-muted">Total Applicants</small>
                            <h3 class="fw-bold text-primary">{{ $comparisons->count() }}</h3>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card soft-card text-center p-3">
                            <small class="text-muted">Panel Evaluators</small>
                            <h3 class="fw-bold text-success">
                                {{ optional($comparisons->first())['evaluations']?->count() ?? 0 }}
                            </h3>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card soft-card text-center p-3">
                            <small class="text-muted">Panel Average Score</small>
                            <h3 class="fw-bold text-info">
                                {{ number_format($comparisons->avg('average'), 2) }}
                            </h3>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card soft-card text-center p-3">
                            <small class="text-muted">High Disagreement Cases</small>
                            <h3 class="fw-bold text-danger">
                                {{ $comparisons->where('spread', '>', 15)->count() }}
                            </h3>
                        </div>
                    </div>
                </div>
            @else
                <div class="row g-3 mb-4">
                    <div class="col-sm-6 col-xl-3">
                        <div class="card soft-card text-center h-100 p-3">
                            <small class="text-muted">Total Applicants</small>
                            <h3 class="fw-bold text-primary mb-0">{{ $comparisons->count() }}</h3>
                        </div>
                    </div>

                    <div class="col-sm-6 col-xl-3">
                        <div class="card soft-card text-center h-100 p-3">
                            <small class="text-muted">Panel Evaluators</small>
                            <h3 class="fw-bold text-success mb-0">
                                {{ $comparisons->flatMap(fn ($row) => $row['evaluations'])->pluck('evaluator_id')->filter()->unique()->count() }}
                            </h3>
                        </div>
                    </div>

                    <div class="col-sm-6 col-xl-3">
                        <div class="card soft-card text-center h-100 p-3">
                            <small class="text-muted">Decision Categories</small>
                            <h3 class="fw-bold text-info mb-0">{{ count($decisionOptions) }}</h3>
                        </div>
                    </div>

                    <div class="col-sm-6 col-xl-3">
                        <div class="card soft-card text-center h-100 p-3">
                            <small class="text-muted">Recorded Decisions</small>
                            <h3 class="fw-bold text-dark mb-0">{{ $comparisons->sum('total_decisions') }}</h3>
                        </div>
                    </div>
                </div>
            @endif

            @if ($isNumeric)
                {{-- ================= RANKING TABLE ================= --}}
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white fw-bold">
                        Applicant Ranking Overview
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="60">Rank</th>
                                    <th>Submission Code</th>
                                    <th class="text-center">Average</th>
                                    <th class="text-center">Highest</th>
                                    <th class="text-center">Lowest</th>
                                    <th class="text-center">Spread</th>
                                    <th class="text-center">Evaluators</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($comparisons as $index => $row)
                                    <tr>
                                        <td class="text-center fw-bold">
                                            <span class="badge bg-dark">{{ $index + 1 }}</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                {{ $row['submission_code'] }}
                                            </span>
                                            @if ($index === 0)
                                                <span class="badge bg-success ms-2">
                                                    <i class="feather-award me-1"></i> Top Ranked
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-center fw-bold text-primary">{{ $row['average'] }}</td>
                                        <td class="text-center text-success">{{ $row['highest'] }}</td>
                                        <td class="text-center text-danger">{{ $row['lowest'] }}</td>
                                        <td class="text-center">
                                            <span class="badge {{ $row['spread'] > 15 ? 'bg-danger' : 'bg-warning text-dark' }}">
                                                {{ $row['spread'] }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            {{ $row['evaluations']->count() }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- ================= DETAILED NUMERIC ANALYSIS ================= --}}
                @foreach ($comparisons as $rank => $row)
                    <div class="card shadow-sm mb-4 border-start border-4 border-primary">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>
                                Rank {{ $rank + 1 }} &mdash; {{ $row['submission_code'] }}
                            </strong>
                            <span class="badge bg-primary">
                                Avg Score: {{ $row['average'] }}
                            </span>
                        </div>

                        <div class="card-body">
                            <table class="table table-sm table-bordered mb-3">
                                <thead class="table-light">
                                    <tr>
                                        <th>Evaluator</th>
                                        <th class="text-center">Score</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($row['evaluations'] as $evaluationSubmission)
                                        <tr>
                                            <td>{{ $evaluationSubmission->evaluator->name }}</td>
                                            <td class="text-center fw-bold">
                                                {{ $evaluationSubmission->overall_score }}
                                            </td>
                                            <td>
                                                <span class="badge bg-success">Submitted</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            <div class="d-flex justify-content-end">
                                <button class="btn btn-outline-secondary btn-sm"
                                    onclick="toggleChart('{{ $row['submission_code'] }}')">
                                    <i class="feather-bar-chart-2 me-1"></i>
                                    View Score Chart
                                </button>
                            </div>

                            <div class="mt-4 d-none" id="chart-{{ $row['submission_code'] }}">
                                <canvas height="120"></canvas>
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                {{-- ================= CATEGORICAL DISTRIBUTION OVERVIEW ================= --}}
                <div class="alert alert-light border d-flex align-items-start gap-2 mb-4" role="note">
                    <i class="feather-info text-info mt-1" aria-hidden="true"></i>
                    <div class="small text-muted">
                        Counts represent criterion-level decisions recorded by the panel. They are a distribution only and do not
                        create an overall applicant outcome, average score, or rank.
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white">
                        <div class="fw-bold">Applicant Decision Distribution</div>
                        <small>Combined decisions from all submitted panel evaluations</small>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Submission Code</th>
                                    <th class="text-center">Evaluators</th>
                                    @foreach ($decisionOptions as $decision => $fallbackLabel)
                                        <th class="text-center">
                                            {{ $evaluation->decisionLabel($decision) ?? $fallbackLabel }}
                                        </th>
                                    @endforeach
                                    <th class="text-center">Recorded Decisions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($comparisons as $row)
                                    <tr>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                {{ $row['submission_code'] }}
                                            </span>
                                        </td>
                                        <td class="text-center">{{ $row['evaluations']->count() }}</td>
                                        @foreach ($decisionOptions as $decision => $fallbackLabel)
                                            @php
                                                $decisionStat = $row['decision_counts']->firstWhere('decision', (int) $decision);
                                                $decisionCount = $decisionStat['count'] ?? 0;
                                                $decisionColor = $decisionColors[(int) $decision] ?? 'secondary';
                                            @endphp
                                            <td class="text-center">
                                                <span class="badge bg-{{ $decisionColor }}-subtle text-{{ $decisionColor }} border">
                                                    {{ $decisionCount }}
                                                </span>
                                            </td>
                                        @endforeach
                                        <td class="text-center fw-bold">{{ $row['total_decisions'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- ================= DETAILED CATEGORICAL ANALYSIS ================= --}}
                @foreach ($comparisons as $row)
                    <div class="card shadow-sm mb-4 border-start border-4 border-info">
                        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <strong>{{ $row['submission_code'] }}</strong>
                            <span class="badge bg-info">
                                {{ $row['total_decisions'] }} recorded {{ \Illuminate\Support\Str::plural('decision', $row['total_decisions']) }}
                            </span>
                        </div>

                        <div class="card-body">
                            <h6 class="fw-semibold mb-3">Combined decision distribution</h6>

                            <div class="row g-3 mb-4">
                                @foreach ($decisionOptions as $decision => $fallbackLabel)
                                    @php
                                        $decisionLabel = $evaluation->decisionLabel($decision) ?? $fallbackLabel;
                                        $decisionStat = $row['decision_counts']->firstWhere('decision', (int) $decision);
                                        $decisionCount = $decisionStat['count'] ?? 0;
                                        $decisionPercentage = $row['total_decisions'] > 0
                                            ? round(($decisionCount / $row['total_decisions']) * 100, 1)
                                            : 0;
                                        $decisionColor = $decisionColors[(int) $decision] ?? 'secondary';
                                    @endphp

                                    <div class="col-md-{{ count($decisionOptions) === 2 ? '6' : '4' }}">
                                        <div class="border rounded p-3 h-100">
                                            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                                <span class="fw-semibold">{{ $decisionLabel }}</span>
                                                <span class="badge bg-{{ $decisionColor }}">{{ $decisionCount }}</span>
                                            </div>
                                            <div class="progress" style="height: 8px;" role="progressbar"
                                                aria-label="{{ $decisionLabel }} decisions"
                                                aria-valuenow="{{ $decisionPercentage }}" aria-valuemin="0" aria-valuemax="100">
                                                <div class="progress-bar bg-{{ $decisionColor }}"
                                                    style="width: {{ $decisionPercentage }}%"></div>
                                            </div>
                                            <div class="small text-muted mt-2">{{ number_format($decisionPercentage, 1) }}% of recorded decisions</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <h6 class="fw-semibold mb-3">Evaluator breakdown</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Evaluator</th>
                                            @foreach ($decisionOptions as $decision => $fallbackLabel)
                                                <th class="text-center">
                                                    {{ $evaluation->decisionLabel($decision) ?? $fallbackLabel }}
                                                </th>
                                            @endforeach
                                            <th class="text-center">Recorded Decisions</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($row['evaluations'] as $evaluationSubmission)
                                            @php
                                                $recordedDecisionCount = $evaluationSubmission->criteriaScores
                                                    ->filter(fn ($score) => $score->decision !== null
                                                        && $score->decision !== ''
                                                        && array_key_exists((int) $score->decision, $decisionOptions))
                                                    ->count();
                                            @endphp
                                            <tr>
                                                <td>{{ $evaluationSubmission->evaluator?->name ?? 'Evaluator' }}</td>
                                                @foreach ($decisionOptions as $decision => $fallbackLabel)
                                                    <td class="text-center">
                                                        {{ $evaluationSubmission->criteriaScores
                                                            ->filter(fn ($score) => $score->decision !== null
                                                                && $score->decision !== ''
                                                                && (int) $score->decision === (int) $decision)
                                                            ->count() }}
                                                    </td>
                                                @endforeach
                                                <td class="text-center fw-semibold">{{ $recordedDecisionCount }}</td>
                                                <td><span class="badge bg-success">Submitted</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif
        @endif
    </div>

    @if ($isNumeric && $comparisons->isNotEmpty())
        {{-- ================= NUMERIC-ONLY CHARTS ================= --}}
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            const comparisons = @json($comparisons);

            function toggleChart(code) {
                const el = document.getElementById('chart-' + code);
                el.classList.toggle('d-none');

                if (!el.dataset.loaded) {
                    renderChart(el, code);
                    el.dataset.loaded = true;
                }
            }

            function renderChart(container, code) {
                const ctx = container.querySelector('canvas').getContext('2d');
                const row = comparisons.find(
                    r => r.submission_code === code
                );

                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: row.evaluations.map(e => e.evaluator.name),
                        datasets: [{
                            data: row.evaluations.map(e => e.overall_score),
                            backgroundColor: '#0d6efd'
                        }]
                    },
                    options: {
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }
        </script>
    @endif
@endsection
