<?php

use App\Http\Controllers\SubActivityController;
use App\Models\Activity;
use App\Models\ActivityAllocation;
use App\Models\Project;
use App\Models\SubActivity;
use App\Models\SubActivityAllocation;

function subActivityAllocationFixture(array $activityBudgets, array $targetAllocations, array $siblingAllocations): SubActivity
{
    $project = new Project([
        'start_year' => min(array_keys($activityBudgets)),
        'end_year' => max(array_keys($activityBudgets)),
    ]);

    $activity = new Activity();
    $activity->setRelation('project', $project);
    $activity->setRelation('allocations', collect($activityBudgets)
        ->map(fn ($amount, $year) => new ActivityAllocation(['year' => $year, 'amount' => $amount]))
        ->values());

    $target = new SubActivity();
    $target->forceFill(['id' => 'target']);
    $target->setRelation('activity', $activity);
    $target->setRelation('allocations', collect($targetAllocations)
        ->map(fn ($amount, $year) => new SubActivityAllocation(['year' => $year, 'amount' => $amount]))
        ->values());

    $sibling = new SubActivity();
    $sibling->forceFill(['id' => 'sibling']);
    $sibling->setRelation('activity', $activity);
    $sibling->setRelation('allocations', collect($siblingAllocations)
        ->map(fn ($amount, $year) => new SubActivityAllocation(['year' => $year, 'amount' => $amount]))
        ->values());

    $activity->setRelation('subActivities', collect([$target, $sibling]));

    return $target;
}

function validateSubActivityAllocationFixture(SubActivity $target, array &$proposed): ?string
{
    $method = new ReflectionMethod(SubActivityController::class, 'validateSubActivityAllocations');

    return $method->invokeArgs(new SubActivityController(), [$target, &$proposed]);
}

it('allows a corrective rephasing when an unrelated legacy year overage is reduced', function () {
    $target = subActivityAllocationFixture(
        [2025 => 0, 2026 => 25_000_000],
        [2025 => 24_500_000, 2026 => 0],
        [2025 => 24_800, 2026 => 0],
    );
    $proposed = [2025 => 0, 2026 => 24_000_000];

    expect(validateSubActivityAllocationFixture($target, $proposed))->toBeNull();
});

it('still rejects a new annual over-allocation', function () {
    $target = subActivityAllocationFixture(
        [2025 => 0, 2026 => 25_000_000, 2027 => 1_000_000],
        [2025 => 24_500_000, 2026 => 0, 2027 => 0],
        [2025 => 24_800, 2026 => 0, 2027 => 0],
    );
    $proposed = [2025 => 0, 2026 => 25_100_000, 2027 => 0];

    expect(validateSubActivityAllocationFixture($target, $proposed))
        ->toContain('year 2026 would exceed the parent activity budget');
});

it('still rejects an increase to an existing total-envelope exception', function () {
    $target = subActivityAllocationFixture(
        [2025 => 0, 2026 => 24_000_000],
        [2025 => 24_500_000, 2026 => 0],
        [2025 => 24_800, 2026 => 0],
    );
    $proposed = [2025 => 0, 2026 => 24_600_000];

    expect(validateSubActivityAllocationFixture($target, $proposed))
        ->toContain('combined sub-activity total');
});
