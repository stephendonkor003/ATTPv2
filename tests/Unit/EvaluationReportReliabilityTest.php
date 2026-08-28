<?php

use App\Http\Controllers\EvaluationReportController;
use App\Models\Evaluation;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationCriteria;
use App\Models\EvaluationCriteriaScore;
use App\Models\EvaluationSection;
use App\Models\EvaluationSubmission;
use App\Models\User;
use App\Support\PdfBranding;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;

function invokeEvaluationReportMethod(string $method, mixed ...$arguments): mixed
{
    $reflection = new ReflectionMethod(EvaluationReportController::class, $method);

    return $reflection->invoke(new EvaluationReportController, ...$arguments);
}

function reportEvaluation(string $type = Evaluation::TYPE_SERVICES): Evaluation
{
    return (new Evaluation)->forceFill([
        'id' => (string) Str::uuid(),
        'name' => 'Report evaluation',
        'type' => $type,
    ]);
}

it('builds a readable JPEG data URI and safely handles a missing logo', function () {
    $root = dirname(__DIR__, 2);
    $logoPath = $root.'/public/assets/images/attp-logo.jpeg';
    $dataUri = PdfBranding::logoDataUri($logoPath);

    expect($dataUri)
        ->toStartWith('data:image/jpeg;base64,')
        ->and(base64_decode(str($dataUri)->after(',')->toString(), true))
        ->toBe(file_get_contents($logoPath))
        ->and(PdfBranding::logoDataUri($root.'/public/assets/images/missing-logo.jpeg'))
        ->toBeNull();
});

it('uses the configured criterion maximum instead of a hard coded one hundred points', function () {
    $evaluation = reportEvaluation();
    $firstSection = (new EvaluationSection)->forceFill(['id' => (string) Str::uuid()]);
    $secondSection = (new EvaluationSection)->forceFill(['id' => (string) Str::uuid()]);

    $firstSection->setRelation('criteria', new EloquentCollection([
        (new EvaluationCriteria)->forceFill(['max_score' => 10]),
        (new EvaluationCriteria)->forceFill(['max_score' => 12.5]),
    ]));
    $secondSection->setRelation('criteria', new EloquentCollection([
        (new EvaluationCriteria)->forceFill(['max_score' => 8]),
    ]));
    $evaluation->setRelation('sections', new EloquentCollection([$firstSection, $secondSection]));

    $submission = new EvaluationSubmission;
    $submission->setRelation('evaluation', $evaluation);

    $categoricalSubmission = new EvaluationSubmission;
    $categoricalSubmission->setRelation('evaluation', reportEvaluation(Evaluation::TYPE_GOODS));

    expect(invokeEvaluationReportMethod('overallMax', $submission))
        ->toBe(30.5)
        ->and(invokeEvaluationReportMethod('overallMax', $categoricalSubmission))
        ->toBeNull();
});

it('counts only real identifiers and keeps same name evaluators separate', function () {
    $firstEvaluator = (new User)->forceFill([
        'id' => (string) Str::uuid(),
        'name' => 'Same Evaluator Name',
        'email' => 'first.evaluator@example.test',
    ]);
    $secondEvaluator = (new User)->forceFill([
        'id' => (string) Str::uuid(),
        'name' => 'Same Evaluator Name',
        'email' => 'second.evaluator@example.test',
    ]);

    $makeSubmission = function (?User $evaluator, ?string $procurementId, float $score): EvaluationSubmission {
        $submission = (new EvaluationSubmission)->forceFill([
            'evaluator_id' => $evaluator?->id,
            'procurement_id' => $procurementId,
            'overall_score' => $score,
        ]);
        $submission->setRelation('evaluator', $evaluator);
        $submission->setRelation('evaluation', reportEvaluation());

        return $submission;
    };

    $submissions = collect([
        $makeSubmission($firstEvaluator, (string) Str::uuid(), 10),
        $makeSubmission($secondEvaluator, (string) Str::uuid(), 20),
        $makeSubmission(null, null, 30),
    ]);

    $summary = invokeEvaluationReportMethod('buildSummary', $submissions);
    $breakdown = invokeEvaluationReportMethod('buildEvaluatorBreakdown', $submissions);

    expect($summary)
        ->toMatchArray([
            'total' => 3,
            'procurements' => 2,
            'evaluators' => 2,
            'avg_overall' => 20.0,
        ])
        ->and($breakdown)->toHaveCount(3)
        ->and($breakdown->pluck('email')->filter()->sort()->values()->all())
        ->toBe([
            'first.evaluator@example.test',
            'second.evaluator@example.test',
        ]);
});

