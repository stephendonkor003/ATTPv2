<?php

use App\Http\Controllers\EvaluationAssignmentController;
use App\Http\Controllers\EvaluationSubmissionController;
use App\Models\EoiTechnicalProposalCandidate;
use App\Models\EoiTechnicalProposalDocument;
use App\Models\EoiTechnicalProposalRound;
use App\Models\EoiTechnicalProposalSubmission;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationSubmission;
use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Services\EoiQualificationService;
use App\Services\EvaluationAssignmentTargetResolver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

function proposalAssignmentMethodSource(string $class, string $method): string
{
    $reflection = new ReflectionMethod($class, $method);
    $lines = file($reflection->getFileName());

    return implode('', array_slice(
        $lines,
        $reflection->getStartLine() - 1,
        $reflection->getEndLine() - $reflection->getStartLine() + 1
    ));
}

/**
 * Build a database-free, relation-loaded technical-proposal target.
 *
 * @return array{
 *     procurement: Procurement,
 *     round: EoiTechnicalProposalRound,
 *     applicant: FormSubmission,
 *     candidate: EoiTechnicalProposalCandidate,
 *     proposal: EoiTechnicalProposalSubmission,
 *     document: EoiTechnicalProposalDocument
 * }
 */
function proposalAssignmentTargetFixture(): array
{
    $procurement = (new Procurement)->forceFill([
        'id' => '10000000-0000-4000-8000-000000000001',
        'title' => 'Technical proposal target fixture',
    ]);
    $round = (new EoiTechnicalProposalRound)->forceFill([
        'id' => '20000000-0000-4000-8000-000000000001',
        'procurement_id' => $procurement->getKey(),
        'round_number' => 2,
        'status' => EoiTechnicalProposalRound::STATUS_PUBLISHED,
    ]);
    $applicant = (new FormSubmission)->forceFill([
        'id' => '30000000-0000-4000-8000-000000000001',
        'procurement_id' => $procurement->getKey(),
        'status' => FormSubmission::STATUS_TECHNICAL_EVALUATION,
    ]);
    $candidate = (new EoiTechnicalProposalCandidate)->forceFill([
        'id' => '40000000-0000-4000-8000-000000000001',
        'round_id' => $round->getKey(),
        'form_submission_id' => $applicant->getKey(),
        'eoi_outcome_code' => EoiQualificationService::OUTCOME_FULLY_QUALIFIED,
        'status' => EoiTechnicalProposalCandidate::STATUS_QUALIFIED,
    ]);
    $proposal = (new EoiTechnicalProposalSubmission)->forceFill([
        'id' => '50000000-0000-4000-8000-000000000001',
        'candidate_id' => $candidate->getKey(),
        'revision_number' => 2,
        'source' => EoiTechnicalProposalSubmission::SOURCE_VENDOR_PORTAL,
        'received_via' => EoiTechnicalProposalSubmission::CHANNEL_PORTAL,
    ]);
    $document = (new EoiTechnicalProposalDocument)->forceFill([
        'id' => '60000000-0000-4000-8000-000000000001',
        'proposal_submission_id' => $proposal->getKey(),
        'original_filename' => 'technical-proposal.pdf',
        'file_path' => 'eoi-technical-proposals/fixture/proposal.pdf',
    ]);

    $round->setRelation('procurement', $procurement);
    $candidate->setRelation('round', $round);
    $candidate->setRelation('applicant', $applicant);
    $candidate->setRelation('latestSubmission', $proposal);
    $proposal->setRelation('candidate', $candidate);
    $proposal->setRelation('documents', new EloquentCollection([$document]));

    return compact('procurement', 'round', 'applicant', 'candidate', 'proposal', 'document');
}

it('defines an explicit reusable assignment-target resolver contract', function () {
    $resolver = new ReflectionClass(EvaluationAssignmentTargetResolver::class);

    foreach ([
        'latestAssignableRound',
        'technicalProposalOptions',
        'technicalProposalContext',
        'eligibleTargetQuery',
        'eligibleTargets',
        'targetForApplicant',
        'isEligible',
        'assertEligible',
        'targetsForAssignment',
        'technicalCandidateIsEligible',
    ] as $method) {
        expect($resolver->hasMethod($method))->toBeTrue("Missing target resolver method: {$method}")
            ->and($resolver->getMethod($method)->isPublic())->toBeTrue("Target resolver method must be public: {$method}");
    }

    expect(EvaluationAssignment::STAGE_APPLICATION)->toBe('application')
        ->and(EvaluationAssignment::STAGE_TECHNICAL_PROPOSAL)->toBe('technical_proposal')
        ->and(EvaluationAssignment::WORKFLOW_STAGES)->toBe([
            EvaluationAssignment::STAGE_APPLICATION,
            EvaluationAssignment::STAGE_TECHNICAL_PROPOSAL,
        ]);
});

