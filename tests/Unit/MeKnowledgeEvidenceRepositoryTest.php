<?php

it('provides a complete responsive repository workspace and dependency-free controls', function () {
    $root = dirname(__DIR__, 2);
    $view = file_get_contents($root.'/resources/views/me/knowledge-evidence/index.blade.php');
    $styles = file_get_contents($root.'/resources/views/me/knowledge-evidence/partials/styles.blade.php');

    expect($view)
        ->toContain('Search and repository scope')
        ->toContain('Repository folders')
        ->toContain('Document register')
        ->toContain('Controlled document')
        ->toContain('Complete version history')
        ->toContain('data-repo-portfolio')
        ->toContain('data-repo-folder-filter')
        ->toContain('data-indicator-form')
        ->toContain('data-indicator-search')
        ->toContain('data-select-visible')
        ->toContain('data-file-input')
        ->toContain("document.addEventListener('DOMContentLoaded', ready")
        ->and($styles)
        ->toContain('.mel-repository')
        ->toContain('.kr-workspace')
        ->toContain('.kr-table-wrap')
        ->toContain('overflow:auto')
        ->toContain('@media(max-width:');
});

it('keeps repository documents controlled through validation, versioning and protected delivery', function () {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents($root.'/app/Http/Controllers/MeKnowledgeEvidenceController.php');
    $routes = file_get_contents($root.'/routes/web.php');

    expect($controller)
        ->toContain("'external_url' => 'nullable|required_without:evidence_file|url:http,https|max:2000'")
        ->toContain('DB::transaction')
        ->toContain('lockForUpdate()')
        ->toContain('replacement is identical to the current file')
        ->toContain("'validation_status' => 'pending'")
        ->toContain('public function preview(')
        ->toContain("'X-Content-Type-Options' => 'nosniff'")
        ->and($routes)
        ->toContain("name('me.knowledge-evidence.preview')");
});

it('shows the repository navigation to every role allowed to open the workspace', function () {
    $sidebar = file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/partials/sidebar.blade.php');
    $repositoryPosition = strpos($sidebar, "route('budget.me.rebuild.knowledge-repository')");

    expect($repositoryPosition)->not->toBeFalse();

    $permissionContext = substr($sidebar, max(0, $repositoryPosition - 700), 700);
    expect($permissionContext)
        ->toContain('me.configuration.view')
        ->toContain('me.performance_reports.review')
        ->toContain('me.data_entry.view');
});

it('creates and selects an indicator evidence folder without leaving the indicator form', function () {
    $root = dirname(__DIR__, 2);
    $form = file_get_contents($root.'/resources/views/me/indicators/partials/form.blade.php');
    $modals = file_get_contents($root.'/resources/views/me/indicators/partials/inline-config-modals.blade.php');
    $script = file_get_contents($root.'/resources/views/me/indicators/partials/inline-config-script.blade.php');
    $controller = file_get_contents($root.'/app/Http/Controllers/MeKnowledgeEvidenceController.php');

    expect($form)
        ->toContain('data-inline-config-open="evidence"')
        ->toContain('aria-controls="indicatorEvidenceFolderCreateModal"')
        ->toContain('data-inline-selection-status="evidence"')
        ->not->toContain('> Open repository')
        ->and($modals)
        ->toContain('id="indicatorEvidenceFolderCreateModal"')
        ->toContain('data-inline-config-form="evidence"')
        ->toContain('data-inline-target-select="indicator-means-of-verification"')
        ->toContain('name="indicator_creation" value="1"')
        ->and($script)
        ->toContain("fetch(form.action")
        ->toContain('selectCreatedItem(kind, form, payload.data)')
        ->and($controller)
        ->toContain('$inlineIndicatorCreation = ($request->expectsJson() || $request->ajax())')
        ->toContain("'message' => 'Repository folder created and selected.'")
        ->toContain("'documents_count' => 0");
});
