<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Procurement;
use App\Models\DynamicForm;
use App\Models\ProcurementFormAssignment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Procurement\Concerns\GovernanceScope;

class ProcurementFormAssignmentController extends Controller
{
    use GovernanceScope;

    /**
     * Show form attachment page
     */
    public function create(Procurement $procurement)
    {
        $this->assertProcurementInScope($procurement);
        $forms = DynamicForm::approved()
            ->when($procurement->governance_node_id, function ($query) use ($procurement) {
                $query->whereHas('resource', function ($res) use ($procurement) {
                    $res->where('governance_node_id', $procurement->governance_node_id);
                });
            })
            ->orderBy('name')
            ->get();

        return view('procurement.forms.attach', compact(
            'procurement',
            'forms'
        ));
    }

    /**
     * Attach form to procurement
     */
    public function store(Request $request, Procurement $procurement)
    {
        $this->assertProcurementInScope($procurement);
        $data = $request->validate([
            'form_id' => 'required|exists:dynamic_forms,id',
            'stage'   => 'required|in:submission,prescreening,technical,financial',
        ]);

        // Ensure form is approved
        $form = DynamicForm::approved()->findOrFail($data['form_id']);
        if ($procurement->governance_node_id && $form->resource?->governance_node_id !== $procurement->governance_node_id) {
            abort(403, 'You do not have access to attach this form to the selected procurement.');
        }

        ProcurementFormAssignment::updateOrCreate(
            [
                'procurement_id' => $procurement->id,
                'stage' => $data['stage'],
            ],
            [
                'form_id'    => $form->id,
                'created_by' => auth()->id(),
            ]
        );

        return back()->with('success', 'Form attached successfully.');
    }


    /**
     * Attach a dynamic form to a procurement
     */
    public function attachForm(Request $request)
    {
        $request->validate([
            'form_id'        => 'required|exists:dynamic_forms,id',
            'procurement_id' => [
                'required',
                Rule::exists('procurements', 'id')->whereNull('deleted_at'),
            ],
        ]);

        $procurement = Procurement::findOrFail($request->procurement_id);
        $this->assertProcurementInScope($procurement);
        $form = DynamicForm::findOrFail($request->form_id);
        if ($procurement->governance_node_id && $form->resource?->governance_node_id !== $procurement->governance_node_id) {
            abort(403, 'You do not have access to attach this form to the selected procurement.');
        }

        // ✅ Prevent duplicate attachment
        if ($procurement->forms()
            ->where('dynamic_form_id', $request->form_id)
            ->exists()) {

            return back()->with('error', 'This form is already attached to the selected procurement.');
        }

        // ✅ Attach form
        $procurement->forms()->attach($request->form_id, [
            'attached_by' => auth()->id(),
            'attached_at' => now(),
        ]);

        return back()->with('success', 'Form attached to procurement successfully.');
    }
}
