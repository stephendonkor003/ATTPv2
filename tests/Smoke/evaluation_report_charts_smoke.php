<?php

use App\Support\EvaluationReportCharts;
use Dompdf\Dompdf;
use Dompdf\Options;

require __DIR__.'/../../vendor/autoload.php';

$labels = array_map(fn ($number) => 'APP-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT), range(1, 10));
$series = [
    ['name' => 'Evaluator A — technical committee', 'color' => '#176b87', 'values' => [86, null, 0, 74, 92, 66, 70, 84, 79, 80]],
    ['name' => 'Evaluator B — independent specialist', 'color' => '#b4552d', 'values' => [88, 71, 64, 76, 89, 68, 72, 85, 78, 82]],
    ['name' => 'Evaluator C — procurement specialist', 'color' => '#5f4b8b', 'values' => [83, 72, 66, 73, 91, 65, 75, null, 77, 83]],
];
$bars = EvaluationReportCharts::grouped('Panel scores by applicant', $labels, $series);
$lines = EvaluationReportCharts::grouped('Evaluator comparison across applicants', $labels, $series, 'line');
chartSmokeAssert(count($bars) === 2 && count($lines) === 2, 'Applicant chunks lost data beyond eight applicants.');
chartSmokeAssert(str_contains($bars[1]['alt'], 'APP-010') && str_contains($bars[0]['alt'], 'not recorded')
    && str_contains($bars[0]['alt'], 'Evaluator A — technical committee: 0'), 'Accessible chart data lost a last applicant, missing value, or recorded zero.');

$gapChart = EvaluationReportCharts::grouped('Gaps remain gaps', ['A', 'B', 'C', 'D', 'E'], [
    ['name' => 'Panel member', 'values' => [70, 80, null, 0, 60]],
], 'line')[0];
$gapXml = chartSmokeXml($gapChart);
chartSmokeAssert($gapXml->getElementsByTagName('polyline')->length === 2 && $gapXml->getElementsByTagName('circle')->length === 4,
    'A missing evaluator value was plotted as zero or joined across a data gap.');
$pie = EvaluationReportCharts::pie('Panel completion', [
    ['name' => 'Submitted evaluation reports', 'value' => 18, 'color' => '#176b87'],
    ['name' => 'Awaiting panel submission', 'value' => 6, 'color' => '#b4552d'],
    ['name' => 'Returned for revision', 'value' => 0, 'color' => '#5f4b8b'],
]);
chartSmokeAssert(chartSmokeXml($pie)->getElementsByTagName('polygon')->length === 2, 'Pie includes a fabricated zero-count slice.');
$empty = EvaluationReportCharts::pie('No completed outcomes yet', [['name' => 'Qualified', 'value' => 0], ['name' => 'Not qualified', 'value' => null]]);
chartSmokeAssert(chartSmokeXml($empty)->getElementsByTagName('polygon')->length === 0
    && str_contains($empty['alt'], 'Not recorded'), 'Empty outcome chart invented data.');
$escaped = EvaluationReportCharts::grouped('Title <script>alert(1)</script> & review', ['A & B', '<img src=x>'], [
    ['name' => 'Evaluator </text><script>alert(1)</script>', 'color' => 'red" onload="alert(1)', 'values' => [50, null]],
])[0];
$escapedXml = chartSmokeXml($escaped);
chartSmokeAssert($escapedXml->getElementsByTagName('script')->length === 0 && $escapedXml->getElementsByTagName('img')->length === 0,
    'A label or colour escaped its SVG text/attribute context.');

$directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'evaluation-report-charts';
if (! is_dir($directory)) {
    mkdir($directory, 0700, true);
}
$charts = ['bar' => $bars[0], 'line' => $lines[0], 'pie' => $pie, 'empty' => $empty];
$html = '<!doctype html><html><head><meta charset="utf-8"><style>@page{margin:20px}body{font-family:DejaVu Sans,sans-serif}section{page-break-after:always}section:last-child{page-break-after:auto}img{width:100%}p{font-size:11px;color:#52677b}</style></head><body>';
foreach ($charts as $name => $chart) {
    $xml = chartSmokeXml($chart);
    file_put_contents($directory.DIRECTORY_SEPARATOR.$name.'.svg', $xml->saveXML());
    $html .= '<section><img src="'.$chart['src'].'" alt="'.htmlspecialchars($chart['alt'], ENT_QUOTES, 'UTF-8').'"><p>'.htmlspecialchars($chart['note'], ENT_QUOTES, 'UTF-8').'</p></section>';
}
$html .= '</body></html>';
file_put_contents($directory.DIRECTORY_SEPARATOR.'charts.html', $html);
$options = new Options;
$options->set('isRemoteEnabled', false);
$dompdf = new Dompdf($options);
$dompdf->setPaper('a4', 'landscape');
$dompdf->loadHtml($html);
$dompdf->render();
$pdf = $dompdf->output();
chartSmokeAssert(str_starts_with($pdf, '%PDF-') && strlen($pdf) > 20000 && $dompdf->getCanvas()->get_page_count() === 4,
    'Chart SVG images failed to render as four real PDF pages.');
file_put_contents($directory.DIRECTORY_SEPARATOR.'charts.pdf', $pdf);
echo "EVALUATION_REPORT_CHARTS_SMOKE_OK (4 SVG charts rendered to PDF; gaps, zeroes, escaping, and chunk coverage checked)\n";
echo $directory.PHP_EOL;

function chartSmokeXml(array $chart): DOMDocument
{
    chartSmokeAssert(str_starts_with($chart['src'], 'data:image/svg+xml;base64,'), 'Chart source is not a self-contained SVG.');
    $xml = new DOMDocument;
    chartSmokeAssert($xml->loadXML(base64_decode(substr($chart['src'], strlen('data:image/svg+xml;base64,')), true)), 'Chart XML is invalid.');

    return $xml;
}

function chartSmokeAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
