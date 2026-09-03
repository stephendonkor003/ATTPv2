<?php

namespace App\Http\Middleware;

use App\Exceptions\ThinkTankApiException;
use App\Services\ThinkTank\ThinkTankAccountAccessService;
use App\Services\ThinkTank\ThinkTankProductionSecurityService;
use App\Services\ThinkTank\ThinkTankSessionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureThinkTankUser
{
    public function __construct(
        private readonly ThinkTankAccountAccessService $accounts,
        private readonly ThinkTankSessionService $sessions,
        private readonly ThinkTankProductionSecurityService $productionSecurity,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ($user->isSuperAdmin() || $user->isAdmin())) {
            return $next($request);
        }

        if (! $user || $user->user_type !== 'think_tank') {
            abort(403, 'Access denied. This area is restricted to think tank portal users only.');
        }

        try {
            $this->sessions->assertProductionSecurityStores();
            $this->productionSecurity->assertRuntimeConfiguration();
            $membership = $this->accounts->membership($user);

            if (! $this->sessions->hasValidCurrentSession($user, $request)) {
                throw $this->accounts->unavailable();
            }
        } catch (ThinkTankApiException) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'This Think Tank portal session is no longer available. Please sign in again.']);
        }

        $request->attributes->set('think_tank.membership', $membership);

        return $next($request);
    }
}
