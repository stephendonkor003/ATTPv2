@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        <div class="page-header mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1">Consolidated Evaluation Report</h4>
                <p class="text-muted mb-0">All evaluations across all procurements.</p>
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
                        <div class="text-muted">Procurements</div>
                        <div class="h4 mb-0">{{ $summary['procurements'] }}</div>
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
            <div class="col-md-3 text-end">
                <a href="{{ route('reports.evaluations.consolidated.pdf') }}" class="btn btn-success">
                    Download PDF
                </a>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light fw-semibold">Procurement Summary</div>
            <div class="card-body">
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th>Procurement</th>
                            <th>Total Evaluations</th>
                            <th>Evaluators</th>
                            <th>Average Numeric Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($procurementStats as $stat)
                            <tr>
                                <td>{{ $stat['procurement']->title ?? 'N/A' }}</td>
                                <td>{{ $stat['total'] }}</td>
                                <td>{{ $stat['evaluators'] }}</td>
                                <td>{{ $stat['avg_overall'] !== null ? number_format($stat['avg_overall'], 2) : 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">No evaluation data available.</td>
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
                        @forelse ($evaluatorBreakdown as $data)
                            <tr>
                                <td>
                                    {{ $data['name'] }}
                                    @if ($data['email'])
                                        <small class="d-block text-muted">{{ $data['email'] }}</small>
                                    @endif
                                </td>
                                <td>{{ $data['total'] }}</td>
                                <td>{{ $data['avg_overall'] !== null ? number_format($data['avg_overall'], 2) : 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3">No evaluations submitted.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-light fw-semibold">All Submitted Evaluations</div>
            <div class="card-body">
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th>Procurement</th>
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
                                <td>{{ $submission->procurement->title ?? 'N/A' }}</td>
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
                                <td colspan="7">No evaluations submitted.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
