<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Activity;
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
        $subActivities = SubActivity::where('activity_id', $activity->id)
            ->orderBy('name')
            ->get(['id', 'name']);

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
}
