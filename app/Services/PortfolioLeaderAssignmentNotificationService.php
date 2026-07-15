<?php

namespace App\Services;

use App\Jobs\NotifyPortfolioLeaderAssigned;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

class PortfolioLeaderAssignmentNotificationService
{
    public function notify(User $user, Sector $portfolio, string $roleName, ?string $plainPassword = null): bool
    {
        try {
            NotifyPortfolioLeaderAssigned::dispatch($portfolio->id, $user->id, $roleName, $plainPassword);

            return true;
        } catch (Throwable $exception) {
            Log::warning('Portfolio leader assignment notification queue dispatch failed; falling back to immediate send.', [
                'portfolio_id' => $portfolio->id,
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            NotifyPortfolioLeaderAssigned::dispatchSync($portfolio->id, $user->id, $roleName, $plainPassword);

            return true;
        } catch (Throwable $exception) {
            Log::warning('Portfolio leader assignment notification fallback failed.', [
                'portfolio_id' => $portfolio->id,
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
