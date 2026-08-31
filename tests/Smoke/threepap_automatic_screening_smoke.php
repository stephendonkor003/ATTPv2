<?php

use App\Exceptions\TransientThreepapScreeningException;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Models\Procurement;
use App\Models\ProcurementSubmissionScreening;
use App\Services\ProcurementSubmissionScreeningAutomation;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Client\Request;
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

config([
    'services.threepap_checker.base_url' => 'https://checker.3pap.africa/api/v1',
    'services.threepap_checker.api_token' => 'threepap_automatic_smoke_token',
    'services.threepap_checker.automatic.enabled' => true,
]);

Http::preventStrayRequests();
Http::fake([
    'https://checker.3pap.africa/api/v1/sanctions/screen' => Http::sequence()
        ->push([
            'success' => false,
            'error' => 'Temporary provider outage.',
            'code' => 'TEMPORARY_FAILURE',
        ], 503)
        ->pushFailedConnection('cURL error 7: Failed to connect before an HTTP request was sent.')
        ->push([
            'success' => true,
            'total_matches' => 0,
            'risk_level' => 'clear',
            'results' => [],
        ])
        ->push([
            'success' => true,
            'total_matches' => 0,
            'risk_level' => 'clear',
            'results' => [],
        ])
        ->push([
            'success' => true,
            'total_matches' => 0,
            'risk_level' => 'clear',
            'results' => [],
        ]),
]);

DB::beginTransaction();

