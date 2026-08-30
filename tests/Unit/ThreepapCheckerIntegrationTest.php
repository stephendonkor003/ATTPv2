<?php

function threepapIntegrationSources(): array
{
    $root = dirname(__DIR__, 2);

    return [
        'config' => file_get_contents($root.'/config/services.php'),
        'service' => file_get_contents($root.'/app/Services/ProcurementSubmissionScreeningService.php'),
        'command' => file_get_contents($root.'/app/Console/Commands/VerifyThreepapChecker.php'),
        'controller' => file_get_contents(
            $root.'/app/Http/Controllers/Procurement/ProcurementSubmissionController.php'
        ),
        'documentation' => file_get_contents($root.'/docs/threepap-checker-integration.md'),
    ];
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

it('fails safely and invalidates stale human decisions when an applicant is re-screened', function () {
    $service = threepapIntegrationSources()['service'];

    expect($service)
        ->toContain('The 3PAP screening service could not be reached. Try again later.')
        ->toContain("if (! (\$responsePayload['success'] ?? false))")
        ->toContain("if (! (\$result['success'] ?? false))")
        ->toContain("'review_decision' => null")
        ->toContain("'review_notes' => null")
        ->toContain("'reviewed_by' => null")
        ->toContain("'reviewed_at' => null")
        ->toContain("['clear', 'low', 'medium', 'high', 'critical']");
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
