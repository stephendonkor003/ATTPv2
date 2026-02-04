<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Procurement;
use App\Models\Resource;
use App\Models\DynamicForm;
use App\Models\User;
use App\Mail\VendorProcurementInvitation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Procurement\Concerns\GovernanceScope;

class ProcurementController extends Controller
{
    use GovernanceScope;

    /**
     * List all procurements
     */
    public function index()
{
    $scopedNodeIds = $this->scopedNodeIds();
    if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
        abort(403, 'You do not have access to procurements.');
    }

    $procurements = $this->applyProcurementScope(
        Procurement::withCount('forms')
    )
        ->orderByDesc('created_at')
        ->paginate(10); // ✅ FIX

    return view('procurement.index', compact('procurements'));
}


    /**
     * Show create procurement form
     */
    public function create()
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to create procurements.');
        }

        $resources = Resource::orderBy('name')
            ->when($this->scopedNodeIds() !== null, function ($query) {
                $query->whereIn('governance_node_id', $this->scopedNodeIds())
                    ->whereNotNull('governance_node_id');
            })
            ->get();

        $vendorCategories = \App\Models\VendorCategory::where('is_active', true)
            ->orderBy('name')
            ->pluck('name');

        return view('procurement.create', compact('resources', 'vendorCategories'));
    }

    /**
     * Store procurement
     */
  public function store(Request $request)
{
    $data = $request->validate([
        'resource_id'       => 'required|exists:myb_resources,id',
        'title'             => 'required|string|max:255',
        'description'       => 'required|string',
        'fiscal_year'       => 'required|string|max:20',
        'application_start_date' => 'required|date',
        'application_duration_days' => 'required|integer|min:1|max:365',
        'visibility_type' => 'required|in:public,vendor_group',
        'vendor_categories' => 'required_if:visibility_type,vendor_group|array|min:1',
        'vendor_categories.*' => 'string|max:255',
        'reference_no'      => [
            'nullable',
            'string',
            'max:50',
            Rule::exists('myb_procurement_plans', 'procurement_code')
                ->where(function ($query) {
                    if (!auth()->user()->can('procurement.view_all')) {
                        $query->where('created_by', auth()->id());
                    }
                }),
            Rule::unique('procurements', 'reference_no'),
        ],
        'estimated_budget'  => 'nullable|numeric',
    ]);

    $startDate = \Carbon\Carbon::parse($data['application_start_date']);
    $data['application_end_date'] = $startDate->copy()
        ->addDays((int) $data['application_duration_days'])
        ->format('Y-m-d');

    $resource = Resource::findOrFail($data['resource_id']);
    $this->assertResourceInScope($resource);

    $data['created_by'] = auth()->id();
    $data['status']     = 'draft';
    $data['governance_node_id'] = $resource->governance_node_id;
    if (($data['visibility_type'] ?? 'public') !== 'vendor_group') {
        $data['vendor_categories'] = null;
    }

    Procurement::create($data);

    return redirect()
        ->route('procurements.index')
        ->with('success', 'Procurement created successfully.');
}


    /**
     * Show procurement details
     */
    public function show(Procurement $procurement)
    {
        $this->assertProcurementInScope($procurement);
        $procurement->load([
            'resource',
            'forms.resource',
            'forms.creator',
        ]);

        $availableForms = DynamicForm::approved()
            ->whereNull('procurement_id')
            ->when($procurement->governance_node_id, function ($query) use ($procurement) {
                $query->whereHas('resource', function ($res) use ($procurement) {
                    $res->where('governance_node_id', $procurement->governance_node_id);
                });
            })
            ->orderBy('name')
            ->get();

        $vendorCategories = \App\Models\VendorCategory::where('is_active', true)
            ->orderBy('name')
            ->pluck('name');

        return view('procurement.show', compact('procurement', 'availableForms', 'vendorCategories'));
    }

    /**
     * Attach a dynamic form to a procurement
     * (ONE FORM → ONE PROCUREMENT)
     */
    public function attachForm(Request $request)
    {
        $request->validate([
            'form_id'        => 'required|exists:dynamic_forms,id',
            'procurement_id' => 'required|exists:procurements,id',
        ]);

        $form = DynamicForm::findOrFail($request->form_id);
        $procurement = Procurement::findOrFail($request->procurement_id);
        $this->assertProcurementInScope($procurement);
        if ($procurement->governance_node_id && $form->resource?->governance_node_id !== $procurement->governance_node_id) {
            abort(403, 'You do not have access to attach this form to the selected procurement.');
        }

        // ❗ Prevent re-attaching
        if ($form->procurement_id !== null) {
            return back()->with(
                'error',
                'This form is already attached to a procurement.'
            );
        }

        $form->update([
            'procurement_id' => $request->procurement_id,
        ]);

        return back()->with(
            'success',
            'Form successfully attached to the procurement.'
        );
    }

    public function notifyVendors(Request $request, Procurement $procurement)
    {
        $request->validate([
            'vendor_category' => 'nullable|string|max:255|exists:vendor_categories,name',
            'message' => 'nullable|string|max:1000',
        ]);

        $vendorsQuery = User::where('user_type', 'vendor')
            ->where('is_disabled', false)
            ->where('is_blacklisted', false);

        if ($request->filled('vendor_category')) {
            $vendorsQuery->where('vendor_category', $request->input('vendor_category'));
        }

        $vendors = $vendorsQuery->get();

        if ($vendors->isEmpty()) {
            return back()->with('error', 'No vendors found for the selected category.');
        }

        foreach ($vendors as $vendor) {
            Mail::to($vendor->email)->send(
                new VendorProcurementInvitation($procurement, $vendor, $request->input('message'))
            );
        }

        return back()->with('success', "Notification sent to {$vendors->count()} vendors.");
    }
}
