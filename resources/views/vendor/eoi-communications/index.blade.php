@extends('layouts.vendor')

@section('title', 'Evaluation Notices')

@section('content')
    <div class="vendor-page-head">
        <div>
            <div class="vendor-eyebrow">Procurement communications</div>
            <h3 class="vendor-page-title">Evaluation notices &amp; proposal invitations</h3>
            <p class="text-muted mb-0">Review your EOI outcomes, download private records and templates, and submit requested proposals.</p>
        </div>
        <a href="{{ route('vendor.submissions') }}" class="btn btn-vendor-outline btn-sm"><i class="feather-file-text me-1"></i> My Submissions</a>
    </div>

    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div class="notice-list">
        @forelse ($recipients as $recipient)
            @php
                $communication = $recipient->communication;
                $procurement = $communication?->procurement;
                $isProposal = $communication?->type === \App\Models\EoiReportCommunication::TYPE_PROPOSAL_INVITATION;
            @endphp
            <article class="card vendor-card notice-card {{ $recipient->read_at ? '' : 'notice-card--unread' }}">
                <div class="card-body">
                    <div class="notice-card__icon notice-card__icon--{{ $isProposal ? 'proposal' : 'record' }}"><i class="{{ $isProposal ? 'feather-upload-cloud' : 'feather-award' }}"></i></div>
                    <div class="notice-card__body">
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                            <span class="notice-kind">{{ $isProposal ? 'Proposal invitation' : 'Evaluation record' }}</span>
                            @unless ($recipient->read_at)<span class="notice-new">New</span>@endunless
                            @if ($recipient->proposal_submitted_at)<span class="notice-submitted"><i class="feather-check me-1"></i>Proposal submitted</span>@endif
                        </div>
                        <h5 class="mb-1">{{ $communication?->subject ?: 'EOI communication' }}</h5>
                        <p class="text-muted mb-2">{{ $procurement?->title ?: 'Procurement opportunity' }} &middot; {{ $procurement?->reference_no ?: 'No reference' }}</p>
                        <div class="notice-meta">
                            <span><i class="feather-check-circle"></i> {{ $recipient->outcome_label }}</span>
                            <span><i class="feather-navigation"></i> {{ $recipient->workflow_decision }}</span>
                            <span><i class="feather-calendar"></i> {{ $communication?->created_at?->format('d M Y, H:i') }}</span>
                            @if ($isProposal && $communication?->attachments?->isNotEmpty())<span><i class="feather-paperclip"></i> {{ $communication->attachments->count() }} template(s)</span>@endif
                        </div>
                    </div>
                    <a href="{{ route('vendor.eoi-communications.show', $recipient) }}" class="btn btn-vendor btn-sm notice-card__action">
                        {{ $isProposal ? 'Open invitation' : 'View record' }} <i class="feather-arrow-right ms-1"></i>
                    </a>
                </div>
            </article>
        @empty
            <div class="card vendor-card">
                <div class="card-body text-center py-5">
                    <span class="empty-notice-icon"><i class="feather-inbox"></i></span>
                    <h5 class="mt-3">No evaluation notices yet</h5>
                    <p class="text-muted mb-0">Outcome records and proposal invitations will appear here when they are released.</p>
                </div>
            </div>
        @endforelse
    </div>

    @if ($recipients->hasPages())<div class="mt-4">{{ $recipients->links() }}</div>@endif
@endsection

@push('styles')
    <style>
        .notice-list { display: grid; gap: 13px; }
        .notice-card { overflow: hidden; }
        .notice-card--unread { border-left: 4px solid #087443; }
        .notice-card .card-body { align-items: center; display: grid; gap: 15px; grid-template-columns: auto minmax(0, 1fr) auto; padding: 18px; }
        .notice-card__icon, .empty-notice-icon { align-items: center; border-radius: 12px; display: inline-flex; font-size: 22px; height: 48px; justify-content: center; width: 48px; }
        .notice-card__icon--record { background: #eef4ff; color: #2563eb; }
        .notice-card__icon--proposal { background: #ecfdf3; color: #087443; }
        .empty-notice-icon { background: #f1f5f9; color: #64748b; }
        .notice-kind, .notice-new, .notice-submitted { border-radius: 999px; font-size: 10px; font-weight: 800; letter-spacing: .04em; padding: 4px 8px; text-transform: uppercase; }
        .notice-kind { background: #f1f5f9; color: #475569; }
        .notice-new { background: #fff7d6; color: #956500; }
        .notice-submitted { background: #ecfdf3; color: #087443; }
        .notice-meta { color: #667085; display: flex; flex-wrap: wrap; font-size: 11px; gap: 6px 14px; }
        .notice-meta span { align-items: center; display: inline-flex; gap: 4px; }
        @media (max-width: 767.98px) {
            .notice-card .card-body { align-items: flex-start; grid-template-columns: auto minmax(0, 1fr); }
            .notice-card__action { grid-column: 2; justify-self: start; }
        }
    </style>
@endpush
