<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiAnnualSiteVisitAnswer extends BaseModel
{
    protected $table = 'biannual_site_visit_answers';

    protected $fillable = [
        'profile_id',
        'question_id',
        'question_key',
        'value',
        'score',
        'maximum_score',
        'score_weight',
        'rating_label',
        'strength',
        'weakness',
        'evidence_notes',
        'is_not_applicable',
        'na_reason',
        'question_snapshot',
        'metadata',
        'answered_by',
        'updated_by',
        'answered_at',
    ];

    protected $casts = [
        'value' => 'array',
        'score' => 'decimal:4',
        'maximum_score' => 'decimal:4',
        'score_weight' => 'decimal:4',
        'is_not_applicable' => 'boolean',
        'question_snapshot' => 'array',
        'metadata' => 'array',
        'answered_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (BiAnnualSiteVisitAnswer $answer): void {
            if (! $answer->question_id) {
                return;
            }

            $question = $answer->question()->first();

            if (! $question) {
                return;
            }

            $answer->question_key = $answer->question_key ?: $question->question_key;
            $answer->question_snapshot = $answer->question_snapshot ?: $question->snapshot();

            if ($answer->maximum_score === null) {
                $answer->maximum_score = $question->maximum_score;
            }

            if (! array_key_exists('score_weight', $answer->getAttributes())) {
                $answer->score_weight = $question->score_weight ?? 1;
            }

            if (! $answer->rating_label) {
                $answer->rating_label = $question->ratingLabelFor($answer->score);
            }

            if ($answer->answered_by && ! $answer->answered_at) {
                $answer->answered_at = now();
            }
        });
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(BiAnnualSiteVisitProfile::class, 'profile_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(BiAnnualSiteVisitQuestion::class, 'question_id');
    }

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeApplicable(Builder $query): Builder
    {
        return $query->where('is_not_applicable', false);
    }

    public function scopeScored(Builder $query): Builder
    {
        return $query->applicable()->whereNotNull('score');
    }

    public function rawScore(): float
    {
        return $this->is_not_applicable ? 0.0 : (float) ($this->score ?? 0);
    }

    public function weightedScore(): float
    {
        return round($this->rawScore() * (float) ($this->score_weight ?? 1), 4);
    }

    public function rawMaximumScore(): float
    {
        if ($this->is_not_applicable) {
            return 0.0;
        }

        return (float) (
            $this->maximum_score
            ?? $this->question?->maximum_score
            ?? data_get($this->question_snapshot, 'maximum_score')
            ?? 0
        );
    }

    public function weightedMaximumScore(): float
    {
        return round($this->rawMaximumScore() * (float) ($this->score_weight ?? 1), 4);
    }

    public function resolvedRatingLabel(): ?string
    {
        if (filled($this->rating_label)) {
            return (string) $this->rating_label;
        }

        $ratingValue = $this->score;

        if ($ratingValue === null && is_array($this->value)) {
            $ratingValue = $this->value['value'] ?? null;
        }

        return $this->question?->ratingLabelFor($ratingValue);
    }

    public function hasEvidenceNotes(): bool
    {
        return filled($this->evidence_notes);
    }
}
