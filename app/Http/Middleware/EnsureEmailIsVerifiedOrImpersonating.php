<?php

namespace App\Http\Middleware;

use App\Support\UserImpersonation;
use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;

class EnsureEmailIsVerifiedOrImpersonating extends EnsureEmailIsVerified
{
    public function handle($request, Closure $next, $redirectToRoute = null)
    {
        if (UserImpersonation::isActive($request)) {
            return $next($request);
        }

        return parent::handle($request, $next, $redirectToRoute);
    }
}
