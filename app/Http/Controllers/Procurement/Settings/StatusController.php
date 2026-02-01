<?php

namespace App\Http\Controllers\Procurement\Settings;

use App\Http\Controllers\Controller;
use App\Imports\Procurement\StatusImport;
use App\Exports\Procurement\StatusTemplateExport;
use App\Models\ProcurementStatus;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;

class StatusController extends Controller
{
    public function index()
    {
        $statuses = ProcurementStatus::with('creator')
            ->orderBy('sort_order')
            ->get();

        return view('procurement.settings.statuses.index', compact('statuses'));
    }

    public function create()
    {
        return view('procurement.settings.statuses.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:myb_procurement_statuses,name',
            'color' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['is_active'] = $request->has('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        ProcurementStatus::create($validated);

        return redirect()->route('procurement.settings.statuses.index')
            ->with('success', 'Procurement status created successfully.');
    }

    public function edit(ProcurementStatus $status)
    {
        return view('procurement.settings.statuses.edit', compact('status'));
    }

    public function update(Request $request, ProcurementStatus $status)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:myb_procurement_statuses,name,' . $status->id,
            'color' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $status->update($validated);

        return redirect()->route('procurement.settings.statuses.index')
            ->with('success', 'Procurement status updated successfully.');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        try {
            Excel::import(new StatusImport(), $request->file('file'));
        } catch (ExcelValidationException $exception) {
            $errors = collect($exception->failures())->flatMap(function ($failure) {
                return collect($failure->errors())->map(fn ($error) => sprintf('Row %s: %s', $failure->row(), $error));
            })->all();

            return back()
                ->with('import_errors', $errors)
                ->with('error', 'Some rows failed validation. See the list below for details.');
        }

        return back()->with('success', 'Procurement statuses imported successfully.');
    }

    public function destroy(ProcurementStatus $status)
    {
        $status->delete();

        return redirect()->route('procurement.settings.statuses.index')
            ->with('success', 'Procurement status deleted successfully.');
    }

    public function template()
    {
        return Excel::download(new StatusTemplateExport(), 'statuses_template.xlsx');
    }
}
