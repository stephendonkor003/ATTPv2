<?php

namespace App\Http\Controllers\Api\V1\ThinkTank;

use App\Models\User;
use App\Support\ThinkTankApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccessLevelController extends ThinkTankApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->validateOnly($request, []);

        return ThinkTankApiResponse::success(collect(User::THINK_TANK_ACCESS_LEVELS)
            ->map(fn (string $label, string $key): array => [
                'key' => $key,
                'label' => $label,
            ])
            ->values()
            ->all());
    }
}
