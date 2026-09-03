<?php

namespace App\Http\Controllers\Api\V1\ThinkTank;

use App\Http\Resources\ThinkTankViewerResource;
use App\Support\ThinkTankApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends ThinkTankApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->validateOnly($request, []);

        return ThinkTankApiResponse::success(
            (new ThinkTankViewerResource($request->user()))->resolve($request)
        );
    }
}
