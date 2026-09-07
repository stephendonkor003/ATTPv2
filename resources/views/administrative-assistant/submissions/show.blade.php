@extends(($isReviewer ?? false) ? 'layouts.app' : 'layouts.administrative-assistant')

@section('title', $submission->reference_no)
@section('workspace-heading', 'Submission details')

@php
    $isReviewer = $isReviewer ?? false;
    $routePrefix = $isReviewer ? 'assistant-approvals' : 'administrative-assistant.submissions';
    $summary = $submission->summary ?? [];
    $documents = $submission->documents ?? [];
    $kindLabel = $submission->kind === 'disbursement' ? 'Disbursement' : 'Purchase request';
    $statusLabel = ['pending' => 'Awaiting approval', 'approved' => 'Approved', 'rejected' => 'Not approved'][$submission->status] ?? ucfirst($submission->status);
    $formatValue = function ($value) use (&$formatValue) {
        if (is_array($value)) return collect($value)->map(fn ($entry, $key) => (is_string($key) ? \Illuminate\Support\Str::headline($key).': ' : '').$formatValue($entry))->implode(' · ');
        if (is_bool($value)) return $value ? 'Yes' : 'No';
        return $value === null || $value === '' ? '—' : (string) $value;
    };
@endphp

@push('styles')
<style>
    .as-detail { color: #25364c; }
    .as-detail-card { background: #fff; border: 1px solid #dce4ee; border-radius: 18px; box-shadow: 0 10px 30px rgba(16,35,63,.04); overflow: hidden; }
    .as-detail-hero { background: linear-gradient(120deg, #10233f, #154557 65%, #087f73); border-radius: 20px; padding: clamp(24px, 4vw, 34px); color: #e1eef3; }
    .as-detail-hero h1 { color: #fff; font-size: clamp(1.5rem, 3vw, 2rem); overflow-wrap: anywhere; }
    .as-detail-kicker { font-size: .72rem; text-transform: uppercase; letter-spacing: .12em; font-weight: 800; color: #98e6d3; }
    .as-detail-status { font-size: .75rem; font-weight: 750; padding: 9px 13px; border-radius: 100px; white-space: nowrap; }
    .as-detail-status.pending { background: #fff2ce; color: #815700; }
    .as-detail-status.approved { background: #ddf6e9; color: #076a48; }
    .as-detail-status.rejected { background: #ffebe7; color: #a33b36; }
    .as-detail-label { font-size: .73rem; color: #718096; margin-bottom: 5px; }
    .as-detail-value { color: #25364c; font-weight: 650; overflow-wrap: anywhere; white-space: pre-line; }
    .as-journey { display: flex; gap: 16px; position: relative; }
    .as-journey + .as-journey { padding-top: 24px; }
    .as-journey-dot { flex: 0 0 32px; height: 32px; display: grid; place-items: center; background: #edf1f6; color: #718096; border-radius: 50%; }
    .as-journey-dot.done { background: #e5f7ef; color: #08714d; }
    .as-journey-dot.current { background: #fff4d9; color: #80530c; }
    .as-document { display: flex; align-items: center; gap: 12px; padding: 15px; border: 1px solid #e1e8ef; border-radius: 12px; }
    .as-document + .as-document { margin-top: 10px; }
    .as-document-name { font-weight: 650; overflow-wrap: anywhere; }
    .as-document-icon { background: #e9f8f4; color: #087f73; padding: 11px; border-radius: 10px; font-size: 1.1rem; }
    .as-detail-table th { background: #f5f8fb; font-size: .72rem; color: #64748b; white-space: nowrap; }
    .as-detail-table td, .as-detail-table th { padding: 13px 18px; }
    .as-detail-table td { min-width: 100px; white-space: pre-line; }
    .as-review-note { background: #f5f8fb; border-left: 3px solid #087f73; padding: 16px; border-radius: 0 10px 10px 0; white-space: pre-line; }
    .as-detail :focus-visible { outline: 3px solid #0c9488; outline-offset: 3px; }
    #submissionPreviewModal .modal-dialog { max-width: min(1100px, 95vw); }
    #submissionPreviewModal .modal-body { height: 74vh; background: #edf1f6; padding: 0; }
    #submissionPreviewFrame { width: 100%; height: 100%; border: 0; background: #fff; }
</style>
@endpush

@section('content')
<div class="{{ $isReviewer ? 'nxl-container' : '' }} as-detail">
    <a href="{{ route($routePrefix.'.index') }}" class="d-inline-flex align-items-center gap-2 mb-3"><i class="feather-arrow-left"></i>{{ $isReviewer ? 'Back to approvals' : 'Back to my submissions' }}</a>
    <section class="as-detail-hero mb-4 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div><div class="as-detail-kicker mb-2">{{ $kindLabel }}</div><h1 class="fw-bold mb-2">{{ $submission->reference_no }}</h1><div class="small">Submitted by {{ $submission->creator?->name ?? 'Former user' }} · {{ $submission->created_at?->format('d M Y, H:i') }}</div></div>
        <div class="d-flex flex-wrap align-items-center gap-2"><span class="as-detail-status {{ $submission->status }}">{{ $statusLabel }}</span><a href="{{ route($routePrefix.'.pdf', $submission) }}" class="btn btn-light" target="_blank" rel="noopener"><i class="feather-download me-1"></i> Download PDF</a></div>
    </section>

    @if ($isReviewer)
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger"><strong>Please review your decision.</strong><ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @endif

    @if ($submission->status === 'pending')
        <div class="alert alert-warning border-0 d-flex gap-3 mb-4"><i class="feather-clock fs-24 mt-1"></i><div><strong>Awaiting Project Coordinator / administrator approval</strong><div class="mt-1">This submission is excluded from financial reporting. {{ $kindLabel === 'Disbursement' ? 'No payment has been posted and the purchase order balance is unchanged.' : 'No purchase request or budget commitment is published until approval.' }} The Project Coordinator or any authorized administrator can review it.</div></div></div>
    @elseif ($submission->status === 'approved')
        <div class="alert alert-success border-0 mb-4"><i class="feather-check-circle me-2"></i>Approved by {{ $submission->reviewer?->name ?? 'an authorized administrator' }} on {{ $submission->reviewed_at?->format('d M Y, H:i') }}. The record is now available to its operational and reporting workflows.</div>
    @else
        <div class="alert alert-danger border-0 mb-4"><i class="feather-info me-2"></i>This submission was not approved and has no effect on financial reporting. Read the coordinator’s feedback below before preparing a new submission.</div>
    @endif

    <div class="row g-4">
        <div class="col-xl-8">
            <section class="as-detail-card mb-4">
                <div class="p-4 border-bottom"><h2 class="h5 fw-bold mb-1">{{ $kindLabel }} details</h2><div class="small text-muted">The information supplied for this review.</div></div>
                <dl class="row g-4 m-0 p-4">
                    @foreach ($summary as $label => $value)
                        @continue(is_array($value))
                        <div class="col-md-6"><dt class="as-detail-label fw-normal">{{ $label }}</dt><dd class="as-detail-value mb-0">{{ $formatValue($value) }}</dd></div>
                    @endforeach
                </dl>
                @foreach ($summary as $label => $rows)
                    @continue(!is_array($rows) || empty($rows))
                    @php
                        $hasRows = is_array(reset($rows));
                        $columns = $hasRows ? collect($rows)->filter(fn ($row) => is_array($row))->flatMap(fn ($row) => array_keys($row))->unique()->values() : collect();
                    @endphp
                    <div class="px-4 pb-3"><h3 class="h6 fw-bold mb-0">{{ $label }}</h3></div>
                    @if ($hasRows)
                        <div class="table-responsive mb-3"><table class="table as-detail-table mb-0"><thead><tr>@foreach($columns as $column)<th scope="col">{{ \Illuminate\Support\Str::headline($column) }}</th>@endforeach</tr></thead><tbody>@foreach($rows as $row)<tr>@foreach($columns as $column)<td>{{ $formatValue(data_get($row, $column)) }}</td>@endforeach</tr>@endforeach</tbody></table></div>
                    @else
                        <div class="px-4 pb-4 as-detail-value">{{ $formatValue($rows) }}</div>
                    @endif
                @endforeach
            </section>

            <section class="as-detail-card mb-4">
                <div class="p-4 border-bottom d-flex justify-content-between align-items-center"><div><h2 class="h5 fw-bold mb-1">Supporting documents</h2><div class="small text-muted">Preview a PDF or image, or download the original file.</div></div><span class="badge bg-light text-dark border">{{ count($documents) }}</span></div>
                <div class="p-4">
                    @forelse ($documents as $index => $document)
                        @php
                            $documentUrl = route($routePrefix.'.document', [$submission, $index]);
                            $mime = $document['mime'] ?? '';
                            $canPreview = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'text/plain'], true);
                        @endphp
                        <div class="as-document flex-wrap flex-sm-nowrap"><i class="feather-file-text as-document-icon" aria-hidden="true"></i><div class="flex-grow-1" style="min-width:0"><div class="as-document-name">{{ $document['name'] ?? 'Supporting document' }}</div><div class="small text-muted mt-1">{{ \Illuminate\Support\Str::headline(preg_replace('/\.\d+(?=\.|$)/', '', $document['field'] ?? 'Attachment')) }}</div></div><div class="d-flex gap-2 flex-shrink-0">@if($canPreview)<button type="button" class="btn btn-sm btn-outline-primary js-submission-preview" data-url="{{ $documentUrl }}" data-name="{{ $document['name'] ?? 'Document preview' }}"><i class="feather-eye me-1"></i>Preview</button>@endif<a href="{{ $documentUrl }}?download=1" class="btn btn-sm btn-light" aria-label="Download {{ $document['name'] ?? 'supporting document' }}"><i class="feather-download"></i><span class="d-sm-none ms-1">Download</span></a></div></div>
                    @empty
                        <p class="text-muted mb-0">No supporting documents were attached to this submission.</p>
                    @endforelse
                </div>
            </section>

            @if ($submission->review_note)
                <section class="as-detail-card p-4 mb-4"><h2 class="h5 fw-bold mb-3">Coordinator’s feedback</h2><div class="as-review-note">{{ $submission->review_note }}</div><div class="small text-muted mt-3">{{ $submission->reviewer?->name ?? 'Administrator' }} · {{ $submission->reviewed_at?->format('d M Y, H:i') }}</div></section>
            @endif
        </div>

        <aside class="col-xl-4">
            @if ($isReviewer && $submission->status === 'pending')
                <section class="as-detail-card p-4 mb-4">
                    <div class="small text-uppercase fw-bold text-primary mb-2">Decision required</div><h2 class="h5 fw-bold mb-2">Review this submission</h2><p class="small text-muted">Check the details and supporting documents before approving. Approval publishes the record; returning it leaves reporting unchanged.</p>
                    <form method="POST" action="{{ route('assistant-approvals.approve', $submission) }}" class="mb-3">@csrf<label for="approvalNote" class="form-label fw-semibold">Approval note <span class="fw-normal text-muted">(optional)</span></label><textarea name="review_note" id="approvalNote" rows="3" maxlength="3000" class="form-control mb-3" placeholder="Add any context for the assistant">{{ old('review_note') }}</textarea><button type="submit" class="btn btn-success w-100"><i class="feather-check-circle me-1"></i> Approve and publish</button></form>
                    <details class="border-top pt-3" @if($errors->has('review_note')) open @endif><summary class="fw-semibold text-danger" style="cursor:pointer">Return without approval</summary><form method="POST" action="{{ route('assistant-approvals.reject', $submission) }}" class="mt-3">@csrf<label for="rejectionNote" class="form-label fw-semibold">Explain what needs to change <span class="text-danger">*</span></label><textarea name="review_note" id="rejectionNote" rows="4" maxlength="3000" required class="form-control mb-3" placeholder="Give clear feedback so the assistant can prepare a corrected submission.">{{ old('review_note') }}</textarea><button type="submit" class="btn btn-outline-danger w-100">Return with feedback</button></form></details>
                </section>
            @endif

            <section class="as-detail-card p-4 mb-4">
                <h2 class="h5 fw-bold mb-4">Approval journey</h2>
                <div class="as-journey"><span class="as-journey-dot done"><i class="feather-check"></i></span><div><div class="fw-bold">Submitted for review</div><div class="small text-muted">{{ $submission->creator?->name ?? 'Administrative Assistant' }}</div><div class="small text-muted">{{ $submission->created_at?->format('d M Y, H:i') }}</div></div></div>
                <div class="as-journey"><span class="as-journey-dot {{ $submission->status === 'pending' ? 'current' : 'done' }}"><i class="feather-{{ $submission->status === 'pending' ? 'clock' : 'check' }}"></i></span><div><div class="fw-bold">{{ $submission->status === 'pending' ? 'Coordinator review' : 'Decision recorded' }}</div><div class="small text-muted">{{ $submission->status === 'pending' ? 'An authorized administrator will review the documents and details.' : ($submission->reviewer?->name ?? 'Authorized administrator') }}</div>@if($submission->reviewed_at)<div class="small text-muted">{{ $submission->reviewed_at->format('d M Y, H:i') }}</div>@endif</div></div>
                <div class="as-journey"><span class="as-journey-dot {{ $submission->status === 'approved' ? 'done' : '' }}"><i class="feather-{{ $submission->status === 'approved' ? 'check' : ($submission->status === 'rejected' ? 'x' : 'bar-chart-2') }}"></i></span><div><div class="fw-bold">{{ $submission->status === 'rejected' ? 'Not published' : 'Published after approval' }}</div><div class="small text-muted">{{ $submission->status === 'approved' ? 'The approved record is available to its operational and reporting workflows.' : ($submission->status === 'rejected' ? 'This submission has no effect on financial reports.' : 'Only an approved record can affect financial reporting.') }}</div></div></div>
            </section>

            @php
                $notificationMessage = match ($submission->notification_status) {
                    'sent', 'delivered' => 'The review notification was sent with a PDF copy of this submission.',
                    'failed', 'no_recipients' => 'The email notification could not be delivered. This submission is still available in the coordinator’s approval desk.',
                    'mail_not_configured' => 'Email delivery is not configured. This submission is available in the coordinator’s approval desk; an administrator needs to enable the email service.',
                    'cancelled' => 'The email notification was cancelled because this submission no longer needs a review notification.',
                    'sending' => 'The email notification and PDF copy are being sent to the review team.',
                    default => 'The review notification includes a PDF copy of this submission. Delivery is being processed.',
                };
            @endphp
            <section class="as-detail-card p-4 mb-4"><h2 class="h6 fw-bold mb-2"><i class="feather-mail me-1"></i> Review notifications</h2>@if(config('assistant_submissions.coordinator_email'))<div class="small text-muted mb-1">Main Project Coordinator</div><div class="fw-semibold text-break mb-2">{{ config('assistant_submissions.coordinator_email') }}</div><p class="small text-muted">Other administrators also receive review notifications and may approve this submission.</p>@endif<p class="small text-muted mb-0">{{ $notificationMessage }}</p></section>

            @if (!$isReviewer && $submission->status === 'rejected')
                <a href="{{ route($submission->kind === 'disbursement' ? 'administrative-assistant.disbursements.create' : 'administrative-assistant.requests.create') }}" class="btn btn-outline-primary w-100">Prepare a new {{ strtolower($kindLabel) }} <i class="feather-arrow-right ms-1"></i></a>
            @endif
        </aside>
    </div>
</div>

<div class="modal fade" id="submissionPreviewModal" tabindex="-1" aria-labelledby="submissionPreviewTitle" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h6 text-break" id="submissionPreviewTitle">Document preview</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close preview"></button></div><div class="modal-body"><iframe id="submissionPreviewFrame" title="Supporting document preview" src="about:blank"></iframe></div><div class="modal-footer"><a id="submissionPreviewDownload" class="btn btn-outline-primary" href="#"><i class="feather-download me-1"></i>Download original</a><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button></div></div></div></div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById('submissionPreviewModal');
    const frame = document.getElementById('submissionPreviewFrame');
    document.querySelectorAll('.js-submission-preview').forEach(button => button.addEventListener('click', () => {
        if (!window.bootstrap?.Modal) { window.open(button.dataset.url, '_blank', 'noopener'); return; }
        document.getElementById('submissionPreviewTitle').textContent = button.dataset.name;
        document.getElementById('submissionPreviewDownload').href = button.dataset.url + '?download=1';
        frame.src = button.dataset.url;
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }));
    modalElement.addEventListener('hidden.bs.modal', () => { frame.src = 'about:blank'; });
});
</script>
@endpush
