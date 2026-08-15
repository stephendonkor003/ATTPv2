<?php

it('seeds the revised workbook as a provenance-identified immutable version', function () {
    $root = dirname(__DIR__, 2);
    $definition = require $root.'/database/data/biannual_monitoring_questionnaire.php';
    $seeder = file_get_contents(
        $root.'/database/seeders/BiAnnualSiteVisitQuestionnaireSeeder.php'
    );

    expect($definition['counts'])->toBe([
        'sections' => 7,
        'topics' => 30,
        'questions' => 146,
    ])
        ->and(data_get($definition, 'source.revision'))
        ->toBe('2026-08-15-attached-copy')
        ->and(data_get($definition, 'source.sha256'))
        ->toMatch('/^[a-f0-9]{64}$/')
        ->and(data_get($definition, 'source.content_sha256'))
        ->toMatch('/^[a-f0-9]{64}$/')
        ->and($seeder)
        ->toContain("data_get(\$definition, 'source.content_sha256')")
        ->toContain("data_get(\$definition, 'counts.questions', 0)")
        ->toContain('hash_equals(')
        ->toContain("->withCount('questions')")
        ->toContain('->lockForUpdate()')
        ->toContain("->get(['id']);")
        ->toContain('$releaseExists = BiAnnualSiteVisitTemplate::query()')
        ->toContain('under READ COMMITTED')
        ->toContain('BiAnnualSiteVisitTemplate::nextVersionForCode($code)')
        ->toContain("'seeded_by' => self::class")
        ->toContain("'status' => BiAnnualSiteVisitTemplate::STATUS_ARCHIVED")
        ->toContain("'status' => BiAnnualSiteVisitTemplate::STATUS_PUBLISHED")
        ->not->toContain("where('version', 1)->exists()");
});

it('surfaces the seeded default questionnaire on the visit register', function () {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents(
        $root.'/app/Http/Controllers/BiAnnualSiteVisitController.php'
    );
    $index = file_get_contents(
        $root.'/resources/views/biannual-site-visits/index.blade.php'
    );

    expect($controller)
        ->toContain('$defaultTemplate = BiAnnualSiteVisitTemplate::query()')
        ->toContain("->withCount(['sections', 'questions'])")
        ->and($index)
        ->toContain('Default questionnaire ready')
        ->toContain('$defaultTemplate->questions_count')
        ->toContain("route('biannual-site-visits.templates.preview', \$defaultTemplate)")
        ->toContain('Schedule with this template')
        ->toContain('The questionnaire is ready. Schedule the first H1 or H2 visit');
});

it('offers a safe edit and update path for every questionnaire version', function () {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents(
        $root.'/app/Http/Controllers/BiAnnualSiteVisitController.php'
    );
    $index = file_get_contents(
        $root.'/resources/views/biannual-site-visits/index.blade.php'
    );
    $library = file_get_contents(
        $root.'/resources/views/biannual-site-visits/templates/index.blade.php'
    );
    $routes = file_get_contents($root.'/routes/web.php');
    $templateController = file_get_contents(
        $root.'/app/Http/Controllers/BiAnnualSiteVisitTemplateController.php'
    );

    expect($controller)
        ->toContain('$questionnaireTemplates = $canManageTemplates')
        ->and($index)
        ->toContain('Questionnaire templates')
        ->toContain('Edit &amp; update')
        ->toContain('Edit as new version')
        ->toContain("route('biannual-site-visits.templates.editable-draft', \$questionnaireTemplate)")
        ->and($library)
        ->toContain('Edit &amp; update')
        ->toContain('Edit as new version')
        ->toContain("route('biannual-site-visits.templates.editable-draft', \$template)")
        ->and($routes)
        ->toContain("name('templates.editable-draft')")
        ->and($templateController)
        ->toContain('public function editableDraft(')
        ->toContain('->lockForUpdate()')
        ->toContain('$existingDraft')
        ->toContain("'type' => 'derived_template'")
        ->toContain('derived_from_template_id');
});
