<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataWarehouseFile extends BaseModel
{
    protected $fillable = [
        'record_id',
        'title',
        'description',
        'original_name',
        'path',
        'disk',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(DataWarehouseRecord::class, 'record_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
