<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeMatrixVersion extends BaseModel
{
    public const STATUSES = [
        'draft' => 'Draft',
        'active' => 'Active',
        'retired' => 'Retired',
    ];

    protected $table = 'me_matrix_versions';

    protected $fillable = [
        'portfolio_id',
        'repository_item_id',
        'title',
        'matrix_code',
        'version_number',
        'effective_from',
        'effective_to',
        'status',
        'change_summary',
        'import_summary',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'import_summary' => 'array',
        'approved_at' => 'datetime',
    ];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'portfolio_id');
    }

    public function repositoryItem(): BelongsTo
    {
        return $this->belongsTo(MeKnowledgeEvidenceItem::class, 'repository_item_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
