<?php

namespace App\Support;

use App\Http\Controllers\EvaluationSubmissionController;
use Illuminate\Http\Request;

final class AuditorAccess
{
    /**
     * Mutating self-service endpoints that must remain available so an auditor
     * can finish authentication, maintain their credential, leave an
     * impersonation session, and sign out. No business workflow belongs here.
     *
     * @var array<int, string>
     */
    private const AUTH_SESSION_ROUTE_ALLOWLIST = [
        'logout',
        'impersonation.stop',
        'verification.send',
        'password.update',
        'password.change.update',
        'security.password.submit',
        'security.otp.verify',
        'security.otp.resend',
        'api.v1.think-tank.auth.logout',
        'api.v1.think-tank.auth.password.update',
        'api.v1.think-tank.auth.mfa.verify',
        'api.v1.think-tank.auth.mfa.resend',
    ];

    /**
     * These legacy GET routes create or relink an evaluation draft while
     * rendering the form, so their HTTP verb alone is not sufficient.
     *
     * @var array<int, string>
     */
    private const MUTATING_SAFE_ROUTE_DENYLIST = [
        'my.eval.start',
        'eval.assign.start',
    ];

    public static function requestIsAllowed(Request $request): bool
    {
        if (self::requestIsSafeReadAllowed($request)) {
            return true;
        }

        $routeName = $request->route()?->getName();

        return is_string($routeName)
            && in_array($routeName, self::AUTH_SESSION_ROUTE_ALLOWLIST, true);
    }

    public static function requestIsSafeReadAllowed(Request $request): bool
    {
        // The console bootstrap binds a synthetic GET request. Require a
        // matched route so commands and request-less authorization checks do
        // not accidentally inherit system-wide read authority.
        if ($request->route() === null || ! $request->isMethodSafe()) {
            return false;
        }

        $routeName = $request->route()?->getName();

        if (in_array($routeName, self::MUTATING_SAFE_ROUTE_DENYLIST, true)) {
            return false;
        }

        return $request->route()?->getActionName()
            !== EvaluationSubmissionController::class.'@start';
    }
}
