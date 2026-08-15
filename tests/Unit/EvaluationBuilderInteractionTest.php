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

it('shows a direct level-aware delete action for every persisted sub-section', function () {
    $node = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/evaluations/partials/section-builder-node.blade.php'
    );

    expect(preg_match('/@if\s*\(\$level > 1\)(.*?)@endif/s', $node, $nestedDelete))->toBe(1);

    expect($nestedDelete[1])
        ->toContain('class="node-delete-form" data-ajax-delete data-delete-kind="section"')
        ->toContain("action=\"{{ route('evals.cfg.sec.del', \$section) }}\"")
        ->toContain("@method('DELETE')")
        ->toContain('data-confirm="{{ $deleteConfirmation }}"')
        ->toContain('title="Delete {{ $displayLevelLabel }}"')
        ->toContain('aria-label="Delete {{ $displayLevelLabel }} {{ $section->name }}"')
        ->toContain('<span>Delete</span>');

    expect($node)
        ->toContain("\$deleteConfirmation = 'Delete this ' . \$deleteTargetLabel")
        ->toContain("\$children->isNotEmpty() ? ' and all of its child sections' : ''");
});

it('opens the section editor at the subtotal switch from every editable status', function () {
    $root = dirname(__DIR__, 2);
    $builder = file_get_contents($root.'/resources/views/evaluations/show.blade.php');
    $node = file_get_contents($root.'/resources/views/evaluations/partials/section-builder-node.blade.php');
    $controller = file_get_contents($root.'/app/Http/Controllers/EvaluationSectionController.php');

    expect(preg_match(
        '/<div class="subtotal-control" data-subtotal-setting>(.*?)<\/div>/s',
        $node,
        $subtotalControl
    ))->toBe(1);

    expect(preg_match(
        '/@if\s*\(\$showSubtotal\)(?<status>.*?)@endif\s*@if\s*\(\$canEdit\)(?<edit>.*?)@endif/s',
        $subtotalControl[1],
        $subtotalParts
    ))->toBe(1);

    expect($subtotalParts['status'])
        ->toContain('subtotal-pill is-enabled')
        ->toContain('subtotal-pill is-disabled')
        ->and($subtotalParts['edit'])
        ->toContain('class="subtotal-edit-btn" data-edit-subtotal')
        ->toContain('data-toggle-panel="{{ $editFormId }}"')
        ->toContain('data-panel-focus="edit-subtotal-{{ $section->id }}"')
        ->toContain('aria-controls="{{ $editFormId }}"')
        ->toContain("<span>Edit {{ \$isServices ? 'subtotal' : 'summary' }}</span>");

    expect($node)
        ->toContain('id="child-subtotal-{{ $section->id }}" data-subtotal-toggle')
        ->toContain('id="edit-subtotal-{{ $section->id }}" data-subtotal-toggle')
        ->toContain('aria-describedby="edit-subtotal-help-{{ $section->id }}"')
        ->and($builder)
        ->toContain('id="root-section-subtotal" data-subtotal-toggle')
        ->toContain('function setPanel(id, open, focusTargetId = null)')
        ->toContain('requestedTarget && panel.contains(requestedTarget)')
        ->toContain('toggle.dataset.panelFocus || null')
        ->and($controller)
        ->toContain("'show_subtotal' => ['sometimes', 'boolean']")
        ->toContain("? (bool) \$validated['show_subtotal']")
        ->toContain("'show_subtotal' => (bool) \$section->show_subtotal");

    expect(substr_count($node, 'data-edit-subtotal'))->toBe(1);
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

it('offers safe permanent deletion for unused draft evaluation forms from the list', function () {
    $root = dirname(__DIR__, 2);
    $index = file_get_contents($root.'/resources/views/evaluations/index.blade.php');
    $controller = file_get_contents($root.'/app/Http/Controllers/EvaluationController.php');

    expect(preg_match(
        '/@if\s*\(\$eval->status === \'draft\'\)(?<draft>.*?)@elseif\s*\(\$eval->status === \'active\'\)/s',
        $index,
        $statusActions
    ))->toBe(1);

    expect($statusActions['draft'])
        ->toContain("route('evals.cfg.delete', \$eval)")
        ->toContain('data-evaluation-delete')
        ->toContain("@method('DELETE')")
        ->toContain('all of its sections and questions')
        ->toContain('onsubmit="return confirm(this.dataset.confirm);"')
        ->toContain('feather-trash-2')
        ->toContain('> Delete')
        ->and(substr_count($index, "route('evals.cfg.delete', \$eval)"))->toBe(1)
        ->and($controller)
        ->toContain("if (\$evaluation->status !== 'draft')")
        ->toContain('evaluationHasRecordedUse($evaluation)')
        ->toContain('DB::transaction(function () use ($evaluation): void')
        ->toContain('$evaluation->rootSections()->get()->each(')
        ->toContain('fn ($section) => $section->delete()')
        ->toContain('$evaluation->delete();')
        ->toContain('EvaluationSubmission::query()')
        ->toContain('ReworkRequest::query()');
});
