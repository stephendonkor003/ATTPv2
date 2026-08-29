<?php

it('shows evaluator names and emails as separate assignment columns', function () {
    $view = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/evaluations/assign-hub.blade.php'
    );

    expect($view)
        ->toContain('<th scope="col">Evaluator</th>')
        ->toContain('<th scope="col">Email</th>')
        ->toContain('data-label="Email"')
        ->toContain('$assignment->evaluator?->email')
        ->toContain('href="mailto:{{ $evaluatorEmail }}"')
        ->toContain('No email recorded')
        ->toContain('$assignment->evaluator?->name')
        ->toContain('$assignment->evaluation?->name');
});

it('keeps each submission assignment and its actions in one readable row', function () {
    $view = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/evaluations/assign-hub.blade.php'
    );

    expect($view)
        ->toContain('@foreach ($procurement->evaluationAssignments as $assignment)')
        ->toContain('$assignment->submission?->procurement_submission_code')
        ->toContain("route('eval.assign.applicants', \$assignment->id)")
        ->toContain("route('eval.assign.destroy', \$assignment)")
        ->toContain('Specific application')
        ->toContain('Entire procurement')
        ->not->toContain('colspan="4"');
});

it('provides responsive assignment cards and labeled assignment controls', function () {
    $view = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/evaluations/assign-hub.blade.php'
    );

    expect($view)
        ->toContain('for="evaluation-{{ $procurement->id }}"')
        ->toContain('for="evaluator-{{ $procurement->id }}"')
        ->toContain('for="assignment-type-{{ $procurement->id }}"')
        ->toContain('@media (max-width: 991.98px)')
        ->toContain('content: attr(data-label)')
        ->toContain('.assignment-table tbody tr { display: grid;')
        ->toContain('data-assignment-procurement')
        ->toContain('assignmentProcurementSearch')
        ->toContain('assignmentCoverageFilter');
});

it('surfaces assignment feedback and restores the failed procurement form', function () {
    $view = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/evaluations/assign-hub.blade.php'
    );
    $controller = file_get_contents(
        dirname(__DIR__, 2).'/app/Http/Controllers/EvaluationAssignmentController.php'
    );

    expect($view)
        ->toContain("session('success')")
        ->toContain("session('error')")
        ->toContain('$errors->any()')
        ->toContain('$isOldForm')
        ->toContain('$openProcurementId')
        ->toContain('@selected($selectedEvaluatorId === (string) $user->id)');

    expect($controller)
        ->toContain('->withInput()')
        ->toContain("'open_procurement_id' => \$procurement->id")
        ->toContain("'open_procurement_id' => \$procurementId");
});

it('guides a prepared proposal round directly into the qualified technical worklist', function () {
    $view = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/evaluations/assign-hub.blade.php'
    );
    $controller = file_get_contents(
        dirname(__DIR__, 2).'/app/Http/Controllers/EvaluationAssignmentController.php'
    );

    expect($view)
        ->toContain('application_assignment_locked')
        ->toContain('assignment-technical-worklist')
        ->toContain('Technical evaluation worklist')
        ->toContain('now proceed to technical evaluation')
        ->toContain('assignment-technical-candidate-card')
        ->toContain('@unless ($applicationAssignmentLocked)')
        ->toContain('technical_proposal_procurement')
        ->toContain('Original applications are locked');

    expect($controller)
        ->toContain('hasPreparedTechnicalProposalRound')
        ->toContain('The original EOI application stage is locked')
        ->toContain('Evaluator assignment notification could not be sent')
        ->toContain('catch (Throwable $exception)');
});

it('compiles the evaluator assignment blade template', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/evaluations/assign-hub.blade.php'
    );

    $compiler = new \Illuminate\View\Compilers\BladeCompiler(
        new \Illuminate\Filesystem\Filesystem,
        sys_get_temp_dir()
    );
    $compiled = $compiler->compileString($source);

    expect($compiled)
        ->toContain("startSection('content')")
        ->toContain('assignment-email-cell')
        ->not->toContain("@extends('layouts.app')");
});
