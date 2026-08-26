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
        ->toContain('Evidence comment')
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

it('stacks categorical responses below each question without wide qualification columns', function () {
    $workspace = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/evaluations/submit.blade.php'
    );

    expect($workspace)
        ->toContain('<th>Evaluation question and response</th>')
        ->toContain('<td class="categorical-criterion-cell">')
        ->toContain('<div class="criterion-prompt">')
        ->toContain('<div class="categorical-response-grid">')
        ->toContain('<legend class="response-field-label">')
        ->toContain('Evidence comment <span>Required for final submission</span>')
        ->toContain('.categorical-response-grid { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(0, .85fr)')
        ->toContain('.decision-options.is-eoi { grid-template-columns: repeat(3, minmax(0, 1fr)); }')
        ->toContain('@media (max-width: 991.98px)')
        ->toContain('.categorical-response-grid { grid-template-columns: 1fr; }')
        ->not->toContain('class="decision-column"');
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

it('uses the full scoring width and keeps secondary evaluator tools in a closed slide-out drawer', function () {
    $workspace = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/evaluations/submit.blade.php'
    );

    expect($workspace)
        ->toContain('<main class="col-12">')
        ->not->toContain('<main class="col-xl-9 col-lg-8">')
        ->not->toContain('class="evaluator-sidebar"')
        ->toContain('id="evaluatorToolsToggle"')
        ->toContain('aria-controls="evaluatorToolsDrawer" aria-expanded="false"')
        ->toContain('id="evaluatorToolsDrawer" class="evaluator-tools-drawer" role="dialog" aria-modal="true"')
        ->toContain('aria-hidden="true" aria-labelledby="evaluatorToolsTitle"')
        ->toContain('tabindex="-1" inert')
        ->toContain('Tools &amp; verification')
        ->toContain('Evaluation monitor')
        ->toContain('Identity verification')
        ->toContain('Form structure')
        ->toContain('transform: translate3d(100%, 0, 0)')
        ->toContain('.evaluator-tools-drawer.is-open')
        ->toContain("toolsToggle?.addEventListener('click', () => openEvaluatorTools())")
        ->toContain("toolsBackdrop?.addEventListener('click', () => closeEvaluatorTools())")
        ->toContain("if (event.key === 'Escape')")
        ->toContain("toolsDrawer.setAttribute('aria-hidden', 'false')")
        ->toContain("toolsDrawer.setAttribute('aria-hidden', 'true')")
        ->toContain("toolsDrawer.removeAttribute('inert')")
        ->toContain("toolsDrawer.setAttribute('inert', '')")
        ->toContain('focusToRestore.focus()')
        ->toContain('openEvaluatorTools(finalVideo)')
        ->toContain('@media (prefers-reduced-motion: reduce)');
});
