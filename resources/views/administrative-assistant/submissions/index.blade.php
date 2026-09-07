@extends(($isReviewer ?? false) ? 'layouts.app' : 'layouts.administrative-assistant')

@section('title', ($isReviewer ?? false) ? 'Assistant approvals' : 'My submissions')
@section('workspace-heading', 'Purchase requests & disbursements')

@php
    $isReviewer = $isReviewer ?? false;
    $routePrefix = $isReviewer ? 'assistant-approvals' : 'administrative-assistant.submissions';
    $status = $status ?? 'pending';
    $statusLabels = ['pending' => 'Awaiting approval', 'approved' => 'Approved', 'rejected' => 'Not approved'];
@endphp

@push('styles')
<style>
    .as-workspace { color: #25364c; }
    .as-hero { padding: clamp(24px, 4vw, 38px); border-radius: 22px; background: linear-gradient(120deg, #10233f, #154557 64%, #087f73); color: #fff; }
    .as-hero h1 { color: #fff; font-size: clamp(1.6rem, 3vw, 2.2rem); letter-spacing: -.04em; }
    .as-hero p { color: #d7e6ed; max-width: 760px; line-height: 1.7; }
    .as-eyebrow { font-size: .7rem; font-weight: 800; text-transform: uppercase; letter-spacing: .13em; color: #8ee1cd; }
    .as-card { background: #fff; border: 1px solid #dce4ee; border-radius: 18px; box-shadow: 0 10px 30px rgba(16,35,63,.04); }
    .as-count { display: block; padding: 22px; height: 100%; transition: box-shadow .2s; }
    .as-count:hover, .as-count.is-active { border-color: #087f73; box-shadow: 0 0 0 2px rgba(8,127,115,.1); }
    .as-count strong { font-size: 2rem; line-height: 1; color: #10233f; }
    .as-count-icon { width: 40px; height: 40px; background: #e9f8f4; color: #087f73; display: grid; place-items: center; border-radius: 12px; font-size: 1.1rem; }
    .as-status { display: inline-flex; align-items: center; gap: 6px; font-size: .72rem; font-weight: 750; padding: 7px 11px; border-radius: 100px; white-space: nowrap; }
    .as-status-pending { background: #fff4d9; color: #80530c; }
    .as-status-approved { background: #e5f7ef; color: #08714d; }
    .as-status-rejected { background: #fceceb; color: #a33b36; }
    .as-action { padding: 21px; display: flex; align-items: center; gap: 15px; height: 100%; }
    .as-action:hover { border-color: #087f73; background: #fbfffe; }
    .as-action small { display: block; color: #64748b; margin-top: 4px; line-height: 1.5; }
    .as-table thead th { background: #f5f8fb; color: #64748b; font-size: .7rem; letter-spacing: .04em; text-transform: uppercase; padding: 16px 20px; }
    .as-table td { padding: 20px; vertical-align: middle; border-color: #edf1f5; }
    .as-workspace .table-responsive { position: relative; }
    .as-empty { text-align: center; padding: 55px 20px; }
    .as-empty i { font-size: 2.2rem; color: #087f73; }
    .as-search { min-width: min(100%, 290px); }
    .as-workspace :focus-visible { outline: 3px solid #0c9488; outline-offset: 3px; }
    @media(max-width: 575px) { .as-count { padding: 16px; } .as-count strong { font-size: 1.7rem; } .as-count-icon { display: none; } .as-table td, .as-table thead th { padding: 14px; } }
</style>
@endpush

@section('content')
<div class="{{ $isReviewer ? 'nxl-container' : '' }} as-workspace">
    <section class="as-hero mb-4">
        <div class="as-eyebrow mb-2">{{ $isReviewer ? 'Project Coordinator / administrator review desk' : 'Your approval tracker' }}</div>
        <h1 class="fw-bold mb-2">{{ $isReviewer ? 'Assistant approvals' : 'My submissions' }}</h1>
        <p class="mb-0">{{ $isReviewer ? 'Review the assistant’s purchase requests and disbursements, check the supporting documents, and record your decision. Approval makes the record available to the operational and reporting workflows.' : 'Create a purchase request or disbursement and follow it through review. The Project Coordinator or any authorized administrator can approve it. Pending submissions are excluded from financial reporting until approved.' }}</p>
        @if(config('assistant_submissions.coordinator_email'))<div class="small mt-3">Main Project Coordinator: <span class="fw-semibold">{{ config('assistant_submissions.coordinator_email') }}</span> · Other administrators also receive review notifications.</div>@endif
    </section>

    @if ($isReviewer)
        @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @endif

    <div class="row g-3 mb-4">
        @foreach (['pending' => ['Awaiting approval', 'clock', 'Waiting for a decision'], 'approved' => ['Approved', 'check-circle', 'Accepted into the workflow'], 'rejected' => ['Not approved', 'message-circle', 'Review the coordinator’s feedback']] as $key => [$label, $icon, $description])
            <div class="col-12 col-sm-4">
                <a href="{{ route($routePrefix.'.index', ['status' => $key]) }}" class="as-card as-count {{ $status === $key ? 'is-active' : '' }}" @if($status === $key) aria-current="page" @endif>
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-3"><strong>{{ number_format($counts[$key] ?? 0) }}</strong><span class="as-count-icon"><i class="feather-{{ $icon }}" aria-hidden="true"></i></span></div>
                    <div class="fw-bold text-dark">{{ $label }}</div>
                    <div class="small text-muted mt-1">{{ $description }}</div>
                </a>
            </div>
        @endforeach
    </div>

    @unless ($isReviewer)
        <div class="row g-3 mb-4">
            <div class="col-md-6"><a href="{{ route('administrative-assistant.requests.create') }}" class="as-card as-action"><span class="as-count-icon flex-shrink-0"><i class="feather-file-plus"></i></span><div><span class="fw-bold text-dark">Create purchase request</span><small>Add budget details, requested items, and supporting documents.</small></div><i class="feather-arrow-up-right ms-auto"></i></a></div>
            <div class="col-md-6"><a href="{{ route('administrative-assistant.disbursements.create') }}" class="as-card as-action"><span class="as-count-icon flex-shrink-0"><i class="feather-credit-card"></i></span><div><span class="fw-bold text-dark">Create disbursement</span><small>Prepare payment details against an existing purchase order.</small></div><i class="feather-arrow-up-right ms-auto"></i></a></div>
        </div>
    @endunless

    <section class="as-card overflow-hidden">
        <div class="p-4 d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-center">
            <div><h2 class="h5 fw-bold mb-1">{{ $statusLabels[$status] ?? 'All submissions' }}</h2><div class="small text-muted">{{ number_format($submissions->total()) }} {{ $submissions->total() === 1 ? 'submission' : 'submissions' }} · Open a record for its documents and full history.</div></div>
            <form method="GET" action="{{ route($routePrefix.'.index') }}" class="d-flex flex-wrap gap-2">
                <label for="submissionStatus" class="visually-hidden">Status</label>
                <select id="submissionStatus" name="status" class="form-select w-auto">
                    <option value="all" @selected($status === 'all')>All statuses</option>
                    @foreach($statusLabels as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach
                </select>
                <div class="input-group as-search flex-grow-1 w-auto">
                    <label for="submissionSearch" class="visually-hidden">Search submissions</label>
                    <input type="search" id="submissionSearch" name="q" class="form-control" value="{{ request('q') }}" placeholder="Search reference or title">
                    <button class="btn btn-outline-primary" type="submit" aria-label="Filter submissions"><i class="feather-search" aria-hidden="true"></i></button>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table as-table mb-0">
                <thead><tr><th scope="col">Submission</th><th scope="col">{{ $isReviewer ? 'Submitted by' : 'Submitted' }}</th><th scope="col">Status</th><th scope="col">Next step</th><th scope="col"><span class="visually-hidden">View submission</span></th></tr></thead>
                <tbody>
                    @forelse ($submissions as $submission)
                        @php
                            $summary = $submission->summary ?? [];
                            $submissionTitle = $summary['Title'] ?? $summary['Purpose'] ?? $summary['Description'] ?? null;
                            $kindLabel = $submission->kind === 'disbursement' ? 'Disbursement' : 'Purchase request';
                        @endphp
                        <tr>
                            <td style="min-width:230px"><div class="small text-muted mb-1">{{ $kindLabel }}</div><a href="{{ route($routePrefix.'.show', $submission) }}" class="fw-bold">{{ $submission->reference_no }}</a>@if(is_scalar($submissionTitle) && $submissionTitle !== '')<div class="text-muted small mt-1">{{ \Illuminate\Support\Str::limit((string) $submissionTitle, 85) }}</div>@endif</td>
                            <td>@if($isReviewer)<div class="fw-semibold mb-1">{{ $submission->creator?->name ?? 'Former user' }}</div>@endif<div class="small text-muted">{{ $submission->created_at?->format('d M Y, H:i') }}</div></td>
                            <td><span class="as-status as-status-{{ $submission->status }}"><i class="feather-{{ $submission->status === 'approved' ? 'check-circle' : ($submission->status === 'rejected' ? 'x-circle' : 'clock') }}" aria-hidden="true"></i>{{ $statusLabels[$submission->status] ?? ucfirst($submission->status) }}</span></td>
                            <td class="small text-muted">{{ $submission->status === 'pending' ? ($isReviewer ? 'Review and record a decision' : 'Waiting for coordinator approval') : ($submission->status === 'approved' ? 'Available in the approved workflow' : 'Read the review feedback') }}</td>
                            <td class="text-end"><a href="{{ route($routePrefix.'.show', $submission) }}" class="btn btn-sm btn-outline-primary text-nowrap">{{ $isReviewer && $submission->status === 'pending' ? 'Review' : 'View' }} <i class="feather-arrow-right ms-1"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="as-empty"><i class="feather-inbox" aria-hidden="true"></i><h3 class="h5 fw-bold mt-3">{{ request('q') ? 'No matching submissions' : 'No submissions here yet' }}</h3><p class="text-muted mb-0">{{ request('q') ? 'Try another reference or title, or select a different status.' : ($isReviewer ? 'New assistant submissions will appear here when they are ready for review.' : 'Use the actions above to prepare your first purchase request or disbursement.') }}</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($submissions->hasPages())<div class="p-4 border-top">{{ $submissions->withQueryString()->links() }}</div>@endif
    </section>
</div>
@endsection
