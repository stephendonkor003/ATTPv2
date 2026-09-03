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
        .note { background: #f5f8fa; border: 1px solid #dbe4e9; color: #60717f; font-size: 8px; margin-bottom: 7px; padding: 6px; }
        .bar-rail { background: #edf4f6; border-radius: 999px; height: 10px; margin: 2px 0; overflow: hidden; width: 100%; }
        .bar-rail--value { background: #0f766e; border-radius: inherit; height: 10px; }
        .subsection-title { font-size: 10px; margin: 10px 0 6px; }
        .section-grid-2 { display: table; width: 100%; }
        .section-grid-2 td { border: 0; padding: 0 0 6px 0; }
        .app-card { border: 1px solid #d7e2ea; margin-top: 8px; }
        .app-card h3 { background: #f2f8fb; border-bottom: 1px solid #d7e2ea; font-size: 8px; padding: 6px; }
        .app-card table td { font-size: 8px; }
        .metric { color: #0f766e; font-size: 8px; font-weight: bold; }
        .page-break { page-break-before: always; }
        .small { font-size: 7px; color: #637482; }
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

    @php
        $highestScore = $summary['highest_score'] !== null ? number_format((float) $summary['highest_score'], 2).'%' : 'N/A';
        $resultValue = $resultSummary['value'] !== null ? number_format((float) $resultSummary['value'], $method === 'services' ? 1 : 0).$resultSummary['suffix'] : 'N/A';
    @endphp

    <section class="section">
        <h2 class="section-title">Section 1: Executive summary</h2>
        <div class="note">
            Four management cards: applicants, reports, evaluators, templates and highest score for comparison.
        </div>
        <table class="summary">
            <tr>
                <td><span>Total applicants</span><strong>{{ number_format((int) ($summary['applicants'] ?? 0)) }}</strong></td>
                <td><span>Evaluator submissions</span><strong>{{ number_format((int) ($summary['total'] ?? 0)) }}</strong></td>
                <td><span>Evaluators</span><strong>{{ number_format((int) ($summary['evaluators'] ?? 0)) }}</strong></td>
                <td><span>Templates used</span><strong>{{ number_format((int) ($summary['templates'] ?? 0)) }}</strong></td>
                <td><span>Highest score</span><strong>{{ $highestScore }}</strong></td>
            </tr>
            <tr>
                <td colspan="5"><span>{{ $resultSummary['label'] }}</span><strong>{{ $resultValue }}</strong></td>
            </tr>
        </table>
    </section>

    <section class="section">
        <h2 class="section-title">Section 2: Evaluator vs applicant score charts</h2>
        <div class="note">
            For each applicant, evaluator names are shown on the X-axis and percentage scores on the Y-axis.
        </div>
        @if ($evaluatorApplicantCharts->isNotEmpty())
            @foreach ($evaluatorApplicantCharts as $chartGroup)
                <h3 class="subsection-title">{{ $chartGroup['evaluation']?->name ?? 'Evaluation' }} - {{ $chartGroup['phase'] }}</h3>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 18%;">Applicant code</th>
                            <th style="width: 21%;">Applicant</th>
                            <th style="width: 38%;">Evaluator</th>
                            <th style="width: 12%;">Score %</th>
                            <th style="width: 11%;">Evaluator email</th>
                        </tr>
                    </thead>
                </table>
                @foreach ($chartGroup['applicant_charts'] as $applicantChart)
                    <div class="app-card">
                        <h3>{{ $applicantChart['submission_code'] }} - {{ $applicantChart['submission_name'] }} (avg {{ number_format((float) $applicantChart['average_percentage'], 1) }}%)</h3>
                        <table>
                            <tbody>
                                @if ($applicantChart['scores']->isEmpty())
                                    <tr>
                                        <td colspan="5" class="small">No evaluator percentage available for this applicant.</td>
                                    </tr>
                                @else
                                @forelse ($applicantChart['scores'] as $scoreRow)
                                    @php
                                        $width = min(100, max(0, (float) $scoreRow['percentage']));
                                    @endphp
                                    <tr>
                                        <td colspan="2">{{ $scoreRow['evaluator'] }}</td>
                                        <td colspan="2">
                                            <div class="bar-rail">
                                                <div class="bar-rail--value" style="width: {{ $width }}%;"></div>
                                            </div>
                                        </td>
                                        <td>{{ number_format((float) $scoreRow['percentage'], 1) }}%</td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="small">{{ $scoreRow['evaluator_email'] ?: 'Email not available' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="small">No evaluator score rows available.</td></tr>
                                @endforelse
                                @endif
                            </tbody>
                        </table>
                    </div>
                @endforeach
            @endforeach
        @else
            <div class="note">No evaluator chart data is available for this method.</div>
        @endif
    </section>

    <section class="section page-break">
        <h2 class="section-title">Section 3: Detailed evaluator submissions</h2>
        @if ($submissionRows->isNotEmpty())
            <table>
                <thead>
                    <tr>
                        <th>Submission</th>
                        <th>Applicant</th>
                        <th>Evaluation</th>
                        <th>Evaluator</th>
                        <th>Phase</th>
                        <th>Result</th>
                        <th>Submitted</th>
                        <th>Section</th>
                        <th>Criterion</th>
                        <th>Score/Decision</th>
                        <th>Comment</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($submissionRows as $submissionRow)
                        @if ($submissionRow['criterion_rows']->isEmpty())
                            <tr>
                                <td>{{ $submissionRow['code'] }}</td>
                                <td>{{ $submissionRow['applicant'] }}</td>
                                <td>{{ $submissionRow['evaluation'] }}</td>
                                <td>{{ $submissionRow['evaluator'] }}</td>
                                <td>{{ $submissionRow['phase'] }}</td>
                                <td>{{ $submissionRow['result'] }}</td>
                                <td>{{ $submissionRow['submitted_at']?->format('Y-m-d H:i') ?: 'N/A' }}</td>
                                <td colspan="4" class="small">No criteria rows were submitted.</td>
                            </tr>
                        @else
                            @foreach ($submissionRow['criterion_rows'] as $index => $criterionRow)
                                <tr>
                                    <td>{{ $submissionRow['code'] }}</td>
                                    <td>{{ $submissionRow['applicant'] }}</td>
                                    <td>{{ $submissionRow['evaluation'] }}</td>
                                    <td>{{ $submissionRow['evaluator'] }}</td>
                                    <td>{{ $submissionRow['phase'] }}</td>
                                    <td>{{ $submissionRow['result'] }}</td>
                                    <td>{{ $submissionRow['submitted_at']?->format('Y-m-d H:i') ?: 'N/A' }}</td>
                                    <td>{{ $criterionRow['section'] }}</td>
                                    <td>{{ $criterionRow['criterion'] }}</td>
                                    <td>{{ $criterionRow['score_display'] }}</td>
                                    <td>{{ $criterionRow['comment'] }}</td>
                                </tr>
                            @endforeach
                        @endif
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="note">No detailed evaluator submissions are available.</div>
        @endif
    </section>

    <section class="section">
        <h2 class="section-title">Section 4: Applicant intelligence and ranking</h2>
        @if ($intelligenceSummary->isNotEmpty())
            @foreach ($intelligenceSummary as $group)
                <div class="note">
                    {{ $group['evaluation']?->name ?? 'Combined view' }} - {{ $group['phase'] }}. Rank and medal details are included below.
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Submission</th>
                            <th>Applicant</th>
                            <th>Metric</th>
                            <th>Metric label</th>
                            <th>Highest</th>
                            <th>Lowest</th>
                            <th>Spread</th>
                            <th>Panel status</th>
                            <th>Evaluators</th>
                            <th>Medal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group['rankings'] as $row)
                            <tr>
                                <td>
                                    @if (($row['medal'] ?? null) === 'gold') <strong>&#x1F947; GOLD</strong>
                                    @elseif (($row['medal'] ?? null) === 'silver') <strong>&#x1F948; SILVER</strong>
                                    @elseif (($row['medal'] ?? null) === 'bronze') <strong>&#x1F949; BRONZE</strong>
                                    @else <span>{{ $row['rank'] ?: 'N/A' }}</span>
                                    @endif
                                </td>
                                <td>{{ $row['submission']?->procurement_submission_code ?: 'N/A' }}</td>
                                <td>{{ $row['submission']?->display_name ?: 'Applicant not available' }}</td>
                                <td>{{ $row['metric'] !== null ? number_format((float) $row['metric'], 2).'%' : 'N/A' }}</td>
                                <td>{{ $row['metric_label'] ?? 'N/A' }}</td>
                                <td>{{ $row['highest'] !== null ? number_format((float) $row['highest'], 2) : 'N/A' }}</td>
                                <td>{{ $row['lowest'] !== null ? number_format((float) $row['lowest'], 2) : 'N/A' }}</td>
                                <td>{{ $row['spread'] !== null ? number_format((float) $row['spread'], 2) : 'N/A' }}</td>
                                <td>{{ $row['outcome'] ?? $row['panel_status'] ?? 'N/A' }}</td>
                                <td>{{ $row['evaluators'] ?? 0 }}</td>
                                <td>{{ strtoupper((string) ($row['medal'] ?? '')) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach
        @else
            <div class="note">No ranking data is available for applicant intelligence.</div>
        @endif
    </section>
</body>
</html>



