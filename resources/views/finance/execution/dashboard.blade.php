@extends('layouts.app')

@section('content')
    @php
        $rows = collect($executionBreakdownRows ?? []);
        $totals = $executionBreakdownTotals ?? [
            'allocation' => $totalAllocation ?? 0,
            'commitment' => $totalCommitment ?? 0,
            'disbursement' => $totalDisbursements ?? 0,
            'remaining' => ($totalAllocation ?? 0) - ($totalCommitment ?? 0),
            'execution_rate' => $executionRate ?? 0,
            'disbursement_rate' => $disbursementRate ?? 0,
        ];
        $summary = $executionSummary ?? [];
        $currencyCode = $currency ?? ($summary['currency'] ?? 'USD');
        $money = fn ($value) => $currencyCode . ' ' . number_format((float) $value, 2);
        $compactMoney = function ($value) use ($currencyCode) {
            $value = (float) $value;
            if (abs($value) >= 1000000) {
                return $currencyCode . ' ' . number_format($value / 1000000, 2) . 'M';
            }
            if (abs($value) >= 1000) {
                return $currencyCode . ' ' . number_format($value / 1000, 1) . 'K';
            }
            return $currencyCode . ' ' . number_format($value, 2);
        };
        $percent = fn ($value, $decimals = 1) => number_format(max(0, (float) $value), $decimals) . '%';
        $scopeLabel = match ($scopeType ?? 'global') {
            'sector' => 'Sector: ' . ($scope?->name ?? 'N/A'),
            'program' => 'Program: ' . ($scope?->name ?? 'N/A'),
            'project' => 'Project: ' . ($scope?->name ?? 'N/A'),
            default => 'All sectors, programs, and projects',
        };
        $budgetEnvelope = (float) ($summary['budget_envelope'] ?? $totals['allocation'] ?? 0);
        $kpiCards = [
            [
                'label' => 'Budget Envelope',
                'value' => $compactMoney($budgetEnvelope),
                'meta' => $money($budgetEnvelope),
                'icon' => 'feather-target',
                'tone' => 'teal',
            ],
            [
                'label' => 'Planned Commitments',
                'value' => $compactMoney($totals['commitment'] ?? 0),
                'meta' => $percent($totals['execution_rate'] ?? 0) . ' commitment rate',
                'icon' => 'feather-lock',
                'tone' => 'gold',
            ],
            [
                'label' => 'Disbursed',
                'value' => $compactMoney($totals['disbursement'] ?? 0),
                'meta' => $percent($totals['disbursement_rate'] ?? 0) . ' paid',
                'icon' => 'feather-send',
                'tone' => 'green',
            ],
            [
                'label' => 'Remaining Global Commitments',
                'value' => $compactMoney($totals['remaining'] ?? 0),
                'meta' => $money($totals['remaining'] ?? 0),
                'icon' => 'feather-pie-chart',
                'tone' => 'blue',
            ],
            [
                'label' => 'Unpaid Commitments',
                'value' => $compactMoney($summary['unpaid_commitments'] ?? 0),
                'meta' => $money($summary['unpaid_commitments'] ?? 0),
                'icon' => 'feather-clock',
                'tone' => 'coral',
            ],
            [
                'label' => 'Peak Commitment Year',
                'value' => $summary['peak_commitment_year'] ?? 'N/A',
                'meta' => $compactMoney($summary['peak_commitment'] ?? 0),
                'icon' => 'feather-trending-up',
                'tone' => 'violet',
            ],
        ];
    @endphp

    <style>
        .execution-shell {
            --ink: #10212f;
            --muted: #667085;
            --line: #d9e2ea;
            --panel: #ffffff;
            --wash: #f4f7f9;
            --teal: #0f766e;
            --green: #168a5b;
            --gold: #b7791f;
            --blue: #2563eb;
            --coral: #d65a31;
            --violet: #6d5bd0;
            background: var(--wash);
            margin: -1.5rem;
            padding: 1.5rem;
            min-height: calc(100vh - 70px);
            color: var(--ink);
        }

        .execution-topbar {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 1rem;
            align-items: start;
            margin-bottom: 1rem;
        }

        .execution-title {
            font-size: 1.35rem;
            font-weight: 800;
            margin: 0;
            letter-spacing: 0;
        }

        .execution-scope {
            color: var(--muted);
            font-size: .92rem;
            margin-top: .25rem;
        }

        .execution-pdf-btn {
            background: var(--ink);
            border: 0;
            color: #fff;
            font-weight: 800;
            border-radius: 8px;
            padding: .72rem 1rem;
        }

        .execution-pdf-btn:hover,
        .execution-pdf-btn:focus {
            color: #fff;
            background: #0b1721;
        }

        .execution-filter-panel,
        .execution-hero,
        .execution-kpi,
        .execution-panel,
        .execution-table-panel,
        .execution-insight-panel {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: 0 10px 24px rgba(16, 33, 47, .06);
        }

        .execution-filter-panel {
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .execution-filter-panel .form-label {
            color: var(--muted);
            font-size: .78rem;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: .04em;
        }

        .execution-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(260px, .8fr);
            gap: 1rem;
            padding: 1.25rem;
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .execution-hero-figure {
            font-size: clamp(2rem, 5vw, 4rem);
            font-weight: 900;
            line-height: 1;
            letter-spacing: 0;
            margin: .15rem 0 .5rem;
        }

        .execution-hero-label {
            color: var(--muted);
            font-size: .82rem;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: .06em;
        }

        .execution-hero-sub {
            color: var(--muted);
            max-width: 680px;
            margin: 0;
        }

        .execution-hero-metrics {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .75rem;
        }

        .execution-mini {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: .85rem;
            min-width: 0;
        }

        .execution-mini span {
            display: block;
            color: var(--muted);
            font-size: .75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .execution-mini strong {
            display: block;
            font-size: 1.15rem;
            margin-top: .2rem;
        }

        .execution-kpi-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: .85rem;
            margin-bottom: 1rem;
        }

        .execution-kpi {
            padding: 1rem;
            min-width: 0;
            position: relative;
            overflow: hidden;
        }

        .execution-kpi::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 4px;
            background: var(--tone, var(--teal));
        }

        .execution-kpi .icon {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            color: var(--tone, var(--teal));
            background: color-mix(in srgb, var(--tone, var(--teal)) 12%, white);
            margin-bottom: .75rem;
        }

        .execution-kpi .label {
            color: var(--muted);
            font-size: .76rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .04em;
            white-space: normal;
        }

        .execution-kpi .value {
            font-size: 1.35rem;
            font-weight: 900;
            margin-top: .25rem;
            overflow-wrap: anywhere;
        }

        .execution-kpi .meta {
            color: var(--muted);
            font-size: .85rem;
            margin-top: .15rem;
        }

        .tone-teal { --tone: var(--teal); }
        .tone-green { --tone: var(--green); }
        .tone-gold { --tone: var(--gold); }
        .tone-blue { --tone: var(--blue); }
        .tone-coral { --tone: var(--coral); }
        .tone-violet { --tone: var(--violet); }

        .execution-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .execution-panel {
            padding: 1rem;
            min-height: 310px;
        }

        .execution-panel.span-8 { grid-column: span 8; }
        .execution-panel.span-6 { grid-column: span 6; }
        .execution-panel.span-4 { grid-column: span 4; }

        .execution-panel-head {
            display: flex;
            justify-content: space-between;
            gap: .75rem;
            align-items: flex-start;
            margin-bottom: .75rem;
        }

        .execution-panel-title {
            font-weight: 900;
            margin: 0;
            font-size: .98rem;
        }

        .execution-panel-note {
            color: var(--muted);
            margin: .18rem 0 0;
            font-size: .84rem;
        }

        .execution-chart {
            position: relative;
            min-height: 235px;
        }

        .execution-table-panel {
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .execution-table-panel table {
            margin-bottom: 0;
        }

        .execution-table-panel thead th {
            white-space: nowrap;
            color: var(--muted);
            font-size: .76rem;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .execution-table-panel td,
        .execution-table-panel th {
            vertical-align: middle;
        }

        .execution-rate-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 68px;
            padding: .34rem .55rem;
            border-radius: 999px;
            font-weight: 900;
            font-size: .78rem;
        }

        .rate-low { color: #991b1b; background: #fee2e2; }
        .rate-mid { color: #92400e; background: #fef3c7; }
        .rate-good { color: #14532d; background: #dcfce7; }

        .execution-insight-panel {
            padding: 1rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 1400px) {
            .execution-kpi-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 992px) {
            .execution-topbar,
            .execution-hero {
                grid-template-columns: 1fr;
            }

            .execution-panel.span-8,
            .execution-panel.span-6,
            .execution-panel.span-4 {
                grid-column: span 12;
            }
        }

        @media (max-width: 768px) {
            .execution-shell {
                margin: -1rem;
                padding: 1rem;
            }

            .execution-kpi-grid,
            .execution-hero-metrics {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="execution-shell">
        <div class="execution-topbar">
            <div>
                <h4 class="execution-title">Execution Dashboard</h4>
                <div class="execution-scope">{{ $scopeLabel }}</div>
            </div>
            <a href="{{ route('finance.execution.dashboard.export.pdf', request()->query()) }}" class="btn execution-pdf-btn">
                <i class="feather-download me-1"></i> Download PDF
            </a>
        </div>

        <div class="execution-filter-panel">
            <form method="GET" action="{{ route('finance.execution.dashboard') }}" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Sector</label>
                    <select name="sector_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All Sectors</option>
                        @foreach ($sectors as $sector)
                            <option value="{{ $sector->id }}" @selected(request('sector_id') == $sector->id)>
                                {{ $sector->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Program</label>
                    <select name="program_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All Programs</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->id }}" @selected(request('program_id') == $program->id)>
                                {{ $program->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Project</label>
                    <select name="project_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All Projects</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected(request('project_id') == $project->id)>
                                {{ $project->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>

        <section class="execution-hero">
            <div>
                <div class="execution-hero-label">Budget Envelope</div>
                <div class="execution-hero-figure">{{ $compactMoney($budgetEnvelope) }}</div>
                <p class="execution-hero-sub">
                    {{ $money($budgetEnvelope) }} approved for the selected execution scope.
                </p>
            </div>
            <div class="execution-hero-metrics">
                <div class="execution-mini">
                    <span>Commitment Rate</span>
                    <strong>{{ $percent($totals['execution_rate'] ?? 0, 1) }}</strong>
                </div>
                <div class="execution-mini">
                    <span>Disbursement Rate</span>
                    <strong>{{ $percent($totals['disbursement_rate'] ?? 0, 1) }}</strong>
                </div>
                <div class="execution-mini">
                    <span>Latest Year</span>
                    <strong>{{ $summary['latest_year'] ?? 'N/A' }}</strong>
                </div>
                <div class="execution-mini">
                    <span>Years</span>
                    <strong>{{ number_format($summary['active_years'] ?? count($years)) }}</strong>
                </div>
            </div>
        </section>

        <section class="execution-kpi-grid">
            @foreach ($kpiCards as $card)
                <div class="execution-kpi tone-{{ $card['tone'] }}">
                    <span class="icon"><i class="{{ $card['icon'] }}"></i></span>
                    <div class="label">{{ $card['label'] }}</div>
                    <div class="value">{{ $card['value'] }}</div>
                    <div class="meta">{{ $card['meta'] }}</div>
                </div>
            @endforeach
        </section>

        <section class="execution-grid">
            <div class="execution-panel span-8">
                <div class="execution-panel-head">
                    <div>
                        <h6 class="execution-panel-title">Global, Planned, and Disbursed</h6>
                        <p class="execution-panel-note">Cumulative execution trend</p>
                    </div>
                </div>
                <div class="execution-chart"><canvas id="executionLineChart"></canvas></div>
            </div>

            <div class="execution-panel span-4">
                <div class="execution-panel-head">
                    <div>
                        <h6 class="execution-panel-title">Execution Mix</h6>
                        <p class="execution-panel-note">Disbursed, planned not paid, and remaining global commitments</p>
                    </div>
                </div>
                <div class="execution-chart"><canvas id="executionMixChart"></canvas></div>
            </div>

            <div class="execution-panel span-6">
                <div class="execution-panel-head">
                    <div>
                        <h6 class="execution-panel-title">Rate Movement</h6>
                        <p class="execution-panel-note">Planned and disbursed against global commitments</p>
                    </div>
                </div>
                <div class="execution-chart"><canvas id="executionRateChart"></canvas></div>
            </div>

            <div class="execution-panel span-6">
                <div class="execution-panel-head">
                    <div>
                        <h6 class="execution-panel-title">Cumulative Momentum</h6>
                        <p class="execution-panel-note">Running global, planned, and payment flow</p>
                    </div>
                </div>
                <div class="execution-chart"><canvas id="executionCumulativeChart"></canvas></div>
            </div>

            <div class="execution-panel span-6">
                <div class="execution-panel-head">
                    <div>
                        <h6 class="execution-panel-title">Cumulative Financial Profile</h6>
                        <p class="execution-panel-note">Running totals by year</p>
                    </div>
                </div>
                <div class="execution-chart"><canvas id="executionAnnualProfileChart"></canvas></div>
            </div>

            <div class="execution-panel span-6">
                <div class="execution-panel-head">
                    <div>
                        <h6 class="execution-panel-title">Variance Control</h6>
                        <p class="execution-panel-note">Running remaining global commitments after planned commitments</p>
                    </div>
                </div>
                <div class="execution-chart"><canvas id="executionVarianceChart"></canvas></div>
            </div>

            <div class="execution-panel span-6">
                <div class="execution-panel-head">
                    <div>
                        <h6 class="execution-panel-title">Quality Radar</h6>
                        <p class="execution-panel-note">Execution balance and coverage</p>
                    </div>
                </div>
                <div class="execution-chart"><canvas id="executionRadarChart"></canvas></div>
            </div>

            <div class="execution-panel span-6">
                <div class="execution-panel-head">
                    <div>
                        <h6 class="execution-panel-title">Exposure Concentration</h6>
                        <p class="execution-panel-note">Cumulative commitment scale and variance pressure</p>
                    </div>
                </div>
                <div class="execution-chart"><canvas id="executionBubbleChart"></canvas></div>
            </div>
        </section>

        <section class="execution-table-panel">
            <div class="execution-panel-head">
                <div>
                    <h5 class="execution-panel-title">Execution Performance Breakdown</h5>
                    <p class="execution-panel-note">Year-by-year global commitments, planned commitments, disbursements, remaining balance, and rates</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle w-100" id="executionTable">
                    <thead>
                        <tr class="text-center">
                            <th>Year</th>
                            <th class="text-end">Global Commitments</th>
                            <th class="text-end">Planned Commitments</th>
                            <th class="text-end">Disbursed Amount</th>
                            <th class="text-end">Remaining</th>
                            <th class="text-center">Commitment Rate</th>
                            <th class="text-center">Disbursement Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $executionClass = ($row['execution_rate'] ?? 0) < 50 ? 'rate-low' : (($row['execution_rate'] ?? 0) < 80 ? 'rate-mid' : 'rate-good');
                                $disbursementClass = ($row['disbursement_rate'] ?? 0) < 50 ? 'rate-low' : (($row['disbursement_rate'] ?? 0) < 80 ? 'rate-mid' : 'rate-good');
                            @endphp
                            <tr>
                                <td class="fw-semibold text-center">{{ $row['year'] }}</td>
                                <td class="text-end">{{ number_format($row['allocation'], 2) }}</td>
                                <td class="text-end">{{ number_format($row['commitment'], 2) }}</td>
                                <td class="text-end">{{ number_format($row['disbursement'], 2) }}</td>
                                <td class="text-end fw-semibold">{{ number_format($row['remaining'], 2) }}</td>
                                <td class="text-center">
                                    <span class="execution-rate-pill {{ $executionClass }}">{{ $percent($row['execution_rate']) }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="execution-rate-pill {{ $disbursementClass }}">{{ $percent($row['disbursement_rate']) }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="text-center fw-bold">TOTAL</td>
                            <td class="text-end fw-bold">{{ number_format($totals['allocation'], 2) }}</td>
                            <td class="text-end fw-bold">{{ number_format($totals['commitment'], 2) }}</td>
                            <td class="text-end fw-bold">{{ number_format($totals['disbursement'], 2) }}</td>
                            <td class="text-end fw-bold">{{ number_format($totals['remaining'], 2) }}</td>
                            <td class="text-center"><span class="execution-rate-pill rate-good">{{ $percent($totals['execution_rate']) }}</span></td>
                            <td class="text-center"><span class="execution-rate-pill rate-good">{{ $percent($totals['disbursement_rate']) }}</span></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <section class="execution-insight-panel">
            <div class="execution-panel-head">
                <div>
                    <h5 class="execution-panel-title">Execution Insights</h5>
                    <p class="execution-panel-note">Risk and progress signals from the current financial position</p>
                </div>
            </div>
            @forelse($aiInsights as $insight)
                <div class="alert alert-{{ $insight['type'] }} mb-3">
                    <h6 class="fw-semibold mb-1">{{ $insight['title'] }}</h6>
                    <p class="mb-0">{{ $insight['message'] }}</p>
                </div>
            @empty
                <p class="text-muted mb-0">No significant execution risks or anomalies detected.</p>
            @endforelse
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (!window.Chart) {
                return;
            }

            const rows = @json($rows->values());
            const totals = @json($totals);
            const radarMetrics = @json($radarMetrics ?? []);
            const currency = @json($currencyCode);
            const labels = rows.map(row => String(row.year));
            const allocations = rows.map(row => Number(row.allocation || 0));
            const commitments = rows.map(row => Number(row.commitment || 0));
            const disbursements = rows.map(row => Number(row.disbursement || 0));
            const runningTotal = values => {
                let total = 0;
                return values.map(value => {
                    total += Number(value || 0);
                    return Number(total.toFixed(2));
                });
            };
            const cumulativeAllocation = runningTotal(allocations);
            const cumulativeCommitment = runningTotal(commitments);
            const cumulativeDisbursement = runningTotal(disbursements);
            const cumulativeRemaining = cumulativeAllocation.map((value, index) => (
                value - Number(cumulativeCommitment[index] || 0)
            ));
            const cumulativeExecutionRates = cumulativeAllocation.map((value, index) => (
                value > 0 ? (Number(cumulativeCommitment[index] || 0) / value) * 100 : 0
            ));
            const cumulativeDisbursementRates = cumulativeAllocation.map((value, index) => (
                value > 0 ? (Number(cumulativeDisbursement[index] || 0) / value) * 100 : 0
            ));
            const unpaidCommitments = Math.max(Number(totals.commitment || 0) - Number(totals.disbursement || 0), 0);
            const mixValues = [
                Math.max(Number(totals.disbursement || 0), 0),
                unpaidCommitments,
                Math.max(Number(totals.remaining || 0), 0),
            ];

            const money = value => `${currency} ${new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(Number(value || 0))}`;
            const compactMoney = value => `${currency} ${new Intl.NumberFormat('en-US', {
                notation: 'compact',
                maximumFractionDigits: 2
            }).format(Number(value || 0))}`;

            Chart.defaults.font.family = "'Inter','Segoe UI',sans-serif";
            Chart.defaults.font.size = 12;
            Chart.defaults.color = '#667085';

            const commonPlugins = {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 8
                    }
                },
                tooltip: {
                    callbacks: {
                        label: context => {
                            const label = context.dataset.label || context.label || '';
                            const value = context.parsed.y ?? context.parsed.x ?? context.parsed ?? 0;
                            return `${label}: ${context.dataset.percent ? Number(value).toFixed(1) + '%' : money(value)}`;
                        }
                    }
                }
            };

            const makeChart = (id, config) => {
                const element = document.getElementById(id);
                if (!element) {
                    return null;
                }
                return new Chart(element, config);
            };

            makeChart('executionLineChart', {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Cumulative Global Commitments',
                            data: cumulativeAllocation,
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37,99,235,.12)',
                            fill: true,
                            tension: .35,
                            borderWidth: 2
                        },
                        {
                            label: 'Cumulative Planned Commitments',
                            data: cumulativeCommitment,
                            borderColor: '#b7791f',
                            backgroundColor: 'rgba(183,121,31,.12)',
                            fill: true,
                            tension: .35,
                            borderWidth: 2
                        },
                        {
                            label: 'Cumulative Recorded Disbursements',
                            data: cumulativeDisbursement,
                            borderColor: '#168a5b',
                            backgroundColor: 'rgba(22,138,91,.1)',
                            fill: false,
                            tension: .35,
                            borderWidth: 3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: commonPlugins,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: value => compactMoney(value) }
                        }
                    }
                }
            });

            makeChart('executionMixChart', {
                type: 'doughnut',
                data: {
                    labels: ['Disbursed', 'Unpaid Commitments', 'Remaining Global Commitments'],
                    datasets: [{
                        data: mixValues,
                        backgroundColor: ['#168a5b', '#d65a31', '#2563eb'],
                        borderColor: '#fff',
                        borderWidth: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '62%',
                    plugins: commonPlugins
                }
            });

            makeChart('executionRateChart', {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Commitment Rate',
                            data: cumulativeExecutionRates,
                            percent: true,
                            borderColor: '#0f766e',
                            backgroundColor: 'rgba(15,118,110,.13)',
                            fill: true,
                            tension: .35,
                            borderWidth: 2
                        },
                        {
                            label: 'Disbursement Rate',
                            data: cumulativeDisbursementRates,
                            percent: true,
                            borderColor: '#6d5bd0',
                            backgroundColor: 'rgba(109,91,208,.13)',
                            fill: true,
                            tension: .35,
                            borderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: commonPlugins,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: value => `${value}%` }
                        }
                    }
                }
            });

            makeChart('executionCumulativeChart', {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Cumulative Global Commitments',
                            data: cumulativeAllocation,
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37,99,235,.12)',
                            fill: true,
                            tension: .3
                        },
                        {
                            label: 'Cumulative Planned Commitments',
                            data: cumulativeCommitment,
                            borderColor: '#b7791f',
                            backgroundColor: 'rgba(183,121,31,.12)',
                            fill: true,
                            tension: .3
                        },
                        {
                            label: 'Cumulative Recorded Disbursements',
                            data: cumulativeDisbursement,
                            borderColor: '#168a5b',
                            backgroundColor: 'rgba(22,138,91,.12)',
                            fill: true,
                            tension: .3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: commonPlugins,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: value => compactMoney(value) }
                        }
                    }
                }
            });

            makeChart('executionAnnualProfileChart', {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        { label: 'Cumulative Global Commitments', data: cumulativeAllocation, backgroundColor: '#2563eb' },
                        { label: 'Cumulative Planned Commitments', data: cumulativeCommitment, backgroundColor: '#b7791f' },
                        { label: 'Cumulative Recorded Disbursements', data: cumulativeDisbursement, backgroundColor: '#168a5b' }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: commonPlugins,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: value => compactMoney(value) }
                        }
                    }
                }
            });

            makeChart('executionVarianceChart', {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Remaining Global Commitments',
                        data: cumulativeRemaining,
                        backgroundColor: cumulativeRemaining.map(value => value > 0 ? '#0f766e' : '#d65a31'),
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: commonPlugins,
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: { callback: value => compactMoney(value) }
                        }
                    }
                }
            });

            makeChart('executionRadarChart', {
                type: 'radar',
                data: {
                    labels: ['Commitment Rate', 'Timeliness', 'Consistency', 'Coverage', 'Risk Control'],
                    datasets: [{
                        label: 'Score',
                        data: [
                            Number(radarMetrics.budget_utilization || 0),
                            Number(radarMetrics.timeliness || 0),
                            Number(radarMetrics.consistency || 0),
                            Number(radarMetrics.coverage || 0),
                            Number(radarMetrics.risk_exposure || 0)
                        ],
                        backgroundColor: 'rgba(109,91,208,.18)',
                        borderColor: '#6d5bd0',
                        pointBackgroundColor: '#6d5bd0'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: commonPlugins,
                    scales: {
                        r: {
                            min: 0,
                            max: 100,
                            ticks: { callback: value => `${value}%` }
                        }
                    }
                }
            });

            makeChart('executionBubbleChart', {
                type: 'bubble',
                data: {
                    datasets: [{
                        label: 'Year Exposure',
                        data: rows.map((row, index) => ({
                            x: Number(cumulativeExecutionRates[index] || 0),
                            y: Number(cumulativeCommitment[index] || 0),
                            r: Math.max(5, Math.min(22, Math.sqrt(Math.max(Math.abs(Number(cumulativeRemaining[index] || 0)), 1)) / 900)),
                            year: row.year
                        })),
                        backgroundColor: 'rgba(214,90,49,.38)',
                        borderColor: '#d65a31'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        ...commonPlugins,
                        tooltip: {
                            callbacks: {
                                label: context => `${context.raw.year}: ${context.raw.x.toFixed(1)}%, ${money(context.raw.y)} cumulative commitment`
                            }
                        }
                    },
                    scales: {
                        x: {
                            min: 0,
                            title: { display: true, text: 'Commitment Rate' },
                            ticks: { callback: value => `${value}%` }
                        },
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Commitment' },
                            ticks: { callback: value => compactMoney(value) }
                        }
                    }
                }
            });

            const table = document.getElementById('executionTable');
            if (table && window.DataTable) {
                new DataTable(table, {
                    paging: true,
                    searching: true,
                    ordering: true,
                    responsive: true,
                    pageLength: 10,
                    lengthMenu: [5, 10, 25, 50],
                    order: [[0, 'asc']],
                    language: {
                        search: 'Search year:',
                        lengthMenu: 'Show _MENU_ entries',
                        info: 'Showing _START_ to _END_ of _TOTAL_ records'
                    }
                });
            }
        });
    </script>
@endsection
