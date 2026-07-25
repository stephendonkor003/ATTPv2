<?php

use App\Models\Indicator;
use App\Models\MePerformanceReport;
use App\Models\MePerformanceReportDocument;
use App\Models\MePerformanceReportIndicatorResult;
use App\Models\ReportingFrequency;
use App\Support\IndicatorReportingSchedule;
use Illuminate\Database\Eloquent\Collection;

it('enforces the approved indicator cadence for quarterly reporting', function (
    string $intervalUnit,
    int $intervalValue,
    array $dueQuarters
) {
    $frequency = new ReportingFrequency([
        'name' => 'Test frequency',
        'code' => 'TEST',
        'interval_unit' => $intervalUnit,
        'interval_value' => $intervalValue,
    ]);
    $indicator = new Indicator();
    $indicator->setRelation('frequency', $frequency);

    foreach (['Q1', 'Q2', 'Q3', 'Q4'] as $quarter) {
        expect(IndicatorReportingSchedule::isDueInQuarter($indicator, $quarter))
            ->toBe(in_array($quarter, $dueQuarters, true));
    }
})->with([
    'monthly' => ['month', 1, ['Q1', 'Q2', 'Q3', 'Q4']],
    'quarterly' => ['quarterly', 1, ['Q1', 'Q2', 'Q3', 'Q4']],
    'semi-annual' => ['month', 6, ['Q2', 'Q4']],
    'annual' => ['annual', 1, ['Q4']],
]);

it('keeps report status and file presentation rules explicit', function () {
    $report = new MePerformanceReport(['status' => MePerformanceReport::STATUS_DRAFT]);
    expect($report->isEditable())->toBeTrue();

    $report->status = MePerformanceReport::STATUS_SUBMITTED;
    expect($report->isEditable())->toBeFalse();

    $report->status = MePerformanceReport::STATUS_REVIEWED;
    expect($report->isEditable())->toBeFalse()
        ->and($report->lifecycleLabel())->toBe('Reviewed Report');

    $report->status = MePerformanceReport::STATUS_ARCHIVED;
    expect($report->isEditable())->toBeFalse()
        ->and($report->lifecycleLabel())->toBe('Archived Report');

    $document = new MePerformanceReportDocument(['file_size' => 1_572_864]);
    expect($document->formattedSize())->toBe('1.5 MB');
});

it('requires every standardized report section before submission', function () {
    $indicator = new Indicator(['indicator_code' => 'PDO-1']);
    $indicatorResult = new MePerformanceReportIndicatorResult([
        'actual_value' => null,
        'indicator_result_id' => null,
    ]);
    $indicatorResult->setRelation('indicator', $indicator);

    $report = new MePerformanceReport([
        'status' => MePerformanceReport::STATUS_DRAFT,
    ]);
    $report->setRelation('indicatorResults', new Collection([$indicatorResult]));
    $report->setRelation('documents', new Collection());

    expect($report->isSubmissionReady())->toBeFalse()
        ->and($report->sectionCompletion()['indicator_results']['status'])->toBe('not_started')
        ->and($report->sectionCompletion()['means_of_verification']['status'])->toBe('not_started')
        ->and($report->submissionIssues())->toHaveCount(7);

    $indicatorResult->actual_value = 42;
    $indicatorResult->indicator_result_id = '00000000-0000-0000-0000-000000000001';
    $report->fill([
        'key_achievements' => 'Achievement narrative.',
        'variance_explanation' => 'Variance narrative.',
        'means_of_verification_notes' => 'MOV narrative.',
        'overall_assessment' => 'Overall assessment.',
        'performance_rating' => 'on_track',
        'conclusion' => 'Conclusion.',
        'challenges_faced' => 'Challenges.',
        'mitigation_strategies' => 'Mitigation.',
        'lessons_learned' => 'Lessons.',
        'adaptive_management_actions' => 'Adaptive actions.',
        'next_period_priorities' => 'Next-period priorities.',
    ]);
    $report->setRelation('documents', new Collection([
        new MePerformanceReportDocument(['document_name' => 'Signed evidence']),
    ]));

    expect($report->isSubmissionReady())->toBeTrue()
        ->and(collect($report->sectionCompletion())->pluck('status')->unique()->all())
        ->toBe(['complete'])
        ->and($report->submissionIssues())->toBe([]);
});

