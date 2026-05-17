@php
    $currency = 'USD';
    $portalRouteParams = (auth()->user()?->isSuperAdmin() || auth()->user()?->isAdmin())
        ? ['think_tank_member_id' => $member->id]
        : [];
    $planAction = route('think-tank.procurement.plans.store', $portalRouteParams);
    $opportunityAction = route('think-tank.procurement.store', $portalRouteParams);
@endphp

@push('styles')
    <style>
        .tt-proc-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(290px, .65fr);
            gap: 18px;
            margin-bottom: 18px;
        }

        .tt-proc-banner,
        .tt-proc-side,
        .tt-proc-stat,
        .tt-proc-tabs,
        .tt-proc-card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .06);
        }

        .tt-proc-banner {
            min-height: 220px;
            padding: 26px;
            color: #fff;
            background:
                linear-gradient(120deg, rgba(15, 23, 42, .96), rgba(124, 58, 237, .78)),
                url("{{ asset('admin/assets/images/gallery/4.png') }}");
            background-size: cover;
            background-position: center;
        }

        .tt-proc-banner h1 {
            max-width: 820px;
            margin: 10px 0;
            color: #fff;
            font-size: 30px;
            line-height: 1.18;
            letter-spacing: 0;
        }

        .tt-proc-banner p {
            max-width: 850px;
            margin: 0;
            color: rgba(255, 255, 255, .86);
        }

        .tt-proc-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(255, 255, 255, .28);
            background: rgba(255, 255, 255, .13);
            border-radius: 999px;
            padding: 7px 11px;
            font-weight: 850;
            font-size: 12px;
            text-transform: uppercase;
        }

        .tt-proc-side {
            padding: 22px;
            display: grid;
            gap: 14px;
            align-content: center;
            background: linear-gradient(180deg, #f8fafc, #fff);
        }

        .tt-proc-side-list {
            display: grid;
            gap: 9px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .tt-proc-side-list li {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 12px;
            background: #fff;
            color: #334155;
            font-weight: 800;
        }

        .tt-proc-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .tt-proc-stat {
            padding: 18px;
            min-height: 108px;
        }

        .tt-proc-stat .label {
            color: #64748b;
            font-size: 13px;
            font-weight: 850;
        }

        .tt-proc-stat .value {
            color: #0f172a;
            font-size: 25px;
            font-weight: 900;
            margin-top: 8px;
        }

        .tt-proc-tabs {
            overflow: hidden;
        }

        .tt-proc-tabs .nav {
            gap: 8px;
            padding: 14px 16px 0;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .tt-proc-tabs .nav-link {
            border: 1px solid transparent;
            border-radius: 8px 8px 0 0;
            color: #475569;
            font-weight: 850;
            padding: 10px 14px;
        }

        .tt-proc-tabs .nav-link.active {
            color: #0f172a;
            background: #fff;
            border-color: #e2e8f0 #e2e8f0 #fff;
            box-shadow: 0 -4px 10px rgba(15, 23, 42, .04);
        }

        .tt-proc-tab-body {
            padding: 20px;
        }

        .tt-proc-form-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(280px, .55fr);
            gap: 20px;
            align-items: start;
        }

        .tt-proc-card {
            padding: 18px;
            margin-bottom: 16px;
        }

        .tt-proc-card h2 {
            color: #0f172a;
            font-size: 17px;
            font-weight: 900;
            margin: 0 0 4px;
        }

        .tt-proc-card .hint {
            color: #64748b;
            font-size: 13px;
            margin: 0 0 16px;
        }

        .tt-field-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .tt-field {
            display: grid;
            gap: 7px;
        }

        .tt-field.full {
            grid-column: 1 / -1;
        }

        .tt-field label {
            color: #334155;
            font-size: 13px;
            font-weight: 850;
        }

        .tt-field small {
            color: #64748b;
        }

        .tt-field input,
        .tt-field select,
        .tt-field textarea {
            width: 100%;
            border: 1px solid #d8dee8;
            border-radius: 7px;
            padding: 11px 12px;
            background: #fff;
            color: #0f172a;
        }

        .tt-field textarea {
            min-height: 130px;
            resize: vertical;
        }

        .tt-proc-note {
            border: 1px solid #ede9fe;
            border-radius: 9px;
            padding: 16px;
            background: #f5f3ff;
            color: #4c1d95;
        }

        .tt-proc-note h3 {
            color: #3b0764;
            font-size: 15px;
            font-weight: 900;
            margin: 0 0 10px;
        }

        .tt-proc-note ul {
            display: grid;
            gap: 10px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .tt-proc-note li {
            display: flex;
            gap: 9px;
            line-height: 1.45;
        }

        .tt-proc-note i {
            margin-top: 3px;
            color: #7c3aed;
        }

        .tt-proc-table-wrap {
            overflow-x: auto;
        }

        .tt-proc-table th {
            background: #f8fafc;
            color: #475569;
        }

        .tt-name {
            color: #0f172a;
            font-weight: 850;
        }

        .tt-muted {
            color: #64748b;
            font-size: 13px;
        }

        .tt-status {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 5px 9px;
            font-size: 12px;
            font-weight: 850;
            background: #e0f2fe;
            color: #075985;
            text-transform: capitalize;
            white-space: nowrap;
        }

        .tt-status.published,
        .tt-status.active,
        .tt-status.awarded {
            background: #dcfce7;
            color: #166534;
        }

        .tt-status.draft,
        .tt-status.submitted {
            background: #fef3c7;
            color: #92400e;
        }

        .tt-empty {
            border: 1px dashed #cbd5e1;
            border-radius: 9px;
            padding: 26px;
            text-align: center;
            color: #64748b;
            background: #f8fafc;
        }

        .tt-guide-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        @media (max-width: 1100px) {
            .tt-proc-hero,
            .tt-proc-form-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 900px) {
            .tt-proc-stats,
            .tt-field-grid,
            .tt-guide-grid {
                grid-template-columns: 1fr;
            }

            .tt-proc-banner h1 {
                font-size: 23px;
            }
        }
    </style>
@endpush

<x-think-tank.partials.shell :member="$member" title="Procurement Plans">
    <section class="tt-proc-hero">
        <div class="tt-proc-banner">
            <span class="tt-proc-kicker"><i class="feather-briefcase"></i> Think Tank Procurement</span>
            <h1>Plan procurement, publish opportunities, receive applications, and manage selections.</h1>
            <p>
                Use this workspace to keep the ATTP Secretariat fully informed on procurement plans,
                published opportunities, vendor applications, evaluations, and final selections.
            </p>
        </div>
        <aside class="tt-proc-side">
            <div>
                <div class="tt-muted fw-bold mb-2">Pipeline status</div>
                <ul class="tt-proc-side-list">
                    <li><span>Published</span><strong>{{ number_format($procurementStats['published']) }}</strong></li>
                    <li><span>Draft</span><strong>{{ number_format($procurementStats['draft']) }}</strong></li>
                    <li><span>Awarded</span><strong>{{ number_format($procurementStats['awarded']) }}</strong></li>
                </ul>
            </div>
            <a class="btn btn-primary" href="#procurement-workspace">
                <i class="feather-plus me-1"></i> Start procurement
            </a>
        </aside>
    </section>

    <section class="tt-proc-stats">
        <div class="tt-proc-stat">
            <div class="label">Procurement plans</div>
            <div class="value">{{ number_format($procurementStats['plans']) }}</div>
        </div>
        <div class="tt-proc-stat">
            <div class="label">Planned budget</div>
            <div class="value">{{ $currency }} {{ number_format($procurementStats['plan_budget'], 2) }}</div>
        </div>
        <div class="tt-proc-stat">
            <div class="label">Opportunities</div>
            <div class="value">{{ number_format($procurementStats['opportunities']) }}</div>
        </div>
        <div class="tt-proc-stat">
            <div class="label">Applications received</div>
            <div class="value">{{ number_format($procurementStats['applications']) }}</div>
        </div>
    </section>

    <section class="tt-proc-tabs" id="procurement-workspace">
        <ul class="nav nav-tabs" id="ttProcurementTabs" role="tablist">
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
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="plans-list-tab" data-bs-toggle="tab" data-bs-target="#plans-list-pane" type="button" role="tab" aria-controls="plans-list-pane" aria-selected="false">
                    <i class="feather-list me-1"></i> Plans
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="opportunities-list-tab" data-bs-toggle="tab" data-bs-target="#opportunities-list-pane" type="button" role="tab" aria-controls="opportunities-list-pane" aria-selected="false">
                    <i class="feather-briefcase me-1"></i> Opportunities
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="procurement-guide-tab" data-bs-toggle="tab" data-bs-target="#procurement-guide-pane" type="button" role="tab" aria-controls="procurement-guide-pane" aria-selected="false">
                    <i class="feather-help-circle me-1"></i> Guide
                </button>
            </li>
        </ul>

        <div class="tab-content tt-proc-tab-body">
            <div class="tab-pane fade show active" id="plan-pane" role="tabpanel" aria-labelledby="plan-tab" tabindex="0">
                <form method="POST" action="{{ $planAction }}">
                    @csrf
                    <div class="tt-proc-form-grid">
                        <div>
                            <div class="tt-proc-card">
                                <h2>Procurement plan details</h2>
                                <p class="hint">Create the plan before publishing opportunities under it.</p>
                                <div class="tt-field-grid">
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
                                        <small>All think tank procurement budgets must be entered in USD.</small>
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
                                        <textarea id="plan_description" name="description" placeholder="Describe the procurement package, expected outputs, market approach, and Secretariat oversight notes.">{{ old('description') }}</textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap justify-content-end gap-2">
                                <button type="reset" class="btn btn-light border">Clear form</button>
                                <button type="submit" class="btn btn-primary"><i class="feather-send me-1"></i> Submit Plan</button>
                            </div>
                        </div>
                        <aside class="tt-proc-note">
                            <h3>Planning checklist</h3>
                            <ul>
                                <li><i class="feather-check-circle"></i><span>Use a clear plan title linked to the workplan or funded activity.</span></li>
                                <li><i class="feather-check-circle"></i><span>Enter the estimated budget in USD for Secretariat and partner reporting.</span></li>
                                <li><i class="feather-check-circle"></i><span>Add enough description for oversight teams to understand the procurement purpose.</span></li>
                            </ul>
                        </aside>
                    </div>
                </form>
            </div>

            <div class="tab-pane fade" id="opportunity-pane" role="tabpanel" aria-labelledby="opportunity-tab" tabindex="0">
                <form method="POST" action="{{ $opportunityAction }}">
                    @csrf
                    <div class="tt-proc-form-grid">
                        <div>
                            <div class="tt-proc-card">
                                <h2>Opportunity details</h2>
                                <p class="hint">Published opportunities appear on the public procurement page and receive vendor applications.</p>
                                <div class="tt-field-grid">
                                    <div class="tt-field full">
                                        <label for="think_tank_procurement_plan_id">Link to plan</label>
                                        <select id="think_tank_procurement_plan_id" name="think_tank_procurement_plan_id">
                                            <option value="">No linked plan</option>
                                            @foreach($plans as $plan)
                                                <option value="{{ $plan->id }}" @selected(old('think_tank_procurement_plan_id') === $plan->id)>
                                                    {{ $plan->title }} · {{ $currency }} {{ number_format((float) $plan->estimated_budget, 2) }}
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
                            </div>
                            <div class="d-flex flex-wrap justify-content-end gap-2">
                                <button type="reset" class="btn btn-light border">Clear form</button>
                                <button type="submit" class="btn btn-primary"><i class="feather-save me-1"></i> Save Opportunity</button>
                            </div>
                        </div>
                        <aside class="tt-proc-note">
                            <h3>Publication checklist</h3>
                            <ul>
                                <li><i class="feather-check-circle"></i><span>Confirm the application window gives vendors enough time to apply.</span></li>
                                <li><i class="feather-check-circle"></i><span>Write the scope clearly so applicants understand deliverables and evidence required.</span></li>
                                <li><i class="feather-check-circle"></i><span>After publishing, use the Applications button to evaluate and select vendors.</span></li>
                            </ul>
                        </aside>
                    </div>
                </form>
            </div>

            <div class="tab-pane fade" id="plans-list-pane" role="tabpanel" aria-labelledby="plans-list-tab" tabindex="0">
                <div class="tt-proc-table-wrap">
                    <table class="tt-proc-table">
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
                                    <div class="tt-muted">{{ $plan->plan_code }}</div>
                                </td>
                                <td>{{ $plan->fiscal_year ?? 'N/A' }}</td>
                                <td>{{ $currency }} {{ number_format((float) $plan->estimated_budget, 2) }}</td>
                                <td>{{ $plan->planned_publish_date?->format('d M Y') ?? 'N/A' }}</td>
                                <td>{{ number_format($plan->procurements_count) }}</td>
                                <td><span class="tt-status {{ $plan->status }}">{{ str_replace('_', ' ', $plan->status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><div class="tt-empty">No procurement plan has been submitted yet.</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="opportunities-list-pane" role="tabpanel" aria-labelledby="opportunities-list-tab" tabindex="0">
                <div class="tt-proc-table-wrap">
                    <table class="tt-proc-table">
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
                                        <a class="btn btn-sm btn-primary" href="{{ route('think-tank.procurement.submissions', array_merge($portalRouteParams, ['procurement' => $procurement])) }}">Applications</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="tt-empty">No procurement opportunity has been created yet.</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $procurements->links() }}</div>
            </div>

            <div class="tab-pane fade" id="procurement-guide-pane" role="tabpanel" aria-labelledby="procurement-guide-tab" tabindex="0">
                <div class="tt-guide-grid">
                    <div class="tt-proc-card">
                        <h2>1. Plan</h2>
                        <p class="hint mb-0">Create procurement plans with USD budgets so the Secretariat can see intended spending before publication.</p>
                    </div>
                    <div class="tt-proc-card">
                        <h2>2. Publish</h2>
                        <p class="hint mb-0">Publish opportunities publicly when the scope, timeline, evaluation basis, and closing date are ready.</p>
                    </div>
                    <div class="tt-proc-card">
                        <h2>3. Evaluate</h2>
                        <p class="hint mb-0">Review applications in the portal, record scores, select the preferred vendor, and keep ATTP oversight visible.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-think-tank.partials.shell>
