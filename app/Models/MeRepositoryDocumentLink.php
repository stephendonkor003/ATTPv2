<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MeRepositoryDocumentLink extends BaseModel
{
    protected $table = 'me_repository_document_links';

    protected $fillable = [
        'repository_item_id',
        'linkable_type',
        'linkable_id',
        'purpose',
        'linked_by',
    ];

    public function repositoryItem(): BelongsTo
    {
        return $this->belongsTo(MeKnowledgeEvidenceItem::class, 'repository_item_id');
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}
