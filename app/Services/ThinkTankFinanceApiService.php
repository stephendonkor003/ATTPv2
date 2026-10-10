<?php

namespace App\Services;

use App\Exceptions\ThinkTankApiException;
use App\Models\ConsortiumDisbursementRequest;
use App\Models\ConsortiumFundAllocation;
use App\Models\ConsortiumThinkTank;
use App\Models\ProcurementDisbursement;
use App\Models\ProcurementPurchaseOrder;
use App\Models\ThinkTankBudgetLine;
use App\Models\ThinkTankProcurementItem;
use App\Models\User;
use App\Services\ThinkTank\ThinkTankApiAuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ThinkTankFinanceApiService
{
    private const COMMITMENT_STATUSES = [
        'issued',
        'pending',
        'partial_paid',
        'partially_paid',
        'paid',
        'fully_paid',
        'closed',
    ];

    private const RESERVED_REQUEST_STATUSES = ['submitted', 'under_review', 'approved', 'partially_paid', 'paid'];

    private const OUTSTANDING_REQUEST_STATUSES = ['submitted', 'under_review', 'approved', 'partially_paid'];

    private const TRANSFER_SEARCH_FIELDS = [
        'referenceNumber',
        'purchaseOrderReference',
        'transferReference',
        'description',
    ];

    public function __construct(private readonly ThinkTankApiAuditService $audit) {}

    /** @return array<string, mixed> */
    public function overview(ConsortiumThinkTank $member, User $viewer): array
    {
        $snapshot = $this->snapshot($member);

        return [
            'asOf' => now()->toIso8601String(),
            'tenant' => $this->tenant($member),
            'permissions' => $this->permissions($viewer),
            'positions' => $snapshot['positions'],
            'recentTransfers' => $snapshot['incoming']
                ->sortByDesc(fn (ProcurementDisbursement $payment): int => $payment->paid_at?->getTimestamp() ?? 0)
                ->take(5)
                ->map(fn (ProcurementDisbursement $payment): array => $this->transferResource($payment, $viewer))
                ->values()
                ->all(),
            'counts' => [
                'transfers' => $snapshot['incoming']->count(),
                'fundingRequests' => $snapshot['requests']->count(),
                'pendingConfirmations' => $snapshot['pendingIncoming']->count(),
                'budgetLines' => $snapshot['budgetLines']->count(),
            ],
            'reconciliationWarnings' => $snapshot['warnings'],
        ];
    }

    /** @return array<string, mixed> */
    public function funds(ConsortiumThinkTank $member, User $viewer, array $filters = []): array
    {
        $snapshot = $this->snapshot($member);
        $transfers = $snapshot['incoming']
            ->sortByDesc(fn (ProcurementDisbursement $payment): int => $payment->paid_at?->getTimestamp() ?? 0)
            ->map(fn (ProcurementDisbursement $payment): array => $this->transferResource($payment, $viewer))
            ->values();
        $requests = $snapshot['requests']
            ->sortByDesc(fn (ConsortiumDisbursementRequest $fundingRequest): int => $fundingRequest->requested_at?->getTimestamp() ?? 0)
            ->map(fn (ConsortiumDisbursementRequest $fundingRequest): array => $this->fundingRequestResource($fundingRequest))
            ->values();
        $transfers = $this->filterResources(
            $transfers,
            $filters,
            ['status', 'receiptStatus'],
            self::TRANSFER_SEARCH_FIELDS,
        );
        $requests = $this->filterResources($requests, $filters, ['status'], ['requestCode', 'purpose', 'reviewNotes']);

        return [
            'asOf' => now()->toIso8601String(),
            'tenant' => $this->tenant($member),
            'permissions' => $this->permissions($viewer),
            'allocations' => $snapshot['allocations']
                ->sortBy('budget_line')
                ->map(fn (ConsortiumFundAllocation $allocation): array => $this->fundAllocationResource(
                    $allocation,
                    $snapshot['requests'],
                ))
                ->values()
                ->all(),
            'options' => [
                'fundAllocations' => $snapshot['allocations']
                    ->where('status', 'active')
                    ->sortBy('budget_line')
                    ->map(fn (ConsortiumFundAllocation $allocation): array => $this->fundAllocationResource(
                        $allocation,
                        $snapshot['requests'],
                    ))
                    ->filter(fn (array $allocation): bool => $this->cents($allocation['available']) > 0)
                    ->values()
                    ->all(),
            ],
            'transfers' => $transfers->all(),
            'fundingRequests' => $requests->all(),
            'resultSet' => [
                'complete' => true,
                'transferCount' => $transfers->count(),
                'fundingRequestCount' => $requests->count(),
            ],
            'positions' => $snapshot['positions'],
            'warnings' => $snapshot['warnings'],
        ];
    }

    /** @return array<string, mixed> */
    public function budgetLines(ConsortiumThinkTank $member, User $viewer, array $filters = []): array
    {
        $snapshot = $this->snapshot($member);
        $metrics = $this->procurementMetrics($member);
        $items = $snapshot['budgetLines']
            ->sortBy(fn (ThinkTankBudgetLine $line): string => $line->fiscal_year.'|'.$line->code)
            ->map(fn (ThinkTankBudgetLine $line): array => $this->budgetLineResource(
                $line,
                $snapshot['budgetLines'],
                $metrics,
            ))
            ->values();
        $items = $this->filterResources($items, $filters, ['status'], ['code', 'name', 'description']);
        if (filled($filters['fiscal_year'] ?? null)) {
            $items = $items->where('fiscalYear', (string) $filters['fiscal_year'])->values();
        }
        $page = $this->paginate($items, $filters);

        return [
            'asOf' => now()->toIso8601String(),
            'tenant' => $this->tenant($member),
            'permissions' => $this->permissions($viewer),
            'items' => $page['items'],
            'pagination' => $page['pagination'],
            'options' => [
                'fundAllocations' => $snapshot['allocations']
                    ->where('status', 'active')
                    ->sortBy('budget_line')
                    ->map(fn (ConsortiumFundAllocation $allocation): array => $this->fundAllocationResource(
                        $allocation,
                        $snapshot['requests'],
                    ))
                    ->values()
                    ->all(),
                'procurementItems' => $metrics['items']
                    ->reject(fn (ThinkTankProcurementItem $item): bool => $snapshot['budgetLines']
                        ->contains(fn (ThinkTankBudgetLine $line): bool => (string) $line->procurement_item_id === (string) $item->id
                        ))
                    ->map(fn (ThinkTankProcurementItem $item): array => $this->procurementOptionResource($item))
                    ->values()
                    ->all(),
            ],
            'positions' => $snapshot['positions'],
            'warnings' => $snapshot['warnings'],
        ];
    }

    /** @return array<string, mixed> */
    public function execution(ConsortiumThinkTank $member, User $viewer, array $filters = []): array
    {
        $metrics = $this->procurementMetrics($member);
        $budgetLinesByProcurementItem = ThinkTankBudgetLine::query()
            ->where('think_tank_member_id', $member->id)
            ->where('consortium_id', $member->consortium_id)
            ->whereNotNull('procurement_item_id')
            ->get()
            ->keyBy(fn (ThinkTankBudgetLine $line): string => (string) $line->procurement_item_id);
        $items = $metrics['items']->map(function (ThinkTankProcurementItem $item) use ($budgetLinesByProcurementItem, $metrics): array {
            $procurementId = filled($item->procurement_id) ? (string) $item->procurement_id : null;
            $committed = $procurementId ? (int) ($metrics['committedByProcurement'][$procurementId] ?? 0) : 0;
            $spent = $procurementId ? (int) ($metrics['spentByProcurement'][$procurementId] ?? 0) : 0;
            $stage = $this->executionStage($item, $committed, $spent);
            $budgetLine = $budgetLinesByProcurementItem->get((string) $item->id);

            return [
                'id' => (string) $item->id,
                'planId' => (string) $item->plan_id,
                'planCode' => (string) ($item->plan?->plan_code ?: ''),
                'itemCode' => (string) $item->item_code,
                'title' => (string) $item->title,
                'fiscalYear' => (string) ($item->plan?->fiscal_year ?: ''),
                'currency' => $this->currency($item->currency, $item->plan?->currency),
                'plannedAmount' => $this->money($this->cents($item->estimated_amount)),
                'workflowStatus' => (string) $item->status,
                'workflowStatusLabel' => Str::headline((string) $item->status),
                'stepStatus' => $item->currentStepActivityStatus(),
                'stage' => $stage,
                'stageLabel' => $this->executionStageLabel($stage),
                'procurementId' => $procurementId,
                'committedAmount' => $this->money($committed),
                'spentAmount' => $this->money($spent),
                'outstandingCommitment' => $this->money(max($committed - $spent, 0)),
                'uncommittedAmount' => $this->money(max($this->cents($item->estimated_amount) - $committed, 0)),
                'remainingPlanAmount' => $this->money(max($this->cents($item->estimated_amount) - $spent, 0)),
                ...$this->executionBudgetLineFields(
                    $budgetLine instanceof ThinkTankBudgetLine ? $budgetLine : null
                ),
            ];
        })->values();
        $items = $this->filterResources(
            $items,
            $filters,
            ['stage', 'workflowStatus', 'stepStatus'],
            ['planCode', 'itemCode', 'title'],
        );
        if (filled($filters['fiscal_year'] ?? null)) {
            $items = $items->where('fiscalYear', (string) $filters['fiscal_year'])->values();
        }

        $totals = $items
            ->groupBy('currency')
            ->map(function (Collection $currencyItems, string $currency): array {
                $planned = $this->sumMoneyFields($currencyItems, 'plannedAmount');
                $committed = $this->sumMoneyFields($currencyItems, 'committedAmount');
                $spent = $this->sumMoneyFields($currencyItems, 'spentAmount');
                $stageAmount = fn (string $stage): int => $this->sumMoneyFields(
                    $currencyItems->where('stage', $stage),
                    'plannedAmount',
                );

                return [
                    'currency' => $currency,
                    'planned' => $this->money($planned),
                    'awaitingSecretariat' => $this->money($stageAmount('awaiting_secretariat')),
                    'awaitingWorldBank' => $this->money($stageAmount('awaiting_world_bank')),
                    'approvedForSpending' => $this->money($stageAmount('approved_no_objection')),
                    'plannedInCommittedStage' => $this->money($stageAmount('committed')),
                    'plannedInSpentStage' => $this->money($stageAmount('spent')),
                    'committed' => $this->money($committed),
                    'spent' => $this->money($spent),
                    // Sum non-negative balances item by item. Portfolio-level
                    // max() would let an overrun on one item conceal unused
                    // budget on a different item.
                    'outstandingCommitment' => $this->money(
                        $this->sumMoneyFields($currencyItems, 'outstandingCommitment')
                    ),
                    'uncommitted' => $this->money(
                        $this->sumMoneyFields($currencyItems, 'uncommittedAmount')
                    ),
                    'remainingPlan' => $this->money(
                        $this->sumMoneyFields($currencyItems, 'remainingPlanAmount')
                    ),
                ];
            })
            ->values()
            ->all();

        $warnings = $items
            ->where('currency', 'UNKNOWN')
            ->map(fn (array $item): array => $this->warning(
                'EXECUTION_CURRENCY_UNKNOWN',
                'critical',
                'A procurement execution item has no explicit currency and was not combined with any currency totals.',
                'UNKNOWN',
                $item['id'],
            ))
            ->values()
            ->all();
        $page = $this->paginate($items, $filters);

        return [
            'asOf' => now()->toIso8601String(),
            'tenant' => $this->tenant($member),
            'permissions' => $this->permissions($viewer),
            'items' => $page['items'],
            'pagination' => $page['pagination'],
            'totalsByCurrency' => $totals,
            'warnings' => $warnings,
        ];
    }

    /** @return array{budgetLineId: string|null, budgetLineCode: string|null, budgetLineName: string|null, budgetLine: string|null} */
    private function executionBudgetLineFields(?ThinkTankBudgetLine $line): array
    {
        $code = $line ? trim((string) $line->code) : '';
        $name = $line ? trim((string) $line->name) : '';
        $label = trim(implode(' - ', array_filter([$code, $name], fn (string $value): bool => $value !== '')));

        return [
            'budgetLineId' => $line ? (string) $line->id : null,
            'budgetLineCode' => $code !== '' ? $code : null,
            'budgetLineName' => $name !== '' ? $name : null,
            'budgetLine' => $label !== '' ? $label : null,
        ];
    }

    /** @return array<string, mixed> */
    public function reports(ConsortiumThinkTank $member, User $viewer, array $filters = []): array
    {
        $allPeriodsSnapshot = $this->snapshot($member);
        $period = filled($filters['period'] ?? null) ? (string) $filters['period'] : null;
        $activitySnapshot = $period === null ? $allPeriodsSnapshot : $this->snapshot($member, $period);
        $positionSnapshot = $period === null ? $allPeriodsSnapshot : $this->snapshot($member, $period, true);
        $confirmedIncoming = $activitySnapshot['recognizedIncoming'];
        $activityByCurrency = collect($activitySnapshot['positions'])->keyBy('currency');
        $positionByCurrency = collect($positionSnapshot['positions'])->keyBy('currency');
        $positions = $activityByCurrency->keys()
            ->merge($positionByCurrency->keys())
            ->unique()
            ->sort()
            ->map(fn (string $currency): array => [
                'currency' => $currency,
                'activity' => $activityByCurrency->get($currency, $this->emptyPosition($currency)),
                'position' => $positionByCurrency->get($currency, $this->emptyPosition($currency)),
            ])
            ->values();
        if (filled($filters['currency'] ?? null)) {
            $positions = $positions->where('currency', Str::upper((string) $filters['currency']))->values();
        }
        $reports = $positions->map(function (array $row) use ($activitySnapshot, $confirmedIncoming): array {
            $currency = $row['currency'];
            $activity = $row['activity'];
            $position = $row['position'];
            // Recipient accounting recognizes cash only after confirmation.
            // Paid-but-unconfirmed transfers remain reconciliation items.
            $periodReceipts = $this->cents($activity['received']);
            $periodExpenditure = $this->cents($activity['spent']);
            $periodNetCash = $periodReceipts - $periodExpenditure;
            $cumulativeReceipts = $this->cents($position['received']);
            $cumulativeExpenditure = $this->cents($position['spent']);
            $cash = $cumulativeReceipts - $cumulativeExpenditure;
            $openingCash = $cash - $periodNetCash;
            $cashAsset = max($cash, 0);
            $cashOverdraft = max(-$cash, 0);
            $fundBalance = $cash;
            $totalLiabilitiesAndFundBalance = $cashOverdraft + $fundBalance;
            $commitments = $this->cents($position['committed']);
            $settledCommitments = $this->cents($position['settledCommitments']);
            $outstandingCommitments = $this->cents($position['outstandingCommitments']);
            $ledger = $this->ledger($confirmedIncoming, $activitySnapshot['spending'], $currency);
            $trialAccounts = [
                [
                    'code' => '1000',
                    'name' => 'Cash and cash equivalents',
                    'debit' => $this->money($cashAsset),
                    'credit' => $this->money($cashOverdraft),
                ],
                [
                    'code' => '5000',
                    'name' => 'Programme expenditure',
                    'debit' => $this->money($cumulativeExpenditure),
                    'credit' => $this->money(0),
                ],
                [
                    'code' => '4000',
                    'name' => 'Restricted ATTP funding',
                    'debit' => $this->money(0),
                    'credit' => $this->money($cumulativeReceipts),
                ],
            ];
            $trialDebits = $cashAsset + $cumulativeExpenditure;
            $trialCredits = $cashOverdraft + $cumulativeReceipts;

            return [
                'currency' => $currency,
                'statementOfFinancialPosition' => [
                    'assets' => ['cashAndCashEquivalents' => $this->money($cashAsset)],
                    'liabilities' => ['cashOverdraft' => $this->money($cashOverdraft)],
                    'fundBalance' => ['restrictedFundBalance' => $this->money($fundBalance)],
                    'totalAssets' => $this->money($cashAsset),
                    'totalLiabilitiesAndFundBalance' => $this->money($totalLiabilitiesAndFundBalance),
                    'isBalanced' => $cashAsset === $totalLiabilitiesAndFundBalance,
                ],
                'receiptsAndExpenditure' => [
                    'receipts' => $this->money($periodReceipts),
                    'pendingConfirmation' => $activity['pendingConfirmation'],
                    'expenditure' => $this->money($periodExpenditure),
                    'surplusOrDeficit' => $this->money($periodNetCash),
                ],
                'cashFlow' => [
                    'openingCash' => $this->money($openingCash),
                    'cashInflows' => $this->money($periodReceipts),
                    'cashOutflows' => $this->money($periodExpenditure),
                    'netCashFlow' => $this->money($periodNetCash),
                    'closingCash' => $this->money($cash),
                ],
                'budgetPerformance' => [
                    'authorizedInternalBudget' => $activity['allocated'],
                    'approvedCommitments' => $activity['committed'],
                    'actualExpenditure' => $activity['spent'],
                    'availableUncommittedBalance' => $activity['available'],
                    'budgetVariance' => $this->money(
                        $this->cents($activity['allocated']) - $this->cents($activity['spent'])
                    ),
                ],
                'trialBalance' => [
                    'accounts' => $trialAccounts,
                    'debits' => $this->money($trialDebits),
                    'credits' => $this->money($trialCredits),
                    'isBalanced' => $trialDebits === $trialCredits,
                ],
                'offBalanceSheetCommitments' => [
                    'grossCommitments' => $this->money($commitments),
                    'settledThroughRecognizedPayments' => $this->money($settledCommitments),
                    'outstandingCommitments' => $this->money($outstandingCommitments),
                ],
                'ledger' => $ledger,
            ];
        })->values()->all();

        return [
            'asOf' => now()->toIso8601String(),
            'tenant' => $this->tenant($member),
            'permissions' => $this->permissions($viewer),
            'reportingBasis' => [
                'basis' => 'recipient_confirmed_cash',
                'period' => $period,
                'description' => 'Cash receipts are recognized only after recipient confirmation. Period activity is shown separately; opening and closing cash and the financial position are cumulative through period end.',
            ],
            'options' => [
                'periods' => $this->reportPeriodOptions($allPeriodsSnapshot),
                'currencies' => collect($allPeriodsSnapshot['positions'])
                    ->pluck('currency')
                    ->filter(fn (string $currency): bool => $currency !== 'UNKNOWN')
                    ->map(fn (string $currency): array => ['value' => $currency, 'label' => $currency])
                    ->values()
                    ->all(),
            ],
            'reports' => $reports,
            'reconciliationWarnings' => collect($activitySnapshot['warnings'])
                ->concat($positionSnapshot['warnings'])
                ->unique(fn (array $warning): string => implode('|', [
                    $warning['code'] ?? '',
                    $warning['currency'] ?? '',
                    $warning['sourceId'] ?? '',
                ]))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array{fund_allocation_id?: string|null, amount: mixed, currency: string, purpose: string, idempotency_key: string}  $data
     * @return array{request: ConsortiumDisbursementRequest, idempotent: bool}
     */
    public function createFundingRequest(
        Request $request,
        ConsortiumThinkTank $member,
        User $actor,
        array $data,
    ): array {
        return DB::transaction(function () use ($request, $member, $actor, $data): array {
            $this->lockMember($member);
            $idempotencyKey = trim($data['idempotency_key']);
            $currency = $this->currency($data['currency']);
            $amount = $this->money($this->cents($data['amount']));
            $fingerprint = hash('sha256', json_encode([
                'fund_allocation_id' => filled($data['fund_allocation_id'] ?? null)
                    ? (string) $data['fund_allocation_id']
                    : null,
                'amount' => $amount,
                'currency' => $currency,
                'purpose' => trim((string) ($data['purpose'] ?? '')),
            ], JSON_THROW_ON_ERROR));

            $existing = ConsortiumDisbursementRequest::query()
                ->where('think_tank_member_id', $member->id)
                ->where('consortium_id', $member->consortium_id)
                ->where('portal_idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if (! hash_equals((string) $existing->portal_idempotency_fingerprint, $fingerprint)) {
                    throw new ThinkTankApiException(
                        'IDEMPOTENCY_CONFLICT',
                        'This idempotency key was already used for a different funding request.',
                        409,
                    );
                }

                return ['request' => $existing, 'idempotent' => true];
            }

            if ($currency !== 'USD') {
                throw ValidationException::withMessages([
                    'currency' => ['Think Tank portal funding requests currently require USD.'],
                ]);
            }

            $allocation = null;
            if (filled($data['fund_allocation_id'] ?? null)) {
                $allocation = ConsortiumFundAllocation::query()
                    ->where('think_tank_member_id', $member->id)
                    ->where('consortium_id', $member->consortium_id)
                    ->where('status', 'active')
                    ->whereKey($data['fund_allocation_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($this->currency($allocation->currency) !== $currency) {
                    throw ValidationException::withMessages([
                        'currency' => ['The request currency must match the selected fund allocation.'],
                    ]);
                }

                $allocationRequests = ConsortiumDisbursementRequest::query()
                    ->where('think_tank_member_id', $member->id)
                    ->where('consortium_id', $member->consortium_id)
                    ->where('fund_allocation_id', $allocation->id)
                    ->lockForUpdate()
                    ->get();
                $reserved = $this->allocationUsage($allocation, $allocationRequests)['reserved'];
                $requested = $this->cents($amount);

                if ($reserved + $requested > $this->cents($allocation->amount_allocated)) {
                    throw ValidationException::withMessages([
                        'amount' => ['The request exceeds the unreserved balance of the selected fund allocation.'],
                    ]);
                }
            }

            $fundingRequest = ConsortiumDisbursementRequest::query()->create([
                'consortium_id' => $member->consortium_id,
                'think_tank_member_id' => $member->id,
                'fund_allocation_id' => $allocation?->id,
                'request_code' => $this->nextFundingRequestCode(),
                'amount_requested' => $amount,
                'amount_approved' => 0,
                'currency' => $currency,
                'status' => 'submitted',
                'purpose' => trim($data['purpose']),
                'requested_by' => $actor->id,
                'requested_at' => now(),
                'portal_idempotency_key' => $idempotencyKey,
                'portal_idempotency_fingerprint' => $fingerprint,
                'portal_lock_version' => 1,
            ]);

            $this->audit->required(
                $request,
                'think_tank.finance.funding_request.submitted',
                'Think tank funding request submitted.',
                [
                    'think_tank_member_id' => (string) $member->id,
                    'funding_request_id' => (string) $fundingRequest->id,
                    'fund_allocation_id' => $allocation?->id ? (string) $allocation->id : null,
                    'amount' => $amount,
                    'currency' => $currency,
                ],
                $actor,
            );

            return ['request' => $fundingRequest, 'idempotent' => false];
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function createBudgetLine(
        Request $request,
        ConsortiumThinkTank $member,
        User $actor,
        array $data,
    ): ThinkTankBudgetLine {
        return DB::transaction(function () use ($request, $member, $actor, $data): ThinkTankBudgetLine {
            $this->lockMember($member);
            $lockedLines = $this->lockBudgetLines($member);
            $attributes = $this->budgetLineAttributes($member, $data, null, $lockedLines);
            $this->assertUniqueBudgetCode($lockedLines, $attributes['fiscal_year'], $attributes['code']);
            $this->assertBudgetHierarchy($member, $lockedLines, $attributes);
            $this->assertBudgetExecutionCoverage($member, $lockedLines, $attributes);

            $line = ThinkTankBudgetLine::query()->create([
                ...$attributes,
                'consortium_id' => $member->consortium_id,
                'think_tank_member_id' => $member->id,
                'portal_lock_version' => 1,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->audit->required(
                $request,
                'think_tank.finance.budget_line.created',
                'Think tank internal budget line created.',
                [
                    'think_tank_member_id' => (string) $member->id,
                    'budget_line_id' => (string) $line->id,
                    'parent_id' => $line->parent_id ? (string) $line->parent_id : null,
                    'amount' => (string) $line->amount,
                    'currency' => (string) $line->currency,
                ],
                $actor,
            );

            return $line;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function updateBudgetLine(
        Request $request,
        ConsortiumThinkTank $member,
        User $actor,
        string $lineId,
        array $data,
    ): ThinkTankBudgetLine {
        return DB::transaction(function () use ($request, $member, $actor, $lineId, $data): ThinkTankBudgetLine {
            $this->lockMember($member);
            $lockedLines = $this->lockBudgetLines($member);
            $line = $lockedLines->firstWhere('id', $lineId);
            abort_unless($line instanceof ThinkTankBudgetLine, 404);
            $this->assertLockToken($line, $data['lock_token'] ?? null);
            $attributes = $this->budgetLineAttributes($member, $data, $line, $lockedLines);
            $this->assertUniqueBudgetCode(
                $lockedLines,
                $attributes['fiscal_year'],
                $attributes['code'],
                (string) $line->id,
            );
            $this->assertBudgetHierarchy($member, $lockedLines, $attributes, $line);
            $this->assertBudgetExecutionCoverage($member, $lockedLines, $attributes, $line);

            $line->update([
                ...$attributes,
                'portal_lock_version' => $line->nextPortalLockVersion(),
                'updated_by' => $actor->id,
            ]);

            $this->audit->required(
                $request,
                'think_tank.finance.budget_line.updated',
                'Think tank internal budget line updated.',
                [
                    'think_tank_member_id' => (string) $member->id,
                    'budget_line_id' => (string) $line->id,
                    'parent_id' => $line->parent_id ? (string) $line->parent_id : null,
                    'amount' => (string) $line->amount,
                    'currency' => (string) $line->currency,
                    'portal_lock_version' => (int) $line->portal_lock_version,
                ],
                $actor,
            );

            return $line->refresh();
        }, 3);
    }

    /** @return array<string, mixed> */
    public function transferResource(ProcurementDisbursement $payment, User $viewer): array
    {
        $payment->loadMissing([
            'purchaseOrder.purchaseRequest',
            'purchaseOrder.budgetCommitment.purchaseRequest',
            'recipientConfirmer:id,name',
        ]);
        $receiptStatus = trim((string) $payment->recipient_confirmation_status) ?: 'pending';
        $description = $payment->purchaseOrder?->po_title
            ?: $payment->purchaseOrder?->sourcePurchaseRequest()?->description;

        return [
            'id' => (string) $payment->id,
            'referenceNumber' => $payment->reference_no ?: null,
            'purchaseOrderReference' => $payment->purchaseOrder?->reference_no,
            'transferReference' => $payment->transfer_reference ?: null,
            'description' => filled($description) ? (string) $description : null,
            'amount' => $this->money($this->cents($payment->amount)),
            'currency' => $this->paymentCurrency($payment),
            'paymentMethod' => $payment->payment_method ?: null,
            'paidAt' => $payment->paid_at?->toIso8601String(),
            'status' => (string) $payment->status,
            'statusLabel' => Str::headline((string) $payment->status),
            'receiptStatus' => $receiptStatus,
            'receiptStatusLabel' => $receiptStatus === 'confirmed' ? 'Receipt confirmed' : 'Awaiting receipt confirmation',
            'confirmedAt' => $payment->recipient_confirmed_at?->toIso8601String(),
            'confirmedBy' => $payment->recipientConfirmer?->name,
            'confirmationNotes' => $payment->recipient_confirmation_notes ?: null,
            'lockToken' => $this->lockToken($payment),
            'canConfirm' => $receiptStatus === 'pending'
                && $this->permissions($viewer)['canConfirmTransfers'],
        ];
    }

    /** @return array<string, mixed> */
    public function fundingRequestResource(ConsortiumDisbursementRequest $fundingRequest): array
    {
        $fundingRequest->loadMissing('reviewer:id,name');

        return [
            'id' => (string) $fundingRequest->id,
            'requestCode' => (string) $fundingRequest->request_code,
            'fundAllocationId' => $fundingRequest->fund_allocation_id ? (string) $fundingRequest->fund_allocation_id : null,
            'purpose' => $fundingRequest->purpose ?: null,
            'amountRequested' => $this->money($this->cents($fundingRequest->amount_requested)),
            'amountApproved' => $this->money($this->cents($fundingRequest->amount_approved)),
            'currency' => $this->currency($fundingRequest->currency),
            'status' => (string) $fundingRequest->status,
            'statusLabel' => $this->fundingRequestStatusLabel((string) $fundingRequest->status),
            'requestedAt' => $fundingRequest->requested_at?->toIso8601String(),
            'reviewedAt' => $fundingRequest->reviewed_at?->toIso8601String(),
            'reviewedBy' => $fundingRequest->reviewer?->name,
            'reviewNotes' => $fundingRequest->review_notes ?: null,
            'paidAt' => $fundingRequest->paid_at?->toIso8601String(),
            'lockToken' => $this->lockToken($fundingRequest),
        ];
    }

    /** @return array<string, mixed> */
    public function budgetLine(
        ConsortiumThinkTank $member,
        ThinkTankBudgetLine $line,
    ): array {
        abort_unless(
            (string) $line->think_tank_member_id === (string) $member->id
            && (string) $line->consortium_id === (string) $member->consortium_id,
            404,
        );

        $allLines = ThinkTankBudgetLine::query()
            ->where('think_tank_member_id', $member->id)
            ->where('consortium_id', $member->consortium_id)
            ->with(['procurementItem.plan', 'procurementItem.procurement'])
            ->get();
        $current = $allLines->firstWhere('id', $line->id);
        abort_unless($current instanceof ThinkTankBudgetLine, 404);

        return $this->budgetLineResource($current, $allLines, $this->procurementMetrics($member));
    }

    /** @return array<string, mixed> */
    private function fundAllocationResource(
        ConsortiumFundAllocation $allocation,
        ?Collection $requests = null,
    ): array {
        $usage = $this->allocationUsage(
            $allocation,
            ($requests ?? collect())->where('fund_allocation_id', $allocation->id),
        );
        $reserved = $usage['reserved'];
        $allocated = $this->cents($allocation->amount_allocated);

        return [
            'id' => (string) $allocation->id,
            'label' => trim((string) $allocation->budget_line) ?: 'Fund allocation',
            'budgetLine' => $allocation->budget_line ?: null,
            'currency' => $this->currency($allocation->currency),
            'amountAllocated' => $this->money($allocated),
            'amountCommitted' => $this->money($this->cents($allocation->amount_committed)),
            'amountDisbursed' => $this->money($this->cents($allocation->amount_disbursed)),
            'amountSpent' => $this->money($this->cents($allocation->amount_spent)),
            'amountReserved' => $this->money($reserved),
            'available' => $this->money(max($allocated - $reserved, 0)),
            'canRequest' => $allocation->status === 'active' && $allocated > $reserved,
            'status' => (string) $allocation->status,
            'statusLabel' => Str::headline((string) $allocation->status),
        ];
    }

    /**
     * A legacy allocation may already carry a disbursed balance without a
     * matching paid request. Conversely, current transfers have both records.
     * Taking the greater historical consumption avoids both reopening spent
     * capacity and double-counting the canonical paired records.
     *
     * @return array{disbursed: int, paidRequests: int, outstandingRequests: int, reserved: int}
     */
    private function allocationUsage(ConsortiumFundAllocation $allocation, Collection $requests): array
    {
        $paidRequests = $requests
            ->where('status', 'paid')
            ->sum(fn (ConsortiumDisbursementRequest $row): int => $this->requestReservationCents($row));
        $outstandingRequests = $requests
            ->whereIn('status', self::OUTSTANDING_REQUEST_STATUSES)
            ->sum(fn (ConsortiumDisbursementRequest $row): int => $this->requestReservationCents($row));
        $disbursed = $this->cents($allocation->amount_disbursed);

        return [
            'disbursed' => $disbursed,
            'paidRequests' => $paidRequests,
            'outstandingRequests' => $outstandingRequests,
            'reserved' => max($disbursed, $paidRequests) + $outstandingRequests,
        ];
    }

    private function requestReservationCents(ConsortiumDisbursementRequest $request): int
    {
        return $this->cents($request->amount_approved) > 0
            ? $this->cents($request->amount_approved)
            : $this->cents($request->amount_requested);
    }

    /** @return array<string, mixed> */
    private function procurementOptionResource(ThinkTankProcurementItem $item): array
    {
        return [
            'id' => (string) $item->id,
            'planId' => (string) $item->plan_id,
            'planCode' => (string) ($item->plan?->plan_code ?: ''),
            'itemCode' => (string) $item->item_code,
            'title' => (string) $item->title,
            'label' => trim((string) $item->item_code.' - '.(string) $item->title, ' -'),
            'fiscalYear' => (string) ($item->plan?->fiscal_year ?: ''),
            'status' => (string) $item->status,
            'statusLabel' => Str::headline((string) $item->status),
            'currency' => $this->currency($item->currency, $item->plan?->currency),
            'plannedAmount' => $this->money($this->cents($item->estimated_amount)),
        ];
    }

    public function lockToken(Model $model): string
    {
        $version = max(1, (int) ($model->getAttribute('portal_lock_version') ?: 1));
        $attributes = $model->getAttributes();
        unset($attributes['portal_lock_version']);
        ksort($attributes);

        return hash_hmac(
            'sha256',
            $model::class.'|'.$model->getKey().'|'.$version.'|'.hash('sha256', serialize($attributes)),
            (string) config('app.key'),
        );
    }

    public function assertLockToken(Model $model, mixed $provided): void
    {
        if (! is_string($provided) || ! hash_equals($this->lockToken($model), trim($provided))) {
            throw new ThinkTankApiException(
                'STALE_WRITE',
                'This finance record changed after it was opened. Reload the latest version before saving.',
                409,
            );
        }
    }

    /** @return array{canView: bool, canManage: bool, canRequestFunds: bool, canConfirmTransfers: bool, canManageBudgetLines: bool} */
    public function permissions(User $viewer): array
    {
        $auditor = $viewer->isAuditor();
        $manage = ! $auditor && $viewer->hasPermission('think_tank.finance.manage');

        return [
            'canView' => $auditor || $manage || $viewer->hasPermission('think_tank.finance.view'),
            'canManage' => $manage,
            'canRequestFunds' => $manage,
            'canConfirmTransfers' => $manage,
            'canManageBudgetLines' => $manage,
        ];
    }

    /**
     * Pure cents-based accounting helper used by the API and focused tests.
     *
     * @return array{receipts: string, expenditure: string, cash: string, debits: string, credits: string, balanced: bool}
     */
    public function balancedPosition(mixed $receipts, mixed $expenditure): array
    {
        $receiptCents = $this->cents($receipts);
        $expenditureCents = $this->cents($expenditure);
        $cash = $receiptCents - $expenditureCents;
        $debits = max($cash, 0) + $expenditureCents;
        $credits = max(-$cash, 0) + $receiptCents;

        return [
            'receipts' => $this->money($receiptCents),
            'expenditure' => $this->money($expenditureCents),
            'cash' => $this->money($cash),
            'debits' => $this->money($debits),
            'credits' => $this->money($credits),
            'balanced' => $debits === $credits,
        ];
    }

    /** @return array<string, mixed> */
    private function snapshot(
        ConsortiumThinkTank $member,
        ?string $fiscalYear = null,
        bool $throughPeriodEnd = false,
    ): array {
        $now = now();
        $periodStart = $fiscalYear !== null
            ? $now->copy()->setDate((int) $fiscalYear, 1, 1)->startOfDay()
            : null;
        $periodEnd = $fiscalYear !== null
            ? $now->copy()->setDate((int) $fiscalYear, 12, 31)->endOfDay()
            : null;
        $cutoff = $periodEnd && $periodEnd->lt($now) ? $periodEnd : $now;

        // A transfer paid in one year and confirmed in the next belongs to
        // the confirmation year's recipient books. Load paid transfers up to
        // the reporting cutoff, then classify them by confirmed_at below.
        $incomingQuery = $this->incomingTransfersQuery($member)
            ->where('paid_at', '<=', $cutoff);
        $spendingQuery = $this->spendingPaymentsQuery($member)
            ->where('paid_at', '<=', $cutoff);
        $commitmentsQuery = $this->commitmentOrdersQuery($member);
        $this->constrainCommitmentDates(
            $commitmentsQuery,
            $cutoff,
            $fiscalYear !== null && ! $throughPeriodEnd ? $periodStart : null,
        );
        if ($fiscalYear !== null) {
            if ($throughPeriodEnd) {
                // The cutoff predicates above already provide cumulative
                // activity through the end of the selected calendar year.
            } else {
                $spendingQuery->whereBetween('paid_at', [$periodStart, $cutoff]);
            }
        }

        $incoming = $incomingQuery
            ->with([
                'purchaseOrder.purchaseRequest',
                'purchaseOrder.budgetCommitment.purchaseRequest',
                'recipientConfirmer:id,name',
            ])
            ->get();
        $spending = $spendingQuery
            ->with(['purchaseOrder.procurement', 'procurement'])
            ->get();
        $commitments = $commitmentsQuery->get();
        $confirmedThroughCutoff = $incoming->filter(
            fn (ProcurementDisbursement $payment): bool => $payment->recipient_confirmation_status === 'confirmed'
                && $payment->recipient_confirmed_at !== null
                && $payment->recipient_confirmed_at->lte($cutoff)
        );
        $recognizedIncoming = $fiscalYear !== null && ! $throughPeriodEnd
            ? $confirmedThroughCutoff->filter(
                fn (ProcurementDisbursement $payment): bool => $payment->recipient_confirmed_at->gte($periodStart)
            )
            : $confirmedThroughCutoff;
        $pendingIncoming = $incoming->reject(
            fn (ProcurementDisbursement $payment): bool => $payment->recipient_confirmation_status === 'confirmed'
                && $payment->recipient_confirmed_at !== null
                && $payment->recipient_confirmed_at->lte($cutoff)
        );
        $requestsQuery = ConsortiumDisbursementRequest::query()
            ->where('think_tank_member_id', $member->id)
            ->where('consortium_id', $member->consortium_id)
            ->where(fn ($query) => $query->whereNull('requested_at')->orWhere('requested_at', '<=', $cutoff))
            ->with('reviewer:id,name');
        $budgetLinesQuery = ThinkTankBudgetLine::query()
            ->where('think_tank_member_id', $member->id)
            ->where('consortium_id', $member->consortium_id)
            ->with(['procurementItem.plan', 'procurementItem.procurement']);
        if ($fiscalYear !== null) {
            if ($throughPeriodEnd) {
                $requestsQuery->where('requested_at', '<=', $cutoff);
            } else {
                $requestsQuery->whereBetween('requested_at', [$periodStart, $cutoff]);
            }
        }
        $requests = $requestsQuery->get();
        $allocations = ConsortiumFundAllocation::query()
            ->where('think_tank_member_id', $member->id)
            ->where('consortium_id', $member->consortium_id)
            ->get();
        $budgetLines = $budgetLinesQuery->get();
        if ($fiscalYear !== null) {
            $reportYear = (int) $fiscalYear;
            $budgetLines = $budgetLines
                ->filter(function (ThinkTankBudgetLine $line) use ($reportYear, $throughPeriodEnd): bool {
                    $lineYear = $this->fiscalCalendarYear((string) $line->fiscal_year);

                    return $lineYear !== null
                        && ($throughPeriodEnd ? $lineYear <= $reportYear : $lineYear === $reportYear);
                })
                ->values();
        }

        $currencies = $incoming->map(fn (ProcurementDisbursement $row): string => $this->paymentCurrency($row))
            ->merge($spending->map(fn (ProcurementDisbursement $row): string => $this->paymentCurrency($row)))
            ->merge($commitments->map(fn (ProcurementPurchaseOrder $row): string => $this->purchaseOrderCurrency($row)))
            ->merge($requests->map(fn (ConsortiumDisbursementRequest $row): string => $this->currency($row->currency)))
            ->merge($budgetLines->map(fn (ThinkTankBudgetLine $row): string => $this->currency($row->currency)))
            ->push('USD')
            ->unique()
            ->sort()
            ->values();

        $positions = $currencies->map(function (string $currency) use (

            $spending,
            $commitments,
            $requests,
            $budgetLines,
            $recognizedIncoming,
            $pendingIncoming,
        ): array {
            $currencySpending = $spending->filter(fn (ProcurementDisbursement $row): bool => $this->paymentCurrency($row) === $currency);
            $currencyCommitments = $commitments->filter(fn (ProcurementPurchaseOrder $row): bool => $this->purchaseOrderCurrency($row) === $currency);
            $currencyRequests = $requests->filter(fn (ConsortiumDisbursementRequest $row): bool => $this->currency($row->currency) === $currency);
            $currencyBudget = $budgetLines->filter(fn (ThinkTankBudgetLine $row): bool => $this->currency($row->currency) === $currency
                && $row->parent_id === null
                && $row->status === ThinkTankBudgetLine::STATUS_ACTIVE
            );
            $received = $recognizedIncoming
                ->filter(fn (ProcurementDisbursement $row): bool => $this->paymentCurrency($row) === $currency)
                ->sum(fn (ProcurementDisbursement $row): int => $this->cents($row->amount));
            $pendingConfirmation = $pendingIncoming
                ->filter(fn (ProcurementDisbursement $row): bool => $this->paymentCurrency($row) === $currency)
                ->sum(fn (ProcurementDisbursement $row): int => $this->cents($row->amount));
            $spent = $currencySpending->sum(fn (ProcurementDisbursement $row): int => $this->cents($row->amount));
            $committed = $currencyCommitments->sum(fn (ProcurementPurchaseOrder $row): int => $this->cents($row->amount));
            $allocated = $currencyBudget->sum(fn (ThinkTankBudgetLine $row): int => $this->cents($row->amount));
            $commitmentSettlement = $this->commitmentSettlement($currencyCommitments, $currencySpending);
            $requested = $currencyRequests
                ->whereIn('status', self::RESERVED_REQUEST_STATUSES)
                ->sum(fn (ConsortiumDisbursementRequest $row): int => $this->cents($row->amount_requested));

            return [
                'currency' => $currency,
                'allocated' => $this->money($allocated),
                'received' => $this->money($received),
                'pendingConfirmation' => $this->money($pendingConfirmation),
                'requested' => $this->money($requested),
                'committed' => $this->money($committed),
                'settledCommitments' => $this->money($commitmentSettlement['settled']),
                'outstandingCommitments' => $this->money($commitmentSettlement['outstanding']),
                'spent' => $this->money($spent),
                // Calculate the conservative execution requirement one
                // procurement at a time. A spend overrun on one procurement
                // must not be masked by unused commitment headroom on another.
                'available' => $this->money($allocated - $commitmentSettlement['required']),
                'cashBalance' => $this->money($received - $spent),
            ];
        })->all();

        return [
            'incoming' => $incoming,
            'recognizedIncoming' => $recognizedIncoming->values(),
            'pendingIncoming' => $pendingIncoming->values(),
            'spending' => $spending,
            'commitments' => $commitments,
            'requests' => $requests,
            'allocations' => $allocations,
            'budgetLines' => $budgetLines,
            'positions' => $positions,
            'warnings' => $this->reconciliationWarnings($positions),
        ];
    }

    public function incomingTransfersQuery(ConsortiumThinkTank $member): Builder
    {
        return app(ThinkTankFundingSourceService::class)->incomingPaymentsQuery($member);
    }

    public function incomingFundingPurchaseOrdersQuery(ConsortiumThinkTank $member): Builder
    {
        return app(ThinkTankFundingSourceService::class)->incomingPurchaseOrdersQuery($member);
    }

    private function spendingPaymentsQuery(ConsortiumThinkTank $member)
    {
        return ProcurementDisbursement::query()
            ->recognizedPayment()
            ->where(fn (Builder $query) => $query
                ->whereNull('purchase_order_id')
                ->orWhereNotIn(
                    'purchase_order_id',
                    $this->incomingFundingPurchaseOrdersQuery($member)->select('procurement_purchase_orders.id')
                ))
            ->where(function ($query) use ($member): void {
                $query->whereHas('procurement', fn ($procurement) => $procurement
                    ->where('think_tank_member_id', $member->id)
                    ->where('consortium_id', $member->consortium_id))
                    ->orWhereHas('purchaseOrder.procurement', fn ($procurement) => $procurement
                        ->where('think_tank_member_id', $member->id)
                        ->where('consortium_id', $member->consortium_id));
            });
    }

    private function commitmentOrdersQuery(ConsortiumThinkTank $member)
    {
        return ProcurementPurchaseOrder::query()
            ->whereIn('status', self::COMMITMENT_STATUSES)
            ->whereNotIn(
                'procurement_purchase_orders.id',
                $this->incomingFundingPurchaseOrdersQuery($member)->select('procurement_purchase_orders.id')
            )
            ->whereHas('procurement', fn ($procurement) => $procurement
                ->where('think_tank_member_id', $member->id)
                ->where('consortium_id', $member->consortium_id));
    }

    /**
     * Settle recognized spend only against its own procurement commitment.
     * Payments without a procurement identifier inherit the linked purchase
     * order's procurement, then fall back to that purchase order itself. This
     * prevents an overrun on one procurement from netting an unrelated open
     * commitment down to an understated portfolio balance.
     *
     * @param  Collection<int, ProcurementPurchaseOrder>  $commitments
     * @param  Collection<int, ProcurementDisbursement>  $spending
     * @return array{gross: int, settled: int, outstanding: int, required: int}
     */
    private function commitmentSettlement(Collection $commitments, Collection $spending): array
    {
        $committedByExecution = $commitments
            ->groupBy(fn (ProcurementPurchaseOrder $order): string => $this->commitmentExecutionKey($order))
            ->map(fn (Collection $rows): int => $rows
                ->sum(fn (ProcurementPurchaseOrder $order): int => $this->cents($order->amount)));
        $spentByExecution = $spending
            ->groupBy(fn (ProcurementDisbursement $payment): string => $this->spendingExecutionKey($payment))
            ->map(fn (Collection $rows): int => $rows
                ->sum(fn (ProcurementDisbursement $payment): int => $this->cents($payment->amount)));

        $settled = $committedByExecution
            ->map(fn (int $committed, string $key): int => min(
                $committed,
                (int) ($spentByExecution[$key] ?? 0),
            ))
            ->sum();
        $outstanding = $committedByExecution
            ->map(fn (int $committed, string $key): int => max(
                $committed - (int) ($spentByExecution[$key] ?? 0),
                0,
            ))
            ->sum();
        $required = $committedByExecution->keys()
            ->merge($spentByExecution->keys())
            ->unique()
            ->sum(fn (string $key): int => max(
                (int) ($committedByExecution[$key] ?? 0),
                (int) ($spentByExecution[$key] ?? 0),
            ));

        return [
            'gross' => $committedByExecution->sum(),
            'settled' => $settled,
            'outstanding' => $outstanding,
            'required' => $required,
        ];
    }

    private function commitmentExecutionKey(ProcurementPurchaseOrder $order): string
    {
        if (filled($order->procurement_id)) {
            return 'procurement:'.$order->procurement_id;
        }

        return 'purchase-order:'.$order->id;
    }

    private function spendingExecutionKey(ProcurementDisbursement $payment): string
    {
        $procurementId = $payment->procurement_id ?: $payment->purchaseOrder?->procurement_id;
        if (filled($procurementId)) {
            return 'procurement:'.$procurementId;
        }
        if (filled($payment->purchase_order_id)) {
            return 'purchase-order:'.$payment->purchase_order_id;
        }

        return 'unmatched-payment:'.$payment->id;
    }

    /** @return array<string, mixed> */
    private function procurementMetrics(ConsortiumThinkTank $member, bool $includeFuture = false): array
    {
        $items = ThinkTankProcurementItem::query()
            ->whereHas('plan', fn ($query) => $query
                ->where('think_tank_member_id', $member->id)
                ->where('consortium_id', $member->consortium_id))
            ->with(['plan', 'procurement'])
            ->get();
        $ordersQuery = $this->commitmentOrdersQuery($member);
        $paymentsQuery = $this->spendingPaymentsQuery($member);
        if (! $includeFuture) {
            $this->constrainCommitmentDates($ordersQuery, now());
            $paymentsQuery->where('paid_at', '<=', now());
        }
        $orders = $ordersQuery->get();
        $payments = $paymentsQuery->with('purchaseOrder')->get();
        $committed = [];
        $spent = [];

        foreach ($orders as $order) {
            if (filled($order->procurement_id)) {
                $key = (string) $order->procurement_id;
                $committed[$key] = ($committed[$key] ?? 0) + $this->cents($order->amount);
            }
        }

        foreach ($payments as $payment) {
            $procurementId = $payment->procurement_id ?: $payment->purchaseOrder?->procurement_id;
            if (filled($procurementId)) {
                $key = (string) $procurementId;
                $spent[$key] = ($spent[$key] ?? 0) + $this->cents($payment->amount);
            }
        }

        return [
            'items' => $items,
            'committedByProcurement' => $committed,
            'spentByProcurement' => $spent,
        ];
    }

    private function executionStage(ThinkTankProcurementItem $item, int $committed, int $spent): string
    {
        if ($spent > 0) {
            return 'spent';
        }

        if ($committed > 0) {
            return 'committed';
        }

        if (in_array($item->status, [
            ThinkTankProcurementItem::STATUS_NO_OBJECTION,
            ThinkTankProcurementItem::STATUS_PUBLISHED,
        ], true) || $item->currentStepActivityStatus() === ThinkTankProcurementItem::STEP_STATUS_CLEARED) {
            return 'approved_no_objection';
        }

        if ($item->status === ThinkTankProcurementItem::STATUS_APPROVED) {
            return 'awaiting_world_bank';
        }

        return match ($item->status) {
            ThinkTankProcurementItem::STATUS_SUBMITTED => 'awaiting_secretariat',
            ThinkTankProcurementItem::STATUS_REVISION_REQUESTED => 'revision_requested',
            ThinkTankProcurementItem::STATUS_REJECTED => 'rejected',
            ThinkTankProcurementItem::STATUS_DRAFT => 'draft',
            default => 'unclassified',
        };
    }

    private function executionStageLabel(string $stage): string
    {
        return match ($stage) {
            'awaiting_secretariat' => 'Awaiting ATTP Secretariat approval',
            'awaiting_world_bank' => 'Awaiting World Bank no-objection',
            'approved_no_objection' => 'Approved / no-objection',
            'committed' => 'Committed',
            'spent' => 'Spent',
            'revision_requested' => 'Revision requested',
            'rejected' => 'Rejected',
            'draft' => 'Draft',
            'unclassified' => 'Unclassified',
            default => Str::headline($stage),
        };
    }

    /** @return array<string, mixed> */
    private function budgetLineResource(
        ThinkTankBudgetLine $line,
        Collection $allLines,
        array $metrics,
    ): array {
        $children = $allLines->where('parent_id', $line->id)->where('status', '!=', ThinkTankBudgetLine::STATUS_CLOSED);
        $childAmount = $children->sum(fn (ThinkTankBudgetLine $child): int => $this->cents($child->amount));
        $relatedLines = $this->descendantLines($line, $allLines)->push($line);
        $procurementIds = $relatedLines
            ->map(fn (ThinkTankBudgetLine $related): mixed => $related->procurementItem?->procurement_id)
            ->filter()
            ->map(fn ($id): string => (string) $id)
            ->unique();
        $committed = $procurementIds->sum(fn (string $id): int => (int) ($metrics['committedByProcurement'][$id] ?? 0));
        $spent = $procurementIds->sum(fn (string $id): int => (int) ($metrics['spentByProcurement'][$id] ?? 0));
        $executionRequired = $procurementIds->sum(fn (string $id): int => max(
            (int) ($metrics['committedByProcurement'][$id] ?? 0),
            (int) ($metrics['spentByProcurement'][$id] ?? 0),
        ));
        $amount = $this->cents($line->amount);

        return [
            'id' => (string) $line->id,
            'parentId' => $line->parent_id ? (string) $line->parent_id : null,
            'fundAllocationId' => $line->fund_allocation_id ? (string) $line->fund_allocation_id : null,
            'procurementItemId' => $line->procurement_item_id ? (string) $line->procurement_item_id : null,
            'code' => (string) $line->code,
            'name' => (string) $line->name,
            'description' => $line->description ?: null,
            'fiscalYear' => (string) $line->fiscal_year,
            'currency' => $this->currency($line->currency),
            'amount' => $this->money($amount),
            'status' => (string) $line->status,
            'statusLabel' => match ($line->status) {
                ThinkTankBudgetLine::STATUS_DRAFT => 'Draft',
                ThinkTankBudgetLine::STATUS_ACTIVE => 'Active',
                ThinkTankBudgetLine::STATUS_CLOSED => 'Closed',
                default => Str::headline((string) $line->status),
            },
            'procurementItem' => $line->procurementItem
                ? $this->procurementOptionResource($line->procurementItem)
                : null,
            'classificationLocked' => $relatedLines->contains(
                fn (ThinkTankBudgetLine $related): bool => filled($related->procurementItem?->procurement_id)
            ),
            'childAmount' => $this->money($childAmount),
            'unallocatedAmount' => $this->money($amount - $childAmount),
            'committedAmount' => $this->money($committed),
            'spentAmount' => $this->money($spent),
            'availableAmount' => $this->money($amount - $executionRequired),
            'lockToken' => $this->lockToken($line),
            'createdAt' => $line->created_at?->toIso8601String(),
            'updatedAt' => $line->updated_at?->toIso8601String(),
        ];
    }

    private function descendantLines(ThinkTankBudgetLine $line, Collection $allLines): Collection
    {
        $descendants = collect();
        $frontier = collect([(string) $line->id]);

        while ($frontier->isNotEmpty()) {
            $children = $allLines->filter(fn (ThinkTankBudgetLine $candidate): bool => $candidate->parent_id !== null
                && $frontier->contains((string) $candidate->parent_id)
            );
            $children = $children->reject(fn (ThinkTankBudgetLine $candidate): bool => $descendants->contains('id', $candidate->id));
            $descendants = $descendants->concat($children);
            $frontier = $children->pluck('id')->map(fn ($id): string => (string) $id);
        }

        return $descendants;
    }

    /** @return array<string, mixed> */
    private function budgetLineAttributes(
        ConsortiumThinkTank $member,
        array $data,
        ?ThinkTankBudgetLine $existing,
        Collection $lockedLines,
    ): array {
        $value = fn (string $key, mixed $fallback = null): mixed => array_key_exists($key, $data)
            ? $data[$key]
            : ($existing?->getAttribute($key) ?? $fallback);
        $parentId = filled($value('parent_id')) ? (string) $value('parent_id') : null;
        $allocationId = filled($value('fund_allocation_id')) ? (string) $value('fund_allocation_id') : null;
        $procurementItemId = filled($value('procurement_item_id')) ? (string) $value('procurement_item_id') : null;
        $procurementItem = null;

        if ($parentId !== null) {
            $parent = $lockedLines->first(fn (ThinkTankBudgetLine $line): bool => (string) $line->id === $parentId);
            abort_unless($parent instanceof ThinkTankBudgetLine, 404);
            $parentAllocationId = filled($parent->fund_allocation_id) ? (string) $parent->fund_allocation_id : null;
            if ($allocationId === null && $parentAllocationId !== null) {
                $allocationId = $parentAllocationId;
            }
            if ($allocationId !== $parentAllocationId) {
                throw ValidationException::withMessages([
                    'fund_allocation_id' => ['A child budget line must inherit the same fund allocation as its parent.'],
                ]);
            }
            if (filled($parent->procurement_item_id)) {
                throw ValidationException::withMessages([
                    'parent_id' => ['A budget line linked to procurement is an execution leaf and cannot receive child allocations.'],
                ]);
            }
        }

        if ($allocationId !== null) {
            $allocation = ConsortiumFundAllocation::query()
                ->where('think_tank_member_id', $member->id)
                ->where('consortium_id', $member->consortium_id)
                ->whereKey($allocationId)
                ->lockForUpdate()
                ->firstOrFail();
            $allocationChanged = ! $existing
                || (string) $existing->fund_allocation_id !== (string) $allocationId;
            if ($allocationChanged && $allocation->status !== 'active') {
                throw ValidationException::withMessages([
                    'fund_allocation_id' => ['New budget allocations require an active fund allocation.'],
                ]);
            }
        }
        if ($procurementItemId !== null) {
            $procurementItem = ThinkTankProcurementItem::query()
                ->whereKey($procurementItemId)
                ->whereHas('plan', fn ($query) => $query
                    ->where('think_tank_member_id', $member->id)
                    ->where('consortium_id', $member->consortium_id))
                ->with(['plan', 'procurement'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedLines->contains(fn (ThinkTankBudgetLine $line): bool => (string) $line->id !== (string) $existing?->id
                && (string) $line->procurement_item_id === $procurementItemId
            )) {
                throw ValidationException::withMessages([
                    'procurement_item_id' => ['This procurement plan item is already linked to another budget line.'],
                ]);
            }
            if ($existing && $lockedLines->contains(
                fn (ThinkTankBudgetLine $line): bool => (string) $line->parent_id === (string) $existing->id
                    && $line->status !== ThinkTankBudgetLine::STATUS_CLOSED
            )) {
                throw ValidationException::withMessages([
                    'procurement_item_id' => ['Only a leaf budget line can be linked to a procurement plan item.'],
                ]);
            }
        }

        $hasExecutedBranch = filled($procurementItem?->procurement_id);
        if ($existing) {
            $descendantItemIds = $this->descendantLines($existing, $lockedLines)
                ->pluck('procurement_item_id')
                ->filter()
                ->map(fn ($id): string => (string) $id)
                ->unique()
                ->values();
            if (! $hasExecutedBranch && $descendantItemIds->isNotEmpty()) {
                $hasExecutedBranch = ThinkTankProcurementItem::query()
                    ->whereIn('id', $descendantItemIds)
                    ->whereNotNull('procurement_id')
                    ->whereHas('plan', fn ($query) => $query
                        ->where('think_tank_member_id', $member->id)
                        ->where('consortium_id', $member->consortium_id))
                    ->lockForUpdate()
                    ->get()
                    ->isNotEmpty();
            }
            $this->assertExecutedClassificationImmutable(
                $existing,
                $hasExecutedBranch,
                $parentId,
                $allocationId,
            );
        }

        $currency = $this->currency($value('currency', 'USD'));
        $amount = $this->cents($value('amount'));
        $status = (string) $value('status', ThinkTankBudgetLine::STATUS_ACTIVE);
        $fiscalYear = trim((string) $value('fiscal_year'));

        if ($currency !== 'USD') {
            throw ValidationException::withMessages([
                'currency' => ['Internal Think Tank budget lines currently require USD.'],
            ]);
        }
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => ['The budget-line amount must be greater than zero.']]);
        }
        if (preg_match('/^20\d{2}(?:\/\d{2})?$/', $fiscalYear) !== 1) {
            throw ValidationException::withMessages([
                'fiscal_year' => ['Use a fiscal-year label such as 2026 or 2026/27.'],
            ]);
        }
        if (! in_array($status, ThinkTankBudgetLine::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => ['The selected budget-line status is invalid.']]);
        }
        if ($procurementItem instanceof ThinkTankProcurementItem) {
            if ($this->currency($procurementItem->currency, $procurementItem->plan?->currency) !== 'USD') {
                throw ValidationException::withMessages([
                    'procurement_item_id' => ['Only USD procurement items can be linked to the USD internal budget.'],
                ]);
            }
            if ((string) $procurementItem->plan?->fiscal_year !== $fiscalYear) {
                throw ValidationException::withMessages([
                    'fiscal_year' => ['The budget line fiscal year must exactly match the linked procurement plan fiscal year.'],
                ]);
            }
        }

        return [
            'parent_id' => $parentId,
            'fund_allocation_id' => $allocationId,
            'procurement_item_id' => $procurementItemId,
            'code' => Str::upper(trim((string) $value('code'))),
            'name' => trim((string) $value('name')),
            'description' => filled($value('description')) ? trim((string) $value('description')) : null,
            'fiscal_year' => $fiscalYear,
            'currency' => $currency,
            'amount' => $this->money($amount),
            'status' => $status,
        ];
    }

    private function assertExecutedClassificationImmutable(
        ?ThinkTankBudgetLine $existing,
        bool $hasExecutedBranch,
        ?string $parentId,
        ?string $allocationId,
    ): void {
        if (! $existing || ! $hasExecutedBranch) {
            return;
        }

        if ((string) ($existing->parent_id ?? '') !== (string) ($parentId ?? '')) {
            throw ValidationException::withMessages([
                'parent_id' => ['An executed procurement budget line cannot be moved. Record an audited budget correction instead.'],
            ]);
        }
        if ((string) ($existing->fund_allocation_id ?? '') !== (string) ($allocationId ?? '')) {
            throw ValidationException::withMessages([
                'fund_allocation_id' => ['An executed procurement budget line cannot be reclassified to another funding source. Record an audited budget correction instead.'],
            ]);
        }
    }

    private function assertUniqueBudgetCode(
        Collection $lockedLines,
        string $fiscalYear,
        string $code,
        ?string $exceptId = null,
    ): void {
        $duplicate = $lockedLines->contains(fn (ThinkTankBudgetLine $line): bool => (string) $line->id !== (string) $exceptId
            && (string) $line->fiscal_year === $fiscalYear
            && Str::upper((string) $line->code) === $code
        );

        if ($duplicate) {
            throw ValidationException::withMessages([
                'code' => ['This budget-line code is already in use for the selected fiscal year.'],
            ]);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function assertBudgetHierarchy(
        ConsortiumThinkTank $member,
        Collection $lockedLines,
        array $attributes,
        ?ThinkTankBudgetLine $existing = null,
    ): void {
        $lineId = $existing ? (string) $existing->id : null;
        $parentId = $attributes['parent_id'];
        $candidateAmount = $this->cents($attributes['amount']);
        $candidateActive = $attributes['status'] === ThinkTankBudgetLine::STATUS_ACTIVE;

        if ($parentId !== null) {
            if ($parentId === $lineId) {
                throw ValidationException::withMessages(['parent_id' => ['A budget line cannot be its own parent.']]);
            }

            $parent = $lockedLines->first(fn (ThinkTankBudgetLine $line): bool => (string) $line->id === $parentId);
            abort_unless($parent instanceof ThinkTankBudgetLine, 404);
            if ($parent->status === ThinkTankBudgetLine::STATUS_CLOSED) {
                throw ValidationException::withMessages(['parent_id' => ['A closed budget line cannot receive suballocations.']]);
            }
            if ($candidateActive && $parent->status !== ThinkTankBudgetLine::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'status' => ['An active child budget line requires an active parent and active ancestor chain.'],
                ]);
            }
            if ((string) $parent->fiscal_year !== (string) $attributes['fiscal_year']) {
                throw ValidationException::withMessages(['fiscal_year' => ['A child budget line must use its parent fiscal year.']]);
            }
            if ((string) $parent->currency !== (string) $attributes['currency']) {
                throw ValidationException::withMessages(['currency' => ['A child budget line must use its parent currency.']]);
            }

            $cursor = $parent;
            $seen = [];
            while ($cursor) {
                $cursorId = (string) $cursor->id;
                if ($cursorId === $lineId || isset($seen[$cursorId])) {
                    throw ValidationException::withMessages(['parent_id' => ['The selected parent would create a budget hierarchy cycle.']]);
                }
                $seen[$cursorId] = true;
                if ($candidateActive && $cursor->status !== ThinkTankBudgetLine::STATUS_ACTIVE) {
                    throw ValidationException::withMessages([
                        'status' => ['An active child budget line requires an active parent and active ancestor chain.'],
                    ]);
                }
                $cursor = $cursor->parent_id
                    ? $lockedLines->first(fn (ThinkTankBudgetLine $line): bool => (string) $line->id === (string) $cursor->parent_id)
                    : null;
            }

            $siblingTotal = $lockedLines
                ->filter(fn (ThinkTankBudgetLine $line): bool => (string) $line->parent_id === $parentId
                    && (string) $line->id !== (string) $lineId
                    && $line->status !== ThinkTankBudgetLine::STATUS_CLOSED
                )
                ->sum(fn (ThinkTankBudgetLine $line): int => $this->cents($line->amount));

            if ($candidateActive && $siblingTotal + $candidateAmount > $this->cents($parent->amount)) {
                throw ValidationException::withMessages([
                    'amount' => ['Active child budget lines cannot exceed the parent budget-line amount.'],
                ]);
            }
        } elseif ($attributes['status'] === ThinkTankBudgetLine::STATUS_ACTIVE) {
            $otherTopLevel = $lockedLines
                ->filter(fn (ThinkTankBudgetLine $line): bool => $line->parent_id === null
                    && (string) $line->id !== (string) $lineId
                    && $line->status === ThinkTankBudgetLine::STATUS_ACTIVE
                )
                ->sum(fn (ThinkTankBudgetLine $line): int => $this->cents($line->amount));
            $confirmedReceipts = $this->incomingTransfersQuery($member)
                ->where('recipient_confirmation_status', 'confirmed')
                ->whereNotNull('recipient_confirmed_at')
                ->where('recipient_confirmed_at', '<=', now())
                ->where('paid_at', '<=', now())
                ->lockForUpdate()
                ->get()
                ->filter(fn (ProcurementDisbursement $payment): bool => $this->paymentCurrency($payment) === 'USD')
                ->sum(fn (ProcurementDisbursement $payment): int => $this->cents($payment->amount));

            if ($otherTopLevel + $candidateAmount > $confirmedReceipts) {
                throw ValidationException::withMessages([
                    'amount' => ['Active top-level budget lines cannot exceed confirmed USD receipts.'],
                ]);
            }

            if (filled($attributes['fund_allocation_id'])) {
                $allocationTopLevel = $lockedLines
                    ->filter(fn (ThinkTankBudgetLine $line): bool => $line->parent_id === null
                        && (string) $line->id !== (string) $lineId
                        && (string) $line->fund_allocation_id === (string) $attributes['fund_allocation_id']
                        && $line->status === ThinkTankBudgetLine::STATUS_ACTIVE
                    )
                    ->sum(fn (ThinkTankBudgetLine $line): int => $this->cents($line->amount));
                $allocationReceipts = $this->incomingTransfersQuery($member)
                    ->where('fund_allocation_id', $attributes['fund_allocation_id'])
                    ->where('recipient_confirmation_status', 'confirmed')
                    ->whereNotNull('recipient_confirmed_at')
                    ->where('recipient_confirmed_at', '<=', now())
                    ->where('paid_at', '<=', now())
                    ->lockForUpdate()
                    ->get()
                    ->filter(fn (ProcurementDisbursement $payment): bool => $this->paymentCurrency($payment) === 'USD')
                    ->sum(fn (ProcurementDisbursement $payment): int => $this->cents($payment->amount));

                if ($allocationTopLevel + $candidateAmount > $allocationReceipts) {
                    throw ValidationException::withMessages([
                        'amount' => ['Active top-level lines for this fund allocation cannot exceed its confirmed USD receipts.'],
                    ]);
                }
            }
        }

        $sameParent = $existing !== null
            && (string) ($existing->parent_id ?? '') === (string) ($parentId ?? '');
        if (! $sameParent) {
            $parent = $parentId === null
                ? null
                : $lockedLines->first(fn (ThinkTankBudgetLine $line): bool => (string) $line->id === (string) $parentId);
            $newDepth = $parent instanceof ThinkTankBudgetLine
                ? $this->budgetHierarchyDepth($parent, $lockedLines) + 1
                : 1;
            $subtreeHeight = $existing instanceof ThinkTankBudgetLine
                ? $this->budgetSubtreeHeight($existing, $lockedLines)
                : 1;

            if ($newDepth + $subtreeHeight - 1 > 4) {
                throw ValidationException::withMessages([
                    'parent_id' => ['Budget allocations may only use Program, Project, Activity, and Sub-activity levels.'],
                ]);
            }
        }

        if ($existing) {
            $children = $lockedLines->filter(fn (ThinkTankBudgetLine $line): bool => (string) $line->parent_id === (string) $existing->id
                && $line->status !== ThinkTankBudgetLine::STATUS_CLOSED
            );
            $childTotal = $children->sum(fn (ThinkTankBudgetLine $line): int => $this->cents($line->amount));
            $activeDescendants = $this->descendantLines($existing, $lockedLines)
                ->where('status', ThinkTankBudgetLine::STATUS_ACTIVE);

            if ($attributes['status'] !== ThinkTankBudgetLine::STATUS_ACTIVE && $activeDescendants->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'status' => ['Move, close, or deactivate every active descendant before deactivating this parent line.'],
                ]);
            }

            if ($attributes['status'] === ThinkTankBudgetLine::STATUS_CLOSED && $children->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'status' => ['Close or move every active child budget line before closing its parent.'],
                ]);
            }

            if ($childTotal > $candidateAmount) {
                throw ValidationException::withMessages([
                    'amount' => ['The amount cannot be lower than the total of its active child budget lines.'],
                ]);
            }

            if ($children->contains(fn (ThinkTankBudgetLine $child): bool => (string) $child->fiscal_year !== (string) $attributes['fiscal_year']
                || (string) $child->currency !== (string) $attributes['currency']
                || (string) $child->fund_allocation_id !== (string) $attributes['fund_allocation_id']
            )) {
                throw ValidationException::withMessages([
                    'fiscal_year' => ['Move or update child lines before changing their parent fiscal year, currency, or fund allocation.'],
                ]);
            }
        }
    }

    private function budgetHierarchyDepth(ThinkTankBudgetLine $line, Collection $allLines): int
    {
        $depth = 1;
        $seen = [(string) $line->id => true];
        $cursor = $line;

        while ($cursor->parent_id !== null) {
            $parentId = (string) $cursor->parent_id;
            if (isset($seen[$parentId])) {
                throw ValidationException::withMessages([
                    'parent_id' => ['The existing budget hierarchy contains a cycle and must be corrected first.'],
                ]);
            }
            $seen[$parentId] = true;
            $parent = $allLines->first(fn (ThinkTankBudgetLine $candidate): bool => (string) $candidate->id === $parentId);
            if (! $parent instanceof ThinkTankBudgetLine) {
                break;
            }
            $depth++;
            $cursor = $parent;
        }

        return $depth;
    }

    private function budgetSubtreeHeight(ThinkTankBudgetLine $line, Collection $allLines): int
    {
        $height = 1;
        $frontier = [(string) $line->id => 1];
        $visited = [(string) $line->id => true];

        while ($frontier !== []) {
            $children = $allLines->filter(fn (ThinkTankBudgetLine $candidate): bool => $candidate->parent_id !== null
                && array_key_exists((string) $candidate->parent_id, $frontier)
            );
            $next = [];
            foreach ($children as $child) {
                $childId = (string) $child->id;
                if (isset($visited[$childId])) {
                    continue;
                }
                $visited[$childId] = true;
                $next[$childId] = $frontier[(string) $child->parent_id] + 1;
                $height = max($height, $next[$childId]);
            }
            $frontier = $next;
        }

        return $height;
    }

    /** @param array<string, mixed> $attributes */
    private function assertBudgetExecutionCoverage(
        ConsortiumThinkTank $member,
        Collection $lockedLines,
        array $attributes,
        ?ThinkTankBudgetLine $existing = null,
    ): void {
        // Capacity enforcement includes dated future commitments so a legacy
        // forward-dated order cannot be hidden by an as-of-now report cutoff.
        $metrics = $this->procurementMetrics($member, true);
        $itemsById = $metrics['items']->keyBy(fn (ThinkTankProcurementItem $item): string => (string) $item->id);
        $newItemId = filled($attributes['procurement_item_id']) ? (string) $attributes['procurement_item_id'] : null;
        $oldItemId = $existing && filled($existing->procurement_item_id)
            ? (string) $existing->procurement_item_id
            : null;

        if ($oldItemId !== null && $oldItemId !== $newItemId) {
            throw ValidationException::withMessages([
                'procurement_item_id' => ['A procurement plan link is permanent and cannot be removed or replaced.'],
            ]);
        }

        $lineIds = collect();
        if ($existing) {
            $lineIds = $this->descendantLines($existing, $lockedLines)
                ->pluck('id')
                ->map(fn ($id): string => (string) $id);
        }

        $procurementItemIds = $lockedLines
            ->filter(fn (ThinkTankBudgetLine $line): bool => $lineIds->contains((string) $line->id))
            ->pluck('procurement_item_id')
            ->filter()
            ->map(fn ($id): string => (string) $id);
        if ($newItemId !== null) {
            $procurementItemIds->push($newItemId);
        }

        $linkedItems = $procurementItemIds
            ->unique()
            ->map(fn (string $itemId): mixed => $itemsById->get($itemId))
            ->filter(fn ($item): bool => $item instanceof ThinkTankProcurementItem);
        $required = $linkedItems
            ->groupBy(fn (ThinkTankProcurementItem $item): string => filled($item->procurement_id)
                ? 'procurement:'.$item->procurement_id
                : 'item:'.$item->id)
            ->sum(function (Collection $group) use ($metrics): int {
                $planned = $group->sum(
                    fn (ThinkTankProcurementItem $item): int => $this->cents($item->estimated_amount)
                );
                $procurementId = $group->first()?->procurement_id;
                $committed = $procurementId
                    ? (int) ($metrics['committedByProcurement'][(string) $procurementId] ?? 0)
                    : 0;
                $spent = $procurementId
                    ? (int) ($metrics['spentByProcurement'][(string) $procurementId] ?? 0)
                    : 0;

                return max($planned, $committed, $spent);
            });

        if ($attributes['status'] === ThinkTankBudgetLine::STATUS_CLOSED && $procurementItemIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'status' => ['A budget line that funds a procurement item, directly or through a child, cannot be closed.'],
            ]);
        }
        if ($attributes['status'] === ThinkTankBudgetLine::STATUS_DRAFT && $procurementItemIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'status' => ['A budget line that funds a procurement item, directly or through a child, must remain active.'],
            ]);
        }
        if ($this->cents($attributes['amount']) < $required) {
            throw ValidationException::withMessages([
                'amount' => ['The amount cannot be lower than linked procurement plans, commitments, or expenditure for this line and its children.'],
            ]);
        }
    }

    private function lockMember(ConsortiumThinkTank $member): ConsortiumThinkTank
    {
        return ConsortiumThinkTank::query()
            ->whereKey($member->id)
            ->where('consortium_id', $member->consortium_id)
            ->where('status', 'active')
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockBudgetLines(ConsortiumThinkTank $member): Collection
    {
        return ThinkTankBudgetLine::query()
            ->where('think_tank_member_id', $member->id)
            ->where('consortium_id', $member->consortium_id)
            ->lockForUpdate()
            ->get();
    }

    /** @return array<int, array<string, mixed>> */
    private function ledger(Collection $incoming, Collection $spending, string $currency): array
    {
        $receipts = $incoming
            ->filter(fn (ProcurementDisbursement $row): bool => $this->paymentCurrency($row) === $currency)
            ->map(fn (ProcurementDisbursement $row): array => [
                'id' => 'receipt:'.$row->id,
                'date' => $row->recipient_confirmed_at?->toDateString(),
                'type' => 'restricted_fund_receipt',
                'reference' => $row->transfer_reference ?: $row->reference_no,
                'description' => 'ATTP Secretariat funding received',
                'debitAccount' => 'Cash and cash equivalents',
                'creditAccount' => 'Restricted ATTP funding',
                'amount' => $this->money($this->cents($row->amount)),
                'currency' => $currency,
                'sourceId' => (string) $row->id,
            ]);
        $expenses = $spending
            ->filter(fn (ProcurementDisbursement $row): bool => $this->paymentCurrency($row) === $currency)
            ->map(fn (ProcurementDisbursement $row): array => [
                'id' => 'expenditure:'.$row->id,
                'date' => $row->paid_at?->toDateString(),
                'type' => 'procurement_expenditure',
                'reference' => $row->reference_no,
                'description' => 'Recognized procurement expenditure',
                'debitAccount' => 'Programme expenditure',
                'creditAccount' => 'Cash and cash equivalents',
                'amount' => $this->money($this->cents($row->amount)),
                'currency' => $currency,
                'sourceId' => (string) $row->id,
            ]);

        return $receipts->concat($expenses)
            ->sortBy(fn (array $entry): string => ($entry['date'] ?? '').'|'.$entry['id'])
            ->values()
            ->all();
    }

    /** @param array<int, array<string, mixed>> $positions
     * @return array<int, array<string, mixed>>
     */
    private function reconciliationWarnings(array $positions): array
    {
        return collect($positions)->flatMap(function (array $position): array {
            $warnings = [];
            $currency = $position['currency'];
            $cash = $this->cents($position['cashBalance']);
            $budget = $this->cents($position['allocated']);
            $confirmed = $this->cents($position['received']);
            $available = $this->cents($position['available']);
            $pendingConfirmation = $this->cents($position['pendingConfirmation']);

            if ($currency === 'UNKNOWN') {
                $warnings[] = $this->warning(
                    'UNKNOWN_CURRENCY',
                    'critical',
                    'Records with no explicit currency are isolated from named-currency balances.',
                    $currency,
                );
            }
            if ($cash < 0) {
                $warnings[] = $this->warning(
                    'NEGATIVE_CASH_POSITION',
                    'critical',
                    'Recognized expenditure exceeds recognized receipts in this currency.',
                    $currency,
                );
            }
            if ($currency === 'USD' && $budget > $confirmed) {
                $warnings[] = $this->warning(
                    'BUDGET_EXCEEDS_CONFIRMED_RECEIPTS',
                    'critical',
                    'Active top-level internal budget lines exceed confirmed USD receipts.',
                    $currency,
                );
            }
            if ($available < 0) {
                $warnings[] = $this->warning(
                    'EXECUTION_EXCEEDS_INTERNAL_BUDGET',
                    'critical',
                    'Procurement commitments or recognized expenditure exceed active internal budget lines.',
                    $currency,
                );
            }
            if ($pendingConfirmation > 0) {
                $warnings[] = $this->warning(
                    'RECEIPTS_AWAITING_CONFIRMATION',
                    'warning',
                    'One or more Secretariat-posted transfers await recipient confirmation.',
                    $currency,
                );
            }

            return $warnings;
        })->values()->all();
    }

    /** @return array<string, string> */
    private function emptyPosition(string $currency): array
    {
        return [
            'currency' => $currency,
            'allocated' => '0.00',
            'received' => '0.00',
            'pendingConfirmation' => '0.00',
            'requested' => '0.00',
            'committed' => '0.00',
            'settledCommitments' => '0.00',
            'outstandingCommitments' => '0.00',
            'spent' => '0.00',
            'available' => '0.00',
            'cashBalance' => '0.00',
        ];
    }

    /** @return array<string, mixed> */
    private function warning(
        string $code,
        string $severity,
        string $message,
        ?string $currency = null,
        ?string $sourceId = null,
    ): array {
        return array_filter([
            'code' => $code,
            'severity' => $severity,
            'message' => $message,
            'currency' => $currency,
            'sourceId' => $sourceId,
        ], fn ($value): bool => $value !== null);
    }

    /**
     * @param  array<int, string>  $statusFields
     * @param  array<int, string>  $searchFields
     */
    private function filterResources(
        Collection $resources,
        array $filters,
        array $statusFields,
        array $searchFields,
    ): Collection {
        $query = Str::lower(trim((string) ($filters['q'] ?? '')));
        $status = Str::lower(trim((string) ($filters['status'] ?? '')));
        $currency = Str::upper(trim((string) ($filters['currency'] ?? '')));

        return $resources->filter(function (array $resource) use (
            $query,
            $status,
            $currency,
            $statusFields,
            $searchFields,
        ): bool {
            if ($currency !== '' && Str::upper((string) ($resource['currency'] ?? '')) !== $currency) {
                return false;
            }

            if ($status !== '' && ! collect($statusFields)->contains(
                fn (string $field): bool => Str::lower((string) ($resource[$field] ?? '')) === $status
            )) {
                return false;
            }

            if ($query !== '' && ! collect($searchFields)->contains(
                fn (string $field): bool => str_contains(
                    Str::lower((string) ($resource[$field] ?? '')),
                    $query,
                )
            )) {
                return false;
            }

            return true;
        })->values();
    }

    /** @return array{items: array<int, array<string, mixed>>, pagination: array<string, int|null>} */
    private function paginate(Collection $items, array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 25)));
        $total = $items->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $slice = $items->slice(($page - 1) * $perPage, $perPage)->values();
        $from = $slice->isEmpty() ? null : (($page - 1) * $perPage) + 1;
        $to = $slice->isEmpty() ? null : $from + $slice->count() - 1;

        return [
            'items' => $slice->all(),
            'pagination' => [
                'currentPage' => $page,
                'lastPage' => $lastPage,
                'perPage' => $perPage,
                'total' => $total,
                'from' => $from,
                'to' => $to,
            ],
        ];
    }

    /** @param array<string, mixed> $snapshot
     * @return array<int, array{value: string, label: string}>
     */
    private function reportPeriodOptions(array $snapshot): array
    {
        return $snapshot['incoming']
            ->map(fn (ProcurementDisbursement $row): ?string => $row->recipient_confirmation_status === 'confirmed'
                && $row->recipient_confirmed_at !== null
                && $row->recipient_confirmed_at->lte(now())
                ? $row->recipient_confirmed_at->format('Y')
                : $row->paid_at?->format('Y'))
            ->merge($snapshot['spending']->map(fn (ProcurementDisbursement $row): ?string => $row->paid_at?->format('Y')))
            ->merge($snapshot['commitments']->map(
                fn (ProcurementPurchaseOrder $row): ?string => ($row->issued_at ?: $row->created_at)?->format('Y')
            ))
            ->merge($snapshot['requests']->map(fn (ConsortiumDisbursementRequest $row): ?string => $row->requested_at?->format('Y')))
            ->merge($snapshot['budgetLines']->map(
                fn (ThinkTankBudgetLine $line): ?string => ($year = $this->fiscalCalendarYear((string) $line->fiscal_year)) !== null
                    ? (string) $year
                    : null
            ))
            ->push(now()->format('Y'))
            ->filter(fn ($year): bool => is_string($year) && preg_match('/^\d{4}$/', $year) === 1)
            ->unique()
            ->sortDesc()
            ->map(fn (string $year): array => ['value' => $year, 'label' => 'Reporting year '.$year])
            ->values()
            ->all();
    }

    private function fiscalCalendarYear(string $fiscalYear): ?int
    {
        return preg_match('/^(20\d{2})(?:\/\d{2})?$/', trim($fiscalYear), $matches) === 1
            ? (int) $matches[1]
            : null;
    }

    /**
     * Apply one accounting-date policy to current and period commitment views.
     * Legacy issued purchase orders may have no issued_at value; for those
     * records the immutable creation timestamp is the recognition boundary.
     */
    private function constrainCommitmentDates(
        Builder $query,
        mixed $through,
        mixed $from = null,
    ): Builder {
        return $query->where(function (Builder $dates) use ($through, $from): void {
            $dates->where(function (Builder $issued) use ($through, $from): void {
                $issued->whereNotNull('issued_at')
                    ->where('issued_at', '<=', $through);
                if ($from !== null) {
                    $issued->where('issued_at', '>=', $from);
                }
            })->orWhere(function (Builder $legacy) use ($through, $from): void {
                $legacy->whereNull('issued_at')
                    ->where('created_at', '<=', $through);
                if ($from !== null) {
                    $legacy->where('created_at', '>=', $from);
                }
            });
        });
    }

    /** @return array{id: string, name: string, consortiumId: string|null} */
    private function tenant(ConsortiumThinkTank $member): array
    {
        return [
            'id' => (string) $member->id,
            'name' => (string) $member->name,
            'consortiumId' => $member->consortium_id ? (string) $member->consortium_id : null,
        ];
    }

    private function paymentCurrency(ProcurementDisbursement $payment): string
    {
        $payment->loadMissing('purchaseOrder');

        return $this->currency($payment->currency, $payment->purchaseOrder?->currency);
    }

    private function purchaseOrderCurrency(ProcurementPurchaseOrder $order): string
    {
        return $this->currency($order->currency);
    }

    private function currency(mixed $primary, mixed $fallback = null): string
    {
        foreach ([$primary, $fallback] as $candidate) {
            $currency = Str::upper(trim((string) $candidate));
            if (preg_match('/^[A-Z]{3}$/', $currency) === 1) {
                return $currency;
            }
        }

        return 'UNKNOWN';
    }

    private function cents(mixed $amount): int
    {
        $value = trim((string) ($amount ?? '0'));
        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $value, $matches)) {
            return 0;
        }

        $fraction = substr(str_pad($matches[3] ?? '', 2, '0'), 0, 2);
        $cents = ((int) $matches[2] * 100) + (int) $fraction;

        return ($matches[1] ?? '') === '-' ? -$cents : $cents;
    }

    private function money(int $cents): string
    {
        $negative = $cents < 0;
        $absolute = abs($cents);
        $money = intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);

        return $negative ? '-'.$money : $money;
    }

    private function sumMoneyFields(Collection $rows, string $field): int
    {
        return $rows->sum(fn (array $row): int => $this->cents($row[$field] ?? '0'));
    }

    private function fundingRequestStatusLabel(string $status): string
    {
        return match ($status) {
            'submitted' => 'Awaiting Secretariat review',
            'under_review' => 'Under Secretariat review',
            'approved' => 'Approved',
            'partially_paid' => 'Partially paid',
            'paid' => 'Paid',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
            default => Str::headline($status),
        };
    }

    private function nextFundingRequestCode(): string
    {
        do {
            $code = 'TT-FR-'.now()->format('Y').'-'.Str::upper(Str::random(8));
        } while (ConsortiumDisbursementRequest::query()->where('request_code', $code)->exists());

        return $code;
    }
}
