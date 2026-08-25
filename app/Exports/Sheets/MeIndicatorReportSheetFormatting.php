<?php

namespace App\Exports\Sheets;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

trait MeIndicatorReportSheetFormatting
{
    private function cellValue(mixed $value): string|int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (is_array($value) || $value instanceof \Traversable) {
            return collect($value)->map(function (mixed $item): string {
                if (is_scalar($item) || $item === null) {
                    return (string) $item;
                }

                return json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
            })->filter()->join('; ');
        }

        return (string) $value;
    }

    private function listValue(mixed $values): string
    {
        return collect($values instanceof Collection ? $values->all() : (array) $values)
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->map(fn (mixed $value): string => is_scalar($value)
                ? (string) $value
                : (json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: ''))
            ->filter()
            ->unique()
            ->join(', ');
    }

    private function dateTime(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s T');
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s T');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function resultsLevel(?string $level): string
    {
        return match ($level) {
            'pdo' => 'PDO',
            'intermediate_results' => 'Intermediate Results',
            default => 'Not classified',
        };
    }

    private function percentage(mixed $value): ?string
    {
        return is_numeric($value) ? number_format((float) $value, 1).'%' : null;
    }
}
