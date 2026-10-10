<?php

use App\Http\Controllers\Procurement\ProcurementDisbursementController;
use App\Http\Controllers\Procurement\ProcurementPurchaseOrderController;
use App\Models\ConsortiumThinkTank;
use App\Models\Procurement;
use App\Models\ProcurementDisbursement;
use App\Models\ProcurementPurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\ThinkTankBudgetLine;
use App\Models\ThinkTankProcurementItem;
use App\Models\ThinkTankProcurementPlan;
use App\Services\ProcurementSubmissionIdempotencyService;
use App\Services\ThinkTankProcurementBudgetGuard;
use App\Support\ExactMoney;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

function bootThinkTankProcurementBudgetGuardApplication(): bool
{
    if (Container::getInstance()->bound(Kernel::class)) {
        return false;
    }

    $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();

    return true;
}

$thinkTankBudgetGuardBootedHere = false;
beforeEach(function () use (&$thinkTankBudgetGuardBootedHere): void {
    $thinkTankBudgetGuardBootedHere = bootThinkTankProcurementBudgetGuardApplication();
});
afterEach(function () use (&$thinkTankBudgetGuardBootedHere): void {
    if ($thinkTankBudgetGuardBootedHere) {
        restore_error_handler();
        restore_exception_handler();
    }
});

function invokeThinkTankBudgetGuard(string $method, mixed ...$arguments): mixed
{
    bootThinkTankProcurementBudgetGuardApplication();
    $reflection = new ReflectionMethod(ThinkTankProcurementBudgetGuard::class, $method);

    return $reflection->invoke(new ThinkTankProcurementBudgetGuard, ...$arguments);
}

/** @return array{member: ConsortiumThinkTank, plan: ThinkTankProcurementPlan, item: ThinkTankProcurementItem, line: ThinkTankBudgetLine, procurement: Procurement} */
function thinkTankBudgetScenario(array $lineOverrides = [], array $itemOverrides = []): array
{
    bootThinkTankProcurementBudgetGuardApplication();
    $member = (new ConsortiumThinkTank)->forceFill([
        'id' => '10000000-0000-4000-8000-000000000001',
        'consortium_id' => '20000000-0000-4000-8000-000000000001',
        'status' => 'active',
    ]);
    $plan = (new ThinkTankProcurementPlan)->forceFill([
        'id' => '30000000-0000-4000-8000-000000000001',
        'consortium_id' => $member->consortium_id,
        'think_tank_member_id' => $member->id,
        'fiscal_year' => '2026',
        'currency' => 'USD',
    ]);
    $item = (new ThinkTankProcurementItem)->forceFill([
        'id' => '40000000-0000-4000-8000-000000000001',
        'plan_id' => $plan->id,
        'estimated_amount' => '80.00',
        'currency' => 'USD',
        ...$itemOverrides,
    ]);
    $line = (new ThinkTankBudgetLine)->forceFill([
        'id' => '50000000-0000-4000-8000-000000000001',
        'consortium_id' => $member->consortium_id,
        'think_tank_member_id' => $member->id,
        'procurement_item_id' => $item->id,
        'fiscal_year' => '2026',
        'currency' => 'USD',
        'amount' => '100.00',
        'status' => ThinkTankBudgetLine::STATUS_ACTIVE,
        ...$lineOverrides,
    ]);
    $procurement = (new Procurement)->forceFill([
        'id' => '60000000-0000-4000-8000-000000000001',
        'procurement_owner_type' => 'think_tank',
        'consortium_id' => $member->consortium_id,
        'think_tank_member_id' => $member->id,
        'think_tank_procurement_plan_id' => $plan->id,
        'estimated_budget' => '80.00',
    ]);

    return compact('member', 'plan', 'item', 'line', 'procurement');
}

it('requires exactly one tenant budget line before procurement execution', function () {
    $scenario = thinkTankBudgetScenario();

    expect(fn () => invokeThinkTankBudgetGuard(
        'budgetLineForItem',
        collect(),
        $scenario['item'],
    ))->toThrow(ValidationException::class, 'exactly one tenant-owned internal budget line');
});

