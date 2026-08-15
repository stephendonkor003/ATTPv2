<?php

use App\Http\Controllers\EvaluationScoringController;
use App\Http\Controllers\EvaluationSubmissionController;
use App\Models\Evaluation;
use App\Models\EvaluationSection;
use App\Models\EvaluationSubmission;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

it('accepts only section identifiers from the submission evaluation', function () {
    $evaluation = (new Evaluation)->forceFill(['id' => 'evaluation-a']);
    $section = (new EvaluationSection)->forceFill([
        'id' => 'section-a',
        'evaluation_id' => 'evaluation-a',
    ]);
    $evaluation->setRelation('sections', new EloquentCollection([$section]));

    $guard = new ReflectionMethod(
        EvaluationSubmissionController::class,
        'sectionsBelongToEvaluation'
    );
    $controller = new EvaluationSubmissionController;

    expect($guard->invoke($controller, ['section-a' => []], $evaluation))->toBeTrue()
        ->and($guard->invoke(
            $controller,
            ['section-from-another-evaluation' => []],
            $evaluation
        ))->toBeFalse();

    $source = file_get_contents(
        dirname(__DIR__, 2).'/app/Http/Controllers/EvaluationSubmissionController.php'
    );
    expect(substr_count($source, 'sectionsBelongToEvaluation($sectionPayload, $evaluation)'))
        ->toBe(2)
        ->and($source)->toContain('The selected section does not belong to this evaluation.');
});

it('blocks autosave scoring and note writes after final submission', function () {
    $draft = new class extends EvaluationSubmission
    {
        public function isSubmitted(): bool
        {
            return false;
        }
    };
    $submitted = new class extends EvaluationSubmission
    {
        public function isSubmitted(): bool
        {
            return true;
        }
    };

    foreach ([
        new EvaluationSubmissionController,
        new EvaluationScoringController,
    ] as $controller) {
        $guard = new ReflectionMethod($controller, 'submissionIsMutable');

        expect($guard->invoke($controller, $draft))->toBeTrue()
            ->and($guard->invoke($controller, $submitted))->toBeFalse();
    }

    $root = dirname(__DIR__, 2);
    $submissionController = file_get_contents(
        $root.'/app/Http/Controllers/EvaluationSubmissionController.php'
    );
    $scoringController = file_get_contents(
        $root.'/app/Http/Controllers/EvaluationScoringController.php'
    );

    expect(substr_count($submissionController, 'submissionIsMutable($submission)'))->toBe(1)
        ->and($submissionController)->toContain('Submitted evaluations cannot be modified.')
        ->and(substr_count($scoringController, 'submissionIsMutable($submission)'))->toBe(2)
        ->and($scoringController)->toContain('Submitted evaluations cannot be modified.');
});
