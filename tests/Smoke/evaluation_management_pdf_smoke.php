<?php

use App\Http\Controllers\EvaluationReportController;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationCriteriaScore;
use App\Models\EvaluationSectionScore;
use App\Models\EvaluationSubmission;
use App\Models\Procurement;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';

$scope = null;
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--scope=')) {
        $scope = substr($argument, strlen('--scope='));
    }
}

if ($scope === null) {
    // Independent processes release every imported PDF/font between exports.
    set_time_limit(500);
    foreach (['scored', 'consolidated', 'layout'] as $childScope) {
        $process = new Process([PHP_BINARY, __FILE__, '--scope='.$childScope], dirname(__DIR__, 2));
        $process->setTimeout(240);
        $process->run(static fn ($type, $output) => print $output);
        if (! $process->isSuccessful()) {
            exit(1);
        }
    }
    echo "EVALUATION_MANAGEMENT_FULL_PDFS_OK\n";
    exit(0);
}

if (! in_array($scope, ['scored', 'consolidated', 'layout'], true)) {
    fwrite(STDERR, "Use --scope=scored, --scope=consolidated or --scope=layout.\n");
    exit(1);
}

putenv('SESSION_DRIVER=array');
$_ENV['SESSION_DRIVER'] = $_SERVER['SESSION_DRIVER'] = 'array';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
Mail::fake();
Bus::fake();
Queue::fake();

function managementPdfAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$before = [EvaluationSubmission::count(), EvaluationCriteriaScore::count(), EvaluationSectionScore::count(), EvaluationAssignment::count()];
$started = microtime(true);
$directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'evaluation-management-report';
if (! is_dir($directory)) {
    mkdir($directory, 0700, true);
}

