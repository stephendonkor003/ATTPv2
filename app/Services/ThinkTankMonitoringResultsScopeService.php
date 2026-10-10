<?php

namespace App\Services;

use App\Models\ConsortiumThinkTank;
use App\Models\Indicator;
use App\Models\IndicatorTarget;
use App\Models\MeDataCollectionAssignment;
use App\Models\MeFramework;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ThinkTankMonitoringResultsScopeService
{
    /**
     * @return array{
     *     indicatorIds:array<int, string>,
     *     periods:Collection,
     *     reportingPeriodId:?string,
     *     projectYear:int,
     *     projectYearOptions:array<int, int>,
     *     reportingYear:?int
     * }
     */
    public function resolve(
        ConsortiumThinkTank $member,
        ?int $requestedProjectYear,
        ?int $requestedReportingYear,
        ?string $reportingPeriodId,
        bool $includeArchived = false,
    ): array {
        $frameworkId = MeFramework::query()->current()->value('id');
        $assignments = MeDataCollectionAssignment::query()
            ->where('think_tank_member_id', $member->id)
            ->whereHas('collection.form', fn ($query) => $query->where('status', 'published'))
            ->with([
                'collection:id,form_id,reporting_period_id',
                'collection.form:id,indicator_id,status',
                'collection.form.indicator:id,framework_id,project_component_id,is_active',
                'collection.form.indicators:id,framework_id,project_component_id,is_active',
                'collection.reportingPeriod:id,label,reporting_year,period_start,status,lifecycle_status',
            ])
            ->get();

        $assignedPeriods = $assignments
            ->pluck('collection.reportingPeriod')
            ->filter()
            ->unique('id')
            ->sortByDesc('period_start')
            ->values();
        $periods = $includeArchived
            ? $assignedPeriods
            : $assignedPeriods->filter(fn ($period): bool => $period->isActive())->values();
        $selectedPeriod = filled($reportingPeriodId)
            ? $assignedPeriods->first(fn ($period): bool => (string) $period->id === $reportingPeriodId)
            : null;
        if (filled($reportingPeriodId) && ($selectedPeriod === null || (! $includeArchived && ! $selectedPeriod->isActive()))) {
            abort(404);
        }
        if ($selectedPeriod === null && $requestedReportingYear === null) {
            $selectedPeriod = $periods->first();
        }
        $effectivePeriodId = $selectedPeriod ? (string) $selectedPeriod->id : null;

        [$filterReportingYear, $benchmarkReportingYear] = $this->reportingYearContext(
            $periods,
            $requestedReportingYear,
            $effectivePeriodId,
        );

        $scopedAssignments = $assignments->filter(function (MeDataCollectionAssignment $assignment) use (
            $periods,
            $effectivePeriodId,
            $requestedReportingYear,
        ): bool {
            $period = $assignment->collection?->reportingPeriod;
            if (! $period || ! $periods->contains('id', $period->id)) {
                return false;
            }
            if ($effectivePeriodId !== null) {
                return (string) $period->id === $effectivePeriodId;
            }

            return $requestedReportingYear !== null
                && (int) $period->reporting_year === $requestedReportingYear;
        });

        $indicatorIds = $frameworkId
            ? $scopedAssignments->flatMap(function (MeDataCollectionAssignment $assignment) use ($frameworkId): Collection {
                $form = $assignment->collection?->form;

                return collect([$form?->indicator])
                    ->merge($form?->indicators ?? collect())
                    ->filter(fn ($indicator): bool => (string) $indicator?->framework_id === (string) $frameworkId
                        && (bool) $indicator?->is_active)
                    ->pluck('id');
            })->filter()->map(fn ($id): string => (string) $id)->unique()->values()->all()
            : [];
        $indicators = $frameworkId && $indicatorIds !== []
            ? Indicator::query()
                ->where('framework_id', $frameworkId)
                ->where('is_active', true)
                ->whereKey($indicatorIds)
                ->with('projectComponent:id,start_year,end_year,total_years')
                ->get(['id', 'framework_id', 'project_component_id'])
            : collect();
        $indicatorIds = $indicators->pluck('id')->map(fn ($id): string => (string) $id)->values()->all();
        $targets = $indicatorIds === []
            ? collect()
            : IndicatorTarget::query()
                ->whereIn('indicator_id', $indicatorIds)
                ->where('approval_status', 'approved')
                ->where(function ($query) use ($member): void {
                    $query->where('target_scope', 'project')
                        ->orWhere(function ($tenantQuery) use ($member): void {
                            $tenantQuery->where('target_scope', 'think_tank')
                                ->where('think_tank_member_id', $member->id);
                        });
                })
                ->get([
                    'indicator_id', 'target_scope', 'think_tank_member_id', 'project_year',
                    'reporting_year', 'revision', 'effective_from', 'created_at',
                ]);
        $applicableTargets = $this->applicableTargets($targets, (string) $member->id);

        $durationYears = $indicators->flatMap(function (Indicator $indicator): array {
            $duration = $this->projectDuration($indicator->projectComponent);

            return $duration > 0 ? range(1, $duration) : [];
        });
        $options = $applicableTargets->flatten(1)
            ->pluck('project_year')
            ->merge($durationYears)
            ->map(fn ($year): int => (int) $year)
            ->filter(fn (int $year): bool => $year >= 1 && $year <= 4)
            ->unique()
            ->sort()
            ->values();
        if ($options->isEmpty()) {
            $options = collect(range(1, 4));
        }

        if ($requestedProjectYear !== null && ! $options->containsStrict($requestedProjectYear)) {
            throw ValidationException::withMessages([
                'project_year' => ['The selected project year is outside the available reporting cycle.'],
            ]);
        }

        $selected = $requestedProjectYear
            ?? $this->targetYearForReportingYear($applicableTargets, $benchmarkReportingYear)
            ?? $this->projectYearForReportingYear($indicators, $benchmarkReportingYear, $options)
            ?? $this->latestTargetYear($applicableTargets, $benchmarkReportingYear)
            ?? (int) $options->first();

        return [
            'indicatorIds' => $indicatorIds,
            'periods' => $periods,
            'reportingPeriodId' => $effectivePeriodId,
            'projectYear' => (int) $selected,
            'projectYearOptions' => $options->all(),
            'reportingYear' => $filterReportingYear,
        ];
    }

    private function applicableTargets(Collection $targets, string $memberId): Collection
    {
        return $targets->groupBy(fn (IndicatorTarget $target): string => (string) $target->indicator_id)
            ->map(function (Collection $indicatorTargets) use ($memberId): Collection {
                $tenantTargets = $indicatorTargets->filter(fn (IndicatorTarget $target): bool => $target->target_scope === 'think_tank'
                    && (string) $target->think_tank_member_id === $memberId
                );

                return $tenantTargets->isNotEmpty()
                    ? $tenantTargets->values()
                    : $indicatorTargets->where('target_scope', 'project')->values();
            });
    }

    /** @return array{0:?int,1:?int} */
    private function reportingYearContext(
        Collection $periods,
        ?int $requestedReportingYear,
        ?string $reportingPeriodId,
    ): array {
        $selectedPeriod = filled($reportingPeriodId)
            ? $periods->first(fn ($period): bool => (string) $period->id === $reportingPeriodId)
            : null;
        if (filled($reportingPeriodId) && $selectedPeriod === null) {
            abort(404);
        }
        $periodReportingYear = $selectedPeriod?->reporting_year !== null
            ? (int) $selectedPeriod->reporting_year
            : null;
        if ($selectedPeriod !== null
            && $requestedReportingYear !== null
            && $requestedReportingYear !== $periodReportingYear) {
            throw ValidationException::withMessages([
                'reporting_year' => ['The reporting year must match the selected reporting period.'],
            ]);
        }
        $filterReportingYear = $selectedPeriod !== null ? $periodReportingYear : $requestedReportingYear;
        $benchmarkReportingYear = $filterReportingYear
            ?? $selectedPeriod?->period_start?->year
            ?? $periods->pluck('reporting_year')->filter()->map(fn ($year): int => (int) $year)->max();

        return [$filterReportingYear, $benchmarkReportingYear];
    }

    private function targetYearForReportingYear(Collection $targetsByIndicator, ?int $reportingYear): ?int
    {
        if ($reportingYear === null) {
            return null;
        }

        return $this->dominantYear($targetsByIndicator->map(function (Collection $targets) use ($reportingYear): ?int {
            $target = $this->latestTarget($targets->filter(fn (IndicatorTarget $candidate): bool => (int) $candidate->reporting_year === $reportingYear
                && $this->validProjectYear($candidate->project_year)
            ));

            return $target ? (int) $target->project_year : null;
        }));
    }

    private function projectYearForReportingYear(
        Collection $indicators,
        ?int $reportingYear,
        Collection $options,
    ): ?int {
        if ($reportingYear === null) {
            return null;
        }

        return $this->dominantYear($indicators->map(function (Indicator $indicator) use ($reportingYear, $options): ?int {
            $project = $indicator->projectComponent;
            if (! $project || (int) $project->start_year < 1) {
                return null;
            }
            $duration = $this->projectDuration($project) ?: 4;
            $year = min($duration, max(1, $reportingYear - (int) $project->start_year + 1));

            return $options->containsStrict($year) ? $year : null;
        }));
    }

    private function latestTargetYear(Collection $targetsByIndicator, ?int $reportingYear): ?int
    {
        return $this->dominantYear($targetsByIndicator->map(function (Collection $targets) use ($reportingYear): ?int {
            $eligible = $targets->filter(fn (IndicatorTarget $target): bool => $this->validProjectYear($target->project_year)
                && ($reportingYear === null
                    || ($target->reporting_year !== null && (int) $target->reporting_year <= $reportingYear))
            );
            $target = $this->latestTarget($eligible);

            return $target ? (int) $target->project_year : null;
        }));
    }

    private function dominantYear(Collection $years): ?int
    {
        return $years->filter(fn ($year): bool => $this->validProjectYear($year))
            ->map(fn ($year): int => (int) $year)
            ->countBy()
            ->map(fn (int $count, int|string $year): array => ['year' => (int) $year, 'count' => $count])
            ->sort(fn (array $left, array $right): int => ($right['count'] <=> $left['count']) ?: ($right['year'] <=> $left['year'])
            )
            ->first()['year'] ?? null;
    }

    private function latestTarget(Collection $targets): ?IndicatorTarget
    {
        return $targets
            ->sortByDesc(fn (IndicatorTarget $target): string => sprintf(
                '%010d|%010d|%s|%s',
                (int) ($target->reporting_year ?: 0),
                (int) $target->revision,
                $target->effective_from?->format('Ymd') ?? '00000000',
                $target->created_at?->format('YmdHis.u') ?? ''
            ))
            ->first();
    }

    private function projectDuration(mixed $project): int
    {
        if (! $project) {
            return 0;
        }
        $duration = (int) ($project->total_years ?: 0);
        if ($duration < 1 && $project->start_year && $project->end_year) {
            $duration = ((int) $project->end_year - (int) $project->start_year) + 1;
        }

        return min(4, max(0, $duration));
    }

    private function validProjectYear(mixed $year): bool
    {
        return is_numeric($year) && (int) $year >= 1 && (int) $year <= 4;
    }
}
