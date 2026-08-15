@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        <div class="page-header mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1">Procurement Evaluation Report</h4>
                <p class="text-muted mb-0">{{ $procurement->title }} ({{ $procurement->reference_no ?? 'N/A' }})</p>
            </div>
            <a href="{{ route('reports.evaluations.index') }}" class="btn btn-outline-secondary btn-sm">
                Back to Reports
            </a>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="text-muted">Total Evaluations</div>
                        <div class="h4 mb-0">{{ $summary['total'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="text-muted">Evaluators</div>
                        <div class="h4 mb-0">{{ $summary['evaluators'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="text-muted">Average Numeric Score</div>
                        <div class="h4 mb-0">
                            {{ $summary['avg_overall'] !== null ? number_format($summary['avg_overall'], 2) : 'N/A' }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 text-end">
                <a href="{{ route('reports.evaluations.procurement.pdf', $procurement) }}" class="btn btn-success">
                    Download PDF
                </a>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Applicant Ranking</span>
                <span class="badge bg-warning text-dark">Average Panel Score</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="70" class="text-center">Rank</th>
                            <th>Submission</th>
                            <th>Applicant</th>
                            <th class="text-center">Average</th>
                            <th class="text-center">Highest</th>
                            <th class="text-center">Lowest</th>
                            <th class="text-center">Spread</th>
                            <th class="text-center">Evaluators</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rankings as $row)
                            <tr>
                                <td class="text-center">
                                    @if ($row['rank'] !== null)
                                        <span class="badge {{ $row['rank'] === 1 ? 'bg-success' : 'bg-secondary' }}">
                                            #{{ $row['rank'] }}
                                        </span>
                                    @else
                                        <span class="badge bg-info text-dark">Categorical</span>
                                    @endif
                                </td>
                                <td class="fw-semibold text-primary">
                                    {{ $row['submission']?->procurement_submission_code ?? 'N/A' }}
                                </td>
                                <td>{{ $row['submission']?->submitter?->name ?? 'N/A' }}</td>
                                <td class="text-center fw-bold">{{ $row['average'] !== null ? number_format($row['average'], 2) : 'N/A' }}</td>
                                <td class="text-center text-success">{{ $row['highest'] !== null ? number_format($row['highest'], 2) : 'N/A' }}</td>
                                <td class="text-center text-danger">{{ $row['lowest'] !== null ? number_format($row['lowest'], 2) : 'N/A' }}</td>
                                <td class="text-center">{{ $row['spread'] !== null ? number_format($row['spread'], 2) : 'N/A' }}</td>
                                <td class="text-center">{{ $row['evaluators'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-3">No ranked applicants yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light fw-semibold">Evaluator Breakdown</div>
            <div class="card-body">
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th>Evaluator</th>
                            <th>Total Evaluations</th>
                            <th>Average Numeric Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($evaluatorBreakdown as $name => $data)
                            <tr>
                                <td>{{ $name }}</td>
                                <td>{{ $data['total'] }}</td>
                                <td>{{ $data['avg_overall'] !== null ? number_format($data['avg_overall'], 2) : 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3">No evaluations submitted yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @foreach ($evaluationStats as $stat)
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <span>{{ $stat['evaluation']->name }}</span>
                    <span class="badge bg-light text-dark">{{ $stat['evaluation']->typeLabel() }}</span>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <div class="text-muted">Total Evaluations</div>
                            <div class="fw-semibold">{{ $stat['total'] }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted">Average Numeric Score</div>
                            <div class="fw-semibold">
                                {{ $stat['avg_overall'] !== null ? number_format($stat['avg_overall'], 2) : 'Categorical decisions' }}
                            </div>
                        </div>
                    </div>

                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>Criteria</th>
                                @if ($stat['evaluation']->isGoods())
                                    <th>Yes</th>
                                    <th>No</th>
                                    <th>Pass Rate</th>
                                @elseif ($stat['evaluation']->isEoi())
                                    @foreach ($stat['evaluation']->decisionOptions() as $decisionLabel)
                                        <th>{{ $decisionLabel }}</th>
                                    @endforeach
                                    <th>Samples</th>
                                @else
                                    <th>Max</th>
                                    <th>Average Score</th>
                                    <th>Samples</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($stat['criteria_stats'] as $criteria)
                                <tr>
                                    <td>{{ $criteria['name'] }}</td>
                                    @if ($stat['evaluation']->isGoods())
                                        <td>{{ $criteria['yes'] }}</td>
                                        <td>{{ $criteria['no'] }}</td>
                                        <td>{{ $criteria['rate'] }}%</td>
                                    @elseif ($stat['evaluation']->isEoi())
                                        <td>{{ $criteria['qualified'] }}</td>
                                        <td>{{ $criteria['average_qualified'] }}</td>
                                        <td>{{ $criteria['not_qualified'] }}</td>
                                        <td>{{ $criteria['total'] }}</td>
                                    @else
                                        <td>{{ $criteria['max'] }}</td>
                                        <td>{{ number_format($criteria['avg'], 2) }}</td>
                                        <td>{{ $criteria['total'] }}</td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $stat['evaluation']->isEoi() ? 5 : 4 }}">No criteria data available.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        <div class="card shadow-sm">
            <div class="card-header bg-light fw-semibold">Submitted Evaluations</div>
            <div class="card-body">
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th>Submission Code</th>
                            <th>Applicant</th>
                            <th>Evaluation</th>
                            <th>Evaluator</th>
                            <th>Result</th>
                            <th>Submitted At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($submissions as $submission)
                            <tr>
                                <td>{{ $submission->applicant?->procurement_submission_code ?? 'N/A' }}</td>
                                <td>{{ $submission->applicant?->submitter?->name ?? 'N/A' }}</td>
                                <td>{{ $submission->evaluation?->name ?? 'N/A' }}</td>
                                <td>{{ $submission->evaluator?->name ?? 'N/A' }}</td>
                                <td>
                                    @if ($submission->evaluation?->usesNumericScoring())
                                        {{ $submission->overall_score !== null ? number_format($submission->overall_score, 2) : 'N/A' }}
                                    @else
                                        @php
                                            $decisionSummary = collect($submission->evaluation?->decisionOptions() ?? [])
                                                ->map(function (string $label, int $decision) use ($submission) {
                                                    return $submission->criteriaScores->where('decision', $decision)->count().' '.$label;
                                                })
                                                ->implode(' / ');
                                        @endphp
                                        {{ $decisionSummary ?: 'No decisions recorded' }}
                                    @endif
                                </td>
                                <td>{{ $submission->submitted_at?->format('d M Y, H:i') ?? 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">No evaluations submitted yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
