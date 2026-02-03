<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EvaluationSubmission extends BaseModel
{
    use HasFactory;

    /**
     * Explicit table name (safety)
     */
    protected $table = 'evaluation_submissions';

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'evaluation_id',
        'procurement_id',
        'evaluator_id',
        'form_submission_id',   // applicant (form submission)
        'overall_score',
        'comments',
        'video_path',
        'video_duration',
        'submitted_at',
    ];

    /**
     * Attribute casting
     */
    protected $casts = [
        'submitted_at' => 'datetime',
        'overall_score' => 'float',
    ];

    /* =====================================================
     | RELATIONSHIPS
     ===================================================== */

    /**
     * Evaluation definition
     */
    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }

    /**
     * Procurement context
     */
    public function procurement()
    {
        return $this->belongsTo(Procurement::class);
    }

    /**
     * Evaluator (user)
     */
    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    /**
     * Applicant being evaluated (form submission)
     */
    public function applicant()
    {
        return $this->belongsTo(
            FormSubmission::class,
            'form_submission_id'
        );
    }

    /**
     * Evaluator scores per criterion
     * → evaluation_criteria_scores.submission_id
     */
    public function criteriaScores()
    {
        return $this->hasMany(
            EvaluationCriteriaScore::class,
            'submission_id'
        );
    }

    /**
     * Evaluator section summaries
     * → evaluation_section_scores.submission_id
     */
    public function sectionScores()
    {
        return $this->hasMany(
            EvaluationSectionScore::class,
            'submission_id'
        );
    }

    /* =====================================================
     | CALCULATIONS
     ===================================================== */

    /**
     * Recalculate overall score
     * Source of truth = criteria scores
     */
    public function recalculateTotals(): void
{
    if ($this->evaluation->type === 'goods') {
        $this->forceFill(['overall_score' => null])->saveQuietly();
        return;
    }

    $overall = $this->criteriaScores()->sum('score');

    $this->forceFill([
        'overall_score' => round($overall, 2),
    ])->saveQuietly();
}


    /* =====================================================
     | STATE HELPERS
     ===================================================== */

    /**
     * Check if evaluation is final
     */
    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }
}
