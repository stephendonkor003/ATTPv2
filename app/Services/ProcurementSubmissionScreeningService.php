<?php

namespace App\Services;

use App\Models\FormSubmission;
use App\Models\ProcurementSubmissionScreening;
use App\Models\User;
use Composer\CaBundle\CaBundle;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
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
        'official_country',
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

    public function deferSubmissionScreening(string $submissionId): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        app()->terminating(function () use ($submissionId) {
            try {
                $submission = FormSubmission::with(['values', 'submitter'])->find($submissionId);
                if ($submission) {
                    $this->screenSubmission($submission, null, 'auto');
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }

    public function screenSubmission(
        FormSubmission $submission,
        ?User $actor = null,
        string $checkedVia = 'manual'
    ): ProcurementSubmissionScreening {
        $this->ensureConfigured();

        $submission->loadMissing(['values', 'submitter']);

        $entity = $this->buildEntityPayload($submission);
        if (blank($entity['name'])) {
            return $this->storeFailure(
                $submission,
                $entity,
                'Applicant name was not available for international screening.',
                $actor,
                $checkedVia
            );
        }

        try {
            $response = $this->client()->post('/sanctions/screen', array_filter([
                'name' => $entity['name'],
                'country' => $entity['country'],
                'max_results' => 10,
            ], fn ($value) => filled($value)));
        } catch (Throwable $exception) {
            report($exception);

            return $this->storeFailure(
                $submission,
                $entity,
                'The 3PAP screening service could not be reached. Try again later.',
                $actor,
                $checkedVia
            );
        }

        if ($response->failed()) {
            return $this->storeFailure(
                $submission,
                $entity,
                $this->extractErrorMessage($response),
                $actor,
                $checkedVia,
                $response->status(),
                $this->responsePayload($response)
            );
        }

        $responsePayload = $this->responsePayload($response);
        if (! ($responsePayload['success'] ?? false)) {
            return $this->storeFailure(
                $submission,
                $entity,
                $this->payloadErrorMessage($responsePayload, '3PAP did not complete the sanctions screening.'),
                $actor,
                $checkedVia,
                $response->status(),
                $responsePayload
            );
        }

        $payload = $this->normalizeSingleResponse($entity, $responsePayload);

        return $this->storeSuccess($submission, $entity, $payload, $actor, $checkedVia);
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
            'timeout' => max(1, (int) $this->resolveRuntimeConfigValue(
                'services.threepap_checker.timeout',
                'THREEPAP_CHECKER_TIMEOUT',
                20
            )),
            'connect_timeout' => max(1, (int) $this->resolveRuntimeConfigValue(
                'services.threepap_checker.connect_timeout',
                'THREEPAP_CHECKER_CONNECT_TIMEOUT',
                5
            )),
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
            ?? $this->pickValueByKeyword($values, ['company', 'business', 'vendor', 'supplier', 'contractor', 'organization', 'organisation', 'firm'])
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
        return [
            'success' => (bool) data_get($response, 'success', false),
            'query' => $entity,
            'risk_level' => strtolower((string) data_get($response, 'risk_level', 'clear')),
            'total_matches' => (int) data_get($response, 'total_matches', 0),
            'is_flagged' => in_array(
                strtolower((string) data_get($response, 'risk_level', 'clear')),
                ['medium', 'high', 'critical'],
                true
            ),
            'matches' => array_values((array) data_get($response, 'results', [])),
            'raw' => $response,
        ];
    }

    private function normalizeBatchResponse(array $entity, array $result): array
    {
        return [
            'success' => (bool) data_get($result, 'success', false),
            'query' => $entity,
            'risk_level' => strtolower((string) data_get($result, 'risk_level', 'clear')),
            'total_matches' => (int) data_get($result, 'total_matches', 0),
            'is_flagged' => (bool) data_get(
                $result,
                'is_flagged',
                in_array(
                    strtolower((string) data_get($result, 'risk_level', 'clear')),
                    ['medium', 'high', 'critical'],
                    true
                )
            ),
            'matches' => array_values((array) data_get($result, 'matches', [])),
            'raw' => $result,
        ];
    }

    private function storeSuccess(
        FormSubmission $submission,
        array $entity,
        array $payload,
        ?User $actor,
        string $checkedVia
    ): ProcurementSubmissionScreening {
        $riskLevel = strtolower((string) ($payload['risk_level'] ?? 'clear'));
        if (! in_array($riskLevel, ['clear', 'low', 'medium', 'high', 'critical'], true)) {
            $riskLevel = 'clear';
        }
        $totalMatches = (int) ($payload['total_matches'] ?? 0);

        return ProcurementSubmissionScreening::updateOrCreate(
            ['submission_id' => $submission->id],
            [
                'provider' => '3pap',
                'checked_by' => $actor?->id,
                'checked_via' => $checkedVia,
                'request_status' => 'success',
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
            ]
        );
    }

    private function storeFailure(
        FormSubmission $submission,
        array $entity,
        string $message,
        ?User $actor,
        string $checkedVia,
        ?int $statusCode = null,
        mixed $raw = null
    ): ProcurementSubmissionScreening {
        return ProcurementSubmissionScreening::updateOrCreate(
            ['submission_id' => $submission->id],
            [
                'provider' => '3pap',
                'checked_by' => $actor?->id,
                'checked_via' => $checkedVia,
                'request_status' => 'error',
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
            ]
        );
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
