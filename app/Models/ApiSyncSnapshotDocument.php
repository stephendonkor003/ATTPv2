<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiSyncSnapshotDocument extends BaseModel
{
    public const STATE_PREPARING = 'preparing';

    public const STATE_READY = 'ready';

    public const STATE_HELD = 'held';

    public const STATE_PURGED = 'purged';

    protected $table = 'api_sync_snapshot_documents';

    protected $fillable = [
        'pairing_id', 'snapshot_id', 'sequence', 'source_type', 'source_document_id',
        'source_version_id', 'source_key', 'source_revision', 'category', 'classification', 'title',
        'display_filename', 'detected_mime', 'byte_size', 'sha256',
        'portfolio_external_id', 'project_external_ids', 'parent_type',
        'parent_external_id', 'source_updated_at', 'storage_disk', 'storage_path',
        'state', 'hold_code', 'hold_message', 'copied_at', 'purged_at',
    ];

    protected $hidden = ['storage_disk', 'storage_path', 'source_key', 'source_revision'];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'byte_size' => 'integer',
            'project_external_ids' => 'array',
            'source_updated_at' => 'immutable_datetime',
            'copied_at' => 'immutable_datetime',
            'purged_at' => 'immutable_datetime',
        ];
    }

    public function pairing(): BelongsTo
    {
        return $this->belongsTo(ApiSyncPairing::class, 'pairing_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(ApiSyncSnapshotDocumentIssue::class, 'document_id');
    }
}
