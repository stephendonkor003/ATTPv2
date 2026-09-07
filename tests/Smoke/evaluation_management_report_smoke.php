<?php

use App\Http\Controllers\EvaluationReportController;
use App\Models\EoiTechnicalProposalRound;
use App\Models\Evaluation;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationCriteria;
use App\Models\EvaluationCriteriaScore;
use App\Models\EvaluationSection;
use App\Models\EvaluationSectionScore;
use App\Models\EvaluationSubmission;
use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\User;
use App\Services\EvaluationAssignmentTargetResolver;
use App\Services\EvaluationManagementReportService;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Eloquent\Collection as Models;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

putenv('SESSION_DRIVER=array');
$_ENV['SESSION_DRIVER'] = $_SERVER['SESSION_DRIVER'] = 'array';
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
Mail::fake();
Bus::fake();
$service = app(EvaluationManagementReportService::class);
$procurement = Procurement::findOrFail('01a0047d-3294-7100-b8e1-37187541feba');
$scoredProcurement = Procurement::findOrFail('3ed5b12a-8895-4cd3-a2fa-4e74bb147141');
$before = [EvaluationSubmission::count(), EvaluationCriteriaScore::count(), EvaluationAssignment::count()];

// Pure model fixtures exercise reporting rules without inserting scores or
// changing evaluator assignments in the application's database.
$originalResolver = app(EvaluationAssignmentTargetResolver::class);
$targets = [];
$targetFailures = [];
// Closures used by Mockery must observe targets populated after construction.
$resolver = Mockery::mock(EvaluationAssignmentTargetResolver::class);
$resolver->shouldReceive('targetsForAssignment')->andReturnUsing(function ($assignment) use (&$targets, &$targetFailures) {
    if (isset($targetFailures[$assignment->id])) {
        throw $targetFailures[$assignment->id];
    }

    return $targets[$assignment->id] ?? new Models;
});
$app->instance(EvaluationAssignmentTargetResolver::class, $resolver);

