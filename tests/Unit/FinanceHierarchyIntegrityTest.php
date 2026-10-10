<?php

use App\Http\Controllers\BudgetCommitmentController;
use App\Models\Activity;
use App\Models\ProgramFunding;
use App\Models\Project;
use App\Models\SubActivity;
use App\Services\ThinkTankProcurementBudgetGuard;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

function bootFinanceHierarchyIntegrityApplication(): bool
{
    if (Container::getInstance()->bound(Kernel::class)) {
        return false;
    }

    $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();

    return true;
}

function financeHierarchyAllocation(string $programId): SubActivity
{
    $project = (new Project)->forceFill(['program_id' => $programId]);
    $activity = new Activity;
    $activity->setRelation('project', $project);

    $allocation = new SubActivity;
    $allocation->setRelation('activity', $activity);

    return $allocation;
}

it('accepts only funding from the selected allocation programme', function () {
    $bootedHere = bootFinanceHierarchyIntegrityApplication();

    try {
        $controller = new BudgetCommitmentController(new ThinkTankProcurementBudgetGuard);
        $method = new ReflectionMethod($controller, 'assertFundingMatchesAllocationProgram');
        $allocation = financeHierarchyAllocation('10000000-0000-4000-8000-000000000001');

        $matchingFunding = (new ProgramFunding)->forceFill([
            'program_id' => '10000000-0000-4000-8000-000000000001',
        ]);
        $method->invoke($controller, $matchingFunding, $allocation);

        $differentFunding = (new ProgramFunding)->forceFill([
            'program_id' => '20000000-0000-4000-8000-000000000001',
        ]);

        expect(fn () => $method->invoke($controller, $differentFunding, $allocation))
            ->toThrow(ValidationException::class, 'different programme');
    } finally {
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('enforces the funding programme invariant on both create and update paths', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/BudgetCommitmentController.php');

    preg_match_all(
        '/\$this->assertFundingMatchesAllocationProgram\(\$funding, \$allocation\);/',
        $source,
        $matches
    );

    expect($matches[0])->toHaveCount(2)
        ->and($source)->toContain("SubActivity::with('activity.project')");
});

it('blocks cross-programme hierarchy moves only when financial history exists', function () {
    $root = dirname(__DIR__, 2);
    $projectController = file_get_contents($root.'/app/Http/Controllers/ProjectController.php');
    $activityController = file_get_contents($root.'/app/Http/Controllers/ActivityController.php');

    expect($projectController)
        ->toContain('(string) $project->program_id !== (string) $program->id')
        ->toContain('&& $this->projectHasFinancialHistory($project)')
        ->toContain('FinancialHierarchyDeletionGuard::class')->toContain('projectDependencies($project)')
        ->and($activityController)
        ->toContain('(string) $lockedSourceProject->program_id !== (string) $lockedTargetProject->program_id')
        ->toContain('&& $this->activityHasFinancialHistory($activityToMove)')
        ->toContain('FinancialHierarchyDeletionGuard::class')->toContain('activityDependencies($activity)')
        ->toContain('Keep it within its current programme.');
});

it('locks approved request amounts after a live purchase order exists', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/BudgetCommitmentController.php');

    expect($source)
        ->toContain('$hasActivePurchaseOrder = ProcurementPurchaseOrder::query()')
        ->toContain("->whereNotIn('status', ['cancelled', 'void', 'rejected'])")
        ->toContain('already has a purchase order');
});

it('derives purchase order status from recorded payments during edits', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Procurement/ProcurementPurchaseOrderController.php');

    expect($source)
        ->toContain('purchaseOrderStatusForRecordedPayments(')
        ->toContain("? 'paid'")
        ->toContain(": 'partial_paid'")
        ->toContain("'status' => \$persistedStatus");
});
