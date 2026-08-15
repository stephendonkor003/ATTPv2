<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class BiAnnualSiteVisitProfile extends BaseModel
{
    public const VISIT_TYPE = 'biannual_monitoring';

    public const FIRST_HALF = 1;

    public const SECOND_HALF = 2;

    public const MUTABLE_WORKFLOW_STATUSES = ['draft', 'returned', 'in_progress'];

    protected $table = 'biannual_site_visit_profiles';

    protected $fillable = [
        'site_visit_id',
        'think_tank_member_id',
        'template_id',
        'reference_number',
        'title',
        'template_version',
        'cycle_year',
        'cycle_half',
        'location',
        'starts_on',
        'ends_on',
        'objectives',
        'questionnaire_snapshot',
        'settings',
        'visibility_snapshot',
        'completion_percentage',
        'score_percentage',
        'notes',
        'is_active',
        'deactivated_at',
        'deactivated_by',
        'deactivation_reason',
        'reactivated_at',
        'reactivated_by',
        'created_by',
        'updated_by',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'template_version' => 'integer',
        'cycle_year' => 'integer',
        'cycle_half' => 'integer',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'questionnaire_snapshot' => 'array',
        'settings' => 'array',
        'visibility_snapshot' => 'array',
        'completion_percentage' => 'decimal:2',
        'score_percentage' => 'decimal:2',
        'is_active' => 'boolean',
        'deactivated_at' => 'datetime',
        'reactivated_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (BiAnnualSiteVisitProfile $profile): void {
            if (! $profile->template_id) {
                return;
            }

            $template = $profile->template()->withStructure()->first();

            if (! $template) {
                return;
            }

            $profile->template_version = $profile->template_version ?: $template->version;
            $profile->questionnaire_snapshot = $profile->questionnaire_snapshot
                ?: $template->questionnaireSnapshot();
        });
    }

    public function siteVisit(): BelongsTo
    {
        return $this->belongsTo(SiteVisit::class, 'site_visit_id');
    }

    public function thinkTank(): BelongsTo
    {
        return $this->belongsTo(ConsortiumThinkTank::class, 'think_tank_member_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(BiAnnualSiteVisitTemplate::class, 'template_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(BiAnnualSiteVisitAnswer::class, 'profile_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    public function reactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reactivated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function hasMutableWorkflowStatus(): bool
    {
        return in_array(
            (string) $this->siteVisit?->status,
            self::MUTABLE_WORKFLOW_STATUSES,
            true
        );
    }

    public function scopeForThinkTank(Builder $query, string $thinkTankId): Builder
    {
        return $query->where('think_tank_member_id', $thinkTankId);
    }

    public function scopeForCycle(Builder $query, int $year, ?int $half = null): Builder
    {
        return $query
            ->where('cycle_year', $year)
            ->when($half !== null, fn (Builder $cycleQuery) => $cycleQuery->where('cycle_half', $half));
    }

    public function scopeUsingTemplate(Builder $query, string $templateId): Builder
    {
        return $query->where('template_id', $templateId);
    }

    public function scopeWithQuestionnaire(Builder $query): Builder
    {
        return $query->with([
            'siteVisit.group.leader',
            'siteVisit.group.members.user',
            'thinkTank',
            'template',
            'answers.question',
        ]);
    }

    public function cycleLabel(): string
    {
        $half = $this->cycle_half === self::FIRST_HALF ? 'H1' : 'H2';

        return trim($half.' '.$this->cycle_year);
    }

    public function aggregateScore(bool $weighted = true): float
    {
        return round(
            (float) $this->scoringAnswers()->sum(
                fn (BiAnnualSiteVisitAnswer $answer): float => $weighted
                    ? $answer->weightedScore()
                    : $answer->rawScore()
            ),
            4
        );
    }

    public function aggregateMaximumScore(bool $weighted = true): float
    {
        return round(
            (float) $this->scoringAnswers()->sum(
                fn (BiAnnualSiteVisitAnswer $answer): float => $weighted
                    ? $answer->weightedMaximumScore()
                    : $answer->rawMaximumScore()
            ),
            4
        );
    }

    public function aggregateScorePercentage(bool $weighted = true): ?float
    {
        $maximum = $this->aggregateMaximumScore($weighted);

        if ($maximum <= 0) {
            return null;
        }

        return round(($this->aggregateScore($weighted) / $maximum) * 100, 2);
    }

    public function totalScore(bool $weighted = true): float
    {
        return $this->aggregateScore($weighted);
    }

    public function maximumScore(bool $weighted = true): float
    {
        return $this->aggregateMaximumScore($weighted);
    }

    public function scorePercentage(bool $weighted = true): ?float
    {
        return $this->aggregateScorePercentage($weighted);
    }

    private function scoringAnswers(): Collection
    {
        $this->loadMissing('answers.question');

        return $this->answers
            ->filter(fn (BiAnnualSiteVisitAnswer $answer): bool => ! $answer->is_not_applicable)
            ->filter(fn (BiAnnualSiteVisitAnswer $answer): bool => $answer->score !== null);
    }
}
