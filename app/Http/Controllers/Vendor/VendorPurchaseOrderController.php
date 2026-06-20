<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\ProcurementPurchaseOrder;
use App\Models\ProcurementPurchaseOrderItemEvidence;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class VendorPurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $vendor = $this->vendor($request);

        $status = $request->query('status');

        $purchaseOrders = ProcurementPurchaseOrder::with([
                'procurement',
                'vendor',
                'lineItemEvidence',
                'disbursements',
                'purchaseRequest.items.resourceCategory',
                'purchaseRequest.items.resource',
                'purchaseRequest.items.deliverable',
                'budgetCommitment.purchaseRequest.items.resourceCategory',
                'budgetCommitment.purchaseRequest.items.resource',
                'budgetCommitment.purchaseRequest.items.deliverable',
            ])
            ->where('vendor_id', $vendor->id)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByDesc('issued_at')
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        $stats = ProcurementPurchaseOrder::query()
            ->where('vendor_id', $vendor->id)
            ->selectRaw("COUNT(*) as total")
            ->selectRaw("SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft")
            ->selectRaw("SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) as issued")
            ->selectRaw("SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed")
            ->selectRaw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled")
            ->first();

        return view('vendor.purchase-orders.index', [
            'purchaseOrders' => $purchaseOrders,
            'stats' => [
                'total' => (int) ($stats->total ?? 0),
                'draft' => (int) ($stats->draft ?? 0),
                'issued' => (int) ($stats->issued ?? 0),
                'closed' => (int) ($stats->closed ?? 0),
                'cancelled' => (int) ($stats->cancelled ?? 0),
            ],
            'status' => $status,
        ]);
    }

    public function show(Request $request, ProcurementPurchaseOrder $purchaseOrder)
    {
        $vendor = $this->vendor($request);
        $this->assertPurchaseOrderOwnership($purchaseOrder, $vendor);

        $this->loadPurchaseOrderDetail($purchaseOrder);

        return view('vendor.purchase-orders.show', [
            'purchaseOrder' => $purchaseOrder,
            'sourcePurchaseRequest' => $purchaseOrder->sourcePurchaseRequest(),
        ]);
    }

    public function pdf(Request $request, ProcurementPurchaseOrder $purchaseOrder)
    {
        $vendor = $this->vendor($request);
        $this->assertPurchaseOrderOwnership($purchaseOrder, $vendor);
        $this->loadPurchaseOrderDetail($purchaseOrder);

        $pdf = Pdf::loadView('procurement.purchase-orders.pdf', [
            'purchaseOrder' => $purchaseOrder,
        ]);

        return $pdf->stream('purchase-order-' . ($purchaseOrder->reference_no ?? 'draft') . '.pdf');
    }

    public function download(Request $request, ProcurementPurchaseOrder $purchaseOrder)
    {
        $vendor = $this->vendor($request);
        $this->assertPurchaseOrderOwnership($purchaseOrder, $vendor);
        $this->loadPurchaseOrderDetail($purchaseOrder);

        $pdf = Pdf::loadView('procurement.purchase-orders.pdf', [
            'purchaseOrder' => $purchaseOrder,
        ]);

        return $pdf->download('purchase-order-' . ($purchaseOrder->reference_no ?? 'draft') . '.pdf');
    }

    public function storeEvidence(Request $request, ProcurementPurchaseOrder $purchaseOrder, PurchaseRequestItem $item)
    {
        $vendor = $this->vendor($request);
        $this->assertPurchaseOrderOwnership($purchaseOrder, $vendor);
        $this->assertItemBelongsToPurchaseOrder($purchaseOrder, $item);

        abort_if(strtolower((string) $purchaseOrder->status) === 'cancelled', 403, 'This purchase order is not open for evidence uploads.');

        $data = $request->validate([
            'deliverable_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'document_names' => ['nullable', 'array', 'max:20'],
            'document_names.*' => ['nullable', 'string', 'max:255'],
            'documents' => ['required', 'array', 'min:1', 'max:20'],
            'documents.*' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,zip', 'max:20480'],
        ], [
            'documents.required' => 'Upload at least one evidence document.',
            'documents.*.mimes' => 'Evidence documents must be PDF, Office, image, or ZIP files.',
        ]);

        $evidence = ProcurementPurchaseOrderItemEvidence::firstOrNew([
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_request_item_id' => $item->id,
        ]);

        if ($evidence->exists && $evidence->is_met) {
            throw ValidationException::withMessages([
                'documents' => 'This evidence has already been verified by ATTP and cannot be changed from the vendor portal.',
            ]);
        }

        if ($evidence->exists && $evidence->isLockedForVendorUpload()) {
            throw ValidationException::withMessages([
                'documents' => 'This evidence has already been submitted and is awaiting internal verification. You can only upload again if ATTP requests a resubmission.',
            ]);
        }

        $documents = collect($evidence->documents ?? [])
            ->filter(fn ($document) => is_array($document))
            ->values()
            ->all();
        $documentNames = $data['document_names'] ?? [];

        foreach ($request->file('documents', []) as $index => $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $displayName = trim((string) ($documentNames[$index] ?? ''));
            $path = $file->store("procurement_purchase_orders/{$purchaseOrder->id}/line-item-evidence/{$item->id}");

            $documents[] = [
                'path' => $path,
                'name' => $file->getClientOriginalName(),
                'display_name' => $displayName !== '' ? $displayName : null,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'source' => 'vendor',
                'uploaded_by' => $vendor->id,
                'uploaded_by_name' => $vendor->name,
                'uploaded_at' => now()->toIso8601String(),
            ];
        }

        if (empty($documents)) {
            throw ValidationException::withMessages([
                'documents' => 'No valid evidence documents were uploaded.',
            ]);
        }

        $evidence->fill([
            'deliverable_id' => $item->deliverable_id,
            'is_met' => (bool) ($evidence->is_met ?? false),
            'deliverable_date' => $data['deliverable_date'] ?? $evidence->deliverable_date,
            'delivered_unit_price' => $evidence->delivered_unit_price,
            'delivered_quantity' => $evidence->delivered_quantity,
            'delivered_amount' => $evidence->delivered_amount,
            'notes' => $this->appendVendorNote($evidence->notes, $data['notes'] ?? null, $vendor),
            'documents' => $documents,
            'vendor_submission_status' => ProcurementPurchaseOrderItemEvidence::VENDOR_STATUS_SUBMITTED,
            'vendor_submitted_at' => now(),
            'vendor_resubmission_requested_at' => null,
            'vendor_resubmission_requested_by' => null,
            'vendor_resubmission_note' => null,
            'created_by' => $evidence->created_by ?: $vendor->id,
        ]);
        $evidence->save();

        return redirect()
            ->route('vendor.purchase-orders.show', $purchaseOrder)
            ->with('success', 'Evidence uploaded to the purchase order deliverable.');
    }

    public function downloadEvidenceDocument(
        Request $request,
        ProcurementPurchaseOrder $purchaseOrder,
        ProcurementPurchaseOrderItemEvidence $evidence,
        int $document
    ) {
        $vendor = $this->vendor($request);
        $this->assertPurchaseOrderOwnership($purchaseOrder, $vendor);

        abort_unless((string) $evidence->purchase_order_id === (string) $purchaseOrder->id, 404);

        $documents = $evidence->documents ?? [];
        $file = $documents[$document] ?? null;
        abort_unless(is_array($file) && !empty($file['path']), 404, 'Evidence document not found.');

        $privateDisk = Storage::disk('local');
        abort_unless($privateDisk->exists($file['path']), 404, 'Evidence document file missing on disk.');

        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];

        $fileName = ($file['display_name'] ?? null) ?: ($file['name'] ?? basename($file['path']));

        if ($request->boolean('download')) {
            return $privateDisk->download($file['path'], $fileName, $headers);
        }

        return $privateDisk->response($file['path'], $fileName, $headers);
    }

    private function vendor(Request $request): User
    {
        $user = $request->user();

        abort_unless($user && $user->user_type === 'vendor', 403, 'Only vendors can access this page.');
        abort_if((bool) ($user->is_disabled ?? false), 403, 'Your vendor account has been disabled. Please contact the administrator.');

        return $user;
    }

    private function assertPurchaseOrderOwnership(ProcurementPurchaseOrder $purchaseOrder, User $vendor): void
    {
        abort_unless((string) $purchaseOrder->vendor_id === (string) $vendor->id, 403, 'You do not have access to this purchase order.');
    }

    private function loadPurchaseOrderDetail(ProcurementPurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->load([
            'procurement',
            'vendor',
            'subActivity',
            'negotiation',
            'invoice',
            'deliverables',
            'lineItemEvidence.deliverable',
            'disbursements',
            'purchaseRequest.items.resourceCategory',
            'purchaseRequest.items.resource',
            'purchaseRequest.items.deliverable',
            'purchaseRequest.programFunding.program',
            'purchaseRequest.governanceNode',
            'budgetCommitment.purchaseRequest.items.resourceCategory',
            'budgetCommitment.purchaseRequest.items.resource',
            'budgetCommitment.purchaseRequest.items.deliverable',
            'budgetCommitment.purchaseRequest.programFunding.program',
        ]);
    }

    private function assertItemBelongsToPurchaseOrder(ProcurementPurchaseOrder $purchaseOrder, PurchaseRequestItem $item): void
    {
        $sourcePurchaseRequest = $purchaseOrder->sourcePurchaseRequest();

        abort_unless($sourcePurchaseRequest, 404, 'Purchase order source request not found.');
        abort_unless((string) $item->purchase_request_id === (string) $sourcePurchaseRequest->id, 404, 'This deliverable is not linked to the purchase order.');
    }

    private function appendVendorNote(?string $currentNotes, ?string $newNote, User $vendor): ?string
    {
        $newNote = trim((string) $newNote);

        if ($newNote === '') {
            return $currentNotes;
        }

        $entry = 'Vendor note from ' . ($vendor->name ?: 'Vendor') . ' on ' . now()->format('M d, Y H:i') . ': ' . $newNote;

        return trim(collect([$currentNotes, $entry])->filter()->join("\n\n"));
    }
}
