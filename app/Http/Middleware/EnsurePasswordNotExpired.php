<?php

namespace App\Http\Middleware;

use App\Support\UserImpersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordNotExpired
{
    /**
     * Routes that should be excluded from this middleware
     */
    protected array $except = [
        'security.password.change',
        'security.password.submit',
        'security.otp.show',
        'security.otp.verify',
        'security.otp.resend',
        'logout',
        'login',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Skip if not authenticated
        if (! $user) {
            return $next($request);
        }

        // The administrator has already authenticated; do not force changes to
        // the impersonated user's password or account state.
        if (UserImpersonation::isActive($request)) {
            return $next($request);
        }

        // Skip for super admins
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Skip if route is in exception list
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        // Check if user must change password (first login)
        if ($user->mustChangePassword()) {
            return redirect()->route('security.password.change')
                ->with('warning', 'For security reasons, please change your password before continuing.');
        }

        // Check if password has expired (older than 2 months)
        if ($user->isPasswordExpired()) {
            return redirect()->route('security.password.change')
                ->with('warning', 'Your password has expired. Please create a new password to continue.');
        }

        return $next($request);
    }

    protected function shouldSkip(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        if (! $routeName) {
            return false;
        }

        return in_array($routeName, $this->except);
    }
}
