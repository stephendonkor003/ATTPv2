<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeKnowledgeEvidenceItem extends BaseModel
{
    public const DOCUMENT_TYPES = [
        'means_of_verification' => 'Means of Verification',
        'meal_plan' => 'MEAL Plan',
        'theory_of_change' => 'Theory of Change',
        'evaluation' => 'Evaluation',
        'research' => 'Research / Evidence',
        'other' => 'Other',
    ];

    protected $table = 'me_knowledge_evidence_items';

    protected $fillable = [
        'portfolio_id',
        'title',
        'document_type',
        'description',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'external_url',
        'created_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'portfolio_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(Indicator::class, 'means_of_verification_id');
    }

    public function typeLabel(): string
    {
        return self::DOCUMENT_TYPES[$this->document_type] ?? 'Other';
    }
}
