<?php

namespace App\Http\Middleware;

use App\Models\SystemAuditLog;
use App\Models\User;
use App\Support\AuditorAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnforceAuditorReadOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->authenticatedUser($request);

        if (! $user?->isAuditor() || AuditorAccess::requestIsAllowed($request)) {
            return $next($request);
        }

        $this->recordBlockedAttempt($request, $user);

        abort(403, 'Auditor accounts are read-only. This action would change system data.');
    }

    private function authenticatedUser(Request $request): ?User
    {
        $user = $request->user();

        // The Think Tank API is deliberately cookie-only. Leave any supplied
        // Authorization header for its stateful boundary to reject with the
        // canonical AUTHORIZATION_HEADER_NOT_ALLOWED response. Other API
        // namespaces may legitimately use Sanctum bearer tokens, so resolve
        // those identities here before their route-level authentication runs.
        $thinkTankApiRejectsAuthorization = $request->is(
            'api/v1/think-tank',
            'api/v1/think-tank/*'
        ) && trim((string) $request->header('Authorization')) !== '';

        if (! $user && $request->is('api/*') && ! $thinkTankApiRejectsAuthorization) {
            $user = Auth::guard('sanctum')->user();
        }

        return $user instanceof User ? $user : null;
    }

    private function recordBlockedAttempt(Request $request, User $user): void
    {
        // Unit probes and other transient model instances have no durable
        // identity and must not create synthetic production audit rows.
        if (! $user->exists || ! $user->getKey()) {
            return;
        }

        try {
            SystemAuditLog::query()->create([
                'user_id' => $user->getKey(),
                'module' => 'security',
                'action' => 'auditor_write_blocked',
                'action_message' => 'Blocked a write attempt from a read-only Auditor account.',
                'description' => 'The system-wide Auditor read-only boundary rejected this request before its controller ran.',
                'method' => $request->method(),
                'url' => $request->url(),
                'route_name' => $request->route()?->getName(),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'status_code' => 403,
                'payload' => [
                    'path' => '/'.ltrim($request->path(), '/'),
                ],
            ]);
        } catch (Throwable $exception) {
            // Enforcement must remain fail-closed even if audit persistence is
            // temporarily unavailable.
            Log::warning('A blocked Auditor write attempt could not be recorded.', [
                'user_id' => $user->getKey(),
                'route_name' => $request->route()?->getName(),
                'exception' => $exception::class,
            ]);
        }
    }
}
