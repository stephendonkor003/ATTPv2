<?php

use App\Models\Evaluation;

it('maps EOI categories without enabling numeric scoring', function () {
    $evaluation = new Evaluation(['type' => Evaluation::TYPE_EOI]);

    expect($evaluation->isEoi())->toBeTrue()
        ->and($evaluation->usesCategoricalDecisions())->toBeTrue()
        ->and($evaluation->usesNumericScoring())->toBeFalse()
        ->and($evaluation->decisionOptions())->toBe([
            2 => 'Qualified',
            1 => 'Average Qualified',
            0 => 'Not Qualified',
        ])
        ->and($evaluation->decisionLabel(2))->toBe('Qualified')
        ->and($evaluation->decisionLabel('1'))->toBe('Average Qualified')
        ->and($evaluation->decisionLabel(0))->toBe('Not Qualified')
        ->and($evaluation->decisionLabel(99))->toBeNull();
});

it('defines expression of interest evaluations as a central categorical domain contract', function () {
    $model = file_get_contents(
        dirname(__DIR__, 2).'/app/Models/Evaluation.php'
    );

    expect($model)
        ->toContain("TYPE_SERVICES = 'services'")
        ->toContain("TYPE_GOODS = 'goods'")
        ->toContain("TYPE_EOI = 'eoi'")
        ->toContain('MANAGED_TYPES')
        ->toContain('EOI_DECISIONS')
        ->toContain('function configurationTypes(')
        ->toContain('function isServices(')
        ->toContain('function isGoods(')
        ->toContain('function isEoi(')
        ->toContain('function usesNumericScoring(')
        ->toContain('function usesCategoricalDecisions(')
        ->toContain('function decisionOptions(')
        ->toContain('function decisionLabel(');

    expect(preg_match("/2\\s*=>\\s*'Qualified'/", $model))->toBe(1)
        ->and(preg_match("/1\\s*=>\\s*'Average Qualified'/", $model))->toBe(1)
        ->and(preg_match("/0\\s*=>\\s*'Not Qualified'/", $model))->toBe(1);
});

it('presents the EOI workflow and its outcomes on the advanced configuration page', function () {
    $view = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/evaluations/create.blade.php'
    );
    $preview = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/evaluations/partials/template-preview.blade.php'
    );

    expect($view)
        ->toContain("route('evals.cfg.store')")
        ->toContain('name="name"')
        ->toContain('evaluations.partials.portfolio-field')
        ->toContain('name="description"')
        ->toContain('name="type"')
        ->toContain('value="eoi"')
        ->toContain('Expression of Interest')
        ->toContain('Qualified')
        ->toContain('Average Qualified')
        ->toContain('Not Qualified')
        ->toContain("old('type'")
        ->toContain('@csrf');

    expect($preview)
        ->toContain('typeLabel()')
        ->toContain('decisionOptions()')
        ->toContain('usesNumericScoring()');
});

it('keeps EOI templates available throughout configuration and assignment', function () {
    $root = dirname(__DIR__, 2);

    $configurationSources = [
        file_get_contents($root.'/app/Http/Controllers/EvaluationController.php'),
        file_get_contents($root.'/app/Http/Controllers/EvaluationSectionController.php'),
        file_get_contents($root.'/app/Http/Controllers/EvaluationCriteriaController.php'),
    ];

    foreach ($configurationSources as $source) {
        expect($source)->toContain('Evaluation::MANAGED_TYPES');
    }

    $assignmentSources = [
        file_get_contents($root.'/app/Http/Controllers/EvaluationAssignmentController.php'),
        file_get_contents($root.'/app/Http/Controllers/ProcurementEvaluationController.php'),
        file_get_contents($root.'/app/Http/Controllers/Procurement/EvaluationController.php'),
    ];

    foreach ($assignmentSources as $source) {
        expect($source)->toContain('Evaluation::MANAGED_TYPES');
    }
});

it('handles EOI decisions explicitly without falling through to numeric services scoring', function () {
    $root = dirname(__DIR__, 2);
    $submissionController = file_get_contents(
        $root.'/app/Http/Controllers/EvaluationSubmissionController.php'
    );
    $scoringController = file_get_contents(
        $root.'/app/Http/Controllers/EvaluationScoringController.php'
    );
    $submissionModel = file_get_contents(
        $root.'/app/Models/EvaluationSubmission.php'
    );
    $sectionScoreModel = file_get_contents(
        $root.'/app/Models/EvaluationSectionScore.php'
    );
    $submitView = file_get_contents(
        $root.'/resources/views/evaluations/submit.blade.php'
    );

    expect($submissionController)
        ->toContain('usesCategoricalDecisions()')
        ->toContain('decisionOptions()')
        ->toContain("'comment'")
        ->and($scoringController)
        ->toContain('usesCategoricalDecisions()')
        ->toContain('decisionOptions()')
        ->toContain("'comment'")
        ->and($submissionModel)
        ->toContain('usesNumericScoring()')
        ->and($sectionScoreModel)
        ->toContain('usesNumericScoring()')
        ->and($submitView)
        ->toContain('usesCategoricalDecisions()')
        ->toContain('decisionOptions()');
});
