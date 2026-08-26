<?php

use App\Exceptions\ApiSyncException;
use App\Http\Controllers\System\ApiSyncController;
use App\Models\ApiSyncInvitation;
use App\Models\ApiSyncPairing;
use App\Models\User;
use App\Services\ApiSync\ApiSyncInvitationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

foreach ([
    'api_sync_pairings',
    'api_sync_events',
    'api_sync_invitations',
    'api_sync_invitation_events',
    'api_sync_v2_nonces',
    'api_sync_snapshot_datasets',
    'api_sync_snapshot_records',
    'api_sync_snapshot_documents',
    'api_sync_snapshot_document_issues',
] as $table) {
    $assert(Schema::hasTable($table), "Required API Sync table {$table} is missing.");
}

$assert(
    Schema::hasColumns('api_sync_pairings', [
        'inbound_invitation_id',
        'snapshot_failure_reason',
        'document_snapshot_status',
        'document_snapshot_failure_reason',
    ]),
    'The API Sync pairing schema is missing administration-workspace fields.',
);
$assert(
    Schema::hasColumns('api_sync_invitations', [
        'authorization_id',
        'authorization_receipt',
        'terminal_error_code',
    ]),
    'The API Sync invitation schema is missing recovery or failure fields.',
);

/** @var User|null $administrator */
$administrator = User::query()
    ->with(['role.permissions', 'permissions'])
    ->whereHas('role.permissions', fn ($query) => $query->where('permissions.name', 'api_sync.view'))
    ->first();

$assert($administrator !== null, 'No administrator with api_sync.view is available for the authenticated smoke.');
$assert($administrator->hasPermission('api_sync.view'), 'The selected smoke user cannot view API Sync.');

$session = $app->make('session')->driver();
$session->start();
$request = Request::create('/system/api-sync', 'GET', ['status' => 'all']);
$request->setLaravelSession($session);
$request->setUserResolver(static fn (): User => $administrator);
$route = $app->make('router')->getRoutes()->getByName('system.api-sync.index');
$assert($route !== null, 'The system.api-sync.index route is not registered.');
$request->setRouteResolver(static fn () => $route);
$app->instance('request', $request);

Auth::shouldUse('web');
Auth::setUser($administrator);
View::share('errors', new ViewErrorBag);

/** @var ApiSyncController $controller */
$controller = $app->make(ApiSyncController::class);
$workspace = $controller->index($request);
$assert($workspace->name() === 'system.api-sync.index', 'The API Sync controller returned the wrong workspace view.');

$data = $workspace->getData();
foreach (['incoming', 'summary', 'statusFilter', 'statusFilters', 'readinessChecks', 'activity'] as $key) {
    $assert(array_key_exists($key, $data), "The API Sync workspace omitted {$key}.");
}
$assert($data['statusFilter'] === 'all', 'The API Sync workspace did not preserve the requested status filter.');
$assert((int) $data['summary']['total'] === DB::table('api_sync_invitations')->count(), 'The workspace total does not match stored invitations.');
$indexUrl = route('system.api-sync.index');
$assert($data['incoming']->path() === $indexUrl, 'The invitation paginator is not pinned to the API Sync index route.');
$assert($data['history']->path() === $indexUrl, 'The legacy paginator is not pinned to the API Sync index route.');

$legacyPostRequest = Request::create('/system/api-sync/pairings', 'POST');
$legacyPostRequest->setLaravelSession($session);
$legacyPostRequest->setUserResolver(static fn (): User => $administrator);
$legacyPostRequest->setRouteResolver(static fn () => $route);
$app->instance('request', $legacyPostRequest);
$legacyPostData = (function (Request $postRequest): array {
    return $this->viewData($postRequest);
})->call($controller, $legacyPostRequest);
$assert($legacyPostData['incoming']->path() === $indexUrl, 'A legacy POST changed the invitation paginator path.');
$assert($legacyPostData['history']->path() === $indexUrl, 'A legacy POST changed the history paginator path.');

