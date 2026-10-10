<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Program;
use App\Models\Project;
use DomainException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FinancialHierarchyDeletionGuard
{
    /**
     * @return array<string, int>
     */
    public function activityDependencies(Activity $activity): array
    {
        return $this->dependencyCounts(
            activityIds: [(string) $activity->getKey()],
            subActivityIds: $this->subActivityIds([(string) $activity->getKey()]),
        );
    }

    /**
     * @return array<string, int>
     */
    public function projectDependencies(Project $project): array
    {
        $projectIds = [(string) $project->getKey()];
        $activityIds = $this->activityIds($projectIds);

        return $this->dependencyCounts(
            projectIds: $projectIds,
            activityIds: $activityIds,
            subActivityIds: $this->subActivityIds($activityIds),
        );
    }

    /**
     * @return array<string, int>
     */
    public function programDependencies(Program $program): array
    {
        $programIds = [(string) $program->getKey()];
        $projectIds = DB::table('myb_projects')
            ->whereIn('program_id', $programIds)
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
        $activityIds = $this->activityIds($projectIds);

        return $this->dependencyCounts(
            programIds: $programIds,
            projectIds: $projectIds,
            activityIds: $activityIds,
            subActivityIds: $this->subActivityIds($activityIds),
            programFundingIds: DB::table('myb_program_fundings')
                ->whereIn('program_id', $programIds)
                ->pluck('id')
                ->map(static fn ($id): string => (string) $id)
                ->all(),
        );
    }

    public function assertActivityCanBeDeleted(Activity $activity): void
    {
        $this->assertNoDependencies('activity', $this->activityDependencies($activity));
    }

    public function assertProjectCanBeDeleted(Project $project): void
    {
        $this->assertNoDependencies('project', $this->projectDependencies($project));
    }

    public function assertProgramCanBeDeleted(Program $program): void
    {
        $this->assertNoDependencies('program', $this->programDependencies($program));
    }

    /**
     * @param  array<string, int>  $dependencies
     */
    private function assertNoDependencies(string $hierarchyType, array $dependencies): void
    {
        $linked = collect($dependencies)->filter(static fn (int $count): bool => $count > 0);

        if ($linked->isEmpty()) {
            return;
        }

        $summary = $linked
            ->map(static fn (int $count, string $label): string => $count.' '.$label)
            ->implode(', ');

        throw new DomainException(
            "This {$hierarchyType} cannot be deleted because it or its descendants are linked to {$summary}. "
            .'Reassign the linked records first so financial and audit history remains intact.'
        );
    }

    /**
     * @param  array<int, string>  $programIds
     * @param  array<int, string>  $projectIds
     * @param  array<int, string>  $activityIds
     * @param  array<int, string>  $subActivityIds
     * @param  array<int, string>  $programFundingIds
     * @return array<string, int>
     */
    private function dependencyCounts(
        array $programIds = [],
        array $projectIds = [],
        array $activityIds = [],
        array $subActivityIds = [],
        array $programFundingIds = [],
    ): array {
        $allocationIds = array_values(array_unique([
            ...$programIds,
            ...$projectIds,
            ...$activityIds,
            ...$subActivityIds,
        ]));

        return [
            'budget commitment(s)' => $this->polymorphicFinancialCount(
                'myb_budget_commitments',
                $allocationIds,
                $programFundingIds,
            ),
            'purchase request(s)' => $this->polymorphicFinancialCount(
                'myb_purchase_requests',
                $allocationIds,
                $programFundingIds,
            ),
            'purchase order(s)' => $this->whereInCount(
                'procurement_purchase_orders',
                'sub_activity_id',
                $subActivityIds,
            ),
            'invoice(s)' => $this->whereInCount(
                'procurement_invoices',
                'sub_activity_id',
                $subActivityIds,
            ),
            'disbursement(s)' => $this->whereInCount(
                'procurement_disbursements',
                'sub_activity_id',
                $subActivityIds,
            ),
            'program budget allocation(s)' => $this->hierarchyColumnCount(
                'program_budget_allocations',
                $projectIds,
                $activityIds,
                $subActivityIds,
            ),
            'procurement plan(s)' => $this->activityAndSubActivityCount(
                'myb_procurement_plans',
                $activityIds,
                $subActivityIds,
            ),
            'activity report(s)' => $this->activityAndSubActivityCount(
                'attp_activity_reports',
                $activityIds,
                $subActivityIds,
            ),
            'vendor purchase request(s)' => $this->whereInCount(
                'vendor_purchase_requests',
                'sub_activity_id',
                $subActivityIds,
            ),
            'vendor assignment(s)' => $this->hierarchyColumnCount(
                'vendor_sub_activity_assignments',
                $projectIds,
                $activityIds,
                $subActivityIds,
                $programIds,
            ),
            'program funding record(s)' => count($programIds) === 0
                ? 0
                : DB::table('myb_program_fundings')->whereIn('program_id', $programIds)->count(),
        ];
    }

    /**
     * Commitments and purchase requests use a polymorphic allocation UUID.
     * Matching the UUID as well as program funding also catches malformed or
     * legacy allocation_level values instead of allowing them to be orphaned.
     *
     * @param  array<int, string>  $allocationIds
     * @param  array<int, string>  $programFundingIds
     */
    private function polymorphicFinancialCount(
        string $table,
        array $allocationIds,
        array $programFundingIds,
    ): int {
        if ($allocationIds === [] && $programFundingIds === []) {
            return 0;
        }

        return DB::table($table)
            ->where(function (Builder $query) use ($allocationIds, $programFundingIds): void {
                if ($allocationIds !== []) {
                    $query->whereIn('allocation_id', $allocationIds);
                }

                if ($programFundingIds !== []) {
                    $method = $allocationIds === [] ? 'whereIn' : 'orWhereIn';
                    $query->{$method}('program_funding_id', $programFundingIds);
                }
            })
            ->count();
    }

    /**
     * @param  array<int, string>  $projectIds
     * @param  array<int, string>  $activityIds
     * @param  array<int, string>  $subActivityIds
     * @param  array<int, string>  $programIds
     */
    private function hierarchyColumnCount(
        string $table,
        array $projectIds,
        array $activityIds,
        array $subActivityIds,
        array $programIds = [],
    ): int {
        if ($programIds === [] && $projectIds === [] && $activityIds === [] && $subActivityIds === []) {
            return 0;
        }

        return DB::table($table)
            ->where(function (Builder $query) use (
                $programIds,
                $projectIds,
                $activityIds,
                $subActivityIds,
            ): void {
                $this->appendWhereIn($query, 'program_id', $programIds);
                $this->appendWhereIn($query, 'project_id', $projectIds);
                $this->appendWhereIn($query, 'activity_id', $activityIds);
                $this->appendWhereIn($query, 'sub_activity_id', $subActivityIds);
            })
            ->count();
    }

    /**
     * @param  array<int, string>  $activityIds
     * @param  array<int, string>  $subActivityIds
     */
    private function activityAndSubActivityCount(
        string $table,
        array $activityIds,
        array $subActivityIds,
    ): int {
        if ($activityIds === [] && $subActivityIds === []) {
            return 0;
        }

        return DB::table($table)
            ->where(function (Builder $query) use ($activityIds, $subActivityIds): void {
                $this->appendWhereIn($query, 'activity_id', $activityIds);
                $this->appendWhereIn($query, 'sub_activity_id', $subActivityIds);
            })
            ->count();
    }

    /**
     * @param  array<int, string>  $ids
     */
    private function whereInCount(string $table, string $column, array $ids): int
    {
        return $ids === [] ? 0 : DB::table($table)->whereIn($column, $ids)->count();
    }

    /**
     * Build a single OR group without depending on a caller to identify which
     * hierarchy level is the first non-empty one.
     *
     * @param  array<int, string>  $ids
     */
    private function appendWhereIn(Builder $query, string $column, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $query->orWhereIn($column, $ids);
    }

    /**
     * @param  array<int, string>  $projectIds
     * @return array<int, string>
     */
    private function activityIds(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        return DB::table('myb_activities')
            ->whereIn('project_id', $projectIds)
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
    }

    /**
     * @param  array<int, string>  $activityIds
     * @return array<int, string>
     */
    private function subActivityIds(array $activityIds): array
    {
        if ($activityIds === []) {
            return [];
        }

        return DB::table('myb_sub_activities')
            ->whereIn('activity_id', $activityIds)
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
    }
}
