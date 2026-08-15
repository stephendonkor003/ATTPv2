<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class EvaluationSection extends BaseModel
{
    use HasFactory;

    public const MAX_DEPTH = 4;

    public const LEVEL_LABELS = [
        1 => 'Section',
        2 => 'Sub-Section',
        3 => 'Sub-Sub Section',
        4 => 'Sub-Sub-Sub Section',
    ];

    protected $fillable = [
        'evaluation_id',
        'parent_section_id',
        'name',
        'description',
        'show_subtotal',
        'sort_order',
    ];

    protected $casts = [
        'show_subtotal' => 'boolean',
        'sort_order' => 'integer',
    ];

    private ?int $resolvedHierarchyDepth = null;

    private ?string $resolvedOutlineNumber = null;

    protected static function booted(): void
    {
        static::deleting(function (self $section): void {
            // The legacy schema did not add cascading foreign keys for criteria or
            // score rows. Delete them explicitly so removing a branch cannot leave
            // hidden/orphaned evaluation data behind.
            $section->children()->get()->each(
                fn (self $child) => $child->delete()
            );

            $criteriaIds = $section->criteria()->pluck('id');

            if ($criteriaIds->isNotEmpty()) {
                EvaluationCriteriaScore::query()
                    ->whereIn('evaluation_criteria_id', $criteriaIds)
                    ->delete();
            }

            EvaluationSectionScore::query()
                ->where('evaluation_section_id', $section->id)
                ->delete();

            $section->criteria()->delete();
        });
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_section_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_section_id')->ordered();
    }

    /**
     * Recursive relationship used to load a complete, ordered branch in one graph.
     */
    public function childrenRecursive(): HasMany
    {
        return $this->children()->with([
            'criteria',
            'childrenRecursive',
        ]);
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(EvaluationCriteria::class)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_section_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * Ancestors ordered from the immediate parent to the root section.
     */
    public function ancestors(): Collection
    {
        $ancestors = collect();
        $seen = [$this->getKey() => true];
        $current = $this;

        while (filled($current->parent_section_id)) {
            $parent = $current->relationLoaded('parent')
                ? $current->getRelation('parent')
                : $current->parent()->first();

            if (! $parent || isset($seen[$parent->getKey()])) {
                break;
            }

            $ancestors->push($parent);
            $seen[$parent->getKey()] = true;
            $current = $parent;
        }

        return $ancestors;
    }

    /**
     * Descendants in depth-first display order.
     */
    public function descendants(): Collection
    {
        $descendants = collect();
        $seen = [$this->getKey() => true];

        $walk = function (self $section) use (&$walk, $descendants, &$seen): void {
            $children = $section->relationLoaded('childrenRecursive')
                ? $section->getRelation('childrenRecursive')
                : $section->childrenRecursive()->get();

            foreach ($children as $child) {
                if (isset($seen[$child->getKey()])) {
                    continue;
                }

                $seen[$child->getKey()] = true;
                $descendants->push($child);
                $walk($child);
            }
        };

        $walk($this);

        return $descendants;
    }

    public function subtreeSections(): Collection
    {
        return collect([$this])->merge($this->descendants());
    }

    public function subtreeIds(): Collection
    {
        return $this->subtreeSections()
            ->map(fn (self $section): string => (string) $section->getKey())
            ->values();
    }

    public function subtreeCriteria(): Collection
    {
        return $this->subtreeSections()
            ->flatMap(function (self $section): Collection {
                return $section->relationLoaded('criteria')
                    ? $section->getRelation('criteria')
                    : $section->criteria()->get();
            })
            ->values();
    }

    public function subtotalMaxScore(): float
    {
        return round((float) $this->subtreeCriteria()->sum('max_score'), 2);
    }

    public function containsDescendant(self|string $section): bool
    {
        $sectionId = $section instanceof self ? $section->getKey() : $section;

        return $this->descendants()->contains(
            fn (self $descendant): bool => $descendant->getKey() === $sectionId
        );
    }

    public function isLeaf(): bool
    {
        if ($this->relationLoaded('childrenRecursive')) {
            return $this->getRelation('childrenRecursive')->isEmpty();
        }

        return ! $this->children()->exists();
    }

    public function subtreeHeight(): int
    {
        $baseDepth = $this->depth;

        return $this->subtreeSections()
            ->map(fn (self $section): int => $section->depth - $baseDepth + 1)
            ->max() ?? 1;
    }

    public function getDepthAttribute(): int
    {
        return $this->resolvedHierarchyDepth ?? ($this->ancestors()->count() + 1);
    }

    public function getLevelLabelAttribute(): string
    {
        return $this->levelLabel();
    }

    public function getOutlineNumberAttribute(): string
    {
        return $this->resolvedOutlineNumber ?? $this->resolveOutlineNumber();
    }

    public function levelLabel(?int $depth = null): string
    {
        $depth ??= $this->depth;

        return self::LEVEL_LABELS[$depth] ?? 'Section';
    }

    public function setHierarchyContext(int $depth, string $outlineNumber): self
    {
        $this->resolvedHierarchyDepth = $depth;
        $this->resolvedOutlineNumber = $outlineNumber;

        return $this;
    }

    private function resolveOutlineNumber(): string
    {
        $path = $this->ancestors()->reverse()->push($this);
        $segments = [];

        foreach ($path as $section) {
            $siblings = filled($section->parent_section_id)
                ? self::query()->where('parent_section_id', $section->parent_section_id)->ordered()->pluck('id')
                : self::query()
                    ->where('evaluation_id', $section->evaluation_id)
                    ->roots()
                    ->ordered()
                    ->pluck('id');

            $position = $siblings->search($section->getKey(), strict: true);
            $segments[] = $position === false ? 1 : $position + 1;
        }

        return implode('.', $segments);
    }
}