it('protects unfinished reports and removes applicant identity from anonymised output', function () {
    $controller = file_get_contents(
        dirname(__DIR__, 2).'/app/Http/Controllers/EvaluationReportController.php'
    );
    $pdfView = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/reports/evaluations/pdf/submission.blade.php'
    );

    expect($controller)
        ->toContain('abort_unless(')
        ->toContain('$submission->submitted_at')
        ->toContain("'A completed evaluation report was not found.'")
        ->toContain("? 'applicant'")
        ->toContain('PdfBranding::viewData()')
        ->not->toContain('return 100;');

    expect($pdfView)
        ->toContain("\$applicantName = \$anonymised ? 'Applicant XXX'")
        ->toContain("\$applicantEmail = \$anonymised ? 'Redacted'")
        ->toContain("\$submissionCode = \$anonymised ? 'ANONYMISED'")
        ->toContain('$applicantIdentifiers')
        ->toContain('str_ireplace')
        ->toContain('{{ $applicantEmail }}');
});

it('skips missing evaluation relations when building detailed statistics', function () {
    $controller = file_get_contents(
        dirname(__DIR__, 2).'/app/Http/Controllers/EvaluationReportController.php'
    );
    $orphan = new EvaluationSubmission;
    $orphan->setRelation('evaluation', null);
    $orphan->setRelation('criteriaScores', new EloquentCollection);
    $statistics = invokeEvaluationReportMethod('buildEvaluationStats', collect([$orphan]));

    expect($controller)
        ->toContain('->filter(fn ($submission) => $submission->evaluation !== null)')
        ->toContain("->pluck('evaluator_id')->filter()->unique()->count()")
        ->and($statistics)->toBeEmpty();
});

it('normalises services scores and ranks only applicants with complete panels', function () {
    $evaluation = reportEvaluation();
    $section = (new EvaluationSection)->forceFill(['id' => (string) Str::uuid()]);
    $section->setRelation('criteria', new EloquentCollection([
        (new EvaluationCriteria)->forceFill(['max_score' => 50]),
    ]));
    $evaluation->setRelation('sections', new EloquentCollection([$section]));

    $submission = function (string $applicantId, string $evaluatorId, float $score) use ($evaluation): EvaluationSubmission {
        $report = (new EvaluationSubmission)->forceFill([
            'id' => (string) Str::uuid(),
            'evaluation_id' => $evaluation->id,
            'form_submission_id' => $applicantId,
            'evaluator_id' => $evaluatorId,
            'overall_score' => $score,
        ]);
        $report->setRelation('evaluation', $evaluation);
        $report->setRelation('criteriaScores', new EloquentCollection);
        $report->setRelation('applicant', null);

        return $report;
    };

    $evaluators = collect(range(1, 5))->mapWithKeys(fn (int $index): array => [
        $index => (string) Str::uuid(),
    ]);
    $reports = collect([
        $submission('applicant-a', $evaluators[1], 40),
        $submission('applicant-b', $evaluators[2], 40),
        $submission('applicant-c', $evaluators[3], 35),
        $submission('applicant-d', $evaluators[4], 45),
    ]);
    $assignment = fn (string $applicantId, string $evaluatorId): EvaluationAssignment => (new EvaluationAssignment)->forceFill([
        'evaluation_id' => $evaluation->id,
        'form_submission_id' => $applicantId,
        'user_id' => $evaluatorId,
    ]);
    $assignments = collect([
        $assignment('applicant-a', $evaluators[1]),
        $assignment('applicant-b', $evaluators[2]),
        $assignment('applicant-c', $evaluators[3]),
        $assignment('applicant-d', $evaluators[4]),
        $assignment('applicant-d', $evaluators[5]),
    ]);

    $rows = invokeEvaluationReportMethod(
        'buildMethodApplicantSummaries',
        $reports,
        Evaluation::TYPE_SERVICES,
        $assignments
    );

    expect($rows->pluck('metric')->all())->toBe([80.0, 80.0, 70.0, 90.0])
        ->and($rows->pluck('rank')->all())->toBe([1, 1, 3, null])
        ->and($rows->last())
        ->toMatchArray([
            'outcome' => 'Panel incomplete',
            'completed_tasks' => 1,
            'expected_tasks' => 2,
            'panel_complete' => false,
        ]);
});

it('excludes services scores when the template maximum is not configured', function () {
    $evaluation = reportEvaluation();
    $evaluation->setRelation('sections', new EloquentCollection);
    $submission = (new EvaluationSubmission)->forceFill([
        'overall_score' => 40,
    ]);
    $submission->setRelation('evaluation', $evaluation);

    expect(invokeEvaluationReportMethod('normalisedServiceScore', $submission))->toBeNull();
});

