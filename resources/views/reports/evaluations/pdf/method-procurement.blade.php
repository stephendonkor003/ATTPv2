<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $methodDefinition['label'] }} Evaluation Report</title>
    <style>
        @page { margin: 25mm 12mm 18mm; }
        body { color: #233444; font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; line-height: 1.4; }
        h1, h2, h3, p { margin: 0; }
        .header { background: #123a48; color: #fff; left: -12mm; padding: 12px 12mm; position: fixed; right: -12mm; top: -25mm; }
        .brand { display: table; width: 100%; }
        .brand-copy, .brand-logo { display: table-cell; vertical-align: middle; }
        .brand-logo { text-align: right; width: 90px; }
        .brand-logo img { background: #fff; border-radius: 4px; max-height: 34px; max-width: 80px; padding: 2px; }
        .eyebrow { color: #8fe1d3; font-size: 7px; font-weight: bold; letter-spacing: .08em; text-transform: uppercase; }
        .header h1 { color: #fff; font-size: 16px; margin-top: 2px; }
        .header p { color: #d9e8ec; font-size: 8px; margin-top: 3px; }
        .footer { border-top: 1px solid #cad5dc; bottom: -13mm; color: #71808d; font-size: 7px; left: 0; padding-top: 5px; position: fixed; right: 0; text-align: center; }
        .section { margin-top: 12px; page-break-inside: avoid; }
        .section-title { border-left: 3px solid #0f766e; color: #152b3a; font-size: 12px; margin-bottom: 6px; padding-left: 6px; }
        table { border-collapse: collapse; page-break-inside: auto; width: 100%; }
        tr { page-break-inside: avoid; }
        th { background: #eaf3f3; color: #294657; font-size: 7px; letter-spacing: .035em; text-align: left; text-transform: uppercase; }
        th, td { border: 1px solid #d9e2e7; padding: 5px 6px; vertical-align: top; }
        .summary { table-layout: fixed; }
        .summary td { background: #f8fafb; text-align: center; }
        .summary span { color: #71808d; display: block; font-size: 7px; text-transform: uppercase; }
        .summary strong { color: #123a48; display: block; font-size: 14px; margin-top: 2px; }
        .badge { border: 1px solid #badfd7; border-radius: 8px; color: #0f6c63; display: inline-block; font-size: 7px; font-weight: bold; padding: 2px 5px; }
        .rank { background: #edf3f4; border-radius: 10px; display: inline-block; font-weight: bold; padding: 2px 5px; }
        .rank-1 { background: #fff0b8; color: #8c6106; }
        .rank-2 { background: #eceff2; color: #596772; }
        .rank-3 { background: #f5ddcc; color: #97592f; }
        .note { background: #f5f8fa; border: 1px solid #dbe4e9; color: #60717f; font-size: 8px; margin-bottom: 7px; padding: 6px; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <header class="header">
        <div class="brand">
            <div class="brand-copy">
                <span class="eyebrow">{{ $methodDefinition['label'] }} · {{ $methodDefinition['mode'] }}</span>
                <h1>Procurement Evaluation Report</h1>
                <p>{{ $procurement->title ?: 'Untitled procurement' }} · {{ $procurement->reference_no ?: 'No reference number' }}</p>
            </div>
            @if ($logoDataUri)<div class="brand-logo"><img src="{{ $logoDataUri }}" alt="{{ $platformName }}"></div>@endif
        </div>
    </header>

    <footer class="footer">
        {{ $platformName }} · {{ $methodDefinition['label'] }} Evaluation Report · Generated {{ now()->format('d M Y, H:i') }}
    </footer>

    <section class="section">
        <table class="summary">
            <tr>
                <td><span>Applicants evaluated</span><strong>{{ number_format($summary['applicants']) }}</strong></td>
                <td><span>Panel reports</span><strong>{{ number_format($summary['total']) }}</strong></td>
                <td><span>Evaluators</span><strong>{{ number_format($summary['evaluators']) }}</strong></td>
                <td><span>Templates</span><strong>{{ number_format($summary['templates']) }}</strong></td>
                <td><span>{{ $resultSummary['label'] }}</span><strong>{{ $resultSummary['value'] !== null ? number_format($resultSummary['value'], $method === 'services' ? 1 : 0).$resultSummary['suffix'] : 'N/A' }}</strong></td>
            </tr>
        </table>
    </section>

    @if ($method === 'services')
        @forelse ($serviceRankingGroups as $rankingGroup)
            <section class="section">
                <h2 class="section-title">{{ $rankingGroup['phase'] }} · {{ $rankingGroup['evaluation']->name }} Ranking</h2>
                <div class="note">This evaluation is ranked independently. Scores are normalised against its configured maximum; incomplete panels have no rank or medal.</div>
                <table>
                    <thead><tr><th>Rank</th><th>Submission</th><th>Applicant</th><th>Panel average</th><th>Highest</th><th>Lowest</th><th>Panel status</th><th>Tasks</th></tr></thead>
                    <tbody>
                        @forelse ($rankingGroup['rankings'] as $row)
                            <tr>
                                <td><span class="rank {{ $row['rank'] && $row['rank'] <= 3 ? 'rank-'.$row['rank'] : '' }}">{{ $row['rank'] ? '#'.$row['rank'] : 'N/A' }}</span></td>
                                <td>{{ $row['submission']?->procurement_submission_code ?: 'N/A' }}</td>
                                <td>{{ $row['submission']?->display_name ?: 'Applicant not available' }}</td>
                                <td>{{ $row['metric'] !== null ? number_format($row['metric'], 1).'%' : 'N/A' }}</td>
                                <td>{{ $row['highest'] !== null ? number_format($row['highest'], 1).'%' : 'N/A' }}</td>
                                <td>{{ $row['lowest'] !== null ? number_format($row['lowest'], 1).'%' : 'N/A' }}</td>
                                <td>{{ $row['outcome'] }}</td>
                                <td>{{ $row['expected_tasks'] !== null ? $row['completed_tasks'].'/'.$row['expected_tasks'] : $row['completed_tasks'].' recorded' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8">No scored submissions are available for this evaluation.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        @empty
            <section class="section"><h2 class="section-title">Applicant Rankings</h2><div class="note">No scored Services evaluations are available.</div></section>
        @endforelse
    @else
        <section class="section">
            <h2 class="section-title">Applicant Compliance Evidence</h2>
            <div class="note">Goods decisions are categorical. Yes and No results are submitted evidence, not a numeric rank or final award decision.</div>
            <table>
                <thead><tr><th>Submission</th><th>Applicant</th><th>Submitted evidence</th><th>Yes</th><th>No</th><th>Panel status</th><th>Tasks</th><th>Reports</th></tr></thead>
                <tbody>
                    @forelse ($applicantSummaries as $row)
                        <tr>
                            <td>{{ $row['submission']?->procurement_submission_code ?: 'N/A' }}</td>
                            <td>{{ $row['submission']?->display_name ?: 'Applicant not available' }}</td>
                            <td><span class="badge">{{ $row['outcome'] }}</span></td>
                            <td>{{ $row['counts']['yes'] ?? 0 }}</td>
                            <td>{{ $row['counts']['no'] ?? 0 }}</td>
                            <td>{{ $row['panel_status'] }}</td>
                            <td>{{ $row['expected_tasks'] !== null ? $row['completed_tasks'].'/'.$row['expected_tasks'] : $row['completed_tasks'].' recorded' }}</td>
                            <td>{{ $row['evaluations'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8">No completed applicant results are available.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    @endif

    @foreach ($evaluationStats as $stat)
        <section class="section">
            <h2 class="section-title">{{ $stat['evaluation']->name }} · Criteria Analysis</h2>
            <table>
                <thead>
                    @if ($method === 'services')<tr><th>Criterion</th><th>Maximum</th><th>Average score</th><th>Samples</th></tr>
                    @else<tr><th>Criterion</th><th>Yes</th><th>No</th><th>Pass rate</th><th>Samples</th></tr>@endif
                </thead>
                <tbody>
                    @forelse ($stat['criteria_stats'] as $criterion)
                        <tr>
                            <td>{{ $criterion['name'] }}</td>
                            @if ($method === 'services')
                                <td>{{ number_format((float) $criterion['max'], 2) }}</td><td>{{ number_format((float) $criterion['avg'], 2) }}</td><td>{{ $criterion['total'] }}</td>
                            @else
                                <td>{{ $criterion['yes'] }}</td><td>{{ $criterion['no'] }}</td><td>{{ number_format($criterion['rate'], 1) }}%</td><td>{{ $criterion['total'] }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $method === 'services' ? 4 : 5 }}">No criterion data is available.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    @endforeach

    <section class="section page-break">
        <h2 class="section-title">Submitted Evaluation Audit</h2>
        <table>
            <thead><tr><th>Submission</th><th>Applicant</th><th>Evaluation</th><th>Phase</th><th>Evaluator</th><th>Result</th><th>Submitted</th></tr></thead>
            <tbody>
                @forelse ($submissionRows as $row)
                    <tr>
                        <td>{{ $row['code'] }}</td><td>{{ $row['applicant'] }}</td><td>{{ $row['evaluation'] }}</td><td>{{ $row['phase'] }}</td><td>{{ $row['evaluator'] }}</td><td>{{ $row['result'] }}</td><td>{{ $row['submitted_at']?->format('Y-m-d H:i') ?: 'N/A' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7">No submitted evaluations are available.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</body>
</html>
