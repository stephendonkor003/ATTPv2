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
        ->toContain('Rework requires your attention')
        ->toContain('Edit and resubmit')
        ->toContain('$reworkTasks->isNotEmpty()')
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
        ->toContain('<input id="{{ $scoreInputId }}" type="number"')
        ->toContain('name="criteria[{{ $criterion->id }}][score]"')
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

it('renders numeric scoring as accessible responsive question cards', function () {
    $workspace = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/evaluations/submit.blade.php'
    );

    expect($workspace)
        ->toContain('<div class="numeric-question-list numeric-criteria-wrap" role="list"')
        ->toContain('<article class="numeric-question-card criterion-row" data-criterion-row role="listitem">')
        ->toContain('<label for="{{ $scoreInputId }}">Evaluator score</label>')
        ->toContain('<input id="{{ $scoreInputId }}" type="number"')
        ->toContain('name="criteria[{{ $criterion->id }}][score]"')
        ->toContain('class="form-control score-input" min="0"')
        ->toContain('inputmode="decimal" placeholder="0.00"')
        ->toContain('data-section-id="{{ $section->id }}"')
        ->toContain('aria-describedby="{{ $scoreRangeId }}"')
        ->toContain('<label for="{{ $scoreCommentId }}" class="evidence-label">')
        ->toContain('<strong>Evidence response</strong>')
        ->toContain('name="criteria[{{ $criterion->id }}][comment]"')
        ->toContain('class="form-control evidence-comment numeric-evidence-comment" rows="5" maxlength="5000"')
        ->toContain('placeholder="Explain the evidence and reasoning for this score" required>')
        ->toContain('.numeric-evidence-response { min-width: 0; grid-column: 1 / -1;')
        ->toContain('.numeric-evidence-comment { display: block; width: 100%; max-width: 100%; min-height: 120px;')
        ->toContain('.score-input { flex: 1 1 12rem; width: 100%; min-width: 8rem; min-height: 52px;')
        ->toContain('.score-input:focus, .score-input:focus-visible')
        ->toContain('.numeric-response-panel { display: grid; min-width: 0; grid-template-columns: minmax(170px, .55fr) minmax(280px, 1fr);')
        ->toContain('.numeric-response-panel { grid-template-columns: 1fr; }')
        ->toContain('.score-entry { flex-direction: column; }')
        ->toContain('function numericResponseComplete(input)')
        ->toContain("row?.querySelector('.numeric-evidence-comment')")
        ->toContain('? numericResponseComplete(scoreInput)')
        ->toContain('const answered = relevant.filter(input => numericResponseComplete(input)).length;')
        ->toContain("event.target.matches('.score-input, .decision-input, .evidence-comment')")
        ->not->toContain('<div class="table-responsive numeric-criteria-wrap">')
        ->not->toContain('.score-column { width: 130px; }');
});

it('renders categorical responses as overflow-safe question cards instead of a table', function () {
    $workspace = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/evaluations/submit.blade.php'
    );

    expect($workspace)
        ->toContain('<div class="categorical-question-list" role="list"')
        ->toContain('<article class="categorical-question-card" data-criterion-row role="listitem">')
        ->toContain('<header class="categorical-question-header">')
        ->toContain('<div class="categorical-answer-stack">')
        ->toContain('<fieldset class="decision-fieldset categorical-response-panel"')
        ->toContain('<div class="evidence-response categorical-response-panel">')
        ->toContain('<strong>Evidence comment</strong>')
        ->toContain('.categorical-question-list { display: grid; width: 100%; max-width: 100%; min-width: 0;')
        ->toContain('.categorical-question-copy p { max-width: 100%;')
        ->toContain('.categorical-answer-stack { display: grid; width: 100%; min-width: 0; grid-template-columns: minmax(0, 1fr);')
        ->toContain('.decision-options.is-eoi { grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); }')
        ->toContain('.decision-option:focus-within')
        ->toContain('.decision-option > span:last-child { min-width: 0; overflow-wrap: anywhere; }')
        ->toContain('.evidence-comment { display: block; width: 100%; max-width: 100%;')
        ->toContain('function categoricalResponseComplete(input)')
        ->toContain("row?.querySelector('.evidence-comment')")
        ->toContain(": categoricalResponseComplete(row.querySelector('.decision-input:checked'))")
        ->toContain('const completeInputs = selectedInputs.filter(input => categoricalResponseComplete(input));')
        ->toContain('const relevantComplete = completeInputs.filter(input => ids.includes(String(input.dataset.sectionId)));')
        ->toContain('const answered = relevantComplete.length;')
        ->toContain("event.target.matches('.score-input, .decision-input, .evidence-comment')")
        ->not->toContain('<th>Evaluation question and response</th>')
        ->not->toContain('class="categorical-criterion-cell"')
        ->not->toContain('categorical-response-grid');
});

it('presents section strengths and weaknesses as optional summaries', function () {
    $workspace = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/evaluations/submit.blade.php'
    );

    expect($workspace)
        ->toContain('<span>Section strengths</span>')
        ->toContain('<span>Section weaknesses</span>')
        ->toContain('<small>Optional summary</small>')
        ->toContain('placeholder="Optional: summarise the strongest evidence">')
        ->toContain('placeholder="Optional: summarise cross-cutting gaps or concerns">')
        ->toContain('.section-notes label small { color: #98a2b3;')
        ->not->toContain('placeholder="Optional: summarise the strongest evidence" required')
        ->not->toContain('placeholder="Optional: summarise cross-cutting gaps or concerns" required');
});

it('reveals and focuses invalid question controls inside collapsed sections', function () {
    $workspace = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/evaluations/submit.blade.php'
    );

    expect($workspace)
        ->toContain("finalForm?.addEventListener('invalid', event => {")
        ->toContain("invalidControl.closest('[data-criterion-row]')")
        ->toContain("const content = invalidControl.closest('.assessment-node-body');")
        ->toContain("const section = invalidControl.closest('[data-evaluation-section]');")
        ->toContain("content?.classList.contains('is-collapsed')")
        ->toContain("content.classList.remove('is-collapsed');")
        ->toContain("toggle?.setAttribute('aria-expanded', 'true');")
        ->toContain("invalidControl.scrollIntoView({ behavior: 'smooth', block: 'center' });")
        ->toContain('invalidControl.focus({ preventScroll: true });')
        ->toContain('}, true);');
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