$attentionRequest = Request::create('/system/api-sync', 'GET', ['status' => 'attention']);
$attentionRequest->setLaravelSession($session);
$attentionRequest->setUserResolver(static fn (): User => $administrator);
$attentionRequest->setRouteResolver(static fn () => $route);
$app->instance('request', $attentionRequest);
$attentionData = $controller->index($attentionRequest)->getData();
$assert($attentionData['statusFilter'] === 'attention', 'The attention filter was not accepted.');
$assert(
    $attentionData['incoming']->every(fn ($invitation): bool => $invitation->status === 'failed'
        || $invitation->pairing?->snapshot_status === 'failed'
        || $invitation->pairing?->document_snapshot_status === 'failed'),
    'The attention filter returned an invitation without a workflow or operational failure.',
);

$transferRequest = Request::create('/system/api-sync', 'GET', ['status' => 'transfer']);
$transferRequest->setLaravelSession($session);
$transferRequest->setUserResolver(static fn (): User => $administrator);
$transferRequest->setRouteResolver(static fn () => $route);
$app->instance('request', $transferRequest);
$transferData = $controller->index($transferRequest)->getData();
$assert($transferData['statusFilter'] === 'transfer', 'The transfer filter was not accepted.');
$assert(
    $transferData['incoming']->every(fn ($invitation): bool => in_array(
        $invitation->status,
        ['activation_pending', 'activation_received', 'active'],
        true,
    ) && $invitation->pairing?->snapshot_status !== 'failed'
        && $invitation->pairing?->document_snapshot_status !== 'failed'),
    'The transfer filter included a request that needs operational attention.',
);

$invalidRequest = Request::create('/system/api-sync', 'GET', ['status' => 'not-a-real-filter']);
$invalidRequest->setLaravelSession($session);
$invalidRequest->setUserResolver(static fn (): User => $administrator);
$invalidRequest->setRouteResolver(static fn () => $route);
$app->instance('request', $invalidRequest);
$invalidData = $controller->index($invalidRequest)->getData();
$assert($invalidData['statusFilter'] === 'all', 'An unknown API Sync filter did not fail safely to all requests.');

