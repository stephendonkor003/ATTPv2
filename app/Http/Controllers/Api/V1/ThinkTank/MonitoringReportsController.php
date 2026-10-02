<?php

namespace App\Http\Controllers\Api\V1\ThinkTank;

use App\Exceptions\ThinkTankApiException;
use App\Http\Controllers\ThinkTankPerformanceReportController;
use App\Models\ConsortiumThinkTank;
use App\Models\MeDataCollectionAssignment;
use App\Models\MeIndicatorAchievement;
use App\Models\MeKnowledgeEvidenceItem;
use App\Models\MePerformanceReport;
use App\Models\MePerformanceReportDocument;
use App\Models\MeRepositoryDocumentLink;
use App\Services\ThinkTankMonitoringApiService;
use App\Support\ThinkTankApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class MonitoringReportsController extends ThinkTankPerformanceReportController
{
    public function __construct(private readonly ThinkTankMonitoringApiService $api)
    {
        parent::__construct();
    }

    public function apiIndex(Request $request): JsonResponse
    {
        $this->rejectUnexpectedQuery($request, ['status', 'q', 'page']);
        $request->validate([
            'status' => ['nullable', Rule::in([
                MePerformanceReport::STATUS_DRAFT,
                MePerformanceReport::STATUS_SUBMITTED,
                MePerformanceReport::STATUS_REVIEWED,
                MePerformanceReport::STATUS_VERIFIED,
                MePerformanceReport::STATUS_APPROVED,
                MePerformanceReport::STATUS_ARCHIVED,
            ])],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        return ThinkTankApiResponse::success(
            $this->api->reportRegister(parent::index($request)->getData())
        );
    }

    public function apiStore(Request $request): JsonResponse
    {
        $this->assertJsonObject($request);
        $this->rejectUnexpected($request, ['assignment_id']);
        $assignmentId = $request->validate(['assignment_id' => ['required', 'uuid']])['assignment_id'];
        $member = $this->memberFromRequest($request);

        DB::transaction(function () use ($request, $assignmentId, $member): void {
            MeDataCollectionAssignment::query()
                ->where('think_tank_member_id', $member->id)
                ->whereKey($assignmentId)
                ->lockForUpdate()
                ->firstOrFail();

            parent::store($request);
        });

        $report = MePerformanceReport::query()
            ->where('think_tank_member_id', $member->id)
            ->where('assignment_id', $assignmentId)
            ->firstOrFail();

        return ThinkTankApiResponse::success(
            $this->detail($request, $report),
            201,
            'Draft report created.',
        );
    }

    public function apiShow(Request $request, string $report): JsonResponse
    {
        $this->rejectUnexpectedQuery($request, []);

        return ThinkTankApiResponse::success(
            $this->detail($request, $this->report($request, $report))
        );
    }

    public function apiUpdate(Request $request, string $report): JsonResponse
    {
        $this->assertMultipart($request);
        $this->rejectUnexpected($request, [
            '_method', 'lock_token', 'indicator_results', 'key_achievements',
            'variance_explanation', 'means_of_verification_notes', 'overall_assessment',
            'performance_rating', 'conclusion', 'challenges_faced', 'mitigation_strategies',
            'lessons_learned', 'adaptive_management_actions', 'next_period_priorities',
            'document_names', 'documents',
        ]);
        $member = $this->memberFromRequest($request);

        DB::transaction(function () use ($request, $report, $member): void {
            $locked = MePerformanceReport::query()
                ->where('think_tank_member_id', $member->id)
                ->whereKey($report)
                ->lockForUpdate()
                ->firstOrFail();
            $this->api->assertLockToken($locked, $request->input('lock_token'));

            parent::update($request, $locked);
            $this->api->incrementLockVersion($locked);
        });

        return ThinkTankApiResponse::success(
            $this->detail($request, $this->report($request, $report)),
            200,
            'Draft report saved.',
        );
    }

    public function apiSubmit(Request $request, string $report): JsonResponse
    {
        $this->assertJsonObject($request);
        $this->rejectUnexpected($request, ['lock_token']);
        $request->validate(['lock_token' => ['required', 'string', 'size:64']]);
        $member = $this->memberFromRequest($request);

        DB::transaction(function () use ($request, $report, $member): void {
            $locked = MePerformanceReport::query()
                ->where('think_tank_member_id', $member->id)
                ->whereKey($report)
                ->lockForUpdate()
                ->firstOrFail();
            $this->api->assertLockToken($locked, $request->input('lock_token'));

            parent::submit($request, $locked);
            $this->api->incrementLockVersion($locked);
        });

        return ThinkTankApiResponse::success(
            $this->detail($request, $this->report($request, $report)),
            200,
            'Report submitted to the Secretariat/M&E Officer for review.',
        );
    }

    public function document(
        Request $request,
        string $report,
        string $document,
    ): Response {
        $this->rejectUnexpectedQuery($request, []);
        $ownedReport = $this->report($request, $report);
        $ownedDocument = MePerformanceReportDocument::query()
            ->where('report_id', $ownedReport->id)
            ->whereKey($document)
            ->firstOrFail();
        $response = parent::downloadDocument($request, $ownedReport, $ownedDocument);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; sandbox");
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    public function apiDestroyDocument(
        Request $request,
        string $report,
        string $document,
    ): JsonResponse {
        $this->assertJsonObject($request);
        $this->rejectUnexpected($request, ['lock_token']);
        $request->validate(['lock_token' => ['required', 'string', 'size:64']]);
        $member = $this->memberFromRequest($request);

        DB::transaction(function () use ($request, $report, $document, $member): void {
            $locked = MePerformanceReport::query()
                ->where('think_tank_member_id', $member->id)
                ->whereKey($report)
                ->lockForUpdate()
                ->firstOrFail();
            $this->api->assertLockToken($locked, $request->input('lock_token'));
            $ownedDocument = MePerformanceReportDocument::query()
                ->where('report_id', $locked->id)
                ->whereKey($document)
                ->firstOrFail();
            parent::destroyDocument($request, $locked, $ownedDocument);
            $this->api->incrementLockVersion($locked);
        });

        return ThinkTankApiResponse::success(
            $this->detail($request, $this->report($request, $report)),
            200,
            'Supporting document removed.',
        );
    }

    public function achievementEvidence(
        Request $request,
        string $report,
        string $achievement,
        string $evidence,
    ): BinaryFileResponse {
        $this->rejectUnexpectedQuery($request, []);
        $ownedReport = $this->report($request, $report);
        $ownedAchievement = MeIndicatorAchievement::query()
            ->where('report_id', $ownedReport->id)
            ->whereKey($achievement)
            ->firstOrFail();
        $link = MeRepositoryDocumentLink::query()
            ->where('linkable_type', MeIndicatorAchievement::class)
            ->where('linkable_id', $ownedAchievement->id)
            ->where('repository_item_id', $evidence)
            ->firstOrFail();
        $item = MeKnowledgeEvidenceItem::query()->whereKey($link->repository_item_id)->firstOrFail();
        $path = str_replace('\\', '/', trim((string) $item->file_path));
        abort_unless(
            $path !== ''
                && ! str_contains($path, '..')
                && (str_starts_with($path, 'me/knowledge-evidence/') || str_starts_with($path, 'me/performance-reports/'))
                && Storage::disk('local')->exists($path),
            404,
        );

        $response = response()->download(
            Storage::disk('local')->path($path),
            (string) ($item->original_filename ?: 'evidence'),
            [
                'Content-Type' => 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
            ],
            'attachment',
        );
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    /** @return array<string, mixed> */
    private function detail(Request $request, MePerformanceReport $report): array
    {
        return $this->api->reportDetail(parent::edit($request, $report)->getData());
    }

    private function report(Request $request, string $report): MePerformanceReport
    {
        return MePerformanceReport::query()
            ->where('think_tank_member_id', $this->memberFromRequest($request)->id)
            ->whereKey($report)
            ->firstOrFail();
    }

    private function memberFromRequest(Request $request): ConsortiumThinkTank
    {
        $member = $request->attributes->get('think_tank.membership');
        abort_unless($member instanceof ConsortiumThinkTank, 403);

        return $member;
    }

    /** @param array<int, string> $allowed */
    private function rejectUnexpected(Request $request, array $allowed): void
    {
        $this->rejectUnexpectedQuery($request, []);
        $unexpected = array_diff(array_keys($request->all()), $allowed);
        if ($unexpected !== []) {
            throw ValidationException::withMessages(collect($unexpected)
                ->mapWithKeys(fn (string $field): array => [$field => ['This field is not allowed.']])
                ->all());
        }
    }

    /** @param array<int, string> $allowed */
    private function rejectUnexpectedQuery(Request $request, array $allowed): void
    {
        $unexpected = array_diff(array_keys($request->query->all()), $allowed);
        if ($unexpected !== []) {
            throw ValidationException::withMessages(collect($unexpected)
                ->mapWithKeys(fn (string $field): array => [$field => ['This query parameter is not allowed.']])
                ->all());
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
