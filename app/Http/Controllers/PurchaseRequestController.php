<?php

namespace App\Http\Controllers;

use App\Mail\PurchaseRequestMail;
use App\Models\BudgetCommitment;
use App\Models\ProcurementPurchaseOrder;
use App\Models\ProcurementPurchaseOrderItemEvidence;
use App\Models\PurchaseRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class PurchaseRequestController extends Controller
{
    public function index()
    {
        $canViewAll = Auth::user()?->can('finance.purchase_requests.view_all') === true;
        $scopedNodeIds = $canViewAll ? null : $this->scopedNodeIds();
        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to purchase requests.');
        }

        $purchaseRequests = PurchaseRequest::with([
            'programFunding.program',
            'governanceNode',
            'subActivity',
            'commitments',
        ])
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->orderByDesc('created_at')
            ->get();

        $canApprovePurchaseRequests = Auth::user()?->can('finance.purchase_requests.approve') === true;
        $canEditPurchaseRequests = Auth::user()?->can('finance.commitments.edit') === true;
        $canDeletePurchaseRequests = Auth::user()?->can('finance.commitments.delete') === true;

        return view('finance.purchase-requests.index', compact(
            'purchaseRequests',
            'canViewAll',
            'canApprovePurchaseRequests',
            'canEditPurchaseRequests',
            'canDeletePurchaseRequests'
        ));
    }

    public function show(PurchaseRequest $purchaseRequest)
    {
        $this->assertPurchaseRequestInScope($purchaseRequest);

        $purchaseRequest->load([
            'programFunding.program',
            'governanceNode',
            'subActivity',
            'items.resourceCategory',
            'items.resource',
            'items.deliverable.procurement',
            'commitments' => fn ($query) => $query->orderBy('commitment_year'),
            'creator',
            'approver',
            'rejector',
        ]);

        $yearSplits = $purchaseRequest->commitments
            ->groupBy('commitment_year')
            ->map(fn ($rows) => round((float) $rows->sum('commitment_amount'), 2))
            ->sortKeys();

        $canApprovePurchaseRequests = Auth::user()?->can('finance.purchase_requests.approve') === true;
        $canEditPurchaseRequests = Auth::user()?->can('finance.commitments.edit') === true;
        $canDeletePurchaseRequests = Auth::user()?->can('finance.commitments.delete') === true;
        $canManageLineItemEvidence = Auth::user()?->can('finance.purchase_orders.create') === true;
        $evidencePurchaseOrder = $this->purchaseRequestEvidencePurchaseOrder($purchaseRequest);
        $evidencePurchaseOrder?->loadMissing('lineItemEvidence');
        $lineItemEvidenceByItem = $evidencePurchaseOrder
            ? $evidencePurchaseOrder->lineItemEvidence->keyBy('purchase_request_item_id')
            : collect();

        return view('finance.purchase-requests.show', compact(
            'purchaseRequest',
            'yearSplits',
            'canApprovePurchaseRequests',
            'canEditPurchaseRequests',
            'canDeletePurchaseRequests',
            'canManageLineItemEvidence',
            'evidencePurchaseOrder',
            'lineItemEvidenceByItem'
        ));
    }

    public function pdf(PurchaseRequest $purchaseRequest)
    {
        $this->assertPurchaseRequestInScope($purchaseRequest);

        $purchaseRequest->load([
            'programFunding.program',
            'governanceNode',
            'subActivity',
            'items.resourceCategory',
            'items.resource',
            'items.deliverable.procurement',
            'commitments' => fn ($query) => $query->orderBy('commitment_year'),
            'creator',
        ]);

        $pdf = Pdf::loadView('finance.purchase-requests.pdf', [
            'purchaseRequest' => $purchaseRequest,
        ]);

        return $pdf->stream('purchase-request-' . $purchaseRequest->reference_no . '.pdf');
    }

    public function download(PurchaseRequest $purchaseRequest)
    {
        $this->assertPurchaseRequestInScope($purchaseRequest);

        $purchaseRequest->load([
            'programFunding.program',
            'governanceNode',
            'subActivity',
            'items.resourceCategory',
            'items.resource',
            'items.deliverable.procurement',
            'commitments' => fn ($query) => $query->orderBy('commitment_year'),
            'creator',
        ]);

        $pdf = Pdf::loadView('finance.purchase-requests.pdf', [
            'purchaseRequest' => $purchaseRequest,
        ]);

        return $pdf->download('purchase-request-' . $purchaseRequest->reference_no . '.pdf');
    }

    public function send(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->assertPurchaseRequestInScope($purchaseRequest);

        $validated = $request->validate([
            'recipient_name' => 'required|string|max:150',
            'recipient_email' => 'required|email|max:150',
        ]);

        try {
            Mail::to($validated['recipient_email'], $validated['recipient_name'])
                ->send(new PurchaseRequestMail($purchaseRequest, $validated['recipient_name']));
        } catch (\Throwable $e) {
            Log::error('Purchase request email failed', [
                'purchase_request_id' => $purchaseRequest->id,
                'recipient_email' => $validated['recipient_email'],
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'email' => 'Unable to send the purchase request email. Please try again.',
            ]);
        }

        return back()->with('success', 'Purchase request sent successfully.');
    }

    public function storeLineItemEvidence(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->assertPurchaseRequestInScope($purchaseRequest);

        $purchaseRequest->loadMissing([
            'items.deliverable',
            'commitments' => fn ($query) => $query->orderBy('commitment_year'),
        ]);

        $purchaseOrder = $this->purchaseRequestEvidencePurchaseOrder($purchaseRequest);
        if (! $purchaseOrder) {
            throw ValidationException::withMessages([
                'line_item_evidence' => 'Create a purchase order for this purchase request before adding deliverable evidence.',
            ]);
        }

        $data = $request->validate([
            'purchase_request_item_id' => ['required', 'exists:myb_purchase_request_items,id'],
            'is_met' => ['nullable', 'boolean'],
            'deliverable_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'document_names' => ['nullable', 'array', 'max:20'],
            'document_names.*' => ['nullable', 'string', 'max:255'],
            'documents' => ['nullable', 'array', 'max:20'],
            'documents.*' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip', 'max:20480'],
        ], [
            'documents.*.mimes' => 'Line item evidence must be a PDF, Office document, image, or ZIP file.',
        ]);

        $item = $purchaseRequest->items->firstWhere('id', $data['purchase_request_item_id']);
        if (! $item) {
            throw ValidationException::withMessages([
                'purchase_request_item_id' => 'The selected line item does not belong to this purchase request.',
            ]);
        }

        $evidence = ProcurementPurchaseOrderItemEvidence::firstOrNew([
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_request_item_id' => $item->id,
        ]);

        $documents = collect($evidence->documents ?? [])
            ->filter(fn ($document) => is_array($document))
            ->values()
            ->all();

        $documentNames = $data['document_names'] ?? [];
        foreach (($request->file('documents', []) ?? []) as $index => $file) {
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
                'uploaded_by' => Auth::id(),
                'uploaded_at' => now()->toIso8601String(),
            ];
        }

        $isMet = (bool) ($data['is_met'] ?? false);
        $deliverableDate = trim((string) ($data['deliverable_date'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? ''));

        if (! $isMet && $deliverableDate === '' && $notes === '' && empty($documents)) {
            if ($evidence->exists) {
                $evidence->delete();
            }

            return back()->with('success', 'Line item evidence cleared.');
        }

        $evidence->fill([
            'deliverable_id' => $item->deliverable_id,
            'is_met' => $isMet,
            'deliverable_date' => $deliverableDate !== '' ? $deliverableDate : null,
            'notes' => $notes !== '' ? $notes : null,
            'documents' => $documents,
            'created_by' => $evidence->created_by ?: Auth::id(),
        ]);
        $evidence->save();

        return back()->with('success', 'Line item deliverable evidence saved.');
    }

    public function edit(PurchaseRequest $purchaseRequest)
    {
        $this->assertPurchaseRequestInScope($purchaseRequest);

        if (!$this->purchaseRequestIsFullyDraft($purchaseRequest)) {
            return back()->withErrors([
                'status' => 'Only purchase requests with draft commitments can be edited.',
            ]);
        }

        $commitment = $purchaseRequest->commitments()
            ->where('status', BudgetCommitment::STATUS_DRAFT)
            ->orderBy('commitment_year')
            ->first();

        if (!$commitment) {
            return back()->withErrors([
                'status' => 'This purchase request has no editable draft commitment.',
            ]);
        }

        return redirect()->route('finance.commitments.edit', $commitment);
    }

    public function destroyInfo(PurchaseRequest $purchaseRequest): \Illuminate\Http\JsonResponse
    {
        $this->assertPurchaseRequestInScope($purchaseRequest);

        $purchaseRequest->load('commitments');
        $currency = $purchaseRequest->currency
            ?? $purchaseRequest->programFunding?->program?->currency
            ?? '';

        $canDelete   = true;
        $blockReason = null;

        if ($purchaseRequest->status === 'approved') {
            $canDelete   = false;
            $blockReason = 'Approved purchase requests cannot be deleted.';
        } elseif ($purchaseRequest->commitments->contains('status', BudgetCommitment::STATUS_APPROVED)) {
            $canDelete   = false;
            $blockReason = 'This purchase request has approved commitments and cannot be deleted.';
        }

        $commitmentIds = $purchaseRequest->commitments->pluck('id')->filter()->values();

        $chain = [[
            'type'         => 'purchase_request',
            'reference_no' => $purchaseRequest->reference_no,
            'status'       => $purchaseRequest->status,
            'item_count'   => $purchaseRequest->items()->count(),
            'total_amount' => number_format((float) $purchaseRequest->total_amount, 2),
            'currency'     => $currency,
            'commitment_count' => $purchaseRequest->commitments->count(),
        ]];

        $pos = ProcurementPurchaseOrder::query()
            ->where(function ($q) use ($purchaseRequest, $commitmentIds) {
                $q->where('purchase_request_id', $purchaseRequest->id);
                if ($commitmentIds->isNotEmpty()) {
                    $q->orWhereIn('budget_commitment_id', $commitmentIds);
                }
            })
            ->withCount('disbursements')
            ->get();

        foreach ($pos as $po) {
            $lockedStatuses = ['approved', 'completed'];
            if ($canDelete && in_array($po->status, $lockedStatuses, true)) {
                $canDelete   = false;
                $blockReason = 'A linked purchase order is in ' . $po->status . ' status and cannot be deleted.';
            }

            $chain[] = [
                'type'               => 'purchase_order',
                'reference_no'       => $po->reference_no ?? '—',
                'status'             => $po->status,
                'vendor'             => $po->vendor?->name ?? '—',
                'amount'             => number_format((float) $po->amount, 2),
                'currency'           => $po->currency ?? $currency,
                'disbursement_count' => $po->disbursements_count,
                'has_invoice'        => (bool) $po->invoice_id,
                'has_negotiation'    => (bool) $po->negotiation_id,
            ];
        }

        return response()->json([
            'can_delete'   => $canDelete,
            'block_reason' => $blockReason,
            'is_admin'     => Auth::user()?->isAdmin() ?? false,
            'summary' => [
                'reference_no' => $purchaseRequest->reference_no,
                'total_amount' => number_format((float) $purchaseRequest->total_amount, 2),
                'currency'     => $currency,
                'status'       => $purchaseRequest->status,
            ],
            'chain' => $chain,
        ]);
    }

    public function destroy(PurchaseRequest $purchaseRequest)
    {
        $this->assertPurchaseRequestInScope($purchaseRequest);

        $purchaseRequest->load('commitments');

        if ($purchaseRequest->status === 'approved') {
            return back()->with('error', 'Approved purchase requests cannot be deleted.');
        }

        if ($purchaseRequest->commitments->contains('status', BudgetCommitment::STATUS_APPROVED)) {
            return back()->with('error', 'This purchase request has approved commitments and cannot be deleted.');
        }

        $commitmentIds = $purchaseRequest->commitments->pluck('id')->filter()->values();

        DB::transaction(function () use ($purchaseRequest, $commitmentIds) {
            // Cascade-delete linked purchase orders and their sub-records
            $pos = ProcurementPurchaseOrder::query()
                ->where(function ($q) use ($purchaseRequest, $commitmentIds) {
                    $q->where('purchase_request_id', $purchaseRequest->id);
                    if ($commitmentIds->isNotEmpty()) {
                        $q->orWhereIn('budget_commitment_id', $commitmentIds);
                    }
                })->get();

            foreach ($pos as $po) {
                $this->deletePurchaseOrderCascade($po);
            }

            $purchaseRequest->commitments()->delete();
            $purchaseRequest->items()->delete();
            $purchaseRequest->delete();
        });

        return redirect()
            ->route('finance.purchase-requests.index')
            ->with('success', 'Purchase request and all linked records deleted.');
    }

    public function forceDestroy(PurchaseRequest $purchaseRequest)
    {
        $this->assertPurchaseRequestInScope($purchaseRequest);

        if (!Auth::user()?->isAdmin()) {
            abort(403, 'Only administrators can force-delete purchase requests.');
        }

        $purchaseRequest->load('commitments');
        $commitmentIds = $purchaseRequest->commitments->pluck('id')->filter()->values();

        DB::transaction(function () use ($purchaseRequest, $commitmentIds) {
            $pos = ProcurementPurchaseOrder::query()
                ->where(function ($q) use ($purchaseRequest, $commitmentIds) {
                    $q->where('purchase_request_id', $purchaseRequest->id);
                    if ($commitmentIds->isNotEmpty()) {
                        $q->orWhereIn('budget_commitment_id', $commitmentIds);
                    }
                })->get();

            foreach ($pos as $po) {
                $this->deletePurchaseOrderCascade($po);
            }

            $purchaseRequest->commitments()->delete();
            $purchaseRequest->items()->delete();
            $purchaseRequest->delete();
        });

        return redirect()
            ->route('finance.purchase-requests.index')
            ->with('success', 'Purchase request and all linked records have been force-deleted.');
    }

    private function deletePurchaseOrderCascade(ProcurementPurchaseOrder $po): void
    {
        $invoiceId     = $po->invoice_id;
        $negotiationId = $po->negotiation_id;

        $po->disbursements()->delete();
        $po->deliverables()->detach();

        $po->invoice_id     = null;
        $po->negotiation_id = null;
        $po->save();

        $po->delete();

        if ($invoiceId) {
            \App\Models\ProcurementInvoice::find($invoiceId)?->delete();
        }
        if ($negotiationId) {
            \App\Models\ProcurementContractNegotiation::find($negotiationId)?->delete();
        }
    }

    private function purchaseRequestEvidencePurchaseOrder(PurchaseRequest $purchaseRequest): ?ProcurementPurchaseOrder
    {
        $purchaseRequest->loadMissing('commitments');
        $commitmentIds = $purchaseRequest->commitments->pluck('id')->filter()->values();

        return ProcurementPurchaseOrder::query()
            ->where(function ($query) use ($purchaseRequest, $commitmentIds) {
                $query->where('purchase_request_id', $purchaseRequest->id);

                if ($commitmentIds->isNotEmpty()) {
                    $query->orWhereIn('budget_commitment_id', $commitmentIds);
                }
            })
            ->whereNotIn('status', ['cancelled'])
            ->latest()
            ->first();
    }

    public function approve(PurchaseRequest $purchaseRequest)
    {
        $this->assertPurchaseRequestInScope($purchaseRequest);

        if ($purchaseRequest->status === 'approved') {
            return back()->with('success', 'Purchase request is already approved.');
        }

        if (!in_array($purchaseRequest->status, ['draft', 'submitted'], true)) {
            return back()->withErrors([
                'status' => 'Only draft or submitted purchase requests can be approved.',
            ]);
        }

        if (!$purchaseRequest->commitments()->exists()) {
            return back()->withErrors([
                'status' => 'This purchase request has no linked budget commitments to approve.',
            ]);
        }

        $cancelledCommitments = $purchaseRequest->commitments()
            ->where('status', BudgetCommitment::STATUS_CANCELLED)
            ->exists();

        if ($cancelledCommitments) {
            return back()->withErrors([
                'status' => 'This purchase request has cancelled commitments and cannot be approved. Please edit or recreate the request first.',
            ]);
        }

        DB::transaction(function () use ($purchaseRequest) {
            $purchaseRequest->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'rejection_reason' => null,
                'rejected_by' => null,
                'rejected_at' => null,
            ]);

            $purchaseRequest->commitments()
                ->whereIn('status', [
                    BudgetCommitment::STATUS_DRAFT,
                    BudgetCommitment::STATUS_SUBMITTED,
                    BudgetCommitment::STATUS_APPROVED,
                ])
                ->update([
                    'status' => BudgetCommitment::STATUS_APPROVED,
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                    'rejection_reason' => null,
                ]);
        });

        return back()->with('success', 'Purchase request approved. Linked budget commitments are now approved.');
    }

    public function reject(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->assertPurchaseRequestInScope($purchaseRequest);

        if ($purchaseRequest->status === 'approved') {
            return back()->withErrors([
                'status' => 'Approved purchase requests cannot be rejected. Cancel the downstream procurement documents first if this decision must be reversed.',
            ]);
        }

        if (!in_array($purchaseRequest->status, ['draft', 'submitted'], true)) {
            return back()->withErrors([
                'status' => 'Only draft or submitted purchase requests can be rejected.',
            ]);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|min:5|max:1000',
        ], [
            'rejection_reason.required' => 'Please enter the reason this purchase request is being rejected.',
            'rejection_reason.min' => 'The rejection reason must be at least 5 characters.',
        ]);

        $approvedCommitments = $purchaseRequest->commitments()
            ->where('status', BudgetCommitment::STATUS_APPROVED)
            ->exists();

        if ($approvedCommitments) {
            return back()->withErrors([
                'status' => 'This purchase request has approved commitments and cannot be rejected from here.',
            ]);
        }

        DB::transaction(function () use ($purchaseRequest, $validated) {
            $purchaseRequest->update([
                'status' => 'rejected',
                'approved_by' => null,
                'approved_at' => null,
                'rejection_reason' => $validated['rejection_reason'],
                'rejected_by' => Auth::id(),
                'rejected_at' => now(),
            ]);

            $purchaseRequest->commitments()
                ->whereIn('status', [
                    BudgetCommitment::STATUS_DRAFT,
                    BudgetCommitment::STATUS_SUBMITTED,
                ])
                ->update([
                    'status' => BudgetCommitment::STATUS_CANCELLED,
                    'approved_by' => null,
                    'approved_at' => null,
                    'rejection_reason' => $validated['rejection_reason'],
                ]);
        });

        return back()->with('success', 'Purchase request rejected. Linked draft commitments have been cancelled and the budget has been released.');
    }

    private function scopedNodeIds(): ?array
    {
        $currentUser = Auth::user();

        if (!$currentUser || $currentUser->isAdmin()) {
            return null;
        }

        if (!$currentUser->governance_node_id) {
            return [];
        }

        return [$currentUser->governance_node_id];
    }

    private function assertPurchaseRequestInScope(PurchaseRequest $purchaseRequest): void
    {
        if (Auth::user()?->can('finance.purchase_requests.view_all') === true) {
            return;
        }

        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (!$purchaseRequest->governance_node_id || !in_array($purchaseRequest->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this purchase request.');
        }
    }

    private function purchaseRequestIsFullyDraft(PurchaseRequest $purchaseRequest): bool
    {
        if (($purchaseRequest->status ?? 'draft') !== 'draft') {
            return false;
        }

        $statuses = $purchaseRequest->commitments()->pluck('status');

        return $statuses->isNotEmpty()
            && $statuses->every(fn ($status) => $status === BudgetCommitment::STATUS_DRAFT);
    }
}
