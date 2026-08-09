@extends('layouts.app')
@section('title', 'Think Tank Procurement Reports')
@include('think-tank-procurement-admin._styles')

@section('content')
@php
    $hasFilters = collect($filters)->filter(fn ($value) => filled($value))->isNotEmpty();
    $reportFilterParams = array_filter([
        'fiscal_year' => $filters['fiscal_year'],
        'status' => $filters['status'],
        'q' => $filters['q'],
    ], fn ($value) => filled($value));
    $currencySummary = $summary['budget_by_currency']->map(
        fn ($row) => $row['currency'].' '.number_format((float) $row['amount'], 2)
    )->implode(' · ');
    $stepEligibleStatuses = ['approved', 'no_objection_obtained', 'published'];
    $stepReadyOnPage = $items->getCollection()->filter(
        fn ($item) => $item->plan?->status === 'approved' && in_array($item->status, $stepEligibleStatuses, true)
    )->count();
@endphp

<div class="nxl-container">
    <div class="atp">
        <section class="atp-report-hero">
            <div>
                <div class="atp-kicker">Procurement reporting centre</div>
                <h1>Think Tank procurement reports</h1>
                <p>Review the consolidated procurement position, download individual Think Tank reports, produce an official consolidated PDF, or prepare approved items for STEP.</p>
            </div>
            <div class="atp-actions">
                <a class="atp-btn light" href="{{ route('think-tank-procurement.index') }}"><i class="feather-arrow-left"></i> Review workspace</a>
                @if($summary['items'])
                    <a class="atp-btn gold" href="{{ route('think-tank-procurement.reports.pdf', ['scope' => 'consolidated', ...$reportFilterParams]) }}"><i class="feather-download"></i> Consolidated PDF</a>
                @endif
            </div>
        </section>

        @if(session('error') || $errors->any())
            <div class="atp-step-alert danger" role="alert">
                <span><i class="feather-alert-circle"></i></span>
                <div><strong>STEP workbook could not be prepared</strong><p>{{ session('error') ?: $errors->first() }}</p></div>
            </div>
        @endif

        <section class="atp-metrics" aria-label="Procurement report summary">
            <article class="atp-metric"><i class="feather-users"></i><strong>{{ number_format($summary['think_tanks']) }}</strong><span>Think Tanks</span></article>
            <article class="atp-metric"><i class="feather-list"></i><strong>{{ number_format($summary['items']) }}</strong><span>Procurement items</span></article>
            <article class="atp-metric"><i class="feather-check-square"></i><strong>{{ number_format($summary['step_eligible']) }}</strong><span>Ready for STEP</span></article>
            <article class="atp-metric"><i class="feather-globe"></i><strong>{{ number_format($summary['no_objection']) }}</strong><span>No-objection / published</span></article>
            <article class="atp-metric"><i class="feather-send"></i><strong>{{ number_format($summary['published']) }}</strong><span>Published opportunities</span></article>
        </section>

        <section class="atp-report-value">
            <span><i class="feather-dollar-sign"></i></span>
            <div><small>Recorded procurement value</small><strong>{{ $currencySummary ?: 'No monetary value recorded' }}</strong><p>Currencies are shown separately and are not converted.</p></div>
        </section>

        <section class="atp-filter-shell">
            <div class="atp-filter-title">
                <div><span class="atp-section-kicker">Report controls</span><h2>Filter the reporting dataset</h2></div>
                @if($hasFilters)<a class="atp-clear-link" href="{{ route('think-tank-procurement.reports') }}"><i class="feather-x"></i> Clear all filters</a>@endif
            </div>
            <form class="atp-filter" method="GET" action="{{ route('think-tank-procurement.reports') }}">
                <div><label for="report-search">Search</label><div class="atp-search-control"><i class="feather-search"></i><input id="report-search" name="q" value="{{ $filters['q'] }}" placeholder="Item title, code or source reference"></div></div>
                <div><label for="report-member">Think Tank</label><select id="report-member" name="think_tank_member_id"><option value="">All Think Tanks</option>@foreach($members as $member)<option value="{{ $member->id }}" @selected($filters['think_tank_member_id'] === $member->id)>{{ $member->name }}</option>@endforeach</select></div>
                <div><label for="report-year">Financial year</label><select id="report-year" name="fiscal_year"><option value="">All years</option>@foreach($fiscalYears as $year)<option value="{{ $year }}" @selected((string) $filters['fiscal_year'] === (string) $year)>FY {{ $year }}</option>@endforeach</select></div>
                <div><label for="report-status">Item status</label><select id="report-status" name="status"><option value="">All statuses</option>@foreach(['draft', 'submitted', 'revision_requested', 'rejected', 'approved', 'no_objection_obtained', 'published'] as $status)<option value="{{ $status }}" @selected($filters['status'] === $status)>{{ Str::headline($status) }}</option>@endforeach</select></div>
                <button class="atp-btn primary" type="submit"><i class="feather-filter"></i> Run report</button>
            </form>
        </section>

        <section class="atp-report-downloads" aria-label="PDF report downloads">
            <article class="atp-download-card consolidated">
                <span class="atp-download-icon"><i class="feather-layers"></i></span>
                <div><span class="atp-section-kicker">All Think Tanks</span><h2>Consolidated procurement report</h2><p>One official PDF containing the portfolio summary, organization comparison and complete item register for the selected year, status and search filters.</p></div>
                @if($summary['items'])
                    <a class="atp-btn primary" href="{{ route('think-tank-procurement.reports.pdf', ['scope' => 'consolidated', ...$reportFilterParams]) }}"><i class="feather-file-text"></i> Download consolidated PDF</a>
                @else
                    <button class="atp-btn" disabled>No data to download</button>
                @endif
            </article>

            <article class="atp-download-card individual">
                <span class="atp-download-icon"><i class="feather-user"></i></span>
                <div><span class="atp-section-kicker">One organization</span><h2>Individual Think Tank report</h2><p>Select a Think Tank to generate its own procurement-position PDF using the current year, status and search filters.</p></div>
                <form method="GET" action="{{ route('think-tank-procurement.reports.pdf') }}">
                    <input type="hidden" name="scope" value="individual">
                    @foreach($reportFilterParams as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
                    <select name="think_tank_member_id" aria-label="Select Think Tank for individual PDF" required>
                        <option value="">Select a Think Tank</option>
                        @foreach($members as $member)<option value="{{ $member->id }}" @selected($filters['think_tank_member_id'] === $member->id)>{{ $member->name }}</option>@endforeach
                    </select>
                    <button class="atp-btn" type="submit"><i class="feather-download"></i> Download individual PDF</button>
                </form>
            </article>
        </section>

        <section class="atp-panel">
            <div class="atp-panel-head"><div><span class="atp-section-kicker">Portfolio distribution</span><h2>Think Tank report cards</h2><p>Compare item volumes, recorded values and World Bank clearance, or download one organization directly.</p></div><span class="atp-item-total"><strong>{{ $byThinkTank->count() }}</strong> organizations</span></div>
            <div class="atp-panel-body">
                <div class="atp-report-org-grid">
                    @forelse($byThinkTank as $row)
                        <article class="atp-report-org">
                            <div class="atp-report-org-head"><span class="atp-report-org-icon"><i class="feather-briefcase"></i></span><div><h3>{{ $row['name'] }}</h3><p>{{ $row['country'] ?: 'Country not set' }} @if($row['years']->isNotEmpty())&middot; {{ $row['years']->map(fn ($year) => 'FY '.$year)->implode(', ') }}@endif</p></div></div>
                            <div class="atp-report-org-value"><small>Recorded value</small><strong>{{ $row['budget_by_currency']->implode(' · ') }}</strong></div>
                            <div class="atp-report-org-stats"><span><strong>{{ $row['items'] }}</strong> items</span><span><strong>{{ $row['approved'] }}</strong> approved</span><span class="{{ $row['action_required'] ? 'alert' : '' }}"><strong>{{ $row['action_required'] }}</strong> action</span><span><strong>{{ $row['no_objection'] }}</strong> cleared</span></div>
                            <a class="atp-report-org-download" href="{{ route('think-tank-procurement.reports.pdf', ['scope' => 'individual', 'think_tank_member_id' => $row['id'], ...$reportFilterParams]) }}"><i class="feather-download"></i> Download individual PDF</a>
                        </article>
                    @empty
                        <div class="atp-empty atp-report-empty"><span class="atp-empty-icon"><i class="feather-bar-chart-2"></i></span><h3>No report data found</h3><p>Adjust the report filters to display procurement records.</p></div>
                    @endforelse
                </div>
            </div>
        </section>

        <form id="step-export-form" method="POST" action="{{ route('think-tank-procurement.step-export') }}">
            @csrf
            <input type="hidden" name="think_tank_member_id" value="{{ $filters['think_tank_member_id'] }}">
            <input type="hidden" name="fiscal_year" value="{{ $filters['fiscal_year'] }}">
            <input type="hidden" name="status" value="{{ $filters['status'] }}">
            <input type="hidden" name="q" value="{{ $filters['q'] }}">
            <section class="atp-panel atp-register-panel">
                <div class="atp-register-head">
                    <div class="atp-register-title">
                        <span class="atp-section-kicker">Detailed register</span>
                        <h2>Consolidated procurement items</h2>
                        <p>A clean working register of every item in this report. Only items whose plan and item approvals are complete can be included in a STEP workbook.</p>
                    </div>
                    <div class="atp-register-controls">
                        <div class="atp-step-readiness {{ $summary['step_eligible'] ? 'ready' : 'blocked' }}">
                            <span><i class="{{ $summary['step_eligible'] ? 'feather-check-circle' : 'feather-clock' }}"></i></span>
                            <div><strong>{{ number_format($summary['step_eligible']) }} STEP-ready</strong><small>in {{ number_format($summary['items']) }} filtered items</small></div>
                        </div>
                        <div class="atp-register-actions">
                            <button class="atp-btn" type="submit" name="selection_mode" value="explicit" data-export-selected disabled>
                                <i class="feather-check-square"></i> Export selected <span data-selected-count>0</span>
                            </button>
                            <button class="atp-btn primary" type="submit" name="selection_mode" value="filtered" @disabled(!$summary['step_eligible'])>
                                <i class="feather-download"></i> Export all {{ number_format($summary['step_eligible']) }} ready
                            </button>
                        </div>
                    </div>
                </div>

                @if(!$summary['step_eligible'] && $summary['items'])
                    <div class="atp-step-guidance"><i class="feather-info"></i><span><strong>No items are ready for STEP yet.</strong> Submit and approve the annual procurement plan, then approve the required procurement items. The download controls will activate automatically.</span></div>
                @elseif($summary['step_eligible'])
                    <div class="atp-step-guidance ready"><i class="feather-shield"></i><span>Select rows from this page, or export every STEP-ready item matching the report filters above.</span></div>
                @endif

                <div class="atp-register-frame">
                    <div class="atp-table-wrap">
                    <table id="procurement-step-register" class="atp-table atp-report-table">
                        <colgroup><col class="atp-col-select"><col class="atp-col-organization"><col class="atp-col-item"><col class="atp-col-method"><col class="atp-col-schedule"><col class="atp-col-amount"><col class="atp-col-documents"><col class="atp-col-status"></colgroup>
                        <thead>
                            <tr>
                                <th class="no-sort atp-select-column"><input type="checkbox" data-select-all aria-label="Select every STEP-ready item on this page" @disabled(!$stepReadyOnPage)></th>
                                <th>Think Tank &amp; plan</th>
                                <th>Procurement item</th>
                                <th>Approach</th>
                                <th>Schedule</th>
                                <th class="text-end">Budget</th>
                                <th class="no-sort">Attachments</th>
                                <th>Workflow</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($items as $item)
                            @php
                                $isStepReady = $item->plan?->status === 'approved' && in_array($item->status, $stepEligibleStatuses, true);
                                $torCount = $item->documents->where('document_type', 'tor')->count();
                                $supportingCount = $item->documents->where('document_type', 'supporting')->count();
                            @endphp
                            <tr class="{{ $isStepReady ? 'is-step-ready' : 'is-not-step-ready' }}" data-register-row>
                                <td class="atp-select-column">
                                    @if($isStepReady)
                                        <input type="checkbox" name="item_ids[]" value="{{ $item->id }}" data-item-checkbox aria-label="Select {{ $item->item_code }} for STEP export">
                                    @else
                                        <span class="atp-step-locked" title="Plan and item approval are required for STEP export"><i class="feather-lock"></i></span>
                                    @endif
                                </td>
                                <td data-order="{{ $item->plan?->member?->name }}">
                                    <div class="atp-register-primary">{{ $item->plan?->member?->name ?: 'Think Tank not set' }}</div>
                                    <div class="atp-register-meta"><span>{{ $item->plan?->plan_code ?: 'No plan code' }}</span><span>FY {{ $item->plan?->fiscal_year ?: '—' }}</span></div>
                                </td>
                                <td data-order="{{ $item->item_code }}">
                                    <a class="atp-register-code" href="{{ route('think-tank-procurement.show', $item->plan) }}#item-{{ $item->id }}">{{ $item->item_code }}</a>
                                    <div class="atp-register-item-title">{{ $item->title }}</div>
                                    @if($item->source_reference)<small class="atp-register-reference">Ref: {{ $item->source_reference }}</small>@endif
                                </td>
                                <td>
                                    <div class="atp-register-primary">{{ $item->procurement_method ?: 'Not set' }}</div>
                                    <span class="atp-register-tag">{{ Str::headline($item->procurement_category ?: 'uncategorized') }}</span>
                                </td>
                                <td data-order="{{ $item->planned_start_date?->timestamp ?: 0 }}">
                                    <div class="atp-register-primary">{{ $item->planned_quarter ?: 'Quarter TBC' }}</div>
                                    <div class="atp-register-meta">{{ $item->planned_start_date?->format('d M Y') ?: 'Start date TBC' }}</div>
                                </td>
                                <td class="atp-register-amount" data-order="{{ (float) $item->estimated_amount }}">
                                    <small>{{ $item->currency ?: 'USD' }}</small><strong>{{ number_format((float) $item->estimated_amount, 2) }}</strong>
                                </td>
                                <td>
                                    <div class="atp-register-docs">
                                        <span class="{{ $torCount ? 'has-files' : '' }}"><i class="feather-file-text"></i> {{ $torCount }} TOR</span>
                                        <span class="{{ $supportingCount ? 'has-files' : '' }}"><i class="feather-paperclip"></i> {{ $supportingCount }} supporting</span>
                                    </div>
                                </td>
                                <td data-order="{{ $item->status }}">
                                    <span class="atp-status {{ $item->status }}">{{ $item->workflowActivityStatus() }}</span>
                                    <div class="atp-register-plan-status">Plan: {{ Str::headline($item->plan?->status ?: 'unknown') }}</div>
                                    @if($item->step_reference)<small class="atp-register-step-ref">STEP {{ $item->step_reference }}</small>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="atp-empty">No items match the selected report filters.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                    </div>
                </div>
            </section>
        </form>

        @if($items->hasPages())<div>{{ $items->links() }}</div>@endif
        <p class="text-muted fs-11 mb-0">Mixed currencies are displayed as recorded and are not converted. The PDF and STEP workbook retain each item’s original currency.</p>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const selectAll = document.querySelector('[data-select-all]');
    const itemCheckboxes = Array.from(document.querySelectorAll('[data-item-checkbox]'));
    const exportSelected = document.querySelector('[data-export-selected]');
    const selectedCount = document.querySelector('[data-selected-count]');

    const updateSelection = () => {
        const checked = itemCheckboxes.filter(box => box.checked);
        itemCheckboxes.forEach(box => box.closest('[data-register-row]')?.classList.toggle('is-selected', box.checked));
        if (selectedCount) selectedCount.textContent = checked.length;
        if (exportSelected) exportSelected.disabled = checked.length === 0;
        if (selectAll) {
            selectAll.checked = itemCheckboxes.length > 0 && checked.length === itemCheckboxes.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < itemCheckboxes.length;
        }
    };

    selectAll?.addEventListener('change', event => {
        itemCheckboxes.forEach(box => box.checked = event.target.checked);
        updateSelection();
    });
    itemCheckboxes.forEach(box => box.addEventListener('change', updateSelection));
    updateSelection();

    const table = document.getElementById('procurement-step-register');
    const registerRows = table?.querySelectorAll('tbody tr[data-register-row]') ?? [];
    if (table && registerRows.length && window.jQuery && jQuery.fn.DataTable && !jQuery.fn.DataTable.isDataTable(table)) {
        jQuery(table).DataTable({
            paging: false,
            searching: false,
            info: false,
            lengthChange: false,
            ordering: true,
            order: [],
            autoWidth: false,
            responsive: false,
            dom: 't',
            columnDefs: [{ targets: [0, 6], orderable: false }]
        });
    }
});
</script>
@endpush
