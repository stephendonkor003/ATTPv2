<?php

use App\Services\ProcurementRichTextService;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;

function bootThinkTankVendorDirectoryApplication(): array
{
    if (Container::getInstance()->bound(Kernel::class)) {
        return [Container::getInstance(), false];
    }

    $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();

    return [$application, true];
}

it('purifies procurement rich text with a strict allowlist', function () {
    [, $bootedHere] = bootThinkTankVendorDirectoryApplication();

    try {
        $richText = new ProcurementRichTextService;
        $clean = $richText->sanitizeForStorage(
            '<h2>Scope</h2><p onmouseover="alert(1)">Safe <strong>details</strong>'.
            '<script>alert(2)</script><img src=x onerror="alert(3)">'.
            '<a href="javascript:alert(4)">bad link</a>'.
            '<a href="https://example.test/tender">good link</a></p>'
        );

        expect($clean)->toContain('<h2>Scope</h2>', '<strong>details</strong>', 'https://example.test/tender')
            ->and($clean)->not->toContain('script', 'onmouseover', 'onerror', '<img', 'javascript:')
            ->and($richText->sanitizeForStorage('<p><br></p>'))->toBeNull()
            ->and((string) $richText->render('Budget < 10k'))->toBe('Budget &lt; 10k')
            ->and(str_replace("\r\n", "\n", (string) $richText->render('Line one'."\n".'Line two')))
            ->toBe('Line one<br />'."\n".'Line two');
    } finally {
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('defines a tenant-isolated vendor directory and UUID audience contract', function () {
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_10_01_000004_create_think_tank_vendor_directory.php');
    $routes = file_get_contents(dirname(__DIR__, 2).'/routes/api/think-tank.php');
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Api/V1/ThinkTank/VendorDirectoryController.php');
    $directory = file_get_contents(dirname(__DIR__, 2).'/app/Services/ThinkTankVendorDirectoryService.php');
    $execution = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Api/V1/ThinkTank/ProcurementExecutionController.php');
    $richText = file_get_contents(dirname(__DIR__, 2).'/app/Services/ProcurementRichTextService.php');
    $repairMigration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_10_01_000005_remove_think_tank_payees_from_vendor_directories.php');
    $vendorPortal = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Vendor/VendorProcurementController.php');
    $composer = json_decode(file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($migration)->toContain(
        "Schema::create('attp_think_tank_vendor_user'",
        "Schema::create('attp_think_tank_vendor_categories'",
        "Schema::create('attp_think_tank_vendor_category_user'",
        "['think_tank_member_id', 'vendor_user_id']",
        'attp_tt_vendor_category_user_membership_fk',
        "'can_manage_setup'",
        "'tenant_vendor_category_ids'",
        "'tenant_vendor_ids'",
    )->and($routes)->toContain(
        "Route::get('vendor-directory'",
        "Route::post('vendor-categories'",
        "Route::post('vendors'",
        "Route::patch('vendors/{vendor}'",
        "Route::post('vendors/{vendor}/invitation'",
    )->and($controller)->toContain(
        "'think_tank_member_id' => \$tenant->getKey()",
        "'category_ids.*' => ['uuid', 'distinct']",
        "'credentialsSent' => \$invitationSent",
        "globally blacklisted",
        "globally disabled",
        'IDENTITY_RECONCILIATION_REQUIRED',
        "'think-tank-user-email:'",
        'must_change_password',
        'password_changed_at !== null',
        'FILTER_VALIDATE_EMAIL',
        'while still holding the shared identity lock used by resends',
        "can_manage_setup', true",
    )->and($directory)->toContain(
        'vendorCanAccess',
        "->where('think_tank_member_id', \$tenantId)",
        'tenant_vendor_category_ids',
        'tenant_vendor_ids',
        "\$targetVendorIds === []\n            && \$targetCategoryIds === []",
    )->and($execution)->toContain(
        "'tenant_vendor_category_ids.*' => ['uuid', 'distinct']",
        "'tenant_vendor_ids.*' => ['uuid', 'distinct']",
        "'tenant_vendor_category_ids' => \$targets['categoryIds'] ?: null",
        "'tenant_vendor_ids' => \$targets['vendorIds'] ?: null",
    )->and($vendorPortal)->toContain(
        'vendorCanAccess($user, $procurement)',
        'assertVendorCategoryAccess($user, $procurement)',
        "->where('vendor_user_id', \$user->getKey())",
        "->whereIn('think_tank_member_id', \$activeTenantIds)",
        'abort(404)',
    )->and($richText)->toContain(
        "storage_path('framework/cache/htmlpurifier')",
        "'Cache.SerializerPath'",
    )->and($repairMigration)->toContain(
        "whereNull('membership.invited_by')",
        "users.think_tank_member_id",
        "member.vendor_user_id",
        "'can_manage_setup'",
    )->and($composer['require']['ezyang/htmlpurifier'] ?? null)->toBe('^4.19');
});
