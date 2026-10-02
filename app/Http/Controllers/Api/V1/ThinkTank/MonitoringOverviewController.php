<?php

namespace App\Http\Controllers\Api\V1\ThinkTank;

use App\Http\Controllers\Controller;
use App\Models\ConsortiumThinkTank;
use App\Models\MePerformanceReport;
use App\Notifications\MeReportingNotification;
use App\Services\AttpMelResultsService;
use App\Services\ThinkTankMeAssignmentService;
use App\Services\ThinkTankMonitoringApiService;
use App\Services\ThinkTankMonitoringResultsScopeService;
use App\Support\ThinkTankApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MonitoringOverviewController extends Controller
{
    public function __invoke(
        Request $request,
        ThinkTankMeAssignmentService $assignments,
        AttpMelResultsService $results,
        ThinkTankMonitoringApiService $api,
        ThinkTankMonitoringResultsScopeService $resultsScope,
    ): JsonResponse {
        if ($request->query->count() !== 0) {
            throw ValidationException::withMessages(['query' => ['Query parameters are not allowed.']]);
        }
        $request->validate([]);
        $member = $request->attributes->get('think_tank.membership');
        abort_unless($member instanceof ConsortiumThinkTank, 403);
        $user = $request->user();
        $canViewAssignments = $user->can('think_tank.me.view') || $user->can('think_tank.me.submit');
        $assignmentOverview = [
            'summary' => ['total' => 0, 'open' => 0, 'upcoming' => 0, 'submitted' => 0, 'closed' => 0, 'action_required' => 0],
            'priority' => collect(),
        ];
        $resultPayload = $this->emptyResultsPayload((string) $member->name);
        if ($canViewAssignments) {
            $assignmentOverview = $assignments->overview(
                $member,
                [],
                $user->can('think_tank.me.submit'),
                6,
            );
            $scope = $resultsScope->resolve(
                $member,
                null,
                null,
                null,
            );
            $resultFilters = [
                'project_year' => $scope['projectYear'],
                'project_year_options' => $scope['projectYearOptions'],
                'reporting_year' => null,
                'reporting_period_id' => null,
                'indicator_ids' => $scope['indicatorIds'],
            ];
            $resultPayload = $api->resultsPayload(
                $results->build($resultFilters, (string) $member->id),
                $resultFilters,
                $scope['periods'],
                (string) $member->name,
            );
        }
        $canViewReports = $user->can('think_tank.me.reports.view')
            || $user->can('think_tank.me.reports.manage')
            || $user->can('think_tank.me.reports.submit');
        $canViewNotifications = $user->can('think_tank.me.notifications.view');
        $statuses = [
            MePerformanceReport::STATUS_DRAFT,
            MePerformanceReport::STATUS_SUBMITTED,
            MePerformanceReport::STATUS_REVIEWED,
            MePerformanceReport::STATUS_VERIFIED,
            MePerformanceReport::STATUS_APPROVED,
            MePerformanceReport::STATUS_ARCHIVED,
        ];
        $reportSummary = collect($statuses)->mapWithKeys(fn (string $status): array => [$status => 0])->all();
        $recentReports = collect();
        if ($canViewReports) {
            $reportQuery = MePerformanceReport::query()->where('think_tank_member_id', $member->id);
            $reportSummary = collect($statuses)->mapWithKeys(fn (string $status): array => [
                $status => (clone $reportQuery)->where('status', $status)->count(),
            ])->all();
            $recentReports = (clone $reportQuery)->with([
                'form:id,code,title',
                'projectComponent:id,project_id,name',
                'responsibleDirectorate:id,name,code',
            ])->withCount(['indicatorResults', 'documents'])->latest('updated_at')->limit(5)->get();
        }
        $unreadNotificationCount = 0;
        $recentNotifications = collect();
        if ($canViewNotifications) {
            $notificationQuery = $user->notifications()->where('type', MeReportingNotification::class);
            $unreadNotificationCount = (clone $notificationQuery)->whereNull('read_at')->count();
            $recentNotifications = (clone $notificationQuery)->latest()->limit(5)->get();
        }

        return ThinkTankApiResponse::success([
            'tenant' => ['id' => (string) $member->id, 'name' => (string) $member->name],
            'permissions' => [
                'canView' => $canViewAssignments,
                'canSubmit' => $user->can('think_tank.me.submit'),
                'canViewReports' => $canViewReports,
                'canManageReports' => $user->can('think_tank.me.reports.manage'),
                'canSubmitReports' => $user->can('think_tank.me.reports.submit'),
                'canViewNotifications' => $canViewNotifications,
            ],
            'assignments' => [
                'summary' => $api->assignmentSummary($assignmentOverview['summary']),
                'priority' => collect($assignmentOverview['priority'])
                    ->map(fn (array $card): array => $api->assignmentCard($card))->values()->all(),
            ],
            'results' => $resultPayload,
            'reports' => [
                'summary' => $reportSummary,
                'recent' => $recentReports->map(fn (MePerformanceReport $report): array => $api->reportListItem($report))->all(),
            ],
            'notifications' => [
                'unreadCount' => $unreadNotificationCount,
                'recent' => $recentNotifications->map(fn ($notification): array => $api->notificationItem($notification))->all(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function emptyResultsPayload(string $memberName): array
    {
        return [
            'framework' => null,
            'scopeLabel' => $memberName.' - approved results are not available for this access level',
            'summary' => [
                'indicatorCount' => 0, 'pdoCount' => 0, 'approvedResultCount' => 0,
                'evidenceCount' => 0, 'verifiedEvidenceCount' => 0, 'averageAchievement' => null,
                'onTrackCount' => 0, 'attentionCount' => 0, 'reportedIndicatorCount' => 0,
                'notReportedCount' => 0, 'onTrackRate' => null, 'averageCompleteness' => 0,
                'evidenceVerificationRate' => null,
            ],
            'analytics' => [
                'performance' => [], 'components' => [], 'attainment' => [],
                'gender' => ['female' => 0, 'male' => 0],
                'trends' => ['up' => 0, 'down' => 0, 'flat' => 0, 'changed' => 0, 'none' => 0],
                'attention' => [],
                'quality' => ['reportingCompleteness' => 0, 'evidenceVerification' => null],
            ],
            'rows' => [],
            'filters' => ['projectYear' => 1, 'reportingYear' => null, 'reportingPeriodId' => null],
            'options' => ['projectYears' => [1, 2, 3, 4], 'periods' => [], 'reportingYears' => []],
        ];
    }
}