it('accepts only a qualified exact-round technical target with a documented latest revision', function () {
    $resolver = new EvaluationAssignmentTargetResolver;
    $valid = proposalAssignmentTargetFixture();

    expect($resolver->technicalCandidateIsEligible(
        $valid['candidate'],
        $valid['round'],
        $valid['procurement']
    ))->toBeTrue();

    $averageQualified = proposalAssignmentTargetFixture();
    $averageQualified['candidate']->eoi_outcome_code = EoiQualificationService::OUTCOME_AVERAGE_QUALIFIED;
    expect($resolver->technicalCandidateIsEligible(
        $averageQualified['candidate'],
        $averageQualified['round'],
        $averageQualified['procurement']
    ))->toBeTrue();

    $invalidCases = [
        'proposal compliance has not qualified the candidate' => function (array $target): array {
            $target['candidate']->status = EoiTechnicalProposalCandidate::STATUS_UNDER_REVIEW;

            return $target;
        },
        'the EOI outcome snapshot is not qualified' => function (array $target): array {
            $target['candidate']->eoi_outcome_code = EoiQualificationService::OUTCOME_NOT_QUALIFIED;

            return $target;
        },
        'the applicant lifecycle has not reached technical evaluation' => function (array $target): array {
            $target['applicant']->status = FormSubmission::STATUS_TECHNICAL_PROPOSAL_SUBMITTED;

            return $target;
        },
        'the candidate belongs to another proposal round' => function (array $target): array {
            $target['candidate']->round_id = '20000000-0000-4000-8000-000000000099';

            return $target;
        },
        'the candidate points to another applicant' => function (array $target): array {
            $target['candidate']->form_submission_id = '30000000-0000-4000-8000-000000000099';

            return $target;
        },
        'the proposal round is not assignable' => function (array $target): array {
            $target['round']->status = EoiTechnicalProposalRound::STATUS_CANCELLED;

            return $target;
        },
        'no proposal revision exists' => function (array $target): array {
            $target['candidate']->setRelation('latestSubmission', null);

            return $target;
        },
        'the latest revision number is invalid' => function (array $target): array {
            $target['proposal']->revision_number = 0;

            return $target;
        },
        'the latest proposal revision has no document' => function (array $target): array {
            $target['proposal']->setRelation('documents', new EloquentCollection);

            return $target;
        },
        'the latest revision belongs to another candidate' => function (array $target): array {
            $target['proposal']->candidate_id = '40000000-0000-4000-8000-000000000099';

            return $target;
        },
    ];

    foreach ($invalidCases as $reason => $mutate) {
        $target = $mutate(proposalAssignmentTargetFixture());

        expect($resolver->technicalCandidateIsEligible(
            $target['candidate'],
            $target['round'],
            $target['procurement']
        ))->toBeFalse($reason);
    }

    $wrongProcurement = proposalAssignmentTargetFixture();
    $otherProcurement = (new Procurement)->forceFill([
        'id' => '10000000-0000-4000-8000-000000000099',
    ]);
    expect($resolver->technicalCandidateIsEligible(
        $wrongProcurement['candidate'],
        $wrongProcurement['round'],
        $otherProcurement
    ))->toBeFalse('A candidate from another procurement crossed the assignment boundary.');
});

