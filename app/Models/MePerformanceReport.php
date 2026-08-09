<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MePerformanceReport extends BaseModel
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_ARCHIVED = 'archived';

    public const QUARTERS = [
        'Q1' => 'Q1 (January – March)',
        'Q2' => 'Q2 (April – June)',
        'Q3' => 'Q3 (July – September)',
        'Q4' => 'Q4 (October – December)',
    ];

    public const REPORTING_PERIOD_TYPES = [
        'quarter' => 'Quarterly',
        'semi_annual' => 'Semi-Annual',
        'annual' => 'Annual',
    ];

    public const PERIOD_LABELS = [
        'quarter' => [
            'Q1' => 'Q1 (January – March)',
            'Q2' => 'Q2 (April – June)',
            'Q3' => 'Q3 (July – September)',
            'Q4' => 'Q4 (October – December)',
        ],
        'semi_annual' => [
            'H1' => 'H1 (January – June)',
            'H2' => 'H2 (July – December)',
        ],
        'annual' => [
            'ANNUAL' => 'Annual (January – December)',
        ],
    ];

    public const PERFORMANCE_RATINGS = [
        'exceptional' => 'Exceptional',
        'on_track' => 'On Track',
        'at_risk' => 'At Risk',
        'off_track' => 'Off Track',
        'not_rated' => 'Not Rated',
    ];

    protected $table = 'me_performance_reports';

    protected $fillable = [
        'form_id',
        'reporting_period_id',
        'portfolio_id',
        'project_component_id',
        'responsible_directorate_id',
        'think_tank_member_id',
        'assignment_id',
        'reporting_year',
        'reporting_quarter',
        'reporting_period_type',
        'reporting_period_label',
        'reporting_scope',
        'status',
        'key_achievements',
        'variance_explanation',
        'means_of_verification_notes',
        'overall_assessment',
        'performance_rating',
        'conclusion',
        'challenges_faced',
        'mitigation_strategies',
        'lessons_learned',
        'adaptive_management_actions',
        'next_period_priorities',
        'created_by',
        'updated_by',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'verified_by',
        'verified_at',
        'verification_notes',
        'approved_by',
        'approved_at',
        'approval_notes',
        'archived_by',
        'archived_at',
        'archive_notes',
    ];

    protected $casts = [
        'reporting_year' => 'integer',
        'reporting_scope' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'verified_at' => 'datetime',
        'approved_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(MeDataEntryForm::class, 'form_id');
    }

    public function reportingPeriod(): BelongsTo
    {
        return $this->belongsTo(MeReportingPeriod::class, 'reporting_period_id');
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'portfolio_id');
    }

    public function projectComponent(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_component_id');
    }

    public function responsibleDirectorate(): BelongsTo
    {
        return $this->belongsTo(GovernanceNode::class, 'responsible_directorate_id');
    }

    public function thinkTank(): BelongsTo
    {
        return $this->belongsTo(ConsortiumThinkTank::class, 'think_tank_member_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(MeDataCollectionAssignment::class, 'assignment_id');
    }

    public function indicatorResults(): HasMany
    {
        return $this->hasMany(MePerformanceReportIndicatorResult::class, 'report_id')
            ->orderBy('created_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(MePerformanceReportDocument::class, 'report_id')
            ->orderBy('created_at');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(MePerformanceReportTransition::class, 'report_id')
            ->latest('created_at');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isReviewed(): bool
    {
        return in_array($this->status, [self::STATUS_REVIEWED, self::STATUS_VERIFIED, self::STATUS_APPROVED], true);
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    public function isApproved(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_ARCHIVED], true);
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function lifecycleLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft Report',
            self::STATUS_SUBMITTED => 'Submitted Report',
            self::STATUS_REVIEWED => 'Reviewed Report',
            self::STATUS_VERIFIED => 'Verified Report',
            self::STATUS_APPROVED => 'Approved Report',
            self::STATUS_ARCHIVED => 'Archived Report',
            default => str($this->status)->headline()->toString(),
        };
    }

    /**
     * Return the authoritative completion state for the seven mandatory report
     * sections. The same result is used by authors, reviewers and submission
     * validation so lifecycle stages cannot present different requirements.
     *
     * @return array<string, array{
     *     number:int,
     *     title:string,
     *     status:string,
     *     status_label:string,
     *     completed:int,
     *     total:int,
     *     missing:array<int, string>
     * }>
     */
    public function sectionCompletion(): array
    {
        if (! $this->relationLoaded('indicatorResults')) {
            $this->load(['indicatorResults.indicator', 'indicatorResults.achievements']);
        } else {
            $this->indicatorResults->each(function ($result): void {
                if (! $result->relationLoaded('indicator')) {
                    $result->load('indicator');
                }
                if (! $result->relationLoaded('achievements')) {
                    $result->load('achievements');
                }
            });
        }
        if (! $this->relationLoaded('documents')) {
            $this->load('documents');
        }

        $indicatorRequirements = [];
        foreach ($this->indicatorResults as $result) {
            $label = $result->indicator?->indicator_code
                ?: $result->indicator?->name
                ?: 'Linked indicator';
            $hasResult = $result->indicator?->value_type === 'milestone'
                ? filled($result->actual_text)
                : $result->actual_value !== null;
            $indicatorRequirements['Period result for '.$label] = $hasResult
                && filled($result->indicator_result_id);
            $indicatorRequirements['Achievement detail for '.$label] = $result->achievements->isNotEmpty();
            if ($result->indicator?->organization_rollup_method === 'weighted_average') {
                $indicatorRequirements['Weighted roll-up numerator and denominator for '.$label] = $result->rollup_numerator !== null
                    && $result->rollup_denominator !== null
                    && (float) $result->rollup_denominator > 0;
            }
        }
        if ($indicatorRequirements === []) {
            $indicatorRequirements['At least one due indicator result'] = false;
        }

        return [
            'indicator_results' => $this->completionEntry(
                1,
                'Indicator results and progress against target',
                $indicatorRequirements
            ),
            'achievements_variance' => $this->completionEntry(
                2,
                'Achievements and variance',
                [
                    'Key achievements' => filled($this->key_achievements),
                    'Explanation of variance from targets' => filled($this->variance_explanation),
                ]
            ),
            'means_of_verification' => $this->completionEntry(
                3,
                'Means of Verification and supporting documents',
                [
                    'Means of Verification notes' => filled($this->means_of_verification_notes),
                    'At least one supporting attachment' => $this->documents->isNotEmpty(),
                ]
            ),
            'overall_assessment' => $this->completionEntry(
                4,
                'Overall assessment, performance rating and conclusion',
                [
                    'Overall assessment' => filled($this->overall_assessment),
                    'Performance rating' => filled($this->performance_rating),
                    'Conclusion' => filled($this->conclusion),
                ]
            ),
            'challenges_mitigation' => $this->completionEntry(
                5,
                'Challenges and mitigation strategies',
                [
                    'Challenges faced' => filled($this->challenges_faced),
                    'Mitigation strategies' => filled($this->mitigation_strategies),
                ]
            ),
            'lessons_adaptive_management' => $this->completionEntry(
                6,
                'Lessons learned and adaptive management',
                [
                    'Lessons learned' => filled($this->lessons_learned),
                    'Adaptive management actions' => filled($this->adaptive_management_actions),
                ]
            ),
            'next_period_priorities' => $this->completionEntry(
                7,
                'Priorities or plans for the next reporting period',
                [
                    'Next reporting-period priorities or plans' => filled($this->next_period_priorities),
                ]
            ),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function submissionIssues(): array
    {
        return collect($this->sectionCompletion())
            ->filter(fn (array $section): bool => $section['status'] !== 'complete')
            ->map(fn (array $section): string => $section['number'].'. '.$section['title'].': '
                .implode(', ', $section['missing']))
            ->values()
            ->all();
    }

    public function isSubmissionReady(): bool
    {
        return $this->submissionIssues() === [];
    }

    public function periodLabel(): string
    {
        return ($this->reporting_period_label ?: $this->reporting_quarter).' '.$this->reporting_year;
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @param  array<string, bool>  $requirements
     * @return array{
     *     number:int,
     *     title:string,
     *     status:string,
     *     status_label:string,
     *     completed:int,
     *     total:int,
     *     missing:array<int, string>
     * }
     */
    private function completionEntry(int $number, string $title, array $requirements): array
    {
        $missing = collect($requirements)
            ->filter(fn (bool $complete): bool => ! $complete)
            ->keys()
            ->values()
            ->all();
        $completed = count($requirements) - count($missing);
        $status = $missing === []
            ? 'complete'
            : ($completed > 0 ? 'in_progress' : 'not_started');

        return [
            'number' => $number,
            'title' => $title,
            'status' => $status,
            'status_label' => match ($status) {
                'complete' => 'Complete',
                'in_progress' => 'In progress',
                default => 'Not started',
            },
            'completed' => $completed,
            'total' => count($requirements),
            'missing' => $missing,
        ];
    }
}
