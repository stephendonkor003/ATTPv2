<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TreatyMemberStateStatus extends BaseModel
{
    use HasFactory;

    protected $table = 'myb_treaty_member_state_statuses';

    protected $fillable = [
        'treaty_id',
        'member_state_id',
        'is_signed',
        'signed_at',
        'signed_by_user_id',
        'signed_document_path',
        'signed_document_name',
        'signed_notes',
        'signed_service_code',
        'signed_service_code_verified_at',
        'signed_service_code_verified_by_user_id',
        'is_ratified',
        'ratified_at',
        'ratified_by_user_id',
        'ratified_document_path',
        'ratified_document_name',
        'ratified_notes',
        'ratified_service_code',
        'ratified_service_code_verified_at',
        'ratified_service_code_verified_by_user_id',
        'is_original_submitted',
        'original_submitted_at',
        'original_submitted_by_user_id',
        'original_document_path',
        'original_document_name',
        'original_notes',
        'updated_by',
    ];

    protected $casts = [
        'is_signed' => 'boolean',
        'signed_at' => 'datetime',
        'signed_service_code_verified_at' => 'datetime',
        'is_ratified' => 'boolean',
        'ratified_at' => 'datetime',
        'ratified_service_code_verified_at' => 'datetime',
        'is_original_submitted' => 'boolean',
        'original_submitted_at' => 'datetime',
    ];

    public function treaty(): BelongsTo
    {
        return $this->belongsTo(Treaty::class, 'treaty_id');
    }

    public function memberState(): BelongsTo
    {
        return $this->belongsTo(AuMemberState::class, 'member_state_id');
    }

    public function signedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by_user_id');
    }

    public function signedServiceCodeVerifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_service_code_verified_by_user_id');
    }

    public function ratifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ratified_by_user_id');
    }

    public function ratifiedServiceCodeVerifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ratified_service_code_verified_by_user_id');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function originalSubmittedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_submitted_by_user_id');
    }

    public static function generateUniqueServiceCode(string $column): string
    {
        do {
            $candidate = implode('-', str_split(Str::upper(Str::random(16)), 4));
        } while (self::query()->where($column, $candidate)->exists());

        return $candidate;
    }

    public function hasVerifiedProofOfService(): bool
    {
        return !empty($this->signed_service_code_verified_at) && !empty($this->ratified_service_code_verified_at);
    }
}