it('rejects inactive and tenant-mismatched execution lines', function (array $overrides, string $message) {
    $scenario = thinkTankBudgetScenario($overrides);

    expect(fn () => invokeThinkTankBudgetGuard(
        'assertCoverage',
        $scenario['member'],
        collect([$scenario['line']]),
        $scenario['line'],
        $scenario['item'],
        $scenario['plan'],
        $scenario['procurement'],
        collect(),
        collect(),
    ))->toThrow(ValidationException::class, $message);
})->with([
    'inactive' => [['status' => ThinkTankBudgetLine::STATUS_DRAFT], 'must be active'],
    'another tenant' => [['think_tank_member_id' => '10000000-0000-4000-8000-000000000099'], 'outside this Think Tank tenant'],
    'wrong fiscal year' => [['fiscal_year' => '2025'], 'fiscal year must exactly match'],
]);

it('enforces leaf-only execution so parent and child lines cannot double allocate', function () {
    $scenario = thinkTankBudgetScenario();
    $child = (new ThinkTankBudgetLine)->forceFill([
        'id' => '50000000-0000-4000-8000-000000000002',
        'consortium_id' => $scenario['member']->consortium_id,
        'think_tank_member_id' => $scenario['member']->id,
        'parent_id' => $scenario['line']->id,
        'fiscal_year' => '2026',
        'currency' => 'USD',
        'amount' => '25.00',
        'status' => ThinkTankBudgetLine::STATUS_ACTIVE,
    ]);

    expect(fn () => invokeThinkTankBudgetGuard(
        'assertCoverage',
        $scenario['member'],
        collect([$scenario['line'], $child]),
        $scenario['line'],
        $scenario['item'],
        $scenario['plan'],
        $scenario['procurement'],
        collect(),
        collect(),
    ))->toThrow(ValidationException::class, 'leaf budget line');
});

it('rejects a purchase order that exceeds its line or aggregate commitment cap', function () {
    $scenario = thinkTankBudgetScenario();

    expect(fn () => invokeThinkTankBudgetGuard(
        'assertPurchaseOrderProjection',
        $scenario['line'],
        $scenario['item'],
        $scenario['procurement'],
        collect(),
        collect(),
        10_001,
        'issued',
    ))->toThrow(ValidationException::class, 'exceeds the linked internal budget line');

    $existing = (new ProcurementPurchaseOrder)->forceFill([
        'id' => '70000000-0000-4000-8000-000000000001',
        'amount' => '50.00',
        'status' => 'issued',
    ]);
    expect(fn () => invokeThinkTankBudgetGuard(
        'assertPurchaseOrderProjection',
        $scenario['line'],
        $scenario['item'],
        $scenario['procurement'],
        collect([$existing]),
        collect(),
        5_001,
        'issued',
    ))->toThrow(ValidationException::class, 'commitments exceed');
});

