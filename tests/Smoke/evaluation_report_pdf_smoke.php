<?php

use App\Http\Controllers\EvaluationReportController;
use App\Mail\EvaluationCompleted;
use App\Models\EvaluationSubmission;
use App\Models\User;
use App\Support\PdfBranding;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$compiledPath = sys_get_temp_dir().'/attp-evaluation-report-smoke-'.getmypid();

if (! is_dir($compiledPath) && ! mkdir($compiledPath, 0777, true) && ! is_dir($compiledPath)) {
    throw new RuntimeException('Unable to create an isolated Blade cache for the report smoke test.');
}

putenv('VIEW_COMPILED_PATH='.$compiledPath);
$_ENV['VIEW_COMPILED_PATH'] = $compiledPath;
$_SERVER['VIEW_COMPILED_PATH'] = $compiledPath;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

$administrator = User::query()
    ->where('user_type', 'admin')
    ->firstOrFail();
$request = Request::create('/reports/evaluations', 'GET');
$request->setUserResolver(fn () => $administrator);
$app->instance('request', $request);
Auth::setUser($administrator);

$submission = EvaluationSubmission::query()
    ->whereNotNull('submitted_at')
    ->with(['applicant.submitter', 'procurement'])
    ->get()
    ->first(fn ($candidate) => filled($candidate->applicant?->submitter?->email));

if (! $submission) {
    throw new RuntimeException('No completed evaluation with applicant identity data was found.');
}
$controller = $app->make(EvaluationReportController::class);

$assertPdf = function ($response, string $label): string {
    $content = $response->getContent();

    if ($response->getStatusCode() !== 200) {
        throw new RuntimeException($label.' did not return HTTP 200.');
    }

    if (! str_starts_with((string) $response->headers->get('content-type'), 'application/pdf')) {
        throw new RuntimeException($label.' did not return a PDF content type.');
    }

    if (! str_starts_with($content, '%PDF')) {
        throw new RuntimeException($label.' did not contain a valid PDF signature.');
    }

    return (string) $response->headers->get('content-disposition');
};

$normalDisposition = $assertPdf(
    $controller->submissionPdf($submission),
    'Standard evaluation submission PDF'
);
$anonymisedDisposition = $assertPdf(
    $controller->submissionAnonymisedPdf($submission),
    'Anonymised evaluation submission PDF'
);
$assertPdf(
    $controller->procurementPdf($submission->procurement),
    'Procurement evaluation PDF'
);
$assertPdf(
    $controller->consolidatedPdf(),
    'Consolidated evaluation PDF'
);

if (! str_contains($normalDisposition, 'evaluation-submission-')) {
    throw new RuntimeException('The standard PDF filename is invalid.');
}

$applicantSlug = Str::slug($submission->applicant?->display_name ?: '');
$submissionCodeSlug = Str::slug($submission->applicant?->procurement_submission_code ?: '');

foreach (array_filter([$applicantSlug, $submissionCodeSlug]) as $identifier) {
    if (str_contains(Str::lower($anonymisedDisposition), Str::lower($identifier))) {
        throw new RuntimeException('The anonymised PDF filename exposes an applicant identifier.');
    }
}

$submission->load([
    'procurement',
    'applicant.submitter',
    'evaluation.sections.criteria',
    'criteriaScores.criteria',
    'sectionScores.section',
    'evaluator',
]);
$overallMax = $submission->evaluation?->usesNumericScoring()
    ? (float) $submission->evaluation->sections->sum(
        fn ($section) => (float) $section->criteria->sum('max_score')
    )
    : null;
$anonymisedHtml = view('reports.evaluations.pdf.submission', array_merge([
    'submission' => $submission,
    'overallMax' => $overallMax,
    'anonymised' => true,
], PdfBranding::viewData()))->render();

$privateIdentifiers = [
    $submission->applicant?->display_name,
    $submission->applicant?->submitter?->email,
    $submission->applicant?->procurement_submission_code,
];

foreach (array_filter($privateIdentifiers, fn ($value) => is_string($value) && strlen($value) > 2) as $identifier) {
    if (str_contains($anonymisedHtml, e($identifier))) {
        throw new RuntimeException('The anonymised PDF body exposes an applicant identifier.');
    }
}

if (! str_contains($anonymisedHtml, 'Applicant XXX')
    || ! str_contains($anonymisedHtml, 'Redacted')
    || ! str_contains($anonymisedHtml, 'ANONYMISED')) {
    throw new RuntimeException('The anonymised PDF body is missing its safe identity placeholders.');
}

$draft = EvaluationSubmission::query()->whereNull('submitted_at')->first()
    ?? (new EvaluationSubmission)->forceFill(['submitted_at' => null]);

try {
    $controller->submission($draft);
    throw new RuntimeException('An unfinished evaluation was exposed through the report endpoint.');
} catch (NotFoundHttpException) {
    // Expected: only completed evaluations may be reported.
}

$mailable = (new EvaluationCompleted($submission))->build();

if (count($mailable->rawAttachments) !== 1) {
    throw new RuntimeException('The completion email did not build its PDF attachment.');
}

echo "EVALUATION_REPORT_PDF_SMOKE_OK\n";
