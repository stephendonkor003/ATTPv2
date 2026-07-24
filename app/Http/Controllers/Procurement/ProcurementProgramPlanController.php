<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Procurement\Concerns\GovernanceScope;
use App\Models\GovernanceNode;
use App\Models\ProcurementProgramPlan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        ]);

        $validated['created_by'] = auth()->id();
        $validated['governance_node_id'] = $governanceNodeId;

        ProcurementProgramPlan::create($validated);

        return redirect()->route('procurement.structure.index')
            ->with('success', 'Program plan saved.');
    }

    public function edit(ProcurementProgramPlan $programPlan)
    {
        $this->assertProcurementProgramPlanInScope($programPlan);

        $governanceNodes = $this->availableGovernanceNodes();
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
        ]);

        $validated['governance_node_id'] = $governanceNodeId;

        $programPlan->update($validated);

        return redirect()->route('procurement.structure.index')
            ->with('success', 'Program plan updated.');
    }

    private function availableGovernanceNodes()
    {
        $query = GovernanceNode::query()
            ->where(function ($scope) {
                $scope->whereNull('status')
                    ->orWhere('status', 'active');
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
        $governanceNodes = $this->availableGovernanceNodes();

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