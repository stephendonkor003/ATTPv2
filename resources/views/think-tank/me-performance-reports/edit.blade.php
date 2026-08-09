@php
    $editable = $canManage && $report->isEditable();
    $portalEditParams = array_merge(['report' => $report], $portalRouteParams);
@endphp

<x-think-tank.partials.shell :member="$member" :title="'Performance Report · '.$report->periodLabel()">

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
                        @php $indicator=$result->indicator; $actual=old('indicator_results.'.$result->id.'.actual_value',$result->actual_value); $actualText=old('indicator_results.'.$result->id.'.actual_text',$result->actual_text); $rollupNumerator=old('indicator_results.'.$result->id.'.rollup_numerator',$result->rollup_numerator); $rollupDenominator=old('indicator_results.'.$result->id.'.rollup_denominator',$result->rollup_denominator); @endphp
                        <article class="rp-indicator" data-indicator data-target="{{ $result->target_value }}">
                            <span class="rp-code">{{ $indicator?->indicator_code }} · {{ $result->reporting_frequency }}</span>
                            <h3>{{ $indicator?->name }}</h3>
                            <div class="rp-result">
                                <div class="rp-cell"><small>Selected period</small><strong>{{ $indicator?->value_type === 'milestone' ? ($result->actual_text ?: 'Pending') : ($result->actual_value !== null ? number_format((float)$result->actual_value,2) : 'Pending') }}</strong></div>
                                <div class="rp-cell"><small>Cumulative year</small><strong>{{ $result->cumulative_year_result !== null ? number_format((float)$result->cumulative_year_result,2) : 'Pending' }}</strong></div>
                                <div class="rp-cell"><small>From baseline</small><strong>{{ $result->cumulative_programme_result !== null ? number_format((float)$result->cumulative_programme_result,2) : 'Pending' }}</strong></div>
                                <div class="rp-cell"><small>Annual target</small><strong>{{ $result->annual_target !== null ? number_format((float)$result->annual_target,2) : 'Not set' }}</strong></div>
                                <div class="rp-cell"><small>Life target</small><strong>{{ $result->life_of_programme_target !== null ? number_format((float)$result->life_of_programme_target,2) : 'Not set' }}</strong></div>
                                <div class="rp-cell"><small>Target achieved</small><strong>{{ $result->target_achievement_percent !== null ? number_format((float)$result->target_achievement_percent,1).'%' : 'Pending' }}</strong></div>
                                @if($indicator?->value_type === 'milestone')
                                    <div class="rp-cell"><small>Milestone status</small><textarea name="indicator_results[{{ $result->id }}][actual_text]" rows="3" @disabled(!$editable)>{{ $actualText }}</textarea></div>
                                @else
                                    <div class="rp-cell"><small>Actual result</small><input type="number" step="any" name="indicator_results[{{ $result->id }}][actual_value]" value="{{ $actual }}" data-actual @disabled(!$editable)></div>
                                @endif
                                <div class="rp-cell"><small>Period progress</small><strong data-progress>{{ $result->progress_percent !== null ? number_format((float)$result->progress_percent,1).'%' : 'Pending' }}</strong></div>
                                @if($indicator?->organization_rollup_method === 'weighted_average')
                                    <div class="rp-cell"><small>Weighted numerator</small><input type="number" step="any" min="0" name="indicator_results[{{ $result->id }}][rollup_numerator]" value="{{ $rollupNumerator }}" @disabled(!$editable)></div>
                                    <div class="rp-cell"><small>Weighted denominator</small><input type="number" step="any" min="0.0001" name="indicator_results[{{ $result->id }}][rollup_denominator]" value="{{ $rollupDenominator }}" @disabled(!$editable)></div>
                                @endif
                            </div>
                            <div class="rp-mov"><i class="feather-layers me-1"></i>Time aggregation: {{ \App\Models\Indicator::AGGREGATION_METHODS[$result->aggregation_method] ?? 'Latest reported value' }} &middot; Organization roll-up: {{ \App\Models\Indicator::ORGANIZATION_ROLLUP_METHODS[$indicator?->organization_rollup_method] ?? 'Sum' }}</div>
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

        @include('me.performance-reports.partials.achievement-tracker')

        @if($editable)
            @foreach($report->documents as $document)<form id="delete-{{ $document->id }}" method="POST" action="{{ route('think-tank.performance-reports.documents.destroy',array_merge(['report'=>$report,'document'=>$document],$portalRouteParams)) }}" class="d-none">@csrf @method('DELETE')</form>@endforeach
            @if($report->documents->isNotEmpty())
                <section class="rp-section">
                    <div class="rp-section-head"><span class="rp-number"><i class="feather-layers"></i></span><div><h2>Document revisions</h2><p>Upload a corrected version while retaining every earlier file.</p></div></div>
                    <div class="rp-body d-grid gap-3">
                        @foreach($report->documents as $document)
                            <div class="border rounded-3 p-3">
                                <strong>{{ $document->document_name }} - current version {{ $document->repositoryItem?->version_number ?: 1 }}</strong>
                                @if($document->repositoryItem?->versions?->isNotEmpty())<details class="small mt-1"><summary class="text-primary">Version history ({{ $document->repositoryItem->versions->count() }})</summary><ol class="mb-2 ps-3">@foreach($document->repositoryItem->versions->sortByDesc('version_number') as $version)<li><a href="{{ route('budget.me.knowledge-evidence.versions.download',[$document->repositoryItem,$version]) }}">v{{ $version->version_number }} - {{ $version->original_filename }}</a>@if($version->change_notes) - {{ $version->change_notes }}@endif</li>@endforeach</ol></details>@endif
                                <form method="POST" action="{{ route('think-tank.performance-reports.documents.replace',array_merge(['report'=>$report,'document'=>$document],$portalRouteParams)) }}" enctype="multipart/form-data" class="row g-2 align-items-end mt-1">@csrf<div class="col-md-5"><label class="small">Corrected file</label><input type="file" name="replacement_file" class="form-control" required></div><div class="col-md-5"><label class="small">What changed? *</label><input name="change_notes" class="form-control" required maxlength="5000" placeholder="Explain corrections made"></div><div class="col-md-2"><button class="btn btn-outline-primary w-100">Upload v{{ ((int)($document->repositoryItem?->version_number ?: 1))+1 }}</button></div></form>
                                <div class="text-end mt-2"><button type="submit" form="delete-{{ $document->id }}" class="btn btn-sm btn-outline-danger" onclick="return confirm('Unlink this attachment? Version history remains in the repository.')"><i class="feather-trash-2 me-1"></i>Unlink</button></div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        @endif

        @if(!$editable && $report->documents->isNotEmpty())
            <section class="rp-section"><div class="rp-section-head"><span class="rp-number"><i class="feather-layers"></i></span><div><h2>Document version history</h2><p>Every submitted and corrected evidence file remains available.</p></div></div><div class="rp-body d-grid gap-3">@foreach($report->documents as $document)<div><strong>{{ $document->document_name }}</strong> · current version {{ $document->repositoryItem?->version_number ?: 1 }}@if($document->repositoryItem?->versions?->isNotEmpty())<ol class="small mb-0 mt-1">@foreach($document->repositoryItem->versions->sortByDesc('version_number') as $version)<li><a href="{{ route('budget.me.knowledge-evidence.versions.download',[$document->repositoryItem,$version]) }}">v{{ $version->version_number }} - {{ $version->original_filename }}</a>@if($version->change_notes) - {{ $version->change_notes }}@endif</li>@endforeach</ol>@endif</div>@endforeach</div></section>
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
