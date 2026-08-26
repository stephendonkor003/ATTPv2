<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\ProcurementPlan;
use App\Models\Resource;
use App\Models\DynamicForm;
use App\Models\User;
use App\Mail\VendorProcurementInvitation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Procurement\Concerns\GovernanceScope;

class ProcurementController extends Controller
{
    use GovernanceScope;

    private const DELETE_BLOCKING_RELATIONS = [
        'attp_think_tank_procurement_items' => 'a linked think tank procurement plan item',
        'attp_think_tank_procurement_reviews' => 'think tank procurement reviews',
        'evaluations' => 'procurement evaluation forms',
        'evaluation_submissions' => 'submitted evaluations',
        'form_submissions' => 'vendor submissions',
        'procurement_contract_negotiations' => 'contract negotiations',
        'procurement_deliverables' => 'procurement deliverables',
        'procurement_disbursements' => 'procurement disbursements',
        'procurement_invoices' => 'procurement invoices',
        'procurement_purchase_orders' => 'purchase orders',
        'site_visits' => 'site visits',
        'vendor_information_requests' => 'vendor information requests',
        'vendor_messages' => 'vendor messages',
        'vendor_purchase_requests' => 'vendor purchase requests',
        'vendor_reports' => 'vendor reports',
    ];

    private const DELETE_SETUP_TABLES = [
        'evaluation_assignments',
        'prescreening_assignments',
        'prescreening_template_procurements',
        'procurement_evaluations',
        'procurement_form_assignments',
        'procurement_form_maps',
        'procurement_user_permissions',
    ];

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
            Rule::exists('myb_procurement_plans', 'procurement_code'),
            Rule::unique('procurements', 'reference_no'),
        ],
        'estimated_budget'  => 'nullable|numeric',
        'documents' => 'nullable|array|max:20',
        'documents.*.name' => 'required_with:documents.*.file|nullable|string|max:255',
        'documents.*.file' => [
            'required_with:documents.*.name',
            'file',
            'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,jpg,jpeg,png,zip',
            'max:20480',
        ],
    ], [
        'documents.*.name.required_with' => 'Enter a document name for every uploaded file.',
        'documents.*.file.required_with' => 'Choose a file for every procurement document name.',
        'documents.*.file.mimes' => 'Procurement documents must be PDF, Office, CSV, text, image, or ZIP files.',
        'documents.*.file.max' => 'Each procurement document must not exceed 20 MB.',
    ]);

    $startDate = \Carbon\Carbon::parse($data['application_start_date']);
    $data['application_end_date'] = $startDate->copy()
        ->addDays((int) $data['application_duration_days'])
        ->format('Y-m-d');

    $resource = Resource::findOrFail($data['resource_id']);
    $this->assertResourceInScope($resource);

    if (! empty($data['reference_no'])) {
        $plan = ProcurementPlan::where('procurement_code', $data['reference_no'])->firstOrFail();
        $this->assertProcurementPlanInScope($plan);

        if (
            $plan->governance_node_id
            && $resource->governance_node_id
            && (string) $plan->governance_node_id !== (string) $resource->governance_node_id
        ) {
            return back()
                ->withErrors(['reference_no' => 'Selected procurement plan item belongs to a different portfolio than the selected resource.'])
                ->withInput();
        }
    }

    $data['created_by'] = auth()->id();
    $data['status']     = 'draft';
    $data['governance_node_id'] = $resource->governance_node_id;
    if (($data['visibility_type'] ?? 'public') !== 'vendor_group') {
        $data['vendor_categories'] = null;
    }

    $documentRows = $data['documents'] ?? [];
    unset($data['documents']);

    $storedPaths = [];

    try {
        DB::transaction(function () use ($data, $documentRows, &$storedPaths) {
            $procurement = Procurement::create($data);

            foreach ($documentRows as $documentRow) {
                $file = $documentRow['file'] ?? null;
                if (! $file) {
                    continue;
                }

                $path = $file->store("procurements/{$procurement->id}/documents", 'local');
                if (! $path) {
                    throw new \RuntimeException('A procurement document could not be stored.');
                }

                $storedPaths[] = $path;

                $procurement->documents()->create([
                    'document_name' => trim((string) $documentRow['name']),
                    'original_name' => basename((string) $file->getClientOriginalName()),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => (int) $file->getSize(),
                    'uploaded_by' => auth()->id(),
                ]);
            }
        });
    } catch (\Throwable $e) {
        foreach ($storedPaths as $storedPath) {
            Storage::disk('local')->delete($storedPath);
        }

        Log::error('Procurement creation failed.', [
            'user_id' => auth()->id(),
            'exception' => $e,
        ]);

        return back()
            ->withInput()
            ->with('error', 'The procurement could not be created. No files were retained; please try again.');
    }

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
            'documents.uploader',
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

    public function downloadDocument(Procurement $procurement, ProcurementDocument $document)
    {
        $this->assertProcurementInScope($procurement);

        return $this->documentDownloadResponse($procurement, $document);
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

    public function destroy(Request $request, Procurement $procurement)
    {
        abort_unless(
            $request->user()?->isAdmin(),
            403,
            'Only System Admin users can delete procurements.'
        );

        $this->assertProcurementInScope($procurement);

        try {
            $deletionError = DB::transaction(function () use ($procurement): ?string {
                $lockedProcurement = Procurement::query()
                    ->whereKey($procurement->getKey())
                    ->lockForUpdate()
                    ->first();

                if (! $lockedProcurement) {
                    return 'This procurement no longer exists.';
                }

                if ($lockedProcurement->status !== 'draft') {
                    return 'Only draft procurements can be deleted.';
                }

                if ($this->procurementHasPublicationHistory($lockedProcurement)) {
                    return 'A procurement that has been published must be retained for audit history.';
                }

                if (
                    filled($lockedProcurement->awarded_submission_id)
                    || filled($lockedProcurement->awarded_vendor_id)
                    || filled($lockedProcurement->awarded_at)
                ) {
                    return 'An awarded procurement must be retained for audit history.';
                }

                if ($dependency = $this->procurementDeletionDependency($lockedProcurement)) {
                    return "This procurement cannot be deleted because it has {$dependency}.";
                }

                DB::table('dynamic_forms')
                    ->where('procurement_id', $lockedProcurement->getKey())
                    ->update([
                        'procurement_id' => null,
                        'updated_at' => now(),
                    ]);

                foreach (self::DELETE_SETUP_TABLES as $table) {
                    DB::table($table)
                        ->where('procurement_id', $lockedProcurement->getKey())
                        ->delete();
                }

                $lockedProcurement->delete();

                return null;
            });
        } catch (\Throwable $exception) {
            Log::error('Procurement deletion failed.', [
                'procurement_id' => $procurement->getKey(),
                'user_id' => $request->user()?->getKey(),
                'exception' => $exception,
            ]);

            return back()->with(
                'error',
                'The procurement could not be deleted. No records or files were removed.'
            );
        }

        if ($deletionError) {
            return back()->with('error', $deletionError);
        }

        return redirect()
            ->route('procurements.index')
            ->with('success', 'Procurement deleted successfully.');
    }

    private function procurementDeletionDependency(Procurement $procurement): ?string
    {
        foreach (self::DELETE_BLOCKING_RELATIONS as $table => $description) {
            if (
                DB::table($table)
                    ->where('procurement_id', $procurement->getKey())
                    ->exists()
            ) {
                return $description;
            }
        }

        return null;
    }

    private function procurementHasPublicationHistory(Procurement $procurement): bool
    {
        if (
            (int) $procurement->publication_version > 1
            || filled($procurement->recalled_at)
            || filled($procurement->republished_at)
        ) {
            return true;
        }

        if (
            filled($procurement->reference_no)
            && ProcurementPlan::query()
                ->where('procurement_code', $procurement->reference_no)
                ->where(function ($query): void {
                    $query->where('is_launched', true)
                        ->orWhereNotNull('launched_at');
                })
                ->exists()
        ) {
            return true;
        }

        if (
            DB::table('procurement_audit_logs')
                ->where('procurement_id', $procurement->getKey())
                ->whereRaw('LOWER(action) LIKE ?', ['%publish%'])
                ->exists()
        ) {
            return true;
        }

        return DB::table('system_audit_logs')
            ->where('payload->table', $procurement->getTable())
            ->where('payload->id', (string) $procurement->getKey())
            ->whereIn('payload->changes->status', ['published', 'closed', 'awarded'])
            ->exists();
    }

    private function documentDownloadResponse(Procurement $procurement, ProcurementDocument $document)
    {
        abort_unless((string) $document->procurement_id === (string) $procurement->id, 404);

        $path = (string) $document->file_path;
        $expectedPrefix = "procurements/{$procurement->id}/documents/";
        abort_unless($path !== '' && str_starts_with($path, $expectedPrefix), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404, 'Procurement document file not found.');

        return $disk->download(
            $path,
            basename($document->original_name ?: $path),
            [
                'Content-Type' => $document->mime_type ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, max-age=0',
            ]
        );
    }
}
