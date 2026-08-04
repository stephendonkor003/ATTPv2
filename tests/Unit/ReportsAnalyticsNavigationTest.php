<?php

it('integrates financial execution analytics into project financial position', function () {
    $root = dirname(__DIR__, 2);
    $sidebar = file_get_contents($root.'/resources/views/layouts/partials/sidebar.blade.php');
    $position = file_get_contents($root.'/resources/views/budgetreport/project-financial-position.blade.php');
    $positionPdf = file_get_contents($root.'/resources/views/budgetreport/project-financial-position-pdf.blade.php');
    $webExecution = file_get_contents($root.'/resources/views/budgetreport/partials/execution-dashboard-web.blade.php');
    $pdfExecution = file_get_contents($root.'/resources/views/budgetreport/partials/execution-dashboard-pdf.blade.php');
    $reportController = file_get_contents($root.'/app/Http/Controllers/BudgetReportController.php');
    $legacyController = file_get_contents($root.'/app/Http/Controllers/MasterDashboard.php');

    expect(substr_count($sidebar, "route('budget.reports.project-financial-position')"))->toBe(1)
        ->and($sidebar)->not->toContain("route('finance.execution.dashboard')")
        ->and($position)->toContain("@include('budgetreport.partials.execution-dashboard-web')")
        ->and($position)->toContain('data-financial-control="unallocated-funds"')
        ->and($position)->toContain('Available Unallocated Funds')
        ->and($position)->not->toContain('Open source dashboard')
        ->and($positionPdf)->toContain("@include('budgetreport.partials.execution-dashboard-pdf')")
        ->and($positionPdf)->toContain('Available Unallocated Funds')
        ->and($pdfExecution)->not->toContain('<h2>Financial Execution Analytics</h2>')
        ->and($pdfExecution)->not->toContain('Execution Dashboard')
        ->and($webExecution)->toContain('Financial Execution Analytics')
        ->and($webExecution)->toContain('Year-by-Year Execution')
        ->and($webExecution)->toContain('Component Execution Breakdown')
        ->and($webExecution)->toContain('Sub-Component Execution Breakdown')
        ->and($pdfExecution)->toContain('Sub-Component Execution Breakdown')
        ->and($webExecution)->toContain('Execution Insights')
        ->and(substr_count($webExecution, "'key' =>"))->toBe(7)
        ->and(substr_count($pdfExecution, "'key' =>"))->toBe(7)
        ->and($reportController)->toContain("'executionDashboard' => \$executionDashboard")
        ->and($reportController)->toContain("'project_id' => \$dashboardProjectId")
        ->and($reportController)->toContain("\$totals['unallocated_funds'] = round(\$unallocatedFunds['available_balance'], 2)")
        ->and($reportController)->toContain("buildFromDataset(")
        ->and($legacyController)->toContain("redirect()->route('budget.reports.project-financial-position'")
        ->and($legacyController)->toContain("redirect()->route('budget.reports.project-financial-position.export.pdf'");
});

it('matches only explicit unallocated-funds balance-sheet lines', function () {
    $controller = new \App\Http\Controllers\BudgetReportController;
    $method = new ReflectionMethod($controller, 'isUnallocatedFundsBalanceSheetLine');

    expect($method->invoke($controller, 'Unallocated Funds'))->toBeTrue()
        ->and($method->invoke($controller, '  unallocated   fund  '))->toBeTrue()
        ->and($method->invoke($controller, 'Unallocated Programme Balance'))->toBeFalse()
        ->and($method->invoke($controller, 'Funding Utilization Gap'))->toBeFalse();
});
