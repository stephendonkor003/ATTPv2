<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndicatorCodeHistory extends BaseModel
{
    public const UPDATED_AT = null;

    public const CREATED_AT = 'changed_at';

    protected $table = 'me_indicator_code_histories';

    protected $fillable = [
        'indicator_id',
        'old_code',
        'new_code',
        'change_reason',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class, 'indicator_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