try {
    $applicants = new Models(array_map(fn ($name) => managementSmokeApplicant($name), ['Alpha', 'Bravo', 'Charlie', 'Delta zero', 'Echo incomplete', 'Foxtrot invalid']));
    $evaluatorA = (new User)->forceFill(['id' => (string) Str::uuid(), 'name' => 'Evaluator A', 'email' => 'a@example.test']);
    $evaluatorB = (new User)->forceFill(['id' => (string) Str::uuid(), 'name' => 'Evaluator B', 'email' => 'b@example.test']);
    $evaluation = managementSmokeEvaluation('Technical method and experience');
    $assignmentA = managementSmokeAssignment($evaluation, $evaluatorA, $procurement);
    $assignmentB = managementSmokeAssignment($evaluation, $evaluatorB, $procurement);
    $targets[$assignmentA->id] = $targets[$assignmentB->id] = $applicants;
    $pairs = [
        [[32, 48], [36, 44]], // Equal overall mean 80, with raw section mean 46/60.
        [[36, 54], [28, 42]], // Equal overall mean 80; ties share rank 1.
        [[24, 36], [24, 36]], // Rank 3, not 2 after a tied first place.
        [[0, 0], [0, 0]],     // Recorded zero is valid and remains rankable.
        [[40, 59], null],     // Higher partial score must remain unranked.
        [[24, null], [24, 36]], // Missing criterion invalidates overall ranking.
    ];
    $submissions = new Models;
    foreach ($pairs as $index => $pair) {
        foreach ([$assignmentA, $assignmentB] as $column => $assignment) {
            if ($pair[$column] !== null) {
                $submissions->push(managementSmokeSubmission($assignment, $applicants[$index], $pair[$column]));
            }
        }
    }
    $older = managementSmokeSubmission($assignmentA, $applicants[0], [8, 12]);
    $older->submitted_at = '2026-09-01 13:00:00';
    $submissions->push($older);
    $report = $service->build($procurement, $submissions, collect([$assignmentA, $assignmentB]));
    $rows = collect($report['groups'][0]['rankings'])->keyBy('name');
    managementSmokeAssert($report['overview']['submitted_reports'] === 11 && count($report['audit']) === 11, 'Superseded evaluator revision was counted twice.');
    managementSmokeAssert($rows['Alpha']['score'] === 80.0 && $rows['Alpha']['rank'] === 1 && $rows['Bravo']['rank'] === 1
        && $rows['Charlie']['rank'] === 3, 'Tied normalized means do not retain competition ranking.');
    managementSmokeAssert($rows['Delta zero']['score'] === 0.0 && $rows['Delta zero']['rank'] === 4, 'A recorded zero was treated as missing.');
    managementSmokeAssert($rows['Echo incomplete']['rank'] === null && $rows['Echo incomplete']['completed'] === 1 && $rows['Echo incomplete']['expected'] === 2,
        'A partial panel obtained a merit rank.');
    managementSmokeAssert($rows['Foxtrot invalid']['rank'] === null && $rows['Foxtrot invalid']['score'] === null
        && $rows['Foxtrot invalid']['_complete'] === false, 'A missing criterion was converted to zero, ranked or counted as a complete panel.');
    $completionChart = collect($report['groups'][0]['charts'])->first(fn ($chart) => ($chart['data']['kind'] ?? null) === 'pie');
    $completionCounts = collect($completionChart['data']['slices'])->pluck('value', 'name')->all();
    managementSmokeAssert($completionCounts === ['Panel complete' => 4.0, 'Panel incomplete' => 2.0],
        'Panel completion chart counts a finalized record with invalid numeric criteria as complete.');
    $parent = collect($report['sections'])->firstWhere('title', 'Technical assessment');
    $child = collect($report['sections'])->firstWhere('title', 'Relevant experience');
    $parentAlpha = collect($parent['rankings'])->firstWhere('name', 'Alpha');
    $childAlpha = collect($child['rankings'])->firstWhere('name', 'Alpha');
    managementSmokeAssert($parent['max_score'] === 100.0 && $parentAlpha['score'] === 80.0 && $child['max_score'] === 60.0
        && $childAlpha['score'] === 46.0 && $childAlpha['percentage'] === 76.67, 'Section analytics duplicate descendant criteria or confuse raw scores and percentages.');
    managementSmokeAssert($report['overview']['elapsed'] === '1d 3h 0m' && $report['overview']['active_time'] === 'Not recorded'
        && str_contains($report['overview']['timing_note'], 'calendar') && collect($report['evaluators'])->every(fn ($row) => $row['active_time'] === 'Not recorded'),
        'Calendar interval is incorrectly represented as evaluator active working time.');
    managementSmokeAssert(collect($report['insights'])->contains(fn ($row) => $row['title'] === 'Score validation required'), 'Missing criterion record is absent from management warnings.');
    echo "MANAGEMENT_REVISIONS_TIES_ZEROES_COMPLETENESS_SECTIONS_TIMING_OK\n";

    $contextAssignments = $contextSubmissions = [];
    foreach ([null, 1, 2] as $roundNumber) {
        $round = $roundNumber === null ? null : (new EoiTechnicalProposalRound)->forceFill(['id' => (string) Str::uuid(), 'round_number' => $roundNumber,
            'procurement_id' => $procurement->id, 'status' => EoiTechnicalProposalRound::STATUS_PUBLISHED]);
        foreach ([$evaluatorA, $evaluatorB] as $person) {
            $assignment = managementSmokeAssignment($evaluation, $person, $procurement, $round);
            $targets[$assignment->id] = new Models([$applicants[0]]);
            $contextAssignments[] = $assignment;
            $scores = match ($roundNumber) {
                1 => [34, 51], 2 => [30, 45], default => [12, 18]
            };
            $contextSubmissions[] = managementSmokeSubmission($assignment, $applicants[0], $scores);
        }
    }
    $contextReport = $service->build($procurement, collect($contextSubmissions), collect($contextAssignments));
    $contextMeans = collect($contextReport['groups'])->map(fn ($group) => $group['rankings'][0]['score'])->sort()->values()->all();
    managementSmokeAssert(count($contextReport['groups']) === 3 && $contextMeans === [30.0, 75.0, 85.0]
        && $contextReport['overview']['submitted_reports'] === 6, 'Application and technical proposal rounds were averaged or deduplicated together.');

    // Exercise the real live-target resolver with historical, now unavailable
    // rounds. Its validation gate must not prevent read-only historical reports.
    $app->instance(EvaluationAssignmentTargetResolver::class, $originalResolver);
    try {
        foreach ([EoiTechnicalProposalRound::STATUS_CANCELLED, EoiTechnicalProposalRound::STATUS_DRAFT] as $roundStatus) {
            $historicalRound = (new EoiTechnicalProposalRound)->forceFill(['id' => (string) Str::uuid(), 'round_number' => 3,
                'procurement_id' => $procurement->id, 'status' => $roundStatus]);
            $historicalAssignment = managementSmokeAssignment($evaluation, $evaluatorA, $procurement, $historicalRound);
            $historicalSubmission = managementSmokeSubmission($historicalAssignment, $applicants[0], [28, 42]);
            $historicalReport = $service->build($procurement, collect([$historicalSubmission]), collect([$historicalAssignment]));
            managementSmokeAssert($historicalReport['overview']['submitted_reports'] === 1
                && $historicalReport['overview']['evaluated_applicants'] === 1
                && count($historicalReport['details']) === 1 && count($historicalReport['audit']) === 1
                && count($historicalReport['groups'][0]['rankings']) === 1
                && $historicalReport['groups'][0]['rankings'][0]['score'] === 70.0,
                'An unavailable '.$roundStatus.' round prevented reporting or discarded historical finalized scores.');
            managementSmokeAssert(str_contains($historicalReport['groups'][0]['phase'], 'Proposal round 3 ('.Str::headline($roundStatus).')')
                && collect($historicalReport['insights'])->contains(fn ($row) => $row['title'] === 'Current eligibility unavailable'),
                'Historical '.$roundStatus.' round is not distinguished from current eligibility.');
        }
    } finally {
        $app->instance(EvaluationAssignmentTargetResolver::class, $resolver);
    }
    $targetFailures[$assignmentA->id] = new RuntimeException('Reporting storage unavailable');
    $systemFailurePreserved = false;
    try {
        $service->build($procurement, collect(), collect([$assignmentA]));
    } catch (RuntimeException $exception) {
        $systemFailurePreserved = $exception === $targetFailures[$assignmentA->id];
    } finally {
        unset($targetFailures[$assignmentA->id]);
    }
    managementSmokeAssert($systemFailurePreserved, 'Report fallback swallowed a system failure unrelated to eligibility validation.');
    echo "MANAGEMENT_HISTORICAL_UNAVAILABLE_ROUNDS_AND_SYSTEM_FAILURE_BOUNDARY_OK\n";
    foreach (['goods', 'eoi'] as $method) {
        $categorical = managementSmokeEvaluation('Categorical '.$method, $method);
        $assignment = managementSmokeAssignment($categorical, $evaluatorA, $procurement);
        $targets[$assignment->id] = new Models([$applicants[0]]);
        $submission = managementSmokeSubmission($assignment, $applicants[0], [null, null]);
        foreach ($submission->criteriaScores as $index => $score) {
            $score->decision = $method === 'goods' ? ($index === 0 ? 1 : 0) : ($index === 0 ? 2 : 1);
        }
        $categoricalReport = $service->build($procurement, collect([$submission]), collect([$assignment]));
        managementSmokeAssert($categoricalReport['groups'][0]['numeric'] === false
            && $categoricalReport['groups'][0]['rankings'][0]['_complete'] === true
            && collect($categoricalReport['groups'][0]['rankings'])->every(fn ($row) => $row['rank'] === null && $row['score'] === null)
            && collect($categoricalReport['sections'])->every(fn ($section) => collect($section['rankings'])->every(fn ($row) => $row['rank'] === null)),
            'Categorical '.$method.' decisions produced a fabricated numeric merit rank.');

        $negative = managementSmokeSubmission($assignment, $applicants[0], [null, null]);
        foreach ($negative->criteriaScores as $score) {
            $score->decision = 0;
        }
        $negativeReport = $service->build($procurement, collect([$negative]), collect([$assignment]));
        managementSmokeAssert($negativeReport['groups'][0]['rankings'][0]['_complete'] === true
            && $negativeReport['groups'][0]['rankings'][0]['status'] === ($method === 'goods' ? '2 No' : '2 Not qualified'),
            'Recorded zero-valued '.$method.' decisions were mistaken for missing responses.');

        foreach (['missing_row', 'null_decision', 'invalid_decision', 'all_missing'] as $case) {
            $incomplete = managementSmokeSubmission($assignment, $applicants[0], [null, null]);
            $incomplete->criteriaScores[0]->decision = $method === 'goods' ? 1 : 2;
            $incomplete->criteriaScores[1]->decision = match ($case) {
                'invalid_decision' => $method === 'goods' ? 2 : 3,
                default => null,
            };
            if ($case === 'missing_row') {
                $incomplete->setRelation('criteriaScores', new Models([$incomplete->criteriaScores[0]]));
            } elseif ($case === 'all_missing') {
                $incomplete->criteriaScores[0]->decision = null;
            }
            $incompleteReport = $service->build($procurement, collect([$incomplete]), collect([$assignment]));
            $row = $incompleteReport['groups'][0]['rankings'][0];
            managementSmokeAssert($row['_complete'] === false && $row['rank'] === null && $row['score'] === null
                && $row['completed'] === 1 && $row['expected'] === 1
                && $incompleteReport['overview']['submitted_reports'] === 1
                && $incompleteReport['overview']['evaluated_applicants'] === 1,
                'Categorical '.$method.' '.$case.' became a complete panel or lost its finalized report count.');
            managementSmokeAssert($incompleteReport['details'][0]['criteria'][1]['value'] === 'Not recorded',
                'Categorical '.$method.' '.$case.' invented a valid missing/invalid criterion decision.');
            if ($case === 'all_missing') {
                managementSmokeAssert(collect($incompleteReport['details'][0]['criteria'])->every(fn ($criterion) => $criterion['value'] === 'Not recorded'),
                    'Entirely missing '.$method.' decisions were shown as recorded negative outcomes.');
            } else {
                managementSmokeAssert(str_contains($row['status'], $method === 'goods' ? '1 Yes' : '1 Qualified'),
                    'An incomplete '.$method.' record lost its actually recorded decision count.');
            }
        }
    }
    echo "MANAGEMENT_CATEGORICAL_MISSING_DECISIONS_REQUIRE_COMPLETE_CRITERIA_OK\n";
    echo "MANAGEMENT_PHASE_ROUND_ISOLATION_AND_CATEGORICAL_OUTCOMES_OK\n";
} finally {
    $app->instance(EvaluationAssignmentTargetResolver::class, $originalResolver);
    Mockery::close();
}

