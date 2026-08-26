<?php

// App\Models\ProcurementAuditLog.php
namespace App\Models;

use App\Models\BaseModel;

class ProcurementAuditLog extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'procurement_id',
        'form_id',
        'submission_id',
        'metadata',
        'created_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function procurement()
    {
        return $this->belongsTo(Procurement::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
