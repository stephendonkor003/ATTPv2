<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Mail\VendorRequestResponse;
use App\Models\VendorInformationRequest;
use App\Models\VendorMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

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
}
