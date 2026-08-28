<?php

use App\Models\Evaluation;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationCriteria;
use App\Models\EvaluationCriteriaScore;
use App\Models\EvaluationSection;
use App\Models\EvaluationSubmission;
use App\Models\FormSubmission;
use App\Models\User;
use App\Services\EoiQualificationService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

it('returns Fully Qualified only when the complete panel records all qualified decisions', function () {
    $service = new EoiQualificationService;

    expect($service->classify([2, 2, 2], true))
        ->toMatchArray([
            'code' => EoiQualificationService::OUTCOME_FULLY_QUALIFIED,
            'label' => 'Fully Qualified',
            'tone' => 'success',
        ])
        ->and($service->classify([2, 2, 2], false)['code'])
        ->toBe(EoiQualificationService::OUTCOME_PENDING);
});

it('returns Average Qualified for a complete positive mixed panel and identifies technical evaluation as next', function () {
    $outcome = (new EoiQualificationService)->classify([2, 1, 2], true);

    expect($outcome)
        ->toMatchArray([
            'code' => EoiQualificationService::OUTCOME_AVERAGE_QUALIFIED,
            'label' => 'Average Qualified',
            'tone' => 'warning',
        ])
        ->and($outcome['description'])
        ->toContain('advances to Technical Evaluation');
});

it('applies one Not Qualified decision as an immediate veto regardless of other decisions or completeness', function () {
    $service = new EoiQualificationService;

    expect($service->classify([2, 2, 0], true)['code'])
        ->toBe(EoiQualificationService::OUTCOME_NOT_QUALIFIED)
        ->and($service->classify([2, 1, 0], false))
        ->toMatchArray([
            'code' => EoiQualificationService::OUTCOME_NOT_QUALIFIED,
            'label' => 'Not Qualified',
            'tone' => 'danger',
        ])
        ->and($service->classify([0], false)['code'])
        ->toBe(EoiQualificationService::OUTCOME_NOT_QUALIFIED);
});

it('keeps every incomplete panel without a veto pending', function (array $decisions) {
    expect((new EoiQualificationService)->classify($decisions, false)['code'])
        ->toBe(EoiQualificationService::OUTCOME_PENDING);
})->with([
    'all qualified so far' => [[2, 2]],
    'qualified and average so far' => [[2, 1]],
    'average only so far' => [[1]],
    'no decisions yet' => [[]],
]);

it('ignores null and malformed values instead of converting them into categorical outcomes', function () {
    $service = new EoiQualificationService;
    $malformed = [null, '', ' ', 'not-a-decision', '2-not-a-decision', 3, -1, 2.5, false, true];

    expect($service->classify($malformed, true)['code'])
        ->toBe(EoiQualificationService::OUTCOME_PENDING)
        ->and($service->classify([2, null, 'not-a-decision'], true)['code'])
        ->toBe(EoiQualificationService::OUTCOME_FULLY_QUALIFIED)
        ->and($service->classify([2, 1, null, 'not-a-decision'], true)['code'])
        ->toBe(EoiQualificationService::OUTCOME_AVERAGE_QUALIFIED);
});

