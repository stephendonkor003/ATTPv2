@extends('layouts.app')

@section('title', 'Member State National Data')

@push('styles')
<style>
.nd-page { --nd-ink:#0f172a; --nd-soft:#64748b; --nd-line:#dbe7f3; }
.nd-hero{background:linear-gradient(130deg,#0f172a 0%,#115e59 50%,#0ea5e9 100%);border-radius:18px;padding:1.2rem 1.25rem;border:1px solid rgba(255,255,255,.25);color:#f8fafc;box-shadow:0 16px 30px rgba(15,23,42,.24)}
.nd-hero .kicker{text-transform:uppercase;font-size:.72rem;letter-spacing:.09em;color:rgba(248,250,252,.82)}
.nd-hero h4{color:#fff;font-weight:800}
.nd-stats .card{border:1px solid var(--nd-line);border-radius:13px;box-shadow:0 5px 12px rgba(15,23,42,.06)}
.nd-stat-label{color:var(--nd-soft);text-transform:uppercase;letter-spacing:.06em;font-size:.7rem}
.nd-stat-value{font-size:1.22rem;font-weight:800;color:var(--nd-ink)}
.nd-panel{border:1px solid var(--nd-line);border-radius:16px;box-shadow:0 8px 20px rgba(15,23,42,.07)}
.nd-panel .card-header{background:linear-gradient(120deg,#f8fbff 0%,#eef8ff 100%);border-bottom:1px solid #e6eef8}
.nd-section{border:1px solid #dce7f4;border-radius:12px;padding:.9rem;background:#fbfdff}
.nd-section-title{font-weight:800;color:#0f172a;margin-bottom:.6rem}
.nd-textarea{min-height:96px}
.nd-choice-box{max-height:165px;overflow:auto;border:1px solid #d5e2f1;border-radius:10px;padding:.55rem;background:#fff}
.nd-choice-item{display:flex;align-items:flex-start;gap:.5rem;padding:.25rem .15rem}
.nd-choice-item + .nd-choice-item{border-top:1px dashed #e4edf7}
.nd-range-wrap{background:#fff;border:1px solid #d8e7f5;border-radius:10px;padding:.55rem .65rem}
.nd-score-pill{border-radius:999px;padding:.18rem .52rem;font-size:.72rem;font-weight:700;background:#0f766e;color:#fff}
.nd-preview{border:1px solid #cbe6de;border-radius:12px;background:#f4fcfa;padding:.75rem}
.nd-filters{border:1px solid #dbe7f3;border-radius:12px;background:#f8fbff;padding:.75rem}
.nd-entry{border:1px solid #e2ebf7;border-radius:12px;padding:.85rem;background:#fff;box-shadow:0 4px 10px rgba(15,23,42,.05)}
.nd-entry + .nd-entry{margin-top:.75rem}
.nd-order{width:34px;height:34px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;background:linear-gradient(145deg,#0f766e 0%,#16a34a 100%);color:#fff;font-weight:800}
.nd-meta{color:#64748b;font-size:.78rem}
.nd-badge{border-radius:999px;font-size:.72rem;font-weight:700;padding:.2rem .52rem}
.nd-status-not_started{background:#f1f5f9;color:#334155}
.nd-status-in_progress{background:#dbeafe;color:#1d4ed8}
.nd-status-advanced{background:#e0f2fe;color:#0369a1}
.nd-status-completed{background:#dcfce7;color:#166534}
.nd-status-stalled{background:#fee2e2;color:#b91c1c}
.nd-review-pending{background:#fef3c7;color:#92400e}
.nd-review-approved{background:#dcfce7;color:#166534}
.nd-review-revision_required{background:#fee2e2;color:#991b1b}
.nd-review-rejected{background:#e2e8f0;color:#334155}
.nd-chip-list{display:flex;flex-wrap:wrap;gap:.35rem}
.nd-chip{border:1px solid #d3e2f2;border-radius:999px;padding:.16rem .54rem;font-size:.72rem;background:#f8fbff;color:#0f172a}
.nd-trend-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:.6rem}
.nd-trend-card{border:1px solid #dce7f4;border-radius:12px;padding:.65rem;background:#fff}
.nd-trend-bar{height:9px;border-radius:999px;background:#e6eef8;overflow:hidden}
.nd-trend-fill-coop{height:100%;background:linear-gradient(90deg,#0ea5e9 0%,#0284c7 100%)}
.nd-trend-fill-outreach{height:100%;background:linear-gradient(90deg,#22c55e 0%,#15803d 100%)}
.nd-empty{border:1px dashed #d4e2f1;border-radius:12px;padding:1rem;text-align:center;color:#64748b;background:#f8fbff}
</style>
@endpush

@section('content')
@php
    $statusLabels = [
        'not_started' => 'Not Started',
        'in_progress' => 'In Progress',
        'advanced' => 'Advanced',
        'completed' => 'Completed',
        'stalled' => 'Stalled',
    ];
    $reviewLabels = [
        'pending' => 'Pending Review',
        'approved' => 'Approved',
        'revision_required' => 'Revision Required',
        'rejected' => 'Rejected',
    ];
    $flagshipMap = $flagshipProjects->keyBy('id');
    $commodityMap = $commodities->keyBy('id');
    $oldFlagships = old('flagship_projects_supported', []);
    $oldCommodities = old('commodity_focus', []);
@endphp

<main class="nxl-container nd-page">
    <div class="nd-hero mb-4">
        <div class="kicker">National Reporting Workspace</div>
        <h4 class="mb-1">{{ $memberState?->name }} Agenda 2063 Delivery and Citizen Impact Reporting</h4>
        <p class="mb-0">Submit policy steps, outreach results, flagship awareness, and commodity preservation efforts.</p>
    </div>

    @if (session('success')) <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div> @endif
    @if (session('error')) <div class="alert alert-danger border-0 shadow-sm">{{ session('error') }}</div> @endif
    @if ($errors->any()) <div class="alert alert-danger border-0 shadow-sm">Validation issue detected. Please review required reporting fields.</div> @endif

    <div class="row g-3 nd-stats mb-4">
        <div class="col-md-2 col-sm-6"><div class="card h-100"><div class="card-body"><div class="nd-stat-label">Total Reports</div><div class="nd-stat-value">{{ number_format((int) ($stats['total_entries'] ?? 0)) }}</div></div></div></div>
        <div class="col-md-2 col-sm-6"><div class="card h-100"><div class="card-body"><div class="nd-stat-label">Approved</div><div class="nd-stat-value text-success">{{ number_format((int) ($stats['approved_count'] ?? 0)) }}</div></div></div></div>
        <div class="col-md-2 col-sm-6"><div class="card h-100"><div class="card-body"><div class="nd-stat-label">Pending Review</div><div class="nd-stat-value text-warning">{{ number_format((int) ($stats['pending_review_count'] ?? 0)) }}</div></div></div></div>
        <div class="col-md-2 col-sm-6"><div class="card h-100"><div class="card-body"><div class="nd-stat-label">Avg Cooperation</div><div class="nd-stat-value text-primary">{{ number_format((float) ($stats['average_cooperation_score'] ?? 0), 1) }}%</div></div></div></div>
        <div class="col-md-2 col-sm-6"><div class="card h-100"><div class="card-body"><div class="nd-stat-label">Agenda Awareness</div><div class="nd-stat-value text-info">{{ number_format((float) ($stats['average_agenda_awareness_score'] ?? 0), 1) }}%</div></div></div></div>
        <div class="col-md-2 col-sm-6"><div class="card h-100"><div class="card-body"><div class="nd-stat-label">People Reached</div><div class="nd-stat-value text-success">{{ number_format((int) ($stats['people_reached_total'] ?? 0)) }}</div></div></div></div>
    </div>

    <div class="card nd-panel mb-4">
        <div class="card-header"><h5 class="mb-0 fw-bold text-dark"><i class="feather-edit-3 me-1"></i>Submit National Agenda 2063 Report</h5></div>
        <div class="card-body">
            <form action="{{ route('member-state.national-data.store') }}" method="POST" class="row g-3" id="nationalDataForm">
                @csrf

                <div class="col-12">
                    <div class="nd-section">
                        <div class="nd-section-title">Reporting Period and Core Indicator</div>
                        <div class="row g-2">
                            <div class="col-md-2">
                                <label class="form-label">Recorded On</label>
                                <input type="date" name="recorded_on" class="form-control @error('recorded_on') is-invalid @enderror" value="{{ old('recorded_on', now()->toDateString()) }}" required>
                                @error('recorded_on') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Period Type</label>
                                <select name="reporting_period_type" id="reportingPeriodType" class="form-select @error('reporting_period_type') is-invalid @enderror" required>
                                    <option value="daily" @selected(old('reporting_period_type') === 'daily')>Daily</option>
                                    <option value="monthly" @selected(old('reporting_period_type', 'monthly') === 'monthly')>Monthly</option>
                                    <option value="quarterly" @selected(old('reporting_period_type') === 'quarterly')>Quarterly</option>
                                    <option value="yearly" @selected(old('reporting_period_type') === 'yearly')>Yearly</option>
                                    <option value="special" @selected(old('reporting_period_type') === 'special')>Special</option>
                                </select>
                                @error('reporting_period_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-2"><label class="form-label">Year</label><input type="number" name="reporting_year" min="1963" max="2100" value="{{ old('reporting_year', now()->year) }}" class="form-control @error('reporting_year') is-invalid @enderror">@error('reporting_year') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                            <div class="col-md-2"><label class="form-label">Month</label><input type="number" name="reporting_month" min="1" max="12" value="{{ old('reporting_month', now()->month) }}" class="form-control @error('reporting_month') is-invalid @enderror">@error('reporting_month') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                            <div class="col-md-2" id="reportingDayWrap"><label class="form-label">Day</label><input type="number" name="reporting_day" min="1" max="31" value="{{ old('reporting_day', now()->day) }}" class="form-control @error('reporting_day') is-invalid @enderror">@error('reporting_day') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                            <div class="col-md-2">
                                <label class="form-label">Progress Status</label>
                                <select name="progress_status" class="form-select @error('progress_status') is-invalid @enderror" required>
                                    @foreach($statusLabels as $value => $label)
                                        <option value="{{ $value }}" @selected(old('progress_status', 'in_progress') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('progress_status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4"><label class="form-label">Indicator Name</label><input type="text" name="indicator_name" class="form-control @error('indicator_name') is-invalid @enderror" value="{{ old('indicator_name') }}" placeholder="e.g., Community awareness on Agenda 2063" required>@error('indicator_name') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                            <div class="col-md-2"><label class="form-label">Indicator Value</label><input type="number" step="0.0001" name="indicator_value" class="form-control @error('indicator_value') is-invalid @enderror" value="{{ old('indicator_value') }}" required>@error('indicator_value') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                            <div class="col-md-2"><label class="form-label">Unit</label><input type="text" name="unit" class="form-control @error('unit') is-invalid @enderror" value="{{ old('unit') }}" placeholder="% / index">@error('unit') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                            <div class="col-md-2">
                                <label class="form-label">Cooperation Score</label>
                                <div class="nd-range-wrap">
                                    <input type="range" class="form-range" min="0" max="100" step="0.1" id="cooperationScoreRange" name="cooperation_score" value="{{ old('cooperation_score', 50) }}" required>
                                    <div class="d-flex justify-content-between align-items-center"><small class="text-muted">0-100</small><span class="nd-score-pill" id="cooperationScoreValue">{{ old('cooperation_score', 50) }}%</span></div>
                                </div>
                                @error('cooperation_score') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-2"><label class="form-label">Data Source</label><input type="text" name="data_source" class="form-control @error('data_source') is-invalid @enderror" value="{{ old('data_source') }}" placeholder="Ministry / Bureau">@error('data_source') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="nd-section h-100">
                        <div class="nd-section-title">Agenda Alignment, Policy Action, and Outreach Detail</div>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label">Aspiration</label>
                                <select name="aspiration_id" id="aspirationSelect" class="form-select @error('aspiration_id') is-invalid @enderror">
                                    <option value="">Select aspiration</option>
                                    @foreach($aspirations as $aspiration)
                                        <option value="{{ $aspiration->id }}" @selected(old('aspiration_id') == $aspiration->id)>Asp {{ $aspiration->number }} - {{ \Illuminate\Support\Str::limit($aspiration->title, 46) }}</option>
                                    @endforeach
                                </select>
                                @error('aspiration_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Goal</label>
                                <select name="goal_id" id="goalSelect" class="form-select @error('goal_id') is-invalid @enderror">
                                    <option value="">Select goal</option>
                                    @foreach($aspirations as $aspiration)
                                        @foreach($aspiration->goals as $goal)
                                            <option value="{{ $goal->id }}" data-aspiration="{{ $aspiration->id }}" @selected(old('goal_id') == $goal->id)>Goal {{ $goal->number }} (Asp {{ $aspiration->number }})</option>
                                        @endforeach
                                    @endforeach
                                </select>
                                @error('goal_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4"><label class="form-label">Evidence Links</label><input type="text" name="evidence_links" value="{{ old('evidence_links') }}" class="form-control @error('evidence_links') is-invalid @enderror" placeholder="URL(s) separated by comma">@error('evidence_links') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>

                            <div class="col-12"><label class="form-label">Agenda Relevance Summary</label><textarea name="agenda_relevance_summary" class="form-control nd-textarea @error('agenda_relevance_summary') is-invalid @enderror" placeholder="How this report supports Agenda 2063 outcomes.">{{ old('agenda_relevance_summary') }}</textarea>@error('agenda_relevance_summary') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                            <div class="col-md-6"><label class="form-label">Policy Initiatives and Government Actions</label><textarea name="policy_actions" class="form-control nd-textarea @error('policy_actions') is-invalid @enderror" placeholder="Policy reforms, legal instruments, regulations...">{{ old('policy_actions') }}</textarea>@error('policy_actions') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                            <div class="col-md-6"><label class="form-label">Institutional Implementation Steps</label><textarea name="institutional_steps" class="form-control nd-textarea @error('institutional_steps') is-invalid @enderror" placeholder="Implementation model, ministries involved...">{{ old('institutional_steps') }}</textarea>@error('institutional_steps') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                            <div class="col-md-6"><label class="form-label">Livelihood and Socio-economic Impact</label><textarea name="livelihood_impact_summary" class="form-control nd-textarea @error('livelihood_impact_summary') is-invalid @enderror" placeholder="How initiatives improve livelihoods.">{{ old('livelihood_impact_summary') }}</textarea>@error('livelihood_impact_summary') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                            <div class="col-md-6"><label class="form-label">Citizen Engagement and Outreach Results</label><textarea name="public_engagement_summary" class="form-control nd-textarea @error('public_engagement_summary') is-invalid @enderror" placeholder="Campaigns, townhalls, media outreach...">{{ old('public_engagement_summary') }}</textarea>@error('public_engagement_summary') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                            <div class="col-md-6"><label class="form-label">Outreach Channels</label><textarea name="awareness_outreach_channels" class="form-control nd-textarea @error('awareness_outreach_channels') is-invalid @enderror" placeholder="Radio, TV, social media, schools...">{{ old('awareness_outreach_channels') }}</textarea>@error('awareness_outreach_channels') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                            <div class="col-md-6"><label class="form-label">National Projects and Programs</label><textarea name="national_projects_programs" class="form-control nd-textarea @error('national_projects_programs') is-invalid @enderror" placeholder="Country-specific initiatives supporting Agenda 2063.">{{ old('national_projects_programs') }}</textarea>@error('national_projects_programs') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                            <div class="col-md-6"><label class="form-label">Youth and Women Inclusion Actions</label><textarea name="youth_women_inclusion_actions" class="form-control nd-textarea @error('youth_women_inclusion_actions') is-invalid @enderror" placeholder="Inclusion mechanisms and outcomes.">{{ old('youth_women_inclusion_actions') }}</textarea>@error('youth_women_inclusion_actions') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                            <div class="col-md-6"><label class="form-label">Partnerships and Regional Support</label><textarea name="partnerships_support" class="form-control nd-textarea @error('partnerships_support') is-invalid @enderror" placeholder="RECs, AU bodies, private sector, civil society.">{{ old('partnerships_support') }}</textarea>@error('partnerships_support') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                            <div class="col-md-6"><label class="form-label">Citizen Feedback Summary</label><textarea name="citizen_feedback_summary" class="form-control nd-textarea @error('citizen_feedback_summary') is-invalid @enderror" placeholder="Community sentiment, concerns, requests.">{{ old('citizen_feedback_summary') }}</textarea>@error('citizen_feedback_summary') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                            <div class="col-md-6"><label class="form-label">Risks and Challenges</label><textarea name="risk_challenges" class="form-control nd-textarea @error('risk_challenges') is-invalid @enderror" placeholder="Operational constraints and risk profile.">{{ old('risk_challenges') }}</textarea>@error('risk_challenges') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                            <div class="col-md-6"><label class="form-label">Next Steps and Commitments</label><textarea name="next_steps_commitments" class="form-control nd-textarea @error('next_steps_commitments') is-invalid @enderror" placeholder="Immediate and medium-term commitments.">{{ old('next_steps_commitments') }}</textarea>@error('next_steps_commitments') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                            <div class="col-md-6"><label class="form-label">Additional Notes</label><textarea name="notes" class="form-control nd-textarea @error('notes') is-invalid @enderror" placeholder="Extra context for AU analysis.">{{ old('notes') }}</textarea>@error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="nd-section mb-3">
                        <div class="nd-section-title">Public Awareness and Outreach Scoring</div>
                        <div class="mb-2"><label class="form-label">Aspiration Awareness Score</label><div class="nd-range-wrap"><input type="range" class="form-range score-range" min="0" max="100" step="0.1" data-output="#agendaAwarenessValue" name="agenda_awareness_score" value="{{ old('agenda_awareness_score', 50) }}"><div class="text-end"><span class="nd-score-pill" id="agendaAwarenessValue">{{ old('agenda_awareness_score', 50) }}%</span></div></div>@error('agenda_awareness_score') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror</div>
                        <div class="mb-2"><label class="form-label">Flagship Awareness Score</label><div class="nd-range-wrap"><input type="range" class="form-range score-range" min="0" max="100" step="0.1" data-output="#flagshipAwarenessValue" name="flagship_awareness_score" value="{{ old('flagship_awareness_score', 50) }}"><div class="text-end"><span class="nd-score-pill" id="flagshipAwarenessValue">{{ old('flagship_awareness_score', 50) }}%</span></div></div>@error('flagship_awareness_score') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror</div>
                        <div class="mb-2"><label class="form-label">Outreach Coverage Score</label><div class="nd-range-wrap"><input type="range" class="form-range score-range" min="0" max="100" step="0.1" data-output="#outreachCoverageValue" name="outreach_coverage_score" value="{{ old('outreach_coverage_score', 50) }}"><div class="text-end"><span class="nd-score-pill" id="outreachCoverageValue">{{ old('outreach_coverage_score', 50) }}%</span></div></div>@error('outreach_coverage_score') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror</div>
                    </div>

                    <div class="nd-section mb-3">
                        <div class="nd-section-title">Impact Counts and Financials</div>
                        <div class="row g-2">
                            <div class="col-6"><label class="form-label">People Reached</label><input type="number" min="0" name="people_reached" value="{{ old('people_reached') }}" class="form-control @error('people_reached') is-invalid @enderror">@error('people_reached') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                            <div class="col-6"><label class="form-label">Households Impacted</label><input type="number" min="0" name="households_impacted" value="{{ old('households_impacted') }}" class="form-control @error('households_impacted') is-invalid @enderror">@error('households_impacted') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                            <div class="col-6"><label class="form-label">Budget Allocated (USD)</label><input type="number" min="0" step="0.01" name="budget_allocated_usd" value="{{ old('budget_allocated_usd') }}" class="form-control @error('budget_allocated_usd') is-invalid @enderror">@error('budget_allocated_usd') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                            <div class="col-6"><label class="form-label">Budget Executed (USD)</label><input type="number" min="0" step="0.01" name="budget_executed_usd" value="{{ old('budget_executed_usd') }}" class="form-control @error('budget_executed_usd') is-invalid @enderror">@error('budget_executed_usd') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                        </div>
                    </div>

                    <div class="nd-section mb-3">
                        <div class="nd-section-title">Flagship Projects Supported</div>
                        <input type="text" class="form-control form-control-sm mb-2" id="flagshipSearchInput" placeholder="Search flagship projects...">
                        <div class="nd-choice-box" id="flagshipChoiceBox">
                            @forelse($flagshipProjects as $project)
                                <label class="nd-choice-item" data-choice-item data-choice-text="{{ strtolower($project->name) }}">
                                    <input type="checkbox" name="flagship_projects_supported[]" value="{{ $project->id }}" @checked(in_array($project->id, $oldFlagships, true))>
                                    <span><strong>#{{ $project->number }}</strong> {{ $project->name }}</span>
                                </label>
                            @empty
                                <div class="text-muted small">No active flagship projects configured.</div>
                            @endforelse
                        </div>
                        @error('flagship_projects_supported') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>

                    <div class="nd-section mb-3">
                        <div class="nd-section-title">Commodity Focus and Preservation</div>
                        <input type="text" class="form-control form-control-sm mb-2" id="commoditySearchInput" placeholder="Search commodity...">
                        <div class="nd-choice-box" id="commodityChoiceBox">
                            @forelse($commodities as $commodity)
                                <label class="nd-choice-item" data-choice-item data-choice-text="{{ strtolower($commodity->name . ' ' . ($commodity->category ?? '')) }}">
                                    <input type="checkbox" name="commodity_focus[]" value="{{ $commodity->id }}" @checked(in_array($commodity->id, $oldCommodities, true))>
                                    <span><strong>{{ $commodity->name }}</strong> @if($commodity->category)<small class="text-muted">({{ $commodity->category }})</small>@endif</span>
                                </label>
                            @empty
                                <div class="text-muted small">No commodity catalog entries available.</div>
                            @endforelse
                        </div>
                        @error('commodity_focus') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        <label class="form-label mt-2">Commodity Preservation Policies</label>
                        <textarea name="commodity_preservation_policies" class="form-control nd-textarea @error('commodity_preservation_policies') is-invalid @enderror" placeholder="Regulatory, environmental, and sustainability safeguards.">{{ old('commodity_preservation_policies') }}</textarea>
                        @error('commodity_preservation_policies') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <label class="form-label mt-2">Commodity Value Addition and Growth Strategy</label>
                        <textarea name="commodity_value_addition" class="form-control nd-textarea @error('commodity_value_addition') is-invalid @enderror" placeholder="Processing, industrial policy, export value chain improvements.">{{ old('commodity_value_addition') }}</textarea>
                        @error('commodity_value_addition') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="nd-preview">
                        <div class="small text-uppercase text-muted mb-1">Live Summary Preview</div>
                        <div class="small text-dark" id="nationalDataLivePreview">Start typing to generate a live summary of your national report.</div>
                    </div>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary"><i class="feather-save me-1"></i>Submit National Report</button>
                    <button type="reset" class="btn btn-light border" id="nationalDataResetBtn">Reset</button>
                    <span class="small text-muted align-self-center">Submitted entries are reviewed before they are used in reporting and comparisons.</span>
                </div>
            </form>
        </div>
    </div>

    <div class="card nd-panel mb-4">
        <div class="card-header">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0 fw-bold text-dark"><i class="feather-filter me-1"></i>Filter Submitted Reports</h5>
                <form method="GET" action="{{ route('member-state.national-data.index') }}" class="nd-filters d-flex flex-wrap gap-2" id="nationalDataFilterForm">
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" style="min-width:220px;" placeholder="Search indicators, policies, outreach...">
                    <select name="year" class="form-select form-select-sm" data-auto-filter>
                        <option value="">Year</option>
                        @foreach($years as $year)
                            <option value="{{ $year }}" @selected(($filters['year'] ?? '') == $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                    <select name="month" class="form-select form-select-sm" data-auto-filter>
                        <option value="">Month</option>
                        @for($m=1; $m<=12; $m++)
                            <option value="{{ $m }}" @selected((string) ($filters['month'] ?? '') === (string) $m)>{{ $m }}</option>
                        @endfor
                    </select>
                    <input type="number" min="1" max="31" name="day" value="{{ $filters['day'] ?? '' }}" class="form-control form-control-sm" placeholder="Day" style="width:86px;">
                    <select name="reporting_period_type" class="form-select form-select-sm" data-auto-filter>
                        <option value="">Period</option>
                        <option value="daily" @selected(($filters['reporting_period_type'] ?? '') === 'daily')>Daily</option>
                        <option value="monthly" @selected(($filters['reporting_period_type'] ?? '') === 'monthly')>Monthly</option>
                        <option value="quarterly" @selected(($filters['reporting_period_type'] ?? '') === 'quarterly')>Quarterly</option>
                        <option value="yearly" @selected(($filters['reporting_period_type'] ?? '') === 'yearly')>Yearly</option>
                        <option value="special" @selected(($filters['reporting_period_type'] ?? '') === 'special')>Special</option>
                    </select>
                    <select name="progress_status" class="form-select form-select-sm" data-auto-filter>
                        <option value="">Status</option>
                        @foreach($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['progress_status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="review_status" class="form-select form-select-sm" data-auto-filter>
                        <option value="">Review</option>
                        @foreach($reviewLabels as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['review_status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="aspiration_id" class="form-select form-select-sm" data-auto-filter>
                        <option value="">Aspiration</option>
                        @foreach($aspirations as $aspiration)
                            <option value="{{ $aspiration->id }}" @selected(($filters['aspiration_id'] ?? '') == $aspiration->id)>Asp {{ $aspiration->number }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-sm btn-outline-secondary">Apply</button>
                    <a href="{{ route('member-state.national-data.index') }}" class="btn btn-sm btn-light border">Clear</a>
                </form>
            </div>
        </div>
        <div class="card-body">
            <div class="nd-trend-list mb-3">
                @forelse($monthlyTrend as $trend)
                    @php
                        $avgCoop = max(0, min(100, (float) ($trend->avg_cooperation ?? 0)));
                        $avgOutreach = max(0, min(100, (float) ($trend->avg_outreach ?? 0)));
                    @endphp
                    <div class="nd-trend-card">
                        <div class="fw-semibold text-dark mb-1">{{ $trend->month_key }}</div>
                        <div class="small text-muted mb-1">Cooperation {{ number_format($avgCoop, 1) }}%</div>
                        <div class="nd-trend-bar mb-2"><div class="nd-trend-fill-coop" style="width: {{ $avgCoop }}%"></div></div>
                        <div class="small text-muted mb-1">Outreach {{ number_format($avgOutreach, 1) }}%</div>
                        <div class="nd-trend-bar mb-2"><div class="nd-trend-fill-outreach" style="width: {{ $avgOutreach }}%"></div></div>
                        <small class="text-muted">{{ (int) $trend->entries_count }} report(s)</small>
                    </div>
                @empty
                    <div class="nd-empty">Trend cards will appear once reports are submitted.</div>
                @endforelse
            </div>

            @forelse($entries as $entry)
                @php
                    $rowNumber = ((int) ($entries->firstItem() ?? 1)) + $loop->index;
                    $entryFlagships = collect($entry->flagship_projects_supported ?? [])->map(function ($id) use ($flagshipMap) {
                        $project = $flagshipMap->get($id);
                        return $project ? "#{$project->number} {$project->name}" : null;
                    })->filter()->values();
                    $entryCommodities = collect($entry->commodity_focus ?? [])->map(function ($id) use ($commodityMap) {
                        return $commodityMap->get($id)?->name;
                    })->filter()->values();
                    $statusKey = (string) ($entry->progress_status ?? 'in_progress');
                    $statusLabel = $statusLabels[$statusKey] ?? ucfirst(str_replace('_', ' ', $statusKey));
                    $reviewKey = (string) ($entry->review_status ?? 'pending');
                    $reviewLabel = $reviewLabels[$reviewKey] ?? ucfirst(str_replace('_', ' ', $reviewKey));
                @endphp
                <article class="nd-entry">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="d-flex align-items-start gap-2">
                            <span class="nd-order">{{ $rowNumber }}</span>
                            <div>
                                <div class="fw-semibold text-dark">{{ $entry->indicator_name }}</div>
                                <div class="nd-meta">{{ optional($entry->recorded_on)->format('d M Y') }} | {{ ucfirst(str_replace('_', ' ', (string) $entry->reporting_period_type)) }} | Cooperation {{ number_format((float) $entry->cooperation_score, 1) }}%</div>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-1 justify-content-end">
                            <span class="nd-badge nd-status-{{ $statusKey }}">{{ $statusLabel }}</span>
                            <span class="nd-badge nd-review-{{ $reviewKey }}">{{ $reviewLabel }}</span>
                        </div>
                    </div>

                    <div class="row g-2 mt-1">
                        <div class="col-md-3"><small class="text-muted">Indicator Value</small><div class="fw-semibold">{{ number_format((float) $entry->indicator_value, 4) }} {{ $entry->unit }}</div></div>
                        <div class="col-md-3"><small class="text-muted">Aspiration / Goal</small><div>{{ $entry->aspiration?->number ? 'Asp ' . $entry->aspiration->number : '-' }} @if($entry->goal?->number) / Goal {{ $entry->goal->number }} @endif</div></div>
                        <div class="col-md-3"><small class="text-muted">Awareness (A/F/O)</small><div>{{ number_format((float) ($entry->agenda_awareness_score ?? 0), 1) }} / {{ number_format((float) ($entry->flagship_awareness_score ?? 0), 1) }} / {{ number_format((float) ($entry->outreach_coverage_score ?? 0), 1) }}</div></div>
                        <div class="col-md-3"><small class="text-muted">People / Households</small><div>{{ number_format((int) ($entry->people_reached ?? 0)) }} / {{ number_format((int) ($entry->households_impacted ?? 0)) }}</div></div>
                    </div>

                    @if($entryFlagships->isNotEmpty() || $entryCommodities->isNotEmpty())
                        <div class="mt-2">
                            @if($entryFlagships->isNotEmpty())
                                <div class="small text-muted mb-1">Flagship focus</div>
                                <div class="nd-chip-list mb-2">@foreach($entryFlagships as $chip)<span class="nd-chip">{{ $chip }}</span>@endforeach</div>
                            @endif
                            @if($entryCommodities->isNotEmpty())
                                <div class="small text-muted mb-1">Commodities</div>
                                <div class="nd-chip-list">@foreach($entryCommodities as $chip)<span class="nd-chip">{{ $chip }}</span>@endforeach</div>
                            @endif
                        </div>
                    @endif

                    <details class="mt-2">
                        <summary class="small fw-semibold text-primary" style="cursor:pointer;">View full narrative details</summary>
                        <div class="row g-2 mt-1">
                            <div class="col-md-6"><strong>Agenda relevance:</strong><div class="small text-muted">{{ $entry->agenda_relevance_summary ?: 'Not provided.' }}</div></div>
                            <div class="col-md-6"><strong>Policy actions:</strong><div class="small text-muted">{{ $entry->policy_actions ?: 'Not provided.' }}</div></div>
                            <div class="col-md-6"><strong>Implementation steps:</strong><div class="small text-muted">{{ $entry->institutional_steps ?: 'Not provided.' }}</div></div>
                            <div class="col-md-6"><strong>Livelihood impact:</strong><div class="small text-muted">{{ $entry->livelihood_impact_summary ?: 'Not provided.' }}</div></div>
                            <div class="col-md-6"><strong>Public engagement:</strong><div class="small text-muted">{{ $entry->public_engagement_summary ?: 'Not provided.' }}</div></div>
                            <div class="col-md-6"><strong>National projects:</strong><div class="small text-muted">{{ $entry->national_projects_programs ?: 'Not provided.' }}</div></div>
                            <div class="col-md-6"><strong>Commodity preservation:</strong><div class="small text-muted">{{ $entry->commodity_preservation_policies ?: 'Not provided.' }}</div></div>
                            <div class="col-md-6"><strong>Value addition:</strong><div class="small text-muted">{{ $entry->commodity_value_addition ?: 'Not provided.' }}</div></div>
                            <div class="col-md-6"><strong>Reviewed by:</strong><div class="small text-muted">{{ $entry->reviewer?->name ?: 'Awaiting review' }}</div></div>
                            <div class="col-md-6"><strong>Reviewed at:</strong><div class="small text-muted">{{ $entry->reviewed_at ? $entry->reviewed_at->format('d M Y H:i') : 'Awaiting review' }}</div></div>
                            <div class="col-12"><strong>Review notes:</strong><div class="small text-muted">{{ $entry->review_notes ?: 'No reviewer notes yet.' }}</div></div>
                            <div class="col-12"><strong>Risks and next steps:</strong><div class="small text-muted">{{ $entry->risk_challenges ?: 'N/A' }} | {{ $entry->next_steps_commitments ?: 'N/A' }}</div></div>
                        </div>
                    </details>

                    <div class="d-flex justify-content-end mt-2">
                        @if($reviewKey === 'approved')
                            <span class="small text-success fw-semibold">Approved records are locked for reporting integrity.</span>
                        @else
                            <form action="{{ route('member-state.national-data.destroy', $entry) }}" method="POST" onsubmit="return confirm('Delete this national report entry?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="nd-empty">No national reports found for the selected filters.</div>
            @endforelse
        </div>

        @if($entries->hasPages())
            <div class="card-footer bg-white">{{ $entries->links() }}</div>
        @endif
    </div>
</main>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const periodType = document.getElementById('reportingPeriodType');
    const dayWrap = document.getElementById('reportingDayWrap');
    const aspirationSelect = document.getElementById('aspirationSelect');
    const goalSelect = document.getElementById('goalSelect');
    const coopRange = document.getElementById('cooperationScoreRange');
    const resetBtn = document.getElementById('nationalDataResetBtn');
    const form = document.getElementById('nationalDataForm');
    const preview = document.getElementById('nationalDataLivePreview');

    const syncPeriodDayVisibility = () => {
        if (!periodType || !dayWrap) return;
        dayWrap.style.display = (periodType.value === 'daily') ? '' : 'none';
    };

    const syncGoalOptions = () => {
        if (!aspirationSelect || !goalSelect) return;
        const aspirationId = aspirationSelect.value;
        Array.from(goalSelect.querySelectorAll('option')).forEach((option, index) => {
            if (index === 0) { option.hidden = false; return; }
            const owner = option.getAttribute('data-aspiration');
            const visible = !aspirationId || owner === aspirationId;
            option.hidden = !visible;
            if (!visible && option.selected) goalSelect.value = '';
        });
    };

    const syncRangeOutput = (rangeEl, outputSelector) => {
        if (!rangeEl || !outputSelector) return;
        const output = document.querySelector(outputSelector);
        if (!output) return;
        output.textContent = Number(rangeEl.value).toFixed(1) + '%';
    };

    const bindChoiceSearch = (inputId, boxId) => {
        const input = document.getElementById(inputId);
        const box = document.getElementById(boxId);
        if (!input || !box) return;
        input.addEventListener('input', function () {
            const term = input.value.trim().toLowerCase();
            box.querySelectorAll('[data-choice-item]').forEach((item) => {
                const text = item.getAttribute('data-choice-text') || '';
                item.style.display = text.includes(term) ? '' : 'none';
            });
        });
    };

    const buildLivePreview = () => {
        if (!form || !preview) return;
        const indicator = form.querySelector('[name="indicator_name"]')?.value || '';
        const policy = form.querySelector('[name="policy_actions"]')?.value || '';
        const outreach = form.querySelector('[name="public_engagement_summary"]')?.value || '';
        const projects = form.querySelector('[name="national_projects_programs"]')?.value || '';
        const commodity = form.querySelector('[name="commodity_value_addition"]')?.value || '';
        const snippets = [indicator, policy, outreach, projects, commodity].map((v) => v.trim()).filter((v) => v.length > 0).map((v) => v.length > 120 ? v.slice(0, 120) + '...' : v);
        preview.textContent = snippets.length ? snippets.join(' | ') : 'Start typing to generate a live summary of your national report.';
    };

    const filterForm = document.getElementById('nationalDataFilterForm');
    if (filterForm) filterForm.querySelectorAll('[data-auto-filter]').forEach((field) => field.addEventListener('change', () => filterForm.submit()));
    if (periodType) periodType.addEventListener('change', syncPeriodDayVisibility);
    if (aspirationSelect) aspirationSelect.addEventListener('change', syncGoalOptions);
    if (coopRange) { coopRange.addEventListener('input', () => syncRangeOutput(coopRange, '#cooperationScoreValue')); syncRangeOutput(coopRange, '#cooperationScoreValue'); }
    document.querySelectorAll('.score-range').forEach((rangeEl) => { const output = rangeEl.getAttribute('data-output'); rangeEl.addEventListener('input', () => syncRangeOutput(rangeEl, output)); syncRangeOutput(rangeEl, output); });
    if (form) { form.addEventListener('input', buildLivePreview); buildLivePreview(); }
    if (resetBtn) resetBtn.addEventListener('click', () => window.setTimeout(() => { syncPeriodDayVisibility(); syncGoalOptions(); if (coopRange) syncRangeOutput(coopRange, '#cooperationScoreValue'); document.querySelectorAll('.score-range').forEach((rangeEl) => syncRangeOutput(rangeEl, rangeEl.getAttribute('data-output'))); buildLivePreview(); }, 0));

    bindChoiceSearch('flagshipSearchInput', 'flagshipChoiceBox');
    bindChoiceSearch('commoditySearchInput', 'commodityChoiceBox');
    syncPeriodDayVisibility();
    syncGoalOptions();
});
</script>
@endpush
