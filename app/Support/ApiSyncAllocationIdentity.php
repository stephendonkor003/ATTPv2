<?php

namespace App\Support;

final class ApiSyncAllocationIdentity
{
    /**
     * Convert supported ATTP allocation vocabulary to the public API vocabulary.
     *
     * Unknown values deliberately return null. This prevents a malformed or legacy
     * database value from becoming a trusted relationship type in a sync payload.
     */
    public static function normalizeLevel(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $normalized = strtolower(trim((string) $value));
        $normalized = preg_replace('/[\s-]+/', '_', $normalized) ?? '';
        $normalized = trim($normalized, '_');

        return match ($normalized) {
            'project', 'projects', 'project_level' => 'project',
            'activity', 'activities', 'activity_level' => 'activity',
            'subactivity', 'subactivities', 'sub_activity', 'sub_activities',
            'sub_activity_level' => 'sub_activity',
            default => null,
        };
    }

    /**
     * Create a collision-safe public identifier without trusting a raw type prefix.
     */
    public static function externalId(mixed $level, mixed $id): ?string
    {
        $normalizedLevel = self::normalizeLevel($level);
        $normalizedId = is_string($id) || is_numeric($id) ? trim((string) $id) : '';

        if ($normalizedLevel === null || $normalizedId === '') {
            return null;
        }

        return $normalizedLevel.':'.$normalizedId;
    }
}
