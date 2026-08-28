<?php

use App\Http\Controllers\EvaluationAssignmentController;
use App\Models\Evaluation;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationCriteria;
use App\Models\EvaluationCriteriaScore;
use App\Models\EvaluationSection;
use App\Models\EvaluationSectionScore;
use App\Models\EvaluationSubmission;
use App\Models\Procurement;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

$administrator = User::query()->where('user_type', 'admin')->firstOrFail();
$request = Request::create('/evaluations/assignments/smoke', 'DELETE', server: [
    'HTTP_REFERER' => url('/evaluations/assignments'),
]);
$request->setUserResolver(fn () => $administrator);
$session = $app->make('session')->driver();
$session->start();
$request->setLaravelSession($session);
$app->instance('request', $request);
Auth::setUser($administrator);

DB::beginTransaction();

try {
    $evaluation = Evaluation::query()
        ->where('type', Evaluation::TYPE_EOI)
        ->whereDoesntHave('assignments')
        ->with('sections.criteria')
        ->firstOrFail();

    if ($evaluation->sections->flatMap->criteria->isEmpty()) {
        $section = $evaluation->sections->first() ?? EvaluationSection::create([
            'evaluation_id' => $evaluation->id,
            'name' => 'Assignment removal smoke section',
            'sort_order' => 1,
        ]);
        EvaluationCriteria::create([
            'evaluation_section_id' => $section->id,
            'name' => 'Assignment removal smoke criterion',
            'description' => 'Transactional fixture criterion.',
        ]);
        $evaluation->load('sections.criteria');
    }

    $criterion = $evaluation->sections->flatMap->criteria->firstOrFail();
    $section = $criterion->section;
    $procurement = Procurement::query()->has('submissions')->firstOrFail();
    $applicant = $procurement->submissions()->firstOrFail();

    $makeAssignment = fn (): EvaluationAssignment => EvaluationAssignment::create([
        'evaluation_id' => $evaluation->id,
        'procurement_id' => $procurement->id,
        'form_submission_id' => $applicant->id,
        'user_id' => $administrator->id,
        'assigned_by' => $administrator->id,
        'assigned_at' => now(),
        'status' => 'assigned',
    ]);

    $draftAssignment = $makeAssignment();
    $draft = EvaluationSubmission::create([
        'evaluation_id' => $evaluation->id,
        'procurement_id' => $procurement->id,
        'form_submission_id' => $applicant->id,
        'evaluator_id' => $administrator->id,
        'submitted_at' => null,
    ]);
    $criteriaScore = EvaluationCriteriaScore::create([
        'submission_id' => $draft->id,
        'evaluation_criteria_id' => $criterion->id,
        'decision' => 2,
        'comment' => 'Abandoned smoke-test draft.',
    ]);
    $sectionScore = EvaluationSectionScore::create([
        'submission_id' => $draft->id,
        'evaluation_section_id' => $section->id,
        'strengths' => 'Draft strength',
        'weaknesses' => 'Draft weakness',
    ]);

    $app->make(EvaluationAssignmentController::class)->destroy($draftAssignment);

    if (EvaluationAssignment::query()->whereKey($draftAssignment->id)->exists()
        || EvaluationSubmission::query()->whereKey($draft->id)->exists()
        || EvaluationCriteriaScore::query()->whereKey($criteriaScore->id)->exists()
        || EvaluationSectionScore::query()->whereKey($sectionScore->id)->exists()) {
        throw new RuntimeException('Removing an unsubmitted assignment did not clean up its uncovered draft artifacts.');
    }

    $submittedAssignment = $makeAssignment();
    $submitted = EvaluationSubmission::create([
        'evaluation_id' => $evaluation->id,
        'procurement_id' => $procurement->id,
        'form_submission_id' => $applicant->id,
        'evaluator_id' => $administrator->id,
        'submitted_at' => now(),
    ]);

    $app->make(EvaluationAssignmentController::class)->destroy($submittedAssignment);

    if (! EvaluationAssignment::query()->whereKey($submittedAssignment->id)->exists()
        || ! EvaluationSubmission::query()->whereKey($submitted->id)->exists()) {
        throw new RuntimeException('A submitted evaluation was orphaned when its active assignment was removed.');
    }

    $overlappingAssignment = EvaluationAssignment::create([
        'evaluation_id' => $evaluation->id,
        'procurement_id' => $procurement->id,
        'form_submission_id' => null,
        'user_id' => $administrator->id,
        'assigned_by' => $administrator->id,
        'assigned_at' => now(),
        'status' => 'assigned',
    ]);

    $app->make(EvaluationAssignmentController::class)->destroy($submittedAssignment->fresh());

    if (EvaluationAssignment::query()->whereKey($submittedAssignment->id)->exists()
        || ! EvaluationAssignment::query()->whereKey($overlappingAssignment->id)->exists()
        || ! EvaluationSubmission::query()->whereKey($submitted->id)->exists()) {
        throw new RuntimeException('A duplicate assignment could not be removed safely through its remaining overlapping coverage.');
    }

    echo "EVALUATION_ASSIGNMENT_REMOVAL_SMOKE_OK\n";
} finally {
    DB::rollBack();
}
