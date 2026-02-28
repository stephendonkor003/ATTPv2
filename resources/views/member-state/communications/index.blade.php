@extends('layouts.app')

@section('title', 'Member State Communications')

@push('styles')
<style>
.comm-hero{border-radius:16px;padding:1.1rem 1.2rem;border:1px solid rgba(255,255,255,.22);background:linear-gradient(130deg,#0f172a 0%,#0f766e 52%,#0ea5e9 100%);color:#f8fafc;box-shadow:0 14px 28px rgba(15,23,42,.2)}
.comm-hero .kicker{font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:rgba(248,250,252,.85)}
.comm-hero h4{color:#ffffff !important;font-weight:800;letter-spacing:.01em;text-shadow:0 1px 2px rgba(2,6,23,.45)}
.comm-hero p{color:rgba(248,250,252,.92)}
.comm-stat{border:1px solid #e2e8f0;border-radius:12px;background:#fff;padding:.82rem .9rem;box-shadow:0 4px 10px rgba(15,23,42,.05)}
.comm-stat .label{font-size:.72rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em}
.comm-stat .value{font-size:1.25rem;font-weight:800;color:#0f172a}
.comm-panel{border:1px solid #dbeafe;border-radius:14px;background:#fff;box-shadow:0 6px 14px rgba(15,23,42,.06)}
.comm-panel .card-header{border-bottom:1px solid #e2e8f0;background:linear-gradient(120deg,#f8fafc 0%,#eef2ff 100%)}
.comm-panel-title{display:flex;align-items:center;gap:.5rem;color:#0f172a;font-weight:800}
.comm-upload-zone{border:1px dashed #7dd3fc;border-radius:12px;background:linear-gradient(120deg,#f0f9ff 0%,#ecfeff 100%);padding:.85rem}
.comm-upload-note{font-size:.77rem;color:#0369a1}
.comm-file-chip{display:inline-flex;align-items:center;gap:.35rem;padding:.25rem .55rem;border:1px solid #bfdbfe;border-radius:999px;background:#eff6ff;color:#1e3a8a;font-size:.73rem;margin:.18rem}
.comm-help-card{height:100%;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc;padding:.85rem}
.comm-help-card h6{margin:0 0 .45rem 0;color:#0f172a}
.comm-help-card ul{margin:0;padding-left:1rem}
.comm-help-card li{font-size:.82rem;color:#334155;margin-bottom:.35rem}
.comm-filter{border:1px solid #dbeafe;border-radius:12px;background:linear-gradient(120deg,#f8fbff 0%,#eff6ff 100%);padding:.75rem}
.comm-item{border:1px solid #e2e8f0;border-radius:12px;background:#fff;padding:.85rem .9rem;box-shadow:0 4px 10px rgba(15,23,42,.05)}
.comm-item + .comm-item{margin-top:.7rem}
.comm-order{width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;border-radius:999px;background:linear-gradient(140deg,#0f766e 0%,#16a34a 100%);color:#fff;font-weight:800;font-size:.82rem;box-shadow:0 8px 12px rgba(15,23,42,.16)}
.comm-subject{margin:0;color:#0f172a;font-size:1rem}
.comm-meta{font-size:.78rem;color:#64748b}
.comm-response{border:1px solid #bbf7d0;border-radius:10px;background:#f0fdf4;padding:.6rem .7rem}
.comm-message{font-size:.88rem;color:#334155;margin-bottom:.45rem}
.comm-files{display:flex;flex-wrap:wrap;gap:.45rem}
.comm-file-link{display:inline-flex;align-items:center;gap:.35rem;padding:.28rem .58rem;border:1px solid #cbd5e1;border-radius:999px;background:#fff;color:#0f172a;font-size:.75rem}
.comm-empty{border:1px dashed #cbd5e1;border-radius:12px;background:#f8fafc;padding:1rem;text-align:center;color:#64748b}
.badge-status{border-radius:999px;padding:.28rem .58rem;font-size:.72rem;font-weight:700;display:inline-flex;align-items:center;gap:.3rem}
.status-pending_response{background:#dbeafe;color:#1d4ed8}
.status-in_review{background:#fef3c7;color:#92400e}
.status-answered{background:#dcfce7;color:#166534}
.status-closed{background:#e2e8f0;color:#334155}
@media (max-width: 767px){
    .comm-item .d-flex{flex-direction:column;align-items:flex-start !important}
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
    <div class="comm-hero mb-4">
        <div class="kicker">Official Exchange Desk</div>
        <h4 class="mb-1">Communications with the African Union</h4>
        <p class="mb-0">{{ $memberState?->name }} can submit official notes, reports, and supporting files for AU review and response tracking.</p>
    </div>

    @if (session('success')) <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div> @endif
    @if (session('error')) <div class="alert alert-danger border-0 shadow-sm">{{ session('error') }}</div> @endif
    @if ($errors->any()) <div class="alert alert-danger border-0 shadow-sm"><strong>Validation issue:</strong> please check your communication form entries.</div> @endif

    <div class="row g-3 mb-4">
        <div class="col-md-2"><div class="comm-stat"><div class="label">Total</div><div class="value">{{ $stats['total'] ?? 0 }}</div></div></div>
        <div class="col-md-2"><div class="comm-stat"><div class="label">Pending</div><div class="value text-primary">{{ $stats['pending_response'] ?? 0 }}</div></div></div>
        <div class="col-md-2"><div class="comm-stat"><div class="label">In Review</div><div class="value text-warning">{{ $stats['in_review'] ?? 0 }}</div></div></div>
        <div class="col-md-2"><div class="comm-stat"><div class="label">Answered</div><div class="value text-success">{{ $stats['answered'] ?? 0 }}</div></div></div>
        <div class="col-md-2"><div class="comm-stat"><div class="label">With Files</div><div class="value text-info">{{ $stats['with_attachments'] ?? 0 }}</div></div></div>
        <div class="col-md-2"><div class="comm-stat"><div class="label">Open Ratio</div><div class="value">{{ ($stats['total'] ?? 0) > 0 ? number_format((((int) ($stats['pending_response'] ?? 0) + (int) ($stats['in_review'] ?? 0)) / (int) $stats['total']) * 100, 1) : '0.0' }}%</div></div></div>
    </div>

    <div class="card comm-panel mb-4">
        <div class="card-header">
            <div class="comm-panel-title"><i class="feather-send"></i> Submit Communication</div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-lg-8">
                    <form action="{{ route('member-state.communications.store') }}" method="POST" enctype="multipart/form-data" class="row g-3">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label">Date</label>
                            <input type="date" name="communication_date" class="form-control @error('communication_date') is-invalid @enderror"
                                   value="{{ old('communication_date', now()->toDateString()) }}" required>
                            @error('communication_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Channel</label>
                            <select name="channel" class="form-select @error('channel') is-invalid @enderror" required>
                                <option value="official_note" @selected(old('channel')==='official_note')>Official Note</option>
                                <option value="email" @selected(old('channel')==='email')>Email</option>
                                <option value="meeting" @selected(old('channel')==='meeting')>Meeting Brief</option>
                                <option value="report" @selected(old('channel')==='report')>Official Report</option>
                                <option value="other" @selected(old('channel')==='other')>Other</option>
                            </select>
                            @error('channel') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Subject</label>
                            <input type="text" name="subject" value="{{ old('subject') }}"
                                   class="form-control @error('subject') is-invalid @enderror"
                                   placeholder="Communication title" required>
                            @error('subject') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Message</label>
                            <textarea name="message" rows="5" class="form-control @error('message') is-invalid @enderror"
                                      placeholder="Provide a detailed communication for AU review..." required>{{ old('message') }}</textarea>
                            @error('message') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Supporting Files (Multiple Allowed)</label>
                            <div class="comm-upload-zone">
                                <div id="attachmentInputList" class="d-grid gap-2">
                                    <input type="file" name="attachments[]" class="form-control communication-attachment-input @error('attachments') is-invalid @enderror @if($errors->has('attachments.*')) is-invalid @endif"
                                           accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.png,.jpg,.jpeg,.txt,.zip">
                                </div>
                                <button type="button" id="addAttachmentInputBtn" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="feather-plus me-1"></i>Add More Files
                                </button>
                                <div class="comm-upload-note mt-2">
                                    Upload up to 25 files. Maximum size per file: 20MB.
                                </div>
                                <div id="selectedAttachmentFiles" class="mt-2"></div>
                            </div>
                            @error('attachments') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            @if($errors->has('attachments.*')) <div class="invalid-feedback d-block">{{ $errors->first('attachments.*') }}</div> @endif
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button class="btn btn-primary"><i class="feather-send me-1"></i>Submit Communication</button>
                            <button type="reset" class="btn btn-light border">Reset</button>
                        </div>
                    </form>
                </div>
                <div class="col-lg-4">
                    <div class="comm-help-card">
                        <h6><i class="feather-shield me-1"></i>Submission Quality Checklist</h6>
                        <ul>
                            <li>Use a precise subject line with treaty/program reference when applicable.</li>
                            <li>State action requested from AU in the first paragraph.</li>
                            <li>Attach supporting evidence (letters, reports, annexes) in readable formats.</li>
                            <li>Keep communication factual and include dates, agencies, and official signatories.</li>
                        </ul>
                        <div class="small text-muted mt-2">Status will update as AU processes your request.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card comm-panel">
        <div class="card-header">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="comm-panel-title"><i class="feather-inbox"></i> Communication Register</div>
                <form method="GET" action="{{ route('member-state.communications.index') }}" class="comm-filter d-flex flex-wrap gap-2">
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" style="min-width:220px;" placeholder="Search subject, message, AU response...">
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
                    <a href="{{ route('member-state.communications.index') }}" class="btn btn-sm btn-light border">Clear</a>
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
                        default => ucfirst(str_replace('_', ' ', $item->channel))
                    };
                @endphp
                <article class="comm-item">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="d-flex align-items-start gap-2">
                            <span class="comm-order">{{ $rowNumber }}</span>
                            <div>
                                <h6 class="comm-subject">{{ $item->subject }}</h6>
                                <div class="comm-meta">
                                    {{ optional($item->communication_date)->format('d M Y') }} |
                                    {{ $channelLabel }} |
                                    {{ $item->attachments->count() }} file(s)
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge-status {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $item->status)) }}</span>
                            @if($item->status !== 'answered')
                                <form action="{{ route('member-state.communications.destroy', $item) }}" method="POST" onsubmit="return confirm('Delete this communication?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <p class="comm-message mt-2 mb-2">{{ \Illuminate\Support\Str::limit($item->message, 280) }}</p>

                    @if($item->attachments->count())
                        <div class="comm-files mb-2">
                            @foreach($item->attachments as $attachment)
                                <a class="comm-file-link"
                                   href="{{ route('member-state.communications.attachments.download', ['communication' => $item, 'attachment' => $attachment]) }}?download=1">
                                    <i class="feather-paperclip"></i>
                                    <span>{{ \Illuminate\Support\Str::limit($attachment->file_name, 26) }}</span>
                                    <small class="text-muted">({{ $formatBytes($attachment->file_size_bytes) }})</small>
                                </a>
                            @endforeach
                        </div>
                    @endif

                    @if($item->response_text)
                        <div class="comm-response">
                            <div class="small fw-semibold text-success mb-1">AU Response</div>
                            <div class="small text-dark">{{ \Illuminate\Support\Str::limit($item->response_text, 420) }}</div>
                        </div>
                    @else
                        <div class="small text-muted">Awaiting AU response.</div>
                    @endif
                </article>
            @empty
                <div class="comm-empty">No communications found for the selected filter.</div>
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const inputList = document.getElementById('attachmentInputList');
    const addBtn = document.getElementById('addAttachmentInputBtn');
    const output = document.getElementById('selectedAttachmentFiles');

    if (!inputList || !addBtn || !output) {
        return;
    }

    const allowedAccept = '.pdf,.doc,.docx,.xls,.xlsx,.csv,.png,.jpg,.jpeg,.txt,.zip';
    const maxInputs = 25;

    const getInputs = () => Array.from(inputList.querySelectorAll('.communication-attachment-input'));

    const renderSelectedFiles = () => {
        output.innerHTML = '';
        const files = getInputs()
            .flatMap((inputEl) => Array.from(inputEl.files || []))
            .filter((file) => !!file);

        if (!files.length) {
            return;
        }

        const summary = document.createElement('div');
        summary.className = 'small text-muted mb-1';
        summary.textContent = files.length + ' file(s) selected';
        output.appendChild(summary);

        files.forEach((file) => {
            const chip = document.createElement('span');
            chip.className = 'comm-file-chip';
            chip.innerHTML = '<i class="feather-file"></i> ' + file.name;
            output.appendChild(chip);
        });
    };

    const bindInput = (inputEl) => {
        inputEl.addEventListener('change', renderSelectedFiles);
    };

    getInputs().forEach(bindInput);

    addBtn.addEventListener('click', () => {
        const current = getInputs();
        if (current.length >= maxInputs) {
            return;
        }

        const next = document.createElement('input');
        next.type = 'file';
        next.name = 'attachments[]';
        next.accept = allowedAccept;
        next.className = 'form-control communication-attachment-input mt-1';
        inputList.appendChild(next);
        bindInput(next);

        if (getInputs().length >= maxInputs) {
            addBtn.disabled = true;
        }
    });
});
</script>
@endpush
