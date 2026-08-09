<?php

namespace App\Models;

class MePerformanceThreshold extends BaseModel
{
    protected $table = 'me_performance_thresholds';

    protected $fillable = [
        'framework_id', 'code', 'label', 'minimum_percent', 'maximum_percent',
        'color', 'display_order',
    ];

    protected $casts = [
        'minimum_percent' => 'decimal:2',
        'maximum_percent' => 'decimal:2',
        'display_order' => 'integer',
    ];
}
