<?php

namespace App\Http\Controllers\Procurement\Settings;

use App\Http\Controllers\Controller;
use App\Imports\Procurement\MethodPlannedImport;
use App\Exports\Procurement\MethodPlannedTemplateExport;
use App\Models\ProcurementMethodPlanned;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;

class MethodPlannedController extends Controller
{
    public function index()
    {
        $methods = ProcurementMethodPlanned::with(['creator', 'milestones'])
            ->latest()
            ->get();

        return view('procurement.settings.method-planned.index', compact('methods'));
    }

    public function create()
    {
        return view('procurement.settings.method-planned.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'method_name' => 'required|string|max:255|unique:myb_procurement_method_planned,method_name',
            'description' => 'nullable|string',
            'milestones' => 'required|array|min:1',
            'milestones.*.title' => 'required|string|max:255',
            'milestones.*.description' => 'nullable|string',
            'milestones.*.target_days' => 'required|integer|min:0',
            'milestones.*.sort_order' => 'nullable|integer|min:0',
            'milestones.*.is_active' => 'nullable',
        ]);

        $method = ProcurementMethodPlanned::create([
            'method_name' => $validated['method_name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->has('is_active'),
            'created_by' => auth()->id(),
        ]);

        $this->syncMilestones($method, $validated['milestones']);

        return redirect()->route('procurement.settings.method-planned.index')
            ->with('success', 'Method planned created successfully.');
    }

    public function edit(ProcurementMethodPlanned $methodPlanned)
    {
        $methodPlanned->load('milestones');

        return view('procurement.settings.method-planned.edit', compact('methodPlanned'));
    }

    public function update(Request $request, ProcurementMethodPlanned $methodPlanned)
    {
        $validated = $request->validate([
            'method_name' => 'required|string|max:255|unique:myb_procurement_method_planned,method_name,' . $methodPlanned->id,
            'description' => 'nullable|string',
            'milestones' => 'required|array|min:1',
            'milestones.*.title' => 'required|string|max:255',
            'milestones.*.description' => 'nullable|string',
            'milestones.*.target_days' => 'required|integer|min:0',
            'milestones.*.sort_order' => 'nullable|integer|min:0',
            'milestones.*.is_active' => 'nullable',
        ]);

        $methodPlanned->update([
            'method_name' => $validated['method_name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->has('is_active'),
        ]);

        $this->syncMilestones($methodPlanned, $validated['milestones']);

        return redirect()->route('procurement.settings.method-planned.index')
            ->with('success', 'Method planned updated successfully.');
    }

    public function destroy(ProcurementMethodPlanned $methodPlanned)
    {
        $methodPlanned->delete();

        return redirect()->route('procurement.settings.method-planned.index')
            ->with('success', 'Method planned deleted successfully.');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        try {
            Excel::import(new MethodPlannedImport(), $request->file('file'));
        } catch (ExcelValidationException $exception) {
            $errors = collect($exception->failures())->flatMap(function ($failure) {
                return collect($failure->errors())->map(fn ($error) => sprintf('Row %s: %s', $failure->row(), $error));
            })->all();

            return back()
                ->with('import_errors', $errors)
                ->with('error', 'Some rows failed validation. See the list below for details.');
        }

        return back()->with('success', 'Procurement methods imported successfully.');
    }

    private function syncMilestones(ProcurementMethodPlanned $method, array $milestones): void
    {
        $submittedIds = collect($milestones)
            ->pluck('id')
            ->filter()
            ->values()
            ->all();

        if ($submittedIds) {
            $method->milestones()->whereNotIn('id', $submittedIds)->delete();
        } else {
            $method->milestones()->delete();
        }

        foreach ($milestones as $index => $entry) {
            $title = trim($entry['title'] ?? '');
            if ($title === '') {
                continue;
            }

            $milestoneData = [
                'title' => $title,
                'description' => $entry['description'] ?? null,
                'target_days' => (int) ($entry['target_days'] ?? 0),
                'sort_order' => isset($entry['sort_order']) && $entry['sort_order'] !== '' ? (int) $entry['sort_order'] : $index + 1,
                'is_active' => !empty($entry['is_active']),
            ];

            if (!empty($entry['id'])) {
                $method->milestones()->where('id', $entry['id'])->update($milestoneData);
            } else {
                $milestoneData['created_by'] = auth()->id();
                $method->milestones()->create($milestoneData);
            }
        }
    }

    public function template()
    {
        return Excel::download(new MethodPlannedTemplateExport(), 'methods_planned_template.xlsx');
    }
}
