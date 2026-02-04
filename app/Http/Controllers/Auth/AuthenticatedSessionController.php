<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Mail\Security\LoginOtpMail;
use App\Models\UserLoginOtp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

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
        $request->authenticate();
        $request->session()->regenerate();

        $user = Auth::user();

        if ($user->user_type === 'vendor') {
            if ($user->is_blacklisted) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->withErrors(['email' => 'Your vendor account has been blacklisted. Please contact the administrator.']);
            }

            if ($user->is_disabled) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->withErrors(['email' => 'Your vendor account has been disabled. Please contact the administrator.']);
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
        if ($user->requiresOtpVerification()) {
            $this->sendLoginOtp($user, $request->session()->getId());
            return redirect()->route('security.otp.show')
                ->with('otpSent', true);
        }

        // Redirect funding partners to their portal
        if ($user->user_type === 'funding_partner' || $user->isFundingPartner()) {
            return redirect()->intended(route('partner.dashboard', absolute: false));
        }

        // Redirect vendors to their portal
        if ($user->user_type === 'vendor') {
            return redirect()->intended(route('vendor.dashboard', absolute: false));
        }

        // Default redirect to admin dashboard for all other users
        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Generate and send OTP to the user's email.
     */
    protected function sendLoginOtp($user, ?string $sessionId = null): void
    {
        // Generate new OTP
        $otp = UserLoginOtp::generateFor($user, $sessionId);

        // Send email with OTP
        Mail::to($user->email)->send(
            new LoginOtpMail($user, $otp->otp_code)
        );
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Clear OTP verification status from session
        $request->session()->forget('otp_verified');
        $request->session()->forget('otp_verified_at');

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
