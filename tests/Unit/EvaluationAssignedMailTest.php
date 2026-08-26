<?php

it('provides the evaluator assignment email view required by the mailable', function () {
    $root = dirname(__DIR__, 2);
    $mailable = file_get_contents($root.'/app/Mail/EvaluationAssigned.php');
    $controller = file_get_contents($root.'/app/Http/Controllers/EvaluationAssignmentController.php');
    $viewPath = $root.'/resources/views/emails/evaluations/assigned.blade.php';

    expect(is_file($viewPath))->toBeTrue();

    expect($mailable)
        ->toContain('public ?FormSubmission $submission = null')
        ->toContain("->view('emails.evaluations.assigned')")
        ->and($controller)
        ->toContain('new EvaluationAssigned($evaluator, $evaluation, $procurement, $submission)');
});

it('renders safe fallbacks for optional assignment email data', function () {
    $view = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/emails/evaluations/assigned.blade.php'
    );

    expect($view)
        ->toContain("data_get(\$evaluator ?? null, 'name')")
        ->toContain("data_get(\$evaluation ?? null, 'name')")
        ->toContain("data_get(\$procurement ?? null, 'title')")
        ->toContain("data_get(\$procurement ?? null, 'reference_no')")
        ->toContain("data_get(\$submission ?? null, 'procurement_submission_code')")
        ->toContain("\$assignmentScope = \$submissionCode !== '' ? 'Specific submission' : 'Entire procurement'")
        ->toContain("@if (\$submissionCode !== '')")
        ->toContain("route('my.eval.index')")
        ->toContain('Open My Evaluations')
        ->not->toContain('{{ $submission->procurement_submission_code }}')
        ->not->toContain('{{ $evaluator->name }}')
        ->not->toContain('{{ $evaluation->name }}')
        ->not->toContain('{{ $procurement->title }}');
});
