<?php

use App\Models\Evaluation;

it('presents assigned procurements and application actions without querying from Blade', function () {
    $root = dirname(__DIR__, 2);
    $worklist = file_get_contents($root.'/resources/views/evaluations/my.blade.php');

    expect($worklist)
        ->toContain('My Evaluations')
        ->toContain('Services</strong> Numeric scores')
        ->toContain('Goods</strong> Yes or No')
        ->toContain('EOI</strong> Qualification category')
        ->toContain("'Not started'")
        ->toContain("'Draft saved'")
        ->toContain("'Completed'")
        ->toContain("route('my.eval.start', [\$assignment, \$application])")
        ->toContain("route('my.eval.view', [\$assignment, \$application])")
        ->toContain('Start evaluation')
        ->toContain('Continue')
        ->toContain('View evaluation')
        ->toContain('No applications are ready for evaluation')
        ->not->toContain('EvaluationSubmission::')
        ->not->toContain('::query(')
        ->not->toContain('::where(');
});

it('keeps the scoring form visible and saves drafts manually and after a debounce', function () {
    $workspace = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/evaluations/submit.blade.php'
    );

    expect($workspace)
        ->toContain('<div id="evaluationForm">')
        ->not->toContain('<div id="evaluationForm" class="d-none">')
        ->not->toContain('lockedNotice')
        ->not->toContain("evaluationForm?.classList.remove('d-none')")
        ->toContain('id="saveDraft"')
        ->toContain('Save draft')
        ->toContain("route('my.eval.save', [\$assignment->id, \$applicant->id])")
        ->toContain('function scheduleDraftSave()')
        ->toContain('window.setTimeout(() => saveDraft(false), 1200)')
        ->toContain('saveDraftButton?.addEventListener(\'click\', () => saveDraft(true))')
        ->toContain('fetch(SAVE_URL')
        ->toContain("method: 'POST'")
        ->toContain("payload.delete('video')")
        ->toContain('Draft saved at');
});

it('renders the correct response controls for services goods and expression of interest', function () {
    $workspace = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/evaluations/submit.blade.php'
    );

    $goods = new Evaluation(['type' => Evaluation::TYPE_GOODS]);
    $eoi = new Evaluation(['type' => Evaluation::TYPE_EOI]);

    expect($workspace)
        ->toContain('@if ($isNumeric)')
        ->toContain('<input type="number" name="criteria[{{ $criterion->id }}]"')
        ->toContain('max="{{ $criterion->max_score }}"')
        ->toContain('data-max="{{ $criterion->max_score }}"')
        ->toContain('class="decision-options {{ $evaluation->isEoi() ? \'is-eoi\' : \'is-goods\' }}"')
        ->toContain('type="radio"')
        ->toContain('name="criteria[{{ $criterion->id }}][decision]"')
        ->toContain('value="{{ $decisionValue }}"')
        ->toContain('Evaluator comment')
        ->toContain('class="form-control evidence-comment"')
        ->toContain('@if ($evaluation->isEoi())')
        ->toContain('<span class="decision-number">{{ $loop->iteration }}</span>')
        ->and($goods->decisionOptions())->toBe([
            1 => 'Yes',
            0 => 'No',
        ])
        ->and($eoi->decisionOptions())->toBe([
            2 => 'Qualified',
            1 => 'Average Qualified',
            0 => 'Not Qualified',
        ]);
});

it('uses applicant-scoped canonical evaluator routes', function () {
    $routes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');

    expect($routes)
        ->toMatch("/Route::get\('\/{assignment}\/start\/{applicant}'.*?->name\('start'\);/s")
        ->toMatch("/Route::post\('\/{assignment}\/save\/{applicant}'.*?->name\('save'\);/s")
        ->toMatch("/Route::post\('\/{assignment}\/submit\/{applicant}'.*?->name\('submit'\);/s")
        ->toMatch("/Route::get\('\/{assignment}\/view\/{applicant}'.*?->name\('view'\);/s");
});

it('keeps identity verification final-only and returns evaluators to their worklist', function () {
    $root = dirname(__DIR__, 2);
    $workspace = file_get_contents($root.'/resources/views/evaluations/submit.blade.php');
    $readOnlyView = file_get_contents($root.'/resources/views/evaluations/view.blade.php');

    expect($workspace)
        ->toContain('Upload a verification video')
        ->toContain('This is required only for final submission.')
        ->toContain("payload.delete('video')")
        ->toContain("finalForm?.addEventListener('submit'")
        ->toContain('Record or upload an identity verification video before final submission. Your draft remains saved.')
        ->not->toContain('Complete identity verification to begin')
        ->and($readOnlyView)
        ->toContain("route('my.eval.index')")
        ->not->toContain("route('eval.assign.hub')");
});
