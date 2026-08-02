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

    private const LEGACY_PARENT_SCHEDULE = [
        2024 => 0,
        2025 => 0,
        2026 => 10_258_500,
        2027 => 10_248_500,
        2028 => 5_000_000,
    ];

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

    private const RECONCILED_PARENT_SCHEDULE = [
        2024 => 0,
        2025 => 0,
        2026 => 10_258_500,
        2027 => 10_248_500,
        2028 => 5_297_800,
    ];

    private const RECONCILED_TARGET_SCHEDULE = [
        2024 => 0,
        2025 => 0,
        2026 => 9_678_500,
        2027 => 9_678_500,
        2028 => 5_143_000,
    ];

    private const LEGACY_SIBLING_SCHEDULE = [
        2024 => 0,
        2025 => 24_800,
        2026 => 0,
        2027 => 0,
        2028 => 0,
    ];

    private const RECONCILED_SIBLING_SCHEDULE = [
        2024 => 0,
        2025 => 0,
        2026 => 0,
        2027 => 0,
        2028 => 24_800,
    ];

    public function preview(SubActivity $subActivity): array
    {
        if ((string) $subActivity->getKey() !== self::TARGET_SUB_ACTIVITY_ID) {
            return ['status' => 'unavailable', 'can_reconcile' => false];
        }

        $snapshot = $this->snapshot();
        $status = $this->classifySnapshot($snapshot);

        return [
            'status' => $status,
            'can_reconcile' => $status === 'ready',
            'snapshot' => $snapshot,
            'planned_target_schedule' => self::RECONCILED_TARGET_SCHEDULE,
            'planned_parent_2028' => self::RECONCILED_PARENT_SCHEDULE[2028],
            'planned_project_2028_remaining' => 5_145_200,
            'message' => match ($status) {
                'ready' => 'The exact audited legacy allocation is present and can be reconciled safely.',
                'complete' => 'The audited allocation reconciliation has already been completed.',
                default => 'The server figures differ from the audited baseline. No automatic database changes are allowed.',
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
                ->where('year', 2028)
                ->lockForUpdate()
                ->firstOrFail();
            ActivityAllocation::query()
                ->whereIn('activity_id', $projectActivityIds)
                ->where('year', 2028)
                ->lockForUpdate()
                ->get();
            ActivityAllocation::query()
                ->where('activity_id', self::ACTIVITY_ID)
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

            if ($status !== 'ready') {
                throw new DomainException('Reconciliation stopped because the current server figures do not match the audited legacy baseline.');
            }

            ActivityAllocation::query()
                ->where('activity_id', self::ACTIVITY_ID)
                ->where('year', 2028)
                ->firstOrFail()
                ->update(['amount' => self::RECONCILED_PARENT_SCHEDULE[2028]]);

            foreach (self::RECONCILED_TARGET_SCHEDULE as $year => $amount) {
                SubActivityAllocation::updateOrCreate(
                    ['sub_activity_id' => self::TARGET_SUB_ACTIVITY_ID, 'year' => $year],
                    ['amount' => $amount]
                );
            }

            foreach (self::RECONCILED_SIBLING_SCHEDULE as $year => $amount) {
                SubActivityAllocation::updateOrCreate(
                    ['sub_activity_id' => self::SIBLING_SUB_ACTIVITY_ID, 'year' => $year],
                    ['amount' => $amount]
                );
            }

            $after = $this->snapshot();
            if ($this->classifySnapshot($after) !== 'complete') {
                throw new DomainException('Reconciliation verification failed, so every database change was rolled back.');
            }

            $auditLog = SystemAuditLog::create([
                'user_id' => $actorId,
                'module' => 'budget',
                'action' => 'financial_hierarchy_reconciled',
                'action_message' => 'Rephased Funding to Think Tanks and reconciled its parent activity envelope.',
                'description' => 'One-click correction of the audited legacy 2025 sub-activity allocation exception.',
                'method' => 'POST',
                'route_name' => 'budget.subactivities.reconcile-funding-allocation',
                'status_code' => 200,
                'payload' => [
                    'project_id' => self::PROJECT_ID,
                    'activity_id' => self::ACTIVITY_ID,
                    'target_sub_activity_id' => self::TARGET_SUB_ACTIVITY_ID,
                    'sibling_sub_activity_id' => self::SIBLING_SUB_ACTIVITY_ID,
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

        if ($this->isLegacySnapshot($snapshot)) {
            return 'ready';
        }

        return 'blocked';
    }

    private function isLegacySnapshot(array $snapshot): bool
    {
        return $this->amountEquals($snapshot['project_2028'] ?? null, 11_413_000)
            && $this->amountEquals($snapshot['project_activity_2028'] ?? null, 5_970_000)
            && $this->scheduleEquals($snapshot['parent_by_year'] ?? [], self::LEGACY_PARENT_SCHEDULE)
            && $this->scheduleEquals($snapshot['children_by_year'] ?? [], self::LEGACY_CHILDREN_SCHEDULE)
            && $this->scheduleEquals($snapshot['target_by_year'] ?? [], self::LEGACY_TARGET_SCHEDULE)
            && $this->scheduleEquals($snapshot['sibling_by_year'] ?? [], self::LEGACY_SIBLING_SCHEDULE);
    }

    private function isReconciledSnapshot(array $snapshot): bool
    {
        return $this->amountEquals($snapshot['project_2028'] ?? null, 11_413_000)
            && $this->amountEquals($snapshot['project_activity_2028'] ?? null, 6_267_800)
            && $this->amountEquals($snapshot['project_2028_remaining'] ?? null, 5_145_200)
            && $this->scheduleEquals($snapshot['parent_by_year'] ?? [], self::RECONCILED_PARENT_SCHEDULE)
            && $this->scheduleEquals($snapshot['children_by_year'] ?? [], self::RECONCILED_PARENT_SCHEDULE)
            && $this->scheduleEquals($snapshot['target_by_year'] ?? [], self::RECONCILED_TARGET_SCHEDULE)
            && $this->scheduleEquals($snapshot['sibling_by_year'] ?? [], self::RECONCILED_SIBLING_SCHEDULE)
            && $this->amountEquals($snapshot['parent_total'] ?? null, 25_804_800)
            && $this->amountEquals($snapshot['children_total'] ?? null, 25_804_800)
            && $this->amountEquals($snapshot['target_total'] ?? null, 24_500_000);
    }

    private function snapshot(): array
    {
        $project2028 = (float) ProjectAllocation::query()
            ->where('project_id', self::PROJECT_ID)
            ->where('year', 2028)
            ->value('amount');
        $projectActivity2028 = (float) ActivityAllocation::query()
            ->join('myb_activities', 'myb_activities.id', '=', 'myb_activity_allocations.activity_id')
            ->where('myb_activities.project_id', self::PROJECT_ID)
            ->where('myb_activity_allocations.year', 2028)
            ->sum('myb_activity_allocations.amount');
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

        return [
            'project_2028' => $project2028,
            'project_activity_2028' => $projectActivity2028,
            'project_2028_remaining' => round($project2028 - $projectActivity2028, 2),
            'parent_by_year' => $this->normalizedSchedule($parentByYear),
            'children_by_year' => $this->normalizedSchedule($childrenByYear),
            'target_by_year' => $this->normalizedSchedule($targetByYear),
            'sibling_by_year' => $this->normalizedSchedule($siblingByYear),
            'parent_total' => round(array_sum($parentByYear), 2),
            'children_total' => round(array_sum($childrenByYear), 2),
            'target_total' => round(array_sum($targetByYear), 2),
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
