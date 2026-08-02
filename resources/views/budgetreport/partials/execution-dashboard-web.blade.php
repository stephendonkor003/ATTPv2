@php
    $execution = $executionDashboard ?? [];
    $executionSummary = $execution['executionSummary'] ?? [];
    $executionTotals = $execution['executionBreakdownTotals'] ?? [];
    $executionRows = collect($execution['executionBreakdownRows'] ?? []);
    $executionComponents = collect($execution['componentBreakdownRows'] ?? []);
    $executionCharts = $execution['executionChartImages'] ?? [];
    $executionInsights = collect($execution['aiInsights'] ?? []);
    $executionCurrency = $execution['currency'] ?? ($currency ?? 'USD');
    $executionMoney = fn ($value) => $executionCurrency.' '.number_format((float) $value, 2);
    $executionPercent = fn ($value) => number_format((float) $value, 1).'%';
    $executionChartCards = [
        ['key' => 'execution_mix', 'title' => 'Execution Mix', 'note' => 'Disbursed, unpaid commitments, and remaining global commitments.'],
        ['key' => 'rate_movement', 'title' => 'Rate Movement', 'note' => 'Cumulative commitment and disbursement rates.'],
        ['key' => 'cumulative_momentum', 'title' => 'Cumulative Momentum', 'note' => 'Running allocation, commitment, and payment movement.'],
        ['key' => 'financial_profile', 'title' => 'Cumulative Financial Profile', 'note' => 'Running financial totals across implementation years.'],
        ['key' => 'variance_control', 'title' => 'Variance Control', 'note' => 'Remaining allocation after commitments by year.'],
        ['key' => 'quality_radar', 'title' => 'Execution Quality Radar', 'note' => 'Utilization, timeliness, consistency, coverage, and risk control.'],
        ['key' => 'exposure_concentration', 'title' => 'Exposure Concentration', 'note' => 'Commitment scale and variance pressure over time.'],
    ];
    $executionKpis = [
        ['label' => 'Budget Envelope', 'value' => $executionMoney($executionSummary['budget_envelope'] ?? 0), 'tone' => 'blue'],
        ['label' => 'Scheduled Allocation', 'value' => $executionMoney($executionSummary['scheduled_allocation'] ?? 0), 'tone' => 'violet'],
        ['label' => 'Committed', 'value' => $executionMoney($executionSummary['committed'] ?? 0), 'tone' => 'gold'],
        ['label' => 'Disbursed', 'value' => $executionMoney($executionSummary['disbursed'] ?? 0), 'tone' => 'green'],
        ['label' => 'Remaining Allocation', 'value' => $executionMoney($executionSummary['remaining_allocation'] ?? 0), 'tone' => 'blue'],
        ['label' => 'Unpaid Commitments', 'value' => $executionMoney($executionSummary['unpaid_commitments'] ?? 0), 'tone' => 'orange'],
        ['label' => 'Commitment Rate', 'value' => $executionPercent($executionTotals['execution_rate'] ?? 0), 'tone' => 'gold'],
        ['label' => 'Disbursement Rate', 'value' => $executionPercent($executionTotals['disbursement_rate'] ?? 0), 'tone' => 'green'],
    ];
@endphp

