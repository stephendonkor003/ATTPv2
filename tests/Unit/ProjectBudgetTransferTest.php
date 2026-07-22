<?php

use App\Http\Controllers\AllocationSummaryController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\BudgetReportController;
use App\Models\Activity;
use App\Models\ActivityAllocation;
use App\Models\Project;
use App\Models\ProjectAllocation;
use App\Models\Program;
use App\Models\Sector;
use App\Models\SubActivity;
use App\Models\SubActivityAllocation;

it('moves the activity budget envelope between components without counting sub-activities again', function () {
    $controller = new ActivityController();
    $method = new \ReflectionMethod($controller, 'budgetEnvelopeTransferPlan');

    $plan = $method->invoke(
        $controller,
        5_000_000.00,
        34_000_000.00,
        [2024 => 0.00, 2025 => 441_300.00, 2026 => 1_716_800.00, 2027 => 1_446_900.00, 2028 => 1_395_000.00],
        [2024 => 0.00, 2025 => 591_300.00, 2026 => 12_942_800.00, 2027 => 12_443_400.00, 2028 => 8_022_500.00],
        [2024 => 0.00, 2025 => 0.00, 2026 => 300_000.00, 2027 => 300_000.00, 2028 => 300_000.00]
    );

    expect($plan['moved_amount'])->toBe(900_000.00)
        ->and($plan['source_total'])->toBe(4_100_000.00)
        ->and($plan['target_total'])->toBe(34_900_000.00)
        ->and($plan['source_total'] + $plan['target_total'])->toBe(39_000_000.00)
        ->and($plan['source_yearly'])->toBe([
            2024 => 0.00,
            2025 => 441_300.00,
            2026 => 1_416_800.00,
            2027 => 1_146_900.00,
            2028 => 1_095_000.00,
        ])
        ->and($plan['target_yearly'])->toBe([
            2024 => 0.00,
            2025 => 591_300.00,
            2026 => 13_242_800.00,
            2027 => 12_743_400.00,
            2028 => 8_322_500.00,
        ]);
});

it('rebalances target years without changing the component budget envelope', function () {
    $controller = new ActivityController();
    $method = new \ReflectionMethod($controller, 'rebalanceAllocationPlan');

    $plan = $method->invoke(
        $controller,
        [2025 => 100.00, 2026 => 400.00, 2027 => 500.00],
        [2025 => 300.00, 2026 => 400.00, 2027 => 100.00]
    );

    expect($plan)->toBe([
        2025 => 300.00,
        2026 => 400.00,
        2027 => 300.00,
    ])->and(array_sum($plan))->toBe(1000.00);
});

it('rejects a reallocation when the target component has insufficient total budget', function () {
    $controller = new ActivityController();
    $method = new \ReflectionMethod($controller, 'rebalanceAllocationPlan');

    expect(fn () => $method->invoke(
        $controller,
        [2025 => 100.00, 2026 => 200.00],
        [2025 => 200.00, 2026 => 200.00]
    ))->toThrow(\RuntimeException::class, 'does not have enough available budget');
});

it('uses the project envelope once in allocation summary reporting', function () {
    $project = new Project(['total_budget' => 1000.00]);
    $project->setRelation('allocations', collect([
        new ProjectAllocation(['amount' => 1000.00]),
    ]));

    $activity = new Activity();
    $activity->setRelation('allocations', collect([
        new ActivityAllocation(['amount' => 600.00]),
    ]));

    $subActivity = new SubActivity();
    $subActivity->setRelation('allocations', collect([
        new SubActivityAllocation(['amount' => 300.00]),
    ]));

    $activity->setRelation('subActivities', collect([$subActivity]));
    $project->setRelation('activities', collect([$activity]));

    $controller = new AllocationSummaryController();
    $method = new \ReflectionMethod($controller, 'projectAllocationEnvelope');

    expect($method->invoke($controller, $project))->toBe(1000.00);
});

it('reports sub-activity gaps and overallocations per activity without netting branches together', function () {
    $sector = new Sector(['name' => 'Policy']);
    $program = new Program(['name' => 'Programme']);
    $program->setRelation('sector', $sector);

    $project = \Mockery::mock(Project::class)->makePartial();
    $project->forceFill([
        'name' => 'Project',
        'total_budget' => 300.00,
        'start_year' => 2025,
        'end_year' => 2025,
    ]);
    $project->shouldReceive('loadMissing')->andReturnSelf();
    $project->setRelation('program', $program);
    $project->setRelation('allocations', collect([
        new ProjectAllocation(['year' => 2025, 'amount' => 300.00]),
    ]));

    $overallocatedActivity = new Activity(['name' => 'Overallocated activity']);
    $overallocatedActivity->setRelation('allocations', collect([
        new ActivityAllocation(['year' => 2025, 'amount' => 100.00]),
    ]));
    $overallocatedSubActivity = new SubActivity(['name' => 'Overallocated sub-activity']);
    $overallocatedSubActivity->setRelation('allocations', collect([
        new SubActivityAllocation(['year' => 2025, 'amount' => 120.00]),
    ]));
    $overallocatedActivity->setRelation('subActivities', collect([$overallocatedSubActivity]));

    $partiallyAllocatedActivity = new Activity(['name' => 'Partially allocated activity']);
    $partiallyAllocatedActivity->setRelation('allocations', collect([
        new ActivityAllocation(['year' => 2025, 'amount' => 100.00]),
    ]));
    $partiallyAllocatedSubActivity = new SubActivity(['name' => 'Partial sub-activity']);
    $partiallyAllocatedSubActivity->setRelation('allocations', collect([
        new SubActivityAllocation(['year' => 2025, 'amount' => 50.00]),
    ]));
    $partiallyAllocatedActivity->setRelation('subActivities', collect([$partiallyAllocatedSubActivity]));

    $project->setRelation('activities', collect([$overallocatedActivity, $partiallyAllocatedActivity]));

    $controller = new BudgetReportController();
    $method = new \ReflectionMethod($controller, 'buildSelectedProjectProgress');
    $payload = $method->invoke($controller, $project);

    expect($payload['summary']['project_budget'])->toBe(300.00)
        ->and($payload['summary']['activity_budget'])->toBe(200.00)
        ->and($payload['summary']['sub_activity_budget'])->toBe(170.00)
        ->and($payload['summary']['remaining_to_sub_activities'])->toBe(50.00)
        ->and($payload['summary']['sub_activity_overallocation'])->toBe(20.00)
        ->and($payload['summary']['sub_activity_progress'])->toBe(75.0);
});
