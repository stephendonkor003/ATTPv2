<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreatySupportingDocument extends BaseModel
{
    use HasFactory;

    protected $table = 'myb_treaty_supporting_documents';

    protected $fillable = [
        'treaty_id',
        'title',
        'document_type',
        'file_path',
        'file_name',
        'uploaded_by',
    ];

    public function treaty(): BelongsTo
    {
        return $this->belongsTo(Treaty::class, 'treaty_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
