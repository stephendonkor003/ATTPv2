<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOtpVerified
{
    /**
     * Routes that should be excluded from this middleware
     */
    protected array $except = [
        'security.otp.show',
        'security.otp.verify',
        'security.otp.resend',
        'security.password.change',
        'security.password.submit',
        'logout',
        'login',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Skip if not authenticated
        if (!$user) {
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

        // Skip if user doesn't require OTP verification
        if (!$user->requiresOtpVerification()) {
            return $next($request);
        }

        // Check if OTP was verified in this session and still within validity window.
        if (!$this->hasValidSessionOtpVerification($request, (string) $user->id)) {
            // Store intended URL
            session()->put('url.intended', $request->url());

            return redirect()->route('security.otp.show')
                ->with('info', 'Please verify your identity with the code sent to your email.');
        }

        return $next($request);
    }

    protected function shouldSkip(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        if (!$routeName) {
            return false;
        }

        return in_array($routeName, $this->except);
    }

    protected function hasValidSessionOtpVerification(Request $request, string $userId): bool
    {
        if (!$request->session()->get('otp_verified', false)) {
            return false;
        }

        if ((string) $request->session()->get('otp_verified_user_id', '') !== $userId) {
            return false;
        }

        $verifiedAtRaw = $request->session()->get('otp_verified_at');
        if (!is_string($verifiedAtRaw) || trim($verifiedAtRaw) === '') {
            return false;
        }

        try {
            $verifiedAt = Carbon::parse($verifiedAtRaw);
        } catch (\Throwable $exception) {
            return false;
        }

        return $verifiedAt->isAfter(now()->subHours(24));
    }
}