try {
    managementPdfAssert(class_exists(\setasign\Fpdi\Fpdi::class), 'Composer installation of the PDF merger is not ready.');
    if ($scope === 'layout') {
        managementPdfLayoutSmoke($directory);
        managementPdfAssert($before === [EvaluationSubmission::count(), EvaluationCriteriaScore::count(), EvaluationSectionScore::count(), EvaluationAssignment::count()], 'Layout smoke changed evaluation records.');
        Mail::assertNothingSent();
        Bus::assertNothingDispatched();
        Queue::assertNothingPushed();
        echo "EVALUATION_MANAGEMENT_PDF_BOOKMARKS_AND_FOOTER_OK\n";
        exit(0);
    }
    $administrator = User::with('role')->get()->first(fn ($user) => ! $user->is_disabled && ! $user->is_blacklisted
        && in_array($user->role?->name, ['System Admin', 'Super Admin'], true));
    managementPdfAssert($administrator !== null, 'An active administrator is required to verify authorized PDF routes.');
    Auth::setUser($administrator);
    $url = $scope === 'scored'
        ? '/reports/evaluations/method/services/procurement/3ed5b12a-8895-4cd3-a2fa-4e74bb147141/pdf'
        : '/reports/evaluations/consolidated/pdf';
    $request = Request::create($url, 'GET');
    $request->setUserResolver(fn () => $administrator);
    $session = $app['session.store'];
    $session->start();
    $request->setLaravelSession($session);
    $app->instance('request', $request);
    $controller = app(EvaluationReportController::class);
    $procurement = $scope === 'scored' ? Procurement::findOrFail('3ed5b12a-8895-4cd3-a2fa-4e74bb147141') : null;
    $view = $scope === 'scored' ? $controller->methodProcurement('services', $procurement) : $controller->consolidated();
    $management = $view->getData()['management'];
    $sourceIds = array_column($management['audit'], 'id');
    managementPdfAssert(count($sourceIds) > 12 && count($sourceIds) === count($management['details']), 'Real PDF fixture does not exercise complete batched evaluator records.');
    $narratives = [];
    foreach ($management['details'] as $index => $detail) {
        $narratives[] = ['record' => $sourceIds[$index], 'field' => 'overall comment', 'text' => (string) ($detail['comments'] ?? '')];
        foreach ($detail['criteria'] ?? [] as $criterionIndex => $criterion) {
            $narratives[] = ['record' => $sourceIds[$index], 'field' => 'criterion '.$criterionIndex, 'text' => (string) ($criterion['comment'] ?? '')];
        }
        foreach ($detail['section_feedback'] ?? [] as $feedbackIndex => $feedback) {
            foreach (['strengths', 'weaknesses'] as $field) {
                $narratives[] = ['record' => $sourceIds[$index], 'field' => 'section '.$feedbackIndex.' '.$field, 'text' => (string) ($feedback[$field] ?? '')];
            }
        }
    }
    $expected = ['source_ids' => $sourceIds, 'narratives' => $narratives, 'sections' => [
        'Summary overview', 'Evaluation results and rankings', 'Detailed evaluator and section scores',
        'Panel consistency and management insights', 'Governance, audit trail and next steps',
    ]];
    $expectationPath = $directory.DIRECTORY_SEPARATOR.$scope.'-full-expected.json';
    file_put_contents($expectationPath, json_encode($expected, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    echo strtoupper($scope).'_PDF_START records='.count($sourceIds).' narratives='.count($narratives)."\n";
    unset($view, $management, $expected, $narratives);
    gc_collect_cycles();
    $renderStarted = microtime(true);
    $response = $scope === 'scored' ? $controller->methodProcurementPdf('services', $procurement) : $controller->consolidatedPdf();
    managementPdfAssert($response->getStatusCode() === 200 && str_starts_with((string) $response->headers->get('Content-Type'), 'application/pdf'), 'PDF controller did not return a PDF download.');
    $bytes = $response->getContent();
    managementPdfAssert(str_starts_with($bytes, '%PDF-') && strlen($bytes) > 10000, 'Full PDF is empty or invalid.');
    $pdfPath = $directory.DIRECTORY_SEPARATOR.$scope.'-full.pdf';
    file_put_contents($pdfPath, $bytes);
    $renderSeconds = round(microtime(true) - $renderStarted, 2);
    unset($response, $bytes);
    gc_collect_cycles();

    $python = <<<'PYTHON'
import json, re, sys, unicodedata
import pymupdf

pdf_path, expected_path = sys.argv[1:3]
expected = json.load(open(expected_path, encoding='utf-8'))
document = pymupdf.open(pdf_path)

def normalise(value):
    value = unicodedata.normalize('NFKC', value).casefold()
    return ''.join(character for character in value if character.isalnum())

body_parts = []
blank_pages = []
for index, page in enumerate(document):
    full_text = page.get_text()
    footer = rf'Page\s+{index+1}\s+of\s+{len(document)}\b'
    assert re.search(footer, full_text), f'Missing global page number {index+1} of {len(document)}'
    # Ignore repeated branded headers/footers when joining a narrative across pages.
    body = page.get_text('text', clip=pymupdf.Rect(0, 80, page.rect.width, page.rect.height-35), sort=True)
    body_parts.append(body)
    if len(body.strip()) < 20:
        blank_pages.append(index+1)
assert not blank_pages, f'Unexpected blank content pages: {blank_pages}'
body = normalise('\n'.join(body_parts))
for title in expected['sections']:
    assert normalise(title) in body, f'Missing report section: {title}'
for record_id in expected['source_ids']:
    assert normalise(record_id) in body, f'Missing source audit record: {record_id}'
missing = []
for row in expected['narratives']:
    text = normalise(row['text'])
    if text and text not in body:
        missing.append({'record': row['record'], 'field': row['field'], 'characters': len(row['text'])})
assert not missing, f'Missing or interrupted complete narratives: {missing[:12]} (total {len(missing)})'
print(json.dumps({'pages': len(document), 'source_records': len(expected['source_ids']), 'narratives_verified': len(expected['narratives']), 'global_page_numbers': True, 'blank_pages': 0}))
PYTHON;
    $process = new Process(['python', '-c', $python, $pdfPath, $expectationPath], dirname(__DIR__, 2));
    $process->setTimeout(60);
    $process->mustRun();
    echo $process->getOutput();
    managementPdfAssert($before === [EvaluationSubmission::count(), EvaluationCriteriaScore::count(), EvaluationSectionScore::count(), EvaluationAssignment::count()], 'Read-only PDF export modified evaluation records.');
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
    Bus::assertNothingDispatched();
    Queue::assertNothingPushed();
    echo json_encode(['scope' => $scope, 'pdf_path' => $pdfPath, 'pdf_bytes' => filesize($pdfPath), 'render_seconds' => $renderSeconds,
        'wall_seconds' => round(microtime(true) - $started, 2), 'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1)], JSON_UNESCAPED_SLASHES)."\n";
    echo strtoupper($scope)."_COMPLETE_MANAGEMENT_PDF_OK\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n");
    exit(1);
}

function managementPdfLayoutSmoke(string $directory): void
{
    $details = $audit = [];
    for ($index = 1; $index <= 13; $index++) {
        $details[] = ['applicant' => 'Layout fixture applicant', 'code' => 'LAYOUT-1', 'evaluation' => 'Technical assessment',
            'phase' => 'Technical', 'evaluator' => 'Fixture evaluator '.$index, 'result' => '80 / 100', 'submitted_at' => '07 Sep 2026, 10:00',
            'comments' => 'Complete fixture comment '.$index,
            'section_feedback' => [['section' => 'Technical approach', 'strengths' => 'A clear approach.', 'weaknesses' => 'Clarify implementation timing.']],
            'criteria' => [['section' => 'Technical approach', 'criterion' => 'Methodology', 'value' => 80, 'max' => 100, 'comment' => 'Criterion rationale '.$index]]];
        $audit[] = ['applicant' => 'Layout fixture applicant', 'code' => 'LAYOUT-1', 'evaluation' => 'Technical assessment',
            'evaluator' => 'Fixture evaluator '.$index, 'submitted_at' => '07 Sep 2026, 10:00', 'revision' => 1, 'id' => 'layout-record-'.$index];
    }
    $management = ['generated_at' => '07 Sep 2026, 10:00 UTC', 'overview' => ['total_applicants' => 1, 'evaluated_applicants' => 1,
        'evaluator_count' => 13, 'submitted_reports' => 13, 'template_count' => 1, 'start' => '06 Sep 2026', 'end' => '07 Sep 2026',
        'elapsed' => '1 day', 'active_time' => 'Not recorded', 'timing_note' => 'Synthetic presentation fixture; no records were created.'],
        'evaluators' => [], 'overview_charts' => [], 'groups' => [], 'sections' => [], 'details' => $details, 'insights' => [],
        'consistency' => [], 'methodology' => ['Synthetic fixture verifies section navigation and page footers.'], 'actions' => [], 'audit' => $audit];
    $data = array_merge(['management' => $management,
        'procurement' => (object) ['title' => 'PDF bookmark and footer regression', 'reference_no' => 'LAYOUT-REGRESSION'],
        'methodDefinition' => ['label' => 'Services', 'mode' => 'Synthetic layout verification']], \App\Support\PdfBranding::viewData());
    $path = $directory.DIRECTORY_SEPARATOR.'bookmarks-footer-regression.pdf';
    $started = microtime(true);
    file_put_contents($path, \App\Support\EvaluationReportPdf::output($data));
    $python = <<<'PYTHON'
import json, re, sys
import pymupdf

document = pymupdf.open(sys.argv[1])
titles = ['Summary overview', 'Evaluation results and rankings', 'Detailed evaluator and section scores',
          'Panel consistency and management insights', 'Governance, audit trail and next steps']
bookmarks = document.get_toc()
assert len(bookmarks) == 5, f'Expected five native PDF bookmarks, got {bookmarks}'
assert [row[2] for row in bookmarks] == sorted(set(row[2] for row in bookmarks)), 'Bookmark destinations are not ordered distinct section starts'
for (level, title, page_number), expected in zip(bookmarks, titles):
    assert level == 1 and title == f'{titles.index(expected)+1} {expected}', f'Incorrect native bookmark: {title}'
    assert 1 <= page_number <= len(document), f'Invalid bookmark page: {page_number}'
    assert expected in document[page_number-1].get_text(), f'Bookmark points to the wrong section: {title}'
for index, page in enumerate(document):
    spans = [span for block in page.get_text('dict')['blocks'] if 'lines' in block for line in block['lines'] for span in line['spans']]
    timestamps = [pymupdf.Rect(span['bbox']) for span in spans if 'Generated' in span['text']]
    numbers = [pymupdf.Rect(span['bbox']) for span in spans if re.search(rf'Page\s+{index+1}\s+of\s+{len(document)}\b', span['text'])]
    assert timestamps and numbers, f'Missing timestamp or global page number on page {index+1}'
    assert all(not timestamp.intersects(number) for timestamp in timestamps for number in numbers), f'Timestamp overlaps page counter on page {index+1}'
print(json.dumps({'pages': len(document), 'native_bookmarks': bookmarks, 'footer_overlap': False}))
PYTHON;
    $process = new Process(['python', '-c', $python, $path], dirname(__DIR__, 2));
    $process->setTimeout(30);
    $process->mustRun();
    echo $process->getOutput();
    echo json_encode(['layout_pdf' => $path, 'seconds' => round(microtime(true) - $started, 2)], JSON_UNESCAPED_SLASHES)."\n";
}
