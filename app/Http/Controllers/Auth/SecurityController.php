<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\ThinkTankApiException;
use App\Http\Controllers\Controller;
use App\Mail\Security\PasswordChangedMail;
use App\Models\User;
use App\Models\UserLoginOtp;
use App\Services\ThinkTank\ThinkTankAccountAccessService;
use App\Services\ThinkTank\ThinkTankApiAuditService;
use App\Services\ThinkTank\ThinkTankAuthenticationStateService;
use App\Services\ThinkTank\ThinkTankMfaService;
use App\Services\ThinkTank\ThinkTankProductionSecurityService;
use App\Services\ThinkTank\ThinkTankSessionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
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

        if ($user->isThinkTankUser()) {
            try {
                $sessions = app(ThinkTankSessionService::class);
                $sessions->assertProductionSecurityStores();
                app(ThinkTankProductionSecurityService::class)->assertRuntimeConfiguration();
                app(ThinkTankAccountAccessService::class)->membership($user);

                if (! $sessions->hasValidCurrentSession($user, $request)) {
                    throw app(ThinkTankAccountAccessService::class)->unavailable();
                }
            } catch (ThinkTankApiException) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->withErrors(['email' => 'This Think Tank portal session is no longer available. Please sign in again.']);
            }
        }

        $minimumLength = $user->isThinkTankUser()
            ? (int) config('think_tank_portal.password_min_length', 12)
            : 8;

        $request->validate([
            'current_password' => [
                'bail',
                'required',
                'string',
                'max:4096',
                $this->passwordByteRule(),
            ],
            'password' => [
                'bail',
                'required',
                'string',
                'max:4096',
                $this->passwordByteRule(),
                'different:current_password',
                'confirmed',
                Password::min($minimumLength)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
        ], [
            'current_password.current_password' => 'The current password you entered is incorrect.',
            'password.min' => "Your new password must be at least {$minimumLength} characters long.",
            'password.mixed' => 'Your new password must contain both uppercase and lowercase letters.',
            'password.numbers' => 'Your new password must contain at least one number.',
            'password.symbols' => 'Your new password must contain at least one special character.',
            'password.uncompromised' => 'This password has appeared in a data breach. Please choose a different password.',
        ]);

        try {
            $user = DB::transaction(function () use ($request, $user): User {
                $lockedUser = User::query()
                    ->whereKey($user->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedUser->isThinkTankUser()) {
                    app(ThinkTankAccountAccessService::class)->membership($lockedUser);
                }

                // Check the current credential while holding the same row lock
                // used for the update, closing the concurrent-change window.
                if (! Hash::check($request->string('current_password')->toString(), $lockedUser->getAuthPassword())) {
                    throw ValidationException::withMessages([
                        'current_password' => ['The current password you entered is incorrect.'],
                    ]);
                }

                $lockedUser->forceFill([
                    'password' => $request->string('password')->toString(),
                    'password_changed_at' => now(),
                    'must_change_password' => false,
                    'otp_verified_at' => null,
                ])->save();

                if ($lockedUser->isThinkTankUser()) {
                    $sessions = app(ThinkTankSessionService::class);
                    $sessions->invalidateMfa($lockedUser);
                    $sessions->revokeOtherSessions($lockedUser, $request);
                    app(ThinkTankApiAuditService::class)->required(
                        $request,
                        'think_tank.password.changed',
                        'Think tank portal password changed through the legacy transition route.',
                        ['target_user_id' => (string) $lockedUser->getKey()],
                        $lockedUser,
                    );
                } else {
                    $lockedUser->forceFill(['remember_token' => Str::random(60)])->save();
                }

                return $lockedUser;
            });
        } catch (ThinkTankApiException) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'This Think Tank portal account is not currently available.']);
        }

        if ($user->isThinkTankUser()) {
            app(ThinkTankSessionService::class)->bindCurrentSession($user, $request);
            app(ThinkTankAuthenticationStateService::class)->clearMfaSession($request);
        }

        // Send confirmation email immediately so users receive security notices without a queue worker.
        try {
            Mail::to($user->email)->send(new PasswordChangedMail($user));
        } catch (Throwable $exception) {
            Log::warning('Password changed email could not be sent.', [
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
        if ($user->isThinkTankUser() || $user->requiresOtpVerification()) {
            $otpSent = $this->sendOtpCode($user);
            $redirect = redirect()->route('security.otp.show')
                ->with('otpSent', $otpSent)
                ->with('success', 'Your password has been updated. Please verify the OTP sent to your email.');

            if (! $otpSent) {
                $redirect->with('warning', 'The email service is currently unavailable. In local development, use the verification code shown below.');
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

        if ($user->user_type === 'ttl') {
            return redirect()->intended(route('ttl.dashboard'))
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
            ->where('session_id', $request->session()->getId())
            ->where('expires_at', '>', now())
            ->whereNull('verified_at')
            ->first();

        if (! $recentOtp) {
            $otpSent = $this->sendOtpCode($user);
            if (! $otpSent) {
                session()->flash('warning', 'The email service is currently unavailable. In local development, use the verification code shown below.');
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
            'otp_code' => ['required', 'digits:6'],
        ], [
            'otp_code.required' => 'Please enter the 6-digit verification code.',
            'otp_code.digits' => 'The verification code must be exactly 6 digits.',
        ]);

        $user = auth()->user();

        // Session and account keys are deliberately independent of client IP,
        // so proxy/IP rotation cannot expand a six-digit guessing budget.
        $account = hash('sha256', (string) $user->getKey());
        $sessionKey = 'think-tank-mfa-session:'.$account.'|'.hash('sha256', $request->session()->getId());
        $accountKey = 'think-tank-mfa-account:'.$account;
        $sessionMaximum = (int) config('think_tank_portal.mfa_verify_max_attempts', 5);
        $accountMaximum = (int) config('think_tank_portal.mfa_verify_account_max_attempts', 10);

        if (RateLimiter::tooManyAttempts($sessionKey, $sessionMaximum)
            || RateLimiter::tooManyAttempts($accountKey, $accountMaximum)) {
            $seconds = max(
                RateLimiter::availableIn($sessionKey),
                RateLimiter::availableIn($accountKey),
            );

            return back()->withErrors([
                'otp_code' => "Too many attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        // Verify the OTP and bind it to the current browser session.
        if (! app(ThinkTankMfaService::class)->verify($request, $user, $request->string('otp_code')->toString())) {
            $decay = (int) config('think_tank_portal.mfa_verify_decay_seconds', 600);
            RateLimiter::hit($sessionKey, $decay);
            RateLimiter::hit($accountKey, $decay);

            return back()->withErrors([
                'otp_code' => 'The verification code is invalid or has expired. Please request a new code.',
            ]);
        }

        RateLimiter::clear($sessionKey);
        RateLimiter::clear($accountKey);
        RateLimiter::clear('think-tank-login-account:'.hash('sha256', mb_strtolower((string) $user->email)));

        // Mark OTP as verified for the session
        $user->markOtpAsVerified();
        $request->session()->regenerate();
        app(ThinkTankAuthenticationStateService::class)->markMfaVerified($request, $user);

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

        if ($user->user_type === 'ttl') {
            return redirect()->intended(route('ttl.dashboard'))
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
            ->where('session_id', $request->session()->getId())
            ->where('created_at', '>', now()->subSeconds(60))
            ->first();

        if ($recentOtp) {
            return back()->with('warning', 'Please wait at least 60 seconds before requesting a new code.');
        }

        if (! $this->sendOtpCode($user)) {
            return back()->with('warning', 'The email service is currently unavailable. In local development, use the verification code shown below.');
        }

        return back()->with('success', 'A new verification code has been sent to your email.');
    }

    /**
     * Send OTP code to user's email
     */
    protected function sendOtpCode($user): bool
    {
        try {
            app(ThinkTankMfaService::class)->send(request(), $user, true);

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function passwordByteRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value)
                && strlen($value) > (int) config('think_tank_portal.password_max_bytes', 72)) {
                $fail('The :attribute must not exceed 72 bytes.');
            }
        };
    }
}
