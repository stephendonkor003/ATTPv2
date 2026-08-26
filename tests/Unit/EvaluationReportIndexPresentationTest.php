<?php

it('builds a procurement-first evaluation report library with separate method routes', function () {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents($root.'/app/Http/Controllers/EvaluationReportController.php');
    $view = file_get_contents($root.'/resources/views/reports/evaluations/index.blade.php');

    expect($controller)
        ->toContain("'evaluationAssignments.evaluation:id,name,type,evaluation_phase,procurement_id'")
        ->toContain("'evaluations:id,name,type,evaluation_phase,procurement_id'")
        ->toContain('$directEvaluationsByProcurement')
        ->toContain('->whereNotNull(\'submitted_at\')')
        ->toContain('->whereIn(\'procurement_id\', $procurements->pluck(\'id\'))')
        ->toContain("'procurement_method' =>")
        ->toContain("'methods' => \$methods")
        ->toContain("'status' => 'awaiting'")
        ->and($view)
        ->toContain('Reports grouped by procurement')
        ->toContain('Procurement method')
        ->toContain('Evaluation methods')
        ->toContain('data-report-procurement')
        ->toContain('data-report-method')
        ->toContain("route('reports.evaluations.procurement', \$procurement)")
        ->toContain("route('reports.evaluations.eoi.procurement', \$procurement)")
        ->toContain("route('reports.evaluations.submission', \$submission)")
        ->toContain('evaluationMethodFilter')
        ->toContain('evaluationReportNoResults');
});

