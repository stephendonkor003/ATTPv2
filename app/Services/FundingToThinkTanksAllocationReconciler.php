<?php

namespace App\Services;

use App\Models\ActivityAllocation;
use App\Models\ProjectAllocation;
use App\Models\SubActivity;
use App\Models\SubActivityAllocation;
use App\Models\SystemAuditLog;
use DomainException;
use Illuminate\Support\Facades\DB;

class FundingToThinkTanksAllocationReconciler
{
    public const TARGET_SUB_ACTIVITY_ID = '019ea974-4bc0-73d9-a6b9-4693201bbc24';

    private const PROJECT_ID = '019ea974-4b90-73da-8f40-95438ba9d559';

    private const ACTIVITY_ID = '019ea974-4b9f-7348-b643-f9bbd6ffcec4';

    private const SIBLING_SUB_ACTIVITY_ID = '019ea974-4be2-7029-b4ea-4ea6cfd76368';

    private const YEARS = [2024, 2025, 2026, 2027, 2028];

    private const TARGET_TOTAL = 24_500_000;

    private const SIBLING_TOTAL = 24_800;

    private const LEGACY_CHILDREN_SCHEDULE = [
        2024 => 0,
        2025 => 24_524_800,
        2026 => 580_000,
        2027 => 570_000,
        2028 => 130_000,
    ];

    private const LEGACY_TARGET_SCHEDULE = [
        2024 => 0,
        2025 => 24_500_000,
        2026 => 0,
        2027 => 0,
        2028 => 0,
    ];

    private const LEGACY_SIBLING_SCHEDULE = [
        2024 => 0,
        2025 => 24_800,
        2026 => 0,
        2027 => 0,
        2028 => 0,
    ];

    /**
     * Production fingerprint captured from the 2026-08-02 server backup. The
     * target was accidentally saved at every displayed annual maximum, which
     * increased its envelope from USD 24.5M to USD 25.507M.
     */
    private const MAX_FILLED_TARGET_SCHEDULE = [
        2024 => 0,
        2025 => 0,
        2026 => 10_258_500,
        2027 => 10_248_500,
        2028 => 5_000_000,
    ];

    private const MAX_FILLED_CHILDREN_SCHEDULE = [
        2024 => 0,
        2025 => 24_800,
        2026 => 10_258_500,
        2027 => 10_248_500,
        2028 => 5_000_000,
    ];

    private const EMPTY_SCHEDULE = [
        2024 => 0,
        2025 => 0,
        2026 => 0,
        2027 => 0,
        2028 => 0,
    ];

    private const OTHER_CHILDREN_SCHEDULE = [
        2024 => 0,
        2025 => 0,
        2026 => 580_000,
        2027 => 570_000,
        2028 => 130_000,
    ];

    private const RECONCILED_TARGET_SCHEDULE = [
        2024 => 0,
        2025 => 24_800,
        2026 => 9_678_500,
        2027 => 9_678_500,
        2028 => 5_118_200,
    ];

    private const RECONCILED_SIBLING_SCHEDULE = [
        2024 => 0,
        2025 => 0,
        2026 => 0,
        2027 => 0,
        2028 => 24_800,
    ];

    /**
     * A manual server edit placed almost USD 24,800 in 2025 without moving
     * that amount out of 2028. Recognize the exact audited drift so the
     * protected repair can restore the USD 24.5M envelope and exact cents.
     */
    private const ROUNDING_DRIFT_TARGET_SCHEDULE = [
        2024 => 0,
        2025 => 24_799.99,
        2026 => 9_678_500,
        2027 => 9_678_500,
        2028 => 5_143_000,
    ];

    private const ROUNDING_DRIFT_CHILDREN_SCHEDULE = [
        2024 => 0,
        2025 => 24_799.99,
        2026 => 9_678_500,
        2027 => 9_678_500,
        2028 => 5_167_800,
    ];

