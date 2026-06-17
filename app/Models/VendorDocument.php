<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorDocument extends BaseModel
{
    protected $fillable = [
        'user_id',
        'uploaded_by',
        'source_type',
        'source_id',
        'title',
        'document_type',
        'description',
        'file_path',
        'file_name',
        'mime_type',
        'file_size_bytes',
        'tags',
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
