<?php

use App\Models\EoiTechnicalProposalCandidate;
use App\Models\EoiTechnicalProposalRound;
use App\Models\EoiTechnicalProposalRule;
use App\Models\EoiTechnicalProposalRuleApplication;
use App\Models\EoiTechnicalProposalSubmission;
use App\Services\EoiTechnicalProposalService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;

class InMemoryEoiTechnicalProposalCandidate extends EoiTechnicalProposalCandidate
{
    /** All relations used by the pure helpers are injected by the fixture. */
    public function loadMissing($relations)
    {
        return $this;
    }
}

function technicalProposalRoundFixture(array $attributes = [], array $rules = []): EoiTechnicalProposalRound
{
    $round = (new EoiTechnicalProposalRound)->setRawAttributes(array_merge([
        'id' => (string) Str::uuid(),
        'status' => EoiTechnicalProposalRound::STATUS_PUBLISHED,
        'timezone' => 'Africa/Nairobi',
        'opens_at' => CarbonImmutable::parse('2026-09-01 06:00:00', 'UTC'),
        'deadline_at' => CarbonImmutable::parse('2026-09-01 12:00:00', 'UTC'),
        'late_policy' => EoiTechnicalProposalRound::LATE_REJECT,
        'portal_requirement' => EoiTechnicalProposalRound::REQUIREMENT_REQUIRED,
        'email_requirement' => EoiTechnicalProposalRound::REQUIREMENT_ALLOWED,
        'physical_requirement' => EoiTechnicalProposalRound::REQUIREMENT_NOT_ALLOWED,
    ], $attributes));
    $round->setRelation('rules', new EloquentCollection($rules));

    return $round;
}

function technicalProposalRuleFixture(bool $mandatory = true): EoiTechnicalProposalRule
{
    return (new EoiTechnicalProposalRule)->setRawAttributes([
        'id' => (string) Str::uuid(),
        'code' => Str::upper(Str::random(8)),
        'title' => 'Proposal compliance rule',
        'is_mandatory' => $mandatory,
        'is_disqualifying' => true,
    ]);
}

function technicalProposalSubmissionFixture(
    int $revision,
    string $channel = EoiTechnicalProposalSubmission::CHANNEL_PORTAL,
    bool $late = false
): EoiTechnicalProposalSubmission {
    return (new EoiTechnicalProposalSubmission)->setRawAttributes([
        'id' => (string) Str::uuid(),
        'revision_number' => $revision,
        'received_via' => $channel,
        'is_late' => $late,
    ]);
}

function technicalProposalRuleApplicationFixture(
    EoiTechnicalProposalRule $rule,
    string $finding,
    string $effect = EoiTechnicalProposalRuleApplication::EFFECT_NONE,
    ?EoiTechnicalProposalSubmission $submission = null,
    bool $revoked = false
): EoiTechnicalProposalRuleApplication {
    return (new EoiTechnicalProposalRuleApplication)->setRawAttributes([
        'id' => (string) Str::uuid(),
        'rule_id' => $rule->getKey(),
        'proposal_submission_id' => $submission?->getKey(),
        'finding' => $finding,
        'effect' => $effect,
        'revoked_at' => $revoked ? CarbonImmutable::parse('2026-09-02 09:00:00', 'UTC') : null,
    ]);
}

function technicalProposalCandidateFixture(
    EoiTechnicalProposalRound $round,
    array $submissions = [],
    array $applications = [],
    string $status = EoiTechnicalProposalCandidate::STATUS_INVITED
): EoiTechnicalProposalCandidate {
    $candidate = (new InMemoryEoiTechnicalProposalCandidate)->setRawAttributes([
        'id' => (string) Str::uuid(),
        'round_id' => $round->getKey(),
        'form_submission_id' => (string) Str::uuid(),
        'status' => $status,
    ]);
    $candidate->setRelation('round', $round);
    $candidate->setRelation('submissions', new EloquentCollection($submissions));
    $candidate->setRelation('ruleApplications', new EloquentCollection($applications));

    return $candidate;
}

it('treats the exact deadline as on time and only a later instant as late', function () {
    $service = new EoiTechnicalProposalService;
    $round = technicalProposalRoundFixture();
    $deadline = CarbonImmutable::parse('2026-09-01 12:00:00', 'UTC');

    $atDeadline = $service->deadlineState($round, $deadline);
    $oneSecondLate = $service->deadlineState($round, $deadline->addSecond());

    expect($atDeadline)
        ->is_past_deadline->toBeFalse()
        ->is_open->toBeTrue()
        ->accepts_portal->toBeTrue()
        ->and($oneSecondLate)
        ->is_past_deadline->toBeTrue()
        ->is_open->toBeFalse()
        ->accepts_portal->toBeFalse();
});

