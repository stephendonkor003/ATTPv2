<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\ThinkTankApiException;
use App\Http\Controllers\Controller;
use App\Mail\Security\PasswordChangedMail;
use App\Models\User;
use App\Models\UserLoginOtp;
use App\Services\PendingLoginService;
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

        $request->session()->regenerate();
        if ($user->isThinkTankUser()) {
            app(ThinkTankSessionService::class)->bindCurrentSession($user, $request);
        }
        if ($user->isThinkTankUser() || $user->requiresOtpVerification()) {
            $user->markOtpAsVerified();
            app(ThinkTankAuthenticationStateService::class)->markMfaVerified($request, $user);
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
        $user = $this->otpUser($request);
        if (! $user) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Your verification session expired. Please sign in again.']);
        }

        // Generate and send OTP if not already sent recently
        $recentOtp = UserLoginOtp::where('user_id', $user->id)
            ->where('session_id', $request->session()->getId())
            ->where('expires_at', '>', now())
            ->whereNull('verified_at')
            ->first();

        if (! $recentOtp) {
            $challenge = $this->sendOtpCode($user);
            $otpSent = $challenge !== null;
            if ($challenge !== null && ! $request->user()) {
                app(PendingLoginService::class)->refreshExpiration(
                    $request,
                    $challenge['expires_at'],
                    PendingLoginService::PURPOSE_WEB,
                );
            }
            if ($challenge === null) {
                session()->flash('warning', 'The verification code could not be delivered. No code was created; please retry when email delivery is available.');
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

        $user = $this->otpUser($request);
        if (! $user) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Your verification session expired. Please sign in again.']);
        }

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

        $pendingLogin = ! $request->user();
        if ($pendingLogin) {
            // The account may have been changed while the OTP comparison was
            // in flight. Re-resolve the purpose-bound pending identity so a
            // password reset, disablement, email/type change, or access change
            // cannot be followed by a stale login.
            $freshUser = app(PendingLoginService::class)->user(
                $request,
                PendingLoginService::PURPOSE_WEB,
            );

            if (! $freshUser) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->withErrors(['email' => 'Your verification session is no longer available. Please sign in again.']);
            }

            $user = $freshUser;

            if ($user->is_disabled || ($user->user_type === 'vendor' && $user->is_blacklisted)) {
                app(PendingLoginService::class)->clear($request, PendingLoginService::PURPOSE_WEB);
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->withErrors(['email' => 'This account is not currently available. Please contact the administrator.']);
            }

            if ($user->isThinkTankUser()) {
                try {
                    $sessions = app(ThinkTankSessionService::class);
                    $sessions->assertProductionSecurityStores();
                    app(ThinkTankProductionSecurityService::class)->assertRuntimeConfiguration();
                    app(ThinkTankAccountAccessService::class)->membership($user);
                } catch (ThinkTankApiException) {
                    app(PendingLoginService::class)->clear($request, PendingLoginService::PURPOSE_WEB);
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return redirect()->route('login')
                        ->withErrors(['email' => 'This Think Tank portal account is not currently available.']);
                }
            }

            $remember = app(PendingLoginService::class)->remember($request, PendingLoginService::PURPOSE_WEB);
            app(PendingLoginService::class)->clear($request, PendingLoginService::PURPOSE_WEB);
            Auth::guard('web')->login($user, $remember);
        }

        RateLimiter::clear($sessionKey);
        RateLimiter::clear($accountKey);
        RateLimiter::clear('think-tank-login-account:'.hash('sha256', mb_strtolower((string) $user->email)));

        // Mark OTP as verified only after both factors have succeeded, then
        // rotate the session identifier before granting account access.
        $user->markOtpAsVerified();
        $request->session()->regenerate();
        app(ThinkTankAuthenticationStateService::class)->markMfaVerified($request, $user);
        if ($user->isThinkTankUser()) {
            app(ThinkTankSessionService::class)->bindCurrentSession($user, $request);
        }

        // Log the activity
        Log::info('OTP verification successful', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
        ]);

        if ($user->mustChangePassword() || $user->isPasswordExpired()) {
            return redirect()->route('security.password.change')
                ->with('success', 'Identity verified. Create your private password to finish signing in.');
        }

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
        $user = $this->otpUser($request);
        if (! $user) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Your verification session expired. Please sign in again.']);
        }

        // Rate limiting: Check if OTP was sent in last 60 seconds
        $recentOtp = UserLoginOtp::where('user_id', $user->id)
            ->where('session_id', $request->session()->getId())
            ->where('created_at', '>', now()->subSeconds(60))
            ->first();

        if ($recentOtp) {
            return back()->with('warning', 'Please wait at least 60 seconds before requesting a new code.');
        }

        $challenge = $this->sendOtpCode($user);
        if ($challenge === null) {
            return back()->with('warning', 'The verification code could not be delivered. No code was created; please try again.');
        }
        if (! $request->user()) {
            app(PendingLoginService::class)->refreshExpiration(
                $request,
                $challenge['expires_at'],
                PendingLoginService::PURPOSE_WEB,
            );
        }

        return back()->with('success', 'A new verification code has been sent to your email.');
    }

    /**
     * Send OTP code to user's email
     */
    protected function sendOtpCode($user): ?array
    {
        try {
            return app(ThinkTankMfaService::class)->send(request(), $user, true);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function otpUser(Request $request): ?User
    {
        $authenticated = $request->user();

        return $authenticated instanceof User
            ? $authenticated
            : app(PendingLoginService::class)->user($request, PendingLoginService::PURPOSE_WEB);
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
