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
        'supporting_evidence' => 'Supporting Evidence',
        'me_matrix' => 'M&E Matrix',
    ];

    protected $table = 'me_knowledge_evidence_items';

    protected $fillable = [
        'portfolio_id',
        'folder_id',
        'title',
        'document_type',
        'repository_category',
        'description',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'checksum_sha256',
        'version_number',
        'external_url',
        'validation_status',
        'validated_by',
        'validated_at',
        'validation_notes',
        'created_by',
        'updated_by',
        'retired_at',
        'retired_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'validated_at' => 'datetime',
        'version_number' => 'integer',
        'retired_at' => 'datetime',
    ];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'portfolio_id');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MeRepositoryFolder::class, 'folder_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(Indicator::class, 'means_of_verification_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(MeRepositoryDocumentVersion::class, 'repository_item_id')
            ->orderByDesc('version_number');
    }

    public function links(): HasMany
    {
        return $this->hasMany(MeRepositoryDocumentLink::class, 'repository_item_id');
    }

    public function matrixVersions(): HasMany
    {
        return $this->hasMany(MeMatrixVersion::class, 'repository_item_id');
    }

    public function reportDocuments(): HasMany
    {
        return $this->hasMany(MePerformanceReportDocument::class, 'repository_item_id');
    }

    public function typeLabel(): string
    {
        return self::DOCUMENT_TYPES[$this->document_type] ?? 'Other';
    }
}
