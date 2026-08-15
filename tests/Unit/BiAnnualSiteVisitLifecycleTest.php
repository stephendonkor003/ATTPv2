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

it('creates additional monitoring-team accounts from the add members modal', function () {
    $root = dirname(__DIR__, 2);
    $index = file_get_contents($root.'/resources/views/biannual-site-visits/index.blade.php');
    $controller = file_get_contents($root.'/app/Http/Controllers/BiAnnualSiteVisitController.php');
    $indexActionStart = strpos($controller, 'public function index(');
    $indexActionEnd = strpos($controller, 'public function create(', $indexActionStart);
    $actionStart = strpos($controller, 'public function addTeamMembers(');
    $actionEnd = strpos($controller, 'public function updateTeam(', $actionStart);
    $teamValidationStart = strpos($controller, 'private function hasValidMonitoringTeam(');
    $teamValidationEnd = strpos($controller, 'private function activeInternalStaffQuery(', $teamValidationStart);

    expect($indexActionStart)->not->toBeFalse()
        ->and($indexActionEnd)->not->toBeFalse()
        ->and($actionStart)->not->toBeFalse()
        ->and($actionEnd)->not->toBeFalse()
        ->and($teamValidationStart)->not->toBeFalse()
        ->and($teamValidationEnd)->not->toBeFalse();

    $indexAction = substr($controller, $indexActionStart, $indexActionEnd - $indexActionStart);
    $addMembersAction = substr($controller, $actionStart, $actionEnd - $actionStart);
    $teamValidation = substr(
        $controller,
        $teamValidationStart,
        $teamValidationEnd - $teamValidationStart
    );

    expect($index)
        ->toContain('modal-dialog modal-xl modal-dialog-centered basv-member-picker-dialog')
        ->toContain('Complete system user directory')
        ->toContain('id="team-member-search"')
        ->toContain('id="team-directory-count"')
        ->toContain('data-account-type=')
        ->toContain('data-account-status=')
        ->toContain('data-assigned-label hidden>Already assigned')
        ->toContain("searchInput.addEventListener('input', filterOptions)")
        ->toContain('option.hidden = !matches')
        ->toContain('option.dataset.search.includes(query)')
        ->toContain('.basv-member-directory-scroll')
        ->toContain('id="show-additional-member-form"')
        ->toContain('Create monitoring-team member')
        ->toContain('id="additional_member_name"')
        ->toContain('id="additional_member_email"')
        ->toContain('id="additional_member_specialism"')
        ->toContain('id="team-member-server-errors"')
        ->toContain('aria-live="polite"')
        ->toContain('new_team_members[${key}][name]')
        ->toContain('new_team_members[${key}][email]')
        ->toContain('removeButton.addEventListener')
        ->toContain('options = options.filter(candidate => candidate !== option)')
        ->toContain("saveButton.setAttribute('aria-busy', 'true')")
        ->toContain('Object.entries(oldNewMembers)')
        ->and($indexAction)
        ->toContain('$teamAssignableUsers = $canManageTeams')
        ->toContain('? User::query()')
        ->toContain("'user_type'")
        ->toContain("'is_disabled'")
        ->toContain("'is_blacklisted'")
        ->not->toContain('activeInternalStaffQuery()')
        ->not->toContain('filter_var(')
        ->and($addMembersAction)
        ->toContain("'team_members.*' => ['required', 'string', 'max:80', 'distinct']")
        ->toContain("'new_team_members.*.name' => ['required', 'string', 'max:255']")
        ->toContain("'new_team_members.*.email' => ['required', 'email:rfc', 'max:255']")
        ->toContain('$this->resolveTeamReferences(')
        ->toContain('Str::password(12)')
        ->toContain("'must_change_password' => true")
        ->toContain("->whereIn(DB::raw('LOWER(email)'), \$newEmails->all())")
        ->toContain('->lockForUpdate()')
        ->toContain('$storedSpecialisms[(string) $member->id]')
        ->toContain('new UserAccountCreated(')
        ->toContain('$existingMembers = User::query()')
        ->not->toContain('activeInternalStaffQuery()')
        ->not->toContain('filter_var(')
        ->not->toContain('active internal staff account')
        ->not->toContain("'team_members.*' => ['required', 'uuid'")
        ->and($teamValidation)
        ->toContain('$members = User::query()')
        ->toContain('$leader = $this->activeInternalStaffQuery()')
        ->not->toContain("whereNull('is_disabled')")
        ->not->toContain("whereNull('is_blacklisted')")
        ->not->toContain("whereNotIn('user_type'");
});
