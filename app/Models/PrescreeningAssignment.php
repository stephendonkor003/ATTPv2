<?php
namespace App\Models;

use App\Models\BaseModel;

class PrescreeningAssignment extends BaseModel
{
    protected $fillable = [
        'procurement_id',
        'user_id',
        'assigned_by',
        'assigned_at',
    ];

    public $timestamps = false;

    public function procurement()
    {
        return $this->belongsTo(Procurement::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}