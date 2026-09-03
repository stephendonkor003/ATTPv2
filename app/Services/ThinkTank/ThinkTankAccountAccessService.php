<?php

namespace App\Services\ThinkTank;

use App\Exceptions\ThinkTankApiException;
use App\Models\ConsortiumThinkTank;
use App\Models\User;

class ThinkTankAccountAccessService
{
    public function membership(User $user): ConsortiumThinkTank
    {
        if ($user->user_type !== 'think_tank') {
            throw $this->unavailable();
        }

        if ($user->is_blacklisted || $user->hasActiveLoginBlock()) {
            throw $this->unavailable();
        }

        $membership = $user->relationLoaded('assignedThinkTankMembership')
            ? $user->assignedThinkTankMembership
            : $user->assignedThinkTankMembership()->first();

        if (! $membership || $membership->status !== 'active') {
            throw $this->unavailable();
        }

        if (! array_key_exists(
            trim((string) $user->think_tank_access_level),
            User::THINK_TANK_ACCESS_LEVELS
        )) {
            throw $this->unavailable();
        }

        return $membership->loadMissing('consortium');
    }

    public function unavailable(): ThinkTankApiException
    {
        return new ThinkTankApiException(
            'ACCOUNT_UNAVAILABLE',
            'This think tank portal account is not currently available.',
            403,
            ['state' => 'UNAUTHENTICATED', 'next_action' => 'LOGIN'],
        );
    }
}