    public function preview(SubActivity $subActivity): array
    {
        if ((string) $subActivity->getKey() !== self::TARGET_SUB_ACTIVITY_ID) {
            return ['status' => 'unavailable', 'can_reconcile' => false];
        }

        $snapshot = $this->snapshot();
        $status = $this->classifySnapshot($snapshot);
        $plan = $status === 'ready' ? $this->reconciliationPlan($snapshot) : null;
        $plannedParent = $plan['parent_by_year'] ?? $snapshot['parent_by_year'];
        $plannedProjectRemaining = $plan['project_remaining_by_year'] ?? $snapshot['project_remaining_by_year'];

        return [
            'status' => $status,
            'can_reconcile' => $status === 'ready',
            'snapshot' => $snapshot,
            'planned_target_schedule' => self::RECONCILED_TARGET_SCHEDULE,
            'planned_parent_schedule' => $plannedParent,
            'planned_parent_2028' => $plannedParent[2028] ?? 0,
            'planned_project_remaining_by_year' => $plannedProjectRemaining,
            'planned_project_2028_remaining' => $plannedProjectRemaining[2028] ?? 0,
            'message' => match ($status) {
                'ready' => 'The audited allocation fingerprint is present and the current server envelopes support a safe automatic reconciliation.',
                'complete' => 'The audited allocation reconciliation has already been completed.',
                default => 'The current hierarchy cannot be reconciled automatically without exceeding an annual project envelope or changing unaudited child allocations.',
            },
        ];
    }

    public function reconcile(SubActivity $subActivity, ?string $actorId = null): array
    {
        if ((string) $subActivity->getKey() !== self::TARGET_SUB_ACTIVITY_ID) {
            throw new DomainException('This reconciliation is restricted to the audited Funding to Think Tanks sub-activity.');
        }

        return DB::transaction(function () use ($actorId): array {
            $projectActivityIds = DB::table('myb_activities')
                ->where('project_id', self::PROJECT_ID)
                ->pluck('id');
            $activitySubActivityIds = SubActivity::query()
                ->where('activity_id', self::ACTIVITY_ID)
                ->pluck('id');

            ProjectAllocation::query()
                ->where('project_id', self::PROJECT_ID)
                ->whereIn('year', self::YEARS)
                ->lockForUpdate()
                ->get();
            ActivityAllocation::query()
                ->whereIn('activity_id', $projectActivityIds)
                ->whereIn('year', self::YEARS)
                ->lockForUpdate()
                ->get();
            SubActivityAllocation::query()
                ->whereIn('sub_activity_id', $activitySubActivityIds)
                ->lockForUpdate()
                ->get();

            $before = $this->snapshot();
            $status = $this->classifySnapshot($before);

            if ($status === 'complete') {
                return [
                    'changed' => false,
                    'status' => 'complete',
                    'snapshot' => $before,
                    'audit_log_id' => null,
                ];
            }

            $plan = $status === 'ready' ? $this->reconciliationPlan($before) : null;
            if ($plan === null) {
                throw new DomainException('Reconciliation stopped because the current server hierarchy cannot be corrected within its annual project envelopes.');
            }

            foreach ($plan['parent_by_year'] as $year => $amount) {
                ActivityAllocation::updateOrCreate(
                    ['activity_id' => self::ACTIVITY_ID, 'year' => $year],
                    ['amount' => $amount]
                );
            }

            foreach ($plan['target_by_year'] as $year => $amount) {
                SubActivityAllocation::updateOrCreate(
                    ['sub_activity_id' => self::TARGET_SUB_ACTIVITY_ID, 'year' => $year],
                    ['amount' => $amount]
                );
            }

            foreach ($plan['sibling_by_year'] as $year => $amount) {
                SubActivityAllocation::updateOrCreate(
                    ['sub_activity_id' => self::SIBLING_SUB_ACTIVITY_ID, 'year' => $year],
                    ['amount' => $amount]
                );
            }

            $after = $this->snapshot();
            if (! $this->isReconciledSnapshot($after)
                || ! $this->scheduleEquals($after['parent_by_year'], $plan['parent_by_year'])) {
                throw new DomainException('Reconciliation verification failed, so every database change was rolled back.');
            }

            $auditLog = SystemAuditLog::create([
                'user_id' => $actorId,
                'module' => 'budget',
                'action' => 'financial_hierarchy_reconciled',
                'action_message' => 'Rephased Funding to Think Tanks and safely reconciled its parent activity envelope.',
                'description' => 'One-click correction of the audited legacy 2025 allocation exception while preserving compatible server-side parent capacity.',
                'method' => 'POST',
                'route_name' => 'budget.subactivities.reconcile-funding-allocation',
                'status_code' => 200,
                'payload' => [
                    'project_id' => self::PROJECT_ID,
                    'activity_id' => self::ACTIVITY_ID,
                    'target_sub_activity_id' => self::TARGET_SUB_ACTIVITY_ID,
                    'sibling_sub_activity_id' => self::SIBLING_SUB_ACTIVITY_ID,
                    'plan' => $plan,
                    'before' => $before,
                    'after' => $after,
                ],
            ]);

            return [
                'changed' => true,
                'status' => 'complete',
                'snapshot' => $after,
                'audit_log_id' => $auditLog->id,
            ];
        });
    }