it('does not let an existing purchase order be reduced below its payments', function () {
    $scenario = thinkTankBudgetScenario(['amount' => '200.00']);
    $order = (new ProcurementPurchaseOrder)->forceFill([
        'id' => '70000000-0000-4000-8000-000000000001',
        'amount' => '100.00',
        'status' => 'issued',
    ]);
    $payment = (new ProcurementDisbursement)->forceFill([
        'id' => '80000000-0000-4000-8000-000000000001',
        'purchase_order_id' => $order->id,
        'amount' => '80.00',
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    expect(fn () => invokeThinkTankBudgetGuard(
        'assertPurchaseOrderProjection',
        $scenario['line'],
        $scenario['item'],
        $scenario['procurement'],
        collect([$order]),
        collect([$payment]),
        7_000,
        'issued',
        $order,
    ))->toThrow(ValidationException::class, 'cannot be reduced below its recognized payments');
});

it('rejects a commitment year outside the linked budget fiscal year', function () {
    $scenario = thinkTankBudgetScenario(['fiscal_year' => '2026/27']);

    expect(fn () => invokeThinkTankBudgetGuard(
        'assertCommitmentFiscalYear',
        $scenario['line'],
        2027,
    ))->toThrow(ValidationException::class, 'commitment year must match');

    expect(invokeThinkTankBudgetGuard(
        'assertCommitmentFiscalYear',
        $scenario['line'],
        2026,
    ))->toBeNull();
});

it('blocks cross-procurement moves and reclassification after financial history', function () {
    $scenario = thinkTankBudgetScenario();
    $differentOrder = (new ProcurementPurchaseOrder)->forceFill([
        'id' => '70000000-0000-4000-8000-000000000001',
        'procurement_id' => '60000000-0000-4000-8000-000000000099',
    ]);

    expect(fn () => invokeThinkTankBudgetGuard(
        'assertExistingPurchaseOrderIdentity',
        $differentOrder,
        $scenario['procurement'],
        collect(),
    ))->toThrow(ValidationException::class, 'cannot be moved');

    $unclassifiedOrder = (new ProcurementPurchaseOrder)->forceFill([
        'id' => '70000000-0000-4000-8000-000000000002',
    ]);
    $payment = (new ProcurementDisbursement)->forceFill([
        'id' => '80000000-0000-4000-8000-000000000001',
        'purchase_order_id' => $unclassifiedOrder->id,
        'amount' => '1.00',
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    expect(fn () => invokeThinkTankBudgetGuard(
        'assertExistingPurchaseOrderIdentity',
        $unclassifiedOrder,
        $scenario['procurement'],
        collect([$payment]),
    ))->toThrow(ValidationException::class, 'financial history cannot be reclassified');
});

it('rejects projected payments above either the purchase order or budget line', function (string $lineAmount, string $orderAmount, string $message) {
    $scenario = thinkTankBudgetScenario(['amount' => $lineAmount], ['estimated_amount' => '50.00']);
    $order = (new ProcurementPurchaseOrder)->forceFill([
        'id' => '70000000-0000-4000-8000-000000000001',
        'amount' => $orderAmount,
        'status' => 'issued',
    ]);
    $row = [
        'amount' => '90.00',
        'status' => 'paid',
        'paid_at' => now()->toDateString(),
    ];

    expect(fn () => invokeThinkTankBudgetGuard(
        'assertPaymentProjection',
        $scenario['line'],
        $order,
        collect([$order]),
        collect(),
        [$row],
    ))->toThrow(ValidationException::class, $message);
})->with([
    'purchase order cap' => ['1000.00', '80.00', 'locked purchase-order amount'],
    'budget line cap' => ['80.00', '100.00', 'linked internal budget line'],
]);

it('counts legacy future-dated paid rows conservatively against budget capacity', function () {
    $scenario = thinkTankBudgetScenario(['amount' => '100.00'], ['estimated_amount' => '50.00']);
    $order = (new ProcurementPurchaseOrder)->forceFill([
        'id' => '70000000-0000-4000-8000-000000000001',
        'amount' => '100.00',
        'status' => 'issued',
    ]);
    $futurePayment = (new ProcurementDisbursement)->forceFill([
        'id' => '80000000-0000-4000-8000-000000000001',
        'purchase_order_id' => $order->id,
        'amount' => '80.00',
        'status' => 'paid',
        'paid_at' => now()->addDay(),
    ]);

    expect(fn () => invokeThinkTankBudgetGuard(
        'assertPaymentProjection',
        $scenario['line'],
        $order,
        collect([$order]),
        collect([$futurePayment]),
        [[
            'amount' => '30.00',
            'status' => 'paid',
            'paid_at' => now()->toDateString(),
        ]],
    ))->toThrow(ValidationException::class, 'linked internal budget line');
});

it('excludes future-dated payments from current paid summaries', function () {
    $item = (new PurchaseRequestItem)->forceFill([
        'id' => '90000000-0000-4000-8000-000000000001',
        'amount' => '100.00',
    ]);
    $request = (new PurchaseRequest)->forceFill([
        'id' => '91000000-0000-4000-8000-000000000001',
    ]);
    $request->setRelation('items', collect([$item]));

    $paid = (new ProcurementDisbursement)->forceFill([
        'purchase_request_item_id' => $item->id,
        'amount' => '25.00',
        'status' => 'paid',
        'paid_at' => now()->subDay(),
    ]);
    $futurePaid = (new ProcurementDisbursement)->forceFill([
        'purchase_request_item_id' => $item->id,
        'amount' => '60.00',
        'status' => 'paid',
        'paid_at' => now()->addDay(),
    ]);
    $order = (new ProcurementPurchaseOrder)->forceFill(['amount' => '100.00']);
    $order->setRelation('purchaseRequest', $request);
    $order->setRelation('budgetCommitment', null);
    $order->setRelation('lineItemEvidence', collect());
    $order->setRelation('disbursements', collect([$paid, $futurePaid]));

    expect($order->actualPaidAmount())->toBe(25.0)
        ->and($order->lineItemSummary()['paid_amount'])->toBe(25.0)
        ->and($order->lineItemSummary()['pending_amount'])->toBe(75.0);
});

it('makes recognized payment rows immutable outside the reversal workflow', function () {
    $guard = new ThinkTankProcurementBudgetGuard;
    $controller = new ProcurementDisbursementController(
        $guard,
        new ProcurementSubmissionIdempotencyService,
    );
    $method = new ReflectionMethod($controller, 'assertRecognizedPaymentMutationsUseReversal');
    $payment = (new ProcurementDisbursement)->forceFill([
        'id' => '80000000-0000-4000-8000-000000000001',
        'reference_no' => 'PAY-001',
        'purchase_request_item_id' => '90000000-0000-4000-8000-000000000001',
        'deliverable_id' => null,
        'amount' => '80.00',
        'payment_method' => 'Bank Transfer',
        'transfer_reference' => 'TX-001',
        'status' => 'paid',
        'paid_at' => now()->startOfDay(),
        'notes' => 'Settled',
    ]);
    $row = [
        'index' => 0,
        'id' => $payment->id,
        'reference_no' => 'PAY-001',
        'purchase_request_item_id' => $payment->purchase_request_item_id,
        'deliverable_id' => null,
        'amount' => '80.00',
        'payment_method' => 'Bank Transfer',
        'transfer_reference' => 'TX-001',
        'status' => 'reversed',
        'paid_at' => now()->toDateString(),
        'notes' => 'Settled',
    ];

    expect(fn () => $method->invoke($controller, collect([$payment]), [$row], collect()))
        ->toThrow(ValidationException::class, 'recognized, voided, or reversed payment is immutable');
    expect(fn () => $method->invoke($controller, collect([$payment]), [], collect([$payment->id])))
        ->toThrow(ValidationException::class, 'cannot be removed');

    $payment->status = 'reversed';
    $row['status'] = 'paid';
    expect(fn () => $method->invoke($controller, collect([$payment]), [$row], collect()))
        ->toThrow(ValidationException::class, 'recognized, voided, or reversed payment is immutable');
});

it('keeps reversed payment history attached to its purchase order', function () {
    $guard = new ThinkTankProcurementBudgetGuard;
    $method = new ReflectionMethod($guard, 'disbursementPreventsHardDelete');
    $reversed = (new ProcurementDisbursement)->forceFill([
        'status' => 'reversed',
        'paid_at' => now()->subDay(),
    ]);
    $futurePaid = (new ProcurementDisbursement)->forceFill([
        'status' => 'paid',
        'paid_at' => now()->addDay(),
    ]);
    $pending = (new ProcurementDisbursement)->forceFill([
        'status' => 'pending',
        'paid_at' => now(),
    ]);

    expect($method->invoke($guard, $reversed))->toBeTrue()
        ->and($method->invoke($guard, $futurePaid))->toBeTrue()
        ->and($method->invoke($guard, $pending))->toBeFalse();
});

it('preserves contractual commitment status when the final payment is reversed', function () {
    $controller = new ProcurementDisbursementController(
        new ThinkTankProcurementBudgetGuard,
        new ProcurementSubmissionIdempotencyService,
    );
    $method = new ReflectionMethod($controller, 'purchaseOrderStatusAfterPaymentSync');

    expect($method->invoke($controller, 'partial_paid', 0.0, 100.0))->toBe('issued')
        ->and($method->invoke($controller, 'paid', 0.0, 100.0))->toBe('issued')
        ->and($method->invoke($controller, 'issued', 0.0, 100.0))->toBe('issued')
        ->and($method->invoke($controller, 'closed', 0.0, 100.0))->toBe('closed')
        ->and($method->invoke($controller, 'draft', 0.0, 100.0))->toBe('draft');
});

it('enforces the generic purchase-order paid floor under the locked boundary', function () {
    $controller = new ProcurementPurchaseOrderController(
        new ThinkTankProcurementBudgetGuard,
        new ProcurementSubmissionIdempotencyService,
    );
    $method = new ReflectionMethod($controller, 'assertPurchaseOrderPaymentBoundary');
    $order = (new ProcurementPurchaseOrder)->forceFill(['amount' => '100.00', 'status' => 'issued']);
    $payment = (new ProcurementDisbursement)->forceFill([
        'amount' => '80.00',
        'status' => 'paid',
        'paid_at' => now()->addDay(),
    ]);

    expect(fn () => $method->invoke($controller, $order, collect([$payment]), '70.00', 'issued'))
        ->toThrow(ValidationException::class, 'cannot be reduced below its recorded payments');
    expect(fn () => $method->invoke($controller, $order, collect([$payment]), '100.00', 'cancelled'))
        ->toThrow(ValidationException::class, 'cannot be moved to draft or cancelled');
});

it('compares large monetary boundaries and the final cent without floating point loss', function () {
    $controller = new ProcurementPurchaseOrderController(
        new ThinkTankProcurementBudgetGuard,
        new ProcurementSubmissionIdempotencyService,
    );
    $method = new ReflectionMethod($controller, 'assertPurchaseOrderPaymentBoundary');
    $order = (new ProcurementPurchaseOrder)->forceFill([
        'amount' => ExactMoney::DATABASE_MAX,
        'status' => 'issued',
    ]);
    $payment = (new ProcurementDisbursement)->forceFill([
        'amount' => ExactMoney::DATABASE_MAX,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    expect(ExactMoney::cents(ExactMoney::DATABASE_MAX))->toBe(999999999999999)
        ->and(ExactMoney::cents('0.01'))->toBe(1)
        ->and($method->invoke($controller, $order, collect([$payment]), ExactMoney::DATABASE_MAX, 'issued'))->toBeNull();

    $disbursementController = new ProcurementDisbursementController(
        new ThinkTankProcurementBudgetGuard,
        new ProcurementSubmissionIdempotencyService,
    );
    $paymentCents = new ReflectionMethod($disbursementController, 'paymentAmountCents');
    expect($paymentCents->invoke($disbursementController, ExactMoney::DATABASE_MAX))->toBe(999999999999999);

    expect(fn () => $method->invoke($controller, $order, collect([$payment]), '9999999999999.98', 'issued'))
        ->toThrow(ValidationException::class, 'cannot be reduced below its recorded payments');
    expect(fn () => ExactMoney::cents('999999999999999999999999999999.99'))
        ->toThrow(InvalidArgumentException::class, 'outside the supported range');
    expect(fn () => ExactMoney::cents('0.001'))
        ->toThrow(InvalidArgumentException::class, 'no more than two decimal places');
});

it('wires procurement inference tenant columns locks and future-date validation at controller boundaries', function () {
    $purchaseOrders = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Procurement/ProcurementPurchaseOrderController.php');
    $invoices = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Procurement/ProcurementInvoiceController.php');
    $disbursements = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Procurement/ProcurementDisbursementController.php');
    $executions = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Api/V1/ThinkTank/ProcurementExecutionController.php');
    $budgetGuard = file_get_contents(dirname(__DIR__, 2).'/app/Services/ThinkTankProcurementBudgetGuard.php');
    $routes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');
    $commitments = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/BudgetCommitmentController.php');
    $purchaseRequests = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/PurchaseRequestController.php');

    expect($purchaseOrders)
        ->toContain('authoritativeProcurementForDeliverables')
        ->toContain('ProcurementDeliverable::withTrashed()')
        ->toContain("'consortium_id' => \$procurement?->consortium_id")
        ->toContain("'think_tank_member_id' => \$procurement?->think_tank_member_id")
        ->toContain('assertPurchaseOrderBoundary')
        ->toContain('lockCommitmentCapacity')
        ->toContain('locked commitment balance')
        ->toContain('lockHardDeleteBoundary')
        ->toContain('assertExistingThinkTankOwnershipPreserved')
        ->toContain('assertPurchaseOrderPaymentBoundary')
        ->toContain('remainingCommitmentCents')
        ->toContain('ExactMoney::cents')
        ->and($invoices)
        ->toContain('assertPurchaseOrderBoundary')
        ->toContain('The invoice amount or currency changed while its budget boundary was being checked')
        ->toContain('ExactMoney::normalize')
        ->toContain("'consortium_id' => \$lockedProcurement->consortium_id")
        ->toContain("'think_tank_member_id' => \$lockedProcurement->think_tank_member_id")
        ->and($disbursements)
        ->toContain("'before_or_equal:today'")
        ->toContain('assertRecognizedPaymentMutationsUseReversal')
        ->toContain('Secretariat-to-Think-Tank transfers cannot be reversed through procurement payments')
        ->toContain('lockPaymentBoundary')
        ->toContain('lockForUpdate()')
        ->toContain('purchaseOrderStatusAfterPaymentSync')
        ->and($executions)
        ->toContain('lockPlanningItem')
        ->toContain('lockExecution')
        ->toContain("->whereHas('budgetLine'")
        ->and($budgetGuard)
        ->toContain('historicalAwardCandidatesQuery()')
        ->toContain('assertCommitmentFiscalYear')
        ->and($routes)
        ->toContain("Route::post('/', [ProcurementDisbursementController::class, 'store'])")
        ->toContain("->middleware('permission:finance.purchase_orders.create')")
        ->toContain("->middleware('permission:finance.purchase_requests.approve')")
        ->and($commitments)->toContain('lockHardDeleteBoundary')
        ->and($purchaseRequests)->toContain('lockHardDeleteBoundary');
});
