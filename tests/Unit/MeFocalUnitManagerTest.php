<?php

it('provides a responsive focal-unit control centre with operational analytics', function () {
    $root = dirname(__DIR__, 2);
    $view = file_get_contents($root.'/resources/views/me/focal-units/index.blade.php');
    $styles = file_get_contents($root.'/resources/views/me/focal-units/partials/styles.blade.php');

    expect($view)
        ->toContain('M&amp;E Focal Unit control centre')
        ->toContain('Search and responsibility scope')
        ->toContain('focal-readiness-chart')
        ->toContain('focal-consortium-chart')
        ->toContain('focal-country-chart')
        ->toContain('Focal responsibility register')
        ->toContain('data-focal-row')
        ->toContain('Platform account control')
        ->toContain("document.addEventListener('DOMContentLoaded', ready")
        ->and($styles)
        ->toContain('.mel-focal')
        ->toContain('.fu-table-wrap')
        ->toContain('overflow:auto')
        ->toContain('@media(max-width:');
});

it('exports a controlled focal responsibility PDF with an access checklist', function () {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents($root.'/app/Http/Controllers/MeFocalUnitController.php');
    $pdf = file_get_contents($root.'/resources/views/me/focal-units/report-pdf.blade.php');
    $routes = file_get_contents($root.'/routes/web.php');

    expect($controller)
        ->toContain('public function pdf(Request $request)')
        ->toContain("Pdf::loadView('me.focal-units.report-pdf'")
        ->toContain("setPaper('a4', 'landscape')")
        ->and($pdf)
        ->toContain('Executive readiness summary')
        ->toContain('Coverage profile')
        ->toContain('Detailed focal responsibility register')
        ->toContain('Accountability and access-control checklist')
        ->toContain('Controlled distribution:')
        ->and($routes)
        ->toContain("Route::get('export/pdf', 'pdf')->name('pdf')");
});

it('separates focal responsibility records from account authority and preserves history', function () {
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/MeFocalUnitController.php');

    expect($controller)
        ->toContain('public function linkAccount')
        ->toContain('public function unlinkAccount')
        ->toContain('public function restore')
        ->toContain("'is_active' => false, 'is_primary' => false")
        ->toContain('Unlink the platform account before changing this contact email or mapped organization.')
        ->toContain('demoteOtherPrimaryContacts')
        ->toContain("'think_tank_access_level' => User::THINK_TANK_ACCESS_ME")
        ->toContain('Internal, vendor and funding-partner accounts cannot be converted');
});
