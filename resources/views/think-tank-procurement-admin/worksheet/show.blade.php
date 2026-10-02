@extends('layouts.app')
@section('title', $item->item_code.' Procurement Worksheet')
@include('think-tank-procurement-admin._styles')
@include('think-tank-procurement-admin.worksheet._styles')

@section('content')
@php
    $statusLabels = [
        'draft' => 'Draft at Think Tank',
        'submitted' => 'Submitted to AUC-ATTP',
        'revision_requested' => 'Returned for correction',
        'rejected' => 'Rejected',
        'approved' => 'Pending World Bank no-objection',
        'no_objection_obtained' => 'No-objection received / ready to execute',
        'published' => 'Execution underway / published',
    ];
    $memberName = $plan->member?->name ?: 'Think Tank not set';
    $planApproved = $plan->status === 'approved';
    $canReviewNow = $permissions['review'] && $item->status === 'submitted' && in_array($plan->status, ['submitted', 'revision_requested'], true);
    $canRecordNoObjection = $permissions['step'] && $item->status === 'approved' && $planApproved;
    $plannedMilestones = collect($item->planned_milestones ?? [])->filter(fn ($row) => is_array($row));
@endphp

<div class="nxl-container">
    <main class="ttw" aria-labelledby="item-worksheet-title">
        <nav class="ttw-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('think-tank-procurement.worksheet.index') }}"><i class="feather-arrow-left"></i> Item worksheet</a>
            <i class="feather-chevron-right"></i>
            @canany(['think_tank.procurement.review', 'procurement.view_all', 'procurement.manage_all'])
                <a href="{{ route('think-tank-procurement.show', $plan) }}">{{ $plan->plan_code }}</a>
            @else
                <span>{{ $plan->plan_code }}</span>
            @endcanany
            <i class="feather-chevron-right"></i><strong>{{ $item->item_code }}</strong>
        </nav>

        @if(session('success'))
            <div class="ttw-alert" role="status"><i class="feather-check-circle"></i><div><strong>Workflow updated</strong><p>{{ session('success') }}</p></div></div>
        @endif
        @if(session('error') || $errors->any())
            <div class="ttw-alert danger" role="alert"><i class="feather-alert-circle"></i><div><strong>The action was not recorded</strong><p>{{ session('error') ?: $errors->first() }}</p></div></div>
        @endif

        <header class="ttw-record-hero">
            <div>
                <span class="ttw-kicker">{{ $memberName }} · FY {{ $plan->fiscal_year }}</span>
                <h1 id="item-worksheet-title">{{ $item->title }}</h1>
                <div class="ttw-record-meta">
                    <span><i class="feather-hash"></i> {{ $item->item_code }}</span>
                    <span><i class="feather-folder"></i> {{ $plan->plan_code }} · Version {{ $plan->version }}</span>
                    <span><i class="feather-map-pin"></i> {{ $plan->member?->country ?: 'Country not set' }}</span>
                    <span><i class="feather-users"></i> {{ $plan->consortium?->name ?: 'Consortium not set' }}</span>
                    <span><i class="feather-clock"></i> Updated {{ $item->updated_at?->format('d M Y, H:i') ?: 'date unavailable' }}</span>
                </div>
            </div>
            <div class="ttw-record-actions">
                <span class="ttw-status {{ $item->status }}">{{ $statusLabels[$item->status] ?? Str::headline($item->status) }}</span>
                <a class="ttw-btn" target="_blank" rel="noopener" href="{{ route('think-tank-procurement.worksheet.pdf', [$plan, $item]) }}"><i class="feather-eye"></i> View PDF</a>
                <a class="ttw-btn primary" href="{{ route('think-tank-procurement.worksheet.pdf', [$plan, $item, 'download' => 1]) }}"><i class="feather-download"></i> Download PDF</a>
            </div>
        </header>

        <section class="ttw-flow" aria-label="Item workflow position">
            @foreach($workflowStages as $position => $stage)
                <div class="ttw-flow-step {{ $stage['state'] }}">
                    <span>{{ $position + 1 }}</span>
                    <div><strong>{{ $stage['label'] }}</strong><small>{{ $stage['detail'] }}</small></div>
                </div>
            @endforeach
        </section>

        <div class="ttw-workspace">
            <div class="d-grid gap-3">
                <section class="ttw-panel" aria-labelledby="submission-record-heading">
                    <div class="ttw-panel-head"><div><span class="ttw-section-kicker">Think Tank submission</span><h2 id="submission-record-heading">Procurement item record</h2><p>Authoritative fields submitted within {{ $plan->plan_code }}.</p></div><span class="ttw-status {{ $plan->status }}">Plan: {{ Str::headline($plan->status) }}</span></div>
                    <div class="ttw-panel-body">
                        @if($item->description)<p class="ttw-description">{{ $item->description }}</p>@endif
                        <dl class="ttw-facts">
                            <div class="ttw-fact"><dt>Estimated amount</dt><dd>{{ $item->currency ?: 'Unspecified' }} {{ number_format((float) $item->estimated_amount, 2) }}</dd></div>
                            <div class="ttw-fact"><dt>Procurement category</dt><dd>{{ Str::headline($item->procurement_category) ?: 'Not set' }}</dd></div>
                            <div class="ttw-fact"><dt>Procurement method</dt><dd>{{ $item->procurement_method ?: 'Not set' }}</dd></div>
                            <div class="ttw-fact"><dt>Market approach</dt><dd>{{ $item->market_approach ?: 'Not set' }}</dd></div>
                            <div class="ttw-fact"><dt>Review type</dt><dd>{{ $item->review_type ?: 'Not set' }}</dd></div>
                            <div class="ttw-fact"><dt>Document type</dt><dd>{{ $item->source_document_type ?: 'Not set' }}</dd></div>
                            <div class="ttw-fact"><dt>Loan / credit no.</dt><dd>{{ $item->loan_credit_no ?: 'Not set' }}</dd></div>
                            <div class="ttw-fact"><dt>Component</dt><dd>{{ $item->component ?: 'Not set' }}</dd></div>
                            <div class="ttw-fact"><dt>Source reference</dt><dd>{{ $item->source_reference ?: 'Not set' }}</dd></div>
                            <div class="ttw-fact"><dt>Quantity / unit</dt><dd>{{ $item->quantity !== null ? rtrim(rtrim(number_format((float) $item->quantity, 4, '.', ''), '0'), '.') : 'Not set' }} {{ $item->unit }}</dd></div>
                            <div class="ttw-fact"><dt>Planned period</dt><dd>{{ $item->planned_quarter ?: 'Quarter not set' }} · {{ $item->planned_start_date?->format('d M Y') ?: 'Start TBC' }} to {{ $item->planned_end_date?->format('d M Y') ?: 'End TBC' }}</dd></div>
                            <div class="ttw-fact"><dt>SEA / SH risk</dt><dd>{{ $item->source_sea_sh_risk ?: 'Not set' }}</dd></div>
                            <div class="ttw-fact"><dt>Process status</dt><dd>{{ $item->source_process_status ?: 'Not set' }}</dd></div>
                            <div class="ttw-fact"><dt>Activity status</dt><dd>{{ $item->source_activity_status ?: $item->workflowActivityStatus() }}</dd></div>
                            <div class="ttw-fact"><dt>In process</dt><dd>{{ $item->source_in_process ?: 'Not set' }}</dd></div>
                        </dl>

                        @if($item->limited_selection_justification || $item->budget_reference || $item->bank_comment || $item->action_taken)
                            <div class="ttw-subhead"><h3>Workbook notes and controls</h3><span>Preserved from the submitted plan</span></div>
                            <dl class="ttw-facts">
                                @if($item->limited_selection_justification)<div class="ttw-fact"><dt>Limited selection justification</dt><dd>{{ $item->limited_selection_justification }}</dd></div>@endif
                                @if($item->budget_reference)<div class="ttw-fact"><dt>Budget reference</dt><dd>{{ $item->budget_reference }}</dd></div>@endif
                                @if($item->bank_comment)<div class="ttw-fact"><dt>World Bank comment</dt><dd>{{ $item->bank_comment }}</dd></div>@endif
                                @if($item->action_taken)<div class="ttw-fact"><dt>Action taken</dt><dd>{{ $item->action_taken }}</dd></div>@endif
                            </dl>
                        @endif

                        <div class="ttw-subhead"><h3>Private supporting documents</h3><span>{{ $item->documents->count() }} attachment(s)</span></div>
                        <div class="ttw-docs">
                            @forelse($item->documents as $document)
                                <a class="ttw-doc" href="{{ route('think-tank-procurement.documents.download', [$plan, $item, $document]) }}">
                                    <i class="{{ $document->document_type === 'tor' ? 'feather-file-text' : ($document->document_type === 'no_objection' ? 'feather-check-circle' : 'feather-paperclip') }}"></i>
                                    <span><strong>{{ $document->document_name ?: $document->original_name }}</strong><small>{{ Str::headline($document->document_type) }} · {{ $document->formatted_size }} · uploaded by {{ $document->uploader?->name ?: 'System user' }}</small></span>
                                    <i class="feather-download" aria-hidden="true"></i>
                                </a>
                            @empty
                                <div class="ttw-permission-note"><i class="feather-alert-circle"></i><span>No supporting document is attached to this item.</span></div>
                            @endforelse
                        </div>

                        @if($plannedMilestones->isNotEmpty())
                            <div class="ttw-subhead"><h3>Procurement method milestones</h3><span>Planned and imported actual dates</span></div>
                            <div class="ttw-milestones">
                                @foreach($plannedMilestones as $milestone)
                                    <div class="ttw-milestone">
                                        <strong>{{ $milestone['milestone'] ?? Str::headline($milestone['key'] ?? 'Milestone') }}</strong>
                                        <span>{{ Str::headline($milestone['timing'] ?? 'Schedule') }}</span>
                                        <em>{{ $milestone['date'] ?? $milestone['value'] ?? 'Not scheduled' }}</em>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </section>

                <section class="ttw-panel" aria-labelledby="timeline-heading">
                    <div class="ttw-panel-head"><div><span class="ttw-section-kicker">Accountability</span><h2 id="timeline-heading">Status and decision timeline</h2><p>Plan-level and item-level events that establish this record's current position.</p></div><span class="ttw-status">{{ $timeline->count() }} events</span></div>
                    <div class="ttw-panel-body">
                        <div class="ttw-timeline">
                            @forelse($timeline as $event)
                                <article class="ttw-event">
                                    <span class="ttw-event-marker"><i class="{{ str_contains($event->action, 'reject') || str_contains($event->action, 'revision') ? 'feather-alert-circle' : 'feather-check' }}"></i></span>
                                    <div>
                                        <strong>{{ Str::headline($event->action) }} @if(!$event->item_id)<span class="text-muted">· Plan event</span>@endif</strong>
                                        <p>{{ $event->actor?->name ?: 'System' }}@if($event->from_status || $event->to_status) · {{ Str::headline($event->from_status ?: 'created') }} → {{ Str::headline($event->to_status ?: 'recorded') }}@endif</p>
                                        @if($event->reason)<p class="ttw-review-note">{{ $event->reason }}</p>@endif
                                        @if(($event->notifications_queued_count + $event->notifications_sent_count + $event->notifications_failed_count) > 0)
                                            <div class="ttw-delivery" aria-label="Notification delivery summary">
                                                <span><i class="feather-clock"></i> {{ $event->notifications_queued_count }} queued</span>
                                                <span class="sent"><i class="feather-check"></i> {{ $event->notifications_sent_count }} sent</span>
                                                @if($event->notifications_failed_count)<span class="failed"><i class="feather-alert-circle"></i> {{ $event->notifications_failed_count }} failed</span>@endif
                                            </div>
                                        @endif
                                    </div>
                                    <time datetime="{{ $event->created_at?->toIso8601String() }}">{{ $event->created_at?->format('d M Y H:i') }}</time>
                                </article>
                            @empty
                                <div class="ttw-permission-note"><i class="feather-clock"></i><span>No workflow event has been recorded for this plan and item.</span></div>
                            @endforelse
                        </div>
                    </div>
                </section>
            </div>

            <aside class="ttw-control" aria-label="Permitted workflow actions">
                <section class="ttw-stage-card">
                    <small>Current workflow position</small>
                    <h2>{{ $statusLabels[$item->status] ?? Str::headline($item->status) }}</h2>
                    <p>{{ match($item->status) {
                        'submitted' => 'The Think Tank has submitted this item to the AUC-ATTP Secretariat for review.',
                        'approved' => $planApproved ? 'Secretariat review is complete. Record the World Bank no-objection when the formal decision is received.' : 'The item is accepted, but the complete annual plan must be approved before World Bank processing.',
                        'no_objection_obtained' => 'The World Bank no-objection has been received and this item is ready to execute.',
                        'published' => 'The cleared item has moved into execution or publication.',
                        'revision_requested' => 'The item is back with the Think Tank for correction and resubmission.',
                        'rejected' => 'The rejection is final for this submission version unless the workflow is reopened.',
                        default => 'The item is still being prepared by the Think Tank.',
                    } }}</p>
                    <div class="ttw-checklist">
                        <span class="ttw-check"><i class="{{ $plan->submitted_at ? 'feather-check-circle' : 'feather-circle' }}"></i> Plan submitted to AUC-ATTP</span>
                        <span class="ttw-check"><i class="{{ $planApproved ? 'feather-check-circle' : 'feather-circle' }}"></i> Full annual plan approved</span>
                        <span class="ttw-check"><i class="{{ $item->step_exported_at ? 'feather-check-circle' : 'feather-circle' }}"></i> STEP export {{ $item->step_exported_at ? 'recorded '.$item->step_exported_at->format('d M Y') : 'not yet recorded' }}</span>
                        <span class="ttw-check"><i class="{{ $item->no_objection_recorded_at ? 'feather-check-circle' : 'feather-circle' }}"></i> World Bank decision {{ $item->no_objection_recorded_at ? 'recorded' : 'pending' }}</span>
                    </div>
                </section>

                @if($item->review_reason)
                    <div class="ttw-review-note"><strong>Latest review instruction</strong><br>{{ $item->review_reason }}</div>
                @endif

                @if($canReviewNow)
                    <section class="ttw-action-box">
                        <h3><i class="feather-shield"></i> Secretariat item decision</h3>
                        <p>Approve this item or return it with a clear instruction. Returning or rejecting requires a reason.</p>
                        <form method="POST" action="{{ route('think-tank-procurement.items.decision', [$plan, $item]) }}" class="ttw-form-stack" data-review-form>
                            @csrf
                            <div><label class="ttw-label" for="review-reason">Decision reason / correction instruction</label><textarea class="ttw-input" id="review-reason" name="reason" rows="4" placeholder="Required when returning or rejecting">{{ old('reason') }}</textarea></div>
                            <div class="ttw-action-row">
                                <button class="ttw-btn primary" name="decision" value="approve" onclick="return confirm('Approve this procurement item at Secretariat level?')"><i class="feather-check"></i> Approve item</button>
                                <button class="ttw-btn warn" name="decision" value="revision_requested"><i class="feather-corner-up-left"></i> Return</button>
                                <button class="ttw-btn danger" name="decision" value="rejected" onclick="return confirm('Reject this procurement item? The reason will be sent to the Think Tank.')"><i class="feather-x"></i> Reject</button>
                            </div>
                        </form>
                    </section>
                @endif

                @if($canRecordNoObjection)
                    <section class="ttw-action-box">
                        <h3><i class="feather-globe"></i> World Bank no-objection</h3>
                        <p>Use the formal World Bank decision. This action records the no-objection and marks the item ready to execute.</p>
                        <form method="POST" action="{{ route('think-tank-procurement.items.no-objection', [$plan, $item]) }}" enctype="multipart/form-data" class="ttw-form-stack" data-wb-decision-form>
                            @csrf
                            <div><label class="ttw-label" for="step-reference">STEP reference</label><input class="ttw-input" id="step-reference" name="step_reference" value="{{ old('step_reference', $item->step_reference) }}" required></div>
                            <div class="ttw-form-grid">
                                <div><label class="ttw-label" for="decision-date">Decision date</label><input class="ttw-input" id="decision-date" type="date" name="no_objection_date" value="{{ old('no_objection_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></div>
                                <div><label class="ttw-label" for="decision-reference">Decision reference <span class="text-muted">reference or document required</span></label><input class="ttw-input" id="decision-reference" name="no_objection_reference" value="{{ old('no_objection_reference') }}" data-wb-reference aria-describedby="wb-evidence-help"></div>
                            </div>
                            <div><label class="ttw-label" for="decision-document">Decision document <span class="text-muted">PDF or Word, max 20 MB</span></label><input class="ttw-input" id="decision-document" type="file" name="no_objection_document" accept=".pdf,.doc,.docx" data-wb-document><small id="wb-evidence-help" class="text-muted">Provide the World Bank reference, the formal decision document, or both.</small></div>
                            <div><label class="ttw-label" for="decision-notes">Decision notes</label><textarea class="ttw-input" id="decision-notes" name="no_objection_notes" rows="4" placeholder="Optional context included in the Think Tank notification">{{ old('no_objection_notes') }}</textarea></div>
                            <button class="ttw-btn primary" type="submit" onclick="return confirm('Record this World Bank no-objection and mark the item ready to execute?')"><i class="feather-check-circle"></i> Record no-objection and mark ready to execute</button>
                        </form>
                    </section>
                @elseif($item->status === 'approved' && !$planApproved)
                    <div class="ttw-permission-note"><i class="feather-lock"></i><span>World Bank no-objection processing is locked until the complete annual procurement plan is approved.</span></div>
                @endif

                @if(in_array($item->status, ['no_objection_obtained', 'published'], true))
                    <section class="ttw-action-box">
                        <h3><i class="feather-check-circle"></i> Clearance record</h3>
                        <dl class="ttw-facts">
                            <div class="ttw-fact"><dt>STEP reference</dt><dd>{{ $item->step_reference ?: 'Not recorded' }}</dd></div>
                            <div class="ttw-fact"><dt>Decision reference</dt><dd>{{ $item->no_objection_reference ?: 'Not recorded' }}</dd></div>
                            <div class="ttw-fact"><dt>Decision date</dt><dd>{{ $item->no_objection_date?->format('d M Y') ?: 'Not recorded' }}</dd></div>
                        </dl>
                        @if($item->no_objection_notes)<p class="ttw-review-note mt-2">{{ $item->no_objection_notes }}</p>@endif
                    </section>
                @endif

                @unless($canReviewNow || $canRecordNoObjection)
                    @if(!in_array($item->status, ['no_objection_obtained', 'published'], true))
                        <div class="ttw-permission-note"><i class="feather-info"></i><span>No transition is available for this item at its current status or under your assigned permissions. The worksheet remains available for authorized review.</span></div>
                    @endif
                @endunless
            </aside>
        </div>
    </main>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var reviewForm = document.querySelector('[data-review-form]');
    if (reviewForm) {
        var reason = reviewForm.querySelector('[name="reason"]');
        reason.addEventListener('input', function () { reason.setCustomValidity(''); });
        reviewForm.addEventListener('submit', function (event) {
            var decision = event.submitter ? event.submitter.value : '';
            if (decision === 'approve' || reason.value.trim()) return;
            event.preventDefault();
            reason.setCustomValidity('Give the Think Tank a clear reason for returning or rejecting this item.');
            reason.reportValidity();
        });
    }

    var form = document.querySelector('[data-wb-decision-form]');
    if (!form) return;
    var reference = form.querySelector('[data-wb-reference]');
    var documentInput = form.querySelector('[data-wb-document]');
    var clearEvidenceError = function () { reference.setCustomValidity(''); };
    reference.addEventListener('input', clearEvidenceError);
    documentInput.addEventListener('change', clearEvidenceError);
    form.addEventListener('submit', function (event) {
        if (reference.value.trim() || documentInput.files.length) return;
        event.preventDefault();
        reference.setCustomValidity('Enter the World Bank decision reference or attach the formal decision document.');
        reference.reportValidity();
    });
});
</script>
@endpush
