<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class ThinkTankUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $accessLevel = trim((string) $this->think_tank_access_level);
        $accountStatus = $this->is_blacklisted
            ? 'blacklisted'
            : ($this->hasActiveLoginBlock() ? 'disabled' : 'active');

        return [
            'id' => (string) $this->getKey(),
            'name' => (string) $this->name,
            'email' => (string) $this->email,
            'access_level' => $accessLevel,
            'access_label' => User::THINK_TANK_ACCESS_LEVELS[$accessLevel] ?? 'Unassigned',
            'account_status' => $accountStatus,
            'is_disabled' => $accountStatus === 'disabled',
            'invitation_pending' => $this->email_verified_at === null || (bool) $this->must_change_password,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
