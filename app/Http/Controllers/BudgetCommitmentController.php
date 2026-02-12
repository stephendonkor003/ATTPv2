<?php

namespace App\Http\Controllers;

use App\Models\BudgetCommitment;
use App\Models\ProgramFunding;
use App\Models\ResourceCategory;
use App\Models\Resource;
use App\Models\Project;
use App\Models\Activity;
use App\Models\SubActivity;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class BudgetCommitmentController extends Controller
{
    /* =========================================================
     | CONSTANTS
     ========================================================= */
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_CANCELLED = 'cancelled';

    /* =========================================================
     | ================== BUDGET COMMITMENTS ==================
     ========================================================= */


    public function index()
{
    $canViewAll = Auth::user()?->can('finance.commitments.view_all') === true;
    $scopedNodeIds = $canViewAll ? null : $this->scopedNodeIds();
    if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
        abort(403, 'You do not have access to commitments.');
    }

        $commitments = BudgetCommitment::with([
            'programFunding.program',
            'resourceCategory',
            'resource',
            'purchaseRequest.items',

            // eager load concrete models
            'programFunding',
        ])
    ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
        $query->whereIn('governance_node_id', $scopedNodeIds)
            ->whereNotNull('governance_node_id');
    })
    ->orderBy('id', 'desc')
    ->get();

    return view('finance.commitments.index', compact('commitments'));
}



    public function create()
    {
        return view('finance.commitments.create', [
            'fundings' => ProgramFunding::where('status', 'approved')
                ->when($this->scopedNodeIds() !== null, function ($query) {
                    $query->whereIn('governance_node_id', $this->scopedNodeIds())
                        ->whereNotNull('governance_node_id');
                })
                ->get(),
            'resourceCategories' => ResourceCategory::where('status', 'active')
                ->when($this->scopedNodeIds() !== null, function ($query) {
                    $query->whereIn('governance_node_id', $this->scopedNodeIds())
                        ->whereNotNull('governance_node_id');
                })
                ->get(),
        ]);
    }

    public function store(Request $request)
{
	    /* =====================================================
	     * 1. VALIDATION
	     * ===================================================== */
        $validated = $request->validate([
            'program_funding_id'   => 'required|exists:myb_program_fundings,id',
            'allocation_level'     => 'required|in:sub_activity',
            'commitment_year'      => 'required|integer|min:2000',
            'allocation_id'        => 'required|string|exists:myb_sub_activities,id',
            'description'          => 'nullable|string|max:5000',
            'delivery_date'        => 'required|date|after_or_equal:today',
        // Backwards compatible: allow old single-item fields if items[] isn't provided
        'resource_category_id' => 'nullable|exists:myb_resource_categories,id',
        'resource_id'          => 'nullable|exists:myb_resources,id',
        'commitment_amount'    => 'nullable|numeric|min:0.01',

        'items'                => 'nullable|array|min:1',
        'items.*.resource_category_id' => 'required|exists:myb_resource_categories,id',
        'items.*.resource_id'          => 'required|exists:myb_resources,id',
        'items.*.amount'               => 'required|numeric|min:0.01',
        'items.*.milestone'            => 'nullable|string|max:255',
        'items.*.milestone_date'       => 'nullable|date',
    ]);

	    $transactionStarted = false;

	    try {

	        /* =====================================================
	         * 2. FUNDING VALIDATION
         * ===================================================== */
        $funding = ProgramFunding::find($validated['program_funding_id']);

        if (!$funding) {
            return back()
                ->withErrors(['program_funding_id' => 'Selected program funding not found.'])
                ->withInput();
        }

        if ($funding->status !== 'approved') {
            return back()
                ->withErrors(['program_funding_id' => 'Only APPROVED program funding can be committed.'])
                ->withInput();
        }
        $this->assertFundingInScope($funding);

        /* =====================================================
         * 3. ALLOCATION VALIDATION
         * ===================================================== */
	        $allocationExists = SubActivity::where('id', $validated['allocation_id'])->exists();

		        if (!$allocationExists) {
		            return back()
		                ->withErrors(['allocation_id' => 'Selected allocation record does not exist.'])
		                ->withInput();
		        }
			        $this->assertAllocationInScope($validated['allocation_level'], $validated['allocation_id']);

		        $itemsInput = collect($validated['items'] ?? []);
		        if ($itemsInput->isEmpty()) {
		            if (
		                empty($validated['resource_category_id'])
		                || empty($validated['resource_id'])
		                || empty($validated['commitment_amount'])
		            ) {
		                return back()
		                    ->withErrors([
		                        'items' => 'Please add at least one purchase request item.',
		                    ])
		                    ->withInput();
		            }

		            $itemsInput = collect([
		                [
		                    'resource_category_id' => $validated['resource_category_id'],
		                    'resource_id' => $validated['resource_id'],
		                    'amount' => $validated['commitment_amount'],
		                ],
		            ]);
		        }

        $items = $itemsInput->map(function ($item) {
            $milestone = isset($item['milestone']) && is_string($item['milestone'])
                ? trim($item['milestone'])
                : null;
            $milestoneDate = isset($item['milestone_date']) ? $item['milestone_date'] : null;

            return [
                'resource_category_id' => (string) ($item['resource_category_id'] ?? ''),
                'resource_id' => (string) ($item['resource_id'] ?? ''),
                'amount' => round((float) ($item['amount'] ?? 0), 2),
                'milestone' => $milestone !== '' ? $milestone : null,
                'milestone_date' => $milestoneDate ?: null,
            ];
        })->values();

		        $requestedAmount = round((float) $items->sum('amount'), 2);
		        if ($requestedAmount <= 0) {
		            return back()
		                ->withErrors([
		                    'commitment_amount' => 'Commitment amount must be greater than zero.',
		                ])
		                ->withInput();
		        }

		        foreach ($items as $item) {
		            $this->assertResourceCategoryInScope($item['resource_category_id']);

		            $resource = Resource::find($item['resource_id']);
		            if (!$resource) {
		                return back()
		                    ->withErrors([
		                        'items' => 'One or more selected resource items were not found.',
		                    ])
		                    ->withInput();
		            }

		            $this->assertResourceInScope($resource);

		            if ((string) $resource->resource_category_id !== (string) $item['resource_category_id']) {
		                return back()
		                    ->withErrors([
		                        'items' => 'One or more resource items do not match their selected category.',
		                    ])
		                    ->withInput();
		            }
		        }

		        /* =====================================================
		         * 4. MULTI-YEAR SPLITTING (START YEAR + FORWARD)
		         * ===================================================== */
		        $startYear = (int) $validated['commitment_year'];

		        $startYearAllocated = (float) $this->getAllocatedAmount(
		            $validated['allocation_level'],
		            $validated['allocation_id'],
	            $startYear
	        );

	        if ($startYearAllocated <= 0) {
	            return back()
	                ->withErrors([
	                    'commitment_year' => 'No budget allocation exists for the selected year.',
	                ])
	                ->withInput();
	        }

	        $years = DB::table('myb_sub_activity_allocations')
	            ->where('sub_activity_id', $validated['allocation_id'])
	            ->whereNotNull('year')
	            ->distinct()
	            ->orderBy('year')
	            ->pluck('year')
	            ->map(fn ($year) => (int) $year)
	            ->filter(fn ($year) => $year >= $startYear)
	            ->values();

	        if ($years->isEmpty()) {
	            return back()
	                ->withErrors([
	                    'commitment_year' => 'No budget allocation years found from the selected year.',
	                ])
	                ->withInput();
	        }

	        $remainingToAllocate = $requestedAmount;
	        $totalRemainingAvailable = 0.0;
	        $splits = [];

	        foreach ($years as $year) {
	            $allocated = (float) $this->getAllocatedAmount(
	                $validated['allocation_level'],
	                $validated['allocation_id'],
	                (int) $year
	            );

	            if ($allocated <= 0) {
	                continue;
	            }

	            $committedQuery = BudgetCommitment::query()
	                ->where('allocation_level', $validated['allocation_level'])
	                ->where('allocation_id', $validated['allocation_id'])
	                ->where('commitment_year', (int) $year)
	                ->whereIn('status', [
	                    BudgetCommitment::STATUS_DRAFT,
	                    BudgetCommitment::STATUS_SUBMITTED,
	                    BudgetCommitment::STATUS_APPROVED,
	                ]);

	            if (!empty($funding->governance_node_id)) {
	                $committedQuery->where('governance_node_id', $funding->governance_node_id);
	            } else {
	                $scopedNodeIds = $this->scopedNodeIds();
	                if ($scopedNodeIds !== null) {
	                    $committedQuery->whereIn('governance_node_id', $scopedNodeIds)
	                        ->whereNotNull('governance_node_id');
	                }
	            }

	            $committed = (float) $committedQuery->sum('commitment_amount');
	            $remaining = round($allocated - $committed, 2);

	            if ($remaining <= 0) {
	                continue;
	            }

	            $totalRemainingAvailable += $remaining;

	            if ($remainingToAllocate <= 0) {
	                continue;
	            }

	            $use = round(min($remaining, $remainingToAllocate), 2);
	            if ($use <= 0) {
	                continue;
	            }

	            $splits[] = [
	                'year' => (int) $year,
	                'amount' => $use,
	            ];

	            $remainingToAllocate = round($remainingToAllocate - $use, 2);
	            if ($remainingToAllocate <= 0) {
	                $remainingToAllocate = 0;
	                break;
	            }
	        }

	        $totalRemainingAvailable = round($totalRemainingAvailable, 2);

	        if ($requestedAmount > $totalRemainingAvailable) {
	            return back()
	                ->withErrors([
	                    'commitment_amount' =>
	                        'Commitment exceeds remaining budget across allocation years. Available: ' .
	                        number_format($totalRemainingAvailable, 2),
	                ])
	                ->withInput();
	        }

		        if ($remainingToAllocate > 0 || empty($splits)) {
		            return back()
		                ->withErrors([
		                    'commitment_amount' => 'Unable to distribute commitment across allocation years.',
		                ])
		                ->withInput();
		        }

		        DB::beginTransaction();
		        $transactionStarted = true;

			        $purchaseRequest = PurchaseRequest::create([
			            'reference_no' => $this->generatePurchaseRequestReference(),
			            'program_funding_id' => $validated['program_funding_id'],
			            'governance_node_id' => $funding->governance_node_id,
			            'allocation_level' => $validated['allocation_level'],
			            'allocation_id' => $validated['allocation_id'],
			            'start_year' => $startYear,
			            'commitment_date' => now()->toDateString(),
			            'delivery_date' => $validated['delivery_date'],
			            'currency' => $funding->currency ?? $funding->program?->currency,
			            'total_amount' => $requestedAmount,
			            'description' => $validated['description'] ?? null,
			            'status' => 'draft',
			            'created_by' => Auth::id(),
			        ]);

        foreach ($items as $item) {
            PurchaseRequestItem::create([
                'purchase_request_id' => $purchaseRequest->id,
                'resource_category_id' => $item['resource_category_id'],
                'resource_id' => $item['resource_id'],
                'amount' => $item['amount'],
                'milestone' => $item['milestone'] ?? null,
                'milestone_date' => $item['milestone_date'] ?? null,
            ]);
        }

		        foreach ($splits as $split) {
		            BudgetCommitment::create([
		                'purchase_request_id' => $purchaseRequest->id,
		                'program_funding_id'   => $validated['program_funding_id'],
		                'governance_node_id'   => $funding->governance_node_id,
		                'allocation_level'     => $validated['allocation_level'],
		                'allocation_id'        => $validated['allocation_id'],
		                'resource_category_id' => null,
		                'resource_id'          => null,
		                'commitment_amount'    => $split['amount'],
		                'commitment_year'      => $split['year'],
		                'status'               => BudgetCommitment::STATUS_DRAFT,
		                'description'          => $validated['description'] ?? null,
		                'created_by'           => Auth::id(),
		            ]);
		        }

		        DB::commit();
		        $transactionStarted = false;

		        return redirect()
		            ->route('finance.purchase-requests.show', $purchaseRequest)
		            ->with(
		                'success',
		                'Purchase Request ' . $purchaseRequest->reference_no . ' created successfully (Draft).'
		            );

	    } catch (\Throwable $e) {

	        if ($transactionStarted) {
	            DB::rollBack();
	        }

        /* =====================================================
         * 7. LOG + SURFACE ERROR
         * ===================================================== */
        \Log::error('Budget Commitment Store Failed', [
            'error' => $e->getMessage(),
            'exception' => get_class($e),
            'user_id' => Auth::id(),
        ]);

        if (config('app.debug')) {
            \Log::debug('Budget Commitment Store Failed (debug context)', [
                'trace' => $e->getTraceAsString(),
                // Avoid logging raw request payloads in production (PII risk).
                'payload' => $request->except(['password', 'password_confirmation', 'current_password', '_token']),
            ]);
        }

        return back()
            ->withErrors([
                'system' => 'Unable to save the budget commitment. Please try again.',
            ])
            ->withInput();

    }
}


    public function show(BudgetCommitment $commitment)
    {
        if (Auth::user()?->can('finance.commitments.view_all') !== true) {
            $this->assertCommitmentInScope($commitment);
        }
	        $commitment->load([
	            'programFunding.program',
	            'resourceCategory',
	            'resource',
	            'purchaseRequest.items.resourceCategory',
	            'purchaseRequest.items.resource',
	        ]);

        return view('finance.commitments.show', compact('commitment'));
    }

    public function submit(BudgetCommitment $commitment)
    {
        $this->assertCommitmentInScope($commitment);
        if ($commitment->status !== self::STATUS_DRAFT) {
            abort(403);
        }

        $commitment->update(['status' => self::STATUS_SUBMITTED]);

        return back()->with('success', 'Commitment submitted.');
    }

    public function approve(BudgetCommitment $commitment)
    {
        $this->assertCommitmentInScope($commitment);
        if (!in_array($commitment->status, [self::STATUS_SUBMITTED, self::STATUS_DRAFT], true)) {
            abort(403, 'Only draft or submitted commitments can be approved.');
        }

        $commitment->update([
            'status'      => self::STATUS_APPROVED,
            'approved_by'=> Auth::id(),
            'approved_at'=> now(),
            'rejection_reason' => null,
        ]);

        return back()->with('success', 'Commitment approved.');
    }

    public function cancel(Request $request, BudgetCommitment $commitment)
    {
        $this->assertCommitmentInScope($commitment);
        if ($commitment->status === self::STATUS_APPROVED) {
            abort(403);
        }

        $data = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $commitment->update([
            'status' => self::STATUS_CANCELLED,
            'rejection_reason' => $data['reason'],
        ]);

        return back()->with('success', 'Commitment cancelled.');
    }

    public function edit(BudgetCommitment $commitment)
    {
        $this->assertCommitmentInScope($commitment);
        if ($commitment->status !== self::STATUS_DRAFT) {
            abort(403, 'Only draft commitments can be edited.');
        }

        $commitment->load(['purchaseRequest.items', 'programFunding']);

        $subActivity = SubActivity::find($commitment->allocation_id);
        $activityId = $subActivity?->activity_id;
        $projectId = $subActivity?->activity?->project_id;

        $items = $commitment->purchaseRequest?->items
            ? $commitment->purchaseRequest->items->map(function ($item) {
                return [
                    'resource_category_id' => $item->resource_category_id,
                    'resource_id' => $item->resource_id,
                    'amount' => (float) $item->amount,
                    'milestone' => $item->milestone,
                    'milestone_date' => $item->milestone_date?->format('Y-m-d'),
                ];
            })->values()->toArray()
            : [];

        return view('finance.commitments.create', [
            'commitment' => $commitment,
            'purchaseRequest' => $commitment->purchaseRequest,
            'fundings' => ProgramFunding::where('status', 'approved')
                ->when($this->scopedNodeIds() !== null, function ($query) {
                    $query->whereIn('governance_node_id', $this->scopedNodeIds())
                        ->whereNotNull('governance_node_id');
                })
                ->get(),
            'resourceCategories' => ResourceCategory::where('status', 'active')
                ->when($this->scopedNodeIds() !== null, function ($query) {
                    $query->whereIn('governance_node_id', $this->scopedNodeIds())
                        ->whereNotNull('governance_node_id');
                })
                ->get(),
            'defaults' => [
                'project_id' => $projectId,
                'activity_id' => $activityId,
                'sub_activity_id' => $commitment->allocation_id,
                'year' => $commitment->purchaseRequest?->start_year ?? $commitment->commitment_year,
            ],
            'items' => $items,
        ]);
    }

    public function update(Request $request, BudgetCommitment $commitment)
    {
        $this->assertCommitmentInScope($commitment);
        if ($commitment->status !== self::STATUS_DRAFT) {
            abort(403, 'Only draft commitments can be updated.');
        }

        $validated = $request->validate([
            'program_funding_id'   => 'required|exists:myb_program_fundings,id',
            'allocation_level'     => 'required|in:sub_activity',
            'commitment_year'      => 'required|integer|min:2000',
            'allocation_id'        => 'required|string|exists:myb_sub_activities,id',
            'description'          => 'nullable|string|max:5000',
            'delivery_date'        => 'required|date|after_or_equal:today',
            // Backwards compatible: allow old single-item fields if items[] isn't provided
            'resource_category_id' => 'nullable|exists:myb_resource_categories,id',
            'resource_id'          => 'nullable|exists:myb_resources,id',
            'commitment_amount'    => 'nullable|numeric|min:0.01',

            'items'                => 'nullable|array|min:1',
            'items.*.resource_category_id' => 'required|exists:myb_resource_categories,id',
            'items.*.resource_id'          => 'required|exists:myb_resources,id',
            'items.*.amount'               => 'required|numeric|min:0.01',
            'items.*.milestone'            => 'nullable|string|max:255',
            'items.*.milestone_date'       => 'nullable|date',
        ]);

        $transactionStarted = false;

        try {
            $funding = ProgramFunding::find($validated['program_funding_id']);
            if (!$funding) {
                return back()
                    ->withErrors(['program_funding_id' => 'Selected program funding not found.'])
                    ->withInput();
            }

            if ($funding->status !== 'approved') {
                return back()
                    ->withErrors(['program_funding_id' => 'Only APPROVED program funding can be committed.'])
                    ->withInput();
            }
            $this->assertFundingInScope($funding);

            $allocationExists = SubActivity::where('id', $validated['allocation_id'])->exists();
            if (!$allocationExists) {
                return back()
                    ->withErrors(['allocation_id' => 'Selected allocation record does not exist.'])
                    ->withInput();
            }
            $this->assertAllocationInScope($validated['allocation_level'], $validated['allocation_id']);

            $itemsInput = collect($validated['items'] ?? []);
            if ($itemsInput->isEmpty()) {
                if (
                    empty($validated['resource_category_id'])
                    || empty($validated['resource_id'])
                    || empty($validated['commitment_amount'])
                ) {
                    return back()
                        ->withErrors([
                            'items' => 'Please add at least one purchase request item.',
                        ])
                        ->withInput();
                }

                $itemsInput = collect([
                    [
                        'resource_category_id' => $validated['resource_category_id'],
                        'resource_id' => $validated['resource_id'],
                        'amount' => $validated['commitment_amount'],
                    ],
                ]);
            }

            $items = $itemsInput->map(function ($item) {
                $milestone = isset($item['milestone']) && is_string($item['milestone'])
                    ? trim($item['milestone'])
                    : null;
                $milestoneDate = isset($item['milestone_date']) ? $item['milestone_date'] : null;

                return [
                    'resource_category_id' => (string) ($item['resource_category_id'] ?? ''),
                    'resource_id' => (string) ($item['resource_id'] ?? ''),
                    'amount' => round((float) ($item['amount'] ?? 0), 2),
                    'milestone' => $milestone !== '' ? $milestone : null,
                    'milestone_date' => $milestoneDate ?: null,
                ];
            })->values();

            $requestedAmount = round((float) $items->sum('amount'), 2);
            if ($requestedAmount <= 0) {
                return back()
                    ->withErrors([
                        'commitment_amount' => 'Commitment amount must be greater than zero.',
                    ])
                    ->withInput();
            }

            foreach ($items as $item) {
                $this->assertResourceCategoryInScope($item['resource_category_id']);

                $resource = Resource::find($item['resource_id']);
                if (!$resource) {
                    return back()
                        ->withErrors([
                            'items' => 'One or more selected resource items were not found.',
                        ])
                        ->withInput();
                }

                $this->assertResourceInScope($resource);

                if ((string) $resource->resource_category_id !== (string) $item['resource_category_id']) {
                    return back()
                        ->withErrors([
                            'items' => 'One or more resource items do not match their selected category.',
                        ])
                        ->withInput();
                }
            }

            $startYear = (int) $validated['commitment_year'];
            $startYearAllocated = (float) $this->getAllocatedAmount(
                $validated['allocation_level'],
                $validated['allocation_id'],
                $startYear
            );

            if ($startYearAllocated <= 0) {
                return back()
                    ->withErrors([
                        'commitment_year' => 'No budget allocation exists for the selected year.',
                    ])
                    ->withInput();
            }

            $years = DB::table('myb_sub_activity_allocations')
                ->where('sub_activity_id', $validated['allocation_id'])
                ->whereNotNull('year')
                ->distinct()
                ->orderBy('year')
                ->pluck('year')
                ->map(fn ($year) => (int) $year)
                ->filter(fn ($year) => $year >= $startYear)
                ->values();

            if ($years->isEmpty()) {
                return back()
                    ->withErrors([
                        'commitment_year' => 'No budget allocation years found from the selected year.',
                    ])
                    ->withInput();
            }

            $remainingToAllocate = $requestedAmount;
            $totalRemainingAvailable = 0.0;
            $splits = [];

            foreach ($years as $year) {
                $allocated = (float) $this->getAllocatedAmount(
                    $validated['allocation_level'],
                    $validated['allocation_id'],
                    (int) $year
                );

                if ($allocated <= 0) {
                    continue;
                }

                $committedQuery = BudgetCommitment::query()
                    ->where('allocation_level', $validated['allocation_level'])
                    ->where('allocation_id', $validated['allocation_id'])
                    ->where('commitment_year', (int) $year)
                    ->whereIn('status', [
                        BudgetCommitment::STATUS_DRAFT,
                        BudgetCommitment::STATUS_SUBMITTED,
                        BudgetCommitment::STATUS_APPROVED,
                    ]);

                if ($commitment->purchase_request_id) {
                    $committedQuery->where(function ($q) use ($commitment) {
                        $q->whereNull('purchase_request_id')
                            ->orWhere('purchase_request_id', '!=', $commitment->purchase_request_id);
                    });
                }

                if (!empty($funding->governance_node_id)) {
                    $committedQuery->where('governance_node_id', $funding->governance_node_id);
                } else {
                    $scopedNodeIds = $this->scopedNodeIds();
                    if ($scopedNodeIds !== null) {
                        $committedQuery->whereIn('governance_node_id', $scopedNodeIds)
                            ->whereNotNull('governance_node_id');
                    }
                }

                $committed = (float) $committedQuery->sum('commitment_amount');
                $remaining = round($allocated - $committed, 2);

                if ($remaining <= 0) {
                    continue;
                }

                $totalRemainingAvailable += $remaining;

                if ($remainingToAllocate <= 0) {
                    continue;
                }

                $use = round(min($remaining, $remainingToAllocate), 2);
                if ($use <= 0) {
                    continue;
                }

                $splits[] = [
                    'year' => (int) $year,
                    'amount' => $use,
                ];

                $remainingToAllocate = round($remainingToAllocate - $use, 2);
                if ($remainingToAllocate <= 0) {
                    $remainingToAllocate = 0;
                    break;
                }
            }

            $totalRemainingAvailable = round($totalRemainingAvailable, 2);

            if ($requestedAmount > $totalRemainingAvailable) {
                return back()
                    ->withErrors([
                        'commitment_amount' =>
                            'Commitment exceeds remaining budget across allocation years. Available: ' .
                            number_format($totalRemainingAvailable, 2),
                    ])
                    ->withInput();
            }

            if ($remainingToAllocate > 0 || empty($splits)) {
                return back()
                    ->withErrors([
                        'commitment_amount' => 'Unable to distribute commitment across allocation years.',
                    ])
                    ->withInput();
            }

            DB::beginTransaction();
            $transactionStarted = true;

            $purchaseRequest = $commitment->purchaseRequest;
            if (!$purchaseRequest) {
                $purchaseRequest = PurchaseRequest::create([
                    'reference_no' => $this->generatePurchaseRequestReference(),
                    'created_by' => Auth::id(),
                ]);
            }

            $purchaseRequest->update([
                'program_funding_id' => $validated['program_funding_id'],
                'governance_node_id' => $funding->governance_node_id,
                'allocation_level' => $validated['allocation_level'],
                'allocation_id' => $validated['allocation_id'],
                'start_year' => $startYear,
                'commitment_date' => now()->toDateString(),
                'delivery_date' => $validated['delivery_date'],
                'currency' => $funding->currency ?? $funding->program?->currency,
                'total_amount' => $requestedAmount,
                'description' => $validated['description'] ?? null,
                'status' => BudgetCommitment::STATUS_DRAFT,
            ]);

            $purchaseRequest->items()->delete();
            foreach ($items as $item) {
                PurchaseRequestItem::create([
                    'purchase_request_id' => $purchaseRequest->id,
                    'resource_category_id' => $item['resource_category_id'],
                    'resource_id' => $item['resource_id'],
                    'amount' => $item['amount'],
                    'milestone' => $item['milestone'] ?? null,
                    'milestone_date' => $item['milestone_date'] ?? null,
                ]);
            }

            $existingCommitments = $purchaseRequest->commitments()->orderBy('commitment_year')->get();
            if ($existingCommitments->isEmpty() && $commitment->exists) {
                $existingCommitments = collect([$commitment]);
            }

            foreach ($splits as $index => $split) {
                $payload = [
                    'purchase_request_id' => $purchaseRequest->id,
                    'program_funding_id'   => $validated['program_funding_id'],
                    'governance_node_id'   => $funding->governance_node_id,
                    'allocation_level'     => $validated['allocation_level'],
                    'allocation_id'        => $validated['allocation_id'],
                    'resource_category_id' => null,
                    'resource_id'          => null,
                    'commitment_amount'    => $split['amount'],
                    'commitment_year'      => $split['year'],
                    'status'               => BudgetCommitment::STATUS_DRAFT,
                    'description'          => $validated['description'] ?? null,
                    'created_by'           => $commitment->created_by ?? Auth::id(),
                ];

                if (isset($existingCommitments[$index])) {
                    $existingCommitments[$index]->update($payload);
                } else {
                    BudgetCommitment::create($payload);
                }
            }

            if ($existingCommitments->count() > count($splits)) {
                $extra = $existingCommitments->slice(count($splits));
                foreach ($extra as $row) {
                    $row->delete();
                }
            }

            DB::commit();
            $transactionStarted = false;

            return redirect()
                ->route('finance.purchase-requests.show', $purchaseRequest)
                ->with('success', 'Budget commitment updated.');
        } catch (\Throwable $e) {
            if ($transactionStarted) {
                DB::rollBack();
            }

            \Log::error('Budget Commitment Update Failed', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'user_id' => Auth::id(),
            ]);

            if (config('app.debug')) {
                \Log::debug('Budget Commitment Update Failed (debug context)', [
                    'trace' => $e->getTraceAsString(),
                    'payload' => $request->except(['password', 'password_confirmation', 'current_password', '_token']),
                ]);
            }

            return back()
                ->withErrors([
                    'system' => 'Unable to update the budget commitment. Please try again.',
                ])
                ->withInput();
        }
    }

    public function destroy(BudgetCommitment $commitment)
    {
        $this->assertCommitmentInScope($commitment);

        $purchaseRequest = $commitment->purchaseRequest;

        if ($purchaseRequest) {
            $nonDraftExists = $purchaseRequest->commitments()
                ->where('status', '!=', self::STATUS_DRAFT)
                ->exists();
            if ($nonDraftExists) {
                abort(403, 'Only draft commitments can be deleted.');
            }

            DB::transaction(function () use ($purchaseRequest) {
                $purchaseRequest->commitments()->delete();
                $purchaseRequest->items()->delete();
                $purchaseRequest->delete();
            });
        } else {
            if ($commitment->status !== self::STATUS_DRAFT) {
                abort(403, 'Only draft commitments can be deleted.');
            }
            $commitment->delete();
        }

        return redirect()
            ->route('finance.commitments.index')
            ->with('success', 'Budget commitment deleted.');
    }

    /* =========================================================
     | ================== RESOURCE MANAGEMENT =================
     ========================================================= */

    /** Resource Categories (index + store + update + delete) */
    public function resourceCategories()
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to resource categories.');
        }

        return view('finance.resources.categories.index', [
            'categories' => ResourceCategory::with('governanceNode')
                ->latest()
                ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                    $query->whereIn('governance_node_id', $scopedNodeIds)
                        ->whereNotNull('governance_node_id');
                })
                ->get()
        ]);
    }

    public function storeResourceCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to create resource categories.');
        }

        ResourceCategory::create([
            'name' => $request->name,
            'description' => $request->description,
            'governance_node_id' => Auth::user()?->governance_node_id,
            'status' => 'active',
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', 'Resource category added.');
    }

    public function updateResourceCategory(Request $request, ResourceCategory $category)
    {
        $this->assertResourceCategoryInScope($category->id);

        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'status' => 'required|in:active,inactive',
        ]);

        $category->update([
            'name' => $request->name,
            'description' => $request->description,
            'status' => $request->status,
        ]);

        return back()->with('success', 'Resource category updated.');
    }

    public function destroyResourceCategory(ResourceCategory $category)
    {
        $this->assertResourceCategoryInScope($category->id);

        // Check if category has resources
        if ($category->resources()->exists()) {
            return back()->with('error', 'Cannot delete category with existing resources.');
        }

        // Check if category has commitments
        if ($category->commitments()->exists()) {
            return back()->with('error', 'Cannot delete category with existing commitments.');
        }

        $category->delete();

        return back()->with('success', 'Resource category deleted.');
    }

    /** Resources (items) */
    public function resources()
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to resources.');
        }

        return view('finance.resources.items.index', [
            'resources' => Resource::with(['category', 'governanceNode'])
                ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                    $query->whereIn('governance_node_id', $scopedNodeIds)
                        ->whereNotNull('governance_node_id');
                })
                ->latest()
                ->get(),
            'categories'=> ResourceCategory::where('status','active')
                ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                    $query->whereIn('governance_node_id', $scopedNodeIds)
                        ->whereNotNull('governance_node_id');
                })
                ->get()
        ]);
    }

    // public function storeResource(Request $request)
    // {
    //     $request->validate([
    //         'resource_category_id' => 'required|exists:myb_resource_categories,id',
    //         'name' => 'required|string|max:255',
    //     ]);

    //     Resource::create([
    //         'resource_category_id' => $request->resource_category_id,
    //         'name' => $request->name,
    //         'reference_code' => $request->reference_code,
    //         'description' => $request->description,
    //         'status' => 'active',
    //         'created_by' => Auth::id(),
    //     ]);

    //     return back()->with('success', 'Resource created.');
    // }

    public function storeResource(Request $request)
{
    $validated = $request->validate([
        'resource_category_id' => 'required|exists:myb_resource_categories,id',
        'name'                 => 'required|string|max:255',
        'reference_code'       => 'nullable|string|max:100',
        'description'          => 'nullable|string|max:1000',
        'is_human_resource'    => 'nullable|boolean', // ✅ NEW
    ]);

	    $this->assertResourceCategoryInScope($validated['resource_category_id']);

    Resource::create([
        'resource_category_id' => $validated['resource_category_id'],
        'governance_node_id'   => ResourceCategory::find($validated['resource_category_id'])?->governance_node_id,
        'name'                 => $validated['name'],
        'reference_code'       => $validated['reference_code'] ?? null,
        'description'          => $validated['description'] ?? null,
        'is_human_resource'    => $request->boolean('is_human_resource'), // ✅ KEY LINE
        'status'               => 'active',
        'created_by'           => Auth::id(),
    ]);

    return back()->with('success', 'Resource created successfully.');
}

    public function updateResource(Request $request, Resource $resource)
    {
        $this->assertResourceInScope($resource);

        $validated = $request->validate([
            'resource_category_id' => 'required|exists:myb_resource_categories,id',
            'name'                 => 'required|string|max:255',
            'reference_code'       => 'nullable|string|max:100',
            'description'          => 'nullable|string|max:1000',
            'is_human_resource'    => 'nullable|boolean',
            'status'               => 'required|in:active,inactive',
        ]);

	        $this->assertResourceCategoryInScope($validated['resource_category_id']);

        $resource->update([
            'resource_category_id' => $validated['resource_category_id'],
            'name'                 => $validated['name'],
            'reference_code'       => $validated['reference_code'] ?? null,
            'description'          => $validated['description'] ?? null,
            'is_human_resource'    => $request->boolean('is_human_resource'),
            'status'               => $validated['status'],
        ]);

        return back()->with('success', 'Resource updated successfully.');
    }

    public function destroyResource(Resource $resource)
    {
        $this->assertResourceInScope($resource);

        // Check if resource has commitments
        if ($resource->commitments()->exists()) {
            return back()->with('error', 'Cannot delete resource with existing budget commitments.');
        }

        // Check if resource has procurements
        if ($resource->procurements()->exists()) {
            return back()->with('error', 'Cannot delete resource with existing procurements.');
        }

        $resource->delete();

        return back()->with('success', 'Resource deleted successfully.');
    }

    /* =========================================================
     | ================== AJAX ENDPOINTS ======================
     ========================================================= */

    public function projects()
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            return collect();
        }

        return Project::select('id','name')
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->orderBy('name')
            ->get();
    }

    public function activities($projectId)
    {
        $project = Project::findOrFail($projectId);
        $this->assertProjectInScope($project);

        return Activity::where('project_id',$projectId)
            ->select('id','name')
            ->when($this->scopedNodeIds() !== null, function ($query) {
                $query->whereIn('governance_node_id', $this->scopedNodeIds())
                    ->whereNotNull('governance_node_id');
            })
            ->orderBy('name')->get();
    }

    public function subActivities($activityId)
    {
        $activity = Activity::findOrFail($activityId);
        $this->assertActivityInScope($activity);

        return SubActivity::where('activity_id',$activityId)
            ->select('id','name')
            ->when($this->scopedNodeIds() !== null, function ($query) {
                $query->whereIn('governance_node_id', $this->scopedNodeIds())
                    ->whereNotNull('governance_node_id');
            })
            ->orderBy('name')->get();
    }

    public function allocationYears($level, $id)
    {
        $this->assertAllocationInScope($level, $id);
        $years = match ($level) {
            'project' => DB::table('myb_project_allocations')->where('project_id',$id)->pluck('year'),
            'activity' => DB::table('myb_activity_allocations')->where('activity_id',$id)->pluck('year'),
            'sub_activity' => DB::table('myb_sub_activity_allocations')->where('sub_activity_id',$id)->pluck('year'),
        };

        return response()->json($years->unique()->values());
    }

    public function allocationBreakdown($level, $id)
    {
        $this->assertAllocationInScope($level, $id);
        $excludeId = request('exclude');

        $years = match ($level) {
            'project' => DB::table('myb_project_allocations')
                ->where('project_id', $id)
                ->pluck('year'),

            'activity' => DB::table('myb_activity_allocations')
                ->where('activity_id', $id)
                ->pluck('year'),

            'sub_activity' => DB::table('myb_sub_activity_allocations')
                ->where('sub_activity_id', $id)
                ->pluck('year'),

            default => collect(),
        };

        $years = $years
            ->filter(fn ($year) => $year !== null)
            ->unique()
            ->sort()
            ->values();

        $scopedNodeIds = $this->scopedNodeIds();

        $breakdown = $years->map(function ($year) use ($level, $id, $scopedNodeIds) {
            $allocated = (float) $this->allocationSum($level, $id, (int) $year);

            $committed = (float) BudgetCommitment::query()
                ->where('allocation_level', $level)
                ->where('allocation_id', $id)
                ->where('commitment_year', (int) $year)
                ->whereIn('status', [
                    self::STATUS_DRAFT,
                    self::STATUS_SUBMITTED,
                    self::STATUS_APPROVED,
                ])
                ->when(request('exclude'), function ($query) {
                    $query->where('id', '!=', request('exclude'));
                })
                ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                    $query->whereIn('governance_node_id', $scopedNodeIds)
                        ->whereNotNull('governance_node_id');
                })
                ->sum('commitment_amount');

            return [
                'year' => (int) $year,
                'allocated' => $allocated,
                'committed' => $committed,
                'remaining' => $allocated - $committed,
            ];
        });

        return response()->json($breakdown);
    }

    public function remainingBudget(Request $request)
    {
        $this->assertAllocationInScope($request->allocation_level, $request->allocation_id);
        $allocated = $this->allocationSum(
            $request->allocation_level,
            $request->allocation_id,
            $request->year
        );

        $scopedNodeIds = $this->scopedNodeIds();
        $committed = BudgetCommitment::where([
            'allocation_level' => $request->allocation_level,
            'allocation_id' => $request->allocation_id,
            'commitment_year' => $request->year,
        ])
            ->whereIn('status', [
                self::STATUS_DRAFT,
                self::STATUS_SUBMITTED,
                self::STATUS_APPROVED,
            ])
            ->when($request->exclude, function ($query) use ($request) {
                $query->where('id', '!=', $request->exclude);
            })
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->sum('commitment_amount');

        return response()->json([
            'allocated' => (float)$allocated,
            'committed' => (float)$committed,
            'remaining' => (float)($allocated - $committed),
        ]);
    }

    /* =========================================================
     | ================== EXECUTION DASHBOARD =================
     ========================================================= */

    public function executionDashboard()
    {
        return view('finance.execution.dashboard');
    }




