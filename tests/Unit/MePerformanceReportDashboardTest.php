<?php

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
        'indicatorCompleteness',
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
    ] as $filter) {
        expect($controller)->toContain("'{$filter}'")
            ->and($view)->toContain('name="'.$filter.'"');
    }

    expect($controller)
        ->toContain('applyAssignedPortfolioScopeToPortfolioOwnedRecords')
        ->toContain('assignment.collection:id,due_at')
        ->toContain('indicatorResults as reported_indicator_results_count')
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
        ->toContain('permission:me.performance_reports.view');

    expect($register)
        ->toContain('Reporting performance dashboard')
        ->toContain("route('budget.me.rebuild.reporting-dashboard')");
});
