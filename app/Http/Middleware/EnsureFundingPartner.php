<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFundingPartner
{
    /**
     * Handle an incoming request.
     *
     * Ensures only funding partners can access partner portal routes.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ($user->isSuperAdmin() || $user->isAdmin())) {
            return $next($request);
        }

        // Check if user is a funding partner
        if (!$user || ($user->user_type !== 'funding_partner' && !$user->isFundingPartner())) {
            abort(403, 'Access denied. This area is restricted to funding partners only.');
        }

        // Check if user has portal access
        $funder = $user->funderPortal;
        if (!$funder || !$funder->hasPortalAccess()) {
            abort(403, 'Your partner portal access has been disabled. Please contact the administrator.');
        }

        return $next($request);
    }
}
