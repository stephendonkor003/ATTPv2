<?php

namespace App\Models;

use App\Models\BaseModel;

class ProcurementFormAssignment extends BaseModel
{
    protected $fillable = [
        'procurement_id',
        'form_id',
        'stage',
        'created_by',
    ];

    public function procurement()
    {
        return $this->belongsTo(Procurement::class)->withTrashed();
    }

    public function form()
    {
        return $this->belongsTo(DynamicForm::class);
    }
}
