<?php

use App\Http\Controllers\EvaluationAssignmentController;
use App\Http\Controllers\EvaluationSubmissionController;
use App\Models\EoiTechnicalProposalCandidate;
use App\Models\EoiTechnicalProposalDocument;
use App\Models\EoiTechnicalProposalRound;
use App\Models\EoiTechnicalProposalSubmission;
use App\Models\Evaluation;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationCriteria;
use App\Models\EvaluationSection;
use App\Models\EvaluationSubmission;
use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\Sector;
use App\Models\User;
use App\Services\EoiQualificationService;
use App\Services\EvaluationAssignmentTargetResolver;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

$evaluator = User::query()
    ->where('user_type', 'admin')
    ->where(function ($query): void {
        $query->whereNull('user_type')
            ->orWhereNotIn('user_type', ['vendor', 'think_tank']);
    })
    ->firstOrFail();

Auth::setUser($evaluator);
DB::beginTransaction();
$storedProposalPath = null;

try {
    $portfolioId = Sector::query()->value('id');
    if (! $portfolioId) {
        throw new RuntimeException('A portfolio fixture is required for the proposal assignment smoke test.');
    }

    $procurement = Procurement::create([
        'title' => 'Proposal assignment smoke procurement',
        'reference_no' => 'PROP-SMOKE-'.strtoupper(substr((string) str()->uuid(), 0, 8)),
        'status' => 'closed',
        'created_by' => $evaluator->getKey(),
    ]);
    $evaluation = Evaluation::create([
        'name' => 'Reusable proposal evaluation smoke form',
        'description' => 'Transactional proposal assignment fixture.',
        'status' => 'active',
        'type' => Evaluation::TYPE_SERVICES,
        'portfolio_id' => $portfolioId,
        'is_portfolio_custom' => true,
        'created_by' => $evaluator->getKey(),
    ]);
    $section = EvaluationSection::create([
        'evaluation_id' => $evaluation->getKey(),
        'name' => 'Technical quality',
        'sort_order' => 1,
    ]);
    EvaluationCriteria::create([
        'evaluation_section_id' => $section->getKey(),
        'name' => 'Proposal quality',
        'max_score' => 100,
    ]);
    $applicant = FormSubmission::create([
        'procurement_id' => $procurement->getKey(),
        'submitted_by' => $evaluator->getKey(),
        'status' => FormSubmission::STATUS_TECHNICAL_EVALUATION,
        'submitted_at' => now()->subDay(),
    ]);
    $round = EoiTechnicalProposalRound::create([
        'procurement_id' => $procurement->getKey(),
        'round_number' => 1,
        'title' => 'Technical proposal smoke round',
        'timezone' => config('app.timezone', 'UTC'),
        'late_policy' => EoiTechnicalProposalRound::LATE_REJECT,
        'portal_requirement' => EoiTechnicalProposalRound::REQUIREMENT_REQUIRED,
        'email_requirement' => EoiTechnicalProposalRound::REQUIREMENT_ALLOWED,
        'physical_requirement' => EoiTechnicalProposalRound::REQUIREMENT_NOT_ALLOWED,
        'status' => EoiTechnicalProposalRound::STATUS_PUBLISHED,
        'created_by' => $evaluator->getKey(),
        'published_by' => $evaluator->getKey(),
        'published_at' => now()->subHour(),
    ]);
    $candidate = EoiTechnicalProposalCandidate::create([
        'round_id' => $round->getKey(),
        'form_submission_id' => $applicant->getKey(),
        'user_id' => $evaluator->getKey(),
        'eoi_outcome_code' => EoiQualificationService::OUTCOME_FULLY_QUALIFIED,
        'eoi_outcome_label' => 'Fully Qualified',
        'workflow_decision' => 'Technical Proposal',
        'status' => EoiTechnicalProposalCandidate::STATUS_QUALIFIED,
        'invited_at' => now()->subDay(),
        'first_submitted_at' => now()->subHours(2),
        'last_submitted_at' => now()->subHours(2),
        'reviewed_by' => $evaluator->getKey(),
        'reviewed_at' => now()->subHour(),
    ]);
    $proposal = EoiTechnicalProposalSubmission::create([
        'candidate_id' => $candidate->getKey(),
        'revision_number' => 1,
        'source' => EoiTechnicalProposalSubmission::SOURCE_VENDOR_PORTAL,
        'received_via' => EoiTechnicalProposalSubmission::CHANNEL_PORTAL,
        'received_at' => now()->subHours(2),
        'uploaded_at' => now()->subHours(2),
        'is_late' => false,
        'minutes_late' => 0,
        'submitted_by' => $evaluator->getKey(),
    ]);
    $storedProposalPath = 'eoi-technical-proposals/'.$round->getKey()
        .'/candidates/'.$candidate->getKey().'/revisions/1/proposal.pdf';
    Storage::disk('local')->put($storedProposalPath, '%PDF-1.4 proposal assignment smoke');
    $document = EoiTechnicalProposalDocument::create([
        'proposal_submission_id' => $proposal->getKey(),
        'document_label' => 'Technical proposal',
        'file_path' => $storedProposalPath,
        'original_filename' => 'proposal.pdf',
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'sha256' => hash('sha256', 'proposal-assignment-smoke'),
        'uploaded_by' => $evaluator->getKey(),
    ]);
    $request = Request::create('/evaluation-assignments/store', 'POST', [
        'evaluation_id' => $evaluation->getKey(),
        'procurement_id' => $procurement->getKey(),
        'technical_proposal_round_id' => $round->getKey(),
        'user_id' => $evaluator->getKey(),
        'assignment_type' => 'technical_proposal_procurement',
    ], server: ['HTTP_REFERER' => url('/evaluation-assignments')]);
    $request->setUserResolver(fn () => $evaluator);
    $session = $app->make('session')->driver();
    $session->start();
    $request->setLaravelSession($session);
    $app->instance('request', $request);
    Mail::fake();

    $app->make(EvaluationAssignmentController::class)->store($request);
    $assignment = EvaluationAssignment::query()
        ->where('evaluation_id', $evaluation->getKey())
        ->where('procurement_id', $procurement->getKey())
        ->where('workflow_stage', EvaluationAssignment::STAGE_TECHNICAL_PROPOSAL)
        ->where('technical_proposal_round_id', $round->getKey())
        ->firstOrFail();

    $resolver = $app->make(EvaluationAssignmentTargetResolver::class);
    $targets = $resolver->targetsForAssignment($assignment->fresh());
    if ($targets->pluck('id')->all() !== [$applicant->getKey()]) {
        throw new RuntimeException('The proposal assignment did not resolve only its qualified exact-round target.');
    }

    $app->make(EvaluationSubmissionController::class)->start(
        $assignment->fresh(),
        $applicant->fresh()
    );
    $evaluationRecord = EvaluationSubmission::query()
        ->where('evaluation_assignment_id', $assignment->getKey())
        ->where('form_submission_id', $applicant->getKey())
        ->firstOrFail();

    if ((string) $evaluationRecord->technical_proposal_candidate_id !== (string) $candidate->getKey()
        || (string) $evaluationRecord->technical_proposal_submission_id !== (string) $proposal->getKey()) {
        throw new RuntimeException('The evaluator record did not snapshot the accepted proposal candidate and revision.');
    }

    $downloadResponse = $app->make(EvaluationSubmissionController::class)->proposalDocument(
        $assignment->fresh(),
        $candidate->fresh(),
        $proposal->fresh(),
        $document->fresh()
    );
    if (! str_contains((string) $downloadResponse->headers->get('content-disposition'), 'proposal.pdf')) {
        throw new RuntimeException('The assigned evaluator could not resolve the private proposal document.');
    }

    $candidate->forceFill(['status' => EoiTechnicalProposalCandidate::STATUS_DISQUALIFIED])->save();
    $blocked = false;
    try {
        $app->make(EvaluationSubmissionController::class)->start(
            $assignment->fresh(),
            $applicant->fresh()
        );
    } catch (HttpException $exception) {
        $blocked = $exception->getStatusCode() === 409;
    }

    if (! $blocked) {
        throw new RuntimeException('A disqualified proposal remained accessible through the evaluator start action.');
    }

    $app->make(EvaluationAssignmentController::class)->destroy($assignment->fresh());
    $specificRequest = Request::create('/evaluation-assignments/store', 'POST', [
        'evaluation_id' => $evaluation->getKey(),
        'procurement_id' => $procurement->getKey(),
        'technical_proposal_round_id' => $round->getKey(),
        'user_id' => $evaluator->getKey(),
        'assignment_type' => 'technical_proposal_submission',
        'submission_id' => $applicant->getKey(),
    ], server: ['HTTP_REFERER' => url('/evaluation-assignments')]);
    $specificRequest->setUserResolver(fn () => $evaluator);
    $specificRequest->setLaravelSession($session);
    $app->instance('request', $specificRequest);
    $app->make(EvaluationAssignmentController::class)->store($specificRequest);

    if (EvaluationAssignment::query()
        ->where('evaluation_id', $evaluation->getKey())
        ->where('procurement_id', $procurement->getKey())
        ->exists()) {
        throw new RuntimeException('A crafted assignment request accepted a disqualified proposal candidate.');
    }

    if (! $document->exists) {
        throw new RuntimeException('The accepted proposal document fixture was not persisted.');
    }

    echo "EVALUATION_ASSIGNMENT_PROPOSAL_ROUND_SMOKE_OK\n";
} finally {
    DB::rollBack();
    if ($storedProposalPath) {
        Storage::disk('local')->delete($storedProposalPath);
    }
}
