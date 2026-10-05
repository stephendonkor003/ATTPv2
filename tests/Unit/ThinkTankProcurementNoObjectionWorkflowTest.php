<?php

use App\Jobs\SendThinkTankProcurementStatusNotification;
use App\Models\ThinkTankProcurementItem;
use App\Models\ThinkTankProcurementPlan;
use App\Services\ThinkTankProcurementApiService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;

it('maps the compatible statuses to the Secretariat and World Bank lifecycle', function (): void {
    $service = new ThinkTankProcurementApiService;
    $label = new ReflectionMethod($service, 'itemStatusLabel');

    expect($label->invoke($service, ThinkTankProcurementItem::STATUS_SUBMITTED))
        ->toBe('Sent to AUC-ATTP Secretariat')
        ->and($label->invoke($service, ThinkTankProcurementItem::STATUS_APPROVED))
        ->toBe('Pending World Bank no-objection')
        ->and($label->invoke($service, ThinkTankProcurementItem::STATUS_NO_OBJECTION))
        ->toBe('No-objection received — ready to execute');
});

it('treats an authoritative STEP Cleared row as ready while keeping formal evidence explicit', function (): void {
    $item = (new ThinkTankProcurementItem)->forceFill([
        'status' => ThinkTankProcurementItem::STATUS_NO_OBJECTION,
        'source_activity_status' => 'Cleared',
        'step_activity_status' => ThinkTankProcurementItem::STEP_STATUS_CLEARED,
        'source_payload' => ['source_statuses' => ['activity_status' => 'Cleared']],
        'no_objection_date' => null,
        'no_objection_reference' => null,
    ]);
    $item->setRelation('documents', new Collection);
    $item->setRelation('plan', (new ThinkTankProcurementPlan)->forceFill([
        'status' => ThinkTankProcurementPlan::STATUS_DRAFT,
    ]));

    expect($item->hasCompleteNoObjectionEvidence())->toBeFalse()
        ->and($item->isReadyToExecute())->toBeTrue()
        ->and($item->currentStepActivityStatus())->toBe('Cleared');

    $item->setRawAttributes(array_merge($item->getAttributes(), [
        'no_objection_date' => '2026-10-01',
        'no_objection_reference' => 'WB-NO-001',
    ]));

    expect($item->hasCompleteNoObjectionEvidence())->toBeTrue()
        ->and($item->isReadyToExecute())->toBeTrue()
        ->and($item->importedActivityStatus())->toBe('Cleared');
});

it('prefers an existing terminal workflow clearance over a stale imported STEP value', function (): void {
    $item = (new ThinkTankProcurementItem)->forceFill([
        'status' => ThinkTankProcurementItem::STATUS_NO_OBJECTION,
        'source_activity_status' => 'New',
        'step_activity_status' => null,
        'source_payload' => ['source_statuses' => ['activity_status' => 'New']],
    ]);

    expect($item->importedActivityStatus())->toBe('New')
        ->and($item->currentStepActivityStatus())->toBe('Cleared');
});

