<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Evaluation extends BaseModel
{
    use HasFactory;

    public const TYPE_SERVICES = 'services';

    public const TYPE_GOODS = 'goods';

    public const TYPE_EOI = 'eoi';

    public const MANAGED_TYPES = [
        self::TYPE_SERVICES,
        self::TYPE_GOODS,
        self::TYPE_EOI,
    ];

    public const GOODS_DECISIONS = [
        1 => 'Yes',
        0 => 'No',
    ];

    public const EOI_DECISIONS = [
        2 => 'Qualified',
        1 => 'Average Qualified',
        0 => 'Not Qualified',
    ];

    protected $fillable = [
        'name',
        'description',
        'status',
        'type',
        'portfolio_id',
        'is_portfolio_custom',
        'think_tank_member_id',
        'evaluation_phase',
        'procurement_id',
        'created_by',
    ];

    protected $casts = [
        'is_portfolio_custom' => 'boolean',
    ];

    public static function configurationTypes(): array
    {
        return [
            self::TYPE_SERVICES => [
                'label' => 'Services',
                'mode' => 'Numeric scoring',
                'description' => 'Score every criterion against a configured maximum.',
                'color' => 'primary',
            ],
            self::TYPE_GOODS => [
                'label' => 'Goods',
                'mode' => 'Compliance decision',
                'description' => 'Record a Yes or No decision with an evaluator comment.',
                'color' => 'warning',
            ],
            self::TYPE_EOI => [
                'label' => 'Expression of Interest',
                'mode' => 'Qualification category',
                'description' => 'Classify each criterion as Qualified, Average Qualified, or Not Qualified.',
                'color' => 'info',
            ],
        ];
    }

    public function isServices(): bool
    {
        return $this->type === self::TYPE_SERVICES;
    }

    public function isGoods(): bool
    {
        return $this->type === self::TYPE_GOODS;
    }

    public function isEoi(): bool
    {
        return $this->type === self::TYPE_EOI;
    }

    public function usesNumericScoring(): bool
    {
        return $this->isServices();
    }

    public function usesCategoricalDecisions(): bool
    {
        return $this->isGoods() || $this->isEoi();
    }

    public function decisionOptions(): array
    {
        return match ($this->type) {
            self::TYPE_GOODS => self::GOODS_DECISIONS,
            self::TYPE_EOI => self::EOI_DECISIONS,
            default => [],
        };
    }

    public function decisionLabel(int|string|null $decision): ?string
    {
        if ($decision === null || $decision === '') {
            return null;
        }

        return $this->decisionOptions()[(int) $decision] ?? null;
    }

    public function typeLabel(): string
    {
        return self::configurationTypes()[$this->type]['label'] ?? str($this->type)->headline()->toString();
    }

    public function typeColor(): string
    {
        return self::configurationTypes()[$this->type]['color'] ?? 'secondary';
    }

    /* ===============================
     | RELATIONSHIPS
     =============================== */

    /**
     * Evaluation has many sections
     */
    public function sections(): HasMany
    {
        return $this->hasMany(EvaluationSection::class);
    }

    /**
     * Ordered roots for the four-level evaluation form hierarchy.
     */
    public function rootSections(): HasMany
    {
        return $this->hasMany(EvaluationSection::class)
            ->roots()
            ->ordered();
    }

    /**
     * Load the complete ordered section tree, including criteria at every tier.
     */
    public function orderedSectionTree(): Collection
    {
        return $this->rootSections()
            ->with([
                'criteria',
                'childrenRecursive',
            ])
            ->get();
    }

    /**
     * Depth-first ordered sections. Each returned model has derived depth,
     * level_label and outline_number accessors ready for legacy flat loops.
     */
    public function flattenedSections(): Collection
    {
        $flattened = collect();

        $walk = function (Collection $sections, string $prefix, int $depth) use (&$walk, $flattened): void {
            foreach ($sections->values() as $index => $section) {
                $outlineNumber = ltrim($prefix.'.'.($index + 1), '.');
                $section->setHierarchyContext($depth, $outlineNumber);
                $flattened->push($section);

                $children = $section->relationLoaded('childrenRecursive')
                    ? $section->getRelation('childrenRecursive')
                    : collect();

                $walk($children, $outlineNumber, $depth + 1);
            }
        };

        $walk($this->orderedSectionTree(), '', 1);

        return $flattened;
    }

    /**
     * Evaluation assigned to procurements
     */
    public function procurements()
    {
        return $this->belongsToMany(
            Procurement::class,
            'procurement_evaluations',
            'evaluation_id',
            'procurement_id'
        );
    }

    public function portfolio()
    {
        return $this->belongsTo(Sector::class, 'portfolio_id');
    }

    public function thinkTankMember()
    {
        return $this->belongsTo(ConsortiumThinkTank::class, 'think_tank_member_id');
    }

    public function procurement()
    {
        return $this->belongsTo(Procurement::class);
    }

    /**
     * Users assigned to evaluate
     */
    public function assignments()
    {
        return $this->hasMany(EvaluationAssignment::class);
    }

    /**
     * Creator (admin)
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function criteria()
    {
        return $this->hasManyThrough(
            EvaluationCriteria::class,
            EvaluationSection::class,
            'evaluation_id',          // FK on evaluation_sections
            'evaluation_section_id',  // FK on evaluation_criteria
            'id',
            'id'
        );
    }
}
