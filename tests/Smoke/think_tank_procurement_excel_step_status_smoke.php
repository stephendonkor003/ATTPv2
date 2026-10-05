<?php

declare(strict_types=1);

use App\Models\ThinkTankProcurementDocument;
use App\Models\ThinkTankProcurementItem;
use App\Models\ThinkTankProcurementPlan;
use App\Models\User;
use App\Services\ThinkTankProcurementSpreadsheetMigrationService;
use App\Services\ThinkTankProcurementWorkflowService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$application = require dirname(__DIR__, 2).'/bootstrap/app.php';
$application->make(Kernel::class)->bootstrap();

$ensure = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$migrationKey = ThinkTankProcurementSpreadsheetMigrationService::MIGRATION_KEY;
$actor = User::query()->where('user_type', 'admin')->firstOrFail();
$workflow = app(ThinkTankProcurementWorkflowService::class);
$migration = app(ThinkTankProcurementSpreadsheetMigrationService::class);

DB::beginTransaction();

try {
    $allTerminalPlan = ThinkTankProcurementPlan::query()
        ->where('status', ThinkTankProcurementPlan::STATUS_DRAFT)
        ->whereHas('items', fn ($items) => $items
            ->where('source_payload->migration->key', $migrationKey))
        ->whereDoesntHave('items', fn ($items) => $items->whereNotIn('status', [
            ThinkTankProcurementItem::STATUS_NO_OBJECTION,
            ThinkTankProcurementItem::STATUS_PUBLISHED,
        ]))
        ->firstOrFail();
    $terminalItemIds = $allTerminalPlan->items()->pluck('id');
    $workflow->submit($allTerminalPlan, $actor);
    $ensure($allTerminalPlan->fresh()->status === ThinkTankProcurementPlan::STATUS_SUBMITTED, 'An all-Cleared imported plan could not be submitted.');
    $ensure(
        ThinkTankProcurementItem::query()->whereIn('id', $terminalItemIds)->where('status', '<>', ThinkTankProcurementItem::STATUS_NO_OBJECTION)->doesntExist(),
        'Submitting an all-Cleared plan changed a terminal item status.',
    );
    $workflow->decidePlan($allTerminalPlan->fresh(), $actor, 'approve', null);
    $ensure($allTerminalPlan->fresh()->status === ThinkTankProcurementPlan::STATUS_APPROVED, 'An all-Cleared imported plan could not be approved.');

    $mixedPlan = ThinkTankProcurementPlan::query()
        ->where('status', ThinkTankProcurementPlan::STATUS_DRAFT)
        ->whereHas('items', fn ($items) => $items->where('status', ThinkTankProcurementItem::STATUS_DRAFT))
        ->whereHas('items', fn ($items) => $items->where('status', ThinkTankProcurementItem::STATUS_NO_OBJECTION))
        ->firstOrFail();
    $activeItems = $mixedPlan->items()->whereNotIn('status', [
        ThinkTankProcurementItem::STATUS_NO_OBJECTION,
        ThinkTankProcurementItem::STATUS_PUBLISHED,
    ])->get();
    foreach ($activeItems as $activeItem) {
        ThinkTankProcurementDocument::query()->create([
            'item_id' => $activeItem->id,
            'document_type' => 'tor',
            'document_name' => 'Transactional smoke TOR',
            'original_name' => 'transactional-smoke-tor.pdf',
            'file_path' => 'transactional-smoke/not-persisted.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1,
            'uploaded_by' => $actor->id,
        ]);
    }
    $terminalBeforeSubmit = $mixedPlan->items()->where('status', ThinkTankProcurementItem::STATUS_NO_OBJECTION)->pluck('id');
    $workflow->submit($mixedPlan, $actor);
    $ensure($mixedPlan->fresh()->status === ThinkTankProcurementPlan::STATUS_SUBMITTED, 'A mixed imported plan could not be submitted.');
    $ensure(
        ThinkTankProcurementItem::query()->whereIn('id', $terminalBeforeSubmit)->where('status', '<>', ThinkTankProcurementItem::STATUS_NO_OBJECTION)->doesntExist(),
        'Mixed-plan submission changed an imported Cleared item.',
    );
    $ensure(
        ThinkTankProcurementItem::query()->whereIn('id', $activeItems->pluck('id'))->where('status', '<>', ThinkTankProcurementItem::STATUS_SUBMITTED)->doesntExist(),
        'Mixed-plan submission did not submit every active item.',
    );

    $returnedPlan = ThinkTankProcurementPlan::query()
        ->where('status', ThinkTankProcurementPlan::STATUS_DRAFT)
        ->whereHas('items', fn ($items) => $items->where('status', ThinkTankProcurementItem::STATUS_DRAFT))
        ->firstOrFail();
    $returnedPlan->forceFill([
        'status' => ThinkTankProcurementPlan::STATUS_APPROVED,
        'review_notes' => 'Prior approval note.',
        'approved_at' => now(),
    ])->save();
    $returnedItem = $returnedPlan->items()->where('status', ThinkTankProcurementItem::STATUS_DRAFT)->firstOrFail();
    $returnedItem->forceFill(['status' => ThinkTankProcurementItem::STATUS_APPROVED])->save();
    $returnedComment = 'STEP returned this activity for correction in the transactional smoke test.';
    $returnedItem = $workflow->syncExternalStepStatus(
        $returnedItem,
        $actor,
        'returned',
        $returnedComment,
        (int) $returnedItem->portal_lock_version,
    );
    $returnedPlan = $returnedPlan->fresh();
    $returnedItem->setRelation('plan', $returnedPlan);
    $ensure($returnedPlan->status === ThinkTankProcurementPlan::STATUS_REVISION_REQUESTED, 'STEP Returned did not reopen the approved plan.');
    $ensure($returnedPlan->approved_at === null && $returnedPlan->rejected_at === null, 'STEP Returned left a mutually exclusive plan decision timestamp behind.');
    $ensure($returnedPlan->review_notes === $returnedComment, 'STEP Returned did not synchronize the plan review note.');
    $ensure($returnedItem->status === ThinkTankProcurementItem::STATUS_REVISION_REQUESTED, 'STEP Returned did not update the item workflow status.');
    $ensure($returnedItem->currentStepActivityStatus() === ThinkTankProcurementItem::STEP_STATUS_RETURNED, 'Current STEP Returned status was not persisted.');
    $ensure($returnedItem->review_reason === $returnedComment, 'The current STEP return instruction was not exposed as the item review reason.');
    $ensure($returnedItem->isEditable(), 'A STEP Returned item was not editable after its plan reopened.');

    $currentStepMigration = require dirname(__DIR__, 2).'/database/migrations/2026_10_04_000002_add_current_step_status_to_think_tank_procurement_items.php';
    $currentStepMigration->up();
    $ensure(
        $returnedItem->fresh()->currentStepActivityStatus() === ThinkTankProcurementItem::STEP_STATUS_RETURNED,
        'Reapplying the current STEP-status migration overwrote a staff-recorded status.',
    );

    $evidenceItem = ThinkTankProcurementItem::query()
        ->where('source_payload->migration->key', $migrationKey)
        ->where('status', ThinkTankProcurementItem::STATUS_NO_OBJECTION)
        ->whereNull('no_objection_date')
        ->whereHas('plan', fn ($plans) => $plans->where('status', ThinkTankProcurementPlan::STATUS_DRAFT))
        ->firstOrFail();
    $evidenceSource = $evidenceItem->source_payload;
    $evidenceLock = (int) $evidenceItem->portal_lock_version;
    $evidenceItem = $workflow->recordNoObjection($evidenceItem, $actor, [
        'step_reference' => 'STEP-SMOKE-NOT-PERSISTED',
        'no_objection_reference' => 'WB-SMOKE-NOT-PERSISTED',
        'no_objection_date' => now()->toDateString(),
        'no_objection_notes' => 'Transactional supplemental evidence test.',
    ]);
    $ensure($evidenceItem->plan->status === ThinkTankProcurementPlan::STATUS_DRAFT, 'Supplemental evidence unexpectedly rewrote the imported plan status.');
    $ensure($evidenceItem->hasCompleteNoObjectionEvidence(), 'Supplemental evidence was not recorded on an imported Cleared item.');
    $ensure($evidenceItem->isReadyToExecute(), 'A STEP Cleared item stopped being ready after supplemental evidence.');
    $ensure($evidenceItem->portal_lock_version === $evidenceLock + 1, 'Supplemental evidence did not advance the optimistic lock.');
    $ensure($evidenceItem->source_payload === $evidenceSource, 'Supplemental evidence changed immutable imported source data.');

    $syncItem = ThinkTankProcurementItem::query()
        ->where('source_payload->migration->key', $migrationKey)
        ->where('status', ThinkTankProcurementItem::STATUS_DRAFT)
        ->whereHas('plan', fn ($plans) => $plans->where('status', ThinkTankProcurementPlan::STATUS_DRAFT))
        ->whereDoesntHave('events')
        ->firstOrFail();
    $syncSource = $syncItem->source_payload;
    $syncImportedStatus = $syncItem->importedActivityStatus();
    $syncItem = $workflow->syncExternalStepStatus(
        $syncItem,
        $actor,
        'cleared',
        'STEP confirmed World Bank no-objection in the transactional smoke test.',
        (int) $syncItem->portal_lock_version,
    );
    $syncItem = $workflow->syncExternalStepStatus(
        $syncItem,
        $actor,
        null,
        'Follow-up append-only STEP comment in the transactional smoke test.',
        (int) $syncItem->portal_lock_version,
    );
    $syncLock = (int) $syncItem->portal_lock_version;
    $syncEvents = $syncItem->events()->count();

    $migration->seed(ThinkTankProcurementSpreadsheetMigrationService::BAND_AT_OR_ABOVE);
    $migration->seed(ThinkTankProcurementSpreadsheetMigrationService::BAND_BELOW);

    $syncItem = $syncItem->fresh();
    $ensure($syncItem->status === ThinkTankProcurementItem::STATUS_NO_OBJECTION, 'Seeder rerun erased a staff STEP Cleared update.');
    $ensure($syncItem->currentStepActivityStatus() === ThinkTankProcurementItem::STEP_STATUS_CLEARED, 'Seeder rerun erased the current STEP status.');
    $ensure($syncItem->importedActivityStatus() === $syncImportedStatus, 'Seeder rerun changed the imported Excel Activity Status.');
    $ensure($syncItem->source_payload === $syncSource, 'Seeder rerun changed the immutable imported row after a staff update.');
    $ensure($syncItem->portal_lock_version === $syncLock, 'Seeder rerun reset the optimistic lock.');
    $ensure($syncItem->events()->count() === $syncEvents, 'Seeder rerun erased append-only STEP comments.');

    echo "THINK_TANK_PROCUREMENT_EXCEL_STEP_STATUS_OK\n";
} finally {
    DB::rollBack();
}
