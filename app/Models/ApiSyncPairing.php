<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiSyncPairing extends BaseModel
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CLAIMED = 'claimed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_ABANDONED = 'abandoned';

    protected $fillable = [
        'inbound_invitation_id',
        'code_hash',
        'status',
        'created_by',
        'code_expires_at',
        'claimed_at',
        'claim_idempotency_hash',
        'claim_recovery_hash',
        'consumer_instance',
        'consumer_name',
        'snapshot_id',
        'snapshot_at',
        'snapshot_status',
        'snapshot_started_at',
        'snapshot_materialized_at',
        'snapshot_failed_at',
        'snapshot_purged_at',
        'snapshot_failure_reason',
        'snapshot_record_count',
        'snapshot_bytes',
        'document_snapshot_status',
        'document_snapshot_started_at',
        'document_snapshot_materialized_at',
        'document_snapshot_purged_at',
        'document_snapshot_failure_reason',
        'document_discovered_count',
        'document_ready_count',
        'document_held_count',
        'document_snapshot_bytes',
        'token_hash',
        'token_expires_at',
        'last_used_at',
        'request_count',
        'completed_at',
        'abandoned_at',
        'revoked_at',
        'revoked_by',
        'revoke_reason',
    ];

    protected $hidden = [
        'code_hash',
        'claim_idempotency_hash',
        'claim_recovery_hash',
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'code_expires_at' => 'immutable_datetime',
            'claimed_at' => 'immutable_datetime',
            'snapshot_at' => 'immutable_datetime',
            'snapshot_started_at' => 'immutable_datetime',
            'snapshot_materialized_at' => 'immutable_datetime',
            'snapshot_failed_at' => 'immutable_datetime',
            'snapshot_purged_at' => 'immutable_datetime',
            'snapshot_record_count' => 'integer',
            'snapshot_bytes' => 'integer',
            'document_snapshot_started_at' => 'immutable_datetime',
            'document_snapshot_materialized_at' => 'immutable_datetime',
            'document_snapshot_purged_at' => 'immutable_datetime',
            'document_discovered_count' => 'integer',
            'document_ready_count' => 'integer',
            'document_held_count' => 'integer',
            'document_snapshot_bytes' => 'integer',
            'token_expires_at' => 'immutable_datetime',
            'last_used_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'abandoned_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'request_count' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ApiSyncEvent::class, 'pairing_id');
    }

    public function inboundInvitation(): BelongsTo
    {
        return $this->belongsTo(ApiSyncInvitation::class, 'inbound_invitation_id');
    }

    public function snapshotDocuments(): HasMany
    {
        return $this->hasMany(ApiSyncSnapshotDocument::class, 'pairing_id')->orderBy('sequence');
    }

    public function snapshotDocumentIssues(): HasMany
    {
        return $this->hasMany(ApiSyncSnapshotDocumentIssue::class, 'pairing_id')->latest('created_at');
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_CLAIMED
            && filled($this->token_hash)
            && $this->token_expires_at?->isFuture();
    }
}
