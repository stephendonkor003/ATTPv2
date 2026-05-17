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

        if (! $user->thinkTankMembership) {
            abort(403, 'Your think tank account is not linked to a consortium membership yet.');
        }

        return $next($request);
    }
}
