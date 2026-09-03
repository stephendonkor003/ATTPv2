<?php

namespace App\Http\Middleware;

use App\Exceptions\ThinkTankApiException;
use App\Services\ThinkTank\ThinkTankProductionSecurityService;
use App\Services\ThinkTank\ThinkTankSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureThinkTankApiStatefulSession
{
    public function __construct(
        private readonly ThinkTankSessionService $sessions,
        private readonly ThinkTankProductionSecurityService $productionSecurity,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->sessions->assertProductionSecurityStores();
        $this->productionSecurity->assertRuntimeConfiguration();

        if (trim((string) $request->header('Authorization', '')) !== '') {
            throw new ThinkTankApiException(
                'AUTHORIZATION_HEADER_NOT_ALLOWED',
                'This portal accepts first-party session cookies only.',
                400,
            );
        }

        if (! $request->hasSession()) {
            throw new ThinkTankApiException(
                'STATEFUL_SESSION_REQUIRED',
                'This endpoint requires an allowed first-party portal origin.',
                400,
            );
        }

        return $next($request);
    }
}
