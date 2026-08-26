@extends('layouts.app')

@section('title', 'API Sync Control Center')
@section('lean_admin_scripts', '1')

@push('styles')
<style>
    .api-sync-page {
        --sync-ink: #16332c;
        --sync-muted: #667a74;
        --sync-green: #176b55;
        --sync-green-dark: #0f493b;
        --sync-green-soft: #eaf5f1;
        --sync-gold: #c59730;
        --sync-border: #dfe9e5;
        --sync-surface: #fff;
        color: var(--sync-ink);
        padding-bottom: 2rem;
    }
    .api-sync-page .sync-hero {
        position: relative;
        overflow: hidden;
        padding: clamp(1.5rem, 3vw, 2.75rem);
        border-radius: 24px;
        color: #fff;
        background:
            radial-gradient(circle at 88% 12%, rgba(255,255,255,.16) 0 7%, transparent 7.5%),
            radial-gradient(circle at 91% 20%, transparent 0 15%, rgba(255,255,255,.08) 15.5% 18%, transparent 18.5%),
            linear-gradient(132deg, #0d332a 0%, #176b55 62%, #98701f 120%);
        box-shadow: 0 18px 40px rgba(15, 73, 59, .18);
    }
    .api-sync-page .sync-hero::after {
        content: "";
        position: absolute;
        inset: auto -45px -85px auto;
        width: 240px;
        height: 240px;
        border: 42px solid rgba(255,255,255,.06);
        border-radius: 50%;
        pointer-events: none;
    }
    .api-sync-page .sync-hero__content { position: relative; z-index: 1; max-width: 850px; }
    .api-sync-page .sync-eyebrow {
        margin-bottom: .55rem;
        font-size: .7rem;
        font-weight: 800;
        letter-spacing: .14em;
        text-transform: uppercase;
    }
    .api-sync-page .sync-hero h1 { color: #fff; font-size: clamp(1.55rem, 3vw, 2.35rem); }
    .api-sync-page .sync-hero p { max-width: 760px; color: rgba(255,255,255,.78); }
    .api-sync-page .sync-hero__meta { display: flex; flex-wrap: wrap; gap: .55rem; margin-top: 1.2rem; }
    .api-sync-page .sync-hero__chip,
    .api-sync-page .sync-health-pill {
        display: inline-flex;
        align-items: center;
        gap: .42rem;
        min-height: 32px;
        padding: .38rem .72rem;
        border: 1px solid rgba(255,255,255,.17);
        border-radius: 999px;
        color: #fff;
        background: rgba(255,255,255,.1);
        font-size: .75rem;
        font-weight: 650;
        backdrop-filter: blur(8px);
    }
    .api-sync-page .sync-health-pill.ready { background: rgba(20, 120, 83, .38); }
    .api-sync-page .sync-health-pill.warning { background: rgba(141, 72, 15, .42); }
    .api-sync-page .sync-health-dot { width: 8px; height: 8px; border-radius: 50%; background: #71e7ae; box-shadow: 0 0 0 4px rgba(113,231,174,.15); }
    .api-sync-page .sync-health-pill.warning .sync-health-dot { background: #ffd36a; box-shadow: 0 0 0 4px rgba(255,211,106,.15); }
    .api-sync-page .sync-card {
        border: 1px solid var(--sync-border);
        border-radius: 18px;
        background: var(--sync-surface);
        box-shadow: 0 10px 28px rgba(20, 54, 44, .055);
    }
    .api-sync-page .sync-metric { position: relative; overflow: hidden; height: 100%; padding: 1rem 1.05rem; }
    .api-sync-page .sync-metric::after { content: ""; position: absolute; right: -25px; bottom: -38px; width: 85px; height: 85px; border-radius: 50%; background: var(--sync-green-soft); }
    .api-sync-page .sync-metric__icon { display: grid; place-items: center; width: 38px; height: 38px; border-radius: 12px; color: var(--sync-green); background: var(--sync-green-soft); }
    .api-sync-page .sync-metric__value { margin-top: .8rem; font-size: 1.55rem; line-height: 1; font-weight: 800; }
    .api-sync-page .sync-metric__label { margin-top: .38rem; color: var(--sync-muted); font-size: .76rem; font-weight: 650; }
    .api-sync-page .sync-section-head { padding: 1.35rem 1.4rem .85rem; }
    .api-sync-page .sync-section-head h2 { font-size: 1.08rem; }
    .api-sync-page .sync-filter-nav { display: flex; gap: .45rem; overflow-x: auto; padding: .2rem 1.4rem 1rem; scrollbar-width: thin; }
    .api-sync-page .sync-filter-link { flex: 0 0 auto; padding: .5rem .78rem; border: 1px solid var(--sync-border); border-radius: 10px; color: #526761; background: #fff; font-size: .75rem; font-weight: 700; text-decoration: none; }
    .api-sync-page .sync-filter-link:hover { color: var(--sync-green); border-color: #a9cbc0; }
    .api-sync-page .sync-filter-link.active { color: #fff; border-color: var(--sync-green); background: var(--sync-green); }
    .api-sync-page .sync-request { margin: 0 1.4rem 1rem; padding: 1.2rem; border: 1px solid #e2ebe8; border-radius: 16px; background: #fff; transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease; }
    .api-sync-page .sync-request:hover { transform: translateY(-1px); border-color: #b0d0c6; box-shadow: 0 11px 25px rgba(23, 107, 85, .075); }
    .api-sync-page .sync-request__body { min-width: 0; }
    .api-sync-page .sync-status { display: inline-flex; align-items: center; gap: .4rem; padding: .32rem .65rem; border-radius: 999px; font-size: .7rem; font-weight: 800; white-space: nowrap; }
    .api-sync-page .sync-status::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    .api-sync-page .sync-status.pending { color: #805c00; background: #fff4cf; }
    .api-sync-page .sync-status.approval_in_progress,
    .api-sync-page .sync-status.activation_pending,
    .api-sync-page .sync-status.activation_received { color: #075985; background: #e0f2fe; }
    .api-sync-page .sync-status.active,
    .api-sync-page .sync-status.completed { color: #166534; background: #dcfce7; }
    .api-sync-page .sync-status.declined,
    .api-sync-page .sync-status.expired,
    .api-sync-page .sync-status.revoked { color: #475467; background: #eef1f3; }
    .api-sync-page .sync-status.failed { color: #991b1b; background: #fee2e2; }
    .api-sync-page .sync-request-id { display: inline-flex; align-items: center; flex: 1 1 300px; min-width: 0; max-width: 100%; gap: .35rem; }
    .api-sync-page .sync-request-id code { display: block; flex: 1 1 auto; min-width: 0; max-width: min(420px, 55vw); overflow: hidden; color: #536962; text-overflow: ellipsis; white-space: nowrap; }
    .api-sync-page .sync-copy { display: inline-grid; place-items: center; flex: 0 0 auto; width: 25px; height: 25px; padding: 0; border: 0; border-radius: 7px; color: #60736d; background: #f1f5f3; }
    .api-sync-page .sync-copy-feedback { flex: 0 0 auto; min-width: 0; color: #166534; font-size: .67rem; font-weight: 700; }
    .api-sync-page .sync-copy-feedback.is-error { color: #991b1b; }
    .api-sync-page .sync-origin-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .65rem; margin-top: 1rem; }
    .api-sync-page .sync-origin { min-width: 0; padding: .75rem .8rem; border-radius: 12px; background: #f6f9f8; }
    .api-sync-page .sync-origin span { display: block; margin-bottom: .2rem; color: var(--sync-muted); font-size: .67rem; font-weight: 750; text-transform: uppercase; letter-spacing: .05em; }
    .api-sync-page .sync-origin code { display: block; overflow-wrap: anywhere; color: #29483f; font-size: .72rem; }
    .api-sync-page .sync-tag { display: inline-flex; margin: .18rem .18rem .05rem 0; padding: .28rem .55rem; border-radius: 8px; color: #315e51; background: #edf6f2; font-size: .69rem; font-weight: 650; }
    .api-sync-page .sync-subtitle { margin: .85rem 0 .15rem; color: var(--sync-muted); font-size: .69rem; font-weight: 750; text-transform: uppercase; letter-spacing: .055em; }
    .api-sync-page .sync-notice { margin-top: 1rem; padding: .78rem .85rem; border-left: 3px solid var(--sync-gold); border-radius: 10px; color: #65501c; background: #fffaea; font-size: .76rem; }
    .api-sync-page .sync-failure { margin-top: 1rem; padding: .8rem .9rem; border-left: 3px solid #dc3545; border-radius: 10px; color: #7f1d1d; background: #fff1f2; font-size: .76rem; }
    .api-sync-page .sync-progress { height: 7px; border-radius: 99px; background: #e7eeeb; }
    .api-sync-page .sync-progress .progress-bar { border-radius: inherit; background: linear-gradient(90deg, var(--sync-green), var(--sync-gold)); }
    .api-sync-page .sync-actions { min-width: 150px; }
    .api-sync-page .sync-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(135px, 1fr)); gap: .6rem; margin-top: 1rem; }
    .api-sync-page .sync-kpi { padding: .65rem .75rem; border: 1px solid #e9efed; border-radius: 11px; background: #fafcfb; }
    .api-sync-page .sync-kpi span { display: block; margin-bottom: .18rem; color: var(--sync-muted); font-size: .66rem; }
    .api-sync-page .sync-kpi strong { font-size: .82rem; }
    .api-sync-page .sync-approval-meta { display: flex; flex-wrap: wrap; gap: .35rem .65rem; margin-top: .7rem; color: var(--sync-muted); font-size: .72rem; }
    .api-sync-page .sync-docs { margin-top: .85rem; padding: .9rem; border: 1px solid #eee2bd; border-radius: 12px; background: #fffcf3; }
    .api-sync-page .sync-empty { padding: 3.2rem 1.4rem; text-align: center; }
    .api-sync-page .sync-empty__icon { display: grid; place-items: center; width: 52px; height: 52px; margin: 0 auto 1rem; border-radius: 16px; color: var(--sync-green); background: var(--sync-green-soft); font-size: 1.2rem; }
    .api-sync-page .sync-aside { position: sticky; top: 86px; }
    .api-sync-page .sync-check { display: flex; gap: .75rem; padding: .8rem 0; border-bottom: 1px solid #edf1ef; }
    .api-sync-page .sync-check:last-child { border-bottom: 0; }
    .api-sync-page .sync-check__icon { display: grid; place-items: center; flex: 0 0 auto; width: 30px; height: 30px; border-radius: 9px; color: #166534; background: #dcfce7; }
    .api-sync-page .sync-check__icon.warning { color: #92400e; background: #fef3c7; }
    .api-sync-page .sync-step { display: flex; gap: .75rem; margin-bottom: .95rem; }
    .api-sync-page .sync-step__number { display: grid; place-items: center; flex: 0 0 auto; width: 30px; height: 30px; border-radius: 9px; color: var(--sync-green); background: var(--sync-green-soft); font-size: .75rem; font-weight: 800; }
    .api-sync-page .sync-table th { color: #71817c; font-size: .67rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; white-space: nowrap; }
    .api-sync-page .sync-table td { color: #334b44; font-size: .78rem; }
    .api-sync-page .sync-auto-refresh { display: inline-flex; align-items: center; gap: .45rem; color: var(--sync-muted); font-size: .73rem; }
    .api-sync-page .sync-auto-refresh .form-check-input { margin-top: 0; }
    .api-sync-page .sync-sensitive { font: 750 1.2rem/1 ui-monospace, SFMono-Regular, Menlo, monospace; letter-spacing: .18em; text-align: center; }
    .api-sync-page .sync-modal .modal-content { border: 0; border-radius: 18px; box-shadow: 0 22px 60px rgba(15, 45, 36, .2); }
    .api-sync-page .sync-generated-code { padding: 1rem; border: 1px dashed #d6b35b; border-radius: 12px; background: #fff9e8; font: 800 1.5rem/1 ui-monospace, SFMono-Regular, Menlo, monospace; letter-spacing: .15em; text-align: center; }
    .api-sync-page :is(a, button, input, textarea, select, summary):focus-visible {
        outline: 3px solid rgba(14, 165, 233, .52) !important;
        outline-offset: 2px;
        box-shadow: 0 0 0 2px #fff;
    }
    @media (max-width: 991.98px) {
        .api-sync-page .sync-aside { position: static; }
        .api-sync-page .sync-actions { width: 100%; min-width: 0; flex-direction: row !important; flex-wrap: wrap; }
        .api-sync-page .sync-actions .btn { flex: 1 1 145px; }
    }
    @media (max-width: 575.98px) {
        .api-sync-page .sync-hero { border-radius: 18px; }
        .api-sync-page .sync-origin-grid,
        .api-sync-page .sync-kpis { grid-template-columns: 1fr; }
        .api-sync-page .sync-request { margin-inline: .85rem; padding: 1rem; }
        .api-sync-page .sync-section-head,
        .api-sync-page .sync-filter-nav { padding-inline: .85rem; }
    }
</style>
@endpush

@section('content')
@php
    $statusLabels = [
        'pending' => 'Awaiting approval',
        'approval_in_progress' => 'Approval interrupted',
        'activation_pending' => 'Awaiting activation',
        'activation_received' => 'Activation received',
        'active' => 'Transfer active',
        'completed' => 'Completed',
        'declined' => 'Declined',
        'expired' => 'Expired',
        'revoked' => 'Revoked',
        'failed' => 'Needs attention',
    ];
    $formatBytes = static function ($bytes): string {
        $bytes = max(0, (int) $bytes);
        if ($bytes < 1024) return number_format($bytes).' B';
        if ($bytes < 1048576) return number_format($bytes / 1024, 1).' KB';
        if ($bytes < 1073741824) return number_format($bytes / 1048576, 1).' MB';
        return number_format($bytes / 1073741824, 1).' GB';
    };
    $flashMessage = session('success') ?: $successMessage;
    $reopenModal = (string) session('action_modal', '');
    $safeActionInput = (array) session('action_safe_input', []);
    $legacyPanelOpen = (bool) session('legacy_panel_open', false);
    $legacyAction = (string) session('legacy_action', '');
    $legacyPairingId = (string) session('legacy_pairing_id', '');
    $indexUrl = route('system.api-sync.index');
    $refreshUrl = route('system.api-sync.index', ['status' => $statusFilter]);
    $incoming->withPath($indexUrl);
    $history->withPath($indexUrl)->appends(['status' => $statusFilter]);
    $hasLiveRequests = collect($incoming->items())->contains(fn ($item) =>
        in_array($item->status, ['approval_in_progress', 'activation_pending', 'activation_received', 'active'], true)
        && ! ($item->status === 'activation_pending' && (!$item->credential_expires_at || $item->credential_expires_at->isPast()))
    );
@endphp

<main class="nxl-container api-sync-page"
      id="api-sync-workspace"
      data-has-live-requests="{{ $hasLiveRequests ? '1' : '0' }}"
      data-refresh-url="{{ $refreshUrl }}"
      data-generated-response="{{ $generatedCode ? '1' : '0' }}">
    <section class="sync-hero mb-4" aria-labelledby="api-sync-title">
        <div class="sync-hero__content">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <div class="sync-eyebrow">Incoming AU-PReMIS synchronization</div>
                    <h1 class="fw-bold mb-2" id="api-sync-title">API Sync Control Center</h1>
                    <p class="mb-0">Review signed AU-PReMIS requests, authorize only the requested access, and follow each immutable snapshot through transfer completion.</p>
                </div>
                <span class="sync-health-pill {{ $isReady ? 'ready' : 'warning' }}">
                    <span class="sync-health-dot" aria-hidden="true"></span>
                    {{ $isReady ? 'Configuration ready' : 'Configuration needs attention' }}
                </span>
            </div>
            <div class="sync-hero__meta">
                <span class="sync-hero__chip"><i class="feather-server" aria-hidden="true"></i>{{ $providerName }}{{ $providerCode ? ' / '.$providerCode : '' }}</span>
                <span class="sync-hero__chip"><i class="feather-shield" aria-hidden="true"></i>Signed protocol {{ $v2Enabled ? 'v2 enabled' : 'v2 disabled' }}</span>
                <span class="sync-hero__chip"><i class="feather-file-text" aria-hidden="true"></i>Documents {{ $documentsEnabled ? 'enabled' : 'disabled' }}</span>
                <span class="sync-hero__chip"><i class="feather-clock" aria-hidden="true"></i>Updated {{ $refreshedAt->format('H:i:s T') }}</span>
            </div>
        </div>
    </section>

    @if($flashMessage)
        <div class="alert alert-success border-0 shadow-sm d-flex align-items-start gap-2" role="status">
            <i class="feather-check-circle mt-1" aria-hidden="true"></i><span>{{ $flashMessage }}</span>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger border-0 shadow-sm" role="alert">
            <div class="d-flex align-items-center gap-2 fw-bold"><i class="feather-alert-circle" aria-hidden="true"></i>The requested action was not completed.</div>
            <ul class="mb-0 mt-2 ps-4">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    @if(!$isReady)
        <div class="alert alert-warning border-0 shadow-sm" role="alert">
            <i class="feather-alert-triangle me-1" aria-hidden="true"></i>
            New synchronization approvals are paused until every security-readiness item below is complete. Existing transfers can still be reviewed or stopped safely.
        </div>
    @endif

    <section class="row g-3 mb-4" aria-label="Synchronization overview">
        @foreach([
            ['value' => $summary['total'], 'label' => 'All requests', 'icon' => 'feather-layers'],
            ['value' => $summary['awaiting'], 'label' => 'Awaiting approval', 'icon' => 'feather-user-check'],
            ['value' => $summary['transfer'], 'label' => 'In progress', 'icon' => 'feather-refresh-cw'],
            ['value' => $summary['completed'], 'label' => 'Completed', 'icon' => 'feather-check-circle'],
            ['value' => $summary['attention'], 'label' => 'Needs attention', 'icon' => 'feather-alert-triangle'],
        ] as $metric)
            <div class="col-6 col-md-4 col-xl">
                <article class="sync-card sync-metric">
                    <span class="sync-metric__icon"><i class="{{ $metric['icon'] }}" aria-hidden="true"></i></span>
                    <div class="sync-metric__value">{{ number_format($metric['value']) }}</div>
                    <div class="sync-metric__label">{{ $metric['label'] }}</div>
                </article>
            </div>
        @endforeach
    </section>

    <div class="row g-4 align-items-start mb-4">
        <div class="col-xl-8">
            <section class="sync-card" aria-labelledby="incoming-sync-title">
                <header class="sync-section-head d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <h2 class="fw-bold mb-1" id="incoming-sync-title">Incoming synchronization requests</h2>
                        <p class="small text-muted mb-0">Every request is signed and bound to this exact ATTP origin.</p>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        @if($hasLiveRequests)
                            <label class="sync-auto-refresh" for="api-sync-auto-refresh" title="Refresh this page every 30 seconds while work is active">
                                <input class="form-check-input" id="api-sync-auto-refresh" type="checkbox" checked>
                                Auto-refresh
                            </label>
                        @endif
                        <a class="btn btn-light btn-sm" href="{{ route('system.api-sync.index', ['status' => $statusFilter]) }}">
                            <i class="feather-refresh-cw me-1" aria-hidden="true"></i>Refresh
                        </a>
                    </div>
                </header>

                <nav class="sync-filter-nav" aria-label="Filter synchronization requests by status">
                    @foreach($statusFilters as $key => $label)
                        <a class="sync-filter-link {{ $statusFilter === $key ? 'active' : '' }}"
                           href="{{ route('system.api-sync.index', ['status' => $key]) }}"
                           @if($statusFilter === $key) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </nav>

                @forelse($incoming as $invitation)
                    @php
                        $pairing = $invitation->pairing;
                        $snapshotStatus = $pairing?->snapshot_status;
                        $documentStatus = $pairing?->document_snapshot_status;
                        $documentRequested = in_array('documents.metadata.read', (array) $invitation->requested_scopes, true)
                            && in_array('documents.content.read', (array) $invitation->requested_scopes, true);
                        $credentialExpired = ! $invitation->credential_expires_at || $invitation->credential_expires_at->isPast();
                        $recovering = ! $credentialExpired
                            && in_array($invitation->status, ['approval_in_progress', 'activation_received'], true)
                            && \Illuminate\Support\Str::isUuid((string) $invitation->confirmation_request_id)
                            && \Illuminate\Support\Str::isUuid((string) $invitation->confirmation_request_nonce);
                        $approvalExpired = in_array($invitation->status, ['pending', 'approval_in_progress'], true)
                            && ($credentialExpired || (! $recovering && $invitation->expires_at?->isPast()));
                        $activationExpired = $invitation->status === 'activation_pending' && $credentialExpired;
                        $displayStatus = ($approvalExpired || $activationExpired) ? 'expired' : $invitation->status;
                        $recordFailed = $snapshotStatus === 'failed' || filled($pairing?->snapshot_failure_reason);
                        $documentFailed = $documentStatus === 'failed' || filled($pairing?->document_snapshot_failure_reason);
                        $hasFailure = $displayStatus === 'failed' || $recordFailed || $documentFailed || filled($invitation->terminal_error_code);
                        $terminal = in_array($displayStatus, ['completed', 'declined', 'expired', 'revoked', 'failed'], true);
                        $canApprove = ! $credentialExpired
                            && (($invitation->status === 'pending' && $invitation->expires_at?->isFuture()) || $recovering);
                        $canDecline = in_array($invitation->status, ['pending', 'approval_in_progress'], true) && !$approvalExpired;
                        $canRevoke = in_array($invitation->status, ['activation_received', 'active'], true);
                        $showApproveAction = $canApprove && $isReady && (bool) request()->user()?->can('api_sync.invitations.approve');
                        $showDeclineAction = $canDecline && (bool) request()->user()?->can('api_sync.invitations.decline');
                        $showRevokeAction = $canRevoke && (bool) request()->user()?->can('api_sync.invitations.revoke');
                        $hasRequestActions = $showApproveAction || $showDeclineAction || $showRevokeAction;
                        $progress = match (true) {
                            $displayStatus === 'completed' => 100,
                            $snapshotStatus === 'ready' && (!$documentRequested || $documentStatus === 'ready') => 92,
                            $snapshotStatus === 'ready' && in_array($documentStatus, ['pending', 'building'], true) => 82,
                            $snapshotStatus === 'ready' => 78,
                            $snapshotStatus === 'building' => 70,
                            $invitation->status === 'active' => 58,
                            $invitation->status === 'activation_received' => 48,
                            $invitation->status === 'activation_pending' => 40,
                            $invitation->status === 'approval_in_progress' => 28,
                            $invitation->status === 'pending' => 12,
                            default => 0,
                        };
                        $approveModalKey = 'approve-'.$invitation->id;
                        $declineModalKey = 'decline-'.$invitation->id;
                        $revokeModalKey = 'revoke-'.$invitation->id;
                    @endphp

                    <article class="sync-request">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                            <div class="sync-request__body flex-grow-1">
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                    <h3 class="h6 fw-bold mb-0">{{ $invitation->central_name }}</h3>
                                    <span class="sync-status {{ $displayStatus }}">{{ $statusLabels[$displayStatus] ?? ucfirst(str_replace('_', ' ', $displayStatus)) }}</span>
                                    @if($recovering)<span class="badge bg-soft-warning text-warning">Safe retry available</span>@endif
                                </div>
                                <div class="d-flex flex-wrap align-items-center gap-2 small text-muted">
                                    <span class="sync-request-id">
                                        <span>Request</span><code title="{{ $invitation->id }}">{{ $invitation->id }}</code>
                                        <button class="sync-copy" type="button" data-copy-value="{{ $invitation->id }}" aria-label="Copy request ID" title="Copy request ID"><i class="feather-copy" aria-hidden="true"></i></button>
                                        <span class="sync-copy-feedback" data-copy-feedback aria-live="polite"></span>
                                    </span>
                                    <span aria-hidden="true">&middot;</span>
                                    <span>Received {{ $invitation->received_at?->diffForHumans() }}</span>
                                    <span aria-hidden="true">&middot;</span>
                                    <span>Approval expires {{ $invitation->expires_at?->format('d M Y, H:i T') }}</span>
                                </div>

                                <div class="sync-origin-grid">
                                    <div class="sync-origin"><span>Trusted AU-PReMIS origin</span><code>{{ $invitation->central_origin }}</code></div>
                                    <div class="sync-origin"><span>This ATTP target</span><code>{{ $invitation->target_origin }}</code></div>
                                </div>

                                <div class="sync-subtitle">Requested datasets</div>
                                <div>@forelse((array) $invitation->requested_datasets as $dataset)<span class="sync-tag">{{ ucwords(str_replace(['_', '.'], ' ', $dataset)) }}</span>@empty<span class="small text-muted">None listed</span>@endforelse</div>
                                <div class="sync-subtitle">Requested access</div>
                                <div>@forelse((array) $invitation->requested_scopes as $scope)<span class="sync-tag">{{ ucwords(str_replace(['_', '.'], ' ', $scope)) }}</span>@empty<span class="small text-muted">None listed</span>@endforelse</div>

                                @if($canApprove && !$recovering)
                                    <div class="sync-notice"><i class="feather-shield me-1" aria-hidden="true"></i>Approval releases only the datasets and capabilities shown above to the exact trusted origin. Review each item before entering the code.</div>
                                @elseif($recovering)
                                    <div class="sync-notice"><i class="feather-rotate-ccw me-1" aria-hidden="true"></i>The previous confirmation was interrupted after a secure request ID was reserved. Retrying reuses that binding and does not create a second approval.</div>
                                @endif
                                @if($canApprove && !$isReady)
                                    <div class="sync-notice"><i class="feather-lock me-1" aria-hidden="true"></i>Approval is temporarily unavailable because this ATTP environment has incomplete security-readiness checks. Complete the items in the readiness panel before authorizing this request.</div>
                                @endif

                                @if($hasFailure)
                                    <div class="sync-failure" role="alert">
                                        <strong class="d-block mb-1"><i class="feather-alert-triangle me-1" aria-hidden="true"></i>Synchronization needs attention</strong>
                                        @if(filled($invitation->terminal_error_code))<div>Terminal result: {{ ucfirst(str_replace('_', ' ', $invitation->terminal_error_code)) }}</div>@endif
                                        @if(filled($pairing?->snapshot_failure_reason))<div>Record snapshot: {{ $pairing->snapshot_failure_reason }}</div>@endif
                                        @if(filled($pairing?->document_snapshot_failure_reason))<div>Document snapshot: {{ $pairing->document_snapshot_failure_reason }}</div>@endif
                                        @if(!filled($invitation->terminal_error_code) && !filled($pairing?->snapshot_failure_reason) && !filled($pairing?->document_snapshot_failure_reason))<div>Review the activity log below for the most recent failure details.</div>@endif
                                    </div>
                                @endif

                                @if(!$terminal)
                                    <div class="mt-3">
                                        <div class="progress sync-progress" role="progressbar" aria-label="Synchronization progress for {{ $invitation->central_name }}" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100">
                                            <div class="progress-bar" style="width: {{ $progress }}%"></div>
                                        </div>
                                        <div class="d-flex justify-content-between gap-2 mt-2 small text-muted">
                                            <span>{{ $snapshotStatus ? 'Records: '.ucfirst(str_replace('_', ' ', $snapshotStatus)).($documentRequested ? ' · Documents: '.ucfirst(str_replace('_', ' ', $documentStatus ?: 'pending')) : '') : 'Local authorization stage' }}</span>
                                            <span>{{ $progress }}%</span>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            @if($hasRequestActions)
                                <div class="sync-actions d-flex flex-column align-items-stretch gap-2">
                                    @if($showApproveAction)
                                        <button class="btn btn-success btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#{{ $approveModalKey }}"><i class="feather-shield me-1" aria-hidden="true"></i>{{ $recovering ? 'Retry approval' : 'Review & approve' }}</button>
                                    @endif
                                    @if($showDeclineAction)
                                        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#{{ $declineModalKey }}">Decline</button>
                                    @endif
                                    @if($showRevokeAction)
                                        <button class="btn btn-outline-danger btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#{{ $revokeModalKey }}"><i class="feather-x-circle me-1" aria-hidden="true"></i>Stop transfer</button>
                                    @endif
                                </div>
                            @endif
                        </div>

                        @if($pairing)
                            <div class="sync-kpis">
                                <div class="sync-kpi"><span>Snapshot records</span><strong>{{ number_format($pairing->snapshot_record_count ?? 0) }}</strong></div>
                                <div class="sync-kpi"><span>Snapshot size</span><strong>{{ $formatBytes($pairing->snapshot_bytes ?? 0) }}</strong></div>
                                <div class="sync-kpi"><span>Transfer requests</span><strong>{{ number_format($pairing->request_count ?? 0) }}</strong></div>
                                <div class="sync-kpi"><span>Credential expires</span><strong>{{ $pairing->token_expires_at?->format('d M Y, H:i T') ?? 'Not issued' }}</strong></div>
                            </div>
                        @endif
                        @if($invitation->approver || $invitation->approved_at)
                            <div class="sync-approval-meta">
                                <span><i class="feather-user-check me-1" aria-hidden="true"></i>Approved by {{ $invitation->approver?->name ?? 'Authorized administrator' }}</span>
                                @if($invitation->approved_at)<span><i class="feather-clock me-1" aria-hidden="true"></i>{{ $invitation->approved_at->format('d M Y, H:i T') }}</span>@endif
                            </div>
                        @endif

                        @if($pairing && $documentRequested)
                            @can('api_sync.documents.view')
                                <section class="sync-docs" aria-label="Document snapshot checkpoint">
                                    <div class="d-flex flex-wrap justify-content-between gap-2 small">
                                        <strong><i class="feather-file-text me-1" aria-hidden="true"></i>Document checkpoint</strong>
                                        <span class="text-muted">{{ $formatBytes($pairing->document_snapshot_bytes ?? 0) }} frozen privately</span>
                                    </div>
                                    <div class="row g-2 mt-1 small">
                                        <div class="col-4"><span class="text-muted d-block">Discovered</span><strong>{{ number_format($pairing->document_discovered_count ?? 0) }}</strong></div>
                                        <div class="col-4"><span class="text-muted d-block">Ready</span><strong class="text-success">{{ number_format($pairing->document_ready_count ?? 0) }}</strong></div>
                                        <div class="col-4"><span class="text-muted d-block">Held safely</span><strong class="{{ ($pairing->document_held_count ?? 0) > 0 ? 'text-warning' : '' }}">{{ number_format($pairing->document_held_count ?? 0) }}</strong></div>
                                    </div>
                                    <p class="small text-muted mt-2 mb-0">Held files are excluded safely when missing, changed, empty, oversized, active, encrypted, or outside the project-document allowlist.</p>
                                </section>
                            @endcan
                        @endif
                    </article>

                    @if($showApproveAction)
                        <div class="modal fade sync-modal" id="{{ $approveModalKey }}" tabindex="-1" aria-labelledby="{{ $approveModalKey }}-title" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
                                <form method="POST" action="{{ route('system.api-sync.invitations.approve', $invitation) }}" autocomplete="off" data-secure-form>
                                    @csrf
                                    <div class="modal-header border-0 pb-0">
                                        <div><div class="sync-eyebrow text-success">Sensitive authorization</div><h2 class="modal-title h5 fw-bold" id="{{ $approveModalKey }}-title">{{ $recovering ? 'Retry secure approval' : 'Approve AU-PReMIS request' }}</h2></div>
                                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close approval dialog"></button>
                                    </div>
                                    <div class="modal-body p-4">
                                        <div class="alert alert-warning small"><i class="feather-alert-triangle me-1" aria-hidden="true"></i>Confirm every item in this exact authorization scope. The seven-digit code is never stored by ATTP.</div>
                                        <div class="small text-muted">Request <code>{{ $invitation->id }}</code> &middot; expires {{ $invitation->expires_at?->format('d M Y, H:i T') }}</div>
                                        <div class="sync-origin-grid">
                                            <div class="sync-origin"><span>Trusted AU-PReMIS origin</span><code>{{ $invitation->central_origin }}</code></div>
                                            <div class="sync-origin"><span>This ATTP target</span><code>{{ $invitation->target_origin }}</code></div>
                                        </div>
                                        <div class="sync-subtitle">Datasets being authorized</div>
                                        <div>@forelse((array) $invitation->requested_datasets as $dataset)<span class="sync-tag">{{ ucwords(str_replace(['_', '.'], ' ', $dataset)) }}</span>@empty<span class="small text-muted">None listed</span>@endforelse</div>
                                        <div class="sync-subtitle">Access being authorized</div>
                                        <div class="mb-3">@forelse((array) $invitation->requested_scopes as $scope)<span class="sync-tag">{{ ucwords(str_replace(['_', '.'], ' ', $scope)) }}</span>@empty<span class="small text-muted">None listed</span>@endforelse</div>
                                        <label class="form-label fw-semibold" for="code-{{ $invitation->id }}">Seven-digit code from AU-PReMIS</label>
                                        <input class="form-control sync-sensitive {{ $reopenModal === $approveModalKey && $errors->has('authorization_code') ? 'is-invalid' : '' }}"
                                               id="code-{{ $invitation->id }}"
                                               name="authorization_code"
                                               inputmode="numeric"
                                               pattern="[0-9]{7}"
                                               maxlength="7"
                                               autocomplete="one-time-code"
                                               aria-describedby="code-help-{{ $invitation->id }}{{ $reopenModal === $approveModalKey && $errors->has('authorization_code') ? ' code-error-'.$invitation->id : '' }}"
                                               @if($reopenModal === $approveModalKey && $errors->has('authorization_code')) aria-invalid="true" @endif
                                               required>
                                        @if($reopenModal === $approveModalKey && $errors->has('authorization_code'))<div class="invalid-feedback" id="code-error-{{ $invitation->id }}">{{ $errors->first('authorization_code') }}</div>@endif
                                        <div class="form-text mb-3" id="code-help-{{ $invitation->id }}">Obtain it directly from the authorized AU-PReMIS administrator.</div>
                                        <label class="form-label fw-semibold" for="approve-password-{{ $invitation->id }}">Your current password</label>
                                        <input class="form-control {{ $reopenModal === $approveModalKey && $errors->has('current_password') ? 'is-invalid' : '' }}"
                                               id="approve-password-{{ $invitation->id }}"
                                               type="password"
                                               name="current_password"
                                               autocomplete="current-password"
                                               @if($reopenModal === $approveModalKey && $errors->has('current_password')) aria-invalid="true" aria-describedby="approve-password-error-{{ $invitation->id }}" @endif
                                               required>
                                        @if($reopenModal === $approveModalKey && $errors->has('current_password'))<div class="invalid-feedback" id="approve-password-error-{{ $invitation->id }}">{{ $errors->first('current_password') }}</div>@endif
                                    </div>
                                    <div class="modal-footer border-0 pt-0">
                                        <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
                                        <button class="btn btn-success" type="submit" data-submit-once><i class="feather-check-circle me-1" aria-hidden="true"></i>{{ $recovering ? 'Retry secure confirmation' : 'Authorize secure transfer' }}</button>
                                    </div>
                                </form>
                            </div></div>
                        </div>
                    @endif

                    @if($showDeclineAction)
                        <div class="modal fade sync-modal" id="{{ $declineModalKey }}" tabindex="-1" aria-labelledby="{{ $declineModalKey }}-title" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                                <form method="POST" action="{{ route('system.api-sync.invitations.decline', $invitation) }}" data-secure-form>
                                    @csrf
                                    <div class="modal-header border-0"><h2 class="modal-title h5 fw-bold" id="{{ $declineModalKey }}-title">Decline request</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close decline dialog"></button></div>
                                    <div class="modal-body">
                                        <p class="small text-muted">No data will be released. The reason is retained in the append-only audit trail.</p>
                                        <label class="form-label fw-semibold" for="decline-reason-{{ $invitation->id }}">Reason</label>
                                        <textarea class="form-control mb-3 {{ $reopenModal === $declineModalKey && $errors->has('reason') ? 'is-invalid' : '' }}" id="decline-reason-{{ $invitation->id }}" name="reason" minlength="10" maxlength="500" rows="3" @if($reopenModal === $declineModalKey && $errors->has('reason')) aria-invalid="true" aria-describedby="decline-reason-error-{{ $invitation->id }}" @endif required>{{ $reopenModal === $declineModalKey ? data_get($safeActionInput, 'reason') : '' }}</textarea>
                                        @if($reopenModal === $declineModalKey && $errors->has('reason'))<div class="invalid-feedback mb-3" id="decline-reason-error-{{ $invitation->id }}">{{ $errors->first('reason') }}</div>@endif
                                        <label class="form-label fw-semibold" for="decline-password-{{ $invitation->id }}">Current password</label>
                                        <input class="form-control {{ $reopenModal === $declineModalKey && $errors->has('current_password') ? 'is-invalid' : '' }}" id="decline-password-{{ $invitation->id }}" type="password" name="current_password" autocomplete="current-password" @if($reopenModal === $declineModalKey && $errors->has('current_password')) aria-invalid="true" aria-describedby="decline-password-error-{{ $invitation->id }}" @endif required>
                                        @if($reopenModal === $declineModalKey && $errors->has('current_password'))<div class="invalid-feedback" id="decline-password-error-{{ $invitation->id }}">{{ $errors->first('current_password') }}</div>@endif
                                    </div>
                                    <div class="modal-footer border-0"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger" type="submit" data-submit-once>Decline request</button></div>
                                </form>
                            </div></div>
                        </div>
                    @endif

                    @if($showRevokeAction)
                        <div class="modal fade sync-modal" id="{{ $revokeModalKey }}" tabindex="-1" aria-labelledby="{{ $revokeModalKey }}-title" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                                <form method="POST" action="{{ route('system.api-sync.invitations.revoke', $invitation) }}" data-secure-form>
                                    @csrf
                                    <div class="modal-header border-0"><h2 class="modal-title h5 fw-bold" id="{{ $revokeModalKey }}-title">Stop active transfer</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close revoke dialog"></button></div>
                                    <div class="modal-body">
                                        <div class="alert alert-danger small">This immediately revokes the credential and schedules the private snapshot for removal.</div>
                                        <label class="form-label fw-semibold" for="revoke-reason-{{ $invitation->id }}">Reason</label>
                                        <textarea class="form-control mb-3 {{ $reopenModal === $revokeModalKey && $errors->has('reason') ? 'is-invalid' : '' }}" id="revoke-reason-{{ $invitation->id }}" name="reason" minlength="10" maxlength="500" rows="3" @if($reopenModal === $revokeModalKey && $errors->has('reason')) aria-invalid="true" aria-describedby="revoke-reason-error-{{ $invitation->id }}" @endif required>{{ $reopenModal === $revokeModalKey ? data_get($safeActionInput, 'reason') : '' }}</textarea>
                                        @if($reopenModal === $revokeModalKey && $errors->has('reason'))<div class="invalid-feedback mb-3" id="revoke-reason-error-{{ $invitation->id }}">{{ $errors->first('reason') }}</div>@endif
                                        <label class="form-label fw-semibold" for="revoke-password-{{ $invitation->id }}">Current password</label>
                                        <input class="form-control {{ $reopenModal === $revokeModalKey && $errors->has('current_password') ? 'is-invalid' : '' }}" id="revoke-password-{{ $invitation->id }}" type="password" name="current_password" autocomplete="current-password" @if($reopenModal === $revokeModalKey && $errors->has('current_password')) aria-invalid="true" aria-describedby="revoke-password-error-{{ $invitation->id }}" @endif required>
                                        @if($reopenModal === $revokeModalKey && $errors->has('current_password'))<div class="invalid-feedback" id="revoke-password-error-{{ $invitation->id }}">{{ $errors->first('current_password') }}</div>@endif
                                    </div>
                                    <div class="modal-footer border-0"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Keep running</button><button class="btn btn-danger" type="submit" data-submit-once>Revoke credential</button></div>
                                </form>
                            </div></div>
                        </div>
                    @endif
                @empty
                    <div class="sync-empty">
                        <span class="sync-empty__icon"><i class="feather-inbox" aria-hidden="true"></i></span>
                        <h3 class="h6 fw-bold">{{ $statusFilter === 'all' ? 'No incoming requests yet' : 'No requests match this filter' }}</h3>
                        <p class="small text-muted mb-0">{{ $statusFilter === 'all' ? 'A verified request will appear here after AU-PReMIS connects this ATTP origin.' : 'Choose another status or return to all requests.' }}</p>
                        @if($statusFilter !== 'all')<a class="btn btn-light btn-sm mt-3" href="{{ route('system.api-sync.index') }}">View all requests</a>@endif
                    </div>
                @endforelse

                @if($incoming->hasPages())
                    <div class="px-4 pb-4">{{ $incoming->links() }}</div>
                @endif
            </section>
        </div>

        <div class="col-xl-4">
            <div class="sync-aside">
                <section class="sync-card mb-4" aria-labelledby="readiness-title">
                    <div class="p-4">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div><div class="sync-eyebrow text-success">Environment</div><h2 class="h5 fw-bold mb-0" id="readiness-title">Security readiness</h2></div>
                            <span class="badge {{ $isReady ? 'bg-soft-success text-success' : 'bg-soft-warning text-warning' }}">{{ $readinessChecks->where('ready', true)->count() }}/{{ $readinessChecks->count() }}</span>
                        </div>
                        @foreach($readinessChecks as $check)
                            <div class="sync-check">
                                <span class="sync-check__icon {{ $check['ready'] ? '' : 'warning' }}"><i class="{{ $check['ready'] ? 'feather-check' : 'feather-alert-triangle' }}" aria-hidden="true"></i></span>
                                <div><div class="small fw-bold">{{ $check['label'] }}</div><div class="small text-muted">{{ $check['detail'] }}</div></div>
                            </div>
                        @endforeach
                        <div class="small text-muted mt-3"><i class="feather-link me-1" aria-hidden="true"></i><span class="text-break">{{ $publicOrigin }}</span></div>
                        @if($trustedCentralOrigin)<div class="small text-muted mt-1"><i class="feather-shield me-1" aria-hidden="true"></i><span class="text-break">{{ $trustedCentralOrigin }}</span></div>@endif
                    </div>
                </section>

                <section class="sync-card" aria-labelledby="approval-guide-title">
                    <div class="p-4">
                        <div class="sync-eyebrow text-warning">Administrator guide</div>
                        <h2 class="h5 fw-bold mb-3" id="approval-guide-title">How approval works</h2>
                        @foreach([
                            ['Verify the request', 'Check the trusted central, this domain, expiry, datasets, and access.'],
                            ['Enter the central code', 'Use the seven digits supplied through the approved AU channel.'],
                            ['Wait for credential proof', 'AU-PReMIS proves possession of its separate high-entropy credential.'],
                            ['Continue working', 'The '.$snapshotQueue.' queue prepares and transfers an immutable snapshot.'],
                        ] as $index => $step)
                            <div class="sync-step"><span class="sync-step__number">{{ $index + 1 }}</span><div><div class="small fw-bold">{{ $step[0] }}</div><div class="small text-muted">{{ $step[1] }}</div></div></div>
                        @endforeach
                        <div class="sync-notice"><strong>Security reminder</strong><br>Never approve an unexpected request or share an authorization code outside an approved channel.</div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    @can('api_sync.audit.view')
        <section class="sync-card mb-4" aria-labelledby="sync-activity-title">
            <header class="sync-section-head">
                <h2 class="fw-bold mb-1" id="sync-activity-title">Synchronization activity</h2>
                <p class="small text-muted mb-0">Latest 30 human-readable security and transfer events from the append-only audit trail.</p>
            </header>
            <div class="table-responsive">
                <table class="table sync-table align-middle mb-0" aria-labelledby="sync-activity-title">
                    <caption class="visually-hidden">Latest 30 API synchronization security and transfer events</caption>
                    <thead><tr><th class="ps-4" scope="col">When</th><th scope="col">Activity</th><th scope="col">Source</th><th scope="col">Actor</th></tr></thead>
                    <tbody>
                        @forelse($activity as $item)
                            <tr>
                                <td class="ps-4 text-nowrap">{{ $item['occurred_at']?->format('d M Y, H:i') }}</td>
                                <td><strong>{{ $item['message'] }}</strong><div class="text-muted small">{{ ucwords(str_replace('_', ' ', $item['event_type'])) }}</div></td>
                                <td><span class="badge bg-light text-dark">{{ $item['source'] }}</span></td>
                                <td>{{ $item['actor'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-5 text-center text-muted">No synchronization activity has been recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endcan

    @if($legacyV1Enabled)
        <details class="sync-card mb-4" @if($generatedCode || $legacyPanelOpen) open @endif>
            <summary class="p-4 fw-bold" style="cursor:pointer">Legacy locally generated pairings <span class="badge bg-soft-warning text-warning ms-2">Migration mode</span></summary>
            <div class="border-top p-4">
                <div class="alert alert-warning small">Legacy v1 is enabled temporarily. New connections should be initiated by signed AU-PReMIS invitations.</div>
                @if($generatedCode)
                    <div class="mb-4">
                        <div class="small fw-bold mb-2">Single-use pairing code</div>
                        <div class="sync-generated-code" aria-label="Generated single-use pairing code">{{ $generatedCode }}</div>
                        <div class="small text-muted mt-2">Expires {{ $generatedExpiresAt ? \Illuminate\Support\Carbon::parse($generatedExpiresAt)->format('d M Y, H:i T') : 'soon' }}. Share it only through the approved secure channel.</div>
                    </div>
                @endif
                @can('api_sync.generate')
                    <form class="row g-2 align-items-end mb-4" method="POST" action="{{ route('system.api-sync.pairings.generate') }}" data-secure-form>
                        @csrf
                        <div class="col-md-7">
                            <label class="form-label small fw-bold" for="legacy-current-password">Current password</label>
                            <input class="form-control {{ $legacyPanelOpen && in_array($legacyAction, ['', 'generate'], true) && $errors->has('current_password') ? 'is-invalid' : '' }}"
                                   id="legacy-current-password"
                                   type="password"
                                   name="current_password"
                                   autocomplete="current-password"
                                   @if($legacyPanelOpen && in_array($legacyAction, ['', 'generate'], true) && $errors->has('current_password')) aria-invalid="true" aria-describedby="legacy-generate-password-error" @endif
                                   required>
                            @if($legacyPanelOpen && in_array($legacyAction, ['', 'generate'], true) && $errors->has('current_password'))<div class="invalid-feedback" id="legacy-generate-password-error">{{ $errors->first('current_password') }}</div>@endif
                        </div>
                        <div class="col-md-auto"><button class="btn btn-warning" type="submit" data-submit-once><i class="feather-key me-1" aria-hidden="true"></i>Generate {{ $pairingTtlMinutes }}-minute code</button></div>
                    </form>
                @endcan
                <div class="table-responsive">
                    <table class="table sync-table align-middle">
                        <caption class="visually-hidden">Legacy API synchronization pairings and available actions</caption>
                        <thead><tr><th scope="col">Created</th><th scope="col">Consumer</th><th scope="col">Status</th><th scope="col">Snapshot</th><th scope="col">Action</th></tr></thead>
                        <tbody>
                            @forelse($history as $pairing)
                                <tr>
                                    <td class="text-nowrap">{{ $pairing->created_at?->format('d M Y, H:i') }}</td>
                                    <td>{{ $pairing->consumer_name ?: 'Not claimed' }}</td>
                                    <td>{{ ucfirst($pairing->status) }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $pairing->snapshot_status ?: 'not started')) }}</td>
                                    <td>
                                        @if(!in_array($pairing->status, ['completed', 'revoked', 'expired', 'abandoned'], true))
                                            @can('api_sync.revoke')
                                                <form method="POST" action="{{ route('system.api-sync.pairings.revoke', $pairing) }}" data-secure-form class="d-flex gap-2">
                                                    @csrf
                                                    <div>
                                                        <input class="form-control form-control-sm {{ $legacyPanelOpen && $legacyAction === 'revoke' && $legacyPairingId === (string) $pairing->id && $errors->has('current_password') ? 'is-invalid' : '' }}"
                                                               type="password"
                                                               name="current_password"
                                                               autocomplete="current-password"
                                                               placeholder="Current password"
                                                               aria-label="Current password to revoke pairing"
                                                               @if($legacyPanelOpen && $legacyAction === 'revoke' && $legacyPairingId === (string) $pairing->id && $errors->has('current_password')) aria-invalid="true" aria-describedby="legacy-revoke-password-error-{{ $pairing->id }}" @endif
                                                               required>
                                                        @if($legacyPanelOpen && $legacyAction === 'revoke' && $legacyPairingId === (string) $pairing->id && $errors->has('current_password'))<div class="invalid-feedback" id="legacy-revoke-password-error-{{ $pairing->id }}">{{ $errors->first('current_password') }}</div>@endif
                                                    </div>
                                                    <button class="btn btn-outline-danger btn-sm" type="submit" data-submit-once>Revoke</button>
                                                </form>
                                            @endcan
                                        @else<span class="text-muted">&mdash;</span>@endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">No legacy pairings.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($history->hasPages()){{ $history->links() }}@endif
            </div>
        </details>
    @endif
</main>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const workspace = document.getElementById('api-sync-workspace');
    if (!workspace) return;
    const refreshUrl = workspace.dataset.refreshUrl || @json(route('system.api-sync.index'));

    if (workspace.dataset.generatedResponse === '1' && window.history?.replaceState) {
        window.history.replaceState({}, document.title, refreshUrl);
    }

    workspace.querySelectorAll('[data-secure-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('[data-submit-once]');
            if (!button || button.disabled) return;
            button.disabled = true;
            button.dataset.originalHtml = button.innerHTML;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Working securely&hellip;';
        });
    });

    workspace.querySelectorAll('[data-copy-value]').forEach((button) => {
        button.addEventListener('click', async () => {
            const feedback = button.closest('.sync-request-id')?.querySelector('[data-copy-feedback]');
            try {
                await navigator.clipboard.writeText(button.dataset.copyValue || '');
                const icon = button.querySelector('i');
                if (icon) icon.className = 'feather-check';
                button.setAttribute('aria-label', 'Request ID copied');
                button.setAttribute('title', 'Request ID copied');
                if (feedback) {
                    feedback.classList.remove('is-error');
                    feedback.textContent = 'Copied';
                }
                window.setTimeout(() => {
                    if (icon) icon.className = 'feather-copy';
                    button.setAttribute('aria-label', 'Copy request ID');
                    button.setAttribute('title', 'Copy request ID');
                    if (feedback) feedback.textContent = '';
                }, 1600);
            } catch (_) {
                button.setAttribute('title', 'Copy unavailable');
                if (feedback) {
                    feedback.classList.add('is-error');
                    feedback.textContent = 'Copy unavailable';
                }
            }
        });
    });

    const modalToReopen = @json($reopenModal);
    if (modalToReopen && window.bootstrap?.Modal) {
        const modal = document.getElementById(modalToReopen);
        if (modal) {
            modal.addEventListener('shown.bs.modal', () => {
                const invalidField = modal.querySelector('.is-invalid');
                if (invalidField instanceof HTMLElement) invalidField.focus();
            }, { once: true });
            window.bootstrap.Modal.getOrCreateInstance(modal).show();
        }
    } else {
        const legacyInvalidField = workspace.querySelector('details[open] .is-invalid');
        if (legacyInvalidField instanceof HTMLElement) legacyInvalidField.focus();
    }

    const autoRefresh = document.getElementById('api-sync-auto-refresh');
    if (autoRefresh) {
        const storageKey = 'attp-api-sync-auto-refresh';
        try {
            const storedPreference = window.sessionStorage.getItem(storageKey);
            if (storedPreference !== null) autoRefresh.checked = storedPreference === '1';
            autoRefresh.addEventListener('change', () => window.sessionStorage.setItem(storageKey, autoRefresh.checked ? '1' : '0'));
        } catch (_) {
            // Auto-refresh still works when browser storage is unavailable.
        }
        window.setInterval(() => {
            const editing = workspace.querySelector('.modal.show, form:focus-within');
            if (autoRefresh.checked && !document.hidden && !editing) window.location.assign(refreshUrl);
        }, 30000);
    }
});
</script>
@endpush
