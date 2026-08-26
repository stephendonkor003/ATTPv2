<?php

it('builds the API Sync administration workspace from bounded filter and health data', function () {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents($root.'/app/Http/Controllers/System/ApiSyncController.php');

    expect($controller)
        ->toContain('private const STATUS_FILTERS')
        ->toContain("'awaiting' => [")
        ->toContain('ApiSyncInvitation::STATUS_APPROVAL_IN_PROGRESS')
        ->toContain("'transfer' => [")
        ->toContain('ApiSyncInvitation::STATUS_ACTIVATION_RECEIVED')
        ->toContain("'attention' => [ApiSyncInvitation::STATUS_FAILED]")
        ->toContain("request->query('status', 'all')")
        ->toContain("DB::table('api_sync_invitations')")
        ->toContain("->groupBy('status')")
        ->toContain("'summary' => ".'$summary')
        ->toContain("'statusFilter' => ".'$statusFilter')
        ->toContain("'statusFilters' => [")
        ->toContain("->paginate(12, ['*'], 'invitations_page')")
        ->toContain('->withQueryString()')
        ->toContain("->orWhereHas('pairing', ".'$operationalFailure'.')')
        ->toContain("->whereDoesntHave('pairing', ".'$operationalFailure'.')')
        ->toContain("'readinessChecks' => ".'$readinessChecks')
        ->toContain("'isReady' => ".'$readinessChecks->every')
        ->toContain('snapshot_failure_reason')
        ->toContain('document_snapshot_failure_reason');
});

it('classifies scheduler and credential expiries as closed without hiding valid resumable approvals', function () {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents($root.'/app/Http/Controllers/System/ApiSyncController.php');
    $view = file_get_contents($root.'/resources/views/system/api-sync/index.blade.php');

    expect($controller)
        ->toContain('$nominallyExpired = static function')
        ->toContain("->where('expires_at', '<=', ".'$now'.')')
        ->toContain("->orWhere('credential_expires_at', '<=', ".'$now'.')')
        ->toContain("->whereNull('confirmation_request_id')")
        ->toContain("->orWhereNull('confirmation_request_nonce')")
        ->toContain("->where('status', ApiSyncInvitation::STATUS_ACTIVATION_PENDING)")
        ->toContain('$availableForApproval = static function')
        ->toContain("->where('credential_expires_at', '>', ".'$now'.')')
        ->toContain("->where('expires_at', '>', ".'$now'.')')
        ->toContain("->whereNotNull('confirmation_request_id')")
        ->toContain("->whereNotNull('confirmation_request_nonce')")
        ->toContain("->when(\$statusFilter === 'awaiting'")
        ->toContain('->where($availableForApproval)')
        ->toContain("->when(\$statusFilter === 'closed'")
        ->toContain('->orWhere($nominallyExpired)')
        ->toContain('$availableForTransfer = static fn ($query)')
        ->toContain("->where('status', '!=', ApiSyncInvitation::STATUS_ACTIVATION_PENDING)")
        ->toContain('->where($availableForTransfer)')
        ->toContain("'closed' => \$closedCount");

    expect($view)
        ->toContain('$credentialExpired = ! $invitation->credential_expires_at || $invitation->credential_expires_at->isPast()')
        ->toContain('$recovering = ! $credentialExpired')
        ->toContain('$canApprove = ! $credentialExpired')
        ->toContain("\$activationExpired = \$invitation->status === 'activation_pending' && \$credentialExpired")
        ->toContain("\$displayStatus = (\$approvalExpired || \$activationExpired) ? 'expired' : \$invitation->status");
});

it('rejects expired approval credentials before outbound confirmation and includes them in maintenance', function () {
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/ApiSync/ApiSyncInvitationService.php');
    $activate = str($service)->between('public function activate(', 'public function finalize(')->toString();
    $approve = str($service)->between('public function approve(', 'public function decline(')->toString();
    $maintenance = str($service)->between('public function expireAndPrune(', 'private function activationDescriptor(')->toString();

    expect($approve)
        ->toContain('$credentialExpired = in_array($locked->status, [')
        ->toContain('! $locked->credential_expires_at || $locked->credential_expires_at->isPast()')
        ->toContain("'status' => ApiSyncInvitation::STATUS_EXPIRED")
        ->toContain("'approval_credential_expired'")
        ->toContain("if ((\$attempt['failure'] ?? null) instanceof ApiSyncException)")
        ->toContain('Http::acceptJson()');
    expect(strpos($approve, '$credentialExpired = in_array'))
        ->toBeLessThan(strpos($approve, 'Http::acceptJson()'));
    expect(strpos($approve, '$credentialExpired = in_array'))
        ->toBeLessThan(strpos($approve, '$recovering = in_array'));
    expect(strpos($approve, "if ((\$attempt['failure'] ?? null) instanceof ApiSyncException)"))
        ->toBeLessThan(strpos($approve, 'Http::acceptJson()'));

    expect($activate)
        ->toContain('DB::transaction(function () use ($invitation, $signature, $digest, $request): ApiSyncPairing|ApiSyncException')
        ->toContain("['reason' => 'activation_credential_expired']")
        ->toContain("return new ApiSyncException('activation_credential_expired'")
        ->toContain('if ($activation instanceof ApiSyncException)')
        ->toContain('throw $activation;');

    expect($maintenance)
        ->toContain("where('status', ApiSyncInvitation::STATUS_PENDING)")
        ->toContain("->orWhere('credential_expires_at', '<=', now())")
        ->toContain('ApiSyncInvitation::STATUS_APPROVAL_IN_PROGRESS')
        ->toContain('&& ($invitation->expires_at?->isPast() || $invitation->credential_expires_at?->isPast())')
        ->toContain("'status' => ApiSyncInvitation::STATUS_EXPIRED");
});

