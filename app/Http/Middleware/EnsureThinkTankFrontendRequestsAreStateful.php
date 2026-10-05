<?php

namespace App\Http\Middleware;

use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

class EnsureThinkTankFrontendRequestsAreStateful extends EnsureFrontendRequestsAreStateful
{
    /**
     * The separate portal reaches this API through its same-origin Next.js
     * gateway, so Laravel's configured SameSite policy remains authoritative.
     * Sanctum's default middleware overwrites it with Lax on every request.
     */
    protected function configureSecureCookieSessions()
    {
        // Retain Sanctum's HttpOnly hardening, but do not replace the configured
        // SameSite value. Production verifies that value is Strict.
        config(['session.http_only' => true]);
    }
}
