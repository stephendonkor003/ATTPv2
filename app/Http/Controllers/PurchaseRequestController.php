<?php

namespace App\Http\Controllers;

use App\Mail\PurchaseRequestMail;
use App\Models\PurchaseRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        ])
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->orderByDesc('created_at')
            ->get();

        return view('finance.purchase-requests.index', compact('purchaseRequests', 'canViewAll'));
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
        ]);

        $yearSplits = $purchaseRequest->commitments
            ->groupBy('commitment_year')
            ->map(fn ($rows) => round((float) $rows->sum('commitment_amount'), 2))
            ->sortKeys();

        return view('finance.purchase-requests.show', compact('purchaseRequest', 'yearSplits'));
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
}