it('counts procurement-wide evaluator assignments toward each applicant panel', function () {
    $evaluationId = (string) Str::uuid();
    $evaluatorId = (string) Str::uuid();
    $submission = (new EvaluationSubmission)->forceFill([
        'id' => (string) Str::uuid(),
        'evaluation_id' => $evaluationId,
        'form_submission_id' => (string) Str::uuid(),
        'evaluator_id' => $evaluatorId,
    ]);
    $assignment = (new EvaluationAssignment)->forceFill([
        'evaluation_id' => $evaluationId,
        'form_submission_id' => null,
        'user_id' => $evaluatorId,
    ]);

    expect(invokeEvaluationReportMethod(
        'applicantPanelProgress',
        collect([$submission]),
        collect([$assignment])
    ))->toBe([
        'expected_tasks' => 1,
        'completed_tasks' => 1,
        'panel_complete' => true,
        'panel_status' => 'Panel complete',
    ]);
});

it('marks legacy reports without assignment history as unverifiable instead of incomplete', function () {
    $submission = (new EvaluationSubmission)->forceFill([
        'id' => (string) Str::uuid(),
        'evaluation_id' => (string) Str::uuid(),
        'form_submission_id' => (string) Str::uuid(),
        'evaluator_id' => (string) Str::uuid(),
    ]);

    expect(invokeEvaluationReportMethod(
        'applicantPanelProgress',
        collect([$submission]),
        collect()
    ))->toBe([
        'expected_tasks' => null,
        'completed_tasks' => 1,
        'panel_complete' => false,
        'panel_status' => 'Assignment baseline unavailable',
    ]);
});

it('counts an EOI submission only while a current assignment covers its exact panel task', function () {
    $evaluationId = (string) Str::uuid();
    $applicantId = (string) Str::uuid();
    $evaluatorId = (string) Str::uuid();
    $submission = (new EvaluationSubmission)->forceFill([
        'evaluation_id' => $evaluationId,
        'form_submission_id' => $applicantId,
        'evaluator_id' => $evaluatorId,
    ]);
    $assignment = fn (?string $assignedApplicant, ?string $assignedEvaluator = null): EvaluationAssignment => (new EvaluationAssignment)->forceFill([
        'evaluation_id' => $evaluationId,
        'form_submission_id' => $assignedApplicant,
        'user_id' => $assignedEvaluator ?? $evaluatorId,
    ]);

    expect(invokeEvaluationReportMethod(
        'eoiSubmissionHasActiveAssignment',
        $submission,
        collect([$assignment(null)])
    ))->toBeTrue()
        ->and(invokeEvaluationReportMethod(
            'eoiSubmissionHasActiveAssignment',
            $submission,
            collect([$assignment($applicantId)])
        ))->toBeTrue()
        ->and(invokeEvaluationReportMethod(
            'eoiSubmissionHasActiveAssignment',
            $submission,
            collect([$assignment((string) Str::uuid())])
        ))->toBeFalse()
        ->and(invokeEvaluationReportMethod(
            'eoiSubmissionHasActiveAssignment',
            $submission,
            collect([$assignment($applicantId, (string) Str::uuid())])
        ))->toBeFalse()
        ->and(invokeEvaluationReportMethod(
            'eoiSubmissionHasActiveAssignment',
            $submission,
            collect()
        ))->toBeFalse();
});

it('keeps goods outcomes categorical and never assigns a numeric rank', function () {
    $evaluation = reportEvaluation(Evaluation::TYPE_GOODS);
    $submission = (new EvaluationSubmission)->forceFill([
        'id' => (string) Str::uuid(),
        'form_submission_id' => 'goods-applicant',
    ]);
    $submission->setRelation('evaluation', $evaluation);
    $submission->setRelation('applicant', null);
    $submission->setRelation('criteriaScores', new EloquentCollection([
        (new EvaluationCriteriaScore)->forceFill(['decision' => 1]),
        (new EvaluationCriteriaScore)->forceFill(['decision' => 0]),
    ]));

    $row = invokeEvaluationReportMethod(
        'buildMethodApplicantSummaries',
        collect([$submission]),
        Evaluation::TYPE_GOODS
    )->first();

    expect($row)
        ->toMatchArray([
            'rank' => null,
            'metric' => null,
            'outcome' => 'Exceptions recorded',
            'counts' => ['yes' => 1, 'no' => 1, 'total' => 2],
        ]);
});

it('neutralises spreadsheet formulas in exported user-controlled values', function () {
    expect(invokeEvaluationReportMethod('safeSpreadsheetValue', '=HYPERLINK("https://example.test")'))
        ->toBe('\'=HYPERLINK("https://example.test")')
        ->and(invokeEvaluationReportMethod('safeSpreadsheetValue', '  +1+1'))
        ->toBe('\'  +1+1')
        ->and(invokeEvaluationReportMethod('safeSpreadsheetValue', 'Ordinary applicant'))
        ->toBe('Ordinary applicant');
});
