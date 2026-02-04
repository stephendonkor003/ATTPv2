<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementContractDocument extends BaseModel
{
    protected $table = 'procurement_contract_documents';

    protected $fillable = [
        'negotiation_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'uploaded_by',
    ];

    public function negotiation(): BelongsTo
    {
        return $this->belongsTo(ProcurementContractNegotiation::class, 'negotiation_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
