<?php

// App\Models\ProcurementUserPermission.php
namespace App\Models;

use App\Models\BaseModel;

class ProcurementUserPermission extends BaseModel
{
    protected $fillable = [
        'user_id',
        'procurement_id',
        'form_id',
        'stage',
        'permission',
        'assigned_by',
        'assigned_at'
    ];

    protected $dates = ['assigned_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function procurement()
    {
        return $this->belongsTo(Procurement::class);
    }

    public function form()
    {
        return $this->belongsTo(DynamicForm::class);
    }
}