it('locks status transitions and stages one queued delivery per event recipient', function (): void {
    $root = dirname(__DIR__, 2);
    $workflow = file_get_contents($root.'/app/Services/ThinkTankProcurementWorkflowService.php');
    $delivery = file_get_contents($root.'/app/Services/ThinkTankProcurementStatusNotificationService.php');
    $job = file_get_contents($root.'/app/Jobs/SendThinkTankProcurementStatusNotification.php');
    $reconciler = file_get_contents($root.'/app/Console/Commands/ReconcileThinkTankProcurementStatusNotifications.php');
    $scheduler = file_get_contents($root.'/bootstrap/app.php');
    $migration = file_get_contents($root.'/database/migrations/2026_10_01_000002_create_think_tank_procurement_status_notifications.php');
    $interfaces = class_implements(SendThinkTankProcurementStatusNotification::class);

    expect(substr_count($workflow, 'lockForUpdate()'))->toBeGreaterThanOrEqual(7)
        ->and($workflow)->toContain("'item_submitted_to_secretariat'")
        ->toContain('ThinkTankProcurementStatusNotificationService::class')
        ->toContain('$fromStatus !== $toStatus')
        ->toContain("'notification_suppressed' => true")
        ->toContain("'notification_covered_by' => 'plan_submitted'")
        ->not->toContain('notifyMember(')
        ->not->toContain('Mail::to(')
        ->and($delivery)->toContain('DB::afterCommit')
        ->toContain('status transition is already committed')
        ->toContain('Str::lower(trim((string) $user->email))')
        ->toContain('hasActiveLoginBlock()')
        ->toContain('User::THINK_TANK_ACCESS_PROCUREMENT')
        ->toContain('recipientIsStillEligible')
        ->not->toContain('orWhereKey(')
        ->and($job)->toContain('public int $timeout = 90')
        ->toContain('public bool $failOnTimeout = true')
        ->toContain('recipientIsStillEligible($notification)')
        ->toContain("'recipient_no_longer_eligible'")
        ->toContain('ThinkTankProcurementStatusNotification::STATUS_SENDING')
        ->toContain('catch (TransportExceptionInterface $exception)')
        ->toContain('automatically sending the same event')
        ->and($reconciler)->toContain('--include-ambiguous')
        ->toContain('TransportExceptionInterface')
        ->and($scheduler)->toContain('think-tank:procurement-notifications:reconcile --limit=25')
        ->and($migration)->toContain("unique(['event_id', 'recipient_email']")
        ->and($interfaces)->toContain(ShouldQueue::class)
        ->toContain(ShouldBeUnique::class)
        ->toContain(ShouldBeEncrypted::class);
});

it('keeps supplemental World Bank evidence dated and attaches a recorded event PDF', function (): void {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents($root.'/app/Http/Controllers/AdminThinkTankProcurementController.php');
    $mail = file_get_contents($root.'/app/Mail/ThinkTankProcurementStatusMail.php');
    $pdf = file_get_contents($root.'/resources/views/emails/think-tank/procurement-status-pdf.blade.php');
    $api = file_get_contents($root.'/app/Services/ThinkTankProcurementApiService.php');

    expect($controller)->toContain('before_or_equal:today')
        ->toContain("'no_objection_reference' => 'nullable|string|max:255'")
        ->toContain("'no_objection_document' => 'nullable|file|mimes:pdf,doc,docx|max:20480'")
        ->toContain('lockForUpdate()')
        ->and($mail)->toContain('->attachData(')
        ->toContain('PdfPageNumbering::stamp')
        ->toContain('event->from_status')
        ->toContain('event->to_status')
        ->and($pdf)->not->toContain('file_path')
        ->not->toContain('recipient_email')
        ->and($api)->toContain("'isReadyToExecute'")
        ->toContain("'clearanceStatus'")
        ->toContain("'formalEvidenceComplete'")
        ->toContain("'currentStepActivityStatus'")
        ->toContain("'importedActivityStatus'")
        ->toContain("'readyToExecuteAt'")
        ->toContain("'readyToExecuteBy'");
});

it('keeps legacy singular TOR uploads while accepting bounded repeatable TOR files', function (): void {
    $controller = file_get_contents(
        dirname(__DIR__, 2).'/app/Http/Controllers/Api/V1/ThinkTank/ProcurementController.php'
    );

    expect($controller)->toContain("'tor' => ['nullable', 'file'")
        ->toContain("'tor_documents' => ['nullable', 'array', 'max:20']")
        ->toContain("'supporting_documents' => ['nullable', 'array', 'max:20']")
        ->toContain('$torCount + $supportingCount > self::MAX_DOCUMENTS_PER_REQUEST')
        ->toContain('MAX_DOCUMENT_BYTES_PER_REQUEST')
        ->toContain("\$request->file('tor_documents', [])");
});
