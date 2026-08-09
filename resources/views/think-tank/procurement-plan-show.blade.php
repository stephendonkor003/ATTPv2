@php
    $statusLabels = [
        'draft'=>'Draft','submitted'=>'Under review','revision_requested'=>'Action required',
        'rejected'=>'Rejected','approved'=>'Approved','no_objection_obtained'=>'No-objection obtained','published'=>'Published'
    ];
    $editable = $plan->isEditable();
    $routeParams = fn(array $values=[]) => array_merge($portalRouteParams, $values);
    $torCount = $plan->items->filter(fn($item) => $item->documents->contains('document_type', 'tor'))->count();
    $documentCount = $plan->items->sum(fn($item) => $item->documents->count());
    $completion = $plan->items->isEmpty() ? 0 : (int) round(($torCount / $plan->items->count()) * 100);
@endphp


<x-think-tank.partials.shell :member="$member" :title="$plan->title">
<div class="ttpp">
    <div class="ttpp-breadcrumbs">
        <a class="ttpp-back" href="{{ route('think-tank.procurement-plans', $portalRouteParams) }}"><i class="feather-arrow-left"></i> Annual plans</a>
        <span aria-hidden="true">/</span>
        <span>{{ $plan->plan_code }}</span>
    </div>

    <header class="ttpp-head">
        <div class="ttpp-head-copy">
            <div class="ttpp-eyebrow"><i class="feather-folder" aria-hidden="true"></i> Financial year {{ str_starts_with((string) $plan->fiscal_year, 'FY') ? $plan->fiscal_year : 'FY '.$plan->fiscal_year }}</div>
            <h1>{{ $plan->title }}</h1>
            <p class="ttpp-head-description">{{ $plan->description ?: 'Annual procurement planning workspace for preparing, documenting and submitting procurement activities to ATTP.' }}</p>
            <div class="ttpp-head-meta">
                <span><i class="feather-hash"></i>{{ $plan->plan_code }}</span>
                <span><i class="feather-layers"></i>Version {{ $plan->version }}</span>
                <span><i class="feather-clock"></i>Updated {{ $plan->updated_at?->format('d M Y, H:i') }}</span>
            </div>
        </div>
        <div class="ttpp-head-command">
            <div class="ttpp-head-status">
                <span>Current plan status</span>
                <strong class="ttpp-status {{ $plan->status }}">{{ $statusLabels[$plan->status] ?? Str::headline($plan->status) }}</strong>
            </div>
            <div class="ttpp-head-actions">
                @if($editable)
                    <button class="ttpp-btn" type="button" onclick="document.getElementById('edit-plan-dialog').showModal()"><i class="feather-edit-2"></i> Edit plan</button>
                    <button class="ttpp-btn primary" type="button" data-ttpp-add-item aria-controls="add-item-dialog" onclick="var dialog=document.getElementById('add-item-dialog');if(dialog&&!dialog.open){typeof dialog.showModal==='function'?dialog.showModal():dialog.setAttribute('open','');window.setTimeout(function(){var first=dialog.querySelector('[data-first-item-title]');if(first){first.focus()}},80)}"><i class="feather-plus"></i> Add procurement item</button>
                    <form method="POST" action="{{ route('think-tank.procurement-plans.submit', $routeParams(['plan'=>$plan])) }}" onsubmit="return confirm('Submit this complete plan to ATTP for review? You will not be able to edit it while under review.')">
                        @csrf
                        <button class="ttpp-btn is-submit" type="submit" @disabled($plan->items->isEmpty())><i class="feather-send"></i> {{ $plan->submitted_at ? 'Resubmit plan' : 'Submit for review' }}</button>
                    </form>
                @else
                    <p class="ttpp-lock-note"><i class="feather-lock"></i> Editing is unavailable while this plan is in the review workflow.</p>
                @endif
            </div>
        </div>
    </header>

    @if(in_array($plan->status,['revision_requested','rejected']) && ($plan->decision_reason || $plan->review_notes))
        <div class="ttpp-alert"><i class="feather-alert-triangle"></i><div><strong>ATTP Procurement Officer action note</strong><p>{{ $plan->decision_reason ?: $plan->review_notes }}</p></div></div>
    @endif

    @php
        $stage = match($plan->status){'draft','revision_requested','rejected'=>1,'submitted'=>2,'approved'=>3,default=>1};
        if($plan->items->whereIn('status',['no_objection_obtained','published'])->isNotEmpty()) $stage=4;
    @endphp
    <section class="ttpp-overview" aria-label="Plan overview">
        <div class="ttpp-flow-wrap">
            <div class="ttpp-section-heading">
                <div><span class="ttpp-section-kicker">Workflow</span><h2>Plan progress</h2></div>
                <span>Stage {{ $stage }} of 4</span>
            </div>
            <div class="ttpp-flow">
                @foreach([1=>['Plan & documents','Prepare items and TORs'],2=>['ATTP review','Institutional review'],3=>['STEP & World Bank','No-objection process'],4=>['Execution','Publish and evaluate']] as $number=>$step)
                    <div class="ttpp-step {{ $number<$stage?'done':($number===$stage?'current':'') }}">
                        <b>@if($number<$stage)<i class="feather-check"></i>@else{{ $number }}@endif</b>
                        <span><strong>{{ $step[0] }}</strong><small>{{ $step[1] }}</small></span>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="ttpp-metrics">
            <article class="ttpp-metric"><span class="ttpp-metric-icon"><i class="feather-package"></i></span><div><strong>{{ $itemStats['total'] }}</strong><span>Procurement items</span></div></article>
            <article class="ttpp-metric is-value"><span class="ttpp-metric-icon"><i class="feather-dollar-sign"></i></span><div><strong>{{ $plan->currency }} {{ number_format($itemStats['budget'], 2) }}</strong><span>Total estimated value</span></div></article>
            <article class="ttpp-metric"><span class="ttpp-metric-icon"><i class="feather-paperclip"></i></span><div><strong>{{ $documentCount }}</strong><span>Attached documents</span></div></article>
            <article class="ttpp-metric"><span class="ttpp-metric-icon"><i class="feather-check-circle"></i></span><div><strong>{{ $completion }}%</strong><span>Items with TOR</span></div></article>
        </div>
    </section>

    <section class="ttpp-panel">
        <div class="ttpp-panel-head ttpp-register-head">
            <div><span class="ttpp-section-kicker">Procurement register</span><h2>Plan items</h2><p>Review budget, scheduling, documents and decision status for every activity.</p></div>
            <div class="ttpp-register-actions">
                <span class="ttpp-count-badge">{{ $plan->items->count() }} {{ Str::plural('item', $plan->items->count()) }}</span>
                @if($editable)<button class="ttpp-btn primary" type="button" data-ttpp-add-item aria-controls="add-item-dialog" onclick="var dialog=document.getElementById('add-item-dialog');if(dialog&&!dialog.open){typeof dialog.showModal==='function'?dialog.showModal():dialog.setAttribute('open','');window.setTimeout(function(){var first=dialog.querySelector('[data-first-item-title]');if(first){first.focus()}},80)}"><i class="feather-plus"></i> Add item</button>@endif
            </div>
        </div>
        <div class="ttpp-panel-body">
            <div class="ttpp-item-list">
            @forelse($plan->items as $item)
                @php
                    $itemEvaluations=$evaluations->where('procurement_id',$item->procurement_id);
                    $canEdit=$item->isEditable();
                @endphp
                <article class="ttpp-item" id="item-{{ $item->id }}">
                    <div class="ttpp-item-main">
                        <div class="ttpp-item-overview">
                            <span class="ttpp-item-number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <div class="ttpp-item-copy">
                                <div class="ttpp-item-code">{{ $item->item_code }} @if($item->source_reference)&middot; {{ $item->source_reference }}@endif</div>
                                <h3>{{ $item->title }}</h3>
                                <div class="ttpp-item-meta">
                                    <span><i class="feather-tag"></i>{{ Str::headline($item->procurement_category) }}</span>
                                    <span><i class="feather-compass"></i>{{ $item->procurement_method }}</span>
                                    @if($item->planned_quarter)<span><i class="feather-calendar"></i>{{ $item->planned_quarter }}</span>@endif
                                    @if($item->planned_start_date)<span><i class="feather-clock"></i>{{ $item->planned_start_date->format('d M Y') }}@if($item->planned_end_date) &ndash; {{ $item->planned_end_date->format('d M Y') }}@endif</span>@endif
                                </div>
                                @if($item->description)<p class="ttpp-item-description">{{ $item->description }}</p>@endif
                            </div>
                        </div>
                        <div class="ttpp-item-side">
                            <span class="ttpp-item-value-label">Estimated value</span>
                            <div class="ttpp-item-value">{{ $item->currency }} {{ number_format((float)$item->estimated_amount,2) }}</div>
                            <span class="ttpp-status {{ $item->status }}">{{ $item->workflowActivityStatus() }}</span>
                        </div>
                    </div>

                    <div class="ttpp-register-fields" aria-label="Procurement register fields">
                        <div><span>Loan / credit no.</span><strong>{{ $item->loan_credit_no ?: 'Not specified' }}</strong></div>
                        <div><span>Component</span><strong>{{ $item->component ?: 'Not specified' }}</strong></div>
                        <div><span>Review type</span><strong>{{ $item->review_type ?: 'Not specified' }}</strong></div>
                        <div><span>Category</span><strong>{{ Str::headline($item->procurement_category) }}</strong></div>
                        <div><span>Market approach</span><strong>{{ $item->market_approach ?: 'Not specified' }}</strong></div>
                        <div><span>High SEA/SH risk</span><strong>{{ $item->source_sea_sh_risk ?: 'Not specified' }}</strong></div>
                        <div class="is-wide"><span>Procurement document type</span><strong>{{ $item->source_document_type ?: 'Not specified' }}</strong></div>
                        <div><span>Process status</span><strong>{{ $item->source_process_status ?: 'Not specified' }}</strong></div>
                        <div><span>Activity status</span><strong>{{ $item->workflowActivityStatus() }}</strong></div>
                    </div>

                    <div class="ttpp-item-documents">
                        <div class="ttpp-documents-label"><i class="feather-paperclip"></i><span><strong>Documents</strong><small>{{ $item->documents->count() }} attached</small></span></div>
                        <div class="ttpp-docs">
                            @foreach($item->documents as $document)
                                <span class="ttpp-doc {{ $document->document_type }}">
                                    <i class="{{ $document->document_type==='tor'?'feather-file-text':'feather-paperclip' }}"></i>
                                    <a href="{{ route('think-tank.procurement-plans.documents.download',$routeParams(['plan'=>$plan,'item'=>$item,'document'=>$document])) }}">{{ $document->document_name }}</a>
                                    <small>{{ $document->formatted_size }}</small>
                                    @if($canEdit && !($document->document_type==='tor' && $item->documents->where('document_type','tor')->count()<=1))
                                    <form method="POST" action="{{ route('think-tank.procurement-plans.documents.destroy',$routeParams(['plan'=>$plan,'item'=>$item,'document'=>$document])) }}" onsubmit="return confirm('Remove this document?')">@csrf @method('DELETE')<button title="Remove" type="submit">&times;</button></form>
                                    @endif
                                </span>
                            @endforeach
                        </div>
                    </div>

                    @if($item->review_reason)<div class="ttpp-reason"><strong>Review note:</strong> {{ $item->review_reason }}</div>@endif

                    <div class="ttpp-item-tools">
                        @if($canEdit)
                            <button class="ttpp-btn" type="button" onclick="document.getElementById('edit-item-{{ $item->id }}').showModal()"><i class="feather-edit-2"></i> Correct / edit</button>
                            <form method="POST" action="{{ route('think-tank.procurement-plans.items.destroy',$routeParams(['plan'=>$plan,'item'=>$item])) }}" onsubmit="return confirm('Remove {{ $item->item_code }} and all its documents from this plan?')">@csrf @method('DELETE')<button class="ttpp-btn danger" type="submit"><i class="feather-trash-2"></i> Remove</button></form>
                        @endif
                        @if($item->status==='no_objection_obtained' && !$item->procurement_id)
                            <button class="ttpp-btn primary" type="button" onclick="document.getElementById('launch-item-{{ $item->id }}').showModal()"><i class="feather-send"></i> Configure & publish</button>
                        @endif
                        @if($item->procurement)
                            <div class="ttpp-execution">
                                <strong><i class="feather-radio"></i> {{ Str::headline($item->procurement->status) }}</strong>
                                <span>{{ $item->procurement->submissions->where('status', '!=', \App\Models\FormSubmission::STATUS_WITHDRAWN)->count() }} active application(s)</span>
                                @if($item->procurement->status==='published')
                                    <a href="{{ route('public.procurement.show',$item->procurement) }}" target="_blank">Open public notice <i class="feather-external-link"></i></a>
                                    <button class="ttpp-btn danger" type="button" onclick="document.getElementById('recall-item-{{ $item->id }}').showModal()"><i class="feather-rotate-ccw"></i> Recall</button>
                                @elseif($item->procurement->status==='recalled')
                                    <span class="ttpp-recalled-note"><i class="feather-alert-circle"></i> Recalled {{ $item->procurement->recalled_at?->format('d M Y, H:i') }}</span>
                                    <button class="ttpp-btn primary" type="button" onclick="document.getElementById('republish-item-{{ $item->id }}').showModal()"><i class="feather-refresh-cw"></i> Republish</button>
                                @endif
                                <button class="ttpp-btn" type="button" onclick="document.getElementById('evaluation-item-{{ $item->id }}').showModal()"><i class="feather-check-square"></i> Evaluations</button>
                            </div>
                        @endif
                    </div>

                    @if($item->procurement && $itemEvaluations->isNotEmpty())
                    <div class="ttpp-inline-panel">
                        <h4>Evaluation setup</h4>
                        <div class="ttpp-evals">
                            @foreach($itemEvaluations as $evaluation)
                            <div class="ttpp-eval">
                                <h5>{{ Str::headline($evaluation->evaluation_phase) }} &middot; {{ $evaluation->name }}</h5>
                                <p>{{ $evaluation->sections->flatMap->criteria->count() }} criteria &middot; {{ $evaluation->assignments->count() }} assigned team member(s)</p>
                                <form method="POST" action="{{ route('think-tank.procurement-plans.evaluations.assign',$routeParams(['plan'=>$plan,'item'=>$item,'evaluation'=>$evaluation])) }}">
                                    @csrf
                                    <select name="evaluator_ids[]" multiple required aria-label="Evaluation team members">
                                        @foreach($teamMembers as $team)<option value="{{ $team->id }}">{{ $team->name }} — {{ $team->email }}</option>@endforeach
                                    </select>
                                    <button class="ttpp-small-btn mt-2" type="submit"><i class="feather-user-plus"></i> Add to evaluation team</button>
                                </form>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </article>

                @if($canEdit)
                <dialog class="ttpp-dialog ttpp-create-dialog" id="edit-item-{{ $item->id }}" @if(old('_item_form') === 'edit-'.$item->id && $errors->any()) data-auto-open @endif>
                    <div class="ttpp-dialog-head"><h3>Correct {{ $item->item_code }}</h3><button class="ttpp-dialog-close" type="button" onclick="this.closest('dialog').close()">&times;</button></div>
                    <div class="ttpp-dialog-body">
                        <form method="POST" action="{{ route('think-tank.procurement-plans.items.update',$routeParams(['plan'=>$plan,'item'=>$item])) }}" enctype="multipart/form-data">
                            @csrf @method('PUT')
                            <input type="hidden" name="_item_form" value="edit-{{ $item->id }}">
                            @if(old('_item_form') === 'edit-'.$item->id && $errors->any())
                                <div class="ttpp-validation-summary" role="alert"><i class="feather-alert-circle"></i><div><strong>Please review the highlighted information</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
                            @endif
                            @include('think-tank.partials.procurement-item-fields', ['item'=>$item, 'prefix'=>'edit-'.$item->id])
                            <div class="ttpp-form-actions"><button class="ttpp-btn" type="button" onclick="this.closest('dialog').close()">Cancel</button><button class="ttpp-btn primary" type="submit">Save correction</button></div>
                        </form>
                    </div>
                </dialog>
                @endif

                @if($item->status==='no_objection_obtained' && !$item->procurement_id)
                    @include('think-tank.partials.procurement-publication-builder', ['item' => $item])
                @endif

                @if($item->procurement)
                @if($item->procurement->status === 'published')
                <dialog class="ttpp-dialog" id="recall-item-{{ $item->id }}">
                    <div class="ttpp-dialog-head"><h3>Recall published procurement</h3><button class="ttpp-dialog-close" type="button" onclick="this.closest('dialog').close()">&times;</button></div>
                    <div class="ttpp-dialog-body">
                        <div class="ttpp-validation-summary"><i class="feather-alert-triangle"></i><div><strong>Applicants will be notified</strong><p class="mb-0">The public notice will close immediately. Existing applications remain stored and will be returned to vendors for response when you republish.</p></div></div>
                        <form method="POST" action="{{ route('think-tank.procurement-plans.items.recall-publication',$routeParams(['plan'=>$plan,'item'=>$item])) }}" onsubmit="return confirm('Recall this opportunity and notify all applicants?')">
                            @csrf
                            <div class="ttpp-field full"><label>Reason for recall <em>Required</em></label><textarea name="recall_reason" minlength="10" maxlength="2000" required placeholder="Explain clearly why the opportunity is being recalled and what applicants should expect."></textarea><p class="ttpp-help">This note will be included in every applicant email and shown when vendors resubmit.</p></div>
                            <div class="ttpp-form-actions"><button class="ttpp-btn" type="button" onclick="this.closest('dialog').close()">Cancel</button><button class="ttpp-btn danger" type="submit"><i class="feather-rotate-ccw"></i> Recall & notify vendors</button></div>
                        </form>
                    </div>
                </dialog>
                @elseif($item->procurement->status === 'recalled')
                <dialog class="ttpp-dialog" id="republish-item-{{ $item->id }}">
                    <div class="ttpp-dialog-head"><h3>Republish procurement opportunity</h3><button class="ttpp-dialog-close" type="button" onclick="this.closest('dialog').close()">&times;</button></div>
                    <div class="ttpp-dialog-body">
                        <div class="ttpp-recall-summary"><strong>Recall note</strong><p>{{ $item->procurement->recall_reason }}</p><span>{{ $item->procurement->submissions->count() }} previous application(s) will be notified by email.</span></div>
                        <form method="POST" action="{{ route('think-tank.procurement-plans.items.republish',$routeParams(['plan'=>$plan,'item'=>$item])) }}">
                            @csrf
                            <div class="ttpp-form-grid">
                                <div class="ttpp-field wide"><label>Reopens on <em>Required</em></label><input type="date" name="application_start_date" min="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" required></div>
                                <div class="ttpp-field wide"><label>New closing date <em>Required</em></label><input type="date" name="application_end_date" min="{{ now()->toDateString() }}" value="{{ now()->addDays(14)->toDateString() }}" required></div>
                            </div>
                            <p class="ttpp-help mt-2">Vendors with active applications can respond and resubmit without applying again. Vendors who withdrew can submit a new application.</p>
                            <div class="ttpp-form-actions"><button class="ttpp-btn" type="button" onclick="this.closest('dialog').close()">Cancel</button><button class="ttpp-btn primary" type="submit"><i class="feather-refresh-cw"></i> Republish & notify vendors</button></div>
                        </form>
                    </div>
                </dialog>
                @endif

                <dialog class="ttpp-dialog" id="evaluation-item-{{ $item->id }}">
                    <div class="ttpp-dialog-head"><h3>Technical & financial evaluation templates</h3><button class="ttpp-dialog-close" type="button" onclick="this.closest('dialog').close()">&times;</button></div>
                    <div class="ttpp-dialog-body">
                        <form method="POST" action="{{ route('think-tank.procurement-plans.evaluations.store',$routeParams(['plan'=>$plan,'item'=>$item])) }}">
                            @csrf
                            <div class="ttpp-form-grid">
                                <div class="ttpp-field wide"><label>Template name</label><input name="name" placeholder="Technical evaluation — {{ $item->item_code }}" required></div>
                                <div class="ttpp-field"><label>Evaluation phase</label><select name="evaluation_phase" required><option value="technical">Technical</option><option value="financial">Financial</option></select></div>
                                <div class="ttpp-field full"><label>Instructions</label><textarea name="description" placeholder="Guidance for the evaluation panel"></textarea></div>
                            </div>
                            <h4 class="mt-3">Scoring criteria <small>(must total 100)</small></h4>
                            <div class="ttpp-criteria-rows" data-criteria-rows>
                                <div class="ttpp-criteria-row"><input name="criteria[0][name]" placeholder="Criterion" required><input name="criteria[0][description]" placeholder="Scoring guidance"><input type="number" name="criteria[0][max_score]" value="100" min=".01" max="100" step=".01" required><button class="ttpp-small-btn" type="button" data-remove-row>&times;</button></div>
                            </div>
                            <button class="ttpp-small-btn mt-2" type="button" data-add-criterion><i class="feather-plus"></i> Add criterion</button>
                            <div class="ttpp-form-actions"><button class="ttpp-btn" type="button" onclick="this.closest('dialog').close()">Cancel</button><button class="ttpp-btn primary" type="submit">Create evaluation template</button></div>
                        </form>
                    </div>
                </dialog>
                @endif
            @empty
                <div class="ttpp-empty ttpp-empty-register">
                    <span><i class="feather-package"></i></span>
                    <h3>Build your annual procurement plan</h3>
                    <p>Add the first planned activity, its estimated budget and mandatory Terms of Reference.</p>
                    @if($editable)<button class="ttpp-btn primary" type="button" data-ttpp-add-item aria-controls="add-item-dialog" onclick="var dialog=document.getElementById('add-item-dialog');if(dialog&&!dialog.open){typeof dialog.showModal==='function'?dialog.showModal():dialog.setAttribute('open','');window.setTimeout(function(){var first=dialog.querySelector('[data-first-item-title]');if(first){first.focus()}},80)}"><i class="feather-plus"></i> Add the first item</button>@endif
                </div>
            @endforelse
            </div>
        </div>
    </section>

    <details class="ttpp-panel ttpp-audit" @if(request()->has('audit')) open @endif>
        <summary class="ttpp-panel-head">
            <div><span class="ttpp-section-kicker">Accountability</span><h2>Audit trail</h2><p>Who did what, when, and why across this plan.</p></div>
            <span class="ttpp-audit-summary"><strong>{{ $plan->events->count() }}</strong> events <i class="feather-chevron-down"></i></span>
        </summary>
        <div class="ttpp-panel-body">
            <div class="ttpp-timeline">
                @forelse($plan->events as $event)
                    <div class="ttpp-event">
                        <span class="ttpp-event-icon"><i class="feather-activity"></i></span>
                        <div><strong>{{ Str::headline($event->action) }} @if($event->item) &middot; {{ $event->item->item_code }} @endif</strong><p>{{ $event->actor?->name ?? 'System' }}@if($event->reason) &mdash; {{ $event->reason }}@endif</p></div>
                        <time>{{ $event->created_at?->format('d M Y H:i') }}</time>
                    </div>
                @empty
                    <div class="ttpp-empty">The first plan activity will appear here.</div>
                @endforelse
            </div>
        </div>
    </details>
