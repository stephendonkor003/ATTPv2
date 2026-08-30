<?php

use App\Http\Controllers\EvaluationScoringController;
use App\Http\Controllers\EvaluationSubmissionController;
use App\Models\Evaluation;
use App\Models\EvaluationCriteria;
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

it('requires a score and response for every numeric question while section summaries remain optional', function () {
    $evaluation = (new Evaluation)->forceFill([
        'id' => 'evaluation-a',
        'type' => Evaluation::TYPE_SERVICES,
    ]);
    $section = (new EvaluationSection)->forceFill([
        'id' => 'section-a',
        'evaluation_id' => 'evaluation-a',
    ]);
    $criterion = (new EvaluationCriteria)->forceFill([
        'id' => 'criterion-a',
        'evaluation_section_id' => 'section-a',
        'max_score' => 10,
    ]);
    $section->setRelation('criteria', new EloquentCollection([$criterion]));
    $evaluation->setRelation('sections', new EloquentCollection([$section]));

    $rulesMethod = new ReflectionMethod(
        EvaluationSubmissionController::class,
        'finalSubmissionRules'
    );
    $rules = $rulesMethod->invoke(new EvaluationSubmissionController, $evaluation);

    expect($rules)
        ->toHaveKey('criteria.criterion-a', ['required', 'array'])
        ->toHaveKey('criteria.criterion-a.score', [
            'required',
            'numeric',
            'min:0',
            'max:10',
        ])
        ->toHaveKey('criteria.criterion-a.comment', ['required', 'string', 'max:5000'])
        ->toHaveKey('sections', ['sometimes', 'array'])
        ->toHaveKey('sections.section-a', ['sometimes', 'array'])
        ->toHaveKey('sections.section-a.strengths', ['nullable', 'string', 'max:5000'])
        ->toHaveKey('sections.section-a.weaknesses', ['nullable', 'string', 'max:5000']);
});