it('allows a flagged late portal upload without describing the strict window as open', function () {
    $service = new EoiTechnicalProposalService;
    $round = technicalProposalRoundFixture([
        'late_policy' => EoiTechnicalProposalRound::LATE_ALLOW_FLAGGED,
    ]);

    $state = $service->deadlineState($round, '2026-09-01 15:00:01 Africa/Nairobi');

    expect($state)
        ->is_past_deadline->toBeTrue()
        ->is_open->toBeFalse()
        ->accepts_portal->toBeTrue()
        ->late_policy->toBe(EoiTechnicalProposalRound::LATE_ALLOW_FLAGGED);
});

it('maps configured channel requirements and treats courier as a physical delivery', function () {
    $service = new EoiTechnicalProposalService;
    $round = technicalProposalRoundFixture([
        'portal_requirement' => EoiTechnicalProposalRound::REQUIREMENT_REQUIRED,
        'email_requirement' => EoiTechnicalProposalRound::REQUIREMENT_ALLOWED,
        'physical_requirement' => EoiTechnicalProposalRound::REQUIREMENT_NOT_ALLOWED,
    ]);

    expect($service->channelRequirement($round, EoiTechnicalProposalSubmission::CHANNEL_PORTAL))
        ->toBe(EoiTechnicalProposalRound::REQUIREMENT_REQUIRED)
        ->and($service->channelRequirement($round, EoiTechnicalProposalSubmission::CHANNEL_EMAIL))
        ->toBe(EoiTechnicalProposalRound::REQUIREMENT_ALLOWED)
        ->and($service->channelRequirement($round, EoiTechnicalProposalSubmission::CHANNEL_PHYSICAL))
        ->toBe(EoiTechnicalProposalRound::REQUIREMENT_NOT_ALLOWED)
        ->and($service->channelRequirement($round, EoiTechnicalProposalSubmission::CHANNEL_COURIER))
        ->toBe(EoiTechnicalProposalRound::REQUIREMENT_NOT_ALLOWED)
        ->and($service->channelRequirement($round, EoiTechnicalProposalSubmission::CHANNEL_OTHER))
        ->toBe(EoiTechnicalProposalRound::REQUIREMENT_ALLOWED)
        ->and($service->channelRequirement($round, 'unsupported-channel'))
        ->toBe(EoiTechnicalProposalRound::REQUIREMENT_NOT_ALLOWED)
        ->and($service->requiredChannels($round))
        ->toBe([EoiTechnicalProposalSubmission::CHANNEL_PORTAL]);
});

it('reports each missing required channel and accepts courier for a physical-copy requirement', function () {
    $service = new EoiTechnicalProposalService;
    $round = technicalProposalRoundFixture([
        'portal_requirement' => EoiTechnicalProposalRound::REQUIREMENT_REQUIRED,
        'email_requirement' => EoiTechnicalProposalRound::REQUIREMENT_NOT_ALLOWED,
        'physical_requirement' => EoiTechnicalProposalRound::REQUIREMENT_REQUIRED,
    ]);
    $portal = technicalProposalSubmissionFixture(1);
    $candidate = technicalProposalCandidateFixture($round, [$portal]);

    expect($service->missingRequiredChannels($candidate))
        ->toBe([EoiTechnicalProposalSubmission::CHANNEL_PHYSICAL]);

    $courier = technicalProposalSubmissionFixture(
        2,
        EoiTechnicalProposalSubmission::CHANNEL_COURIER
    );
    $candidate->setRelation('submissions', new EloquentCollection([$portal, $courier]));

    expect($service->missingRequiredChannels($candidate))->toBe([]);
});

it('identifies every prohibited channel while preserving the actual receipt history', function () {
    $service = new EoiTechnicalProposalService;
    $round = technicalProposalRoundFixture([
        'portal_requirement' => EoiTechnicalProposalRound::REQUIREMENT_ALLOWED,
        'email_requirement' => EoiTechnicalProposalRound::REQUIREMENT_NOT_ALLOWED,
        'physical_requirement' => EoiTechnicalProposalRound::REQUIREMENT_NOT_ALLOWED,
    ]);
    $candidate = technicalProposalCandidateFixture($round, [
        technicalProposalSubmissionFixture(1, EoiTechnicalProposalSubmission::CHANNEL_EMAIL),
        technicalProposalSubmissionFixture(2, EoiTechnicalProposalSubmission::CHANNEL_COURIER),
    ]);

    expect($service->prohibitedReceivedChannels($candidate))
        ->toBe([
            EoiTechnicalProposalSubmission::CHANNEL_EMAIL,
            EoiTechnicalProposalSubmission::CHANNEL_COURIER,
        ]);
});