$administrator = User::with('role')->get()->first(fn ($user) => $user->isAdmin() || $user->isSuperAdmin());
managementSmokeAssert($administrator !== null, 'An administrator is required for report route verification.');
Auth::setUser($administrator);
$request = Request::create('/reports/evaluations', 'GET');
$request->setUserResolver(fn () => $administrator);
$session = $app['session.store'];
$session->start();
$request->setLaravelSession($session);
$app->instance('request', $request);
$controller = app(EvaluationReportController::class);
$sourceSubmission = EvaluationSubmission::with(['applicant.submitter', 'applicant.values', 'evaluation.sections.criteria', 'procurement'])
    ->where('procurement_id', $scoredProcurement->id)->whereNotNull('submitted_at')->firstOrFail();
$feedbackSections = $sourceSubmission->evaluation->sections->take(2)->values();
managementSmokeAssert($feedbackSections->count() === 2, 'Two configured sections are required for the nonempty feedback privacy fixture.');
$sourceSubmission->setRelation('sectionScores', new Models($feedbackSections->map(fn ($section, $index) => (new EvaluationSectionScore)->forceFill([
    'id' => (string) Str::uuid(), 'evaluation_section_id' => $section->id,
    'strengths' => 'Confidential strengths fixture '.$index.' for '.$sourceSubmission->applicant->display_name,
    'weaknesses' => 'Confidential weaknesses fixture '.$index.' for '.$sourceSubmission->applicant->procurement_submission_code,
])->setRelation('section', $section))->all()));
$individual = $service->forSubmission($sourceSubmission);
$anonymous = $service->forSubmission($sourceSubmission, true);
$privateIdentifiers = array_filter([$sourceSubmission->applicant?->display_name, $sourceSubmission->applicant?->procurement_submission_code, $sourceSubmission->applicant?->submitter?->email]);
$inspectPrivate = function ($value) use (&$inspectPrivate, $privateIdentifiers): void {
    if (is_array($value)) {
        foreach ($value as $child) {
            $inspectPrivate($child);
        }

        return;
    }
    if (! is_string($value)) {
        return;
    }
    if (str_starts_with($value, 'data:image/svg+xml;base64,')) {
        $value = base64_decode(substr($value, strlen('data:image/svg+xml;base64,')));
    }
    foreach ($privateIdentifiers as $identifier) {
        managementSmokeAssert(! str_contains($value, $identifier)
            && ! str_contains($value, htmlspecialchars($identifier, ENT_QUOTES | ENT_XML1, 'UTF-8')), 'Anonymous report leaked applicant identity, including embedded SVG.');
    }
};
$inspectPrivate($anonymous);
managementSmokeAssert($anonymous['details'][0]['result'] === $individual['details'][0]['result']
    && $anonymous['details'][0]['comments'] === 'Withheld in anonymised report'
    && collect($anonymous['details'][0]['criteria'])->every(fn ($criterion) => $criterion['comment'] === 'Withheld in anonymised report')
    && collect($anonymous['groups'])->every(fn ($group) => collect($group['rankings'])->every(fn ($row) => $row['rank'] === null)),
    'Anonymous individual report changed numeric results, exposed comments, or invented a procurement-wide rank.');
