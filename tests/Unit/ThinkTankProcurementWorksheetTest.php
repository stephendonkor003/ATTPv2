<?php

use App\Http\Controllers\AdminThinkTankProcurementWorksheetController;
use App\Models\ThinkTankProcurementItem;
use App\Models\ThinkTankProcurementPlan;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function bootThinkTankProcurementWorksheetApplication(): array
{
    if (Container::getInstance()->bound(Kernel::class)) {
        return [Container::getInstance(), false];
    }

    $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();

    return [$application, true];
}

it('registers the worksheet and private evidence routes behind Secretariat permissions', function () {
    [$application, $bootedHere] = bootThinkTankProcurementWorksheetApplication();

    try {
        $routes = $application->make(Router::class)->getRoutes();

        foreach ([
            'think-tank-procurement.worksheet.index',
            'think-tank-procurement.worksheet.show',
            'think-tank-procurement.worksheet.pdf',
        ] as $name) {
            $route = $routes->getByName($name);
            expect($route)->not->toBeNull("Expected route [{$name}] to be registered.");
            $middleware = implode('|', $route->middleware());
            expect($middleware)
                ->toContain('auth')
                ->toContain('not.funding.partner')
                ->toContain('think_tank.procurement.review')
                ->toContain('think_tank.procurement.step')
                ->toContain('procurement.view_all')
                ->toContain('procurement.manage_all');
        }

        $documentRoute = $routes->getByName('think-tank-procurement.documents.download');
        expect($documentRoute)->not->toBeNull()
            ->and(implode('|', $documentRoute->middleware()))
            ->toContain('think_tank.procurement.step');

        expect(implode('|', $routes->getByName('think-tank-procurement.items.decision')->middleware()))
            ->toContain('think_tank.procurement.review')
            ->not->toContain('think_tank.procurement.step')
            ->and(implode('|', $routes->getByName('think-tank-procurement.items.no-objection')->middleware()))
            ->toContain('think_tank.procurement.step')
            ->and(implode('|', $routes->getByName('think-tank-procurement.reports')->middleware()))
            ->toContain('think_tank.procurement.step');
    } finally {
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('rejects an item that is not nested under the requested procurement plan', function () {
    $controller = new AdminThinkTankProcurementWorksheetController;
    $plan = (new ThinkTankProcurementPlan)->forceFill([
        'id' => '10000000-0000-4000-8000-000000000001',
    ]);
    $item = (new ThinkTankProcurementItem)->forceFill([
        'id' => '10000000-0000-4000-8000-000000000002',
        'plan_id' => '20000000-0000-4000-8000-000000000001',
    ]);

    expect(fn () => $controller->show(Request::create('/worksheet', 'GET'), $plan, $item))
        ->toThrow(NotFoundHttpException::class);
});

it('uses STEP export and no-objection state as distinct worksheet stages', function () {
    $controller = new AdminThinkTankProcurementWorksheetController;
    $method = (new ReflectionClass($controller))->getMethod('workflowStages');
    $plan = (new ThinkTankProcurementPlan)->forceFill(['status' => 'approved']);
    $item = (new ThinkTankProcurementItem)->forceFill([
        'status' => 'approved',
        'step_exported_at' => null,
    ]);

    $beforeStep = $method->invoke($controller, $plan, $item);
    $item->step_exported_at = now();
    $afterStep = $method->invoke($controller, $plan, $item);
    $item->status = 'no_objection_obtained';
    $ready = $method->invoke($controller, $plan, $item);

    expect($beforeStep[2])->toMatchArray(['label' => 'STEP handoff', 'state' => 'current'])
        ->and($beforeStep[3])->toMatchArray(['label' => 'World Bank review', 'state' => 'upcoming'])
        ->and($afterStep[2])->toMatchArray(['label' => 'STEP handoff', 'state' => 'complete'])
        ->and($afterStep[3])->toMatchArray(['label' => 'World Bank review', 'state' => 'current'])
        ->and($ready[4])->toMatchArray(['label' => 'Ready to execute', 'state' => 'current']);
});

it('presents item context transitions controlled PDFs and aggregate notification delivery only', function () {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents($root.'/app/Http/Controllers/AdminThinkTankProcurementWorksheetController.php');
    $adminController = file_get_contents($root.'/app/Http/Controllers/AdminThinkTankProcurementController.php');
    $index = file_get_contents($root.'/resources/views/think-tank-procurement-admin/worksheet/index.blade.php');
    $show = file_get_contents($root.'/resources/views/think-tank-procurement-admin/worksheet/show.blade.php');
    $pdf = file_get_contents($root.'/resources/views/think-tank-procurement-admin/worksheet/pdf.blade.php');
    $sidebar = file_get_contents($root.'/resources/views/layouts/partials/sidebar.blade.php');

    expect($controller)
        ->toContain("'world_bank_pending'")
        ->toContain("->where('status', 'no_objection_obtained')")
        ->toContain("'statusNotifications as notifications_queued_count'")
        ->toContain("['pending', 'processing', 'sending']")
        ->toContain("'statusNotifications as notifications_sent_count'")
        ->toContain("'statusNotifications as notifications_failed_count'")
        ->toContain('PdfBranding::viewData()')
        ->toContain('PdfPageNumbering::stamp($pdf)')
        ->toContain("'Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0'")
        ->not->toContain('recipient_email')
        ->not->toContain('recipient_name')
        ->and($adminController)
        ->toContain("! str_contains(\$path, '..')")
        ->toContain("'Content-Type' => 'application/octet-stream'")
        ->toContain("'Content-Security-Policy' => \"default-src 'none'; sandbox\"")
        ->toContain("addCacheControlDirective('no-store')")
        ->and($index)
        ->toContain('Submitted to AUC-ATTP')
        ->toContain('Pending World Bank no-objection')
        ->toContain('No-objection received / ready to execute')
        ->toContain('name="think_tank_member_id"')
        ->toContain('name="fiscal_year"')
        ->toContain('name="queue"')
        ->toContain('name="documents"')
        ->and($show)
        ->toContain("route('think-tank-procurement.items.decision'")
        ->toContain("route('think-tank-procurement.items.no-objection'")
        ->toContain('Record no-objection and mark ready to execute')
        ->toContain('max="{{ now()->toDateString() }}"')
        ->toContain('data-wb-reference')
        ->toContain('data-wb-document')
        ->toContain('Notification delivery summary')
        ->not->toContain('recipient_email')
        ->not->toContain('recipient_name')
        ->and($pdf)
        ->toContain('Controlled internal record')
        ->toContain('Private document register')
        ->toContain('Status and decision timeline')
        ->not->toContain('recipient_email')
        ->not->toContain('recipient_name')
        ->and($sidebar)
        ->toContain('Item Review Worksheet')
        ->toContain("route('think-tank-procurement.worksheet.index')");
});