it('derives invited submitted and late states from immutable proposal revisions', function () {
    $service = new EoiTechnicalProposalService;
    $round = technicalProposalRoundFixture([
        'portal_requirement' => EoiTechnicalProposalRound::REQUIREMENT_ALLOWED,
    ]);

    expect($service->deriveCandidateStatus(technicalProposalCandidateFixture($round)))
        ->toBe(EoiTechnicalProposalCandidate::STATUS_INVITED)
        ->and($service->deriveCandidateStatus(technicalProposalCandidateFixture(
            $round,
            [technicalProposalSubmissionFixture(1)]
        )))->toBe(EoiTechnicalProposalCandidate::STATUS_SUBMITTED)
        ->and($service->deriveCandidateStatus(technicalProposalCandidateFixture(
            $round,
            [technicalProposalSubmissionFixture(1, late: true)]
        )))->toBe(EoiTechnicalProposalCandidate::STATUS_LATE);
});

it('derives disqualified only from an active disqualifying effect', function () {
    $service = new EoiTechnicalProposalService;
    $rule = technicalProposalRuleFixture();
    $round = technicalProposalRoundFixture([], [$rule]);
    $submission = technicalProposalSubmissionFixture(1);
    $active = technicalProposalRuleApplicationFixture(
        $rule,
        EoiTechnicalProposalRuleApplication::FINDING_NON_COMPLIANT,
        EoiTechnicalProposalRuleApplication::EFFECT_DISQUALIFY,
        $submission
    );
    $revoked = technicalProposalRuleApplicationFixture(
        $rule,
        EoiTechnicalProposalRuleApplication::FINDING_NON_COMPLIANT,
        EoiTechnicalProposalRuleApplication::EFFECT_DISQUALIFY,
        $submission,
        true
    );

    expect($service->deriveCandidateStatus(technicalProposalCandidateFixture(
        $round,
        [$submission],
        [$active]
    )))->toBe(EoiTechnicalProposalCandidate::STATUS_DISQUALIFIED)
        ->and($service->deriveCandidateStatus(technicalProposalCandidateFixture(
            $round,
            [$submission],
            [$revoked]
        )))->toBe(EoiTechnicalProposalCandidate::STATUS_SUBMITTED);
});

it('keeps incomplete findings under review and qualifies only when all mandatory rules and channels resolve', function () {
    $service = new EoiTechnicalProposalService;
    $firstRule = technicalProposalRuleFixture();
    $secondRule = technicalProposalRuleFixture();
    $round = technicalProposalRoundFixture([
        'portal_requirement' => EoiTechnicalProposalRound::REQUIREMENT_REQUIRED,
        'email_requirement' => EoiTechnicalProposalRound::REQUIREMENT_NOT_ALLOWED,
        'physical_requirement' => EoiTechnicalProposalRound::REQUIREMENT_REQUIRED,
    ], [$firstRule, $secondRule]);
    $portal = technicalProposalSubmissionFixture(1);
    $courier = technicalProposalSubmissionFixture(2, EoiTechnicalProposalSubmission::CHANNEL_COURIER);
    $firstResolved = technicalProposalRuleApplicationFixture(
        $firstRule,
        EoiTechnicalProposalRuleApplication::FINDING_COMPLIANT
    );
    $secondResolved = technicalProposalRuleApplicationFixture(
        $secondRule,
        EoiTechnicalProposalRuleApplication::FINDING_WAIVED
    );

    expect($service->deriveCandidateStatus(technicalProposalCandidateFixture(
        $round,
        [$portal, $courier],
        [$firstResolved]
    )))->toBe(EoiTechnicalProposalCandidate::STATUS_UNDER_REVIEW)
        ->and($service->deriveCandidateStatus(technicalProposalCandidateFixture(
            $round,
            [$portal, $courier],
            [$firstResolved, $secondResolved]
        )))->toBe(EoiTechnicalProposalCandidate::STATUS_QUALIFIED);

    $missingPhysical = technicalProposalCandidateFixture(
        $round,
        [$portal],
        [$firstResolved, $secondResolved]
    );
    expect($service->deriveCandidateStatus($missingPhysical))
        ->toBe(EoiTechnicalProposalCandidate::STATUS_UNDER_REVIEW);
});

it('allows an optional-only checklist to resolve after its recorded findings and channels pass', function () {
    $service = new EoiTechnicalProposalService;
    $optionalRule = technicalProposalRuleFixture(false);
    $round = technicalProposalRoundFixture([
        'portal_requirement' => EoiTechnicalProposalRound::REQUIREMENT_ALLOWED,
    ], [$optionalRule]);
    $submission = technicalProposalSubmissionFixture(1);
    $finding = technicalProposalRuleApplicationFixture(
        $optionalRule,
        EoiTechnicalProposalRuleApplication::FINDING_COMPLIANT,
        submission: $submission
    );

    expect($service->deriveCandidateStatus(technicalProposalCandidateFixture(
        $round,
        [$submission],
        [$finding]
    )))->toBe(EoiTechnicalProposalCandidate::STATUS_QUALIFIED);
});

