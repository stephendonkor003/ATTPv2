<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrmGrievanceAttachment extends BaseModel
{
    protected $fillable = [
        'grievance_id',
        'uploaded_by',
        'title',
        'file_path',
        'file_name',
        'mime_type',
        'file_size_bytes',
    ];

    public function grievance(): BelongsTo
    {
        return $this->belongsTo(GrmGrievance::class, 'grievance_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