it('pins both API Sync paginators to the index route after a legacy POST render', function () {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents($root.'/app/Http/Controllers/System/ApiSyncController.php');
    $view = file_get_contents($root.'/resources/views/system/api-sync/index.blade.php');

    expect(substr_count($controller, "->withPath(route('system.api-sync.index'))"))->toBe(2);
    expect($view)
        ->toContain("\$indexUrl = route('system.api-sync.index')")
        ->toContain('data-refresh-url="{{ $refreshUrl }}"')
        ->toContain("workspace.dataset.generatedResponse === '1'")
        ->toContain('window.history.replaceState({}, document.title, refreshUrl)')
        ->toContain('window.location.assign(refreshUrl)');
});

it('gates approval controls on the complete security-readiness result', function () {
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/system/api-sync/index.blade.php');

    expect($view)
        ->toContain('$showApproveAction = $canApprove && $isReady')
        ->toContain('@if($showApproveAction)')
        ->toContain('@if($canApprove && !$isReady)')
        ->toContain('New synchronization approvals are paused until every security-readiness item')
        ->toContain('Approval is temporarily unavailable because this ATTP environment has incomplete security-readiness checks');
});

it('keeps recovery actions and operational failures visible in the API Sync page', function () {
    $root = dirname(__DIR__, 2);
    $view = file_get_contents($root.'/resources/views/system/api-sync/index.blade.php');

    expect($view)
        ->toContain('$statusFilters')
        ->toContain('$statusFilter')
        ->toContain("route('system.api-sync.index', ['status' => ".'$key'.'])')
        ->toContain('aria-current="page"')
        ->toContain('$summary')
        ->toContain('$readinessChecks')
        ->toContain('$recovering = ! $credentialExpired')
        ->toContain('$canApprove = ! $credentialExpired')
        ->toContain("\$recovering ? 'Retry approval' : 'Review & approve'")
        ->toContain('snapshot_failure_reason')
        ->toContain('document_snapshot_failure_reason')
        ->toContain('terminal_error_code')
        ->toContain("session('action_modal',")
        ->toContain("session('action_safe_input'")
        ->toContain('id="api-sync-auto-refresh"')
        ->toContain('data-has-live-requests=')
        ->toContain('window.sessionStorage.getItem(storageKey)')
        ->toContain("workspace.querySelector('.modal.show, form:focus-within')");
});

it('gives API Sync controls and live state accessible names', function () {
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/system/api-sync/index.blade.php');

    expect($view)
        ->toContain('aria-labelledby=')
        ->toContain('aria-label="Close approval dialog"')
        ->toContain('aria-label="Close decline dialog"')
        ->toContain('aria-label="Close revoke dialog"')
        ->toContain('aria-label="Synchronization progress for ')
        ->toContain('role="alert"')
        ->toContain('for="api-sync-auto-refresh"');
});

it('removes sensitive approval values while preserving only safe modal recovery input', function () {
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/System/ApiSyncController.php');

    expect($controller)
        ->toContain("request->request->remove('authorization_code')")
        ->toContain("request->request->remove('current_password')")
        ->toContain("->with('action_modal'")
        ->toContain("->with('action_safe_input'")
        ->not->toContain('->withInput(')
        ->not->toContain("->with('authorization_code'")
        ->not->toContain("->with('current_password'");
});

it('handles legacy password failures without flashing the submitted secret', function () {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents($root.'/app/Http/Controllers/System/ApiSyncController.php');
    $view = file_get_contents($root.'/resources/views/system/api-sync/index.blade.php');
    $generate = str($controller)->between('public function generate', 'public function revoke')->toString();
    $revoke = str($controller)->between('public function revoke', 'public function approveInvitation')->toString();

    expect($generate)
        ->toContain("request->request->remove('current_password')")
        ->toContain("withErrors(['current_password'")
        ->toContain("->with('legacy_panel_open', true)")
        ->toContain("->with('legacy_action', 'generate')")
        ->not->toContain('->withInput(')
        ->not->toContain("->with('current_password'");

    expect($revoke)
        ->toContain("request->request->remove('current_password')")
        ->toContain("withErrors(['current_password'")
        ->toContain("->with('legacy_panel_open', true)")
        ->toContain("->with('legacy_action', 'revoke')")
        ->toContain("->with('legacy_pairing_id'")
        ->not->toContain('->withInput(')
        ->not->toContain("->with('current_password'");

    expect($view)
        ->toContain("session('legacy_panel_open', false)")
        ->toContain("session('legacy_action', '')")
        ->toContain("session('legacy_pairing_id', '')")
        ->toContain('@if($generatedCode || $legacyPanelOpen) open @endif')
        ->toContain("\$legacyAction === 'revoke'")
        ->toContain("\$errors->has('current_password')")
        ->not->toContain("old('current_password')");
});