managementSmokeAssert(count($individual['details'][0]['section_feedback']) === 2
    && collect($individual['details'][0]['section_feedback'])->every(fn ($feedback) => str_contains($feedback['strengths'], 'Confidential strengths fixture')
        && str_contains($feedback['weaknesses'], 'Confidential weaknesses fixture'))
    && count($anonymous['details'][0]['section_feedback']) === 2
    && collect($anonymous['details'][0]['section_feedback'])->every(fn ($feedback) => $feedback['strengths'] === 'Withheld in anonymised report'
        && $feedback['weaknesses'] === 'Withheld in anonymised report')
    && ! str_contains(json_encode($anonymous), 'Confidential strengths fixture')
    && ! str_contains(json_encode($anonymous), 'Confidential weaknesses fixture'),
    'Nonempty section strengths/weaknesses were lost in the named report or leaked from the anonymised report.');
echo "MANAGEMENT_INDIVIDUAL_ANONYMISED_PRIVACY_AND_SCORE_PRESERVATION_OK\n";
$build = new ReflectionMethod(EvaluationReportController::class, 'buildMethodProcurementReport');
$directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'evaluation-management-report';
if (! is_dir($directory)) {
    mkdir($directory, 0700, true);
}

foreach (['endowment' => $procurement, 'scored' => $scoredProcurement] as $name => $source) {
    $data = $build->invoke($controller, 'services', $source);
    $management = $data['management'];
    $oldRanks = collect($data['serviceRankingGroups'])->flatMap(fn ($group) => $group['rankings'])
        ->filter(fn ($row) => $row['rank'] !== null)->mapWithKeys(fn ($row) => [(string) $row['submission']->id => $row['rank']])->sortKeys()->all();
    $newRanks = collect($management['groups'])->flatMap(fn ($group) => $group['rankings'])
        ->filter(fn ($row) => $row['rank'] !== null)->mapWithKeys(fn ($row) => [$row['_id'] => $row['rank']])->sortKeys()->all();
    managementSmokeAssert($oldRanks === $newRanks, 'New '.$name.' report changed authoritative complete-panel rankings.');
    managementSmokeAssert($management['overview']['total_applicants'] === $source->submissions()->count(), 'Overview applicant total is not sourced from procurement applications.');
    if ($management['overview']['submitted_reports'] === 0) {
        managementSmokeAssert($newRanks === [] && $management['details'] === [] && $management['overview']['elapsed'] === 'Not recorded', 'Unscored procurement invented results or a completion duration.');
    }
    $html = $controller->methodProcurement('services', $source)->render();
    foreach (['summary', 'results', 'details', 'consistency', 'governance'] as $section) {
        managementSmokeAssert(str_contains($html, 'id="evaluation-'.$section.'-title"'), $name.' web report is missing section '.$section.'.');
    }
    managementSmokeAssert(str_contains($html, 'data:image/svg+xml;base64,') && ! str_contains($html, 'cdn.jsdelivr.net/npm/chart.js'), 'Web charts still depend on external Chart.js or are missing.');
    file_put_contents($directory.DIRECTORY_SEPARATOR.$name.'.html', $html);
    file_put_contents($directory.DIRECTORY_SEPARATOR.$name.'-overview.json', json_encode($management['overview'], JSON_PRETTY_PRINT));
    $pdfHtml = view('reports.evaluations.pdf.method-procurement', array_merge($data, \App\Support\PdfBranding::viewData()))->render();
    foreach (range(1, 5) as $section) {
        managementSmokeAssert(str_contains($pdfHtml, 'Section '.$section.' of 5'), $name.' PDF report is missing section '.$section.'.');
    }
    file_put_contents($directory.DIRECTORY_SEPARATOR.$name.'-pdf.html', $pdfHtml);
    if (getenv('EVALUATION_SMOKE_SKIP_PDF') !== '1') {
        $pdfResponse = $controller->methodProcurementPdf('services', $source);
        managementSmokeAssert($pdfResponse->getStatusCode() === 200 && str_starts_with($pdfResponse->getContent(), '%PDF-') && strlen($pdfResponse->getContent()) > 20000,
            $name.' five-section PDF route did not render real content.');
        file_put_contents($directory.DIRECTORY_SEPARATOR.$name.'.pdf', $pdfResponse->getContent());
    }
    echo strtoupper($name).(getenv('EVALUATION_SMOKE_SKIP_PDF') === '1' ? '_REAL_WEB_PDF_TEMPLATE_OK ' : '_REAL_WEB_PDF_OK ')
        .json_encode(['applicants' => $management['overview']['total_applicants'], 'evaluated' => $management['overview']['evaluated_applicants'], 'reports' => $management['overview']['submitted_reports'], 'evaluators' => $management['overview']['evaluator_count'], 'ranked' => count($newRanks)]).PHP_EOL;
}
managementSmokeAssert($before === [EvaluationSubmission::count(), EvaluationCriteriaScore::count(), EvaluationAssignment::count()], 'Read-only report smoke changed evaluation data.');
Mail::assertNothingSent();
Bus::assertNothingDispatched();
echo "EVALUATION_MANAGEMENT_REPORT_SMOKE_OK (in-memory behavioral fixtures and read-only real reports)\n";
echo $directory.PHP_EOL;

