<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EvaluationSection extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'evaluation_id',
        'name',
        'description',
    ];

    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function criteria()
    {
        return $this->hasMany(EvaluationCriteria::class);
    }
}