DB::beginTransaction();
try {
    $fixtureNow = now();
    $insertInvitation = static function (
        string $status,
        $expiresAt,
        string $label,
        bool $recovering = false,
        $credentialExpiresAt = null,
        bool $authorized = false,
    ) use ($administrator, $fixtureNow): string {
        $id = (string) Str::uuid();
        $row = [
            'id' => $id,
            'protocol_version' => '2.0',
            'central_instance_id' => 'api-sync-admin-smoke-'.$label,
            'central_name' => 'API Sync admin smoke '.$label,
            'central_origin' => 'https://premis.example.test',
            'target_origin' => 'https://attp.example.test',
            'confirmation_url' => 'https://premis.example.test/api/v2/portfolio-sync/invitations/'.$id.'/confirm',
            'requested_datasets' => json_encode(['projects'], JSON_THROW_ON_ERROR),
            'requested_scopes' => json_encode(['records.read'], JSON_THROW_ON_ERROR),
            'credential_digest' => hash('sha256', 'credential-'.$id),
            'signature_key_id' => 'api-sync-smoke-key',
            'invitation_nonce' => (string) Str::uuid(),
            'invitation_payload_hash' => hash('sha256', 'payload-'.$id),
            'status' => $status,
            'issued_at' => $fixtureNow->copy()->subDay(),
            'expires_at' => $expiresAt,
            'credential_expires_at' => $credentialExpiresAt ?? $fixtureNow->copy()->addHours(4),
            'received_at' => $fixtureNow->copy()->addMinutes(10),
            'approval_attempts' => $recovering ? 1 : 0,
            'created_at' => $fixtureNow,
            'updated_at' => $fixtureNow,
        ];
        if ($recovering) {
            $row += [
                'confirmation_request_id' => (string) Str::uuid(),
                'confirmation_request_nonce' => (string) Str::uuid(),
                'approved_by' => $administrator->id,
                'last_approval_attempt_at' => $fixtureNow,
            ];
        }
        if ($authorized) {
            $row += [
                'approved_by' => $administrator->id,
                'approved_at' => $fixtureNow,
                'authorization_id' => (string) Str::uuid(),
                'authorization_receipt' => 'api-sync-smoke-encrypted-receipt-placeholder',
                'authorization_verified_at' => $fixtureNow,
            ];
        }
        DB::table('api_sync_invitations')->insert($row);

        return $id;
    };

    $freshPendingId = $insertInvitation('pending', $fixtureNow->copy()->addMinutes(30), 'fresh');
    $overduePendingId = $insertInvitation('pending', $fixtureNow->copy()->subMinutes(5), 'overdue');
    $recoveringId = $insertInvitation('approval_in_progress', $fixtureNow->copy()->subMinutes(5), 'recovering', true);
    $credentialExpiredRecoveringId = $insertInvitation(
        'approval_in_progress',
        $fixtureNow->copy()->subMinutes(10),
        'credential-expired-recovering',
        true,
        $fixtureNow->copy()->subMinutes(5),
    );
    $credentialExpiredPendingId = $insertInvitation(
        'pending',
        $fixtureNow->copy()->subMinutes(10),
        'credential-expired-pending',
        false,
        $fixtureNow->copy()->subMinutes(5),
    );
    $credentialExpiredActivationId = $insertInvitation(
        'activation_pending',
        $fixtureNow->copy()->subMinutes(10),
        'credential-expired-activation',
        false,
        $fixtureNow->copy()->subMinutes(5),
        true,
    );

    $awaitingRequest = Request::create('/system/api-sync', 'GET', ['status' => 'awaiting']);
    $awaitingRequest->setLaravelSession($session);
    $awaitingRequest->setUserResolver(static fn (): User => $administrator);
    $awaitingRequest->setRouteResolver(static fn () => $route);
    $app->instance('request', $awaitingRequest);
    $awaitingData = $controller->index($awaitingRequest)->getData();
    $awaitingIds = collect($awaitingData['incoming']->items())->pluck('id')->map(fn ($id): string => (string) $id);
    $assert($awaitingIds->contains($freshPendingId), 'A fresh pending request disappeared from awaiting approval.');
    $assert($awaitingIds->contains($recoveringId), 'An expired but resumable approval disappeared from awaiting approval.');
    $assert(! $awaitingIds->contains($overduePendingId), 'A scheduler-gap overdue request remained in awaiting approval.');
    $assert(! $awaitingIds->contains($credentialExpiredRecoveringId), 'A credential-expired recovery remained in awaiting approval.');
    $assert(! $awaitingIds->contains($credentialExpiredPendingId), 'A credential-expired pending request remained in awaiting approval.');

    $app->instance('request', $transferRequest);
    $fixtureTransferData = $controller->index($transferRequest)->getData();
    $fixtureTransferIds = collect($fixtureTransferData['incoming']->items())->pluck('id')->map(fn ($id): string => (string) $id);
    $assert(! $fixtureTransferIds->contains($credentialExpiredActivationId), 'A credential-expired activation remained in the transfer workspace.');

    $closedRequest = Request::create('/system/api-sync', 'GET', ['status' => 'closed']);
    $closedRequest->setLaravelSession($session);
    $closedRequest->setUserResolver(static fn (): User => $administrator);
    $closedRequest->setRouteResolver(static fn () => $route);
    $app->instance('request', $closedRequest);
    $closedWorkspace = $controller->index($closedRequest);
    $closedData = $closedWorkspace->getData();
    $closedIds = collect($closedData['incoming']->items())->pluck('id')->map(fn ($id): string => (string) $id);
    $assert($closedIds->contains($overduePendingId), 'A scheduler-gap overdue request was not classified as closed.');
    $assert($closedIds->contains($credentialExpiredRecoveringId), 'A credential-expired recovery was not classified as closed.');
    $assert($closedIds->contains($credentialExpiredPendingId), 'A credential-expired pending request was not classified as closed.');
    $assert($closedIds->contains($credentialExpiredActivationId), 'A credential-expired activation was not classified as closed.');
    $assert(! $closedIds->contains($freshPendingId), 'A fresh pending request was classified as closed.');
    $assert(! $closedIds->contains($recoveringId), 'A resumable approval was classified as closed.');

    $closedHtml = $closedWorkspace->render();
    $expiredRecoveryLabel = 'API Sync admin smoke credential-expired-recovering';
    $labelPosition = strpos($closedHtml, $expiredRecoveryLabel);
    $assert($labelPosition !== false, 'The credential-expired recovery did not render in the closed workspace.');
    $cardStart = strrpos(substr($closedHtml, 0, $labelPosition), '<article class="sync-request">');
    $cardEnd = strpos($closedHtml, '</article>', $labelPosition);
    $assert($cardStart !== false && $cardEnd !== false, 'The credential-expired recovery card could not be isolated.');
    $expiredRecoveryCard = substr($closedHtml, $cardStart, $cardEnd + strlen('</article>') - $cardStart);
    $assert(str_contains($expiredRecoveryCard, '<span class="sync-status expired">Expired</span>'), 'The UI did not visibly classify the credential-expired recovery as expired.');
    $assert(! str_contains($expiredRecoveryCard, 'Safe retry available'), 'The UI offered safe recovery after credential expiry.');
    $assert(! str_contains($expiredRecoveryCard, 'Retry approval'), 'The UI offered retry approval after credential expiry.');
    $assert(! str_contains($expiredRecoveryCard, 'The previous confirmation was interrupted'), 'The UI described expired credentials as safely recoverable.');
    $assert(! str_contains($expiredRecoveryCard, 'data-bs-target="#approve-'.$credentialExpiredRecoveringId.'"'), 'The UI exposed an approval control after credential expiry.');
    $assert(! str_contains($closedHtml, 'id="approve-'.$credentialExpiredRecoveringId.'"'), 'The UI rendered an approval modal after credential expiry.');

    Http::fake();
    $approvalRequest = Request::create(
        '/system/api-sync/invitations/'.$credentialExpiredRecoveringId.'/approve',
        'POST',
    );
    $approvalRequest->setLaravelSession($session);
    $approvalRequest->setUserResolver(static fn (): User => $administrator);
    $approvalRequest->setRouteResolver(static fn () => $route);
    $app->instance('request', $approvalRequest);
    $approvalFailure = null;
    try {
        $app->make(ApiSyncInvitationService::class)->approve(
            ApiSyncInvitation::query()->findOrFail($credentialExpiredRecoveringId),
            $administrator,
            '1234567',
            $approvalRequest,
        );
    } catch (ApiSyncException $exception) {
        $approvalFailure = $exception;
    }
    $assert($approvalFailure instanceof ApiSyncException, 'Credential-expired approval did not fail closed.');
    $assert($approvalFailure->errorCode === 'approval_credential_expired', 'Credential-expired approval returned the wrong error code.');
    $assert($approvalFailure->httpStatus === 410, 'Credential-expired approval returned the wrong HTTP status.');
    $assert(ApiSyncInvitation::query()->findOrFail($credentialExpiredRecoveringId)->status === 'expired', 'Credential-expired approval did not persist its terminal state.');
    Http::assertNothingSent();

    $maintenance = $app->make(ApiSyncInvitationService::class)->expireAndPrune(2_000);
    $assert($maintenance['expired_invitations'] >= 1, 'Invitation maintenance did not classify any credential expiry.');
    $assert(ApiSyncInvitation::query()->findOrFail($credentialExpiredPendingId)->status === 'expired', 'Invitation maintenance did not expire a pending request whose bounded credential elapsed.');

    $enabledBeforeReadinessCheck = config('api_sync.enabled');
    try {
        config()->set('api_sync.enabled', false);
        $app->instance('request', $awaitingRequest);
        $notReadyWorkspace = $controller->index($awaitingRequest);
        $notReadyData = $notReadyWorkspace->getData();
        $notReadyHtml = $notReadyWorkspace->render();
        $assert($notReadyData['isReady'] === false, 'An incomplete API Sync configuration was reported as ready.');
        $assert(str_contains($notReadyHtml, 'API Sync admin smoke fresh'), 'The readiness smoke did not render its pending request.');
        $assert(str_contains($notReadyHtml, 'New synchronization approvals are paused'), 'The workspace did not explain its readiness approval gate.');
        $assert(! str_contains($notReadyHtml, 'data-bs-target="#approve-'.$freshPendingId.'"'), 'The workspace exposed approval while security readiness was incomplete.');
    } finally {
        config()->set('api_sync.enabled', $enabledBeforeReadinessCheck);
    }
} finally {
    DB::rollBack();
}

