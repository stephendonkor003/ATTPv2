<?php

namespace App\Http\Controllers\Api\V1\ThinkTank;

use App\Http\Controllers\Controller;
use App\Notifications\MeReportingNotification;
use App\Services\ThinkTankMonitoringApiService;
use App\Support\ThinkTankApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MonitoringNotificationsController extends Controller
{
    private const CATEGORIES = [
        'me_collection', 'me_submission', 'reporting_period', 'performance_report',
        'mission_report', 'deadline', 'corrective_action', 'mov_validation',
    ];

    private const SEVERITIES = ['danger', 'warning', 'info', 'success', 'secondary'];

    public function index(Request $request, ThinkTankMonitoringApiService $api): JsonResponse
    {
        $unexpected = array_diff(array_keys($request->query->all()), [
            'state', 'category', 'severity', 'q', 'page',
        ]);
        if ($unexpected !== []) {
            throw ValidationException::withMessages(collect($unexpected)
                ->mapWithKeys(fn (string $field): array => [$field => ['This query parameter is not allowed.']])
                ->all());
        }
        $filters = $request->validate([
            'state' => ['nullable', Rule::in(['all', 'unread', 'read'])],
            'category' => ['nullable', Rule::in(self::CATEGORIES)],
            'severity' => ['nullable', Rule::in(self::SEVERITIES)],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        return ThinkTankApiResponse::success($api->notificationsPayload($request->user(), $filters));
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $this->assertEmptyJsonObject($request);
        $item = $request->user()->notifications()
            ->where('type', MeReportingNotification::class)
            ->whereKey($notification)
            ->firstOrFail();
        $item->markAsRead();

        return ThinkTankApiResponse::success([
            'id' => (string) $item->id,
            'readAt' => $item->fresh()->read_at?->toIso8601String(),
        ], 200, 'Notification marked as read.');
    }

    public function readAll(Request $request): JsonResponse
    {
        $this->assertEmptyJsonObject($request);
        $updated = $request->user()->unreadNotifications()
            ->where('type', MeReportingNotification::class)
            ->update(['read_at' => now()]);

        return ThinkTankApiResponse::success(
            ['updated' => (int) $updated],
            200,
            $updated ? 'Reporting notifications marked as read.' : 'There were no unread reporting notifications.',
        );
    }

    private function assertEmptyJsonObject(Request $request): void
    {
        if ($request->query->count() !== 0) {
            throw ValidationException::withMessages(['query' => ['Query parameters are not allowed on mutations.']]);
        }
        abort_unless($request->isJson(), 415, 'An application/json request body is required.');
        $document = json_decode($request->getContent());
        abort_unless(is_object($document) && get_object_vars($document) === [], 422, 'No request fields are allowed.');
    }
}
