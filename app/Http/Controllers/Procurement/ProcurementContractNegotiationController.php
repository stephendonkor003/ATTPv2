<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Procurement\Concerns\GovernanceScope;
use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\ProcurementContractDocument;
use App\Models\ProcurementContractNegotiation;
use App\Models\ProcurementAuditLog;
use App\Mail\VendorContractTerminated;
use App\Notifications\VendorContractTerminatedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ProcurementContractNegotiationController extends Controller
{
    use GovernanceScope;

    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner', 'permission:forms.manage']);
    }

    public function index()
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to procurements.');
        }

        $procurements = $this->applyProcurementScope(
            Procurement::with('resource')
                ->withCount([
                    'submissions',
                    'contractNegotiations',
                    'contractNegotiations as agreed_negotiations_count' => function ($query) {
                        $query->where('status', 'agreed');
                    },
                ])
        )
            ->orderByDesc('created_at')
            ->paginate(12);

        return view('procurement.contract-negotiations.index', compact('procurements'));
    }

    public function show(Procurement $procurement)
    {
        $this->assertProcurementInScope($procurement);

        $submissions = $procurement->submissions()
            ->with('submitter')
            ->orderByDesc('submitted_at')
            ->get();

        $negotiations = $procurement->contractNegotiations()
            ->with(['submission.submitter', 'documents'])
            ->orderByDesc('created_at')
            ->get();

        return view('procurement.contract-negotiations.show', compact('procurement', 'submissions', 'negotiations'));
    }

    public function store(Request $request, Procurement $procurement)
    {
        $this->assertProcurementInScope($procurement);

        $data = $request->validate([
            'submission_id' => 'required|exists:form_submissions,id',
            'proposed_amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:5000',
            'documents' => 'nullable|array',
            'documents.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:10240',
        ]);

        $submission = FormSubmission::with('submitter')
            ->where('procurement_id', $procurement->id)
            ->findOrFail($data['submission_id']);

        $vendor = $submission->submitter;
        if (!$vendor || $vendor->user_type !== 'vendor') {
            return back()->withErrors([
                'submission_id' => 'The selected submission does not belong to a vendor.',
            ])->withInput();
        }

        $existing = ProcurementContractNegotiation::where('procurement_id', $procurement->id)
            ->where('submission_id', $submission->id)
            ->whereNotIn('status', ['cancelled'])
            ->first();

        if ($existing) {
            return back()->withErrors([
                'submission_id' => 'A negotiation already exists for this vendor.',
            ])->withInput();
        }

        $negotiation = ProcurementContractNegotiation::create([
            'procurement_id' => $procurement->id,
            'submission_id' => $submission->id,
            'vendor_id' => $vendor->id,
            'proposed_amount' => $data['proposed_amount'],
            'status' => 'in_progress',
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        if ($request->hasFile('documents')) {
            $this->saveDocuments($request->file('documents', []), $negotiation);
        }

        return back()->with('success', 'Contract negotiation started successfully.');
    }

    public function agree(
        Request $request,
        Procurement $procurement,
        ProcurementContractNegotiation $negotiation
    ) {
        $this->assertProcurementInScope($procurement);
        $this->assertNegotiationMatches($procurement, $negotiation);

        if ($procurement->status === 'awarded') {
            return back()->with('error', 'This procurement has already been awarded.');
        }

        if ($negotiation->status === 'cancelled') {
            return back()->with('error', 'Cancelled negotiations cannot be approved.');
        }

        if ($negotiation->status === 'terminated') {
            return back()->with('error', 'Terminated negotiations cannot be approved.');
        }

        $data = $request->validate([
            'agreed_amount' => 'required|numeric|min:0.01',
        ]);

        $negotiation->update([
            'agreed_amount' => $data['agreed_amount'],
            'status' => 'agreed',
            'agreed_at' => now(),
        ]);

        $procurement->contractNegotiations()
            ->where('id', '!=', $negotiation->id)
            ->where('status', '!=', 'agreed')
            ->where('status', '!=', 'terminated')
            ->update(['status' => 'cancelled']);

        return back()->with(
            'success',
            'Negotiation approved. Vendor can now submit invoices after award.'
        );
    }

    public function terminate(Request $request, Procurement $procurement, ProcurementContractNegotiation $negotiation)
    {
        $this->assertProcurementInScope($procurement);
        $this->assertNegotiationMatches($procurement, $negotiation);

        if ($procurement->status !== 'awarded') {
            return back()->with('error', 'Only awarded procurements can be terminated.');
        }

        if ($procurement->awarded_submission_id && $negotiation->submission_id !== $procurement->awarded_submission_id) {
            return back()->with('error', 'Only the awarded vendor contract can be terminated.');
        }

        if ($negotiation->status === 'terminated') {
            return back()->with('error', 'This contract is already terminated.');
        }

        $data = $request->validate([
            'termination_reason' => 'required|string|max:2000',
        ]);

        $negotiation->update([
            'status' => 'terminated',
            'termination_reason' => $data['termination_reason'],
            'terminated_by' => auth()->id(),
            'terminated_at' => now(),
        ]);

        $procurement->update([
            'status' => 'closed',
            'awarded_submission_id' => null,
            'awarded_vendor_id' => null,
            'awarded_at' => null,
        ]);

        ProcurementAuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'Terminated procurement contract',
            'procurement_id' => $procurement->id,
            'submission_id' => $negotiation->submission_id,
            'metadata' => [
                'negotiation_id' => $negotiation->id,
                'reason' => $data['termination_reason'],
            ],
            'created_at' => now(),
        ]);

        $vendor = $negotiation->vendor ?? $negotiation->submission?->submitter;
        if ($vendor && !empty($vendor->email)) {
            $mail = new VendorContractTerminated($procurement, $vendor, $data['termination_reason']);

            try {
                Mail::to($vendor->email)->send($mail);
            } catch (\Throwable $exception) {
                logger()->error('Termination email failed.', [
                    'procurement_id' => $procurement->id,
                    'vendor_id' => $vendor->id,
                    'error' => $exception->getMessage(),
                ]);
            }

            try {
                $vendor->notify(new VendorContractTerminatedNotification($procurement, $data['termination_reason']));
            } catch (\Throwable $exception) {
                logger()->error('Termination notification failed.', [
                    'procurement_id' => $procurement->id,
                    'vendor_id' => $vendor->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return back()->with('success', 'Contract terminated and vendor notified. Procurement is reopened for re-award.');
    }

    public function storeDocuments(
        Request $request,
        Procurement $procurement,
        ProcurementContractNegotiation $negotiation
    ) {
        $this->assertProcurementInScope($procurement);
        $this->assertNegotiationMatches($procurement, $negotiation);

        $request->validate([
            'documents' => 'required|array|min:1',
            'documents.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:10240',
        ]);
        $this->saveDocuments($request->file('documents', []), $negotiation);

        return back()->with('success', 'Documents uploaded successfully.');
    }

    public function downloadDocument(
        Request $request,
        Procurement $procurement,
        ProcurementContractNegotiation $negotiation,
        ProcurementContractDocument $document
    ) {
        $this->assertProcurementInScope($procurement);
        $this->assertNegotiationMatches($procurement, $negotiation);

        if ($document->negotiation_id !== $negotiation->id) {
            abort(404);
        }

        $path = (string) ($document->file_path ?? '');
        abort_if($path === '', 404, 'Document not found.');

        $privateDisk = Storage::disk('local');

        if (!$privateDisk->exists($path) && Storage::disk('public')->exists($path)) {
            $stream = Storage::disk('public')->readStream($path);
            if ($stream !== false) {
                $privateDisk->writeStream($path, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                Storage::disk('public')->delete($path);
            }
        }

        abort_unless($privateDisk->exists($path), 404, 'Document file missing on disk.');

        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($request->boolean('download')) {
            return $privateDisk->download($path, $document->file_name ?? basename($path), $headers);
        }

        return $privateDisk->response($path, null, $headers);
    }

    private function assertNegotiationMatches(Procurement $procurement, ProcurementContractNegotiation $negotiation): void
    {
        if ($negotiation->procurement_id !== $procurement->id) {
            abort(404);
        }
    }

    private function saveDocuments(array $files, ProcurementContractNegotiation $negotiation): void
    {
        foreach ($files as $file) {
            $path = $file->store('procurement_contracts');

            ProcurementContractDocument::create([
                'negotiation_id' => $negotiation->id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => auth()->id(),
            ]);
        }
    }
}
