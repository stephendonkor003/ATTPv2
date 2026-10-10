<?php

use App\Exceptions\ThinkTankApiException;
use App\Http\Controllers\AdminThinkTankController;
use App\Http\Controllers\ConsortiumOperationsController;
use App\Models\ConsortiumDisbursementRequest;
use App\Models\ConsortiumFundAllocation;
use App\Models\ConsortiumThinkTank;
use App\Models\ProcurementDisbursement;
use App\Models\ProcurementPurchaseOrder;
use App\Models\Role;
use App\Models\ThinkTankBudgetLine;
use App\Models\ThinkTankProcurementItem;
use App\Models\User;
use App\Services\ThinkTank\ThinkTankApiAuditService;
use App\Services\ThinkTank\ThinkTankInvitationService;
use App\Services\ThinkTank\ThinkTankUserManagementService;
use App\Services\ThinkTankFinanceApiService;
use App\Services\ThinkTankFundingReceiptService;
use App\Services\ThinkTankFundingSourceService;
use App\Services\ThinkTankHistoricalFundingBackfillService;
use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function bootThinkTankFinanceApiApplication(): array
{
    if (Container::getInstance()->bound(Kernel::class)) {
        return [Container::getInstance(), false];
    }

    $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();

    return [$application, true];
}

function financeApiService(): ThinkTankFinanceApiService
{
    return new ThinkTankFinanceApiService(Mockery::mock(ThinkTankApiAuditService::class));
}

/** @return array{program: string, funding: string, project: string, activity: string, subActivity: string, commitment: string} */
function createThinkTankFundingSourceFixture(): array
{
    $schema = DB::connection()->getSchemaBuilder();
    $schema->create('myb_programs', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('program_id');
    });
    $schema->create('myb_program_fundings', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->uuid('program_id');
        $table->decimal('approved_amount', 15, 2);
        $table->string('currency', 3);
        $table->string('status');
        $table->timestamp('approved_at')->nullable();
    });
    $schema->create('myb_projects', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->uuid('program_id');
        $table->string('project_id');
    });
    $schema->create('myb_activities', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->uuid('project_id');
    });
    $schema->create('myb_sub_activities', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->uuid('activity_id');
        $table->string('name');
    });
    $schema->create('myb_budget_commitments', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->uuid('program_funding_id');
    });

    $ids = [
        'program' => '19000000-0000-4000-8000-000000000001',
        'funding' => '19000000-0000-4000-8000-000000000002',
        'project' => '19000000-0000-4000-8000-000000000003',
        'activity' => '19000000-0000-4000-8000-000000000004',
        'subActivity' => '19000000-0000-4000-8000-000000000005',
        'commitment' => '19000000-0000-4000-8000-000000000006',
    ];
    DB::table('myb_programs')->insert(['id' => $ids['program'], 'program_id' => 'PROG00001']);
    DB::table('myb_program_fundings')->insert([
        'id' => $ids['funding'],
        'program_id' => $ids['program'],
        'approved_amount' => '24500000.00',
        'currency' => 'USD',
        'status' => 'approved',
        'approved_at' => '2026-01-01 00:00:00',
    ]);
    DB::table('myb_projects')->insert([
        'id' => $ids['project'],
        'program_id' => $ids['program'],
        'project_id' => 'PROG00001-02',
    ]);
    DB::table('myb_activities')->insert(['id' => $ids['activity'], 'project_id' => $ids['project']]);
    DB::table('myb_sub_activities')->insert([
        'id' => $ids['subActivity'],
        'activity_id' => $ids['activity'],
        'name' => ThinkTankFundingSourceService::SUB_ACTIVITY_NAME,
    ]);
    DB::table('myb_budget_commitments')->insert([
        'id' => $ids['commitment'],
        'program_funding_id' => $ids['funding'],
    ]);

    return $ids;
}

