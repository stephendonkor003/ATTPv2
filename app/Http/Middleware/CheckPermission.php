<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions)
    {
        if (!auth()->check()) {
            abort(403, 'Unauthenticated.');
        }

        $resolvedPermissions = [];

        foreach ($permissions as $permissionGroup) {
            foreach (explode('|', $permissionGroup) as $permission) {
                $permission = trim($permission);

                if ($permission !== '') {
                    $resolvedPermissions[] = $permission;
                }
            }
        }

        if ($resolvedPermissions === []) {
            abort(403, 'No permission was provided for this action.');
        }

        $authorized = false;

        foreach ($resolvedPermissions as $permission) {
            if (auth()->user()->hasPermission($permission)) {
                $authorized = true;
                break;
            }
        }

        if (! $authorized) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
