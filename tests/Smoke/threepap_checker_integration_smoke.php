<?php

use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Services\ProcurementSubmissionScreeningService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$testToken = '3pap_smoke_token_that_must_never_be_rendered';
config([
    'services.threepap_checker.base_url' => 'https://checker.3pap.africa/api/v1',
    'services.threepap_checker.api_token' => $testToken,
    'services.threepap_checker.timeout' => 20,
    'services.threepap_checker.connect_timeout' => 5,
]);

Http::preventStrayRequests();
Http::fake([
    'https://checker.3pap.africa/api/v1/usage' => Http::sequence()
        ->push([
            'success' => true,
            'plan' => 'Professional',
            'token' => [
                'scopes' => ['sanctions_search', 'usage_billing'],
            ],
            'usage' => [
                'sanctions_search' => [
                    'used' => 12,
                    'limit' => 500,
                    'remaining' => 488,
                ],
            ],
        ])
        ->push([
            'success' => true,
            'plan' => 'Professional',
            'token' => ['scopes' => ['sanctions_search']],
            'usage' => [
                'sanctions_search' => ['used' => 12, 'limit' => 500, 'remaining' => 488],
            ],
        ]),
    'https://checker.3pap.africa/api/v1/sanctions/screen' => Http::sequence()
        ->push([
            'success' => true,
            'query' => 'Smoke Test Consulting Ltd',
            'total_matches' => 1,
            'risk_level' => 'high',
            'results' => [[
                'id' => 156,
                'name' => 'Smoke Test Consulting Limited',
                'country' => 'Kenya',
                'dataset' => 'World Bank',
                'program' => 'Debarment',
                'match_score' => 0.956,
                'source_url' => 'https://example.test/sanction/156',
            ]],
        ])
        ->push([
            'success' => 'true',
            'total_matches' => 0,
            'risk_level' => 'clear',
            'results' => [],
        ])
        ->push([
            'success' => true,
            'total_matches' => 0,
            'results' => [],
        ])
        ->push([
            'success' => true,
            'total_matches' => 0,
            'risk_level' => 'unknown',
            'results' => [],
        ])
        ->push([
            'success' => false,
            'error' => 'Token lacks the required scope.',
            'code' => 'INSUFFICIENT_SCOPE',
        ], 403)
        ->pushFailedConnection('3PAP smoke connection failure'),
]);
DB::beginTransaction();

try {
    $submission = FormSubmission::create([
        'status' => FormSubmission::STATUS_SUBMITTED,
        'submitted_at' => now(),
    ]);
    FormSubmissionValue::create([
        'submission_id' => $submission->getKey(),
        'field_key' => 'official_name',
        'value' => 'Smoke Test Consulting Ltd',
    ]);
    FormSubmissionValue::create([
        'submission_id' => $submission->getKey(),
        'field_key' => 'country',
        'value' => 'Kenya',
    ]);

    $status = app(ProcurementSubmissionScreeningService::class)->accountStatus();
    $assert($status['ok'], 'A valid 3PAP account was not accepted.');
    $assert($status['authenticated'], '3PAP account authentication was not recorded.');
    $assert($status['scope_enabled'], 'The sanctions_search scope was not detected.');
    $assert($status['usage']['remaining'] === 488, 'The remaining sanctions quota was not normalized.');

    Http::assertSent(function (Request $request) use ($testToken): bool {
        return $request->url() === 'https://checker.3pap.africa/api/v1/usage'
            && $request->hasHeader('Authorization', 'Bearer '.$testToken);
    });

    $assert(Artisan::call('threepap:verify') === 0, 'The safe 3PAP verification command failed.');
    $assert(
        ! str_contains(Artisan::output(), $testToken),
        'The 3PAP verification command exposed the configured token.'
    );

    $screening = app(ProcurementSubmissionScreeningService::class)->screenSubmission(
        $submission->fresh(['values', 'submitter'])
    );
    $assert($screening->request_status === 'success', 'A successful 3PAP response was stored as a failure.');
    $assert($screening->provider === '3pap', 'The screening provider was not identified as 3PAP.');
    $assert($screening->risk_level === 'high', 'The 3PAP risk level was not stored.');
    $assert($screening->total_matches === 1, 'The 3PAP match count was not stored.');
    $assert($screening->is_flagged, 'A high-risk 3PAP match was not flagged.');
    $assert(
        data_get($screening->response_payload, 'matches.0.dataset') === 'World Bank',
        'The normalized 3PAP match was not retained for the report.'
    );

    Http::assertSent(function (Request $request) use ($testToken): bool {
        return $request->url() === 'https://checker.3pap.africa/api/v1/sanctions/screen'
            && $request->hasHeader('Authorization', 'Bearer '.$testToken)
            && $request['name'] === 'Smoke Test Consulting Ltd'
            && $request['country'] === 'Kenya'
            && $request['max_results'] === 10;
    });

    $screening->update([
        'review_decision' => 'fit',
        'review_notes' => 'This decision must be cleared by a re-run.',
        'reviewed_at' => now(),
    ]);

    $malformedSuccess = app(ProcurementSubmissionScreeningService::class)->screenSubmission(
        $submission->fresh(['values', 'submitter'])
    );
    $assert($malformedSuccess->request_status === 'error', 'A non-boolean success flag was accepted.');
    $assert($malformedSuccess->risk_level === null, 'A non-boolean success flag produced a clear result.');

    $incomplete = app(ProcurementSubmissionScreeningService::class)->screenSubmission(
        $submission->fresh(['values', 'submitter'])
    );
    $assert($incomplete->request_status === 'error', 'An incomplete 3PAP response was accepted.');
    $assert($incomplete->risk_level === null, 'A missing risk classification defaulted to clear.');

    $unknownRisk = app(ProcurementSubmissionScreeningService::class)->screenSubmission(
        $submission->fresh(['values', 'submitter'])
    );
    $assert($unknownRisk->request_status === 'error', 'An unknown 3PAP risk classification was accepted.');
    $assert($unknownRisk->risk_level === null, 'An unknown risk classification defaulted to clear.');
    $assert(
        data_get($unknownRisk->response_payload, 'raw.code') === 'INVALID_PROVIDER_RESPONSE',
        'An invalid provider response was not retained as an auditable error.'
    );

    $failed = app(ProcurementSubmissionScreeningService::class)->screenSubmission(
        $submission->fresh(['values', 'submitter'])
    );
    $assert($failed->request_status === 'error', 'A 3PAP authorization error was not stored safely.');
    $assert(
        str_contains((string) $failed->error_message, 'INSUFFICIENT_SCOPE'),
        'The machine-readable 3PAP error code was not retained.'
    );
    $assert($failed->review_decision === null, 'A stale fit decision survived a failed re-screening attempt.');
    $assert($failed->review_notes === null, 'Stale review notes survived a re-screening attempt.');

    $unreachable = app(ProcurementSubmissionScreeningService::class)->screenSubmission(
        $submission->fresh(['values', 'submitter'])
    );
    $assert($unreachable->request_status === 'error', 'A 3PAP connection failure escaped the workflow.');
    $assert(
        $unreachable->error_message === 'The 3PAP response was not received, so the request outcome is unknown. Verify 3PAP usage before re-running.',
        'A 3PAP connection failure did not use the safe operator message.'
    );
    $assert(
        ! str_contains((string) json_encode($unreachable->response_payload), $testToken),
        'A stored 3PAP failure payload exposed the configured token.'
    );

    echo "THREEPAP_CHECKER_INTEGRATION_SMOKE_OK\n";
} finally {
    DB::rollBack();
}
