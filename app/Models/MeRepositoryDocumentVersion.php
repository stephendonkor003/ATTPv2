<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeRepositoryDocumentVersion extends BaseModel
{
    protected $table = 'me_repository_document_versions';

    protected $fillable = [
        'repository_item_id',
        'version_number',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'checksum_sha256',
        'change_notes',
        'uploaded_by',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'file_size' => 'integer',
    ];

    public function repositoryItem(): BelongsTo
    {
        return $this->belongsTo(MeKnowledgeEvidenceItem::class, 'repository_item_id');
    }
}
