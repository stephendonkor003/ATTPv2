<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BiAnnualSiteVisitQuestion extends BaseModel
{
    public const TYPE_TEXT = 'text';

    public const TYPE_TEXTAREA = 'textarea';

    public const TYPE_NUMBER = 'number';

    public const TYPE_YES_NO = 'yes_no';

    public const TYPE_SELECT = 'select';

    public const TYPE_MULTISELECT = 'multiselect';

    public const TYPE_RATING = 'rating';

    public const TYPE_DATE = 'date';

    public const TYPE_FILE = 'file';

    public const TYPE_SCORED_FINDING = 'scored_finding';

    protected $table = 'biannual_site_visit_questions';

    protected $fillable = [
        'template_id',
        'topic_id',
        'question_key',
        'question_type',
        'prompt',
        'help_text',
        'options',
        'validation',
        'visibility',
        'settings',
        'rating_labels',
        'is_required',
        'is_scored',
        'allows_na',
        'maximum_score',
        'score_weight',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'options' => 'array',
        'validation' => 'array',
        'visibility' => 'array',
        'settings' => 'array',
        'rating_labels' => 'array',
        'is_required' => 'boolean',
        'is_scored' => 'boolean',
        'allows_na' => 'boolean',
        'maximum_score' => 'decimal:4',
        'score_weight' => 'decimal:4',
        'sort_order' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(BiAnnualSiteVisitTemplate::class, 'template_id');
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(BiAnnualSiteVisitTopic::class, 'topic_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(BiAnnualSiteVisitAnswer::class, 'question_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('created_at');
    }

    public function scopeScored(Builder $query): Builder
    {
        return $query->where('is_scored', true);
    }

    public function scopeForKey(Builder $query, string $key): Builder
    {
        return $query->where('question_key', $key);
    }

    public function ratingLabelFor(mixed $value): ?string
    {
        if ($value === null || $value === '' || is_array($value) || is_object($value)) {
            return null;
        }

        $valueKey = (string) $value;
        $labels = $this->rating_labels ?? [];

        if (! array_is_list($labels) && array_key_exists($valueKey, $labels)) {
            $label = $labels[$valueKey];

            if (is_scalar($label)) {
                return (string) $label;
            }

            if (is_array($label) && isset($label['label'])) {
                return (string) $label['label'];
            }
        }

        foreach ($labels as $label) {
            if (
                is_array($label)
                && array_key_exists('value', $label)
                && (string) $label['value'] === $valueKey
            ) {
                return isset($label['label']) ? (string) $label['label'] : null;
            }
        }

        foreach ($this->options ?? [] as $option) {
            if (
                is_array($option)
                && array_key_exists('value', $option)
                && (string) $option['value'] === $valueKey
            ) {
                return isset($option['label']) ? (string) $option['label'] : null;
            }
        }

        $options = $this->options ?? [];

        if (! array_is_list($options) && array_key_exists($valueKey, $options)) {
            $option = $options[$valueKey];

            if (is_scalar($option)) {
                return (string) $option;
            }

            if (is_array($option) && isset($option['label'])) {
                return (string) $option['label'];
            }
        }

        return null;
    }

    public function weightedMaximumScore(): float
    {
        if (! $this->is_scored || $this->maximum_score === null) {
            return 0.0;
        }

        return round((float) $this->maximum_score * (float) ($this->score_weight ?? 1), 4);
    }

    public function snapshot(): array
    {
        return [
            'id' => (string) $this->id,
            'question_key' => (string) $this->question_key,
            'question_type' => (string) $this->question_type,
            'prompt' => (string) $this->prompt,
            'help_text' => $this->help_text,
            'options' => $this->options,
            'validation' => $this->validation,
            'visibility' => $this->visibility,
            'settings' => $this->settings,
            'rating_labels' => $this->rating_labels,
            'is_required' => (bool) $this->is_required,
            'is_scored' => (bool) $this->is_scored,
            'allows_na' => (bool) $this->allows_na,
            'maximum_score' => $this->maximum_score === null ? null : (float) $this->maximum_score,
            'score_weight' => (float) ($this->score_weight ?? 1),
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