</div>

@if($editable)
<dialog class="ttpp-dialog ttpp-create-dialog" id="add-item-dialog" @if(old('_item_form') === 'create' && $errors->any()) data-auto-open @endif>
    <div class="ttpp-dialog-head ttpp-create-dialog-head">
        <div class="ttpp-dialog-title">
            <span><i class="feather-plus"></i></span>
            <div><small>{{ $plan->plan_code }} &middot; {{ str_starts_with((string) $plan->fiscal_year, 'FY') ? $plan->fiscal_year : 'FY '.$plan->fiscal_year }}</small><h3>Add procurement item</h3><p>Complete the planning details and attach the mandatory TOR.</p></div>
        </div>
        <button class="ttpp-dialog-close" type="button" onclick="var dialog=this.closest('dialog');typeof dialog.close==='function'?dialog.close():dialog.removeAttribute('open')" aria-label="Close item form">&times;</button>
    </div>
    <form class="ttpp-create-form" method="POST" action="{{ route('think-tank.procurement-plans.items.store',$routeParams(['plan'=>$plan])) }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="_item_form" value="create">
        <div class="ttpp-dialog-body">
            @if(old('_item_form') === 'create' && $errors->any())
                <div class="ttpp-validation-summary" role="alert">
                    <i class="feather-alert-circle"></i>
                    <div><strong>Please review the highlighted information</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                </div>
            @endif
            @include('think-tank.partials.procurement-item-fields', ['item'=>null, 'prefix'=>'create'])
        </div>
        <div class="ttpp-dialog-actions">
            <p><i class="feather-shield"></i> The item is saved as a draft and recorded in the audit trail.</p>
            <div><button class="ttpp-btn" type="button" onclick="var dialog=this.closest('dialog');typeof dialog.close==='function'?dialog.close():dialog.removeAttribute('open')">Cancel</button><button class="ttpp-btn primary" type="submit"><i class="feather-plus-circle"></i> Add item to plan</button></div>
        </div>
    </form>
