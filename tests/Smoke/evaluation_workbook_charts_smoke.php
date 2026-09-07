<?php

use App\Exports\EvaluationProcurementWorkbookExport;
use App\Support\EvaluationReportCharts;
use Illuminate\Contracts\Console\Kernel;
use Maatwebsite\Excel\Excel;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$labels = array_map(fn ($number) => 'APP-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT), range(1, 10));
$series = [
    ['name' => 'Evaluator A', 'color' => '#176b87', 'values' => [86, null, 0, 74, 92, 66, 70, 84, 79, 80]],
    ['name' => '=SUM(1,1)', 'color' => '#b4552d', 'values' => [88, 71, 64, 76, 89, 68, 72, 85, 78, 82]],
];
$bars = EvaluationReportCharts::grouped('Panel scores', $labels, $series);
$lines = EvaluationReportCharts::grouped('Evaluator comparisons', $labels, $series, 'line');
$pie = EvaluationReportCharts::pie('Panel completion', [
    ['name' => 'Completed', 'value' => 18, 'color' => '#176b87'],
    ['name' => 'Awaiting submission', 'value' => 6, 'color' => '#b4552d'],
    ['name' => 'No returned reports', 'value' => 0, 'color' => '#5f4b8b'],
    ['name' => 'Not recorded', 'value' => null, 'color' => '#23745c'],
]);
$empty = EvaluationReportCharts::pie('No recorded outcomes', [['name' => 'Outcome', 'value' => null]]);
$titles = ['1 Summary overview', '2 Results and rankings', '3 Detailed scoring', '4 Panel consistency', '5 Governance and audit'];
$rows = array_fill_keys($titles, [['Field', 'Value'], ['Recorded zero', 0], ['Pending', 'Not recorded']]);
$map = array_combine($titles, [[$pie], $bars, [$bars[0]], $lines, [$pie, $empty]]);
$export = new EvaluationProcurementWorkbookExport($rows, $map);
$nativeCounts = array_map(fn ($sheet) => count($sheet->charts()), $export->sheets());
workbookAssert($nativeCounts === [1, 2, 1, 2, 1], 'Five sections did not receive their matching native charts, or empty data invented a chart.');
workbookAssert($bars[1]['data']['labels'] === ['APP-009', 'APP-010'] && $bars[0]['data']['series'][0]['values'][1] === null
    && $bars[0]['data']['series'][0]['values'][2] === 0.0, 'Shared chart metadata lost applicant chunks, gaps or zeroes.');

$content = app('excel')->raw($export, Excel::XLSX);
$directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'evaluation-workbook-charts';
if (! is_dir($directory)) {
    mkdir($directory, 0700, true);
}
$path = $directory.DIRECTORY_SEPARATOR.'five-sections-native-charts.xlsx';
file_put_contents($path, $content);
$zip = new ZipArchive;
workbookAssert($zip->open($path) === true, 'Excel export is not a valid XLSX ZIP file.');
$chartXml = [];
for ($entry = 0; $entry < $zip->numFiles; $entry++) {
    $name = $zip->getNameIndex($entry);
    if (preg_match('~^xl/charts/chart[0-9]+\.xml$~', $name)) {
        $chartXml[] = $zip->getFromIndex($entry);
    }
}
workbookAssert(count($chartXml) === 7, 'Saved workbook omitted its editable chart parts.');
$counts = ['barChart' => 0, 'lineChart' => 0, 'pieChart' => 0];
$gapChecked = $pieChecked = false;
foreach ($chartXml as $xml) {
    $dom = new DOMDocument;
    workbookAssert($dom->loadXML($xml), 'Saved native chart is malformed XML.');
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('c', 'http://schemas.openxmlformats.org/drawingml/2006/chart');
    $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
    foreach (array_keys($counts) as $type) {
        $counts[$type] += $xpath->query('//c:'.$type)->length;
    }
    workbookAssert($xpath->query('//c:numCache/c:pt/c:v[not(text())]')->length === 0, 'Missing observation was written as an empty numeric point instead of a gap.');
    if ($xpath->query('//c:lineChart')->length && str_contains($xml, 'APP-008')) {
        workbookAssert($xpath->query('//c:dispBlanksAs[@val="gap"]')->length === 1, 'Missing line scores are connected or plotted as zero.');
        $points = $xpath->query('(//c:lineChart/c:ser)[1]/c:val/c:numRef/c:numCache/c:pt');
        $values = [];
        foreach ($points as $point) {
            $values[(int) $point->getAttribute('idx')] = (float) $point->textContent;
        }
        workbookAssert(! array_key_exists(1, $values) && ($values[2] ?? null) === 0.0 && ($values[0] ?? null) === 86.0,
            'Native line cache confused a missing score with recorded zero or reindexed applicants.');
        workbookAssert($xpath->query('//c:lineChart/c:ser/c:spPr/a:ln/a:solidFill/a:srgbClr[@val="176b87"]')->length === 1
            && $xpath->query('//c:lineChart/c:ser/c:spPr/a:ln/a:solidFill/a:srgbClr[@val="b4552d"]')->length === 1,
            'Distinct evaluator colours did not survive native Excel line export.');
        $gapChecked = true;
    }
    if ($xpath->query('//c:pieChart')->length) {
        $points = $xpath->query('//c:pieChart/c:ser/c:val/c:numRef/c:numCache/c:pt');
        $values = [];
        foreach ($points as $point) {
            $values[(int) $point->getAttribute('idx')] = (float) $point->textContent;
        }
        workbookAssert($values === [0 => 18.0, 1 => 6.0, 2 => 0.0], 'Pie distribution lost a recorded count or fabricated a missing count.');
        $pieChecked = true;
    }
}
workbookAssert($counts === ['barChart' => 3, 'lineChart' => 2, 'pieChart' => 2] && $gapChecked && $pieChecked,
    'Native workbook types or numeric caches were not fully verified.');
$zip->close();

$reader = new Xlsx;
$reader->setIncludeCharts(true);
$book = $reader->load($path);
workbookAssert($book->getSheetNames() === $titles, 'Five report sheet titles/order changed during export.');
foreach ($book->getAllSheets() as $index => $sheet) {
    workbookAssert($sheet->getChartCount() === $nativeCounts[$index], 'Excel reader cannot reopen embedded native charts.');
    workbookAssert($sheet->getCell('B2')->getValue() === 0, 'Recorded zero disappeared from a report cell.');
    foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
        workbookAssert($sheet->getCell($coordinate)->getDataType() !== 'f', 'A chart source label became an executable spreadsheet formula.');
    }
}
$last = $book->getSheet(4)->toArray();
workbookAssert(str_contains(json_encode($last), 'No recorded data to plot.'), 'Empty chart has no explanatory source data note.');
$book->disconnectWorksheets();
echo 'EVALUATION_WORKBOOK_CHARTS_SMOKE_OK (5 sheets, 7 native charts, missing values, recorded zeroes, colours, types, safe source tables and reader round-trip)'.PHP_EOL;
echo $path.PHP_EOL;

