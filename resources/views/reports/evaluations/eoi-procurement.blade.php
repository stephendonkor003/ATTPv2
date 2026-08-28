@extends('layouts.app')

@section('title', 'EOI Qualification Report')

@section('content')
    @php
        $errors = $errors ?? new \Illuminate\Support\ViewErrorBag();
        $procurement = $report['procurement'];
        $stats = $report['stats'];
        $applicants = $report['applicants'];
        $generatedAt = $report['generated_at'];
        $procurementTitle = $procurement->title ?: 'Untitled procurement';
        $procurementReference = $procurement->reference_no ?: 'No reference number';
        $qualifiedApplicants = $applicants
            ->filter(fn (array $row): bool => (bool) ($row['can_advance'] ?? false)
                && (bool) ($row['panel_complete'] ?? false)
                && in_array(data_get($row, 'outcome.code'), ['fully_qualified', 'average_qualified'], true))
            ->values();
        $panelInProgressApplicants = $applicants
            ->filter(fn (array $row): bool => ! (bool) ($row['panel_complete'] ?? false))
            ->values();
        $notQualifiedApplicants = $applicants
            ->where('outcome.code', 'not_qualified')
            ->values();
        $finalNotQualifiedApplicants = $notQualifiedApplicants
            ->filter(fn (array $row): bool => (bool) ($row['panel_complete'] ?? false))
            ->values();
        $earlyVetoApplicants = $notQualifiedApplicants
            ->filter(fn (array $row): bool => ! (bool) ($row['panel_complete'] ?? false))
            ->values();
        $determinedApplicants = $qualifiedApplicants->count() + $finalNotQualifiedApplicants->count();
        $determinationPercent = $stats['total_applicants'] > 0
            ? (int) round(($determinedApplicants / $stats['total_applicants']) * 100)
            : 0;
        $communications = $communications ?? collect();
        $technicalProposalRounds = $technicalProposalRounds ?? collect();
        $communicationPreview = $communicationPreview ?? [
            'evaluation_records' => [
                'total' => $determinedApplicants,
                'eligible' => $determinedApplicants,
                'unsendable' => 0,
                'recipients' => collect(),
            ],
            'proposal_invitation' => [
                'total' => $qualifiedApplicants->count(),
                'eligible' => $qualifiedApplicants->count(),
                'unsendable' => 0,
                'recipients' => collect(),
            ],
        ];
    @endphp

    <main class="nxl-container eoi-report" aria-labelledby="eoiReportTitle">
        <header class="page-header eoi-page-header">
            <div class="page-header-left">
                <span class="eoi-page-kicker">Expression of Interest &middot; Panel Decision</span>
                <h4 class="fw-bold mb-1" id="eoiReportTitle">
                    <i class="feather-award me-2" aria-hidden="true"></i>
                    Selection &amp; Qualification Report
                </h4>
                <p class="mb-0">
                    {{ $procurementTitle }}
                    <span class="eoi-header-separator" aria-hidden="true">/</span>
                    {{ $procurementReference }}
                </p>
            </div>

            <div class="eoi-header-actions" aria-label="Report actions">
                <a href="{{ route('reports.evaluations.method', \App\Models\Evaluation::TYPE_EOI) }}" class="btn btn-light btn-sm">
                    <i class="feather-arrow-left me-1" aria-hidden="true"></i>
                    EOI Procurements
                </a>
                <a href="{{ route('reports.evaluations.eoi.procurement.excel', $procurement) }}" class="btn btn-light btn-sm">
                    <i class="feather-grid me-1" aria-hidden="true"></i>
                    Excel
                </a>
                <a href="{{ route('reports.evaluations.eoi.procurement.csv', $procurement) }}" class="btn btn-light btn-sm">
                    <i class="feather-file-text me-1" aria-hidden="true"></i>
                    CSV
                </a>
                <a href="{{ route('reports.evaluations.eoi.procurement.pdf', ['procurement' => $procurement, 'fresh' => ($report['generated_at'] ?? now())->getTimestampMs()]) }}" class="btn btn-success btn-sm">
                    <i class="feather-download me-1" aria-hidden="true"></i>
                    PDF
                </a>
                <button type="button" class="btn btn-light btn-sm" onclick="window.print()">
                    <i class="feather-printer me-1" aria-hidden="true"></i>
                    Print
                </button>
            </div>
        </header>

        @if (session('success'))
            <div class="alert alert-success eoi-communication-alert" role="status">
                <i class="feather-check-circle" aria-hidden="true"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if (session('warning'))
            <div class="alert alert-warning eoi-communication-alert" role="status">
                <i class="feather-alert-triangle" aria-hidden="true"></i>
                <span>{{ session('warning') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger eoi-communication-alert" role="alert">
                <i class="feather-alert-circle" aria-hidden="true"></i>
                <div>
                    <strong>The communication was not sent.</strong>
                    <ul class="mb-0 mt-1">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            </div>
        @endif

        <section class="eoi-communications" aria-labelledby="eoiCommunicationsTitle">
            <header class="eoi-communications__header">
                <div>
                    <span class="eoi-eyebrow">Applicant communications</span>
                    <h5 id="eoiCommunicationsTitle">Release outcomes &amp; request proposals</h5>
                    <p>Messages are personalized, sent one applicant at a time, and recorded here. Disabled, blacklisted, invalid, and imported placeholder contacts are skipped.</p>
                </div>
                <span class="eoi-private-badge"><i class="feather-lock" aria-hidden="true"></i> Private delivery</span>
            </header>

            <div class="eoi-communication-grid">
                @php
                    $recordPreview = data_get($communicationPreview, 'evaluation_records', []);
                    $proposalPreview = data_get($communicationPreview, 'proposal_invitation', []);
                @endphp
                <article class="eoi-communication-card eoi-communication-card--records">
                    <span class="eoi-communication-card__icon"><i class="feather-file-text" aria-hidden="true"></i></span>
                    <div class="eoi-communication-card__body">
                        <span class="eoi-communication-step">1 &middot; Finalized applicants</span>
                        <h6>Send evaluation records</h6>
                        <p>Attach each applicant's own consolidated PDF. Applicant identity remains visible; every evaluator identity is masked as <strong>XXX-XXXX-XXXX</strong>.</p>
                        <div class="eoi-recipient-counts">
                            <span><b>{{ number_format($recordPreview['eligible'] ?? 0) }}</b> ready</span>
                            <span><b>{{ number_format($recordPreview['total'] ?? 0) }}</b> final outcomes</span>
                            @if (($recordPreview['unsendable'] ?? 0) > 0)<span class="is-warning"><b>{{ number_format($recordPreview['unsendable']) }}</b> unsendable</span>@endif
                        </div>
                        @if (($recordPreview['unsendable'] ?? 0) > 0)
                            <details class="eoi-unsendable">
                                <summary>Review unsendable contacts</summary>
                                <ul>
                                    @foreach (collect($recordPreview['recipients'] ?? [])->where('eligible', false) as $contact)
                                        <li><strong>{{ $contact['name'] }}</strong> &mdash; {{ $contact['reason'] }}</li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </div>
                    @can('evaluations.manage')
                        <form method="POST" action="{{ route('reports.evaluations.eoi.communications.evaluation-records', $procurement) }}" class="eoi-communication-card__action" data-eoi-send-form data-sending-label="Generating &amp; sending..." data-confirm="Send individualized evaluation-record PDFs to all eligible finalized applicants?">
                            @csrf
                            <button type="submit" class="btn btn-outline-success" @disabled(($recordPreview['eligible'] ?? 0) < 1)>
                                <i class="feather-send me-1" aria-hidden="true"></i>
                                Send {{ number_format($recordPreview['eligible'] ?? 0) }} record(s)
                            </button>
                        </form>
                    @else
                        <div class="eoi-communication-card__readonly"><i class="feather-eye" aria-hidden="true"></i> Manage permission required to send</div>
                    @endcan
                </article>

                <article class="eoi-communication-card eoi-communication-card--proposal">
                    <span class="eoi-communication-card__icon"><i class="feather-upload-cloud" aria-hidden="true"></i></span>
                    <div class="eoi-communication-card__body">
                        <span class="eoi-communication-step">2 &middot; Qualified Applicants only</span>
                        <h6>Invite proposal submissions</h6>
                        <p>Write a tailored message, attach up to 10 PDF, Word, or Excel templates, and invite qualified applicants into their protected vendor-portal workspace.</p>
                        <div class="eoi-recipient-counts">
                            <span><b>{{ number_format($proposalPreview['eligible'] ?? 0) }}</b> ready</span>
                            <span><b>{{ number_format($proposalPreview['total'] ?? 0) }}</b> qualified</span>
                            @if (($proposalPreview['unsendable'] ?? 0) > 0)<span class="is-warning"><b>{{ number_format($proposalPreview['unsendable']) }}</b> unsendable</span>@endif
                        </div>
                        @if (($proposalPreview['unsendable'] ?? 0) > 0)
                            <details class="eoi-unsendable">
                                <summary>Review unsendable contacts</summary>
                                <ul>
                                    @foreach (collect($proposalPreview['recipients'] ?? [])->where('eligible', false) as $contact)
                                        <li><strong>{{ $contact['name'] }}</strong> &mdash; {{ $contact['reason'] }}</li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </div>
                    @can('evaluations.manage')
                        <button type="button" class="btn btn-success eoi-communication-card__action" data-bs-toggle="modal" data-bs-target="#eoiProposalInvitationModal" @disabled(($proposalPreview['eligible'] ?? 0) < 1)>
                            <i class="feather-mail me-1" aria-hidden="true"></i>
                            Compose invitation
                        </button>
                    @else
                        <div class="eoi-communication-card__readonly"><i class="feather-eye" aria-hidden="true"></i> Manage permission required to send</div>
                    @endcan
                </article>
            </div>

            @if ($communications->isNotEmpty())
                <div class="eoi-communication-history">
                    <div class="eoi-communication-history__heading">
                        <h6><i class="feather-clock" aria-hidden="true"></i> Recent delivery history</h6>
                        <span>Latest {{ $communications->count() }} batch(es)</span>
                    </div>
                    <div class="eoi-communication-history__list">
                        @foreach ($communications as $communication)
                            <article>
                                <span class="eoi-history-icon"><i class="{{ $communication->type === 'evaluation_records' ? 'feather-file-text' : 'feather-mail' }}" aria-hidden="true"></i></span>
                                <div>
                                    <strong>{{ $communication->subject }}</strong>
                                    <small>{{ $communication->type === 'evaluation_records' ? 'Evaluation records' : 'Proposal invitation' }} &middot; {{ $communication->creator?->name ?: 'System user' }} &middot; {{ $communication->created_at->format('d M Y, H:i') }}</small>
                                    @can('evaluations.manage')
                                    @if ($communication->attachments->isNotEmpty())
                                        <span class="eoi-history-files">
                                            @foreach ($communication->attachments as $attachment)
                                                <a href="{{ route('reports.evaluations.eoi.communications.attachments.download', [$procurement, $communication, $attachment]) }}"><i class="feather-paperclip" aria-hidden="true"></i> {{ $attachment->original_filename }}</a>
                                            @endforeach
                                        </span>
                                    @endif
                                    @endcan
                                    @can('evaluations.manage')
                                        @php
                                            $proposalRecipients = $communication->type === 'proposal_invitation'
                                                ? $communication->recipients->whereNotNull('proposal_submitted_at')
                                                : collect();
                                        @endphp
                                        @if ($proposalRecipients->isNotEmpty())
                                            <details class="eoi-history-proposals">
                                                <summary><i class="feather-inbox" aria-hidden="true"></i> {{ $proposalRecipients->count() }} proposal response(s)</summary>
                                                <div>
                                                    @foreach ($proposalRecipients as $proposalRecipient)
                                                        <section>
                                                            <strong>{{ $proposalRecipient->recipient_name }}</strong>
                                                            <small>Submitted {{ $proposalRecipient->proposal_submitted_at->format('d M Y, H:i') }}</small>
                                                            @if ($proposalRecipient->proposal_message)<p>{{ $proposalRecipient->proposal_message }}</p>@endif
                                                            <span>
                                                                @foreach ($proposalRecipient->proposalDocuments as $document)
                                                                    <a href="{{ route('reports.evaluations.eoi.communications.proposal-documents.download', [$procurement, $communication, $proposalRecipient, $document]) }}"><i class="feather-download" aria-hidden="true"></i> {{ $document->original_filename }}</a>
                                                                @endforeach
                                                            </span>
                                                        </section>
                                                    @endforeach
                                                </div>
                                            </details>
                                        @endif
                                    @endcan
                                </div>
                                <div class="eoi-history-stats">
                                    <span class="is-sent">{{ $communication->sent_recipients_count }} sent</span>
                                    @if ($communication->skipped_recipients_count)<span class="is-skipped">{{ $communication->skipped_recipients_count }} skipped</span>@endif
                                    @if ($communication->failed_recipients_count)<span class="is-failed">{{ $communication->failed_recipients_count }} failed</span>@endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>

        @include('reports.evaluations.partials.eoi-technical-proposal-workflow', [
            'technicalProposalRounds' => $technicalProposalRounds,
            'procurement' => $procurement,
        ])

        @can('evaluations.manage')
            @push('modals')
            <div class="modal fade" id="eoiProposalInvitationModal" tabindex="-1" aria-labelledby="eoiProposalInvitationTitle" aria-describedby="eoiProposalInvitationDescription" aria-hidden="true">
                <div class="modal-dialog modal-dialog-scrollable eoi-proposal-dialog">
                    <form class="modal-content eoi-proposal-modal" method="POST" enctype="multipart/form-data" action="{{ route('reports.evaluations.eoi.communications.proposal-invitation', $procurement) }}" data-eoi-send-form data-sending-label="Sending invitations...">
                        @csrf
                        <div class="modal-header">
                            <div class="eoi-proposal-modal__heading">
                                <span class="eoi-proposal-modal__icon" aria-hidden="true"><i class="feather-sliders"></i></span>
                                <div>
                                    <span class="eoi-eyebrow">Qualified Applicants &middot; {{ number_format($proposalPreview['eligible'] ?? 0) }} recipient(s)</span>
                                    <h5 class="modal-title" id="eoiProposalInvitationTitle">Configure rules &amp; send proposal invitation</h5>
                                    <p id="eoiProposalInvitationDescription">Set every submission rule, channel, deadline and template in one workspace.</p>
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="eoi-modal-note"><i class="feather-shield" aria-hidden="true"></i><span>Each applicant receives a separate email and a private portal invitation. No recipient list is exposed.</span></div>
                            <div class="mb-3">
                                <label for="eoiProposalSubject" class="form-label">Email subject</label>
                                <input id="eoiProposalSubject" name="subject" type="text" class="form-control @error('subject') is-invalid @enderror" maxlength="180" required value="{{ old('subject', 'Invitation to submit proposal — '.$procurementReference) }}">
                                @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label for="eoiProposalMessage" class="form-label">Message to Qualified Applicants</label>
                                <textarea id="eoiProposalMessage" name="message" class="form-control @error('message') is-invalid @enderror" rows="8" maxlength="5000" required placeholder="Explain the next stage, proposal expectations, and any submission instructions.">{{ old('message', "Congratulations. Your Expression of Interest has qualified for the next stage. Please review the attached templates and submit your completed proposal securely through the ATTP vendor portal.") }}</textarea>
                                <div class="form-text">Formatting is kept as plain text in the email for security. Line breaks will be preserved.</div>
                                @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="eoi-round-config mb-4">
                                <div class="eoi-round-config__head">
                                    <div>
                                        <span class="eoi-eyebrow">Technical proposal round</span>
                                        <h6>Deadline and submission channels</h6>
                                    </div>
                                    <span><i class="feather-lock"></i> Locked after invitation</span>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="eoiProposalTitle" class="form-label">Round title</label>
                                        <input id="eoiProposalTitle" name="proposal_title" class="form-control" maxlength="180" value="{{ old('proposal_title', 'Technical Proposal Submission — '.$procurementReference) }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="eoiProposalDeadline" class="form-label">Submission deadline</label>
                                        <input id="eoiProposalDeadline" name="deadline_at" type="datetime-local" class="form-control @error('deadline_at') is-invalid @enderror" value="{{ old('deadline_at', now()->addDays(14)->format('Y-m-d\TH:i')) }}" required>
                                        @error('deadline_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-3">
                                        <label for="eoiProposalTimezone" class="form-label">Time zone</label>
                                        <select id="eoiProposalTimezone" name="timezone" class="form-select">
                                            @foreach (['Africa/Nairobi' => 'Africa/Nairobi (EAT)', 'UTC' => 'UTC', 'Africa/Addis_Ababa' => 'Africa/Addis Ababa', 'Africa/Accra' => 'Africa/Accra', 'Africa/Lagos' => 'Africa/Lagos', 'Africa/Johannesburg' => 'Africa/Johannesburg'] as $zone => $zoneLabel)
                                                <option value="{{ $zone }}" @selected(old('timezone', config('app.timezone', 'Africa/Nairobi')) === $zone)>{{ $zoneLabel }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label" for="eoiLatePolicy">Late proposals</label>
                                        <select id="eoiLatePolicy" name="late_policy" class="form-select">
                                            <option value="allow_flagged" @selected(old('late_policy', 'allow_flagged') === 'allow_flagged')>Accept and flag late</option>
                                            <option value="reject" @selected(old('late_policy') === 'reject')>Reject in portal</option>
                                            <option value="admin_capture_only" @selected(old('late_policy') === 'admin_capture_only')>Admin capture only</option>
                                        </select>
                                    </div>
                                    @foreach ([
                                        'portal_requirement' => ['Portal submission', 'required'],
                                        'email_requirement' => ['Email submission', 'allowed'],
                                        'physical_requirement' => ['Physical copy', 'not_allowed'],
                                    ] as $channelName => [$channelLabel, $channelDefault])
                                        <div class="col-md-3">
                                            <label class="form-label" for="{{ $channelName }}">{{ $channelLabel }}</label>
                                            <select id="{{ $channelName }}" name="{{ $channelName }}" class="form-select">
                                                <option value="required" @selected(old($channelName, $channelDefault) === 'required')>Required</option>
                                                <option value="allowed" @selected(old($channelName, $channelDefault) === 'allowed')>Allowed</option>
                                                <option value="not_allowed" @selected(old($channelName, $channelDefault) === 'not_allowed')>Not allowed</option>
                                            </select>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            @php
                                $proposalRules = old('rules', [
                                    ['title' => 'Submission received by the stated deadline', 'description' => 'The proposal must be received no later than the published deadline.', 'category' => 'deadline', 'is_mandatory' => 1, 'is_disqualifying' => 1],
                                    ['title' => 'Submission channel requirements followed', 'description' => 'Every channel marked as required must be satisfied.', 'category' => 'channel', 'is_mandatory' => 1, 'is_disqualifying' => 1],
                                    ['title' => 'Required proposal documents are complete', 'description' => 'All required forms, schedules, and supporting documents must be included.', 'category' => 'document', 'is_mandatory' => 1, 'is_disqualifying' => 1],
                                ]);
                            @endphp
                            <div class="eoi-rules-builder mb-4">
                                <div class="eoi-rules-builder__head">
                                    <div>
                                        <span class="eoi-eyebrow">Rules &amp; regulations</span>
                                        <h6>Proposal compliance checklist</h6>
                                        <p>Add as many rules as needed. A failed disqualifying rule must include a reviewer rationale.</p>
                                    </div>
                                    <button type="button" class="btn btn-outline-success btn-sm" id="eoiAddProposalRule"><i class="feather-plus me-1"></i> Add rule</button>
                                </div>
                                <div id="eoiProposalRules" class="eoi-rule-list">
                                    @foreach ($proposalRules as $ruleIndex => $proposalRule)
                                        <div class="eoi-rule-row" data-rule-row>
                                            <span class="eoi-rule-row__number">{{ $ruleIndex + 1 }}</span>
                                            <div class="eoi-rule-row__fields">
                                                <input name="rules[{{ $ruleIndex }}][title]" class="form-control" maxlength="255" required placeholder="Rule title" value="{{ $proposalRule['title'] ?? '' }}">
                                                <textarea name="rules[{{ $ruleIndex }}][description]" class="form-control" rows="2" maxlength="10000" placeholder="What must the applicant do?">{{ $proposalRule['description'] ?? '' }}</textarea>
                                                <div class="eoi-rule-row__options">
                                                    <select name="rules[{{ $ruleIndex }}][category]" class="form-select form-select-sm">
                                                        @foreach (['general', 'eligibility', 'document', 'deadline', 'channel', 'declaration'] as $ruleCategory)
                                                            <option value="{{ $ruleCategory }}" @selected(($proposalRule['category'] ?? 'general') === $ruleCategory)>{{ str($ruleCategory)->headline() }}</option>
                                                        @endforeach
                                                    </select>
                                                    <label><input type="hidden" name="rules[{{ $ruleIndex }}][is_mandatory]" value="0"><input type="checkbox" name="rules[{{ $ruleIndex }}][is_mandatory]" value="1" @checked((bool) ($proposalRule['is_mandatory'] ?? true))> Mandatory</label>
                                                    <label><input type="hidden" name="rules[{{ $ruleIndex }}][is_disqualifying]" value="0"><input type="checkbox" name="rules[{{ $ruleIndex }}][is_disqualifying]" value="1" @checked((bool) ($proposalRule['is_disqualifying'] ?? false))> Can disqualify</label>
                                                </div>
                                            </div>
                                            <button type="button" class="eoi-rule-remove" data-remove-rule aria-label="Remove this rule"><i class="feather-trash-2"></i></button>
                                        </div>
                                    @endforeach
                                </div>
                                @error('rules')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                                @error('rules.*.title')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                            </div>
                            <div>
                                <label for="eoiProposalTemplates" class="form-label">Proposal templates <span class="text-muted fw-normal">(optional)</span></label>
                                <input id="eoiProposalTemplates" name="templates[]" type="file" class="form-control @error('templates') is-invalid @enderror @error('templates.*') is-invalid @enderror" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.odt,.ods,.odp,.jpg,.jpeg,.png" multiple>
                                <div class="form-text">Up to 20 safe business-document or image files, 20 MB each. Executables, scripts, SVG, HTML, and macro-enabled Office files are blocked.</div>
                                @error('templates')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @error('templates.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="eoi-file-selection" id="eoiTemplateSelection" aria-live="polite">No templates selected.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success"><i class="feather-send me-1" aria-hidden="true"></i> Send to Qualified Applicants</button>
                        </div>
                    </form>
                </div>
            </div>
            @endpush
        @endcan

        <section class="eoi-qualified-shortlist" id="eoiDecisionSummary" aria-labelledby="eoiDecisionSummaryTitle">
            <header class="eoi-qualified-shortlist__header">
                <div class="eoi-qualified-heading">
                    <span class="eoi-qualified-heading__icon" aria-hidden="true">
                        <i class="feather-clipboard"></i>
                    </span>
                    <div>
                        <span class="eoi-eyebrow">Applicant outcome summary</span>
                        <h5 id="eoiDecisionSummaryTitle">Panel Decision Summary</h5>
                        <p>Final outcomes are separated from applicants whose assigned panel tasks are still in progress.</p>
                    </div>
                </div>
                <div class="eoi-qualified-totals" aria-label="Applicant decision totals">
                    <span><strong>{{ number_format($qualifiedApplicants->count()) }}</strong> qualified</span>
                    <span class="eoi-qualified-total--stopped"><b>{{ number_format($finalNotQualifiedApplicants->count()) }}</b> not qualified</span>
                    <span class="eoi-qualified-total--pending"><b>{{ number_format($panelInProgressApplicants->count()) }}</b> awaiting panel</span>
                </div>
            </header>

            <div class="eoi-decision-summary-grid">
                <section
                    class="eoi-decision-group eoi-decision-group--qualified"
                    aria-labelledby="qualifiedApplicantsTitle"
                    data-summary-outcome="qualified"
                >
                    <header class="eoi-decision-group__header">
                        <span class="eoi-decision-group__icon" aria-hidden="true"><i class="feather-check-circle"></i></span>
                        <div>
                            <h6 id="qualifiedApplicantsTitle">Qualified Applicants</h6>
                            <p>Panel complete &middot; advances to Technical Evaluation</p>
                        </div>
                        <strong aria-label="{{ $qualifiedApplicants->count() }} qualified applicants">{{ number_format($qualifiedApplicants->count()) }}</strong>
                    </header>

                    @if ($qualifiedApplicants->isNotEmpty())
                        <ul class="eoi-decision-list" role="list">
                            @foreach ($qualifiedApplicants as $qualifiedRow)
                                @php
                                    $qualifiedApplicant = $qualifiedRow['applicant'];
                                    $qualifiedOutcome = $qualifiedRow['outcome'];
                                @endphp
                                <li
                                    class="eoi-decision-person"
                                    data-summary-outcome="qualified"
                                    data-summary-applicant="{{ $qualifiedApplicant->id }}"
                                    data-qualified-applicant="{{ $qualifiedApplicant->id }}"
                                >
                                    <span class="eoi-decision-avatar" aria-hidden="true">
                                        {{ Illuminate\Support\Str::upper(Illuminate\Support\Str::substr($qualifiedApplicant->display_name, 0, 1)) }}
                                    </span>
                                    <span class="eoi-decision-identity">
                                        <strong>{{ $qualifiedApplicant->display_name }}</strong>
                                        <small>{{ $qualifiedApplicant->procurement_submission_code ?: 'No submission code' }}</small>
                                    </span>
                                    <span class="eoi-decision-result">
                                        <span class="eoi-outcome eoi-outcome--{{ $qualifiedOutcome['code'] }}">
                                            <i class="{{ $qualifiedOutcome['code'] === 'fully_qualified' ? 'feather-check-circle' : 'feather-minus-circle' }}" aria-hidden="true"></i>
                                            {{ $qualifiedOutcome['label'] }}
                                        </span>
                                        <small>{{ $qualifiedRow['completed_tasks'] }}/{{ $qualifiedRow['expected_tasks'] }} panel tasks</small>
                                    </span>
                                    <a
                                        class="eoi-decision-evidence"
                                        href="#eoi-applicant-{{ $qualifiedApplicant->id }}"
                                        data-eoi-open-applicant="{{ $qualifiedApplicant->id }}"
                                        aria-label="View panel evidence for {{ $qualifiedApplicant->display_name }}"
                                    >
                                        Evidence <i class="feather-arrow-right" aria-hidden="true"></i>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="eoi-decision-empty">
                            <i class="feather-clock" aria-hidden="true"></i>
                            <span>No applicants are qualified yet.</span>
                        </div>
                    @endif
                </section>

                <section
                    class="eoi-decision-group eoi-decision-group--stopped"
                    aria-labelledby="notQualifiedApplicantsTitle"
                    data-summary-outcome="not-qualified"
                >
                    <header class="eoi-decision-group__header">
                        <span class="eoi-decision-group__icon" aria-hidden="true"><i class="feather-x-circle"></i></span>
                        <div>
                            <h6 id="notQualifiedApplicantsTitle">Not Qualified Applicants</h6>
                            <p>Panel complete &middot; does not advance</p>
                        </div>
                        <strong aria-label="{{ $finalNotQualifiedApplicants->count() }} not qualified applicants">{{ number_format($finalNotQualifiedApplicants->count()) }}</strong>
                    </header>

                    @if ($finalNotQualifiedApplicants->isNotEmpty())
                        <ul class="eoi-decision-list" role="list">
                            @foreach ($finalNotQualifiedApplicants as $notQualifiedRow)
                                @php
                                    $notQualifiedApplicant = $notQualifiedRow['applicant'];
                                @endphp
                                <li
                                    class="eoi-decision-person"
                                    data-summary-outcome="not-qualified"
                                    data-summary-applicant="{{ $notQualifiedApplicant->id }}"
                                >
                                    <span class="eoi-decision-avatar" aria-hidden="true">
                                        {{ Illuminate\Support\Str::upper(Illuminate\Support\Str::substr($notQualifiedApplicant->display_name, 0, 1)) }}
                                    </span>
                                    <span class="eoi-decision-identity">
                                        <strong>{{ $notQualifiedApplicant->display_name }}</strong>
                                        <small>{{ $notQualifiedApplicant->procurement_submission_code ?: 'No submission code' }}</small>
                                    </span>
                                    <span class="eoi-decision-result">
                                        <span class="eoi-outcome eoi-outcome--not_qualified">
                                            <i class="feather-x-circle" aria-hidden="true"></i>
                                            Not Qualified
                                        </span>
                                        <small>{{ $notQualifiedRow['completed_tasks'] }}/{{ $notQualifiedRow['expected_tasks'] }} panel tasks</small>
                                    </span>
                                    <a
                                        class="eoi-decision-evidence"
                                        href="#eoi-applicant-{{ $notQualifiedApplicant->id }}"
                                        data-eoi-open-applicant="{{ $notQualifiedApplicant->id }}"
                                        aria-label="View panel evidence for {{ $notQualifiedApplicant->display_name }}"
                                    >
                                        Evidence <i class="feather-arrow-right" aria-hidden="true"></i>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="eoi-decision-empty">
                            <i class="feather-check" aria-hidden="true"></i>
                            <span>No final Not Qualified decisions.</span>
                        </div>
                    @endif
                </section>

                <section
                    class="eoi-decision-group eoi-decision-group--pending"
                    aria-labelledby="awaitingPanelApplicantsTitle"
                    data-summary-outcome="pending"
                >
                    <header class="eoi-decision-group__header">
                        <span class="eoi-decision-group__icon" aria-hidden="true"><i class="feather-clock"></i></span>
                        <div>
                            <h6 id="awaitingPanelApplicantsTitle">Awaiting Panel Completion</h6>
                            <p>No final routing decision until every remaining assigned task is complete</p>
                        </div>
                        <strong aria-label="{{ $panelInProgressApplicants->count() }} applicants awaiting panel completion">{{ number_format($panelInProgressApplicants->count()) }}</strong>
                    </header>

                    @if ($panelInProgressApplicants->isNotEmpty())
                        <ul class="eoi-decision-list" role="list">
                            @foreach ($panelInProgressApplicants as $pendingRow)
                                @php
                                    $pendingApplicant = $pendingRow['applicant'];
                                    $pendingHasVeto = data_get($pendingRow, 'outcome.code') === 'not_qualified';
                                @endphp
                                <li
                                    class="eoi-decision-person"
                                    data-summary-outcome="pending"
                                    data-summary-applicant="{{ $pendingApplicant->id }}"
                                >
                                    <span class="eoi-decision-avatar" aria-hidden="true">
                                        {{ Illuminate\Support\Str::upper(Illuminate\Support\Str::substr($pendingApplicant->display_name, 0, 1)) }}
                                    </span>
                                    <span class="eoi-decision-identity">
                                        <strong>{{ $pendingApplicant->display_name }}</strong>
                                        <small>{{ $pendingApplicant->procurement_submission_code ?: 'No submission code' }}</small>
                                    </span>
                                    <span class="eoi-decision-result">
                                        <span class="eoi-outcome eoi-outcome--pending">
                                            <i class="feather-clock" aria-hidden="true"></i>
                                            {{ $pendingHasVeto ? 'NQ recorded · panel incomplete' : 'Awaiting Panel' }}
                                        </span>
                                        <small>{{ $pendingRow['completed_tasks'] }}/{{ $pendingRow['expected_tasks'] }} panel tasks</small>
                                    </span>
                                    <a
                                        class="eoi-decision-evidence"
                                        href="#eoi-applicant-{{ $pendingApplicant->id }}"
                                        data-eoi-open-applicant="{{ $pendingApplicant->id }}"
                                        aria-label="View current panel evidence for {{ $pendingApplicant->display_name }}"
                                    >
                                        Evidence <i class="feather-arrow-right" aria-hidden="true"></i>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="eoi-decision-empty">
                            <i class="feather-check-circle" aria-hidden="true"></i>
                            <span>No applicants are awaiting panel completion.</span>
                        </div>
                    @endif
                </section>
            </div>
        </section>

        <section class="eoi-executive-overview" aria-labelledby="eoiOverviewTitle">
            <div class="eoi-overview-main">
                <span class="eoi-overview-badge"><i class="feather-shield" aria-hidden="true"></i> Automatic qualification gate</span>
                <h5 id="eoiOverviewTitle">One clear decision register. One non-negotiable rule.</h5>
                <p>
                    Every assigned evaluator must finish. Fully Qualified and Average Qualified applicants move forward;
                    a single <strong>Not Qualified</strong> decision stops progression.
                </p>
                <div class="eoi-overview-meta">
                    <span><i class="feather-hash" aria-hidden="true"></i> {{ $procurementReference }}</span>
                    <span><i class="feather-users" aria-hidden="true"></i> {{ number_format($stats['panel_members']) }} panel member(s)</span>
                    <span><i class="feather-file-text" aria-hidden="true"></i> {{ number_format($stats['submitted_evaluations']) }} reports submitted</span>
                    <span><i class="feather-calendar" aria-hidden="true"></i> {{ $generatedAt->format('d M Y, H:i') }}</span>
                </div>
            </div>
            <aside class="eoi-overview-progress" aria-label="Overall decision progress">
                <div class="eoi-overview-progress__top">
                    <span>Decisions finalised</span>
                    <strong>{{ $determinedApplicants }}/{{ $stats['total_applicants'] }}</strong>
                </div>
                <span
                    class="eoi-overview-progress__track"
                    role="progressbar"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="{{ $determinationPercent }}"
                >
                    <span style="width: {{ $determinationPercent }}%"></span>
                </span>
                <small>{{ $determinationPercent }}% of applicant outcomes are final</small>
            </aside>
        </section>

        <section class="eoi-stage-overview" aria-label="EOI workflow summary">
            <article class="eoi-stage-card eoi-stage-card--total">
                <span class="eoi-stage-card__icon"><i class="feather-users" aria-hidden="true"></i></span>
                <div><span>Total applicants</span><strong>{{ number_format($stats['total_applicants']) }}</strong></div>
                <small>Entered in this EOI decision register</small>
            </article>
            <article class="eoi-stage-card eoi-stage-card--advance">
                <span class="eoi-stage-card__icon"><i class="feather-trending-up" aria-hidden="true"></i></span>
                <div><span>Technical-ready</span><strong>{{ number_format($qualifiedApplicants->count()) }}</strong></div>
                <small>{{ number_format($stats['fully_qualified']) }} fully &middot; {{ number_format($stats['average_qualified']) }} average</small>
            </article>
            <article class="eoi-stage-card eoi-stage-card--pending">
                <span class="eoi-stage-card__icon"><i class="feather-clock" aria-hidden="true"></i></span>
                <div><span>Panel in progress</span><strong>{{ number_format($panelInProgressApplicants->count()) }}</strong></div>
                <small>
                    Outcome held until every task is complete
                    @if ($earlyVetoApplicants->isNotEmpty())
                        &middot; {{ number_format($earlyVetoApplicants->count()) }} with NQ recorded
                    @endif
                </small>
            </article>
            <article class="eoi-stage-card eoi-stage-card--stopped">
                <span class="eoi-stage-card__icon"><i class="feather-slash" aria-hidden="true"></i></span>
                <div><span>NQ recorded</span><strong>{{ number_format($notQualifiedApplicants->count()) }}</strong></div>
                <small>{{ number_format($finalNotQualifiedApplicants->count()) }} panel-complete &middot; progression stopped</small>
            </article>
        </section>

        <section class="eoi-applicant-panel" aria-labelledby="applicantSummaryTitle">
            <div class="eoi-panel-heading">
                <div>
                    <span class="eoi-eyebrow">Complete decision register</span>
                    <h5 id="applicantSummaryTitle">All Applicant Decisions &amp; Panel Evidence</h5>
                    <p>Search every applicant, review the consolidated decision, then expand a record for the full evaluator audit trail.</p>
                </div>
                <div class="eoi-generated-at">
                    <i class="feather-calendar" aria-hidden="true"></i>
                    <span>Generated</span>
                    <strong>{{ $generatedAt->format('d M Y, H:i') }}</strong>
                </div>
            </div>

            <div class="eoi-filter-bar" role="search" aria-label="Filter applicant qualification results">
                <div class="eoi-search-field">
                    <label for="eoiApplicantSearch">Search applicants</label>
                    <div class="eoi-input-wrap">
                        <i class="feather-search" aria-hidden="true"></i>
                        <input
                            type="search"
                            class="form-control"
                            id="eoiApplicantSearch"
                            placeholder="Name, submission code, or email"
                            autocomplete="off"
                        >
                    </div>
                </div>
                <div class="eoi-filter-field">
                    <label for="eoiOutcomeFilter">Outcome</label>
                    <select class="form-control" id="eoiOutcomeFilter">
                        <option value="all">All outcomes ({{ $stats['total_applicants'] }})</option>
                        <option value="fully_qualified">Fully Qualified ({{ $stats['fully_qualified'] }})</option>
                        <option value="average_qualified">Average Qualified ({{ $stats['average_qualified'] }})</option>
                        <option value="not_qualified">Not Qualified ({{ $stats['not_qualified'] }})</option>
                        <option value="pending">Awaiting Panel ({{ $stats['pending'] }})</option>
                    </select>
                </div>
                <div class="eoi-filter-field">
                    <label for="eoiPanelFilter">Panel status</label>
                    <select class="form-control" id="eoiPanelFilter">
                        <option value="all">All panel statuses</option>
                        <option value="complete">Panel complete</option>
                        <option value="incomplete">Panel incomplete</option>
                    </select>
                </div>
                <div class="eoi-filter-actions" aria-label="Applicant detail controls">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="eoiExpandAll">
                        <i class="feather-chevrons-down me-1" aria-hidden="true"></i>
                        Expand visible
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="eoiCollapseAll">
                        <i class="feather-chevrons-up me-1" aria-hidden="true"></i>
                        Collapse all
                    </button>
                    <button type="button" class="btn btn-light btn-sm" id="eoiClearFilters">
                        <i class="feather-rotate-ccw me-1" aria-hidden="true"></i>
                        Clear
                    </button>
                </div>
            </div>

            <div class="eoi-list-status" id="eoiResultCount" role="status" aria-live="polite">
                Showing {{ $stats['total_applicants'] }} of {{ $stats['total_applicants'] }} applicants
            </div>

            <div class="eoi-applicant-list" id="eoiApplicantList">
                @forelse ($applicants as $row)
                    @php
                        $applicant = $row['applicant'];
                        $outcome = $row['outcome'];
                        $applicantName = $applicant->display_name;
                        $submissionCode = $applicant->procurement_submission_code ?: 'No submission code';
                        $applicantEmail = $applicant->submitter?->email;
                        $panelState = $row['panel_complete'] ? 'complete' : 'incomplete';
                        $searchText = Illuminate\Support\Str::lower(implode(' ', array_filter([
                            $applicantName,
                            $submissionCode,
                            $applicantEmail,
                            $outcome['label'],
                            $row['next_stage'],
                        ])));
                        $outcomeIcon = match ($outcome['code']) {
                            'fully_qualified' => 'feather-check-circle',
                            'average_qualified' => 'feather-minus-circle',
                            'not_qualified' => 'feather-x-circle',
                            default => 'feather-clock',
                        };
                    @endphp

                    <details
                        class="eoi-applicant eoi-applicant--{{ $outcome['code'] }}"
                        id="eoi-applicant-{{ $applicant->id }}"
                        data-search="{{ $searchText }}"
                        data-outcome="{{ $outcome['code'] }}"
                        data-panel="{{ $panelState }}"
                    >
                        <summary>
                            <span class="eoi-applicant-identity">
                                <span class="eoi-applicant-avatar" aria-hidden="true">
                                    <i class="feather-user"></i>
                                </span>
                                <span>
                                    <strong>{{ $applicantName }}</strong>
                                    <small>
                                        {{ $submissionCode }}
                                        @if ($applicantEmail)
                                            <span aria-hidden="true">&middot;</span> {{ $applicantEmail }}
                                        @endif
                                    </small>
                                </span>
                            </span>

                            <span class="eoi-panel-progress">
                                <span class="eoi-panel-progress-label">
                                    <span>Panel completion</span>
                                    <strong>{{ $row['completed_tasks'] }}/{{ $row['expected_tasks'] }} tasks</strong>
                                </span>
                                <span
                                    class="eoi-progress-track"
                                    role="progressbar"
                                    aria-label="Panel completion for {{ $applicantName }}"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                    aria-valuenow="{{ $row['completion_percent'] }}"
                                >
                                    <span style="width: {{ $row['completion_percent'] }}%"></span>
                                </span>
                                <small>
                                    {{ $row['completed_evaluators'] }}/{{ $row['expected_evaluators'] }} evaluator(s)
                                    <span class="eoi-panel-label eoi-panel-label--{{ $panelState }}">
                                        {{ $row['panel_complete'] ? 'Complete' : 'In progress' }}
                                    </span>
                                </small>
                            </span>

                            <span class="eoi-decision-totals" aria-label="Decision counts">
                                <span class="eoi-count eoi-count--qualified" title="Qualified decisions">
                                    <b>Q</b> {{ $row['counts']['qualified'] }}
                                </span>
                                <span class="eoi-count eoi-count--average" title="Average Qualified decisions">
                                    <b>AQ</b> {{ $row['counts']['average_qualified'] }}
                                </span>
                                <span class="eoi-count eoi-count--not-qualified" title="Not Qualified decisions">
                                    <b>NQ</b> {{ $row['counts']['not_qualified'] }}
                                </span>
                            </span>

                            <span class="eoi-applicant-outcome">
                                <span class="eoi-outcome eoi-outcome--{{ $outcome['code'] }}">
                                    <i class="{{ $outcomeIcon }}" aria-hidden="true"></i>
                                    {{ $outcome['label'] }}
                                </span>
                                <small>
                                    Next stage: <strong>{{ $row['next_stage'] }}</strong>
                                </small>
                            </span>

                            <span class="eoi-summary-chevron" aria-hidden="true">
                                <i class="feather-chevron-down"></i>
                            </span>
                        </summary>

                        <div class="eoi-applicant-detail">
                            <div class="eoi-determination eoi-determination--{{ $outcome['code'] }}">
                                <span class="eoi-determination-icon" aria-hidden="true">
                                    <i class="{{ $outcomeIcon }}"></i>
                                </span>
                                <div>
                                    <span class="eoi-eyebrow">{{ $row['panel_complete'] ? 'Panel determination' : 'Current panel signal' }}</span>
                                    <h6>{{ $outcome['label'] }} &mdash; {{ $row['next_stage'] }}</h6>
                                    <p>{{ $outcome['description'] }}</p>
                                </div>
                                <div class="eoi-determination-meta">
                                    <span>{{ $row['total_decisions'] }} decision(s)</span>
                                    <span>{{ $row['evaluation_reports']->count() }} template(s)</span>
                                </div>
                            </div>

                            <div class="eoi-detail-stats" aria-label="Detailed applicant totals">
                                <div>
                                    <span>Panel tasks</span>
                                    <strong>{{ $row['completed_tasks'] }} / {{ $row['expected_tasks'] }}</strong>
                                </div>
                                <div>
                                    <span>Qualified</span>
                                    <strong class="text-success">{{ $row['counts']['qualified'] }}</strong>
                                </div>
                                <div>
                                    <span>Average Qualified</span>
                                    <strong class="eoi-text-warning">{{ $row['counts']['average_qualified'] }}</strong>
                                </div>
                                <div>
                                    <span>Not Qualified</span>
                                    <strong class="text-danger">{{ $row['counts']['not_qualified'] }}</strong>
                                </div>
                                <div>
                                    <span>Panel status</span>
                                    <strong>{{ $row['panel_complete'] ? 'Complete' : 'In progress' }}</strong>
                                </div>
                                <div>
                                    <span>Progression</span>
                                    <strong>{{ $row['can_advance'] ? 'Approved' : 'Not approved' }}</strong>
                                </div>
                            </div>

                            <div class="eoi-template-stack">
                                @forelse ($row['evaluation_reports'] as $templateReport)
                                    @php
                                        $evaluation = $templateReport['evaluation'];
                                        $members = $templateReport['members'];
                                        $criteriaRows = $templateReport['criteria'];
                                        $completedMembers = $members->where('task_complete', true)->count();
                                    @endphp

                                    <article class="eoi-template-card">
                                        <header class="eoi-template-header">
                                            <div>
                                                <span class="eoi-eyebrow">EOI evaluation template</span>
                                                <h6>
                                                    <i class="feather-clipboard" aria-hidden="true"></i>
                                                    {{ $evaluation->name }}
                                                </h6>
                                                @if ($evaluation->description)
                                                    <p>{{ $evaluation->description }}</p>
                                                @endif
                                            </div>
                                            <div class="eoi-template-meta">
                                                <span><strong>{{ $criteriaRows->count() }}</strong> criteria</span>
                                                <span><strong>{{ $members->count() }}</strong> evaluator(s)</span>
                                                <span class="{{ $completedMembers === $members->count() && $members->isNotEmpty() ? 'is-complete' : '' }}">
                                                    <strong>{{ $completedMembers }}/{{ $members->count() }}</strong> complete
                                                </span>
                                            </div>
                                        </header>

                                        <section class="eoi-member-section" aria-labelledby="memberHeading-{{ $applicant->id }}-{{ $evaluation->id }}">
                                            <div class="eoi-subsection-title">
                                                <div>
                                                    <span class="eoi-eyebrow">Assigned panel</span>
                                                    <h6 id="memberHeading-{{ $applicant->id }}-{{ $evaluation->id }}">Evaluator completion</h6>
                                                </div>
                                                <span>{{ $completedMembers }} of {{ $members->count() }} task(s) complete</span>
                                            </div>

                                            <div class="eoi-member-grid">
                                                @forelse ($members as $member)
                                                    @php
                                                        $memberStatus = $member['task_complete']
                                                            ? 'complete'
                                                            : ($member['submitted'] ? 'incomplete' : 'pending');
                                                        $memberStatusLabel = $member['task_complete']
                                                            ? 'Complete'
                                                            : ($member['submitted'] ? 'Missing decisions' : 'Awaiting submission');
                                                    @endphp
                                                    <article class="eoi-member eoi-member--{{ $memberStatus }}">
                                                        <div class="eoi-member-head">
                                                            <span class="eoi-member-icon" aria-hidden="true">
                                                                <i class="{{ $member['task_complete'] ? 'feather-user-check' : 'feather-user' }}"></i>
                                                            </span>
                                                            <div>
                                                                <strong>{{ $member['name'] }}</strong>
                                                                @if ($member['email'])
                                                                    <small>{{ $member['email'] }}</small>
                                                                @endif
                                                            </div>
                                                            <span class="eoi-member-status">{{ $memberStatusLabel }}</span>
                                                        </div>
                                                        <div class="eoi-member-foot">
                                                            <span>
                                                                <i class="feather-calendar" aria-hidden="true"></i>
                                                                {{ $member['submitted_at']?->format('d M Y, H:i') ?? 'Not submitted' }}
                                                            </span>
                                                            <span class="eoi-member-counts" aria-label="Evaluator decision counts">
                                                                <b class="is-q">Q {{ $member['counts']['qualified'] }}</b>
                                                                <b class="is-aq">AQ {{ $member['counts']['average_qualified'] }}</b>
                                                                <b class="is-nq">NQ {{ $member['counts']['not_qualified'] }}</b>
                                                            </span>
                                                        </div>
                                                        @unless ($member['assigned'])
                                                            <small class="eoi-import-note">
                                                                <i class="feather-info" aria-hidden="true"></i>
                                                                Imported submission; original assignment is unavailable.
                                                            </small>
                                                        @endunless
                                                    </article>
                                                @empty
                                                    <div class="eoi-inline-empty">
                                                        <i class="feather-user-x" aria-hidden="true"></i>
                                                        No evaluator records are available for this template.
                                                    </div>
                                                @endforelse
                                            </div>
                                        </section>

                                        <section class="eoi-matrix-section" aria-labelledby="matrixHeading-{{ $applicant->id }}-{{ $evaluation->id }}">
                                            <div class="eoi-subsection-title">
                                                <div>
                                                    <span class="eoi-eyebrow">Decision evidence</span>
                                                    <h6 id="matrixHeading-{{ $applicant->id }}-{{ $evaluation->id }}">Criterion-by-evaluator matrix</h6>
                                                </div>
                                                <div class="eoi-matrix-legend" aria-label="Decision legend">
                                                    <span class="eoi-decision eoi-decision--qualified">Q</span>
                                                    <span>Qualified</span>
                                                    <span class="eoi-decision eoi-decision--average">AQ</span>
                                                    <span>Average Qualified</span>
                                                    <span class="eoi-decision eoi-decision--not-qualified">NQ</span>
                                                    <span>Not Qualified</span>
                                                </div>
                                            </div>

                                            <div class="table-responsive eoi-matrix-wrap">
                                                <table class="table eoi-matrix-table">
                                                    <caption class="visually-hidden">
                                                        Criterion decisions and comments for {{ $evaluation->name }} and {{ $applicantName }}
                                                    </caption>
                                                    <thead>
                                                        <tr>
                                                            <th scope="col" class="eoi-criterion-column">Criterion</th>
                                                            <th scope="col" class="eoi-result-column">Panel result</th>
                                                            @foreach ($members as $member)
                                                                <th scope="col" class="eoi-evaluator-column">
                                                                    <span>{{ $member['name'] }}</span>
                                                                    <small>{{ $member['task_complete'] ? 'Complete' : ($member['submitted'] ? 'Incomplete' : 'Pending') }}</small>
                                                                </th>
                                                            @endforeach
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @forelse ($criteriaRows as $criterionRow)
                                                            @php
                                                                $criterion = $criterionRow['criterion'];
                                                                $criterionOutcome = $criterionRow['outcome'];
                                                            @endphp
                                                            <tr>
                                                                <th scope="row" class="eoi-criterion-cell">
                                                                    <span class="eoi-criterion-section">{{ $criterionRow['section']->name }}</span>
                                                                    <strong>{{ $loop->iteration }}. {{ $criterion->name }}</strong>
                                                                    @if ($criterion->description)
                                                                        <small>{{ $criterion->description }}</small>
                                                                    @endif
                                                                </th>
                                                                <td class="eoi-result-cell">
                                                                    <span class="eoi-outcome eoi-outcome--{{ $criterionOutcome['code'] }}">
                                                                        {{ $criterionOutcome['label'] }}
                                                                    </span>
                                                                    <span class="eoi-result-counts">
                                                                        Q {{ $criterionRow['counts']['qualified'] }}
                                                                        <span aria-hidden="true">&middot;</span>
                                                                        AQ {{ $criterionRow['counts']['average_qualified'] }}
                                                                        <span aria-hidden="true">&middot;</span>
                                                                        NQ {{ $criterionRow['counts']['not_qualified'] }}
                                                                    </span>
                                                                </td>
                                                                @foreach ($members as $member)
                                                                    @php
                                                                        $assessment = $criterionRow['assessments']->firstWhere('member_key', $member['key']);
                                                                        $decisionClass = $assessment
                                                                            ? match ($assessment['decision']) {
                                                                                2 => 'qualified',
                                                                                1 => 'average',
                                                                                default => 'not-qualified',
                                                                            }
                                                                            : 'pending';
                                                                        $decisionShort = $assessment
                                                                            ? match ($assessment['decision']) {
                                                                                2 => 'Q',
                                                                                1 => 'AQ',
                                                                                default => 'NQ',
                                                                            }
                                                                            : null;
                                                                    @endphp
                                                                    <td class="eoi-assessment-cell">
                                                                        @if ($assessment)
                                                                            <span class="eoi-decision eoi-decision--{{ $decisionClass }}">
                                                                                <b>{{ $decisionShort }}</b>
                                                                                {{ $assessment['label'] }}
                                                                            </span>
                                                                            @if ($assessment['comment'] !== '')
                                                                                <p>{{ $assessment['comment'] }}</p>
                                                                            @else
                                                                                <small class="eoi-no-comment">No evaluator comment</small>
                                                                            @endif
                                                                        @else
                                                                            <span class="eoi-decision eoi-decision--pending">
                                                                                <i class="feather-clock" aria-hidden="true"></i>
                                                                                Awaiting decision
                                                                            </span>
                                                                            <small class="eoi-no-comment">
                                                                                {{ $member['submitted'] ? 'Decision not recorded' : 'Evaluation not submitted' }}
                                                                            </small>
                                                                        @endif
                                                                    </td>
                                                                @endforeach
                                                            </tr>
                                                        @empty
                                                            <tr>
                                                                <td colspan="{{ 2 + $members->count() }}">
                                                                    <div class="eoi-inline-empty">
                                                                        <i class="feather-inbox" aria-hidden="true"></i>
                                                                        No criteria were found for this evaluation template.
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </section>
                                    </article>
                                @empty
                                    <div class="eoi-inline-empty eoi-inline-empty--large">
                                        <i class="feather-clipboard" aria-hidden="true"></i>
                                        <strong>No detailed evaluation records yet</strong>
                                        <span>Panel templates and evaluator decisions will appear here as they are assigned.</span>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </details>
                @empty
                    <div class="eoi-empty-state">
                        <span><i class="feather-users" aria-hidden="true"></i></span>
                        <h6>No applicants are available</h6>
                        <p>Applicant qualification results will appear after submissions enter the EOI workflow.</p>
                    </div>
                @endforelse
            </div>

            <div class="eoi-empty-state eoi-filter-empty" id="eoiNoResults" hidden>
                <span><i class="feather-search" aria-hidden="true"></i></span>
                <h6>No applicants match these filters</h6>
                <p>Try a different name, outcome, or panel status.</p>
                <button type="button" class="btn btn-outline-primary btn-sm" id="eoiNoResultsClear">Clear filters</button>
            </div>
        </section>

        <footer class="eoi-report-footer">
            <div>
                <i class="feather-info" aria-hidden="true"></i>
                <span>
                    <strong>Decision key:</strong> Q = Qualified, AQ = Average Qualified, NQ = Not Qualified.
                </span>
            </div>
            <div>
                <i class="feather-file-text" aria-hidden="true"></i>
                <span>{{ number_format($stats['submitted_evaluations']) }} submitted evaluator report(s)</span>
            </div>
        </footer>
    </main>
@endsection

@push('styles')
    <style>
        .eoi-report,
        #eoiProposalInvitationModal {
            --eoi-ink: #172033;
            --eoi-muted: #667085;
            --eoi-line: #dce3ec;
            --eoi-soft: #f6f8fb;
            --eoi-blue: #2563eb;
            --eoi-teal: #0f766e;
            --eoi-green: #15803d;
            --eoi-green-soft: #ecfdf3;
            --eoi-amber: #b45309;
            --eoi-amber-soft: #fffbeb;
            --eoi-red: #b42318;
            --eoi-red-soft: #fff1f0;
            --eoi-slate: #475467;
        }

        .eoi-report {
            color: var(--eoi-ink);
            padding-bottom: 28px;
        }

        .eoi-page-kicker {
            color: rgba(255, 255, 255, .76);
            display: block;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .13em;
            margin-bottom: 3px;
            text-transform: uppercase;
        }

        .eoi-page-header p {
            color: rgba(255, 255, 255, .84) !important;
            font-size: 13px;
        }

        .eoi-header-separator {
            margin: 0 7px;
            opacity: .55;
        }

        .eoi-header-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .eoi-communication-alert {
            align-items: flex-start;
            display: flex;
            gap: 10px;
            margin: 14px 0 0;
        }

        .eoi-communication-alert > i { font-size: 18px; margin-top: 1px; }

        .eoi-communications {
            background: #fff;
            border: 1px solid var(--eoi-line);
            border-radius: 16px;
            box-shadow: 0 12px 30px rgba(16, 24, 40, .06);
            margin-top: 16px;
            overflow: hidden;
        }

        .eoi-communications__header {
            align-items: flex-start;
            background: linear-gradient(135deg, #f7fbf9 0%, #fff 70%);
            border-bottom: 1px solid var(--eoi-line);
            display: flex;
            gap: 20px;
            justify-content: space-between;
            padding: 20px 22px;
        }

        .eoi-communications__header h5 { font-size: 19px; margin: 3px 0 4px; }
        .eoi-communications__header p { color: var(--eoi-muted); margin: 0; max-width: 820px; }

        .eoi-private-badge {
            align-items: center;
            background: #ecfdf3;
            border: 1px solid #bbf7d0;
            border-radius: 999px;
            color: #087443;
            display: inline-flex;
            flex: 0 0 auto;
            font-size: 11px;
            font-weight: 800;
            gap: 6px;
            padding: 7px 10px;
            text-transform: uppercase;
        }

        .eoi-communication-grid { display: grid; gap: 14px; grid-template-columns: repeat(2, minmax(0, 1fr)); padding: 18px 22px; }

        .eoi-communication-card {
            align-items: flex-start;
            background: #fff;
            border: 1px solid var(--eoi-line);
            border-radius: 13px;
            display: grid;
            gap: 13px;
            grid-template-columns: auto minmax(0, 1fr);
            padding: 17px;
        }

        .eoi-communication-card--records { border-top: 3px solid #2563eb; }
        .eoi-communication-card--proposal { border-top: 3px solid #15803d; }
        .eoi-communication-card__icon { align-items: center; background: #eef4ff; border-radius: 11px; color: #2563eb; display: inline-flex; font-size: 20px; height: 42px; justify-content: center; width: 42px; }
        .eoi-communication-card--proposal .eoi-communication-card__icon { background: #ecfdf3; color: #15803d; }
        .eoi-communication-step { color: var(--eoi-muted); font-size: 10px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .eoi-communication-card h6 { font-size: 16px; margin: 3px 0 5px; }
        .eoi-communication-card p { color: var(--eoi-muted); font-size: 12px; margin: 0; }
        .eoi-communication-card__action, form.eoi-communication-card__action { grid-column: 2; justify-self: start; margin: 1px 0 0; }
        .eoi-communication-card__readonly { color: var(--eoi-muted); font-size: 11px; grid-column: 2; }

        .eoi-recipient-counts { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 11px; }
        .eoi-recipient-counts span { background: var(--eoi-soft); border-radius: 999px; color: var(--eoi-slate); font-size: 10px; padding: 5px 8px; }
        .eoi-recipient-counts span b { color: var(--eoi-ink); font-size: 12px; }
        .eoi-recipient-counts .is-warning { background: var(--eoi-amber-soft); color: var(--eoi-amber); }

        .eoi-unsendable { margin-top: 9px; }
        .eoi-unsendable summary { color: var(--eoi-amber); cursor: pointer; font-size: 11px; font-weight: 700; }
        .eoi-unsendable ul { color: var(--eoi-muted); font-size: 10px; margin: 7px 0 0; padding-left: 18px; }

        .eoi-communication-history { border-top: 1px solid var(--eoi-line); padding: 16px 22px 20px; }
        .eoi-communication-history__heading { align-items: center; display: flex; justify-content: space-between; margin-bottom: 9px; }
        .eoi-communication-history__heading h6 { margin: 0; }
        .eoi-communication-history__heading span { color: var(--eoi-muted); font-size: 10px; }
        .eoi-communication-history__list { border: 1px solid var(--eoi-line); border-radius: 10px; overflow: hidden; }
        .eoi-communication-history__list article { align-items: center; border-bottom: 1px solid var(--eoi-line); display: grid; gap: 10px; grid-template-columns: auto minmax(0, 1fr) auto; padding: 11px 13px; }
        .eoi-communication-history__list article:last-child { border-bottom: 0; }
        .eoi-history-icon { align-items: center; background: var(--eoi-soft); border-radius: 8px; color: var(--eoi-teal); display: inline-flex; height: 32px; justify-content: center; width: 32px; }
        .eoi-communication-history__list strong { display: block; font-size: 11px; }
        .eoi-communication-history__list small { color: var(--eoi-muted); display: block; font-size: 9px; }
        .eoi-history-files { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 4px; }
        .eoi-history-files a { background: #f2f7f4; border-radius: 5px; color: #087443; font-size: 9px; padding: 3px 6px; text-decoration: none; }
        .eoi-history-proposals { margin-top: 6px; }
        .eoi-history-proposals > summary { color: #087443; cursor: pointer; font-size: 9px; font-weight: 800; }
        .eoi-history-proposals > div { background: #f8fbf9; border: 1px solid #dce8e1; border-radius: 7px; margin-top: 5px; max-height: 220px; overflow: auto; padding: 7px; }
        .eoi-history-proposals section { border-bottom: 1px solid #e3ebe6; padding: 6px 2px; }
        .eoi-history-proposals section:last-child { border-bottom: 0; }
        .eoi-history-proposals section p { color: var(--eoi-muted); font-size: 9px; margin: 3px 0; white-space: pre-line; }
        .eoi-history-proposals section span { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
        .eoi-history-proposals section a { background: #eaf5ef; border-radius: 5px; color: #087443; font-size: 9px; padding: 3px 6px; text-decoration: none; }
        .eoi-history-stats { align-items: flex-end; display: flex; flex-direction: column; gap: 3px; }
        .eoi-history-stats span { border-radius: 999px; font-size: 9px; font-weight: 800; padding: 3px 7px; }
        .eoi-history-stats .is-sent { background: #ecfdf3; color: #15803d; }
        .eoi-history-stats .is-skipped { background: #fffbeb; color: #b45309; }
        .eoi-history-stats .is-failed { background: #fff1f0; color: #b42318; }

        body.modal-open .eoi-report { filter: none !important; }
        #eoiProposalInvitationModal { color: var(--eoi-ink); padding: 0 !important; z-index: 2050 !important; }
        .modal-backdrop.show { background: #081a2b; opacity: .48 !important; z-index: 2040 !important; }
        .eoi-proposal-dialog {
            height: calc(100vh - 24px);
            height: calc(100dvh - 24px);
            margin: 12px auto;
            max-width: none;
            width: calc(100vw - 24px);
        }
        .eoi-proposal-modal { border: 0; border-radius: 18px; box-shadow: 0 28px 80px rgba(2, 18, 35, .28); height: 100%; max-height: none !important; overflow: hidden; }
        .eoi-proposal-modal .modal-header { align-items: center; background: linear-gradient(135deg, #f4fbf7 0%, #fff 72%); border-bottom-color: var(--eoi-line); flex: 0 0 auto; padding: 18px 24px; }
        .eoi-proposal-modal__heading { align-items: center; display: flex; gap: 13px; min-width: 0; }
        .eoi-proposal-modal__icon { align-items: center; background: #dff4e8; border-radius: 13px; color: #087443; display: inline-flex; flex: 0 0 auto; font-size: 22px; height: 48px; justify-content: center; width: 48px; }
        .eoi-proposal-modal .modal-title { font-size: 19px; margin: 1px 0 2px; }
        .eoi-proposal-modal__heading p { color: var(--eoi-muted); font-size: 11px; margin: 0; }
        .eoi-proposal-modal .modal-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding: 22px 24px 30px; scrollbar-gutter: stable; }
        .eoi-proposal-modal .modal-footer { background: #fafcfb; border-top-color: var(--eoi-line); box-shadow: 0 -8px 24px rgba(15, 23, 42, .05); flex: 0 0 auto; padding: 14px 24px; }
        .eoi-proposal-modal .eoi-rule-list { max-height: none; overflow: visible; }
        .eoi-modal-note { align-items: flex-start; background: #eef7f2; border: 1px solid #cde4d7; border-radius: 9px; color: #315744; display: flex; font-size: 11px; gap: 8px; margin-bottom: 17px; padding: 10px 12px; }
        .eoi-file-selection { background: var(--eoi-soft); border-radius: 7px; color: var(--eoi-muted); font-size: 10px; margin-top: 7px; padding: 7px 9px; }

        @media (max-width: 991.98px) {
            .eoi-communication-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 575.98px) {
            .eoi-proposal-dialog { height: 100vh; height: 100dvh; margin: 0; width: 100vw; }
            .eoi-proposal-modal { border-radius: 0; }
            .eoi-proposal-modal .modal-header { align-items: flex-start; padding: 14px 16px; }
            .eoi-proposal-modal__icon { height: 40px; width: 40px; }
            .eoi-proposal-modal .modal-title { font-size: 16px; }
            .eoi-proposal-modal__heading p { display: none; }
            .eoi-proposal-modal .modal-body { padding: 16px; }
            .eoi-proposal-modal .modal-footer { padding: 11px 16px; }
            .eoi-proposal-modal .modal-footer .btn { flex: 1 1 auto; }
            .eoi-communications__header { flex-direction: column; }
            .eoi-communication-grid, .eoi-communication-history { padding-left: 12px; padding-right: 12px; }
            .eoi-communication-card { grid-template-columns: 1fr; }
            .eoi-communication-card__action, form.eoi-communication-card__action, .eoi-communication-card__readonly { grid-column: 1; }
            .eoi-communication-history__list article { align-items: flex-start; grid-template-columns: auto minmax(0, 1fr); }
            .eoi-history-stats { align-items: flex-start; flex-direction: row; grid-column: 2; }
        }

        .eoi-eyebrow {
            color: var(--eoi-muted);
            display: block;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .09em;
            text-transform: uppercase;
        }

        .eoi-qualified-shortlist {
            background: #fff;
            border: 1px solid #b7e4c7;
            border-radius: 16px;
            box-shadow: 0 18px 38px rgba(21, 128, 61, .09);
            margin-bottom: 16px;
            overflow: hidden;
            position: relative;
        }

        .eoi-qualified-shortlist::before {
            background: linear-gradient(90deg, #15803d, #14b8a6 65%, #38bdf8);
            content: '';
            height: 5px;
            left: 0;
            position: absolute;
            right: 0;
            top: 0;
        }

        .eoi-qualified-shortlist__header {
            align-items: center;
            background: linear-gradient(120deg, #f0fdf4 0%, #f7fffb 58%, #eff6ff 100%);
            border-bottom: 1px solid #cdebd7;
            display: flex;
            gap: 24px;
            justify-content: space-between;
            padding: 24px 26px 20px;
        }

        .eoi-qualified-heading {
            align-items: center;
            display: flex;
            gap: 14px;
            min-width: 0;
        }

        .eoi-qualified-heading__icon {
            align-items: center;
            background: linear-gradient(145deg, #15803d, #0f766e);
            border: 4px solid rgba(255, 255, 255, .82);
            border-radius: 15px;
            box-shadow: 0 9px 18px rgba(21, 128, 61, .2);
            color: #fff;
            display: inline-flex;
            flex: 0 0 54px;
            font-size: 23px;
            height: 54px;
            justify-content: center;
        }

        .eoi-qualified-heading .eoi-eyebrow {
            color: #15803d;
        }

        .eoi-qualified-heading h5 {
            color: #12301f;
            font-size: 22px;
            font-weight: 850;
            letter-spacing: -.025em;
            margin: 3px 0 2px;
        }

        .eoi-qualified-heading p {
            color: #527060;
            font-size: 12px;
            margin: 0;
        }

        .eoi-qualified-totals {
            align-items: center;
            display: flex;
            flex: 0 0 auto;
            flex-wrap: wrap;
            gap: 7px;
            justify-content: flex-end;
        }

        .eoi-qualified-totals span {
            background: rgba(255, 255, 255, .88);
            border: 1px solid #cce7d5;
            border-radius: 999px;
            color: #42624e;
            font-size: 10px;
            font-weight: 700;
            padding: 7px 10px;
            white-space: nowrap;
        }

        .eoi-qualified-totals span:first-child {
            background: #166534;
            border-color: #166534;
            color: #fff;
        }

        .eoi-qualified-totals strong,
        .eoi-qualified-totals b {
            font-size: 12px;
            margin-right: 2px;
        }

        .eoi-qualified-grid {
            background: #fbfefc;
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            max-height: 610px;
            overflow: auto;
            padding: 18px;
        }

        .eoi-qualified-card {
            --qualified-accent: #15803d;
            --qualified-soft: #f0fdf4;
            background: #fff;
            border: 1px solid #d9e8df;
            border-top: 4px solid var(--qualified-accent);
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(15, 23, 42, .05);
            display: flex;
            flex-direction: column;
            min-width: 0;
            overflow: hidden;
        }

        .eoi-qualified-card--average_qualified {
            --qualified-accent: #d97706;
            --qualified-soft: #fffbeb;
        }

        .eoi-qualified-card__top {
            align-items: center;
            display: flex;
            justify-content: space-between;
            padding: 11px 14px 5px;
        }

        .eoi-qualified-sequence {
            color: #98a2b3;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: .12em;
        }

        .eoi-qualified-card__identity {
            align-items: center;
            display: flex;
            gap: 11px;
            min-width: 0;
            padding: 8px 14px 13px;
        }

        .eoi-qualified-avatar {
            align-items: center;
            background: var(--qualified-soft);
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            color: var(--qualified-accent);
            display: inline-flex;
            flex: 0 0 42px;
            font-size: 15px;
            font-weight: 900;
            height: 42px;
            justify-content: center;
        }

        .eoi-qualified-card--average_qualified .eoi-qualified-avatar {
            border-color: #fde68a;
        }

        .eoi-qualified-card__identity > div {
            min-width: 0;
        }

        .eoi-qualified-card__identity h6 {
            color: var(--eoi-ink);
            font-size: 14px;
            font-weight: 850;
            line-height: 1.35;
            margin: 0 0 3px;
        }

        .eoi-qualified-card__identity p {
            color: var(--eoi-muted);
            font-size: 10px;
            line-height: 1.45;
            margin: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .eoi-qualified-card__metrics {
            background: #f8fafc;
            border-bottom: 1px solid #e7edf3;
            border-top: 1px solid #e7edf3;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
        }

        .eoi-qualified-card__metrics span {
            padding: 10px 12px;
        }

        .eoi-qualified-card__metrics span + span {
            border-left: 1px solid #e7edf3;
        }

        .eoi-qualified-card__metrics small {
            color: #7c899b;
            display: block;
            font-size: 8px;
            font-weight: 800;
            letter-spacing: .045em;
            text-transform: uppercase;
        }

        .eoi-qualified-card__metrics strong {
            color: var(--eoi-ink);
            display: block;
            font-size: 14px;
            margin-top: 2px;
        }

        .eoi-qualified-card__route {
            align-items: center;
            display: grid;
            gap: 0 8px;
            grid-template-columns: 1fr auto;
            margin-top: auto;
            padding: 12px 14px;
        }

        .eoi-qualified-card__route > span {
            color: #64748b;
            font-size: 9px;
            font-weight: 700;
        }

        .eoi-qualified-card__route > span i {
            color: var(--qualified-accent);
        }

        .eoi-qualified-card__route > strong {
            color: var(--qualified-accent);
            font-size: 11px;
            grid-column: 1;
        }

        .eoi-qualified-card__route > a {
            align-items: center;
            color: #1d4ed8;
            display: inline-flex;
            font-size: 10px;
            font-weight: 800;
            gap: 5px;
            grid-column: 2;
            grid-row: 1 / 3;
            text-decoration: none;
            white-space: nowrap;
        }

        .eoi-qualified-card__route > a:hover {
            color: #1e3a8a;
            text-decoration: underline;
        }

        .eoi-qualified-empty {
            align-items: center;
            background: #fbfefc;
            display: grid;
            gap: 13px;
            grid-template-columns: auto 1fr auto;
            min-height: 116px;
            padding: 22px 26px;
        }

        .eoi-qualified-empty > span:first-child {
            align-items: center;
            background: #ecfdf3;
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            color: #15803d;
            display: inline-flex;
            font-size: 20px;
            height: 46px;
            justify-content: center;
            width: 46px;
        }

        .eoi-qualified-empty strong {
            color: #173b25;
            display: block;
            font-size: 13px;
        }

        .eoi-qualified-empty p {
            color: #667085;
            font-size: 11px;
            margin: 3px 0 0;
        }

        .eoi-qualified-empty__progress {
            background: #fff;
            border: 1px solid #cce7d5;
            border-radius: 999px;
            color: #527060;
            font-size: 10px;
            font-weight: 800;
            padding: 7px 10px;
            white-space: nowrap;
        }

        .eoi-qualified-totals .eoi-qualified-total--stopped {
            border-color: #fecaca;
            color: #991b1b;
        }

        .eoi-qualified-totals .eoi-qualified-total--pending {
            border-color: #fde68a;
            color: #92400e;
        }

        .eoi-decision-summary-grid {
            background: #f8fafc;
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            padding: 16px;
        }

        .eoi-decision-group {
            --decision-accent: #15803d;
            --decision-border: #bbf7d0;
            --decision-soft: #f0fdf4;
            background: #fff;
            border: 1px solid var(--decision-border);
            border-radius: 13px;
            box-shadow: 0 8px 18px rgba(15, 23, 42, .045);
            min-width: 0;
            overflow: hidden;
        }

        .eoi-decision-group--stopped {
            --decision-accent: #b42318;
            --decision-border: #fecaca;
            --decision-soft: #fff1f0;
        }

        .eoi-decision-group--pending {
            --decision-accent: #b45309;
            --decision-border: #fde68a;
            --decision-soft: #fffbeb;
            grid-column: 1 / -1;
        }

        .eoi-decision-group__header {
            align-items: center;
            background: var(--decision-soft);
            border-bottom: 1px solid var(--decision-border);
            display: grid;
            gap: 10px;
            grid-template-columns: auto minmax(0, 1fr) auto;
            padding: 13px 14px;
        }

        .eoi-decision-group__icon {
            align-items: center;
            background: #fff;
            border: 1px solid var(--decision-border);
            border-radius: 9px;
            color: var(--decision-accent);
            display: inline-flex;
            font-size: 16px;
            height: 36px;
            justify-content: center;
            width: 36px;
        }

        .eoi-decision-group__header h6 {
            color: var(--decision-accent);
            font-size: 13px;
            font-weight: 850;
            margin: 0 0 2px;
        }

        .eoi-decision-group__header p {
            color: #667085;
            font-size: 9px;
            line-height: 1.4;
            margin: 0;
        }

        .eoi-decision-group__header > strong {
            align-items: center;
            background: var(--decision-accent);
            border-radius: 999px;
            color: #fff;
            display: inline-flex;
            font-size: 13px;
            height: 30px;
            justify-content: center;
            min-width: 30px;
            padding: 0 8px;
        }

        .eoi-decision-list {
            list-style: none;
            margin: 0;
            max-height: 330px;
            overflow: auto;
            padding: 0;
        }

        .eoi-decision-person {
            align-items: center;
            display: grid;
            gap: 10px;
            grid-template-columns: auto minmax(0, 1fr) auto auto;
            min-width: 0;
            padding: 11px 13px;
        }

        .eoi-decision-person + .eoi-decision-person {
            border-top: 1px solid #e7edf3;
        }

        .eoi-decision-avatar {
            align-items: center;
            background: var(--decision-soft);
            border: 1px solid var(--decision-border);
            border-radius: 9px;
            color: var(--decision-accent);
            display: inline-flex;
            flex: 0 0 38px;
            font-size: 13px;
            font-weight: 900;
            height: 38px;
            justify-content: center;
            width: 38px;
        }

        .eoi-decision-identity,
        .eoi-decision-result {
            min-width: 0;
        }

        .eoi-decision-identity strong {
            color: var(--eoi-ink);
            display: block;
            font-size: 12px;
            font-weight: 800;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .eoi-decision-identity small,
        .eoi-decision-result > small {
            color: #667085;
            display: block;
            font-size: 8.5px;
            margin-top: 3px;
        }

        .eoi-decision-result {
            text-align: right;
        }

        .eoi-decision-result .eoi-outcome {
            justify-content: center;
            white-space: nowrap;
        }

        .eoi-decision-evidence {
            align-items: center;
            border-radius: 7px;
            color: #1d4ed8;
            display: inline-flex;
            font-size: 9px;
            font-weight: 800;
            gap: 4px;
            padding: 6px;
            text-decoration: none;
            white-space: nowrap;
        }

        .eoi-decision-evidence:hover {
            background: #eff6ff;
            color: #1e3a8a;
            text-decoration: underline;
        }

        .eoi-decision-evidence:focus-visible {
            outline: 3px solid rgba(37, 99, 235, .35);
            outline-offset: 2px;
        }

        .eoi-decision-empty {
            align-items: center;
            color: #667085;
            display: flex;
            font-size: 10px;
            gap: 8px;
            min-height: 70px;
            padding: 16px;
        }

        .eoi-decision-empty i {
            color: var(--decision-accent);
            font-size: 15px;
        }

        .eoi-executive-overview {
            align-items: stretch;
            background: linear-gradient(130deg, #101c33 0%, #172b46 58%, #143b45 100%);
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 15px;
            box-shadow: 0 16px 32px rgba(15, 23, 42, .13);
            color: #fff;
            display: grid;
            gap: 26px;
            grid-template-columns: minmax(0, 1fr) 300px;
            margin-bottom: 14px;
            overflow: hidden;
            padding: 23px 25px;
            position: relative;
        }

        .eoi-executive-overview::after {
            background: #14b8a6;
            border-radius: 50%;
            content: '';
            filter: blur(45px);
            height: 150px;
            opacity: .14;
            position: absolute;
            right: -40px;
            top: -70px;
            width: 210px;
        }

        .eoi-overview-main,
        .eoi-overview-progress {
            position: relative;
            z-index: 1;
        }

        .eoi-overview-badge {
            align-items: center;
            background: rgba(45, 212, 191, .12);
            border: 1px solid rgba(94, 234, 212, .25);
            border-radius: 999px;
            color: #99f6e4;
            display: inline-flex;
            font-size: 9px;
            font-weight: 850;
            gap: 6px;
            letter-spacing: .07em;
            padding: 5px 9px;
            text-transform: uppercase;
        }

        .eoi-overview-main h5 {
            color: #fff;
            font-size: 19px;
            font-weight: 850;
            letter-spacing: -.018em;
            margin: 11px 0 6px;
        }

        .eoi-overview-main > p {
            color: #cbd5e1;
            font-size: 12px;
            line-height: 1.55;
            margin: 0;
            max-width: 850px;
        }

        .eoi-overview-main > p strong {
            color: #fda4af;
        }

        .eoi-overview-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 7px 15px;
            margin-top: 15px;
        }

        .eoi-overview-meta span {
            align-items: center;
            color: #aebed0;
            display: inline-flex;
            font-size: 9px;
            gap: 5px;
        }

        .eoi-overview-meta i {
            color: #5eead4;
        }

        .eoi-overview-progress {
            align-self: center;
            background: rgba(255, 255, 255, .07);
            border: 1px solid rgba(255, 255, 255, .11);
            border-radius: 12px;
            padding: 15px;
        }

        .eoi-overview-progress__top {
            align-items: baseline;
            display: flex;
            justify-content: space-between;
        }

        .eoi-overview-progress__top span {
            color: #cbd5e1;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .eoi-overview-progress__top strong {
            color: #fff;
            font-size: 19px;
        }

        .eoi-overview-progress__track {
            background: rgba(255, 255, 255, .12);
            border-radius: 999px;
            display: block;
            height: 8px;
            margin: 10px 0 7px;
            overflow: hidden;
        }

        .eoi-overview-progress__track > span {
            background: linear-gradient(90deg, #4ade80, #2dd4bf);
            border-radius: inherit;
            display: block;
            height: 100%;
        }

        .eoi-overview-progress small {
            color: #aebed0;
            font-size: 9px;
        }

        .eoi-stage-overview {
            display: grid;
            gap: 11px;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            margin-bottom: 17px;
        }

        .eoi-stage-card {
            --stage-accent: #2563eb;
            --stage-soft: #eff6ff;
            align-items: center;
            background: #fff;
            border: 1px solid var(--eoi-line);
            border-radius: 11px;
            box-shadow: 0 7px 18px rgba(15, 23, 42, .04);
            display: grid;
            gap: 2px 10px;
            grid-template-columns: auto 1fr;
            min-height: 94px;
            padding: 13px 14px;
        }

        .eoi-stage-card--advance { --stage-accent: #15803d; --stage-soft: #f0fdf4; }
        .eoi-stage-card--pending { --stage-accent: #b45309; --stage-soft: #fffbeb; }
        .eoi-stage-card--stopped { --stage-accent: #b42318; --stage-soft: #fff1f0; }

        .eoi-stage-card__icon {
            align-items: center;
            background: var(--stage-soft);
            border-radius: 9px;
            color: var(--stage-accent);
            display: inline-flex;
            grid-row: 1;
            height: 36px;
            justify-content: center;
            width: 36px;
        }

        .eoi-stage-card > div {
            align-items: baseline;
            display: flex;
            gap: 8px;
            justify-content: space-between;
            min-width: 0;
        }

        .eoi-stage-card > div span {
            color: #667085;
            font-size: 9px;
            font-weight: 850;
            letter-spacing: .045em;
            text-transform: uppercase;
        }

        .eoi-stage-card > div strong {
            color: var(--stage-accent);
            font-size: 23px;
            line-height: 1;
        }

        .eoi-stage-card > small {
            color: #8792a3;
            font-size: 9px;
            grid-column: 1 / -1;
            line-height: 1.35;
            margin-top: 6px;
        }

        .eoi-rule-card {
            align-items: center;
            background: linear-gradient(135deg, #172033 0%, #263650 100%);
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: 14px;
            box-shadow: 0 14px 28px rgba(15, 23, 42, .12);
            color: #fff;
            display: grid;
            gap: 18px;
            grid-template-columns: auto minmax(220px, .85fr) minmax(480px, 1.6fr);
            margin-bottom: 16px;
            overflow: hidden;
            padding: 20px;
            position: relative;
        }

        .eoi-rule-card::after {
            background: #14b8a6;
            border-radius: 999px;
            content: '';
            filter: blur(28px);
            height: 100px;
            opacity: .16;
            position: absolute;
            right: -25px;
            top: -40px;
            width: 180px;
        }

        .eoi-rule-icon {
            align-items: center;
            background: rgba(20, 184, 166, .14);
            border: 1px solid rgba(94, 234, 212, .22);
            border-radius: 12px;
            color: #5eead4;
            display: inline-flex;
            font-size: 25px;
            height: 54px;
            justify-content: center;
            width: 54px;
        }

        .eoi-rule-copy {
            min-width: 0;
        }

        .eoi-rule-copy .eoi-eyebrow {
            color: #5eead4;
        }

        .eoi-rule-copy h5 {
            color: #fff;
            font-size: 17px;
            font-weight: 800;
            margin: 5px 0 6px;
        }

        .eoi-rule-copy p {
            color: #cbd5e1;
            font-size: 12px;
            line-height: 1.55;
            margin: 0;
        }

        .eoi-rule-copy p strong {
            color: #fff;
        }

        .eoi-rule-path {
            display: grid;
            gap: 8px;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            position: relative;
            z-index: 1;
        }

        .eoi-rule-step {
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .1);
            border-radius: 9px;
            min-width: 0;
            padding: 11px 12px;
        }

        .eoi-rule-step span,
        .eoi-rule-step small {
            color: #aab8ca;
            display: block;
            font-size: 9px;
            line-height: 1.35;
        }

        .eoi-rule-step strong {
            display: block;
            font-size: 12px;
            margin: 4px 0;
        }

        .eoi-rule-step--success { border-top: 3px solid #4ade80; }
        .eoi-rule-step--warning { border-top: 3px solid #fbbf24; }
        .eoi-rule-step--danger { border-top: 3px solid #fb7185; }

        .eoi-kpi-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            margin-bottom: 16px;
        }

        .eoi-kpi {
            align-items: flex-start;
            background: #fff;
            border: 1px solid var(--eoi-line);
            border-radius: 11px;
            box-shadow: 0 8px 20px rgba(15, 23, 42, .04);
            display: flex;
            gap: 10px;
            min-height: 116px;
            padding: 15px 13px;
            position: relative;
        }

        .eoi-kpi::after {
            background: var(--kpi-color, var(--eoi-blue));
            border-radius: 0 0 8px 8px;
            bottom: 0;
            content: '';
            height: 3px;
            left: 14px;
            position: absolute;
            right: 14px;
        }

        .eoi-kpi-icon {
            align-items: center;
            background: var(--kpi-soft, #eff6ff);
            border-radius: 8px;
            color: var(--kpi-color, var(--eoi-blue));
            display: inline-flex;
            flex: 0 0 32px;
            height: 32px;
            justify-content: center;
        }

        .eoi-kpi > div {
            min-width: 0;
        }

        .eoi-kpi > div > span {
            color: var(--eoi-muted);
            display: block;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .04em;
            line-height: 1.3;
            text-transform: uppercase;
        }

        .eoi-kpi strong {
            color: var(--eoi-ink);
            display: block;
            font-size: 24px;
            line-height: 1;
            margin: 7px 0 5px;
        }

        .eoi-kpi small {
            color: #98a2b3;
            display: block;
            font-size: 9px;
            line-height: 1.3;
        }

        .eoi-kpi--total { --kpi-color: #2563eb; --kpi-soft: #eff6ff; }
        .eoi-kpi--advance { --kpi-color: #0f766e; --kpi-soft: #ecfdf5; }
        .eoi-kpi--qualified { --kpi-color: #15803d; --kpi-soft: #f0fdf4; }
        .eoi-kpi--average { --kpi-color: #b45309; --kpi-soft: #fffbeb; }
        .eoi-kpi--not-qualified { --kpi-color: #b42318; --kpi-soft: #fff1f0; }
        .eoi-kpi--pending { --kpi-color: #64748b; --kpi-soft: #f1f5f9; }

        .eoi-applicant-panel {
            background: #fff;
            border: 1px solid var(--eoi-line);
            border-radius: 14px;
            box-shadow: 0 14px 30px rgba(15, 23, 42, .05);
            overflow: hidden;
        }

        .eoi-panel-heading {
            align-items: flex-start;
            border-bottom: 1px solid var(--eoi-line);
            display: flex;
            gap: 18px;
            justify-content: space-between;
            padding: 18px 20px;
        }

        .eoi-panel-heading h5 {
            color: var(--eoi-ink);
            font-size: 18px;
            font-weight: 800;
            margin: 4px 0;
        }

        .eoi-panel-heading p {
            color: var(--eoi-muted);
            font-size: 12px;
            margin: 0;
        }

        .eoi-generated-at {
            align-items: center;
            background: var(--eoi-soft);
            border: 1px solid var(--eoi-line);
            border-radius: 8px;
            display: grid;
            flex: 0 0 auto;
            gap: 0 8px;
            grid-template-columns: auto 1fr;
            padding: 8px 11px;
        }

        .eoi-generated-at i {
            color: var(--eoi-teal);
            grid-row: 1 / 3;
        }

        .eoi-generated-at span {
            color: var(--eoi-muted);
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .eoi-generated-at strong {
            color: var(--eoi-ink);
            font-size: 11px;
        }

        .eoi-filter-bar {
            align-items: end;
            background: #f8fafc;
            border-bottom: 1px solid var(--eoi-line);
            display: grid;
            gap: 10px;
            grid-template-columns: minmax(220px, 1.3fr) minmax(170px, .75fr) minmax(170px, .75fr) auto;
            padding: 14px 20px;
        }

        .eoi-filter-bar label {
            color: var(--eoi-slate);
            display: block;
            font-size: 10px;
            font-weight: 800;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .eoi-filter-bar .form-control {
            border-color: #cbd5e1;
            border-radius: 7px;
            color: var(--eoi-ink);
            font-size: 12px;
            height: 38px;
        }

        .eoi-filter-bar .form-control:focus {
            border-color: var(--eoi-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .1);
        }

        .eoi-input-wrap {
            position: relative;
        }

        .eoi-input-wrap i {
            color: #98a2b3;
            left: 12px;
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
        }

        .eoi-input-wrap input {
            padding-left: 35px;
        }

        .eoi-filter-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .eoi-filter-actions .btn {
            min-height: 38px;
            white-space: nowrap;
        }

        .eoi-list-status {
            background: #fff;
            border-bottom: 1px solid var(--eoi-line);
            color: var(--eoi-muted);
            font-size: 10px;
            font-weight: 700;
            padding: 8px 20px;
            text-align: right;
        }

        .eoi-applicant-list {
            background: var(--eoi-soft);
            display: grid;
            gap: 10px;
            padding: 14px;
        }

        .eoi-applicant[hidden] {
            display: none !important;
        }

        .eoi-applicant {
            background: #fff;
            border: 1px solid var(--eoi-line);
            border-left: 4px solid #94a3b8;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, .035);
            overflow: hidden;
            scroll-margin-top: 100px;
        }

        .eoi-applicant--fully_qualified { border-left-color: var(--eoi-green); }
        .eoi-applicant--average_qualified { border-left-color: #d97706; }
        .eoi-applicant--not_qualified { border-left-color: var(--eoi-red); }
        .eoi-applicant--pending { border-left-color: #64748b; }

        .eoi-applicant[open] {
            border-color: #b8c7d9;
            box-shadow: 0 12px 28px rgba(15, 23, 42, .07);
        }

        .eoi-applicant > summary {
            align-items: center;
            cursor: pointer;
            display: grid;
            gap: 16px;
            grid-template-columns: minmax(230px, 1.25fr) minmax(180px, .8fr) auto minmax(175px, .75fr) auto;
            list-style: none;
            min-height: 88px;
            padding: 13px 15px;
            transition: background-color .2s ease;
        }

        .eoi-applicant > summary::-webkit-details-marker {
            display: none;
        }

        .eoi-applicant > summary:hover {
            background: #fbfcfe;
        }

        .eoi-applicant > summary:focus-visible {
            box-shadow: inset 0 0 0 3px rgba(37, 99, 235, .22);
            outline: none;
        }

        .eoi-applicant[open] > summary {
            background: #fbfcfe;
            border-bottom: 1px solid var(--eoi-line);
        }

        .eoi-applicant-identity {
            align-items: center;
            display: flex;
            gap: 11px;
            min-width: 0;
        }

        .eoi-applicant-avatar {
            align-items: center;
            background: #eef6ff;
            border: 1px solid #dbeafe;
            border-radius: 9px;
            color: var(--eoi-blue);
            display: inline-flex;
            flex: 0 0 38px;
            height: 38px;
            justify-content: center;
        }

        .eoi-applicant-identity > span:last-child {
            min-width: 0;
        }

        .eoi-applicant-identity strong {
            color: var(--eoi-ink);
            display: block;
            font-size: 14px;
            line-height: 1.35;
        }

        .eoi-applicant-identity small {
            color: var(--eoi-muted);
            display: block;
            font-size: 11px;
            line-height: 1.45;
            margin-top: 3px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .eoi-panel-progress-label {
            align-items: baseline;
            display: flex;
            gap: 8px;
            justify-content: space-between;
        }

        .eoi-panel-progress-label span,
        .eoi-panel-progress small {
            color: var(--eoi-muted);
            font-size: 9px;
        }

        .eoi-panel-progress-label strong {
            color: var(--eoi-ink);
            font-size: 10px;
        }

        .eoi-progress-track {
            background: #e8edf3;
            border-radius: 999px;
            display: block;
            height: 6px;
            margin: 6px 0 5px;
            overflow: hidden;
        }

        .eoi-progress-track > span {
            background: linear-gradient(90deg, var(--eoi-blue), #14b8a6);
            border-radius: inherit;
            display: block;
            height: 100%;
            min-width: 0;
        }

        .eoi-panel-progress small {
            align-items: center;
            display: flex;
            gap: 7px;
            justify-content: space-between;
        }

        .eoi-panel-label {
            border-radius: 999px;
            font-size: 8px;
            font-weight: 800;
            padding: 2px 6px;
            text-transform: uppercase;
        }

        .eoi-panel-label--complete {
            background: var(--eoi-green-soft);
            color: var(--eoi-green);
        }

        .eoi-panel-label--incomplete {
            background: #f1f5f9;
            color: #64748b;
        }

        .eoi-decision-totals {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            justify-content: center;
        }

        .eoi-count {
            border: 1px solid transparent;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 700;
            padding: 5px 7px;
            white-space: nowrap;
        }

        .eoi-count b {
            font-size: 8px;
            letter-spacing: .04em;
        }

        .eoi-count--qualified { background: var(--eoi-green-soft); border-color: #bbf7d0; color: var(--eoi-green); }
        .eoi-count--average { background: var(--eoi-amber-soft); border-color: #fde68a; color: var(--eoi-amber); }
        .eoi-count--not-qualified { background: var(--eoi-red-soft); border-color: #fecaca; color: var(--eoi-red); }

        .eoi-applicant-outcome {
            align-items: flex-end;
            display: flex;
            flex-direction: column;
            gap: 5px;
            text-align: right;
        }

        .eoi-applicant-outcome small {
            color: var(--eoi-muted);
            font-size: 9px;
        }

        .eoi-applicant-outcome small strong {
            color: var(--eoi-ink);
        }

        .eoi-outcome {
            align-items: center;
            border: 1px solid transparent;
            border-radius: 999px;
            display: inline-flex;
            font-size: 9px;
            font-weight: 800;
            gap: 5px;
            line-height: 1.2;
            padding: 5px 8px;
            white-space: nowrap;
        }

        .eoi-outcome--fully_qualified { background: var(--eoi-green-soft); border-color: #bbf7d0; color: var(--eoi-green); }
        .eoi-outcome--average_qualified { background: var(--eoi-amber-soft); border-color: #fde68a; color: var(--eoi-amber); }
        .eoi-outcome--not_qualified { background: var(--eoi-red-soft); border-color: #fecaca; color: var(--eoi-red); }
        .eoi-outcome--pending { background: #f1f5f9; border-color: #dbe3ec; color: #64748b; }

        .eoi-summary-chevron {
            align-items: center;
            background: var(--eoi-soft);
            border: 1px solid var(--eoi-line);
            border-radius: 7px;
            color: var(--eoi-slate);
            display: inline-flex;
            height: 29px;
            justify-content: center;
            transition: transform .2s ease;
            width: 29px;
        }

        .eoi-applicant[open] .eoi-summary-chevron {
            transform: rotate(180deg);
        }

        .eoi-applicant-detail {
            background: #fff;
            padding: 16px;
        }

        .eoi-determination {
            align-items: center;
            background: var(--determination-soft, #f8fafc);
            border: 1px solid var(--determination-line, var(--eoi-line));
            border-left: 4px solid var(--determination-color, #64748b);
            border-radius: 9px;
            display: grid;
            gap: 12px;
            grid-template-columns: auto 1fr auto;
            margin-bottom: 12px;
            padding: 13px 14px;
        }

        .eoi-determination--fully_qualified { --determination-color: var(--eoi-green); --determination-soft: var(--eoi-green-soft); --determination-line: #bbf7d0; }
        .eoi-determination--average_qualified { --determination-color: var(--eoi-amber); --determination-soft: var(--eoi-amber-soft); --determination-line: #fde68a; }
        .eoi-determination--not_qualified { --determination-color: var(--eoi-red); --determination-soft: var(--eoi-red-soft); --determination-line: #fecaca; }

        .eoi-determination-icon {
            align-items: center;
            background: #fff;
            border: 1px solid var(--determination-line, var(--eoi-line));
            border-radius: 8px;
            color: var(--determination-color, #64748b);
            display: inline-flex;
            height: 36px;
            justify-content: center;
            width: 36px;
        }

        .eoi-determination h6 {
            color: var(--eoi-ink);
            font-size: 13px;
            font-weight: 800;
            margin: 3px 0;
        }

        .eoi-determination p {
            color: var(--eoi-slate);
            font-size: 10px;
            line-height: 1.45;
            margin: 0;
        }

        .eoi-determination-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            justify-content: flex-end;
        }

        .eoi-determination-meta span {
            background: rgba(255, 255, 255, .75);
            border: 1px solid var(--determination-line, var(--eoi-line));
            border-radius: 999px;
            color: var(--eoi-slate);
            font-size: 9px;
            font-weight: 700;
            padding: 4px 7px;
        }

        .eoi-detail-stats {
            display: grid;
            gap: 8px;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            margin-bottom: 14px;
        }

        .eoi-detail-stats > div {
            background: var(--eoi-soft);
            border: 1px solid var(--eoi-line);
            border-radius: 8px;
            padding: 9px 10px;
        }

        .eoi-detail-stats span {
            color: var(--eoi-muted);
            display: block;
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .eoi-detail-stats strong {
            color: var(--eoi-ink);
            display: block;
            font-size: 12px;
            margin-top: 4px;
        }

        .eoi-text-warning { color: var(--eoi-amber) !important; }

        .eoi-template-stack {
            display: grid;
            gap: 14px;
        }

        .eoi-template-card {
            border: 1px solid var(--eoi-line);
            border-radius: 10px;
            overflow: hidden;
        }

        .eoi-template-header {
            align-items: flex-start;
            background: linear-gradient(110deg, #f8fafc, #f0f7f7);
            border-bottom: 1px solid var(--eoi-line);
            display: flex;
            gap: 15px;
            justify-content: space-between;
            padding: 14px 16px;
        }

        .eoi-template-header h6 {
            align-items: center;
            color: var(--eoi-ink);
            display: flex;
            font-size: 14px;
            font-weight: 800;
            gap: 7px;
            margin: 4px 0 0;
        }

        .eoi-template-header h6 i {
            color: var(--eoi-teal);
        }

        .eoi-template-header p {
            color: var(--eoi-muted);
            font-size: 10px;
            line-height: 1.45;
            margin: 5px 0 0;
            max-width: 700px;
        }

        .eoi-template-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            justify-content: flex-end;
        }

        .eoi-template-meta span {
            background: #fff;
            border: 1px solid var(--eoi-line);
            border-radius: 999px;
            color: var(--eoi-muted);
            font-size: 9px;
            padding: 5px 8px;
            white-space: nowrap;
        }

        .eoi-template-meta span strong {
            color: var(--eoi-ink);
        }

        .eoi-template-meta span.is-complete {
            background: var(--eoi-green-soft);
            border-color: #bbf7d0;
            color: var(--eoi-green);
        }

        .eoi-template-meta span.is-complete strong {
            color: var(--eoi-green);
        }

        .eoi-member-section,
        .eoi-matrix-section {
            padding: 14px 16px;
        }

        .eoi-member-section {
            border-bottom: 1px solid var(--eoi-line);
        }

        .eoi-subsection-title {
            align-items: flex-end;
            display: flex;
            gap: 15px;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .eoi-subsection-title h6 {
            color: var(--eoi-ink);
            font-size: 12px;
            font-weight: 800;
            margin: 3px 0 0;
        }

        .eoi-subsection-title > span {
            color: var(--eoi-muted);
            font-size: 9px;
            font-weight: 700;
        }

        .eoi-member-grid {
            display: grid;
            gap: 8px;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        }

        .eoi-member {
            background: #fbfcfe;
            border: 1px solid var(--eoi-line);
            border-left: 3px solid #94a3b8;
            border-radius: 8px;
            padding: 10px;
        }

        .eoi-member--complete { border-left-color: var(--eoi-green); }
        .eoi-member--incomplete { border-left-color: var(--eoi-amber); }
        .eoi-member--pending { border-left-color: #94a3b8; }

        .eoi-member-head {
            align-items: center;
            display: grid;
            gap: 8px;
            grid-template-columns: auto 1fr auto;
        }

        .eoi-member-icon {
            align-items: center;
            background: #eef2f6;
            border-radius: 7px;
            color: var(--eoi-slate);
            display: inline-flex;
            height: 31px;
            justify-content: center;
            width: 31px;
        }

        .eoi-member--complete .eoi-member-icon {
            background: var(--eoi-green-soft);
            color: var(--eoi-green);
        }

        .eoi-member-head > div {
            min-width: 0;
        }

        .eoi-member-head strong {
            color: var(--eoi-ink);
            display: block;
            font-size: 12px;
            overflow-wrap: anywhere;
        }

        .eoi-member-head small {
            color: var(--eoi-muted);
            display: block;
            font-size: 10px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .eoi-member-status {
            background: #eef2f6;
            border-radius: 999px;
            color: #64748b;
            font-size: 9px;
            font-weight: 800;
            padding: 4px 7px;
            white-space: nowrap;
        }

        .eoi-member--complete .eoi-member-status { background: var(--eoi-green-soft); color: var(--eoi-green); }
        .eoi-member--incomplete .eoi-member-status { background: var(--eoi-amber-soft); color: var(--eoi-amber); }

        .eoi-member-foot {
            align-items: center;
            border-top: 1px solid #e8edf3;
            display: flex;
            gap: 8px;
            justify-content: space-between;
            margin-top: 8px;
            padding-top: 7px;
        }

        .eoi-member-foot > span:first-child {
            color: var(--eoi-muted);
            font-size: 9px;
        }

        .eoi-member-counts {
            display: flex;
            gap: 4px;
        }

        .eoi-member-counts b {
            border-radius: 4px;
            font-size: 8px;
            padding: 2px 4px;
        }

        .eoi-member-counts .is-q { background: var(--eoi-green-soft); color: var(--eoi-green); }
        .eoi-member-counts .is-aq { background: var(--eoi-amber-soft); color: var(--eoi-amber); }
        .eoi-member-counts .is-nq { background: var(--eoi-red-soft); color: var(--eoi-red); }

        .eoi-import-note {
            color: var(--eoi-muted);
            display: block;
            font-size: 8px;
            margin-top: 6px;
        }

        .eoi-matrix-legend {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 4px 6px;
        }

        .eoi-matrix-legend > span:not(.eoi-decision) {
            color: var(--eoi-muted);
            font-size: 8px;
            margin-right: 4px;
        }

        .eoi-matrix-wrap {
            border: 1px solid var(--eoi-line);
            border-radius: 8px;
            max-height: 620px;
        }

        .eoi-matrix-table {
            border-collapse: separate;
            border-spacing: 0;
            margin: 0;
            min-width: 850px;
        }

        .eoi-matrix-table thead th {
            --bs-table-color: #fff;
            --bs-table-color-state: #fff;
            background: #172033;
            border-color: #344054;
            color: #fff !important;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .03em;
            padding: 9px 10px;
            position: sticky;
            text-transform: uppercase;
            top: 0;
            vertical-align: bottom;
            z-index: 2;
        }

        .eoi-matrix-table thead th:first-child {
            left: 0;
            z-index: 3;
        }

        .eoi-matrix-table thead th small {
            color: #fff !important;
            display: block;
            font-size: 9px;
            font-weight: 600;
            letter-spacing: 0;
            margin-top: 3px;
            text-transform: none;
        }

        .eoi-criterion-column { min-width: 230px; width: 26%; }
        .eoi-result-column { min-width: 145px; width: 15%; }
        .eoi-evaluator-column { min-width: 190px; }

        .eoi-matrix-table tbody th,
        .eoi-matrix-table tbody td {
            border-color: #e5eaf0;
            padding: 10px;
            vertical-align: top;
        }

        .eoi-matrix-table tbody tr:nth-child(even) > * {
            background: #fbfcfe;
        }

        .eoi-matrix-table tbody tr:hover > * {
            background: #f5f9ff;
        }

        .eoi-criterion-cell {
            background: #fff;
            left: 0;
            position: sticky;
            z-index: 1;
        }

        .eoi-criterion-section {
            color: var(--eoi-teal);
            display: block;
            font-size: 8px;
            font-weight: 800;
            letter-spacing: .04em;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .eoi-criterion-cell strong {
            color: var(--eoi-ink);
            display: block;
            font-size: 11px;
            line-height: 1.4;
        }

        .eoi-criterion-cell small {
            color: var(--eoi-muted);
            display: block;
            font-size: 10px;
            font-weight: 400;
            line-height: 1.45;
            margin-top: 4px;
        }

        .eoi-result-cell .eoi-outcome {
            white-space: normal;
        }

        .eoi-result-counts {
            color: var(--eoi-muted);
            display: block;
            font-size: 8px;
            line-height: 1.5;
            margin-top: 6px;
        }

        .eoi-decision {
            align-items: center;
            border: 1px solid transparent;
            border-radius: 5px;
            display: inline-flex;
            font-size: 8px;
            font-weight: 700;
            gap: 5px;
            line-height: 1.2;
            padding: 4px 6px;
        }

        .eoi-decision b {
            font-size: 8px;
        }

        .eoi-decision--qualified { background: var(--eoi-green-soft); border-color: #bbf7d0; color: var(--eoi-green); }
        .eoi-decision--average { background: var(--eoi-amber-soft); border-color: #fde68a; color: var(--eoi-amber); }
        .eoi-decision--not-qualified { background: var(--eoi-red-soft); border-color: #fecaca; color: var(--eoi-red); }
        .eoi-decision--pending { background: #f1f5f9; border-color: #dbe3ec; color: #64748b; }

        .eoi-assessment-cell p {
            color: var(--eoi-slate);
            font-size: 10px;
            line-height: 1.5;
            margin: 7px 0 0;
            white-space: pre-line;
        }

        .eoi-no-comment {
            color: #98a2b3;
            display: block;
            font-size: 9px;
            font-style: italic;
            margin-top: 7px;
        }

        .eoi-inline-empty,
        .eoi-empty-state {
            align-items: center;
            color: var(--eoi-muted);
            display: flex;
            font-size: 10px;
            gap: 7px;
            justify-content: center;
            padding: 18px;
            text-align: center;
        }

        .eoi-inline-empty--large {
            flex-direction: column;
            min-height: 130px;
        }

        .eoi-inline-empty--large i {
            color: #94a3b8;
            font-size: 22px;
        }

        .eoi-inline-empty--large strong {
            color: var(--eoi-ink);
            font-size: 12px;
        }

        .eoi-filter-empty[hidden] {
            display: none !important;
        }

        .eoi-empty-state {
            flex-direction: column;
            min-height: 220px;
        }

        .eoi-empty-state > span {
            align-items: center;
            background: #eef2f6;
            border-radius: 999px;
            color: #64748b;
            display: inline-flex;
            font-size: 23px;
            height: 52px;
            justify-content: center;
            width: 52px;
        }

        .eoi-empty-state h6 {
            color: var(--eoi-ink);
            font-size: 14px;
            font-weight: 800;
            margin: 3px 0 0;
        }

        .eoi-empty-state p {
            margin: 0;
        }

        .eoi-report-footer {
            align-items: center;
            color: var(--eoi-muted);
            display: flex;
            font-size: 10px;
            gap: 12px;
            justify-content: space-between;
            padding: 12px 4px 0;
        }

        .eoi-report-footer > div {
            align-items: center;
            display: flex;
            gap: 6px;
        }

        .eoi-report-footer i {
            color: var(--eoi-teal);
        }

        @media (max-width: 1399.98px) {
            .eoi-stage-overview {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .eoi-kpi-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .eoi-kpi {
                min-height: 100px;
            }

            .eoi-filter-bar {
                grid-template-columns: minmax(220px, 1.2fr) repeat(2, minmax(160px, .7fr));
            }

            .eoi-filter-actions {
                grid-column: 1 / -1;
            }

            .eoi-applicant > summary {
                grid-template-columns: minmax(220px, 1.2fr) minmax(175px, .8fr) auto minmax(165px, .75fr) auto;
                gap: 10px;
            }
        }

        @media (max-width: 1199.98px) {
            .eoi-executive-overview {
                grid-template-columns: minmax(0, 1fr) 250px;
            }

            .eoi-rule-card {
                grid-template-columns: auto 1fr;
            }

            .eoi-rule-path {
                grid-column: 1 / -1;
            }

            .eoi-applicant > summary {
                grid-template-columns: minmax(220px, 1fr) minmax(170px, .75fr) auto;
            }

            .eoi-decision-totals {
                justify-content: flex-start;
            }

            .eoi-applicant-outcome {
                align-items: flex-start;
                text-align: left;
            }

            .eoi-summary-chevron {
                grid-column: 3;
                grid-row: 1 / 3;
                justify-self: end;
            }

            .eoi-detail-stats {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .eoi-qualified-shortlist__header {
                align-items: flex-start;
                flex-direction: column;
                padding: 21px 18px 17px;
            }

            .eoi-qualified-totals {
                justify-content: flex-start;
            }

            .eoi-qualified-grid {
                grid-template-columns: 1fr;
                max-height: none;
                padding: 12px;
            }

            .eoi-qualified-empty {
                grid-template-columns: auto 1fr;
                padding: 18px;
            }

            .eoi-qualified-empty__progress {
                grid-column: 1 / -1;
                justify-self: start;
            }

            .eoi-decision-summary-grid {
                grid-template-columns: 1fr;
                padding: 12px;
            }

            .eoi-decision-group--pending {
                grid-column: auto;
            }

            .eoi-decision-list {
                max-height: none;
                overflow: visible;
            }

            .eoi-decision-person {
                align-items: start;
                grid-template-columns: auto minmax(0, 1fr) auto;
            }

            .eoi-decision-result {
                grid-column: 2 / 4;
                text-align: left;
            }

            .eoi-decision-evidence {
                grid-column: 3;
                grid-row: 1;
            }

            .eoi-executive-overview {
                gap: 18px;
                grid-template-columns: 1fr;
                padding: 20px 18px;
            }

            .eoi-overview-progress {
                width: 100%;
            }

            .eoi-stage-overview {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .eoi-header-actions,
            .eoi-header-actions .btn {
                width: 100%;
            }

            .eoi-header-actions .btn {
                flex: 1 1 0;
            }

            .eoi-rule-card {
                align-items: flex-start;
                grid-template-columns: auto 1fr;
                padding: 16px;
            }

            .eoi-rule-path {
                grid-template-columns: 1fr;
            }

            .eoi-kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .eoi-panel-heading,
            .eoi-template-header,
            .eoi-subsection-title,
            .eoi-report-footer {
                align-items: flex-start;
                flex-direction: column;
            }

            .eoi-generated-at {
                align-self: stretch;
            }

            .eoi-filter-bar {
                grid-template-columns: 1fr;
            }

            .eoi-filter-actions {
                grid-column: auto;
            }

            .eoi-filter-actions .btn {
                flex: 1 1 130px;
            }

            .eoi-list-status {
                text-align: left;
            }

            .eoi-applicant > summary {
                grid-template-columns: 1fr auto;
                padding: 14px;
            }

            .eoi-panel-progress,
            .eoi-decision-totals,
            .eoi-applicant-outcome {
                grid-column: 1 / -1;
            }

            .eoi-summary-chevron {
                grid-column: 2;
                grid-row: 1;
            }

            .eoi-determination {
                align-items: flex-start;
                grid-template-columns: auto 1fr;
            }

            .eoi-determination-meta {
                grid-column: 1 / -1;
                justify-content: flex-start;
            }

            .eoi-template-meta {
                justify-content: flex-start;
            }

            .eoi-matrix-legend {
                margin-top: 5px;
            }
        }

        @media (max-width: 479.98px) {
            .eoi-qualified-heading {
                align-items: flex-start;
            }

            .eoi-qualified-heading__icon {
                flex-basis: 45px;
                font-size: 19px;
                height: 45px;
            }

            .eoi-qualified-heading h5 {
                font-size: 18px;
            }

            .eoi-qualified-card__route {
                align-items: flex-start;
                grid-template-columns: 1fr;
            }

            .eoi-qualified-card__route > a {
                grid-column: 1;
                grid-row: auto;
                margin-top: 8px;
            }

            .eoi-decision-group__header {
                align-items: start;
            }

            .eoi-decision-person {
                grid-template-columns: auto minmax(0, 1fr);
            }

            .eoi-decision-result,
            .eoi-decision-evidence {
                grid-column: 2;
                grid-row: auto;
                justify-self: start;
            }

            .eoi-stage-overview,
            .eoi-kpi-grid,
            .eoi-detail-stats {
                grid-template-columns: 1fr;
            }

            .eoi-kpi {
                min-height: 86px;
            }

            .eoi-applicant-list,
            .eoi-applicant-detail {
                padding: 10px;
            }

            .eoi-member-head {
                grid-template-columns: auto 1fr;
            }

            .eoi-member-status {
                grid-column: 1 / -1;
                justify-self: start;
            }

            .eoi-member-foot {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        @media print {
            @page {
                size: A4 landscape;
                margin: 12mm;
            }

            .content-wrapper {
                margin-left: 0 !important;
            }

            .content-wrapper > header,
            .nxl-header,
            .nxl-navigation,
            .eoi-header-actions,
            .eoi-filter-bar,
            .eoi-list-status,
            .eoi-summary-chevron,
            .eoi-qualified-card__route > a,
            .eoi-decision-evidence {
                display: none !important;
            }

            .eoi-communications,
            .eoi-communication-alert,
            .modal {
                display: none !important;
            }

            .content-wrapper .content {
                min-height: auto !important;
                padding: 0 !important;
            }

            .eoi-report {
                margin: 0 !important;
                max-width: none;
                padding: 0 !important;
            }

            .eoi-qualified-grid {
                max-height: none;
                overflow: visible;
            }

            .eoi-decision-list {
                max-height: none;
                overflow: visible;
            }

            .eoi-report,
            .eoi-applicant-panel,
            .eoi-applicant,
            .eoi-template-card {
                box-shadow: none !important;
            }

            .eoi-applicant > .eoi-applicant-detail {
                display: block !important;
            }

            .eoi-applicant-list {
                padding: 0;
            }

            .eoi-matrix-wrap {
                max-height: none;
                overflow: visible !important;
            }

            .eoi-matrix-table thead th,
            .eoi-criterion-cell {
                position: static;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .eoi-applicant > summary,
            .eoi-summary-chevron {
                transition: none;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const templateInput = document.getElementById('eoiProposalTemplates');
            const templateSelection = document.getElementById('eoiTemplateSelection');

            templateInput?.addEventListener('change', function () {
                const files = Array.from(templateInput.files || []);
                const totalBytes = files.reduce((total, file) => total + file.size, 0);
                const totalMegabytes = (totalBytes / (1024 * 1024)).toFixed(1);

                if (templateSelection) {
                    templateSelection.textContent = files.length
                        ? `${files.length} file(s) selected · ${totalMegabytes} MB combined`
                        : 'No templates selected.';
                }
            });

            const rulesList = document.getElementById('eoiProposalRules');
            const addRuleButton = document.getElementById('eoiAddProposalRule');
            let nextRuleIndex = rulesList
                ? rulesList.querySelectorAll('[data-rule-row]').length
                : 0;

            const refreshRuleRows = () => {
                if (!rulesList) return;
                const rows = Array.from(rulesList.querySelectorAll('[data-rule-row]'));
                rows.forEach((row, index) => {
                    const number = row.querySelector('.eoi-rule-row__number');
                    if (number) number.textContent = String(index + 1);
                    const removeButton = row.querySelector('[data-remove-rule]');
                    if (removeButton) removeButton.disabled = rows.length === 1;
                });
            };

            rulesList?.addEventListener('click', (event) => {
                const removeButton = event.target.closest('[data-remove-rule]');
                if (!removeButton || rulesList.querySelectorAll('[data-rule-row]').length <= 1) return;
                removeButton.closest('[data-rule-row]')?.remove();
                refreshRuleRows();
            });

            addRuleButton?.addEventListener('click', () => {
                if (!rulesList || rulesList.querySelectorAll('[data-rule-row]').length >= 250) return;
                const index = nextRuleIndex++;
                const row = document.createElement('div');
                row.className = 'eoi-rule-row';
                row.dataset.ruleRow = '';
                row.innerHTML = `
                    <span class="eoi-rule-row__number"></span>
                    <div class="eoi-rule-row__fields">
                        <input name="rules[${index}][title]" class="form-control" maxlength="255" required placeholder="Rule title">
                        <textarea name="rules[${index}][description]" class="form-control" rows="2" maxlength="10000" placeholder="What must the applicant do?"></textarea>
                        <div class="eoi-rule-row__options">
                            <select name="rules[${index}][category]" class="form-select form-select-sm"><option value="general">General</option><option value="eligibility">Eligibility</option><option value="document">Document</option><option value="deadline">Deadline</option><option value="channel">Channel</option><option value="declaration">Declaration</option></select>
                            <label><input type="hidden" name="rules[${index}][is_mandatory]" value="0"><input type="checkbox" name="rules[${index}][is_mandatory]" value="1" checked> Mandatory</label>
                            <label><input type="hidden" name="rules[${index}][is_disqualifying]" value="0"><input type="checkbox" name="rules[${index}][is_disqualifying]" value="1"> Can disqualify</label>
                        </div>
                    </div>
                    <button type="button" class="eoi-rule-remove" data-remove-rule aria-label="Remove this rule"><i class="feather-trash-2"></i></button>`;
                rulesList.appendChild(row);
                refreshRuleRows();
                row.querySelector('input')?.focus();
            });
            refreshRuleRows();

            document.querySelectorAll('[data-eoi-send-form]').forEach((form) => {
                form.addEventListener('submit', function (event) {
                    if (form.dataset.submitting === 'true') {
                        event.preventDefault();
                        return;
                    }

                    if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
                        event.preventDefault();
                        return;
                    }

                    form.dataset.submitting = 'true';
                    const submitButton = form.querySelector('button[type="submit"]');
                    if (submitButton) {
                        submitButton.disabled = true;
                        submitButton.innerHTML = `<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>${form.dataset.sendingLabel || 'Sending...'}`;
                    }
                });
            });

            @if (request()->boolean('compose_proposal') || $errors->has('subject') || $errors->has('message') || $errors->has('deadline_at') || $errors->has('rules') || $errors->has('rules.*') || $errors->has('templates') || $errors->has('templates.*'))
                const proposalModalElement = document.getElementById('eoiProposalInvitationModal');
                if (proposalModalElement && window.bootstrap?.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(proposalModalElement).show();
                }
            @endif

            @if (request()->boolean('admin_upload'))
                const firstProposalCandidate = document.querySelector('#technicalProposalWorkspace .tp-candidate');
                if (firstProposalCandidate) {
                    firstProposalCandidate.open = true;
                }
            @endif

            const searchInput = document.getElementById('eoiApplicantSearch');
            const outcomeFilter = document.getElementById('eoiOutcomeFilter');
            const panelFilter = document.getElementById('eoiPanelFilter');
            const resultCount = document.getElementById('eoiResultCount');
            const noResults = document.getElementById('eoiNoResults');
            const expandButton = document.getElementById('eoiExpandAll');
            const collapseButton = document.getElementById('eoiCollapseAll');
            const clearButton = document.getElementById('eoiClearFilters');
            const noResultsClearButton = document.getElementById('eoiNoResultsClear');
            const applicantList = document.getElementById('eoiApplicantList');

            if (!searchInput || !outcomeFilter || !panelFilter || !applicantList) {
                return;
            }

            const applicantCards = Array.from(applicantList.querySelectorAll('.eoi-applicant'));
            const qualifiedApplicantLinks = Array.from(document.querySelectorAll('[data-eoi-open-applicant]'));
            const totalApplicants = applicantCards.length;

            const normalize = (value) => String(value || '').trim().toLocaleLowerCase();

            const applyFilters = () => {
                const query = normalize(searchInput.value);
                const outcome = outcomeFilter.value;
                const panel = panelFilter.value;
                let visibleApplicants = 0;

                applicantCards.forEach((card) => {
                    const matchesSearch = !query || normalize(card.dataset.search).includes(query);
                    const matchesOutcome = outcome === 'all' || card.dataset.outcome === outcome;
                    const matchesPanel = panel === 'all' || card.dataset.panel === panel;
                    const isVisible = matchesSearch && matchesOutcome && matchesPanel;

                    card.hidden = !isVisible;
                    if (isVisible) {
                        visibleApplicants += 1;
                    } else {
                        card.open = false;
                    }
                });

                if (resultCount) {
                    resultCount.textContent = `Showing ${visibleApplicants} of ${totalApplicants} applicants`;
                }

                if (noResults) {
                    noResults.hidden = visibleApplicants !== 0 || totalApplicants === 0;
                }

                applicantList.hidden = visibleApplicants === 0 && totalApplicants > 0;
            };

            const clearFilters = () => {
                searchInput.value = '';
                outcomeFilter.value = 'all';
                panelFilter.value = 'all';
                applyFilters();
                searchInput.focus();
            };

            searchInput.addEventListener('input', applyFilters);
            outcomeFilter.addEventListener('change', applyFilters);
            panelFilter.addEventListener('change', applyFilters);

            expandButton?.addEventListener('click', function () {
                applicantCards.filter((card) => !card.hidden).forEach((card) => {
                    card.open = true;
                });
            });

            collapseButton?.addEventListener('click', function () {
                applicantCards.forEach((card) => {
                    card.open = false;
                });
            });

            clearButton?.addEventListener('click', clearFilters);
            noResultsClearButton?.addEventListener('click', clearFilters);

            qualifiedApplicantLinks.forEach((link) => {
                link.addEventListener('click', function (event) {
                    const applicantId = link.dataset.eoiOpenApplicant;
                    const target = document.getElementById(`eoi-applicant-${applicantId}`);

                    if (!target) {
                        return;
                    }

                    event.preventDefault();
                    searchInput.value = '';
                    outcomeFilter.value = 'all';
                    panelFilter.value = 'all';
                    applyFilters();
                    target.open = true;

                    window.requestAnimationFrame(() => {
                        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                        target.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' });
                        target.querySelector('summary')?.focus({ preventScroll: true });
                        window.history.replaceState(null, '', `#eoi-applicant-${applicantId}`);
                    });
                });
            });

            applyFilters();
        });
    </script>
@endpush
