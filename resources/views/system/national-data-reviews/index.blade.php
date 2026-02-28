@extends('layouts.app')

@section('title', 'National Data Review Desk')

@push('styles')
<style>
.ndr-hero{border-radius:16px;padding:1.1rem 1.2rem;border:1px solid rgba(255,255,255,.22);background:linear-gradient(128deg,#0f172a 0%,#14532d 52%,#0ea5e9 100%);color:#f8fafc;box-shadow:0 14px 28px rgba(15,23,42,.2)}
.ndr-hero .kicker{font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:rgba(248,250,252,.82)}
.ndr-hero h4{margin:.2rem 0 .35rem;color:#fff;font-weight:800}
.ndr-stat{border:1px solid #dbeafe;border-radius:12px;background:#fff;padding:.75rem .85rem;box-shadow:0 5px 12px rgba(15,23,42,.06)}
.ndr-stat .label{font-size:.7rem;letter-spacing:.06em;text-transform:uppercase;color:#64748b}
.ndr-stat .value{font-size:1.16rem;font-weight:800;color:#0f172a}
.ndr-panel{border:1px solid #dbeafe;border-radius:14px;background:#fff;box-shadow:0 8px 18px rgba(15,23,42,.07)}
.ndr-panel .card-header{border-bottom:1px solid #e2e8f0;background:linear-gradient(120deg,#f8fafc 0%,#ecfeff 100%)}
.ndr-filter{border:1px solid #dbeafe;border-radius:12px;background:#f8fbff;padding:.72rem}
.ndr-item{border:1px solid #e2e8f0;border-radius:12px;background:#fff;padding:.9rem;box-shadow:0 4px 12px rgba(15,23,42,.05)}
.ndr-item + .ndr-item{margin-top:.78rem}
.ndr-order{width:35px;height:35px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;font-weight:800;color:#fff;background:linear-gradient(140deg,#0f766e 0%,#16a34a 100%);box-shadow:0 8px 14px rgba(15,23,42,.16)}
.ndr-badge{border-radius:999px;padding:.22rem .58rem;font-size:.72rem;font-weight:700;display:inline-flex;align-items:center;gap:.3rem}
.ndr-progress{background:#dbeafe;color:#1d4ed8}
.ndr-review-pending{background:#fef3c7;color:#92400e}
.ndr-review-approved{background:#dcfce7;color:#166534}
.ndr-review-revision_required{background:#fee2e2;color:#991b1b}
.ndr-review-rejected{background:#e2e8f0;color:#334155}
.ndr-meta{font-size:.79rem;color:#64748b}
.ndr-summary{font-size:.88rem;color:#334155}
.ndr-metric{border:1px solid #dbeafe;border-radius:10px;background:#f8fbff;padding:.48rem .58rem}
.ndr-metric .k{font-size:.7rem;color:#64748b;text-transform:uppercase;letter-spacing:.06em}
.ndr-metric .v{font-weight:700;color:#0f172a}
.ndr-form{border:1px solid #dbeafe;border-radius:12px;background:#f8fafc;padding:.72rem}
.ndr-empty{border:1px dashed #cbd5e1;border-radius:12px;background:#f8fafc;padding:1rem;text-align:center;color:#64748b}
</style>
@endpush

@section('content')
@php
    $progressLabels = [
        'not_started' => 'Not Started',
        'in_progress' => 'In Progress',
        'advanced' => 'Advanced',
        'completed' => 'Completed',
        'stalled' => 'Stalled',
    ];
    $reviewLabels = [
        'pending' => 'Pending Review',
        'approved' => 'Approved',
        'revision_required' => 'Revision Required',
        'rejected' => 'Rejected',
    ];
@endphp
<main class="nxl-container">
    <div class="ndr-hero mb-4">
        <div class="kicker">Back-Office Validation</div>
        <h4>Member-State National Data Review Desk</h4>
        <p class="mb-0">Review submissions, approve trusted records for reporting/comparison, or request revisions with clear guidance.</p>
    </div>

    @if (session('success')) <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div> @endif
    @if (session('error')) <div class="alert alert-danger border-0 shadow-sm">{{ session('error') }}</div> @endif
    @if ($errors->any()) <div class="alert alert-danger border-0 shadow-sm">Please correct the review form validation errors and submit again.</div> @endif

    <div class="row g-3 mb-4">
        <div class="col-md-2"><div class="ndr-stat"><div class="label">Total</div><div class="value">{{ number_format((int) ($stats['total'] ?? 0)) }}</div></div></div>
        <div class="col-md-2"><div class="ndr-stat"><div class="label">Pending</div><div class="value text-warning">{{ number_format((int) ($stats['pending'] ?? 0)) }}</div></div></div>
        <div class="col-md-2"><div class="ndr-stat"><div class="label">Approved</div><div class="value text-success">{{ number_format((int) ($stats['approved'] ?? 0)) }}</div></div></div>
        <div class="col-md-2"><div class="ndr-stat"><div class="label">Revision Req.</div><div class="value text-danger">{{ number_format((int) ($stats['revision_required'] ?? 0)) }}</div></div></div>
        <div class="col-md-2"><div class="ndr-stat"><div class="label">Rejected</div><div class="value text-secondary">{{ number_format((int) ($stats['rejected'] ?? 0)) }}</div></div></div>
        <div class="col-md-2"><div class="ndr-stat"><div class="label">Approved Avg Score</div><div class="value text-primary">{{ number_format((float) ($stats['approved_avg_score'] ?? 0), 1) }}%</div></div></div>
    </div>

    <div class="card ndr-panel">
        <div class="card-header">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0 fw-bold text-dark"><i class="feather-check-circle me-1"></i> Review Queue</h5>
                <form method="GET" action="{{ route('system.national-data-reviews.index') }}" class="ndr-filter d-flex flex-wrap gap-2" id="ndrFilterForm">
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" style="min-width:210px;" placeholder="Search state, indicator, policy, notes...">
                    <select name="member_state_id" class="form-select form-select-sm" style="min-width:170px;">
                        <option value="">All member states</option>
                        @foreach($memberStates as $state)
                            <option value="{{ $state->id }}" @selected(($filters['member_state_id'] ?? '') === $state->id)>{{ $state->name }}</option>
                        @endforeach
                    </select>
                    <select name="review_status" class="form-select form-select-sm" style="min-width:160px;">
                        <option value="">All review status</option>
                        @foreach($reviewLabels as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['review_status'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="progress_status" class="form-select form-select-sm" style="min-width:145px;">
                        <option value="">All progress</option>
                        @foreach($progressLabels as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['progress_status'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="aspiration_id" id="ndrAspirationFilter" class="form-select form-select-sm" style="min-width:145px;">
                        <option value="">Aspiration</option>
                        @foreach($aspirations as $aspiration)
                            <option value="{{ $aspiration->id }}" @selected(($filters['aspiration_id'] ?? '') === $aspiration->id)>Asp {{ $aspiration->number }}</option>
                        @endforeach
                    </select>
                    <select name="goal_id" id="ndrGoalFilter" class="form-select form-select-sm" style="min-width:145px;">
                        <option value="">Goal</option>
                        @foreach($aspirations as $aspiration)
                            @foreach($aspiration->goals as $goal)
                                <option value="{{ $goal->id }}" data-aspiration="{{ $aspiration->id }}" @selected(($filters['goal_id'] ?? '') === $goal->id)>Goal {{ $goal->number }}</option>
                            @endforeach
                        @endforeach
                    </select>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm">
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm">
                    <button class="btn btn-sm btn-outline-secondary">Filter</button>
                    <a href="{{ route('system.national-data-reviews.index') }}" class="btn btn-sm btn-light border">Clear</a>
                </form>
            </div>
        </div>
        <div class="card-body">
            @forelse($entries as $entry)
                @php
                    $rowNumber = ((int) ($entries->firstItem() ?? 1)) + $loop->index;
                    $progressLabel = $progressLabels[$entry->progress_status] ?? ucfirst(str_replace('_', ' ', (string) $entry->progress_status));
                    $reviewStatus = (string) ($entry->review_status ?? 'pending');
                    $reviewLabel = $reviewLabels[$reviewStatus] ?? ucfirst(str_replace('_', ' ', $reviewStatus));
                @endphp
                <article class="ndr-item">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="d-flex align-items-start gap-2">
                            <span class="ndr-order">{{ $rowNumber }}</span>
                            <div>
                                <h6 class="mb-1 text-dark">{{ $entry->indicator_name }}</h6>
                                <div class="ndr-meta">
                                    <strong>{{ $entry->memberState?->name ?? 'N/A' }}</strong> |
                                    {{ optional($entry->recorded_on)->format('d M Y') }} |
                                    {{ ucfirst(str_replace('_', ' ', (string) $entry->reporting_period_type)) }} |
                                    Asp {{ $entry->aspiration?->number ?? '-' }} / Goal {{ $entry->goal?->number ?? '-' }}
                                </div>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-1">
                            <span class="ndr-badge ndr-progress">{{ $progressLabel }}</span>
                            <span class="ndr-badge ndr-review-{{ $reviewStatus }}">{{ $reviewLabel }}</span>
                        </div>
                    </div>

                    <div class="row g-2 mt-1">
                        <div class="col-md-2"><div class="ndr-metric"><div class="k">Indicator Value</div><div class="v">{{ number_format((float) $entry->indicator_value, 4) }} {{ $entry->unit }}</div></div></div>
                        <div class="col-md-2"><div class="ndr-metric"><div class="k">Cooperation</div><div class="v">{{ number_format((float) $entry->cooperation_score, 1) }}%</div></div></div>
                        <div class="col-md-2"><div class="ndr-metric"><div class="k">Agenda Aware</div><div class="v">{{ number_format((float) ($entry->agenda_awareness_score ?? 0), 1) }}%</div></div></div>
                        <div class="col-md-2"><div class="ndr-metric"><div class="k">Outreach</div><div class="v">{{ number_format((float) ($entry->outreach_coverage_score ?? 0), 1) }}%</div></div></div>
                        <div class="col-md-2"><div class="ndr-metric"><div class="k">People Reached</div><div class="v">{{ number_format((int) ($entry->people_reached ?? 0)) }}</div></div></div>
                        <div class="col-md-2"><div class="ndr-metric"><div class="k">Data Source</div><div class="v">{{ $entry->data_source ?: 'N/A' }}</div></div></div>
                    </div>

                    <div class="ndr-summary mt-2">{{ \Illuminate\Support\Str::limit((string) ($entry->agenda_relevance_summary ?: $entry->notes ?: 'No summary provided.'), 420) }}</div>

                    <details class="mt-2">
                        <summary class="small fw-semibold text-primary" style="cursor:pointer;">Open full submission detail</summary>
                        <div class="row g-2 mt-2">
                            <div class="col-md-6"><strong>Policy actions:</strong><div class="small text-muted">{{ $entry->policy_actions ?: 'Not provided.' }}</div></div>
                            <div class="col-md-6"><strong>Implementation steps:</strong><div class="small text-muted">{{ $entry->institutional_steps ?: 'Not provided.' }}</div></div>
                            <div class="col-md-6"><strong>Public engagement:</strong><div class="small text-muted">{{ $entry->public_engagement_summary ?: 'Not provided.' }}</div></div>
                            <div class="col-md-6"><strong>National projects/programs:</strong><div class="small text-muted">{{ $entry->national_projects_programs ?: 'Not provided.' }}</div></div>
                            <div class="col-md-6"><strong>Commodity preservation:</strong><div class="small text-muted">{{ $entry->commodity_preservation_policies ?: 'Not provided.' }}</div></div>
                            <div class="col-md-6"><strong>Commodity value addition:</strong><div class="small text-muted">{{ $entry->commodity_value_addition ?: 'Not provided.' }}</div></div>
                            <div class="col-md-6"><strong>Risks/challenges:</strong><div class="small text-muted">{{ $entry->risk_challenges ?: 'Not provided.' }}</div></div>
                            <div class="col-md-6"><strong>Next steps:</strong><div class="small text-muted">{{ $entry->next_steps_commitments ?: 'Not provided.' }}</div></div>
                            <div class="col-md-6"><strong>Reviewer:</strong><div class="small text-muted">{{ $entry->reviewer?->name ?: 'Not reviewed yet' }}</div></div>
                            <div class="col-md-6"><strong>Reviewed at:</strong><div class="small text-muted">{{ $entry->reviewed_at ? $entry->reviewed_at->format('d M Y H:i') : 'Not reviewed yet' }}</div></div>
                            <div class="col-12"><strong>Review notes:</strong><div class="small text-muted">{{ $entry->review_notes ?: 'No review notes recorded.' }}</div></div>
                        </div>
                    </details>

                    @can('national_data.approve')
                        <form action="{{ route('system.national-data-reviews.status.update', $entry) }}" method="POST" class="ndr-form mt-2">
                            @csrf
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label small text-muted mb-1">Review decision</label>
                                    <select name="review_status" class="form-select form-select-sm">
                                        <option value="pending" @selected($entry->review_status === 'pending')>Pending Review</option>
                                        <option value="approved" @selected($entry->review_status === 'approved')>Approve for Reporting</option>
                                        <option value="revision_required" @selected($entry->review_status === 'revision_required')>Request Revision</option>
                                        <option value="rejected" @selected($entry->review_status === 'rejected')>Reject</option>
                                    </select>
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label small text-muted mb-1">Review notes / guidance to member state</label>
                                    <textarea name="review_notes" rows="2" class="form-control form-control-sm" placeholder="Provide clear feedback and next action...">{{ old('review_notes', $entry->review_notes) }}</textarea>
                                </div>
                                <div class="col-md-2 d-grid">
                                    <button class="btn btn-sm btn-success"><i class="feather-save me-1"></i>Save</button>
                                </div>
                            </div>
                        </form>
                    @else
                        <div class="small text-muted mt-2">You can view submissions but do not have permission to change review status.</div>
                    @endcan
                </article>
            @empty
                <div class="ndr-empty">No national data submissions found for the current filters.</div>
            @endforelse
        </div>
        @if($entries->hasPages())
            <div class="card-footer bg-white">{{ $entries->links() }}</div>
        @endif
    </div>
</main>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const aspirationFilter = document.getElementById('ndrAspirationFilter');
    const goalFilter = document.getElementById('ndrGoalFilter');
    if (!aspirationFilter || !goalFilter) {
        return;
    }

    const syncGoalFilter = () => {
        const aspirationId = aspirationFilter.value;
        Array.from(goalFilter.querySelectorAll('option')).forEach((option, index) => {
            if (index === 0) {
                option.hidden = false;
                return;
            }
            const owner = option.getAttribute('data-aspiration');
            const visible = !aspirationId || owner === aspirationId;
            option.hidden = !visible;
            if (!visible && option.selected) {
                goalFilter.value = '';
            }
        });
    };

    aspirationFilter.addEventListener('change', syncGoalFilter);
    syncGoalFilter();
});
</script>
@endpush
