<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Mail\Security\LoginOtpMail;
use App\Models\UserLoginOtp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
                    ? 'Your account is temporarily blocked until ' . $until . '. Please contact the administrator.'
                    : 'Your account has been blocked. Please contact the administrator.';
                return redirect()->route('login')
                    ->withErrors(['email' => $message]);
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
            $otpSent = $this->sendLoginOtp($user, $request->session()->getId());
            $redirect = redirect()->route('security.otp.show')
                ->with('otpSent', $otpSent);

            if (! $otpSent) {
                $redirect->with('warning', 'The email service is currently unavailable. In local development, use the verification code shown below or in the Laravel log.');
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

        // Default redirect to admin dashboard for all other users
        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Generate and send OTP to the user's email.
     */
    protected function sendLoginOtp($user, ?string $sessionId = null): bool
    {
        // Generate new OTP
        $otp = UserLoginOtp::generateFor($user, $sessionId);

        try {
            Mail::to($user->email)->send(
                new LoginOtpMail($user, $otp->otp_code)
            );

            return true;
        } catch (Throwable $exception) {
            Log::warning('Login OTP email could not be sent.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'mailer' => config('mail.default'),
                'error' => $exception->getMessage(),
            ]);

            if (app()->environment(['local', 'testing'])) {
                session()->flash('devOtpCode', $otp->otp_code);
                Log::info('Local development OTP fallback code.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'otp_code' => $otp->otp_code,
                ]);
            }

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
