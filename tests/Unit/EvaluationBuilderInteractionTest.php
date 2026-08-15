<?php

it('renders root-toned branches with direct and rolled-up question counts', function () {
    $root = dirname(__DIR__, 2);
    $builder = file_get_contents($root.'/resources/views/evaluations/show.blade.php');
    $node = file_get_contents($root.'/resources/views/evaluations/partials/section-builder-node.blade.php');
    $theme = file_get_contents($root.'/resources/views/evaluations/partials/hierarchy-theme.blade.php');

    expect($builder)
        ->toContain('evaluations.partials.hierarchy-theme')
        ->toContain('subtreeCriteriaCounts')
        ->toContain('data-overall-question-count')
        ->toContain("'rootIndex' => \$rootIndex")
        ->and($node)
        ->toContain('hierarchy-tone-{{ $rootIndex % 8 }}')
        ->toContain('data-direct-criteria-count')
        ->toContain('data-node-total-criteria')
        ->toContain('total questions')
        ->and($theme)
        ->toContain('.hierarchy-tone-0')
        ->toContain('.hierarchy-tone-7')
        ->toContain('--section-color');
});

it('keeps section and question mutations inside the dynamic builder flow', function () {
    $root = dirname(__DIR__, 2);
    $builder = file_get_contents($root.'/resources/views/evaluations/show.blade.php');
    $node = file_get_contents($root.'/resources/views/evaluations/partials/section-builder-node.blade.php');
    $controller = file_get_contents($root.'/app/Http/Controllers/EvaluationSectionController.php');

    expect($builder)
        ->toContain('data-section-form')
        ->toContain('refreshBuilderStructure(')
        ->toContain('DOMParser')
        ->toContain('data-ajax-delete')
        ->toContain('data-shift-section')
        ->and($node)
        ->toContain('data-section-form')
        ->toContain('data-delete-kind="section"')
        ->toContain('data-delete-kind="criterion"')
        ->and($controller)
        ->toContain('expectsJson()')
        ->toContain('sectionPayload(')
        ->toContain("'deleted_section_id'");
});

it('rejects stale-page question mutations as JSON instead of following a success-looking redirect', function () {
    $controller = file_get_contents(
        dirname(__DIR__, 2).'/app/Http/Controllers/EvaluationCriteriaController.php'
    );

    expect($controller)
        ->toContain('if ($request->expectsJson() || $request->ajax())')
        ->toContain('if (request()->expectsJson() || request()->ajax())')
        ->toContain("'message' => 'Cannot modify criteria once evaluation is active.'")
        ->toContain("'message' => 'Cannot delete criteria once evaluation is active.'")
        ->and(substr_count($controller, '], 422);'))->toBe(3);
});

it('uses a consistent guided details screen that returns to the builder', function () {
    $root = dirname(__DIR__, 2);
    $edit = file_get_contents($root.'/resources/views/evaluations/edit.blade.php');
    $controller = file_get_contents($root.'/app/Http/Controllers/EvaluationController.php');

    expect($edit)
        ->toContain('data-evaluation-details')
        ->toContain('Edit evaluation details')
        ->toContain('Back to builder')
        ->toContain('Save and return to builder')
        ->toContain('data-description-count')
        ->toContain('data-summary-name')
        ->and($controller)
        ->toContain("->route('evals.cfg.show', \$evaluation)")
        ->toContain("'description' => 'nullable|string|max:1000'");
});
