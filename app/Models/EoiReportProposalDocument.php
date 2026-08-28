<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EoiReportProposalDocument extends BaseModel
{
    protected $fillable = [
        'recipient_id',
        'uploaded_by',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'sha256',
    ];

    protected function casts(): array
    {
        return ['file_size' => 'integer'];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(EoiReportCommunicationRecipient::class, 'recipient_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
