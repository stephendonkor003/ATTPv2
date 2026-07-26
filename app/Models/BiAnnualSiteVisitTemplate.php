<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BiAnnualSiteVisitTemplate extends BaseModel
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    protected $table = 'biannual_site_visit_templates';

    protected $fillable = [
        'code',
        'version',
        'name',
        'description',
        'instructions',
        'status',
        'is_default',
        'settings',
        'visibility',
        'created_by',
        'updated_by',
        'published_by',
        'published_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'is_default' => 'boolean',
        'settings' => 'array',
        'visibility' => 'array',
        'published_at' => 'datetime',
    ];

    public function sections(): HasMany
    {
        return $this->hasMany(BiAnnualSiteVisitSection::class, 'template_id')
            ->orderBy('sort_order')
            ->orderBy('created_at');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(BiAnnualSiteVisitQuestion::class, 'template_id')
            ->orderBy('sort_order')
            ->orderBy('created_at');
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(BiAnnualSiteVisitProfile::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function scopeForCode(Builder $query, string $code): Builder
    {
        return $query->where('code', $code);
    }

    public function scopeVersion(Builder $query, int $version): Builder
    {
        return $query->where('version', $version);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ARCHIVED);
    }

    public function scopeDefaultTemplate(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->defaultTemplate();
    }

    public function scopeLatestVersion(Builder $query): Builder
    {
        return $query->orderByDesc('version');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query
            ->published()
            ->whereNotExists(function ($newerVersion) use ($table): void {
                $newerVersion
                    ->selectRaw('1')
                    ->from($table.' as newer_template')
                    ->whereColumn('newer_template.code', $table.'.code')
                    ->where('newer_template.status', self::STATUS_PUBLISHED)
                    ->whereColumn('newer_template.version', '>', $table.'.version');
            });
    }

    public function scopeWithStructure(Builder $query): Builder
    {
        return $query->with([
            'sections.topics.questions' => fn ($questions) => $questions
                ->orderBy('sort_order')
                ->orderBy('created_at'),
        ]);
    }

    public static function nextVersionForCode(string $code): int
    {
        return ((int) static::query()->forCode($code)->max('version')) + 1;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function maximumScore(): float
    {
        $questions = $this->relationLoaded('questions')
            ? $this->questions
            : $this->questions()->get();

        return round(
            (float) $questions
                ->where('is_scored', true)
                ->sum(fn (BiAnnualSiteVisitQuestion $question): float => $question->weightedMaximumScore()),
            4
        );
    }

    public function questionnaireSnapshot(): array
    {
        $this->loadMissing('sections.topics.questions');

        return [
            'template' => [
                'id' => (string) $this->id,
                'code' => (string) $this->code,
                'version' => (int) $this->version,
                'name' => (string) $this->name,
                'description' => $this->description,
                'instructions' => $this->instructions,
                'settings' => $this->settings,
                'visibility' => $this->visibility,
            ],
            'sections' => $this->sections->map(fn (BiAnnualSiteVisitSection $section): array => [
                'id' => (string) $section->id,
                'section_key' => (string) $section->section_key,
                'title' => (string) $section->title,
                'description' => $section->description,
                'guidance' => $section->guidance,
                'settings' => $section->settings,
                'visibility' => $section->visibility,
                'sort_order' => (int) $section->sort_order,
                'topics' => $section->topics->map(fn (BiAnnualSiteVisitTopic $topic): array => [
                    'id' => (string) $topic->id,
                    'topic_key' => (string) $topic->topic_key,
                    'title' => (string) $topic->title,
                    'description' => $topic->description,
                    'guidance' => $topic->guidance,
                    'settings' => $topic->settings,
                    'visibility' => $topic->visibility,
                    'sort_order' => (int) $topic->sort_order,
                    'questions' => $topic->questions->map(
                        fn (BiAnnualSiteVisitQuestion $question): array => $question->snapshot()
                    )->values()->all(),
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
