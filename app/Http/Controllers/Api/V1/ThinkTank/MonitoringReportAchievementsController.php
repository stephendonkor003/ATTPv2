<?php

namespace App\Http\Controllers\Api\V1\ThinkTank;

use App\Exceptions\ThinkTankApiException;
use App\Http\Controllers\MeIndicatorAchievementController;
use App\Http\Controllers\ThinkTankPerformanceReportController;
use App\Models\ConsortiumThinkTank;
use App\Models\MeIndicatorAchievement;
use App\Models\MeIndicatorAchievementDisaggregation;
use App\Models\MePerformanceReport;
use App\Models\MePerformanceReportIndicatorResult;
use App\Models\MeRepositoryDocumentLink;
use App\Services\ThinkTankMonitoringApiService;
use App\Support\ThinkTankApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;

class MonitoringReportAchievementsController extends MeIndicatorAchievementController
{
    public function __construct(private readonly ThinkTankMonitoringApiService $api)
    {
        // The API route group applies stateful authentication, account state,
        // M&E-area access, granular permissions, CSRF, and no-store headers.
    }

    public function storeAchievement(Request $request, string $report, string $result): JsonResponse
    {
        $this->assertJsonObject($request);
        $this->rejectUnexpected($request, $this->achievementFields());

        return $this->mutate($request, $report, function (MePerformanceReport $ownedReport) use ($request, $result): void {
            $ownedResult = MePerformanceReportIndicatorResult::query()
                ->where('report_id', $ownedReport->id)
                ->whereKey($result)
                ->firstOrFail();
            parent::store($request, $ownedReport, $ownedResult);
        }, 'Achievement added.');
    }

    public function updateAchievement(
        Request $request,
        string $report,
        string $result,
        string $achievement,
    ): JsonResponse {
        $this->assertJsonObject($request);
        $this->rejectUnexpected($request, $this->achievementFields());

        return $this->mutate($request, $report, function (MePerformanceReport $ownedReport) use ($request, $result, $achievement): void {
            MePerformanceReportIndicatorResult::query()
                ->where('report_id', $ownedReport->id)
                ->whereKey($result)
                ->firstOrFail();
            $ownedAchievement = MeIndicatorAchievement::query()
                ->where('report_id', $ownedReport->id)
                ->where('report_indicator_result_id', $result)
                ->whereKey($achievement)
                ->firstOrFail();
            parent::update($request, $ownedReport, $ownedAchievement);
        }, 'Achievement updated.');
    }

    public function destroyAchievement(
        Request $request,
        string $report,
        string $result,
        string $achievement,
    ): JsonResponse {
        $this->assertJsonObject($request);
        $this->rejectUnexpected($request, ['lock_token']);

        return $this->mutate($request, $report, function (MePerformanceReport $ownedReport) use ($request, $result, $achievement): void {
            MePerformanceReportIndicatorResult::query()
                ->where('report_id', $ownedReport->id)
                ->whereKey($result)
                ->firstOrFail();
            $ownedAchievement = MeIndicatorAchievement::query()
                ->where('report_id', $ownedReport->id)
                ->where('report_indicator_result_id', $result)
                ->whereKey($achievement)
                ->firstOrFail();
            parent::destroy($request, $ownedReport, $ownedAchievement);
        }, 'Achievement removed.');
    }

    public function apiStoreBreakdown(Request $request, string $report, string $achievement): JsonResponse
    {
        $this->assertJsonObject($request);
        $this->rejectUnexpected($request, [
            'geographic_scope', 'country', 'rec', 'implementing_institution_type',
            'implementing_institution', 'priority_theme', 'gender', 'age_group',
            'stakeholder_category', 'beneficiary_count', 'lock_token',
        ]);

        return $this->mutate($request, $report, function (MePerformanceReport $ownedReport) use ($request, $achievement): void {
            $ownedAchievement = $this->achievement($ownedReport, $achievement);
            parent::storeBreakdown($request, $ownedReport, $ownedAchievement);
        }, 'Beneficiary breakdown added.');
    }

    public function apiDestroyBreakdown(
        Request $request,
        string $report,
        string $achievement,
        string $breakdown,
    ): JsonResponse {
        $this->assertJsonObject($request);
        $this->rejectUnexpected($request, ['lock_token']);

        return $this->mutate($request, $report, function (MePerformanceReport $ownedReport) use ($request, $achievement, $breakdown): void {
            $ownedAchievement = $this->achievement($ownedReport, $achievement);
            $ownedBreakdown = MeIndicatorAchievementDisaggregation::query()
                ->where('achievement_id', $ownedAchievement->id)
                ->whereKey($breakdown)
                ->firstOrFail();
            parent::destroyBreakdown($request, $ownedReport, $ownedAchievement, $ownedBreakdown);
        }, 'Beneficiary breakdown removed.');
    }