it('uses the target resolver for assignment creation worklists and every mutable evaluator entry point', function () {
    $store = proposalAssignmentMethodSource(EvaluationAssignmentController::class, 'store');
    $worklist = proposalAssignmentMethodSource(EvaluationSubmissionController::class, 'renderEvaluationWorklist');
    $start = proposalAssignmentMethodSource(EvaluationSubmissionController::class, 'start');
    $save = proposalAssignmentMethodSource(EvaluationSubmissionController::class, 'saveScores');
    $submit = proposalAssignmentMethodSource(EvaluationSubmissionController::class, 'submit');
    $readyGuard = proposalAssignmentMethodSource(EvaluationSubmissionController::class, 'assertApplicantReadyForEvaluation');
    $statusSync = proposalAssignmentMethodSource(EvaluationSubmissionController::class, 'synchronizeAssignmentStatus');
    $assignmentTargets = proposalAssignmentMethodSource(EvaluationSubmissionController::class, 'applicationsForAssignment');

    expect($store)
        ->toContain('technical_proposal_procurement')
        ->toContain('technical_proposal_submission')
        ->toContain('workflow_stage')
        ->toContain('technical_proposal_round_id');
    expect(
        str_contains($store, 'assertEligible(')
        || str_contains($store, 'targetForApplicant(')
    )->toBeTrue('Specific proposal assignments must be resolved and validated server-side.');

    expect($worklist)->toContain('applicationsForAssignment(')
        ->and($assignmentTargets)->toContain('targetsForAssignment(')
        ->and($statusSync)->toContain('targetsForAssignment(');

    foreach (['start' => $start, 'saveScores' => $save, 'submit' => $submit] as $method => $source) {
        preg_match('/assertApplicantReadyForEvaluation\s*\(([^;]+)\)/s', $source, $guardCall);

        expect($guardCall[1] ?? '')
            ->toContain('$assignment')
            ->toContain('$applicant');
    }

    expect($readyGuard)
        ->toContain('EvaluationAssignment $assignment');
    expect(
        str_contains($readyGuard, 'assertEligible(')
        || str_contains($readyGuard, 'isEligible(')
        || str_contains($readyGuard, 'targetForApplicant(')
    )->toBeTrue('The mutable-action guard must use the shared target resolver.');
});

it('persists the workflow round and immutable proposal revision in evaluation identity', function () {
    $assignment = new EvaluationAssignment;
    $evaluationSubmission = new EvaluationSubmission;
    $assignmentModel = file_get_contents(dirname(__DIR__, 2).'/app/Models/EvaluationAssignment.php');
    $submissionModel = file_get_contents(dirname(__DIR__, 2).'/app/Models/EvaluationSubmission.php');
    $submissionController = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/EvaluationSubmissionController.php');
    $migrationSources = collect(glob(dirname(__DIR__, 2).'/database/migrations/*.php'))
        ->map(fn (string $path): string => file_get_contents($path));
    $identityMigration = $migrationSources->first(
        fn (string $source): bool => str_contains($source, "Schema::table('evaluation_assignments'")
            && str_contains($source, 'workflow_stage')
            && str_contains($source, 'evaluation_assignment_id')
            && str_contains($source, 'technical_proposal_candidate_id')
            && str_contains($source, 'technical_proposal_submission_id')
    );

    expect($assignment->getFillable())
        ->toContain('workflow_stage', 'technical_proposal_round_id')
        ->and($assignmentModel)
        ->toContain('function technicalProposalRound(')
        ->and($evaluationSubmission->getFillable())
        ->toContain(
            'evaluation_assignment_id',
            'technical_proposal_candidate_id',
            'technical_proposal_submission_id'
        )
        ->and($submissionModel)
        ->toContain('function assignment(')
        ->toContain('function technicalProposalCandidate(')
        ->toContain('function technicalProposalSubmission(')
        ->and($submissionController)
        ->toContain("'evaluation_assignment_id'")
        ->toContain("'technical_proposal_candidate_id'")
        ->toContain("'technical_proposal_submission_id'")
        ->and($identityMigration)->not->toBeNull();
});

it('presents procurement identity and explicit application or technical-proposal targets', function () {
    $view = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/evaluations/assign-hub.blade.php'
    );

    expect($view)
        ->toContain('Procurement title')
        ->toContain('id="procurement-title-{{ $procurement->id }}"')
        ->toContain('readonly')
        ->toContain('name="procurement_id"')
        ->toContain('name="assignment_type"')
        ->toContain('value="procurement"')
        ->toContain('value="submission"')
        ->toContain('value="technical_proposal_procurement"')
        ->toContain('value="technical_proposal_submission"')
        ->toContain('name="technical_proposal_round_id"')
        ->toContain('name="submission_id"')
        ->toContain('data-assignment-form')
        ->toContain('data-assignment-target-group')
        ->toContain('data-assignment-target="application"')
        ->toContain('data-assignment-target="technical_proposal"')
        ->toContain('data-candidate-id')
        ->toContain('data-assignment-scope-help')
        ->toContain('assignment-shortlist-note')
        ->toContain('assignment-shortlist-badge')
        ->toContain('EoiTechnicalProposalCandidate::STATUS_QUALIFIED');
});
