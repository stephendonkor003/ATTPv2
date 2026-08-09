<?php

use App\Models\Indicator;
use App\Models\IndicatorResult;
use App\Models\IndicatorTarget;
use App\Models\MeDataSubmission;
use App\Models\MePerformanceReport;
use App\Models\MeReportingPeriod;
use App\Services\AttpMelFrameworkInstaller;
use App\Services\AttpMelResultsService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;

it('contains the exact official ATTP indicator set without inventing INTC2.6', function () {
    $method = new ReflectionMethod(AttpMelFrameworkInstaller::class, 'definitions');
    $definitions = collect($method->invoke(app(AttpMelFrameworkInstaller::class)));

    expect($definitions)->toHaveCount(18)
        ->and($definitions->pluck('code')->all())->not->toContain('INTC2.6')
        ->and($definitions->where('level', 'pdo'))->toHaveCount(5)
        ->and($definitions->firstWhere('code', 'PDO 3-CE')['targets'])
        ->toBe(['Y1' => 20, 'Y2' => 10, 'Y3' => 15, 'END' => 20])
        ->and($definitions->firstWhere('code', 'PDO 4')['calculation_configuration'])
        ->toMatchArray([
            'source_indicator_codes' => ['INTC2.5'],
            'achievement_filter' => ['lead_researcher_gender' => 'female'],
        ])
        ->and($definitions->firstWhere('code', 'PDO 1')['targets']['END'])
        ->toBe('PSC has prepared and approved an exit strategy outlining a sustainability mechanism.')
        ->and($definitions->firstWhere('code', 'INTC2.3')['operational_frequency_code'])
        ->toBe('SEMI_ANNUAL')
        ->and($definitions->firstWhere('code', 'INTC2.3')['reporting_frequency'])
        ->toBe('Annual')
        ->and($definitions->firstWhere('code', 'INTC2.11')['data_sources'])
        ->toContain('Annual policy community survey administered by the AUC');
});

it('ships complete idempotent Think Tank reporting instruments without turning the policy survey into a manual return', function () {
    $root = dirname(__DIR__, 2);
    $seeder = file_get_contents($root.'/database/seeders/AttpMelThinkTankReportingSeeder.php');
    $portal = file_get_contents($root.'/app/Http/Controllers/ThinkTankMeDataController.php');

    expect($seeder)
        ->toContain("'ATTP-TT-INTC2-3'")
        ->toContain("'ATTP-TT-INTC2-4'")
        ->toContain("'ATTP-TT-INTC2-5'")
        ->toContain("'ATTP-TT-INTC2-7'")
        ->toContain("'ATTP-TT-INTC2-8'")
        ->toContain("'ATTP-TT-INTC2-9'")
        ->toContain("'ATTP-TT-INTC2-10'")
        ->not->toContain("'ATTP-TT-INTC2-11'")
        ->toContain("'rollup_numerator_field_key' => 'female_staff_supported'")
        ->toContain("'rollup_denominator_field_key' => 'eligible_female_professional_staff'")
        ->toContain("where('status', 'active')")
        ->toContain('MeDataCollection::STATUS_DRAFT')
        ->and($portal)
        ->toContain('rollup_numerator_field_key')
        ->toContain('must equal numerator ÷ denominator × 100');
});

it('aggregates percentages with numerators and denominators and handles booleans', function () {
    $service = app(AttpMelResultsService::class);
    $aggregate = new ReflectionMethod($service, 'aggregate');

    $percentage = new Indicator(['value_type' => 'percentage', 'aggregation_method' => 'percentage']);
    $percentageResults = collect([
        new IndicatorResult(['actual_value' => 80, 'rollup_numerator' => 8, 'rollup_denominator' => 10]),
        new IndicatorResult(['actual_value' => 25, 'rollup_numerator' => 5, 'rollup_denominator' => 20]),
    ]);
    $boolean = new Indicator(['value_type' => 'boolean', 'aggregation_method' => 'latest']);

    expect($aggregate->invoke($service, $percentage, $percentageResults))->toBe(43.3333)
        ->and($aggregate->invoke($service, $boolean, collect([
            new IndicatorResult(['actual_value' => 0]),
            new IndicatorResult(['actual_value' => 1]),
        ])))->toBeTrue();
});

