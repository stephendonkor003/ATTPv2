<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\BudgetCommitment;
use App\Models\ProcurementGeographic;
use App\Models\ProcurementMethodPlanned;
use App\Models\ProcurementPlan;
use App\Models\ProcurementProgramPlan;
use App\Models\ProcurementStage;
use App\Models\ProcurementStatus;
use App\Models\ProcurementStepApproval;
use App\Models\ProcurementStepStage;
use App\Models\SubActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
class ProcurementPlanController extends Controller
{
    /**
     * Display a listing of procurement plans.
     */
    public function index(Request $request)
    {
        $query = ProcurementPlan::with([
            'activity',
            'subActivity',
            'methodPlanned',
            'geographic',
            'stage',
            'status',
            'stepStage',
            'stepApproval',
            'creator'
        ]);

        // Filter by user unless they have 'procurement.view_all' permission
        if (!auth()->user()->can('procurement.view_all')) {
            $query->where('created_by', auth()->id());
        }

        // Filter by launch status
        if ($request->filled('launched')) {
            $query->where('is_launched', $request->launched === 'yes');
        }

        // Filter by fiscal year
        if ($request->filled('fiscal_year')) {
            $query->where('fiscal_year', $request->fiscal_year);
        }

        // Filter by stage
        if ($request->filled('stage_id')) {
            $query->where('stage_id', $request->stage_id);
        }

        $plans = $query->orderBy('created_at', 'desc')->get();

        // Get filter options
        $stages = ProcurementStage::active()->ordered()->get();
        $fiscalYears = ProcurementPlan::whereNotNull('fiscal_year')
            ->distinct()
            ->orderBy('fiscal_year', 'desc')
            ->pluck('fiscal_year');

        return view('procurement.plans.index', compact('plans', 'stages', 'fiscalYears'));
    }

    public function sheet(Request $request)
    {
        $query = ProcurementProgramPlan::where('is_active', true);

        // Filter by user unless they have 'procurement.view_all' permission
        if (!auth()->user()->can('procurement.view_all')) {
            $query->where('created_by', auth()->id());
        }

        $programPlans = $query->withCount('procurements')
            ->orderBy('name')
            ->get();

        return view('procurement.plans.sheet', compact('programPlans'));
    }

    /**
     * Show the form for creating a new procurement plan.
     */
    public function create()
    {
        $activities = Activity::with('project')->orderBy('name')->get();
        $methods = ProcurementMethodPlanned::active()->orderBy('method_name')->get();
        $geographics = ProcurementGeographic::active()->orderBy('name')->get();
        $stages = ProcurementStage::active()->ordered()->get();
        $statuses = ProcurementStatus::active()->orderBy('sort_order')->get();
        $stepStages = ProcurementStepStage::active()->ordered()->get();
        $stepApprovals = ProcurementStepApproval::with('governanceNode')
            ->where('is_active', true)
            ->orderBy('approval_order')
            ->get();
        $programPlans = ProcurementProgramPlan::where('is_active', true)
            ->orderBy('name')
            ->get();

        // Generate a default procurement code
        $defaultCode = ProcurementPlan::generateCode();

        return view('procurement.plans.create', compact(
            'activities',
            'methods',
            'geographics',
            'stages',
            'statuses',
            'stepStages',
            'stepApprovals',
            'defaultCode',
            'programPlans'
        ));
    }

