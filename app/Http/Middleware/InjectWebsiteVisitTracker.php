<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectWebsiteVisitTracker
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldInject($request, $response)) {
            return $response;
        }

        $content = $response->getContent();
        if (! is_string($content) || stripos($content, '</body>') === false) {
            return $response;
        }

        $script = view('partials.website-visit-tracker', [
            'startUrl' => route('website-visit-tracker.start'),
            'heartbeatUrl' => route('website-visit-tracker.heartbeat'),
        ])->render();

        $response->setContent(str_ireplace('</body>', $script . "\n</body>", $content));

        return $response;
    }

    private function shouldInject(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || $request->expectsJson() || $request->ajax()) {
            return false;
        }

        if ($request->routeIs('website-visit-tracker.*')) {
            return false;
        }

        if ($request->is(
            'login',
            'register',
            'forgot-password',
            'reset-password*',
            'verify-email*',
            'confirm-password',
            'security/otp*',
            'security/password*'
        )) {
            return false;
        }

        if (! $response->isSuccessful()) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');

        return $contentType === '' || str_contains(strtolower($contentType), 'text/html');
    }
}