</dialog>
@endif

<dialog class="ttpp-dialog" id="edit-plan-dialog" @if(old('_item_form') === 'plan' && $errors->any()) data-auto-open @endif>
    <div class="ttpp-dialog-head"><h3>Edit annual plan folder</h3><button class="ttpp-dialog-close" type="button" onclick="this.closest('dialog').close()">&times;</button></div>
    <div class="ttpp-dialog-body">
        <form method="POST" action="{{ route('think-tank.procurement-plans.update',$routeParams(['plan'=>$plan])) }}">@csrf @method('PUT')
            <input type="hidden" name="_item_form" value="plan">
            @if(old('_item_form') === 'plan' && $errors->any())
                <div class="ttpp-validation-summary" role="alert"><i class="feather-alert-circle"></i><div><strong>Please review the plan information</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
            @endif
            <div class="ttpp-form-grid">
                <div class="ttpp-field wide"><label>Plan title</label><input name="title" value="{{ old('_item_form') === 'plan' ? old('title', $plan->title) : $plan->title }}" required></div>
                <div class="ttpp-field"><label>Financial year</label><input name="fiscal_year" value="{{ old('_item_form') === 'plan' ? old('fiscal_year', $plan->fiscal_year) : $plan->fiscal_year }}" required></div>
                <div class="ttpp-field"><label>Currency</label><input name="currency" value="{{ old('_item_form') === 'plan' ? old('currency', $plan->currency) : $plan->currency }}" required></div>
                <div class="ttpp-field full"><label>Planning note</label><textarea name="description">{{ old('_item_form') === 'plan' ? old('description', $plan->description) : $plan->description }}</textarea></div>
            </div>
            <div class="ttpp-form-actions"><button class="ttpp-btn" type="button" onclick="this.closest('dialog').close()">Cancel</button><button class="ttpp-btn primary" type="submit">Save plan</button></div>
        </form>
    </div>
