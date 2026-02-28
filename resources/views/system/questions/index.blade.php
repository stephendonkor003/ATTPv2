@extends('layouts.app')

@section('title', 'Respond to Member-State Questions')

@push('styles')
<style>
.sys-qa-hero{border-radius:16px;padding:1.1rem 1.2rem;border:1px solid rgba(255,255,255,.2);background:linear-gradient(130deg,#0f172a 0%,#0f766e 55%,#16a34a 100%);color:#f8fafc;box-shadow:0 14px 30px rgba(15,23,42,.2)}
.sys-qa-hero .kicker{font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:rgba(248,250,252,.82)}
.sys-qa-hero h4{margin-bottom:.32rem;color:#fff;font-weight:800}
.sys-qa-stat{border:1px solid #e2e8f0;border-radius:12px;background:#fff;padding:.78rem .9rem;box-shadow:0 4px 10px rgba(15,23,42,.05)}
.sys-qa-stat .label{font-size:.72rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em}
.sys-qa-stat .value{font-size:1.2rem;font-weight:800;color:#0f172a}
.sys-qa-panel{border:1px solid #dbeafe;border-radius:14px;background:#fff;box-shadow:0 6px 16px rgba(15,23,42,.06)}
.sys-qa-panel .card-header{border-bottom:1px solid #e2e8f0;background:linear-gradient(120deg,#f8fafc 0%,#eef2ff 100%)}
.sys-qa-filter{border:1px solid #dbeafe;border-radius:12px;background:#f8fbff;padding:.75rem}
.sys-qa-item{border:1px solid #e2e8f0;border-radius:12px;background:#fff;padding:.92rem;box-shadow:0 4px 12px rgba(15,23,42,.05)}
.sys-qa-item + .sys-qa-item{margin-top:.8rem}
.sys-qa-order{width:34px;height:34px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;font-weight:800;color:#fff;background:linear-gradient(140deg,#0f766e 0%,#16a34a 100%);box-shadow:0 8px 14px rgba(15,23,42,.16)}
.sys-qa-meta{font-size:.79rem;color:#64748b}
.sys-qa-text{font-size:.89rem;color:#334155}
.sys-qa-form{border:1px solid #d1fae5;border-radius:12px;background:#f0fdf4;padding:.78rem}
.sys-qa-response{border:1px solid #bbf7d0;border-radius:10px;background:#ecfdf5;padding:.65rem .75rem}
.sys-qa-empty{border:1px dashed #cbd5e1;border-radius:12px;background:#f8fafc;padding:1rem;text-align:center;color:#64748b}
.sys-qa-badge{border-radius:999px;padding:.26rem .58rem;font-size:.72rem;font-weight:700;display:inline-flex;align-items:center;gap:.3rem}
.status-open{background:#dbeafe;color:#1d4ed8}
.status-in_review{background:#fef3c7;color:#92400e}
.status-answered{background:#dcfce7;color:#166534}
.status-closed{background:#e2e8f0;color:#334155}
.priority-low{background:#f1f5f9;color:#334155}
.priority-normal{background:#dbeafe;color:#1d4ed8}
.priority-high{background:#fef3c7;color:#92400e}
.priority-urgent{background:#fee2e2;color:#b91c1c}
@media (max-width: 767px){
    .sys-qa-item .d-flex{flex-direction:column;align-items:flex-start !important}
}
</style>
@endpush

@section('content')
<main class="nxl-container">
    <div class="sys-qa-hero mb-4">
        <div class="kicker">Back-Office Questions Desk</div>
        <h4>Respond to Member-State Questions</h4>
        <p class="mb-0">Review incoming policy and implementation questions, publish AU responses, and keep member states informed by email.</p>
    </div>

    @if (session('success')) <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div> @endif
    @if (session('error')) <div class="alert alert-danger border-0 shadow-sm">{{ session('error') }}</div> @endif
    @if ($errors->any()) <div class="alert alert-danger border-0 shadow-sm">Please review validation details for your response form.</div> @endif

    <div class="row g-3 mb-4">
        <div class="col-md-2"><div class="sys-qa-stat"><div class="label">Total</div><div class="value">{{ $stats['total'] ?? 0 }}</div></div></div>
        <div class="col-md-2"><div class="sys-qa-stat"><div class="label">Open</div><div class="value text-primary">{{ $stats['open'] ?? 0 }}</div></div></div>
        <div class="col-md-2"><div class="sys-qa-stat"><div class="label">In Review</div><div class="value text-warning">{{ $stats['in_review'] ?? 0 }}</div></div></div>
        <div class="col-md-2"><div class="sys-qa-stat"><div class="label">Answered</div><div class="value text-success">{{ $stats['answered'] ?? 0 }}</div></div></div>
        <div class="col-md-2"><div class="sys-qa-stat"><div class="label">Closed</div><div class="value text-secondary">{{ $stats['closed'] ?? 0 }}</div></div></div>
        <div class="col-md-2"><div class="sys-qa-stat"><div class="label">Urgent</div><div class="value text-danger">{{ $stats['urgent'] ?? 0 }}</div></div></div>
    </div>

    <div class="card sys-qa-panel">
        <div class="card-header">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0 fw-bold text-dark"><i class="feather-help-circle me-1"></i> Question Register</h5>
                <form method="GET" action="{{ route('system.questions.index') }}" class="sys-qa-filter d-flex flex-wrap gap-2">
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" style="min-width:220px;" placeholder="Search member state, subject, or question...">
                    <select name="member_state_id" class="form-select form-select-sm" style="min-width:190px;">
                        <option value="">All member states</option>
                        @foreach($memberStates as $state)
                            <option value="{{ $state->id }}" @selected(($filters['member_state_id'] ?? '') === $state->id)>{{ $state->name }}</option>
                        @endforeach
                    </select>
                    <select name="priority" class="form-select form-select-sm" style="min-width:150px;">
                        <option value="">All priorities</option>
                        <option value="low" @selected(($filters['priority'] ?? '')==='low')>Low</option>
                        <option value="normal" @selected(($filters['priority'] ?? '')==='normal')>Normal</option>
                        <option value="high" @selected(($filters['priority'] ?? '')==='high')>High</option>
                        <option value="urgent" @selected(($filters['priority'] ?? '')==='urgent')>Urgent</option>
                    </select>
                    <select name="status" class="form-select form-select-sm" style="min-width:160px;">
                        <option value="">All statuses</option>
                        <option value="open" @selected(($filters['status'] ?? '')==='open')>Open</option>
                        <option value="in_review" @selected(($filters['status'] ?? '')==='in_review')>In Review</option>
                        <option value="answered" @selected(($filters['status'] ?? '')==='answered')>Answered</option>
                        <option value="closed" @selected(($filters['status'] ?? '')==='closed')>Closed</option>
                    </select>
                    <button class="btn btn-sm btn-outline-secondary">Filter</button>
                    <a href="{{ route('system.questions.index') }}" class="btn btn-sm btn-light border">Clear</a>
                </form>
            </div>
        </div>
        <div class="card-body">
            @forelse($questions as $item)
                @php
                    $rowNumber = ((int) ($questions->firstItem() ?? 1)) + $loop->index;
                    $statusClass = 'status-' . (string) $item->status;
                    $priorityClass = 'priority-' . (string) $item->priority;
                @endphp
                <article class="sys-qa-item">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="d-flex align-items-start gap-2">
                            <span class="sys-qa-order">{{ $rowNumber }}</span>
                            <div>
                                <h6 class="mb-1 text-dark">{{ $item->subject }}</h6>
                                <div class="sys-qa-meta">
                                    <strong>{{ $item->memberState?->name ?? 'N/A' }}</strong> |
                                    {{ optional($item->asked_on)->format('d M Y') }} |
                                    Submitted by {{ $item->creator?->name ?? 'Member State User' }}
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="sys-qa-badge {{ $priorityClass }}">{{ ucfirst((string) $item->priority) }}</span>
                            <span class="sys-qa-badge {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', (string) $item->status)) }}</span>
                        </div>
                    </div>

                    <p class="sys-qa-text mt-2 mb-2">{{ \Illuminate\Support\Str::limit((string) $item->question_text, 460) }}</p>

                    @if($item->answer_text)
                        <div class="sys-qa-response mb-2">
                            <div class="small fw-semibold text-success mb-1">
                                Latest AU Response
                                @if($item->answered_at)
                                    <span class="text-muted fw-normal">| {{ $item->answered_at->format('d M Y H:i') }}</span>
                                @endif
                            </div>
                            <div class="small text-dark">{{ \Illuminate\Support\Str::limit((string) $item->answer_text, 520) }}</div>
                            @if($item->answeredBy)
                                <div class="small text-muted mt-1">Responded by {{ $item->answeredBy->name }} ({{ $item->answeredBy->email }})</div>
                            @endif
                        </div>
                    @endif

                    @can('questions.respond')
                        <form action="{{ route('system.questions.respond', $item) }}" method="POST" class="sys-qa-form">
                            @csrf
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <label class="form-label small text-muted mb-1">Set Status</label>
                                    <select name="status" class="form-select form-select-sm">
                                        <option value="in_review" @selected($item->status === 'in_review')>In Review</option>
                                        <option value="answered" @selected($item->status === 'answered')>Answered</option>
                                        <option value="closed" @selected($item->status === 'closed')>Closed</option>
                                    </select>
                                </div>
                                <div class="col-md-9">
                                    <label class="form-label small text-muted mb-1">Response to Member State</label>
                                    <textarea name="answer_text" rows="3" class="form-control form-control-sm" placeholder="Write the AU response that should be visible to member-state users..." required>{{ old('answer_text', $item->answer_text) }}</textarea>
                                </div>
                                <div class="col-12 d-flex justify-content-end">
                                    <button class="btn btn-sm btn-success">
                                        <i class="feather-send me-1"></i>Save Response
                                    </button>
                                </div>
                            </div>
                        </form>
                    @else
                        <div class="small text-muted">You can view question details but do not have response permission.</div>
                    @endcan
                </article>
            @empty
                <div class="sys-qa-empty">No questions found for the selected filters.</div>
            @endforelse
        </div>
        @if($questions->hasPages())
            <div class="card-footer bg-white">
                {{ $questions->links() }}
            </div>
        @endif
    </div>
</main>
@endsection
