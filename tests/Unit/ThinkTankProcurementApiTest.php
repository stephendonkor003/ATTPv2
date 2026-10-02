<?php

use App\Exceptions\ThinkTankApiException;
use App\Http\Controllers\Api\V1\ThinkTank\ProcurementController;
use App\Models\ConsortiumThinkTank;
use App\Models\ProcurementDisbursement;
use App\Models\ProcurementPurchaseOrder;
use App\Models\ThinkTankProcurementItem;
use App\Models\ThinkTankProcurementPlan;
use App\Models\User;
use App\Services\ThinkTankProcurementApiService;
use App\Services\ThinkTankProcurementWorkflowService;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Router;
use Illuminate\Validation\ValidationException;

function bootThinkTankProcurementApiApplication(): array
{
    if (Container::getInstance()->bound(Kernel::class)) {
        return [Container::getInstance(), false];
    }

    $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();

    return [$application, true];
}

function procurementItemRequest(array $overrides = []): Request
{
    return Request::create('/api/v1/think-tank/procurement/plans/plan/items', 'POST', [
        'lock_token' => str_repeat('a', 64),
        'title' => 'Independent audit services',
        'review_type' => 'Prior',
        'procurement_category' => 'consulting_services',
        'market_approach' => 'Open - International',
        'procurement_method' => 'QCBS/FBS/LCS',
        'estimated_amount_usd' => '10000.00',
        'source_document_type' => 'Terms of Reference',
        'source_process_status' => 'Planned',
        ...$overrides,
    ], [], [], ['CONTENT_TYPE' => 'multipart/form-data; boundary=attp-procurement-test']);
}

it('uses an exact per-item USD threshold and keeps unknown currency out of USD totals', function () {
    $api = new ThinkTankProcurementApiService;

    expect($api->thresholdBand('9999.99', 'usd'))->toMatchArray([
        'code' => ThinkTankProcurementApiService::BAND_BELOW,
        'amountUsd' => 9999.99,
        'isComparable' => true,
    ])->and($api->thresholdBand('10000.00', 'USD'))->toMatchArray([
        'code' => ThinkTankProcurementApiService::BAND_AT_OR_ABOVE,
        'amountUsd' => 10000.0,
        'isComparable' => true,
    ])->and($api->thresholdBand('12000', 'EUR'))->toMatchArray([
        'code' => ThinkTankProcurementApiService::BAND_CURRENCY_REVIEW,
        'amountUsd' => null,
        'isComparable' => false,
    ])->and($api->thresholdBand('12000', null))->toMatchArray([
        'code' => ThinkTankProcurementApiService::BAND_CURRENCY_REVIEW,
        'amountUsd' => null,
        'isComparable' => false,
    ])->and($api->thresholdBand('12000', '   '))->toMatchArray([
        'code' => ThinkTankProcurementApiService::BAND_CURRENCY_REVIEW,
        'amountUsd' => null,
        'isComparable' => false,
    ]);
});