try {
    $procurement = Procurement::create([
        'title' => 'Automatic 3PAP Smoke Procurement',
        'reference_no' => '3PAP-SMOKE-'.strtoupper(bin2hex(random_bytes(4))),
        'status' => 'published',
    ]);
    $submission = FormSubmission::create([
        'procurement_id' => $procurement->getKey(),
        'status' => FormSubmission::STATUS_SUBMITTED,
        'submitted_at' => now(),
    ]);
    FormSubmissionValue::create([
        'submission_id' => $submission->getKey(),
        'field_key' => 'official_name',
        'value' => 'Automatic Screening Smoke Ltd',
    ]);
    FormSubmissionValue::create([
        'submission_id' => $submission->getKey(),
        'field_key' => 'country',
        'value' => 'Kenya',
    ]);
    FormSubmissionValue::create([
        'submission_id' => $submission->getKey(),
        'field_key' => 'consortium_name',
        'value' => 'Automatic Screening Consortium',
    ]);

    $automation = app(ProcurementSubmissionScreeningAutomation::class);
    $queued = $automation->queueSubmission($submission);
    $assert($queued === ProcurementSubmissionScreeningAutomation::QUEUED, 'A new submission was not queued.');

    $screening = $submission->screening()->firstOrFail();
    $firstRunToken = $screening->run_token;
    $assert($screening->request_status === ProcurementSubmissionScreening::STATUS_QUEUED, 'Queued state was not stored.');
    $assert(filled($screening->submission_fingerprint), 'The screening fingerprint was not stored.');

    $duplicate = $automation->queueSubmission($submission->fresh());
    $assert($duplicate === ProcurementSubmissionScreeningAutomation::ALREADY_ACTIVE, 'An active screening was queued twice.');
    $assert($submission->screening()->value('run_token') === $firstRunToken, 'Duplicate queueing rotated the active run token.');

    $automation->process((string) $submission->getKey(), $firstRunToken);

    $ambiguous = $submission->screening()->firstOrFail();
    $assert($ambiguous->request_status === ProcurementSubmissionScreening::STATUS_ERROR, 'An ambiguous 503 response was not stopped.');
    $assert($ambiguous->attempt_count === 1, 'The first provider call did not consume exactly one persisted attempt.');
    $assert(! $ambiguous->retryable, 'An ambiguous HTTP response could consume another credit automatically.');

    $rerun = $automation->queueSubmission($submission->fresh(), checkedVia: 'manual', force: true);
    $assert($rerun === ProcurementSubmissionScreeningAutomation::QUEUED, 'An explicit re-screen was not queued.');
    $rerunToken = (string) $submission->screening()->value('run_token');
    $assert($rerunToken !== $firstRunToken, 'An explicit re-screen did not rotate the run token.');

    try {
        $automation->process((string) $submission->getKey(), $rerunToken);
        throw new RuntimeException('A known pre-request connection failure did not trigger safe backoff.');
    } catch (TransientThreepapScreeningException) {
        // Expected: this transport failure happened before the provider could
        // receive or bill the request, so the same unique job may retry.
    }

    $retrying = $submission->screening()->firstOrFail();
    $assert($retrying->request_status === ProcurementSubmissionScreening::STATUS_RETRYING, 'A safe connection failure was not scheduled for retry.');
    $assert($retrying->attempt_count === 1, 'The failed transport attempt was not persisted exactly once.');
    $assert($retrying->retryable, 'A known pre-request connection failure was not marked retryable.');

    $automation->process((string) $submission->getKey(), $rerunToken);
    Http::assertSentCount(2);
    $submission->screening()->update(['next_retry_at' => now()->subSecond()]);

    $automation->process((string) $submission->getKey(), $rerunToken);
    $successful = $submission->screening()->firstOrFail();
    $assert($successful->request_status === ProcurementSubmissionScreening::STATUS_SUCCESS, 'The explicit re-screen did not store success.');
    $assert($successful->attempt_count === 2, 'The safe retry did not preserve the exact provider-call count.');

    $current = $automation->queueSubmission($submission->fresh());
    $assert($current === ProcurementSubmissionScreeningAutomation::UP_TO_DATE, 'An unchanged successful screening would spend another credit.');

    $successful->update([
        'review_decision' => 'fit',
        'review_notes' => 'Must be invalidated by a forced re-screen.',
    ]);
    $forced = $automation->queueSubmission($submission->fresh(), checkedVia: 'manual', force: true);
    $assert($forced === ProcurementSubmissionScreeningAutomation::QUEUED, 'A requested re-screen was not queued.');

    $newRun = $submission->screening()->firstOrFail();
    $assert($newRun->run_token !== $firstRunToken, 'A forced re-screen did not rotate its run token.');
    $assert($newRun->review_decision === null, 'A stale human decision survived a re-screen.');

    $automation->process((string) $submission->getKey(), $firstRunToken);
    Http::assertSentCount(3);

    $automation->process((string) $submission->getKey(), $newRun->run_token);
    Http::assertSentCount(4);
    Http::assertSent(fn (Request $request): bool => $request['name'] === 'Automatic Screening Consortium');
    $assert(
        $submission->screening()->value('request_status') === ProcurementSubmissionScreening::STATUS_SUCCESS,
        'The current run did not complete after a stale job was ignored.',
    );

    config(['services.threepap_checker.automatic.enabled' => false]);
    $disabled = $automation->queueSubmission($submission->fresh(), force: true);
    $assert(
        $disabled === ProcurementSubmissionScreeningAutomation::AUTOMATION_DISABLED,
        'Disabled automation did not return its explicit state.',
    );
    $staged = $submission->screening()->firstOrFail();
    $assert($staged->request_status === ProcurementSubmissionScreening::STATUS_WAITING, 'A disabled automatic run left the prior result looking current.');
    $assert($staged->retryable, 'A staged submission will not be recoverable after automation is enabled.');
    $assert(
        data_get($staged->response_payload, 'automation.state') === 'automatic_disabled',
        'The reason for staging the submission was not retained.',
    );

    config(['services.threepap_checker.automatic.enabled' => true]);
    $automation->process((string) $submission->getKey(), $staged->run_token);
    $assert(
        $submission->screening()->value('request_status') === ProcurementSubmissionScreening::STATUS_SUCCESS,
        'A staged applicant was not recoverable after automation was enabled.',
    );
    Http::assertSentCount(5);

    $manualOverride = $automation->queueSubmission($submission->fresh(), checkedVia: 'manual', force: true);
    $assert(
        $manualOverride === ProcurementSubmissionScreeningAutomation::QUEUED,
        'An authorised manual run could not override disabled automatic state after re-enabling.',
    );

    echo "THREEPAP_AUTOMATIC_SCREENING_SMOKE_OK\n";
} finally {
    DB::rollBack();
}
