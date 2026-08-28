<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EoiReportCommunication extends BaseModel
{
    public const TYPE_EVALUATION_RECORDS = 'evaluation_records';

    public const TYPE_PROPOSAL_INVITATION = 'proposal_invitation';

    protected $fillable = [
        'procurement_id',
        'type',
        'subject',
        'message',
        'created_by',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(EoiReportCommunicationRecipient::class, 'communication_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EoiReportCommunicationAttachment::class, 'communication_id');
    }
}
