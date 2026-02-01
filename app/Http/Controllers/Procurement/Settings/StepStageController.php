<?php

namespace App\Http\Controllers\Procurement\Settings;

use App\Http\Controllers\Controller;
use App\Imports\Procurement\StepStageImport;
use App\Exports\Procurement\StepStageTemplateExport;
use App\Models\ProcurementStage;
use App\Models\ProcurementStepStage;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;

class StepStageController extends Controller
{
    public function index()
    {
        $stepStages = ProcurementStepStage::with(['creator', 'stage'])
            ->orderBy('sort_order')
            ->get();

        return view('procurement.settings.step-stages.index', compact('stepStages'));
    }

    public function create()
    {
        $stages = ProcurementStage::active()->ordered()->get();

        return view('procurement.settings.step-stages.create', compact('stages'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'stage_id' => 'nullable|exists:myb_procurement_stages,id',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['is_active'] = $request->has('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        ProcurementStepStage::create($validated);

        return redirect()->route('procurement.settings.step-stages.index')
            ->with('success', 'Step stage created successfully.');
    }

    public function edit(ProcurementStepStage $stepStage)
    {
        $stages = ProcurementStage::active()->ordered()->get();

        return view('procurement.settings.step-stages.edit', compact('stepStage', 'stages'));
    }

    public function update(Request $request, ProcurementStepStage $stepStage)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'stage_id' => 'nullable|exists:myb_procurement_stages,id',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $stepStage->update($validated);

        return redirect()->route('procurement.settings.step-stages.index')
            ->with('success', 'Step stage updated successfully.');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        try {
            Excel::import(new StepStageImport(), $request->file('file'));
        } catch (ExcelValidationException $exception) {
            $errors = collect($exception->failures())->flatMap(function ($failure) {
                return collect($failure->errors())->map(fn ($error) => sprintf('Row %s: %s', $failure->row(), $error));
            })->all();

            return back()
                ->with('import_errors', $errors)
                ->with('error', 'Some rows failed validation. See the list below for details.');
        }

        return back()->with('success', 'Step stages imported successfully.');
    }

    public function destroy(ProcurementStepStage $stepStage)
    {
        if ($stepStage->approvalProcesses()->exists()) {
            return back()->with('error', 'Cannot delete step stage with associated approval processes.');
        }

        $stepStage->delete();

        return redirect()->route('procurement.settings.step-stages.index')
            ->with('success', 'Step stage deleted successfully.');
    }

    public function template()
    {
        return Excel::download(new StepStageTemplateExport(), 'step_stages_template.xlsx');
    }
}
