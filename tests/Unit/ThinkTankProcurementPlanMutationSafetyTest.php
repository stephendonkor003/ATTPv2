<?php

use App\Http\Controllers\ThinkTankProcurementPlanController;
use App\Models\ThinkTankProcurementDocument;
use App\Models\ThinkTankProcurementItem;
use App\Models\ThinkTankProcurementPlan;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function bootThinkTankProcurementPlanMutationApplication(): array
{
    if (Container::getInstance()->bound(Kernel::class)) {
        return [Container::getInstance(), false];
    }

    $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();

    return [$application, true];
}

it('locks the plan before nested records and rechecks lifecycle state inside mutations', function (): void {
    $controller = file_get_contents(
        dirname(__DIR__, 2).'/app/Http/Controllers/ThinkTankProcurementPlanController.php'
    );

    expect(substr_count($controller, '$this->lockPlanForMutation('))->toBeGreaterThanOrEqual(8)
        ->and(substr_count($controller, '$this->lockItemForMutation('))->toBeGreaterThanOrEqual(6)
        ->and($controller)->toContain("->where('think_tank_member_id', \$member->getKey())")
        ->toContain("->where('plan_id', \$plan->getKey())")
        ->toContain("abort_unless(\$lockedPlan->isEditable()")
        ->toContain("abort_unless(\$lockedItem->isEditable()")
        ->toContain("\$lockedItem->status === ThinkTankProcurementItem::STATUS_NO_OBJECTION")
        ->toContain("\$lockedItem->status === ThinkTankProcurementItem::STATUS_PUBLISHED")
        ->toContain("->where('item_id', \$lockedItem->id)")
        ->toContain('->orderBy(\'id\')')
        ->toContain('->lockForUpdate()');
});

it('accepts only canonical document paths for the exact locked plan item boundary', function (): void {
    [, $bootedHere] = bootThinkTankProcurementPlanMutationApplication();

    try {
        $controller = app(ThinkTankProcurementPlanController::class);
        $method = new ReflectionMethod($controller, 'procurementDocumentPath');

        $plan = (new ThinkTankProcurementPlan)->forceFill(['id' => 'plan-id']);
        $item = (new ThinkTankProcurementItem)->forceFill(['id' => 'item-id']);
        $document = (new ThinkTankProcurementDocument)->forceFill([
            'file_path' => 'think-tank-procurement/plan-id/item-id/evidence.pdf',
        ]);

        expect($method->invoke($controller, $plan, $item, $document))
            ->toBe('think-tank-procurement/plan-id/item-id/evidence.pdf');

        foreach ([
            '../evidence.pdf',
            'think-tank-procurement/plan-id/item-id/../evidence.pdf',
            'think-tank-procurement/plan-id/other-item/evidence.pdf',
            'think-tank-procurement/plan-id/item-id/%2e%2e/evidence.pdf',
            "think-tank-procurement/plan-id/item-id/evidence.pdf\0.txt",
            'C:/think-tank-procurement/plan-id/item-id/evidence.pdf',
        ] as $unsafePath) {
            $document->file_path = $unsafePath;
            expect(fn () => $method->invoke($controller, $plan, $item, $document))
                ->toThrow(NotFoundHttpException::class);
        }
    } finally {
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('forces private inert attachment responses for procurement planning documents', function (): void {
    $controller = file_get_contents(
        dirname(__DIR__, 2).'/app/Http/Controllers/ThinkTankProcurementPlanController.php'
    );

    expect($controller)->toContain("'Content-Type' => 'application/octet-stream'")
        ->toContain("'Content-Security-Policy' => \"default-src 'none'; sandbox\"")
        ->toContain("'X-Content-Type-Options' => 'nosniff'")
        ->toContain("'attachment'")
        ->toContain('$response->setPrivate()')
        ->toContain("addCacheControlDirective('no-store')")
        ->toContain('realpath($disk->path($path))')
        ->toContain('$this->pathIsInside($absolutePath, $diskRoot)')
        ->toContain('$this->safeDocumentDownloadName($document)');
});
