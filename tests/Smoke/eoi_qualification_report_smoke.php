<?php

use App\Models\Evaluation;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationCriteria;
use App\Models\EvaluationCriteriaScore;
use App\Models\EvaluationSection;
use App\Models\EvaluationSubmission;
use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\User;
use App\Services\EoiQualificationService;
use App\Support\PdfBranding;
use App\Support\ProcurementReviewAssignees;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

$administrator = User::query()->where('user_type', 'admin')->firstOrFail();
$request = Request::create('/reports/evaluations/eoi/smoke', 'GET');
$request->setUserResolver(fn () => $administrator);
$app->instance('request', $request);
Auth::setUser($administrator);

DB::beginTransaction();

try {
    $evaluation = Evaluation::query()
        ->where('type', Evaluation::TYPE_EOI)
        ->whereDoesntHave('assignments')
        ->with('sections.criteria')
        ->firstOrFail();

    if ($evaluation->sections->flatMap->criteria->isEmpty()) {
        $section = $evaluation->sections->first() ?? EvaluationSection::create([
            'evaluation_id' => $evaluation->id,
            'name' => 'EOI smoke eligibility',
            'sort_order' => 1,
        ]);

        foreach (['Legal eligibility', 'Relevant experience'] as $criterionName) {
            EvaluationCriteria::create([
                'evaluation_section_id' => $section->id,
                'name' => $criterionName,
                'description' => 'Transactional EOI qualification criterion.',
                'max_score' => null,
            ]);
        }

        $evaluation->load('sections.criteria');
    }

    $criteria = $evaluation->sections->flatMap->criteria->values();

    $procurement = Procurement::query()
        ->has('submissions', '>=', 2)
        ->whereDoesntHave('evaluationAssignments.evaluation', fn ($query) => $query
            ->where('type', Evaluation::TYPE_EOI))
        ->whereDoesntHave('submissions.evaluationSubmissions.evaluation', fn ($query) => $query
            ->where('type', Evaluation::TYPE_EOI))
        ->firstOrFail();
    $applicants = $procurement->submissions()->limit(2)->get();
    $evaluators = ProcurementReviewAssignees::query()->limit(2)->get();

    if ($criteria->isEmpty() || $applicants->count() !== 2 || $evaluators->count() !== 2) {
        throw new RuntimeException('The EOI smoke fixture needs criteria, two applicants, and two eligible evaluators.');
    }

    $applicants->each(function (FormSubmission $applicant): void {
        $applicant->update(['status' => 'prescreen_passed']);
    });

    foreach ($evaluators as $evaluator) {
        EvaluationAssignment::create([
            'evaluation_id' => $evaluation->id,
            'procurement_id' => $procurement->id,
            'form_submission_id' => null,
            'user_id' => $evaluator->id,
            'assigned_by' => $administrator->id,
            'assigned_at' => now(),
            'status' => 'assigned',
        ]);
    }

    $decisionSets = [
        // Applicant one has no veto and therefore advances as Average Qualified.
        [[2, 2], [2, 1]],
        // Applicant two has one veto and therefore cannot advance.
        [[2, 2], [2, 0]],
    ];

    foreach ($applicants->values() as $applicantIndex => $applicant) {
        foreach ($evaluators->values() as $evaluatorIndex => $evaluator) {
            $submission = EvaluationSubmission::create([
                'evaluation_id' => $evaluation->id,
                'procurement_id' => $procurement->id,
                'form_submission_id' => $applicant->id,
                'evaluator_id' => $evaluator->id,
                'submitted_at' => now(),
            ]);

            foreach ($criteria as $criterionIndex => $criterion) {
                $configured = $decisionSets[$applicantIndex][$evaluatorIndex];
                $decision = $configured[$criterionIndex] ?? $configured[array_key_last($configured)];

                EvaluationCriteriaScore::create([
                    'submission_id' => $submission->id,
                    'evaluation_criteria_id' => $criterion->id,
                    'decision' => $decision,
                    'score' => null,
                    'comment' => 'Transactional EOI smoke evidence.',
                ]);
            }
        }
    }

    $qualificationService = $app->make(EoiQualificationService::class);
    $qualificationService->synchronizeApplicantStage($applicants[0]);
    $qualificationService->synchronizeApplicantStage($applicants[1]);
    $report = $qualificationService->buildProcurementReport($procurement);

    $firstRow = $report['applicants']->first(
        fn (array $row): bool => (string) $row['applicant']->id === (string) $applicants[0]->id
    );
    $secondRow = $report['applicants']->first(
        fn (array $row): bool => (string) $row['applicant']->id === (string) $applicants[1]->id
    );

    if (($firstRow['outcome']['code'] ?? null) !== EoiQualificationService::OUTCOME_AVERAGE_QUALIFIED
        || ! ($firstRow['can_advance'] ?? false)
        || $applicants[0]->fresh()->status !== FormSubmission::STATUS_TECHNICAL_EVALUATION) {
        throw new RuntimeException('The no-veto EOI applicant did not advance to Technical Evaluation.');
    }

    if (($secondRow['outcome']['code'] ?? null) !== EoiQualificationService::OUTCOME_NOT_QUALIFIED
        || ($secondRow['can_advance'] ?? true)
        || $applicants[1]->fresh()->status !== FormSubmission::STATUS_EOI_NOT_QUALIFIED) {
        throw new RuntimeException('The EOI veto did not block the Not Qualified applicant.');
    }

    $html = view('reports.evaluations.eoi-procurement', compact('report'))->render();
    foreach (['EOI Qualification Report', 'Average Qualified', 'Not Qualified', 'Technical Evaluation'] as $needle) {
        if (! str_contains($html, $needle)) {
            throw new RuntimeException("The EOI HTML report is missing: {$needle}");
        }
    }

    $pdf = Pdf::loadView(
        'reports.evaluations.pdf.eoi-procurement',
        array_merge(compact('report'), PdfBranding::viewData())
    )->setPaper('a4', 'landscape')->output();

    if (! str_starts_with($pdf, '%PDF')) {
        throw new RuntimeException('The EOI qualification download did not render a valid PDF.');
    }

    echo "EOI_QUALIFICATION_REPORT_SMOKE_OK\n";
} finally {
    DB::rollBack();
}
