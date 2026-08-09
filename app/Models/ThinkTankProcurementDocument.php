<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThinkTankProcurementDocument extends BaseModel
{
    protected $table = 'attp_think_tank_procurement_documents';

    protected $fillable = [
        'item_id', 'document_type', 'document_name', 'original_name',
        'file_path', 'mime_type', 'file_size', 'uploaded_by',
    ];

    protected $casts = ['file_size' => 'integer'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(ThinkTankProcurementItem::class, 'item_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getFormattedSizeAttribute(): string
    {
        if ($this->file_size >= 1_048_576) {
            return number_format($this->file_size / 1_048_576, 1).' MB';
        }

        return number_format(max($this->file_size, 1) / 1_024, 1).' KB';
    }
}
