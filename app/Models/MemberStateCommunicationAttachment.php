<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberStateCommunicationAttachment extends BaseModel
{
    use HasFactory;

    protected $table = 'myb_member_state_communication_attachments';

    protected $fillable = [
        'communication_id',
        'file_path',
        'file_name',
        'mime_type',
        'file_size_bytes',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
    ];

    public function communication(): BelongsTo
    {
        return $this->belongsTo(MemberStateCommunication::class, 'communication_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