it('keeps currency review rows visible while filtering the report to a USD band', function () {
    $api = new ThinkTankProcurementApiService;
    $plan = (new ThinkTankProcurementPlan)->forceFill([
        'id' => '10000000-0000-4000-8000-000000000001',
        'plan_code' => 'TT-PP-2026-0001',
        'title' => '2026 annual plan',
        'fiscal_year' => '2026',
        'currency' => 'USD',
        'status' => ThinkTankProcurementPlan::STATUS_DRAFT,
        'portal_lock_version' => 1,
    ]);
    $below = (new ThinkTankProcurementItem)->forceFill([
        'id' => '10000000-0000-4000-8000-000000000002',
        'plan_id' => $plan->id,
        'item_code' => 'TT-PP-2026-0001-001',
        'title' => 'Below threshold row',
        'estimated_amount' => 9000,
        'currency' => 'USD',
        'status' => ThinkTankProcurementItem::STATUS_DRAFT,
        'source_activity_status' => 'Imported activity status',
    ]);
    $unknown = (new ThinkTankProcurementItem)->forceFill([
        'id' => '10000000-0000-4000-8000-000000000003',
        'plan_id' => $plan->id,
        'item_code' => 'TT-PP-2026-0001-002',
        'title' => 'Unknown currency row',
        'estimated_amount' => 50000,
        'currency' => null,
        'status' => ThinkTankProcurementItem::STATUS_APPROVED,
    ]);
    $plan->setRelation('items', new EloquentCollection([$below, $unknown]));
    $legacyCurrencyPlan = (new ThinkTankProcurementPlan)->forceFill([
        'id' => '10000000-0000-4000-8000-000000000006',
        'plan_code' => 'TT-PP-2024-0001',
        'title' => 'Legacy EUR plan',
        'fiscal_year' => '2024',
        'currency' => 'EUR',
        'status' => ThinkTankProcurementPlan::STATUS_APPROVED,
    ]);
    $usdItemOnLegacyPlan = (new ThinkTankProcurementItem)->forceFill([
        'id' => '10000000-0000-4000-8000-000000000007',
        'plan_id' => $legacyCurrencyPlan->id,
        'item_code' => 'TT-PP-2024-0001-001',
        'title' => 'USD item under EUR plan',
        'estimated_amount' => 8000,
        'currency' => 'USD',
        'status' => ThinkTankProcurementItem::STATUS_APPROVED,
    ]);
    $legacyCurrencyPlan->setRelation('items', new EloquentCollection([$usdItemOnLegacyPlan]));
    $member = (new ConsortiumThinkTank)->forceFill(['id' => '10000000-0000-4000-8000-000000000004']);
    $viewer = new class extends User
    {
        public function hasPermission(string $permission): bool
        {
            return in_array($permission, [
                'think_tank.procurement_plans.view',
                'think_tank.procurement_plans.manage',
            ], true);
        }
    };

    $payload = $api->usageReport($member, collect([$plan, $legacyCurrencyPlan]), $viewer, [
        'band' => ThinkTankProcurementApiService::BAND_BELOW,
    ]);
    $legacyPlan = (new ThinkTankProcurementPlan)->forceFill([
        'id' => '10000000-0000-4000-8000-000000000005',
        'plan_code' => 'TT-PP-2025-0001',
        'title' => 'Imported annual plan',
        'fiscal_year' => '2025',
        'currency' => null,
        'status' => ThinkTankProcurementPlan::STATUS_DRAFT,
    ]);
    $legacyPlan->setRelation('items', new EloquentCollection([$unknown]));
    $legacyCard = $api->planCard($legacyPlan, $viewer, collect());

    expect($payload['summary'])->toMatchArray([
        'plannedUsd' => 9000.0,
        'approvedUsd' => 0.0,
        'itemCount' => 1,
        'currencyReviewCount' => 2,
    ])->and($payload['rows'])->toHaveCount(1)
        ->and($payload['rows'][0]['band'])->toBe(ThinkTankProcurementApiService::BAND_BELOW)
        ->and($payload['rows'][0]['activityStatus'])->toBe('Imported activity status')
        ->and($payload['currencyReview'])->toHaveCount(2)
        ->and($payload['currencyReview'][0])->toMatchArray([
            'id' => $unknown->id,
            'currency' => 'UNKNOWN',
            'estimatedAmountUsd' => null,
            'plannedUsd' => 0.0,
            'approvedUsd' => 0.0,
            'band' => ThinkTankProcurementApiService::BAND_CURRENCY_REVIEW,
        ])->and($payload['currencyReview'][0]['currencyReviewContexts']['item'])->toBe([
            'label' => 'TT-PP-2026-0001-002',
            'currency' => 'UNKNOWN',
        ])->and($payload['currencyReview'][1])->toMatchArray([
            'id' => $usdItemOnLegacyPlan->id,
            'plannedUsd' => 0.0,
            'approvedUsd' => 0.0,
            'committedUsd' => 0.0,
            'paidUsd' => 0.0,
            'band' => ThinkTankProcurementApiService::BAND_CURRENCY_REVIEW,
        ])->and($payload['currencyReview'][1]['currencyReviewContexts']['plan'])->toBe([
            'label' => 'TT-PP-2024-0001',
            'currency' => 'EUR',
        ])->and($legacyCard)->toMatchArray([
            'currency' => 'UNKNOWN',
            'canEdit' => false,
            'canSubmit' => false,
        ]);
});

