<?php

namespace App\Http\Controllers\Api\V1\ThinkTank;

use App\Http\Controllers\Controller;
use App\Models\ConsortiumThinkTank;
use App\Services\AttpMelResultsService;
use App\Services\ThinkTankMonitoringApiService;
use App\Services\ThinkTankMonitoringResultsScopeService;
use App\Support\ThinkTankApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MonitoringResultsController extends Controller
{
    public function __invoke(
        Request $request,
        AttpMelResultsService $results,
        ThinkTankMonitoringApiService $api,
        ThinkTankMonitoringResultsScopeService $resultsScope,
    ): JsonResponse {
        $unexpected = array_diff(array_keys($request->query->all()), [
            'project_year', 'reporting_year', 'reporting_period_id', 'include_archived',
        ]);
        if ($unexpected !== []) {
            throw ValidationException::withMessages(collect($unexpected)
                ->mapWithKeys(fn (string $field): array => [$field => ['This query parameter is not allowed.']])
                ->all());
        }
        $validated = $request->validate([
            'project_year' => ['nullable', 'integer', 'min:1', 'max:4'],
            'reporting_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'reporting_period_id' => ['nullable', 'uuid'],
            'include_archived' => ['nullable', 'boolean'],
        ]);
        $member = $request->attributes->get('think_tank.membership');
        abort_unless($member instanceof ConsortiumThinkTank, 403);

        $scope = $resultsScope->resolve(
            $member,
            isset($validated['project_year']) ? (int) $validated['project_year'] : null,
            isset($validated['reporting_year']) ? (int) $validated['reporting_year'] : null,
            $validated['reporting_period_id'] ?? null,
            filter_var($validated['include_archived'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );
        $filters = [
            'project_year' => $scope['projectYear'],
            'project_year_options' => $scope['projectYearOptions'],
            'reporting_year' => $scope['reportingYear'],
            'reporting_period_id' => $scope['reportingPeriodId'],
            'include_archived' => filter_var($validated['include_archived'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'indicator_ids' => $scope['indicatorIds'],
        ];

        return ThinkTankApiResponse::success($api->resultsPayload(
            $results->build($filters, (string) $member->id),
            $filters,
            $scope['periods'],
            (string) $member->name,
        ));
    }
}
