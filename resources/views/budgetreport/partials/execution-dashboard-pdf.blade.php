@php
    $exec = $executionDashboard ?? [];
    $execSummary = $exec['executionSummary'] ?? [];
    $execTotals = $exec['executionBreakdownTotals'] ?? [];
    $execRows = collect($exec['executionBreakdownRows'] ?? []);
    $execComponents = collect($exec['componentBreakdownRows'] ?? []);
    $execCharts = $exec['executionChartImages'] ?? [];
    $execInsights = collect($exec['aiInsights'] ?? []);
    $execCurrency = $exec['currency'] ?? ($currency ?? 'USD');
    $execMoney = fn ($value) => $execCurrency.' '.number_format((float) $value, 2);
    $execPercent = fn ($value) => number_format((float) $value, 1).'%';
    $execChartCards = [
        ['key' => 'execution_mix', 'title' => 'Execution Mix', 'note' => 'Disbursed, unpaid commitments, and remaining global commitments'],
        ['key' => 'rate_movement', 'title' => 'Rate Movement', 'note' => 'Cumulative commitment and disbursement rates'],
        ['key' => 'cumulative_momentum', 'title' => 'Cumulative Momentum', 'note' => 'Running allocation, commitment, and payment movement'],
        ['key' => 'financial_profile', 'title' => 'Cumulative Financial Profile', 'note' => 'Running financial totals across implementation years'],
        ['key' => 'variance_control', 'title' => 'Variance Control', 'note' => 'Remaining allocation after commitments by year'],
        ['key' => 'quality_radar', 'title' => 'Execution Quality Radar', 'note' => 'Utilization, timeliness, consistency, coverage, and risk control'],
        ['key' => 'exposure_concentration', 'title' => 'Exposure Concentration', 'note' => 'Commitment scale and variance pressure over time'],
    ];
@endphp