it('uses explicit transaction currency before inherited financial currency', function () {
    $api = new ThinkTankProcurementApiService;
    $orderCurrency = new ReflectionMethod($api, 'strictPurchaseOrderCurrency');
    $paymentCurrency = new ReflectionMethod($api, 'strictPaymentCurrency');
    $usdOrder = (new ProcurementPurchaseOrder)->forceFill(['currency' => 'USD']);
    $usdOrder->syncOriginal();
    $usdOrder->setRelation('purchaseRequest', null);
    $usdOrder->setRelation('budgetCommitment', null);
    $eurOrder = (new ProcurementPurchaseOrder)->forceFill(['currency' => 'eur']);
    $eurOrder->syncOriginal();
    $eurOrder->setRelation('purchaseRequest', null);
    $eurOrder->setRelation('budgetCommitment', null);
    $eurPayment = (new ProcurementDisbursement)->forceFill(['currency' => 'EUR']);
    $eurPayment->syncOriginal();
    $eurPayment->setRelation('purchaseOrder', $usdOrder);
    $inheritedPayment = (new ProcurementDisbursement)->forceFill(['currency' => null]);
    $inheritedPayment->syncOriginal();
    $inheritedPayment->setRelation('purchaseOrder', $usdOrder);

    expect($orderCurrency->invoke($api, $eurOrder))->toBe('EUR')
        ->and($paymentCurrency->invoke($api, $eurPayment))->toBe('EUR')
        ->and($paymentCurrency->invoke($api, $inheritedPayment))->toBe('USD');
});

