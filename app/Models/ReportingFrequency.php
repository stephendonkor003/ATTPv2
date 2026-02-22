<?php

namespace App\Models;

use App\Models\BaseModel;

class ReportingFrequency extends BaseModel
{
    protected $table = 'me_reporting_frequencies';

    public const INTERVAL_UNITS = [
        'second' => 'Second',
        'minute' => 'Minute',
        'hour' => 'Hour',
        'day' => 'Day',
        'week' => 'Week',
        'month' => 'Month',
        'quarterly' => 'Quarterly',
        'year' => 'Year',
        'annual' => 'Annual',
        'quinquennial' => 'Quinquennial',
        'once' => 'Once',
    ];

    protected $fillable = [
        'name',
        'code',
        'interval_unit',
        'interval_value',
        'frequency_in_days',
        'description',
        'sort_order',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'interval_value' => 'integer',
        'frequency_in_days' => 'integer',
    ];

    // Relationships
    public function indicators()
    {
        return $this->hasMany(Indicator::class, 'frequency_of_reporting_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public static function intervalOptions(): array
    {
        return self::INTERVAL_UNITS;
    }

    public function resolvedIntervalUnit(): string
    {
        if ($this->interval_unit && array_key_exists($this->interval_unit, self::INTERVAL_UNITS)) {
            return $this->interval_unit;
        }

        $code = strtoupper(trim((string) $this->code));
        $name = strtolower(trim((string) $this->name));
        $days = (int) ($this->frequency_in_days ?? 0);

        if (str_contains($code, 'SECOND') || str_contains($name, 'second')) {
            return 'second';
        }
        if (str_contains($code, 'MINUTE') || str_contains($name, 'minute')) {
            return 'minute';
        }
        if (str_contains($code, 'HOUR') || str_contains($name, 'hour')) {
            return 'hour';
        }
        if (str_contains($code, 'WEEK') || str_contains($name, 'week')) {
            return 'week';
        }
        if (str_contains($code, 'MONTH') || str_contains($name, 'month')) {
            return 'month';
        }
        if (str_contains($code, 'QUARTER') || str_contains($name, 'quarter')) {
            return 'quarterly';
        }
        if (str_contains($code, 'QUINQ') || str_contains($name, 'quinquen')) {
            return 'quinquennial';
        }
        if (str_contains($code, 'ANNUAL') || str_contains($name, 'annual')) {
            return 'annual';
        }
        if (str_contains($code, 'YEAR') || str_contains($name, 'year')) {
            return 'year';
        }
        if (str_contains($code, 'ONCE') || str_contains($name, 'once')) {
            return 'once';
        }

        if ($days > 0 && $days < 7) {
            return 'day';
        }
        if ($days >= 7 && $days < 30) {
            return 'week';
        }
        if ($days >= 30 && $days < 90) {
            return 'month';
        }
        if ($days >= 90 && $days < 365) {
            return 'quarterly';
        }
        if ($days >= (365 * 5)) {
            return 'quinquennial';
        }
        if ($days >= 365) {
            return 'annual';
        }

        return 'day';
    }

    public function resolvedIntervalValue(): ?int
    {
        $unit = $this->resolvedIntervalUnit();
        if ($unit === 'once') {
            return null;
        }

        if (!is_null($this->interval_value) && (int) $this->interval_value > 0) {
            return (int) $this->interval_value;
        }

        $days = (int) ($this->frequency_in_days ?? 0);
        if ($days <= 0) {
            return 1;
        }

        return match ($unit) {
            'week' => max(1, (int) round($days / 7)),
            'month' => max(1, (int) round($days / 30)),
            'quarterly' => max(1, (int) round($days / 90)),
            'year', 'annual' => max(1, (int) round($days / 365)),
            'quinquennial' => max(1, (int) round($days / (365 * 5))),
            default => max(1, $days),
        };
    }

    public function intervalDisplay(): string
    {
        $unit = $this->resolvedIntervalUnit();
        if ($unit === 'once') {
            return 'Once';
        }

        $label = self::INTERVAL_UNITS[$unit] ?? ucfirst($unit);
        $value = $this->resolvedIntervalValue() ?? 1;
        $pluralLabel = $value === 1 ? $label : $label . 's';

        return 'Every ' . $value . ' ' . $pluralLabel;
    }
}
