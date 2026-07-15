<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\DiscussionParticipantPasswordResetMail;
use App\Models\DiscussionParticipant;
use App\Services\DiscussionParticipantPasswordResetService;
use App\Services\DiscussionParticipantTokenService;
use App\Support\DiscussionAccountEmailPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Throwable;

class DiscussionParticipantPasswordController extends Controller
{
    public const FORGOT_RESPONSE = 'If the email can be used, a password reset link will be sent shortly.';

    public function __construct(
        private readonly DiscussionParticipantPasswordResetService $resets,
        private readonly DiscussionParticipantTokenService $tokens
    ) {}

    public function forgot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $email = DiscussionAccountEmailPolicy::normalize($validated['email']);
        $participant = DiscussionParticipant::query()->where('email', $email)->first();

        if ($participant) {
            $issued = $this->resets->issue($participant);
            $query = http_build_query([
                'password_reset' => 1,
                'email' => $participant->email,
                'token' => $issued['plain_text_token'],
            ], '', '&', PHP_QUERY_RFC3986);
            $resetUrl = route('discussion.join').'?'.$query;

            try {
                Mail::to($participant->email)->send(new DiscussionParticipantPasswordResetMail(
                    $participant,
                    $resetUrl,
                    DiscussionParticipantPasswordResetService::LIFETIME_MINUTES
                ));
            } catch (Throwable $exception) {
                // Do not delete a newer link that may have replaced this one
                // while the mail transport was running.
                $issued['reset']->newQuery()
                    ->whereKey($issued['reset']->getKey())
                    ->where('token_hash', $issued['reset']->token_hash)
                    ->delete();

                Log::warning('Discussion participant password reset email could not be sent.', [
                    'participant_id' => $participant->getKey(),
                    'mailer' => config('mail.default'),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return response()->json(['message' => self::FORGOT_RESPONSE]);
    }

    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'token' => ['required', 'string', 'min:40', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $participant = $this->resets->consume(
            $validated['email'],
            $validated['token'],
            $validated['password']
        );

        if (! $participant) {
            throw ValidationException::withMessages([
                'token' => ['This password reset link is invalid or has expired.'],
            ]);
        }

        $response = response()->json([
            'message' => 'Your password has been reset. Please sign in with your new password.',
        ]);

        return $response->withCookie($this->tokens->expiredRememberedDeviceCookie($request));
    }
}
