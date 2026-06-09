<?php

 namespace App\Http\Controllers;

use App\Models\Sector;
use App\Models\GovernanceNode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SectorController extends Controller
{

    public function __construct()
{
    $this->middleware('permission:sector.view')->only(['index','show']);
    $this->middleware('permission:sector.create')->only(['create','store']);
    $this->middleware('permission:sector.edit')->only(['edit','update']);
    $this->middleware('permission:sector.delete')->only(['destroy']);
}

    public function index()
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to sectors.');
        }

        $sectors = Sector::with('governanceNode')
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->orderBy('name')
            ->get();
        return view('sectors.index', compact('sectors'));
    }

    public function create()
    {
        $nodes = $this->availableNodes();
        return view('sectors.create', compact('nodes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'governance_node_id' => 'required|exists:myb_governance_nodes,id',
        ]);

        $this->assertNodeInScope($request->governance_node_id);

        Sector::create([
            'name' => $request->name,
            'description' => $request->description,
            'governance_node_id' => $request->governance_node_id,
        ]);

        return back()->with('success', 'Sector created successfully.');
    }

    public function edit(Sector $sector)
    {
        $this->assertSectorInScope($sector);
        $nodes = $this->availableNodes();
        return view('sectors.edit', compact('sector', 'nodes'));
    }

    public function update(Request $request, Sector $sector)
    {
        $this->assertSectorInScope($sector);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'governance_node_id' => 'required|exists:myb_governance_nodes,id',
        ]);

        $this->assertNodeInScope($request->governance_node_id);

        $sector->update([
            'name' => $request->name,
            'description' => $request->description,
            'governance_node_id' => $request->governance_node_id,
        ]);

        return redirect()
            ->route('budget.sectors.index')
            ->with('success', 'Sector updated successfully.');
    }

    public function destroy(Sector $sector)
    {
        $this->assertSectorInScope($sector);
        $sector->delete();

        return redirect()
            ->route('budget.sectors.index')
            ->with('success', 'Sector deleted successfully.');
    }

    private function scopedNodeIds(): ?array
    {
        $currentUser = Auth::user();

        if (!$currentUser || $currentUser->isAdmin() || $currentUser->isSuperAdmin()) {
            return null;
        }

        if (!$currentUser->governance_node_id) {
            return [];
        }

        return [$currentUser->governance_node_id];
    }

    private function availableNodes()
    {
        $scopedNodeIds = $this->scopedNodeIds();

        return GovernanceNode::with('level')
            ->orderBy('name')
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('id', $scopedNodeIds);
            })
            ->get();
    }

    private function assertSectorInScope(Sector $sector): void
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (!$sector->governance_node_id || !in_array($sector->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this sector.');
        }
    }

    private function assertNodeInScope(string $nodeId): void
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (!in_array($nodeId, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to assign this governance node.');
        }
    }
}
