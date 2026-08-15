<?php

use App\Models\BiAnnualSiteVisitProfile;
use App\Models\SiteVisit;

it('limits schedule mutations to non-final workflow states', function () {
    $profile = (new BiAnnualSiteVisitProfile)->forceFill(['is_active' => true]);

    foreach (BiAnnualSiteVisitProfile::MUTABLE_WORKFLOW_STATUSES as $status) {
        $profile->setRelation('siteVisit', (new SiteVisit)->forceFill(['status' => $status]));
        expect($profile->hasMutableWorkflowStatus())->toBeTrue();
    }

    foreach (['submitted', 'approved'] as $status) {
        $profile->setRelation('siteVisit', (new SiteVisit)->forceFill(['status' => $status]));
        expect($profile->hasMutableWorkflowStatus())->toBeFalse();
    }
});

it('wires reversible lifecycle routes and preserves the workflow status', function () {
    $root = dirname(__DIR__, 2);
    $routes = file_get_contents($root.'/routes/web.php');
    $controller = file_get_contents($root.'/app/Http/Controllers/BiAnnualSiteVisitController.php');
    $migration = file_get_contents(
        $root.'/database/migrations/2026_08_15_000002_add_lifecycle_to_biannual_site_visit_profiles.php'
    );

    expect($routes)
        ->toContain("name('edit')")
        ->toContain("name('update')")
        ->toContain("name('deactivate')")
        ->toContain("name('reactivate')")
        ->and($controller)
        ->toContain('public function edit(')
        ->toContain('public function update(')
        ->toContain('public function deactivate(')
        ->toContain('public function reactivate(')
        ->toContain("'is_active' => false")
        ->toContain("'is_active' => true")
        ->toContain("'lifecycle_history'")
        ->not->toContain("\$lockedSiteVisit->update(['status' => 'inactive'])")
        ->and(substr_count($controller, '$this->assertScheduleMutable($lockedProfile)'))
        ->toBeGreaterThanOrEqual(4)
        ->and($migration)
        ->toContain("\$table->boolean('is_active')")
        ->toContain("\$table->timestamp('deactivated_at')")
        ->toContain("\$table->text('deactivation_reason')");
});

it('shows schedule edit and reversible deactivation controls in the visit experience', function () {
    $root = dirname(__DIR__, 2);
    $index = file_get_contents($root.'/resources/views/biannual-site-visits/index.blade.php');
    $show = file_get_contents($root.'/resources/views/biannual-site-visits/show.blade.php');
    $edit = file_get_contents($root.'/resources/views/biannual-site-visits/edit.blade.php');

    expect($index)
        ->toContain("route('biannual-site-visits.edit', \$visit)")
        ->toContain("route('biannual-site-visits.deactivate', \$visit)")
        ->toContain("route('biannual-site-visits.reactivate', \$visit)")
        ->toContain('Reason for deactivation')
        ->toContain('Its team, questionnaire responses, and audit history will not be deleted.')
        ->and($show)
        ->toContain('Edit schedule')
        ->toContain('This scheduled visit is inactive and read-only.')
        ->and($edit)
        ->toContain('Locked audit identity')
        ->toContain('Save schedule changes')
        ->toContain("route('biannual-site-visits.update', \$visit)");
});
