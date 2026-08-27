<?php

namespace App\Http\Middleware;

use App\Models\SystemAuditLog;
use App\Models\User;
use App\Support\UserImpersonation;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ValidateUserImpersonation
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! $request->session()->has(UserImpersonation::SESSION_KEY)) {
            return $next($request);
        }

        $state = UserImpersonation::state($request);

        if ($state === null) {
            return $this->terminateWithoutRestoring($request, null, null, 'invalid_session_state');
        }

        $administrator = User::with('role')->find($state['administrator_id']);

        if (! $this->isRestorableAdministrator($administrator)) {
            return $this->terminateWithoutRestoring(
                $request,
                $administrator,
                $state,
                'administrator_no_longer_authorized'
            );
        }

        $user = $request->user();

        // A deleted target can no longer be resolved by Laravel's session guard.
        // The server-side recovery context can still return the valid administrator.
        if (! $user) {
            return $this->restoreAdministrator($request, $administrator, $state, 'impersonated_user_missing');
        }

        if ((string) $user->id !== (string) $state['user_id']) {
            return $this->terminateWithoutRestoring($request, $administrator, $state, 'effective_user_mismatch');
        }

        if ($this->hasExpired($state['started_at'] ?? null)) {
            return $this->restoreAdministrator($request, $administrator, $state, 'session_expired');
        }

        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return $this->restoreAdministrator($request, $administrator, $state, 'target_became_administrator');
        }

        if ($user->is_disabled || $user->is_blacklisted) {
            return $this->restoreAdministrator($request, $administrator, $state, 'target_access_revoked');
        }

        return $next($request);
    }

    private function isRestorableAdministrator(?User $administrator): bool
    {
        return $administrator !== null
            && ($administrator->isAdmin() || $administrator->isSuperAdmin())
            && ! $administrator->hasActiveLoginBlock()
            && ! $administrator->is_blacklisted;
    }

    private function hasExpired(mixed $startedAt): bool
    {
        if (! is_string($startedAt) || trim($startedAt) === '') {
            return true;
        }

        try {
            $started = Carbon::parse($startedAt);
        } catch (Throwable) {
            return true;
        }

        $ttlMinutes = max(1, (int) config('security.impersonation_ttl_minutes', 240));

        return $started->isFuture() || $started->lte(now()->subMinutes($ttlMinutes));
    }

    private function restoreAdministrator(
        Request $request,
        User $administrator,
        array $state,
        string $reason
    ): Response {
        $this->auditTermination($request, $administrator, $state, $reason);

        UserImpersonation::markAuthSwitch($request);
        Auth::guard('web')->login($administrator);
        $request->session()->forget(UserImpersonation::SESSION_KEY);
        UserImpersonation::clearIdentityVerificationState($request);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('system.users.index')
            ->with('error', $this->restorationMessage($reason));
    }

    private function terminateWithoutRestoring(
        Request $request,
        ?User $administrator,
        ?array $state,
        string $reason
    ): Response {
        Log::warning('An invalid user impersonation session was terminated.', [
            'reason' => $reason,
            'administrator_id' => $state['administrator_id'] ?? $administrator?->id,
            'impersonated_user_id' => $state['user_id'] ?? null,
        ]);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->withErrors(['email' => 'The impersonation session is no longer valid. Please sign in again.']);
    }

    private function auditTermination(
        Request $request,
        User $administrator,
        array $state,
        string $reason
    ): void {
        try {
            SystemAuditLog::create([
                'user_id' => $administrator->id,
                'module' => 'user_management',
                'action' => 'user_impersonation_terminated',
                'action_message' => 'User impersonation ended automatically: '.str_replace('_', ' ', $reason).'.',
                'description' => 'The system automatically ended an unsafe or expired user impersonation session.',
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'route_name' => $request->route()?->getName(),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000),
                'status_code' => 302,
                'payload' => [
                    'administrator_id' => (string) $administrator->id,
                    'impersonated_user_id' => (string) $state['user_id'],
                    'termination_reason' => $reason,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::warning('Automatic impersonation termination could not be audited.', [
                'reason' => $reason,
                'administrator_id' => $administrator->id,
                'impersonated_user_id' => $state['user_id'],
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function restorationMessage(string $reason): string
    {
        return match ($reason) {
            'session_expired' => 'The Login as session reached its time limit. You have been returned to your administrator account.',
            'target_access_revoked' => 'The user account is no longer active. You have been returned to your administrator account.',
            'target_became_administrator' => 'The user became an administrator, so Login as was ended automatically.',
            'impersonated_user_missing' => 'The user account no longer exists. You have been returned to your administrator account.',
            default => 'The Login as session ended safely. You have been returned to your administrator account.',
        };
    }
}
