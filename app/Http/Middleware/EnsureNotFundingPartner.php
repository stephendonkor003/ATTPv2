<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotFundingPartner
{
    /**
     * Handle an incoming request.
     *
     * Ensures funding partners cannot access admin/system routes.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Redirect funding partners to their portal if they try to access admin routes
        if ($user && ($user->user_type === 'funding_partner' || $user->isFundingPartner())) {
            if ($request->expectsJson()) {
                abort(403, 'You do not have permission to access that area.');
            }

            return redirect()->route('partner.dashboard')
                ->with('error', 'You do not have permission to access that area.');
        }

        if ($user && $user->user_type === 'vendor') {
            if ($request->expectsJson()) {
                abort(403, 'You do not have permission to access that area.');
            }

            return redirect()->route('vendor.dashboard')
                ->with('error', 'You do not have permission to access that area.');
        }

        return $next($request);
    }
}
