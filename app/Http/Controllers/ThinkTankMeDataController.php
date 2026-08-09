<?php

namespace App\Http\Controllers;

use App\Models\ConsortiumThinkTank;
use App\Models\IndicatorResult;
use App\Models\MeDataCollection;
use App\Models\MeDataCollectionAssignment;
use App\Models\MeDataEntryForm;
use App\Models\MeDataEntryFormField;
use App\Models\MeDataEntryFormSection;
use App\Models\MeDataSubmission;
use App\Models\MeDataSubmissionAnswer;
use App\Models\MeDataSubmissionReview;
use App\Models\MeDataSubmissionVersion;
use App\Models\MeSubmissionEvidence;
use App\Services\MeDataQualityService;
use App\Services\MeReportingNotificationService;
use App\Services\ThinkTankMeAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ThinkTankMeDataController extends Controller
{
    private const SECTION_GUIDANCE_FALLBACK = 'Complete the questions in this section using the most accurate information available. Review your answers before continuing to the next section.';

    private const SECTION_BACKGROUND_PALETTE = [
        '#EFF6FF',
        '#F0FDF4',
        '#FFFBEB',
        '#FDF2F8',
        '#F5F3FF',
        '#ECFEFF',
        '#FFF7ED',
        '#F8FAFC',
    ];

    public function __construct(
        private readonly ThinkTankMeAssignmentService $meAssignments
    ) {}

    public function index(Request $request)
    {
        $member = $this->member($request);
        $portalRouteParams = $this->portalRouteParams($request, $member);
        $overview = $this->meAssignments->overview(
            $member,
            $portalRouteParams,
            $this->canSubmit($request)
        );

        return view('think-tank.me-data.index', [
            'member' => $member,
            'assignmentGroups' => $overview['groups'],
            'summary' => $overview['summary'],
            'portalRouteParams' => $portalRouteParams,
        ]);
    }

    public function show(Request $request, MeDataCollectionAssignment $assignment)
    {
        $member = $this->member($request);
        $assignment = $this->assignmentForMember($assignment, $member);
        $this->assertPublishedForm($assignment);

        $form = $assignment->collection->form;
        $fields = $form->fields->sortBy('sort_order')->values();
        $formSections = $this->formSectionsForDisplay($form, $fields);
        $submission = $assignment->submission;
        $answerValues = $this->answerValues($submission, $fields);
        $state = $this->meAssignments->stateFor($assignment);
        $editable = $this->canSubmit($request)
            && $this->submissionIsEditable($submission)
            && $assignment->collection->isAcceptingSubmissions()
            && $assignment->collection->reportingPeriod?->isOpenForSubmission();

        return view('think-tank.me-data.show', [
            'member' => $member,
            'assignment' => $assignment,
            'collection' => $assignment->collection,
            'form' => $form,
            'period' => $assignment->collection->reportingPeriod,
            'fields' => $fields,
            'formSections' => $formSections,
            'submission' => $submission,
            'answerValues' => $answerValues,
            'attachments' => $this->attachmentsForSubmission($request, $assignment, $submission, $member),
            'fieldOptions' => $fields->mapWithKeys(fn ($field) => [
                (string) $field->id => $this->optionsForField($field),
            ]),
            'fieldUploadSettings' => $fields
                ->filter(fn ($field): bool => $field->isUpload())
                ->mapWithKeys(fn ($field): array => [
                    (string) $field->id => $this->uploadSettings($field),
                ]),
            'editable' => $editable,
            'assignmentState' => $state,
            'progress' => $this->meAssignments->progressFor($fields, $submission),
            'portalRouteParams' => $this->portalRouteParams($request, $member),
        ]);
    }

    public function download(
        Request $request,
        MeDataCollectionAssignment $assignment,
        MeDataSubmissionAnswer $answer,
        int $fileIndex
    ): BinaryFileResponse {
        abort_unless($request->hasValidSignature(), 403, 'This download link is invalid or has expired.');

        $member = $this->member($request);
        $assignment = $this->assignmentForMember($assignment, $member);
        $submission = $assignment->submission;

        abort_unless(
            $submission
            && (string) $answer->submission_id === (string) $submission->id,
            404
        );

        $field = $assignment->collection->form->fields
            ->first(fn (MeDataEntryFormField $candidate): bool => (string) $candidate->id === (string) $answer->field_id);

        abort_unless($field && $field->isUpload(), 404);

        $files = $this->storedFilesForAnswer($answer, $submission, $field, true);
        $file = $files->get($fileIndex);

        abort_unless(is_array($file), 404);

        $path = (string) $file['path'];
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404, 'The requested file is no longer available.');

        $response = response()->download(
            $disk->path($path),
            $this->safeDownloadName((string) ($file['original_name'] ?? 'attachment')),
            [
                'Content-Type' => 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
            ],
            'attachment'
        );

        // Laravel's binary download factory marks files public by default. These
        // evidence files are private submission data and must never be cached by
        // a shared intermediary after the signed URL has been used.
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    public function saveDraft(Request $request, MeDataCollectionAssignment $assignment)
    {
        abort_unless($request->user()?->user_type === 'think_tank', 403);

        $member = $this->member($request);
        $assignment = $this->assignmentForMember($assignment, $member);
        $this->assertPublishedForm($assignment);
        $this->assertCollectionAccepting($assignment->collection);

        $request->validate([
            'answers' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->validateAnswers(
            $request,
            $assignment->collection->form->fields,
            false,
            $assignment->submission
        );

        $storedPaths = [];
        $obsoletePaths = [];

        try {
            DB::transaction(function () use (
                $request,
                $assignment,
                $member,
                &$storedPaths,
                &$obsoletePaths
            ): void {
                $lockedAssignment = $this->lockedAssignmentForMember($assignment, $member);
                $this->assertPublishedForm($lockedAssignment);
                $this->assertCollectionAccepting($lockedAssignment->collection);

                $fields = $lockedAssignment->collection->form->fields->sortBy('sort_order')->values();
                $submission = $this->editableSubmission($lockedAssignment);
                $values = $this->validateAnswers($request, $fields, false, $submission);

                if (! $submission) {
                    $submission = MeDataSubmission::create([
                        'assignment_id' => $lockedAssignment->id,
                        'revision' => 1,
                        'status' => MeDataSubmission::STATUS_DRAFT,
                        'workflow_status' => MeDataSubmission::STATUS_DRAFT,
                        'schema_snapshot' => $this->schemaSnapshot($lockedAssignment),
                        'notes' => $request->input('notes'),
                    ]);
                } else {
                    if (blank($submission->schema_snapshot)) {
                        $submission->schema_snapshot = $this->schemaSnapshot($lockedAssignment);
                    }

                    $submission->notes = $request->input('notes');
                    $submission->save();
                }

                $this->persistAnswers($submission, $fields, $values, $storedPaths, $obsoletePaths);
            });
        } catch (Throwable $exception) {
            $this->deleteStoredPaths($storedPaths);

            throw $exception;
        }

        $this->deleteStoredPaths($obsoletePaths);

        return redirect()
            ->route('think-tank.me-data.show', array_merge(
                ['assignment' => $assignment->id],
                $this->portalRouteParams($request, $member)
            ))
            ->with('success', 'Your draft has been saved. You can return and complete it before the collection closes.');
    }

    public function submit(Request $request, MeDataCollectionAssignment $assignment)
    {
        abort_unless($request->user()?->user_type === 'think_tank', 403);

        $member = $this->member($request);
        $assignment = $this->assignmentForMember($assignment, $member);
        $this->assertPublishedForm($assignment);
        $this->assertCollectionAccepting($assignment->collection);

        $request->validate([
            'answers' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->validateAnswers(
            $request,
            $assignment->collection->form->fields,
            true,
            $assignment->submission
        );

        $storedPaths = [];
        $obsoletePaths = [];

        $submittedRecord = null;
        try {
            DB::transaction(function () use (
                $request,
                $assignment,
                $member,
                &$storedPaths,
                &$obsoletePaths,
                &$submittedRecord
            ): void {
                $lockedAssignment = $this->lockedAssignmentForMember($assignment, $member);
                $this->assertPublishedForm($lockedAssignment);
                $this->assertCollectionAccepting($lockedAssignment->collection);

                $fields = $lockedAssignment->collection->form->fields->sortBy('sort_order')->values();
                $submission = $this->editableSubmission($lockedAssignment);
                $values = $this->validateAnswers($request, $fields, true, $submission);

                if (! $submission) {
                    $submission = MeDataSubmission::create([
                        'assignment_id' => $lockedAssignment->id,
                        'revision' => 1,
                        'status' => MeDataSubmission::STATUS_DRAFT,
                        'workflow_status' => MeDataSubmission::STATUS_DRAFT,
                        'schema_snapshot' => $this->schemaSnapshot($lockedAssignment),
                    ]);
                } elseif (blank($submission->schema_snapshot)) {
                    $submission->schema_snapshot = $this->schemaSnapshot($lockedAssignment);
                }

                $answers = $this->persistAnswers(
                    $submission,
                    $fields,
                    $values,
                    $storedPaths,
                    $obsoletePaths
                );

                $isResubmission = $submission->effectiveStatus() === MeDataSubmission::STATUS_RETURNED;
                if ($isResubmission) {
                    $submission->revision = max(1, (int) $submission->revision) + 1;
                }

                $submission->fill([
                    'status' => MeDataSubmission::STATUS_SUBMITTED,
                    'workflow_status' => $isResubmission
                        ? MeDataSubmission::STATUS_RESUBMITTED
                        : MeDataSubmission::STATUS_SUBMITTED,
                    'current_version' => max(1, (int) $submission->revision),
                    'notes' => $request->input('notes'),
                    'submitted_by' => $request->user()->id,
                    'submitted_at' => now(),
                ] + ($isResubmission ? [
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'review_notes' => null,
                    'under_review_by' => null,
                    'under_review_at' => null,
                    'verified_by' => null,
                    'verified_at' => null,
                    'approved_by' => null,
                    'approved_at' => null,
                    'rejected_by' => null,
                    'rejected_at' => null,
                ] : []))->save();

                $this->createIndicatorResults(
                    $lockedAssignment,
                    $submission,
                    $fields,
                    $values,
                    $answers,
                    $member,
                    $request
                );
                $this->syncSubmissionEvidence($lockedAssignment, $submission, $fields, $answers, $member, $request);

                MeDataSubmissionVersion::query()->updateOrCreate(
                    ['submission_id' => $submission->id, 'version' => (int) $submission->revision],
                    [
                        'status' => $submission->workflow_status,
                        'schema_snapshot' => $submission->schema_snapshot,
                        'answers_snapshot' => $answers->mapWithKeys(fn (MeDataSubmissionAnswer $answer): array => [
                            $answer->field_key => $answer->value,
                        ])->all(),
                        'evidence_snapshot' => $answers->filter(fn (MeDataSubmissionAnswer $answer): bool => $answer->field?->isUpload())->pluck('value', 'field_key')->all(),
                        'submitter_notes' => $submission->notes,
                        'created_by' => $request->user()->id,
                        'submitted_at' => $submission->submitted_at,
                    ]
                );
                MeDataSubmissionReview::query()->create([
                    'submission_id' => $submission->id,
                    'submission_version' => (int) $submission->revision,
                    'from_status' => $isResubmission ? MeDataSubmission::STATUS_RETURNED : MeDataSubmission::STATUS_DRAFT,
                    'to_status' => $submission->workflow_status,
                    'action' => $isResubmission ? 'resubmitted' : 'submitted',
                    'comments' => $submission->notes,
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                ]);
                $submittedRecord = $submission;
            });
        } catch (Throwable $exception) {
            $this->deleteStoredPaths($storedPaths);

            throw $exception;
        }

        $this->deleteStoredPaths($obsoletePaths);
        if ($submittedRecord) {
            app(MeDataQualityService::class)->evaluate($submittedRecord);
            app(MeReportingNotificationService::class)->submissionLifecycle(
                $submittedRecord,
                $submittedRecord->workflow_status
            );
        }

        return redirect()
            ->route('think-tank.me-data.show', array_merge(
                ['assignment' => $assignment->id],
                $this->portalRouteParams($request, $member)
            ))
            ->with('success', 'Your M&E data has been submitted for review.');
    }

    private function member(Request $request): ConsortiumThinkTank
    {
        $user = $request->user();
        abort_unless($user, 403);

        abort_if(
            $user->hasActiveLoginBlock() || (bool) $user->is_blacklisted,
            403,
            'This account is not permitted to submit M&E data.'
        );

        if ($user->isSuperAdmin() || $user->isAdmin()) {
            $memberId = trim((string) $request->query('think_tank_member_id', ''));

            $member = ConsortiumThinkTank::query()
                ->with('consortium')
                ->where('status', 'active')
                ->when($memberId !== '', fn ($query) => $query->whereKey($memberId))
                ->orderBy('name')
                ->first();

            abort_unless($member, 404, 'No active think tank was found.');

            return $member;
        }

        abort_unless($user->user_type === 'think_tank', 403);

        $member = $user->resolvedThinkTankMembership();
        abort_unless($member, 403, 'This account is not linked to a think tank.');
        abort_unless($member->status === 'active', 403, 'This think tank membership is not active.');

        return $member->loadMissing('consortium');
    }

    private function assignmentForMember(
        MeDataCollectionAssignment $assignment,
        ConsortiumThinkTank $member
    ): MeDataCollectionAssignment {
        abort_unless(
            (string) $assignment->think_tank_member_id === (string) $member->id,
            403,
            'This data collection is not assigned to your think tank.'
        );

        return $assignment->load([
            'collection.form.sections',
            'collection.form.indicator.unit',
            'collection.form.fields.indicator',
            'collection.form.fields.formSection',
            'collection.reportingPeriod',
            'submission.answers',
        ]);
    }

    private function lockedAssignmentForMember(
        MeDataCollectionAssignment $assignment,
        ConsortiumThinkTank $member
    ): MeDataCollectionAssignment {
        $lockedAssignment = MeDataCollectionAssignment::query()
            ->whereKey($assignment->id)
            ->where('think_tank_member_id', $member->id)
            ->lockForUpdate()
            ->first();

        abort_unless($lockedAssignment, 403, 'This data collection is not assigned to your think tank.');

        $collection = MeDataCollection::query()
            ->whereKey($lockedAssignment->collection_id)
            ->lockForUpdate()
            ->firstOrFail();

        $collection->load([
            'form.sections',
            'form.indicator.unit',
            'form.fields.indicator',
            'form.fields.formSection',
            'reportingPeriod',
        ]);

        $lockedAssignment->setRelation('collection', $collection);
        $lockedAssignment->setRelation(
            'submission',
            MeDataSubmission::query()
                ->where('assignment_id', $lockedAssignment->id)
                ->lockForUpdate()
                ->first()
        );

        return $lockedAssignment;
    }

    private function assertPublishedForm(MeDataCollectionAssignment $assignment): void
    {
        abort_unless(
            $assignment->collection
            && in_array($assignment->collection->status, [
                MeDataCollection::STATUS_OPEN,
                MeDataCollection::STATUS_CLOSED,
            ], true)
            && $assignment->collection->form
            && $assignment->collection->form->status === MeDataEntryForm::STATUS_PUBLISHED,
            404,
            'This M&E form is not available.'
        );
    }

    private function assertCollectionAccepting(MeDataCollection $collection): void
    {
        if (! $collection->isAcceptingSubmissions()) {
            throw ValidationException::withMessages([
                'collection' => 'This collection is not currently accepting responses.',
            ]);
        }

        if (! $collection->reportingPeriod
            || ! $collection->reportingPeriod->isOpenForSubmission()) {
            throw ValidationException::withMessages([
                'collection' => 'The reporting period is not active.',
            ]);
        }
    }

    private function editableSubmission(MeDataCollectionAssignment $assignment): ?MeDataSubmission
    {
        $submission = $assignment->submission;

        if ($submission && ! $this->submissionIsEditable($submission)) {
            throw ValidationException::withMessages([
                'submission' => 'This response has already been submitted and can no longer be changed.',
            ]);
        }

        return $submission;
    }

    private function submissionIsEditable(?MeDataSubmission $submission): bool
    {
        if (! $submission) {
            return true;
        }

        return $submission->isEditable();
    }

    private function answerValues(?MeDataSubmission $submission, Collection $fields): Collection
    {
        if (! $submission) {
            return collect();
        }

        $submission->loadMissing('answers');
        $fieldsById = $fields->keyBy(
            fn (MeDataEntryFormField $field): string => (string) $field->id
        );

        return $submission->answers->mapWithKeys(function (MeDataSubmissionAnswer $answer) use ($fieldsById): array {
            $fieldId = (string) $answer->field_id;
            $field = $fieldsById->get($fieldId);

            if (! $field) {
                return [];
            }

            return [
                $fieldId => $this->answerValueForDisplay($field, $answer->value),
            ];
        });
    }

    /**
     * Keep the value passed to the response controls consistent with the
     * configured field type. Upload metadata is rendered separately as an
     * attachment list and must never be treated as a list of scalar choices.
     */
    private function answerValueForDisplay(MeDataEntryFormField $field, mixed $payload): mixed
    {
        $value = $this->answerPayloadValue($payload);

        if ($field->isUpload()) {
            return null;
        }

        if (in_array($field->field_type, [
            MeDataEntryFormField::TYPE_MULTISELECT,
            MeDataEntryFormField::TYPE_CHECKBOX,
        ], true)) {
            return $this->scalarAnswerValues($value);
        }

        if (is_scalar($value)) {
            return $value;
        }

        $legacyValues = $this->scalarAnswerValues($value);

        return count($legacyValues) === 1 ? $legacyValues[0] : null;
    }

    private function scalarAnswerValues(mixed $value): array
    {
        if (! is_array($value)) {
            return is_scalar($value) ? [$value] : [];
        }

        return collect($value)
            ->flatten()
            ->filter(fn ($item): bool => is_scalar($item) && trim((string) $item) !== '')
            ->values()
            ->all();
    }

    private function answerPayloadValue(mixed $payload): mixed
    {
        if (is_array($payload) && array_key_exists('value', $payload)) {
            return $payload['value'];
        }

        return $payload;
    }

    private function validateAnswers(
        Request $request,
        Collection $fields,
        bool $final,
        ?MeDataSubmission $submission = null
    ): array {
        $input = $request->input('answers', []);
        $input = is_array($input) ? $input : [];
        $uploaded = $request->file('answers', []);
        $uploaded = is_array($uploaded) ? $uploaded : [];
        $fieldIds = $fields->pluck('id')->map(fn ($id): string => (string) $id);
        $submittedIds = collect(array_unique(array_merge(array_keys($input), array_keys($uploaded))))
            ->map(fn ($id): string => (string) $id);
        $unknown = $submittedIds->diff($fieldIds);

        if ($unknown->isNotEmpty()) {
            $this->replaceAnswersWithSafeRedirectInput($request, $fields, $input);

            throw ValidationException::withMessages([
                'answers' => 'The response contains a field that is not part of this form.',
            ]);
        }

        $answers = $submission
            ? $submission->answers()->get()->keyBy(fn (MeDataSubmissionAnswer $answer): string => (string) $answer->field_id)
            : collect();
        $values = [];
        $errors = [];

        foreach ($fields as $field) {
            $fieldId = (string) $field->id;

            if ($field->isUpload()) {
                $existing = $submission && $answers->has($fieldId)
                    ? $this->storedFilesForAnswer($answers->get($fieldId), $submission, $field, true)->all()
                    : [];
                $uploads = $this->uploadedFiles($uploaded[$fieldId] ?? null);
                $value = [
                    'existing' => $existing,
                    'uploads' => $uploads,
                    'multiple' => $this->uploadSettings($field)['multiple'],
                ];
                $values[$fieldId] = $value;
                $fieldErrors = $this->validateUploadAnswer(
                    $field,
                    $value,
                    $input[$fieldId] ?? null,
                    $final
                );

                if ($fieldErrors !== []) {
                    $errors["answers.{$fieldId}"] = $fieldErrors;
                }

                continue;
            }

            $raw = array_key_exists($fieldId, $input)
                ? $input[$fieldId]
                : (in_array($field->field_type, [
                    MeDataEntryFormField::TYPE_MULTISELECT,
                    MeDataEntryFormField::TYPE_CHECKBOX,
                ], true) ? [] : null);
            $value = $this->normalizeAnswer($field, $raw);
            $values[$fieldId] = $value;

            if ($this->answerIsBlank($value)) {
                if ($final && $field->is_required) {
                    $errors["answers.{$fieldId}"][] = "{$field->label} is required.";
                }

                continue;
            }

            $fieldErrors = $this->validateEnteredAnswer($field, $value);
            if ($fieldErrors !== []) {
                $errors["answers.{$fieldId}"] = $fieldErrors;
            }
        }

        foreach ($fields->where('field_type', MeDataEntryFormField::TYPE_PERCENTAGE) as $percentageField) {
            $validation = is_array($percentageField->validation) ? $percentageField->validation : [];
            $numeratorField = $fields->firstWhere(
                'field_key',
                $validation['rollup_numerator_field_key'] ?? null
            );
            $denominatorField = $fields->firstWhere(
                'field_key',
                $validation['rollup_denominator_field_key'] ?? null
            );
            if (! $numeratorField || ! $denominatorField) {
                continue;
            }

            $percentage = $values[(string) $percentageField->id] ?? null;
            $numerator = $values[(string) $numeratorField->id] ?? null;
            $denominator = $values[(string) $denominatorField->id] ?? null;
            if (! is_numeric($percentage) || ! is_numeric($numerator) || ! is_numeric($denominator)) {
                continue;
            }

            if ((float) $denominator <= 0) {
                $errors['answers.'.(string) $denominatorField->id][] = "{$denominatorField->label} must be greater than zero.";

                continue;
            }
            if ((float) $numerator > (float) $denominator) {
                $errors['answers.'.(string) $numeratorField->id][] = "{$numeratorField->label} cannot exceed {$denominatorField->label}.";
            }

            $expected = round(((float) $numerator / (float) $denominator) * 100, 2);
            if (abs((float) $percentage - $expected) > 0.01) {
                $errors['answers.'.(string) $percentageField->id][] = "{$percentageField->label} must equal numerator ÷ denominator × 100 ({$expected}%).";
            }
        }

        if ($errors !== []) {
            $this->replaceAnswersWithSafeRedirectInput($request, $fields, $input);

            throw ValidationException::withMessages($errors);
        }

        return $values;
    }

    /**
     * Validation exceptions flash the request input to the session. Replace
     * malformed nested values before redirecting so scalar controls never
     * receive arrays, while valid multi-choice selections remain available.
     */
    private function replaceAnswersWithSafeRedirectInput(
        Request $request,
        Collection $fields,
        array $input
    ): void {
        $safe = [];

        foreach ($fields as $field) {
            if ($field->isUpload()) {
                continue;
            }

            $fieldId = (string) $field->id;
            $isMultipleChoice = in_array($field->field_type, [
                MeDataEntryFormField::TYPE_MULTISELECT,
                MeDataEntryFormField::TYPE_CHECKBOX,
            ], true);

            if (! array_key_exists($fieldId, $input) && ! $isMultipleChoice) {
                continue;
            }

            $safe[$fieldId] = $this->normalizeAnswer(
                $field,
                $input[$fieldId] ?? []
            );
        }

        $request->merge(['answers' => $safe]);
    }

    private function normalizeAnswer(MeDataEntryFormField $field, mixed $value): mixed
    {
        if (in_array($field->field_type, [
            MeDataEntryFormField::TYPE_MULTISELECT,
            MeDataEntryFormField::TYPE_CHECKBOX,
        ], true)) {
            return collect(is_array($value) ? $value : [$value])
                ->filter(fn ($item): bool => is_scalar($item) && trim((string) $item) !== '')
                ->map(fn ($item): string => trim((string) $item))
                ->unique()
                ->values()
                ->all();
        }

        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (in_array($field->field_type, [
            MeDataEntryFormField::TYPE_INTEGER,
            MeDataEntryFormField::TYPE_YEAR,
        ], true)
            && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        if ($field->field_type === MeDataEntryFormField::TYPE_RATING
            && is_numeric($value)
            && (float) $value === (float) (int) $value) {
            return (int) $value;
        }

        if (in_array($field->field_type, [
            MeDataEntryFormField::TYPE_NUMBER,
            MeDataEntryFormField::TYPE_PERCENTAGE,
            MeDataEntryFormField::TYPE_CURRENCY,
            MeDataEntryFormField::TYPE_SCALE,
        ], true) && is_numeric($value)) {
            return (float) $value;
        }

        return $field->field_type === MeDataEntryFormField::TYPE_YES_NO
            ? Str::lower($value)
            : $value;
    }

    private function validateEnteredAnswer(MeDataEntryFormField $field, mixed $value): array
    {
        $validation = is_array($field->validation) ? $field->validation : [];
        $rules = ['bail'];
        $checkStep = false;

        switch ($field->field_type) {
            case MeDataEntryFormField::TYPE_INTEGER:
                $rules[] = 'integer';
                $checkStep = true;
                break;

            case MeDataEntryFormField::TYPE_NUMBER:
            case MeDataEntryFormField::TYPE_CURRENCY:
                $rules[] = 'numeric';
                $checkStep = true;
                break;

            case MeDataEntryFormField::TYPE_PERCENTAGE:
                $rules[] = 'numeric';
                $rules[] = 'between:0,100';
                $checkStep = true;
                break;

            case MeDataEntryFormField::TYPE_RATING:
                [$ratingMin, $ratingMax] = $this->ratingSettings($validation);
                $rules[] = 'integer';
                $rules[] = "between:{$ratingMin},{$ratingMax}";
                $checkStep = true;
                break;

            case MeDataEntryFormField::TYPE_SCALE:
                [$scaleMin, $scaleMax] = $this->scaleBounds($validation);
                $rules[] = 'numeric';
                $rules[] = "between:{$scaleMin},{$scaleMax}";
                $checkStep = true;
                break;

            case MeDataEntryFormField::TYPE_DATE:
                return $this->validateDateValue($field, $value, ['Y-m-d'], 'date');

            case MeDataEntryFormField::TYPE_TIME:
                return $this->validateDateValue($field, $value, ['H:i', 'H:i:s'], 'time');

            case MeDataEntryFormField::TYPE_DATETIME:
                return $this->validateDateValue(
                    $field,
                    $value,
                    ['Y-m-d\TH:i', 'Y-m-d\TH:i:s'],
                    'date and time'
                );

            case MeDataEntryFormField::TYPE_MONTH:
                return $this->validateDateValue($field, $value, ['Y-m'], 'month');

            case MeDataEntryFormField::TYPE_YEAR:
                $rules[] = 'integer';
                $rules[] = 'regex:/^\d{4}$/';
                $rules[] = 'min:'.(int) ($validation['min'] ?? 1900);
                $rules[] = 'max:'.(int) ($validation['max'] ?? 2100);
                break;

            case MeDataEntryFormField::TYPE_SELECT:
            case MeDataEntryFormField::TYPE_RADIO:
                $rules[] = 'string';
                $rules[] = Rule::in($this->optionsForField($field)->pluck('value')->all());
                break;

            case MeDataEntryFormField::TYPE_MULTISELECT:
            case MeDataEntryFormField::TYPE_CHECKBOX:
                return $this->validateChoiceList($field, $value, $validation);

            case MeDataEntryFormField::TYPE_YES_NO:
                $rules[] = Rule::in(['yes', 'no']);
                break;

            case MeDataEntryFormField::TYPE_EMAIL:
                $rules[] = 'string';
                $rules[] = 'email:rfc';
                if (is_numeric($validation['min_length'] ?? null)) {
                    $rules[] = 'min:'.max(0, (int) $validation['min_length']);
                }
                $rules[] = 'max:'.min(255, max(1, (int) ($validation['max_length'] ?? 255)));
                break;

            case MeDataEntryFormField::TYPE_PHONE:
                $rules[] = 'string';
                $rules[] = 'regex:/^\+?[0-9()\.\-\s]{5,30}$/';
                if (is_numeric($validation['min_length'] ?? null)) {
                    $rules[] = 'min:'.max(0, (int) $validation['min_length']);
                }
                $rules[] = 'max:'.min(30, max(1, (int) ($validation['max_length'] ?? 30)));
                break;

            case MeDataEntryFormField::TYPE_URL:
                $rules[] = 'string';
                $rules[] = 'url:http,https';
                if (is_numeric($validation['min_length'] ?? null)) {
                    $rules[] = 'min:'.max(0, (int) $validation['min_length']);
                }
                $rules[] = 'max:'.min(2048, max(1, (int) ($validation['max_length'] ?? 2048)));
                break;

            default:
                $rules[] = 'string';
                if (is_numeric($validation['min_length'] ?? null)) {
                    $rules[] = 'min:'.max(0, (int) $validation['min_length']);
                }
                $rules[] = 'max:'.min(20000, max(1, (int) ($validation['max_length'] ?? 5000)));
                break;
        }

        if ($field->isNumeric() && ! in_array($field->field_type, [
            MeDataEntryFormField::TYPE_RATING,
            MeDataEntryFormField::TYPE_SCALE,
        ], true)) {
            if (is_numeric($validation['min'] ?? null)) {
                $rules[] = 'min:'.$validation['min'];
            }
            if (is_numeric($validation['max'] ?? null)) {
                $rules[] = 'max:'.$validation['max'];
            }
        }

        $validator = Validator::make(
            ['answer' => $value],
            ['answer' => $rules],
            [],
            ['answer' => $field->label]
        );

        $errors = $validator->errors()->get('answer');

        if ($errors === [] && $checkStep) {
            if ($field->field_type === MeDataEntryFormField::TYPE_RATING) {
                [$base, , $step] = $this->ratingSettings($validation);
            } elseif ($field->field_type === MeDataEntryFormField::TYPE_SCALE) {
                [$base] = $this->scaleBounds($validation);
                $step = $this->scaleSetting($validation, 'step', 1);
            } else {
                $step = $validation['step'] ?? null;
                $base = is_numeric($validation['min'] ?? null) ? (float) $validation['min'] : 0.0;
            }

            if (is_numeric($step) && (float) $step > 0) {
                $multiple = ((float) $value - $base) / (float) $step;

                if (abs($multiple - round($multiple)) > 0.0000001) {
                    $errors[] = "{$field->label} must use increments of {$step}.";
                }
            }
        }

        return $errors;
    }

    private function validateChoiceList(MeDataEntryFormField $field, mixed $value, array $validation): array
    {
        if (! is_array($value)) {
            return ["{$field->label} must be a list of choices."];
        }

        $selected = collect($value)->map(fn ($option): string => (string) $option)->values();
        $allowed = $this->optionsForField($field)->pluck('value')->map(fn ($option): string => (string) $option);
        $errors = [];

        if ($selected->diff($allowed)->isNotEmpty()) {
            $errors[] = "{$field->label} contains an unavailable choice.";
        }

        $min = is_numeric($validation['min_selections'] ?? null)
            ? max(0, (int) $validation['min_selections'])
            : null;
        $max = is_numeric($validation['max_selections'] ?? null)
            ? max(1, (int) $validation['max_selections'])
            : null;

        if ($min !== null && $selected->count() < $min) {
            $errors[] = "Select at least {$min} choice".($min === 1 ? '.' : 's.');
        }
        if ($max !== null && $selected->count() > $max) {
            $errors[] = "Select no more than {$max} choice".($max === 1 ? '.' : 's.');
        }

        return array_values(array_unique($errors));
    }

    private function validateDateValue(
        MeDataEntryFormField $field,
        mixed $value,
        array $formats,
        string $description
    ): array {
        if (! is_scalar($value)) {
            return ["{$field->label} must be a valid {$description}."];
        }

        $value = (string) $value;

        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);
            $dateErrors = \DateTimeImmutable::getLastErrors();
            $valid = $date !== false
                && ($dateErrors === false
                    || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
                && $date->format($format) === $value;

            if ($valid) {
                return [];
            }
        }

        return ["{$field->label} must be a valid {$description}."];
    }

    private function scaleSetting(array $validation, string $key, int|float $default): int|float
    {
        $value = $validation[$key] ?? data_get($validation, "scale.{$key}");

        return is_numeric($value) ? (float) $value : $default;
    }

    private function scaleBounds(array $validation): array
    {
        $min = (float) $this->scaleSetting($validation, 'min', 1);
        $max = (float) $this->scaleSetting($validation, 'max', 10);

        return $max < $min ? [$max, $min] : [$min, $max];
    }

    private function ratingSettings(array $validation): array
    {
        $min = min(10, max(1, (int) ($validation['min'] ?? 1)));
        $max = min(10, max(1, (int) ($validation['max'] ?? 5)));
        if ($max < $min) {
            [$min, $max] = [$max, $min];
        }

        $step = is_numeric($validation['step'] ?? null)
            ? min(max(1, (int) $validation['step']), max(1, $max - $min))
            : 1;

        return [$min, $max, $step];
    }

    private function validateUploadAnswer(
        MeDataEntryFormField $field,
        array $value,
        mixed $plainValue,
        bool $final
    ): array {
        $settings = $this->uploadSettings($field);
        $existing = collect($value['existing'] ?? [])->filter(fn ($file): bool => is_array($file))->values();
        $uploads = collect($value['uploads'] ?? [])->filter(
            fn ($file): bool => $file instanceof UploadedFile
        )->values();
        $effectiveCount = $uploads->isEmpty()
            ? $existing->count()
            : ($settings['multiple'] ? $existing->count() + $uploads->count() : $uploads->count());
        $errors = [];

        if (! $this->answerIsBlank($plainValue)) {
            $errors[] = "{$field->label} must be uploaded from your device.";
        }

        if (! $settings['multiple'] && $uploads->count() > 1) {
            $errors[] = "{$field->label} accepts one file only.";
        }

        if ($effectiveCount > $settings['max_files']) {
            $errors[] = "{$field->label} accepts no more than {$settings['max_files']} file"
                .($settings['max_files'] === 1 ? '.' : 's.');
        }

        if ($final && $field->is_required && $effectiveCount === 0) {
            $errors[] = "{$field->label} is required.";
        }

        if ($uploads->isNotEmpty() && $settings['allowed_extensions'] === []) {
            $errors[] = "{$field->label} does not permit any safe file formats.";

            return array_values(array_unique($errors));
        }

        foreach ($uploads as $index => $upload) {
            if (! $upload->isValid()) {
                $errors[] = 'File '.($index + 1)." for {$field->label} could not be uploaded.";

                continue;
            }

            $extension = Str::lower((string) $upload->getClientOriginalExtension());
            if ($extension === '' || ! in_array($extension, $settings['allowed_extensions'], true)) {
                $errors[] = 'File '.($index + 1)." for {$field->label} must be one of: "
                    .implode(', ', $settings['allowed_extensions']).'.';

                continue;
            }

            $rules = [
                'bail',
                'file',
                'max:'.($settings['max_file_size_mb'] * 1024),
                'mimes:'.implode(',', $settings['allowed_extensions']),
            ];

            if ($field->field_type === MeDataEntryFormField::TYPE_IMAGE) {
                $rules[] = 'image';
            }

            $validator = Validator::make(
                ['upload' => $upload],
                ['upload' => $rules],
                [],
                ['upload' => $field->label.' file '.($index + 1)]
            );

            $errors = array_merge($errors, $validator->errors()->get('upload'));
        }

        return array_values(array_unique($errors));
    }

    private function uploadedFiles(mixed $value): array
    {
        if ($value instanceof UploadedFile) {
            return [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->flatMap(fn ($item): array => $this->uploadedFiles($item))
            ->values()
            ->all();
    }

    private function uploadSettings(MeDataEntryFormField $field): array
    {
        $validation = is_array($field->validation) ? $field->validation : [];
        $defaults = $field->field_type === MeDataEntryFormField::TYPE_IMAGE
            ? ['jpg', 'jpeg', 'png', 'webp', 'gif']
            : ['pdf', 'csv', 'txt', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'zip'];
        $configured = $validation['allowed_extensions']
            ?? $validation['allowed_mimes']
            ?? $validation['mimes']
            ?? null;
        $extensions = $configured === null
            ? $defaults
            : (is_array($configured)
                ? $configured
                : (preg_split('/[\s,]+/', (string) $configured) ?: []));
        $blocked = [
            'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
            'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'exe', 'dll', 'com', 'bat',
            'cmd', 'ps1', 'vbs', 'scr', 'lnk', 'js', 'mjs', 'html', 'htm',
            'svg', 'svgz', 'xml', 'xhtml', 'jar', 'apk', 'msi', 'msix',
        ];

        $extensions = collect($extensions)
            ->filter(fn ($extension): bool => is_scalar($extension))
            ->map(fn ($extension): string => Str::lower(ltrim(trim((string) $extension), '.')))
            ->filter(fn (string $extension): bool => preg_match('/^[a-z0-9]{1,10}$/', $extension) === 1)
            ->reject(fn (string $extension): bool => in_array($extension, $blocked, true))
            ->when(
                $field->field_type === MeDataEntryFormField::TYPE_IMAGE,
                fn (Collection $items): Collection => $items->intersect($defaults)
            )
            ->unique()
            ->values()
            ->all();

        $maxSize = $validation['max_file_size_mb']
            ?? $validation['max_size_mb']
            ?? $validation['max_file_size']
            ?? 10;
        $maxSize = is_numeric($maxSize) ? (int) $maxSize : 10;
        $multiple = filter_var($validation['multiple'] ?? false, FILTER_VALIDATE_BOOL);
        $maxFiles = is_numeric($validation['max_files'] ?? null)
            ? (int) $validation['max_files']
            : ($multiple ? 5 : 1);

        return [
            'allowed_extensions' => $extensions,
            'max_file_size_mb' => min(50, max(1, $maxSize)),
            'multiple' => $multiple,
            'max_files' => $multiple ? min(10, max(1, $maxFiles)) : 1,
        ];
    }

    private function persistUploadedFiles(
        MeDataSubmission $submission,
        MeDataEntryFormField $field,
        array $value,
        array &$storedPaths,
        array &$obsoletePaths
    ): array {
        $existing = collect($value['existing'] ?? [])->filter(fn ($file): bool => is_array($file))->values();
        $uploads = collect($value['uploads'] ?? [])->filter(
            fn ($file): bool => $file instanceof UploadedFile
        )->values();
        $multiple = (bool) ($value['multiple'] ?? false);

        if ($uploads->isEmpty()) {
            return $existing->all();
        }

        $newFiles = $uploads->map(function (UploadedFile $upload) use (
            $submission,
            $field,
            &$storedPaths
        ): array {
            $extension = Str::lower((string) $upload->getClientOriginalExtension());
            $directory = "me-data/submissions/{$submission->id}/{$field->id}";
            $filename = Str::uuid()->toString().'.'.$extension;
            $sourcePath = $upload->getRealPath();
            $checksum = is_string($sourcePath) && $sourcePath !== ''
                ? hash_file('sha256', $sourcePath)
                : false;
            $path = $upload->storeAs($directory, $filename, 'local');

            if (! is_string($path) || $path === '') {
                throw ValidationException::withMessages([
                    "answers.{$field->id}" => "{$field->label} could not be stored. Please try again.",
                ]);
            }

            $storedPaths[] = $path;

            return array_filter([
                'id' => Str::uuid()->toString(),
                'disk' => 'local',
                'path' => $path,
                'original_name' => $this->safeDownloadName($upload->getClientOriginalName()),
                'mime_type' => (string) ($upload->getMimeType() ?: 'application/octet-stream'),
                'extension' => $extension,
                'size' => (int) $upload->getSize(),
                'sha256' => is_string($checksum) ? $checksum : null,
                'uploaded_at' => now()->toIso8601String(),
            ], fn ($item): bool => $item !== null && $item !== '');
        });

        if ($multiple) {
            return $existing->concat($newFiles)->values()->all();
        }

        $obsoletePaths = array_merge(
            $obsoletePaths,
            $existing->pluck('path')->filter(fn ($path): bool => is_string($path))->all()
        );

        return $newFiles->take(1)->values()->all();
    }

    private function storedFilesForAnswer(
        MeDataSubmissionAnswer $answer,
        MeDataSubmission $submission,
        MeDataEntryFormField $field,
        bool $mustExist
    ): Collection {
        $payload = $this->answerPayloadValue($answer->value);

        if (! is_array($payload)) {
            return collect();
        }

        $items = isset($payload['path']) || isset($payload['stored_path'])
            ? [$payload]
            : $payload;

        return collect($items)
            ->map(fn ($item): ?array => $this->normalizeStoredFileMetadata(
                $item,
                $submission,
                $field,
                $mustExist
            ))
            ->filter()
            ->values();
    }

    private function normalizeStoredFileMetadata(
        mixed $item,
        MeDataSubmission $submission,
        MeDataEntryFormField $field,
        bool $mustExist
    ): ?array {
        if (! is_array($item)) {
            return null;
        }

        $disk = trim((string) ($item['disk'] ?? 'local'));
        $path = str_replace('\\', '/', trim((string) ($item['path'] ?? $item['stored_path'] ?? '')));

        if ($disk !== 'local' || ! $this->isSafeStoredPath($path, $submission, $field)) {
            return null;
        }

        if ($mustExist && ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $mimeType = trim((string) ($item['mime_type'] ?? 'application/octet-stream'));
        if (preg_match('/^[a-z0-9.+-]+\/[a-z0-9.+-]+$/i', $mimeType) !== 1) {
            $mimeType = 'application/octet-stream';
        }

        $checksum = Str::lower(trim((string) ($item['sha256'] ?? '')));

        return array_filter([
            'id' => trim((string) ($item['id'] ?? '')) ?: null,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $this->safeDownloadName((string) ($item['original_name'] ?? 'attachment')),
            'mime_type' => $mimeType,
            'extension' => Str::lower(trim((string) ($item['extension'] ?? pathinfo($path, PATHINFO_EXTENSION)))),
            'size' => is_numeric($item['size'] ?? null) ? max(0, (int) $item['size']) : null,
            'sha256' => preg_match('/^[a-f0-9]{64}$/', $checksum) === 1 ? $checksum : null,
            'uploaded_at' => trim((string) ($item['uploaded_at'] ?? '')) ?: null,
        ], fn ($value): bool => $value !== null && $value !== '');
    }

    private function isSafeStoredPath(
        string $path,
        MeDataSubmission $submission,
        MeDataEntryFormField $field
    ): bool {
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '..')) {
            return false;
        }

        $prefix = "me-data/submissions/{$submission->id}/{$field->id}/";
        if (! Str::startsWith($path, $prefix)) {
            return false;
        }

        $filename = Str::after($path, $prefix);

        return $filename !== ''
            && ! str_contains($filename, '/')
            && preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,180}$/', $filename) === 1;
    }

    private function attachmentsForSubmission(
        Request $request,
        MeDataCollectionAssignment $assignment,
        ?MeDataSubmission $submission,
        ConsortiumThinkTank $member
    ): Collection {
        if (! $submission) {
            return collect();
        }

        $answers = $submission->answers->keyBy(
            fn (MeDataSubmissionAnswer $answer): string => (string) $answer->field_id
        );

        return $assignment->collection->form->fields
            ->filter(fn (MeDataEntryFormField $field): bool => $field->isUpload())
            ->mapWithKeys(function (MeDataEntryFormField $field) use (
                $request,
                $assignment,
                $submission,
                $member,
                $answers
            ): array {
                $answer = $answers->get((string) $field->id);
                if (! $answer) {
                    return [(string) $field->id => collect()];
                }

                $files = $this->storedFilesForAnswer($answer, $submission, $field, true)
                    ->map(function (array $file, int $index) use (
                        $request,
                        $assignment,
                        $answer,
                        $member
                    ): array {
                        $params = array_merge([
                            'assignment' => $assignment->id,
                            'answer' => $answer->id,
                            'fileIndex' => $index,
                        ], $this->portalRouteParams($request, $member));

                        return array_merge($file, [
                            'download_url' => URL::temporarySignedRoute(
                                'think-tank.me-data.download',
                                now()->addMinutes(30),
                                $params
                            ),
                        ]);
                    });

                return [(string) $field->id => $files];
            });
    }

    private function deleteStoredPaths(array $paths): void
    {
        $safePaths = collect($paths)
            ->filter(fn ($path): bool => is_string($path))
            ->map(fn (string $path): string => str_replace('\\', '/', trim($path)))
            ->filter(fn (string $path): bool => Str::startsWith($path, 'me-data/submissions/'))
            ->reject(fn (string $path): bool => str_contains($path, '..') || str_contains($path, "\0"))
            ->unique()
            ->values()
            ->all();

        if ($safePaths !== []) {
            Storage::disk('local')->delete($safePaths);
        }
    }

    private function safeDownloadName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $name));
        $name = Str::limit($name, 180, '');

        return $name !== '' && ! in_array($name, ['.', '..'], true) ? $name : 'attachment';
    }

    private function optionsForField(MeDataEntryFormField $field): Collection
    {
        return collect(is_array($field->options) ? $field->options : [])
            ->map(function ($option, $key): ?array {
                if (is_array($option)) {
                    $value = $option['value'] ?? $option['label'] ?? null;
                    $label = $option['label'] ?? $value;
                } elseif (! is_int($key) && ! ctype_digit((string) $key)) {
                    $value = $key;
                    $label = $option;
                } else {
                    $value = $option;
                    $label = $option;
                }

                if ($value === null || trim((string) $value) === '') {
                    return null;
                }

                return [
                    'value' => (string) $value,
                    'label' => trim((string) $label) ?: (string) $value,
                ];
            })
            ->filter()
            ->unique('value')
            ->values();
    }

    private function persistAnswers(
        MeDataSubmission $submission,
        Collection $fields,
        array $values,
        array &$storedPaths,
        array &$obsoletePaths
    ): Collection {
        return $fields->mapWithKeys(function (MeDataEntryFormField $field) use (
            $submission,
            $values,
            &$storedPaths,
            &$obsoletePaths
        ) {
            $value = $values[(string) $field->id] ?? null;

            if ($field->isUpload()) {
                $value = $this->persistUploadedFiles(
                    $submission,
                    $field,
                    is_array($value) ? $value : [],
                    $storedPaths,
                    $obsoletePaths
                );
            } else {
                $value = $this->normalizeAnswer($field, $value);
            }

            $answer = MeDataSubmissionAnswer::updateOrCreate(
                [
                    'submission_id' => $submission->id,
                    'field_key' => $field->field_key,
                ],
                [
                    'field_id' => $field->id,
                    'value' => ['value' => $value],
                ]
            );

            return [(string) $field->id => $answer];
        });
    }

    private function createIndicatorResults(
        MeDataCollectionAssignment $assignment,
        MeDataSubmission $submission,
        Collection $fields,
        array $values,
        Collection $answers,
        ConsortiumThinkTank $member,
        Request $request
    ): void {
        $collection = $assignment->collection;
        $period = $collection->reportingPeriod;
        $form = $collection->form;

        foreach ($fields as $field) {
            $value = $values[(string) $field->id] ?? null;

            if (! $field->indicator_id || $this->answerIsBlank($value)) {
                continue;
            }

            $indicator = $field->indicator;
            if (! $indicator) {
                continue;
            }
            $isMilestone = $indicator->value_type === 'milestone';
            $isBoolean = $indicator->value_type === 'boolean';
            if (! $field->isNumeric() && ! $isMilestone && ! $isBoolean) {
                continue;
            }
            $actualText = $isMilestone ? trim((string) $value) : null;
            $actualValue = $isBoolean
                ? (in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y'], true) ? 1 : 0)
                : ($isMilestone ? null : $value);
            $rollupNumerator = null;
            $rollupDenominator = null;
            if ($indicator->value_type === 'percentage') {
                $validation = is_array($field->validation) ? $field->validation : [];
                $numeratorField = $fields->firstWhere(
                    'field_key',
                    $validation['rollup_numerator_field_key'] ?? null
                );
                $denominatorField = $fields->firstWhere(
                    'field_key',
                    $validation['rollup_denominator_field_key'] ?? null
                );
                $numeratorValue = $numeratorField
                    ? ($values[(string) $numeratorField->id] ?? null)
                    : null;
                $denominatorValue = $denominatorField
                    ? ($values[(string) $denominatorField->id] ?? null)
                    : null;
                $rollupNumerator = is_numeric($numeratorValue) ? (float) $numeratorValue : null;
                $rollupDenominator = is_numeric($denominatorValue) ? (float) $denominatorValue : null;
                if ($rollupNumerator !== null && $rollupDenominator !== null && $rollupDenominator > 0) {
                    $actualValue = round(($rollupNumerator / $rollupDenominator) * 100, 4);
                }
            }

            $result = IndicatorResult::updateOrCreate(
                [
                    'data_submission_id' => $submission->id,
                    'source_field_key' => $field->field_key,
                ],
                [
                    'indicator_id' => $indicator->id,
                    'reporting_period_id' => $period->id,
                    'think_tank_member_id' => $member->id,
                    'period_type' => $period->period_type,
                    'period_label' => $period->label,
                    'period_start' => $period->period_start,
                    'period_end' => $period->period_end,
                    'actual_value' => $actualValue,
                    'actual_text' => $actualText,
                    'rollup_numerator' => $rollupNumerator,
                    'rollup_denominator' => $rollupDenominator,
                    'unit_id' => $indicator->unit_id,
                    'data_source' => 'Think tank portal: '.$member->name,
                    'method' => 'M&E data collection form: '.$form->title,
                    'notes' => $submission->notes,
                    'review_status' => 'submitted',
                    'collected_by' => $request->user()->id,
                    'collected_at' => now(),
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]
            );

            $answers->get((string) $field->id)?->update([
                'indicator_result_id' => $result->id,
            ]);
        }
    }

    private function syncSubmissionEvidence(
        MeDataCollectionAssignment $assignment,
        MeDataSubmission $submission,
        Collection $fields,
        Collection $answers,
        ConsortiumThinkTank $member,
        Request $request
    ): void {
        $submission->evidence()->delete();
        foreach ($fields->filter(fn (MeDataEntryFormField $field): bool => $field->isUpload()) as $field) {
            $answer = $answers->get((string) $field->id);
            if (! $answer) {
                continue;
            }
            foreach ($this->storedFilesForAnswer($answer, $submission, $field, false) as $file) {
                MeSubmissionEvidence::query()->create([
                    'submission_id' => $submission->id,
                    'indicator_id' => $field->indicator_id ?: $assignment->collection->form->indicator_id,
                    'reporting_period_id' => $assignment->collection->reporting_period_id,
                    'think_tank_member_id' => $member->id,
                    'answer_id' => $answer->id,
                    'evidence_type' => (string) data_get($field->validation, 'evidence_type', 'other'),
                    'document_title' => $field->label.' — '.$file['original_name'],
                    'file_path' => $file['path'],
                    'original_name' => $file['original_name'],
                    'mime_type' => $file['mime_type'] ?? null,
                    'file_size' => $file['size'] ?? null,
                    'checksum' => $file['sha256'] ?? null,
                    'verification_status' => 'pending',
                    'uploaded_by' => $request->user()->id,
                ]);
            }
        }
    }

    /**
     * Build the participant-facing section structure without trusting stored
     * colours or leaving older, unlinked fields out of the form.
     */
    private function formSectionsForDisplay(MeDataEntryForm $form, Collection $fields): Collection
    {
        $orderedFields = $fields
            ->sortBy(fn (MeDataEntryFormField $field): string => sprintf(
                '%010d|%s|%s',
                (int) $field->sort_order,
                (string) $field->created_at,
                (string) $field->id
            ))
            ->values();
        $sections = $form->relationLoaded('sections')
            ? $form->sections
            : $form->sections()->get();
        $linkedFieldIds = collect();

        $displaySections = $sections
            ->sortBy(fn (MeDataEntryFormSection $section): string => sprintf(
                '%010d|%s|%s',
                (int) $section->sort_order,
                (string) $section->created_at,
                (string) $section->id
            ))
            ->map(function (MeDataEntryFormSection $section) use ($orderedFields, $linkedFieldIds): array {
                $sectionFields = $orderedFields
                    ->filter(fn (MeDataEntryFormField $field): bool => (string) $field->section_id === (string) $section->id)
                    ->values();

                $linkedFieldIds->push(...$sectionFields->pluck('id')->map(fn ($id): string => (string) $id));

                $configuredColor = $this->normalizeSectionColor($section->background_color);
                $description = trim((string) $section->description);

                return [
                    'id' => (string) $section->id,
                    'key' => trim((string) $section->section_key),
                    'name' => trim((string) $section->name) ?: 'General information',
                    'description' => $description,
                    'guidance' => $description !== '' ? $description : self::SECTION_GUIDANCE_FALLBACK,
                    'configured_background_color' => $configuredColor,
                    'background_color' => $this->sectionColorPalette($configuredColor)['background'],
                    'sort_order' => (int) $section->sort_order,
                    'is_legacy' => false,
                    'palette' => $this->sectionColorPalette($configuredColor),
                    'fields' => $sectionFields,
                ];
            })
            ->filter(fn (array $section): bool => $section['fields']->isNotEmpty())
            ->values();

        $legacyFields = $orderedFields
            ->reject(fn (MeDataEntryFormField $field): bool => $linkedFieldIds->contains((string) $field->id));

        $legacyFields
            ->groupBy(function (MeDataEntryFormField $field): string {
                $name = trim((string) $field->section);

                return $name !== '' ? $name : 'General information';
            })
            ->each(function (Collection $sectionFields, string|int $legacyName) use ($displaySections): void {
                $name = trim((string) $legacyName) ?: 'General information';
                $paletteIndex = hexdec(substr(hash('sha256', Str::lower($name)), 0, 8))
                    % count(self::SECTION_BACKGROUND_PALETTE);
                $configuredColor = self::SECTION_BACKGROUND_PALETTE[$paletteIndex];

                $displaySections->push([
                    'id' => null,
                    'key' => 'legacy-'.(Str::slug($name) ?: 'information').'-'.substr(hash('sha256', $name), 0, 8),
                    'name' => $name,
                    'description' => '',
                    'guidance' => self::SECTION_GUIDANCE_FALLBACK,
                    'configured_background_color' => $configuredColor,
                    'background_color' => $this->sectionColorPalette($configuredColor)['background'],
                    'sort_order' => (int) ($sectionFields->min('sort_order') ?? $displaySections->count()),
                    'is_legacy' => true,
                    'palette' => $this->sectionColorPalette($configuredColor),
                    'fields' => $sectionFields->values(),
                ]);
            });

        return $displaySections->values();
    }

    private function normalizeSectionColor(mixed $color, string $fallback = '#EFF6FF'): string
    {
        $normalized = Str::upper(trim((string) $color));

        return preg_match('/^#[0-9A-F]{6}$/', $normalized) === 1
            ? $normalized
            : $fallback;
    }

    /**
     * The configured colour is softened when needed and paired with a dark,
     * contrast-checked accent. Section numbers and borders also distinguish
     * groups, so colour is never the only visual cue.
     */
    private function sectionColorPalette(string $configuredColor): array
    {
        $base = $this->normalizeSectionColor($configuredColor);
        $background = $this->relativeLuminance($base) >= 0.78
            ? $base
            : $this->blendHexColors($base, '#FFFFFF', 0.86);
        $accent = $this->blendHexColors($base, '#172033', 0.74);
        $header = $this->blendHexColors($background, $accent, 0.055);
        $borderSeed = $this->blendHexColors($base, '#172033', 0.38);
        $border = $this->blendHexColors($background, $borderSeed, 0.42);

        if ($this->contrastRatio($accent, $header) < 4.5
            || $this->contrastRatio($accent, '#FFFFFF') < 4.5) {
            $accent = '#172033';
        }

        return [
            'background' => $this->normalizeSectionColor($background),
            'header' => $this->normalizeSectionColor($header),
            'border' => $this->normalizeSectionColor($border),
            'accent' => $this->normalizeSectionColor($accent),
            'text' => '#172033',
        ];
    }

    private function blendHexColors(string $from, string $to, float $toWeight): string
    {
        $fromChannels = $this->hexChannels($this->normalizeSectionColor($from));
        $toChannels = $this->hexChannels($this->normalizeSectionColor($to));
        $weight = min(1, max(0, $toWeight));

        return sprintf(
            '#%02X%02X%02X',
            (int) round($fromChannels[0] * (1 - $weight) + $toChannels[0] * $weight),
            (int) round($fromChannels[1] * (1 - $weight) + $toChannels[1] * $weight),
            (int) round($fromChannels[2] * (1 - $weight) + $toChannels[2] * $weight)
        );
    }

    private function contrastRatio(string $first, string $second): float
    {
        $firstLuminance = $this->relativeLuminance($first);
        $secondLuminance = $this->relativeLuminance($second);
        $lighter = max($firstLuminance, $secondLuminance);
        $darker = min($firstLuminance, $secondLuminance);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function relativeLuminance(string $color): float
    {
        $channels = array_map(function (int $channel): float {
            $normalized = $channel / 255;

            return $normalized <= 0.03928
                ? $normalized / 12.92
                : (($normalized + 0.055) / 1.055) ** 2.4;
        }, $this->hexChannels($this->normalizeSectionColor($color)));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    private function hexChannels(string $color): array
    {
        return [
            hexdec(substr($color, 1, 2)),
            hexdec(substr($color, 3, 2)),
            hexdec(substr($color, 5, 2)),
        ];
    }

    private function schemaSnapshot(MeDataCollectionAssignment $assignment): array
    {
        $collection = $assignment->collection;
        $form = $collection->form;
        $period = $collection->reportingPeriod;
        $displaySections = $this->formSectionsForDisplay($form, $form->fields);
        $sectionMetadata = $displaySections->map(fn (array $section): array => [
            'id' => $section['id'],
            'section_key' => $section['key'],
            'name' => $section['name'],
            'description' => $section['description'] !== '' ? $section['description'] : null,
            'background_color' => $section['background_color'],
            'configured_background_color' => $section['configured_background_color'],
            'sort_order' => $section['sort_order'],
            'is_legacy' => $section['is_legacy'],
        ])->values();
        $sectionByField = collect();

        foreach ($displaySections as $index => $section) {
            foreach ($section['fields'] as $field) {
                $sectionByField->put((string) $field->id, $sectionMetadata->get($index));
            }
        }
        $displayFields = $displaySections
            ->flatMap(fn (array $section): array => $section['fields']->all())
            ->values();

        return [
            'captured_at' => now()->toIso8601String(),
            'form' => [
                'id' => $form->id,
                'code' => $form->code,
                'title' => $form->title,
                'version' => $form->version,
                'indicator' => $form->indicator ? [
                    'id' => $form->indicator->id,
                    'code' => $form->indicator->indicator_code,
                    'name' => $form->indicator->name,
                    'definition' => $form->indicator->definitions,
                    'unit' => $form->indicator->unit?->symbol ?: $form->indicator->unit?->name,
                ] : null,
            ],
            'reporting_period' => [
                'id' => $period?->id,
                'code' => $period?->code,
                'label' => $period?->label,
                'period_type' => $period?->period_type,
                'period_start' => $period?->period_start?->toDateString(),
                'period_end' => $period?->period_end?->toDateString(),
            ],
            'sections' => $sectionMetadata->all(),
            'fields' => $displayFields->map(fn ($field) => [
                'id' => $field->id,
                'indicator_id' => $field->indicator_id,
                'section_id' => $field->section_id,
                'section' => $field->section,
                'form_section' => $sectionByField->get((string) $field->id),
                'field_key' => $field->field_key,
                'label' => $field->label,
                'help_text' => $field->help_text,
                'field_type' => $field->field_type,
                'options' => $field->options,
                'validation' => $field->validation,
                'unit_label' => $field->unit_label,
                'is_required' => (bool) $field->is_required,
                'sort_order' => (int) $field->sort_order,
            ])->values()->all(),
        ];
    }

    private function answerIsBlank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return collect($value)->filter(fn ($item) => ! $this->answerIsBlank($item))->isEmpty();
        }

        return false;
    }

    private function portalRouteParams(Request $request, ConsortiumThinkTank $member): array
    {
        $user = $request->user();

        return $user && ($user->isSuperAdmin() || $user->isAdmin())
            ? ['think_tank_member_id' => $member->id]
            : [];
    }

    private function canSubmit(Request $request): bool
    {
        $user = $request->user();

        return $user?->user_type === 'think_tank'
            && $user->canAccessThinkTankArea('me')
            && $user->can('think_tank.me.submit');
    }
}
