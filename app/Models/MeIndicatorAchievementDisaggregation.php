<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeIndicatorAchievementDisaggregation extends BaseModel
{
    protected $table = 'me_indicator_achievement_disaggregations';

    protected $fillable = [
        'achievement_id',
        'geographic_scope',
        'country',
        'rec',
        'implementing_institution_type',
        'implementing_institution',
        'priority_theme',
        'gender',
        'age_group',
        'stakeholder_category',
        'beneficiary_count',
        'combination_hash',
        'additional_dimensions',
    ];

    protected $casts = [
        'beneficiary_count' => 'integer',
        'additional_dimensions' => 'array',
    ];

    public function achievement(): BelongsTo
    {
        return $this->belongsTo(MeIndicatorAchievement::class, 'achievement_id');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function combinationHash(array $attributes): string
    {
        $keys = [
            'geographic_scope',
            'country',
            'rec',
            'implementing_institution_type',
            'implementing_institution',
            'priority_theme',
            'gender',
            'age_group',
            'stakeholder_category',
        ];

        $normalized = collect($keys)
            ->mapWithKeys(fn (string $key): array => [
                $key => mb_strtolower(trim((string) ($attributes[$key] ?? ''))),
            ])
            ->all();

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    }
}
