<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Procurement Evaluation Report</title>
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
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            background: #1d4ed8;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">{{ config('app.name') }} - Procurement Evaluation Report</div>
        <div class="meta">
            Procurement: {{ $procurement->title ?? 'N/A' }} |
            Reference: {{ $procurement->reference_no ?? 'N/A' }} |
            Generated: {{ now()->format('Y-m-d H:i') }}
        </div>
    </div>

    <div class="section">
        <h2>Summary</h2>
        <table>
            <tr>
                <th>Total Evaluations</th>
                <th>Evaluators</th>
                <th>Average Overall</th>
            </tr>
            <tr>
                <td>{{ $summary['total'] }}</td>
                <td>{{ $summary['evaluators'] }}</td>
                <td>{{ number_format($summary['avg_overall'], 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>Evaluator Breakdown</h2>
        <table>
            <tr>
                <th>Evaluator</th>
                <th>Total Evaluations</th>
                <th>Average Overall</th>
            </tr>
            @forelse ($evaluatorBreakdown as $name => $data)
                <tr>
                    <td>{{ $name }}</td>
                    <td>{{ $data['total'] }}</td>
                    <td>{{ number_format($data['avg_overall'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">No evaluations submitted.</td>
                </tr>
            @endforelse
        </table>
    </div>

    <div class="section">
        <h2>Applicant Ranking</h2>
        <table>
            <tr>
                <th>Rank</th>
                <th>Submission</th>
                <th>Applicant</th>
                <th>Average</th>
                <th>Highest</th>
                <th>Lowest</th>
                <th>Spread</th>
                <th>Evaluators</th>
            </tr>
            @forelse ($rankings as $row)
                <tr>
                    <td>#{{ $row['rank'] }}</td>
                    <td>{{ $row['submission']?->procurement_submission_code ?? 'N/A' }}</td>
                    <td>{{ $row['submission']?->submitter?->name ?? 'N/A' }}</td>
                    <td>{{ number_format($row['average'], 2) }}</td>
                    <td>{{ number_format($row['highest'], 2) }}</td>
                    <td>{{ number_format($row['lowest'], 2) }}</td>
                    <td>{{ number_format($row['spread'], 2) }}</td>
                    <td>{{ $row['evaluators'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">No ranked applicants.</td>
                </tr>
            @endforelse
        </table>
    </div>

    @foreach ($evaluationStats as $stat)
        <div class="section">
            <h2>{{ $stat['evaluation']->name }} <span class="badge">{{ strtoupper($stat['type']) }}</span></h2>
            <table>
                <tr>
                    <th>Total Evaluations</th>
                    <th>Average Overall</th>
                </tr>
                <tr>
                    <td>{{ $stat['total'] }}</td>
                    <td>{{ number_format($stat['avg_overall'], 2) }}</td>
                </tr>
            </table>

            <table>
                <tr>
                    <th>Criteria</th>
                    @if ($stat['type'] === 'goods')
                        <th>Yes</th>
                        <th>No</th>
                        <th>Pass Rate</th>
                    @else
                        <th>Max</th>
                        <th>Average Score</th>
                        <th>Samples</th>
                    @endif
                </tr>
                @forelse ($stat['criteria_stats'] as $criteria)
                    <tr>
                        <td>{{ $criteria['name'] }}</td>
                        @if ($stat['type'] === 'goods')
                            <td>{{ $criteria['yes'] }}</td>
                            <td>{{ $criteria['no'] }}</td>
                            <td>{{ $criteria['rate'] }}%</td>
                        @else
                            <td>{{ $criteria['max'] }}</td>
                            <td>{{ number_format($criteria['avg'], 2) }}</td>
                            <td>{{ $criteria['total'] }}</td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">No criteria data available.</td>
                    </tr>
                @endforelse
            </table>
        </div>
    @endforeach

    <div class="section">
        <h2>Submitted Evaluations</h2>
        <table>
            <tr>
                <th>Submission Code</th>
                <th>Applicant</th>
                <th>Evaluation</th>
                <th>Evaluator</th>
                <th>Overall Score</th>
                <th>Submitted At</th>
            </tr>
            @forelse ($submissions as $submission)
                <tr>
                    <td>{{ $submission->applicant?->procurement_submission_code ?? 'N/A' }}</td>
                    <td>{{ $submission->applicant?->submitter?->name ?? 'N/A' }}</td>
                    <td>{{ $submission->evaluation?->name ?? 'N/A' }}</td>
                    <td>{{ $submission->evaluator?->name ?? 'N/A' }}</td>
                    <td>{{ number_format($submission->overall_score ?? 0, 2) }}</td>
                    <td>{{ $submission->submitted_at?->format('Y-m-d H:i') ?? 'N/A' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No evaluations submitted.</td>
                </tr>
            @endforelse
        </table>
    </div>

    <div class="footer">
        {{ config('app.name') }} - Procurement Evaluation Report - Generated: {{ now()->format('Y-m-d H:i') }}
    </div>
</body>
</html>
