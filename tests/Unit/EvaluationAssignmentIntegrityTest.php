<?php

it('keeps the active assignment set authoritative and safely removes abandoned evaluation drafts', function () {
    $root = dirname(__DIR__, 2);
    $assignmentController = file_get_contents($root.'/app/Http/Controllers/EvaluationAssignmentController.php');
    $reportController = file_get_contents($root.'/app/Http/Controllers/EvaluationReportController.php');
    $qualificationService = file_get_contents($root.'/app/Services/EoiQualificationService.php');

    expect($assignmentController)
        ->toContain("->whereNull('form_submission_id')")
        ->toContain("->orWhere('form_submission_id', \$validated['submission_id'])")
        ->toContain('evaluationSubmissionsForAssignment')
        ->toContain('filled($record->submitted_at)')
        ->toContain('$draft->criteriaScores()->delete()')
        ->toContain('$draft->sectionScores()->delete()')
        ->toContain('$draft->delete()')
        ->and($reportController)
        ->toContain('activeReportSubmissions')
        ->toContain('eoiSubmissionHasActiveAssignment')
        ->and($qualificationService)
        ->toContain("'assignment_baseline_available' => \$expectedTasks->isNotEmpty()")
        ->toContain("->filter(fn (array \$report): bool => \$report['members']->isNotEmpty())")
        ->not->toContain('Legacy/imported submissions may not have surviving assignment rows.')
        ->not->toContain("->concat(\$submissionRecords->pluck('evaluation'))");
});
