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
