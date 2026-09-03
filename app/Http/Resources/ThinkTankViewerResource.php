<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class ThinkTankViewerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $membership = $request->attributes->get('think_tank.membership')
            ?: $this->assignedThinkTankMembership()?->with('consortium')->first();
        $accessLevel = trim((string) $this->think_tank_access_level);

        $permissions = array_values($this->thinkTankPermissionNames());

        if ($accessLevel === User::THINK_TANK_ACCESS_ADMIN) {
            $permissions[] = 'think_tank.users.manage';
        }

        return [
            'id' => (string) $this->getKey(),
            'name' => (string) $this->name,
            'email' => (string) $this->email,
            'tenant' => $membership ? [
                'id' => (string) $membership->getKey(),
                'name' => (string) $membership->name,
                'status' => (string) $membership->status,
                'consortium' => $membership->consortium ? [
                    'id' => (string) $membership->consortium->getKey(),
                    'name' => (string) $membership->consortium->name,
                ] : null,
            ] : null,
            'access' => [
                'key' => $accessLevel,
                'label' => User::THINK_TANK_ACCESS_LEVELS[$accessLevel] ?? 'Unassigned',
                'permissions' => array_values(array_unique($permissions)),
            ],
        ];
    }
}