it('keeps an unreported boolean result missing instead of treating it as no', function () {
    $service = app(AttpMelResultsService::class);
    $aggregate = new ReflectionMethod($service, 'aggregate');
    $achievement = new ReflectionMethod($service, 'achievement');
    $indicator = new Indicator(['value_type' => 'boolean', 'aggregation_method' => 'latest']);

    $actual = $aggregate->invoke($service, $indicator, collect());

    expect($actual)->toBeNull()
        ->and($achievement->invoke($service, $indicator, $actual, null, 'Yes'))->toBeNull();
});

it('uses the chronologically latest approved value for latest aggregation', function () {
    $service = app(AttpMelResultsService::class);
    $aggregate = new ReflectionMethod($service, 'aggregate');
    $indicator = new Indicator(['value_type' => 'number', 'aggregation_method' => 'latest']);
    $newer = new IndicatorResult;
    $newer->setRawAttributes(['period_end' => '2026-06-30', 'approved_at' => '2026-07-02 10:00:00', 'actual_value' => 28], true);
    $older = new IndicatorResult;
    $older->setRawAttributes(['period_end' => '2026-03-31', 'approved_at' => '2026-04-02 10:00:00', 'actual_value' => 12], true);

    expect($aggregate->invoke($service, $indicator, collect([$newer, $older])))->toBe(28.0);
});

it('supports qualitative milestone results', function () {
    $service = app(AttpMelResultsService::class);
    $aggregate = new ReflectionMethod($service, 'aggregate');
    $indicator = new Indicator(['value_type' => 'milestone', 'aggregation_method' => 'non_additive']);
    $older = new IndicatorResult;
    $older->setRawAttributes(['actual_text' => 'Platform design approved', 'approved_at' => Carbon::parse('2026-01-01')], true);
    $newer = new IndicatorResult;
    $newer->setRawAttributes(['actual_text' => 'Platform established and operational', 'approved_at' => Carbon::parse('2026-07-01')], true);

    expect($aggregate->invoke($service, $indicator, collect([$older, $newer])))
        ->toBe('Platform established and operational');
});

it('calculates an approved period-over-period trend', function () {
    $service = app(AttpMelResultsService::class);
    $trend = new ReflectionMethod($service, 'trend');
    $indicator = new Indicator(['value_type' => 'number', 'aggregation_method' => 'sum']);
    $older = new IndicatorResult;
    $older->setRawAttributes([
        'reporting_period_id' => 'period-1',
        'period_end' => '2026-03-31',
        'actual_value' => 15,
    ], true);
    $newer = new IndicatorResult;
    $newer->setRawAttributes([
        'reporting_period_id' => 'period-2',
        'period_end' => '2026-06-30',
        'actual_value' => 20,
    ], true);

    expect($trend->invoke($service, $indicator, collect([$newer, $older])))
        ->toMatchArray([
            'direction' => 'up',
            'label' => '+5.00',
            'change' => 5.0,
            'current' => 20.0,
            'previous' => 15.0,
        ]);
});

it('selects the latest approved target revision without overwriting history', function () {
    $service = app(AttpMelResultsService::class);
    $targetFor = new ReflectionMethod($service, 'targetFor');
    $indicator = new Indicator;
    $indicator->setRelation('targets', new EloquentCollection([
        new IndicatorTarget(['target_scope' => 'project', 'project_year' => 2, 'target_value' => 12, 'revision' => 1, 'approval_status' => 'approved']),
        new IndicatorTarget(['target_scope' => 'project', 'project_year' => 2, 'target_value' => 15, 'revision' => 2, 'approval_status' => 'approved']),
        new IndicatorTarget(['target_scope' => 'project', 'project_year' => 2, 'target_value' => 99, 'revision' => 3, 'approval_status' => 'draft']),
    ]));

    $target = $targetFor->invoke($service, $indicator, 2, 2027, null);
    expect((float) $target->target_value)->toBe(15.0)
        ->and($target->revision)->toBe(2);
});