public function executionData()
{
    $scopedNodeIds = $this->scopedNodeIds();
    $programs = ProgramFunding::with('program.projects')
        ->where('status', 'approved')
        ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
            $query->whereIn('governance_node_id', $scopedNodeIds)
                ->whereNotNull('governance_node_id');
        })
        ->get()
        ->map(function ($funding) {

            $program = $funding->program;
            $projectIds = $program->projects->pluck('id');

            if ($projectIds->isEmpty()) {
                return null;
            }

            $years = DB::table('myb_project_allocations')
                ->whereIn('project_id', $projectIds)
                ->distinct()
                ->orderBy('year')
                ->pluck('year')
                ->values();

            if ($years->isEmpty()) {
                return null;
            }

            /* ================= PROGRAM LEVEL ================= */
            $programAllocation = [];
            $programCommitment = [];

            foreach ($years as $year) {
                $programAllocation[$year] = DB::table('myb_project_allocations')
                    ->whereIn('project_id', $projectIds)
                    ->where('year', $year)
                    ->sum('amount');

                $programCommitment[$year] = BudgetCommitment::where('commitment_year', $year)
                    ->whereIn('allocation_id', $projectIds)
                    ->where('allocation_level', 'project')
                    ->whereIn('status', ['submitted', 'approved'])
                    ->sum('commitment_amount');
            }

            /* ================= PROJECT LEVEL ================= */
            $projects = $program->projects->map(function ($project) use ($years) {

                $alloc = [];
                $commit = [];

                foreach ($years as $year) {
                    $alloc[$year] = DB::table('myb_project_allocations')
                        ->where('project_id', $project->id)
                        ->where('year', $year)
                        ->sum('amount');

                    $commit[$year] = BudgetCommitment::where('allocation_level', 'project')
                        ->where('allocation_id', $project->id)
                        ->where('commitment_year', $year)
                        ->whereIn('status', ['submitted','approved'])
                        ->sum('commitment_amount');
                }

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'allocation' => $alloc,
                    'commitment' => $commit,
                    'ai_summary' => $this->aiSummary($alloc, $commit),
                ];
            });

            return [
                'id' => $program->id,
                'name' => $program->name,
                'currency' => $program->currency,
                'years' => $years,
                'allocation' => $programAllocation,
                'commitment' => $programCommitment,
                'projects' => $projects,
                'ai_summary' => $this->aiSummary($programAllocation, $programCommitment),
            ];
        })
        ->filter()
        ->values();

    return response()->json(['programs' => $programs]);
}
protected function aiSummary(array $allocated, array $committed)
{
    $totalAlloc = array_sum($allocated);
    $totalCommit = array_sum($committed);

    if ($totalAlloc == 0) {
        return 'No allocated budget defined for this period.';
    }

    if ($totalCommit == 0) {
        return 'No commitments recorded yet. Execution has not started.';
    }

    if ($totalCommit > $totalAlloc) {
        return '⚠️ Commitments exceed allocated budget. Immediate financial review recommended.';
    }

    $ratio = ($totalCommit / $totalAlloc) * 100;

    if ($ratio < 40) {
        return 'Execution is significantly behind schedule with low budget utilization.';
    }

    if ($ratio <= 80) {
        return 'Execution is progressing steadily within expected budget thresholds.';
    }

    return 'Execution is nearing full utilization of allocated budget.';
}




    /* =========================================================
     | ================== INTERNAL HELPERS ====================
     ========================================================= */

    private function allocationSum(string $level, string $id, int $year): float
    {
        return match ($level) {
            'project' => DB::table('myb_project_allocations')
                ->where('project_id',$id)->where('year',$year)->sum('amount'),

            'activity' => DB::table('myb_activity_allocations')
                ->where('activity_id',$id)->where('year',$year)->sum('amount'),

            'sub_activity' => DB::table('myb_sub_activity_allocations')
                ->where('sub_activity_id',$id)->where('year',$year)->sum('amount'),
        };
    }

    private function generatePurchaseRequestReference(): string
    {
        $year = now()->year;

        do {
            $reference = 'PR-' . $year . '-' . strtoupper(Str::random(5));
        } while (PurchaseRequest::where('reference_no', $reference)->exists());

        return $reference;
    }

    /**
 * AJAX: Get resources by category
 */
		public function resourcesByCategory($categoryId)
	{
	    $this->assertResourceCategoryInScope($categoryId);
	    return Resource::where('resource_category_id', $categoryId)
	        ->where('status', 'active')
	        ->select('id', 'name')
        ->when($this->scopedNodeIds() !== null, function ($query) {
            $query->whereIn('governance_node_id', $this->scopedNodeIds())
                ->whereNotNull('governance_node_id');
        })
        ->orderBy('name')
        ->get();
}

