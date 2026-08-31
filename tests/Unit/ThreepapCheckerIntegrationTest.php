<?php

function threepapIntegrationSources(): array
{
    $root = dirname(__DIR__, 2);

    return [
        'config' => file_get_contents($root.'/config/services.php'),
        'service' => file_get_contents($root.'/app/Services/ProcurementSubmissionScreeningService.php'),
        'automation' => file_get_contents($root.'/app/Services/ProcurementSubmissionScreeningAutomation.php'),
        'job' => file_get_contents($root.'/app/Jobs/ScreenProcurementSubmission.php'),
        'command' => file_get_contents($root.'/app/Console/Commands/VerifyThreepapChecker.php'),
        'pending_command' => file_get_contents(
            $root.'/app/Console/Commands/QueuePendingThreepapScreenings.php'
        ),
        'controller' => file_get_contents(
            $root.'/app/Http/Controllers/Procurement/ProcurementSubmissionController.php'
        ),
        'public_controller' => file_get_contents(
            $root.'/app/Http/Controllers/Procurement/PublicProcurementController.php'
        ),
        'vendor_controller' => file_get_contents(
            $root.'/app/Http/Controllers/Vendor/VendorProcurementController.php'
        ),
        'vendor_portal_controller' => file_get_contents(
            $root.'/app/Http/Controllers/Vendor/VendorPortalController.php'
        ),
        'form_submission_controller' => file_get_contents(
            $root.'/app/Http/Controllers/Procurement/FormSubmissionController.php'
        ),
        'schedule' => file_get_contents($root.'/bootstrap/app.php'),
        'automation_migration' => file_get_contents(
            $root.'/database/migrations/2026_08_30_000001_add_automation_fields_to_procurement_submission_screenings_table.php'
        ),
        'documentation' => file_get_contents($root.'/docs/threepap-checker-integration.md'),
    ];
}

function bootThreepapIntegrationApplication(): array
{
    if (Illuminate\Container\Container::getInstance()->bound(
        Illuminate\Contracts\Console\Kernel::class,
    )) {
        return [Illuminate\Container\Container::getInstance(), false];
    }

    $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $application->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    return [$application, true];
}

it('uses the documented 3PAP bearer endpoints and bounded request settings', function () {
    $sources = threepapIntegrationSources();

    expect($sources['config'])
        ->toContain("'base_url' => env('THREEPAP_CHECKER_BASE_URL', 'https://checker.3pap.africa/api/v1')")
        ->toContain("'api_token' => env('THREEPAP_CHECKER_API_TOKEN')")
        ->toContain("'connect_timeout' => (int) env('THREEPAP_CHECKER_CONNECT_TIMEOUT', 5)")
        ->toContain("'ca_bundle' => env('THREEPAP_CHECKER_CA_BUNDLE')")
        ->and($sources['service'])
        ->toContain('->withToken((string) $config[\'api_token\'])')
        ->toContain("->withOptions(['verify' => \$caBundle])")
        ->toContain('CaBundle::getSystemCaRootBundlePath()')
        ->not->toContain('withoutVerifying')
        ->toContain("->get('/usage')")
        ->toContain("->post('/sanctions/screen'")
        ->toContain("->post('/sanctions/batch'")
        ->toContain('foreach ($items->chunk(50) as $chunk)')
        ->toContain("in_array('sanctions_search', \$scopes, true)");
});

it('runs automatic screening through encrypted unique queue jobs and scheduled recovery', function () {
    $sources = threepapIntegrationSources();

    $jobImplementsQueueContract = preg_match(
        '/class\s+ScreenProcurementSubmission\s+implements'
            .'(?=[^{]*\bShouldQueue\b)'
            .'(?=[^{]*\bShouldBeUnique\b)'
            .'(?=[^{]*\bShouldBeEncrypted\b)[^{]*\{/s',
        $sources['job']
    );

    expect($jobImplementsQueueContract)->toBe(1)
        ->and($sources['job'])
        ->toContain('function uniqueId(): string')
        ->toContain('ProcurementSubmissionScreeningAutomation')
        ->and($sources['automation'])
        ->toContain('class ProcurementSubmissionScreeningAutomation')
        ->toContain('function queueSubmission(')
        ->toContain('function recoverPending(')
        ->toContain('new UniqueLock($job->uniqueVia())')
        ->toContain('$uniqueLock->acquire($job)')
        ->toContain('Bus::dispatch($job)')
        ->toContain('function leaseRecoveryRun(')
        ->toContain('json_encode($payload, JSON_THROW_ON_ERROR)')
        ->and($sources['job'])
        ->toContain('function uniqueVia(): CacheRepository')
        ->and($sources['service'])
        ->toContain("DB::raw('attempt_count + 1')")
        ->and($sources['pending_command'])
        ->toContain("protected \$signature = 'threepap:screen-pending")
        ->toContain('->recoverPending(')
        ->and($sources['schedule'])
        ->toContain('threepap:screen-pending')
        ->toContain('->withoutOverlapping()')
        ->and($sources['service'])
        ->not->toContain('app()->terminating');

    foreach ([
        'public_controller',
        'vendor_controller',
        'vendor_portal_controller',
        'form_submission_controller',
    ] as $controller) {
        expect($sources[$controller])
            ->toContain('ProcurementSubmissionScreeningAutomation')
            ->toContain('->queueSubmission(');
    }
});

