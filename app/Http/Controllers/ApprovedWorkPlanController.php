<?php

namespace App\Http\Controllers;

use App\Exports\ApprovedWorkPlanRegistryExport;
use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\ApprovedWorkPlan;
use App\Models\ApprovedWorkPlanItemReview;
use App\Models\BudgetCommitment;
use App\Models\Funder;
use App\Models\Program;
use App\Models\ProgramFunding;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Resource;
use App\Models\ResourceCategory;
use App\Models\SystemAuditLog;
use App\Models\SubActivity;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ApprovedWorkPlanController extends Controller
{
    use ScopesAssignedPortfolios;

    public function index(Request $request)
    {
        return view('finance.awp.registry', $this->workPlanRegistryData($request));
    }

    public function exportRegistryPdf(Request $request)
    {
        $data = $this->workPlanRegistryData($request);
        $filename = 'work-plans-registry-' . now()->format('Ymd-His') . '.pdf';

        return Pdf::loadView('finance.awp.registry-pdf', $data)
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    public function exportRegistryExcel(Request $request)
    {
        $data = $this->workPlanRegistryData($request);
        $filename = 'work-plans-registry-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(new ApprovedWorkPlanRegistryExport(
            $data['folders'],
            $data['summary'],
            $data['selectedProgramId'],
            $data['selectedYear']
        ), $filename);
    }

    private function workPlanRegistryData(Request $request): array
    {
        $currentUser = $request->user();
        $programsQuery = Program::query()->orderBy('name');

        if ($this->userHasAssignedPortfolioScope($currentUser)) {
            $this->applyAssignedPortfolioScopeToPrograms($programsQuery, $currentUser);
        }

        $programs = $programsQuery->get();
        $selectedProgramId = $request->input('program_id');
        $selectedYear = $request->input('year');

        if ($selectedProgramId && ! $programs->pluck('id')->map(fn ($id) => (string) $id)->contains((string) $selectedProgramId)) {
            $selectedProgramId = null;
        }

        $requestsQuery = PurchaseRequest::with([
            'programFunding.program',
            'items.resource',
            'commitments.approvedWorkPlans',
        ])
            ->whereNotNull('work_plan_source');

        if ($this->userHasAssignedPortfolioScope($currentUser)) {
            $this->applyAssignedPortfolioScopeToPurchaseRequests($requestsQuery, $currentUser);
        }

        $requests = $requestsQuery
            ->when($selectedProgramId, function ($query) use ($selectedProgramId) {
                $query->whereHas('programFunding', fn ($fundingQuery) => $fundingQuery->where('program_id', $selectedProgramId));
            })
            ->when($selectedYear, fn ($query) => $query->where('start_year', (int) $selectedYear))
            ->orderByDesc('updated_at')
            ->get();

        $folders = $requests
            ->groupBy(function (PurchaseRequest $request) {
                return implode('|', [
                    $request->programFunding?->program_id ?: 'no-program',
                    $request->start_year ?: 'no-year',
                    $request->work_plan_source ?: 'Untitled Work Plan',
                ]);
            })
            ->map(function ($folderRequests) {
                $first = $folderRequests->first();
                $program = $first->programFunding?->program;
                $items = $folderRequests->flatMap(fn (PurchaseRequest $request) => $request->items);
                $commitments = $folderRequests->flatMap(fn (PurchaseRequest $request) => $request->commitments);
                $approvedPlans = $commitments->flatMap(fn (BudgetCommitment $commitment) => $commitment->approvedWorkPlans);
                $statusCounts = $approvedPlans
                    ->groupBy(fn (ApprovedWorkPlan $plan) => $plan->status ?: 'draft')
                    ->map(fn ($rows) => $rows->count());

                return [
                    'folder_name' => $first->work_plan_source ?: 'Untitled Work Plan',
                    'program' => $program,
                    'program_id' => $program?->id,
                    'year' => (int) $first->start_year,
                    'currency' => $first->currency ?: $program?->currency ?: 'USD',
                    'items_count' => $items->count(),
                    'planned_amount' => round((float) $folderRequests->sum('total_amount'), 2),
                    'committed_amount' => round((float) $commitments->sum('commitment_amount'), 2),
                    'approved_count' => (int) ($statusCounts['approved'] ?? 0),
                    'submitted_count' => (int) ($statusCounts['submitted'] ?? 0),
                    'draft_count' => (int) ($statusCounts['draft'] ?? 0),
                    'closed_count' => (int) ($statusCounts['closed'] ?? 0),
                    'latest_update' => $folderRequests->max('updated_at'),
                    'items_preview' => $items
                        ->take(5)
                        ->map(fn (PurchaseRequestItem $item) => $item->resource?->name ?: $item->milestone ?: 'Work plan item')
                        ->values(),
                ];
            })
            ->sortByDesc('latest_update')
            ->values();

        $years = $requests
            ->pluck('start_year')
            ->filter()
            ->map(fn ($year) => (int) $year)
            ->unique()
            ->sortDesc()
            ->values();

        $summary = [
            'folders' => $folders->count(),
            'items' => $folders->sum('items_count'),
            'amount' => $folders->sum('planned_amount'),
            'programs' => $folders->pluck('program_id')->filter()->unique()->count(),
        ];

        return [
            'programs' => $programs,
            'folders' => $folders,
            'years' => $years,
            'summary' => $summary,
            'selectedProgramId' => $selectedProgramId,
            'selectedYear' => $selectedYear,
        ];
    }

    public function partnerIndex(Request $request)
    {
        $funder = $this->partnerFunder($request);
        $fundings = ProgramFunding::with('program')
            ->where('funder_id', $funder->id)
            ->where('status', 'approved')
            ->get();

        $programs = $fundings
            ->map(function (ProgramFunding $funding) {
                return $funding->program
                    ?: ($funding->program_name ? Program::where('name', $funding->program_name)->first() : null);
            })
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        $program = null;
        $report = null;
        $summary = null;
        $filters = $this->resolveFilters($request);
        $selectedProgramId = $request->input('program_id') ?: $programs->first()?->id;

        if ($selectedProgramId) {
            $program = Program::with(['projects.activities.subActivities.allocations'])->findOrFail($selectedProgramId);

            $programFundingIds = $fundings
                ->filter(function (ProgramFunding $funding) use ($program) {
                    return (string) $funding->program_id === (string) $program->id
                        || ($funding->program_name && $funding->program_name === $program->name);
                })
                ->pluck('id')
                ->all();

            abort_if(empty($programFundingIds), 403, 'This work plan is not funded by your partner account.');

            $report = $this->buildWorkPlanHierarchy($program, $programFundingIds, $filters);
            $summary = $this->summarizeWorkPlan($report);
        }

        return view('finance.awp.index', [
            'programs' => $programs,
            'program' => $program,
            'funders' => collect([$funder]),
            'report' => $report,
            'summary' => $summary,
            'filters' => $filters,
            'query' => $request->query(),
            'selectedProgramId' => $selectedProgramId,
            'awpLayout' => 'layouts.partner',
            'awpTitle' => 'Partner Work Plan - No Objection',
            'awpSubtitle' => 'Review work plan items funded by your organization and record no-objection decisions.',
            'awpIndexRoute' => 'partner.workplan.index',
            'awpReviewRoute' => 'partner.workplan.items.review',
            'awpDocumentRoute' => 'partner.workplan.items.document',
            'awpCanReview' => (bool) $request->user()?->hasPermission('partner.workplan.review'),
            'awpCanEdit' => false,
            'awpAllowDocumentUpload' => false,
            'awpReadOnly' => ! $request->user()?->hasPermission('partner.workplan.review'),
        ]);
    }

    public function create(Request $request)
    {
        $currentUser = $request->user();
        $programsQuery = Program::query()->orderBy('name');

        if ($this->userHasAssignedPortfolioScope($currentUser)) {
            $this->applyAssignedPortfolioScopeToPrograms($programsQuery, $currentUser);
        }

        $programs = $programsQuery->get();
        $selectedProgramId = $request->input('program_id') ?: $programs->first()?->id;
        if ($selectedProgramId && ! $programs->pluck('id')->map(fn ($id) => (string) $id)->contains((string) $selectedProgramId)) {
            $selectedProgramId = $programs->first()?->id;
        }

        $program = null;
        $funding = null;
        $fundings = collect();
        $years = collect();
        $selectedYear = (int) $request->input('year', now()->year);
        $folderName = trim((string) $request->input('folder_name'));
        $folderOptions = collect();
        $sheet = null;

        if ($selectedProgramId) {
            $program = Program::with([
                'projects.allocations',
                'projects.activities.allocations',
                'projects.activities.subActivities.allocations',
                'approvedFundings.funder',
                'fundings.funder',
            ])->findOrFail($selectedProgramId);
            $this->assertProgramInAwpScope($program, $currentUser);

            $fundings = $this->programWorkPlanFundings($program);
            $funding = $fundings->first();
            $years = $this->programWorkPlanYears($program);
            $selectedYear = $years->contains($selectedYear) ? $selectedYear : (int) ($years->first() ?: now()->year);

            $folderOptions = $this->folderOptionsForProgramYear($fundings->pluck('id')->all(), $selectedYear);
            $folderName = $folderName
                ?: ($folderOptions->first() ?: $this->defaultWorkPlanFolderName($program));

            $sheet = $this->buildAllocationWorkPlanSheet($program, $fundings->pluck('id')->all(), $selectedYear);
        }

        return view('finance.awp.create', [
            'programs' => $programs,
            'program' => $program,
            'funding' => $funding,
            'fundings' => $fundings,
            'years' => $years,
            'selectedProgramId' => $selectedProgramId,
            'selectedYear' => $selectedYear,
            'folderName' => $folderName,
            'folderOptions' => $folderOptions,
            'sheet' => $sheet,
            'currency' => $program?->currency ?? $funding?->currency ?? 'USD',
        ]);
    }

    public function storeAllocationSheet(Request $request)
    {
        $data = $request->validate([
            'program_id' => ['required', 'uuid', Rule::exists('myb_programs', 'id')],
            'year' => 'required|integer|min:1900|max:2200',
            'folder_name' => 'required|string|max:180',
            'use_allocations' => 'accepted',
            'include' => 'nullable|array',
            'include.*' => 'nullable|boolean',
            'amounts' => 'nullable|array',
            'amounts.*' => 'nullable|numeric|min:0|max:999999999999.99',
            'documents' => 'nullable|array',
            'documents.*.tor_file' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png|max:20480',
            'documents.*.concept_note_file' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png|max:20480',
        ]);

        $program = Program::with([
            'projects.activities.subActivities.allocations',
            'approvedFundings',
            'fundings',
        ])->findOrFail($data['program_id']);
        $this->assertProgramInAwpScope($program, $request->user());

        $funding = $this->programWorkPlanFundings($program)->first();

        if (! $funding) {
            throw ValidationException::withMessages([
                'program_id' => 'An approved program funding source is required before a work plan can be created.',
            ]);
        }

        $subActivities = $program->projects
            ->flatMap(fn ($project) => $project->activities)
            ->flatMap(fn ($activity) => $activity->subActivities)
            ->keyBy(fn ($subActivity) => (string) $subActivity->id);

        $includedIds = collect($data['include'] ?? [])
            ->filter(fn ($value) => (bool) $value)
            ->keys()
            ->filter(fn ($id) => $subActivities->has((string) $id))
            ->values();

        if ($includedIds->isEmpty()) {
            throw ValidationException::withMessages([
                'include' => 'Select at least one sub-activity line to create the work plan sheet.',
            ]);
        }

        $folderName = trim($data['folder_name']);
        $year = (int) $data['year'];
        $currency = $program->currency ?: $funding->currency ?: 'USD';
        $created = 0;
        $createdAmount = 0.0;

        DB::transaction(function () use (
            $includedIds,
            $subActivities,
            $data,
            $year,
            $folderName,
            $funding,
            $program,
            $currency,
            &$created,
            &$createdAmount
        ) {
            foreach ($includedIds as $subActivityId) {
                $subActivity = $subActivities[(string) $subActivityId];
                $amount = round((float) data_get($data, "amounts.{$subActivityId}", 0), 2);

                if ($amount <= 0) {
                    continue;
                }

                $this->assertWorkPlanAmountWithinBudget(
                    $subActivity,
                    $year,
                    $amount
                );

                $activity = $subActivity->activity;
                $project = $activity?->project;
                $requestGovernanceNodeId = $subActivity->governance_node_id ?: $funding->governance_node_id;
                $resourceGovernanceNodeId = $this->workPlanResourceGovernanceNodeId($subActivity, $funding, request()->user());
                $category = $this->resourceCategoryForObjectType(
                    'Work Plan Allocation',
                    request()->user()?->id,
                    $resourceGovernanceNodeId
                );
                $resource = Resource::firstOrCreate(
                    [
                        'resource_category_id' => $category->id,
                        'name' => $subActivity->name,
                    ],
                    [
                        'governance_node_id' => $resourceGovernanceNodeId,
                        'reference_code' => 'AWP-' . $year . '-' . Str::upper(Str::random(5)),
                        'description' => $subActivity->description ?: $activity?->name,
                        'status' => 'active',
                        'is_human_resource' => false,
                        'created_by' => request()->user()?->id,
                    ]
                );

                $purchaseRequest = PurchaseRequest::create([
                    'reference_no' => $this->nextPurchaseRequestReference('AWP'),
                    'program_funding_id' => $funding->id,
                    'governance_node_id' => $requestGovernanceNodeId,
                    'allocation_level' => 'sub_activity',
                    'allocation_id' => $subActivity->id,
                    'start_year' => $year,
                    'commitment_date' => Carbon::create($year, 1, 1)->toDateString(),
                    'delivery_date' => Carbon::create($year, 12, 31)->toDateString(),
                    'currency' => $currency,
                    'total_amount' => $amount,
                    'description' => "FY{$year} {$folderName}: {$subActivity->name}",
                    'status' => 'draft',
                    'work_plan_source' => $folderName,
                    'work_plan_component' => $project?->name,
                    'work_plan_sub_component' => $activity?->name,
                    'created_by' => request()->user()?->id,
                ]);

                $item = PurchaseRequestItem::create([
                    'purchase_request_id' => $purchaseRequest->id,
                    'resource_category_id' => $category->id,
                    'resource_id' => $resource->id,
                    'milestone' => $subActivity->name,
                    'amount' => $amount,
                    'work_plan_source' => $folderName,
                    'work_plan_sort_order' => ($created + 1) * 10,
                    'work_plan_serial' => (string) ($created + 1),
                    'implemented_by' => 'ATTP Secretariat',
                    'budget_code' => $this->workPlanBudgetCode($project, $activity, $subActivity, $year),
                    'object_type' => 'Work Plan Allocation',
                    'estimated_amount' => $amount,
                    'work_plan_payment_basis' => 'scheduled',
                    'intermediate_indicator' => $subActivity->name,
                    'result_indicator' => $subActivity->expected_outcome_value ?: $activity?->expected_outcome_value,
                    'observations' => 'Pulled from the approved allocation structure.',
                ]);
                $this->syncWorkPlanItemDocuments($item, $funding, request(), "documents.{$subActivityId}");

                $commitment = BudgetCommitment::create([
                    'purchase_request_id' => $purchaseRequest->id,
                    'program_funding_id' => $funding->id,
                    'governance_node_id' => $purchaseRequest->governance_node_id,
                    'resource_category_id' => $category->id,
                    'resource_id' => $resource->id,
                    'allocation_level' => 'sub_activity',
                    'allocation_id' => $subActivity->id,
                    'commitment_amount' => $amount,
                    'commitment_year' => $year,
                    'status' => BudgetCommitment::STATUS_SUBMITTED,
                    'description' => "FY{$year} {$folderName}: {$subActivity->name}",
                    'created_by' => request()->user()?->id,
                ]);

                ApprovedWorkPlan::create([
                    'awp_code' => $this->nextWorkPlanCode($year),
                    'title' => $subActivity->name,
                    'budget_commitment_id' => $commitment->id,
                    'program_funding_id' => $funding->id,
                    'governance_node_id' => $purchaseRequest->governance_node_id,
                    'fiscal_year' => (string) $year,
                    'planned_amount' => $amount,
                    'currency' => $currency,
                    'start_date' => Carbon::create($year, 1, 1)->toDateString(),
                    'end_date' => Carbon::create($year, 12, 31)->toDateString(),
                    'status' => 'submitted',
                    'description' => $folderName,
                    'expected_outputs' => $item->result_indicator ?: $subActivity->name,
                    'implementation_notes' => "Created from allocation pull for {$program->name}.",
                    'created_by' => request()->user()?->id,
                ]);

                $created++;
                $createdAmount += $amount;
            }
        });

        if ($created === 0) {
            throw ValidationException::withMessages([
                'amounts' => 'No work plan line was created because every selected amount was zero.',
            ]);
        }

        $this->auditAction('work_plan.created_from_allocations', 'Work plan sheet created from allocations', [
            'program_id' => $program->id,
            'year' => $year,
            'folder_name' => $folderName,
            'items_created' => $created,
            'amount' => $createdAmount,
        ]);

        return redirect()
            ->route('finance.awp.index', [
                'program_id' => $program->id,
                'year' => $year,
            ])
            ->with('success', "{$created} work plan line(s) created for {$folderName}.");
    }

    public function storeManualSheet(Request $request)
    {
        $data = $request->validate([
            'program_id' => ['required', 'uuid', Rule::exists('myb_programs', 'id')],
            'year' => 'required|integer|min:1900|max:2200',
            'folder_name' => 'required|string|max:180',
            'items' => 'required|array|min:1',
            'items.*.sub_activity_id' => ['required', 'uuid', Rule::exists('myb_sub_activities', 'id')],
            'items.*.title' => 'required|string|max:255',
            'items.*.actual_amount' => 'required|numeric|min:0.01|max:999999999999.99',
            'items.*.implemented_by' => 'nullable|string|max:255',
            'items.*.budget_code' => 'nullable|string|max:255',
            'items.*.object_type' => 'nullable|string|max:255',
            'items.*.result_indicator' => 'nullable|string|max:4000',
            'items.*.notes' => 'nullable|string|max:4000',
            'items.*.tor_file' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png|max:20480',
            'items.*.concept_note_file' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png|max:20480',
        ]);

        $program = Program::with([
            'projects.activities.subActivities.allocations',
            'approvedFundings',
            'fundings',
        ])->findOrFail($data['program_id']);
        $this->assertProgramInAwpScope($program, $request->user());

        $funding = $this->programWorkPlanFundings($program)->first();

        if (! $funding) {
            throw ValidationException::withMessages([
                'program_id' => 'An approved program funding source is required before a manual work plan can be created.',
            ]);
        }

        $subActivities = $program->projects
            ->flatMap(fn ($project) => $project->activities)
            ->flatMap(fn ($activity) => $activity->subActivities)
            ->keyBy(fn ($subActivity) => (string) $subActivity->id);

        $year = (int) $data['year'];
        $folderName = trim($data['folder_name']);
        $currency = $program->currency ?: $funding->currency ?: 'USD';
        $created = 0;
        $createdAmount = 0.0;

        DB::transaction(function () use (
            $data,
            $subActivities,
            $year,
            $folderName,
            $funding,
            $program,
            $currency,
            &$created,
            &$createdAmount
        ) {
            foreach ($data['items'] as $index => $row) {
                $subActivity = $subActivities[(string) $row['sub_activity_id']] ?? null;
                if (! $subActivity) {
                    throw ValidationException::withMessages([
                        "items.{$index}.sub_activity_id" => 'Select a sub-activity that belongs to the selected program.',
                    ]);
                }

                $amount = round((float) $row['actual_amount'], 2);
                $this->assertWorkPlanAmountWithinBudget($subActivity, $year, $amount);

                $title = trim((string) $row['title']);
                $activity = $subActivity->activity;
                $project = $activity?->project;
                $objectType = trim((string) ($row['object_type'] ?? '')) ?: 'Manual Work Plan';
                $requestGovernanceNodeId = $subActivity->governance_node_id ?: $funding->governance_node_id;
                $resourceGovernanceNodeId = $this->workPlanResourceGovernanceNodeId($subActivity, $funding, request()->user());
                $category = $this->resourceCategoryForObjectType(
                    $objectType,
                    request()->user()?->id,
                    $resourceGovernanceNodeId
                );
                $resource = Resource::create([
                    'resource_category_id' => $category->id,
                    'governance_node_id' => $resourceGovernanceNodeId,
                    'name' => $title,
                    'reference_code' => ($row['budget_code'] ?? null) ?: 'AWP-' . $year . '-' . Str::upper(Str::random(5)),
                    'description' => ($row['result_indicator'] ?? null) ?: $subActivity->name,
                    'status' => 'active',
                    'is_human_resource' => false,
                    'created_by' => request()->user()?->id,
                ]);

                $purchaseRequest = PurchaseRequest::create([
                    'reference_no' => $this->nextPurchaseRequestReference('AWP'),
                    'program_funding_id' => $funding->id,
                    'governance_node_id' => $requestGovernanceNodeId,
                    'allocation_level' => 'sub_activity',
                    'allocation_id' => $subActivity->id,
                    'start_year' => $year,
                    'commitment_date' => Carbon::create($year, 1, 1)->toDateString(),
                    'delivery_date' => Carbon::create($year, 12, 31)->toDateString(),
                    'currency' => $currency,
                    'total_amount' => $amount,
                    'description' => "FY{$year} {$folderName}: {$title}",
                    'status' => 'draft',
                    'work_plan_source' => $folderName,
                    'work_plan_component' => $project?->name,
                    'work_plan_sub_component' => $activity?->name,
                    'created_by' => request()->user()?->id,
                ]);

                $item = PurchaseRequestItem::create([
                    'purchase_request_id' => $purchaseRequest->id,
                    'resource_category_id' => $category->id,
                    'resource_id' => $resource->id,
                    'milestone' => $title,
                    'amount' => $amount,
                    'work_plan_source' => $folderName,
                    'work_plan_sort_order' => ($created + 1) * 10,
                    'work_plan_serial' => (string) ($created + 1),
                    'implemented_by' => ($row['implemented_by'] ?? null) ?: 'ATTP Secretariat',
                    'budget_code' => ($row['budget_code'] ?? null) ?: $this->workPlanBudgetCode($project, $activity, $subActivity, $year),
                    'object_type' => $objectType,
                    'estimated_amount' => $amount,
                    'work_plan_payment_basis' => 'scheduled',
                    'intermediate_indicator' => $title,
                    'result_indicator' => ($row['result_indicator'] ?? null) ?: null,
                    'observations' => ($row['notes'] ?? null) ?: 'Manually created work plan line.',
                ]);
                $this->syncWorkPlanItemDocuments($item, $funding, request(), "items.{$index}");

                $commitment = BudgetCommitment::create([
                    'purchase_request_id' => $purchaseRequest->id,
                    'program_funding_id' => $funding->id,
                    'governance_node_id' => $purchaseRequest->governance_node_id,
                    'resource_category_id' => $category->id,
                    'resource_id' => $resource->id,
                    'allocation_level' => 'sub_activity',
                    'allocation_id' => $subActivity->id,
                    'commitment_amount' => $amount,
                    'commitment_year' => $year,
                    'status' => BudgetCommitment::STATUS_SUBMITTED,
                    'description' => "FY{$year} {$folderName}: {$title}",
                    'created_by' => request()->user()?->id,
                ]);

                ApprovedWorkPlan::create([
                    'awp_code' => $this->nextWorkPlanCode($year),
                    'title' => $title,
                    'budget_commitment_id' => $commitment->id,
                    'program_funding_id' => $funding->id,
                    'governance_node_id' => $purchaseRequest->governance_node_id,
                    'fiscal_year' => (string) $year,
                    'planned_amount' => $amount,
                    'currency' => $currency,
                    'start_date' => Carbon::create($year, 1, 1)->toDateString(),
                    'end_date' => Carbon::create($year, 12, 31)->toDateString(),
                    'status' => 'submitted',
                    'description' => $folderName,
                    'expected_outputs' => $item->result_indicator ?: $title,
                    'implementation_notes' => ($row['notes'] ?? null) ?: 'Manually created from the Work Plans Registry.',
                    'created_by' => request()->user()?->id,
                ]);

                $created++;
                $createdAmount += $amount;
            }
        });

        $this->auditAction('work_plan.created_manually', 'Manual work plan sheet created', [
            'program_id' => $program->id,
            'year' => $year,
            'folder_name' => $folderName,
            'items_created' => $created,
            'amount' => $createdAmount,
        ]);

        return redirect()
            ->route('finance.awp.index', [
                'program_id' => $program->id,
                'year' => $year,
            ])
            ->with('success', "{$created} manual work plan line(s) saved for {$folderName}.");
    }

    public function renameFolder(Request $request)
    {
        $data = $request->validate([
            'program_id' => ['required', 'uuid', Rule::exists('myb_programs', 'id')],
            'year' => 'required|integer|min:1900|max:2200',
            'old_folder_name' => 'required|string|max:180',
            'folder_name' => 'required|string|max:180',
        ]);

        $program = Program::with(['approvedFundings', 'fundings'])->findOrFail($data['program_id']);
        $this->assertProgramInAwpScope($program, $request->user());

        $fundingIds = $this->programWorkPlanFundings($program)->pluck('id')->all();

        if (empty($fundingIds)) {
            throw ValidationException::withMessages([
                'program_id' => 'No funding source was found for this work plan folder.',
            ]);
        }

        $oldName = trim($data['old_folder_name']);
        $newName = trim($data['folder_name']);
        $year = (int) $data['year'];

        DB::transaction(function () use ($fundingIds, $oldName, $newName, $year) {
            $requestIds = PurchaseRequest::query()
                ->whereIn('program_funding_id', $fundingIds)
                ->where('start_year', $year)
                ->where('work_plan_source', $oldName)
                ->pluck('id');

            PurchaseRequest::whereIn('id', $requestIds)->update(['work_plan_source' => $newName]);
            PurchaseRequestItem::whereIn('purchase_request_id', $requestIds)->update(['work_plan_source' => $newName]);

            ApprovedWorkPlan::query()
                ->whereIn('program_funding_id', $fundingIds)
                ->where('fiscal_year', (string) $year)
                ->where('description', $oldName)
                ->update(['description' => $newName]);
        });

        $this->auditAction('work_plan.folder_renamed', 'Work plan folder renamed', [
            'program_id' => $program->id,
            'year' => $year,
            'old_folder_name' => $oldName,
            'folder_name' => $newName,
        ]);

        return redirect()
            ->route('finance.awp.create', [
                'program_id' => $program->id,
                'year' => $year,
                'folder_name' => $newName,
            ])
            ->with('success', 'Work plan folder renamed.');
    }

    public function reviewItem(Request $request, PurchaseRequestItem $item)
    {
        abort_unless($this->canReviewAwpItems($request), 403);
        $this->assertAwpItemInScope($item, $request->user());

        $data = $request->validate([
            'status' => 'required|in:pending,approved,rejected,needs_revision',
            'review_notes' => 'nullable|string|max:2000',
            'document_type' => 'nullable|required_with:document_file|in:tor,terms_of_reference,concept_note',
            'document_file' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png|max:20480',
            'tor_file' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png|max:20480',
            'concept_note_file' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png|max:20480',
        ]);

        $item->load('purchaseRequest.programFunding.funder');
        $purchaseRequest = $item->purchaseRequest;
        $existingReview = $item->awpReview;

        $documentPayload = [];
        if ($request->hasFile('document_file')) {
            if ($existingReview?->document_path) {
                Storage::disk('local')->delete($existingReview->document_path);
            }

            $file = $request->file('document_file');
            $documentPayload = [
                'document_type' => $data['document_type'],
                'document_path' => $file->store("approved-work-plan-items/{$item->id}", 'local'),
                'document_name' => $file->getClientOriginalName(),
                'document_uploaded_by' => $request->user()?->id,
                'document_uploaded_at' => now(),
            ];
        }

        if ($request->hasFile('tor_file')) {
            if ($existingReview?->tor_path) {
                Storage::disk('local')->delete($existingReview->tor_path);
            }

            $file = $request->file('tor_file');
            $documentPayload = [
                ...$documentPayload,
                'tor_path' => $file->store("approved-work-plan-items/{$item->id}/tor", 'local'),
                'tor_name' => $file->getClientOriginalName(),
                'tor_uploaded_at' => now(),
                'document_uploaded_by' => $request->user()?->id,
                'document_uploaded_at' => now(),
            ];
        }

        if ($request->hasFile('concept_note_file')) {
            if ($existingReview?->concept_note_path) {
                Storage::disk('local')->delete($existingReview->concept_note_path);
            }

            $file = $request->file('concept_note_file');
            $documentPayload = [
                ...$documentPayload,
                'concept_note_path' => $file->store("approved-work-plan-items/{$item->id}/concept-note", 'local'),
                'concept_note_name' => $file->getClientOriginalName(),
                'concept_note_uploaded_at' => now(),
                'document_uploaded_by' => $request->user()?->id,
                'document_uploaded_at' => now(),
            ];
        }

        ApprovedWorkPlanItemReview::updateOrCreate(
            ['purchase_request_item_id' => $item->id],
            [
                'program_funding_id' => $purchaseRequest?->program_funding_id,
                'funder_id' => $purchaseRequest?->programFunding?->funder_id,
                'status' => $data['status'],
                'reviewed_by' => $request->user()?->id,
                'reviewed_at' => now(),
                'review_notes' => $data['review_notes'] ?? null,
                ...$documentPayload,
            ]
        );
        $this->syncApprovedWorkPlanStatusForItem($item, $data['status'], $data['review_notes'] ?? null);

        return back()->with('success', 'Work plan item review updated.');
    }

    public function partnerReviewItem(Request $request, PurchaseRequestItem $item)
    {
        $this->assertPartnerCanAccessItem($request, $item);

        $data = $request->validate([
            'status' => 'required|in:pending,approved,rejected,needs_revision',
            'review_notes' => 'nullable|string|max:2000',
        ]);

        $item->load('purchaseRequest.programFunding.funder');
        $purchaseRequest = $item->purchaseRequest;

        ApprovedWorkPlanItemReview::updateOrCreate(
            ['purchase_request_item_id' => $item->id],
            [
                'program_funding_id' => $purchaseRequest?->program_funding_id,
                'funder_id' => $purchaseRequest?->programFunding?->funder_id,
                'status' => $data['status'],
                'reviewed_by' => $request->user()?->id,
                'reviewed_at' => now(),
                'review_notes' => $data['review_notes'] ?? null,
            ]
        );
        $this->syncApprovedWorkPlanStatusForItem($item, $data['status'], $data['review_notes'] ?? null);

        return back()->with('success', 'World Bank review updated.');
    }

    public function updateItem(Request $request, PurchaseRequestItem $item)
    {
        abort_unless($request->user()?->hasPermission('finance.awp.edit'), 403);
        $this->assertAwpItemInScope($item, $request->user());

        $item->loadMissing([
            'awpReview',
            'purchaseRequest.commitments.approvedWorkPlans',
            'purchaseRequest.programFunding',
            'purchaseRequest.subActivity',
            'resource',
            'resourceCategory',
        ]);

        abort_if(
            $item->awpReview?->status === 'approved',
            403,
            'This work plan item is already approved by the World Bank and cannot be edited.'
        );

        $data = $request->validate([
            'sub_activity_id' => [
                'required',
                'uuid',
                Rule::exists('myb_sub_activities', 'id'),
            ],
            'activity' => 'nullable|string|max:255',
            'work_plan_serial' => 'nullable|string|max:40',
            'implemented_by' => 'nullable|string|max:255',
            'budget_code' => 'nullable|string|max:255',
            'estimated_amount' => 'required|numeric|min:0|max:999999999999.99',
            'object_type' => 'nullable|string|max:255',
            'work_plan_months' => 'nullable|array',
            'work_plan_months.*' => ['string', Rule::in(array_keys($this->workPlanMonthLabels()))],
            'work_plan_audience' => 'nullable|string|max:255',
            'work_plan_units' => 'nullable|string|max:255',
            'work_plan_payment_basis' => ['nullable', Rule::in(['one_off', 'scheduled', 'monthly'])],
            'work_plan_person_months' => 'nullable|integer|min:0|max:120',
            'work_plan_monthly_amount' => 'nullable|numeric|min:0|max:999999999999.99',
            'intermediate_indicator' => 'nullable|string|max:4000',
            'result_indicator' => 'nullable|string|max:4000',
            'observations' => 'nullable|string|max:4000',
            'attp_secretariat_comments' => 'nullable|string|max:4000',
        ]);
        $data = array_merge([
            'activity' => null,
            'work_plan_serial' => null,
            'implemented_by' => null,
            'budget_code' => null,
            'object_type' => null,
            'work_plan_months' => [],
            'work_plan_audience' => null,
            'work_plan_units' => null,
            'work_plan_payment_basis' => null,
            'work_plan_person_months' => null,
            'work_plan_monthly_amount' => null,
            'intermediate_indicator' => null,
            'result_indicator' => null,
            'observations' => null,
            'attp_secretariat_comments' => null,
        ], $data);

        $purchaseRequest = $item->purchaseRequest;
        $selectedSubActivity = SubActivity::with('activity.project')->findOrFail($data['sub_activity_id']);
        $this->assertSubActivityInAwpScope($selectedSubActivity, $request->user());

        $programId = $purchaseRequest?->programFunding?->program_id;
        if ($programId && (string) $selectedSubActivity->activity?->project?->program_id !== (string) $programId) {
            throw ValidationException::withMessages([
                'sub_activity_id' => 'Select a sub-activity that belongs to this work plan program.',
            ]);
        }

        $data['activity'] = trim((string) ($data['activity'] ?? '')) ?: $selectedSubActivity->name;

        $amount = round((float) $data['estimated_amount'], 2);
        $year = (int) ($purchaseRequest?->start_year ?: $purchaseRequest?->commitments?->first()?->commitment_year ?: now()->year);
        $this->assertWorkPlanAmountWithinBudget(
            $selectedSubActivity,
            $year,
            $amount,
            [],
            $purchaseRequest?->commitments?->pluck('id')->all() ?? []
        );

        $months = $this->normalizeWorkPlanMonths($data['work_plan_months'] ?? []);
        $paymentBasis = $this->normalizePaymentBasis(
            $data['work_plan_payment_basis'] ?? null,
            $data,
            $months
        );
        $personMonths = $data['work_plan_person_months'] ?? null;
        $personMonths = $personMonths !== null && $personMonths !== ''
            ? (int) $personMonths
            : ($paymentBasis === 'monthly' ? count($months) : null);
        $monthlyAmount = $data['work_plan_monthly_amount'] ?? null;
        $monthlyAmount = $monthlyAmount !== null && $monthlyAmount !== ''
            ? round((float) $monthlyAmount, 2)
            : (($paymentBasis === 'monthly' && $personMonths > 0) ? round($amount / $personMonths, 2) : null);
        $resourceGovernanceNodeId = $this->workPlanResourceGovernanceNodeId(
            $selectedSubActivity,
            $purchaseRequest?->programFunding,
            $request->user()
        );
        $category = $this->resourceCategoryForObjectType(
            $data['object_type'] ?? null,
            $request->user()?->id,
            $resourceGovernanceNodeId
        );
        $resource = $this->syncWorkPlanResource(
            $item,
            $category,
            $data,
            $request->user()?->id,
            $resourceGovernanceNodeId
        );

        $item->update([
            'resource_category_id' => $category->id,
            'resource_id' => $resource->id,
            'work_plan_serial' => $data['work_plan_serial'] ?: null,
            'implemented_by' => $data['implemented_by'] ?: null,
            'budget_code' => $data['budget_code'] ?: null,
            'object_type' => $data['object_type'] ?: null,
            'estimated_amount' => $amount,
            'amount' => $amount,
            'work_plan_months' => $months ?: null,
            'work_plan_audience' => $data['work_plan_audience'] ?: null,
            'work_plan_units' => $data['work_plan_units'] ?: null,
            'work_plan_payment_basis' => $paymentBasis,
            'work_plan_person_months' => $personMonths,
            'work_plan_monthly_amount' => $monthlyAmount,
            'milestone' => $data['intermediate_indicator'] ?: $data['result_indicator'] ?: null,
            'intermediate_indicator' => $data['intermediate_indicator'] ?: null,
            'result_indicator' => $data['result_indicator'] ?: null,
            'observations' => $data['observations'] ?: null,
            'attp_secretariat_comments' => $data['attp_secretariat_comments'] ?: null,
        ]);

        if ($purchaseRequest) {
            $purchaseRequest->update([
                'allocation_id' => $selectedSubActivity->id,
                'total_amount' => $amount,
                'description' => 'FY' . ($purchaseRequest->start_year ?: now()->year) . ' ATTP work plan item: ' . $data['activity'],
            ]);

            foreach ($purchaseRequest->commitments as $commitment) {
                $commitment->update([
                    'resource_category_id' => $category->id,
                    'resource_id' => $resource->id,
                    'allocation_id' => $selectedSubActivity->id,
                    'commitment_amount' => $amount,
                    'description' => 'FY' . ($commitment->commitment_year ?: $purchaseRequest->start_year) . ' ATTP work plan commitment: ' . $data['activity'],
                ]);

                $commitment->approvedWorkPlans()->update([
                    'title' => $data['activity'],
                    'planned_amount' => $amount,
                    'expected_outputs' => $data['result_indicator'] ?: $data['intermediate_indicator'],
                    'implementation_notes' => trim(($data['observations'] ?? '') . "\n" . ($data['attp_secretariat_comments'] ?? '')) ?: null,
                ]);
            }
        }

        return back()->with('success', 'Work plan item updated.');
    }

    public function updateSheetItem(Request $request, PurchaseRequestItem $item)
    {
        abort_unless($request->user()?->hasPermission('finance.awp.edit'), 403);
        $this->assertAwpItemInScope($item, $request->user());

        $item->loadMissing([
            'awpReview',
            'purchaseRequest.commitments.approvedWorkPlans',
            'purchaseRequest.programFunding',
            'purchaseRequest.subActivity.activity.project',
            'resourceCategory',
            'resource',
        ]);

        abort_if(
            $item->awpReview?->status === 'approved',
            403,
            'This work plan item is already approved by the World Bank and cannot be edited.'
        );

        $purchaseRequest = $item->purchaseRequest;
        abort_unless($purchaseRequest?->subActivity, 404);

        $data = $request->validate([
            'activity' => 'required|string|max:255',
            'estimated_amount' => 'required|numeric|min:0|max:999999999999.99',
            'implemented_by' => 'nullable|string|max:255',
            'budget_code' => 'nullable|string|max:255',
            'object_type' => 'nullable|string|max:255',
            'result_indicator' => 'nullable|string|max:4000',
            'observations' => 'nullable|string|max:4000',
            'tor_file' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png|max:20480',
            'concept_note_file' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png|max:20480',
        ]);

        $amount = round((float) $data['estimated_amount'], 2);
        $year = (int) ($purchaseRequest->start_year ?: now()->year);
        $commitmentIds = $purchaseRequest->commitments->pluck('id')->all();

        $this->assertWorkPlanAmountWithinBudget(
            $purchaseRequest->subActivity,
            $year,
            $amount,
            [],
            $commitmentIds
        );

        $resourceGovernanceNodeId = $this->workPlanResourceGovernanceNodeId(
            $purchaseRequest->subActivity,
            $purchaseRequest->programFunding,
            $request->user()
        );
        $category = $item->resourceCategory;
        if (! $category || ($this->userHasAssignedPortfolioScope($request->user()) && ! $this->resourceCategoryIsInAssignedPortfolioNode($category, $request->user()))) {
            $category = $this->resourceCategoryForObjectType(
                $data['object_type'] ?? null,
                $request->user()?->id,
                $resourceGovernanceNodeId
            );
        }
        $resource = $item->resource ?: new Resource();
        $resource->fill([
            'resource_category_id' => $category->id,
            'governance_node_id' => $resource->governance_node_id ?: $resourceGovernanceNodeId,
            'name' => $data['activity'],
            'reference_code' => $resource->reference_code ?: $data['budget_code'] ?: 'AWP-' . $year . '-' . Str::upper(Str::random(5)),
            'description' => $data['result_indicator'] ?: null,
            'status' => 'active',
            'is_human_resource' => false,
            'created_by' => $resource->created_by ?: $request->user()?->id,
        ]);
        $resource->save();

        DB::transaction(function () use ($item, $purchaseRequest, $category, $resource, $data, $amount, $year) {
            $item->update([
                'resource_category_id' => $category->id,
                'resource_id' => $resource->id,
                'milestone' => $data['activity'],
                'amount' => $amount,
                'estimated_amount' => $amount,
                'implemented_by' => $data['implemented_by'] ?: null,
                'budget_code' => $data['budget_code'] ?: null,
                'object_type' => $data['object_type'] ?: $item->object_type,
                'intermediate_indicator' => $data['activity'],
                'result_indicator' => $data['result_indicator'] ?: null,
                'observations' => $data['observations'] ?: null,
            ]);

            $purchaseRequest->update([
                'total_amount' => $amount,
                'description' => "FY{$year} {$purchaseRequest->work_plan_source}: {$data['activity']}",
            ]);

            foreach ($purchaseRequest->commitments as $commitment) {
                $commitment->update([
                    'resource_category_id' => $category->id,
                    'resource_id' => $resource->id,
                    'commitment_amount' => $amount,
                    'description' => "FY{$year} {$purchaseRequest->work_plan_source}: {$data['activity']}",
                ]);

                $commitment->approvedWorkPlans()->update([
                    'title' => $data['activity'],
                    'planned_amount' => $amount,
                    'expected_outputs' => $data['result_indicator'] ?: $data['activity'],
                    'implementation_notes' => $data['observations'] ?: null,
                ]);
            }
        });
        $this->syncWorkPlanItemDocuments($item, $purchaseRequest->programFunding, $request);

        $this->auditAction('work_plan.item_updated', 'Work plan sheet item updated', [
            'item_id' => $item->id,
            'purchase_request_id' => $purchaseRequest->id,
            'amount' => $amount,
        ]);

        return back()->with('success', 'Work plan sheet item updated.');
    }

    public function destroyItem(Request $request, PurchaseRequestItem $item)
    {
        abort_unless($request->user()?->hasPermission('finance.awp.edit'), 403);
        $this->assertAwpItemInScope($item, $request->user());

        $item->loadMissing([
            'awpReview',
            'purchaseRequest.items',
            'purchaseRequest.commitments.approvedWorkPlans',
            'purchaseRequest.programFunding',
        ]);

        abort_if(
            $item->awpReview?->status === 'approved',
            403,
            'This work plan item is already approved by the World Bank and cannot be removed.'
        );

        $purchaseRequest = $item->purchaseRequest;
        abort_unless($purchaseRequest, 404);

        $deletedPayload = [
            'item_id' => $item->id,
            'purchase_request_id' => $purchaseRequest->id,
            'program_funding_id' => $purchaseRequest->program_funding_id,
            'amount' => (float) $item->amount,
        ];

        DB::transaction(function () use ($item, $purchaseRequest) {
            foreach ($purchaseRequest->commitments as $commitment) {
                $commitment->approvedWorkPlans()->delete();
                $commitment->delete();
            }

            if ($purchaseRequest->items->count() <= 1) {
                $purchaseRequest->delete();
                return;
            }

            $item->delete();
            $purchaseRequest->update([
                'total_amount' => $purchaseRequest->items()
                    ->whereKeyNot($item->id)
                    ->sum('amount'),
            ]);
        });

        $this->auditAction('work_plan.item_removed', 'Work plan sheet item removed', $deletedPayload);

        return back()->with('success', 'Work plan line removed.');
    }

    public function downloadItemDocument(PurchaseRequestItem $item)
    {
        if (request()->user()?->hasPermission('finance.awp.view')) {
            $this->assertAwpItemInScope($item, request()->user());
        }

        $review = $item->awpReview;

        $type = request('type');
        $path = match ($type) {
            'tor' => $review?->tor_path,
            'concept_note' => $review?->concept_note_path,
            default => $review?->document_path,
        };
        $name = match ($type) {
            'tor' => $review?->tor_name,
            'concept_note' => $review?->concept_note_name,
            default => $review?->document_name,
        };

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download(
            $path,
            $name ?: 'work-plan-document'
        );
    }

    public function partnerDownloadItemDocument(Request $request, PurchaseRequestItem $item)
    {
        $this->assertPartnerCanAccessItem($request, $item);

        return $this->downloadItemDocument($item);
    }

    private function syncWorkPlanItemDocuments(PurchaseRequestItem $item, ?ProgramFunding $funding, Request $request, ?string $baseKey = null): void
    {
        $item->loadMissing('awpReview');
        $existingReview = $item->awpReview;
        $torFile = $request->file($baseKey ? "{$baseKey}.tor_file" : 'tor_file');
        $conceptNoteFile = $request->file($baseKey ? "{$baseKey}.concept_note_file" : 'concept_note_file');
        $documentPayload = [
            'program_funding_id' => $funding?->id,
            'funder_id' => $funding?->funder_id,
            'status' => $existingReview?->status ?: 'pending',
        ];

        if ($torFile) {
            if ($existingReview?->tor_path) {
                Storage::disk('local')->delete($existingReview->tor_path);
            }

            $documentPayload = [
                ...$documentPayload,
                'tor_path' => $torFile->store("approved-work-plan-items/{$item->id}/tor", 'local'),
                'tor_name' => $torFile->getClientOriginalName(),
                'tor_uploaded_at' => now(),
                'document_uploaded_by' => $request->user()?->id,
                'document_uploaded_at' => now(),
            ];
        }

        if ($conceptNoteFile) {
            if ($existingReview?->concept_note_path) {
                Storage::disk('local')->delete($existingReview->concept_note_path);
            }

            $documentPayload = [
                ...$documentPayload,
                'concept_note_path' => $conceptNoteFile->store("approved-work-plan-items/{$item->id}/concept-note", 'local'),
                'concept_note_name' => $conceptNoteFile->getClientOriginalName(),
                'concept_note_uploaded_at' => now(),
                'document_uploaded_by' => $request->user()?->id,
                'document_uploaded_at' => now(),
            ];
        }

        ApprovedWorkPlanItemReview::updateOrCreate(
            ['purchase_request_item_id' => $item->id],
            $documentPayload
        );
    }

    private function syncApprovedWorkPlanStatusForItem(PurchaseRequestItem $item, string $reviewStatus, ?string $reviewNotes = null): void
    {
        $item->loadMissing('purchaseRequest.commitments.approvedWorkPlans');
        $workPlanStatus = $reviewStatus === 'approved' ? 'approved' : 'submitted';

        foreach ($item->purchaseRequest?->commitments ?? collect() as $commitment) {
            $payload = [
                'status' => $workPlanStatus,
                'review_notes' => $reviewNotes,
            ];

            if ($reviewStatus === 'approved') {
                $payload['approved_by'] = request()->user()?->id;
                $payload['approved_at'] = now();
            } else {
                $payload['approved_by'] = null;
                $payload['approved_at'] = null;
            }

            $commitment->approvedWorkPlans()->update($payload);
        }
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->hydrateFromCommitment($data);
        $this->assertAwpPayloadInScope($data, $request->user());

        ApprovedWorkPlan::create([
            ...$data,
            'awp_code' => $data['awp_code'] ?? $this->nextCode(),
            'currency' => 'USD',
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Approved Work Plan created.');
    }

    public function update(Request $request, ApprovedWorkPlan $awp)
    {
        $this->assertApprovedWorkPlanInScope($awp, $request->user());
        abort_if($awp->status === 'approved' && ! $request->user()?->can('finance.awp.approve'), 403);

        $data = $this->validated($request, $awp);
        $this->hydrateFromCommitment($data);
        $this->assertAwpPayloadInScope($data, $request->user());

        $awp->update([
            ...$data,
            'currency' => 'USD',
        ]);

        return back()->with('success', 'Approved Work Plan updated.');
    }

    public function approve(Request $request, ApprovedWorkPlan $awp)
    {
        $this->assertApprovedWorkPlanInScope($awp, $request->user());

        $data = $request->validate([
            'review_notes' => 'nullable|string|max:2000',
        ]);

        $awp->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'review_notes' => $data['review_notes'] ?? null,
        ]);

        return back()->with('success', 'Approved Work Plan approved.');
    }

    public function close(Request $request, ApprovedWorkPlan $awp)
    {
        $this->assertApprovedWorkPlanInScope($awp, $request->user());

        $data = $request->validate([
            'review_notes' => 'nullable|string|max:2000',
        ]);

        $awp->update([
            'status' => 'closed',
            'review_notes' => $data['review_notes'] ?? $awp->review_notes,
        ]);

        return back()->with('success', 'Approved Work Plan closed.');
    }

    public function destroy(ApprovedWorkPlan $awp)
    {
        $this->assertApprovedWorkPlanInScope($awp, request()->user());
        abort_if($awp->status === 'approved', 403, 'Approved work plans cannot be deleted.');

        $awp->delete();

        return back()->with('success', 'Approved Work Plan deleted.');
    }

    private function validated(Request $request, ?ApprovedWorkPlan $awp = null): array
    {
        return $request->validate([
            'awp_code' => ['nullable', 'string', 'max:100', Rule::unique('approved_work_plans', 'awp_code')->ignore($awp?->id)],
            'title' => 'required|string|max:255',
            'budget_commitment_id' => 'nullable|exists:myb_budget_commitments,id',
            'program_funding_id' => 'nullable|exists:myb_program_fundings,id',
            'fiscal_year' => 'nullable|string|max:20',
            'planned_amount' => 'required|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'required|in:draft,submitted,approved,closed',
            'description' => 'nullable|string',
            'expected_outputs' => 'nullable|string',
            'implementation_notes' => 'nullable|string',
        ]);
    }

    private function hydrateFromCommitment(array &$data): void
    {
        if (empty($data['budget_commitment_id'])) {
            return;
        }

        $commitment = BudgetCommitment::whereKey($data['budget_commitment_id'])->first();
        if (! $commitment) {
            return;
        }

        $data['program_funding_id'] = $data['program_funding_id'] ?: $commitment->program_funding_id;
        $data['fiscal_year'] = $data['fiscal_year'] ?: (string) $commitment->commitment_year;
        $data['planned_amount'] = (float) ($data['planned_amount'] ?: $commitment->commitment_amount);
        $data['governance_node_id'] = $commitment->governance_node_id;
    }

    private function nextCode(): string
    {
        return 'AWP-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5));
    }

    private function nextWorkPlanCode(int $year): string
    {
        do {
            $code = 'AWP-' . $year . '-' . Str::upper(Str::random(6));
        } while (ApprovedWorkPlan::where('awp_code', $code)->exists());

        return $code;
    }

    private function nextPurchaseRequestReference(string $prefix = 'PR'): string
    {
        do {
            $reference = $prefix . '-' . now()->year . '-' . Str::upper(Str::random(5));
        } while (PurchaseRequest::where('reference_no', $reference)->exists());

        return $reference;
    }

    private function programWorkPlanFundings(Program $program)
    {
        $approved = $program->approvedFundings ?? collect();

        return ($approved->isNotEmpty() ? $approved : ($program->fundings ?? collect()))
            ->filter()
            ->sortByDesc(fn ($funding) => $funding->status === 'approved')
            ->values();
    }

    private function programWorkPlanYears(Program $program)
    {
        $years = collect();

        if ($program->start_year && $program->end_year && (int) $program->end_year >= (int) $program->start_year) {
            $years = $years->merge(range((int) $program->start_year, (int) $program->end_year));
        }

        foreach ($program->projects as $project) {
            if ($project->start_year && $project->end_year && (int) $project->end_year >= (int) $project->start_year) {
                $years = $years->merge(range((int) $project->start_year, (int) $project->end_year));
            }

            $years = $years->merge(($project->allocations ?? collect())->pluck('year'));
            $years = $years->merge(($project->allocations ?? collect())->pluck('actual_year'));

            foreach ($project->activities as $activity) {
                $years = $years->merge(($activity->allocations ?? collect())->pluck('year'));

                foreach ($activity->subActivities as $subActivity) {
                    $years = $years->merge(($subActivity->allocations ?? collect())->pluck('year'));
                }
            }
        }

        return $years
            ->filter(fn ($year) => (int) $year > 0)
            ->map(fn ($year) => (int) $year)
            ->unique()
            ->sort()
            ->values()
            ->whenEmpty(fn ($collection) => $collection->push(now()->year));
    }

    private function defaultWorkPlanFolderName(Program $program): string
    {
        $name = trim((string) ($program->program_id ?: $program->name));

        return 'Work Plan for ' . ($name ?: 'Program');
    }

    private function folderOptionsForProgramYear(array $fundingIds, int $year)
    {
        if (empty($fundingIds)) {
            return collect();
        }

        return PurchaseRequest::query()
            ->whereIn('program_funding_id', $fundingIds)
            ->where('start_year', $year)
            ->whereNotNull('work_plan_source')
            ->distinct()
            ->orderBy('work_plan_source')
            ->pluck('work_plan_source')
            ->filter()
            ->values();
    }

    private function buildAllocationWorkPlanSheet(Program $program, array $fundingIds, int $year): array
    {
        $requests = empty($fundingIds)
            ? collect()
            : PurchaseRequest::with([
                'items.resource',
                'items.resourceCategory',
                'items.awpReview',
                'commitments.approvedWorkPlans',
            ])
                ->whereIn('program_funding_id', $fundingIds)
                ->where('allocation_level', 'sub_activity')
                ->where('start_year', $year)
                ->get();

        $requestsBySubActivity = $requests->groupBy('allocation_id');
        $commitmentsBySubActivity = empty($fundingIds)
            ? collect()
            : BudgetCommitment::query()
                ->where('allocation_level', 'sub_activity')
                ->where('commitment_year', $year)
                ->where('status', '!=', BudgetCommitment::STATUS_CANCELLED)
                ->get()
                ->groupBy('allocation_id');

        $totals = [
            'allocation' => 0.0,
            'committed' => 0.0,
            'planned' => 0.0,
            'available' => 0.0,
            'items' => 0,
            'sub_activities' => 0,
        ];

        $projects = $program->projects
            ->sortBy(fn ($project) => $this->sortKeyForProject($project))
            ->map(function ($project) use ($year, $requestsBySubActivity, $commitmentsBySubActivity, &$totals) {
                $projectTotals = [
                    'allocation' => $this->allocationAmountForYear($project->allocations ?? collect(), $year),
                    'committed' => 0.0,
                    'planned' => 0.0,
                    'available' => 0.0,
                    'items' => 0,
                ];

                $activities = $project->activities
                    ->sortBy(fn ($activity) => Str::lower((string) $activity->name))
                    ->map(function ($activity) use ($year, $requestsBySubActivity, $commitmentsBySubActivity, &$projectTotals, &$totals) {
                        $activityTotals = [
                            'allocation' => $this->allocationAmountForYear($activity->allocations ?? collect(), $year),
                            'committed' => 0.0,
                            'planned' => 0.0,
                            'available' => 0.0,
                            'items' => 0,
                        ];

                        $subActivities = $activity->subActivities
                            ->sortBy(fn ($subActivity) => Str::lower((string) $subActivity->name))
                            ->map(function ($subActivity) use ($year, $requestsBySubActivity, $commitmentsBySubActivity, &$activityTotals, &$projectTotals, &$totals) {
                                $allocation = $this->subActivityAllocationAmountForYear($subActivity, $year);
                                $commitments = collect($commitmentsBySubActivity[(string) $subActivity->id] ?? []);
                                $committed = round((float) $commitments->sum('commitment_amount'), 2);
                                $requests = collect($requestsBySubActivity[(string) $subActivity->id] ?? []);
                                $existingItems = $requests
                                    ->flatMap(function (PurchaseRequest $request) {
                                        return $request->items->map(function (PurchaseRequestItem $item) use ($request) {
                                            $status = $item->awpReview?->status ?: 'pending';
                                            $locked = $status === 'approved';

                                            return [
                                                'item' => $item,
                                                'request' => $request,
                                                'review' => $item->awpReview,
                                                'label' => $item->resource?->name ?: $item->milestone ?: 'Work plan item',
                                                'amount' => (float) ($item->estimated_amount ?: $item->amount),
                                                'status' => $status,
                                                'status_label' => $this->formatWorldBankStatus($status),
                                                'locked' => $locked,
                                            ];
                                        });
                                    })
                                    ->values();
                                $planned = round((float) $existingItems->sum('amount'), 2);
                                $available = max(0, round($allocation - $committed, 2));

                                foreach (['committed' => $committed, 'planned' => $planned, 'available' => $available] as $key => $value) {
                                    $activityTotals[$key] += $value;
                                    $projectTotals[$key] += $value;
                                    $totals[$key] += $value;
                                }

                                $activityTotals['items'] += $existingItems->count();
                                $projectTotals['items'] += $existingItems->count();
                                $totals['items'] += $existingItems->count();
                                $totals['sub_activities']++;
                                $totals['allocation'] += $allocation;

                                return [
                                    'subActivity' => $subActivity,
                                    'allocation' => $allocation,
                                    'committed' => $committed,
                                    'planned' => $planned,
                                    'available' => $available,
                                    'suggested' => $available,
                                    'existing_items' => $existingItems,
                                ];
                            })
                            ->values();

                        $activityTotals['available'] = round($activityTotals['available'], 2);

                        return [
                            'activity' => $activity,
                            'subActivities' => $subActivities,
                            'totals' => $activityTotals,
                        ];
                    })
                    ->values();

                return [
                    'project' => $project,
                    'activities' => $activities,
                    'totals' => $projectTotals,
                ];
            })
            ->values();

        foreach (['allocation', 'committed', 'planned', 'available'] as $key) {
            $totals[$key] = round((float) $totals[$key], 2);
        }

        return [
            'projects' => $projects,
            'totals' => $totals,
        ];
    }

    private function allocationAmountForYear($allocations, int $year): float
    {
        return round((float) collect($allocations)
            ->filter(function ($allocation) use ($year) {
                return (int) ($allocation->year ?? 0) === $year
                    || (int) ($allocation->actual_year ?? 0) === $year;
            })
            ->sum('amount'), 2);
    }

    private function subActivityAllocationAmountForYear($subActivity, int $year): float
    {
        return $this->allocationAmountForYear($subActivity->allocations ?? collect(), $year);
    }

    private function committedAmountForSubActivityYear(string $subActivityId, int $year, array $fundingIds = [], array $excludeCommitmentIds = []): float
    {
        $fundingIds = array_values(array_filter($fundingIds));

        return round((float) BudgetCommitment::query()
            ->where('allocation_level', 'sub_activity')
            ->where('allocation_id', $subActivityId)
            ->where('commitment_year', $year)
            ->where('status', '!=', BudgetCommitment::STATUS_CANCELLED)
            ->when(! empty($fundingIds), fn ($query) => $query->whereIn('program_funding_id', $fundingIds))
            ->when(! empty($excludeCommitmentIds), fn ($query) => $query->whereNotIn('id', $excludeCommitmentIds))
            ->sum('commitment_amount'), 2);
    }

    private function assertWorkPlanAmountWithinBudget($subActivity, int $year, float $amount, array $fundingIds = [], array $excludeCommitmentIds = []): void
    {
        $allocation = $this->subActivityAllocationAmountForYear($subActivity, $year);
        $committedElsewhere = $this->committedAmountForSubActivityYear(
            (string) $subActivity->id,
            $year,
            $fundingIds,
            $excludeCommitmentIds
        );
        $available = max(0, round($allocation - $committedElsewhere, 2));

        if ($allocation <= 0) {
            throw ValidationException::withMessages([
                'estimated_amount' => "No allocation exists for {$subActivity->name} in {$year}.",
            ]);
        }

        if ($amount > $available + 0.01) {
            throw ValidationException::withMessages([
                'estimated_amount' => "The work plan amount for {$subActivity->name} cannot exceed the available {$year} allocation of USD " . number_format($available, 2) . '.',
            ]);
        }
    }

    private function workPlanBudgetCode($project, $activity, $subActivity, int $year): string
    {
        $projectCode = $project?->project_id ?: 'PROJECT';
        $subCode = Str::upper(Str::substr(str_replace('-', '', (string) $subActivity->id), 0, 6));

        return Str::limit($projectCode . '-WP-' . $year . '-' . $subCode, 120, '');
    }

    private function resolveFilters(Request $request): array
    {
        $mode = $request->input('filter_mode', 'multi_year');
        $year = (int) $request->input('year', now()->year);
        $startYear = (int) $request->input('start_year', $year);
        $endYear = (int) $request->input('end_year', $startYear);
        $startDate = null;
        $endDate = null;

        if ($mode === 'yearly') {
            $startYear = $endYear = $year;
            $startDate = Carbon::create($year, 1, 1)->startOfDay();
            $endDate = Carbon::create($year, 12, 31)->endOfDay();
        } elseif ($mode === 'quarterly') {
            $quarter = max(1, min(4, (int) $request->input('quarter', 1)));
            $startMonth = (($quarter - 1) * 3) + 1;
            $startYear = $endYear = $year;
            $startDate = Carbon::create($year, $startMonth, 1)->startOfDay();
            $endDate = (clone $startDate)->addMonths(2)->endOfMonth()->endOfDay();
        } elseif ($mode === 'semiannual') {
            $half = (int) $request->input('half', 1) === 2 ? 2 : 1;
            $startYear = $endYear = $year;
            $startDate = Carbon::create($year, $half === 1 ? 1 : 7, 1)->startOfDay();
            $endDate = Carbon::create($year, $half === 1 ? 6 : 12, 1)->endOfMonth()->endOfDay();
        } elseif ($mode === 'range') {
            $startDate = $request->filled('start_date') ? Carbon::parse($request->input('start_date'))->startOfDay() : null;
            $endDate = $request->filled('end_date') ? Carbon::parse($request->input('end_date'))->endOfDay() : null;
            $startYear = $startDate?->year ?? $startYear;
            $endYear = $endDate?->year ?? $startYear;
        } else {
            $mode = 'multi_year';
            if ($endYear < $startYear) {
                $endYear = $startYear;
            }
            $startDate = Carbon::create($startYear, 1, 1)->startOfDay();
            $endDate = Carbon::create($endYear, 12, 31)->endOfDay();
        }

        return [
            'mode' => $mode,
            'start_year' => $startYear,
            'end_year' => $endYear,
            'year_range' => range($startYear, $endYear),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'label' => $startDate && $endDate
                ? $startDate->format('M d, Y') . ' - ' . $endDate->format('M d, Y')
                : 'All available work plan items',
        ];
    }

    private function buildWorkPlanHierarchy(Program $program, array $fundingIds, array $filters): array
    {
        $requests = empty($fundingIds)
            ? collect()
            : PurchaseRequest::with([
                'items.resourceCategory',
                'items.resource',
                'items.awpReview.reviewer',
                'items.awpReview.documentUploader',
                'programFunding.funder',
                'commitments',
            ])
                ->whereIn('program_funding_id', $fundingIds)
                ->where('allocation_level', 'sub_activity')
                ->get()
                ->filter(function (PurchaseRequest $request) use ($filters) {
                    $date = $this->resolveRequestDate($request);
                    if (!$filters['start_date'] || !$filters['end_date']) {
                        return true;
                    }

                    return $date->between($filters['start_date'], $filters['end_date']);
                });

        $requestsBySubActivity = $requests->groupBy('allocation_id');

        return $program->projects->sortBy(fn ($project) => $this->sortKeyForProject($project))->map(function ($project) use ($requestsBySubActivity) {
            $projectTotal = 0;
            $projectCounts = $this->emptyStatusCounts();

            $activities = $project->activities
                ->sortBy(fn ($activity) => $this->sortKeyForActivity($activity, $requestsBySubActivity))
                ->map(function ($activity) use ($requestsBySubActivity, &$projectTotal, &$projectCounts) {
                $activityTotal = 0;
                $activityCounts = $this->emptyStatusCounts();

                $subActivities = $activity->subActivities
                    ->sortBy(fn ($subActivity) => $this->sortKeyForSubActivity($subActivity, $requestsBySubActivity))
                    ->map(function ($subActivity) use ($requestsBySubActivity, &$activityTotal, &$activityCounts, &$projectTotal, &$projectCounts) {
                    $items = collect($requestsBySubActivity[$subActivity->id] ?? [])
                        ->flatMap(function (PurchaseRequest $request) {
                            $requestCommitmentAmount = $this->committedAmountForRequest($request);
                            $requestItemAmount = (float) $request->items->sum('amount');

                            return $request->items
                                ->sortBy(fn (PurchaseRequestItem $item) => $this->sortKeyForItem($item))
                                ->map(function (PurchaseRequestItem $item) use ($request, $requestCommitmentAmount, $requestItemAmount) {
                                $review = $item->awpReview;
                                $status = $review?->status ?? 'pending';
                                $commitmentAmount = $this->commitmentAmountForItem(
                                    $item,
                                    $requestCommitmentAmount,
                                    $requestItemAmount
                                );
                                $months = $this->normalizeWorkPlanMonths($item->work_plan_months ?? []);
                                $paymentBasis = $this->normalizePaymentBasis($item->work_plan_payment_basis, [
                                    'activity' => $item->resource?->name ?? $item->milestone ?? '',
                                    'object_type' => $item->object_type,
                                    'observations' => $item->observations,
                                    'intermediate_indicator' => $item->intermediate_indicator,
                                ], $months);
                                $personMonths = $item->work_plan_person_months ?: ($paymentBasis === 'monthly' ? count($months) : null);
                                $monthlyAmount = $item->work_plan_monthly_amount
                                    ?: (($paymentBasis === 'monthly' && $personMonths > 0) ? round($commitmentAmount / $personMonths, 2) : null);

                                return [
                                    'item' => $item,
                                    'purchaseRequest' => $request,
                                    'label' => $item->resource?->name
                                        ?? $item->resourceCategory?->name
                                        ?? $item->milestone
                                        ?? $request->description
                                        ?? 'Work plan item',
                                    'amount' => $commitmentAmount,
                                    'status' => $status,
                                    'status_label' => $this->formatWorldBankStatus($status),
                                    'review' => $review,
                                    'is_placeholder' => false,
                                    'month_keys' => $months,
                                    'month_labels' => $this->workPlanMonthLabelsFor($months),
                                    'month_text' => $this->workPlanMonthText($months),
                                    'payment_basis' => $paymentBasis,
                                    'payment_basis_label' => $this->workPlanPaymentBasisLabel($paymentBasis),
                                    'person_months' => $personMonths,
                                    'monthly_amount' => $monthlyAmount,
                                ];
                            });
                        })->values();

                    if ($items->isEmpty()) {
                        $items = collect([
                            [
                                'item' => null,
                                'purchaseRequest' => null,
                                'label' => 'No work plan item recorded',
                                'amount' => 0.0,
                                'status' => 'pending',
                                'status_label' => 'Not approved by World Bank',
                                'review' => null,
                                'is_placeholder' => true,
                                'month_keys' => [],
                                'month_labels' => [],
                                'month_text' => null,
                                'payment_basis' => 'scheduled',
                                'payment_basis_label' => 'Scheduled activity',
                                'person_months' => null,
                                'monthly_amount' => null,
                            ],
                        ]);
                    }

                    $subTotal = (float) $items->sum('amount');
                    $subCounts = $this->countItemStatuses($items);

                    $activityTotal += $subTotal;
                    $projectTotal += $subTotal;
                    $activityCounts = $this->mergeStatusCounts($activityCounts, $subCounts);
                    $projectCounts = $this->mergeStatusCounts($projectCounts, $subCounts);

                    return [
                        'subActivity' => $subActivity,
                        'allocation_years' => $this->subActivityAllocationYears($subActivity),
                        'items' => $items,
                        'total' => $subTotal,
                        'counts' => $subCounts,
                    ];
                })->values();

                return [
                    'activity' => $activity,
                    'subActivities' => $subActivities,
                    'total' => $activityTotal,
                    'counts' => $activityCounts,
                ];
            })->values();

            return [
                'project' => $project,
                'activities' => $activities,
                'total' => $projectTotal,
                'counts' => $projectCounts,
            ];
        })->values()->all();
    }

    private function summarizeWorkPlan(array $report): array
    {
        $counts = $this->emptyStatusCounts();
        $amount = 0;

        foreach ($report as $projectRow) {
            $amount += $projectRow['total'];
            $counts = $this->mergeStatusCounts($counts, $projectRow['counts']);
        }

        $totalItems = array_sum($counts);

        return [
            'total_items' => $totalItems,
            'approved' => $counts['approved'],
            'pending' => $counts['pending'],
            'rejected' => $counts['rejected'],
            'needs_revision' => $counts['needs_revision'],
            'approval_rate' => $totalItems > 0 ? round(($counts['approved'] / $totalItems) * 100, 2) : 0,
            'amount' => $amount,
        ];
    }

    private function workPlanMonthLabels(): array
    {
        return [
            'jan' => 'Jan',
            'feb' => 'Feb',
            'mar' => 'Mar',
            'apr' => 'Apr',
            'may' => 'May',
            'jun' => 'Jun',
            'jul' => 'Jul',
            'aug' => 'Aug',
            'sep' => 'Sep',
            'oct' => 'Oct',
            'nov' => 'Nov',
            'dec' => 'Dec',
        ];
    }

    private function normalizeWorkPlanMonths($months): array
    {
        $labels = $this->workPlanMonthLabels();
        $months = is_array($months) ? $months : [];

        return collect(array_keys($labels))
            ->filter(fn (string $month) => in_array($month, $months, true))
            ->values()
            ->all();
    }

    private function workPlanMonthLabelsFor(array $months): array
    {
        $labels = $this->workPlanMonthLabels();

        return collect($this->normalizeWorkPlanMonths($months))
            ->map(fn (string $month) => $labels[$month])
            ->all();
    }

    private function workPlanMonthText(array $months): ?string
    {
        $labels = $this->workPlanMonthLabelsFor($months);

        return empty($labels) ? null : implode(', ', $labels);
    }

    private function normalizePaymentBasis(?string $paymentBasis, array $data, array $months): string
    {
        if (in_array($paymentBasis, ['one_off', 'scheduled', 'monthly'], true)) {
            return $paymentBasis;
        }

        if ($this->looksLikeMonthlyPersonnelLine($data)) {
            return 'monthly';
        }

        return count($months) > 1 ? 'scheduled' : 'one_off';
    }

    private function looksLikeMonthlyPersonnelLine(array $data): bool
    {
        $objectType = $this->normalizeWorkPlanText((string) ($data['object_type'] ?? ''));
        if ($objectType !== '' && $objectType !== 'consulting') {
            return false;
        }

        $text = $this->normalizeWorkPlanText(implode(' ', [
            $data['activity'] ?? '',
            $data['observations'] ?? '',
            $data['intermediate_indicator'] ?? '',
        ]));

        if (str_contains($text, 'critical position')) {
            return true;
        }

        foreach ([
            'technical project coordinator',
            'procurement specialist',
            'project admin assistant',
            'technical project advisor',
            'communication specialist',
            'project m e officer',
            'financial management specialist',
            'grm consultant',
        ] as $phrase) {
            if (str_contains($text, $phrase)) {
                return true;
            }
        }

        return false;
    }

    private function workPlanPaymentBasisLabel(string $paymentBasis): string
    {
        return match ($paymentBasis) {
            'monthly' => 'Monthly / person-month',
            'scheduled' => 'Scheduled activity',
            default => 'One-off / milestone',
        };
    }

    private function subActivityAllocationYears($subActivity)
    {
        return ($subActivity->allocations ?? collect())
            ->sortBy('year')
            ->map(fn ($allocation) => [
                'year' => (int) $allocation->year,
                'amount' => (float) $allocation->amount,
            ])
            ->values();
    }

    private function resourceCategoryForObjectType(?string $objectType, ?string $createdBy, ?string $preferredGovernanceNodeId = null): ResourceCategory
    {
        $normalized = $this->normalizeWorkPlanText((string) $objectType);
        $name = match (true) {
            str_contains($normalized, 'workshop') || str_contains($normalized, 'w shop') => 'Workshops & Events',
            str_contains($normalized, 'goods') => 'Goods',
            str_contains($normalized, 'application') || str_contains($normalized, 'pass through') => 'Grants / Transfers',
            str_contains($normalized, 'staff') || str_contains($normalized, 'translation') || str_contains($normalized, 'communication') || str_contains($normalized, 'ioc') => 'Implementation/Operational Costs (IOC)',
            default => 'Consulting Services',
        };

        $currentUser = request()->user();
        if ($this->userHasAssignedPortfolioScope($currentUser)) {
            $assignedNodeIds = $this->assignedPortfolioNodeIds($currentUser);
            $nodeId = $preferredGovernanceNodeId && in_array((string) $preferredGovernanceNodeId, $assignedNodeIds, true)
                ? (string) $preferredGovernanceNodeId
                : ($assignedNodeIds[0] ?? null);

            if (! $nodeId) {
                throw ValidationException::withMessages([
                    'object_type' => 'Your portfolio is missing a governance mapping, so work plan resources cannot be created yet.',
                ]);
            }

            return ResourceCategory::firstOrCreate(
                [
                    'name' => $name,
                    'governance_node_id' => $nodeId,
                ],
                [
                    'description' => $name . ' for ATTP work plan items',
                    'status' => 'active',
                    'created_by' => $createdBy,
                ]
            );
        }

        return ResourceCategory::firstOrCreate(
            ['name' => $name],
            [
                'description' => $name . ' for ATTP work plan items',
                'status' => 'active',
                'created_by' => $createdBy,
            ]
        );
    }

    private function syncWorkPlanResource(PurchaseRequestItem $item, ResourceCategory $category, array $data, ?string $createdBy, ?string $governanceNodeId = null): Resource
    {
        $item->loadMissing('purchaseRequest');
        $resource = $item->resource ?: new Resource();
        $resource->fill([
            'resource_category_id' => $category->id,
            'governance_node_id' => $resource->governance_node_id ?: $governanceNodeId ?: $item->purchaseRequest?->governance_node_id,
            'name' => $data['activity'],
            'reference_code' => $data['budget_code'] ?: $resource->reference_code ?: 'AWP-' . now()->year . '-' . Str::upper(Str::random(5)),
            'description' => $data['intermediate_indicator'] ?: $data['result_indicator'] ?: null,
            'status' => 'active',
            'is_human_resource' => $this->normalizeWorkPlanText((string) ($data['object_type'] ?? '')) === 'consulting',
            'created_by' => $resource->created_by ?: $createdBy,
        ]);
        $resource->save();

        return $resource;
    }

    private function normalizeWorkPlanText(string $value): string
    {
        $value = Str::ascii(Str::lower($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    private function sortKeyForProject($project): string
    {
        if (preg_match('/component\s*#?\s*(\d+)/i', (string) $project->name, $matches)) {
            return sprintf('%03d-%s', (int) $matches[1], Str::lower($project->name));
        }

        return '999-' . Str::lower((string) $project->name);
    }

    private function sortKeyForActivity($activity, $requestsBySubActivity): string
    {
        $sortOrder = $activity->subActivities
            ->map(fn ($subActivity) => $this->minimumWorkPlanSortOrder($subActivity, $requestsBySubActivity))
            ->filter()
            ->min();

        $subComponentOrder = 999;
        if (preg_match('/\(([a-z])\)/i', (string) $activity->name, $matches)) {
            $subComponentOrder = ord(Str::lower($matches[1])) - 96;
        }

        return sprintf(
            '%010d-%03d-%s',
            $sortOrder ?: 999999999,
            $subComponentOrder,
            Str::lower((string) $activity->name)
        );
    }

    private function sortKeyForSubActivity($subActivity, $requestsBySubActivity): string
    {
        return sprintf(
            '%010d-%s',
            $this->minimumWorkPlanSortOrder($subActivity, $requestsBySubActivity) ?: 999999999,
            Str::lower((string) $subActivity->name)
        );
    }

    private function sortKeyForItem(PurchaseRequestItem $item): string
    {
        return sprintf(
            '%010d-%s',
            $item->work_plan_sort_order ?: 999999999,
            Str::lower((string) ($item->resource?->name ?? $item->milestone ?? $item->id))
        );
    }

    private function minimumWorkPlanSortOrder($subActivity, $requestsBySubActivity): ?int
    {
        return collect($requestsBySubActivity[$subActivity->id] ?? [])
            ->flatMap(fn (PurchaseRequest $request) => $request->items)
            ->pluck('work_plan_sort_order')
            ->filter()
            ->min();
    }

    private function resolveRequestDate(PurchaseRequest $request): Carbon
    {
        return $request->commitment_date
            ?? $request->delivery_date
            ?? $request->created_at
            ?? now();
    }

    private function emptyStatusCounts(): array
    {
        return [
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'needs_revision' => 0,
        ];
    }

    private function committedAmountForRequest(PurchaseRequest $request): float
    {
        $eligibleStatuses = [
            BudgetCommitment::STATUS_SUBMITTED,
            BudgetCommitment::STATUS_APPROVED,
        ];

        $commitments = $request->commitments ?? collect();
        $committed = (float) $commitments
            ->whereIn('status', $eligibleStatuses)
            ->sum('commitment_amount');

        return $committed > 0 ? $committed : (float) $commitments->sum('commitment_amount');
    }

    private function commitmentAmountForItem(PurchaseRequestItem $item, float $requestCommitmentAmount, float $requestItemAmount): float
    {
        if ($requestCommitmentAmount <= 0) {
            return 0.0;
        }

        if ($requestItemAmount <= 0) {
            return round($requestCommitmentAmount, 2);
        }

        return round($requestCommitmentAmount * ((float) $item->amount / $requestItemAmount), 2);
    }

    private function countItemStatuses($items): array
    {
        $counts = $this->emptyStatusCounts();
        foreach ($items as $item) {
            $status = $item['status'] ?? 'pending';
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }

        return $counts;
    }

    private function mergeStatusCounts(array $left, array $right): array
    {
        foreach ($right as $status => $count) {
            $left[$status] = ($left[$status] ?? 0) + $count;
        }

        return $left;
    }

    private function formatWorldBankStatus(string $status): string
    {
        return match ($status) {
            'approved' => 'Approved by World Bank',
            'rejected' => 'Rejected by World Bank',
            'needs_revision' => 'Needs revision by World Bank',
            default => 'Not approved by World Bank',
        };
    }

    private function canReviewAwpItems(Request $request): bool
    {
        $user = $request->user();

        return (bool) ($user && ($user->hasPermission('finance.awp.approve') || $user->hasPermission('partner.workplan.review')));
    }

    private function assertProgramInAwpScope(Program $program, $user): void
    {
        if (! $this->userHasAssignedPortfolioScope($user)) {
            return;
        }

        abort_unless($this->programIsInAssignedPortfolio($program, $user), 403, 'This program is not assigned to your portfolio.');
    }

    private function assertSubActivityInAwpScope(SubActivity $subActivity, $user): void
    {
        if (! $this->userHasAssignedPortfolioScope($user)) {
            return;
        }

        abort_unless($this->subActivityIsInAssignedPortfolio($subActivity, $user), 403, 'This sub-activity is not assigned to your portfolio.');
    }

    private function assertAwpItemInScope(PurchaseRequestItem $item, $user): void
    {
        if (! $this->userHasAssignedPortfolioScope($user)) {
            return;
        }

        $item->loadMissing('purchaseRequest');

        abort_unless(
            $item->purchaseRequest && $this->purchaseRequestIsInAssignedPortfolio($item->purchaseRequest, $user),
            403,
            'This work plan item is not assigned to your portfolio.'
        );
    }

    private function assertApprovedWorkPlanInScope(ApprovedWorkPlan $awp, $user): void
    {
        if (! $this->userHasAssignedPortfolioScope($user)) {
            return;
        }

        abort_unless(
            $this->approvedWorkPlanIsInAssignedPortfolio($awp, $user),
            403,
            'This approved work plan is not assigned to your portfolio.'
        );
    }

    private function assertAwpPayloadInScope(array $data, $user): void
    {
        if (! $this->userHasAssignedPortfolioScope($user)) {
            return;
        }

        if (! empty($data['budget_commitment_id'])) {
            $commitment = BudgetCommitment::find($data['budget_commitment_id']);
            abort_unless(
                $commitment && $this->commitmentIsInAssignedPortfolio($commitment, $user),
                403,
                'This commitment is not assigned to your portfolio.'
            );

            return;
        }

        if (! empty($data['program_funding_id'])) {
            $funding = ProgramFunding::find($data['program_funding_id']);
            abort_unless(
                $funding && $this->fundingIsInAssignedPortfolio($funding, $user),
                403,
                'This funding source is not assigned to your portfolio.'
            );

            return;
        }

        abort_if(true, 403, 'A portfolio-linked commitment or funding source is required.');
    }

    private function workPlanResourceGovernanceNodeId($subActivity, ?ProgramFunding $funding, $user): ?string
    {
        if (! $this->userHasAssignedPortfolioScope($user)) {
            return $subActivity->governance_node_id ?: $funding?->governance_node_id;
        }

        $subActivity->loadMissing('activity.project.program.sector');
        $assignedNodeIds = $this->assignedPortfolioNodeIds($user);
        $portfolioNodeId = $subActivity->activity?->project?->program?->sector?->governance_node_id;

        if ($portfolioNodeId && in_array((string) $portfolioNodeId, $assignedNodeIds, true)) {
            return (string) $portfolioNodeId;
        }

        return $assignedNodeIds[0] ?? null;
    }

    private function partnerFunder(Request $request): Funder
    {
        $funder = $request->user()?->partnerFunder() ?? Auth::user()?->partnerFunder();

        abort_unless($funder && $funder->hasPortalAccess(), 403, 'No funding partner account is linked to this user.');

        return $funder;
    }

    private function assertPartnerCanAccessItem(Request $request, PurchaseRequestItem $item): void
    {
        $funder = $this->partnerFunder($request);
        $item->loadMissing('purchaseRequest.programFunding');
        $itemFunderId = $item->purchaseRequest?->programFunding?->funder_id;

        abort_unless((string) $itemFunderId === (string) $funder->id, 403, 'This work plan item is not funded by your partner account.');
    }

    private function auditAction(string $action, string $message, array $payload = []): void
    {
        try {
            $request = request();

            SystemAuditLog::create([
                'user_id' => $request->user()?->id,
                'module' => 'finance_work_plan_registry',
                'action' => $action,
                'action_message' => $message,
                'description' => $message,
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'route_name' => $request->route()?->getName(),
                'ip_address' => $request->ip(),
                'country' => null,
                'user_agent' => $request->userAgent() ? substr((string) $request->userAgent(), 0, 1000) : null,
                'status_code' => 200,
                'payload' => $payload,
            ]);
        } catch (Throwable) {
            // Audit logging must not block the work plan workflow.
        }
    }
}