it('keeps every finance route stateful tenant ready area and permission protected', function () {
    [$application, $bootedHere] = bootThinkTankFinanceApiApplication();

    try {
        /** @var Router $router */
        $router = $application->make(Router::class);
        $routes = collect($router->getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/think-tank/finance'));

        expect($routes)->toHaveCount(9);
        $routes->each(function ($route): void {
            expect($route->gatherMiddleware())
                ->toContain('think.tank.api.no-store')
                ->toContain('think.tank.api.stateful')
                ->toContain('auth:sanctum')
                ->toContain('think.tank.api.account')
                ->toContain('think.tank.api.ready')
                ->toContain('think.tank.area:finance');
            expect(collect($route->gatherMiddleware())->contains(
                fn (string $middleware): bool => str_starts_with($middleware, 'permission:think_tank.finance.')
            ))->toBeTrue();
        });

        $methods = $routes->mapWithKeys(fn ($route): array => [
            $route->uri() => collect($route->methods())->reject(fn (string $method): bool => $method === 'HEAD')->values()->all(),
        ]);
        expect($methods['api/v1/think-tank/finance/overview'])->toBe(['GET'])
            ->and($methods['api/v1/think-tank/finance/funding-requests'])->toBe(['POST'])
            ->and($methods['api/v1/think-tank/finance/transfers/{disbursement}/confirm'])->toBe(['POST']);
    } finally {
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('balances recipient cash accounting exactly and keeps currencies independent', function () {
    [$application, $bootedHere] = bootThinkTankFinanceApiApplication();

    try {
        $service = financeApiService();
        $usd = $service->balancedPosition('1000.10', '425.05');
        $eur = $service->balancedPosition('75.00', '100.00');

        expect($usd)->toMatchArray([
            'receipts' => '1000.10',
            'expenditure' => '425.05',
            'cash' => '575.05',
            'debits' => '1000.10',
            'credits' => '1000.10',
            'balanced' => true,
        ])->and($eur)->toMatchArray([
            'receipts' => '75.00',
            'expenditure' => '100.00',
            'cash' => '-25.00',
            'debits' => '100.00',
            'credits' => '100.00',
            'balanced' => true,
        ]);
    } finally {
        Mockery::close();
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('settles commitments per procurement without netting unrelated overruns', function () {
    [$application, $bootedHere] = bootThinkTankFinanceApiApplication();

    try {
        $service = financeApiService();
        $settlement = new ReflectionMethod($service, 'commitmentSettlement');
        $firstProcurement = '0a000000-0000-4000-8000-000000000001';
        $secondProcurement = '0a000000-0000-4000-8000-000000000002';
        $firstOrder = (new ProcurementPurchaseOrder)->forceFill([
            'id' => '0a000000-0000-4000-8000-000000000003',
            'procurement_id' => $firstProcurement,
            'amount' => '100.00',
        ]);
        $secondOrder = (new ProcurementPurchaseOrder)->forceFill([
            'id' => '0a000000-0000-4000-8000-000000000004',
            'procurement_id' => $secondProcurement,
            'amount' => '100.00',
        ]);
        $firstPayment = (new ProcurementDisbursement)->forceFill([
            'id' => '0a000000-0000-4000-8000-000000000005',
            'procurement_id' => $firstProcurement,
            'purchase_order_id' => $firstOrder->id,
            'amount' => '150.00',
        ]);
        $firstPayment->setRelation('purchaseOrder', $firstOrder);

        expect($settlement->invoke(
            $service,
            collect([$firstOrder, $secondOrder]),
            collect([$firstPayment]),
        ))->toBe([
            'gross' => 20000,
            'settled' => 10000,
            'outstanding' => 10000,
            'required' => 25000,
        ]);

        $fallbackOrder = (new ProcurementPurchaseOrder)->forceFill([
            'id' => '0a000000-0000-4000-8000-000000000006',
            'procurement_id' => null,
            'amount' => '60.00',
        ]);
        $fallbackPayment = (new ProcurementDisbursement)->forceFill([
            'id' => '0a000000-0000-4000-8000-000000000007',
            'procurement_id' => null,
            'purchase_order_id' => $fallbackOrder->id,
            'amount' => '20.00',
        ]);
        $fallbackPayment->setRelation('purchaseOrder', $fallbackOrder);

        expect($settlement->invoke(
            $service,
            collect([$fallbackOrder]),
            collect([$fallbackPayment]),
        ))->toBe([
            'gross' => 6000,
            'settled' => 2000,
            'outstanding' => 4000,
            'required' => 6000,
        ]);
    } finally {
        Mockery::close();
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('publishes and searches transfer descriptions in the complete funds register', function () {
    [$application, $bootedHere] = bootThinkTankFinanceApiApplication();

    try {
        $service = financeApiService();
        $auditor = (new User)->forceFill(['id' => '0b000000-0000-4000-8000-000000000001']);
        $auditor->setRelation('role', (new Role)->forceFill([
            'name' => Role::AUDITOR_NAME,
            'is_read_only_auditor' => true,
        ]));
        $order = (new ProcurementPurchaseOrder)->forceFill([
            'id' => '0b000000-0000-4000-8000-000000000002',
            'reference_no' => 'PO-TT-001',
            'po_title' => 'Research dissemination tranche',
        ]);
        $order->setRelation('purchaseRequest', null);
        $order->setRelation('budgetCommitment', null);
        $payment = (new ProcurementDisbursement)->forceFill([
            'id' => '0b000000-0000-4000-8000-000000000003',
            'purchase_order_id' => $order->id,
            'reference_no' => 'PAY-TT-001',
            'amount' => '100.00',
            'currency' => 'USD',
            'status' => 'paid',
            'recipient_confirmation_status' => 'pending',
            'portal_lock_version' => 1,
        ]);
        $payment->setRelation('purchaseOrder', $order);
        $payment->setRelation('recipientConfirmer', null);

        $resource = $service->transferResource($payment, $auditor);
        $fields = (new ReflectionClass($service))
            ->getReflectionConstant('TRANSFER_SEARCH_FIELDS')
            ->getValue();
        $filter = new ReflectionMethod($service, 'filterResources');
        $matches = $filter->invoke(
            $service,
            collect([$resource]),
            ['q' => 'dissemination tranche'],
            ['status', 'receiptStatus'],
            $fields,
        );

        expect($resource['description'])->toBe('Research dissemination tranche')
            ->and($fields)->toContain('description')
            ->and($matches)->toHaveCount(1);
    } finally {
        Mockery::close();
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('publishes the linked budget allocation identity on execution rows', function () {
    [$application, $bootedHere] = bootThinkTankFinanceApiApplication();

    try {
        $service = financeApiService();
        $resource = new ReflectionMethod($service, 'executionBudgetLineFields');
        $line = (new ThinkTankBudgetLine)->forceFill([
            'id' => '0f000000-0000-4000-8000-000000000001',
            'code' => 'RES-01',
            'name' => 'Research delivery',
        ]);

        expect($resource->invoke($service, $line))->toBe([
            'budgetLineId' => '0f000000-0000-4000-8000-000000000001',
            'budgetLineCode' => 'RES-01',
            'budgetLineName' => 'Research delivery',
            'budgetLine' => 'RES-01 - Research delivery',
        ])->and($resource->invoke($service, null))->toBe([
            'budgetLineId' => null,
            'budgetLineCode' => null,
            'budgetLineName' => null,
            'budgetLine' => null,
        ]);
    } finally {
        Mockery::close();
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('binds stale write tokens to the exact finance record state and lock version', function () {
    [$application, $bootedHere] = bootThinkTankFinanceApiApplication();
    $originalKey = config('app.key');
    config(['app.key' => 'base64:'.base64_encode(str_repeat('f', 32))]);

    try {
        $service = financeApiService();
        $line = (new ThinkTankBudgetLine)->forceFill([
            'id' => '10000000-0000-4000-8000-000000000001',
            'think_tank_member_id' => '10000000-0000-4000-8000-000000000002',
            'amount' => '100.00',
            'status' => ThinkTankBudgetLine::STATUS_ACTIVE,
            'portal_lock_version' => 1,
        ]);
        $token = $service->lockToken($line);
        $service->assertLockToken($line, $token);
        $line->portal_lock_version = 2;

        expect($token)->toHaveLength(64)
            ->and(fn () => $service->assertLockToken($line, $token))->toThrow(ThinkTankApiException::class);
    } finally {
        config(['app.key' => $originalKey]);
        Mockery::close();
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('reports auditor finance capabilities as view only even on a safe read', function () {
    [$application, $bootedHere] = bootThinkTankFinanceApiApplication();

    try {
        $auditor = (new User)->forceFill(['id' => '15000000-0000-4000-8000-000000000001']);
        $auditor->setRelation('role', (new Role)->forceFill([
            'name' => Role::AUDITOR_NAME,
            'is_read_only_auditor' => true,
        ]));

        $service = financeApiService();
        $transfer = (new ProcurementDisbursement)->forceFill([
            'id' => '15000000-0000-4000-8000-000000000002',
            'amount' => '100.00',
            'currency' => 'USD',
            'status' => 'paid',
            'recipient_confirmation_status' => 'pending',
            'portal_lock_version' => 1,
        ]);
        $transfer->setRelation('purchaseOrder', null);
        $transfer->setRelation('recipientConfirmer', null);

        expect($service->permissions($auditor))->toBe([
            'canView' => true,
            'canManage' => false,
            'canRequestFunds' => false,
            'canConfirmTransfers' => false,
            'canManageBudgetLines' => false,
        ])->and($service->transferResource($transfer, $auditor)['canConfirm'])->toBeFalse();
    } finally {
        Mockery::close();
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('rejects overallocated children cycles and active children beneath inactive ancestors', function () {
    [$application, $bootedHere] = bootThinkTankFinanceApiApplication();

    try {
        $service = financeApiService();
        $guard = new ReflectionMethod($service, 'assertBudgetHierarchy');
        $member = (new ConsortiumThinkTank)->forceFill([
            'id' => '20000000-0000-4000-8000-000000000001',
            'consortium_id' => '20000000-0000-4000-8000-000000000002',
        ]);
        $parent = (new ThinkTankBudgetLine)->forceFill([
            'id' => '20000000-0000-4000-8000-000000000003',
            'amount' => '100.00',
            'currency' => 'USD',
            'fiscal_year' => '2026',
            'status' => ThinkTankBudgetLine::STATUS_ACTIVE,
        ]);
        $draftParent = (new ThinkTankBudgetLine)->forceFill([
            'id' => '20000000-0000-4000-8000-000000000004',
            'amount' => '100.00',
            'currency' => 'USD',
            'fiscal_year' => '2026',
            'status' => ThinkTankBudgetLine::STATUS_DRAFT,
        ]);
        $rows = new Collection([$parent, $draftParent]);
        $child = fn (string $parentId, string $amount = '110.00'): array => [
            'parent_id' => $parentId,
            'fund_allocation_id' => null,
            'procurement_item_id' => null,
            'code' => 'CHILD',
            'name' => 'Child line',
            'description' => null,
            'fiscal_year' => '2026',
            'currency' => 'USD',
            'amount' => $amount,
            'status' => ThinkTankBudgetLine::STATUS_ACTIVE,
        ];

        expect(fn () => $guard->invoke($service, $member, $rows, $child((string) $parent->id)))
            ->toThrow(ValidationException::class)
            ->and(fn () => $guard->invoke($service, $member, $rows, $child((string) $draftParent->id, '50.00')))
            ->toThrow(ValidationException::class);

        $parent->parent_id = $draftParent->id;
        expect(fn () => $guard->invoke(
            $service,
            $member,
            $rows,
            $child((string) $parent->id, '50.00'),
            $draftParent,
        ))->toThrow(ValidationException::class);
    } finally {
        Mockery::close();
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('keeps executed budget classification immutable without an audited correction', function () {
    [$application, $bootedHere] = bootThinkTankFinanceApiApplication();

    try {
        $service = financeApiService();
        $guard = new ReflectionMethod($service, 'assertExecutedClassificationImmutable');
        $attributes = new ReflectionMethod($service, 'budgetLineAttributes');
        $line = (new ThinkTankBudgetLine)->forceFill([
            'id' => '25000000-0000-4000-8000-000000000001',
            'parent_id' => '25000000-0000-4000-8000-000000000002',
            'fund_allocation_id' => '25000000-0000-4000-8000-000000000003',
        ]);
        expect($guard->invoke(
            $service,
            $line,
            true,
            (string) $line->parent_id,
            (string) $line->fund_allocation_id,
        ))->toBeNull()
            ->and(fn () => $guard->invoke(
                $service,
                $line,
                true,
                null,
                (string) $line->fund_allocation_id,
            ))->toThrow(ValidationException::class)
            ->and(fn () => $guard->invoke(
                $service,
                $line,
                true,
                (string) $line->parent_id,
                null,
            ))->toThrow(ValidationException::class)
            ->and($guard->invoke($service, $line, false, null, null))->toBeNull();

        $correctable = (new ThinkTankBudgetLine)->forceFill([
            'id' => '25000000-0000-4000-8000-000000000004',
            'parent_id' => null,
            'fund_allocation_id' => '25000000-0000-4000-8000-000000000005',
            'procurement_item_id' => null,
            'code' => 'CORRECTABLE',
            'name' => 'Pre-execution allocation',
            'description' => null,
            'fiscal_year' => '2026',
            'currency' => 'USD',
            'amount' => '100.00',
            'status' => ThinkTankBudgetLine::STATUS_ACTIVE,
        ]);
        $correctable->setRelation('procurementItem', null);
        $normalized = $attributes->invoke(
            $service,
            (new ConsortiumThinkTank)->forceFill([
                'id' => '25000000-0000-4000-8000-000000000006',
                'consortium_id' => '25000000-0000-4000-8000-000000000007',
            ]),
            ['fund_allocation_id' => null],
            $correctable,
            collect([$correctable]),
        );

        expect($normalized['fund_allocation_id'])->toBeNull()
            ->and($normalized['amount'])->toBe('100.00');
    } finally {
        Mockery::close();
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('does not let one procurement overrun mask unused headroom on another', function () {
    [$application, $bootedHere] = bootThinkTankFinanceApiApplication();

    try {
        $service = financeApiService();
        $resource = new ReflectionMethod($service, 'budgetLineResource');
        $root = (new ThinkTankBudgetLine)->forceFill([
            'id' => '26000000-0000-4000-8000-000000000001',
            'parent_id' => null,
            'code' => 'ROOT',
            'name' => 'Root',
            'fiscal_year' => '2026',
            'currency' => 'USD',
            'amount' => '200.00',
            'status' => ThinkTankBudgetLine::STATUS_ACTIVE,
            'portal_lock_version' => 1,
        ]);
        $root->setRelation('procurementItem', null);
        $children = collect([
            ['id' => '26000000-0000-4000-8000-000000000002', 'procurement' => '26000000-0000-4000-8000-000000000004'],
            ['id' => '26000000-0000-4000-8000-000000000003', 'procurement' => '26000000-0000-4000-8000-000000000005'],
        ])->map(function (array $identity) use ($root): ThinkTankBudgetLine {
            $line = (new ThinkTankBudgetLine)->forceFill([
                'id' => $identity['id'],
                'parent_id' => $root->id,
                'currency' => 'USD',
                'amount' => '100.00',
                'status' => ThinkTankBudgetLine::STATUS_ACTIVE,
            ]);
            $line->setRelation('procurementItem', (new ThinkTankProcurementItem)->forceFill([
                'id' => $identity['id'],
                'procurement_id' => $identity['procurement'],
            ]));

            return $line;
        });
        $allLines = $children->prepend($root);
        $result = $resource->invoke($service, $root, $allLines, [
            'committedByProcurement' => [
                '26000000-0000-4000-8000-000000000004' => 10000,
                '26000000-0000-4000-8000-000000000005' => 10000,
            ],
            'spentByProcurement' => [
                '26000000-0000-4000-8000-000000000004' => 15000,
            ],
        ]);

        expect($result['committedAmount'])->toBe('200.00')
            ->and($result['spentAmount'])->toBe('150.00')
            ->and($result['availableAmount'])->toBe('-50.00')
            ->and($result['classificationLocked'])->toBeTrue();
    } finally {
        Mockery::close();
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('confirms a recognized direct transfer once and replays without reposting accounting', function () {
    [$application, $bootedHere] = bootThinkTankFinanceApiApplication();
    $connectionName = 'think_tank_finance_receipt_test';
    $originalConnection = config('database.default');
    $originalDefinition = config('database.connections.'.$connectionName);
    $originalKey = config('app.key');
    Carbon::setTestNow('2027-01-05 10:00:00');
    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('r', 32)),
        'database.default' => $connectionName,
        'database.connections.'.$connectionName => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
    ]);

    try {
        $schema = DB::connection($connectionName)->getSchemaBuilder();
        $schema->create('attp_consortium_think_tanks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('consortium_id');
            $table->string('name');
            $table->string('status');
            $table->timestamps();
        });
        $schema->create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
        });
        $schema->create('system_audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('module')->nullable();
            $table->string('action')->nullable();
            $table->string('action_message')->nullable();
            $table->text('description')->nullable();
            $table->string('method')->nullable();
            $table->string('url')->nullable();
            $table->string('route_name')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('country')->nullable();
            $table->text('user_agent')->nullable();
            $table->integer('status_code')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
        $fundingSource = createThinkTankFundingSourceFixture();
        $schema->create('procurement_purchase_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('procurement_id')->nullable();
            $table->uuid('budget_commitment_id')->nullable();
            $table->uuid('sub_activity_id')->nullable();
            $table->uuid('consortium_id');
            $table->uuid('think_tank_member_id');
            $table->string('po_type');
            $table->string('reference_no')->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->string('status');
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });
        $schema->create('procurement_disbursements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('purchase_order_id')->nullable();
            $table->uuid('procurement_id')->nullable();
            $table->uuid('consortium_id');
            $table->uuid('think_tank_member_id');
            $table->uuid('fund_allocation_id')->nullable();
            $table->uuid('consortium_disbursement_request_id')->nullable();
            $table->string('reference_no')->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->string('status');
            $table->string('payment_method')->nullable();
            $table->string('transfer_reference')->nullable();
            $table->string('recipient_confirmation_status')->nullable();
            $table->uuid('recipient_confirmed_by')->nullable();
            $table->timestamp('recipient_confirmed_at')->nullable();
            $table->text('recipient_confirmation_notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
        $schema->create('procurements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('consortium_id');
            $table->uuid('think_tank_member_id');
            $table->softDeletes();
        });
        $schema->create('attp_fund_allocations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('consortium_id');
            $table->uuid('think_tank_member_id');
            $table->uuid('program_funding_id')->nullable();
            $table->uuid('source_purchase_order_id')->nullable();
            $table->string('budget_line')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('amount_allocated', 18, 2)->default(0);
            $table->decimal('amount_committed', 18, 2)->default(0);
            $table->decimal('amount_disbursed', 18, 2)->default(0);
            $table->decimal('amount_spent', 18, 2)->default(0);
            $table->string('status')->default('active');
        });
        $schema->create('attp_disbursement_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('consortium_id');
            $table->uuid('think_tank_member_id');
            $table->timestamp('requested_at')->nullable();
        });
        $schema->create('attp_think_tank_budget_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('consortium_id');
            $table->uuid('think_tank_member_id');
            $table->uuid('parent_id')->nullable();
            $table->uuid('procurement_item_id')->nullable();
            $table->string('fiscal_year', 7);
            $table->string('currency', 3);
            $table->decimal('amount', 18, 2);
            $table->string('status');
        });

        $tenantId = '30000000-0000-4000-8000-000000000001';
        $consortiumId = '30000000-0000-4000-8000-000000000002';
        $actorId = '30000000-0000-4000-8000-000000000003';
        $orderId = '30000000-0000-4000-8000-000000000004';
        $transferId = '30000000-0000-4000-8000-000000000005';
        $futureTransferId = '30000000-0000-4000-8000-000000000008';
        $procurementId = '30000000-0000-4000-8000-000000000009';
        $legacyCurrentOrderId = '30000000-0000-4000-8000-000000000011';
        $legacyFutureOrderId = '30000000-0000-4000-8000-000000000012';
        DB::table('attp_consortium_think_tanks')->insert([
            'id' => $tenantId, 'consortium_id' => $consortiumId, 'name' => 'Tenant A', 'status' => 'active',
        ]);
        DB::table('attp_consortium_think_tanks')->insert([
            'id' => '30000000-0000-4000-8000-000000000006',
            'consortium_id' => '30000000-0000-4000-8000-000000000007',
            'name' => 'Tenant B',
            'status' => 'active',
        ]);
        DB::table('users')->insert(['id' => $actorId, 'name' => 'Finance officer']);
        DB::table('procurements')->insert([
            'id' => $procurementId,
            'consortium_id' => $consortiumId,
            'think_tank_member_id' => $tenantId,
        ]);
        DB::table('procurement_purchase_orders')->insert([
            'id' => $orderId, 'consortium_id' => $consortiumId, 'think_tank_member_id' => $tenantId,
            'budget_commitment_id' => $fundingSource['commitment'],
            'sub_activity_id' => $fundingSource['subActivity'],
            'po_type' => 'think_tank_transfer', 'reference_no' => 'TT-PO-1', 'amount' => '250.00',
            'currency' => 'USD', 'status' => 'fully_paid',
        ]);
        DB::table('procurement_purchase_orders')->insert([
            [
                'id' => $legacyCurrentOrderId,
                'procurement_id' => $procurementId,
                'consortium_id' => $consortiumId,
                'think_tank_member_id' => $tenantId,
                'po_type' => 'procurement',
                'reference_no' => 'TT-PO-LEGACY-CURRENT',
                'amount' => '75.00',
                'currency' => 'USD',
                'status' => 'issued',
                'issued_at' => null,
                'created_at' => '2027-01-03 09:00:00',
                'updated_at' => '2027-01-03 09:00:00',
            ],
            [
                'id' => $legacyFutureOrderId,
                'procurement_id' => $procurementId,
                'consortium_id' => $consortiumId,
                'think_tank_member_id' => $tenantId,
                'po_type' => 'procurement',
                'reference_no' => 'TT-PO-LEGACY-FUTURE',
                'amount' => '500.00',
                'currency' => 'USD',
                'status' => 'issued',
                'issued_at' => null,
                'created_at' => '2027-01-06 09:00:00',
                'updated_at' => '2027-01-06 09:00:00',
            ],
        ]);
        DB::table('procurement_disbursements')->insert([
            'id' => $transferId, 'purchase_order_id' => $orderId, 'consortium_id' => $consortiumId,
            'think_tank_member_id' => $tenantId, 'reference_no' => 'TT-PAY-1', 'amount' => '250.00',
            'currency' => 'USD', 'status' => 'paid', 'recipient_confirmation_status' => 'pending',
            'paid_at' => '2026-12-31 09:00:00',
        ]);
        DB::table('procurement_disbursements')->insert([
            'id' => $futureTransferId,
            'purchase_order_id' => $orderId,
            'consortium_id' => $consortiumId,
            'think_tank_member_id' => $tenantId,
            'reference_no' => 'TT-PAY-FUTURE',
            'amount' => '25.00',
            'currency' => 'USD',
            'status' => 'paid',
            'recipient_confirmation_status' => 'pending',
            'paid_at' => '2027-01-06 09:00:00',
        ]);
        DB::table('procurement_disbursements')->insert([
            'id' => '30000000-0000-4000-8000-000000000010',
            'purchase_order_id' => null,
            'procurement_id' => $procurementId,
            'consortium_id' => $consortiumId,
            'think_tank_member_id' => $tenantId,
            'reference_no' => 'TT-SPEND-NO-PO',
            'amount' => '50.00',
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => '2027-01-04 09:00:00',
        ]);

        $member = ConsortiumThinkTank::query()->findOrFail($tenantId);
        $actor = (new User)->forceFill(['id' => $actorId, 'name' => 'Finance officer']);
        $actor->exists = true;
        $actor->setRelation('role', (new Role)->forceFill([
            'name' => 'System Admin',
            'is_read_only_auditor' => false,
        ]));
        $audit = Mockery::mock(ThinkTankApiAuditService::class);
        $audit->shouldReceive('required')->once();
        $finance = new ThinkTankFinanceApiService($audit);
        $service = new ThinkTankFundingReceiptService($finance, $audit);
        $original = ProcurementDisbursement::query()->findOrFail($transferId);
        $token = $finance->lockToken($original);
        $request = Request::create('/api/v1/think-tank/finance/transfers/'.$transferId.'/confirm', 'POST');

        $first = $service->confirm($request, $member, $actor, $transferId, $token, 'Matched to bank statement.');
        $replay = $service->confirm($request, $member, $actor, $transferId, $token, 'Ignored replay note.');
        $foreignMember = ConsortiumThinkTank::query()->findOrFail('30000000-0000-4000-8000-000000000006');
        $report2026 = collect($finance->reports($member, $actor, ['period' => '2026'])['reports'])
            ->firstWhere('currency', 'USD');
        $report2027 = collect($finance->reports($member, $actor, ['period' => '2027'])['reports'])
            ->firstWhere('currency', 'USD');
        $funds = $finance->funds($member, $actor);
        $futureTransfer = ProcurementDisbursement::query()->findOrFail($futureTransferId);
        expect(fn () => $service->confirm(
            $request,
            $member,
            $actor,
            $futureTransferId,
            $finance->lockToken($futureTransfer),
        ))->toThrow(ModelNotFoundException::class);
        $futureTransfer->recipient_confirmation_status = 'rejected';
        $futureTransfer->setRelation('purchaseOrder', ProcurementPurchaseOrder::query()->findOrFail($orderId));
        $futureTransfer->setRelation('recipientConfirmer', null);
        $futureResource = $finance->transferResource($futureTransfer, $actor);

        expect($first['idempotent'])->toBeFalse()
            ->and($replay['idempotent'])->toBeTrue()
            ->and($first['transfer']->recipient_confirmation_status)->toBe('confirmed')
            ->and($report2026['receiptsAndExpenditure']['receipts'])->toBe('0.00')
            ->and($report2026['receiptsAndExpenditure']['pendingConfirmation'])->toBe('250.00')
            ->and($report2027['receiptsAndExpenditure']['receipts'])->toBe('250.00')
            ->and($report2027['receiptsAndExpenditure']['expenditure'])->toBe('50.00')
            ->and($report2027['offBalanceSheetCommitments'])->toBe([
                'grossCommitments' => '75.00',
                'settledThroughRecognizedPayments' => '50.00',
                'outstandingCommitments' => '25.00',
            ])
            ->and($report2027['ledger'][0]['date'])->toBe('2027-01-04')
            ->and($report2027['ledger'][0]['type'])->toBe('procurement_expenditure')
            ->and($report2027['ledger'][1]['date'])->toBe('2027-01-05')
            ->and($funds['resultSet'])->toBe([
                'complete' => true,
                'transferCount' => 1,
                'fundingRequestCount' => 0,
            ])
            ->and($funds['positions'][0]['spent'])->toBe('50.00')
            ->and($funds['positions'][0]['cashBalance'])->toBe('200.00')
            ->and($futureResource['canConfirm'])->toBeFalse()
            ->and(DB::table('procurement_purchase_orders')->where('id', $orderId)->value('status'))->toBe('fully_paid')
            ->and($schema->hasTable('procurement_invoices'))->toBeFalse()
            ->and(fn () => $service->confirm($request, $foreignMember, $actor, $transferId, $token))
            ->toThrow(ModelNotFoundException::class);
    } finally {
        Carbon::setTestNow();
        DB::purge($connectionName);
        config([
            'database.default' => $originalConnection,
            'database.connections.'.$connectionName => $originalDefinition,
            'app.key' => $originalKey,
        ]);
        Mockery::close();
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
})->skip(! extension_loaded('pdo_sqlite'), 'Enable pdo_sqlite to run isolated finance receipt tests.');

it('creates funding requests idempotently and rejects key reuse with a changed payload', function () {
    [$application, $bootedHere] = bootThinkTankFinanceApiApplication();
    $connectionName = 'think_tank_finance_request_test';
    $originalConnection = config('database.default');
    $originalDefinition = config('database.connections.'.$connectionName);
    config([
        'database.default' => $connectionName,
        'database.connections.'.$connectionName => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
    ]);

    try {
        $schema = DB::connection($connectionName)->getSchemaBuilder();
        $schema->create('attp_consortium_think_tanks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('consortium_id');
            $table->string('name');
            $table->string('status');
            $table->timestamps();
        });
        $schema->create('attp_disbursement_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('consortium_id');
            $table->uuid('think_tank_member_id');
            $table->uuid('fund_allocation_id')->nullable();
            $table->string('request_code')->unique();
            $table->decimal('amount_requested', 18, 2);
            $table->decimal('amount_approved', 18, 2)->default(0);
            $table->string('currency', 3);
            $table->string('status');
            $table->text('purpose');
            $table->uuid('requested_by');
            $table->timestamp('requested_at');
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('portal_idempotency_key', 100)->nullable();
            $table->string('portal_idempotency_fingerprint', 64)->nullable();
            $table->unsignedBigInteger('portal_lock_version')->default(1);
            $table->timestamps();
            $table->unique(['think_tank_member_id', 'portal_idempotency_key']);
        });

        $tenantId = '40000000-0000-4000-8000-000000000001';
        $consortiumId = '40000000-0000-4000-8000-000000000002';
        $actorId = '40000000-0000-4000-8000-000000000003';
        DB::table('attp_consortium_think_tanks')->insert([
            'id' => $tenantId,
            'consortium_id' => $consortiumId,
            'name' => 'Tenant A',
            'status' => 'active',
        ]);
        $member = ConsortiumThinkTank::query()->findOrFail($tenantId);
        $actor = (new User)->forceFill(['id' => $actorId, 'name' => 'Finance officer']);
        $actor->exists = true;
        $audit = Mockery::mock(ThinkTankApiAuditService::class);
        $audit->shouldReceive('required')->once();
        $service = new ThinkTankFinanceApiService($audit);
        $request = Request::create('/api/v1/think-tank/finance/funding-requests', 'POST');
        $payload = [
            'fund_allocation_id' => null,
            'amount' => '1250.00',
            'currency' => 'USD',
            'purpose' => 'Second-quarter research implementation tranche.',
            'idempotency_key' => 'finance-request-2026-0001',
        ];

        [$first, $replay] = ConsortiumDisbursementRequest::withoutEvents(fn (): array => [
            $service->createFundingRequest($request, $member, $actor, $payload),
            $service->createFundingRequest($request, $member, $actor, $payload),
        ]);

        expect($first['idempotent'])->toBeFalse()
            ->and($replay['idempotent'])->toBeTrue()
            ->and((string) $first['request']->id)->toBe((string) $replay['request']->id)
            ->and(DB::table('attp_disbursement_requests')->count())->toBe(1)
            ->and(fn () => ConsortiumDisbursementRequest::withoutEvents(fn () => $service->createFundingRequest(
                $request,
                $member,
                $actor,
                [...$payload, 'amount' => '1300.00'],
            )))->toThrow(ThinkTankApiException::class);
    } finally {
        DB::purge($connectionName);
        config([
            'database.default' => $originalConnection,
            'database.connections.'.$connectionName => $originalDefinition,
        ]);
        Mockery::close();
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
})->skip(! extension_loaded('pdo_sqlite'), 'Enable pdo_sqlite to run isolated finance request tests.');

it('does not reopen historically disbursed allocation capacity or double count paired paid requests', function () {
    [$application, $bootedHere] = bootThinkTankFinanceApiApplication();

    try {
        $service = financeApiService();
        $allocation = (new ConsortiumFundAllocation)->forceFill([
            'id' => '45000000-0000-4000-8000-000000000001',
            'amount_allocated' => '1000.00',
            'amount_disbursed' => '600.00',
        ]);
        $requests = collect([
            (new ConsortiumDisbursementRequest)->forceFill([
                'fund_allocation_id' => $allocation->id,
                'amount_requested' => '100.00',
                'amount_approved' => '100.00',
                'status' => 'paid',
            ]),
            (new ConsortiumDisbursementRequest)->forceFill([
                'fund_allocation_id' => $allocation->id,
                'amount_requested' => '200.00',
                'amount_approved' => '200.00',
                'status' => 'approved',
            ]),
        ]);
        $method = new ReflectionMethod($service, 'allocationUsage');
        $usage = $method->invoke($service, $allocation, $requests);

        expect($usage)->toMatchArray([
            'disbursed' => 60000,
            'paidRequests' => 10000,
            'outstandingRequests' => 20000,
            'reserved' => 80000,
        ]);
    } finally {
        Mockery::close();
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('accepts canonical fiscal labels and permits a pre-execution funding-source correction', function () {
    [$application, $bootedHere] = bootThinkTankFinanceApiApplication();

    try {
        $service = financeApiService();
        $method = new ReflectionMethod($service, 'budgetLineAttributes');
        $member = (new ConsortiumThinkTank)->forceFill([
            'id' => '46000000-0000-4000-8000-000000000001',
            'consortium_id' => '46000000-0000-4000-8000-000000000002',
        ]);
        $payload = [
            'code' => 'RESEARCH-01',
            'name' => 'Research delivery',
            'fiscal_year' => '2026',
            'currency' => 'USD',
            'amount' => '100.00',
            'status' => ThinkTankBudgetLine::STATUS_ACTIVE,
        ];

        expect($method->invoke($service, $member, $payload, null, collect())['fiscal_year'])->toBe('2026')
            ->and($method->invoke(
                $service,
                $member,
                [...$payload, 'fiscal_year' => '2026/27'],
                null,
                collect(),
            )['fiscal_year'])->toBe('2026/27')
            ->and(fn () => $method->invoke(
                $service,
                $member,
                [...$payload, 'fiscal_year' => '26/27'],
                null,
                collect(),
            ))->toThrow(ValidationException::class);

        $linked = (new ThinkTankBudgetLine)->forceFill([
            ...$payload,
            'id' => '46000000-0000-4000-8000-000000000003',
            'fund_allocation_id' => '46000000-0000-4000-8000-000000000004',
        ]);
        $corrected = $method->invoke(
            $service,
            $member,
            ['fund_allocation_id' => null],
            $linked,
            collect([$linked]),
        );
        expect($corrected['fund_allocation_id'])->toBeNull();
    } finally {
        Mockery::close();
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('preserves a partial approval and increments its allocation only once when paid', function () {
    [$application, $bootedHere] = bootThinkTankFinanceApiApplication();
    $connectionName = 'think_tank_finance_review_test';
    $originalConnection = config('database.default');
    $originalDefinition = config('database.connections.'.$connectionName);
    config([
        'database.default' => $connectionName,
        'database.connections.'.$connectionName => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
    ]);

    try {
        $schema = DB::connection($connectionName)->getSchemaBuilder();
        $schema->create('attp_consortium_think_tanks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('consortium_id');
            $table->string('status');
            $table->timestamps();
        });
        $schema->create('procurements', fn (Blueprint $table) => $table->uuid('id')->primary());
        $schema->create('procurement_purchase_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('budget_commitment_id')->nullable();
            $table->uuid('sub_activity_id')->nullable();
            $table->uuid('consortium_id');
            $table->uuid('think_tank_member_id');
            $table->string('po_type');
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('issued');
            $table->timestamp('issued_at')->nullable();
        });
        $schema->create('procurement_disbursements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('purchase_order_id');
            $table->uuid('procurement_id')->nullable();
            $table->uuid('consortium_id');
            $table->uuid('think_tank_member_id');
            $table->uuid('fund_allocation_id')->nullable();
            $table->uuid('consortium_disbursement_request_id')->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->string('status');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
        $schema->create('attp_fund_allocations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('consortium_id');
            $table->uuid('think_tank_member_id');
            $table->uuid('program_funding_id')->nullable();
            $table->uuid('source_purchase_order_id')->nullable();
            $table->string('currency', 3);
            $table->decimal('amount_allocated', 18, 2);
            $table->decimal('amount_disbursed', 18, 2)->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
        });
        $fundingSource = createThinkTankFundingSourceFixture();
        $schema->create('attp_disbursement_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('consortium_id');
            $table->uuid('think_tank_member_id');
            $table->uuid('fund_allocation_id');
            $table->string('currency', 3);
            $table->decimal('amount_requested', 18, 2);
            $table->decimal('amount_approved', 18, 2)->default(0);
            $table->string('status');
            $table->text('review_notes')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('portal_lock_version')->default(1);
            $table->timestamps();
        });
        $schema->create('system_audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('module')->nullable();
            $table->string('action')->nullable();
            $table->string('action_message')->nullable();
            $table->text('description')->nullable();
            $table->string('method')->nullable();
            $table->string('url')->nullable();
            $table->string('route_name')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('country')->nullable();
            $table->text('user_agent')->nullable();
            $table->integer('status_code')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        $tenantId = '50000000-0000-4000-8000-000000000001';
        $consortiumId = '50000000-0000-4000-8000-000000000002';
        $allocationId = '50000000-0000-4000-8000-000000000003';
        $requestId = '50000000-0000-4000-8000-000000000004';
        DB::table('attp_consortium_think_tanks')->insert([
            'id' => $tenantId, 'consortium_id' => $consortiumId, 'status' => 'active',
        ]);
        DB::table('attp_fund_allocations')->insert([
            'id' => $allocationId, 'consortium_id' => $consortiumId, 'think_tank_member_id' => $tenantId,
            'program_funding_id' => $fundingSource['funding'], 'currency' => 'USD', 'amount_allocated' => '1000.00',
            'amount_disbursed' => '80.00', 'status' => 'active',
        ]);
        DB::table('attp_disbursement_requests')->insert([
            'id' => $requestId, 'consortium_id' => $consortiumId, 'think_tank_member_id' => $tenantId,
            'fund_allocation_id' => $allocationId, 'currency' => 'USD', 'amount_requested' => '100.00',
            'amount_approved' => '80.00', 'status' => 'approved', 'portal_lock_version' => 2,
        ]);

        $controller = new ConsortiumOperationsController(
            Mockery::mock(ThinkTankUserManagementService::class),
            Mockery::mock(ThinkTankInvitationService::class),
        );
        $httpRequest = Request::create('/consortium-operations/disbursements/'.$requestId.'/review', 'POST', [
            'status' => 'paid',
            'review_notes' => 'Approved partial tranche paid.',
        ]);
        $bound = ConsortiumDisbursementRequest::query()->findOrFail($requestId);
        expect(fn () => $controller->reviewDisbursement($httpRequest, $bound))
            ->toThrow(ValidationException::class)
            ->and(DB::table('attp_disbursement_requests')->where('id', $requestId)->value('status'))
            ->toBe('approved');

        $orderId = '50000000-0000-4000-8000-000000000005';
        DB::table('procurement_purchase_orders')->insert([
            'id' => $orderId,
            'budget_commitment_id' => $fundingSource['commitment'],
            'sub_activity_id' => $fundingSource['subActivity'],
            'consortium_id' => $consortiumId,
            'think_tank_member_id' => $tenantId,
            'po_type' => 'think_tank_transfer',
            'amount' => '80.00',
            'currency' => 'USD',
            'status' => 'fully_paid',
            'issued_at' => '2026-10-05 08:00:00',
        ]);
        DB::table('procurement_disbursements')->insert([
            'id' => '50000000-0000-4000-8000-000000000006',
            'purchase_order_id' => $orderId,
            'consortium_id' => $consortiumId,
            'think_tank_member_id' => $tenantId,
            'fund_allocation_id' => $allocationId,
            'consortium_disbursement_request_id' => $requestId,
            'amount' => '80.00',
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => '2026-10-05 09:00:00',
        ]);
        $controller->reviewDisbursement($httpRequest, $bound);
        $controller->reviewDisbursement($httpRequest, $bound->fresh());

        expect(DB::table('attp_disbursement_requests')->where('id', $requestId)->value('amount_approved'))->toBe(80)
            ->and(DB::table('attp_disbursement_requests')->where('id', $requestId)->value('status'))->toBe('paid')
            ->and(DB::table('attp_fund_allocations')->where('id', $allocationId)->value('amount_disbursed'))->toBe(80);
    } finally {
        DB::purge($connectionName);
        config([
            'database.default' => $originalConnection,
            'database.connections.'.$connectionName => $originalDefinition,
        ]);
        Mockery::close();
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
})->skip(! extension_loaded('pdo_sqlite'), 'Enable pdo_sqlite to run isolated finance review tests.');

it('backfills historical awards only from a reviewed exact plan and confirms a partial payment once', function () {
    [$application, $bootedHere] = bootThinkTankFinanceApiApplication();
    $connectionName = 'think_tank_historical_funding_backfill_test';
    $originalConnection = config('database.default');
    $originalDefinition = config('database.connections.'.$connectionName);
    $originalKey = config('app.key');
    Carbon::setTestNow('2026-10-05 12:00:00');
    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('h', 32)),
        'database.default' => $connectionName,
        'database.connections.'.$connectionName => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
    ]);

    try {
        $schema = DB::connection($connectionName)->getSchemaBuilder();
        $source = createThinkTankFundingSourceFixture();
        $schema->create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
        });
        $schema->create('attp_consortium_think_tanks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('consortium_id');
            $table->uuid('vendor_user_id')->nullable();
            $table->string('name');
            $table->string('status');
            $table->timestamps();
        });
        $schema->create('procurements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('consortium_id')->nullable();
            $table->uuid('think_tank_member_id')->nullable();
            $table->softDeletes();
        });
        $schema->create('procurement_purchase_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('procurement_id')->nullable();
            $table->uuid('invoice_id')->nullable();
            $table->uuid('budget_commitment_id')->nullable();
            $table->uuid('sub_activity_id')->nullable();
            $table->uuid('consortium_id')->nullable();
            $table->uuid('think_tank_member_id')->nullable();
            $table->uuid('vendor_id')->nullable();
            $table->string('reference_no')->nullable();
            $table->string('po_type')->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->string('status');
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });
        $schema->create('procurement_disbursements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('purchase_order_id')->nullable();
            $table->uuid('procurement_id')->nullable();
            $table->uuid('vendor_id')->nullable();
            $table->uuid('sub_activity_id')->nullable();
            $table->uuid('consortium_id')->nullable();
            $table->uuid('think_tank_member_id')->nullable();
            $table->uuid('fund_allocation_id')->nullable();
            $table->uuid('consortium_disbursement_request_id')->nullable();
            $table->string('reference_no')->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->string('status');
            $table->string('payment_method')->nullable();
            $table->string('transfer_reference')->nullable();
            $table->string('recipient_confirmation_status')->nullable();
            $table->uuid('recipient_confirmed_by')->nullable();
            $table->timestamp('recipient_confirmed_at')->nullable();
            $table->text('recipient_confirmation_notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
        $schema->create('attp_fund_allocations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('consortium_id');
            $table->uuid('think_tank_member_id');
            $table->uuid('program_funding_id');
            $table->uuid('source_purchase_order_id')->nullable()->unique();
            $table->string('budget_line');
            $table->string('currency', 3);
            $table->decimal('amount_allocated', 18, 2)->default(0);
            $table->decimal('amount_committed', 18, 2)->default(0);
            $table->decimal('amount_disbursed', 18, 2)->default(0);
            $table->decimal('amount_spent', 18, 2)->default(0);
            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        $schema->create('attp_disbursement_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('consortium_id')->nullable();
            $table->uuid('think_tank_member_id')->nullable();
            $table->uuid('fund_allocation_id')->nullable();
            $table->decimal('amount_requested', 18, 2)->default(0);
            $table->decimal('amount_approved', 18, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->uuid('reviewed_by')->nullable();
        });
        $schema->create('attp_think_tank_budget_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('consortium_id');
            $table->uuid('think_tank_member_id');
            $table->uuid('parent_id')->nullable();
            $table->uuid('procurement_item_id')->nullable();
            $table->string('fiscal_year', 7);
            $table->string('currency', 3);
            $table->decimal('amount', 18, 2);
            $table->string('status');
        });
        $schema->create('system_audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('module')->nullable();
            $table->string('action')->nullable();
            $table->string('action_message')->nullable();
            $table->text('description')->nullable();
            $table->string('method')->nullable();
            $table->string('url')->nullable();
            $table->string('route_name')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('country')->nullable();
            $table->text('user_agent')->nullable();
            $table->integer('status_code')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        $memberId = '60000000-0000-4000-8000-000000000001';
        $consortiumId = '60000000-0000-4000-8000-000000000002';
        $vendorId = '60000000-0000-4000-8000-000000000003';
        $orderId = '60000000-0000-4000-8000-000000000004';
        $paymentId = '60000000-0000-4000-8000-000000000005';
        $actorId = '60000000-0000-4000-8000-000000000006';
        DB::table('users')->insert(['id' => $actorId, 'name' => 'Finance officer']);
        DB::table('attp_consortium_think_tanks')->insert([
            'id' => $memberId,
            'consortium_id' => $consortiumId,
            'vendor_user_id' => $vendorId,
            'name' => 'Historical award tenant',
            'status' => 'active',
        ]);
        DB::table('procurement_purchase_orders')->insert([
            'id' => $orderId,
            'budget_commitment_id' => $source['commitment'],
            'sub_activity_id' => $source['subActivity'],
            'vendor_id' => $vendorId,
            'reference_no' => 'AUC-TK-HIST-001',
            'po_type' => 'procurement',
            'amount' => '1000.00',
            'currency' => 'USD',
            'status' => 'partial_paid',
            'issued_at' => '2026-01-15 00:00:00',
        ]);
        DB::table('procurement_disbursements')->insert([
            'id' => $paymentId,
            'purchase_order_id' => $orderId,
            'vendor_id' => $vendorId,
            'reference_no' => 'PAY-HIST-001',
            'amount' => '250.00',
            'currency' => 'USD',
            'status' => 'fully_paid',
            'paid_at' => '2026-02-01 09:00:00',
        ]);

        $sources = new ThinkTankFundingSourceService;
        $backfill = new ThinkTankHistoricalFundingBackfillService($sources);
        $preview = $backfill->preview();
        expect($preview['dryRun'])->toBeTrue()
            ->and($preview['candidates'])->toBe(1)
            ->and($preview['ready'])->toBe(1)
            ->and($preview['wouldCreateAllocations'])->toBe(1)
            ->and($preview['planHash'])->toMatch('/^[a-f0-9]{64}$/')
            ->and($preview['rows'][0])->toMatchArray([
                'referenceNumber' => 'AUC-TK-HIST-001',
                'thinkTankMemberId' => $memberId,
                'consortiumId' => $consortiumId,
                'amount' => '1000.00',
                'recognizedPaid' => '250.00',
                'allocationId' => null,
            ])
            ->and(DB::table('attp_fund_allocations')->count())->toBe(0)
            ->and(DB::table('procurement_purchase_orders')->where('id', $orderId)->value('po_type'))->toBe('procurement')
            ->and($sources->incomingPurchaseOrdersQuery()->count())->toBe(0)
            ->and($sources->incomingPaymentsQuery()->count())->toBe(0);

        expect(fn () => $backfill->apply(2, $preview['planHash']))
            ->toThrow(RuntimeException::class);
        expect(DB::table('attp_fund_allocations')->count())->toBe(0);
        DB::table('procurement_disbursements')->where('id', $paymentId)->update([
            'reference_no' => 'PAY-HIST-CHANGED',
        ]);
        expect(fn () => $backfill->apply(1, $preview['planHash']))
            ->toThrow(RuntimeException::class)
            ->and(DB::table('attp_fund_allocations')->count())->toBe(0);
        DB::table('procurement_disbursements')->where('id', $paymentId)->update([
            'reference_no' => 'PAY-HIST-001',
        ]);

        $applied = $backfill->apply(1, $preview['planHash']);
        $allocation = ConsortiumFundAllocation::query()->where('source_purchase_order_id', $orderId)->firstOrFail();
        expect($applied['changed'])->toBeTrue()
            ->and($applied['allocationsCreated'])->toBe(1)
            ->and($allocation->amount_allocated)->toBe('1000.00')
            ->and($allocation->amount_committed)->toBe('1000.00')
            ->and($allocation->amount_disbursed)->toBe('250.00')
            ->and(DB::table('procurement_purchase_orders')->where('id', $orderId)->value('po_type'))->toBe('procurement')
            ->and(DB::table('procurement_purchase_orders')->where('id', $orderId)->value('think_tank_member_id'))->toBe($memberId)
            ->and(DB::table('procurement_disbursements')->where('id', $paymentId)->value('fund_allocation_id'))->toBe($allocation->id)
            ->and(DB::table('procurement_disbursements')->where('id', $paymentId)->value('recipient_confirmation_status'))->toBe('pending')
            ->and($sources->incomingPurchaseOrdersQuery()->count())->toBe(1)
            ->and($sources->incomingPaymentsQuery()->count())->toBe(1);

        $rerun = $backfill->preview();
        $reapplied = $backfill->apply(1, $rerun['planHash']);
        expect($reapplied['changed'])->toBeFalse()
            ->and(DB::table('attp_fund_allocations')->count())->toBe(1)
            ->and(DB::table('system_audit_logs')->where('action', 'historical_award_funding_backfilled')->count())->toBe(1);

        $member = ConsortiumThinkTank::query()->findOrFail($memberId);
        $actor = (new User)->forceFill(['id' => $actorId, 'name' => 'Finance officer']);
        $actor->exists = true;
        $actor->setRelation('role', (new Role)->forceFill([
            'name' => 'System Admin',
            'is_read_only_auditor' => false,
        ]));
        $audit = Mockery::mock(ThinkTankApiAuditService::class);
        $audit->shouldReceive('required')->once();
        $finance = new ThinkTankFinanceApiService($audit);
        $receipts = new ThinkTankFundingReceiptService($finance, $audit);
        $payment = ProcurementDisbursement::query()->findOrFail($paymentId);
        $token = $finance->lockToken($payment);
        $httpRequest = Request::create('/api/v1/think-tank/finance/transfers/'.$paymentId.'/confirm', 'POST');
        $confirmed = $receipts->confirm($httpRequest, $member, $actor, $paymentId, $token, 'Partial award payment received.');
        $replayed = $receipts->confirm($httpRequest, $member, $actor, $paymentId, $token, 'Replay.');
        $funds = $finance->funds($member, $actor);

        expect($confirmed['idempotent'])->toBeFalse()
            ->and($replayed['idempotent'])->toBeTrue()
            ->and($funds['resultSet'])->toMatchArray(['complete' => true, 'transferCount' => 1])
            ->and($funds['positions'][0])->toMatchArray([
                'currency' => 'USD',
                'received' => '250.00',
                'pendingConfirmation' => '0.00',
                'cashBalance' => '250.00',
            ])
            ->and(DB::table('system_audit_logs')->where('action', 'historical_award_funding_backfilled')->count())->toBe(1);

        DB::table('attp_consortium_think_tanks')->insert([
            'id' => '60000000-0000-4000-8000-000000000007',
            'consortium_id' => '60000000-0000-4000-8000-000000000008',
            'vendor_user_id' => $vendorId,
            'name' => 'Ambiguous duplicate tenant',
            'status' => 'active',
        ]);
        $ambiguous = $backfill->preview();
        expect($ambiguous['ready'])->toBe(0)
            ->and($ambiguous['errors'][0])->toContain('requires exactly one vendor_user_id membership match')
            ->and(fn () => $backfill->apply(1, $ambiguous['planHash']))->toThrow(RuntimeException::class)
            ->and(DB::table('attp_fund_allocations')->count())->toBe(1)
            ->and(DB::table('system_audit_logs')->where('action', 'historical_award_funding_backfilled')->count())->toBe(1);
        DB::table('attp_consortium_think_tanks')
            ->where('id', '60000000-0000-4000-8000-000000000007')
            ->delete();
        DB::table('attp_consortium_think_tanks')->where('id', $memberId)->update(['status' => 'inactive']);
        $inactive = $backfill->preview();
        expect($inactive['ready'])->toBe(0)
            ->and($inactive['errors'][0])->toContain('inactive think-tank membership')
            ->and(fn () => $backfill->apply(1, $inactive['planHash']))->toThrow(RuntimeException::class)
            ->and(DB::table('attp_fund_allocations')->count())->toBe(1);
    } finally {
        Carbon::setTestNow();
        DB::purge($connectionName);
        config([
            'database.default' => $originalConnection,
            'database.connections.'.$connectionName => $originalDefinition,
            'app.key' => $originalKey,
        ]);
        Mockery::close();
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
})->skip(! extension_loaded('pdo_sqlite'), 'Enable pdo_sqlite to run isolated historical funding backfill tests.');

it('fulfills an approved portal request from its existing award without duplicating the commitment', function () {
    [$application, $bootedHere] = bootThinkTankFinanceApiApplication();
    $connectionName = 'think_tank_historical_award_fulfillment_test';
    $originalConnection = config('database.default');
    $originalDefinition = config('database.connections.'.$connectionName);
    Carbon::setTestNow('2026-10-05 12:00:00');
    config([
        'database.default' => $connectionName,
        'database.connections.'.$connectionName => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
    ]);

    try {
        $schema = DB::connection($connectionName)->getSchemaBuilder();
        $source = createThinkTankFundingSourceFixture();
        $schema->create('attp_consortia', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
        });
        $schema->create('attp_consortium_think_tanks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('consortium_id');
            $table->uuid('portal_user_id')->nullable();
            $table->uuid('vendor_user_id')->nullable();
            $table->string('name');
            $table->string('status');
            $table->timestamps();
        });
        $schema->create('procurement_purchase_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('procurement_id')->nullable();
            $table->uuid('invoice_id')->nullable();
            $table->uuid('budget_commitment_id')->nullable();
            $table->uuid('sub_activity_id')->nullable();
            $table->uuid('governance_node_id')->nullable();
            $table->uuid('consortium_id')->nullable();
            $table->uuid('think_tank_member_id')->nullable();
            $table->uuid('vendor_id')->nullable();
            $table->string('reference_no')->nullable();
            $table->string('po_type')->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->string('status');
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });
        $schema->create('procurement_disbursements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('purchase_order_id');
            $table->uuid('procurement_id')->nullable();
            $table->uuid('vendor_id')->nullable();
            $table->uuid('sub_activity_id')->nullable();
            $table->uuid('governance_node_id')->nullable();
            $table->uuid('consortium_id')->nullable();
            $table->uuid('think_tank_member_id')->nullable();
            $table->uuid('fund_allocation_id')->nullable();
            $table->uuid('consortium_disbursement_request_id')->nullable();
            $table->string('reference_no')->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->string('payment_method')->nullable();
            $table->string('transfer_reference')->nullable();
            $table->string('status');
            $table->string('recipient_confirmation_status')->nullable();
            $table->string('secretariat_idempotency_key', 100)->nullable()->unique();
            $table->string('secretariat_idempotency_fingerprint', 64)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        $schema->create('attp_fund_allocations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('consortium_id');
            $table->uuid('think_tank_member_id');
            $table->uuid('program_funding_id');
            $table->uuid('source_purchase_order_id')->nullable()->unique();
            $table->string('budget_line');
            $table->string('currency', 3);
            $table->decimal('amount_allocated', 18, 2);
            $table->decimal('amount_committed', 18, 2);
            $table->decimal('amount_disbursed', 18, 2);
            $table->decimal('amount_spent', 18, 2)->default(0);
            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        $schema->create('attp_disbursement_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('consortium_id');
            $table->uuid('think_tank_member_id');
            $table->uuid('fund_allocation_id')->nullable();
            $table->string('request_code');
            $table->decimal('amount_requested', 18, 2);
            $table->decimal('amount_approved', 18, 2)->default(0);
            $table->string('currency', 3);
            $table->string('status');
            $table->text('purpose')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('portal_lock_version')->default(1);
            $table->timestamps();
        });
        $schema->create('myb_purchase_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
        });
        $schema->create('procurement_invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
        });
        $schema->create('system_audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('module')->nullable();
            $table->string('action')->nullable();
            $table->string('action_message')->nullable();
            $table->text('description')->nullable();
            $table->string('method')->nullable();
            $table->string('url')->nullable();
            $table->string('route_name')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('country')->nullable();
            $table->text('user_agent')->nullable();
            $table->integer('status_code')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        $consortiumId = '65000000-0000-4000-8000-000000000001';
        $memberId = '65000000-0000-4000-8000-000000000002';
        $vendorId = '65000000-0000-4000-8000-000000000003';
        $orderId = '65000000-0000-4000-8000-000000000004';
        $allocationId = '65000000-0000-4000-8000-000000000005';
        $requestId = '65000000-0000-4000-8000-000000000006';
        DB::table('attp_consortia')->insert(['id' => $consortiumId, 'name' => 'Consortium A']);
        DB::table('attp_consortium_think_tanks')->insert([
            'id' => $memberId,
            'consortium_id' => $consortiumId,
            'vendor_user_id' => $vendorId,
            'name' => 'Award tenant',
            'status' => 'active',
        ]);
        DB::table('procurement_purchase_orders')->insert([
            'id' => $orderId,
            'budget_commitment_id' => $source['commitment'],
            'sub_activity_id' => $source['subActivity'],
            'consortium_id' => $consortiumId,
            'think_tank_member_id' => $memberId,
            'vendor_id' => $vendorId,
            'reference_no' => 'AUC-TK-AWARD-001',
            'po_type' => 'procurement',
            'amount' => '1000.00',
            'currency' => 'USD',
            'status' => 'partial_paid',
            'issued_at' => '2026-01-01 00:00:00',
        ]);
        DB::table('attp_fund_allocations')->insert([
            'id' => $allocationId,
            'consortium_id' => $consortiumId,
            'think_tank_member_id' => $memberId,
            'program_funding_id' => $source['funding'],
            'source_purchase_order_id' => $orderId,
            'budget_line' => 'Historical award',
            'currency' => 'USD',
            'amount_allocated' => '1000.00',
            'amount_committed' => '1000.00',
            'amount_disbursed' => '250.00',
            'status' => 'active',
        ]);
        DB::table('procurement_disbursements')->insert([
            'id' => '65000000-0000-4000-8000-000000000007',
            'purchase_order_id' => $orderId,
            'vendor_id' => $vendorId,
            'sub_activity_id' => $source['subActivity'],
            'consortium_id' => $consortiumId,
            'think_tank_member_id' => $memberId,
            'fund_allocation_id' => $allocationId,
            'reference_no' => 'PAY-AWARD-001',
            'amount' => '250.00',
            'currency' => 'USD',
            'status' => 'fully_paid',
            'recipient_confirmation_status' => 'confirmed',
            'paid_at' => '2026-02-01 00:00:00',
        ]);
        DB::table('attp_disbursement_requests')->insert([
            'id' => $requestId,
            'consortium_id' => $consortiumId,
            'think_tank_member_id' => $memberId,
            'fund_allocation_id' => $allocationId,
            'request_code' => 'TT-FR-2026-001',
            'amount_requested' => '100.00',
            'amount_approved' => '100.00',
            'currency' => 'USD',
            'status' => 'approved',
            'purpose' => 'Second approved award tranche',
            'requested_at' => '2026-09-01 00:00:00',
            'portal_lock_version' => 2,
        ]);

        $controller = new AdminThinkTankController(
            Mockery::mock(ThinkTankUserManagementService::class),
            Mockery::mock(ThinkTankInvitationService::class),
        );
        $payload = [
            'think_tank_member_id' => $memberId,
            'funding_request_id' => $requestId,
            'idempotency_key' => 'award-request-650001',
            'amount' => '100.00',
            'currency' => 'USD',
            'payment_method' => 'Bank transfer',
            'transfer_reference' => 'BANK-AWARD-002',
            'paid_at' => '2026-10-05 09:00:00',
            'notes' => 'Approved second tranche.',
        ];
        $httpRequest = Request::create('/think-tanks-admin/funding', 'POST', $payload);
        $controller->storeFunding($httpRequest);
        $controller->storeFunding(Request::create('/think-tanks-admin/funding', 'POST', $payload));

        $linkedPayments = DB::table('procurement_disbursements')
            ->where('consortium_disbursement_request_id', $requestId)
            ->get();
        expect($linkedPayments)->toHaveCount(1)
            ->and($linkedPayments->first()->purchase_order_id)->toBe($orderId)
            ->and($linkedPayments->first()->fund_allocation_id)->toBe($allocationId)
            ->and($linkedPayments->first()->amount)->toBe(100)
            ->and(DB::table('procurement_purchase_orders')->count())->toBe(1)
            ->and(DB::table('myb_budget_commitments')->count())->toBe(1)
            ->and(DB::table('myb_purchase_requests')->count())->toBe(0)
            ->and(DB::table('procurement_invoices')->count())->toBe(0)
            ->and(DB::table('attp_fund_allocations')->count())->toBe(1)
            ->and(DB::table('attp_fund_allocations')->where('id', $allocationId)->value('amount_disbursed'))->toBe(350)
            ->and(DB::table('attp_disbursement_requests')->where('id', $requestId)->value('status'))->toBe('paid')
            ->and(DB::table('attp_disbursement_requests')->where('id', $requestId)->value('amount_approved'))->toBe(100)
            ->and(DB::table('procurement_purchase_orders')->where('id', $orderId)->value('po_type'))->toBe('procurement')
            ->and(DB::table('procurement_purchase_orders')->where('id', $orderId)->value('status'))->toBe('partial_paid');
    } finally {
        Carbon::setTestNow();
        DB::purge($connectionName);
        config([
            'database.default' => $originalConnection,
            'database.connections.'.$connectionName => $originalDefinition,
        ]);
        Mockery::close();
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
})->skip(! extension_loaded('pdo_sqlite'), 'Enable pdo_sqlite to run isolated historical award fulfillment tests.');

it('applies and rolls back the additive finance schema it owns', function () {
    [$application, $bootedHere] = bootThinkTankFinanceApiApplication();
    $connectionName = 'think_tank_finance_migration_test';
    $originalConnection = config('database.default');
    $originalDefinition = config('database.connections.'.$connectionName);
    config([
        'database.default' => $connectionName,
        'database.connections.'.$connectionName => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
    ]);

    try {
        $schema = DB::connection($connectionName)->getSchemaBuilder();
        foreach ([
            'attp_consortia',
            'attp_consortium_think_tanks',
            'attp_fund_allocations',
            'attp_think_tank_procurement_items',
            'procurement_purchase_orders',
            'users',
        ] as $table) {
            $schema->create($table, fn (Blueprint $blueprint) => $blueprint->uuid('id')->primary());
        }
        $schema->create('attp_disbursement_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('think_tank_member_id')->nullable();
        });
        $schema->create('procurement_disbursements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
        });

        $migration = require dirname(__DIR__, 2).'/database/migrations/2026_10_05_000001_create_think_tank_finance_budget_lines.php';
        $migration->up();

        expect($schema->hasTable('attp_think_tank_budget_lines'))->toBeTrue()
            ->and($schema->hasColumns('attp_disbursement_requests', [
                'portal_idempotency_key',
                'portal_idempotency_fingerprint',
                'portal_lock_version',
            ]))->toBeTrue();
        expect($schema->hasColumns('procurement_disbursements', [
            'secretariat_idempotency_key',
            'secretariat_idempotency_fingerprint',
        ]))->toBeTrue()
            ->and($schema->hasColumn('attp_fund_allocations', 'source_purchase_order_id'))->toBeTrue();

        $migration->down();
        expect($schema->hasTable('attp_think_tank_budget_lines'))->toBeFalse()
            ->and($schema->hasColumn('attp_disbursement_requests', 'portal_idempotency_key'))->toBeFalse()
            ->and($schema->hasColumn('attp_fund_allocations', 'source_purchase_order_id'))->toBeFalse()
            ->and($schema->hasColumn('procurement_disbursements', 'secretariat_idempotency_key'))->toBeFalse();
    } finally {
        DB::purge($connectionName);
        config([
            'database.default' => $originalConnection,
            'database.connections.'.$connectionName => $originalDefinition,
        ]);
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
})->skip(! extension_loaded('pdo_sqlite'), 'Enable pdo_sqlite to run isolated finance migration tests.');

it('keeps Secretariat analysis on exact funding records and current accounting cutoffs', function () {
    $root = dirname(__DIR__, 2);
    $admin = file_get_contents($root.'/app/Http/Controllers/AdminThinkTankController.php');
    $consortium = file_get_contents($root.'/app/Http/Controllers/ConsortiumOperationsController.php');
    $portal = file_get_contents($root.'/app/Http/Controllers/ThinkTankPortalController.php');
    preg_match(
        '/private function directoryFinanceTotals\(.*?private function paidDisbursements\(/s',
        $admin,
        $directoryFinance,
    );

    expect($directoryFinance[0] ?? '')
        ->toContain('incomingPurchaseOrdersQuery()')
        ->toContain('incomingPaymentsQuery()')
        ->not->toContain("orWhereIn('vendor_id'")
        ->not->toContain('portal_user_id')
        ->and($admin)
        ->toContain('currentPurchaseOrders')
        ->toContain("->whereNull('issued_at')")
        ->toContain("->where('created_at', '<=', now())")
        ->toContain("->where('paid_at', '<=', now())")
        ->toContain("->whereNotNull('recipient_confirmed_at')")
        ->toContain("->where('recipient_confirmed_at', '<=', now())")
        ->and($consortium)
        ->toContain('currentFundingPurchaseOrders')
        ->toContain("->whereNull('issued_at')")
        ->toContain("->where('created_at', '<=', now())")
        ->toContain("->where('paid_at', '<=', now())")
        ->toContain('confirmedTransferDisbursements')
        ->toContain("->whereNotNull('recipient_confirmed_at')")
        ->toContain("->where('recipient_confirmed_at', '<=', now())")
        ->and($portal)
        ->toContain('currentFundingPurchaseOrders')
        ->toContain('paidFundingDisbursements')
        ->toContain('confirmedFundingDisbursements')
        ->toContain("->whereNull('issued_at')")
        ->toContain("->where('created_at', '<=', now())")
        ->toContain("->where('paid_at', '<=', now())")
        ->toContain("->whereNotNull('recipient_confirmed_at')")
        ->toContain("->where('recipient_confirmed_at', '<=', now())");
});

it('keeps source ownership and accounting mutations fail closed in implementation', function () {
    $root = dirname(__DIR__, 2);
    $finance = file_get_contents($root.'/app/Services/ThinkTankFinanceApiService.php');
    $controller = file_get_contents($root.'/app/Http/Controllers/Api/V1/ThinkTank/FinanceController.php');
    $receipt = file_get_contents($root.'/app/Services/ThinkTankFundingReceiptService.php');
    $legacy = file_get_contents($root.'/app/Http/Controllers/ThinkTankPortalController.php');
    $admin = file_get_contents($root.'/app/Http/Controllers/AdminThinkTankController.php');
    $consortium = file_get_contents($root.'/app/Http/Controllers/ConsortiumOperationsController.php');
    $disbursementModel = file_get_contents($root.'/app/Models/ProcurementDisbursement.php');
    $routes = file_get_contents($root.'/routes/web.php');
    $migration = file_get_contents($root.'/database/migrations/2026_10_05_000001_create_think_tank_finance_budget_lines.php');
    $fundingSource = file_get_contents($root.'/app/Services/ThinkTankFundingSourceService.php');
    $backfill = file_get_contents($root.'/app/Services/ThinkTankHistoricalFundingBackfillService.php');
    preg_match('/public function funds\(.*?public function fundingRequest\(/s', $controller, $fundsMethod);
    preg_match('/public function updateFundingTransfer\(.*?private function/s', $admin, $updateTransferMethod);

    expect($finance)->toContain('recognizedPayment()')
        ->toContain("where('think_tank_member_id', \$member->id)")
        ->toContain("where('consortium_id', \$member->consortium_id)")
        ->toContain('incomingPaymentsQuery($member)')
        ->toContain('incomingPurchaseOrdersQuery($member)')
        ->toContain('lockForUpdate()')
        ->toContain('portal_idempotency_fingerprint')
        ->toContain("'date' => \$row->recipient_confirmed_at?->toDateString()")
        ->toContain('max($disbursed, $paidRequests) + $outstandingRequests')
        ->toContain('Active top-level budget lines cannot exceed confirmed USD receipts.')
        ->toContain('Active top-level lines for this fund allocation cannot exceed its confirmed USD receipts.')
        ->toContain('A child budget line must inherit the same fund allocation as its parent.')
        ->toContain('A budget line that funds a procurement item, directly or through a child, cannot be closed.')
        ->toContain("'complete' => true")
        ->and($receipt)->toContain("if (\$transfer->recipient_confirmation_status === 'confirmed')")
        ->toContain('$this->finance->assertLockToken($transfer, $lockToken)')
        ->not->toContain('ProcurementInvoice')
        ->not->toContain("'invoice_id'")
        ->and($legacy)->toContain('ThinkTankFundingReceiptService $receipts')
        ->not->toContain("logger()->info('Think tank transfer PO notes'")
        ->and($routes)->not->toContain("->post('/purchase-orders', 'storePurchaseOrder')")
        ->and($fundsMethod[0] ?? '')->not->toContain("'page'")->not->toContain("'per_page'")
        ->and($migration)->toContain('attp_tt_budget_member_proc_item_uq')
        ->toContain('attp_disb_req_member_idempotency_uq')
        ->toContain('proc_disb_secretariat_idempotency_uq')
        ->and($admin)->toContain("'funding_request_id' => 'nullable|uuid|exists:attp_disbursement_requests,id'")
        ->toContain("->where('status', 'approved')")
        ->toContain("'secretariat_idempotency_key' => \$data['idempotency_key']")
        ->toContain('source_purchase_order_id')
        ->toContain('A portal request against a backfilled award')
        ->toContain("\$allocation->update(['source_purchase_order_id' => \$purchaseOrder->id])")
        ->toContain('A posted funding transfer amount is immutable.')
        ->not->toContain("'amount_requested' => \$newAmount")
        ->and($updateTransferMethod[0] ?? '')->not->toContain('secretariat_idempotency_fingerprint')
        ->and($disbursementModel)->toContain("->where(\"{\$table}.paid_at\", '<=', now())")
        ->and($consortium)->toContain("Rule::exists('attp_fund_allocations', 'id')")
        ->toContain("->where('consortium_id', \$consortium->id)")
        ->and($fundingSource)->toContain("PROGRAM_CODE = 'PROG00001'")
        ->toContain("COMPONENT_CODE = 'PROG00001-02'")
        ->toContain("SUB_ACTIVITY_NAME = 'Funding to Think Tanks'")
        ->toContain('incoming_award_allocations.source_purchase_order_id')
        ->toContain('incoming_payment_allocations.source_purchase_order_id')
        ->and($backfill)->toContain('planHash')
        ->toContain('source_purchase_order_id');
});
