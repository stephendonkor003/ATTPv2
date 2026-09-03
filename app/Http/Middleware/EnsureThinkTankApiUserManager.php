<?php

namespace App\Http\Middleware;

use App\Exceptions\ThinkTankApiException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureThinkTankApiUserManager
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $isAdministrator = $user->think_tank_access_level === User::THINK_TANK_ACCESS_ADMIN;

        if (! $isAdministrator || ! $user->hasPermission('think_tank.team.manage')) {
            throw new ThinkTankApiException(
                'FORBIDDEN',
                'You do not have permission to manage think tank portal users.',
                403,
            );
        }

        return $next($request);
    }
}
