<?php

namespace App\Models;

use App\Models\BaseModel;

class DynamicFormField extends BaseModel
{
    protected $table = 'dynamic_form_fields';

    protected $fillable = [
        'form_id',
        'label',
        'help_text',
        'placeholder',
        'field_key',
        'field_type',
        'is_required',
        'options',
        'validation_rules',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'validation_rules' => 'array',
    ];

    public function optionValues(): array
    {
        $stored = trim((string) $this->options);
        if ($stored === '') {
            return [];
        }

        $decoded = json_decode($stored, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && array_is_list($decoded)) {
            return collect($decoded)
                ->filter(fn (mixed $option): bool => is_scalar($option))
                ->map(fn (mixed $option): string => trim((string) $option))
                ->filter()
                ->values()
                ->all();
        }

        // Backward-compatible parsing for forms saved before JSON option
        // serialization was introduced.
        return collect(preg_split('/[\r\n,]+/', (string) $this->options))
            ->map(fn ($option) => trim((string) $option))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @param array<int, string> $options */
    public static function encodeOptionValues(array $options): ?string
    {
        $values = collect($options)
            ->map(fn (mixed $option): string => trim((string) $option))
            ->filter()
            ->values()
            ->all();

        return $values === []
            ? null
            : json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Parent Form
     */
    public function form()
    {
        return $this->belongsTo(DynamicForm::class, 'form_id');
    }
}
