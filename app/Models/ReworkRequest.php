<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\BaseModel;

class ReworkRequest extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'evaluation_id',
        'evaluator_id',
        'message',
        'status',
    ];

    // Relationships
    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }
}