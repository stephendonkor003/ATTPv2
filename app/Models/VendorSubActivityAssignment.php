<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorSubActivityAssignment extends BaseModel
{
    protected $fillable = [
        'vendor_id',
        'program_id',
        'project_id',
        'activity_id',
        'sub_activity_id',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    public function subActivity(): BelongsTo
    {
        return $this->belongsTo(SubActivity::class, 'sub_activity_id');
    }
}
