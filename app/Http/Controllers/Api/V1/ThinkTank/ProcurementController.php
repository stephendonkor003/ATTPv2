<?php

namespace App\Http\Controllers\Api\V1\ThinkTank;

use App\Exceptions\ThinkTankApiException;
use App\Http\Controllers\Controller;
use App\Models\ConsortiumThinkTank;
use App\Models\SystemAuditLog;
use App\Models\ThinkTankProcurementDocument;
use App\Models\ThinkTankProcurementItem;
use App\Models\ThinkTankProcurementPlan;
use App\Services\ThinkTankProcurementApiService;
use App\Services\ThinkTankProcurementWorkflowService;
use App\Support\ThinkTankApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ProcurementController extends Controller
{
    private const MAX_DOCUMENTS_PER_REQUEST = 20;

    private const MAX_DOCUMENT_BYTES_PER_REQUEST = 60 * 1024 * 1024;

    private const PLAN_STATUSES = [
        ThinkTankProcurementPlan::STATUS_DRAFT,
        ThinkTankProcurementPlan::STATUS_SUBMITTED,
        ThinkTankProcurementPlan::STATUS_REVISION_REQUESTED,
        ThinkTankProcurementPlan::STATUS_REJECTED,
        ThinkTankProcurementPlan::STATUS_APPROVED,
    ];

    private const ITEM_STATUSES = [
        ThinkTankProcurementItem::STATUS_DRAFT,
        ThinkTankProcurementItem::STATUS_SUBMITTED,
        ThinkTankProcurementItem::STATUS_REVISION_REQUESTED,
        ThinkTankProcurementItem::STATUS_REJECTED,
        ThinkTankProcurementItem::STATUS_APPROVED,
        ThinkTankProcurementItem::STATUS_NO_OBJECTION,
        ThinkTankProcurementItem::STATUS_PUBLISHED,
    ];

    private const BANDS = [
        ThinkTankProcurementApiService::BAND_BELOW,
        ThinkTankProcurementApiService::BAND_AT_OR_ABOVE,
        ThinkTankProcurementApiService::BAND_CURRENCY_REVIEW,
    ];

    public function __construct(
        private readonly ThinkTankProcurementApiService $api,
        private readonly ThinkTankProcurementWorkflowService $workflow,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $this->rejectUnexpectedQuery($request, []);
        $member = $this->member($request);
        $plans = ThinkTankProcurementPlan::query()
            ->where('think_tank_member_id', $member->id)
            ->with('items')
            ->latest('updated_at')
            ->get();

        return ThinkTankApiResponse::success($this->api->overview($member, $plans, $request->user()));
    }

    public function index(Request $request): JsonResponse
    {
        $this->rejectUnexpectedQuery($request, ['q', 'status', 'fiscal_year', 'band', 'page']);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(self::PLAN_STATUSES)],
            'fiscal_year' => ['nullable', 'string', 'max:20'],
            'band' => ['nullable', Rule::in(self::BANDS)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $member = $this->member($request);
        $base = ThinkTankProcurementPlan::query()->where('think_tank_member_id', $member->id);
        $allPlans = (clone $base)->with('items')->latest('updated_at')->get();
        $query = trim((string) ($filters['q'] ?? ''));

        $plans = (clone $base)
            ->with('items')
            ->when(filled($filters['status'] ?? null), fn ($builder) => $builder->where('status', $filters['status']))
            ->when(filled($filters['fiscal_year'] ?? null), fn ($builder) => $builder->where('fiscal_year', $filters['fiscal_year']))
            ->when($query !== '', function ($builder) use ($query): void {
                $search = '%'.Str::lower($query).'%';
                $builder->where(function ($nested) use ($search): void {
                    $nested->whereRaw('LOWER(title) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(plan_code) LIKE ?', [$search]);
                });
            })
            ->when(filled($filters['band'] ?? null), function ($builder) use ($filters): void {
                $band = $filters['band'];
                if ($band === ThinkTankProcurementApiService::BAND_CURRENCY_REVIEW) {
                    $builder->whereHas('items')
                        ->where(function ($plans): void {
                            $plans->whereNull('currency')
                                ->orWhereRaw("TRIM(currency) = ''")
                                ->orWhereRaw("UPPER(TRIM(currency)) <> 'USD'")
                                ->orWhereHas('items', fn ($items) => $items
                                    ->whereNull('currency')
                                    ->orWhereRaw("TRIM(currency) = ''")
                                    ->orWhereRaw("UPPER(TRIM(currency)) <> 'USD'"));
                        });

                    return;
                }

                $builder->whereRaw("UPPER(TRIM(currency)) = 'USD'")
                    ->whereHas('items', fn ($items) => $items
                        ->whereRaw("UPPER(TRIM(currency)) = 'USD'")
                        ->where(
                            'estimated_amount',
                            $band === ThinkTankProcurementApiService::BAND_BELOW ? '<' : '>=',
                            ThinkTankProcurementApiService::THRESHOLD_USD,
                        ));
            })
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        return ThinkTankApiResponse::success($this->api->planRegister(
            $plans,
            $allPlans,
            $request->user(),
            $filters,
        ));
    }

    public function storePlan(Request $request): JsonResponse
    {
        $this->assertJsonObject($request);
        $this->rejectUnexpectedBody($request, [
            'title', 'fiscal_year', 'planned_publish_date', 'description', 'currency',
        ]);
        $data = $request->validate($this->planRules(false));
        $member = $this->member($request);
        $fiscalYear = $this->validatedFiscalYear((string) $data['fiscal_year']);

        $plan = DB::transaction(function () use ($request, $member, $data, $fiscalYear): ThinkTankProcurementPlan {
            $lockedMember = ConsortiumThinkTank::query()->whereKey($member->id)->lockForUpdate()->firstOrFail();
            $this->assertNoOtherActivePlan($lockedMember, $fiscalYear);
            $plan = ThinkTankProcurementPlan::query()->create([
                'consortium_id' => $lockedMember->consortium_id,
                'think_tank_member_id' => $lockedMember->id,
                'plan_code' => $this->newPlanCode($fiscalYear),
                'title' => trim((string) $data['title']),
                'fiscal_year' => $fiscalYear,
                'estimated_budget' => 0,
                'currency' => 'USD',
                'planned_publish_date' => $data['planned_publish_date'] ?? null,
                'description' => $this->nullableText($data['description'] ?? null),
                'status' => ThinkTankProcurementPlan::STATUS_DRAFT,
                'version' => 1,
                'portal_lock_version' => 1,
                'created_by' => $request->user()->id,
            ]);
            $this->workflow->event($plan, null, $request->user(), 'plan_folder_created', null, $plan->status);

            return $plan;
        });

        return ThinkTankApiResponse::success(
            $this->api->planDetail($this->ownedPlan($request, (string) $plan->id), $request->user()),
            201,
            'Annual procurement plan created.',
        );
    }

    public function showPlan(Request $request, string $plan): JsonResponse
    {
        $this->rejectUnexpectedQuery($request, []);

        return ThinkTankApiResponse::success(
            $this->api->planDetail($this->ownedPlan($request, $plan), $request->user()),
        );
    }

    public function updatePlan(Request $request, string $plan): JsonResponse
    {
        $this->assertJsonObject($request);
        $this->rejectUnexpectedBody($request, [
            'title', 'fiscal_year', 'planned_publish_date', 'description', 'currency', 'lock_token',
        ]);
        $data = $request->validate($this->planRules(true));
        $member = $this->member($request);
        $fiscalYear = $this->validatedFiscalYear((string) $data['fiscal_year']);

        DB::transaction(function () use ($request, $member, $plan, $data, $fiscalYear): void {
            $lockedMember = ConsortiumThinkTank::query()->whereKey($member->id)->lockForUpdate()->firstOrFail();
            $locked = ThinkTankProcurementPlan::query()
                ->where('think_tank_member_id', $lockedMember->id)
                ->whereKey($plan)
                ->lockForUpdate()
                ->firstOrFail();
            $this->api->assertLockToken($locked, $data['lock_token']);
            abort_unless($locked->isEditable(), 422, 'This plan is locked while under review or after approval.');
            $this->assertPortalWritablePlanCurrency($locked);
            $this->assertNoOtherActivePlan($lockedMember, $fiscalYear, (string) $locked->id);

            $locked->update([
                'title' => trim((string) $data['title']),
                'fiscal_year' => $fiscalYear,
                'currency' => 'USD',
                'planned_publish_date' => $data['planned_publish_date'] ?? null,
                'description' => $this->nullableText($data['description'] ?? null),
            ]);
            $this->workflow->event($locked, null, $request->user(), 'plan_folder_updated', $locked->status, $locked->status);
            $this->api->incrementLockVersion($locked);
        });

        return ThinkTankApiResponse::success(
            $this->api->planDetail($this->ownedPlan($request, $plan), $request->user()),
            200,
            'Annual procurement plan updated.',
        );
    }

    public function submitPlan(Request $request, string $plan): JsonResponse
    {
        $this->assertJsonObject($request);
        $this->rejectUnexpectedBody($request, ['lock_token']);
        $data = $request->validate(['lock_token' => ['required', 'string', 'size:64']]);
        $member = $this->member($request);

        DB::transaction(function () use ($request, $member, $plan, $data): void {
            $lockedMember = ConsortiumThinkTank::query()->whereKey($member->id)->lockForUpdate()->firstOrFail();
            $locked = ThinkTankProcurementPlan::query()
                ->where('think_tank_member_id', $lockedMember->id)
                ->whereKey($plan)
                ->lockForUpdate()
                ->firstOrFail();
            $this->api->assertLockToken($locked, $data['lock_token']);
            $this->assertPortalWritablePlanCurrency($locked);
            $this->assertNoOtherActivePlan($lockedMember, (string) $locked->fiscal_year, (string) $locked->id);
            $lockedItems = ThinkTankProcurementItem::query()
                ->where('plan_id', $locked->id)
                ->lockForUpdate()
                ->get();
            if ($lockedItems->contains(fn (ThinkTankProcurementItem $item): bool => Str::upper(trim((string) $item->currency)) !== 'USD')) {
                throw new ThinkTankApiException(
                    'CURRENCY_REVIEW_REQUIRED',
                    'Resolve every unknown or non-USD item currency before submitting this plan through the portal.',
                    422,
                );
            }

            $this->workflow->submit($locked, $request->user());
            $this->api->incrementLockVersion($locked);
            $lockedItems->each(fn (ThinkTankProcurementItem $item) => $this->api->incrementLockVersion($item));
        });

        return ThinkTankApiResponse::success(
            $this->api->planDetail($this->ownedPlan($request, $plan), $request->user()),
            200,
            'Annual procurement plan submitted for ATTP review.',
        );
    }

    public function storeItem(Request $request, string $plan): JsonResponse
    {
        $this->assertMultipart($request);
        $this->normalizePlannedMilestones($request);
        $this->rejectUnexpectedBody($request, $this->itemBodyKeys());
        $data = $this->validateItem($request);
        $member = $this->member($request);
        $storedPaths = [];

        try {
            DB::transaction(function () use ($request, $member, $plan, $data, &$storedPaths): void {
                $locked = ThinkTankProcurementPlan::query()
                    ->where('think_tank_member_id', $member->id)
                    ->whereKey($plan)
                    ->lockForUpdate()
                    ->firstOrFail();
                $this->api->assertLockToken($locked, $data['lock_token']);
                abort_unless($locked->isEditable(), 422, 'Items cannot be added while this plan is under review or approved.');
                $this->assertPortalWritablePlanCurrency($locked);

                $methodCode = $this->validatedMethodCode($data);
                $item = $locked->items()->create([
                    ...$this->itemAttributes($data),
                    'item_code' => $this->workflow->nextItemCode($locked),
                    'procurement_method' => $this->api->methodLabel($methodCode),
                    'estimated_amount' => $this->calculatedAmount($data),
                    'currency' => 'USD',
                    'planned_milestones' => $this->api->mergePlannedMilestones(
                        null,
                        $methodCode,
                        $this->plannedMilestoneMap($data['planned_milestones'] ?? []),
                    ),
                    'status' => ThinkTankProcurementItem::STATUS_DRAFT,
                    'source_activity_status' => ThinkTankProcurementItem::ACTIVITY_STATUS_DRAFT,
                    'portal_lock_version' => 1,
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);
                $this->storeItemDocuments($request, $item, $storedPaths);
                $this->api->syncPlanBudget($locked);
                $this->workflow->event($locked, $item, $request->user(), 'item_created', null, $item->status, null, [
                    'estimated_amount_usd' => (float) $item->estimated_amount,
                    'threshold_band' => $this->api->thresholdBand($item->estimated_amount, 'USD')['code'],
                ]);
                $this->api->incrementLockVersion($locked);
            });
        } catch (Throwable $exception) {
            $this->deleteStoredPaths($storedPaths);
            throw $exception;
        }

        return ThinkTankApiResponse::success(
            $this->api->planDetail($this->ownedPlan($request, $plan), $request->user()),
            201,
            'Procurement item added.',
        );
    }

    public function updateItem(Request $request, string $plan, string $item): JsonResponse
    {
        $this->assertMultipart($request);
        $this->normalizePlannedMilestones($request);
        $this->rejectUnexpectedBody($request, $this->itemBodyKeys());
        $data = $this->validateItem($request);
        $member = $this->member($request);
        $storedPaths = [];

        try {
            DB::transaction(function () use ($request, $member, $plan, $item, $data, &$storedPaths): void {
                $lockedPlan = ThinkTankProcurementPlan::query()
                    ->where('think_tank_member_id', $member->id)
                    ->whereKey($plan)
                    ->lockForUpdate()
                    ->firstOrFail();
                $lockedItem = ThinkTankProcurementItem::query()
                    ->where('plan_id', $lockedPlan->id)
                    ->whereKey($item)
                    ->lockForUpdate()
                    ->firstOrFail();
                $this->api->assertLockToken($lockedItem, $data['lock_token']);
                abort_unless($lockedItem->isEditable(), 422, 'This item is locked while under review or after approval.');
                $this->assertPortalWritablePlanCurrency($lockedPlan);
                $this->assertPortalWritableItemCurrency($lockedItem);

                $methodCode = $this->validatedMethodCode($data);
                $previousStatus = $lockedItem->status;
                $lockedItem->update([
                    ...$this->itemAttributes($data),
                    'procurement_method' => $this->api->methodLabel($methodCode),
                    'estimated_amount' => $this->calculatedAmount($data),
                    'currency' => 'USD',
                    'planned_milestones' => array_key_exists('planned_milestones', $data)
                        ? $this->api->mergePlannedMilestones(
                            $lockedItem->planned_milestones,
                            $methodCode,
                            $this->plannedMilestoneMap($data['planned_milestones']),
                        )
                        : $lockedItem->planned_milestones,
                    'status' => ThinkTankProcurementItem::STATUS_DRAFT,
                    'review_reason' => null,
                    'updated_by' => $request->user()->id,
                ]);
                $this->storeItemDocuments($request, $lockedItem, $storedPaths);
                $this->api->syncPlanBudget($lockedPlan);
                $this->workflow->event($lockedPlan, $lockedItem, $request->user(), 'item_corrected', $previousStatus, $lockedItem->status, null, [
                    'estimated_amount_usd' => (float) $lockedItem->estimated_amount,
                    'threshold_band' => $this->api->thresholdBand($lockedItem->estimated_amount, 'USD')['code'],
                ]);
                $this->api->incrementLockVersion($lockedItem);
                $this->api->incrementLockVersion($lockedPlan);
            });
        } catch (Throwable $exception) {
            $this->deleteStoredPaths($storedPaths);
            throw $exception;
        }

        return ThinkTankApiResponse::success(
            $this->api->planDetail($this->ownedPlan($request, $plan), $request->user()),
            200,
            'Procurement item updated.',
        );
    }

    public function destroyItem(Request $request, string $plan, string $item): JsonResponse
    {
        $this->assertJsonObject($request);
        $this->rejectUnexpectedBody($request, ['lock_token']);
        $data = $request->validate(['lock_token' => ['required', 'string', 'size:64']]);
        $member = $this->member($request);
        $directory = null;

        DB::transaction(function () use ($request, $member, $plan, $item, $data, &$directory): void {
            $lockedPlan = ThinkTankProcurementPlan::query()
                ->where('think_tank_member_id', $member->id)
                ->whereKey($plan)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedItem = ThinkTankProcurementItem::query()
                ->where('plan_id', $lockedPlan->id)
                ->whereKey($item)
                ->lockForUpdate()
                ->firstOrFail();
            $this->api->assertLockToken($lockedItem, $data['lock_token']);
            abort_unless($lockedItem->isEditable(), 422, 'This item cannot be removed in its current state.');
            $this->assertPortalWritablePlanCurrency($lockedPlan);
            $this->assertPortalWritableItemCurrency($lockedItem);
            $payload = ['item_code' => $lockedItem->item_code, 'title' => $lockedItem->title, 'status' => $lockedItem->status];
            $directory = "think-tank-procurement/{$lockedPlan->id}/{$lockedItem->id}";
            $lockedItem->delete();
            $this->api->syncPlanBudget($lockedPlan);
            $this->workflow->event($lockedPlan, null, $request->user(), 'item_removed', null, null, null, $payload);
            $this->api->incrementLockVersion($lockedPlan);
        });

        if (is_string($directory) && $directory !== '') {
            Storage::disk('local')->deleteDirectory($directory);
        }

        return ThinkTankApiResponse::success(
            $this->api->planDetail($this->ownedPlan($request, $plan), $request->user()),
            200,
            'Procurement item removed.',
        );
    }

    public function document(Request $request, string $plan, string $item, string $document): BinaryFileResponse
    {
        $this->rejectUnexpectedQuery($request, []);
        [$ownedItem, $ownedDocument] = $this->ownedDocument($request, $plan, $item, $document);
        $path = $this->verifiedDocumentPath($ownedItem, $ownedDocument);
        try {
            SystemAuditLog::create([
                'user_id' => $request->user()?->id,
                'module' => 'think_tank_procurement',
                'action' => 'procurement_private_document_downloaded',
                'action_message' => 'Private procurement document downloaded',
                'description' => $ownedItem->item_code.' document accessed',
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'route_name' => $request->route()?->getName(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status_code' => 200,
                'payload' => [
                    'plan_id' => $plan,
                    'item_id' => $ownedItem->id,
                    'document_id' => $ownedDocument->id,
                    'document_type' => $ownedDocument->document_type,
                ],
            ]);
        } catch (Throwable) {
            // The private file remains authorized by the tenant-bound query if
            // the secondary audit store is temporarily unavailable.
        }
        $response = response()->download(
            Storage::disk('local')->path($path),
            basename((string) $ownedDocument->original_name),
            [
                'Content-Type' => 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
            ],
            'attachment',
        );
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    public function destroyDocument(Request $request, string $plan, string $item, string $document): JsonResponse
    {
        $this->assertJsonObject($request);
        $this->rejectUnexpectedBody($request, ['lock_token']);
        $data = $request->validate(['lock_token' => ['required', 'string', 'size:64']]);
        $member = $this->member($request);
        $path = null;

        DB::transaction(function () use ($request, $member, $plan, $item, $document, $data, &$path): void {
            $lockedPlan = ThinkTankProcurementPlan::query()
                ->where('think_tank_member_id', $member->id)
                ->whereKey($plan)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedItem = ThinkTankProcurementItem::query()
                ->where('plan_id', $lockedPlan->id)
                ->whereKey($item)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedDocument = ThinkTankProcurementDocument::query()
                ->where('item_id', $lockedItem->id)
                ->whereKey($document)
                ->lockForUpdate()
                ->firstOrFail();
            $this->api->assertLockToken($lockedItem, $data['lock_token']);
            abort_unless($lockedItem->isEditable(), 422, 'Documents are locked in the current workflow state.');
            $this->assertPortalWritablePlanCurrency($lockedPlan);
            $this->assertPortalWritableItemCurrency($lockedItem);
            $path = $this->verifiedDocumentPath($lockedItem, $lockedDocument, false);
            $name = $lockedDocument->original_name;
            $lockedDocument->delete();
            $this->workflow->event($lockedPlan, $lockedItem, $request->user(), 'item_document_removed', $lockedItem->status, $lockedItem->status, null, [
                'document_name' => $name,
            ]);
            $this->api->incrementLockVersion($lockedItem);
            $this->api->incrementLockVersion($lockedPlan);
        });

        if (is_string($path) && $path !== '') {
            Storage::disk('local')->delete($path);
        }

        return ThinkTankApiResponse::success(
            $this->api->planDetail($this->ownedPlan($request, $plan), $request->user()),
            200,
            'Procurement document removed.',
        );
    }

    public function usageApprovals(Request $request): JsonResponse
    {
        $this->rejectUnexpectedQuery($request, ['q', 'fiscal_year', 'status', 'band']);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'fiscal_year' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', Rule::in(self::ITEM_STATUSES)],
            'band' => ['nullable', Rule::in(self::BANDS)],
        ]);
        $member = $this->member($request);
        $plans = ThinkTankProcurementPlan::query()
            ->where('think_tank_member_id', $member->id)
            ->with('items')
            ->latest('updated_at')
            ->get();

        return ThinkTankApiResponse::success(
            $this->api->usageReport($member, $plans, $request->user(), $filters),
        );
    }

    /** @return array<string, array<int, mixed>> */
    private function planRules(bool $updating): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'fiscal_year' => ['required', 'string', 'max:20'],
            'planned_publish_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:5000'],
            'currency' => ['required', Rule::in(['USD'])],
            ...($updating ? ['lock_token' => ['required', 'string', 'size:64']] : []),
        ];
    }

    /** @return array<int, string> */
    private function itemBodyKeys(): array
    {
        return [
            '_method', 'lock_token', 'source_reference', 'title', 'description', 'source_in_process',
            'loan_credit_no', 'component', 'review_type', 'procurement_category', 'market_approach',
            'procurement_method', 'quantity', 'unit', 'estimated_unit_cost', 'estimated_amount_usd',
            'limited_selection_justification', 'source_sea_sh_risk', 'source_document_type',
            'source_process_status', 'budget_reference', 'action_taken', 'planned_quarter',
            'planned_start_date', 'planned_end_date', 'planned_milestones', 'tor', 'tor_documents',
            'supporting_documents',
        ];
    }

    /** @return array<string, mixed> */
    private function validateItem(Request $request): array
    {
        $data = $request->validate([
            'lock_token' => ['required', 'string', 'size:64'],
            'source_reference' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'source_in_process' => ['nullable', Rule::in(['Yes', 'No'])],
            'loan_credit_no' => ['nullable', 'string', 'max:255'],
            'component' => ['nullable', 'string', 'max:5000'],
            'review_type' => ['required', Rule::in(['Prior', 'Post'])],
            'procurement_category' => ['required', Rule::in([
                'goods', 'works', 'consulting_services', 'non_consulting_services', 'training', 'other',
            ])],
            'market_approach' => ['required', Rule::in([
                'Open - International', 'Open - National', 'Limited - International',
                'Limited - National', 'Direct - International', 'Direct - National',
            ])],
            'procurement_method' => ['required', 'string', 'max:120'],
            'quantity' => ['nullable', 'numeric', 'decimal:0,4', 'min:0.0001', 'max:99999999999999.9999'],
            'unit' => ['nullable', 'string', 'max:60'],
            'estimated_unit_cost' => ['nullable', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999999999.99'],
            'estimated_amount_usd' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999999999.99'],
            'limited_selection_justification' => ['nullable', 'string', 'max:20000'],
            'source_sea_sh_risk' => ['nullable', Rule::in(['Yes', 'No', 'Not Applicable'])],
            'source_document_type' => ['required', 'string', 'max:255'],
            'source_process_status' => ['required', 'string', 'max:255'],
            'budget_reference' => ['nullable', 'string', 'max:50000'],
            'action_taken' => ['nullable', 'string', 'max:20000'],
            'planned_quarter' => ['nullable', Rule::in(['Q1', 'Q2', 'Q3', 'Q4'])],
            'planned_start_date' => ['nullable', 'date'],
            'planned_end_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
            'planned_milestones' => ['nullable', 'array', 'max:20'],
            'planned_milestones.*.key' => ['required', 'string', 'max:100'],
            'planned_milestones.*.plannedDate' => ['nullable', 'date'],
            'tor' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:20480'],
            'tor_documents' => ['nullable', 'array', 'max:20'],
            'tor_documents.*' => ['file', 'mimes:pdf,doc,docx', 'max:20480'],
            'supporting_documents' => ['nullable', 'array', 'max:20'],
            'supporting_documents.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,jpg,jpeg,png,zip', 'max:20480'],
        ]);

        $torCount = ($request->hasFile('tor') ? 1 : 0) + count($request->file('tor_documents', []));
        $supportingCount = count($request->file('supporting_documents', []));
        if ($torCount > 20) {
            throw ValidationException::withMessages([
                'tor_documents' => ['A maximum of 20 Terms of Reference files may be uploaded in one request.'],
            ]);
        }
        if ($torCount + $supportingCount > self::MAX_DOCUMENTS_PER_REQUEST) {
            throw ValidationException::withMessages([
                'supporting_documents' => ['A maximum of 20 TOR and supporting files may be uploaded in one request.'],
            ]);
        }

        $files = collect($request->file('tor_documents', []))
            ->merge($request->file('supporting_documents', []));
        if ($request->hasFile('tor')) {
            $files->prepend($request->file('tor'));
        }
        $combinedBytes = $files->sum(fn ($file): int => max(0, (int) $file->getSize()));
        if ($combinedBytes > self::MAX_DOCUMENT_BYTES_PER_REQUEST) {
            throw ValidationException::withMessages([
                'supporting_documents' => ['The combined TOR and supporting files may not exceed 60 MB in one request.'],
            ]);
        }

        foreach (($data['planned_milestones'] ?? []) as $index => $milestone) {
            $unexpected = array_diff(array_keys($milestone), ['key', 'plannedDate']);
            if ($unexpected !== []) {
                throw ValidationException::withMessages([
                    "planned_milestones.{$index}" => ['Only a milestone key and planned date may be submitted. Actual dates are read-only.'],
                ]);
            }
        }

        $methodCode = $this->validatedMethodCode($data);
        $allowedKeys = $this->api->writableMilestoneKeys($methodCode);
        $knownKeys = $this->api->milestoneKeys($methodCode);
        foreach (($data['planned_milestones'] ?? []) as $index => $milestone) {
            $key = (string) $milestone['key'];
            if (! in_array($key, $knownKeys, true)) {
                throw ValidationException::withMessages([
                    "planned_milestones.{$index}.key" => ['This milestone does not belong to the selected procurement method.'],
                ]);
            }

            // Actual-only workbook stages are accepted but never made
            // client-writable. mergePlannedMilestones preserves any imported
            // actual value and ignores the submitted planned date.
            if (! in_array($key, $allowedKeys, true)) {
                unset($data['planned_milestones'][$index]);
            }
        }
        if (isset($data['planned_milestones'])) {
            $data['planned_milestones'] = array_values($data['planned_milestones']);
        }

        if (isset($data['quantity'], $data['estimated_unit_cost'])) {
            $calculated = round((float) $data['quantity'] * (float) $data['estimated_unit_cost'], 2);
            $entered = round((float) $data['estimated_amount_usd'], 2);
            if (abs($calculated - $entered) > 0.01) {
                throw ValidationException::withMessages([
                    'estimated_amount_usd' => [
                        'The estimated amount must match quantity multiplied by estimated unit cost when both supplemental values are supplied.',
                    ],
                ]);
            }
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function validatedMethodCode(array $data): string
    {
        $code = $this->api->methodCode((string) $data['procurement_method'], (string) $data['procurement_category']);
        if (! $code) {
            throw ValidationException::withMessages([
                'procurement_method' => ['Select a procurement method supported by the annual-plan workbook.'],
            ]);
        }

        return $code;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function itemAttributes(array $data): array
    {
        return collect($data)->only([
            'source_reference', 'title', 'description', 'source_in_process', 'loan_credit_no',
            'component', 'review_type', 'procurement_category', 'market_approach', 'quantity', 'unit',
            'estimated_unit_cost', 'limited_selection_justification', 'source_sea_sh_risk',
            'source_document_type', 'source_process_status', 'budget_reference', 'action_taken',
            'planned_quarter', 'planned_start_date', 'planned_end_date',
        ])->map(fn ($value) => is_string($value) ? $this->nullableText($value) : $value)->all();
    }

    /** @param array<string, mixed> $data */
    private function calculatedAmount(array $data): float
    {
        return round((float) $data['estimated_amount_usd'], 2);
    }

    /** @param array<int, array<string, mixed>> $milestones
     * @return array<string, mixed>
     */
    private function plannedMilestoneMap(array $milestones): array
    {
        return collect($milestones)->mapWithKeys(fn (array $milestone): array => [
            (string) $milestone['key'] => $milestone['plannedDate'] ?? null,
        ])->all();
    }

    private function normalizePlannedMilestones(Request $request): void
    {
        $value = $request->input('planned_milestones');
        if (! is_string($value)) {
            return;
        }

        $decoded = json_decode($value, true);
        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw ValidationException::withMessages([
                'planned_milestones' => ['Planned milestones must be a JSON array.'],
            ]);
        }

        $request->merge(['planned_milestones' => $decoded]);
    }

    private function storeItemDocuments(Request $request, ThinkTankProcurementItem $item, array &$storedPaths): void
    {
        $files = [];
        if ($request->hasFile('tor')) {
            $files[] = ['type' => 'tor', 'name' => 'Terms of Reference', 'file' => $request->file('tor')];
        }
        foreach ($request->file('tor_documents', []) as $file) {
            $files[] = ['type' => 'tor', 'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 'file' => $file];
        }
        foreach ($request->file('supporting_documents', []) as $file) {
            $files[] = ['type' => 'supporting', 'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 'file' => $file];
        }

        foreach ($files as $entry) {
            $file = $entry['file'];
            $path = $file->store("think-tank-procurement/{$item->plan_id}/{$item->id}", 'local');
            if (! $path) {
                throw new ThinkTankApiException('DOCUMENT_STORAGE_FAILED', 'A procurement document could not be stored.', 500);
            }
            $storedPaths[] = $path;
            $item->documents()->create([
                'document_type' => $entry['type'],
                'document_name' => $entry['name'],
                'original_name' => basename($file->getClientOriginalName()),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => (int) $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }
    }

    /** @param array<int, string> $paths */
    private function deleteStoredPaths(array $paths): void
    {
        foreach ($paths as $path) {
            Storage::disk('local')->delete($path);
        }
    }

    private function ownedPlan(Request $request, string $id): ThinkTankProcurementPlan
    {
        return ThinkTankProcurementPlan::query()
            ->where('think_tank_member_id', $this->member($request)->id)
            ->whereKey($id)
            ->firstOrFail();
    }

    /** @return array{0: ThinkTankProcurementItem, 1: ThinkTankProcurementDocument} */
    private function ownedDocument(Request $request, string $plan, string $item, string $document): array
    {
        $ownedPlan = $this->ownedPlan($request, $plan);
        $ownedItem = ThinkTankProcurementItem::query()
            ->where('plan_id', $ownedPlan->id)
            ->whereKey($item)
            ->firstOrFail();
        $ownedDocument = ThinkTankProcurementDocument::query()
            ->where('item_id', $ownedItem->id)
            ->whereKey($document)
            ->firstOrFail();

        return [$ownedItem, $ownedDocument];
    }

    private function verifiedDocumentPath(
        ThinkTankProcurementItem $item,
        ThinkTankProcurementDocument $document,
        bool $mustExist = true,
    ): string {
        $path = str_replace('\\', '/', trim((string) $document->file_path));
        $expected = "think-tank-procurement/{$item->plan_id}/{$item->id}/";
        abort_unless(
            $path !== ''
                && ! str_contains($path, '..')
                && str_starts_with($path, $expected)
                && (! $mustExist || Storage::disk('local')->exists($path)),
            404,
        );

        return $path;
    }

    private function member(Request $request): ConsortiumThinkTank
    {
        $member = $request->attributes->get('think_tank.membership');
        abort_unless($member instanceof ConsortiumThinkTank, 403);

        return $member;
    }

    private function assertNoOtherActivePlan(
        ConsortiumThinkTank $member,
        string $fiscalYear,
        ?string $exceptId = null,
    ): void {
        $canonical = $this->api->canonicalFiscalYear($fiscalYear);
        $duplicate = ThinkTankProcurementPlan::query()
            ->where('think_tank_member_id', $member->id)
            ->where('status', '<>', ThinkTankProcurementPlan::STATUS_REJECTED)
            ->when($exceptId, fn ($query) => $query->where('id', '<>', $exceptId))
            ->lockForUpdate()
            ->get(['id', 'fiscal_year'])
            ->contains(fn (ThinkTankProcurementPlan $plan): bool => $this->api->canonicalFiscalYear((string) $plan->fiscal_year) === $canonical);

        if ($duplicate) {
            throw ValidationException::withMessages([
                'fiscal_year' => ['An active procurement plan already exists for this fiscal year.'],
            ]);
        }
    }

    private function validatedFiscalYear(string $value): string
    {
        $canonical = $this->api->canonicalFiscalYear($value);
        if (! preg_match('/^20\d{2}(?:\/\d{2})?$/', $canonical)) {
            throw ValidationException::withMessages([
                'fiscal_year' => ['Use YYYY or YYYY/YY for the fiscal year.'],
            ]);
        }

        return $canonical;
    }

    private function newPlanCode(string $fiscalYear): string
    {
        $year = preg_replace('/\D/', '', $fiscalYear) ?: now()->format('Y');
        do {
            $code = 'TT-PP-'.$year.'-'.Str::upper(Str::random(8));
        } while (ThinkTankProcurementPlan::query()->where('plan_code', $code)->exists());

        return $code;
    }

    private function assertPortalWritablePlanCurrency(ThinkTankProcurementPlan $plan): void
    {
        if (Str::upper(trim((string) $plan->currency)) !== 'USD') {
            throw new ThinkTankApiException(
                'CURRENCY_REVIEW_REQUIRED',
                'This legacy plan has an unknown or non-USD currency and cannot be converted silently in the portal.',
                422,
            );
        }
    }

    private function assertPortalWritableItemCurrency(ThinkTankProcurementItem $item): void
    {
        if (Str::upper(trim((string) $item->currency)) !== 'USD') {
            throw new ThinkTankApiException(
                'CURRENCY_REVIEW_REQUIRED',
                'This legacy item has an unknown or non-USD currency and cannot be converted silently in the portal.',
                422,
            );
        }
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    /** @param array<int, string> $allowed */
    private function rejectUnexpectedQuery(Request $request, array $allowed): void
    {
        $unexpected = array_diff(array_keys($request->query->all()), $allowed);
        if ($unexpected !== []) {
            throw ValidationException::withMessages(collect($unexpected)
                ->mapWithKeys(fn (string $field): array => [$field => ['This query parameter is not allowed.']])
                ->all());
        }
    }

    /** @param array<int, string> $allowed */
    private function rejectUnexpectedBody(Request $request, array $allowed): void
    {
        $this->rejectUnexpectedQuery($request, []);
        $unexpected = array_diff(array_keys($request->all()), $allowed);
        if ($unexpected !== []) {
            throw ValidationException::withMessages(collect($unexpected)
                ->mapWithKeys(fn (string $field): array => [$field => ['This field is not allowed.']])
                ->all());
        }
    }

    private function assertJsonObject(Request $request): void
    {
        $this->rejectUnexpectedQuery($request, []);
        if (! $request->isJson()) {
            throw new ThinkTankApiException(
                'JSON_BODY_REQUIRED',
                'This operation requires an application/json request body.',
                415,
            );
        }

        $decoded = json_decode((string) $request->getContent());
        if (! is_object($decoded)) {
            throw new ThinkTankApiException(
                'JSON_OBJECT_REQUIRED',
                'The JSON request body must be an object.',
                422,
            );
        }
    }

    private function assertMultipart(Request $request): void
    {
        $this->rejectUnexpectedQuery($request, []);
        if (! str_starts_with(Str::lower((string) $request->header('Content-Type')), 'multipart/form-data')) {
            throw new ThinkTankApiException(
                'MULTIPART_BODY_REQUIRED',
                'This operation requires a multipart/form-data request body.',
                415,
            );
        }
    }
}
