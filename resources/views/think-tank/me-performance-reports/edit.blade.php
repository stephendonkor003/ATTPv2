@php
    $editable = $canManage && $report->isEditable();
    $portalEditParams = array_merge(['report' => $report], $portalRouteParams);
@endphp

<x-think-tank.partials.shell :member="$member" :title="'Performance Report · '.$report->periodLabel()">
    <style>
        .tt-preport {
            --rp-green: #176b4b; --rp-deep: #0d4d36; --rp-ink: #1b2c23;
            --rp-muted: #68776e; --rp-line: #dbe6df; max-width: 1180px; margin: 0 auto;
        }
        .tt-preport .rp-head { padding: 1.15rem; border-radius: 12px; color: #fff; background: linear-gradient(120deg,var(--rp-deep),#20815d); }
        .tt-preport .rp-head-top { display:flex; justify-content:space-between; gap:1rem; }
        .tt-preport .rp-head h1 { margin:.55rem 0 .2rem; color:#fff; font-size:1.35rem; font-weight:800; }
        .tt-preport .rp-head p { margin:0; color:rgba(255,255,255,.72); font-size:.78rem; }
        .tt-preport .rp-status { align-self:flex-start; padding:.35rem .6rem; border:1px solid rgba(255,255,255,.28); border-radius:999px; background:rgba(255,255,255,.1); font-size:.66rem; font-weight:800; }
        .tt-preport .rp-meta { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:1px; margin-top:1rem; overflow:hidden; border-radius:8px; background:rgba(255,255,255,.16); }
        .tt-preport .rp-meta div { min-width:0; padding:.65rem; background:rgba(0,0,0,.08); }
        .tt-preport .rp-meta small { display:block; color:rgba(255,255,255,.6); font-size:.58rem; font-weight:800; text-transform:uppercase; }
        .tt-preport .rp-meta strong { display:block; overflow:hidden; color:#fff; font-size:.73rem; text-overflow:ellipsis; white-space:nowrap; }
        .tt-preport .rp-section { margin-top:.8rem; overflow:hidden; border:1px solid var(--rp-line); border-radius:11px; background:#fff; }
        .tt-preport .rp-section-head { display:flex; gap:.65rem; padding:.8rem .9rem; border-bottom:1px solid var(--rp-line); background:#fbfdfc; }
        .tt-preport .rp-number { width:28px; height:28px; display:grid; place-items:center; flex:0 0 auto; border-radius:8px; color:var(--rp-green); background:#eaf5ef; font-size:.72rem; font-weight:850; }
        .tt-preport .rp-section h2 { margin:0; color:var(--rp-ink); font-size:.85rem; font-weight:800; }
        .tt-preport .rp-section-head p { margin:.15rem 0 0; color:var(--rp-muted); font-size:.68rem; }
        .tt-preport .rp-body { padding:.9rem; }
        .tt-preport .rp-indicators { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.7rem; }
        .tt-preport .rp-indicator { padding:.8rem; border:1px solid var(--rp-line); border-radius:9px; background:#fcfefd; }
        .tt-preport .rp-code { color:var(--rp-green); font-size:.62rem; font-weight:850; }
        .tt-preport .rp-indicator h3 { margin:.2rem 0 .6rem; color:var(--rp-ink); font-size:.8rem; line-height:1.4; }
        .tt-preport .rp-result { display:grid; grid-template-columns:repeat(4,1fr); gap:.4rem; }
        .tt-preport .rp-cell { padding:.5rem; border:1px solid #e4ece8; border-radius:7px; background:#fff; }
        .tt-preport .rp-cell small { display:block; color:var(--rp-muted); font-size:.56rem; font-weight:800; text-transform:uppercase; }
        .tt-preport .rp-cell strong { color:var(--rp-ink); font-size:.78rem; }
        .tt-preport .rp-cell input { width:100%; min-height:32px; margin-top:.25rem; border:1px solid #cfddd5; border-radius:6px; padding:.3rem; }
        .tt-preport .rp-mov { margin-top:.55rem; padding-top:.5rem; border-top:1px dashed #d9e4de; color:#50665b; font-size:.66rem; }
        .tt-preport label { color:var(--rp-ink); font-size:.7rem; font-weight:750; }
        .tt-preport textarea, .tt-preport select, .tt-preport input.form-control { border-color:#cfddd5; border-radius:8px; font-size:.76rem; }
        .tt-preport .rp-doc { display:grid; grid-template-columns:1fr auto; gap:.6rem; align-items:center; padding:.6rem; border:1px solid var(--rp-line); border-radius:8px; background:#fbfdfc; }
        .tt-preport .rp-doc strong,.tt-preport .rp-doc small { display:block; }
        .tt-preport .rp-upload { display:grid; grid-template-columns:.8fr 1.2fr auto; gap:.5rem; }
        .tt-preport .rp-actions { position:sticky; bottom:.6rem; z-index:5; display:flex; justify-content:space-between; gap:.7rem; margin-top:.8rem; padding:.7rem; border:1px solid var(--rp-line); border-radius:9px; background:rgba(255,255,255,.96); box-shadow:0 8px 24px rgba(20,60,43,.14); }
        .tt-preport .rp-history { display:grid; gap:.45rem; }
        .tt-preport .rp-history-item { padding:.6rem; border-left:3px solid #9abbaa; background:#f7faf8; font-size:.7rem; }
        .tt-preport .rp-section-head>div { min-width:0; }
        .tt-preport .section-state { display:inline-flex; align-items:center; gap:.3rem; flex:0 0 auto; margin-left:auto; padding:.3rem .5rem; border:1px solid; border-radius:999px; font-size:.63rem; font-weight:850; white-space:nowrap; }
        .tt-preport .section-state small { padding-left:.28rem; border-left:1px solid currentColor; font-size:.56rem; opacity:.75; }
        .tt-preport .section-state--complete { border-color:#a7d7bf; color:#087443; background:#eaf8f0; }
        .tt-preport .section-state--in-progress { border-color:#f1d28c; color:#8a5a00; background:#fff8e7; }
        .tt-preport .section-state--not-started { border-color:#edb8b5; color:#a3312c; background:#fff0ef; }
        .tt-preport .report-completion { margin-top:.8rem; padding:.9rem; border:1px solid var(--rp-line); border-radius:11px; background:#fff; }
        .tt-preport .completion-heading { display:flex; align-items:center; justify-content:space-between; gap:1rem; }
        .tt-preport .completion-eyebrow,.tt-preport .stage-action-kicker { color:var(--rp-green); font-size:.58rem; font-weight:850; letter-spacing:.06em; text-transform:uppercase; }
        .tt-preport .completion-heading h5,.tt-preport .stage-action-copy h5 { margin:.15rem 0; color:var(--rp-ink); font-size:.86rem; font-weight:850; }
        .tt-preport .completion-heading p,.tt-preport .stage-action-copy p { margin:0; color:var(--rp-muted); font-size:.68rem; }
        .tt-preport .completion-score { display:grid; min-width:70px; padding:.5rem; border-radius:9px; color:#8a5a00; background:#fff8e7; text-align:center; }
        .tt-preport .completion-score.is-ready { color:#087443; background:#eaf8f0; }
        .tt-preport .completion-score strong,.tt-preport .completion-score span { line-height:1.1; }
        .tt-preport .completion-score span { margin-top:.15rem; font-size:.55rem; font-weight:800; text-transform:uppercase; }
        .tt-preport .completion-progress { height:6px; margin-top:.7rem; overflow:hidden; border-radius:999px; background:#f1e0df; }
        .tt-preport .completion-progress span { display:block; height:100%; border-radius:inherit; background:linear-gradient(90deg,#d8941d,#15935d); }
        .tt-preport .completion-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.45rem; margin-top:.7rem; }
        .tt-preport .completion-item { display:flex; align-items:flex-start; gap:.45rem; min-width:0; padding:.5rem; border:1px solid; border-radius:8px; color:inherit; text-decoration:none; }
        .tt-preport .completion-item>span { display:grid; place-items:center; flex:0 0 auto; width:1.4rem; height:1.4rem; border-radius:6px; color:#fff; background:currentColor; font-size:.6rem; font-weight:850; }
        .tt-preport .completion-item div { min-width:0; }
        .tt-preport .completion-item strong,.tt-preport .completion-item small { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .tt-preport .completion-item strong { font-size:.62rem; }
        .tt-preport .completion-item small { margin-top:.1rem; font-size:.56rem; }
        .tt-preport .completion-item--complete { border-color:#b9dfca; color:#087443; background:#f2fbf6; }
        .tt-preport .completion-item--in-progress { border-color:#f1d28c; color:#8a5a00; background:#fffaf0; }
        .tt-preport .completion-item--not-started { border-color:#edc3c0; color:#a3312c; background:#fff5f4; }
        .tt-preport .report-stage-actions { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:1rem; align-items:center; margin-top:.8rem; padding:.9rem; border:1px solid #bad5e7; border-left:5px solid #1676b8; border-radius:11px; background:linear-gradient(135deg,#f8fcff,#edf7fd); }
        .tt-preport .report-stage-actions--submitted { border-color:#efd294; border-left-color:#d8941d; background:linear-gradient(135deg,#fffdf8,#fff7e5); }
        .tt-preport .report-stage-actions--reviewed { border-color:#abd7bf; border-left-color:#15935d; background:linear-gradient(135deg,#f7fdf9,#eaf8f0); }
        .tt-preport .report-stage-actions--archived { border-color:#cbd2d8; border-left-color:#3e4a53; background:linear-gradient(135deg,#fbfcfc,#eef1f3); }
        .tt-preport .stage-review-form { display:grid; gap:.45rem; }
        .tt-preport .stage-action-buttons { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:.5rem; }
        .tt-preport .lifecycle-action { display:inline-flex; align-items:center; justify-content:center; gap:.4rem; min-height:40px; padding-right:.9rem; padding-left:.9rem; font-weight:800; }
        .tt-preport .lifecycle-action:disabled { cursor:not-allowed; opacity:.48; }
        .tt-preport .stage-locked { display:inline-flex; align-items:center; justify-content:center; gap:.4rem; padding:.6rem .75rem; border:1px solid #ced7d3; border-radius:8px; color:#52635c; background:rgba(255,255,255,.7); font-size:.68rem; font-weight:800; }
        @media(max-width:760px){.tt-preport .rp-meta,.tt-preport .rp-indicators,.tt-preport .completion-grid{grid-template-columns:1fr 1fr}.tt-preport .rp-upload{grid-template-columns:1fr}.tt-preport .rp-actions{align-items:stretch;flex-direction:column}.tt-preport .report-stage-actions{grid-template-columns:1fr}}
        @media(max-width:500px){.tt-preport .rp-meta,.tt-preport .rp-indicators,.tt-preport .rp-result{grid-template-columns:1fr}}
    </style>

    <div class="tt-preport">
        <header class="rp-head">
            <div class="rp-head-top">
                <div>
                    <a href="{{ route('think-tank.performance-reports.index', $portalRouteParams) }}" class="text-white-50 text-decoration-none small"><i class="feather-arrow-left me-1"></i>Performance reports</a>
                    <h1>{{ $report->form?->title }}</h1>
                    <p>{{ $report->periodLabel() }} · {{ $member->name }}</p>
                </div>
                <span class="rp-status">{{ $report->lifecycleLabel() }}</span>
            </div>
            <div class="rp-meta">
                <div><small>Form</small><strong>{{ $report->form?->code }}</strong></div>
                <div><small>Component</small><strong>{{ $report->projectComponent?->name }}</strong></div>
                <div><small>Directorate</small><strong>{{ $report->responsibleDirectorate?->name ?: 'Not assigned' }}</strong></div>
                <div><small>Period</small><strong>{{ $report->reportingPeriod?->label }}</strong></div>
            </div>
        </header>

        @if (session('success'))<div class="alert alert-success mt-3 mb-0">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger mt-3 mb-0"><ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @if ($report->isEditable() && $report->review_notes)
            <div class="alert alert-warning mt-3 mb-0"><strong>Returned for correction:</strong> {{ $report->review_notes }}</div>
        @endif
        @unless($editable)
            <div class="alert alert-light border mt-3 mb-0"><i class="feather-lock me-1"></i>This {{ strtolower($report->lifecycleLabel()) }} is read-only. Only draft reports may be edited.</div>
        @endunless

        @include('me.performance-reports.partials.completion-summary')

        <form method="POST" action="{{ route('think-tank.performance-reports.update', $portalEditParams) }}" enctype="multipart/form-data">
            @csrf @method('PUT')

            <section class="rp-section" id="report-section-1">
                <div class="rp-section-head"><span class="rp-number">1</span><div><h2>Indicator results and progress against target</h2><p>Enter results for indicators due under their approved reporting frequency.</p></div>@include('me.performance-reports.partials.section-status', ['section' => $sectionCompletion['indicator_results']])</div>
                <div class="rp-body"><div class="rp-indicators">
                    @foreach($report->indicatorResults as $result)
                        @php $indicator=$result->indicator; $actual=old('indicator_results.'.$result->id.'.actual_value',$result->actual_value); @endphp
                        <article class="rp-indicator" data-indicator data-target="{{ $result->target_value }}">
                            <span class="rp-code">{{ $indicator?->indicator_code }} · {{ $result->reporting_frequency }}</span>
                            <h3>{{ $indicator?->name }}</h3>
                            <div class="rp-result">
                                <div class="rp-cell"><small>Selected period</small><strong>{{ $result->actual_value !== null ? number_format((float)$result->actual_value,2) : 'Pending' }}</strong></div>
                                <div class="rp-cell"><small>Cumulative year</small><strong>{{ $result->cumulative_year_result !== null ? number_format((float)$result->cumulative_year_result,2) : 'Pending' }}</strong></div>
                                <div class="rp-cell"><small>From baseline</small><strong>{{ $result->cumulative_programme_result !== null ? number_format((float)$result->cumulative_programme_result,2) : 'Pending' }}</strong></div>
                                <div class="rp-cell"><small>Annual target</small><strong>{{ $result->annual_target !== null ? number_format((float)$result->annual_target,2) : 'Not set' }}</strong></div>
                                <div class="rp-cell"><small>Life target</small><strong>{{ $result->life_of_programme_target !== null ? number_format((float)$result->life_of_programme_target,2) : 'Not set' }}</strong></div>
                                <div class="rp-cell"><small>Target achieved</small><strong>{{ $result->target_achievement_percent !== null ? number_format((float)$result->target_achievement_percent,1).'%' : 'Pending' }}</strong></div>
                                <div class="rp-cell"><small>Actual result</small><input type="number" step="any" name="indicator_results[{{ $result->id }}][actual_value]" value="{{ $actual }}" data-actual @disabled(!$editable)></div>
                                <div class="rp-cell"><small>Period progress</small><strong data-progress>{{ $result->progress_percent !== null ? number_format((float)$result->progress_percent,1).'%' : 'Pending' }}</strong></div>
                            </div>
                            <div class="rp-mov"><i class="feather-layers me-1"></i>Aggregation: {{ \App\Models\Indicator::AGGREGATION_METHODS[$result->aggregation_method] ?? 'Latest reported value' }}</div>
                            <div class="rp-mov"><i class="feather-archive me-1"></i>Means of Verification: {{ $indicator?->meansOfVerification?->title ?: 'No repository MOV linked' }}</div>
                        </article>
                    @endforeach
                </div></div>
            </section>

            <section class="rp-section" id="report-section-2">
                <div class="rp-section-head"><span class="rp-number">2</span><div><h2>Achievements and variance</h2><p>Summarise delivery and explain variance from targets.</p></div>@include('me.performance-reports.partials.section-status', ['section' => $sectionCompletion['achievements_variance']])</div>
                <div class="rp-body"><div class="row g-3">
                    <div class="col-md-6"><label for="achievements">Key achievements</label><textarea id="achievements" name="key_achievements" rows="5" class="form-control" @disabled(!$editable)>{{ old('key_achievements',$report->key_achievements) }}</textarea></div>
                    <div class="col-md-6"><label for="variance">Explanation of variance from targets</label><textarea id="variance" name="variance_explanation" rows="5" class="form-control" @disabled(!$editable)>{{ old('variance_explanation',$report->variance_explanation) }}</textarea></div>
                </div></div>
            </section>

            <section class="rp-section" id="report-section-3">
                <div class="rp-section-head"><span class="rp-number">3</span><div><h2>Means of Verification and supporting documents</h2><p>Describe and attach the evidence supporting the reported results.</p></div>@include('me.performance-reports.partials.section-status', ['section' => $sectionCompletion['means_of_verification']])</div>
                <div class="rp-body">
                    <label for="mov-notes">Means of Verification (MOV) notes</label>
                    <textarea id="mov-notes" name="means_of_verification_notes" rows="4" class="form-control" @disabled(!$editable)>{{ old('means_of_verification_notes',$report->means_of_verification_notes) }}</textarea>
                    @if($report->documents->isNotEmpty())
                        <div class="d-grid gap-2 mt-3">@foreach($report->documents as $document)
                            <div class="rp-doc"><div><strong>{{ $document->document_name }}</strong><small class="text-muted">{{ $document->original_filename }} · {{ $document->formattedSize() }}</small></div><a href="{{ route('think-tank.performance-reports.documents.download', array_merge(['report'=>$report,'document'=>$document],$portalRouteParams)) }}" class="btn btn-sm btn-light border"><i class="feather-download"></i></a></div>
                        @endforeach</div>
                    @endif
                    @if($editable)
                        <div class="mt-3 d-flex justify-content-between"><label>Additional attachments</label><button type="button" class="btn btn-sm btn-light border" data-add-document><i class="feather-plus me-1"></i>Add</button></div>
                        <div class="d-grid gap-2" data-document-list><div class="rp-upload" data-document-row><input name="document_names[]" class="form-control" placeholder="Document name"><input type="file" name="documents[]" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.jpg,.jpeg,.png,.zip"><button type="button" class="btn btn-light border" data-remove-document><i class="feather-x"></i></button></div></div>
                    @endif
                </div>
            </section>

            <section class="rp-section" id="report-section-4">
                <div class="rp-section-head"><span class="rp-number">4</span><div><h2>Overall assessment, performance rating and conclusion</h2><p>Provide the evidence-based management assessment.</p></div>@include('me.performance-reports.partials.section-status', ['section' => $sectionCompletion['overall_assessment']])</div>
                <div class="rp-body"><div class="row g-3">
                    <div class="col-md-8"><label for="assessment">Overall assessment</label><textarea id="assessment" name="overall_assessment" rows="5" class="form-control" @disabled(!$editable)>{{ old('overall_assessment',$report->overall_assessment) }}</textarea></div>
                    <div class="col-md-4"><label for="rating">Performance rating</label><select id="rating" name="performance_rating" class="form-select" @disabled(!$editable)><option value="">Select rating</option>@foreach($performanceRatings as $value=>$label)<option value="{{ $value }}" @selected(old('performance_rating',$report->performance_rating)===$value)>{{ $label }}</option>@endforeach</select></div>
                    <div class="col-12"><label for="conclusion">Conclusion</label><textarea id="conclusion" name="conclusion" rows="4" class="form-control" @disabled(!$editable)>{{ old('conclusion',$report->conclusion) }}</textarea></div>
                </div></div>
            </section>

            <section class="rp-section" id="report-section-5">
                <div class="rp-section-head"><span class="rp-number">5</span><div><h2>Challenges and mitigation strategies</h2><p>Record constraints and the response taken.</p></div>@include('me.performance-reports.partials.section-status', ['section' => $sectionCompletion['challenges_mitigation']])</div>
                <div class="rp-body"><div class="row g-3">
                    <div class="col-md-6"><label for="challenges">Challenges faced</label><textarea id="challenges" name="challenges_faced" rows="5" class="form-control" @disabled(!$editable)>{{ old('challenges_faced',$report->challenges_faced) }}</textarea></div>
                    <div class="col-md-6"><label for="mitigation">Mitigation strategies</label><textarea id="mitigation" name="mitigation_strategies" rows="5" class="form-control" @disabled(!$editable)>{{ old('mitigation_strategies',$report->mitigation_strategies) }}</textarea></div>
                </div></div>
            </section>

            <section class="rp-section" id="report-section-6">
                <div class="rp-section-head"><span class="rp-number">6</span><div><h2>Lessons learned and adaptive management</h2><p>State lessons and the implementation adjustments they trigger.</p></div>@include('me.performance-reports.partials.section-status', ['section' => $sectionCompletion['lessons_adaptive_management']])</div>
                <div class="rp-body"><div class="row g-3">
                    <div class="col-md-6"><label for="lessons">Lessons learned</label><textarea id="lessons" name="lessons_learned" rows="5" class="form-control" @disabled(!$editable)>{{ old('lessons_learned',$report->lessons_learned) }}</textarea></div>
                    <div class="col-md-6"><label for="adaptive">Adaptive management actions</label><textarea id="adaptive" name="adaptive_management_actions" rows="5" class="form-control" @disabled(!$editable)>{{ old('adaptive_management_actions',$report->adaptive_management_actions) }}</textarea></div>
                </div></div>
            </section>

            <section class="rp-section" id="report-section-7">
                <div class="rp-section-head"><span class="rp-number">7</span><div><h2>Priorities or plans for the next reporting period</h2><p>Set out the next period’s delivery priorities.</p></div>@include('me.performance-reports.partials.section-status', ['section' => $sectionCompletion['next_period_priorities']])</div>
                <div class="rp-body"><label for="priorities">Next reporting-period priorities or plans</label><textarea id="priorities" name="next_period_priorities" rows="5" class="form-control" @disabled(!$editable)>{{ old('next_period_priorities',$report->next_period_priorities) }}</textarea></div>
            </section>

            @if($editable)<div class="rp-actions"><span class="text-muted small"><i class="feather-info me-1"></i>Save changes before submitting.</span><button class="btn btn-success fw-bold" type="submit"><i class="feather-save me-1"></i>Save Draft</button></div>@endif
        </form>

        @if($editable)
            @foreach($report->documents as $document)<form id="delete-{{ $document->id }}" method="POST" action="{{ route('think-tank.performance-reports.documents.destroy',array_merge(['report'=>$report,'document'=>$document],$portalRouteParams)) }}" class="d-none">@csrf @method('DELETE')</form>@endforeach
            @if($report->documents->isNotEmpty())<section class="rp-section"><div class="rp-body d-flex flex-wrap justify-content-between gap-2"><span class="text-muted small">Remove an incorrect attachment before replacing it.</span><div class="d-flex flex-wrap gap-1">@foreach($report->documents as $document)<button type="submit" form="delete-{{ $document->id }}" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this attachment?')"><i class="feather-trash-2 me-1"></i>{{ \Illuminate\Support\Str::limit($document->document_name,20) }}</button>@endforeach</div></div></section>@endif
        @endif

        @include('me.performance-reports.partials.lifecycle-actions', ['isPortal' => true])

        @if($report->review_notes)<section class="rp-section"><div class="rp-section-head"><span class="rp-number"><i class="feather-message-square"></i></span><div><h2>Secretariat/M&amp;E review notes</h2><p>{{ $report->reviewedBy?->name }} · {{ optional($report->reviewed_at)->format('d M Y, H:i') }}</p></div></div><div class="rp-body">{{ $report->review_notes }}</div></section>@endif
        @if($report->isArchived())<section class="rp-section"><div class="rp-section-head"><span class="rp-number"><i class="feather-archive"></i></span><div><h2>Historical record</h2><p>Archived {{ optional($report->archived_at)->format('d M Y, H:i') }} by {{ $report->archivedBy?->name ?: 'the Secretariat' }}.</p></div></div>@if($report->archive_notes)<div class="rp-body">{{ $report->archive_notes }}</div>@endif</section>@endif

        @if($report->transitions->isNotEmpty())<section class="rp-section"><div class="rp-section-head"><span class="rp-number"><i class="feather-clock"></i></span><div><h2>Lifecycle history</h2><p>Controlled audit trail for this report.</p></div></div><div class="rp-body rp-history">@foreach($report->transitions as $transition)<div class="rp-history-item"><strong>{{ \Illuminate\Support\Str::headline($transition->action) }}</strong> · {{ $transition->actor?->name ?: 'System' }} · {{ $transition->created_at?->format('d M Y, H:i') }}@if($transition->notes)<div class="text-muted mt-1">{{ $transition->notes }}</div>@endif</div>@endforeach</div></section>@endif
    </div>

    <script>
        (() => {
            document.querySelectorAll('[data-indicator]').forEach(card => {
                const input=card.querySelector('[data-actual]'), output=card.querySelector('[data-progress]');
                input?.addEventListener('input',()=>{const t=Number(card.dataset.target),a=Number(input.value);output.textContent=card.dataset.target!==''&&t!==0&&input.value!==''?((a/t)*100).toFixed(1)+'%':'Pending';});
            });
            const list=document.querySelector('[data-document-list]');
            const bind=button=>button.addEventListener('click',()=>{const rows=list?.querySelectorAll('[data-document-row]')||[];if(rows.length===1){button.closest('[data-document-row]')?.querySelectorAll('input').forEach(i=>i.value='');}else button.closest('[data-document-row]')?.remove();});
            list?.querySelectorAll('[data-remove-document]').forEach(bind);
            document.querySelector('[data-add-document]')?.addEventListener('click',()=>{const rows=list?.querySelectorAll('[data-document-row]')||[];if(!list||!rows.length||rows.length>=10)return;const clone=rows[0].cloneNode(true);clone.querySelectorAll('input').forEach(i=>i.value='');bind(clone.querySelector('[data-remove-document]'));list.appendChild(clone);});
        })();
    </script>
</x-think-tank.partials.shell>
