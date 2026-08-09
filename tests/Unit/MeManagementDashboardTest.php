<?php

use App\Http\Controllers\MeManagementDashboardController;
use App\Models\Indicator;
use App\Models\MePerformanceReportIndicatorResult;

it('replaces the management scaffold with a permission-scoped decision dashboard', function () {
    $root = dirname(__DIR__, 2);
    $routes = file_get_contents($root.'/routes/web.php');
    $controller = file_get_contents($root.'/app/Http/Controllers/MeManagementDashboardController.php');
    $view = file_get_contents($root.'/resources/views/me/management-dashboard/index.blade.php');

    expect($routes)
        ->toContain('MeManagementDashboardController')
        ->toContain("Route::get('me/rebuild/management-dashboard'")
        ->toContain('me.performance_reports.view|me.performance_reports.review');

    foreach ([
        'official_reports',
        'official_rate',
        'average_achievement',
        'reporting_coverage',
        'awaiting_decision',
        'open_errors',
        'open_warnings',
        'evidence_coverage',
        'attention_reports',
        'submission_approval_rate',
        'assignmentHasBeenSubmitted',
        'assignmentHasBeenApproved',
        'organizationRows',
        'portfolioRows',
        'periodHealth',
        'recentDecisions',
    ] as $metric) {
        expect($controller)->toContain($metric);
    }

    expect($controller)
        ->toContain('applyAssignedPortfolioScopeToPortfolioOwnedRecords')
        ->toContain('applyAssignedPortfolioScopeToSectors')
        ->toContain('isApproved()')
        ->toContain('resultIsComplete')
        ->toContain('assertAuthorizedFilter');

    foreach (['reporting_year', 'portfolio_id', 'reporting_period_id', 'think_tank_id'] as $filter) {
        expect($controller)->toContain("'{$filter}'")
            ->and($view)->toContain('name="'.$filter.'"');
    }

    expect($view)
        ->toContain('Official performance is approval-controlled')
        ->toContain('Portfolio readiness')
        ->toContain('Management action queue')
        ->toContain('Reporting organization coverage')
        ->toContain('Reporting window health')
        ->toContain('Recent official decisions')
        ->toContain("asset('admin/assets/vendors/js/apexcharts.min.js')")
        ->toContain('management-portfolio-chart')
        ->toContain('management-lifecycle-chart')
        ->toContain('management-performance-chart')
        ->toContain('management-quality-chart')
        ->toContain('dataPointSelection')
        ->toContain('management-print');
});

it('treats qualitative and numeric official indicator results correctly', function () {
    $controller = new MeManagementDashboardController;
    $method = new ReflectionMethod($controller, 'resultIsComplete');

    $milestone = new MePerformanceReportIndicatorResult([
        'indicator_result_id' => '019fe238-b9e0-72d3-8baf-2ec8958cea9a',
        'actual_text' => 'Milestone completed',
    ]);
    $milestone->setRelation('indicator', new Indicator(['value_type' => 'milestone']));

    $numericZero = new MePerformanceReportIndicatorResult([
        'indicator_result_id' => '019fe238-b9e0-72d3-8baf-2ec8958cea9a',
        'actual_value' => 0,
    ]);
    $numericZero->setRelation('indicator', new Indicator(['value_type' => 'number']));

    $missingCanonicalResult = new MePerformanceReportIndicatorResult(['actual_value' => 25]);
    $missingCanonicalResult->setRelation('indicator', new Indicator(['value_type' => 'number']));

    expect($method->invoke($controller, $milestone))->toBeTrue()
        ->and($method->invoke($controller, $numericZero))->toBeTrue()
        ->and($method->invoke($controller, $missingCanonicalResult))->toBeFalse();
});
