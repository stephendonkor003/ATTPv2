<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\ThinkTankApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\ThinkTank\ThinkTankAccountAccessService;
use App\Services\ThinkTank\ThinkTankMfaService;
use App\Services\ThinkTank\ThinkTankProductionSecurityService;
use App\Services\ThinkTank\ThinkTankSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Throwable;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        // Honeypot: legitimate users never fill this hidden field; bots do.
        if ($request->filled('website')) {
            return redirect()->route('login')
                ->withErrors(['email' => trans('auth.failed')]);
        }

        $request->authenticate();
        $request->session()->regenerate();
        $request->session()->forget([
            'otp_verified',
            'otp_verified_at',
            'otp_verified_user_id',
        ]);

        $user = Auth::user();

        if ($user->user_type === 'vendor') {
            if ($user->is_blacklisted) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->withErrors(['email' => 'Your vendor account has been blacklisted. Please contact the administrator.']);
            }
        }

        if ($user->is_disabled) {
            // Auto-release temporary blocks that already expired.
            if ($user->disabled_until && $user->disabled_until->isPast()) {
                $user->update([
                    'is_disabled' => false,
                    'disabled_at' => null,
                    'disabled_until' => null,
                    'disabled_reason' => null,
                ]);
                $user->refresh();
            } else {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                $until = optional($user->disabled_until)->format('d M Y H:i');
                $message = $until
                    ? 'Your account is temporarily blocked until '.$until.'. Please contact the administrator.'
                    : 'Your account has been blocked. Please contact the administrator.';

                return redirect()->route('login')
                    ->withErrors(['email' => $message]);
            }
        }

        if ($user->user_type === 'think_tank') {
            try {
                $sessions = app(ThinkTankSessionService::class);
                $sessions->assertProductionSecurityStores();
                app(ThinkTankProductionSecurityService::class)->assertRuntimeConfiguration();
                app(ThinkTankAccountAccessService::class)->membership($user);
                $sessions->bindCurrentSession($user, $request);
            } catch (ThinkTankApiException) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->withErrors(['email' => 'This Think Tank portal account is not currently available.']);
            }
        }

        // Check if user is a super admin (bypass all security checks)
        if ($user->isSuperAdmin()) {
            // Funding partners who are also super admins go to partner dashboard
            if ($user->user_type === 'funding_partner' || $user->isFundingPartner()) {
                return redirect()->intended(route('partner.dashboard', absolute: false));
            }

            return redirect()->intended(route('dashboard', absolute: false));
        }

        // Check if password change is required (first login or expired)
        if ($user->mustChangePassword() || $user->isPasswordExpired()) {
            return redirect()->route('security.password.change');
        }

        // Generate and send OTP for non-admin users
        if ($user->isThinkTankUser() || $user->requiresOtpVerification()) {
            $otpSent = $this->sendLoginOtp($user, $request->session()->getId());
            $redirect = redirect()->route('security.otp.show')
                ->with('otpSent', $otpSent);

            if (! $otpSent) {
                $redirect->with('warning', 'The email service is currently unavailable. In local development, use the verification code shown below.');
            }

            return $redirect;
        }

        // Redirect funding partners to their portal
        if ($user->user_type === 'funding_partner' || $user->isFundingPartner()) {
            return redirect()->intended(route('partner.dashboard', absolute: false));
        }

        // Redirect vendors to their portal
        if ($user->user_type === 'vendor') {
            return redirect()->intended(route('vendor.dashboard', absolute: false));
        }

        // Redirect member states to their dedicated portal dashboard
        if ($user->user_type === 'member_state') {
            return redirect()->intended(route('member-state.dashboard', absolute: false));
        }

        if ($user->user_type === 'think_tank') {
            return redirect()->intended(route('think-tank.dashboard', absolute: false));
        }

        if ($user->user_type === 'ttl') {
            return redirect()->intended(route('ttl.dashboard', absolute: false));
        }

        // Default redirect to admin dashboard for all other users
        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Generate and send OTP to the user's email.
     */
    protected function sendLoginOtp($user, ?string $sessionId = null): bool
    {
        try {
            app(ThinkTankMfaService::class)->send(request(), $user, true);

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Clear OTP verification status from session
        $request->session()->forget('otp_verified');
        $request->session()->forget('otp_verified_at');
        $request->session()->forget('otp_verified_user_id');

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
