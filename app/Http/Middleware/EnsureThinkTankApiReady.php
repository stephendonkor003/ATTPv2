<?php

namespace App\Http\Middleware;

use App\Exceptions\ThinkTankApiException;
use App\Services\ThinkTank\ThinkTankAuthenticationStateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureThinkTankApiReady
{
    public function __construct(private readonly ThinkTankAuthenticationStateService $states) {}

    public function handle(Request $request, Closure $next): Response
    {
        $state = $this->states->state($request, $request->user());

        if ($state !== ThinkTankAuthenticationStateService::READY) {
            throw new ThinkTankApiException(
                $state,
                $state === ThinkTankAuthenticationStateService::PASSWORD_CHANGE_REQUIRED
                    ? 'A password change is required before using the portal.'
                    : 'Multi-factor verification is required before using the portal.',
                409,
                $this->states->summary($state),
            );
        }

        return $next($request);
    }
}
