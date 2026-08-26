<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Consolidated Evaluation Report</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #111; }
        h1, h2 { margin: 0 0 8px 0; color: #0f172a; }
        .section { margin: 18px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; }
        th { background: #f2f2f2; text-align: left; }
        .header {
            background: #0f172a;
            color: #fff;
            padding: 14px 18px;
            border-bottom: 4px solid #22c55e;
        }
        .header .title { font-size: 18px; font-weight: bold; }
        .header .meta { font-size: 11px; margin-top: 6px; color: #e2e8f0; }
        .footer {
            position: fixed;
            bottom: -10px;
            left: 0;
            right: 0;
            height: 42px;
            background: #0f172a;
            color: #e2e8f0;
            font-size: 10px;
            padding: 10px 16px;
            border-top: 3px solid #f59e0b;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">{{ config('app.name') }} - Consolidated Evaluation Report</div>
        <div class="meta">
            Generated: {{ now()->format('Y-m-d H:i') }}
        </div>
    </div>

    <div class="section">
        <h2>Summary</h2>
        <table>
            <tr>
                <th>Total Evaluations</th>
                <th>Procurements</th>
                <th>Evaluators</th>
                <th>Average Numeric Score</th>
            </tr>
            <tr>
                <td>{{ $summary['total'] }}</td>
                <td>{{ $summary['procurements'] }}</td>
                <td>{{ $summary['evaluators'] }}</td>
                <td>{{ $summary['avg_overall'] !== null ? number_format($summary['avg_overall'], 2) : 'N/A' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>Procurement Summary</h2>
        <table>
            <tr>
                <th>Procurement</th>
                <th>Total Evaluations</th>
                <th>Evaluators</th>
                <th>Average Numeric Score</th>
            </tr>
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
        </table>
    </div>

    <div class="section">
        <h2>Evaluator Breakdown</h2>
        <table>
            <tr>
                <th>Evaluator</th>
                <th>Total Evaluations</th>
                <th>Average Numeric Score</th>
            </tr>
            @forelse ($evaluatorBreakdown as $data)
                <tr>
                    <td>
                        {{ $data['name'] }}
                        @if ($data['email'])
                            <br><small>{{ $data['email'] }}</small>
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
        </table>
    </div>

    <div class="section">
        <h2>All Submitted Evaluations</h2>
        <table>
            <tr>
                <th>Procurement</th>
                <th>Submission Code</th>
                <th>Applicant</th>
                <th>Evaluation</th>
                <th>Evaluator</th>
                <th>Result</th>
                <th>Submitted At</th>
            </tr>
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
                    <td>{{ $submission->submitted_at?->format('Y-m-d H:i') ?? 'N/A' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">No evaluations submitted.</td>
                </tr>
            @endforelse
        </table>
    </div>

    <div class="footer">
        {{ config('app.name') }} - Consolidated Evaluation Report - Generated: {{ now()->format('Y-m-d H:i') }}
    </div>
</body>
</html>
