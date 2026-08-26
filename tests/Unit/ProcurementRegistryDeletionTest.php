<?php

it('exposes a System Admin-only procurement registry deletion action', function () {
    $root = dirname(__DIR__, 2);
    $routes = file_get_contents($root.'/routes/web.php');
    $controller = file_get_contents($root.'/app/Http/Controllers/Procurement/ProcurementController.php');
    $view = file_get_contents($root.'/resources/views/procurement/index.blade.php');

    expect($routes)
        ->toContain("Route::delete('/{procurement}', [ProcurementController::class, 'destroy'])")
        ->toContain("->name('destroy')");

    expect($controller)
        ->toContain('public function destroy(Request $request, Procurement $procurement)')
        ->toContain('$request->user()?->isAdmin()')
        ->toContain('Only System Admin users can delete procurements.')
        ->toContain("\$lockedProcurement->status !== 'draft'")
        ->toContain('procurementHasPublicationHistory($lockedProcurement)')
        ->toContain('procurementDeletionDependency($lockedProcurement)')
        ->toContain('DB::transaction(function () use ($procurement): ?string')
        ->toContain('->lockForUpdate()')
        ->toContain('$lockedProcurement->delete();');

    expect($view)
        ->toContain('auth()->user()?->isAdmin()')
        ->toContain("route('procurements.destroy', \$p)")
        ->toContain("@method('DELETE')")
        ->toContain('This cannot be undone.');
});

it('blocks operational records and cleans only setup data before deletion', function () {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents($root.'/app/Http/Controllers/Procurement/ProcurementController.php');
    $model = file_get_contents($root.'/app/Models/Procurement.php');

    foreach ([
        'form_submissions',
        'evaluation_submissions',
        'procurement_contract_negotiations',
        'procurement_disbursements',
        'procurement_invoices',
        'procurement_purchase_orders',
        'site_visits',
        'vendor_purchase_requests',
    ] as $blockingTable) {
        expect($controller)->toContain("'{$blockingTable}'");
    }

    expect($controller)
        ->toContain("DB::table('dynamic_forms')")
        ->toContain("'procurement_id' => null")
        ->toContain('DELETE_SETUP_TABLES')
        ->toContain("DB::table('procurement_audit_logs')")
        ->toContain("DB::table('system_audit_logs')")
        ->toContain("->where('is_launched', true)");

    expect($model)
        ->toContain('static::deleted(function (Procurement $procurement)')
        ->toContain('DB::afterCommit(function () use ($procurementId, $coverImagePath): void')
        ->toContain('if (! $localFilesDeleted || ! $coverImageDeleted)')
        ->not->toContain('static::deleting(function (Procurement $procurement)');
});
