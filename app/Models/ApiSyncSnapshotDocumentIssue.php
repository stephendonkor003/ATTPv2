<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ApiSyncSnapshotDocumentIssue extends BaseModel
{
    public const UPDATED_AT = null;

    protected $table = 'api_sync_snapshot_document_issues';

    protected $fillable = [
        'pairing_id', 'document_id', 'snapshot_id', 'source_type',
        'source_document_id', 'source_version_id', 'code', 'message', 'context',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('API synchronization document issues are append-only.'));
        static::deleting(fn () => throw new LogicException('API synchronization document issues are append-only.'));
    }

    public function pairing(): BelongsTo
    {
        return $this->belongsTo(ApiSyncPairing::class, 'pairing_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(ApiSyncSnapshotDocument::class, 'document_id');
    }
}
