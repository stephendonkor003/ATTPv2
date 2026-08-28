<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EoiTechnicalProposalRule extends BaseModel
{
    public const CATEGORY_GENERAL = 'general';

    public const CATEGORY_ELIGIBILITY = 'eligibility';

    public const CATEGORY_DOCUMENT = 'document';

    public const CATEGORY_DEADLINE = 'deadline';

    public const CATEGORY_CHANNEL = 'channel';

    public const CATEGORY_DECLARATION = 'declaration';

    protected $fillable = [
        'round_id',
        'code',
        'title',
        'description',
        'category',
        'is_mandatory',
        'is_disqualifying',
        'requires_acknowledgement',
        'sort_order',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
            'is_disqualifying' => 'boolean',
            'requires_acknowledgement' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(EoiTechnicalProposalRound::class, 'round_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(EoiTechnicalProposalRuleApplication::class, 'rule_id');
    }
}
