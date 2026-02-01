<?php

namespace App\Http\Controllers;

use App\Models\ProgramFunding;
use App\Models\ProgramFundingDocument;
use App\Models\Department;
use App\Models\Funder;
use App\Models\GovernanceNode;
use App\Models\AuMemberState;
use App\Models\AuRegionalBlock;
use App\Models\AuAspiration;
use App\Models\AuGoal;
use App\Models\AuFlagshipProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProgramFundingController extends Controller
{
    /* =====================================================
     * INDEX – GOVERNANCE + ANALYTICS
     * ===================================================== */
    public function index()
{
    $scopedNodeIds = $this->scopedNodeIds();
    if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
        abort(403, 'You do not have access to program funding records.');
    }

    $fundings = ProgramFunding::with([
            'program',
            'funder',
            'governanceNode',
        ])
        ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
            $query->whereIn('governance_node_id', $scopedNodeIds)
                ->whereNotNull('governance_node_id');
        })
        ->latest()
        ->paginate(15);

    $debug = [
        'user_id' => Auth::id(),
        'user_name' => Auth::user()?->name,
        'user_node_id' => Auth::user()?->governance_node_id,
        'is_admin' => Auth::user()?->isAdmin() ?? false,
        'visible_node_ids' => $scopedNodeIds,
    ];

    return view('finance.program-funding.index', compact('fundings', 'debug'));
}



    /* =====================================================
     * CREATE
     * ===================================================== */
    public function create()
    {
        return view('finance.program-funding.create', [
            'funders'          => Funder::orderBy('name')->get(),
            'nodes'            => $this->availableNodes(),
            'memberStates'     => AuMemberState::active()->ordered()->get(),
            'regionalBlocks'   => AuRegionalBlock::active()->ordered()->get(),
            'aspirations'      => AuAspiration::active()->ordered()->get(),
            'goals'            => AuGoal::active()->with('aspiration')->ordered()->get(),
            'flagshipProjects' => AuFlagshipProject::active()->ordered()->get(),
        ]);
    }




 public function store(Request $request)
{
    DB::beginTransaction();

    try {

        /* ================= VALIDATION ================= */
        $validated = $request->validate([
            'program_name'    => 'required|string|max:255',
            'funder_id'       => 'required|exists:myb_funders,id',
            'governance_node_id' => 'required|exists:myb_governance_nodes,id',
            'funding_type'    => 'required|in:grant,allocation,capital',
            'approved_amount' => 'required|numeric|min:0',
            'currency'        => 'required|string|max:10',
            'start_year'      => 'required|integer|min:2000',
            'end_year'        => 'required|integer|gte:start_year',

            // AU Strategic Alignment
            'is_continental_initiative' => 'nullable|boolean',
            'member_state_ids'          => 'nullable|array',
            'member_state_ids.*'        => 'exists:myb_au_member_states,id',
            'regional_block_ids'        => 'nullable|array',
            'regional_block_ids.*'      => 'exists:myb_au_regional_blocks,id',
            'aspiration_ids'            => 'nullable|array',
            'aspiration_ids.*'          => 'exists:myb_au_aspirations,id',
            'goal_ids'                  => 'nullable|array',
            'goal_ids.*'                => 'exists:myb_au_goals,id',
            'flagship_project_ids'      => 'nullable|array',
            'flagship_project_ids.*'    => 'exists:myb_au_flagship_projects,id',

            // Documents
            'documents'        => 'nullable|array',
            'documents.*'      => 'file|mimes:pdf,doc,docx,xls,xlsx,jpg,png|max:5242880',
            'document_types'   => 'required_with:documents|array',
            'document_types.*' => 'required|string|max:100',
            'document_names'   => 'required_with:documents|array',
            'document_names.*' => 'required|string|max:255',
        ]);

        /* ================= PROGRAM RULES ================= */
        // Program name is stored as free text. Program validation is not enforced here.

        $this->assertNodeInScope((int) $validated['governance_node_id']);

        /* ================= CREATE FUNDING ================= */
        $funding = ProgramFunding::create([
            'program_name'              => $validated['program_name'],
            'funder_id'                 => $validated['funder_id'],
            'governance_node_id'        => $validated['governance_node_id'],
            'funding_type'              => $validated['funding_type'],
            'approved_amount'           => $validated['approved_amount'],
            'currency'                  => $validated['currency'],
            'start_year'                => $validated['start_year'],
            'end_year'                  => $validated['end_year'],
            'status'                    => 'draft',
            'is_continental_initiative' => $request->boolean('is_continental_initiative'),
            'created_by'                => Auth::id(),
        ]);

        /* ================= SYNC AU RELATIONSHIPS ================= */
        // Only sync member states if NOT a continental initiative
        if (!$request->boolean('is_continental_initiative') && !empty($request->input('member_state_ids'))) {
            $funding->memberStates()->sync($request->input('member_state_ids'));
        }

        if (!empty($request->input('regional_block_ids'))) {
            $funding->regionalBlocks()->sync($request->input('regional_block_ids'));
        }

        if (!empty($request->input('aspiration_ids'))) {
            $funding->aspirations()->sync($request->input('aspiration_ids'));
        }

        if (!empty($request->input('goal_ids'))) {
            $funding->goals()->sync($request->input('goal_ids'));
        }

        if (!empty($request->input('flagship_project_ids'))) {
            $funding->flagshipProjects()->sync($request->input('flagship_project_ids'));
        }

        /* ================= SAVE DOCUMENTS ================= */
        if ($request->hasFile('documents')) {

            $files = $request->file('documents');
            $types = $request->input('document_types');
            $names = $request->input('document_names');

            foreach ($files as $index => $file) {

                if (!$file->isValid()) {
                    throw new \Exception('Invalid document upload detected.');
                }

                if (!isset($types[$index])) {
                    throw new \Exception('Document type mismatch detected.');
                }

                // Safe fallback for name
                $fileName = $names[$index]
                    ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

                $storedPath = $file->store(
                    'program-funding-documents',
                    'public'
                );

                ProgramFundingDocument::create([
                    'program_funding_id' => $funding->id,
                    'document_type'      => $types[$index],
                    'file_name'          => $fileName, // ✅ FIXED
                    'file_path'          => $storedPath,
                    'uploaded_by'        => Auth::id(),
                ]);
            }
        }

        DB::commit();

        return redirect()
            ->route('finance.program-funding.index')
            ->with('success', 'Program funding created successfully with documents.');

    } catch (\Throwable $e) {

        DB::rollBack();

        Log::error('Program Funding Store Error', [
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
        ]);

        return back()
            ->withErrors(['error' => $e->getMessage()])
            ->withInput();
    }
}



    /* =====================================================
     * SHOW
     * ===================================================== */
 public function show($id)
{
    $programFunding = ProgramFunding::with([
        'department',
        'program',
        'funder',
        'documents',
        'creator',
        'governanceNode',
        'memberStates',
        'regionalBlocks',
        'aspirations',
        'goals.aspiration',
        'flagshipProjects',
    ])->findOrFail($id);

    $this->assertFundingInScope($programFunding);

    return view('finance.program-funding.show', compact('programFunding'));
}


    /* =====================================================
     * SUBMIT / APPROVE / REJECT
     * ===================================================== */
    // public function submit(ProgramFunding $programFunding)
    // {
    //     abort_if($programFunding->status !== 'draft', 403);

    //     $programFunding->update([
    //         'status'       => 'submitted',
    //         'submitted_at' => now(),
    //     ]);

    //     return back()->with('success', 'Funding submitted for approval.');
    // }


    public function submit(ProgramFunding $funding)
    {
        abort_if(!in_array($funding->status, ['draft', 'rejected'], true), 403);
        $this->assertFundingInScope($funding);

        $funding->update([
            'status'       => 'submitted',
            'submitted_at' => now(),
            'rejection_reason' => null,
            'rejected_by' => null,
            'rejected_at' => null,
        ]);

        return back()->with('success', 'Funding submitted for approval.');
    }


    public function approve(ProgramFunding $funding)
    {
        abort_if($funding->status !== 'submitted', 403);
        $this->assertFundingInScope($funding);

        $allocated = $funding->program
            ? $funding->program->totalAllocatedAmount()
            : 0;

        if ($allocated > $funding->approved_amount) {
            return back()->withErrors([
                'approved_amount' =>
                    'Cannot approve funding. Allocations already exceed approved amount.',
            ]);
        }

        $funding->update([
            'status'      => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Funding approved.');
    }

    public function reject(ProgramFunding $funding)
    {
        abort_if($funding->status !== 'submitted', 403);
        $this->assertFundingInScope($funding);

        request()->validate([
            'rejection_reason' => 'required|string|min:5',
        ]);

        $funding->update([
            'status' => 'rejected',
            'rejection_reason' => request('rejection_reason'),
            'rejected_by' => Auth::id(),
            'rejected_at' => now(),
        ]);

        return back()->with('success', 'Funding rejected.');
    }



    /* =====================================================
 * EDIT
 * ===================================================== */
public function edit(ProgramFunding $programFunding)
{
    $this->assertFundingInScope($programFunding);

    // Load AU relationships for pre-selection
    $programFunding->load([
        'memberStates',
        'regionalBlocks',
        'aspirations',
        'goals',
        'flagshipProjects',
    ]);

    return view('finance.program-funding.edit', [
        'programFunding'   => $programFunding,
        'nodes'            => $this->availableNodes(),
        'funders'          => Funder::orderBy('name')->get(),
        'memberStates'     => AuMemberState::active()->ordered()->get(),
        'regionalBlocks'   => AuRegionalBlock::active()->ordered()->get(),
        'aspirations'      => AuAspiration::active()->ordered()->get(),
        'goals'            => AuGoal::active()->with('aspiration')->ordered()->get(),
        'flagshipProjects' => AuFlagshipProject::active()->ordered()->get(),
    ]);
}

/* =====================================================
 * UPDATE
 * ===================================================== */
public function update(Request $request, ProgramFunding $programFunding)
{
    $this->assertFundingInScope($programFunding);

    $validated = $request->validate([
        'program_name' => 'required|string|max:255',
        'funder_id' => 'required|exists:myb_funders,id',
        'governance_node_id' => 'required|exists:myb_governance_nodes,id',
        'funding_type' => 'required|in:grant,allocation,capital',
        'approved_amount' => 'required|numeric|min:0',
        'currency'        => 'required|string|max:10',
        'start_year'      => 'required|integer|min:2000',
        'end_year'        => 'required|integer|gte:start_year',

        // AU Strategic Alignment
        'is_continental_initiative' => 'nullable|boolean',
        'member_state_ids'          => 'nullable|array',
        'member_state_ids.*'        => 'exists:myb_au_member_states,id',
        'regional_block_ids'        => 'nullable|array',
        'regional_block_ids.*'      => 'exists:myb_au_regional_blocks,id',
        'aspiration_ids'            => 'nullable|array',
        'aspiration_ids.*'          => 'exists:myb_au_aspirations,id',
        'goal_ids'                  => 'nullable|array',
        'goal_ids.*'                => 'exists:myb_au_goals,id',
        'flagship_project_ids'      => 'nullable|array',
        'flagship_project_ids.*'    => 'exists:myb_au_flagship_projects,id',
    ]);

    $this->assertNodeInScope((int) $validated['governance_node_id']);

    // Update basic fields
    $programFunding->update([
        'program_name'              => $validated['program_name'],
        'funder_id'                 => $validated['funder_id'],
        'governance_node_id'        => $validated['governance_node_id'],
        'funding_type'              => $validated['funding_type'],
        'approved_amount'           => $validated['approved_amount'],
        'currency'                  => $validated['currency'],
        'start_year'                => $validated['start_year'],
        'end_year'                  => $validated['end_year'],
        'is_continental_initiative' => $request->boolean('is_continental_initiative'),
    ]);

    /* ================= SYNC AU RELATIONSHIPS ================= */
    // Only sync member states if NOT a continental initiative
    if ($request->boolean('is_continental_initiative')) {
        $programFunding->memberStates()->sync([]);
    } else {
        $programFunding->memberStates()->sync($request->input('member_state_ids', []));
    }

    $programFunding->regionalBlocks()->sync($request->input('regional_block_ids', []));
    $programFunding->aspirations()->sync($request->input('aspiration_ids', []));
    $programFunding->goals()->sync($request->input('goal_ids', []));
    $programFunding->flagshipProjects()->sync($request->input('flagship_project_ids', []));

    return redirect()
        ->route('finance.program-funding.show', $programFunding)
        ->with('success', 'Program funding updated successfully.');
}

    public function destroy(ProgramFunding $programFunding)
    {
        $this->assertFundingInScope($programFunding);
        $programFunding->delete();

        return redirect()
            ->route('finance.program-funding.index')
            ->with('success', 'Program funding deleted successfully.');
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

    private function availableNodes()
    {
        $scopedNodeIds = $this->scopedNodeIds();

        return GovernanceNode::orderBy('name')
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('id', $scopedNodeIds);
            })
            ->get();
    }

    private function assertFundingInScope(ProgramFunding $funding): void
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (!$funding->governance_node_id || !in_array($funding->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this program funding record.');
        }
    }

    private function assertNodeInScope(int $nodeId): void
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (!in_array($nodeId, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to assign this governance node.');
        }
    }

    // Exact-node scoping only (no descendants).



}
