<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureMemberState
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || $user->user_type !== 'member_state') {
            abort(403, 'Access denied. Member state portal only.');
        }

        if (!$user->member_state_id) {
            abort(403, 'Your account is not linked to a member state.');
        }

        return $next($request);
    }
}
