@extends('layouts.vendor')

@section('title', 'My Procurement Submissions')

@section('content')
    <div class="mb-4">
        <h3 class="mb-1">My Procurement Submissions</h3>
        <p class="text-muted mb-0">Review, respond, resubmit or withdraw your procurement applications.</p>
    </div>

    <div class="card vendor-card">
        <div class="card-body">
            @if ($submissions->isEmpty())
                <p class="text-muted mb-0">No submissions yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Procurement Reference</th>
                                <th>Procurement</th>
                                <th>Status</th>
                                <th>Application Closes</th>
                                <th>Open</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($submissions as $submission)
                                <tr>
                                    <td>
                                        <span class="badge-soft">
                                            {{ $submission->procurement_reference ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td>{{ $submission->procurement?->title ?? 'N/A' }}</td>
                                    <td>
                                        <span class="status-pill">{{ Str::headline($submission->status ?? 'pending') }}</span>
                                        @if($submission->is_recalled)<div class="small text-warning mt-1">Opportunity recalled</div>@endif
                                    </td>
                                    <td>{{ $submission->application_end_date ?? 'N/A' }}</td>
                                    <td>
                                        @if ($submission->is_recalled)
                                            <span class="badge bg-warning-subtle text-warning-emphasis">Awaiting republication</span>
                                        @elseif ($submission->is_open)
                                            <span class="badge bg-success-subtle text-success">Open</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary">Closed</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($submission->can_apply_again)
                                            <a href="{{ route('public.procurement.show', $submission->procurement) }}" class="btn btn-vendor btn-sm">Apply again</a>
                                        @elseif ($submission->is_open && !$submission->isWithdrawn())
                                            <a href="{{ route('vendor.applications.edit', $submission) }}"
                                                class="btn btn-vendor btn-sm">
                                                {{ $submission->status === \App\Models\FormSubmission::STATUS_REVISION_REQUESTED ? 'Respond & resubmit' : 'Review / resubmit' }}
                                            </a>
                                        @elseif ($submission->is_recalled && !$submission->isWithdrawn())
                                            <span class="text-muted small d-block">Your application is preserved</span>
                                        @else
                                            <span class="text-muted small">Locked</span>
                                        @endif
                                        @if($submission->can_withdraw && !$submission->is_open)
                                            <form class="mt-2" method="POST" action="{{ route('vendor.applications.withdraw', $submission) }}" onsubmit="var reason=window.prompt('Please give a reason for withdrawing this application:');if(!reason||reason.trim().length<5){return false;}this.elements.withdrawal_reason.value=reason.trim();return confirm('Withdraw this application?');">
                                                @csrf
                                                <input type="hidden" name="withdrawal_reason">
                                                <button class="btn btn-sm btn-outline-danger" type="submit">Withdraw</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
