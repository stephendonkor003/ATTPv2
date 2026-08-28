<?php

it('builds a method-first evaluation report centre with dedicated procurement lists', function () {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents($root.'/app/Http/Controllers/EvaluationReportController.php');
    $routes = file_get_contents($root.'/routes/web.php');
    $landing = file_get_contents($root.'/resources/views/reports/evaluations/index.blade.php');
    $methodList = file_get_contents($root.'/resources/views/reports/evaluations/method.blade.php');

    expect($controller)
        ->toContain('public function methodIndex(string $method)')
        ->toContain("->whereHas('evaluationAssignments.evaluation'")
        ->toContain("->orWhereHas('submissions.evaluationSubmissions.evaluation'")
        ->toContain("->orWhereHas('evaluationSubmissions.evaluation'")
        ->toContain("->orWhereHas('evaluations'")
        ->toContain("->orWhereHas('directEvaluations'")
        ->toContain("->whereHas('evaluation', fn (\$evaluation) => \$evaluation->where('type', \$method))")
        ->and($routes)
        ->toContain("'/method/{method}'")
        ->toContain("'services|goods|eoi'")
        ->toContain("->name('method')")
        ->and($landing)
        ->toContain('Evaluation Report Centre')
        ->toContain('evr-method-grid')
        ->toContain('evr-method-card--{{ $type }}')
        ->toContain("route('reports.evaluations.method', \$type)")
        ->toContain("'services' => 'feather-bar-chart-2'")
        ->toContain("'goods' => 'feather-package'")
        ->toContain("'eoi' => 'feather-user-check'")
        ->and($methodList)
        ->toContain('Procurements using {{ $methodDefinition[\'label\'] }}')
        ->toContain('data-method-procurement')
        ->toContain('methodProcurementSearch')
        ->toContain("route('reports.evaluations.method.procurement'")
        ->toContain("route('reports.evaluations.eoi.procurement'");
});

it('provides method-aware rankings, categorical outcomes, and every requested export', function () {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents($root.'/app/Http/Controllers/EvaluationReportController.php');
    $routes = file_get_contents($root.'/routes/web.php');
    $detail = file_get_contents($root.'/resources/views/reports/evaluations/method-procurement.blade.php');
    $eoiDetail = file_get_contents($root.'/resources/views/reports/evaluations/eoi-procurement.blade.php');

    expect($controller)
        ->toContain('normalisedServiceScore')
        ->toContain('buildServiceRankingGroups')
        ->toContain('if ($expectedKeys->isEmpty())')
        ->toContain("'panel_status' => 'Assignment baseline unavailable'")
        ->toContain('abs($previousMetric - $row[\'metric\']) >= 0.005')
        ->toContain('if ($method !== Evaluation::TYPE_SERVICES)')
        ->toContain('safeSpreadsheetValue')
        ->toContain("preg_match('/^[\\x00-\\x20]*[=+\\-@]/u'")
        ->and($routes)
        ->toContain("->name('method.procurement.excel')")
        ->toContain("->name('method.procurement.csv')")
        ->toContain("->name('method.procurement.pdf')")
        ->toContain("->name('eoi.procurement.excel')")
        ->toContain("->name('eoi.procurement.csv')")
        ->and($detail)
        ->toContain('feather-award')
        ->toContain('Applicant ranking')
        ->toContain('Applicant compliance summary')
        ->toContain('Goods evaluations are categorical')
        ->toContain('not converted into numeric ranks')
        ->toContain("route('reports.evaluations.method.procurement.excel'")
        ->toContain("route('reports.evaluations.method.procurement.csv'")
        ->toContain("route('reports.evaluations.method.procurement.pdf'")
        ->toContain('onclick="window.print()"')
        ->and($eoiDetail)
        ->toContain("route('reports.evaluations.eoi.procurement.excel'")
        ->toContain("route('reports.evaluations.eoi.procurement.csv'")
        ->toContain('onclick="window.print()"');
});
