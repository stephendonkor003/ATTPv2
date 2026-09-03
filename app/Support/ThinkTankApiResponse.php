<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

final class ThinkTankApiResponse
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public static function success(mixed $data, int $status = 200, ?string $message = null, array $extra = []): JsonResponse
    {
        $payload = ['data' => $data];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()->json([...$payload, ...$extra], $status);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function error(string $code, string $message, int $status, array $extra = []): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'code' => $code,
            ...$extra,
        ], $status);
    }

    public static function isPortalRequest(): bool
    {
        return request()->is('api/v1/think-tank', 'api/v1/think-tank/*');
    }
}
