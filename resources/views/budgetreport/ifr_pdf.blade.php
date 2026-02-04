<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>IFR Report</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #111; }
        h1, h2 { margin: 0 0 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; }
        th { background: #f2f2f2; text-align: left; }
        .header { background: #0f172a; color: #fff; padding: 12px 16px; border-bottom: 3px solid #22c55e; }
        .header .title { font-size: 16px; font-weight: bold; }
        .section { margin: 14px 0; }
        .right { text-align: right; }
        .project-row { background: #f8fafc; font-weight: bold; }
        .activity-row { background: #fdfdfd; font-weight: bold; }
        .sub-row td:first-child { padding-left: 18px; }
        .activity-row td:first-child { padding-left: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">{{ $program->name ?? 'IFR Report' }}</div>
        <div>{{ $filters['label'] ?? '' }}</div>
    </div>

    @php
        $currency = $program->currency
            ?? $program->approvedFundings?->first()?->currency
            ?? $program->fundings?->first()?->currency
            ?? '';
        $funders = $program->approvedFundings?->pluck('funder')->filter()->unique('id')
            ?? collect();
        if ($funders->isEmpty()) {
            $funders = $program->fundings?->pluck('funder')->filter()->unique('id') ?? collect();
        }
    @endphp

    <div class="section">
        <strong>Funding Partners:</strong>
        {{ $funders->pluck('name')->implode(', ') ?: 'N/A' }}
        <br>
        <strong>Total Committed:</strong> {{ $currency }} {{ number_format($totals['committed'] ?? 0, 2) }}
        &nbsp;&nbsp;
        <strong>Total Disbursed:</strong> {{ $currency }} {{ number_format($totals['disbursed'] ?? 0, 2) }}
    </div>

    <div class="section">
        <h3 style="margin: 0 0 8px 0; color: #0f172a;">Section 1: Interim Financial Balance Sheet</h3>
        <table>
            <thead>
                <tr>
                    <th rowspan="2">Project / Activity / Sub-Activity</th>
                    <th rowspan="2">PR Reference No</th>
                    <th rowspan="2" class="right">Committed</th>
                    <th rowspan="2" class="right">Actual Disbursement</th>
                    <th rowspan="2" class="right">Variance</th>
                    <th rowspan="2" class="right">Utilization %</th>
                    @foreach ($filters['year_range'] as $year)
                        <th colspan="3" class="right">{{ $year }}</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($filters['year_range'] as $year)
                        <th class="right">Committed</th>
                        <th class="right">Disbursed</th>
                        <th class="right">Variance</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $projectRow)
                    <tr class="project-row">
                        <td colspan="2" style="font-size: 13px; font-weight: bold;">{{ $projectRow['project']->name }}</td>
                        <td class="right">{{ number_format($projectRow['committed'], 2) }}</td>
                        <td class="right">{{ number_format($projectRow['disbursed'], 2) }}</td>
                        <td class="right">{{ number_format($projectRow['variance'], 2) }}</td>
                        <td class="right">{{ number_format($projectRow['utilization'], 2) }}%</td>
                        @foreach ($filters['year_range'] as $year)
                            <td class="right">{{ number_format($projectRow['yearly']['committed'][$year] ?? 0, 2) }}</td>
                            <td class="right">{{ number_format($projectRow['yearly']['disbursed'][$year] ?? 0, 2) }}</td>
                            <td class="right">{{ number_format($projectRow['yearly']['variance'][$year] ?? 0, 2) }}</td>
                        @endforeach
                    </tr>
                    @foreach ($projectRow['activities'] as $activityRow)
                        <tr class="activity-row">
                            <td colspan="2" style="font-style: italic; font-weight: bold;">{{ $activityRow['activity']->name }}</td>
                            <td class="right">{{ number_format($activityRow['committed'], 2) }}</td>
                            <td class="right">{{ number_format($activityRow['disbursed'], 2) }}</td>
                            <td class="right">{{ number_format($activityRow['variance'], 2) }}</td>
                            <td class="right">{{ number_format($activityRow['utilization'], 2) }}%</td>
                            @foreach ($filters['year_range'] as $year)
                                <td class="right">{{ number_format($activityRow['yearly']['committed'][$year] ?? 0, 2) }}</td>
                                <td class="right">{{ number_format($activityRow['yearly']['disbursed'][$year] ?? 0, 2) }}</td>
                                <td class="right">{{ number_format($activityRow['yearly']['variance'][$year] ?? 0, 2) }}</td>
                            @endforeach
                        </tr>
                        @foreach ($activityRow['subActivities'] as $subRow)
                            <tr class="sub-row">
                                <td>{{ $subRow['subActivity']->name }}</td>
                                <td>{{ $subRow['references'] }}</td>
                                <td class="right">{{ number_format($subRow['committed'], 2) }}</td>
                                <td class="right">{{ number_format($subRow['disbursed'], 2) }}</td>
                                <td class="right">{{ number_format($subRow['variance'], 2) }}</td>
                                <td class="right">{{ number_format($subRow['utilization'], 2) }}%</td>
                                @foreach ($filters['year_range'] as $year)
                                    <td class="right">{{ number_format($subRow['yearly']['committed'][$year] ?? 0, 2) }}</td>
                                    <td class="right">{{ number_format($subRow['yearly']['disbursed'][$year] ?? 0, 2) }}</td>
                                    <td class="right">{{ number_format($subRow['yearly']['variance'][$year] ?? 0, 2) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3 style="margin: 0 0 8px 0; color: #0f172a;">Section 2: Graphs & Trends</h3>

        @if (!empty($chartImages['line']) || !empty($chartImages['bar']) || !empty($chartImages['bubble']))
            <table>
                <tr>
                    <td style="width: 33%; text-align: center;">
                        <div><strong>Line Chart</strong></div>
                        @if (!empty($chartImages['line']))
                            <img src="{{ $chartImages['line'] }}" style="width: 100%; max-width: 320px;" alt="Line Chart">
                        @else
                            <div>N/A</div>
                        @endif
                    </td>
                    <td style="width: 33%; text-align: center;">
                        <div><strong>Bar Chart</strong></div>
                        @if (!empty($chartImages['bar']))
                            <img src="{{ $chartImages['bar'] }}" style="width: 100%; max-width: 320px;" alt="Bar Chart">
                        @else
                            <div>N/A</div>
                        @endif
                    </td>
                    <td style="width: 34%; text-align: center;">
                        <div><strong>Bubble Chart</strong></div>
                        @if (!empty($chartImages['bubble']))
                            <img src="{{ $chartImages['bubble'] }}" style="width: 100%; max-width: 320px;" alt="Bubble Chart">
                        @else
                            <div>N/A</div>
                        @endif
                    </td>
                </tr>
            </table>
        @else
            <table>
                <thead>
                    <tr>
                        <th style="width: 35%;">Line Chart (Commitments vs Disbursements)</th>
                        <th style="width: 35%;">Bar Chart (Committed vs Disbursed)</th>
                        <th>Bubble Chart (Sub-Activity Distribution)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            @if (!empty($chartData['line']['labels']))
                                @foreach ($chartData['line']['labels'] as $index => $label)
                                    <div>
                                        {{ $label }}:
                                        Commit {{ number_format($chartData['line']['commitments'][$index] ?? 0, 2) }},
                                        Disb {{ number_format($chartData['line']['disbursements'][$index] ?? 0, 2) }}
                                    </div>
                                @endforeach
                            @else
                                N/A
                            @endif
                        </td>
                        <td>
                            @if (!empty($chartData['bar']['labels']))
                                @foreach ($chartData['bar']['labels'] as $index => $label)
                                    <div>
                                        {{ $label }}:
                                        Commit {{ number_format($chartData['bar']['commitments'][$index] ?? 0, 2) }},
                                        Disb {{ number_format($chartData['bar']['disbursements'][$index] ?? 0, 2) }}
                                    </div>
                                @endforeach
                            @else
                                N/A
                            @endif
                        </td>
                        <td>
                            @if (!empty($chartData['bubble']))
                                @foreach ($chartData['bubble'] as $bubble)
                                    <div>{{ $bubble['label'] ?? 'Sub-Activity' }}: Commit {{ number_format($bubble['x'] ?? 0, 2) }}, Disb {{ number_format($bubble['y'] ?? 0, 2) }}</div>
                                @endforeach
                            @else
                                N/A
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        @endif
    </div>

    <div class="section">
        <h3 style="margin: 0 0 8px 0; color: #0f172a;">Section 3: IFR Summary</h3>
        <ul>
            @foreach ($summary as $line)
                <li>{{ $line }}</li>
            @endforeach
        </ul>
    </div>
</body>
</html>
