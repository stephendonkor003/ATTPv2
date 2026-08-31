<?php

use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Routing\Route as IlluminateRoute;

function bootProcurementScreeningAuthorizationApplication(): array
{
    if (Container::getInstance()->bound(Kernel::class)) {
        return [Container::getInstance(), false];
    }

    $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();

    return [$application, true];
}

function procurementScreeningAuthorizationRoute($application, string $name): IlluminateRoute
{
    $route = $application['router']->getRoutes()->getByName($name);

    expect($route)->not->toBeNull("Expected route [{$name}] to be registered.");

    return $route;
}

it('protects every 3PAP operation with the procurement management permission', function () {
    [$application, $bootedHere] = bootProcurementScreeningAuthorizationApplication();

    try {
        foreach ([
            'procurement.submissions.screen-all',
            'procurement.submissions.screening.report',
            'procurement.submissions.screen',
            'procurement.submissions.screening.decision',
        ] as $routeName) {
            $middleware = procurementScreeningAuthorizationRoute($application, $routeName)
                ->gatherMiddleware();

            expect($middleware)
                ->toContain('web')
                ->toContain('auth')
                ->toContain('not.funding.partner')
                ->toContain('permission:forms.manage');
        }

        $controller = (string) file_get_contents(
            dirname(__DIR__, 2).'/app/Http/Controllers/Procurement/ProcurementSubmissionController.php'
        );

        expect(substr_count($controller, '$this->authorizeScreeningOperation($request);'))
            ->toBe(4)
            ->and($controller)
            ->toContain("\$request->user()?->can('forms.manage') === true");
    } finally {
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('derives a generic form submission procurement from the locked form record', function () {
    $root = dirname(__DIR__, 2);
    $controller = (string) file_get_contents(
        $root.'/app/Http/Controllers/Procurement/FormSubmissionController.php'
    );
    $view = (string) file_get_contents(
        $root.'/resources/views/procurement/submissions/create.blade.php'
    );

    expect($controller)
        ->toContain('->lockForUpdate()')
        ->toContain('$procurement = $this->boundProcurement($form);')
        ->toContain("'procurement_id' => \$procurement->getKey()")
        ->toContain('Procurement::query()->find($form->procurement_id)')
        ->not->toContain("\$validated['procurement_id']")
        ->not->toContain("Rule::exists('procurements', 'id')")
        ->and($view)
        ->not->toContain('name="procurement_id"')
        ->not->toContain("request('procurement_id')");
});

it('protects generic form submission creation and throttles writes', function () {
    [$application, $bootedHere] = bootProcurementScreeningAuthorizationApplication();

    try {
        $createMiddleware = procurementScreeningAuthorizationRoute($application, 'submissions.create')
            ->gatherMiddleware();
        $storeMiddleware = procurementScreeningAuthorizationRoute($application, 'submissions.store')
            ->gatherMiddleware();

        expect($createMiddleware)
            ->toContain('auth')
            ->toContain('not.funding.partner')
            ->toContain('permission:forms.submit')
            ->and($storeMiddleware)
            ->toContain('auth')
            ->toContain('not.funding.partner')
            ->toContain('permission:forms.submit')
            ->toContain('throttle:10,1,procurement-form-submission');

        $controller = (string) file_get_contents(
            dirname(__DIR__, 2).'/app/Http/Controllers/Procurement/FormSubmissionController.php'
        );

        expect(substr_count($controller, '$this->authorizeSubmissionOperation($request);'))
            ->toBe(2)
            ->and($controller)
            ->toContain("\$request->user()?->can('forms.submit') === true");
    } finally {
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('only presents 3PAP controls to procurement managers', function () {
    $root = dirname(__DIR__, 2);
    $index = (string) file_get_contents(
        $root.'/resources/views/procurement/procuresubmissions/index.blade.php'
    );
    $show = (string) file_get_contents(
        $root.'/resources/views/procurement/procuresubmissions/show.blade.php'
    );

    expect(substr_count($index, "@can('forms.manage')"))
        ->toBeGreaterThanOrEqual(3)
        ->and($show)
        ->toContain("@can('forms.manage')")
        ->toContain("route('procurement.submissions.screening.report', \$submission)");
});
