@extends('layouts.app')
@section('title', 'Think Tank Procurement Worksheet')
@include('think-tank-procurement-admin._styles')
@include('think-tank-procurement-admin.worksheet._styles')

@section('content')
@php
    $hasFilters = collect($filters)->contains(fn ($value) => filled($value));
    $statusLabels = [
        'draft' => 'Draft at Think Tank',
        'submitted' => 'Submitted to AUC-ATTP',
        'revision_requested' => 'Returned for correction',
        'rejected' => 'Rejected',
        'approved' => 'Pending World Bank no-objection',
        'no_objection_obtained' => 'No-objection received / ready to execute',
        'published' => 'Execution underway / published',
    ];
    $planStatusLabels = [
        'draft' => 'Draft', 'submitted' => 'Submitted to AUC-ATTP',
        'revision_requested' => 'Returned for correction', 'rejected' => 'Rejected',
        'approved' => 'Secretariat approved',
    ];
    $currencySummary = $currencyTotals->map(
        fn ($row) => $row['currency'].' '.number_format((float) $row['amount'], 2)
    )->implode(' · ');
@endphp

<div class="nxl-container">
    <main class="ttw" aria-labelledby="worksheet-title">
        <header class="ttw-hero">
            <div>
                <div class="ttw-kicker">AUC-ATTP Secretariat · Item-level control desk</div>
                <h1 id="worksheet-title">Think Tank procurement worksheet</h1>
                <p>Review every submitted item in context, open its private supporting documents, record the Secretariat decision, and complete World Bank no-objection processing without losing the plan-level audit trail.</p>
            </div>
            <div class="ttw-hero-actions">
                @canany(['think_tank.procurement.review', 'procurement.view_all', 'procurement.manage_all'])
                    <a class="ttw-btn light" href="{{ route('think-tank-procurement.index') }}"><i class="feather-folder"></i> Plan folders</a>
                @endcanany
                @canany(['think_tank.procurement.reports', 'think_tank.procurement.step', 'procurement.view_all', 'procurement.manage_all'])
                    <a class="ttw-btn light" href="{{ route('think-tank-procurement.reports') }}"><i class="feather-bar-chart-2"></i> Reports &amp; STEP</a>
                @endcanany
            </div>
        </header>

        @if(session('success'))
            <div class="ttw-alert" role="status"><i class="feather-check-circle"></i><div><strong>Workflow updated</strong><p>{{ session('success') }}</p></div></div>
        @endif
        @if(session('error') || $errors->any())
            <div class="ttw-alert danger" role="alert"><i class="feather-alert-circle"></i><div><strong>The request could not be completed</strong><p>{{ session('error') ?: $errors->first() }}</p></div></div>
        @endif

        <section class="ttw-metrics" aria-label="Filtered procurement workload">
            <article class="ttw-metric"><i class="feather-layers"></i><strong>{{ number_format($stats['total']) }}</strong><span>Items in current scope</span></article>
            <article class="ttw-metric review"><i class="feather-inbox"></i><strong>{{ number_format($stats['secretariat_review']) }}</strong><span>Submitted to AUC-ATTP</span></article>
            <article class="ttw-metric bank"><i class="feather-globe"></i><strong>{{ number_format($stats['world_bank_pending']) }}</strong><span>Pending World Bank no-objection</span></article>
            <article class="ttw-metric ready"><i class="feather-check-circle"></i><strong>{{ number_format($stats['ready_to_execute']) }}</strong><span>No-objection received / ready to execute</span></article>
            <article class="ttw-metric action"><i class="feather-alert-triangle"></i><strong>{{ number_format($stats['action_required']) }}</strong><span>Returned or rejected</span></article>
            <article class="ttw-metric"><i class="feather-send"></i><strong>{{ number_format($stats['published']) }}</strong><span>Execution underway / published</span></article>
        </section>

        <div class="ttw-value-strip"><i class="feather-dollar-sign"></i><span><strong>Recorded value in this filtered scope:</strong> {{ $currencySummary ?: 'No monetary amount recorded' }}. Currencies are kept separate and are not converted.</span></div>

        <section class="ttw-filter-shell" aria-labelledby="worksheet-filter-heading">
            <div class="ttw-filter-head">
                <div><span class="ttw-section-kicker">Queue controls</span><h2 id="worksheet-filter-heading">Find the next item to process</h2><p>Combine organization, year, workflow, status, document and keyword filters.</p></div>
                @if($hasFilters)<a class="ttw-clear" href="{{ route('think-tank-procurement.worksheet.index') }}"><i class="feather-x"></i> Clear all filters</a>@endif
            </div>
            <form class="ttw-filters" method="GET" action="{{ route('think-tank-procurement.worksheet.index') }}">
                <div><label for="ttw-q">Search</label><input class="ttw-input" id="ttw-q" name="q" value="{{ $filters['q'] }}" placeholder="Item, plan, STEP or no-objection reference"></div>
                <div><label for="ttw-member">Think Tank</label><select class="ttw-input" id="ttw-member" name="think_tank_member_id"><option value="">All Think Tanks</option>@foreach($members as $member)<option value="{{ $member->id }}" @selected($filters['think_tank_member_id'] === $member->id)>{{ $member->name }}</option>@endforeach</select></div>
                <div><label for="ttw-year">Financial year</label><select class="ttw-input" id="ttw-year" name="fiscal_year"><option value="">All years</option>@foreach($fiscalYears as $year)<option value="{{ $year }}" @selected((string) $filters['fiscal_year'] === (string) $year)>FY {{ $year }}</option>@endforeach</select></div>
                <div><label for="ttw-queue">Processing queue</label><select class="ttw-input" id="ttw-queue" name="queue"><option value="">All queues</option><option value="secretariat_review" @selected($filters['queue'] === 'secretariat_review')>Submitted to AUC-ATTP</option><option value="world_bank_pending" @selected($filters['queue'] === 'world_bank_pending')>Pending World Bank no-objection</option><option value="ready_to_execute" @selected($filters['queue'] === 'ready_to_execute')>Ready to execute</option><option value="published" @selected($filters['queue'] === 'published')>Execution underway / published</option><option value="action_required" @selected($filters['queue'] === 'action_required')>Returned or rejected</option></select></div>
                <div><label for="ttw-plan-status">Plan status</label><select class="ttw-input" id="ttw-plan-status" name="plan_status"><option value="">Any plan status</option>@foreach($planStatusLabels as $value => $label)<option value="{{ $value }}" @selected($filters['plan_status'] === $value)>{{ $label }}</option>@endforeach</select></div>
                <div><label for="ttw-item-status">Item status</label><select class="ttw-input" id="ttw-item-status" name="item_status"><option value="">Any item status</option>@foreach($statusLabels as $value => $label)<option value="{{ $value }}" @selected($filters['item_status'] === $value)>{{ $label }}</option>@endforeach</select></div>
                <div><label for="ttw-documents">Evidence readiness</label><select class="ttw-input" id="ttw-documents" name="documents"><option value="">Any document state</option><option value="missing_tor" @selected($filters['documents'] === 'missing_tor')>Missing TOR</option><option value="has_tor" @selected($filters['documents'] === 'has_tor')>TOR attached</option><option value="has_no_objection" @selected($filters['documents'] === 'has_no_objection')>No-objection document attached</option><option value="missing_no_objection" @selected($filters['documents'] === 'missing_no_objection')>No no-objection document</option></select></div>
                <div class="ttw-filter-actions"><a class="ttw-btn" href="{{ route('think-tank-procurement.worksheet.index') }}">Reset</a><button class="ttw-btn primary" type="submit"><i class="feather-filter"></i> Apply filters</button></div>
            </form>
        </section>

        <section class="ttw-panel" aria-labelledby="worksheet-register-heading">
            <div class="ttw-panel-head">
                <div><span class="ttw-section-kicker">Controlled register</span><h2 id="worksheet-register-heading">Item-by-item submission worksheet</h2><p>Each row retains its Think Tank, annual plan, evidence and workflow context.</p></div>
                <span class="ttw-status">{{ number_format($items->total()) }} records</span>
            </div>
            @if($items->isNotEmpty())
                <div class="ttw-table-wrap">
                    <table class="ttw-table">
                        <thead><tr><th>Think Tank</th><th>Plan context</th><th>Procurement item</th><th>Method / review</th><th class="text-end">Estimated amount</th><th>Private documents</th><th>Current position</th><th>Action</th></tr></thead>
                        <tbody>
                        @foreach($items as $item)
                            @php
                                $nextAction = match($item->status) {
                                    'submitted' => 'Complete Secretariat review',
                                    'approved' => $item->plan?->status === 'approved' ? 'Record World Bank no-objection' : 'Await full plan approval',
                                    'no_objection_obtained' => 'Ready for execution',
                                    'published' => 'Execution underway',
                                    'revision_requested' => 'Await Think Tank correction',
                                    'rejected' => 'Decision recorded',
                                    default => 'Await Think Tank submission',
                                };
                            @endphp
                            <tr>
                                <td class="ttw-org"><strong>{{ $item->plan?->member?->name ?: 'Think Tank not set' }}</strong><small>{{ $item->plan?->member?->country ?: 'Country not set' }}</small></td>
                                <td class="ttw-plan"><span class="ttw-code">{{ $item->plan?->plan_code ?: 'No plan code' }}</span><small>FY {{ $item->plan?->fiscal_year ?: 'N/A' }} · {{ $planStatusLabels[$item->plan?->status] ?? Str::headline($item->plan?->status) }}</small></td>
                                <td class="ttw-item-name"><span class="ttw-code">{{ $item->item_code }}</span><strong>{{ $item->title }}</strong>@if($item->source_reference)<small>Source {{ $item->source_reference }}</small>@endif</td>
                                <td><strong>{{ $item->procurement_method ?: 'Not set' }}</strong><br><small>{{ $item->review_type ?: 'Review not set' }} · {{ Str::headline($item->procurement_category) ?: 'Category not set' }}</small></td>
                                <td class="ttw-money"><small>{{ $item->currency ?: 'Unspecified' }}</small><br><strong>{{ number_format((float) $item->estimated_amount, 2) }}</strong></td>
                                <td><div class="ttw-doc-summary"><span><i class="feather-file-text"></i> {{ $item->tor_documents_count }} TOR</span><span><i class="feather-paperclip"></i> {{ $item->documents_count }} total</span>@if($item->no_objection_documents_count)<span><i class="feather-check-circle"></i> Decision attached</span>@endif</div></td>
                                <td><span class="ttw-status {{ $item->status }}">{{ $statusLabels[$item->status] ?? Str::headline($item->status) }}</span><span class="ttw-next">{{ $nextAction }}</span></td>
                                <td><a class="ttw-btn" href="{{ route('think-tank-procurement.worksheet.show', [$item->plan, $item]) }}"><i class="feather-arrow-right"></i> Open worksheet</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="ttw-pagination">{{ $items->onEachSide(1)->links() }}</div>
            @else
                <div class="ttw-empty"><i class="feather-inbox"></i><h3>No procurement items match these filters</h3><p>Clear one or more filters to return to the complete Secretariat worksheet.</p></div>
            @endif
        </section>
    </main>
</div>
@endsection
