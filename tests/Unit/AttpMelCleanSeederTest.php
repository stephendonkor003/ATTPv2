<?php

use App\Services\AttpMelFrameworkInstaller;
use Database\Seeders\AttpMelThinkTankReportingSeeder;

it('registers the requested framework URL and handles an unseeded database without a 404', function () {
    $routes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');
    $controller = file_get_contents(
        dirname(__DIR__, 2).'/app/Http/Controllers/MeFrameworkController.php'
    );

    expect($routes)
        ->toContain("Route::prefix('me/framework')")
        ->toContain("Route::get('/', 'index')->name('index')")
        ->and($controller)
        ->toContain('me.configuration.manage|me.configuration.view')
        ->toContain("response()->view('me.framework.missing')")
        ->not->toContain('current()->firstOrFail()');
});

it('ships one explicit atomic clean seeder instead of making normal application seeding destructive', function () {
    $root = dirname(__DIR__, 2);
    $cleanSeeder = file_get_contents($root.'/database/seeders/AttpMelCleanSeeder.php');
    $normalSeeder = file_get_contents($root.'/database/seeders/DatabaseSeeder.php');

    expect($cleanSeeder)
        ->toContain('DB::transaction(function (): array')
        ->toContain('$this->purgeControlledDataset();')
        ->toContain('$this->call(AttpMelFrameworkSeeder::class);')
        ->toContain('$this->validateCleanInstall();')
        ->toContain('90 clean targets')
        ->toContain('24 indicator disaggregation requirements')
        ->toContain('every collection assigned to every active Think Tank')
        ->and($normalSeeder)->not->toContain('AttpMelCleanSeeder::class');
});

it('defines the complete official framework reporting and evidence configuration', function () {
    $installer = file_get_contents(
        dirname(__DIR__, 2).'/app/Services/AttpMelFrameworkInstaller.php'
    );
    $reportingSeeder = file_get_contents(
        dirname(__DIR__, 2).'/database/seeders/AttpMelThinkTankReportingSeeder.php'
    );
    $readiness = file_get_contents(
        dirname(__DIR__, 2).'/app/Services/MeReportingReadinessService.php'
    );

    expect(AttpMelFrameworkInstaller::INDICATOR_CODES)->toHaveCount(18)
        ->and(AttpMelThinkTankReportingSeeder::FORM_CODES)->toHaveCount(7)
        ->and(AttpMelThinkTankReportingSeeder::DIMENSION_CODES)->toHaveCount(12)
        ->and($installer)
        ->toContain("'target_context' => Indicator::SETUP_TARGET_CONTEXT")
        ->toContain("'means_of_verification_folder_id' => \$evidenceFolder->id")
        ->toContain("->where('portfolio_id', \$portfolioId)")
        ->toContain("->where('code', \$code)")
        ->and($reportingSeeder)
        ->toContain("'portfolio_id' => \$this->portfolioId")
        ->toContain("'government_body_type' => true")
        ->toContain("'research_output_type' => true")
        ->toContain("'support_type' => true")
        ->and($readiness)
        ->toContain("whereNotNull('means_of_verification_folder_id')")
        ->not->toContain("whereNotNull('means_of_verification_id')");
});
