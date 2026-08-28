<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EoiTechnicalProposalDocument extends BaseModel
{
    protected $fillable = [
        'proposal_submission_id',
        'document_label',
        'file_path',
        'original_filename',
        'extension',
        'mime_type',
        'file_size',
        'sha256',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['file_size' => 'integer'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(EoiTechnicalProposalSubmission::class, 'proposal_submission_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
