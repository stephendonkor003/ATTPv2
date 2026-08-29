<?php

use App\Http\Controllers\EvaluationReportController;
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
        ->first();

    if (! $evaluation) {
        $evaluation = Evaluation::create([
            'name' => 'EOI qualification smoke '.str()->upper(str()->random(6)),
            'description' => 'Transactional EOI qualification report fixture.',
            'status' => 'open',
            'type' => Evaluation::TYPE_EOI,
            'created_by' => $administrator->getKey(),
        ])->load('sections.criteria');
    }

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

    // Simulate the historical bug: a removed evaluator assignment left a
    // submitted NQ record behind. It must remain outside the active panel.
    $orphanEvaluator = User::query()
        ->whereNotIn('id', $evaluators->pluck('id'))
        ->firstOrFail();
    $orphanSubmission = EvaluationSubmission::create([
        'evaluation_id' => $evaluation->id,
        'procurement_id' => $procurement->id,
        'form_submission_id' => $applicants[0]->id,
        'evaluator_id' => $orphanEvaluator->id,
        'submitted_at' => now(),
    ]);

    foreach ($criteria as $criterion) {
        EvaluationCriteriaScore::create([
            'submission_id' => $orphanSubmission->id,
            'evaluation_criteria_id' => $criterion->id,
            'decision' => 0,
            'score' => null,
            'comment' => 'Historical evidence from a removed assignment.',
        ]);
    }

    $qualificationService = $app->make(EoiQualificationService::class);
    $qualificationService->synchronizeApplicantStage($applicants[0]);
    $qualificationService->synchronizeApplicantStage($applicants[1]);
    $report = $qualificationService->buildProcurementReport($procurement);

    $activeReportFilter = new ReflectionMethod(EvaluationReportController::class, 'activeReportSubmissions');
    $activeReportSubmissions = $activeReportFilter->invoke(
        $app->make(EvaluationReportController::class),
        EvaluationSubmission::query()
            ->with('evaluation')
            ->where('procurement_id', $procurement->id)
            ->where('evaluation_id', $evaluation->id)
            ->whereNotNull('submitted_at')
            ->get()
    );

    if ($activeReportSubmissions->count() !== 4
        || $activeReportSubmissions->contains('id', $orphanSubmission->id)) {
        throw new RuntimeException('A removed EOI assignment still appeared in a consolidated active report surface.');
    }

    $firstRow = $report['applicants']->first(
        fn (array $row): bool => (string) $row['applicant']->id === (string) $applicants[0]->id
    );
    $secondRow = $report['applicants']->first(
        fn (array $row): bool => (string) $row['applicant']->id === (string) $applicants[1]->id
    );

    if (($firstRow['outcome']['code'] ?? null) !== EoiQualificationService::OUTCOME_AVERAGE_QUALIFIED
        || ! ($firstRow['can_advance'] ?? false)
        || ($firstRow['counts']['not_qualified'] ?? null) !== 0
        || ($firstRow['expected_tasks'] ?? null) !== 2
        || ($firstRow['completed_tasks'] ?? null) !== 2
        || ($report['stats']['panel_members'] ?? null) !== 2
        || ($report['stats']['submitted_evaluations'] ?? null) !== 4
        || $applicants[0]->fresh()->status !== FormSubmission::STATUS_TECHNICAL_EVALUATION) {
        throw new RuntimeException('The active EOI panel was changed by evidence from a removed assignment.');
    }

    if (($secondRow['outcome']['code'] ?? null) !== EoiQualificationService::OUTCOME_NOT_QUALIFIED
        || ($secondRow['can_advance'] ?? true)
        || $applicants[1]->fresh()->status !== FormSubmission::STATUS_EOI_NOT_QUALIFIED) {
        throw new RuntimeException('The EOI veto did not block the Not Qualified applicant.');
    }

    $html = view('reports.evaluations.eoi-procurement', compact('report'))->render();
    foreach (['Panel Decision Summary', 'Qualified Applicants', 'Not Qualified Applicants', 'Awaiting Panel Completion', 'All Applicant Decisions', 'Average Qualified', 'Technical Evaluation'] as $needle) {
        if (! str_contains($html, $needle)) {
            throw new RuntimeException("The EOI HTML report is missing: {$needle}");
        }
    }

    $expectedRankedCount = $report['qualified_ranking']->count();

    if (substr_count($html, 'data-qualified-rank="') !== $expectedRankedCount
        || ! str_contains($html, 'bi bi-trophy-fill')
        || ! str_contains($html, 'ranked first to last')
        || ! str_contains($html, 'data-qualified-progression="proceeding"')) {
        throw new RuntimeException('The EOI web report did not render every qualified applicant with a trophy rank and progression decision.');
    }

    foreach (range(1, $expectedRankedCount) as $rank) {
        if (! str_contains($html, 'data-qualified-rank="'.$rank.'"')) {
            throw new RuntimeException("The EOI web shortlist is missing qualification rank {$rank}.");
        }
    }

    if (! str_contains($html, 'data-qualified-applicant="'.$applicants[0]->id.'"')
        || str_contains($html, 'data-qualified-applicant="'.$applicants[1]->id.'"')) {
        throw new RuntimeException('The EOI shortlist did not isolate the applicants approved for Technical Evaluation.');
    }

    $summaryContainsApplicant = static function (string $outcome, string $applicantId) use ($html): bool {
        return preg_match(
            '/<li[^>]*data-summary-outcome="'.preg_quote($outcome, '/').'"[^>]*data-summary-applicant="'.preg_quote($applicantId, '/').'"[^>]*>/s',
            $html
        ) === 1;
    };

    if (! $summaryContainsApplicant('qualified', (string) $applicants[0]->id)
        || ! $summaryContainsApplicant('not-qualified', (string) $applicants[1]->id)
        || $summaryContainsApplicant('not-qualified', (string) $applicants[0]->id)
        || ! str_contains($html, 'data-summary-outcome="pending"')) {
        throw new RuntimeException('The EOI decision summary did not separate qualified, final Not Qualified, and awaiting-panel groups.');
    }

    $pdfData = array_merge(compact('report'), PdfBranding::viewData());
    $pdfHtml = view('reports.evaluations.pdf.eoi-procurement', $pdfData)->render();

    $pdfChecks = [
        'qualified applicant ranking' => str_contains($pdfHtml, 'Qualified Applicant Ranking'),
        'current shortlist decision' => str_contains($pdfHtml, 'Current shortlist decision'),
        'final workflow decision' => str_contains($pdfHtml, 'Final outcome workflow decision'),
        'active-panel rule' => str_contains($pdfHtml, 'Only currently assigned panel tasks are counted'),
        'detailed layout revision' => str_contains($pdfHtml, 'data-layout-revision="detailed-v3"'),
        'qualified applicant' => str_contains($pdfHtml, e($applicants[0]->display_name)),
        'not-qualified applicant' => str_contains($pdfHtml, e($applicants[1]->display_name)),
        'qualified progression' => str_contains($pdfHtml, 'data-qualified-progression="proceeding"'),
        'evaluation evidence appendix' => str_contains($pdfHtml, 'Applicant Evaluation Evidence Appendix'),
        'criterion evidence' => str_contains($pdfHtml, 'Transactional EOI smoke evidence.'),
        'technical workflow snapshot' => str_contains($pdfHtml, 'Technical Proposal Workflow Snapshot'),
        'communication snapshot' => str_contains($pdfHtml, 'Communication Delivery Snapshot'),
    ];
    $missingPdfChecks = collect($pdfChecks)->filter(fn (bool $passed): bool => ! $passed)->keys();

    if ($missingPdfChecks->isNotEmpty()) {
        throw new RuntimeException('The EOI PDF is missing: '.$missingPdfChecks->implode(', ').'.');
    }

    $pdfDocument = Pdf::loadView(
        'reports.evaluations.pdf.eoi-procurement',
        $pdfData
    )->setPaper('a4', 'landscape');
    $pdfDocument->render();
    $pdfPageCount = $pdfDocument->getDomPDF()->getCanvas()->get_page_count();
    $pdf = $pdfDocument->output();

    if (! str_starts_with($pdf, '%PDF')) {
        throw new RuntimeException('The EOI qualification download did not render a valid PDF.');
    }

    if ($pdfPageCount < 2) {
        throw new RuntimeException('The detailed EOI PDF did not produce a complete paged report.');
    }

    $exportController = $app->make(EvaluationReportController::class);
    $csvRowsMethod = new ReflectionMethod(EvaluationReportController::class, 'eoiCsvRows');
    [$csvHeadings, $csvRows] = $csvRowsMethod->invoke($exportController, $report, collect(), collect());
    $workbookRowsMethod = new ReflectionMethod(EvaluationReportController::class, 'eoiWorkbookRows');
    $workbookRows = $workbookRowsMethod->invoke($exportController, $report, collect(), collect());

    foreach (['Qualified rank', 'Shortlist progression', 'Current workflow decision', 'Criterion decision totals'] as $heading) {
        if (! in_array($heading, $csvHeadings, true)) {
            throw new RuntimeException("The EOI CSV export is missing the {$heading} column.");
        }
    }

    if (collect($csvRows)->doesntContain(fn (array $row): bool => in_array('Applicant outcome', $row, true))
        || ! array_key_exists('Qualified Ranking', $workbookRows)
        || ! array_key_exists('Applicant Register', $workbookRows)
        || ! array_key_exists('Evidence & Workflow', $workbookRows)
        || ! array_key_exists('Proposal Rounds', $workbookRows)
        || ! array_key_exists('Communications', $workbookRows)) {
        throw new RuntimeException('The EOI spreadsheet exports do not include the full report registers.');
    }

    // Dompdf retains a sizable in-memory document after rendering. Release the
    // direct-render assertion before exercising the real controller download.
    unset($pdfDocument, $pdf, $pdfHtml, $pdfData, $csvRows, $workbookRows, $report, $html);
    gc_collect_cycles();

    $downloadResponse = $exportController
        ->eoiProcurementPdf($procurement, $qualificationService);
    $cacheControl = (string) $downloadResponse->headers->get('cache-control');
    $contentDisposition = (string) $downloadResponse->headers->get('content-disposition');

    if (! str_starts_with((string) $downloadResponse->getContent(), '%PDF')
        || ! str_contains($cacheControl, 'no-store')
        || $downloadResponse->headers->get('x-eoi-pdf-layout') !== 'detailed-v3'
        || preg_match('/eoi-qualification-.+-\d{8}-\d{6}\.pdf/i', $contentDisposition) !== 1) {
        throw new RuntimeException('The EOI PDF response is not a fresh, versioned, non-cacheable detailed download.');
    }

    unset($downloadResponse);
    gc_collect_cycles();

    $excelResponse = $exportController->eoiProcurementExcel($procurement, $qualificationService);
    $excelContentType = (string) $excelResponse->headers->get('content-type');
    $excelCacheControl = (string) $excelResponse->headers->get('cache-control');

    if (! str_contains($excelContentType, 'spreadsheetml.sheet')
        || ! str_contains($excelCacheControl, 'no-store')) {
        throw new RuntimeException('The EOI Excel export is not a fresh workbook download.');
    }

    $csvResponse = $exportController->eoiProcurementCsv($procurement, $qualificationService);
    ob_start();
    $csvResponse->sendContent();
    $csvContent = (string) ob_get_clean();

    if (! str_contains((string) $csvResponse->headers->get('cache-control'), 'no-store')
        || ! str_contains($csvContent, 'Shortlist progression')
        || ! str_contains($csvContent, 'Current workflow decision')
        || ! str_contains($csvContent, 'Applicant outcome')) {
        throw new RuntimeException('The EOI CSV export is missing fresh comprehensive report data.');
    }

    echo "EOI_QUALIFICATION_REPORT_SMOKE_OK\n";
} finally {
    DB::rollBack();
}
