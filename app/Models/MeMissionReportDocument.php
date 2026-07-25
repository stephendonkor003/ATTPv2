<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeMissionReportDocument extends BaseModel
{
    protected $table = 'me_mission_report_documents';

    protected $fillable = [
        'mission_report_id', 'document_name', 'file_path', 'original_filename',
        'mime_type', 'file_size', 'uploaded_by',
    ];

    protected $casts = ['file_size' => 'integer'];

    public function report(): BelongsTo { return $this->belongsTo(MeMissionReport::class, 'mission_report_id'); }
    public function uploadedBy(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
}
