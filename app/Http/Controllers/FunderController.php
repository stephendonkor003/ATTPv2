<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Funder;
use App\Mail\FundingPartnerWelcome;
use App\Models\PartnerActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FunderController extends Controller
{
    /**
     * Display a listing of funders.
     */
    public function index()
    {
        $funders = Funder::with([
            'relationshipManager:id,name,email',
            'portalUser:id,name,email',
            'lastContactOwner:id,name,email',
            'programFundings:id,funder_id,program_id,program_name,approved_amount,currency,status',
        ])
            ->withCount([
                'informationRequests as open_requests_count' => fn ($query) => $query->whereIn('status', ['pending', 'in_progress']),
            ])
            ->orderBy('name')
            ->get()
            ->each(fn (Funder $funder) => $this->appendPartnerInsights($funder));

        return view('finance.funders.index', compact('funders'));
    }

    /**
     * Show the form for creating a new funder.
     */
    public function create()
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('finance.funders.create', compact('users'));
    }

    /**
     * Store a newly created funder.
     */
    public function store(Request $request)
    {
        $validated = $this->validateFunder($request);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('funders/logos', 'public');
        }

        $funder = Funder::create($validated);

        // Handle portal access
        $portalEmailSent = $this->syncPortalAccess($funder, $validated, $request->boolean('has_portal_access'));

        return redirect()
            ->route('finance.funders.index')
            ->with('success', 'Partner created successfully.' . ($portalEmailSent ? ' Portal access email sent.' : ''));
    }

    /**
     * Display the specified funder profile.
     */
    public function show(Request $request, Funder $funder)
    {
        $funder = $this->loadPartnerDetails($funder);

        if ($request->boolean('modal') || $request->ajax()) {
            return view('finance.funders.partials.crm-body', compact('funder'));
        }

        return view('finance.funders.show', compact('funder'));
    }

    /**
     * Show the form for editing the specified funder.
     */
    public function edit(Funder $funder)
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('finance.funders.edit', compact('funder', 'users'));
    }

    /**
     * Update the specified funder.
     */
    public function update(Request $request, Funder $funder)
    {
        $validated = $this->validateFunder($request, $funder);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($funder->logo) {
                Storage::disk('public')->delete($funder->logo);
            }
            $validated['logo'] = $request->file('logo')->store('funders/logos', 'public');
        }

        $funder->update($validated);
        $this->syncPortalAccess($funder->fresh(['portalUser']), $validated, $request->boolean('has_portal_access'));

        return redirect()
            ->route('finance.funders.index')
            ->with('success', 'Partner updated successfully.');
    }

    /**
     * Download a PDF CRM summary for the specified partner.
     */
    public function pdf(Funder $funder)
    {
        $funder = $this->loadPartnerDetails($funder);

        $pdf = Pdf::loadView('finance.funders.pdf', compact('funder'))->setPaper('a4', 'landscape');

        return $pdf->download(
            'partner-crm-' . Str::slug($funder->name ?: 'partner') . '-' . now()->format('Ymd_His') . '.pdf'
        );
    }

    private function validateFunder(Request $request, ?Funder $funder = null): array
    {
        $contactRequirement = $request->boolean('has_portal_access') ? 'required' : 'nullable';
        $emailRule = Rule::unique('users', 'email');

        if ($funder?->user_id) {
            $emailRule = $emailRule->ignore($funder->user_id);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('myb_funders', 'name')->ignore($funder?->id),
            ],
            'type' => ['required', Rule::in(Funder::TYPES)],
            'currency' => 'required|string|max:10',
            'logo' => 'nullable|image|mimes:jpeg,jpg,png,svg|max:2048',
            'has_portal_access' => 'nullable|boolean',
            'contact_person' => [$contactRequirement, 'string', 'max:255'],
            'contact_email' => [$contactRequirement, 'email', 'max:255', $emailRule],
            'contact_phone' => 'nullable|string|max:20',
            'relationship_manager_id' => 'nullable|exists:users,id',
            'partnership_status' => ['nullable', Rule::in(Funder::PARTNERSHIP_STATUSES)],
            'partnership_started_at' => 'nullable|date',
            'next_follow_up_at' => 'nullable|date',
            'last_contact_at' => 'nullable|date',
            'last_contact_subject' => 'nullable|string|max:255',
            'last_contact_status' => ['nullable', Rule::in(Funder::COMMUNICATION_STATUSES)],
            'last_contact_user_id' => 'nullable|exists:users,id',
            'last_contact_notes' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $validated['has_portal_access'] = $request->boolean('has_portal_access');

        return $validated;
    }

    private function syncPortalAccess(Funder $funder, array $validated, bool $hasPortalAccess): bool
    {
        if (!$hasPortalAccess) {
            if ($funder->user_id) {
                $funder->update([
                    'user_id' => null,
                    'has_portal_access' => false,
                ]);
            }

            return false;
        }

        if ($funder->portalUser) {
            $funder->portalUser->update([
                'name' => $validated['contact_person'] ?: $funder->name,
                'email' => $validated['contact_email'],
            ]);

            if (!$funder->has_portal_access) {
                $funder->update(['has_portal_access' => true]);
            }

            return false;
        }

        $partnerRoleId = Role::where('name', 'Funding Partner')->value('id');
        $password = Str::random(12);

        $user = User::create([
            'name' => $validated['contact_person'],
            'email' => $validated['contact_email'],
            'password' => Hash::make($password),
            'user_type' => 'funding_partner',
            'role_id' => $partnerRoleId,
            'must_change_password' => true,
        ]);

        $funder->update([
            'user_id' => $user->id,
            'has_portal_access' => true,
        ]);

        try {
            Mail::to($user->email)->send(new FundingPartnerWelcome($funder->fresh(), $user, $password));
        } catch (\Exception $e) {
            \Log::error('Failed to send partner welcome email: ' . $e->getMessage());
        }

        PartnerActivityLog::logActivity(
            funderId: $funder->id,
            userId: $user->id,
            action: 'account_created',
            metadata: ['updated_by' => auth()->id()]
        );

        return true;
    }

    private function loadPartnerDetails(Funder $funder): Funder
    {
        $funder->load([
            'relationshipManager:id,name,email',
            'lastContactOwner:id,name,email',
            'portalUser:id,name,email,created_at',
            'programFundings' => fn ($query) => $query
                ->with([
                    'program:id,name',
                    'department:id,name',
                    'governanceNode:id,name',
                    'creator:id,name',
                ])
                ->orderByDesc('approved_amount'),
            'informationRequests' => fn ($query) => $query
                ->with([
                    'requester:id,name,email',
                    'responder:id,name,email',
                    'programFunding.program:id,name',
                ])
                ->latest(),
            'activityLogs' => fn ($query) => $query
                ->with('user:id,name,email')
                ->latest('created_at')
                ->limit(15),
        ]);

        $this->appendPartnerInsights($funder);

        return $funder;
    }

    private function appendPartnerInsights(Funder $funder): void
    {
        $fundings = $funder->relationLoaded('programFundings') ? $funder->programFundings : collect();
        $requests = $funder->relationLoaded('informationRequests') ? $funder->informationRequests : collect();
        $activities = $funder->relationLoaded('activityLogs') ? $funder->activityLogs : collect();

        $programKeys = $fundings
            ->map(function ($funding) {
                if ($funding->program_id) {
                    return $funding->program_id;
                }

                $name = trim((string) ($funding->program_name ?? ''));

                return $name !== '' ? Str::lower($name) : null;
            })
            ->filter()
            ->unique();

        $currencyBreakdown = $fundings
            ->groupBy(fn ($funding) => strtoupper($funding->currency ?: $funder->currency ?: 'N/A'))
            ->map(function ($items, $currency) {
                return [
                    'currency' => $currency,
                    'amount' => $items->sum(fn ($item) => (float) ($item->approved_amount ?? 0)),
                    'count' => $items->count(),
                ];
            })
            ->values();

        $totalAmountUsd = $currencyBreakdown
            ->where('currency', 'USD')
            ->sum('amount');

        $latestRequest = $requests->sortByDesc('created_at')->first();
        $latestActivity = $activities->sortByDesc('created_at')->first();
        $latestCommunication = $this->resolveLatestCommunication($funder, $latestRequest);

        $lastEngagementAt = collect([
            $funder->last_contact_at,
            $latestRequest?->created_at,
            $latestRequest?->responded_at,
            $latestActivity?->created_at,
        ])
            ->filter()
            ->sortByDesc(fn ($value) => $value->getTimestamp())
            ->first();

        $funder->setAttribute('total_programs_supported', $programKeys->count());
        $funder->setAttribute('total_amount_usd', $totalAmountUsd);
        $funder->setAttribute('currency_breakdown', $currencyBreakdown);
        $funder->setAttribute('has_non_usd_funding', $currencyBreakdown->contains(fn ($item) => $item['currency'] !== 'USD'));
        $funder->setAttribute('program_status_breakdown', $fundings->groupBy(fn ($funding) => $funding->status ?: 'unknown')->map->count());
        $funder->setAttribute('latest_request', $latestRequest);
        $funder->setAttribute('latest_activity', $latestActivity);
        $funder->setAttribute('latest_communication', $latestCommunication);
        $funder->setAttribute('last_engagement_at', $lastEngagementAt);
        $funder->setAttribute('open_requests_count', $funder->open_requests_count ?? $requests->whereIn('status', ['pending', 'in_progress'])->count());
        $funder->setAttribute('resolved_requests_count', $requests->whereIn('status', ['completed', 'rejected'])->count());
    }

    private function resolveLatestCommunication(Funder $funder, $latestRequest): ?array
    {
        $manualAt = $funder->last_contact_at;
        $requestAt = $latestRequest?->created_at;

        if ($manualAt && (!$requestAt || $manualAt->greaterThanOrEqualTo($requestAt))) {
            return [
                'source' => 'crm',
                'label' => 'CRM log',
                'occurred_at' => $manualAt,
                'subject' => $funder->last_contact_subject ?: 'Partner communication logged',
                'status' => $funder->last_contact_status,
                'owner_name' => $funder->lastContactOwner?->name,
                'notes' => $funder->last_contact_notes,
            ];
        }

        if (!$latestRequest) {
            return null;
        }

        $status = match ($latestRequest->status) {
            'completed', 'rejected' => 'attended',
            'in_progress' => 'follow_up_needed',
            default => 'pending',
        };

        return [
            'source' => 'request',
            'label' => 'Information request',
            'occurred_at' => $latestRequest->created_at,
            'subject' => $latestRequest->subject,
            'status' => $status,
            'owner_name' => $latestRequest->responder?->name,
            'notes' => $latestRequest->response ?: $latestRequest->message,
        ];
    }
}
