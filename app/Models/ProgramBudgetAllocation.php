<?php

 namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\BaseModel;

class ProgramBudgetAllocation extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'activity_id',
        'sub_activity_id',
        'year',
        'allocated_amount',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }

    public function subActivity()
    {
        return $this->belongsTo(SubActivity::class);
    }
}