it('publishes each screening run only once while its unique lock is held', function () {
    [$application, $bootedHere] = bootThreepapIntegrationApplication();
    Illuminate\Support\Facades\Queue::fake();

    $submissionId = (string) Illuminate\Support\Str::uuid();
    $runToken = (string) Illuminate\Support\Str::uuid();
    $job = new App\Jobs\ScreenProcurementSubmission($submissionId, $runToken);
    $lock = new Illuminate\Bus\UniqueLock($job->uniqueVia());
    $dispatch = new ReflectionMethod(
        App\Services\ProcurementSubmissionScreeningAutomation::class,
        'dispatchJob',
    );

    try {
        $automation = app(App\Services\ProcurementSubmissionScreeningAutomation::class);

        expect($dispatch->invoke($automation, $submissionId, $runToken))->toBeTrue()
            ->and($dispatch->invoke($automation, $submissionId, $runToken))->toBeTrue();

        Illuminate\Support\Facades\Queue::assertPushed(
            App\Jobs\ScreenProcurementSubmission::class,
            1,
        );
    } finally {
        $lock->release($job);

        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('fails safely and invalidates stale human decisions when an applicant is re-screened', function () {
    $service = threepapIntegrationSources()['service'];

    expect($service)
        ->toContain('The 3PAP screening service could not be reached. The system will retry automatically.')
        ->toContain('the request outcome is unknown. Verify 3PAP usage before re-running.')
        ->toContain("if ((\$responsePayload['success'] ?? null) === false)")
        ->toContain('$this->singleResponseValidationError($responsePayload)')
        ->toContain('No clearance was recorded.')
        ->toContain("if (! (\$result['success'] ?? false))")
        ->toContain("'review_decision' => null")
        ->toContain("'review_notes' => null")
        ->toContain("'reviewed_by' => null")
        ->toContain("'reviewed_at' => null")
        ->toContain("['clear', 'low', 'medium', 'high', 'critical']")
        ->not->toContain("\$riskLevel = 'clear'");
});

it('rejects incomplete or malformed single-screening success payloads', function () {
    $service = new App\Services\ProcurementSubmissionScreeningService;
    $validate = new ReflectionMethod($service, 'singleResponseValidationError');
    $validateBatch = new ReflectionMethod($service, 'batchResultValidationError');
    $valid = [
        'success' => true,
        'risk_level' => 'clear',
        'total_matches' => 0,
        'results' => [],
    ];

    expect($validate->invoke($service, $valid))->toBeNull();

    foreach ([
        [...$valid, 'success' => 'true'],
        [...$valid, 'risk_level' => 'unknown'],
        [...$valid, 'total_matches' => '0'],
        [...$valid, 'total_matches' => -1],
        [...$valid, 'results' => ['id' => 123]],
        [...$valid, 'total_matches' => 1],
        [...$valid, 'results' => [['id' => 123]]],
        [...$valid, 'risk_level' => 'high'],
        [...$valid, 'risk_level' => 'high', 'total_matches' => 1, 'results' => [[], []]],
        array_diff_key($valid, ['risk_level' => true]),
        array_diff_key($valid, ['total_matches' => true]),
        array_diff_key($valid, ['results' => true]),
    ] as $invalid) {
        expect($validate->invoke($service, $invalid))->not->toBeNull();
    }

    expect($validate->invoke($service, [
        ...$valid,
        'risk_level' => 'high',
        'total_matches' => 2,
        'results' => [['id' => 123]],
    ]))->toBeNull();

    expect($validateBatch->invoke($service, [
        'success' => true,
        'risk_level' => 'clear',
        'total_matches' => 0,
        'matches' => [],
    ]))->toBeNull()
        ->and($validateBatch->invoke($service, [
            'success' => true,
            'risk_level' => 'clear',
            'total_matches' => 1,
            'matches' => [['id' => 123]],
        ]))->not->toBeNull();
});

it('backfills UUID screening rows without offset skips and documents numeric failed-job IDs', function () {
    $sources = threepapIntegrationSources();

    expect($sources['automation_migration'])
        ->toContain('do {')
        ->toContain("->whereNull('run_token')")
        ->toContain("->pluck('id')")
        ->toContain('while ($screeningIds->isNotEmpty())')
        ->not->toContain('->chunk(250')
        ->and($sources['documentation'])
        ->toContain('numeric ID shown by `php artisan queue:failed`')
        ->toContain('php artisan queue:forget <failed-job-id>')
        ->not->toContain('failed-job-uuid');
});

it('keeps report navigation read-only and provides a token-safe account verification command', function () {
    $sources = threepapIntegrationSources();

    expect($sources['controller'])
        ->not->toContain("\$request->boolean('run')")
        ->and($sources['command'])
        ->toContain("protected \$signature = 'threepap:verify'")
        ->toContain('$screeningService->accountStatus()')
        ->toContain('No applicant was screened and no sanctions-search credit was consumed.')
        ->not->toContain('api_token')
        ->and($sources['documentation'])
        ->toContain('Store the credential in the deployment environment or secret manager.')
        ->toContain('commit it to Git or expose it in browser-side code.')
        ->toContain('The token itself is never printed by the verification command.')
        ->toContain('A risk match is evidence for human review, not an automatic exclusion.')
        ->toContain('TLS certificate verification is always enabled.');

    expect(preg_match('/3pap_[a-f0-9]{64}/i', $sources['documentation']))->toBe(0);
});
