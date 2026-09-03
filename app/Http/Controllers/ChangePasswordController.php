<?php

namespace App\Http\Controllers;

use App\Mail\PasswordChangedNotification;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Throwable;

class ChangePasswordController extends Controller
{
    public function show(Request $request)
    {
        abort_if(
            $request->user()->isThinkTankUser(),
            403,
            'Think Tank password changes must be completed through the dedicated portal.'
        );

        return view('auth.change-password');
    }

    public function update(Request $request)
    {
        abort_if(
            $request->user()->isThinkTankUser(),
            403,
            'Think Tank password changes must be completed through the dedicated portal.'
        );

        $request->validate([
            'password' => [
                'bail',
                'required',
                'string',
                'max:4096',
                $this->passwordByteRule(),
                'confirmed',
                Password::min(12)->mixedCase()->numbers()->symbols(),
            ],
            'password_confirmation' => [
                'bail',
                'required',
                'string',
                'max:4096',
                $this->passwordByteRule(),
            ],
        ]);

        $user = Auth::user();

        $user->forceFill([
            'password' => $request->string('password')->toString(),
            'must_change_password' => false,
            'password_changed_at' => now(),
            'otp_verified_at' => null,
            'remember_token' => Str::random(60),
        ])->save();

        // Security notifications confirm the event but never contain a
        // plaintext password or any reusable credential.
        try {
            Mail::to($user->email)->send(new PasswordChangedNotification($user));
        } catch (Throwable $exception) {
            Log::warning('Password changed email could not be sent.', [
                'user_id' => $user->getKey(),
                'mailer' => config('mail.default'),
                'error' => $exception->getMessage(),
            ]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('success', 'Password updated successfully. You have been logged out for security. Please log in again.');
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