</dialog>
<script>
window.openProcurementItemDialog = () => {
    const dialog = document.getElementById('add-item-dialog');
    if (!dialog || dialog.open) return;
    if (typeof dialog.showModal === 'function') {
        dialog.showModal();
    } else {
        dialog.setAttribute('open', '');
        dialog.classList.add('is-dialog-fallback');
    }
    window.setTimeout(() => dialog.querySelector('[data-first-item-title]')?.focus(), 80);
};

const automaticDialog = document.querySelector('.ttpp-dialog[data-auto-open]');
if (automaticDialog && !automaticDialog.open) {
    typeof automaticDialog.showModal === 'function'
        ? automaticDialog.showModal()
        : automaticDialog.setAttribute('open', '');
}

document.querySelectorAll('[data-publication-builder]').forEach(function (builder) {
    const rows = builder.querySelector('[data-publication-questions]');
    const template = builder.querySelector('[data-publication-question-template]');
    const emptyState = builder.querySelector('[data-publication-empty]');
    const addButton = builder.querySelector('[data-add-publication-question]');
    const initialNode = builder.querySelector('[data-publication-initial]');

    if (!rows || !template || !addButton) return;

    const updateQuestion = function (row) {
        const type = row.querySelector('[data-question-type]')?.value || 'text';
        const optionsTypes = ['select', 'radio', 'multiselect', 'checkbox'];
        const lengthTypes = ['text', 'textarea', 'email', 'tel', 'url'];
        const placeholderTypes = ['text', 'textarea', 'email', 'tel', 'number', 'url'];
        const usesChoices = optionsTypes.includes(type);
        const optionsPanel = row.querySelector('[data-question-options]');
        const optionsInput = row.querySelector('[data-question-options-input]');
        optionsPanel.hidden = !usesChoices;
        optionsInput.required = usesChoices;
        optionsInput.disabled = !usesChoices;
        row.querySelector('[data-question-number-settings]').hidden = type !== 'number';
        row.querySelector('[data-question-length-settings]').hidden = !lengthTypes.includes(type);
        row.querySelector('[data-question-file-settings]').hidden = !['file', 'image'].includes(type);
        row.querySelector('[data-question-placeholder-wrap]').hidden = !placeholderTypes.includes(type);

        const extensionInput = row.querySelector('[name$="[allowed_extensions]"]');
        const fileSizeInput = row.querySelector('[name$="[max_file_size_mb]"]');
        if (type === 'image') {
            extensionInput.placeholder = 'jpg, jpeg, png, webp';
            fileSizeInput.placeholder = '5';
        } else if (type === 'file') {
            extensionInput.placeholder = 'pdf, doc, docx, xlsx';
            fileSizeInput.placeholder = '10';
        }

        const choiceTitles = {
            select: 'Dropdown answers (one answer allowed)',
            radio: 'Radio-button answers (one answer allowed)',
            multiselect: 'Multi-select answers (several answers allowed)',
            checkbox: 'Checkbox answers (several answers allowed)'
        };
        const choiceHelp = {
            select: 'The applicant must choose one answer from this dropdown list.',
            radio: 'Every answer will appear as a radio button and the applicant can choose only one.',
            multiselect: 'The applicant can choose one or several answers from this list.',
            checkbox: 'Every answer will appear as a checkbox and the applicant can tick several.'
        };
        row.querySelector('[data-choice-settings-title]').textContent = choiceTitles[type] || 'Permitted answers';
        row.querySelector('[data-choice-settings-help]').textContent = choiceHelp[type] || 'Applicants will only be able to choose from these answers.';

        const choices = (optionsInput.value || '').split(/[\n,]+/).map(value => value.trim()).filter(Boolean);
        const preview = row.querySelector('[data-choice-preview]');
        const previewValues = row.querySelector('[data-choice-preview-values]');
        preview.hidden = !usesChoices || choices.length === 0;
        previewValues.replaceChildren(...choices.slice(0, 50).map(function (choice) {
            const chip = document.createElement('span');
            chip.textContent = choice;
            return chip;
        }));

        const required = row.querySelector('[data-question-required]')?.checked;
        row.querySelector('[data-required-label]').textContent = required ? 'Required' : 'Optional';
        const label = row.querySelector('[data-question-label]')?.value.trim();
        row.querySelector('[data-question-title]').textContent = label || 'New question';
    };

    const reindex = function () {
        const questionRows = Array.from(rows.querySelectorAll('[data-publication-question]'));
        questionRows.forEach(function (row, index) {
            row.querySelector('[data-question-number]').textContent = index + 1;
            row.querySelectorAll('[name]').forEach(function (input) {
                input.name = input.name.replace(/fields\[(?:__INDEX__|\d+)\]/, 'fields[' + index + ']');
            });
            row.querySelector('[data-question-up]').disabled = index === 0;
            row.querySelector('[data-question-down]').disabled = index === questionRows.length - 1;
        });
        emptyState.hidden = questionRows.length > 0;
        addButton.disabled = questionRows.length >= 30;
    };

    const addQuestion = function (data = {}) {
        if (rows.children.length >= 30) return;
        const fragment = template.content.cloneNode(true);
        const row = fragment.querySelector('[data-publication-question]');
        rows.appendChild(fragment);
        reindex();

        const setValue = function (suffix, value) {
            const input = row.querySelector('[name$="[' + suffix + ']"]');
            if (input && value !== undefined && value !== null) input.value = value;
        };
        setValue('label', data.label || '');
        setValue('type', data.type || 'text');
        setValue('help_text', data.help_text || '');
        setValue('placeholder', data.placeholder || '');
        setValue('options', data.options || '');
        setValue('min', data.min ?? '');
        setValue('max', data.max ?? '');
        setValue('max_length', data.max_length ?? '');
        setValue('allowed_extensions', data.allowed_extensions || '');
        setValue('max_file_size_mb', data.max_file_size_mb ?? '');
        row.querySelector('[data-question-required]').checked = ['1', 1, true, 'true', 'on'].includes(data.required);
        updateQuestion(row);
        row.querySelector('[data-question-label]')?.focus();
    };

    addButton.addEventListener('click', function () { addQuestion(); });
    rows.addEventListener('input', function (event) {
        const row = event.target.closest('[data-publication-question]');
        if (row) updateQuestion(row);
    });
    rows.addEventListener('change', function (event) {
        const row = event.target.closest('[data-publication-question]');
        if (row) updateQuestion(row);
    });
    rows.addEventListener('click', function (event) {
        const row = event.target.closest('[data-publication-question]');
        if (!row) return;
        if (event.target.closest('[data-remove-publication-question]')) row.remove();
        if (event.target.closest('[data-question-up]') && row.previousElementSibling) rows.insertBefore(row, row.previousElementSibling);
        if (event.target.closest('[data-question-down]') && row.nextElementSibling) rows.insertBefore(row.nextElementSibling, row);
        reindex();
    });

    try {
        const initialQuestions = JSON.parse(initialNode?.textContent || '[]');
        initialQuestions.forEach(addQuestion);
    } catch (error) {
        console.warn('The previous application questions could not be restored.', error);
    }
    reindex();

    builder.addEventListener('submit', reindex);
    const coverInput = builder.querySelector('[data-publication-cover]');
    coverInput?.addEventListener('change', function () {
        const file = coverInput.files?.[0];
        const preview = builder.querySelector('[data-cover-preview]');
        if (!file) {
            preview.hidden = true;
            return;
        }
        builder.querySelector('[data-cover-preview-image]').src = URL.createObjectURL(file);
        builder.querySelector('[data-cover-preview-name]').textContent = file.name;
        preview.hidden = false;
    });
});

