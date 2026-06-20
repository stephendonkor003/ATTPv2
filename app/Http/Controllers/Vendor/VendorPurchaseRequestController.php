<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\SubActivity;
use App\Models\User;
use App\Models\VendorDocument;
use App\Models\VendorPurchaseRequest;
use App\Services\VendorPurchaseRequestAdminNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VendorPurchaseRequestController extends Controller
{
    public function index(Request $request)
    {
        return $this->redirectToPurchaseOrders();

        $user = $this->vendor($request);

        $requests = VendorPurchaseRequest::with(['procurement', 'subActivity.activity.project.program', 'purchaseOrder', 'items'])
            ->where('user_id', $user->id)
            ->where('request_type', 'purchase_request')
            ->latest()
            ->get();

        $stats = [
            'total' => $requests->count(),
            'submitted' => $requests->where('status', 'submitted')->count(),
            'in_review' => $requests->where('status', 'in_review')->count(),
            'revision_requested' => $requests->where('status', 'revision_requested')->count(),
            'approved' => $requests->whereIn('status', ['approved', 'converted', 'completed'])->count(),
        ];

        return view('vendor.purchase-requests.index', [
            'requests' => $requests,
            'stats' => $stats,
            'pageTitle' => 'Purchase Requests',
        ]);
    }

    public function create(Request $request)
    {
        return $this->redirectToPurchaseOrders();

        $user = $this->vendor($request);

        return view('vendor.purchase-requests.create', [
            'pageTitle' => 'Create Purchase Request',
            'procurements' => $this->vendorProcurements($user),
        ]);
    }

    public function store(Request $request)
    {
        return $this->redirectToPurchaseOrders();

        $user = $this->vendor($request);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'procurement_id' => ['nullable', Rule::exists('procurements', 'id')],
            'sub_activity_id' => ['required', Rule::exists('myb_sub_activities', 'id')],
            'currency' => 'required|string|max:10',
            'needed_by' => 'nullable|date',
            'priority' => 'required|in:low,normal,high,urgent',
            'description' => 'nullable|string|max:5000',
            'items' => 'nullable|array',
            'items.*.item_name' => 'nullable|string|max:255',
            'items.*.description' => 'nullable|string|max:1000',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.delivery_date' => 'nullable|date',
            'documents.*' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,zip|max:20480',
        ]);

        $allowedFundingSources = $this->vendorProcurements($user)->pluck('id')->all();
        if (! in_array($data['sub_activity_id'], $allowedFundingSources, true)) {
            return back()->withErrors([
                'sub_activity_id' => 'You do not have access to this procurement funding source.',
            ])->withInput();
        }

        $lineItems = collect($data['items'] ?? [])
            ->filter(fn ($item) => filled($item['item_name'] ?? null))
            ->map(function ($item) {
                $quantity = (float) ($item['quantity'] ?? 1);
                $unitPrice = (float) ($item['unit_price'] ?? 0);

                return [
                    'item_name' => $item['item_name'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'amount' => round($quantity * $unitPrice, 2),
                    'delivery_date' => $item['delivery_date'] ?? null,
                ];
            })
            ->values();

        $total = $lineItems->sum('amount');

        if ($lineItems->isEmpty() || $total <= 0) {
            return back()
                ->withErrors(['items' => 'Add at least one line item with quantity and unit price.'])
                ->withInput();
        }

        $vendorRequest = DB::transaction(function () use ($request, $user, $data, $lineItems, $total) {
            $vendorRequest = VendorPurchaseRequest::create([
                'user_id' => $user->id,
                'procurement_id' => $data['procurement_id'] ?? null,
                'sub_activity_id' => $data['sub_activity_id'],
                'purchase_order_id' => null,
                'reference_no' => VendorPurchaseRequest::generateReference(),
                'request_type' => 'purchase_request',
                'title' => $data['title'],
                'requested_amount' => $total,
                'currency' => strtoupper($data['currency']),
                'needed_by' => $data['needed_by'] ?? null,
                'priority' => $data['priority'],
                'status' => 'submitted',
                'description' => $data['description'] ?? null,
                'business_justification' => null,
            ]);

            $vendorRequest->items()->createMany($lineItems->all());

            $this->storeDocuments($request, $user, $vendorRequest);

            return $vendorRequest;
        });

        app(VendorPurchaseRequestAdminNotificationService::class)->notify($vendorRequest);

        return redirect()
            ->route('vendor.purchase-requests.show', $vendorRequest)
            ->with('success', 'Request submitted to the ATTP administration team.');
    }

    public function edit(Request $request, VendorPurchaseRequest $purchaseRequest)
    {
        return $this->redirectToPurchaseOrders();

        $user = $this->vendor($request);
        abort_unless((string) $purchaseRequest->user_id === (string) $user->id, 403);
        abort_unless($purchaseRequest->status === 'revision_requested', 403);

        $purchaseRequest->load(['items', 'documents', 'subActivity.activity.project.program']);

        return view('vendor.purchase-requests.create', [
            'pageTitle' => 'Edit Purchase Request',
            'procurements' => $this->vendorProcurements($user),
            'purchaseRequest' => $purchaseRequest,
            'formAction' => route('vendor.purchase-requests.update', $purchaseRequest),
            'formMethod' => 'PUT',
            'submitButtonText' => 'Resubmit Request',
        ]);
    }

    public function update(Request $request, VendorPurchaseRequest $purchaseRequest)
    {
        return $this->redirectToPurchaseOrders();

        $user = $this->vendor($request);
        abort_unless((string) $purchaseRequest->user_id === (string) $user->id, 403);
        abort_unless($purchaseRequest->status === 'revision_requested', 403);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'procurement_id' => ['nullable', Rule::exists('procurements', 'id')],
            'sub_activity_id' => ['required', Rule::exists('myb_sub_activities', 'id')],
            'currency' => 'required|string|max:10',
            'needed_by' => 'nullable|date',
            'priority' => 'required|in:low,normal,high,urgent',
            'description' => 'nullable|string|max:5000',
            'items' => 'nullable|array',
            'items.*.item_name' => 'nullable|string|max:255',
            'items.*.description' => 'nullable|string|max:1000',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.delivery_date' => 'nullable|date',
            'documents.*' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,zip|max:20480',
            'remove_documents' => 'nullable|array',
            'remove_documents.*' => ['uuid', Rule::exists('vendor_documents', 'id')],
        ]);

        $allowedFundingSources = $this->vendorProcurements($user)->pluck('id')->all();
        if (! in_array($data['sub_activity_id'], $allowedFundingSources, true)) {
            return back()->withErrors([
                'sub_activity_id' => 'You do not have access to this procurement funding source.',
            ])->withInput();
        }

        $lineItems = $this->lineItemsFrom($data);
        $total = $lineItems->sum('amount');

        if ($lineItems->isEmpty() || $total <= 0) {
            return back()
                ->withErrors(['items' => 'Add at least one line item with quantity and unit price.'])
                ->withInput();
        }

        DB::transaction(function () use ($request, $user, $purchaseRequest, $data, $lineItems, $total) {
            $purchaseRequest->update([
                'procurement_id' => $data['procurement_id'] ?? null,
                'sub_activity_id' => $data['sub_activity_id'],
                'title' => $data['title'],
                'requested_amount' => $total,
                'currency' => strtoupper($data['currency']),
                'needed_by' => $data['needed_by'] ?? null,
                'priority' => $data['priority'],
                'status' => 'submitted',
                'description' => $data['description'] ?? null,
                'business_justification' => null,
                'admin_response' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);

            $purchaseRequest->items()->delete();
            $purchaseRequest->items()->createMany($lineItems->all());

            $this->removeDocuments($purchaseRequest, $user, $data['remove_documents'] ?? []);
            $this->storeDocuments($request, $user, $purchaseRequest);
        });

        app(VendorPurchaseRequestAdminNotificationService::class)->notify($purchaseRequest->fresh());

        return redirect()
            ->route('vendor.purchase-requests.show', $purchaseRequest)
            ->with('success', 'Request updated and resubmitted to the ATTP administration team.');
    }

    public function show(Request $request, VendorPurchaseRequest $purchaseRequest)
    {
        return $this->redirectToPurchaseOrders();

        $user = $this->vendor($request);
        abort_unless((string) $purchaseRequest->user_id === (string) $user->id, 403);

        $purchaseRequest->load(['procurement', 'subActivity.activity.project.program', 'purchaseOrder', 'items', 'documents']);

        return view('vendor.purchase-requests.show', compact('purchaseRequest'));
    }

    public function download(Request $request, VendorPurchaseRequest $purchaseRequest, VendorDocument $document)
    {
        return $this->redirectToPurchaseOrders();

        $user = $this->vendor($request);
        abort_unless((string) $purchaseRequest->user_id === (string) $user->id, 403);
        abort_unless((string) $document->user_id === (string) $user->id, 403);
        abort_unless($document->source_type === 'vendor_purchase_request' && (string) $document->source_id === (string) $purchaseRequest->id, 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }

    private function vendor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user && $user->user_type === 'vendor', 403);
        abort_if($user->is_disabled || $user->is_blacklisted, 403);

        return $user;
    }

    private function redirectToPurchaseOrders()
    {
        return redirect()
            ->route('vendor.purchase-orders.index')
            ->with('info', 'Vendor purchase requests are no longer submitted from the portal. Open an assigned purchase order to upload deliverable evidence.');
    }

    private function vendorProcurements(User $user)
    {
        return SubActivity::query()
            ->with(['activity.project.program'])
            ->whereIn('id', $user->vendorSubActivityAssignments()->pluck('sub_activity_id'))
            ->orderBy('name')
            ->get();
    }

    private function lineItemsFrom(array $data)
    {
        return collect($data['items'] ?? [])
            ->filter(fn ($item) => filled($item['item_name'] ?? null))
            ->map(function ($item) {
                $quantity = (float) ($item['quantity'] ?? 1);
                $unitPrice = (float) ($item['unit_price'] ?? 0);

                return [
                    'item_name' => $item['item_name'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'amount' => round($quantity * $unitPrice, 2),
                    'delivery_date' => $item['delivery_date'] ?? null,
                ];
            })
            ->values();
    }

    private function removeDocuments(VendorPurchaseRequest $purchaseRequest, User $user, array $documentIds): void
    {
        if (empty($documentIds)) {
            return;
        }

        $documents = $purchaseRequest->documents()
            ->where('user_id', $user->id)
            ->whereIn('id', $documentIds)
            ->get();

        foreach ($documents as $document) {
            if ($document->file_path && Storage::disk('local')->exists($document->file_path)) {
                Storage::disk('local')->delete($document->file_path);
            }

            $document->delete();
        }
    }

    private function storeDocuments(Request $request, User $user, VendorPurchaseRequest $vendorRequest): void
    {
        foreach ($request->file('documents', []) as $file) {
            if (!$file) {
                continue;
            }

            $path = $file->store("vendor-documents/{$user->id}/purchase-requests/{$vendorRequest->id}", 'local');

            VendorDocument::create([
                'user_id' => $user->id,
                'uploaded_by' => $user->id,
                'source_type' => 'vendor_purchase_request',
                'source_id' => $vendorRequest->id,
                'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'document_type' => $vendorRequest->request_type,
                'description' => $vendorRequest->reference_no,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size_bytes' => $file->getSize(),
                'tags' => [$vendorRequest->request_type, 'submitted'],
            ]);
        }
    }
}
