<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdministrativeAssistant
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->isAdministrativeAssistant(),
            403,
            'This workspace is only available to Administrative Assistants.'
        );

        return $next($request);
    }
}
