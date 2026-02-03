<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\EvaluationCriteria;
use App\Models\EvaluationSection;


class Evaluation extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'status',
        'type',
        'created_by',
    ];

    /* ===============================
     | RELATIONSHIPS
     =============================== */

    /**
     * Evaluation has many sections
     */
    public function sections()
    {
        return $this->hasMany(EvaluationSection::class);
    }

    /**
     * Evaluation assigned to procurements
     */
    public function procurements()
    {
        return $this->belongsToMany(
            Procurement::class,
            'procurement_evaluations',
            'evaluation_id',
            'procurement_id'
        );
    }

    /**
     * Users assigned to evaluate
     */
    public function assignments()
    {
        return $this->hasMany(EvaluationAssignment::class);
    }

    /**
     * Creator (admin)
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }



public function criteria()
{
    return $this->hasManyThrough(
        EvaluationCriteria::class,
        EvaluationSection::class,
        'evaluation_id',          // FK on evaluation_sections
        'evaluation_section_id',  // FK on evaluation_criteria
        'id',
        'id'
    );
}

}