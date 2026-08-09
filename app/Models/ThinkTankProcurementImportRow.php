<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThinkTankProcurementImportRow extends BaseModel
{
    protected $table = 'attp_think_tank_procurement_import_rows';

    protected $guarded = [];

    protected $casts = ['row_payload' => 'array'];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ThinkTankProcurementImportBatch::class, 'batch_id');
    }
}
