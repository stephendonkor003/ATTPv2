<?php

it('registers a focused administrative assistant evidence workspace', function () {
    $routes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/AdministrativeAssistantEvidenceController.php');
    $redirectMiddleware = file_get_contents(dirname(__DIR__, 2).'/app/Http/Middleware/RedirectAdministrativeAssistantToPortal.php');

    expect($routes)
        ->toContain("->prefix('administrative-assistant')")
        ->toContain("->name('administrative-assistant.')")
        ->toContain("->name('evidence.store')");

    expect($controller)
        ->toContain("'source' => 'administrative_assistant'")
        ->toContain('resolveOrCreateInvoice(')
        ->toContain("'invoice_id' => \$invoice?->id")
        ->toContain("'vendor_submission_status' => ProcurementPurchaseOrderItemEvidence::VENDOR_STATUS_SUBMITTED");

    expect($redirectMiddleware)
        ->toContain("'administrative-assistant.*'")
        ->toContain("->route('administrative-assistant.dashboard')");
});

it('shows assistant evidence on finance and vendor invoice pages', function () {
    $financeInvoice = file_get_contents(dirname(__DIR__, 2).'/resources/views/procurement/invoices/show.blade.php');
    $vendorInvoice = file_get_contents(dirname(__DIR__, 2).'/resources/views/vendor/invoices/show.blade.php');
    $assistantView = file_get_contents(dirname(__DIR__, 2).'/resources/views/administrative-assistant/evidence.blade.php');

    expect($financeInvoice)->toContain('Invoice & Evidence Documents');
    expect($vendorInvoice)->toContain('Invoice & Evidence Documents');
    expect($assistantView)
        ->toContain('Upload and link everything')
        ->toContain('automatically connected to the purchase request, vendor account, and invoice register');
});

it('organizes assistant work into year month and vendor cards', function () {
    $dashboard = file_get_contents(dirname(__DIR__, 2).'/resources/views/administrative-assistant/dashboard.blade.php');

    expect($dashboard)
        ->toContain('Choose a year')
        ->toContain('monthly folders')
        ->toContain('Choose a month')
        ->toContain('vendor-card')
        ->toContain('Each vendor has one clear card');
});
