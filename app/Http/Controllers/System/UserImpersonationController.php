<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\SystemAuditLog;
use App\Models\User;
use App\Support\UserImpersonation;
use App\Services\ThinkTank\ThinkTankUserManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class UserImpersonationController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        $administrator = $request->user();

        abort_unless(
            $administrator && ($administrator->isAdmin() || $administrator->isSuperAdmin()),
            403,
            'Only administrators can log in as another user.'
        );

        if (UserImpersonation::state($request) !== null) {
            return back()->with('error', 'Return to your administrator account before logging in as another user.');
        }

        if ((string) $administrator->id === (string) $user->id) {
            return back()->with('error', 'You cannot log in as your own account.');
        }

        app(ThinkTankUserManagementService::class)
            ->assertNotManagedPortalIdentity($user);

        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return back()->with('error', 'Administrator accounts cannot be impersonated.');
        }

        if ($user->is_blacklisted) {
            return back()->with('error', 'This user cannot be impersonated while their account is blacklisted.');
        }

        if ($user->is_disabled && $user->disabled_until?->isPast()) {
            $user->update([
                'is_disabled' => false,
                'disabled_at' => null,
                'disabled_until' => null,
                'disabled_reason' => null,
            ]);
            $user->refresh();
        }

        if ($user->hasActiveLoginBlock()) {
            return back()->with('error', 'This user cannot be impersonated while their login is blocked.');
        }

        if ($readinessError = $this->portalReadinessError($user)) {
            return back()->with('error', $readinessError);
        }

        $state = [
            'administrator_id' => (string) $administrator->id,
            'user_id' => (string) $user->id,
            'started_at' => now()->toIso8601String(),
        ];

        $audited = $this->audit(
            $request,
            $administrator,
            $user,
            'user_impersonation_started',
            "Administrator {$administrator->name} started acting as {$user->name}."
        );

        if (! $audited) {
            return back()->with('error', 'Login as could not start because its security audit was unavailable. Please try again.');
        }

        UserImpersonation::markAuthSwitch($request);
        Auth::guard('web')->login($user);
        $request->session()->put(UserImpersonation::SESSION_KEY, $state);
        UserImpersonation::clearIdentityVerificationState($request);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()
            ->to($this->landingPageFor($user))
            ->with('success', "You are now acting as {$user->name}.");
    }

    public function destroy(Request $request): RedirectResponse
    {
        $state = UserImpersonation::state($request);

        abort_unless(
            $state !== null && UserImpersonation::isActive($request),
            403,
            'There is no active user impersonation session.'
        );

        $impersonatedUser = $request->user();
        $administrator = User::with('role')->find($state['administrator_id']);

        if (! $administrator
            || (! $administrator->isAdmin() && ! $administrator->isSuperAdmin())
            || $administrator->hasActiveLoginBlock()
            || $administrator->is_blacklisted) {
            Log::warning('An impersonation session could not restore its administrator.', [
                'administrator_id' => $state['administrator_id'],
                'impersonated_user_id' => $state['user_id'],
            ]);

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'The administrator account can no longer be restored. Please sign in again.']);
        }

        $this->audit(
            $request,
            $administrator,
            $impersonatedUser,
            'user_impersonation_stopped',
            "Administrator {$administrator->name} stopped acting as {$impersonatedUser->name}."
        );

        UserImpersonation::markAuthSwitch($request);
        Auth::guard('web')->login($administrator);
        $request->session()->forget(UserImpersonation::SESSION_KEY);
        UserImpersonation::clearIdentityVerificationState($request);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('system.users.index')
            ->with('success', 'You have returned to your administrator account.');
    }

    private function landingPageFor(User $user): string
    {
        if ($user->isAdministrativeAssistant()) {
            return route('administrative-assistant.dashboard', absolute: false);
        }

        $routeName = match ($user->user_type) {
            'funding_partner' => 'partner.dashboard',
            'vendor' => 'vendor.dashboard',
            'member_state' => 'member-state.dashboard',
            'think_tank' => 'think-tank.dashboard',
            'ttl' => 'ttl.dashboard',
            default => 'dashboard',
        };

        return route($routeName, absolute: false);
    }

    private function portalReadinessError(User $user): ?string
    {
        if ($user->user_type === 'funding_partner') {
            $funder = $user->partnerFunder();

            if (! $funder || ! $funder->hasPortalAccess()) {
                return 'This funding partner account does not currently have an active portal link.';
            }

            if (! $user->hasPermission('partner.dashboard.access')) {
                return 'This funding partner account does not have dashboard access.';
            }
        }

        if ($user->user_type === 'member_state' && ! $user->member_state_id) {
            return 'This user is not linked to a member state yet.';
        }

        if ($user->user_type === 'think_tank') {
            $membership = $user->resolvedThinkTankMembership();

            if (! $membership || $membership->status !== 'active') {
                return 'This user is not linked to an active think tank membership.';
            }

            if (! $user->resolvedThinkTankAccessLevel() || ! $user->hasPermission('think_tank.portal.access')) {
                return 'This user does not currently have think tank portal access.';
            }
        }

        if ($user->user_type === 'ttl') {
            $email = Str::lower((string) $user->email);
            $hasProgram = Program::query()
                ->where(function ($query) use ($user, $email) {
                    $query->where('ttl_user_id', $user->id);

                    if ($email !== '') {
                        $query->orWhereRaw('LOWER(ttl_email) = ?', [$email]);
                    }
                })
                ->exists();

            if (! $hasProgram) {
                return 'This TTL account does not have a program assignment yet.';
            }
        }

        return null;
    }

    private function audit(
        Request $request,
        User $administrator,
        ?User $impersonatedUser,
        string $action,
        string $message
    ): bool {
        try {
            SystemAuditLog::create([
                'user_id' => $administrator->id,
                'module' => 'user_management',
                'action' => $action,
                'action_message' => Str::limit($message, 255, ''),
                'description' => $message,
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'route_name' => $request->route()?->getName(),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000),
                'status_code' => 200,
                'payload' => [
                    'administrator_id' => (string) $administrator->id,
                    'impersonated_user_id' => (string) ($impersonatedUser?->id ?? ''),
                ],
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::warning('User impersonation audit event could not be recorded.', [
                'action' => $action,
                'administrator_id' => $administrator->id,
                'impersonated_user_id' => $impersonatedUser?->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
