<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorInformationRequest extends BaseModel
{
    protected $fillable = [
        'user_id',
        'procurement_id',
        'request_topic',
        'details',
        'response',
        'responded_by',
        'responded_at',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }
}
