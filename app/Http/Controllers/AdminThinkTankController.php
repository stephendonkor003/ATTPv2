<?php

namespace App\Http\Controllers;

use App\Mail\ThinkTankPortalWelcome;
use App\Models\BudgetCommitment;
use App\Models\Consortium;
use App\Models\ConsortiumFundAllocation;
use App\Models\ConsortiumThinkTank;
use App\Models\ProcurementDisbursement;
use App\Models\ProcurementInvoice;
use App\Models\ProcurementPurchaseOrder;
use App\Models\Program;
use App\Models\ProgramFunding;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\SubActivity;
use App\Models\SystemAuditLog;
use App\Models\ThinkDataset;
use App\Models\User;
use App\Support\IpGeo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class AdminThinkTankController extends Controller
{
    public function directory(Request $request)
    {
        $summary = [
            'total' => ConsortiumThinkTank::count(),
            'active' => ConsortiumThinkTank::where('status', 'active')->count(),
            'system_dataset' => ThinkDataset::count(),
            'dataset_linked' => ConsortiumThinkTank::whereNotNull('think_dataset_id')->count(),
            'portal_linked' => ConsortiumThinkTank::whereNotNull('portal_user_id')->count(),
            'approved_ops' => (float) ConsortiumThinkTank::sum('budget_allocated') + (float) ConsortiumFundAllocation::sum('amount_allocated'),
            'transferred' => (float) ProcurementDisbursement::whereNotNull('think_tank_member_id')->sum('amount'),
        ];

        $thinkTanks = ConsortiumThinkTank::query()
            ->with(['consortium.programFunding.program', 'thinkDataset', 'portalUser', 'vendorUser'])
            ->withSum('fundAllocations', 'amount_allocated')
            ->withSum('transferDisbursements', 'amount')
            ->withCount(['reports', 'researchOutputs', 'procurementPlans', 'procurements'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = '%' . trim((string) $request->input('q')) . '%';
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', $search)
                        ->orWhere('country', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhereHas('thinkDataset', function ($datasetQuery) use ($search) {
                            $datasetQuery->where('tt_name_en', 'like', $search)
                                ->orWhere('ottd_id', 'like', $search)
                                ->orWhere('g_email', 'like', $search)
                                ->orWhere('website', 'like', $search);
                        });
                });
            })
            ->when($request->filled('consortium_id'), fn ($query) => $query->where('consortium_id', $request->input('consortium_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->input('portal') === 'linked', fn ($query) => $query->whereNotNull('portal_user_id'))
            ->when($request->input('portal') === 'unlinked', fn ($query) => $query->whereNull('portal_user_id'))
            ->when($request->input('dataset') === 'linked', fn ($query) => $query->whereNotNull('think_dataset_id'))
            ->when($request->input('dataset') === 'unlinked', fn ($query) => $query->whereNull('think_dataset_id'))
            ->orderBy('name')
            ->get();

        $thinkDatasets = ThinkDataset::query()
            ->orderBy('tt_name_en')
            ->get(['id', 'ottd_id', 'tt_name_en', 'country', 'g_email', 'website', 'is_validated']);

        $datasetLookup = $thinkDatasets
            ->mapWithKeys(fn (ThinkDataset $dataset) => [
                $dataset->id => [
                    'name' => $dataset->tt_name_en,
                    'country' => $dataset->country,
                    'email' => $dataset->g_email,
                    'website' => $dataset->website,
                ],
            ])
            ->all();

        return view('think-tanks-admin.directory', [
            'thinkTanks' => $thinkTanks,
            'consortia' => Consortium::orderBy('name')->get(),
            'thinkDatasets' => $thinkDatasets,
            'datasetLookup' => $datasetLookup,
            'roles' => ['lead', 'member', 'implementing_partner'],
            'statuses' => ['active', 'inactive', 'suspended', 'closed'],
            'summary' => $summary,
        ]);
    }

    public function store(Request $request)
    {
        $this->hydrateDatasetFields($request);
        $data = $request->validate($this->memberRules());
        $data['joined_at'] = $data['joined_at'] ?? now()->toDateString();

        [$portalUser, $temporaryPassword] = $this->resolvePortalUser($data);
        $data['portal_user_id'] = $portalUser?->id;

        $member = ConsortiumThinkTank::create($data);

        if ($portalUser?->email) {
            $this->sendWelcomeSafely($portalUser, $member, $temporaryPassword);
        }

        $this->auditAction('think_tank.created', 'Think tank profile created', [
            'think_tank_member_id' => $member->id,
            'think_tank_name' => $member->name,
            'consortium_id' => $member->consortium_id,
        ]);

        return redirect()
            ->route('think-tanks-admin.show', $member)
            ->with('success', 'Think tank profile created' . ($temporaryPassword ? '. Temporary password: ' . $temporaryPassword : '.'));
    }

    public function show(ConsortiumThinkTank $thinkTank)
    {
        $thinkTank->load([
            'consortium.programFunding.program',
            'portalUser',
            'fundAllocations.disbursementRequests',
            'transferDisbursements.purchaseOrder.budgetCommitment',
            'reports',
            'researchOutputs',
            'procurementPlans',
        ]);

        return view('think-tanks-admin.show', compact('thinkTank'));
    }

    public function update(Request $request, ConsortiumThinkTank $thinkTank)
    {
        $this->hydrateDatasetFields($request);
        $data = $request->validate($this->memberRules($thinkTank));
        [$portalUser] = $this->resolvePortalUser($data, false);
        $data['portal_user_id'] = $portalUser?->id ?? $data['portal_user_id'] ?? null;

        $thinkTank->update($data);

        $this->auditAction('think_tank.updated', 'Think tank profile updated', [
            'think_tank_member_id' => $thinkTank->id,
            'think_tank_name' => $thinkTank->name,
            'changes' => $thinkTank->getChanges(),
        ]);

        return redirect()
            ->route('think-tanks-admin.show', $thinkTank)
            ->with('success', 'Think tank profile updated.');
    }

    public function funding(Request $request)
    {
        $source = $this->fundingSource();
        $summary = $this->budgetSummary($source);

        $thinkTanks = ConsortiumThinkTank::query()
            ->with([
                'consortium',
                'transferDisbursements' => fn ($query) => $query
                    ->with(['purchaseOrder.budgetCommitment.purchaseRequest', 'fundAllocation', 'consortiumDisbursementRequest', 'recipientConfirmer'])
                    ->latest('paid_at')
                    ->latest(),
            ])
            ->withSum('fundAllocations', 'amount_allocated')
            ->withSum('transferDisbursements', 'amount')
            ->withCount([
                'transferDisbursements',
                'transferDisbursements as confirmed_transfers_count' => fn ($query) => $query->where('recipient_confirmation_status', 'confirmed'),
            ])
            ->orderBy('name')
            ->get();

        return view('think-tanks-admin.funding', [
            'source' => $source,
            'summary' => $summary,
            'thinkTanks' => $thinkTanks,
        ]);
    }

    public function createFunding()
    {
        $source = $this->fundingSource();
        $summary = $this->budgetSummary($source);

        return view('think-tanks-admin.funding-create', [
            'source' => $source,
            'summary' => $summary,
            'thinkTanks' => ConsortiumThinkTank::with('consortium')->orderBy('name')->get(),
        ]);
    }

    public function fundingHistory()
    {
        $source = $this->fundingSource();
        $summary = $this->budgetSummary($source);

        $transfers = ProcurementDisbursement::query()
            ->with([
                'thinkTankMember.consortium',
                'thinkTankMember.portalUser',
                'purchaseOrder.budgetCommitment.purchaseRequest',
                'purchaseOrder.budgetCommitment.approver',
                'purchaseOrder.budgetCommitment.creator',
                'fundAllocation',
                'consortiumDisbursementRequest',
                'recipientConfirmer',
            ])
            ->whereNotNull('think_tank_member_id')
            ->whereHas('purchaseOrder', fn ($query) => $query->where('po_type', 'think_tank_transfer'))
            ->latest('paid_at')
            ->latest()
            ->paginate(15);

        return view('think-tanks-admin.funding-history', [
            'source' => $source,
            'summary' => $summary,
            'transfers' => $transfers,
        ]);
    }

    public function storeFunding(Request $request)
    {
        $source = $this->fundingSource();
        if (! $source['programFunding'] || ! $source['subActivity']) {
            return back()->with('error', 'The African Think Tank Project / Funding to Think Tanks budget source could not be found.');
        }

        $data = $request->validate([
            'think_tank_member_id' => 'required|exists:attp_consortium_think_tanks,id',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|max:10',
            'payment_method' => 'required|string|max:80',
            'transfer_reference' => 'nullable|string|max:120',
            'paid_at' => 'required|date',
            'notes' => 'nullable|string|max:3000',
        ]);

        $summary = $this->budgetSummary($source);
        if ((float) $data['amount'] > (float) $summary['remaining']) {
            return back()
                ->withErrors(['amount' => 'Transfer exceeds remaining Funding to Think Tanks budget. Available: ' . number_format($summary['remaining'], 2)])
                ->withInput();
        }

        $member = ConsortiumThinkTank::with('consortium')->findOrFail($data['think_tank_member_id']);
        $currency = 'USD';
        $paidAt = Carbon::parse($data['paid_at']);

        DB::transaction(function () use ($data, $source, $member, $currency, $paidAt, $request) {
            $purchaseRequest = PurchaseRequest::create([
                'reference_no' => $this->nextReference('PR-TT'),
                'program_funding_id' => $source['programFunding']->id,
                'governance_node_id' => $source['programFunding']->governance_node_id,
                'allocation_level' => 'sub_activity',
                'allocation_id' => $source['subActivity']->id,
                'start_year' => (int) $paidAt->format('Y'),
                'commitment_date' => now()->toDateString(),
                'delivery_date' => $paidAt->toDateString(),
                'currency' => $currency,
                'total_amount' => $data['amount'],
                'description' => 'Funding transfer to think tank: ' . $member->name,
                'status' => 'approved',
                'created_by' => $request->user()?->id,
            ]);

            $commitment = BudgetCommitment::create([
                'purchase_request_id' => $purchaseRequest->id,
                'program_funding_id' => $source['programFunding']->id,
                'governance_node_id' => $source['programFunding']->governance_node_id,
                'allocation_level' => 'sub_activity',
                'allocation_id' => $source['subActivity']->id,
                'commitment_amount' => $data['amount'],
                'commitment_year' => (int) $paidAt->format('Y'),
                'status' => BudgetCommitment::STATUS_APPROVED,
                'description' => 'Funding to Think Tanks transfer for ' . $member->name,
                'created_by' => $request->user()?->id,
                'approved_by' => $request->user()?->id,
                'approved_at' => now(),
            ]);

            $allocation = ConsortiumFundAllocation::create([
                'consortium_id' => $member->consortium_id,
                'think_tank_member_id' => $member->id,
                'program_funding_id' => $source['programFunding']->id,
                'budget_line' => 'Funding to Think Tanks',
                'currency' => $currency,
                'amount_allocated' => $data['amount'],
                'amount_committed' => $data['amount'],
                'amount_disbursed' => $data['amount'],
                'status' => 'active',
                'notes' => $data['notes'] ?? null,
            ]);

            $invoice = ProcurementInvoice::create([
                'vendor_id' => $member->vendor_user_id ?: $member->portal_user_id,
                'sub_activity_id' => $source['subActivity']->id,
                'governance_node_id' => $source['programFunding']->governance_node_id,
                'invoice_month' => $paidAt->copy()->startOfMonth()->toDateString(),
                'reference_no' => ProcurementInvoice::generateReference(),
                'amount' => $data['amount'],
                'currency' => $currency,
                'status' => 'paid',
                'created_by' => $request->user()?->id,
                'approved_by' => $request->user()?->id,
                'approved_at' => now(),
                'notes' => 'Paid Funding to Think Tanks transfer for ' . $member->name . (! empty($data['notes']) ? ': ' . $data['notes'] : ''),
            ]);

            $purchaseOrder = ProcurementPurchaseOrder::create([
                'invoice_id' => $invoice->id,
                'budget_commitment_id' => $commitment->id,
                'sub_activity_id' => $source['subActivity']->id,
                'governance_node_id' => $source['programFunding']->governance_node_id,
                'consortium_id' => $member->consortium_id,
                'think_tank_member_id' => $member->id,
                'vendor_id' => $member->vendor_user_id ?: $member->portal_user_id,
                'reference_no' => ProcurementPurchaseOrder::generateThinkTankTransferReference($member),
                'po_type' => 'think_tank_transfer',
                'amount' => $data['amount'],
                'currency' => $currency,
                'status' => 'pending',
                'created_by' => $request->user()?->id,
                'issued_at' => $paidAt,
            ]);

            $disbursementRequest = $allocation->disbursementRequests()->create([
                'consortium_id' => $member->consortium_id,
                'think_tank_member_id' => $member->id,
                'request_code' => $this->nextReference('ATTP-DISB'),
                'amount_requested' => $data['amount'],
                'amount_approved' => $data['amount'],
                'currency' => $currency,
                'status' => 'paid',
                'purpose' => $data['notes'] ?? 'Funding to Think Tanks transfer',
                'requested_by' => $request->user()?->id,
                'requested_at' => now(),
                'reviewed_by' => $request->user()?->id,
                'reviewed_at' => now(),
                'paid_at' => $paidAt,
            ]);

            $disbursement = ProcurementDisbursement::create([
                'purchase_order_id' => $purchaseOrder->id,
                'vendor_id' => $member->vendor_user_id ?: $member->portal_user_id,
                'sub_activity_id' => $source['subActivity']->id,
                'governance_node_id' => $source['programFunding']->governance_node_id,
                'consortium_id' => $member->consortium_id,
                'think_tank_member_id' => $member->id,
                'fund_allocation_id' => $allocation->id,
                'consortium_disbursement_request_id' => $disbursementRequest->id,
                'reference_no' => ProcurementDisbursement::generateReference(),
                'amount' => $data['amount'],
                'currency' => $currency,
                'payment_method' => $data['payment_method'],
                'transfer_reference' => $data['transfer_reference'] ?? null,
                'status' => 'paid',
                'recipient_confirmation_status' => 'pending',
                'paid_at' => $paidAt,
                'created_by' => $request->user()?->id,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->auditAction('think_tank.transfer.created', 'Funding transfer recorded for think tank', [
                'disbursement_id' => $disbursement->id,
                'reference_no' => $disbursement->reference_no,
                'think_tank_member_id' => $member->id,
                'think_tank_name' => $member->name,
                'amount' => (float) $data['amount'],
                'currency' => $currency,
            ]);
        });

        return redirect()
            ->route('think-tanks-admin.funding.history')
            ->with('success', 'Funding transfer recorded. The think tank can now confirm receipt from its portal.');
    }

    public function updateFundingTransfer(Request $request, ProcurementDisbursement $transfer)
    {
        abort_unless((bool) $transfer->think_tank_member_id, 404);

        $source = $this->fundingSource();
        if (! $source['programFunding'] || ! $source['subActivity']) {
            return back()->with('error', 'The Funding to Think Tanks budget source could not be found.');
        }

        $transfer->load([
            'thinkTankMember',
            'purchaseOrder.invoice',
            'purchaseOrder.budgetCommitment.purchaseRequest',
            'fundAllocation',
            'consortiumDisbursementRequest',
        ]);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:80',
            'transfer_reference' => 'nullable|string|max:120',
            'paid_at' => 'required|date',
            'notes' => 'nullable|string|max:3000',
        ]);

        $oldAmount = (float) $transfer->amount;
        $newAmount = round((float) $data['amount'], 2);
        $summary = $this->budgetSummary($source);
        $availableIncludingThisTransfer = (float) $summary['remaining'] + $oldAmount;

        if ($newAmount > $availableIncludingThisTransfer) {
            return back()
                ->withErrors(['amount' => 'Updated transfer exceeds remaining Funding to Think Tanks budget. Available including this transfer: ' . number_format($availableIncludingThisTransfer, 2)])
                ->withInput();
        }

        $paidAt = Carbon::parse($data['paid_at']);
        $currency = 'USD';

        DB::transaction(function () use ($transfer, $data, $oldAmount, $newAmount, $paidAt, $currency, $request) {
            $transfer->update([
                'amount' => $newAmount,
                'currency' => $currency,
                'payment_method' => $data['payment_method'],
                'transfer_reference' => $data['transfer_reference'] ?? null,
                'paid_at' => $paidAt,
                'notes' => $data['notes'] ?? null,
            ]);

            $purchaseOrder = $transfer->purchaseOrder;
            if ($purchaseOrder) {
                $invoice = $purchaseOrder->invoice;
                if ($invoice) {
                    $invoice->update([
                        'vendor_id' => $purchaseOrder->vendor_id,
                        'sub_activity_id' => $purchaseOrder->sub_activity_id,
                        'governance_node_id' => $purchaseOrder->governance_node_id,
                        'invoice_month' => $paidAt->copy()->startOfMonth()->toDateString(),
                        'amount' => $newAmount,
                        'currency' => $currency,
                        'status' => 'paid',
                        'approved_by' => $request->user()?->id,
                        'approved_at' => $invoice->approved_at ?: now(),
                        'notes' => 'Paid Funding to Think Tanks transfer for ' . ($transfer->thinkTankMember?->name ?? 'think tank') . (! empty($data['notes']) ? ': ' . $data['notes'] : ''),
                    ]);
                } else {
                    $invoice = ProcurementInvoice::create([
                        'vendor_id' => $purchaseOrder->vendor_id,
                        'sub_activity_id' => $purchaseOrder->sub_activity_id,
                        'governance_node_id' => $purchaseOrder->governance_node_id,
                        'invoice_month' => $paidAt->copy()->startOfMonth()->toDateString(),
                        'reference_no' => ProcurementInvoice::generateReference(),
                        'amount' => $newAmount,
                        'currency' => $currency,
                        'status' => 'paid',
                        'created_by' => $request->user()?->id,
                        'approved_by' => $request->user()?->id,
                        'approved_at' => now(),
                        'notes' => 'Paid Funding to Think Tanks transfer for ' . ($transfer->thinkTankMember?->name ?? 'think tank') . (! empty($data['notes']) ? ': ' . $data['notes'] : ''),
                    ]);
                }

                $purchaseOrder->update([
                    'invoice_id' => $invoice->id,
                    'amount' => $newAmount,
                    'currency' => $currency,
                    'issued_at' => $paidAt,
                    'status' => $transfer->recipient_confirmation_status === 'confirmed' ? 'fully_paid' : 'pending',
                ]);
            }

            $commitment = $purchaseOrder?->budgetCommitment;
            if ($commitment) {
                $commitment->update([
                    'commitment_amount' => $newAmount,
                    'commitment_year' => (int) $paidAt->format('Y'),
                    'description' => 'Funding to Think Tanks transfer for ' . ($transfer->thinkTankMember?->name ?? 'think tank'),
                    'status' => BudgetCommitment::STATUS_APPROVED,
                    'approved_by' => $request->user()?->id,
                    'approved_at' => now(),
                ]);
            }

            $purchaseRequest = $commitment?->purchaseRequest;
            if ($purchaseRequest) {
                $purchaseRequest->update([
                    'start_year' => (int) $paidAt->format('Y'),
                    'delivery_date' => $paidAt->toDateString(),
                    'currency' => $currency,
                    'total_amount' => $newAmount,
                    'description' => 'Funding transfer to think tank: ' . ($transfer->thinkTankMember?->name ?? 'think tank'),
                    'status' => 'approved',
                ]);
            }

            if ($transfer->fundAllocation) {
                $transfer->fundAllocation->update([
                    'currency' => $currency,
                    'amount_allocated' => $newAmount,
                    'amount_committed' => $newAmount,
                    'amount_disbursed' => $newAmount,
                    'notes' => $data['notes'] ?? null,
                ]);
            }

            if ($transfer->consortiumDisbursementRequest) {
                $transfer->consortiumDisbursementRequest->update([
                    'amount_requested' => $newAmount,
                    'amount_approved' => $newAmount,
                    'currency' => $currency,
                    'purpose' => $data['notes'] ?? 'Funding to Think Tanks transfer',
                    'reviewed_by' => $request->user()?->id,
                    'reviewed_at' => now(),
                    'paid_at' => $paidAt,
                ]);
            }

            $this->auditAction('think_tank.transfer.updated', 'Funding transfer updated for think tank', [
                'disbursement_id' => $transfer->id,
                'reference_no' => $transfer->reference_no,
                'think_tank_member_id' => $transfer->think_tank_member_id,
                'old_amount' => $oldAmount,
                'new_amount' => $newAmount,
                'currency' => $currency,
            ]);
        });

        return back()->with('success', 'Funding transfer updated and the finance trail was synchronized.');
    }

    private function memberRules(?ConsortiumThinkTank $member = null): array
    {
        return [
            'consortium_id' => 'required|exists:attp_consortia,id',
            'think_dataset_id' => [
                'nullable',
                'exists:think_datasets,id',
                Rule::unique('attp_consortium_think_tanks', 'think_dataset_id')
                    ->where(fn ($query) => $query->where('consortium_id', request('consortium_id', $member?->consortium_id)))
                    ->ignore($member?->id),
            ],
            'name' => 'required|string|max:255',
            'country' => 'nullable|string|max:255',
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('attp_consortium_think_tanks', 'email')->ignore($member?->id),
            ],
            'role' => 'required|in:lead,member,implementing_partner',
            'status' => 'nullable|in:active,inactive,suspended,closed',
            'budget_allocated' => 'nullable|numeric|min:0',
            'joined_at' => 'nullable|date',
            'portal_user_id' => 'nullable|exists:users,id',
            'vendor_user_id' => 'nullable|exists:users,id',
            'au_sap_vendor_number' => 'nullable|string|max:120',
        ];
    }

    private function hydrateDatasetFields(Request $request): void
    {
        if (! $request->filled('think_dataset_id')) {
            return;
        }

        $dataset = ThinkDataset::find($request->input('think_dataset_id'));
        if (! $dataset) {
            return;
        }

        $request->merge([
            'name' => $request->input('name') ?: $dataset->tt_name_en,
            'country' => $request->input('country') ?: $dataset->country,
            'email' => $request->input('email') ?: $dataset->g_email,
        ]);
    }

    private function resolvePortalUser(array $data, bool $createWhenMissing = true): array
    {
        if (! empty($data['portal_user_id'])) {
            return [User::find($data['portal_user_id']), null];
        }

        if (! $createWhenMissing || empty($data['email'])) {
            return [null, null];
        }

        $roleId = Role::where('name', 'Think Tank User')->value('id');
        $password = Str::password(14);

        $user = User::firstOrCreate(
            ['email' => Str::lower($data['email'])],
            [
                'name' => $data['name'],
                'password' => Hash::make($password),
                'user_type' => 'think_tank',
                'role_id' => $roleId,
                'must_change_password' => true,
            ]
        );

        if ($user->user_type !== 'think_tank') {
            abort(422, 'This email is already assigned to another account type.');
        }

        $user->update(['role_id' => $user->role_id ?: $roleId]);

        return [$user, $user->wasRecentlyCreated ? $password : null];
    }

    private function sendWelcomeSafely(User $user, ConsortiumThinkTank $member, ?string $temporaryPassword): void
    {
        try {
            Mail::to($user->email)->send(new ThinkTankPortalWelcome($member, $member->consortium, $user, $temporaryPassword));
        } catch (Throwable) {
            // Mail failure should not block profile creation.
        }
    }

    private function fundingSource(): array
    {
        $program = Program::query()
            ->where('name', 'like', '%African Think%')
            ->orWhere('program_id', 'like', 'PROG00001%')
            ->with(['projects.activities.subActivities'])
            ->first();

        $programFunding = ProgramFunding::query()
            ->where('status', 'approved')
            ->when($program, fn ($query) => $query->where('program_id', $program->id))
            ->orderByDesc('approved_at')
            ->orderByDesc('approved_amount')
            ->first()
            ?: ProgramFunding::where('status', 'approved')
                ->where('program_name', 'like', '%African Think%')
                ->orderByDesc('approved_at')
                ->first();

        $subActivity = SubActivity::query()
            ->where('name', 'like', '%Funding to Think Tanks%')
            ->with('activity.project.program')
            ->first();

        return [
            'program' => $program ?: $subActivity?->activity?->project?->program,
            'programFunding' => $programFunding,
            'subActivity' => $subActivity,
        ];
    }

    private function budgetSummary(array $source): array
    {
        $subActivity = $source['subActivity'];
        $allocated = $subActivity ? (float) $subActivity->allocations()->sum('amount') : 0.0;
        $budget = $allocated;

        $transferred = $subActivity
            ? (float) ProcurementDisbursement::where('sub_activity_id', $subActivity->id)
                ->whereNotNull('think_tank_member_id')
                ->sum('amount')
            : 0.0;

        $confirmed = $subActivity
            ? (float) ProcurementDisbursement::where('sub_activity_id', $subActivity->id)
                ->whereNotNull('think_tank_member_id')
                ->where('recipient_confirmation_status', 'confirmed')
                ->sum('amount')
            : 0.0;

        $pending = max($transferred - $confirmed, 0);
        $remaining = max($budget - $transferred, 0);

        return [
            'allocated' => $allocated,
            'budget' => $budget,
            'transferred' => $transferred,
            'confirmed' => $confirmed,
            'pending' => $pending,
            'remaining' => $remaining,
            'transfer_rate' => $budget > 0 ? round(($transferred / $budget) * 100, 1) : 0,
            'remaining_rate' => $budget > 0 ? round(($remaining / $budget) * 100, 1) : 0,
            'confirmation_rate' => $transferred > 0 ? round(($confirmed / $transferred) * 100, 1) : 0,
        ];
    }

    private function nextReference(string $prefix): string
    {
        do {
            $reference = $prefix . '-' . now()->format('Y') . '-' . Str::upper(Str::random(6));
        } while (
            PurchaseRequest::where('reference_no', $reference)->exists()
            || ProcurementDisbursement::where('reference_no', $reference)->exists()
        );

        return $reference;
    }

    private function auditAction(string $action, string $message, array $payload = []): void
    {
        try {
            $request = request();

            SystemAuditLog::create([
                'user_id' => $request->user()?->id,
                'module' => 'think_tank_management',
                'action' => $action,
                'action_message' => $message,
                'description' => $message,
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'route_name' => $request->route()?->getName(),
                'ip_address' => $request->ip(),
                'country' => IpGeo::countryForIp($request->ip()),
                'user_agent' => $request->userAgent() ? substr((string) $request->userAgent(), 0, 1000) : null,
                'status_code' => 200,
                'payload' => $payload,
            ]);
        } catch (Throwable) {
            // Audit logging must never block the operational workflow.
        }
    }
}
