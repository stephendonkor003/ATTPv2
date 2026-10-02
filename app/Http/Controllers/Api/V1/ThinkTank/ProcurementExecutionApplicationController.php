<?php

namespace App\Http\Controllers\Api\V1\ThinkTank;

use App\Exceptions\ThinkTankApiException;
use App\Models\ConsortiumThinkTank;
use App\Models\DynamicForm;
use App\Models\DynamicFormField;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Models\Procurement;
use App\Models\User;
use App\Services\DynamicProcurementSubmissionFileService;
use App\Services\DynamicProcurementSubmissionValidation;
use App\Services\ThinkTankProcurementExecutionService;
use App\Support\DynamicProcurementFormCatalog;
use App\Support\ThinkTankApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ProcurementExecutionApplicationController extends ThinkTankApiController
{
    public function __construct(
        private readonly DynamicProcurementSubmissionFileService $submissionFiles,
        private readonly DynamicProcurementSubmissionValidation $submissionValidation,
        private readonly ThinkTankProcurementExecutionService $executions,
    ) {}

    public function index(Request $request, string $execution): JsonResponse
    {
        $filters = $this->validateOnly($request, [
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $procurement = $this->ownedExecution($request, $execution);
        $form = $this->authoritativeForm($procurement);
        $base = $this->submissionQuery($procurement, $form);
        $uploadFieldKeys = $this->uploadFieldKeys($form);
        $statusCounts = (clone $base)
            ->pluck('status')
            ->map(fn (mixed $status): string => $this->statusKey($status))
            ->countBy()
            ->sortKeys();

        $query = (clone $base)->with([
            'submitter:id,name,email',
            'values:id,submission_id,field_key,value',
        ]);
        $search = Str::lower(trim((string) ($filters['q'] ?? '')));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $nested) use ($like): void {
                $nested
                    ->whereRaw('LOWER(COALESCE(procurement_submission_code, ?)) LIKE ?', ['', $like])
                    ->orWhereHas('values', fn (Builder $values): Builder => $values
                        ->whereIn('field_key', ['official_name', 'official_email'])
                        ->whereRaw('LOWER(COALESCE(value, ?)) LIKE ?', ['', $like]))
                    ->orWhereExists(function ($submitters) use ($like): void {
                        $submitters
                            ->selectRaw('1')
                            ->from('users')
                            ->whereRaw('CAST(users.id AS VARCHAR) = form_submissions.submitted_by')
                            ->where(function ($identity) use ($like): void {
                                $identity
                                    ->whereRaw('LOWER(COALESCE(users.name, ?)) LIKE ?', ['', $like])
                                    ->orWhereRaw('LOWER(COALESCE(users.email, ?)) LIKE ?', ['', $like]);
                            });
                    });
            });
        }
        if (filled($filters['status'] ?? null)) {
            if ($filters['status'] === 'unknown') {
                $query->where(function (Builder $statuses): void {
                    $statuses->whereNull('status')->orWhereRaw('TRIM(status) = ?', ['']);
                });
            } else {
                $query->where('status', $filters['status']);
            }
        }

        $paginator = $query
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();
        $total = (int) $statusCounts->sum();
        $withFiles = 0;
        if ($uploadFieldKeys !== []) {
            (clone $base)
                ->with(['values' => fn ($values) => $values
                    ->select(['id', 'submission_id', 'field_key', 'value'])
                    ->whereIn('field_key', $uploadFieldKeys)])
                ->whereHas('values', fn (Builder $values): Builder => $values
                    ->whereIn('field_key', $uploadFieldKeys)
                    ->whereNotNull('value')
                    ->where('value', '<>', ''))
                ->chunkById(200, function (Collection $submissions) use ($form, &$withFiles): void {
                    foreach ($submissions as $submission) {
                        if ($submission instanceof FormSubmission
                            && $this->hasReadableFile($form, $submission)) {
                            $withFiles++;
                        }
                    }
                });
        }

        return ThinkTankApiResponse::success([
            'permissions' => $this->permissions($request->user()),
            'execution' => $this->executionDto($procurement),
            'summary' => [
                'total' => $total,
                'currentPublication' => (clone $base)
                    ->where('publication_version', max(1, (int) $procurement->publication_version))
                    ->count(),
                'submitted' => (int) $statusCounts->get(FormSubmission::STATUS_SUBMITTED, 0),
                'revisionRequested' => (int) $statusCounts->get(FormSubmission::STATUS_REVISION_REQUESTED, 0),
                'withdrawn' => (int) $statusCounts->get(FormSubmission::STATUS_WITHDRAWN, 0),
                'withFiles' => $withFiles,
                'statusCounts' => $statusCounts->all(),
            ],
            'applications' => $paginator->getCollection()
                ->map(fn (FormSubmission $submission): array => $this->applicationDto(
                    $submission,
                    $form,
                ))
                ->values()
                ->all(),
            'filters' => [
                'query' => (string) ($filters['q'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
            ],
            'options' => [
                'statuses' => $statusCounts
                    ->map(fn (int $count, string $status): array => [
                        'value' => $status,
                        'label' => $this->statusLabel($status),
                        'count' => $count,
                    ])
                    ->values()
                    ->all(),
            ],
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function show(Request $request, string $execution, string $submission): JsonResponse
    {
        $this->validateOnly($request, []);
        $procurement = $this->ownedExecution($request, $execution);
        $form = $this->authoritativeForm($procurement);
        $record = $this->submissionQuery($procurement, $form)
            ->with(['submitter:id,name,email', 'values:id,submission_id,field_key,value'])
            ->whereKey($submission)
            ->firstOrFail();

        return ThinkTankApiResponse::success([
            'permissions' => $this->permissions($request->user()),
            'execution' => $this->executionDto($procurement),
            'application' => [
                ...$this->applicationDto($record, $form),
                'vendorResponse' => $this->nullableText($record->vendor_response),
                'withdrawalReason' => $this->nullableText($record->withdrawal_reason),
            ],
            'fields' => $form->fields
                ->map(fn (DynamicFormField $field): array => $this->fieldDto(
                    $procurement,
                    $record,
                    $field,
                    $record->values->firstWhere('field_key', $field->field_key),
                ))
                ->values()
                ->all(),
        ]);
    }

    public function download(
        Request $request,
        string $execution,
        string $submission,
        string $value,
    ): StreamedResponse {
        $this->validateOnly($request, []);
        $procurement = $this->ownedExecution($request, $execution);
        $form = $this->authoritativeForm($procurement);
        $record = $this->submissionQuery($procurement, $form)
            ->whereKey($submission)
            ->firstOrFail();
        $storedValue = FormSubmissionValue::query()
            ->where('submission_id', $record->id)
            ->whereKey($value)
            ->firstOrFail();
        $field = $form->fields->firstWhere('field_key', $storedValue->field_key);
        abort_unless(
            $field instanceof DynamicFormField
                && in_array($field->field_type, DynamicProcurementFormCatalog::UPLOAD_TYPES, true),
            404,
        );

        [$disk, $path, $extension] = $this->storedAttachment($field, $storedValue);

        return $disk->download(
            $path,
            $this->attachmentName($field, $extension),
            [
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
            ],
        );
    }

    private function ownedExecution(Request $request, string $id): Procurement
    {
        $member = $request->attributes->get('think_tank.membership');
        abort_unless($member instanceof ConsortiumThinkTank, 403);

        return Procurement::query()
            ->where('procurement_owner_type', 'think_tank')
            ->where('think_tank_member_id', $member->id)
            ->whereNotNull('think_tank_procurement_plan_id')
            ->with(['thinkTankPlanningItem.plan', 'forms.fields'])
            ->whereKey($id)
            ->firstOrFail();
    }

    private function authoritativeForm(Procurement $procurement): DynamicForm
    {
        if ($procurement->forms->count() !== 1) {
            throw new ThinkTankApiException(
                'FORM_INTEGRITY_ERROR',
                'This procurement does not have one authoritative application form. Contact support before reviewing applications.',
                409,
            );
        }

        /** @var DynamicForm $form */
        $form = $procurement->forms->first();
        $this->submissionValidation->rules($form);

        return $form;
    }

    private function submissionQuery(Procurement $procurement, DynamicForm $form): Builder
    {
        return FormSubmission::query()
            ->where('procurement_id', $procurement->id)
            ->where('form_id', $form->id)
            ->whereHas('form', fn (Builder $forms): Builder => $forms
                ->where('procurement_id', $procurement->id));
    }

    /** @return array<int, string> */
    private function uploadFieldKeys(DynamicForm $form): array
    {
        return $form->fields
            ->whereIn('field_type', DynamicProcurementFormCatalog::UPLOAD_TYPES)
            ->pluck('field_key')
            ->map(fn (mixed $key): string => (string) $key)
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function applicationDto(FormSubmission $submission, DynamicForm $form): array
    {
        $this->assertValueIntegrity($form, $submission->values);
        $name = $this->identityValue($submission, 'official_name')
            ?? $this->nullableText($submission->submitter?->name);
        $email = $this->identityValue($submission, 'official_email')
            ?? $this->nullableText($submission->submitter?->email);
        $fileCount = $this->readableFileCount($form, $submission);
        $status = $this->statusKey($submission->status);

        return [
            'id' => (string) $submission->id,
            'code' => $submission->procurement_submission_code ?: null,
            'status' => $status,
            'statusLabel' => $this->statusLabel($status),
            'applicantId' => $submission->submitted_by ? (string) $submission->submitted_by : null,
            'applicantName' => $name,
            'applicantEmail' => $email,
            'submittedAt' => $this->dateTime($submission->submitted_at),
            'resubmittedAt' => $this->dateTime($submission->resubmitted_at),
            'withdrawnAt' => $this->dateTime($submission->withdrawn_at),
            'publicationVersion' => max(1, (int) $submission->publication_version),
            'fileCount' => $fileCount,
        ];
    }

    private function readableFileCount(DynamicForm $form, FormSubmission $submission): int
    {
        $uploadFields = $form->fields
            ->whereIn('field_type', DynamicProcurementFormCatalog::UPLOAD_TYPES)
            ->keyBy('field_key');

        return $submission->values
            ->filter(function (FormSubmissionValue $value) use ($uploadFields): bool {
                $field = $uploadFields->get($value->field_key);

                return $field instanceof DynamicFormField
                    && $this->readableAttachment($field, $value) !== null;
            })
            ->count();
    }

    private function hasReadableFile(DynamicForm $form, FormSubmission $submission): bool
    {
        $uploadFields = $form->fields
            ->whereIn('field_type', DynamicProcurementFormCatalog::UPLOAD_TYPES)
            ->keyBy('field_key');

        return $submission->values
            ->contains(function (FormSubmissionValue $value) use ($uploadFields): bool {
                $field = $uploadFields->get($value->field_key);

                return $field instanceof DynamicFormField
                    && $this->readableAttachment($field, $value) !== null;
            });
    }

    /** @return array<string, mixed> */
    private function executionDto(Procurement $procurement): array
    {
        $item = $procurement->thinkTankPlanningItem;

        return [
            'id' => (string) $procurement->id,
            'title' => (string) $procurement->title,
            'reference' => $procurement->reference_no ?: null,
            'status' => (string) $procurement->status,
            'statusLabel' => $this->executions->statusLabel((string) $procurement->status),
            'fiscalYear' => $procurement->fiscal_year !== null ? (string) $procurement->fiscal_year : null,
            'publicationVersion' => max(1, (int) $procurement->publication_version),
            'applicationStartDate' => $this->date($procurement->application_start_date),
            'applicationEndDate' => $this->date($procurement->application_end_date),
            'register' => [
                'planId' => $item?->plan_id ? (string) $item->plan_id : null,
                'planCode' => $item?->plan?->plan_code ?: null,
                'itemId' => $item?->id ? (string) $item->id : null,
                'itemCode' => $item?->item_code ?: null,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function fieldDto(
        Procurement $procurement,
        FormSubmission $submission,
        DynamicFormField $field,
        ?FormSubmissionValue $storedValue,
    ): array {
        $isFile = in_array($field->field_type, DynamicProcurementFormCatalog::UPLOAD_TYPES, true);
        $base = [
            'id' => $storedValue ? (string) $storedValue->id : null,
            'key' => (string) $field->field_key,
            'label' => (string) $field->label,
            'type' => (string) $field->field_type,
            'required' => (bool) $field->is_required,
            'value' => null,
            'isFile' => $isFile,
            'hasFile' => false,
            'fileName' => null,
            'fileSize' => null,
            'downloadUrl' => null,
        ];

        if (! $isFile) {
            $base['value'] = $this->answerValue($field, $storedValue?->value);

            return $base;
        }
        if (! $storedValue) {
            return $base;
        }
        $attachment = $this->readableAttachment($field, $storedValue);
        if ($attachment === null) {
            return $base;
        }
        [, , $extension, $size] = $attachment;

        return [
            ...$base,
            'hasFile' => true,
            'fileName' => $this->attachmentName($field, $extension),
            'fileSize' => $size,
            'downloadUrl' => route(
                'api.v1.think-tank.procurement.executions.applications.values.download',
                [
                    'execution' => $procurement->id,
                    'submission' => $submission->id,
                    'value' => $storedValue->id,
                ],
                false,
            ),
        ];
    }

    private function answerValue(DynamicFormField $field, mixed $stored): string|array|bool|null
    {
        if ($stored === null || $stored === '') {
            return null;
        }
        if (in_array($field->field_type, ['multiselect', 'checkbox'], true)) {
            try {
                $decoded = json_decode((string) $stored, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new ConflictHttpException('This application contains an unreadable multiple-choice answer.');
            }
            if (! is_array($decoded) || ! array_is_list($decoded)
                || collect($decoded)->contains(fn (mixed $value): bool => ! is_scalar($value))) {
                throw new ConflictHttpException('This application contains an invalid multiple-choice answer.');
            }

            $values = collect($decoded)->map(fn (mixed $value): string => (string) $value)->all();
            if (array_diff($values, $field->optionValues()) !== []
                || count($values) !== count(array_unique($values))) {
                throw new ConflictHttpException('This application contains an answer outside the configured choices.');
            }

            return $values;
        }
        if ($field->field_type === 'boolean') {
            $boolean = filter_var($stored, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($boolean === null) {
                throw new ConflictHttpException('This application contains an invalid confirmation answer.');
            }

            return $boolean;
        }
        if (in_array($field->field_type, ['select', 'radio'], true)
            && ! in_array((string) $stored, $field->optionValues(), true)) {
            throw new ConflictHttpException('This application contains an answer outside the configured choices.');
        }

        return (string) $stored;
    }

    private function assertValueIntegrity(DynamicForm $form, Collection $values): void
    {
        $valueKeys = $values
            ->map(fn (FormSubmissionValue $value): string => trim((string) $value->field_key))
            ->values();
        if ($valueKeys->contains('')
            || $valueKeys->count() !== $valueKeys->unique()->count()
            || $valueKeys->diff($form->fields->pluck('field_key'))->isNotEmpty()) {
            throw new ConflictHttpException(
                'This application contains conflicting answer records. Review is paused until support corrects the data.',
            );
        }
    }

    /** @return array{0: mixed, 1: string, 2: string} */
    private function storedAttachment(DynamicFormField $field, FormSubmissionValue $value): array
    {
        $attachment = $this->readableAttachment($field, $value, true);
        abort_unless($attachment !== null, 404, 'File unavailable.');

        return [$attachment[0], $attachment[1], $attachment[2]];
    }

    /** @return array{0: mixed, 1: string, 2: string, 3: int}|null */
    private function readableAttachment(
        DynamicFormField $field,
        FormSubmissionValue $value,
        bool $migrateLegacy = false,
    ): ?array
    {
        $path = $this->submissionFiles->normalizedAllowedPath($value->value);
        if ($path === null) {
            return null;
        }
        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, DynamicProcurementFormCatalog::extensionsFor($field->field_type), true)) {
            return null;
        }
        $local = Storage::disk('local');
        $public = Storage::disk('public');
        $localMetadata = $local->exists($path) ? $this->regularFileMetadata($local, $path) : null;
        $publicMetadata = $public->exists($path) ? $this->regularFileMetadata($public, $path) : null;

        if ($migrateLegacy && $localMetadata !== null && $publicMetadata !== null) {
            if ($localMetadata['size'] === $publicMetadata['size']
                && $this->sameStoredFile($localMetadata, $publicMetadata)) {
                try {
                    if ($public->delete($path)) {
                        $publicMetadata = null;
                    }
                } catch (\Throwable) {
                    // The verified private copy remains authoritative.
                }
            } else {
                // A failed historical migration may have left a partial private
                // copy. Never prefer it over the intact, verified source.
                try {
                    $local->delete($path);
                } catch (\Throwable) {
                    // This request will still use the verified public source.
                }
                $localMetadata = null;
            }
        }

        if ($migrateLegacy && $localMetadata === null && $publicMetadata !== null) {
            $temporaryPath = $path.'.migrating-'.Str::uuid();
            $stream = null;
            $migrated = false;
            $installedByThisRequest = false;
            try {
                $stream = $public->readStream($path);
                if ($stream !== false && $local->writeStream($temporaryPath, $stream) === true) {
                    $temporaryMetadata = $this->regularFileMetadata($local, $temporaryPath);
                    if ($temporaryMetadata !== null
                        && $temporaryMetadata['size'] === $publicMetadata['size']
                        && $this->sameStoredFile($temporaryMetadata, $publicMetadata)) {
                        $installedByThisRequest = $local->move($temporaryPath, $path);
                    }
                    if ($installedByThisRequest) {
                        $candidate = $this->regularFileMetadata($local, $path);
                        $migrated = $candidate !== null
                            && $candidate['size'] === $publicMetadata['size']
                            && $this->sameStoredFile($candidate, $publicMetadata);
                        $localMetadata = $migrated ? $candidate : null;
                    }
                }
            } catch (\Throwable) {
                $migrated = false;
                $localMetadata = null;
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
                try {
                    $local->delete($temporaryPath);
                } catch (\Throwable) {
                    // A non-authoritative temporary file is never selected below.
                }
                if ($installedByThisRequest && ! $migrated && $local->exists($path)) {
                    try {
                        $local->delete($path);
                    } catch (\Throwable) {
                        // Never select the unverified copy; the public source remains available.
                    }
                }
            }

            if ($migrated) {
                try {
                    if ($public->delete($path)) {
                        $publicMetadata = null;
                    }
                } catch (\Throwable) {
                    // The verified private copy is authoritative; retry cleanup later.
                }
            }

            // Another authorized request may have won the atomic move while
            // this request was copying. Re-resolve that verified private target
            // instead of returning a stale public-disk decision.
            if ($localMetadata === null && $local->exists($path)) {
                $concurrentCandidate = $this->regularFileMetadata($local, $path);
                $publicStillExists = $public->exists($path);
                if ($concurrentCandidate !== null
                    && (! $publicStillExists || $this->sameStoredFile($concurrentCandidate, $publicMetadata))) {
                    $localMetadata = $concurrentCandidate;
                    if ($publicStillExists) {
                        try {
                            if ($public->delete($path)) {
                                $publicMetadata = null;
                            }
                        } catch (\Throwable) {
                            // The matching private copy is safe to serve.
                        }
                    }
                }
            }
            if ($publicMetadata !== null && ! $public->exists($path)) {
                $publicMetadata = null;
            }
        }

        if ($localMetadata !== null) {
            return [$local, $path, $extension, $localMetadata['size']];
        }
        if ($publicMetadata !== null) {
            return [$public, $path, $extension, $publicMetadata['size']];
        }

        return null;
    }

    /** @return array{size: int, absolutePath: string}|null */
    private function regularFileMetadata(mixed $disk, string $path): ?array
    {
        try {
            $absolutePath = $disk->path($path);
            if (is_link($absolutePath) || ! is_file($absolutePath) || ! is_readable($absolutePath)) {
                return null;
            }

            return [
                'size' => max(0, (int) $disk->size($path)),
                'absolutePath' => $absolutePath,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array{size: int, absolutePath: string} $left
     * @param array{size: int, absolutePath: string} $right
     */
    private function sameStoredFile(array $left, array $right): bool
    {
        if ($left['size'] !== $right['size']) {
            return false;
        }

        $leftHash = hash_file('sha256', $left['absolutePath']);
        $rightHash = hash_file('sha256', $right['absolutePath']);

        return is_string($leftHash)
            && is_string($rightHash)
            && hash_equals($leftHash, $rightHash);
    }

    private function attachmentName(DynamicFormField $field, string $extension): string
    {
        $base = Str::slug((string) $field->label) ?: 'application-attachment';

        return mb_substr($base, 0, 120).($extension !== '' ? '.'.$extension : '');
    }

    private function identityValue(FormSubmission $submission, string $key): ?string
    {
        return $this->nullableText($submission->values->firstWhere('field_key', $key)?->value);
    }

    /** @return array{canView: bool, canManage: bool} */
    private function permissions(User $user): array
    {
        return [
            'canView' => $user->hasPermission('think_tank.procurement.evaluate'),
            'canManage' => $user->hasPermission('think_tank.procurement_plans.manage'),
        ];
    }

    private function statusKey(mixed $status): string
    {
        $status = trim((string) $status);

        return $status !== '' ? $status : 'unknown';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            FormSubmission::STATUS_SUBMITTED => 'Submitted',
            FormSubmission::STATUS_REVISION_REQUESTED => 'Revision requested',
            FormSubmission::STATUS_WITHDRAWN => 'Withdrawn',
            FormSubmission::STATUS_EOI_EVALUATION => 'EOI evaluation',
            FormSubmission::STATUS_EOI_NOT_QUALIFIED => 'EOI not qualified',
            FormSubmission::STATUS_TECHNICAL_PROPOSAL_INVITED => 'Technical proposal invited',
            FormSubmission::STATUS_TECHNICAL_PROPOSAL_SUBMITTED => 'Technical proposal submitted',
            FormSubmission::STATUS_TECHNICAL_PROPOSAL_DISQUALIFIED => 'Technical proposal disqualified',
            FormSubmission::STATUS_TECHNICAL_EVALUATION => 'Technical evaluation',
            'prescreen_passed' => 'Pre-screening passed',
            'prescreen_failed' => 'Pre-screening not passed',
            'selected' => 'Selected',
            'unknown' => 'Not recorded',
            default => Str::headline($status),
        };
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    private function date(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        return method_exists($value, 'toDateString') ? $value->toDateString() : (string) $value;
    }

    private function dateTime(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        return method_exists($value, 'toIso8601String') ? $value->toIso8601String() : (string) $value;
    }
}
