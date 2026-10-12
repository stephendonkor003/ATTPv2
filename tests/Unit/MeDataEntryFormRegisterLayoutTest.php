<?php

it('keeps data entry to form creation and a searchable collection bank', function () {
    $root = dirname(__DIR__, 2);
    $view = file_get_contents($root.'/resources/views/me/data-entry/index.blade.php');
    $controller = file_get_contents($root.'/app/Http/Controllers/MeDataEntryController.php');
    $routes = file_get_contents($root.'/routes/web.php');

    expect($view)
        ->toContain("'forms' => ['label' => 'Form creation'")
        ->toContain("'collections' => ['label' => 'Data collection Bank'")
        ->not->toContain("'periods' => ['label'")
        ->not->toContain("'reports' => ['label'")
        ->not->toContain("'submissions' => ['label'")
        ->toContain('data-form-create-dialog')
        ->toContain('1. Link an indicator to this form')
        ->toContain('2. Create the form')
        ->toContain('Form sections and questions')
        ->toContain('data-activate-form-dialog')
        ->toContain('data-activate-select-visible')
        ->toContain('data-activate-thinktank-search')
        ->toContain('name="start_date"')
        ->toContain('name="end_date"')
        ->toContain('name="member_ids[]"')
        ->toContain('Currently collecting data')
        ->toContain('Ready to activate')
        ->toContain('me-bank-grid')
        ->toContain('Submitted')
        ->toContain('Not submitted')
        ->toContain('data-activate-form')
        ->toContain('Active Now')
        ->toContain('me-live-pulse')
        ->toContain('me-form-template-table')
        ->toContain('<th>Status</th>')
        ->toContain("route('budget.me.data-entry.forms.destroy', \$form)")
        ->toContain("route('budget.me.data-entry.forms.activate', \$form)")
        ->toContain('Search indicators or Think Tanks')
        ->toContain('data-me-data-entry-fragment')
        ->toContain("requestUrl.searchParams.set('fragment', '1')")
        ->toContain('window.history.pushState');

    expect($controller)
        ->toContain("private const TABS = ['forms', 'collections'];")
        ->toContain('$requestedTab = (string) $request->query(\'tab\', \'forms\')')
        ->toContain('function activateForm(')
        ->toContain('function destroyForm(')
        ->toContain('assertFormReadyForCollection')
        ->toContain('MeReportingPeriod::LIFECYCLE_OPEN')
        ->toContain('MeDataCollection::STATUS_OPEN')
        ->toContain('syncAssignments($collection, $memberIds');

    expect($routes)
        ->toContain("Route::post('forms/{form}/activate', 'activateForm')->name('forms.activate')")
        ->toContain("Route::delete('forms/{form}', 'destroyForm')->name('forms.destroy')");
});
