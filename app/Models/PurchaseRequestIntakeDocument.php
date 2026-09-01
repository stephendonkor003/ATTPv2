<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestIntakeDocument extends BaseModel
{
    protected $table = 'purchase_request_intake_documents';

    protected $fillable = [
        'intake_id',
        'uploaded_by',
        'file_path',
        'file_name',
        'mime_type',
        'file_size_bytes',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
    ];

    public function intake(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestIntake::class, 'intake_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
