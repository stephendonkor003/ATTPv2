<?php

namespace App\Http\Controllers\Procurement\Settings;

use App\Http\Controllers\Controller;
use App\Imports\Procurement\StageImport;
use App\Exports\Procurement\StageTemplateExport;
use App\Models\ProcurementStage;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;

class StageController extends Controller
{
    public function index()
    {
        $stages = ProcurementStage::with('creator')
            ->orderBy('sort_order')
            ->get();

        return view('procurement.settings.stages.index', compact('stages'));
    }

    public function create()
    {
        return view('procurement.settings.stages.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'stage_name' => 'required|string|max:255|unique:myb_procurement_stages,stage_name',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['is_active'] = $request->has('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        ProcurementStage::create($validated);

        return redirect()->route('procurement.settings.stages.index')
            ->with('success', 'Procurement stage created successfully.');
    }

    public function edit(ProcurementStage $stage)
    {
        return view('procurement.settings.stages.edit', compact('stage'));
    }

    public function update(Request $request, ProcurementStage $stage)
    {
        $validated = $request->validate([
            'stage_name' => 'required|string|max:255|unique:myb_procurement_stages,stage_name,' . $stage->id,
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $stage->update($validated);

        return redirect()->route('procurement.settings.stages.index')
            ->with('success', 'Procurement stage updated successfully.');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        try {
            Excel::import(new StageImport(), $request->file('file'));
        } catch (ExcelValidationException $exception) {
            $errors = collect($exception->failures())->flatMap(function ($failure) {
                return collect($failure->errors())->map(fn ($error) => sprintf('Row %s: %s', $failure->row(), $error));
            })->all();

            return back()
                ->with('import_errors', $errors)
                ->with('error', 'Some rows failed validation. See the list below for details.');
        }

        return back()->with('success', 'Procurement stages imported successfully.');
    }

    public function destroy(ProcurementStage $stage)
    {
        if ($stage->stepStages()->exists()) {
            return back()->with('error', 'Cannot delete stage with associated step stages.');
        }

        $stage->delete();

        return redirect()->route('procurement.settings.stages.index')
            ->with('success', 'Procurement stage deleted successfully.');
    }

    public function template()
    {
        return Excel::download(new StageTemplateExport(), 'stages_template.xlsx');
    }
}