it('renders every requested report section and component linkage control', function () {
    $create = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/me/performance-reports/create.blade.php'
    );
    $edit = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/me/performance-reports/edit.blade.php'
    );
    $templateRegister = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/me/data-entry/index.blade.php'
    );
    $routes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');
    $completionSummary = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/me/performance-reports/partials/completion-summary.blade.php'
    );
    $lifecycleActions = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/me/performance-reports/partials/lifecycle-actions.blade.php'
    );

    expect($templateRegister)
        ->toContain('Project Component')
        ->toContain('data-component-directorate')
        ->toContain('Performance Reports');

    expect($create)
        ->toContain('name="reporting_quarter"')
        ->toContain('name="reporting_year"')
        ->toContain('Create Report')
        ->toContain('Responsible Directorate');

    foreach ([
        'Indicator results and progress against target',
        'Key achievements',
        'Explanation of variance from targets',
        'Means of Verification (MOV) notes',
        'Overall assessment',
        'Performance rating',
        'Conclusion',
        'Challenges faced',
        'Mitigation strategies',
        'Lessons learned',
        'Adaptive management actions',
        'Priorities or plans for the next reporting period',
    ] as $requiredSection) {
        expect($edit)->toContain($requiredSection);
    }

    expect($edit)
        ->toContain("route('budget.me.rebuild.knowledge-repository'")
        ->toContain('name="documents[]"')
        ->toContain('sectionCompletion')
        ->toContain('Lifecycle history');

    expect($completionSummary)
        ->toContain('Mandatory section check')
        ->toContain('Complete all red and amber sections')
        ->toContain('completion-item--');

    expect($lifecycleActions)
        ->toContain('Submit Report')
        ->toContain('Return Report')
        ->toContain('Approve Report')
        ->toContain('Archive Report')
        ->toContain('lifecycle-action--submit')
        ->toContain('lifecycle-action--return')
        ->toContain('lifecycle-action--approve')
        ->toContain('lifecycle-action--archive')
        ->toContain('@disabled(!$submissionReady)');

    expect($routes)
        ->toContain("Route::prefix('me/data-entry/performance-reports')")
        ->toContain("Route::post('{report}/submit', 'submit')")
        ->toContain("Route::post('{report}/review', 'review')")
        ->toContain("Route::post('{report}/archive', 'archive')");
});

it('enforces the four-stage lifecycle across author and Secretariat roles', function () {
    $portalIndex = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/think-tank/me-performance-reports/index.blade.php'
    );
    $portalEditor = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/think-tank/me-performance-reports/edit.blade.php'
    );
    $portalController = file_get_contents(
        dirname(__DIR__, 2).'/app/Http/Controllers/ThinkTankPerformanceReportController.php'
    );
    $internalController = file_get_contents(
        dirname(__DIR__, 2).'/app/Http/Controllers/MePerformanceReportController.php'
    );
    $reportModel = file_get_contents(
        dirname(__DIR__, 2).'/app/Models/MePerformanceReport.php'
    );
    $userModel = file_get_contents(dirname(__DIR__, 2).'/app/Models/User.php');

    expect($portalIndex)
        ->toContain('Draft Reports')
        ->toContain('Submitted Reports')
        ->toContain('Reviewed Reports')
        ->toContain('Archived Reports')
        ->toContain('Only forms assigned to this organization are available');

    expect($portalEditor)
        ->toContain('Save Draft')
        ->toContain('completion-summary')
        ->toContain('lifecycle-actions')
        ->toContain('Only draft reports may be edited')
        ->toContain('Lifecycle history');

    expect($portalController)
        ->toContain("where('think_tank_member_id', \$member->id)")
        ->toContain('assertOwnedReport($report, $member)')
        ->toContain("can('think_tank.me.reports.manage')")
        ->toContain("can('think_tank.me.reports.submit')");

    expect($internalController)
        ->toContain("permission:me.performance_reports.review")
        ->toContain("permission:me.performance_reports.archive")
        ->toContain('STATUS_REVIEWED')
        ->toContain('STATUS_ARCHIVED')
        ->toContain('reviewed_and_approved')
        ->toContain('recordTransition(');

    expect($reportModel)
        ->toContain('sectionCompletion')
        ->toContain('submissionIssues')
        ->toContain('isSubmissionReady');

    expect($userModel)
        ->toContain("'think_tank.me.reports.view'")
        ->toContain("'think_tank.me.reports.manage'")
        ->toContain("'think_tank.me.reports.submit'");
});
