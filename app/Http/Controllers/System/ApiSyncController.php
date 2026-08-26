<?php

namespace App\Http\Controllers\System;

use App\Exceptions\ApiSyncException;
use App\Http\Controllers\Controller;
use App\Models\ApiSyncEvent;
use App\Models\ApiSyncInvitation;
use App\Models\ApiSyncInvitationEvent;
use App\Models\ApiSyncPairing;
use App\Services\ApiSync\ApiSyncInvitationAuditService;
use App\Services\ApiSync\ApiSyncInvitationService;
use App\Services\ApiSync\ApiSyncPairingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ApiSyncController extends Controller
{
    private const STATUS_FILTERS = [
        'all' => [],
        'awaiting' => [
            ApiSyncInvitation::STATUS_PENDING,
            ApiSyncInvitation::STATUS_APPROVAL_IN_PROGRESS,
        ],
        'transfer' => [
            ApiSyncInvitation::STATUS_ACTIVATION_PENDING,
            ApiSyncInvitation::STATUS_ACTIVATION_RECEIVED,
            ApiSyncInvitation::STATUS_ACTIVE,
        ],
        'completed' => [ApiSyncInvitation::STATUS_COMPLETED],
        'attention' => [ApiSyncInvitation::STATUS_FAILED],
        'closed' => [
            ApiSyncInvitation::STATUS_DECLINED,
            ApiSyncInvitation::STATUS_EXPIRED,
            ApiSyncInvitation::STATUS_REVOKED,
        ],
    ];

    public function __construct(
        private readonly ApiSyncPairingService $pairings,
        private readonly ApiSyncInvitationService $invitations,
        private readonly ApiSyncInvitationAuditService $invitationAudit,
    ) {}

    public function index(Request $request): View
    {
        return view('system.api-sync.index', $this->viewData($request));
    }

    public function generate(Request $request): Response|RedirectResponse
    {
        $currentPassword = (string) $request->input('current_password');
        if ($currentPassword === '' || ! Hash::check($currentPassword, (string) $request->user()->getAuthPassword())) {
            $request->request->remove('current_password');

            return back()
                ->withErrors(['current_password' => 'Your current administrator password is incorrect.'])
                ->with('legacy_panel_open', true)
                ->with('legacy_action', 'generate');
        }

        try {
            $generated = $this->pairings->generate($request->user(), $request);
        } finally {
            $request->request->remove('current_password');
            $currentPassword = '';
        }
        $data = $this->viewData(
            $request,
            $generated['code'],
            $generated['pairing']->code_expires_at?->toIso8601String(),
            'A new single-use pairing code is ready. Share it through the approved secure channel.',
        );

        return response(view('system.api-sync.index', $data)->render(), 201)
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }

    public function revoke(Request $request, ApiSyncPairing $pairing): RedirectResponse
    {
        $currentPassword = (string) $request->input('current_password');
        if ($currentPassword === '' || ! Hash::check($currentPassword, (string) $request->user()->getAuthPassword())) {
            $request->request->remove('current_password');

            return back()
                ->withErrors(['current_password' => 'Your current administrator password is incorrect.'])
                ->with('legacy_panel_open', true)
                ->with('legacy_action', 'revoke')
                ->with('legacy_pairing_id', (string) $pairing->id);
        }

        try {
            $this->pairings->revoke($pairing, $request->user(), $request);
        } finally {
            $request->request->remove('current_password');
            $currentPassword = '';
        }

        return redirect()->route('system.api-sync.index')
            ->with('success', 'The synchronization session was revoked and its credential can no longer be used.');
    }

    public function approveInvitation(Request $request, ApiSyncInvitation $invitation): RedirectResponse
    {
        $code = trim((string) $request->input('authorization_code'));
        $currentPassword = (string) $request->input('current_password');
        if (! preg_match('/^\d{7}$/', $code)) {
            $this->invitationAudit->record($invitation, 'local_approval_rejected', 'A local approval attempt was rejected because the authorization code format was invalid.', actor: $request->user(), request: $request, statusCode: 422);
            $request->request->remove('authorization_code');
            $request->request->remove('current_password');

            return $this->actionError($invitation, 'approve', [
                'authorization_code' => 'Enter the exact seven-digit code shown in AU-PReMIS.',
            ]);
        }
        if ($currentPassword === '' || ! Hash::check($currentPassword, (string) $request->user()->getAuthPassword())) {
            $this->invitationAudit->record($invitation, 'local_approval_rejected', 'A local approval attempt was rejected because administrator password confirmation failed.', actor: $request->user(), request: $request, statusCode: 422);
            $request->request->remove('authorization_code');
            $request->request->remove('current_password');

            return $this->actionError($invitation, 'approve', [
                'current_password' => 'Your current administrator password is incorrect.',
            ]);
        }

        try {
            $approved = $this->invitations->approve($invitation, $request->user(), $code, $request);
        } catch (ApiSyncException $exception) {
            return $this->actionError($invitation, 'approve', [
                'authorization_code' => $exception->getMessage(),
            ]);
        } finally {
            // Do not carry either sensitive value into a later middleware,
            // redirect session, exception context, or accidental debug dump.
            $request->request->remove('authorization_code');
            $request->request->remove('current_password');
            $code = '';
            $currentPassword = '';
        }

        return redirect()->route('system.api-sync.index')
            ->with('success', "The request from {$approved->central_name} was approved. Snapshot preparation and transfer will continue in the background.");
    }

    public function declineInvitation(Request $request, ApiSyncInvitation $invitation): RedirectResponse
    {
        $currentPassword = (string) $request->input('current_password');
        $reason = trim((string) $request->input('reason'));
        if ($currentPassword === '' || ! Hash::check($currentPassword, (string) $request->user()->getAuthPassword())) {
            $this->invitationAudit->record($invitation, 'local_decline_rejected', 'A request-decline attempt was rejected because administrator password confirmation failed.', actor: $request->user(), request: $request, statusCode: 422);
            $request->request->remove('current_password');

            return $this->actionError($invitation, 'decline', [
                'current_password' => 'Your current administrator password is incorrect.',
            ], ['reason' => $reason]);
        }
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
            $request->request->remove('current_password');

            return $this->actionError($invitation, 'decline', [
                'reason' => 'Give a clear reason between 10 and 500 characters.',
            ], ['reason' => $reason]);
        }

        try {
            $this->invitations->decline($invitation, $request->user(), $reason, $request);
        } catch (ApiSyncException $exception) {
            return $this->actionError($invitation, 'decline', [
                'reason' => $exception->getMessage(),
            ], ['reason' => $reason]);
        } finally {
            $request->request->remove('current_password');
        }

        return redirect()->route('system.api-sync.index')->with('success', 'The AU-PReMIS synchronization request was declined and no data was released.');
    }

    public function revokeInvitation(Request $request, ApiSyncInvitation $invitation): RedirectResponse
    {
        $currentPassword = (string) $request->input('current_password');
        $reason = trim((string) $request->input('reason'));
        if ($currentPassword === '' || ! Hash::check($currentPassword, (string) $request->user()->getAuthPassword())) {
            $this->invitationAudit->record($invitation, 'local_revocation_rejected', 'A transfer-revocation attempt was rejected because administrator password confirmation failed.', actor: $request->user(), request: $request, statusCode: 422);
            $request->request->remove('current_password');

            return $this->actionError($invitation, 'revoke', [
                'current_password' => 'Your current administrator password is incorrect.',
            ], ['reason' => $reason]);
        }
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
            $request->request->remove('current_password');

            return $this->actionError($invitation, 'revoke', [
                'reason' => 'Give a clear reason between 10 and 500 characters.',
            ], ['reason' => $reason]);
        }

        try {
            $this->invitations->revoke($invitation, $request->user(), $reason, $request);
        } catch (ApiSyncException $exception) {
            return $this->actionError($invitation, 'revoke', [
                'reason' => $exception->getMessage(),
            ], ['reason' => $reason]);
        } finally {
            $request->request->remove('current_password');
        }

        return redirect()->route('system.api-sync.index')->with('success', 'The AU-PReMIS synchronization credential was revoked and any stored snapshot will be removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(
        Request $request,
        ?string $generatedCode = null,
        ?string $generatedExpiresAt = null,
        ?string $successMessage = null,
    ): array {
        $statusFilter = strtolower(trim((string) $request->query('status', 'all')));
        if (! array_key_exists($statusFilter, self::STATUS_FILTERS)) {
            $statusFilter = 'all';
        }

        $now = now();
        $nominallyExpired = static function ($query) use ($now): void {
            $query->where(function ($query) use ($now): void {
                $query->where(function ($query) use ($now): void {
                    $query->where('status', ApiSyncInvitation::STATUS_PENDING)
                        ->where(function ($query) use ($now): void {
                            $query->where('expires_at', '<=', $now)
                                ->orWhere('credential_expires_at', '<=', $now);
                        });
                })->orWhere(function ($query) use ($now): void {
                    $query->where('status', ApiSyncInvitation::STATUS_APPROVAL_IN_PROGRESS)
                        ->where(function ($query) use ($now): void {
                            $query->where('credential_expires_at', '<=', $now)
                                ->orWhere(function ($query) use ($now): void {
                                    $query->where('expires_at', '<=', $now)
                                        ->where(function ($query): void {
                                            $query->whereNull('confirmation_request_id')
                                                ->orWhereNull('confirmation_request_nonce');
                                        });
                                });
                        });
                })->orWhere(function ($query) use ($now): void {
                    $query->where('status', ApiSyncInvitation::STATUS_ACTIVATION_PENDING)
                        ->where('credential_expires_at', '<=', $now);
                });
            });
        };
        $availableForApproval = static function ($query) use ($now): void {
            $query->where('credential_expires_at', '>', $now)
                ->where(function ($query) use ($now): void {
                    $query->where('expires_at', '>', $now)
                        ->orWhere(function ($query): void {
                            $query->where('status', ApiSyncInvitation::STATUS_APPROVAL_IN_PROGRESS)
                                ->whereNotNull('confirmation_request_id')
                                ->whereNotNull('confirmation_request_nonce');
                        });
                });
        };

        $statusCounts = DB::table('api_sync_invitations')
            ->select('status', DB::raw('COUNT(*) AS aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count): int => (int) $count);
        $countStatuses = fn (array $statuses): int => (int) collect($statuses)
            ->sum(fn (string $status): int => (int) $statusCounts->get($status, 0));
        $operationalFailure = static fn ($query) => $query
            ->where('snapshot_status', 'failed')
            ->orWhere('document_snapshot_status', 'failed');
        $availableForTransfer = static fn ($query) => $query
            ->where('status', '!=', ApiSyncInvitation::STATUS_ACTIVATION_PENDING)
            ->orWhere('credential_expires_at', '>', $now);
        $attentionCount = ApiSyncInvitation::query()
            ->where(fn ($query) => $query
                ->where('status', ApiSyncInvitation::STATUS_FAILED)
                ->orWhereHas('pairing', $operationalFailure))
            ->count();
        $transferCount = ApiSyncInvitation::query()
            ->whereIn('status', self::STATUS_FILTERS['transfer'])
            ->where($availableForTransfer)
            ->whereDoesntHave('pairing', $operationalFailure)
            ->count();
        $awaitingCount = ApiSyncInvitation::query()
            ->whereIn('status', self::STATUS_FILTERS['awaiting'])
            ->where($availableForApproval)
            ->count();
        $closedCount = $countStatuses(self::STATUS_FILTERS['closed'])
            + ApiSyncInvitation::query()->where($nominallyExpired)->count();
        $summary = [
            'total' => (int) $statusCounts->sum(),
            'awaiting' => $awaitingCount,
            'transfer' => $transferCount,
            'completed' => $countStatuses(self::STATUS_FILTERS['completed']),
            'attention' => $attentionCount,
            'closed' => $closedCount,
        ];

        $history = ApiSyncPairing::query()
            ->with(['creator:id,name', 'revoker:id,name'])
            ->latest()
            ->paginate(15)
            ->withPath(route('system.api-sync.index'))
            ->withQueryString();
        $incoming = ApiSyncInvitation::query()
            ->with([
                'approver:id,name',
                'pairing:id,inbound_invitation_id,snapshot_id,snapshot_status,snapshot_started_at,snapshot_materialized_at,snapshot_failed_at,snapshot_failure_reason,snapshot_record_count,snapshot_bytes,token_expires_at,request_count,document_snapshot_status,document_snapshot_started_at,document_snapshot_materialized_at,document_discovered_count,document_ready_count,document_held_count,document_snapshot_bytes,document_snapshot_failure_reason',
            ])
            ->when($statusFilter === 'attention', fn ($query) => $query
                ->where(fn ($query) => $query
                    ->where('status', ApiSyncInvitation::STATUS_FAILED)
                    ->orWhereHas('pairing', $operationalFailure)))
            ->when($statusFilter === 'transfer', fn ($query) => $query
                ->whereIn('status', self::STATUS_FILTERS['transfer'])
                ->where($availableForTransfer)
                ->whereDoesntHave('pairing', $operationalFailure))
            ->when($statusFilter === 'awaiting', fn ($query) => $query
                ->whereIn('status', self::STATUS_FILTERS['awaiting'])
                ->where($availableForApproval))
            ->when($statusFilter === 'closed', fn ($query) => $query
                ->where(fn ($query) => $query
                    ->whereIn('status', self::STATUS_FILTERS['closed'])
                    ->orWhere($nominallyExpired)))
            ->when(
                ! in_array($statusFilter, ['all', 'awaiting', 'attention', 'transfer', 'closed'], true),
                fn ($query) => $query->whereIn('status', self::STATUS_FILTERS[$statusFilter])
            )
            ->latest('received_at')
            ->paginate(12, ['*'], 'invitations_page')
            ->withPath(route('system.api-sync.index'))
            ->withQueryString();
        $events = $request->user()?->can('api_sync.audit.view')
            ? ApiSyncEvent::query()->with('user:id,name')->latest('created_at')->limit(30)->get()
            : collect();
        $invitationEvents = $request->user()?->can('api_sync.audit.view')
            ? ApiSyncInvitationEvent::query()->with('user:id,name')->latest('created_at')->limit(30)->get()
            : collect();
        $activity = $invitationEvents
            ->map(fn (ApiSyncInvitationEvent $event): array => [
                'occurred_at' => $event->created_at,
                'message' => $event->message,
                'event_type' => $event->event_type,
                'actor' => $event->user?->name ?: 'Trusted system',
                'source' => 'Invitation',
            ])
            ->concat($events->map(fn (ApiSyncEvent $event): array => [
                'occurred_at' => $event->created_at,
                'message' => $event->message,
                'event_type' => $event->event_type,
                'actor' => $event->user?->name ?: 'Trusted system',
                'source' => 'Transfer',
            ]))
            ->sortByDesc(fn (array $item): int => $item['occurred_at']?->getTimestamp() ?? 0)
            ->take(30)
            ->values();

        $enabled = (bool) config('api_sync.enabled');
        $v2Enabled = (bool) config('api_sync.v2.enabled');
        $documentsEnabled = (bool) config('api_sync.v2.documents.enabled', false);
        $queueConnection = (string) config('api_sync.snapshot.connection', 'api_sync_database');
        $queueReady = true;
        $queueReadinessDetail = "Connection {$queueConnection}; queue ".config('api_sync.snapshot.queue', 'api-sync').'.';
        try {
            $this->pairings->ensureAsyncQueue($documentsEnabled);
        } catch (ApiSyncException $exception) {
            $queueReady = false;
            $queueReadinessDetail = $exception->getMessage();
        }
        $trustedCentralConfigured = filled(config('api_sync.v2.central.instance_id'))
            && filled(config('api_sync.v2.central.origin'))
            && filled(config('api_sync.v2.central.key_id'))
            && (filled(config('api_sync.v2.central.public_key_path')) || filled(config('api_sync.v2.central.public_key_pem')))
            && filled(config('api_sync.v2.central.public_key_sha256'));
        $readinessChecks = collect([
            [
                'label' => 'API Sync service',
                'ready' => $enabled,
                'detail' => $enabled ? 'Provider endpoints are enabled.' : 'Enable ATTP_API_SYNC_ENABLED.',
            ],
            [
                'label' => 'Signed invitations',
                'ready' => $v2Enabled,
                'detail' => $v2Enabled ? 'Protocol v2 invitation validation is enabled.' : 'Enable ATTP_API_SYNC_V2_ENABLED.',
            ],
            [
                'label' => 'Provider identity',
                'ready' => filled(config('api_sync.provider.instance_id')) && filled(config('api_sync.v2.public_origin')),
                'detail' => filled(config('api_sync.provider.instance_id')) && filled(config('api_sync.v2.public_origin'))
                    ? 'This ATTP instance and public origin are bound to every request.'
                    : 'Configure the provider instance ID and exact public origin.',
            ],
            [
                'label' => 'Trusted AU-PReMIS',
                'ready' => $trustedCentralConfigured,
                'detail' => $trustedCentralConfigured ? 'Origin, key ID and pinned public key are configured.' : 'Complete the trusted central origin and signing-key settings.',
            ],
            [
                'label' => 'Durable snapshot queue',
                'ready' => $queueReady,
                'detail' => $queueReadinessDetail,
            ],
        ]);

        return [
            'history' => $history,
            'incoming' => $incoming,
            'events' => $events,
            'invitationEvents' => $invitationEvents,
            'activity' => $activity,
            'summary' => $summary,
            'statusFilter' => $statusFilter,
            'statusFilters' => [
                'all' => 'All requests',
                'awaiting' => 'Awaiting approval',
                'transfer' => 'In progress',
                'completed' => 'Completed',
                'attention' => 'Needs attention',
                'closed' => 'Closed',
            ],
            'readinessChecks' => $readinessChecks,
            'isReady' => $readinessChecks->every(fn (array $check): bool => $check['ready']),
            'generatedCode' => $generatedCode,
            'generatedExpiresAt' => $generatedExpiresAt,
            'successMessage' => $successMessage,
            'enabled' => $enabled,
            'v2Enabled' => $v2Enabled,
            'documentsEnabled' => $documentsEnabled,
            'legacyV1Enabled' => (bool) config('api_sync.legacy_v1_enabled', false),
            'pairingTtlMinutes' => (int) config('api_sync.pairing_ttl_minutes', 10),
            'sessionTtlMinutes' => (int) config('api_sync.session_ttl_minutes', 360),
            'providerName' => (string) config('api_sync.provider.name', config('app.name')),
            'providerCode' => (string) config('api_sync.provider.code', 'ATTP'),
            'publicOrigin' => (string) config('api_sync.v2.public_origin', config('app.url')),
            'trustedCentralOrigin' => (string) config('api_sync.v2.central.origin', ''),
            'snapshotQueue' => (string) config('api_sync.snapshot.queue', 'api-sync'),
            'refreshedAt' => now(),
        ];
    }

    /** @param array<string, string> $errors @param array<string, string> $safeInput */
    private function actionError(
        ApiSyncInvitation $invitation,
        string $action,
        array $errors,
        array $safeInput = [],
    ): RedirectResponse {
        return back()
            ->withErrors($errors)
            ->with('action_modal', $action.'-'.$invitation->id)
            ->with('action_safe_input', $safeInput);
    }
}
