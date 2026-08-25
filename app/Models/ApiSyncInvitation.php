<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ApiSyncInvitation extends BaseModel
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVAL_IN_PROGRESS = 'approval_in_progress';

    public const STATUS_ACTIVATION_PENDING = 'activation_pending';

    public const STATUS_ACTIVATION_RECEIVED = 'activation_received';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'id',
        'protocol_version',
        'central_instance_id',
        'central_name',
        'central_origin',
        'target_origin',
        'confirmation_url',
        'requested_datasets',
        'requested_scopes',
        'credential_digest',
        'signature_key_id',
        'invitation_nonce',
        'invitation_payload_hash',
        'status',
        'issued_at',
        'expires_at',
        'credential_expires_at',
        'received_at',
        'approval_attempts',
        'last_approval_attempt_at',
        'confirmation_request_id',
        'confirmation_request_nonce',
        'approved_by',
        'approved_at',
        'authorization_id',
        'authorization_receipt',
        'authorization_verified_at',
        'activation_received_at',
        'activation_request_id',
        'activation_nonce',
        'activation_payload_hash',
        'confirmation_id',
        'central_run_id',
        'confirmation_receipt',
        'receipt_verified_at',
        'terminal_error_code',
        'declined_at',
        'declined_by',
        'decline_reason',
        'revoked_at',
        'revoked_by',
        'revoke_reason',
        'completed_at',
    ];

    protected $hidden = [
        'credential_digest',
        'invitation_payload_hash',
        'activation_payload_hash',
        'authorization_receipt',
        'confirmation_receipt',
    ];

    protected function casts(): array
    {
        return [
            'requested_datasets' => 'array',
            'requested_scopes' => 'array',
            'confirmation_receipt' => 'encrypted:array',
            'authorization_receipt' => 'encrypted:array',
            'issued_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'credential_expires_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
            'last_approval_attempt_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'authorization_verified_at' => 'immutable_datetime',
            'activation_received_at' => 'immutable_datetime',
            'receipt_verified_at' => 'immutable_datetime',
            'declined_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'approval_attempts' => 'integer',
        ];
    }

    public function pairing(): HasOne
    {
        return $this->hasOne(ApiSyncPairing::class, 'inbound_invitation_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ApiSyncInvitationEvent::class, 'invitation_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function decliner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declined_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isPendingApproval(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVAL_IN_PROGRESS], true)
            && $this->expires_at?->isFuture();
    }

    public function permitsDataset(string $dataset): bool
    {
        return in_array($dataset, (array) $this->requested_datasets, true);
    }
}
