<?php

namespace App\Http\Controllers;

use App\Mail\PurchaseRequestMail;
use App\Models\BudgetCommitment;
use App\Models\ProcurementPurchaseOrder;
use App\Models\PurchaseRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

        return view('finance.purchase-requests.show', compact(
            'purchaseRequest',
            'yearSplits',
            'canApprovePurchaseRequests',
            'canEditPurchaseRequests',
            'canDeletePurchaseRequests'
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