function managementSmokeApplicant(string $name): FormSubmission
{
    $user = (new User)->forceFill(['id' => (string) Str::uuid(), 'name' => $name]);

    return (new FormSubmission)->forceFill(['id' => (string) Str::uuid(), 'procurement_submission_code' => 'APP-'.strtoupper(substr($name, 0, 3))])
        ->setRelation('submitter', $user)->setRelation('values', new Models);
}

function managementSmokeEvaluation(string $name, string $type = 'services'): Evaluation
{
    $evaluation = (new Evaluation)->forceFill(['id' => (string) Str::uuid(), 'name' => $name, 'type' => $type, 'evaluation_phase' => 'technical']);
    $parent = (new EvaluationSection)->forceFill(['id' => (string) Str::uuid(), 'evaluation_id' => $evaluation->id, 'name' => 'Technical assessment', 'sort_order' => 1, 'parent_section_id' => null]);
    $child = (new EvaluationSection)->forceFill(['id' => (string) Str::uuid(), 'evaluation_id' => $evaluation->id, 'name' => 'Relevant experience', 'sort_order' => 2, 'parent_section_id' => $parent->id]);
    $method = (new EvaluationCriteria)->forceFill(['id' => (string) Str::uuid(), 'evaluation_section_id' => $parent->id, 'name' => 'Proposed method', 'max_score' => 40])->setRelation('section', $parent);
    $experience = (new EvaluationCriteria)->forceFill(['id' => (string) Str::uuid(), 'evaluation_section_id' => $child->id, 'name' => 'Relevant experience evidence', 'max_score' => 60])->setRelation('section', $child);
    $parent->setRelation('criteria', new Models([$method]));
    $child->setRelation('criteria', new Models([$experience]));

    return $evaluation->setRelation('sections', new Models([$parent, $child]));
}