it('uses only current distinct panel tasks and ignores evidence from removed assignments', function () {
    $evaluation = (new Evaluation)->forceFill([
        'id' => (string) Str::uuid(),
        'name' => 'EOI eligibility',
        'type' => Evaluation::TYPE_EOI,
    ]);
    $section = (new EvaluationSection)->forceFill([
        'id' => (string) Str::uuid(),
        'evaluation_id' => $evaluation->id,
        'name' => 'Eligibility',
        'sort_order' => 1,
    ]);
    $criteria = collect(['Legal status', 'Relevant experience'])
        ->map(fn (string $name) => (new EvaluationCriteria)->forceFill([
            'id' => (string) Str::uuid(),
            'evaluation_section_id' => $section->id,
            'name' => $name,
        ]));
    $section->setRelation('criteria', new EloquentCollection($criteria->all()));
    $evaluation->setRelation('sections', new EloquentCollection([$section]));

    $applicant = (new FormSubmission)->forceFill([
        'id' => (string) Str::uuid(),
        'procurement_id' => (string) Str::uuid(),
        'procurement_submission_code' => 'EOI-001',
        'status' => FormSubmission::STATUS_EOI_EVALUATION,
    ]);
    $applicant->setRelation('submitter', (new User)->forceFill(['name' => 'Applicant One']));
    $applicant->setRelation('values', new EloquentCollection);

    $evaluators = collect(['Panel One', 'Panel Two'])
        ->map(fn (string $name) => (new User)->forceFill([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'email' => Str::slug($name).'@example.test',
        ]));
    $assignmentFor = function (User $evaluator, ?string $specificApplicant = null) use ($evaluation, $applicant): EvaluationAssignment {
        $assignment = (new EvaluationAssignment)->forceFill([
            'id' => (string) Str::uuid(),
            'evaluation_id' => $evaluation->id,
            'procurement_id' => $applicant->procurement_id,
            'form_submission_id' => $specificApplicant,
            'user_id' => $evaluator->id,
        ]);
        $assignment->setRelation('evaluation', $evaluation);
        $assignment->setRelation('evaluator', $evaluator);

        return $assignment;
    };
    $assignments = collect([
        $assignmentFor($evaluators[0]),
        $assignmentFor($evaluators[0], $applicant->id), // Same task through overlapping coverage.
        $assignmentFor($evaluators[1]),
    ]);
    $submissionFor = function (User $evaluator, array $decisions) use ($evaluation, $applicant, $criteria): EvaluationSubmission {
        $submission = (new EvaluationSubmission)->forceFill([
            'id' => (string) Str::uuid(),
            'evaluation_id' => $evaluation->id,
            'procurement_id' => $applicant->procurement_id,
            'form_submission_id' => $applicant->id,
            'evaluator_id' => $evaluator->id,
        ]);
        $submission->setRawAttributes(array_merge($submission->getAttributes(), [
            'submitted_at' => Carbon::parse('2026-08-26 12:00:00'),
        ]), true);
        $scores = $criteria->values()->map(function (EvaluationCriteria $criterion, int $index) use ($submission, $decisions) {
            return (new EvaluationCriteriaScore)->forceFill([
                'id' => (string) Str::uuid(),
                'submission_id' => $submission->id,
                'evaluation_criteria_id' => $criterion->id,
                'decision' => $decisions[$index],
                'comment' => 'Panel evidence',
            ]);
        });
        $submission->setRelation('evaluation', $evaluation);
        $submission->setRelation('evaluator', $evaluator);
        $submission->setRelation('criteriaScores', new EloquentCollection($scores->all()));

        return $submission;
    };

    $service = new EoiQualificationService;
    $method = new ReflectionMethod($service, 'buildApplicantRow');
    $firstOnly = $method->invoke(
        $service,
        $applicant,
        $assignments,
        collect([$submissionFor($evaluators[0], [2, 2])]),
        collect([$evaluation])
    );

    expect($firstOnly)
        ->toMatchArray([
            'expected_tasks' => 2,
            'completed_tasks' => 1,
            'expected_evaluators' => 2,
            'completed_evaluators' => 1,
            'panel_complete' => false,
            'can_advance' => false,
        ])
        ->and($firstOnly['outcome']['code'])
        ->toBe(EoiQualificationService::OUTCOME_PENDING);

    $incompleteWithVeto = $method->invoke(
        $service,
        $applicant,
        $assignments,
        collect([$submissionFor($evaluators[0], [2, 0])]),
        collect([$evaluation])
    );

    expect($incompleteWithVeto)
        ->toMatchArray([
            'panel_complete' => false,
            'can_advance' => false,
            'next_stage' => 'Awaiting EOI panel',
        ])
        ->and($incompleteWithVeto['outcome']['code'])
        ->toBe(EoiQualificationService::OUTCOME_NOT_QUALIFIED)
        ->and($incompleteWithVeto['outcome']['description'])
        ->toContain('final routing remains held');

    $completeWithVeto = $method->invoke(
        $service,
        $applicant,
        $assignments,
        collect([
            $submissionFor($evaluators[0], [2, 2]),
            $submissionFor($evaluators[1], [2, 0]),
        ]),
        collect([$evaluation])
    );

    expect($completeWithVeto)
        ->toMatchArray([
            'expected_tasks' => 2,
            'completed_tasks' => 2,
            'panel_complete' => true,
            'can_advance' => false,
        ])
        ->and($completeWithVeto['counts']['not_qualified'])->toBe(1)
        ->and($completeWithVeto['outcome']['code'])
        ->toBe(EoiQualificationService::OUTCOME_NOT_QUALIFIED)
        ->and($completeWithVeto['next_stage'])->toBe('Does not advance');

    $currentPanelOnly = $method->invoke(
        $service,
        $applicant,
        collect([$assignmentFor($evaluators[0])]),
        collect([
            $submissionFor($evaluators[0], [2, 2]),
            $submissionFor($evaluators[1], [0, 0]), // Historical evidence from a removed assignment.
        ]),
        collect([$evaluation])
    );

    expect($currentPanelOnly)
        ->toMatchArray([
            'expected_tasks' => 1,
            'completed_tasks' => 1,
            'panel_complete' => true,
            'can_advance' => true,
        ])
        ->and($currentPanelOnly['counts'])->toBe([
            'qualified' => 2,
            'average_qualified' => 0,
            'not_qualified' => 0,
        ])
        ->and($currentPanelOnly['outcome']['code'])
        ->toBe(EoiQualificationService::OUTCOME_FULLY_QUALIFIED)
        ->and($currentPanelOnly['evaluation_reports']->first()['members'])->toHaveCount(1);

    $allAssignmentsRemoved = $method->invoke(
        $service,
        $applicant,
        collect(),
        collect([
            $submissionFor($evaluators[0], [2, 2]),
            $submissionFor($evaluators[1], [0, 0]),
        ]),
        collect([$evaluation])
    );

    expect($allAssignmentsRemoved)
        ->toMatchArray([
            'assignment_baseline_available' => false,
            'expected_tasks' => 0,
            'completed_tasks' => 0,
            'expected_evaluators' => 0,
            'completed_evaluators' => 0,
            'panel_complete' => false,
            'can_advance' => false,
            'completion_percent' => 0,
        ])
        ->and($allAssignmentsRemoved['counts'])->toBe([
            'qualified' => 0,
            'average_qualified' => 0,
            'not_qualified' => 0,
        ])
        ->and($allAssignmentsRemoved['outcome']['code'])
        ->toBe(EoiQualificationService::OUTCOME_PENDING)
        ->and($allAssignmentsRemoved['evaluation_reports'])->toBeEmpty();
});

