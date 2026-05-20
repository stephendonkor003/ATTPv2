@php
    $currency = 'USD';
    $maxOpportunityBudget = max(1, (float) $opportunityRecords->max('estimated_budget'));
    $statusTotal = max(1, (int) $statusCounts->sum());
    $planStatusTotal = max(1, (int) $planStatusCounts->sum());
    $applicationStatusTotal = max(1, (int) $submissionStatusCounts->sum());
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Think Tank Procurement Report</title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #172033;
            font-size: 11px;
            margin: 0;
            background: #f8fafc;
        }

        .page {
            padding: 24px;
        }

        .header {
            background: #0f172a;
            color: #ffffff;
            border-radius: 10px;
            padding: 18px;
        }

        .header .kicker {
            color: #fde68a;
            text-transform: uppercase;
            font-size: 10px;
            font-weight: 700;
        }

        h1 {
            font-size: 22px;
            margin: 6px 0;
        }

        h2 {
            font-size: 14px;
            margin: 0 0 8px;
            color: #0f172a;
        }

        .muted {
            color: #64748b;
        }

        .grid-4 {
            display: table;
            width: 100%;
            border-spacing: 8px;
            margin: 14px -8px 12px;
        }

        .metric {
            display: table-cell;
            width: 25%;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
        }

        .metric span {
            display: block;
            color: #64748b;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .metric strong {
            display: block;
            color: #0f172a;
            font-size: 17px;
            margin-top: 6px;
        }

        .section {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px;
            margin-top: 12px;
        }

        .bar-row {
            margin-bottom: 8px;
        }

        .bar-meta {
            display: table;
            width: 100%;
            margin-bottom: 3px;
        }

        .bar-meta span,
        .bar-meta strong {
            display: table-cell;
        }

        .bar-meta strong {
            text-align: right;
        }

        .bar-track {
            height: 8px;
            background: #e2e8f0;
            border-radius: 999px;
            overflow: hidden;
        }

        .bar-fill {
            height: 8px;
            background: #2563eb;
            border-radius: 999px;
        }

        .bar-fill.green { background: #0f766e; }
        .bar-fill.amber { background: #f59e0b; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        th {
            background: #f1f5f9;
            color: #334155;
            text-align: left;
            padding: 7px;
            font-size: 9px;
            text-transform: uppercase;
        }

        td {
            border-bottom: 1px solid #e2e8f0;
            padding: 7px;
            vertical-align: top;
        }

        .status {
            display: inline-block;
            border-radius: 999px;
            padding: 3px 7px;
            background: #dbeafe;
            color: #1e40af;
            font-size: 9px;
            font-weight: 700;
            text-transform: capitalize;
        }

        .two-col {
            display: table;
            width: 100%;
            border-spacing: 10px;
            margin: 4px -10px 0;
        }

        .col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="kicker">Think Tank Procurement Report</div>
            <h1>{{ $member->name }}</h1>
            <div>{{ $member->consortium?->name ?? 'No consortium assigned' }} | {{ $member->country ?? 'Country not set' }} | Generated {{ now()->format('d M Y H:i') }}</div>
        </div>

        <div class="grid-4">
            <div class="metric">
                <span>Plans</span>
                <strong>{{ number_format($procurementStats['plans']) }}</strong>
                <div class="muted">{{ $currency }} {{ number_format($procurementStats['plan_budget'], 2) }} planned</div>
            </div>
            <div class="metric">
                <span>Opportunities</span>
                <strong>{{ number_format($procurementStats['opportunities']) }}</strong>
                <div class="muted">{{ $currency }} {{ number_format($procurementStats['opportunity_budget'], 2) }} pipeline</div>
            </div>
            <div class="metric">
                <span>Applications</span>
                <strong>{{ number_format($procurementStats['applications']) }}</strong>
                <div class="muted">{{ number_format($procurementStats['reviewed']) }} reviewed</div>
            </div>
            <div class="metric">
                <span>Selections</span>
                <strong>{{ number_format($procurementStats['selected']) }}</strong>
                <div class="muted">{{ number_format($procurementStats['awarded']) }} awarded</div>
            </div>
        </div>

        <div class="two-col">
            <div class="col">
                <div class="section">
                    <h2>Opportunity Status</h2>
                    @forelse($statusCounts as $status => $count)
                        <div class="bar-row">
                            <div class="bar-meta">
                                <span>{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                                <strong>{{ number_format($count) }}</strong>
                            </div>
                            <div class="bar-track">
                                <div class="bar-fill" style="width: {{ min(100, round(($count / $statusTotal) * 100, 1)) }}%;"></div>
                            </div>
                        </div>
                    @empty
                        <div class="muted">No opportunity status data.</div>
                    @endforelse
                </div>
            </div>
            <div class="col">
                <div class="section">
                    <h2>Plan Status</h2>
                    @forelse($planStatusCounts as $status => $count)
                        <div class="bar-row">
                            <div class="bar-meta">
                                <span>{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                                <strong>{{ number_format($count) }}</strong>
                            </div>
                            <div class="bar-track">
                                <div class="bar-fill green" style="width: {{ min(100, round(($count / $planStatusTotal) * 100, 1)) }}%;"></div>
                            </div>
                        </div>
                    @empty
                        <div class="muted">No plan status data.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="two-col">
            <div class="col">
                <div class="section">
                    <h2>Application Status</h2>
                    @forelse($submissionStatusCounts as $status => $count)
                        <div class="bar-row">
                            <div class="bar-meta">
                                <span>{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                                <strong>{{ number_format($count) }}</strong>
                            </div>
                            <div class="bar-track">
                                <div class="bar-fill amber" style="width: {{ min(100, round(($count / $applicationStatusTotal) * 100, 1)) }}%;"></div>
                            </div>
                        </div>
                    @empty
                        <div class="muted">No application status data.</div>
                    @endforelse
                </div>
            </div>
            <div class="col">
                <div class="section">
                    <h2>Pipeline Controls</h2>
                    <table>
                        <tbody>
                            <tr><td>Open opportunities</td><td>{{ number_format($procurementStats['open']) }}</td></tr>
                            <tr><td>Closing soon</td><td>{{ number_format($procurementStats['closing_soon']) }}</td></tr>
                            <tr><td>Draft opportunities</td><td>{{ number_format($procurementStats['draft']) }}</td></tr>
                            <tr><td>Closed opportunities</td><td>{{ number_format($procurementStats['closed']) }}</td></tr>
                            <tr><td>Average applications</td><td>{{ $procurementStats['average_applications'] }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>Opportunity Register</h2>
            <table>
                <thead>
                    <tr>
                        <th>Opportunity</th>
                        <th>Plan</th>
                        <th>Budget</th>
                        <th>Status</th>
                        <th>Applications</th>
                        <th>Closing</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($opportunityRecords->take(18) as $procurement)
                        <tr>
                            <td>
                                <strong>{{ $procurement->title }}</strong><br>
                                <span class="muted">{{ $procurement->reference_no ?? 'No reference' }}</span>
                            </td>
                            <td>{{ $procurement->thinkTankProcurementPlan?->title ?? 'Unlinked' }}</td>
                            <td>{{ $currency }} {{ number_format((float) $procurement->estimated_budget, 2) }}</td>
                            <td><span class="status">{{ str_replace('_', ' ', $procurement->status) }}</span></td>
                            <td>{{ number_format($procurement->submissions_count) }}</td>
                            <td>{{ $procurement->application_end_date?->format('d M Y') ?? 'N/A' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">No procurement opportunities found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="section">
            <h2>Plan Register</h2>
            <table>
                <thead>
                    <tr>
                        <th>Plan</th>
                        <th>Fiscal year</th>
                        <th>Budget</th>
                        <th>Publish date</th>
                        <th>Opportunities</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($plans->take(18) as $plan)
                        <tr>
                            <td>
                                <strong>{{ $plan->title }}</strong><br>
                                <span class="muted">{{ $plan->plan_code ?? 'No plan code' }}</span>
                            </td>
                            <td>{{ $plan->fiscal_year ?? 'N/A' }}</td>
                            <td>{{ $currency }} {{ number_format((float) $plan->estimated_budget, 2) }}</td>
                            <td>{{ $plan->planned_publish_date?->format('d M Y') ?? 'N/A' }}</td>
                            <td>{{ number_format($plan->procurements_count) }}</td>
                            <td><span class="status">{{ str_replace('_', ' ', $plan->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">No procurement plans found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
