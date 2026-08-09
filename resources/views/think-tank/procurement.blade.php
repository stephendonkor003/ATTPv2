@php
    $currency = 'USD';
    $isAdminView = auth()->user()?->isSuperAdmin() || auth()->user()?->isAdmin();
    $canManageProcurement = auth()->user()?->can('think_tank.procurement.manage');
    $canDownloadProcurement = auth()->user()?->can('think_tank.procurement.download');
    $planAction = route('think-tank.procurement.plans.store', $portalRouteParams);
    $opportunityAction = route('think-tank.procurement.store', $portalRouteParams);
    $resetParams = $isAdminView ? ['think_tank_member_id' => $member->id] : [];
@endphp


<x-think-tank.partials.shell :member="$member" title="Procurement">
    <div class="tt-proc-shell">
        <section class="tt-proc-search">
            <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
                <div>
                    <h2 class="tt-search-title">Procurement Search</h2>
                    <p class="tt-search-subtitle">Search procurement plans, opportunities, applications, budgets, and review status for the selected think tank.</p>
                </div>
                <div class="tt-search-actions">
                    @if($canDownloadProcurement)
                        <a class="btn btn-dark" href="{{ route('think-tank.procurement.download', $procurementQueryParams) }}">
                            <i class="feather-download me-1"></i> Download Report
                        </a>
                    @endif
                    @if($canManageProcurement)
                        <a class="btn btn-primary" href="#procurement-workspace">
                            <i class="feather-plus me-1"></i> New Procurement
                        </a>
                    @endif
                </div>
            </div>

            <form method="GET" action="{{ route('think-tank.procurement') }}">
                <div class="tt-search-grid">
                    @if($isAdminView)
                        <div class="tt-field">
                            <label for="think_tank_member_id">Think tank</label>
                            <select id="think_tank_member_id" name="think_tank_member_id">
                                @foreach($membersForSearch as $searchMember)
                                    <option value="{{ $searchMember->id }}" @selected($searchMember->id === $member->id)>
                                        {{ $searchMember->name }} @if($searchMember->consortium) - {{ $searchMember->consortium->name }} @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="tt-field">
                        <label for="q">Keyword</label>
                        <input id="q" name="q" value="{{ $keyword }}" placeholder="Plan, reference, title">
                    </div>
                    <div class="tt-field">
                        <label for="status">Opportunity status</label>
                        <select id="status" name="status">
                            <option value="">All statuses</option>
                            @foreach(['draft', 'published', 'closed', 'awarded'] as $status)
                                <option value="{{ $status }}" @selected($statusFilter === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="tt-field">
                        <label for="plan_status">Plan status</label>
                        <select id="plan_status" name="plan_status">
                            <option value="">All plans</option>
                            @foreach(['submitted', 'approved', 'rejected'] as $status)
                                <option value="{{ $status }}" @selected($planStatusFilter === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="tt-field">
                        <label for="fiscal_year">Fiscal year</label>
                        <select id="fiscal_year" name="fiscal_year">
                            <option value="">All years</option>
                            @foreach($fiscalYears as $year)
                                <option value="{{ $year }}" @selected($fiscalYearFilter === (string) $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="tt-field">
                        <label for="date_from">From</label>
                        <input id="date_from" type="date" name="date_from" value="{{ $dashboardFilter['date_from'] }}">
                    </div>
                    <div class="tt-field">
                        <label for="date_to">To</label>
                        <input id="date_to" type="date" name="date_to" value="{{ $dashboardFilter['date_to'] }}">
                    </div>
                    <div class="tt-search-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="feather-search me-1"></i> Run Search
                        </button>
                        <a class="btn btn-light border" href="{{ route('think-tank.procurement', $resetParams) }}">Reset</a>
                    </div>
                </div>
            </form>
        </section>

        <section class="tt-proc-hero">
            <div class="tt-hero-grid">
                <div>
                    <span class="tt-kicker"><i class="feather-briefcase"></i> Procurement Analysis</span>
                    <h1>{{ $member->name }} procurement pipeline</h1>
                    <p>
                        This page brings procurement plans, published opportunities, vendor applications, reviews, and selection status into one reportable workspace.
                    </p>
                    <div class="tt-hero-meta mt-3">
                        {{ $member->consortium?->name ?? 'No consortium assigned' }} | {{ $member->country ?? 'Country not set' }} | All figures are {{ $currency }}
                    </div>
                </div>
                <div class="tt-hero-facts">
                    <div class="tt-hero-fact">
                        <span>Pipeline budget</span>
                        <strong>{{ $currency }} {{ number_format($procurementStats['opportunity_budget'], 2) }}</strong>
                    </div>
                    <div class="tt-hero-fact">
                        <span>Open opportunities</span>
                        <strong>{{ number_format($procurementStats['open']) }}</strong>
                    </div>
                    <div class="tt-hero-fact">
                        <span>Applications reviewed</span>
                        <strong>{{ number_format($procurementStats['reviewed']) }}</strong>
                    </div>
                    <div class="tt-hero-fact">
                        <span>Closing soon</span>
                        <strong>{{ number_format($procurementStats['closing_soon']) }}</strong>
                    </div>
                </div>
            </div>
        </section>

        <section class="tt-kpi-grid">
            <div class="tt-kpi-card">
                <div class="tt-kpi-icon"><i class="feather-map"></i></div>
                <div class="tt-kpi-label">Procurement plans</div>
                <div class="tt-kpi-value">{{ number_format($procurementStats['plans']) }}</div>
                <div class="tt-kpi-note">{{ $currency }} {{ number_format($procurementStats['plan_budget'], 2) }} planned</div>
            </div>
            <div class="tt-kpi-card green">
                <div class="tt-kpi-icon"><i class="feather-briefcase"></i></div>
                <div class="tt-kpi-label">Opportunities</div>
                <div class="tt-kpi-value">{{ number_format($procurementStats['opportunities']) }}</div>
                <div class="tt-kpi-note">{{ number_format($procurementStats['published']) }} published</div>
            </div>
            <div class="tt-kpi-card amber">
                <div class="tt-kpi-icon"><i class="feather-users"></i></div>
                <div class="tt-kpi-label">Applications received</div>
                <div class="tt-kpi-value">{{ number_format($procurementStats['applications']) }}</div>
                <div class="tt-kpi-note">{{ $procurementStats['average_applications'] }} average per opportunity</div>
            </div>
            <div class="tt-kpi-card teal">
                <div class="tt-kpi-icon"><i class="feather-award"></i></div>
                <div class="tt-kpi-label">Selections</div>
                <div class="tt-kpi-value">{{ number_format($procurementStats['selected']) }}</div>
                <div class="tt-kpi-note">{{ number_format($procurementStats['awarded']) }} awarded opportunities</div>
            </div>
        </section>

        <section class="tt-main-grid">
            <div>
                <section class="tt-chart-grid">
                    <div class="tt-chart-box">
                        <div class="tt-panel-head">
                            <div>
                                <h2>Opportunity Status</h2>
                                <p>Current procurement pipeline by status.</p>
                            </div>
                        </div>
                        <div id="ttProcStatusChart"></div>
                    </div>
                    <div class="tt-chart-box">
                        <div class="tt-panel-head">
                            <div>
                                <h2>Pipeline Movement</h2>
                                <p>Plans, opportunities, and applications across recent months.</p>
                            </div>
                        </div>
                        <div id="ttProcPipelineChart"></div>
                    </div>
                    <div class="tt-chart-box">
                        <div class="tt-panel-head">
                            <div>
                                <h2>Budget Published</h2>
                                <p>Opportunity budgets created in the reporting window.</p>
                            </div>
                        </div>
                        <div id="ttProcBudgetChart"></div>
                    </div>
                    <div class="tt-chart-box">
                        <div class="tt-panel-head">
                            <div>
                                <h2>Application Review</h2>
                                <p>Applications received, reviewed, and selected.</p>
                            </div>
                        </div>
                        <div id="ttProcReviewChart"></div>
                    </div>
                </section>

                <section class="tt-proc-tabs" id="procurement-workspace">
                    <ul class="nav nav-tabs" id="ttProcurementTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $canManageProcurement ? '' : 'active' }}" id="plans-list-tab" data-bs-toggle="tab" data-bs-target="#plans-list-pane" type="button" role="tab" aria-controls="plans-list-pane" aria-selected="{{ $canManageProcurement ? 'false' : 'true' }}">
                                <i class="feather-list me-1"></i> Plan Register
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="opportunities-list-tab" data-bs-toggle="tab" data-bs-target="#opportunities-list-pane" type="button" role="tab" aria-controls="opportunities-list-pane" aria-selected="false">
                                <i class="feather-briefcase me-1"></i> Opportunity Register
                            </button>
                        </li>
                        @if($canManageProcurement)
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="plan-tab" data-bs-toggle="tab" data-bs-target="#plan-pane" type="button" role="tab" aria-controls="plan-pane" aria-selected="true">
                                    <i class="feather-map me-1"></i> New Plan
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="opportunity-tab" data-bs-toggle="tab" data-bs-target="#opportunity-pane" type="button" role="tab" aria-controls="opportunity-pane" aria-selected="false">
                                    <i class="feather-upload-cloud me-1"></i> Publish Opportunity
                                </button>
                            </li>
                        @endif
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="procurement-controls-tab" data-bs-toggle="tab" data-bs-target="#procurement-controls-pane" type="button" role="tab" aria-controls="procurement-controls-pane" aria-selected="false">
                                <i class="feather-shield me-1"></i> Quality Controls
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content tt-tab-body">
                        <div class="tab-pane fade {{ $canManageProcurement ? '' : 'show active' }}" id="plans-list-pane" role="tabpanel" aria-labelledby="plans-list-tab" tabindex="0">
                            <div class="tt-table-wrap">
                                <table class="tt-table">
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
                                        @forelse($plans as $plan)
                                            <tr>
                                                <td>
                                                    <div class="tt-name">{{ $plan->title }}</div>
                                                    <div class="tt-muted">{{ $plan->plan_code ?? 'No plan code' }}</div>
                                                </td>
                                                <td>{{ $plan->fiscal_year ?? 'N/A' }}</td>
                                                <td>{{ $currency }} {{ number_format((float) $plan->estimated_budget, 2) }}</td>
                                                <td>{{ $plan->planned_publish_date?->format('d M Y') ?? 'N/A' }}</td>
                                                <td>{{ number_format($plan->procurements_count) }}</td>
                                                <td><span class="tt-status {{ $plan->status }}">{{ str_replace('_', ' ', $plan->status) }}</span></td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6">
                                                    <div class="tt-empty">No procurement plan matches the selected search.</div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="opportunities-list-pane" role="tabpanel" aria-labelledby="opportunities-list-tab" tabindex="0">
                            <div class="tt-table-wrap">
                                <table class="tt-table">
                                    <thead>
                                        <tr>
                                            <th>Opportunity</th>
                                            <th>Plan</th>
                                            <th>Budget</th>
                                            <th>Status</th>
                                            <th>Applications</th>
                                            <th>Closing</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($procurements as $procurement)
                                            <tr>
                                                <td>
                                                    <div class="tt-name">{{ $procurement->title }}</div>
                                                    <div class="tt-muted">{{ $procurement->reference_no ?? 'No reference' }}</div>
                                                </td>
                                                <td>{{ $procurement->thinkTankProcurementPlan?->title ?? 'Unlinked' }}</td>
                                                <td>{{ $currency }} {{ number_format((float) $procurement->estimated_budget, 2) }}</td>
                                                <td><span class="tt-status {{ $procurement->status }}">{{ str_replace('_', ' ', $procurement->status) }}</span></td>
                                                <td>{{ number_format($procurement->submissions_count) }}</td>
                                                <td>{{ $procurement->application_end_date?->format('d M Y') ?? 'N/A' }}</td>
                                                <td class="text-end">
                                                    <div class="d-flex flex-wrap justify-content-end gap-2">
                                                        @if($procurement->status === 'published')
                                                            <a class="btn btn-sm btn-light border" href="{{ route('public.procurement.show', $procurement) }}" target="_blank" rel="noopener">Public</a>
                                                        @endif
                                                        @canany(['think_tank.procurement.evaluate', 'think_tank.procurement.select'])
                                                            <a class="btn btn-sm btn-primary" href="{{ route('think-tank.procurement.submissions', array_merge($portalRouteParams, ['procurement' => $procurement])) }}">Applications</a>
                                                        @endcanany
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7">
                                                    <div class="tt-empty">No procurement opportunity matches the selected search.</div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3">{{ $procurements->links() }}</div>
                        </div>

                        @if($canManageProcurement)
                            <div class="tab-pane fade show active" id="plan-pane" role="tabpanel" aria-labelledby="plan-tab" tabindex="0">
                                <form method="POST" action="{{ $planAction }}">
                                    @csrf
                                    <div class="tt-workflow-grid">
                                        <div class="tt-form-card">
                                            <h3>Procurement plan details</h3>
                                            <p>Create the plan before publishing opportunities under it.</p>
                                            <div class="tt-form-grid mt-3">
                                                <div class="tt-field full">
                                                    <label for="plan_title">Plan title</label>
                                                    <input id="plan_title" name="title" value="{{ old('title') }}" placeholder="2026 research services procurement plan" required>
                                                </div>
                                                <div class="tt-field">
                                                    <label for="plan_fiscal_year">Fiscal year</label>
                                                    <input id="plan_fiscal_year" name="fiscal_year" value="{{ old('fiscal_year', now()->format('Y')) }}" placeholder="2026">
                                                </div>
                                                <div class="tt-field">
                                                    <label for="plan_budget">Estimated budget (USD)</label>
                                                    <input id="plan_budget" type="number" min="0" step="0.01" name="estimated_budget" value="{{ old('estimated_budget') }}" placeholder="0.00" required>
                                                </div>
                                                <div class="tt-field">
                                                    <label for="plan_currency">Currency</label>
                                                    <input id="plan_currency" name="currency" value="USD" readonly>
                                                </div>
                                                <div class="tt-field">
                                                    <label for="planned_publish_date">Planned publish date</label>
                                                    <input id="planned_publish_date" type="date" name="planned_publish_date" value="{{ old('planned_publish_date') }}">
                                                </div>
                                                <div class="tt-field full">
                                                    <label for="plan_description">Plan description</label>
                                                    <textarea id="plan_description" name="description" placeholder="Describe the procurement package, expected outputs, market approach, and oversight notes.">{{ old('description') }}</textarea>
                                                </div>
                                            </div>
                                            <div class="d-flex flex-wrap justify-content-end gap-2 mt-3">
                                                <button type="reset" class="btn btn-light border">Clear form</button>
                                                <button type="submit" class="btn btn-primary"><i class="feather-send me-1"></i> Submit Plan</button>
                                            </div>
                                        </div>
                                        <aside class="tt-side-note">
                                            <h3>Plan evidence</h3>
                                            <ul class="tt-check-list">
                                                <li><i class="feather-check-circle me-1"></i> Use a title linked to the funded work or output package.</li>
                                                <li><i class="feather-check-circle me-1"></i> Enter the estimated budget in USD.</li>
                                                <li><i class="feather-check-circle me-1"></i> Add enough context for Secretariat oversight.</li>
                                            </ul>
                                        </aside>
                                    </div>
                                </form>
                            </div>

                            <div class="tab-pane fade" id="opportunity-pane" role="tabpanel" aria-labelledby="opportunity-tab" tabindex="0">
                                <form method="POST" action="{{ $opportunityAction }}">
                                    @csrf
                                    <div class="tt-workflow-grid">
                                        <div class="tt-form-card">
                                            <h3>Opportunity details</h3>
                                            <p>Published opportunities appear on the public procurement page and receive vendor applications.</p>
                                            <div class="tt-form-grid mt-3">
                                                <div class="tt-field full">
                                                    <label for="think_tank_procurement_plan_id">Linked plan</label>
                                                    <select id="think_tank_procurement_plan_id" name="think_tank_procurement_plan_id">
                                                        <option value="">No linked plan</option>
                                                        @foreach($planOptions as $plan)
                                                            <option value="{{ $plan->id }}" @selected(old('think_tank_procurement_plan_id') === $plan->id)>
                                                                {{ $plan->title }} | {{ $currency }} {{ number_format((float) $plan->estimated_budget, 2) }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="tt-field full">
                                                    <label for="opp_title">Opportunity title</label>
                                                    <input id="opp_title" name="title" value="{{ old('title') }}" placeholder="Consultancy for policy research and stakeholder validation" required>
                                                </div>
                                                <div class="tt-field">
                                                    <label for="reference_no">Reference number</label>
                                                    <input id="reference_no" name="reference_no" value="{{ old('reference_no') }}" placeholder="TT-PROC-2026-001">
                                                </div>
                                                <div class="tt-field">
                                                    <label for="opp_fiscal_year">Fiscal year</label>
                                                    <input id="opp_fiscal_year" name="fiscal_year" value="{{ old('fiscal_year', now()->format('Y')) }}" placeholder="2026">
                                                </div>
                                                <div class="tt-field">
                                                    <label for="estimated_budget">Estimated budget (USD)</label>
                                                    <input id="estimated_budget" type="number" min="0" step="0.01" name="estimated_budget" value="{{ old('estimated_budget') }}" placeholder="0.00" required>
                                                </div>
                                                <div class="tt-field">
                                                    <label for="status">Publishing status</label>
                                                    <select id="status" name="status" required>
                                                        <option value="published" @selected(old('status', 'published') === 'published')>Publish now</option>
                                                        <option value="draft" @selected(old('status') === 'draft')>Save as draft</option>
                                                    </select>
                                                </div>
                                                <div class="tt-field">
                                                    <label for="application_start_date">Application start date</label>
                                                    <input id="application_start_date" type="date" name="application_start_date" value="{{ old('application_start_date', now()->toDateString()) }}">
                                                </div>
                                                <div class="tt-field">
                                                    <label for="application_end_date">Application end date</label>
                                                    <input id="application_end_date" type="date" name="application_end_date" value="{{ old('application_end_date') }}" required>
                                                </div>
                                                <div class="tt-field full">
                                                    <label for="opp_description">Opportunity description</label>
                                                    <textarea id="opp_description" name="description" placeholder="Describe the scope, eligibility, deliverables, evaluation basis, and application instructions." required>{{ old('description') }}</textarea>
                                                </div>
                                            </div>
                                            <div class="d-flex flex-wrap justify-content-end gap-2 mt-3">
                                                <button type="reset" class="btn btn-light border">Clear form</button>
                                                <button type="submit" class="btn btn-primary"><i class="feather-save me-1"></i> Save Opportunity</button>
                                            </div>
                                        </div>
                                        <aside class="tt-side-note">
                                            <h3>Publication evidence</h3>
                                            <ul class="tt-check-list">
                                                <li><i class="feather-check-circle me-1"></i> Confirm the application window before publishing.</li>
                                                <li><i class="feather-check-circle me-1"></i> State deliverables and evidence required from vendors.</li>
                                                <li><i class="feather-check-circle me-1"></i> Review applications from the opportunity register.</li>
                                            </ul>
                                        </aside>
                                    </div>
                                </form>
                            </div>
                        @endif

                        <div class="tab-pane fade" id="procurement-controls-pane" role="tabpanel" aria-labelledby="procurement-controls-tab" tabindex="0">
                            <div class="tt-chart-grid">
                                <div class="tt-form-card">
                                    <h3>Budget discipline</h3>
                                    <p>Each plan and opportunity is stored in USD for consistent ATTP reporting.</p>
                                </div>
                                <div class="tt-form-card">
                                    <h3>Public visibility</h3>
                                    <p>Published opportunities are available to vendors through the public procurement page.</p>
                                </div>
                                <div class="tt-form-card">
                                    <h3>Application review</h3>
                                    <p>Evaluation records capture technical score, financial score, total score, recommendation, and comments.</p>
                                </div>
                                <div class="tt-form-card">
                                    <h3>Selection record</h3>
                                    <p>Selected applications are linked to the opportunity for audit and procurement follow-through.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <aside>
                <div class="tt-proc-panel">
                    <div class="tt-panel-head">
                        <div>
                            <h2>Plan Status</h2>
                            <p>Submitted procurement plans by status.</p>
                        </div>
                    </div>
                    <div class="tt-status-list">
                        @forelse($planStatusCounts as $status => $count)
                            <div class="tt-status-row">
                                <span>{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                                <strong>{{ number_format($count) }}</strong>
                            </div>
                        @empty
                            <div class="tt-empty">No plan status data yet.</div>
                        @endforelse
                    </div>
                </div>

                <div class="tt-proc-panel">
                    <div class="tt-panel-head">
                        <div>
                            <h2>Application Status</h2>
                            <p>Vendor application review spread.</p>
                        </div>
                    </div>
                    <div class="tt-status-list">
                        @forelse($submissionStatusCounts as $status => $count)
                            <div class="tt-status-row">
                                <span>{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                                <strong>{{ number_format($count) }}</strong>
                            </div>
                        @empty
                            <div class="tt-empty">No application status data yet.</div>
                        @endforelse
                    </div>
                </div>

                <div class="tt-proc-panel">
                    <div class="tt-panel-head">
                        <div>
                            <h2>Pipeline Summary</h2>
                            <p>Key operational counts for this view.</p>
                        </div>
                    </div>
                    <div class="tt-status-list">
                        <div class="tt-status-row"><span>Open</span><strong>{{ number_format($procurementStats['open']) }}</strong></div>
                        <div class="tt-status-row"><span>Closing soon</span><strong>{{ number_format($procurementStats['closing_soon']) }}</strong></div>
                        <div class="tt-status-row"><span>Draft</span><strong>{{ number_format($procurementStats['draft']) }}</strong></div>
                        <div class="tt-status-row"><span>Closed</span><strong>{{ number_format($procurementStats['closed']) }}</strong></div>
                    </div>
                </div>

                <div class="tt-proc-panel">
                    <div class="tt-panel-head">
                        <div>
                            <h2>Quick Links</h2>
                            <p>Move between procurement surfaces.</p>
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        <a class="btn btn-primary" href="#procurement-workspace"><i class="feather-list me-1"></i> Open registers</a>
                        <a class="btn btn-light border" href="{{ route('think-tank.dashboard', $portalRouteParams) }}"><i class="feather-activity me-1"></i> Dashboard overview</a>
                        @if($canDownloadProcurement)
                            <a class="btn btn-dark" href="{{ route('think-tank.procurement.download', $procurementQueryParams) }}"><i class="feather-download me-1"></i> Download procurement PDF</a>
                        @endif
                    </div>
                </div>
            </aside>
        </section>
    </div>
</x-think-tank.partials.shell>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof ApexCharts === 'undefined') {
                return;
            }

            const chartData = @json($chartData);
            const baseOptions = {
                chart: { toolbar: { show: false }, fontFamily: 'Inter, Arial, sans-serif' },
                dataLabels: { enabled: false },
                colors: ['#1d4ed8', '#0f766e', '#f59e0b', '#ef4444', '#64748b'],
                grid: { borderColor: '#e2e8f0' },
                legend: { position: 'bottom' }
            };

            new ApexCharts(document.querySelector('#ttProcStatusChart'), {
                ...baseOptions,
                chart: { ...baseOptions.chart, type: 'donut', height: 250 },
                series: chartData.opportunityStatus.values,
                labels: chartData.opportunityStatus.labels
            }).render();

            new ApexCharts(document.querySelector('#ttProcPipelineChart'), {
                ...baseOptions,
                chart: { ...baseOptions.chart, type: 'area', height: 250 },
                stroke: { curve: 'smooth', width: 3 },
                fill: { opacity: .15 },
                series: [
                    { name: 'Plans', data: chartData.pipeline.plans },
                    { name: 'Opportunities', data: chartData.pipeline.opportunities },
                    { name: 'Applications', data: chartData.pipeline.applications }
                ],
                xaxis: { categories: chartData.pipeline.labels }
            }).render();

            new ApexCharts(document.querySelector('#ttProcBudgetChart'), {
                ...baseOptions,
                chart: { ...baseOptions.chart, type: 'bar', height: 250 },
                series: [{ name: 'USD budget', data: chartData.budget.values }],
                xaxis: { categories: chartData.budget.labels },
                yaxis: { labels: { formatter: value => '$' + Number(value || 0).toLocaleString() } },
                plotOptions: { bar: { borderRadius: 5, columnWidth: '46%' } }
            }).render();

            new ApexCharts(document.querySelector('#ttProcReviewChart'), {
                ...baseOptions,
                chart: { ...baseOptions.chart, type: 'bar', height: 250 },
                series: [{ name: 'Records', data: chartData.review.values }],
                xaxis: { categories: chartData.review.labels },
                plotOptions: { bar: { horizontal: true, borderRadius: 5 } }
            }).render();
        });
    </script>
@endpush