    public function classifySnapshot(array $snapshot): string
    {
        if ($this->isReconciledSnapshot($snapshot)) {
            return 'complete';
        }

        if ($this->reconciliationPlan($snapshot) !== null) {
            return 'ready';
        }

        return 'blocked';
    }

    private function reconciliationPlan(array $snapshot): ?array
    {
        if (! $this->isAuditedRepairableSnapshot($snapshot)) {
            return null;
        }

        $currentParent = $this->normalizedSchedule($snapshot['parent_by_year'] ?? []);
        $projectByYear = $this->normalizedSchedule($snapshot['project_by_year'] ?? []);
        $projectActivityByYear = $this->normalizedSchedule($snapshot['project_activity_by_year'] ?? []);

        if (! $this->amountEquals($snapshot['parent_total'] ?? null, array_sum($currentParent))) {
            return null;
        }

        $otherChildren = $this->normalizedSchedule($snapshot['other_children_by_year'] ?? []);
        $plannedChildren = [];
        foreach (self::YEARS as $year) {
            $plannedChildren[$year] = round(
                self::RECONCILED_TARGET_SCHEDULE[$year]
                + self::RECONCILED_SIBLING_SCHEDULE[$year]
                + $otherChildren[$year],
                2
            );
        }

        $plannedParent = $currentParent;
        foreach ($plannedChildren as $year => $minimumAmount) {
            $plannedParent[$year] = max($plannedParent[$year], $minimumAmount);
        }

        $preservedParentTotal = max(array_sum($currentParent), array_sum($plannedChildren));
        $reductionRequired = round(array_sum($plannedParent) - $preservedParentTotal, 2);
        $surpluses = [];

        foreach (self::YEARS as $year) {
            $surpluses[$year] = max(
                round($plannedParent[$year] - $plannedChildren[$year], 2),
                0
            );
        }

        arsort($surpluses, SORT_NUMERIC);
        foreach ($surpluses as $year => $surplus) {
            if ($reductionRequired <= 0.004) {
                break;
            }

            $reduction = min($surplus, $reductionRequired);
            $plannedParent[$year] = round($plannedParent[$year] - $reduction, 2);
            $reductionRequired = round($reductionRequired - $reduction, 2);
        }

        if ($reductionRequired > 0.004) {
            return null;
        }

        $plannedProjectActivity = [];
        $plannedProjectRemaining = [];
        foreach (self::YEARS as $year) {
            $plannedProjectActivity[$year] = round(
                $projectActivityByYear[$year] - $currentParent[$year] + $plannedParent[$year],
                2
            );

            if ($plannedProjectActivity[$year] < -0.004
                || $plannedProjectActivity[$year] > $projectByYear[$year] + 0.004) {
                return null;
            }

            $plannedProjectRemaining[$year] = round(
                $projectByYear[$year] - $plannedProjectActivity[$year],
                2
            );
        }

        return [
            'parent_by_year' => $plannedParent,
            'target_by_year' => self::RECONCILED_TARGET_SCHEDULE,
            'sibling_by_year' => self::RECONCILED_SIBLING_SCHEDULE,
            'children_by_year' => $plannedChildren,
            'project_activity_by_year' => $plannedProjectActivity,
            'project_remaining_by_year' => $plannedProjectRemaining,
            'preserved_parent_total' => $preservedParentTotal,
        ];
    }

