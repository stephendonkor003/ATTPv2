<?php

namespace App\Http\Controllers\Procurement\Settings;

use App\Http\Controllers\Controller;
use App\Imports\Procurement\StepApprovalImport;
use App\Exports\Procurement\StepApprovalTemplateExport;
use App\Models\GovernanceNode;
use App\Models\ProcurementStepApproval;
use App\Models\ProcurementStepStage;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;

class StepApprovalController extends Controller
{
    public function index()
    {
        $approvals = ProcurementStepApproval::with(['creator', 'stepStage', 'governanceNode'])
            ->orderBy('approval_order')
            ->get();

        return view('procurement.settings.step-approvals.index', compact('approvals'));
    }

    public function create()
    {
        $stepStages = ProcurementStepStage::active()->ordered()->get();
        $governanceNodes = GovernanceNode::where('status', 'active')->orderBy('name')->get();

        return view('procurement.settings.step-approvals.create', compact('stepStages', 'governanceNodes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'step_stage_id' => 'nullable|exists:myb_procurement_step_stages,id',
            'governance_node_id' => 'nullable|exists:myb_governance_nodes,id',
            'description' => 'nullable|string',
            'approval_order' => 'nullable|integer|min:0',
            'is_required' => 'nullable',
            'is_active' => 'nullable',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['is_required'] = $request->has('is_required');
        $validated['is_active'] = $request->has('is_active');
        $validated['approval_order'] = $validated['approval_order'] ?? 0;

        ProcurementStepApproval::create($validated);

        return redirect()->route('procurement.settings.step-approvals.index')
            ->with('success', 'Step approval process created successfully.');
    }

    public function edit(ProcurementStepApproval $stepApproval)
    {
        $stepStages = ProcurementStepStage::active()->ordered()->get();
        $governanceNodes = GovernanceNode::where('status', 'active')->orderBy('name')->get();

        return view('procurement.settings.step-approvals.edit', compact('stepApproval', 'stepStages', 'governanceNodes'));
    }

    public function update(Request $request, ProcurementStepApproval $stepApproval)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'step_stage_id' => 'nullable|exists:myb_procurement_step_stages,id',
            'governance_node_id' => 'nullable|exists:myb_governance_nodes,id',
            'description' => 'nullable|string',
            'approval_order' => 'nullable|integer|min:0',
            'is_required' => 'nullable',
            'is_active' => 'nullable',
        ]);

        $validated['is_required'] = $request->has('is_required');
        $validated['is_active'] = $request->has('is_active');

        $stepApproval->update($validated);

        return redirect()->route('procurement.settings.step-approvals.index')
            ->with('success', 'Step approval process updated successfully.');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        try {
            Excel::import(new StepApprovalImport(), $request->file('file'));
        } catch (ExcelValidationException $exception) {
            $errors = collect($exception->failures())->flatMap(function ($failure) {
                return collect($failure->errors())->map(fn ($error) => sprintf('Row %s: %s', $failure->row(), $error));
            })->all();

            return back()
                ->with('import_errors', $errors)
                ->with('error', 'Some rows failed validation. See the list below for details.');
        }

        return back()->with('success', 'Step approvals imported successfully.');
    }

    public function destroy(ProcurementStepApproval $stepApproval)
    {
        $stepApproval->delete();

        return redirect()->route('procurement.settings.step-approvals.index')
            ->with('success', 'Step approval process deleted successfully.');
    }

    public function template()
    {
        return Excel::download(new StepApprovalTemplateExport(), 'step_approvals_template.xlsx');
    }
}
