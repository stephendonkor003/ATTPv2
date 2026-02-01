<?php

namespace App\Http\Controllers\Procurement\Settings;

use App\Http\Controllers\Controller;
use App\Imports\Procurement\GeographicImport;
use App\Exports\Procurement\GeographicTemplateExport;
use App\Models\ProcurementGeographic;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;

class GeographicController extends Controller
{
    public function index()
    {
        $geographics = ProcurementGeographic::with('creator')
            ->latest()
            ->get();

        return view('procurement.settings.geographics.index', compact('geographics'));
    }

    public function create()
    {
        return view('procurement.settings.geographics.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:myb_procurement_geographics,name',
            'description' => 'nullable|string',
            'is_active' => 'nullable',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['is_active'] = $request->has('is_active');

        ProcurementGeographic::create($validated);

        return redirect()->route('procurement.settings.geographics.index')
            ->with('success', 'Geographic location created successfully.');
    }

    public function edit(ProcurementGeographic $geographic)
    {
        return view('procurement.settings.geographics.edit', compact('geographic'));
    }

    public function update(Request $request, ProcurementGeographic $geographic)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:myb_procurement_geographics,name,' . $geographic->id,
            'description' => 'nullable|string',
            'is_active' => 'nullable',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $geographic->update($validated);

        return redirect()->route('procurement.settings.geographics.index')
            ->with('success', 'Geographic location updated successfully.');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        try {
            Excel::import(new GeographicImport(), $request->file('file'));
        } catch (ExcelValidationException $exception) {
            $errors = collect($exception->failures())->flatMap(function ($failure) {
                return collect($failure->errors())->map(fn ($error) => sprintf('Row %s: %s', $failure->row(), $error));
            })->all();

            return back()
                ->with('import_errors', $errors)
                ->with('error', 'Some rows failed validation. See the list below for details.');
        }

        return back()->with('success', 'Geographic locations imported successfully.');
    }

    public function destroy(ProcurementGeographic $geographic)
    {
        $geographic->delete();

        return redirect()->route('procurement.settings.geographics.index')
            ->with('success', 'Geographic location deleted successfully.');
    }

    public function template()
    {
        return Excel::download(new GeographicTemplateExport(), 'geographics_template.xlsx');
    }
}
