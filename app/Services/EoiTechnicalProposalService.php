<?php

namespace App\Services;

use App\Models\EoiReportCommunication;
use App\Models\EoiTechnicalProposalCandidate;
use App\Models\EoiTechnicalProposalDocument;
use App\Models\EoiTechnicalProposalRound;
use App\Models\EoiTechnicalProposalRule;
use App\Models\EoiTechnicalProposalRuleApplication;
use App\Models\EoiTechnicalProposalSubmission;
use App\Models\EoiTechnicalProposalTemplate;
use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class EoiTechnicalProposalService
{
    public const MAX_RULES = 250;

    public const MAX_FILES_PER_REQUEST = 20;

    public const MAX_TEMPLATE_FILE_BYTES = 20 * 1024 * 1024;

    public const MAX_PROPOSAL_FILE_BYTES = 25 * 1024 * 1024;

    public const MAX_COMBINED_FILE_BYTES = 100 * 1024 * 1024;

    /**
     * File types intentionally exclude executables, scripts, SVG, HTML and
     * macro-enabled Office documents. MIME is detected server-side.
     *
     * @var array<string, array<int, string>>
     */
    private const ALLOWED_DOCUMENT_MIME_TYPES = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/x-ole-storage', 'application/vnd.ms-office'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
        ],
        'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/vnd.ms-office'],
        'xlsx' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
        ],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/x-ole-storage', 'application/vnd.ms-office'],
        'pptx' => [
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/zip',
        ],
        'csv' => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
        'txt' => ['text/plain'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip'],
        'odp' => ['application/vnd.oasis.opendocument.presentation', 'application/zip'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
    ];

    /**
     * Create a fully configured draft. Rules and templates are part of the
     * same transaction; stored files are removed if the database work fails.
     *
     * @param  array<string, mixed>  $config
     * @param  array<int, array<string, mixed>>  $rules
     * @param  array<int, UploadedFile|array<string, mixed>>  $templates
     */
    public function createDraft(
        array $config,
        array $rules,
        array $templates,
        User $user
    ): EoiTechnicalProposalRound {
        if (count($rules) > self::MAX_RULES) {
            $this->fail('rules', 'A proposal round may contain no more than '.self::MAX_RULES.' rules.');
        }

        $uploads = $this->normaliseUploads($templates, 'templates');
        $this->assertUploadLimits($uploads, self::MAX_TEMPLATE_FILE_BYTES, 'templates');

        $procurementId = $config['procurement_id'] ?? null;

        if (($config['procurement'] ?? null) instanceof Procurement) {
            $procurementId = $config['procurement']->getKey();
        }

        if (! is_string($procurementId) || trim($procurementId) === '') {
            $this->fail('procurement_id', 'A procurement is required for the proposal round.');
        }

        $timezone = trim((string) ($config['timezone'] ?? config('app.timezone', 'UTC')));

        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            $this->fail('timezone', 'Choose a valid IANA timezone.');
        }

        $latePolicy = (string) ($config['late_policy'] ?? EoiTechnicalProposalRound::LATE_REJECT);
        $this->assertAllowedValue('late_policy', $latePolicy, [
            EoiTechnicalProposalRound::LATE_REJECT,
            EoiTechnicalProposalRound::LATE_ALLOW_FLAGGED,
            EoiTechnicalProposalRound::LATE_ADMIN_CAPTURE_ONLY,
        ]);

        $requirements = [
            'portal_requirement' => (string) ($config['portal_requirement'] ?? EoiTechnicalProposalRound::REQUIREMENT_REQUIRED),
            'email_requirement' => (string) ($config['email_requirement'] ?? EoiTechnicalProposalRound::REQUIREMENT_ALLOWED),
            'physical_requirement' => (string) ($config['physical_requirement'] ?? EoiTechnicalProposalRound::REQUIREMENT_NOT_ALLOWED),
        ];

        foreach ($requirements as $field => $value) {
            $this->assertAllowedValue($field, $value, [
                EoiTechnicalProposalRound::REQUIREMENT_REQUIRED,
                EoiTechnicalProposalRound::REQUIREMENT_ALLOWED,
                EoiTechnicalProposalRound::REQUIREMENT_NOT_ALLOWED,
            ]);
        }

        if (collect($requirements)->every(
            fn (string $requirement): bool => $requirement === EoiTechnicalProposalRound::REQUIREMENT_NOT_ALLOWED
        )) {
            $this->fail('portal_requirement', 'At least one proposal submission channel must be allowed.');
        }

        $opensAt = $this->dateTime($config['opens_at'] ?? null, $timezone, 'opens_at');
        $deadlineAt = $this->dateTime($config['deadline_at'] ?? null, $timezone, 'deadline_at');

        if ($opensAt && $deadlineAt && $deadlineAt->lessThanOrEqualTo($opensAt)) {
            $this->fail('deadline_at', 'The proposal deadline must be after the opening time.');
        }

        $storedPaths = [];

        try {
            $round = DB::transaction(function () use (
                $config,
                $rules,
                $uploads,
                $user,
                $procurementId,
                $timezone,
                $latePolicy,
                $requirements,
                $opensAt,
                $deadlineAt,
                &$storedPaths
            ): EoiTechnicalProposalRound {
                $procurement = Procurement::query()
                    ->withTrashed()
                    ->lockForUpdate()
                    ->findOrFail($procurementId);

                if ((bool) ($config['reuse_unnotified_round'] ?? false)) {
                    $reusableRound = EoiTechnicalProposalRound::query()
                        ->where('procurement_id', $procurement->getKey())
                        ->whereIn('status', [
                            EoiTechnicalProposalRound::STATUS_DRAFT,
                            EoiTechnicalProposalRound::STATUS_PUBLISHED,
                        ])
                        ->whereDoesntHave('communications', fn ($query) => $query
                            ->where('type', EoiReportCommunication::TYPE_PROPOSAL_INVITATION))
                        ->orderByDesc('round_number')
                        ->first();

                    if ($reusableRound) {
                        return $reusableRound;
                    }
                }

                $requestedRoundNumber = $config['round_number'] ?? null;

                if ($requestedRoundNumber !== null
                    && (! is_numeric($requestedRoundNumber) || (int) $requestedRoundNumber < 1)) {
                    $this->fail('round_number', 'The round number must be a positive whole number.');
                }

                $roundNumber = $requestedRoundNumber !== null
                    ? (int) $requestedRoundNumber
                    : ((int) EoiTechnicalProposalRound::query()
                        ->where('procurement_id', $procurement->getKey())
                        ->max('round_number')) + 1;

                if (EoiTechnicalProposalRound::query()
                    ->where('procurement_id', $procurement->getKey())
                    ->where('round_number', $roundNumber)
                    ->exists()) {
                    $this->fail('round_number', 'That technical-proposal round already exists for this procurement.');
                }

                $title = $this->requiredText(
                    $config['title'] ?? 'Technical Proposal Round '.$roundNumber,
                    'title',
                    180
                );

                $round = EoiTechnicalProposalRound::create([
                    'procurement_id' => $procurement->getKey(),
                    'round_number' => $roundNumber,
                    'title' => $title,
                    'instructions' => $this->optionalText($config['instructions'] ?? null, 20000),
                    'opens_at' => $opensAt,
                    'deadline_at' => $deadlineAt,
                    'timezone' => $timezone,
                    'late_policy' => $latePolicy,
                    ...$requirements,
                    'status' => EoiTechnicalProposalRound::STATUS_DRAFT,
                    'created_by' => $user->getKey(),
                ]);

                $usedCodes = [];

                foreach (array_values($rules) as $index => $ruleData) {
                    if (! is_array($ruleData)) {
                        $this->fail("rules.{$index}", 'Each proposal rule must be an object.');
                    }

                    $title = $this->requiredText($ruleData['title'] ?? null, "rules.{$index}.title", 255);
                    $code = $this->uniqueRuleCode($ruleData['code'] ?? null, $title, $index, $usedCodes);
                    $category = trim((string) ($ruleData['category'] ?? EoiTechnicalProposalRule::CATEGORY_GENERAL));

                    if ($category === '' || mb_strlen($category) > 60 || preg_match('/^[a-z0-9_-]+$/i', $category) !== 1) {
                        $this->fail("rules.{$index}.category", 'Rule categories may contain letters, numbers, hyphens, and underscores.');
                    }

                    EoiTechnicalProposalRule::create([
                        'round_id' => $round->getKey(),
                        'code' => $code,
                        'title' => $title,
                        'description' => $this->optionalText($ruleData['description'] ?? null, 10000),
                        'category' => strtolower($category),
                        'is_mandatory' => (bool) ($ruleData['is_mandatory'] ?? true),
                        'is_disqualifying' => (bool) ($ruleData['is_disqualifying'] ?? false),
                        'requires_acknowledgement' => (bool) ($ruleData['requires_acknowledgement'] ?? false),
                        'sort_order' => max(0, (int) ($ruleData['sort_order'] ?? $index)),
                        'created_by' => $user->getKey(),
                    ]);
                }

                foreach ($uploads as $index => $uploadData) {
                    $metadata = $this->storeUpload(
                        $uploadData['file'],
                        'eoi-technical-proposals/'.$round->getKey().'/templates',
                        self::MAX_TEMPLATE_FILE_BYTES,
                        "templates.{$index}"
                    );
                    $storedPaths[] = $metadata['file_path'];

                    EoiTechnicalProposalTemplate::create([
                        'round_id' => $round->getKey(),
                        'title' => $this->optionalText($uploadData['title'] ?? null, 255)
                            ?: pathinfo($metadata['original_filename'], PATHINFO_FILENAME),
                        'description' => $this->optionalText($uploadData['description'] ?? null, 2000),
                        'sort_order' => max(0, (int) ($uploadData['sort_order'] ?? $index)),
                        ...$metadata,
                        'uploaded_by' => $user->getKey(),
                    ]);
                }

                return $round;
            });
        } catch (Throwable $exception) {
            $this->deleteStoredPaths($storedPaths);

            throw $exception;
        }

        return $round;
    }

    /**
     * Publish once and idempotently enroll every currently qualified EOI row.
     * Existing candidate snapshots are never silently replaced.
     *
     * @param  iterable<int, array<string, mixed>>  $qualifiedRows
     */
    public function publish(
        EoiTechnicalProposalRound $round,
        iterable $qualifiedRows,
        User $user
    ): EoiTechnicalProposalRound {
        $rows = collect($qualifiedRows)->values();

        DB::transaction(function () use ($round, $rows, $user): void {
            $lockedRound = EoiTechnicalProposalRound::query()
                ->lockForUpdate()
                ->findOrFail($round->getKey());

            if (! in_array($lockedRound->status, [
                EoiTechnicalProposalRound::STATUS_DRAFT,
                EoiTechnicalProposalRound::STATUS_PUBLISHED,
            ], true)) {
                $this->fail('round', 'Only a draft or published proposal round can enroll candidates.');
            }

            if ($lockedRound->status === EoiTechnicalProposalRound::STATUS_DRAFT) {
                $lockedRound->forceFill([
                    'status' => EoiTechnicalProposalRound::STATUS_PUBLISHED,
                    'published_by' => $user->getKey(),
                    'published_at' => now(),
                    'closed_at' => null,
                ])->save();
            }

            foreach ($rows as $row) {
                if (! is_array($row) || ! $this->isQualifiedRow($row)) {
                    continue;
                }

                $applicant = $row['applicant'];

                if ((string) $applicant->procurement_id !== (string) $lockedRound->procurement_id) {
                    continue;
                }

                $applicant->loadMissing('submitter');

                EoiTechnicalProposalCandidate::query()->firstOrCreate(
                    [
                        'round_id' => $lockedRound->getKey(),
                        'form_submission_id' => $applicant->getKey(),
                    ],
                    [
                        'user_id' => $applicant->submitter?->getKey(),
                        'eoi_outcome_code' => (string) data_get($row, 'outcome.code'),
                        'eoi_outcome_label' => (string) data_get($row, 'outcome.label'),
                        'workflow_decision' => (string) ($row['next_stage'] ?? 'Technical Proposal'),
                        'status' => EoiTechnicalProposalCandidate::STATUS_INVITED,
                        'invited_at' => now(),
                    ]
                );
            }
        }, 3);

        return $round->fresh([
            'procurement',
            'rules',
            'templates',
            'candidates.applicant.submitter',
        ]);
    }

    /**
     * Store a new immutable proposal revision and its private documents.
     * Vendor timestamps are server-generated; administrators may capture the
     * actual historical receipt time for email or physical submissions.
     *
     * @param  array<int, UploadedFile|array<string, mixed>>  $files
     * @param  array{cover_note?: mixed, capture_note?: mixed}  $notes
     */
    public function createSubmission(
        EoiTechnicalProposalCandidate $candidate,
        array $files,
        User $actor,
        string $source,
        string $channel,
        DateTimeInterface|string|null $receivedAt = null,
        array $notes = []
    ): EoiTechnicalProposalSubmission {
        $this->assertAllowedValue('source', $source, [
            EoiTechnicalProposalSubmission::SOURCE_VENDOR_PORTAL,
            EoiTechnicalProposalSubmission::SOURCE_ADMIN_CAPTURE,
        ]);
        $this->assertAllowedValue('received_via', $channel, [
            EoiTechnicalProposalSubmission::CHANNEL_PORTAL,
            EoiTechnicalProposalSubmission::CHANNEL_EMAIL,
            EoiTechnicalProposalSubmission::CHANNEL_PHYSICAL,
            EoiTechnicalProposalSubmission::CHANNEL_COURIER,
            EoiTechnicalProposalSubmission::CHANNEL_OTHER,
        ]);

        $uploads = $this->normaliseUploads($files, 'documents');
        $this->assertUploadLimits($uploads, self::MAX_PROPOSAL_FILE_BYTES, 'documents', true);
        $storedPaths = [];

        try {
            $proposalSubmission = DB::transaction(function () use (
                $candidate,
                $uploads,
                $actor,
                $source,
                $channel,
                $receivedAt,
                $notes,
                &$storedPaths
            ): EoiTechnicalProposalSubmission {
                $candidateRoundId = (string) $candidate->round_id;
                $lockedRound = EoiTechnicalProposalRound::query()
                    ->lockForUpdate()
                    ->findOrFail($candidateRoundId);
                $lockedCandidate = EoiTechnicalProposalCandidate::query()
                    ->with('applicant')
                    ->lockForUpdate()
                    ->findOrFail($candidate->getKey());

                if ((string) $lockedCandidate->round_id !== (string) $lockedRound->getKey()) {
                    $this->fail('candidate', 'The applicant does not belong to this proposal round.');
                }

                $isVendorPortal = $source === EoiTechnicalProposalSubmission::SOURCE_VENDOR_PORTAL;

                if ($isVendorPortal) {
                    $this->assertVendorSubmissionActor($lockedCandidate, $actor);

                    if ($channel !== EoiTechnicalProposalSubmission::CHANNEL_PORTAL) {
                        $this->fail('received_via', 'Vendor-portal submissions must use the portal channel.');
                    }

                    if (in_array($lockedCandidate->status, [
                        EoiTechnicalProposalCandidate::STATUS_QUALIFIED,
                        EoiTechnicalProposalCandidate::STATUS_DISQUALIFIED,
                        EoiTechnicalProposalCandidate::STATUS_WITHDRAWN,
                    ], true)) {
                        $this->fail('candidate', 'This proposal stage is no longer open for applicant uploads.');
                    }
                } elseif (trim((string) ($notes['capture_note'] ?? '')) === '') {
                    $this->fail('capture_note', 'Explain how and why this proposal is being captured on behalf of the applicant.');
                }

                if (! in_array($lockedRound->status, [
                    EoiTechnicalProposalRound::STATUS_PUBLISHED,
                    EoiTechnicalProposalRound::STATUS_CLOSED,
                ], true) || ($isVendorPortal && ! $lockedRound->isPublished())) {
                    $this->fail('round', 'The technical-proposal round is not open for this submission.');
                }

                $received = $isVendorPortal
                    ? CarbonImmutable::now()
                    : $this->dateTime($receivedAt, $lockedRound->timezone, 'received_at');

                if (! $received) {
                    $this->fail('received_at', 'The actual receipt time is required for an admin-captured proposal.');
                }

                if (! $isVendorPortal && $received->greaterThan(CarbonImmutable::now()->addMinutes(5))) {
                    $this->fail('received_at', 'The receipt time cannot be in the future.');
                }

                $deadlineState = $this->deadlineState($lockedRound, $received);

                if ($isVendorPortal) {
                    if ($this->channelRequirement($lockedRound, $channel)
                        === EoiTechnicalProposalRound::REQUIREMENT_NOT_ALLOWED) {
                        $this->fail('received_via', 'Online portal submission is not allowed for this round.');
                    }

                    if ($deadlineState['is_before_open']) {
                        $this->fail('round', 'The technical-proposal submission window has not opened yet.');
                    }

                    if ($deadlineState['is_past_deadline']
                        && $lockedRound->late_policy !== EoiTechnicalProposalRound::LATE_ALLOW_FLAGGED) {
                        $this->fail('deadline', 'The technical-proposal deadline has passed.');
                    }
                }

                $revisionNumber = ((int) EoiTechnicalProposalSubmission::query()
                    ->where('candidate_id', $lockedCandidate->getKey())
                    ->max('revision_number')) + 1;
                $minutesLate = $this->minutesLate($lockedRound, $received);
                $uploadedAt = CarbonImmutable::now();

                $proposalSubmission = EoiTechnicalProposalSubmission::create([
                    'candidate_id' => $lockedCandidate->getKey(),
                    'revision_number' => $revisionNumber,
                    'source' => $source,
                    'received_via' => $channel,
                    'received_at' => $received,
                    'uploaded_at' => $uploadedAt,
                    'is_late' => $minutesLate > 0,
                    'minutes_late' => $minutesLate,
                    'cover_note' => $this->optionalText($notes['cover_note'] ?? null, 5000),
                    'capture_note' => $isVendorPortal
                        ? null
                        : $this->requiredText($notes['capture_note'] ?? null, 'capture_note', 5000),
                    'submitted_by' => $isVendorPortal ? $actor->getKey() : $lockedCandidate->user_id,
                    'captured_by' => $isVendorPortal ? null : $actor->getKey(),
                ]);

                foreach ($uploads as $index => $uploadData) {
                    $metadata = $this->storeUpload(
                        $uploadData['file'],
                        'eoi-technical-proposals/'.$lockedRound->getKey()
                            .'/candidates/'.$lockedCandidate->getKey()
                            .'/revisions/'.$revisionNumber,
                        self::MAX_PROPOSAL_FILE_BYTES,
                        "documents.{$index}"
                    );
                    $storedPaths[] = $metadata['file_path'];

                    EoiTechnicalProposalDocument::create([
                        'proposal_submission_id' => $proposalSubmission->getKey(),
                        'document_label' => $this->optionalText($uploadData['label'] ?? null, 255),
                        ...$metadata,
                        'uploaded_by' => $actor->getKey(),
                    ]);
                }

                $firstSubmittedAt = $lockedCandidate->first_submitted_at;
                $lastSubmittedAt = $lockedCandidate->last_submitted_at;

                $lockedCandidate->forceFill([
                    'first_submitted_at' => ! $firstSubmittedAt || $received->lessThan($firstSubmittedAt)
                        ? $received
                        : $firstSubmittedAt,
                    'last_submitted_at' => ! $lastSubmittedAt || $received->greaterThan($lastSubmittedAt)
                        ? $received
                        : $lastSubmittedAt,
                ])->save();

                $this->refreshCandidateStatus($lockedCandidate, $actor);

                return $proposalSubmission;
            });
        } catch (Throwable $exception) {
            $this->deleteStoredPaths($storedPaths);

            throw $exception;
        }

        return $proposalSubmission->fresh(['documents', 'candidate.round']);
    }

    public function applyRuleFinding(
        EoiTechnicalProposalCandidate $candidate,
        EoiTechnicalProposalRule $rule,
        string $finding,
        string $effect,
        ?string $rationale,
        User $actor,
        ?EoiTechnicalProposalSubmission $submission = null
    ): EoiTechnicalProposalRuleApplication {
        $this->assertAllowedValue('finding', $finding, [
            EoiTechnicalProposalRuleApplication::FINDING_COMPLIANT,
            EoiTechnicalProposalRuleApplication::FINDING_NON_COMPLIANT,
            EoiTechnicalProposalRuleApplication::FINDING_WAIVED,
            EoiTechnicalProposalRuleApplication::FINDING_NOT_APPLICABLE,
        ]);
        $this->assertAllowedValue('effect', $effect, [
            EoiTechnicalProposalRuleApplication::EFFECT_NONE,
            EoiTechnicalProposalRuleApplication::EFFECT_DISQUALIFY,
        ]);

        $rationale = $this->optionalText($rationale, 10000);

        if (in_array($finding, [
            EoiTechnicalProposalRuleApplication::FINDING_NON_COMPLIANT,
            EoiTechnicalProposalRuleApplication::FINDING_WAIVED,
        ], true) && ! $rationale) {
            $this->fail('rationale', 'A rationale is required for a non-compliant or waived rule finding.');
        }

        if ($effect === EoiTechnicalProposalRuleApplication::EFFECT_DISQUALIFY
            && ($finding !== EoiTechnicalProposalRuleApplication::FINDING_NON_COMPLIANT
                || ! $rule->is_disqualifying)) {
            $this->fail('effect', 'Only a non-compliant finding on a disqualifying rule may disqualify an applicant.');
        }

        return DB::transaction(function () use (
            $candidate,
            $rule,
            $finding,
            $effect,
            $rationale,
            $actor,
            $submission
        ): EoiTechnicalProposalRuleApplication {
            $lockedCandidate = EoiTechnicalProposalCandidate::query()
                ->lockForUpdate()
                ->findOrFail($candidate->getKey());
            $lockedRule = EoiTechnicalProposalRule::query()
                ->lockForUpdate()
                ->findOrFail($rule->getKey());

            if ((string) $lockedRule->round_id !== (string) $lockedCandidate->round_id) {
                $this->fail('rule', 'The selected rule does not belong to this applicant proposal round.');
            }

            if ($effect === EoiTechnicalProposalRuleApplication::EFFECT_DISQUALIFY
                && ($finding !== EoiTechnicalProposalRuleApplication::FINDING_NON_COMPLIANT
                    || ! $lockedRule->is_disqualifying)) {
                $this->fail('effect', 'Only a non-compliant finding on a disqualifying rule may disqualify an applicant.');
            }

            $round = EoiTechnicalProposalRound::query()->findOrFail($lockedCandidate->round_id);

            if (! in_array($round->status, [
                EoiTechnicalProposalRound::STATUS_PUBLISHED,
                EoiTechnicalProposalRound::STATUS_CLOSED,
            ], true)) {
                $this->fail('round', 'Rule findings may be recorded only after a proposal round is published.');
            }

            if ($submission) {
                $submissionExists = EoiTechnicalProposalSubmission::query()
                    ->whereKey($submission->getKey())
                    ->where('candidate_id', $lockedCandidate->getKey())
                    ->exists();

                if (! $submissionExists) {
                    $this->fail('proposal_submission_id', 'The selected proposal revision does not belong to this applicant.');
                }
            }

            EoiTechnicalProposalRuleApplication::query()
                ->where('candidate_id', $lockedCandidate->getKey())
                ->where('rule_id', $lockedRule->getKey())
                ->whereNull('revoked_at')
                ->update([
                    'revoked_by' => $actor->getKey(),
                    'revoked_at' => now(),
                    'revocation_reason' => 'Superseded by a new rule finding.',
                    'updated_at' => now(),
                ]);

            $application = EoiTechnicalProposalRuleApplication::create([
                'candidate_id' => $lockedCandidate->getKey(),
                'rule_id' => $lockedRule->getKey(),
                'proposal_submission_id' => $submission?->getKey(),
                'rule_code_snapshot' => $lockedRule->code,
                'rule_title_snapshot' => $lockedRule->title,
                'rule_is_disqualifying_snapshot' => $lockedRule->is_disqualifying,
                'finding' => $finding,
                'effect' => $effect,
                'rationale' => $rationale,
                'applied_by' => $actor->getKey(),
                'applied_at' => now(),
            ]);

            $this->refreshCandidateStatus($lockedCandidate, $actor);

            return $application->fresh(['rule', 'submission', 'applier']);
        });
    }

    public function revokeRuleFinding(
        EoiTechnicalProposalRuleApplication $application,
        string $reason,
        User $actor
    ): EoiTechnicalProposalRuleApplication {
        $reason = $this->requiredText($reason, 'revocation_reason', 10000);

        return DB::transaction(function () use ($application, $reason, $actor): EoiTechnicalProposalRuleApplication {
            $candidateId = EoiTechnicalProposalRuleApplication::query()
                ->whereKey($application->getKey())
                ->value('candidate_id');
            $candidate = EoiTechnicalProposalCandidate::query()
                ->lockForUpdate()
                ->findOrFail($candidateId);
            $lockedApplication = EoiTechnicalProposalRuleApplication::query()
                ->lockForUpdate()
                ->findOrFail($application->getKey());

            if ((string) $lockedApplication->candidate_id !== (string) $candidate->getKey()) {
                $this->fail('application', 'The rule finding applicant changed while it was being revoked.');
            }

            if (! $lockedApplication->revoked_at) {
                $lockedApplication->forceFill([
                    'revoked_by' => $actor->getKey(),
                    'revoked_at' => now(),
                    'revocation_reason' => $reason,
                ])->save();

                $this->refreshCandidateStatus($candidate, $actor);
            }

            return $lockedApplication->fresh(['rule', 'submission', 'revoker']);
        });
    }

    /**
     * @return array{
     *   is_before_open: bool,
     *   is_past_deadline: bool,
     *   is_open: bool,
     *   accepts_portal: bool,
     *   opens_at: ?CarbonInterface,
     *   deadline_at: ?CarbonInterface,
     *   at: CarbonInterface,
     *   late_policy: string
     * }
     */
    public function deadlineState(
        EoiTechnicalProposalRound $round,
        DateTimeInterface|string|null $at = null
    ): array {
        $moment = $at instanceof DateTimeInterface
            ? CarbonImmutable::instance($at)
            : ($at !== null
                ? CarbonImmutable::parse($at, $round->timezone ?: config('app.timezone', 'UTC'))
                : CarbonImmutable::now());
        $opensAt = $round->opens_at;
        $deadlineAt = $round->deadline_at;
        $isBeforeOpen = $opensAt !== null && $moment->lessThan($opensAt);
        $isPastDeadline = $deadlineAt !== null && $moment->greaterThan($deadlineAt);
        $isPublished = $round->status === EoiTechnicalProposalRound::STATUS_PUBLISHED;
        $portalAllowed = $this->channelRequirement($round, EoiTechnicalProposalSubmission::CHANNEL_PORTAL)
            !== EoiTechnicalProposalRound::REQUIREMENT_NOT_ALLOWED;
        $acceptsLatePortal = $round->late_policy === EoiTechnicalProposalRound::LATE_ALLOW_FLAGGED;

        return [
            'is_before_open' => $isBeforeOpen,
            'is_past_deadline' => $isPastDeadline,
            'is_open' => $isPublished && ! $isBeforeOpen && ! $isPastDeadline,
            'accepts_portal' => $isPublished
                && $portalAllowed
                && ! $isBeforeOpen
                && (! $isPastDeadline || $acceptsLatePortal),
            'opens_at' => $opensAt,
            'deadline_at' => $deadlineAt,
            'at' => $moment,
            'late_policy' => $round->late_policy,
        ];
    }

    public function channelRequirement(EoiTechnicalProposalRound $round, string $channel): string
    {
        return match ($channel) {
            EoiTechnicalProposalSubmission::CHANNEL_PORTAL => $round->portal_requirement,
            EoiTechnicalProposalSubmission::CHANNEL_EMAIL => $round->email_requirement,
            EoiTechnicalProposalSubmission::CHANNEL_PHYSICAL,
            EoiTechnicalProposalSubmission::CHANNEL_COURIER => $round->physical_requirement,
            EoiTechnicalProposalSubmission::CHANNEL_OTHER => EoiTechnicalProposalRound::REQUIREMENT_ALLOWED,
            default => EoiTechnicalProposalRound::REQUIREMENT_NOT_ALLOWED,
        };
    }

    /** @return array<int, string> */
    public function requiredChannels(EoiTechnicalProposalRound $round): array
    {
        return collect([
            EoiTechnicalProposalSubmission::CHANNEL_PORTAL => $round->portal_requirement,
            EoiTechnicalProposalSubmission::CHANNEL_EMAIL => $round->email_requirement,
            EoiTechnicalProposalSubmission::CHANNEL_PHYSICAL => $round->physical_requirement,
        ])
            ->filter(fn (string $requirement): bool => $requirement === EoiTechnicalProposalRound::REQUIREMENT_REQUIRED)
            ->keys()
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    public function missingRequiredChannels(EoiTechnicalProposalCandidate $candidate): array
    {
        $candidate->loadMissing(['round', 'submissions']);
        $receivedChannels = $candidate->submissions->pluck('received_via')->unique();

        return collect($this->requiredChannels($candidate->round))
            ->reject(function (string $requiredChannel) use ($receivedChannels): bool {
                if ($requiredChannel === EoiTechnicalProposalSubmission::CHANNEL_PHYSICAL) {
                    return $receivedChannels->contains(EoiTechnicalProposalSubmission::CHANNEL_PHYSICAL)
                        || $receivedChannels->contains(EoiTechnicalProposalSubmission::CHANNEL_COURIER);
                }

                return $receivedChannels->contains($requiredChannel);
            })
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    public function prohibitedReceivedChannels(EoiTechnicalProposalCandidate $candidate): array
    {
        $candidate->loadMissing(['round', 'submissions']);

        return $candidate->submissions
            ->pluck('received_via')
            ->filter(fn (string $channel): bool => $this->channelRequirement($candidate->round, $channel)
                === EoiTechnicalProposalRound::REQUIREMENT_NOT_ALLOWED)
            ->unique()
            ->values()
            ->all();
    }

    public function deriveCandidateStatus(EoiTechnicalProposalCandidate $candidate): string
    {
        if ($candidate->status === EoiTechnicalProposalCandidate::STATUS_WITHDRAWN) {
            return EoiTechnicalProposalCandidate::STATUS_WITHDRAWN;
        }

        $candidate->loadMissing(['round.rules', 'submissions', 'ruleApplications']);
        $latestSubmission = $candidate->submissions->sortByDesc('revision_number')->first();
        $currentApplications = $candidate->ruleApplications
            ->whereNull('revoked_at')
            ->filter(fn (EoiTechnicalProposalRuleApplication $application): bool => ! $application->proposal_submission_id
                || (string) $application->proposal_submission_id === (string) $latestSubmission?->getKey())
            ->values();

        if ($currentApplications->contains(
            fn (EoiTechnicalProposalRuleApplication $application): bool => $application->effect
                === EoiTechnicalProposalRuleApplication::EFFECT_DISQUALIFY
        )) {
            return EoiTechnicalProposalCandidate::STATUS_DISQUALIFIED;
        }

        if (! $latestSubmission) {
            return EoiTechnicalProposalCandidate::STATUS_INVITED;
        }

        $mandatoryRules = $candidate->round->rules->where('is_mandatory', true);

        if ($currentApplications->isNotEmpty()) {
            $currentByRule = $currentApplications->keyBy(
                fn (EoiTechnicalProposalRuleApplication $application): string => (string) $application->rule_id
            );
            $allMandatoryResolved = $mandatoryRules
                ->every(function (EoiTechnicalProposalRule $rule) use ($currentByRule): bool {
                    $application = $currentByRule->get((string) $rule->getKey());

                    return $application && in_array($application->finding, [
                        EoiTechnicalProposalRuleApplication::FINDING_COMPLIANT,
                        EoiTechnicalProposalRuleApplication::FINDING_WAIVED,
                        EoiTechnicalProposalRuleApplication::FINDING_NOT_APPLICABLE,
                    ], true);
                });

            if ($allMandatoryResolved && $this->missingRequiredChannels($candidate) === []) {
                return EoiTechnicalProposalCandidate::STATUS_QUALIFIED;
            }

            return EoiTechnicalProposalCandidate::STATUS_UNDER_REVIEW;
        }

        return $latestSubmission->is_late
            ? EoiTechnicalProposalCandidate::STATUS_LATE
            : EoiTechnicalProposalCandidate::STATUS_SUBMITTED;
    }

    private function refreshCandidateStatus(
        EoiTechnicalProposalCandidate $candidate,
        ?User $reviewer = null
    ): void {
        $candidate->unsetRelation('submissions');
        $candidate->unsetRelation('ruleApplications');
        $candidate->unsetRelation('round');
        $status = $this->deriveCandidateStatus($candidate);
        $updates = ['status' => $status];

        if ($reviewer && in_array($status, [
            EoiTechnicalProposalCandidate::STATUS_UNDER_REVIEW,
            EoiTechnicalProposalCandidate::STATUS_QUALIFIED,
            EoiTechnicalProposalCandidate::STATUS_DISQUALIFIED,
        ], true)) {
            $updates['reviewed_by'] = $reviewer->getKey();
            $updates['reviewed_at'] = now();
        }

        $candidate->forceFill($updates)->save();
    }

    private function isQualifiedRow(array $row): bool
    {
        return ($row['applicant'] ?? null) instanceof FormSubmission
            && (bool) ($row['panel_complete'] ?? false)
            && (bool) ($row['can_advance'] ?? false)
            && in_array(data_get($row, 'outcome.code'), [
                EoiQualificationService::OUTCOME_FULLY_QUALIFIED,
                EoiQualificationService::OUTCOME_AVERAGE_QUALIFIED,
            ], true);
    }

    private function assertVendorSubmissionActor(
        EoiTechnicalProposalCandidate $candidate,
        User $actor
    ): void {
        if ($actor->user_type !== 'vendor'
            || (bool) $actor->is_disabled
            || (bool) $actor->is_blacklisted
            || (string) $candidate->user_id !== (string) $actor->getKey()
            || ($candidate->applicant
                && (string) $candidate->applicant->submitted_by !== (string) $actor->getKey())) {
            $this->fail('candidate', 'This applicant is not authorized to submit to the selected proposal round.');
        }
    }

    private function minutesLate(
        EoiTechnicalProposalRound $round,
        CarbonInterface $receivedAt
    ): int {
        if (! $round->deadline_at || $receivedAt->lessThanOrEqualTo($round->deadline_at)) {
            return 0;
        }

        return (int) max(1, ceil($round->deadline_at->diffInSeconds($receivedAt) / 60));
    }

    /**
     * @param  array<int, UploadedFile|array<string, mixed>>  $entries
     * @return array<int, array<string, mixed>>
     */
    private function normaliseUploads(array $entries, string $field): array
    {
        if (count($entries) > self::MAX_FILES_PER_REQUEST) {
            $this->fail($field, 'You may upload no more than '.self::MAX_FILES_PER_REQUEST.' files at a time.');
        }

        return collect(array_values($entries))->map(function ($entry, int $index) use ($field): array {
            if ($entry instanceof UploadedFile) {
                return ['file' => $entry];
            }

            if (is_array($entry) && ($entry['file'] ?? null) instanceof UploadedFile) {
                return $entry;
            }

            $this->fail("{$field}.{$index}", 'Each document must be a valid uploaded file.');
        })->all();
    }

    /** @param  array<int, array<string, mixed>>  $uploads */
    private function assertUploadLimits(
        array $uploads,
        int $maximumFileBytes,
        string $field,
        bool $required = false
    ): void {
        if ($required && $uploads === []) {
            $this->fail($field, 'Choose at least one proposal document.');
        }

        $combinedBytes = 0;

        foreach ($uploads as $index => $uploadData) {
            /** @var UploadedFile $file */
            $file = $uploadData['file'];
            $size = max(0, (int) $file->getSize());
            $combinedBytes += $size;

            if (! $file->isValid()) {
                $this->fail("{$field}.{$index}", 'The uploaded file did not arrive successfully.');
            }

            if ($size < 1 || $size > $maximumFileBytes) {
                $maximumMb = (int) floor($maximumFileBytes / 1024 / 1024);
                $this->fail("{$field}.{$index}", "Each file must be non-empty and no larger than {$maximumMb} MB.");
            }

            $this->validatedFileType($file, "{$field}.{$index}");
        }

        if ($combinedBytes > self::MAX_COMBINED_FILE_BYTES) {
            $maximumMb = (int) floor(self::MAX_COMBINED_FILE_BYTES / 1024 / 1024);
            $this->fail($field, "The combined upload size may not exceed {$maximumMb} MB.");
        }
    }

    /** @return array{0: string, 1: string} */
    private function validatedFileType(UploadedFile $file, string $field): array
    {
        $extension = strtolower(trim((string) $file->getClientOriginalExtension()));
        $mimeType = strtolower(trim(strtok((string) $file->getMimeType(), ';') ?: ''));
        $allowedMimeTypes = self::ALLOWED_DOCUMENT_MIME_TYPES[$extension] ?? null;

        if (! $allowedMimeTypes || ! in_array($mimeType, $allowedMimeTypes, true)) {
            $this->fail(
                $field,
                'Unsupported or mismatched document type. Use PDF, Office, OpenDocument, CSV, text, JPG, or PNG files.'
            );
        }

        return [$extension, $mimeType];
    }

    /**
     * @return array{
     *   file_path: string,
     *   original_filename: string,
     *   extension: string,
     *   mime_type: string,
     *   file_size: int,
     *   sha256: string
     * }
     */
    private function storeUpload(
        UploadedFile $file,
        string $directory,
        int $maximumFileBytes,
        string $field
    ): array {
        [$extension, $mimeType] = $this->validatedFileType($file, $field);
        $size = (int) $file->getSize();

        if ($size < 1 || $size > $maximumFileBytes) {
            $this->fail($field, 'The document size is outside the permitted range.');
        }

        $sha256 = hash_file('sha256', $file->getRealPath());

        if (! is_string($sha256) || $sha256 === '') {
            throw new RuntimeException('The uploaded document checksum could not be calculated.');
        }

        $path = $file->storeAs($directory, Str::uuid().'.'.$extension, 'local');

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('The uploaded document could not be stored.');
        }

        return [
            'file_path' => $path,
            'original_filename' => $this->safeFilename($file->getClientOriginalName()),
            'extension' => $extension,
            'mime_type' => $mimeType,
            'file_size' => $size,
            'sha256' => $sha256,
        ];
    }

    /** @param  array<int, string>  $paths */
    private function deleteStoredPaths(array $paths): void
    {
        foreach (array_unique($paths) as $path) {
            Storage::disk('local')->delete($path);
        }
    }

    private function safeFilename(string $filename): string
    {
        $filename = trim(str_replace(["\0", "\r", "\n", '/', '\\'], '-', $filename));

        return Str::limit($filename !== '' ? $filename : 'document', 240, '');
    }

    /** @param  array<int, string>  $allowed */
    private function assertAllowedValue(string $field, string $value, array $allowed): void
    {
        if (! in_array($value, $allowed, true)) {
            $this->fail($field, 'The selected value is invalid.');
        }
    }

    private function requiredText(mixed $value, string $field, int $maximumLength): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            $this->fail($field, 'This field is required.');
        }

        if (mb_strlen($value) > $maximumLength) {
            $this->fail($field, "This field may not exceed {$maximumLength} characters.");
        }

        return $value;
    }

    private function optionalText(mixed $value, int $maximumLength): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $maximumLength) {
            throw ValidationException::withMessages([
                'text' => "This field may not exceed {$maximumLength} characters.",
            ]);
        }

        return $value;
    }

    private function dateTime(mixed $value, string $timezone, string $field): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if ($value instanceof DateTimeInterface) {
                return CarbonImmutable::instance($value)->utc();
            }

            return CarbonImmutable::parse((string) $value, $timezone)->utc();
        } catch (Throwable) {
            $this->fail($field, 'Enter a valid date and time.');
        }
    }

    /**
     * @param  array<int, string>  $usedCodes
     */
    private function uniqueRuleCode(
        mixed $requestedCode,
        string $title,
        int $index,
        array &$usedCodes
    ): string {
        $base = strtoupper(Str::slug(trim((string) ($requestedCode ?: $title)), '_'));
        $base = substr($base !== '' ? $base : 'RULE_'.($index + 1), 0, 40);
        $code = $base;
        $suffix = 2;

        while (in_array($code, $usedCodes, true)) {
            $tail = '_'.$suffix++;
            $code = substr($base, 0, 40 - strlen($tail)).$tail;
        }

        $usedCodes[] = $code;

        return $code;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
