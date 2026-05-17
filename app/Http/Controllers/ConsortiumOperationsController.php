<?php

namespace App\Http\Controllers;

use App\Mail\ThinkTankPortalWelcome;
use App\Models\Consortium;
use App\Models\ConsortiumActivityReport;
use App\Models\ConsortiumDisbursementRequest;
use App\Models\ConsortiumExpenseReport;
use App\Models\ConsortiumFundAllocation;
use App\Models\ConsortiumRiskFlag;
use App\Models\ConsortiumThinkTank;
use App\Models\ConsortiumWorkplan;
use App\Models\AuMemberState;
use App\Models\Funder;
use App\Models\ProgramFunding;
use App\Models\Procurement;
use App\Models\Role;
use App\Models\ThinkTankProcurementPlan;
use App\Models\ThinkTankResearchOutput;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class ConsortiumOperationsController extends Controller
{
    public function index(Request $request)
    {
        $query = Consortium::query()
            ->with(['funder', 'programFunding.program', 'secretariatManager'])
            ->withCount(['members', 'activityReports', 'riskFlags'])
            ->withSum('fundAllocations', 'amount_disbursed');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('q')) {
            $search = '%' . trim((string) $request->input('q')) . '%';
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', $search)
                    ->orWhere('code', 'like', $search)
                    ->orWhere('country', 'like', $search)
                    ->orWhere('region', 'like', $search);
            });
        }

        $consortia = $query->orderBy('name')->paginate(15)->withQueryString();
        $summary = $this->summaryMetrics();
        $firstPortalMember = ConsortiumThinkTank::orderBy('name')->first();

        return view('consortium-operations.index', compact('consortia', 'summary', 'firstPortalMember'));
    }

    public function runtimeOverview(Request $request)
    {
        $funder = $request->user()?->funderPortal;
        $summary = $this->summaryMetrics($funder);

        $consortia = Consortium::with([
            'funder',
            'members',
            'activityReports' => fn ($query) => $query->latest()->limit(5),
            'riskFlags' => fn ($query) => $query->where('status', 'open')->latest()->limit(5),
            'fundAllocations',
            'researchOutputs',
            'procurementPlans',
            'procurements.submissions',
            'procurements.awardedSubmission.submitter',
        ])
            ->when($funder, fn ($query) => $query->where('funder_id', $funder->id))
            ->orderBy('name')
            ->get();

        return view('consortium-operations.runtime-overview', compact('consortia', 'summary', 'funder'));
    }

    public function show(Consortium $consortium)
    {
        $consortium->load([
            'funder',
            'programFunding.program',
            'secretariatManager',
            'members.portalUser',
            'members.fundAllocations',
            'members.reports',
            'members.researchOutputs',
            'workplans',
            'fundAllocations.member',
            'activityReports.member',
            'activityReports.evidence',
            'disbursementRequests.member',
            'expenseReports',
            'riskFlags',
            'researchOutputs.member',
            'procurementPlans.member',
            'procurements.thinkTankMember',
            'procurements.submissions',
            'procurements.awardedSubmission.submitter',
        ]);

        $memberStates = AuMemberState::active()->ordered()->get(['id', 'name', 'code']);

        return view('consortium-operations.show', compact('consortium', 'memberStates'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:attp_consortia,code',
            'program_funding_id' => 'nullable|exists:myb_program_fundings,id',
            'funder_id' => 'nullable|exists:myb_funders,id',
            'country' => ['nullable', 'string', 'max:255', Rule::exists('myb_au_member_states', 'name')->where('is_active', true)],
            'region' => 'nullable|string|max:255',
            'currency' => 'nullable|string|max:10',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'mandate' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $data['code'] = $data['code'] ?? $this->nextCode('ATTP-CONS');
        $data['currency'] = $data['currency'] ?? 'USD';
        $data['secretariat_manager_id'] = $request->user()?->id;

        if (! empty($data['program_funding_id']) && empty($data['funder_id'])) {
            $data['funder_id'] = ProgramFunding::whereKey($data['program_funding_id'])->value('funder_id');
        }

        $consortium = Consortium::create($data);

        return redirect()->route('consortium-operations.show', $consortium)
            ->with('success', 'Consortium workspace created.');
    }

    public function addMember(Request $request, Consortium $consortium)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'think_dataset_id' => [
                'nullable',
                'exists:think_datasets,id',
                Rule::unique('attp_consortium_think_tanks', 'think_dataset_id')
                    ->where('consortium_id', $consortium->id)
                    ->ignore(null),
            ],
            'portal_user_id' => 'nullable|exists:users,id',
            'country' => ['nullable', 'string', 'max:255', Rule::exists('myb_au_member_states', 'name')->where('is_active', true)],
            'email' => 'nullable|email|max:255',
            'role' => 'required|in:lead,member,implementing_partner',
            'budget_allocated' => 'nullable|numeric|min:0',
            'initial_disbursed_amount' => 'nullable|numeric|min:0',
        ]);

        $data['consortium_id'] = $consortium->id;
        $data['joined_at'] = now()->toDateString();
        $initialDisbursedAmount = (float) ($data['initial_disbursed_amount'] ?? 0);
        unset($data['initial_disbursed_amount']);

        $temporaryPassword = null;
        $portalUser = null;

        if (empty($data['portal_user_id']) && ! empty($data['email'])) {
            $roleId = Role::where('name', 'Think Tank User')->value('id');
            $generatedPassword = Str::password(14);
            $user = User::firstOrCreate(
                ['email' => Str::lower($data['email'])],
                [
                    'name' => $data['name'],
                    'password' => Hash::make($generatedPassword),
                    'user_type' => 'think_tank',
                    'role_id' => $roleId,
                    'must_change_password' => true,
                ]
            );

            if ($user->wasRecentlyCreated) {
                $temporaryPassword = $generatedPassword;
            }

            if ($user->user_type !== 'think_tank') {
                return back()->withErrors(['email' => 'This email is already assigned to another account type.']);
            }

            $user->update(['role_id' => $user->role_id ?: $roleId]);
            $data['portal_user_id'] = $user->id;
            $portalUser = $user;
        } elseif (! empty($data['portal_user_id'])) {
            $portalUser = User::find($data['portal_user_id']);
        }

        $member = ConsortiumThinkTank::create($data);

        if ($initialDisbursedAmount > 0) {
            ConsortiumFundAllocation::create([
                'consortium_id' => $consortium->id,
                'think_tank_member_id' => $member->id,
                'budget_line' => 'Initial disbursement to ' . $member->name,
                'currency' => 'USD',
                'amount_allocated' => $initialDisbursedAmount,
                'amount_disbursed' => $initialDisbursedAmount,
                'status' => 'active',
                'notes' => 'Recorded when the think tank member was created.',
            ]);
        }

        $message = 'Think tank added to consortium.';
        if ($temporaryPassword) {
            $message .= ' Temporary portal password: ' . $temporaryPassword;
        }

        if ($portalUser?->email) {
            $mailSent = $this->sendThinkTankWelcomeSafely($portalUser, $member, $consortium, $temporaryPassword);
            if (! $mailSent) {
                $message .= $temporaryPassword
                    ? ' Email delivery failed, so share the temporary password manually.'
                    : ' Email delivery failed.';
            }
        }

        return back()->with('success', $message);
    }

    public function addWorkplan(Request $request, Consortium $consortium)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'period_label' => 'nullable|string|max:100',
            'starts_on' => 'nullable|date',
            'ends_on' => 'nullable|date|after_or_equal:starts_on',
            'planned_budget' => 'nullable|numeric|min:0',
            'objectives' => 'nullable|string',
        ]);

        $data['consortium_id'] = $consortium->id;
        $data['program_funding_id'] = $consortium->program_funding_id;

        ConsortiumWorkplan::create($data);

        return back()->with('success', 'Workplan added.');
    }

    public function storeReport(Request $request, Consortium $consortium)
    {
        $data = $request->validate([
            'think_tank_member_id' => 'nullable|exists:attp_consortium_think_tanks,id',
            'workplan_id' => 'nullable|exists:attp_workplans,id',
            'title' => 'required|string|max:255',
            'reporting_period_start' => 'nullable|date',
            'reporting_period_end' => 'nullable|date|after_or_equal:reporting_period_start',
            'progress_percent' => 'nullable|numeric|min:0|max:100',
            'funds_spent' => 'nullable|numeric|min:0',
            'summary' => 'nullable|string',
            'achievements' => 'nullable|string',
            'challenges' => 'nullable|string',
            'next_steps' => 'nullable|string',
            'evidence_title' => 'nullable|string|max:255',
            'evidence_file' => 'nullable|file|max:20480',
            'evidence_titles' => 'nullable|array',
            'evidence_titles.*' => 'nullable|string|max:255',
            'evidence_files' => 'nullable|array',
            'evidence_files.*' => 'file|max:20480',
        ]);

        DB::transaction(function () use ($data, $request, $consortium) {
            $report = ConsortiumActivityReport::create([
                ...collect($data)->except(['evidence_title', 'evidence_file', 'evidence_titles', 'evidence_files'])->all(),
                'consortium_id' => $consortium->id,
                'status' => 'submitted',
                'submitted_by' => $request->user()?->id,
                'submitted_at' => now(),
            ]);

            $evidenceFiles = collect($request->file('evidence_files', []))->filter();
            if ($request->hasFile('evidence_file')) {
                $evidenceFiles->prepend($request->file('evidence_file'));
            }

            $evidenceTitles = collect($data['evidence_titles'] ?? []);

            foreach ($evidenceFiles as $index => $file) {
                $path = $file->store("consortium-evidence/{$consortium->id}");

                $report->evidence()->create([
                    'uploaded_by' => $request->user()?->id,
                    'title' => $evidenceTitles->get($index) ?: ($data['evidence_title'] ?? $file->getClientOriginalName()),
                    'evidence_type' => 'document',
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size_bytes' => $file->getSize(),
                ]);
            }
        });

        return back()->with('success', 'Activity report submitted.');
    }

    public function reviewReport(Request $request, ConsortiumActivityReport $report)
    {
        $data = $request->validate([
            'status' => 'required|in:approved,revisions_requested,rejected',
            'review_notes' => 'nullable|string',
        ]);

        $report->update([
            'status' => $data['status'],
            'review_notes' => $data['review_notes'] ?? null,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Report review saved.');
    }

    public function addAllocation(Request $request, Consortium $consortium)
    {
        $data = $request->validate([
            'think_tank_member_id' => 'nullable|exists:attp_consortium_think_tanks,id',
            'budget_line' => 'required|string|max:255',
            'currency' => 'nullable|string|max:10',
            'amount_allocated' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        ConsortiumFundAllocation::create([
            ...$data,
            'consortium_id' => $consortium->id,
            'program_funding_id' => $consortium->program_funding_id,
            'currency' => $data['currency'] ?? $consortium->currency,
        ]);

        return back()->with('success', 'Fund allocation added.');
    }

    public function requestDisbursement(Request $request, Consortium $consortium)
    {
        $data = $request->validate([
            'think_tank_member_id' => 'nullable|exists:attp_consortium_think_tanks,id',
            'fund_allocation_id' => 'nullable|exists:attp_fund_allocations,id',
            'amount_requested' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|max:10',
            'purpose' => 'nullable|string',
        ]);

        ConsortiumDisbursementRequest::create([
            ...$data,
            'consortium_id' => $consortium->id,
            'request_code' => $this->nextCode('ATTP-DISB'),
            'currency' => $data['currency'] ?? $consortium->currency,
            'requested_by' => $request->user()?->id,
            'requested_at' => now(),
        ]);

        return back()->with('success', 'Disbursement request submitted.');
    }

    public function reviewDisbursement(Request $request, ConsortiumDisbursementRequest $disbursement)
    {
        $data = $request->validate([
            'status' => 'required|in:approved,rejected,paid',
            'amount_approved' => 'nullable|numeric|min:0',
            'review_notes' => 'nullable|string',
        ]);

        $disbursement->update([
            'status' => $data['status'],
            'amount_approved' => $data['amount_approved'] ?? $disbursement->amount_requested,
            'review_notes' => $data['review_notes'] ?? null,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
            'paid_at' => $data['status'] === 'paid' ? now() : $disbursement->paid_at,
        ]);

        if ($data['status'] === 'paid' && $disbursement->allocation) {
            $disbursement->allocation->increment('amount_disbursed', (float) $disbursement->amount_approved);
        }

        return back()->with('success', 'Disbursement review saved.');
    }

    public function storeExpense(Request $request, Consortium $consortium)
    {
        $data = $request->validate([
            'think_tank_member_id' => 'nullable|exists:attp_consortium_think_tanks,id',
            'activity_report_id' => 'nullable|exists:attp_activity_reports,id',
            'fund_allocation_id' => 'nullable|exists:attp_fund_allocations,id',
            'description' => 'required|string|max:255',
            'vendor_name' => 'nullable|string|max:255',
            'expense_date' => 'nullable|date',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|max:10',
            'receipt' => 'nullable|file|max:20480',
        ]);

        if ($request->hasFile('receipt')) {
            $data['receipt_path'] = $request->file('receipt')->store("consortium-expenses/{$consortium->id}");
        }

        unset($data['receipt']);

        ConsortiumExpenseReport::create([
            ...$data,
            'consortium_id' => $consortium->id,
            'expense_code' => $this->nextCode('ATTP-EXP'),
            'currency' => $data['currency'] ?? $consortium->currency,
            'submitted_by' => $request->user()?->id,
            'submitted_at' => now(),
        ]);

        return back()->with('success', 'Expense report submitted.');
    }

    public function addRisk(Request $request, Consortium $consortium)
    {
        $data = $request->validate([
            'think_tank_member_id' => 'nullable|exists:attp_consortium_think_tanks,id',
            'activity_report_id' => 'nullable|exists:attp_activity_reports,id',
            'title' => 'required|string|max:255',
            'category' => 'nullable|string|max:40',
            'severity' => 'required|in:low,medium,high,critical',
            'description' => 'nullable|string',
            'mitigation_plan' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        ConsortiumRiskFlag::create([
            ...$data,
            'consortium_id' => $consortium->id,
            'category' => $data['category'] ?? 'operational',
            'raised_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Risk flag added.');
    }

    private function summaryMetrics(?Funder $funder = null): array
    {
        $consortiumQuery = Consortium::query()->when($funder, fn ($query) => $query->where('funder_id', $funder->id));
        $consortiumIds = (clone $consortiumQuery)->pluck('id');

        return [
            'consortia' => (clone $consortiumQuery)->count(),
            'think_tanks' => ConsortiumThinkTank::whereIn('consortium_id', $consortiumIds)->count(),
            'submitted_reports' => ConsortiumActivityReport::whereIn('consortium_id', $consortiumIds)->count(),
            'pending_reports' => ConsortiumActivityReport::whereIn('consortium_id', $consortiumIds)->where('status', 'submitted')->count(),
            'funds_allocated' => ConsortiumFundAllocation::whereIn('consortium_id', $consortiumIds)->sum('amount_allocated'),
            'funds_disbursed' => ConsortiumFundAllocation::whereIn('consortium_id', $consortiumIds)->sum('amount_disbursed'),
            'funds_spent' => ConsortiumFundAllocation::whereIn('consortium_id', $consortiumIds)->sum('amount_spent'),
            'open_risks' => ConsortiumRiskFlag::whereIn('consortium_id', $consortiumIds)->where('status', 'open')->count(),
            'research_outputs' => ThinkTankResearchOutput::whereIn('consortium_id', $consortiumIds)->count(),
            'procurement_plans' => ThinkTankProcurementPlan::whereIn('consortium_id', $consortiumIds)->count(),
            'procurement_opportunities' => Procurement::whereIn('consortium_id', $consortiumIds)->where('procurement_owner_type', 'think_tank')->count(),
            'procurement_applications' => \App\Models\FormSubmission::whereIn('procurement_id', Procurement::whereIn('consortium_id', $consortiumIds)->pluck('id'))->count(),
        ];
    }

    private function nextCode(string $prefix): string
    {
        return $prefix . '-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5));
    }

    private function sendThinkTankWelcomeSafely(User $user, ConsortiumThinkTank $member, Consortium $consortium, ?string $temporaryPassword): bool
    {
        try {
            Mail::to($user->email)->send(new ThinkTankPortalWelcome($member, $consortium, $user, $temporaryPassword));

            return true;
        } catch (Throwable $exception) {
            Log::warning('Think tank portal welcome email could not be sent.', [
                'user_id' => $user->id,
                'think_tank_member_id' => $member->id,
                'consortium_id' => $consortium->id,
                'error' => $exception->getMessage(),
            ]);

            if (app()->environment(['local', 'testing']) && $temporaryPassword) {
                Log::info('Local development think tank temporary password fallback.', [
                    'email' => $user->email,
                    'temporary_password' => $temporaryPassword,
                    'think_tank' => $member->name,
                    'consortium' => $consortium->name,
                ]);
            }

            return false;
        }
    }
}
