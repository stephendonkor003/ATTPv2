@php
    $technicalProposalRounds = $technicalProposalRounds ?? collect();
    $candidateTone = fn (string $status): string => match ($status) {
        'qualified' => 'success',
        'disqualified' => 'danger',
        'late' => 'warning',
        'submitted', 'under_review' => 'primary',
        default => 'secondary',
    };
    $technicalProposalService = app(\App\Services\EoiTechnicalProposalService::class);
@endphp

<section class="tp-workspace" id="technicalProposalWorkspace" aria-labelledby="technicalProposalWorkspaceTitle">
    <header class="tp-workspace__header">
        <div class="tp-workspace__heading">
            <span class="tp-workspace__icon"><i class="feather-briefcase" aria-hidden="true"></i></span>
            <div>
                <span class="eoi-eyebrow">Post-qualification workflow</span>
                <h5 id="technicalProposalWorkspaceTitle">Technical Proposal Workspace</h5>
                <p>Rules, templates, applicant submissions, receipt channels, lateness, and review decisions are kept together as an auditable journey.</p>
            </div>
        </div>
        <a href="#eoiCommunicationsTitle" class="btn btn-outline-success btn-sm"><i class="feather-mail me-1"></i> Create invitation round</a>
    </header>

    @forelse ($technicalProposalRounds as $proposalRound)
        @php
            $roundCandidates = $proposalRound->candidates;
            $submittedCandidates = $roundCandidates->filter(fn ($candidate) => $candidate->submissions->isNotEmpty());
            $roundTimezone = $proposalRound->timezone ?: config('app.timezone', 'UTC');
        @endphp
        <article class="tp-round">
            <header class="tp-round__header">
                <div>
                    <span class="tp-round__eyebrow">Round {{ $proposalRound->round_number }}</span>
                    <h6>{{ $proposalRound->title }}</h6>
                    <p>{{ $proposalRound->instructions ?: 'Qualified applicants were invited to submit their technical proposals.' }}</p>
                </div>
                <div class="tp-round__status">
                    <span class="badge bg-{{ $proposalRound->status === 'published' ? 'success' : ($proposalRound->status === 'closed' ? 'dark' : 'secondary') }}">{{ str($proposalRound->status)->headline() }}</span>
                    <small>{{ $proposalRound->published_at ? 'Published '.$proposalRound->published_at->format('d M Y, H:i') : 'Draft configuration' }}</small>
                </div>
            </header>

            <div class="tp-round__facts">
                <div><span><i class="feather-clock"></i></span><small>Deadline</small><strong>{{ $proposalRound->deadline_at ? $proposalRound->deadline_at->timezone($roundTimezone)->format('d M Y, H:i').' '.$roundTimezone : 'No deadline' }}</strong></div>
                <div><span><i class="feather-list"></i></span><small>Rules</small><strong>{{ number_format($proposalRound->rules->count()) }}</strong></div>
                <div><span><i class="feather-file-text"></i></span><small>Templates</small><strong>{{ number_format($proposalRound->templates->count()) }}</strong></div>
                <div><span><i class="feather-users"></i></span><small>Enrolled</small><strong>{{ number_format($roundCandidates->count()) }}</strong></div>
                <div><span><i class="feather-inbox"></i></span><small>Responded</small><strong>{{ number_format($submittedCandidates->count()) }}</strong></div>
            </div>

            <div class="tp-round__policy">
                @foreach (['Portal' => $proposalRound->portal_requirement, 'Email' => $proposalRound->email_requirement, 'Physical' => $proposalRound->physical_requirement] as $channel => $requirement)
                    <span class="is-{{ $requirement }}"><b>{{ $channel }}</b> {{ str($requirement)->replace('_', ' ') }}</span>
                @endforeach
                <span><b>Late policy</b> {{ str($proposalRound->late_policy)->replace('_', ' ') }}</span>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-lg-6">
                    <details class="tp-config-card" open>
                        <summary><span><i class="feather-shield"></i> Rules &amp; regulations</span><b>{{ $proposalRound->rules->count() }}</b></summary>
                        <ol class="tp-rule-register">
                            @forelse ($proposalRound->rules as $rule)
                                <li>
                                    <div><strong>{{ $rule->title }}</strong><small>{{ str($rule->category)->headline() }} @if($rule->is_mandatory)&middot; Mandatory @endif @if($rule->is_disqualifying)&middot; Can disqualify @endif</small></div>
                                    @if ($rule->description)<p>{{ $rule->description }}</p>@endif
                                </li>
                            @empty
                                <li class="text-muted">No rules were recorded for this legacy round.</li>
                            @endforelse
                        </ol>
                    </details>
                </div>
                <div class="col-lg-6">
                    <details class="tp-config-card" open>
                        <summary><span><i class="feather-paperclip"></i> Proposal templates</span><b>{{ $proposalRound->templates->count() }}</b></summary>
                        <div class="tp-template-list">
                            @forelse ($proposalRound->templates as $template)
                                <a href="{{ route('reports.evaluations.eoi.technical-proposals.templates.download', [$procurement, $proposalRound, $template]) }}">
                                    <span><i class="feather-file"></i></span>
                                    <div><strong>{{ $template->original_filename }}</strong><small>{{ strtoupper($template->extension) }} &middot; {{ number_format($template->file_size / 1024, 0) }} KB</small></div>
                                    <i class="feather-download"></i>
                                </a>
                            @empty
                                <p class="text-muted small mb-0">No separate templates were uploaded for this round.</p>
                            @endforelse
                        </div>
                    </details>
                </div>
            </div>

            <div class="tp-candidates">
                <div class="tp-candidates__heading">
                    <div><span class="eoi-eyebrow">Qualified applicant register</span><h6>Proposal receipt &amp; compliance review</h6></div>
                    <span>{{ $submittedCandidates->count() }} of {{ $roundCandidates->count() }} responded</span>
                </div>

                @forelse ($roundCandidates as $candidate)
                    @php
                        $candidateName = $candidate->applicant?->display_name ?: $candidate->user?->name ?: 'Qualified applicant';
                        $latestSubmission = $candidate->submissions->sortByDesc('revision_number')->first();
                        $activeFindings = $candidate->ruleApplications
                            ->whereNull('revoked_at')
                            ->filter(fn ($finding) => ! $finding->proposal_submission_id || (string) $finding->proposal_submission_id === (string) $latestSubmission?->getKey())
                            ->keyBy('rule_id');
                        $missingChannels = $technicalProposalService->missingRequiredChannels($candidate);
                        $prohibitedChannels = $technicalProposalService->prohibitedReceivedChannels($candidate);
                    @endphp
                    <details class="tp-candidate" id="technical-proposal-candidate-{{ $candidate->id }}">
                        <summary>
                            <span class="tp-candidate__avatar">{{ str($candidateName)->substr(0, 1)->upper() }}</span>
                            <span class="tp-candidate__identity"><strong>{{ $candidateName }}</strong><small>{{ $candidate->applicant?->procurement_submission_code ?: 'No submission code' }} &middot; {{ $candidate->eoi_outcome_label }}</small></span>
                            <span class="tp-candidate__receipt"><b>{{ $candidate->submissions->count() }} revision(s)</b><small>{{ $candidate->last_submitted_at ? 'Last received '.$candidate->last_submitted_at->format('d M Y, H:i') : 'No proposal received' }}</small></span>
                            <span class="badge bg-{{ $candidateTone($candidate->status) }}">{{ $candidate->status === 'invited' ? 'Awaiting proposal' : str($candidate->status)->headline() }}</span>
                            <i class="feather-chevron-down tp-candidate__chevron"></i>
                        </summary>
                        <div class="tp-candidate__body">
                            @if ($latestSubmission?->is_late || $missingChannels !== [] || $prohibitedChannels !== [])
                                <div class="tp-compliance-alert">
                                    <i class="feather-alert-triangle"></i>
                                    <div>
                                        <strong>Compliance attention required</strong>
                                        @if ($latestSubmission?->is_late)<span>Latest receipt is {{ number_format($latestSubmission->minutes_late) }} minute(s) after the deadline.</span>@endif
                                        @if ($missingChannels !== [])<span>Missing required channel: {{ collect($missingChannels)->map(fn ($channel) => str($channel)->headline())->implode(', ') }}.</span>@endif
                                        @if ($prohibitedChannels !== [])<span>Proposal recorded through a prohibited channel: {{ collect($prohibitedChannels)->map(fn ($channel) => str($channel)->headline())->implode(', ') }}. Apply the channel rule and record the decision.</span>@endif
                                    </div>
                                </div>
                            @endif
                            @can('evaluations.manage')
                                <div class="row g-4">
                                    <div class="col-xl-5">
                                        <div class="tp-action-card">
                                            <span class="eoi-eyebrow">Admin capture</span>
                                            <h6>Upload on applicant’s behalf</h6>
                                            <p>Use this for proposals received by email, physical delivery, or courier. Record the original receipt time—the system calculates lateness.</p>
                                            <form method="POST" enctype="multipart/form-data" action="{{ route('reports.evaluations.eoi.technical-proposals.capture', [$procurement, $proposalRound, $candidate]) }}">
                                                @csrf
                                                <div class="row g-2">
                                                    <div class="col-sm-5">
                                                        <label class="form-label">Received via</label>
                                                        <select name="received_via" class="form-select" required>
                                                            @foreach (['email' => 'Email', 'physical' => 'Physical copy', 'courier' => 'Courier', 'other' => 'Other'] as $channelValue => $channelLabel)
                                                                @php $channelRequirement = $technicalProposalService->channelRequirement($proposalRound, $channelValue); @endphp
                                                                <option value="{{ $channelValue }}">{{ $channelLabel }} — {{ str($channelRequirement)->replace('_', ' ')->headline() }}{{ $channelRequirement === 'not_allowed' ? ' (capture for audit)' : '' }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-sm-7"><label class="form-label">Actual receipt time</label><input name="received_at" type="datetime-local" class="form-control" max="{{ now()->format('Y-m-d\TH:i') }}" required></div>
                                                    <div class="col-12"><label class="form-label">Capture note</label><textarea name="capture_note" class="form-control" rows="2" maxlength="5000" required placeholder="For example: Received in the procurement mailbox and registered by…"></textarea></div>
                                                    <div class="col-12"><label class="form-label">Applicant cover note <span class="text-muted">(optional)</span></label><textarea name="cover_note" class="form-control" rows="2" maxlength="5000"></textarea></div>
                                                    <div class="col-12"><label class="form-label">Proposal documents</label><input name="documents[]" type="file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.odt,.ods,.odp,.jpg,.jpeg,.png" multiple required><small>Up to 20 files; safe business-document formats only.</small></div>
                                                </div>
                                                <button type="submit" class="btn btn-outline-success w-100 mt-3"><i class="feather-upload-cloud me-1"></i> Capture proposal revision</button>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="col-xl-7">
                                        <div class="tp-action-card">
                                            <span class="eoi-eyebrow">Rule application</span>
                                            <h6>Compliance review</h6>
                                            @if ($latestSubmission && $proposalRound->rules->isNotEmpty())
                                                <form method="POST" action="{{ route('reports.evaluations.eoi.technical-proposals.review', [$procurement, $proposalRound, $candidate]) }}">
                                                    @csrf
                                                    <div class="tp-review-rules">
                                                        @foreach ($proposalRound->rules as $rule)
                                                            @php $currentFinding = $activeFindings->get($rule->id); @endphp
                                                            <div class="tp-review-rule">
                                                                <div><strong>{{ $loop->iteration }}. {{ $rule->title }}</strong><small>{{ $rule->is_disqualifying ? 'Disqualifying rule' : 'Advisory / non-disqualifying rule' }}</small></div>
                                                                <select name="findings[{{ $rule->id }}][finding]" class="form-select form-select-sm" required>
                                                                    @foreach (['compliant' => 'Compliant', 'non_compliant' => 'Non-compliant', 'waived' => 'Waived', 'not_applicable' => 'Not applicable'] as $value => $label)
                                                                        <option value="{{ $value }}" @selected(($currentFinding?->finding ?? 'compliant') === $value)>{{ $label }}</option>
                                                                    @endforeach
                                                                </select>
                                                                <select name="findings[{{ $rule->id }}][effect]" class="form-select form-select-sm">
                                                                    <option value="none" @selected(($currentFinding?->effect ?? 'none') === 'none')>No disqualification</option>
                                                                    @if ($rule->is_disqualifying)<option value="disqualify" @selected($currentFinding?->effect === 'disqualify')>Disqualify applicant</option>@endif
                                                                </select>
                                                                <textarea name="findings[{{ $rule->id }}][rationale]" class="form-control form-control-sm" rows="2" maxlength="10000" placeholder="Required for non-compliance, waiver, or disqualification">{{ $currentFinding?->rationale }}</textarea>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    <button class="btn btn-success w-100 mt-3" type="submit"><i class="feather-shield me-1"></i> Save compliance decision</button>
                                                </form>
                                            @elseif (! $latestSubmission)
                                                <div class="alert alert-light border mb-0">Receive at least one proposal revision before applying rules.</div>
                                            @else
                                                <div class="alert alert-warning mb-0">This round has no configured rules to review.</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endcan

                            <div class="tp-revisions">
                                <h6>Submission history</h6>
                                @forelse ($candidate->submissions->sortByDesc('revision_number') as $proposalSubmission)
                                    <article>
                                        <div class="tp-revision__head">
                                            <span>Revision {{ $proposalSubmission->revision_number }}</span>
                                            <div>
                                                <b>{{ str($proposalSubmission->received_via)->headline() }}</b>
                                                @if ($proposalSubmission->is_late)<span class="badge bg-warning text-dark">{{ number_format($proposalSubmission->minutes_late) }} min late</span>@else<span class="badge bg-success">On time</span>@endif
                                            </div>
                                        </div>
                                        <p>Received {{ $proposalSubmission->received_at?->format('d M Y, H:i') }} &middot; {{ $proposalSubmission->source === 'admin_capture' ? 'Captured by '.($proposalSubmission->capturer?->name ?: 'administrator') : 'Submitted in vendor portal' }}</p>
                                        @if ($proposalSubmission->capture_note)<div class="tp-revision__note"><b>Capture record:</b> {{ $proposalSubmission->capture_note }}</div>@endif
                                        <div class="tp-revision__files">
                                            @foreach ($proposalSubmission->documents as $document)
                                                <a href="{{ route('reports.evaluations.eoi.technical-proposals.documents.download', [$procurement, $proposalRound, $candidate, $proposalSubmission, $document]) }}"><i class="feather-download"></i> {{ $document->original_filename }}</a>
                                            @endforeach
                                        </div>
                                    </article>
                                @empty
                                    <p class="text-muted small mb-0">No proposal documents have been registered yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </details>
                @empty
                    <div class="tp-empty"><i class="feather-user-x"></i><strong>No candidates were enrolled</strong><span>The round may have been created when no panel-complete qualified applicant had an eligible account.</span></div>
                @endforelse
            </div>
        </article>
    @empty
        <div class="tp-empty tp-empty--round">
            <span><i class="feather-clipboard"></i></span>
            <div><strong>No technical proposal round yet</strong><p>Use “Compose invitation” above to define the deadline, channels, rules, and templates before notifying Qualified Applicants.</p></div>
        </div>
    @endforelse
</section>

@push('styles')
<style>
    .tp-workspace{background:#f7faf8;border:1px solid #dce7e1;border-radius:18px;margin:24px 0;padding:22px}.tp-workspace__header,.tp-workspace__heading,.tp-round__header,.tp-candidates__heading{align-items:center;display:flex;justify-content:space-between;gap:16px}.tp-workspace__heading{justify-content:flex-start}.tp-workspace__icon{align-items:center;background:#e1f4e9;border-radius:13px;color:#087443;display:inline-flex;font-size:22px;height:50px;justify-content:center;width:50px}.tp-workspace h5,.tp-workspace h6{margin:2px 0 3px}.tp-workspace p{color:#667085;margin:0}.tp-round{background:#fff;border:1px solid #dce7e1;border-radius:15px;margin-top:18px;overflow:hidden}.tp-round__header{border-bottom:1px solid #e6ece9;padding:18px 20px}.tp-round__header>div:first-child{max-width:780px}.tp-round__eyebrow{color:#087443;font-size:10px;font-weight:800;letter-spacing:.09em;text-transform:uppercase}.tp-round__status{text-align:right}.tp-round__status small{color:#667085;display:block;font-size:10px;margin-top:5px}.tp-round__facts{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:1px;background:#e6ece9}.tp-round__facts>div{background:#fbfdfc;display:grid;grid-template-columns:auto 1fr;column-gap:9px;padding:13px 16px}.tp-round__facts span{align-items:center;background:#eaf6ef;border-radius:8px;color:#087443;display:flex;grid-row:1/3;height:34px;justify-content:center;width:34px}.tp-round__facts small{color:#667085;font-size:9px;text-transform:uppercase}.tp-round__facts strong{font-size:12px}.tp-round__policy{display:flex;flex-wrap:wrap;gap:7px;padding:14px 20px 0}.tp-round__policy span{background:#f2f5f3;border-radius:999px;color:#475467;font-size:10px;padding:6px 9px}.tp-round__policy .is-required{background:#e7f7ee;color:#087443}.tp-round__policy .is-not_allowed{background:#fff0f0;color:#b42318}.tp-round>.row{padding:0 20px 18px}.tp-config-card{border:1px solid #e1e9e5;border-radius:11px;height:100%;overflow:hidden}.tp-config-card summary{align-items:center;background:#f7faf8;cursor:pointer;display:flex;justify-content:space-between;padding:12px 14px}.tp-config-card summary span{font-size:12px;font-weight:700}.tp-config-card summary b{background:#e4eee8;border-radius:999px;font-size:10px;padding:3px 8px}.tp-rule-register{margin:0;padding:8px 16px 10px 42px}.tp-rule-register li{padding:7px 0}.tp-rule-register strong,.tp-rule-register small{display:block}.tp-rule-register small{color:#667085;font-size:9px}.tp-rule-register p{font-size:10px;margin-top:3px}.tp-template-list{display:grid;gap:6px;padding:10px}.tp-template-list a{align-items:center;border:1px solid #e5ebe8;border-radius:8px;color:#344054;display:grid;gap:8px;grid-template-columns:auto 1fr auto;padding:8px 10px;text-decoration:none}.tp-template-list a>span{align-items:center;background:#eaf6ef;border-radius:7px;color:#087443;display:flex;height:30px;justify-content:center;width:30px}.tp-template-list strong,.tp-template-list small{display:block;font-size:10px}.tp-template-list small{color:#667085;font-size:9px}.tp-candidates{border-top:1px solid #e6ece9;padding:18px 20px 20px}.tp-candidates__heading>span{background:#eaf6ef;border-radius:999px;color:#087443;font-size:10px;font-weight:700;padding:6px 10px}.tp-candidate{border:1px solid #dfe8e3;border-radius:11px;margin-top:9px;overflow:hidden}.tp-candidate>summary{align-items:center;cursor:pointer;display:grid;gap:10px;grid-template-columns:auto minmax(0,1.4fr) minmax(150px,.8fr) auto auto;padding:12px 14px}.tp-candidate[open]>summary{background:#f7fbf9;border-bottom:1px solid #e3ebe7}.tp-candidate__avatar{align-items:center;background:#dff3e8;border-radius:50%;color:#087443;display:flex;font-weight:800;height:38px;justify-content:center;width:38px}.tp-candidate__identity strong,.tp-candidate__identity small,.tp-candidate__receipt b,.tp-candidate__receipt small{display:block}.tp-candidate__identity small,.tp-candidate__receipt small{color:#667085;font-size:9px}.tp-candidate__receipt{font-size:10px}.tp-candidate__chevron{transition:transform .2s}.tp-candidate[open] .tp-candidate__chevron{transform:rotate(180deg)}.tp-candidate__body{padding:16px}.tp-compliance-alert{align-items:flex-start;background:#fff8e7;border:1px solid #f0d89a;border-radius:9px;color:#7a4b00;display:flex;gap:9px;margin-bottom:13px;padding:10px 12px}.tp-compliance-alert>i{font-size:18px}.tp-compliance-alert strong,.tp-compliance-alert span{display:block}.tp-compliance-alert strong{font-size:10px}.tp-compliance-alert span{font-size:9px}.tp-action-card{background:#fbfdfc;border:1px solid #e1e9e5;border-radius:11px;height:100%;padding:14px}.tp-action-card>p{font-size:10px;margin-bottom:12px}.tp-action-card .form-label{font-size:10px;font-weight:700;margin-bottom:3px}.tp-action-card small{color:#667085;font-size:9px}.tp-review-rules{display:grid;gap:8px}.tp-review-rule{align-items:start;border-bottom:1px solid #e6ece9;display:grid;gap:7px;grid-template-columns:minmax(160px,1fr) 130px 150px;overflow:hidden;padding-bottom:8px}.tp-review-rule textarea{grid-column:1/-1}.tp-review-rule strong,.tp-review-rule small{display:block}.tp-review-rule strong{font-size:10px}.tp-review-rule small{color:#667085;font-size:8px}.tp-revisions{border-top:1px solid #e3ebe7;margin-top:16px;padding-top:14px}.tp-revisions>article{background:#f8faf9;border-radius:9px;margin-top:7px;padding:11px}.tp-revision__head{align-items:center;display:flex;justify-content:space-between;font-size:10px}.tp-revision__head>span{font-weight:800}.tp-revision__head b{margin-right:6px}.tp-revisions article>p{font-size:9px;margin:3px 0}.tp-revision__note{background:#fff;border-left:2px solid #087443;font-size:9px;margin:7px 0;padding:6px 8px}.tp-revision__files{display:flex;flex-wrap:wrap;gap:5px}.tp-revision__files a{background:#fff;border:1px solid #dde6e1;border-radius:6px;color:#087443;font-size:9px;padding:5px 7px;text-decoration:none}.tp-empty{align-items:center;color:#667085;display:flex;flex-direction:column;gap:5px;padding:25px;text-align:center}.tp-empty--round{background:#fff;border:1px dashed #bfcfc6;border-radius:13px;flex-direction:row;margin-top:16px;text-align:left}.tp-empty--round>span{align-items:center;background:#eaf6ef;border-radius:10px;color:#087443;display:flex;font-size:20px;height:44px;justify-content:center;width:44px}.tp-empty--round p{font-size:11px}.eoi-round-config,.eoi-rules-builder{background:#f8faf9;border:1px solid #dfe8e3;border-radius:12px;padding:14px}.eoi-round-config__head,.eoi-rules-builder__head{align-items:start;display:flex;justify-content:space-between;margin-bottom:12px}.eoi-round-config__head>span{background:#e6f3eb;border-radius:999px;color:#087443;font-size:9px;padding:5px 8px}.eoi-rules-builder__head p{font-size:10px}.eoi-rule-list{display:grid;gap:8px}.eoi-rule-row{align-items:start;background:#fff;border:1px solid #e0e8e4;border-radius:10px;display:grid;gap:9px;grid-template-columns:auto minmax(0,1fr) auto;padding:10px}.eoi-rule-row__number{align-items:center;background:#e5f4eb;border-radius:50%;color:#087443;display:flex;font-size:10px;font-weight:800;height:26px;justify-content:center;width:26px}.eoi-rule-row__fields{display:grid;gap:6px}.eoi-rule-row__options{align-items:center;display:flex;flex-wrap:wrap;gap:12px}.eoi-rule-row__options select{max-width:160px}.eoi-rule-row__options label{font-size:10px}.eoi-rule-remove{background:none;border:0;color:#b42318;padding:5px}@media(max-width:991.98px){.tp-round__facts{grid-template-columns:repeat(2,1fr)}.tp-candidate>summary{grid-template-columns:auto 1fr auto}.tp-candidate__receipt{grid-column:2}.tp-review-rule{grid-template-columns:1fr}.tp-review-rule textarea{grid-column:auto}}@media(max-width:575.98px){.tp-workspace{padding:14px}.tp-workspace__header,.tp-round__header{align-items:flex-start;flex-direction:column}.tp-round__facts{grid-template-columns:1fr}.tp-candidate>summary{grid-template-columns:auto 1fr}.tp-candidate>summary>.badge,.tp-candidate__chevron{grid-column:2}.eoi-rule-row__options{align-items:flex-start;flex-direction:column}}
</style>
@endpush
