<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EoiTechnicalProposalTemplate extends BaseModel
{
    protected $fillable = [
        'round_id',
        'title',
        'description',
        'sort_order',
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
        return [
            'sort_order' => 'integer',
            'file_size' => 'integer',
        ];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(EoiTechnicalProposalRound::class, 'round_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