    public function apiStoreEvidence(Request $request, string $report, string $achievement): JsonResponse
    {
        $this->assertMultipart($request);
        $this->rejectUnexpected($request, [
            'document_title', 'document_description', 'evidence_file', 'lock_token',
        ]);

        return $this->mutate($request, $report, function (MePerformanceReport $ownedReport) use ($request, $achievement): void {
            $ownedAchievement = $this->achievement($ownedReport, $achievement);
            parent::storeDocument($request, $ownedReport, $ownedAchievement);
        }, 'Achievement evidence uploaded.');
    }

    public function apiUnlinkEvidence(
        Request $request,
        string $report,
        string $achievement,
        string $evidence,
    ): JsonResponse {
        $this->assertJsonObject($request);
        $this->rejectUnexpected($request, ['lock_token']);

        return $this->mutate($request, $report, function (MePerformanceReport $ownedReport) use ($request, $achievement, $evidence): void {
            $ownedAchievement = $this->achievement($ownedReport, $achievement);
            $link = MeRepositoryDocumentLink::query()
                ->where('linkable_type', MeIndicatorAchievement::class)
                ->where('linkable_id', $ownedAchievement->id)
                ->where('repository_item_id', $evidence)
                ->firstOrFail();
            parent::unlinkDocument($request, $ownedReport, $ownedAchievement, $link);
        }, 'Achievement evidence unlinked.');
    }

    private function mutate(
        Request $request,
        string $report,
        callable $callback,
        string $message,
    ): JsonResponse {
        $member = $this->memberFromRequest($request);

        DB::transaction(function () use ($request, $report, $member, $callback): void {
            $locked = MePerformanceReport::query()
                ->where('think_tank_member_id', $member->id)
                ->whereKey($report)
                ->lockForUpdate()
                ->firstOrFail();
            $this->api->assertLockToken($locked, $request->input('lock_token'));
            $callback($locked);
            $this->api->incrementLockVersion($locked);
        });

        $fresh = MePerformanceReport::query()
            ->where('think_tank_member_id', $member->id)
            ->whereKey($report)
            ->firstOrFail();

        return ThinkTankApiResponse::success($this->detail($request, $fresh), 200, $message);
    }

    /** @return array<string, mixed> */
    private function detail(Request $request, MePerformanceReport $report): array
    {
        $view = app(ThinkTankPerformanceReportController::class)->edit($request, $report);

        return $this->api->reportDetail($view->getData());
    }

    private function achievement(MePerformanceReport $report, string $achievement): MeIndicatorAchievement
    {
        return MeIndicatorAchievement::query()
            ->where('report_id', $report->id)
            ->whereKey($achievement)
            ->firstOrFail();
    }

    private function memberFromRequest(Request $request): ConsortiumThinkTank
    {
        $member = $request->attributes->get('think_tank.membership');
        abort_unless($member instanceof ConsortiumThinkTank, 403);

        return $member;
    }

    /** @return array<int, string> */
    private function achievementFields(): array
    {
        return [
            'title', 'description', 'achieved_on', 'geographic_scope', 'country',
            'rec', 'location', 'collaborating_institutions', 'priority_themes', 'lock_token',
        ];
    }

    /** @param array<int, string> $allowed */
    private function rejectUnexpected(Request $request, array $allowed): void
    {
        if ($request->query->count() !== 0) {
            throw ValidationException::withMessages(['query' => ['Query parameters are not allowed on mutations.']]);
        }
        $unexpected = array_diff(array_keys($request->all()), $allowed);
        if ($unexpected !== []) {
            throw ValidationException::withMessages(collect($unexpected)
                ->mapWithKeys(fn (string $field): array => [$field => ['This field is not allowed.']])
                ->all());
        }
        if (! is_string($request->input('lock_token')) || strlen($request->input('lock_token')) !== 64) {
            throw ValidationException::withMessages(['lock_token' => ['The current record version is required.']]);
        }
    }

    private function assertMultipart(Request $request): void
    {
        if (! str_starts_with(strtolower((string) $request->header('Content-Type')), 'multipart/form-data')) {
            throw new ThinkTankApiException(
                'MULTIPART_BODY_REQUIRED',
                'This operation requires a multipart/form-data request body.',
                415,
            );
        }
    }

    private function assertJsonObject(Request $request): void
    {
        if (! $request->isJson()) {
            throw new ThinkTankApiException(
                'JSON_BODY_REQUIRED',
                'This operation requires an application/json request body.',
                415,
            );
        }

        try {
            $document = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ThinkTankApiException('INVALID_JSON', 'The request body must contain valid JSON.', 400);
        }

        if (! is_object($document)) {
            throw new ThinkTankApiException('JSON_OBJECT_REQUIRED', 'The request body must be a JSON object.', 400);
        }
    }
}
