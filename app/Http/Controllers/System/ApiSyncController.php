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
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ApiSyncController extends Controller
{
    public function __construct(
        private readonly ApiSyncPairingService $pairings,
        private readonly ApiSyncInvitationService $invitations,
        private readonly ApiSyncInvitationAuditService $invitationAudit,
    ) {}

    public function index(Request $request): View
    {
        return view('system.api-sync.index', $this->viewData($request));
    }

    public function generate(Request $request): Response
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ], [
            'current_password.current_password' => 'The administrator password is incorrect.',
        ]);
        $generated = $this->pairings->generate($request->user(), $request);
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
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ], [
            'current_password.current_password' => 'The administrator password is incorrect.',
        ]);
        $this->pairings->revoke($pairing, $request->user(), $request);

        return back()->with('success', 'The synchronization session was revoked and its credential can no longer be used.');
    }

    public function approveInvitation(Request $request, ApiSyncInvitation $invitation): RedirectResponse
    {
        $code = trim((string) $request->input('authorization_code'));
        $currentPassword = (string) $request->input('current_password');
        if (! preg_match('/^\d{7}$/', $code)) {
            $this->invitationAudit->record($invitation, 'local_approval_rejected', 'A local approval attempt was rejected because the authorization code format was invalid.', actor: $request->user(), request: $request, statusCode: 422);

            return back()->withErrors(['authorization_code' => 'Enter the exact seven-digit code shown in AU-PReMIS.']);
        }
        if ($currentPassword === '' || ! Hash::check($currentPassword, (string) $request->user()->getAuthPassword())) {
            $this->invitationAudit->record($invitation, 'local_approval_rejected', 'A local approval attempt was rejected because administrator password confirmation failed.', actor: $request->user(), request: $request, statusCode: 422);

            return back()->withErrors(['current_password' => 'Your current administrator password is incorrect.']);
        }

        try {
            $approved = $this->invitations->approve($invitation, $request->user(), $code, $request);
        } catch (ApiSyncException $exception) {
            return back()->withErrors(['authorization_code' => $exception->getMessage()]);
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

            return back()->withErrors(['current_password' => 'Your current administrator password is incorrect.']);
        }
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
            return back()->withErrors(['reason' => 'Give a clear reason between 10 and 500 characters.']);
        }

        try {
            $this->invitations->decline($invitation, $request->user(), $reason, $request);
        } catch (ApiSyncException $exception) {
            return back()->withErrors(['reason' => $exception->getMessage()]);
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

            return back()->withErrors(['current_password' => 'Your current administrator password is incorrect.']);
        }
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
            return back()->withErrors(['reason' => 'Give a clear reason between 10 and 500 characters.']);
        }

        try {
            $this->invitations->revoke($invitation, $request->user(), $reason, $request);
        } catch (ApiSyncException $exception) {
            return back()->withErrors(['reason' => $exception->getMessage()]);
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
        $history = ApiSyncPairing::query()
            ->with(['creator:id,name', 'revoker:id,name'])
            ->latest()
            ->paginate(15);
        $incoming = ApiSyncInvitation::query()
            ->with([
                'approver:id,name',
                'pairing:id,inbound_invitation_id,snapshot_id,snapshot_status,snapshot_record_count,snapshot_bytes,snapshot_materialized_at,token_expires_at,request_count,document_snapshot_status,document_snapshot_started_at,document_snapshot_materialized_at,document_discovered_count,document_ready_count,document_held_count,document_snapshot_bytes,document_snapshot_failure_reason',
            ])
            ->latest('received_at')
            ->paginate(15, ['*'], 'invitations_page');
        $events = $request->user()?->can('api_sync.audit.view')
            ? ApiSyncEvent::query()->with('user:id,name')->latest('created_at')->limit(30)->get()
            : collect();
        $invitationEvents = $request->user()?->can('api_sync.audit.view')
            ? ApiSyncInvitationEvent::query()->with('user:id,name')->latest('created_at')->limit(30)->get()
            : collect();

        return [
            'history' => $history,
            'incoming' => $incoming,
            'events' => $events,
            'invitationEvents' => $invitationEvents,
            'generatedCode' => $generatedCode,
            'generatedExpiresAt' => $generatedExpiresAt,
            'successMessage' => $successMessage,
            'enabled' => (bool) config('api_sync.enabled'),
            'legacyV1Enabled' => (bool) config('api_sync.legacy_v1_enabled', false),
            'pairingTtlMinutes' => (int) config('api_sync.pairing_ttl_minutes', 10),
            'sessionTtlMinutes' => (int) config('api_sync.session_ttl_minutes', 360),
        ];
    }
}
