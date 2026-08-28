@extends('layouts.vendor')

@section('title', 'Evaluation Communication')

@section('content')
    @php
        $communication = $recipient->communication;
        $procurement = $communication->procurement;
        $isProposal = $communication->type === \App\Models\EoiReportCommunication::TYPE_PROPOSAL_INVITATION;
    @endphp

    <div class="vendor-page-head">
        <div>
            <div class="vendor-eyebrow">{{ $isProposal ? 'Qualified Applicant proposal stage' : 'Private EOI outcome' }}</div>
            <h3 class="vendor-page-title">{{ $communication->subject }}</h3>
            <p class="text-muted mb-0">{{ $procurement?->title ?: 'Procurement opportunity' }} &middot; {{ $procurement?->reference_no ?: 'No reference' }}</p>
        </div>
        <a href="{{ route('vendor.eoi-communications.index') }}" class="btn btn-vendor-outline btn-sm"><i class="feather-arrow-left me-1"></i> All notices</a>
    </div>

    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())
        <div class="alert alert-danger"><strong>Please correct the following:</strong><ul class="mb-0 mt-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card vendor-card h-100">
                <div class="card-body p-4">
                    <div class="communication-banner communication-banner--{{ $isProposal ? 'proposal' : 'record' }}">
                        <span><i class="{{ $isProposal ? 'feather-upload-cloud' : 'feather-award' }}"></i></span>
                        <div>
                            <div class="vendor-eyebrow">{{ $isProposal ? 'Invitation issued' : 'Final evaluation released' }}</div>
                            <h4 class="mb-1">{{ $recipient->outcome_label }}</h4>
                            <p class="mb-0">Workflow decision: <strong>{{ $recipient->workflow_decision }}</strong></p>
                        </div>
                    </div>

                    @if ($isProposal)
                        <h5 class="mt-4 mb-2">Message from the procurement team</h5>
                        <div class="communication-message">{!! nl2br(e($communication->message)) !!}</div>

                        <h5 class="mt-4 mb-2">Proposal templates</h5>
                        @forelse ($communication->attachments as $attachment)
                            <a class="secure-file" href="{{ route('vendor.eoi-communications.templates.download', [$recipient, $attachment]) }}">
                                <span><i class="feather-file-text"></i></span>
                                <div><strong>{{ $attachment->original_filename }}</strong><small>{{ strtoupper(pathinfo($attachment->original_filename, PATHINFO_EXTENSION)) }} &middot; {{ number_format($attachment->file_size / 1024, 0) }} KB</small></div>
                                <i class="feather-download"></i>
                            </a>
                        @empty
                            <p class="text-muted small">No separate templates were attached. Follow the instructions in the message above.</p>
                        @endforelse
                    @else
                        <h5 class="mt-4 mb-2">Your evaluation record</h5>
                        <p class="text-muted">The PDF contains your consolidated active-panel record, final outcome, workflow decision, and criterion-level evidence. Evaluator identities are protected as <strong>XXX-XXXX-XXXX</strong>.</p>
                        @if ($recipient->record_file_path)
                            <a href="{{ route('vendor.eoi-communications.record.download', $recipient) }}" class="btn btn-vendor"><i class="feather-download me-1"></i> Download evaluation record PDF</a>
                        @else
                            <div class="alert alert-warning mb-0">The PDF is not available. Please contact support.</div>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card vendor-card mb-3">
                <div class="card-body">
                    <div class="vendor-eyebrow">Application</div>
                    <dl class="communication-details mb-0">
                        <div><dt>Applicant</dt><dd>{{ $recipient->recipient_name }}</dd></div>
                        <div><dt>Outcome</dt><dd>{{ $recipient->outcome_label }}</dd></div>
                        <div><dt>Issued</dt><dd>{{ $communication->created_at->format('d M Y, H:i') }}</dd></div>
                        @if ($recipient->proposal_submitted_at)<div><dt>Proposal submitted</dt><dd>{{ $recipient->proposal_submitted_at->format('d M Y, H:i') }}</dd></div>@endif
                    </dl>
                </div>
            </div>

            @if ($isProposal)
                <div class="card vendor-card">
                    <div class="card-body">
                        <div class="vendor-eyebrow">Secure portal submission</div>
                        <h5 class="mb-2">{{ $recipient->proposal_submitted_at ? 'Add updated documents' : 'Submit your proposal' }}</h5>
                        <p class="text-muted small">Upload PDF, Word, or Excel documents. Your original EOI application will not be changed.</p>
                        <form method="POST" enctype="multipart/form-data" action="{{ route('vendor.eoi-communications.proposal.submit', $recipient) }}">
                            @csrf
                            <div class="mb-3">
                                <label for="proposalMessage" class="form-label small fw-semibold">Cover note <span class="text-muted fw-normal">(optional)</span></label>
                                <textarea id="proposalMessage" name="proposal_message" rows="3" maxlength="2000" class="form-control">{{ old('proposal_message') }}</textarea>
                            </div>
                            <div class="mb-3">
                                <label for="proposalDocuments" class="form-label small fw-semibold">Proposal documents</label>
                                <input id="proposalDocuments" name="documents[]" type="file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx" multiple required>
                                <div class="form-text">Up to 10 files, 10 MB each, 25 MB combined.</div>
                            </div>
                            <button type="submit" class="btn btn-vendor w-100"><i class="feather-upload-cloud me-1"></i> Submit securely</button>
                        </form>

                        @if ($recipient->proposalDocuments->isNotEmpty())
                            <hr>
                            <h6 class="mb-2">Submitted documents</h6>
                            <div class="submitted-files">
                                @foreach ($recipient->proposalDocuments->sortByDesc('created_at') as $document)
                                    <a href="{{ route('vendor.eoi-communications.proposal-documents.download', [$recipient, $document]) }}"><i class="feather-check-circle"></i><span>{{ $document->original_filename }}<small>{{ $document->created_at->format('d M Y, H:i') }}</small></span><i class="feather-download"></i></a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .communication-banner { align-items: center; border: 1px solid; border-radius: 13px; display: flex; gap: 15px; padding: 18px; }
        .communication-banner > span { align-items: center; border-radius: 11px; display: inline-flex; flex: 0 0 48px; font-size: 23px; height: 48px; justify-content: center; }
        .communication-banner--record { background: #f5f8ff; border-color: #cbd9fc; }
        .communication-banner--record > span { background: #e4edff; color: #2563eb; }
        .communication-banner--proposal { background: #f2faf6; border-color: #bfe1ce; }
        .communication-banner--proposal > span { background: #dff5e8; color: #087443; }
        .communication-message { background: #f8faf9; border-left: 3px solid #087443; border-radius: 0 9px 9px 0; color: #344054; padding: 14px 16px; }
        .secure-file { align-items: center; border: 1px solid #dce6e0; border-radius: 9px; color: #172033; display: grid; gap: 10px; grid-template-columns: auto minmax(0,1fr) auto; margin-top: 8px; padding: 10px 12px; text-decoration: none; }
        .secure-file:hover { background: #f7fbf9; border-color: #a8cfb9; color: #087443; }
        .secure-file > span { align-items: center; background: #eef7f2; border-radius: 7px; color: #087443; display: inline-flex; height: 32px; justify-content: center; width: 32px; }
        .secure-file strong, .secure-file small { display: block; }
        .secure-file small { color: #667085; font-size: 10px; }
        .communication-details > div { border-bottom: 1px solid #e9eeeb; padding: 9px 0; }
        .communication-details > div:last-child { border-bottom: 0; padding-bottom: 0; }
        .communication-details dt { color: #667085; font-size: 10px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
        .communication-details dd { color: #172033; font-size: 13px; margin: 2px 0 0; }
        .submitted-files { display: grid; gap: 6px; }
        .submitted-files a { align-items: center; background: #f8faf9; border-radius: 7px; color: #344054; display: grid; font-size: 11px; gap: 7px; grid-template-columns: auto minmax(0,1fr) auto; padding: 7px 9px; text-decoration: none; }
        .submitted-files a > i:first-child { color: #087443; }
        .submitted-files small { color: #667085; display: block; font-size: 9px; }
    </style>
@endpush
