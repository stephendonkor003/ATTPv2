<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        if (! $this->passwordResetTokensTableExists()) {
            $this->logPasswordResetFailure('Password reset token table is missing.');

            return $this->temporaryPasswordResetFailure($request);
        }

        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );
        } catch (QueryException $exception) {
            $this->logPasswordResetFailure('Password reset link database failure.', $exception);

            return $this->temporaryPasswordResetFailure($request);
        } catch (Throwable $exception) {
            $this->logPasswordResetFailure('Password reset link delivery failure.', $exception);

            return $this->temporaryPasswordResetFailure($request);
        }

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', 'If an account exists for this email, a password reset link will be sent shortly.')
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
                'email' => 'Password reset is temporarily unavailable. Please try again shortly or contact ATTP support.',
            ]);
    }

    private function logPasswordResetFailure(string $message, ?Throwable $exception = null): void
    {
        Log::error($message, [
            'exception' => $exception?->getMessage(),
        ]);
    }
}
