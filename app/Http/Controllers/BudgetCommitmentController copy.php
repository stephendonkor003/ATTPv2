<?php

namespace App\Http\Controllers;

use App\Models\BudgetCommitment;
use App\Models\ProgramFunding;
use App\Models\ResourceCategory;
use App\Models\Resource;
use App\Models\Project;
use App\Models\Activity;
use App\Models\SubActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
    $commitments = BudgetCommitment::with([
        'programFunding.program',
        'resourceCategory',
        'resource',

        // eager load concrete models
        'programFunding',
    ])
    ->latest()
    ->paginate(15);

    return view('finance.commitments.index', compact('commitments'));
}


    public function create()
    {
        return view('finance.commitments.create', [
            'fundings' => ProgramFunding::where('status', 'approved')->get(),
            'resourceCategories' => ResourceCategory::where('status', 'active')->get(),
        ]);
    }

    public function store(Request $request)
{
    /* =====================================================
     * 1. VALIDATION
     * ===================================================== */
    $validated = $request->validate([
        'program_funding_id'   => 'required|exists:myb_program_fundings,id',
        'allocation_level'     => 'required|in:project,activity,sub_activity',
        'allocation_id'        => 'required|integer',
        'resource_category_id' => 'required|exists:myb_resource_categories,id',
        'resource_id'          => 'required|exists:myb_resources,id',
        'commitment_amount'    => 'required|numeric|min:0.01',
        'commitment_year'      => 'required|integer|min:2000',
    ]);

    DB::beginTransaction();

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

        /* =====================================================
         * 3. ALLOCATION VALIDATION
         * ===================================================== */
        $allocationExists = match ($validated['allocation_level']) {
            'project'      => Project::where('id', $validated['allocation_id'])->exists(),
            'activity'     => Activity::where('id', $validated['allocation_id'])->exists(),
            'sub_activity' => SubActivity::where('id', $validated['allocation_id'])->exists(),
        };

        if (!$allocationExists) {
            return back()
                ->withErrors(['allocation_id' => 'Selected allocation record does not exist.'])
                ->withInput();
        }

        /* =====================================================
         * 4. ALLOCATED AMOUNT (SAFE)
         * ===================================================== */
        $allocatedAmount = $this->getAllocatedAmount(
            $validated['allocation_level'],
            $validated['allocation_id'],
            $validated['commitment_year']
        );

        $allocatedAmount = (float) ($allocatedAmount ?? 0);

        if ($allocatedAmount <= 0) {
            return back()
                ->withErrors([
                    'commitment_year' =>
                        'No budget allocation exists for the selected year.'
                ])
                ->withInput();
        }

        /* =====================================================
         * 5. COMMITTED SO FAR
         * ===================================================== */
        $committedAmount = BudgetCommitment::where(
                'allocation_level',
                $validated['allocation_level']
            )
            ->where('allocation_id', $validated['allocation_id'])
            ->where('commitment_year', $validated['commitment_year'])
            ->whereIn('status', [
                BudgetCommitment::STATUS_DRAFT,
                BudgetCommitment::STATUS_SUBMITTED,
                BudgetCommitment::STATUS_APPROVED,
            ])
            ->sum('commitment_amount');

        $remaining = $allocatedAmount - $committedAmount;

        if ($validated['commitment_amount'] > $remaining) {
            return back()
                ->withErrors([
                    'commitment_amount' =>
                        'Commitment exceeds remaining budget. Available: ' .
                        number_format($remaining, 2)
                ])
                ->withInput();
        }

        /* =====================================================
         * 6. CREATE COMMITMENT
         * ===================================================== */
        BudgetCommitment::create([
            'program_funding_id'   => $validated['program_funding_id'],
            'allocation_level'     => $validated['allocation_level'],
            'allocation_id'        => $validated['allocation_id'],
            'resource_category_id' => $validated['resource_category_id'],
            'resource_id'          => $validated['resource_id'],
            'commitment_amount'    => $validated['commitment_amount'],
            'commitment_year'      => $validated['commitment_year'],
            'status'               => BudgetCommitment::STATUS_DRAFT,
            'created_by'           => Auth::id(),
        ]);

        DB::commit();

        return redirect()
            ->route('finance.commitments.index')
            ->with('success', 'Budget commitment created successfully (Draft).');

    } catch (\Throwable $e) {

        DB::rollBack();

        /* =====================================================
         * 7. LOG + SURFACE ERROR
         * ===================================================== */
        \Log::error('Budget Commitment Store Failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'payload' => $request->all(),
        ]);

         return back()
    ->withErrors([
        'system' => $e->getMessage()
    ])
    ->withInput();

    }
}


    public function show(BudgetCommitment $commitment)
    {
        $commitment->load([
            'programFunding.program',
            'resourceCategory',
            'resource'
        ]);

        return view('finance.commitments.show', compact('commitment'));
    }

    public function submit(BudgetCommitment $commitment)
    {
        if ($commitment->status !== self::STATUS_DRAFT) {
            abort(403);
        }

        $commitment->update(['status' => self::STATUS_SUBMITTED]);

        return back()->with('success', 'Commitment submitted.');
    }

    public function approve(BudgetCommitment $commitment)
    {
        if ($commitment->status !== self::STATUS_SUBMITTED) {
            abort(403);
        }

        $commitment->update([
            'status'      => self::STATUS_APPROVED,
            'approved_by'=> Auth::id(),
            'approved_at'=> now(),
        ]);

        return back()->with('success', 'Commitment approved.');
    }

    public function cancel(BudgetCommitment $commitment)
    {
        if ($commitment->status === self::STATUS_APPROVED) {
            abort(403);
        }

        $commitment->update(['status' => self::STATUS_CANCELLED]);

        return back()->with('success', 'Commitment cancelled.');
    }

    /* =========================================================
     | ================== RESOURCE MANAGEMENT =================
     ========================================================= */

    /** Resource Categories (index + store) */
    public function resourceCategories()
    {
        return view('finance.resources.categories.index', [
            'categories' => ResourceCategory::latest()->get()
        ]);
    }

    public function storeResourceCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100'
        ]);

        ResourceCategory::create([
            'name' => $request->name,
            'description' => $request->description,
            'status' => 'active',
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', 'Resource category added.');
    }

    /** Resources (items) */
    public function resources()
    {
        return view('finance.resources.items.index', [
            'resources' => Resource::with('category')->latest()->get(),
            'categories'=> ResourceCategory::where('status','active')->get()
        ]);
    }

    public function storeResource(Request $request)
    {
        $request->validate([
            'resource_category_id' => 'required|exists:myb_resource_categories,id',
            'name' => 'required|string|max:255',
        ]);

        Resource::create([
            'resource_category_id' => $request->resource_category_id,
            'name' => $request->name,
            'reference_code' => $request->reference_code,
            'description' => $request->description,
            'status' => 'active',
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', 'Resource created.');
    }

    /* =========================================================
     | ================== AJAX ENDPOINTS ======================
     ========================================================= */

    public function projects()
    {
        return Project::select('id','name')->orderBy('name')->get();
    }

    public function activities($projectId)
    {
        return Activity::where('project_id',$projectId)
            ->select('id','name')->orderBy('name')->get();
    }

    public function subActivities($activityId)
    {
        return SubActivity::where('activity_id',$activityId)
            ->select('id','name')->orderBy('name')->get();
    }

    public function allocationYears($level, $id)
    {
        $years = match ($level) {
            'project' => DB::table('myb_project_allocations')->where('project_id',$id)->pluck('year'),
            'activity' => DB::table('myb_activity_allocations')->where('activity_id',$id)->pluck('year'),
            'sub_activity' => DB::table('myb_sub_activity_allocations')->where('sub_activity_id',$id)->pluck('year'),
        };

        return response()->json($years->unique()->values());
    }

    public function remainingBudget(Request $request)
    {
        $allocated = $this->allocationSum(
            $request->allocation_level,
            $request->allocation_id,
            $request->year
        );

        $committed = BudgetCommitment::where([
            'allocation_level' => $request->allocation_level,
            'allocation_id' => $request->allocation_id,
            'commitment_year' => $request->year,
        ])->whereIn('status', [
            self::STATUS_DRAFT,
            self::STATUS_SUBMITTED,
            self::STATUS_APPROVED,
        ])->sum('commitment_amount');

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
        $months = collect(range(1,12))
            ->map(fn($m) => Carbon::create()->month($m)->format('M'));

        return response()->json([
            'months' => $months,
            'committed' => BudgetCommitment::whereIn('status',[
                self::STATUS_SUBMITTED,
                self::STATUS_APPROVED
            ])->selectRaw('EXTRACT(MONTH FROM created_at) as m, SUM(commitment_amount) t')
              ->groupByRaw('EXTRACT(MONTH FROM created_at)')->pluck('t'),
        ]);
    }

    /* =========================================================
     | ================== INTERNAL HELPERS ====================
     ========================================================= */

    private function allocationSum(string $level, int $id, int $year): float
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

    /**
 * AJAX: Get resources by category
 */
public function resourcesByCategory($categoryId)
{
    return Resource::where('resource_category_id', $categoryId)
        ->where('status', 'active')
        ->select('id', 'name')
        ->orderBy('name')
        ->get();
}

/**
 * =========================================================
 * HELPER: Get Allocated Amount for a Level & Year
 * =========================================================
 */
private function getAllocatedAmount(string $level, int $id, int $year): float
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


}