it('enforces reporting windows and editable correction states', function () {
    Carbon::setTestNow('2026-08-08 12:00:00');
    $period = new MeReportingPeriod;
    $period->setRawAttributes([
        'status' => MeReportingPeriod::STATUS_ACTIVE,
        'lifecycle_status' => MeReportingPeriod::LIFECYCLE_OPEN,
        'submission_opens_at' => Carbon::parse('2026-08-01 00:00:00'),
        'submission_deadline' => Carbon::parse('2026-08-31 23:59:59'),
    ], true);
    expect($period->isOpenForSubmission())->toBeTrue();
    $period->lifecycle_status = MeReportingPeriod::LIFECYCLE_CLOSED;
    expect($period->isOpenForSubmission())->toBeFalse();

    $submission = new MeDataSubmission(['workflow_status' => MeDataSubmission::STATUS_RETURNED]);
    expect($submission->isEditable())->toBeTrue()
        ->and($submission->canSubmit())->toBeTrue();
    $submission->workflow_status = MeDataSubmission::STATUS_APPROVED;
    expect($submission->isEditable())->toBeFalse();
    Carbon::setTestNow();
});

it('never treats reviewed or merely verified reports as official approval', function () {
    $report = new MePerformanceReport(['status' => MePerformanceReport::STATUS_REVIEWED]);
    expect($report->isApproved())->toBeFalse();
    $report->status = MePerformanceReport::STATUS_VERIFIED;
    expect($report->isApproved())->toBeFalse();
    $report->status = MePerformanceReport::STATUS_APPROVED;
    expect($report->isApproved())->toBeTrue();
    $report->status = MePerformanceReport::STATUS_ARCHIVED;
    expect($report->isApproved())->toBeTrue();
});

it('wires isolation permissions evidence review audit notifications and exports', function () {
    $root = dirname(__DIR__, 2);
    $routes = file_get_contents($root.'/routes/web.php');
    $portal = file_get_contents($root.'/app/Http/Controllers/ThinkTankMeDataController.php');
    $review = file_get_contents($root.'/app/Http/Controllers/MeSubmissionReviewController.php');
    $results = file_get_contents($root.'/app/Services/AttpMelResultsService.php');
    $dashboard = file_get_contents($root.'/resources/views/me/results-dashboard/index.blade.php');
    $dqa = file_get_contents($root.'/app/Services/MeDataQualityService.php');
    $audit = file_get_contents($root.'/app/Providers/AppServiceProvider.php');
    $scheduler = file_get_contents($root.'/bootstrap/app.php');

    expect($portal)
        ->toContain('resolvedThinkTankMembership()')
        ->toContain('assignmentForMember($assignment, $member)')
        ->toContain('isOpenForSubmission()')
        ->toContain('MeDataSubmissionVersion::query()')
        ->toContain('syncSubmissionEvidence(')
        ->and($review)
        ->toContain('Start review')
        ->toContain('Resolve all blocking data-quality findings')
        ->toContain('comments are required for a return or rejection')
        ->and($results)
        ->toContain('->approved()')
        ->toContain('deduplication_key')
        ->toContain('rollup_numerator')
        ->and($dashboard)
        ->toContain('component-performance-chart')
        ->toContain('performance-distribution-chart')
        ->toContain('indicator-attainment-chart')
        ->toContain('reporting-quality-chart')
        ->toContain("asset('admin/assets/vendors/js/apexcharts.min.js')")
        ->not->toContain('cdn.jsdelivr.net')
        ->and($dqa)
        ->toContain('required_evidence_missing')
        ->toContain('percentage_above_100')
        ->toContain('possible_duplicate_result')
        ->and($routes)
        ->toContain("Route::prefix('me/results-dashboard')")
        ->toContain("Route::prefix('me/submission-reviews')")
        ->toContain("Route::prefix('me/framework')")
        ->toContain("Route::get('export/excel', 'excel')")
        ->toContain("Route::get('export/csv', 'csv')")
        ->toContain("Route::get('export/pdf', 'pdf')")
        ->and($audit)->toContain("'MeDataSubmissionReview' => 'me'")
        ->and($scheduler)->toContain("command('me:send-reporting-reminders')");
});
