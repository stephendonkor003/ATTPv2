@extends('layouts.app')

@section('title', 'API Sync')

@push('styles')
<style>
.sync-hero{background:linear-gradient(135deg,#102f27,#176b55 60%,#b98720);color:#fff;border-radius:20px;overflow:hidden;position:relative}.sync-hero:after{content:"";position:absolute;width:280px;height:280px;border:55px solid rgba(255,255,255,.08);border-radius:50%;right:-90px;top:-105px}.sync-card{border:1px solid #e5ece9;border-radius:16px;box-shadow:0 8px 28px rgba(20,54,44,.06)}.sync-eyebrow{font-size:.72rem;text-transform:uppercase;letter-spacing:.12em;opacity:.82}.sync-status{display:inline-flex;align-items:center;gap:.4rem;padding:.32rem .72rem;border-radius:999px;font-size:.75rem;font-weight:700}.sync-status.pending{background:#fff4d6;color:#805c00}.sync-status.approval_in_progress,.sync-status.activation_pending,.sync-status.activation_received{background:#e0f2fe;color:#075985}.sync-status.active,.sync-status.completed{background:#dcfce7;color:#166534}.sync-status.declined,.sync-status.expired,.sync-status.revoked{background:#f2f4f7;color:#475467}.sync-status.failed{background:#fee2e2;color:#991b1b}.sync-request{border:1px solid #e6ece9;border-radius:14px;transition:.2s}.sync-request:hover{border-color:#a7c9bd;box-shadow:0 8px 24px rgba(23,107,85,.08)}.sync-pill{display:inline-flex;padding:.24rem .55rem;background:#f1f7f4;border-radius:7px;font-size:.72rem;color:#315e51;margin:.12rem}.sync-tip{border-left:3px solid #c79a2d;background:#fffbef}.sync-step{width:32px;height:32px;display:inline-grid;place-items:center;border-radius:9px;background:#e9f5f0;color:#176b55;font-weight:700;flex:0 0 auto}.sync-progress{height:7px;background:#e9efec}.sync-progress .progress-bar{background:linear-gradient(90deg,#176b55,#c79a2d)}.sync-table th{white-space:nowrap;font-size:.72rem;letter-spacing:.04em;text-transform:uppercase;color:#667085}.modal-content{border:0;border-radius:18px}.sensitive-input{font:700 1.25rem/1 ui-monospace,SFMono-Regular,Menlo,monospace;letter-spacing:.18em;text-align:center}
</style>
@endpush

@section('content')
@php
$labels=['pending'=>'Awaiting local approval','approval_in_progress'=>'Verifying approval','activation_pending'=>'Waiting for central activation','activation_received'=>'Credential verified','active'=>'Transfer active','completed'=>'Completed','declined'=>'Declined','expired'=>'Expired','revoked'=>'Revoked','failed'=>'Needs attention'];
@endphp
<div class="main-content">
    <div class="sync-hero p-4 p-lg-5 mb-4"><div class="position-relative" style="z-index:1;max-width:820px"><div class="sync-eyebrow mb-2">Secure inter-platform exchange</div><h3 class="fw-bold mb-2">Incoming AU-PReMIS synchronization</h3><p class="mb-0 opacity-75">Review requests sent by the African Union’s central portfolio platform. Nothing leaves this project until a local administrator confirms the AU-PReMIS code and AU-PReMIS proves possession of its separate high-entropy credential.</p></div></div>

    @if(session('success'))<div class="alert alert-success border-0 shadow-sm"><i class="feather-check-circle me-2"></i>{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger border-0 shadow-sm"><strong>Approval was not completed.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="row g-4 mb-4">
        <div class="col-xl-8"><section class="sync-card card h-100">
            <div class="card-header bg-white border-0 d-flex flex-wrap justify-content-between align-items-center gap-2 p-4 pb-2"><div><h5 class="fw-bold mb-1">Incoming requests</h5><p class="text-muted small mb-0">Each request is signed and bound to this exact project domain.</p></div><a class="btn btn-light btn-sm" href="{{ route('system.api-sync.index') }}"><i class="feather-refresh-cw me-1"></i>Refresh status</a></div>
            <div class="card-body p-4">
            @forelse($incoming as $invitation)
                @php
                $snapshot=$invitation->pairing?->snapshot_status;
                $documentStatus=$invitation->pairing?->document_snapshot_status;
                $documentRequested=in_array('documents.metadata.read',(array)$invitation->requested_scopes,true)&&in_array('documents.content.read',(array)$invitation->requested_scopes,true);
                $progress=match(true){$invitation->status==='completed'=>100,$snapshot==='ready'&&(!$documentRequested||$documentStatus==='ready')=>92,$snapshot==='ready'&&in_array($documentStatus,['pending','building'],true)=>82,$snapshot==='ready'=>78,$snapshot==='building'=>72,$invitation->status==='active'=>58,$invitation->status==='activation_received'=>48,$invitation->status==='activation_pending'=>40,$invitation->status==='approval_in_progress'=>30,$invitation->status==='pending'=>12,default=>0};
                $terminal=in_array($invitation->status,['completed','declined','expired','revoked','failed'],true);
                @endphp
                <article class="sync-request p-3 p-lg-4 mb-3">
                    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3"><div class="flex-grow-1">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2"><h6 class="fw-bold mb-0">{{ $invitation->central_name }}</h6><span class="sync-status {{ $invitation->status }}"><i class="feather-circle" style="font-size:7px"></i>{{ $labels[$invitation->status] ?? ucfirst(str_replace('_',' ',$invitation->status)) }}</span></div>
                        <div class="small text-muted mb-2">Request <span class="font-monospace">{{ Str::limit($invitation->id,18,'…') }}</span> · Received {{ $invitation->received_at?->diffForHumans() }} · Approval expires {{ $invitation->expires_at?->format('d M Y, H:i T') }}</div>
                        <div class="small mb-2"><span class="text-muted">Trusted central origin:</span> <span class="font-monospace text-break">{{ $invitation->central_origin }}</span><br><span class="text-muted">This ATTP target:</span> <span class="font-monospace text-break">{{ $invitation->target_origin }}</span></div>
                        <div><span class="small text-muted me-1">Datasets:</span>@foreach($invitation->requested_datasets as $dataset)<span class="sync-pill">{{ ucwords(str_replace('_',' ',$dataset)) }}</span>@endforeach</div>
                        <div class="mt-1"><span class="small text-muted me-1">Approved capabilities:</span>@foreach($invitation->requested_scopes as $scope)<span class="sync-pill">{{ ucwords(str_replace(['.','_'],' ',$scope)) }}</span>@endforeach</div>
                        @if($invitation->status==='pending')<div class="alert alert-warning py-2 px-3 small mt-3 mb-0"><i class="feather-shield me-1"></i>Approval releases only the datasets and document capabilities listed above to the exact central origin. Review every item before entering the code.</div>@endif
                        @if(!$terminal)<div class="progress sync-progress mt-3" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:{{ $progress }}%"></div></div><div class="d-flex justify-content-between small text-muted mt-2"><span>{{ $snapshot ? 'Records: '.ucfirst(str_replace('_',' ',$snapshot)).($documentRequested?' · Documents: '.ucfirst(str_replace('_',' ',$documentStatus??'pending')):'') : 'Local authorization stage' }}</span><span>{{ $progress }}%</span></div>@endif
                    </div><div class="d-flex flex-lg-column align-items-stretch gap-2" style="min-width:145px">
                        @if($invitation->status==='pending' && $invitation->expires_at?->isFuture())
                            @can('api_sync.invitations.approve')<button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#approve-{{ $invitation->id }}"><i class="feather-shield me-1"></i>Review & approve</button>@endcan
                            @can('api_sync.invitations.decline')<button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#decline-{{ $invitation->id }}">Decline</button>@endcan
                        @elseif(in_array($invitation->status,['activation_received','active'],true))
                            @can('api_sync.invitations.revoke')<button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#revoke-{{ $invitation->id }}"><i class="feather-x-circle me-1"></i>Stop transfer</button>@endcan
                        @endif
                    </div></div>
                    @if($invitation->pairing)<div class="row g-2 mt-3 small"><div class="col-md-4"><div class="bg-light rounded-3 p-2"><span class="text-muted d-block">Snapshot records</span><strong>{{ number_format($invitation->pairing->snapshot_record_count??0) }}</strong></div></div><div class="col-md-4"><div class="bg-light rounded-3 p-2"><span class="text-muted d-block">Transfer requests</span><strong>{{ number_format($invitation->pairing->request_count??0) }}</strong></div></div><div class="col-md-4"><div class="bg-light rounded-3 p-2"><span class="text-muted d-block">Credential expires</span><strong>{{ $invitation->pairing->token_expires_at?->format('d M, H:i')??'—' }}</strong></div></div></div>@endif
                    @if($invitation->pairing&&$documentRequested)
                    @can('api_sync.documents.view')
                    <div class="sync-tip rounded-3 p-3 mt-3 small">
                        <div class="d-flex flex-wrap justify-content-between gap-2"><strong><i class="feather-file-text me-1"></i>Approved document checkpoint</strong><span>{{ number_format($invitation->pairing->document_snapshot_bytes??0) }} bytes frozen privately</span></div>
                        <div class="row g-2 mt-1"><div class="col-4"><span class="text-muted d-block">Discovered</span><strong>{{ number_format($invitation->pairing->document_discovered_count??0) }}</strong></div><div class="col-4"><span class="text-muted d-block">Ready</span><strong class="text-success">{{ number_format($invitation->pairing->document_ready_count??0) }}</strong></div><div class="col-4"><span class="text-muted d-block">Held safely</span><strong class="{{ ($invitation->pairing->document_held_count??0)>0?'text-warning':'text-muted' }}">{{ number_format($invitation->pairing->document_held_count??0) }}</strong></div></div>
                        <div class="text-muted mt-2"><i class="feather-info me-1"></i>Held files never stop approved records from being prepared. They are excluded when missing, changed, empty, oversized, active, encrypted, or outside the fixed project-document allowlist.</div>
                    </div>
                    @endcan
                    @endif
                </article>

                @if($invitation->status==='pending')
                <div class="modal fade" id="approve-{{ $invitation->id }}" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="{{ route('system.api-sync.invitations.approve',$invitation) }}" autocomplete="off">@csrf
                    <div class="modal-header border-0 pb-0"><div><div class="sync-eyebrow text-success mb-1">Sensitive authorization</div><h5 class="modal-title fw-bold">Approve AU-PReMIS request</h5></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body p-4"><div class="alert alert-warning small"><i class="feather-alert-triangle me-1"></i>Confirm the exact central and target origins, expiry, listed datasets, and document capabilities first. Approval releases nothing else. The seven-digit code is never stored by ATTP.</div><label class="form-label fw-semibold" for="code-{{ $invitation->id }}">Seven-digit code from AU-PReMIS</label><input id="code-{{ $invitation->id }}" class="form-control sensitive-input mb-2" name="authorization_code" inputmode="numeric" pattern="[0-9]{7}" maxlength="7" autocomplete="one-time-code" required><div class="form-text mb-3">Obtain it directly from the authorized AU-PReMIS administrator.</div><label class="form-label fw-semibold" for="password-{{ $invitation->id }}">Your current password</label><input id="password-{{ $invitation->id }}" class="form-control" type="password" name="current_password" autocomplete="current-password" required></div>
                    <div class="modal-footer border-0 pt-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success" type="submit" data-submit-once><i class="feather-check-circle me-1"></i>Authorize secure transfer</button></div>
                </form></div></div></div>
                <div class="modal fade" id="decline-{{ $invitation->id }}" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="{{ route('system.api-sync.invitations.decline',$invitation) }}">@csrf<div class="modal-header border-0"><h5 class="modal-title fw-bold">Decline request</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><label class="form-label">Reason</label><textarea class="form-control mb-3" name="reason" minlength="10" maxlength="500" rows="3" required></textarea><label class="form-label">Current password</label><input class="form-control" type="password" name="current_password" autocomplete="current-password" required></div><div class="modal-footer border-0"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger" type="submit" data-submit-once>Decline request</button></div></form></div></div></div>
                @endif
                @if(in_array($invitation->status,['activation_received','active'],true))
                <div class="modal fade" id="revoke-{{ $invitation->id }}" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="{{ route('system.api-sync.invitations.revoke',$invitation) }}">@csrf<div class="modal-header border-0"><h5 class="modal-title fw-bold">Stop active transfer</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="alert alert-danger small">This immediately revokes the credential and schedules the snapshot for removal.</div><label class="form-label">Reason</label><textarea class="form-control mb-3" name="reason" minlength="10" maxlength="500" rows="3" required></textarea><label class="form-label">Current password</label><input class="form-control" type="password" name="current_password" autocomplete="current-password" required></div><div class="modal-footer border-0"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Keep running</button><button class="btn btn-danger" type="submit" data-submit-once>Revoke credential</button></div></form></div></div></div>
                @endif
            @empty
                <div class="text-center py-5"><span class="sync-step mx-auto mb-3"><i class="feather-inbox"></i></span><h6 class="fw-bold">No incoming requests</h6><p class="text-muted small mb-0">A signed request will appear after AU-PReMIS connects this project domain.</p></div>
            @endforelse
            @if($incoming->hasPages())<div class="mt-3">{{ $incoming->links() }}</div>@endif
            </div>
        </section></div>
        <div class="col-xl-4"><aside class="sync-card card h-100"><div class="card-body p-4"><h5 class="fw-bold mb-3"><i class="feather-help-circle text-warning me-2"></i>How approval works</h5>
            <div class="d-flex gap-3 mb-3"><span class="sync-step">1</span><div><strong>Verify the request</strong><div class="small text-muted">Check AU-PReMIS, this domain, datasets and expiry.</div></div></div><div class="d-flex gap-3 mb-3"><span class="sync-step">2</span><div><strong>Enter the central code</strong><div class="small text-muted">Use the seven digits from AU-PReMIS and confirm your password.</div></div></div><div class="d-flex gap-3 mb-3"><span class="sync-step">3</span><div><strong>Credential proof</strong><div class="small text-muted">AU-PReMIS proves its separate high-entropy credential.</div></div></div><div class="d-flex gap-3 mb-4"><span class="sync-step">4</span><div><strong>Continue working</strong><div class="small text-muted">A durable queue transfers an immutable snapshot in the background.</div></div></div>
            <div class="sync-tip rounded-3 p-3 small"><strong>Security tip</strong><br>Never approve an unexpected request. Share codes only through an approved AU channel.</div><hr><div class="small text-muted"><i class="feather-file me-1"></i>Documents are included only when their two separate capabilities are explicitly requested and approved.</div>
        </div></aside></div>
    </div>

    @can('api_sync.audit.view')<section class="sync-card card mb-4"><div class="card-header bg-white border-0 p-4 pb-2"><h5 class="fw-bold mb-1">Human-readable activity</h5><p class="text-muted small mb-0">Security and transfer events are append-only.</p></div><div class="table-responsive"><table class="table sync-table align-middle mb-0"><thead><tr><th class="ps-4">When</th><th>Activity</th><th>Administrator</th></tr></thead><tbody>@forelse($invitationEvents as $event)<tr><td class="ps-4 text-nowrap">{{ $event->created_at?->format('d M Y, H:i') }}</td><td><strong>{{ $event->message }}</strong><div class="text-muted small">{{ ucwords(str_replace('_',' ',$event->event_type)) }}</div></td><td>{{ $event->user?->name??'Trusted system' }}</td></tr>@empty<tr><td colspan="3" class="text-center text-muted py-4">No invitation activity yet.</td></tr>@endforelse</tbody></table></div></section>@endcan

    @if($legacyV1Enabled)
        <details class="sync-card card mb-4"><summary class="card-header bg-white border-0 p-4 fw-bold" style="cursor:pointer">Legacy locally generated pairings <span class="badge bg-light text-muted ms-2">Temporary migration mode</span></summary><div class="card-body border-top"><div class="alert alert-warning small">The legacy v1 feature flag is enabled for a controlled migration window. New connections must still start in AU-PReMIS.</div><div class="table-responsive"><table class="table sync-table align-middle"><thead><tr><th>Created</th><th>Consumer</th><th>Status</th><th>Snapshot</th></tr></thead><tbody>@forelse($history as $pairing)<tr><td>{{ $pairing->created_at?->format('d M Y, H:i') }}</td><td>{{ $pairing->consumer_name??'Not claimed' }}</td><td>{{ ucfirst($pairing->status) }}</td><td>{{ ucfirst($pairing->snapshot_status??'Not started') }}</td></tr>@empty<tr><td colspan="4" class="text-center text-muted">No legacy pairings.</td></tr>@endforelse</tbody></table></div>{{ $history->links() }}</div></details>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('form').forEach(form=>form.addEventListener('submit',()=>{const button=form.querySelector('[data-submit-once]');if(button){button.disabled=true;button.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Working securely…'}}));
@if($incoming->contains(fn($item)=>in_array($item->status,['approval_in_progress','activation_pending','activation_received','active'],true))) setTimeout(()=>{if(!document.querySelector('.modal.show'))window.location.reload()},20000); @endif
</script>
@endpush
