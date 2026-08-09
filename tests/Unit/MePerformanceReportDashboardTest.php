<?php

use App\Http\Controllers\MePerformanceReportDashboardController;
use App\Models\Indicator;
use App\Models\MePerformanceReportIndicatorResult;

it('defines every requested reporting dashboard metric and filter', function () {
    $controller = file_get_contents(
        dirname(__DIR__, 2).'/app/Http/Controllers/MePerformanceReportDashboardController.php'
    );
    $view = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/me/performance-reports/dashboard.blade.php'
    );

    foreach ([
        "'draft'",
        "'submitted'",
        "'returned'",
        "'verified'",
        "'approved'",
        "'archived'",
        'returned_for_correction',
        'overdueReports',
        'awaitingReview',
        'averageReviewMinutes',
        'averageApprovalMinutes',
        'indicatorCompleteness',
        'submissionReadiness',
        'evidenceCoverage',
        'ratingDistribution',
        'attentionReports',
        'reportsByThinkTank',
        'reportsByComponent',
        'reportsByPeriod',
    ] as $metric) {
        expect($controller)->toContain($metric);
    }

    foreach ([
        'reporting_year',
        'reporting_period_type',
        'reporting_period_label',
        'component_id',
        'results_level',
        'think_tank_id',
        'indicator_id',
        'thematic_area_id',
        'status',
        'performance_rating',
        'q',
        'sort',
        'per_page',
    ] as $filter) {
        expect($controller)->toContain("'{$filter}'")
            ->and($view)->toContain('name="'.$filter.'"');
    }

    expect($controller)
        ->toContain('applyAssignedPortfolioScopeToPortfolioOwnedRecords')
        ->toContain('assignment.collection:id,due_at')
        ->toContain('actual_text')
        ->toContain('resultIsComplete')
        ->toContain('decorateReport')
        ->toContain("'review_queue'")
        ->toContain("'approved_decisions'")
        ->toContain("'evidence_present'")
        ->toContain('public function csv')
        ->toContain('applyDrilldown');

    expect($view)
        ->toContain('Workflow distribution')
        ->toContain('Submission timeliness')
        ->toContain('Reports by think tank or partner')
        ->toContain('Reports by project component')
        ->toContain('Reports by reporting period')
        ->toContain('Indicator completeness')
        ->toContain('Awaiting review')
        ->toContain('Avg. review &amp; approval')
        ->toContain('Management attention queue')
        ->toContain('Export filtered CSV')
        ->toContain("asset('admin/assets/vendors/js/apexcharts.min.js')")
        ->toContain('report-readiness-chart')
        ->toContain('dataPointSelection')
        ->toContain('Permission-scoped drill-down')
        ->toContain("route('budget.me.performance-reports.edit'");
});

it('replaces the reporting dashboard scaffold with the permission-scoped dashboard', function () {
    $routes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');
    $register = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/me/data-entry/index.blade.php'
    );

    expect($routes)
        ->toContain('MePerformanceReportDashboardController')
        ->toContain("Route::get('me/rebuild/reporting-and-dashboard'")
        ->toContain("Route::get('me/rebuild/reporting-and-dashboard/export/csv'")
        ->toContain("name('me.rebuild.reporting-dashboard.csv')")
        ->toContain('permission:me.performance_reports.view');

    expect($register)
        ->toContain('Reporting performance dashboard')
        ->toContain("route('budget.me.rebuild.reporting-dashboard')");
});

it('counts milestone results by qualitative value and numeric results by numeric value', function () {
    $controller = new MePerformanceReportDashboardController;
    $method = new ReflectionMethod($controller, 'resultIsComplete');

    $milestone = new MePerformanceReportIndicatorResult([
        'indicator_result_id' => '019fe238-b9e0-72d3-8baf-2ec8958cea9a',
        'actual_text' => 'Milestone achieved and documented',
    ]);
    $milestone->setRelation('indicator', new Indicator(['value_type' => 'milestone']));

    $emptyMilestone = new MePerformanceReportIndicatorResult([
        'indicator_result_id' => '019fe238-b9e0-72d3-8baf-2ec8958cea9a',
    ]);
    $emptyMilestone->setRelation('indicator', new Indicator(['value_type' => 'milestone']));

    $numericZero = new MePerformanceReportIndicatorResult([
        'indicator_result_id' => '019fe238-b9e0-72d3-8baf-2ec8958cea9a',
        'actual_value' => 0,
    ]);
    $numericZero->setRelation('indicator', new Indicator(['value_type' => 'number']));

    expect($method->invoke($controller, $milestone))->toBeTrue()
        ->and($method->invoke($controller, $emptyMilestone))->toBeFalse()
        ->and($method->invoke($controller, $numericZero))->toBeTrue();
});