document.addEventListener('click', function (event) {
    if (event.target.closest('[data-ttpp-add-item]')) {
        window.openProcurementItemDialog();
        return;
    }

    const criterionButton = event.target.closest('[data-add-criterion]');
    if (criterionButton) {
        const rows = criterionButton.parentElement.querySelector('[data-criteria-rows]');
        const index = rows.children.length;
        rows.insertAdjacentHTML('beforeend', '<div class="ttpp-criteria-row"><input name="criteria['+index+'][name]" placeholder="Criterion" required><input name="criteria['+index+'][description]" placeholder="Scoring guidance"><input type="number" name="criteria['+index+'][max_score]" min=".01" max="100" step=".01" required><button class="ttpp-small-btn" type="button" data-remove-row>&times;</button></div>');
    }
    const remove = event.target.closest('[data-remove-row]');
    if (remove && remove.parentElement.parentElement.children.length > 1) remove.parentElement.remove();
});

window.recalculateProcurementAmount = function (source) {
    const builder = source?.matches?.('.ttpp-item-builder') ? source : source?.closest?.('.ttpp-item-builder');
    const quantity = builder?.querySelector('[data-ttpp-quantity]');
    const unitCost = builder?.querySelector('[data-ttpp-unit-cost]');
    const total = builder?.querySelector('[data-ttpp-total]');
    if (!quantity || !unitCost || !total) return;

    total.value = quantity.value !== '' && unitCost.value !== ''
        ? (Number(quantity.value) * Number(unitCost.value)).toFixed(2)
        : '';
};

document.querySelectorAll('.ttpp-item-builder').forEach(window.recalculateProcurementAmount);

document.addEventListener('input', function (event) {
    if (event.target.matches('[data-ttpp-quantity], [data-ttpp-unit-cost]')) {
        window.recalculateProcurementAmount(event.target);
    }
});

document.addEventListener('change', function (event) {
    const input = event.target.closest('[data-ttpp-file]');
    if (!input) return;
    const box = input.closest('.ttpp-upload-box');
    const helper = box?.querySelector('.ttpp-upload-copy small');
    if (!helper) return;
    if (!helper.dataset.defaultText) helper.dataset.defaultText = helper.textContent.trim();
    const files = Array.from(input.files || []);
    helper.textContent = files.length === 0
        ? helper.dataset.defaultText
        : files.length === 1 ? files[0].name : `${files.length} files selected`;
    box.classList.toggle('has-file', files.length > 0);
});
</script>
</x-think-tank.partials.shell>