/**
 * =========================================================
 * HELPER: Get Allocated Amount for a Level & Year
 * =========================================================
 */
	private function getAllocatedAmount(string $level, string $id, int $year): float
{
    return match ($level) {

        'project' => (float) \DB::table('myb_project_allocations')
            ->where('project_id', $id)
            ->where('year', $year)
            ->sum('amount'),

        'activity' => (float) \DB::table('myb_activity_allocations')
            ->where('activity_id', $id)
            ->where('year', $year)
            ->sum('amount'),

        'sub_activity' => (float) \DB::table('myb_sub_activity_allocations')
            ->where('sub_activity_id', $id)
            ->where('year', $year)
            ->sum('amount'),

        default => 0,
    };
}

    private function scopedNodeIds(): ?array
    {
        $currentUser = Auth::user();

        if (!$currentUser || $currentUser->isAdmin()) {
            return null;
        }

        if (!$currentUser->governance_node_id) {
            return [];
        }

        return [$currentUser->governance_node_id];
    }

    private function assertFundingInScope(ProgramFunding $funding): void
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (!$funding->governance_node_id || !in_array($funding->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this funding.');
        }
    }

    private function assertProjectInScope(Project $project): void
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (!$project->governance_node_id || !in_array($project->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this project.');
        }
    }

    private function assertActivityInScope(Activity $activity): void
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        $nodeId = $activity->governance_node_id ?? $activity->project?->governance_node_id;
        if (!$nodeId || !in_array($nodeId, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this activity.');
        }
    }

    private function assertSubActivityInScope(SubActivity $sub): void
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        $nodeId = $sub->governance_node_id ?? $sub->activity?->governance_node_id;
        if (!$nodeId || !in_array($nodeId, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this sub-activity.');
        }
    }

    private function assertAllocationInScope(string $level, string $id): void
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        $nodeId = match ($level) {
            'project' => Project::find($id)?->governance_node_id,
            'activity' => Activity::find($id)?->governance_node_id,
            'sub_activity' => SubActivity::find($id)?->governance_node_id,
            default => null,
        };

        if (!$nodeId || !in_array($nodeId, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this allocation.');
        }
    }

    private function assertResourceCategoryInScope(string $categoryId): void
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        $nodeId = ResourceCategory::find($categoryId)?->governance_node_id;
        if (!$nodeId || !in_array($nodeId, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this resource category.');
        }
    }

    private function assertCommitmentInScope(BudgetCommitment $commitment): void
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (!$commitment->governance_node_id || !in_array($commitment->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this commitment.');
        }
    }

    private function assertResourceInScope(Resource $resource): void
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (!$resource->governance_node_id || !in_array($resource->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this resource.');
        }
    }
}
