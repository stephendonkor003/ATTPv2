<?php

it('renders complete procurement item create and edit forms', function () {
    $create = file_get_contents(
        dirname(__DIR__, 2) . '/resources/views/procurement/plans/create.blade.php'
    );
    $edit = file_get_contents(
        dirname(__DIR__, 2) . '/resources/views/procurement/plans/edit.blade.php'
    );

    expect($create)
        ->toContain("old('procurement_code', \$defaultCode)")
        ->toContain('name="is_code_auto_generated"')
        ->toContain('name="program_plan_id"')
        ->toContain('name="currency"')
        ->toContain('name="fiscal_year"')
        ->toContain('initialSubActivityId')
        ->toContain('applyPortfolioFilter()');

    expect($edit)
        ->toContain('name="is_code_auto_generated"')
        ->toContain('name="program_plan_id"')
        ->toContain("old('program_plan_id', \$plan->program_plan_id)")
        ->toContain('name="currency"')
        ->toContain('name="fiscal_year"')
        ->toContain('applyPortfolioFilter()');
});

it('keeps procurement item scheduling and launch state authoritative on the server', function () {
    $controller = file_get_contents(
        dirname(__DIR__, 2) . '/app/Http/Controllers/Procurement/ProcurementPlanController.php'
    );

    expect($controller)
        ->toContain("'activity_id' => 'required|exists:myb_activities,id'")
        ->toContain("'estimated_start_date' => 'required|date'")
        ->toContain("\$validated['estimated_end_date'] = \$this->calculatedEndDate(")
        ->toContain("\$validated['launched_at'] = \$validated['is_launched']")
        ->toContain('The procurement plan item could not be saved.')
        ->not->toContain("'Failed to create procurement plan: ' . \$e->getMessage()");
});

it('protects populated procurement plan sheets during portfolio edits', function () {
    $controller = file_get_contents(
        dirname(__DIR__, 2) . '/app/Http/Controllers/Procurement/ProcurementProgramPlanController.php'
    );
    $structure = file_get_contents(
        dirname(__DIR__, 2) . '/resources/views/procurement/structure/plans/index.blade.php'
    );

    expect($controller)
        ->toContain("\$programPlan->procurements()->exists()")
        ->toContain('Move or remove those items before changing its portfolio.');

    expect($structure)
        ->toContain('procurement.structure.edit')
        ->toContain('procurement.plans.create')
        ->toContain("{{ \$plan->is_active ? 'Active' : 'Archived' }}");
});

