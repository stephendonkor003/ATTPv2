<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ThinkTankProcurementImportBatch extends BaseModel
{
    protected $table = 'attp_think_tank_procurement_import_batches';

    protected $guarded = [];

    protected $casts = ['summary' => 'array'];

    public function rows(): HasMany
    {
        return $this->hasMany(ThinkTankProcurementImportRow::class, 'batch_id');
    }
}