it('issues state-bound HMAC locks and rejects stale procurement writes', function () {
    [, $bootedHere] = bootThinkTankProcurementApiApplication();
    $originalKey = config('app.key');
    config(['app.key' => 'base64:procurement-api-test-key']);

    try {
        $api = new ThinkTankProcurementApiService;
        $plan = (new ThinkTankProcurementPlan)->forceFill([
            'id' => '10000000-0000-4000-8000-000000000010',
            'portal_lock_version' => 1,
            'updated_at' => '2026-10-01 08:00:00',
        ]);
        $token = $api->lockToken($plan);
        $plan->updated_at = '2026-10-01 09:00:00';
        $changedStateToken = $api->lockToken($plan);
        $plan->portal_lock_version = 2;
        $nextToken = $api->lockToken($plan);

        expect($token)->toHaveLength(64)
            ->and($changedStateToken)->not->toBe($token)
            ->and($nextToken)->not->toBe($token)
            ->and(fn () => $api->assertLockToken($plan, $token))->toThrow(ThinkTankApiException::class)
            ->and(fn () => $api->assertLockToken($plan, $changedStateToken))->toThrow(ThinkTankApiException::class)
            ->and(fn () => $api->assertLockToken($plan, $nextToken))->not->toThrow(Throwable::class);
    } finally {
        config(['app.key' => $originalKey]);

        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('matches every workbook method template and preserves actual-only milestones', function () {
    $api = new ThinkTankProcurementApiService;
    $templates = collect($api->methodTemplates())->keyBy('code');
    $expected = [
        'rfq' => [
            'draft_request_for_quotations', 'specific_procurement_notice', 'invitation_to_supplier_contractor',
            'amendments_to_request_for_quotations', 'receive_quotations', 'comparison_of_quotations',
            'notification_of_intention_of_award', 'signed_contract', 'contract_amendments',
            'contract_completion', 'contract_termination',
        ],
        'qcbs_fbs_lcs' => [
            'tor', 'eoi', 'eoi_evaluation_and_shortlist', 'shortlist_and_draft_rfp', 'rfp_as_issued',
            'rfp_amendments', 'technical_proposals_opening_minutes', 'technical_evaluation',
            'financial_proposals_opening_minutes', 'combined_evaluation_and_draft_negotiated_contract',
            'notification_of_intention_of_award', 'signed_contract', 'contract_amendments',
            'contract_completion', 'contract_termination',
        ],
        'cqs' => [
            'tor', 'eoi', 'eoi_evaluation_and_shortlist', 'shortlist_and_draft_rfp',
            'draft_negotiated_contract', 'notification_of_intention_of_award', 'signed_contract',
            'contract_amendments', 'contract_completion', 'contract_termination',
        ],
        'cds' => [
            'tor', 'justification_for_direct_selection', 'invitation_to_selected_consultant', 'amendments_to_tor',
            'draft_negotiated_contract', 'notification_of_intention_of_award', 'signed_contract',
            'contract_amendments', 'contract_completion', 'contract_termination',
        ],
        'indv' => [
            'tor', 'eoi', 'eoi_evaluation_and_shortlist', 'justification_for_direct_selection',
            'invitation_to_selected_consultant', 'draft_negotiated_contract',
            'notification_of_intention_of_award', 'signed_contract', 'contract_amendments',
            'contract_completion', 'contract_termination',
        ],
        'direct_goods' => [
            'justification_for_direct_procurement', 'invitation_to_supplier_contractor', 'draft_contract',
            'notification_of_intention_of_award', 'signed_contract', 'contract_amendments', 'contract_completion',
        ],
    ];

    foreach ($expected as $method => $keys) {
        expect(collect($templates->get($method)['milestones'])->pluck('key')->all())->toBe($keys);
    }

    $merged = $api->mergePlannedMilestones([
        ['key' => 'signed_contract', 'timing' => 'planned', 'date' => '2026-06-01'],
        ['key' => 'contract_amendments', 'timing' => 'actual', 'date' => '2026-08-01'],
        ['milestone' => 'Imported custom stage', 'timing' => 'actual', 'date' => '2026-09-01'],
    ], 'rfq', [
        'signed_contract' => '2026-07-01',
        'contract_amendments' => '2026-07-15',
    ]);

    expect($api->methodCode('QCBS / FBS / LCS'))->toBe('qcbs_fbs_lcs')
        ->and($api->methodCode('QCBS/FBS/LCS'))->toBe('qcbs_fbs_lcs')
        ->and($api->methodCode('direct goods', 'GoOdS'))->toBe('direct_goods')
        ->and($api->methodCode('Direct Selection', 'services'))->toBeNull()
        ->and(collect($templates->get('cds')['milestones'])->pluck('key'))->toContain('contract_amendments')
        ->not->toContain('contract_amments')
        ->and(collect($templates->get('rfq')['milestones'])->firstWhere('key', 'contract_amendments')['plannedWritable'])->toBeFalse()
        ->and(collect($merged)->where('key', 'signed_contract')->where('timing', 'planned')->pluck('date')->all())->toBe(['2026-07-01'])
        ->and(collect($merged)->where('key', 'contract_amendments')->where('timing', 'planned'))->toBeEmpty()
        ->and(collect($merged)->where('key', 'contract_amendments')->where('timing', 'actual')->pluck('date')->all())->toBe(['2026-08-01'])
        ->and(collect($merged)->where('milestone', 'Imported custom stage'))->toHaveCount(1);
});

it('accepts the workbook estimate directly and rejects a conflicting optional unit breakdown', function () {
    [, $bootedHere] = bootThinkTankProcurementApiApplication();
    $controller = new ProcurementController(
        new ThinkTankProcurementApiService,
        new ThinkTankProcurementWorkflowService,
    );
    $validate = new ReflectionMethod($controller, 'validateItem');
    $amount = new ReflectionMethod($controller, 'calculatedAmount');

    try {
        $direct = $validate->invoke($controller, procurementItemRequest());
        $matching = $validate->invoke($controller, procurementItemRequest([
            'quantity' => '4',
            'estimated_unit_cost' => '2500',
            'planned_milestones' => [
                ['key' => 'signed_contract', 'plannedDate' => '2026-08-01'],
                ['key' => 'contract_amendments', 'plannedDate' => '2026-09-01'],
            ],
        ]));

        expect($direct)->not->toHaveKeys(['quantity', 'estimated_unit_cost'])
            ->and($amount->invoke($controller, $direct))->toBe(10000.0)
            ->and($matching['planned_milestones'])->toBe([
                ['key' => 'signed_contract', 'plannedDate' => '2026-08-01'],
            ])
            ->and(fn () => $validate->invoke($controller, procurementItemRequest([
                'quantity' => '3',
                'estimated_unit_cost' => '2500',
            ])))->toThrow(ValidationException::class)
            ->and(fn () => $validate->invoke($controller, procurementItemRequest([
                'estimated_amount_usd' => '9999.999',
            ])))->toThrow(ValidationException::class)
            ->and(fn () => $validate->invoke($controller, procurementItemRequest([
                'source_in_process' => 'Maybe',
            ])))->toThrow(ValidationException::class);
    } finally {
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('accepts repeatable TOR files with bounded combined procurement uploads', function () {
    [, $bootedHere] = bootThinkTankProcurementApiApplication();
    $controller = new ProcurementController(
        new ThinkTankProcurementApiService,
        new ThinkTankProcurementWorkflowService,
    );
    $validate = new ReflectionMethod($controller, 'validateItem');

    try {
        $repeatable = procurementItemRequest();
        $repeatable->files->set('tor_documents', [
            UploadedFile::fake()->create('tor-main.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('tor-annex.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ]);
        $validated = $validate->invoke($controller, $repeatable);

        $tooManyTor = procurementItemRequest();
        $tooManyTor->files->set('tor_documents', collect(range(1, 21))
            ->map(fn (int $number) => UploadedFile::fake()->create("tor-{$number}.pdf", 10, 'application/pdf'))
            ->all());

        $combinedOverflow = procurementItemRequest();
        $combinedOverflow->files->set('tor_documents', collect(range(1, 10))
            ->map(fn (int $number) => UploadedFile::fake()->create("tor-{$number}.pdf", 10, 'application/pdf'))
            ->all());
        $combinedOverflow->files->set('supporting_documents', collect(range(1, 11))
            ->map(fn (int $number) => UploadedFile::fake()->create("support-{$number}.pdf", 10, 'application/pdf'))
            ->all());

        $combinedSizeOverflow = procurementItemRequest();
        $combinedSizeOverflow->files->set('supporting_documents', collect(range(1, 4))
            ->map(fn (int $number) => UploadedFile::fake()->create("large-support-{$number}.pdf", 16 * 1024, 'application/pdf'))
            ->all());

        expect($validated['tor_documents'])->toHaveCount(2)
            ->and(fn () => $validate->invoke($controller, $tooManyTor))->toThrow(ValidationException::class)
            ->and(fn () => $validate->invoke($controller, $combinedOverflow))->toThrow(ValidationException::class)
            ->and(fn () => $validate->invoke($controller, $combinedSizeOverflow))->toThrow(ValidationException::class);
    } finally {
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('keeps procurement routes tenant-bound stateful ready permissioned and no-store', function () {
    [$application, $bootedHere] = bootThinkTankProcurementApiApplication();

    try {
        /** @var Router $router */
        $router = $application->make(Router::class);
        $routes = collect($router->getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/think-tank/procurement'));

        // Twelve annual-plan/report routes, seventeen controlled execution
        // routes, and five tenant vendor-directory routes retain one boundary.
        expect($routes)->toHaveCount(34);
        $routes->each(function ($route): void {
            expect($route->gatherMiddleware())
                ->toContain('think.tank.api.no-store')
                ->toContain('think.tank.api.stateful')
                ->toContain('auth:sanctum')
                ->toContain('think.tank.api.account')
                ->toContain('think.tank.api.ready')
                ->toContain('think.tank.area:procurement_plans');
            expect(collect($route->gatherMiddleware())->contains(
                fn (string $middleware): bool => str_contains($middleware, 'think_tank.procurement_plans.')
            ))->toBeTrue();
        });
    } finally {
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('serializes procurement tenant isolation strict inputs and financial rules in source', function () {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents($root.'/app/Http/Controllers/Api/V1/ThinkTank/ProcurementController.php');
    $service = file_get_contents($root.'/app/Services/ThinkTankProcurementApiService.php');
    $migration = file_get_contents($root.'/database/migrations/2026_10_01_000001_add_portal_fields_to_think_tank_procurement.php');
    $statuses = (new ReflectionClass(ThinkTankProcurementApiService::class))
        ->getReflectionConstant('COMMITTED_PURCHASE_ORDER_STATUSES')
        ->getValue();

    expect(substr_count($controller, "where('think_tank_member_id', \$member->id)"))->toBeGreaterThanOrEqual(8)
        ->and($controller)->toContain('->whereKey($plan)')
        ->toContain("where('plan_id', \$lockedPlan->id)")
        ->toContain("where('item_id', \$lockedItem->id)")
        ->toContain('lockForUpdate()')
        ->toContain('JSON_BODY_REQUIRED')
        ->toContain('MULTIPART_BODY_REQUIRED')
        ->toContain("'estimated_amount_usd' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999999999.99']")
        ->toContain("'source_in_process' => ['nullable', Rule::in(['Yes', 'No'])]")
        ->not->toContain("'bank_comment'")
        ->not->toContain("whereDoesntHave('items')")
        ->toContain("Str::upper(trim((string) \$item->currency)) !== 'USD'")
        ->toContain('Resolve every unknown or non-USD item currency before submitting')
        ->and($service)->toContain('recognizedPayment()')
        ->toContain("whereIn('status', self::COMMITTED_PURCHASE_ORDER_STATUSES)")
        ->toContain("'committedUsd' => \$isExplicitUsd ?")
        ->toContain("'paidUsd' => \$isExplicitUsd ?")
        ->toContain("getAttribute('portal_lock_version')")
        ->toContain("increment('portal_lock_version')")
        ->toContain("'key' => 'currency'")
        ->toContain("&& \$rows->where('band', self::BAND_CURRENCY_REVIEW)->isEmpty()")
        ->toContain("'currencyReviewContexts'")
        ->not->toContain("\$item->currency ?: 'USD'")
        ->and(substr_count($controller, "'source_activity_status' => ThinkTankProcurementItem::ACTIVITY_STATUS_DRAFT"))->toBe(1)
        ->and($statuses)->toBe(['issued', 'closed'])
        ->and((new ThinkTankProcurementPlan)->getCasts()['portal_lock_version'])->toBe('integer')
        ->and((new ThinkTankProcurementItem)->getCasts()['portal_lock_version'])->toBe('integer')
        ->and(substr_count($migration, "unsignedBigInteger('portal_lock_version')->default(1)"))->toBe(2)
        ->and($migration)->toContain("text('budget_reference')")
        ->toContain("text('limited_selection_justification')")
        ->toContain("text('bank_comment')")
        ->toContain("text('action_taken')");
});
