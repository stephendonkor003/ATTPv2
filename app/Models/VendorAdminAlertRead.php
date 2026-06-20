<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorAdminAlertRead extends BaseModel
{
    protected $fillable = [
        'admin_id',
        'alert_type',
        'source_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
