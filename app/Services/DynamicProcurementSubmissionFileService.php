<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class DynamicProcurementSubmissionFileService
{
    public const DIRECTORY = 'procurement_submissions';

    /** @var array<int, string> */
    private const ALLOWED_PREFIXES = [
        'procurement_submissions/',
        'public/procurement_submissions/',
    ];

    public function store(UploadedFile $file): string
    {
        $path = Storage::disk('local')->putFile(self::DIRECTORY, $file);
        if (! is_string($path) || ! $this->isAllowedPath($path)) {
            throw new \RuntimeException('The procurement application file could not be stored securely.');
        }

        return $path;
    }

    /** @param iterable<int, mixed> $paths */
    public function deleteMany(iterable $paths): void
    {
        foreach ($paths as $path) {
            $normalized = $this->normalizedAllowedPath($path);
            if ($normalized !== null) {
                Storage::disk('local')->delete($normalized);
                // Historical submissions were written to the public disk with
                // the same stored path. Remove either copy only after the
                // caller's database transaction has committed.
                Storage::disk('public')->delete($normalized);
            }
        }
    }

    public function isAllowedPath(mixed $path): bool
    {
        return $this->normalizedAllowedPath($path) !== null;
    }

    public function exists(mixed $path): bool
    {
        $normalized = $this->normalizedAllowedPath($path);

        return $normalized !== null
            && (Storage::disk('local')->exists($normalized) || Storage::disk('public')->exists($normalized));
    }

    public function size(mixed $path): ?int
    {
        $normalized = $this->normalizedAllowedPath($path);
        if ($normalized === null) {
            return null;
        }

        foreach (['local', 'public'] as $diskName) {
            $disk = Storage::disk($diskName);
            if ($disk->exists($normalized)) {
                try {
                    return max(0, (int) $disk->size($normalized));
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        return null;
    }

    public function normalizedAllowedPath(mixed $path): ?string
    {
        if (! is_string($path) || str_contains($path, "\0")) {
            return null;
        }

        $normalized = str_replace('\\', '/', trim($path));
        if ($normalized === '' || str_starts_with($normalized, '/')) {
            return null;
        }

        $segments = explode('/', $normalized);
        if (in_array('', $segments, true) || in_array('.', $segments, true) || in_array('..', $segments, true)) {
            return null;
        }

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($normalized, $prefix) && strlen($normalized) > strlen($prefix)) {
                return $normalized;
            }
        }

        return null;
    }
}
