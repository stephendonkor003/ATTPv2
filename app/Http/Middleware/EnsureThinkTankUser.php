<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureThinkTankUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ($user->isSuperAdmin() || $user->isAdmin())) {
            return $next($request);
        }

        if (! $user || $user->user_type !== 'think_tank') {
            abort(403, 'Access denied. This area is restricted to think tank portal users only.');
        }

        if ($user->is_blacklisted || $user->hasActiveLoginBlock()) {
            abort(403, 'This portal account is not currently available.');
        }

        $membership = $user->resolvedThinkTankMembership();

        if (! $membership) {
            abort(403, 'Your think tank account is not linked to a consortium membership yet.');
        }

        if ($membership->status !== 'active') {
            abort(403, 'Your think tank membership is not currently active.');
        }

        if (! $user->resolvedThinkTankAccessLevel()) {
            abort(403, 'Your think tank portal access level has not been assigned.');
        }

        return $next($request);
    }
}
