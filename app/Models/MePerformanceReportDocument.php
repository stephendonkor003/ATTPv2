<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MePerformanceReportDocument extends BaseModel
{
    protected $table = 'me_performance_report_documents';

    protected $fillable = [
        'report_id',
        'document_name',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'validation_status',
        'validated_by',
        'validated_at',
        'validation_notes',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'validated_at' => 'datetime',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(MePerformanceReport::class, 'report_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function formattedSize(): string
    {
        if (! $this->file_size) {
            return 'Unknown size';
        }

        if ($this->file_size >= 1_048_576) {
            return number_format($this->file_size / 1_048_576, 1).' MB';
        }

        return number_format(max($this->file_size, 1) / 1_024, 1).' KB';
    }
}
