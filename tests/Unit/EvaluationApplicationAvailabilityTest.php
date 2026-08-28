<?php

use App\Models\FormSubmission;

it('keeps assigned applications available throughout the evaluation lifecycle', function (mixed $status) {
    $submission = new FormSubmission(['status' => $status]);

    expect($submission->isAvailableForEvaluation())->toBeTrue();
})->with([
    'legacy status' => null,
    'submitted' => FormSubmission::STATUS_SUBMITTED,
    'under review' => 'under_review',
    'prescreen passed' => 'prescreen_passed',
    'evaluated' => 'evaluated',
    'site visit completed' => 'site_visit_completed',
    'approved' => 'approved',
    'selected' => 'selected',
]);

it('blocks application states that must not receive evaluation input', function (string $status) {
    $submission = new FormSubmission(['status' => $status]);

    expect($submission->isAvailableForEvaluation())->toBeFalse();
})->with([
    'draft' => 'draft',
    'revision requested' => FormSubmission::STATUS_REVISION_REQUESTED,
    'withdrawn' => FormSubmission::STATUS_WITHDRAWN,
    'prescreen failed' => 'prescreen_failed',
]);

it('keeps broad lifecycle availability while delegating workflow targets to the assignment resolver', function () {
    $root = dirname(__DIR__, 2);
    $model = file_get_contents($root.'/app/Models/FormSubmission.php');
    $workspace = file_get_contents($root.'/app/Http/Controllers/EvaluationSubmissionController.php');
    $targetResolver = file_get_contents($root.'/app/Services/EvaluationAssignmentTargetResolver.php');
    $thinkTankWorkspace = file_get_contents($root.'/app/Http/Controllers/ThinkTankProcurementPlanController.php');

    expect($model)
        ->toContain('public const EVALUATION_BLOCKED_STATUSES')
        ->toContain('public function scopeAvailableForEvaluation(Builder $query): Builder')
        ->and($targetResolver)
        ->toContain('->availableForEvaluation()')
        ->toContain('targetsForAssignment(')
        ->toContain('EoiTechnicalProposalCandidate::STATUS_QUALIFIED')
        ->toContain('FormSubmission::STATUS_TECHNICAL_EVALUATION')
        ->and($workspace)
        ->toContain('targetsForAssignment(')
        ->toContain('isEligible(')
        ->and($thinkTankWorkspace)->toContain('->availableForEvaluation()');
});

it('treats an explicit assignment as evaluator access without applying management portfolio scope', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/app/Http/Controllers/EvaluationSubmissionController.php'
    );
    $worklistStart = strpos($source, 'private function renderEvaluationWorklist');
    $worklistEnd = strpos($source, 'public function start', $worklistStart);
    $worklistMethod = substr($source, $worklistStart, $worklistEnd - $worklistStart);
    $ownerStart = strpos($source, 'private function assertAssignmentOwner');
    $ownerEnd = strpos($source, 'private function assertApplicantBelongsToAssignment', $ownerStart);
    $ownerMethod = substr($source, $ownerStart, $ownerEnd - $ownerStart);

    expect($worklistMethod)
        ->toContain("fn (\$query) => \$query->where('user_id', \$user->id)")
        ->not->toContain('applyAssignedPortfolioScopeToEvaluationAssignments')
        ->and($ownerMethod)
        ->toContain('(string) $assignment->user_id === (string) $user->id')
        ->not->toContain('evaluationAssignmentIsInAssignedPortfolio');
});
