<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EoiReportCommunicationAttachment extends BaseModel
{
    protected $fillable = [
        'communication_id',
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

    public function communication(): BelongsTo
    {
        return $this->belongsTo(EoiReportCommunication::class, 'communication_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
