<?php

namespace App\Models;

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
        $affectedSections = collect([$this->section])
            ->merge($this->section->ancestors());

        // Categorical evaluations do not aggregate numeric scores.
        if (! $this->submission->evaluation->usesNumericScoring()) {
            self::query()
                ->where('submission_id', $this->submission_id)
                ->whereIn('evaluation_section_id', $affectedSections->pluck('id'))
                ->update(['section_score' => null]);

            return;
        }

        foreach ($affectedSections as $section) {
            $criteriaTotal = EvaluationCriteriaScore::query()
                ->where('submission_id', $this->submission_id)
                ->whereIn(
                    'evaluation_criteria_id',
                    $section->subtreeCriteria()->pluck('id')
                )
                ->sum('score');

            self::query()->updateOrCreate(
                [
                    'submission_id' => $this->submission_id,
                    'evaluation_section_id' => $section->id,
                ],
                ['section_score' => round($criteriaTotal, 2)]
            );
        }

        $this->submission->recalculateTotals();
    }
}