    private function isAuditedRepairableSnapshot(array $snapshot): bool
    {
        $legacy = $this->scheduleEquals($snapshot['children_by_year'] ?? [], self::LEGACY_CHILDREN_SCHEDULE)
            && $this->scheduleEquals($snapshot['target_by_year'] ?? [], self::LEGACY_TARGET_SCHEDULE)
            && $this->scheduleEquals($snapshot['sibling_by_year'] ?? [], self::LEGACY_SIBLING_SCHEDULE)
            && $this->scheduleEquals($snapshot['other_children_by_year'] ?? [], self::OTHER_CHILDREN_SCHEDULE)
            && $this->amountEquals($snapshot['children_total'] ?? null, array_sum(self::LEGACY_CHILDREN_SCHEDULE))
            && $this->amountEquals($snapshot['target_total'] ?? null, self::TARGET_TOTAL)
            && $this->amountEquals($snapshot['sibling_total'] ?? null, self::SIBLING_TOTAL);

        $maxFilled = $this->scheduleEquals($snapshot['children_by_year'] ?? [], self::MAX_FILLED_CHILDREN_SCHEDULE)
            && $this->scheduleEquals($snapshot['target_by_year'] ?? [], self::MAX_FILLED_TARGET_SCHEDULE)
            && $this->scheduleEquals($snapshot['sibling_by_year'] ?? [], self::LEGACY_SIBLING_SCHEDULE)
            && $this->scheduleEquals($snapshot['other_children_by_year'] ?? [], self::EMPTY_SCHEDULE)
            && $this->scheduleEquals($snapshot['parent_by_year'] ?? [], self::MAX_FILLED_CHILDREN_SCHEDULE)
            && $this->amountEquals($snapshot['children_total'] ?? null, array_sum(self::MAX_FILLED_CHILDREN_SCHEDULE))
            && $this->amountEquals($snapshot['target_total'] ?? null, array_sum(self::MAX_FILLED_TARGET_SCHEDULE))
            && $this->amountEquals($snapshot['sibling_total'] ?? null, self::SIBLING_TOTAL);

        $roundingDrift = $this->scheduleEquals($snapshot['children_by_year'] ?? [], self::ROUNDING_DRIFT_CHILDREN_SCHEDULE)
            && $this->scheduleEquals($snapshot['target_by_year'] ?? [], self::ROUNDING_DRIFT_TARGET_SCHEDULE)
            && $this->scheduleEquals($snapshot['sibling_by_year'] ?? [], self::RECONCILED_SIBLING_SCHEDULE)
            && $this->scheduleEquals($snapshot['other_children_by_year'] ?? [], self::EMPTY_SCHEDULE)
            && $this->amountEquals($snapshot['children_total'] ?? null, array_sum(self::ROUNDING_DRIFT_CHILDREN_SCHEDULE))
            && $this->amountEquals($snapshot['target_total'] ?? null, array_sum(self::ROUNDING_DRIFT_TARGET_SCHEDULE))
            && $this->amountEquals($snapshot['sibling_total'] ?? null, self::SIBLING_TOTAL);

        return $legacy || $maxFilled || $roundingDrift;
    }

    private function isReconciledSnapshot(array $snapshot): bool
    {
        $otherChildren = $this->normalizedSchedule($snapshot['other_children_by_year'] ?? []);
        $recognizedOtherChildren = $this->scheduleEquals($otherChildren, self::OTHER_CHILDREN_SCHEDULE)
            || $this->scheduleEquals($otherChildren, self::EMPTY_SCHEDULE);
        $expectedChildren = [];
        foreach (self::YEARS as $year) {
            $expectedChildren[$year] = round(
                self::RECONCILED_TARGET_SCHEDULE[$year]
                + self::RECONCILED_SIBLING_SCHEDULE[$year]
                + $otherChildren[$year],
                2
            );
        }

        if (! $recognizedOtherChildren
            || ! $this->scheduleEquals($snapshot['children_by_year'] ?? [], $expectedChildren)
            || ! $this->scheduleEquals($snapshot['target_by_year'] ?? [], self::RECONCILED_TARGET_SCHEDULE)
            || ! $this->scheduleEquals($snapshot['sibling_by_year'] ?? [], self::RECONCILED_SIBLING_SCHEDULE)
            || ! $this->amountEquals($snapshot['children_total'] ?? null, array_sum($expectedChildren))
            || ! $this->amountEquals($snapshot['target_total'] ?? null, self::TARGET_TOTAL)
            || ! $this->amountEquals($snapshot['sibling_total'] ?? null, self::SIBLING_TOTAL)) {
            return false;
        }

        $parentByYear = $this->normalizedSchedule($snapshot['parent_by_year'] ?? []);
        $projectByYear = $this->normalizedSchedule($snapshot['project_by_year'] ?? []);
        $projectActivityByYear = $this->normalizedSchedule($snapshot['project_activity_by_year'] ?? []);

        if (! $this->amountEquals($snapshot['parent_total'] ?? null, array_sum($parentByYear))) {
            return false;
        }

        foreach (self::YEARS as $year) {
            if ($expectedChildren[$year] > $parentByYear[$year] + 0.004
                || $projectActivityByYear[$year] > $projectByYear[$year] + 0.004) {
                return false;
            }
        }

        return true;
    }