    /**
     * Store a newly created procurement plan.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'procurement_code' => 'required|string|max:50|unique:myb_procurement_plans,procurement_code',
            'is_code_auto_generated' => 'boolean',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'activity_id' => 'nullable|exists:myb_activities,id',
            'sub_activity_id' => 'nullable|exists:myb_sub_activities,id',
            'method_planned_id' => 'nullable|exists:myb_procurement_method_planned,id',
            'program_plan_id' => 'required|exists:myb_procurement_program_plans,id',
            'geographic_id' => 'nullable|exists:myb_procurement_geographics,id',
            'stage_id' => 'nullable|exists:myb_procurement_stages,id',
            'status_id' => 'nullable|exists:myb_procurement_statuses,id',
            'step_stage_id' => 'nullable|exists:myb_procurement_step_stages,id',
            'step_approval_id' => 'nullable|exists:myb_procurement_step_approvals,id',
            'is_launched' => 'boolean',
            'estimated_start_date' => 'nullable|date',
            'estimated_end_date' => 'nullable|date|after_or_equal:estimated_start_date',
            'estimated_budget' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'remarks' => 'nullable|string',
            'fiscal_year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $this->ensureSubActivityIsApprovedCommittedInScope($request);

        $validated['is_code_auto_generated'] = $request->has('is_code_auto_generated');
        $validated['is_launched'] = $request->has('is_launched');
        $validated['created_by'] = auth()->id();

        // If launched, set launched_at
        if ($validated['is_launched']) {
            $validated['launched_at'] = now();
        }

        // Auto-calculate end date if method is selected and start date provided
        if ($request->filled('method_planned_id') && $request->filled('estimated_start_date') && !$request->filled('estimated_end_date')) {
            $method = ProcurementMethodPlanned::find($request->method_planned_id);
            if ($method) {
                $validated['estimated_end_date'] = \Carbon\Carbon::parse($request->estimated_start_date)
                    ->addDays($method->method_target_days)
                    ->format('Y-m-d');
            }
        }

        ProcurementPlan::create($validated);

        return redirect()->route('procurement.plans.index')
            ->with('success', 'Procurement plan created successfully.');
    }

    /**
     * Display the specified procurement plan.
     */
    public function show(ProcurementPlan $plan)
    {
        $plan->load([
            'activity.project',
            'subActivity',
            'methodPlanned',
            'geographic',
            'stage',
            'status',
            'stepStage',
            'stepApproval.governanceNode',
            'creator',
            'updater'
        ]);

        return view('procurement.plans.show', compact('plan'));
    }

    /**
     * Show the form for editing the specified procurement plan.
     */
    public function edit(ProcurementPlan $plan)
    {
        $activities = Activity::with('project')->orderBy('name')->get();
        $subActivities = $plan->activity_id
            ? SubActivity::where('activity_id', $plan->activity_id)->orderBy('name')->get()
            : collect();
        $methods = ProcurementMethodPlanned::active()->orderBy('method_name')->get();
        $geographics = ProcurementGeographic::active()->orderBy('name')->get();
        $stages = ProcurementStage::active()->ordered()->get();
        $statuses = ProcurementStatus::active()->orderBy('sort_order')->get();
        $stepStages = ProcurementStepStage::active()->ordered()->get();
        $stepApprovals = ProcurementStepApproval::with('governanceNode')
            ->where('is_active', true)
            ->orderBy('approval_order')
            ->get();
        $programPlans = ProcurementProgramPlan::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('procurement.plans.edit', compact(
            'plan',
            'activities',
            'subActivities',
            'methods',
            'geographics',
            'stages',
            'statuses',
            'stepStages',
            'stepApprovals'
            ,'programPlans'
        ));
    }

