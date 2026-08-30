<?php

use App\Http\Controllers\EvaluationSubmissionController;
use App\Models\EoiTechnicalProposalCandidate;
use App\Models\EoiTechnicalProposalRound;
use App\Models\Evaluation;
use App\Models\EvaluationAssignment;
use App\Models\User;
use App\Services\EndowmentFundTechnicalProposalDocumentRehydrator;
use App\Support\EndowmentFundTechnicalProposalDocumentManifest;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

$round = EoiTechnicalProposalRound::query()
    ->where('title', EndowmentFundTechnicalProposalDocumentManifest::ROUND_TITLE)
    ->whereHas('procurement', fn ($query) => $query->where(
        'reference_no',
        EndowmentFundTechnicalProposalDocumentManifest::PROCUREMENT_REFERENCE
    ))
    ->with('procurement')
    ->sole();
$candidate = $round->candidates()
    ->with(['applicant.values', 'latestSubmission.documents'])
    ->get()
    ->first(fn (EoiTechnicalProposalCandidate $candidate): bool => $candidate->applicant?->display_name === 'BwB');

if (! $candidate || ! $candidate->latestSubmission) {
    throw new RuntimeException('The seeded BwB technical-proposal fixture is unavailable.');
}

$document = $candidate->latestSubmission->documents
    ->firstWhere('original_filename', 'Power of Attorney_compressed.pdf');

if (! $document) {
    throw new RuntimeException('The seeded BwB proposal document fixture is unavailable.');
}

Storage::fake('local');
$disk = Storage::disk('local');
$rehydrator = $app->make(EndowmentFundTechnicalProposalDocumentRehydrator::class);
$missing = $rehydrator->inspect($document);

if (! $missing['recognized']
    || $missing['source_status'] !== EndowmentFundTechnicalProposalDocumentRehydrator::SOURCE_HEALTHY
    || $missing['storage_status'] !== EndowmentFundTechnicalProposalDocumentRehydrator::STORAGE_MISSING) {
    throw new RuntimeException('The recognized seeded document was not audited as recoverable and missing.');
}

$auditExit = Artisan::call('eoi:endowment-proposals:audit');

if ($auditExit !== 1 || $disk->allFiles() !== []) {
    throw new RuntimeException('The default audit unexpectedly changed private storage or hid missing copies.');
}

$unknown = clone $document;
$unknown->forceFill(['original_filename' => 'unregistered-proposal.pdf']);

if ($rehydrator->recoverMissing($unknown) || $disk->allFiles() !== []) {
    throw new RuntimeException('The recovery service accepted a document outside its immutable manifest.');
}

$repairExit = Artisan::call('eoi:endowment-proposals:audit', ['--repair' => true]);

if ($repairExit !== 0) {
    throw new RuntimeException('Explicit repair did not restore every checksum-pinned private copy: '.Artisan::output());
}

foreach (EndowmentFundTechnicalProposalDocumentManifest::all() as $manifestDocument) {
    $storedDocument = $round->candidates
        ->first(fn (EoiTechnicalProposalCandidate $item): bool => $item->applicant?->display_name === $manifestDocument['applicant_name'])
        ?->latestSubmission?->documents
        ->firstWhere('original_filename', $manifestDocument['filename']);

    if (! $storedDocument || ! $disk->exists($storedDocument->file_path)) {
        throw new RuntimeException('Explicit repair omitted '.$manifestDocument['filename'].'.');
    }

    $stream = $disk->readStream($storedDocument->file_path);
    $hash = hash_init('sha256');
    hash_update_stream($hash, $stream);
    fclose($stream);

    if (! hash_equals($manifestDocument['sha256'], hash_final($hash))) {
        throw new RuntimeException('Explicit repair stored unexpected bytes for '.$manifestDocument['filename'].'.');
    }
}

$kpmgCandidate = $round->candidates
    ->first(fn (EoiTechnicalProposalCandidate $item): bool => $item->applicant?->display_name === 'KPMG');
$kpmgDocument = $kpmgCandidate?->latestSubmission?->documents
    ->firstWhere('original_filename', 'Auc (KPMG)_compressed.pdf');

if (! $kpmgDocument) {
    throw new RuntimeException('The seeded KPMG proposal document fixture is unavailable.');
}

$disk->put($kpmgDocument->file_path, '%PDF-intentionally-corrupt');

if ($rehydrator->recoverMissing($kpmgDocument)
    || $disk->get($kpmgDocument->file_path) !== '%PDF-intentionally-corrupt') {
    throw new RuntimeException('An evaluator GET replaced a present integrity-mismatched document.');
}

if (Artisan::call('eoi:endowment-proposals:audit', ['--repair' => true]) !== 0) {
    throw new RuntimeException('Explicit repair did not replace a manifest document with invalid stored bytes.');
}

// Remove one verified copy and prove the authorized evaluator download action
// invokes the same narrow recovery path before streaming the real bytes.
$disk->delete($document->file_path);
$evaluator = User::query()
    ->where(function ($query): void {
        $query->whereNull('user_type')->orWhereNotIn('user_type', ['vendor', 'think_tank']);
    })
    ->firstOrFail();
Auth::setUser($evaluator);

$evaluation = new Evaluation;
$evaluation->forceFill(['id' => (string) Str::uuid()]);
$assignment = new EvaluationAssignment;
$assignment->forceFill([
    'id' => (string) Str::uuid(),
    'evaluation_id' => $evaluation->getKey(),
    'procurement_id' => $round->procurement_id,
    'form_submission_id' => $candidate->form_submission_id,
    'workflow_stage' => EvaluationAssignment::STAGE_TECHNICAL_PROPOSAL,
    'technical_proposal_round_id' => $round->getKey(),
    'user_id' => $evaluator->getKey(),
]);
$assignment->setRelation('evaluation', $evaluation);
$assignment->setRelation('procurement', $round->procurement);
$assignment->setRelation('technicalProposalRound', $round);

$response = $app->make(EvaluationSubmissionController::class)->proposalDocument(
    $assignment,
    $candidate,
    $candidate->latestSubmission,
    $document
);

if (! $disk->exists($document->file_path)) {
    throw new RuntimeException('The evaluator download did not recover its missing recognized private copy.');
}

ob_start();
$response->sendContent();
$streamedBytes = ob_get_clean();
$manifestDocument = EndowmentFundTechnicalProposalDocumentManifest::find(
    'BwB',
    'Power of Attorney_compressed.pdf'
);

if (! is_string($streamedBytes)
    || strlen($streamedBytes) !== $manifestDocument['file_size']
    || ! hash_equals($manifestDocument['sha256'], hash('sha256', $streamedBytes))) {
    throw new RuntimeException('The evaluator response did not stream the exact approved proposal bytes.');
}

echo "ENDOWMENT_FUND_PROPOSAL_DOCUMENT_REHYDRATION_SMOKE_OK\n";
