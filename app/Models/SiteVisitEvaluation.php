<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\BaseModel;

class SiteVisitEvaluation extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'consortium_id',
        'team_id',
        'leader_id',
        'evaluator_id',
        'evaluation_date',

        // ===== 1. ORGANIZATIONAL CAPACITY (10 points) =====
        's1_1_score', 's1_1_strength', 's1_1_weakness',
        's1_2_score', 's1_2_strength', 's1_2_weakness',
        's1_3_score', 's1_3_strength', 's1_3_weakness',
        's1_4_score', 's1_4_strength', 's1_4_weakness',
        's1_comments',

        // ===== 2. TECHNICAL CAPABILITY (5 points) =====
        's2_1_score', 's2_1_strength', 's2_1_weakness',
        's2_2_score', 's2_2_strength', 's2_2_weakness',
        's2_3_score', 's2_3_strength', 's2_3_weakness',
        's2_comments',

        // ===== 3. PARTNERSHIPS & COLLABORATION (5 points) =====
        's3_1_score', 's3_1_strength', 's3_1_weakness',
        's3_2_score', 's3_2_strength', 's3_2_weakness',
        's3_3_score', 's3_3_strength', 's3_3_weakness',
        's3_comments',

        // ===== 4. INNOVATION & IMPACT (5 points) =====
        's4_1_score', 's4_1_strength', 's4_1_weakness',
        's4_2_score', 's4_2_strength', 's4_2_weakness',
        's4_3_score', 's4_3_strength', 's4_3_weakness',
        's4_comments',

        // ===== 5. SUSTAINABILITY (5 points) =====
        's5_1_score', 's5_1_strength', 's5_1_weakness',
        's5_2_score', 's5_2_strength', 's5_2_weakness',
        's5_3_score', 's5_3_strength', 's5_3_weakness',
        's5_comments',

        // ===== 6. FACILITY & RESOURCE ADEQUACY (5 points) =====
        's6_1_score', 's6_1_strength', 's6_1_weakness',
        's6_2_score', 's6_2_strength', 's6_2_weakness',
        's6_3_score', 's6_3_strength', 's6_3_weakness',
        's6_comments',

        // ===== TOTALS & GENERAL =====
        'total_score',
        'general_observations',
        'overall_strength',
        'overall_weakness',
        'additional_comments',
        'evaluator_name',
        'evaluator_signature',
         'rework_status', 'rework_comment',
         'rework_requested_by', 'rework_completed_by',
    ];

    /* =======================
     |  Relationships
     ======================= */
    public function consortium()
    {
        return $this->belongsTo(\App\Models\Applicant::class, 'consortium_id');
    }

    public function team()
    {
        return $this->belongsTo(\App\Models\EvaluatorTeam::class, 'team_id');
    }

    public function leader()
    {
        return $this->belongsTo(\App\Models\User::class, 'leader_id');
    }

    public function evaluator()
    {
        return $this->belongsTo(\App\Models\User::class, 'evaluator_id');
    }

    /* =======================
     |  Computed Attributes
     ======================= */
    public function getComputedTotalAttribute()
    {
        $fields = [
            's1_1_score','s1_2_score','s1_3_score','s1_4_score',
            's2_1_score','s2_2_score','s2_3_score',
            's3_1_score','s3_2_score','s3_3_score',
            's4_1_score','s4_2_score','s4_3_score',
            's5_1_score','s5_2_score','s5_3_score',
            's6_1_score','s6_2_score','s6_3_score'
        ];

        return collect($fields)->reduce(fn($sum, $f) => $sum + ($this->$f ?? 0), 0);
    }
}
