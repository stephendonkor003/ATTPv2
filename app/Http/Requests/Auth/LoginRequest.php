<?php

namespace App\Http\Requests\Auth;

use Closure;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['bail', 'required', 'string', 'max:4096', $this->passwordByteRule()],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        try {
            $authenticated = Auth::attempt($this->only('email', 'password'), $this->boolean('remember'));
        } catch (RuntimeException $exception) {
            if (! str_contains($exception->getMessage(), 'does not use the Bcrypt algorithm')) {
                throw $exception;
            }

            Log::warning('Login rejected because the stored password hash is incompatible with bcrypt.', [
                'email' => $this->string('email')->toString(),
                'ip' => $this->ip(),
            ]);

            $authenticated = false;
        }

        if (! $authenticated) {
            RateLimiter::hit($this->throttleKey());
            RateLimiter::hit(
                $this->accountThrottleKey(),
                (int) config('think_tank_portal.login_email_decay_seconds', 900),
            );

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        // Think Tank failures remain account-limited until the complete MFA
        // ceremony succeeds. Other legacy account types retain prior behavior.
        if (! Auth::user()?->isThinkTankUser()) {
            RateLimiter::clear($this->accountThrottleKey());
        }
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        $accountMaximum = (int) config('think_tank_portal.login_email_max_attempts', 20);

        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)
            && ! RateLimiter::tooManyAttempts($this->accountThrottleKey(), $accountMaximum)) {
            return;
        }

        event(new Lockout($this));

        $seconds = max(
            RateLimiter::availableIn($this->throttleKey()),
            RateLimiter::availableIn($this->accountThrottleKey()),
        );

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }

    public function accountThrottleKey(): string
    {
        $email = mb_strtolower(trim($this->string('email')->toString()));

        return 'think-tank-login-account:'.hash('sha256', $email);
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
