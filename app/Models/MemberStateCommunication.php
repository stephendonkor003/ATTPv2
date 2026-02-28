<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberStateCommunication extends BaseModel
{
    use HasFactory;

    protected $table = 'myb_member_state_communications';

    protected $fillable = [
        'member_state_id',
        'communication_date',
        'subject',
        'message',
        'channel',
        'status',
        'response_text',
        'responded_by',
        'responded_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'communication_date' => 'date',
        'responded_at' => 'datetime',
    ];

    public function memberState(): BelongsTo
    {
        return $this->belongsTo(AuMemberState::class, 'member_state_id');
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MemberStateCommunicationAttachment::class, 'communication_id');
    }
}