$app->instance('request', $request);

$html = $workspace->render();
$assert(str_contains($html, 'API Sync'), 'The authenticated API Sync workspace did not render.');
$assert(str_contains($html, 'AU-PReMIS'), 'The rendered workspace omitted its trusted central-system context.');
$assert(str_contains($html, 'status=attention'), 'The rendered workspace omitted request filtering.');
$assert(str_contains($html, 'aria-current="page"'), 'The active API Sync filter is not identified accessibly.');

$sensitiveSamples = collect()
    ->merge(DB::table('api_sync_pairings')->pluck('code_hash'))
    ->merge(DB::table('api_sync_pairings')->whereNotNull('token_hash')->pluck('token_hash'))
    ->merge(DB::table('api_sync_invitations')->pluck('credential_digest'))
    ->filter(fn ($value): bool => is_string($value) && $value !== '');

foreach ($sensitiveSamples as $secret) {
    $assert(! str_contains($html, $secret), 'The rendered API Sync workspace exposed a credential digest.');
}

$session->forget(['errors', '_old_input', 'legacy_panel_open', 'legacy_action', 'legacy_pairing_id']);
$generateSecret = 'legacy-generate-smoke-'.Str::uuid();
$legacyGenerateRequest = Request::create(
    '/system/api-sync/pairings',
    'POST',
    ['current_password' => $generateSecret],
    [],
    [],
    ['HTTP_REFERER' => $indexUrl],
);
$legacyGenerateRequest->setLaravelSession($session);
$legacyGenerateRequest->setUserResolver(static fn (): User => $administrator);
$legacyGenerateRequest->setRouteResolver(static fn () => $route);
$app->instance('request', $legacyGenerateRequest);
$generateFailure = $controller->generate($legacyGenerateRequest);
$generateErrors = $session->get('errors');
$assert($generateFailure->isRedirect(), 'A rejected legacy generation did not redirect safely.');
$assert(! $legacyGenerateRequest->request->has('current_password'), 'Legacy generation retained the submitted password in the request.');
$assert($generateErrors instanceof ViewErrorBag && $generateErrors->has('current_password'), 'Legacy generation did not return its scoped password error.');
$assert($session->get('legacy_panel_open') === true, 'Legacy generation did not reopen its panel after validation failed.');
$assert($session->get('legacy_action') === 'generate', 'Legacy generation did not identify its validation context.');
$assert($session->getOldInput('current_password') === null, 'Legacy generation flashed its password as old input.');
$assert(! str_contains(serialize($session->all()), $generateSecret), 'Legacy generation flashed the submitted password.');

