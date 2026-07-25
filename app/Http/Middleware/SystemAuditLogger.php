<?php

namespace App\Http\Middleware;

use App\Models\SystemAuditLog;
use App\Support\IpGeo;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SystemAuditLogger
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $path = '/' . ltrim($request->path(), '/');
        $skipPrefixes = ['/assets', '/storage', '/favicon', '/css', '/js', '/build'];

        foreach ($skipPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $response;
            }
        }

        $payload = null;
        if (in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $excludedFields = [
                'password',
                'password_confirmation',
                'current_password',
                '_token',
            ];

            if (str_starts_with((string) $request->route()?->getName(), 'system.news.')) {
                $excludedFields = [...$excludedFields, 'body', 'cover_image', 'attachments'];
            }

            $payload = $request->except($excludedFields);

            if (str_starts_with((string) $request->route()?->getName(), 'system.news.')) {
                $payload['_news_content'] = [
                    'body_length' => strlen((string) $request->input('body')),
                    'cover_uploaded' => $request->hasFile('cover_image'),
                    'attachment_count' => count($request->file('attachments', [])),
                ];
            }
        }

        try {
            $country = IpGeo::countryForIp($request->ip());

            SystemAuditLog::create([
                'user_id' => optional($request->user())->id,
                'action' => 'request',
                'description' => null,
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'route_name' => optional($request->route())->getName(),
                'ip_address' => $request->ip(),
                'country' => $country,
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'status_code' => $response->getStatusCode(),
                'payload' => $payload,
            ]);
        } catch (Throwable $exception) {
            Log::warning('System request audit logging failed.', [
                'route_name' => optional($request->route())->getName(),
                'message' => $exception->getMessage(),
            ]);
        }

        return $response;
    }
}