<style>
    .exec-page { page-break-before: always; }
    .exec-header { background: #123f52; color: #fff; border-radius: 8px; padding: 16px 18px; margin-bottom: 10px; }
    .exec-header .eyebrow { color: #f9d77e; font-size: 8px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
    .exec-header h2 { font-size: 19px; margin: 5px 0 4px; }
    .exec-header p { color: #d9e8ed; font-size: 9px; margin: 0; }
    .exec-scope { margin-top: 8px; font-size: 8px; color: #e8f1f4; }
    .exec-kpis { width: 100%; border-collapse: separate; border-spacing: 5px; margin: 0 -5px 9px; }
    .exec-kpis td { width: 25%; border: 1px solid #dce6eb; border-top: 3px solid #176b87; border-radius: 5px; padding: 8px; vertical-align: top; }
    .exec-kpis .label { color: #64748b; display: block; font-size: 7px; font-weight: bold; text-transform: uppercase; }
    .exec-kpis .value { color: #142c3a; display: block; font-size: 11px; font-weight: bold; margin-top: 4px; }
    .exec-chart-grid { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 0 -6px; }
    .exec-chart-grid td { width: 50%; vertical-align: top; }
    .exec-chart { border: 1px solid #dce6eb; border-radius: 6px; padding: 8px; page-break-inside: avoid; }
    .exec-chart h3 { color: #142c3a; font-size: 11px; margin: 0 0 2px; }
    .exec-chart p { color: #64748b; font-size: 7px; margin: 0 0 5px; }
    .exec-chart img { display: block; width: 100%; height: 155px; object-fit: contain; }
    .exec-chart.full img { height: 230px; }
    .exec-section { border: 1px solid #dce6eb; border-radius: 6px; margin-top: 10px; page-break-inside: auto; }
    .exec-section-head { background: #edf4f6; border-bottom: 1px solid #dce6eb; padding: 8px 10px; }
    .exec-section-head h3 { color: #142c3a; font-size: 11px; margin: 0; }
    .exec-section-head p { color: #64748b; font-size: 7px; margin: 2px 0 0; }
    .exec-table { width: 100%; border-collapse: collapse; font-size: 7px; }
    .exec-table th { background: #123f52; color: #fff; padding: 6px 5px; text-align: left; }
    .exec-table td { border: 1px solid #dce6eb; padding: 5px; vertical-align: top; }
    .exec-table .num { text-align: right; white-space: nowrap; }
    .exec-table .center { text-align: center; white-space: nowrap; }
    .exec-table tfoot td, .exec-table tfoot th { background: #edf6f3; color: #142c3a; font-weight: bold; }
    .exec-component-note { color: #64748b; display: block; font-size: 6.5px; margin-top: 2px; }
    .exec-insights { padding: 7px 9px; }
    .exec-insight { border-left: 3px solid #176b87; background: #f7fafb; margin-bottom: 5px; padding: 6px 8px; page-break-inside: avoid; }
    .exec-insight.warning, .exec-insight.risk { border-color: #d65a31; background: #fff7f4; }
    .exec-insight.success { border-color: #1d8f6f; background: #f1faf6; }
    .exec-insight strong { color: #142c3a; display: block; font-size: 8px; }
    .exec-insight span { color: #526674; display: block; font-size: 7px; margin-top: 2px; }
</style>

<div class="exec-page">
    <div class="exec-header">
        <div class="eyebrow">Section 2 · Integrated financial execution analytics</div>
        <p>This complete execution analysis forms part of the Project Financial Position report.</p>
        <div class="exec-scope">
            Sector: {{ data_get($exec, 'executionFilters.sector', 'All Sectors') }} &nbsp; | &nbsp;
            Program: {{ data_get($exec, 'executionFilters.program', $program->name ?? 'Selected Program') }} &nbsp; | &nbsp;
            Project: {{ data_get($exec, 'executionFilters.project', 'All Projects') }}
        </div>
    </div>

    <table class="exec-kpis"><tr>
        <td><span class="label">Budget Envelope</span><span class="value">{{ $execMoney($execSummary['budget_envelope'] ?? 0) }}</span></td>
        <td><span class="label">Scheduled Allocation</span><span class="value">{{ $execMoney($execSummary['scheduled_allocation'] ?? 0) }}</span></td>
        <td><span class="label">Committed</span><span class="value">{{ $execMoney($execSummary['committed'] ?? 0) }}</span></td>
        <td><span class="label">Disbursed</span><span class="value">{{ $execMoney($execSummary['disbursed'] ?? 0) }}</span></td>
    </tr></table>
    <table class="exec-kpis"><tr>
        <td><span class="label">Remaining Allocation</span><span class="value">{{ $execMoney($execSummary['remaining_allocation'] ?? 0) }}</span></td>
        <td><span class="label">Unpaid Commitments</span><span class="value">{{ $execMoney($execSummary['unpaid_commitments'] ?? 0) }}</span></td>
        <td><span class="label">Commitment Rate</span><span class="value">{{ $execPercent($execTotals['execution_rate'] ?? 0) }}</span></td>
        <td><span class="label">Disbursement Rate</span><span class="value">{{ $execPercent($execTotals['disbursement_rate'] ?? 0) }}</span></td>
    </tr></table>

    <div class="exec-chart full">
        <h3>{{ $execChartCards[0]['title'] }}</h3><p>{{ $execChartCards[0]['note'] }}</p>
        @if (! empty($execCharts[$execChartCards[0]['key']]))<img src="{{ $execCharts[$execChartCards[0]['key']] }}" alt="Execution Mix">@endif
    </div>
</div>

@foreach (array_chunk(array_slice($execChartCards, 1), 4) as $chartPage)
    <div class="exec-page">
        <div class="exec-header"><div class="eyebrow">Integrated Financial Execution Analytics</div><h2>Execution Graphs</h2><p>Audited visual analysis generated from the same financial execution snapshot.</p></div>
        <table class="exec-chart-grid">
            @foreach (array_chunk($chartPage, 2) as $chartRow)
                <tr>
                    @foreach ($chartRow as $chartCard)
                        <td><div class="exec-chart"><h3>{{ $chartCard['title'] }}</h3><p>{{ $chartCard['note'] }}</p>@if (! empty($execCharts[$chartCard['key']]))<img src="{{ $execCharts[$chartCard['key']] }}" alt="{{ $chartCard['title'] }}">@endif</div></td>
                    @endforeach
                    @if (count($chartRow) === 1)<td></td>@endif
                </tr>
            @endforeach
        </table>
    </div>
@endforeach

<div class="exec-page">
    <div class="exec-header"><div class="eyebrow">Integrated Financial Execution Analytics</div><h2>Execution Tables and Insights</h2><p>Detailed annual and component-level financial execution.</p></div>
    <div class="exec-section">
        <div class="exec-section-head"><h3>Year-by-Year Execution</h3><p>Annual allocation, commitment, disbursement, remaining balance, and utilization.</p></div>
        <table class="exec-table"><thead><tr><th>Year</th><th class="num">Allocation</th><th class="num">Committed</th><th class="num">Disbursed</th><th class="num">Remaining</th><th class="center">Commitment %</th><th class="center">Disbursement %</th></tr></thead><tbody>
            @forelse ($execRows as $row)
                <tr><td><strong>{{ $row['year'] }}</strong></td><td class="num">{{ number_format($row['allocation'] ?? 0, 2) }}</td><td class="num">{{ number_format($row['commitment'] ?? 0, 2) }}</td><td class="num">{{ number_format($row['disbursement'] ?? 0, 2) }}</td><td class="num">{{ number_format($row['remaining'] ?? 0, 2) }}</td><td class="center">{{ $execPercent($row['execution_rate'] ?? 0) }}</td><td class="center">{{ $execPercent($row['disbursement_rate'] ?? 0) }}</td></tr>
            @empty<tr><td colspan="7" class="center">No yearly execution records are available.</td></tr>@endforelse
        </tbody><tfoot><tr><th>Total</th><td class="num">{{ number_format($execTotals['allocation'] ?? 0, 2) }}</td><td class="num">{{ number_format($execTotals['commitment'] ?? 0, 2) }}</td><td class="num">{{ number_format($execTotals['disbursement'] ?? 0, 2) }}</td><td class="num">{{ number_format($execTotals['remaining'] ?? 0, 2) }}</td><td class="center">{{ $execPercent($execTotals['execution_rate'] ?? 0) }}</td><td class="center">{{ $execPercent($execTotals['disbursement_rate'] ?? 0) }}</td></tr></tfoot></table>
    </div>

    <div class="exec-section">
        <div class="exec-section-head"><h3>Component Execution Breakdown</h3><p>Reconciled financial execution by programme component.</p></div>
        <table class="exec-table"><thead><tr><th>Component</th><th class="num">Allocation</th><th class="num">Committed</th><th class="num">Disbursed</th><th class="num">Remaining</th><th class="center">Commitment %</th><th class="center">Disbursement %</th></tr></thead><tbody>
            @forelse ($execComponents as $component)
                <tr><td><strong>{{ $component['label'] ?? 'Unassigned' }}</strong>@if (! empty($component['description']))<span class="exec-component-note">{{ $component['description'] }}</span>@endif</td><td class="num">{{ number_format($component['allocation'] ?? 0, 2) }}</td><td class="num">{{ number_format($component['commitment'] ?? 0, 2) }}</td><td class="num">{{ number_format($component['disbursement'] ?? 0, 2) }}</td><td class="num">{{ number_format($component['remaining'] ?? 0, 2) }}</td><td class="center">{{ $execPercent($component['execution_rate'] ?? 0) }}</td><td class="center">{{ $execPercent($component['disbursement_rate'] ?? 0) }}</td></tr>
            @empty<tr><td colspan="7" class="center">No component execution records are available.</td></tr>@endforelse
        </tbody></table>
    </div>

    <div class="exec-section">
        <div class="exec-section-head"><h3>Execution Insights</h3><p>Risk and progress signals calculated from the audited execution snapshot.</p></div>
        <div class="exec-insights">
            @forelse ($execInsights as $insight)
                <div class="exec-insight {{ $insight['type'] ?? '' }}"><strong>{{ $insight['title'] ?? 'Execution insight' }}</strong><span>{{ $insight['message'] ?? '' }}</span></div>
            @empty
                <div class="exec-insight success"><strong>No significant execution risks detected</strong><span>The selected programme does not currently show a material execution anomaly.</span></div>
            @endforelse
        </div>
    </div>
</div>
