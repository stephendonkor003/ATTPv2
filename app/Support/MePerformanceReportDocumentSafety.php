<?php

namespace App\Support;

final class MePerformanceReportDocumentSafety
{
    public static function normalizeRelativePath(?string $path): ?string
    {
        $path = (string) $path;
        if (str_contains($path, "\0")) {
            return null;
        }

        $path = trim(str_replace('\\', '/', $path));
        if ($path === ''
            || str_contains($path, '..')
            || str_starts_with($path, '/')
            || str_contains($path, '://')
            || preg_match('/^[a-z]:\//i', $path) === 1) {
            return null;
        }

        $segments = explode('/', $path);
        if (in_array('.', $segments, true) || in_array('..', $segments, true)) {
            return null;
        }

        $normalized = implode('/', array_values(array_filter(
            $segments,
            static fn (string $segment): bool => $segment !== ''
        )));

        return $normalized !== '' ? $normalized : null;
    }

    /** @param list<string> $prefixes */
    public static function hasExpectedPrefix(string $path, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            $prefix = trim(str_replace('\\', '/', $prefix), '/');
            if ($prefix !== '' && str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    public static function safeDownloadName(?string $name, string $path): string
    {
        $candidate = self::basename((string) $name);
        if ($candidate === '') {
            $candidate = self::basename($path);
        }

        return $candidate !== '' ? $candidate : 'supporting-document';
    }

    public static function isContainedPath(string $root, string $candidate): bool
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $candidate = str_replace('\\', '/', $candidate);
        if ($root === '' || $candidate === '') {
            return false;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $root = strtolower($root);
            $candidate = strtolower($candidate);
        }

        return str_starts_with($candidate, $root.'/');
    }

    private static function basename(string $name): string
    {
        $name = basename(str_replace('\\', '/', trim($name)));
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?? '';
        $name = trim($name);
        if ($name === '' || in_array($name, ['.', '..'], true)) {
            return '';
        }

        return mb_substr($name, 0, 180);
    }
}
