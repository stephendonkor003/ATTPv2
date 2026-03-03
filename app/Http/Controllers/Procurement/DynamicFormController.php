<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\DynamicForm;
use App\Models\ProcurementFormAssignment;
use App\Models\Resource;
use App\Models\Procurement;
use Illuminate\Http\Request;
use App\Http\Controllers\Procurement\Concerns\GovernanceScope;

class DynamicFormController extends Controller
{
    use GovernanceScope;

    /**
     * List all procurement forms
     */
    // public function index()
    // {
    //     $forms = DynamicForm::with('resource')
    //         ->orderByDesc('created_at')
    //         ->get();

    //     return view('procurement.forms.index', compact('forms'));
    // }

    public function index()
    {
        $forms = DynamicForm::with('resource')
            ->withCount('submissions')
            ->when($this->scopedNodeIds() !== null, function ($query) {
                $query->whereHas('resource', function ($res) {
                    $res->whereIn('governance_node_id', $this->scopedNodeIds())
                        ->whereNotNull('governance_node_id');
                });
            })
            ->latest()
            ->get();

        $resources = Resource::orderBy('name')
            ->when($this->scopedNodeIds() !== null, function ($query) {
                $query->whereIn('governance_node_id', $this->scopedNodeIds())
                    ->whereNotNull('governance_node_id');
            })
            ->get();

        $procurements = $this->applyProcurementScope(
            Procurement::orderBy('title')
        )->get(); // 👈 REQUIRED

        return view('procurement.forms.index', compact(
            'forms',
            'resources',
            'procurements'
        ));
    }



    /**
     * Show create form page
     */
    public function create()
    {
        $resources = Resource::orderBy('name')
            ->when($this->scopedNodeIds() !== null, function ($query) {
                $query->whereIn('governance_node_id', $this->scopedNodeIds())
                    ->whereNotNull('governance_node_id');
            })
            ->get();

        return view('procurement.forms.create', compact('resources'));
    }

    /**
     * Store new procurement form
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'resource_id' => 'required|exists:myb_resources,id',
            'name'        => 'required|string|max:255',
            'applies_to'  => 'required|in:submission,prescreening,technical,financial',
        ]);

        $resource = Resource::findOrFail($data['resource_id']);
        $this->assertResourceInScope($resource);

        $data['created_by'] = auth()->id();
        $data['status']     = 'draft';
        $data['is_active']  = 0; // only active AFTER approval

        $form = DynamicForm::create($data);
        $form->ensureGlobalFields();

        $message = 'Procurement form created. Default Name and Email fields were added automatically.';

        return redirect()
            ->route('forms.edit', $form->id)
            ->with('success', $message);
    }

    /**
     * Edit form + manage fields
     */
    public function edit(DynamicForm $form)
    {
        $this->assertFormInScope($form);
        $form->ensureGlobalFields();
        $form->load('fields')->loadCount('submissions');
        $resources = Resource::orderBy('name')
            ->when($this->scopedNodeIds() !== null, function ($query) {
                $query->whereIn('governance_node_id', $this->scopedNodeIds())
                    ->whereNotNull('governance_node_id');
            })
            ->get();

        return view('procurement.forms.edit', compact('form', 'resources'));
    }

    /**
     * Submit form for approval
     */
    public function submit(DynamicForm $form)
    {
        $this->assertFormInScope($form);
        if (!in_array($form->status, ['draft', 'rejected'], true)) {
            return back()->with('error', 'Only draft or rejected forms can be submitted.');
        }

        if ($form->hasSubmissions()) {
            return back()->with('error', 'This form already has submissions and cannot be resubmitted.');
        }

        if ($form->fields()->count() === 0) {
            return back()->with('error', 'You must add at least one field before submitting.');
        }

        $form->update([
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);

        return back()->with('success', 'Form submitted for approval.');
    }

    /**
     * Approve a submitted form
     */
    public function approve(DynamicForm $form)
    {
        $this->assertFormInScope($form);
        if ($form->status !== 'submitted') {
            return back()->with('error', 'Only submitted forms can be approved.');
        }

        $form->update([
            'status'      => 'approved',
            'is_active'   => 1,
            'approved_at'=> now(),
            'approved_by'=> auth()->id(),
        ]);

        return back()->with('success', 'Form approved and activated successfully.');
    }

    /**
     * Reject a submitted form
     */
    public function reject(Request $request, DynamicForm $form)
    {
        $this->assertFormInScope($form);
        if ($form->status !== 'submitted') {
            return back()->with('error', 'Only submitted forms can be rejected.');
        }

        $request->validate([
            'rejection_reason' => 'required|string|min:5',
        ]);

        $form->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->rejection_reason,
            'is_active'        => 0,
        ]);

        return back()->with('success', 'Form rejected and returned for correction.');
    }

    public function destroy(DynamicForm $form)
    {
        $this->assertFormInScope($form);

        if ($form->hasSubmissions()) {
            return back()->with('error', 'This form already has submissions and cannot be deleted.');
        }

        ProcurementFormAssignment::where('form_id', $form->id)->delete();
        $form->fields()->delete();
        $form->delete();

        return redirect()
            ->route('forms.index')
            ->with('success', 'Form deleted successfully.');
    }


    /**
     * Attach a dynamic form to a procurement
     */
    public function attachForm(Request $request)
    {
        $request->validate([
            'form_id'        => 'required|exists:dynamic_forms,id',
            'procurement_id' => 'required|exists:procurements,id',
        ]);

        $form = DynamicForm::findOrFail($request->form_id);
        $this->assertFormInScope($form);
        $procurement = Procurement::findOrFail($request->procurement_id);
        $this->assertProcurementInScope($procurement);
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

    private function assertFormInScope(DynamicForm $form): void
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        $nodeId = $form->resource?->governance_node_id;
        if (!$nodeId || !in_array($nodeId, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this form.');
        }
    }
}