    private function snapshot(): array
    {
        $projectByYear = ProjectAllocation::query()
            ->where('project_id', self::PROJECT_ID)
            ->whereIn('year', self::YEARS)
            ->groupBy('year')
            ->selectRaw('year, SUM(amount) AS amount')
            ->pluck('amount', 'year')
            ->all();
        $projectActivityByYear = ActivityAllocation::query()
            ->join('myb_activities', 'myb_activities.id', '=', 'myb_activity_allocations.activity_id')
            ->where('myb_activities.project_id', self::PROJECT_ID)
            ->whereIn('myb_activity_allocations.year', self::YEARS)
            ->groupBy('myb_activity_allocations.year')
            ->selectRaw('myb_activity_allocations.year, SUM(myb_activity_allocations.amount) AS amount')
            ->pluck('amount', 'year')
            ->all();
        $parentByYear = ActivityAllocation::query()
            ->where('activity_id', self::ACTIVITY_ID)
            ->pluck('amount', 'year')
            ->all();
        $childrenByYear = SubActivityAllocation::query()
            ->join('myb_sub_activities', 'myb_sub_activities.id', '=', 'myb_sub_activity_allocations.sub_activity_id')
            ->where('myb_sub_activities.activity_id', self::ACTIVITY_ID)
            ->groupBy('myb_sub_activity_allocations.year')
            ->selectRaw('myb_sub_activity_allocations.year, SUM(myb_sub_activity_allocations.amount) AS amount')
            ->pluck('amount', 'year')
            ->all();
        $targetByYear = SubActivityAllocation::query()
            ->where('sub_activity_id', self::TARGET_SUB_ACTIVITY_ID)
            ->pluck('amount', 'year')
            ->all();
        $siblingByYear = SubActivityAllocation::query()
            ->where('sub_activity_id', self::SIBLING_SUB_ACTIVITY_ID)
            ->pluck('amount', 'year')
            ->all();

        $normalizedProject = $this->normalizedSchedule($projectByYear);
        $normalizedProjectActivities = $this->normalizedSchedule($projectActivityByYear);
        $normalizedParent = $this->normalizedSchedule($parentByYear);
        $normalizedChildren = $this->normalizedSchedule($childrenByYear);
        $normalizedTarget = $this->normalizedSchedule($targetByYear);
        $normalizedSibling = $this->normalizedSchedule($siblingByYear);
        $otherChildren = [];
        $projectRemaining = [];

        foreach (self::YEARS as $year) {
            $otherChildren[$year] = round(
                $normalizedChildren[$year] - $normalizedTarget[$year] - $normalizedSibling[$year],
                2
            );
            $projectRemaining[$year] = round(
                $normalizedProject[$year] - $normalizedProjectActivities[$year],
                2
            );
        }

        return [
            'project_by_year' => $normalizedProject,
            'project_activity_by_year' => $normalizedProjectActivities,
            'project_remaining_by_year' => $projectRemaining,
            'project_2028' => $normalizedProject[2028],
            'project_activity_2028' => $normalizedProjectActivities[2028],
            'project_2028_remaining' => $projectRemaining[2028],
            'parent_by_year' => $normalizedParent,
            'children_by_year' => $normalizedChildren,
            'target_by_year' => $normalizedTarget,
            'sibling_by_year' => $normalizedSibling,
            'other_children_by_year' => $otherChildren,
            'parent_total' => round(array_sum($parentByYear), 2),
            'children_total' => round(array_sum($childrenByYear), 2),
            'target_total' => round(array_sum($targetByYear), 2),
            'sibling_total' => round(array_sum($siblingByYear), 2),
        ];
    }

    private function normalizedSchedule(array $schedule): array
    {
        $normalized = [];
        foreach (self::YEARS as $year) {
            $normalized[$year] = round((float) ($schedule[$year] ?? 0), 2);
        }

        return $normalized;
    }

    private function scheduleEquals(array $actual, array $expected): bool
    {
        $actual = $this->normalizedSchedule($actual);
        foreach ($expected as $year => $amount) {
            if (! $this->amountEquals($actual[$year] ?? null, $amount)) {
                return false;
            }
        }

        return true;
    }

    private function amountEquals(mixed $actual, float|int $expected): bool
    {
        return is_numeric($actual) && abs((float) $actual - (float) $expected) <= 0.004;
    }
}
