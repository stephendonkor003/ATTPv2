<?php

// App\Models\ProcurementFormMap.php
namespace App\Models;

use App\Models\BaseModel;

class ProcurementFormMap extends BaseModel
{
    protected $fillable = [
        'procurement_id',
        'form_id',
        'stage'
    ];

    public function procurement()
    {
        return $this->belongsTo(Procurement::class);
    }

    public function form()
    {
        return $this->belongsTo(DynamicForm::class);
    }
}