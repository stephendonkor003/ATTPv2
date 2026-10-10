<?php

use App\Services\ProcurementSubmissionIdempotencyService;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

it('builds stable actor-scoped fingerprints and rejects a changed replay payload', function () {
    $bootedHere = bootProcurementSubmissionIdempotencyApplication();
    $service = new ProcurementSubmissionIdempotencyService;

    try {
        $first = $service->fingerprint('purchase_order.create', 'actor-1', [
            'validated' => [
                'amount' => '100.00',
                'currency' => 'USD',
            ],
            'deliverable_ids' => ['deliverable-1', 'deliverable-2'],
        ]);
        $same = $service->fingerprint('purchase_order.create', 'actor-1', [
            'deliverable_ids' => ['deliverable-1', 'deliverable-2'],
            'validated' => [
                'currency' => 'USD',
                'amount' => '100.00',
            ],
        ]);
        $changed = $service->fingerprint('purchase_order.create', 'actor-1', [
            'validated' => [
                'amount' => '100.01',
                'currency' => 'USD',
            ],
            'deliverable_ids' => ['deliverable-1', 'deliverable-2'],
        ]);
        $otherActor = $service->fingerprint('purchase_order.create', 'actor-2', [
            'validated' => [
                'amount' => '100.00',
                'currency' => 'USD',
            ],
            'deliverable_ids' => ['deliverable-1', 'deliverable-2'],
        ]);

        expect($first)->toBe($same)
            ->and($changed)->not->toBe($first)
            ->and($otherActor)->not->toBe($first);

        expect(fn () => $service->assertReplayMatches($first, $same))->not->toThrow(ValidationException::class)
            ->and(fn () => $service->assertReplayMatches($first, $changed))
            ->toThrow(ValidationException::class, 'already used for different procurement details');
    } finally {
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('replays a full-capacity purchase order before recalculating available commitment or budget', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Procurement/ProcurementPurchaseOrderController.php');
    $store = procurementIdempotencyMethodSource($source, 'public function store(Request $request)', 'public function update(');

    $earlyReplay = strpos($store, '$earlyReplay = $this->purchaseOrderReplay(');
    $remainingCapacity = strpos($store, '$remainingCents = $this->remainingCommitmentCents(');
    $claim = strpos($store, '[$claim, $claimReplay] = $this->claimPurchaseOrderSubmission(');
    $budgetBoundary = strpos($store, '$this->thinkTankBudgetGuard->assertPurchaseOrderBoundary(');
    $commitmentBoundary = strpos($store, '$this->lockCommitmentCapacity(');

    expect($earlyReplay)->not->toBeFalse()
        ->and($remainingCapacity)->not->toBeFalse()
        ->and($claim)->not->toBeFalse()
        ->and($budgetBoundary)->not->toBeFalse()
        ->and($commitmentBoundary)->not->toBeFalse()
        ->and($earlyReplay)->toBeLessThan($remainingCapacity)
        ->and($claim)->toBeLessThan($budgetBoundary)
        ->and($claim)->toBeLessThan($commitmentBoundary)
        ->and($store)->toContain('}, 3);');
});

it('replays full purchase-order payment batches before locking or projecting payment capacity', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Procurement/ProcurementDisbursementController.php');
    $store = procurementIdempotencyMethodSource($source, 'private function persistDisbursement(', 'public function show(');
    $update = procurementIdempotencyMethodSource($source, 'public function update(', 'public function storeProcurementProcessing(');

    $storeReplay = strpos($store, '$existingBatch = $this->disbursementBatchReplay(');
    $storeProjection = strpos($store, '$paymentRows = $this->validatedPaymentRows(');
    $storeClaim = strpos($store, '[$batch, $batchReplay] = $this->claimDisbursementBatch(');
    $storeBoundary = strpos($store, '$this->thinkTankBudgetGuard->lockPaymentBoundary(');
    $updateClaim = strpos($update, '[$batch, $batchReplay] = $this->claimDisbursementBatch(');
    $updateBoundary = strpos($update, '$this->thinkTankBudgetGuard->lockPaymentBoundary(');

    expect($storeReplay)->not->toBeFalse()
        ->and($storeProjection)->not->toBeFalse()
        ->and($storeClaim)->not->toBeFalse()
        ->and($storeBoundary)->not->toBeFalse()
        ->and($updateClaim)->not->toBeFalse()
        ->and($updateBoundary)->not->toBeFalse()
        ->and($storeReplay)->toBeLessThan($storeProjection)
        ->and($storeClaim)->toBeLessThan($storeBoundary)
        ->and($updateClaim)->toBeLessThan($updateBoundary)
        ->and($store)->toContain('}, 3);')
        ->and($update)->toContain('}, 3);');
});

it('captures payment audit before-images under lock and preserves voided rows for durable replay', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Procurement/ProcurementDisbursementController.php');
    $update = procurementIdempotencyMethodSource($source, 'public function update(', 'public function storeProcurementProcessing(');

    $rowLock = strpos($update, '->lockForUpdate()');
    $beforeSnapshot = strpos($update, '$before = $editableDisbursements');
    $auditWrite = strpos($update, "'action' => 'Updated disbursement batch'");

    expect($rowLock)->not->toBeFalse()
        ->and($beforeSnapshot)->not->toBeFalse()
        ->and($auditWrite)->not->toBeFalse()
        ->and($rowLock)->toBeLessThan($beforeSnapshot)
        ->and($beforeSnapshot)->toBeLessThan($auditWrite)
        ->and($update)->toContain("'status' => 'void'")
        ->and($update)->toContain("'voided_disbursement_ids' => \$deleteIds->all()")
        ->and($update)->not->toContain('->get((string) $deleteId)?->delete()');
});

