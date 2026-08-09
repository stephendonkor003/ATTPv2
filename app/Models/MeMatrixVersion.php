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

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function formatLabel(): string
    {
        return strtoupper((string) data_get($this->import_summary, 'format', 'File'));
    }

    /** @return array{sheet_count:int, data_rows:int, data_columns:int, formula_cells:int, validated_cells:int} */
    public function inspectionTotals(): array
    {
        $sheets = collect(data_get($this->import_summary, 'sheets', []));

        return [
            'sheet_count' => (int) data_get($this->import_summary, 'sheet_count', $sheets->count()),
            'data_rows' => (int) $sheets->sum('data_rows'),
            'data_columns' => (int) $sheets->sum('data_columns'),
            'formula_cells' => (int) $sheets->sum('formula_cells'),
            'validated_cells' => (int) $sheets->sum('validated_cells'),
        ];
    }
}
