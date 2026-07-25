<?php

use App\Models\Indicator;
use App\Models\MeMissionReport;
use App\Services\IndicatorAggregationService;

it('applies explicit aggregation policies without summing non-additive indicators', function () {
    $service = new IndicatorAggregationService();
    $values = collect([20, 40, 30]);

    expect($service->aggregate($values, 'sum'))->toBe(90.0)
        ->and($service->aggregate($values, 'average'))->toBe(30.0)
        ->and($service->aggregate($values, 'minimum'))->toBe(20.0)
        ->and($service->aggregate($values, 'maximum'))->toBe(40.0)
        ->and($service->aggregate($values, 'percentage'))->toBe(30.0)
        ->and($service->aggregate($values, 'ratio'))->toBe(30.0)
        ->and($service->aggregate($values, 'non_additive'))->toBe(30.0)
        ->and(array_keys(Indicator::AGGREGATION_METHODS))->toContain('sum', 'percentage', 'ratio', 'average');
});

it('requires every standardized mission report section before submission', function () {
    $report = new MeMissionReport([
        'title' => 'Regional monitoring mission',
        'location' => 'Nairobi',
        'team_members' => 'M&E Officer',
        'objectives' => 'Verify implementation.',
        'methodology' => 'Document review and interviews.',
        'executive_summary' => 'Implementation is progressing.',
        'key_findings' => 'Delivery evidence was available.',
        'recommendations' => 'Close the remaining action.',
        'corrective_actions' => 'Upload the signed verification.',
        'responsible_parties' => 'Implementing partner',
        'lessons_learned' => 'Earlier evidence checks reduce delays.',
        'conclusion' => 'Follow-up is required.',
    ]);

    expect($report->completionIssues())->toBeEmpty();
    $report->corrective_actions = null;
    expect($report->completionIssues())->toContain('Corrective actions');
});

it('exposes mission workflows, reporting reminders and all cumulative result fields', function () {
    $root = dirname(__DIR__, 2);
    $missionView = file_get_contents($root.'/resources/views/me/mission-reports/form.blade.php');
    $reportView = file_get_contents($root.'/resources/views/me/performance-reports/edit.blade.php');
    $command = file_get_contents($root.'/app/Console/Commands/SendMeReportingReminders.php');
    $routes = file_get_contents($root.'/routes/web.php');
    $portalNotifications = file_get_contents(
        $root.'/resources/views/think-tank/reporting-notifications/index.blade.php'
    );

    expect($missionView)
        ->toContain('Standardized M&amp;E mission report')
        ->toContain('Submit')
        ->toContain('Return Report')
        ->toContain('Approve Report')
        ->toContain('Archive Report');

    foreach ([
        'Result this period',
        'Cumulative this year',
        'Since programme baseline',
        'Annual target',
        'Life-of-programme target',
        'Annual target achieved',
    ] as $field) {
        expect($reportView)->toContain($field);
    }

    expect($command)
        ->toContain('deadline_upcoming')
        ->toContain('assignment_deadline_upcoming')
        ->toContain('report_overdue')
        ->toContain('corrective_action_outstanding')
        ->toContain('mov_validation_required');

    expect($routes)
        ->toContain("Route::prefix('me/mission-reports')")
        ->toContain("Route::prefix('me/reporting-notifications')")
        ->toContain("name('reporting-notifications.index')");

    expect($portalNotifications)
        ->toContain('Notifications &amp; Reminders')
        ->toContain('Mark all read');
});
