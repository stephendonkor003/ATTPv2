@extends('layouts.app')
@section('title', $plan->plan_code.' Review')
@include('think-tank-procurement-admin._styles')

@section('content')
@php
    $statusLabels = [
        'draft' => 'Draft',
        'submitted' => 'Under review',
        'revision_requested' => 'Action required',
        'rejected' => 'Rejected',
        'approved' => 'Approved',
        'no_objection_obtained' => 'No-objection obtained',
        'published' => 'Published',
    ];
    $memberName = $plan->member?->name ?: 'Unnamed Think Tank';
    $initials = collect(preg_split('/\s+/', trim($memberName)) ?: [])
        ->filter()->take(2)->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->implode('') ?: 'TT';
    $hasNoObjection = $plan->items->contains(fn ($item) => in_array($item->status, ['no_objection_obtained', 'published'], true));
    $hasPublished = $plan->items->contains('status', 'published');
    $workflowStep = $hasPublished ? 5 : ($hasNoObjection ? 4 : ($plan->status === 'approved' ? 3 : (in_array($plan->status, ['submitted', 'revision_requested', 'rejected'], true) ? 2 : 1)));
@endphp

<div class="nxl-container">
    <div class="atp">
        <nav class="atp-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('think-tank-procurement.index', ['fiscal_year' => $plan->fiscal_year]) }}"><i class="feather-arrow-left"></i> Procurement folders</a>
            <i class="feather-chevron-right"></i>
            <span>{{ $memberName }}</span>
            <i class="feather-chevron-right"></i>
            <strong>FY {{ $plan->fiscal_year }}</strong>
        </nav>

        <header class="atp-plan-cover">
            <div class="atp-plan-identity">
                <span class="atp-plan-avatar">{{ $initials }}</span>
                <div>
                    <div class="atp-kicker">Financial year {{ $plan->fiscal_year }}</div>
                    <h1>{{ $memberName }}</h1>
                    <p>{{ $plan->title }}</p>
                    <div class="atp-plan-meta">
                        <span><i class="feather-hash"></i> {{ $plan->plan_code }}</span>
                        <span><i class="feather-layers"></i> Version {{ $plan->version }}</span>
                        <span><i class="feather-users"></i> {{ $plan->consortium?->name ?: 'Consortium not set' }}</span>
                        <span><i class="feather-clock"></i> {{ $plan->submitted_at ? 'Submitted '.$plan->submitted_at->format('d M Y, H:i') : 'Not submitted' }}</span>
                    </div>
                </div>
            </div>
            <div class="atp-plan-cover-side">
                <span class="atp-status {{ $plan->status }}">{{ $statusLabels[$plan->status] ?? Str::headline($plan->status) }}</span>
                <a class="atp-btn" href="{{ route('think-tank-procurement.reports', ['think_tank_member_id' => $plan->think_tank_member_id, 'fiscal_year' => $plan->fiscal_year]) }}">
                    <i class="feather-bar-chart-2"></i> Open filtered report
                </a>
            </div>
        </header>

        <section class="atp-workflow" aria-label="Procurement plan workflow">
            @foreach([
                1 => ['folder', 'Plan prepared'],
                2 => ['search', 'ATTP review'],
                3 => ['check-circle', 'Plan approved'],
                4 => ['globe', 'No-objection'],
                5 => ['send', 'Execution'],
            ] as $step => [$icon, $label])
                <div class="atp-workflow-step {{ $workflowStep >= $step ? 'complete' : '' }} {{ $workflowStep === $step ? 'current' : '' }}">
                    <span><i class="feather-{{ $icon }}"></i></span>
                    <strong>{{ $label }}</strong>
                </div>
            @endforeach
        </section>

        <nav class="atp-section-nav" aria-label="Plan page sections">
            <a href="#plan-summary"><i class="feather-pie-chart"></i> Summary</a>
            @if(in_array($plan->status, ['submitted', 'revision_requested'], true))<a href="#plan-decision"><i class="feather-shield"></i> Plan decision</a>@endif
            <a href="#procurement-items"><i class="feather-list"></i> Items <span>{{ $stats['items'] }}</span></a>
            <a href="#audit-trail"><i class="feather-activity"></i> Audit trail</a>
        </nav>

        <section id="plan-summary" class="atp-metrics atp-plan-metrics">
            <article class="atp-metric"><i class="feather-list"></i><strong>{{ number_format($stats['items']) }}</strong><span>Plan items</span></article>
            <article class="atp-metric"><i class="feather-dollar-sign"></i><strong>{{ $plan->currency }} {{ number_format($stats['budget'], 2) }}</strong><span>Plan value</span></article>
            <article class="atp-metric"><i class="feather-check"></i><strong>{{ number_format($stats['approved']) }}</strong><span>Approved / cleared</span></article>
            <article class="atp-metric {{ $stats['action'] ? 'attention' : '' }}"><i class="feather-alert-circle"></i><strong>{{ number_format($stats['action']) }}</strong><span>Need correction</span></article>
            <article class="atp-metric"><i class="feather-globe"></i><strong>{{ number_format($stats['no_objection']) }}</strong><span>No-objection / published</span></article>
        </section>

        @if(in_array($plan->status, ['submitted', 'revision_requested'], true))
            <section id="plan-decision" class="atp-panel atp-decision-panel">
                <div class="atp-panel-head">
                    <div>
                        <span class="atp-section-kicker">Plan-level control</span>
                        <h2>Complete the whole-plan decision</h2>
                        <p>Approve the complete submission or return it with clear, actionable instructions.</p>
                    </div>
                    <span class="atp-panel-icon"><i class="feather-shield"></i></span>
                </div>
                <div class="atp-panel-body">
                    <form method="POST" action="{{ route('think-tank-procurement.plans.decision', $plan) }}">
                        @csrf
                        <label class="atp-label" for="plan-decision-reason">Decision reason or correction instructions <span class="text-muted">(required when returning or rejecting)</span></label>
                        <textarea id="plan-decision-reason" class="atp-input atp-decision-note" name="reason" placeholder="Explain exactly what the Think Tank should correct or remove before resubmitting.">{{ old('reason') }}</textarea>
                        <div class="atp-action-buttons">
                            <button class="atp-btn success" name="decision" value="approve" @disabled($stats['action'] > 0) @if($stats['action'] === 0) onclick="return confirm('Approve this entire procurement plan?')" @else title="Resolve every returned or rejected item first" @endif><i class="feather-check"></i> Approve full plan</button>
                            <button class="atp-btn warn" name="decision" value="revision_requested"><i class="feather-corner-up-left"></i> Return for correction</button>
                            <button class="atp-btn danger" name="decision" value="rejected" onclick="return confirm('Reject this entire plan?')"><i class="feather-x"></i> Reject full plan</button>
                        </div>
                    </form>
                </div>
            </section>
        @endif

        <section id="procurement-items" class="atp-panel atp-items-panel">
            <div class="atp-panel-head">
                <div>
                    <span class="atp-section-kicker">Plan register</span>
                    <h2>Procurement items and documents</h2>
                    <p>Open TORs and supporting documents, then record a decision against the relevant item.</p>
                </div>
                <span class="atp-item-total"><strong>{{ $plan->items->count() }}</strong> items</span>
            </div>
            <div class="atp-panel-body atp-item-list">
                @forelse($plan->items as $item)
                    <article class="atp-item" id="item-{{ $item->id }}">
                        <div class="atp-item-main">
                            <span class="atp-item-index">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <div class="atp-item-content">
                                <div class="atp-code">{{ $item->item_code }} @if($item->source_reference)&middot; Source {{ $item->source_reference }}@endif</div>
                                <h3>{{ $item->title }}</h3>
                                @if($item->description)<p class="atp-item-description">{{ $item->description }}</p>@endif

                                <dl class="atp-item-facts">
                                    <div><dt>Loan / credit no.</dt><dd>{{ $item->loan_credit_no ?: 'Not set' }}</dd></div>
                                    <div><dt>Component</dt><dd>{{ $item->component ?: 'Not set' }}</dd></div>
                                    <div><dt>Category</dt><dd>{{ Str::headline($item->procurement_category) ?: 'Not set' }}</dd></div>
                                    <div><dt>Method</dt><dd>{{ $item->procurement_method ?: 'Not set' }}</dd></div>
                                    <div><dt>Review type</dt><dd>{{ $item->review_type ?: 'Not set' }}</dd></div>
                                    <div><dt>Market approach</dt><dd>{{ $item->market_approach ?: 'Not set' }}</dd></div>
                                    <div><dt>High SEA/SH risk</dt><dd>{{ $item->source_sea_sh_risk ?: 'Not set' }}</dd></div>
                                    <div><dt>Document type</dt><dd>{{ $item->source_document_type ?: 'Not set' }}</dd></div>
                                    <div><dt>Process status</dt><dd>{{ $item->source_process_status ?: 'Not set' }}</dd></div>
                                    <div><dt>Activity status</dt><dd>{{ $item->workflowActivityStatus() }}</dd></div>
                                    <div><dt>Schedule</dt><dd>{{ $item->planned_quarter ?: ($item->planned_start_date?->format('d M Y') ?: 'Not scheduled') }}</dd></div>
                                    <div><dt>Documents</dt><dd>{{ $item->documents->count() }} attached</dd></div>
                                </dl>

                                <div class="atp-docs">
                                    @forelse($item->documents as $document)
                                        <a class="atp-doc {{ $document->document_type }}" href="{{ route('think-tank-procurement.documents.download', [$plan, $item, $document]) }}">
                                            <i class="{{ $document->document_type === 'tor' ? 'feather-file-text' : 'feather-paperclip' }}"></i>
                                            <span>{{ $document->document_name }} <small>{{ $document->formatted_size }}</small></span>
                                            <i class="feather-download"></i>
                                        </a>
                                    @empty
                                        <span class="atp-no-doc"><i class="feather-alert-circle"></i> No document attached</span>
                                    @endforelse
                                </div>
                            </div>
                            <aside class="atp-item-side">
                                <div class="atp-value">{{ $item->currency }} {{ number_format((float) $item->estimated_amount, 2) }}</div>
                                <span class="atp-status {{ $item->status }}">{{ $item->workflowActivityStatus() }}</span>
                            </aside>
                        </div>

                        @if($item->review_reason)
                            <div class="atp-review-note"><i class="feather-message-square"></i><div><strong>Review note</strong><p>{{ $item->review_reason }}</p></div></div>
                        @endif

                        @if(in_array($item->status, ['no_objection_obtained', 'published'], true))
                            <div class="atp-clearance-strip">
                                <span><i class="feather-check-circle"></i><strong>World Bank clearance</strong></span>
                                <span>STEP {{ $item->step_reference ?: 'reference pending' }}</span>
                                <span>{{ $item->no_objection_date?->format('d M Y') ?: 'Date not recorded' }}</span>
                                @if($item->procurement)<span class="atp-status {{ $item->procurement->status }}">Opportunity {{ Str::headline($item->procurement->status) }}</span>@endif
                            </div>
                        @endif

                        @if($item->status === 'submitted' || $item->status === 'approved')
                            <div class="atp-item-actions">
                                @if($item->status === 'submitted')
                                    <div class="atp-action-box">
                                        <h4><i class="feather-edit-3"></i> Item review decision</h4>
                                        <form method="POST" action="{{ route('think-tank-procurement.items.decision', [$plan, $item]) }}">
                                            @csrf
                                            <textarea class="atp-input" name="reason" placeholder="Add the reason when returning or rejecting this item"></textarea>
                                            <div class="atp-action-buttons">
                                                <button class="atp-btn success" name="decision" value="approve"><i class="feather-check"></i> Approve item</button>
                                                <button class="atp-btn warn" name="decision" value="revision_requested"><i class="feather-corner-up-left"></i> Return item</button>
                                                <button class="atp-btn danger" name="decision" value="rejected"><i class="feather-x"></i> Reject item</button>
                                            </div>
                                        </form>
                                    </div>
                                @else
                                    <div class="atp-action-box atp-step-box">
                                        <h4><i class="feather-upload-cloud"></i> STEP handoff</h4>
                                        @if($plan->status === 'approved')
                                            <p>Export the approved item, upload it to STEP, then return here to record the TTL decision.</p>
                                            <a class="atp-btn" href="{{ route('think-tank-procurement.reports', ['think_tank_member_id' => $plan->think_tank_member_id, 'fiscal_year' => $plan->fiscal_year]) }}">Open STEP export</a>
                                        @else
                                            <p>Complete the full plan approval before exporting this item to STEP.</p>
                                        @endif
                                    </div>
                                @endif

                                @if($item->status === 'approved' && $plan->status === 'approved')
                                    <div class="atp-action-box">
                                        <h4><i class="feather-globe"></i> Record World Bank no-objection</h4>
                                        <form method="POST" action="{{ route('think-tank-procurement.items.no-objection', [$plan, $item]) }}" enctype="multipart/form-data">
                                            @csrf
                                            <div class="atp-form-grid">
                                                <div><label class="atp-label">STEP reference</label><input class="atp-input" name="step_reference" value="{{ $item->step_reference }}" required></div>
                                                <div><label class="atp-label">Decision date</label><input class="atp-input" type="date" name="no_objection_date" value="{{ now()->toDateString() }}" required></div>
                                                <div><label class="atp-label">No-objection reference</label><input class="atp-input" name="no_objection_reference"></div>
                                                <div><label class="atp-label">Decision document</label><input class="atp-input" type="file" name="no_objection_document" accept=".pdf,.doc,.docx"></div>
                                            </div>
                                            <textarea class="atp-input mt-2" name="no_objection_notes" placeholder="Notes included in the notification to the Think Tank"></textarea>
                                            <button class="atp-btn primary mt-2" type="submit"><i class="feather-check-circle"></i> Confirm and notify Think Tank</button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="atp-empty"><span class="atp-empty-icon"><i class="feather-inbox"></i></span><h3>No procurement items</h3><p>This annual plan does not contain any items yet.</p></div>
                @endforelse
            </div>
        </section>

        <section id="audit-trail" class="atp-panel">
            <div class="atp-panel-head">
                <div><span class="atp-section-kicker">Accountability</span><h2>Complete audit trail</h2><p>Every plan, item review, STEP and execution event in chronological context.</p></div>
                <span class="atp-item-total"><strong>{{ $plan->events->count() }}</strong> events</span>
            </div>
            <div class="atp-panel-body">
                <div class="atp-timeline">
                    @forelse($plan->events as $event)
                        <div class="atp-event">
                            <span class="atp-event-icon"><i class="feather-check"></i></span>
                            <div><strong>{{ Str::headline($event->action) }} @if($event->item)&middot; {{ $event->item->item_code }}@endif</strong><p>{{ $event->actor?->name ?? 'System' }}@if($event->reason) &mdash; {{ $event->reason }}@endif</p></div>
                            <time>{{ $event->created_at?->format('d M Y H:i') }}</time>
                        </div>
                    @empty
                        <div class="atp-empty">No workflow event has been recorded.</div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
</div>
@endsection
