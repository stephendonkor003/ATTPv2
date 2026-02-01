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
use Illuminate\Validation\Rules\Password;

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
        Mail::to($user->email)->queue(new PasswordChangedMail($user));

        // Log the activity
        Log::info('Password changed successfully', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
        ]);

        // Redirect funding partners to their portal
        if ($user->user_type === 'funding_partner' || $user->isFundingPartner()) {
            return redirect()->intended(route('partner.dashboard'))
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
            $this->sendOtpCode($user);
            $otpSent = true;
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

        // Verify the OTP
        if (!UserLoginOtp::verifyCode($user, $request->otp_code)) {
            return back()->withErrors([
                'otp_code' => 'The verification code is invalid or has expired. Please request a new code.',
            ]);
        }

        // Mark OTP as verified for the session
        $user->markOtpAsVerified();

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

        $this->sendOtpCode($user);

        return back()->with('success', 'A new verification code has been sent to your email.');
    }

    /**
     * Send OTP code to user's email
     */
    protected function sendOtpCode($user): void
    {
        $otp = UserLoginOtp::generateFor($user, session()->getId());

        Mail::to($user->email)->queue(new LoginOtpMail($user, $otp->otp_code));

        // Log the activity
        Log::info('Login OTP code sent', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => request()->ip(),
        ]);
    }
}
