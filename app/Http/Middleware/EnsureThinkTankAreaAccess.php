<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureThinkTankAreaAccess
{
    public function handle(Request $request, Closure $next, string ...$areas): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Unauthenticated.');
        }

        $areas = collect($areas)
            ->flatMap(fn (string $area): array => explode('|', $area))
            ->map(fn (string $area): string => trim($area))
            ->filter()
            ->values();

        if ($areas->isEmpty()) {
            abort(403, 'No think tank portal area was provided.');
        }

        if (! $areas->contains(fn (string $area): bool => $user->canAccessThinkTankArea($area))) {
            abort(403, 'Your think tank access level does not include this area.');
        }

        return $next($request);
    }
}
