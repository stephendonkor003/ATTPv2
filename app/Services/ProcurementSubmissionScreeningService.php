<?php

namespace App\Services;

use App\Models\FormSubmission;
use App\Models\ProcurementSubmissionScreening;
use App\Models\User;
use Composer\CaBundle\CaBundle;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ProcurementSubmissionScreeningService
{
    private const COMPANY_KEYS = [
        'company_name',
        'business_name',
        'vendor_name',
        'supplier_name',
        'contractor_name',
        'organization_name',
        'organisation_name',
        'registered_name',
        'legal_name',
        'firm_name',
        'entity_name',
        'consortium_name',
        'think_tank_name',
        'lead_think_tank_name',
        'institution_name',
        'applicant_organization',
        'official_name',
    ];

    private const COUNTRY_KEYS = [
        'country',
        'company_country',
        'vendor_country',
        'supplier_country',
        'contractor_country',
        'business_country',
        'registered_country',
        'country_of_registration',
        'official_country',
        'think_tank_country',
        'lead_think_tank_country',
        'organization_country',
        'organisation_country',
        'nationality',
    ];

    private ?array $screeningConfig = null;

    public function isConfigured(): bool
    {
        $config = $this->screeningConfig();

        return filled($config['api_token'])
            && filled($config['base_url']);
    }

    /**
     * Verify the configured account without consuming a sanctions-search
     * credit. The official /usage endpoint authenticates the token and
     * exposes its scopes and current monthly allowance.
     *
     * @return array{
     *     ok: bool,
     *     configured: bool,
     *     authenticated: bool,
     *     scope_enabled: bool,
     *     plan: null|string,
     *     scopes: array<int, string>,
     *     usage: array{used: null|int, limit: null|int, remaining: null|int},
     *     message: string
     * }
     */
    public function accountStatus(): array
    {
        $result = [
            'ok' => false,
            'configured' => $this->isConfigured(),
            'authenticated' => false,
            'scope_enabled' => false,
            'plan' => null,
            'scopes' => [],
            'usage' => [
                'used' => null,
                'limit' => null,
                'remaining' => null,
            ],
            'message' => '3PAP sanctions screening is not configured.',
        ];

        if (! $result['configured']) {
            return $result;
        }

        try {
            $response = $this->client()->get('/usage');
        } catch (Throwable $exception) {
            report($exception);

            return [
                ...$result,
                'message' => 'The 3PAP service could not be reached. Check network access and the configured base URL.',
            ];
        }

        if ($response->failed()) {
            return [
                ...$result,
                'message' => $this->extractErrorMessage($response),
            ];
        }

        $payload = $this->responsePayload($response);
        if (! ($payload['success'] ?? false)) {
            return [
                ...$result,
                'message' => $this->payloadErrorMessage($payload, '3PAP did not confirm the configured account.'),
            ];
        }

        $scopes = collect((array) data_get($payload, 'token.scopes', []))
            ->filter(fn ($scope): bool => is_string($scope) && $scope !== '')
            ->map(fn (string $scope): string => strtolower(trim($scope)))
            ->unique()
            ->values()
            ->all();
        $scopeEnabled = in_array('sanctions_search', $scopes, true);
        $usage = (array) data_get($payload, 'usage.sanctions_search', []);
        $normalizedUsage = [
            'used' => $this->nullableInteger($usage['used'] ?? null),
            'limit' => $this->nullableInteger($usage['limit'] ?? null),
            'remaining' => $this->nullableInteger($usage['remaining'] ?? null),
        ];
        $quotaAvailable = $normalizedUsage['remaining'] === null || $normalizedUsage['remaining'] > 0;
        $ok = $scopeEnabled && $quotaAvailable;

        return [
            ...$result,
            'ok' => $ok,
            'authenticated' => true,
            'scope_enabled' => $scopeEnabled,
            'plan' => filled($payload['plan'] ?? null) ? (string) $payload['plan'] : null,
            'scopes' => $scopes,
            'usage' => $normalizedUsage,
            'message' => match (true) {
                ! $scopeEnabled => 'The token is valid but does not include the required sanctions_search scope.',
                ! $quotaAvailable => 'The token is valid, but its monthly sanctions-search quota is exhausted.',
                default => 'The 3PAP token, sanctions_search scope, and monthly quota are available.',
            },
        ];
    }

    public function screenSubmission(
        FormSubmission $submission,
        ?User $actor = null,
        string $checkedVia = 'manual',
        ?string $runToken = null,
    ): ?ProcurementSubmissionScreening {
        $this->ensureConfigured();

        $submission->loadMissing(['values', 'submitter']);

        $entity = $this->buildEntityPayload($submission);
        if (blank($entity['name'])) {
            return $this->storeFailure(
                $submission,
                $entity,
                'Applicant name was not available for international screening.',
                $actor,
                $checkedVia,
                runToken: $runToken,
            );
        }

        if (filled($runToken)) {
            $marked = ProcurementSubmissionScreening::query()
                ->where('submission_id', $submission->id)
                ->where('run_token', $runToken)
                ->where('request_status', ProcurementSubmissionScreening::STATUS_PROCESSING)
                ->where('attempt_count', '<', 5)
                ->update([
                    'attempt_count' => DB::raw('attempt_count + 1'),
                    'request_started_at' => now(),
                ]);

            if ($marked !== 1) {
                return null;
            }
        }

        try {
            $response = $this->client()->post('/sanctions/screen', array_filter([
                'name' => $entity['name'],
                'country' => $entity['country'],
                'max_results' => 10,
            ], fn ($value) => filled($value)));
        } catch (Throwable $exception) {
            report($exception);
            $failure = $this->safeExceptionMetadata($exception);

            return $this->storeFailure(
                $submission,
                $entity,
                $failure['retryable']
                    ? 'The 3PAP screening service could not be reached. The system will retry automatically.'
                    : 'The 3PAP response was not received, so the request outcome is unknown. Verify 3PAP usage before re-running.',
                $actor,
                $checkedVia,
                raw: $failure,
                runToken: $runToken,
            );
        }

        if ($response->failed()) {
            $failure = $this->httpFailureMetadata($response);

            return $this->storeFailure(
                $submission,
                $entity,
                $failure['outcome_unknown']
                    ? $this->extractErrorMessage($response).' Automatic retry was stopped because the request may already have consumed a screening credit.'
                    : $this->extractErrorMessage($response),
                $actor,
                $checkedVia,
                $response->status(),
                $failure,
                $runToken,
            );
        }

        $responsePayload = $this->responsePayload($response);
        if (($responsePayload['success'] ?? null) === false) {
            return $this->storeFailure(
                $submission,
                $entity,
                $this->payloadErrorMessage($responsePayload, '3PAP did not complete the sanctions screening.'),
                $actor,
                $checkedVia,
                $response->status(),
                $responsePayload,
                $runToken,
            );
        }

        $validationError = $this->singleResponseValidationError($responsePayload);
        if ($validationError !== null) {
            return $this->storeFailure(
                $submission,
                $entity,
                '3PAP returned an incomplete or invalid sanctions screening result. No clearance was recorded.',
                $actor,
                $checkedVia,
                $response->status(),
                [
                    'code' => 'INVALID_PROVIDER_RESPONSE',
                    'validation_error' => $validationError,
                    'provider_response' => $responsePayload,
                ],
                $runToken,
            );
        }

        $payload = $this->normalizeSingleResponse($entity, $responsePayload);

        return $this->storeSuccess($submission, $entity, $payload, $actor, $checkedVia, $runToken);
    }

    /**
     * Hash only the normalized data sent to 3PAP. This lets the queue avoid
     * spending another screening credit when an unchanged submission is
     * rediscovered by the recovery task.
     */
    public function submissionFingerprint(FormSubmission $submission): string
    {
        $submission->loadMissing(['values', 'submitter']);
        $entity = $this->buildEntityPayload($submission);

        return hash('sha256', json_encode([
            'version' => 1,
            'name' => $this->fingerprintValue($entity['name']),
            'country' => $this->fingerprintValue($entity['country']),
        ], JSON_THROW_ON_ERROR));
    }

    public function screenSubmissions(iterable $submissions, ?User $actor = null, string $checkedVia = 'bulk'): array
    {
        $this->ensureConfigured();

        $summary = [
            'checked' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        $items = collect($submissions)
            ->map(function (FormSubmission $submission) {
                $submission->loadMissing(['values', 'submitter']);

                return [
                    'submission' => $submission,
                    'entity' => $this->buildEntityPayload($submission),
                ];
            });

        foreach ($items->chunk(50) as $chunk) {
            [$ready, $skipped] = $chunk->partition(
                fn (array $item) => filled($item['entity']['name'])
            );

            $ready = $ready->values();
            $skipped = $skipped->values();

            foreach ($skipped as $item) {
                $this->storeFailure(
                    $item['submission'],
                    $item['entity'],
                    'Applicant name was not available for international screening.',
                    $actor,
                    $checkedVia
                );
                $summary['skipped']++;
            }

            if ($ready->isEmpty()) {
                continue;
            }

            try {
                $response = $this->client()->post('/sanctions/batch', [
                    'entities' => $ready->map(fn (array $item) => array_filter([
                        'name' => $item['entity']['name'],
                        'country' => $item['entity']['country'],
                    ], fn ($value) => filled($value)))->all(),
                ]);
            } catch (Throwable $exception) {
                report($exception);

                foreach ($ready as $item) {
                    $this->storeFailure(
                        $item['submission'],
                        $item['entity'],
                        'The 3PAP screening service could not be reached. Try again later.',
                        $actor,
                        $checkedVia
                    );
                    $summary['failed']++;
                }

                continue;
            }

            if ($response->failed()) {
                $message = $this->extractErrorMessage($response);
                $responsePayload = $this->responsePayload($response);

                foreach ($ready as $item) {
                    $this->storeFailure(
                        $item['submission'],
                        $item['entity'],
                        $message,
                        $actor,
                        $checkedVia,
                        $response->status(),
                        $responsePayload
                    );
                    $summary['failed']++;
                }

                continue;
            }

            $responsePayload = $this->responsePayload($response);
            if (! ($responsePayload['success'] ?? false)) {
                $message = $this->payloadErrorMessage(
                    $responsePayload,
                    '3PAP did not complete the batch sanctions screening.'
                );

                foreach ($ready as $item) {
                    $this->storeFailure(
                        $item['submission'],
                        $item['entity'],
                        $message,
                        $actor,
                        $checkedVia,
                        $response->status(),
                        $responsePayload
                    );
                    $summary['failed']++;
                }

                continue;
            }

            $results = array_values((array) data_get($responsePayload, 'results', []));

            foreach ($ready as $index => $item) {
                $result = $results[$index] ?? null;
                if (! is_array($result)
                    || strcasecmp(trim((string) data_get($result, 'name')), trim($item['entity']['name'])) !== 0) {
                    $result = $this->matchBatchResultByName($results, $item['entity']['name']);
                }

                if (! is_array($result)) {
                    $this->storeFailure(
                        $item['submission'],
                        $item['entity'],
                        'International screening did not return a result for this applicant.',
                        $actor,
                        $checkedVia,
                        $response->status(),
                        $responsePayload
                    );
                    $summary['failed']++;

                    continue;
                }

                if (! ($result['success'] ?? false)) {
                    $this->storeFailure(
                        $item['submission'],
                        $item['entity'],
                        $this->payloadErrorMessage($result, '3PAP did not screen this applicant.'),
                        $actor,
                        $checkedVia,
                        $response->status(),
                        $result
                    );
                    $summary['failed']++;

                    continue;
                }

                $validationError = $this->batchResultValidationError($result);
                if ($validationError !== null) {
                    $this->storeFailure(
                        $item['submission'],
                        $item['entity'],
                        '3PAP returned an incomplete or invalid batch screening result. No clearance was recorded.',
                        $actor,
                        $checkedVia,
                        $response->status(),
                        [
                            'code' => 'INVALID_PROVIDER_RESPONSE',
                            'validation_error' => $validationError,
                            'provider_response' => $result,
                        ],
                    );
                    $summary['failed']++;

                    continue;
                }

                $payload = $this->normalizeBatchResponse($item['entity'], $result);
                $this->storeSuccess($item['submission'], $item['entity'], $payload, $actor, $checkedVia);
                $summary['checked']++;
            }
        }

        return $summary;
    }

    private function client(): PendingRequest
    {
        $config = $this->screeningConfig();
        $caBundle = filled($config['ca_bundle'])
            ? (string) $config['ca_bundle']
            : CaBundle::getSystemCaRootBundlePath();

        return Http::acceptJson()
            ->asJson()
            ->withToken((string) $config['api_token'])
            ->withOptions(['verify' => $caBundle])
            ->connectTimeout((int) $config['connect_timeout'])
            ->timeout((int) $config['timeout'])
            ->baseUrl(rtrim((string) $config['base_url'], '/'));
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('International screening is not configured.');
        }
    }

    private function screeningConfig(): array
    {
        if ($this->screeningConfig !== null) {
            return $this->screeningConfig;
        }

        return $this->screeningConfig = [
            'base_url' => $this->resolveRuntimeConfigValue(
                'services.threepap_checker.base_url',
                'THREEPAP_CHECKER_BASE_URL',
                'https://checker.3pap.africa/api/v1'
            ),
            'api_token' => $this->resolveRuntimeConfigValue(
                'services.threepap_checker.api_token',
                'THREEPAP_CHECKER_API_TOKEN'
            ),
            'timeout' => min(45, max(1, (int) $this->resolveRuntimeConfigValue(
                'services.threepap_checker.timeout',
                'THREEPAP_CHECKER_TIMEOUT',
                20
            ))),
            'connect_timeout' => min(15, max(1, (int) $this->resolveRuntimeConfigValue(
                'services.threepap_checker.connect_timeout',
                'THREEPAP_CHECKER_CONNECT_TIMEOUT',
                5
            ))),
            'ca_bundle' => $this->resolveRuntimeConfigValue(
                'services.threepap_checker.ca_bundle',
                'THREEPAP_CHECKER_CA_BUNDLE'
            ),
        ];
    }

    private function resolveRuntimeConfigValue(string $configKey, string $envKey, mixed $default = null): mixed
    {
        $configValue = config($configKey);
        if (filled($configValue)) {
            return $configValue;
        }

        $runtimeValue = $_ENV[$envKey] ?? $_SERVER[$envKey] ?? getenv($envKey);
        if ($runtimeValue !== false && filled($runtimeValue)) {
            return $runtimeValue;
        }

        $fileValue = $this->readEnvironmentFileValue($envKey);
        if (filled($fileValue)) {
            return $fileValue;
        }

        return $default;
    }

    private function readEnvironmentFileValue(string $key): ?string
    {
        foreach (['.env', 'env'] as $environmentFile) {
            $path = base_path($environmentFile);
            if (! is_file($path)) {
                continue;
            }

            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                    continue;
                }

                [$name, $value] = array_pad(explode('=', $line, 2), 2, null);
                if (trim((string) $name) !== $key) {
                    continue;
                }

                $value = trim((string) $value);
                if ($value === '') {
                    return null;
                }

                if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
                    return substr($value, 1, -1);
                }

                if (str_starts_with($value, "'") && str_ends_with($value, "'")) {
                    return substr($value, 1, -1);
                }

                return $value;
            }
        }

        return null;
    }

    private function buildEntityPayload(FormSubmission $submission): array
    {
        $values = $submission->values
            ->mapWithKeys(fn ($value) => [$value->field_key => $this->normalizeScalar($value->value)])
            ->filter(fn ($value) => filled($value));

        $name = $this->pickFirstValue($values, self::COMPANY_KEYS)
            ?? $this->pickValueByKeyword($values, [
                'company',
                'business',
                'vendor',
                'supplier',
                'contractor',
                'organization',
                'organisation',
                'consortium',
                'think_tank',
                'institution',
                'firm',
            ])
            ?? $submission->submitter?->name;

        $country = $this->pickFirstValue($values, self::COUNTRY_KEYS)
            ?? $this->pickValueByKeyword($values, ['country', 'nationality']);

        return [
            'name' => $name,
            'country' => $country,
        ];
    }

    private function pickFirstValue(Collection $values, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $values->get($key);
            if (filled($value)) {
                return $value;
            }
        }

        return null;
    }

    private function pickValueByKeyword(Collection $values, array $keywords): ?string
    {
        foreach ($values as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            foreach ($keywords as $keyword) {
                if (str_contains($normalizedKey, $keyword) && filled($value)) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function normalizeScalar(mixed $value): ?string
    {
        if (is_array($value) || is_object($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || str_starts_with($value, '[') || str_starts_with($value, '{')) {
            return null;
        }

        return $value;
    }

    private function normalizeSingleResponse(array $entity, array $response): array
    {
        $riskLevel = strtolower(trim($response['risk_level']));

        return [
            'success' => true,
            'query' => $entity,
            'risk_level' => $riskLevel,
            'total_matches' => $response['total_matches'],
            'is_flagged' => in_array(
                $riskLevel,
                ['medium', 'high', 'critical'],
                true
            ),
            'matches' => $response['results'],
            'raw' => $response,
        ];
    }

    private function singleResponseValidationError(array $response): ?string
    {
        if (($response['success'] ?? null) !== true) {
            return 'The success field must be the boolean value true.';
        }

        if (! array_key_exists('risk_level', $response)
            || ! is_string($response['risk_level'])
            || trim($response['risk_level']) === '') {
            return 'The risk_level field must be a non-empty string.';
        }

        $riskLevel = strtolower(trim($response['risk_level']));
        if (! in_array($riskLevel, ['clear', 'low', 'medium', 'high', 'critical'], true)) {
            return 'The risk_level field contains an unknown classification.';
        }

        if (! array_key_exists('total_matches', $response)
            || ! is_int($response['total_matches'])
            || $response['total_matches'] < 0) {
            return 'The total_matches field must be a non-negative integer.';
        }

        if (! array_key_exists('results', $response)
            || ! is_array($response['results'])
            || ! array_is_list($response['results'])) {
            return 'The results field must be a JSON array.';
        }

        foreach ($response['results'] as $result) {
            if (! is_array($result)) {
                return 'Every results entry must be a JSON object.';
            }
        }

        $totalMatches = $response['total_matches'];
        $returnedMatches = count($response['results']);

        if ($returnedMatches > $totalMatches) {
            return 'The results array cannot contain more entries than total_matches.';
        }

        if ($riskLevel === 'clear' && ($totalMatches !== 0 || $returnedMatches !== 0)) {
            return 'A clear result must report zero matches and an empty results array.';
        }

        if ($riskLevel !== 'clear' && $totalMatches === 0) {
            return 'A non-clear result must report at least one match.';
        }

        return null;
    }

    private function normalizeBatchResponse(array $entity, array $result): array
    {
        $riskLevel = strtolower(trim($result['risk_level']));

        return [
            'success' => true,
            'query' => $entity,
            'risk_level' => $riskLevel,
            'total_matches' => $result['total_matches'],
            'is_flagged' => (bool) data_get(
                $result,
                'is_flagged',
                in_array(
                    $riskLevel,
                    ['medium', 'high', 'critical'],
                    true
                )
            ),
            'matches' => $result['matches'],
            'raw' => $result,
        ];
    }

    private function batchResultValidationError(array $result): ?string
    {
        $singleShape = $result;
        if (array_key_exists('matches', $result)) {
            $singleShape['results'] = $result['matches'];
        } else {
            unset($singleShape['results']);
        }

        return $this->singleResponseValidationError($singleShape);
    }

    private function storeSuccess(
        FormSubmission $submission,
        array $entity,
        array $payload,
        ?User $actor,
        string $checkedVia,
        ?string $runToken = null,
    ): ?ProcurementSubmissionScreening {
        $riskLevel = is_string($payload['risk_level'] ?? null)
            ? strtolower(trim($payload['risk_level']))
            : null;

        if (! in_array($riskLevel, ['clear', 'low', 'medium', 'high', 'critical'], true)) {
            return $this->storeFailure(
                $submission,
                $entity,
                '3PAP returned an invalid risk classification. No clearance was recorded.',
                $actor,
                $checkedVia,
                raw: [
                    'code' => 'INVALID_PROVIDER_RESPONSE',
                    'provider_response' => $payload,
                ],
                runToken: $runToken,
            );
        }
        $totalMatches = (int) ($payload['total_matches'] ?? 0);

        return $this->persistResult(
            $submission,
            [
                'provider' => '3pap',
                'checked_by' => $actor?->id,
                'checked_via' => $checkedVia,
                'request_status' => ProcurementSubmissionScreening::STATUS_SUCCESS,
                'submission_fingerprint' => $this->submissionFingerprint($submission),
                'retryable' => false,
                'processing_started_at' => null,
                'request_started_at' => null,
                'next_retry_at' => null,
                'entity_name' => $entity['name'],
                'entity_country' => $entity['country'],
                'risk_level' => $riskLevel,
                'total_matches' => $totalMatches,
                'is_flagged' => (bool) ($payload['is_flagged'] ?? in_array(
                    $riskLevel,
                    ['medium', 'high', 'critical'],
                    true
                )),
                'error_message' => null,
                'last_checked_at' => now(),
                'response_payload' => $payload,
                'review_decision' => null,
                'review_notes' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ],
            $runToken,
        );
    }

    private function storeFailure(
        FormSubmission $submission,
        array $entity,
        string $message,
        ?User $actor,
        string $checkedVia,
        ?int $statusCode = null,
        mixed $raw = null,
        ?string $runToken = null,
    ): ?ProcurementSubmissionScreening {
        return $this->persistResult(
            $submission,
            [
                'provider' => '3pap',
                'checked_by' => $actor?->id,
                'checked_via' => $checkedVia,
                'request_status' => ProcurementSubmissionScreening::STATUS_ERROR,
                'submission_fingerprint' => $this->submissionFingerprint($submission),
                'retryable' => $this->isSafePreRequestFailureMetadata($raw),
                'processing_started_at' => null,
                'request_started_at' => null,
                'next_retry_at' => null,
                'entity_name' => $entity['name'],
                'entity_country' => $entity['country'],
                'risk_level' => null,
                'total_matches' => 0,
                'is_flagged' => false,
                'error_message' => $message,
                'last_checked_at' => now(),
                'review_decision' => null,
                'review_notes' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'response_payload' => [
                    'success' => false,
                    'query' => $entity,
                    'status' => $statusCode,
                    'error' => $message,
                    'raw' => $raw,
                ],
            ],
            $runToken,
        );
    }

    private function persistResult(
        FormSubmission $submission,
        array $attributes,
        ?string $runToken,
    ): ?ProcurementSubmissionScreening {
        if (filled($runToken)) {
            $databaseAttributes = $attributes;
            $databaseAttributes['response_payload'] = json_encode(
                $attributes['response_payload'] ?? null,
                JSON_THROW_ON_ERROR,
            );

            $updated = ProcurementSubmissionScreening::query()
                ->where('submission_id', $submission->id)
                ->where('run_token', $runToken)
                ->update($databaseAttributes);

            if ($updated !== 1) {
                return null;
            }

            return ProcurementSubmissionScreening::query()
                ->where('submission_id', $submission->id)
                ->where('run_token', $runToken)
                ->first();
        }

        $existing = ProcurementSubmissionScreening::query()
            ->where('submission_id', $submission->id)
            ->first();

        return ProcurementSubmissionScreening::updateOrCreate(
            ['submission_id' => $submission->id],
            [
                'run_token' => $existing?->run_token ?: (string) Str::uuid(),
                ...$attributes,
            ],
        );
    }

    /** @return array{code:string,curl_code:null|int,retryable:bool} */
    private function safeExceptionMetadata(Throwable $exception): array
    {
        preg_match('/cURL error\s+(\d+)/i', $exception->getMessage(), $matches);
        $curlCode = isset($matches[1]) ? (int) $matches[1] : null;

        return [
            'code' => in_array($curlCode, [5, 6, 7, 35, 60], true)
                ? 'CONNECTION_FAILED'
                : 'OUTCOME_UNKNOWN',
            'curl_code' => $curlCode,
            // Retry only failures known to happen before an HTTP request can
            // be processed; an ambiguous timeout could otherwise spend a
            // second sanctions-search credit.
            'retryable' => in_array($curlCode, [5, 6, 7, 35, 60], true),
        ];
    }

    /** @return array{provider:array<string,mixed>,code:string,retryable:bool,outcome_unknown:bool} */
    private function httpFailureMetadata(Response $response): array
    {
        $payload = $this->responsePayload($response);
        $status = $response->status();
        $code = strtoupper(trim((string) data_get($payload, 'code')));

        return [
            'provider' => $payload,
            'code' => $code,
            // 3PAP does not document an idempotency key. Even a 5xx can arrive
            // after the paid screening was accepted, so HTTP responses are
            // never repeated automatically.
            'retryable' => false,
            'outcome_unknown' => in_array($status, [408, 425], true) || $status >= 500,
        ];
    }

    private function isSafePreRequestFailureMetadata(mixed $metadata): bool
    {
        $code = strtoupper(trim((string) data_get($metadata, 'code')));
        $curlCode = data_get($metadata, 'curl_code');

        return $code === 'CONNECTION_FAILED'
            && data_get($metadata, 'retryable') === true
            && is_numeric($curlCode)
            && in_array((int) $curlCode, [5, 6, 7, 35, 60], true);
    }

    private function fingerprintValue(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return Str::lower(preg_replace('/\s+/u', ' ', trim((string) $value)) ?? trim((string) $value));
    }

    private function extractErrorMessage(Response $response): string
    {
        return $this->payloadErrorMessage(
            $this->responsePayload($response),
            sprintf('3PAP screening request failed with HTTP %s.', $response->status())
        );
    }

    /** @return array<string, mixed> */
    private function responsePayload(Response $response): array
    {
        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    /** @param array<string, mixed> $payload */
    private function payloadErrorMessage(array $payload, string $fallback): string
    {
        $message = trim((string) (data_get($payload, 'error') ?: data_get($payload, 'message')));
        $code = trim((string) data_get($payload, 'code'));

        if ($message === '') {
            return $fallback;
        }

        return $code !== '' ? $message.' ('.$code.')' : $message;
    }

    private function nullableInteger(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function matchBatchResultByName(array $results, string $name): ?array
    {
        $needle = strtolower(trim($name));

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            if (strtolower(trim((string) data_get($result, 'name'))) === $needle) {
                return $result;
            }
        }

        return null;
    }
}
