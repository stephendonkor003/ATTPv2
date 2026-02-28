@extends('layouts.app')

@section('title', 'Member-State Communications Desk')

@push('styles')
<style>
.sys-comm-hero{border-radius:16px;padding:1.15rem 1.2rem;border:1px solid rgba(255,255,255,.2);background:linear-gradient(130deg,#0f172a 0%,#0f766e 55%,#0ea5e9 100%);color:#f8fafc;box-shadow:0 14px 30px rgba(15,23,42,.2)}
.sys-comm-hero .kicker{font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:rgba(248,250,252,.82)}
.sys-comm-hero h4{margin-bottom:.35rem;color:#fff;font-weight:800}
.sys-comm-stat{border:1px solid #e2e8f0;border-radius:12px;background:#fff;padding:.78rem .9rem;box-shadow:0 4px 10px rgba(15,23,42,.05)}
.sys-comm-stat .label{font-size:.72rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em}
.sys-comm-stat .value{font-size:1.2rem;font-weight:800;color:#0f172a}
.sys-comm-panel{border:1px solid #dbeafe;border-radius:14px;background:#fff;box-shadow:0 6px 16px rgba(15,23,42,.06)}
.sys-comm-panel .card-header{border-bottom:1px solid #e2e8f0;background:linear-gradient(120deg,#f8fafc 0%,#eef2ff 100%)}
.sys-comm-filter{border:1px solid #dbeafe;border-radius:12px;background:#f8fbff;padding:.75rem}
.sys-comm-item{border:1px solid #e2e8f0;border-radius:12px;background:#fff;padding:.92rem;box-shadow:0 4px 12px rgba(15,23,42,.05)}
.sys-comm-item + .sys-comm-item{margin-top:.8rem}
.sys-comm-meta{font-size:.79rem;color:#64748b}
.sys-comm-order{width:34px;height:34px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;font-weight:800;color:#fff;background:linear-gradient(140deg,#0f766e 0%,#16a34a 100%);box-shadow:0 8px 14px rgba(15,23,42,.16)}
.sys-comm-message{font-size:.89rem;color:#334155}
.sys-comm-files{display:flex;flex-wrap:wrap;gap:.45rem}
.sys-comm-file{display:inline-flex;align-items:center;gap:.35rem;padding:.26rem .6rem;border:1px solid #cbd5e1;border-radius:999px;background:#fff;color:#0f172a;font-size:.75rem}
.sys-comm-response{border:1px solid #bbf7d0;border-radius:10px;background:#f0fdf4;padding:.65rem .75rem}
.sys-comm-form{border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc;padding:.8rem}
.sys-badge{border-radius:999px;padding:.26rem .58rem;font-size:.72rem;font-weight:700;display:inline-flex;align-items:center;gap:.3rem}
.status-pending_response{background:#dbeafe;color:#1d4ed8}
.status-in_review{background:#fef3c7;color:#92400e}
.status-answered{background:#dcfce7;color:#166534}
.status-closed{background:#e2e8f0;color:#334155}
.sys-empty{border:1px dashed #cbd5e1;border-radius:12px;background:#f8fafc;padding:1rem;text-align:center;color:#64748b}
@media (max-width: 767px){
    .sys-comm-item .d-flex{flex-direction:column;align-items:flex-start !important}
}
</style>
@endpush

@section('content')
@php
    $formatBytes = function ($bytes) {
        $bytes = (int) $bytes;
        if ($bytes <= 0) {
            return '0 KB';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return number_format($value, $power === 0 ? 0 : 1) . ' ' . $units[$power];
    };
@endphp
<main class="nxl-container">
    <div class="sys-comm-hero mb-4">
        <div class="kicker">Back-Office Operations</div>
        <h4>Member-State Communications Desk</h4>
        <p class="mb-0">Track submissions, review attachments, and issue official responses to member states.</p>
    </div>

    @if (session('success')) <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div> @endif
    @if (session('error')) <div class="alert alert-danger border-0 shadow-sm">{{ session('error') }}</div> @endif
    @if ($errors->any()) <div class="alert alert-danger border-0 shadow-sm">Please review validation details for your response form.</div> @endif

    <div class="row g-3 mb-4">
        <div class="col-md-2"><div class="sys-comm-stat"><div class="label">Total</div><div class="value">{{ $stats['total'] ?? 0 }}</div></div></div>
        <div class="col-md-2"><div class="sys-comm-stat"><div class="label">Pending</div><div class="value text-primary">{{ $stats['pending_response'] ?? 0 }}</div></div></div>
        <div class="col-md-2"><div class="sys-comm-stat"><div class="label">In Review</div><div class="value text-warning">{{ $stats['in_review'] ?? 0 }}</div></div></div>
        <div class="col-md-2"><div class="sys-comm-stat"><div class="label">Answered</div><div class="value text-success">{{ $stats['answered'] ?? 0 }}</div></div></div>
        <div class="col-md-2"><div class="sys-comm-stat"><div class="label">Closed</div><div class="value text-secondary">{{ $stats['closed'] ?? 0 }}</div></div></div>
        <div class="col-md-2"><div class="sys-comm-stat"><div class="label">With Files</div><div class="value text-info">{{ $stats['with_attachments'] ?? 0 }}</div></div></div>
    </div>

    <div class="card sys-comm-panel">
        <div class="card-header">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0 fw-bold text-dark"><i class="feather-inbox me-1"></i> Communication Register</h5>
                <form method="GET" action="{{ route('system.communications.index') }}" class="sys-comm-filter d-flex flex-wrap gap-2">
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" style="min-width:220px;" placeholder="Search member state, subject, content...">
                    <select name="member_state_id" class="form-select form-select-sm" style="min-width:190px;">
                        <option value="">All member states</option>
                        @foreach($memberStates as $state)
                            <option value="{{ $state->id }}" @selected(($filters['member_state_id'] ?? '') === $state->id)>{{ $state->name }}</option>
                        @endforeach
                    </select>
                    <select name="channel" class="form-select form-select-sm" style="min-width:160px;">
                        <option value="">All channels</option>
                        <option value="official_note" @selected(($filters['channel'] ?? '')==='official_note')>Official Note</option>
                        <option value="email" @selected(($filters['channel'] ?? '')==='email')>Email</option>
                        <option value="meeting" @selected(($filters['channel'] ?? '')==='meeting')>Meeting Brief</option>
                        <option value="report" @selected(($filters['channel'] ?? '')==='report')>Official Report</option>
                        <option value="other" @selected(($filters['channel'] ?? '')==='other')>Other</option>
                    </select>
                    <select name="status" class="form-select form-select-sm" style="min-width:160px;">
                        <option value="">All statuses</option>
                        <option value="pending_response" @selected(($filters['status'] ?? '')==='pending_response')>Pending Response</option>
                        <option value="in_review" @selected(($filters['status'] ?? '')==='in_review')>In Review</option>
                        <option value="answered" @selected(($filters['status'] ?? '')==='answered')>Answered</option>
                        <option value="closed" @selected(($filters['status'] ?? '')==='closed')>Closed</option>
                    </select>
                    <button class="btn btn-sm btn-outline-secondary">Filter</button>
                    <a href="{{ route('system.communications.index') }}" class="btn btn-sm btn-light border">Clear</a>
                </form>
            </div>
        </div>
        <div class="card-body">
            @forelse($communications as $item)
                @php
                    $rowNumber = ((int) ($communications->firstItem() ?? 1)) + $loop->index;
                    $statusClass = match($item->status) {
                        'answered' => 'status-answered',
                        'in_review' => 'status-in_review',
                        'closed' => 'status-closed',
                        default => 'status-pending_response'
                    };
                    $channelLabel = match($item->channel) {
                        'official_note' => 'Official Note',
                        'meeting' => 'Meeting Brief',
                        'report' => 'Official Report',
                        default => ucfirst(str_replace('_', ' ', (string) $item->channel))
                    };
                @endphp
                <article class="sys-comm-item">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="d-flex align-items-start gap-2">
                            <span class="sys-comm-order">{{ $rowNumber }}</span>
                            <div>
                                <h6 class="mb-1 text-dark">{{ $item->subject }}</h6>
                                <div class="sys-comm-meta">
                                    <strong>{{ $item->memberState?->name ?? 'N/A' }}</strong> |
                                    {{ optional($item->communication_date)->format('d M Y') }} |
                                    {{ $channelLabel }} |
                                    {{ $item->attachments->count() }} file(s)
                                </div>
                            </div>
                        </div>
                        <span class="sys-badge {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', (string) $item->status)) }}</span>
                    </div>

                    <p class="sys-comm-message mt-2 mb-2">{{ \Illuminate\Support\Str::limit((string) $item->message, 430) }}</p>

                    @if($item->attachments->count())
                        <div class="sys-comm-files mb-2">
                            @foreach($item->attachments as $attachment)
                                <a class="sys-comm-file"
                                   href="{{ route('system.communications.attachments.download', ['communication' => $item, 'attachment' => $attachment]) }}?download=1">
                                    <i class="feather-paperclip"></i>
                                    <span>{{ \Illuminate\Support\Str::limit($attachment->file_name, 30) }}</span>
                                    <small class="text-muted">({{ $formatBytes($attachment->file_size_bytes) }})</small>
                                </a>
                            @endforeach
                        </div>
                    @endif

                    @if($item->response_text)
                        <div class="sys-comm-response mb-2">
                            <div class="small fw-semibold text-success mb-1">
                                AU Response
                                @if($item->responded_at)
                                    <span class="text-muted fw-normal">| {{ $item->responded_at->format('d M Y H:i') }}</span>
                                @endif
                            </div>
                            <div class="small text-dark">{{ \Illuminate\Support\Str::limit((string) $item->response_text, 460) }}</div>
                        </div>
                    @endif

                    @can('communications.respond')
                        <form action="{{ route('system.communications.respond', $item) }}" method="POST" class="sys-comm-form">
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
                                    <textarea name="response_text" rows="3" class="form-control form-control-sm" placeholder="Write the AU response that should be visible to member-state users..." required>{{ old('response_text', $item->response_text) }}</textarea>
                                </div>
                                <div class="col-12 d-flex justify-content-end">
                                    <button class="btn btn-sm btn-success">
                                        <i class="feather-send me-1"></i>Save Response
                                    </button>
                                </div>
                            </div>
                        </form>
                    @else
                        <div class="small text-muted">You can view communication details but do not have response permission.</div>
                    @endcan
                </article>
            @empty
                <div class="sys-empty">No communications found for the selected filters.</div>
            @endforelse
        </div>
        @if($communications->hasPages())
            <div class="card-footer bg-white">
                {{ $communications->links() }}
            </div>
        @endif
    </div>
</main>
@endsection
