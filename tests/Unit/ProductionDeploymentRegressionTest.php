<?php

use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\View\Compilers\BladeCompiler;

/** @return array{0: \Illuminate\Foundation\Application, 1: bool} */
function bootProductionDeploymentRegressionApplication(): array
{
    if (Container::getInstance()->bound(Kernel::class)) {
        return [Container::getInstance(), false];
    }

    $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();

    return [$application, true];
}

it('compiles every Blade template without raw PHP directives or unbalanced components', function (): void {
    [$application, $bootedHere] = bootProductionDeploymentRegressionApplication();

    try {
        /** @var BladeCompiler $compiler */
        $compiler = $application->make('blade.compiler');
        $views = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(resource_path('views'), FilesystemIterator::SKIP_DOTS),
        );
        $uncompiled = [];
        $componentImbalances = [];
        $compiledCount = 0;

        foreach ($views as $view) {
            if (! $view->isFile() || ! str_ends_with($view->getFilename(), '.blade.php')) {
                continue;
            }

            $compiledCount++;
            $compiled = $compiler->compileString((string) file_get_contents($view->getPathname()));

            if (preg_match('/(?<!@)@php(?:\s|\()/i', $compiled) === 1) {
                $uncompiled[] = str_replace('\\', '/', $view->getPathname());
            }

            $componentStarts = substr_count($compiled, 'startComponent(');
            $componentRenders = substr_count($compiled, 'renderComponent()');
            if ($componentStarts !== $componentRenders) {
                $componentImbalances[] = [
                    'view' => str_replace('\\', '/', $view->getPathname()),
                    'starts' => $componentStarts,
                    'renders' => $componentRenders,
                ];
            }
        }

        expect($compiledCount)->toBeGreaterThan(0)
            ->and($uncompiled)->toBe([])
            ->and($componentImbalances)->toBe([]);
    } finally {
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('locks Laravel to a release containing the stale recaller fix', function (): void {
    $lock = json_decode(
        (string) file_get_contents(dirname(__DIR__, 2).'/composer.lock'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $packages = collect($lock['packages'] ?? [])->keyBy('name');
    $frameworkVersion = ltrim((string) data_get($packages, 'laravel/framework.version'), 'v');

    expect($frameworkVersion)->not->toBe('')
        ->and(version_compare($frameworkVersion, '12.69.1', '>='))->toBeTrue();
});
