<?php

namespace App\Support;

use App\Models\IndicatorSurveyResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MeSurveyCleanup
{
    public static function attachmentPathsFromResponse(IndicatorSurveyResponse $response): array
    {
        return self::attachmentPathsFromAnswers((array) ($response->answers ?? []));
    }

    public static function attachmentPathsFromResponses(iterable $responses): array
    {
        return collect($responses)
            ->filter(fn ($response) => $response instanceof IndicatorSurveyResponse)
            ->flatMap(fn (IndicatorSurveyResponse $response) => self::attachmentPathsFromResponse($response))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function attachmentPathsFromAnswers(array $answers): array
    {
        return collect($answers)
            ->flatMap(fn ($answer) => self::extractAttachmentPaths($answer))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected static function extractAttachmentPaths(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $paths = [];

        $storedPath = trim((string) ($value['stored_path'] ?? $value['path'] ?? ''));
        if ($storedPath !== '') {
            $paths[] = ltrim($storedPath, '/');
        }

        $link = trim((string) ($value['Link'] ?? $value['link'] ?? $value['url'] ?? ''));
        if ($link !== '') {
            $resolvedPath = self::resolvePublicStoragePath($link);
            if ($resolvedPath !== null) {
                $paths[] = $resolvedPath;
            }
        }

        foreach ($value as $nestedValue) {
            if (is_array($nestedValue)) {
                $paths = array_merge($paths, self::extractAttachmentPaths($nestedValue));
            }
        }

        return array_values(array_unique(array_filter($paths)));
    }

    protected static function resolvePublicStoragePath(string $url): ?string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH));
        if ($path === '') {
            return null;
        }

        $diskRootPath = trim((string) parse_url(Storage::disk('public')->url(''), PHP_URL_PATH));
        $normalizedDiskRoot = $diskRootPath !== '' ? rtrim($diskRootPath, '/') : '';

        if ($normalizedDiskRoot !== '' && Str::startsWith($path, $normalizedDiskRoot . '/')) {
            $relativePath = Str::after($path, $normalizedDiskRoot . '/');
            return $relativePath !== '' ? trim($relativePath, '/') : null;
        }

        if (Str::startsWith($path, '/storage/')) {
            $relativePath = Str::after($path, '/storage/');
            return $relativePath !== '' ? trim($relativePath, '/') : null;
        }

        return null;
    }
}
