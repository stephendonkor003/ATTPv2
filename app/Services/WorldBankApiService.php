<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class WorldBankApiService
{
    private const DEFAULT_PER_PAGE = 1000;
    private const MAX_PAGES = 500;

    private string $baseUrl;

    public function __construct()
    {
        $configuredBaseUrl = config('services.world_bank.base_url');
        $this->baseUrl = rtrim(
            is_string($configuredBaseUrl) && $configuredBaseUrl !== ''
                ? $configuredBaseUrl
                : 'https://api.worldbank.org/v2',
            '/'
        );
    }

    public function getTopics(): array
    {
        return $this->fetchPaginated('/topic', [], 200);
    }

    public function getIndicators(): array
    {
        return $this->fetchPaginated('/indicator', [], 2000);
    }

    public function getIndicatorsByTopic(int $topicId): array
    {
        return $this->fetchPaginated("/topic/{$topicId}/indicator", [], 2000);
    }

    public function getCountries(): array
    {
        return $this->fetchPaginated('/country', [], 500);
    }

    public function getIndicatorData(
        string $indicatorId,
        string $countryScope = 'all',
        ?int $yearFrom = null,
        ?int $yearTo = null
    ): array {
        $query = [];

        if ($yearFrom !== null && $yearTo !== null && $yearFrom <= $yearTo) {
            $query['date'] = $yearFrom . ':' . $yearTo;
        }

        return $this->fetchPaginated(
            "/country/{$countryScope}/indicator/{$indicatorId}",
            $query,
            20000
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchPaginated(string $path, array $query = [], int $perPage = self::DEFAULT_PER_PAGE): array
    {
        $rows = [];
        $page = 1;
        $totalPages = 1;

        while ($page <= $totalPages && $page <= self::MAX_PAGES) {
            $payload = $this->request($path, array_merge($query, [
                'format' => 'json',
                'per_page' => $perPage,
                'page' => $page,
            ]));

            if (!is_array($payload) || count($payload) < 2) {
                break;
            }

            $meta = is_array($payload[0] ?? null) ? $payload[0] : [];
            $data = $payload[1] ?? [];

            if (is_array($meta) && isset($meta['pages'])) {
                $totalPages = max(1, (int) $meta['pages']);
            }

            if (!is_array($data) || empty($data)) {
                break;
            }

            foreach ($data as $item) {
                if (is_array($item)) {
                    $rows[] = $item;
                }
            }

            $page++;
        }

        return $rows;
    }

    /**
     * @return array<mixed>
     */
    private function request(string $path, array $query): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');

        $response = Http::acceptJson()
            ->timeout(60)
            ->retry(3, 700)
            ->get($url, $query);

        if (!$response->successful()) {
            throw new RuntimeException(
                "World Bank API request failed for {$url} with status {$response->status()}"
            );
        }

        $json = $response->json();

        if (!is_array($json)) {
            throw new RuntimeException("World Bank API returned an invalid payload for {$url}");
        }

        return $json;
    }
}