$session->forget(['errors', '_old_input', 'legacy_panel_open', 'legacy_action', 'legacy_pairing_id']);
$revokeSecret = 'legacy-revoke-smoke-'.Str::uuid();
$legacyPairing = ApiSyncPairing::query()->first() ?? (new ApiSyncPairing)->forceFill(['id' => (string) Str::uuid()]);
$legacyRevokeRequest = Request::create(
    '/system/api-sync/pairings/'.$legacyPairing->id.'/revoke',
    'POST',
    ['current_password' => $revokeSecret],
    [],
    [],
    ['HTTP_REFERER' => $indexUrl],
);
$legacyRevokeRequest->setLaravelSession($session);
$legacyRevokeRequest->setUserResolver(static fn (): User => $administrator);
$legacyRevokeRequest->setRouteResolver(static fn () => $route);
$app->instance('request', $legacyRevokeRequest);
$revokeFailure = $controller->revoke($legacyRevokeRequest, $legacyPairing);
$revokeErrors = $session->get('errors');
$assert($revokeFailure->isRedirect(), 'A rejected legacy revocation did not redirect safely.');
$assert(! $legacyRevokeRequest->request->has('current_password'), 'Legacy revocation retained the submitted password in the request.');
$assert($revokeErrors instanceof ViewErrorBag && $revokeErrors->has('current_password'), 'Legacy revocation did not return its scoped password error.');
$assert($session->get('legacy_panel_open') === true, 'Legacy revocation did not reopen its panel after validation failed.');
$assert($session->get('legacy_action') === 'revoke', 'Legacy revocation did not identify its validation context.');
$assert((string) $session->get('legacy_pairing_id') === (string) $legacyPairing->id, 'Legacy revocation did not identify the affected pairing safely.');
$assert($session->getOldInput('current_password') === null, 'Legacy revocation flashed its password as old input.');
$assert(! str_contains(serialize($session->all()), $revokeSecret), 'Legacy revocation flashed the submitted password.');
$session->forget(['errors', '_old_input', 'legacy_panel_open', 'legacy_action', 'legacy_pairing_id']);

echo "Authenticated API Sync administration workspace smoke passed.\n";
