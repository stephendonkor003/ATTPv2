<?php

namespace App\Http\Controllers\Api\V1\ThinkTank;

use App\Exceptions\ThinkTankApiException;
use App\Http\Controllers\ThinkTankMeDataController;
use App\Models\ConsortiumThinkTank;
use App\Models\MeDataCollectionAssignment;
use App\Models\MeDataSubmission;
use App\Services\ThinkTankMeAssignmentService;
use App\Services\ThinkTankMonitoringApiService;
use App\Support\ThinkTankApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MonitoringAssignmentsController extends ThinkTankMeDataController
{
    public function __construct(
        ThinkTankMeAssignmentService $assignments,
        private readonly ThinkTankMonitoringApiService $api,
    ) {
        parent::__construct($assignments);
    }

    public function index(Request $request): JsonResponse
    {
        $this->rejectUnexpectedQuery($request, ['state', 'q']);
        $filters = $request->validate([
            'state' => ['nullable', Rule::in(['all', 'open', 'upcoming', 'submitted', 'closed'])],
            'q' => ['nullable', 'string', 'max:120'],
        ]);
        $view = parent::index($request);

        $data = $view->getData();

        return ThinkTankApiResponse::success($this->api->assignmentRegister(
            ['groups' => $data['assignmentGroups'], 'summary' => $data['summary']],
            (string) ($filters['state'] ?? 'all'),
            trim((string) ($filters['q'] ?? '')),
        ));
    }

    public function apiShow(Request $request, string $assignment): JsonResponse
    {
        $this->rejectUnexpectedQuery($request, []);

        return ThinkTankApiResponse::success(
            $this->detail($request, $this->assignment($request, $assignment))
        );
    }

    public function draft(Request $request, string $assignment): JsonResponse
    {
        $this->assertMultipart($request);
        $this->rejectUnexpected($request, ['answers', 'notes', 'lock_token', '_method']);
        $member = $this->memberFromRequest($request);

        DB::transaction(function () use ($request, $assignment, $member): void {
            $locked = MeDataCollectionAssignment::query()
                ->where('think_tank_member_id', $member->id)
                ->whereKey($assignment)
                ->lockForUpdate()
                ->firstOrFail();
            $submission = MeDataSubmission::query()
                ->where('assignment_id', $locked->id)
                ->lockForUpdate()
                ->first();
            if ($submission) {
                $this->api->assertLockToken($submission, $request->input('lock_token'));
            }

            parent::saveDraft($request, $locked);
            if ($submission) {
                $this->api->incrementLockVersion($submission);
            }
        });

        return ThinkTankApiResponse::success(
            $this->detail($request, $this->assignment($request, $assignment)),
            200,
            'Your draft has been saved.',
        );
    }

    public function apiSubmit(Request $request, string $assignment): JsonResponse
    {
        $this->assertMultipart($request);
        $this->rejectUnexpected($request, ['answers', 'notes', 'lock_token', '_method']);
        $member = $this->memberFromRequest($request);

        DB::transaction(function () use ($request, $assignment, $member): void {
            $locked = MeDataCollectionAssignment::query()
                ->where('think_tank_member_id', $member->id)
                ->whereKey($assignment)
                ->lockForUpdate()
                ->firstOrFail();
            $submission = MeDataSubmission::query()
                ->where('assignment_id', $locked->id)
                ->lockForUpdate()
                ->first();
            if ($submission) {
                $this->api->assertLockToken($submission, $request->input('lock_token'));
            }

            parent::submit($request, $locked);
            if ($submission) {
                $this->api->incrementLockVersion($submission);
            }
        });

        return ThinkTankApiResponse::success(
            $this->detail($request, $this->assignment($request, $assignment)),
            200,
            'Your M&E data has been submitted for review.',
        );
    }

    public function attachment(
        Request $request,
        string $assignment,
        string $attachment,
    ): BinaryFileResponse {
        $this->rejectUnexpectedQuery($request, []);
        $owned = $this->assignment($request, $assignment);
        $view = parent::show($request, $owned)->getData();
        $match = null;
        $matchFieldId = null;

        foreach (collect($view['attachments']) as $fieldId => $files) {
            foreach (collect($files) as $file) {
                if ($this->api->attachmentId($file, (string) $owned->id, (string) $fieldId) === $attachment) {
                    $match = $file;
                    $matchFieldId = (string) $fieldId;
                    break 2;
                }
            }
        }

        abort_unless(is_array($match), 404);
        $path = str_replace('\\', '/', trim((string) $match['path']));
        $submissionId = (string) ($view['submission']?->id ?: '');
        $expectedPrefix = 'me-data/submissions/'.$submissionId.'/'.$matchFieldId.'/';
        abort_unless(
            $submissionId !== ''
                && $path !== ''
                && ! str_contains($path, '..')
                && str_starts_with($path, $expectedPrefix)
                && Storage::disk('local')->exists($path),
            404,
        );

        $response = response()->download(
            Storage::disk('local')->path($path),
            (string) ($match['original_name'] ?? 'attachment'),
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
    private function detail(Request $request, MeDataCollectionAssignment $assignment): array
    {
        return $this->api->assignmentDetail(parent::show($request, $assignment)->getData());
    }

    private function assignment(Request $request, string $assignment): MeDataCollectionAssignment
    {
        return MeDataCollectionAssignment::query()
            ->where('think_tank_member_id', $this->memberFromRequest($request)->id)
            ->whereKey($assignment)
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
}
