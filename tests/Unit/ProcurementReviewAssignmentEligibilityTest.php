<?php

use App\Support\ProcurementReviewAssignees;

it('defines vendor and think tank accounts as ineligible procurement reviewers', function () {
    expect(ProcurementReviewAssignees::EXCLUDED_USER_TYPES)
        ->toBe(['vendor', 'think_tank']);
});

it('filters both assignment dropdowns and validates crafted assignment requests', function () {
    $prescreeningController = file_get_contents(
        dirname(__DIR__, 2).'/app/Http/Controllers/PrescreeningUserAssignmentController.php'
    );
    $evaluationController = file_get_contents(
        dirname(__DIR__, 2).'/app/Http/Controllers/EvaluationAssignmentController.php'
    );
    $legacyEvaluationController = file_get_contents(
        dirname(__DIR__, 2).'/app/Http/Controllers/Procurement/EvaluationAssignmentController.php'
    );

    expect($prescreeningController)
        ->toContain('ProcurementReviewAssignees::query()')
        ->toContain('ProcurementReviewAssignees::existsRule()')
        ->toContain('findOrFail($validated[\'user_id\'])');

    expect($evaluationController)
        ->toContain('ProcurementReviewAssignees::query()')
        ->toContain('ProcurementReviewAssignees::existsRule()')
        ->toContain('findOrFail($validated[\'user_id\'])');

    expect($legacyEvaluationController)
        ->toContain('ProcurementReviewAssignees::existsRule()');
});

it('explains the assignment restriction beside both user selectors', function () {
    $prescreeningView = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/prescreening/assignments/edit.blade.php'
    );
    $evaluationView = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/evaluations/assign-hub.blade.php'
    );

    expect($prescreeningView)
        ->toContain('Vendor and think tank accounts are not eligible');

    expect($evaluationView)
        ->toContain('Vendor and think tank accounts are excluded');
});
