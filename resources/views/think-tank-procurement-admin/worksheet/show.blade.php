@extends('layouts.app')
@section('title', $item->item_code.' Procurement Worksheet')
@include('think-tank-procurement-admin._styles')
@include('think-tank-procurement-admin.worksheet._styles')

@section('content')
@php
    $hasCompleteNoObjectionEvidence = $item->hasCompleteNoObjectionEvidence();
    $readyToExecute = $item->isReadyToExecute();
    $statusLabels = [
        'draft' => 'Draft at Think Tank',
        'submitted' => 'Submitted to AUC-ATTP',
        'revision_requested' => 'Returned for correction',
        'rejected' => 'Rejected',
        'approved' => 'Pending World Bank no-objection',
        'no_objection_obtained' => 'STEP Cleared / ready to execute',
        'published' => 'Execution underway / published',
    ];
    $memberName = $plan->member?->name ?: 'Think Tank not set';
    $planApproved = $plan->status === 'approved';
    $canReviewNow = $permissions['review'] && $item->status === 'submitted' && in_array($plan->status, ['submitted', 'revision_requested'], true);
    $canRecordNoObjection = $permissions['step'] && (
        ($item->status === 'approved' && $planApproved)
        || in_array($item->status, ['no_objection_obtained', 'published'], true)
    );
    $canSyncStepStatus = (bool) ($permissions['step'] ?? false);
    $hasExecutionRecord = filled($item->procurement_id) || $item->status === 'published';
    $plannedMilestones = collect($item->planned_milestones ?? [])->filter(fn ($row) => is_array($row));
    $sourcePayload = is_array($item->source_payload) ? $item->source_payload : [];
    $importedExcelActivityStatus = $item->importedActivityStatus();
    $currentStepActivityStatus = $item->currentStepActivityStatus();
    $sourceRow = (array) data_get($sourcePayload, 'source.row', []);
    $sourceCells = collect(data_get($sourceRow, 'cells', []))->filter(fn ($cell) => is_array($cell));
    $sourceHeaders = collect(data_get($sourcePayload, 'source.header.cells', []));
    $sourceSubheaders = collect(data_get($sourcePayload, 'source.subheader.cells', []));
    $sourceReviewFlags = collect(data_get($sourcePayload, 'review_flags', []))->filter(fn ($flag) => is_array($flag));
    $sourceProvenance = (array) data_get($sourcePayload, 'provenance.selected', []);
    $payloadValue = static function (mixed $value): string {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    };
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
                            <div class="ttw-fact"><dt>Imported Excel Activity Status</dt><dd>{{ $importedExcelActivityStatus ?: 'Not supplied in Excel' }}</dd></div>
                            <div class="ttw-fact"><dt>Current STEP Activity Status</dt><dd>{{ $currentStepActivityStatus ?: 'Not synchronized' }}</dd></div>
                            <div class="ttw-fact"><dt>Current system status</dt><dd>{{ $statusLabels[$item->status] ?? Str::headline($item->status) }}</dd></div>
                            <div class="ttw-fact"><dt>In process</dt><dd>{{ $item->source_in_process ?: 'Not set' }}</dd></div>
                        </dl>

                        @if($item->limited_selection_justification || $item->budget_reference || $item->bank_comment || $item->action_taken)
                            <div class="ttw-subhead"><h3>Workbook notes and controls</h3><span>Imported Excel evidence · read-only</span></div>
                            <dl class="ttw-facts">
                                @if($item->limited_selection_justification)<div class="ttw-fact"><dt>Limited selection justification</dt><dd>{{ $item->limited_selection_justification }}</dd></div>@endif
                                @if($item->budget_reference)<div class="ttw-fact"><dt>Budget reference</dt><dd>{{ $item->budget_reference }}</dd></div>@endif
                                @if($item->bank_comment)<div class="ttw-fact"><dt>World Bank comment (imported)</dt><dd>{{ $item->bank_comment }}</dd></div>@endif
                                @if($item->action_taken)<div class="ttw-fact"><dt>AUC comment / action taken (imported)</dt><dd>{{ $item->action_taken }}</dd></div>@endif
                            </dl>
                            <div class="ttw-source-lock"><i class="feather-lock"></i><span>These workbook values are source evidence. STEP synchronization appends a separate audited comment and never rewrites them.</span></div>
                        @endif

                        <div class="ttw-subhead"><h3>Complete imported Excel source row</h3><span>Read-only payload · {{ $sourceCells->count() }} preserved cell(s)</span></div>
                        <div class="ttw-source-meta">
                            <span><strong>Workbook</strong>{{ $item->source_file ?: data_get($sourceProvenance, 'source_file', 'Not recorded') }}</span>
                            <span><strong>Sheet</strong>{{ $item->source_sheet ?: data_get($sourceProvenance, 'source_sheet', 'Not recorded') }}</span>
                            <span><strong>Row</strong>{{ $item->source_row ?: data_get($sourceProvenance, 'source_row', data_get($sourceRow, 'row_number', 'Not recorded')) }}</span>
                            <span><strong>Source path</strong>{{ data_get($sourceProvenance, 'source_path', 'Not recorded') }}</span>
                        </div>
                        @if($sourceCells->isNotEmpty())
                            <div class="ttw-source-grid" aria-label="Complete imported Excel row payload">
                                @foreach($sourceCells as $column => $cell)
                                    @php
                                        $header = data_get($sourceHeaders->get($column), 'formatted');
                                        $subheader = data_get($sourceSubheaders->get($column), 'formatted');
                                        $cellLabel = collect([$header, $subheader])->filter(fn ($value) => filled($value))->unique()->implode(' / ');
                                        $cellComment = (array) data_get($cell, 'comment', []);
                                    @endphp
                                    <article class="ttw-source-cell">
                                        <header><strong>{{ data_get($cell, 'coordinate', $column.($sourceRow['row_number'] ?? '')) }}</strong><span>{{ $cellLabel ?: 'Unlabelled workbook column '.$column }}</span></header>
                                        <div class="ttw-source-value">{{ $payloadValue(data_get($cell, 'formatted')) }}</div>
                                        <dl>
                                            <div><dt>Raw</dt><dd>{{ $payloadValue(data_get($cell, 'raw')) }}</dd></div>
                                            <div><dt>Formula</dt><dd>{{ $payloadValue(data_get($cell, 'formula')) }}</dd></div>
                                            <div><dt>Cached</dt><dd>{{ $payloadValue(data_get($cell, 'cached')) }}</dd></div>
                                            <div><dt>Excel type</dt><dd>{{ $payloadValue(data_get($cell, 'data_type')) }}</dd></div>
                                        </dl>
                                        @if(filled(data_get($cellComment, 'text')))
                                            <div class="ttw-source-comment">
                                                <strong><i class="feather-message-square"></i> Imported Excel {{ data_get($cellComment, 'type') === 'threaded' ? 'threaded comment' : 'note' }}</strong>
                                                <p>{{ data_get($cellComment, 'text') }}</p>
                                                @if(filled(data_get($cellComment, 'author')))<small>Author: {{ data_get($cellComment, 'author') }}</small>@endif
                                            </div>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <div class="ttw-permission-note"><i class="feather-alert-circle"></i><span>No cell-level source payload is attached to this record. The canonical imported fields above remain available.</span></div>
                        @endif

                        <div class="ttw-subhead"><h3>Import review flags</h3><span>{{ $sourceReviewFlags->count() }} flag(s) retained with this row</span></div>
                        @forelse($sourceReviewFlags as $flag)
                            <article class="ttw-review-flag">
                                <strong>{{ Str::headline((string) data_get($flag, 'type', data_get($flag, 'source', 'Review flag'))) }}</strong>
                                <pre>{{ json_encode($flag, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) }}</pre>
                            </article>
                        @empty
                            <div class="ttw-permission-note"><i class="feather-check-circle"></i><span>The audited import did not attach a review flag to this source row.</span></div>
                        @endforelse

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
                                        @if(data_get($event->metadata, 'external_activity_status'))<p class="ttw-review-note">STEP Activity Status: {{ data_get($event->metadata, 'external_activity_status') }}</p>@endif
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
                        'no_objection_obtained' => 'STEP is synchronized as Cleared. The World Bank no-objection is recorded and this item is ready to execute.',
                        'published' => 'The cleared item has moved into execution or publication.',
                        'revision_requested' => 'The item is back with the Think Tank for correction and resubmission.',
                        'rejected' => 'The rejection is final for this submission version unless the workflow is reopened.',
                        default => 'The item is still being prepared by the Think Tank.',
                    } }}</p>
                    <div class="ttw-checklist">
                        <span class="ttw-check"><i class="{{ $plan->submitted_at ? 'feather-check-circle' : 'feather-circle' }}"></i> Plan submitted to AUC-ATTP</span>
                        <span class="ttw-check"><i class="{{ $planApproved ? 'feather-check-circle' : 'feather-circle' }}"></i> Full annual plan approved</span>
                        <span class="ttw-check"><i class="{{ $item->step_exported_at ? 'feather-check-circle' : 'feather-circle' }}"></i> STEP export {{ $item->step_exported_at ? 'recorded '.$item->step_exported_at->format('d M Y') : 'not yet recorded' }}</span>
                        <span class="ttw-check"><i class="{{ $hasCompleteNoObjectionEvidence ? 'feather-check-circle' : 'feather-circle' }}"></i> Supplemental formal evidence {{ $hasCompleteNoObjectionEvidence ? 'recorded' : 'not recorded (optional)' }}</span>
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

                @if($canSyncStepStatus)
                    <section class="ttw-action-box">
                        <h3><i class="feather-refresh-cw"></i> Synchronize external STEP status</h3>
                        <p>This records STEP's current Activity Status independently of the imported plan approval workflow. The imported Excel row, World Bank comment, and AUC comment remain unchanged.</p>
                        <form method="POST" action="{{ route('think-tank-procurement.items.step-status', [$plan, $item]) }}" class="ttw-form-stack" data-step-sync-form>
                            @csrf
                            <input type="hidden" name="lock_version" value="{{ $item->portal_lock_version ?: 1 }}">
                            <div>
                                <label class="ttw-label" for="step-activity-status">Confirmed STEP Activity Status</label>
                                <select class="ttw-input" id="step-activity-status" name="step_activity_status" aria-describedby="step-status-help">
                                    <option value="">Keep current STEP status — add comment only</option>
                                    @unless($hasExecutionRecord)
                                        <option value="new" @selected(old('step_activity_status', Str::lower((string) $currentStepActivityStatus)) === 'new')>New</option>
                                        <option value="returned" @selected(old('step_activity_status', Str::lower((string) $currentStepActivityStatus)) === 'returned')>Returned</option>
                                    @endunless
                                    <option value="cleared" @selected(old('step_activity_status', Str::lower((string) $currentStepActivityStatus)) === 'cleared')>Cleared</option>
                                </select>
                                <small id="step-status-help" class="text-muted">Select a status only from confirmed STEP information. Cleared records the World Bank no-objection and makes the item ready to execute; it never invents a decision date, reference, or document.</small>
                            </div>
                            <div>
                                <label class="ttw-label" for="step-sync-comment">Update comment</label>
                                <textarea class="ttw-input" id="step-sync-comment" name="comment" rows="4" minlength="3" maxlength="5000" required placeholder="State what was confirmed in STEP and why this update is being recorded.">{{ old('comment') }}</textarea>
                                <small class="text-muted">Required. The comment is appended to the audit timeline with your identity and cannot overwrite imported comments.</small>
                            </div>
                            <button class="ttw-btn primary" type="submit" onclick="return confirm('Record this STEP update and append the audit comment?')"><i class="feather-save"></i> Record STEP update</button>
                        </form>
                    </section>
                @endif

                @if($canRecordNoObjection)
                    <section class="ttw-action-box">
                        <h3><i class="feather-globe"></i> World Bank no-objection evidence</h3>
                        <p>{{ in_array($item->status, ['no_objection_obtained', 'published'], true) ? 'STEP already records this item as Cleared. Add or update the supplemental formal decision evidence here.' : 'Use the formal World Bank decision. This action records the no-objection and marks the item ready to execute.' }}</p>
                        <form method="POST" action="{{ route('think-tank-procurement.items.no-objection', [$plan, $item]) }}" enctype="multipart/form-data" class="ttw-form-stack" data-wb-decision-form data-existing-evidence="{{ $hasCompleteNoObjectionEvidence ? '1' : '0' }}">
                            @csrf
                            <div><label class="ttw-label" for="step-reference">STEP reference</label><input class="ttw-input" id="step-reference" name="step_reference" value="{{ old('step_reference', $item->step_reference) }}" required></div>
                            <div class="ttw-form-grid">
                                <div><label class="ttw-label" for="decision-date">Decision date</label><input class="ttw-input" id="decision-date" type="date" name="no_objection_date" value="{{ old('no_objection_date', $item->no_objection_date?->toDateString()) }}" max="{{ now()->toDateString() }}" required></div>
                                <div><label class="ttw-label" for="decision-reference">Decision reference <span class="text-muted">reference or document required</span></label><input class="ttw-input" id="decision-reference" name="no_objection_reference" value="{{ old('no_objection_reference', $item->no_objection_reference) }}" data-wb-reference aria-describedby="wb-evidence-help"></div>
                            </div>
                            <div><label class="ttw-label" for="decision-document">Decision document <span class="text-muted">PDF or Word, max 20 MB</span></label><input class="ttw-input" id="decision-document" type="file" name="no_objection_document" accept=".pdf,.doc,.docx" data-wb-document><small id="wb-evidence-help" class="text-muted">Provide the World Bank reference, the formal decision document, or both.</small></div>
                            <div><label class="ttw-label" for="decision-notes">Decision notes</label><textarea class="ttw-input" id="decision-notes" name="no_objection_notes" rows="4" placeholder="Optional context included in the Think Tank notification">{{ old('no_objection_notes', $item->no_objection_notes) }}</textarea></div>
                            <button class="ttw-btn primary" type="submit" onclick="return confirm('Record this World Bank no-objection evidence?')"><i class="feather-check-circle"></i> Record no-objection evidence</button>
                        </form>
                    </section>
                @elseif($item->status === 'approved' && !$planApproved)
                    <div class="ttw-permission-note"><i class="feather-lock"></i><span>World Bank no-objection processing is locked until the complete annual procurement plan is approved.</span></div>
                @endif

                @if(in_array($item->status, ['no_objection_obtained', 'published'], true))
                    <section class="ttw-action-box">
                        <h3><i class="{{ $readyToExecute ? 'feather-check-circle' : 'feather-alert-circle' }}"></i> Clearance record</h3>
                        <p>STEP is recorded as Cleared and the item is ready to execute.</p>
                        @unless($hasCompleteNoObjectionEvidence)
                            <div class="ttw-permission-note evidence-pending"><i class="feather-info"></i><span>The Excel/STEP clearance is authoritative. A dated reference or decision document has not been added as supplemental evidence.</span></div>
                        @endunless
                        <dl class="ttw-facts">
                            <div class="ttw-fact"><dt>STEP reference</dt><dd>{{ $item->step_reference ?: 'Not recorded' }}</dd></div>
                            <div class="ttw-fact"><dt>Decision reference</dt><dd>{{ $item->no_objection_reference ?: 'Not recorded' }}</dd></div>
                            <div class="ttw-fact"><dt>Decision date</dt><dd>{{ $item->no_objection_date?->format('d M Y') ?: 'Not recorded' }}</dd></div>
                        </dl>
                        @if($item->no_objection_notes)<p class="ttw-review-note mt-2">{{ $item->no_objection_notes }}</p>@endif
                    </section>
                @endif

                @unless($canReviewNow || $canRecordNoObjection || $canSyncStepStatus)
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
        if (form.dataset.existingEvidence === '1' || reference.value.trim() || documentInput.files.length) return;
        event.preventDefault();
        reference.setCustomValidity('Enter the World Bank decision reference or attach the formal decision document.');
        reference.reportValidity();
    });
});
</script>
@endpush
