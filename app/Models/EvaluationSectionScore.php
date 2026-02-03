<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EvaluationSectionScore extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'evaluation_section_id',
        'section_score',
        'strengths',
        'weaknesses',
    ];

    public function submission()
    {
        return $this->belongsTo(EvaluationSubmission::class);
    }

    public function section()
    {
        return $this->belongsTo(EvaluationSection::class, 'evaluation_section_id');
    }

 public function recalculateSectionScore()
{
    // Goods evaluations do NOT aggregate numeric scores
    if ($this->submission->evaluation->type === 'goods') {
        return;
    }

    $criteriaTotal = \App\Models\EvaluationCriteriaScore::where('submission_id', $this->submission_id)
        ->whereIn(
            'evaluation_criteria_id',
            $this->section->criteria->pluck('id')
        )
        ->sum('score');

    $this->update([
        'section_score' => round($criteriaTotal, 2)
    ]);

    $this->submission->recalculateTotals();
}


}
