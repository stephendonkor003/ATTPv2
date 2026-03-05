<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class WorldShapeService
{
    private const BASE_ASSET_DIRECTORY = 'assets/Worldshapes';

    private const REGION_ORDER = [
        'Africa',
        'Antartica',
        'Asia',
        'Australia',
        'Europe',
        'North America',
        'Oceanica',
        'South America',
    ];

    private const REGION_LABEL_OVERRIDES = [
        'Antartica' => 'Antarctica',
        'Oceanica' => 'Oceania',
    ];

    public function getAvailableRegions(): array
    {
        $directory = public_path(self::BASE_ASSET_DIRECTORY);

        if (!File::exists($directory)) {
            return [];
        }

        $detectedRegions = collect(File::directories($directory))
            ->map(function (string $path): string {
                return basename($path);
            })
            ->values();

        $orderedRegions = collect(self::REGION_ORDER)
            ->filter(function (string $region) use ($detectedRegions): bool {
                return $detectedRegions->contains($region);
            });

        $remainingRegions = $detectedRegions
            ->reject(function (string $region) use ($orderedRegions): bool {
                return $orderedRegions->contains($region);
            })
            ->sort()
            ->values();

        return $orderedRegions
            ->merge($remainingRegions)
            ->values()
            ->all();
    }

    public function getRegionLabels(array $regions): array
    {
        return collect($regions)
            ->mapWithKeys(function (string $region): array {
                return [$region => self::REGION_LABEL_OVERRIDES[$region] ?? $region];
            })
            ->all();
    }

    public function getShapeFilesByRegion(array $regions): array
    {
        $shapeFilesByRegion = [];

        foreach ($regions as $region) {
            $shapeFilesByRegion[$region] = $this->getShapeFilesForRegion((string) $region);
        }

        return $shapeFilesByRegion;
    }

    public function getShapeFilesForRegion(string $region): array
    {
        $regionPath = public_path(self::BASE_ASSET_DIRECTORY . DIRECTORY_SEPARATOR . $region);

        if (!File::exists($regionPath)) {
            return [];
        }

        $regionSegment = rawurlencode($region);
        $baseUrl = app()->bound('request') ? rtrim(request()->getBaseUrl(), '/') : '';
        $assetPathPrefix = ($baseUrl !== '' ? $baseUrl : '')
            . '/'
            . self::BASE_ASSET_DIRECTORY
            . '/'
            . $regionSegment
            . '/';

        return collect(File::files($regionPath))
            ->filter(function ($file): bool {
                return strtolower((string) $file->getExtension()) === 'shp';
            })
            ->sortBy(function ($file): string {
                return (string) $file->getFilename();
            })
            ->map(function ($file) use ($assetPathPrefix): string {
                return $assetPathPrefix . rawurlencode((string) $file->getFilename());
            })
            ->values()
            ->all();
    }

    public function getCountriesByRegion(array $shapeFilesByRegion): array
    {
        $countriesByRegion = [];

        foreach ($shapeFilesByRegion as $region => $shapeFiles) {
            $countriesByRegion[(string) $region] = collect($shapeFiles)
                ->map(function (string $shapeFile): string {
                    $path = parse_url($shapeFile, PHP_URL_PATH);
                    $normalizedPath = $path ?: $shapeFile;

                    return rawurldecode((string) pathinfo($normalizedPath, PATHINFO_FILENAME));
                })
                ->filter(function (string $country): bool {
                    return trim($country) !== '';
                })
                ->unique()
                ->sort()
                ->values()
                ->all();
        }

        return $countriesByRegion;
    }
}
