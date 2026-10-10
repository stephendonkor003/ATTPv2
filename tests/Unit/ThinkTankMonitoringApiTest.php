<?php

use App\Exceptions\ThinkTankApiException;
use App\Http\Controllers\Api\V1\ThinkTank\MonitoringAssignmentsController;
use App\Http\Controllers\Api\V1\ThinkTank\MonitoringReportsController;
use App\Models\IndicatorTarget;
use App\Models\MeDataSubmission;
use App\Models\MePerformanceReport;
use App\Models\MeReportingPeriod;
use App\Services\ThinkTankMeAssignmentService;
use App\Services\ThinkTankMonitoringApiService;
use App\Services\ThinkTankMonitoringResultsScopeService;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function bootThinkTankMonitoringApiApplication(): array
{
    if (Container::getInstance()->bound(Kernel::class)) {
        return [Container::getInstance(), false];
    }

    $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();

    return [$application, true];
}

it('issues stable opaque lock and attachment tokens and rejects stale writes', function () {
    [, $bootedHere] = bootThinkTankMonitoringApiApplication();
    $originalKey = config('app.key');
    config(['app.key' => 'base64:monitoring-api-test-key']);

    try {
        $api = new ThinkTankMonitoringApiService;
        $submission = (new MeDataSubmission)->forceFill([
            'id' => '10000000-0000-4000-8000-000000000001',
            'portal_lock_version' => 1,
            'updated_at' => '2026-09-30 08:00:00',
        ]);
        $token = $api->lockToken($submission);
        $submission->updated_at = '2026-09-30 09:00:00';
        $sameVersionToken = $api->lockToken($submission);
        $submission->portal_lock_version = 2;
        $nextToken = $api->lockToken($submission);
        $legacyFile = ['path' => 'me-data/submissions/submission/field/file.pdf'];
        $attachment = $api->attachmentId(
            $legacyFile,
            '10000000-0000-4000-8000-000000000002',
            '10000000-0000-4000-8000-000000000003',
        );

        expect($token)->toHaveLength(64)
            ->and($sameVersionToken)->toBe($token)
            ->and($nextToken)->not->toBe($token)
            ->and(fn () => $api->assertLockToken($submission, $token))->toThrow(ThinkTankApiException::class)
            ->and(fn () => $api->assertLockToken($submission, $nextToken))->not->toThrow(Throwable::class)
            ->and(fn () => $api->assertLockToken($submission, str_repeat('0', 64)))
            ->toThrow(ThinkTankApiException::class)
            ->and(Str::isUuid($attachment))->toBeTrue()
            ->and($api->attachmentId(
                $legacyFile,
                '10000000-0000-4000-8000-000000000002',
                '10000000-0000-4000-8000-000000000003',
            ))->toBe($attachment);
    } finally {
        config(['app.key' => $originalKey]);

        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('defines a reversible lock version and advances it once per existing record mutation', function () {
    $root = dirname(__DIR__, 2);
    $migration = file_get_contents(
        $root.'/database/migrations/2026_09_30_000001_add_portal_lock_versions_to_me_monitoring_records.php'
    );
    $service = file_get_contents($root.'/app/Services/ThinkTankMonitoringApiService.php');
    $assignments = file_get_contents($root.'/app/Http/Controllers/Api/V1/ThinkTank/MonitoringAssignmentsController.php');
    $reports = file_get_contents($root.'/app/Http/Controllers/Api/V1/ThinkTank/MonitoringReportsController.php');
    $achievements = file_get_contents($root.'/app/Http/Controllers/Api/V1/ThinkTank/MonitoringReportAchievementsController.php');

    expect((new MeDataSubmission)->getCasts()['portal_lock_version'])->toBe('integer')
        ->and((new MePerformanceReport)->getCasts()['portal_lock_version'])->toBe('integer')
        ->and(substr_count($migration, "unsignedBigInteger('portal_lock_version')->default(1)"))->toBe(2)
        ->and(substr_count($migration, "Schema::hasColumn('me_data_submissions', 'portal_lock_version')"))->toBe(2)
        ->and(substr_count($migration, "Schema::hasColumn('me_performance_reports', 'portal_lock_version')"))->toBe(2)
        ->and(substr_count($migration, "dropColumn('portal_lock_version')"))->toBe(2)
        ->and($service)->toContain("getAttribute('portal_lock_version')")
        ->toContain("increment('portal_lock_version')")
        ->and(substr_count($assignments, 'incrementLockVersion($submission)'))->toBe(2)
        ->and(substr_count($reports, 'incrementLockVersion($locked)'))->toBe(3)
        ->and(substr_count($achievements, 'incrementLockVersion($locked)'))->toBe(1)
        ->and($reports)->not->toContain('$locked->touch()')
        ->and($achievements)->not->toContain('$locked->touch()');
});

it('maps legacy notification links only to tenant portal monitoring destinations', function () {
    $api = new ThinkTankMonitoringApiService;
    $assignment = '10000000-0000-4000-8000-000000000010';
    $report = '10000000-0000-4000-8000-000000000011';

    expect($api->notificationDestination('https://backend.test/think-tank/me-data/'.$assignment))
        ->toBe('/monitoring/assignments/'.$assignment)
        ->and($api->notificationDestination('https://backend.test/think-tank/performance-reports/'.$report.'/edit'))
        ->toBe('/monitoring/reports/'.$report)
        ->and($api->notificationDestination('https://evil.test/steal'))
        ->toBe('/monitoring/notifications')
        ->and($api->notificationDestination('javascript:alert(1)'))
        ->toBe('/monitoring/notifications');
});

it('keeps reporting periods canonical and resolves tenant targets before project targets', function () {
    $service = new ThinkTankMonitoringResultsScopeService;
    $periods = collect([
        (new MeReportingPeriod)->forceFill([
            'id' => '10000000-0000-4000-8000-000000000020',
            'reporting_year' => 2026,
        ]),
        (new MeReportingPeriod)->forceFill([
            'id' => '10000000-0000-4000-8000-000000000021',
            'reporting_year' => 2027,
        ]),
        (new MeReportingPeriod)->forceFill([
            'id' => '10000000-0000-4000-8000-000000000022',
            'reporting_year' => null,
            'period_start' => '2028-01-01',
        ]),
    ]);
    $reportingContext = new ReflectionMethod($service, 'reportingYearContext');
    $applicableTargets = new ReflectionMethod($service, 'applicableTargets');
    $targetYear = new ReflectionMethod($service, 'targetYearForReportingYear');
    $dominantYear = new ReflectionMethod($service, 'dominantYear');
    $memberId = '10000000-0000-4000-8000-000000000030';
    $targets = collect([
        new IndicatorTarget([
            'indicator_id' => 'indicator-a', 'target_scope' => 'project',
            'project_year' => 4, 'reporting_year' => 2026, 'revision' => 99,
        ]),
        new IndicatorTarget([
            'indicator_id' => 'indicator-a', 'target_scope' => 'think_tank',
            'think_tank_member_id' => $memberId, 'project_year' => 2,
            'reporting_year' => 2026, 'revision' => 1,
        ]),
        new IndicatorTarget([
            'indicator_id' => 'indicator-b', 'target_scope' => 'project',
            'project_year' => 3, 'reporting_year' => 2026, 'revision' => 1,
        ]),
        new IndicatorTarget([
            'indicator_id' => 'indicator-c', 'target_scope' => 'project',
            'project_year' => 4, 'reporting_year' => 2026, 'revision' => 99,
        ]),
        new IndicatorTarget([
            'indicator_id' => 'indicator-c', 'target_scope' => 'think_tank',
            'think_tank_member_id' => $memberId, 'project_year' => 2,
            'reporting_year' => 2026, 'revision' => 1,
        ]),
    ]);

    $applicable = $applicableTargets->invoke($service, $targets, $memberId);

    expect($reportingContext->invoke(
        $service,
        $periods,
        2026,
        '10000000-0000-4000-8000-000000000020',
    ))->toBe([2026, 2026])
        ->and($reportingContext->invoke($service, $periods, null, null))->toBe([null, 2027])
        ->and($reportingContext->invoke(
            $service,
            $periods,
            null,
            '10000000-0000-4000-8000-000000000022',
        ))->toBe([null, 2028])
        ->and(fn () => $reportingContext->invoke(
            $service,
            $periods,
            2025,
            '10000000-0000-4000-8000-000000000020',
        ))->toThrow(ValidationException::class)
        ->and(fn () => $reportingContext->invoke(
            $service,
            $periods,
            2028,
            '10000000-0000-4000-8000-000000000022',
        ))->toThrow(ValidationException::class)
        ->and($applicable->get('indicator-a')->pluck('target_scope')->all())->toBe(['think_tank'])
        ->and($applicable->get('indicator-c')->pluck('target_scope')->all())->toBe(['think_tank'])
        ->and($targetYear->invoke($service, $applicable, 2026))->toBe(2)
        ->and($dominantYear->invoke($service, collect([2, 3])))->toBe(3);
});

it('keeps archived Think Tank results opt-in and distinguishes open from historical periods', function () {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents($root.'/app/Http/Controllers/Api/V1/ThinkTank/MonitoringResultsController.php');
    $scope = file_get_contents($root.'/app/Services/ThinkTankMonitoringResultsScopeService.php');
    $api = file_get_contents($root.'/app/Services/ThinkTankMonitoringApiService.php');
    $activePeriod = (new MeReportingPeriod)->forceFill([
        'status' => MeReportingPeriod::STATUS_ACTIVE,
        'lifecycle_status' => MeReportingPeriod::LIFECYCLE_OPEN,
    ]);
    $legacyOpenPeriod = (new MeReportingPeriod)->forceFill([
        'status' => MeReportingPeriod::STATUS_ACTIVE,
        'lifecycle_status' => null,
    ]);
    $historicalPeriod = (new MeReportingPeriod)->forceFill([
        'status' => MeReportingPeriod::STATUS_CLOSED,
        'lifecycle_status' => MeReportingPeriod::LIFECYCLE_CLOSED,
    ]);

    expect($activePeriod->isActive())->toBeTrue()
        ->and($legacyOpenPeriod->isActive())->toBeTrue()
        ->and($historicalPeriod->isActive())->toBeFalse()
        ->and($controller)->toContain("'include_archived'")
        ->toContain('FILTER_VALIDATE_BOOLEAN')
        ->and($scope)->toContain('$includeArchived = false')
        ->toContain('$period->isActive()')
        ->toContain('! $includeArchived && ! $selectedPeriod->isActive()')
        ->and($api)->toContain('includeArchived')
        ->toContain('include_archived')
        ->toContain('historical');
});

it('enforces the declared json and multipart mutation transports', function () {
    $api = new ThinkTankMonitoringApiService;
    $reports = new MonitoringReportsController($api);
    $assignments = new MonitoringAssignmentsController(new ThinkTankMeAssignmentService, $api);
    $jsonGuard = new ReflectionMethod($reports, 'assertJsonObject');
    $multipartGuard = new ReflectionMethod($assignments, 'assertMultipart');
    $validJson = Request::create('/reports', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], '{}');
    $jsonArray = Request::create('/reports', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], '[]');
    $multipart = Request::create('/assignments/id/draft', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'multipart/form-data; boundary=attp-test',
    ]);
    $wrongTransport = Request::create('/assignments/id/draft', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], '{}');

    expect(fn () => $jsonGuard->invoke($reports, $validJson))->not->toThrow(Throwable::class)
        ->and(fn () => $jsonGuard->invoke($reports, $jsonArray))->toThrow(ThinkTankApiException::class)
        ->and(fn () => $multipartGuard->invoke($assignments, $multipart))->not->toThrow(Throwable::class)
        ->and(fn () => $multipartGuard->invoke($assignments, $wrongTransport))->toThrow(ThinkTankApiException::class);
});

