<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\Security\LoginOtpMail;
use App\Mail\Security\PasswordChangedMail;
use App\Models\UserLoginOtp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Throwable;

class SecurityController extends Controller
{
    /* =====================================================
     | PASSWORD CHANGE
     ===================================================== */

    /**
     * Show the force password change form
     */
    public function showPasswordChangeForm()
    {
        $user = auth()->user();

        // Determine the reason for password change
        $reason = 'security';
        $message = 'Please update your password to continue.';

        if ($user->must_change_password) {
            $reason = 'first_login';
            $message = 'Welcome! For your security, please create a new password to get started.';
        } elseif ($user->isPasswordExpired()) {
            $reason = 'expired';
            $message = 'Your password has expired. Please create a new password to continue using the platform.';
        }

        return view('auth.security.password-change', [
            'reason' => $reason,
            'message' => $message,
        ]);
    }

    /**
     * Handle password change submission
     */
    public function submitPasswordChange(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
        ], [
            'current_password.current_password' => 'The current password you entered is incorrect.',
            'password.min' => 'Your new password must be at least 8 characters long.',
            'password.mixed' => 'Your new password must contain both uppercase and lowercase letters.',
            'password.numbers' => 'Your new password must contain at least one number.',
            'password.symbols' => 'Your new password must contain at least one special character.',
            'password.uncompromised' => 'This password has appeared in a data breach. Please choose a different password.',
        ]);

        // Update password
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Mark as changed
        $user->markPasswordAsChanged();

        // Send confirmation email (queued for scalability)
        try {
            Mail::to($user->email)->queue(new PasswordChangedMail($user));
        } catch (Throwable $exception) {
            Log::warning('Password changed email could not be queued.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'mailer' => config('mail.default'),
                'error' => $exception->getMessage(),
            ]);
        }

        // Log the activity
        Log::info('Password changed successfully', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
        ]);

        // Send OTP right after password change for all non-admin users
        if ($user->requiresOtpVerification()) {
            $otpSent = $this->sendOtpCode($user);
            $redirect = redirect()->route('security.otp.show')
                ->with('otpSent', $otpSent)
                ->with('success', 'Your password has been updated. Please verify the OTP sent to your email.');

            if (! $otpSent) {
                $redirect->with('warning', 'The email service is currently unavailable. In local development, use the verification code shown below or in the Laravel log.');
            }

            return $redirect;
        }

        // Redirect funding partners to their portal
        if ($user->user_type === 'funding_partner' || $user->isFundingPartner()) {
            return redirect()->intended(route('partner.dashboard'))
                ->with('success', 'Your password has been updated successfully. Your account is now active.');
        }

        if ($user->user_type === 'vendor') {
            return redirect()->intended(route('vendor.dashboard'))
                ->with('success', 'Your password has been updated successfully. Your account is now active.');
        }

        if ($user->user_type === 'member_state') {
            return redirect()->intended(route('member-state.dashboard'))
                ->with('success', 'Your password has been updated successfully. Your account is now active.');
        }

        if ($user->user_type === 'think_tank') {
            return redirect()->intended(route('think-tank.dashboard'))
                ->with('success', 'Your password has been updated successfully. Your account is now active.');
        }

        return redirect()->intended(route('dashboard'))
            ->with('success', 'Your password has been updated successfully. Your account is now active.');
    }

    /* =====================================================
     | OTP VERIFICATION
     ===================================================== */

    /**
     * Show OTP verification form and send OTP
     */
    public function showOtpForm(Request $request)
    {
        $user = auth()->user();

        // Generate and send OTP if not already sent recently
        $recentOtp = UserLoginOtp::where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->whereNull('verified_at')
            ->first();

        if (!$recentOtp) {
            $otpSent = $this->sendOtpCode($user);
            if (! $otpSent) {
                session()->flash('warning', 'The email service is currently unavailable. In local development, use the verification code shown below or in the Laravel log.');
            }
        } else {
            $otpSent = false;
        }

        return view('auth.security.verify-otp', [
            'user' => $user,
            'otpSent' => $otpSent,
            'expiresAt' => $recentOtp?->expires_at ?? now()->addMinutes(10),
        ]);
    }

    /**
     * Verify OTP code
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp_code' => ['required', 'string', 'size:6'],
        ], [
            'otp_code.required' => 'Please enter the 6-digit verification code.',
            'otp_code.size' => 'The verification code must be exactly 6 digits.',
        ]);

        $user = auth()->user();

        // Rate limit OTP verification attempts to reduce brute-force risk.
        $throttleKey = Str::lower('otp_verify|'.$user->id.'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'otp_code' => "Too many attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        // Verify the OTP and bind it to the current browser session.
        if (!UserLoginOtp::verifyCode($user, $request->otp_code, $request->session()->getId())) {
            RateLimiter::hit($throttleKey, 600); // 10 minutes
            return back()->withErrors([
                'otp_code' => 'The verification code is invalid or has expired. Please request a new code.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        // Mark OTP as verified for the session
        $user->markOtpAsVerified();
        $request->session()->regenerate();
        $request->session()->put([
            'otp_verified' => true,
            'otp_verified_at' => now()->toIso8601String(),
            'otp_verified_user_id' => (string) $user->id,
        ]);

        // Log the activity
        Log::info('OTP verification successful', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
        ]);

        // Redirect funding partners to their portal
        if ($user->user_type === 'funding_partner' || $user->isFundingPartner()) {
            return redirect()->intended(route('partner.dashboard'))
                ->with('success', 'Identity verified successfully. Welcome back!');
        }

        if ($user->user_type === 'vendor') {
            return redirect()->intended(route('vendor.dashboard'))
                ->with('success', 'Identity verified successfully. Welcome back!');
        }

        if ($user->user_type === 'member_state') {
            return redirect()->intended(route('member-state.dashboard'))
                ->with('success', 'Identity verified successfully. Welcome back!');
        }

        if ($user->user_type === 'think_tank') {
            return redirect()->intended(route('think-tank.dashboard'))
                ->with('success', 'Identity verified successfully. Welcome back!');
        }

        return redirect()->intended(route('dashboard'))
            ->with('success', 'Identity verified successfully. Welcome back!');
    }

    /**
     * Resend OTP code
     */
    public function resendOtp(Request $request)
    {
        $user = auth()->user();

        // Rate limiting: Check if OTP was sent in last 60 seconds
        $recentOtp = UserLoginOtp::where('user_id', $user->id)
            ->where('created_at', '>', now()->subSeconds(60))
            ->first();

        if ($recentOtp) {
            return back()->with('warning', 'Please wait at least 60 seconds before requesting a new code.');
        }

        if (! $this->sendOtpCode($user)) {
            return back()->with('warning', 'The email service is currently unavailable. In local development, use the verification code shown below or in the Laravel log.');
        }

        return back()->with('success', 'A new verification code has been sent to your email.');
    }

    /**
     * Send OTP code to user's email
     */
    protected function sendOtpCode($user): bool
    {
        $otp = UserLoginOtp::generateFor($user, session()->getId());

        try {
            Mail::to($user->email)->send(
                new LoginOtpMail($user, $otp->otp_code)
            );

            // Log the activity
            Log::info('Login OTP code sent', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => request()->ip(),
            ]);

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
}
