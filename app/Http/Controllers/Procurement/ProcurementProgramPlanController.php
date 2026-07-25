<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Procurement\Concerns\GovernanceScope;
use App\Models\GovernanceNode;
use App\Models\ProcurementProgramPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProcurementProgramPlanController extends Controller
{
    use GovernanceScope;

    public function index()
    {
        $plans = $this->applyProcurementProgramPlanScope(
            ProcurementProgramPlan::with(['creator', 'governanceNode'])
        )
            ->withCount('procurements')
            ->orderByDesc('created_at')
            ->get();

        $governanceNodes = $this->availableGovernanceNodes();
        $canChoosePortfolio = $governanceNodes->count() !== 1;
        $currentGovernanceNodeName = $governanceNodes->count() === 1
            ? $governanceNodes->first()->name
            : null;

        return view('procurement.structure.plans.index', compact(
            'plans',
            'governanceNodes',
            'canChoosePortfolio',
            'currentGovernanceNodeName'
        ));
    }

    public function store(Request $request)
    {
        $governanceNodeId = $this->resolveGovernanceNodeId($request);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('myb_procurement_program_plans', 'name')
                    ->where(fn ($query) => $query->where('governance_node_id', $governanceNodeId)),
            ],
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'sometimes|boolean',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['governance_node_id'] = $governanceNodeId;
        $validated['is_active'] = $request->boolean('is_active', true);

        ProcurementProgramPlan::create($validated);

        return redirect()->route('procurement.structure.index')
            ->with('success', 'Program plan saved.');
    }

    public function edit(ProcurementProgramPlan $programPlan)
    {
        $this->assertProcurementProgramPlanInScope($programPlan);

        $governanceNodes = $this->availableGovernanceNodes($programPlan->governance_node_id);
        $canChoosePortfolio = $governanceNodes->count() !== 1;
        $currentGovernanceNodeName = $governanceNodes->count() === 1
            ? $governanceNodes->first()->name
            : null;

        return view('procurement.structure.plans.edit', compact(
            'programPlan',
            'governanceNodes',
            'canChoosePortfolio',
            'currentGovernanceNodeName'
        ));
    }

    public function update(Request $request, ProcurementProgramPlan $programPlan)
    {
        $this->assertProcurementProgramPlanInScope($programPlan);

        $governanceNodeId = $this->resolveGovernanceNodeId($request, $programPlan);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('myb_procurement_program_plans', 'name')
                    ->where(fn ($query) => $query->where('governance_node_id', $governanceNodeId))
                    ->ignore($programPlan->id),
            ],
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'required|boolean',
        ]);

        $validated['governance_node_id'] = $governanceNodeId;
        $validated['is_active'] = $request->boolean('is_active');

        if (
            (string) $programPlan->governance_node_id !== (string) $governanceNodeId
            && $programPlan->procurements()->exists()
        ) {
            throw ValidationException::withMessages([
                'governance_node_id' => 'This plan already contains procurement items. Move or remove those items before changing its portfolio.',
            ]);
        }

        DB::transaction(fn () => $programPlan->update($validated));

        return redirect()->route('procurement.structure.index')
            ->with('success', 'Program plan updated.');
    }

    private function availableGovernanceNodes(?string $includeNodeId = null)
    {
        $query = GovernanceNode::query()
            ->where(function ($scope) use ($includeNodeId) {
                $scope->whereNull('status')
                    ->orWhere('status', 'active')
                    ->when($includeNodeId, fn ($nodes) => $nodes->orWhere('id', $includeNodeId));
            })
            ->orderBy('name');

        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds !== null) {
            $query->whereIn('id', $scopedNodeIds);
        }

        return $query->get(['id', 'name', 'code']);
    }

    private function resolveGovernanceNodeId(Request $request, ?ProcurementProgramPlan $programPlan = null): string
    {
        $governanceNodes = $this->availableGovernanceNodes($programPlan?->governance_node_id);

        if ($governanceNodes->isEmpty()) {
            abort(403, 'You do not have an assigned portfolio for procurement planning.');
        }

        if ($governanceNodes->count() === 1) {
            return (string) $governanceNodes->first()->id;
        }

        $request->validate([
            'governance_node_id' => [
                'required',
                Rule::exists('myb_governance_nodes', 'id'),
            ],
        ]);

        $governanceNodeId = (string) $request->input('governance_node_id');
        $this->assertGovernanceNodeInScope($governanceNodeId, 'You do not have access to this portfolio.');

        return $governanceNodeId;
    }
}
