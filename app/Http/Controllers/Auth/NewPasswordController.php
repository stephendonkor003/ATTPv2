<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\ThinkTankApiException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ThinkTank\ThinkTankAccountAccessService;
use App\Services\ThinkTank\ThinkTankApiAuditService;
use App\Services\ThinkTank\ThinkTankSessionService;
use Closure;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => [
                'bail',
                'required',
                'string',
                'max:4096',
                $this->passwordByteRule(),
                'confirmed',
                Rules\Password::min((int) config('think_tank_portal.password_min_length', 12))
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
            'password_confirmation' => [
                'bail',
                'required',
                'string',
                'max:4096',
                $this->passwordByteRule(),
            ],
        ]);

        $submittedEmail = trim($request->string('email')->toString());
        $email = mb_strtolower($submittedEmail);
        $request->merge(['email' => $submittedEmail]);
        $attemptKey = 'legacy-password-reset:'.hash('sha256', $email).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($attemptKey, 5)) {
            return back()->withInput($request->only('email'))->withErrors([
                'token' => 'This password reset request cannot be completed. Please request a new link shortly.',
            ]);
        }

        RateLimiter::hit($attemptKey, 300);

        if (! $this->passwordResetTokensTableExists()) {
            $this->logPasswordResetFailure('Password reset token table is missing.');

            return $this->temporaryPasswordResetFailure($request);
        }

        try {
            /** @var PasswordBroker $broker */
            $broker = Password::broker();
            $status = $broker->reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function (User $user, string $password) use ($broker, $request) {
                    if ($user->isThinkTankUser()) {
                        DB::transaction(function () use ($broker, $password, $request, $user): void {
                            $lockedUser = User::query()
                                ->whereKey($user->getKey())
                                ->lockForUpdate()
                                ->firstOrFail();

                            try {
                                app(ThinkTankAccountAccessService::class)->membership($lockedUser);
                            } catch (ThinkTankApiException) {
                                throw ValidationException::withMessages([
                                    'token' => ['This password reset link is invalid or has expired.'],
                                ]);
                            }

                            // The first concurrent submission consumes the token
                            // before releasing this user-row lock. A replay cannot
                            // overwrite the password selected by the first request.
                            if (! $broker->tokenExists($lockedUser, $request->string('token')->toString())) {
                                throw ValidationException::withMessages([
                                    'token' => ['This password reset link is invalid or has expired.'],
                                ]);
                            }

                            if (Hash::check($password, $lockedUser->getAuthPassword())) {
                                throw ValidationException::withMessages([
                                    'password' => ['The new password must be different from the current password.'],
                                ]);
                            }

                            $lockedUser->forceFill([
                                'password' => $password,
                                'must_change_password' => false,
                                'password_changed_at' => now(),
                                'email_verified_at' => $lockedUser->email_verified_at ?: now(),
                                'otp_verified_at' => null,
                            ])->save();

                            $sessions = app(ThinkTankSessionService::class);
                            $sessions->invalidateMfa($lockedUser);
                            $sessions->revokeAllSessions($lockedUser);
                            $broker->deleteToken($lockedUser);
                            app(ThinkTankApiAuditService::class)->required(
                                $request,
                                'think_tank.password.reset',
                                'Think tank portal password reset through the legacy transition route.',
                                ['target_user_id' => (string) $lockedUser->getKey()],
                                $lockedUser,
                            );
                        });
                    } else {
                        $user->forceFill([
                            'password' => $password,
                            'remember_token' => Str::random(60),
                        ])->save();
                    }

                    event(new PasswordReset($user));
                }
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            $this->logPasswordResetFailure('Password reset database failure.', $exception);

            return $this->temporaryPasswordResetFailure($request);
        } catch (Throwable $exception) {
            $this->logPasswordResetFailure('Password reset failure.', $exception);

            return $this->temporaryPasswordResetFailure($request);
        }

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        if ($status === Password::PASSWORD_RESET) {
            RateLimiter::clear($attemptKey);

            return redirect()->route('login')->with('status', __($status));
        }

        return back()->withInput($request->only('email'))->withErrors([
            'token' => 'This password reset link is invalid or has expired.',
        ]);
    }

    private function passwordResetTokensTableExists(): bool
    {
        try {
            return Schema::hasTable(config('auth.passwords.'.config('auth.defaults.passwords').'.table', 'password_reset_tokens'));
        } catch (Throwable $exception) {
            $this->logPasswordResetFailure('Password reset token table check failed.', $exception);

            return false;
        }
    }

    private function temporaryPasswordResetFailure(Request $request): RedirectResponse
    {
        return back()
            ->withInput($request->only('email'))
            ->withErrors([
                'email' => 'Password reset is temporarily unavailable. Please request a new link shortly or contact ATTP support.',
            ]);
    }

    private function logPasswordResetFailure(string $message, ?Throwable $exception = null): void
    {
        Log::error($message, [
            'exception' => $exception?->getMessage(),
        ]);
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