it('keeps every monitoring route behind the stateful ready M and E boundary', function () {
    [$application, $bootedHere] = bootThinkTankMonitoringApiApplication();

    try {
        /** @var Router $router */
        $router = $application->make(Router::class);
        $routes = collect($router->getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/think-tank/monitoring'));

        expect($routes)->toHaveCount(25);
        $routes->each(function ($route): void {
            $middleware = $route->gatherMiddleware();
            expect($middleware)
                ->toContain('think.tank.api.no-store')
                ->toContain('think.tank.api.stateful')
                ->toContain('auth:sanctum')
                ->toContain('think.tank.api.account')
                ->toContain('think.tank.api.ready')
                ->toContain('think.tank.area:me');
            expect(collect($middleware)->contains(
                fn (string $item): bool => str_starts_with($item, 'permission:think_tank.me.')
            ))->toBeTrue();
        });
    } finally {
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('serializes tenant scoping content types privacy and forced achievement ownership in source', function () {
    $root = dirname(__DIR__, 2);
    $assignments = file_get_contents($root.'/app/Http/Controllers/Api/V1/ThinkTank/MonitoringAssignmentsController.php');
    $reports = file_get_contents($root.'/app/Http/Controllers/Api/V1/ThinkTank/MonitoringReportsController.php');
    $achievements = file_get_contents($root.'/app/Http/Controllers/Api/V1/ThinkTank/MonitoringReportAchievementsController.php');
    $results = file_get_contents($root.'/app/Http/Controllers/Api/V1/ThinkTank/MonitoringResultsController.php');
    $overview = file_get_contents($root.'/app/Http/Controllers/Api/V1/ThinkTank/MonitoringOverviewController.php');
    $resultsScope = file_get_contents($root.'/app/Services/ThinkTankMonitoringResultsScopeService.php');
    $api = file_get_contents($root.'/app/Services/ThinkTankMonitoringApiService.php');
    $legacyAchievement = file_get_contents($root.'/app/Http/Controllers/MeIndicatorAchievementController.php');
    $legacyReports = file_get_contents($root.'/app/Http/Controllers/ThinkTankPerformanceReportController.php');
    $baseReports = file_get_contents($root.'/app/Http/Controllers/MePerformanceReportController.php');

    expect($assignments)
        ->toContain("where('think_tank_member_id', \$member->id)")
        ->toContain('MULTIPART_BODY_REQUIRED')
        ->toContain('whereKey($assignment)')
        ->and($reports)
        ->toContain("where('think_tank_member_id', \$member->id)")
        ->toContain('JSON_BODY_REQUIRED')
        ->toContain('MULTIPART_BODY_REQUIRED')
        ->and($achievements)
        ->toContain("where('think_tank_member_id', \$member->id)")
        ->toContain("where('report_id', \$ownedReport->id)")
        ->and($results)
        ->toContain('$results->build($filters, (string) $member->id)')
        ->toContain('$scope = $resultsScope->resolve(')
        ->toContain("'indicator_ids' => \$scope['indicatorIds']")
        ->not->toContain("'project_year' => (int) (\$validated['project_year'] ?? 1)")
        ->and($resultsScope)
        ->toContain('MeFramework::query()->current()->value(')
        ->toContain("where('is_active', true)")
        ->toContain("where('approval_status', 'approved')")
        ->toContain("where('think_tank_member_id', \$member->id)")
        ->toContain('applicableTargets($targets, (string) $member->id)')
        ->toContain('The reporting year must match the selected reporting period.')
        ->toContain('The selected project year is outside the available reporting cycle.')
        ->and($api)
        ->toContain("'projectYears' => collect(\$filters['project_year_options'] ?? [1, 2, 3, 4])")
        ->and($overview)
        ->toContain('if ($canViewAssignments)')
        ->toContain('if ($canViewReports)')
        ->toContain('if ($canViewNotifications)')
        ->and($legacyAchievement)
        ->toContain("\$validated['lead_think_tank_member_id'] = (string) \$report->think_tank_member_id")
        ->and($legacyReports)
        ->toContain("\$data['activeThinkTanks'] = ConsortiumThinkTank::query()")
        ->toContain('->whereKey($member->id)')
        ->toContain("\$data['canSubmit'] = \$this->canSubmit(\$request)")
        ->and($baseReports)
        ->toContain('userMaySubmitReport($request, $report)')
        ->toContain("can('think_tank.me.reports.submit')");
});