function managementSmokeAssignment(Evaluation $evaluation, User $evaluator, Procurement $procurement, ?EoiTechnicalProposalRound $round = null): EvaluationAssignment
{
    return (new EvaluationAssignment)->forceFill(['id' => (string) Str::uuid(), 'evaluation_id' => $evaluation->id,
        'procurement_id' => $procurement->id, 'user_id' => $evaluator->id, 'assigned_at' => '2026-09-01 09:00:00',
        'workflow_stage' => $round ? 'technical_proposal' : 'application', 'technical_proposal_round_id' => $round?->id])
        ->setRelation('evaluation', $evaluation)->setRelation('evaluator', $evaluator)->setRelation('procurement', $procurement)->setRelation('technicalProposalRound', $round);
}

function managementSmokeSubmission(EvaluationAssignment $assignment, FormSubmission $applicant, array $values): EvaluationSubmission
{
    $submission = (new EvaluationSubmission)->forceFill(['id' => (string) Str::uuid(), 'evaluation_id' => $assignment->evaluation_id,
        'evaluation_assignment_id' => $assignment->id, 'evaluator_id' => $assignment->user_id, 'form_submission_id' => $applicant->id,
        'overall_score' => array_sum($values), 'submitted_at' => '2026-09-02 12:00:00', 'revision_number' => 1])
        ->setRelation('assignment', $assignment)->setRelation('evaluation', $assignment->evaluation)->setRelation('evaluator', $assignment->evaluator)
        ->setRelation('applicant', $applicant)->setRelation('technicalProposalCandidate', null);
    $criteria = $assignment->evaluation->sections->flatMap->criteria->values();
    $scores = new Models;
    foreach ($criteria as $index => $criterion) {
        $scores->push((new EvaluationCriteriaScore)->forceFill(['id' => (string) Str::uuid(), 'evaluation_criteria_id' => $criterion->id,
            'score' => $values[$index], 'comment' => 'Recorded fixture rationale'])->setRelation('criteria', $criterion));
    }

    return $submission->setRelation('criteriaScores', $scores);
}

function managementSmokeAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
