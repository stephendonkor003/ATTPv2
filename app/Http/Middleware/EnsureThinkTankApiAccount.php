<?php

namespace App\Http\Middleware;

use App\Exceptions\ThinkTankApiException;
use App\Services\ThinkTank\ThinkTankAccountAccessService;
use App\Services\ThinkTank\ThinkTankSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureThinkTankApiAccount
{
    public function __construct(
        private readonly ThinkTankAccountAccessService $access,
        private readonly ThinkTankSessionService $sessions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $membership = $this->access->membership($request->user());

        if (! $this->sessions->hasValidCurrentSession($request->user(), $request)) {
            throw new ThinkTankApiException(
                'SESSION_REVOKED',
                'This session is no longer valid. Please sign in again.',
                401,
                ['state' => 'UNAUTHENTICATED', 'next_action' => 'LOGIN'],
            );
        }

        $request->attributes->set('think_tank.membership', $membership);

        return $next($request);
    }
}
