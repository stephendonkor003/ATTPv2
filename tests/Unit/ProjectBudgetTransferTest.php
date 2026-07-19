<?php

use App\Models\Activity;
use App\Models\ActivityAllocation;
use App\Models\Project;

it('transfers the activity budget from the source project to the target project when reallocated', function () {
    $sourceProject = new Project(['total_budget' => 1000.00]);
    $targetProject = new Project(['total_budget' => 250.00]);

    $activity = new Activity();
    $activity->setRelation('project', $sourceProject);
    $activity->setRelation('allocations', collect([
        new ActivityAllocation(['year' => 2025, 'amount' => 300.00]),
        new ActivityAllocation(['year' => 2026, 'amount' => 150.00]),
    ]));
    $activity->setRelation('subActivities', collect());

    $transferredAmount = $sourceProject->transferActivityBudgetTo($activity, $targetProject, false);

    expect($transferredAmount)->toBe(450.00)
        ->and($sourceProject->total_budget)->toBe(550.00)
        ->and($targetProject->total_budget)->toBe(700.00);
});