    /**
     * Update the specified procurement plan.
     */
    public function update(Request $request, ProcurementPlan $plan)
    {
        $validated = $request->validate([
            'procurement_code' => 'required|string|max:50|unique:myb_procurement_plans,procurement_code,' . $plan->id,
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'activity_id' => 'nullable|exists:myb_activities,id',
            'sub_activity_id' => 'nullable|exists:myb_sub_activities,id',
            'method_planned_id' => 'nullable|exists:myb_procurement_method_planned,id',
            'program_plan_id' => 'required|exists:myb_procurement_program_plans,id',
            'geographic_id' => 'nullable|exists:myb_procurement_geographics,id',
            'stage_id' => 'nullable|exists:myb_procurement_stages,id',
            'status_id' => 'nullable|exists:myb_procurement_statuses,id',
            'step_stage_id' => 'nullable|exists:myb_procurement_step_stages,id',
            'step_approval_id' => 'nullable|exists:myb_procurement_step_approvals,id',
            'is_launched' => 'boolean',
            'estimated_start_date' => 'nullable|date',
            'estimated_end_date' => 'nullable|date|after_or_equal:estimated_start_date',
            'estimated_budget' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'remarks' => 'nullable|string',
            'fiscal_year' => 'nullable|integer|min:2000|max:2100',
        ]);

        if (
            $request->filled('sub_activity_id')
            && (
                $request->input('sub_activity_id') !== $plan->sub_activity_id
                || $request->input('activity_id') !== $plan->activity_id
            )
        ) {
            $this->ensureSubActivityIsApprovedCommittedInScope($request);
        }

        $validated['is_launched'] = $request->has('is_launched');
        $validated['updated_by'] = auth()->id();

        // If just launched now
        if ($validated['is_launched'] && !$plan->is_launched) {
            $validated['launched_at'] = now();
        }

        // Auto-calculate end date if method changed or start date changed
        if ($request->filled('method_planned_id') && $request->filled('estimated_start_date')) {
            $method = ProcurementMethodPlanned::find($request->method_planned_id);
            if ($method && !$request->filled('estimated_end_date')) {
        $validated['estimated_end_date'] = \Carbon\Carbon::parse($request->estimated_start_date)
            ->addDays($method->method_target_days)
            ->format('Y-m-d');
            }
        }

        $plan->update($validated);

        return redirect()->route('procurement.plans.index')
            ->with('success', 'Procurement plan updated successfully.');
    }

    /**
     * Remove the specified procurement plan.
     */
    public function destroy(ProcurementPlan $plan)
    {
        $plan->delete();

        return redirect()->route('procurement.plans.index')
            ->with('success', 'Procurement plan deleted successfully.');
    }

    /**
     * Toggle launch status.
     */
    public function toggleLaunch(ProcurementPlan $plan)
    {
        $plan->is_launched = !$plan->is_launched;

        if ($plan->is_launched) {
            $plan->launched_at = now();
        } else {
            $plan->launched_at = null;
        }

        $plan->save();

        $status = $plan->is_launched ? 'launched' : 'unlaunched';

        return back()->with('success', "Procurement plan has been {$status}.");
    }

    /**
     * Generate a new procurement code via AJAX.
     */
    public function generateCode(Request $request)
    {
        $methodAbbr = $request->get('method_abbr', 'CS');
        $geoAbbr = $request->get('geo_abbr', 'CQS');

        $code = ProcurementPlan::generateCode($methodAbbr, $geoAbbr);

        return response()->json(['code' => $code]);
    }

    /**
     * Get sub-activities by activity ID via AJAX.
     */
    public function getSubActivities(Activity $activity)
    {
        $this->assertActivityInScope($activity);

        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            return response()->json([]);
        }

