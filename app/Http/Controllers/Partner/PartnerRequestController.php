<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Funder;
use App\Models\PartnerInformationRequest;
use App\Models\ProgramFunding;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use App\Notifications\PartnerRequestCreatedNotification;

class PartnerRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:partner.requests.view']);
    }

    /**
     * Display a list of all information requests from this partner
     */
    public function index()
    {
        $funder = $this->getPartnerFunder();

        $requests = PartnerInformationRequest::with([
            'programFunding.program',
            'responder'
        ])
            ->where('funder_id', $funder->id)
            ->latest()
            ->get();

        return view('partner.requests.index', compact('funder', 'requests'));
    }

    /**
     * Show the form for creating a new information request
     */
    public function create()
    {
        $this->middleware('permission:partner.requests.create');

        $funder = $this->getPartnerFunder();

        // Get programs this funder is funding
        $fundings = ProgramFunding::with('program')
            ->where('funder_id', $funder->id)
            ->where('status', 'approved')
            ->orderBy('program_name')
            ->get();

        return view('partner.requests.create', compact('funder', 'fundings'));
    }

    /**
     * Store a newly created information request
     */
    public function store(Request $request)
    {
        $this->middleware('permission:partner.requests.create');

        $funder = $this->getPartnerFunder();

        $validated = $request->validate([
            'program_funding_id' => 'nullable|exists:myb_program_fundings,id',
            'request_type'       => 'required|in:financial_report,progress_update,documentation,other',
            'subject'            => 'required|string|max:255',
            'message'            => 'required|string|min:10',
            'priority'           => 'required|in:low,normal,high,urgent',
        ]);

        // Verify the program funding belongs to this funder
        if ($validated['program_funding_id']) {
            $funding = ProgramFunding::where('id', $validated['program_funding_id'])
                ->where('funder_id', $funder->id)
                ->first();

            if (!$funding) {
                return back()->withErrors(['program_funding_id' => 'Invalid program selection.']);
            }
        }

        $infoRequest = PartnerInformationRequest::create([
            'funder_id'           => $funder->id,
            'program_funding_id'  => $validated['program_funding_id'],
            'requested_by'        => Auth::id(),
            'request_type'        => $validated['request_type'],
            'subject'             => $validated['subject'],
            'message'             => $validated['message'],
            'priority'            => $validated['priority'],
            'status'              => 'pending',
        ]);

        // Notify System Admin users
        try {
            $admins = User::whereHas('role', function ($q) {
                $q->where('name', 'System Admin');
            })->get();

            if ($admins->count() > 0) {
                Notification::send($admins, new PartnerRequestCreatedNotification($infoRequest));
            }
        } catch (\Exception $e) {
            \Log::error('Failed to notify admins about partner request: ' . $e->getMessage());
        }

        return redirect()
            ->route('partner.requests.index')
            ->with('success', 'Your information request has been submitted successfully.');
    }

    /**
     * Display the specified information request
     */
    public function show($id)
    {
        $funder = $this->getPartnerFunder();

        $request = PartnerInformationRequest::with([
            'programFunding.program',
            'responder'
        ])
            ->where('funder_id', $funder->id)
            ->findOrFail($id);

        return view('partner.requests.show', compact('funder', 'request'));
    }

    /**
     * Get the authenticated partner's funder record
     */
    protected function getPartnerFunder(): Funder
    {
        $funder = Auth::user()?->partnerFunder();

        if (!$funder) {
            abort(403, 'No funder account associated with this user.');
        }

        return $funder;
    }
}
