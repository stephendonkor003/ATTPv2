<?php

function procurementRegistryDeletionSources(): array
{
    $root = dirname(__DIR__, 2);

    return [
        'routes' => file_get_contents($root.'/routes/web.php'),
        'controller' => file_get_contents(
            $root.'/app/Http/Controllers/Procurement/ProcurementController.php'
        ),
        'model' => file_get_contents($root.'/app/Models/Procurement.php'),
        'migration' => file_get_contents(
            $root.'/database/migrations/2026_08_26_000001_add_soft_deletes_to_procurements.php'
        ),
        'view' => file_get_contents($root.'/resources/views/procurement/index.blade.php'),
        'dashboard' => file_get_contents($root.'/app/Http/Controllers/DashboardController.php'),
        'document' => file_get_contents($root.'/app/Models/ProcurementDocument.php'),
        'audit' => file_get_contents($root.'/app/Models/ProcurementAuditLog.php'),
        'audit_controller' => file_get_contents(
            $root.'/app/Http/Controllers/Procurement/ProcurementAuditController.php'
        ),
    ];
}

it('exposes an exact System Admin-only procurement registry deletion action', function () {
    $sources = procurementRegistryDeletionSources();

    expect($sources['routes'])
        ->toContain("Route::delete('/{procurement}', [ProcurementController::class, 'destroy'])")
        ->toContain("->name('destroy')")
        ->and($sources['controller'])
        ->toContain('public function destroy(Request $request, Procurement $procurement)')
        ->toContain('$request->user()?->isAdmin()')
        ->toContain('Only System Admin users can delete procurements.')
        ->not->toContain('$request->user()?->isSuperAdmin()')
        ->and($sources['view'])
        ->toContain('@if (auth()->user()?->isAdmin())')
        ->toContain("route('procurements.destroy', \$p)")
        ->toContain("@method('DELETE')");
});

it('soft deletes procurements at any status without detaching or deleting dependencies', function () {
    $controller = procurementRegistryDeletionSources()['controller'];

    expect($controller)
        ->toContain('DB::transaction(function () use ($procurement): bool')
        ->toContain('->lockForUpdate()')
        ->toContain("'deleted_by' => auth()->id()")
        ->toContain('ProcurementAuditLog::create([')
        ->toContain("'action' => 'Soft deleted procurement'")
        ->toContain("'procurement_id' => \$lockedProcurement->getKey()")
        ->toContain("'status' => \$lockedProcurement->status")
        ->toContain('return (bool) $lockedProcurement->delete();')
        ->toContain('Procurement removed from the active registry successfully.')
        ->not->toContain('DELETE_BLOCKING_RELATIONS')
        ->not->toContain('DELETE_SETUP_TABLES')
        ->not->toContain('procurementDeletionDependency(')
        ->not->toContain('procurementHasPublicationHistory(')
        ->not->toContain("\$lockedProcurement->status !== 'draft'")
        ->not->toContain("DB::table('dynamic_forms')")
        ->not->toContain("'procurement_id' => null")
        ->not->toContain('forceDelete(');
});

it('defines the soft-delete schema and preserves files until a force delete', function () {
    $sources = procurementRegistryDeletionSources();
    $migration = $sources['migration'];
    $model = $sources['model'];

    expect($migration)
        ->toContain("Schema::table('procurements'")
        ->toContain("\$table->foreignUuid('deleted_by')")
        ->toContain("->constrained('users')")
        ->toContain('->nullOnDelete()')
        ->toContain('$table->softDeletes();')
        ->toContain("\$table->index('deleted_at')")
        ->toContain("\$table->dropIndex(['deleted_at'])")
        ->toContain('$table->dropSoftDeletes();')
        ->toContain("\$table->dropConstrainedForeignId('deleted_by')")
        ->and($model)
        ->toContain('use Illuminate\\Database\\Eloquent\\SoftDeletes;')
        ->toContain('use SoftDeletes;')
        ->toContain("'deleted_by'")
        ->toContain("'deleted_at' => 'datetime'")
        ->toContain("return \$this->belongsTo(User::class, 'deleted_by');")
        ->toContain('static::forceDeleted(function (Procurement $procurement)')
        ->toContain('DB::afterCommit(function () use ($procurementId, $coverImagePath): void')
        ->toContain("->deleteDirectory(\"procurements/{\$procurementId}\")")
        ->not->toContain('static::deleted(function (Procurement $procurement)')
        ->and(substr_count($model, "self::withTrashed()->where('slug', \$slug)"))->toBe(2);

    expect($sources['dashboard'])
        ->toContain("DB::table('procurements')->whereNull('deleted_at')->where('status', 'published')->count()");
});

it('retains linked-record access and accurately explains the registry action', function () {
    $sources = procurementRegistryDeletionSources();

    expect($sources['document'])
        ->toContain('return $this->belongsTo(Procurement::class)->withTrashed();')
        ->and($sources['audit'])
        ->toContain("'created_at' => 'datetime'")
        ->toContain('return $this->belongsTo(Procurement::class)->withTrashed();')
        ->toContain('return $this->belongsTo(User::class);')
        ->and($sources['audit_controller'])
        ->toContain("->with(['procurement', 'user'])")
        ->and($sources['view'])
        ->toContain('Move this procurement to deleted records?')
        ->toContain('linked records and files are retained')
        ->toContain('title="Soft delete procurement"')
        ->not->toContain('This cannot be undone.')
        ->not->toContain('Permanently delete this procurement?');
});
