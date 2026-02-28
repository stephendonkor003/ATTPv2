@extends('layouts.app')

@section('title', 'Member State Treaty Dashboard')

@push('styles')
<style>
.treaty-hero{border-radius:16px;padding:1.1rem 1.2rem;border:1px solid rgba(255,255,255,.24);background:linear-gradient(130deg,#0f172a 0%,#0f766e 50%,#0ea5e9 100%);color:#f8fafc;box-shadow:0 14px 26px rgba(15,23,42,.2)}
.treaty-hero .kicker{font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:rgba(248,250,252,.84)}
.treaty-summary-card,.treaty-record{border:1px solid #e2e8f0;border-radius:12px;background:#fff;box-shadow:0 4px 10px rgba(15,23,42,.05)}
.treaty-search-panel{border:1px solid #dbeafe;border-radius:12px;background:linear-gradient(115deg,#f8fbff 0%,#eff6ff 100%)}
.treaty-record-header{border-bottom:1px solid #e2e8f0;background:linear-gradient(120deg,#f8fafc 0%,#f1f5f9 100%)}
.treaty-record-header.dynamic{background:linear-gradient(120deg,var(--treaty-soft-color,#f8fafc) 0%,#ffffff 100%)}
.treaty-title-row{display:flex;align-items:flex-start;gap:.62rem}
.treaty-order-badge{width:34px;height:34px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;font-size:.85rem;font-weight:800;color:#ffffff;background:linear-gradient(140deg,#0f766e 0%,#16a34a 100%);box-shadow:0 8px 14px rgba(15,23,42,.18);border:2px solid rgba(255,255,255,.9)}
.treaty-title-meta{min-width:0}
.stage-badge{font-size:.75rem;border-radius:999px;padding:.33rem .62rem;font-weight:600;display:inline-flex;align-items:center;gap:.25rem;border:1px solid transparent}
.stage-not-started{background:#f1f5f9;color:#475569;border-color:#cbd5e1}
.stage-signed{background:#e0f2fe;color:#0369a1;border-color:#7dd3fc}
.stage-ratified{background:#dbeafe;color:#1d4ed8;border-color:#93c5fd}
.stage-original-submitted{background:#fef9c3;color:#a16207;border-color:#fcd34d}
.stage-completed{background:#dcfce7;color:#166534;border-color:#86efac}
.code-block{border:1px solid #cbd5e1;border-radius:10px;background:#f8fafc;padding:.5rem .6rem}
.code-block code{font-size:.87rem;font-weight:700;color:#1e293b}
.workflow-panel{position:relative;border:1px solid #dbe5ef;border-radius:14px;padding:.9rem;height:100%;background:linear-gradient(180deg,#ffffff 0%,#f8fafc 100%);box-shadow:0 10px 18px rgba(15,23,42,.06);transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease}
.workflow-panel:hover{transform:translateY(-2px);box-shadow:0 14px 24px rgba(15,23,42,.1)}
.workflow-panel::before{content:"";position:absolute;left:0;top:0;right:0;height:4px;border-radius:14px 14px 0 0;background:var(--workflow-accent,#0ea5e9)}
.workflow-panel.sign-step{--workflow-accent:#0ea5e9}
.workflow-panel.ratify-step{--workflow-accent:#2563eb}
.workflow-panel.original-step{--workflow-accent:#16a34a}
.workflow-step-head{display:flex;align-items:flex-start;gap:.65rem;margin-bottom:.78rem;padding-bottom:.62rem;border-bottom:1px dashed #cbd5e1}
.workflow-step-no{width:28px;height:28px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:800;color:#fff;background:var(--workflow-accent,#0ea5e9);box-shadow:0 6px 12px rgba(2,6,23,.16);flex:0 0 auto}
.workflow-step-title-wrap{min-width:0}
.workflow-step-title{margin:0;color:#0f172a;font-size:.95rem;font-weight:800}
.workflow-step-note{margin:.12rem 0 0 0;color:#475569;font-size:.76rem;line-height:1.3}
.workflow-step-state{margin-left:auto;display:inline-flex;align-items:center;padding:.2rem .55rem;border-radius:999px;font-size:.68rem;font-weight:700;line-height:1;border:1px solid transparent;white-space:nowrap}
.workflow-step-state.done{background:#dcfce7;color:#166534;border-color:#86efac}
.workflow-step-state.pending{background:#fef9c3;color:#854d0e;border-color:#fde68a}
.workflow-step-state.locked{background:#fee2e2;color:#991b1b;border-color:#fecaca}
.workflow-panel .form-control.form-control-sm{border-radius:10px}
.workflow-panel textarea.form-control.form-control-sm{min-height:72px}
.workflow-panel .btn{border-radius:10px}
.workflow-submit-btn{width:100%;font-weight:700}
.workflow-panel .alert{border-radius:10px;border:1px solid #e2e8f0}
.treaty-readmore-section h6{font-size:.9rem;color:#0f172a;margin-bottom:.35rem}
.treaty-modal .modal-dialog{z-index:2100}
.treaty-modal .modal-content{border:0;border-radius:18px;background:#fff;opacity:1;filter:none;backdrop-filter:none;overflow:hidden;box-shadow:0 24px 48px rgba(2,6,23,.2)}
.treaty-modal .modal-header{border:0;padding:1rem 1.2rem;background:linear-gradient(120deg,#0f172a 0%,#0f766e 52%,#14b8a6 100%);color:#f8fafc}
.treaty-modal .modal-header .modal-title{color:#ffffff !important;font-weight:800;letter-spacing:.01em;text-shadow:0 1px 2px rgba(2,6,23,.45);margin:0}
.treaty-modal .modal-header .btn-close{filter:invert(1);opacity:.95}
.treaty-modal .modal-body{background:linear-gradient(180deg,#f8fafc 0%,#ffffff 44%,#f8fafc 100%);padding:1.1rem 1.2rem}
.treaty-modal .modal-footer{background:#fff;border-top:1px solid #e2e8f0}
.treaty-modal .modal-dialog-scrollable .modal-body{scrollbar-width:auto;scrollbar-color:#0ea5e9 #dcfce7}
.treaty-modal .modal-dialog-scrollable .modal-body::-webkit-scrollbar{width:14px}
.treaty-modal .modal-dialog-scrollable .modal-body::-webkit-scrollbar-track{background:linear-gradient(180deg,#ecfdf5 0%,#dcfce7 100%);border-radius:999px}
.treaty-modal .modal-dialog-scrollable .modal-body::-webkit-scrollbar-thumb{background:linear-gradient(180deg,#14b8a6 0%,#0ea5e9 50%,#2563eb 100%);border-radius:999px;border:3px solid #dcfce7}
.treaty-modal .modal-dialog-scrollable .modal-body::-webkit-scrollbar-thumb:hover{background:linear-gradient(180deg,#06b6d4 0%,#0284c7 55%,#1d4ed8 100%)}
.treaty-modal-hero{display:grid;grid-template-columns:minmax(0,1fr) 380px;gap:1rem;margin-bottom:1rem}
.treaty-modal-member{display:grid;grid-template-columns:1fr;gap:.75rem;padding:.85rem;border:1px solid #dbeafe;border-radius:14px;background:linear-gradient(115deg,#ecfeff 0%,#eff6ff 60%,#f8fafc 100%)}
.treaty-modal-eyebrow{font-size:.72rem;text-transform:uppercase;letter-spacing:.08em;color:#0369a1;font-weight:700}
.treaty-modal-member h5{margin:.15rem 0 0 0;color:#0f172a}
.member-flag-wrap{width:100%;height:188px;border-radius:12px;border:1px solid #cbd5e1;background:#fff;overflow:hidden;box-shadow:0 10px 18px rgba(15,23,42,.14)}
.member-flag-wave{width:100%;height:100%;object-fit:cover;transform-origin:left center;animation:memberFlagWave 4.5s ease-in-out infinite}
.member-flag-fallback{width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#475569;font-size:.74rem}
.treaty-africa-card{border:1px solid #d1fae5;border-radius:14px;background:linear-gradient(180deg,#ecfdf5 0%,#f8fafc 100%);padding:.65rem;display:flex;flex-direction:column;min-height:312px}
.treaty-africa-card .label{font-size:.74rem;text-transform:uppercase;letter-spacing:.06em;font-weight:700;color:#065f46;margin-bottom:.4rem}
.treaty-africa-map{height:260px;min-height:260px;flex:1 1 auto;border:1px solid #bbf7d0;border-radius:10px;background:#f0fdf4;position:relative;overflow:hidden}
.treaty-africa-map .jvectormap-container,.treaty-africa-map svg{width:100% !important;height:100% !important}
.treaty-africa-note{font-size:.74rem;color:#166534;margin-top:.45rem}
.treaty-info-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.65rem;margin-bottom:.85rem}
.treaty-info-card{border:1px solid #e2e8f0;border-radius:12px;background:#fff;padding:.65rem .75rem}
.treaty-info-card .k{font-size:.72rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em}
.treaty-info-card .v{margin-top:.22rem;color:#0f172a;font-weight:700;font-size:.92rem}
.treaty-readmore-block{border:1px solid #e2e8f0;border-radius:12px;padding:.72rem .78rem;background:#fff;height:100%}
@keyframes memberFlagWave{
    0%,100%{transform:perspective(900px) rotateY(0deg) skewY(0deg)}
    25%{transform:perspective(900px) rotateY(-8deg) skewY(1deg)}
    50%{transform:perspective(900px) rotateY(4deg) skewY(-1deg)}
    75%{transform:perspective(900px) rotateY(-6deg) skewY(1deg)}
}
@media (max-width: 991px){
    .treaty-modal-hero{grid-template-columns:1fr}
    .treaty-info-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media (max-width: 575px){
    .member-flag-wrap{height:160px}
    .treaty-africa-map{height:220px;min-height:220px}
    .treaty-info-grid{grid-template-columns:1fr}
}
</style>
@endpush

@section('content')
@php
    $totalTreaties = max(1, (int) $summary['total_treaties']);
    $completionRate = ($summary['fully_completed_count'] / $totalTreaties) * 100;
    $memberName = $memberState?->name ?? 'Member State';
    $memberCode2 = strtoupper((string) ($memberState?->code_alpha2 ?? ''));
    $memberFlagUrl = $memberState?->flag_url;
    if (!$memberFlagUrl && $memberCode2 !== '') {
        $memberFlagUrl = asset('admin/assets/vendors/img/flags/4x3/' . strtolower($memberCode2) . '.svg');
    }
@endphp
<main class="nxl-container">
    <div class="treaty-hero mb-4">
        <div class="kicker">Member State Treaty Execution Desk</div>
        <h4 class="mb-1">{{ $memberState?->name ?? 'Member State' }} Treaty Workspace</h4>
        <p class="mb-1 text-white-50">Comprehensive treaty workflow, proof-of-service codes, and legal verification tracking.</p>
        <small>Completion rate: <strong>{{ number_format($completionRate, 1) }}%</strong></small>
    </div>

    @if (session('success')) <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div> @endif
    @if (session('warning')) <div class="alert alert-warning border-0 shadow-sm">{{ session('warning') }}</div> @endif
    @if (session('error')) <div class="alert alert-danger border-0 shadow-sm">{{ session('error') }}</div> @endif
    @if (session('info')) <div class="alert alert-info border-0 shadow-sm">{{ session('info') }}</div> @endif
    @if ($errors->any()) <div class="alert alert-danger border-0 shadow-sm"><strong>Validation issue:</strong> Please review your inputs.</div> @endif

    <div class="row g-3 mb-4">
        <div class="col-md-2"><div class="treaty-summary-card p-3"><small class="text-muted d-block">Total</small><h4 class="mb-0">{{ $summary['total_treaties'] }}</h4></div></div>
        <div class="col-md-2"><div class="treaty-summary-card p-3"><small class="text-muted d-block">Signed</small><h4 class="mb-0 text-info">{{ $summary['signed_count'] }}</h4></div></div>
        <div class="col-md-2"><div class="treaty-summary-card p-3"><small class="text-muted d-block">Ratified</small><h4 class="mb-0 text-primary">{{ $summary['ratified_count'] }}</h4></div></div>
        <div class="col-md-2"><div class="treaty-summary-card p-3"><small class="text-muted d-block">Original Submitted</small><h4 class="mb-0 text-success">{{ $summary['original_submitted_count'] }}</h4></div></div>
        <div class="col-md-2"><div class="treaty-summary-card p-3"><small class="text-muted d-block">Completed</small><h4 class="mb-0 text-success">{{ $summary['fully_completed_count'] }}</h4></div></div>
        <div class="col-md-2"><div class="treaty-summary-card p-3"><small class="text-muted d-block">Pending Legal</small><h4 class="mb-0 text-warning">{{ $summary['pending_legal_verification_count'] }}</h4></div></div>
    </div>

    <div class="treaty-search-panel p-3 mb-4">
        <form id="treatyFilterForm" method="GET" action="{{ route('member-state.treaties.index') }}" class="row g-2 align-items-end">
            <div class="col-lg-4">
                <label class="form-label small text-muted mb-1">Runtime Search (auto)</label>
                <input type="text" id="treatySearchInput" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Title, treaty code, service code...">
            </div>
            <div class="col-lg-2">
                <label class="form-label small text-muted mb-1">Stage</label>
                <select id="treatyStageFilter" name="stage" class="form-select">
                    <option value="">All stages</option>
                    <option value="not_started" @selected(($filters['stage'] ?? '') === 'not_started')>Not Started</option>
                    <option value="signed" @selected(($filters['stage'] ?? '') === 'signed')>Signed</option>
                    <option value="ratified" @selected(($filters['stage'] ?? '') === 'ratified')>Ratified</option>
                    <option value="original_submitted" @selected(($filters['stage'] ?? '') === 'original_submitted')>Original Submitted</option>
                    <option value="completed" @selected(($filters['stage'] ?? '') === 'completed')>Completed</option>
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label small text-muted mb-1">Code Verification</label>
                <select id="treatyVerificationFilter" name="code_verification" class="form-select">
                    <option value="">All</option>
                    <option value="pending" @selected(($filters['code_verification'] ?? '') === 'pending')>Pending</option>
                    <option value="verified" @selected(($filters['code_verification'] ?? '') === 'verified')>Verified</option>
                    <option value="not_generated" @selected(($filters['code_verification'] ?? '') === 'not_generated')>Not Generated</option>
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label small text-muted mb-1">Adoption Year</label>
                <select id="treatyYearFilter" name="year" class="form-select">
                    <option value="">All years</option>
                    @foreach ($availableYears as $year)
                        <option value="{{ $year }}" @selected((string) ($filters['year'] ?? '') === (string) $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 d-flex gap-2">
                <a href="{{ route('member-state.treaties.index') }}" class="btn btn-light border w-100">Reset</a>
            </div>
        </form>
        <div class="row g-2 mt-1">
            <div class="col-lg-12 d-flex align-items-end justify-content-end">
                <small class="text-muted">Showing <strong id="treatyVisibleCount">{{ $treaties->count() }}</strong> treaty record(s)</small>
            </div>
        </div>
    </div>

    <div class="d-grid gap-3" id="treatyCardsWrap">
        @forelse ($treaties as $treaty)
            @php
                $status = $statusByTreatyId->get($treaty->id);
                $stage = $stageByTreatyId->get($treaty->id, 'Not Started');
                $referenceCode = $treaty->reference_code ?: 'TRT-' . strtoupper(substr((string) $treaty->id, 0, 8));
                $hasOriginalSubmission = !empty($status?->original_document_path);
                $signedVerified = !empty($status?->signed_service_code_verified_at);
                $ratifiedVerified = !empty($status?->ratified_service_code_verified_at);
                $legalCodesVerified = $signedVerified && $ratifiedVerified;
                $isSignedCodePending = !empty($status?->signed_service_code) && !$signedVerified;
                $isRatifiedCodePending = !empty($status?->ratified_service_code) && !$ratifiedVerified;
                $canResendServiceEmail = $status && ($isSignedCodePending || $isRatifiedCodePending);
                $signStateClass = $status?->is_signed ? 'done' : 'pending';
                $signStateLabel = $status?->is_signed ? 'Completed' : 'Pending';
                $ratifyStateClass = !$status?->is_signed ? 'locked' : ($status?->is_ratified ? 'done' : 'pending');
                $ratifyStateLabel = !$status?->is_signed ? 'Locked' : ($status?->is_ratified ? 'Completed' : 'Pending');
                $originalStateClass = !$status?->is_ratified
                    ? 'locked'
                    : (($status?->is_original_submitted && $legalCodesVerified) ? 'done' : 'pending');
                $originalStateLabel = !$status?->is_ratified
                    ? 'Locked'
                    : (($status?->is_original_submitted && $legalCodesVerified) ? 'Completed' : 'In Review');
                $stageClass = match ($stage) {
                    'Signed' => 'stage-signed',
                    'Ratified' => 'stage-ratified',
                    'Original Submitted' => 'stage-original-submitted',
                    'Completed' => 'stage-completed',
                    default => 'stage-not-started',
                };
                $searchBlob = strtolower(implode(' ', [
                    $treaty->title,
                    $treaty->short_title,
                    $referenceCode,
                    $status?->signed_service_code,
                    $status?->ratified_service_code,
                    $stage,
                    $treaty->description,
                    $treaty->overview,
                    $treaty->key_provisions,
                    $treaty->implementation_framework,
                    $treaty->monitoring_and_reporting,
                ]));
                $collapseId = 'treaty-collapse-' . $loop->index;
                $modalId = 'treaty-readmore-modal-' . $loop->index;
                $mapId = 'treaty-africa-map-' . $loop->index;
                $mapHighlightColor = ($status?->is_ratified || $status?->is_original_submitted) ? '#16a34a' : ($status?->is_signed ? '#facc15' : '#94a3b8');
                $mapHighlightLabel = ($status?->is_ratified || $status?->is_original_submitted) ? 'ratified' : ($status?->is_signed ? 'signed' : 'not-started');
                $palette = [
                    ['soft' => '#e0f2fe', 'border' => '#0284c7'],
                    ['soft' => '#dcfce7', 'border' => '#16a34a'],
                    ['soft' => '#fef3c7', 'border' => '#d97706'],
                    ['soft' => '#ede9fe', 'border' => '#7c3aed'],
                    ['soft' => '#ffe4e6', 'border' => '#e11d48'],
                    ['soft' => '#e0f2f1', 'border' => '#0f766e'],
                ];
                $paletteColor = $palette[$loop->index % count($palette)];
            @endphp
            <article class="treaty-record js-treaty-card" data-search="{{ $searchBlob }}"
                style="border-left:6px solid {{ $paletteColor['border'] }};">
                <div class="treaty-record-header dynamic p-3" style="--treaty-soft-color: {{ $paletteColor['soft'] }}">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div class="treaty-title-row">
                            <span class="treaty-order-badge">{{ $loop->iteration }}</span>
                            <div class="treaty-title-meta">
                                <h5 class="mb-1">{{ $treaty->title }}</h5>
                                <div class="small text-muted"><strong>Treaty Code:</strong> {{ $referenceCode }} @if($treaty->short_title)| <strong>Short:</strong> {{ $treaty->short_title }} @endif</div>
                                <div class="small text-muted mt-1">Adopted: {{ optional($treaty->adoption_date)->format('d M Y') ?: 'N/A' }} | Entry into force: {{ optional($treaty->entry_into_force_date)->format('d M Y') ?: 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <span class="stage-badge {{ $stageClass }}"><i class="feather-activity"></i> {{ $stage }}</span>
                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
                                Read More
                            </button>
                            <button class="btn btn-sm btn-outline-dark js-treaty-collapse-toggle" type="button" data-target="#{{ $collapseId }}" aria-controls="{{ $collapseId }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}"><span class="js-collapse-label">{{ $loop->first ? 'Hide Details' : 'Details & Actions' }}</span></button>
                        </div>
                    </div>
                </div>
                <div id="{{ $collapseId }}" class="collapse js-treaty-collapse {{ $loop->first ? 'show' : '' }}">
                    <div class="p-3">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="code-block">
                                    <div class="small text-muted mb-1">Signed Proof-of-Service Code</div>
                                    @if ($status?->signed_service_code)
                                        <div class="d-flex justify-content-between align-items-center gap-2"><code>{{ $status->signed_service_code }}</code><button type="button" class="btn btn-sm btn-outline-secondary js-copy-code" data-code="{{ $status->signed_service_code }}">Copy</button></div>
                                        <div class="small mt-1 {{ $signedVerified ? 'text-success' : 'text-warning' }}">{{ $signedVerified ? 'Verified: ' . optional($status->signed_service_code_verified_at)->format('d M Y H:i') : 'Awaiting AU Legal verification' }}</div>
                                    @else
                                        <div class="small text-muted">Generated after signature submission.</div>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="code-block">
                                    <div class="small text-muted mb-1">Ratified Proof-of-Service Code</div>
                                    @if ($status?->ratified_service_code)
                                        <div class="d-flex justify-content-between align-items-center gap-2"><code>{{ $status->ratified_service_code }}</code><button type="button" class="btn btn-sm btn-outline-secondary js-copy-code" data-code="{{ $status->ratified_service_code }}">Copy</button></div>
                                        <div class="small mt-1 {{ $ratifiedVerified ? 'text-success' : 'text-warning' }}">{{ $ratifiedVerified ? 'Verified: ' . optional($status->ratified_service_code_verified_at)->format('d M Y H:i') : 'Awaiting AU Legal verification' }}</div>
                                    @else
                                        <div class="small text-muted">Generated after ratification submission.</div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if ($treaty->supportingDocuments->count())
                            <div class="mb-3">
                                <div class="small text-muted fw-semibold mb-2">Supporting Documents</div>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($treaty->supportingDocuments as $supportingDocument)
                                        <a href="{{ route('treaties.supporting-documents.download', $supportingDocument->id) }}?download=1" class="btn btn-sm btn-outline-secondary"><i class="feather-paperclip me-1"></i>{{ $supportingDocument->title ?: $supportingDocument->file_name }}</a>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="row g-3">
                            <div class="col-lg-4">
                                <div class="workflow-panel sign-step">
                                    <div class="workflow-step-head">
                                        <span class="workflow-step-no">1</span>
                                        <div class="workflow-step-title-wrap">
                                            <h6 class="workflow-step-title">Sign Treaty</h6>
                                            <p class="workflow-step-note">Upload signed copy and official evidence.</p>
                                        </div>
                                        <span class="workflow-step-state {{ $signStateClass }}">{{ $signStateLabel }}</span>
                                    </div>
                                    @if ($status?->signed_document_path)
                                        <a href="{{ route('treaty-statuses.documents.download', ['treatyStatus' => $status->id, 'type' => 'signed']) }}?download=1" class="btn btn-sm btn-outline-info mb-2"><i class="feather-download me-1"></i>Current Signed File</a>
                                    @endif
                                    @can('member_state.treaties.update')
                                    <form method="POST" action="{{ route('member-state.treaties.status.update', $treaty->id) }}" enctype="multipart/form-data">
                                        @csrf
                                        <input type="hidden" name="action_type" value="sign">
                                        <div class="mb-2"><input type="date" name="action_date" class="form-control form-control-sm" value="{{ now()->toDateString() }}"></div>
                                        <div class="mb-2"><input type="file" name="proof_document" class="form-control form-control-sm" required></div>
                                        <div class="mb-2"><textarea name="notes" rows="2" class="form-control form-control-sm" placeholder="Signing note (optional)"></textarea></div>
                                        <button class="btn btn-sm btn-info text-white workflow-submit-btn"><i class="feather-check me-1"></i>{{ $status?->is_signed ? 'Update Signature' : 'Mark as Signed' }}</button>
                                    </form>
                                    @endcan
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="workflow-panel ratify-step">
                                    <div class="workflow-step-head">
                                        <span class="workflow-step-no">2</span>
                                        <div class="workflow-step-title-wrap">
                                            <h6 class="workflow-step-title">Ratify Treaty</h6>
                                            <p class="workflow-step-note">Submit ratification instrument after signature.</p>
                                        </div>
                                        <span class="workflow-step-state {{ $ratifyStateClass }}">{{ $ratifyStateLabel }}</span>
                                    </div>
                                    @if ($status?->ratified_document_path)
                                        <a href="{{ route('treaty-statuses.documents.download', ['treatyStatus' => $status->id, 'type' => 'ratified']) }}?download=1" class="btn btn-sm btn-outline-primary mb-2"><i class="feather-download me-1"></i>Current Ratified File</a>
                                    @endif
                                    @if ($status?->is_signed)
                                        @can('member_state.treaties.update')
                                        <form method="POST" action="{{ route('member-state.treaties.status.update', $treaty->id) }}" enctype="multipart/form-data">
                                            @csrf
                                            <input type="hidden" name="action_type" value="ratify">
                                            <div class="mb-2"><input type="date" name="action_date" class="form-control form-control-sm" value="{{ now()->toDateString() }}"></div>
                                            <div class="mb-2"><input type="file" name="proof_document" class="form-control form-control-sm" required></div>
                                            <div class="mb-2"><textarea name="notes" rows="2" class="form-control form-control-sm" placeholder="Ratification note (optional)"></textarea></div>
                                            <button class="btn btn-sm btn-primary workflow-submit-btn"><i class="feather-award me-1"></i>{{ $status?->is_ratified ? 'Update Ratification' : 'Mark as Ratified' }}</button>
                                        </form>
                                        @endcan
                                    @else
                                        <div class="alert alert-warning small mb-0">This step becomes available after signing.</div>
                                    @endif
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="workflow-panel original-step">
                                    <div class="workflow-step-head">
                                        <span class="workflow-step-no">3</span>
                                        <div class="workflow-step-title-wrap">
                                            <h6 class="workflow-step-title">Submit Original Copy</h6>
                                            <p class="workflow-step-note">Provide final physical/legal copy to AU Legal.</p>
                                        </div>
                                        <span class="workflow-step-state {{ $originalStateClass }}">{{ $originalStateLabel }}</span>
                                    </div>
                                    @if ($status?->original_document_path)
                                        <a href="{{ route('treaty-statuses.documents.download', ['treatyStatus' => $status->id, 'type' => 'original']) }}?download=1" class="btn btn-sm btn-outline-success mb-2"><i class="feather-download me-1"></i>Current Original File</a>
                                    @endif
                                    @if ($status?->is_ratified)
                                        @can('member_state.treaties.update')
                                        <form method="POST" action="{{ route('member-state.treaties.status.update', $treaty->id) }}" enctype="multipart/form-data">
                                            @csrf
                                            <input type="hidden" name="action_type" value="submit_original">
                                            <div class="mb-2"><input type="date" name="action_date" class="form-control form-control-sm" value="{{ now()->toDateString() }}"></div>
                                            <div class="mb-2"><input type="file" name="original_document" class="form-control form-control-sm" required></div>
                                            <div class="mb-2"><textarea name="notes" rows="2" class="form-control form-control-sm" placeholder="Submission note to AU Legal Directorate"></textarea></div>
                                            <button class="btn btn-sm btn-success workflow-submit-btn"><i class="feather-send me-1"></i>{{ $hasOriginalSubmission ? 'Update Submission' : 'Submit Original Copy' }}</button>
                                        </form>
                                        @endcan
                                        @if ($hasOriginalSubmission && !$legalCodesVerified) <div class="alert alert-warning small mt-2 mb-0">Original submitted. Final completion requires AU Legal verification of both codes.</div> @endif
                                        @if ($status?->is_original_submitted && $legalCodesVerified) <div class="alert alert-success small mt-2 mb-0">Final completion confirmed. AU Legal verified both codes.</div> @endif
                                    @else
                                        <div class="alert alert-warning small mb-0">This step becomes available after ratification.</div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if ($canResendServiceEmail)
                            <div class="mt-3 p-3 border rounded">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <div>
                                        <div class="fw-semibold">Proof-of-Service Email</div>
                                        <div class="small text-muted">Resend to AU Legal if previous delivery was missed.</div>
                                    </div>
                                    @can('member_state.treaties.update')
                                    <form method="POST" action="{{ route('member-state.treaties.status.resend-proof-email', $treaty->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-dark"><i class="feather-mail me-1"></i>Resend Email</button>
                                    </form>
                                    @endcan
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </article>

            @push('modals')
                <div class="modal treaty-modal" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">{{ $treaty->title }} - Full Information</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body treaty-readmore-section">
                                <div class="treaty-modal-hero">
                                    <div class="treaty-modal-member">
                                        <div class="member-flag-wrap">
                                            @if ($memberFlagUrl)
                                                <img src="{{ $memberFlagUrl }}" alt="{{ $memberName }} flag" class="member-flag-wave" loading="lazy"
                                                    onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div class="member-flag-fallback" style="display:none;">No Flag</div>
                                            @else
                                                <div class="member-flag-fallback">No Flag</div>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="treaty-modal-eyebrow">Member State Focus</div>
                                            <h5>{{ $memberName }}</h5>
                                        </div>
                                    </div>
                                    <div class="treaty-africa-card">
                                        <div class="label">Africa Shape Map</div>
                                        <div id="{{ $mapId }}" class="treaty-africa-map"
                                            data-focus-code="{{ $memberCode2 }}"
                                            data-highlight-color="{{ $mapHighlightColor }}"
                                            data-highlight-stage="{{ $mapHighlightLabel }}"></div>
                                        @if ($mapHighlightLabel === 'ratified')
                                            <div class="treaty-africa-note">Green highlight means ratified status for {{ $memberName }}.</div>
                                        @elseif($mapHighlightLabel === 'signed')
                                            <div class="treaty-africa-note">Yellow highlight means signed status for {{ $memberName }}.</div>
                                        @else
                                            <div class="treaty-africa-note">Gray highlight means treaty action not started for {{ $memberName }}.</div>
                                        @endif
                                    </div>
                                </div>

                                <div class="treaty-info-grid">
                                    <div class="treaty-info-card">
                                        <div class="k">Reference Code</div>
                                        <div class="v">{{ $referenceCode }}</div>
                                    </div>
                                    <div class="treaty-info-card">
                                        <div class="k">Adoption Date</div>
                                        <div class="v">{{ optional($treaty->adoption_date)->format('d M Y') ?: 'N/A' }}</div>
                                    </div>
                                    <div class="treaty-info-card">
                                        <div class="k">Entry Into Force</div>
                                        <div class="v">{{ optional($treaty->entry_into_force_date)->format('d M Y') ?: 'N/A' }}</div>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-lg-6">
                                        <div class="treaty-readmore-block">
                                            <h6>Description</h6>
                                            <p class="mb-0" style="white-space: pre-wrap;">{{ $treaty->description ?: 'No additional description provided.' }}</p>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="treaty-readmore-block">
                                            <h6>Overview / Background</h6>
                                            <p class="mb-0" style="white-space: pre-wrap;">{{ $treaty->overview ?: 'Not provided.' }}</p>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="treaty-readmore-block">
                                            <h6>Key Provisions</h6>
                                            <p class="mb-0" style="white-space: pre-wrap;">{{ $treaty->key_provisions ?: 'Not provided.' }}</p>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="treaty-readmore-block">
                                            <h6>Implementation Framework</h6>
                                            <p class="mb-0" style="white-space: pre-wrap;">{{ $treaty->implementation_framework ?: 'Not provided.' }}</p>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="treaty-readmore-block">
                                            <h6>Monitoring & Reporting</h6>
                                            <p class="mb-0" style="white-space: pre-wrap;">{{ $treaty->monitoring_and_reporting ?: 'Not provided.' }}</p>
                                        </div>
                                    </div>
                                    @if ($treaty->supportingDocuments->count())
                                        <div class="col-12">
                                            <div class="treaty-readmore-block">
                                                <h6>Supporting Documents</h6>
                                                <div class="d-flex flex-wrap gap-2">
                                                    @foreach ($treaty->supportingDocuments as $supportingDocument)
                                                        <a href="{{ route('treaties.supporting-documents.download', $supportingDocument->id) }}?download=1"
                                                            class="btn btn-sm btn-outline-secondary">
                                                            <i class="feather-paperclip me-1"></i>
                                                            {{ $supportingDocument->title ?: $supportingDocument->file_name }}
                                                        </a>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="modal-footer justify-content-between">
                                @if ($treaty->read_more_url)
                                    <a href="{{ $treaty->read_more_url }}" target="_blank" rel="noopener noreferrer"
                                        class="btn btn-primary">
                                        <i class="feather-external-link me-1"></i> Open External Treaty Link
                                    </a>
                                @else
                                    <span class="text-muted small">No external read-more link has been configured.</span>
                                @endif
                                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            @endpush
        @empty
            <div class="alert alert-info border-0 shadow-sm mb-0">No treaty records matched your search/filter criteria.</div>
        @endforelse
    </div>
</main>
@endsection

@push('scripts')
<script src="{{ asset('admin/assets/vendors/js/jquery-jvectormap.min.js') }}"></script>
<script src="{{ asset('admin/assets/vendors/js/jvectormap-world.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded',function(){
    const liveSearchInput=document.getElementById('treatySearchInput');
    const filterForm=document.getElementById('treatyFilterForm');
    const stageFilter=document.getElementById('treatyStageFilter');
    const verificationFilter=document.getElementById('treatyVerificationFilter');
    const yearFilter=document.getElementById('treatyYearFilter');
    const wrap=document.getElementById('treatyCardsWrap');
    const countEl=document.getElementById('treatyVisibleCount');
    const cards=wrap?Array.from(wrap.querySelectorAll('.js-treaty-card')):[];
    const refresh=()=>{ if(!countEl) return; countEl.textContent=cards.filter(c=>c.style.display!=='none').length.toString(); };

    let submitTimer=null;
    const autoSubmit=()=>{
        if(!filterForm) return;
        if(submitTimer){ clearTimeout(submitTimer); }
        submitTimer=setTimeout(()=>filterForm.submit(),550);
    };

    if(liveSearchInput){
        liveSearchInput.addEventListener('input',()=>{
            const term=(liveSearchInput.value||'').toLowerCase().trim();
            cards.forEach(c=>{ const txt=(c.getAttribute('data-search')||'').toLowerCase(); c.style.display=(term===''||txt.includes(term))?'':'none'; });
            refresh();
            autoSubmit();
        });
    }

    [stageFilter,verificationFilter,yearFilter].forEach(el=>{
        if(!el) return;
        el.addEventListener('change',()=>{ if(filterForm){ filterForm.submit(); } });
    });

    document.querySelectorAll('.js-copy-code').forEach(btn=>{ btn.addEventListener('click',async()=>{ const code=btn.getAttribute('data-code')||''; if(!code) return; try{ await navigator.clipboard.writeText(code); const t=btn.textContent; btn.textContent='Copied'; setTimeout(()=>btn.textContent=t,900);}catch(e){} }); });

    const collapseButtons = Array.from(document.querySelectorAll('.js-treaty-collapse-toggle'));
    const collapsePanels = Array.from(document.querySelectorAll('.js-treaty-collapse'));
    const getCollapseButton = (panel) => collapseButtons.find((btn) => btn.getAttribute('data-target') === `#${panel.id}`);
    const setCollapseButtonState = (btn, isOpen) => {
        btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        const label = btn.querySelector('.js-collapse-label');
        if (label) {
            label.textContent = isOpen ? 'Hide Details' : 'Details & Actions';
        }
    };

    collapseButtons.forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            const selector = btn.getAttribute('data-target');
            const target = selector ? document.querySelector(selector) : null;
            if (!target) {
                return;
            }

            if (window.bootstrap && window.bootstrap.Collapse) {
                const instance = window.bootstrap.Collapse.getOrCreateInstance(target, { toggle: false });
                instance.toggle();
                return;
            }

            const isOpen = target.classList.toggle('show');
            setCollapseButtonState(btn, isOpen);
        });
    });

    collapsePanels.forEach((panel) => {
        const linkedButton = getCollapseButton(panel);
        if (linkedButton) {
            setCollapseButtonState(linkedButton, panel.classList.contains('show'));
        }
        panel.addEventListener('shown.bs.collapse', () => {
            const btn = getCollapseButton(panel);
            if (btn) {
                setCollapseButtonState(btn, true);
            }
        });
        panel.addEventListener('hidden.bs.collapse', () => {
            const btn = getCollapseButton(panel);
            if (btn) {
                setCollapseButtonState(btn, false);
            }
        });
    });

    const africaFocus = { scale: 2.55, x: 0.57, y: 0.58, animate: false };

    const syncAfricaMapSize = (el) => {
        try {
            const $map = window.jQuery(el);
            const mapObject = $map.vectorMap('get', 'mapObject');
            mapObject.updateSize();
            mapObject.setFocus(africaFocus);
        } catch (error) {
            // no-op
        }
    };

    const initAfricaMap = (el) => {
        if (!el || el.dataset.ready === '1') {
            return;
        }
        if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.vectorMap !== 'function') {
            return;
        }

        const focusCode = String(el.getAttribute('data-focus-code') || '').trim().toLowerCase();
        const highlightColor = String(el.getAttribute('data-highlight-color') || '#16a34a');
        const highlightStage = String(el.getAttribute('data-highlight-stage') || 'member-state');
        const $map = window.jQuery(el);

        $map.vectorMap({
            map: 'world_mill_en',
            backgroundColor: 'transparent',
            zoomOnScroll: false,
            zoomButtons: false,
            panOnDrag: false,
            regionStyle: {
                initial: { fill: '#d9e3ec', stroke: '#ffffff', 'stroke-width': 0.65 },
                hover: { fill: '#cbd5e1' },
                selected: { fill: highlightColor },
                selectedHover: { fill: highlightColor },
            },
            regionsSelectable: true,
            regionsSelectableOne: true,
            selectedRegions: focusCode ? [focusCode] : [],
            onRegionTipShow: function (event, label, code) {
                if (focusCode && String(code || '').toLowerCase() === focusCode) {
                    label.html(label.html() + ' (' + highlightStage.replace('-', ' ') + ')');
                }
            },
        });

        el.dataset.ready = '1';
        syncAfricaMapSize(el);
    };

    document.querySelectorAll('.treaty-modal').forEach((modalEl) => {
        modalEl.addEventListener('shown.bs.modal', () => {
            const mapEl = modalEl.querySelector('.treaty-africa-map');
            if (!mapEl) {
                return;
            }
            if (mapEl.dataset.ready !== '1') {
                setTimeout(() => initAfricaMap(mapEl), 40);
                return;
            }
            setTimeout(() => syncAfricaMapSize(mapEl), 40);
        });
    });

    refresh();
});
</script>
@endpush