        $subActivities = SubActivity::query()
            ->select('myb_sub_activities.id', 'myb_sub_activities.name')
            ->join('myb_budget_commitments as commitments', function ($join) use ($scopedNodeIds) {
                $join->on('commitments.allocation_id', '=', 'myb_sub_activities.id')
                    ->where('commitments.allocation_level', '=', 'sub_activity')
                    ->where('commitments.status', '=', BudgetCommitment::STATUS_APPROVED);

                if ($scopedNodeIds !== null) {
                    $join->whereIn('commitments.governance_node_id', $scopedNodeIds)
                        ->whereNotNull('commitments.governance_node_id');
                }
            })
            ->where('myb_sub_activities.activity_id', $activity->id)
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('myb_sub_activities.governance_node_id', $scopedNodeIds)
                    ->whereNotNull('myb_sub_activities.governance_node_id');
            })
            ->distinct()
            ->orderBy('myb_sub_activities.name')
            ->get();

        return response()->json($subActivities);
    }

    /**
     * Calculate end date based on method and start date via AJAX.
     */
    public function calculateEndDate(Request $request)
    {
        $request->validate([
            'method_id' => 'required|exists:myb_procurement_method_planned,id',
            'start_date' => 'required|date',
        ]);

        $method = ProcurementMethodPlanned::find($request->method_id);
        $startDate = \Carbon\Carbon::parse($request->start_date);
        $endDate = $startDate->addDays($method->method_target_days);

        return response()->json([
            'end_date' => $endDate->format('Y-m-d'),
            'target_days' => $method->method_target_days,
        ]);
    }

    /**
     * Lookup procurement plan codes for selection.
     */
    public function lookup(Request $request)
    {
        $request->validate([
            'q' => 'nullable|string|max:100',
        ]);

        $query = ProcurementPlan::query()
            ->with([
                'activity:id,name',
                'subActivity:id,name',
                'methodPlanned:id,method_name',
                'programPlan:id,name',
                'geographic:id,name',
                'stage:id,stage_name',
                'status:id,name',
            ]);

        if (!auth()->user()->can('procurement.view_all')) {
            $query->where('created_by', auth()->id());
        }

        if ($request->filled('q')) {
            $term = $request->input('q');
            $query->where(function ($q) use ($term) {
                $q->where('procurement_code', 'like', "%{$term}%")
                    ->orWhere('title', 'like', "%{$term}%");
            });
        }

        $plans = $query->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $payload = $plans->map(function (ProcurementPlan $plan) {
            return [
                'id' => $plan->id,
                'procurement_code' => $plan->procurement_code,
                'title' => $plan->title,
                'fiscal_year' => $plan->fiscal_year,
                'estimated_budget' => $plan->estimated_budget,
                'estimated_start_date' => $plan->estimated_start_date?->format('Y-m-d'),
                'estimated_end_date' => $plan->estimated_end_date?->format('Y-m-d'),
                'program_plan' => $plan->programPlan?->name,
                'activity' => $plan->activity?->name,
                'sub_activity' => $plan->subActivity?->name,
                'method' => $plan->methodPlanned?->method_name,
                'geographic' => $plan->geographic?->name,
                'stage' => $plan->stage?->stage_name,
                'status' => $plan->status?->name,
            ];
        });

        return response()->json($payload);
    }

    public function programPlanSheet(ProcurementProgramPlan $programPlan)
    {
        $plans = $programPlan->procurements()->with([
            'activity',
            'subActivity',
            'methodPlanned',
            'geographic',
            'stage',
            'status',
            'creator',
        ])->orderBy('procurement_code')->get();

        return view('procurement.plans.program-plan-sheet', compact('programPlan', 'plans'));
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

    private function assertActivityInScope(Activity $activity): void
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        $activity->loadMissing('project');

        $nodeId = $activity->governance_node_id ?? $activity->project?->governance_node_id;
        if (!$nodeId || !in_array($nodeId, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this activity.');
        }
    }

    private function ensureSubActivityIsApprovedCommittedInScope(Request $request): void
    {
        if (!$request->filled('sub_activity_id')) {
            return;
        }

        $subActivity = SubActivity::with(['activity.project'])->find($request->input('sub_activity_id'));
        if (!$subActivity) {
            throw ValidationException::withMessages([
                'sub_activity_id' => 'Selected sub activity not found.',
            ]);
        }

        if ($request->filled('activity_id') && $subActivity->activity_id !== $request->input('activity_id')) {
            throw ValidationException::withMessages([
                'sub_activity_id' => 'Selected sub activity does not belong to the selected activity.',
            ]);
        }

        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds !== null) {
            $nodeId = $subActivity->governance_node_id
                ?? $subActivity->activity?->governance_node_id
                ?? $subActivity->activity?->project?->governance_node_id;

            if (!$nodeId || !in_array($nodeId, $scopedNodeIds, true)) {
                throw ValidationException::withMessages([
                    'sub_activity_id' => 'Selected sub activity is not within your governance scope.',
                ]);
            }
        }

        $hasApprovedCommitment = BudgetCommitment::query()
            ->where('allocation_level', 'sub_activity')
            ->where('allocation_id', $subActivity->id)
            ->where('status', BudgetCommitment::STATUS_APPROVED)
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->exists();

        if (!$hasApprovedCommitment) {
            throw ValidationException::withMessages([
                'sub_activity_id' => 'Selected sub activity does not have an approved commitment.',
            ]);
        }
    }
}
