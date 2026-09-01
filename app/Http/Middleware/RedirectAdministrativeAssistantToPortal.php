<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectAdministrativeAssistantToPortal
{
    private const ALLOWED_ROUTE_PATTERNS = [
        'administrative-assistant.*',
        'logout',
        'profile.*',
        'security.*',
        'language.*',
        'password.*',
        'verification.*',
        'impersonation.stop',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->isAdministrativeAssistant()) {
            return $next($request);
        }

        foreach (self::ALLOWED_ROUTE_PATTERNS as $pattern) {
            if ($request->routeIs($pattern)) {
                return $next($request);
            }
        }

        if (! $request->isMethodSafe()) {
            abort(403, 'Administrative Assistant accounts can only use their focused workspace.');
        }

        return redirect()
            ->route('administrative-assistant.dashboard')
            ->with('info', 'Your account has a focused workspace for purchase requests, invoices, and evidence documents.');
    }
}
