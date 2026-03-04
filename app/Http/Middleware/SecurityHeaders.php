<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Prevent clickjacking
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Prevent MIME sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // XSS protection (legacy browsers)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer protection
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Allow browser features needed by the app
        $response->headers->set(
            'Permissions-Policy',
            'camera=(self), microphone=(self), geolocation=(self)'
        );

        // Content Security Policy
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self';
            script-src 'self' 'unsafe-inline' 'unsafe-eval' https:;
            style-src 'self' 'unsafe-inline' https:;
            img-src 'self' data: https:;
            font-src 'self' data: https:;
            connect-src 'self' https:;
            media-src 'self' blob:;
            frame-ancestors 'self';
            base-uri 'self';
            form-action 'self';"
        );

        // Enforce HTTPS
        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // Prevent caching on sensitive routes
        if ($request->routeIs('login', 'security.*')) {
            $response->headers->set(
                'Cache-Control',
                'no-store, no-cache, must-revalidate, max-age=0'
            );
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}