it('preserves a withdrawn candidate regardless of later submission or finding data', function () {
    $service = new EoiTechnicalProposalService;
    $rule = technicalProposalRuleFixture();
    $round = technicalProposalRoundFixture([], [$rule]);
    $submission = technicalProposalSubmissionFixture(1);
    $effect = technicalProposalRuleApplicationFixture(
        $rule,
        EoiTechnicalProposalRuleApplication::FINDING_NON_COMPLIANT,
        EoiTechnicalProposalRuleApplication::EFFECT_DISQUALIFY,
        $submission
    );
    $candidate = technicalProposalCandidateFixture(
        $round,
        [$submission],
        [$effect],
        EoiTechnicalProposalCandidate::STATUS_WITHDRAWN
    );

    expect($service->deriveCandidateStatus($candidate))
        ->toBe(EoiTechnicalProposalCandidate::STATUS_WITHDRAWN);
});

it('keeps proposal files private, rejects dangerous formats, and retains download hardening', function () {
    $root = dirname(__DIR__, 2);
    $serviceSource = file_get_contents($root.'/app/Services/EoiTechnicalProposalService.php');
    $adminDownloads = file_get_contents($root.'/app/Http/Controllers/EoiReportCommunicationController.php');
    $vendorDownloads = file_get_contents($root.'/app/Http/Controllers/Vendor/EoiCommunicationController.php');
    $mimeTypes = (new ReflectionClass(EoiTechnicalProposalService::class))
        ->getReflectionConstant('ALLOWED_DOCUMENT_MIME_TYPES')
        ->getValue();

    expect($serviceSource)
        ->toContain("'eoi-technical-proposals/'")
        ->toContain("Storage::disk('local')->delete")
        ->toContain("Str::uuid().'.'.\$extension")
        ->and(array_keys($mimeTypes))
        ->toContain('pdf', 'docx', 'xlsx', 'csv', 'odt', 'jpg', 'png')
        ->not->toContain('exe', 'js', 'html', 'svg', 'docm', 'xlsm')
        ->and($adminDownloads)
        ->toContain("'Cache-Control' => 'private, no-store, max-age=0'")
        ->toContain("'X-Content-Type-Options' => 'nosniff'")
        ->and($vendorDownloads)
        ->toContain("'Cache-Control' => 'private, no-store, max-age=0'")
        ->toContain("'X-Content-Type-Options' => 'nosniff'");
});

it('declares the complete private workflow ownership graph and immutable rule snapshots', function () {
    $root = dirname(__DIR__, 2);
    $round = file_get_contents($root.'/app/Models/EoiTechnicalProposalRound.php');
    $candidate = file_get_contents($root.'/app/Models/EoiTechnicalProposalCandidate.php');
    $submission = file_get_contents($root.'/app/Models/EoiTechnicalProposalSubmission.php');
    $rule = file_get_contents($root.'/app/Models/EoiTechnicalProposalRule.php');
    $application = file_get_contents($root.'/app/Models/EoiTechnicalProposalRuleApplication.php');
    $migration = file_get_contents($root.'/database/migrations/2026_08_28_000002_create_eoi_technical_proposal_workflow.php');

    expect($round)
        ->toContain("hasMany(EoiTechnicalProposalRule::class, 'round_id')")
        ->toContain("hasMany(EoiTechnicalProposalTemplate::class, 'round_id')")
        ->toContain("hasMany(EoiTechnicalProposalCandidate::class, 'round_id')")
        ->and($candidate)
        ->toContain("belongsTo(FormSubmission::class, 'form_submission_id')")
        ->toContain("hasMany(EoiTechnicalProposalSubmission::class, 'candidate_id')")
        ->toContain("hasMany(EoiTechnicalProposalRuleApplication::class, 'candidate_id')")
        ->and($submission)
        ->toContain("hasMany(EoiTechnicalProposalDocument::class, 'proposal_submission_id')")
        ->and($rule)
        ->toContain("hasMany(EoiTechnicalProposalRuleApplication::class, 'rule_id')")
        ->and($application)
        ->toContain("belongsTo(EoiTechnicalProposalSubmission::class, 'proposal_submission_id')")
        ->and($migration)
        ->toContain("'rule_code_snapshot'")
        ->toContain("'rule_title_snapshot'")
        ->toContain("'rule_is_disqualifying_snapshot'")
        ->toContain("'revoked_at'")
        ->toContain('eoi_tp_finding_one_active_uq')
        ->toContain('WHERE revoked_at IS NULL')
        ->toContain("'sha256', 64");
});
