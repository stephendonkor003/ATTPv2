<?php

use App\Models\MeIndicatorAchievement;

it('implements the complete M&E requirements workflow', function () {
    $root = dirname(__DIR__, 2);
    $migration = file_get_contents($root.'/database/migrations/2026_08_02_000003_add_repository_folders_and_align_me_requirements.php');
    $repository = file_get_contents($root.'/resources/views/me/knowledge-evidence/index.blade.php');
    $indicatorForm = file_get_contents($root.'/resources/views/me/indicators/partials/form.blade.php');
    $consolidated = file_get_contents($root.'/resources/views/me/consolidated-reports/index.blade.php');
    $dashboard = file_get_contents($root.'/resources/views/me/performance-reports/dashboard.blade.php');
    $lifecycle = file_get_contents($root.'/resources/views/me/performance-reports/partials/lifecycle-actions.blade.php');
    $routes = file_get_contents($root.'/routes/web.php');

    expect($migration)
        ->toContain("Schema::create('me_repository_folders'")
        ->toContain("Schema::create('me_repository_folder_indicators'")
        ->toContain('means_of_verification_folder_id')
        ->toContain("'reporting_period'")
        ->and($repository)
        ->toContain('Create indicator folder')
        ->toContain('Complete version history')
        ->toContain('Decision note *')
        ->and($indicatorForm)
        ->toContain('name="means_of_verification_folder_id"')
        ->not->toContain('name="means_of_verification_id"')
        ->and($consolidated)
        ->toContain('name="think_tank_id"')
        ->toContain('name="gender"')
        ->toContain('name="age_group"')
        ->toContain('name="stakeholder_category"')
        ->and($dashboard)
        ->toContain('Beneficiary and achievement disaggregation filters')
        ->and($lifecycle)
        ->toContain('Reject &amp; Return')
        ->toContain('required placeholder="Explain why the report is verified')
        ->and($routes)
        ->toContain("name('documents.replace')");

    expect(MeIndicatorAchievement::GENDERS)->toBe([
        'female' => 'Female',
        'male' => 'Male',
    ])->and(MeIndicatorAchievement::AGE_GROUPS)->toBe([
        'youth_below_35' => 'Youth below 35 years',
        'adult_35_plus' => 'Adults aged 35 years and above',
    ]);
});
