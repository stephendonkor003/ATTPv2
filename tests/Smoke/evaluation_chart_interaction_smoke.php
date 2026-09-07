<?php

use App\Support\EvaluationReportCharts;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function interactionAssert(bool $condition, string $message): void
{
    if (! $condition) throw new RuntimeException($message);
}

$labels = ['Applicant with a complete, unabridged name', 'Recorded zero', 'Missing score', 'Applicant </script><img src=x onerror=alert(1)>'];
$series = [['name' => 'Evaluator <script>alert(2)</script> & "colleague"', 'color' => '#176b87', 'values' => [17.5, 0, null, 20]]];
$bar = EvaluationReportCharts::grouped('Section scores', $labels, $series, 'bar', 20, 'Section score (points)')[0];
$line = EvaluationReportCharts::grouped('Panel percentages', $labels, $series, 'line')[0];
$pie = EvaluationReportCharts::pie('Recorded outcomes', [
    ['name' => 'Completed', 'value' => 3], ['name' => 'Awaiting', 'value' => 1],
    ['name' => 'Recorded zero category', 'value' => 0], ['name' => 'Missing count', 'value' => null],
]);
$empty = EvaluationReportCharts::pie('No recorded outcomes', [['name' => 'Zero', 'value' => 0], ['name' => 'Missing', 'value' => null]]);

interactionAssert($bar['interaction']['records'][0]['applicant'] === $labels[0]
    && $bar['interaction']['records'][0]['evaluator'] === $series[0]['name'], 'Tooltip labels were truncated or changed.');
interactionAssert($bar['interaction']['records'][1]['value'] === 0.0 && $bar['interaction']['records'][1]['display_value'] === '0 points'
    && $bar['interaction']['records'][2]['value'] === null && $bar['interaction']['records'][2]['display_value'] === 'Not recorded', 'Missing scores were converted to zero.');
interactionAssert($line['interaction']['records'][0]['display_value'] === '17.5%' && $line['interaction']['records'][1]['display_value'] === '0%', 'Score units were lost.');
interactionAssert($pie['interaction']['records'][0]['percentage'] === 75.0 && $pie['interaction']['records'][2]['display_percentage'] === '0.0%'
    && $pie['interaction']['records'][3]['percentage'] === null, 'Pie shares invented or omitted data.');
interactionAssert(count($pie['interaction']['targets']) === 6 && count($empty['interaction']['targets']) === 2
    && $empty['interaction']['records'][0]['display_percentage'] === 'Not available', 'Zero and missing pie categories lost inspectable legends or gained fake slices.');
$emptyXml = new DOMDocument;
$emptyXml->loadXML(base64_decode(explode(',', $empty['src'], 2)[1]));
interactionAssert($emptyXml->getElementsByTagName('polygon')->length === 0, 'An empty distribution draws a fabricated slice.');

$body = '';
foreach ([$bar, $line, $pie, $empty] as $chart) {
    interactionAssert(count($chart['interaction']['records']) > 0 && $chart['interaction']['width'] === 1100, 'Missing interaction dimensions or records.');
    $html = view('reports.evaluations.partials.interactive-chart', ['chart' => $chart])->render();
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($dom);
    interactionAssert($xpath->query('//script')->length === 0 && $xpath->query('//img')->length === 1, 'A chart label escaped into executable markup.');
    interactionAssert($xpath->query('//*[@onerror or @onload]')->length === 0, 'A label created an event handler.');
    $figure = $xpath->query('//figure')->item(0);
    $records = json_decode($figure->getAttribute('data-eval-records'), true, 512, JSON_THROW_ON_ERROR);
    interactionAssert($records === json_decode(json_encode($chart['interaction']['records']), true), 'Escaping changed tooltip data.');
    interactionAssert($xpath->query('//details//tbody/tr')->length === count($records), 'No-JavaScript table omitted observations.');
    interactionAssert($xpath->query('//img')->item(0)->getAttribute('src') === $chart['src'], 'Interactive web image differs from the export image.');
    $body .= $html;
}

$directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'evaluation-report-charts';
if (! is_dir($directory)) mkdir($directory, 0700, true);
$style = file_get_contents(__DIR__.'/../../resources/views/reports/evaluations/partials/management-report-styles.blade.php');
file_put_contents($directory.DIRECTORY_SEPARATOR.'interactive.html', '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
    .$style.'<link rel="stylesheet" href="/assets/css/evaluation-chart-details.css"><script defer src="/assets/js/evaluation-chart-details.js"></script>'
    .'<style>body{font-family:Arial,sans-serif;margin:16px}main{max-width:1100px;margin:auto}</style></head><body><main class="eval-management">'.$body.'</main></body></html>');
echo "EVALUATION_CHART_INTERACTION_SMOKE_OK (full labels, score units, zero/missing values, pie shares, safe Blade metadata, no-JS tables and source parity)\n";
echo $directory.DIRECTORY_SEPARATOR.'interactive.html'.PHP_EOL;
