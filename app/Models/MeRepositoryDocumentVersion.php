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

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function formattedSize(): string
    {
        if (! $this->file_size) {
            return 'Size unavailable';
        }
        if ($this->file_size >= 1_048_576) {
            return number_format($this->file_size / 1_048_576, 1).' MB';
        }

        return number_format(max($this->file_size, 1) / 1024, 1).' KB';
    }
}
