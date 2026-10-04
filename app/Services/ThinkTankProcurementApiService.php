<?php

namespace App\Services;

use App\Exceptions\ThinkTankApiException;
use App\Models\ConsortiumThinkTank;
use App\Models\ProcurementDisbursement;
use App\Models\ProcurementPurchaseOrder;
use App\Models\ThinkTankProcurementDocument;
use App\Models\ThinkTankProcurementItem;
use App\Models\ThinkTankProcurementPlan;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ThinkTankProcurementApiService
{
    public const THRESHOLD_USD = 10_000.00;

    public const BAND_BELOW = 'below_10000';

    public const BAND_AT_OR_ABOVE = 'at_or_above_10000';

    public const BAND_CURRENCY_REVIEW = 'currency_review';

    private const COMMITTED_PURCHASE_ORDER_STATUSES = ['issued', 'closed'];

    /** @var array<string, array{label: string, aliases: array<int, string>, milestones: array<int, array{key: string, label: string, planned_writable: bool}>}> */
    private const METHOD_TEMPLATES = [
        'rfq' => [
            'label' => 'Request for Quotations (RFQ)',
            'aliases' => ['rfq', 'request for quotations'],
            'milestones' => [
                ['key' => 'draft_request_for_quotations', 'label' => 'Draft Request for Quotations', 'planned_writable' => true],
                ['key' => 'specific_procurement_notice', 'label' => 'Specific Procurement Notice', 'planned_writable' => true],
                ['key' => 'invitation_to_supplier_contractor', 'label' => 'Invitation to Supplier / Contractor', 'planned_writable' => true],
                ['key' => 'amendments_to_request_for_quotations', 'label' => 'Amendments to Request for Quotations', 'planned_writable' => false],
                ['key' => 'receive_quotations', 'label' => 'Receive Quotations', 'planned_writable' => true],
                ['key' => 'comparison_of_quotations', 'label' => 'Comparison of Quotations', 'planned_writable' => true],
                ['key' => 'notification_of_intention_of_award', 'label' => 'Notification of Intention of Award', 'planned_writable' => true],
                ['key' => 'signed_contract', 'label' => 'Signed Contract', 'planned_writable' => true],
                ['key' => 'contract_amendments', 'label' => 'Contract Amendments', 'planned_writable' => false],
                ['key' => 'contract_completion', 'label' => 'Contract Completion', 'planned_writable' => true],
                ['key' => 'contract_termination', 'label' => 'Contract Termination', 'planned_writable' => false],
            ],
        ],
        'rfb' => [
            'label' => 'Request for Bids (RFB)',
            'aliases' => ['rfb', 'request for bids'],
            'milestones' => [
                ['key' => 'draft_prequalification_documents', 'label' => 'Draft Pre-qualification Documents', 'planned_writable' => true],
                ['key' => 'prequalification_notice', 'label' => 'Specific Procurement Notice (Pre-qualification)', 'planned_writable' => true],
                ['key' => 'amendments_to_prequalification_documents', 'label' => 'Amendments to Pre-qualification Documents', 'planned_writable' => false],
                ['key' => 'prequalification_opening_minutes', 'label' => 'Opening / Minutes of Pre-qualification', 'planned_writable' => true],
                ['key' => 'prequalification_evaluation_report', 'label' => 'Pre-qualification Evaluation Report', 'planned_writable' => true],
                ['key' => 'draft_bidding_documents', 'label' => 'Draft Bidding Documents', 'planned_writable' => true],
                ['key' => 'bidding_notice', 'label' => 'Specific Procurement Notice (Bidding)', 'planned_writable' => true],
                ['key' => 'invitation_to_providers', 'label' => 'Invitation to Providers', 'planned_writable' => true],
                ['key' => 'amendments_to_bidding_documents', 'label' => 'Amendments to Bidding Documents', 'planned_writable' => false],
                ['key' => 'bid_submission_opening_minutes', 'label' => 'Bid Submission / Opening / Minutes', 'planned_writable' => true],
                ['key' => 'bid_evaluation_and_award_recommendation', 'label' => 'Bid Evaluation Report and Recommendation for Award', 'planned_writable' => true],
                ['key' => 'notification_of_intention_of_award', 'label' => 'Notification of Intention of Award', 'planned_writable' => true],
                ['key' => 'signed_contract', 'label' => 'Signed Contract', 'planned_writable' => true],
                ['key' => 'contract_amendments', 'label' => 'Contract Amendments', 'planned_writable' => false],
                ['key' => 'contract_completion', 'label' => 'Contract Completion', 'planned_writable' => true],
                ['key' => 'contract_termination', 'label' => 'Contract Termination', 'planned_writable' => false],
            ],
        ],
        'qcbs_fbs_lcs' => [
            'label' => 'QCBS / FBS / LCS',
            'aliases' => [
                'qcbs', 'fbs', 'lcs', 'qcbs / fbs / lcs', 'qcbs/fbs/lcs',
                'quality and cost-based selection', 'fixed budget-based selection', 'least cost-based selection',
            ],
            'milestones' => [
                ['key' => 'tor', 'label' => 'Terms of Reference (TOR)', 'planned_writable' => true],
                ['key' => 'eoi', 'label' => 'Expression of Interest (EOI)', 'planned_writable' => true],
                ['key' => 'eoi_evaluation_and_shortlist', 'label' => 'EOI Evaluation and Shortlist', 'planned_writable' => true],
                ['key' => 'shortlist_and_draft_rfp', 'label' => 'Shortlist and Draft RFP', 'planned_writable' => true],
                ['key' => 'rfp_as_issued', 'label' => 'RFP as Issued', 'planned_writable' => true],
                ['key' => 'rfp_amendments', 'label' => 'RFP Amendments', 'planned_writable' => true],
                ['key' => 'technical_proposals_opening_minutes', 'label' => 'Technical Proposals Opening / Minutes', 'planned_writable' => true],
                ['key' => 'technical_evaluation', 'label' => 'Technical Evaluation', 'planned_writable' => true],
                ['key' => 'financial_proposals_opening_minutes', 'label' => 'Financial Proposals Opening / Minutes', 'planned_writable' => true],
                ['key' => 'combined_evaluation_and_draft_negotiated_contract', 'label' => 'Combined Evaluation Report and Draft Negotiated Contract', 'planned_writable' => true],
                ['key' => 'notification_of_intention_of_award', 'label' => 'Notification of Intention of Award', 'planned_writable' => true],
                ['key' => 'signed_contract', 'label' => 'Signed Contract', 'planned_writable' => true],
                ['key' => 'contract_amendments', 'label' => 'Contract Amendments', 'planned_writable' => false],
                ['key' => 'contract_completion', 'label' => 'Contract Completion', 'planned_writable' => true],
                ['key' => 'contract_termination', 'label' => 'Contract Termination', 'planned_writable' => false],
            ],
        ],
        'cqs' => [
            'label' => "Consultant's Qualifications-Based Selection (CQS)",
            'aliases' => ['cqs', "consultant's qualifications-based selection", 'consultants qualifications based selection'],
            'milestones' => [
                ['key' => 'tor', 'label' => 'Terms of Reference (TOR)', 'planned_writable' => true],
                ['key' => 'eoi', 'label' => 'Expression of Interest (EOI)', 'planned_writable' => true],
                ['key' => 'eoi_evaluation_and_shortlist', 'label' => 'EOI Evaluation and Shortlist', 'planned_writable' => true],
                ['key' => 'shortlist_and_draft_rfp', 'label' => 'Shortlist and Draft RFP', 'planned_writable' => true],
                ['key' => 'draft_negotiated_contract', 'label' => 'Draft Negotiated Contract', 'planned_writable' => true],
                ['key' => 'notification_of_intention_of_award', 'label' => 'Notification of Intention of Award', 'planned_writable' => true],
                ['key' => 'signed_contract', 'label' => 'Signed Contract', 'planned_writable' => true],
                ['key' => 'contract_amendments', 'label' => 'Contract Amendments', 'planned_writable' => false],
                ['key' => 'contract_completion', 'label' => 'Contract Completion', 'planned_writable' => true],
                ['key' => 'contract_termination', 'label' => 'Contract Termination', 'planned_writable' => false],
            ],
        ],
        'cds' => [
            'label' => 'Consultant Direct Selection (CDS)',
            'aliases' => ['cds', 'consultant direct selection'],
            'milestones' => [
                ['key' => 'tor', 'label' => 'Terms of Reference (TOR)', 'planned_writable' => true],
                ['key' => 'justification_for_direct_selection', 'label' => 'Justification for Direct Selection', 'planned_writable' => true],
                ['key' => 'invitation_to_selected_consultant', 'label' => 'Invitation to Identified / Selected Consultant', 'planned_writable' => true],
                ['key' => 'amendments_to_tor', 'label' => 'Amendments to TOR', 'planned_writable' => true],
                ['key' => 'draft_negotiated_contract', 'label' => 'Draft Negotiated Contract', 'planned_writable' => true],
                ['key' => 'notification_of_intention_of_award', 'label' => 'Notification of Intention of Award', 'planned_writable' => true],
                ['key' => 'signed_contract', 'label' => 'Signed Contract', 'planned_writable' => true],
                ['key' => 'contract_amendments', 'label' => 'Contract Amendments', 'planned_writable' => false],
                ['key' => 'contract_completion', 'label' => 'Contract Completion', 'planned_writable' => true],
                ['key' => 'contract_termination', 'label' => 'Contract Termination', 'planned_writable' => false],
            ],
        ],
        'indv' => [
            'label' => 'Individual Consultant Selection (INDV)',
            'aliases' => ['indv', 'individual consultant selection'],
            'milestones' => [
                ['key' => 'tor', 'label' => 'Terms of Reference (TOR)', 'planned_writable' => true],
                ['key' => 'eoi', 'label' => 'Expression of Interest (EOI)', 'planned_writable' => true],
                ['key' => 'eoi_evaluation_and_shortlist', 'label' => 'EOI Evaluation and Shortlist', 'planned_writable' => true],
                ['key' => 'justification_for_direct_selection', 'label' => 'Justification for Direct Selection', 'planned_writable' => true],
                ['key' => 'invitation_to_selected_consultant', 'label' => 'Invitation to Identified / Selected Consultant', 'planned_writable' => true],
                ['key' => 'draft_negotiated_contract', 'label' => 'Draft Negotiated Contract', 'planned_writable' => true],
                ['key' => 'notification_of_intention_of_award', 'label' => 'Notification of Intention of Award', 'planned_writable' => true],
                ['key' => 'signed_contract', 'label' => 'Signed Contract', 'planned_writable' => true],
                ['key' => 'contract_amendments', 'label' => 'Contract Amendments', 'planned_writable' => false],
                ['key' => 'contract_completion', 'label' => 'Contract Completion', 'planned_writable' => true],
                ['key' => 'contract_termination', 'label' => 'Contract Termination', 'planned_writable' => false],
            ],
        ],
        'direct_goods' => [
            'label' => 'Direct Selection - Goods',
            'aliases' => ['direct_goods', 'direct goods', 'direct selection - goods'],
            'milestones' => [
                ['key' => 'justification_for_direct_procurement', 'label' => 'Justification for Direct Procurement', 'planned_writable' => true],
                ['key' => 'invitation_to_supplier_contractor', 'label' => 'Invitation to Supplier / Contractor', 'planned_writable' => true],
                ['key' => 'draft_contract', 'label' => 'Draft Contract', 'planned_writable' => true],
                ['key' => 'notification_of_intention_of_award', 'label' => 'Notification of Intention of Award', 'planned_writable' => true],
                ['key' => 'signed_contract', 'label' => 'Signed Contract', 'planned_writable' => true],
                ['key' => 'contract_amendments', 'label' => 'Contract Amendments', 'planned_writable' => false],
                ['key' => 'contract_completion', 'label' => 'Contract Completion', 'planned_writable' => true],
            ],
        ],
        'direct_selection' => [
            'label' => 'Direct Selection',
            'aliases' => ['direct selection', 'direct procurement'],
            'milestones' => [
                ['key' => 'justification_for_direct_procurement', 'label' => 'Justification for Direct Procurement', 'planned_writable' => true],
                ['key' => 'invitation_to_supplier_contractor', 'label' => 'Invitation to Supplier / Contractor', 'planned_writable' => true],
                ['key' => 'draft_contract', 'label' => 'Draft Contract', 'planned_writable' => true],
                ['key' => 'notification_of_intention_of_award', 'label' => 'Notification of Intention of Award', 'planned_writable' => true],
                ['key' => 'signed_contract', 'label' => 'Signed Contract', 'planned_writable' => true],
                ['key' => 'contract_amendments', 'label' => 'Contract Amendments', 'planned_writable' => false],
                ['key' => 'contract_completion', 'label' => 'Contract Completion', 'planned_writable' => true],
                ['key' => 'contract_termination', 'label' => 'Contract Termination', 'planned_writable' => false],
            ],
        ],
    ];

    /** @return array<string, mixed> */
    public function thresholdBand(mixed $amount, ?string $currency): array
    {
        $currency = Str::upper(trim((string) $currency));
        if ($currency !== 'USD') {
            return [
                'code' => self::BAND_CURRENCY_REVIEW,
                'label' => 'Currency review required',
                'thresholdUsd' => self::THRESHOLD_USD,
                'amountUsd' => null,
                'isComparable' => false,
            ];
        }

        $amount = round((float) $amount, 2);
        $below = $amount < self::THRESHOLD_USD;

        return [
            'code' => $below ? self::BAND_BELOW : self::BAND_AT_OR_ABOVE,
            'label' => $below ? 'Below USD 10,000' : 'USD 10,000 and above',
            'thresholdUsd' => self::THRESHOLD_USD,
            'amountUsd' => $amount,
            'isComparable' => true,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function methodTemplates(): array
    {
        return collect(self::METHOD_TEMPLATES)
            ->map(fn (array $template, string $code): array => [
                'code' => $code,
                'label' => $template['label'],
                'milestones' => collect($template['milestones'])->map(fn (array $milestone): array => [
                    'key' => $milestone['key'],
                    'label' => $milestone['label'],
                    'plannedWritable' => $milestone['planned_writable'],
                ])->all(),
            ])
            ->values()
            ->all();
    }

    public function methodCode(string $value, ?string $category = null): ?string
    {
        $needle = Str::lower(trim($value));
        if (isset(self::METHOD_TEMPLATES[$needle])) {
            return $needle;
        }

        foreach (self::METHOD_TEMPLATES as $code => $template) {
            foreach ($template['aliases'] as $alias) {
                if ($needle === Str::lower($alias) || str_contains($needle, Str::lower($alias))) {
                    return $code;
                }
            }
        }

        return null;
    }

    public function methodLabel(string $code): string
    {
        return self::METHOD_TEMPLATES[$code]['label'] ?? Str::headline($code);
    }

    /** @return array<int, string> */
    public function acceptedMethodValues(): array
    {
        return collect(self::METHOD_TEMPLATES)
            ->flatMap(fn (array $template, string $code): array => [$code, $template['label'], ...$template['aliases']])
            ->unique(fn (string $value): string => Str::lower($value))
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    public function writableMilestoneKeys(string $methodCode): array
    {
        return collect(self::METHOD_TEMPLATES[$methodCode]['milestones'] ?? [])
            ->where('planned_writable', true)
            ->pluck('key')
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    public function milestoneKeys(string $methodCode): array
    {
        return collect(self::METHOD_TEMPLATES[$methodCode]['milestones'] ?? [])
            ->pluck('key')
            ->values()
            ->all();
    }

    /**
     * Replace only portal-writable planned dates. Imported actual dates and
     * unknown workbook columns remain byte-for-byte represented in the JSON.
     *
     * @param  array<int, mixed>|null  $existing
     * @param  array<string, mixed>  $plannedDates
     * @return array<int, mixed>
     */
    public function mergePlannedMilestones(?array $existing, string $methodCode, array $plannedDates): array
    {
        $template = collect(self::METHOD_TEMPLATES[$methodCode]['milestones'] ?? []);
        $writable = $template->where('planned_writable', true)->keyBy('key');
        $knownLabels = $template->mapWithKeys(fn (array $stage): array => [
            $this->milestoneKey($stage['label']) => $stage['key'],
        ]);

        $preserved = collect($existing ?? [])->filter(function (mixed $entry) use ($writable, $knownLabels): bool {
            if (! is_array($entry) || Str::lower((string) ($entry['timing'] ?? '')) !== 'planned') {
                return true;
            }

            $key = trim((string) ($entry['key'] ?? ''));
            if ($key === '') {
                $key = (string) $knownLabels->get($this->milestoneKey((string) ($entry['milestone'] ?? '')), '');
            }

            return ! $writable->has($key);
        })->values();

        foreach ($plannedDates as $key => $date) {
            if (! $writable->has((string) $key) || blank($date)) {
                continue;
            }

            $stage = $writable->get((string) $key);
            $preserved->push([
                'key' => (string) $key,
                'milestone' => $stage['label'],
                'timing' => 'planned',
                'value' => (string) $date,
                'date' => (string) $date,
                'source' => 'portal',
            ]);
        }

        return $preserved->all();
    }

    public function canonicalFiscalYear(string $value): string
    {
        $value = preg_replace('/\s+/', '', trim($value)) ?: '';
        if (preg_match('/^(20\d{2})(?:[\/-](?:20)?(\d{2}))?$/', $value, $matches)) {
            return isset($matches[2]) && $matches[2] !== ''
                ? $matches[1].'/'.$matches[2]
                : $matches[1];
        }

        return $value;
    }

    public function lockToken(Model $model): string
    {
        $version = max(1, (int) ($model->getAttribute('portal_lock_version') ?: 1));
        $attributes = $model->getAttributes();
        unset($attributes['portal_lock_version']);
        ksort($attributes);
        $stateFingerprint = hash('sha256', serialize($attributes));

        return hash_hmac(
            'sha256',
            $model::class.'|'.$model->getKey().'|'.$version.'|'.$stateFingerprint,
            (string) config('app.key'),
        );
    }

    public function assertLockToken(Model $model, mixed $provided): void
    {
        if (! is_string($provided) || ! hash_equals($this->lockToken($model), trim($provided))) {
            throw new ThinkTankApiException(
                'STALE_WRITE',
                'This procurement record changed after it was opened. Reload the latest version before saving.',
                409,
            );
        }
    }

    public function incrementLockVersion(Model $model): void
    {
        $model->increment('portal_lock_version');
        $model->refresh();
    }

    public function syncPlanBudget(ThinkTankProcurementPlan $plan): void
    {
        $currency = Str::upper(trim((string) $plan->currency));
        if ($currency !== 'USD') {
            return;
        }

        $amount = $plan->items()
            ->whereRaw("UPPER(TRIM(currency)) = 'USD'")
            ->sum('estimated_amount');

        $plan->forceFill(['estimated_budget' => $amount])->saveQuietly();
    }

    /** @return array<string, mixed> */
    public function planCard(
        ThinkTankProcurementPlan $plan,
        User $viewer,
        ?Collection $usageRows = null,
    ): array {
        if (! $plan->relationLoaded('items')) {
            $plan->load('items');
        }
        $usageRows ??= $this->usageRows(collect([$plan]));
        $rows = $usageRows->where('planId', (string) $plan->id);
        $money = $this->moneySummary($rows);
        $canManage = $viewer->hasPermission('think_tank.procurement_plans.manage');
        $sourceCurrency = Str::upper(trim((string) $plan->currency));
        $portalWritable = $sourceCurrency === 'USD';

        return [
            ...$money,
            'id' => (string) $plan->id,
            'planCode' => (string) $plan->plan_code,
            'title' => (string) $plan->title,
            'fiscalYear' => (string) ($plan->fiscal_year ?: ''),
            'plannedPublishDate' => $this->date($plan->planned_publish_date),
            'currency' => $sourceCurrency !== '' ? $sourceCurrency : 'UNKNOWN',
            'status' => (string) $plan->status,
            'statusLabel' => $this->planStatusLabel($plan->status),
            'itemCount' => $plan->items->count(),
            'aboveThresholdCount' => $rows->where('band', self::BAND_AT_OR_ABOVE)->count(),
            'belowThresholdCount' => $rows->where('band', self::BAND_BELOW)->count(),
            'currencyReviewCount' => $rows->where('band', self::BAND_CURRENCY_REVIEW)->count(),
            'canEdit' => $canManage && $plan->isEditable() && $portalWritable,
            'canSubmit' => $canManage
                && $plan->isEditable()
                && $portalWritable
                && $plan->items->isNotEmpty()
                && $rows->where('band', self::BAND_CURRENCY_REVIEW)->isEmpty(),
            'updatedAt' => $this->dateTime($plan->updated_at),
        ];
    }

    /** @return array<string, mixed> */
    public function planDetail(ThinkTankProcurementPlan $plan, User $viewer): array
    {
        $plan->loadMissing([
            'items.documents',
            'items.procurement',
            'items.noObjectionRecorder:id,name',
            'events.actor:id,name',
            'events.item:id,item_code,title',
        ]);
        $usageRows = $this->usageRows(collect([$plan]));
        $rowsByItem = $usageRows->keyBy('id');
        $canManage = $viewer->hasPermission('think_tank.procurement_plans.manage');
        $missingTor = $plan->items->filter(fn (ThinkTankProcurementItem $item): bool => ! $item->hasTermsOfReference());
        $blockedItems = $plan->items->whereIn('status', [
            ThinkTankProcurementItem::STATUS_REJECTED,
            ThinkTankProcurementItem::STATUS_NO_OBJECTION,
            ThinkTankProcurementItem::STATUS_PUBLISHED,
        ]);
        $currencyReviewItems = $plan->items->filter(
            fn (ThinkTankProcurementItem $item): bool => Str::upper(trim((string) $item->currency)) !== 'USD'
        );
        $currencyReady = Str::upper(trim((string) $plan->currency)) === 'USD' && $currencyReviewItems->isEmpty();

        return [
            ...$this->planCard($plan, $viewer, $usageRows),
            'description' => $plan->description ?: null,
            'lockToken' => $this->lockToken($plan),
            'reviewNotes' => $plan->review_notes ?: null,
            'decisionReason' => $plan->decision_reason ?: null,
            'submittedAt' => $this->dateTime($plan->submitted_at),
            'approvedAt' => $this->dateTime($plan->approved_at),
            'items' => $plan->items->map(fn (ThinkTankProcurementItem $item): array => $this->item(
                $item,
                $viewer,
                $rowsByItem->get((string) $item->id),
            ))->values()->all(),
            'events' => $plan->events->map(fn ($event): array => [
                'id' => (string) $event->id,
                'action' => (string) $event->action,
                'fromStatus' => $event->from_status ?: null,
                'toStatus' => $event->to_status ?: null,
                'reason' => $event->reason ?: null,
                'actor' => $event->actor?->name ?: null,
                'occurredAt' => $this->dateTime($event->created_at),
            ])->values()->all(),
            'permissions' => [
                'canView' => $viewer->hasPermission('think_tank.procurement_plans.view') || $canManage,
                'canManage' => $canManage,
            ],
            'submissionChecklist' => [
                [
                    'key' => 'items',
                    'label' => 'At least one procurement item',
                    'complete' => $plan->items->isNotEmpty(),
                    'message' => $plan->items->isNotEmpty()
                        ? $plan->items->count().' item(s) are included.'
                        : 'Add at least one procurement item.',
                ],
                [
                    'key' => 'terms_of_reference',
                    'label' => 'Terms of Reference for every item',
                    'complete' => $plan->items->isNotEmpty() && $missingTor->isEmpty(),
                    'message' => $missingTor->isEmpty()
                        ? 'Every item has a Terms of Reference document.'
                        : 'Missing TOR: '.$missingTor->pluck('item_code')->implode(', '),
                ],
                [
                    'key' => 'currency',
                    'label' => 'Explicit USD currency for the plan and every item',
                    'complete' => $currencyReady,
                    'message' => $currencyReady
                        ? 'The plan and every item are explicitly denominated in USD.'
                        : 'Currency review required: unknown or non-USD records cannot be submitted through the USD portal.',
                ],
                [
                    'key' => 'workflow_state',
                    'label' => 'Items are ready for submission',
                    'complete' => $blockedItems->isEmpty(),
                    'message' => $blockedItems->isEmpty()
                        ? 'No item is blocked by its current workflow state.'
                        : 'Correct rejected items; items already in execution cannot be resubmitted.',
                ],
            ],
        ];
    }

    /** @param array<string, mixed>|null $usageRow
     * @return array<string, mixed>
     */
    public function item(ThinkTankProcurementItem $item, User $viewer, ?array $usageRow = null): array
    {
        $item->loadMissing(['documents', 'procurement', 'plan', 'noObjectionRecorder:id,name']);
        $usageRow ??= $this->usageRows(collect([$item->plan]))->firstWhere('id', (string) $item->id) ?? [];
        $methodCode = $this->methodCode((string) $item->procurement_method, $item->procurement_category);
        $sourceCurrency = Str::upper(trim((string) $item->currency));
        $planCurrency = Str::upper(trim((string) $item->plan?->currency));
        $band = $this->thresholdBand(
            $item->estimated_amount,
            $sourceCurrency === 'USD' && $planCurrency === 'USD' ? 'USD' : null,
        );
        $canManage = $viewer->hasPermission('think_tank.procurement_plans.manage');

        return [
            ...$this->moneySummary(collect([$usageRow])),
            'id' => (string) $item->id,
            'itemCode' => (string) $item->item_code,
            'activityReference' => $item->source_reference ?: null,
            'activityDescription' => (string) $item->title,
            'inProcess' => $item->source_in_process ?: null,
            'loanCreditNo' => $item->loan_credit_no ?: null,
            'component' => $item->component ?: null,
            'reviewType' => $item->review_type ?: null,
            'category' => $item->procurement_category ?: null,
            'marketApproach' => $item->market_approach ?: null,
            'procurementMethod' => $item->procurement_method ?: null,
            'quantity' => $item->quantity !== null ? (float) $item->quantity : null,
            'unit' => $item->unit ?: null,
            'estimatedUnitCost' => $item->estimated_unit_cost !== null ? (float) $item->estimated_unit_cost : null,
            'estimatedAmountUsd' => $band['amountUsd'],
            'sourceAmount' => (float) $item->estimated_amount,
            'currency' => $sourceCurrency !== '' ? $sourceCurrency : 'UNKNOWN',
            'band' => $band['code'],
            'limitedSelectionJustification' => $item->limited_selection_justification ?: null,
            'highSeaShRisk' => $item->source_sea_sh_risk ?: null,
            'procurementDocumentType' => $item->source_document_type ?: null,
            'processStatus' => $item->source_process_status ?: null,
            'activityStatus' => $item->source_activity_status ?: $item->workflowActivityStatus(),
            'budgetReference' => $item->budget_reference ?: null,
            'bankComment' => $item->bank_comment ?: null,
            'actionTaken' => $item->action_taken ?: null,
            'status' => (string) $item->status,
            'statusLabel' => $this->itemStatusLabel($item->status),
            'reviewReason' => $item->review_reason ?: null,
            'roadmap' => $this->roadmap($item, $methodCode)['stages'],
            'documents' => $item->documents->map(fn (ThinkTankProcurementDocument $document): array => [
                'id' => (string) $document->id,
                'type' => (string) $document->document_type,
                'name' => (string) $document->original_name,
                'mimeType' => $document->mime_type ?: null,
                'size' => (int) $document->file_size,
                'uploadedAt' => $this->dateTime($document->created_at),
                'downloadUrl' => route('api.v1.think-tank.procurement.documents.show', [
                    'plan' => $item->plan_id,
                    'item' => $item->id,
                    'document' => $document->id,
                ], false),
            ])->values()->all(),
            'canEdit' => $canManage
                && $item->isEditable()
                && $sourceCurrency === 'USD'
                && $planCurrency === 'USD',
            'lockToken' => $this->lockToken($item),
            'updatedAt' => $this->dateTime($item->updated_at),
            // Additional read-only workflow context; it never changes the workbook fields above.
            'workflow' => [
                'reviewedAt' => $this->dateTime($item->reviewed_at),
                'stepReference' => $item->step_reference ?: null,
                'stepExportedAt' => $this->dateTime($item->step_exported_at),
                'noObjectionReference' => $item->no_objection_reference ?: null,
                'noObjectionDate' => $this->date($item->no_objection_date),
                'noObjectionNotes' => $item->no_objection_notes ?: null,
                'noObjectionRecordedAt' => $this->dateTime($item->no_objection_recorded_at),
                'isReadyToExecute' => in_array($item->status, [
                    ThinkTankProcurementItem::STATUS_NO_OBJECTION,
                    ThinkTankProcurementItem::STATUS_PUBLISHED,
                ], true),
                'readyToExecuteAt' => $this->dateTime($item->no_objection_recorded_at),
                'readyToExecuteBy' => $item->noObjectionRecorder ? [
                    'id' => (string) $item->noObjectionRecorder->id,
                    'name' => (string) $item->noObjectionRecorder->name,
                ] : null,
                'execution' => $item->procurement ? [
                    'id' => (string) $item->procurement->id,
                    'reference' => $item->procurement->reference_no ?: null,
                    'status' => (string) $item->procurement->status,
                    'applicationStartDate' => $this->date($item->procurement->application_start_date),
                    'applicationEndDate' => $this->date($item->procurement->application_end_date),
                ] : null,
            ],
        ];
    }

    /** @param array<string, string> $filters
     * @return array<string, mixed>
     */
    public function usageReport(ConsortiumThinkTank $member, Collection $plans, User $viewer, array $filters): array
    {
        $rows = $this->usageRows($plans);
        $query = Str::lower(trim((string) ($filters['q'] ?? '')));
        $fiscalYear = trim((string) ($filters['fiscal_year'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $band = trim((string) ($filters['band'] ?? ''));

        $baseFiltered = $rows->filter(function (array $row) use ($query, $fiscalYear, $status): bool {
            if ($fiscalYear !== '' && $row['fiscalYear'] !== $fiscalYear) {
                return false;
            }
            if ($status !== '' && $row['status'] !== $status) {
                return false;
            }
            if ($query !== '') {
                $haystack = Str::lower(implode(' ', [
                    $row['planCode'], $row['itemCode'], $row['activityReference'],
                    $row['activityDescription'], $row['procurementMethod'],
                ]));
                if (! str_contains($haystack, $query)) {
                    return false;
                }
            }

            return true;
        })->values();
        $filtered = $band === ''
            ? $baseFiltered
            : $baseFiltered->where('band', $band)->values();
        $currencyReview = $baseFiltered->where('currencyReviewRequired', true)->values();
        $money = $this->moneySummary($filtered);
        $canManage = $viewer->hasPermission('think_tank.procurement_plans.manage');

        return [
            'permissions' => [
                'canView' => $viewer->hasPermission('think_tank.procurement_plans.view') || $canManage,
                'canManage' => $canManage,
            ],
            'threshold' => ['currency' => 'USD', 'amount' => self::THRESHOLD_USD, 'rule' => 'gte_above'],
            'summary' => [
                ...$money,
                'itemCount' => $filtered->count(),
                'currencyReviewCount' => $currencyReview->count(),
            ],
            'rows' => $filtered->map(fn (array $row): array => $this->publicUsageRow($row))->all(),
            'currencyReview' => $currencyReview
                ->map(fn (array $row): array => $this->publicUsageRow($row))->values()->all(),
            'filters' => [
                'query' => (string) ($filters['q'] ?? ''),
                'fiscalYear' => (string) ($filters['fiscal_year'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
                'band' => (string) ($filters['band'] ?? ''),
            ],
            'options' => [
                'fiscalYears' => $plans->pluck('fiscal_year')->filter()->unique()->sortDesc()->values()
                    ->map(fn ($year): array => ['value' => (string) $year, 'label' => (string) $year])->all(),
                'statuses' => $this->itemStatusOptions(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function overview(ConsortiumThinkTank $member, Collection $plans, User $viewer): array
    {
        $plans->each(function (ThinkTankProcurementPlan $plan): void {
            if (! $plan->relationLoaded('items')) {
                $plan->load('items');
            }
        });
        $rows = $this->usageRows($plans);
        $money = $this->moneySummary($rows);
        $canManage = $viewer->hasPermission('think_tank.procurement_plans.manage');

        return [
            'permissions' => [
                'canView' => $viewer->hasPermission('think_tank.procurement_plans.view') || $canManage,
                'canManage' => $canManage,
            ],
            'summary' => [
                ...$money,
                'planCount' => $plans->count(),
                'draftPlanCount' => $plans->where('status', ThinkTankProcurementPlan::STATUS_DRAFT)->count(),
                'submittedPlanCount' => $plans->where('status', ThinkTankProcurementPlan::STATUS_SUBMITTED)->count(),
                'approvedPlanCount' => $plans->where('status', ThinkTankProcurementPlan::STATUS_APPROVED)->count(),
                'actionRequiredCount' => $plans->whereIn('status', [
                    ThinkTankProcurementPlan::STATUS_REVISION_REQUESTED,
                    ThinkTankProcurementPlan::STATUS_REJECTED,
                ])->count(),
                'itemCount' => $rows->count(),
                'currencyReviewCount' => $rows->where('currencyReviewRequired', true)->count(),
            ],
            'bands' => collect([
                self::BAND_BELOW => 'Below USD 10,000',
                self::BAND_AT_OR_ABOVE => 'USD 10,000 and above',
                self::BAND_CURRENCY_REVIEW => 'Currency review required',
            ])->map(function (string $label, string $band) use ($rows): array {
                $bandRows = $rows->where('band', $band);

                return [
                    ...$this->moneySummary($bandRows),
                    'band' => $band,
                    'label' => $label,
                    'itemCount' => $bandRows->count(),
                ];
            })->values()->all(),
            'statuses' => collect($this->planStatusOptions())->map(fn (array $option): array => [
                'status' => $option['value'],
                'label' => $option['label'],
                'count' => $plans->where('status', $option['value'])->count(),
            ])->all(),
            'recentPlans' => $plans->sortByDesc('updated_at')->take(5)
                ->map(fn (ThinkTankProcurementPlan $plan): array => $this->planCard($plan, $viewer, $rows))->values()->all(),
        ];
    }

    /** @param Collection<int, ThinkTankProcurementPlan> $allPlans
     * @param  array<string, string>  $filters
     * @return array<string, mixed>
     */
    public function planRegister(
        LengthAwarePaginator $paginator,
        Collection $allPlans,
        User $viewer,
        array $filters,
    ): array {
        $allPlans->loadMissing('items');
        $pagePlans = $paginator->getCollection();
        $pagePlans->loadMissing('items');
        $allRows = $this->usageRows($allPlans);
        $pageRows = $allRows->whereIn('planId', $pagePlans->pluck('id')->map(fn ($id): string => (string) $id));
        $money = $this->moneySummary($allRows);
        $canManage = $viewer->hasPermission('think_tank.procurement_plans.manage');

        return [
            'permissions' => [
                'canView' => $viewer->hasPermission('think_tank.procurement_plans.view') || $canManage,
                'canManage' => $canManage,
            ],
            'summary' => [
                ...$money,
                'planCount' => $allPlans->count(),
                'itemCount' => $allRows->count(),
                'aboveThresholdCount' => $allRows->where('band', self::BAND_AT_OR_ABOVE)->count(),
                'belowThresholdCount' => $allRows->where('band', self::BAND_BELOW)->count(),
                'currencyReviewCount' => $allRows->where('currencyReviewRequired', true)->count(),
            ],
            'plans' => $pagePlans->map(fn (ThinkTankProcurementPlan $plan): array => $this->planCard($plan, $viewer, $pageRows))->values()->all(),
            'filters' => [
                'query' => (string) ($filters['q'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
                'fiscalYear' => (string) ($filters['fiscal_year'] ?? ''),
                'band' => (string) ($filters['band'] ?? ''),
            ],
            'options' => [
                'statuses' => $this->planStatusOptions(),
                'fiscalYears' => $allPlans->pluck('fiscal_year')->filter()->unique()->sortDesc()->values()
                    ->map(fn ($year): array => ['value' => (string) $year, 'label' => (string) $year])->all(),
            ],
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function roadmap(ThinkTankProcurementItem $item, ?string $methodCode): array
    {
        $template = collect($methodCode ? (self::METHOD_TEMPLATES[$methodCode]['milestones'] ?? []) : []);
        $legacy = collect($item->planned_milestones ?? []);
        $mappedKeys = collect();

        $stages = $template->map(function (array $stage) use ($legacy, $mappedKeys): array {
            $matches = $legacy->filter(function (mixed $entry) use ($stage): bool {
                if (! is_array($entry)) {
                    return false;
                }

                $entryKey = trim((string) ($entry['key'] ?? ''));

                return $entryKey === $stage['key']
                    || $this->milestoneKey((string) ($entry['milestone'] ?? '')) === $this->milestoneKey($stage['label']);
            });
            $matches->keys()->each(fn ($key) => $mappedKeys->push($key));
            $planned = $matches->first(fn (array $entry): bool => Str::lower((string) ($entry['timing'] ?? '')) === 'planned');
            $actual = $matches->first(fn (array $entry): bool => Str::lower((string) ($entry['timing'] ?? '')) === 'actual');

            return [
                'key' => $stage['key'],
                'label' => $stage['label'],
                'plannedDate' => $this->milestoneValue($planned),
                'actualDate' => $this->milestoneValue($actual),
            ];
        });

        $unmapped = $legacy->reject(fn ($entry, $key): bool => $mappedKeys->contains($key))
            ->filter(fn ($entry): bool => is_array($entry))
            ->groupBy(fn (array $entry): string => (string) ($entry['milestone'] ?? 'Imported milestone'))
            ->map(function (Collection $entries, string $label): array {
                $planned = $entries->first(fn (array $entry): bool => Str::lower((string) ($entry['timing'] ?? '')) === 'planned');
                $actual = $entries->first(fn (array $entry): bool => Str::lower((string) ($entry['timing'] ?? '')) === 'actual');

                return [
                    'key' => 'legacy_'.$this->milestoneKey($label),
                    'label' => $label,
                    'plannedDate' => $this->milestoneValue($planned),
                    'actualDate' => $this->milestoneValue($actual),
                ];
            })->values();

        return [
            'methodCode' => $methodCode,
            'methodLabel' => $methodCode ? self::METHOD_TEMPLATES[$methodCode]['label'] : ($item->procurement_method ?: 'Imported method'),
            'stages' => $stages->concat($unmapped)->values()->all(),
        ];
    }

    /** @param Collection<int, ThinkTankProcurementPlan> $plans
     * @return Collection<int, array<string, mixed>>
     */
    private function usageRows(Collection $plans): Collection
    {
        $plans->each(function (ThinkTankProcurementPlan $plan): void {
            if (! $plan->relationLoaded('items')) {
                $plan->load('items');
            }
        });
        $items = $plans->flatMap(fn (ThinkTankProcurementPlan $plan) => $plan->items)->values();
        $plansById = $plans->keyBy(fn (ThinkTankProcurementPlan $plan): string => (string) $plan->id);
        $procurementIds = $items->pluck('procurement_id')->filter()->unique()->values();

        $purchaseOrders = $procurementIds->isEmpty()
            ? collect()
            : ProcurementPurchaseOrder::query()
                ->whereIn('procurement_id', $procurementIds)
                ->whereIn('status', self::COMMITTED_PURCHASE_ORDER_STATUSES)
                ->with([
                    'purchaseRequest.programFunding.program',
                    'budgetCommitment.programFunding.program',
                    'budgetCommitment.purchaseRequest.programFunding.program',
                ])
                ->get();

        $payments = $procurementIds->isEmpty()
            ? collect()
            : ProcurementDisbursement::query()
                ->recognizedPayment()
                ->where(function ($query) use ($procurementIds): void {
                    $query->whereIn('procurement_id', $procurementIds)
                        ->orWhereHas('purchaseOrder', fn ($orders) => $orders->whereIn('procurement_id', $procurementIds));
                })
                ->with([
                    'purchaseOrder.purchaseRequest.programFunding.program',
                    'purchaseOrder.budgetCommitment.programFunding.program',
                    'purchaseOrder.budgetCommitment.purchaseRequest.programFunding.program',
                ])
                ->get();

        $approvedStatuses = [
            ThinkTankProcurementItem::STATUS_APPROVED,
            ThinkTankProcurementItem::STATUS_NO_OBJECTION,
            ThinkTankProcurementItem::STATUS_PUBLISHED,
        ];

        return $items->map(function (ThinkTankProcurementItem $item) use (
            $plansById,
            $purchaseOrders,
            $payments,
            $approvedStatuses,
        ): array {
            /** @var ThinkTankProcurementPlan $plan */
            $plan = $plansById->get((string) $item->plan_id);
            $procurementId = $item->procurement_id ? (string) $item->procurement_id : null;
            $orders = $procurementId
                ? $purchaseOrders->where('procurement_id', $procurementId)
                : collect();
            $itemPayments = $procurementId
                ? $payments->filter(fn (ProcurementDisbursement $payment): bool => (string) (
                    $payment->procurement_id ?: $payment->purchaseOrder?->procurement_id
                ) === $procurementId)
                : collect();
            $sourceCurrency = Str::upper(trim((string) $item->currency));
            $displayCurrency = $sourceCurrency !== '' ? $sourceCurrency : 'UNKNOWN';
            $planCurrency = Str::upper(trim((string) $plan->currency));
            $displayPlanCurrency = $planCurrency !== '' ? $planCurrency : 'UNKNOWN';
            $isExplicitUsd = $sourceCurrency === 'USD' && $planCurrency === 'USD';
            $band = $this->thresholdBand($item->estimated_amount, $isExplicitUsd ? 'USD' : null);
            $usdOrders = $orders->filter(fn (ProcurementPurchaseOrder $order): bool => $this->strictPurchaseOrderCurrency($order) === 'USD');
            $usdPayments = $itemPayments->filter(fn (ProcurementDisbursement $payment): bool => $this->strictPaymentCurrency($payment) === 'USD');
            $nonUsdOrders = $orders->reject(fn (ProcurementPurchaseOrder $order): bool => $this->strictPurchaseOrderCurrency($order) === 'USD');
            $nonUsdPayments = $itemPayments->reject(fn (ProcurementDisbursement $payment): bool => $this->strictPaymentCurrency($payment) === 'USD');
            $amountUsd = $isExplicitUsd ? round((float) $item->estimated_amount, 2) : 0.0;

            return [
                'plannedUsd' => $amountUsd,
                'approvedUsd' => in_array($item->status, $approvedStatuses, true) ? $amountUsd : 0.0,
                'committedUsd' => $isExplicitUsd ? round((float) $usdOrders->sum('amount'), 2) : 0.0,
                'paidUsd' => $isExplicitUsd ? round((float) $usdPayments->sum('amount'), 2) : 0.0,
                'id' => (string) $item->id,
                'planId' => (string) $plan->id,
                'planCode' => (string) $plan->plan_code,
                'fiscalYear' => (string) ($plan->fiscal_year ?: ''),
                'itemCode' => (string) $item->item_code,
                'activityReference' => $item->source_reference ?: null,
                'activityDescription' => (string) $item->title,
                'procurementMethod' => $item->procurement_method ?: null,
                'reviewType' => $item->review_type ?: null,
                'currency' => $displayCurrency,
                'estimatedAmountUsd' => $band['amountUsd'],
                'sourceAmount' => (float) $item->estimated_amount,
                'band' => $band['code'],
                'status' => (string) $item->status,
                'statusLabel' => $this->itemStatusLabel($item->status),
                'activityStatus' => $item->source_activity_status ?: $item->workflowActivityStatus(),
                'approvalReference' => $item->no_objection_reference ?: $item->step_reference ?: null,
                'approvedAt' => $this->dateTime($item->no_objection_recorded_at ?: $item->reviewed_at),
                'currencyReviewRequired' => ! $isExplicitUsd || $nonUsdOrders->isNotEmpty() || $nonUsdPayments->isNotEmpty(),
                'currencyReviewContexts' => [
                    'plan' => $planCurrency !== 'USD' ? [
                        'label' => (string) $plan->plan_code,
                        'currency' => $displayPlanCurrency,
                    ] : null,
                    'item' => $sourceCurrency !== 'USD' ? [
                        'label' => (string) $item->item_code,
                        'currency' => $displayCurrency,
                    ] : null,
                    'commitments' => $nonUsdOrders->map(fn (ProcurementPurchaseOrder $order): array => [
                        'label' => $order->reference_no ?: 'Purchase order',
                        'currency' => $this->strictPurchaseOrderCurrency($order) ?: 'UNKNOWN',
                    ])->values()->all(),
                    'payments' => $nonUsdPayments->map(fn (ProcurementDisbursement $payment): array => [
                        'label' => $payment->reference_no ?: 'Payment',
                        'currency' => $this->strictPaymentCurrency($payment) ?: 'UNKNOWN',
                    ])->values()->all(),
                ],
            ];
        })->values();
    }

    private function strictPurchaseOrderCurrency(ProcurementPurchaseOrder $order): ?string
    {
        if (! $order->relationLoaded('purchaseRequest') || ! $order->relationLoaded('budgetCommitment')) {
            $order->loadMissing([
                'purchaseRequest.programFunding.program',
                'budgetCommitment.programFunding.program',
                'budgetCommitment.purchaseRequest.programFunding.program',
            ]);
        }

        $commitment = $order->budgetCommitment;
        $request = $order->purchaseRequest ?: $commitment?->purchaseRequest;

        return $this->firstExplicitCurrency([
            $order->getRawOriginal('currency'),
            $request?->programFunding?->program?->getRawOriginal('currency'),
            $request?->programFunding?->getRawOriginal('currency'),
            $request?->getRawOriginal('currency'),
            $commitment?->programFunding?->program?->getRawOriginal('currency'),
            $commitment?->programFunding?->getRawOriginal('currency'),
            $commitment?->purchaseRequest?->programFunding?->program?->getRawOriginal('currency'),
            $commitment?->purchaseRequest?->programFunding?->getRawOriginal('currency'),
            $commitment?->purchaseRequest?->getRawOriginal('currency'),
        ]);
    }

    private function strictPaymentCurrency(ProcurementDisbursement $payment): ?string
    {
        return $this->firstExplicitCurrency([
            $payment->getRawOriginal('currency'),
            $payment->purchaseOrder ? $this->strictPurchaseOrderCurrency($payment->purchaseOrder) : null,
        ]);
    }

    /** @param array<int, mixed> $candidates */
    private function firstExplicitCurrency(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $currency = Str::upper(trim((string) $candidate));
            if ($currency !== '') {
                return $currency;
            }
        }

        return null;
    }

    /** @param Collection<int, array<string, mixed>> $rows
     * @return array{plannedUsd: float, approvedUsd: float, committedUsd: float, paidUsd: float}
     */
    private function moneySummary(Collection $rows): array
    {
        return [
            'plannedUsd' => round((float) $rows->sum('plannedUsd'), 2),
            'approvedUsd' => round((float) $rows->sum('approvedUsd'), 2),
            'committedUsd' => round((float) $rows->sum('committedUsd'), 2),
            'paidUsd' => round((float) $rows->sum('paidUsd'), 2),
        ];
    }

    /** @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function publicUsageRow(array $row): array
    {
        return collect($row)->only([
            'plannedUsd', 'approvedUsd', 'committedUsd', 'paidUsd',
            'id', 'planId', 'planCode', 'fiscalYear', 'itemCode',
            'activityReference', 'activityDescription', 'procurementMethod', 'reviewType',
            'currency', 'estimatedAmountUsd', 'sourceAmount', 'band', 'status',
            'statusLabel', 'activityStatus', 'approvalReference', 'approvedAt',
            'currencyReviewContexts',
        ])->all();
    }

    /** @return array<int, array{value: string, label: string}> */
    private function planStatusOptions(): array
    {
        return collect([
            ThinkTankProcurementPlan::STATUS_DRAFT,
            ThinkTankProcurementPlan::STATUS_SUBMITTED,
            ThinkTankProcurementPlan::STATUS_REVISION_REQUESTED,
            ThinkTankProcurementPlan::STATUS_REJECTED,
            ThinkTankProcurementPlan::STATUS_APPROVED,
        ])->map(fn (string $status): array => ['value' => $status, 'label' => $this->planStatusLabel($status)])->all();
    }

    /** @return array<int, array{value: string, label: string}> */
    private function itemStatusOptions(): array
    {
        return collect([
            ThinkTankProcurementItem::STATUS_DRAFT,
            ThinkTankProcurementItem::STATUS_SUBMITTED,
            ThinkTankProcurementItem::STATUS_REVISION_REQUESTED,
            ThinkTankProcurementItem::STATUS_REJECTED,
            ThinkTankProcurementItem::STATUS_APPROVED,
            ThinkTankProcurementItem::STATUS_NO_OBJECTION,
            ThinkTankProcurementItem::STATUS_PUBLISHED,
        ])->map(fn (string $status): array => ['value' => $status, 'label' => $this->itemStatusLabel($status)])->all();
    }

    private function milestoneKey(string $label): string
    {
        $normalized = Str::of($label)
            ->lower()
            ->replaceMatches('/\((?:tor|eoi)\)/', '')
            ->replace(['terms of reference', 'expression of interest'], ['tor', 'eoi'])
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_');

        return (string) $normalized;
    }

    /** @param array<string, mixed>|null $entry */
    private function milestoneValue(?array $entry): ?string
    {
        if (! $entry) {
            return null;
        }

        $value = trim((string) ($entry['date'] ?? $entry['value'] ?? ''));

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function planStatusLabel(?string $status): string
    {
        return match ($status) {
            ThinkTankProcurementPlan::STATUS_SUBMITTED => 'Sent to AUC-ATTP Secretariat',
            ThinkTankProcurementPlan::STATUS_REVISION_REQUESTED => 'Revision requested',
            ThinkTankProcurementPlan::STATUS_REJECTED => 'Rejected - action required',
            ThinkTankProcurementPlan::STATUS_APPROVED => 'Approved by AUC-ATTP Secretariat',
            default => 'Draft',
        };
    }

    private function itemStatusLabel(?string $status): string
    {
        return match ($status) {
            ThinkTankProcurementItem::STATUS_SUBMITTED => 'Sent to AUC-ATTP Secretariat',
            ThinkTankProcurementItem::STATUS_REVISION_REQUESTED => 'Revision requested',
            ThinkTankProcurementItem::STATUS_REJECTED => 'Rejected - action required',
            ThinkTankProcurementItem::STATUS_APPROVED => 'Pending World Bank no-objection',
            ThinkTankProcurementItem::STATUS_NO_OBJECTION => 'No-objection received — ready to execute',
            ThinkTankProcurementItem::STATUS_PUBLISHED => 'Published for applications',
            default => 'Draft',
        };
    }

    private function date(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        return method_exists($value, 'toDateString') ? $value->toDateString() : (string) $value;
    }

    private function dateTime(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        return method_exists($value, 'toIso8601String') ? $value->toIso8601String() : (string) $value;
    }
}
