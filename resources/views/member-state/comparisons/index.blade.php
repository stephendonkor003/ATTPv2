@extends('layouts.app')

@section('title', 'Member State Comparison Analytics')

@push('styles')
<style>
.cmp-page{--cmp-ink:#0f172a;--cmp-soft:#64748b;--cmp-line:#dbe7f3}
.cmp-hero{border-radius:18px;padding:1.1rem 1.2rem;border:1px solid rgba(255,255,255,.2);background:linear-gradient(128deg,#0f172a 0%,#0f766e 52%,#0284c7 100%);color:#f8fafc;box-shadow:0 16px 30px rgba(15,23,42,.22)}
.cmp-hero .kicker{font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:rgba(248,250,252,.82)}
.cmp-hero h4{margin:.18rem 0 .35rem;color:#fff;font-weight:800}
.cmp-panel{border:1px solid var(--cmp-line);border-radius:16px;background:#fff;box-shadow:0 8px 20px rgba(15,23,42,.07)}
.cmp-panel .card-header{border-bottom:1px solid #e2e8f0;background:linear-gradient(120deg,#f8fbff 0%,#eef8ff 100%)}
.cmp-filter{border:1px solid #dbeafe;border-radius:12px;background:#f8fbff;padding:.78rem}
.cmp-stat{border:1px solid #dbeafe;border-radius:12px;background:#fff;padding:.78rem .9rem;box-shadow:0 4px 12px rgba(15,23,42,.06)}
.cmp-stat .label{font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b}
.cmp-stat .value{font-size:1.18rem;font-weight:800;color:#0f172a}
.cmp-note{border:1px solid #bae6fd;border-radius:14px;background:linear-gradient(120deg,#f0f9ff 0%,#ecfeff 100%);padding:.9rem}
.cmp-note li{margin-bottom:.35rem;color:#0f172a}
.cmp-chart-card{border:1px solid #dbeafe;border-radius:12px;background:#fff;box-shadow:0 4px 12px rgba(15,23,42,.05)}
.cmp-chart-title{font-size:.84rem;font-weight:700;color:#0f172a;padding:.68rem .82rem;border-bottom:1px solid #e2e8f0;background:#f8fafc}
.cmp-chart-body{padding:.5rem .65rem}
.cmp-table th{white-space:nowrap;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;color:#334155}
.cmp-table td{font-size:.83rem;vertical-align:middle}
.cmp-highlight{background:#ecfeff !important}
.cmp-badge{border-radius:999px;padding:.2rem .54rem;font-size:.7rem;font-weight:700}
.cmp-rank{width:34px;height:34px;border-radius:999px;background:linear-gradient(140deg,#0f766e 0%,#16a34a 100%);display:inline-flex;align-items:center;justify-content:center;color:#fff;font-weight:800}
.cmp-detail{border:1px solid #dbeafe;border-radius:12px;background:#fff;padding:.8rem}
.cmp-detail + .cmp-detail{margin-top:.65rem}
.cmp-empty{border:1px dashed #cbd5e1;border-radius:12px;background:#f8fafc;padding:1rem;text-align:center;color:#64748b}
.cmp-filter .select2-container{width:100% !important}
.cmp-filter .select2-container--default .select2-selection--multiple{min-height:38px;border:1px solid #bfdbfe;border-radius:10px;background:#fff;padding:2px 6px}
.cmp-filter .select2-container--default .select2-selection--multiple .select2-selection__choice{background:#0ea5e9;border-color:#0284c7;color:#fff}
.cmp-filter .select2-container--default .select2-selection--multiple .select2-selection__choice__remove{color:#fff}
.cmp-filter .select2-container--default.select2-container--focus .select2-selection--multiple{border-color:#0ea5e9}
#cmpPeerStateSelect{min-height:160px;border:1px solid #bfdbfe;border-radius:10px;padding:.32rem;background:#fff}
#cmpPeerStateSelect option{padding:.35rem .5rem;border-radius:8px;margin:2px 0}
#cmpPeerStateSelect option:checked{background:linear-gradient(120deg,#0284c7 0%,#0ea5e9 100%);color:#fff}
.cmp-export-group{display:flex;gap:.35rem;align-items:center}
.cmp-export-btn{border:1px solid #bfdbfe;background:#fff;color:#0f172a;font-size:.72rem;padding:.22rem .5rem;border-radius:8px;cursor:pointer}
.cmp-export-btn:hover{background:#eff6ff}
.cmp-export-btn:disabled{opacity:.65;cursor:not-allowed}
.cmp-tooltip{padding:.55rem .6rem;font-size:.74rem}
.cmp-tooltip .title{font-weight:700;color:#0f172a;margin-bottom:.25rem}
.cmp-tooltip .stage{color:#0369a1;font-weight:600}
.cmp-tooltip .list{max-width:300px;max-height:150px;overflow:auto;display:flex;flex-direction:column;gap:.18rem;color:#334155}
.cmp-chip-list{display:flex;flex-wrap:wrap;gap:.3rem}
.cmp-chip{display:inline-flex;align-items:center;padding:.2rem .48rem;border-radius:999px;font-size:.68rem;font-weight:700;background:#ecfeff;color:#0f766e;border:1px solid #99f6e4}
</style>
@endpush

@section('content')
@php
    $currentRank = (int) ($currentRow['rank'] ?? 0);
    $currentIndex = (float) ($currentRow['overall_index'] ?? 0);
    $peerIndex = (float) ($peerAverage['overall_index'] ?? 0);
    $indexGap = round($currentIndex - $peerIndex, 2);
@endphp
<main class="nxl-container cmp-page">
    <div class="cmp-hero mb-4">
        <div class="kicker">Comparison Intelligence</div>
        <h4>Member-State Benchmarking and Progress Explanation</h4>
        <p class="mb-1">Compare your country against multiple member states using treaty progression, approved national data, outreach delivery, and commodity trend outcomes.</p>
        <span class="badge bg-light text-dark">Approved national-data submissions only</span>
    </div>

    <div class="card cmp-panel mb-4" id="cmpSectionFilters">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center gap-2">
                <h5 class="mb-0 fw-bold text-dark"><i class="feather-sliders me-1"></i>Comparison Filters</h5>
                <div class="cmp-export-group">
                    <button type="button" class="cmp-export-btn" data-target="cmpSectionFilters" data-format="png">PNG</button>
                    <button type="button" class="cmp-export-btn" data-target="cmpSectionFilters" data-format="pdf">PDF</button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" class="cmp-filter" id="cmpFilterForm">
                <div class="row g-2">
                    <div class="col-md-2">
                        <label class="form-label">From Date</label>
                        <input type="date" name="from" class="form-control form-control-sm" value="{{ $from }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To Date</label>
                        <input type="date" name="to" class="form-control form-control-sm" value="{{ $to }}">
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <label class="form-label mb-1">Peer Member States (compare multiple countries)</label>
                            <div class="small">
                                <a href="#" id="cmpSelectAllPeers">Select all</a> |
                                <a href="#" id="cmpClearAllPeers">Clear all</a>
                            </div>
                        </div>
                        <select name="peer_state_ids[]" id="cmpPeerStateSelect" class="form-select form-select-sm cmp-select2" multiple>
                            @foreach($allPeerStates as $state)
                                <option value="{{ $state->id }}" @selected($selectedPeerStateIds->contains($state->id))>
                                    {{ $state->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label mb-1">Commodities (compare same commodities across countries)</label>
                        <select name="commodity_ids[]" id="cmpCommoditySelect" class="form-select form-select-sm cmp-select2" multiple>
                            @foreach($allCommodities as $commodity)
                                <option value="{{ $commodity->id }}" @selected(in_array($commodity->id, $filters['commodity_ids'] ?? [], true))>
                                    {{ $commodity->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Aspiration</label>
                        <select name="aspiration_id" id="cmpAspirationFilter" class="form-select form-select-sm">
                            <option value="">All aspirations</option>
                            @foreach($aspirations as $aspiration)
                                <option value="{{ $aspiration->id }}" @selected(($filters['aspiration_id'] ?? '') === $aspiration->id)>Asp {{ $aspiration->number }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Goal</label>
                        <select name="goal_id" id="cmpGoalFilter" class="form-select form-select-sm">
                            <option value="">All goals</option>
                            @foreach($aspirations as $aspiration)
                                @foreach($aspiration->goals as $goal)
                                    <option value="{{ $goal->id }}" data-aspiration="{{ $aspiration->id }}" @selected(($filters['goal_id'] ?? '') === $goal->id)>Goal {{ $goal->number }}</option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Period Type</label>
                        <select name="reporting_period_type" class="form-select form-select-sm">
                            <option value="">All periods</option>
                            <option value="daily" @selected(($filters['reporting_period_type'] ?? '') === 'daily')>Daily</option>
                            <option value="monthly" @selected(($filters['reporting_period_type'] ?? '') === 'monthly')>Monthly</option>
                            <option value="quarterly" @selected(($filters['reporting_period_type'] ?? '') === 'quarterly')>Quarterly</option>
                            <option value="yearly" @selected(($filters['reporting_period_type'] ?? '') === 'yearly')>Yearly</option>
                            <option value="special" @selected(($filters['reporting_period_type'] ?? '') === 'special')>Special</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Progress</label>
                        <select name="progress_status" class="form-select form-select-sm">
                            <option value="">All statuses</option>
                            <option value="not_started" @selected(($filters['progress_status'] ?? '') === 'not_started')>Not Started</option>
                            <option value="in_progress" @selected(($filters['progress_status'] ?? '') === 'in_progress')>In Progress</option>
                            <option value="advanced" @selected(($filters['progress_status'] ?? '') === 'advanced')>Advanced</option>
                            <option value="completed" @selected(($filters['progress_status'] ?? '') === 'completed')>Completed</option>
                            <option value="stalled" @selected(($filters['progress_status'] ?? '') === 'stalled')>Stalled</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Min Data Points</label>
                        <input type="number" min="0" name="min_data_points" class="form-control form-control-sm" value="{{ $filters['min_data_points'] ?? '0' }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Min Cooperation %</label>
                        <input type="number" step="0.1" min="0" max="100" name="min_cooperation" class="form-control form-control-sm" value="{{ $filters['min_cooperation'] ?? '' }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Min Ratified %</label>
                        <input type="number" step="0.1" min="0" max="100" name="min_ratification" class="form-control form-control-sm" value="{{ $filters['min_ratification'] ?? '' }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Commodity Growth</label>
                        <select name="growth_direction" class="form-select form-select-sm">
                            <option value="" @selected(($filters['growth_direction'] ?? '') === '')>Any</option>
                            <option value="positive" @selected(($filters['growth_direction'] ?? '') === 'positive')>Positive</option>
                            <option value="negative" @selected(($filters['growth_direction'] ?? '') === 'negative')>Negative</option>
                            <option value="flat" @selected(($filters['growth_direction'] ?? '') === 'flat')>Near Flat</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Sort By</label>
                        <select name="sort_by" class="form-select form-select-sm">
                            <option value="overall_index" @selected(($filters['sort_by'] ?? 'overall_index') === 'overall_index')>Overall Index</option>
                            <option value="avg_cooperation_score" @selected(($filters['sort_by'] ?? '') === 'avg_cooperation_score')>Cooperation</option>
                            <option value="awareness_composite_score" @selected(($filters['sort_by'] ?? '') === 'awareness_composite_score')>Awareness</option>
                            <option value="avg_outreach_score" @selected(($filters['sort_by'] ?? '') === 'avg_outreach_score')>Outreach</option>
                            <option value="treaty_ratification_rate" @selected(($filters['sort_by'] ?? '') === 'treaty_ratification_rate')>Ratification</option>
                            <option value="treaty_signed_rate" @selected(($filters['sort_by'] ?? '') === 'treaty_signed_rate')>Signed Rate</option>
                            <option value="treaty_original_submission_rate" @selected(($filters['sort_by'] ?? '') === 'treaty_original_submission_rate')>Original Rate</option>
                            <option value="avg_budget_execution_rate" @selected(($filters['sort_by'] ?? '') === 'avg_budget_execution_rate')>Budget Execution</option>
                            <option value="avg_growth_rate" @selected(($filters['sort_by'] ?? '') === 'avg_growth_rate')>Commodity Growth</option>
                            <option value="commodity_count" @selected(($filters['sort_by'] ?? '') === 'commodity_count')>Commodity Coverage</option>
                            <option value="export_value_total" @selected(($filters['sort_by'] ?? '') === 'export_value_total')>Commodity Export Value</option>
                            <option value="data_points" @selected(($filters['sort_by'] ?? '') === 'data_points')>Data Points</option>
                            <option value="people_reached_total" @selected(($filters['sort_by'] ?? '') === 'people_reached_total')>People Reached</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label mb-1">Order</label>
                        <select name="sort_dir" class="form-select form-select-sm">
                            <option value="desc" @selected(($filters['sort_dir'] ?? 'desc') === 'desc')>Desc</option>
                            <option value="asc" @selected(($filters['sort_dir'] ?? '') === 'asc')>Asc</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label mb-1">Rows</label>
                        <input type="number" min="5" max="60" name="max_results" class="form-control form-control-sm" value="{{ $filters['max_results'] ?? '20' }}">
                    </div>
                    <div class="col-12 d-flex gap-2 mt-1">
                        <button class="btn btn-primary btn-sm"><i class="feather-bar-chart-2 me-1"></i>Run Full Comparison</button>
                        <a href="{{ route('member-state.comparisons.index') }}" class="btn btn-light border btn-sm">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if($currentRow)
        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="cmp-stat"><div class="label">Your Rank</div><div class="value">#{{ $currentRank }}</div></div></div>
            <div class="col-md-3"><div class="cmp-stat"><div class="label">Overall Index</div><div class="value text-primary">{{ number_format($currentIndex, 2) }}</div></div></div>
            <div class="col-md-3"><div class="cmp-stat"><div class="label">Peer Avg Gap</div><div class="value {{ $indexGap >= 0 ? 'text-success' : 'text-danger' }}">{{ $indexGap >= 0 ? '+' : '-' }}{{ number_format(abs($indexGap), 2) }}</div></div></div>
            <div class="col-md-3"><div class="cmp-stat"><div class="label">Approved Reports</div><div class="value text-info">{{ number_format((int) ($currentRow['data_points'] ?? 0)) }}</div></div></div>
        </div>
    @endif

    <div class="cmp-note mb-4" id="cmpSectionSummary">
        <div class="d-flex justify-content-end mb-2">
            <div class="cmp-export-group">
                <button type="button" class="cmp-export-btn" data-target="cmpSectionSummary" data-format="png">PNG</button>
                <button type="button" class="cmp-export-btn" data-target="cmpSectionSummary" data-format="pdf">PDF</button>
            </div>
        </div>
        <ul class="mb-0">
            <li><strong>Summary:</strong> {{ $insights['headline'] ?? '' }}</li>
            <li><strong>Strength signal:</strong> {{ $insights['strength'] ?? '' }}</li>
            <li><strong>Priority improvement:</strong> {{ $insights['gap'] ?? '' }}</li>
        </ul>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="cmp-chart-card h-100" id="cmpSectionOverallChart">
                <div class="cmp-chart-title d-flex justify-content-between align-items-center">
                    <span>Overall Index Ranking</span>
                    <div class="cmp-export-group">
                        <button type="button" class="cmp-export-btn" data-target="cmpSectionOverallChart" data-format="png">PNG</button>
                        <button type="button" class="cmp-export-btn" data-target="cmpSectionOverallChart" data-format="pdf">PDF</button>
                    </div>
                </div>
                <div class="cmp-chart-body"><div id="cmpOverallIndexChart" style="height: 330px;"></div></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="cmp-chart-card h-100" id="cmpSectionTreatyChart">
                <div class="cmp-chart-title d-flex justify-content-between align-items-center">
                    <span>Treaty Progression: Signed vs Ratified vs Original</span>
                    <div class="cmp-export-group">
                        <button type="button" class="cmp-export-btn" data-target="cmpSectionTreatyChart" data-format="png">PNG</button>
                        <button type="button" class="cmp-export-btn" data-target="cmpSectionTreatyChart" data-format="pdf">PDF</button>
                    </div>
                </div>
                <div class="cmp-chart-body"><div id="cmpTreatyStageChart" style="height: 330px;"></div></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="cmp-chart-card h-100" id="cmpSectionRadarChart">
                <div class="cmp-chart-title d-flex justify-content-between align-items-center">
                    <span>{{ $memberState?->name }} vs Peer Average (Dimension Radar)</span>
                    <div class="cmp-export-group">
                        <button type="button" class="cmp-export-btn" data-target="cmpSectionRadarChart" data-format="png">PNG</button>
                        <button type="button" class="cmp-export-btn" data-target="cmpSectionRadarChart" data-format="pdf">PDF</button>
                    </div>
                </div>
                <div class="cmp-chart-body"><div id="cmpRadarChart" style="height: 330px;"></div></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="cmp-chart-card h-100" id="cmpSectionTrendChart">
                <div class="cmp-chart-title d-flex justify-content-between align-items-center">
                    <span>Monthly Cooperation Trend (Current vs Peer Average)</span>
                    <div class="cmp-export-group">
                        <button type="button" class="cmp-export-btn" data-target="cmpSectionTrendChart" data-format="png">PNG</button>
                        <button type="button" class="cmp-export-btn" data-target="cmpSectionTrendChart" data-format="pdf">PDF</button>
                    </div>
                </div>
                <div class="cmp-chart-body"><div id="cmpTrendChart" style="height: 330px;"></div></div>
            </div>
        </div>
        <div class="col-lg-12">
            <div class="cmp-chart-card h-100" id="cmpSectionCommodityChart">
                <div class="cmp-chart-title d-flex justify-content-between align-items-center">
                    <span>Commodity Comparison: Growth, Export Value, and Coverage</span>
                    <div class="cmp-export-group">
                        <button type="button" class="cmp-export-btn" data-target="cmpSectionCommodityChart" data-format="png">PNG</button>
                        <button type="button" class="cmp-export-btn" data-target="cmpSectionCommodityChart" data-format="pdf">PDF</button>
                    </div>
                </div>
                <div class="cmp-chart-body"><div id="cmpCommodityChart" style="height: 360px;"></div></div>
            </div>
        </div>
    </div>

    <div class="card cmp-panel mb-4" id="cmpSectionTable">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center gap-2">
                <h5 class="mb-0 fw-bold text-dark"><i class="feather-table me-1"></i>Detailed Comparison Table</h5>
                <div class="cmp-export-group">
                    <button type="button" class="cmp-export-btn" data-target="cmpSectionTable" data-format="png">PNG</button>
                    <button type="button" class="cmp-export-btn" data-target="cmpSectionTable" data-format="pdf">PDF</button>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0 cmp-table">
                    <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Member State</th>
                        <th>Overall</th>
                        <th>Coop</th>
                        <th>Awareness</th>
                        <th>Outreach</th>
                        <th>Treaties S/R/O</th>
                        <th>Budget Exec</th>
                        <th>Commodity Growth</th>
                        <th>Commodity Coverage</th>
                        <th>Commodity Export (USD)</th>
                        <th>Data Points</th>
                        <th>People Reached</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr @class(['cmp-highlight' => $row['is_current']])>
                            <td><span class="cmp-rank">{{ $row['rank'] }}</span></td>
                            <td>
                                <div class="fw-semibold">{{ $row['member_state_name'] }}</div>
                                @if($row['is_current'])<span class="cmp-badge bg-primary text-white">Current State</span>@endif
                            </td>
                            <td><strong>{{ number_format($row['overall_index'], 2) }}</strong></td>
                            <td>{{ number_format($row['avg_cooperation_score'], 1) }}%</td>
                            <td>{{ number_format($row['awareness_composite_score'], 1) }}%</td>
                            <td>{{ number_format($row['avg_outreach_score'], 1) }}%</td>
                            <td>
                                {{ number_format($row['treaty_signed_rate'], 1) }}% /
                                {{ number_format($row['treaty_ratification_rate'], 1) }}% /
                                {{ number_format($row['treaty_original_submission_rate'], 1) }}%
                            </td>
                            <td>{{ number_format($row['avg_budget_execution_rate'], 1) }}%</td>
                            <td>{{ number_format($row['avg_growth_rate'], 2) }}%</td>
                            <td>{{ number_format($row['commodity_count']) }} tracked</td>
                            <td>${{ number_format($row['export_value_total'], 0) }}</td>
                            <td>{{ number_format($row['data_points']) }}</td>
                            <td>{{ number_format($row['people_reached_total']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="text-center py-4 text-muted">No approved comparison data available for the selected period.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card cmp-panel mb-4" id="cmpSectionCommodityMatrix">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center gap-2">
                <h5 class="mb-0 fw-bold text-dark"><i class="feather-package me-1"></i>Commodity Comparison Matrix</h5>
                <div class="cmp-export-group">
                    <button type="button" class="cmp-export-btn" data-target="cmpSectionCommodityMatrix" data-format="png">PNG</button>
                    <button type="button" class="cmp-export-btn" data-target="cmpSectionCommodityMatrix" data-format="pdf">PDF</button>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0 cmp-table">
                    <thead>
                    <tr>
                        <th>Member State</th>
                        <th>Tracked Commodities</th>
                        <th>Commodity Records</th>
                        <th>Avg Growth</th>
                        <th>Total Export Value</th>
                        <th>Total Production Volume</th>
                        <th>Total Export Volume</th>
                        <th>Top Commodities</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr @class(['cmp-highlight' => $row['is_current']])>
                            <td>
                                <div class="fw-semibold">{{ $row['member_state_name'] }}</div>
                                @if($row['is_current'])<span class="cmp-badge bg-primary text-white">Current State</span>@endif
                            </td>
                            <td>{{ number_format((int) ($row['commodity_count'] ?? 0)) }}</td>
                            <td>{{ number_format((int) ($row['commodity_points'] ?? 0)) }}</td>
                            <td>{{ number_format((float) ($row['avg_growth_rate'] ?? 0), 2) }}%</td>
                            <td>${{ number_format((float) ($row['export_value_total'] ?? 0), 0) }}</td>
                            <td>{{ number_format((float) ($row['production_volume_total'] ?? 0), 2) }}</td>
                            <td>{{ number_format((float) ($row['export_volume_total'] ?? 0), 2) }}</td>
                            <td>
                                @php($topCommodities = $row['top_commodities'] ?? [])
                                @if(!empty($topCommodities))
                                    <div class="cmp-chip-list">
                                        @foreach($topCommodities as $commodity)
                                            <span class="cmp-chip" title="{{ $commodity['label'] ?? $commodity['name'] }}">{{ $commodity['name'] }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-muted small">No commodity trend data in selected window.</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No commodity comparison data available for the selected period.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card cmp-panel" id="cmpSectionInsights">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center gap-2">
                <h5 class="mb-0 fw-bold text-dark"><i class="feather-message-square me-1"></i>Country-by-Country Insight Summary</h5>
                <div class="cmp-export-group">
                    <button type="button" class="cmp-export-btn" data-target="cmpSectionInsights" data-format="png">PNG</button>
                    <button type="button" class="cmp-export-btn" data-target="cmpSectionInsights" data-format="pdf">PDF</button>
                </div>
            </div>
        </div>
        <div class="card-body">
            @forelse($rows as $row)
                <div class="cmp-detail">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div>
                            <div class="fw-semibold text-dark">{{ $row['member_state_name'] }} (Rank #{{ $row['rank'] }})</div>
                            <div class="small text-muted">
                                Treaty completion track: {{ $row['signed_count'] }}/{{ $row['total_treaties'] }} signed,
                                {{ $row['ratified_count'] }}/{{ $row['total_treaties'] }} ratified,
                                {{ $row['original_count'] }}/{{ $row['total_treaties'] }} originals submitted.
                            </div>
                        </div>
                        <span class="cmp-badge bg-light text-dark">Index {{ number_format($row['overall_index'], 2) }}</span>
                    </div>
                    <div class="small mt-2 text-dark">{{ $row['summary'] }}</div>
                </div>
            @empty
                <div class="cmp-empty">No insight cards available.</div>
            @endforelse
        </div>
    </div>
</main>
@endsection

@push('scripts')
<script src="{{ asset('admin/assets/vendors/js/select2.min.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const peerSelect = document.getElementById('cmpPeerStateSelect');
    const commoditySelect = document.getElementById('cmpCommoditySelect');
    const selectAllLink = document.getElementById('cmpSelectAllPeers');
    const clearAllLink = document.getElementById('cmpClearAllPeers');
    const aspirationFilter = document.getElementById('cmpAspirationFilter');
    const goalFilter = document.getElementById('cmpGoalFilter');
    const exportButtons = Array.from(document.querySelectorAll('.cmp-export-btn'));

    const initSelectFilters = () => {
        if (!window.jQuery || !window.jQuery.fn || typeof window.jQuery.fn.select2 !== 'function') {
            return false;
        }

        window.jQuery('.cmp-select2').each(function () {
            const $select = window.jQuery(this);
            if ($select.hasClass('select2-hidden-accessible')) {
                return;
            }

            const isPeer = this.id === 'cmpPeerStateSelect';
            $select.select2({
                width: '100%',
                placeholder: isPeer ? 'Select peer member states' : 'Select commodities',
                allowClear: false,
                closeOnSelect: false,
            });
        });

        return true;
    };

    if (!initSelectFilters()) {
        window.setTimeout(initSelectFilters, 250);
        window.setTimeout(initSelectFilters, 800);
    }

    if (peerSelect && selectAllLink) {
        selectAllLink.addEventListener('click', function (event) {
            event.preventDefault();
            Array.from(peerSelect.options).forEach(option => option.selected = true);
            if (initSelectFilters()) {
                window.jQuery(peerSelect).trigger('change');
            }
        });
    }
    if (peerSelect && clearAllLink) {
        clearAllLink.addEventListener('click', function (event) {
            event.preventDefault();
            Array.from(peerSelect.options).forEach(option => option.selected = false);
            if (initSelectFilters()) {
                window.jQuery(peerSelect).trigger('change');
            }
        });
    }

    if (commoditySelect && initSelectFilters()) {
        window.jQuery(commoditySelect).trigger('change');
    }

    const syncGoalOptions = () => {
        if (!aspirationFilter || !goalFilter) return;
        const aspirationId = aspirationFilter.value;
        Array.from(goalFilter.querySelectorAll('option')).forEach((option, index) => {
            if (index === 0) {
                option.hidden = false;
                return;
            }
            const owner = option.getAttribute('data-aspiration');
            const visible = !aspirationId || owner === aspirationId;
            option.hidden = !visible;
            if (!visible && option.selected) {
                goalFilter.value = '';
            }
        });
    };
    if (aspirationFilter) {
        aspirationFilter.addEventListener('change', syncGoalOptions);
    }
    syncGoalOptions();

    const toSafeFilename = (value) => {
        return String(value || 'comparison-section')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    };

    const escapeHtml = (value) => {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    };

    const exportElement = async (targetId, format, triggerBtn) => {
        const sectionEl = document.getElementById(targetId);
        if (!sectionEl) {
            return;
        }

        if (typeof window.html2canvas !== 'function') {
            alert('PNG/PDF export is not available because html2canvas failed to load.');
            return;
        }

        const canvas = await window.html2canvas(sectionEl, {
            backgroundColor: '#ffffff',
            scale: 2,
            useCORS: true,
            allowTaint: true,
            logging: false,
        });

        const fileBase = toSafeFilename(targetId + '-' + new Date().toISOString().slice(0, 10));

        if (format === 'png') {
            const pngUrl = canvas.toDataURL('image/png');
            const link = document.createElement('a');
            link.href = pngUrl;
            link.download = `${fileBase}.png`;
            document.body.appendChild(link);
            link.click();
            link.remove();
            return;
        }

        if (!window.jspdf || !window.jspdf.jsPDF) {
            alert('PDF export is not available because jsPDF failed to load.');
            return;
        }

        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF('p', 'mm', 'a4');
        const pageWidth = pdf.internal.pageSize.getWidth();
        const pageHeight = pdf.internal.pageSize.getHeight();
        const margin = 8;
        const imgWidth = pageWidth - (margin * 2);
        const imgHeight = (canvas.height * imgWidth) / canvas.width;
        const imageData = canvas.toDataURL('image/png');

        let heightLeft = imgHeight;
        let position = margin;

        pdf.addImage(imageData, 'PNG', margin, position, imgWidth, imgHeight);
        heightLeft -= (pageHeight - (margin * 2));

        while (heightLeft > 0) {
            position = heightLeft - imgHeight + margin;
            pdf.addPage();
            pdf.addImage(imageData, 'PNG', margin, position, imgWidth, imgHeight);
            heightLeft -= (pageHeight - (margin * 2));
        }

        pdf.save(`${fileBase}.pdf`);
    };

    exportButtons.forEach((btn) => {
        btn.addEventListener('click', async function () {
            const targetId = btn.getAttribute('data-target');
            const format = btn.getAttribute('data-format');
            if (!targetId || !format) {
                return;
            }

            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = '...';
            try {
                await exportElement(targetId, format, btn);
            } catch (error) {
                console.error(error);
                alert('Export failed for this section. Please try again.');
            } finally {
                btn.disabled = false;
                btn.textContent = originalText;
            }
        });
    });

    if (typeof ApexCharts === 'undefined') {
        return;
    }

    const chartData = @json($chartPayload);
    const labels = chartData.labels || [];
    const overallSeries = chartData.overallIndex || [];
    const signedSeries = chartData.signed || [];
    const ratifiedSeries = chartData.ratification || [];
    const originalSeries = chartData.original || [];
    const commodityGrowthSeries = (chartData.commodityGrowth || []).map(v => Number(v || 0));
    const commodityCoverageSeries = (chartData.commodityCoverage || []).map(v => Number(v || 0));
    const commodityExportSeries = (chartData.commodityExportValue || []).map(v => Number(v || 0));
    const commodityTop = chartData.commodityTop || [];
    const treatyNames = chartData.treatyNames || { signed: [], ratified: [], original: [] };
    const months = chartData.months || [];
    const currentTrend = (chartData.currentTrend || []).map(v => v === null ? null : Number(v));
    const peerTrend = (chartData.peerTrend || []).map(v => v === null ? null : Number(v));

    const overallEl = document.querySelector('#cmpOverallIndexChart');
    if (overallEl) {
        new ApexCharts(overallEl, {
            chart: { type: 'bar', height: 330, toolbar: { show: false } },
            series: [{ name: 'Overall Index', data: overallSeries }],
            xaxis: { categories: labels, max: 100 },
            plotOptions: { bar: { horizontal: true, borderRadius: 6 } },
            dataLabels: { enabled: false },
            colors: ['#0284c7'],
            grid: { borderColor: '#e2e8f0' }
        }).render();
    }

    const treatyEl = document.querySelector('#cmpTreatyStageChart');
    if (treatyEl) {
        new ApexCharts(treatyEl, {
            chart: { type: 'bar', height: 330, toolbar: { show: false } },
            series: [
                { name: 'Signed %', data: signedSeries },
                { name: 'Ratified %', data: ratifiedSeries },
                { name: 'Original %', data: originalSeries }
            ],
            xaxis: { categories: labels },
            plotOptions: { bar: { borderRadius: 5, columnWidth: '48%' } },
            dataLabels: { enabled: false },
            tooltip: {
                custom: function({ series, seriesIndex, dataPointIndex }) {
                    const stageKeys = ['signed', 'ratified', 'original'];
                    const stageLabels = ['Signed', 'Ratified', 'Original'];
                    const stateName = labels[dataPointIndex] || 'Member State';
                    const stageKey = stageKeys[seriesIndex] || 'signed';
                    const stageLabel = stageLabels[seriesIndex] || 'Stage';
                    const value = Number(series?.[seriesIndex]?.[dataPointIndex] || 0).toFixed(1);
                    const names = (treatyNames[stageKey] && treatyNames[stageKey][dataPointIndex]) ? treatyNames[stageKey][dataPointIndex] : [];
                    const namesHtml = names.length
                        ? names.map((name) => `<span>${escapeHtml(name)}</span>`).join('')
                        : '<span>No treaty names available for this stage.</span>';

                    return `
                        <div class="cmp-tooltip">
                            <div class="title">${escapeHtml(stateName)}</div>
                            <div class="stage">${escapeHtml(stageLabel)}: ${value}%</div>
                            <div class="list">${namesHtml}</div>
                        </div>
                    `;
                }
            },
            colors: ['#0ea5e9', '#16a34a', '#f59e0b'],
            grid: { borderColor: '#e2e8f0' }
        }).render();
    }

    const radarEl = document.querySelector('#cmpRadarChart');
    if (radarEl) {
        new ApexCharts(radarEl, {
            chart: { type: 'radar', height: 330, toolbar: { show: false } },
            series: [
                { name: 'Current State', data: chartData.currentDimensions || [0, 0, 0, 0, 0, 0] },
                { name: 'Peer Average', data: chartData.peerDimensions || [0, 0, 0, 0, 0, 0] }
            ],
            labels: ['Cooperation', 'Awareness', 'Outreach', 'Ratification', 'Original', 'Commodity'],
            yaxis: { max: 100, min: 0, tickAmount: 5 },
            stroke: { width: 2 },
            colors: ['#0284c7', '#16a34a'],
            fill: { opacity: 0.2 }
        }).render();
    }

    const trendEl = document.querySelector('#cmpTrendChart');
    if (trendEl) {
        new ApexCharts(trendEl, {
            chart: { type: 'line', height: 330, toolbar: { show: false } },
            series: [
                { name: 'Current State', data: currentTrend },
                { name: 'Peer Average', data: peerTrend }
            ],
            xaxis: { categories: months },
            stroke: { width: 3, curve: 'smooth' },
            markers: { size: 4 },
            colors: ['#0ea5e9', '#16a34a'],
            grid: { borderColor: '#e2e8f0' }
        }).render();
    }

    const commodityEl = document.querySelector('#cmpCommodityChart');
    if (commodityEl) {
        new ApexCharts(commodityEl, {
            chart: { type: 'line', height: 360, toolbar: { show: false } },
            series: [
                { name: 'Avg Growth %', type: 'column', data: commodityGrowthSeries },
                { name: 'Export Value (USD)', type: 'line', data: commodityExportSeries },
                { name: 'Tracked Commodities', type: 'line', data: commodityCoverageSeries }
            ],
            xaxis: { categories: labels },
            yaxis: [
                {
                    title: { text: 'Growth %' },
                    labels: {
                        formatter: (value) => `${Number(value || 0).toFixed(0)}%`
                    }
                },
                {
                    opposite: true,
                    title: { text: 'Export USD / Commodity Count' },
                    labels: {
                        formatter: (value) => {
                            const num = Number(value || 0);
                            if (num >= 1000000000) return `$${(num / 1000000000).toFixed(1)}B`;
                            if (num >= 1000000) return `$${(num / 1000000).toFixed(1)}M`;
                            if (num >= 1000) return `$${(num / 1000).toFixed(0)}K`;
                            return `${num.toFixed(0)}`;
                        }
                    }
                }
            ],
            dataLabels: { enabled: false },
            stroke: { width: [0, 3, 3], curve: 'smooth' },
            plotOptions: { bar: { borderRadius: 5, columnWidth: '42%' } },
            tooltip: {
                custom: function({ dataPointIndex }) {
                    const stateName = labels[dataPointIndex] || 'Member State';
                    const growth = Number(commodityGrowthSeries[dataPointIndex] || 0).toFixed(2);
                    const exportUsd = Number(commodityExportSeries[dataPointIndex] || 0);
                    const coverage = Number(commodityCoverageSeries[dataPointIndex] || 0);
                    const top = Array.isArray(commodityTop[dataPointIndex]) ? commodityTop[dataPointIndex] : [];
                    const topHtml = top.length
                        ? top.map((item) => `<span>${escapeHtml(item.label || item.name || '')}</span>`).join('')
                        : '<span>No top commodity records for this state in the selected period.</span>';

                    return `
                        <div class="cmp-tooltip">
                            <div class="title">${escapeHtml(stateName)}</div>
                            <div class="stage">Growth: ${growth}% | Coverage: ${coverage}</div>
                            <div class="stage">Export Value: $${exportUsd.toLocaleString()}</div>
                            <div class="list">${topHtml}</div>
                        </div>
                    `;
                }
            },
            colors: ['#16a34a', '#0284c7', '#f59e0b'],
            grid: { borderColor: '#e2e8f0' }
        }).render();
    }
});
</script>
@endpush
