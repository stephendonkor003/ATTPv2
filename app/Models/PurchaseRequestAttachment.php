<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestAttachment extends BaseModel
{
    protected $table = 'myb_purchase_request_attachments';

    protected $fillable = [
        'purchase_request_id',
        'uploaded_by',
        'document_type',
        'title',
        'file_path',
        'file_name',
        'mime_type',
        'file_size_bytes',
    ];

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