<style>
    .pfp-execution { margin-top: 28px; border-top: 4px solid #176b87; padding-top: 24px; }
    .pfp-execution-hero { background: linear-gradient(135deg, #123f52 0%, #176b87 58%, #1d8f6f 100%); color: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 16px 36px rgba(18, 63, 82, .18); }
    .pfp-execution-eyebrow { color: #f9d77e; font-size: .72rem; font-weight: 800; letter-spacing: .13em; text-transform: uppercase; }
    .pfp-execution-scope { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
    .pfp-execution-scope span { border: 1px solid rgba(255,255,255,.28); background: rgba(255,255,255,.1); border-radius: 999px; padding: 6px 10px; font-size: .75rem; font-weight: 700; }
    .pfp-execution-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin: 16px 0; }
    .pfp-execution-kpi { --tone: #176b87; background: #fff; border: 1px solid #dde6ec; border-top: 4px solid var(--tone); border-radius: 12px; padding: 14px; min-width: 0; }
    .pfp-execution-kpi.green { --tone: #1d8f6f; } .pfp-execution-kpi.gold { --tone: #d49a21; }
    .pfp-execution-kpi.orange { --tone: #d65a31; } .pfp-execution-kpi.violet { --tone: #6d5bd0; }
    .pfp-execution-kpi .label { color: #64748b; font-size: .69rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
    .pfp-execution-kpi .value { color: #142c3a; font-size: 1.05rem; font-weight: 850; margin-top: 5px; overflow-wrap: anywhere; }
    .pfp-execution-charts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    .pfp-execution-chart { background: #fff; border: 1px solid #dde6ec; border-radius: 14px; padding: 14px; break-inside: avoid; }
    .pfp-execution-chart:first-child { grid-column: 1 / -1; }
    .pfp-execution-chart h6 { color: #142c3a; font-weight: 800; margin-bottom: 2px; }
    .pfp-execution-chart p { color: #64748b; font-size: .76rem; margin-bottom: 10px; }
    .pfp-execution-chart img { display: block; width: 100%; height: auto; min-height: 230px; object-fit: contain; }
    .pfp-execution-panel { background: #fff; border: 1px solid #dde6ec; border-radius: 14px; margin-top: 16px; overflow: hidden; break-inside: avoid; }
    .pfp-execution-panel-head { background: #f7fafb; border-bottom: 1px solid #dde6ec; padding: 14px 16px; }
    .pfp-execution-panel-head h5 { color: #142c3a; font-size: 1rem; font-weight: 850; margin: 0; }
    .pfp-execution-panel-head p { color: #64748b; font-size: .76rem; margin: 3px 0 0; }
    .pfp-execution-table { margin: 0; font-size: .78rem; }
    .pfp-execution-table thead th { background: #123f52; color: #fff; border-color: rgba(255,255,255,.13); white-space: nowrap; }
    .pfp-execution-table td, .pfp-execution-table th { padding: 9px 10px; vertical-align: middle; }
    .pfp-execution-table tfoot th, .pfp-execution-table tfoot td { background: #edf6f3; font-weight: 850; }
    .pfp-execution-rate { display: inline-block; min-width: 58px; border-radius: 999px; padding: 4px 7px; background: #e8f5ef; color: #146c50; font-weight: 800; text-align: center; }
    .pfp-execution-rate.warn { background: #fff4d9; color: #8a5a00; } .pfp-execution-rate.bad { background: #fde8e4; color: #a23c27; }
    .pfp-execution-component small { color: #64748b; display: block; margin-top: 2px; }
    .pfp-execution-insights { display: grid; gap: 10px; padding: 14px; }
    .pfp-execution-insight { border-left: 4px solid #176b87; background: #f7fafb; border-radius: 8px; padding: 11px 12px; }
    .pfp-execution-insight.warning, .pfp-execution-insight.risk { border-left-color: #d65a31; background: #fff7f4; }
    .pfp-execution-insight.success { border-left-color: #1d8f6f; background: #f1faf6; }
    .pfp-execution-insight strong { color: #142c3a; display: block; font-size: .84rem; }
    .pfp-execution-insight span { color: #526674; display: block; font-size: .77rem; margin-top: 2px; }
    @media (max-width: 991.98px) { .pfp-execution-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .pfp-execution-charts { grid-template-columns: 1fr; } .pfp-execution-chart:first-child { grid-column: auto; } }
    @media (max-width: 575.98px) { .pfp-execution-kpis { grid-template-columns: 1fr; } .pfp-execution-hero { padding: 18px; } .pfp-execution-chart img { min-height: 180px; } }
    @media print { .pfp-execution { break-before: page; } .pfp-execution-chart { break-inside: avoid; } }
</style>

<section class="pfp-execution" id="integratedExecutionDashboard">
    <div class="pfp-execution-hero">
        <div class="pfp-execution-eyebrow">Section 2 · Integrated financial execution analytics</div>
        <h4 class="fw-bold text-white mt-2 mb-2">Financial Execution Analytics</h4>
        <p class="mb-0 text-white-50">The complete execution view is now part of this Project Financial Position report and its PDF export.</p>
        <div class="pfp-execution-scope">
            <span>Sector: {{ data_get($execution, 'executionFilters.sector', 'All Sectors') }}</span>
            <span>Program: {{ data_get($execution, 'executionFilters.program', $program->name ?? 'Selected Program') }}</span>
            <span>Project: {{ data_get($execution, 'executionFilters.project', 'All Projects') }}</span>
            <span>{{ number_format((int) ($executionSummary['active_years'] ?? $executionRows->count())) }} implementation years</span>
        </div>
    </div>

    <div class="pfp-execution-kpis">
        @foreach ($executionKpis as $kpi)
            <div class="pfp-execution-kpi {{ $kpi['tone'] }}">
                <div class="label">{{ $kpi['label'] }}</div>
                <div class="value">{{ $kpi['value'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="pfp-execution-charts">
        @foreach ($executionChartCards as $chartCard)
            <article class="pfp-execution-chart">
                <h6>{{ $chartCard['title'] }}</h6>
                <p>{{ $chartCard['note'] }}</p>
                @if (! empty($executionCharts[$chartCard['key']]))
                    <img src="{{ $executionCharts[$chartCard['key']] }}" alt="{{ $chartCard['title'] }} chart">
                @else
                    <div class="text-muted small py-5 text-center">No chart data is available for this scope.</div>
                @endif
            </article>
        @endforeach
    </div>

    <div class="pfp-execution-panel">
        <div class="pfp-execution-panel-head">
            <h5>Year-by-Year Execution</h5>
            <p>Annual allocation, commitment, disbursement, remaining balance, and utilization rates.</p>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered pfp-execution-table">
                <thead><tr><th>Year</th><th class="text-end">Allocation</th><th class="text-end">Committed</th><th class="text-end">Disbursed</th><th class="text-end">Remaining</th><th class="text-center">Commitment %</th><th class="text-center">Disbursement %</th></tr></thead>
                <tbody>
                    @forelse ($executionRows as $row)
                        @php
                            $commitmentTone = ($row['execution_rate'] ?? 0) < 50 ? 'bad' : (($row['execution_rate'] ?? 0) < 80 ? 'warn' : '');
                            $disbursementTone = ($row['disbursement_rate'] ?? 0) < 50 ? 'bad' : (($row['disbursement_rate'] ?? 0) < 80 ? 'warn' : '');
                        @endphp
                        <tr><td><strong>{{ $row['year'] }}</strong></td><td class="text-end">{{ number_format($row['allocation'] ?? 0, 2) }}</td><td class="text-end">{{ number_format($row['commitment'] ?? 0, 2) }}</td><td class="text-end">{{ number_format($row['disbursement'] ?? 0, 2) }}</td><td class="text-end">{{ number_format($row['remaining'] ?? 0, 2) }}</td><td class="text-center"><span class="pfp-execution-rate {{ $commitmentTone }}">{{ $executionPercent($row['execution_rate'] ?? 0) }}</span></td><td class="text-center"><span class="pfp-execution-rate {{ $disbursementTone }}">{{ $executionPercent($row['disbursement_rate'] ?? 0) }}</span></td></tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No yearly execution records are available.</td></tr>
                    @endforelse
                </tbody>
                <tfoot><tr><th>Total</th><td class="text-end">{{ number_format($executionTotals['allocation'] ?? 0, 2) }}</td><td class="text-end">{{ number_format($executionTotals['commitment'] ?? 0, 2) }}</td><td class="text-end">{{ number_format($executionTotals['disbursement'] ?? 0, 2) }}</td><td class="text-end">{{ number_format($executionTotals['remaining'] ?? 0, 2) }}</td><td class="text-center">{{ $executionPercent($executionTotals['execution_rate'] ?? 0) }}</td><td class="text-center">{{ $executionPercent($executionTotals['disbursement_rate'] ?? 0) }}</td></tr></tfoot>
            </table>
        </div>
    </div>

    <div class="pfp-execution-panel">
        <div class="pfp-execution-panel-head">
            <h5>Component Execution Breakdown</h5>
            <p>Reconciled allocation, commitment, disbursement, remaining balance, and rates by component.</p>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered pfp-execution-table">
                <thead><tr><th>Component</th><th class="text-end">Allocation</th><th class="text-end">Committed</th><th class="text-end">Disbursed</th><th class="text-end">Remaining</th><th class="text-center">Commitment %</th><th class="text-center">Disbursement %</th></tr></thead>
                <tbody>
                    @forelse ($executionComponents as $component)
                        <tr><td class="pfp-execution-component"><strong>{{ $component['label'] ?? 'Unassigned' }}</strong>@if (! empty($component['description']))<small>{{ $component['description'] }}</small>@endif</td><td class="text-end">{{ number_format($component['allocation'] ?? 0, 2) }}</td><td class="text-end">{{ number_format($component['commitment'] ?? 0, 2) }}</td><td class="text-end">{{ number_format($component['disbursement'] ?? 0, 2) }}</td><td class="text-end">{{ number_format($component['remaining'] ?? 0, 2) }}</td><td class="text-center">{{ $executionPercent($component['execution_rate'] ?? 0) }}</td><td class="text-center">{{ $executionPercent($component['disbursement_rate'] ?? 0) }}</td></tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No component execution records are available.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="pfp-execution-panel">
        <div class="pfp-execution-panel-head"><h5>Execution Insights</h5><p>Risk and progress signals calculated from the same audited execution snapshot.</p></div>
        <div class="pfp-execution-insights">
            @forelse ($executionInsights as $insight)
                <div class="pfp-execution-insight {{ $insight['type'] ?? '' }}"><strong>{{ $insight['title'] ?? 'Execution insight' }}</strong><span>{{ $insight['message'] ?? '' }}</span></div>
            @empty
                <div class="pfp-execution-insight success"><strong>No significant execution risks detected</strong><span>The selected programme does not currently show a material execution anomaly.</span></div>
            @endforelse
        </div>
    </div>
</section>