it('persists durable unique submission claims and stable form keys', function () {
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_10_05_000002_add_procurement_submission_idempotency.php');
    $purchaseOrderForm = file_get_contents(dirname(__DIR__, 2).'/resources/views/procurement/purchase-orders/create.blade.php');
    $paymentCreateForm = file_get_contents(dirname(__DIR__, 2).'/resources/views/procurement/disbursements/create.blade.php');
    $paymentEditForm = file_get_contents(dirname(__DIR__, 2).'/resources/views/procurement/disbursements/edit.blade.php');

    expect($migration)
        ->toContain("'submission_idempotency_key'")
        ->toContain("'submission_idempotency_fingerprint'")
        ->toContain("Schema::create('procurement_purchase_order_submission_claims'")
        ->toContain("Schema::create('procurement_disbursement_submission_batches'")
        ->toContain("->unique('proc_po_claim_idempotency_uq')")
        ->toContain("->unique('proc_disb_batch_idempotency_uq')")
        ->and($purchaseOrderForm)
        ->toContain('name="idempotency_key" value="{{ old(\'idempotency_key\', $idempotencyKey) }}"')
        ->and($paymentCreateForm)
        ->toContain('name="idempotency_key" value="{{ old(\'idempotency_key\', $idempotencyKey) }}"')
        ->and($paymentEditForm)
        ->toContain('name="idempotency_key" value="{{ old(\'idempotency_key\', $idempotencyKey) }}"');
});

function procurementIdempotencyMethodSource(string $source, string $startNeedle, string $endNeedle): string
{
    $start = strpos($source, $startNeedle);
    $end = $start === false ? false : strpos($source, $endNeedle, $start + strlen($startNeedle));

    if ($start === false || $end === false) {
        throw new RuntimeException("Could not isolate controller source between {$startNeedle} and {$endNeedle}.");
    }

    return substr($source, $start, $end - $start);
}

function bootProcurementSubmissionIdempotencyApplication(): bool
{
    if (Container::getInstance()->bound(Kernel::class)) {
        return false;
    }

    $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();

    return true;
}