if (getenv('EVALUATION_WORKBOOK_REAL') === '1') {
    unset($export, $content, $book, $chartXml);
    gc_collect_cycles();
    $administrator = \App\Models\User::with('role')->get()->first(fn ($user) => $user->isAdmin() || $user->isSuperAdmin());
    workbookAssert($administrator !== null, 'A reporting administrator is required for real export checks.');
    \Illuminate\Support\Facades\Auth::setUser($administrator);
    $request = \Illuminate\Http\Request::create('/reports/evaluations', 'GET');
    $request->setUserResolver(fn () => $administrator);
    $app->instance('request', $request);
    \Illuminate\Support\Facades\Mail::fake();
    \Illuminate\Support\Facades\Bus::fake();
    $controller = app(\App\Http\Controllers\EvaluationReportController::class);
    $before = [\App\Models\EvaluationSubmission::count(), \App\Models\EvaluationCriteriaScore::count()];
    foreach ([
        'endowment' => ['services', '01a0047d-3294-7100-b8e1-37187541feba'],
        'scored' => ['services', '3ed5b12a-8895-4cd3-a2fa-4e74bb147141'],
        'eoi' => ['eoi', '01a0047d-3294-7100-b8e1-37187541feba'],
    ] as $name => [$method, $procurementId]) {
        $procurement = \App\Models\Procurement::findOrFail($procurementId);
        $response = $method === 'eoi'
            ? $controller->eoiProcurementExcel($procurement, app(\App\Services\EoiQualificationService::class))
            : $controller->methodProcurementExcel($method, $procurement);
        workbookAssert($response->getStatusCode() === 200
            && str_contains($response->headers->get('Content-Type'), 'spreadsheetml.sheet'), $name.' controller did not return a real XLSX download.');
        $destination = $directory.DIRECTORY_SEPARATOR.$name.'-real.xlsx';
        copy($response->getFile()->getPathname(), $destination);
        $archive = new ZipArchive;
        workbookAssert($archive->open($destination) === true, $name.' controller returned a malformed workbook.');
        $manifest = new DOMDocument;
        $manifest->loadXML($archive->getFromName('xl/workbook.xml'));
        $sheetTitles = [];
        foreach ($manifest->getElementsByTagName('sheet') as $node) {
            $sheetTitles[] = $node->getAttribute('name');
        }
        workbookAssert(array_slice($sheetTitles, 0, 5) === $titles, $name.' real workbook lost the five report sections.');
        $nativeCharts = 0;
        for ($entry = 0; $entry < $archive->numFiles; $entry++) {
            if (! preg_match('~^xl/charts/chart[0-9]+\.xml$~', $archive->getNameIndex($entry))) {
                continue;
            }
            $nativeCharts++;
            $chartDocument = new DOMDocument;
            workbookAssert($chartDocument->loadXML($archive->getFromIndex($entry)), $name.' real chart XML is invalid.');
            $chartQuery = new DOMXPath($chartDocument);
            $chartQuery->registerNamespace('c', 'http://schemas.openxmlformats.org/drawingml/2006/chart');
            workbookAssert($chartQuery->query('//c:numCache/c:pt/c:v[not(text())]')->length === 0, $name.' real chart fabricated an empty numeric value.');
        }
        workbookAssert($nativeCharts > 0, $name.' real workbook omitted all native report graphs.');
        $archive->close();
        echo strtoupper($name).'_REAL_CONTROLLER_XLSX_OK '.json_encode(['sheets' => count($sheetTitles), 'native_charts' => $nativeCharts, 'bytes' => filesize($destination)]).PHP_EOL;
        unset($response, $archive, $procurement, $manifest, $chartDocument, $chartQuery);
        gc_collect_cycles();
    }
    workbookAssert($before === [\App\Models\EvaluationSubmission::count(), \App\Models\EvaluationCriteriaScore::count()], 'Report export changed source evaluation data.');
    \Illuminate\Support\Facades\Mail::assertNothingSent();
    \Illuminate\Support\Facades\Bus::assertNothingDispatched();
}

function workbookAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
