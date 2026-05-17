<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
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
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        if (! $this->passwordResetTokensTableExists()) {
            $this->logPasswordResetFailure('Password reset token table is missing.');

            return $this->temporaryPasswordResetFailure($request);
        }

        try {
            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function (User $user) use ($request) {
                    $user->forceFill([
                        'password' => Hash::make($request->password),
                        'remember_token' => Str::random(60),
                    ])->save();

                    event(new PasswordReset($user));
                }
            );
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
        return $status == Password::PASSWORD_RESET
                    ? redirect()->route('login')->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
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
}
