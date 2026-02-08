<?php

namespace App\Models;

use App\Models\BaseModel;

class ProgramFundingDocument extends BaseModel
{
    protected $table = 'myb_program_funding_documents';

     protected $fillable = [
        'program_funding_id',
        'document_type',
        'description',
        'file_name',      // ✅ ADD THIS
        'file_path',
        'uploaded_by',
    ];

    protected $casts = [
        'issued_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function programFunding()
    {
        return $this->belongsTo(ProgramFunding::class, 'program_funding_id');
    }
}
