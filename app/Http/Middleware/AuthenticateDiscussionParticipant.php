<?php

namespace App\Http\Middleware;

use App\Services\DiscussionParticipantTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDiscussionParticipant
{
    public function __construct(private readonly DiscussionParticipantTokenService $tokens)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->tokens->resolve($request);

        if (! $token) {
            $response = response()->json([
                'message' => 'Please sign in with a discussion participant account.',
                'code' => 'participant_unauthenticated',
            ], 401);

            if ($request->cookies->has(DiscussionParticipantTokenService::COOKIE_NAME)) {
                $response->headers->setCookie($this->tokens->expiredRememberedDeviceCookie($request));
            }

            return $response;
        }

        if ($token->participant->isBlocked()) {
            // Blocking an account must end remembered sessions on every device,
            // including accounts blocked outside the normal admin action.
            $token->participant->tokens()->delete();

            $response = response()->json([
                'message' => 'This participant account has been blocked from discussions.',
                'code' => 'participant_blocked',
                'reason' => $token->participant->blocked_reason,
            ], 403);
            $response->headers->setCookie($this->tokens->expiredRememberedDeviceCookie($request));

            return $response;
        }

        $request->attributes->set('discussion_participant', $token->participant);
        $request->attributes->set('discussion_participant_token', $token);

        $response = $next($request);
        $refreshedCookie = $this->tokens->refreshedRememberedDeviceCookie($request, $token);

        if ($refreshedCookie) {
            $response->headers->setCookie($refreshedCookie);
        }

        return $response;
    }
}
