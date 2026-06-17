<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Mail\VendorRequestResponse;
use App\Models\VendorDocument;
use App\Models\VendorInformationRequest;
use App\Models\VendorMessage;
use App\Models\VendorPurchaseRequest;
use App\Models\VendorReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class VendorRequestManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner', 'permission:vendor.requests.manage']);
    }

    public function messagesIndex()
    {
        $messages = VendorMessage::with(['user', 'procurement'])
            ->latest()
            ->get();

        return view('vendor.admin.requests.messages.index', compact('messages'));
    }

    public function messagesShow(VendorMessage $message)
    {
        $message->load(['user', 'procurement']);

        return view('vendor.admin.requests.messages.show', compact('message'));
    }

    public function messagesRespond(Request $request, VendorMessage $message)
    {
        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,closed',
            'response' => 'required|string|min:5',
        ]);

        $message->update([
            'status' => $validated['status'],
            'response' => $validated['response'],
            'responded_by' => Auth::id(),
            'responded_at' => now(),
        ]);

        if ($message->user) {
            Mail::to($message->user->email)->send(new VendorRequestResponse('message', $message));
        }

        return back()->with('success', 'Response sent to vendor.');
    }

    public function informationIndex()
    {
        $requests = VendorInformationRequest::with(['user', 'procurement'])
            ->latest()
            ->get();

        return view('vendor.admin.requests.information.index', compact('requests'));
    }

    public function informationShow(VendorInformationRequest $requestRecord)
    {
        $requestRecord->load(['user', 'procurement']);

        return view('vendor.admin.requests.information.show', compact('requestRecord'));
    }

    public function informationRespond(Request $request, VendorInformationRequest $requestRecord)
    {
        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,closed',
            'response' => 'required|string|min:5',
        ]);

        $requestRecord->update([
            'status' => $validated['status'],
            'response' => $validated['response'],
            'responded_by' => Auth::id(),
            'responded_at' => now(),
        ]);

        if ($requestRecord->user) {
            Mail::to($requestRecord->user->email)->send(new VendorRequestResponse('information', $requestRecord));
        }

        return back()->with('success', 'Response sent to vendor.');
    }

    public function purchaseRequestsIndex()
    {
        $requests = VendorPurchaseRequest::with(['user', 'procurement', 'purchaseOrder'])
            ->latest()
            ->get();

        return view('vendor.admin.requests.purchase-requests.index', compact('requests'));
    }

    public function purchaseRequestsShow(VendorPurchaseRequest $purchaseRequest)
    {
        $purchaseRequest->load(['user', 'procurement', 'purchaseOrder', 'items', 'documents']);

        return view('vendor.admin.requests.purchase-requests.show', compact('purchaseRequest'));
    }

    public function purchaseRequestsRespond(Request $request, VendorPurchaseRequest $purchaseRequest)
    {
        $validated = $request->validate([
            'status' => 'required|in:submitted,in_review,approved,rejected,converted,completed',
            'admin_response' => 'nullable|string|max:5000',
        ]);

        $purchaseRequest->update([
            'status' => $validated['status'],
            'admin_response' => $validated['admin_response'] ?? null,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Vendor purchase request updated.');
    }

    public function reportsIndex()
    {
        $reports = VendorReport::with(['user', 'procurement', 'purchaseOrder'])
            ->latest()
            ->get();

        return view('vendor.admin.requests.reports.index', compact('reports'));
    }

    public function reportsShow(VendorReport $report)
    {
        $report->load(['user', 'procurement', 'purchaseOrder', 'documents']);

        return view('vendor.admin.requests.reports.show', compact('report'));
    }

    public function reportsRespond(Request $request, VendorReport $report)
    {
        $validated = $request->validate([
            'status' => 'required|in:submitted,reviewed,accepted,rejected',
            'admin_feedback' => 'nullable|string|max:5000',
        ]);

        $report->update([
            'status' => $validated['status'],
            'admin_feedback' => $validated['admin_feedback'] ?? null,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Vendor report review saved.');
    }

    public function downloadDocument(VendorDocument $document)
    {
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }
}