it('wires panel outcomes into the EOI and Technical Evaluation lifecycle gates', function () {
    $serviceSource = file_get_contents(
        dirname(__DIR__, 2).'/app/Services/EoiQualificationService.php'
    );
    $submissionController = file_get_contents(
        dirname(__DIR__, 2).'/app/Http/Controllers/EvaluationSubmissionController.php'
    );
    $submissionModel = file_get_contents(
        dirname(__DIR__, 2).'/app/Models/FormSubmission.php'
    );

    expect($serviceSource)
        ->toContain('synchronizeApplicantStage')
        ->toContain('STATUS_TECHNICAL_EVALUATION')
        ->toContain('STATUS_EOI_NOT_QUALIFIED')
        ->and($submissionController)
        ->toContain('The EOI panel must be completed before Technical Evaluation can begin.')
        ->and($submissionModel)
        ->toContain("STATUS_EOI_NOT_QUALIFIED = 'eoi_not_qualified'")
        ->toContain('self::STATUS_EOI_NOT_QUALIFIED')
        ->and((new FormSubmission(['status' => FormSubmission::STATUS_EOI_EVALUATION]))->isAvailableForEvaluation())
        ->toBeTrue()
        ->and((new FormSubmission(['status' => FormSubmission::STATUS_TECHNICAL_EVALUATION]))->isAvailableForEvaluation())
        ->toBeTrue()
        ->and((new FormSubmission(['status' => FormSubmission::STATUS_EOI_NOT_QUALIFIED]))->isAvailableForEvaluation())
        ->toBeFalse();
});
