<?php

namespace App\Http\Middleware;

use App\Services\ApiSync\ApiSyncPairingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiSync
{
    public function __construct(private readonly ApiSyncPairingService $pairings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $pairing = $this->pairings->authenticate($request);
        $request->attributes->set('api_sync_pairing', $pairing);

        return $next($request);
    }
}
